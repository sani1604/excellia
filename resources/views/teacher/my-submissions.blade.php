@extends('layouts.app')

@section('title', 'My Submissions')
@section('heading', 'My Submissions & Review Remarks')
@section('subheading', 'Complete log of your uploaded documents, current version numbers, review decisions, and remarks')

@section('content')
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Task & Academic Context</th>
                        <th class="px-4 py-3 text-left">Current Version</th>
                        <th class="px-4 py-3 text-left">Last Submitted</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Latest Reviewer Remark</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($submissions as $sub)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $sub->task?->title }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $sub->task?->teachingAllocation?->schoolClass?->name }}-{{ $sub->task?->teachingAllocation?->section?->name }}
                                    · {{ $sub->task?->teachingAllocation?->subject?->name }}
                                    · {{ $sub->task?->period_label }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span class="font-bold text-slate-900">v{{ $sub->currentVersion?->version_number ?? 1 }}</span>
                                <div class="text-slate-500 truncate max-w-44">{{ $sub->currentVersion?->drive_file_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700 whitespace-nowrap">
                                {{ $sub->last_submitted_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($sub->task)
                                    @include('partials.status-badge', ['task' => $sub->task])
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700 max-w-xs">
                                {{ $sub->currentVersion?->latestReview?->remarks ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($sub->task)
                                    <a href="{{ route('tasks.show', $sub->task) }}"
                                       class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $sub->task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                        {{ $sub->task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'Resubmit' : 'History' }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-xs text-slate-500">You have not submitted any documents yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $submissions->links() }}</div>
    </div>
@endsection
