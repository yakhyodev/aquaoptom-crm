<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyItem extends Model
{
    protected $fillable = [
        'supply_id',
        'product_variant_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
