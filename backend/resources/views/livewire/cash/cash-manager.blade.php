<div class="space-y-6" wire:poll.15s>
    {{-- Feedback Message --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-xl flex items-center justify-between {{ $feedbackType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-700' : 'bg-rose-500/10 border border-rose-500/30 text-rose-700' }}">
            <div class="flex items-center space-x-3">
                <span class="text-xl">{{ $feedbackType === 'success' ? '✓' : '⚠️' }}</span>
                <span class="font-medium text-sm">{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-xs hover:underline opacity-80">Yopish</button>
        </div>
    @endif

    {{-- Kassa Hisoblari (Account Cards) --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Jami Kassa Mablag'i --}}
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="text-xs font-semibold text-slate-600  mb-1">Hozir barcha hisoblarda bor pul</div>
            <div class="text-2xl font-black text-slate-900">
                {{ number_format($totalBalance, 0, '.', ' ') }} <span class="text-sm font-semibold text-emerald-700">so'm</span>
            </div>
            <div class="text-xs text-slate-600 mt-2">Barcha hisoblar yig'indisi</div>
        </div>

        {{-- Har bir hisob --}}
        @foreach ($accounts as $acc)
            <div class="bg-slate-50 border {{ $acc->is_default ? 'border-sky-500/40 shadow-sky-500/5' : 'border-slate-200' }} rounded-2xl p-5 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-slate-600 ">{{ $acc->name }}</span>
                    @if ($acc->is_default)
                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-sky-500/20 text-sky-700">ASOSIY</span>
                    @endif
                </div>
                <div class="text-2xl font-black text-slate-900">
                    {{ number_format($acc->balance, 0, '.', ' ') }} <span class="text-sm font-semibold text-slate-600">so'm</span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-600 mt-2">
                    <span class=" font-semibold text-xs text-slate-600">{{ match($acc->type) { 'CASH' => 'Naqd pul', 'CARD' => 'Karta', 'BANK' => 'Bank hisobi', default => 'Pul hisobi' } }}</span>
                    <button wire:click="openTransferModal" class="text-sky-700 hover:text-sky-700 font-medium">O'tkazma →</button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="money-flow-cards"><div><span>Bugun kassaga kirgan pul</span><strong>{{ number_format($todayCashIn, 0, '.', ' ') }} so‘m</strong></div><div><span>Bugun kassadan chiqqan pul</span><strong>{{ number_format($todayCashOut, 0, '.', ' ') }} so‘m</strong></div><div><span>Shundan do‘kon xarajatlari</span><strong>{{ number_format($todayExpenses, 0, '.', ' ') }} so‘m</strong></div></div>
    <p class="workspace-caption">Bugungi summalarga ichki o‘tkazmalar va boshlang‘ich qoldiq qo‘shilmaydi. Pastdagi tarix filtri yuqoridagi hozirgi qoldiqlarni o‘zgartirmaydi.</p>
    {{-- Smena (Cash Session) Banner --}}
    <div class="bg-gradient-to-r from-slate-50 to-slate-100/90 border border-slate-200 rounded-2xl p-6 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            @if ($activeSession)
                <div class="space-y-1">
                    <div class="flex items-center space-x-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-700 border border-emerald-500/30">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 mr-2 animate-pulse"></span>
                            SMENA OCHIQ (#{{ $activeSession->session_number }})
                        </span>
                        <span class="text-xs text-slate-600 font-medium">
                            {{ $activeSession->account->name ?? 'Asosiy Kassa' }}
                        </span>
                    </div>
                    <div class="text-slate-700 text-sm">
                        Ochilgan vaqt: <span class="font-bold text-slate-900">{{ $activeSession->opened_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s') }}</span> |
                        Kassir: <span class="font-semibold text-sky-700">{{ $activeSession->opener->name ?? 'Noma\'lum' }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-6 pt-2 text-xs text-slate-600">
                        <div>
                            Boshlang'ich naqd: <span class="font-bold text-slate-900">{{ number_format($activeSession->opening_balance, 0, '.', ' ') }} so'm</span>
                        </div>
                        <div>
                            Hisob bo‘yicha qolgan pul: <span class="font-bold text-emerald-700 text-sm">{{ number_format($activeSessionExpected, 0, '.', ' ') }} so'm</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <button wire:click="openCloseModal({{ $activeSession->id }})" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-sm shadow-md transition-all">
                        Kunni yakunlash
                    </button>
                </div>
            @else
                <div class="space-y-1">
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-300">
                            OCHIQ SMENA YO'Q
                        </span>
                    </div>
                    <p class="text-slate-600 text-sm">
                        Savdolar va kassa pul harakatlarini rasmiy smenaga bog'lash uchun yangi smena oching.
                    </p>
                </div>
                <div>
                    <button wire:click="openNewSessionModal" class="px-5 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-900 font-bold text-sm shadow-md transition-all">
                        + Ish kunini boshlash
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Tezkor Amallar Qatori (Quick Action Buttons) --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        {{-- Tablar --}}
        <div class="inline-flex p-1 bg-slate-50 border border-slate-200 rounded-xl">
            <button wire:click="setTab('movements')" class="px-4 py-2 rounded-lg text-xs font-bold transition-all {{ $activeTab === 'movements' ? 'bg-sky-500 text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Pul kirimi va chiqimi
            </button>
            <button wire:click="setTab('sessions')" class="px-4 py-2 rounded-lg text-xs font-bold transition-all {{ $activeTab === 'sessions' ? 'bg-sky-500 text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Ish kunlari tarixi
            </button>
        </div>

        {{-- Tezkor tugmalar --}}
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="openExpenseModal" class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-700 border border-rose-500/30 text-xs font-bold transition-all flex items-center space-x-2">
                <span>−</span>
                <span>Xarajat yozish</span>
            </button>
            <button wire:click="openTransferModal" class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-700 border border-indigo-500/30 text-xs font-bold transition-all flex items-center space-x-2">
                <span>⇄</span>
                <span>Pulni boshqa hisobga o‘tkazish</span>
            </button>
            <button wire:click="openOwnerFundsModal('DEPOSIT')" class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-700 border border-emerald-500/30 text-xs font-bold transition-all flex items-center space-x-2">
                <span>+</span>
                <span>Egadan pul olish</span>
            </button>
            <button wire:click="openOwnerFundsModal('DRAW')" class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-700 border border-amber-500/30 text-xs font-bold transition-all flex items-center space-x-2">
                <span>−</span>
                <span>Egaga pul berish</span>
            </button>
        </div>
    </div>

    {{-- TAB 1: Pul kirimi va chiqimi --}}
    @if ($activeTab === 'movements')
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
            {{-- Filtrlar --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                {{-- Hisob filtri --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Hisob</label>
                    <select wire:model.live="filterAccountId" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                        <option value="">Barcha hisoblar</option>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Yo'nalish filtri --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Kirim / Chiqim</label>
                    <select wire:model.live="filterDirection" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                        <option value="ALL">Barchasi</option>
                        <option value="IN">Faqat Kirim (+)</option>
                        <option value="OUT">Faqat Chiqim (−)</option>
                    </select>
                </div>

                {{-- Turi filtri --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Harakat Turi</label>
                    <select wire:model.live="filterType" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                        <option value="ALL">Barchasi</option>
                        <option value="SALE_PAYMENT">Savdo to'lovi</option>
                        <option value="CUSTOMER_PAYMENT">Mijoz qarz to'lovi</option>
                        <option value="SUPPLIER_PAYMENT">Ta'minotchi to'lovi</option>
                        <option value="EXPENSE">Operatsion xarajat</option>
                        <option value="TRANSFER_IN">O'tkazma kirimi</option>
                        <option value="TRANSFER_OUT">O'tkazma chiqimi</option>
                        <option value="OWNER_DEPOSIT">Egasi kiritgan pul</option>
                        <option value="OWNER_DRAW">Egasi olgan pul (Draw)</option>
                        <option value="DIFFERENCE_SURPLUS">Kassa ortiqchaligi</option>
                        <option value="DIFFERENCE_SHORTAGE">Kassa kamomadi</option>
                    </select>
                </div>

                {{-- Davr filtri --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Davr</label>
                    <select wire:model.live="filterDateRange" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                        <option value="today">Bugun</option>
                        <option value="7days">So'nggi 7 kun</option>
                        <option value="month">Shu oy</option>
                        <option value="all">Barchasi</option>
                    </select>
                </div>

                {{-- Qidiruv --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Qidiruv</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Izoh yoki ID bo'yicha..." class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-600 focus:outline-none focus:border-sky-500">
                </div>
            </div>

            {{-- Jadval --}}
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100/60 text-slate-600 font-semibold border-b border-slate-200">
                            <th class="py-3 px-4">Sana / Vaqt</th>
                            <th class="py-3 px-4">Hisob</th>
                            <th class="py-3 px-4">Harakat Turi</th>
                            <th class="py-3 px-4 text-right">Kirim (+)</th>
                            <th class="py-3 px-4 text-right">Chiqim (−)</th>
                            <th class="py-3 px-4 text-right">Qoldiq</th>
                            <th class="py-3 px-4">Mas'ul</th>
                            <th class="py-3 px-4">Izoh / Hujjat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 text-slate-700">
                        @forelse ($movements as $m)
                            <tr class="hover:bg-slate-100/30 transition-colors">
                                <td class="py-3 px-4 font-mono text-xs text-slate-600 whitespace-nowrap">
                                    {{ $m->created_at ? $m->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s') : '-' }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    {{ $m->account->name ?? 'Hisob #' . $m->cash_account_id }}
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $typeBadge = match($m->type) {
                                            'SALE_PAYMENT' => 'bg-emerald-500/20 text-emerald-700 border-emerald-500/30',
                                            'CUSTOMER_PAYMENT' => 'bg-teal-500/20 text-teal-700 border-teal-500/30',
                                            'SUPPLIER_PAYMENT' => 'bg-orange-500/20 text-orange-700 border-orange-500/30',
                                            'EXPENSE' => 'bg-rose-500/20 text-rose-700 border-rose-500/30',
                                            'TRANSFER_IN', 'TRANSFER_OUT' => 'bg-indigo-500/20 text-indigo-700 border-indigo-500/30',
                                            'OWNER_DEPOSIT' => 'bg-sky-500/20 text-sky-700 border-sky-500/30',
                                            'OWNER_DRAW' => 'bg-amber-500/20 text-amber-700 border-amber-500/30',
                                            'DIFFERENCE_SURPLUS' => 'bg-purple-500/20 text-purple-700 border-purple-500/30',
                                            'DIFFERENCE_SHORTAGE' => 'bg-red-500/20 text-red-700 border-red-500/30',
                                            default => 'bg-slate-200 text-slate-700 border-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold border {{ $typeBadge }}">
                                        <x-enum-label :value="$m->type" />
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-emerald-700 whitespace-nowrap">
                                    {{ $m->debit > 0 ? '+' . number_format($m->debit, 0, '.', ' ') : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-rose-700 whitespace-nowrap">
                                    {{ $m->credit > 0 ? '−' . number_format($m->credit, 0, '.', ' ') : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 whitespace-nowrap">
                                    {{ number_format($m->balance_after, 0, '.', ' ') }} so'm
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    {{ $m->creator->name ?? 'Tizim' }}
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <div class="max-w-xs truncate" title="{{ $m->description }}">
                                        {{ $m->description ?: ($m->reference_type ? "{$m->reference_type} #{$m->reference_id}" : '-') }}
                                    </div>
                                    @if ($m->session)
                                        <div class="text-xs text-slate-600 font-mono">Smena: {{ $m->session->session_number }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-600">
                                    Belgilangan filtrlarga mos pul harakatlari topilmadi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Sahifalash --}}
            <div>
                {{ $movements->links() }}
            </div>
        </div>
    @endif

    {{-- TAB 2: Ish kunlari tarixi --}}
    @if ($activeTab === 'sessions')
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100/60 text-slate-600 font-semibold border-b border-slate-200">
                            <th class="py-3 px-4">Smena Raqami</th>
                            <th class="py-3 px-4">Kassa</th>
                            <th class="py-3 px-4">Ochildi / Mas'ul</th>
                            <th class="py-3 px-4">Yopildi / Mas'ul</th>
                            <th class="py-3 px-4 text-right">Boshlang'ich</th>
                            <th class="py-3 px-4 text-right">Kutilgan</th>
                            <th class="py-3 px-4 text-right">Sanalgan</th>
                            <th class="py-3 px-4 text-right">Farq</th>
                            <th class="py-3 px-4">Holat</th>
                            <th class="py-3 px-4 text-center">Farq / Amallar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 text-slate-700">
                        @forelse ($sessions as $s)
                            <tr class="hover:bg-slate-100/30 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                    {{ $s->session_number }}
                                </td>
                                <td class="py-3 px-4 text-slate-700">
                                    {{ $s->account->name ?? '-' }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-xs font-mono text-slate-600">
                                        {{ $s->opened_at ? $s->opened_at->timezone('Asia/Tashkent')->format('Y-m-d H:i') : '-' }}
                                    </div>
                                    <div class="text-slate-700 font-medium">{{ $s->opener->name ?? '-' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    @if ($s->closed_at)
                                        <div class="text-xs font-mono text-slate-600">
                                            {{ $s->closed_at->timezone('Asia/Tashkent')->format('Y-m-d H:i') }}
                                        </div>
                                        <div class="text-slate-700 font-medium">{{ $s->closer->name ?? '-' }}</div>
                                    @else
                                        <span class="text-emerald-700 font-bold">Hozir ochiq</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right font-semibold text-slate-700 whitespace-nowrap">
                                    {{ number_format($s->opening_balance, 0, '.', ' ') }}
                                </td>
                                <td class="py-3 px-4 text-right font-semibold text-slate-700 whitespace-nowrap">
                                    {{ $s->expected_closing_balance !== null ? number_format($s->expected_closing_balance, 0, '.', ' ') : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 whitespace-nowrap">
                                    {{ $s->actual_closing_balance !== null ? number_format($s->actual_closing_balance, 0, '.', ' ') : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-black whitespace-nowrap">
                                    @if ($s->difference > 0)
                                        <span class="text-purple-700">+{{ number_format($s->difference, 0, '.', ' ') }}</span>
                                    @elseif ($s->difference < 0)
                                        <span class="text-rose-700">−{{ number_format(abs($s->difference), 0, '.', ' ') }}</span>
                                    @else
                                        <span class="text-slate-600">0</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @php
                                        $statusClass = match($s->status) {
                                            'OPEN' => 'bg-emerald-500/20 text-emerald-700 border-emerald-500/30',
                                            'CLOSED' => 'bg-slate-100 text-slate-600 border-slate-300',
                                            'PROVISIONAL' => 'bg-amber-500/20 text-amber-700 border-amber-500/30',
                                            default => 'bg-slate-100 text-slate-600 border-slate-300',
                                        };
                                    @endphp
                                    <span class="inline-block px-2.5 py-0.5 rounded text-xs font-bold border {{ $statusClass }}">
                                        <x-enum-label :value="$s->status" />
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    @if ($s->difference_status === 'PENDING_APPROVAL')
                                        <button wire:click="openApproveDiscrepancyModal({{ $s->id }})" class="px-3 py-1 rounded-lg bg-purple-500/20 hover:bg-purple-500/30 text-purple-700 border border-purple-500/30 text-xs font-bold">
                                            Farqni Ko'rish / Tasdiqlash
                                        </button>
                                    @elseif ($s->difference_status === 'APPROVED')
                                        <span class="text-xs text-emerald-700 font-semibold">✓ Tasdiqlangan</span>
                                    @elseif ($s->difference_status === 'REJECTED')
                                        <span class="text-xs text-slate-600 font-semibold">Rad etilgan</span>
                                    @else
                                        <span class="text-slate-600">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-600">
                                    Smenalar tarixi mavjud emas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $sessions->links() }}
            </div>
        </div>
    @endif

    {{-- MODAL: Smena Ochish --}}
    @if ($showOpenSessionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">Yangi Kassa Smenasini Ochish</h3>
                    <button wire:click="$set('showOpenSessionModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Kassa Hisobi</label>
                        <select wire:model="openAccountId" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-sky-500">
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} (Joriy qoldiq: {{ number_format($acc->balance, 0, '.', ' ') }} so'm)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Boshlang'ich Naqd Qoldiq (so'm)</label>
                        <input type="number" wire:model="openBalance" min="0" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-sky-500">
                        <div class="text-xs text-slate-600 mt-1">Smena boshlanishidagi sanalgan naqd summa.</div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Izoh (Ixtiyoriy)</label>
                        <input type="text" wire:model="openNotes" placeholder="Masalan: Ertalabki smena" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-sky-500">
                    </div>
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
                    <button wire:click="$set('showOpenSessionModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900">Bekor qilish</button>
                    <button wire:click="submitOpenSession" wire:loading.attr="disabled" wire:target="submitOpenSession" class="px-5 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-900 font-bold text-xs shadow-md">Smenani Ochish</button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: Kunni yakunlash --}}
    @if ($showCloseSessionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-lg p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">Kunni yakunlash</h3>
                    <button wire:click="$set('showCloseSessionModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>
                <div class="space-y-4">
                    {{-- Balanslar taqqosi --}}
                    <div class="grid grid-cols-2 gap-3 p-4 rounded-xl bg-slate-100/60 border border-slate-300/60">
                        <div>
                            <div class="text-xs text-slate-600">Tizim bo'yicha kutilgan:</div>
                            <div class="text-lg font-black text-slate-900 mt-0.5">
                                {{ number_format($closeExpectedBalance, 0, '.', ' ') }} so'm
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-600">Farq:</div>
                            <div class="text-lg font-black mt-0.5 {{ $closeDifference > 0 ? 'text-purple-700' : ($closeDifference < 0 ? 'text-rose-700' : 'text-emerald-700') }}">
                                {{ $closeDifference > 0 ? '+' : '' }}{{ number_format($closeDifference, 0, '.', ' ') }} so'm
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Kassir Sanagan Haqiqiy Naqd Summa (so'm)</label>
                        <input type="number" wire:model.live="closeActualBalance" min="0" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 font-bold focus:outline-none focus:border-sky-500">
                    </div>

                    @if ($closeDifference !== 0)
                        <div>
                            <label class="block text-xs font-semibold text-rose-700 mb-1">Farq Sababi (Majburiy!)</label>
                            <textarea wire:model="closeDifferenceReason" rows="2" placeholder="Farq sababini batafsil yozing..." class="w-full bg-slate-100 border border-rose-500/50 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-600 focus:outline-none focus:border-rose-400"></textarea>
                            @error('closeDifferenceReason')
                                <span class="text-xs text-rose-700">{{ $message }}</span>
                            @enderror
                            <div class="text-xs text-slate-600 mt-1">
                                ℹ️ Farq mavjud bo'lganda kassa balansi yashirin o'zgartirilmaydi; vakolatli xodim tomonidan ruxsatli hujjat orqali tasdiqlanadi.
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center space-x-2 pt-1">
                        <input type="checkbox" id="closeProvisional" wire:model="closeIsProvisional" class="rounded bg-slate-100 border-slate-300 text-sky-700 focus:ring-0">
                        <label for="closeProvisional" class="text-xs text-slate-700">
                            Offline qurilmalardan kechikkan sinxronizatsiya kutilmoqda (Vaqtincha / Provisional yopish)
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Umumiy Izoh (Ixtiyoriy)</label>
                        <input type="text" wire:model="closeNotes" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
                    <button wire:click="$set('showCloseSessionModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900">Bekor qilish</button>
                    <button wire:click="submitCloseSession" wire:loading.attr="disabled" wire:target="submitCloseSession" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-md">Smenani Yopish</button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: Kassa Farqini Tasdiqlash (Discrepancy Approval) --}}
    @if ($showApproveDiscrepancyModal && $selectedSession)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">Kassa Farqini Tasdiqlash</h3>
                    <button wire:click="$set('showApproveDiscrepancyModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>
                <div class="space-y-3">
                    <div class="p-3 bg-slate-100/60 rounded-xl space-y-1 text-xs">
                        <div>Smena: <span class="font-bold text-slate-900">#{{ $selectedSession->session_number }}</span></div>
                        <div>Kassa: <span class="text-slate-700">{{ $selectedSession->account->name ?? '-' }}</span></div>
                        <div>Kutilgan summa: <span class="font-semibold text-slate-700">{{ number_format($selectedSession->expected_closing_balance, 0, '.', ' ') }} so'm</span></div>
                        <div>Sanalgan summa: <span class="font-semibold text-slate-700">{{ number_format($selectedSession->actual_closing_balance, 0, '.', ' ') }} so'm</span></div>
                        <div class="pt-1 border-t border-slate-300 flex justify-between">
                            <span>Farq:</span>
                            <span class="font-black {{ $selectedSession->difference > 0 ? 'text-purple-700' : 'text-rose-700' }}">
                                {{ $selectedSession->difference > 0 ? '+' : '' }}{{ number_format($selectedSession->difference, 0, '.', ' ') }} so'm
                            </span>
                        </div>
                    </div>
                    <div class="p-3 bg-slate-100/40 rounded-xl border border-slate-300/60">
                        <div class="text-xs font-semibold text-slate-600">Kassir izohi:</div>
                        <div class="text-xs text-amber-700 font-medium mt-1">{{ $selectedSession->difference_reason ?: 'Sabab ko\'rsatilmagan' }}</div>
                    </div>
                    <div class="flex items-center space-x-2 pt-1">
                        <input type="checkbox" id="adjustLedger" wire:model="adjustLedgerOnApproval" class="rounded bg-slate-100 border-slate-300 text-sky-700 focus:ring-0">
                        <label for="adjustLedger" class="text-xs text-slate-700">
                            Farq hujjati orqali pul daftarini qonuniy to'g'rilash ({{ $selectedSession->difference > 0 ? 'Kirim ortiqchalik' : 'Chiqim kamomad' }})
                        </label>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                    <button wire:click="submitRejectDiscrepancy" wire:loading.attr="disabled" wire:target="submitRejectDiscrepancy" class="px-3 py-2 rounded-xl text-xs font-bold text-rose-700 hover:bg-rose-500/10">Rad etish</button>
                    <div class="flex space-x-2">
                        <button wire:click="$set('showApproveDiscrepancyModal', false)" class="px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900">Yopish</button>
                        <button wire:click="submitApproveDiscrepancy" wire:loading.attr="disabled" wire:target="submitApproveDiscrepancy" class="px-4 py-2 rounded-xl bg-purple-500 hover:bg-purple-400 text-slate-900 font-bold text-xs shadow-md">Farqni Tasdiqlash</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: Operatsion Xarajat --}}
    @if ($showExpenseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">Do‘kon xarajatini yozish</h3>
                    <button wire:click="$set('showExpenseModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">To'lov Qilinadigan Kassa</label>
                        <select wire:model.live="expenseAccountId" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} (Mavjud: {{ number_format($acc->balance, 0, '.', ' ') }} so'm)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Xarajat Toifasi</label>
                        <select wire:model="expenseCategory" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                            <option value="TRANSPORT">Transport / Yetkazib berish</option>
                            <option value="SALARY">Ish haqi / Oylik</option>
                            <option value="RENT">Ijara to'lovi</option>
                            <option value="UTILITIES">Kommunal xizmatlar</option>
                            <option value="UNLOADING">Yuk tushirish / Ishchi kuchi</option>
                            <option value="OTHER">Boshqa xarajat</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Xarajat Summasi (so'm)</label>
                        <input id="cash-expense-amount" aria-label="Xarajat summasi" type="number" wire:model.live.debounce.300ms="expenseAmount" min="1" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 font-bold focus:outline-none focus:border-sky-500">
                    </div>
                    @php($expenseAccount = $accounts->firstWhere('id', $expenseAccountId))
                    @if($expenseAccount)<div class="workspace-hint"><p>Hozir bu hisobda: <strong>{{ number_format($expenseAccount->balance, 0, '.', ' ') }} so‘m</strong></p><p>Xarajatdan keyin: <strong>{{ number_format($expenseAccount->balance - $expenseAmount, 0, '.', ' ') }} so‘m</strong></p>@if($expenseAmount > $expenseAccount->balance)<p class="text-rose-700">Pul yetmaydi. Summani kamaytiring yoki boshqa hisobni tanlang.</p>@endif</div>@endif
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Batafsil Izoh (Ixtiyoriy)</label>
                        <input type="text" wire:model="expenseDescription" placeholder="Masalan: Gazel yoqilg'isi uchun" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                    </div>
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
                    <button wire:click="$set('showExpenseModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900">Bekor qilish</button>
                    <button wire:click="submitExpense" wire:loading.attr="disabled" wire:target="submitExpense" class="px-5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-900 font-bold text-xs shadow-md">Xarajatni Chiqim Qilish</button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: Pulni boshqa hisobga o‘tkazish --}}
    @if ($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">Hisoblararo Pul O'tkazmasi</h3>
                    <button wire:click="$set('showTransferModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Qaysi Hisobdan (Chiqim)</label>
                        <select wire:model="transferFromAccountId" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} (Mavjud: {{ number_format($acc->balance, 0, '.', ' ') }} so'm)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Qaysi Hisobga (Kirim)</label>
                        <select wire:model="transferToAccountId" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} (Mavjud: {{ number_format($acc->balance, 0, '.', ' ') }} so'm)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">O'tkazma Summasi (so'm)</label>
                        <input type="number" wire:model="transferAmount" min="1" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 font-bold focus:outline-none focus:border-sky-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Izoh (Ixtiyoriy)</label>
                        <input type="text" wire:model="transferDescription" placeholder="Masalan: Naqd pulni bank hisobiga topshirish (Inkassatsiya)" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                    </div>
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
                    <button wire:click="$set('showTransferModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900">Bekor qilish</button>
                    <button wire:click="submitTransfer" wire:loading.attr="disabled" wire:target="submitTransfer" class="px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-slate-900 font-bold text-xs shadow-md">O'tkazish</button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: Egasi Mablag'i (Deposit / Draw) --}}
    @if ($showOwnerFundsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">Do'kon Egasi Mablag'i Boshqaruvi</h3>
                    <button wire:click="$set('showOwnerFundsModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Amal Turi</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="$set('ownerFundType', 'DEPOSIT')" class="py-2 rounded-xl text-xs font-bold border transition-all {{ $ownerFundType === 'DEPOSIT' ? 'bg-emerald-500/20 text-emerald-700 border-emerald-500' : 'bg-slate-100 text-slate-600 border-slate-300' }}">
                                + Mablag' Kiritish
                            </button>
                            <button type="button" wire:click="$set('ownerFundType', 'DRAW')" class="py-2 rounded-xl text-xs font-bold border transition-all {{ $ownerFundType === 'DRAW' ? 'bg-amber-500/20 text-amber-700 border-amber-500' : 'bg-slate-100 text-slate-600 border-slate-300' }}">
                                − Chiqarib Olish (Draw)
                            </button>
                        </div>
                    </div>
                    @if ($ownerFundType === 'DRAW')
                        <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-700">
                            ℹ️ <strong>Muhim:</strong> Egaga mablag' chiqarish (draw) operatsion xarajat hisoblanmaydi va sotuv yalpi foydasini kamaytirmaydi.
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Kassa Hisobi</label>
                        <select wire:model="ownerAccountId" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} (Mavjud: {{ number_format($acc->balance, 0, '.', ' ') }} so'm)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Summa (so'm)</label>
                        <input type="number" wire:model="ownerAmount" min="1" class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 font-bold focus:outline-none focus:border-sky-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Izoh (Ixtiyoriy)</label>
                        <input type="text" wire:model="ownerDescription" placeholder="Izoh..." class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-sky-500">
                    </div>
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
                    <button wire:click="$set('showOwnerFundsModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900">Bekor qilish</button>
                    <button wire:click="submitOwnerFunds" wire:loading.attr="disabled" wire:target="submitOwnerFunds" class="px-5 py-2.5 rounded-xl {{ $ownerFundType === 'DEPOSIT' ? 'bg-emerald-500 hover:bg-emerald-400' : 'bg-amber-500 hover:bg-amber-400' }} text-slate-900 font-bold text-xs shadow-md">
                        {{ $ownerFundType === 'DEPOSIT' ? 'Mablag\'ni Kiritish' : 'Mablag\'ni Chiqarish' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
