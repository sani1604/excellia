<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'api_token_hash',
        'api_token',
        'remember_token',
        'access_token',
        'refresh_token',
        'private_key',
        'client_secret',
        'file_contents',
        'raw_bytes',
        'token',
        'secret',
    ];

    public function log(
        string $actionKey,
        string $entityType,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
        ?Request $request = null
    ): AuditEvent {
        $req = $request ?? (app()->bound('request') ? request() : null);
        $actorId = $actor?->id ?? auth()->id();

        return AuditEvent::query()->create([
            'actor_user_id' => $actorId,
            'action_key' => $actionKey,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before !== null ? $this->redact($before) : null,
            'after_json' => $after !== null ? $this->redact($after) : null,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 512) : null,
            'created_at' => now(),
        ]);
    }

    public function redact(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            $normalized = mb_strtolower((string) $key);
            $shouldRedact = false;
            foreach (self::REDACTED_KEYS as $sensitive) {
                if ($normalized === $sensitive || str_contains($normalized, $sensitive)) {
                    $shouldRedact = true;
                    break;
                }
            }

            if ($shouldRedact) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->redact($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
