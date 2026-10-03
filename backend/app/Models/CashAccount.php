<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    protected $fillable = ['name', 'type', 'balance', 'is_default'];

    protected $casts = [
        'balance' => 'integer',
        'is_default' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
