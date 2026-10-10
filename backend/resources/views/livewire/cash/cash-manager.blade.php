<div class="space-y-6" wire:poll.15s>
    @if($feedbackMessage && ! $showExpenseModal && ! $showOwnerFundsModal)<div class="trade-alert {{ $feedbackType === 'error' ? 'trade-alert-error' : '' }}" role="{{ $feedbackType === 'error' ? 'alert' : 'status' }}">{{ $feedbackMessage }}</div>@endif
    <section class="trade-card cash-overview">
        <p class="text-slate-600">Kassada hozir bor pul</p><strong class="block mt-2 text-4xl font-black text-emerald-700" data-testid="cash-balance">{{ number_format($totalBalance, 0, '.', ' ') }} <small class="text-lg">so‘m</small></strong>
        <p class="mt-2 text-sm text-slate-600">Barcha pul bitta kassada hisoblanadi. Pul qo‘shilsa ko‘payadi, to‘lov va xarajat qilinsa kamayadi.</p>
        <div class="grid gap-3 mt-5 sm:grid-cols-3"><div class="workspace-hint">Bugun kassaga kirgan pul<strong class="block mt-1 text-emerald-700">+{{ number_format($todayCashIn, 0, '.', ' ') }} so‘m</strong></div><div class="workspace-hint">Bugun kassadan chiqqan pul<strong class="block mt-1 text-rose-700">−{{ number_format($todayCashOut, 0, '.', ' ') }} so‘m</strong></div><div class="workspace-hint">Shundan do‘kon xarajatlari<strong class="block mt-1">{{ number_format($todayExpenses, 0, '.', ' ') }} so‘m</strong></div></div>
    </section>
    <div class="cash-actions grid gap-4 sm:grid-cols-2">
        @can('manage_cash')<button type="button" wire:click="openOwnerFundsModal('DEPOSIT')" class="trade-card text-left hover:ring-2 hover:ring-emerald-500"><span class="text-3xl">📥</span><h2 class="mt-3 text-xl font-bold">Kassaga pul qo‘shish</h2><p class="mt-2 text-slate-600">Do‘kon egasi kiritgan pul yoki qo‘shimcha mablag‘.</p></button>@endcan
        @can('record_expense')<button type="button" wire:click="openExpenseModal" class="trade-card text-left hover:ring-2 hover:ring-rose-500"><span class="text-3xl">📤</span><h2 class="mt-3 text-xl font-bold">Xarajat yozish</h2><p class="mt-2 text-slate-600">Transport, ijara, ish haqi yoki boshqa do‘kon xarajati.</p></button>@endcan
    </div>
    <div class="workspace-hint flex flex-wrap items-center justify-between gap-3"><p>Mijoz qarzini to‘lasa yoki yetkazuvchiga qarz to‘lasangiz, uni <strong>«Qarzlar va to‘lovlar»</strong> orqali yozing. Sotuv puli kassaga avtomatik tushadi.</p><a class="trade-button trade-button-secondary" href="{{ url('/qarzdorliklar') }}">Qarz to‘lovlariga o‘tish →</a>@can('manage_cash')<button type="button" wire:click="openOwnerFundsModal('DRAW')" class="trade-button trade-button-secondary">Egaga pul berish</button>@endcan</div>
    <section class="trade-card space-y-4">
        <h2 class="text-xl font-bold">Pul qayerdan kirdi va qayerga ketdi?</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="trade-field"><label for="cash-direction">Qaysi amallar?</label><select id="cash-direction" wire:model.live="filterDirection"><option value="ALL">Barchasi</option><option value="IN">Pul kirimi</option><option value="OUT">Pul chiqimi</option></select></div>
            <div class="trade-field"><label for="cash-period">Qachon?</label><select id="cash-period" wire:model.live="filterDateRange"><option value="today">Bugun</option><option value="7days">So‘nggi 7 kun</option><option value="month">Shu oy</option><option value="all">Barcha vaqt</option></select></div>
            <div class="trade-field flex-1 min-w-0"><label for="cash-search">Qidirish</label><input id="cash-search" wire:model.live.debounce.300ms="search" placeholder="Izoh yoki amalni qidiring"></div>
        </div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-slate-200 text-slate-600"><th class="p-3">Sana va vaqt</th><th class="p-3">Nima bo‘ldi?</th><th class="p-3 text-right">Pul kirdi</th><th class="p-3 text-right">Pul chiqdi</th><th class="p-3 text-right">Amaldan keyin</th><th class="p-3">Izoh / mas’ul</th></tr></thead><tbody>
        @forelse($movements as $m)<tr class="border-b border-slate-100"><td class="p-3 whitespace-nowrap">{{ $m->created_at->timezone('Asia/Tashkent')->format('d.m.Y H:i:s') }}</td><td class="p-3"><x-enum-label :value="$m->type" /></td><td class="p-3 text-right whitespace-nowrap text-emerald-700 font-bold">{{ $m->debit > 0 ? '+'.number_format($m->debit, 0, '.', ' ') : '—' }}</td><td class="p-3 text-right whitespace-nowrap text-rose-700 font-bold">{{ $m->credit > 0 ? '−'.number_format($m->credit, 0, '.', ' ') : '—' }}</td><td class="p-3 text-right whitespace-nowrap font-bold">{{ number_format($m->balance_after, 0, '.', ' ') }} so‘m</td><td class="p-3"><p>{{ $m->description ?: 'Izohsiz' }}</p><small class="text-slate-600">{{ $m->creator->name ?? 'Tizim' }}</small></td></tr>
        @empty<tr><td colspan="6" class="p-8 text-center text-slate-600">Hali pul harakati yo‘q. Birinchi savdo yoki pul kirimidan keyin shu yerda ko‘rinadi.</td></tr>@endforelse
        </tbody></table></div>{{ $movements->links() }}
    </section>

    @if($showExpenseModal)
        @php($expenseCash = (int) $accounts->firstWhere('id', $expenseAccountId)?->balance)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" x-on:keydown.escape.window="$wire.set('showExpenseModal', false)">
            <form wire:submit="submitExpense" role="dialog" aria-modal="true" aria-labelledby="expense-title" class="trade-card w-full max-w-lg max-h-[90dvh] overflow-y-auto space-y-5 shadow-2xl">
                <div class="flex items-start justify-between gap-3"><h2 id="expense-title" class="text-xl font-bold">Do‘kon xarajatini yozish</h2><button type="button" wire:click="$set('showExpenseModal', false)" class="trade-remove" aria-label="Xarajat oynasini yopish">×</button></div>
                @if($feedbackMessage && $feedbackType === 'error')<div class="trade-alert trade-alert-error" role="alert">{{ $feedbackMessage }}</div>@endif<x-validation-errors />
                <div class="trade-field"><label for="expense-category">Pul nimaga sarflandi?</label><select id="expense-category" wire:model="expenseCategory"><option value="OTHER">Boshqa xarajat</option><option value="RENT">Ijara</option><option value="SALARY">Ish haqi</option><option value="UTILITIES">Kommunal xizmatlar</option><option value="TRANSPORT">Transport / yetkazib berish</option><option value="UNLOADING">Yuk tushirish</option></select></div>
                <div class="trade-field"><label for="cash-expense-amount">Xarajat summasi</label><div class="trade-input-unit"><input id="cash-expense-amount" type="number" min="1" step="1" inputmode="numeric" wire:model.live.debounce.300ms="expenseAmount" placeholder="Summani yozing"><span>so‘m</span></div></div>
                <div class="workspace-hint"><p>Kassada hozir: <strong>{{ number_format($expenseCash, 0, '.', ' ') }} so‘m</strong></p><p>Xarajatdan keyin: <strong>{{ number_format($expenseCash - (int) $expenseAmount, 0, '.', ' ') }} so‘m</strong></p></div>
                @if((int) $expenseAmount > $expenseCash)<div class="trade-alert trade-alert-error" role="alert">Kassada pul yetmaydi. {{ number_format((int) $expenseAmount - $expenseCash, 0, '.', ' ') }} so‘m yetishmayapti. Summani kamaytiring yoki avval kassaga pul qo‘shing.</div>@endif
                <div class="trade-field"><label for="expense-notes">Izoh (ixtiyoriy)</label><input id="expense-notes" wire:model="expenseDescription" placeholder="Masalan: Gazel yoqilg'isi uchun"></div>
                <div class="flex flex-wrap justify-end gap-3"><button type="button" wire:click="$set('showExpenseModal', false)" class="trade-button trade-button-secondary">Bekor qilish</button><button type="submit" wire:loading.attr="disabled" wire:target="submitExpense" class="trade-button trade-button-primary"><span wire:loading.remove wire:target="submitExpense">Xarajatni saqlash</span><span wire:loading wire:target="submitExpense">Saqlanyapti…</span></button></div>
            </form>
        </div>
    @endif

    @if($showOwnerFundsModal)
        @php($ownerCash = (int) $accounts->firstWhere('id', $ownerAccountId)?->balance)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" x-on:keydown.escape.window="$wire.set('showOwnerFundsModal', false)">
            <form wire:submit="submitOwnerFunds" role="dialog" aria-modal="true" aria-labelledby="owner-funds-title" class="trade-card w-full max-w-lg max-h-[90dvh] overflow-y-auto space-y-5 shadow-2xl">
                <div class="flex items-start justify-between gap-3"><h2 id="owner-funds-title" class="text-xl font-bold">{{ $ownerFundType === 'DEPOSIT' ? 'Kassaga pul qo‘shish' : 'Egaga pul berish' }}</h2><button type="button" wire:click="$set('showOwnerFundsModal', false)" class="trade-remove" aria-label="Pul oynasini yopish">×</button></div>
                @if($feedbackMessage && $feedbackType === 'error')<div class="trade-alert trade-alert-error" role="alert">{{ $feedbackMessage }}</div>@endif<x-validation-errors />
                <p class="text-slate-600">{{ $ownerFundType === 'DEPOSIT' ? 'Bu qo‘shimcha mablag‘. Mijoz qarzini to‘lagan bo‘lsa, «Qarzlar va to‘lovlar»dan foydalaning.' : 'Bu egaga qaytarilgan mablag‘. Do‘kon xarajati va foyda hisobotiga qo‘shilmaydi.' }}</p>
                <div class="trade-field"><label for="owner-amount">Summa</label><div class="trade-input-unit"><input id="owner-amount" wire:model.live.debounce.300ms="ownerAmount" type="number" min="1" step="1" inputmode="numeric" placeholder="Summani yozing"><span>so‘m</span></div></div>
                <div class="workspace-hint"><p>Kassada hozir: <strong>{{ number_format($ownerCash, 0, '.', ' ') }} so‘m</strong></p><p>Saqlangandan keyin: <strong>{{ number_format($ownerCash + ($ownerFundType === 'DEPOSIT' ? (int) $ownerAmount : -(int) $ownerAmount), 0, '.', ' ') }} so‘m</strong></p></div>
                @if($ownerFundType !== 'DEPOSIT' && (int) $ownerAmount > $ownerCash)<div class="trade-alert trade-alert-error" role="alert">Kassada pul yetmaydi. Summani kamaytiring.</div>@endif
                <div class="trade-field"><label for="owner-notes">Izoh (ixtiyoriy)</label><input id="owner-notes" wire:model="ownerDescription" placeholder="Pul nima uchun kiritildi yoki olindi?"></div>
                <div class="flex flex-wrap justify-end gap-3"><button type="button" wire:click="$set('showOwnerFundsModal', false)" class="trade-button trade-button-secondary">Bekor qilish</button><button type="submit" wire:loading.attr="disabled" wire:target="submitOwnerFunds" class="trade-button trade-button-primary"><span wire:loading.remove wire:target="submitOwnerFunds">{{ $ownerFundType === 'DEPOSIT' ? 'Pulni qo‘shish' : 'Tasdiqlash va chiqim qilish' }}</span><span wire:loading wire:target="submitOwnerFunds">Saqlanyapti…</span></button></div>
            </form>
        </div>
    @endif
</div>
