<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case SUBMITTED_PENDING_REVIEW = 'SUBMITTED_PENDING_REVIEW';
    case RETURNED_FOR_CORRECTION = 'RETURNED_FOR_CORRECTION';
    case RESUBMITTED_PENDING_REVIEW = 'RESUBMITTED_PENDING_REVIEW';
    case APPROVED = 'APPROVED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Not Started',
            self::SUBMITTED_PENDING_REVIEW => 'Submitted — Review Pending',
            self::RETURNED_FOR_CORRECTION => 'Returned for Correction',
            self::RESUBMITTED_PENDING_REVIEW => 'Resubmitted — Review Pending',
            self::APPROVED => 'Approved',
            self::CANCELLED => 'Cancelled',
        };
    }

    public static function displayLabels(): array
    {
        return [
            'NOT_STARTED' => 'Not Started',
            'SUBMITTED_PENDING_REVIEW' => 'Submitted — Review Pending',
            'RETURNED_FOR_CORRECTION' => 'Returned for Correction',
            'RESUBMITTED_PENDING_REVIEW' => 'Resubmitted — Review Pending',
            'APPROVED' => 'Approved',
            'OVERDUE_NOT_SUBMITTED' => 'Overdue — Not Submitted',
            'OVERDUE_PENDING_REVIEW' => 'Overdue — Review Pending',
            'ESCALATED' => 'Escalated',
            'CANCELLED' => 'Cancelled',
        ];
    }

    public function isPendingReview(): bool
    {
        return in_array($this, [self::SUBMITTED_PENDING_REVIEW, self::RESUBMITTED_PENDING_REVIEW], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::CANCELLED], true);
    }
}
