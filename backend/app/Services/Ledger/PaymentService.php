<?php

namespace App\Services\Ledger;

use App\Models\Payment;
use App\Services\Operations\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected CashAccountService $cashService,
        protected CustomerLedgerService $customerLedgerService,
        protected SupplierLedgerService $supplierLedgerService
    ) {}

    /**
     * Mijozdan to'lov qabul qilish (Customer Payment).
     * Atomik tarzda:
     * 1. Kassaga pul kirimi (CashMovement)
     * 2. Mijoz daftarida credit (CustomerLedger - qarz kamayadi yoki avans ko'payadi)
     * 3. Payment hujjati
     */
    public function recordCustomerPayment(
        int $customerId,
        int $cashAccountId,
        int $amount,
        string $paymentMethod = 'CASH',
        ?string $operationId = null,
        ?string $notes = null,
        ?int $userId = null
    ): Payment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('To\'lov summasi 0 dan katta butun son bo\'lishi shart.');
        }

        return DB::transaction(function () use ($customerId, $cashAccountId, $amount, $paymentMethod, $operationId, $notes, $userId) {
            $paymentNumber = DocumentNumberGenerator::nextPaymentNumber();

            // 1. Payment yaratish
            $payment = Payment::create([
                'payment_number' => $paymentNumber,
                'operation_id' => $operationId ?: Str::uuid()->toString(),
                'party_type' => 'CUSTOMER',
                'party_id' => $customerId,
                'cash_account_id' => $cashAccountId,
                'payment_type' => 'CUSTOMER_PAYMENT',
                'payment_method' => strtoupper($paymentMethod),
                'direction' => 'IN',
                'amount' => $amount,
                'notes' => $notes,
                'status' => 'COMPLETED',
                'created_by' => $userId,
            ]);

            // 2. Kassa kirimi
            $this->cashService->recordInflow(
                cashAccountId: $cashAccountId,
                amount: $amount,
                type: 'CUSTOMER_PAYMENT',
                operationId: $payment->operation_id,
                referenceType: Payment::class,
                referenceId: $payment->id,
                description: "Mijozdan to'lov #{$paymentNumber}: {$notes}",
                userId: $userId
            );

            // 3. Mijoz qarz daftari (Credit - qarz kamayadi)
            $this->customerLedgerService->recordCredit(
                customerId: $customerId,
                amount: $amount,
                type: 'PAYMENT',
                paymentMethod: $paymentMethod,
                operationId: $payment->operation_id,
                referenceType: Payment::class,
                referenceId: $payment->id,
                notes: "To'lov qabul qilindi #{$paymentNumber}",
                userId: $userId
            );

            return $payment;
        });
    }

    /**
     * Ta'minotchiga to'lov qilish (Supplier Payment).
     * Atomik tarzda:
     * 1. Kassadan pul chiqimi (CashMovement)
     * 2. Ta'minotchi daftarida debit (SupplierLedger - qarzimiz kamayadi yoki avansimiz ko'payadi)
     * 3. Payment hujjati
     */
    public function recordSupplierPayment(
        int $supplierId,
        int $cashAccountId,
        int $amount,
        string $paymentMethod = 'CASH',
        ?string $operationId = null,
        ?string $notes = null,
        ?int $userId = null
    ): Payment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('To\'lov summasi 0 dan katta butun son bo\'lishi shart.');
        }

        return DB::transaction(function () use ($supplierId, $cashAccountId, $amount, $paymentMethod, $operationId, $notes, $userId) {
            $paymentNumber = DocumentNumberGenerator::nextPaymentNumber();

            // 1. Payment yaratish
            $payment = Payment::create([
                'payment_number' => $paymentNumber,
                'operation_id' => $operationId ?: Str::uuid()->toString(),
                'party_type' => 'SUPPLIER',
                'party_id' => $supplierId,
                'cash_account_id' => $cashAccountId,
                'payment_type' => 'SUPPLIER_PAYMENT',
                'payment_method' => strtoupper($paymentMethod),
                'direction' => 'OUT',
                'amount' => $amount,
                'notes' => $notes,
                'status' => 'COMPLETED',
                'created_by' => $userId,
            ]);

            // 2. Kassadan pul chiqimi
            $this->cashService->recordOutflow(
                cashAccountId: $cashAccountId,
                amount: $amount,
                type: 'SUPPLIER_PAYMENT',
                operationId: $payment->operation_id,
                referenceType: Payment::class,
                referenceId: $payment->id,
                description: "Ta'minotchiga to'lov #{$paymentNumber}: {$notes}",
                userId: $userId
            );

            // 3. Ta'minotchi qarz daftari (Debit - majburiyatimiz kamayadi)
            $this->supplierLedgerService->recordPaymentDebit(
                supplierId: $supplierId,
                amount: $amount,
                type: 'PAYMENT',
                paymentMethod: $paymentMethod,
                operationId: $payment->operation_id,
                referenceType: Payment::class,
                referenceId: $payment->id,
                notes: "To'lov qilindi #{$paymentNumber}",
                userId: $userId
            );

            return $payment;
        });
    }
}
