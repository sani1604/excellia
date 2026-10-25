@php
    /** @var string $workflowStatus */
    $workflowStatus = $workflowStatus ?? ($task->workflow_status ?? 'NOT_STARTED');
    $isOverdue = (bool) ($isOverdue ?? ($task->is_overdue ?? false));
    $isReviewOverdue = (bool) ($isReviewOverdue ?? ($task->is_review_overdue ?? false));
    $escalationLevel = (int) ($escalationLevel ?? ($task->escalation_level ?? 0));
    $overdueDays = isset($task) && method_exists($task, 'overdueDays') ? $task->overdueDays() : ($overdueDays ?? 0);

    $wfColors = [
        'NOT_STARTED' => 'bg-slate-100 text-slate-700 border-slate-200',
        'SUBMITTED_PENDING_REVIEW' => 'bg-sky-50 text-sky-700 border-sky-200',
        'RESUBMITTED_PENDING_REVIEW' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'RETURNED_FOR_CORRECTION' => 'bg-amber-100 text-amber-900 border-amber-300',
        'APPROVED' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'CANCELLED' => 'bg-slate-100 text-slate-500 border-slate-200 line-through',
    ];
    $friendlyLabels = [
        'NOT_STARTED' => 'Pending',
        'SUBMITTED_PENDING_REVIEW' => 'Sent for Check',
        'RESUBMITTED_PENDING_REVIEW' => 'Resubmitted',
        'RETURNED_FOR_CORRECTION' => 'Needs Correction',
        'APPROVED' => 'Approved',
        'CANCELLED' => 'Cancelled',
    ];
@endphp

<div class="inline-flex flex-wrap items-center gap-1.5">
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $wfColors[$workflowStatus] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
        {{ $friendlyLabels[$workflowStatus] ?? $workflowStatus }}
    </span>

    @if(!in_array($workflowStatus, ['APPROVED', 'CANCELLED'], true))
        @if($isOverdue)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                Late{{ $overdueDays > 0 ? " ({$overdueDays}d)" : '' }}
            </span>
        @endif

        @if($isReviewOverdue)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                Check Pending
            </span>
        @endif

        @if($escalationLevel > 0)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-600 text-white">
                Escalated
            </span>
        @endif
    @endif
</div>
