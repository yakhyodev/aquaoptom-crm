<div class="space-y-6" wire:poll.30s="refreshDashboard">
    <!-- 1. Header & Period Filter Bar -->
    <div class="bg-gradient-to-r from-blue-50/40 via-slate-50 to-slate-50 border border-slate-200 rounded-3xl p-6 sm:p-8">
        <div class="dashboard-welcome"><div><span class="trade-eyebrow">DO‘KONNING UMUMIY HOLATI</span><h1>Xush kelibsiz, {{ auth()->user()->name }}!</h1><p>Hozirgi qoldiqlarni ko‘ring yoki yangi ish boshlang.</p></div><a href="{{ route('guide') }}" class="trade-button trade-button-secondary">🧭 Do‘kon xaritasi</a></div>
        <div class="dashboard-actions"><a href="{{ route('sales.pos') }}"><span>⚡</span><strong>Tezkor sotuv</strong><small>Mijozsiz sotish</small></a><a href="{{ route('sales.pos', ['mode' => 'customer']) }}"><span>👤</span><strong>Mijozga sotuv</strong><small>To‘lov yoki nasiya</small></a><a href="{{ route('inventory.inward') }}"><span>📥</span><strong>Mahsulot keldi</strong><small>Omborga kirim qilish</small></a><a href="{{ route('debts.index') }}"><span>🤝</span><strong>Qarz bilan ishlash</strong><small>To‘lov olish yoki berish</small></a></div>

        <!-- Period Selector Tabs -->
        <div class="mt-6 pt-5 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5 bg-slate-50/80 p-1.5 rounded-2xl border border-slate-200 text-xs">
                @php
                    $periods = [
                        'today' => 'Bugun',
                        'yesterday' => 'Kecha',
                        'this_week' => 'Shu hafta',
                        'this_month' => 'Shu oy',
                        'last_month' => "O'tgan oy",
                        'all_time' => 'Barcha davrlar',
                    ];
                @endphp
                @foreach ($periods as $key => $title)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $key }}')"
                        class="px-3 py-1.5 rounded-xl font-medium transition {{ $period === $key ? 'bg-blue-600 text-slate-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50' }}"
                    >
                        {{ $title }}
                    </button>
                @endforeach
            </div>

            <!-- Custom date range -->
            <div class="flex items-center gap-2 text-xs">
                <input
                    type="date"
                    wire:model="customStart"
                    class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs focus:outline-none focus:border-blue-500"
                />
                <span class="text-slate-600">—</span>
                <input
                    type="date"
                    wire:model="customEnd"
                    class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs focus:outline-none focus:border-blue-500"
                />
                <button
                    type="button"
                    wire:click="applyCustomDates"
                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-medium border border-slate-300 transition"
                >
                    Tanlash
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Data Completeness & Offline Indicator Banner -->
    <div class="p-4 rounded-2xl border {{ $dashboard['warnings']['completeness_percent'] >= 100 ? 'bg-emerald-50/20 border-emerald-200/50 text-emerald-700' : 'bg-amber-50/20 border-amber-200/50 text-amber-700' }} flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center font-bold text-sm {{ $dashboard['warnings']['completeness_percent'] >= 100 ? 'bg-emerald-50/50 text-emerald-700' : 'bg-amber-50/50 text-amber-700' }}">
                {{ $dashboard['warnings']['completeness_percent'] }}%
            </div>
            <div>
                <div class="font-bold text-slate-900 flex items-center gap-2">
                    <span>Ma'lumotlar to'liqligi: {{ $dashboard['warnings']['completeness_percent'] }}%</span>
                    @if($dashboard['warnings']['stale_devices_count'] > 0)
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50/80 text-amber-700">
                            {{ $dashboard['warnings']['stale_devices_count'] }} ta offline qurilma
                        </span>
                    @endif
                </div>
                <div class="text-xs text-slate-600 mt-0.5">{{ $dashboard['warnings']['completeness_note'] }}</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($dashboard['warnings']['needs_review_count'] > 0)
                <a href="{{ route('admin.index') }}" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-slate-900 rounded-xl font-bold text-xs transition shadow">
                    ⚠ {{ $dashboard['warnings']['needs_review_count'] }} ta ko'rib chiqish kutilmoqda
                </a>
            @endif
        </div>
    </div>

    <section class="dashboard-cash" aria-label="Kassaning hozirgi holati"><div class="dashboard-cash-total"><span>Hozir do‘konda bor jami pul</span><strong data-testid="dashboard-cash">{{ number_format($dashboard['balances']['total_cash'], 0, '.', ' ') }} <small>so‘m</small></strong><p>Do‘kon kassasidagi jami mablag‘. Sana filtri hozirgi qoldiqni o‘zgartirmaydi.</p>@can('view_cash')<a href="{{ route('cash.index') }}">Kassa va xarajatlarni ochish →</a>@endcan</div><div class="dashboard-cash-accounts"><div class="workspace-caption">Oxirgi yangilanish: {{ $dashboard['as_of_time'] }}<button wire:click="refreshDashboard" aria-label="Qoldiqlarni yangilash">↻ Yangilash</button></div></div></section>
    <div class="money-flow-cards"><div><span>Tanlangan davrda pul kirdi</span><strong>{{ number_format($dashboard['flow']['cash_in'], 0, '.', ' ') }} so‘m</strong></div><div><span>Tanlangan davrda pul chiqdi</span><strong>{{ number_format($dashboard['flow']['cash_out'], 0, '.', ' ') }} so‘m</strong></div><div><span>Shundan do‘kon xarajatlari</span><strong data-testid="dashboard-expenses">{{ number_format($dashboard['flow']['operating_expenses'], 0, '.', ' ') }} so‘m</strong></div></div>
    <p class="workspace-caption">Hisoblararo o‘tkazmalar va boshlang‘ich qoldiq davriy pul kirimi/chiqimiga qo‘shilmaydi. Mijozdan tushgan to‘lovlar quyida alohida.</p>
    <!-- 3. DAVRIY OQIM (Flow Metrics) -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold  text-slate-600 flex items-center gap-2">
                <span>📊</span>
                <span>Tanlangan davrdagi natija ({{ $dashboard['period']['label'] }})</span>
            </h3>
            <span class="text-xs text-slate-600">Tanlangan davr ichidagi amallar</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Jami Savdo -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-xs font-semibold text-slate-600 ">Jami Savdo</div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 mt-2">
                    {{ number_format($dashboard['flow']['total_sales'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-600">so'm</span>
                </div>
                <div class="text-xs text-slate-600 mt-1">
                    {{ $dashboard['flow']['sales_count'] }} ta chek
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">🛒</div>
            </div>

            <!-- Tushgan Pul (Cash Collections) -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-xs font-semibold text-emerald-700 ">Tushgan Pul (Kassa)</div>
                <div class="text-xl sm:text-2xl font-black text-emerald-700 mt-2">
                    {{ number_format($dashboard['flow']['cash_collected'], 0, '.', ' ') }} <span class="text-xs font-normal text-emerald-600">so'm</span>
                </div>
                <div class="text-xs text-slate-600 mt-1.5 flex flex-wrap gap-2 font-mono">
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">💵</div>
            </div>

            <!-- Yangi Nasiya (New Debt) -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-xs font-semibold text-amber-700 ">Yangi Nasiya</div>
                <div class="text-xl sm:text-2xl font-black text-amber-700 mt-2">
                    {{ number_format($dashboard['flow']['new_debt'], 0, '.', ' ') }} <span class="text-xs font-normal text-amber-600">so'm</span>
                </div>
                <div class="text-xs text-slate-600 mt-1">
                    Shu davrda berilgan qarz
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">📑</div>
            </div>

            <!-- Savdodan foyda -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-xs font-semibold text-cyan-700 ">Savdodan foyda</div>
                <div class="text-xl sm:text-2xl font-black text-cyan-700 mt-2">
                    @if($dashboard['can_view_cost'])
                        {{ number_format($dashboard['flow']['gross_profit'] ?? 0, 0, '.', ' ') }} <span class="text-xs font-normal text-cyan-600">so'm</span>
                    @else
                        <span class="text-slate-600 italic">*** Yashirin</span>
                    @endif
                </div>
                <div class="text-xs text-slate-600 mt-1">
                    @if($dashboard['can_view_cost'])
                        Tannarx: {{ number_format($dashboard['flow']['total_cost'] ?? 0, 0, '.', ' ') }}
                    @else
                        Tannarx huquqi yo'q
                    @endif
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">📈</div>
            </div>

            <!-- Operatsion Xarajatlar -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-xs font-semibold text-rose-700 ">Xarajatlar</div>
                <div class="text-xl sm:text-2xl font-black text-rose-700 mt-2">
                    {{ number_format($dashboard['flow']['operating_expenses'], 0, '.', ' ') }} <span class="text-xs font-normal text-rose-600">so'm</span>
                </div>
                <div class="text-xs text-slate-600 mt-1">
                    Do‘kon xarajatlari
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">💸</div>
            </div>
        </div>
    </div>

    <!-- 4. JORIY AS-OF BALANSLAR (Hozirgi mavjud qoldiqlar — davrga bog'liq emas!) -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold  text-slate-600 flex items-center gap-2">
                <span>🏦</span>
                <span>Hozirgi qoldiqlar va qarzlar</span>
            </h3>
            <span class="text-xs text-cyan-700 font-mono">Hozirgi holat</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 4.1. Kassadagi jami pul -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-600">Kassadagi jami pul</span>
                    <span class="text-xs text-emerald-700 font-bold">Faol</span>
                </div>
                <div class="text-2xl font-black text-slate-900">
                    {{ number_format($dashboard['balances']['total_cash'], 0, '.', ' ') }} <span class="text-xs text-slate-600 font-normal">so'm</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-200 space-y-1.5 text-xs"><p>Do‘kon kassasi</p>
                </div>
            </div>

            <!-- 4.2. Mijozlar Balansi: Alohida Qarzlar va Alohida Avanslar -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-600">Mijozlar Balansi</span>
                    <span class="text-xs text-slate-600 uppercase font-mono">Qarz / oldindan to‘lov</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="text-xs text-amber-700 font-semibold">Bizga Qarzdorlik (Qarzlar):</div>
                        <div class="text-xl font-black text-amber-700">
                            {{ number_format($dashboard['balances']['customer_debts'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-xs text-slate-600 mt-0.5">{{ $dashboard['balances']['debtors_count'] }} ta xaridorda mavjud</div>
                    </div>
                    <div class="pt-2 border-t border-slate-200">
                        <div class="text-xs text-emerald-700 font-semibold">Mijozlar Avansi (Ortiqcha to'lov):</div>
                        <div class="text-lg font-bold text-emerald-700">
                            {{ number_format($dashboard['balances']['customer_advances'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-xs text-slate-600 mt-0.5">Qarz bilan net qilinmaydi</div>
                    </div>
                </div>
            </div>

            <!-- 4.3. Ta'minotchilar Balansi: Alohida Bizning Qarzimiz va Alohida Avansimiz -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-600">Ta'minotchilar Balansi</span>
                    <span class="text-xs text-slate-600 uppercase font-mono">Qarz / oldindan to‘lov</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="text-xs text-rose-700 font-semibold">Bizning Qarzimiz (Payables):</div>
                        <div class="text-xl font-black text-rose-700">
                            {{ number_format($dashboard['balances']['supplier_payables'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-xs text-slate-600 mt-0.5">To'lanishi kutilayotgan pul</div>
                    </div>
                    <div class="pt-2 border-t border-slate-200">
                        <div class="text-xs text-cyan-700 font-semibold">Bizning Avansimiz (Oldindan to'lov):</div>
                        <div class="text-lg font-bold text-cyan-700">
                            {{ number_format($dashboard['balances']['supplier_advances'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-xs text-slate-600 mt-0.5">Ta'minotchidagi mablag'imiz</div>
                    </div>
                </div>
            </div>

            <!-- 4.4. Ombor Qoldiqlari va Baholash -->
            <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-600">Ombor Qoldig'i</span>
                    <span class="text-xs text-blue-700 font-bold">O‘rtacha kirim narxi</span>
                </div>
                <div class="text-2xl font-black text-slate-900">
                    {{ number_format($dashboard['balances']['stock_units'], 0, '.', ' ') }} <span class="text-xs text-slate-600 font-normal">dona</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-200 space-y-1 text-xs">
                    <div class="flex items-center justify-between text-slate-700">
                        <span class="text-slate-600">Tannarx qiymati:</span>
                        <span class="font-mono font-semibold">
                            @if($dashboard['can_view_cost'])
                                {{ number_format($dashboard['balances']['stock_cost_valuation'] ?? 0, 0, '.', ' ') }} so'm
                            @else
                                <span class="text-slate-600 italic">***</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-slate-700">
                        <span class="text-slate-600">Sotuv qiymati:</span>
                        <span class="font-mono font-semibold">{{ number_format($dashboard['balances']['potential_retail_value'], 0, '.', ' ') }} so'm</span>
                    </div>
                    @if($dashboard['can_view_cost'])
                        <div class="flex items-center justify-between text-cyan-700 font-medium pt-1">
                            <span>Kutilayotgan foyda:</span>
                            <span class="font-mono font-bold">{{ number_format($dashboard['balances']['potential_gross_profit'] ?? 0, 0, '.', ' ') }} so'm</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Ogohlantirishlar va Kam qoldiqlar -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- 5.1. Kam qolgan tovarlar -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <span>⚠</span>
                    <span>Kam Qoldiq Tovarlar (Chegara: {{ $dashboard['warnings']['low_stock_threshold'] }} dona)</span>
                </h4>
                <span class="text-xs text-amber-700 font-semibold">{{ $dashboard['warnings']['low_stock_count'] }} ta</span>
            </div>

            @if(count($dashboard['warnings']['low_stock_items']) > 0)
                <div class="space-y-2 text-xs">
                    @foreach ($dashboard['warnings']['low_stock_items'] as $item)
                        <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-200 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-slate-900">{{ $item->product_name }}</span>
                                <span class="text-slate-600 ml-1">({{ $item->volume_name }})</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg font-mono font-bold text-xs {{ $item->quantity <= 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                {{ $item->quantity }} dona
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-6 text-center text-xs text-slate-600">
                    Barcha tovarlar belgilangan chegaradan yetarli miqdorda mavjud.
                </div>
            @endif
        </div>

        <!-- 5.2. Limitdan oshgan qarzdorlar va Offline qurilmalar -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <span>📑</span>
                        <span>Kredit Limitidan Oshgan Qarzdorlar</span>
                    </h4>
                    <span class="text-xs text-rose-700 font-semibold">{{ $dashboard['warnings']['overdue_debtors_count'] }} ta</span>
                </div>

                @if(count($dashboard['warnings']['overdue_debtors']) > 0)
                    <div class="space-y-2 text-xs">
                        @foreach ($dashboard['warnings']['overdue_debtors'] as $debtor)
                            <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-200 flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $debtor->name }}</div>
                                    <div class="text-xs text-slate-600 font-mono">{{ $debtor->phone }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-rose-700 font-mono font-bold">{{ number_format($debtor->current_debt, 0, '.', ' ') }} so'm</div>
                                    <div class="text-xs text-slate-600">Limit: {{ number_format($debtor->debt_limit, 0, '.', ' ') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-3 text-center text-xs text-slate-600">
                        Limitni buzgan qarzdorlar mavjud emas.
                    </div>
                @endif
            </div>

            <!-- Stale devices -->
            <div class="pt-3 border-t border-slate-200">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-700 flex items-center gap-2">
                        <span>📱</span>
                        <span>Kechikkan Offline Qurilmalar (>24 soat)</span>
                    </h4>
                    <span class="text-xs text-amber-700 font-mono">{{ $dashboard['warnings']['stale_devices_count'] }} ta</span>
                </div>

                @if(count($dashboard['warnings']['stale_devices']) > 0)
                    <div class="space-y-1.5 text-xs">
                        @foreach ($dashboard['warnings']['stale_devices'] as $dev)
                            <div class="p-2 rounded-xl bg-amber-50/20 border border-amber-200/40 flex items-center justify-between text-amber-700">
                                <span>{{ $dev->name }} (<x-enum-label :value="$dev->device_type" />)</span>
                                <span class="text-xs font-mono text-slate-600">
                                    {{ $dev->last_seen_at ? \Carbon\Carbon::parse($dev->last_seen_at)->diffForHumans() : 'Hech qachon' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-xs text-slate-600">Barcha qurilmalar so'nggi 24 soatda faol bo'lgan.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- 6. Oxirgi Amallar Lentasi (Recent Activity Feed) -->
    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <span>⚡</span>
                <span>Oxirgi Operatsiyalar Lentasi (Jonli oqim)</span>
            </h4>
            <span class="text-xs text-slate-600">Savdo, kirim, to'lov va xarajatlar</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="text-xs  text-slate-600 border-b border-slate-200 bg-slate-50/60">
                    <tr>
                        <th class="px-4 py-3">Turi & Hujjat</th>
                        <th class="px-4 py-3">Taraf (Mijoz / Ta'minotchi)</th>
                        <th class="px-4 py-3 text-right">Summa</th>
                        <th class="px-4 py-3 text-right">To'landi</th>
                        <th class="px-4 py-3 text-right">Qarz</th>
                        <th class="px-4 py-3">Mas'ul xodim</th>
                        <th class="px-4 py-3">Vaqt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/60">
                    @forelse ($dashboard['recent_activities'] as $act)
                        <tr class="hover:bg-slate-100/30 transition">
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold font-mono uppercase
                                    @if($act['type'] === 'SALE') bg-blue-50 text-blue-700 border border-blue-200
                                    @elseif($act['type'] === 'PURCHASE') bg-cyan-50 text-cyan-700 border border-cyan-200
                                    @elseif(str_starts_with($act['type'], 'PAYMENT')) bg-emerald-50 text-emerald-700 border border-emerald-200
                                    @else bg-rose-50 text-rose-700 border border-rose-200 @endif
                                ">
                                    <x-enum-label :value="$act['type']" />
                                </span>
                                <span class="ml-2 font-semibold text-slate-900">{{ $act['title'] }}</span>
                            </td>
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $act['party'] }}</td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900">
                                @if($act['amount'] !== null)
                                    {{ number_format($act['amount'], 0, '.', ' ') }} so'm
                                @else
                                    <span class="text-slate-600 italic">***</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-emerald-700">
                                {{ number_format($act['paid_amount'], 0, '.', ' ') }} so'm
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-amber-700">
                                @if($act['debt_amount'] > 0)
                                    {{ number_format($act['debt_amount'], 0, '.', ' ') }} so'm
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $act['actor'] }}</td>
                            <td class="px-4 py-2.5 text-slate-600 font-mono text-xs">{{ $act['time'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-600">
                                Amallar tarixi mavjud emas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 7. Draft Retention Modal (Ochiq qoralama saqlanishi tekshiruvi) -->
    @if($showDraftModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Mening eslatmam</h3>
                    <button wire:click="toggleDraftModal" class="text-slate-600 hover:text-slate-900">&times;</button>
                </div>
                <p class="text-slate-600 text-xs">
                    Ochiq sahifada yangilanish bo‘lsa ham eslatma saqlanib turadi.
                </p>
                <div>
                    <textarea
                        wire:model="draftNote"
                        rows="4"
                        placeholder="Mijoz uchun eslatma yoki savat qoralamasi..."
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500 text-xs"
                    >{{ $draftNote }}</textarea>
                    @if($draftNote)
                        <div class="mt-2 p-2 bg-slate-50 rounded-lg text-slate-700 text-xs font-mono border border-slate-200">
                            {{ $draftNote }}
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        wire:click="toggleDraftModal"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-xl font-semibold transition"
                    >
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
