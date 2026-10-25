@extends('layouts.app')

@section('title', 'Audit Trail')
@section('heading', 'Immutable Audit Trail')
@section('subheading', 'Append-only log of user, master, task, submission, review, Drive mapping, and restricted-file access events (sensitive tokens redacted)')

@section('content')
    <form method="GET" action="{{ route('admin.audit-logs') }}" class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-center gap-3 text-xs">
        <input type="text" name="action_key" value="{{ request('action_key') }}" placeholder="Filter action (e.g. submission.returned)"
               class="rounded-lg border border-slate-300 px-3 py-1.5 w-64">
        <input type="text" name="entity_type" value="{{ request('entity_type') }}" placeholder="Entity type (e.g. document_task)"
               class="rounded-lg border border-slate-300 px-3 py-1.5 w-52">
        <button type="submit" class="px-4 py-1.5 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Filter</button>
        <a href="{{ route('admin.audit-logs') }}" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700">Reset</a>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Timestamp</th>
                        <th class="px-4 py-3 text-left">Actor</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Entity</th>
                        <th class="px-4 py-3 text-left">Redacted Payload Summary</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($events as $ev)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                {{ $ev->created_at?->timezone(\App\Models\SystemSetting::timezone())->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $ev->actor?->full_name ?? 'System Scheduler' }}</div>
                                <div class="text-slate-500">{{ $ev->actor?->primaryRole?->key ?? 'SYSTEM' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <code class="px-2 py-0.5 rounded bg-slate-100 text-indigo-700 font-semibold">{{ $ev->action_key }}</code>
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-700">{{ $ev->entity_type }} #{{ $ev->entity_id ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-md">
                                @if($ev->after_json)
                                    <pre class="text-[11px] text-slate-600 bg-slate-50 p-2 rounded overflow-x-auto">{{ json_encode($ev->after_json, JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $events->links() }}</div>
    </div>
@endsection
