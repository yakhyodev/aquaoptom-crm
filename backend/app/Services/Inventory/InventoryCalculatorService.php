<?php

namespace App\Services\Inventory;

use App\Models\PriceHistory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Operations\Exceptions\OperationPermissionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryCalculatorService
{
    /**
     * Tanlangan variantlar bo'yicha interaktiv kalkulyatsiya.
     *
     * Qabul mezonlari:
     * - Fanta barcha hajm vs 1L vs ikki hajm summalari haqiqiy rowsdan hisoblanadi.
     * - Narxsiz variantlar nol narxga tenglashtirilmaydi (missing_price_count va missing_price_units ajratiladi).
     * - Potensial foyda haqiqiy foyda yoki cash deb nomlanmaydi (expected_gross_profit deb yuritiladi).
     * - Tannarx/qiymat rol bo'yicha (canViewCost = false bo'lsa yashiriladi).
     *
     * @param  array<int>  $selectedVariantIds  Tanlangan variant ID lari
     * @param  array<int, int>  $simulationPrices  Vaqtinchalik simulyatsiya narxlari [variant_id => price]
     * @param  int|null  $warehouseId  Ombor ID (default: null)
     * @param  bool  $canViewCost  Foydalanuvchida 'view_cost_price' huquqi bormi
     */
    public function calculate(
        array $selectedVariantIds,
        array $simulationPrices = [],
        ?int $warehouseId = null,
        bool $canViewCost = true
    ): array {
        if (empty($selectedVariantIds)) {
            return [
                'total_cost_value' => $canViewCost ? 0 : null,
                'priced_cost_value' => $canViewCost ? 0 : null,
                'total_potential_sale_value' => 0,
                'potential_gross_profit' => $canViewCost ? 0 : null,
                'expected_gross_profit' => $canViewCost ? 0 : null,
                'potential_margin_percent' => $canViewCost ? 0.0 : null,
                'total_quantity_units' => 0,
                'total_free_units' => 0,
                'total_allocated_units' => 0,
                'selected_count' => 0,
                'missing_price_count' => 0,
                'missing_price_units' => 0,
                'missing_price_cost_value' => $canViewCost ? 0 : null,
                'is_fully_priced' => true,
                'items' => [],
            ];
        }

        $variants = ProductVariant::with(['product', 'volume', 'packages', 'balance', 'inventoryAllocations'])
            ->whereIn('id', $selectedVariantIds)
            ->get();

        $totalCostValue = 0;
        $pricedCostValue = 0;
        $pricedSaleValue = 0;
        $totalPhysicalUnits = 0;
        $totalAllocatedUnits = 0;
        $totalFreeUnits = 0;
        $missingPriceCount = 0;
        $missingPriceUnits = 0;
        $missingPriceCostValue = 0;
        $items = [];

        foreach ($variants as $variant) {
            $stock = $variant->balance ? (int) $variant->balance->quantity : 0;
            $wac = $variant->balance ? (int) $variant->balance->average_cost : 0;
            $systemSalePrice = (int) ($variant->default_sale_price ?? 0);

            // Ajratilgan va erkin qoldiq hisobi
            $allocated = (int) $variant->inventoryAllocations
                ->where('status', 'ACTIVE')
                ->sum(function ($alloc) {
                    return max(0, (int) $alloc->allocated_quantity - (int) $alloc->consumed_quantity - (int) $alloc->returned_quantity);
                });
            $freeStock = max(0, $stock - $allocated);

            // Simulyatsiya narxi mavjudligini tekshirish
            $hasSimulationPrice = isset($simulationPrices[$variant->id])
                && is_numeric($simulationPrices[$variant->id])
                && (int) $simulationPrices[$variant->id] > 0;

            $effectiveSalePrice = $hasSimulationPrice
                ? (int) $simulationPrices[$variant->id]
                : $systemSalePrice;

            $hasPrice = ($effectiveSalePrice > 0);
            $costValue = (int) round($stock * $wac);

            if (! $hasPrice) {
                // Narxi yo'q variant nol narxga tenglashtirilmaydi!
                $missingPriceCount++;
                $missingPriceUnits += $stock;
                $missingPriceCostValue += $costValue;
                $potentialSale = null;
                $potentialProfit = null;
            } else {
                $potentialSale = (int) round($stock * $effectiveSalePrice);
                $potentialProfit = $potentialSale - $costValue;

                $pricedSaleValue += $potentialSale;
                $pricedCostValue += $costValue;
            }

            $totalCostValue += $costValue;
            $totalPhysicalUnits += $stock;
            $totalAllocatedUnits += $allocated;
            $totalFreeUnits += $freeStock;

            // Qadoq ekvivalenti (yashik = 12 dona)
            $yashikPackage = $variant->packages->firstWhere('name', 'yashik');
            $unitsPerYashik = $yashikPackage ? $yashikPackage->units_per_package : 12;
            $yashikCount = $unitsPerYashik > 0 ? round($stock / $unitsPerYashik, 1) : 0;

            $items[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product?->name ?? 'Noma\'lum',
                'volume_name' => $variant->volume?->name ?? (string) (($variant->volume?->value_ml ?? 0) / 1000).'L',
                'sku' => $variant->sku,
                'stock_units' => $stock,
                'free_units' => $freeStock,
                'allocated_units' => $allocated,
                'stock_yashik' => $yashikCount,
                'wac_cost' => $canViewCost ? $wac : null,
                'system_sale_price' => $systemSalePrice,
                'simulation_price' => $hasSimulationPrice ? (int) $simulationPrices[$variant->id] : null,
                'effective_sale_price' => $effectiveSalePrice,
                'has_price' => $hasPrice,
                'cost_value' => $canViewCost ? $costValue : null,
                'potential_sale' => $potentialSale,
                'potential_profit' => $canViewCost ? $potentialProfit : null,
            ];
        }

        $isFullyPriced = ($missingPriceCount === 0);
        $expectedGrossProfit = $canViewCost ? ($pricedSaleValue - $pricedCostValue) : null;
        $marginPercent = ($canViewCost && $pricedSaleValue > 0)
            ? round((($pricedSaleValue - $pricedCostValue) / $pricedSaleValue) * 100, 1)
            : ($canViewCost ? 0.0 : null);

        return [
            'total_cost_value' => $canViewCost ? $totalCostValue : null,
            'priced_cost_value' => $canViewCost ? $pricedCostValue : null,
            'total_potential_sale_value' => $pricedSaleValue,
            'potential_gross_profit' => $expectedGrossProfit, // Legacy backward compatibility key
            'expected_gross_profit' => $expectedGrossProfit,   // Standard V2 key
            'potential_margin_percent' => $marginPercent,
            'total_quantity_units' => $totalPhysicalUnits,
            'total_free_units' => $totalFreeUnits,
            'total_allocated_units' => $totalAllocatedUnits,
            'selected_count' => count($selectedVariantIds),
            'missing_price_count' => $missingPriceCount,
            'missing_price_units' => $missingPriceUnits,
            'missing_price_cost_value' => $canViewCost ? $missingPriceCostValue : null,
            'is_fully_priced' => $isFullyPriced,
            'items' => $items,
        ];
    }

    /**
     * Taxminiy narxlarni katalogdagi tizim narxiga saqlash (manage_prices ruxsati talab qilinadi).
     *
     * @param  array<int, int>  $simulationPrices  [variant_id => new_price]
     * @return int Yangilangan variantlar soni
     *
     * @throws OperationPermissionException
     */
    public function applySimulationPricesToCatalog(
        array $simulationPrices,
        User $user,
        ?string $reason = null
    ): int {
        if (! $user->hasPermission('manage_prices')) {
            throw new OperationPermissionException(
                operationId: (string) Str::uuid(),
                message: "Sotuv narxlarini katalogga saqlash uchun sizda 'manage_prices' ruxsati bo'lishi shart!"
            );
        }

        $validPrices = array_filter($simulationPrices, fn ($p) => is_numeric($p) && (int) $p > 0);
        if (empty($validPrices)) {
            return 0;
        }

        return DB::transaction(function () use ($validPrices, $user, $reason) {
            $updatedCount = 0;
            $variants = ProductVariant::whereIn('id', array_keys($validPrices))->lockForUpdate()->get();

            foreach ($variants as $variant) {
                $newPrice = (int) $validPrices[$variant->id];
                $oldPrice = (int) ($variant->default_sale_price ?? 0);

                if ($newPrice !== $oldPrice) {
                    $variant->default_sale_price = $newPrice;
                    $variant->version = ($variant->version ?? 0) + 1;
                    $variant->save();

                    PriceHistory::create([
                        'product_variant_id' => $variant->id,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'version' => $variant->version,
                        'changed_by' => $user->id,
                        'changed_at' => now(),
                        'reason' => $reason ?: 'Ombor kalkulyatoridan tizim narxi saqlandi',
                    ]);

                    $updatedCount++;
                }
            }

            return $updatedCount;
        });
    }
}
