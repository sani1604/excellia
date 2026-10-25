@extends('layouts.app')

@section('title', 'Document Types')
@section('heading', 'Document Types')
@section('subheading', 'Manage the types of files teachers submit (Lesson Plans, Worksheets, Question Papers, etc.).')

@section('content')
    @php
        $privacyLabels = [
            'STANDARD' => 'Normal School Access',
            'RESTRICTED' => 'Confidential (Coordinator & Principal)',
            'STRICTLY_RESTRICTED' => 'Strictly Confidential (Exam / Answer Papers)',
        ];
        $cadenceLabels = [
            'MONTHLY' => 'Monthly',
            'WEEKLY' => 'Weekly',
            'PER_ASSESSMENT' => 'Every Exam / Test',
            'ONCE_YEARLY' => 'Once a Year',
        ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-900">Add Document Type</h2>
            <form method="POST" action="{{ route('admin.document-types.store') }}" class="mt-4 space-y-3.5 text-sm">
                @csrf
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Document Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Lab Practical Plan"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Folder No. *</label>
                        <input type="text" name="folder_code" required placeholder="06"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Short Code *</label>
                        <input type="text" name="code" required placeholder="LAB_PLAN"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    </div>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Google Drive Folder Label *</label>
                    <input type="text" name="folder_name" required placeholder="06 – Lab Practical Plans"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Allowed Files *</label>
                        <input type="text" name="extensions_csv" required value="pdf,docx,xlsx"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Max Size (MB) *</label>
                        <input type="number" name="max_file_size_mb" required value="25"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    </div>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Who Can Open Files? *</label>
                    <select name="sensitivity_level" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                        @foreach($privacyLabels as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Usual Schedule</label>
                    <select name="default_due_rule" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                        @foreach($cadenceLabels as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save Document Type
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">Current Document Types</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($documentTypes as $dt)
                    <div class="p-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-base font-bold text-slate-900">{{ $dt->folder_name }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $dt->sensitivity_level === 'STRICTLY_RESTRICTED' ? 'bg-rose-100 text-rose-800' : ($dt->is_restricted ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $privacyLabels[$dt->sensitivity_level] ?? 'Normal' }}
                                </span>
                                @if(!$dt->is_active)
                                    <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs">Inactive</span>
                                @endif
                            </div>
                            <div class="text-sm text-slate-600">
                                Allowed: <strong>{{ strtoupper(implode(', ', $dt->normalizedExtensions())) }}</strong> · Up to {{ $dt->max_file_size_mb }} MB · {{ $cadenceLabels[$dt->default_due_rule] ?? $dt->default_due_rule }}
                            </div>
                        </div>

                        <details class="shrink-0 text-left">
                            <summary class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold cursor-pointer inline-block">
                                Edit
                            </summary>
                            <form method="POST" action="{{ route('admin.document-types.update', $dt) }}" class="mt-2 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5 w-64 text-xs">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" value="{{ $dt->name }}" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" placeholder="Name">
                                <input type="text" name="folder_name" value="{{ $dt->folder_name }}" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" placeholder="Folder Name">
                                <input type="text" name="extensions_csv" value="{{ implode(',', $dt->normalizedExtensions()) }}" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                <div class="grid grid-cols-2 gap-1.5">
                                    <input type="number" name="max_file_size_mb" value="{{ $dt->max_file_size_mb }}" required class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">
                                    <select name="sensitivity_level" class="rounded-lg border border-slate-300 bg-white px-1.5 py-1.5">
                                        @foreach($privacyLabels as $lvl => $lbl)
                                            <option value="{{ $lvl }}" @selected($dt->sensitivity_level === $lvl)>{{ explode(' ', $lbl)[0] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="hidden" name="default_due_rule" value="{{ $dt->default_due_rule }}">
                                <div class="flex items-center justify-between pt-1">
                                    <label class="flex items-center gap-1"><input type="checkbox" name="requires_review" value="1" @checked($dt->requires_review)>Coordinator Check</label>
                                    <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($dt->is_active)>Active</label>
                                </div>
                                <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save Changes</button>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
