<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    public $timestamps = false; // faqat updated_at

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'average_cost',
        'total_value',
        'updated_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'average_cost' => 'integer',
        'total_value' => 'integer',
        'updated_at' => 'datetime',
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
