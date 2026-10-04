<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\OutboxEvent;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\Ledger\Exceptions\CannotReturnMoreThanPurchasedException;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Ledger\SupplierLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierReturnService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected InventoryAllocationService $inventoryAllocationService,
        protected SupplierLedgerService $supplierLedgerService
    ) {}

    /**
     * Ta'minotchiga tovar qaytarish (Supplier Return) operatsiyasini atomik qayd etish.
     *
     * Invariantlar:
     * 1. Xarid qilingandan ko'p qaytarish yo'q.
     * 2. Chiqim faol qurilmalarga ajratilgan rezervlar chegarasini buzolmaydi (ReservedStockProtectionException).
     * 3. Ta'minotchi balansi xarid narxidagi tijoriy kredit (commercial credit) bo'yicha kamaytiriladi.
     * 4. Ombor chiqimi joriy WAC (Weighted Average Cost) bo'yicha hisoblanadi va farq qayd etiladi.
     */
    public function createSupplierReturn(
        int $purchaseId,
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
                "Ta'minotchiga qaytarish uchun kamida bitta tovar tanlanishi shart!",
                [],
                'NO_ITEMS_SELECTED'
            );
        }

        return DB::transaction(function () use (
            $purchaseId,
            $items,
            $reason,
            $operationId,
            $userId,
            $notes
        ) {
            // 1. Idempotency tekshiruvi
            $existingReturn = PurchaseReturn::with('items')->where('operation_id', $operationId)->first();
            if ($existingReturn) {
                return [
                    'success' => true,
                    'is_replay' => true,
                    'return' => $existingReturn,
                    'return_number' => $existingReturn->return_number,
                    'total_credit_amount' => $existingReturn->total_credit_amount,
                    'total_cost_amount' => $existingReturn->total_cost_amount,
                    'cost_discrepancy' => $existingReturn->cost_discrepancy,
                ];
            }

            // 2. Kirim hujjatini qulflash
            $purchase = Purchase::with('items')->where('id', $purchaseId)->lockForUpdate()->firstOrFail();

            if ($purchase->status === 'CANCELLED') {
                throw new OperationValidationException(
                    $operationId,
                    "Bekor qilingan (#{$purchase->invoice_number}) kirim bo'yicha tovar qaytarib bo'lmaydi!",
                    [],
                    'PURCHASE_ALREADY_CANCELLED'
                );
            }

            $preparedItems = [];
            $totalCreditAmount = 0;
            $totalCostAmount = 0;

            // 3. Har bir tovar qatorini tekshirish va rezerv himoyasini tekshirish
            foreach ($items as $itemReq) {
                $purchaseItemId = (int) ($itemReq['purchase_item_id'] ?? 0);
                $quantity = (int) ($itemReq['quantity'] ?? 0);

                if ($quantity <= 0) {
                    throw new OperationValidationException(
                        $operationId,
                        "Qaytariladigan tovar miqdori 0 dan katta butun son bo'lishi shart!",
                        ['purchase_item_id' => $purchaseItemId],
                        'INVALID_RETURN_QUANTITY'
                    );
                }

                $purchaseItem = $purchase->items->firstWhere('id', $purchaseItemId);
                if (! $purchaseItem) {
                    throw new OperationValidationException(
                        $operationId,
                        "Ushbu kirim hujjatida bunday tovar qatori topilmadi (#{$purchaseItemId})!",
                        [],
                        'ITEM_NOT_FOUND_IN_PURCHASE'
                    );
                }

                $alreadyReturnedQty = (int) PurchaseReturnItem::where('purchase_item_id', $purchaseItem->id)->sum('quantity');
                $purchasedQty = (int) $purchaseItem->quantity;
                $availableToReturn = $purchasedQty - $alreadyReturnedQty;

                if ($quantity > $availableToReturn) {
                    throw new CannotReturnMoreThanPurchasedException(
                        "Xarid qilingan miqdordan ortiq tovar ta'minotchiga qaytarib bo'lmaydi! Jami kirim: {$purchasedQty} dona, allaqachon qaytarilgan: {$alreadyReturnedQty} dona, so'ralgan: {$quantity} dona.",
                        $operationId,
                        [
                            'purchase_item_id' => $purchaseItem->id,
                            'purchased_quantity' => $purchasedQty,
                            'already_returned' => $alreadyReturnedQty,
                            'requested' => $quantity,
                        ]
                    );
                }

                // INVARIANT: "rezervga qarshi supplier return stockni yashirin sarflamaydi"
                $this->inventoryAllocationService->validateStockReductionAllowed(
                    $purchaseItem->product_variant_id,
                    $quantity,
                    $purchase->warehouse_id
                );

                $preparedItems[] = [
                    'purchase_item' => $purchaseItem,
                    'quantity' => $quantity,
                    'unit_cost' => (int) $purchaseItem->unit_cost,
                ];
            }

            $returnNumber = DocumentNumberGenerator::nextPurchaseReturnNumber();

            // 4. Asosiy PurchaseReturn hujjatini yaratish
            $purchaseReturn = PurchaseReturn::create([
                'operation_id' => $operationId,
                'return_number' => $returnNumber,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'warehouse_id' => $purchase->warehouse_id,
                'total_credit_amount' => 0, // quyida to'ldiriladi
                'total_cost_amount' => 0,
                'cost_discrepancy' => 0,
                'status' => 'POSTED',
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
                'posted_at' => Carbon::now(),
            ]);

            // 5. Ombordan chiqim qilish (WAC bo'yicha) va qatorlarni saqlash
            foreach ($preparedItems as $prep) {
                $purchaseItem = $prep['purchase_item'];
                $quantity = $prep['quantity'];
                $unitCost = $prep['unit_cost'];

                // Ombordan chiqim (PURCHASE_RETURN turi bilan)
                $outflowResult = $this->inventoryLedgerService->recordOutflow(
                    productVariantId: $purchaseItem->product_variant_id,
                    quantity: $quantity,
                    movementType: 'PURCHASE_RETURN',
                    warehouseId: $purchase->warehouse_id,
                    operationId: $operationId,
                    referenceType: PurchaseReturn::class,
                    referenceId: $purchaseReturn->id,
                    userId: $userId
                );

                $lineCredit = (int) round($quantity * $unitCost);
                $lineWacCost = (int) $outflowResult['total_cost'];
                $currentWac = (int) $outflowResult['unit_cost'];

                $totalCreditAmount += $lineCredit;
                $totalCostAmount += $lineWacCost;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'purchase_item_id' => $purchaseItem->id,
                    'product_variant_id' => $purchaseItem->product_variant_id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'current_wac' => $currentWac,
                    'line_credit' => $lineCredit,
                    'line_wac_cost' => $lineWacCost,
                ]);
            }

            $costDiscrepancy = $totalCreditAmount - $totalCostAmount;

            $purchaseReturn->update([
                'total_credit_amount' => $totalCreditAmount,
                'total_cost_amount' => $totalCostAmount,
                'cost_discrepancy' => $costDiscrepancy,
            ]);

            // 6. Ta'minotchi balansini kamaytirish (Tijoriy kredit / Majburiyat kamayishi)
            $this->supplierLedgerService->recordPaymentDebit(
                supplierId: $purchase->supplier_id,
                amount: $totalCreditAmount,
                type: 'PURCHASE_RETURN',
                operationId: $operationId,
                referenceType: PurchaseReturn::class,
                referenceId: $purchaseReturn->id,
                notes: "Ta'minotchiga tovar qaytarildi (#{$returnNumber}). Majburiyat kamaytirildi.",
                userId: $userId
            );

            // 7. Audit va Outbox
            AuditLog::create([
                'operation_id' => $operationId,
                'user_id' => $userId,
                'action' => 'PURCHASE_RETURN_CREATED',
                'auditable_type' => PurchaseReturn::class,
                'auditable_id' => $purchaseReturn->id,
                'new_values' => [
                    'return_number' => $returnNumber,
                    'purchase_id' => $purchase->id,
                    'total_credit_amount' => $totalCreditAmount,
                    'total_cost_amount' => $totalCostAmount,
                    'cost_discrepancy' => $costDiscrepancy,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            OutboxEvent::create([
                'event_id' => (string) Str::uuid(),
                'operation_id' => $operationId,
                'event_name' => 'SupplierReturnCreated',
                'aggregate_type' => 'PurchaseReturn',
                'aggregate_id' => (string) $purchaseReturn->id,
                'payload' => [
                    'purchase_return_id' => $purchaseReturn->id,
                    'return_number' => $returnNumber,
                    'purchase_id' => $purchase->id,
                    'total_credit_amount' => $totalCreditAmount,
                    'total_cost_amount' => $totalCostAmount,
                    'cost_discrepancy' => $costDiscrepancy,
                ],
                'status' => 'PENDING',
                'created_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'is_replay' => false,
                'return' => $purchaseReturn->load('items'),
                'return_number' => $returnNumber,
                'total_credit_amount' => $totalCreditAmount,
                'total_cost_amount' => $totalCostAmount,
                'cost_discrepancy' => $costDiscrepancy,
            ];
        });
    }
}
