@extends('layouts.app')

@section('title', 'Files to Check')
@section('heading', 'Files to Check')
@section('subheading', 'Open submitted teacher files to approve them or send back with feedback.')

@section('content')
    <!-- Simple Filter Bar -->
    <form method="GET" action="{{ route('reviews.queue') }}" class="bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap items-end gap-3 text-sm">
        <div class="flex-1 min-w-40">
            <label class="block text-slate-600 font-medium text-xs mb-1">Status</label>
            <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                <option value="PENDING_REVIEW" @selected(($filters['status'] ?? '') === 'PENDING_REVIEW')>Waiting for Check</option>
                <option value="" @selected(($filters['status'] ?? '') === '')>All Files</option>
                <option value="RETURNED_FOR_CORRECTION" @selected(($filters['status'] ?? '') === 'RETURNED_FOR_CORRECTION')>Needs Correction</option>
                <option value="APPROVED" @selected(($filters['status'] ?? '') === 'APPROVED')>Approved</option>
                <option value="NOT_STARTED" @selected(($filters['status'] ?? '') === 'NOT_STARTED')>Not Uploaded Yet</option>
            </select>
        </div>
        <div class="flex-1 min-w-36">
            <label class="block text-slate-600 font-medium text-xs mb-1">Class</label>
            <select name="class_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                <option value="">All Classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected(($filters['class_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-40">
            <label class="block text-slate-600 font-medium text-xs mb-1">Teacher</label>
            <select name="teacher_user_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                <option value="">All Teachers</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected(($filters['teacher_user_id'] ?? '') == $t->id)>{{ $t->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold cursor-pointer">Show</button>
            <a href="{{ route('reviews.queue', ['status' => '']) }}" class="px-3.5 py-2 rounded-xl bg-slate-100 text-slate-700 font-medium">Reset</a>
        </div>
    </form>

    <div class="space-y-3">
        @forelse($tasks as $task)
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 transition">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="text-base font-bold text-slate-900">{{ $task->title }}</span>
                        @include('partials.status-badge', ['task' => $task])
                    </div>
                    <div class="text-sm text-slate-600">
                        <strong>{{ $task->assignedTeacher?->full_name }}</strong>
                        · {{ $task->teachingAllocation?->schoolClass?->name }} - Section {{ $task->teachingAllocation?->section?->name }}
                        · {{ $task->teachingAllocation?->subject?->name }}
                    </div>
                    <div class="text-xs text-slate-500">
                        {{ $task->documentType?->name }} ({{ $task->period_label }}) · Due {{ $task->due_at?->format('d M Y') }}
                        @if($task->submission?->last_submitted_at)
                            · Uploaded {{ $task->submission->last_submitted_at->format('d M Y, h:i A') }}
                        @endif
                    </div>
                </div>

                <div class="shrink-0">
                    @if($task->submission)
                        <a href="{{ route('reviews.show', $task->submission) }}"
                           class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-2xs">
                            Check File
                        </a>
                    @else
                        <a href="{{ route('tasks.show', $task) }}"
                           class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium">
                            View Task
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-sm text-slate-500">
                No files match this filter.
            </div>
        @endforelse
    </div>
    <div>{{ $tasks->links() }}</div>
@endsection
