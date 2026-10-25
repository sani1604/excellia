@extends('layouts.app')

@section('title', 'System Settings, Notifications & Escalation Policy')
@section('heading', 'System Settings, Multi-Channel Notifications & Escalation Rules')
@section('subheading', 'Configure timezone, reviewer SLA, submission modes, In-App / Email / WhatsApp channels, notification events, and escalation thresholds')

@section('content')
    @php
        $allowedModes = (array) \App\Models\SystemSetting::getValue('submission_modes', ['PORTAL_UPLOAD', 'DRIVE_LINK']);
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Operational Parameters & Channel Switches -->
        <form method="POST" action="{{ route('admin.settings.update') }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-4 text-xs">
            @csrf
            <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Operational Parameters & Outbound Channels</h2>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">School Timezone</label>
                    <input type="text" name="school_timezone" value="{{ \App\Models\SystemSetting::timezone() }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Reviewer Turnaround SLA (Hours)</label>
                    <input type="number" name="review_sla_hours" value="{{ \App\Models\SystemSetting::getValue('review_sla_hours', 48) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">Configured School Holidays (Comma-separated YYYY-MM-DD)</label>
                <input type="text" name="holidays_csv"
                       value="{{ implode(', ', (array) \App\Models\SystemSetting::getValue('holidays_dates', [])) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Permitted Teacher Submission Methods</label>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="submission_modes[]" value="PORTAL_UPLOAD" @checked(in_array('PORTAL_UPLOAD', $allowedModes, true))>
                        <span>Portal File Upload (Private Vault / Drive API)</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="submission_modes[]" value="DRIVE_LINK" @checked(in_array('DRIVE_LINK', $allowedModes, true))>
                        <span>Institutional Google Drive File Link</span>
                    </label>
                </div>
            </div>

            <div class="space-y-2 pt-1 border-t border-slate-100">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="allow_co_class_teachers" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('allow_co_class_teachers', false))>
                    <span>Allow Co-Class-Teacher assignments per section</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="exclude_holidays_from_overdue" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('exclude_holidays_from_overdue', false))>
                    <span>Exclude configured holidays when counting overdue days</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="email_notifications_enabled" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('email_notifications_enabled', false))>
                    <span>Enable Email notifications alongside In-App alerts</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="whatsapp_notifications_enabled" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('whatsapp_notifications_enabled', false))>
                    <span>Enable WhatsApp notifications alongside In-App alerts (Provider: <code>{{ config('excellia.whatsapp.provider') }}</code>)</span>
                </label>
            </div>

            @if(auth()->user()->hasPermission('system.settings.manage'))
                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">
                    Save System Settings
                </button>
            @endif
        </form>

        <!-- Editable Event Notification Rules -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Workflow Event Notification Rules</h2>
                <p class="text-xs text-slate-500">Toggle active events and choose delivery channels (In-App, Email, WhatsApp).</p>
            </div>
            <div class="divide-y divide-slate-100 max-h-[28rem] overflow-y-auto text-xs">
                @foreach($notificationRules as $nRule)
                    @php $ch = (array) $nRule->channels; @endphp
                    <form method="POST" action="{{ route('admin.notification-rules.update', $nRule) }}" class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2">
                        @csrf
                        @method('PATCH')
                        <div class="min-w-44">
                            <div class="font-semibold text-slate-900">{{ $nRule->name }}</div>
                            <code class="text-[10px] text-slate-500">{{ $nRule->event_key }}</code>
                        </div>
                        <div class="flex items-center gap-2.5">
                            @foreach(['IN_APP' => 'In-App', 'EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'] as $ck => $clabel)
                                <label class="inline-flex items-center gap-1">
                                    <input type="checkbox" name="channels[]" value="{{ $ck }}" @checked(in_array($ck, $ch, true))>
                                    <span>{{ $clabel }}</span>
                                </label>
                            @endforeach
                            <label class="inline-flex items-center gap-1 font-semibold text-emerald-700 ml-1">
                                <input type="checkbox" name="is_active" value="1" @checked($nRule->is_active)>
                                <span>Active</span>
                            </label>
                            <button type="submit" class="px-2.5 py-1 rounded bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Editable Escalation & Reminder Matrix -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden text-xs">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Configurable Escalation & Reminder Threshold Matrix</h2>
                <p class="text-xs text-slate-500">Evaluated hourly in school timezone with idempotency protection and scoped recipient resolution.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Rule Name</th>
                        <th class="px-4 py-2.5 text-left">Trigger</th>
                        <th class="px-4 py-2.5 text-left">Threshold</th>
                        <th class="px-4 py-2.5 text-left">Level</th>
                        <th class="px-4 py-2.5 text-left">Recipient Role</th>
                        <th class="px-4 py-2.5 text-left">Channels & Flags</th>
                        <th class="px-4 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($escalationRules as $rule)
                        @php $rch = (array) $rule->channels; @endphp
                        <tr>
                            <td colspan="7" class="p-0">
                                <form method="POST" action="{{ route('admin.escalation-rules.update', $rule) }}" class="grid grid-cols-1 md:grid-cols-12 items-center gap-2 px-4 py-2.5">
                                    @csrf
                                    @method('PATCH')
                                    <div class="md:col-span-3">
                                        <input type="text" name="name" value="{{ $rule->name }}" required class="w-full rounded border border-slate-300 px-2 py-1 font-medium">
                                    </div>
                                    <div class="md:col-span-2 font-mono text-[11px] text-slate-600">{{ $rule->trigger_type }}</div>
                                    <div class="md:col-span-1">
                                        <input type="number" name="threshold_value" value="{{ $rule->threshold_value }}" required class="w-16 rounded border border-slate-300 px-2 py-1">
                                    </div>
                                    <div class="md:col-span-1">
                                        <input type="number" name="escalation_level" value="{{ $rule->escalation_level }}" min="0" max="5" required class="w-14 rounded border border-slate-300 px-2 py-1 font-bold">
                                    </div>
                                    <div class="md:col-span-2">
                                        <select name="target_role_key" class="w-full rounded border border-slate-300 px-2 py-1 bg-white">
                                            @foreach($roles as $r)
                                                <option value="{{ $r->key }}" @selected($rule->target_role_key === $r->key)>{{ $r->key }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-2 flex flex-wrap items-center gap-2">
                                        @foreach(['IN_APP' => 'App', 'EMAIL' => 'Mail', 'WHATSAPP' => 'WA'] as $ck => $cl)
                                            <label class="inline-flex items-center gap-0.5"><input type="checkbox" name="channels[]" value="{{ $ck }}" @checked(in_array($ck, $rch, true))><span>{{ $cl }}</span></label>
                                        @endforeach
                                        <label class="inline-flex items-center gap-0.5"><input type="checkbox" name="skip_weekends" value="1" @checked($rule->skip_weekends)><span>NoWknd</span></label>
                                        <label class="inline-flex items-center gap-0.5 text-emerald-700 font-semibold"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)><span>On</span></label>
                                    </div>
                                    <div class="md:col-span-1 text-right">
                                        <button type="submit" class="px-2.5 py-1 rounded bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Add New Escalation Rule -->
        <form method="POST" action="{{ route('admin.escalation-rules.store') }}" class="p-4 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center gap-2">
            @csrf
            <span class="font-bold text-slate-700 mr-1">Add Rule:</span>
            <input type="text" name="name" required placeholder="Rule Name (e.g. Overdue +7 Days)" class="rounded border border-slate-300 px-2.5 py-1.5 bg-white w-52">
            <select name="trigger_type" class="rounded border border-slate-300 px-2 py-1.5 bg-white">
                <option value="OVERDUE_DAYS">OVERDUE_DAYS</option>
                <option value="DUE_SOON">DUE_SOON</option>
                <option value="ON_DUE_DATE">ON_DUE_DATE</option>
                <option value="REVIEW_SLA_HOURS">REVIEW_SLA_HOURS</option>
            </select>
            <input type="number" name="threshold_value" required value="7" class="rounded border border-slate-300 px-2 py-1.5 bg-white w-20" title="Threshold">
            <input type="number" name="escalation_level" required value="3" min="0" max="5" class="rounded border border-slate-300 px-2 py-1.5 bg-white w-16" title="Escalation Level">
            <select name="target_role_key" class="rounded border border-slate-300 px-2 py-1.5 bg-white">
                @foreach($roles as $r)
                    <option value="{{ $r->key }}">{{ $r->key }}</option>
                @endforeach
            </select>
            <input type="hidden" name="channels[]" value="IN_APP">
            <label class="inline-flex items-center gap-1"><input type="checkbox" name="channels[]" value="EMAIL" checked>Email</label>
            <label class="inline-flex items-center gap-1"><input type="checkbox" name="channels[]" value="WHATSAPP" checked>WhatsApp</label>
            <input type="hidden" name="is_active" value="1">
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Add Escalation Rule</button>
        </form>
    </div>
@endsection
