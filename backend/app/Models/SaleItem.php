<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'package_id',
        'package_quantity',
        'quantity',
        'sale_price',
        'purchase_cost_snapshot', // Tarixiy WAC tannarx snapshot
        'line_total',
        'cost_total',
        'gross_profit',
        'is_system_price',
        'price_version',
    ];

    protected $casts = [
        'package_quantity' => 'integer',
        'quantity' => 'decimal:3',
        'sale_price' => 'integer',
        'purchase_cost_snapshot' => 'integer',
        'line_total' => 'integer',
        'cost_total' => 'integer',
        'gross_profit' => 'integer',
        'is_system_price' => 'boolean',
        'price_version' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ProductPackage::class, 'package_id');
    }
}
