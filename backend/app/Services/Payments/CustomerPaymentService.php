<?php

namespace App\Services\Payments;

use App\Events\PaymentRecordedBroadcastEvent;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\User;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\CustomerLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\TransactionalOperationService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CustomerPaymentService
{
    public function __construct(
        protected TransactionalOperationService $operationService,
        protected CustomerLedgerService $customerLedgerService,
        protected CashAccountService $cashAccountService
    ) {}

    /**
     * Mijozdan qarz to'lovini qabul qilish.
     *
     * Qat'iy qoidalar:
     * - Keyingi to'lov mahsulot yoki cheklarga majburiy taqsimlanmaydi; umumiy qarzni kamaytiradi.
     * - Oldingi savdo summasi, tannarx va uning yalpi foydasi (gross profit) aslo o'zgarmaydi.
     * - Qarzdan ortiq to'lov aniq tasdiqlansa, mijoz avansi (manfiy signed balans) hosil qiladi.
     * - max(0) taqiqlangan!
     */
    public function execute(
        int $customerId,
        int $amount,
        int $cashAccountId,
        string $paymentMethod = 'CASH',
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null,
        bool $confirmExcessAsAdvance = false,
        mixed $happenedAt = null,
        ?array $rawPayload = null
    ): array {
        $operationId = $operationId ?: (string) Str::uuid();

        // 1. Huquq tekshiruvi (Permission Guard)
        if ($userId) {
            $user = User::find($userId);
            if ($user && ! $user->hasRole(['OWNER', 'ADMIN', 'SALES_MANAGER', 'CASHIER']) && ! $user->hasPermission('view_debts')) {
                throw new OperationPermissionException(
                    operationId: $operationId,
                    message: "Sizda qarz to'lovlarini qabul qilish huquqi mavjud emas!",
                    details: ['error_code' => 'NO_DEBT_PAYMENT_PERMISSION']
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
            'customer_id' => $customerId,
            'amount' => $amount,
            'cash_account_id' => $cashAccountId,
            'payment_method' => strtoupper($paymentMethod),
        ];

        $result = $this->operationService->execute(
            operationId: $operationId,
            operationType: 'CUSTOMER_PAYMENT',
            payload: $rawPayload ?? $canonicalPayload,
            businessCallback: function () use (
                $customerId,
                $amount,
                $cashAccountId,
                $paymentMethod,
                $operationId,
                $userId,
                $notes,
                $confirmExcessAsAdvance,
                $happenedAt
            ) {
                // Customer va CashAccount ni atomik qulflash
                $customer = Customer::where('id', $customerId)->lockForUpdate()->first();
                if (! $customer) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Mijoz (#{$customerId}) tizimda topilmadi!",
                        errorCode: 'CUSTOMER_NOT_FOUND'
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

                $currentDebt = (int) $customer->current_debt;

                // Qarzdan ortiq to'lov tekshiruvi (Advance confirmation guard)
                if ($currentDebt > 0 && $amount > $currentDebt && ! $confirmExcessAsAdvance) {
                    $excessAmount = $amount - $currentDebt;
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Kiritilgan summa ({$amount} so'm) mijozning joriy qarzidan ({$currentDebt} so'm) ortiq! Ortiqcha {$excessAmount} so'm avans sifatida saqlanishini tasdiqlang.",
                        errorCode: 'EXCESS_PAYMENT_REQUIRES_ADVANCE_CONFIRMATION',
                        details: [
                            'current_debt' => $currentDebt,
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
                    'created_at' => $eventTime,
                    'updated_at' => Carbon::now(),
                ]);

                // 5. Kassa kirimi (Cash Inflow)
                $cashMovement = $this->cashAccountService->recordInflow(
                    cashAccountId: $cashAccountId,
                    amount: $amount,
                    type: 'CUSTOMER_PAYMENT',
                    operationId: $operationId,
                    referenceType: Payment::class,
                    referenceId: $payment->id,
                    description: "Mijozdan to'lov: {$customer->name} (#{$paymentNumber})",
                    userId: $userId
                );

                // 6. Mijoz daftari (Credit: qarz kamayishi yoki avans oshishi)
                $ledgerEntry = $this->customerLedgerService->recordCredit(
                    customerId: $customerId,
                    amount: $amount,
                    type: 'PAYMENT',
                    paymentMethod: strtoupper($paymentMethod),
                    operationId: $operationId,
                    referenceType: Payment::class,
                    referenceId: $payment->id,
                    notes: $notes ?? "Qarz to'lovi #{$paymentNumber}",
                    userId: $userId
                );

                $newBalance = (int) $customer->fresh()->current_debt;

                // 7. Audit Log
                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'customer_payment',
                    'entity_type' => Payment::class,
                    'entity_id' => $payment->id,
                    'old_values' => ['current_debt' => $currentDebt],
                    'new_values' => [
                        'current_debt' => $newBalance,
                        'paid_amount' => $amount,
                        'payment_number' => $paymentNumber,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'created_at' => Carbon::now(),
                ]);

                // 8. Outbox Event (Hodisalar navbati)
                OutboxEvent::create([
                    'event_id' => (string) Str::uuid(),
                    'operation_id' => $operationId,
                    'event_name' => 'customer_payment_completed',
                    'aggregate_type' => 'payment',
                    'aggregate_id' => (string) $payment->id,
                    'payload' => [
                        'operation_id' => $operationId,
                        'payment_id' => $payment->id,
                        'payment_number' => $paymentNumber,
                        'customer_id' => $customerId,
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
                    'customer_id' => $customerId,
                    'customer_name' => $customer->name,
                    'amount' => $amount,
                    'previous_debt' => $currentDebt,
                    'new_debt' => $newBalance,
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

        if (isset($result['payment_id'])) {
            $payment = Payment::find($result['payment_id']);
            if ($payment) {
                try {
                    broadcast(new PaymentRecordedBroadcastEvent($payment));
                } catch (\Throwable $e) {
                    // Broadcasting xatosi commit bo'lgan to'lovni buzmaydi
                }
            }
        }

        return $result;
    }
}
