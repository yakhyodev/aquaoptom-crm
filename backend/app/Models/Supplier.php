<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'company_name',
        'phone',
        'address',
        'balance',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'balance' => 'integer',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(SupplierLedger::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->name];

        if (! empty($this->company_name)) {
            $parts[] = $this->company_name;
        }

        if (! empty($this->phone)) {
            $parts[] = 'tel: '.$this->phone;
        }

        return implode(' — ', $parts);
    }

    public function hasHistoricalRecords(): bool
    {
        return $this->purchases()->exists() || $this->ledger()->exists();
    }
}
