@extends('layouts.app')

@section('title', 'Classes & Teachers')
@section('heading', 'Classes, Subjects & Teacher Assignments')
@section('subheading', 'Assign teachers to classes & subjects. Google Drive folders are created automatically when saved.')

@section('content')
    <!-- Session Selector Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.academics') }}" class="flex items-center gap-2 text-sm">
            <label class="font-medium text-slate-600">Viewing Session:</label>
            <select name="academic_year_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 font-medium">
                @foreach($academicYears as $yr)
                    <option value="{{ $yr->id }}" @selected($yr->id == $selectedYearId)>
                        {{ $yr->name }} {{ $yr->is_current ? '(Current Session)' : '(' . ucfirst(strtolower($yr->status)) . ')' }}
                    </option>
                @endforeach
            </select>
        </form>

        <div class="text-xs text-slate-500">
            {{ $classes->count() }} Classes · {{ $sections->count() }} Sections · {{ $subjects->count() }} Subjects
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Assign Teacher to Class & Subject -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-900">Assign Teacher to Class</h2>
            <p class="text-sm text-slate-500 mt-0.5">Select the class, section, subject, and teacher.</p>

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
                        <span>Also Class Teacher for this section</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="mark_verified" value="1" checked>
                        <span>Confirm & activate immediately (sets up Drive folders)</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save Teacher Assignment
                </button>
            </form>
        </div>

        <!-- Current Class-Teacher-Subject List -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">Assigned Teachers & Classes</h2>
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

                        <div class="flex items-center gap-2 shrink-0">
                            @if(!$a->isVerified())
                                <form method="POST" action="{{ route('admin.allocations.verify', $a) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-600 text-white text-xs font-semibold cursor-pointer">
                                        Confirm
                                    </button>
                                </form>
                            @else
                                <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">Active</span>
                            @endif

                            @if(auth()->user()->hasPermission('teacher_allocations.manage'))
                                <details class="text-left">
                                    <summary class="px-3 py-1.5 rounded-xl {{ $a->needs_reassignment ? 'bg-rose-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-800' }} text-xs font-semibold cursor-pointer inline-block">
                                        {{ $a->needs_reassignment ? 'Assign New Teacher' : 'Change' }}
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
                                        <label class="flex items-center gap-1.5">
                                            <input type="checkbox" name="is_class_teacher" value="1" @checked($a->is_class_teacher)>
                                            <span>Class Teacher</span>
                                        </label>
                                        <label class="flex items-center gap-1.5">
                                            <input type="checkbox" name="reassign_open_tasks" value="1" checked>
                                            <span>Move pending tasks to new teacher</span>
                                        </label>
                                        <label class="flex items-center gap-1.5">
                                            <input type="checkbox" name="is_active" value="1" @checked($a->is_active)>
                                            <span>Keep active</span>
                                        </label>
                                        <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">
                                            Save Changes
                                        </button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-5 py-3 border-t border-slate-100">{{ $allocations->links() }}</div>
        </div>
    </div>

    <!-- Collapsible School Setup Drawers (Sessions, Classes, Sections, Subjects) -->
    <details class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <summary class="px-6 py-4 font-bold text-base text-slate-900 cursor-pointer flex items-center justify-between hover:bg-slate-50">
            <span>Manage Sessions, Classes, Sections & Subjects</span>
            <span class="text-xs font-medium text-indigo-600">Click to open</span>
        </summary>

        <div class="p-6 border-t border-slate-100 space-y-6">
            <!-- Academic Sessions -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 mb-3">Academic Sessions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    @foreach($academicYears as $yr)
                        <div class="rounded-xl border {{ $yr->is_current ? 'border-indigo-400 bg-indigo-50/40' : 'border-slate-200 bg-slate-50' }} p-4 flex flex-col justify-between gap-3">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-900">{{ $yr->name }}</span>
                                    @if($yr->is_current)
                                        <span class="px-2.5 py-0.5 rounded-full bg-indigo-600 text-white text-xs font-semibold">Current</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700 text-xs">{{ ucfirst(strtolower($yr->status)) }}</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 mt-1">{{ $yr->start_date?->format('d M Y') }} – {{ $yr->end_date?->format('d M Y') }}</div>
                            </div>

                            @if(auth()->user()->hasPermission('academic_years.manage'))
                                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-200/70 text-xs">
                                    <form method="POST" action="{{ route('admin.academic-years.update', $yr) }}" class="flex items-center gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-lg border border-slate-300 px-2 py-1 bg-white">
                                            @foreach(['DRAFT' => 'Draft', 'ACTIVE' => 'Active', 'ARCHIVED' => 'Archived'] as $st => $stLabel)
                                                <option value="{{ $st }}" @selected($yr->status === $st)>{{ $stLabel }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-800 text-white font-semibold cursor-pointer">Save</button>
                                    </form>

                                    @if(!$yr->is_current && $yr->status !== 'ARCHIVED')
                                        <form method="POST" action="{{ route('admin.academic-years.current', $yr) }}">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">
                                                Make Current
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Add New Session / Class / Section / Subject -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <form method="POST" action="{{ route('admin.academic-years.store') }}" class="rounded-xl border border-slate-200 p-4 space-y-2.5">
                    @csrf
                    <h4 class="font-bold text-slate-900 text-sm">Add New Session</h4>
                    <input type="text" name="name" required placeholder="e.g. 2028-29" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="start_date" required value="2028-04-01" class="rounded-lg border border-slate-300 px-2 py-1.5">
                        <input type="date" name="end_date" required value="2029-03-31" class="rounded-lg border border-slate-300 px-2 py-1.5">
                    </div>
                    <select name="status" class="w-full rounded-lg border border-slate-300 px-2.5 py-2 bg-white">
                        <option value="DRAFT">Draft</option>
                        <option value="ACTIVE">Active</option>
                    </select>
                    <button type="submit" class="w-full py-2 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Add Session</button>
                </form>

                <form method="POST" action="{{ route('admin.classes.store') }}" class="rounded-xl border border-slate-200 p-4 space-y-2.5">
                    @csrf
                    <h4 class="font-bold text-slate-900 text-sm">Add Class</h4>
                    <input type="text" name="name" required placeholder="Class Name (e.g. Class XIII)" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="code" required placeholder="Short Code" class="rounded-lg border border-slate-300 px-2.5 py-2">
                        <input type="number" name="display_order" required value="16" class="rounded-lg border border-slate-300 px-2.5 py-2">
                    </div>
                    <select name="stage" class="w-full rounded-lg border border-slate-300 px-2.5 py-2 bg-white">
                        <option value="PRE_PRIMARY">Pre-Primary</option>
                        <option value="PRIMARY">Primary</option>
                        <option value="MIDDLE">Middle</option>
                        <option value="SECONDARY">Secondary</option>
                        <option value="SENIOR_SECONDARY">Senior Secondary</option>
                    </select>
                    <button type="submit" class="w-full py-2 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Add Class</button>
                </form>

                <form method="POST" action="{{ route('admin.sections.store') }}" class="rounded-xl border border-slate-200 p-4 space-y-2.5">
                    @csrf
                    <h4 class="font-bold text-slate-900 text-sm">Add Section</h4>
                    <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">
                    <select name="class_id" required class="w-full rounded-lg border border-slate-300 px-2.5 py-2 bg-white">
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" required placeholder="Section Letter (e.g. C)" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <input type="hidden" name="display_order" value="3">
                    <button type="submit" class="w-full py-2 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Add Section</button>
                </form>

                <form method="POST" action="{{ route('admin.subjects.store') }}" class="rounded-xl border border-slate-200 p-4 space-y-2.5">
                    @csrf
                    <h4 class="font-bold text-slate-900 text-sm">Add Subject</h4>
                    <input type="text" name="name" required placeholder="Subject Name (e.g. Biology)" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <input type="text" name="code" placeholder="Short Code (e.g. BIO)" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <input type="text" name="category" placeholder="Group (e.g. Science)" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <button type="submit" class="w-full py-2 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Add Subject</button>
                </form>
            </div>
        </div>
    </details>
@endsection
