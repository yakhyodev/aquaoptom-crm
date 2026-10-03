<?php

use Livewire\Component;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    public string $period = 'today'; // 'today', 'month', 'all'
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    // Yangi Xarajat
    public string $expenseCategory = 'Transport / Yetkazib berish';
    public ?int $expenseAmount = null;
    public ?int $expenseAccountId = null;
    public string $expenseDescription = '';

    // Qarz to'lash
    public ?int $payingCustomerId = null;
    public ?int $repaymentAmount = null;
    public ?int $repaymentAccountId = null;
    public string $repaymentNotes = '';

    public function mount()
    {
        $defaultAccount = CashAccount::where('is_default', true)->first();
        if ($defaultAccount) {
            $this->expenseAccountId = $defaultAccount->id;
            $this->repaymentAccountId = $defaultAccount->id;
        }
    }

    public function addExpense()
    {
        $this->validate([
            'expenseCategory' => 'required|string',
            'expenseAmount' => 'required|integer|min:100',
            'expenseAccountId' => 'required|exists:cash_accounts,id',
            'expenseDescription' => 'nullable|string|max:255',
        ]);

        $account = CashAccount::find($this->expenseAccountId);
        if (!$account || $account->balance < $this->expenseAmount) {
            $this->errorMessage = "Tanlangan kassada mablag' yetarli emas! Mavjud: " . number_format($account->balance ?? 0, 0, '.', ' ') . " so'm.";
            return;
        }

        DB::transaction(function () use ($account) {
            $expense = Expense::create([
                'category' => $this->expenseCategory,
                'cash_account_id' => $account->id,
                'amount' => $this->expenseAmount,
                'description' => $this->expenseDescription ?: $this->expenseCategory,
                'created_by' => auth()->id() ?? 1,
            ]);

            CashTransaction::create([
                'cash_account_id' => $account->id,
                'type' => 'EXPENSE',
                'amount' => -$this->expenseAmount,
                'reference_type' => Expense::class,
                'reference_id' => $expense->id,
                'description' => "Xarajat: {$this->expenseCategory} (" . ($this->expenseDescription ?: 'Izohsiz') . ")",
                'created_by' => auth()->id() ?? 1,
                'created_at' => Carbon::now(),
            ]);

            $account->decrement('balance', $this->expenseAmount);

            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'EXPENSE_CREATE',
                'auditable_type' => Expense::class,
                'auditable_id' => $expense->id,
                'new_values' => [
                    'category' => $expense->category,
                    'amount' => $expense->amount,
                    'account' => $account->name,
                ],
            ]);
        });

        $this->successMessage = "✓ " . number_format($this->expenseAmount, 0, '.', ' ') . " so'mlik xarajat kassadan yozildi!";
        $this->reset(['expenseAmount', 'expenseDescription']);
    }

    public function repayDebt()
    {
        $this->validate([
            'payingCustomerId' => 'required|exists:customers,id',
            'repaymentAmount' => 'required|integer|min:100',
            'repaymentAccountId' => 'required|exists:cash_accounts,id',
            'repaymentNotes' => 'nullable|string|max:255',
        ]);

        $customer = Customer::find($this->payingCustomerId);
        $account = CashAccount::find($this->repaymentAccountId);

        if (!$customer || !$account) {
            return;
        }

        DB::transaction(function () use ($customer, $account) {
            $newDebt = max(0, $customer->current_debt - $this->repaymentAmount);

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'type' => 'PAYMENT',
                'debit' => 0,
                'credit' => $this->repaymentAmount,
                'balance_after' => $newDebt,
                'reference_type' => CashTransaction::class,
                'reference_id' => null,
                'notes' => $this->repaymentNotes ?: "Qarz to'lovi qabul qilindi",
                'created_by' => auth()->id() ?? 1,
                'created_at' => Carbon::now(),
            ]);

            CashTransaction::create([
                'cash_account_id' => $account->id,
                'type' => 'CUSTOMER_PAYMENT',
                'amount' => $this->repaymentAmount,
                'reference_type' => Customer::class,
                'reference_id' => $customer->id,
                'description' => "Qarz to'lovi: {$customer->name}",
                'created_by' => auth()->id() ?? 1,
                'created_at' => Carbon::now(),
            ]);

            $customer->current_debt = $newDebt;
            $customer->save();

            $account->increment('balance', $this->repaymentAmount);

            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'DEBT_PAYMENT',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'new_values' => [
                    'amount' => $this->repaymentAmount,
                    'remaining_debt' => $newDebt,
                    'account' => $account->name,
                ],
            ]);
        });

        $this->successMessage = "✓ {$customer->name} tomonidan " . number_format($this->repaymentAmount, 0, '.', ' ') . " so'm qarz to'lovi qabul qilindi!";
        $this->reset(['payingCustomerId', 'repaymentAmount', 'repaymentNotes']);
    }

    public function with()
    {
        $salesQuery = Sale::query();
        $expensesQuery = Expense::query();

        if ($this->period === 'today') {
            $salesQuery->whereDate('created_at', Carbon::today());
            $expensesQuery->whereDate('created_at', Carbon::today());
        } elseif ($this->period === 'month') {
            $salesQuery->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year);
            $expensesQuery->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year);
        }

        $totalRevenue = (int) $salesQuery->sum('total_amount');
        $totalCogs = (int) $salesQuery->sum('total_cost');
        $realizedGrossProfit = $totalRevenue - $totalCogs;
        $totalExpenses = (int) $expensesQuery->sum('amount');
        $netOperatingProfit = $realizedGrossProfit - $totalExpenses;

        $cashAccounts = CashAccount::orderBy('id')->get();
        $debtCustomers = Customer::where('current_debt', '>', 0)->orderByDesc('current_debt')->get();
        $totalOutstandingDebt = (int) Customer::sum('current_debt');

        $recentTransactions = CashTransaction::with('account')->orderByDesc('created_at')->limit(8)->get();
        $recentExpenses = Expense::with('account')->orderByDesc('created_at')->limit(6)->get();

        return [
            'totalRevenue' => $totalRevenue,
            'totalCogs' => $totalCogs,
            'realizedGrossProfit' => $realizedGrossProfit,
            'totalExpenses' => $totalExpenses,
            'netOperatingProfit' => $netOperatingProfit,
            'cashAccounts' => $cashAccounts,
            'debtCustomers' => $debtCustomers,
            'totalOutstandingDebt' => $totalOutstandingDebt,
            'recentTransactions' => $recentTransactions,
            'recentExpenses' => $recentExpenses,
        ];
    }
};
?>

<div class="space-y-6">
    <!-- Header va Davr Filtr -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs uppercase font-black text-emerald-400 bg-emerald-950/80 px-2.5 py-1 rounded-full border border-emerald-900">
                    Moliya, Kassa & Qarzlar • Master Prompt #15-18
                </span>
                <h2 class="text-xl font-bold text-white">Moliyaviy Hisobot & Kassa Boshqaruvi</h2>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Realized Gross Profit (Haqiqiy foyda) vs Operatsion xarajatlar vs Sof operatsion foyda
            </p>
        </div>

        <!-- Davr Tanlash -->
        <div class="flex items-center gap-2 bg-slate-950 p-1.5 rounded-2xl border border-slate-800 text-xs font-semibold">
            <button wire:click="$set('period', 'today')"
                class="px-3.5 py-1.5 rounded-xl transition-all {{ $period === 'today' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 hover:text-white' }}">
                Bugun
            </button>
            <button wire:click="$set('period', 'month')"
                class="px-3.5 py-1.5 rounded-xl transition-all {{ $period === 'month' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 hover:text-white' }}">
                Shu Oy
            </button>
            <button wire:click="$set('period', 'all')"
                class="px-3.5 py-1.5 rounded-xl transition-all {{ $period === 'all' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 hover:text-white' }}">
                Barcha Vaqt
            </button>
        </div>
    </div>

    <!-- Xabarnomalar -->
    @if ($successMessage)
        <div class="p-4 bg-emerald-950/80 border border-emerald-800 text-emerald-300 rounded-2xl text-xs font-bold flex items-center justify-between shadow-lg">
            <span>{{ $successMessage }}</span>
            <button wire:click="$set('successMessage', null)" class="text-emerald-400 hover:text-white ml-4">✕</button>
        </div>
    @endif
    @if ($errorMessage)
        <div class="p-4 bg-red-950/80 border border-red-800 text-red-300 rounded-2xl text-xs font-bold flex items-center justify-between shadow-lg">
            <span>⚠️ {{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-red-400 hover:text-white ml-4">✕</button>
        </div>
    @endif

    <!-- 5 ta Asosiy Moliyaviy Ko'rsatkich -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Savdo Tushumi -->
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-1">
            <span class="text-xs text-slate-400">Jami Savdo Tushumi:</span>
            <div class="text-xl font-black text-cyan-400 tracking-tight">
                {{ number_format($totalRevenue, 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-slate-500">Optom savdodan tushgan summa</p>
        </div>

        <!-- COGS (Tannarx) -->
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-1">
            <span class="text-xs text-slate-400">Sotilgan Tovar Tannarxi (COGS):</span>
            <div class="text-xl font-black text-slate-300 tracking-tight">
                {{ number_format($totalCogs, 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-slate-500">Tarixiy kirim narxlari bo'yicha</p>
        </div>

        <!-- Realized Gross Profit -->
        <div class="bg-slate-900 border border-emerald-900/40 p-5 rounded-2xl space-y-1">
            <span class="text-xs text-emerald-400 font-bold">Haqiqiy Yalpi Foyda:</span>
            <div class="text-xl font-black text-emerald-400 tracking-tight">
                +{{ number_format($realizedGrossProfit, 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-emerald-600 font-semibold">Tushum - COGS</p>
        </div>

        <!-- Operatsion Xarajatlar -->
        <div class="bg-slate-900 border border-amber-900/40 p-5 rounded-2xl space-y-1">
            <span class="text-xs text-amber-400 font-bold">Do'kon Xarajatlari:</span>
            <div class="text-xl font-black text-amber-400 tracking-tight">
                -{{ number_format($totalExpenses, 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-slate-500">Ijara, ish haqi, transport va boshqalar</p>
        </div>

        <!-- Sof Operatsion Foyda -->
        <div class="bg-gradient-to-br from-slate-900 to-emerald-950/40 border border-emerald-800/80 p-5 rounded-2xl space-y-1 shadow-lg">
            <span class="text-xs text-emerald-300 font-black uppercase tracking-wider">Sof Operatsion Foyda:</span>
            <div class="text-2xl font-black {{ $netOperatingProfit >= 0 ? 'text-emerald-400' : 'text-red-400' }} tracking-tight">
                {{ $netOperatingProfit >= 0 ? '+' : '' }}{{ number_format($netOperatingProfit, 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-slate-400">Yalpi foyda - Xarajatlar</p>
        </div>
    </div>

    <!-- Kassalar Holati & Umumiy Nasiya -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach($cashAccounts as $acc)
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 block">
                        {{ $acc->type === 'CASH' ? '💵 Naqd Kassa' : ($acc->type === 'CARD' ? '💳 Karta (Terminal)' : '🏦 Bank Hisobi') }}
                    </span>
                    <span class="text-lg font-black text-white font-mono mt-0.5 block">
                        {{ number_format($acc->balance, 0, '.', ' ') }} so'm
                    </span>
                </div>
                <span class="text-xs text-slate-500 font-bold uppercase">{{ $acc->name }}</span>
            </div>
        @endforeach

        <div class="bg-slate-900 border border-amber-900/60 p-4 rounded-2xl flex items-center justify-between">
            <div>
                <span class="text-xs text-amber-400 font-bold block">🧾 Jami Nasiyalar (Qarzlar):</span>
                <span class="text-lg font-black text-amber-400 font-mono mt-0.5 block">
                    {{ number_format($totalOutstandingDebt, 0, '.', ' ') }} so'm
                </span>
            </div>
            <span class="text-xs text-slate-500 font-bold font-mono">{{ count($debtCustomers) }} ta mijoz</span>
        </div>
    </div>

    <!-- 2 ta Amaliy Panel: Tezkor Xarajat Kiritish & Qarz Qabul Qilish -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Xarajat Kiritish Formasi -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4 shadow-xl">
            <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
                <span class="text-lg">💸</span>
                <h3 class="text-sm font-bold text-white">Do'kon Operatsion Xarajati Kiritish</h3>
            </div>

            <form wire:submit.prevent="addExpense" class="space-y-3.5">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Xarajat Kategoriyasi:</label>
                    <select wire:model="expenseCategory" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none">
                        <option value="Transport / Yetkazib berish">🚚 Transport / Yetkazib berish</option>
                        <option value="Ish haqi">👷 Ish haqi (Hodimlar maoshi)</option>
                        <option value="Ijara">🏢 Do'kon / Ombor Ijarasi</option>
                        <option value="Elektr / Kommunal">💡 Elektr & Kommunal to'lovlar</option>
                        <option value="Tushlik / Choyxona">🍲 Tushlik & Xo'jalik xarajatlari</option>
                        <option value="Boshqa">📦 Boshqa xarajatlar</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Summasi (so'm):</label>
                        <input type="number" wire:model="expenseAmount" placeholder="Masalan: 150000"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none font-mono">
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Qaysi Kassadan to'lanadi:</label>
                        <select wire:model="expenseAccountId" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none">
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} ({{ number_format($acc->balance, 0, '.', ' ') }} so'm)</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Izoh / Tafsilot:</label>
                    <input type="text" wire:model="expenseDescription" placeholder="Masalan: Labo mashinasiga yoqilg'i quyildi"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-xs shadow-lg transition-all">
                    Xarajatni Kassadan Chiqarish 💸
                </button>
            </form>
        </div>

        <!-- Nasiya / Qarz To'lovini Qabul Qilish Formasi -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4 shadow-xl">
            <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
                <span class="text-lg">🤝</span>
                <h3 class="text-sm font-bold text-white">Mijoz Qarz To'lovini Qabul Qilish</h3>
            </div>

            <form wire:submit.prevent="repayDebt" class="space-y-3.5">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Qarzdor Mijoz:</label>
                    <select wire:model="payingCustomerId" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none">
                        <option value="">-- Mijozni tanlang --</option>
                        @foreach($debtCustomers as $dc)
                            <option value="{{ $dc->id }}">
                                {{ $dc->name }} — Qarzi: {{ number_format($dc->current_debt, 0, '.', ' ') }} so'm
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">To'lanayotgan Summa (so'm):</label>
                        <input type="number" wire:model="repaymentAmount" placeholder="Masalan: 500000"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none font-mono">
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Qaysi Kassaga kirim:</label>
                        <select wire:model="repaymentAccountId" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none">
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">To'lov izohi:</label>
                    <input type="text" wire:model="repaymentNotes" placeholder="Masalan: Kechagi yuk uchun to'liq hisob-kitob"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg transition-all">
                    Qarz To'lovini Qabul Qilish & Kassaga Kirim 💵
                </button>
            </form>
        </div>
    </div>

    <!-- Oxirgi Kassa Harakatlari va Xarajatlar Jadvallari -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Kassa Jurnali -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-3">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center justify-between">
                <span>📑 Oxirgi Kassa Harakatlari</span>
                <span class="text-[10px] text-slate-500 font-mono">So'nggi 8 ta</span>
            </h3>

            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-950 text-slate-400 font-bold uppercase border-b border-slate-800">
                        <tr>
                            <th class="p-2.5">Sana</th>
                            <th class="p-2.5">Turi</th>
                            <th class="p-2.5">Kassa</th>
                            <th class="p-2.5 text-right">Summa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 bg-slate-900/60">
                        @forelse($recentTransactions as $tx)
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-2.5 text-slate-500 font-mono text-[11px]">
                                    {{ $tx->created_at ? $tx->created_at->format('H:i d.m') : '-' }}
                                </td>
                                <td class="p-2.5 text-slate-300 font-medium">
                                    {{ $tx->description }}
                                </td>
                                <td class="p-2.5 font-mono text-slate-400 text-[11px]">
                                    {{ $tx->account->name ?? '-' }}
                                </td>
                                <td class="p-2.5 text-right font-mono font-bold {{ $tx->amount >= 0 ? 'text-emerald-400' : 'text-amber-400' }}">
                                    {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount, 0, '.', ' ') }} so'm
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-center text-slate-500">Hozircha harakat yo'q.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Qarzdorlar Ro'yxati -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-3">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center justify-between">
                <span>⚠️ Qarzdor Mijozlar Ro'yxati</span>
                <span class="text-[10px] text-amber-400 font-mono font-bold">{{ count($debtCustomers) }} ta mijoz</span>
            </h3>

            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-950 text-slate-400 font-bold uppercase border-b border-slate-800">
                        <tr>
                            <th class="p-2.5">Mijoz Nomi</th>
                            <th class="p-2.5">Telefon</th>
                            <th class="p-2.5 text-right">Qarz Miqdori</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 bg-slate-900/60">
                        @forelse($debtCustomers as $dc)
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-2.5 font-bold text-white">👤 {{ $dc->name }}</td>
                                <td class="p-2.5 font-mono text-slate-400">{{ $dc->phone ?? 'Mavjud emas' }}</td>
                                <td class="p-2.5 text-right font-mono font-black text-amber-400">
                                    {{ number_format($dc->current_debt, 0, '.', ' ') }} so'm
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-4 text-center text-slate-500">Qarzdor mijozlar mavjud emas. Barcha hisob-kitoblar toza! 👍</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
