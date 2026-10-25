<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public static function timezone(): string
    {
        $tz = static::getValue('school_timezone', config('excellia.default_timezone', 'Asia/Kolkata'));

        return is_string($tz) && $tz !== '' ? $tz : 'Asia/Kolkata';
    }
}
