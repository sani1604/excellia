@extends('layouts.app')

@section('title', 'Check File — ' . $task->title)
@section('heading', $task->title)
@section('subheading', $task->assignedTeacher?->full_name . ' · ' . $task->teachingAllocation?->schoolClass?->name . ' - Section ' . $task->teachingAllocation?->section?->name . ' · ' . $task->teachingAllocation?->subject?->name)

@section('content')
    @php
        $aiAllowed = app(\App\Services\AiAssistantService::class)->isAllowedForUser(auth()->user());
    @endphp

    <!-- Step 1: Open Teacher's File -->
    @if($submission->currentVersion)
        <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    @include('partials.status-badge', ['task' => $task])
                    <span class="text-xs text-slate-500">{{ $task->documentType?->name }} · {{ $task->period_label }}</span>
                </div>
                <h2 class="text-base sm:text-lg font-bold text-slate-900 pt-1 break-all">{{ $submission->currentVersion->drive_file_name }}</h2>
                <p class="text-sm text-slate-500">
                    Uploaded by <strong>{{ $task->assignedTeacher?->full_name }}</strong> on
                    {{ $submission->currentVersion->uploaded_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                    ({{ $submission->currentVersion->formattedSize() }})
                </p>
                @if($submission->currentVersion->teacher_note)
                    <div class="mt-2 rounded-xl bg-slate-50 border border-slate-200 px-4 py-2.5 text-sm text-slate-700">
                        <strong>Teacher's Note:</strong> {{ $submission->currentVersion->teacher_note }}
                    </div>
                @endif
            </div>

            <div class="shrink-0">
                @if($canOpenCurrentFile)
                    <a href="{{ route('submissions.versions.file', $submission->currentVersion) }}" target="_blank"
                       class="w-full sm:w-auto text-center inline-flex items-center justify-center px-5 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold shadow-xs">
                        Open & Read File
                    </a>
                @else
                    <span class="inline-block px-4 py-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold">
                        Confidential File (Restricted Access)
                    </span>
                @endif
            </div>
        </div>
    @endif

    <!-- Step 2: Approve or Ask for Correction -->
    @if($canDecide)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <form method="POST" action="{{ route('reviews.approve', $submission) }}"
                  class="bg-emerald-50/70 rounded-2xl border border-emerald-200 p-5 sm:p-6 flex flex-col justify-between space-y-4">
                @csrf
                <div>
                    <h3 class="text-base font-bold text-emerald-950">Looks Good — Approve</h3>
                    <p class="text-sm text-emerald-900 mt-1">Marks this task as completed and informs the teacher.</p>
                    <div class="flex items-center justify-between gap-2 mt-4 mb-1">
                        <label class="text-sm font-medium text-emerald-950">Optional Note</label>
                        @if($aiAllowed)
                            <button type="button"
                                    onclick="window.excelliaAiAssist(this,{action:'review_feedback',decision:'APPROVE',task_title:@js($task->title),teacher_name:@js($task->assignedTeacher?->full_name)},function(txt){document.getElementById('approve_remarks').value=txt;})"
                                    class="text-xs font-semibold text-purple-800 hover:underline cursor-pointer">
                                ✨ AI Draft Note
                            </button>
                        @endif
                    </div>
                    <textarea id="approve_remarks" name="remarks" rows="3" placeholder="e.g. Well structured lesson plan, approved."
                              class="w-full rounded-xl border border-emerald-300 bg-white px-3.5 py-2.5 text-sm"></textarea>
                </div>
                <button type="submit"
                        class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-xs cursor-pointer">
                    Approve Submission
                </button>
            </form>

            <form method="POST" action="{{ route('reviews.return', $submission) }}"
                  class="bg-amber-50/70 rounded-2xl border border-amber-300 p-5 sm:p-6 flex flex-col justify-between space-y-4">
                @csrf
                <div>
                    <h3 class="text-base font-bold text-amber-950">Needs Changes — Send Back</h3>
                    <p class="text-sm text-amber-900 mt-1">Asks the teacher to make corrections and upload again.</p>
                    <div class="flex items-center justify-between gap-2 mt-4 mb-1">
                        <label class="text-sm font-medium text-amber-950">What should the teacher fix? *</label>
                        @if($aiAllowed)
                            <button type="button"
                                    onclick="window.excelliaAiAssist(this,{action:'review_feedback',decision:'RETURN',task_title:@js($task->title),teacher_name:@js($task->assignedTeacher?->full_name),keywords:document.getElementById('return_remarks').value},function(txt){document.getElementById('return_remarks').value=txt;})"
                                    class="text-xs font-semibold text-purple-800 hover:underline cursor-pointer">
                                ✨ AI Polite Draft
                            </button>
                        @endif
                    </div>
                    <textarea id="return_remarks" name="remarks" rows="3" required placeholder="Write briefly what needs to be updated (or type 2-3 keywords and tap AI Polite Draft)..."
                              class="w-full rounded-xl border border-amber-300 bg-white px-3.5 py-2.5 text-sm"></textarea>
                </div>
                <button type="submit"
                        class="w-full py-3 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold shadow-xs cursor-pointer">
                    Send Back for Correction
                </button>
            </form>
        </div>
    @elseif($task->workflow_status === 'APPROVED' && auth()->user()->hasPermission('tasks.update'))
        <details class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5">
            <summary class="text-sm font-semibold text-amber-800 cursor-pointer flex items-center justify-between">
                <span>Submission is Approved — Need the teacher to upload an updated file? Click to Reopen</span>
                <span class="text-xs px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200">Reopen Option</span>
            </summary>
            <form method="POST" action="{{ route('tasks.reopen', $task) }}" class="mt-3 pt-3 border-t border-slate-100 flex flex-col sm:flex-row gap-2.5">
                @csrf
                <input type="text" name="reason" required placeholder="Reason for reopening approved submission (recorded in Audit Log)..."
                       class="flex-1 rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
                <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold cursor-pointer">
                    Reopen for Resubmission
                </button>
            </form>
        </details>
    @endif

    <!-- Previous Uploads & Feedback History -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">Upload & Feedback History</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach($submission->versions as $ver)
                <div class="p-4 sm:p-5 space-y-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm">
                            <span class="font-bold text-slate-900">Upload #{{ $ver->version_number }}</span> ·
                            <span class="font-medium text-slate-800 break-all">{{ $ver->drive_file_name }}</span>
                            <span class="text-slate-500">({{ $ver->uploaded_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }})</span>
                        </div>
                        @if($scopeAuth->canAccessSubmissionFile(auth()->user(), $ver))
                            <a href="{{ route('submissions.versions.file', $ver) }}" target="_blank"
                               class="text-sm font-semibold text-indigo-600 hover:underline">Open File</a>
                        @endif
                    </div>
                    @if($ver->teacher_note)
                        <div class="text-sm text-slate-600">Teacher's Note: "{{ $ver->teacher_note }}"</div>
                    @endif
                    @foreach($ver->reviews as $rev)
                        <div class="rounded-xl p-3.5 text-sm border {{ $rev->decision === 'APPROVED' ? 'bg-emerald-50 border-emerald-200 text-emerald-950' : 'bg-amber-50 border-amber-200 text-amber-950' }}">
                            <strong>{{ $rev->decision === 'APPROVED' ? 'Approved' : 'Sent back for correction' }}</strong> by {{ $rev->reviewer?->full_name }}
                            on {{ $rev->reviewed_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                            @if($rev->remarks)
                                <div class="mt-1">"{{ $rev->remarks }}"</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endsection
