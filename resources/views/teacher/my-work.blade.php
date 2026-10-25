@extends('layouts.app')

@section('title', 'My Assigned Tasks')
@section('heading', 'My Assigned Tasks')
@section('subheading', 'Task-first submission workspace — class, section, subject, and document type are prefilled automatically')

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        @php
            $tabs = [
                '' => 'All Assigned',
                'pending' => 'Pending (' . $summary['pending_submission'] . ')',
                'returned' => 'Returned (' . $summary['returned'] . ')',
                'under_review' => 'Under Review (' . $summary['pending_review'] . ')',
                'overdue' => 'Overdue (' . $summary['overdue'] . ')',
                'approved' => 'Approved (' . $summary['approved'] . ')',
            ];
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('teacher.tasks', $key ? ['filter' => $key] : []) }}"
               class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border transition {{ $activeFilter === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Task</th>
                        <th class="px-4 py-3 text-left">Class / Section / Subject</th>
                        <th class="px-4 py-3 text-left">Document Type & Period</th>
                        <th class="px-4 py-3 text-left">Deadline</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tasks as $task)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $task->title }}</div>
                                @if($task->submission?->currentVersion?->latestReview?->remarks && $task->workflow_status === 'RETURNED_FOR_CORRECTION')
                                    <div class="mt-1 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                        Reviewer Remark: "{{ $task->submission->currentVersion->latestReview->remarks }}"
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700">
                                <div class="font-semibold">{{ $task->teachingAllocation?->schoolClass?->name }} — Sec {{ $task->teachingAllocation?->section?->name }}</div>
                                <div class="text-slate-500">{{ $task->teachingAllocation?->subject?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700">
                                <div class="font-medium">{{ $task->documentType?->name }}</div>
                                <div class="text-slate-500">{{ $task->period_label }}</div>
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
                                    {{ $task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'Resubmit' : ($task->workflow_status === 'NOT_STARTED' ? 'Submit Document' : 'Open Task') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-xs text-slate-500">No tasks match the selected filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
