<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAllocationMovement extends Model
{
    protected $fillable = [
        'inventory_allocation_id',
        'device_id',
        'product_variant_id',
        'warehouse_id',
        'operation_id',
        'movement_type',
        'quantity',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(InventoryAllocation::class, 'inventory_allocation_id');
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
