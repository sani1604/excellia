@extends('layouts.app')

@section('title', 'Academic Masters & Allocations')
@section('heading', 'Academic Masters & Teaching Allocations')
@section('subheading', 'Configurable Academic Years, Classes (PG–XII), Sections, Global Subjects, Class Teachers, Timetable Verification, and Teacher Reassignment')

@section('content')
    <!-- Academic Years Register & Single Current Year Switcher -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Academic Years & Current Year Switcher</h2>
                <p class="text-xs text-slate-500">Only one academic year is marked Current at a time; switching preserves all historical task deadlines & ownership.</p>
            </div>
            <form method="GET" action="{{ route('admin.academics') }}" class="flex items-center gap-2 text-xs">
                <label class="font-semibold text-slate-600">Inspect Year:</label>
                <select name="academic_year_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-1.5">
                    @foreach($academicYears as $yr)
                        <option value="{{ $yr->id }}" @selected($yr->id == $selectedYearId)>
                            {{ $yr->name }} ({{ $yr->status }}{{ $yr->is_current ? ' · CURRENT' : '' }})
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            @foreach($academicYears as $yr)
                <div class="rounded-lg border {{ $yr->is_current ? 'border-indigo-400 bg-indigo-50/40' : 'border-slate-200 bg-slate-50/50' }} p-3.5 flex flex-col justify-between gap-3">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-slate-900">{{ $yr->name }}</span>
                            <div class="flex items-center gap-1.5">
                                @if($yr->is_current)
                                    <span class="px-2 py-0.5 rounded bg-indigo-600 text-white font-bold text-[10px]">CURRENT</span>
                                @endif
                                <span class="px-2 py-0.5 rounded bg-slate-200 text-slate-700 font-semibold text-[10px]">{{ $yr->status }}</span>
                            </div>
                        </div>
                        <div class="text-slate-500 mt-1">{{ $yr->start_date?->format('d M Y') }} – {{ $yr->end_date?->format('d M Y') }}</div>
                    </div>

                    @if(auth()->user()->hasPermission('academic_years.manage'))
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-200/70">
                            <form method="POST" action="{{ route('admin.academic-years.update', $yr) }}" class="flex items-center gap-1.5">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="rounded border border-slate-300 px-2 py-1 text-[11px] bg-white">
                                    @foreach(['DRAFT', 'ACTIVE', 'ARCHIVED'] as $st)
                                        <option value="{{ $st }}" @selected($yr->status === $st)>{{ $st }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-2 py-1 rounded bg-slate-800 text-white font-semibold cursor-pointer">Update</button>
                            </form>

                            @if(!$yr->is_current && $yr->status !== 'ARCHIVED')
                                <form method="POST" action="{{ route('admin.academic-years.current', $yr) }}">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                                        Set Current
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Teaching Allocation & Class Teacher Form -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Add Teaching / Class-Teacher Allocation</h2>
            <form method="POST" action="{{ route('admin.allocations.store') }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Academic Year *</label>
                    <select name="academic_year_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($academicYears as $yr)
                            <option value="{{ $yr->id }}" @selected($yr->id == $selectedYearId)>{{ $yr->name }} ({{ $yr->status }})</option>
                        @endforeach
                    </select>
                </div>
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
                                <option value="{{ $sec->id }}">{{ $sec->schoolClass?->name }} — Sec {{ $sec->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Subject (Nullable only if dedicated Class Teacher)</label>
                    <select name="subject_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">— Class Teacher Only —</option>
                        @foreach($subjects as $sub)
                            <option value="{{ $sub->id }}">{{ $sub->name }} ({{ $sub->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Teacher *</label>
                    <select name="teacher_user_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->full_name }} ({{ $t->employee_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_class_teacher" value="1">
                        <span>Primary Class Teacher for this Section</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="mark_verified" value="1" checked>
                        <span>Verify against approved timetable immediately</span>
                    </label>
                </div>
                <button type="submit" class="w-full py-2 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">
                    Save Teaching Allocation
                </button>
            </form>
        </div>

        <!-- Allocations Table with Inline Teacher Reassignment & Edit -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Active Teaching Allocations, Timetable Verification & Reassignment</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Class / Section</th>
                            <th class="px-4 py-2.5 text-left">Subject</th>
                            <th class="px-4 py-2.5 text-left">Teacher</th>
                            <th class="px-4 py-2.5 text-left">Verification</th>
                            <th class="px-4 py-2.5 text-right">Edit / Reassign</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($allocations as $a)
                            <tr class="{{ $a->needs_reassignment ? 'bg-rose-50/40' : '' }}">
                                <td class="px-4 py-2.5 font-semibold align-top">
                                    {{ $a->schoolClass?->name }} — Sec {{ $a->section?->name }}
                                    @if($a->is_class_teacher)
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px]">Class Teacher</span>
                                    @endif
                                    @if(!$a->is_active)
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 text-[10px]">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 align-top">{{ $a->subject?->name ?? 'Class Teacher Only' }}</td>
                                <td class="px-4 py-2.5 align-top">
                                    {{ $a->teacher?->full_name }}
                                    @if($a->needs_reassignment)
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 font-bold text-[10px]">Needs Reassignment</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 align-top">
                                    @if($a->isVerified())
                                        <span class="text-emerald-700 font-semibold">Verified ({{ $a->verified_at?->format('d M Y') }})</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.allocations.verify', $a) }}">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded bg-amber-600 text-white font-semibold cursor-pointer">Verify Now</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right align-top">
                                    @if(auth()->user()->hasPermission('teacher_allocations.manage'))
                                        <details class="inline-block text-left">
                                            <summary class="px-2.5 py-1 rounded {{ $a->needs_reassignment ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-800' }} font-semibold cursor-pointer inline-block">
                                                {{ $a->needs_reassignment ? 'Reassign Now' : 'Edit' }}
                                            </summary>
                                            <form method="POST" action="{{ route('admin.allocations.update', $a) }}" class="mt-2 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2 w-64">
                                                @csrf
                                                @method('PATCH')
                                                <div>
                                                    <label class="block font-semibold text-slate-700 mb-1">Assigned Teacher</label>
                                                    <select name="teacher_user_id" required class="w-full rounded border border-slate-300 px-2 py-1 bg-white">
                                                        @foreach($teachers as $t)
                                                            <option value="{{ $t->id }}" @selected($a->teacher_user_id === $t->id)>{{ $t->full_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <label class="flex items-center gap-1.5">
                                                    <input type="checkbox" name="is_class_teacher" value="1" @checked($a->is_class_teacher)>
                                                    <span>Primary Class Teacher</span>
                                                </label>
                                                <label class="flex items-center gap-1.5">
                                                    <input type="checkbox" name="reassign_open_tasks" value="1" checked>
                                                    <span>Reassign open unapproved tasks</span>
                                                </label>
                                                <label class="flex items-center gap-1.5">
                                                    <input type="checkbox" name="is_active" value="1" @checked($a->is_active)>
                                                    <span>Allocation Active</span>
                                                </label>
                                                <button type="submit" class="w-full py-1 rounded bg-indigo-600 text-white font-semibold cursor-pointer">
                                                    Save Allocation
                                                </button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100">{{ $allocations->links() }}</div>
        </div>
    </div>

    <!-- Quick Master Creation Cards: Academic Years, Classes, Sections, Subjects -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 text-xs">
        <form method="POST" action="{{ route('admin.academic-years.store') }}" class="bg-white rounded-xl border border-slate-200 p-4 space-y-2.5">
            @csrf
            <h3 class="font-bold text-slate-900">Add Academic Year ({{ $academicYears->count() }})</h3>
            <input type="text" name="name" required placeholder="e.g. 2028-29" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="start_date" required value="2028-04-01" class="rounded border border-slate-300 px-2 py-1.5">
                <input type="date" name="end_date" required value="2029-03-31" class="rounded border border-slate-300 px-2 py-1.5">
            </div>
            <select name="status" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
                <option value="DRAFT">DRAFT</option>
                <option value="ACTIVE">ACTIVE</option>
                <option value="ARCHIVED">ARCHIVED</option>
            </select>
            <button type="submit" class="w-full py-1.5 rounded bg-slate-900 text-white font-semibold cursor-pointer">Add Academic Year</button>
        </form>

        <form method="POST" action="{{ route('admin.classes.store') }}" class="bg-white rounded-xl border border-slate-200 p-4 space-y-2.5">
            @csrf
            <h3 class="font-bold text-slate-900">Classes Master ({{ $classes->count() }}: PG–XII)</h3>
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="code" required placeholder="Code (e.g. C13)" class="rounded border border-slate-300 px-2.5 py-1.5">
                <input type="number" name="display_order" required value="16" class="rounded border border-slate-300 px-2.5 py-1.5">
            </div>
            <input type="text" name="name" required placeholder="Display Name" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
            <select name="stage" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
                <option value="PRE_PRIMARY">PRE_PRIMARY</option>
                <option value="PRIMARY">PRIMARY</option>
                <option value="MIDDLE">MIDDLE</option>
                <option value="SECONDARY">SECONDARY</option>
                <option value="SENIOR_SECONDARY">SENIOR_SECONDARY</option>
            </select>
            <button type="submit" class="w-full py-1.5 rounded bg-slate-900 text-white font-semibold cursor-pointer">Add Class</button>
        </form>

        <form method="POST" action="{{ route('admin.sections.store') }}" class="bg-white rounded-xl border border-slate-200 p-4 space-y-2.5">
            @csrf
            <h3 class="font-bold text-slate-900">Sections Master ({{ $sections->count() }})</h3>
            <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">
            <select name="class_id" required class="w-full rounded border border-slate-300 px-2.5 py-1.5">
                @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <input type="text" name="name" required placeholder="Section Name (e.g. C)" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
            <input type="number" name="display_order" value="3" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
            <button type="submit" class="w-full py-1.5 rounded bg-slate-900 text-white font-semibold cursor-pointer">Add Section</button>
        </form>

        <form method="POST" action="{{ route('admin.subjects.store') }}" class="bg-white rounded-xl border border-slate-200 p-4 space-y-2.5">
            @csrf
            <h3 class="font-bold text-slate-900">Global Subjects ({{ $subjects->count() }})</h3>
            <input type="text" name="code" placeholder="Code (e.g. BIO)" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
            <input type="text" name="name" required placeholder="Subject Name (e.g. Biology)" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
            <input type="text" name="category" placeholder="Category (e.g. STEM)" class="w-full rounded border border-slate-300 px-2.5 py-1.5">
            <button type="submit" class="w-full py-1.5 rounded bg-slate-900 text-white font-semibold cursor-pointer">Add Subject</button>
        </form>
    </div>

    <!-- Expandable Classes, Sections & Subjects Edit Registers -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 text-xs">
        <details class="bg-white rounded-xl border border-slate-200 p-4">
            <summary class="font-bold text-slate-900 cursor-pointer">Edit Classes Register ({{ $classes->count() }})</summary>
            <div class="mt-3 space-y-2 max-h-72 overflow-y-auto pr-1">
                @foreach($classes as $c)
                    <form method="POST" action="{{ route('admin.classes.update', $c) }}" class="flex items-center gap-1.5 border-b border-slate-100 pb-2">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="name" value="{{ $c->name }}" required class="w-24 rounded border border-slate-300 px-1.5 py-1">
                        <input type="number" name="display_order" value="{{ $c->display_order }}" required class="w-12 rounded border border-slate-300 px-1.5 py-1">
                        <input type="hidden" name="stage" value="{{ $c->stage }}">
                        <input type="hidden" name="drive_folder_label" value="{{ $c->drive_folder_label }}">
                        <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)>On</label>
                        <button type="submit" class="ml-auto px-2 py-1 rounded bg-slate-800 text-white cursor-pointer">Save</button>
                    </form>
                @endforeach
            </div>
        </details>

        <details class="bg-white rounded-xl border border-slate-200 p-4">
            <summary class="font-bold text-slate-900 cursor-pointer">Edit Sections Register ({{ $sections->count() }})</summary>
            <div class="mt-3 space-y-2 max-h-72 overflow-y-auto pr-1">
                @foreach($sections as $sec)
                    <form method="POST" action="{{ route('admin.sections.update', $sec) }}" class="flex items-center gap-1.5 border-b border-slate-100 pb-2">
                        @csrf
                        @method('PATCH')
                        <span class="w-20 font-semibold truncate">{{ $sec->schoolClass?->name }}</span>
                        <input type="text" name="name" value="{{ $sec->name }}" required class="w-14 rounded border border-slate-300 px-1.5 py-1">
                        <input type="number" name="display_order" value="{{ $sec->display_order }}" required class="w-12 rounded border border-slate-300 px-1.5 py-1">
                        <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($sec->is_active)>On</label>
                        <button type="submit" class="ml-auto px-2 py-1 rounded bg-slate-800 text-white cursor-pointer">Save</button>
                    </form>
                @endforeach
            </div>
        </details>

        <details class="bg-white rounded-xl border border-slate-200 p-4">
            <summary class="font-bold text-slate-900 cursor-pointer">Edit Subjects Register ({{ $subjects->count() }})</summary>
            <div class="mt-3 space-y-2 max-h-72 overflow-y-auto pr-1">
                @foreach($subjects as $sub)
                    <form method="POST" action="{{ route('admin.subjects.update', $sub) }}" class="flex items-center gap-1.5 border-b border-slate-100 pb-2">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="code" value="{{ $sub->code }}" class="w-14 rounded border border-slate-300 px-1.5 py-1 font-mono">
                        <input type="text" name="name" value="{{ $sub->name }}" required class="w-28 rounded border border-slate-300 px-1.5 py-1">
                        <input type="hidden" name="category" value="{{ $sub->category }}">
                        <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($sub->is_active)>On</label>
                        <button type="submit" class="ml-auto px-2 py-1 rounded bg-slate-800 text-white cursor-pointer">Save</button>
                    </form>
                @endforeach
            </div>
        </details>
    </div>
@endsection
