<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'phone',
        'store_name',
        'address',
        'debt_limit',
        'is_strict_credit_limit',
        'payment_due_date',
        'current_debt',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'debt_limit' => 'integer',
        'is_strict_credit_limit' => 'boolean',
        'current_debt' => 'integer',
        'payment_due_date' => 'date',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(CustomerLedger::class);
    }

    public function creditAllocations(): HasMany
    {
        return $this->hasMany(CreditAllocation::class);
    }

    /**
     * Arxitektura bo'yicha qidiruv va ko'rinish formati:
     * "Akmal — Bahor Market — tel: +998901234567 — Chilonzor"
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->name];

        if (! empty($this->store_name)) {
            $parts[] = $this->store_name;
        }

        if (! empty($this->phone)) {
            $parts[] = 'tel: '.$this->phone;
        } else {
            $parts[] = 'telefon yo\'q';
        }

        if (! empty($this->address)) {
            $parts[] = $this->address;
        }

        return implode(' — ', $parts);
    }

    /**
     * Tarixda ishlatilgan yozuvlar mavjudligini tekshirish (o'chirilmaslik himoyasi)
     */
    public function hasHistoricalRecords(): bool
    {
        return $this->sales()->exists() || $this->ledger()->exists();
    }
}
