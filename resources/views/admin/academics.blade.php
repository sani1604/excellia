@extends('layouts.app')

@section('title', 'Academic & Teacher Mapping Master')
@section('heading', 'Academic Masters & Teacher Mapping')
@section('subheading', 'Manage Teacher-Subject Mappings, Academic Years, Classes, Sections, and Global Subjects.')

@section('content')
    @php
        $activeTab = $tab ?? request('tab', 'allocations');
        $tabs = [
            'allocations' => 'Teacher-Subject Mapping (' . $allocations->total() . ')',
            'years' => 'Academic Years (' . $academicYears->count() . ')',
            'classes' => 'Classes Master (' . $classes->count() . ')',
            'sections' => 'Sections Master (' . $sections->count() . ')',
            'subjects' => 'Subjects Master (' . $subjects->count() . ')',
        ];
    @endphp

    <!-- Session Selector + Clean 5-Master Tab Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
            @foreach($tabs as $key => $label)
                <a href="{{ route('admin.academics', ['tab' => $key, 'academic_year_id' => $selectedYearId]) }}"
                   class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold border whitespace-nowrap transition {{ $activeTab === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.academics') }}" class="flex items-center gap-2 text-xs sm:text-sm shrink-0">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <label class="font-medium text-slate-600">Session:</label>
            <select name="academic_year_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 font-medium">
                @foreach($academicYears as $yr)
                    <option value="{{ $yr->id }}" @selected($yr->id == $selectedYearId)>
                        {{ $yr->name }} {{ $yr->is_current ? '(Current)' : '' }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if($activeTab === 'allocations')
        <!-- TAB 1: Teacher-Subject & Class-Teacher Mapping CRUD -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                <h2 class="text-base font-bold text-slate-900">Assign Teacher to Class</h2>
                <p class="text-sm text-slate-500 mt-0.5">Google Drive folders (00–05) are provisioned automatically when verified.</p>

                <form method="POST" action="{{ route('admin.allocations.store') }}" class="mt-4 space-y-3.5 text-sm">
                    @csrf
                    <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Class *</label>
                            <select name="class_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                                @foreach($classes as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Section *</label>
                            <select name="section_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                                @foreach($sections as $sec)
                                    <option value="{{ $sec->id }}">{{ $sec->schoolClass?->name }} — Sec {{ $sec->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Subject</label>
                        <select name="subject_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            <option value="">Class Teacher Only (No Subject)</option>
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Teacher *</label>
                        <select name="teacher_user_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2 pt-1 text-xs text-slate-700">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_class_teacher" value="1">
                            <span>Primary Class Teacher for this section</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="mark_verified" value="1" checked>
                            <span>Confirm & activate immediately</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                        Save Teacher Mapping
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-slate-900">Mapped Teachers & Classes</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($allocations as $a)
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 {{ $a->needs_reassignment ? 'bg-rose-50/50' : '' }}">
                            <div class="space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">
                                        {{ $a->schoolClass?->name }} — Section {{ $a->section?->name }}
                                    </span>
                                    <span class="text-sm text-slate-600">· {{ $a->subject?->name ?? 'Class Teacher' }}</span>
                                    @if($a->is_class_teacher)
                                        <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold">Class Teacher</span>
                                    @endif
                                    @if(!$a->is_active)
                                        <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs">Inactive</span>
                                    @endif
                                </div>
                                <div class="text-sm text-slate-700">
                                    Teacher: <strong>{{ $a->teacher?->full_name }}</strong>
                                    @if($a->needs_reassignment)
                                        <span class="ml-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-xs font-bold">Needs New Teacher</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                @if(!$a->isVerified())
                                    <form method="POST" action="{{ route('admin.allocations.verify', $a) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-600 text-white text-xs font-semibold cursor-pointer">
                                            Verify
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">Verified</span>
                                @endif

                                @if(auth()->user()->hasPermission('teacher_allocations.manage'))
                                    <details class="text-left">
                                        <summary class="px-3 py-1.5 rounded-xl {{ $a->needs_reassignment ? 'bg-rose-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-800' }} text-xs font-semibold cursor-pointer inline-block">
                                            {{ $a->needs_reassignment ? 'Reassign' : 'Edit' }}
                                        </summary>
                                        <form method="POST" action="{{ route('admin.allocations.update', $a) }}" class="mt-2 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5 w-64 text-xs">
                                            @csrf
                                            @method('PATCH')
                                            <label class="block font-semibold text-slate-700">Select Teacher</label>
                                            <select name="teacher_user_id" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                                @foreach($teachers as $t)
                                                    <option value="{{ $t->id }}" @selected($a->teacher_user_id === $t->id)>{{ $t->full_name }}</option>
                                                @endforeach
                                            </select>
                                            <label class="flex items-center gap-1.5"><input type="checkbox" name="is_class_teacher" value="1" @checked($a->is_class_teacher)><span>Class Teacher</span></label>
                                            <label class="flex items-center gap-1.5"><input type="checkbox" name="reassign_open_tasks" value="1" checked><span>Move open tasks to new teacher</span></label>
                                            <label class="flex items-center gap-1.5"><input type="checkbox" name="is_active" value="1" @checked($a->is_active)><span>Active</span></label>
                                            <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save</button>
                                        </form>
                                    </details>

                                    <form method="POST" action="{{ route('admin.allocations.destroy', $a) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold cursor-pointer">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-5 py-3 border-t border-slate-100">{{ $allocations->links() }}</div>
            </div>
        </div>
    @elseif($activeTab === 'years')
        <!-- TAB 2: Academic Years Master CRUD -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                <h2 class="text-base font-bold text-slate-900">Add Academic Session</h2>
                <form method="POST" action="{{ route('admin.academic-years.store') }}" class="mt-4 space-y-3.5 text-sm">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Session Name *</label>
                        <input type="text" name="name" required placeholder="e.g. 2028-29" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Start Date *</label>
                            <input type="date" name="start_date" required value="2028-04-01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">End Date *</label>
                            <input type="date" name="end_date" required value="2029-03-31" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Status *</label>
                        <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            <option value="DRAFT">Draft</option>
                            <option value="ACTIVE">Active</option>
                            <option value="ARCHIVED">Archived</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-semibold cursor-pointer">Add Session</button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-3">
                @foreach($academicYears as $yr)
                    <div class="bg-white rounded-2xl border {{ $yr->is_current ? 'border-indigo-400' : 'border-slate-200' }} p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-base font-bold text-slate-900">{{ $yr->name }}</span>
                                @if($yr->is_current)
                                    <span class="px-2.5 py-0.5 rounded-full bg-indigo-600 text-white text-xs font-semibold">Current Session</span>
                                @endif
                                <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold">{{ $yr->status }}</span>
                            </div>
                            <div class="text-sm text-slate-500 mt-0.5">{{ $yr->start_date?->format('d M Y') }} – {{ $yr->end_date?->format('d M Y') }}</div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.academic-years.update', $yr) }}" class="flex items-center gap-1.5 text-xs">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="rounded-xl border border-slate-300 px-2.5 py-1.5 bg-white">
                                    @foreach(['DRAFT' => 'Draft', 'ACTIVE' => 'Active', 'ARCHIVED' => 'Archived'] as $st => $lbl)
                                        <option value="{{ $st }}" @selected($yr->status === $st)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-semibold cursor-pointer">Update</button>
                            </form>

                            @if(!$yr->is_current && $yr->status !== 'ARCHIVED')
                                <form method="POST" action="{{ route('admin.academic-years.current', $yr) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-xs font-semibold cursor-pointer">Make Current</button>
                                </form>
                            @endif

                            @if(!$yr->is_current)
                                <form method="POST" action="{{ route('admin.academic-years.destroy', $yr) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 text-rose-700 text-xs font-semibold cursor-pointer">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif($activeTab === 'classes')
        <!-- TAB 3: Classes Master CRUD -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                <h2 class="text-base font-bold text-slate-900">Add Class</h2>
                <form method="POST" action="{{ route('admin.classes.store') }}" class="mt-4 space-y-3.5 text-sm">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Class Display Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Class X" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Code *</label>
                            <input type="text" name="code" required placeholder="C13" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Order *</label>
                            <input type="number" name="display_order" required value="16" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">School Wing *</label>
                        <select name="stage" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            <option value="PRE_PRIMARY">Pre-Primary</option>
                            <option value="PRIMARY">Primary</option>
                            <option value="MIDDLE">Middle</option>
                            <option value="SECONDARY">Secondary</option>
                            <option value="SENIOR_SECONDARY">Senior Secondary</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Drive Folder Label (Optional)</label>
                        <input type="text" name="drive_folder_label" placeholder="e.g. 16 – Class XIII" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-semibold cursor-pointer">Save Class</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                @foreach($classes as $c)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <span class="font-bold text-slate-900 text-sm">{{ $c->name }}</span>
                            <span class="text-xs text-slate-500 ml-1">({{ $c->code }} · {{ str_replace('_', ' ', $c->stage) }} · Order {{ $c->display_order }})</span>
                            @if(!$c->is_active)
                                <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs">Inactive</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.classes.update', $c) }}" class="flex flex-wrap items-center gap-1.5 text-xs">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" value="{{ $c->name }}" required class="w-28 rounded-lg border border-slate-300 px-2 py-1">
                                <input type="number" name="display_order" value="{{ $c->display_order }}" required class="w-14 rounded-lg border border-slate-300 px-2 py-1">
                                <select name="stage" class="rounded-lg border border-slate-300 px-2 py-1 bg-white">
                                    @foreach(['PRE_PRIMARY', 'PRIMARY', 'MIDDLE', 'SECONDARY', 'SENIOR_SECONDARY'] as $st)
                                        <option value="{{ $st }}" @selected($c->stage === $st)>{{ str_replace('_', ' ', $st) }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="drive_folder_label" value="{{ $c->drive_folder_label }}">
                                <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)>Active</label>
                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.classes.destroy', $c) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold cursor-pointer">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif($activeTab === 'sections')
        <!-- TAB 4: Sections Master CRUD -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                <h2 class="text-base font-bold text-slate-900">Add Section</h2>
                <form method="POST" action="{{ route('admin.sections.store') }}" class="mt-4 space-y-3.5 text-sm">
                    @csrf
                    <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Class *</label>
                        <select name="class_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Section Name *</label>
                        <input type="text" name="name" required placeholder="e.g. C" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Display Order</label>
                        <input type="number" name="display_order" value="3" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-semibold cursor-pointer">Add Section</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                @foreach($sections as $sec)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-sm font-bold text-slate-900">
                            {{ $sec->schoolClass?->name }} — Section {{ $sec->name }}
                            @if(!$sec->is_active)
                                <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs">Inactive</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.sections.update', $sec) }}" class="flex items-center gap-1.5 text-xs">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" value="{{ $sec->name }}" required class="w-16 rounded-lg border border-slate-300 px-2 py-1">
                                <input type="number" name="display_order" value="{{ $sec->display_order }}" required class="w-14 rounded-lg border border-slate-300 px-2 py-1">
                                <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($sec->is_active)>Active</label>
                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.sections.destroy', $sec) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold cursor-pointer">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <!-- TAB 5: Global Subjects Master CRUD -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                <h2 class="text-base font-bold text-slate-900">Add Global Subject</h2>
                <form method="POST" action="{{ route('admin.subjects.store') }}" class="mt-4 space-y-3.5 text-sm">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Subject Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Biology" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Short Code</label>
                            <input type="text" name="code" placeholder="BIO" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 uppercase">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Department</label>
                            <input type="text" name="category" placeholder="STEM" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Description</label>
                        <input type="text" name="description" placeholder="Optional curriculum note" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-semibold cursor-pointer">Add Subject</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                @foreach($subjects as $sub)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <span class="text-sm font-bold text-slate-900">{{ $sub->name }}</span>
                            <span class="text-xs text-slate-500 ml-1">({{ $sub->code ?: '—' }} · {{ $sub->category ?: 'General' }})</span>
                            @if(!$sub->is_active)
                                <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs">Inactive</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.subjects.update', $sub) }}" class="flex flex-wrap items-center gap-1.5 text-xs">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="code" value="{{ $sub->code }}" placeholder="Code" class="w-16 rounded-lg border border-slate-300 px-2 py-1 uppercase">
                                <input type="text" name="name" value="{{ $sub->name }}" required class="w-32 rounded-lg border border-slate-300 px-2 py-1">
                                <input type="text" name="category" value="{{ $sub->category }}" placeholder="Dept" class="w-24 rounded-lg border border-slate-300 px-2 py-1">
                                <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($sub->is_active)>Active</label>
                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.subjects.destroy', $sub) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold cursor-pointer">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
