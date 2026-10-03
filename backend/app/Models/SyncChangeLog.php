<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncChangeLog extends Model
{
    public $timestamps = false;

    protected $table = 'sync_change_log';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'change_type',
        'version',
        'is_tombstone',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_tombstone' => 'boolean',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
