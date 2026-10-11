<?php

namespace App\Services\Sync;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncChangeLog;
use App\Models\User;
use App\Models\Volume;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SyncChangeLogService
{
    // PostgreSQL advisory lock ID commit-order kafolati uchun
    private const ADVISORY_LOCK_ID = 987654321;

    /**
     * Entity o'zgarishini change logga yozish.
     * PostgreSQL advisory lock orqali ID lar qat'iy commit tartibida beriladi,
     * bu esa "late commit past ID yo'qolishi" (lost commits) xavfini to'liq yo'qotadi.
     */
    public function logChange(
        string $entityType,
        string|int $entityId,
        string $changeType,
        array $payload,
        int $version = 1,
        bool $isTombstone = false
    ): SyncChangeLog {
        // Agar PostgreSQL bo'lsa, tranzaksiya darajasida advisory lock olamiz
        if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() > 0) {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [self::ADVISORY_LOCK_ID]);
        }

        if (in_array(strtoupper($entityType), ['PRODUCT_VARIANT', 'VARIANT'], true) && ! $isTombstone && ! isset($payload['volume_ml'])) {
            $variant = ProductVariant::with('volume')->find($entityId);
            if ($variant?->volume) {
                $payload += ['volume_id' => $variant->volume_id, 'volume_ml' => $variant->volume->value_ml,
                    'volume_litres' => (string) $variant->volume->litres];
            }
        }

        return SyncChangeLog::create([
            'entity_type' => strtoupper($entityType),
            'entity_id' => (string) $entityId,
            'change_type' => strtoupper($changeType),
            'version' => $version,
            'is_tombstone' => $isTombstone,
            'payload' => $payload,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Kursor bo'yicha o'zgarishlarni tortib olish (Delta pull).
     *
     * @param  int  $cursor  Qurilma bilgan oxirgi kursor (id)
     * @param  int  $limit  Sahifadagi maksimal yozuvlar soni
     * @param  User|null  $user  Ruxsatlar bo'yicha maxfiy moliyaviy ma'lumotlarni yashirish uchun
     */
    public function pullChanges(int $cursor = 0, int $limit = 50, ?User $user = null): array
    {
        $limit = max(1, min($limit, 200));

        $changes = SyncChangeLog::where('id', '>', $cursor)
            ->orderBy('id', 'asc')
            ->limit($limit + 1)
            ->get();

        $hasMore = $changes->count() > $limit;
        if ($hasMore) {
            $changes = $changes->slice(0, $limit);
        }

        $canViewCost = $user && $user->hasPermission('view_cost_price');

        $items = $changes->map(function (SyncChangeLog $change) use ($canViewCost) {
            $payload = $change->payload;

            // Xodimda tannarxni ko'rish ruxsati bo'lmasa, maxfiy ma'lumotlarni yashirish
            if (! $canViewCost && is_array($payload)) {
                $payload = $this->maskSensitiveFields($payload);
            }

            return [
                'cursor' => $change->id,
                'entity_type' => $change->entity_type,
                'entity_id' => $change->entity_id,
                'change_type' => $change->change_type,
                'version' => $change->version,
                'is_tombstone' => $change->is_tombstone,
                'payload' => $payload,
                'created_at' => $change->created_at->format('Y-m-d\TH:i:s\Z'),
            ];
        })->values()->all();

        $nextCursor = ! empty($items) ? end($items)['cursor'] : $cursor;

        return [
            'items' => $items,
            'cursor' => $cursor,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'count' => count($items),
        ];
    }

    /**
     * Tizimdagi mavjud ma'lumotlarni change logga boshlang'ich import qilish (Init feed).
     */
    public function populateInitialChanges(): int
    {
        $count = 0;

        DB::transaction(function () use (&$count) {
            // Mahsulotlar
            foreach (Product::whereNull('deleted_at')->get() as $product) {
                if (! SyncChangeLog::where('entity_type', 'PRODUCT')->where('entity_id', (string) $product->id)->exists()) {
                    $this->logChange('PRODUCT', $product->id, 'CREATED', [
                        'id' => $product->id,
                        'name' => $product->name,
                        'normalized_name' => $product->normalized_name,
                        'code' => $product->code,
                        'status' => $product->status,
                    ]);
                    $count++;
                }
            }

            // Hajmlar
            foreach (Volume::whereNull('deleted_at')->get() as $volume) {
                if (! SyncChangeLog::where('entity_type', 'VOLUME')->where('entity_id', (string) $volume->id)->exists()) {
                    $this->logChange('VOLUME', $volume->id, 'CREATED', [
                        'id' => $volume->id,
                        'name' => $volume->name,
                        'value_ml' => $volume->value_ml,
                        'unit' => $volume->unit,
                    ]);
                    $count++;
                }
            }

            // Variantlar
            foreach (ProductVariant::with(['product', 'volume'])->whereNull('deleted_at')->get() as $variant) {
                if (! SyncChangeLog::where('entity_type', 'PRODUCT_VARIANT')->where('entity_id', (string) $variant->id)->exists()) {
                    $this->logChange('PRODUCT_VARIANT', $variant->id, 'CREATED', [
                        'id' => $variant->id,
                        'product_id' => $variant->product_id,
                        'volume_id' => $variant->volume_id,
                        'product_name' => $variant->product?->name,
                        'volume_name' => $variant->volume?->name,
                        'sku' => $variant->sku,
                        'default_sale_price' => $variant->default_sale_price,
                        'version' => $variant->version,
                        'status' => $variant->status,
                    ], $variant->version ?: 1);
                    $count++;
                }
            }

            // Mijozlar
            foreach (Customer::whereNull('deleted_at')->get() as $customer) {
                if (! SyncChangeLog::where('entity_type', 'CUSTOMER')->where('entity_id', (string) $customer->id)->exists()) {
                    $this->logChange('CUSTOMER', $customer->id, 'CREATED', [
                        'id' => $customer->id,
                        'uuid' => $customer->uuid,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'store_name' => $customer->store_name,
                        'address' => $customer->address,
                        'debt_limit' => $customer->debt_limit,
                        'is_strict_credit_limit' => $customer->is_strict_credit_limit,
                        'current_debt' => $customer->current_debt,
                        'status' => $customer->status,
                    ]);
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Maxfiy maydonlarni (tannarx, o'rtacha tannarx) yashirish.
     */
    public function maskSensitiveFields(array $payload): array
    {
        $sensitiveKeys = ['cost_price', 'average_cost', 'total_cost', 'unit_cost', 'gross_profit', 'net_profit', 'profit', 'total_value'];
        foreach ($payload as $key => $value) {
            if (in_array($key, $sensitiveKeys, true)) {
                $payload[$key] = null;
            } elseif (is_array($value)) {
                $payload[$key] = $this->maskSensitiveFields($value);
            }
        }

        return $payload;
    }
}
