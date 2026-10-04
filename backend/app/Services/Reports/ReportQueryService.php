<?php

namespace App\Services\Reports;

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\DamageRecord;
use App\Models\Device;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    /**
     * Savdo hisoboti (Sales Summary & Analytics)
     */
    public function getSalesSummary(array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $query = $this->buildSalesQuery($filters, $period);

        // Agregatsiyalar butun filtrlangan to'plam bo'yicha hisoblanadi
        $totalGrossSales = (int) (clone $query)->where('status', '!=', 'CANCELLED')->sum('total_amount');
        $totalInitialPaid = (int) (clone $query)->where('status', '!=', 'CANCELLED')->sum('paid_amount');
        $totalInitialDebt = (int) (clone $query)->where('status', '!=', 'CANCELLED')->sum('debt_amount');
        $totalOrdersCount = (int) (clone $query)->where('status', '!=', 'CANCELLED')->count();

        // To'lov turlari bo'yicha taqsimot
        $cashAtPos = (int) (clone $query)->where('status', '!=', 'CANCELLED')->where('payment_method', 'CASH')->sum('paid_amount');
        $cardAtPos = (int) (clone $query)->where('status', '!=', 'CANCELLED')->where('payment_method', 'CARD')->sum('paid_amount');
        $bankAtPos = (int) (clone $query)->where('status', '!=', 'CANCELLED')->where('payment_method', 'BANK')->sum('paid_amount');

        // Sotilgan tovarlar soni (dona)
        $saleIds = (clone $query)->where('status', '!=', 'CANCELLED')->pluck('id');
        $totalUnitsSold = (int) SaleItem::whereIn('sale_id', $saleIds)->sum('quantity');

        // Davr ichidagi qaytarishlar (Sale Returns)
        $returnQuery = SaleReturn::whereBetween('posted_at', [$period['start_utc'], $period['end_utc']])
            ->where('status', 'COMPLETED');
        if (! empty($filters['customer_id'])) {
            $returnQuery->where('customer_id', $filters['customer_id']);
        }
        $totalReturnsAmount = (int) (clone $returnQuery)->sum('total_amount');
        $totalReturnsCount = (int) (clone $returnQuery)->count();
        $totalRefundCash = (int) (clone $returnQuery)->sum('refund_amount');
        $totalRefundDebtReduction = (int) (clone $returnQuery)->sum('debt_deduction_amount');

        $netSales = $totalGrossSales - $totalReturnsAmount;
        $averageTicket = $totalOrdersCount > 0 ? (int) round($totalGrossSales / $totalOrdersCount) : 0;

        // Mahsulotlar / Variantlar kesimida sotuv
        $productBreakdown = [];
        if ($saleIds->isNotEmpty()) {
            $productBreakdown = DB::table('sale_items')
                ->join('product_variants', 'sale_items.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->leftJoin('volumes', 'product_variants.volume_id', '=', 'volumes.id')
                ->whereIn('sale_items.sale_id', $saleIds)
                ->select(
                    'products.name as product_name',
                    'volumes.name as volume_name',
                    'product_variants.sku',
                    'product_variants.id as variant_id',
                    DB::raw('SUM(sale_items.quantity) as units_sold'),
                    DB::raw('SUM(sale_items.line_total) as total_revenue'),
                    DB::raw('COUNT(DISTINCT sale_items.sale_id) as sales_count')
                )
                ->groupBy('products.name', 'volumes.name', 'product_variants.sku', 'product_variants.id')
                ->orderByDesc('total_revenue')
                ->limit(50)
                ->get()
                ->map(fn ($row) => [
                    'product_name' => $row->product_name,
                    'volume_name' => $row->volume_name ?: '',
                    'sku' => $row->sku,
                    'variant_id' => $row->variant_id,
                    'units_sold' => (int) $row->units_sold,
                    'total_revenue' => (int) $row->total_revenue,
                    'sales_count' => (int) $row->sales_count,
                ])
                ->toArray();
        }

        // Kunlik dinamika (Trend)
        $dailyTrend = [];
        $salesForTrend = (clone $query)->where('status', '!=', 'CANCELLED')
            ->select('id', 'total_amount', 'created_at', 'posted_at')
            ->get();

        foreach ($salesForTrend as $sale) {
            $dateKey = Carbon::parse($sale->posted_at ?: $sale->created_at)
                ->setTimezone(ReportPeriod::TIMEZONE)
                ->format('Y-m-d');

            if (! isset($dailyTrend[$dateKey])) {
                $dailyTrend[$dateKey] = [
                    'date' => $dateKey,
                    'orders_count' => 0,
                    'total_amount' => 0,
                ];
            }
            $dailyTrend[$dateKey]['orders_count']++;
            $dailyTrend[$dateKey]['total_amount'] += (int) $sale->total_amount;
        }
        ksort($dailyTrend);

        return [
            'period' => $period,
            'kpi' => [
                'total_gross_sales' => $totalGrossSales,
                'total_returns' => $totalReturnsAmount,
                'net_sales' => $netSales,
                'total_initial_paid' => $totalInitialPaid,
                'total_initial_debt' => $totalInitialDebt,
                'cash_at_pos' => $cashAtPos,
                'card_at_pos' => $cardAtPos,
                'bank_at_pos' => $bankAtPos,
                'total_orders_count' => $totalOrdersCount,
                'total_units_sold' => $totalUnitsSold,
                'average_ticket' => $averageTicket,
                'total_returns_count' => $totalReturnsCount,
                'refund_cash' => $totalRefundCash,
                'refund_debt_reduction' => $totalRefundDebtReduction,
            ],
            'product_breakdown' => $productBreakdown,
            'daily_trend' => array_values($dailyTrend),
        ];
    }

    /**
     * Yalpi va Operatsion Foyda hisoboti (Profit & Loss / Performance)
     */
    public function getProfitAndLoss(array $filters, bool $canViewCost = true): array
    {
        $period = $this->resolvePeriod($filters);

        if (! $canViewCost) {
            return [
                'period' => $period,
                'can_view_cost' => false,
                'net_sales' => null,
                'cogs' => null,
                'gross_profit' => null,
                'gross_margin_percent' => null,
                'operating_expenses' => null,
                'damage_loss' => null,
                'operating_profit' => null,
                'operating_margin_percent' => null,
            ];
        }

        // 1. Tushum (Net Sales)
        $salesQuery = $this->buildSalesQuery($filters, $period)->where('status', '!=', 'CANCELLED');
        $totalGrossSales = (int) (clone $salesQuery)->sum('total_amount');

        $returnQuery = SaleReturn::whereBetween('posted_at', [$period['start_utc'], $period['end_utc']])
            ->where('status', 'COMPLETED');
        if (! empty($filters['customer_id'])) {
            $returnQuery->where('customer_id', $filters['customer_id']);
        }
        $totalReturns = (int) (clone $returnQuery)->sum('total_amount');
        $netSales = $totalGrossSales - $totalReturns;

        // 2. Sotilgan Mahsulotlar Tannarxi (COGS)
        $saleIds = (clone $salesQuery)->pluck('id');
        $soldCost = (int) SaleItem::whereIn('sale_id', $saleIds)
            ->selectRaw('SUM(quantity * purchase_cost_snapshot) as total_cost')
            ->value('total_cost');

        // Yaroqli qaytgan tovarlar tannarxi COGS dan ayiriladi
        $returnIds = (clone $returnQuery)->pluck('id');
        $returnedCost = 0;
        if ($returnIds->isNotEmpty()) {
            $returnedCost = (int) DB::table('sale_return_items')
                ->whereIn('sale_return_id', $returnIds)
                ->where('is_damaged', false)
                ->selectRaw('SUM(quantity * purchase_cost_snapshot) as total_cost')
                ->value('total_cost');
        }
        $cogs = max(0, $soldCost - $returnedCost);

        // 3. Yalpi Foyda (Gross Profit)
        $grossProfit = $netSales - $cogs;
        $grossMarginPercent = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0;

        // 4. Operatsion Xarajatlar (Operating Expenses)
        // DIQQAT: faqat EXPENSE harakatlari, OWNER_DRAW / OWNER_WITHDRAWAL operatsion xarajat hisoblanmaydi!
        $operatingExpenses = (int) CashMovement::whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
            ->where('type', 'EXPENSE')
            ->sum('amount');

        // Xarajatlar toifalari bo'yicha taqsimot
        $expenseCategories = Expense::whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(id) as count'))
            ->groupBy('category')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category ?: 'Boshqa',
                'amount' => (int) $r->total_amount,
                'count' => (int) $r->count,
            ])
            ->toArray();

        // 5. Brak va Yaroqsiz Tovar Yo'qotishi (Damage / Scrap Loss)
        $damageLoss = (int) DamageRecord::whereBetween('posted_at', [$period['start_utc'], $period['end_utc']])
            ->where('status', 'POSTED')
            ->sum('total_loss_value');

        // 6. Sof Operatsion Foyda (Operating Profit)
        $operatingProfit = $grossProfit - $operatingExpenses - $damageLoss;
        $operatingMarginPercent = $netSales > 0 ? round(($operatingProfit / $netSales) * 100, 2) : 0;

        return [
            'period' => $period,
            'can_view_cost' => true,
            'net_sales' => $netSales,
            'gross_sales' => $totalGrossSales,
            'returns' => $totalReturns,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMarginPercent,
            'operating_expenses' => $operatingExpenses,
            'expense_categories' => $expenseCategories,
            'damage_loss' => $damageLoss,
            'operating_profit' => $operatingProfit,
            'operating_margin_percent' => $operatingMarginPercent,
        ];
    }

    /**
     * Kirim hisoboti (Purchases & Inward)
     */
    public function getPurchasesSummary(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        $query = Purchase::whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
            ->where('status', 'POSTED');

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        $totalInwardAmount = (int) (clone $query)->sum('total_amount');
        $totalPaidAmount = (int) (clone $query)->sum('paid_amount');
        $totalDebtAmount = (int) (clone $query)->sum('debt_amount');
        $inwardCount = (int) (clone $query)->count();

        $purchaseIds = (clone $query)->pluck('id');
        $totalUnitsInward = (int) DB::table('purchase_items')
            ->whereIn('purchase_id', $purchaseIds)
            ->sum('quantity');

        // Ta'minotchi qaytarishlari
        $supplierReturnQuery = PurchaseReturn::whereBetween('posted_at', [$period['start_utc'], $period['end_utc']])
            ->where('status', 'POSTED');
        if (! empty($filters['supplier_id'])) {
            $supplierReturnQuery->where('supplier_id', $filters['supplier_id']);
        }
        $totalSupplierReturnsCredit = (int) (clone $supplierReturnQuery)->sum('total_credit_amount');
        $totalSupplierReturnsCount = (int) (clone $supplierReturnQuery)->count();

        $netInwardAmount = $totalInwardAmount - $totalSupplierReturnsCredit;

        return [
            'period' => $period,
            'total_inward_amount' => $totalInwardAmount,
            'total_supplier_returns_credit' => $totalSupplierReturnsCredit,
            'net_inward_amount' => $netInwardAmount,
            'total_paid_amount' => $totalPaidAmount,
            'total_debt_amount' => $totalDebtAmount,
            'inward_count' => $inwardCount,
            'total_units_inward' => $totalUnitsInward,
            'total_supplier_returns_count' => $totalSupplierReturnsCount,
        ];
    }

    /**
     * Tarixiy Ombor Qoldig'i va Baholash Hisoboti (Stock & Value Report)
     * Qoida: Davr bosh/yakun balans ledgerdan (inventory_movements), hozirgi snapshot tarixiy balans emas.
     */
    public function getInventoryValuationReport(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        $variantsQuery = ProductVariant::with(['product', 'volume']);
        if (! empty($filters['product_id'])) {
            $variantsQuery->where('product_id', $filters['product_id']);
        }
        if (! empty($filters['product_variant_id'])) {
            $variantsQuery->where('id', $filters['product_variant_id']);
        }
        if (! empty($filters['volume_id'])) {
            $variantsQuery->where('volume_id', $filters['volume_id']);
        }

        $variants = $variantsQuery->get();

        $rows = [];
        $totalOpeningUnits = 0;
        $totalInwardUnits = 0;
        $totalOutwardUnits = 0;
        $totalAdjustmentUnits = 0;
        $totalClosingUnits = 0;
        $totalClosingValuation = 0;

        foreach ($variants as $variant) {
            // Davr boshidagi qoldiq: created_at < start_utc
            $openingUnits = (int) InventoryMovement::where('product_variant_id', $variant->id)
                ->where('created_at', '<', $period['start_utc'])
                ->sum('quantity');

            // Davr ichidagi kirimlar (quantity > 0)
            $inwardUnits = (int) InventoryMovement::where('product_variant_id', $variant->id)
                ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                ->where('quantity', '>', 0)
                ->sum('quantity');

            // Davr ichidagi chiqimlar (quantity < 0)
            $outwardUnits = (int) abs(InventoryMovement::where('product_variant_id', $variant->id)
                ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                ->where('quantity', '<', 0)
                ->whereNotIn('movement_type', ['ADJUSTMENT', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT'])
                ->sum('quantity'));

            // Davr ichidagi tuzatishlar (ADJUSTMENT_IN, ADJUSTMENT_OUT)
            $adjustmentUnits = (int) InventoryMovement::where('product_variant_id', $variant->id)
                ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                ->whereIn('movement_type', ['ADJUSTMENT', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT'])
                ->sum('quantity');

            // Davr yakunidagi qoldiq: created_at <= end_utc
            $closingUnits = (int) InventoryMovement::where('product_variant_id', $variant->id)
                ->where('created_at', '<=', $period['end_utc'])
                ->sum('quantity');

            // WAC tannarx: oxirgi harakatdagi unit_cost yoki balance_after_value / balance_after_quantity
            $latestMovement = InventoryMovement::where('product_variant_id', $variant->id)
                ->where('created_at', '<=', $period['end_utc'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $wacUnitCost = ($latestMovement && $closingUnits > 0 && $latestMovement->balance_after_value > 0)
                ? (int) round($latestMovement->balance_after_value / $closingUnits)
                : ($latestMovement ? (int) $latestMovement->unit_cost : 0);
            $closingValuation = $closingUnits > 0 ? (int) round($closingUnits * $wacUnitCost) : 0;

            $totalOpeningUnits += $openingUnits;
            $totalInwardUnits += $inwardUnits;
            $totalOutwardUnits += $outwardUnits;
            $totalAdjustmentUnits += $adjustmentUnits;
            $totalClosingUnits += $closingUnits;
            $totalClosingValuation += $closingValuation;

            $rows[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name ?? 'Noma\'lum',
                'volume_name' => $variant->volume->name ?? '',
                'sku' => $variant->sku,
                'opening_units' => $openingUnits,
                'inward_units' => $inwardUnits,
                'outward_units' => $outwardUnits,
                'adjustment_units' => $adjustmentUnits,
                'closing_units' => $closingUnits,
                'wac_cost' => $wacUnitCost,
                'closing_valuation' => $closingValuation,
            ];
        }

        return [
            'period' => $period,
            'summary' => [
                'total_opening_units' => $totalOpeningUnits,
                'total_inward_units' => $totalInwardUnits,
                'total_outward_units' => $totalOutwardUnits,
                'total_adjustment_units' => $totalAdjustmentUnits,
                'total_closing_units' => $totalClosingUnits,
                'total_closing_valuation' => $totalClosingValuation,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Taraf Ko'chirmasi (Party Statements: Customer / Supplier)
     * Qoida: Davr bosh/yakun balans ledgerdan hisoblanadi.
     */
    public function getPartyStatements(string $partyType, ?int $partyId, array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        if ($partyType === 'customer') {
            $parties = $partyId ? Customer::where('id', $partyId)->get() : Customer::all();

            $statements = [];
            foreach ($parties as $customer) {
                // Davr boshi balansi
                $openingDebits = (int) CustomerLedger::where('customer_id', $customer->id)
                    ->where('created_at', '<', $period['start_utc'])
                    ->sum('debit');
                $openingCredits = (int) CustomerLedger::where('customer_id', $customer->id)
                    ->where('created_at', '<', $period['start_utc'])
                    ->sum('credit');
                $openingBalance = $openingDebits - $openingCredits;

                // Davr ichidagi debetlar (qarz ko'payishi — masalan savdo)
                $periodDebits = (int) CustomerLedger::where('customer_id', $customer->id)
                    ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                    ->sum('debit');

                // Davr ichidagi kreditlar (qarz kamayishi — to'lov yoki qaytarish)
                $periodCredits = (int) CustomerLedger::where('customer_id', $customer->id)
                    ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                    ->sum('credit');

                // Davr yakunidagi balans
                $closingBalance = $openingBalance + $periodDebits - $periodCredits;

                // Harakatlar ro'yxati (agar alohida bitta mijoz so'ralsa)
                $movements = [];
                if ($partyId) {
                    $movements = CustomerLedger::where('customer_id', $customer->id)
                        ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                        ->orderBy('created_at')
                        ->get()
                        ->map(fn ($m) => [
                            'occurred_at' => Carbon::parse($m->created_at)->setTimezone(ReportPeriod::TIMEZONE)->format('Y-m-d H:i:s'),
                            'movement_type' => $m->type,
                            'signed_amount' => (int) ($m->debit - $m->credit),
                            'notes' => $m->notes,
                            'source_document_type' => $m->reference_type,
                        ])
                        ->toArray();
                }

                $statements[] = [
                    'party_id' => $customer->id,
                    'party_name' => $customer->name,
                    'party_phone' => $customer->phone,
                    'opening_balance' => $openingBalance,
                    'period_debits' => $periodDebits,
                    'period_credits' => $periodCredits,
                    'closing_balance' => $closingBalance,
                    'movements' => $movements,
                ];
            }

            return [
                'party_type' => 'customer',
                'period' => $period,
                'statements' => $statements,
            ];
        }

        // Ta'minotchi (Supplier)
        $suppliers = $partyId ? Supplier::where('id', $partyId)->get() : Supplier::all();

        $statements = [];
        foreach ($suppliers as $supplier) {
            // Davr boshi balansi
            $openingCredits = (int) SupplierLedger::where('supplier_id', $supplier->id)
                ->where('created_at', '<', $period['start_utc'])
                ->sum('credit');
            $openingDebits = (int) SupplierLedger::where('supplier_id', $supplier->id)
                ->where('created_at', '<', $period['start_utc'])
                ->sum('debit');
            $openingBalance = $openingCredits - $openingDebits;

            // Davr ichidagi kreditlar (ta'minotchi oldidagi qarzimiz ortishi — kirim)
            $periodCredits = (int) SupplierLedger::where('supplier_id', $supplier->id)
                ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                ->sum('credit');

            // Davr ichidagi debetlar (qarzimiz kamayishi — to'lov yoki qaytarish)
            $periodDebits = (int) SupplierLedger::where('supplier_id', $supplier->id)
                ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                ->sum('debit');

            $closingBalance = $openingBalance + $periodCredits - $periodDebits;

            $movements = [];
            if ($partyId) {
                $movements = SupplierLedger::where('supplier_id', $supplier->id)
                    ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn ($m) => [
                        'occurred_at' => Carbon::parse($m->created_at)->setTimezone(ReportPeriod::TIMEZONE)->format('Y-m-d H:i:s'),
                        'movement_type' => $m->type,
                        'signed_amount' => (int) ($m->credit - $m->debit),
                        'notes' => $m->notes,
                        'source_document_type' => $m->reference_type,
                    ])
                    ->toArray();
            }

            $statements[] = [
                'party_id' => $supplier->id,
                'party_name' => $supplier->name,
                'party_phone' => $supplier->phone,
                'opening_balance' => $openingBalance,
                'period_debits' => $periodDebits,
                'period_credits' => $periodCredits,
                'closing_balance' => $closingBalance,
                'movements' => $movements,
            ];
        }

        return [
            'party_type' => 'supplier',
            'period' => $period,
            'statements' => $statements,
        ];
    }

    /**
     * Kassa va Smena hisoboti (Cash & Sessions)
     */
    public function getCashSummary(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        $accounts = CashAccount::all();
        $accountSummaries = [];

        $totalOpeningCash = 0;
        $totalInflows = 0;
        $totalOutflows = 0;
        $totalClosingCash = 0;

        foreach ($accounts as $account) {
            // Davr boshi balansi
            $openingBalance = (int) CashMovement::where('cash_account_id', $account->id)
                ->where('created_at', '<', $period['start_utc'])
                ->selectRaw("SUM(CASE WHEN direction IN ('IN', 'INFLOW') THEN amount ELSE -amount END) as balance")
                ->value('balance');

            // Davr ichidagi kirimlar
            $inflows = (int) CashMovement::where('cash_account_id', $account->id)
                ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                ->whereIn('direction', ['IN', 'INFLOW'])
                ->sum('amount');

            // Davr ichidagi chiqimlar
            $outflows = (int) CashMovement::where('cash_account_id', $account->id)
                ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
                ->whereIn('direction', ['OUT', 'OUTFLOW'])
                ->sum('amount');

            $closingBalance = $openingBalance + $inflows - $outflows;

            $totalOpeningCash += $openingBalance;
            $totalInflows += $inflows;
            $totalOutflows += $outflows;
            $totalClosingCash += $closingBalance;

            $accountSummaries[] = [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'opening_balance' => $openingBalance,
                'inflows' => $inflows,
                'outflows' => $outflows,
                'closing_balance' => $closingBalance,
            ];
        }

        // Smenalar ro'yxati (Cash Sessions)
        $sessions = CashSession::with(['cashAccount', 'openedByUser', 'closedByUser'])
            ->whereBetween('opened_at', [$period['start_utc'], $period['end_utc']])
            ->orderByDesc('opened_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'account_name' => $s->cashAccount->name ?? 'Kassa',
                'opened_at' => Carbon::parse($s->opened_at)->setTimezone(ReportPeriod::TIMEZONE)->format('Y-m-d H:i'),
                'closed_at' => $s->closed_at ? Carbon::parse($s->closed_at)->setTimezone(ReportPeriod::TIMEZONE)->format('Y-m-d H:i') : '-',
                'opening_amount' => (int) $s->opening_amount,
                'expected_closing_amount' => (int) $s->expected_closing_amount,
                'actual_closing_amount' => (int) $s->actual_closing_amount,
                'discrepancy' => (int) $s->discrepancy,
                'discrepancy_reason' => $s->discrepancy_reason,
                'status' => $s->status,
            ])
            ->toArray();

        return [
            'period' => $period,
            'summary' => [
                'total_opening_cash' => $totalOpeningCash,
                'total_inflows' => $totalInflows,
                'total_outflows' => $totalOutflows,
                'total_closing_cash' => $totalClosingCash,
            ],
            'account_summaries' => $accountSummaries,
            'sessions' => $sessions,
        ];
    }

    /**
     * Xodimlar (Kassirlar / Sotuvchilar) faoliyati hisoboti
     */
    public function getStaffSummary(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        $sales = Sale::whereBetween('created_at', [$period['start_utc'], $period['end_utc']])
            ->where('status', '!=', 'CANCELLED')
            ->with(['creator'])
            ->get();

        $staffMap = [];
        foreach ($sales as $sale) {
            $userId = $sale->created_by ?: 0;
            $userName = $sale->creator?->name ?: 'Noma\'lum xodim';
            $userRole = $sale->creator?->role ?: 'SALES_MANAGER';

            if (! isset($staffMap[$userId])) {
                $staffMap[$userId] = [
                    'user_id' => $userId,
                    'name' => $userName,
                    'role' => $userRole,
                    'orders_count' => 0,
                    'total_amount' => 0,
                    'paid_cash' => 0,
                    'paid_card' => 0,
                    'paid_bank' => 0,
                    'debt_amount' => 0,
                ];
            }

            $staffMap[$userId]['orders_count']++;
            $staffMap[$userId]['total_amount'] += (int) $sale->total_amount;
            $staffMap[$userId]['debt_amount'] += (int) $sale->debt_amount;

            if ($sale->payment_method === 'CASH') {
                $staffMap[$userId]['paid_cash'] += (int) $sale->paid_amount;
            } elseif ($sale->payment_method === 'CARD') {
                $staffMap[$userId]['paid_card'] += (int) $sale->paid_amount;
            } elseif ($sale->payment_method === 'BANK') {
                $staffMap[$userId]['paid_bank'] += (int) $sale->paid_amount;
            }
        }

        return [
            'period' => $period,
            'staff' => array_values($staffMap),
        ];
    }

    /**
     * Offline Qurilmalar & Sinxronizatsiya Holati hisoboti
     */
    public function getSyncSummary(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        $devices = Device::all()->map(function ($device) {
            $activeAllocations = $device->inventoryAllocations()
                ->where('status', 'ACTIVE')
                ->whereRaw('(allocated_quantity - consumed_quantity - returned_quantity) > 0')
                ->get();

            $totalReservedUnits = $activeAllocations->sum(fn ($a) => $a->allocated_quantity - $a->consumed_quantity - $a->returned_quantity);

            return [
                'id' => $device->id,
                'device_code' => $device->device_code,
                'device_name' => $device->name ?: $device->device_code,
                'device_type' => $device->device_type,
                'status' => $device->status,
                'last_seen_at' => $device->last_seen_at ? Carbon::parse($device->last_seen_at)->setTimezone(ReportPeriod::TIMEZONE)->format('Y-m-d H:i:s') : '-',
                'active_allocations_count' => $activeAllocations->count(),
                'total_reserved_units' => (int) $totalReservedUnits,
                'freeze_requested' => (bool) $device->freeze_requested_at,
                'freeze_acknowledged' => (bool) $device->freeze_acknowledged_at,
            ];
        })->toArray();

        return [
            'period' => $period,
            'devices' => $devices,
        ];
    }

    /**
     * Umumiy Savdo Query quruvchisi
     */
    public function buildSalesQuery(array $filters, array $period): Builder
    {
        $query = Sale::query()
            ->whereBetween('created_at', [$period['start_utc'], $period['end_utc']]);

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['staff_id']) || ! empty($filters['user_id'])) {
            $query->where('created_by', $filters['staff_id'] ?? $filters['user_id']);
        }

        if (! empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('payment_method', $filters['payment_method'])
                    ->orWhere('payment_type', $filters['payment_method']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'ILIKE', "%{$term}%")
                    ->orWhere('notes', 'ILIKE', "%{$term}%")
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'ILIKE', "%{$term}%")
                            ->orWhere('phone', 'ILIKE', "%{$term}%");
                    });
            });
        }

        if (! empty($filters['product_id']) || ! empty($filters['product_variant_id']) || ! empty($filters['volume_id'])) {
            $query->whereHas('items', function ($iq) use ($filters) {
                if (! empty($filters['product_variant_id'])) {
                    $iq->where('product_variant_id', $filters['product_variant_id']);
                }
                if (! empty($filters['product_id']) || ! empty($filters['volume_id'])) {
                    $iq->whereHas('variant', function ($vq) use ($filters) {
                        if (! empty($filters['product_id'])) {
                            $vq->where('product_id', $filters['product_id']);
                        }
                        if (! empty($filters['volume_id'])) {
                            $vq->where('volume_id', $filters['volume_id']);
                        }
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Davrni aniqlash
     */
    protected function resolvePeriod(array $filters): array
    {
        return ReportPeriod::resolve(
            preset: $filters['period'] ?? ($filters['preset'] ?? 'today'),
            fromDate: $filters['from_date'] ?? null,
            toDate: $filters['to_date'] ?? null,
            month: $filters['month'] ?? null
        );
    }
}
