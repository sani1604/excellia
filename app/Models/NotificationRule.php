<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    protected $fillable = [
        'event_key',
        'name',
        'channels',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
