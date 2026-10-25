<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAllocation;
use App\Models\UserScopeAssignment;
use App\Services\AuditService;
use App\Services\MasterDataService;
use App\Services\ScopeAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterApiController extends Controller
{
    public function __construct(
        private readonly MasterDataService $masterService,
        private readonly ScopeAuthorizationService $scopeAuth,
        private readonly AuditService $audit,
    ) {}

    public function roles(): JsonResponse
    {
        return response()->json([
            'data' => Role::query()->with('permissions')->orderBy('id')->get(),
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => Permission::query()->orderBy('module')->orderBy('key')->get(),
        ]);
    }

    public function syncRolePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $before = $role->permissions()->pluck('permissions.key')->all();
        $role->permissions()->sync($validated['permission_ids']);
        $after = $role->permissions()->pluck('permissions.key')->all();

        $this->audit->log(
            'role.permissions_updated',
            'role',
            $role->id,
            ['permissions' => $before],
            ['permissions' => $after],
            $request->user()
        );

        return response()->json(['data' => $role->load('permissions')]);
    }

    public function storeScopeAssignment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'scope_type' => ['required', 'string', 'in:SCHOOL,STAGE,CLASS,SECTION,SUBJECT,TEACHER'],
            'scope_id' => ['nullable', 'integer'],
            'scope_value' => ['nullable', 'string', 'max:64'],
            'can_review' => ['nullable', 'boolean'],
            'can_access_restricted' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $assignment = UserScopeAssignment::query()->create(array_merge($validated, [
            'can_review' => (bool) ($validated['can_review'] ?? false),
            'can_access_restricted' => (bool) ($validated['can_access_restricted'] ?? false),
            'is_active' => true,
            'assigned_by' => $request->user()->id,
        ]));

        $this->audit->log('scope.assigned', 'user_scope_assignment', $assignment->id, null, $assignment->toArray(), $request->user());

        return response()->json(['data' => $assignment], 201);
    }

    public function academicYears(): JsonResponse
    {
        return response()->json([
            'data' => AcademicYear::query()->orderByDesc('start_date')->get(),
        ]);
    }

    public function storeAcademicYear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:DRAFT,ACTIVE,ARCHIVED'],
        ]);

        $year = $this->masterService->saveAcademicYear($validated, $request->user());

        return response()->json(['data' => $year], 201);
    }

    public function updateAcademicYear(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:32', 'unique:academic_years,name,' . $academicYear->id],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:DRAFT,ACTIVE,ARCHIVED'],
        ]);

        $year = $this->masterService->saveAcademicYear($validated, $request->user(), $academicYear);

        return response()->json(['data' => $year]);
    }

    public function classes(): JsonResponse
    {
        return response()->json([
            'data' => SchoolClass::query()->orderBy('display_order')->get(),
        ]);
    }

    public function storeClass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:24', 'unique:classes,code'],
            'name' => ['required', 'string', 'max:80'],
            'display_order' => ['required', 'integer', 'min:0'],
            'stage' => ['nullable', 'string', 'max:40'],
            'drive_folder_label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cls = SchoolClass::query()->create(array_merge($validated, [
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]));

        $this->audit->log('class.created', 'school_class', $cls->id, null, $cls->toArray(), $request->user());

        return response()->json(['data' => $cls], 201);
    }

    public function updateClass(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'stage' => ['nullable', 'string', 'max:40'],
            'drive_folder_label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $schoolClass->toArray();
        $schoolClass->update($validated);
        $this->audit->log('class.updated', 'school_class', $schoolClass->id, $before, $schoolClass->fresh()->toArray(), $request->user());

        return response()->json(['data' => $schoolClass->fresh()]);
    }

    public function sections(Request $request): JsonResponse
    {
        $sections = Section::query()
            ->with(['academicYear', 'schoolClass'])
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
            ->orderBy('class_id')
            ->orderBy('display_order')
            ->get();

        return response()->json(['data' => $sections]);
    }

    public function storeSection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'name' => ['required', 'string', 'max:32'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $section = $this->masterService->saveSection($validated, $request->user());

        return response()->json(['data' => $section->load(['academicYear', 'schoolClass'])], 201);
    }

    public function updateSection(Request $request, Section $section): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:32'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->masterService->saveSection($validated, $request->user(), $section);

        return response()->json(['data' => $updated]);
    }

    public function subjects(): JsonResponse
    {
        return response()->json([
            'data' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $subject = Subject::query()->create(array_merge($validated, [
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]));

        $this->audit->log('subject.created', 'subject', $subject->id, null, $subject->toArray(), $request->user());

        return response()->json(['data' => $subject], 201);
    }

    public function updateSubject(Request $request, Subject $subject): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'unique:subjects,code,' . $subject->id],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $subject->toArray();
        $subject->update($validated);
        $this->audit->log('subject.updated', 'subject', $subject->id, $before, $subject->fresh()->toArray(), $request->user());

        return response()->json(['data' => $subject->fresh()]);
    }

    public function teachingAllocations(Request $request): JsonResponse
    {
        $query = TeachingAllocation::query()
            ->with(['academicYear', 'schoolClass', 'section', 'subject', 'teacher', 'verifier'])
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->when($request->filled('teacher_user_id'), fn ($q) => $q->where('teacher_user_id', $request->integer('teacher_user_id')));

        $this->scopeAuth->applyAllocationScope($query, $request->user());

        return response()->json([
            'data' => $query->orderBy('class_id')->orderBy('section_id')->paginate((int) $request->integer('per_page', 50)),
        ]);
    }

    public function storeTeachingAllocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_user_id' => ['required', 'integer', 'exists:users,id'],
            'is_class_teacher' => ['nullable', 'boolean'],
            'is_co_class_teacher' => ['nullable', 'boolean'],
            'allocation_start_date' => ['nullable', 'date'],
            'allocation_end_date' => ['nullable', 'date', 'after_or_equal:allocation_start_date'],
            'is_active' => ['nullable', 'boolean'],
            'mark_verified' => ['nullable', 'boolean'],
        ]);

        $allocation = $this->masterService->saveTeachingAllocation($validated, $request->user());

        return response()->json(['data' => $allocation], 201);
    }

    public function updateTeachingAllocation(Request $request, TeachingAllocation $teachingAllocation): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'is_class_teacher' => ['sometimes', 'boolean'],
            'is_co_class_teacher' => ['sometimes', 'boolean'],
            'allocation_start_date' => ['nullable', 'date'],
            'allocation_end_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'reassign_open_tasks' => ['nullable', 'boolean'],
        ]);

        $updated = $this->masterService->saveTeachingAllocation($validated, $request->user(), $teachingAllocation);

        return response()->json(['data' => $updated]);
    }

    public function verifyTeachingAllocation(Request $request, TeachingAllocation $teachingAllocation): JsonResponse
    {
        $verified = $this->masterService->verifyAllocation($teachingAllocation, $request->user());

        return response()->json(['data' => $verified]);
    }

    public function documentTypes(): JsonResponse
    {
        return response()->json([
            'data' => DocumentType::query()->orderBy('display_order')->get(),
        ]);
    }

    public function storeDocumentType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:48', 'unique:document_types,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'folder_code' => ['required', 'string', 'max:16'],
            'folder_name' => ['required', 'string', 'max:180'],
            'allowed_extensions' => ['required', 'array', 'min:1'],
            'allowed_extensions.*' => ['string', 'max:12'],
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:500'],
            'sensitivity_level' => ['required', 'string', 'in:STANDARD,RESTRICTED,STRICTLY_RESTRICTED'],
            'is_restricted' => ['nullable', 'boolean'],
            'access_policy_key' => ['nullable', 'string', 'max:64'],
            'default_reviewer_role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'default_due_rule' => ['nullable', 'string', 'max:64'],
            'requires_review' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($validated['allowed_extensions'] as $ext) {
            if (in_array(mb_strtolower(ltrim($ext, '.')), config('excellia.security.blocked_extensions', []), true)) {
                throw new DomainRuleException("Extension .{$ext} is prohibited and cannot be added to a document type.");
            }
        }

        $isRestricted = (bool) ($validated['is_restricted'] ?? ($validated['sensitivity_level'] !== 'STANDARD'));
        $docType = DocumentType::query()->create(array_merge($validated, [
            'is_restricted' => $isRestricted,
            'access_policy_key' => $validated['access_policy_key'] ?? ($isRestricted ? 'RESTRICTED_ASSESSMENT' : 'STANDARD_ACADEMIC'),
            'requires_review' => (bool) ($validated['requires_review'] ?? true),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'display_order' => (int) ($validated['display_order'] ?? 10),
        ]));

        $this->audit->log('document_type.created', 'document_type', $docType->id, null, $docType->toArray(), $request->user());

        return response()->json(['data' => $docType], 201);
    }

    public function updateDocumentType(Request $request, DocumentType $documentType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'folder_name' => ['sometimes', 'string', 'max:180'],
            'allowed_extensions' => ['sometimes', 'array', 'min:1'],
            'allowed_extensions.*' => ['string', 'max:12'],
            'max_file_size_mb' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'sensitivity_level' => ['sometimes', 'string', 'in:STANDARD,RESTRICTED,STRICTLY_RESTRICTED'],
            'is_restricted' => ['sometimes', 'boolean'],
            'access_policy_key' => ['sometimes', 'string', 'max:64'],
            'default_due_rule' => ['nullable', 'string', 'max:64'],
            'requires_review' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $before = $documentType->toArray();
        $documentType->update($validated);
        $this->audit->log('document_type.updated', 'document_type', $documentType->id, $before, $documentType->fresh()->toArray(), $request->user());

        return response()->json(['data' => $documentType->fresh()]);
    }

    public function driveMappings(Request $request): JsonResponse
    {
        $mappings = DriveFolderMapping::query()
            ->with(['academicYear', 'schoolClass', 'section', 'subject', 'teacher', 'documentType', 'verifier'])
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json($mappings);
    }

    public function storeDriveMapping(Request $request): JsonResponse
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
            'access_policy_key' => ['nullable', 'string', 'max:64'],
            'verify_immediately' => ['nullable', 'boolean'],
        ]);

        $mapping = $this->masterService->saveDriveMapping($validated, $request->user());

        return response()->json(['data' => $mapping], 201);
    }

    public function updateDriveMapping(Request $request, DriveFolderMapping $driveFolderMapping): JsonResponse
    {
        $validated = $request->validate([
            'drive_folder_id' => ['sometimes', 'string', 'max:255'],
            'drive_folder_name' => ['sometimes', 'string', 'max:255'],
            'access_policy_key' => ['sometimes', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $mapping = $this->masterService->saveDriveMapping($validated, $request->user(), $driveFolderMapping);

        return response()->json(['data' => $mapping]);
    }

    public function verifyDriveMapping(Request $request, DriveFolderMapping $driveFolderMapping): JsonResponse
    {
        $verified = $this->masterService->verifyDriveMapping($driveFolderMapping, $request->user());

        return response()->json(['data' => $verified]);
    }

    public function provisionDriveMappings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);

        $year = ! empty($validated['academic_year_id'])
            ? AcademicYear::query()->findOrFail((int) $validated['academic_year_id'])
            : (AcademicYear::current() ?? AcademicYear::query()->orderByDesc('start_date')->firstOrFail());

        $res = $this->masterService->provisionCanonicalDriveMappings($year, $request->user());

        return response()->json([
            'message' => 'Provisioned canonical Drive folder mappings.',
            'data' => $res,
        ]);
    }
}
