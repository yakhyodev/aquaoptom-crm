<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUpdate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'update_id',
        'chat_id',
        'created_at',
    ];

    protected $casts = [
        'update_id' => 'integer',
        'chat_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
