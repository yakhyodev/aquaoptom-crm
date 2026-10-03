<?php

namespace App\Services\Ledger;

use App\Models\Customer;
use App\Models\CustomerLedger;
use Carbon\Carbon;
use InvalidArgumentException;

class CustomerLedgerService
{
    /**
     * Mijoz qarzini ko'paytiruvchi operatsiya (Debit).
     * Masalan: Nasiya savdo, xizmat ko'rsatish, yoki boshlang'ich qarz.
     *
     * Qoida: balance_after = current_debt + amount
     */
    public function recordDebit(
        int $customerId,
        int $amount,
        string $type = 'SALE',
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null
    ): CustomerLedger {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit summasi 0 dan katta butun son bo\'lishi shart.');
        }

        $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

        $oldDebt = (int) $customer->current_debt;
        $newDebt = $oldDebt + $amount;

        $customer->current_debt = $newDebt;
        $customer->save();

        return CustomerLedger::create([
            'operation_id' => $operationId,
            'customer_id' => $customerId,
            'type' => $type,
            'debit' => $amount,
            'credit' => 0,
            'balance_after' => $newDebt,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Mijoz qarzini kamaytiruvchi yoki avans hosil qiluvchi operatsiya (Credit).
     * Masalan: To'lov qabul qilish, tovar qaytarish krediti, yoki boshlang'ich avans.
     *
     * Qat'iy Invariant: max(0, ...) taqiqlangan!
     * Agar mijoz qarzidan ortiq to'lasa, newDebt manfiy bo'ladi (-50 000 so'm avans).
     */
    public function recordCredit(
        int $customerId,
        int $amount,
        string $type = 'PAYMENT',
        ?string $paymentMethod = 'CASH',
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null
    ): CustomerLedger {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit summasi 0 dan katta butun son bo\'lishi shart.');
        }

        $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

        $oldDebt = (int) $customer->current_debt;
        $newDebt = $oldDebt - $amount; // Manfiy bo'lishi mumkin (avans!)

        $customer->current_debt = $newDebt;
        $customer->save();

        return CustomerLedger::create([
            'operation_id' => $operationId,
            'customer_id' => $customerId,
            'type' => $type,
            'payment_method' => $paymentMethod,
            'debit' => 0,
            'credit' => $amount,
            'balance_after' => $newDebt,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Mijozning signed balansini olish.
     * Musbat = qarz, manfiy = avans.
     */
    public function getSignedBalance(int $customerId): array
    {
        $customer = Customer::findOrFail($customerId);
        $debt = (int) $customer->current_debt;

        return [
            'customer_id' => $customerId,
            'balance' => $debt,
            'is_debt' => $debt > 0,
            'is_advance' => $debt < 0,
            'debt_amount' => max(0, $debt),
            'advance_amount' => abs(min(0, $debt)),
        ];
    }

    /**
     * Mijozning cached qoldig'ini daftardagi (ledger) barcha harakatlar yig'indisi bilan solishtirish.
     */
    public function auditBalanceAgainstLedger(int $customerId): array
    {
        $customer = Customer::findOrFail($customerId);
        $cachedDebt = (int) $customer->current_debt;

        $totalDebit = (int) CustomerLedger::where('customer_id', $customerId)->sum('debit');
        $totalCredit = (int) CustomerLedger::where('customer_id', $customerId)->sum('credit');
        $ledgerDebt = $totalDebit - $totalCredit;

        return [
            'customer_id' => $customerId,
            'cached_debt' => $cachedDebt,
            'ledger_debt' => $ledgerDebt,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_consistent' => ($cachedDebt === $ledgerDebt),
        ];
    }
}
