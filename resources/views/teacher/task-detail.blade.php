@extends('layouts.app')

@section('title', $task->title)
@section('heading', $task->title)
@section('subheading', $task->academicYear?->name . ' · ' . ($task->teachingAllocation?->schoolClass?->name ?? '') . ' - Section ' . ($task->teachingAllocation?->section?->name ?? '') . ' · ' . ($task->teachingAllocation?->subject?->name ?? ''))

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Columns: Task Metadata + Upload/Resubmit Form + Version History -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Returned Remarks Callout (Section 6.1) -->
            @if($task->workflow_status === 'RETURNED_FOR_CORRECTION' && $task->submission?->currentVersion?->latestReview)
                <div class="rounded-xl bg-amber-50 border border-amber-300 p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-amber-800">Returned for Correction — Reviewer Remarks</div>
                    <p class="text-sm text-amber-950 font-medium mt-1.5">
                        "{{ $task->submission->currentVersion->latestReview->remarks }}"
                    </p>
                    <div class="text-xs text-amber-700 mt-2">
                        Reviewed by {{ $task->submission->currentVersion->latestReview->reviewer?->full_name }}
                        on {{ $task->submission->currentVersion->latestReview->reviewed_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                    </div>
                </div>
            @endif

            <!-- Submit / Resubmit Document Card -->
            @if($canSubmit)
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{ $task->submission ? 'Upload Corrected Version (Resubmit)' : 'Submit Academic Document' }}
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Academic context is prefilled from your assigned task. Earlier versions are preserved automatically.
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 text-xs font-semibold">
                            Next Version: v{{ ($task->submission?->versions->max('version_number') ?? 0) + 1 }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('tasks.submit', $task) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Submission Method</label>
                            <div class="flex flex-wrap gap-4 text-xs">
                                @if(in_array('PORTAL_UPLOAD', $allowedModes, true))
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="submission_mode" value="PORTAL_UPLOAD" checked
                                               onchange="document.getElementById('upload_box').classList.remove('hidden');document.getElementById('drive_link_box').classList.add('hidden');">
                                        <span class="font-medium text-slate-800">Upload File via Portal (Stored to Mapped Drive Folder)</span>
                                    </label>
                                @endif
                                @if(in_array('DRIVE_LINK', $allowedModes, true))
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="submission_mode" value="DRIVE_LINK"
                                               onchange="document.getElementById('drive_link_box').classList.remove('hidden');document.getElementById('upload_box').classList.add('hidden');">
                                        <span class="font-medium text-slate-800">Link Existing School Google Drive File</span>
                                    </label>
                                @endif
                            </div>
                        </div>

                        <div id="upload_box" class="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-5">
                            <label for="file" class="block text-xs font-semibold text-slate-700 mb-1">
                                Select Academic Document File
                            </label>
                            <input id="file" name="file" type="file"
                                   class="block w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                            <p class="text-[11px] text-slate-500 mt-2">
                                Allowed formats: <strong>{{ implode(', ', array_map(fn($e) => '.'.$e, $task->documentType?->normalizedExtensions() ?? [])) }}</strong> ·
                                Max file size: <strong>{{ $task->documentType?->max_file_size_mb }} MB</strong> · Executable/script files are blocked.
                            </p>
                        </div>

                        <div id="drive_link_box" class="hidden space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Google Drive File ID or School Workspace URL</label>
                                <input type="text" name="drive_file_id_or_url" placeholder="https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view"
                                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                <p class="text-[11px] text-slate-500 mt-1">Never use public "Anyone with the link" URLs; file must be shared within school Workspace scope.</p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">File Name (with extension)</label>
                                    <input type="text" name="drive_file_name" placeholder="lesson-plan-november.pdf"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Approximate Size (MB)</label>
                                    <input type="number" step="0.1" name="file_size_mb" value="1.2"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="teacher_note" class="block text-xs font-semibold text-slate-700 mb-1">Teacher Note / Summary of Changes (Optional)</label>
                            <textarea id="teacher_note" name="teacher_note" rows="2" placeholder="Add context or note corrections made..."
                                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </div>

                        <button type="submit"
                                class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm cursor-pointer">
                            {{ $task->submission ? 'Submit Corrected Version' : 'Confirm & Submit Document' }}
                        </button>
                    </form>
                </div>
            @endif

            <!-- Submission Version & Review History Timeline (Section 6.1) -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Submission Version & Review History</h2>
                    @if($task->submission && $canReview)
                        <a href="{{ route('reviews.show', $task->submission) }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold">
                            Open Reviewer Workspace
                        </a>
                    @endif
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($task->submission?->versions ?? [] as $ver)
                        <div class="p-5 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded bg-slate-900 text-white text-xs font-bold">v{{ $ver->version_number }}</span>
                                    <span class="text-sm font-semibold text-slate-900">{{ $ver->drive_file_name }}</span>
                                    <span class="text-xs text-slate-500">({{ $ver->formattedSize() }} · {{ $ver->submission_mode }})</span>
                                </div>

                                @if($scopeAuth->canAccessSubmissionFile(auth()->user(), $ver))
                                    <a href="{{ route('submissions.versions.file', $ver) }}" target="_blank"
                                       class="inline-flex items-center px-3 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold">
                                        Open / Download File
                                    </a>
                                @else
                                    <span class="text-[11px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 px-2.5 py-1 rounded">
                                        Restricted File — Explicit Grant Required
                                    </span>
                                @endif
                            </div>

                            <div class="text-xs text-slate-500">
                                Uploaded by {{ $ver->uploader?->full_name }} on {{ $ver->uploaded_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                                · Drive ID: <code class="text-slate-700">{{ $ver->drive_file_id }}</code>
                            </div>

                            @if($ver->teacher_note)
                                <div class="text-xs text-slate-700 bg-slate-50 rounded-lg p-3 border border-slate-200">
                                    <span class="font-semibold">Teacher Note:</span> {{ $ver->teacher_note }}
                                </div>
                            @endif

                            @foreach($ver->reviews as $rev)
                                <div class="rounded-lg p-3 text-xs border {{ $rev->decision === 'APPROVED' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
                                    <div class="font-bold">
                                        {{ $rev->decision === 'APPROVED' ? 'APPROVED' : 'RETURNED FOR CORRECTION' }}
                                        by {{ $rev->reviewer?->full_name }}
                                        ({{ $rev->reviewed_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }})
                                    </div>
                                    @if($rev->remarks)
                                        <p class="mt-1">{{ $rev->remarks }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-slate-500">No document versions have been submitted for this task yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Column: Prefilled Academic Context & Mapped Drive Folder -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3 text-xs">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Task Specification</h3>
                <div>
                    <span class="text-slate-500 block">Current Status</span>
                    <div class="mt-1">@include('partials.status-badge', ['task' => $task])</div>
                </div>
                <div>
                    <span class="text-slate-500 block">Academic Year / Period</span>
                    <span class="font-semibold text-slate-900">{{ $task->academicYear?->name }} · {{ $task->period_label }}</span>
                </div>
                <div>
                    <span class="text-slate-500 block">Class / Section / Subject</span>
                    <span class="font-semibold text-slate-900">
                        {{ $task->teachingAllocation?->schoolClass?->name }} — Section {{ $task->teachingAllocation?->section?->name }}
                        ({{ $task->teachingAllocation?->subject?->name }})
                    </span>
                </div>
                <div>
                    <span class="text-slate-500 block">Assigned Teacher</span>
                    <span class="font-semibold text-slate-900">{{ $task->assignedTeacher?->full_name }}</span>
                </div>
                <div>
                    <span class="text-slate-500 block">Document Category & Sensitivity</span>
                    <span class="font-semibold text-slate-900">{{ $task->documentType?->folder_name }}</span>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold {{ $task->documentType?->is_restricted ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700' }}">
                        {{ $task->documentType?->sensitivity_level }}
                    </span>
                </div>
                <div>
                    <span class="text-slate-500 block">Submission Deadline</span>
                    <span class="font-semibold text-slate-900">
                        {{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                    </span>
                </div>
                @if($task->instructions)
                    <div>
                        <span class="text-slate-500 block">Instructions</span>
                        <p class="text-slate-700 mt-0.5 leading-relaxed">{{ $task->instructions }}</p>
                    </div>
                @endif
            </div>

            <div class="bg-slate-900 text-slate-200 rounded-xl p-5 space-y-2 text-xs">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-indigo-400">Google Drive Destination</div>
                <p class="text-slate-300 leading-relaxed break-words">{{ $canonicalHierarchy }}</p>
                @if($folderMapping)
                    <div class="pt-2 border-t border-slate-800 text-[11px] text-emerald-400">
                        Verified Folder ID: <code>{{ $folderMapping->drive_folder_id }}</code>
                    </div>
                @endif
                <div class="text-[11px] text-slate-400">Policy: Least-privilege Workspace access · Public links disabled.</div>
            </div>
        </div>
    </div>
@endsection
