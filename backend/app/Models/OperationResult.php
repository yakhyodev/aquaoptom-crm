<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'operation_id',
        'operation_type',
        'payload_fingerprint',
        'actor_id',
        'device_id',
        'source',
        'status',
        'expected_version',
        'result_payload',
        'error_category',
        'error_code',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'result_payload' => 'array',
        'expected_version' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
