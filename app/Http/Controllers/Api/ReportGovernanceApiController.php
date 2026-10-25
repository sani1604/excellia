<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DocumentTask;
use App\Models\EscalationRule;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\OverdueEscalationService;
use App\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportGovernanceApiController extends Controller
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly OverdueEscalationService $overdueService,
        private readonly AuditService $audit,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reporting->dashboardMetrics($request->user(), $request->all()),
        ]);
    }

    public function classWise(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reporting->classWiseReport($request->user(), $request->all()),
        ]);
    }

    public function teacherWise(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reporting->teacherWiseReport($request->user(), $request->all()),
        ]);
    }

    public function subjectWise(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reporting->subjectWiseReport($request->user(), $request->all()),
        ]);
    }

    public function overdue(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reporting->overdueEscalationReport($request->user(), $request->all()),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $logs = NotificationLog::query()
            ->where('recipient_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 30));

        return response()->json($logs);
    }

    public function markNotificationRead(Request $request, NotificationLog $notification): JsonResponse
    {
        if ((int) $notification->recipient_user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['data' => $notification->fresh()]);
    }

    public function sendReminder(Request $request, DocumentTask $task): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $logs = $this->overdueService->sendManualReminder($task, $request->user(), $validated['message'] ?? null);

        return response()->json([
            'message' => 'Reminder dispatched successfully.',
            'logs_count' => count($logs),
        ]);
    }

    public function notificationRules(): JsonResponse
    {
        return response()->json([
            'data' => NotificationRule::query()->orderBy('id')->get(),
        ]);
    }

    public function updateNotificationRule(Request $request, NotificationRule $notificationRule): JsonResponse
    {
        $validated = $request->validate([
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string', 'in:IN_APP,EMAIL,WHATSAPP'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $notificationRule->toArray();
        $notificationRule->update($validated);
        $this->audit->log('notification_rule.updated', 'notification_rule', $notificationRule->id, $before, $notificationRule->fresh()->toArray(), $request->user());

        return response()->json(['data' => $notificationRule->fresh()]);
    }

    public function escalationRules(): JsonResponse
    {
        return response()->json([
            'data' => EscalationRule::query()->orderBy('threshold_value')->orderBy('id')->get(),
        ]);
    }

    public function storeEscalationRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trigger_type' => ['required', 'string', 'in:DUE_SOON,ON_DUE_DATE,OVERDUE_DAYS,REVIEW_SLA_HOURS'],
            'threshold_value' => ['required', 'integer', 'min:-30', 'max:720'],
            'escalation_level' => ['required', 'integer', 'min:0', 'max:5'],
            'target_role_key' => ['required', 'string', 'exists:roles,key'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:IN_APP,EMAIL,WHATSAPP'],
            'skip_weekends' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = EscalationRule::query()->create(array_merge($validated, [
            'skip_weekends' => (bool) ($validated['skip_weekends'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]));

        $this->audit->log('escalation_rule.created', 'escalation_rule', $rule->id, null, $rule->toArray(), $request->user());

        return response()->json(['data' => $rule], 201);
    }

    public function updateEscalationRule(Request $request, EscalationRule $escalationRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'threshold_value' => ['sometimes', 'integer', 'min:-30', 'max:720'],
            'escalation_level' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'target_role_key' => ['sometimes', 'string', 'exists:roles,key'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string', 'in:IN_APP,EMAIL,WHATSAPP'],
            'skip_weekends' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $escalationRule->toArray();
        $escalationRule->update($validated);
        $this->audit->log('escalation_rule.updated', 'escalation_rule', $escalationRule->id, $before, $escalationRule->fresh()->toArray(), $request->user());

        return response()->json(['data' => $escalationRule->fresh()]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => SystemSetting::query()->orderBy('group')->orderBy('key')->get(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_timezone' => ['sometimes', 'string', 'timezone'],
            'review_sla_hours' => ['sometimes', 'integer', 'min:4', 'max:336'],
            'allow_co_class_teachers' => ['sometimes', 'boolean'],
            'exclude_holidays_from_overdue' => ['sometimes', 'boolean'],
            'email_notifications_enabled' => ['sometimes', 'boolean'],
            'whatsapp_notifications_enabled' => ['sometimes', 'boolean'],
            'holidays_dates' => ['sometimes', 'array'],
            'holidays_dates.*' => ['date_format:Y-m-d'],
            'submission_modes' => ['sometimes', 'array', 'min:1'],
            'submission_modes.*' => ['string', 'in:PORTAL_UPLOAD,DRIVE_LINK'],
        ]);

        $actor = $request->user();
        foreach ($validated as $key => $val) {
            $group = str_contains($key, 'notifications') ? 'notifications' : ($key === 'allow_co_class_teachers' ? 'academics' : 'workflow');
            SystemSetting::setValue($key, $val, $group, null, $actor->id);
        }

        $this->audit->log('settings.updated', 'system_setting', null, null, $validated, $actor);

        return response()->json([
            'data' => SystemSetting::query()->orderBy('group')->orderBy('key')->get(),
        ]);
    }

    public function auditEvents(Request $request): JsonResponse
    {
        $events = AuditEvent::query()
            ->with('actor')
            ->when($request->filled('action_key'), fn ($q) => $q->where('action_key', $request->string('action_key')))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('actor_user_id'), fn ($q) => $q->where('actor_user_id', $request->integer('actor_user_id')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json($events);
    }
}
