@extends('layouts.app')

@section('title', 'Task Assignment & Schedule')
@section('heading', 'Task Assignment & Idempotent Schedule Generator')
@section('subheading', 'Create tasks strictly against active teaching allocations or bulk-generate recurring schedules idempotently')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Create One-Time Task -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Assign Single Academic Task</h2>
            <p class="text-xs text-slate-500 mt-0.5">Select from active teaching allocations in your scope.</p>

            <form method="POST" action="{{ route('tasks.store') }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Teaching Allocation (Class · Section · Subject · Teacher) *</label>
                    <select name="teaching_allocation_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Select Allocation...</option>
                        @foreach($allocations as $a)
                            <option value="{{ $a->id }}">
                                {{ $a->schoolClass?->name }}-{{ $a->section?->name }} · {{ $a->subject?->name ?? 'Class Teacher' }} — {{ $a->teacher?->full_name }}
                                {{ $a->isVerified() ? '✓' : '(Unverified)' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Document Type *</label>
                        <select name="document_type_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach($documentTypes as $dt)
                                <option value="{{ $dt->id }}">{{ $dt->folder_code }} — {{ $dt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Period / Cycle Label *</label>
                        <input type="text" name="period_label" required placeholder="e.g. December 2026 / Pre-Board 1"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Task Title *</label>
                        <input type="text" name="title" required placeholder="e.g. Monthly Lesson Plan — Dec 2026"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Due Date & Time (IST) *</label>
                        <input type="datetime-local" name="due_at" required value="{{ now()->addDays(7)->format('Y-m-d\T17:00') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Direct Reviewer (Optional — defaults to scoped coordinator)</label>
                    <select name="reviewer_user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Use Scoped Coordinator Policy</option>
                        @foreach($reviewers as $rev)
                            <option value="{{ $rev->id }}">{{ $rev->full_name }} ({{ $rev->primaryRole?->name }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Instructions</label>
                    <textarea name="instructions" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                </div>

                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Assign Task & Notify Teacher
                </button>
            </form>
        </div>

        <!-- Idempotent Recurring Schedule Generator -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Generate Tasks from Approved Schedule (Idempotent)</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Generates tasks across verified teaching allocations only. Running twice for the same period safely skips existing tasks.
            </p>

            <form method="POST" action="{{ route('tasks.generate') }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Academic Year *</label>
                        <select name="academic_year_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach($academicYears as $yr)
                                <option value="{{ $yr->id }}" @selected($currentYear?->id === $yr->id)>{{ $yr->name }} ({{ $yr->status }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Document Type *</label>
                        <select name="document_type_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach($documentTypes as $dt)
                                <option value="{{ $dt->id }}">{{ $dt->folder_code }} — {{ $dt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Filter Class (Optional)</label>
                        <select name="class_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">All Authorised Classes</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Filter Subject (Optional)</label>
                        <select name="subject_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">All Taught Subjects</option>
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Period Label *</label>
                        <input type="text" name="period_label" required placeholder="e.g. January 2027"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Due Date & Time *</label>
                        <input type="datetime-local" name="due_at" required value="{{ now()->addDays(10)->format('Y-m-d\T17:00') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Title Prefix</label>
                    <input type="text" name="title_prefix" placeholder="e.g. Monthly Lesson Plan"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold cursor-pointer">
                    Generate Schedule Tasks Idempotently
                </button>
            </form>
        </div>
    </div>

    <!-- Existing Tasks Register with Deadline Update, Cancel & Reopen Controls -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Manage Assigned Tasks (Update Deadline / Reassign / Cancel / Reopen)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Task</th>
                        <th class="px-4 py-3 text-left">Context & Teacher</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Deadline / Reassign</th>
                        <th class="px-4 py-3 text-right">Governance Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($tasks as $task)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('tasks.show', $task) }}" class="font-semibold text-indigo-700 hover:underline">{{ $task->title }}</a>
                                <div class="text-slate-500">{{ $task->documentType?->name }} · {{ $task->period_label }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $task->teachingAllocation?->schoolClass?->name }}-{{ $task->teachingAllocation?->section?->name }} ({{ $task->teachingAllocation?->subject?->name }})</div>
                                <div class="text-slate-500">{{ $task->assignedTeacher?->full_name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @include('partials.status-badge', ['task' => $task])
                            </td>
                            <td class="px-4 py-3">
                                @if($task->workflow_status !== 'CANCELLED')
                                    <form method="POST" action="{{ route('tasks.update', $task) }}" class="flex flex-wrap items-center gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <input type="datetime-local" name="due_at" value="{{ $task->due_at?->timezone(\App\Models\SystemSetting::timezone())->format('Y-m-d\TH:i') }}"
                                               class="rounded border border-slate-300 px-2 py-1 text-[11px]">
                                        @if($task->workflow_status !== 'APPROVED')
                                            <select name="assigned_teacher_id" class="rounded border border-slate-300 px-2 py-1 text-[11px]">
                                                @foreach($teachers as $t)
                                                    <option value="{{ $t->id }}" @selected($task->assigned_teacher_id === $t->id)>{{ $t->full_name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <button type="submit" class="px-2.5 py-1 rounded bg-slate-800 text-white font-semibold cursor-pointer">Save</button>
                                    </form>
                                @else
                                    <span class="text-slate-400">Reason: {{ $task->cancellation_reason }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($task->workflow_status === 'APPROVED')
                                    <form method="POST" action="{{ route('tasks.reopen', $task) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Reopen reason..." class="rounded border border-slate-300 px-2 py-1 text-[11px] w-32">
                                        <button type="submit" class="px-2.5 py-1 rounded bg-amber-600 text-white font-semibold cursor-pointer">Reopen</button>
                                    </form>
                                @elseif($task->workflow_status !== 'CANCELLED' && auth()->user()->hasPermission('tasks.cancel'))
                                    <form method="POST" action="{{ route('tasks.cancel', $task) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Cancel reason..." class="rounded border border-slate-300 px-2 py-1 text-[11px] w-32">
                                        <button type="submit" class="px-2.5 py-1 rounded bg-rose-600 text-white font-semibold cursor-pointer">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $tasks->links() }}</div>
    </div>
@endsection
