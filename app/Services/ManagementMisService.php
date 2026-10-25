<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Models\AcademicYear;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubmissionReview;
use App\Models\SubmissionVersion;
use App\Models\SystemSetting;
use App\Models\TeachingAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ManagementMisService
{
    public const AVAILABLE_TASK_COLUMNS = [
        'academic_year' => 'Academic Year',
        'stage_label' => 'Wing / Stage',
        'class_name' => 'Class',
        'section_name' => 'Section',
        'subject_name' => 'Subject',
        'teacher_name' => 'Teacher Name',
        'employee_code' => 'Emp Code',
        'teacher_phone' => 'Teacher Phone',
        'document_type' => 'Document Type',
        'title' => 'Task Title',
        'period' => 'Period / Month',
        'due_date' => 'Due Date',
        'submission_timestamp' => 'Submitted On',
        'version_count' => 'Uploads (v#)',
        'workflow_status_label' => 'Workflow Status',
        'is_overdue' => 'Late Flag',
        'overdue_days' => 'Overdue Days',
        'escalation_level' => 'Escalation',
        'reviewer' => 'Reviewer / Coordinator',
        'last_review_timestamp' => 'Reviewed On',
        'latest_remarks' => 'Latest Review Remarks',
        'last_notification' => 'Last Alert Sent',
        'current_owner' => 'Pending With',
    ];

    public const DEFAULT_TASK_COLUMNS = [
        'class_name',
        'section_name',
        'subject_name',
        'teacher_name',
        'document_type',
        'period',
        'due_date',
        'workflow_status_label',
        'overdue_days',
        'escalation_level',
        'reviewer',
    ];

    public const GROUP_MODES = [
        'tasks' => 'Detailed Task Register (Row-by-Row)',
        'stage' => 'Summary by School Wing / Stage',
        'class' => 'Summary by Class & Section',
        'teacher' => 'Summary by Teacher',
        'subject' => 'Summary by Subject',
        'document_type' => 'Summary by Document Type',
        'status' => 'Summary by Submission Status',
        'reviewer' => 'Summary by Reviewer / Coordinator',
        'escalation' => 'Summary by Escalation Level (L0–L3)',
    ];

    public const STAGE_LABELS = [
        'PRE_PRIMARY' => 'Pre-Primary (PG–KG)',
        'PRIMARY' => 'Primary (I–V)',
        'MIDDLE' => 'Middle (VI–VIII)',
        'SECONDARY' => 'Secondary (IX–X)',
        'SENIOR_SECONDARY' => 'Senior Secondary (XI–XII)',
    ];

    public function __construct(
        private readonly ReportingService $reporting,
        private readonly ScopeAuthorizationService $scopeAuth,
    ) {}

    /**
     * Complete 360-degree Executive Management Analytics & Chart Datasets.
     */
    public function executiveOverview(User $viewer, array $filters = []): array
    {
        $currentYear = AcademicYear::current();
        if (empty($filters['academic_year_id']) && $currentYear) {
            $filters['academic_year_id'] = $currentYear->id;
        }
        $yearId = ! empty($filters['academic_year_id']) ? (int) $filters['academic_year_id'] : null;

        $tasks = $this->buildExtendedTaskQuery($viewer, $filters)
            ->orderBy('due_at')
            ->get();

        $summary = $this->reporting->summarizeTasks($tasks);
        $tz = SystemSetting::timezone();
        $now = now($tz);

        $activeRequired = $tasks->filter(fn (DocumentTask $t) => $t->is_required
            && ! $t->is_exempt
            && $t->workflow_status !== WorkflowStatus::CANCELLED->value
        );

        // 1. Pie / Doughnut Chart: Submission Status Breakdown
        $overdueNotApproved = $activeRequired->filter(fn (DocumentTask $t) => $t->workflow_status !== WorkflowStatus::APPROVED->value
            && ($t->is_overdue || ($t->due_at && $t->due_at->lessThan($now)))
        )->count();

        $onTimeNotStarted = $activeRequired->filter(fn (DocumentTask $t) => $t->workflow_status === WorkflowStatus::NOT_STARTED->value
            && ! ($t->is_overdue || ($t->due_at && $t->due_at->lessThan($now)))
        )->count();

        $statusDistribution = [
            'labels' => ['Approved', 'Waiting Check', 'Sent Back for Fix', 'Pending (On Track)', 'Overdue / Late'],
            'counts' => [
                $summary['approved'],
                $summary['pending_review'],
                $summary['returned'],
                $onTimeNotStarted,
                $overdueNotApproved,
            ],
            'colors' => ['#10b981', '#0ea5e9', '#f59e0b', '#94a3b8', '#f43f5e'],
        ];

        // 2. Wing / Stage Stacked Bar Chart
        $stageOrder = array_keys(self::STAGE_LABELS);
        $stageRows = [];
        foreach ($stageOrder as $stageKey) {
            $group = $activeRequired->filter(fn (DocumentTask $t) => ($t->teachingAllocation?->schoolClass?->stage ?? '') === $stageKey);
            if ($group->isEmpty()) {
                continue;
            }
            $m = $this->reporting->summarizeTasks($group);
            $stageRows[] = array_merge([
                'stage_key' => $stageKey,
                'label' => self::STAGE_LABELS[$stageKey] ?? $stageKey,
            ], $m);
        }

        // 3. Class-wise Bar Chart
        $classRows = $activeRequired
            ->groupBy(fn (DocumentTask $t) => $t->teachingAllocation?->class_id ?? 0)
            ->map(function (Collection $group) {
                /** @var DocumentTask $first */
                $first = $group->first();
                $cls = $first->teachingAllocation?->schoolClass;
                $m = $this->reporting->summarizeTasks($group);

                return array_merge([
                    'class_id' => $cls?->id,
                    'label' => $cls?->name ?? 'Unassigned',
                    'display_order' => $cls?->display_order ?? 999,
                ], $m);
            })
            ->sortBy('display_order')
            ->values()
            ->all();

        // 4. Document Type Horizontal Bar Chart
        $docTypeRows = $activeRequired
            ->groupBy(fn (DocumentTask $t) => $t->document_type_id)
            ->map(function (Collection $group) {
                /** @var DocumentTask $first */
                $first = $group->first();
                $dt = $first->documentType;
                $m = $this->reporting->summarizeTasks($group);

                return array_merge([
                    'document_type_id' => $dt?->id,
                    'code' => $dt?->folder_code ?? '',
                    'label' => ($dt?->folder_code ? $dt->folder_code . ' · ' : '') . ($dt?->name ?? 'Other'),
                    'display_order' => $dt?->display_order ?? 99,
                ], $m);
            })
            ->sortBy('display_order')
            ->values()
            ->all();

        // 5. Subject-wise Bar Chart
        $subjectRows = $activeRequired
            ->groupBy(fn (DocumentTask $t) => $t->teachingAllocation?->subject_id ?? 0)
            ->map(function (Collection $group) {
                /** @var DocumentTask $first */
                $first = $group->first();
                $sub = $first->teachingAllocation?->subject;
                $m = $this->reporting->summarizeTasks($group);

                return array_merge([
                    'subject_id' => $sub?->id,
                    'label' => $sub?->name ?? 'Class Teacher General',
                ], $m);
            })
            ->sortByDesc('expected')
            ->values()
            ->all();

        // 6. Teacher Performance Bar Chart
        $teacherRows = $activeRequired
            ->groupBy('assigned_teacher_id')
            ->map(function (Collection $group) {
                /** @var DocumentTask $first */
                $first = $group->first();
                $m = $this->reporting->summarizeTasks($group);

                return array_merge([
                    'teacher_id' => $first->assigned_teacher_id,
                    'label' => $first->assignedTeacher?->full_name ?? 'Unknown',
                    'employee_code' => $first->assignedTeacher?->employee_code ?? '-',
                ], $m);
            })
            ->sortByDesc('completion_rate')
            ->values()
            ->all();

        // 7. Escalation Funnel
        $escalationFunnel = [
            'labels' => [
                'Approved',
                'Active On-Time',
                'Due Soon (≤3d)',
                'Overdue L0',
                'Escalated L1 (Coord)',
                'Escalated L2 (HM/Prin)',
                'Escalated L3+ (Director)',
            ],
            'counts' => [
                $summary['approved'],
                max(0, $summary['expected'] - $summary['approved'] - $summary['overdue'] - $summary['due_soon']),
                $summary['due_soon'],
                $activeRequired->filter(fn (DocumentTask $t) => $t->workflow_status !== 'APPROVED' && ($t->is_overdue || ($t->due_at && $t->due_at->lessThan($now))) && (int) $t->escalation_level === 0)->count(),
                $activeRequired->filter(fn (DocumentTask $t) => $t->workflow_status !== 'APPROVED' && (int) $t->escalation_level === 1)->count(),
                $activeRequired->filter(fn (DocumentTask $t) => $t->workflow_status !== 'APPROVED' && (int) $t->escalation_level === 2)->count(),
                $activeRequired->filter(fn (DocumentTask $t) => $t->workflow_status !== 'APPROVED' && (int) $t->escalation_level >= 3)->count(),
            ],
            'colors' => ['#10b981', '#38bdf8', '#fbbf24', '#fb7185', '#f97316', '#e11d48', '#881337'],
        ];

        // 8. Monthly / Due-Month Trend Line + Bar Chart
        $timelineRows = $activeRequired
            ->groupBy(fn (DocumentTask $t) => $t->due_at ? $t->due_at->timezone($tz)->format('Y-m') : 'Unscheduled')
            ->sortKeys()
            ->map(function (Collection $group, string $ym) use ($tz) {
                $m = $this->reporting->summarizeTasks($group);
                $firstDue = $group->first()?->due_at;
                $niceLabel = ($ym !== 'Unscheduled' && $firstDue)
                    ? $firstDue->timezone($tz)->format('M Y')
                    : $ym;

                return array_merge([
                    'month_key' => $ym,
                    'label' => $niceLabel,
                ], $m);
            })
            ->values()
            ->all();

        // 9. Operational School Snapshot (Masters, Storage, Review Turnaround, Alerts)
        $allocQuery = TeachingAllocation::query()->where('is_active', true)
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId));
        $this->scopeAuth->applyAllocationScope($allocQuery, $viewer);
        $scopedAllocs = $allocQuery->get();

        $taskIds = $tasks->pluck('id')->all();
        $submissionIds = $tasks->pluck('submission.id')->filter()->values()->all();

        $versionsAgg = ! empty($submissionIds)
            ? SubmissionVersion::query()
                ->whereIn('document_submission_id', $submissionIds)
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(file_size_bytes), 0) as total_bytes')
                ->first()
            : null;

        $avgReviewHours = null;
        if (! empty($submissionIds)) {
            $reviews = SubmissionReview::query()
                ->with('version')
                ->whereHas('version', fn ($q) => $q->whereIn('document_submission_id', $submissionIds))
                ->get();
            if ($reviews->isNotEmpty()) {
                $diffs = $reviews->map(function (SubmissionReview $rev) {
                    $up = $rev->version?->uploaded_at;
                    $rv = $rev->reviewed_at;

                    return ($up && $rv && $rv->greaterThanOrEqualTo($up))
                        ? round($up->diffInMinutes($rv) / 60, 1)
                        : null;
                })->filter(fn ($v) => $v !== null);

                if ($diffs->isNotEmpty()) {
                    $avgReviewHours = round($diffs->avg(), 1);
                }
            }
        }

        $notifBreakdown = NotificationLog::query()
            ->when(! empty($taskIds), fn ($q) => $q->where(function ($sub) use ($taskIds) {
                $sub->where('entity_type', '!=', 'document_task')
                    ->orWhereIn('entity_id', $taskIds);
            }))
            ->where('delivery_status', 'SENT')
            ->selectRaw('channel, COUNT(*) as total')
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->all();

        $driveMappedCount = DriveFolderMapping::query()
            ->where('is_active', true)
            ->whereNotNull('verified_at')
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->count();

        $schoolSnapshot = [
            'active_teachers' => User::query()->where('is_active', true)->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))->count(),
            'total_active_staff' => User::query()->where('is_active', true)->count(),
            'active_classes' => $scopedAllocs->pluck('class_id')->filter()->unique()->count(),
            'active_sections' => $scopedAllocs->pluck('section_id')->filter()->unique()->count(),
            'verified_allocations' => $scopedAllocs->where('is_timetable_verified', true)->count(),
            'total_allocations' => $scopedAllocs->count(),
            'mapped_drive_folders' => $driveMappedCount,
            'uploaded_versions' => (int) ($versionsAgg?->cnt ?? 0),
            'total_storage_mb' => round(((int) ($versionsAgg?->total_bytes ?? 0)) / (1024 * 1024), 2),
            'avg_review_hours' => $avgReviewHours,
            'alerts_in_app' => (int) ($notifBreakdown['IN_APP'] ?? 0),
            'alerts_email' => (int) ($notifBreakdown['EMAIL'] ?? 0),
            'alerts_whatsapp' => (int) ($notifBreakdown['WHATSAPP'] ?? 0),
        ];

        return [
            'summary' => $summary,
            'snapshot' => $schoolSnapshot,
            'charts' => [
                'status_distribution' => $statusDistribution,
                'stage_breakdown' => $stageRows,
                'class_breakdown' => $classRows,
                'document_type_breakdown' => $docTypeRows,
                'subject_breakdown' => $subjectRows,
                'teacher_performance' => $teacherRows,
                'escalation_funnel' => $escalationFunnel,
                'timeline_trend' => $timelineRows,
            ],
        ];
    }

    /**
     * Parses a plain-English "Mentioned Query" into structured MIS filters, grouping, sorting, and chart type.
     */
    public function parseMentionedQuery(string $queryText, array $baseParams = []): array
    {
        $raw = trim($queryText);
        if ($raw === '') {
            return ['params' => $baseParams, 'interpreted' => []];
        }

        $q = strtolower($raw);
        $params = $baseParams;
        $interpreted = [];

        // 1. Academic Year detection (e.g. 2025-26, 2026-27, 2027-28)
        foreach (AcademicYear::query()->get() as $yr) {
            if (str_contains($q, strtolower($yr->name))) {
                $params['academic_year_id'] = $yr->id;
                $interpreted[] = "Session: {$yr->name}";
                break;
            }
        }

        // 2. Wing / Stage detection (order from most specific to general!)
        $stagePatterns = [
            'SENIOR_SECONDARY' => ['senior secondary', 'sr secondary', 'sr. secondary', 'xi-xii', '11 and 12'],
            'PRE_PRIMARY' => ['pre-primary', 'pre primary', 'preprimary', 'kindergarten', 'foundational'],
            'SECONDARY' => ['secondary wing', 'high school'],
            'MIDDLE' => ['middle wing', 'middle school', 'middle stage'],
            'PRIMARY' => ['primary wing', 'primary school', 'primary stage'],
        ];
        foreach ($stagePatterns as $stageKey => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($q, $phrase)) {
                    $params['stage'] = $stageKey;
                    $interpreted[] = 'Wing: ' . (self::STAGE_LABELS[$stageKey] ?? $stageKey);
                    break 2;
                }
            }
        }

        // 3. Class detection (handles Class XII, Class X, Class 10, Class IV, Class 4, KG, Nursery, PG)
        $classes = SchoolClass::query()->where('is_active', true)->orderByDesc('display_order')->get();
        $numberAliases = [
            'class 12' => 'Class XII', 'grade 12' => 'Class XII', 'class xii' => 'Class XII',
            'class 11' => 'Class XI',  'grade 11' => 'Class XI',  'class xi' => 'Class XI',
            'class 10' => 'Class X',   'grade 10' => 'Class X',   'class x' => 'Class X',
            'class 9' => 'Class IX',   'grade 9' => 'Class IX',   'class ix' => 'Class IX',
            'class 8' => 'Class VIII', 'grade 8' => 'Class VIII', 'class viii' => 'Class VIII',
            'class 7' => 'Class VII',  'grade 7' => 'Class VII',  'class vii' => 'Class VII',
            'class 6' => 'Class VI',   'grade 6' => 'Class VI',   'class vi' => 'Class VI',
            'class 5' => 'Class V',    'grade 5' => 'Class V',    'class v' => 'Class V',
            'class 4' => 'Class IV',   'grade 4' => 'Class IV',   'class iv' => 'Class IV',
            'class 3' => 'Class III',  'grade 3' => 'Class III',  'class iii' => 'Class III',
            'class 2' => 'Class II',   'grade 2' => 'Class II',   'class ii' => 'Class II',
            'class 1' => 'Class I',    'grade 1' => 'Class I',    'class i' => 'Class I',
            'nursery' => 'Nursery',
            'playgroup' => 'PG',
        ];
        foreach ($numberAliases as $needle => $targetName) {
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/i', $raw)) {
                $matched = $classes->first(fn (SchoolClass $c) => strcasecmp($c->name, $targetName) === 0);
                if ($matched) {
                    $params['class_id'] = $matched->id;
                    $interpreted[] = "Class: {$matched->name}";
                    break;
                }
            }
        }
        if (empty($params['class_id']) && preg_match('/\b(kg|pg)\b/i', $raw, $m)) {
            $matched = $classes->first(fn (SchoolClass $c) => strcasecmp($c->name, $m[1]) === 0 || strcasecmp($c->code, $m[1]) === 0);
            if ($matched) {
                $params['class_id'] = $matched->id;
                $interpreted[] = "Class: {$matched->name}";
            }
        }

        // 4. Subject detection
        $subjects = Subject::query()->where('is_active', true)->get();
        foreach ($subjects as $sub) {
            $subLower = strtolower($sub->name);
            if (str_contains($q, $subLower) || ($subLower === 'mathematics' && preg_match('/\bmaths?\b/i', $raw))) {
                $params['subject_id'] = $sub->id;
                $interpreted[] = "Subject: {$sub->name}";
                break;
            }
        }

        // 5. Document Type detection
        $docKeywords = [
            'lesson plan' => '01',
            'worksheet' => '02',
            'assignment' => '02',
            'question paper' => '03',
            'marking scheme' => '03',
            'answer sheet' => '04',
            'remedial' => '05',
            'assessment analysis' => '05',
            'overview' => '00',
            'calendar' => '00',
        ];
        $docTypes = DocumentType::query()->where('is_active', true)->get();
        foreach ($docKeywords as $kw => $folderCode) {
            if (str_contains($q, $kw)) {
                $dt = $docTypes->first(fn (DocumentType $d) => $d->folder_code === $folderCode || str_contains(strtolower($d->name), $kw));
                if ($dt) {
                    $params['document_type_id'] = $dt->id;
                    $interpreted[] = "Document Type: {$dt->name}";
                    break;
                }
            }
        }

        // 6. Teacher Name detection
        $teachers = User::query()->where('is_active', true)->whereHas('primaryRole', fn ($r) => $r->where('key', Role::TEACHER))->get();
        foreach ($teachers as $teacher) {
            $full = strtolower($teacher->full_name);
            $firstPart = explode(' ', $full)[0] ?? '';
            if (str_contains($q, $full) || (strlen($firstPart) >= 4 && preg_match('/\b' . preg_quote($firstPart, '/') . '\b/i', $raw))) {
                $params['teacher_user_id'] = $teacher->id;
                $interpreted[] = "Teacher: {$teacher->full_name}";
                break;
            }
        }

        // 7. Status & Escalation detection
        if (preg_match('/\b(l3|level 3)\b/i', $raw)) {
            $params['escalation_min'] = 3;
            $interpreted[] = 'Escalation: Level 3+';
        } elseif (preg_match('/\b(l2|level 2)\b/i', $raw)) {
            $params['escalation_min'] = 2;
            $interpreted[] = 'Escalation: Level 2+';
        } elseif (preg_match('/\b(l1|level 1|escalat)/i', $raw)) {
            $params['status'] = 'ESCALATED';
            $interpreted[] = 'Status: Escalated';
        }

        if (empty($params['status'])) {
            if (preg_match('/\b(overdue|late|delay|delayed|missed)\b/i', $raw)) {
                $params['status'] = 'OVERDUE';
                $interpreted[] = 'Status: Overdue / Late';
            } elseif (preg_match('/\b(pending review|waiting check|awaiting review|unreviewed|to check)\b/i', $raw)) {
                $params['status'] = 'PENDING_REVIEW';
                $interpreted[] = 'Status: Waiting Coordinator Check';
            } elseif (preg_match('/\b(returned|correction|sent back|rejected)\b/i', $raw)) {
                $params['status'] = 'RETURNED_FOR_CORRECTION';
                $interpreted[] = 'Status: Returned for Correction';
            } elseif (preg_match('/\b(not started|unsubmitted|not uploaded|not submitted)\b/i', $raw)) {
                $params['status'] = 'NOT_STARTED';
                $interpreted[] = 'Status: Not Uploaded Yet';
            } elseif (preg_match('/\b(approved|completed)\b/i', $raw) && ! str_contains($q, 'vs') && ! str_contains($q, 'completion')) {
                $params['status'] = 'APPROVED';
                $interpreted[] = 'Status: Approved';
            }
        }

        // 8. Month / Period detection (e.g. October, November, Dec 2026, Term 1, Unit Test)
        $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
        foreach ($months as $month) {
            if (str_contains($q, $month)) {
                $params['period_label'] = ucfirst($month);
                $interpreted[] = 'Period: ' . ucfirst($month);
                break;
            }
        }

        // 9. Group-By / Aggregation mode detection
        $groupAliases = [
            'teacher' => ['by teacher', 'teacher wise', 'teacher-wise', 'per teacher', 'teachers with'],
            'class' => ['by class', 'class wise', 'class-wise', 'per class', 'section wise'],
            'stage' => ['by wing', 'wing wise', 'wing-wise', 'by stage', 'stage wise', 'stage-wise'],
            'subject' => ['by subject', 'subject wise', 'subject-wise', 'per subject'],
            'document_type' => ['by document', 'document wise', 'doc type wise', 'by category', 'by file type'],
            'status' => ['by status', 'status wise', 'status breakdown', 'status summary'],
            'reviewer' => ['by reviewer', 'by coordinator', 'coordinator wise', 'reviewer wise'],
            'escalation' => ['by escalation', 'escalation wise', 'escalation summary'],
        ];
        foreach ($groupAliases as $gKey => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($q, $phrase)) {
                    $params['group_by'] = $gKey;
                    $interpreted[] = 'Grouped By: ' . (self::GROUP_MODES[$gKey] ?? $gKey);
                    break 2;
                }
            }
        }

        // 10. Completion % threshold detection (e.g., "below 60%", "less than 75%", "above 80%", "100%")
        if (preg_match('/(below|under|less than|<)\s*(\d{1,3})\s*%?/i', $raw, $m)) {
            $params['completion_op'] = 'lt';
            $params['completion_val'] = min(100, (int) $m[2]);
            if (empty($params['group_by']) || $params['group_by'] === 'tasks') {
                $params['group_by'] = 'teacher';
            }
            $interpreted[] = "Completion < {$params['completion_val']}%";
        } elseif (preg_match('/(above|over|more than|greater than|>)\s*(\d{1,3})\s*%?/i', $raw, $m)) {
            $params['completion_op'] = 'gt';
            $params['completion_val'] = min(100, (int) $m[2]);
            if (empty($params['group_by']) || $params['group_by'] === 'tasks') {
                $params['group_by'] = 'teacher';
            }
            $interpreted[] = "Completion > {$params['completion_val']}%";
        } elseif (preg_match('/\b100\s*%/i', $raw)) {
            $params['completion_op'] = 'eq';
            $params['completion_val'] = 100;
            if (empty($params['group_by']) || $params['group_by'] === 'tasks') {
                $params['group_by'] = 'teacher';
            }
            $interpreted[] = 'Completion = 100%';
        }

        // 11. Chart Type preference detection
        if (str_contains($q, 'pie')) {
            $params['chart_type'] = 'pie';
            $interpreted[] = 'Chart: Pie Chart';
        } elseif (str_contains($q, 'doughnut') || str_contains($q, 'donut')) {
            $params['chart_type'] = 'doughnut';
            $interpreted[] = 'Chart: Doughnut Chart';
        } elseif (str_contains($q, 'line') || str_contains($q, 'trend')) {
            $params['chart_type'] = 'line';
            $interpreted[] = 'Chart: Line Chart';
        } elseif (str_contains($q, 'horizontal')) {
            $params['chart_type'] = 'horizontalBar';
            $interpreted[] = 'Chart: Horizontal Bar';
        } elseif (str_contains($q, 'bar')) {
            $params['chart_type'] = 'bar';
            $interpreted[] = 'Chart: Bar Chart';
        }

        // 12. Sorting hints
        if (preg_match('/\b(top late|most late|most overdue|highest delay)\b/i', $raw)) {
            $params['sort_by'] = 'overdue';
            $params['sort_dir'] = 'desc';
            if (empty($params['group_by'])) {
                $params['group_by'] = 'teacher';
            }
            $interpreted[] = 'Sorted By: Most Late First';
        } elseif (preg_match('/\b(lowest completion|worst|lagging)\b/i', $raw)) {
            $params['sort_by'] = 'completion_rate';
            $params['sort_dir'] = 'asc';
            if (empty($params['group_by'])) {
                $params['group_by'] = 'teacher';
            }
            $interpreted[] = 'Sorted By: Lowest Completion First';
        }

        if (empty($interpreted)) {
            // Free-text fallback: search task title or period label
            $params['keyword'] = $raw;
            $interpreted[] = "Keyword Match: \"{$raw}\"";
        }

        return [
            'params' => $params,
            'interpreted' => $interpreted,
        ];
    }

    /**
     * Executes a Custom MIS Report ("Select & Do" + "Mentioned Query") and returns rows, columns, KPIs, and dynamic chart data.
     */
    public function runCustomMis(User $viewer, array $rawInput = []): array
    {
        $currentYear = AcademicYear::current();
        $params = $rawInput;

        if (empty($params['academic_year_id']) && $currentYear) {
            $params['academic_year_id'] = $currentYear->id;
        }

        $mentionedQuery = trim((string) ($params['mentioned_query'] ?? ''));
        $interpreted = [];

        if ($mentionedQuery !== '') {
            $parsed = $this->parseMentionedQuery($mentionedQuery, $params);
            $params = $parsed['params'];
            $interpreted = $parsed['interpreted'];
        }

        $groupBy = (string) ($params['group_by'] ?? 'tasks');
        if (! array_key_exists($groupBy, self::GROUP_MODES)) {
            $groupBy = 'tasks';
        }

        $chartType = (string) ($params['chart_type'] ?? ($groupBy === 'status' ? 'doughnut' : 'bar'));
        if (! in_array($chartType, ['bar', 'horizontalBar', 'pie', 'doughnut', 'line'], true)) {
            $chartType = 'bar';
        }

        $selectedColumns = $params['columns'] ?? self::DEFAULT_TASK_COLUMNS;
        if (! is_array($selectedColumns) || empty($selectedColumns)) {
            $selectedColumns = self::DEFAULT_TASK_COLUMNS;
        }
        $selectedColumns = array_values(array_filter(
            $selectedColumns,
            fn ($col) => is_string($col) && array_key_exists($col, self::AVAILABLE_TASK_COLUMNS)
        ));
        if (empty($selectedColumns)) {
            $selectedColumns = self::DEFAULT_TASK_COLUMNS;
        }

        $tasks = $this->buildExtendedTaskQuery($viewer, $params)
            ->orderBy('due_at')
            ->get();

        $summary = $this->reporting->summarizeTasks($tasks);

        if ($groupBy === 'tasks') {
            $rows = $this->formatRichTaskRows($tasks);
            $sortBy = (string) ($params['sort_by'] ?? 'due_date');
            $sortDir = strtolower((string) ($params['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            $rows = collect($rows)->sortBy(
                fn (array $r) => $r[$sortBy] ?? $r['due_date'] ?? '',
                SORT_NATURAL,
                $sortDir === 'desc'
            )->values()->all();

            // Build dynamic chart from task set (group by class or status automatically for visual clarity)
            $chartData = $this->buildDynamicChartFromGroupedRows(
                $this->aggregateTasksByDimension($tasks, 'class'),
                $chartType,
                'Class-wise Breakdown of Filtered Tasks'
            );

            return [
                'mode' => 'tasks',
                'group_by' => $groupBy,
                'chart_type' => $chartType,
                'params' => $params,
                'interpreted' => $interpreted,
                'selected_columns' => $selectedColumns,
                'summary' => $summary,
                'rows' => $rows,
                'chart' => $chartData,
            ];
        }

        // Aggregated Pivot / Summary MIS Mode
        $groupedRows = $this->aggregateTasksByDimension($tasks, $groupBy);

        // Apply optional completion % threshold filter (e.g. completion < 60%)
        if (isset($params['completion_op'], $params['completion_val']) && $params['completion_val'] !== '' && $params['completion_val'] !== null) {
            $val = (float) $params['completion_val'];
            $op = (string) $params['completion_op'];
            $groupedRows = array_values(array_filter($groupedRows, function (array $r) use ($op, $val): bool {
                $rate = (float) ($r['completion_rate'] ?? 0);

                return match ($op) {
                    'lt' => $rate < $val,
                    'lte' => $rate <= $val,
                    'gt' => $rate > $val,
                    'gte' => $rate >= $val,
                    'eq' => abs($rate - $val) < 0.05,
                    default => true,
                };
            }));
        }

        $sortBy = (string) ($params['sort_by'] ?? 'completion_rate');
        $sortDir = strtolower((string) ($params['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $groupedRows = collect($groupedRows)->sortBy(
            fn (array $r) => $r[$sortBy] ?? $r['label'] ?? 0,
            SORT_NATURAL,
            $sortDir === 'desc'
        )->values()->all();

        $chartData = $this->buildDynamicChartFromGroupedRows(
            $groupedRows,
            $chartType,
            self::GROUP_MODES[$groupBy] ?? 'Custom MIS Chart'
        );

        return [
            'mode' => 'grouped',
            'group_by' => $groupBy,
            'chart_type' => $chartType,
            'params' => $params,
            'interpreted' => $interpreted,
            'selected_columns' => $selectedColumns,
            'summary' => $summary,
            'rows' => $groupedRows,
            'chart' => $chartData,
        ];
    }

    /**
     * Saved Custom MIS Presets stored in SystemSetting so Management can pin frequent custom reports.
     */
    public function savedPresets(): array
    {
        $saved = SystemSetting::getValue('management_mis_saved_presets', []);

        return is_array($saved) ? array_values($saved) : [];
    }

    public function savePreset(string $name, array $queryParams, User $actor): array
    {
        $presets = $this->savedPresets();
        $cleanParams = collect($queryParams)
            ->only([
                'academic_year_id', 'stage', 'class_id', 'section_id', 'subject_id',
                'teacher_user_id', 'reviewer_user_id', 'document_type_id', 'status',
                'escalation_min', 'due_from', 'due_to', 'period_label', 'keyword',
                'mentioned_query', 'group_by', 'chart_type', 'completion_op',
                'completion_val', 'sort_by', 'sort_dir', 'columns',
            ])
            ->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])
            ->all();

        $presets[] = [
            'id' => uniqid('mis_', false),
            'name' => trim($name),
            'params' => $cleanParams,
            'created_by' => $actor->full_name,
            'created_at' => now(SystemSetting::timezone())->format('d M Y, h:i A'),
        ];

        SystemSetting::setValue('management_mis_saved_presets', $presets, 'reports', 'Saved Custom Management MIS Presets', $actor->id);

        return $presets;
    }

    public function deletePreset(string $presetId, User $actor): void
    {
        $presets = array_values(array_filter(
            $this->savedPresets(),
            fn (array $p) => ($p['id'] ?? '') !== $presetId
        ));

        SystemSetting::setValue('management_mis_saved_presets', $presets, 'reports', 'Saved Custom Management MIS Presets', $actor->id);
    }

    private function buildExtendedTaskQuery(User $viewer, array $filters): Builder
    {
        $query = $this->reporting->buildFilteredTaskQuery($viewer, $filters);

        if (! empty($filters['stage'])) {
            $stage = (string) $filters['stage'];
            $query->whereHas('teachingAllocation.schoolClass', fn (Builder $q) => $q->where('stage', $stage));
        }

        if (! empty($filters['reviewer_user_id'])) {
            $query->where('document_tasks.reviewer_user_id', (int) $filters['reviewer_user_id']);
        }

        if (isset($filters['escalation_min']) && $filters['escalation_min'] !== '' && $filters['escalation_min'] !== null) {
            $query->where('document_tasks.escalation_level', '>=', (int) $filters['escalation_min']);
        }

        if (! empty($filters['due_from'])) {
            $query->whereDate('document_tasks.due_at', '>=', (string) $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->whereDate('document_tasks.due_at', '<=', (string) $filters['due_to']);
        }

        if (! empty($filters['keyword'])) {
            $kw = trim((string) $filters['keyword']);
            $query->where(function (Builder $sub) use ($kw) {
                $sub->where('document_tasks.title', 'like', "%{$kw}%")
                    ->orWhere('document_tasks.period_label', 'like', "%{$kw}%")
                    ->orWhere('document_tasks.instructions', 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    private function aggregateTasksByDimension(Collection $tasks, string $groupBy): array
    {
        $nonCancelled = $tasks->filter(fn (DocumentTask $t) => $t->workflow_status !== WorkflowStatus::CANCELLED->value);

        if ($nonCancelled->isEmpty()) {
            return [];
        }

        return $nonCancelled
            ->groupBy(function (DocumentTask $t) use ($groupBy): string {
                $alloc = $t->teachingAllocation;

                return match ($groupBy) {
                    'stage' => $alloc?->schoolClass?->stage ?? 'OTHER',
                    'class' => ($alloc?->class_id ?? 0) . '|' . ($alloc?->section_id ?? 0),
                    'teacher' => (string) $t->assigned_teacher_id,
                    'subject' => (string) ($alloc?->subject_id ?? 0),
                    'document_type' => (string) $t->document_type_id,
                    'status' => $t->effectiveStatusKey(),
                    'reviewer' => (string) ($t->reviewer_user_id ?? 0),
                    'escalation' => 'L' . (int) $t->escalation_level,
                    default => 'ALL',
                };
            })
            ->map(function (Collection $group, string $key) use ($groupBy): array {
                /** @var DocumentTask $first */
                $first = $group->first();
                $alloc = $first->teachingAllocation;
                $metrics = $this->reporting->summarizeTasks($group);

                $label = match ($groupBy) {
                    'stage' => self::STAGE_LABELS[$key] ?? $key,
                    'class' => trim(($alloc?->schoolClass?->name ?? 'N/A') . ($alloc?->section?->name ? ' - ' . $alloc->section->name : '')),
                    'teacher' => $first->assignedTeacher?->full_name ?? 'Unassigned',
                    'subject' => $alloc?->subject?->name ?? 'Class Teacher General',
                    'document_type' => ($first->documentType?->folder_code ? $first->documentType->folder_code . ' · ' : '') . ($first->documentType?->name ?? 'Other'),
                    'status' => $first->effectiveStatusLabel(),
                    'reviewer' => $first->reviewer?->full_name ?? 'Scoped Wing Coordinator',
                    'escalation' => match ($key) {
                        'L0' => 'Level 0 (No Escalation)',
                        'L1' => 'Level 1 (Coordinator Alert)',
                        'L2' => 'Level 2 (Headmistress / Principal)',
                        default => "{$key} (Director / Senior Escalation)",
                    },
                    default => $key,
                };

                $subLabel = match ($groupBy) {
                    'teacher' => ($first->assignedTeacher?->employee_code ?? '-') . ' · ' . ($first->assignedTeacher?->email ?? ''),
                    'class' => self::STAGE_LABELS[$alloc?->schoolClass?->stage ?? ''] ?? ($alloc?->schoolClass?->stage ?? ''),
                    'document_type' => $first->documentType?->sensitivity_level ?? 'STANDARD',
                    default => '',
                };

                return array_merge([
                    'group_key' => $key,
                    'label' => $label,
                    'sub_label' => $subLabel,
                ], $metrics);
            })
            ->values()
            ->all();
    }

    private function formatRichTaskRows(Collection $tasks): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $baseRecords = collect($this->reporting->formatTaskRecords($tasks))->keyBy('task_id');
        $submissionIds = $tasks->pluck('submission.id')->filter()->values()->all();
        $versionCounts = ! empty($submissionIds)
            ? SubmissionVersion::query()
                ->whereIn('document_submission_id', $submissionIds)
                ->selectRaw('document_submission_id, COUNT(*) as cnt')
                ->groupBy('document_submission_id')
                ->pluck('cnt', 'document_submission_id')
                ->all()
            : [];

        return $tasks->map(function (DocumentTask $t) use ($baseRecords, $versionCounts): array {
            $base = $baseRecords->get($t->id, []);
            $stageKey = $t->teachingAllocation?->schoolClass?->stage ?? '';
            $subId = $t->submission?->id;

            return array_merge($base, [
                'stage_key' => $stageKey,
                'stage_label' => self::STAGE_LABELS[$stageKey] ?? $stageKey,
                'employee_code' => $t->assignedTeacher?->employee_code ?? '-',
                'teacher_phone' => $t->assignedTeacher?->phone ?? '-',
                'version_count' => $subId ? (int) ($versionCounts[$subId] ?? 0) : 0,
            ]);
        })->values()->all();
    }

    private function buildDynamicChartFromGroupedRows(array $rows, string $chartType, string $title): array
    {
        $sliced = array_slice($rows, 0, 18);
        $labels = array_map(fn (array $r) => $r['label'] ?? '-', $sliced);

        if (in_array($chartType, ['pie', 'doughnut'], true)) {
            $palette = [
                '#4f46e5', '#10b981', '#0ea5e9', '#f59e0b', '#f43f5e',
                '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#64748b',
                '#06b6d4', '#84cc16',
            ];
            $values = array_map(fn (array $r) => (int) ($r['expected'] ?? 0), $sliced);
            $colors = [];
            foreach ($labels as $idx => $_) {
                $colors[] = $palette[$idx % count($palette)];
            }

            return [
                'title' => $title,
                'type' => $chartType,
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Tasks Count',
                        'data' => $values,
                        'backgroundColor' => $colors,
                    ],
                ],
            ];
        }

        return [
            'title' => $title,
            'type' => $chartType === 'horizontalBar' ? 'bar' : $chartType,
            'indexAxis' => $chartType === 'horizontalBar' ? 'y' : 'x',
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Approved',
                    'data' => array_map(fn (array $r) => (int) ($r['approved'] ?? 0), $sliced),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                ],
                [
                    'label' => 'Waiting Check',
                    'data' => array_map(fn (array $r) => (int) ($r['pending_review'] ?? 0), $sliced),
                    'backgroundColor' => '#0ea5e9',
                    'borderColor' => '#0ea5e9',
                ],
                [
                    'label' => 'Overdue / Late',
                    'data' => array_map(fn (array $r) => (int) ($r['overdue'] ?? 0), $sliced),
                    'backgroundColor' => '#f43f5e',
                    'borderColor' => '#f43f5e',
                ],
                [
                    'label' => 'Pending (Not Uploaded)',
                    'data' => array_map(fn (array $r) => (int) ($r['pending_submission'] ?? 0), $sliced),
                    'backgroundColor' => '#cbd5e1',
                    'borderColor' => '#94a3b8',
                ],
            ],
        ];
    }
}
