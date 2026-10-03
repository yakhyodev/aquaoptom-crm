<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_variant_id',
        'package_id',
        'package_quantity',
        'quantity', // Jami donada
        'unit_cost', // 1 dona tannarx
        'total_cost',
    ];

    protected $casts = [
        'package_quantity' => 'integer',
        'quantity' => 'decimal:3',
        'unit_cost' => 'integer',
        'total_cost' => 'integer',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
