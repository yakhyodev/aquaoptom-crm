<?php

namespace App\Services\Inventory;

use App\Events\LowStockDetected;
use App\Models\InventoryAllocation;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryStockService
{
    /**
     * Standart asosiy ombor ID sini olish.
     */
    public function getDefaultWarehouseId(): int
    {
        return (int) (Warehouse::where('is_default', true)->value('id')
            ?: Warehouse::value('id')
            ?: 1);
    }

    /**
     * Filtrlar asosida server qidiruvi query'sini shakllantirish (Paginationdan oldin).
     *
     * @param  array  $filters  [search, volume_ids, product_ids, min_stock, max_stock, threshold_filter, zero_stock_filter, price_filter, status_filter, slow_moving, sort_by, sort_direction]
     */
    public function getFilteredStockQuery(array $filters = [], ?int $warehouseId = null): Builder
    {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $query = ProductVariant::query()
            ->select('product_variants.*')
            ->leftJoin('inventory_balances', function ($join) use ($actualWarehouseId) {
                $join->on('product_variants.id', '=', 'inventory_balances.product_variant_id')
                    ->where('inventory_balances.warehouse_id', '=', $actualWarehouseId);
            })
            ->with([
                'product',
                'volume',
                'balance' => fn ($q) => $q->where('warehouse_id', $actualWarehouseId),
                'activeAllocations.device',
            ]);

        // 1. Matnli qidiruv (Nom, kod, SKU, shtrix-kod, hajm)
        if (! empty($filters['search'])) {
            $term = '%'.mb_strtolower(trim($filters['search'])).'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('product', function ($pq) use ($term) {
                    $pq->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                })
                    ->orWhereRaw('LOWER(product_variants.sku) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(product_variants.barcode) LIKE ?', [$term])
                    ->orWhereHas('volume', function ($vq) use ($term) {
                        $vq->whereRaw('LOWER(name) LIKE ?', [$term]);
                    });
            });
        }

        // 2. Hajmlar (Volume IDs)
        if (! empty($filters['volume_ids']) && is_array($filters['volume_ids'])) {
            $query->whereIn('product_variants.volume_id', array_filter($filters['volume_ids']));
        }

        // 3. Mahsulotlar (Product IDs)
        if (! empty($filters['product_ids']) && is_array($filters['product_ids'])) {
            $query->whereIn('product_variants.product_id', array_filter($filters['product_ids']));
        }

        // 4. Qoldiq diapazoni (Min / Max Stock)
        if (isset($filters['min_stock']) && is_numeric($filters['min_stock'])) {
            $query->whereRaw('COALESCE(inventory_balances.quantity, 0) >= ?', [(float) $filters['min_stock']]);
        }
        if (isset($filters['max_stock']) && is_numeric($filters['max_stock'])) {
            $query->whereRaw('COALESCE(inventory_balances.quantity, 0) <= ?', [(float) $filters['max_stock']]);
        }

        // 5. Chegara filtri (Threshold: below_threshold, normal)
        $thresholdFilter = $filters['threshold_filter'] ?? 'all';
        if ($thresholdFilter === 'below_threshold') {
            $query->whereRaw('COALESCE(inventory_balances.quantity, 0) <= product_variants.minimum_stock AND product_variants.minimum_stock > 0');
        } elseif ($thresholdFilter === 'normal') {
            $query->whereRaw('(COALESCE(inventory_balances.quantity, 0) > product_variants.minimum_stock OR product_variants.minimum_stock = 0)');
        }

        // 6. Nol qoldiq filtri (zero_only, non_zero)
        $zeroStockFilter = $filters['zero_stock_filter'] ?? 'all';
        if ($zeroStockFilter === 'zero_only') {
            $query->whereRaw('COALESCE(inventory_balances.quantity, 0) <= 0');
        } elseif ($zeroStockFilter === 'non_zero') {
            $query->whereRaw('COALESCE(inventory_balances.quantity, 0) > 0');
        }

        // 7. Sotuv narxi mavjudligi (has_price, no_price)
        $priceFilter = $filters['price_filter'] ?? 'all';
        if ($priceFilter === 'has_price') {
            $query->whereNotNull('product_variants.default_sale_price')
                ->where('product_variants.default_sale_price', '>', 0);
        } elseif ($priceFilter === 'no_price') {
            $query->where(function ($q) {
                $q->whereNull('product_variants.default_sale_price')
                    ->orWhere('product_variants.default_sale_price', '<=', 0);
            });
        }

        // 8. Holati (active, archived, all)
        $statusFilter = $filters['status_filter'] ?? 'active';
        if ($statusFilter === 'active') {
            $query->where('product_variants.status', 'active');
        } elseif ($statusFilter === 'archived') {
            $query->withTrashed()->where(function ($q) {
                $q->where('product_variants.status', '!=', 'active')
                    ->orWhereNotNull('product_variants.deleted_at');
            });
        } elseif ($statusFilter === 'all') {
            $query->withTrashed();
        }

        // 9. Sekin sotiladigan tovarlar (so'nggi 30 kunda sotuv bo'lmagan)
        if (! empty($filters['slow_moving'])) {
            $query->whereDoesntHave('movements', function ($mq) {
                $mq->whereIn('movement_type', ['SALE', 'OUTWARD'])
                    ->where('created_at', '>=', now()->subDays(30));
            });
        }

        // 10. Saralash (Sorting)
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = strtolower($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sortBy) {
            case 'quantity':
                $query->orderByRaw("COALESCE(inventory_balances.quantity, 0) {$sortDirection}");
                break;
            case 'cost':
                $query->orderByRaw("COALESCE(inventory_balances.average_cost, 0) {$sortDirection}");
                break;
            case 'total_value':
                $query->orderByRaw("COALESCE(inventory_balances.total_value, 0) {$sortDirection}");
                break;
            case 'price':
                $query->orderBy('product_variants.default_sale_price', $sortDirection);
                break;
            case 'sku':
                $query->orderBy('product_variants.sku', $sortDirection);
                break;
            case 'name':
            default:
                $query->join('products as p_sort', 'product_variants.product_id', '=', 'p_sort.id')
                    ->orderBy('p_sort.name', $sortDirection)
                    ->orderBy('product_variants.id', 'asc');
                break;
        }

        return $query;
    }

    /**
     * Barcha filtrlangan to'plam bo'yicha umumiy agregatlangan natijalar (faqat bitta sahifa emas).
     *
     * @return array<string, mixed>
     */
    public function getAggregatedTotals(array $filters = [], ?int $warehouseId = null, bool $canViewCost = true): array
    {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();
        $query = $this->getFilteredStockQuery($filters, $actualWarehouseId);
        $statsQuery = (clone $query)->reorder();
        $statsQuery->getQuery()->columns = [];

        $stats = $statsQuery->selectRaw('
            COUNT(product_variants.id) as total_variants_count,
            COALESCE(SUM(COALESCE(inventory_balances.quantity, 0)), 0) as total_physical_units,
            COALESCE(SUM(CASE WHEN product_variants.default_sale_price > 0 THEN COALESCE(inventory_balances.quantity, 0) * product_variants.default_sale_price ELSE 0 END), 0) as total_sale_value,
            COALESCE(SUM(COALESCE(inventory_balances.total_value, 0)), 0) as total_cost_value,
            COALESCE(COUNT(CASE WHEN product_variants.default_sale_price IS NULL OR product_variants.default_sale_price <= 0 THEN 1 END), 0) as missing_price_count,
            COALESCE(SUM(CASE WHEN product_variants.default_sale_price IS NULL OR product_variants.default_sale_price <= 0 THEN COALESCE(inventory_balances.quantity, 0) ELSE 0 END), 0) as missing_price_units,
            COALESCE(COUNT(CASE WHEN COALESCE(inventory_balances.quantity, 0) <= product_variants.minimum_stock AND product_variants.minimum_stock > 0 THEN 1 END), 0) as low_stock_count,
            COALESCE(COUNT(CASE WHEN COALESCE(inventory_balances.quantity, 0) <= 0 THEN 1 END), 0) as out_of_stock_count
        ')->first();

        $matchingVariantIds = (clone $query)->reorder()->pluck('product_variants.id')->toArray();
        $totalAllocatedUnits = 0;
        $staleAllocationsCount = 0;

        if (! empty($matchingVariantIds)) {
            $totalAllocatedUnits = (int) InventoryAllocation::whereIn('product_variant_id', $matchingVariantIds)
                ->where('warehouse_id', $actualWarehouseId)
                ->where('status', 'ACTIVE')
                ->sum(DB::raw('allocated_quantity - consumed_quantity - returned_quantity'));

            // Eskirgan ajratmalar (lease muddati o'tgan yoki 24 soatdan beri sinxron bo'lmagan qurilma)
            $staleAllocationsCount = InventoryAllocation::whereIn('product_variant_id', $matchingVariantIds)
                ->where('warehouse_id', $actualWarehouseId)
                ->where('status', 'ACTIVE')
                ->whereRaw('(allocated_quantity - consumed_quantity - returned_quantity) > 0')
                ->whereHas('device', function ($dq) {
                    $dq->where('last_sync_at', '<', now()->subHours(24))
                        ->orWhere('last_seen_at', '<', now()->subHours(24))
                        ->orWhereDoesntHave('offlineAuthorizations', function ($aq) {
                            $aq->where('expires_at', '>', now())->where('is_revoked', false);
                        });
                })
                ->count();
        }

        $totalPhysicalUnits = (int) ($stats->total_physical_units ?? 0);
        $totalFreeUnits = max(0, $totalPhysicalUnits - $totalAllocatedUnits);

        return [
            'total_variants_count' => (int) ($stats->total_variants_count ?? 0),
            'total_physical_units' => $totalPhysicalUnits,
            'total_allocated_units' => $totalAllocatedUnits,
            'total_free_units' => $totalFreeUnits,
            'total_cost_value' => $canViewCost ? (int) ($stats->total_cost_value ?? 0) : null,
            'total_sale_value' => (int) ($stats->total_sale_value ?? 0),
            'missing_price_count' => (int) ($stats->missing_price_count ?? 0),
            'missing_price_units' => (int) ($stats->missing_price_units ?? 0),
            'low_stock_count' => (int) ($stats->low_stock_count ?? 0),
            'out_of_stock_count' => (int) ($stats->out_of_stock_count ?? 0),
            'stale_allocations_count' => $staleAllocationsCount,
        ];
    }

    /**
     * Sahifalangan ombor ro'yxatini olish.
     */
    public function getPaginatedStock(
        array $filters = [],
        int $perPage = 25,
        int $page = 1,
        ?int $warehouseId = null,
        bool $canViewCost = true
    ): LengthAwarePaginator {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();
        $query = $this->getFilteredStockQuery($filters, $actualWarehouseId);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($variant) use ($canViewCost) {
            $stock = $variant->balance ? (int) $variant->balance->quantity : 0;
            $wac = $variant->balance ? (int) $variant->balance->average_cost : 0;
            $costValue = $variant->balance ? (int) $variant->balance->total_value : 0;
            $systemPrice = (int) ($variant->default_sale_price ?? 0);

            $allocated = (int) $variant->activeAllocations->sum(function ($alloc) {
                return max(0, (int) $alloc->allocated_quantity - (int) $alloc->consumed_quantity - (int) $alloc->returned_quantity);
            });
            $freeStock = max(0, $stock - $allocated);

            // Eskirgan ajratma bormi
            $hasStaleAllocation = $variant->activeAllocations->contains(function ($alloc) {
                $rem = max(0, (int) $alloc->allocated_quantity - (int) $alloc->consumed_quantity - (int) $alloc->returned_quantity);
                if ($rem <= 0) {
                    return false;
                }
                $dev = $alloc->device;
                if (! $dev) {
                    return true;
                }
                $isOld = ($dev->last_sync_at && $dev->last_sync_at->lt(now()->subHours(24)))
                    || ($dev->last_seen_at && $dev->last_seen_at->lt(now()->subHours(24)));

                return $isOld || ! $dev->isActive();
            });

            $hasPrice = ($systemPrice > 0);
            $threshold = (int) $variant->minimum_stock;
            $isLowStock = ($threshold > 0 && $stock <= $threshold);
            $isOutOfStock = ($stock <= 0);

            $variant->physical_quantity = $stock;
            $variant->allocated_quantity = $allocated;
            $variant->free_quantity = $freeStock;
            $variant->has_stale_allocation = $hasStaleAllocation;
            $variant->has_price = $hasPrice;
            $variant->is_low_stock = $isLowStock;
            $variant->is_out_of_stock = $isOutOfStock;
            $variant->wac_cost = $canViewCost ? $wac : null;
            $variant->total_cost_value = $canViewCost ? $costValue : null;
            $variant->total_sale_value = $hasPrice ? ($stock * $systemPrice) : 0;

            return $variant;
        });

        return $paginator;
    }

    /**
     * Variant kartasi va qurilmalar bo'yicha ajratmalar tafsiloti.
     */
    public function getVariantDetail(int $variantId, ?int $warehouseId = null, bool $canViewCost = true): ?array
    {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $variant = ProductVariant::with([
            'product',
            'volume',
            'packages',
            'balance' => fn ($q) => $q->where('warehouse_id', $actualWarehouseId),
            'activeAllocations.device.offlineAuthorizations',
        ])->find($variantId);

        if (! $variant) {
            return null;
        }

        $stock = $variant->balance ? (int) $variant->balance->quantity : 0;
        $wac = $variant->balance ? (int) $variant->balance->average_cost : 0;
        $costValue = $variant->balance ? (int) $variant->balance->total_value : 0;
        $systemPrice = (int) ($variant->default_sale_price ?? 0);

        $allocationsDetail = [];
        $totalAllocated = 0;

        foreach ($variant->activeAllocations as $alloc) {
            $rem = max(0, (int) $alloc->allocated_quantity - (int) $alloc->consumed_quantity - (int) $alloc->returned_quantity);
            $totalAllocated += $rem;

            $dev = $alloc->device;
            $isStale = false;
            $leaseExpiresAt = null;

            if ($dev) {
                $activeAuth = $dev->offlineAuthorizations
                    ->where('is_revoked', false)
                    ->sortByDesc('expires_at')
                    ->first();

                if ($activeAuth) {
                    $leaseExpiresAt = $activeAuth->expires_at;
                    if ($activeAuth->expires_at->isPast()) {
                        $isStale = true;
                    }
                } else {
                    $isStale = true;
                }

                if ($dev->last_sync_at && $dev->last_sync_at->lt(now()->subHours(24))) {
                    $isStale = true;
                }
            }

            $allocationsDetail[] = [
                'allocation_id' => $alloc->id,
                'device_code' => $dev?->device_code ?? 'DEV-UNKNOWN',
                'device_name' => $dev?->name ?? 'Noma\'lum qurilma',
                'device_type' => $dev?->device_type ?? 'UNKNOWN',
                'allocated_quantity' => (int) $alloc->allocated_quantity,
                'consumed_quantity' => (int) $alloc->consumed_quantity,
                'returned_quantity' => (int) $alloc->returned_quantity,
                'remaining_quantity' => $rem,
                'is_stale' => $isStale,
                'last_sync_at' => $dev?->last_sync_at?->setTimezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                'lease_expires_at' => $leaseExpiresAt?->setTimezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
            ];
        }

        $freeStock = max(0, $stock - $totalAllocated);

        // Oxirgi kirim va sotuv vaqtlari
        $lastInward = InventoryMovement::where('product_variant_id', $variantId)
            ->whereIn('movement_type', ['PURCHASE', 'INWARD', 'INITIAL_STOCK', 'OPENING_BALANCE'])
            ->latest('created_at')
            ->value('created_at');

        $lastSale = InventoryMovement::where('product_variant_id', $variantId)
            ->whereIn('movement_type', ['SALE', 'OUTWARD'])
            ->latest('created_at')
            ->value('created_at');

        return [
            'variant_id' => $variant->id,
            'product_name' => $variant->product?->name ?? 'Noma\'lum',
            'product_code' => $variant->product?->code,
            'volume_name' => $variant->volume?->name ?? (string) (($variant->volume?->value_ml ?? 0) / 1000).'L',
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'status' => $variant->status,
            'minimum_stock' => (int) $variant->minimum_stock,
            'physical_quantity' => $stock,
            'allocated_quantity' => $totalAllocated,
            'free_quantity' => $freeStock,
            'has_stale_allocation' => collect($allocationsDetail)->contains('is_stale', true),
            'wac_cost' => $canViewCost ? $wac : null,
            'total_cost_value' => $canViewCost ? $costValue : null,
            'default_sale_price' => $systemPrice,
            'has_price' => ($systemPrice > 0),
            'last_inward_at' => $lastInward ? Carbon::parse($lastInward)->setTimezone('Asia/Tashkent')->format('Y-m-d H:i:s') : null,
            'last_sale_at' => $lastSale ? Carbon::parse($lastSale)->setTimezone('Asia/Tashkent')->format('Y-m-d H:i:s') : null,
            'allocations' => $allocationsDetail,
        ];
    }

    /**
     * Source hujjatga bog'langan harakat tarixi (Asia/Tashkent sekund aniqligida).
     */
    public function getVariantMovements(
        int $variantId,
        int $perPage = 25,
        int $page = 1,
        ?int $warehouseId = null,
        bool $canViewCost = true
    ): LengthAwarePaginator {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $query = InventoryMovement::where('product_variant_id', $variantId)
            ->where('warehouse_id', $actualWarehouseId)
            ->with(['creator', 'warehouse'])
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($movement) use ($canViewCost) {
            // Source document reference mapping
            $referenceTitle = 'Noma\'lum hujjat';
            $referenceNumber = null;

            if ($movement->reference_type === 'Purchase' || $movement->reference_type === 'App\Models\Purchase') {
                $purchase = Purchase::with('supplier')->find($movement->reference_id);
                if ($purchase) {
                    $refNum = $purchase->supplier_invoice_number ?: "NK-{$purchase->id}";
                    $supName = $purchase->supplier?->name ?? 'Ta\'minotchi';
                    $referenceTitle = "Kirim hujjati #{$refNum} ({$supName})";
                    $referenceNumber = $refNum;
                } else {
                    $referenceTitle = "Kirim hujjati #{$movement->reference_id}";
                }
            } elseif ($movement->reference_type === 'Sale' || $movement->reference_type === 'App\Models\Sale') {
                $sale = Sale::with('customer')->find($movement->reference_id);
                if ($sale) {
                    $receiptNum = $sale->receipt_data['receipt_number'] ?? "#{$sale->id}";
                    $custName = $sale->customer?->name ?? 'Guest';
                    $referenceTitle = "Sotuv cheki {$receiptNum} ({$custName})";
                    $referenceNumber = $receiptNum;
                } else {
                    $referenceTitle = "Sotuv hujjati #{$movement->reference_id}";
                }
            } elseif (in_array($movement->movement_type, ['INITIAL_STOCK', 'OPENING_BALANCE'], true)) {
                $referenceTitle = 'Boshlang\'ich qoldiq hujjati';
            } elseif (in_array($movement->movement_type, ['ADJUSTMENT_IN', 'ADJUSTMENT_OUT'], true)) {
                $referenceTitle = 'Inventarizatsiya / Ombor tuzatish';
            } elseif ($movement->movement_type === 'SALE_RETURN') {
                $referenceTitle = 'Mijozdan qaytgan tovar';
            } elseif ($movement->movement_type === 'PURCHASE_RETURN') {
                $referenceTitle = 'Ta\'minotchiga qaytarilgan tovar';
            } elseif (in_array($movement->movement_type, ['DAMAGE', 'LOSS'], true)) {
                $referenceTitle = 'Brak / Yaroqsiz tovar chiqimi';
            }

            // O'zbekcha harakat nomi
            $typeLabels = [
                'PURCHASE' => 'Kirim',
                'INWARD' => 'Kirim',
                'SALE' => 'Sotuv',
                'OUTWARD' => 'Sotuv',
                'SALE_RETURN' => 'Sotuv qaytarildi',
                'PURCHASE_RETURN' => 'Ta\'minotchiga qaytarildi',
                'DAMAGE' => 'Brak / Yaroqsiz',
                'LOSS' => 'Yo\'qotish',
                'ADJUSTMENT_IN' => 'Tuzatish (Kirim)',
                'ADJUSTMENT_OUT' => 'Tuzatish (Chiqim)',
                'INITIAL_STOCK' => 'Boshlang\'ich qoldiq',
                'OPENING_BALANCE' => 'Boshlang\'ich qoldiq',
            ];

            $qty = (int) $movement->quantity;
            $isPositive = in_array($movement->movement_type, ['PURCHASE', 'INWARD', 'SALE_RETURN', 'ADJUSTMENT_IN', 'INITIAL_STOCK', 'OPENING_BALANCE'], true);

            return [
                'id' => $movement->id,
                'operation_id' => $movement->operation_id,
                'movement_type' => $movement->movement_type,
                'type_label' => $typeLabels[$movement->movement_type] ?? $movement->movement_type,
                'is_positive' => $isPositive,
                'quantity' => $qty,
                'unit_cost' => $canViewCost ? (int) $movement->unit_cost : null,
                'total_cost' => $canViewCost ? (int) $movement->total_cost : null,
                'balance_after_quantity' => (int) $movement->balance_after_quantity,
                'balance_after_value' => $canViewCost ? (int) $movement->balance_after_value : null,
                'reference_type' => $movement->reference_type,
                'reference_id' => $movement->reference_id,
                'reference_title' => $referenceTitle,
                'reference_number' => $referenceNumber,
                'creator_name' => $movement->creator?->name ?? 'Tizim',
                'created_at' => $movement->created_at
                    ? Carbon::parse($movement->created_at)->setTimezone('Asia/Tashkent')->format('Y-m-d H:i:s')
                    : null,
            ];
        });

        return $paginator;
    }

    /**
     * Kam qoldiq signalini tekshirish va hodisa chiqarish.
     *
     * @return array<ProductVariant>
     */
    public function checkLowStockAlerts(?int $warehouseId = null): array
    {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $lowStockVariants = ProductVariant::where('status', 'active')
            ->where('minimum_stock', '>', 0)
            ->whereHas('balance', function ($bq) use ($actualWarehouseId) {
                $bq->where('warehouse_id', $actualWarehouseId)
                    ->whereRaw('quantity <= product_variants.minimum_stock');
            })
            ->with(['balance', 'product', 'volume'])
            ->get();

        foreach ($lowStockVariants as $variant) {
            $qty = (int) ($variant->balance?->quantity ?? 0);
            event(new LowStockDetected(
                variant: $variant,
                currentQuantity: $qty,
                threshold: (int) $variant->minimum_stock,
                warehouseId: $actualWarehouseId
            ));
        }

        return $lowStockVariants->all();
    }
}
