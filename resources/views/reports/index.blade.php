@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'School Submission Reports')
@section('subheading', 'View submission progress by class, teacher, or subject, and download for Excel.')

@section('content')
    <!-- Simple Report Tabs + Download Button -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $reportTabs = [
                    'class-wise' => 'By Class',
                    'teacher-wise' => 'By Teacher',
                    'subject-wise' => 'By Subject',
                    'overdue' => 'Late List',
                ];
            @endphp
            @foreach($reportTabs as $key => $label)
                @if($key === 'teacher-wise' || auth()->user()->hasPermission('reports.view_scope') || auth()->user()->hasPermission('reports.view_school'))
                    <a href="{{ route('reports.index', array_merge($filters, ['tab' => $key])) }}"
                       class="px-4 py-2 rounded-xl text-sm font-semibold border transition {{ $tab === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasPermission('reports.view_scope') || auth()->user()->hasPermission('reports.view_school'))
                <a href="{{ route('management.mis', ['academic_year_id' => $filters['academic_year_id'] ?? null]) }}"
                   class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                    Charts & Custom MIS Builder →
                </a>
            @endif
            <a href="{{ route('reports.export', $filters) }}"
               class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold">
                Download Excel (CSV)
            </a>
        </div>
    </div>

    <!-- Clean Simple Filter Bar -->
    <form method="GET" action="{{ route('reports.index') }}" class="bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap items-end gap-3 text-sm">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div>
            <label class="block text-slate-600 font-medium text-xs mb-1">Session</label>
            <select name="academic_year_id" class="rounded-xl border border-slate-300 px-3 py-2 bg-white">
                @foreach($academicYears as $yr)
                    <option value="{{ $yr->id }}" @selected(($filters['academic_year_id'] ?? '') == $yr->id)>{{ $yr->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-32">
            <label class="block text-slate-600 font-medium text-xs mb-1">Class</label>
            <select name="class_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                <option value="">All Classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected(($filters['class_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-36">
            <label class="block text-slate-600 font-medium text-xs mb-1">Subject</label>
            <select name="subject_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                <option value="">All Subjects</option>
                @foreach($subjects as $s)
                    <option value="{{ $s->id }}" @selected(($filters['subject_id'] ?? '') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-40">
            <label class="block text-slate-600 font-medium text-xs mb-1">Teacher</label>
            <select name="teacher_user_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                <option value="">All Teachers</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected(($filters['teacher_user_id'] ?? '') == $t->id)>{{ $t->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-36">
            <label class="block text-slate-600 font-medium text-xs mb-1">Status</label>
            <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white">
                <option value="">All</option>
                <option value="APPROVED" @selected(($filters['status'] ?? '') === 'APPROVED')>Approved</option>
                <option value="PENDING_REVIEW" @selected(($filters['status'] ?? '') === 'PENDING_REVIEW')>Waiting Check</option>
                <option value="RETURNED_FOR_CORRECTION" @selected(($filters['status'] ?? '') === 'RETURNED_FOR_CORRECTION')>Needs Correction</option>
                <option value="NOT_STARTED" @selected(($filters['status'] ?? '') === 'NOT_STARTED')>Pending</option>
                <option value="OVERDUE" @selected(($filters['status'] ?? '') === 'OVERDUE')>Late</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold cursor-pointer">Show</button>
            <a href="{{ route('reports.index', ['tab' => $tab]) }}" class="px-3.5 py-2 rounded-xl bg-slate-100 text-slate-700">Clear</a>
        </div>
    </form>

    <!-- 4 Simple Summary Numbers -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-4"><span class="text-sm text-slate-500">Total Assigned</span><div class="text-2xl font-bold mt-0.5">{{ $summary['expected'] }}</div></div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4"><span class="text-sm text-emerald-700">Approved</span><div class="text-2xl font-bold text-emerald-600 mt-0.5">{{ $summary['approved'] }}</div></div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4"><span class="text-sm text-rose-700">Late</span><div class="text-2xl font-bold text-rose-600 mt-0.5">{{ $summary['overdue'] }}</div></div>
        <div class="bg-indigo-600 text-white rounded-2xl p-4"><span class="text-sm text-indigo-100">Completed</span><div class="text-2xl font-bold mt-0.5">{{ number_format($summary['completion_rate'], 0) }}%</div></div>
    </div>

    <!-- Main Report Table -->
    @if($tab !== 'overdue')
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 font-bold text-base text-slate-900">
                {{ $reportTabs[$tab] ?? 'Summary' }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                        <tr>
                            @if($tab === 'class-wise')
                                <th class="px-5 py-3 text-left">Class & Section</th>
                                <th class="px-5 py-3 text-left">Subject</th>
                                <th class="px-5 py-3 text-left">Teacher</th>
                            @elseif($tab === 'teacher-wise')
                                <th class="px-5 py-3 text-left">Teacher</th>
                                <th class="px-5 py-3 text-left">Email</th>
                            @else
                                <th class="px-5 py-3 text-left">Subject</th>
                                <th class="px-5 py-3 text-left">Class</th>
                            @endif
                            <th class="px-5 py-3 text-right">Assigned</th>
                            <th class="px-5 py-3 text-right">Approved</th>
                            <th class="px-5 py-3 text-right">Waiting Check</th>
                            <th class="px-5 py-3 text-right">Late</th>
                            <th class="px-5 py-3 text-right">Completed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $r)
                            <tr>
                                @if($tab === 'class-wise')
                                    <td class="px-5 py-3 font-semibold">{{ $r['class_name'] }} — {{ $r['section_name'] }}</td>
                                    <td class="px-5 py-3">{{ $r['subject_name'] }}</td>
                                    <td class="px-5 py-3">{{ $r['teacher_name'] }}</td>
                                @elseif($tab === 'teacher-wise')
                                    <td class="px-5 py-3 font-semibold">{{ $r['teacher_name'] }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $r['teacher_email'] }}</td>
                                @else
                                    <td class="px-5 py-3 font-semibold">{{ $r['subject_name'] }}</td>
                                    <td class="px-5 py-3">{{ $r['class_name'] }}</td>
                                @endif
                                <td class="px-5 py-3 text-right font-medium">{{ $r['expected'] }}</td>
                                <td class="px-5 py-3 text-right text-emerald-700 font-semibold">{{ $r['approved'] }}</td>
                                <td class="px-5 py-3 text-right text-sky-700">{{ $r['pending_review'] }}</td>
                                <td class="px-5 py-3 text-right text-rose-700 font-semibold">{{ $r['overdue'] }}</td>
                                <td class="px-5 py-3 text-right font-bold">{{ number_format($r['completion_rate'], 0) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-8 text-center text-slate-500">No matching records.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Detailed Task-by-Task List (Collapsible when on Summary Tabs, Open when on Late List!) -->
    <details class="bg-white rounded-2xl border border-slate-200 overflow-hidden" {{ $tab === 'overdue' ? 'open' : '' }}>
        <summary class="px-6 py-4 font-bold text-base text-slate-900 cursor-pointer flex items-center justify-between hover:bg-slate-50">
            <span>Detailed Task-by-Task List ({{ count($taskRecords) }} items)</span>
            <span class="text-xs font-medium text-indigo-600">Click to show / hide</span>
        </summary>
        <div class="border-t border-slate-100 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Class</th>
                        <th class="px-4 py-3 text-left">Subject & Teacher</th>
                        <th class="px-4 py-3 text-left">Document & Period</th>
                        <th class="px-4 py-3 text-left">Due Date</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Feedback</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($taskRecords as $rec)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap font-semibold">{{ $rec['class_name'] }}-{{ $rec['section_name'] }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $rec['subject_name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $rec['teacher_name'] }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $rec['document_type'] }}</div>
                                <div class="text-xs text-slate-500">{{ $rec['period'] }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs">
                                <div>{{ $rec['due_date'] }}</div>
                                @if($rec['is_overdue'])
                                    <span class="text-rose-700 font-bold">Late by {{ $rec['overdue_days'] }}d</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs font-semibold">{{ $rec['workflow_status_label'] }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-xs truncate" title="{{ $rec['latest_remarks'] }}">{{ $rec['latest_remarks'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No tasks match your filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
@endsection
