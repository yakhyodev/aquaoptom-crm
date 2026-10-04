<?php

namespace App\Services\Ledger;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Ledger\Exceptions\InsufficientStockException;
use Carbon\Carbon;

class InventoryLedgerService
{
    public const MAX_QUANTITY = 2147483647;

    /**
     * Omborga tovar kirimini qayd etish va WAC (Weighted Average Cost) ni hisoblash.
     *
     * Invariant: 100 x 5000 + 100 x 6000 = 200 dona / 1 100 000 qiymat / WAC 5500
     */
    public function recordInflow(
        int $productVariantId,
        int $quantity,
        int $unitCost,
        string $movementType = 'PURCHASE',
        ?int $warehouseId = null,
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?int $totalCost = null
    ): array {
        if ($quantity <= 0 || $quantity > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('Kirim miqdori 0 dan katta butun son bo\'lishi shart.');
        }

        if ($unitCost < 0) {
            throw new \InvalidArgumentException('Tannarx manfiy bo\'lishi mumkin emas.');
        }

        if ($unitCost > intdiv(PHP_INT_MAX, $quantity)) {
            throw new \InvalidArgumentException('Kirim qiymati ruxsat etilgan chegaradan oshdi.');
        }

        $warehouseId = $warehouseId ?: $this->getDefaultWarehouseId();
        $inflowTotalValue = $totalCost ?? $quantity * $unitCost;
        if ($inflowTotalValue < 0) {
            throw new \InvalidArgumentException('Jami tannarx manfiy bo‘lishi mumkin emas.');
        }

        // A missing balance row cannot be locked; serialize its initial creation.
        ProductVariant::whereKey($productVariantId)->lockForUpdate()->firstOrFail();

        // InventoryBalance qatorini lockForUpdate bilan bloklash
        $balance = InventoryBalance::where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $newQuantity = $quantity;
            $newTotalValue = $inflowTotalValue;
            $newWac = $unitCost;

            $balance = InventoryBalance::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'quantity' => $newQuantity,
                'total_value' => $newTotalValue,
                'average_cost' => $newWac,
                'updated_at' => Carbon::now(),
            ]);
        } else {
            $oldQuantity = (int) $balance->quantity;
            $oldTotalValue = (int) $balance->total_value;

            if ($oldQuantity < 0 || $oldQuantity > self::MAX_QUANTITY - $quantity || $oldTotalValue < 0 || $oldTotalValue > PHP_INT_MAX - $inflowTotalValue) {
                throw new \InvalidArgumentException('Ombor qoldig‘i ruxsat etilgan chegaradan oshdi.');
            }

            $newQuantity = $oldQuantity + $quantity;
            $newTotalValue = $oldTotalValue + $inflowTotalValue;
            $newWac = $this->roundRatio($newTotalValue, $newQuantity);

            $balance->quantity = $newQuantity;
            $balance->total_value = $newTotalValue;
            $balance->average_cost = $newWac;
            $balance->updated_at = Carbon::now();
            $balance->save();
        }

        // Ledger yozuvi (Source of truth)
        $movement = InventoryMovement::create([
            'operation_id' => $operationId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $inflowTotalValue,
            'balance_after_quantity' => $newQuantity,
            'balance_after_value' => $newTotalValue,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);

        return [
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $inflowTotalValue,
            'balance_quantity' => $newQuantity,
            'balance_total_value' => $newTotalValue,
            'average_cost' => $newWac,
            'movement_id' => $movement->id,
        ];
    }

    /**
     * Ombordan tovar chiqimini qayd etish.
     *
     * Qat'iy Invariant: "Oxirgi dona sotilganda qolgan tannarx to‘liq chiqariladi, qoldiq miqdor va qiymat 0 bo‘ladi."
     */
    public function recordOutflow(
        int $productVariantId,
        int $quantity,
        string $movementType = 'SALE',
        ?int $warehouseId = null,
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): array {
        if ($quantity <= 0 || $quantity > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('Chiqim miqdori 0 dan katta butun son bo\'lishi shart.');
        }

        $warehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $balance = InventoryBalance::where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $currentStock = $balance ? (int) $balance->quantity : 0;
        if ($currentStock > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('Ombor miqdori ruxsat etilgan chegaradan oshdi.');
        }
        if ($currentStock < $quantity) {
            throw new InsufficientStockException(
                "Omborda yetarli qoldiq mavjud emas! So'ralgan: {$quantity} dona, mavjud: {$currentStock} dona."
            );
        }

        $oldTotalValue = (int) $balance->total_value;
        $currentWac = (int) $balance->average_cost;
        $remainingQuantity = $currentStock - $quantity;

        if ($remainingQuantity === 0) {
            // OXIRGI DONA INVARIANTI: qoldiq 0 bo'lganda ombordagi jami qiymat ham qat'iy 0 bo'ladi.
            $outflowCost = $oldTotalValue;
            $remainingValue = 0;
            $newWac = 0;
        } else {
            // Split the product to preserve integer accuracy and avoid bigint overflow.
            $outflowCost = $this->proportionalCost($oldTotalValue, $quantity, $currentStock);
            $remainingValue = $oldTotalValue - $outflowCost;
            $newWac = $this->roundRatio($remainingValue, $remainingQuantity);
        }

        $balance->quantity = $remainingQuantity;
        $balance->total_value = $remainingValue;
        $balance->average_cost = $newWac;
        $balance->updated_at = Carbon::now();
        $balance->save();

        // Chiqim uchun 1 dona tannarxi (hisoblash oson bo'lishi uchun)
        $actualUnitCost = $this->roundRatio($outflowCost, $quantity);

        // Ledger yozuvi
        $movement = InventoryMovement::create([
            'operation_id' => $operationId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $movementType,
            'quantity' => -$quantity, // manfiy chiqim
            'unit_cost' => $actualUnitCost,
            'total_cost' => $outflowCost,
            'balance_after_quantity' => $remainingQuantity,
            'balance_after_value' => $remainingValue,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);

        return [
            'quantity' => $quantity,
            'unit_cost' => $actualUnitCost,
            'total_cost' => $outflowCost,
            'remaining_quantity' => $remainingQuantity,
            'remaining_total_value' => $remainingValue,
            'average_cost' => $newWac,
            'movement_id' => $movement->id,
        ];
    }

    /**
     * Joriy qoldiqni olish (cached InventoryBalance).
     */
    public function getBalance(int $productVariantId, ?int $warehouseId = null): array
    {
        $warehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $balance = InventoryBalance::where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return [
            'quantity' => $balance ? (int) $balance->quantity : 0,
            'total_value' => $balance ? (int) $balance->total_value : 0,
            'average_cost' => $balance ? (int) $balance->average_cost : 0,
        ];
    }

    /**
     * Ledger va cached balance tengligini tekshirish (Audit).
     */
    public function auditBalanceAgainstLedger(int $productVariantId, ?int $warehouseId = null): array
    {
        $warehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $cached = $this->getBalance($productVariantId, $warehouseId);

        $ledgerQuantity = (int) InventoryMovement::where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');

        $isConsistent = ($cached['quantity'] === $ledgerQuantity);

        return [
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'cached_quantity' => $cached['quantity'],
            'ledger_quantity' => $ledgerQuantity,
            'cached_total_value' => $cached['total_value'],
            'average_cost' => $cached['average_cost'],
            'is_consistent' => $isConsistent,
        ];
    }

    /**
     * Standart ombor ID sini olish.
     */
    private function roundRatio(int $numerator, int $denominator): int
    {
        return intdiv($numerator, $denominator)
            + (($numerator % $denominator) >= intdiv($denominator, 2) + ($denominator % 2) ? 1 : 0);
    }

    public function proportionalCost(int $value, int $quantity, int $totalQuantity): int
    {
        if ($value < 0 || $quantity < 1 || $quantity > $totalQuantity || $totalQuantity > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('Tannarx hisoblash miqdori yoki qiymati noto‘g‘ri.');
        }

        return $quantity * intdiv($value, $totalQuantity)
            + $this->roundRatio($quantity * ($value % $totalQuantity), $totalQuantity);
    }

    protected function getDefaultWarehouseId(): int
    {
        $warehouse = Warehouse::where('is_default', true)->first();
        if (! $warehouse) {
            $warehouse = Warehouse::firstOrCreate(['name' => 'Asosiy Ombor'], ['is_default' => true]);
        }

        return $warehouse->id;
    }
}
