<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditEvent;
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
use App\Services\AuditService;
use App\Services\MasterDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMastersController extends Controller
{
    public function __construct(
        private readonly MasterDataService $masterService,
        private readonly AuditService $audit,
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
            'primary_role_id' => ['required', 'integer', 'exists:roles,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'can_access_restricted_files' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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

    public function rolesPermissions(): View
    {
        return view('admin.roles-permissions', [
            'roles' => Role::query()->with('permissions')->orderBy('id')->get(),
            'permissionsByModule' => Permission::query()->orderBy('module')->orderBy('key')->get()->groupBy('module'),
            'scopeAssignments' => UserScopeAssignment::query()
                ->with(['user.primaryRole', 'academicYear', 'assigner'])
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'users' => User::query()->with('primaryRole')->where('is_active', true)->orderBy('full_name')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'sections' => Section::query()->with('schoolClass')->where('is_active', true)->orderBy('class_id')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
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

        return back()->with('success', 'Access scope saved successfully.');
    }

    public function revokeScopeAssignment(Request $request, UserScopeAssignment $scopeAssignment): RedirectResponse
    {
        $before = $scopeAssignment->toArray();
        $scopeAssignment->update(['is_active' => false]);
        $this->audit->log('scope.revoked', 'user_scope_assignment', $scopeAssignment->id, $before, $scopeAssignment->fresh()->toArray(), $request->user());

        return back()->with('success', 'Scope assignment deactivated.');
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
            'name' => ['required', 'string', 'max:80'],
            'display_order' => ['required', 'integer', 'min:0'],
            'stage' => ['required', 'string', 'max:40'],
            'drive_folder_label' => ['nullable', 'string', 'max:120'],
        ]);

        $before = $schoolClass->toArray();
        $schoolClass->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));
        $this->audit->log('class.updated', 'school_class', $schoolClass->id, $before, $schoolClass->fresh()->toArray(), $request->user());

        return back()->with('success', "Class {$schoolClass->name} updated.");
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

    public function settings(): View
    {
        return view('admin.settings', [
            'settings' => SystemSetting::query()->orderBy('group')->orderBy('key')->get()->keyBy('key'),
            'escalationRules' => EscalationRule::query()->orderBy('threshold_value')->orderBy('id')->get(),
            'notificationRules' => NotificationRule::query()->orderBy('id')->get(),
            'roles' => Role::query()->where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_timezone' => ['required', 'string', 'timezone'],
            'review_sla_hours' => ['required', 'integer', 'min:4', 'max:336'],
            'holidays_csv' => ['nullable', 'string', 'max:2000'],
            'submission_modes' => ['nullable', 'array'],
            'submission_modes.*' => ['string', 'in:PORTAL_UPLOAD,DRIVE_LINK'],
        ]);

        $actor = $request->user();
        $modes = ! empty($validated['submission_modes']) ? array_values($validated['submission_modes']) : ['PORTAL_UPLOAD'];

        SystemSetting::setValue('school_timezone', $validated['school_timezone'], 'workflow', 'Configured school timezone for deadlines & overdue calculations', $actor->id);
        SystemSetting::setValue('review_sla_hours', (int) $validated['review_sla_hours'], 'workflow', 'Reviewer turnaround SLA in hours', $actor->id);
        SystemSetting::setValue('submission_modes', $modes, 'workflow', 'Permitted teacher submission methods', $actor->id);
        SystemSetting::setValue('allow_co_class_teachers', $request->boolean('allow_co_class_teachers'), 'academics', 'Allow multiple co-class-teachers per section', $actor->id);
        SystemSetting::setValue('exclude_holidays_from_overdue', $request->boolean('exclude_holidays_from_overdue'), 'workflow', 'Exclude configured holiday dates from overdue day counts', $actor->id);
        SystemSetting::setValue('email_notifications_enabled', $request->boolean('email_notifications_enabled'), 'notifications', 'Enable outbound email channel alongside in-app alerts', $actor->id);
        SystemSetting::setValue('whatsapp_notifications_enabled', $request->boolean('whatsapp_notifications_enabled'), 'notifications', 'Enable outbound WhatsApp channel alongside in-app alerts', $actor->id);

        $holidays = array_values(array_filter(array_map('trim', explode(',', (string) ($validated['holidays_csv'] ?? '')))));
        SystemSetting::setValue('holidays_dates', $holidays, 'workflow', 'Configured school holiday dates (YYYY-MM-DD)', $actor->id);

        $this->audit->log('settings.updated', 'system_setting', null, null, [
            'school_timezone' => $validated['school_timezone'],
            'review_sla_hours' => (int) $validated['review_sla_hours'],
            'submission_modes' => $modes,
            'allow_co_class_teachers' => $request->boolean('allow_co_class_teachers'),
            'exclude_holidays_from_overdue' => $request->boolean('exclude_holidays_from_overdue'),
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
            'whatsapp_notifications_enabled' => $request->boolean('whatsapp_notifications_enabled'),
        ], $actor);

        return back()->with('success', 'System settings and notification channels updated.');
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

        return back()->with('success', "Updated notification rule {$notificationRule->name}.");
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

        return back()->with('success', "Added escalation rule '{$rule->name}'.");
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

        return back()->with('success', "Updated escalation rule '{$escalationRule->name}'.");
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
