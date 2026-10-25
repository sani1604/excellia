<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicYear;
use App\Models\DocumentSubmission;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\SubmissionReview;
use App\Models\SubmissionVersion;
use App\Models\SystemSetting;
use App\Models\TeachingAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TaskWorkflowService
{
    public function __construct(
        private readonly ScopeAuthorizationService $scopeAuth,
        private readonly GoogleDriveService $driveService,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {}

    /**
     * Creates a single document task after validating active academic year, allocation, document type, and duplicate prevention.
     */
    public function createTask(array $data, User $actor): DocumentTask
    {
        $allocation = TeachingAllocation::query()
            ->with(['academicYear', 'schoolClass', 'section', 'subject', 'teacher'])
            ->findOrFail($data['teaching_allocation_id']);

        if (! $allocation->is_active) {
            throw new DomainRuleException('Cannot assign tasks against an inactive teaching allocation.');
        }

        if (! $allocation->teacher || ! $allocation->teacher->is_active) {
            throw new DomainRuleException('Cannot assign tasks to an inactive teacher.');
        }

        if (! $this->scopeAuth->canManageTaskInScope($actor, $allocation)) {
            throw new DomainRuleException('You are not authorised to assign tasks for this class/section/subject scope.', 403);
        }

        $academicYear = $allocation->academicYear;
        if (! $academicYear || $academicYear->status === 'ARCHIVED') {
            throw new DomainRuleException('Cannot create tasks in an archived academic year.');
        }

        if (isset($data['academic_year_id']) && (int) $data['academic_year_id'] !== (int) $allocation->academic_year_id) {
            throw new DomainRuleException('Task academic year does not match the selected teaching allocation.');
        }

        $docType = DocumentType::query()->findOrFail($data['document_type_id']);
        if (! $docType->is_active) {
            throw new DomainRuleException('Selected document type is inactive.');
        }

        $periodLabel = trim((string) $data['period_label']);
        $duplicate = DocumentTask::query()
            ->where('teaching_allocation_id', $allocation->id)
            ->where('document_type_id', $docType->id)
            ->where('period_label', $periodLabel)
            ->where('workflow_status', '!=', WorkflowStatus::CANCELLED->value)
            ->exists();

        if ($duplicate) {
            throw new DomainRuleException("An active task already exists for {$docType->name} ({$periodLabel}) in this allocation.");
        }

        $tz = SystemSetting::timezone();
        $dueAt = Carbon::parse($data['due_at'], $tz);
        $now = now($tz);
        $isOverdue = $dueAt->lessThan($now);

        $task = DB::transaction(function () use ($data, $allocation, $docType, $periodLabel, $dueAt, $isOverdue, $actor) {
            $task = DocumentTask::query()->create([
                'academic_year_id' => $allocation->academic_year_id,
                'teaching_allocation_id' => $allocation->id,
                'assigned_teacher_id' => $allocation->teacher_user_id,
                'document_type_id' => $docType->id,
                'title' => trim((string) $data['title']),
                'instructions' => $data['instructions'] ?? null,
                'period_label' => $periodLabel,
                'due_at' => $dueAt,
                'reviewer_user_id' => $data['reviewer_user_id'] ?? null,
                'reviewer_role_id' => $data['reviewer_role_id'] ?? $docType->default_reviewer_role_id,
                'workflow_status' => WorkflowStatus::NOT_STARTED->value,
                'is_overdue' => $isOverdue,
                'overdue_since' => $isOverdue ? $dueAt : null,
                'is_required' => (bool) ($data['is_required'] ?? true),
                'schedule_idempotency_key' => $data['schedule_idempotency_key'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->audit->log(
                'task.created',
                'document_task',
                $task->id,
                null,
                $task->toArray(),
                $actor
            );

            return $task;
        });

        $this->notifications->dispatch(
            'TASK_ASSIGNED',
            'document_task',
            $task->id,
            $allocation->teacher,
            "New Task Assigned: {$task->title}",
            sprintf(
                '%s (%s - %s %s) for %s is due on %s.',
                $docType->name,
                $allocation->subject?->name ?? 'Class Teacher',
                $allocation->schoolClass?->name ?? '',
                $allocation->section?->name ?? '',
                $task->period_label,
                $task->due_at->timezone($tz)->format('d M Y, h:i A')
            ),
            null,
            "assigned_v1_{$task->id}"
        );

        return $task->fresh(['academicYear', 'teachingAllocation.schoolClass', 'teachingAllocation.section', 'teachingAllocation.subject', 'assignedTeacher', 'documentType']);
    }

    /**
     * Idempotently generates tasks from an approved schedule for verified teaching allocations only (Sections 3.8, 4.1, 15).
     */
    public function generateRecurringTasks(array $params, User $actor): array
    {
        $academicYear = AcademicYear::query()->findOrFail($params['academic_year_id']);
        if ($academicYear->status === 'ARCHIVED') {
            throw new DomainRuleException('Cannot generate recurring tasks for an archived academic year.');
        }

        $docType = DocumentType::query()->findOrFail($params['document_type_id']);
        if (! $docType->is_active) {
            throw new DomainRuleException('Cannot generate tasks for an inactive document type.');
        }

        $periodLabel = trim((string) $params['period_label']);
        $allocQuery = TeachingAllocation::query()
            ->with(['academicYear', 'schoolClass', 'section', 'subject', 'teacher'])
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->whereNotNull('subject_id');

        if (! empty($params['class_id'])) {
            $allocQuery->where('class_id', (int) $params['class_id']);
        }
        if (! empty($params['section_id'])) {
            $allocQuery->where('section_id', (int) $params['section_id']);
        }
        if (! empty($params['subject_id'])) {
            $allocQuery->where('subject_id', (int) $params['subject_id']);
        }

        $this->scopeAuth->applyAllocationScope($allocQuery, $actor);
        $allocations = $allocQuery->get();

        if ($allocations->isEmpty()) {
            throw new DomainRuleException('No matching active teaching allocations found for task generation.');
        }

        // Enforce Section 3.8 & 15: allocations must be verified against timetable before bulk task generation
        $unverifiedCount = $allocations->filter(fn (TeachingAllocation $a) => ! $a->isVerified())->count();
        if ($unverifiedCount > 0) {
            throw new DomainRuleException(
                "{$unverifiedCount} matching teaching allocation(s) have not been verified against the approved timetable yet. Verify allocations before generating tasks."
            );
        }

        $created = [];
        $skipped = 0;

        foreach ($allocations as $allocation) {
            if (! $allocation->teacher || ! $allocation->teacher->is_active) {
                $skipped++;
                continue;
            }

            if (! $this->scopeAuth->canManageTaskInScope($actor, $allocation)) {
                $skipped++;
                continue;
            }

            $idempotencyKey = sprintf(
                'sched:%d:alloc:%d:doc:%d:period:%s',
                $academicYear->id,
                $allocation->id,
                $docType->id,
                mb_strtolower($periodLabel)
            );

            $existing = DocumentTask::query()
                ->where(function ($q) use ($idempotencyKey, $allocation, $docType, $periodLabel) {
                    $q->where('schedule_idempotency_key', $idempotencyKey)
                        ->orWhere(function ($q2) use ($allocation, $docType, $periodLabel) {
                            $q2->where('teaching_allocation_id', $allocation->id)
                                ->where('document_type_id', $docType->id)
                                ->where('period_label', $periodLabel)
                                ->where('workflow_status', '!=', WorkflowStatus::CANCELLED->value);
                        });
                })
                ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            $title = trim((string) ($params['title_prefix'] ?? $docType->name))
                . ' — ' . ($allocation->subject?->name ?? '')
                . ' (' . $allocation->schoolClass->name . '-' . $allocation->section->name . ')';

            $created[] = $this->createTask([
                'academic_year_id' => $academicYear->id,
                'teaching_allocation_id' => $allocation->id,
                'document_type_id' => $docType->id,
                'title' => $title,
                'instructions' => $params['instructions'] ?? null,
                'period_label' => $periodLabel,
                'due_at' => $params['due_at'],
                'reviewer_user_id' => $params['reviewer_user_id'] ?? null,
                'is_required' => $params['is_required'] ?? true,
                'schedule_idempotency_key' => $idempotencyKey,
            ], $actor);
        }

        $this->audit->log(
            'task.schedule_generated',
            'academic_year',
            $academicYear->id,
            null,
            [
                'document_type_id' => $docType->id,
                'period_label' => $periodLabel,
                'created_count' => count($created),
                'skipped_existing_count' => $skipped,
            ],
            $actor
        );

        return [
            'created' => $created,
            'created_count' => count($created),
            'skipped_count' => $skipped,
        ];
    }

    /**
     * Updates task deadline/reviewer/title, recalculating overdue status and logging audit trail.
     */
    public function updateTask(DocumentTask $task, array $data, User $actor): DocumentTask
    {
        $task->loadMissing('teachingAllocation.schoolClass');
        if (! $this->scopeAuth->canManageTaskInScope($actor, $task->teachingAllocation)) {
            throw new DomainRuleException('You are not authorised to modify this task.', 403);
        }

        if ($task->workflow_status === WorkflowStatus::CANCELLED->value) {
            throw new DomainRuleException('Cancelled tasks cannot be modified.');
        }

        $before = $task->toArray();
        $tz = SystemSetting::timezone();

        return DB::transaction(function () use ($task, $data, $before, $actor, $tz) {
            if (array_key_exists('title', $data)) {
                $task->title = trim((string) $data['title']);
            }
            if (array_key_exists('instructions', $data)) {
                $task->instructions = $data['instructions'];
            }
            if (array_key_exists('period_label', $data)) {
                $task->period_label = trim((string) $data['period_label']);
            }
            if (array_key_exists('reviewer_user_id', $data)) {
                $task->reviewer_user_id = $data['reviewer_user_id'] ?: null;
            }
            if (array_key_exists('due_at', $data) && $data['due_at']) {
                $newDue = Carbon::parse($data['due_at'], $tz);
                $task->due_at = $newDue;

                // Recalculate overdue state on deadline change (Rule 13.10)
                if (in_array($task->workflow_status, [WorkflowStatus::APPROVED->value, WorkflowStatus::CANCELLED->value], true)) {
                    $task->is_overdue = false;
                    $task->overdue_since = null;
                } elseif ($newDue->greaterThanOrEqualTo(now($tz))) {
                    $task->is_overdue = false;
                    $task->overdue_since = null;
                    $task->escalation_level = 0;
                    $task->escalated_at = null;
                } else {
                    $task->is_overdue = true;
                    $task->overdue_since = $newDue;
                }
            }

            if (! empty($data['assigned_teacher_id']) && (int) $data['assigned_teacher_id'] !== (int) $task->assigned_teacher_id) {
                if ($task->workflow_status === WorkflowStatus::APPROVED->value) {
                    throw new DomainRuleException('Cannot reassign an already approved historical task.');
                }
                $newTeacher = User::query()->findOrFail((int) $data['assigned_teacher_id']);
                if (! $newTeacher->is_active) {
                    throw new DomainRuleException('Cannot reassign task to an inactive user.');
                }
                $task->assigned_teacher_id = $newTeacher->id;
                $this->audit->log('task.reassigned', 'document_task', $task->id, $before, $task->toArray(), $actor);
            }

            $task->save();

            $this->audit->log(
                isset($data['due_at']) ? 'task.due_date_updated' : 'task.updated',
                'document_task',
                $task->id,
                $before,
                $task->fresh()->toArray(),
                $actor
            );

            return $task->fresh();
        });
    }

    public function cancelTask(DocumentTask $task, string $reason, User $actor): DocumentTask
    {
        $task->loadMissing('teachingAllocation.schoolClass');
        if (! $actor->hasPermission('tasks.cancel') || ! $this->scopeAuth->canManageTaskInScope($actor, $task->teachingAllocation)) {
            throw new DomainRuleException('You are not authorised to cancel this task.', 403);
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new DomainRuleException('Cancellation reason is mandatory.');
        }

        if ($task->workflow_status === WorkflowStatus::CANCELLED->value) {
            throw new DomainRuleException('Task is already cancelled.');
        }

        $before = $task->toArray();

        $task->update([
            'workflow_status' => WorkflowStatus::CANCELLED->value,
            'is_overdue' => false,
            'is_review_overdue' => false,
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancellation_reason' => $cleanReason,
        ]);

        if ($task->submission) {
            $task->submission->update([
                'current_status' => WorkflowStatus::CANCELLED->value,
                'closed_at' => now(),
            ]);
        }

        $this->audit->log('task.cancelled', 'document_task', $task->id, $before, $task->fresh()->toArray(), $actor);

        return $task->fresh();
    }

    /**
     * Teacher submits or resubmits a document (either via portal file upload or approved Google Drive link).
     * Creates a new immutable SubmissionVersion and transitions workflow status inside a DB transaction.
     */
    public function submitTaskDocument(
        DocumentTask $task,
        User $teacher,
        ?UploadedFile $uploadedFile = null,
        ?array $driveLinkPayload = null,
        ?string $teacherNote = null
    ): SubmissionVersion {
        $task->loadMissing(['documentType', 'teachingAllocation.schoolClass', 'teachingAllocation.section', 'teachingAllocation.subject', 'submission']);

        if (! $this->scopeAuth->canSubmitTask($teacher, $task)) {
            throw new DomainRuleException('You can only submit documents for tasks assigned to you.', 403);
        }

        if ($task->workflow_status === WorkflowStatus::APPROVED->value) {
            throw new DomainRuleException('This task is already approved and closed. Authorised reopening is required before making further changes.');
        }

        if ($task->workflow_status === WorkflowStatus::CANCELLED->value) {
            throw new DomainRuleException('Cannot submit against a cancelled task.');
        }

        $isResubmission = $task->submission !== null && $task->submission->versions()->exists();
        if ($isResubmission && ! $teacher->hasPermission('submissions.resubmit_own')) {
            throw new DomainRuleException('You do not have permission to resubmit documents.', 403);
        }

        if ($uploadedFile === null && empty($driveLinkPayload['drive_file_id_or_url'])) {
            throw new DomainRuleException('Please upload a document file or select a valid Google Drive file.');
        }

        $allowedModes = (array) SystemSetting::getValue('submission_modes', ['PORTAL_UPLOAD', 'DRIVE_LINK']);
        $mode = $uploadedFile ? SubmissionVersion::MODE_PORTAL_UPLOAD : SubmissionVersion::MODE_DRIVE_LINK;
        if (! in_array($mode, $allowedModes, true)) {
            throw new DomainRuleException("Submission mode {$mode} is currently disabled by school policy.");
        }

        $nextVersionNumber = $task->submission
            ? ((int) $task->submission->versions()->max('version_number') + 1)
            : 1;

        // Perform file validation & Drive upload BEFORE opening/committing DB changes so failed uploads NEVER record a false submission!
        if ($uploadedFile !== null) {
            $storedMeta = $this->driveService->storeUploadedSubmissionFile($uploadedFile, $task, $nextVersionNumber, $teacher);
        } else {
            $linked = $this->driveService->validateLinkedDriveFile(
                (string) $driveLinkPayload['drive_file_id_or_url'],
                (string) ($driveLinkPayload['drive_file_name'] ?? 'linked-document.pdf'),
                (int) ($driveLinkPayload['file_size_bytes'] ?? 102400),
                (string) ($driveLinkPayload['mime_type'] ?? 'application/pdf'),
                $task->documentType
            );
            $mapping = $this->driveService->resolveFolderMappingForTask($task);
            $storedMeta = [
                'drive_file_id' => $linked['drive_file_id'],
                'drive_folder_id' => $mapping?->drive_folder_id,
                'drive_file_name' => $linked['drive_file_name'],
                'drive_file_url' => $linked['drive_file_url'],
                'storage_disk_path' => null,
                'mime_type' => $linked['mime_type'],
                'file_extension' => $linked['extension'],
                'file_size_bytes' => $linked['size_bytes'],
                'sha256_checksum' => null,
            ];
        }

        $newWorkflowStatus = $nextVersionNumber > 1
            ? WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value
            : WorkflowStatus::SUBMITTED_PENDING_REVIEW->value;

        $reviewSlaHours = (int) SystemSetting::getValue(
            'review_sla_hours',
            config('excellia.workflow.default_review_sla_hours', 48)
        );
        $now = now(SystemSetting::timezone());

        $version = DB::transaction(function () use (
            $task,
            $teacher,
            $mode,
            $nextVersionNumber,
            $storedMeta,
            $teacherNote,
            $newWorkflowStatus,
            $reviewSlaHours,
            $now
        ) {
            $submission = DocumentSubmission::query()->firstOrCreate(
                ['document_task_id' => $task->id],
                [
                    'current_status' => $newWorkflowStatus,
                    'submitted_at' => $now,
                    'last_submitted_at' => $now,
                ]
            );

            $version = SubmissionVersion::query()->create([
                'document_submission_id' => $submission->id,
                'version_number' => $nextVersionNumber,
                'submission_mode' => $mode,
                'drive_file_id' => $storedMeta['drive_file_id'],
                'drive_folder_id' => $storedMeta['drive_folder_id'],
                'drive_file_name' => $storedMeta['drive_file_name'],
                'drive_file_url' => $storedMeta['drive_file_url'],
                'storage_disk_path' => $storedMeta['storage_disk_path'],
                'mime_type' => $storedMeta['mime_type'],
                'file_extension' => $storedMeta['file_extension'],
                'file_size_bytes' => $storedMeta['file_size_bytes'],
                'sha256_checksum' => $storedMeta['sha256_checksum'],
                'reviewer_access_verified' => true,
                'uploaded_by' => $teacher->id,
                'uploaded_at' => $now,
                'teacher_note' => $teacherNote ? trim($teacherNote) : null,
            ]);

            $submission->update([
                'current_version_id' => $version->id,
                'current_status' => $newWorkflowStatus,
                'submitted_at' => $submission->submitted_at ?? $now,
                'last_submitted_at' => $now,
                'approved_at' => null,
                'closed_at' => null,
            ]);

            $task->update([
                'workflow_status' => $newWorkflowStatus,
                'review_due_at' => $now->copy()->addHours(max(1, $reviewSlaHours)),
                'is_review_overdue' => false,
                'review_overdue_since' => null,
            ]);

            $this->audit->log(
                $nextVersionNumber > 1 ? 'submission.resubmitted' : 'submission.uploaded',
                'submission_version',
                $version->id,
                null,
                [
                    'task_id' => $task->id,
                    'submission_id' => $submission->id,
                    'version_number' => $version->version_number,
                    'submission_mode' => $version->submission_mode,
                    'drive_file_id' => $version->drive_file_id,
                    'drive_file_name' => $version->drive_file_name,
                    'file_size_bytes' => $version->file_size_bytes,
                ],
                $teacher
            );

            return $version;
        });

        // Notify assigned reviewer or scoped coordinators
        $reviewers = collect();
        if ($task->reviewer_user_id) {
            $directReviewer = User::query()->find($task->reviewer_user_id);
            if ($directReviewer && $directReviewer->is_active) {
                $reviewers->push($directReviewer);
            }
        }
        if ($reviewers->isEmpty()) {
            $reviewers = $this->scopeAuth->resolveScopedUsersForRole($task, Role::COORDINATOR);
        }

        $eventKey = $nextVersionNumber > 1 ? 'RESUBMISSION_RECEIVED' : 'SUBMISSION_RECEIVED';
        foreach ($reviewers as $reviewer) {
            $this->notifications->dispatch(
                $eventKey,
                'document_task',
                $task->id,
                $reviewer,
                ($nextVersionNumber > 1 ? 'Resubmission Received: ' : 'Submission Received: ') . $task->title,
                "{$teacher->full_name} uploaded version v{$version->version_number} ({$version->drive_file_name}) for review.",
                null,
                "sub_v{$version->version_number}_task_{$task->id}"
            );
        }

        return $version->fresh(['submission.task', 'uploader']);
    }

    /**
     * Reviewer approves the current version of a submission.
     */
    public function approveSubmission(DocumentSubmission $submission, User $reviewer, ?string $remarks = null): SubmissionReview
    {
        $submission->loadMissing(['task.teachingAllocation.schoolClass', 'task.assignedTeacher', 'currentVersion']);
        $task = $submission->task;

        if (! $reviewer->hasPermission('submissions.approve') || ! $this->scopeAuth->canReviewTask($reviewer, $task)) {
            throw new DomainRuleException('You are not authorised to approve submissions in this academic scope.', 403);
        }

        if (! $task->canReviewerDecide() || ! $submission->currentVersion) {
            throw new DomainRuleException('Only submissions currently pending review can be approved.');
        }

        $now = now(SystemSetting::timezone());
        $cleanRemarks = $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null;

        $review = DB::transaction(function () use ($submission, $task, $reviewer, $cleanRemarks, $now) {
            $beforeTask = $task->toArray();

            $review = SubmissionReview::query()->create([
                'submission_version_id' => $submission->current_version_id,
                'reviewer_user_id' => $reviewer->id,
                'decision' => SubmissionReview::DECISION_APPROVED,
                'remarks' => $cleanRemarks,
                'reviewed_at' => $now,
            ]);

            $submission->update([
                'current_status' => WorkflowStatus::APPROVED->value,
                'approved_at' => $now,
                'closed_at' => $now,
            ]);

            $task->update([
                'workflow_status' => WorkflowStatus::APPROVED->value,
                'is_overdue' => false,
                'is_review_overdue' => false,
                'review_overdue_since' => null,
            ]);

            $this->audit->log(
                'submission.approved',
                'document_submission',
                $submission->id,
                $beforeTask,
                [
                    'task_id' => $task->id,
                    'version_id' => $submission->current_version_id,
                    'decision' => 'APPROVED',
                    'remarks' => $cleanRemarks,
                ],
                $reviewer
            );

            return $review;
        });

        if ($task->assignedTeacher) {
            $this->notifications->dispatch(
                'SUBMISSION_APPROVED',
                'document_task',
                $task->id,
                $task->assignedTeacher,
                "Approved: {$task->title}",
                "Your submission (v{$submission->currentVersion->version_number}) for {$task->title} was approved by {$reviewer->full_name}."
                    . ($cleanRemarks ? " Remarks: {$cleanRemarks}" : ''),
                null,
                "approved_v{$submission->currentVersion->version_number}_task_{$task->id}"
            );
        }

        return $review->fresh(['reviewer', 'version']);
    }

    /**
     * Reviewer returns the current version for correction — remarks are strictly mandatory!
     */
    public function returnSubmission(DocumentSubmission $submission, User $reviewer, string $remarks): SubmissionReview
    {
        $submission->loadMissing(['task.teachingAllocation.schoolClass', 'task.assignedTeacher', 'currentVersion']);
        $task = $submission->task;

        if (! $reviewer->hasPermission('submissions.return') || ! $this->scopeAuth->canReviewTask($reviewer, $task)) {
            throw new DomainRuleException('You are not authorised to return submissions in this academic scope.', 403);
        }

        $cleanRemarks = trim($remarks);
        if ($cleanRemarks === '') {
            throw new DomainRuleException('Reviewer remarks are mandatory when returning a submission for correction.');
        }

        if (! $task->canReviewerDecide() || ! $submission->currentVersion) {
            throw new DomainRuleException('Only submissions currently pending review can be returned for correction.');
        }

        $tz = SystemSetting::timezone();
        $now = now($tz);
        $isPastDue = $task->due_at && $task->due_at->lessThan($now);

        $review = DB::transaction(function () use ($submission, $task, $reviewer, $cleanRemarks, $now, $isPastDue) {
            $beforeTask = $task->toArray();

            $review = SubmissionReview::query()->create([
                'submission_version_id' => $submission->current_version_id,
                'reviewer_user_id' => $reviewer->id,
                'decision' => SubmissionReview::DECISION_RETURNED,
                'remarks' => $cleanRemarks,
                'reviewed_at' => $now,
            ]);

            $submission->update([
                'current_status' => WorkflowStatus::RETURNED_FOR_CORRECTION->value,
                'approved_at' => null,
                'closed_at' => null,
            ]);

            $task->update([
                'workflow_status' => WorkflowStatus::RETURNED_FOR_CORRECTION->value,
                'is_overdue' => $isPastDue,
                'overdue_since' => $isPastDue ? ($task->overdue_since ?? $task->due_at) : null,
                'review_due_at' => null,
                'is_review_overdue' => false,
                'review_overdue_since' => null,
            ]);

            $this->audit->log(
                'submission.returned',
                'document_submission',
                $submission->id,
                $beforeTask,
                [
                    'task_id' => $task->id,
                    'version_id' => $submission->current_version_id,
                    'decision' => 'RETURNED',
                    'remarks' => $cleanRemarks,
                ],
                $reviewer
            );

            return $review;
        });

        if ($task->assignedTeacher) {
            $this->notifications->dispatch(
                'SUBMISSION_RETURNED',
                'document_task',
                $task->id,
                $task->assignedTeacher,
                "Returned for Correction: {$task->title}",
                "{$reviewer->full_name} returned v{$submission->currentVersion->version_number} for correction. Remarks: {$cleanRemarks}",
                null,
                "returned_v{$submission->currentVersion->version_number}_task_{$task->id}_{$review->id}"
            );
        }

        return $review->fresh(['reviewer', 'version']);
    }

    /**
     * Authorised reopening of an approved task (Rule 4.4 & 13.6: requires authorised action, reason, and audit event).
     */
    public function reopenApprovedTask(DocumentTask $task, string $reason, User $actor): DocumentTask
    {
        $task->loadMissing(['teachingAllocation.schoolClass', 'submission', 'assignedTeacher']);

        if (! $actor->hasPermission('tasks.update') || ! $this->scopeAuth->canManageTaskInScope($actor, $task->teachingAllocation)) {
            throw new DomainRuleException('You are not authorised to reopen an approved task.', 403);
        }

        if ($task->workflow_status !== WorkflowStatus::APPROVED->value) {
            throw new DomainRuleException('Only approved tasks can be reopened.');
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new DomainRuleException('A reason is required to reopen an approved task.');
        }

        $before = $task->toArray();
        $now = now(SystemSetting::timezone());

        DB::transaction(function () use ($task, $cleanReason, $actor, $before, $now) {
            $task->update([
                'workflow_status' => WorkflowStatus::RETURNED_FOR_CORRECTION->value,
                'reopened_at' => $now,
                'reopened_by' => $actor->id,
                'reopen_reason' => $cleanReason,
                'is_overdue' => $task->due_at && $task->due_at->lessThan($now),
            ]);

            if ($task->submission) {
                $task->submission->update([
                    'current_status' => WorkflowStatus::RETURNED_FOR_CORRECTION->value,
                    'approved_at' => null,
                    'closed_at' => null,
                ]);
            }

            $this->audit->log('task.reopened', 'document_task', $task->id, $before, $task->fresh()->toArray(), $actor);
        });

        if ($task->assignedTeacher) {
            $this->notifications->dispatch(
                'SUBMISSION_RETURNED',
                'document_task',
                $task->id,
                $task->assignedTeacher,
                "Task Reopened: {$task->title}",
                "{$actor->full_name} reopened {$task->title}. Reason: {$cleanReason}",
                null,
                "reopened_{$task->id}_{$now->timestamp}"
            );
        }

        return $task->fresh();
    }
}
