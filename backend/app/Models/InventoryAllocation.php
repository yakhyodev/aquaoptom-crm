<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAllocation extends Model
{
    protected $fillable = [
        'device_id',
        'product_variant_id',
        'warehouse_id',
        'allocated_quantity',
        'consumed_quantity',
        'returned_quantity',
        'epoch',
        'status',
        'notes',
    ];

    protected $casts = [
        'allocated_quantity' => 'integer',
        'consumed_quantity' => 'integer',
        'returned_quantity' => 'integer',
        'epoch' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryAllocationMovement::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, (int) $this->allocated_quantity - (int) $this->consumed_quantity - (int) $this->returned_quantity);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
