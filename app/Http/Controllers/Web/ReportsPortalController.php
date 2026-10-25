<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsPortalController extends Controller
{
    public function __construct(
        private readonly ReportingService $reporting,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $currentYear = AcademicYear::current();
        $filters = $request->all();
        if (empty($filters['academic_year_id']) && $currentYear) {
            $filters['academic_year_id'] = $currentYear->id;
        }

        $tab = $request->string('tab', 'class-wise')->toString();
        if ($user->hasRole(Role::TEACHER) && ! $user->hasPermission('reports.view_scope') && ! $user->hasPermission('reports.view_school')) {
            $tab = 'teacher-wise';
        }

        $reportData = match ($tab) {
            'teacher-wise' => $this->reporting->teacherWiseReport($user, $filters),
            'subject-wise' => $this->reporting->subjectWiseReport($user, $filters),
            'overdue' => $this->reporting->overdueEscalationReport($user, $filters),
            default => $this->reporting->classWiseReport($user, $filters),
        };

        return view('reports.index', [
            'tab' => $tab,
            'filters' => $filters,
            'summary' => $reportData['summary'],
            'rows' => $reportData['rows'],
            'taskRecords' => $reportData['task_records'] ?? $reportData['rows'],
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get(),
            'sections' => Section::query()->where('is_active', true)->orderBy('name')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('display_order')->get(),
            'teachers' => User::query()->where('is_active', true)->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))->orderBy('full_name')->get(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $currentYear = AcademicYear::current();
        $filters = $request->all();
        if (empty($filters['academic_year_id']) && $currentYear) {
            $filters['academic_year_id'] = $currentYear->id;
        }

        $tasks = $this->reporting->buildFilteredTaskQuery($user, $filters)
            ->orderBy('due_at')
            ->get();
        $records = $this->reporting->formatTaskRecords($tasks);

        $fileName = 'excellia-academic-report-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Academic Year',
                'Class',
                'Section',
                'Subject',
                'Teacher',
                'Document Type',
                'Period',
                'Due Date',
                'Submission Timestamp',
                'Workflow Status',
                'Overdue Flag',
                'Overdue Days',
                'Reviewer',
                'Last Review Timestamp',
                'Latest Remarks',
                'Escalation Level',
                'Last Notification',
            ]);

            foreach ($records as $row) {
                fputcsv($out, [
                    $row['academic_year'],
                    $row['class_name'],
                    $row['section_name'],
                    $row['subject_name'],
                    $row['teacher_name'],
                    $row['document_type'],
                    $row['period'],
                    $row['due_date'],
                    $row['submission_timestamp'] ?? '',
                    $row['workflow_status_label'],
                    $row['is_overdue'] ? 'YES' : 'NO',
                    $row['overdue_days'],
                    $row['reviewer'],
                    $row['last_review_timestamp'] ?? '',
                    $row['latest_remarks'],
                    'L' . $row['escalation_level'],
                    $row['last_notification'],
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
