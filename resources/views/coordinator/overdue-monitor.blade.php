@extends('layouts.app')

@section('title', 'Overdue & Escalation Monitor')
@section('heading', 'Overdue & Escalation Monitor')
@section('subheading', 'Active submission delays, escalation tiers (L1–L3), reviewer SLA breaches, and manual reminder dispatch')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('overdue.monitor') }}" class="flex flex-wrap items-center gap-2 text-xs">
            <select name="class_id" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5">
                <option value="">All Classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected(($filters['class_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <select name="subject_id" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5">
                <option value="">All Subjects</option>
                @foreach($subjects as $s)
                    <option value="{{ $s->id }}" @selected(($filters['subject_id'] ?? '') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Apply Filter</button>
        </form>

        @if(auth()->user()->hasPermission('notifications.manage') || auth()->user()->hasPermission('system.settings.manage'))
            <form method="POST" action="{{ route('overdue.evaluate') }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm cursor-pointer">
                    Run Overdue & Escalation Check Now
                </button>
            </form>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Overdue Days</th>
                        <th class="px-4 py-3 text-left">Task & Document Type</th>
                        <th class="px-4 py-3 text-left">Teacher</th>
                        <th class="px-4 py-3 text-left">Class / Subject</th>
                        <th class="px-4 py-3 text-left">Deadline</th>
                        <th class="px-4 py-3 text-left">Escalation & Last Alert</th>
                        <th class="px-4 py-3 text-right">Send Reminder</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($overdueRows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-md bg-rose-100 text-rose-800 text-xs font-bold">
                                    {{ $row['overdue_days'] }} day(s)
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $row['title'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['document_type'] }} · {{ $row['period'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs font-semibold text-slate-800">{{ $row['teacher_name'] }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700">
                                {{ $row['class_name'] }}-{{ $row['section_name'] }} · {{ $row['subject_name'] }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700 whitespace-nowrap">{{ $row['due_date'] }}</td>
                            <td class="px-4 py-3 text-xs">
                                <span class="px-2 py-0.5 rounded font-bold {{ $row['escalation_level'] > 0 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-700' }}">
                                    Level {{ $row['escalation_level'] }}
                                </span>
                                <div class="text-[11px] text-slate-500 mt-1">{{ $row['last_notification'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('overdue.remind', $row['task_id']) }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold cursor-pointer">
                                        Send Reminder
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-xs text-slate-500">No overdue tasks in your authorised scope.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
