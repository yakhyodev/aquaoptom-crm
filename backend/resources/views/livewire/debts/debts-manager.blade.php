<div class="space-y-6" wire:poll.30s>
    @if($successMessage)<div class="trade-alert" role="status">✓ {{ $successMessage }}</div>@endif
    @if($errorMessage && ! $showCustomerPaymentModal && ! $showSupplierPaymentModal)<div class="trade-alert trade-alert-error" role="alert">{{ $errorMessage }}</div>@endif

    <div class="grid gap-4 sm:grid-cols-2" aria-label="Qarz bilan nima qilmoqchisiz?">
        <button type="button" wire:click="switchTab('customers')" class="trade-card text-left {{ $activeTab === 'customers' ? 'ring-2 ring-blue-500' : '' }}" aria-pressed="{{ $activeTab === 'customers' ? 'true' : 'false' }}">
            <span class="text-3xl">📥</span><h2 class="mt-3 text-xl font-bold text-slate-900">Mijoz qarzini to‘ladi</h2>
            <p class="mt-2 text-slate-600">Mijozni toping → «Qarz to‘lovini olish» → summani yozing.</p>
            <p class="mt-4 font-semibold text-blue-700">Mijozlarning bizga qarzi: {{ number_format($customerStats['total_debt'], 0, '.', ' ') }} so‘m</p>
            <p class="mt-1 text-sm text-slate-600">{{ $customerStats['debtors_count'] }} ta qarzdor mijoz · Pul kassaga qo‘shiladi.</p>
        </button>
        <button type="button" wire:click="switchTab('suppliers')" class="trade-card text-left {{ $activeTab === 'suppliers' ? 'ring-2 ring-amber-500' : '' }}" aria-pressed="{{ $activeTab === 'suppliers' ? 'true' : 'false' }}">
            <span class="text-3xl">📤</span><h2 class="mt-3 text-xl font-bold text-slate-900">Yetkazuvchiga pul bermoqchiman</h2>
            <p class="mt-2 text-slate-600">Yetkazuvchini toping → «Qarzni to‘lash» → summani yozing.</p>
            <p class="mt-4 font-semibold text-amber-700">Bizning yetkazuvchilarga qarzimiz: {{ number_format($supplierStats['total_payable'], 0, '.', ' ') }} so‘m</p>
            <p class="mt-1 text-sm text-slate-600">{{ $supplierStats['payables_count'] }} ta yetkazuvchiga qarzmiz · Pul kassadan ayriladi.</p>
        </button>
    </div>
    @php
$isCustomer = $activeTab === 'customers';
@endphp
    <section class="trade-card space-y-5">
        <div><h2 class="text-xl font-bold">{{ $isCustomer ? 'Qaysi mijoz to‘ladi?' : 'Qaysi yetkazuvchiga to‘laymiz?' }}</h2><p class="mt-1 text-slate-600">{{ $isCustomer ? 'To‘lagan pulini shu yerda yozing. Mijozning umumiy qarzi kamayadi.' : 'Berilgan pulni shu yerda yozing. Yetkazuvchiga umumiy qarzimiz kamayadi.' }}</p></div>
        <div class="flex flex-wrap items-end gap-3">
            <div class="trade-field flex-1 min-w-0"><label for="debt-search">{{ $isCustomer ? 'Mijozni topish' : 'Yetkazuvchini topish' }}</label><input id="debt-search" wire:model.live.debounce.300ms="search" placeholder="Ism, do‘kon yoki telefon bo‘yicha qidiring"></div>
            <div class="trade-field"><label for="debt-filter">Kimlar ko‘rinsin?</label><select id="debt-filter" wire:model.live="statusFilter"><option value="all">Barchasi</option><option value="debtors">Faqat qarzi borlar</option><option value="advance">Oldindan to‘langanlar</option><option value="overdue">To‘lov muddati o‘tganlar</option></select></div>
        </div>
        @if(($isCustomer ? $customerStats['total_advance'] : $supplierStats['total_prepaid']) > 0)
            <p class="workspace-hint">Oldindan to‘langan pul: <strong>{{ number_format($isCustomer ? $customerStats['total_advance'] : $supplierStats['total_prepaid'], 0, '.', ' ') }} so‘m</strong>. Bu boshqa odamning qarzini kamaytirmaydi.</p>
        @endif
        <div class="space-y-3">
        @forelse($items as $item)
            @php
$balance = (int) ($isCustomer ? $item->current_debt : $item->balance);
@endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5" wire:key="debt-{{ $activeTab }}-{{ $item->id }}">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0"><h3 class="text-lg font-bold break-words">{{ $item->name }}</h3>@if($isCustomer && $item->store_name)<p class="text-sm font-semibold text-blue-700">{{ $item->store_name }}</p>@elseif(! $isCustomer && $item->company_name)<p class="text-sm text-slate-600">{{ $item->company_name }}</p>@endif<p class="mt-1 text-sm text-slate-600">{{ $item->phone ?: 'Telefon kiritilmagan' }} @if($item->address) · {{ $item->address }} @endif</p></div>
                    <div class="text-left sm:text-right">
                        <p class="text-sm text-slate-600">{{ $balance > 0 ? ($isCustomer ? 'Bizga to‘lashi kerak' : 'Biz to‘lashimiz kerak') : ($balance < 0 ? 'Oldindan to‘langan' : 'Qarz yo‘q') }}</p>
                        <strong class="text-xl {{ $balance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ number_format(abs($balance), 0, '.', ' ') }} so‘m</strong>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" wire:click="{{ $isCustomer ? 'openCustomerPaymentModal' : 'openSupplierPaymentModal' }}({{ $item->id }})" class="trade-button trade-button-primary">{{ $isCustomer ? 'Qarz to‘lovini olish' : 'Qarzni to‘lash' }}</button>
                    <button type="button" wire:click="openStatementModal('{{ $isCustomer ? 'CUSTOMER' : 'SUPPLIER' }}', {{ $item->id }})" class="trade-button trade-button-secondary">Olingan mahsulotlar va to‘lovlar</button>
                    <button type="button" wire:click="openSettingsModal('{{ $isCustomer ? 'CUSTOMER' : 'SUPPLIER' }}', {{ $item->id }})" class="trade-button trade-button-secondary">Qarz muddati</button>
                </div>
            </article>
        @empty
            <div class="trade-empty"><span>🤝</span><h3>{{ $search || $statusFilter !== 'all' ? 'Bu qidiruv bo‘yicha hech kim topilmadi' : ($isCustomer ? 'Hali mijozlar yo‘q' : 'Hali yetkazuvchilar yo‘q') }}</h3><p>{{ $isCustomer ? 'Mijozga nasiya sotuv qilsangiz, qarzi shu yerda ko‘rinadi.' : 'Mahsulot kirimida yetkazuvchini tanlang. To‘lanmagan summa shu yerda ko‘rinadi.' }}</p><a href="{{ url($isCustomer ? '/sotuv?mode=customer' : '/inward') }}" class="trade-button trade-button-secondary mt-3">{{ $isCustomer ? 'Sotuvga o‘tish' : 'Mahsulot kirimiga o‘tish' }}</a></div>
        @endforelse
        </div>
        {{ $items->links() }}
    </section>

    @if($showCustomerPaymentModal && $selectedCustomer)
        @php
            $inputAmount = (int) str_replace([' ', ','], '', $paymentAmount ?? '0');
            $partyBalance = (int) $selectedCustomer->current_debt;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" x-on:keydown.escape.window="$wire.set('showCustomerPaymentModal', false)">
            <form wire:submit="submitCustomerPayment" role="dialog" aria-modal="true" aria-labelledby="paymentAmount-title" class="trade-card w-full max-w-lg max-h-[90dvh] overflow-y-auto space-y-5 shadow-2xl">
                <div class="flex justify-between items-start gap-3"><div><h2 id="paymentAmount-title" class="text-xl font-bold">Mijoz qarz to‘lovini qabul qilish</h2><p class="mt-1 text-slate-600">{{ $selectedCustomer->display_name }}</p></div><button type="button" wire:click="$set('showCustomerPaymentModal', false)" aria-label="To‘lov oynasini yopish" class="trade-remove">×</button></div>
                @if($errorMessage)<div class="trade-alert trade-alert-error" role="alert">{{ $errorMessage }}</div>@endif
                <x-validation-errors />
                <div class="workspace-hint"><p>{{ $partyBalance >= 0 ? 'Hozirgi qarz' : 'Oldindan to‘langan pul' }}: <strong>{{ number_format(abs($partyBalance), 0, '.', ' ') }} so‘m</strong></p><p>Kassada hozir: <strong>{{ number_format($paymentCashBalance, 0, '.', ' ') }} so‘m</strong></p></div>
                <div class="trade-field"><label for="paymentAmount-input">Mijoz qancha pul berdi?</label><div class="trade-input-unit"><input id="paymentAmount-input" type="number" min="1" step="1" inputmode="numeric" wire:model.live.debounce.300ms="paymentAmount" placeholder="Summani yozing"><span>so‘m</span></div></div>
                @if($partyBalance > 0)<button type="button" wire:click="setFullCustomerDebt" class="trade-button trade-button-secondary">Qarzni to‘liq to‘lash: {{ number_format($partyBalance, 0, '.', ' ') }} so‘m</button>@endif
                <div class="trade-summary"><div><span>To‘lovdan keyingi qarz</span><strong>{{ number_format(max(0, $partyBalance - $inputAmount), 0, '.', ' ') }} so‘m</strong></div><div><span>To‘lovdan keyin kassada</span><strong>{{ number_format($paymentCashBalance + $inputAmount, 0, '.', ' ') }} so‘m</strong></div></div>
                @if($inputAmount > max(0, $partyBalance))<label class="workspace-hint flex items-start gap-3"><input type="checkbox" wire:model="confirmExcessAsAdvance"><span>Qarzdan ortiq {{ number_format($inputAmount - max(0, $partyBalance), 0, '.', ' ') }} so‘mni keyingi xaridlar uchun oldindan to‘lov sifatida saqlashga roziman.</span></label>@endif
                <details class="trade-details"><summary>Izoh <span>ixtiyoriy</span></summary><div class="trade-field"><label for="paymentNotes-input">Izoh</label><input id="paymentNotes-input" wire:model="paymentNotes" placeholder="To‘lov haqida izoh"></div></details>
                <p class="text-sm text-slate-600">Pul kassaga qo‘shiladi va mijozning qarzi kamayadi. Saqlangach, oyna yopiladi.</p>
                <div class="flex flex-wrap justify-end gap-3"><button type="button" wire:click="$set('showCustomerPaymentModal', false)" class="trade-button trade-button-secondary">Bekor qilish</button><button type="submit" wire:loading.attr="disabled" wire:target="submitCustomerPayment" class="trade-button trade-button-primary"><span wire:loading.remove wire:target="submitCustomerPayment">To‘lovni qabul qilish</span><span wire:loading wire:target="submitCustomerPayment">Saqlanyapti…</span></button></div>
            </form>
        </div>
    @endif

    @if($showSupplierPaymentModal && $selectedSupplier)
        @php
            $inputAmount = (int) str_replace([' ', ','], '', $supplierPaymentAmount ?? '0');
            $partyBalance = (int) $selectedSupplier->balance;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" x-on:keydown.escape.window="$wire.set('showSupplierPaymentModal', false)">
            <form wire:submit="submitSupplierPayment" role="dialog" aria-modal="true" aria-labelledby="supplierPaymentAmount-title" class="trade-card w-full max-w-lg max-h-[90dvh] overflow-y-auto space-y-5 shadow-2xl">
                <div class="flex justify-between items-start gap-3"><div><h2 id="supplierPaymentAmount-title" class="text-xl font-bold">Yetkazuvchining qarzini to‘lash</h2><p class="mt-1 text-slate-600">{{ $selectedSupplier->display_name }}</p></div><button type="button" wire:click="$set('showSupplierPaymentModal', false)" aria-label="To‘lov oynasini yopish" class="trade-remove">×</button></div>
                @if($errorMessage)<div class="trade-alert trade-alert-error" role="alert">{{ $errorMessage }}</div>@endif
                <x-validation-errors />
                <div class="workspace-hint"><p>{{ $partyBalance >= 0 ? 'Hozirgi qarz' : 'Oldindan to‘langan pul' }}: <strong>{{ number_format(abs($partyBalance), 0, '.', ' ') }} so‘m</strong></p><p>Kassada hozir: <strong>{{ number_format($supplierCashBalance, 0, '.', ' ') }} so‘m</strong></p></div>
                <div class="trade-field"><label for="supplierPaymentAmount-input">Yetkazuvchiga qancha pul berildi?</label><div class="trade-input-unit"><input id="supplierPaymentAmount-input" type="number" min="1" step="1" inputmode="numeric" wire:model.live.debounce.300ms="supplierPaymentAmount" placeholder="Summani yozing"><span>so‘m</span></div></div>
                @if($partyBalance > 0)<button type="button" wire:click="setFullSupplierPayable" class="trade-button trade-button-secondary">Qarzni to‘liq to‘lash: {{ number_format($partyBalance, 0, '.', ' ') }} so‘m</button>@endif
                <div class="trade-summary"><div><span>To‘lovdan keyingi qarz</span><strong>{{ number_format(max(0, $partyBalance - $inputAmount), 0, '.', ' ') }} so‘m</strong></div><div><span>To‘lovdan keyin kassada</span><strong>{{ number_format($supplierCashBalance - $inputAmount, 0, '.', ' ') }} so‘m</strong></div></div>
                @if($inputAmount > $supplierCashBalance)<div class="trade-alert trade-alert-error" role="alert">Kassada pul yetmaydi. {{ number_format($inputAmount - $supplierCashBalance, 0, '.', ' ') }} so‘m yetishmayapti. Summani kamaytiring yoki <a href="{{ route('cash.index') }}" class="underline font-bold">kassaga pul qo‘shing</a>.</div>@endif
                @if($inputAmount > max(0, $partyBalance))<label class="workspace-hint flex items-start gap-3"><input type="checkbox" wire:model="supplierConfirmExcessAsAdvance"><span>Qarzdan ortiq {{ number_format($inputAmount - max(0, $partyBalance), 0, '.', ' ') }} so‘mni keyingi xaridlar uchun oldindan to‘lov sifatida saqlashga roziman.</span></label>@endif
                <details class="trade-details"><summary>Izoh <span>ixtiyoriy</span></summary><div class="trade-field"><label for="supplierPaymentNotes-input">Izoh</label><input id="supplierPaymentNotes-input" wire:model="supplierPaymentNotes" placeholder="To‘lov haqida izoh"></div></details>
                <p class="text-sm text-slate-600">Pul kassadan ayriladi va yetkazuvchiga qarzimiz kamayadi. Saqlangach, oyna yopiladi.</p>
                <div class="flex flex-wrap justify-end gap-3"><button type="button" wire:click="$set('showSupplierPaymentModal', false)" class="trade-button trade-button-secondary">Bekor qilish</button><button type="submit" wire:loading.attr="disabled" wire:target="submitSupplierPayment" class="trade-button trade-button-primary"><span wire:loading.remove wire:target="submitSupplierPayment">Tasdiqlash va chiqim qilish</span><span wire:loading wire:target="submitSupplierPayment">Saqlanyapti…</span></button></div>
            </form>
        </div>
    @endif
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
