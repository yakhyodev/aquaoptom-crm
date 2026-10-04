<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\OutboxEvent;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\CustomerLedgerService;
use App\Services\Ledger\Exceptions\CannotReturnMoreThanSoldException;
use App\Services\Ledger\Exceptions\InsufficientCashException;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleReturnService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected CustomerLedgerService $customerLedgerService,
        protected CashAccountService $cashAccountService
    ) {}

    /**
     * Sotuv qaytarish (Sale Return) operatsiyasini atomik qayd etish.
     *
     * Invariantlar:
     * 1. Sotilgandan ko'p qaytarish yo'q.
     * 2. Original sotuv narxi va cost snapshot ishlatiladi.
     * 3. Yaroqsiz qaytgan tovar sotiladigan qoldiqqa qo'shilmaydi.
     * 4. Qisman returnlar sum/cost yaxlitlashni oxirgi donada to'liq yopadi.
     * 5. Refund cash va qarzni ikki kamaytirmaydi: debt_deduction = total_amount - refund_amount.
     * 6. Kassa mablag'i yetarli bo'lmasa, atomik rollback.
     */
    public function createSaleReturn(
        int $saleId,
        array $items,
        string $reason,
        ?string $operationId = null,
        int $refundAmount = 0,
        ?int $cashAccountId = null,
        ?string $refundPaymentMethod = 'CASH',
        ?int $cashSessionId = null,
        ?int $userId = null,
        ?string $notes = null
    ): array {
        $operationId = $operationId ?: (string) Str::uuid();

        if (empty($items)) {
            throw new OperationValidationException(
                $operationId,
                'Qaytarish uchun kamida bitta tovar tanlanishi shart!',
                [],
                'NO_ITEMS_SELECTED'
            );
        }

        if ($refundAmount < 0) {
            throw new OperationValidationException(
                $operationId,
                "Qaytariladigan pul summasi manfiy bo'lishi mumkin emas!",
                [],
                'NEGATIVE_REFUND_AMOUNT'
            );
        }

        return DB::transaction(function () use (
            $saleId,
            $items,
            $reason,
            $operationId,
            $refundAmount,
            $cashAccountId,
            $refundPaymentMethod,
            $cashSessionId,
            $userId,
            $notes
        ) {
            // 1. Idempotency tekshiruvi
            $existingReturn = SaleReturn::with('items')->where('operation_id', $operationId)->first();
            if ($existingReturn) {
                return [
                    'success' => true,
                    'is_replay' => true,
                    'return' => $existingReturn,
                    'return_number' => $existingReturn->return_number,
                    'total_amount' => $existingReturn->total_amount,
                    'refund_amount' => $existingReturn->refund_amount,
                    'debt_deduction_amount' => $existingReturn->debt_deduction_amount,
                ];
            }

            // 2. Savdoni qulflash (lockForUpdate)
            $sale = Sale::with('items')->where('id', $saleId)->lockForUpdate()->firstOrFail();

            if (in_array($sale->status, ['CANCELLED', 'VOID'], true)) {
                throw new OperationValidationException(
                    $operationId,
                    "Bekor qilingan (#{$sale->invoice_number}) savdo bo'yicha tovar qaytarib bo'lmaydi!",
                    [],
                    'SALE_ALREADY_CANCELLED'
                );
            }

            // 3. Qatorlarni tekshirish va yaxlitlashni hisoblash
            $preparedItems = [];
            $totalReturnAmount = 0;
            $totalReturnCost = 0;

            foreach ($items as $itemReq) {
                $saleItemId = (int) ($itemReq['sale_item_id'] ?? 0);
                $quantity = (int) ($itemReq['quantity'] ?? 0);
                $isDamaged = (bool) ($itemReq['is_damaged'] ?? false);
                $itemCondition = $isDamaged ? 'DAMAGED' : 'SELLABLE';

                if ($quantity <= 0) {
                    throw new OperationValidationException(
                        $operationId,
                        "Qaytariladigan tovar miqdori 0 dan katta butun son bo'lishi shart!",
                        ['sale_item_id' => $saleItemId],
                        'INVALID_RETURN_QUANTITY'
                    );
                }

                $saleItem = $sale->items->firstWhere('id', $saleItemId);
                if (! $saleItem) {
                    throw new OperationValidationException(
                        $operationId,
                        "Ushbu savdo chekida bunday tovar qatori mavjud emas (#{$saleItemId})!",
                        [],
                        'ITEM_NOT_FOUND_IN_SALE'
                    );
                }

                // Allaqachon qaytarilgan miqdorni hisoblash
                $alreadyReturnedQty = (int) SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
                $soldQty = (int) $saleItem->quantity;
                $availableToReturn = $soldQty - $alreadyReturnedQty;

                if ($quantity > $availableToReturn) {
                    throw new CannotReturnMoreThanSoldException(
                        "Sotilgan miqdordan ortiq tovar qaytarib bo'lmaydi! Jami sotilgan: {$soldQty} dona, allaqachon qaytarilgan: {$alreadyReturnedQty} dona, so'ralgan: {$quantity} dona.",
                        $operationId,
                        [
                            'sale_item_id' => $saleItem->id,
                            'sold_quantity' => $soldQty,
                            'already_returned' => $alreadyReturnedQty,
                            'requested' => $quantity,
                        ]
                    );
                }

                // Qat'iy yaxlitlash yopilishi: agar oxirgi dona qaytarilayotgan bo'lsa
                $isFinalUnit = (($alreadyReturnedQty + $quantity) === $soldQty);

                if ($isFinalUnit) {
                    $alreadyReturnedLineTotal = (int) SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('line_total');
                    $lineTotal = (int) $saleItem->line_total - $alreadyReturnedLineTotal;

                    $alreadyReturnedCostTotal = (int) SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('cost_total');
                    $costTotal = (int) $saleItem->cost_total - $alreadyReturnedCostTotal;
                } else {
                    $lineTotal = (int) round($quantity * $saleItem->sale_price);
                    $costTotal = (int) round($quantity * $saleItem->purchase_cost_snapshot);
                }

                $totalReturnAmount += $lineTotal;
                $totalReturnCost += $costTotal;

                $preparedItems[] = [
                    'sale_item' => $saleItem,
                    'quantity' => $quantity,
                    'unit_price' => (int) $saleItem->sale_price,
                    'cost_price' => (int) $saleItem->purchase_cost_snapshot,
                    'line_total' => $lineTotal,
                    'cost_total' => $costTotal,
                    'is_damaged' => $isDamaged,
                    'condition' => $itemCondition,
                ];
            }

            // 4. Pul qaytarish (Refund) va qarz chegirish (Debt deduction) tekshiruvi
            if ($refundAmount > $totalReturnAmount) {
                throw new OperationValidationException(
                    $operationId,
                    "Kassadan qaytariladigan pul summasi ({$refundAmount} so'm) qaytarilgan tovarlar umumiy qiymatidan ({$totalReturnAmount} so'm) oshishi mumkin emas!",
                    [],
                    'REFUND_EXCEEDS_RETURN_VALUE'
                );
            }

            // Dastlab to'langan puldan ortiq naqd qaytarilmasligi
            $previousCashRefunds = (int) SaleReturn::where('sale_id', $sale->id)->sum('refund_amount');
            $maxAllowedCashRefund = max(0, (int) $sale->paid_amount - $previousCashRefunds);

            if ($refundAmount > $maxAllowedCashRefund) {
                throw new OperationValidationException(
                    $operationId,
                    "Kassadan qaytariladigan pul ushbu savdoda olingan pul qoldig'idan ({$maxAllowedCashRefund} so'm) oshishi mumkin emas!",
                    [],
                    'REFUND_EXCEEDS_PAID_AMOUNT'
                );
            }

            // Invariant: Qarzdan kamaytiriladigan summa = Jami tovar - Kassadan qaytarilgan naqd
            $debtDeductionAmount = $totalReturnAmount - $refundAmount;

            $returnNumber = DocumentNumberGenerator::nextSaleReturnNumber();

            // 5. SaleReturn asosiy hujjatini yaratish
            $saleReturn = SaleReturn::create([
                'operation_id' => $operationId,
                'return_number' => $returnNumber,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'warehouse_id' => $sale->warehouse_id,
                'total_amount' => $totalReturnAmount,
                'total_cost' => $totalReturnCost,
                'refund_amount' => $refundAmount,
                'debt_deduction_amount' => $debtDeductionAmount,
                'cash_account_id' => $refundAmount > 0 ? ($cashAccountId ?: $sale->cash_account_id) : null,
                'cash_session_id' => $cashSessionId,
                'refund_payment_method' => $refundAmount > 0 ? $refundPaymentMethod : null,
                'status' => 'POSTED',
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
                'posted_at' => Carbon::now(),
            ]);

            // 6. Qatorlarni saqlash va omborni yangilash
            foreach ($preparedItems as $prep) {
                $saleItem = $prep['sale_item'];

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'quantity' => $prep['quantity'],
                    'unit_price' => $prep['unit_price'],
                    'cost_price' => $prep['cost_price'],
                    'line_total' => $prep['line_total'],
                    'cost_total' => $prep['cost_total'],
                    'is_damaged' => $prep['is_damaged'],
                    'condition' => $prep['condition'],
                ]);

                // Agar tovar shikastlanmagan (sog'lom) bo'lsa, omborga kirim qilinadi
                if (! $prep['is_damaged']) {
                    $this->inventoryLedgerService->recordInflow(
                        productVariantId: $saleItem->product_variant_id,
                        quantity: $prep['quantity'],
                        unitCost: $prep['cost_price'],
                        movementType: 'SALE_RETURN',
                        warehouseId: $sale->warehouse_id,
                        operationId: $operationId,
                        referenceType: SaleReturn::class,
                        referenceId: $saleReturn->id,
                        userId: $userId
                    );
                }
                // DIQQAT: Yaroqsiz (is_damaged) tovar sotiladigan qoldiqqa QO'SHILMAYDI!
            }

            // 7. Kassa chiqimi (agar naqd pul qaytarilsa)
            if ($refundAmount > 0) {
                $targetCashAccountId = $cashAccountId ?: $sale->cash_account_id;
                if (! $targetCashAccountId) {
                    throw new OperationValidationException(
                        $operationId,
                        'Pul qaytarish uchun kassa hisobi tanlanmagan!',
                        [],
                        'CASH_ACCOUNT_REQUIRED'
                    );
                }

                // Agar kassada pul yetarli bo'lmasa, InsufficientCashException tashlanadi va butun tranzaksiya rollback bo'ladi
                $this->cashAccountService->recordOutflow(
                    cashAccountId: $targetCashAccountId,
                    amount: $refundAmount,
                    type: 'SALE_REFUND',
                    operationId: $operationId,
                    referenceType: SaleReturn::class,
                    referenceId: $saleReturn->id,
                    description: "Sotuv qaytarildi (#{$returnNumber}). Mijozga pul to'landi.",
                    userId: $userId,
                    allowNegative: false,
                    cashSessionId: $cashSessionId
                );
            }

            // 8. Mijoz qarzini kamaytirish (faqat debt_deduction_amount bo'yicha)
            // Bu orqali qarz va naqd bir vaqtda ikkilanib kamaymaydi!
            if ($debtDeductionAmount > 0 && $sale->customer_id) {
                $this->customerLedgerService->recordCredit(
                    customerId: $sale->customer_id,
                    amount: $debtDeductionAmount,
                    type: 'SALE_RETURN',
                    paymentMethod: $refundPaymentMethod,
                    operationId: $operationId,
                    referenceType: SaleReturn::class,
                    referenceId: $saleReturn->id,
                    notes: "Sotuv qaytarildi (#{$returnNumber}). Qarz kamaytirildi.",
                    userId: $userId
                );
            }

            // 9. Audit va Outbox
            AuditLog::create([
                'operation_id' => $operationId,
                'user_id' => $userId,
                'action' => 'SALE_RETURN_CREATED',
                'auditable_type' => SaleReturn::class,
                'auditable_id' => $saleReturn->id,
                'new_values' => [
                    'return_number' => $returnNumber,
                    'sale_id' => $sale->id,
                    'total_amount' => $totalReturnAmount,
                    'refund_amount' => $refundAmount,
                    'debt_deduction_amount' => $debtDeductionAmount,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            OutboxEvent::create([
                'event_id' => (string) Str::uuid(),
                'operation_id' => $operationId,
                'event_name' => 'SaleReturnCreated',
                'aggregate_type' => 'SaleReturn',
                'aggregate_id' => (string) $saleReturn->id,
                'payload' => [
                    'sale_return_id' => $saleReturn->id,
                    'return_number' => $returnNumber,
                    'sale_id' => $sale->id,
                    'total_amount' => $totalReturnAmount,
                    'refund_amount' => $refundAmount,
                    'debt_deduction_amount' => $debtDeductionAmount,
                ],
                'status' => 'PENDING',
                'created_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'is_replay' => false,
                'return' => $saleReturn->load('items'),
                'return_number' => $returnNumber,
                'total_amount' => $totalReturnAmount,
                'total_cost' => $totalReturnCost,
                'refund_amount' => $refundAmount,
                'debt_deduction_amount' => $debtDeductionAmount,
            ];
        });
    }
}
