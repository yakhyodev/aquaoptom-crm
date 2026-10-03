<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductNormalizer
{
    /**
     * Nomni normallashtirish: lowercase, bo'shliqlarni tozalash, o'zbek tutuq belgilarini standartlashtirish
     */
    public static function normalize(string $name): string
    {
        return Product::normalizeName($name);
    }

    /**
     * Yangi mahsulot kodi
     */
    public static function generateCode(): string
    {
        return Product::generateCode();
    }

    /**
     * Mahsulotni topish yoki xavfsiz yaratish (concurrent duplicate protection)
     */
    public static function findOrCreate(string $name, ?string $description = null, ?int $createdBy = null): Product
    {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new InvalidArgumentException("Mahsulot nomi bo'sh bo'lishi mumkin emas!");
        }

        $normalized = static::normalize($cleanName);

        $existing = Product::where('normalized_name', $normalized)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($cleanName, $normalized, $description, $createdBy) {
                return Product::create([
                    'name' => $cleanName,
                    'normalized_name' => $normalized,
                    'code' => static::generateCode(),
                    'description' => $description,
                    'status' => 'active',
                    'created_by' => $createdBy,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            return Product::where('normalized_name', $normalized)->firstOrFail();
        }
    }
}
