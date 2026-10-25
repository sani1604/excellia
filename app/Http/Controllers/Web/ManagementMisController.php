<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ManagementMisService;
use App\Services\ScopeAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagementMisController extends Controller
{
    public function __construct(
        private readonly ManagementMisService $mis,
        private readonly ScopeAuthorizationService $scopeAuth,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $currentYear = AcademicYear::current() ?? AcademicYear::query()->orderByDesc('id')->first();

        $input = $request->all();
        if (empty($input['academic_year_id']) && $currentYear) {
            $input['academic_year_id'] = $currentYear->id;
        }

        // Top-level executive overview respects selected academic year & viewer scope
        $overview = $this->mis->executiveOverview($user, [
            'academic_year_id' => $input['academic_year_id'] ?? null,
        ]);

        // Custom MIS ("Select & Do" + "Mentioned Query")
        $customMis = $this->mis->runCustomMis($user, $input);

        $schoolWide = $this->scopeAuth->hasSchoolWideVisibility($user);
        if ($schoolWide) {
            $classes = SchoolClass::query()->where('is_active', true)->orderBy('display_order')->get();
            $sections = Section::query()->where('is_active', true)->orderBy('name')->get();
            $teachers = User::query()
                ->where('is_active', true)
                ->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))
                ->orderBy('full_name')
                ->get();
        } else {
            $allocQuery = TeachingAllocation::query()->where('is_active', true);
            $this->scopeAuth->applyAllocationScope($allocQuery, $user);
            $scopedAllocs = $allocQuery->get();

            $classes = SchoolClass::query()
                ->where('is_active', true)
                ->whereIn('id', $scopedAllocs->pluck('class_id')->filter()->unique()->all())
                ->orderBy('display_order')
                ->get();
            $sections = Section::query()
                ->where('is_active', true)
                ->whereIn('id', $scopedAllocs->pluck('section_id')->filter()->unique()->all())
                ->orderBy('name')
                ->get();
            $teachers = User::query()
                ->where('is_active', true)
                ->whereIn('id', $scopedAllocs->pluck('teacher_user_id')->filter()->unique()->all())
                ->orderBy('full_name')
                ->get();
        }

        $reviewers = User::query()
            ->where('is_active', true)
            ->whereHas('primaryRole', fn ($q) => $q->whereIn('key', [
                Role::COORDINATOR,
                Role::HEADMISTRESS,
                Role::PRINCIPAL,
            ]))
            ->orderBy('full_name')
            ->get();

        return view('management.mis', [
            'overview' => $overview,
            'customMis' => $customMis,
            'availableColumns' => ManagementMisService::AVAILABLE_TASK_COLUMNS,
            'groupModes' => ManagementMisService::GROUP_MODES,
            'stageLabels' => ManagementMisService::STAGE_LABELS,
            'savedPresets' => $this->mis->savedPresets(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => $classes,
            'sections' => $sections,
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('display_order')->get(),
            'teachers' => $teachers,
            'reviewers' => $reviewers,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $customMis = $this->mis->runCustomMis($user, $request->all());

        $this->audit->log(
            'reports.management_mis_exported',
            'management_mis',
            null,
            null,
            [
                'mode' => $customMis['mode'],
                'group_by' => $customMis['group_by'],
                'mentioned_query' => $customMis['params']['mentioned_query'] ?? null,
                'row_count' => count($customMis['rows']),
            ],
            $user
        );

        $fileName = 'excellia-management-mis-' . $customMis['group_by'] . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($customMis) {
            $out = fopen('php://output', 'w');

            if ($customMis['mode'] === 'tasks') {
                $cols = $customMis['selected_columns'];
                $headers = array_map(
                    fn (string $colKey) => ManagementMisService::AVAILABLE_TASK_COLUMNS[$colKey] ?? $colKey,
                    $cols
                );
                fputcsv($out, $headers);

                foreach ($customMis['rows'] as $row) {
                    $line = [];
                    foreach ($cols as $colKey) {
                        $val = $row[$colKey] ?? '';
                        if ($colKey === 'is_overdue') {
                            $val = ! empty($row['is_overdue']) ? 'YES' : 'NO';
                        } elseif ($colKey === 'escalation_level') {
                            $val = 'L' . (int) ($row['escalation_level'] ?? 0);
                        }
                        $line[] = is_scalar($val) ? (string) $val : json_encode($val);
                    }
                    fputcsv($out, $line);
                }
            } else {
                $groupTitle = ManagementMisService::GROUP_MODES[$customMis['group_by']] ?? 'Group';
                fputcsv($out, [
                    $groupTitle,
                    'Details',
                    'Expected',
                    'Submitted',
                    'Approved',
                    'Waiting Check',
                    'Returned for Fix',
                    'Pending Upload',
                    'Overdue / Late',
                    'Escalated',
                    'Completion %',
                ]);

                foreach ($customMis['rows'] as $row) {
                    fputcsv($out, [
                        $row['label'] ?? '',
                        $row['sub_label'] ?? '',
                        $row['expected'] ?? 0,
                        $row['submitted'] ?? 0,
                        $row['approved'] ?? 0,
                        $row['pending_review'] ?? 0,
                        $row['returned'] ?? 0,
                        $row['pending_submission'] ?? 0,
                        $row['overdue'] ?? 0,
                        $row['escalated'] ?? 0,
                        ($row['completion_rate'] ?? 0) . '%',
                    ]);
                }
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storePreset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preset_name' => ['required', 'string', 'max:100'],
            'query_payload' => ['required', 'string', 'max:5000'],
        ]);

        parse_str(ltrim($validated['query_payload'], '?'), $parsedQuery);
        $this->mis->savePreset($validated['preset_name'], is_array($parsedQuery) ? $parsedQuery : [], $request->user());

        $this->audit->log(
            'reports.mis_preset_saved',
            'system_setting',
            null,
            null,
            ['preset_name' => $validated['preset_name']],
            $request->user()
        );

        return redirect()
            ->to(route('management.mis') . ($validated['query_payload'] ? '?' . ltrim($validated['query_payload'], '?') : ''))
            ->with('success', "Saved Custom MIS View \"{$validated['preset_name']}\".");
    }

    public function destroyPreset(Request $request, string $presetId): RedirectResponse
    {
        $this->mis->deletePreset($presetId, $request->user());

        return back()->with('success', 'Removed saved MIS preset.');
    }
}
