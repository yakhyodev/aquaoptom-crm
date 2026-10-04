<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\InventoryBalance;
use App\Models\OutboxEvent;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Ledger\Exceptions\DeviceFreezePendingException;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryAuditService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected InventoryAllocationService $inventoryAllocationService
    ) {}

    /**
     * 1-Qadam: Inventarizatsiyani tayyorlash (Prepare).
     *
     * Invariant:
     * - Tizimdagi kutilayotgan qoldiqlar va WAC tannarxlar snapshot qilinadi.
     * - Faol rezervga ega qurilmalar aniqlanib, ularga freeze so'rovi yuboriladi.
     */
    public function prepareAudit(
        int $warehouseId,
        array $variantIds = [],
        ?string $notes = null,
        ?int $userId = null,
        ?string $operationId = null
    ): InventoryAudit {
        $actor = User::find($userId ?? auth()->id());
        abort_unless($actor && $actor->hasPermission('stock_adjustment'), 403);
        $userId = $actor->id;
        $operationId = $operationId ?: (string) Str::uuid();

        return DB::transaction(function () use (
            $warehouseId,
            $variantIds,
            $notes,
            $userId,
            $operationId
        ) {
            $existing = InventoryAudit::with('items')->where('operation_id', $operationId)->first();
            if ($existing) {
                return $existing;
            }

            $auditNumber = DocumentNumberGenerator::nextAuditNumber();

            // Agar variantlar berilmagan bo'lsa, ombordagi barcha variantlar olinadi
            if (empty($variantIds)) {
                $variantIds = ProductVariant::whereRaw('UPPER(status) = ?', ['ACTIVE'])->pluck('id')->toArray();
            }

            // Faol rezervga ega qurilmalarni tekshirish
            $hasActiveAllocations = InventoryAllocation::whereIn('product_variant_id', $variantIds)
                ->where('warehouse_id', $warehouseId)
                ->where('status', 'ACTIVE')
                ->whereRaw('(allocated_quantity - consumed_quantity - returned_quantity) > 0')
                ->exists();

            $freezeStatus = $hasActiveAllocations ? 'PENDING_ACK' : 'NOT_REQUIRED';

            if ($hasActiveAllocations) {
                // Ushbu variantlar bo'yicha faol rezervi bor qurilmalarga freeze so'rovi
                $deviceIds = InventoryAllocation::whereIn('product_variant_id', $variantIds)
                    ->where('warehouse_id', $warehouseId)
                    ->where('status', 'ACTIVE')
                    ->whereRaw('(allocated_quantity - consumed_quantity - returned_quantity) > 0')
                    ->pluck('device_id')
                    ->unique();

                Device::whereIn('id', $deviceIds)->update([
                    'freeze_requested_at' => Carbon::now(),
                    'freeze_acknowledged_at' => null,
                ]);
            }

            $audit = InventoryAudit::create([
                'operation_id' => $operationId,
                'audit_number' => $auditNumber,
                'warehouse_id' => $warehouseId,
                'status' => 'PREPARED',
                'device_freeze_status' => $freezeStatus,
                'total_expected_qty' => 0,
                'total_counted_qty' => 0,
                'total_discrepancy_qty' => 0,
                'total_discrepancy_value' => 0,
                'notes' => $notes,
                'created_by' => $userId,
                'started_at' => Carbon::now(),
            ]);

            $totalExpected = 0;

            foreach ($variantIds as $variantId) {
                $balance = InventoryBalance::where('product_variant_id', $variantId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $expectedQty = $balance ? (int) $balance->quantity : 0;
                $unitCost = $balance ? (int) $balance->average_cost : 0;

                $totalExpected += $expectedQty;

                InventoryAuditItem::create([
                    'inventory_audit_id' => $audit->id,
                    'product_variant_id' => $variantId,
                    'expected_quantity' => $expectedQty,
                    'counted_quantity' => null,
                    'discrepancy_quantity' => null,
                    'unit_cost' => $unitCost,
                    'discrepancy_value' => null,
                    'status' => 'PENDING',
                ]);
            }

            $audit->update(['total_expected_qty' => $totalExpected]);

            AuditLog::create([
                'operation_id' => $operationId,
                'user_id' => $userId,
                'action' => 'INVENTORY_AUDIT_PREPARED',
                'auditable_type' => InventoryAudit::class,
                'auditable_id' => $audit->id,
                'new_values' => [
                    'audit_number' => $auditNumber,
                    'warehouse_id' => $warehouseId,
                    'freeze_status' => $freezeStatus,
                    'items_count' => count($variantIds),
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            return $audit->load('items.variant.product');
        });
    }

    /**
     * Qurilma tomonidan freeze qabul qilinganini qayd etish (Freeze ACK).
     */
    public function acknowledgeDeviceFreeze(Device $device): void
    {
        $device->update(['freeze_acknowledged_at' => Carbon::now()]);

        // Kutilayotgan auditlarni tekshirish
        $audits = InventoryAudit::whereIn('status', ['PREPARED', 'COUNTING'])
            ->where('device_freeze_status', 'PENDING_ACK')
            ->get();

        foreach ($audits as $audit) {
            $variantIds = $audit->items()->pluck('product_variant_id')->toArray();

            $unacknowledgedDevicesExist = InventoryAllocation::whereIn('product_variant_id', $variantIds)
                ->where('warehouse_id', $audit->warehouse_id)
                ->where('status', 'ACTIVE')
                ->whereRaw('(allocated_quantity - consumed_quantity - returned_quantity) > 0')
                ->whereHas('device', function ($q) {
                    $q->whereNull('freeze_acknowledged_at');
                })
                ->exists();

            if (! $unacknowledgedDevicesExist) {
                $audit->update(['device_freeze_status' => 'ACKNOWLEDGED']);
            }
        }
    }

    /**
     * 2-Qadam: Sanalgan natijalarni kiritish (Record Counts).
     */
    public function recordCounts(InventoryAudit $audit, array $counts, ?string $notes = null): InventoryAudit
    {
        abort_unless(auth()->user()?->hasPermission('stock_adjustment'), 403);
        $seen = [];
        foreach ($counts as $count) {
            $id = $count['product_variant_id'] ?? null;
            $qty = $count['counted_quantity'] ?? null;
            if (! is_numeric($qty) || $qty != (int) $qty || $qty < 0 || isset($seen[$id])) {
                throw new OperationValidationException($audit->operation_id, 'Sanalgan dona butun, manfiy bo‘lmagan va takrorlanmagan bo‘lishi kerak.', errorCode: 'INVALID_COUNT');
            }
            $seen[$id] = true;
        }
        if (in_array($audit->status, ['COMPLETED', 'CANCELLED'], true)) {
            throw new OperationValidationException(
                $audit->operation_id,
                "Yakunlangan yoki bekor qilingan inventarizatsiyaga sanash kiritib bo'lmaydi!",
                [],
                'AUDIT_ALREADY_CLOSED'
            );
        }

        return DB::transaction(function () use ($audit, $counts, $notes) {
            $audit = InventoryAudit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            if (in_array($audit->status, ['COMPLETED', 'CANCELLED'], true)) {
                throw new OperationValidationException($audit->operation_id, 'Inventarizatsiya yopilgan.', errorCode: 'AUDIT_ALREADY_CLOSED');
            }
            $totalCounted = 0;
            $totalDiscrepancyQty = 0;
            $totalDiscrepancyVal = 0;

            foreach ($counts as $countData) {
                $variantId = (int) ($countData['product_variant_id'] ?? 0);
                $countedQty = (int) ($countData['counted_quantity'] ?? 0);
                $reason = $countData['reason'] ?? null;

                $item = $audit->items()->where('product_variant_id', $variantId)->first();
                if (! $item) {
                    continue;
                }

                $expectedQty = (int) $item->expected_quantity;
                $discrepancyQty = $countedQty - $expectedQty;
                $discrepancyVal = (int) ($discrepancyQty * $item->unit_cost);

                $item->update([
                    'counted_quantity' => $countedQty,
                    'discrepancy_quantity' => $discrepancyQty,
                    'discrepancy_value' => $discrepancyVal,
                    'reason' => $reason,
                    'status' => 'COUNTED',
                ]);

                $totalCounted += $countedQty;
                $totalDiscrepancyQty += $discrepancyQty;
                $totalDiscrepancyVal += $discrepancyVal;
            }

            $audit->update([
                'status' => 'COUNTING',
                'total_counted_qty' => $totalCounted,
                'total_discrepancy_qty' => $totalDiscrepancyQty,
                'total_discrepancy_value' => $totalDiscrepancyVal,
                'notes' => $notes ?: $audit->notes,
            ]);

            return $audit->fresh(['items.variant.product']);
        });
    }

    /**
     * 3-Qadam: Inventarizatsiyani tasdiqlash va qoldiqlarni muvofiqlashtirish (Apply & Adjust).
     *
     * Invariantlar:
     * 1. Agar uzilgan qurilma freeze ACK bermagan bo'lsa, DeviceFreezePendingException.
     * 2. Ortiqcha tovar -> ADJUSTMENT_IN (WAC bo'yicha).
     * 3. Kamomad tovar -> ADJUSTMENT_OUT (WAC bo'yicha, rezervlar tekshiriladi).
     * 4. Hujjat holati COMPLETED ga o'tadi va o'chirish taqiqlanadi.
     */
    public function applyAudit(
        InventoryAudit $audit,
        User $actor,
        bool $forceIfFreezePending = false,
        ?string $reason = null
    ): array {
        abort_unless($actor->hasPermission('stock_adjustment'), 403);
        abort_unless(! $forceIfFreezePending || $actor->hasRole('OWNER'), 403);
        if ($audit->status === 'COMPLETED') {
            return [
                'success' => true,
                'is_replay' => true,
                'audit' => $audit,
                'status' => 'COMPLETED',
            ];
        }

        if ($audit->status === 'CANCELLED') {
            throw new OperationValidationException(
                $audit->operation_id,
                "Bekor qilingan inventarizatsiyani tasdiqlab bo'lmaydi!",
                [],
                'AUDIT_CANCELLED'
            );
        }

        // QAT'IY QOIDA: "uzilgan device blokni olmagan bo‘lsa yakuniy tasdiq kutadi"
        if ($audit->device_freeze_status === 'PENDING_ACK' && ! $forceIfFreezePending) {
            $variantIds = $audit->items()->pluck('product_variant_id')->toArray();

            $unacknowledgedDevices = InventoryAllocation::whereIn('product_variant_id', $variantIds)
                ->where('warehouse_id', $audit->warehouse_id)
                ->where('status', 'ACTIVE')
                ->whereRaw('(allocated_quantity - consumed_quantity - returned_quantity) > 0')
                ->whereHas('device', function ($q) {
                    $q->whereNull('freeze_acknowledged_at');
                })
                ->with('device')
                ->get()
                ->pluck('device.device_code')
                ->unique()
                ->values()
                ->toArray();

            if (! empty($unacknowledgedDevices)) {
                $deviceList = implode(', ', $unacknowledgedDevices);
                throw new DeviceFreezePendingException(
                    "Inventarizatsiyani yakunlash uchun quyidagi qurilmalar muzlatish tasdig'ini (freeze ACK) berishi kutilmoqda: [{$deviceList}]. Yakuniy tasdiqni kuting yoki maxsus ruxsat bilan tasdiqlang.",
                    $audit->operation_id,
                    [
                        'audit_id' => $audit->id,
                        'pending_devices' => $unacknowledgedDevices,
                    ]
                );
            }
        }

        return DB::transaction(function () use ($audit, $actor, $forceIfFreezePending, $reason) {
            $audit = InventoryAudit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            if ($audit->status === 'COMPLETED') {
                return ['success' => true, 'is_replay' => true, 'audit' => $audit, 'total_discrepancy_qty' => $audit->total_discrepancy_qty, 'total_discrepancy_value' => $audit->total_discrepancy_value];
            }
            if ($audit->status === 'CANCELLED') {
                throw new OperationValidationException($audit->operation_id, 'Inventarizatsiya bekor qilingan.', errorCode: 'AUDIT_CANCELLED');
            }
            $items = $audit->items()->lockForUpdate()->get();

            foreach ($items as $item) {
                $discQty = (int) $item->discrepancy_quantity;

                // Hech qanday farq yo'q
                if ($discQty === 0) {
                    $item->update(['status' => 'ADJUSTED']);

                    continue;
                }

                // 1. Ortiqcha tovar (Surplus) -> ADJUSTMENT_IN
                if ($discQty > 0) {
                    $this->inventoryLedgerService->recordInflow(
                        productVariantId: $item->product_variant_id,
                        quantity: $discQty,
                        unitCost: (int) $item->unit_cost,
                        movementType: 'ADJUSTMENT_IN',
                        warehouseId: $audit->warehouse_id,
                        operationId: (string) Str::uuid(),
                        referenceType: InventoryAudit::class,
                        referenceId: $audit->id,
                        userId: $actor->id
                    );

                    $item->update(['status' => 'ADJUSTED']);
                }

                // 2. Kamomad (Shortage) -> ADJUSTMENT_OUT
                if ($discQty < 0) {
                    $shortage = abs($discQty);

                    // Rezerv himoyasini tekshirish
                    $this->inventoryAllocationService->validateStockReductionAllowed(
                        $item->product_variant_id,
                        $shortage,
                        $audit->warehouse_id
                    );

                    $this->inventoryLedgerService->recordOutflow(
                        productVariantId: $item->product_variant_id,
                        quantity: $shortage,
                        movementType: 'ADJUSTMENT_OUT',
                        warehouseId: $audit->warehouse_id,
                        operationId: (string) Str::uuid(),
                        referenceType: InventoryAudit::class,
                        referenceId: $audit->id,
                        userId: $actor->id
                    );

                    $item->update(['status' => 'ADJUSTED']);
                }
            }

            $freezeStatus = $forceIfFreezePending ? 'FORCE_CONFIRMED' : $audit->device_freeze_status;

            $audit->update([
                'status' => 'COMPLETED',
                'device_freeze_status' => $freezeStatus,
                'completed_by' => $actor->id,
                'completed_at' => Carbon::now(),
            ]);

            AuditLog::create([
                'operation_id' => $audit->operation_id,
                'user_id' => $actor->id,
                'action' => 'INVENTORY_AUDIT_COMPLETED',
                'auditable_type' => InventoryAudit::class,
                'auditable_id' => $audit->id,
                'new_values' => [
                    'audit_number' => $audit->audit_number,
                    'warehouse_id' => $audit->warehouse_id,
                    'total_expected_qty' => $audit->total_expected_qty,
                    'total_counted_qty' => $audit->total_counted_qty,
                    'total_discrepancy_qty' => $audit->total_discrepancy_qty,
                    'total_discrepancy_value' => $audit->total_discrepancy_value,
                    'forced' => $forceIfFreezePending,
                    'reason' => $reason,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            OutboxEvent::create([
                'event_id' => (string) Str::uuid(),
                'operation_id' => $audit->operation_id,
                'event_name' => 'InventoryAuditCompleted',
                'aggregate_type' => 'InventoryAudit',
                'aggregate_id' => (string) $audit->id,
                'payload' => [
                    'audit_id' => $audit->id,
                    'audit_number' => $audit->audit_number,
                    'total_discrepancy_qty' => $audit->total_discrepancy_qty,
                    'total_discrepancy_value' => $audit->total_discrepancy_value,
                ],
                'status' => 'PENDING',
                'created_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'is_replay' => false,
                'audit' => $audit->fresh(['items.variant.product']),
                'total_discrepancy_qty' => $audit->total_discrepancy_qty,
                'total_discrepancy_value' => $audit->total_discrepancy_value,
            ];
        });
    }
}
