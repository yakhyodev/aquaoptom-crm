<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;

class InventoryCalculatorService
{
    /**
     * Tanlangan variantlar bo'yicha professional kalkulyatsiya
     *
     * @param  array  $selectedVariantIds  Tanlangan variant ID lari
     */
    public function calculate(array $selectedVariantIds, ?int $warehouseId = null): array
    {
        if (empty($selectedVariantIds)) {
            return [
                'total_cost_value' => 0,
                'total_potential_sale_value' => 0,
                'potential_gross_profit' => 0,
                'potential_margin_percent' => 0,
                'total_quantity_units' => 0,
                'selected_count' => 0,
                'missing_price_count' => 0,
                'items' => [],
            ];
        }

        $variants = ProductVariant::with(['product', 'volume', 'packages', 'balance'])
            ->whereIn('id', $selectedVariantIds)
            ->get();

        $totalCostValue = 0;
        $totalSaleValue = 0;
        $totalUnits = 0;
        $missingPriceCount = 0;
        $items = [];

        foreach ($variants as $variant) {
            $stock = $variant->balance ? (float) $variant->balance->quantity : 0.0;
            $wac = $variant->balance ? (int) $variant->balance->average_cost : 0;
            $salePrice = (int) $variant->default_sale_price;

            $hasPrice = ($salePrice > 0);
            if (! $hasPrice) {
                $missingPriceCount++;
            }

            $costValue = (int) round($stock * $wac);
            $potentialSale = $hasPrice ? (int) round($stock * $salePrice) : 0;
            $potentialProfit = $hasPrice ? ($potentialSale - $costValue) : 0;

            // Qadoq ekvivalenti (masalan yashik = 12 dona)
            $yashikPackage = $variant->packages->firstWhere('name', 'yashik');
            $unitsPerYashik = $yashikPackage ? $yashikPackage->units_per_package : 12;
            $yashikCount = $unitsPerYashik > 0 ? round($stock / $unitsPerYashik, 1) : 0;

            $totalCostValue += $costValue;
            $totalSaleValue += $potentialSale;
            $totalUnits += $stock;

            $items[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'volume_name' => $variant->volume->name ?? (string) ($variant->volume->value_ml / 1000).'L',
                'sku' => $variant->sku,
                'stock_units' => $stock,
                'stock_yashik' => $yashikCount,
                'wac_cost' => $wac,
                'sale_price' => $salePrice,
                'has_price' => $hasPrice,
                'cost_value' => $costValue,
                'potential_sale' => $potentialSale,
                'potential_profit' => $potentialProfit,
            ];
        }

        $potentialGrossProfit = $totalSaleValue - $totalCostValue;
        $marginPercent = $totalSaleValue > 0 ? round(($potentialGrossProfit / $totalSaleValue) * 100, 1) : 0.0;

        return [
            'total_cost_value' => $totalCostValue,
            'total_potential_sale_value' => $totalSaleValue,
            'potential_gross_profit' => $potentialGrossProfit,
            'potential_margin_percent' => $marginPercent,
            'total_quantity_units' => $totalUnits,
            'selected_count' => count($selectedVariantIds),
            'missing_price_count' => $missingPriceCount,
            'items' => $items,
        ];
    }
}
