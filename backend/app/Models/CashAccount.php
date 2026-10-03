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

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function activeSession()
    {
        return $this->hasOne(CashSession::class)->where('status', 'OPEN');
    }

    public function hasOpenSession(): bool
    {
        return $this->sessions()->where('status', 'OPEN')->exists();
    }
}
