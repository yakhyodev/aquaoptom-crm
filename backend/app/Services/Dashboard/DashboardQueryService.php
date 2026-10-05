<?php

namespace App\Services\Dashboard;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SyncConflict;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    /**
     * Barcha rolga mos ko'rsatkichlarni real SQL so'rovlar bilan hisoblab qaytarish.
     */
    public function getDashboardData(
        User $user,
        string $period = 'today',
        ?string $customStart = null,
        ?string $customEnd = null
    ): array {
        $tz = SystemSetting::get('timezone', 'Asia/Tashkent');
        [$startUtc, $endUtc, $periodLabel] = $this->resolvePeriodBounds($period, $customStart, $customEnd, $tz);

        $canViewCost = $user->isOwner() || $user->hasPermission('view_cost_price');
        $lowStockThreshold = (int) SystemSetting::get('low_stock_threshold', 10);

        // 1. DAVRIY OQIM (Period Flow — aynan tanlangan davr ichida sodir bo'lgan amallar)
        $flow = $this->getPeriodFlow($startUtc, $endUtc, $canViewCost);

        // 2. JORIY AS-OF BALANSLAR (Hozirgi mavjud qoldiqlar — davrga bog'liq emas!)
        $balances = $this->getAsOfBalances($canViewCost);

        // 3. OGOHLANTIRISHLAR VA TO'LIQLIK (Warnings & Data Completeness)
        $warnings = $this->getWarningsAndCompleteness($lowStockThreshold);

        // 4. OXIRGI AMALLAR (Recent Activity Feed)
        $recentActivities = $this->getRecentActivities(12, $tz, $canViewCost);

        return [
            'period' => [
                'key' => $period,
                'label' => $periodLabel,
                'start_utc' => $startUtc->toIso8601String(),
                'end_utc' => $endUtc->toIso8601String(),
                'timezone' => $tz,
            ],
            'can_view_cost' => $canViewCost,
            'flow' => $flow,
            'balances' => $balances,
            'warnings' => $warnings,
            'recent_activities' => $recentActivities,
            'as_of_time' => Carbon::now($tz)->format('d.m.Y H:i:s'),
        ];
    }

    /**
     * Tanlangan davr chegaralarini Asia/Tashkent bo'yicha hisoblab UTC ga o'girish.
     */
    protected function resolvePeriodBounds(string $period, ?string $customStart, ?string $customEnd, string $tz): array
    {
        $now = Carbon::now($tz);

        switch ($period) {
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $label = 'Kecha ('.$start->format('d.m.Y').')';
                break;
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $label = 'Shu hafta ('.$start->format('d.m').' - '.$end->format('d.m').')';
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = 'Shu oy ('.$now->translatedFormat('F Y').')';
                break;
            case 'last_month':
                $lastM = $now->copy()->subMonth();
                $start = $lastM->copy()->startOfMonth();
                $end = $lastM->copy()->endOfMonth();
                $label = "O'tgan oy (".$lastM->translatedFormat('F Y').')';
                break;
            case 'all_time':
                $start = Carbon::create(2020, 1, 1, 0, 0, 0, $tz);
                $end = $now->copy()->endOfDay();
                $label = 'Barcha davrlar';
                break;
            case 'custom':
                $start = $customStart ? Carbon::parse($customStart, $tz)->startOfDay() : $now->copy()->startOfDay();
                $end = $customEnd ? Carbon::parse($customEnd, $tz)->endOfDay() : $now->copy()->endOfDay();
                $label = 'Oraliq: '.$start->format('d.m.Y').' — '.$end->format('d.m.Y');
                break;
            case 'today':
            default:
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $label = 'Bugun ('.$start->format('d.m.Y').')';
                break;
        }

        return [$start->copy()->utc(), $end->copy()->utc(), $label];
    }

    /**
     * Davriy Oqim (Flow) ko'rsatkichlarini hisoblash.
     */
    protected function getPeriodFlow(Carbon $startUtc, Carbon $endUtc, bool $canViewCost): array
    {
        // 1.1. Savdo aylanmasi
        $salesQuery = DB::table('sales')
            ->whereBetween('completed_at', [$startUtc, $endUtc])
            ->where('status', 'COMPLETED');

        $totalSales = (int) $salesQuery->sum('total_amount');
        $newDebt = (int) $salesQuery->sum('debt_amount');
        $salesCount = $salesQuery->count();

        // Savdo bo'yicha tannarx va yalpi foyda
        $grossProfit = null;
        $totalCost = null;
        if ($canViewCost) {
            $costSum = (int) DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->whereBetween('sales.completed_at', [$startUtc, $endUtc])
                ->where('sales.status', 'COMPLETED')
                ->sum('sale_items.cost_total');

            $totalCost = $costSum;
            $grossProfit = $totalSales - $totalCost;
        }

        // 1.2. Pul tushumi (Cash Collections)
        $totalCashCollected = (int) DB::table('cash_movements')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('direction', 'IN')
            ->whereIn('type', ['SALE_PAYMENT', 'CUSTOMER_PAYMENT'])
            ->sum('amount');

        // c) To'lov usullari bo'yicha bo'linish (cash_movements direction=IN or payments)
        $paymentBreakdown = [
            'cash' => (int) DB::table('cash_movements')
                ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_movements.cash_account_id')
                ->whereBetween('cash_movements.created_at', [$startUtc, $endUtc])
                ->where('cash_movements.direction', 'IN')
                ->whereIn('cash_movements.type', ['SALE_PAYMENT', 'CUSTOMER_PAYMENT'])
                ->where('cash_accounts.type', 'CASH')
                ->sum('cash_movements.amount'),
            'card' => (int) DB::table('cash_movements')
                ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_movements.cash_account_id')
                ->whereBetween('cash_movements.created_at', [$startUtc, $endUtc])
                ->where('cash_movements.direction', 'IN')
                ->whereIn('cash_movements.type', ['SALE_PAYMENT', 'CUSTOMER_PAYMENT'])
                ->where('cash_accounts.type', 'CARD')
                ->sum('cash_movements.amount'),
            'bank' => (int) DB::table('cash_movements')
                ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_movements.cash_account_id')
                ->whereBetween('cash_movements.created_at', [$startUtc, $endUtc])
                ->where('cash_movements.direction', 'IN')
                ->whereIn('cash_movements.type', ['SALE_PAYMENT', 'CUSTOMER_PAYMENT'])
                ->where('cash_accounts.type', 'BANK')
                ->sum('cash_movements.amount'),
        ];

        // 1.3. Operatsion xarajatlar
        $operatingExpenses = (int) DB::table('expenses')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        // 1.4. Ta'minotchilarga to'langan pul
        $supplierPaid = (int) DB::table('cash_movements')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('direction', 'OUT')
            ->where('type', 'SUPPLIER_PAYMENT')
            ->sum('amount');

        $externalCash = DB::table('cash_movements')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->whereNotIn('type', ['TRANSFER_IN', 'TRANSFER_OUT', 'OPENING_BALANCE']);

        return [
            'cash_in' => (int) (clone $externalCash)->where('direction', 'IN')->sum('amount'),
            'cash_out' => (int) (clone $externalCash)->where('direction', 'OUT')->sum('amount'),
            'total_sales' => $totalSales,
            'sales_count' => $salesCount,
            'cash_collected' => $totalCashCollected,
            'new_debt' => $newDebt,
            'gross_profit' => $grossProfit,
            'total_cost' => $totalCost,
            'operating_expenses' => $operatingExpenses,
            'supplier_paid' => $supplierPaid,
            'payments_by_method' => $paymentBreakdown,
        ];
    }

    /**
     * Joriy As-Of Balanslar (Hozirgi mavjud qoldiqlar).
     * DIQQAT: Mijozlar qarzini va avansini bir-biriga qo'shib net qilinmaydi!
     */
    protected function getAsOfBalances(bool $canViewCost): array
    {
        // 2.1. Kassalardagi tirik naqd pullar
        $cashAccounts = CashAccount::all()
            ->map(fn ($acc) => [
                'id' => $acc->id,
                'name' => $acc->name,
                'type' => $acc->type,
                'balance' => (int) $acc->balance,
            ])
            ->toArray();

        $totalCash = array_sum(array_column($cashAccounts, 'balance'));

        // 2.2. Mijozlar balansi: Alohida qarzlar va alohida avanslar
        $customerDebts = (int) Customer::where('current_debt', '>', 0)->sum('current_debt');
        $customerAdvances = (int) abs((int) Customer::where('current_debt', '<', 0)->sum('current_debt'));
        $debtorsCount = Customer::where('current_debt', '>', 0)->count();

        // 2.3. Ta'minotchilar balansi: Alohida bizning qarzimiz va alohida bizning avansimiz
        $supplierPayables = (int) Supplier::where('balance', '>', 0)->sum('balance');
        $supplierAdvances = (int) abs((int) Supplier::where('balance', '<', 0)->sum('balance'));

        // 2.4. Ombor qoldiqlari va baholash
        $totalStockUnits = (int) DB::table('inventory_balances')->sum('quantity');

        $stockCostValuation = null;
        if ($canViewCost) {
            $stockCostValuation = (int) DB::table('inventory_balances')->sum('total_value');
        }

        // Potentsial chakana qiymat (mavjud dona * tizim sotuv narxi)
        $potentialRetailValue = (int) DB::table('inventory_balances')
            ->join('product_variants', 'product_variants.id', '=', 'inventory_balances.product_variant_id')
            ->sum(DB::raw('inventory_balances.quantity * product_variants.default_sale_price'));

        $potentialGrossProfit = null;
        if ($canViewCost && $stockCostValuation !== null) {
            $potentialGrossProfit = $potentialRetailValue - $stockCostValuation;
        }

        return [
            'cash_accounts' => $cashAccounts,
            'total_cash' => $totalCash,
            'customer_debts' => $customerDebts,
            'customer_advances' => $customerAdvances,
            'debtors_count' => $debtorsCount,
            'supplier_payables' => $supplierPayables,
            'supplier_advances' => $supplierAdvances,
            'stock_units' => $totalStockUnits,
            'stock_cost_valuation' => $stockCostValuation,
            'potential_retail_value' => $potentialRetailValue,
            'potential_gross_profit' => $potentialGrossProfit,
        ];
    }

    /**
     * Ogohlantirishlar va Ma'lumotlar to'liqligi ko'rsatkichi.
     */
    protected function getWarningsAndCompleteness(int $lowStockThreshold): array
    {
        // 3.1. Kam qoldiq tovarlar
        $lowStockItems = DB::table('inventory_balances')
            ->join('product_variants', 'product_variants.id', '=', 'inventory_balances.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('volumes', 'volumes.id', '=', 'product_variants.volume_id')
            ->where('inventory_balances.quantity', '<=', $lowStockThreshold)
            ->select([
                'product_variants.id as variant_id',
                'products.name as product_name',
                'volumes.name as volume_name',
                'inventory_balances.quantity',
            ])
            ->orderBy('inventory_balances.quantity', 'asc')
            ->limit(8)
            ->get()
            ->toArray();

        $zeroStockCount = DB::table('inventory_balances')->where('quantity', '<=', 0)->count();

        // 3.2. Stale Offline Qurilmalar (24 soatdan oshgan)
        $staleCutoff = Carbon::now()->subHours(24);
        $staleDevices = Device::where('status', 'ACTIVE')
            ->where(function ($q) use ($staleCutoff) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $staleCutoff);
            })
            ->select(['id', 'name', 'device_code', 'device_type', 'last_seen_at'])
            ->get();

        // 3.3. Kredit limitidan oshgan qarzdorlar
        $overdueDebtors = Customer::where('current_debt', '>', 0)
            ->where('debt_limit', '>', 0)
            ->whereColumn('current_debt', '>', 'debt_limit')
            ->select(['id', 'name', 'phone', 'current_debt', 'debt_limit'])
            ->limit(6)
            ->get();

        // 3.4. NEEDS_REVIEW holatidagi amallar
        $needsReviewCount = SyncConflict::where('status', 'NEEDS_REVIEW')->count();

        // 3.5. Ma'lumotlar To'liqligi Ko'rsatkichi (Completeness Indicator)
        $totalActiveDevices = Device::where('status', 'ACTIVE')->count();
        $staleCount = count($staleDevices);

        $completenessPercent = 100;
        $completenessNote = "Barcha ma'lumotlar to'liq va server bilan sinxronlangan.";

        if ($totalActiveDevices > 0 && $staleCount > 0) {
            $activeSynced = $totalActiveDevices - $staleCount;
            $completenessPercent = (int) round(($activeSynced / $totalActiveDevices) * 100);
            $completenessNote = "{$staleCount} ta offline qurilma 24 soatdan beri aloqaga chiqmagan. Offline savdolar kechikkan bo'lishi mumkin.";
        }

        if ($needsReviewCount > 0) {
            $completenessPercent = max(60, $completenessPercent - ($needsReviewCount * 5));
            $completenessNote .= " {$needsReviewCount} ta amal ko'rib chiqishni kutmoqda (NEEDS_REVIEW).";
        }

        return [
            'low_stock_threshold' => $lowStockThreshold,
            'low_stock_items' => $lowStockItems,
            'low_stock_count' => count($lowStockItems),
            'zero_stock_count' => $zeroStockCount,
            'stale_devices' => $staleDevices,
            'stale_devices_count' => $staleCount,
            'overdue_debtors' => $overdueDebtors,
            'overdue_debtors_count' => count($overdueDebtors),
            'needs_review_count' => $needsReviewCount,
            'completeness_percent' => $completenessPercent,
            'completeness_note' => $completenessNote,
        ];
    }

    /**
     * Oxirgi amallar lentasi (Recent Activity Feed).
     */
    protected function getRecentActivities(int $limit, string $tz, bool $canViewCost): array
    {
        // 1. Oxirgi savdolar
        $sales = Sale::with(['customer', 'creator'])
            ->latest('completed_at')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'type' => 'SALE',
                'title' => "Sotuv: #{$s->invoice_number}",
                'party' => $s->customer?->name ?? 'Tezkor xaridor',
                'amount' => (int) $s->total_amount,
                'paid_amount' => (int) $s->paid_amount,
                'debt_amount' => (int) $s->debt_amount,
                'actor' => $s->creator?->name ?? 'Tizim',
                'time' => $s->completed_at ? Carbon::parse($s->completed_at, 'UTC')->setTimezone($tz)->format('d.m H:i') : '',
                'timestamp' => $s->completed_at?->timestamp ?? 0,
            ]);

        // 2. Oxirgi kirimlar
        $purchases = Purchase::with(['supplier', 'creator'])
            ->where('status', 'POSTED')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'type' => 'PURCHASE',
                'title' => "Kirim: #{$p->invoice_number}",
                'party' => $p->supplier?->name ?? 'Noma\'lum',
                'amount' => $canViewCost ? (int) $p->total_amount : null,
                'paid_amount' => (int) $p->paid_amount,
                'debt_amount' => (int) $p->debt_amount,
                'actor' => $p->creator?->name ?? 'Omborchi',
                'time' => $p->created_at ? Carbon::parse($p->created_at, 'UTC')->setTimezone($tz)->format('d.m H:i') : '',
                'timestamp' => $p->created_at?->timestamp ?? 0,
            ]);

        // 3. Oxirgi to'lovlar
        $payments = Payment::with(['customer', 'supplier', 'creator'])
            ->where('status', 'CONFIRMED')
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn ($pm) => [
                'type' => $pm->direction === 'IN' ? 'PAYMENT_IN' : 'PAYMENT_OUT',
                'title' => "To'lov: #{$pm->payment_number}",
                'party' => $pm->customer?->name ?? $pm->supplier?->name ?? 'Kassa to\'lovi',
                'amount' => (int) $pm->amount,
                'paid_amount' => (int) $pm->amount,
                'debt_amount' => 0,
                'actor' => $pm->creator?->name ?? 'Kassir',
                'time' => $pm->created_at ? Carbon::parse($pm->created_at, 'UTC')->setTimezone($tz)->format('d.m H:i') : '',
                'timestamp' => $pm->created_at?->timestamp ?? 0,
            ]);

        // 4. Oxirgi xarajatlar
        $expenses = Expense::with(['creator'])
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn ($ex) => [
                'type' => 'EXPENSE',
                'title' => "Xarajat: {$ex->category}",
                'party' => $ex->description ?: 'Operatsion xarajat',
                'amount' => (int) $ex->amount,
                'paid_amount' => (int) $ex->amount,
                'debt_amount' => 0,
                'actor' => $ex->creator?->name ?? 'Moliya',
                'time' => $ex->created_at ? Carbon::parse($ex->created_at, 'UTC')->setTimezone($tz)->format('d.m H:i') : '',
                'timestamp' => $ex->created_at?->timestamp ?? 0,
            ]);

        $all = $sales->concat($purchases)->concat($payments)->concat($expenses);

        return $all->sortByDesc('timestamp')->take($limit)->values()->toArray();
    }
}
