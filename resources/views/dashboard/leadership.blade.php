@extends('layouts.app')

@section('title', 'School Overview')
@section('heading', 'School Submission Overview')
@section('subheading', 'Track submitted files, pending checks, late items, and school setup.')

@section('content')
    @php
        $user = auth()->user();
        $isSuperAdmin = $user?->primaryRole?->key === \App\Models\Role::SUPER_ADMIN;
        $emailOn = (bool) \App\Models\SystemSetting::getValue('email_notifications_enabled', false);
        $waOn = (bool) \App\Models\SystemSetting::getValue('whatsapp_notifications_enabled', false);
        $waProvider = strtoupper((string) \App\Models\SystemSetting::getValue('whatsapp_provider', 'sandbox'));
        $aiOn = (bool) \App\Models\SystemSetting::getValue('ai_openrouter_enabled', false);
        $driveLive = (bool) \App\Models\SystemSetting::getValue('google_drive_live_enabled', false);
    @endphp

    <!-- Super Admin Quick Masters & Integrations Hub -->
    @if($isSuperAdmin)
        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Super Admin Control Center — Masters & Integrations</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Manage all school masters, mappings, Email, Whatify WhatsApp, Google Drive, and OpenRouter AI.</p>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="px-2.5 py-1 rounded-full font-semibold {{ $emailOn ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">Email: {{ $emailOn ? 'ON' : 'OFF' }}</span>
                    <span class="px-2.5 py-1 rounded-full font-semibold {{ $waOn ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">WhatsApp ({{ $waProvider }}): {{ $waOn ? 'ON' : 'OFF' }}</span>
                    <span class="px-2.5 py-1 rounded-full font-semibold {{ $driveLive ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700' }}">Drive: {{ $driveLive ? 'Live API' : 'Local Vault' }}</span>
                    <span class="px-2.5 py-1 rounded-full font-semibold {{ $aiOn ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-500' }}">AI Helper: {{ $aiOn ? 'ON' : 'OFF' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
                <a href="{{ route('admin.users') }}" class="rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 p-3.5 transition">
                    <div class="font-bold text-slate-900 text-sm">User Master</div>
                    <div class="text-slate-500 mt-0.5">Staff & Roles</div>
                </a>
                <a href="{{ route('admin.roles') }}" class="rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 p-3.5 transition">
                    <div class="font-bold text-slate-900 text-sm">Roles & Scope</div>
                    <div class="text-slate-500 mt-0.5">Permissions & Wings</div>
                </a>
                <a href="{{ route('admin.academics') }}" class="rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 p-3.5 transition">
                    <div class="font-bold text-slate-900 text-sm">Academic Master</div>
                    <div class="text-slate-500 mt-0.5">Classes, Subjects, Map</div>
                </a>
                <a href="{{ route('admin.document-types') }}" class="rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 p-3.5 transition">
                    <div class="font-bold text-slate-900 text-sm">Document Types</div>
                    <div class="text-slate-500 mt-0.5">00–05+ Categories</div>
                </a>
                <a href="{{ route('admin.drive-mappings') }}" class="rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 p-3.5 transition">
                    <div class="font-bold text-slate-900 text-sm">Drive Mapping</div>
                    <div class="text-slate-500 mt-0.5">Folder Sync & CRUD</div>
                </a>
                <a href="{{ route('admin.settings') }}" class="rounded-xl border border-indigo-200 bg-indigo-50/50 hover:border-indigo-500 p-3.5 transition">
                    <div class="font-bold text-indigo-900 text-sm">Integrations</div>
                    <div class="text-indigo-700 mt-0.5">Whatify, AI, Email, Drive</div>
                </a>
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
            <label for="academic_year_id" class="text-sm font-medium text-slate-600">Session:</label>
            <select id="academic_year_id" name="academic_year_id" onchange="this.form.submit()"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium">
                @foreach($academicYears as $yr)
                    <option value="{{ $yr->id }}" @selected($selectedYearId == $yr->id)>
                        {{ $yr->name }}
                    </option>
                @endforeach
            </select>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('management.mis', ['academic_year_id' => $selectedYearId]) }}"
               class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-2xs">
                Executive Charts & Custom MIS →
            </a>
            @if(auth()->user()->hasPermission('tasks.create'))
                <a href="{{ route('tasks.manage') }}" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-2xs">
                    + Assign Tasks
                </a>
            @endif
            <a href="{{ route('reports.index') }}" class="px-4 py-2.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold">
                View Reports
            </a>
        </div>
    </div>

    <!-- 4 Clear Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="text-sm font-medium text-slate-500">Total Assigned</div>
            <div class="text-3xl font-bold text-slate-900 mt-1">{{ $summary['expected'] }}</div>
            <div class="text-xs text-emerald-700 font-medium mt-1">{{ $summary['approved'] }} approved · {{ $summary['returned'] }} returned</div>
        </div>

        <a href="{{ route('reviews.queue') }}" class="bg-white rounded-2xl border border-sky-200 p-5 hover:border-sky-400 transition">
            <div class="text-sm font-medium text-sky-700">Files Waiting for Check</div>
            <div class="text-3xl font-bold text-sky-600 mt-1">{{ $summary['pending_review'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Tap to review & approve</div>
        </a>

        <a href="{{ route('overdue.monitor') }}" class="bg-white rounded-2xl border border-rose-200 p-5 hover:border-rose-400 transition">
            <div class="text-sm font-medium text-rose-700">Late Submissions</div>
            <div class="text-3xl font-bold text-rose-600 mt-1">{{ $summary['overdue'] }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $summary['escalated'] }} escalated for attention</div>
        </a>

        <div class="bg-indigo-600 rounded-2xl p-5 text-white">
            <div class="text-sm font-medium text-indigo-100">Completion Rate</div>
            <div class="text-3xl font-bold mt-1">{{ number_format($summary['completion_rate'], 0) }}%</div>
            <div class="text-xs text-indigo-100 mt-1">{{ $summary['submitted'] }} of {{ $summary['expected'] }} submitted</div>
        </div>
    </div>

    <!-- Visual Charts Snapshot right on Leadership & Management Dashboard -->
    @if(!empty($executiveOverview['charts']))
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Submission Status Pie</h2>
                        <p class="text-xs text-slate-500">Live breakdown of {{ $summary['expected'] }} assigned tasks</p>
                    </div>
                    <a href="{{ route('management.mis', ['academic_year_id' => $selectedYearId]) }}" class="text-xs font-semibold text-indigo-600 hover:underline">All 6 Charts →</a>
                </div>
                <div class="relative h-56 flex items-center justify-center">
                    <canvas id="dashStatusPieChart"></canvas>
                </div>
            </div>

            <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Progress by School Wing (Bar Chart)</h2>
                        <p class="text-xs text-slate-500">Approved vs Waiting Check vs Late across stages</p>
                    </div>
                    <a href="{{ route('management.mis', ['academic_year_id' => $selectedYearId, 'group_by' => 'stage']) }}#custom-mis-builder"
                       class="text-xs font-semibold text-emerald-700 hover:underline">Custom MIS Query →</a>
                </div>
                <div class="relative h-56">
                    <canvas id="dashStageBarChart"></canvas>
                </div>
            </div>
        </div>

        <script>
            (function () {
                function bootDashCharts() {
                    if (!window.renderExcelliaChart) return;
                    const statusDist = @js($executiveOverview['charts']['status_distribution']);
                    const stageRows = @js($executiveOverview['charts']['stage_breakdown']);

                    window.renderExcelliaChart('dashStatusPieChart', {
                        type: 'doughnut',
                        data: {
                            labels: statusDist.labels,
                            datasets: [{
                                data: statusDist.counts,
                                backgroundColor: statusDist.colors,
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '58%',
                            plugins: { legend: { position: 'right' } }
                        }
                    });

                    window.renderExcelliaChart('dashStageBarChart', {
                        type: 'bar',
                        data: {
                            labels: stageRows.map(r => r.label.replace(/\s*\(.*\)/, '')),
                            datasets: [
                                { label: 'Approved', data: stageRows.map(r => r.approved), backgroundColor: '#10b981', borderRadius: 6 },
                                { label: 'Waiting Check', data: stageRows.map(r => r.pending_review), backgroundColor: '#0ea5e9', borderRadius: 6 },
                                { label: 'Late', data: stageRows.map(r => r.overdue), backgroundColor: '#f43f5e', borderRadius: 6 }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                        }
                    });
                }
                if (document.readyState === 'complete') bootDashCharts();
                else window.addEventListener('load', bootDashCharts);
            })();
        </script>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Files Waiting for Check -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Files Waiting for Check</h2>
                <a href="{{ route('reviews.queue') }}" class="text-sm font-semibold text-indigo-600 hover:underline">See all</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($pendingReviewTasks as $task)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">{{ $task->title }}</div>
                            <div class="text-xs sm:text-sm text-slate-500 mt-0.5">
                                {{ $task->assignedTeacher?->full_name }} ·
                                {{ $task->teachingAllocation?->schoolClass?->name }}-{{ $task->teachingAllocation?->section?->name }} ·
                                {{ $task->teachingAllocation?->subject?->name }}
                            </div>
                        </div>
                        @if($task->submission)
                            <a href="{{ route('reviews.show', $task->submission) }}"
                               class="w-full sm:w-auto text-center px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shrink-0">
                                Check File
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">All caught up! No files waiting for check.</div>
                @endforelse
            </div>
        </div>

        <!-- Late Submissions -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Late Submissions</h2>
                <a href="{{ route('overdue.monitor') }}" class="text-sm font-semibold text-rose-600 hover:underline">Send reminders</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($overdueAndEscalated as $task)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">{{ $task->title }}</div>
                            <div class="text-xs sm:text-sm text-slate-500 mt-0.5">
                                {{ $task->assignedTeacher?->full_name }} ·
                                {{ $task->teachingAllocation?->schoolClass?->name }}-{{ $task->teachingAllocation?->section?->name }} ·
                                Due {{ $task->due_at?->format('d M Y') }}
                            </div>
                        </div>
                        <div class="shrink-0">
                            @include('partials.status-badge', ['task' => $task])
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">No late submissions right now.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Simple Progress by Document Category -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">Progress by Document Type</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Document Type</th>
                        <th class="px-4 py-3 text-right">Assigned</th>
                        <th class="px-4 py-3 text-right">Approved</th>
                        <th class="px-4 py-3 text-right">Waiting Check</th>
                        <th class="px-4 py-3 text-right">Late</th>
                        <th class="px-4 py-3 text-right">Completed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($byDocumentType as $row)
                        <tr>
                            <td class="px-4 py-3.5 font-medium text-slate-900 whitespace-nowrap">{{ $row['document_type'] }}</td>
                            <td class="px-4 py-3.5 text-right">{{ $row['expected'] }}</td>
                            <td class="px-4 py-3.5 text-right text-emerald-700 font-semibold">{{ $row['approved'] }}</td>
                            <td class="px-4 py-3.5 text-right text-sky-700">{{ $row['pending_review'] }}</td>
                            <td class="px-4 py-3.5 text-right text-rose-700 font-semibold">{{ $row['overdue'] }}</td>
                            <td class="px-4 py-3.5 text-right font-bold">{{ number_format($row['completion_rate'], 0) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No tasks assigned for this session yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
