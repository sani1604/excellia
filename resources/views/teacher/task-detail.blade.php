@extends('layouts.app')

@section('title', $task->title)
@section('heading', $task->title)
@section('subheading', ($task->teachingAllocation?->schoolClass?->name ?? '') . ' — Section ' . ($task->teachingAllocation?->section?->name ?? '') . ' · ' . ($task->teachingAllocation?->subject?->name ?? '') . ' · ' . $task->period_label)

@section('content')
    <!-- Simple Info Strip at Top -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-6 text-sm">
            <div>
                <span class="text-slate-500 block text-xs">Document Category</span>
                <span class="font-semibold text-slate-900">{{ $task->documentType?->name }}</span>
            </div>
            <div>
                <span class="text-slate-500 block text-xs">Due Date</span>
                <span class="font-semibold text-slate-900">{{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}</span>
            </div>
            <div>
                <span class="text-slate-500 block text-xs">Teacher</span>
                <span class="font-semibold text-slate-900">{{ $task->assignedTeacher?->full_name }}</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.status-badge', ['task' => $task])
            @if($task->submission && $canReview)
                <a href="{{ route('reviews.show', $task->submission) }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">
                    Check & Approve
                </a>
            @endif
        </div>
    </div>

    @if($task->instructions)
        <div class="rounded-2xl bg-indigo-50/70 border border-indigo-200 px-5 py-4 text-sm text-slate-800">
            <strong class="text-indigo-950">Instructions:</strong> {{ $task->instructions }}
        </div>
    @endif

    <!-- Coordinator Correction Feedback Banner -->
    @if($task->workflow_status === 'RETURNED_FOR_CORRECTION' && $task->submission?->currentVersion?->latestReview)
        <div class="rounded-2xl bg-amber-50 border-2 border-amber-300 p-5">
            <div class="text-sm font-bold text-amber-900">Coordinator Asked for Changes:</div>
            <p class="text-base text-amber-950 font-medium mt-1">
                "{{ $task->submission->currentVersion->latestReview->remarks }}"
            </p>
            <div class="text-xs text-amber-800 mt-2">
                — {{ $task->submission->currentVersion->latestReview->reviewer?->full_name }},
                {{ $task->submission->currentVersion->latestReview->reviewed_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
            </div>
        </div>
    @endif

    <!-- Super Simple Upload Box -->
    @if($canSubmit)
        @php
            $allowedExts = $task->documentType?->normalizedExtensions() ?? [];
            $acceptAttr = implode(',', array_map(fn($e) => '.'.$e, $allowedExts));
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">
                    {{ $task->submission ? 'Upload Corrected File' : 'Upload Your File' }}
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    Your file is saved automatically in the school's Google Drive folder for {{ $task->teachingAllocation?->schoolClass?->name }} {{ $task->teachingAllocation?->subject?->name }}.
                </p>
            </div>

            <form method="POST" action="{{ route('tasks.submit', $task) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" id="submission_mode_input" name="submission_mode" value="{{ in_array('PORTAL_UPLOAD', $allowedModes, true) ? 'PORTAL_UPLOAD' : 'DRIVE_LINK' }}">

                @if(in_array('PORTAL_UPLOAD', $allowedModes, true))
                    <div id="upload_box" class="rounded-2xl border-2 border-dashed border-indigo-200 bg-indigo-50/30 p-6 text-center space-y-3">
                        <label for="file" class="block text-sm font-semibold text-slate-800 cursor-pointer">
                            Choose file from your computer or phone
                        </label>
                        <input id="file" name="file" type="file" accept="{{ $acceptAttr }}"
                               class="mx-auto block w-full max-w-md text-sm text-slate-700 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                        <p class="text-xs text-slate-500">
                            Accepted file types: <strong>{{ strtoupper(implode(', ', $allowedExts)) }}</strong> (up to {{ $task->documentType?->max_file_size_mb }} MB)
                        </p>
                    </div>
                @endif

                @if(in_array('DRIVE_LINK', $allowedModes, true))
                    @if(in_array('PORTAL_UPLOAD', $allowedModes, true))
                        <div class="text-xs">
                            <button type="button"
                                    onclick="const dl=document.getElementById('drive_link_box');const ub=document.getElementById('upload_box');const mi=document.getElementById('submission_mode_input');if(dl.classList.contains('hidden')){dl.classList.remove('hidden');ub.classList.add('hidden');mi.value='DRIVE_LINK';this.textContent='← Switch back to normal file upload';}else{dl.classList.add('hidden');ub.classList.remove('hidden');mi.value='PORTAL_UPLOAD';this.textContent='Already uploaded in School Google Drive? Paste Drive link instead';}"
                                    class="text-indigo-600 hover:underline font-medium cursor-pointer">
                                Already uploaded in School Google Drive? Paste Drive link instead
                            </button>
                        </div>
                    @endif

                    <div id="drive_link_box" class="{{ in_array('PORTAL_UPLOAD', $allowedModes, true) ? 'hidden' : '' }} space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Paste School Google Drive Link</label>
                            <input type="text" name="drive_file_id_or_url" placeholder="https://drive.google.com/file/d/..."
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm">
                            <p class="text-xs text-slate-500 mt-1">Please share only within school accounts (do not use public "Anyone with link").</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">File Name</label>
                                <input type="text" name="drive_file_name" placeholder="e.g. lesson-plan.pdf"
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Approximate Size (MB)</label>
                                <input type="number" step="0.1" name="file_size_mb" value="1.0"
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm">
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    <label for="teacher_note" class="block text-sm font-medium text-slate-700 mb-1">Add a short note (Optional)</label>
                    <textarea id="teacher_note" name="teacher_note" rows="2" placeholder="Write a short note for your coordinator if needed..."
                              class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm"></textarea>
                </div>

                <button type="submit"
                        class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-xs cursor-pointer">
                    {{ $task->submission ? 'Submit Corrected File' : 'Submit File' }}
                </button>
            </form>
        </div>
    @endif

    <!-- Uploaded Files & Coordinator Feedback History -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">Uploaded Files & Coordinator Feedback</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($task->submission?->versions ?? [] as $ver)
                <div class="p-5 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold">Upload #{{ $ver->version_number }}</span>
                                <span class="text-sm font-semibold text-slate-900">{{ $ver->drive_file_name }}</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                Uploaded on {{ $ver->uploaded_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                                · {{ $ver->formattedSize() }}
                            </div>
                        </div>

                        @if($scopeAuth->canAccessSubmissionFile(auth()->user(), $ver))
                            <a href="{{ route('submissions.versions.file', $ver) }}" target="_blank"
                               class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-sm font-semibold">
                                View / Download File
                            </a>
                        @else
                            <span class="text-xs font-medium text-rose-700 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl">
                                Confidential File
                            </span>
                        @endif
                    </div>

                    @if($ver->teacher_note)
                        <div class="text-sm text-slate-700 bg-slate-50 rounded-xl p-3.5 border border-slate-200">
                            <strong>Teacher's Note:</strong> {{ $ver->teacher_note }}
                        </div>
                    @endif

                    @foreach($ver->reviews as $rev)
                        <div class="rounded-xl p-4 text-sm border {{ $rev->decision === 'APPROVED' ? 'bg-emerald-50 border-emerald-200 text-emerald-950' : 'bg-amber-50 border-amber-200 text-amber-950' }}">
                            <div class="font-bold">
                                {{ $rev->decision === 'APPROVED' ? 'Approved' : 'Returned for Correction' }}
                                by {{ $rev->reviewer?->full_name }}
                                <span class="font-normal text-xs opacity-75">({{ $rev->reviewed_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }})</span>
                            </div>
                            @if($rev->remarks)
                                <p class="mt-1">{{ $rev->remarks }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">No file uploaded yet.</div>
            @endforelse
        </div>
    </div>
@endsection
