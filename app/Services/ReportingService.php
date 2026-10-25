<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Models\AcademicYear;
use App\Models\DocumentTask;
use App\Models\NotificationLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportingService
{
    public function __construct(
        private readonly ScopeAuthorizationService $scopeAuth,
    ) {}

    public function buildFilteredTaskQuery(User $viewer, array $filters = []): Builder
    {
        $query = DocumentTask::query()
            ->with([
                'academicYear',
                'assignedTeacher',
                'reviewer',
                'documentType',
                'teachingAllocation.schoolClass',
                'teachingAllocation.section',
                'teachingAllocation.subject',
                'submission.currentVersion.latestReview.reviewer',
            ]);

        $this->scopeAuth->applyTaskScope($query, $viewer);

        $yearId = $filters['academic_year_id'] ?? null;
        if ($yearId) {
            $query->where('document_tasks.academic_year_id', (int) $yearId);
        }

        if (! empty($filters['class_id'])) {
            $classId = (int) $filters['class_id'];
            $query->whereHas('teachingAllocation', fn (Builder $q) => $q->where('class_id', $classId));
        }

        if (! empty($filters['section_id'])) {
            $sectionId = (int) $filters['section_id'];
            $query->whereHas('teachingAllocation', fn (Builder $q) => $q->where('section_id', $sectionId));
        }

        if (! empty($filters['subject_id'])) {
            $subjectId = (int) $filters['subject_id'];
            $query->whereHas('teachingAllocation', fn (Builder $q) => $q->where('subject_id', $subjectId));
        }

        if (! empty($filters['teacher_user_id'])) {
            $query->where('document_tasks.assigned_teacher_id', (int) $filters['teacher_user_id']);
        }

        if (! empty($filters['document_type_id'])) {
            $query->where('document_tasks.document_type_id', (int) $filters['document_type_id']);
        }

        if (! empty($filters['period_label'])) {
            $period = trim((string) $filters['period_label']);
            $query->where('document_tasks.period_label', 'like', "%{$period}%");
        }

        if (! empty($filters['status'])) {
            $status = (string) $filters['status'];
            match ($status) {
                'OVERDUE' => $query->where('document_tasks.is_overdue', true)
                    ->whereNotIn('document_tasks.workflow_status', ['APPROVED', 'CANCELLED']),
                'OVERDUE_NOT_SUBMITTED' => $query->where('document_tasks.is_overdue', true)
                    ->where('document_tasks.workflow_status', 'NOT_STARTED'),
                'OVERDUE_PENDING_REVIEW' => $query->where('document_tasks.is_review_overdue', true)
                    ->whereIn('document_tasks.workflow_status', ['SUBMITTED_PENDING_REVIEW', 'RESUBMITTED_PENDING_REVIEW']),
                'ESCALATED' => $query->where('document_tasks.escalation_level', '>', 0)
                    ->whereNotIn('document_tasks.workflow_status', ['APPROVED', 'CANCELLED']),
                'PENDING_REVIEW' => $query->whereIn('document_tasks.workflow_status', ['SUBMITTED_PENDING_REVIEW', 'RESUBMITTED_PENDING_REVIEW']),
                default => $query->where('document_tasks.workflow_status', $status),
            };
        }

        return $query;
    }

    /**
     * Computes reconciled summary KPIs from a task collection (guaranteed to reconcile 1:1 with task records).
     */
    public function summarizeTasks(Collection $tasks): array
    {
        $tz = SystemSetting::timezone();
        $now = now($tz);
        $dueSoonWindow = (int) config('excellia.workflow.due_soon_days', 3);

        $cancelled = $tasks->where('workflow_status', WorkflowStatus::CANCELLED->value)->count();
        $exempt = $tasks->where('workflow_status', '!=', WorkflowStatus::CANCELLED->value)->where('is_exempt', true)->count();

        // Denominator per Section 7: active required tasks, excluding cancelled & exempt
        $expectedCollection = $tasks->filter(fn (DocumentTask $t) => $t->is_required
            && ! $t->is_exempt
            && $t->workflow_status !== WorkflowStatus::CANCELLED->value
        );

        $expected = $expectedCollection->count();
        $approved = $expectedCollection->where('workflow_status', WorkflowStatus::APPROVED->value)->count();
        $pendingSubmission = $expectedCollection->where('workflow_status', WorkflowStatus::NOT_STARTED->value)->count();
        $pendingReview = $expectedCollection->whereIn('workflow_status', [
            WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
            WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
        ])->count();
        $returned = $expectedCollection->where('workflow_status', WorkflowStatus::RETURNED_FOR_CORRECTION->value)->count();
        $submitted = $expected - $pendingSubmission;

        $overdue = $expectedCollection->filter(function (DocumentTask $t) use ($now) {
            if ($t->workflow_status === WorkflowStatus::APPROVED->value) {
                return false;
            }

            return $t->is_overdue || ($t->due_at && $t->due_at->lessThan($now));
        })->count();

        $dueSoon = $expectedCollection->filter(function (DocumentTask $t) use ($now, $dueSoonWindow) {
            if (in_array($t->workflow_status, [
                WorkflowStatus::APPROVED->value,
                WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
                WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
            ], true)) {
                return false;
            }
            if (! $t->due_at || $t->due_at->lessThan($now)) {
                return false;
            }

            return $now->diffInDays($t->due_at, false) <= $dueSoonWindow;
        })->count();

        $escalated = $expectedCollection->filter(fn (DocumentTask $t) => $t->workflow_status !== WorkflowStatus::APPROVED->value
            && $t->escalation_level > 0
        )->count();

        $reviewSlaOverdue = $expectedCollection->filter(fn (DocumentTask $t) => $t->is_review_overdue
            && in_array($t->workflow_status, [
                WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
                WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
            ], true)
        )->count();

        $completionRate = $expected > 0 ? round(($approved / $expected) * 100, 1) : 0.0;

        return [
            'expected' => $expected,
            'submitted' => $submitted,
            'approved' => $approved,
            'pending_submission' => $pendingSubmission,
            'pending_review' => $pendingReview,
            'returned' => $returned,
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
            'escalated' => $escalated,
            'review_sla_overdue' => $reviewSlaOverdue,
            'cancelled' => $cancelled,
            'exempt' => $exempt,
            'completion_rate' => $completionRate,
        ];
    }

    public function dashboardMetrics(User $viewer, array $filters = []): array
    {
        if (empty($filters['academic_year_id'])) {
            $currentYear = AcademicYear::current();
            if ($currentYear) {
                $filters['academic_year_id'] = $currentYear->id;
            }
        }

        $tasks = $this->buildFilteredTaskQuery($viewer, $filters)
            ->orderBy('due_at')
            ->get();

        return [
            'summary' => $this->summarizeTasks($tasks),
            'by_document_type' => $tasks
                ->filter(fn (DocumentTask $t) => $t->is_required && ! $t->is_exempt && $t->workflow_status !== 'CANCELLED')
                ->groupBy(fn (DocumentTask $t) => $t->documentType?->name ?? 'Other')
                ->map(fn (Collection $group, string $docName) => array_merge(
                    ['document_type' => $docName],
                    $this->summarizeTasks($group)
                ))
                ->values()
                ->all(),
            'filters' => $filters,
        ];
    }

    public function classWiseReport(User $viewer, array $filters = []): array
    {
        $tasks = $this->buildFilteredTaskQuery($viewer, $filters)->get();

        $rows = $tasks
            ->filter(fn (DocumentTask $t) => $t->workflow_status !== WorkflowStatus::CANCELLED->value)
            ->groupBy(function (DocumentTask $t) {
                $alloc = $t->teachingAllocation;

                return implode('|', [
                    $alloc?->class_id ?? 0,
                    $alloc?->section_id ?? 0,
                    $alloc?->subject_id ?? 0,
                    $t->assigned_teacher_id,
                ]);
            })
            ->map(function (Collection $group) {
                /** @var DocumentTask $first */
                $first = $group->first();
                $alloc = $first->teachingAllocation;
                $metrics = $this->summarizeTasks($group);

                return array_merge([
                    'academic_year' => $first->academicYear?->name ?? '',
                    'class_id' => $alloc?->class_id,
                    'class_name' => $alloc?->schoolClass?->name ?? 'N/A',
                    'class_order' => $alloc?->schoolClass?->display_order ?? 999,
                    'section_name' => $alloc?->section?->name ?? 'N/A',
                    'subject_name' => $alloc?->subject?->name ?? 'Class Teacher',
                    'teacher_name' => $first->assignedTeacher?->full_name ?? 'N/A',
                ], $metrics);
            })
            ->sortBy([
                ['class_order', 'asc'],
                ['section_name', 'asc'],
                ['subject_name', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'summary' => $this->summarizeTasks($tasks),
            'rows' => $rows,
            'task_records' => $this->formatTaskRecords($tasks),
        ];
    }

    public function teacherWiseReport(User $viewer, array $filters = []): array
    {
        $tasks = $this->buildFilteredTaskQuery($viewer, $filters)->get();

        $rows = $tasks
            ->filter(fn (DocumentTask $t) => $t->workflow_status !== WorkflowStatus::CANCELLED->value)
            ->groupBy('assigned_teacher_id')
            ->map(function (Collection $group) {
                /** @var DocumentTask $first */
                $first = $group->first();
                $metrics = $this->summarizeTasks($group);

                return array_merge([
                    'teacher_id' => $first->assigned_teacher_id,
                    'employee_code' => $first->assignedTeacher?->employee_code ?? '-',
                    'teacher_name' => $first->assignedTeacher?->full_name ?? 'N/A',
                    'teacher_email' => $first->assignedTeacher?->email ?? '',
                    'compliance_rate' => $metrics['completion_rate'],
                ], $metrics);
            })
            ->sortBy('teacher_name')
            ->values()
            ->all();

        return [
            'summary' => $this->summarizeTasks($tasks),
            'rows' => $rows,
            'task_records' => $this->formatTaskRecords($tasks),
        ];
    }

    public function subjectWiseReport(User $viewer, array $filters = []): array
    {
        $tasks = $this->buildFilteredTaskQuery($viewer, $filters)->get();

        $rows = $tasks
            ->filter(fn (DocumentTask $t) => $t->workflow_status !== WorkflowStatus::CANCELLED->value)
            ->groupBy(function (DocumentTask $t) {
                $alloc = $t->teachingAllocation;

                return ($alloc?->subject_id ?? 0) . '|' . ($alloc?->class_id ?? 0);
            })
            ->map(function (Collection $group) {
                /** @var DocumentTask $first */
                $first = $group->first();
                $alloc = $first->teachingAllocation;
                $metrics = $this->summarizeTasks($group);

                return array_merge([
                    'subject_id' => $alloc?->subject_id,
                    'subject_name' => $alloc?->subject?->name ?? 'Class Teacher',
                    'class_name' => $alloc?->schoolClass?->name ?? 'N/A',
                    'class_order' => $alloc?->schoolClass?->display_order ?? 999,
                ], $metrics);
            })
            ->sortBy([
                ['subject_name', 'asc'],
                ['class_order', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'summary' => $this->summarizeTasks($tasks),
            'rows' => $rows,
            'task_records' => $this->formatTaskRecords($tasks),
        ];
    }

    public function overdueEscalationReport(User $viewer, array $filters = []): array
    {
        $tz = SystemSetting::timezone();
        $now = now($tz);

        $allScopedTasks = $this->buildFilteredTaskQuery($viewer, $filters)->get();
        $overdueTasks = $allScopedTasks->filter(function (DocumentTask $t) use ($now) {
            if (in_array($t->workflow_status, [WorkflowStatus::APPROVED->value, WorkflowStatus::CANCELLED->value], true) || $t->is_exempt) {
                return false;
            }

            return $t->is_overdue || $t->is_review_overdue || ($t->due_at && $t->due_at->lessThan($now));
        })->sortByDesc(fn (DocumentTask $t) => $t->overdueDays($now))->values();

        return [
            'summary' => $this->summarizeTasks($allScopedTasks),
            'rows' => $this->formatTaskRecords($overdueTasks),
        ];
    }

    /**
     * Produces the exact 15 minimum report columns required by Section 7.
     */
    public function formatTaskRecords(Collection $tasks): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $tz = SystemSetting::timezone();
        $now = now($tz);
        $taskIds = $tasks->pluck('id')->all();

        $lastNotifications = NotificationLog::query()
            ->where('entity_type', 'document_task')
            ->whereIn('entity_id', $taskIds)
            ->orderByDesc('sent_at')
            ->get()
            ->groupBy('entity_id')
            ->map(fn (Collection $logs) => $logs->first());

        return $tasks->map(function (DocumentTask $t) use ($tz, $now, $lastNotifications) {
            $alloc = $t->teachingAllocation;
            $submission = $t->submission;
            $version = $submission?->currentVersion;
            $latestReview = $version?->latestReview;
            /** @var NotificationLog|null $lastNotif */
            $lastNotif = $lastNotifications->get($t->id);

            $reviewerName = $latestReview?->reviewer?->full_name
                ?? $t->reviewer?->full_name
                ?? 'Scoped Coordinator';

            $currentOwner = in_array($t->workflow_status, ['SUBMITTED_PENDING_REVIEW', 'RESUBMITTED_PENDING_REVIEW'], true)
                ? "Reviewer ({$reviewerName})"
                : ($t->workflow_status === 'APPROVED' ? 'Completed' : "Teacher ({$t->assignedTeacher?->full_name})");

            return [
                'task_id' => $t->id,
                'academic_year' => $t->academicYear?->name ?? '',
                'class_name' => $alloc?->schoolClass?->name ?? '',
                'section_name' => $alloc?->section?->name ?? '',
                'subject_name' => $alloc?->subject?->name ?? 'Class Teacher',
                'teacher_name' => $t->assignedTeacher?->full_name ?? '',
                'document_type' => $t->documentType?->name ?? '',
                'period' => $t->period_label,
                'title' => $t->title,
                'due_date' => $t->due_at?->timezone($tz)->format('Y-m-d H:i'),
                'submission_timestamp' => $submission?->last_submitted_at?->timezone($tz)->format('Y-m-d H:i'),
                'workflow_status' => $t->workflow_status,
                'workflow_status_label' => $t->workflowLabel(),
                'effective_status' => $t->effectiveStatusKey(),
                'effective_status_label' => $t->effectiveStatusLabel(),
                'is_overdue' => (bool) ($t->is_overdue && ! in_array($t->workflow_status, ['APPROVED', 'CANCELLED'], true)),
                'overdue_days' => $t->overdueDays($now),
                'is_review_overdue' => (bool) $t->is_review_overdue,
                'reviewer' => $reviewerName,
                'last_review_timestamp' => $latestReview?->reviewed_at?->timezone($tz)->format('Y-m-d H:i'),
                'latest_remarks' => $latestReview?->remarks ?? '',
                'escalation_level' => (int) $t->escalation_level,
                'last_notification' => $lastNotif
                    ? ($lastNotif->event_key . ' @ ' . ($lastNotif->sent_at?->timezone($tz)->format('Y-m-d H:i') ?? ''))
                    : 'None',
                'current_owner' => $currentOwner,
            ];
        })->values()->all();
    }
}
