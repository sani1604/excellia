<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiAssistantService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::getValue(
            'ai_openrouter_enabled',
            config('excellia.ai.enabled', false)
        );
    }

    public function isAllowedForUser(?User $user): bool
    {
        if (! $user || ! $user->is_active || ! $this->isEnabled()) {
            return false;
        }

        $roleKey = $user->primaryRole?->key;
        if ($roleKey === Role::SUPER_ADMIN) {
            return true;
        }

        if ($roleKey === Role::TEACHER) {
            return (bool) SystemSetting::getValue('ai_allow_teachers', config('excellia.ai.allow_teachers', true));
        }

        return (bool) SystemSetting::getValue('ai_allow_coordinators', config('excellia.ai.allow_coordinators', true));
    }

    /**
     * Generates school-focused AI text to save work for Teachers, Coordinators, and School Leaders.
     * Uses OpenRouter Chat Completions API when API key is configured; falls back to structured
     * curriculum templates in local/demo mode if no key is stored yet.
     */
    public function generateAssistance(User $actor, string $action, array $context): array
    {
        if (! $this->isEnabled()) {
            throw new DomainRuleException('AI Assistant is currently disabled by Super Admin.');
        }

        if (! $this->isAllowedForUser($actor)) {
            throw new DomainRuleException('AI Assistant is not enabled for your role.');
        }

        [$systemPrompt, $userPrompt, $fallbackText] = $this->buildPrompts($action, $context);

        $apiKey = SystemSetting::getSecret('openrouter_api_key', config('excellia.ai.api_key'));
        $model = (string) SystemSetting::getValue('openrouter_model', config('excellia.ai.model', 'openai/gpt-4o-mini'));
        $baseUrl = rtrim((string) SystemSetting::getValue('openrouter_base_url', config('excellia.ai.base_url', 'https://openrouter.ai/api/v1')), '/');

        $source = 'smart_template';
        $content = $fallbackText;

        if ($apiKey !== null && $apiKey !== '') {
            $resp = Http::timeout(20)
                ->withToken($apiKey)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'https://excelliaschool.edu.in'),
                    'X-Title' => 'Excellia School Academic Portal',
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model ?: 'openai/gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 450,
                ]);

            if (! $resp->successful()) {
                $err = (string) ($resp->json('error.message') ?: ('HTTP ' . $resp->status()));
                throw new RuntimeException("OpenRouter API error: {$err}");
            }

            $aiReply = trim((string) $resp->json('choices.0.message.content', ''));
            if ($aiReply === '') {
                throw new RuntimeException('OpenRouter returned an empty response.');
            }

            $content = $aiReply;
            $source = 'openrouter';
        }

        $this->audit->log('ai.assistance_generated', 'ai_assistant', null, null, [
            'action' => $action,
            'model' => $model,
            'source' => $source,
        ], $actor);

        return [
            'content' => $content,
            'model' => $model,
            'source' => $source,
        ];
    }

    /**
     * Strictly verifies live OpenRouter API connectivity from Super Admin Settings.
     */
    public function testOpenRouterConnection(): array
    {
        $apiKey = SystemSetting::getSecret('openrouter_api_key', config('excellia.ai.api_key'));
        if (! $apiKey) {
            throw new RuntimeException('OpenRouter API Key (sk-or-...) is not saved yet. Please paste and save your key first.');
        }

        $model = (string) SystemSetting::getValue('openrouter_model', config('excellia.ai.model', 'openai/gpt-4o-mini'));
        $baseUrl = rtrim((string) SystemSetting::getValue('openrouter_base_url', config('excellia.ai.base_url', 'https://openrouter.ai/api/v1')), '/');

        $resp = Http::timeout(15)
            ->withToken($apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url', 'https://excelliaschool.edu.in'),
                'X-Title' => 'Excellia School Academic Portal',
                'Accept' => 'application/json',
            ])
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model ?: 'openai/gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => 'Reply in one short sentence confirming Excellia School AI assistant is connected.'],
                ],
                'max_tokens' => 60,
            ]);

        if (! $resp->successful()) {
            $err = (string) ($resp->json('error.message') ?: ('HTTP ' . $resp->status()));
            throw new RuntimeException("OpenRouter connection test failed: {$err}");
        }

        return [
            'model' => (string) ($resp->json('model') ?: $model),
            'reply' => trim((string) $resp->json('choices.0.message.content', 'Connected successfully.')),
        ];
    }

    private function buildPrompts(string $action, array $ctx): array
    {
        $schoolTone = 'You are a warm, practical K-12 school academic coordinator assistant at Excellia School (India, CBSE/NCERT aligned). Write clear, concise, non-technical plain text without markdown code fences.';

        if ($action === 'management_summary') {
            $stats = (string) ($ctx['keywords'] ?? 'School submission metrics');
            $userPrompt = "Based on these Excellia School academic submission metrics ({$stats}), write a crisp 3-bullet executive briefing for the School Director & Principal highlighting: 1) Overall progress, 2) Key bottleneck/overdue area, and 3) Immediate recommended action. Keep under 100 words.";
            $fallback = "Executive Briefing:\n"
                . "• Overall Progress: {$stats}\n"
                . "• Priority Attention: Focus follow-ups on overdue tasks and pending coordinator reviews to clear bottlenecks before weekend escalation.\n"
                . "• Recommended Next Step: Trigger targeted WhatsApp/portal reminders from the Late Submissions screen and review wing-wise completion.";

            return [$schoolTone, $userPrompt, $fallback];
        }

        if ($action === 'task_instructions') {
            $docType = (string) ($ctx['document_type'] ?? 'Academic Document');
            $subject = (string) ($ctx['subject'] ?? 'All Subjects');
            $className = (string) ($ctx['class_name'] ?? 'Assigned Classes');
            $period = (string) ($ctx['period'] ?? 'Upcoming Period');

            $userPrompt = "Write brief, friendly submission instructions (3-4 bullet points, under 90 words) for teachers submitting '{$docType}' for {$className} ({$subject}) covering '{$period}'. Mention clarity, learning outcomes, and timely upload.";
            $fallback = "Please include in your {$docType} ({$period} — {$className}, {$subject}):\n"
                . "• Clear learning objectives and topic-wise period split.\n"
                . "• Classroom activities, board work / teaching aids, and recap questions.\n"
                . "• Differentiation or remedial support notes for learners who need extra practice.\n"
                . "• Please upload in PDF or Word format before the due date.";

            return [$schoolTone, $userPrompt, $fallback];
        }

        if ($action === 'review_feedback') {
            $decision = strtoupper((string) ($ctx['decision'] ?? 'RETURN'));
            $title = (string) ($ctx['task_title'] ?? 'Submission');
            $teacher = (string) ($ctx['teacher_name'] ?? 'Teacher');
            $notes = trim((string) ($ctx['keywords'] ?? ''));

            if ($decision === 'APPROVE') {
                $userPrompt = "Write a warm, appreciative 1-2 sentence approval remark from a Coordinator to teacher {$teacher} for '{$title}'." . ($notes !== '' ? " Mention: {$notes}." : '');
                $fallback = "Thank you, {$teacher}. The {$title} is well-structured, aligned with the syllabus schedule, and approved for classroom use." . ($notes !== '' ? " ({$notes})" : '');
            } else {
                $userPrompt = "Write a polite, constructive 2-3 sentence coordinator review note asking teacher {$teacher} to revise and re-upload '{$title}'." . ($notes !== '' ? " Specifically address: {$notes}." : ' Ask to add clear learning outcomes and assessment checkpoints.');
                $fallback = "Dear {$teacher}, thank you for submitting {$title}. Kindly update the file"
                    . ($notes !== '' ? " to address: {$notes}" : ' with clearer learning outcomes, period-wise pacing, and quick recap questions')
                    . ", and re-upload at your convenience.";
            }

            return [$schoolTone, $userPrompt, $fallback];
        }

        // Default: 'teacher_outline' — helps teachers prepare structure & submission note quickly
        $title = (string) ($ctx['task_title'] ?? 'Academic Submission');
        $docType = (string) ($ctx['document_type'] ?? 'Lesson Plan');
        $className = (string) ($ctx['class_name'] ?? 'Class');
        $subject = (string) ($ctx['subject'] ?? 'Subject');
        $period = (string) ($ctx['period'] ?? 'Current Month');

        $userPrompt = "Create a short, ready-to-use {$docType} checklist/outline and a 1-line teacher submission note for '{$title}' ({$className}, {$subject}, {$period}). Keep under 120 words.";
        $fallback = "Suggested {$docType} Checklist ({$className} · {$subject} · {$period}):\n"
            . "1. Chapter / Topic & Number of Periods\n"
            . "2. Key Learning Outcomes (NCERT / School Curriculum)\n"
            . "3. Warm-up Activity, Main Explanation & Practice Questions\n"
            . "4. Homework / Remedial Follow-up\n\n"
            . "Ready Note for Coordinator: Submitted {$docType} for {$className} {$subject} ({$period}) covering syllabus objectives and practice tasks.";

        return [$schoolTone, $userPrompt, $fallback];
    }
}
