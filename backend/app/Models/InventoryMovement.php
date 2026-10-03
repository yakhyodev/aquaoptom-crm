<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public $timestamps = false; // faqat created_at

    protected $fillable = [
        'operation_id',
        'product_variant_id',
        'warehouse_id',
        'movement_type', // PURCHASE, SALE, SALE_RETURN, PURCHASE_RETURN, DAMAGE, LOSS, ADJUSTMENT_IN, ADJUSTMENT_OUT, OPENING_BALANCE
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after_quantity',
        'balance_after_value',
        'reference_type',
        'reference_id',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'integer',
        'total_cost' => 'integer',
        'balance_after_quantity' => 'decimal:3',
        'balance_after_value' => 'integer',
        'created_at' => 'datetime',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
