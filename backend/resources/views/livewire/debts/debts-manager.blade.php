<div class="space-y-6" wire:poll.30s>
    <!-- Bildirishnomalar -->
    @if ($successMessage)
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-xl">✓</span>
                <span class="font-medium text-sm">{{ $successMessage }}</span>
            </div>
            <button wire:click="clearMessages" class="text-emerald-700/60 hover:text-emerald-700 text-sm">✕</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-xl">⚠️</span>
                <span class="font-medium text-sm">{{ $errorMessage }}</span>
            </div>
            <button wire:click="clearMessages" class="text-rose-700/60 hover:text-rose-700 text-sm">✕</button>
        </div>
    @endif

    <!-- Asosiy Tablar -->
    <div class="flex flex-wrap border-b border-slate-200">
        <button
            wire:click="switchTab('customers')"
            class="px-6 py-3 font-semibold text-sm transition-all border-b-2 flex items-center space-x-2 {{ $activeTab === 'customers' ? 'border-blue-500 text-blue-700 bg-blue-500/5' : 'border-transparent text-slate-600 hover:text-slate-800' }}"
        >
            <span>👥</span>
            <span>Mijozlarning bizga qarzi</span>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $customerStats['debtors_count'] > 0 ? 'bg-rose-500/20 text-rose-700' : 'bg-slate-100 text-slate-600' }}">
                {{ $customerStats['debtors_count'] }}
            </span>
        </button>

        <button
            wire:click="switchTab('suppliers')"
            class="px-6 py-3 font-semibold text-sm transition-all border-b-2 flex items-center space-x-2 {{ $activeTab === 'suppliers' ? 'border-amber-500 text-amber-700 bg-amber-500/5' : 'border-transparent text-slate-600 hover:text-slate-800' }}"
        >
            <span>🏭</span>
            <span>Bizning yetkazuvchilarga qarzimiz</span>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $supplierStats['payables_count'] > 0 ? 'bg-amber-500/20 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                {{ $supplierStats['payables_count'] }}
            </span>
        </button>
    </div>

    <!-- STATISTIKA KARTALARI -->
    @if ($activeTab === 'customers')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Jami Olinadigan Qarz</div>
                <div class="mt-2 text-2xl font-black font-mono text-rose-700">
                    {{ number_format($customerStats['total_debt'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-600">so'm</span>
                </div>
                <div class="mt-1 text-xs text-rose-700/80">
                    {{ $customerStats['debtors_count'] }} ta mijoz qarzdor
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Mijozlar oldindan to‘lagan pul</div>
                <div class="mt-2 text-2xl font-black font-mono text-emerald-700">
                    {{ number_format($customerStats['total_advance'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-600">so'm</span>
                </div>
                <div class="mt-1 text-xs text-emerald-700/80">
                    {{ $customerStats['advance_count'] }} ta mijoz avansda
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Mijozlar bilan umumiy hisob</div>
                <div class="mt-2 text-2xl font-black font-mono {{ $customerStats['net_balance'] >= 0 ? 'text-blue-700' : 'text-emerald-700' }}">
                    {{ number_format($customerStats['net_balance'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-600">so'm</span>
                </div>
                <div class="mt-1 text-xs text-slate-600">
                    Qarzlar yig‘indisi − oldindan to‘lovlar
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Muddati O'tgan Qarzdorlar</div>
                <div class="mt-2 text-2xl font-black font-mono {{ $customerStats['overdue_count'] > 0 ? 'text-rose-700' : 'text-slate-600' }}">
                    {{ $customerStats['overdue_count'] }} <span class="text-xs font-normal text-slate-600">ta mijoz</span>
                </div>
                <div class="mt-1 text-xs text-slate-600">
                    Kelishilgan sana bo'yicha
                </div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Jami To'lanishi Kerak Qarz</div>
                <div class="mt-2 text-2xl font-black font-mono text-amber-700">
                    {{ number_format($supplierStats['total_payable'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-600">so'm</span>
                </div>
                <div class="mt-1 text-xs text-amber-700/80">
                    {{ $supplierStats['payables_count'] }} ta ta'minotchi oldida
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Bizning Avansimiz (Haqdorligimiz)</div>
                <div class="mt-2 text-2xl font-black font-mono text-emerald-700">
                    {{ number_format($supplierStats['total_prepaid'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-600">so'm</span>
                </div>
                <div class="mt-1 text-xs text-emerald-700/80">
                    {{ $supplierStats['prepaid_count'] }} ta ta'minotchi avansda
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Sof Ta'minotchi Majburiyati</div>
                <div class="mt-2 text-2xl font-black font-mono {{ $supplierStats['net_balance'] >= 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ number_format($supplierStats['net_balance'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-600">so'm</span>
                </div>
                <div class="mt-1 text-xs text-slate-600">
                    Qarzlar yig‘indisi − oldindan to‘lovlar
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200">
                <div class="text-xs font-medium text-slate-600">Muddati O'tgan Majburiyatlar</div>
                <div class="mt-2 text-2xl font-black font-mono {{ $supplierStats['overdue_count'] > 0 ? 'text-rose-700' : 'text-slate-600' }}">
                    {{ $supplierStats['overdue_count'] }} <span class="text-xs font-normal text-slate-600">ta ta'minotchi</span>
                </div>
                <div class="mt-1 text-xs text-slate-600">
                    Kelishilgan sana bo'yicha
                </div>
            </div>
        </div>
    @endif

    <!-- FILTR VA QIDIRUV -->
    <div class="p-4 rounded-2xl bg-slate-50/40 border border-slate-200 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="w-full md:w-96 relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-600 text-sm">🔍</span>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ $activeTab === 'customers' ? 'Mijoz, do\'kon nomi, telefon...' : 'Ta\'minotchi, kompaniya, telefon...' }}"
                class="w-full bg-slate-50/80 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-900 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
        </div>

        <div class="flex items-center space-x-2 w-full md:w-auto overflow-x-auto">
            <button
                wire:click="$set('statusFilter', 'all')"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $statusFilter === 'all' ? 'bg-slate-200 text-slate-900' : 'bg-slate-100/40 text-slate-600 hover:text-slate-900' }}"
            >
                Barchasi
            </button>
            <button
                wire:click="$set('statusFilter', 'debtors')"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $statusFilter === 'debtors' ? 'bg-rose-500/20 text-rose-700 border border-rose-500/30' : 'bg-slate-100/40 text-slate-600 hover:text-slate-900' }}"
            >
                {{ $activeTab === 'customers' ? 'Faqat Qarzdorlar' : 'Faqat Qarzimiz borlar' }}
            </button>
            <button
                wire:click="$set('statusFilter', 'advance')"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $statusFilter === 'advance' ? 'bg-emerald-500/20 text-emerald-700 border border-emerald-500/30' : 'bg-slate-100/40 text-slate-600 hover:text-slate-900' }}"
            >
                Faqat Avansdagilar
            </button>
            <button
                wire:click="$set('statusFilter', 'overdue')"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $statusFilter === 'overdue' ? 'bg-amber-500/20 text-amber-700 border border-amber-500/30' : 'bg-slate-100/40 text-slate-600 hover:text-slate-900' }}"
            >
                Muddati O'tganlar
            </button>
        </div>
    </div>

    <!-- JADVAL -->
    <div class="rounded-2xl bg-slate-50/60 border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700">
                <thead class="bg-slate-50/80 text-xs font-semibold text-slate-600  border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Taraf / Nomi</th>
                        <th class="px-6 py-4">Telefon va Manzil</th>
                        <th class="px-6 py-4">Qarz yoki oldindan to‘lov</th>
                        <th class="px-6 py-4">Limit / Muddat</th>
                        <th class="px-6 py-4 text-right">Amallar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/60">
                    @forelse ($items as $item)
                        @php
                            $isCustomer = ($activeTab === 'customers');
                            $balance = $isCustomer ? (int) $item->current_debt : (int) $item->balance;
                            $limit = $isCustomer ? (int) $item->debt_limit : (int) $item->credit_limit;
                            $dueDate = $item->payment_due_date ? Carbon::parse($item->payment_due_date) : null;
                            $isOverdue = $balance > 0 && $dueDate && $dueDate->isPast();
                        @endphp
                        <tr class="hover:bg-slate-100/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900 text-base">
                                    {{ $item->name }}
                                </div>
                                <div class="text-xs text-slate-600">
                                    {{ $isCustomer ? ($item->store_name ? 'Do\'kon: '.$item->store_name : 'Jismoniy shaxs') : ($item->company_name ? 'Kompaniya: '.$item->company_name : 'Ta\'minotchi') }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-xs">
                                <div class="text-slate-700 font-mono">
                                    {{ $item->phone ?: 'Telefon yo\'q' }}
                                </div>
                                <div class="text-slate-600 mt-0.5">
                                    {{ $item->address ?: '-' }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                @if ($balance > 0)
                                    <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold font-mono bg-rose-500/10 text-rose-700 border border-rose-500/20">
                                        Qarz: {{ number_format($balance, 0, '.', ' ') }} so'm
                                    </div>
                                @elseif ($balance < 0)
                                    <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold font-mono bg-emerald-500/10 text-emerald-700 border border-emerald-500/20">
                                        Avans: {{ number_format(abs($balance), 0, '.', ' ') }} so'm
                                    </div>
                                @else
                                    <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-mono text-slate-600 bg-slate-100">
                                        0 so'm (Hisob teng)
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-xs">
                                <div>
                                    <span class="text-slate-600">Limit:</span>
                                    <span class="font-mono {{ $limit > 0 && $balance > $limit ? 'text-rose-700 font-bold' : 'text-slate-700' }}">
                                        {{ $limit > 0 ? number_format($limit, 0, '.', ' ').' so\'m' : 'Cheksiz' }}
                                    </span>
                                </div>
                                <div class="mt-0.5">
                                    <span class="text-slate-600">To'lov sanasi:</span>
                                    @if ($dueDate)
                                        <span class="font-mono {{ $isOverdue ? 'text-rose-700 font-bold' : 'text-slate-700' }}">
                                            {{ $dueDate->format('d.m.Y') }}
                                            @if ($isOverdue)
                                                <span class="text-xs bg-rose-500/20 text-rose-700 px-1 py-0.2 rounded">Kechikkan</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-slate-600">Belgilanmagan</span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right space-x-2">
                                @if ($isCustomer)
                                    <button
                                        type="button"
                                        wire:click="openCustomerPaymentModal({{ $item->id }})"
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-lg text-xs font-medium transition-colors shadow-sm"
                                    >
                                        To'lov qabul qilish
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="openSupplierPaymentModal({{ $item->id }})"
                                        class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-slate-900 rounded-lg text-xs font-medium transition-colors shadow-sm"
                                    >
                                        To'lov qilish
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    wire:click="openStatementModal('{{ $isCustomer ? 'CUSTOMER' : 'SUPPLIER' }}', {{ $item->id }})"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-medium transition-colors border border-slate-300"
                                >
                                    Ko'chirma
                                </button>

                                <button
                                    type="button"
                                    wire:click="openSettingsModal('{{ $isCustomer ? 'CUSTOMER' : 'SUPPLIER' }}', {{ $item->id }})"
                                    class="px-2 py-1.5 text-slate-600 hover:text-slate-900 rounded-lg text-xs transition-colors"
                                    title="Kredit limiti va to'lov sanasini sozlash"
                                >
                                    ⚙️
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-600">
                                Hech qanday ma'lumot topilmadi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items && $items->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50/40">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: MIJOZ TO'LOVINI QABUL QILISH -->
    <!-- ======================================================== -->
    @if ($showCustomerPaymentModal && $selectedCustomer)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Mijozdan To'lov Qabul Qilish</h3>
                        <p class="text-xs text-slate-600 mt-0.5">
                            {{ $selectedCustomer->display_name }}
                        </p>
                    </div>
                    <button wire:click="$set('showCustomerPaymentModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50/60 border border-slate-200/80 flex items-center justify-between">
                    <span class="text-xs text-slate-600">Joriy qarz / avans:</span>
                    <span class="text-sm font-mono font-bold {{ $selectedCustomer->current_debt > 0 ? 'text-rose-700' : ($selectedCustomer->current_debt < 0 ? 'text-emerald-700' : 'text-slate-600') }}">
                        @if ($selectedCustomer->current_debt > 0)
                            Qarz: {{ number_format($selectedCustomer->current_debt, 0, '.', ' ') }} so'm
                        @elseif ($selectedCustomer->current_debt < 0)
                            Avans: {{ number_format(abs($selectedCustomer->current_debt), 0, '.', ' ') }} so'm
                        @else
                            0 so'm
                        @endif
                    </span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">To‘lov summasi (so‘m)</label>
                        <input
                            type="number"
                            wire:model.live="paymentAmount"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-lg font-mono font-bold text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            placeholder="Masalan: 100000"
                        />
                        <div class="flex flex-wrap gap-2 mt-2">
                            @if ($selectedCustomer->current_debt > 0)
                                <button
                                    type="button"
                                    wire:click="setFullCustomerDebt"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-blue-700 rounded-lg"
                                >
                                    To'liq qarz ({{ number_format($selectedCustomer->current_debt, 0, '.', ' ') }})
                                </button>
                            @endif
                            <button type="button" wire:click="setQuickCustomerAmount(100000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-slate-700 rounded-lg">100 000</button>
                            <button type="button" wire:click="setQuickCustomerAmount(500000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-slate-700 rounded-lg">500 000</button>
                            <button type="button" wire:click="setQuickCustomerAmount(1000000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-slate-700 rounded-lg">1 000 000</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Kassa hisobi</label>
                            <select
                                wire:model.live="paymentCashAccountId"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            >
                                @foreach ($cashAccounts as $acc)
                                    <option value="{{ $acc->id }}">
                                        {{ $acc->name }} ({{ number_format($acc->balance, 0, '.', ' ') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">To'lov usuli</label>
                            <select
                                wire:model.live="paymentMethod"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="CASH">Naqd pul</option>
                                <option value="CARD">Plastik karta</option>
                                <option value="BANK">Bank o'tkazmasi</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Izoh (ixtiyoriy)</label>
                        <input
                            type="text"
                            wire:model="paymentNotes"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            placeholder="Qarz to'lovi haqida izoh..."
                        />
                    </div>

                    @php
                        $inputAmount = (int) str_replace([' ', ','], '', $this->paymentAmount ?? '0');
                        $currentDebt = (int) $selectedCustomer->current_debt;
                        $isExcess = ($currentDebt > 0 && $inputAmount > $currentDebt);
                        $excessDiff = $inputAmount - $currentDebt;
                    @endphp

                    @if ($isExcess)
                        <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-700 text-xs space-y-2">
                            <div class="flex items-center space-x-2">
                                <span>⚠️</span>
                                <span class="font-bold">Qarzdan ortiq summa: {{ number_format($excessDiff, 0, '.', ' ') }} so'm</span>
                            </div>
                            <p class="text-xs text-amber-700/80">
                                Ushbu summa mijozning avansi (haqdorligi) sifatida signed balansda saqlanadi.
                            </p>
                            <label class="flex items-center space-x-2 cursor-pointer pt-1">
                                <input
                                    type="checkbox"
                                    wire:model="confirmExcessAsAdvance"
                                    class="rounded bg-slate-50 border-slate-300 text-blue-600 focus:ring-0"
                                />
                                <span class="font-semibold text-slate-900">Ortiqcha pulni avans sifatida tasdiqlayman</span>
                            </label>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end space-x-3 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        wire:click="$set('showCustomerPaymentModal', false)"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-medium"
                    >
                        Bekor qilish
                    </button>
                    <button
                        type="button"
                        wire:click="submitCustomerPayment" wire:loading.attr="disabled" wire:target="submitCustomerPayment"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 disabled:opacity-50"
                    >
                        <span wire:loading.remove>Tasdiqlash va Kirim qilish</span>
                        <span wire:loading>Jarayonda...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ======================================================== -->
    <!-- MODAL 2: TA'MINOTCHIGA TO'LOV QILISH -->
    <!-- ======================================================== -->
    @if ($showSupplierPaymentModal && $selectedSupplier)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Ta'minotchiga To'lov Qilish</h3>
                        <p class="text-xs text-slate-600 mt-0.5">
                            {{ $selectedSupplier->display_name }}
                        </p>
                    </div>
                    <button wire:click="$set('showSupplierPaymentModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50/60 border border-slate-200/80 flex items-center justify-between">
                    <span class="text-xs text-slate-600">Bizning qarzimiz / avansimiz:</span>
                    <span class="text-sm font-mono font-bold {{ $selectedSupplier->balance > 0 ? 'text-amber-700' : ($selectedSupplier->balance < 0 ? 'text-emerald-700' : 'text-slate-600') }}">
                        @if ($selectedSupplier->balance > 0)
                            Qarzimiz: {{ number_format($selectedSupplier->balance, 0, '.', ' ') }} so'm
                        @elseif ($selectedSupplier->balance < 0)
                            Avansimiz: {{ number_format(abs($selectedSupplier->balance), 0, '.', ' ') }} so'm
                        @else
                            0 so'm
                        @endif
                    </span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">To‘lov summasi (so‘m)</label>
                        <input
                            type="number"
                            wire:model.live="supplierPaymentAmount"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-lg font-mono font-bold text-slate-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            placeholder="Masalan: 500000"
                        />
                        <div class="flex flex-wrap gap-2 mt-2">
                            @if ($selectedSupplier->balance > 0)
                                <button
                                    type="button"
                                    wire:click="setFullSupplierPayable"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-amber-700 rounded-lg"
                                >
                                    To'liq qarz ({{ number_format($selectedSupplier->balance, 0, '.', ' ') }})
                                </button>
                            @endif
                            <button type="button" wire:click="setQuickSupplierAmount(100000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-slate-700 rounded-lg">100 000</button>
                            <button type="button" wire:click="setQuickSupplierAmount(500000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-slate-700 rounded-lg">500 000</button>
                            <button type="button" wire:click="setQuickSupplierAmount(1000000)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-xs text-slate-700 rounded-lg">1 000 000</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Kassa hisobi (Chiqim)</label>
                            <select
                                wire:model.live="supplierPaymentCashAccountId"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            >
                                @foreach ($cashAccounts as $acc)
                                    <option value="{{ $acc->id }}">
                                        {{ $acc->name }} ({{ number_format($acc->balance, 0, '.', ' ') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">To'lov usuli</label>
                            <select
                                wire:model.live="supplierPaymentMethod"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            >
                                <option value="CASH">Naqd pul</option>
                                <option value="CARD">Plastik karta</option>
                                <option value="BANK">Bank o'tkazmasi</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Izoh (ixtiyoriy)</label>
                        <input
                            type="text"
                            wire:model="supplierPaymentNotes"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            placeholder="Ta'minotchiga to'lov izohi..."
                        />
                    </div>

                    @php
                        $inputSupAmount = (int) str_replace([' ', ','], '', $this->supplierPaymentAmount ?? '0');
                        $currentPayable = (int) $selectedSupplier->balance;
                        $isSupExcess = ($currentPayable > 0 && $inputSupAmount > $currentPayable);
                        $excessSupDiff = $inputSupAmount - $currentPayable;
                    @endphp

                    @if ($isSupExcess)
                        <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-700 text-xs space-y-2">
                            <div class="flex items-center space-x-2">
                                <span>⚠️</span>
                                <span class="font-bold">Qarzdan ortiq to'lov: {{ number_format($excessSupDiff, 0, '.', ' ') }} so'm</span>
                            </div>
                            <p class="text-xs text-amber-700/80">
                                Ushbu summa ta'minotchiga berilgan avans sifatida signed daftarda saqlanadi.
                            </p>
                            <label class="flex items-center space-x-2 cursor-pointer pt-1">
                                <input
                                    type="checkbox"
                                    wire:model="supplierConfirmExcessAsAdvance"
                                    class="rounded bg-slate-50 border-slate-300 text-amber-600 focus:ring-0"
                                />
                                <span class="font-semibold text-slate-900">Ortiqcha to'lovni avans sifatida tasdiqlayman</span>
                            </label>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end space-x-3 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        wire:click="$set('showSupplierPaymentModal', false)"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-medium"
                    >
                        Bekor qilish
                    </button>
                    <button
                        type="button"
                        wire:click="submitSupplierPayment" wire:loading.attr="disabled" wire:target="submitSupplierPayment"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 bg-amber-600 hover:bg-amber-500 text-slate-900 rounded-xl text-xs font-bold shadow-lg shadow-amber-500/20 disabled:opacity-50"
                    >
                        <span wire:loading.remove>Tasdiqlash va Chiqim qilish</span>
                        <span wire:loading>Jarayonda...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ======================================================== -->
    <!-- MODAL 3: HISOB KO'CHIRMASI (STATEMENT) -->
    <!-- ======================================================== -->
    @if ($showStatementModal && $statementData)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="p-6 border-b border-slate-200 flex items-center justify-between bg-slate-50/60">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xl">{{ $statementPartyType === 'CUSTOMER' ? '👥' : '🏭' }}</span>
                            <h3 class="text-lg font-bold text-slate-900">
                                {{ $statementPartyType === 'CUSTOMER' ? 'Mijozning hisob tarixi' : 'Yetkazuvchining hisob tarixi' }}
                            </h3>
                        </div>
                        <p class="text-xs text-slate-600 mt-1">
                            @if ($statementPartyType === 'CUSTOMER')
                                {{ $statementData['customer']['name'] }}
                                {{ $statementData['customer']['store_name'] ? ' — '.$statementData['customer']['store_name'] : '' }}
                                ({{ $statementData['customer']['phone'] ?: 'tel yo\'q' }})
                            @else
                                {{ $statementData['supplier']['name'] }}
                                {{ $statementData['supplier']['company_name'] ? ' — '.$statementData['supplier']['company_name'] : '' }}
                                ({{ $statementData['supplier']['phone'] ?: 'tel yo\'q' }})
                            @endif
                        </p>
                    </div>
                    <button wire:click="$set('showStatementModal', false)" class="text-slate-600 hover:text-slate-900 text-lg">✕</button>
                </div>

                <!-- Davr va Filtrlar -->
                <div class="p-4 border-b border-slate-200 bg-slate-50/40 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="text-slate-600">Davr:</span>
                        <input
                            type="date"
                            wire:model="statementStartDate"
                            wire:change="loadStatement"
                            class="bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1 text-slate-900 text-xs"
                        />
                        <span class="text-slate-600">—</span>
                        <input
                            type="date"
                            wire:model="statementEndDate"
                            wire:change="loadStatement"
                            class="bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1 text-slate-900 text-xs"
                        />
                    </div>

                    <div class="flex items-center space-x-1">
                        <button type="button" wire:click="setStatementPreset('today')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded">Bugun</button>
                        <button type="button" wire:click="setStatementPreset('yesterday')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded">Kecha</button>
                        <button type="button" wire:click="setStatementPreset('this_week')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded">Shu hafta</button>
                        <button type="button" wire:click="setStatementPreset('this_month')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded">Shu oy</button>
                        <button type="button" wire:click="setStatementPreset('all')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded">Barchasi</button>
                    </div>
                </div>

                <!-- Boshlang'ich -> Harakatlar -> Yakuniy Qoldiq Formulalar Paneli -->
                <div class="p-4 bg-slate-50 border-b border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                    <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-200">
                        <div class="text-xs uppercase font-semibold text-slate-600">Boshlang'ich Qoldiq</div>
                        <div class="mt-1 font-mono font-bold text-sm text-slate-800">
                            {{ number_format($statementData['opening_balance'], 0, '.', ' ') }} so'm
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-200">
                        <div class="text-xs uppercase font-semibold text-slate-600">
                            {{ $statementPartyType === 'CUSTOMER' ? '+ Nasiya Savdolar' : '+ Tovar Kirimlari' }}
                        </div>
                        <div class="mt-1 font-mono font-bold text-sm text-rose-700">
                            {{ number_format($statementData['total_debit'], 0, '.', ' ') }} so'm
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-200">
                        <div class="text-xs uppercase font-semibold text-slate-600">
                            {{ $statementPartyType === 'CUSTOMER' ? '- To\'lovlar / Kredit' : '- To\'lovlarimiz / Chiqim' }}
                        </div>
                        <div class="mt-1 font-mono font-bold text-sm text-emerald-700">
                            {{ number_format($statementData['total_credit'], 0, '.', ' ') }} so'm
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-200">
                        <div class="text-xs uppercase font-semibold text-slate-600">Yakuniy Qoldiq</div>
                        <div class="mt-1 font-mono font-bold text-sm {{ $statementData['closing_balance'] > 0 ? 'text-rose-700' : ($statementData['closing_balance'] < 0 ? 'text-emerald-700' : 'text-slate-700') }}">
                            {{ number_format($statementData['closing_balance'], 0, '.', ' ') }} so'm
                        </div>
                    </div>
                </div>

                @if ($statementData['is_reconciled'])
                    <div class="px-6 py-2 bg-emerald-500/10 border-b border-emerald-500/20 text-emerald-700 text-xs flex items-center justify-between">
                        <span>✓ Boshlang'ich qoldiq + harakatlar = yakuniy ko'chirma matematik tengligi to'liq tasdiqlandi.</span>
                        <span class="font-mono text-xs">Vaqt mintaqasi: Asia/Tashkent (sekund aniqligida)</span>
                    </div>
                @endif

                <!-- Harakatlar jadvali -->
                <div class="p-4 overflow-y-auto flex-1 space-y-3">
                    @forelse ($statementData['movements'] as $m)
                        <div class="p-3 rounded-xl bg-slate-50/60 border border-slate-200 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center space-x-2">
                                    <span class="font-mono text-slate-600">{{ $m['created_at'] }}</span>
                                    <span class="font-semibold text-slate-900 px-2 py-0.5 rounded bg-slate-100">
                                        {{ $m['type_label'] }}
                                    </span>
                                    @if ($m['document_number'])
                                        <span class="font-mono text-blue-700">#{{ $m['document_number'] }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold text-sm {{ $m['debit'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                        {{ $m['debit'] > 0 ? '+'.number_format($m['debit'], 0, '.', ' ') : '-'.number_format($m['credit'], 0, '.', ' ') }} so'm
                                    </span>
                                </div>
                            </div>

                            <!-- Mahsulotlar tafsiloti (agar mavjud bo'lsa) -->
                            @if (! empty($m['items_summary']))
                                <div class="p-2 rounded-lg bg-slate-50/80 border border-slate-200 text-xs space-y-1">
                                    <div class="font-semibold text-slate-600">Tarkibi (Tovar va hajm):</div>
                                    @foreach ($m['items_summary'] as $it)
                                        <div class="flex justify-between text-slate-700 font-mono">
                                            <span>{{ $it['display_name'] }} × {{ $it['quantity'] }} dona</span>
                                            <span>{{ number_format($it['total'], 0, '.', ' ') }} so'm</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Audit va CCTV ma'lumotlari -->
                            <div class="flex flex-wrap items-center justify-between text-xs text-slate-600 pt-1 border-t border-slate-200/60">
                                <div class="flex items-center space-x-3">
                                    <span>Mas'ul: <strong class="text-slate-700">{{ $m['actor_name'] }}</strong></span>
                                    @if ($m['goods_picked_up_at'])
                                        <span class="text-amber-700">📦 Tovar olingan vaqt: <strong>{{ $m['goods_picked_up_at'] }}</strong></span>
                                    @endif
                                    @if ($m['notes'])
                                        <span>Izoh: {{ $m['notes'] }}</span>
                                    @endif
                                </div>
                                <div class="font-mono text-slate-600">
                                    Oraliq qoldiq: <strong class="text-slate-800">{{ number_format($m['balance_after'], 0, '.', ' ') }} so'm</strong>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-slate-600 text-xs">
                            Tanlangan davr uchun hech qanday harakat qayd etilmagan.
                        </div>
                    @endforelse
                </div>

                <div class="p-4 border-t border-slate-200 bg-slate-50/60 flex justify-end">
                    <button
                        type="button"
                        wire:click="$set('showStatementModal', false)"
                        class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold"
                    >
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ======================================================== -->
    <!-- MODAL 4: SOZLAMALAR (KREDIT LIMITI VA TO'LOV SANASI) -->
    <!-- ======================================================== -->
    @if ($showSettingsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl w-full max-w-md p-6 space-y-5 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Qarz chegarasi va to‘lov muddati</h3>
                        <p class="text-xs text-slate-600 mt-0.5">{{ $settingsPartyName }}</p>
                    </div>
                    <button wire:click="$set('showSettingsModal', false)" class="text-slate-600 hover:text-slate-900">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            {{ $settingsPartyType === 'CUSTOMER' ? 'Maksimal Kredit Limiti (UZS)' : 'Kelishilgan Kredit Limiti (UZS)' }}
                        </label>
                        <input
                            type="number"
                            wire:model="settingsDebtLimit"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            placeholder="0 (cheksiz bo'lsa 0 qoldiring)"
                        />
                        <p class="text-xs text-slate-600 mt-1">Ushbu summadan oshganda tizim ogohlantiradi.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            Kelishilgan To'lov Sanasi (Muddat)
                        </label>
                        <input
                            type="date"
                            wire:model="settingsPaymentDueDate"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <p class="text-xs text-slate-600 mt-1">
                            Umumiy qarz uchun kelishilgan sana. Qarz mavjud bo'lsa va sana o'tsa, kechikkan deb belgilanadi.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        wire:click="$set('showSettingsModal', false)"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-medium"
                    >
                        Bekor qilish
                    </button>
                    <button
                        type="button"
                        wire:click="saveSettings" wire:loading.attr="disabled" wire:target="saveSettings"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-xl text-xs font-bold"
                    >
                        Saqlash
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
