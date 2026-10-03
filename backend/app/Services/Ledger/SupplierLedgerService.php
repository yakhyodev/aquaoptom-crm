<?php

namespace App\Services\Ledger;

use App\Models\Supplier;
use App\Models\SupplierLedger;
use Carbon\Carbon;
use InvalidArgumentException;

class SupplierLedgerService
{
    /**
     * Ta'minotchi oldidagi qarzimizni oshiruvchi operatsiya (Credit).
     * Masalan: Mahsulot kirimi (Purchase) yoki boshlang'ich qarzimiz.
     *
     * Qoida: balance_after = current_balance + amount
     */
    public function recordPurchaseCredit(
        int $supplierId,
        int $amount,
        string $type = 'PURCHASE',
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null
    ): SupplierLedger {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit summasi 0 dan katta butun son bo\'lishi shart.');
        }

        $supplier = Supplier::where('id', $supplierId)->lockForUpdate()->firstOrFail();

        $oldBalance = (int) $supplier->balance;
        $newBalance = $oldBalance + $amount;

        $supplier->balance = $newBalance;
        $supplier->save();

        return SupplierLedger::create([
            'operation_id' => $operationId,
            'supplier_id' => $supplierId,
            'type' => $type,
            'debit' => 0,
            'credit' => $amount,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Ta'minotchi oldidagi qarzimizni kamaytiruvchi yoki oldindan to'lov (avans) operatsiyasi (Debit).
     * Masalan: Ta'minotchiga to'lov qilish, qaytarilgan tovar hujjati, yoki boshlang'ich avans.
     *
     * Qat'iy Invariant: max(0, ...) taqiqlangan!
     * Agar ta'minotchiga qarzdan ortiq to'lansa, newBalance manfiy bo'ladi (-100 000 so'm bizning haqdorligimiz/avansimiz).
     */
    public function recordPaymentDebit(
        int $supplierId,
        int $amount,
        string $type = 'PAYMENT',
        ?string $paymentMethod = 'CASH',
        ?string $operationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null
    ): SupplierLedger {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit summasi 0 dan katta butun son bo\'lishi shart.');
        }

        $supplier = Supplier::where('id', $supplierId)->lockForUpdate()->firstOrFail();

        $oldBalance = (int) $supplier->balance;
        $newBalance = $oldBalance - $amount; // Manfiy bo'lishi mumkin (avans!)

        $supplier->balance = $newBalance;
        $supplier->save();

        return SupplierLedger::create([
            'operation_id' => $operationId,
            'supplier_id' => $supplierId,
            'type' => $type,
            'payment_method' => $paymentMethod,
            'debit' => $amount,
            'credit' => 0,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Ta'minotchining signed balansini olish.
     * Musbat = bizning qarzimiz, manfiy = bizning avansimiz (haqdorligimiz).
     */
    public function getSignedBalance(int $supplierId): array
    {
        $supplier = Supplier::findOrFail($supplierId);
        $balance = (int) $supplier->balance;

        return [
            'supplier_id' => $supplierId,
            'balance' => $balance,
            'is_payable' => $balance > 0,
            'is_prepaid' => $balance < 0,
            'payable_amount' => max(0, $balance),
            'prepaid_amount' => abs(min(0, $balance)),
        ];
    }

    /**
     * Ta'minotchining cached qoldig'ini daftardagi (ledger) barcha harakatlar yig'indisi bilan solishtirish.
     */
    public function auditBalanceAgainstLedger(int $supplierId): array
    {
        $supplier = Supplier::findOrFail($supplierId);
        $cachedBalance = (int) $supplier->balance;

        $totalCredit = (int) SupplierLedger::where('supplier_id', $supplierId)->sum('credit');
        $totalDebit = (int) SupplierLedger::where('supplier_id', $supplierId)->sum('debit');
        $ledgerBalance = $totalCredit - $totalDebit;

        return [
            'supplier_id' => $supplierId,
            'cached_balance' => $cachedBalance,
            'ledger_balance' => $ledgerBalance,
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
            'is_consistent' => ($cachedBalance === $ledgerBalance),
        ];
    }
}
