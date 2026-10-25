@extends('layouts.app')

@section('title', 'My Work')
@section('heading', 'My Work & Academic Submissions')
@section('subheading', 'Upload your lesson plans, worksheets, and papers in two clicks.')

@section('content')
    <!-- Returned for Correction Urgent Banner -->
    @if($returnedTasks->isNotEmpty())
        <div class="rounded-2xl bg-amber-50 border-2 border-amber-300 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-amber-950">Please Check & Re-upload ({{ $returnedTasks->count() }})</h2>
                <p class="text-sm text-amber-900 mt-1">
                    <strong>{{ $returnedTasks->first()->title }}:</strong>
                    "{{ $returnedTasks->first()->submission?->currentVersion?->latestReview?->remarks }}"
                </p>
            </div>
            <a href="{{ route('tasks.show', $returnedTasks->first()) }}"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shrink-0 shadow-xs">
                Fix & Upload Again
            </a>
        </div>
    @endif

    <!-- 4 Simple, Easy-to-Read Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('teacher.tasks', ['filter' => 'pending']) }}" class="bg-white rounded-2xl border border-slate-200 p-5 hover:border-indigo-400 transition">
            <div class="text-sm font-medium text-slate-500">To Upload</div>
            <div class="text-3xl font-bold text-slate-900 mt-1">{{ $summary['pending_submission'] }}</div>
            @if($summary['overdue'] > 0)
                <div class="text-xs font-semibold text-rose-600 mt-1">{{ $summary['overdue'] }} past due date</div>
            @endif
        </a>

        <a href="{{ route('teacher.tasks', ['filter' => 'returned']) }}" class="bg-white rounded-2xl border border-amber-200 p-5 hover:border-amber-400 transition">
            <div class="text-sm font-medium text-amber-800">Needs Correction</div>
            <div class="text-3xl font-bold text-amber-600 mt-1">{{ $summary['returned'] }}</div>
        </a>

        <a href="{{ route('teacher.tasks', ['filter' => 'under_review']) }}" class="bg-white rounded-2xl border border-slate-200 p-5 hover:border-sky-400 transition">
            <div class="text-sm font-medium text-slate-500">Sent for Check</div>
            <div class="text-3xl font-bold text-sky-600 mt-1">{{ $summary['pending_review'] }}</div>
        </a>

        <a href="{{ route('teacher.tasks', ['filter' => 'approved']) }}" class="bg-white rounded-2xl border border-emerald-200 p-5 hover:border-emerald-400 transition">
            <div class="text-sm font-medium text-emerald-700">Approved</div>
            <div class="text-3xl font-bold text-emerald-600 mt-1">{{ $summary['approved'] }}</div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Simple Card List of My Tasks -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Your Tasks</h2>
                <a href="{{ route('teacher.tasks') }}" class="text-sm font-semibold text-indigo-600 hover:underline">See all</a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($myTasks as $task)
                    <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/70">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-base font-semibold text-slate-900">{{ $task->title }}</span>
                                @include('partials.status-badge', ['task' => $task])
                            </div>
                            <div class="text-sm text-slate-600">
                                <strong>{{ $task->teachingAllocation?->schoolClass?->name }} - {{ $task->teachingAllocation?->section?->name }}</strong>
                                · {{ $task->teachingAllocation?->subject?->name }}
                                · {{ $task->period_label }}
                            </div>
                            <div class="text-xs text-slate-500">
                                Due Date: <strong class="text-slate-700">{{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}</strong>
                            </div>
                        </div>

                        <div class="shrink-0">
                            <a href="{{ route('tasks.show', $task) }}"
                               class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold {{ $task->canTeacherSubmit() ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                {{ $task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'Fix & Upload' : ($task->workflow_status === 'NOT_STARTED' ? 'Upload File' : 'Open') }}
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center text-sm text-slate-500">You have no pending tasks right now.</div>
                @endforelse
            </div>
        </div>

        <!-- Simple Recent Messages -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Messages & Feedback</h2>
                <a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline">All</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($recentNotifications as $notif)
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-900">{{ $notif->subject }}</span>
                            @if(!$notif->read_at)
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 shrink-0"></span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-600 mt-1">{{ $notif->message_summary }}</p>
                        <div class="text-xs text-slate-400 mt-1.5">{{ $notif->created_at?->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">No new messages.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
