<?php

namespace App\Services\Ledger;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\InventoryAllocationMovement;
use App\Models\InventoryBalance;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Ledger\Exceptions\InsufficientAllocationException;
use App\Services\Ledger\Exceptions\InsufficientFreeStockException;
use App\Services\Ledger\Exceptions\ReservedStockProtectionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryAllocationService
{
    /**
     * Qurilmaga tovar sotish huquqini ajratish (Grant Stock Allocation).
     *
     * @param  int  $quantity  Butun musbat dona
     */
    public function grantAllocation(
        Device $device,
        int $variantId,
        int $quantity,
        ?int $warehouseId = null,
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null
    ): InventoryAllocation {
        $operationId = $operationId ?: (string) Str::uuid();
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        if ($quantity <= 0) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Ajratiladigan tovar miqdori 0 dan katta butun dona bo'lishi shart!",
                errorCode: 'INVALID_QUANTITY'
            );
        }

        if (! $device->isActive()) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Qurilma (#{$device->device_code}) faol emas! Unga tovar ajratib bo'lmaydi.",
                errorCode: 'DEVICE_NOT_ACTIVE'
            );
        }

        return DB::transaction(function () use (
            $device,
            $variantId,
            $quantity,
            $actualWarehouseId,
            $operationId,
            $userId,
            $notes
        ) {
            // Idempotency: Agar ushbu operation_id bilan harakat allaqachon bajarilgan bo'lsa
            $existingMovement = InventoryAllocationMovement::where('operation_id', $operationId)->first();
            if ($existingMovement) {
                return $existingMovement->allocation;
            }

            // 1. Fizik ombor qoldig'ini qulflash (lockForUpdate)
            $balance = InventoryBalance::where('product_variant_id', $variantId)
                ->where('warehouse_id', $actualWarehouseId)
                ->lockForUpdate()
                ->first();

            $totalOnHand = $balance ? (int) $balance->quantity : 0;

            // 2. Barcha faol qurilmalardagi jami band qilingan rezervlarni hisoblash
            $sumReserved = (int) InventoryAllocation::where('product_variant_id', $variantId)
                ->where('warehouse_id', $actualWarehouseId)
                ->where('status', 'ACTIVE')
                ->sum(DB::raw('allocated_quantity - consumed_quantity - returned_quantity'));

            $freeStock = max(0, $totalOnHand - $sumReserved);

            // 3. Erkin qoldiq tekshiruvi: ajratmalar yig'indisi fizik stockdan oshishi mumkin emas!
            if ($quantity > $freeStock) {
                $v = ProductVariant::with(['product', 'volume'])->find($variantId);
                $vName = $v ? "{$v->product->name} ({$v->volume->name})" : "#{$variantId}";

                throw new InsufficientFreeStockException(
                    message: "Omborda yetarli erkin qoldiq mavjud emas! '{$vName}' uchun jami ombor qoldig'i: {$totalOnHand} dona, qurilmalarga rezerv qilingan: {$sumReserved} dona, erkin qoldiq: {$freeStock} dona. So'ralgan: {$quantity} dona.",
                    operationId: $operationId,
                    details: [
                        'variant_id' => $variantId,
                        'total_on_hand' => $totalOnHand,
                        'total_reserved' => $sumReserved,
                        'free_stock' => $freeStock,
                        'requested' => $quantity,
                    ]
                );
            }

            // 4. Ushbu qurilma uchun faol allocation yozuvini olish yoki yaratish
            $allocation = InventoryAllocation::where('device_id', $device->id)
                ->where('product_variant_id', $variantId)
                ->where('warehouse_id', $actualWarehouseId)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->first();

            if (! $allocation) {
                $allocation = InventoryAllocation::create([
                    'device_id' => $device->id,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $actualWarehouseId,
                    'allocated_quantity' => $quantity,
                    'consumed_quantity' => 0,
                    'returned_quantity' => 0,
                    'epoch' => $device->current_lease_epoch,
                    'status' => 'ACTIVE',
                    'notes' => $notes,
                ]);
            } else {
                $allocation->increment('allocated_quantity', $quantity);
            }

            // 5. Harakat auditini yozish (GRANT)
            InventoryAllocationMovement::create([
                'inventory_allocation_id' => $allocation->id,
                'device_id' => $device->id,
                'product_variant_id' => $variantId,
                'warehouse_id' => $actualWarehouseId,
                'operation_id' => $operationId,
                'movement_type' => 'GRANT',
                'quantity' => $quantity,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'STOCK_ALLOCATION_GRANTED',
                'auditable_type' => InventoryAllocation::class,
                'auditable_id' => $allocation->id,
                'new_values' => [
                    'device_id' => $device->id,
                    'device_code' => $device->device_code,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'operation_id' => $operationId,
                    'remaining_free_stock' => $freeStock - $quantity,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            return $allocation->fresh();
        });
    }

    /**
     * Qurilma ajratmasidan sarflash (Consume Stock Allocation).
     * "bir operation_id rezervni ikki sarflamaydi" — qat'iy idempotent.
     */
    public function consumeAllocation(
        Device $device,
        int $variantId,
        int $quantity,
        ?int $warehouseId,
        string $operationId,
        ?int $userId = null,
        ?string $notes = null
    ): InventoryAllocation {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        if ($quantity <= 0) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Sarflanadigan tovar miqdori 0 dan katta butun dona bo'lishi shart!",
                errorCode: 'INVALID_QUANTITY'
            );
        }

        return DB::transaction(function () use (
            $device,
            $variantId,
            $quantity,
            $actualWarehouseId,
            $operationId,
            $userId,
            $notes
        ) {
            // Idempotency: bir operation_id rezervni ikki sarflamaydi
            $existingMovement = InventoryAllocationMovement::where('operation_id', $operationId)->first();
            if ($existingMovement) {
                return $existingMovement->allocation;
            }

            $allocation = InventoryAllocation::where('device_id', $device->id)
                ->where('product_variant_id', $variantId)
                ->where('warehouse_id', $actualWarehouseId)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->first();

            $availableInReservation = $allocation ? $allocation->available_quantity : 0;

            if (! $allocation || $availableInReservation < $quantity) {
                $v = ProductVariant::with(['product', 'volume'])->find($variantId);
                $vName = $v ? "{$v->product->name} ({$v->volume->name})" : "#{$variantId}";

                throw new InsufficientAllocationException(
                    message: "Qurilmada (#{$device->device_code}) '{$vName}' uchun yetarli tovar ajratmasi (rezervi) mavjud emas! Mavjud rezerv: {$availableInReservation} dona, so'ralgan: {$quantity} dona.",
                    operationId: $operationId,
                    details: [
                        'device_id' => $device->id,
                        'variant_id' => $variantId,
                        'available_reservation' => $availableInReservation,
                        'requested' => $quantity,
                    ]
                );
            }

            $allocation->increment('consumed_quantity', $quantity);

            InventoryAllocationMovement::create([
                'inventory_allocation_id' => $allocation->id,
                'device_id' => $device->id,
                'product_variant_id' => $variantId,
                'warehouse_id' => $actualWarehouseId,
                'operation_id' => $operationId,
                'movement_type' => 'CONSUME',
                'quantity' => $quantity,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            return $allocation->fresh();
        });
    }

    /**
     * Qurilma ajratmasini omborga qaytarish (Return Stock Allocation).
     */
    public function returnAllocation(
        Device $device,
        int $variantId,
        int $quantity,
        ?int $warehouseId = null,
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null
    ): InventoryAllocation {
        $operationId = $operationId ?: (string) Str::uuid();
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        if ($quantity <= 0) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Qaytariladigan tovar miqdori 0 dan katta butun dona bo'lishi shart!",
                errorCode: 'INVALID_QUANTITY'
            );
        }

        return DB::transaction(function () use (
            $device,
            $variantId,
            $quantity,
            $actualWarehouseId,
            $operationId,
            $userId,
            $notes
        ) {
            $existingMovement = InventoryAllocationMovement::where('operation_id', $operationId)->first();
            if ($existingMovement) {
                return $existingMovement->allocation;
            }

            $allocation = InventoryAllocation::where('device_id', $device->id)
                ->where('product_variant_id', $variantId)
                ->where('warehouse_id', $actualWarehouseId)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->first();

            $availableInReservation = $allocation ? $allocation->available_quantity : 0;

            if (! $allocation || $availableInReservation < $quantity) {
                throw new InsufficientAllocationException(
                    message: "Qurilmada qaytarish uchun yetarli ishlatilmagan ajratma mavjud emas! Qolgan rezerv: {$availableInReservation} dona, qaytarish so'ralgan: {$quantity} dona.",
                    operationId: $operationId
                );
            }

            $allocation->increment('returned_quantity', $quantity);

            InventoryAllocationMovement::create([
                'inventory_allocation_id' => $allocation->id,
                'device_id' => $device->id,
                'product_variant_id' => $variantId,
                'warehouse_id' => $actualWarehouseId,
                'operation_id' => $operationId,
                'movement_type' => 'RETURN',
                'quantity' => $quantity,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'STOCK_ALLOCATION_RETURNED',
                'auditable_type' => InventoryAllocation::class,
                'auditable_id' => $allocation->id,
                'new_values' => [
                    'device_id' => $device->id,
                    'variant_id' => $variantId,
                    'returned_quantity' => $quantity,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            return $allocation->fresh();
        });
    }

    /**
     * Yo'qolgan qurilmani qo'lda muvofiqlashtirish (Manual Reconcile).
     * "Yo‘qolgan device manual reconcile; expiry/offline/reinstall rezervni avtomatik boshqa devicega bermaydi."
     */
    public function manualReconcileLostDevice(Device $device, User $actor, string $reason): void
    {
        DB::transaction(function () use ($device, $actor, $reason) {
            $allocations = InventoryAllocation::where('device_id', $device->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $remaining = $allocation->available_quantity;

                if ($remaining > 0) {
                    $opId = (string) Str::uuid();

                    $allocation->increment('returned_quantity', $remaining);
                    $allocation->update(['status' => 'RECONCILED']);

                    InventoryAllocationMovement::create([
                        'inventory_allocation_id' => $allocation->id,
                        'device_id' => $device->id,
                        'product_variant_id' => $allocation->product_variant_id,
                        'warehouse_id' => $allocation->warehouse_id,
                        'operation_id' => $opId,
                        'movement_type' => 'RECONCILE',
                        'quantity' => $remaining,
                        'user_id' => $actor->id,
                        'notes' => "Qo'lda muvofiqlashtirildi (Yo'qolgan qurilma). Sabab: {$reason}",
                    ]);
                } else {
                    $allocation->update(['status' => 'CLOSED']);
                }
            }

            $device->update([
                'status' => 'RECONCILED',
                'notes' => trim($device->notes."\n[RECONCILED by {$actor->name}: {$reason}]"),
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'DEVICE_MANUALLY_RECONCILED',
                'auditable_type' => Device::class,
                'auditable_id' => $device->id,
                'new_values' => [
                    'device_id' => $device->id,
                    'device_code' => $device->device_code,
                    'reason' => $reason,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Ombor tovar chiqimi (brak, ta'minotchiga qaytarish, count) rezervlarni buzmasligini tekshirish.
     * "Stock kamaytiradigan keyingi return/brak/count ham rezerv kontraktiga majburiy ulanadi."
     */
    public function validateStockReductionAllowed(int $variantId, int $reductionQuantity, ?int $warehouseId = null): void
    {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $balance = InventoryBalance::where('product_variant_id', $variantId)
            ->where('warehouse_id', $actualWarehouseId)
            ->first();

        $totalOnHand = $balance ? (int) $balance->quantity : 0;

        $sumReserved = (int) InventoryAllocation::where('product_variant_id', $variantId)
            ->where('warehouse_id', $actualWarehouseId)
            ->where('status', 'ACTIVE')
            ->sum(DB::raw('allocated_quantity - consumed_quantity - returned_quantity'));

        $postReductionOnHand = $totalOnHand - $reductionQuantity;

        if ($postReductionOnHand < $sumReserved) {
            $v = ProductVariant::with(['product', 'volume'])->find($variantId);
            $vName = $v ? "{$v->product->name} ({$v->volume->name})" : "#{$variantId}";

            throw new ReservedStockProtectionException(
                message: "Ombordan {$reductionQuantity} dona '{$vName}' chiqarib bo'lmaydi! Chiqimdan so'ng ombor qoldig'i ({$postReductionOnHand} dona) faol qurilmalarga ajratilgan rezervlar miqdoridan ({$sumReserved} dona) kam bo'lib qoladi. Avval qurilmalardan tovar rezervini qaytaring.",
                details: [
                    'variant_id' => $variantId,
                    'total_on_hand' => $totalOnHand,
                    'reduction_quantity' => $reductionQuantity,
                    'post_reduction_on_hand' => $postReductionOnHand,
                    'total_reserved' => $sumReserved,
                ]
            );
        }
    }

    /**
     * Tovar varianti bo'yicha fizik, ajratilgan va erkin qoldiq hisoboti.
     */
    public function getAvailableStockBreakdown(int $variantId, ?int $warehouseId = null): array
    {
        $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

        $balance = InventoryBalance::where('product_variant_id', $variantId)
            ->where('warehouse_id', $actualWarehouseId)
            ->first();

        $totalOnHand = $balance ? (int) $balance->quantity : 0;

        $allocations = InventoryAllocation::with('device')
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $actualWarehouseId)
            ->where('status', 'ACTIVE')
            ->get();

        $totalReserved = 0;
        $deviceBreakdown = [];

        foreach ($allocations as $alloc) {
            $avail = $alloc->available_quantity;
            $totalReserved += $avail;
            $deviceBreakdown[] = [
                'device_id' => $alloc->device_id,
                'device_code' => $alloc->device->device_code ?? '#'.$alloc->device_id,
                'device_name' => $alloc->device->name ?? 'Noma\'lum',
                'allocated' => (int) $alloc->allocated_quantity,
                'consumed' => (int) $alloc->consumed_quantity,
                'returned' => (int) $alloc->returned_quantity,
                'available' => $avail,
            ];
        }

        $freeStock = max(0, $totalOnHand - $totalReserved);

        return [
            'variant_id' => $variantId,
            'warehouse_id' => $actualWarehouseId,
            'physical_on_hand' => $totalOnHand,
            'total_reserved' => $totalReserved,
            'free_stock' => $freeStock,
            'device_allocations' => $deviceBreakdown,
        ];
    }

    protected function getDefaultWarehouseId(): int
    {
        return Warehouse::where('is_default', true)->value('id')
            ?? Warehouse::firstOrCreate(['name' => 'Asosiy Ombor'], ['is_default' => true])->id;
    }
}
