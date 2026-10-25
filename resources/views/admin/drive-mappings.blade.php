@extends('layouts.app')

@section('title', 'Google Drive Mapping Master')
@section('heading', 'Google Drive Folder Mapping Master')
@section('subheading', 'Auto-sync canonical Google Drive folders or add/edit folder mappings.')

@section('content')
    <!-- Automatic Sync + Connection Status Card -->
    <div class="bg-emerald-50/70 rounded-2xl border border-emerald-200 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-bold text-emerald-950">Automatic Folder Setup is Active</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ ($driveMode ?? 'workspace_sandbox') === 'google_api' ? 'bg-emerald-600 text-white' : 'bg-white border border-emerald-300 text-emerald-800' }}">
                    {{ ($driveMode ?? 'workspace_sandbox') === 'google_api' ? 'Live Google Drive API' : 'Local School Vault Mode' }}
                </span>
            </div>
            <p class="text-sm text-emerald-900 mt-1">
                Folders (00–05) are created automatically whenever a teacher is mapped to a class & subject.
                Configure Google Drive API credentials anytime under <a href="{{ route('admin.settings', ['tab' => 'google']) }}" class="underline font-semibold">Integrations & Settings → Google Drive</a>.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.drive-mappings.provision') }}" class="shrink-0">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $currentYear?->id }}">
            <button type="submit" class="w-full sm:w-auto px-5 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold shadow-2xs cursor-pointer">
                Sync All School Folders Now
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Create / Map Folder Form -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
            <h2 class="text-base font-bold text-slate-900">Add / Override Folder Mapping</h2>
            <p class="text-sm text-slate-500 mt-0.5">Link a specific Google Drive folder ID or URL for a class & subject.</p>

            <form method="POST" action="{{ route('admin.drive-mappings.store') }}" class="mt-4 space-y-3.5 text-sm">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $currentYear?->id }}">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Class *</label>
                        <select name="class_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Section *</label>
                        <select name="section_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->schoolClass?->name }} - {{ $sec->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Subject *</label>
                        <select name="subject_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Teacher</label>
                        <select name="teacher_user_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                            <option value="">All Teachers</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Document Category</label>
                    <select name="document_type_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                        <option value="">Main Subject Folder</option>
                        @foreach($documentTypes as $dt)
                            <option value="{{ $dt->id }}">{{ $dt->folder_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Google Drive Folder Link or ID *</label>
                    <input type="text" name="drive_folder_id" required placeholder="1ExcelliaDriveFolderId_2026" class="w-full rounded-xl border border-slate-300 px-3.5 py-2">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Folder Display Path *</label>
                    <input type="text" name="drive_folder_name" required placeholder="Class X / Section A / Mathematics" class="w-full rounded-xl border border-slate-300 px-3.5 py-2">
                </div>
                <input type="hidden" name="verify_immediately" value="1">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save Folder Mapping
                </button>
            </form>
        </div>

        <!-- Clean Folder List -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">Mapped Folders ({{ $mappings->total() }})</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($mappings as $m)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5 min-w-0">
                            <div class="text-sm font-bold text-slate-900">
                                {{ $m->schoolClass?->name }} - Section {{ $m->section?->name }} · {{ $m->subject?->name }}
                                <span class="font-normal text-slate-500">({{ $m->teacher?->full_name ?? 'All Teachers' }})</span>
                            </div>
                            <div class="text-xs text-slate-600 break-words">
                                {{ $m->drive_folder_name }}
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            @if($m->is_active && $m->verified_at)
                                <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">Ready</span>
                            @else
                                <form method="POST" action="{{ route('admin.drive-mappings.verify', $m) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-600 text-white text-xs font-semibold cursor-pointer">Verify</button>
                                </form>
                            @endif

                            <details class="text-left">
                                <summary class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer inline-block">Edit</summary>
                                <form method="POST" action="{{ route('admin.drive-mappings.update', $m) }}" class="mt-2 p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2 w-64 text-xs">
                                    @csrf
                                    @method('PATCH')
                                    <label class="block font-semibold text-slate-700">Google Drive Link or ID</label>
                                    <input type="text" name="drive_folder_id" value="{{ $m->drive_folder_id }}" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                    <label class="block font-semibold text-slate-700">Folder Label</label>
                                    <input type="text" name="drive_folder_name" value="{{ $m->drive_folder_name }}" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                    <label class="flex items-center gap-1.5"><input type="checkbox" name="is_active" value="1" @checked($m->is_active)><span>Active</span></label>
                                    <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save</button>
                                </form>
                            </details>

                            <form method="POST" action="{{ route('admin.drive-mappings.destroy', $m) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold cursor-pointer">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">No folders set up yet. Tap "Sync All School Folders Now" above.</div>
                @endforelse
            </div>
            <div class="px-5 py-3 border-t border-slate-100">{{ $mappings->links() }}</div>
        </div>
    </div>
@endsection
