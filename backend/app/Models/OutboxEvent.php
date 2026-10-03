<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'operation_id',
        'event_name',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'retry_count',
        'max_retries',
        'last_error',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
