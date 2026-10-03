<?php

namespace App\Services\Ledger;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\TransactionalOperationService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OwnerFundsService
{
    public function __construct(
        protected CashAccountService $cashAccountService,
        protected TransactionalOperationService $transactionalOperationService
    ) {}

    /**
     * Do'kon egasi tomonidan kassaga mablag' kiritish (Owner Funding / Capital injection).
     * Invariant: Bu savdo tushumi emas, operatsion daromad deb hisoblanmaydi.
     */
    public function deposit(
        int $cashAccountId,
        int $amount,
        ?string $description = null,
        ?int $userId = null,
        ?string $operationId = null
    ): array {
        $opId = $operationId ?? (string) Str::uuid();

        if ($amount <= 0) {
            throw new OperationValidationException(
                $opId,
                "Kiritilayotgan mablag' summasi 0 dan katta butun son bo'lishi shart!",
                ['amount' => $amount],
                'INVALID_AMOUNT'
            );
        }

        $payload = [
            'type' => 'OWNER_DEPOSIT',
            'cash_account_id' => $cashAccountId,
            'amount' => $amount,
            'description' => $description,
            'user_id' => $userId,
        ];

        return $this->transactionalOperationService->execute(
            businessCallback: function () use ($cashAccountId, $amount, $description, $userId, $opId) {
                $account = CashAccount::where('id', $cashAccountId)->firstOrFail();
                $paymentNumber = DocumentNumberGenerator::nextPaymentNumber();

                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'operation_id' => $opId,
                    'party_type' => 'OWNER',
                    'party_id' => null,
                    'cash_account_id' => $cashAccountId,
                    'payment_type' => 'OWNER_DEPOSIT',
                    'payment_method' => $account->type ?? 'CASH',
                    'direction' => 'IN',
                    'amount' => $amount,
                    'notes' => $description ?: "Egasi mablag' kiritishi (Capital injection)",
                    'status' => 'COMPLETED',
                    'created_by' => $userId,
                ]);

                $movement = $this->cashAccountService->recordInflow(
                    cashAccountId: $cashAccountId,
                    amount: $amount,
                    type: 'OWNER_DEPOSIT',
                    operationId: $opId,
                    referenceType: 'Payment',
                    referenceId: $payment->id,
                    description: $description ?: "Egasi mablag' kiritishi: {$paymentNumber}",
                    userId: $userId
                );

                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'OWNER_DEPOSIT_RECORDED',
                    'auditable_type' => Payment::class,
                    'auditable_id' => $payment->id,
                    'old_values' => null,
                    'new_values' => [
                        'payment_number' => $paymentNumber,
                        'amount' => $amount,
                        'cash_account_id' => $cashAccountId,
                    ],
                    'created_at' => Carbon::now(),
                ]);

                OutboxEvent::create([
                    'event_id' => (string) Str::uuid(),
                    'operation_id' => $opId,
                    'event_name' => 'OwnerDepositRecorded',
                    'aggregate_type' => 'Payment',
                    'aggregate_id' => $payment->id,
                    'payload' => [
                        'payment_id' => $payment->id,
                        'payment_number' => $paymentNumber,
                        'amount' => $amount,
                        'cash_account_id' => $cashAccountId,
                    ],
                    'status' => 'PENDING',
                ]);

                return [
                    'payment_id' => $payment->id,
                    'payment_number' => $paymentNumber,
                    'amount' => $amount,
                    'balance_after' => $movement->balance_after,
                ];
            },
            actorId: $userId,
            operationId: $opId,
            operationType: 'OWNER_DEPOSIT',
            payload: $payload
        );
    }

    /**
     * Do'kon egasi tomonidan mablag' chiqarib olish (Owner Draw / Distribution).
     * Invariant: Egaga pul chiqarish operatsion xarajat va foyda kamayishi deb yozilmaydi!
     * Bu alohida pul harakati bo'lib, Expense modeli yaratilmaydi.
     */
    public function withdraw(
        int $cashAccountId,
        int $amount,
        ?string $description = null,
        ?int $userId = null,
        ?string $operationId = null
    ): array {
        $opId = $operationId ?? (string) Str::uuid();

        if ($amount <= 0) {
            throw new OperationValidationException(
                $opId,
                "Chiqarilayotgan mablag' summasi 0 dan katta butun son bo'lishi shart!",
                ['amount' => $amount],
                'INVALID_AMOUNT'
            );
        }

        $payload = [
            'type' => 'OWNER_DRAW',
            'cash_account_id' => $cashAccountId,
            'amount' => $amount,
            'description' => $description,
            'user_id' => $userId,
        ];

        return $this->transactionalOperationService->execute(
            businessCallback: function () use ($cashAccountId, $amount, $description, $userId, $opId) {
                $account = CashAccount::where('id', $cashAccountId)->firstOrFail();
                $paymentNumber = DocumentNumberGenerator::nextPaymentNumber();

                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'operation_id' => $opId,
                    'party_type' => 'OWNER',
                    'party_id' => null,
                    'cash_account_id' => $cashAccountId,
                    'payment_type' => 'OWNER_DRAW',
                    'payment_method' => $account->type ?? 'CASH',
                    'direction' => 'OUT',
                    'amount' => $amount,
                    'notes' => $description ?: "Egasi mablag' chiqarishi (Owner Draw)",
                    'status' => 'COMPLETED',
                    'created_by' => $userId,
                ]);

                $movement = $this->cashAccountService->recordOutflow(
                    cashAccountId: $cashAccountId,
                    amount: $amount,
                    type: 'OWNER_DRAW',
                    operationId: $opId,
                    referenceType: 'Payment',
                    referenceId: $payment->id,
                    description: $description ?: "Egasi mablag' chiqarishi (Draw): {$paymentNumber}",
                    userId: $userId,
                    allowNegative: false
                );

                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'OWNER_DRAW_RECORDED',
                    'auditable_type' => Payment::class,
                    'auditable_id' => $payment->id,
                    'old_values' => null,
                    'new_values' => [
                        'payment_number' => $paymentNumber,
                        'amount' => $amount,
                        'cash_account_id' => $cashAccountId,
                    ],
                    'created_at' => Carbon::now(),
                ]);

                OutboxEvent::create([
                    'event_id' => (string) Str::uuid(),
                    'operation_id' => $opId,
                    'event_name' => 'OwnerDrawRecorded',
                    'aggregate_type' => 'Payment',
                    'aggregate_id' => $payment->id,
                    'payload' => [
                        'payment_id' => $payment->id,
                        'payment_number' => $paymentNumber,
                        'amount' => $amount,
                        'cash_account_id' => $cashAccountId,
                    ],
                    'status' => 'PENDING',
                ]);

                return [
                    'payment_id' => $payment->id,
                    'payment_number' => $paymentNumber,
                    'amount' => $amount,
                    'balance_after' => $movement->balance_after,
                ];
            },
            actorId: $userId,
            operationId: $opId,
            operationType: 'OWNER_DRAW',
            payload: $payload
        );
    }
}
