@extends('layouts.app')

@section('title', 'Google Drive Folders')
@section('heading', 'School Google Drive Folders')
@section('subheading', 'Folders are created and organized automatically for every class, section, and subject.')

@section('content')
    <!-- Reassuring Automatic Sync Card -->
    <div class="bg-emerald-50/70 rounded-2xl border border-emerald-200 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-emerald-950">Automatic Folder Setup is Active</h2>
            <p class="text-sm text-emerald-900 mt-1">
                Whenever you assign a teacher to a class & subject, the school's Google Drive folders (00 to 05) are set up automatically.
                Tap the button on the right any time you want to check and sync all folders for {{ $currentYear?->name ?? 'the current session' }}.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.drive-mappings.provision') }}" class="shrink-0">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $currentYear?->id }}">
            <button type="submit" class="px-5 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold shadow-2xs cursor-pointer">
                Sync All School Folders Now
            </button>
        </form>
    </div>

    <!-- Clean Folder List -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">Connected Class & Subject Folders</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($mappings as $m)
                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <div class="text-sm font-bold text-slate-900">
                            {{ $m->schoolClass?->name }} - Section {{ $m->section?->name }} · {{ $m->subject?->name }}
                            <span class="font-normal text-slate-500">({{ $m->teacher?->full_name ?? 'All Subject Teachers' }})</span>
                        </div>
                        <div class="text-xs text-slate-600">
                            Folder: {{ $m->drive_folder_name }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if($m->is_active && $m->verified_at)
                            <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">Ready</span>
                        @else
                            <form method="POST" action="{{ route('admin.drive-mappings.verify', $m) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-600 text-white text-xs font-semibold cursor-pointer">Activate</button>
                            </form>
                        @endif

                        <details class="text-left">
                            <summary class="px-3 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer inline-block">Change</summary>
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
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">No folders set up yet. Click "Sync All School Folders Now" above.</div>
            @endforelse
        </div>
        <div class="px-5 py-3 border-t border-slate-100">{{ $mappings->links() }}</div>
    </div>

    <!-- Optional Manual Folder Link Drawer -->
    <details class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <summary class="px-6 py-4 font-bold text-sm text-slate-800 cursor-pointer flex items-center justify-between hover:bg-slate-50">
            <span>Link an Existing Google Drive Folder Manually (Optional)</span>
            <span class="text-xs font-medium text-indigo-600">Click to open</span>
        </summary>
        <form method="POST" action="{{ route('admin.drive-mappings.store') }}" class="p-6 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $currentYear?->id }}">
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
                    <option value="">All Subject Teachers</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Document Type</label>
                <select name="document_type_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                    <option value="">Main Subject Folder</option>
                    @foreach($documentTypes as $dt)
                        <option value="{{ $dt->id }}">{{ $dt->folder_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Google Drive Folder Link or ID *</label>
                <input type="text" name="drive_folder_id" required placeholder="Paste Google Drive folder link" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </div>
            <div class="sm:col-span-2">
                <label class="block font-medium text-slate-700 mb-1">Folder Display Name *</label>
                <input type="text" name="drive_folder_name" required placeholder="e.g. Class X / Section A / Mathematics" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </div>
            <div class="flex items-end">
                <input type="hidden" name="verify_immediately" value="1">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-semibold cursor-pointer">Save Folder</button>
            </div>
        </form>
    </details>
@endsection
