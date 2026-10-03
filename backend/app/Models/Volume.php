<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Volume extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'value_ml',
        'status',
        'created_by',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function hasHistoricalRecords(): bool
    {
        return $this->variants()->where(function ($query) {
            $query->whereHas('movements');
        })->exists();
    }
}
