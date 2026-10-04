<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotDraft extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'type',
        'step',
        'operation_id',
        'payload',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'chat_id' => 'integer',
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
