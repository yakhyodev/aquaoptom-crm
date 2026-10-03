<?php

namespace App\Services\Catalog;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductPackage;
use App\Models\ProductVariant;
use App\Models\Volume;
use Illuminate\Support\Facades\DB;

class CreateVariantService
{
    /**
     * Yangi hajm (Volume) yaratish yoki mavjudini olish
     */
    public function findOrCreateVolume(string $displayName, int $valueMl, ?int $userId = null): Volume
    {
        return Volume::firstOrCreate(
            ['value_ml' => $valueMl],
            [
                'name' => trim($displayName),
                'status' => 'active',
                'created_by' => $userId,
            ]
        );
    }

    /**
     * ProductVariant yaratish (agar mavjud bo'lmasa)
     */
    public function execute(Product $product, Volume $volume, ?int $defaultSalePrice = null, ?int $userId = null, string $source = 'PRODUCT_MASTER'): ProductVariant
    {
        $existing = ProductVariant::where('product_id', $product->id)
            ->where('volume_id', $volume->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($product, $volume, $defaultSalePrice, $userId, $source) {
            $sku = ProductVariant::generateSku($product, $volume);

            // Agar SKU bazada takrorlansa unikal qilish
            $skuCount = ProductVariant::where('sku', 'like', "{$sku}%")->count();
            if ($skuCount > 0) {
                $sku .= '-'.($skuCount + 1);
            }

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'volume_id' => $volume->id,
                'sku' => $sku,
                'default_sale_price' => $defaultSalePrice,
                'minimum_stock' => 50,
                'status' => 'active',
                'created_by' => $userId,
            ]);

            // Standart qadoqlash konfiguratsiyasi (Dona, Blok, Yashik)
            ProductPackage::firstOrCreate(['product_variant_id' => $variant->id, 'name' => 'dona'], ['units_per_package' => 1]);
            ProductPackage::firstOrCreate(['product_variant_id' => $variant->id, 'name' => 'blok'], ['units_per_package' => 6]);
            ProductPackage::firstOrCreate(['product_variant_id' => $variant->id, 'name' => 'yashik'], ['units_per_package' => 12]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'VARIANT_CREATE',
                'auditable_type' => ProductVariant::class,
                'auditable_id' => $variant->id,
                'new_values' => [
                    'product' => $product->name,
                    'volume' => $volume->name,
                    'sku' => $variant->sku,
                    'created_from' => $source,
                ],
            ]);

            return $variant;
        });
    }
}
