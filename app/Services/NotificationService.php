<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NotificationService
{
    public function dispatch(
        string $eventKey,
        string $entityType,
        int $entityId,
        User $recipient,
        string $subject,
        string $messageSummary,
        ?array $channels = null,
        ?string $customIdempotencyScope = null
    ): array {
        if (! $recipient->is_active) {
            return [];
        }

        $rule = NotificationRule::query()->where('event_key', $eventKey)->first();
        if ($rule && ! $rule->is_active) {
            return [];
        }

        $resolvedChannels = $channels ?? ($rule?->channels ?: ['IN_APP']);
        if (empty($resolvedChannels)) {
            return [];
        }

        $logs = [];
        $emailEnabled = (bool) SystemSetting::getValue('email_notifications_enabled', false);
        $whatsappEnabled = (bool) SystemSetting::getValue('whatsapp_notifications_enabled', false);
        $maxRetries = (int) config('excellia.workflow.max_notification_retries', 3);

        foreach (array_unique($resolvedChannels) as $channel) {
            $scopePart = $customIdempotencyScope ?: Str::uuid()->toString();
            $idempotencyKey = hash(
                'sha256',
                "{$eventKey}|{$entityType}|{$entityId}|{$recipient->id}|{$channel}|{$scopePart}"
            );

            $existing = NotificationLog::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && in_array($existing->delivery_status, ['SENT', 'DELIVERED', 'SKIPPED'], true)) {
                $logs[] = $existing;
                continue;
            }

            if ($existing && $existing->retry_count >= $maxRetries) {
                $logs[] = $existing;
                continue;
            }

            if ($channel === 'IN_APP') {
                $logs[] = NotificationLog::query()->updateOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'event_key' => $eventKey,
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'recipient_user_id' => $recipient->id,
                        'channel' => 'IN_APP',
                        'subject' => $subject,
                        'message_summary' => $messageSummary,
                        'sent_at' => now(),
                        'delivery_status' => 'DELIVERED',
                        'provider_message_id' => 'inapp_' . Str::random(16),
                        'error_message' => null,
                    ]
                );
                continue;
            }

            if ($channel === 'EMAIL') {
                if (! $emailEnabled || empty($recipient->email)) {
                    continue;
                }

                $retryCount = $existing ? ($existing->retry_count + 1) : 0;
                try {
                    $this->sendConfiguredEmail($recipient->email, $recipient->full_name, $subject, $messageSummary);

                    $logs[] = NotificationLog::query()->updateOrCreate(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'event_key' => $eventKey,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'recipient_user_id' => $recipient->id,
                            'channel' => 'EMAIL',
                            'subject' => $subject,
                            'message_summary' => $messageSummary,
                            'sent_at' => now(),
                            'delivery_status' => 'SENT',
                            'retry_count' => $retryCount,
                            'provider_message_id' => 'mail_' . Str::random(16),
                            'error_message' => null,
                        ]
                    );
                } catch (Throwable $e) {
                    $logs[] = NotificationLog::query()->updateOrCreate(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'event_key' => $eventKey,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'recipient_user_id' => $recipient->id,
                            'channel' => 'EMAIL',
                            'subject' => $subject,
                            'message_summary' => $messageSummary,
                            'sent_at' => null,
                            'delivery_status' => 'FAILED',
                            'retry_count' => $retryCount,
                            'error_message' => mb_substr($e->getMessage(), 0, 500),
                        ]
                    );
                }
                continue;
            }

            if ($channel === 'WHATSAPP') {
                if (! $whatsappEnabled) {
                    continue;
                }

                $normalizedPhone = $this->normalizePhoneForWhatsApp($recipient->phone);
                if ($normalizedPhone === null) {
                    $logs[] = NotificationLog::query()->updateOrCreate(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'event_key' => $eventKey,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'recipient_user_id' => $recipient->id,
                            'channel' => 'WHATSAPP',
                            'subject' => $subject,
                            'message_summary' => $messageSummary,
                            'sent_at' => null,
                            'delivery_status' => 'SKIPPED',
                            'error_message' => 'Recipient has no valid WhatsApp phone number configured.',
                        ]
                    );
                    continue;
                }

                $retryCount = $existing ? ($existing->retry_count + 1) : 0;
                try {
                    $providerMsgId = $this->deliverWhatsAppMessage($normalizedPhone, $recipient, $eventKey, $subject, $messageSummary);

                    $logs[] = NotificationLog::query()->updateOrCreate(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'event_key' => $eventKey,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'recipient_user_id' => $recipient->id,
                            'channel' => 'WHATSAPP',
                            'subject' => $subject,
                            'message_summary' => $messageSummary,
                            'sent_at' => now(),
                            'delivery_status' => 'SENT',
                            'retry_count' => $retryCount,
                            'provider_message_id' => $providerMsgId,
                            'error_message' => null,
                        ]
                    );
                } catch (Throwable $e) {
                    $logs[] = NotificationLog::query()->updateOrCreate(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'event_key' => $eventKey,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'recipient_user_id' => $recipient->id,
                            'channel' => 'WHATSAPP',
                            'subject' => $subject,
                            'message_summary' => $messageSummary,
                            'sent_at' => null,
                            'delivery_status' => 'FAILED',
                            'retry_count' => $retryCount,
                            'error_message' => mb_substr($e->getMessage(), 0, 500),
                        ]
                    );
                }
            }
        }

        return $logs;
    }

    public function normalizePhoneForWhatsApp(?string $rawPhone): ?string
    {
        if (! $rawPhone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if (strlen($digits) === 10) {
            $cc = preg_replace('/\D+/', '', (string) SystemSetting::getValue(
                'whatsapp_default_country_code',
                config('excellia.whatsapp.default_country_code', '91')
            )) ?: '91';
            $digits = $cc . $digits;
        }

        return (strlen($digits) >= 11 && strlen($digits) <= 15) ? $digits : null;
    }

    public function sendConfiguredEmail(string $toEmail, string $toName, string $subject, string $body): void
    {
        $mailer = (string) SystemSetting::getValue('mail_mailer_mode', config('mail.default', 'log'));
        $fromAddress = (string) SystemSetting::getValue('mail_from_address', config('mail.from.address', 'noreply@excelliaschool.edu.in'));
        $fromName = (string) SystemSetting::getValue('mail_from_name', config('mail.from.name', 'Excellia School Portal'));

        if ($mailer === 'smtp') {
            $host = (string) SystemSetting::getValue('mail_smtp_host', '');
            $port = (int) SystemSetting::getValue('mail_smtp_port', 587);
            $enc = (string) SystemSetting::getValue('mail_smtp_encryption', 'tls');
            $user = (string) SystemSetting::getValue('mail_smtp_username', '');
            $pass = SystemSetting::getSecret('mail_smtp_password');

            if ($host !== '') {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $host,
                    'mail.mailers.smtp.port' => $port,
                    'mail.mailers.smtp.encryption' => $enc !== 'none' ? $enc : null,
                    'mail.mailers.smtp.username' => $user !== '' ? $user : null,
                    'mail.mailers.smtp.password' => $pass,
                ]);
            }
        }

        Mail::raw($body, function ($mail) use ($toEmail, $toName, $subject, $fromAddress, $fromName) {
            if ($fromAddress !== '') {
                $mail->from($fromAddress, $fromName ?: 'Excellia School');
            }
            $mail->to($toEmail, $toName)->subject($subject);
        });
    }

    /**
     * Tests Whatify External API connectivity (`GET /ping` + optional `GET /wallet/balance`).
     * Official Docs: https://whatify.in/api-docs
     */
    public function verifyWhatifyConnection(): array
    {
        $baseUrl = rtrim((string) SystemSetting::getValue(
            'whatify_base_url',
            config('excellia.whatsapp.whatify_base_url', 'https://whatify.in/api/v1/external')
        ), '/');

        $apiKey = SystemSetting::getSecret('whatify_api_key', config('excellia.whatsapp.whatify_api_key'));
        if (! $apiKey) {
            throw new RuntimeException('Whatify API Key (wfy_...) is not saved yet. Please enter your Whatify API key first.');
        }

        $pingResp = Http::timeout(10)
            ->withHeaders(['X-API-Key' => $apiKey, 'Accept' => 'application/json'])
            ->get("{$baseUrl}/ping");

        if (! $pingResp->successful() || ($pingResp->json('status') !== 'ok' && ! $pingResp->json('success', true))) {
            throw new RuntimeException('Whatify /ping failed (HTTP ' . $pingResp->status() . '). Please verify your X-API-Key.');
        }

        $walletInfo = null;
        try {
            $walletResp = Http::timeout(8)
                ->withHeaders(['X-API-Key' => $apiKey, 'Accept' => 'application/json'])
                ->get("{$baseUrl}/wallet/balance");
            if ($walletResp->successful() && $walletResp->json('balance') !== null) {
                $walletInfo = ($walletResp->json('currency', 'INR')) . ' ' . $walletResp->json('balance');
            }
        } catch (Throwable) {
            // Optional metadata
        }

        return [
            'status' => 'ok',
            'wallet' => $walletInfo,
            'timestamp' => $pingResp->json('timestamp'),
        ];
    }

    public function sendDirectWhatsAppTest(string $rawPhone, string $recipientName, string $subject, string $messageSummary): string
    {
        $normalized = $this->normalizePhoneForWhatsApp($rawPhone);
        if (! $normalized) {
            throw new RuntimeException('Please enter a valid 10-digit or country-prefixed mobile number.');
        }

        $dummyRecipient = new User(['full_name' => $recipientName, 'phone' => $normalized]);

        return $this->deliverWhatsAppMessage($normalized, $dummyRecipient, 'TEST_CONNECTION', $subject, $messageSummary);
    }

    private function deliverWhatsAppMessage(
        string $e164Digits,
        User $recipient,
        string $eventKey,
        string $subject,
        string $messageSummary
    ): string {
        $provider = (string) config('excellia.whatsapp.provider', '');
        $configuredProvider = (string) SystemSetting::getValue('whatsapp_provider', '');
        // Allow config() override in tests if explicitly set to non-default, otherwise prefer DB setting
        $activeProvider = ($provider !== '' && $provider !== 'sandbox')
            ? $provider
            : ($configuredProvider !== '' ? $configuredProvider : ($provider ?: 'sandbox'));

        $formattedBody = "*Excellia School Academic Portal*\n*{$subject}*\n{$messageSummary}";

        if ($activeProvider === 'whatify') {
            return $this->deliverViaWhatify($e164Digits, $recipient, $subject, $messageSummary, $formattedBody);
        }

        if ($activeProvider === 'meta_cloud') {
            $apiUrl = rtrim((string) SystemSetting::getValue(
                'whatsapp_meta_api_url',
                config('excellia.whatsapp.api_url', 'https://graph.facebook.com/v21.0')
            ), '/');
            $phoneId = (string) (config('excellia.whatsapp.phone_number_id') ?: SystemSetting::getValue('whatsapp_phone_number_id', ''));
            $token = (string) (config('excellia.whatsapp.access_token') ?: SystemSetting::getSecret('whatsapp_meta_access_token'));

            if ($phoneId === '' || $token === '') {
                throw new RuntimeException('Meta WhatsApp Cloud API phone_number_id or access_token is not configured.');
            }

            $resp = Http::timeout(12)->withToken($token)->post("{$apiUrl}/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $e164Digits,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $formattedBody,
                ],
            ]);

            if (! $resp->successful()) {
                throw new RuntimeException('Meta WhatsApp Cloud API error (' . $resp->status() . ').');
            }

            return (string) ($resp->json('messages.0.id') ?: ('wamid_' . Str::random(16)));
        }

        if ($activeProvider === 'webhook') {
            $webhookUrl = (string) (config('excellia.whatsapp.api_url') ?: SystemSetting::getValue('whatsapp_webhook_url', ''));
            if ($webhookUrl === '') {
                throw new RuntimeException('WhatsApp webhook gateway URL is not configured.');
            }

            $headers = [];
            if ($secret = (config('excellia.whatsapp.webhook_secret') ?: SystemSetting::getSecret('whatsapp_webhook_secret'))) {
                $headers['X-Webhook-Secret'] = (string) $secret;
            }

            $resp = Http::timeout(12)->withHeaders($headers)->post($webhookUrl, [
                'to' => '+' . $e164Digits,
                'recipient_name' => $recipient->full_name,
                'event_key' => $eventKey,
                'subject' => $subject,
                'message' => $formattedBody,
            ]);

            if (! $resp->successful()) {
                throw new RuntimeException('WhatsApp webhook gateway returned HTTP ' . $resp->status() . '.');
            }

            return (string) ($resp->json('message_id') ?: ('wa_hook_' . Str::random(16)));
        }

        // Default 'sandbox' mode: safe local/UAT logging without external secret exposure
        $msgId = 'wa_sandbox_' . Str::random(16);
        Log::info('Excellia WhatsApp Sandbox Dispatch', [
            'message_id' => $msgId,
            'to' => '+' . $e164Digits,
            'event_key' => $eventKey,
            'subject' => $subject,
        ]);

        return $msgId;
    }

    /**
     * Delivers WhatsApp message via official Whatify External API (`https://whatify.in/api-docs`):
     * - Header: `X-API-Key: wfy_...`
     * - Text endpoint: `POST {base_url}/send-message` (`phone`, `message`, optional `whatsapp_account_id`)
     * - Template endpoint: `POST {base_url}/send-template` (`phone`, `template_name`, `body_params`, optional `whatsapp_account_id`)
     */
    private function deliverViaWhatify(
        string $e164Digits,
        User $recipient,
        string $subject,
        string $messageSummary,
        string $formattedBody
    ): string {
        $baseUrl = rtrim((string) SystemSetting::getValue(
            'whatify_base_url',
            config('excellia.whatsapp.whatify_base_url', 'https://whatify.in/api/v1/external')
        ), '/');

        $apiKey = SystemSetting::getSecret('whatify_api_key', config('excellia.whatsapp.whatify_api_key'));
        if (! $apiKey) {
            throw new RuntimeException('Whatify API Key (X-API-Key) is not configured in Super Admin Settings.');
        }

        $mode = (string) SystemSetting::getValue('whatify_mode', config('excellia.whatsapp.whatify_mode', 'text'));
        $templateName = trim((string) SystemSetting::getValue('whatify_template_name', config('excellia.whatsapp.whatify_template_name', '')));
        $accountIdRaw = SystemSetting::getValue('whatify_account_id', config('excellia.whatsapp.whatify_account_id'));
        $accountId = is_numeric($accountIdRaw) && (int) $accountIdRaw > 0 ? (int) $accountIdRaw : null;

        if ($mode === 'template' && $templateName !== '') {
            $payload = [
                'phone' => $e164Digits,
                'template_name' => $templateName,
                'body_params' => [
                    $recipient->full_name ?: 'Teacher',
                    $subject,
                    mb_substr($messageSummary, 0, 500),
                ],
            ];
            if ($accountId !== null) {
                $payload['whatsapp_account_id'] = $accountId;
            }

            $resp = Http::timeout(12)
                ->withHeaders(['X-API-Key' => $apiKey, 'Accept' => 'application/json'])
                ->post("{$baseUrl}/send-template", $payload);
        } else {
            $payload = [
                'phone' => $e164Digits,
                'message' => mb_substr($formattedBody, 0, 4000),
            ];
            if ($accountId !== null) {
                $payload['whatsapp_account_id'] = $accountId;
            }

            $resp = Http::timeout(12)
                ->withHeaders(['X-API-Key' => $apiKey, 'Accept' => 'application/json'])
                ->post("{$baseUrl}/send-message", $payload);
        }

        if (! $resp->successful() || $resp->json('success') === false) {
            $errMsg = (string) ($resp->json('error_message') ?: $resp->json('error') ?: ('HTTP ' . $resp->status()));
            throw new RuntimeException("Whatify API error: {$errMsg}");
        }

        return (string) (
            $resp->json('meta_response.wamid')
            ?: ($resp->json('message_id') ? 'whatify_' . $resp->json('message_id') : ('whatify_' . Str::random(12)))
        );
    }
}
