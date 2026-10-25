@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications & Multi-Channel Delivery Log')
@section('subheading', 'Idempotent In-App, Email, and WhatsApp deadline reminders, submission alerts, review remarks, and escalation notices')

@section('content')
    <div class="flex justify-end">
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold cursor-pointer">
                Mark All as Read
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @forelse($notifications as $n)
            <div class="p-4 flex items-start justify-between gap-4 {{ !$n->read_at ? 'bg-indigo-50/40' : '' }}">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[10px] font-semibold">{{ $n->event_key }}</span>
                        <span class="px-2 py-0.5 rounded font-bold text-[10px] {{ $n->channel === 'WHATSAPP' ? 'bg-emerald-100 text-emerald-800' : ($n->channel === 'EMAIL' ? 'bg-sky-100 text-sky-800' : 'bg-indigo-100 text-indigo-800') }}">
                            {{ $n->channel }}
                        </span>
                        <span class="text-sm font-semibold text-slate-900">{{ $n->subject }}</span>
                        @if(!$n->read_at)
                            <span class="px-1.5 py-0.5 rounded bg-indigo-600 text-white text-[10px] font-bold">NEW</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-700 mt-1">{{ $n->message_summary }}</p>
                    <div class="text-[11px] text-slate-400 mt-1">
                        {{ $n->sent_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') ?? $n->created_at?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                        · Status: {{ $n->delivery_status }}
                        @if($n->provider_message_id)
                            · Ref: <code class="text-[10px]">{{ $n->provider_message_id }}</code>
                        @endif
                    </div>
                </div>

                @if(!$n->read_at)
                    <form method="POST" action="{{ route('notifications.read', $n) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="px-2.5 py-1 rounded bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold cursor-pointer">
                            Mark Read
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="p-8 text-center text-xs text-slate-500">No notifications recorded for your account.</div>
        @endforelse
    </div>
    <div>{{ $notifications->links() }}</div>
@endsection
