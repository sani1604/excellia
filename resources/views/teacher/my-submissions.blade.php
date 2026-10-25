@extends('layouts.app')

@section('title', 'My Uploaded Files')
@section('heading', 'My Uploaded Files')
@section('subheading', 'All documents you have submitted and feedback from your coordinator.')

@section('content')
    <div class="space-y-3">
        @forelse($submissions as $sub)
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="text-base font-bold text-slate-900">{{ $sub->task?->title }}</span>
                        @if($sub->task)
                            @include('partials.status-badge', ['task' => $sub->task])
                        @endif
                    </div>
                    <div class="text-sm text-slate-600">
                        {{ $sub->task?->teachingAllocation?->schoolClass?->name }}-{{ $sub->task?->teachingAllocation?->section?->name }}
                        · {{ $sub->task?->teachingAllocation?->subject?->name }}
                        · {{ $sub->task?->period_label }}
                    </div>
                    <div class="text-xs text-slate-500">
                        Submitted {{ $sub->last_submitted_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                        · File: {{ $sub->currentVersion?->drive_file_name }}
                    </div>
                    @if($sub->currentVersion?->latestReview?->remarks)
                        <div class="text-sm text-slate-700 mt-1.5 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2">
                            <strong>Feedback:</strong> "{{ $sub->currentVersion->latestReview->remarks }}"
                        </div>
                    @endif
                </div>

                @if($sub->task)
                    <a href="{{ route('tasks.show', $sub->task) }}"
                       class="shrink-0 inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold {{ $sub->task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        {{ $sub->task->workflow_status === 'RETURNED_FOR_CORRECTION' ? 'Fix & Upload' : 'View File' }}
                    </a>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-sm text-slate-500">
                You have not uploaded any files yet.
            </div>
        @endforelse
    </div>
    <div>{{ $submissions->links() }}</div>
@endsection
