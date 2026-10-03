<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncConflict extends Model
{
    protected $fillable = [
        'device_id',
        'user_id',
        'operation_id',
        'operation_type',
        'status',
        'device_created_at',
        'received_at',
        'posted_at',
        'raw_payload',
        'payload_fingerprint',
        'error_code',
        'error_message',
        'resolution_action',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'server_entity_type',
        'server_entity_id',
    ];

    protected $casts = [
        'device_created_at' => 'datetime',
        'received_at' => 'datetime',
        'posted_at' => 'datetime',
        'raw_payload' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
