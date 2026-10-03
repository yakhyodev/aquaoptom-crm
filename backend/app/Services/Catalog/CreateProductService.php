<?php

namespace App\Services\Catalog;

use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CreateProductService
{
    /**
     * Mahsulot yaratish (Katalogdan yoki Kirim vaqtida dinamik)
     */
    public function execute(string $name, ?string $description = null, ?int $userId = null, string $source = 'PRODUCT_MASTER'): Product
    {
        $normalized = Product::normalizeName($name);

        // Duplicate tekshiruvi
        $existing = Product::where('normalized_name', $normalized)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($name, $normalized, $description, $userId, $source) {
            $code = Product::generateCode();

            $product = Product::create([
                'name' => trim($name),
                'normalized_name' => $normalized,
                'code' => $code,
                'status' => 'active',
                'description' => $description,
                'created_by' => $userId,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'PRODUCT_CREATE',
                'auditable_type' => Product::class,
                'auditable_id' => $product->id,
                'new_values' => [
                    'name' => $product->name,
                    'code' => $product->code,
                    'created_from' => $source,
                ],
            ]);

            return $product;
        });
    }
}
