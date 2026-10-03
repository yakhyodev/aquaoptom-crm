<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $table = 'price_history';

    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'old_price',
        'new_price',
        'version',
        'changed_by',
        'changed_at',
        'reason',
    ];

    protected $casts = [
        'old_price' => 'integer',
        'new_price' => 'integer',
        'version' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
