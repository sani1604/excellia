@extends('layouts.app')

@section('title', 'Google Drive Folder Mappings')
@section('heading', 'Google Drive Folder Mapping Master')
@section('subheading', 'Canonical Drive Folder IDs for EXCELLIA SCHOOL – ACADEMIC SHARING – 2026–27 (No public links permitted)')

@section('content')
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div>
            <span class="font-bold text-slate-900">Canonical Repository Provisioner</span>
            <p class="text-slate-500 mt-0.5">Idempotently generates & verifies missing canonical Drive folder mappings (`Class / Section / Subject – Teacher / 00–05`) for verified teaching allocations.</p>
        </div>
        <form method="POST" action="{{ route('admin.drive-mappings.provision') }}" class="flex items-center gap-2">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $currentYear?->id }}">
            <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold cursor-pointer">
                Auto-Provision Missing Canonical Folders ({{ $currentYear?->name ?? 'Current Year' }})
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Map Canonical Google Drive Folder</h2>
            <p class="text-xs text-slate-500 mt-0.5">Mappings are accepted only for subjects actively taught in the selected class/section.</p>

            <form method="POST" action="{{ route('admin.drive-mappings.store') }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $currentYear?->id }}">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Class *</label>
                        <select name="class_id" required class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Section *</label>
                        <select name="section_id" required class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->schoolClass?->name }} - {{ $sec->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Subject *</label>
                        <select name="subject_id" required class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Teacher</label>
                        <select name="teacher_user_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            <option value="">All Subject Teachers</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Document Category</label>
                    <select name="document_type_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Subject Root Folder</option>
                        @foreach($documentTypes as $dt)
                            <option value="{{ $dt->id }}">{{ $dt->folder_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Canonical Drive Folder ID or URL *</label>
                    <input type="text" name="drive_folder_id" required placeholder="1ExcelliaDriveFolderId_2026" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Folder Display Path *</label>
                    <input type="text" name="drive_folder_name" required placeholder="14 – Class X / Section A / Mathematics / 01 – Lesson Plans" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="verify_immediately" value="1" checked>
                    <span>Verify & activate folder mapping immediately</span>
                </label>
                <button type="submit" class="w-full py-2 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save Drive Mapping</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Mapped School Repository Folders</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Context</th>
                            <th class="px-4 py-2.5 text-left">Drive Folder ID & Path</th>
                            <th class="px-4 py-2.5 text-left">Policy</th>
                            <th class="px-4 py-2.5 text-right">Status & Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($mappings as $m)
                            <tr>
                                <td class="px-4 py-2.5 align-top">
                                    <div class="font-semibold text-slate-900">{{ $m->schoolClass?->name }}-{{ $m->section?->name }} · {{ $m->subject?->name }}</div>
                                    <div class="text-slate-500">{{ $m->teacher?->full_name ?? 'All' }} · {{ $m->documentType?->folder_code ?? 'Root' }}</div>
                                </td>
                                <td class="px-4 py-2.5 align-top">
                                    <code class="text-indigo-700 font-semibold">{{ $m->drive_folder_id }}</code>
                                    <div class="text-slate-500 truncate max-w-xs" title="{{ $m->drive_folder_name }}">{{ $m->drive_folder_name }}</div>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-[11px] align-top">{{ $m->access_policy_key }}</td>
                                <td class="px-4 py-2.5 text-right align-top space-y-1.5">
                                    @if($m->is_active && $m->verified_at)
                                        <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">Verified</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.drive-mappings.verify', $m) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded bg-amber-600 text-white font-semibold cursor-pointer">Verify</button>
                                        </form>
                                    @endif

                                    <details class="inline-block text-left">
                                        <summary class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold cursor-pointer inline-block">Edit</summary>
                                        <form method="POST" action="{{ route('admin.drive-mappings.update', $m) }}" class="mt-2 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2 w-64">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="drive_folder_id" value="{{ $m->drive_folder_id }}" required class="w-full rounded border border-slate-300 px-2 py-1">
                                            <input type="text" name="drive_folder_name" value="{{ $m->drive_folder_name }}" required class="w-full rounded border border-slate-300 px-2 py-1">
                                            <label class="flex items-center gap-1.5"><input type="checkbox" name="is_active" value="1" @checked($m->is_active)><span>Active</span></label>
                                            <button type="submit" class="w-full py-1 rounded bg-indigo-600 text-white font-semibold cursor-pointer">Save Mapping</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100">{{ $mappings->links() }}</div>
        </div>
    </div>
@endsection
