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
                    Mail::raw($messageSummary, function ($mail) use ($recipient, $subject) {
                        $mail->to($recipient->email, $recipient->full_name)->subject($subject);
                    });

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
            $cc = preg_replace('/\D+/', '', (string) config('excellia.whatsapp.default_country_code', '91')) ?: '91';
            $digits = $cc . $digits;
        }

        return (strlen($digits) >= 11 && strlen($digits) <= 15) ? $digits : null;
    }

    private function deliverWhatsAppMessage(
        string $e164Digits,
        User $recipient,
        string $eventKey,
        string $subject,
        string $messageSummary
    ): string {
        $provider = (string) config('excellia.whatsapp.provider', 'sandbox');
        $formattedBody = "*Excellia School Academic Portal*\n*{$subject}*\n{$messageSummary}";

        if ($provider === 'meta_cloud') {
            $apiUrl = rtrim((string) config('excellia.whatsapp.api_url', 'https://graph.facebook.com/v21.0'), '/');
            $phoneId = (string) config('excellia.whatsapp.phone_number_id');
            $token = (string) config('excellia.whatsapp.access_token');

            if ($phoneId === '' || $token === '') {
                throw new RuntimeException('Meta WhatsApp Cloud API phone_number_id or access_token is not configured.');
            }

            $resp = Http::withToken($token)->post("{$apiUrl}/{$phoneId}/messages", [
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

        if ($provider === 'webhook') {
            $webhookUrl = (string) config('excellia.whatsapp.api_url');
            if ($webhookUrl === '') {
                throw new RuntimeException('WhatsApp webhook gateway URL is not configured.');
            }

            $headers = [];
            if ($secret = config('excellia.whatsapp.webhook_secret')) {
                $headers['X-Webhook-Secret'] = (string) $secret;
            }

            $resp = Http::withHeaders($headers)->post($webhookUrl, [
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
}
