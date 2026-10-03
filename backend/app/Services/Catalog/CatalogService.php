<?php

namespace App\Services\Catalog;

use App\Exceptions\CannotDeleteReferencedRecordException;
use App\Models\AuditLog;
use App\Models\PriceHistory;
use App\Models\ProductVariant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CatalogService
{
    /**
     * Mahsulot va uning variantini yaratish yoki topish.
     * Mahsulotga nom yetadi, ID/kod server beradi; hajm integer ml, product+volume unique.
     */
    public function createVariant(
        string $productName,
        string|int|float $volumeInput,
        ?int $defaultSalePrice = null,
        int $minimumStock = 50,
        ?string $barcode = null,
        ?int $createdBy = null
    ): ProductVariant {
        $product = ProductNormalizer::findOrCreate($productName, createdBy: $createdBy);
        $volume = VolumeNormalizer::findOrCreate($volumeInput, createdBy: $createdBy);

        $existing = ProductVariant::where('product_id', $product->id)
            ->where('volume_id', $volume->id)
            ->first();

        if ($existing) {
            // Agar yangi narx berilgan bo'lsa va variantda narx bo'lmasa, yangilaymiz
            if ($defaultSalePrice !== null && $existing->default_sale_price === null) {
                $this->updatePrice($existing, $defaultSalePrice, 'Dastlabki tizim narxi belgilandi', $createdBy);
            }

            return $existing;
        }

        $sku = ProductVariant::generateSku($product, $volume);

        try {
            return DB::transaction(function () use ($product, $volume, $sku, $barcode, $defaultSalePrice, $minimumStock, $createdBy) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'volume_id' => $volume->id,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'default_sale_price' => $defaultSalePrice,
                    'minimum_stock' => $minimumStock,
                    'status' => 'active',
                    'version' => 1,
                    'created_by' => $createdBy,
                ]);

                if ($defaultSalePrice !== null && $defaultSalePrice > 0) {
                    PriceHistory::create([
                        'product_variant_id' => $variant->id,
                        'old_price' => null,
                        'new_price' => $defaultSalePrice,
                        'version' => 1,
                        'changed_by' => $createdBy,
                        'changed_at' => now(),
                        'reason' => 'Boshlang\'ich tizim narxi',
                    ]);
                }

                return $variant;
            });
        } catch (UniqueConstraintViolationException $e) {
            return ProductVariant::where('product_id', $product->id)
                ->where('volume_id', $volume->id)
                ->firstOrFail();
        }
    }

    /**
     * Narxni yangilash — ruxsat, versiya va price_history bilan
     */
    public function updatePrice(
        ProductVariant $variant,
        ?int $newPrice,
        ?string $reason = null,
        ?int $userId = null
    ): ProductVariant {
        if ($newPrice !== null && $newPrice < 0) {
            throw new InvalidArgumentException("Sotuv narxi musbat bo'lishi shart!");
        }

        $oldPrice = $variant->default_sale_price;

        if ($oldPrice === $newPrice) {
            return $variant;
        }

        return DB::transaction(function () use ($variant, $oldPrice, $newPrice, $reason, $userId) {
            $newVersion = ($variant->version ?? 1) + 1;

            $variant->update([
                'default_sale_price' => $newPrice,
                'version' => $newVersion,
            ]);

            PriceHistory::create([
                'product_variant_id' => $variant->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice ?? 0,
                'version' => $newVersion,
                'changed_by' => $userId,
                'changed_at' => now(),
                'reason' => $reason ?? 'Narx o\'zgartirildi',
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'PRICE_CHANGE',
                'auditable_type' => ProductVariant::class,
                'auditable_id' => $variant->id,
                'old_values' => ['default_sale_price' => $oldPrice],
                'new_values' => ['default_sale_price' => $newPrice, 'version' => $newVersion, 'reason' => $reason],
                'created_at' => now(),
            ]);

            return $variant->fresh();
        });
    }

    /**
     * Minimal qoldiq (kam qoldiq chegarasi)ni yangilash
     */
    public function updateMinimumStock(ProductVariant $variant, int $minimumStock): ProductVariant
    {
        $variant->update(['minimum_stock' => $minimumStock]);

        return $variant->fresh();
    }

    /**
     * Variantni arxivlash
     */
    public function archiveVariant(ProductVariant $variant): bool
    {
        return $variant->update(['status' => 'archived']);
    }

    /**
     * Variantni faollashtirish
     */
    public function activateVariant(ProductVariant $variant): bool
    {
        return $variant->update(['status' => 'active']);
    }

    /**
     * Variantni o'chirish — Tarixda ishlatilgan yozuvlar o'chirilmaydi!
     */
    public function deleteVariant(ProductVariant $variant): bool
    {
        if ($variant->hasHistoricalRecords()) {
            throw new CannotDeleteReferencedRecordException(
                "Ushbu mahsulot varianti savdo yoki ombor harakatlarida ishlatilgan, uni o'chirib bo'lmaydi! Uni arxivlash mumkin."
            );
        }

        return (bool) $variant->delete();
    }
}
