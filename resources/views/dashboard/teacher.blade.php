@extends('layouts.app')

@section('title', 'My Work Dashboard')
@section('heading', 'My Work & Academic Submissions')
@section('subheading', 'Assigned academic document tasks, deadlines, reviewer remarks, and status history for ' . ($currentYear?->name ?? '2026–27'))

@section('content')
    <!-- Returned for Correction Urgent Banner -->
    @if($returnedTasks->isNotEmpty())
        <div class="rounded-xl bg-amber-50 border border-amber-300 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-bold text-amber-900">Action Required: {{ $returnedTasks->count() }} Submission(s) Returned for Correction</h2>
                <p class="text-xs text-amber-800 mt-1">
                    Latest: <span class="font-semibold">{{ $returnedTasks->first()->title }}</span> —
                    "{{ $returnedTasks->first()->submission?->currentVersion?->latestReview?->remarks }}"
                </p>
            </div>
            <a href="{{ route('tasks.show', $returnedTasks->first()) }}"
               class="inline-flex items-center px-3.5 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shrink-0">
                Review Remarks & Resubmit
            </a>
        </div>
    @endif

    <!-- Teacher KPI Cards (Section 6.1: Pending, Due Soon, Under Review, Returned, Approved, Overdue) -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <a href="{{ route('teacher.tasks', ['filter' => 'pending']) }}" class="bg-white rounded-xl border border-slate-200 p-4 hover:border-indigo-400 transition">
            <div class="text-xs font-medium text-slate-500">Pending (Not Started)</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $summary['pending_submission'] }}</div>
        </a>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500">Due Soon (≤3d)</div>
            <div class="text-2xl font-bold text-indigo-600 mt-1">{{ $summary['due_soon'] }}</div>
        </div>
        <a href="{{ route('teacher.tasks', ['filter' => 'under_review']) }}" class="bg-white rounded-xl border border-slate-200 p-4 hover:border-sky-400 transition">
            <div class="text-xs font-medium text-slate-500">Under Review</div>
            <div class="text-2xl font-bold text-sky-600 mt-1">{{ $summary['pending_review'] }}</div>
        </a>
        <a href="{{ route('teacher.tasks', ['filter' => 'returned']) }}" class="bg-white rounded-xl border border-amber-200 p-4 hover:border-amber-400 transition">
            <div class="text-xs font-medium text-amber-700">Returned</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">{{ $summary['returned'] }}</div>
        </a>
        <a href="{{ route('teacher.tasks', ['filter' => 'approved']) }}" class="bg-white rounded-xl border border-emerald-200 p-4 hover:border-emerald-400 transition">
            <div class="text-xs font-medium text-emerald-700">Approved</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $summary['approved'] }}</div>
        </a>
        <a href="{{ route('teacher.tasks', ['filter' => 'overdue']) }}" class="bg-white rounded-xl border border-rose-200 p-4 hover:border-rose-400 transition">
            <div class="text-xs font-medium text-rose-700">Overdue</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ $summary['overdue'] }}</div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Assigned Tasks & Deadlines -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">My Assigned Tasks & Upcoming Deadlines</h2>
                <a href="{{ route('teacher.tasks') }}" class="text-xs font-medium text-indigo-600 hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Task & Academic Context</th>
                            <th class="px-4 py-3 text-left">Due Date</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($myTasks as $task)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $task->title }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        {{ $task->teachingAllocation?->schoolClass?->name }} - {{ $task->teachingAllocation?->section?->name }}
                                        · {{ $task->teachingAllocation?->subject?->name }}
                                        · <span class="font-medium">{{ $task->documentType?->name }}</span> ({{ $task->period_label }})
                                    </div>
                                    @if($task->documentType?->is_restricted)
                                        <span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-bold rounded bg-rose-50 text-rose-700 border border-rose-200">
                                            {{ $task->documentType->sensitivity_level }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-700 whitespace-nowrap">
                                    {{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                                </td>
                                <td class="px-4 py-3">
                                    @include('partials.status-badge', ['task' => $task])
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold {{ $task->canTeacherSubmit() ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                        {{ $task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'Resubmit' : ($task->workflow_status === 'NOT_STARTED' ? 'Submit' : 'View History') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-xs text-slate-500">No tasks currently assigned to you.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Notifications -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Recent Alerts & Remarks</h2>
                <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">All</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($recentNotifications as $notif)
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-slate-900">{{ $notif->subject }}</span>
                            @if(!$notif->read_at)
                                <span class="w-2 h-2 rounded-full bg-indigo-600 shrink-0"></span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-600 mt-1">{{ $notif->message_summary }}</p>
                        <div class="text-[11px] text-slate-400 mt-1">{{ $notif->created_at?->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-slate-500">No notifications yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
