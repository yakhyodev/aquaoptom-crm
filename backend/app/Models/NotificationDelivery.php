<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'outbox_event_id',
        'chat_id',
        'user_id',
        'channel',
        'notification_type',
        'message_text',
        'status',
        'retry_count',
        'last_error',
        'sent_at',
    ];

    protected $casts = [
        'outbox_event_id' => 'integer',
        'chat_id' => 'integer',
        'user_id' => 'integer',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class);
    }
}
