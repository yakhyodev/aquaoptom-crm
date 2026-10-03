<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supply extends Model
{
    protected $fillable = [
        'supplier_name',
        'total_amount',
        'source',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SupplyItem::class);
    }
}
