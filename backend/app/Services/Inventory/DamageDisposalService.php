<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\DamageItem;
use App\Models\DamageRecord;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\TransactionalOperationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DamageDisposalService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected InventoryAllocationService $inventoryAllocationService
    ) {}

    /**
     * Brak / yaroqsiz tovar chiqimi (Damage Disposal).
     *
     * Qat'iy Arxitektura Qoidalari:
     * 1. "Brak tannarx yo‘qotishi, cash expense emas." — hech qanday kassa yoki pul harakati yozilmaydi!
     * 2. "Rezervga qarshi brak stockni yashirin sarflamaydi" — ReservedStockProtectionException orqali himoyalangan.
     * 3. posted hujjat o'chirilmaydi.
     */
    public function recordDamage(
        int $warehouseId,
        array $items,
        string $reason,
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null
    ): array {
        $operationId ??= (string) Str::uuid();
        $actor = $userId ? User::find($userId) : auth()->user();
        if (! $actor || ! $actor->hasPermission('stock_adjustment')) {
            throw new OperationValidationException($operationId, 'Ruxsat mavjud emas.', errorCode: 'PERMISSION_DENIED');
        }
        $payload = compact('warehouseId', 'items', 'reason', 'notes');
        $operations = app(TransactionalOperationService::class);
        $isReplay = $operations->replay($operationId, 'DAMAGE_DISPOSAL', $payload, $actor->id) !== null;
        $result = $operations->execute($operationId, 'DAMAGE_DISPOSAL', $payload, function () use ($warehouseId, $items, $reason, $operationId, $actor, $notes) {
            $result = $this->postRecordDamage($warehouseId, $items, $reason, $operationId, $actor->id, $notes);
            $result['document_id'] = $result['damage_record']->id;
            unset($result['damage_record']);

            return $result;
        }, actorId: $actor->id);
        $result['damage_record'] = DamageRecord::with('items')->findOrFail($result['document_id']);
        $result['is_replay'] = $isReplay;

        return $result;
    }

    private function postRecordDamage(
        int $warehouseId,
        array $items,
        string $reason,
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null
    ): array {
        $operationId = $operationId ?: (string) Str::uuid();

        if (empty($items)) {
            throw new OperationValidationException(
                $operationId,
                'Brakka chiqarish uchun kamida bitta tovar tanlanishi shart!',
                [],
                'NO_ITEMS_SELECTED'
            );
        }

        return DB::transaction(function () use (
            $warehouseId,
            $items,
            $reason,
            $operationId,
            $userId,
            $notes
        ) {
            // 1. Idempotency tekshiruvi
            $existing = DamageRecord::with('items')->where('operation_id', $operationId)->first();
            if ($existing) {
                return [
                    'success' => true,
                    'is_replay' => true,
                    'damage_record' => $existing,
                    'damage_number' => $existing->damage_number,
                    'total_loss_value' => $existing->total_loss_value,
                    'total_quantity' => $existing->total_quantity,
                ];
            }

            $items = collect($items)->groupBy('product_variant_id')->map(function ($group, $variantId) use ($operationId) {
                $quantity = 0;
                foreach ($group as $row) {
                    $raw = $row['quantity'] ?? 0;
                    if (! is_numeric($raw) || $raw != (int) $raw || $raw <= 0 || $raw > InventoryLedgerService::MAX_QUANTITY - $quantity) {
                        throw new OperationValidationException($operationId, 'Butun musbat dona talab qilinadi.', errorCode: 'INVALID_DAMAGE_QUANTITY');
                    }
                    $quantity += (int) $raw;
                }

                return ['product_variant_id' => (int) $variantId, 'quantity' => $quantity];
            })->sortKeys()->values()->all();
            // 2. Tovar va rezerv tekshiruvi
            foreach ($items as $itemReq) {
                $variantId = (int) ($itemReq['product_variant_id'] ?? 0);
                $quantity = (int) ($itemReq['quantity'] ?? 0);

                if (! is_numeric($itemReq['quantity'] ?? null) || $quantity != $itemReq['quantity'] || $quantity <= 0 || $quantity > InventoryLedgerService::MAX_QUANTITY) {
                    throw new OperationValidationException(
                        $operationId,
                        "Brak miqdori 0 dan katta butun son bo'lishi shart!",
                        ['product_variant_id' => $variantId],
                        'INVALID_DAMAGE_QUANTITY'
                    );
                }

                // INVARIANT: "rezervga qarshi brak stockni yashirin sarflamaydi"
                $this->inventoryAllocationService->validateStockReductionAllowed(
                    $variantId,
                    $quantity,
                    $warehouseId
                );
            }

            $damageNumber = DocumentNumberGenerator::nextDamageNumber();

            // 3. DamageRecord yaratish
            $damageRecord = DamageRecord::create([
                'operation_id' => $operationId,
                'damage_number' => $damageNumber,
                'warehouse_id' => $warehouseId,
                'total_loss_value' => 0,
                'total_quantity' => 0,
                'status' => 'POSTED',
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
                'posted_at' => Carbon::now(),
            ]);

            $totalLossValue = 0;
            $totalQuantity = 0;

            // 4. Ombordan chiqim qilish (DAMAGE turi bilan, WAC bo'yicha)
            foreach ($items as $itemReq) {
                $variantId = (int) $itemReq['product_variant_id'];
                $quantity = (int) $itemReq['quantity'];
                $itemReason = $itemReq['reason'] ?? $reason;

                $outflowResult = $this->inventoryLedgerService->recordOutflow(
                    productVariantId: $variantId,
                    quantity: $quantity,
                    movementType: 'DAMAGE',
                    warehouseId: $warehouseId,
                    operationId: $operationId,
                    referenceType: DamageRecord::class,
                    referenceId: $damageRecord->id,
                    userId: $userId
                );

                $unitCost = (int) $outflowResult['unit_cost'];
                $lineTotalCost = (int) $outflowResult['total_cost'];

                $totalLossValue += $lineTotalCost;
                $totalQuantity += $quantity;

                DamageItem::create([
                    'damage_record_id' => $damageRecord->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotalCost,
                    'reason' => $itemReason,
                ]);
            }

            $damageRecord->update([
                'total_loss_value' => $totalLossValue,
                'total_quantity' => $totalQuantity,
            ]);

            // DIQQAT: Hech qanday kassa harakati (CashMovement) yaratilmaydi!
            // Brak faqat ombor qiymatini kamaytiruvchi yo'qotishdir.

            // 5. Audit va Outbox
            AuditLog::create([
                'operation_id' => $operationId,
                'user_id' => $userId,
                'action' => 'DAMAGE_DISPOSAL_RECORDED',
                'auditable_type' => DamageRecord::class,
                'auditable_id' => $damageRecord->id,
                'new_values' => [
                    'damage_number' => $damageNumber,
                    'warehouse_id' => $warehouseId,
                    'total_loss_value' => $totalLossValue,
                    'total_quantity' => $totalQuantity,
                    'reason' => $reason,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            OutboxEvent::create([
                'event_id' => (string) Str::uuid(),
                'operation_id' => $operationId,
                'event_name' => 'DamageDisposalRecorded',
                'aggregate_type' => 'DamageRecord',
                'aggregate_id' => (string) $damageRecord->id,
                'payload' => [
                    'damage_record_id' => $damageRecord->id,
                    'damage_number' => $damageNumber,
                    'total_loss_value' => $totalLossValue,
                    'total_quantity' => $totalQuantity,
                ],
                'status' => 'PENDING',
                'created_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'is_replay' => false,
                'damage_record' => $damageRecord->load('items'),
                'damage_number' => $damageNumber,
                'total_loss_value' => $totalLossValue,
                'total_quantity' => $totalQuantity,
            ];
        });
    }
}
