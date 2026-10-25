<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicYear;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\Section;
use App\Models\SystemSetting;
use App\Models\TeachingAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MasterDataService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly GoogleDriveService $driveService,
    ) {}

    public function createUser(array $data, User $actor): User
    {
        $email = mb_strtolower(trim((string) $data['email']));
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw new DomainRuleException("A user with email {$email} already exists.");
        }

        if (! empty($data['employee_code'])) {
            $emp = trim((string) $data['employee_code']);
            if (User::withTrashed()->where('employee_code', $emp)->exists()) {
                throw new DomainRuleException("Employee code {$emp} is already assigned to another user.");
            }
        }

        return DB::transaction(function () use ($data, $email, $actor) {
            $user = User::query()->create([
                'employee_code' => ! empty($data['employee_code']) ? trim((string) $data['employee_code']) : null,
                'full_name' => trim((string) $data['full_name']),
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'password' => ! empty($data['password']) ? Hash::make((string) $data['password']) : Hash::make('Excellia@2026'),
                'login_provider' => $data['login_provider'] ?? 'local',
                'provider_subject_id' => $data['provider_subject_id'] ?? null,
                'primary_role_id' => (int) $data['primary_role_id'],
                'can_access_restricted_files' => (bool) ($data['can_access_restricted_files'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if (! empty($data['secondary_role_ids']) && is_array($data['secondary_role_ids'])) {
                $syncPayload = [];
                foreach ($data['secondary_role_ids'] as $rid) {
                    if ((int) $rid !== (int) $user->primary_role_id) {
                        $syncPayload[(int) $rid] = ['approved_by' => $actor->id];
                    }
                }
                $user->secondaryRoles()->sync($syncPayload);
            }

            $this->audit->log('user.created', 'user', $user->id, null, $user->fresh()->toArray(), $actor);

            return $user->fresh(['primaryRole', 'secondaryRoles']);
        });
    }

    public function updateUser(User $target, array $data, User $actor): User
    {
        $before = $target->toArray();

        if (isset($data['email'])) {
            $email = mb_strtolower(trim((string) $data['email']));
            if (User::withTrashed()->where('email', $email)->where('id', '!=', $target->id)->exists()) {
                throw new DomainRuleException("Email {$email} is already used by another user.");
            }
            $target->email = $email;
        }

        if (array_key_exists('employee_code', $data)) {
            $emp = $data['employee_code'] !== null ? trim((string) $data['employee_code']) : null;
            if ($emp !== null && $emp !== '' && User::withTrashed()->where('employee_code', $emp)->where('id', '!=', $target->id)->exists()) {
                throw new DomainRuleException("Employee code {$emp} is already assigned to another user.");
            }
            $target->employee_code = $emp ?: null;
        }

        if (isset($data['full_name'])) {
            $target->full_name = trim((string) $data['full_name']);
        }
        if (array_key_exists('phone', $data)) {
            $target->phone = $data['phone'];
        }
        if (isset($data['primary_role_id'])) {
            $target->primary_role_id = (int) $data['primary_role_id'];
        }
        if (array_key_exists('can_access_restricted_files', $data)) {
            $target->can_access_restricted_files = (bool) $data['can_access_restricted_files'];
        }
        if (! empty($data['password'])) {
            $target->password = Hash::make((string) $data['password']);
        }
        if (array_key_exists('is_active', $data) && (bool) $data['is_active'] && ! $target->is_active) {
            $target->is_active = true;
            $target->deactivated_at = null;
            $target->deactivation_reason = null;
        }

        $target->updated_by = $actor->id;
        $target->save();
        $target->flushPermissionCache();

        $this->audit->log('user.updated', 'user', $target->id, $before, $target->fresh()->toArray(), $actor);

        return $target->fresh(['primaryRole', 'secondaryRoles']);
    }

    /**
     * Deactivates a user (never hard-deleting historical users) and flags active teacher allocations/open tasks for reassignment!
     */
    public function deactivateUser(User $target, ?string $reason, User $actor): array
    {
        if ($target->id === $actor->id) {
            throw new DomainRuleException('You cannot deactivate your own active account.');
        }

        $before = $target->toArray();

        return DB::transaction(function () use ($target, $reason, $actor, $before) {
            $flaggedAllocations = TeachingAllocation::query()
                ->where('teacher_user_id', $target->id)
                ->where('is_active', true)
                ->update(['needs_reassignment' => true]);

            $openTasksCount = DocumentTask::query()
                ->where('assigned_teacher_id', $target->id)
                ->whereNotIn('workflow_status', [WorkflowStatus::APPROVED->value, WorkflowStatus::CANCELLED->value])
                ->count();

            $target->update([
                'is_active' => false,
                'api_token_hash' => null,
                'deactivated_at' => now(),
                'deactivation_reason' => $reason ? trim($reason) : 'Deactivated by administrator',
                'needs_reassignment_review' => ($flaggedAllocations > 0 || $openTasksCount > 0),
                'updated_by' => $actor->id,
            ]);

            $this->audit->log(
                'user.deactivated',
                'user',
                $target->id,
                $before,
                array_merge($target->fresh()->toArray(), [
                    'flagged_allocations_count' => $flaggedAllocations,
                    'open_tasks_requiring_reassignment' => $openTasksCount,
                ]),
                $actor
            );

            return [
                'user' => $target->fresh(),
                'flagged_allocations' => $flaggedAllocations,
                'open_tasks_count' => $openTasksCount,
            ];
        });
    }

    public function saveAcademicYear(array $data, User $actor, ?AcademicYear $existing = null): AcademicYear
    {
        $isCurrent = (bool) ($data['is_current'] ?? false);
        $status = $data['status'] ?? ($isCurrent ? 'ACTIVE' : 'DRAFT');

        if ($isCurrent && $status === 'ARCHIVED') {
            throw new DomainRuleException('An archived academic year cannot be marked as the current academic year.');
        }

        return DB::transaction(function () use ($data, $isCurrent, $status, $actor, $existing) {
            if ($isCurrent) {
                AcademicYear::query()
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            if ($existing) {
                $before = $existing->toArray();
                $existing->update([
                    'name' => trim((string) ($data['name'] ?? $existing->name)),
                    'start_date' => $data['start_date'] ?? $existing->start_date,
                    'end_date' => $data['end_date'] ?? $existing->end_date,
                    'is_current' => $isCurrent,
                    'status' => $status,
                ]);
                $this->audit->log('academic_year.updated', 'academic_year', $existing->id, $before, $existing->fresh()->toArray(), $actor);

                return $existing->fresh();
            }

            $year = AcademicYear::query()->create([
                'name' => trim((string) $data['name']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_current' => $isCurrent,
                'status' => $status,
            ]);

            $this->audit->log('academic_year.created', 'academic_year', $year->id, null, $year->toArray(), $actor);

            return $year;
        });
    }

    public function saveSection(array $data, User $actor, ?Section $existing = null): Section
    {
        $yearId = (int) ($data['academic_year_id'] ?? $existing?->academic_year_id);
        $classId = (int) ($data['class_id'] ?? $existing?->class_id);
        $name = trim((string) ($data['name'] ?? $existing?->name));

        $dup = Section::query()
            ->where('academic_year_id', $yearId)
            ->where('class_id', $classId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($dup) {
            throw new DomainRuleException("Section '{$name}' already exists for this class and academic year.");
        }

        if ($existing) {
            $before = $existing->toArray();
            $existing->update([
                'name' => $name,
                'display_order' => (int) ($data['display_order'] ?? $existing->display_order),
                'is_active' => (bool) ($data['is_active'] ?? $existing->is_active),
            ]);
            $this->audit->log('section.updated', 'section', $existing->id, $before, $existing->fresh()->toArray(), $actor);

            return $existing->fresh();
        }

        $section = Section::query()->create([
            'academic_year_id' => $yearId,
            'class_id' => $classId,
            'name' => $name,
            'display_order' => (int) ($data['display_order'] ?? 1),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->audit->log('section.created', 'section', $section->id, null, $section->toArray(), $actor);

        return $section;
    }

    /**
     * Enforces Section 3.8 allocation rules:
     * - Section must belong to selected class & academic year.
     * - Either subject_id is set OR is_class_teacher is true (or both).
     * - Prevents duplicate active rows for same year/class/section/subject/teacher.
     * - Enforces single primary class teacher per section unless co-class-teachers setting is enabled.
     * - On teacher change, preserves historical task ownership and only reassigns selected open tasks if requested.
     */
    public function saveTeachingAllocation(array $data, User $actor, ?TeachingAllocation $existing = null): TeachingAllocation
    {
        $yearId = (int) ($data['academic_year_id'] ?? $existing?->academic_year_id);
        $classId = (int) ($data['class_id'] ?? $existing?->class_id);
        $sectionId = (int) ($data['section_id'] ?? $existing?->section_id);
        $subjectId = ! empty($data['subject_id']) ? (int) $data['subject_id'] : null;
        $teacherId = (int) ($data['teacher_user_id'] ?? $existing?->teacher_user_id);
        $isClassTeacher = (bool) ($data['is_class_teacher'] ?? $existing?->is_class_teacher ?? false);
        $isCoClassTeacher = (bool) ($data['is_co_class_teacher'] ?? $existing?->is_co_class_teacher ?? false);
        $isActive = (bool) ($data['is_active'] ?? $existing?->is_active ?? true);

        $section = Section::query()->find($sectionId);
        if (! $section || (int) $section->class_id !== $classId || (int) $section->academic_year_id !== $yearId) {
            throw new DomainRuleException('Selected section does not belong to the chosen class and academic year.');
        }

        if ($subjectId === null && ! $isClassTeacher) {
            throw new DomainRuleException('Subject is required unless this is a dedicated Class Teacher assignment.');
        }

        $teacher = User::query()->find($teacherId);
        if (! $teacher || ! $teacher->is_active) {
            throw new DomainRuleException('Selected teacher does not exist or is inactive.');
        }

        if ($isActive) {
            $duplicateQuery = TeachingAllocation::query()
                ->where('academic_year_id', $yearId)
                ->where('class_id', $classId)
                ->where('section_id', $sectionId)
                ->where('teacher_user_id', $teacherId)
                ->where('is_active', true)
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id));

            if ($subjectId === null) {
                $duplicateQuery->whereNull('subject_id');
            } else {
                $duplicateQuery->where('subject_id', $subjectId);
            }

            if ($duplicateQuery->exists()) {
                throw new DomainRuleException('Duplicate active teaching allocation exists for this Academic Year, Class, Section, Subject, and Teacher.');
            }

            if ($isClassTeacher) {
                $allowCoClassTeachers = (bool) SystemSetting::getValue('allow_co_class_teachers', false);
                if ($isCoClassTeacher && ! $allowCoClassTeachers) {
                    throw new DomainRuleException('Co-class-teacher assignments are currently disabled in school settings.');
                }

                if (! $isCoClassTeacher) {
                    $existingPrimaryCt = TeachingAllocation::query()
                        ->where('academic_year_id', $yearId)
                        ->where('class_id', $classId)
                        ->where('section_id', $sectionId)
                        ->where('is_active', true)
                        ->where('is_class_teacher', true)
                        ->where('is_co_class_teacher', false)
                        ->where('teacher_user_id', '!=', $teacherId)
                        ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                        ->exists();

                    if ($existingPrimaryCt) {
                        throw new DomainRuleException('This section already has an active primary Class Teacher for the academic year.');
                    }
                }
            }
        }

        return DB::transaction(function () use (
            $data,
            $actor,
            $existing,
            $yearId,
            $classId,
            $sectionId,
            $subjectId,
            $teacherId,
            $isClassTeacher,
            $isCoClassTeacher,
            $isActive
        ) {
            if ($existing) {
                $before = $existing->toArray();
                $previousTeacherId = (int) $existing->teacher_user_id;

                $existing->update([
                    'subject_id' => $subjectId,
                    'teacher_user_id' => $teacherId,
                    'is_class_teacher' => $isClassTeacher,
                    'is_co_class_teacher' => $isCoClassTeacher,
                    'allocation_start_date' => $data['allocation_start_date'] ?? $existing->allocation_start_date,
                    'allocation_end_date' => $data['allocation_end_date'] ?? $existing->allocation_end_date,
                    'is_active' => $isActive,
                    'needs_reassignment' => false,
                ]);

                // Rule 3.8 & 13.11: Preserve historical task ownership; reassign only open tasks if explicitly requested
                if ($previousTeacherId !== $teacherId && ! empty($data['reassign_open_tasks'])) {
                    DocumentTask::query()
                        ->where('teaching_allocation_id', $existing->id)
                        ->whereIn('workflow_status', [
                            WorkflowStatus::NOT_STARTED->value,
                            WorkflowStatus::RETURNED_FOR_CORRECTION->value,
                        ])
                        ->update(['assigned_teacher_id' => $teacherId]);
                }

                $this->audit->log('allocation.updated', 'teaching_allocation', $existing->id, $before, $existing->fresh()->toArray(), $actor);

                $refreshed = $existing->fresh(['academicYear', 'schoolClass', 'section', 'subject', 'teacher']);
                if ($refreshed->is_active && $refreshed->subject_id && $refreshed->isVerified()) {
                    $this->syncAllocationDriveMappings($refreshed, $actor);
                }

                return $refreshed;
            }

            $verifiedNow = ! empty($data['mark_verified']) ? now() : null;
            $allocation = TeachingAllocation::query()->create([
                'academic_year_id' => $yearId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'teacher_user_id' => $teacherId,
                'is_class_teacher' => $isClassTeacher,
                'is_co_class_teacher' => $isCoClassTeacher,
                'allocation_start_date' => $data['allocation_start_date'] ?? null,
                'allocation_end_date' => $data['allocation_end_date'] ?? null,
                'is_active' => $isActive,
                'verified_by' => $verifiedNow ? $actor->id : null,
                'verified_at' => $verifiedNow,
            ]);

            $this->audit->log('allocation.created', 'teaching_allocation', $allocation->id, null, $allocation->toArray(), $actor);

            $freshAlloc = $allocation->fresh(['academicYear', 'schoolClass', 'section', 'subject', 'teacher']);
            if ($freshAlloc->is_active && $freshAlloc->subject_id && $freshAlloc->isVerified()) {
                $this->syncAllocationDriveMappings($freshAlloc, $actor);
            }

            return $freshAlloc;
        });
    }

    public function verifyAllocation(TeachingAllocation $allocation, User $actor): TeachingAllocation
    {
        $before = $allocation->toArray();
        $allocation->update([
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        $this->audit->log('allocation.verified', 'teaching_allocation', $allocation->id, $before, $allocation->fresh()->toArray(), $actor);

        $fresh = $allocation->fresh(['academicYear', 'schoolClass', 'section', 'subject', 'teacher']);
        if ($fresh->is_active && $fresh->subject_id) {
            $this->syncAllocationDriveMappings($fresh, $actor);
        }

        return $fresh;
    }

    /**
     * Automatically ensures canonical Google Drive folder mappings exist for a verified subject allocation
     * so school staff do not need to manually enter Drive folder IDs.
     */
    public function syncAllocationDriveMappings(TeachingAllocation $alloc, User $actor): void
    {
        if ((bool) config('excellia.drive.simulate_outage', false) || config('excellia.drive.mode') === 'google_api') {
            return;
        }

        $alloc->loadMissing(['academicYear', 'schoolClass', 'section', 'subject', 'teacher']);
        if (! $alloc->teacher || ! $alloc->subject || ! $alloc->academicYear) {
            return;
        }

        $docTypes = DocumentType::query()->where('is_active', true)->orderBy('display_order')->get();
        $classLabel = $alloc->schoolClass?->drive_folder_label ?: ($alloc->schoolClass?->name ?? 'Class');
        $sectionLabel = 'Section ' . ($alloc->section?->name ?? '');
        $subjectTeacherLabel = $alloc->subject->name . ' – ' . $alloc->teacher->full_name;

        foreach ($docTypes as $dt) {
            $exists = DriveFolderMapping::query()
                ->where('academic_year_id', $alloc->academic_year_id)
                ->where('class_id', $alloc->class_id)
                ->where('section_id', $alloc->section_id)
                ->where('subject_id', $alloc->subject_id)
                ->where('teacher_user_id', $alloc->teacher_user_id)
                ->where('document_type_id', $dt->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $displayPath = "{$classLabel} / {$sectionLabel} / {$subjectTeacherLabel} / {$dt->folder_name}";
            $seedSlug = sprintf(
                '%s_%s_%s_%s_T%d_%s',
                preg_replace('/\W+/', '', $alloc->academicYear->name),
                $alloc->schoolClass?->code ?? $alloc->class_id,
                $alloc->section?->name ?? $alloc->section_id,
                $alloc->subject?->code ?? $alloc->subject_id,
                $alloc->teacher_user_id,
                $dt->folder_code
            );

            $folderId = $this->driveService->provisionFolder($dt->folder_name, null, $seedSlug);

            DriveFolderMapping::query()->create([
                'academic_year_id' => $alloc->academic_year_id,
                'class_id' => $alloc->class_id,
                'section_id' => $alloc->section_id,
                'subject_id' => $alloc->subject_id,
                'teacher_user_id' => $alloc->teacher_user_id,
                'document_type_id' => $dt->id,
                'drive_folder_id' => $folderId,
                'drive_folder_name' => $displayPath,
                'drive_folder_url' => "https://drive.google.com/drive/folders/{$folderId}",
                'access_policy_key' => $dt->access_policy_key ?: 'STANDARD_ACADEMIC',
                'is_active' => true,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
        }
    }

    public function saveDriveMapping(array $data, User $actor, ?DriveFolderMapping $existing = null): DriveFolderMapping
    {
        $yearId = (int) ($data['academic_year_id'] ?? $existing?->academic_year_id);
        $classId = (int) ($data['class_id'] ?? $existing?->class_id);
        $sectionId = (int) ($data['section_id'] ?? $existing?->section_id);
        $subjectId = (int) ($data['subject_id'] ?? $existing?->subject_id);
        $teacherId = ! empty($data['teacher_user_id']) ? (int) $data['teacher_user_id'] : null;
        $docTypeId = ! empty($data['document_type_id']) ? (int) $data['document_type_id'] : null;

        $section = Section::query()->find($sectionId);
        if (! $section || (int) $section->class_id !== $classId || (int) $section->academic_year_id !== $yearId) {
            throw new DomainRuleException('Drive folder mapping section does not belong to the selected class and academic year.');
        }

        // Section 10: Create mappings only for subjects actually taught in each class/section
        $subjectTaught = TeachingAllocation::query()
            ->where('academic_year_id', $yearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->when($teacherId, fn ($q) => $q->where('teacher_user_id', $teacherId))
            ->exists();

        if (! $subjectTaught) {
            throw new DomainRuleException('Cannot map Drive folder: this subject (and teacher, if specified) has no active teaching allocation in the selected class/section.');
        }

        $rawFolder = trim((string) ($data['drive_folder_id'] ?? $existing?->drive_folder_id ?? ''));
        if (str_contains(mb_strtolower($rawFolder), 'anyone') || str_contains(mb_strtolower((string) ($data['drive_folder_url'] ?? '')), 'anyone')) {
            throw new DomainRuleException('Public "Anyone with the link" Google Drive folders are strictly prohibited.');
        }

        $canonicalFolderId = $this->driveService->extractDriveId($rawFolder);
        if (! $canonicalFolderId) {
            throw new DomainRuleException('Invalid canonical Google Drive folder ID or folder URL.');
        }

        $docType = $docTypeId ? DocumentType::query()->find($docTypeId) : null;
        $policyKey = $data['access_policy_key'] ?? $docType?->access_policy_key ?? 'STANDARD_ACADEMIC';
        $folderUrl = "https://drive.google.com/drive/folders/{$canonicalFolderId}";

        if ($existing) {
            $before = $existing->toArray();
            $existing->update([
                'teacher_user_id' => $teacherId,
                'document_type_id' => $docTypeId,
                'drive_folder_id' => $canonicalFolderId,
                'drive_folder_name' => trim((string) ($data['drive_folder_name'] ?? $existing->drive_folder_name)),
                'drive_folder_url' => $folderUrl,
                'access_policy_key' => $policyKey,
                'is_active' => (bool) ($data['is_active'] ?? $existing->is_active),
            ]);
            $this->audit->log('drive_mapping.updated', 'drive_folder_mapping', $existing->id, $before, $existing->fresh()->toArray(), $actor);

            return $existing->fresh();
        }

        $verified = ! empty($data['verify_immediately']);
        $mapping = DriveFolderMapping::query()->create([
            'academic_year_id' => $yearId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'teacher_user_id' => $teacherId,
            'document_type_id' => $docTypeId,
            'drive_folder_id' => $canonicalFolderId,
            'drive_folder_name' => trim((string) $data['drive_folder_name']),
            'drive_folder_url' => $folderUrl,
            'access_policy_key' => $policyKey,
            'is_active' => $verified,
            'verified_by' => $verified ? $actor->id : null,
            'verified_at' => $verified ? now() : null,
        ]);

        $this->audit->log('drive_mapping.created', 'drive_folder_mapping', $mapping->id, null, $mapping->toArray(), $actor);

        return $mapping;
    }

    public function verifyDriveMapping(DriveFolderMapping $mapping, User $actor): DriveFolderMapping
    {
        $before = $mapping->toArray();
        $mapping->update([
            'is_active' => true,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        $this->audit->log('drive_mapping.verified', 'drive_folder_mapping', $mapping->id, $before, $mapping->fresh()->toArray(), $actor);

        return $mapping->fresh();
    }

    /**
     * Auto-provisions missing canonical Google Drive folder mappings for verified active teaching allocations
     * and active document categories (00–05+) in the given academic year. Idempotent on repeat runs.
     */
    public function provisionCanonicalDriveMappings(AcademicYear $academicYear, User $actor): array
    {
        $allocations = TeachingAllocation::query()
            ->with(['schoolClass', 'section', 'subject', 'teacher'])
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->whereNotNull('subject_id')
            ->whereNotNull('verified_at')
            ->get();

        $docTypes = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($allocations as $alloc) {
            if (! $alloc->teacher || ! $alloc->teacher->is_active) {
                continue;
            }

            $classLabel = $alloc->schoolClass?->drive_folder_label ?: ($alloc->schoolClass?->name ?? 'Class');
            $sectionLabel = 'Section ' . ($alloc->section?->name ?? '');
            $subjectTeacherLabel = ($alloc->subject?->name ?? 'Subject') . ' – ' . $alloc->teacher->full_name;

            foreach ($docTypes as $dt) {
                $exists = DriveFolderMapping::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('class_id', $alloc->class_id)
                    ->where('section_id', $alloc->section_id)
                    ->where('subject_id', $alloc->subject_id)
                    ->where('teacher_user_id', $alloc->teacher_user_id)
                    ->where('document_type_id', $dt->id)
                    ->exists();

                if ($exists) {
                    $skippedCount++;
                    continue;
                }

                $displayPath = "{$classLabel} / {$sectionLabel} / {$subjectTeacherLabel} / {$dt->folder_name}";
                $seedSlug = sprintf(
                    '%s_%s_%s_%s_T%d_%s',
                    preg_replace('/\W+/', '', $academicYear->name),
                    $alloc->schoolClass?->code ?? $alloc->class_id,
                    $alloc->section?->name ?? $alloc->section_id,
                    $alloc->subject?->code ?? $alloc->subject_id,
                    $alloc->teacher_user_id,
                    $dt->folder_code
                );

                $folderId = $this->driveService->provisionFolder($dt->folder_name, null, $seedSlug);

                DriveFolderMapping::query()->create([
                    'academic_year_id' => $academicYear->id,
                    'class_id' => $alloc->class_id,
                    'section_id' => $alloc->section_id,
                    'subject_id' => $alloc->subject_id,
                    'teacher_user_id' => $alloc->teacher_user_id,
                    'document_type_id' => $dt->id,
                    'drive_folder_id' => $folderId,
                    'drive_folder_name' => $displayPath,
                    'drive_folder_url' => "https://drive.google.com/drive/folders/{$folderId}",
                    'access_policy_key' => $dt->access_policy_key ?: 'STANDARD_ACADEMIC',
                    'is_active' => true,
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                ]);
                $createdCount++;
            }
        }

        $this->audit->log(
            'drive_mapping.bulk_provisioned',
            'academic_year',
            $academicYear->id,
            null,
            ['created_count' => $createdCount, 'skipped_count' => $skippedCount],
            $actor
        );

        return [
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
        ];
    }
}
