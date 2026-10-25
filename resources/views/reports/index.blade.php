@extends('layouts.app')

@section('title', 'Compliance Reports')
@section('heading', 'Academic Compliance & Escalation Reports')
@section('subheading', 'Class-wise, teacher-wise, subject-wise, and delay/escalation reports reconciled 1:1 with task-level records')

@section('content')
    <!-- Report Type Tabs -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $reportTabs = [
                    'class-wise' => 'Class-wise Report',
                    'teacher-wise' => 'Teacher-wise Compliance',
                    'subject-wise' => 'Subject-wise Report',
                    'overdue' => 'Delay & Escalation Report',
                ];
            @endphp
            @foreach($reportTabs as $key => $label)
                @if($key === 'teacher-wise' || auth()->user()->hasPermission('reports.view_scope') || auth()->user()->hasPermission('reports.view_school'))
                    <a href="{{ route('reports.index', array_merge($filters, ['tab' => $key])) }}"
                       class="px-3.5 py-2 rounded-lg text-xs font-semibold border transition {{ $tab === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>

        <a href="{{ route('reports.export', $filters) }}"
           class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">
            Export Reconciled CSV
        </a>
    </div>

    <!-- Filter Bar (Year, Class, Section, Subject, Teacher, Period, Status) -->
    <form method="GET" action="{{ route('reports.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 text-xs">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div>
            <label class="block text-slate-600 font-medium mb-1">Academic Year</label>
            <select name="academic_year_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                @foreach($academicYears as $yr)
                    <option value="{{ $yr->id }}" @selected(($filters['academic_year_id'] ?? '') == $yr->id)>{{ $yr->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Class</label>
            <select name="class_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                <option value="">All</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected(($filters['class_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Section</label>
            <select name="section_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                <option value="">All</option>
                @foreach($sections as $sec)
                    <option value="{{ $sec->id }}" @selected(($filters['section_id'] ?? '') == $sec->id)>{{ $sec->name }} (#{{ $sec->class_id }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Subject</label>
            <select name="subject_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                <option value="">All</option>
                @foreach($subjects as $s)
                    <option value="{{ $s->id }}" @selected(($filters['subject_id'] ?? '') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Teacher</label>
            <select name="teacher_user_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                <option value="">All</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected(($filters['teacher_user_id'] ?? '') == $t->id)>{{ $t->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Period</label>
            <input type="text" name="period_label" value="{{ $filters['period_label'] ?? '' }}" placeholder="e.g. Oct 2026"
                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
        </div>
        <div>
            <label class="block text-slate-600 font-medium mb-1">Status</label>
            <select name="status" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                <option value="">All</option>
                <option value="APPROVED" @selected(($filters['status'] ?? '') === 'APPROVED')>Approved</option>
                <option value="PENDING_REVIEW" @selected(($filters['status'] ?? '') === 'PENDING_REVIEW')>Pending Review</option>
                <option value="RETURNED_FOR_CORRECTION" @selected(($filters['status'] ?? '') === 'RETURNED_FOR_CORRECTION')>Returned</option>
                <option value="NOT_STARTED" @selected(($filters['status'] ?? '') === 'NOT_STARTED')>Not Started</option>
                <option value="OVERDUE" @selected(($filters['status'] ?? '') === 'OVERDUE')>Overdue</option>
                <option value="ESCALATED" @selected(($filters['status'] ?? '') === 'ESCALATED')>Escalated</option>
            </select>
        </div>
        <div class="flex items-end gap-1.5">
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Apply</button>
            <a href="{{ route('reports.index', ['tab' => $tab]) }}" class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700">Clear</a>
        </div>
    </form>

    <!-- Summary Strip Reconciled with Filtered Dataset -->
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 text-xs">
        <div class="bg-white rounded-xl border border-slate-200 p-3.5"><span class="text-slate-500">Expected</span><div class="text-xl font-bold mt-0.5">{{ $summary['expected'] }}</div></div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5"><span class="text-emerald-700">Approved</span><div class="text-xl font-bold text-emerald-600 mt-0.5">{{ $summary['approved'] }}</div></div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5"><span class="text-sky-700">Pending Review</span><div class="text-xl font-bold text-sky-600 mt-0.5">{{ $summary['pending_review'] }}</div></div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5"><span class="text-amber-700">Returned</span><div class="text-xl font-bold text-amber-600 mt-0.5">{{ $summary['returned'] }}</div></div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5"><span class="text-rose-700">Overdue</span><div class="text-xl font-bold text-rose-600 mt-0.5">{{ $summary['overdue'] }}</div></div>
        <div class="bg-indigo-600 text-white rounded-xl p-3.5"><span class="text-indigo-100">Completion %</span><div class="text-xl font-bold mt-0.5">{{ number_format($summary['completion_rate'], 1) }}%</div></div>
    </div>

    <!-- Aggregated Summary Table -->
    @if($tab !== 'overdue')
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-200 font-semibold text-sm text-slate-900">
                {{ $reportTabs[$tab] ?? 'Summary Report' }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            @if($tab === 'class-wise')
                                <th class="px-4 py-3 text-left">Class / Section</th>
                                <th class="px-4 py-3 text-left">Subject</th>
                                <th class="px-4 py-3 text-left">Teacher</th>
                            @elseif($tab === 'teacher-wise')
                                <th class="px-4 py-3 text-left">Employee Code</th>
                                <th class="px-4 py-3 text-left">Teacher</th>
                                <th class="px-4 py-3 text-left">Email</th>
                            @else
                                <th class="px-4 py-3 text-left">Subject</th>
                                <th class="px-4 py-3 text-left">Class</th>
                            @endif
                            <th class="px-4 py-3 text-right">Expected</th>
                            <th class="px-4 py-3 text-right">Approved</th>
                            <th class="px-4 py-3 text-right">Pending Sub.</th>
                            <th class="px-4 py-3 text-right">Pending Review</th>
                            <th class="px-4 py-3 text-right">Returned</th>
                            <th class="px-4 py-3 text-right">Overdue</th>
                            <th class="px-4 py-3 text-right">Compliance %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $r)
                            <tr>
                                @if($tab === 'class-wise')
                                    <td class="px-4 py-2.5 font-semibold">{{ $r['class_name'] }} — {{ $r['section_name'] }}</td>
                                    <td class="px-4 py-2.5">{{ $r['subject_name'] }}</td>
                                    <td class="px-4 py-2.5">{{ $r['teacher_name'] }}</td>
                                @elseif($tab === 'teacher-wise')
                                    <td class="px-4 py-2.5 font-mono">{{ $r['employee_code'] }}</td>
                                    <td class="px-4 py-2.5 font-semibold">{{ $r['teacher_name'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-500">{{ $r['teacher_email'] }}</td>
                                @else
                                    <td class="px-4 py-2.5 font-semibold">{{ $r['subject_name'] }}</td>
                                    <td class="px-4 py-2.5">{{ $r['class_name'] }}</td>
                                @endif
                                <td class="px-4 py-2.5 text-right font-medium">{{ $r['expected'] }}</td>
                                <td class="px-4 py-2.5 text-right text-emerald-700 font-semibold">{{ $r['approved'] }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $r['pending_submission'] }}</td>
                                <td class="px-4 py-2.5 text-right text-sky-700">{{ $r['pending_review'] }}</td>
                                <td class="px-4 py-2.5 text-right text-amber-700">{{ $r['returned'] }}</td>
                                <td class="px-4 py-2.5 text-right text-rose-700 font-semibold">{{ $r['overdue'] }}</td>
                                <td class="px-4 py-2.5 text-right font-bold">{{ number_format($r['completion_rate'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-6 text-center text-slate-500">No matching records.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Full 15-Column Task-Level Reconciliation Register (Section 7 Minimum Report Columns) -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 font-semibold text-sm text-slate-900">
            Task-Level Reconciliation Register (All Section 7 Columns)
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-3 py-2.5 text-left">Year</th>
                        <th class="px-3 py-2.5 text-left">Class/Sec</th>
                        <th class="px-3 py-2.5 text-left">Subject</th>
                        <th class="px-3 py-2.5 text-left">Teacher</th>
                        <th class="px-3 py-2.5 text-left">Doc Type & Period</th>
                        <th class="px-3 py-2.5 text-left">Due Date</th>
                        <th class="px-3 py-2.5 text-left">Submitted At</th>
                        <th class="px-3 py-2.5 text-left">Workflow Status</th>
                        <th class="px-3 py-2.5 text-left">Overdue</th>
                        <th class="px-3 py-2.5 text-left">Reviewer & Reviewed At</th>
                        <th class="px-3 py-2.5 text-left">Latest Remarks</th>
                        <th class="px-3 py-2.5 text-left">Escalation / Last Alert</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($taskRecords as $rec)
                        <tr>
                            <td class="px-3 py-2.5 whitespace-nowrap">{{ $rec['academic_year'] }}</td>
                            <td class="px-3 py-2.5 whitespace-nowrap font-semibold">{{ $rec['class_name'] }}-{{ $rec['section_name'] }}</td>
                            <td class="px-3 py-2.5">{{ $rec['subject_name'] }}</td>
                            <td class="px-3 py-2.5">{{ $rec['teacher_name'] }}</td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-slate-900">{{ $rec['document_type'] }}</div>
                                <div class="text-slate-500">{{ $rec['period'] }}</div>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">{{ $rec['due_date'] }}</td>
                            <td class="px-3 py-2.5 whitespace-nowrap">{{ $rec['submission_timestamp'] ?: '—' }}</td>
                            <td class="px-3 py-2.5">{{ $rec['workflow_status_label'] }}</td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                @if($rec['is_overdue'])
                                    <span class="text-rose-700 font-bold">YES ({{ $rec['overdue_days'] }}d)</span>
                                @else
                                    <span class="text-slate-400">No</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div>{{ $rec['reviewer'] }}</div>
                                <div class="text-slate-400">{{ $rec['last_review_timestamp'] ?: '' }}</div>
                            </td>
                            <td class="px-3 py-2.5 max-w-48 truncate" title="{{ $rec['latest_remarks'] }}">{{ $rec['latest_remarks'] ?: '—' }}</td>
                            <td class="px-3 py-2.5">
                                <span class="font-semibold">L{{ $rec['escalation_level'] }}</span>
                                <div class="text-[11px] text-slate-400">{{ $rec['last_notification'] }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-4 py-6 text-center text-slate-500">No task records match current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
