<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAllocation extends Model
{
    protected $fillable = [
        'device_id',
        'customer_id',
        'is_new_customer_budget',
        'allocated_amount',
        'consumed_amount',
        'returned_amount',
        'epoch',
        'status',
        'notes',
    ];

    protected $casts = [
        'is_new_customer_budget' => 'boolean',
        'allocated_amount' => 'integer',
        'consumed_amount' => 'integer',
        'returned_amount' => 'integer',
        'epoch' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CreditAllocationMovement::class);
    }

    public function getAvailableAmountAttribute(): int
    {
        return max(0, (int) $this->allocated_amount - (int) $this->consumed_amount - (int) $this->returned_amount);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
