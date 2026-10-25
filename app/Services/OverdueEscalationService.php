<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Exceptions\DomainRuleException;
use App\Models\DocumentTask;
use App\Models\EscalationRule;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OverdueEscalationService
{
    public function __construct(
        private readonly ScopeAuthorizationService $scopeAuth,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {}

    /**
     * Evaluates deadlines, overdue flags, reviewer SLAs, reminders, and escalations idempotently.
     */
    public function processAll(?CarbonInterface $referenceTime = null): array
    {
        $tz = SystemSetting::timezone();
        $now = $referenceTime ? Carbon::instance($referenceTime)->timezone($tz) : now($tz);
        $rules = EscalationRule::query()->where('is_active', true)->orderBy('threshold_value')->get();

        $excludeHolidays = (bool) SystemSetting::getValue('exclude_holidays_from_overdue', false);
        $holidayDates = (array) SystemSetting::getValue('holidays_dates', []);

        // Ensure approved/cancelled tasks are never marked overdue
        DocumentTask::query()
            ->whereIn('workflow_status', [WorkflowStatus::APPROVED->value, WorkflowStatus::CANCELLED->value])
            ->where(function ($q) {
                $q->where('is_overdue', true)->orWhere('is_review_overdue', true);
            })
            ->update([
                'is_overdue' => false,
                'is_review_overdue' => false,
            ]);

        $activeTasks = DocumentTask::query()
            ->with([
                'assignedTeacher',
                'reviewer',
                'documentType',
                'teachingAllocation.schoolClass',
                'teachingAllocation.section',
                'teachingAllocation.subject',
            ])
            ->whereNotIn('workflow_status', [WorkflowStatus::APPROVED->value, WorkflowStatus::CANCELLED->value])
            ->where('is_exempt', false)
            ->get();

        $markedOverdue = 0;
        $escalationsTriggered = 0;
        $notificationsSent = 0;
        $reviewSlaBreaches = 0;

        foreach ($activeTasks as $task) {
            if (! $task->due_at) {
                continue;
            }

            $dueAt = $task->due_at->copy()->timezone($tz);
            $isPastDue = $now->greaterThan($dueAt);

            if ($isPastDue && ! $task->is_overdue) {
                $task->is_overdue = true;
                $task->overdue_since = $dueAt;
                $task->save();
                $markedOverdue++;
            } elseif (! $isPastDue && $task->is_overdue) {
                $task->is_overdue = false;
                $task->overdue_since = null;
                $task->save();
            }

            // Separate Reviewer SLA evaluation (Section 5.5)
            $isAwaitingReview = in_array($task->workflow_status, [
                WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
                WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
            ], true);

            if ($isAwaitingReview && $task->review_due_at && $now->greaterThan($task->review_due_at)) {
                if (! $task->is_review_overdue) {
                    $task->is_review_overdue = true;
                    $task->review_overdue_since = $task->review_due_at;
                    $task->save();
                    $reviewSlaBreaches++;
                }

                foreach ($rules->where('trigger_type', 'REVIEW_SLA_HOURS') as $slaRule) {
                    $recipients = $this->resolveRuleRecipients($task, $slaRule->target_role_key);
                    foreach ($recipients as $recipient) {
                        $logs = $this->notifications->dispatch(
                            'REVIEW_SLA_BREACH',
                            'document_task',
                            $task->id,
                            $recipient,
                            "Review SLA Overdue: {$task->title}",
                            "Submission for {$task->title} ({$task->assignedTeacher?->full_name}) has exceeded the {$slaRule->threshold_value}h review SLA.",
                            $slaRule->channels ?: ['IN_APP'],
                            "rule_{$slaRule->id}_task_{$task->id}_rev_{$task->review_due_at->timestamp}"
                        );
                        $notificationsSent += count($logs);
                    }
                }

                // Do not send teacher non-submission escalations when awaiting reviewer!
                continue;
            }

            // Upcoming Due Soon reminders (before due_at)
            if (! $isPastDue && $task->assignedTeacher) {
                $daysUntilDue = (int) ceil($now->diffInHours($dueAt, false) / 24);
                foreach ($rules->where('trigger_type', 'DUE_SOON') as $soonRule) {
                    $windowDays = abs((int) $soonRule->threshold_value);
                    if ($daysUntilDue >= 1 && $daysUntilDue <= $windowDays) {
                        $logs = $this->notifications->dispatch(
                            'DUE_SOON',
                            'document_task',
                            $task->id,
                            $task->assignedTeacher,
                            "Upcoming Deadline: {$task->title}",
                            "Reminder: {$task->title} ({$task->period_label}) is due on {$dueAt->format('d M Y, h:i A')}.",
                            $soonRule->channels ?: ['IN_APP'],
                            "rule_{$soonRule->id}_task_{$task->id}_due_{$dueAt->toDateString()}"
                        );
                        $notificationsSent += count($logs);
                    }
                }
            }

            // Due date reached (same calendar date in school timezone)
            if ($now->toDateString() === $dueAt->toDateString() && $task->assignedTeacher) {
                foreach ($rules->where('trigger_type', 'ON_DUE_DATE') as $dueRule) {
                    $logs = $this->notifications->dispatch(
                        'DUE_DATE_REACHED',
                        'document_task',
                        $task->id,
                        $task->assignedTeacher,
                        "Due Today: {$task->title}",
                        "{$task->title} ({$task->period_label}) is due today at {$dueAt->format('h:i A')}.",
                        $dueRule->channels ?: ['IN_APP'],
                        "rule_{$dueRule->id}_task_{$task->id}_dueday_{$dueAt->toDateString()}"
                    );
                    $notificationsSent += count($logs);
                }
            }

            // Overdue teacher submission escalations
            if ($isPastDue) {
                foreach ($rules->where('trigger_type', 'OVERDUE_DAYS') as $overdueRule) {
                    $effectiveOverdueDays = $this->calculateOverdueDays(
                        $dueAt,
                        $now,
                        (bool) $overdueRule->skip_weekends,
                        $excludeHolidays ? $holidayDates : []
                    );

                    if ($effectiveOverdueDays < (int) $overdueRule->threshold_value) {
                        continue;
                    }

                    if ((int) $overdueRule->escalation_level > (int) $task->escalation_level) {
                        $beforeLevel = $task->escalation_level;
                        $task->escalation_level = (int) $overdueRule->escalation_level;
                        $task->escalated_at = $now;
                        $task->last_reminder_at = $now;
                        $task->save();
                        $escalationsTriggered++;

                        $this->audit->log(
                            'escalation.triggered',
                            'document_task',
                            $task->id,
                            ['escalation_level' => $beforeLevel],
                            [
                                'escalation_level' => $task->escalation_level,
                                'overdue_days' => $effectiveOverdueDays,
                                'rule_name' => $overdueRule->name,
                            ]
                        );
                    }

                    $recipients = $this->resolveRuleRecipients($task, $overdueRule->target_role_key);
                    $eventKey = $overdueRule->escalation_level > 0 ? 'ESCALATION_THRESHOLD_REACHED' : 'OVERDUE_REMINDER';

                    foreach ($recipients as $recipient) {
                        $logs = $this->notifications->dispatch(
                            $eventKey,
                            'document_task',
                            $task->id,
                            $recipient,
                            ($overdueRule->escalation_level > 0 ? "[Escalation L{$overdueRule->escalation_level}] " : 'Overdue: ') . $task->title,
                            sprintf(
                                '%s assigned to %s (%s %s - %s) is overdue by %d day(s). Due was %s.',
                                $task->title,
                                $task->assignedTeacher?->full_name ?? 'Teacher',
                                $task->teachingAllocation?->schoolClass?->name ?? '',
                                $task->teachingAllocation?->section?->name ?? '',
                                $task->teachingAllocation?->subject?->name ?? '',
                                $effectiveOverdueDays,
                                $dueAt->format('d M Y')
                            ),
                            $overdueRule->channels ?: ['IN_APP'],
                            "rule_{$overdueRule->id}_task_{$task->id}_due_{$dueAt->timestamp}"
                        );
                        $notificationsSent += count($logs);
                    }
                }
            }
        }

        return [
            'evaluated_tasks' => $activeTasks->count(),
            'marked_overdue' => $markedOverdue,
            'escalations_triggered' => $escalationsTriggered,
            'review_sla_breaches' => $reviewSlaBreaches,
            'notifications_processed' => $notificationsSent,
            'timezone' => $tz,
            'evaluated_at' => $now->toIso8601String(),
        ];
    }

    public function sendManualReminder(DocumentTask $task, User $actor, ?string $customMessage = null): array
    {
        $task->loadMissing(['assignedTeacher', 'teachingAllocation.schoolClass', 'teachingAllocation.section', 'teachingAllocation.subject']);

        if (! $this->scopeAuth->canViewTask($actor, $task) || $actor->hasRole(Role::TEACHER)) {
            throw new DomainRuleException('You are not authorised to send reminders for this task.', 403);
        }

        if (in_array($task->workflow_status, [WorkflowStatus::APPROVED->value, WorkflowStatus::CANCELLED->value], true)) {
            throw new DomainRuleException('Cannot send overdue reminders for approved or cancelled tasks.');
        }

        if (! $task->assignedTeacher || ! $task->assignedTeacher->is_active) {
            throw new DomainRuleException('Assigned teacher is inactive; reassign the task first.');
        }

        $tz = SystemSetting::timezone();
        $now = now($tz);
        $message = $customMessage && trim($customMessage) !== ''
            ? trim($customMessage)
            : sprintf(
                'Reminder from %s: Please submit "%s" (%s) which was due on %s.',
                $actor->full_name,
                $task->title,
                $task->period_label,
                $task->due_at?->timezone($tz)->format('d M Y, h:i A') ?? 'N/A'
            );

        $logs = $this->notifications->dispatch(
            'MANUAL_REMINDER',
            'document_task',
            $task->id,
            $task->assignedTeacher,
            "Reminder: {$task->title}",
            $message,
            null,
            "manual_{$task->id}_{$actor->id}_" . $now->format('YmdHi')
        );

        $task->update(['last_reminder_at' => $now]);

        $this->audit->log(
            'task.reminder_sent',
            'document_task',
            $task->id,
            null,
            ['recipient_user_id' => $task->assigned_teacher_id, 'message' => $message],
            $actor
        );

        return $logs;
    }

    private function resolveRuleRecipients(DocumentTask $task, string $targetRoleKey): array
    {
        if ($targetRoleKey === Role::TEACHER) {
            return ($task->assignedTeacher && $task->assignedTeacher->is_active)
                ? [$task->assignedTeacher]
                : [];
        }

        if ($targetRoleKey === Role::COORDINATOR && $task->reviewer && $task->reviewer->is_active) {
            return [$task->reviewer];
        }

        return $this->scopeAuth->resolveScopedUsersForRole($task, $targetRoleKey)->all();
    }

    private function calculateOverdueDays(
        CarbonInterface $dueAt,
        CarbonInterface $now,
        bool $skipWeekends,
        array $holidayDates
    ): int {
        if ($now->lessThanOrEqualTo($dueAt)) {
            return 0;
        }

        if (! $skipWeekends && empty($holidayDates)) {
            return max(1, (int) floor($dueAt->diffInHours($now) / 24));
        }

        $cursor = Carbon::instance($dueAt)->copy()->startOfDay()->addDay();
        $end = Carbon::instance($now)->copy()->startOfDay();
        $days = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            $dateStr = $cursor->toDateString();
            $isWeekend = $skipWeekends && $cursor->isWeekend();
            $isHoliday = in_array($dateStr, $holidayDates, true);
            if (! $isWeekend && ! $isHoliday) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }
}
