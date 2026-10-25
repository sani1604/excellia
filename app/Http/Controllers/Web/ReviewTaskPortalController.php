<?php

namespace App\Http\Controllers\Web;

use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DocumentSubmission;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAllocation;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\OverdueEscalationService;
use App\Services\ReportingService;
use App\Services\ScopeAuthorizationService;
use App\Services\TaskWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewTaskPortalController extends Controller
{
    public function __construct(
        private readonly TaskWorkflowService $workflow,
        private readonly ScopeAuthorizationService $scopeAuth,
        private readonly ReportingService $reporting,
        private readonly OverdueEscalationService $overdueService,
        private readonly GoogleDriveService $driveService,
    ) {}

    public function reviewQueue(Request $request): View
    {
        $user = $request->user();
        $currentYear = AcademicYear::current();
        $filters = $request->all();
        if (empty($filters['academic_year_id']) && $currentYear) {
            $filters['academic_year_id'] = $currentYear->id;
        }
        if (! $request->filled('status')) {
            $filters['status'] = 'PENDING_REVIEW';
        }

        $tasks = $this->reporting->buildFilteredTaskQuery($user, $filters)
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();

        return view('coordinator.review-queue', [
            'tasks' => $tasks,
            'filters' => $filters,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'sections' => Section::query()->where('is_active', true)->orderBy('name')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('display_order')->get(),
            'teachers' => User::query()->where('is_active', true)->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))->orderBy('full_name')->get(),
        ]);
    }

    public function reviewDetail(Request $request, DocumentSubmission $submission): View
    {
        $submission->load([
            'task.academicYear',
            'task.assignedTeacher',
            'task.reviewer',
            'task.documentType',
            'task.teachingAllocation.schoolClass',
            'task.teachingAllocation.section',
            'task.teachingAllocation.subject',
            'currentVersion.uploader',
            'versions.uploader',
            'versions.reviews.reviewer',
        ]);

        if (! $this->scopeAuth->canViewTask($request->user(), $submission->task)) {
            abort(403, 'You are not authorised to view this submission.');
        }

        return view('coordinator.review-detail', [
            'submission' => $submission,
            'task' => $submission->task,
            'canDecide' => $this->scopeAuth->canReviewTask($request->user(), $submission->task) && $submission->task->canReviewerDecide(),
            'canOpenCurrentFile' => $submission->currentVersion
                ? $this->scopeAuth->canAccessSubmissionFile($request->user(), $submission->currentVersion)
                : false,
            'canonicalHierarchy' => $this->driveService->buildCanonicalFolderHierarchy($submission->task),
            'scopeAuth' => $this->scopeAuth,
        ]);
    }

    public function approve(Request $request, DocumentSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflow->approveSubmission($submission, $request->user(), $validated['remarks'] ?? null);

        return redirect()
            ->route('reviews.show', $submission)
            ->with('success', 'Submission approved. Task is now closed and dashboards have been updated.');
    }

    public function returnForCorrection(Request $request, DocumentSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ], [
            'remarks.required' => 'Remarks are mandatory when returning a submission for correction.',
        ]);

        $this->workflow->returnSubmission($submission, $request->user(), $validated['remarks']);

        return redirect()
            ->route('reviews.show', $submission)
            ->with('success', 'Submission returned for correction with remarks. Teacher has been notified.');
    }

    public function overdueMonitor(Request $request): View
    {
        $currentYear = AcademicYear::current();
        $filters = $request->all();
        if (empty($filters['academic_year_id']) && $currentYear) {
            $filters['academic_year_id'] = $currentYear->id;
        }

        $report = $this->reporting->overdueEscalationReport($request->user(), $filters);

        return view('coordinator.overdue-monitor', [
            'summary' => $report['summary'],
            'overdueRows' => $report['rows'],
            'filters' => $filters,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function sendReminder(Request $request, DocumentTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $this->overdueService->sendManualReminder($task, $request->user(), $validated['message'] ?? null);

        return back()->with('success', "Reminder sent to {$task->assignedTeacher?->full_name} and logged.");
    }

    public function evaluateOverdue(Request $request): RedirectResponse
    {
        $result = $this->overdueService->processAll();

        return back()->with(
            'success',
            "Overdue & SLA check completed ({$result['timezone']}): {$result['marked_overdue']} newly overdue, {$result['escalations_triggered']} escalations, {$result['notifications_processed']} notifications logged."
        );
    }

    public function taskManagement(Request $request): View
    {
        $user = $request->user();
        $currentYear = AcademicYear::current();
        $filters = $request->all();
        if (empty($filters['academic_year_id']) && $currentYear) {
            $filters['academic_year_id'] = $currentYear->id;
        }

        $tasks = $this->reporting->buildFilteredTaskQuery($user, $filters)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $allocQuery = TeachingAllocation::query()
            ->with(['academicYear', 'schoolClass', 'section', 'subject', 'teacher'])
            ->where('is_active', true)
            ->when($currentYear, fn ($q) => $q->where('academic_year_id', $currentYear->id));
        $this->scopeAuth->applyAllocationScope($allocQuery, $user);

        $reviewers = User::query()
            ->where('is_active', true)
            ->whereHas('primaryRole', fn ($q) => $q->whereIn('key', [Role::COORDINATOR, Role::HEADMISTRESS, Role::PRINCIPAL]))
            ->orderBy('full_name')
            ->get();

        return view('coordinator.task-management', [
            'tasks' => $tasks,
            'allocations' => $allocQuery->orderBy('class_id')->orderBy('section_id')->get(),
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('display_order')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'reviewers' => $reviewers,
            'teachers' => User::query()->where('is_active', true)->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))->orderBy('full_name')->get(),
            'filters' => $filters,
            'currentYear' => $currentYear,
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teaching_allocation_id' => ['required', 'integer', 'exists:teaching_allocations,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'title' => ['required', 'string', 'max:200'],
            'period_label' => ['required', 'string', 'max:100'],
            'due_at' => ['required', 'date'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $task = $this->workflow->createTask($validated, $request->user());

        return back()->with('success', "Task \"{$task->title}\" assigned to {$task->assignedTeacher?->full_name}.");
    }

    public function generateSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'period_label' => ['required', 'string', 'max:100'],
            'due_at' => ['required', 'date'],
            'title_prefix' => ['nullable', 'string', 'max:120'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->workflow->generateRecurringTasks($validated, $request->user());

        return back()->with(
            'success',
            "Schedule generation finished idempotently: {$result['created_count']} new task(s) created, {$result['skipped_count']} duplicate/existing skipped."
        );
    }

    public function updateTask(Request $request, DocumentTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'due_at' => ['sometimes', 'date'],
            'assigned_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->workflow->updateTask($task, $validated, $request->user());

        return back()->with('success', "Task #{$task->id} updated and overdue status recalculated.");
    }

    public function cancelTask(Request $request, DocumentTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->workflow->cancelTask($task, $validated['reason'], $request->user());

        return back()->with('success', "Task #{$task->id} cancelled with audit history preserved.");
    }

    public function reopenTask(Request $request, DocumentTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->workflow->reopenApprovedTask($task, $validated['reason'], $request->user());

        return back()->with('success', "Approved task #{$task->id} reopened for correction.");
    }
}
