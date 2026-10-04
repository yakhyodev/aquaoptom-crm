<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageItem extends Model
{
    protected $fillable = [
        'damage_record_id',
        'product_variant_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'integer',
        'total_cost' => 'integer',
    ];

    public function damageRecord(): BelongsTo
    {
        return $this->belongsTo(DamageRecord::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
