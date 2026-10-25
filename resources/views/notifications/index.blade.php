@extends('layouts.app')

@section('title', 'Alerts & Messages')
@section('heading', 'Alerts & Messages')
@section('subheading', 'Reminders, submission updates, and feedback from your coordinator.')

@section('content')
    <div class="flex justify-end">
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold cursor-pointer">
                Mark All as Read
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
        @forelse($notifications as $n)
            @php
                $channelLabels = ['IN_APP' => 'Portal', 'EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'];
            @endphp
            <div class="p-5 flex items-start justify-between gap-4 {{ !$n->read_at ? 'bg-indigo-50/40' : '' }}">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        @if(!$n->read_at)
                            <span class="px-2 py-0.5 rounded-full bg-indigo-600 text-white text-xs font-semibold">New</span>
                        @endif
                        <span class="text-base font-bold text-slate-900">{{ $n->subject }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $n->channel === 'WHATSAPP' ? 'bg-emerald-100 text-emerald-800' : ($n->channel === 'EMAIL' ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-600') }}">
                            {{ $channelLabels[$n->channel] ?? $n->channel }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-700">{{ $n->message_summary }}</p>
                    <div class="text-xs text-slate-400">
                        {{ ($n->sent_at ?? $n->created_at)?->timezone(\App\Models\SystemSetting::timezone())->format('d M Y, h:i A') }}
                    </div>
                </div>

                @if(!$n->read_at)
                    <form method="POST" action="{{ route('notifications.read', $n) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold cursor-pointer">
                            Mark Read
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="p-10 text-center text-sm text-slate-500">You have no alerts or messages yet.</div>
        @endforelse
    </div>
    <div>{{ $notifications->links() }}</div>
@endsection
