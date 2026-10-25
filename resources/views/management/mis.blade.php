@extends('layouts.app')

@section('title', 'Management MIS, Charts & Custom Query Builder')
@section('heading', 'Executive Management Dashboard & Custom MIS')
@section('subheading', '360° whole-school analytics, interactive Pie & Bar charts, and "Select & Do" or plain-English "Mentioned Query" MIS builder.')

@section('content')
    @php
        $summary = $overview['summary'];
        $snap = $overview['snapshot'];
        $charts = $overview['charts'];
        $params = $customMis['params'];
        $aiAllowed = app(\App\Services\AiAssistantService::class)->isAllowedForUser(auth()->user());
        $currentQueryString = request()->getQueryString() ?? '';
        $briefingStats = sprintf(
            'Expected %d tasks, Approved %d (%s%%), Waiting Check %d, Overdue %d, Escalated %d across %d active teachers and %d classes.',
            $summary['expected'],
            $summary['approved'],
            number_format($summary['completion_rate'], 1),
            $summary['pending_review'],
            $summary['overdue'],
            $summary['escalated'],
            $snap['active_teachers'],
            $snap['active_classes']
        );
    @endphp

    <!-- Top Executive Control Bar -->
    <div class="bg-slate-900 text-white rounded-2xl p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs">
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('management.mis') }}" class="flex items-center gap-2">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-300">Academic Session:</label>
                <select name="academic_year_id" onchange="this.form.submit()"
                        class="rounded-xl bg-slate-800 border border-slate-700 text-white px-3.5 py-2 text-sm font-semibold">
                    @foreach($academicYears as $yr)
                        <option value="{{ $yr->id }}" @selected(($params['academic_year_id'] ?? '') == $yr->id)>
                            {{ $yr->name }} {{ $yr->is_current ? '(Active)' : '' }}
                        </option>
                    @endforeach
                </select>
            </form>
            <span class="hidden sm:inline text-slate-600">|</span>
            <span class="text-xs text-slate-300">
                Viewing as <strong class="text-amber-300">{{ auth()->user()->primaryRole?->name }}</strong>
                ({{ auth()->user()->hasPermission('reports.view_school') ? 'Whole-School Visibility' : 'Assigned Wing Scope' }})
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2 no-print">
            @if($aiAllowed)
                <button type="button"
                        onclick="window.excelliaAiAssist(this,{action:'management_summary',keywords:@js($briefingStats)},function(txt){const b=document.getElementById('aiMgmtBriefBox');b.classList.remove('hidden');document.getElementById('aiMgmtBriefText').textContent=txt;})"
                        class="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs sm:text-sm font-semibold cursor-pointer">
                    ✨ AI Executive Briefing
                </button>
            @endif
            <a href="#custom-mis-builder"
               class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs sm:text-sm font-bold">
                Jump to Custom MIS Builder ↓
            </a>
            <a href="{{ route('management.mis.export', request()->query()) }}"
               class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs sm:text-sm font-semibold">
                Export MIS Excel (CSV)
            </a>
            <button type="button" onclick="window.print()"
                    class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs sm:text-sm font-semibold cursor-pointer">
                Print / PDF
            </button>
        </div>
    </div>

    <!-- Optional AI Executive Briefing Panel -->
    <div id="aiMgmtBriefBox" class="hidden bg-purple-50 border border-purple-200 rounded-2xl p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2 mb-1.5">
            <span class="text-xs font-bold uppercase tracking-wider text-purple-900">✨ AI Executive Management Briefing</span>
            <button type="button" onclick="document.getElementById('aiMgmtBriefBox').classList.add('hidden')" class="text-xs text-purple-700 hover:underline cursor-pointer">Hide</button>
        </div>
        <pre id="aiMgmtBriefText" class="whitespace-pre-wrap font-sans text-sm text-purple-950 leading-relaxed"></pre>
    </div>

    <!-- 360° Whole-School Management KPIs (8 Crisp Cards) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
        <div class="bg-indigo-600 text-white rounded-2xl p-4 col-span-2">
            <div class="text-xs font-medium text-indigo-100 uppercase tracking-wider">Overall Completion</div>
            <div class="text-3xl font-extrabold mt-1">{{ number_format($summary['completion_rate'], 1) }}%</div>
            <div class="text-xs text-indigo-100 mt-1">{{ $summary['approved'] }} of {{ $summary['expected'] }} approved ({{ $summary['submitted'] }} uploaded)</div>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 p-4">
            <div class="text-xs font-semibold text-rose-700">Overdue / Late</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ $summary['overdue'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ $summary['due_soon'] }} due in ≤3d</div>
        </div>

        <div class="bg-white rounded-2xl border border-amber-200 p-4">
            <div class="text-xs font-semibold text-amber-800">Escalated (L1–L3)</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">{{ $summary['escalated'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ $summary['review_sla_overdue'] }} SLA breaches</div>
        </div>

        <div class="bg-white rounded-2xl border border-sky-200 p-4">
            <div class="text-xs font-semibold text-sky-700">Waiting Check</div>
            <div class="text-2xl font-bold text-sky-600 mt-1">{{ $summary['pending_review'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ $snap['avg_review_hours'] !== null ? $snap['avg_review_hours'].'h avg check' : 'Active queue' }}</div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <div class="text-xs font-semibold text-slate-600">Teachers & Staff</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $snap['active_teachers'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ $snap['total_active_staff'] }} total active staff</div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <div class="text-xs font-semibold text-slate-600">Classes & Alloc</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $snap['active_classes'] }} / {{ $snap['active_sections'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ $snap['verified_allocations'] }} verified allocs</div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <div class="text-xs font-semibold text-slate-600">Vault & Alerts</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $snap['uploaded_versions'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ $snap['mapped_drive_folders'] }} Drive folders · {{ $snap['alerts_whatsapp'] }} WA</div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- INTERACTIVE 6-CHART VISUAL ANALYTICS GALLERY              -->
    <!-- ========================================================= -->
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Visual School Analytics (Pie, Doughnut, Bar & Trend Charts)</h2>
                <p class="text-xs text-slate-500">Hover over any slice or bar to inspect exact counts and percentages.</p>
            </div>
        </div>

        <!-- Row 1: Status Pie/Doughnut + Wing Stacked Bar -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">1. Submission Status Distribution</h3>
                        <p class="text-xs text-slate-500">Approved vs Waiting Check vs Returned vs Late</p>
                    </div>
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs no-print">
                        <button type="button" id="btnStatusPie" onclick="window.switchStatusChart('pie')"
                                class="px-2.5 py-1 rounded-lg font-semibold bg-white text-slate-900 shadow-2xs cursor-pointer">Pie</button>
                        <button type="button" id="btnStatusDoughnut" onclick="window.switchStatusChart('doughnut')"
                                class="px-2.5 py-1 rounded-lg font-semibold text-slate-600 cursor-pointer">Doughnut</button>
                    </div>
                </div>
                <div class="relative h-64 flex-1">
                    <canvas id="mgmtStatusPieCanvas"></canvas>
                </div>
            </div>

            <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">2. Wing / Stage Compliance (Stacked Bar)</h3>
                        <p class="text-xs text-slate-500">Pre-Primary, Primary, Middle, Secondary & Senior Secondary</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700">5 Wings</span>
                </div>
                <div class="relative h-64 flex-1">
                    <canvas id="mgmtStageBarCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Row 2: Class-wise Bar Chart + Document Category Horizontal Bar Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">3. Class-wise Assigned vs Approved vs Late (Bar Chart)</h3>
                        <p class="text-xs text-slate-500">Compare submission progress across classes</p>
                    </div>
                </div>
                <div class="relative h-64 flex-1">
                    <canvas id="mgmtClassBarCanvas"></canvas>
                </div>
            </div>

            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">4. Document Category Compliance (%)</h3>
                        <p class="text-xs text-slate-500">Horizontal Bar: 00 Overview to 05 Remedial Analysis</p>
                    </div>
                </div>
                <div class="relative h-64 flex-1">
                    <canvas id="mgmtDocHorizCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Row 3: Teacher Performance Bar Chart + Escalation / Monthly Trend -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">5. Teacher Performance & Pending Workload</h3>
                        <p class="text-xs text-slate-500">Approved, Waiting Check & Overdue tasks per teacher</p>
                    </div>
                </div>
                <div class="relative h-64 flex-1">
                    <canvas id="mgmtTeacherBarCanvas"></canvas>
                </div>
            </div>

            <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">6. Escalation Funnel & Monthly Trend</h3>
                        <p class="text-xs text-slate-500">Switch between Escalation Levels (L0–L3) and Monthly Schedule Trend</p>
                    </div>
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs no-print">
                        <button type="button" id="btnTrendEsc" onclick="window.switchTrendChart('escalation')"
                                class="px-2.5 py-1 rounded-lg font-semibold bg-white text-slate-900 shadow-2xs cursor-pointer">Escalations</button>
                        <button type="button" id="btnTrendMonth" onclick="window.switchTrendChart('monthly')"
                                class="px-2.5 py-1 rounded-lg font-semibold text-slate-600 cursor-pointer">Monthly Line</button>
                    </div>
                </div>
                <div class="relative h-64 flex-1">
                    <canvas id="mgmtTrendCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- CUSTOMIZED REPORT MIS BUILDER ("MENTIONED QUERY" + "SELECT & DO") -->
    <!-- ========================================================= -->
    <div id="custom-mis-builder" class="bg-white rounded-2xl border-2 border-indigo-200 shadow-xs overflow-hidden scroll-mt-6">
        <div class="bg-indigo-50/70 px-5 sm:px-6 py-4 border-b border-indigo-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-indigo-600 text-white">Custom MIS Engine</span>
                <h2 class="text-lg font-bold text-slate-900 mt-1">Customized Management MIS — "Mentioned Query" or "Select & Do"</h2>
                <p class="text-xs text-slate-600">Type a requirement in plain English OR pick filters, grouping, chart style, and exact columns below.</p>
            </div>
            <div class="flex items-center gap-2 no-print">
                <a href="{{ route('management.mis', ['academic_year_id' => $params['academic_year_id'] ?? null]) }}#custom-mis-builder"
                   class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-xs font-semibold text-slate-700">
                    Reset Custom MIS
                </a>
                <a href="{{ route('management.mis.export', request()->query()) }}"
                   class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">
                    Download Custom MIS (CSV)
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('management.mis') }}#custom-mis-results" id="customMisForm" class="p-5 sm:p-6 space-y-5 no-print">
            <!-- Mode A: "Mentioned Query" Plain-English Query Box -->
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 space-y-3">
                <label for="mentioned_query" class="block text-sm font-bold text-slate-900">
                    Option 1 — Mention Your Query (Plain-English MIS Search)
                </label>
                <div class="flex flex-col sm:flex-row gap-2.5">
                    <input type="text" id="mentioned_query" name="mentioned_query"
                           value="{{ $params['mentioned_query'] ?? '' }}"
                           placeholder="e.g. Overdue Class X Mathematics tasks, or Teacher wise completion below 80% pie chart, or Primary wing by class..."
                           class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold cursor-pointer shrink-0">
                        Run Query
                    </button>
                </div>

                <!-- 1-Tap Sample Queries -->
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-slate-500 font-medium mr-1">Try 1-Tap Queries:</span>
                    @php
                        $sampleQueries = [
                            'Teacher wise most overdue bar chart',
                            'Wing wise summary pie chart',
                            'Overdue Class X Mathematics',
                            'Lesson plans waiting check by class',
                            'Teachers with completion below 80%',
                            'Escalated L1 tasks by reviewer',
                        ];
                    @endphp
                    @foreach($sampleQueries as $sq)
                        <button type="button"
                                onclick="document.getElementById('mentioned_query').value=@js($sq);document.getElementById('customMisForm').submit();"
                                class="px-2.5 py-1 rounded-lg bg-white hover:bg-indigo-50 border border-slate-300 hover:border-indigo-400 text-slate-700 font-medium cursor-pointer transition">
                            "{{ $sq }}"
                        </button>
                    @endforeach
                </div>

                @if(!empty($customMis['interpreted']))
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-xs font-bold text-emerald-800">Interpreted Query Rules:</span>
                        @foreach($customMis['interpreted'] as $ruleChip)
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-900 text-xs font-semibold">{{ $ruleChip }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Built-in & User-Saved 1-Click Presets -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-bold text-slate-700">Quick MIS Templates:</span>
                <a href="{{ route('management.mis', ['academic_year_id' => $params['academic_year_id'] ?? null, 'group_by' => 'stage', 'chart_type' => 'bar']) }}#custom-mis-results"
                   class="px-3 py-1.5 rounded-xl border border-slate-300 hover:border-indigo-400 bg-white font-semibold text-slate-700">Wing-wise MIS</a>
                <a href="{{ route('management.mis', ['academic_year_id' => $params['academic_year_id'] ?? null, 'group_by' => 'teacher', 'sort_by' => 'overdue', 'sort_dir' => 'desc', 'chart_type' => 'bar']) }}#custom-mis-results"
                   class="px-3 py-1.5 rounded-xl border border-slate-300 hover:border-indigo-400 bg-white font-semibold text-slate-700">Top Late Teachers</a>
                <a href="{{ route('management.mis', ['academic_year_id' => $params['academic_year_id'] ?? null, 'group_by' => 'document_type', 'chart_type' => 'horizontalBar']) }}#custom-mis-results"
                   class="px-3 py-1.5 rounded-xl border border-slate-300 hover:border-indigo-400 bg-white font-semibold text-slate-700">Doc Category Audit</a>
                <a href="{{ route('management.mis', ['academic_year_id' => $params['academic_year_id'] ?? null, 'group_by' => 'status', 'chart_type' => 'doughnut']) }}#custom-mis-results"
                   class="px-3 py-1.5 rounded-xl border border-slate-300 hover:border-indigo-400 bg-white font-semibold text-slate-700">Status Doughnut MIS</a>
                <a href="{{ route('management.mis', ['academic_year_id' => $params['academic_year_id'] ?? null, 'group_by' => 'tasks', 'status' => 'OVERDUE']) }}#custom-mis-results"
                   class="px-3 py-1.5 rounded-xl border border-rose-300 bg-rose-50 font-semibold text-rose-800">All Late Tasks Register</a>

                @foreach($savedPresets as $preset)
                    <span class="inline-flex items-center rounded-xl bg-amber-50 border border-amber-300 px-2.5 py-1 font-semibold text-amber-950 gap-1.5">
                        <a href="{{ route('management.mis', $preset['params'] ?? []) }}#custom-mis-results" class="hover:underline">★ {{ $preset['name'] }}</a>
                    </span>
                @endforeach
            </div>

            <!-- Mode B: "Select & Do" Multi-Dimensional Builder -->
            <div class="space-y-4 pt-2 border-t border-slate-200">
                <div class="text-sm font-bold text-slate-900">Option 2 — Select & Do (Custom Grouping, Filters, Chart & Columns)</div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 text-sm">
                    <div>
                        <label class="block text-xs font-bold text-indigo-900 mb-1">Group Report By (Pivot)</label>
                        <select name="group_by" class="w-full rounded-xl border-2 border-indigo-300 bg-indigo-50/40 px-3 py-2 font-semibold text-slate-900">
                            @foreach($groupModes as $gKey => $gLabel)
                                <option value="{{ $gKey }}" @selected($customMis['group_by'] === $gKey)>{{ $gLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-indigo-900 mb-1">Chart Style</label>
                        <select name="chart_type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-medium">
                            <option value="bar" @selected($customMis['chart_type'] === 'bar')>Vertical Bar Chart</option>
                            <option value="horizontalBar" @selected($customMis['chart_type'] === 'horizontalBar')>Horizontal Bar Chart</option>
                            <option value="pie" @selected($customMis['chart_type'] === 'pie')>Pie Chart</option>
                            <option value="doughnut" @selected($customMis['chart_type'] === 'doughnut')>Doughnut Chart</option>
                            <option value="line" @selected($customMis['chart_type'] === 'line')>Line / Trend Chart</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Session</label>
                        <select name="academic_year_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            @foreach($academicYears as $yr)
                                <option value="{{ $yr->id }}" @selected(($params['academic_year_id'] ?? '') == $yr->id)>{{ $yr->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">School Wing / Stage</label>
                        <select name="stage" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <option value="">All Wings</option>
                            @foreach($stageLabels as $stKey => $stLabel)
                                <option value="{{ $stKey }}" @selected(($params['stage'] ?? '') === $stKey)>{{ $stLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Class</label>
                        <select name="class_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <option value="">All Classes</option>
                            @foreach($classes as $cls)
                                <option value="{{ $cls->id }}" @selected(($params['class_id'] ?? '') == $cls->id)>{{ $cls->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Section</label>
                        <select name="section_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <option value="">All Sections</option>
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}" @selected(($params['section_id'] ?? '') == $sec->id)>
                                    {{ $classes->firstWhere('id', $sec->class_id)?->name }} - {{ $sec->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Subject</label>
                        <select name="subject_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}" @selected(($params['subject_id'] ?? '') == $sub->id)>{{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Teacher</label>
                        <select name="teacher_user_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <option value="">All Teachers</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}" @selected(($params['teacher_user_id'] ?? '') == $t->id)>{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Document Type</label>
                        <select name="document_type_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <option value="">All Document Types</option>
                            @foreach($documentTypes as $dt)
                                <option value="{{ $dt->id }}" @selected(($params['document_type_id'] ?? '') == $dt->id)>{{ $dt->folder_code }} · {{ $dt->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Submission Status</label>
                        <select name="status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <option value="">All Statuses</option>
                            <option value="APPROVED" @selected(($params['status'] ?? '') === 'APPROVED')>Approved</option>
                            <option value="PENDING_REVIEW" @selected(($params['status'] ?? '') === 'PENDING_REVIEW')>Waiting Coordinator Check</option>
                            <option value="RETURNED_FOR_CORRECTION" @selected(($params['status'] ?? '') === 'RETURNED_FOR_CORRECTION')>Sent Back for Correction</option>
                            <option value="NOT_STARTED" @selected(($params['status'] ?? '') === 'NOT_STARTED')>Not Uploaded Yet</option>
                            <option value="OVERDUE" @selected(($params['status'] ?? '') === 'OVERDUE')>Overdue / Late</option>
                            <option value="ESCALATED" @selected(($params['status'] ?? '') === 'ESCALATED')>Escalated (L1–L3)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Due Date From – To</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            <input type="date" name="due_from" value="{{ $params['due_from'] ?? '' }}" class="rounded-xl border border-slate-300 bg-white px-2 py-2 text-xs">
                            <input type="date" name="due_to" value="{{ $params['due_to'] ?? '' }}" class="rounded-xl border border-slate-300 bg-white px-2 py-2 text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Completion % Filter (Grouped)</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            <select name="completion_op" class="rounded-xl border border-slate-300 bg-white px-2 py-2 text-xs">
                                <option value="">Any %</option>
                                <option value="lt" @selected(($params['completion_op'] ?? '') === 'lt')>Below (&lt;)</option>
                                <option value="lte" @selected(($params['completion_op'] ?? '') === 'lte')>At most (&le;)</option>
                                <option value="gt" @selected(($params['completion_op'] ?? '') === 'gt')>Above (&gt;)</option>
                                <option value="gte" @selected(($params['completion_op'] ?? '') === 'gte')>At least (&ge;)</option>
                                <option value="eq" @selected(($params['completion_op'] ?? '') === 'eq')>Exact (=)</option>
                            </select>
                            <input type="number" min="0" max="100" name="completion_val" value="{{ $params['completion_val'] ?? '' }}"
                                   placeholder="e.g. 75" class="rounded-xl border border-slate-300 bg-white px-2.5 py-2 text-xs">
                        </div>
                    </div>
                </div>

                <!-- Column Picker for Detailed Task Register + Sort Controls -->
                <details class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                    <summary class="text-xs font-bold text-slate-700 cursor-pointer flex items-center justify-between">
                        <span>Customize Table & Excel Columns ({{ count($customMis['selected_columns']) }} selected) + Sort Order</span>
                        <span class="text-indigo-600">Expand / Collapse</span>
                    </summary>
                    <div class="mt-3 pt-3 border-t border-slate-200 space-y-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 text-xs">
                            @foreach($availableColumns as $colKey => $colLabel)
                                <label class="inline-flex items-center gap-2 rounded-lg bg-white border border-slate-200 px-2.5 py-1.5 cursor-pointer">
                                    <input type="checkbox" name="columns[]" value="{{ $colKey }}"
                                           @checked(in_array($colKey, $customMis['selected_columns'], true))
                                           class="rounded border-slate-300 text-indigo-600">
                                    <span class="truncate">{{ $colLabel }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-2 text-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="font-semibold text-slate-600">Sort By:</span>
                                <select name="sort_by" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                    <option value="completion_rate" @selected(($params['sort_by'] ?? '') === 'completion_rate')>Completion %</option>
                                    <option value="overdue" @selected(($params['sort_by'] ?? '') === 'overdue')>Overdue Count</option>
                                    <option value="expected" @selected(($params['sort_by'] ?? '') === 'expected')>Assigned Count</option>
                                    <option value="approved" @selected(($params['sort_by'] ?? '') === 'approved')>Approved Count</option>
                                    <option value="due_date" @selected(($params['sort_by'] ?? '') === 'due_date')>Due Date</option>
                                    <option value="overdue_days" @selected(($params['sort_by'] ?? '') === 'overdue_days')>Overdue Days</option>
                                </select>
                                <select name="sort_dir" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                    <option value="desc" @selected(($params['sort_dir'] ?? 'desc') === 'desc')>High → Low</option>
                                    <option value="asc" @selected(($params['sort_dir'] ?? '') === 'asc')>Low → High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </details>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-xs cursor-pointer">
                        Generate Custom MIS & Chart
                    </button>
                </div>
            </div>
        </form>

        <!-- Save Current MIS Preset Bar -->
        @if($currentQueryString !== '')
            <div class="px-5 sm:px-6 py-3 bg-amber-50/70 border-t border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 no-print">
                <form method="POST" action="{{ route('management.mis.presets.store') }}" class="flex flex-wrap items-center gap-2 text-xs w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="query_payload" value="{{ $currentQueryString }}">
                    <span class="font-bold text-amber-950">Save This Custom MIS View:</span>
                    <input type="text" name="preset_name" required maxlength="100" placeholder="e.g. Monday Principal Review"
                           class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs">
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold cursor-pointer">
                        ★ Pin Preset
                    </button>
                </form>

                @if(!empty($savedPresets))
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        @foreach($savedPresets as $preset)
                            <form method="POST" action="{{ route('management.mis.presets.destroy', $preset['id']) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Delete preset" class="px-2 py-1 rounded bg-white border border-amber-300 text-rose-700 hover:bg-rose-50 cursor-pointer">
                                    Delete "{{ $preset['name'] }}" ×
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================================= -->
        <!-- CUSTOM MIS OUTPUT: DYNAMIC CHART + RECONCILED DATA TABLE  -->
        <!-- ========================================================= -->
        <div id="custom-mis-results" class="p-5 sm:p-6 border-t border-slate-200 space-y-5 bg-white">
            <!-- Filtered KPIs -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3.5">
                    <div class="text-xs text-slate-500">Matched Expected</div>
                    <div class="text-xl font-bold text-slate-900">{{ $customMis['summary']['expected'] }}</div>
                </div>
                <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3.5">
                    <div class="text-xs text-emerald-800">Approved</div>
                    <div class="text-xl font-bold text-emerald-700">{{ $customMis['summary']['approved'] }}</div>
                </div>
                <div class="rounded-xl bg-sky-50 border border-sky-200 p-3.5">
                    <div class="text-xs text-sky-800">Waiting Check</div>
                    <div class="text-xl font-bold text-sky-700">{{ $customMis['summary']['pending_review'] }}</div>
                </div>
                <div class="rounded-xl bg-rose-50 border border-rose-200 p-3.5">
                    <div class="text-xs text-rose-800">Overdue / Late</div>
                    <div class="text-xl font-bold text-rose-700">{{ $customMis['summary']['overdue'] }}</div>
                </div>
                <div class="rounded-xl bg-indigo-600 text-white p-3.5 col-span-2 sm:col-span-1">
                    <div class="text-xs text-indigo-100">Filtered Completion</div>
                    <div class="text-xl font-bold">{{ number_format($customMis['summary']['completion_rate'], 1) }}%</div>
                </div>
            </div>

            <!-- Dynamic Custom MIS Chart -->
            @if(!empty($customMis['chart']['labels']))
                <div class="rounded-2xl border border-slate-200 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">{{ $customMis['chart']['title'] }}</h3>
                            <p class="text-xs text-slate-500">Live chart generated from your custom MIS selection ({{ count($customMis['rows']) }} result rows)</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs no-print">
                            @foreach(['bar' => 'Bar', 'horizontalBar' => 'Horiz Bar', 'pie' => 'Pie', 'doughnut' => 'Doughnut', 'line' => 'Line'] as $cKey => $cName)
                                <button type="button" onclick="window.switchCustomMisChart(@js($cKey))"
                                        class="px-2.5 py-1 rounded-lg font-semibold cursor-pointer custom-chart-btn {{ $customMis['chart_type'] === $cKey ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600' }}"
                                        data-chart-key="{{ $cKey }}">
                                    {{ $cName }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="relative h-72">
                        <canvas id="customMisResultCanvas"></canvas>
                    </div>
                </div>
            @endif

            <!-- Dynamic Custom MIS Table -->
            <div class="rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <div class="font-bold text-sm text-slate-900">
                        {{ $groupModes[$customMis['group_by']] ?? 'Custom MIS Results' }}
                        <span class="text-xs font-normal text-slate-500 ml-1">({{ count($customMis['rows']) }} rows)</span>
                    </div>
                    <a href="{{ route('management.mis.export', request()->query()) }}"
                       class="text-xs font-semibold text-indigo-700 hover:underline no-print">
                        Export These Exact Rows to Excel (CSV) →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    @if($customMis['mode'] === 'tasks')
                        <table class="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                            <thead class="bg-slate-50 text-[11px] uppercase text-slate-500">
                                <tr>
                                    @foreach($customMis['selected_columns'] as $colKey)
                                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">
                                            {{ $availableColumns[$colKey] ?? $colKey }}
                                        </th>
                                    @endforeach
                                    <th class="px-4 py-3 text-right font-semibold no-print">Open</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($customMis['rows'] as $row)
                                    <tr class="hover:bg-slate-50/80">
                                        @foreach($customMis['selected_columns'] as $colKey)
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                @if($colKey === 'is_overdue')
                                                    @if(!empty($row['is_overdue']))
                                                        <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-xs font-bold">LATE</span>
                                                    @else
                                                        <span class="text-slate-400">No</span>
                                                    @endif
                                                @elseif($colKey === 'escalation_level')
                                                    <span class="{{ (int)($row['escalation_level'] ?? 0) > 0 ? 'px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-bold text-xs' : 'text-slate-500' }}">
                                                        L{{ (int) ($row['escalation_level'] ?? 0) }}
                                                    </span>
                                                @elseif($colKey === 'workflow_status_label')
                                                    <span class="font-semibold {{ ($row['workflow_status'] ?? '') === 'APPROVED' ? 'text-emerald-700' : (($row['is_overdue'] ?? false) ? 'text-rose-700' : 'text-slate-800') }}">
                                                        {{ $row['workflow_status_label'] ?? '-' }}
                                                    </span>
                                                @else
                                                    {{ $row[$colKey] ?: '-' }}
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-4 py-2.5 text-right no-print">
                                            <a href="{{ route('tasks.show', $row['task_id']) }}" class="text-indigo-600 font-semibold hover:underline">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($customMis['selected_columns']) + 1 }}" class="px-5 py-8 text-center text-slate-500">
                                            No tasks match your custom query filters.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3 text-left">{{ $groupModes[$customMis['group_by']] ?? 'Group' }}</th>
                                    <th class="px-4 py-3 text-right">Assigned</th>
                                    <th class="px-4 py-3 text-right">Submitted</th>
                                    <th class="px-4 py-3 text-right">Approved</th>
                                    <th class="px-4 py-3 text-right">Waiting Check</th>
                                    <th class="px-4 py-3 text-right">Returned</th>
                                    <th class="px-4 py-3 text-right">Pending</th>
                                    <th class="px-4 py-3 text-right">Late</th>
                                    <th class="px-4 py-3 text-right">Escalated</th>
                                    <th class="px-5 py-3 text-right">Completion %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($customMis['rows'] as $row)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-5 py-3">
                                            <div class="font-bold text-slate-900">{{ $row['label'] }}</div>
                                            @if(!empty($row['sub_label']))
                                                <div class="text-xs text-slate-500">{{ $row['sub_label'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold">{{ $row['expected'] }}</td>
                                        <td class="px-4 py-3 text-right">{{ $row['submitted'] }}</td>
                                        <td class="px-4 py-3 text-right text-emerald-700 font-bold">{{ $row['approved'] }}</td>
                                        <td class="px-4 py-3 text-right text-sky-700">{{ $row['pending_review'] }}</td>
                                        <td class="px-4 py-3 text-right text-amber-700">{{ $row['returned'] }}</td>
                                        <td class="px-4 py-3 text-right text-slate-600">{{ $row['pending_submission'] }}</td>
                                        <td class="px-4 py-3 text-right text-rose-700 font-bold">{{ $row['overdue'] }}</td>
                                        <td class="px-4 py-3 text-right text-amber-800">{{ $row['escalated'] }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <div class="w-16 h-2 rounded-full bg-slate-200 overflow-hidden hidden sm:block">
                                                    <div class="h-full rounded-full {{ $row['completion_rate'] >= 75 ? 'bg-emerald-500' : ($row['completion_rate'] >= 40 ? 'bg-amber-500' : 'bg-rose-500') }}"
                                                         style="width: {{ min(100, $row['completion_rate']) }}%"></div>
                                                </div>
                                                <span class="font-bold text-slate-900">{{ number_format($row['completion_rate'], 1) }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-5 py-8 text-center text-slate-500">
                                            No summary groups match your custom filter criteria.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Initializer Script -->
    <script>
        (function () {
            const statusDist = @js($charts['status_distribution']);
            const stageRows = @js($charts['stage_breakdown']);
            const classRows = @js($charts['class_breakdown']);
            const docRows = @js($charts['document_type_breakdown']);
            const teacherRows = @js($charts['teacher_performance']);
            const escFunnel = @js($charts['escalation_funnel']);
            const monthRows = @js($charts['timeline_trend']);
            const customChartRaw = @js($customMis['chart']);

            window.switchStatusChart = function (type) {
                if (!window.renderExcelliaChart) return;
                document.getElementById('btnStatusPie')?.classList.toggle('bg-white', type === 'pie');
                document.getElementById('btnStatusPie')?.classList.toggle('shadow-2xs', type === 'pie');
                document.getElementById('btnStatusDoughnut')?.classList.toggle('bg-white', type === 'doughnut');
                document.getElementById('btnStatusDoughnut')?.classList.toggle('shadow-2xs', type === 'doughnut');

                window.renderExcelliaChart('mgmtStatusPieCanvas', {
                    type: type,
                    data: {
                        labels: statusDist.labels,
                        datasets: [{
                            data: statusDist.counts,
                            backgroundColor: statusDist.colors,
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: type === 'doughnut' ? '58%' : 0,
                        plugins: { legend: { position: 'right' } }
                    }
                });
            };

            window.switchTrendChart = function (mode) {
                if (!window.renderExcelliaChart) return;
                document.getElementById('btnTrendEsc')?.classList.toggle('bg-white', mode === 'escalation');
                document.getElementById('btnTrendMonth')?.classList.toggle('bg-white', mode === 'monthly');

                if (mode === 'escalation') {
                    window.renderExcelliaChart('mgmtTrendCanvas', {
                        type: 'bar',
                        data: {
                            labels: escFunnel.labels,
                            datasets: [{
                                label: 'Tasks',
                                data: escFunnel.counts,
                                backgroundColor: escFunnel.colors,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                        }
                    });
                } else {
                    window.renderExcelliaChart('mgmtTrendCanvas', {
                        type: 'line',
                        data: {
                            labels: monthRows.map(r => r.label),
                            datasets: [
                                { label: 'Assigned', data: monthRows.map(r => r.expected), borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.1)', fill: true, tension: 0.3 },
                                { label: 'Approved', data: monthRows.map(r => r.approved), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: false, tension: 0.3 },
                                { label: 'Overdue', data: monthRows.map(r => r.overdue), borderColor: '#f43f5e', backgroundColor: 'rgba(244,63,94,0.1)', fill: false, tension: 0.3 }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                        }
                    });
                }
            };

            window.switchCustomMisChart = function (newType) {
                if (!window.renderExcelliaChart || !customChartRaw || !customChartRaw.labels?.length) return;
                document.querySelectorAll('.custom-chart-btn').forEach(btn => {
                    const active = btn.getAttribute('data-chart-key') === newType;
                    btn.classList.toggle('bg-white', active);
                    btn.classList.toggle('text-slate-900', active);
                    btn.classList.toggle('shadow-2xs', active);
                });

                const isCircular = (newType === 'pie' || newType === 'doughnut');
                const palette = ['#4f46e5', '#10b981', '#0ea5e9', '#f59e0b', '#f43f5e', '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#64748b'];

                let datasets = customChartRaw.datasets;
                if (isCircular && datasets.length > 1) {
                    // Sum or show primary total across labels for Pie/Doughnut
                    const totals = customChartRaw.labels.map((_, i) =>
                        datasets.reduce((acc, ds) => acc + (Number(ds.data[i]) || 0), 0)
                    );
                    datasets = [{
                        label: 'Total Tasks',
                        data: totals,
                        backgroundColor: customChartRaw.labels.map((_, i) => palette[i % palette.length]),
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }];
                }

                window.renderExcelliaChart('customMisResultCanvas', {
                    type: newType === 'horizontalBar' ? 'bar' : newType,
                    data: {
                        labels: customChartRaw.labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: newType === 'horizontalBar' ? 'y' : 'x',
                        cutout: newType === 'doughnut' ? '55%' : 0,
                        scales: isCircular ? {} : {
                            x: { beginAtZero: true, ticks: { precision: 0 } },
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            };

            function bootAllManagementCharts() {
                if (!window.renderExcelliaChart) return;

                // 1. Status Pie
                window.switchStatusChart('pie');

                // 2. Wing Stacked Bar
                window.renderExcelliaChart('mgmtStageBarCanvas', {
                    type: 'bar',
                    data: {
                        labels: stageRows.map(r => r.label.replace(/\s*\(.*\)/, '')),
                        datasets: [
                            { label: 'Approved', data: stageRows.map(r => r.approved), backgroundColor: '#10b981' },
                            { label: 'Waiting Check', data: stageRows.map(r => r.pending_review), backgroundColor: '#0ea5e9' },
                            { label: 'Sent Back', data: stageRows.map(r => r.returned), backgroundColor: '#f59e0b' },
                            { label: 'Late', data: stageRows.map(r => r.overdue), backgroundColor: '#f43f5e' },
                            { label: 'Pending', data: stageRows.map(r => r.pending_submission), backgroundColor: '#cbd5e1' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { stacked: true },
                            y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });

                // 3. Class-wise Grouped Bar
                window.renderExcelliaChart('mgmtClassBarCanvas', {
                    type: 'bar',
                    data: {
                        labels: classRows.map(r => r.label),
                        datasets: [
                            { label: 'Assigned', data: classRows.map(r => r.expected), backgroundColor: '#6366f1', borderRadius: 4 },
                            { label: 'Approved', data: classRows.map(r => r.approved), backgroundColor: '#10b981', borderRadius: 4 },
                            { label: 'Overdue', data: classRows.map(r => r.overdue), backgroundColor: '#f43f5e', borderRadius: 4 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });

                // 4. Document Type Horizontal Bar (% Completion)
                window.renderExcelliaChart('mgmtDocHorizCanvas', {
                    type: 'bar',
                    data: {
                        labels: docRows.map(r => r.label.length > 26 ? r.label.substring(0, 26) + '…' : r.label),
                        datasets: [{
                            label: 'Completion %',
                            data: docRows.map(r => r.completion_rate),
                            backgroundColor: '#8b5cf6',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: { x: { beginAtZero: true, max: 100 } }
                    }
                });

                // 5. Teacher Performance Bar
                window.renderExcelliaChart('mgmtTeacherBarCanvas', {
                    type: 'bar',
                    data: {
                        labels: teacherRows.map(r => r.label),
                        datasets: [
                            { label: 'Approved', data: teacherRows.map(r => r.approved), backgroundColor: '#10b981', borderRadius: 4 },
                            { label: 'Waiting Check', data: teacherRows.map(r => r.pending_review), backgroundColor: '#0ea5e9', borderRadius: 4 },
                            { label: 'Late', data: teacherRows.map(r => r.overdue), backgroundColor: '#f43f5e', borderRadius: 4 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });

                // 6. Escalation Funnel
                window.switchTrendChart('escalation');

                // 7. Dynamic Custom MIS Result Chart
                window.switchCustomMisChart(@js($customMis['chart_type']));
            }

            if (document.readyState === 'complete') bootAllManagementCharts();
            else window.addEventListener('load', bootAllManagementCharts);
        })();
    </script>
@endsection
