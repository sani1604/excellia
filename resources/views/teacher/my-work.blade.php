@extends('layouts.app')

@section('title', 'My Tasks')
@section('heading', 'My Tasks')
@section('subheading', 'Tap any task below to upload your file.')

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        @php
            $tabs = [
                '' => 'All (' . $summary['expected'] . ')',
                'pending' => 'To Upload (' . $summary['pending_submission'] . ')',
                'returned' => 'Needs Correction (' . $summary['returned'] . ')',
                'under_review' => 'Sent for Check (' . $summary['pending_review'] . ')',
                'overdue' => 'Late (' . $summary['overdue'] . ')',
                'approved' => 'Approved (' . $summary['approved'] . ')',
            ];
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('teacher.tasks', $key ? ['filter' => $key] : []) }}"
               class="px-4 py-2 rounded-xl text-sm font-semibold border transition {{ $activeFilter === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse($tasks as $task)
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 transition">
                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="text-base font-bold text-slate-900">{{ $task->title }}</span>
                        @include('partials.status-badge', ['task' => $task])
                    </div>

                    <div class="text-sm text-slate-600">
                        <strong>{{ $task->teachingAllocation?->schoolClass?->name }} — Section {{ $task->teachingAllocation?->section?->name }}</strong>
                        · {{ $task->teachingAllocation?->subject?->name }}
                        · {{ $task->documentType?->name }} ({{ $task->period_label }})
                    </div>

                    <div class="text-sm text-slate-500">
                        Due Date: <strong class="text-slate-700">{{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}</strong>
                    </div>

                    @if($task->submission?->currentVersion?->latestReview?->remarks && $task->workflow_status === 'RETURNED_FOR_CORRECTION')
                        <div class="mt-2 text-sm text-amber-950 bg-amber-50 border border-amber-300 rounded-xl px-3.5 py-2.5">
                            <strong>Coordinator Feedback:</strong> "{{ $task->submission->currentVersion->latestReview->remarks }}"
                        </div>
                    @endif
                </div>

                <div class="shrink-0">
                    <a href="{{ route('tasks.show', $task) }}"
                       class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold {{ $task->canTeacherSubmit() ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        {{ $task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'Fix & Upload Again' : ($task->workflow_status === 'NOT_STARTED' ? 'Upload File' : 'View Details') }}
                    </a>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-sm text-slate-500">
                No tasks found in this tab.
            </div>
        @endforelse
    </div>
@endsection
