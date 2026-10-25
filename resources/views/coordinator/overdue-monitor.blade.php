@extends('layouts.app')

@section('title', 'Late Submissions')
@section('heading', 'Late Submissions')
@section('subheading', 'See tasks past their due date and send a polite reminder in one click.')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('overdue.monitor') }}" class="flex flex-wrap items-center gap-2 text-sm">
            <select name="class_id" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2">
                <option value="">All Classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected(($filters['class_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <select name="subject_id" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2">
                <option value="">All Subjects</option>
                @foreach($subjects as $s)
                    <option value="{{ $s->id }}" @selected(($filters['subject_id'] ?? '') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-semibold cursor-pointer">Show</button>
        </form>

        @if(auth()->user()->hasPermission('notifications.manage') || auth()->user()->hasPermission('system.settings.manage'))
            <form method="POST" action="{{ route('overdue.evaluate') }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold cursor-pointer">
                    Refresh Late Status
                </button>
            </form>
        @endif
    </div>

    <div class="space-y-3">
        @forelse($overdueRows as $row)
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-800 text-xs font-bold">
                            {{ $row['overdue_days'] }} day(s) late
                        </span>
                        <span class="text-base font-bold text-slate-900">{{ $row['title'] }}</span>
                    </div>
                    <div class="text-sm text-slate-700">
                        <strong>{{ $row['teacher_name'] }}</strong> · {{ $row['class_name'] }}-{{ $row['section_name'] }} · {{ $row['subject_name'] }}
                    </div>
                    <div class="text-xs text-slate-500">
                        {{ $row['document_type'] }} ({{ $row['period'] }}) · Due Date: {{ $row['due_date'] }}
                        @if($row['last_notification'])
                            · Last reminder: {{ $row['last_notification'] }}
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('overdue.remind', $row['task_id']) }}" class="shrink-0">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-2xs cursor-pointer">
                        Send Reminder
                    </button>
                </form>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-sm text-slate-500">
                Great news — there are no late submissions right now!
            </div>
        @endforelse
    </div>
@endsection
