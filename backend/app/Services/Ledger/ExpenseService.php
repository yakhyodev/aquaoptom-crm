<?php

namespace App\Services\Ledger;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\TransactionalOperationService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ExpenseService
{
    public const ALLOWED_CATEGORIES = [
        'RENT',
        'SALARY',
        'TRANSPORT',
        'UTILITIES',
        'UNLOADING',
        'OTHER',
    ];

    public function __construct(
        protected CashAccountService $cashAccountService,
        protected TransactionalOperationService $transactionalOperationService
    ) {}

    /**
     * Operatsion xarajatni qayd etish.
     * Atomik, idempotentsiya himoyasi bilan (bir xil operation_id retry bitta xarajat yaratadi).
     */
    public function createExpense(
        int $cashAccountId,
        int $amount,
        string $category,
        ?string $description = null,
        ?int $userId = null,
        ?string $operationId = null
    ): array {
        $opId = $operationId ?? (string) Str::uuid();
        $normalizedCategory = strtoupper(trim($category));

        if (! in_array($normalizedCategory, self::ALLOWED_CATEGORIES, true)) {
            throw new OperationValidationException(
                $opId,
                "Noto'g'ri xarajat toifasi! Ruxsat etilganlar: ".implode(', ', self::ALLOWED_CATEGORIES),
                ['category' => $category, 'allowed' => self::ALLOWED_CATEGORIES],
                'INVALID_EXPENSE_CATEGORY'
            );
        }

        if ($amount <= 0) {
            throw new OperationValidationException(
                $opId,
                "Xarajat summasi 0 dan katta butun son bo'lishi shart!",
                ['amount' => $amount],
                'INVALID_EXPENSE_AMOUNT'
            );
        }

        $payload = [
            'type' => 'EXPENSE',
            'cash_account_id' => $cashAccountId,
            'amount' => $amount,
            'category' => $normalizedCategory,
            'description' => $description,
            'user_id' => $userId,
        ];

        return $this->transactionalOperationService->execute(
            businessCallback: function () use ($cashAccountId, $amount, $normalizedCategory, $description, $userId, $opId) {
                $account = CashAccount::where('id', $cashAccountId)->firstOrFail();

                $expenseNumber = DocumentNumberGenerator::nextExpenseNumber();
                $paymentNumber = DocumentNumberGenerator::nextPaymentNumber();

                $expense = Expense::create([
                    'operation_id' => $opId,
                    'expense_number' => $expenseNumber,
                    'category' => $normalizedCategory,
                    'cash_account_id' => $cashAccountId,
                    'amount' => $amount,
                    'description' => $description,
                    'created_by' => $userId,
                ]);

                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'operation_id' => $opId,
                    'party_type' => 'EXPENSE',
                    'party_id' => $expense->id,
                    'cash_account_id' => $cashAccountId,
                    'payment_type' => 'EXPENSE',
                    'payment_method' => $account->type ?? 'CASH',
                    'direction' => 'OUT',
                    'amount' => $amount,
                    'notes' => $description ?: "Operatsion xarajat: {$normalizedCategory}",
                    'status' => 'COMPLETED',
                    'created_by' => $userId,
                ]);

                $movement = $this->cashAccountService->recordOutflow(
                    cashAccountId: $cashAccountId,
                    amount: $amount,
                    type: 'EXPENSE',
                    operationId: $opId,
                    referenceType: 'Expense',
                    referenceId: $expense->id,
                    description: "Xarajat [{$normalizedCategory}]: ".($description ?: $expenseNumber),
                    userId: $userId,
                    allowNegative: false
                );

                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'EXPENSE_RECORDED',
                    'auditable_type' => Expense::class,
                    'auditable_id' => $expense->id,
                    'old_values' => null,
                    'new_values' => [
                        'expense_number' => $expenseNumber,
                        'category' => $normalizedCategory,
                        'amount' => $amount,
                        'cash_account_id' => $cashAccountId,
                    ],
                    'created_at' => Carbon::now(),
                ]);

                OutboxEvent::create([
                    'event_id' => (string) Str::uuid(),
                    'operation_id' => $opId,
                    'event_name' => 'ExpenseRecorded',
                    'aggregate_type' => 'Expense',
                    'aggregate_id' => $expense->id,
                    'payload' => [
                        'expense_id' => $expense->id,
                        'expense_number' => $expenseNumber,
                        'category' => $normalizedCategory,
                        'amount' => $amount,
                        'payment_number' => $paymentNumber,
                    ],
                    'status' => 'PENDING',
                ]);

                return [
                    'expense_id' => $expense->id,
                    'expense_number' => $expenseNumber,
                    'payment_id' => $payment->id,
                    'payment_number' => $paymentNumber,
                    'amount' => $amount,
                    'balance_after' => $movement->balance_after,
                ];
            },
            actorId: $userId,
            operationId: $opId,
            operationType: 'EXPENSE',
            payload: $payload
        );
    }
}
