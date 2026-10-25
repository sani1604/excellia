@extends('layouts.app')

@section('title', 'Reminders & Settings')
@section('heading', 'Reminders & School Settings')
@section('subheading', 'Choose how teachers upload files and how WhatsApp, Email, and Portal reminders are sent.')

@section('content')
    @php
        $allowedModes = (array) \App\Models\SystemSetting::getValue('submission_modes', ['PORTAL_UPLOAD', 'DRIVE_LINK']);
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Simple General Settings -->
        <form method="POST" action="{{ route('admin.settings.update') }}" class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4 text-sm">
            @csrf
            <h2 class="text-base font-bold text-slate-900">General School Settings</h2>

            <input type="hidden" name="school_timezone" value="{{ \App\Models\SystemSetting::timezone() }}">

            <div>
                <label class="block font-medium text-slate-700 mb-1">Coordinator Check Time (Hours)</label>
                <input type="number" name="review_sla_hours" value="{{ \App\Models\SystemSetting::getValue('review_sla_hours', 48) }}" required
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                <p class="text-xs text-slate-500 mt-1">Coordinators are reminded gently if a file waits longer than this.</p>
            </div>

            <div>
                <label class="block font-medium text-slate-700 mb-1">School Holidays (YYYY-MM-DD, separated by commas)</label>
                <input type="text" name="holidays_csv"
                       value="{{ implode(', ', (array) \App\Models\SystemSetting::getValue('holidays_dates', [])) }}"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
            </div>

            <div>
                <label class="block font-medium text-slate-700 mb-1.5">How Can Teachers Submit Files?</label>
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="submission_modes[]" value="PORTAL_UPLOAD" @checked(in_array('PORTAL_UPLOAD', $allowedModes, true))>
                        <span>Direct File Upload from Computer / Phone (Recommended)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="submission_modes[]" value="DRIVE_LINK" @checked(in_array('DRIVE_LINK', $allowedModes, true))>
                        <span>Paste School Google Drive Link</span>
                    </label>
                </div>
            </div>

            <div class="space-y-2.5 pt-3 border-t border-slate-100">
                <div class="font-medium text-slate-800">Message Channels & Options</div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="whatsapp_notifications_enabled" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('whatsapp_notifications_enabled', false))>
                    <span>Send WhatsApp alerts to staff mobile numbers</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="email_notifications_enabled" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('email_notifications_enabled', false))>
                    <span>Send Email alerts to school email accounts</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="exclude_holidays_from_overdue" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('exclude_holidays_from_overdue', false))>
                    <span>Do not count school holidays when marking late days</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="allow_co_class_teachers" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('allow_co_class_teachers', false))>
                    <span>Allow two Co-Class Teachers for the same section</span>
                </label>
            </div>

            @if(auth()->user()->hasPermission('system.settings.manage'))
                <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save School Settings
                </button>
            @endif
        </form>

        <!-- Which Events Send Messages -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">When Should Staff Receive Alerts?</h2>
                <p class="text-sm text-slate-500 mt-0.5">Choose Portal, Email, or WhatsApp for each type of update.</p>
            </div>
            <div class="divide-y divide-slate-100 text-sm">
                @foreach($notificationRules as $nRule)
                    @php $ch = (array) $nRule->channels; @endphp
                    <form method="POST" action="{{ route('admin.notification-rules.update', $nRule) }}" class="p-4 flex flex-wrap items-center justify-between gap-3">
                        @csrf
                        @method('PATCH')
                        <div class="font-medium text-slate-900">{{ $nRule->name }}</div>
                        <div class="flex flex-wrap items-center gap-3 text-xs">
                            @foreach(['IN_APP' => 'Portal', 'EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'] as $ck => $clabel)
                                <label class="inline-flex items-center gap-1">
                                    <input type="checkbox" name="channels[]" value="{{ $ck }}" @checked(in_array($ck, $ch, true))>
                                    <span>{{ $clabel }}</span>
                                </label>
                            @endforeach
                            <label class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                                <input type="checkbox" name="is_active" value="1" @checked($nRule->is_active)>
                                <span>On</span>
                            </label>
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Collapsible Automatic Late Reminder Schedule -->
    <details class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <summary class="px-6 py-4 font-bold text-base text-slate-900 cursor-pointer flex items-center justify-between hover:bg-slate-50">
            <span>Automatic Due-Date & Late Reminder Schedule ({{ $escalationRules->count() }} rules)</span>
            <span class="text-xs font-medium text-indigo-600">Click to customize</span>
        </summary>

        <div class="border-t border-slate-100 divide-y divide-slate-100 text-xs">
            @php
                $triggerLabels = [
                    'DUE_SOON' => 'Days Before Due Date',
                    'ON_DUE_DATE' => 'On Due Date',
                    'OVERDUE_DAYS' => 'Days Late',
                    'REVIEW_SLA_HOURS' => 'Hours Waiting for Check',
                ];
            @endphp
            @foreach($escalationRules as $rule)
                @php $rch = (array) $rule->channels; @endphp
                <form method="POST" action="{{ route('admin.escalation-rules.update', $rule) }}" class="p-4 flex flex-wrap items-center justify-between gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" name="name" value="{{ $rule->name }}" required class="rounded-lg border border-slate-300 px-2.5 py-1.5 font-semibold w-52">
                        <span class="text-slate-500">{{ $triggerLabels[$rule->trigger_type] ?? $rule->trigger_type }}:</span>
                        <input type="number" name="threshold_value" value="{{ $rule->threshold_value }}" required class="w-16 rounded-lg border border-slate-300 px-2 py-1.5">
                        <input type="hidden" name="escalation_level" value="{{ $rule->escalation_level }}">
                        <span class="text-slate-500">Inform:</span>
                        <select name="target_role_key" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white">
                            @foreach($roles as $r)
                                <option value="{{ $r->key }}" @selected($rule->target_role_key === $r->key)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        @foreach(['IN_APP' => 'Portal', 'EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'] as $ck => $cl)
                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="channels[]" value="{{ $ck }}" @checked(in_array($ck, $rch, true))><span>{{ $cl }}</span></label>
                        @endforeach
                        <label class="inline-flex items-center gap-1"><input type="checkbox" name="skip_weekends" value="1" @checked($rule->skip_weekends)><span>Skip Sunday</span></label>
                        <label class="inline-flex items-center gap-1 text-emerald-700 font-semibold"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)><span>Active</span></label>
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                    </div>
                </form>
            @endforeach

            <!-- Add New Schedule Rule -->
            <form method="POST" action="{{ route('admin.escalation-rules.store') }}" class="p-4 bg-slate-50 flex flex-wrap items-center gap-2">
                @csrf
                <span class="font-bold text-slate-700">Add Reminder Rule:</span>
                <input type="text" name="name" required placeholder="e.g. 7 Days Late Alert" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white w-48">
                <select name="trigger_type" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white">
                    @foreach($triggerLabels as $tk => $tl)
                        <option value="{{ $tk }}">{{ $tl }}</option>
                    @endforeach
                </select>
                <input type="number" name="threshold_value" required value="7" class="rounded-lg border border-slate-300 px-2 py-1.5 bg-white w-16">
                <input type="hidden" name="escalation_level" value="2">
                <select name="target_role_key" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white">
                    @foreach($roles as $r)
                        <option value="{{ $r->key }}">{{ $r->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="channels[]" value="IN_APP">
                <label class="inline-flex items-center gap-1"><input type="checkbox" name="channels[]" value="EMAIL" checked>Email</label>
                <label class="inline-flex items-center gap-1"><input type="checkbox" name="channels[]" value="WHATSAPP" checked>WhatsApp</label>
                <input type="hidden" name="is_active" value="1">
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Add Rule</button>
            </form>
        </div>
    </details>
@endsection
