@extends('layouts.app')

@section('title', 'Integrations & School Settings')
@section('heading', 'Integrations, Reminders & School Settings')
@section('subheading', 'Configure Email, Whatify WhatsApp API, Google Drive & SSO, OpenRouter AI Assistant, and reminder schedules.')

@section('content')
    @php
        $activeTab = $tab ?? request('tab', 'general');
        $allowedModes = (array) \App\Models\SystemSetting::getValue('submission_modes', ['PORTAL_UPLOAD', 'DRIVE_LINK']);

        $emailOn = (bool) \App\Models\SystemSetting::getValue('email_notifications_enabled', false);
        $waOn = (bool) \App\Models\SystemSetting::getValue('whatsapp_notifications_enabled', false);
        $waProvider = (string) \App\Models\SystemSetting::getValue('whatsapp_provider', config('excellia.whatsapp.provider', 'whatify'));
        $driveLive = (bool) \App\Models\SystemSetting::getValue('google_drive_live_enabled', false);
        $ssoOn = (bool) \App\Models\SystemSetting::getValue('google_sso_enabled', true);
        $aiOn = (bool) \App\Models\SystemSetting::getValue('ai_openrouter_enabled', false);

        $whatifyMasked = \App\Models\SystemSetting::maskedSecret('whatify_api_key', config('excellia.whatsapp.whatify_api_key'));
        $openrouterMasked = \App\Models\SystemSetting::maskedSecret('openrouter_api_key', config('excellia.ai.api_key'));
        $smtpMasked = \App\Models\SystemSetting::maskedSecret('mail_smtp_password');
        $driveSecretMasked = \App\Models\SystemSetting::maskedSecret('google_drive_client_secret', config('excellia.drive.oauth_client_secret'));
        $ssoSecretMasked = \App\Models\SystemSetting::maskedSecret('google_sso_client_secret', config('excellia.sso.google_client_secret'));

        $settingTabs = [
            'general' => 'General',
            'email' => 'Email (' . ($emailOn ? 'ON' : 'OFF') . ')',
            'whatsapp' => 'WhatsApp · Whatify (' . ($waOn ? 'ON' : 'OFF') . ')',
            'google' => 'Google Drive & SSO',
            'ai' => 'AI · OpenRouter (' . ($aiOn ? 'ON' : 'OFF') . ')',
            'rules' => 'Alert & Late Rules',
        ];
    @endphp

    <!-- Swipeable Mobile-Friendly Settings Tab Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
        @foreach($settingTabs as $key => $label)
            <a href="{{ route('admin.settings', ['tab' => $key]) }}"
               class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold border whitespace-nowrap transition {{ $activeTab === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($activeTab === 'general')
        <!-- TAB 1: General School Settings -->
        <form method="POST" action="{{ route('admin.settings.update') }}" class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 space-y-5 text-sm max-w-3xl">
            @csrf
            <input type="hidden" name="settings_section" value="general">
            <h2 class="text-base font-bold text-slate-900">General School Settings</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">School Timezone</label>
                    <input type="text" name="school_timezone" value="{{ \App\Models\SystemSetting::timezone() }}" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Coordinator Check Time (Hours)</label>
                    <input type="number" name="review_sla_hours" value="{{ \App\Models\SystemSetting::getValue('review_sla_hours', 48) }}" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                </div>
            </div>

            <div>
                <label class="block font-medium text-slate-700 mb-1">School Holidays (YYYY-MM-DD, separated by commas)</label>
                <input type="text" name="holidays_csv"
                       value="{{ implode(', ', (array) \App\Models\SystemSetting::getValue('holidays_dates', [])) }}"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
            </div>

            <div>
                <label class="block font-medium text-slate-700 mb-2">How Can Teachers Upload Files?</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="submission_modes[]" value="PORTAL_UPLOAD" @checked(in_array('PORTAL_UPLOAD', $allowedModes, true))>
                        <span>Direct File Upload from Computer / Mobile (Recommended)</span>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="submission_modes[]" value="DRIVE_LINK" @checked(in_array('DRIVE_LINK', $allowedModes, true))>
                        <span>Paste School Google Drive File Link</span>
                    </label>
                </div>
            </div>

            <div class="space-y-2.5 pt-3 border-t border-slate-100">
                <label class="flex items-center gap-2.5">
                    <input type="checkbox" name="exclude_holidays_from_overdue" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('exclude_holidays_from_overdue', false))>
                    <span>Skip school holidays when counting late days</span>
                </label>
                <label class="flex items-center gap-2.5">
                    <input type="checkbox" name="allow_co_class_teachers" value="1"
                           @checked((bool) \App\Models\SystemSetting::getValue('allow_co_class_teachers', false))>
                    <span>Allow two Co-Class Teachers for the same section</span>
                </label>
            </div>

            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                Save General Settings
            </button>
        </form>

    @elseif($activeTab === 'email')
        <!-- TAB 2: Email Enable / Remove + SMTP Settings -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 space-y-5 text-sm">
                @csrf
                <input type="hidden" name="settings_section" value="email">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Email Reminders & Alerts</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Uncheck below to completely turn off / remove email notifications across the portal.</p>
                    </div>
                    <label class="inline-flex items-center gap-2.5 px-4 py-2 rounded-xl {{ $emailOn ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-slate-100 text-slate-700' }} font-semibold cursor-pointer">
                        <input type="checkbox" name="email_notifications_enabled" value="1" @checked($emailOn)>
                        <span>Enable Email Alerts</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Email Mode</label>
                        <select name="mail_mailer_mode" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            <option value="log" @selected(\App\Models\SystemSetting::getValue('mail_mailer_mode', 'log') === 'log')>Local Safe Log</option>
                            <option value="smtp" @selected(\App\Models\SystemSetting::getValue('mail_mailer_mode', 'log') === 'smtp')>Live SMTP Server</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">From Email</label>
                        <input type="email" name="mail_from_address" value="{{ \App\Models\SystemSetting::getValue('mail_from_address', 'noreply@excelliaschool.edu.in') }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">From Name</label>
                        <input type="text" name="mail_from_name" value="{{ \App\Models\SystemSetting::getValue('mail_from_name', 'Excellia School Portal') }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">SMTP Host</label>
                        <input type="text" name="mail_smtp_host" value="{{ \App\Models\SystemSetting::getValue('mail_smtp_host', '') }}" placeholder="smtp.gmail.com"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">SMTP Port</label>
                        <input type="number" name="mail_smtp_port" value="{{ \App\Models\SystemSetting::getValue('mail_smtp_port', 587) }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Encryption</label>
                        <select name="mail_smtp_encryption" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $ev => $el)
                                <option value="{{ $ev }}" @selected(\App\Models\SystemSetting::getValue('mail_smtp_encryption', 'tls') === $ev)>{{ $el }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">SMTP Username</label>
                        <input type="text" name="mail_smtp_username" value="{{ \App\Models\SystemSetting::getValue('mail_smtp_username', '') }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">
                            SMTP Password / App Key
                            @if($smtpMasked) <span class="text-xs text-emerald-700">({{ $smtpMasked }})</span> @endif
                        </label>
                        <input type="password" name="mail_smtp_password" placeholder="Leave blank to keep saved password"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save Email Settings
                </button>
            </form>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit space-y-4 text-sm">
                <h3 class="text-base font-bold text-slate-900">Send Test Email</h3>
                <p class="text-xs text-slate-500">Verify your email transport configuration.</p>
                <form method="POST" action="{{ route('admin.settings.test') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="target" value="email">
                    <input type="email" name="test_email" value="{{ auth()->user()->email }}" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-semibold cursor-pointer">
                        Send Test Email
                    </button>
                </form>
            </div>
        </div>

    @elseif($activeTab === 'whatsapp')
        <!-- TAB 3: WhatsApp — Official Whatify API Integration (https://whatify.in/api-docs) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 space-y-5 text-sm">
                @csrf
                <input type="hidden" name="settings_section" value="whatsapp">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">WhatsApp — Whatify API Integration</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Official Whatify External API (<code>https://whatify.in/api/v1/external</code> · Header <code>X-API-Key</code>).</p>
                    </div>
                    <label class="inline-flex items-center gap-2.5 px-4 py-2 rounded-xl {{ $waOn ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-slate-100 text-slate-700' }} font-semibold cursor-pointer">
                        <input type="checkbox" name="whatsapp_notifications_enabled" value="1" @checked($waOn)>
                        <span>Enable WhatsApp</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">WhatsApp Gateway Provider *</label>
                        <select name="whatsapp_provider" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            <option value="whatify" @selected($waProvider === 'whatify')>Whatify API (whatify.in — Recommended)</option>
                            <option value="meta_cloud" @selected($waProvider === 'meta_cloud')>Meta WhatsApp Cloud API</option>
                            <option value="webhook" @selected($waProvider === 'webhook')>Custom Webhook</option>
                            <option value="sandbox" @selected($waProvider === 'sandbox')>Local Sandbox Log (Testing)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Default Country Code</label>
                        <input type="text" name="whatsapp_default_country_code"
                               value="{{ \App\Models\SystemSetting::getValue('whatsapp_default_country_code', '91') }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <div class="rounded-2xl bg-emerald-50/50 border border-emerald-200 p-4 space-y-4">
                    <div class="font-bold text-emerald-950 text-sm">Whatify External API Credentials</div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Whatify External Base URL</label>
                            <input type="url" name="whatify_base_url"
                                   value="{{ \App\Models\SystemSetting::getValue('whatify_base_url', 'https://whatify.in/api/v1/external') }}"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">
                                Whatify API Key (<code>X-API-Key</code>)
                                @if($whatifyMasked) <span class="text-xs text-emerald-700 font-semibold">Saved: {{ $whatifyMasked }}</span> @endif
                            </label>
                            <input type="password" name="whatify_api_key" placeholder="wfy_your_api_key_here (leave blank to keep)"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Message Type</label>
                            <select name="whatify_mode" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5">
                                <option value="text" @selected(\App\Models\SystemSetting::getValue('whatify_mode', 'text') === 'text')>Text (/send-message)</option>
                                <option value="template" @selected(\App\Models\SystemSetting::getValue('whatify_mode', 'text') === 'template')>Approved Template (/send-template)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">WhatsApp Account ID (Optional)</label>
                            <input type="number" name="whatify_account_id"
                                   value="{{ \App\Models\SystemSetting::getValue('whatify_account_id', '') }}"
                                   placeholder="Auto (1st connected)"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Template Name (if Template)</label>
                            <input type="text" name="whatify_template_name"
                                   value="{{ \App\Models\SystemSetting::getValue('whatify_template_name', '') }}"
                                   placeholder="e.g. school_reminder"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5">
                        </div>
                    </div>

                    @if($whatifyMasked)
                        <label class="inline-flex items-center gap-2 text-xs text-rose-700">
                            <input type="checkbox" name="clear_whatify_api_key" value="1">
                            <span>Remove saved Whatify API key</span>
                        </label>
                    @endif
                </div>

                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save WhatsApp & Whatify Settings
                </button>
            </form>

            <div class="space-y-4">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3 text-sm">
                    <h3 class="text-base font-bold text-slate-900">1. Verify Whatify Key</h3>
                    <p class="text-xs text-slate-500">Checks <code>GET /ping</code> and wallet balance on Whatify.</p>
                    <form method="POST" action="{{ route('admin.settings.test') }}">
                        @csrf
                        <input type="hidden" name="target" value="whatify_ping">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-semibold cursor-pointer">
                            Verify Whatify Connection
                        </button>
                    </form>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3 text-sm">
                    <h3 class="text-base font-bold text-slate-900">2. Send Test WhatsApp</h3>
                    <form method="POST" action="{{ route('admin.settings.test') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="target" value="whatsapp_send">
                        <input type="text" name="test_phone" value="{{ auth()->user()->phone ?? '919876543210' }}" placeholder="919876543210" required
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-semibold cursor-pointer">
                            Send Test Message
                        </button>
                    </form>
                </div>
            </div>
        </div>

    @elseif($activeTab === 'google')
        <!-- TAB 4: Google Drive API & Google Workspace SSO Integration -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 space-y-5 text-sm">
                @csrf
                <input type="hidden" name="settings_section" value="google">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Google Drive Repository Integration</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Switch between Private Local School Vault and Live Google Drive v3 API.</p>
                    </div>
                    <label class="inline-flex items-center gap-2.5 px-4 py-2 rounded-xl {{ $driveLive ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-slate-100 text-slate-700' }} font-semibold cursor-pointer">
                        <input type="checkbox" name="google_drive_live_enabled" value="1" @checked($driveLive)>
                        <span>Enable Live Google Drive API</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Root Academic Folder ID</label>
                        <input type="text" name="google_drive_root_folder_id"
                               value="{{ \App\Models\SystemSetting::getValue('google_drive_root_folder_id', config('excellia.drive.root_folder_id')) }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Root Folder Display Name</label>
                        <input type="text" name="google_drive_root_folder_name"
                               value="{{ \App\Models\SystemSetting::getValue('google_drive_root_folder_name', config('excellia.drive.root_folder_name')) }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Drive OAuth Client ID</label>
                        <input type="text" name="google_drive_client_id"
                               value="{{ \App\Models\SystemSetting::getValue('google_drive_client_id', '') }}"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">
                            Drive Client Secret @if($driveSecretMasked) <span class="text-xs text-emerald-700">(Saved)</span> @endif
                        </label>
                        <input type="password" name="google_drive_client_secret" placeholder="Leave blank to keep"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Drive Refresh Token</label>
                        <input type="password" name="google_drive_refresh_token" placeholder="Leave blank to keep"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Google Workspace Sign-In (SSO)</h3>
                            <p class="text-xs text-slate-500">Show or hide "Sign in with Google Workspace" on the login page.</p>
                        </div>
                        <label class="inline-flex items-center gap-2.5 px-4 py-2 rounded-xl {{ $ssoOn ? 'bg-indigo-50 text-indigo-800' : 'bg-slate-100 text-slate-700' }} font-semibold cursor-pointer">
                            <input type="checkbox" name="google_sso_enabled" value="1" @checked($ssoOn)>
                            <span>Enable Google Sign-In</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Allowed School Domain</label>
                            <input type="text" name="google_sso_hosted_domain"
                                   value="{{ \App\Models\SystemSetting::getValue('google_sso_hosted_domain', config('excellia.sso.allowed_hosted_domain', 'excelliaschool.edu.in')) }}"
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Google SSO Client ID</label>
                            <input type="text" name="google_sso_client_id"
                                   value="{{ \App\Models\SystemSetting::getValue('google_sso_client_id', '') }}"
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">
                                Google SSO Secret @if($ssoSecretMasked) <span class="text-xs text-emerald-700">(Saved)</span> @endif
                            </label>
                            <input type="password" name="google_sso_client_secret" placeholder="Leave blank to keep"
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save Google Drive & SSO Settings
                </button>
            </form>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit space-y-3 text-sm">
                <h3 class="text-base font-bold text-slate-900">Check Storage Connection</h3>
                <p class="text-xs text-slate-500">Verifies Google Drive OAuth / root folder access or confirms Local Vault readiness.</p>
                <form method="POST" action="{{ route('admin.settings.test') }}">
                    @csrf
                    <input type="hidden" name="target" value="google_drive">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-semibold cursor-pointer">
                        Verify Google Drive Status
                    </button>
                </form>
            </div>
        </div>

    @elseif($activeTab === 'ai')
        <!-- TAB 5: OpenRouter AI Assistant (Super Admin Controlled Enable/Disable + API Key + Model) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 space-y-5 text-sm">
                @csrf
                <input type="hidden" name="settings_section" value="ai">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">AI Work-Saver Assistant (OpenRouter)</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Helps Teachers draft lesson/paper checklists and helps Coordinators draft instructions & polite review notes.</p>
                    </div>
                    <label class="inline-flex items-center gap-2.5 px-4 py-2 rounded-xl {{ $aiOn ? 'bg-purple-100 border border-purple-300 text-purple-950' : 'bg-slate-100 text-slate-700' }} font-semibold cursor-pointer">
                        <input type="checkbox" name="ai_openrouter_enabled" value="1" @checked($aiOn)>
                        <span>Enable AI Assistant</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">
                            OpenRouter API Key
                            @if($openrouterMasked) <span class="text-xs text-emerald-700 font-semibold">Saved: {{ $openrouterMasked }}</span> @endif
                        </label>
                        <input type="password" name="openrouter_api_key" placeholder="sk-or-v1-... (leave blank to keep)"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">OpenRouter Model *</label>
                        @php $savedModel = \App\Models\SystemSetting::getValue('openrouter_model', 'openai/gpt-4o-mini'); @endphp
                        <input list="openrouter_models" type="text" name="openrouter_model" value="{{ $savedModel }}" required
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                        <datalist id="openrouter_models">
                            <option value="openai/gpt-4o-mini"></option>
                            <option value="google/gemini-2.0-flash-001"></option>
                            <option value="anthropic/claude-3.5-haiku"></option>
                            <option value="meta-llama/llama-3.3-70b-instruct"></option>
                        </datalist>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">OpenRouter Base URL</label>
                    <input type="url" name="openrouter_base_url"
                           value="{{ \App\Models\SystemSetting::getValue('openrouter_base_url', 'https://openrouter.ai/api/v1') }}"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                </div>

                <div class="space-y-2.5 pt-2 border-t border-slate-100">
                    <div class="font-medium text-slate-800">Who Can Use AI Work-Saver Buttons?</div>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="ai_allow_teachers" value="1"
                               @checked((bool) \App\Models\SystemSetting::getValue('ai_allow_teachers', true))>
                        <span>Teachers — One-click Lesson Plan / Paper Outline & Submission Note helper</span>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="ai_allow_coordinators" value="1"
                               @checked((bool) \App\Models\SystemSetting::getValue('ai_allow_coordinators', true))>
                        <span>Coordinators & Principals — One-click Task Instructions & Polite Feedback drafter</span>
                    </label>
                    @if($openrouterMasked)
                        <label class="flex items-center gap-2 text-xs text-rose-700 pt-1">
                            <input type="checkbox" name="clear_openrouter_api_key" value="1">
                            <span>Remove saved OpenRouter API key</span>
                        </label>
                    @endif
                </div>

                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save AI Assistant Settings
                </button>
            </form>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit space-y-3 text-sm">
                <h3 class="text-base font-bold text-slate-900">Test OpenRouter Key</h3>
                <p class="text-xs text-slate-500">Sends a quick test prompt to OpenRouter using your saved key and selected model.</p>
                <form method="POST" action="{{ route('admin.settings.test') }}">
                    @csrf
                    <input type="hidden" name="target" value="openrouter_ai">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-semibold cursor-pointer">
                        Test OpenRouter AI
                    </button>
                </form>
            </div>
        </div>

    @else
        <!-- TAB 6: Event Notification Rules & Late Escalation Rules CRUD -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-slate-900">Event Notification Switches</h2>
                    <p class="text-sm text-slate-500 mt-0.5">Choose Portal, Email, or WhatsApp for each workflow update.</p>
                </div>
                <div class="divide-y divide-slate-100 text-sm">
                    @foreach($notificationRules as $nRule)
                        @php $ch = (array) $nRule->channels; @endphp
                        <form method="POST" action="{{ route('admin.notification-rules.update', $nRule) }}" class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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
                                    <span>Active</span>
                                </label>
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-slate-900">Automatic Due-Date & Late Escalation Rules</h2>
                </div>
                @php
                    $triggerLabels = [
                        'DUE_SOON' => 'Days Before Due Date',
                        'ON_DUE_DATE' => 'On Due Date',
                        'OVERDUE_DAYS' => 'Days Late',
                        'REVIEW_SLA_HOURS' => 'Hours Waiting Check',
                    ];
                @endphp
                <div class="divide-y divide-slate-100 text-xs">
                    @foreach($escalationRules as $rule)
                        @php $rch = (array) $rule->channels; @endphp
                        <div class="p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                            <form method="POST" action="{{ route('admin.escalation-rules.update', $rule) }}" class="flex-1 flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" value="{{ $rule->name }}" required class="rounded-lg border border-slate-300 px-2.5 py-1.5 font-semibold w-48">
                                <span class="text-slate-500">{{ $triggerLabels[$rule->trigger_type] ?? $rule->trigger_type }}:</span>
                                <input type="number" name="threshold_value" value="{{ $rule->threshold_value }}" required class="w-16 rounded-lg border border-slate-300 px-2 py-1.5">
                                <input type="number" name="escalation_level" value="{{ $rule->escalation_level }}" min="0" max="5" class="w-14 rounded-lg border border-slate-300 px-2 py-1.5" title="Level">
                                <select name="target_role_key" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white">
                                    @foreach($roles as $r)
                                        <option value="{{ $r->key }}" @selected($rule->target_role_key === $r->key)>{{ $r->name }}</option>
                                    @endforeach
                                </select>
                                @foreach(['IN_APP' => 'Portal', 'EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'] as $ck => $cl)
                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="channels[]" value="{{ $ck }}" @checked(in_array($ck, $rch, true))><span>{{ $cl }}</span></label>
                                @endforeach
                                <label class="inline-flex items-center gap-1"><input type="checkbox" name="skip_weekends" value="1" @checked($rule->skip_weekends)><span>No Sun</span></label>
                                <label class="inline-flex items-center gap-1 text-emerald-700 font-semibold"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)><span>Active</span></label>
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Save</button>
                            </form>

                            <form method="POST" action="{{ route('admin.escalation-rules.destroy', $rule) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 font-semibold cursor-pointer">Delete</button>
                            </form>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.escalation-rules.store') }}" class="p-4 bg-slate-50 flex flex-wrap items-center gap-2">
                        @csrf
                        <span class="font-bold text-slate-700">Add Rule:</span>
                        <input type="text" name="name" required placeholder="e.g. 7 Days Late Alert" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white w-48">
                        <select name="trigger_type" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white">
                            @foreach($triggerLabels as $tk => $tl)
                                <option value="{{ $tk }}">{{ $tl }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="threshold_value" required value="7" class="rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white w-16">
                        <input type="number" name="escalation_level" required value="2" min="0" max="5" class="rounded-lg border border-slate-300 px-2 py-1.5 bg-white w-14">
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
            </div>
        </div>
    @endif
@endsection
