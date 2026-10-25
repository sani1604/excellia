<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentTask extends Model
{
    protected $fillable = [
        'academic_year_id',
        'teaching_allocation_id',
        'assigned_teacher_id',
        'document_type_id',
        'title',
        'instructions',
        'period_label',
        'due_at',
        'review_due_at',
        'reviewer_user_id',
        'reviewer_role_id',
        'workflow_status',
        'is_overdue',
        'overdue_since',
        'is_review_overdue',
        'review_overdue_since',
        'escalation_level',
        'escalated_at',
        'last_reminder_at',
        'is_required',
        'is_exempt',
        'exemption_reason',
        'schedule_idempotency_key',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'review_due_at' => 'datetime',
            'overdue_since' => 'datetime',
            'review_overdue_since' => 'datetime',
            'escalated_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'reopened_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_overdue' => 'boolean',
            'is_review_overdue' => 'boolean',
            'is_required' => 'boolean',
            'is_exempt' => 'boolean',
            'escalation_level' => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function teachingAllocation(): BelongsTo
    {
        return $this->belongsTo(TeachingAllocation::class, 'teaching_allocation_id');
    }

    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teacher_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function reviewerRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'reviewer_role_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(DocumentSubmission::class, 'document_task_id');
    }

    public function workflowEnum(): WorkflowStatus
    {
        return WorkflowStatus::tryFrom($this->workflow_status) ?? WorkflowStatus::NOT_STARTED;
    }

    public function workflowLabel(): string
    {
        return $this->workflowEnum()->label();
    }

    public function effectiveStatusKey(): string
    {
        if (in_array($this->workflow_status, ['APPROVED', 'CANCELLED'], true)) {
            return $this->workflow_status;
        }

        if ($this->escalation_level > 0) {
            return 'ESCALATED';
        }

        if ($this->is_review_overdue && in_array($this->workflow_status, ['SUBMITTED_PENDING_REVIEW', 'RESUBMITTED_PENDING_REVIEW'], true)) {
            return 'OVERDUE_PENDING_REVIEW';
        }

        if ($this->is_overdue && $this->workflow_status === 'NOT_STARTED') {
            return 'OVERDUE_NOT_SUBMITTED';
        }

        return $this->workflow_status;
    }

    public function effectiveStatusLabel(): string
    {
        $labels = WorkflowStatus::displayLabels();
        $key = $this->effectiveStatusKey();

        return $labels[$key] ?? $this->workflowLabel();
    }

    public function overdueDays(?CarbonInterface $reference = null): int
    {
        if (! $this->is_overdue || in_array($this->workflow_status, ['APPROVED', 'CANCELLED'], true) || ! $this->due_at) {
            return 0;
        }

        $now = $reference ?? now(SystemSetting::timezone());
        if ($now->lessThanOrEqualTo($this->due_at)) {
            return 0;
        }

        return max(1, (int) floor($this->due_at->diffInHours($now) / 24));
    }

    public function canTeacherSubmit(): bool
    {
        return in_array($this->workflow_status, [
            WorkflowStatus::NOT_STARTED->value,
            WorkflowStatus::RETURNED_FOR_CORRECTION->value,
        ], true);
    }

    public function canReviewerDecide(): bool
    {
        return in_array($this->workflow_status, [
            WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
            WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
        ], true);
    }
}
