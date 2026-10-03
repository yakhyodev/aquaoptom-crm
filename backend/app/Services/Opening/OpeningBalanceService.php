<?php

namespace App\Services\Opening;

use App\Models\OpeningBalanceDocument;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\CustomerLedgerService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Ledger\SupplierLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\TransactionalOperationService;
use InvalidArgumentException;

class OpeningBalanceService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected CustomerLedgerService $customerLedgerService,
        protected SupplierLedgerService $supplierLedgerService,
        protected CashAccountService $cashAccountService,
        protected TransactionalOperationService $transactionalOperationService
    ) {}

    /**
     * Ombor tovari bo'yicha boshlang'ich qoldiq kiritish (Idempotent).
     */
    public function recordStockOpening(
        int $productVariantId,
        int $quantity,
        int $unitCost,
        string $operationId,
        ?int $warehouseId = null,
        ?int $userId = null
    ): array {
        $payload = [
            'type' => 'STOCK',
            'product_variant_id' => $productVariantId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'warehouse_id' => $warehouseId,
        ];

        return $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'RECORD_STOCK_OPENING',
            payload: $payload,
            businessCallback: function () use ($productVariantId, $quantity, $unitCost, $operationId, $warehouseId, $userId) {
                $docNumber = DocumentNumberGenerator::nextOpeningNumber();

                $inflow = $this->inventoryLedgerService->recordInflow(
                    productVariantId: $productVariantId,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    movementType: 'OPENING_BALANCE',
                    warehouseId: $warehouseId,
                    operationId: $operationId,
                    referenceType: OpeningBalanceDocument::class,
                    userId: $userId
                );

                $doc = OpeningBalanceDocument::create([
                    'document_number' => $docNumber,
                    'operation_id' => $operationId,
                    'type' => 'STOCK',
                    'total_amount' => $inflow['total_cost'],
                    'total_items' => $quantity,
                    'status' => 'POSTED',
                    'notes' => "Ombor boshlang'ich qoldig'i: {$quantity} dona x {$unitCost} so'm",
                    'created_by' => $userId,
                ]);

                return [
                    'document_id' => $doc->id,
                    'document_number' => $docNumber,
                    'stock' => $inflow,
                ];
            },
            actorId: $userId
        );
    }

    /**
     * Kassa hisobi bo'yicha boshlang'ich pul qoldig'i kiritish (Idempotent).
     */
    public function recordCashOpening(
        int $cashAccountId,
        int $amount,
        string $operationId,
        ?int $userId = null
    ): array {
        $payload = [
            'type' => 'CASH',
            'cash_account_id' => $cashAccountId,
            'amount' => $amount,
        ];

        return $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'RECORD_CASH_OPENING',
            payload: $payload,
            businessCallback: function () use ($cashAccountId, $amount, $operationId, $userId) {
                $docNumber = DocumentNumberGenerator::nextOpeningNumber();

                $movement = $this->cashAccountService->recordInflow(
                    cashAccountId: $cashAccountId,
                    amount: $amount,
                    type: 'OPENING_BALANCE',
                    operationId: $operationId,
                    referenceType: OpeningBalanceDocument::class,
                    description: "Boshlang'ich kassa qoldig'i #{$docNumber}",
                    userId: $userId
                );

                $doc = OpeningBalanceDocument::create([
                    'document_number' => $docNumber,
                    'operation_id' => $operationId,
                    'type' => 'CASH',
                    'total_amount' => $amount,
                    'total_items' => 1,
                    'status' => 'POSTED',
                    'notes' => "Kassa boshlang'ich qoldig'i #{$cashAccountId}: {$amount} so'm",
                    'created_by' => $userId,
                ]);

                return [
                    'document_id' => $doc->id,
                    'document_number' => $docNumber,
                    'cash_movement_id' => $movement->id,
                    'balance_after' => $movement->balance_after,
                ];
            },
            actorId: $userId
        );
    }

    /**
     * Mijoz bo'yicha boshlang'ich qoldiq (Qarz yoki Avans) kiritish (Idempotent).
     *
     * signedAmount > 0: Mijozning bizdan qarzi
     * signedAmount < 0: Mijozning bizdagi avansi (oldindan to'lovi)
     */
    public function recordCustomerOpening(
        int $customerId,
        int $signedAmount,
        string $operationId,
        ?int $userId = null
    ): array {
        $payload = [
            'type' => 'CUSTOMER',
            'customer_id' => $customerId,
            'signed_amount' => $signedAmount,
        ];

        return $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'RECORD_CUSTOMER_OPENING',
            payload: $payload,
            businessCallback: function () use ($customerId, $signedAmount, $operationId, $userId) {
                $docNumber = DocumentNumberGenerator::nextOpeningNumber();

                if ($signedAmount > 0) {
                    $ledger = $this->customerLedgerService->recordDebit(
                        customerId: $customerId,
                        amount: $signedAmount,
                        type: 'OPENING_BALANCE',
                        operationId: $operationId,
                        referenceType: OpeningBalanceDocument::class,
                        notes: "Boshlang'ich mijoz qarzi #{$docNumber}",
                        userId: $userId
                    );
                } elseif ($signedAmount < 0) {
                    $ledger = $this->customerLedgerService->recordCredit(
                        customerId: $customerId,
                        amount: abs($signedAmount),
                        type: 'OPENING_BALANCE',
                        paymentMethod: 'OFFSET',
                        operationId: $operationId,
                        referenceType: OpeningBalanceDocument::class,
                        notes: "Boshlang'ich mijoz avansi #{$docNumber}",
                        userId: $userId
                    );
                } else {
                    throw new InvalidArgumentException("Boshlang'ich summa 0 bo'lishi mumkin emas.");
                }

                $doc = OpeningBalanceDocument::create([
                    'document_number' => $docNumber,
                    'operation_id' => $operationId,
                    'type' => 'CUSTOMER',
                    'total_amount' => abs($signedAmount),
                    'total_items' => 1,
                    'status' => 'POSTED',
                    'notes' => $signedAmount > 0
                        ? "Mijoz boshlang'ich qarzi: {$signedAmount} so'm"
                        : "Mijoz boshlang'ich avansi: ".abs($signedAmount)." so'm",
                    'created_by' => $userId,
                ]);

                return [
                    'document_id' => $doc->id,
                    'document_number' => $docNumber,
                    'ledger_id' => $ledger->id,
                    'balance_after' => $ledger->balance_after,
                ];
            },
            actorId: $userId
        );
    }

    /**
     * Ta'minotchi bo'yicha boshlang'ich qoldiq (Qarz yoki Avans) kiritish (Idempotent).
     *
     * signedAmount > 0: Bizning ta'minotchiga qarzimiz
     * signedAmount < 0: Bizning ta'minotchidagi avansimiz (haqdorligimiz)
     */
    public function recordSupplierOpening(
        int $supplierId,
        int $signedAmount,
        string $operationId,
        ?int $userId = null
    ): array {
        $payload = [
            'type' => 'SUPPLIER',
            'supplier_id' => $supplierId,
            'signed_amount' => $signedAmount,
        ];

        return $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'RECORD_SUPPLIER_OPENING',
            payload: $payload,
            businessCallback: function () use ($supplierId, $signedAmount, $operationId, $userId) {
                $docNumber = DocumentNumberGenerator::nextOpeningNumber();

                if ($signedAmount > 0) {
                    $ledger = $this->supplierLedgerService->recordPurchaseCredit(
                        supplierId: $supplierId,
                        amount: $signedAmount,
                        type: 'OPENING_BALANCE',
                        operationId: $operationId,
                        referenceType: OpeningBalanceDocument::class,
                        notes: "Boshlang'ich ta'minotchi qarzimiz #{$docNumber}",
                        userId: $userId
                    );
                } elseif ($signedAmount < 0) {
                    $ledger = $this->supplierLedgerService->recordPaymentDebit(
                        supplierId: $supplierId,
                        amount: abs($signedAmount),
                        type: 'OPENING_BALANCE',
                        paymentMethod: 'OFFSET',
                        operationId: $operationId,
                        referenceType: OpeningBalanceDocument::class,
                        notes: "Boshlang'ich ta'minotchi avansimiz #{$docNumber}",
                        userId: $userId
                    );
                } else {
                    throw new InvalidArgumentException("Boshlang'ich summa 0 bo'lishi mumkin emas.");
                }

                $doc = OpeningBalanceDocument::create([
                    'document_number' => $docNumber,
                    'operation_id' => $operationId,
                    'type' => 'SUPPLIER',
                    'total_amount' => abs($signedAmount),
                    'total_items' => 1,
                    'status' => 'POSTED',
                    'notes' => $signedAmount > 0
                        ? "Ta'minotchiga boshlang'ich qarzimiz: {$signedAmount} so'm"
                        : "Ta'minotchiga boshlang'ich avansimiz: ".abs($signedAmount)." so'm",
                    'created_by' => $userId,
                ]);

                return [
                    'document_id' => $doc->id,
                    'document_number' => $docNumber,
                    'ledger_id' => $ledger->id,
                    'balance_after' => $ledger->balance_after,
                ];
            },
            actorId: $userId
        );
    }

    /**
     * Barcha boshlang'ich qoldiqlarni bitta paketda (Batch) kiritish (Idempotent).
     *
     * @param  array  $payload  [ 'stock' => [...], 'cash' => [...], 'customers' => [...], 'suppliers' => [...], 'notes' => '...' ]
     */
    public function recordBatchOpening(
        array $payload,
        string $operationId,
        ?int $userId = null
    ): array {
        return $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'RECORD_BATCH_OPENING',
            payload: $payload,
            businessCallback: function () use ($payload, $operationId, $userId) {
                $docNumber = DocumentNumberGenerator::nextOpeningNumber();
                $totalAmount = 0;
                $totalItems = 0;

                $stockResults = [];
                $cashResults = [];
                $customerResults = [];
                $supplierResults = [];

                // 1. Ombor qoldiqlari
                if (! empty($payload['stock']) && is_array($payload['stock'])) {
                    foreach ($payload['stock'] as $item) {
                        $variantId = (int) $item['variant_id'];
                        $qty = (int) $item['quantity'];
                        $cost = (int) $item['unit_cost'];

                        if ($qty > 0 && $cost >= 0) {
                            $res = $this->inventoryLedgerService->recordInflow(
                                productVariantId: $variantId,
                                quantity: $qty,
                                unitCost: $cost,
                                movementType: 'OPENING_BALANCE',
                                operationId: $operationId,
                                referenceType: OpeningBalanceDocument::class,
                                userId: $userId
                            );
                            $stockResults[] = $res;
                            $totalAmount += $res['total_cost'];
                            $totalItems += $qty;
                        }
                    }
                }

                // 2. Kassa qoldiqlari
                if (! empty($payload['cash']) && is_array($payload['cash'])) {
                    foreach ($payload['cash'] as $item) {
                        $accountId = (int) $item['account_id'];
                        $amt = (int) $item['amount'];

                        if ($amt > 0) {
                            $res = $this->cashAccountService->recordInflow(
                                cashAccountId: $accountId,
                                amount: $amt,
                                type: 'OPENING_BALANCE',
                                operationId: $operationId,
                                referenceType: OpeningBalanceDocument::class,
                                description: "Boshlang'ich kassa qoldig'i #{$docNumber}",
                                userId: $userId
                            );
                            $cashResults[] = [
                                'account_id' => $accountId,
                                'amount' => $amt,
                                'balance_after' => $res->balance_after,
                            ];
                            $totalAmount += $amt;
                            $totalItems++;
                        }
                    }
                }

                // 3. Mijozlar qarz yoki avansi
                if (! empty($payload['customers']) && is_array($payload['customers'])) {
                    foreach ($payload['customers'] as $item) {
                        $cId = (int) $item['customer_id'];
                        $signed = (int) $item['signed_amount'];

                        if ($signed !== 0) {
                            if ($signed > 0) {
                                $res = $this->customerLedgerService->recordDebit(
                                    customerId: $cId,
                                    amount: $signed,
                                    type: 'OPENING_BALANCE',
                                    operationId: $operationId,
                                    referenceType: OpeningBalanceDocument::class,
                                    notes: "Boshlang'ich mijoz qarzi #{$docNumber}",
                                    userId: $userId
                                );
                            } else {
                                $res = $this->customerLedgerService->recordCredit(
                                    customerId: $cId,
                                    amount: abs($signed),
                                    type: 'OPENING_BALANCE',
                                    paymentMethod: 'OFFSET',
                                    operationId: $operationId,
                                    referenceType: OpeningBalanceDocument::class,
                                    notes: "Boshlang'ich mijoz avansi #{$docNumber}",
                                    userId: $userId
                                );
                            }
                            $customerResults[] = [
                                'customer_id' => $cId,
                                'balance_after' => $res->balance_after,
                            ];
                            $totalItems++;
                        }
                    }
                }

                // 4. Ta'minotchilar qarz yoki avansi
                if (! empty($payload['suppliers']) && is_array($payload['suppliers'])) {
                    foreach ($payload['suppliers'] as $item) {
                        $sId = (int) $item['supplier_id'];
                        $signed = (int) $item['signed_amount'];

                        if ($signed !== 0) {
                            if ($signed > 0) {
                                $res = $this->supplierLedgerService->recordPurchaseCredit(
                                    supplierId: $sId,
                                    amount: $signed,
                                    type: 'OPENING_BALANCE',
                                    operationId: $operationId,
                                    referenceType: OpeningBalanceDocument::class,
                                    notes: "Boshlang'ich ta'minotchi qarzimiz #{$docNumber}",
                                    userId: $userId
                                );
                            } else {
                                $res = $this->supplierLedgerService->recordPaymentDebit(
                                    supplierId: $sId,
                                    amount: abs($signed),
                                    type: 'OPENING_BALANCE',
                                    paymentMethod: 'OFFSET',
                                    operationId: $operationId,
                                    referenceType: OpeningBalanceDocument::class,
                                    notes: "Boshlang'ich ta'minotchi avansimiz #{$docNumber}",
                                    userId: $userId
                                );
                            }
                            $supplierResults[] = [
                                'supplier_id' => $sId,
                                'balance_after' => $res->balance_after,
                            ];
                            $totalItems++;
                        }
                    }
                }

                $doc = OpeningBalanceDocument::create([
                    'document_number' => $docNumber,
                    'operation_id' => $operationId,
                    'type' => 'BATCH',
                    'total_amount' => $totalAmount,
                    'total_items' => $totalItems,
                    'status' => 'POSTED',
                    'notes' => $payload['notes'] ?? 'Boshlang\'ich qoldiqlarni to\'liq kiritish',
                    'created_by' => $userId,
                ]);

                return [
                    'document_id' => $doc->id,
                    'document_number' => $docNumber,
                    'total_amount' => $totalAmount,
                    'total_items' => $totalItems,
                    'stock' => $stockResults,
                    'cash' => $cashResults,
                    'customers' => $customerResults,
                    'suppliers' => $supplierResults,
                ];
            },
            actorId: $userId
        );
    }
}
