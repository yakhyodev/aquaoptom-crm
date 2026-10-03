<?php

namespace App\Services\Ledger;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Services\Ledger\Exceptions\InsufficientStockException;
use Carbon\Carbon;

class InventoryLedgerService
{
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
        ?int $userId = null
    ): array {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Kirim miqdori 0 dan katta butun son bo\'lishi shart.');
        }

        if ($unitCost < 0) {
            throw new \InvalidArgumentException('Tannarx manfiy bo\'lishi mumkin emas.');
        }

        $warehouseId = $warehouseId ?: $this->getDefaultWarehouseId();
        $inflowTotalValue = $quantity * $unitCost;

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

            $newQuantity = $oldQuantity + $quantity;
            $newTotalValue = $oldTotalValue + $inflowTotalValue;
            $newWac = $newQuantity > 0 ? (int) round($newTotalValue / $newQuantity) : 0;

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
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Chiqim miqdori 0 dan katta butun son bo\'lishi shart.');
        }

        $warehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $balance = InventoryBalance::where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $currentStock = $balance ? (int) $balance->quantity : 0;
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
            $outflowCost = (int) round($quantity * $currentWac);
            if ($outflowCost > $oldTotalValue) {
                $outflowCost = $oldTotalValue;
            }
            $remainingValue = $oldTotalValue - $outflowCost;
            $newWac = $remainingQuantity > 0 ? (int) round($remainingValue / $remainingQuantity) : 0;
        }

        $balance->quantity = $remainingQuantity;
        $balance->total_value = $remainingValue;
        $balance->average_cost = $newWac;
        $balance->updated_at = Carbon::now();
        $balance->save();

        // Chiqim uchun 1 dona tannarxi (hisoblash oson bo'lishi uchun)
        $actualUnitCost = $quantity > 0 ? (int) round($outflowCost / $quantity) : $currentWac;

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
    protected function getDefaultWarehouseId(): int
    {
        $warehouse = Warehouse::where('is_default', true)->first();
        if (! $warehouse) {
            $warehouse = Warehouse::firstOrCreate(['name' => 'Asosiy Ombor'], ['is_default' => true]);
        }

        return $warehouse->id;
    }
}
