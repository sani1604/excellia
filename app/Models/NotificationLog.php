<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'idempotency_key',
        'event_key',
        'entity_type',
        'entity_id',
        'recipient_user_id',
        'channel',
        'subject',
        'message_summary',
        'read_at',
        'sent_at',
        'delivery_status',
        'retry_count',
        'provider_message_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'retry_count' => 'integer',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
