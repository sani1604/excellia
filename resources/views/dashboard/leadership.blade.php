@extends('layouts.app')

@section('title', 'School Overview')
@section('heading', 'School Submission Overview')
@section('subheading', 'Track submitted files, pending checks, and late items across your classes.')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
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

        <div class="flex flex-wrap items-center gap-2.5">
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

    <!-- 4 Clear Summary Cards (Instead of 8 tiny boxes) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Files Waiting for Check -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Files Waiting for Your Check</h2>
                <a href="{{ route('reviews.queue') }}" class="text-sm font-semibold text-indigo-600 hover:underline">See all</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($pendingReviewTasks as $task)
                    <div class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">{{ $task->title }}</div>
                            <div class="text-sm text-slate-500 mt-0.5">
                                {{ $task->assignedTeacher?->full_name }} ·
                                {{ $task->teachingAllocation?->schoolClass?->name }}-{{ $task->teachingAllocation?->section?->name }} ·
                                {{ $task->teachingAllocation?->subject?->name }}
                            </div>
                        </div>
                        @if($task->submission)
                            <a href="{{ route('reviews.show', $task->submission) }}"
                               class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shrink-0">
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
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Late Submissions</h2>
                <a href="{{ route('overdue.monitor') }}" class="text-sm font-semibold text-rose-600 hover:underline">Send reminders</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($overdueAndEscalated as $task)
                    <div class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">{{ $task->title }}</div>
                            <div class="text-sm text-slate-500 mt-0.5">
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
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">Progress by Document Type</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Document Type</th>
                        <th class="px-5 py-3 text-right">Assigned</th>
                        <th class="px-5 py-3 text-right">Approved</th>
                        <th class="px-5 py-3 text-right">Waiting Check</th>
                        <th class="px-5 py-3 text-right">Late</th>
                        <th class="px-5 py-3 text-right">Completed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($byDocumentType as $row)
                        <tr>
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $row['document_type'] }}</td>
                            <td class="px-5 py-3.5 text-right">{{ $row['expected'] }}</td>
                            <td class="px-5 py-3.5 text-right text-emerald-700 font-semibold">{{ $row['approved'] }}</td>
                            <td class="px-5 py-3.5 text-right text-sky-700">{{ $row['pending_review'] }}</td>
                            <td class="px-5 py-3.5 text-right text-rose-700 font-semibold">{{ $row['overdue'] }}</td>
                            <td class="px-5 py-3.5 text-right font-bold">{{ number_format($row['completion_rate'], 0) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No tasks assigned for this session yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
