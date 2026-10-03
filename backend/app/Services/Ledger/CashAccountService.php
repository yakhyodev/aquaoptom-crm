<?php

namespace App\Services\Ledger;

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Services\Ledger\Exceptions\InsufficientCashException;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CashAccountService
{
    /**
     * Kassaga pul kirimini qayd etish.
     */
    public function recordInflow(
        int $cashAccountId,
        int $amount,
        string $type = 'SALE_PAYMENT',
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $userId = null,
        ?int $cashSessionId = null
    ): CashMovement {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Kirim summasi 0 dan katta butun son bo\'lishi shart.');
        }

        if ($cashSessionId !== null) {
            $session = CashSession::find($cashSessionId);
            if ($session && $session->status === 'CLOSED' && ! in_array($type, ['DIFFERENCE_SURPLUS', 'DIFFERENCE_SHORTAGE'], true)) {
                throw new OperationValidationException(
                    $operationId ?? (string) Str::uuid(),
                    "Yopilgan smenaga yangi operatsiya yozib bo'lmaydi!",
                    ['session_id' => $cashSessionId],
                    'CLOSED_SESSION_CANNOT_ACCEPT_OPERATIONS'
                );
            }
        } else {
            $activeSession = CashSession::where('cash_account_id', $cashAccountId)->where('status', 'OPEN')->first();
            if ($activeSession) {
                $cashSessionId = $activeSession->id;
            }
        }

        $account = CashAccount::where('id', $cashAccountId)->lockForUpdate()->firstOrFail();

        $oldBalance = (int) $account->balance;
        $newBalance = $oldBalance + $amount;

        $account->balance = $newBalance;
        $account->save();

        return CashMovement::create([
            'operation_id' => $operationId,
            'cash_account_id' => $cashAccountId,
            'cash_session_id' => $cashSessionId,
            'type' => $type,
            'direction' => 'IN',
            'debit' => $amount,
            'credit' => 0,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Kassadan pul chiqimini qayd etish.
     *
     * Invariant: Kassa qoldig'idan ortiq pul sarflanmaydi (agar allowNegative ruxsat etilmagan bo'lsa).
     */
    public function recordOutflow(
        int $cashAccountId,
        int $amount,
        string $type = 'EXPENSE',
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $userId = null,
        bool $allowNegative = false,
        ?int $cashSessionId = null
    ): CashMovement {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Chiqim summasi 0 dan katta butun son bo\'lishi shart.');
        }

        if ($cashSessionId !== null) {
            $session = CashSession::find($cashSessionId);
            if ($session && $session->status === 'CLOSED' && ! in_array($type, ['DIFFERENCE_SURPLUS', 'DIFFERENCE_SHORTAGE'], true)) {
                throw new OperationValidationException(
                    $operationId ?? (string) Str::uuid(),
                    "Yopilgan smenaga yangi operatsiya yozib bo'lmaydi!",
                    ['session_id' => $cashSessionId],
                    'CLOSED_SESSION_CANNOT_ACCEPT_OPERATIONS'
                );
            }
        } else {
            $activeSession = CashSession::where('cash_account_id', $cashAccountId)->where('status', 'OPEN')->first();
            if ($activeSession) {
                $cashSessionId = $activeSession->id;
            }
        }

        $account = CashAccount::where('id', $cashAccountId)->lockForUpdate()->firstOrFail();

        $oldBalance = (int) $account->balance;
        if (! $allowNegative && $oldBalance < $amount) {
            throw new InsufficientCashException(
                "Kassada yetarli pul mavjud emas! So'ralgan: {$amount} so'm, mavjud: {$oldBalance} so'm."
            );
        }

        $newBalance = $oldBalance - $amount;
        $account->balance = $newBalance;
        $account->save();

        return CashMovement::create([
            'operation_id' => $operationId,
            'cash_account_id' => $cashAccountId,
            'cash_session_id' => $cashSessionId,
            'type' => $type,
            'direction' => 'OUT',
            'debit' => 0,
            'credit' => $amount,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Kassa hisoblari o'rtasida pul o'tkazish (Transfer).
     * Deadlockdan himoyalanish uchun hisoblar ID lari tartiblangan ketma-ketlikda qulflanadi.
     */
    public function transfer(
        int $fromAccountId,
        int $toAccountId,
        int $amount,
        ?string $operationId = null,
        ?string $description = null,
        ?int $userId = null
    ): array {
        if ($fromAccountId === $toAccountId) {
            throw new InvalidArgumentException('Bir xil hisoblar o\'rtasida o\'tkazma qilinmaydi.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('O\'tkazma summasi 0 dan katta butun son bo\'lishi shart.');
        }

        return DB::transaction(function () use ($fromAccountId, $toAccountId, $amount, $operationId, $description, $userId) {
            // Kanonik tartibda qulflash (deadlock oldini olish)
            $firstId = min($fromAccountId, $toAccountId);
            $secondId = max($fromAccountId, $toAccountId);

            CashAccount::where('id', $firstId)->lockForUpdate()->firstOrFail();
            CashAccount::where('id', $secondId)->lockForUpdate()->firstOrFail();

            $outflow = $this->recordOutflow(
                cashAccountId: $fromAccountId,
                amount: $amount,
                type: 'TRANSFER_OUT',
                operationId: $operationId,
                referenceType: 'CashTransfer',
                referenceId: $toAccountId,
                description: $description ?: "O'tkazma: hisob #{$fromAccountId} -> #{$toAccountId}",
                userId: $userId
            );

            $inflow = $this->recordInflow(
                cashAccountId: $toAccountId,
                amount: $amount,
                type: 'TRANSFER_IN',
                operationId: $operationId,
                referenceType: 'CashTransfer',
                referenceId: $fromAccountId,
                description: $description ?: "Qabul qilindi: hisob #{$fromAccountId} dan",
                userId: $userId
            );

            return [
                'outflow' => $outflow,
                'inflow' => $inflow,
                'amount' => $amount,
            ];
        });
    }

    /**
     * Kassa hisobining cached qoldig'ini daftardagi (movements) summasi bilan tekshirish.
     */
    public function auditBalanceAgainstLedger(int $cashAccountId): array
    {
        $account = CashAccount::findOrFail($cashAccountId);
        $cachedBalance = (int) $account->balance;

        $totalDebit = (int) CashMovement::where('cash_account_id', $cashAccountId)->sum('debit');
        $totalCredit = (int) CashMovement::where('cash_account_id', $cashAccountId)->sum('credit');
        $ledgerBalance = $totalDebit - $totalCredit;

        return [
            'cash_account_id' => $cashAccountId,
            'cached_balance' => $cachedBalance,
            'ledger_balance' => $ledgerBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_consistent' => ($cachedBalance === $ledgerBalance),
        ];
    }

    /**
     * Turi bo'yicha birlamchi hisobni topish yoki yaratish (CASH, CARD, BANK).
     */
    public function getOrCreateAccount(string $type, string $name, bool $isDefault = false): CashAccount
    {
        $type = strtoupper($type);

        $account = CashAccount::where('type', $type)->first();
        if (! $account) {
            $account = CashAccount::create([
                'name' => $name,
                'type' => $type,
                'balance' => 0,
                'is_default' => $isDefault,
            ]);
        }

        return $account;
    }
}
