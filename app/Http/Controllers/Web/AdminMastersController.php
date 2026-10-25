<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditEvent;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\EscalationRule;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\TeachingAllocation;
use App\Models\User;
use App\Models\UserScopeAssignment;
use App\Services\AiAssistantService;
use App\Services\AuditService;
use App\Services\GoogleDriveService;
use App\Services\MasterDataService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AdminMastersController extends Controller
{
    public function __construct(
        private readonly MasterDataService $masterService,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
        private readonly GoogleDriveService $driveService,
        private readonly AiAssistantService $aiService,
    ) {}

    public function users(Request $request): View
    {
        $users = User::query()
            ->with(['primaryRole', 'activeScopeAssignments'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim($request->string('search')->toString());
                $q->where(fn ($sub) => $sub
                    ->where('full_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('employee_code', 'like', "%{$s}%")
                );
            })
            ->when($request->filled('role_id'), fn ($q) => $q->where('primary_role_id', $request->integer('role_id')))
            ->orderByDesc('is_active')
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'roles' => Role::query()->where('is_active', true)->orderBy('id')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'sections' => Section::query()->where('is_active', true)->orderBy('name')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8'],
            'primary_role_id' => ['required', 'integer', 'exists:roles,id'],
            'can_access_restricted_files' => ['nullable', 'boolean'],
        ]);

        $user = $this->masterService->createUser($validated, $request->user());

        return back()->with('success', "Created user {$user->full_name} ({$user->email}).");
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:191'],
            'primary_role_id' => ['required', 'integer', 'exists:roles,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8'],
            'can_access_restricted_files' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['email'])) {
            unset($validated['email']);
        }
        if (empty($validated['password'])) {
            unset($validated['password']);
        }
        $validated['can_access_restricted_files'] = $request->boolean('can_access_restricted_files');
        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $this->masterService->updateUser($user, $validated, $request->user());

        return back()->with('success', "Updated user {$user->full_name}.");
    }

    public function deactivateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $res = $this->masterService->deactivateUser($user, $validated['reason'] ?? null, $request->user());

        return back()->with(
            'success',
            "Deactivated {$user->full_name}. Flagged {$res['flagged_allocations']} active allocation(s) and {$res['open_tasks_count']} open task(s) for reassignment."
        );
    }

    public function rolesPermissions(Request $request): View
    {
        return view('admin.roles-permissions', [
            'tab' => $request->string('tab', 'scopes')->toString(),
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('id')->get(),
            'permissionsByModule' => Permission::query()->orderBy('module')->orderBy('key')->get()->groupBy('module'),
            'scopeAssignments' => UserScopeAssignment::query()
                ->with(['user.primaryRole', 'academicYear', 'assigner'])
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'users' => User::query()->with('primaryRole')->where('is_active', true)->orderBy('full_name')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'sections' => Section::query()->with('schoolClass')->where('is_active', true)->orderBy('class_id')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:48', 'alpha_dash', 'unique:roles,key'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::query()->create([
            'key' => strtoupper(str_replace('-', '_', trim($validated['key']))),
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_system_role' => false,
            'is_active' => true,
        ]);

        $this->audit->log('role.created', 'role', $role->id, null, $role->toArray(), $request->user());

        return redirect()->route('admin.roles', ['tab' => 'roles'])->with('success', "Created role {$role->name}.");
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $before = $role->toArray();
        $role->update([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $role->is_system_role ? true : $request->boolean('is_active', true),
        ]);

        $this->audit->log('role.updated', 'role', $role->id, $before, $role->fresh()->toArray(), $request->user());

        return redirect()->route('admin.roles', ['tab' => 'roles'])->with('success', "Updated role {$role->name}.");
    }

    public function destroyRole(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system_role) {
            throw new DomainRuleException('Built-in system roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            throw new DomainRuleException('Cannot delete a role that still has staff members assigned to it.');
        }

        $before = $role->toArray();
        $role->permissions()->detach();
        $role->delete();

        $this->audit->log('role.deleted', 'role', $before['id'], $before, null, $request->user());

        return redirect()->route('admin.roles', ['tab' => 'roles'])->with('success', "Deleted custom role {$before['name']}.");
    }

    public function syncRolePermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $before = $role->permissions()->pluck('permissions.key')->all();
        $role->permissions()->sync($validated['permission_ids'] ?? []);
        $after = $role->permissions()->pluck('permissions.key')->all();

        User::query()->where('primary_role_id', $role->id)->get()->each->flushPermissionCache();

        $this->audit->log(
            'role.permissions_updated',
            'role',
            $role->id,
            ['permissions' => $before],
            ['permissions' => $after],
            $request->user()
        );

        return back()->with('success', "Updated permissions for role {$role->name}.");
    }

    public function storeScopeAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'scope_type' => ['required', 'string', 'in:SCHOOL,STAGE,CLASS,SECTION,SUBJECT,TEACHER'],
            'scope_id' => ['nullable', 'integer'],
            'class_scope_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_scope_id' => ['nullable', 'integer', 'exists:sections,id'],
            'subject_scope_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'scope_value' => ['nullable', 'string', 'max:64'],
        ]);

        $resolvedScopeId = $validated['scope_id'] ?? match ($validated['scope_type']) {
            'CLASS' => $validated['class_scope_id'] ?? null,
            'SECTION' => $validated['section_scope_id'] ?? null,
            'SUBJECT' => $validated['subject_scope_id'] ?? null,
            default => null,
        };

        $scope = UserScopeAssignment::query()->create([
            'user_id' => $validated['user_id'],
            'academic_year_id' => $validated['academic_year_id'] ?? null,
            'scope_type' => $validated['scope_type'],
            'scope_id' => $resolvedScopeId,
            'scope_value' => $validated['scope_type'] === 'STAGE' ? ($validated['scope_value'] ?? null) : null,
            'can_review' => $request->boolean('can_review'),
            'can_access_restricted' => $request->boolean('can_access_restricted'),
            'is_active' => true,
            'assigned_by' => $request->user()->id,
        ]);

        $this->audit->log('scope.assigned', 'user_scope_assignment', $scope->id, null, $scope->toArray(), $request->user());

        return back()->with('success', 'Access scope mapping saved successfully.');
    }

    public function updateScopeAssignment(Request $request, UserScopeAssignment $scopeAssignment): RedirectResponse
    {
        $before = $scopeAssignment->toArray();
        $scopeAssignment->update([
            'can_review' => $request->boolean('can_review'),
            'can_access_restricted' => $request->boolean('can_access_restricted'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log('scope.updated', 'user_scope_assignment', $scopeAssignment->id, $before, $scopeAssignment->fresh()->toArray(), $request->user());

        return back()->with('success', 'Scope mapping updated.');
    }

    public function revokeScopeAssignment(Request $request, UserScopeAssignment $scopeAssignment): RedirectResponse
    {
        $before = $scopeAssignment->toArray();
        $scopeAssignment->update(['is_active' => false]);
        $this->audit->log('scope.revoked', 'user_scope_assignment', $scopeAssignment->id, $before, $scopeAssignment->fresh()->toArray(), $request->user());

        return back()->with('success', 'Scope assignment deactivated.');
    }

    public function destroyScopeAssignment(Request $request, UserScopeAssignment $scopeAssignment): RedirectResponse
    {
        $before = $scopeAssignment->toArray();
        $scopeAssignment->delete();
        $this->audit->log('scope.deleted', 'user_scope_assignment', $before['id'], $before, null, $request->user());

        return back()->with('success', 'Scope mapping deleted.');
    }

    public function academicMasters(Request $request): View
    {
        $currentYear = AcademicYear::current() ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $selectedYearId = $request->integer('academic_year_id') ?: $currentYear?->id;

        return view('admin.academics', [
            'tab' => $request->string('tab', 'allocations')->toString(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'selectedYearId' => $selectedYearId,
            'classes' => SchoolClass::query()->orderBy('display_order')->get(),
            'sections' => Section::query()
                ->with(['schoolClass', 'academicYear'])
                ->when($selectedYearId, fn ($q) => $q->where('academic_year_id', $selectedYearId))
                ->orderBy('class_id')
                ->orderBy('display_order')
                ->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'allocations' => TeachingAllocation::query()
                ->with(['academicYear', 'schoolClass', 'section', 'subject', 'teacher', 'verifier'])
                ->when($selectedYearId, fn ($q) => $q->where('academic_year_id', $selectedYearId))
                ->orderByDesc('needs_reassignment')
                ->orderBy('class_id')
                ->orderBy('section_id')
                ->paginate(25)
                ->withQueryString(),
            'teachers' => User::query()
                ->where('is_active', true)
                ->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function storeAcademicYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'string', 'in:DRAFT,ACTIVE,ARCHIVED'],
        ]);
        $validated['is_current'] = $request->boolean('is_current');

        $year = $this->masterService->saveAcademicYear($validated, $request->user());

        return back()->with('success', "Academic Year {$year->name} saved.");
    }

    public function updateAcademicYear(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:32', 'unique:academic_years,name,' . $academicYear->id],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'status' => ['required', 'string', 'in:DRAFT,ACTIVE,ARCHIVED'],
        ]);
        $validated['is_current'] = $request->has('is_current') ? $request->boolean('is_current') : $academicYear->is_current;
        if ($validated['status'] === 'ARCHIVED') {
            $validated['is_current'] = false;
        }

        $this->masterService->saveAcademicYear($validated, $request->user(), $academicYear);

        return back()->with('success', "Academic Year {$academicYear->name} updated.");
    }

    public function setCurrentYear(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->masterService->saveAcademicYear([
            'is_current' => true,
            'status' => 'ACTIVE',
        ], $request->user(), $academicYear);

        return back()->with('success', "{$academicYear->name} set as current active academic year without altering historical tasks.");
    }

    public function destroyAcademicYear(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_current) {
            throw new DomainRuleException('Cannot delete the current active academic year.');
        }
        if (Section::query()->where('academic_year_id', $academicYear->id)->exists() || TeachingAllocation::query()->where('academic_year_id', $academicYear->id)->exists()) {
            throw new DomainRuleException('Cannot delete an academic year that already has sections or teacher allocations. Archive it instead.');
        }

        $before = $academicYear->toArray();
        $academicYear->delete();
        $this->audit->log('academic_year.deleted', 'academic_year', $before['id'], $before, null, $request->user());

        return back()->with('success', "Deleted draft academic year {$before['name']}.");
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:24', 'unique:classes,code'],
            'name' => ['required', 'string', 'max:80'],
            'display_order' => ['required', 'integer', 'min:0'],
            'stage' => ['required', 'string', 'max:40'],
            'drive_folder_label' => ['nullable', 'string', 'max:120'],
        ]);

        $cls = SchoolClass::query()->create(array_merge($validated, ['is_active' => true]));
        $this->audit->log('class.created', 'school_class', $cls->id, null, $cls->toArray(), $request->user());

        return back()->with('success', "Class {$cls->name} created.");
    }

    public function updateClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:24', 'unique:classes,code,' . $schoolClass->id],
            'name' => ['required', 'string', 'max:80'],
            'display_order' => ['required', 'integer', 'min:0'],
            'stage' => ['required', 'string', 'max:40'],
            'drive_folder_label' => ['nullable', 'string', 'max:120'],
        ]);
        if (empty($validated['code'])) {
            unset($validated['code']);
        }

        $before = $schoolClass->toArray();
        $schoolClass->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));
        $this->audit->log('class.updated', 'school_class', $schoolClass->id, $before, $schoolClass->fresh()->toArray(), $request->user());

        return back()->with('success', "Class {$schoolClass->name} updated.");
    }

    public function destroyClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        if (Section::query()->where('class_id', $schoolClass->id)->exists() || TeachingAllocation::query()->where('class_id', $schoolClass->id)->exists()) {
            throw new DomainRuleException('Cannot delete a class that has sections or teacher allocations. Mark it Inactive instead.');
        }

        $before = $schoolClass->toArray();
        $schoolClass->delete();
        $this->audit->log('class.deleted', 'school_class', $before['id'], $before, null, $request->user());

        return back()->with('success', "Deleted class {$before['name']}.");
    }

    public function storeSection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'name' => ['required', 'string', 'max:32'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $sec = $this->masterService->saveSection($validated, $request->user());

        return back()->with('success', "Section {$sec->name} added.");
    }

    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32'],
            'display_order' => ['required', 'integer', 'min:0'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $this->masterService->saveSection($validated, $request->user(), $section);

        return back()->with('success', "Section {$section->name} updated.");
    }

    public function destroySection(Request $request, Section $section): RedirectResponse
    {
        if (TeachingAllocation::query()->where('section_id', $section->id)->exists()) {
            throw new DomainRuleException('Cannot delete a section that has teacher allocations. Mark it Inactive instead.');
        }

        $before = $section->toArray();
        $section->delete();
        $this->audit->log('section.deleted', 'section', $before['id'], $before, null, $request->user());

        return back()->with('success', "Deleted section {$before['name']}.");
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $sub = Subject::query()->create(array_merge($validated, ['is_active' => true]));
        $this->audit->log('subject.created', 'subject', $sub->id, null, $sub->toArray(), $request->user());

        return back()->with('success', "Subject {$sub->name} created.");
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'unique:subjects,code,' . $subject->id],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $before = $subject->toArray();
        $subject->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));
        $this->audit->log('subject.updated', 'subject', $subject->id, $before, $subject->fresh()->toArray(), $request->user());

        return back()->with('success', "Subject {$subject->name} updated.");
    }

    public function destroySubject(Request $request, Subject $subject): RedirectResponse
    {
        if (TeachingAllocation::query()->where('subject_id', $subject->id)->exists()) {
            throw new DomainRuleException('Cannot delete a subject that has teacher allocations. Mark it Inactive instead.');
        }

        $before = $subject->toArray();
        $subject->delete();
        $this->audit->log('subject.deleted', 'subject', $before['id'], $before, null, $request->user());

        return back()->with('success', "Deleted subject {$before['name']}.");
    }

    public function storeAllocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $validated['is_class_teacher'] = $request->boolean('is_class_teacher');
        $validated['is_co_class_teacher'] = $request->boolean('is_co_class_teacher');
        $validated['mark_verified'] = $request->boolean('mark_verified');

        $this->masterService->saveTeachingAllocation($validated, $request->user());

        return back()->with('success', 'Teaching allocation saved successfully.');
    }

    public function updateAllocation(Request $request, TeachingAllocation $allocation): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_user_id' => ['required', 'integer', 'exists:users,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);

        $payload = [
            'teacher_user_id' => (int) $validated['teacher_user_id'],
            'subject_id' => $request->has('subject_id') ? ($validated['subject_id'] ?? null) : $allocation->subject_id,
            'is_class_teacher' => $request->boolean('is_class_teacher'),
            'is_co_class_teacher' => $request->boolean('is_co_class_teacher'),
            'is_active' => $request->boolean('is_active', true),
            'reassign_open_tasks' => $request->boolean('reassign_open_tasks'),
        ];

        $this->masterService->saveTeachingAllocation($payload, $request->user(), $allocation);

        return back()->with('success', 'Teaching allocation updated (historical approved tasks preserved).');
    }

    public function verifyAllocation(Request $request, TeachingAllocation $allocation): RedirectResponse
    {
        $this->masterService->verifyAllocation($allocation, $request->user());

        return back()->with('success', 'Teaching allocation verified against approved timetable.');
    }

    public function destroyAllocation(Request $request, TeachingAllocation $allocation): RedirectResponse
    {
        if (DocumentTask::query()->where('teaching_allocation_id', $allocation->id)->exists()) {
            throw new DomainRuleException('Cannot delete an allocation that has academic tasks linked to it. Deactivate or reassign it instead.');
        }

        $before = $allocation->toArray();
        $allocation->delete();
        $this->audit->log('allocation.deleted', 'teaching_allocation', $before['id'], $before, null, $request->user());

        return back()->with('success', 'Unused teaching allocation deleted.');
    }

    public function documentTypes(): View
    {
        return view('admin.document-types', [
            'documentTypes' => DocumentType::query()->with('defaultReviewerRole')->orderBy('display_order')->get(),
            'roles' => Role::query()->where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function storeDocumentType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:48', 'unique:document_types,code'],
            'name' => ['required', 'string', 'max:150'],
            'folder_code' => ['required', 'string', 'max:16'],
            'folder_name' => ['required', 'string', 'max:180'],
            'extensions_csv' => ['required', 'string', 'max:200'],
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:500'],
            'sensitivity_level' => ['required', 'string', 'in:STANDARD,RESTRICTED,STRICTLY_RESTRICTED'],
            'default_due_rule' => ['nullable', 'string', 'max:64'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $exts = $this->parseValidatedExtensions($validated['extensions_csv']);

        $isRestricted = $validated['sensitivity_level'] !== 'STANDARD';
        $docType = DocumentType::query()->create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'folder_code' => trim($validated['folder_code']),
            'folder_name' => trim($validated['folder_name']),
            'allowed_extensions' => $exts,
            'max_file_size_mb' => (int) $validated['max_file_size_mb'],
            'sensitivity_level' => $validated['sensitivity_level'],
            'is_restricted' => $isRestricted,
            'access_policy_key' => $validated['sensitivity_level'] === 'STRICTLY_RESTRICTED'
                ? 'STRICTLY_RESTRICTED_ANSWER_SHEETS'
                : ($isRestricted ? 'RESTRICTED_ASSESSMENT' : 'STANDARD_ACADEMIC'),
            'default_due_rule' => $validated['default_due_rule'] ?? 'MONTHLY',
            'requires_review' => $request->boolean('requires_review', true),
            'is_active' => true,
            'display_order' => (int) ($validated['display_order'] ?? 10),
        ]);

        $this->audit->log('document_type.created', 'document_type', $docType->id, null, $docType->toArray(), $request->user());

        return back()->with('success', "Document type {$docType->name} created.");
    }

    public function updateDocumentType(Request $request, DocumentType $documentType): RedirectResponse
    {
        $validated = $request->validate([
            'folder_code' => ['nullable', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:150'],
            'folder_name' => ['required', 'string', 'max:180'],
            'extensions_csv' => ['required', 'string', 'max:200'],
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:500'],
            'sensitivity_level' => ['required', 'string', 'in:STANDARD,RESTRICTED,STRICTLY_RESTRICTED'],
            'default_due_rule' => ['nullable', 'string', 'max:64'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $exts = $this->parseValidatedExtensions($validated['extensions_csv']);
        $isRestricted = $validated['sensitivity_level'] !== 'STANDARD';

        $before = $documentType->toArray();
        $documentType->update([
            'folder_code' => ! empty($validated['folder_code']) ? trim($validated['folder_code']) : $documentType->folder_code,
            'name' => trim($validated['name']),
            'folder_name' => trim($validated['folder_name']),
            'allowed_extensions' => $exts,
            'max_file_size_mb' => (int) $validated['max_file_size_mb'],
            'sensitivity_level' => $validated['sensitivity_level'],
            'is_restricted' => $isRestricted,
            'access_policy_key' => $validated['sensitivity_level'] === 'STRICTLY_RESTRICTED'
                ? 'STRICTLY_RESTRICTED_ANSWER_SHEETS'
                : ($isRestricted ? 'RESTRICTED_ASSESSMENT' : 'STANDARD_ACADEMIC'),
            'default_due_rule' => $validated['default_due_rule'] ?? $documentType->default_due_rule,
            'requires_review' => $request->boolean('requires_review'),
            'is_active' => $request->boolean('is_active'),
            'display_order' => (int) ($validated['display_order'] ?? $documentType->display_order),
        ]);

        $this->audit->log('document_type.updated', 'document_type', $documentType->id, $before, $documentType->fresh()->toArray(), $request->user());

        return back()->with('success', "Document type {$documentType->name} updated.");
    }

    public function destroyDocumentType(Request $request, DocumentType $documentType): RedirectResponse
    {
        if (DocumentTask::query()->where('document_type_id', $documentType->id)->exists() || DriveFolderMapping::query()->where('document_type_id', $documentType->id)->exists()) {
            throw new DomainRuleException('Cannot delete a document type that has tasks or folder mappings. Mark it Inactive instead.');
        }

        $before = $documentType->toArray();
        $documentType->delete();
        $this->audit->log('document_type.deleted', 'document_type', $before['id'], $before, null, $request->user());

        return back()->with('success', "Deleted document type {$before['name']}.");
    }

    public function driveMappings(): View
    {
        $currentYear = AcademicYear::current();

        return view('admin.drive-mappings', [
            'mappings' => DriveFolderMapping::query()
                ->with(['academicYear', 'schoolClass', 'section', 'subject', 'teacher', 'documentType', 'verifier'])
                ->orderByDesc('id')
                ->paginate(25),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'currentYear' => $currentYear,
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'sections' => Section::query()->with('schoolClass')->where('is_active', true)->orderBy('class_id')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'teachers' => User::query()->where('is_active', true)->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))->orderBy('full_name')->get(),
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('display_order')->get(),
            'driveMode' => $this->driveService->activeMode(),
        ]);
    }

    public function storeDriveMapping(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'drive_folder_id' => ['required', 'string', 'max:255'],
            'drive_folder_name' => ['required', 'string', 'max:255'],
        ]);
        $validated['verify_immediately'] = $request->boolean('verify_immediately');

        $this->masterService->saveDriveMapping($validated, $request->user());

        return back()->with('success', 'Google Drive folder mapping saved.');
    }

    public function updateDriveMapping(Request $request, DriveFolderMapping $mapping): RedirectResponse
    {
        $validated = $request->validate([
            'drive_folder_id' => ['required', 'string', 'max:255'],
            'drive_folder_name' => ['required', 'string', 'max:255'],
        ]);

        $this->masterService->saveDriveMapping([
            'teacher_user_id' => $mapping->teacher_user_id,
            'document_type_id' => $mapping->document_type_id,
            'drive_folder_id' => $validated['drive_folder_id'],
            'drive_folder_name' => $validated['drive_folder_name'],
            'is_active' => $request->boolean('is_active'),
        ], $request->user(), $mapping);

        return back()->with('success', 'Google Drive folder mapping updated.');
    }

    public function destroyDriveMapping(Request $request, DriveFolderMapping $mapping): RedirectResponse
    {
        $before = $mapping->toArray();
        $mapping->delete();
        $this->audit->log('drive_mapping.deleted', 'drive_folder_mapping', $before['id'], $before, null, $request->user());

        return back()->with('success', 'Google Drive folder mapping removed.');
    }

    public function provisionDriveMappings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);

        $year = ! empty($validated['academic_year_id'])
            ? AcademicYear::query()->findOrFail((int) $validated['academic_year_id'])
            : (AcademicYear::current() ?? AcademicYear::query()->orderByDesc('start_date')->firstOrFail());

        $res = $this->masterService->provisionCanonicalDriveMappings($year, $request->user());

        return back()->with(
            'success',
            "Provisioned {$res['created_count']} missing canonical Drive folder mapping(s) for {$year->name} ({$res['skipped_count']} already mapped)."
        );
    }

    public function verifyDriveMapping(Request $request, DriveFolderMapping $mapping): RedirectResponse
    {
        $this->masterService->verifyDriveMapping($mapping, $request->user());

        return back()->with('success', "Verified & activated Drive folder mapping ({$mapping->drive_folder_id}).");
    }

    public function settings(Request $request): View
    {
        return view('admin.settings', [
            'tab' => $request->string('tab', 'general')->toString(),
            'settings' => SystemSetting::query()->orderBy('group')->orderBy('key')->get()->keyBy('key'),
            'escalationRules' => EscalationRule::query()->orderBy('threshold_value')->orderBy('id')->get(),
            'notificationRules' => NotificationRule::query()->orderBy('id')->get(),
            'roles' => Role::query()->where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $section = $request->string('settings_section', 'legacy_all')->toString();
        $actor = $request->user();

        if ($section === 'email') {
            $validated = $request->validate([
                'mail_mailer_mode' => ['required', 'string', 'in:log,smtp'],
                'mail_from_address' => ['nullable', 'email', 'max:191'],
                'mail_from_name' => ['nullable', 'string', 'max:120'],
                'mail_smtp_host' => ['nullable', 'string', 'max:191'],
                'mail_smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'mail_smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
                'mail_smtp_username' => ['nullable', 'string', 'max:191'],
                'mail_smtp_password' => ['nullable', 'string', 'max:500'],
            ]);

            SystemSetting::setValue('email_notifications_enabled', $request->boolean('email_notifications_enabled'), 'email', 'Enable or disable email reminders & notifications', $actor->id);
            SystemSetting::setValue('mail_mailer_mode', $validated['mail_mailer_mode'], 'email', 'Email transport mode', $actor->id);
            SystemSetting::setValue('mail_from_address', $validated['mail_from_address'] ?? '', 'email', 'Sender email address', $actor->id);
            SystemSetting::setValue('mail_from_name', $validated['mail_from_name'] ?? 'Excellia School Portal', 'email', 'Sender display name', $actor->id);
            SystemSetting::setValue('mail_smtp_host', $validated['mail_smtp_host'] ?? '', 'email', 'SMTP server host', $actor->id);
            SystemSetting::setValue('mail_smtp_port', (int) ($validated['mail_smtp_port'] ?? 587), 'email', 'SMTP server port', $actor->id);
            SystemSetting::setValue('mail_smtp_encryption', $validated['mail_smtp_encryption'] ?? 'tls', 'email', 'SMTP encryption', $actor->id);
            SystemSetting::setValue('mail_smtp_username', $validated['mail_smtp_username'] ?? '', 'email', 'SMTP username', $actor->id);

            if ($request->boolean('clear_mail_smtp_password')) {
                SystemSetting::clearSecret('mail_smtp_password');
            } elseif (! empty($validated['mail_smtp_password'])) {
                SystemSetting::setSecret('mail_smtp_password', $validated['mail_smtp_password'], 'email', 'Encrypted SMTP password', $actor->id);
            }

            $this->audit->log('settings.email_updated', 'system_setting', null, null, [
                'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
                'mail_mailer_mode' => $validated['mail_mailer_mode'],
                'mail_from_address' => $validated['mail_from_address'] ?? '',
            ], $actor);

            return redirect()->route('admin.settings', ['tab' => 'email'])->with('success', 'Email reminder settings saved.');
        }

        if ($section === 'whatsapp') {
            $validated = $request->validate([
                'whatsapp_provider' => ['required', 'string', 'in:whatify,meta_cloud,webhook,sandbox'],
                'whatsapp_default_country_code' => ['nullable', 'string', 'max:6'],
                'whatify_base_url' => ['nullable', 'url', 'max:255'],
                'whatify_api_key' => ['nullable', 'string', 'max:500'],
                'whatify_account_id' => ['nullable', 'integer', 'min:1'],
                'whatify_mode' => ['nullable', 'string', 'in:text,template'],
                'whatify_template_name' => ['nullable', 'string', 'max:150'],
                'whatsapp_meta_api_url' => ['nullable', 'url', 'max:255'],
                'whatsapp_phone_number_id' => ['nullable', 'string', 'max:120'],
                'whatsapp_meta_access_token' => ['nullable', 'string', 'max:1000'],
                'whatsapp_webhook_url' => ['nullable', 'url', 'max:255'],
                'whatsapp_webhook_secret' => ['nullable', 'string', 'max:500'],
            ]);

            SystemSetting::setValue('whatsapp_notifications_enabled', $request->boolean('whatsapp_notifications_enabled'), 'whatsapp', 'Enable or disable WhatsApp alerts', $actor->id);
            SystemSetting::setValue('whatsapp_provider', $validated['whatsapp_provider'], 'whatsapp', 'Active WhatsApp provider', $actor->id);
            SystemSetting::setValue('whatsapp_default_country_code', ($validated['whatsapp_default_country_code'] ?? '') ?: '91', 'whatsapp', 'Default country dialing code', $actor->id);
            SystemSetting::setValue('whatify_base_url', ($validated['whatify_base_url'] ?? '') ?: 'https://whatify.in/api/v1/external', 'whatsapp', 'Whatify External API Base URL', $actor->id);
            SystemSetting::setValue('whatify_account_id', $validated['whatify_account_id'] ?? null, 'whatsapp', 'Whatify whatsapp_account_id', $actor->id);
            SystemSetting::setValue('whatify_mode', $validated['whatify_mode'] ?? 'text', 'whatsapp', 'Whatify delivery mode (text or template)', $actor->id);
            SystemSetting::setValue('whatify_template_name', $validated['whatify_template_name'] ?? '', 'whatsapp', 'Approved Whatify template name', $actor->id);
            SystemSetting::setValue('whatsapp_meta_api_url', ($validated['whatsapp_meta_api_url'] ?? '') ?: 'https://graph.facebook.com/v21.0', 'whatsapp', 'Meta Graph API base URL', $actor->id);
            SystemSetting::setValue('whatsapp_phone_number_id', $validated['whatsapp_phone_number_id'] ?? '', 'whatsapp', 'Meta WhatsApp phone_number_id', $actor->id);
            SystemSetting::setValue('whatsapp_webhook_url', $validated['whatsapp_webhook_url'] ?? '', 'whatsapp', 'Custom WhatsApp webhook URL', $actor->id);

            if ($request->boolean('clear_whatify_api_key')) {
                SystemSetting::clearSecret('whatify_api_key');
            } elseif (! empty($validated['whatify_api_key'])) {
                SystemSetting::setSecret('whatify_api_key', $validated['whatify_api_key'], 'whatsapp', 'Encrypted Whatify X-API-Key', $actor->id);
            }

            if (! empty($validated['whatsapp_meta_access_token'])) {
                SystemSetting::setSecret('whatsapp_meta_access_token', $validated['whatsapp_meta_access_token'], 'whatsapp', 'Encrypted Meta Cloud token', $actor->id);
            }
            if (! empty($validated['whatsapp_webhook_secret'])) {
                SystemSetting::setSecret('whatsapp_webhook_secret', $validated['whatsapp_webhook_secret'], 'whatsapp', 'Encrypted Webhook secret', $actor->id);
            }

            $this->audit->log('settings.whatsapp_updated', 'system_setting', null, null, [
                'whatsapp_notifications_enabled' => $request->boolean('whatsapp_notifications_enabled'),
                'whatsapp_provider' => $validated['whatsapp_provider'],
                'whatify_mode' => $validated['whatify_mode'] ?? 'text',
                'whatify_key_configured' => SystemSetting::hasSecret('whatify_api_key', config('excellia.whatsapp.whatify_api_key')),
            ], $actor);

            return redirect()->route('admin.settings', ['tab' => 'whatsapp'])->with('success', 'WhatsApp & Whatify API settings saved.');
        }

        if ($section === 'google') {
            $validated = $request->validate([
                'google_drive_root_folder_id' => ['nullable', 'string', 'max:255'],
                'google_drive_root_folder_name' => ['nullable', 'string', 'max:255'],
                'google_drive_client_id' => ['nullable', 'string', 'max:255'],
                'google_drive_client_secret' => ['nullable', 'string', 'max:500'],
                'google_drive_refresh_token' => ['nullable', 'string', 'max:1000'],
                'google_service_account_email' => ['nullable', 'email', 'max:255'],
                'google_service_account_private_key' => ['nullable', 'string', 'max:8000'],
                'google_impersonate_email' => ['nullable', 'email', 'max:255'],
                'google_sso_hosted_domain' => ['nullable', 'string', 'max:120'],
                'google_sso_client_id' => ['nullable', 'string', 'max:255'],
                'google_sso_client_secret' => ['nullable', 'string', 'max:500'],
            ]);

            SystemSetting::setValue('google_drive_live_enabled', $request->boolean('google_drive_live_enabled'), 'google', 'Enable live Google Drive API v3 vs local vault', $actor->id);
            SystemSetting::setValue('google_drive_root_folder_id', ($validated['google_drive_root_folder_id'] ?? '') ?: '1ExcelliaRoot2026_27AcademicSharing', 'google', 'Root Academic Sharing Drive Folder ID', $actor->id);
            SystemSetting::setValue('google_drive_root_folder_name', ($validated['google_drive_root_folder_name'] ?? '') ?: 'EXCELLIA SCHOOL – ACADEMIC SHARING – 2026–27', 'google', 'Root Drive Folder Name', $actor->id);
            SystemSetting::setValue('google_drive_client_id', $validated['google_drive_client_id'] ?? '', 'google', 'Google Drive OAuth Client ID', $actor->id);
            SystemSetting::setValue('google_service_account_email', $validated['google_service_account_email'] ?? '', 'google', 'Google Service Account Email', $actor->id);
            SystemSetting::setValue('google_impersonate_email', $validated['google_impersonate_email'] ?? '', 'google', 'Google Workspace Admin Impersonation Email', $actor->id);

            SystemSetting::setValue('google_sso_enabled', $request->boolean('google_sso_enabled'), 'google', 'Enable Google Workspace SSO button on login', $actor->id);
            SystemSetting::setValue('google_sso_hosted_domain', $validated['google_sso_hosted_domain'] ?? 'excelliaschool.edu.in', 'google', 'Allowed Google Workspace domain', $actor->id);
            SystemSetting::setValue('google_sso_client_id', $validated['google_sso_client_id'] ?? '', 'google', 'Google SSO Client ID', $actor->id);

            if (! empty($validated['google_drive_client_secret'])) {
                SystemSetting::setSecret('google_drive_client_secret', $validated['google_drive_client_secret'], 'google', 'Encrypted Drive Client Secret', $actor->id);
            }
            if (! empty($validated['google_drive_refresh_token'])) {
                SystemSetting::setSecret('google_drive_refresh_token', $validated['google_drive_refresh_token'], 'google', 'Encrypted Drive Refresh Token', $actor->id);
            }
            if (! empty($validated['google_service_account_private_key'])) {
                SystemSetting::setSecret('google_service_account_private_key', $validated['google_service_account_private_key'], 'google', 'Encrypted Service Account Private Key', $actor->id);
            }
            if (! empty($validated['google_sso_client_secret'])) {
                SystemSetting::setSecret('google_sso_client_secret', $validated['google_sso_client_secret'], 'google', 'Encrypted Google SSO Client Secret', $actor->id);
            }

            $this->audit->log('settings.google_updated', 'system_setting', null, null, [
                'google_drive_live_enabled' => $request->boolean('google_drive_live_enabled'),
                'google_sso_enabled' => $request->boolean('google_sso_enabled'),
                'google_sso_hosted_domain' => $validated['google_sso_hosted_domain'] ?? 'excelliaschool.edu.in',
            ], $actor);

            return redirect()->route('admin.settings', ['tab' => 'google'])->with('success', 'Google Drive & Google Workspace SSO settings saved.');
        }

        if ($section === 'ai') {
            $validated = $request->validate([
                'openrouter_base_url' => ['nullable', 'url', 'max:255'],
                'openrouter_api_key' => ['nullable', 'string', 'max:500'],
                'openrouter_model' => ['required', 'string', 'max:160'],
            ]);

            SystemSetting::setValue('ai_openrouter_enabled', $request->boolean('ai_openrouter_enabled'), 'ai', 'Enable or disable AI Assistant globally', $actor->id);
            SystemSetting::setValue('ai_allow_teachers', $request->boolean('ai_allow_teachers'), 'ai', 'Allow Teachers to use AI outline & note helper', $actor->id);
            SystemSetting::setValue('ai_allow_coordinators', $request->boolean('ai_allow_coordinators'), 'ai', 'Allow Coordinators & Leaders to use AI instruction & review helper', $actor->id);
            SystemSetting::setValue('openrouter_base_url', ($validated['openrouter_base_url'] ?? '') ?: 'https://openrouter.ai/api/v1', 'ai', 'OpenRouter API Base URL', $actor->id);
            SystemSetting::setValue('openrouter_model', trim($validated['openrouter_model']), 'ai', 'Selected OpenRouter model slug', $actor->id);

            if ($request->boolean('clear_openrouter_api_key')) {
                SystemSetting::clearSecret('openrouter_api_key');
            } elseif (! empty($validated['openrouter_api_key'])) {
                SystemSetting::setSecret('openrouter_api_key', $validated['openrouter_api_key'], 'ai', 'Encrypted OpenRouter API Key', $actor->id);
            }

            $this->audit->log('settings.ai_updated', 'system_setting', null, null, [
                'ai_openrouter_enabled' => $request->boolean('ai_openrouter_enabled'),
                'ai_allow_teachers' => $request->boolean('ai_allow_teachers'),
                'ai_allow_coordinators' => $request->boolean('ai_allow_coordinators'),
                'openrouter_model' => trim($validated['openrouter_model']),
                'key_configured' => SystemSetting::hasSecret('openrouter_api_key', config('excellia.ai.api_key')),
            ], $actor);

            return redirect()->route('admin.settings', ['tab' => 'ai'])->with('success', 'OpenRouter AI Assistant settings saved.');
        }

        // General / Legacy combined settings update
        $validated = $request->validate([
            'school_timezone' => ['required', 'string', 'timezone'],
            'review_sla_hours' => ['required', 'integer', 'min:4', 'max:336'],
            'holidays_csv' => ['nullable', 'string', 'max:2000'],
            'submission_modes' => ['nullable', 'array'],
            'submission_modes.*' => ['string', 'in:PORTAL_UPLOAD,DRIVE_LINK'],
        ]);

        $modes = ! empty($validated['submission_modes']) ? array_values($validated['submission_modes']) : ['PORTAL_UPLOAD'];

        SystemSetting::setValue('school_timezone', $validated['school_timezone'], 'workflow', 'Configured school timezone for deadlines & overdue calculations', $actor->id);
        SystemSetting::setValue('review_sla_hours', (int) $validated['review_sla_hours'], 'workflow', 'Reviewer turnaround SLA in hours', $actor->id);
        SystemSetting::setValue('submission_modes', $modes, 'workflow', 'Permitted teacher submission methods', $actor->id);
        SystemSetting::setValue('allow_co_class_teachers', $request->boolean('allow_co_class_teachers'), 'academics', 'Allow multiple co-class-teachers per section', $actor->id);
        SystemSetting::setValue('exclude_holidays_from_overdue', $request->boolean('exclude_holidays_from_overdue'), 'workflow', 'Exclude configured holiday dates from overdue day counts', $actor->id);

        if ($section === 'legacy_all') {
            SystemSetting::setValue('email_notifications_enabled', $request->boolean('email_notifications_enabled'), 'notifications', 'Enable outbound email channel alongside in-app alerts', $actor->id);
            SystemSetting::setValue('whatsapp_notifications_enabled', $request->boolean('whatsapp_notifications_enabled'), 'notifications', 'Enable outbound WhatsApp channel alongside in-app alerts', $actor->id);
        }

        $holidays = array_values(array_filter(array_map('trim', explode(',', (string) ($validated['holidays_csv'] ?? '')))));
        SystemSetting::setValue('holidays_dates', $holidays, 'workflow', 'Configured school holiday dates (YYYY-MM-DD)', $actor->id);

        $this->audit->log('settings.updated', 'system_setting', null, null, [
            'school_timezone' => $validated['school_timezone'],
            'review_sla_hours' => (int) $validated['review_sla_hours'],
            'submission_modes' => $modes,
            'allow_co_class_teachers' => $request->boolean('allow_co_class_teachers'),
            'exclude_holidays_from_overdue' => $request->boolean('exclude_holidays_from_overdue'),
        ], $actor);

        return redirect()->route('admin.settings', ['tab' => 'general'])->with('success', 'General school settings saved.');
    }

    public function testIntegration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'in:email,whatify_ping,whatsapp_send,google_drive,openrouter_ai'],
            'test_email' => ['nullable', 'email', 'max:191'],
            'test_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $target = $validated['target'];
        $tabMap = [
            'email' => 'email',
            'whatify_ping' => 'whatsapp',
            'whatsapp_send' => 'whatsapp',
            'google_drive' => 'google',
            'openrouter_ai' => 'ai',
        ];
        $tab = $tabMap[$target] ?? 'general';

        try {
            if ($target === 'email') {
                $to = $validated['test_email'] ?: $request->user()->email;
                $this->notifications->sendConfiguredEmail(
                    $to,
                    $request->user()->full_name,
                    'Excellia School Portal — Test Email',
                    'This is a test email from Excellia School Academic Portal confirming your email settings are working.'
                );

                return redirect()->route('admin.settings', ['tab' => $tab])->with('success', "Test email sent successfully to {$to}.");
            }

            if ($target === 'whatify_ping') {
                $info = $this->notifications->verifyWhatifyConnection();
                $walletSuffix = ! empty($info['wallet']) ? " · Wallet Balance: {$info['wallet']}" : '';

                return redirect()->route('admin.settings', ['tab' => $tab])->with('success', "Whatify API connected successfully (status: {$info['status']}{$walletSuffix}).");
            }

            if ($target === 'whatsapp_send') {
                $phone = $validated['test_phone'] ?: ($request->user()->phone ?? '');
                $msgId = $this->notifications->sendDirectWhatsAppTest(
                    $phone,
                    $request->user()->full_name,
                    'Test WhatsApp Alert',
                    'Your Excellia School WhatsApp notification channel is connected and working.'
                );

                return redirect()->route('admin.settings', ['tab' => $tab])->with('success', "Test WhatsApp message dispatched successfully (Ref: {$msgId}).");
            }

            if ($target === 'google_drive') {
                $res = $this->driveService->verifyDriveConnection();

                return redirect()->route('admin.settings', ['tab' => $tab])->with('success', $res['message']);
            }

            if ($target === 'openrouter_ai') {
                $res = $this->aiService->testOpenRouterConnection();

                return redirect()->route('admin.settings', ['tab' => $tab])->with('success', "OpenRouter AI ({$res['model']}) replied: \"{$res['reply']}\"");
            }
        } catch (Throwable $e) {
            return redirect()->route('admin.settings', ['tab' => $tab])->with('error', $e->getMessage());
        }

        return redirect()->route('admin.settings', ['tab' => $tab]);
    }

    public function updateNotificationRule(Request $request, NotificationRule $notificationRule): RedirectResponse
    {
        $validated = $request->validate([
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:IN_APP,EMAIL,WHATSAPP'],
        ]);

        $before = $notificationRule->toArray();
        $channels = ! empty($validated['channels']) ? array_values(array_unique($validated['channels'])) : ['IN_APP'];
        $notificationRule->update([
            'channels' => $channels,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log('notification_rule.updated', 'notification_rule', $notificationRule->id, $before, $notificationRule->fresh()->toArray(), $request->user());

        return redirect()->route('admin.settings', ['tab' => 'rules'])->with('success', "Updated notification rule {$notificationRule->name}.");
    }

    public function storeEscalationRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trigger_type' => ['required', 'string', 'in:DUE_SOON,ON_DUE_DATE,OVERDUE_DAYS,REVIEW_SLA_HOURS'],
            'threshold_value' => ['required', 'integer', 'min:-30', 'max:720'],
            'escalation_level' => ['required', 'integer', 'min:0', 'max:5'],
            'target_role_key' => ['required', 'string', 'exists:roles,key'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:IN_APP,EMAIL,WHATSAPP'],
        ]);

        $rule = EscalationRule::query()->create([
            'name' => trim($validated['name']),
            'trigger_type' => $validated['trigger_type'],
            'threshold_value' => (int) $validated['threshold_value'],
            'escalation_level' => (int) $validated['escalation_level'],
            'target_role_key' => $validated['target_role_key'],
            'channels' => ! empty($validated['channels']) ? array_values(array_unique($validated['channels'])) : ['IN_APP'],
            'skip_weekends' => $request->boolean('skip_weekends'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log('escalation_rule.created', 'escalation_rule', $rule->id, null, $rule->toArray(), $request->user());

        return redirect()->route('admin.settings', ['tab' => 'rules'])->with('success', "Added escalation rule '{$rule->name}'.");
    }

    public function updateEscalationRule(Request $request, EscalationRule $escalationRule): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'threshold_value' => ['required', 'integer', 'min:-30', 'max:720'],
            'escalation_level' => ['required', 'integer', 'min:0', 'max:5'],
            'target_role_key' => ['required', 'string', 'exists:roles,key'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:IN_APP,EMAIL,WHATSAPP'],
        ]);

        $before = $escalationRule->toArray();
        $escalationRule->update([
            'name' => trim($validated['name']),
            'threshold_value' => (int) $validated['threshold_value'],
            'escalation_level' => (int) $validated['escalation_level'],
            'target_role_key' => $validated['target_role_key'],
            'channels' => ! empty($validated['channels']) ? array_values(array_unique($validated['channels'])) : ['IN_APP'],
            'skip_weekends' => $request->boolean('skip_weekends'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log('escalation_rule.updated', 'escalation_rule', $escalationRule->id, $before, $escalationRule->fresh()->toArray(), $request->user());

        return redirect()->route('admin.settings', ['tab' => 'rules'])->with('success', "Updated escalation rule '{$escalationRule->name}'.");
    }

    public function destroyEscalationRule(Request $request, EscalationRule $escalationRule): RedirectResponse
    {
        $before = $escalationRule->toArray();
        $escalationRule->delete();
        $this->audit->log('escalation_rule.deleted', 'escalation_rule', $before['id'], $before, null, $request->user());

        return redirect()->route('admin.settings', ['tab' => 'rules'])->with('success', "Deleted escalation rule '{$before['name']}'.");
    }

    public function auditLogs(Request $request): View
    {
        $events = AuditEvent::query()
            ->with('actor.primaryRole')
            ->when($request->filled('action_key'), fn ($q) => $q->where('action_key', 'like', '%' . $request->string('action_key')->toString() . '%'))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')->toString()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs', [
            'events' => $events,
        ]);
    }

    public function notifications(Request $request): View
    {
        $logs = NotificationLog::query()
            ->where('recipient_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('notifications.index', [
            'notifications' => $logs,
        ]);
    }

    public function markNotificationRead(Request $request, NotificationLog $notification): RedirectResponse
    {
        if ((int) $notification->recipient_user_id !== (int) $request->user()->id) {
            abort(403, 'You can only mark your own notifications as read.');
        }

        $notification->update(['read_at' => now()]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        NotificationLog::query()
            ->where('recipient_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    private function parseValidatedExtensions(string $csv): array
    {
        $exts = array_values(array_filter(array_map(
            fn ($e) => ltrim(mb_strtolower(trim($e)), '.'),
            explode(',', $csv)
        )));

        if (empty($exts)) {
            throw new DomainRuleException('At least one allowed file extension is required.');
        }

        foreach ($exts as $ext) {
            if (in_array($ext, config('excellia.security.blocked_extensions', []), true)) {
                throw new DomainRuleException("Extension .{$ext} is prohibited by security policy.");
            }
        }

        return $exts;
    }
}
