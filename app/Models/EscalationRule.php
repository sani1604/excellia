<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscalationRule extends Model
{
    protected $fillable = [
        'name',
        'trigger_type',
        'threshold_value',
        'escalation_level',
        'target_role_key',
        'channels',
        'skip_weekends',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'integer',
            'escalation_level' => 'integer',
            'channels' => 'array',
            'skip_weekends' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
