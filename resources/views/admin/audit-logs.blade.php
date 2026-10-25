@extends('layouts.app')

@section('title', 'Activity History')
@section('heading', 'School Activity History')
@section('subheading', 'Recent actions across uploads, approvals, reminders, and staff changes.')

@section('content')
    <form method="GET" action="{{ route('admin.audit-logs') }}" class="bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap items-center gap-3 text-sm">
        <input type="text" name="action_key" value="{{ request('action_key') }}" placeholder="Search activity (e.g. approved, submitted)..."
               class="rounded-xl border border-slate-300 px-3.5 py-2 flex-1 min-w-56">
        <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-semibold cursor-pointer">Search</button>
        <a href="{{ route('admin.audit-logs') }}" class="px-3.5 py-2 rounded-xl bg-slate-100 text-slate-700">Reset</a>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
        @foreach($events as $ev)
            @php
                $prettyAction = ucwords(str_replace(['.', '_'], ' ', $ev->action_key));
            @endphp
            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="space-y-0.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold text-slate-900">{{ $prettyAction }}</span>
                        <span class="text-xs text-slate-500">by <strong>{{ $ev->actor?->full_name ?? 'Automatic School Timer' }}</strong> ({{ $ev->actor?->primaryRole?->name ?? 'System' }})</span>
                    </div>
                    <div class="text-xs text-slate-400">
                        {{ $ev->created_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                    </div>
                </div>

                @if($ev->after_json)
                    <details class="text-xs shrink-0">
                        <summary class="px-3 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium cursor-pointer inline-block">
                            View Details
                        </summary>
                        <pre class="mt-2 text-xs text-slate-600 bg-slate-50 border border-slate-200 p-3 rounded-xl overflow-x-auto max-w-md">{{ json_encode($ev->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                @endif
            </div>
        @endforeach
    </div>
    <div>{{ $events->links() }}</div>
@endsection
