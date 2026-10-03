<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'volume_id',
        'sku',
        'barcode',
        'default_sale_price',
        'minimum_stock',
        'status',
        'version',
        'created_by',
    ];

    protected $casts = [
        'default_sale_price' => 'integer',
        'minimum_stock' => 'integer',
        'version' => 'integer',
    ];

    public function isSystemPriceSet(): bool
    {
        return $this->default_sale_price !== null && $this->default_sale_price > 0;
    }

    public function hasHistoricalRecords(): bool
    {
        return $this->movements()->exists()
            || SaleItem::where('product_variant_id', $this->id)->exists()
            || PurchaseItem::where('product_variant_id', $this->id)->exists();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function volume(): BelongsTo
    {
        return $this->belongsTo(Volume::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ProductPackage::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(InventoryBalance::class, 'product_variant_id');
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'product_variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_variant_id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class, 'product_variant_id');
    }

    public function inventoryAllocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class, 'product_variant_id');
    }

    /**
     * SKU generatsiyasi (FANTA-500)
     */
    public static function generateSku(Product $product, Volume $volume): string
    {
        $cleanName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $product->name));
        $skuBase = substr($cleanName, 0, 8);

        return "{$skuBase}-{$volume->value_ml}";
    }
}
