<?php

namespace App\Services;

use App\Models\DocumentTask;
use App\Models\Role;
use App\Models\SubmissionVersion;
use App\Models\TeachingAllocation;
use App\Models\User;
use App\Models\UserScopeAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ScopeAuthorizationService
{
    public function hasSchoolWideVisibility(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasPermission('reports.view_school') && $user->hasRole([
            Role::SUPER_ADMIN,
            Role::PRINCIPAL,
            Role::DIRECTOR,
        ])) {
            return true;
        }

        return $user->activeScopeAssignments()
            ->where('scope_type', UserScopeAssignment::SCOPE_SCHOOL)
            ->exists();
    }

    public function applyTaskScope(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSchoolWideVisibility($user)) {
            return $query;
        }

        $scopes = $user->activeScopeAssignments()->get();
        $canViewScope = $user->hasPermission('tasks.view_scope') || $user->hasPermission('reports.view_scope');
        $canViewOwn = $user->hasPermission('tasks.view_own') || $user->hasPermission('submissions.view_own');

        if (! $canViewScope || $scopes->isEmpty()) {
            if ($canViewOwn) {
                return $query->where('document_tasks.assigned_teacher_id', $user->id);
            }

            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($user, $scopes, $canViewOwn) {
            if ($canViewOwn) {
                $outer->orWhere('document_tasks.assigned_teacher_id', $user->id);
            }
            $outer->orWhere('document_tasks.reviewer_user_id', $user->id);

            $this->applyScopeCollectionToTasks($outer, $scopes);
        });
    }

    public function applyAllocationScope(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSchoolWideVisibility($user) || $user->hasPermission('teacher_allocations.manage')) {
            return $query;
        }

        $scopes = $user->activeScopeAssignments()->get();
        if ($scopes->isEmpty()) {
            return $query->where('teaching_allocations.teacher_user_id', $user->id);
        }

        return $query->where(function (Builder $outer) use ($user, $scopes) {
            $outer->orWhere('teaching_allocations.teacher_user_id', $user->id);
            foreach ($scopes as $scope) {
                $outer->orWhere(function (Builder $sub) use ($scope) {
                    if ($scope->academic_year_id) {
                        $sub->where('teaching_allocations.academic_year_id', $scope->academic_year_id);
                    }
                    match ($scope->scope_type) {
                        UserScopeAssignment::SCOPE_SCHOOL => null,
                        UserScopeAssignment::SCOPE_STAGE => $sub->whereHas('schoolClass', fn ($q) => $q->where('stage', $scope->scope_value)),
                        UserScopeAssignment::SCOPE_CLASS => $sub->where('teaching_allocations.class_id', $scope->scope_id),
                        UserScopeAssignment::SCOPE_SECTION => $sub->where('teaching_allocations.section_id', $scope->scope_id),
                        UserScopeAssignment::SCOPE_SUBJECT => $sub->where('teaching_allocations.subject_id', $scope->scope_id),
                        UserScopeAssignment::SCOPE_TEACHER => $sub->where('teaching_allocations.teacher_user_id', $scope->scope_id),
                        default => $sub->whereRaw('1 = 0'),
                    };
                });
            }
        });
    }

    private function applyScopeCollectionToTasks(Builder $outer, Collection $scopes): void
    {
        foreach ($scopes as $scope) {
            $outer->orWhere(function (Builder $sub) use ($scope) {
                if ($scope->academic_year_id) {
                    $sub->where('document_tasks.academic_year_id', $scope->academic_year_id);
                }

                match ($scope->scope_type) {
                    UserScopeAssignment::SCOPE_SCHOOL => null,
                    UserScopeAssignment::SCOPE_STAGE => $sub->whereHas(
                        'teachingAllocation.schoolClass',
                        fn (Builder $q) => $q->where('stage', $scope->scope_value)
                    ),
                    UserScopeAssignment::SCOPE_CLASS => $sub->whereHas(
                        'teachingAllocation',
                        fn (Builder $q) => $q->where('class_id', $scope->scope_id)
                    ),
                    UserScopeAssignment::SCOPE_SECTION => $sub->whereHas(
                        'teachingAllocation',
                        fn (Builder $q) => $q->where('section_id', $scope->scope_id)
                    ),
                    UserScopeAssignment::SCOPE_SUBJECT => $sub->whereHas(
                        'teachingAllocation',
                        fn (Builder $q) => $q->where('subject_id', $scope->scope_id)
                    ),
                    UserScopeAssignment::SCOPE_TEACHER => $sub->where('document_tasks.assigned_teacher_id', $scope->scope_id),
                    default => $sub->whereRaw('1 = 0'),
                };
            });
        }
    }

    public function canViewTask(User $user, DocumentTask $task): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($task->assigned_teacher_id === $user->id && ($user->hasPermission('tasks.view_own') || $user->hasPermission('submissions.view_own'))) {
            return true;
        }

        if ($this->hasSchoolWideVisibility($user)) {
            return true;
        }

        if ($task->reviewer_user_id === $user->id && $user->hasPermission('submissions.review')) {
            return true;
        }

        if (! ($user->hasPermission('tasks.view_scope') || $user->hasPermission('reports.view_scope'))) {
            return false;
        }

        return $this->matchesUserScopes($user, $task, requireReviewFlag: false, requireRestrictedFlag: false);
    }

    public function canSubmitTask(User $user, DocumentTask $task): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($task->assigned_teacher_id !== $user->id) {
            return false;
        }

        return $user->hasPermission('submissions.create_own') || $user->hasPermission('submissions.resubmit_own');
    }

    public function canManageTaskInScope(User $user, TeachingAllocation $allocation): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (! ($user->hasPermission('tasks.create') || $user->hasPermission('tasks.assign') || $user->hasPermission('tasks.update'))) {
            return false;
        }

        if ($user->hasRole([Role::SUPER_ADMIN, Role::PRINCIPAL])) {
            return true;
        }

        $scopes = $user->activeScopeAssignments()->get();
        foreach ($scopes as $scope) {
            if ($this->allocationMatchesScope($allocation, $scope)) {
                return true;
            }
        }

        return false;
    }

    public function canReviewTask(User $user, DocumentTask $task): bool
    {
        if (! $user->is_active) {
            return false;
        }

        // Prevent self-approval of one's own teacher submission
        if ($task->assigned_teacher_id === $user->id) {
            return false;
        }

        if (! $user->hasPermission('submissions.review')) {
            return false;
        }

        if ($user->hasRole(Role::PRINCIPAL) && ($user->hasPermission('submissions.approve') || $user->hasPermission('submissions.return'))) {
            return true;
        }

        if ($user->hasRole(Role::SUPER_ADMIN) && $user->activeScopeAssignments()->where('can_review', true)->exists()) {
            return $this->matchesUserScopes($user, $task, requireReviewFlag: true, requireRestrictedFlag: false);
        }

        if ($task->reviewer_user_id === $user->id) {
            // Even when directly named, verify Headmistress/Coordinator has review authorization
            if ($user->hasRole(Role::HEADMISTRESS)) {
                return $this->matchesUserScopes($user, $task, requireReviewFlag: true, requireRestrictedFlag: false);
            }

            return true;
        }

        // Coordinator / Headmistress must match scope with can_review = true
        return $this->matchesUserScopes($user, $task, requireReviewFlag: true, requireRestrictedFlag: false);
    }

    /**
     * Enforces Section 2.3 & Section 10 file-level authorization:
     * - School-wide report access or admin configuration access does NOT automatically
     *   grant access to restricted files (especially Student Answer Sheets).
     * - Director only accesses files when explicitly authorised.
     */
    public function canAccessSubmissionFile(User $user, SubmissionVersion $version): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $submission = $version->relationLoaded('submission') ? $version->submission : $version->submission()->first();
        if (! $submission) {
            return false;
        }

        $task = $submission->relationLoaded('task') ? $submission->task : $submission->task()->with(['documentType', 'teachingAllocation.schoolClass'])->first();
        if (! $task) {
            return false;
        }

        // 1. Assigned teacher always has access to own uploaded versions
        if ($task->assigned_teacher_id === $user->id) {
            return true;
        }

        // Must at minimum have visibility of the task
        if (! $this->canViewTask($user, $task)) {
            return false;
        }

        $docType = $task->relationLoaded('documentType') ? $task->documentType : $task->documentType()->first();
        $isRestricted = (bool) ($docType?->is_restricted || in_array($docType?->sensitivity_level, ['RESTRICTED', 'STRICTLY_RESTRICTED'], true));

        // 2. Director has report/escalation visibility only; file access requires explicit grant
        if ($user->hasRole(Role::DIRECTOR) && ! $user->can_access_restricted_files) {
            return $this->matchesUserScopes($user, $task, requireReviewFlag: false, requireRestrictedFlag: true);
        }

        if (! $isRestricted) {
            return true;
        }

        // 3. Authorised scoped reviewer for this task can inspect the file to review it
        if ($this->canReviewTask($user, $task)) {
            return true;
        }

        // 4. Otherwise restricted files (Question Papers, Answer Keys, Student Answer Sheets)
        // require explicit restricted-file grant on user or scope assignment — NOT merely SUPER_ADMIN / PRINCIPAL / VIEWER report access!
        if ($user->can_access_restricted_files && $user->hasPermission('files.view_restricted')) {
            return true;
        }

        if ($user->can_access_restricted_files) {
            return true;
        }

        return $this->matchesUserScopes($user, $task, requireReviewFlag: false, requireRestrictedFlag: true);
    }

    public function matchesUserScopes(
        User $user,
        DocumentTask $task,
        bool $requireReviewFlag = false,
        bool $requireRestrictedFlag = false
    ): bool {
        $allocation = $task->relationLoaded('teachingAllocation')
            ? $task->teachingAllocation
            : $task->teachingAllocation()->with('schoolClass')->first();

        if (! $allocation) {
            return false;
        }

        $query = $user->activeScopeAssignments();
        if ($requireReviewFlag) {
            $query->where('can_review', true);
        }
        if ($requireRestrictedFlag) {
            $query->where('can_access_restricted', true);
        }

        foreach ($query->get() as $scope) {
            if ($this->allocationMatchesScope($allocation, $scope, $task->assigned_teacher_id)) {
                return true;
            }
        }

        return false;
    }

    public function allocationMatchesScope(TeachingAllocation $allocation, UserScopeAssignment $scope, ?int $assignedTeacherId = null): bool
    {
        if ($scope->academic_year_id !== null && (int) $scope->academic_year_id !== (int) $allocation->academic_year_id) {
            return false;
        }

        $teacherId = $assignedTeacherId ?? $allocation->teacher_user_id;
        $schoolClass = $allocation->relationLoaded('schoolClass') ? $allocation->schoolClass : $allocation->schoolClass()->first();

        return match ($scope->scope_type) {
            UserScopeAssignment::SCOPE_SCHOOL => true,
            UserScopeAssignment::SCOPE_STAGE => $schoolClass && $schoolClass->stage === $scope->scope_value,
            UserScopeAssignment::SCOPE_CLASS => (int) $allocation->class_id === (int) $scope->scope_id,
            UserScopeAssignment::SCOPE_SECTION => (int) $allocation->section_id === (int) $scope->scope_id,
            UserScopeAssignment::SCOPE_SUBJECT => $allocation->subject_id !== null && (int) $allocation->subject_id === (int) $scope->scope_id,
            UserScopeAssignment::SCOPE_TEACHER => (int) $teacherId === (int) $scope->scope_id,
            default => false,
        };
    }

    /**
     * Finds active users matching a target role and task scope (used for notifications & escalations).
     */
    public function resolveScopedUsersForRole(DocumentTask $task, string $roleKey): Collection
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->whereHas('primaryRole', fn ($q) => $q->where('key', $roleKey))
            ->with(['primaryRole', 'activeScopeAssignments'])
            ->get();

        if (in_array($roleKey, [Role::PRINCIPAL, Role::DIRECTOR, Role::SUPER_ADMIN], true)) {
            return $candidates->filter(function (User $u) use ($task) {
                if ($u->activeScopeAssignments->isEmpty()) {
                    return true;
                }

                return $this->matchesUserScopes($u, $task);
            })->values();
        }

        return $candidates->filter(fn (User $u) => $this->matchesUserScopes($u, $task))->values();
    }
}
