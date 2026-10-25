@extends('layouts.app')

@section('title', 'Assign Tasks')
@section('heading', 'Assign Tasks to Teachers')
@section('subheading', 'Create tasks for all classes at once (e.g., Monthly Lesson Plan) or assign a task to one teacher.')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Option 1: Bulk Create for All Classes/Subjects -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-900">Create Tasks for Multiple Teachers at Once</h2>
            <p class="text-sm text-slate-500 mt-0.5">
                Creates tasks for your classes automatically. If a task already exists for the same month/exam, it will not be duplicated.
            </p>

            <form method="POST" action="{{ route('tasks.generate') }}" class="mt-5 space-y-4 text-sm">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $currentYear?->id ?? $academicYears->first()?->id }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">What document is needed? *</label>
                        <select name="document_type_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($documentTypes as $dt)
                                <option value="{{ $dt->id }}">{{ $dt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Month or Exam Name *</label>
                        <input type="text" name="period_label" required placeholder="e.g. November 2026 or Unit Test 2"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">For Which Class?</label>
                        <select name="class_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            <option value="">All My Classes</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">For Which Subject?</label>
                        <select name="subject_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Due Date & Time *</label>
                        <input type="datetime-local" name="due_at" required value="{{ now()->addDays(10)->format('Y-m-d\T17:00') }}"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Task Heading (Optional)</label>
                        <input type="text" name="title_prefix" placeholder="e.g. Monthly Lesson Plan"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-2xs cursor-pointer">
                    Create Tasks for Teachers
                </button>
            </form>
        </div>

        <!-- Option 2: Assign to One Specific Teacher -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-900">Assign to One Teacher</h2>
            <p class="text-sm text-slate-500 mt-0.5">Use this when you want to assign a single task to one class & teacher.</p>

            <form method="POST" action="{{ route('tasks.store') }}" class="mt-5 space-y-4 text-sm">
                @csrf
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Choose Class, Subject & Teacher *</label>
                    <select name="teaching_allocation_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                        <option value="">Select Teacher & Class...</option>
                        @foreach($allocations as $a)
                            <option value="{{ $a->id }}">
                                {{ $a->schoolClass?->name }}-{{ $a->section?->name }} · {{ $a->subject?->name ?? 'Class Teacher' }} — {{ $a->teacher?->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Document Category *</label>
                        <select name="document_type_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($documentTypes as $dt)
                                <option value="{{ $dt->id }}">{{ $dt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Month or Exam Name *</label>
                        <input type="text" name="period_label" required placeholder="e.g. December 2026"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Task Title *</label>
                        <input type="text" name="title" required placeholder="e.g. December Lesson Plan"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Due Date & Time *</label>
                        <input type="datetime-local" name="due_at" required value="{{ now()->addDays(7)->format('Y-m-d\T17:00') }}"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Short Note for Teacher (Optional)</label>
                    <input type="text" name="instructions" placeholder="Any helpful instruction..."
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold cursor-pointer">
                    Assign Single Task
                </button>
            </form>
        </div>
    </div>

    <!-- Clean Assigned Tasks List (Options tucked neatly inside Edit button!) -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">Recently Assigned Tasks</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach($tasks as $task)
                <div class="p-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('tasks.show', $task) }}" class="text-base font-semibold text-indigo-700 hover:underline">{{ $task->title }}</a>
                            @include('partials.status-badge', ['task' => $task])
                        </div>
                        <div class="text-sm text-slate-600">
                            <strong>{{ $task->assignedTeacher?->full_name }}</strong> ·
                            {{ $task->teachingAllocation?->schoolClass?->name }}-{{ $task->teachingAllocation?->section?->name }} ·
                            {{ $task->teachingAllocation?->subject?->name }} · {{ $task->period_label }}
                        </div>
                        <div class="text-xs text-slate-500">
                            Due: {{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                            @if($task->workflow_status === 'CANCELLED')
                                · Cancelled ({{ $task->cancellation_reason }})
                            @endif
                        </div>
                    </div>

                    @if($task->workflow_status !== 'CANCELLED')
                        <details class="shrink-0">
                            <summary class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer inline-block">
                                Change Date / Options
                            </summary>
                            <div class="mt-2 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3 w-72 text-xs">
                                <form method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <label class="block font-semibold text-slate-700">New Due Date</label>
                                    <input type="datetime-local" name="due_at" value="{{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('Y-m-d\TH:i') }}"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                    @if($task->workflow_status !== 'APPROVED')
                                        <label class="block font-semibold text-slate-700 pt-1">Change Teacher</label>
                                        <select name="assigned_teacher_id" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                            @foreach($teachers as $t)
                                                <option value="{{ $t->id }}" @selected($task->assigned_teacher_id === $t->id)>{{ $t->full_name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save Changes</button>
                                </form>

                                @if($task->workflow_status === 'APPROVED')
                                    <form method="POST" action="{{ route('tasks.reopen', $task) }}" class="pt-2 border-t border-slate-200 space-y-1.5">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Reason to reopen..." class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                        <button type="submit" class="w-full py-1.5 rounded-lg bg-amber-600 text-white font-semibold cursor-pointer">Reopen Approved Task</button>
                                    </form>
                                @elseif(auth()->user()->hasPermission('tasks.cancel'))
                                    <form method="POST" action="{{ route('tasks.cancel', $task) }}" class="pt-2 border-t border-slate-200 space-y-1.5">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Reason to cancel..." class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                        <button type="submit" class="w-full py-1.5 rounded-lg bg-rose-600 text-white font-semibold cursor-pointer">Cancel Task</button>
                                    </form>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t border-slate-100">{{ $tasks->links() }}</div>
    </div>
@endsection
