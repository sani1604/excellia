@extends('layouts.app')

@section('title', 'Academic Oversight Dashboard')
@section('heading', 'Academic Compliance & Monitoring Dashboard')
@section('subheading', 'Real-time submission, review, approval, overdue, and escalation telemetry reconciled against task records')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
            <label for="academic_year_id" class="text-xs font-medium text-slate-600">Academic Year:</label>
            <select id="academic_year_id" name="academic_year_id" onchange="this.form.submit()"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium">
                @foreach($academicYears as $yr)
                    <option value="{{ $yr->id }}" @selected($selectedYearId == $yr->id)>
                        {{ $yr->name }} ({{ $yr->status }})
                    </option>
                @endforeach
            </select>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasPermission('tasks.create'))
                <a href="{{ route('tasks.manage') }}" class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                    + Assign / Generate Tasks
                </a>
            @endif
            <a href="{{ route('reports.index') }}" class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                Open Full Reports
            </a>
        </div>
    </div>

    <!-- Leadership / Coordinator KPI Strip (Sections 6.2 & 6.3) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-3.5">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-[11px] font-medium text-slate-500">Expected Tasks</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $summary['expected'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-[11px] font-medium text-slate-500">Submitted</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ $summary['submitted'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-emerald-200 p-4">
            <div class="text-[11px] font-medium text-emerald-700">Approved</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $summary['approved'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-sky-200 p-4">
            <div class="text-[11px] font-medium text-sky-700">Pending Review</div>
            <div class="text-2xl font-bold text-sky-600 mt-1">{{ $summary['pending_review'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-amber-200 p-4">
            <div class="text-[11px] font-medium text-amber-700">Returned</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">{{ $summary['returned'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-rose-200 p-4">
            <div class="text-[11px] font-medium text-rose-700">Overdue</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ $summary['overdue'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-red-200 p-4">
            <div class="text-[11px] font-medium text-red-700">Escalated</div>
            <div class="text-2xl font-bold text-red-600 mt-1">{{ $summary['escalated'] }}</div>
        </div>
        <div class="bg-indigo-600 rounded-xl p-4 text-white">
            <div class="text-[11px] font-medium text-indigo-100">Completion Rate</div>
            <div class="text-2xl font-bold mt-1">{{ number_format($summary['completion_rate'], 1) }}%</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pending Review Queue Snapshot -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Awaiting Review in Scope</h2>
                <a href="{{ route('reviews.queue') }}" class="text-xs font-medium text-indigo-600 hover:underline">Open Queue</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($pendingReviewTasks as $task)
                    <div class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50">
                        <div>
                            <div class="text-sm font-medium text-slate-900">{{ $task->title }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                {{ $task->assignedTeacher?->full_name }} ·
                                {{ $task->teachingAllocation?->schoolClass?->name }}-{{ $task->teachingAllocation?->section?->name }} ·
                                {{ $task->teachingAllocation?->subject?->name }}
                            </div>
                        </div>
                        @if($task->submission)
                            <a href="{{ route('reviews.show', $task->submission) }}"
                               class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold shrink-0">
                                Inspect
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-slate-500">No submissions currently awaiting review in your scope.</div>
                @endforelse
            </div>
        </div>

        <!-- Active Delays & Escalations -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Overdue & Escalated Items</h2>
                <a href="{{ route('overdue.monitor') }}" class="text-xs font-medium text-rose-600 hover:underline">Overdue Monitor</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($overdueAndEscalated as $task)
                    <div class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50">
                        <div>
                            <div class="text-sm font-medium text-slate-900">{{ $task->title }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">
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
                    <div class="p-6 text-center text-xs text-slate-500">Zero overdue items in scope.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Compliance Breakdown by Document Type -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Compliance by Academic Document Category</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Document Category</th>
                        <th class="px-4 py-3 text-right">Expected</th>
                        <th class="px-4 py-3 text-right">Approved</th>
                        <th class="px-4 py-3 text-right">Pending Review</th>
                        <th class="px-4 py-3 text-right">Returned</th>
                        <th class="px-4 py-3 text-right">Overdue</th>
                        <th class="px-4 py-3 text-right">Completion %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($byDocumentType as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $row['document_type'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['expected'] }}</td>
                            <td class="px-4 py-3 text-right text-emerald-700 font-semibold">{{ $row['approved'] }}</td>
                            <td class="px-4 py-3 text-right text-sky-700">{{ $row['pending_review'] }}</td>
                            <td class="px-4 py-3 text-right text-amber-700">{{ $row['returned'] }}</td>
                            <td class="px-4 py-3 text-right text-rose-700 font-semibold">{{ $row['overdue'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['completion_rate'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-xs text-slate-500">No task records found for selected year.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
