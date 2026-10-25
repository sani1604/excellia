<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value_json',
        'group',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return $default;
            }
            $row = static::query()->where('key', $key)->first();
            if (! $row) {
                return $default;
            }
            $val = $row->value_json;

            return is_array($val) && array_key_exists('value', $val) ? $val['value'] : $val;
        } catch (Throwable) {
            return $default;
        }
    }

    public static function setValue(string $key, mixed $value, string $group = 'general', ?string $description = null, ?int $updatedBy = null): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value_json' => ['value' => $value],
                'group' => $group,
                'description' => $description,
                'updated_by' => $updatedBy,
            ]
        );
    }

    public static function setSecret(string $key, ?string $plainText, string $group = 'integrations', ?string $description = null, ?int $updatedBy = null): ?self
    {
        $trimmed = trim((string) $plainText);
        if ($trimmed === '') {
            return null;
        }

        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value_json' => ['encrypted' => Crypt::encryptString($trimmed)],
                'group' => $group,
                'description' => $description,
                'updated_by' => $updatedBy,
            ]
        );
    }

    public static function clearSecret(string $key): void
    {
        try {
            if (Schema::hasTable('system_settings')) {
                static::query()->where('key', $key)->delete();
            }
        } catch (Throwable) {
            // ignore
        }
    }

    public static function getSecret(string $key, ?string $envFallback = null): ?string
    {
        try {
            if (Schema::hasTable('system_settings')) {
                $row = static::query()->where('key', $key)->first();
                if ($row && is_array($row->value_json) && ! empty($row->value_json['encrypted'])) {
                    return Crypt::decryptString((string) $row->value_json['encrypted']);
                }
            }
        } catch (Throwable) {
            // Fall through to env fallback
        }

        $fallback = trim((string) $envFallback);

        return $fallback !== '' ? $fallback : null;
    }

    public static function hasSecret(string $key, ?string $envFallback = null): bool
    {
        return static::getSecret($key, $envFallback) !== null;
    }

    public static function maskedSecret(string $key, ?string $envFallback = null): ?string
    {
        $secret = static::getSecret($key, $envFallback);
        if ($secret === null) {
            return null;
        }

        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($secret, 0, 5) . '••••••••' . substr($secret, -4);
    }

    public static function timezone(): string
    {
        $tz = static::getValue('school_timezone', config('excellia.default_timezone', 'Asia/Kolkata'));

        return is_string($tz) && $tz !== '' ? $tz : 'Asia/Kolkata';
    }
}
