@extends('layouts.app')

@section('title', 'Coordinator Review Queue')
@section('heading', 'Academic Review Queue')
@section('subheading', 'Filtered strictly to your authorised academic classes, sections, and subjects')

@section('content')
    <form method="GET" action="{{ route('reviews.queue') }}" class="bg-white rounded-xl border border-slate-200 p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
        <div>
            <label class="block text-slate-600 font-medium mb-1">Class</label>
            <select name="class_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                <option value="">All Classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected(($filters['class_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Subject</label>
            <select name="subject_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                <option value="">All Subjects</option>
                @foreach($subjects as $s)
                    <option value="{{ $s->id }}" @selected(($filters['subject_id'] ?? '') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Teacher</label>
            <select name="teacher_user_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                <option value="">All Teachers</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected(($filters['teacher_user_id'] ?? '') == $t->id)>{{ $t->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Document Type</label>
            <select name="document_type_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                <option value="">All Types</option>
                @foreach($documentTypes as $dt)
                    <option value="{{ $dt->id }}" @selected(($filters['document_type_id'] ?? '') == $dt->id)>{{ $dt->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Workflow Status</label>
            <select name="status" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                <option value="PENDING_REVIEW" @selected(($filters['status'] ?? '') === 'PENDING_REVIEW')>Pending Review</option>
                <option value="" @selected(($filters['status'] ?? '') === '')>All Statuses</option>
                <option value="RETURNED_FOR_CORRECTION" @selected(($filters['status'] ?? '') === 'RETURNED_FOR_CORRECTION')>Returned</option>
                <option value="APPROVED" @selected(($filters['status'] ?? '') === 'APPROVED')>Approved</option>
                <option value="NOT_STARTED" @selected(($filters['status'] ?? '') === 'NOT_STARTED')>Not Started</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Filter</button>
            <a href="{{ route('reviews.queue', ['status' => '']) }}" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 font-medium">Reset</a>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Task & Document</th>
                        <th class="px-4 py-3 text-left">Class / Subject</th>
                        <th class="px-4 py-3 text-left">Teacher</th>
                        <th class="px-4 py-3 text-left">Due / Submitted</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tasks as $task)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $task->title }}</div>
                                <div class="text-xs text-slate-500">{{ $task->documentType?->name }} · {{ $task->period_label }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="font-semibold text-slate-800">{{ $task->teachingAllocation?->schoolClass?->name }} - {{ $task->teachingAllocation?->section?->name }}</div>
                                <div class="text-slate-500">{{ $task->teachingAllocation?->subject?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-800">{{ $task->assignedTeacher?->full_name }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                <div>Due: {{ $task->due_at?->format('d M Y') }}</div>
                                @if($task->submission?->last_submitted_at)
                                    <div class="text-indigo-600 font-medium">Sub: {{ $task->submission->last_submitted_at->format('d M, H:i') }} (v{{ $task->submission->currentVersion?->version_number }})</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @include('partials.status-badge', ['task' => $task])
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($task->submission)
                                    <a href="{{ route('reviews.show', $task->submission) }}"
                                       class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                                        Review Submission
                                    </a>
                                @else
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium">
                                        Task Details
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-xs text-slate-500">No tasks match the current review queue filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $tasks->links() }}</div>
    </div>
@endsection
