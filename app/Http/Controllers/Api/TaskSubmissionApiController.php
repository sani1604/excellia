<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentSubmission;
use App\Models\DocumentTask;
use App\Models\SubmissionVersion;
use App\Services\AuditService;
use App\Services\ReportingService;
use App\Services\ScopeAuthorizationService;
use App\Services\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskSubmissionApiController extends Controller
{
    public function __construct(
        private readonly TaskWorkflowService $workflow,
        private readonly ScopeAuthorizationService $scopeAuth,
        private readonly ReportingService $reporting,
        private readonly AuditService $audit,
    ) {}

    public function myTasks(Request $request): JsonResponse
    {
        $tasks = DocumentTask::query()
            ->with([
                'academicYear',
                'documentType',
                'teachingAllocation.schoolClass',
                'teachingAllocation.section',
                'teachingAllocation.subject',
                'submission.currentVersion.latestReview',
            ])
            ->where('assigned_teacher_id', $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('workflow_status', $request->string('status')))
            ->orderBy('due_at')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json($tasks);
    }

    public function indexTasks(Request $request): JsonResponse
    {
        $query = $this->reporting->buildFilteredTaskQuery($request->user(), $request->all())
            ->orderBy('due_at');

        return response()->json($query->paginate((int) $request->integer('per_page', 25)));
    }

    public function showTask(Request $request, DocumentTask $task): JsonResponse
    {
        $task->load([
            'academicYear',
            'assignedTeacher',
            'reviewer',
            'documentType',
            'teachingAllocation.schoolClass',
            'teachingAllocation.section',
            'teachingAllocation.subject',
            'submission.versions.reviews.reviewer',
            'submission.versions.uploader',
        ]);

        if (! $this->scopeAuth->canViewTask($request->user(), $task)) {
            return response()->json(['message' => 'Forbidden: task is outside your authorised scope.'], 403);
        }

        return response()->json(['data' => $task]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'teaching_allocation_id' => ['required', 'integer', 'exists:teaching_allocations,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string'],
            'period_label' => ['required', 'string', 'max:100'],
            'due_at' => ['required', 'date'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $task = $this->workflow->createTask($validated, $request->user());

        return response()->json(['data' => $task], 201);
    }

    public function updateTask(Request $request, DocumentTask $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'instructions' => ['nullable', 'string'],
            'period_label' => ['sometimes', 'string', 'max:100'],
            'due_at' => ['sometimes', 'date'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $updated = $this->workflow->updateTask($task, $validated, $request->user());

        return response()->json(['data' => $updated]);
    }

    public function cancelTask(Request $request, DocumentTask $task): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cancelled = $this->workflow->cancelTask($task, $validated['reason'], $request->user());

        return response()->json(['data' => $cancelled]);
    }

    public function reopenTask(Request $request, DocumentTask $task): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $reopened = $this->workflow->reopenApprovedTask($task, $validated['reason'], $request->user());

        return response()->json(['data' => $reopened]);
    }

    public function generateTasks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'period_label' => ['required', 'string', 'max:100'],
            'due_at' => ['required', 'date'],
            'title_prefix' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $result = $this->workflow->generateRecurringTasks($validated, $request->user());

        return response()->json([
            'message' => 'Task generation completed idempotently.',
            'created_count' => $result['created_count'],
            'skipped_count' => $result['skipped_count'],
            'data' => $result['created'],
        ]);
    }

    public function submitTask(Request $request, DocumentTask $task): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['nullable', 'file'],
            'drive_file_id_or_url' => ['nullable', 'string', 'max:500'],
            'drive_file_name' => ['nullable', 'string', 'max:255'],
            'file_size_bytes' => ['nullable', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:150'],
            'teacher_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $drivePayload = ! empty($validated['drive_file_id_or_url']) ? $validated : null;

        $version = $this->workflow->submitTaskDocument(
            $task,
            $request->user(),
            $request->file('file'),
            $drivePayload,
            $validated['teacher_note'] ?? null
        );

        return response()->json(['data' => $version], 201);
    }

    public function addVersion(Request $request, DocumentSubmission $submission): JsonResponse
    {
        $submission->loadMissing('task');

        return $this->submitTask($request, $submission->task);
    }

    public function mySubmissions(Request $request): JsonResponse
    {
        $submissions = DocumentSubmission::query()
            ->with([
                'task.documentType',
                'task.teachingAllocation.schoolClass',
                'task.teachingAllocation.section',
                'task.teachingAllocation.subject',
                'currentVersion.latestReview.reviewer',
            ])
            ->whereHas('task', fn ($q) => $q->where('assigned_teacher_id', $request->user()->id))
            ->orderByDesc('last_submitted_at')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json($submissions);
    }

    public function showSubmission(Request $request, DocumentSubmission $submission): JsonResponse
    {
        $submission->load([
            'task.documentType',
            'task.assignedTeacher',
            'task.teachingAllocation.schoolClass',
            'task.teachingAllocation.section',
            'task.teachingAllocation.subject',
            'currentVersion.latestReview.reviewer',
        ]);

        if (! $this->scopeAuth->canViewTask($request->user(), $submission->task)) {
            return response()->json(['message' => 'Forbidden: submission is outside your authorised scope.'], 403);
        }

        // Redact raw Drive URL unless caller is authorised to access the file itself
        $canOpenFile = $submission->currentVersion
            ? $this->scopeAuth->canAccessSubmissionFile($request->user(), $submission->currentVersion)
            : false;

        $payload = $submission->toArray();
        if (! $canOpenFile && isset($payload['current_version'])) {
            $payload['current_version']['drive_file_url'] = null;
        }
        $payload['can_access_file'] = $canOpenFile;

        return response()->json(['data' => $payload]);
    }

    public function submissionHistory(Request $request, DocumentSubmission $submission): JsonResponse
    {
        $submission->load([
            'task.documentType',
            'task.teachingAllocation.schoolClass',
            'versions.uploader',
            'versions.reviews.reviewer',
        ]);

        if (! $this->scopeAuth->canViewTask($request->user(), $submission->task)) {
            return response()->json(['message' => 'Forbidden: submission history is outside your authorised scope.'], 403);
        }

        $versions = $submission->versions->map(function (SubmissionVersion $v) use ($request) {
            $canOpen = $this->scopeAuth->canAccessSubmissionFile($request->user(), $v);
            $arr = $v->toArray();
            if (! $canOpen) {
                $arr['drive_file_url'] = null;
            }
            $arr['can_access_file'] = $canOpen;

            return $arr;
        });

        return response()->json([
            'submission_id' => $submission->id,
            'task_id' => $submission->document_task_id,
            'versions' => $versions,
        ]);
    }

    public function approveSubmission(Request $request, DocumentSubmission $submission): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $this->workflow->approveSubmission($submission, $request->user(), $validated['remarks'] ?? null);

        return response()->json(['data' => $review]);
    }

    public function returnSubmission(Request $request, DocumentSubmission $submission): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $review = $this->workflow->returnSubmission($submission, $request->user(), $validated['remarks']);

        return response()->json(['data' => $review]);
    }

    public function fileAccessLink(Request $request, SubmissionVersion $version): JsonResponse
    {
        $version->loadMissing(['submission.task.documentType', 'submission.task.teachingAllocation.schoolClass']);
        $user = $request->user();
        $docType = $version->submission?->task?->documentType;
        $isRestricted = (bool) ($docType?->is_restricted || in_array($docType?->sensitivity_level, ['RESTRICTED', 'STRICTLY_RESTRICTED'], true));

        if (! $this->scopeAuth->canAccessSubmissionFile($user, $version)) {
            if ($isRestricted) {
                $this->audit->log(
                    'file.restricted_access_denied',
                    'submission_version',
                    $version->id,
                    null,
                    ['document_type' => $docType?->code, 'sensitivity' => $docType?->sensitivity_level],
                    $user,
                    $request
                );
            }

            return response()->json([
                'message' => 'Forbidden: you are not authorised to access this academic document file.',
            ], 403);
        }

        if ($isRestricted) {
            $this->audit->log(
                'file.restricted_accessed',
                'submission_version',
                $version->id,
                null,
                [
                    'document_type' => $docType?->code,
                    'sensitivity' => $docType?->sensitivity_level,
                    'drive_file_id' => $version->drive_file_id,
                ],
                $user,
                $request
            );
        }

        return response()->json([
            'data' => [
                'version_id' => $version->id,
                'drive_file_id' => $version->drive_file_id,
                'drive_file_name' => $version->drive_file_name,
                'drive_file_url' => $version->drive_file_url,
                'portal_download_url' => route('submissions.versions.file', $version),
                'sensitivity_level' => $docType?->sensitivity_level,
            ],
        ]);
    }
}
