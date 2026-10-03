<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCursor extends Model
{
    protected $fillable = [
        'device_id',
        'last_cursor',
        'last_pulled_at',
    ];

    protected $casts = [
        'last_cursor' => 'integer',
        'last_pulled_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
