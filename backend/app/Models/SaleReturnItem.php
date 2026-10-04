<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'cost_price',
        'line_total',
        'cost_total',
        'is_damaged',
        'condition',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'integer',
        'cost_price' => 'integer',
        'line_total' => 'integer',
        'cost_total' => 'integer',
        'is_damaged' => 'boolean',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
