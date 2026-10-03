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

class CashTransferService
{
    public function __construct(
        protected CashAccountService $cashAccountService,
        protected TransactionalOperationService $transactionalOperationService
    ) {}

    /**
     * Kassa hisoblari o'rtasida pul o'tkazish (Transfer).
     * Invariant: Transfer ikki hisob harakati bo'lib, yangi savdo, tushum yoki operatsion xarajat emas.
     * Deadlockdan himoyalangan va atomik.
     */
    public function transfer(
        int $fromAccountId,
        int $toAccountId,
        int $amount,
        ?string $description = null,
        ?int $userId = null,
        ?string $operationId = null
    ): array {
        $opId = $operationId ?? (string) Str::uuid();

        if ($fromAccountId === $toAccountId) {
            throw new OperationValidationException(
                $opId,
                "Bir xil hisoblar o'rtasida o'tkazma amalga oshirilmaydi!",
                ['from_account_id' => $fromAccountId, 'to_account_id' => $toAccountId],
                'SAME_ACCOUNT_TRANSFER'
            );
        }

        if ($amount <= 0) {
            throw new OperationValidationException(
                $opId,
                "O'tkazma summasi 0 dan katta butun son bo'lishi shart!",
                ['amount' => $amount],
                'INVALID_AMOUNT'
            );
        }

        $payload = [
            'type' => 'CASH_TRANSFER',
            'from_account_id' => $fromAccountId,
            'to_account_id' => $toAccountId,
            'amount' => $amount,
            'description' => $description,
            'user_id' => $userId,
        ];

        return $this->transactionalOperationService->execute(
            businessCallback: function () use ($fromAccountId, $toAccountId, $amount, $description, $userId, $opId) {
                $fromAccount = CashAccount::where('id', $fromAccountId)->firstOrFail();
                $toAccount = CashAccount::where('id', $toAccountId)->firstOrFail();

                $paymentNumberOut = DocumentNumberGenerator::nextPaymentNumber();
                $paymentNumberIn = DocumentNumberGenerator::nextPaymentNumber();

                $transferResult = $this->cashAccountService->transfer(
                    fromAccountId: $fromAccountId,
                    toAccountId: $toAccountId,
                    amount: $amount,
                    operationId: $opId,
                    description: $description ?: "O'tkazma: {$fromAccount->name} -> {$toAccount->name}",
                    userId: $userId
                );

                $paymentOut = Payment::create([
                    'payment_number' => $paymentNumberOut,
                    'operation_id' => (string) Str::uuid(),
                    'party_type' => 'NONE',
                    'party_id' => null,
                    'cash_account_id' => $fromAccountId,
                    'payment_type' => 'TRANSFER_OUT',
                    'payment_method' => $fromAccount->type ?? 'CASH',
                    'direction' => 'OUT',
                    'amount' => $amount,
                    'notes' => $description ?: "O'tkazma chiqimi: #{$toAccountId} ga",
                    'status' => 'COMPLETED',
                    'created_by' => $userId,
                ]);

                $paymentIn = Payment::create([
                    'payment_number' => $paymentNumberIn,
                    'operation_id' => (string) Str::uuid(),
                    'party_type' => 'NONE',
                    'party_id' => null,
                    'cash_account_id' => $toAccountId,
                    'payment_type' => 'TRANSFER_IN',
                    'payment_method' => $toAccount->type ?? 'CASH',
                    'direction' => 'IN',
                    'amount' => $amount,
                    'notes' => $description ?: "O'tkazma kirimi: #{$fromAccountId} dan",
                    'status' => 'COMPLETED',
                    'created_by' => $userId,
                ]);

                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'CASH_TRANSFER_RECORDED',
                    'auditable_type' => CashAccount::class,
                    'auditable_id' => $fromAccountId,
                    'old_values' => null,
                    'new_values' => [
                        'from_account_id' => $fromAccountId,
                        'to_account_id' => $toAccountId,
                        'amount' => $amount,
                    ],
                    'created_at' => Carbon::now(),
                ]);

                OutboxEvent::create([
                    'event_id' => (string) Str::uuid(),
                    'operation_id' => $opId,
                    'event_name' => 'CashTransferRecorded',
                    'aggregate_type' => 'CashAccount',
                    'aggregate_id' => $fromAccountId,
                    'payload' => [
                        'from_account_id' => $fromAccountId,
                        'to_account_id' => $toAccountId,
                        'amount' => $amount,
                    ],
                    'status' => 'PENDING',
                ]);

                return [
                    'from_account_id' => $fromAccountId,
                    'to_account_id' => $toAccountId,
                    'amount' => $amount,
                    'from_balance_after' => $transferResult['outflow']->balance_after,
                    'to_balance_after' => $transferResult['inflow']->balance_after,
                ];
            },
            actorId: $userId,
            operationId: $opId,
            operationType: 'CASH_TRANSFER',
            payload: $payload
        );
    }
}
