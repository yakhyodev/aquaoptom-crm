<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'normalized_name',
        'code',
        'status',
        'description',
        'created_by',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    protected static function booted(): void
    {
        static::saving(function ($product) {
            if (empty($product->normalized_name) && ! empty($product->name)) {
                $product->normalized_name = static::normalizeName($product->name);
            }
            if (empty($product->code)) {
                $product->code = static::generateCode();
            }
        });
    }

    /**
     * Yangi mahsulot kodi avtomatik generatsiyasi (PRD-000001) — Concurrency safe
     */
    public static function generateCode(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            $seq = DB::select("SELECT nextval('product_code_seq') as seq")[0]->seq;

            return sprintf('PRD-%06d', $seq);
        }

        $lastId = static::max('id') ?? 0;

        return sprintf('PRD-%06d', $lastId + 1);
    }

    /**
     * Nomni normallashtirish (duplicate protection)
     */
    public static function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = str_replace(["\xe2\x80\x98", "\xe2\x80\x99", "\xca\xbb", '`', '’', '‘', 'ʻ'], "'", $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\'\-]+/u', ' ', $normalized);

        return trim(preg_replace('/\s+/', ' ', $normalized));
    }

    /**
     * Tarixda ishlatilgan yozuvlar mavjudligini tekshirish
     */
    public function hasHistoricalRecords(): bool
    {
        return $this->variants()->where(function ($query) {
            $query->whereHas('movements')
                ->orWhereHas('packages');
        })->exists();
    }
}
