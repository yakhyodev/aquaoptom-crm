<?php

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Carbon\Carbon;

class StatementService
{
    /**
     * Mijozning hisob ko'chirmasini (Account Statement) generatsiya qilish.
     *
     * Qat'iy Invariant:
     * boshlang'ich_qoldiq + jami_debitlar - jami_creditlar == yakuniy_qoldiq
     * Vaqtlar sekund aniqligida Asia/Tashkent formatida qaytariladi.
     */
    public function getCustomerStatement(int $customerId, ?string $startDate = null, ?string $endDate = null): array
    {
        $customer = Customer::findOrFail($customerId);

        // Sana chegaralari (Asia/Tashkent bo'yicha)
        $startTashkent = $startDate
            ? Carbon::parse($startDate, 'Asia/Tashkent')->startOfDay()
            : Carbon::now('Asia/Tashkent')->startOfMonth();

        $endTashkent = $endDate
            ? Carbon::parse($endDate, 'Asia/Tashkent')->endOfDay()
            : Carbon::now('Asia/Tashkent')->endOfDay();

        $startUtc = $startTashkent->copy()->utc();
        $endUtc = $endTashkent->copy()->utc();

        // 1. Davr boshidagi qoldiq (Opening Balance)
        // Davr boshlanishidan oldingi barcha harakatlar yig'indisi: SUM(debit) - SUM(credit)
        $priorDebits = (int) CustomerLedger::where('customer_id', $customerId)
            ->where('created_at', '<', $startUtc)
            ->sum('debit');

        $priorCredits = (int) CustomerLedger::where('customer_id', $customerId)
            ->where('created_at', '<', $startUtc)
            ->sum('credit');

        $openingBalance = $priorDebits - $priorCredits;

        // 2. Davrdagi harakatlar (Movements)
        $ledgerEntries = CustomerLedger::where('customer_id', $customerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with(['creator'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $movements = [];
        $runningBalance = $openingBalance;
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($ledgerEntries as $entry) {
            $debit = (int) $entry->debit;
            $credit = (int) $entry->credit;
            $runningBalance += ($debit - $credit);
            $totalDebit += $debit;
            $totalCredit += $credit;

            $itemsSummary = [];
            $documentNumber = null;
            $goodsPickedUpAt = null;

            if ($entry->type === 'SALE' && $entry->reference_id) {
                $sale = Sale::with(['items.variant.product', 'items.variant.volume'])->find($entry->reference_id);
                if ($sale) {
                    $documentNumber = $sale->invoice_number;
                    if ($sale->goods_picked_up_at) {
                        $goodsPickedUpAt = $sale->goods_picked_up_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s');
                    }
                    foreach ($sale->items as $item) {
                        $pName = $item->variant?->product?->name ?? 'Noma\'lum tovar';
                        $vName = $item->variant?->volume?->name ?? '';
                        $itemsSummary[] = [
                            'product_name' => $pName,
                            'volume_name' => $vName,
                            'display_name' => "{$pName} ({$vName})",
                            'quantity' => (int) $item->quantity,
                            'unit_price' => (int) $item->sale_price,
                            'total' => (int) $item->line_total,
                        ];
                    }
                }
            } elseif ($entry->type === 'PAYMENT' && $entry->reference_id) {
                $payment = Payment::with('account')->find($entry->reference_id);
                if ($payment) {
                    $documentNumber = $payment->payment_number;
                }
            }

            $movements[] = [
                'id' => $entry->id,
                'operation_id' => $entry->operation_id,
                'created_at' => $entry->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                'server_time' => $entry->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                'event_time' => $entry->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                'goods_picked_up_at' => $goodsPickedUpAt,
                'type' => $entry->type,
                'type_label' => match ($entry->type) {
                    'SALE' => 'Savdo (Nasiya)',
                    'PAYMENT' => 'Qarz to\'lovi',
                    'RETURN' => 'Tovar qaytarish',
                    'OPENING_BALANCE' => 'Boshlang\'ich qoldiq',
                    default => $entry->type,
                },
                'payment_method' => $entry->payment_method,
                'document_number' => $documentNumber,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => (int) $entry->balance_after,
                'calculated_running_balance' => $runningBalance,
                'actor_name' => $entry->creator?->name ?? 'Tizim',
                'notes' => $entry->notes,
                'items_summary' => $itemsSummary,
            ];
        }

        $closingBalance = $openingBalance + $totalDebit - $totalCredit;

        return [
            'party_type' => 'CUSTOMER',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'store_name' => $customer->store_name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'current_debt' => (int) $customer->current_debt,
                'debt_limit' => (int) $customer->debt_limit,
                'payment_due_date' => $customer->payment_due_date?->format('Y-m-d'),
                'is_debt' => $customer->current_debt > 0,
                'is_advance' => $customer->current_debt < 0,
                'is_overdue' => $customer->current_debt > 0 && $customer->payment_due_date && Carbon::parse($customer->payment_due_date)->isPast(),
            ],
            'period' => [
                'start_date' => $startTashkent->format('Y-m-d H:i:s'),
                'end_date' => $endTashkent->format('Y-m-d H:i:s'),
                'timezone' => 'Asia/Tashkent',
            ],
            'opening_balance' => $openingBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $closingBalance,
            'current_balance' => (int) $customer->current_debt,
            'is_reconciled' => ($openingBalance + $totalDebit - $totalCredit === $closingBalance),
            'movements' => $movements,
        ];
    }

    /**
     * Ta'minotchining hisob ko'chirmasini (Account Statement) generatsiya qilish.
     *
     * Qat'iy Invariant:
     * boshlang'ich_qoldiq + jami_creditlar - jami_debitlar == yakuniy_qoldiq
     * Credit = Tovar kirimi (qarzimiz oshadi)
     * Debit = Bizning to'lovimiz (qarzimiz kamayadi)
     */
    public function getSupplierStatement(int $supplierId, ?string $startDate = null, ?string $endDate = null): array
    {
        $supplier = Supplier::findOrFail($supplierId);

        // Sana chegaralari (Asia/Tashkent)
        $startTashkent = $startDate
            ? Carbon::parse($startDate, 'Asia/Tashkent')->startOfDay()
            : Carbon::now('Asia/Tashkent')->startOfMonth();

        $endTashkent = $endDate
            ? Carbon::parse($endDate, 'Asia/Tashkent')->endOfDay()
            : Carbon::now('Asia/Tashkent')->endOfDay();

        $startUtc = $startTashkent->copy()->utc();
        $endUtc = $endTashkent->copy()->utc();

        // 1. Davr boshidagi qoldiq (Opening Balance)
        // Ta'minotchi uchun: SUM(credit) - SUM(debit)
        $priorCredits = (int) SupplierLedger::where('supplier_id', $supplierId)
            ->where('created_at', '<', $startUtc)
            ->sum('credit');

        $priorDebits = (int) SupplierLedger::where('supplier_id', $supplierId)
            ->where('created_at', '<', $startUtc)
            ->sum('debit');

        $openingBalance = $priorCredits - $priorDebits;

        // 2. Davrdagi harakatlar (Movements)
        $ledgerEntries = SupplierLedger::where('supplier_id', $supplierId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with(['creator'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $movements = [];
        $runningBalance = $openingBalance;
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($ledgerEntries as $entry) {
            $debit = (int) $entry->debit;
            $credit = (int) $entry->credit;
            $runningBalance += ($credit - $debit);
            $totalDebit += $debit;
            $totalCredit += $credit;

            $itemsSummary = [];
            $documentNumber = null;

            if ($entry->type === 'PURCHASE' && $entry->reference_id) {
                $purchase = Purchase::with(['items.variant.product', 'items.variant.volume'])->find($entry->reference_id);
                if ($purchase) {
                    $documentNumber = $purchase->invoice_number;
                    foreach ($purchase->items as $item) {
                        $pName = $item->variant?->product?->name ?? 'Noma\'lum tovar';
                        $vName = $item->variant?->volume?->name ?? '';
                        $itemsSummary[] = [
                            'product_name' => $pName,
                            'volume_name' => $vName,
                            'display_name' => "{$pName} ({$vName})",
                            'quantity' => (int) $item->quantity,
                            'unit_price' => (int) $item->unit_cost,
                            'total' => (int) $item->total_cost,
                        ];
                    }
                }
            } elseif ($entry->type === 'PAYMENT' && $entry->reference_id) {
                $payment = Payment::with('account')->find($entry->reference_id);
                if ($payment) {
                    $documentNumber = $payment->payment_number;
                }
            }

            $movements[] = [
                'id' => $entry->id,
                'operation_id' => $entry->operation_id,
                'created_at' => $entry->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                'server_time' => $entry->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                'event_time' => $entry->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'),
                'type' => $entry->type,
                'type_label' => match ($entry->type) {
                    'PURCHASE' => 'Kirim hujjati',
                    'PAYMENT' => 'Ta\'minotchi to\'lovi',
                    'RETURN' => 'Tovar qaytarish',
                    'OPENING_BALANCE' => 'Boshlang\'ich qoldiq',
                    default => $entry->type,
                },
                'payment_method' => $entry->payment_method,
                'document_number' => $documentNumber,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'debit' => $debit, // Bizning to'lov
                'credit' => $credit, // Kirim tovar
                'balance_after' => (int) $entry->balance_after,
                'calculated_running_balance' => $runningBalance,
                'actor_name' => $entry->creator?->name ?? 'Tizim',
                'notes' => $entry->notes,
                'items_summary' => $itemsSummary,
            ];
        }

        $closingBalance = $openingBalance + $totalCredit - $totalDebit;

        return [
            'party_type' => 'SUPPLIER',
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'balance' => (int) $supplier->balance,
                'credit_limit' => (int) $supplier->credit_limit,
                'payment_due_date' => $supplier->payment_due_date?->format('Y-m-d'),
                'is_payable' => $supplier->balance > 0,
                'is_prepaid' => $supplier->balance < 0,
                'is_overdue' => $supplier->balance > 0 && $supplier->payment_due_date && Carbon::parse($supplier->payment_due_date)->isPast(),
            ],
            'period' => [
                'start_date' => $startTashkent->format('Y-m-d H:i:s'),
                'end_date' => $endTashkent->format('Y-m-d H:i:s'),
                'timezone' => 'Asia/Tashkent',
            ],
            'opening_balance' => $openingBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $closingBalance,
            'current_balance' => (int) $supplier->balance,
            'is_reconciled' => ($openingBalance + $totalCredit - $totalDebit === $closingBalance),
            'movements' => $movements,
        ];
    }
}
