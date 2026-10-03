<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\SupplierLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\TransactionalOperationService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SupplierPaymentService
{
    public function __construct(
        protected TransactionalOperationService $operationService,
        protected SupplierLedgerService $supplierLedgerService,
        protected CashAccountService $cashAccountService
    ) {}

    /**
     * Ta'minotchi oldidagi qarzni to'lash (pul chiqimi).
     *
     * Qat'iy qoidalar:
     * - Ta'minotchi to'lovi ombor tovar qoldig'iga (stock / inventory_movements) aslo tegmaydi!
     * - Kassadagi pul yetarliligi tranzaksiya ichida tekshiriladi va xatolikda to'liq rollback bo'ladi.
     * - Qarzdan ortiq to'lov qilinganda ta'minotchi oldidagi avansimiz (manfiy signed balans) hosil bo'ladi.
     */
    public function execute(
        int $supplierId,
        int $amount,
        int $cashAccountId,
        string $paymentMethod = 'CASH',
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null,
        bool $confirmExcessAsAdvance = false,
        mixed $happenedAt = null
    ): array {
        $operationId = $operationId ?: (string) Str::uuid();

        // 1. Huquq tekshiruvi (Permission Guard)
        if ($userId) {
            $user = User::find($userId);
            if ($user && ! $user->hasRole(['OWNER', 'ADMIN', 'CASHIER']) && ! $user->hasPermission('manage_cash_outflow')) {
                throw new OperationPermissionException(
                    operationId: $operationId,
                    message: "Sizda kassadan ta'minotchiga pul to'lash ruxsati mavjud emas!",
                    details: ['error_code' => 'NO_CASH_OUTFLOW_PERMISSION']
                );
            }
        }

        // 2. Summa validatsiyasi
        if ($amount <= 0) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "To'lov summasi 0 dan katta butun so'm bo'lishi shart!",
                errorCode: 'INVALID_PAYMENT_AMOUNT'
            );
        }

        // 3. Kanonik payload (Idempotency barqarorligi uchun)
        $canonicalPayload = [
            'supplier_id' => $supplierId,
            'amount' => $amount,
            'cash_account_id' => $cashAccountId,
            'payment_method' => strtoupper($paymentMethod),
        ];

        return $this->operationService->execute(
            operationId: $operationId,
            operationType: 'SUPPLIER_PAYMENT',
            payload: $canonicalPayload,
            businessCallback: function () use (
                $supplierId,
                $amount,
                $cashAccountId,
                $paymentMethod,
                $operationId,
                $userId,
                $notes,
                $confirmExcessAsAdvance,
                $happenedAt
            ) {
                // Supplier va CashAccount ni atomik qulflash
                $supplier = Supplier::where('id', $supplierId)->lockForUpdate()->first();
                if (! $supplier) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Ta'minotchi (#{$supplierId}) tizimda topilmadi!",
                        errorCode: 'SUPPLIER_NOT_FOUND'
                    );
                }

                $cashAccount = CashAccount::where('id', $cashAccountId)->lockForUpdate()->first();
                if (! $cashAccount) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Kassa hisobi (#{$cashAccountId}) tizimda topilmadi!",
                        errorCode: 'CASH_ACCOUNT_NOT_FOUND'
                    );
                }

                // Kassada yetarli pul borligini tekshirish
                $currentCash = (int) $cashAccount->balance;
                if ($currentCash < $amount) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Kassada yetarli mablag' mavjud emas! So'ralgan: {$amount} so'm, mavjud kassa qoldig'i: {$currentCash} so'm.",
                        errorCode: 'INSUFFICIENT_CASH',
                        details: [
                            'cash_account_id' => $cashAccountId,
                            'required_amount' => $amount,
                            'available_balance' => $currentCash,
                        ]
                    );
                }

                $currentPayable = (int) $supplier->balance;

                // Qarzdan ortiq to'lov tekshiruvi (Advance confirmation guard)
                if ($currentPayable > 0 && $amount > $currentPayable && ! $confirmExcessAsAdvance) {
                    $excessAmount = $amount - $currentPayable;
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Kiritilgan summa ({$amount} so'm) ta'minotchi oldidagi joriy qarzimizdan ({$currentPayable} so'm) ortiq! Ortiqcha {$excessAmount} so'm avans sifatida to'lanishini tasdiqlang.",
                        errorCode: 'EXCESS_PAYMENT_REQUIRES_ADVANCE_CONFIRMATION',
                        details: [
                            'current_payable' => $currentPayable,
                            'payment_amount' => $amount,
                            'excess_amount' => $excessAmount,
                        ]
                    );
                }

                $paymentNumber = DocumentNumberGenerator::nextPaymentNumber();
                $eventTime = $happenedAt ? Carbon::parse($happenedAt) : Carbon::now();

                // 4. Payment hujjati
                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'operation_id' => $operationId,
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
                    'created_at' => $eventTime,
                    'updated_at' => Carbon::now(),
                ]);

                // 5. Kassadan pul chiqimi (Cash Outflow)
                $cashMovement = $this->cashAccountService->recordOutflow(
                    cashAccountId: $cashAccountId,
                    amount: $amount,
                    type: 'SUPPLIER_PAYMENT',
                    operationId: $operationId,
                    referenceType: Payment::class,
                    referenceId: $payment->id,
                    description: "Ta'minotchiga to'lov: {$supplier->name} (#{$paymentNumber})",
                    userId: $userId
                );

                // 6. Ta'minotchi daftari (Debit: qarzimiz kamayishi yoki avansimiz oshishi)
                // QAT'IY QOIDA: Bu yerda stock / ombor qoldig'iga mutlaqo tegilmaydi!
                $ledgerEntry = $this->supplierLedgerService->recordPaymentDebit(
                    supplierId: $supplierId,
                    amount: $amount,
                    type: 'PAYMENT',
                    paymentMethod: strtoupper($paymentMethod),
                    operationId: $operationId,
                    referenceType: Payment::class,
                    referenceId: $payment->id,
                    notes: $notes ?? "Ta'minotchiga to'lov #{$paymentNumber}",
                    userId: $userId
                );

                $newBalance = (int) $supplier->fresh()->balance;

                // 7. Audit Log
                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'supplier_payment',
                    'entity_type' => Payment::class,
                    'entity_id' => $payment->id,
                    'old_values' => ['payable' => $currentPayable],
                    'new_values' => [
                        'payable' => $newBalance,
                        'paid_amount' => $amount,
                        'payment_number' => $paymentNumber,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'created_at' => Carbon::now(),
                ]);

                // 8. Outbox Event
                OutboxEvent::create([
                    'event_id' => (string) Str::uuid(),
                    'operation_id' => $operationId,
                    'event_name' => 'supplier_payment_completed',
                    'aggregate_type' => 'payment',
                    'aggregate_id' => (string) $payment->id,
                    'payload' => [
                        'operation_id' => $operationId,
                        'payment_id' => $payment->id,
                        'payment_number' => $paymentNumber,
                        'supplier_id' => $supplierId,
                        'amount' => $amount,
                        'new_balance' => $newBalance,
                        'user_id' => $userId,
                    ],
                    'status' => 'PENDING',
                    'created_at' => Carbon::now(),
                ]);

                return [
                    'payment_id' => $payment->id,
                    'payment_number' => $paymentNumber,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplier->name,
                    'amount' => $amount,
                    'previous_payable' => $currentPayable,
                    'new_payable' => $newBalance,
                    'is_advance' => $newBalance < 0,
                    'advance_amount' => $newBalance < 0 ? abs($newBalance) : 0,
                    'cash_account_id' => $cashAccountId,
                    'cash_account_name' => $cashAccount->name,
                    'cash_balance_after' => (int) $cashAccount->fresh()->balance,
                    'created_at' => $eventTime->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                ];
            },
            actorId: $userId
        );
    }
}
