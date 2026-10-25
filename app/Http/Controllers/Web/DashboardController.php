<?php

namespace App\Http\Controllers\Web;

use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DocumentTask;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Services\ManagementMisService;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly ManagementMisService $managementMis,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing(['primaryRole', 'activeScopeAssignments']);
        $currentYear = AcademicYear::current() ?? AcademicYear::query()->orderByDesc('id')->first();
        $yearId = $request->integer('academic_year_id') ?: ($currentYear?->id ?? null);

        $dashboardData = $this->reporting->dashboardMetrics($user, [
            'academic_year_id' => $yearId,
        ]);

        $recentNotifications = NotificationLog::query()
            ->where('recipient_user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        if ($user->hasRole(Role::TEACHER) && ! $user->hasPermission('tasks.view_scope') && ! $user->hasPermission('reports.view_school')) {
            $myTasks = DocumentTask::query()
                ->with([
                    'documentType',
                    'teachingAllocation.schoolClass',
                    'teachingAllocation.section',
                    'teachingAllocation.subject',
                    'submission.currentVersion.latestReview.reviewer',
                ])
                ->where('assigned_teacher_id', $user->id)
                ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
                ->where('workflow_status', '!=', WorkflowStatus::CANCELLED->value)
                ->orderByRaw("CASE WHEN workflow_status = 'RETURNED_FOR_CORRECTION' THEN 0 WHEN is_overdue = 1 THEN 1 ELSE 2 END")
                ->orderBy('due_at')
                ->get();

            $returnedTasks = $myTasks->where('workflow_status', WorkflowStatus::RETURNED_FOR_CORRECTION->value)->values();

            return view('dashboard.teacher', [
                'currentYear' => $currentYear,
                'summary' => $dashboardData['summary'],
                'myTasks' => $myTasks,
                'returnedTasks' => $returnedTasks,
                'recentNotifications' => $recentNotifications,
            ]);
        }

        $scopedQuery = $this->reporting->buildFilteredTaskQuery($user, ['academic_year_id' => $yearId]);

        $pendingReviewTasks = (clone $scopedQuery)
            ->whereIn('workflow_status', [
                WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
                WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
            ])
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $overdueAndEscalated = (clone $scopedQuery)
            ->where('is_overdue', true)
            ->whereNotIn('workflow_status', [WorkflowStatus::APPROVED->value, WorkflowStatus::CANCELLED->value])
            ->orderByDesc('escalation_level')
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $executiveOverview = $this->managementMis->executiveOverview($user, [
            'academic_year_id' => $yearId,
        ]);

        return view('dashboard.leadership', [
            'currentYear' => $currentYear,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'selectedYearId' => $yearId,
            'summary' => $dashboardData['summary'],
            'byDocumentType' => $dashboardData['by_document_type'],
            'executiveOverview' => $executiveOverview,
            'pendingReviewTasks' => $pendingReviewTasks,
            'overdueAndEscalated' => $overdueAndEscalated,
            'recentNotifications' => $recentNotifications,
        ]);
    }
}
