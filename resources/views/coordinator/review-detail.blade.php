@extends('layouts.app')

@section('title', 'Review Submission — ' . $task->title)
@section('heading', 'Review Submission: ' . $task->title)
@section('subheading', $task->assignedTeacher?->full_name . ' · ' . $task->teachingAllocation?->schoolClass?->name . '-' . $task->teachingAllocation?->section?->name . ' · ' . $task->teachingAllocation?->subject?->name)

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <!-- Current Submitted File Inspection Card -->
            @if($submission->currentVersion)
                <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span class="px-2.5 py-0.5 rounded bg-indigo-600 text-white text-xs font-bold">
                                Current Version v{{ $submission->currentVersion->version_number }}
                            </span>
                            <h2 class="text-base font-semibold text-slate-900 mt-1">{{ $submission->currentVersion->drive_file_name }}</h2>
                            <p class="text-xs text-slate-500">
                                Uploaded {{ $submission->currentVersion->uploaded_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                                · {{ $submission->currentVersion->formattedSize() }}
                                · Drive ID: <code>{{ $submission->currentVersion->drive_file_id }}</code>
                            </p>
                        </div>

                        @if($canOpenCurrentFile)
                            <a href="{{ route('submissions.versions.file', $submission->currentVersion) }}" target="_blank"
                               class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">
                                Open / Inspect Document File
                            </a>
                        @else
                            <span class="px-3 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">
                                Restricted File — Access Not Authorised for Your Role
                            </span>
                        @endif
                    </div>

                    @if($submission->currentVersion->teacher_note)
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-3.5 text-xs text-slate-700">
                            <span class="font-semibold text-slate-900">Teacher Note:</span> {{ $submission->currentVersion->teacher_note }}
                        </div>
                    @endif
                </div>
            @endif

            <!-- Reviewer Decision Panel (Approve or Return with Mandatory Remarks) -->
            @if($canDecide)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <form method="POST" action="{{ route('reviews.approve', $submission) }}"
                          class="bg-emerald-50/60 rounded-xl border border-emerald-200 p-5 flex flex-col justify-between space-y-4">
                        @csrf
                        <div>
                            <h3 class="text-sm font-bold text-emerald-900">Approve Current Version (v{{ $submission->currentVersion?->version_number }})</h3>
                            <p class="text-xs text-emerald-800 mt-1">Approving closes the task as complete and notifies the teacher.</p>
                            <label class="block text-xs font-semibold text-emerald-900 mt-3 mb-1">Approval Note (Optional)</label>
                            <textarea name="remarks" rows="3" placeholder="Optional commendation or moderation note..."
                                      class="w-full rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs"></textarea>
                        </div>
                        <button type="submit"
                                class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm cursor-pointer">
                            Approve Submission
                        </button>
                    </form>

                    <form method="POST" action="{{ route('reviews.return', $submission) }}"
                          class="bg-amber-50/60 rounded-xl border border-amber-300 p-5 flex flex-col justify-between space-y-4">
                        @csrf
                        <div>
                            <h3 class="text-sm font-bold text-amber-900">Return for Correction</h3>
                            <p class="text-xs text-amber-800 mt-1">Reopens task for teacher correction. Clear remarks are strictly mandatory.</p>
                            <label class="block text-xs font-semibold text-amber-900 mt-3 mb-1">Mandatory Correction Remarks *</label>
                            <textarea name="remarks" rows="3" required placeholder="Specify exact corrections required before approval..."
                                      class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs"></textarea>
                        </div>
                        <button type="submit"
                                class="w-full py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm cursor-pointer">
                            Return for Correction
                        </button>
                    </form>
                </div>
            @endif

            <!-- Complete Prior Versions & Remarks Audit History -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-900">All Submitted Versions & Review Decisions</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($submission->versions as $ver)
                        <div class="p-5 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="text-xs">
                                    <span class="font-bold text-slate-900">Version v{{ $ver->version_number }}</span> ·
                                    <span class="font-medium text-slate-800">{{ $ver->drive_file_name }}</span>
                                    <span class="text-slate-500">({{ $ver->uploaded_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }})</span>
                                </div>
                                @if($scopeAuth->canAccessSubmissionFile(auth()->user(), $ver))
                                    <a href="{{ route('submissions.versions.file', $ver) }}" target="_blank"
                                       class="text-xs font-semibold text-indigo-600 hover:underline">Open v{{ $ver->version_number }}</a>
                                @endif
                            </div>
                            @if($ver->teacher_note)
                                <div class="text-xs text-slate-600">Teacher Note: "{{ $ver->teacher_note }}"</div>
                            @endif
                            @foreach($ver->reviews as $rev)
                                <div class="rounded-lg p-3 text-xs border {{ $rev->decision === 'APPROVED' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
                                    <strong>{{ $rev->decision }}</strong> by {{ $rev->reviewer?->full_name }}
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
        </div>

        <!-- Right Context Column -->
        <div class="space-y-5">
            <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3 text-xs">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Academic Context</h3>
                <div>
                    <span class="text-slate-500 block">Status</span>
                    <div class="mt-1">@include('partials.status-badge', ['task' => $task])</div>
                </div>
                <div>
                    <span class="text-slate-500 block">Document Category</span>
                    <span class="font-semibold text-slate-900">{{ $task->documentType?->folder_name }}</span>
                </div>
                <div>
                    <span class="text-slate-500 block">Period</span>
                    <span class="font-semibold text-slate-900">{{ $task->period_label }}</span>
                </div>
                <div>
                    <span class="text-slate-500 block">Deadline</span>
                    <span class="font-semibold text-slate-900">{{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}</span>
                </div>
                <div>
                    <span class="text-slate-500 block">Mapped Drive Path</span>
                    <p class="text-slate-700 mt-1 break-words">{{ $canonicalHierarchy }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
