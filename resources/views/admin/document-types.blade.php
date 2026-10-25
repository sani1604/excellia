@extends('layouts.app')

@section('title', 'Document Type Master')
@section('heading', 'Academic Document Type Master')
@section('subheading', 'Configure folder codes (00–05+), sensitivity policies, permitted extensions, maximum file sizes, and review rules')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Add Document Category</h2>
            <form method="POST" action="{{ route('admin.document-types.store') }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Code *</label>
                        <input type="text" name="code" required placeholder="LAB_MANUAL" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Folder Code *</label>
                        <input type="text" name="folder_code" required placeholder="06" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Display Name *</label>
                    <input type="text" name="name" required placeholder="Practical Lab Schedules" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Drive Subfolder Name *</label>
                    <input type="text" name="folder_name" required placeholder="06 – Practical Lab Schedules" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Allowed Extensions *</label>
                        <input type="text" name="extensions_csv" required value="pdf,docx,xlsx" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Max Size (MB) *</label>
                        <input type="number" name="max_file_size_mb" required value="25" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Sensitivity Level *</label>
                        <select name="sensitivity_level" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            <option value="STANDARD">STANDARD</option>
                            <option value="RESTRICTED">RESTRICTED</option>
                            <option value="STRICTLY_RESTRICTED">STRICTLY_RESTRICTED</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Default Cadence</label>
                        <select name="default_due_rule" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            <option value="MONTHLY">MONTHLY</option>
                            <option value="WEEKLY">WEEKLY</option>
                            <option value="PER_ASSESSMENT">PER_ASSESSMENT</option>
                            <option value="ONCE_YEARLY">ONCE_YEARLY</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full py-2 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save Document Category</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Configured Academic Document Types</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Folder & Name</th>
                            <th class="px-4 py-3 text-left">Formats & Size</th>
                            <th class="px-4 py-3 text-left">Sensitivity & Policy</th>
                            <th class="px-4 py-3 text-left">Cadence</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($documentTypes as $dt)
                            <tr>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-semibold text-slate-900">{{ $dt->folder_name }}</div>
                                    <div class="text-slate-500 font-mono">{{ $dt->code }} @if(!$dt->is_active) · <span class="text-rose-600">Inactive</span> @endif</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div>{{ implode(', ', array_map(fn($e) => '.'.$e, $dt->normalizedExtensions())) }}</div>
                                    <div class="text-slate-500">Max {{ $dt->max_file_size_mb }} MB</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="px-2 py-0.5 rounded font-bold {{ $dt->sensitivity_level === 'STRICTLY_RESTRICTED' ? 'bg-rose-600 text-white' : ($dt->is_restricted ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $dt->sensitivity_level }}
                                    </span>
                                    <div class="text-[11px] text-slate-500 mt-1">{{ $dt->access_policy_key }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700 align-top">{{ $dt->default_due_rule }}</td>
                                <td class="px-4 py-3 text-right align-top">
                                    <details class="inline-block text-left">
                                        <summary class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold cursor-pointer inline-block">
                                            Edit
                                        </summary>
                                        <form method="POST" action="{{ route('admin.document-types.update', $dt) }}" class="mt-2 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2 w-64">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="name" value="{{ $dt->name }}" required class="w-full rounded border border-slate-300 px-2 py-1" placeholder="Display Name">
                                            <input type="text" name="folder_name" value="{{ $dt->folder_name }}" required class="w-full rounded border border-slate-300 px-2 py-1" placeholder="Folder Name">
                                            <input type="text" name="extensions_csv" value="{{ implode(',', $dt->normalizedExtensions()) }}" required class="w-full rounded border border-slate-300 px-2 py-1" placeholder="pdf,docx">
                                            <div class="grid grid-cols-2 gap-1.5">
                                                <input type="number" name="max_file_size_mb" value="{{ $dt->max_file_size_mb }}" required class="rounded border border-slate-300 px-2 py-1">
                                                <select name="sensitivity_level" class="rounded border border-slate-300 px-1.5 py-1 bg-white">
                                                    @foreach(['STANDARD', 'RESTRICTED', 'STRICTLY_RESTRICTED'] as $lvl)
                                                        <option value="{{ $lvl }}" @selected($dt->sensitivity_level === $lvl)>{{ $lvl }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="text" name="default_due_rule" value="{{ $dt->default_due_rule }}" class="w-full rounded border border-slate-300 px-2 py-1">
                                            <div class="flex items-center justify-between">
                                                <label class="flex items-center gap-1"><input type="checkbox" name="requires_review" value="1" @checked($dt->requires_review)>Review</label>
                                                <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($dt->is_active)>Active</label>
                                            </div>
                                            <button type="submit" class="w-full py-1 rounded bg-indigo-600 text-white font-semibold cursor-pointer">Save</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
