<div class="space-y-6" wire:poll.30s="refreshDashboard">
    <!-- 1. Header & Period Filter Bar -->
    <div class="bg-gradient-to-r from-blue-900/40 via-slate-900 to-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider">AquaOptom CRM Boshqaruv Markazi</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-950 text-emerald-400 border border-emerald-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse"></span>
                        Real-vaqt faol
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-white mt-1">Xush kelibsiz, {{ auth()->user()->name }}!</h2>
                <p class="text-xs text-slate-400 mt-1 max-w-xl">
                    Rol: <span class="text-white font-semibold">{{ auth()->user()->role }}</span> | 
                    Holat: <span class="text-cyan-400 font-mono">{{ $dashboard['as_of_time'] }}</span> holatiga ko'ra
                </p>
            </div>

            <!-- Quick Action Links -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    wire:click="toggleDraftModal"
                    class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl border border-slate-700 transition flex items-center gap-1.5"
                >
                    <span>📝</span>
                    <span>Qoralama eslatma</span>
                    @if($draftNote)
                        <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    @endif
                </button>
                <a href="{{ route('sales.pos') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center gap-1.5">
                    <span>🛒</span>
                    <span>Yangi Sotuv</span>
                </a>
                <a href="{{ route('inventory.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl border border-slate-700 transition flex items-center gap-1.5">
                    <span>📥</span>
                    <span>Kirim</span>
                </a>
                <button
                    wire:click="refreshDashboard"
                    class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 font-semibold text-xs rounded-xl border border-slate-700 transition"
                    title="Yangilash"
                >
                    🔄
                </button>
            </div>
        </div>

        <!-- Period Selector Tabs -->
        <div class="mt-6 pt-5 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5 bg-slate-950/80 p-1.5 rounded-2xl border border-slate-800 text-xs">
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
                        class="px-3 py-1.5 rounded-xl font-medium transition {{ $period === $key ? 'bg-blue-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900' }}"
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
                    class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                />
                <span class="text-slate-500">—</span>
                <input
                    type="date"
                    wire:model="customEnd"
                    class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                />
                <button
                    type="button"
                    wire:click="applyCustomDates"
                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl font-medium border border-slate-700 transition"
                >
                    Tanlash
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Data Completeness & Offline Indicator Banner -->
    <div class="p-4 rounded-2xl border {{ $dashboard['warnings']['completeness_percent'] >= 100 ? 'bg-emerald-950/20 border-emerald-900/50 text-emerald-300' : 'bg-amber-950/20 border-amber-900/50 text-amber-300' }} flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center font-bold text-sm {{ $dashboard['warnings']['completeness_percent'] >= 100 ? 'bg-emerald-900/50 text-emerald-300' : 'bg-amber-900/50 text-amber-300' }}">
                {{ $dashboard['warnings']['completeness_percent'] }}%
            </div>
            <div>
                <div class="font-bold text-white flex items-center gap-2">
                    <span>Ma'lumotlar to'liqligi: {{ $dashboard['warnings']['completeness_percent'] }}%</span>
                    @if($dashboard['warnings']['stale_devices_count'] > 0)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-900/80 text-amber-200">
                            {{ $dashboard['warnings']['stale_devices_count'] }} ta offline qurilma
                        </span>
                    @endif
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">{{ $dashboard['warnings']['completeness_note'] }}</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($dashboard['warnings']['needs_review_count'] > 0)
                <a href="{{ route('admin.index') }}" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl font-bold text-[11px] transition shadow">
                    ⚠ {{ $dashboard['warnings']['needs_review_count'] }} ta ko'rib chiqish kutilmoqda (NEEDS_REVIEW)
                </a>
            @endif
        </div>
    </div>

    <!-- 3. DAVRIY OQIM (Flow Metrics) -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span>📊</span>
                <span>Davriy Oqim ({{ $dashboard['period']['label'] }})</span>
            </h3>
            <span class="text-xs text-slate-500">Tanlangan davr ichidagi amallar</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Jami Savdo -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Jami Savdo</div>
                <div class="text-xl sm:text-2xl font-black text-white mt-2">
                    {{ number_format($dashboard['flow']['total_sales'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    {{ $dashboard['flow']['sales_count'] }} ta chek
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">🛒</div>
            </div>

            <!-- Tushgan Pul (Cash Collections) -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider">Tushgan Pul (Kassa)</div>
                <div class="text-xl sm:text-2xl font-black text-emerald-400 mt-2">
                    {{ number_format($dashboard['flow']['cash_collected'], 0, '.', ' ') }} <span class="text-xs font-normal text-emerald-600">so'm</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1.5 flex flex-wrap gap-2 font-mono">
                    <span>Naqd: {{ number_format($dashboard['flow']['payments_by_method']['cash'], 0, '.', ' ') }}</span>
                    <span>Karta: {{ number_format($dashboard['flow']['payments_by_method']['card'], 0, '.', ' ') }}</span>
                    <span>Bank: {{ number_format($dashboard['flow']['payments_by_method']['bank'], 0, '.', ' ') }}</span>
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">💵</div>
            </div>

            <!-- Yangi Nasiya (New Debt) -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-[11px] font-semibold text-amber-400 uppercase tracking-wider">Yangi Nasiya</div>
                <div class="text-xl sm:text-2xl font-black text-amber-400 mt-2">
                    {{ number_format($dashboard['flow']['new_debt'], 0, '.', ' ') }} <span class="text-xs font-normal text-amber-600">so'm</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    Shu davrda berilgan qarz
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">📑</div>
            </div>

            <!-- Yalpi Foyda (Gross Profit) -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-[11px] font-semibold text-cyan-400 uppercase tracking-wider">Yalpi Foyda</div>
                <div class="text-xl sm:text-2xl font-black text-cyan-400 mt-2">
                    @if($dashboard['can_view_cost'])
                        {{ number_format($dashboard['flow']['gross_profit'] ?? 0, 0, '.', ' ') }} <span class="text-xs font-normal text-cyan-600">so'm</span>
                    @else
                        <span class="text-slate-500 italic">*** Yashirin</span>
                    @endif
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    @if($dashboard['can_view_cost'])
                        Tannarx: {{ number_format($dashboard['flow']['total_cost'] ?? 0, 0, '.', ' ') }}
                    @else
                        Tannarx huquqi yo'q
                    @endif
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">📈</div>
            </div>

            <!-- Operatsion Xarajatlar -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden">
                <div class="text-[11px] font-semibold text-rose-400 uppercase tracking-wider">Xarajatlar</div>
                <div class="text-xl sm:text-2xl font-black text-rose-400 mt-2">
                    {{ number_format($dashboard['flow']['operating_expenses'], 0, '.', ' ') }} <span class="text-xs font-normal text-rose-600">so'm</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    Operatsion xarajatlar
                </div>
                <div class="absolute -right-2 -bottom-2 text-slate-800/40 text-5xl font-black select-none pointer-events-none">💸</div>
            </div>
        </div>
    </div>

    <!-- 4. JORIY AS-OF BALANSLAR (Hozirgi mavjud qoldiqlar — davrga bog'liq emas!) -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span>🏦</span>
                <span>Joriy Holat va Balanslar (Hozirgi As-Of Qoldiqlar)</span>
            </h3>
            <span class="text-xs text-cyan-400 font-mono">Tirik ma'lumotlar daftari</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 4.1. Kassalardagi Jami Pul -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-400">Kassalardagi Jami Pul</span>
                    <span class="text-xs text-emerald-400 font-bold">Faol</span>
                </div>
                <div class="text-2xl font-black text-white">
                    {{ number_format($dashboard['balances']['total_cash'], 0, '.', ' ') }} <span class="text-xs text-slate-400 font-normal">so'm</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-800 space-y-1.5 text-xs">
                    @foreach ($dashboard['balances']['cash_accounts'] as $acc)
                        <div class="flex items-center justify-between text-slate-300">
                            <span class="text-slate-400">{{ $acc['name'] }} ({{ $acc['type'] }}):</span>
                            <span class="font-mono font-semibold">{{ number_format($acc['balance'], 0, '.', ' ') }} so'm</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 4.2. Mijozlar Balansi: Alohida Qarzlar va Alohida Avanslar -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-400">Mijozlar Balansi</span>
                    <span class="text-[10px] text-slate-500 uppercase font-mono">Signed</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="text-[11px] text-amber-400 font-semibold">Bizga Qarzdorlik (Qarzlar):</div>
                        <div class="text-xl font-black text-amber-400">
                            {{ number_format($dashboard['balances']['customer_debts'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-[10px] text-slate-500 mt-0.5">{{ $dashboard['balances']['debtors_count'] }} ta xaridorda mavjud</div>
                    </div>
                    <div class="pt-2 border-t border-slate-800">
                        <div class="text-[11px] text-emerald-400 font-semibold">Mijozlar Avansi (Ortiqcha to'lov):</div>
                        <div class="text-lg font-bold text-emerald-400">
                            {{ number_format($dashboard['balances']['customer_advances'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-[10px] text-slate-500 mt-0.5">Qarz bilan net qilinmaydi</div>
                    </div>
                </div>
            </div>

            <!-- 4.3. Ta'minotchilar Balansi: Alohida Bizning Qarzimiz va Alohida Avansimiz -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-400">Ta'minotchilar Balansi</span>
                    <span class="text-[10px] text-slate-500 uppercase font-mono">Signed</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="text-[11px] text-rose-400 font-semibold">Bizning Qarzimiz (Payables):</div>
                        <div class="text-xl font-black text-rose-400">
                            {{ number_format($dashboard['balances']['supplier_payables'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-[10px] text-slate-500 mt-0.5">To'lanishi kutilayotgan pul</div>
                    </div>
                    <div class="pt-2 border-t border-slate-800">
                        <div class="text-[11px] text-cyan-400 font-semibold">Bizning Avansimiz (Oldindan to'lov):</div>
                        <div class="text-lg font-bold text-cyan-400">
                            {{ number_format($dashboard['balances']['supplier_advances'], 0, '.', ' ') }} <span class="text-xs font-normal">so'm</span>
                        </div>
                        <div class="text-[10px] text-slate-500 mt-0.5">Ta'minotchidagi mablag'imiz</div>
                    </div>
                </div>
            </div>

            <!-- 4.4. Ombor Qoldiqlari va Baholash -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-400">Ombor Qoldig'i</span>
                    <span class="text-xs text-blue-400 font-bold">WAC</span>
                </div>
                <div class="text-2xl font-black text-white">
                    {{ number_format($dashboard['balances']['stock_units'], 0, '.', ' ') }} <span class="text-xs text-slate-400 font-normal">dona</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-800 space-y-1 text-xs">
                    <div class="flex items-center justify-between text-slate-300">
                        <span class="text-slate-400">Tannarx qiymati:</span>
                        <span class="font-mono font-semibold">
                            @if($dashboard['can_view_cost'])
                                {{ number_format($dashboard['balances']['stock_cost_valuation'] ?? 0, 0, '.', ' ') }} so'm
                            @else
                                <span class="text-slate-500 italic">***</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-slate-300">
                        <span class="text-slate-400">Sotuv qiymati:</span>
                        <span class="font-mono font-semibold">{{ number_format($dashboard['balances']['potential_retail_value'], 0, '.', ' ') }} so'm</span>
                    </div>
                    @if($dashboard['can_view_cost'])
                        <div class="flex items-center justify-between text-cyan-400 font-medium pt-1">
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
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>⚠</span>
                    <span>Kam Qoldiq Tovarlar (Chegara: {{ $dashboard['warnings']['low_stock_threshold'] }} dona)</span>
                </h4>
                <span class="text-xs text-amber-400 font-semibold">{{ $dashboard['warnings']['low_stock_count'] }} ta</span>
            </div>

            @if(count($dashboard['warnings']['low_stock_items']) > 0)
                <div class="space-y-2 text-xs">
                    @foreach ($dashboard['warnings']['low_stock_items'] as $item)
                        <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-white">{{ $item->product_name }}</span>
                                <span class="text-slate-400 ml-1">({{ $item->volume_name }})</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg font-mono font-bold text-xs {{ $item->quantity <= 0 ? 'bg-rose-950 text-rose-400 border border-rose-800' : 'bg-amber-950 text-amber-400 border border-amber-800' }}">
                                {{ $item->quantity }} dona
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-6 text-center text-xs text-slate-500">
                    Barcha tovarlar belgilangan chegaradan yetarli miqdorda mavjud.
                </div>
            @endif
        </div>

        <!-- 5.2. Limitdan oshgan qarzdorlar va Offline qurilmalar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>📑</span>
                        <span>Kredit Limitidan Oshgan Qarzdorlar</span>
                    </h4>
                    <span class="text-xs text-rose-400 font-semibold">{{ $dashboard['warnings']['overdue_debtors_count'] }} ta</span>
                </div>

                @if(count($dashboard['warnings']['overdue_debtors']) > 0)
                    <div class="space-y-2 text-xs">
                        @foreach ($dashboard['warnings']['overdue_debtors'] as $debtor)
                            <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-white">{{ $debtor->name }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $debtor->phone }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-rose-400 font-mono font-bold">{{ number_format($debtor->current_debt, 0, '.', ' ') }} so'm</div>
                                    <div class="text-[10px] text-slate-500">Limit: {{ number_format($debtor->debt_limit, 0, '.', ' ') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-3 text-center text-xs text-slate-500">
                        Limitni buzgan qarzdorlar mavjud emas.
                    </div>
                @endif
            </div>

            <!-- Stale devices -->
            <div class="pt-3 border-t border-slate-800">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-300 flex items-center gap-2">
                        <span>📱</span>
                        <span>Kechikkan Offline Qurilmalar (>24 soat)</span>
                    </h4>
                    <span class="text-xs text-amber-400 font-mono">{{ $dashboard['warnings']['stale_devices_count'] }} ta</span>
                </div>

                @if(count($dashboard['warnings']['stale_devices']) > 0)
                    <div class="space-y-1.5 text-xs">
                        @foreach ($dashboard['warnings']['stale_devices'] as $dev)
                            <div class="p-2 rounded-xl bg-amber-950/20 border border-amber-900/40 flex items-center justify-between text-amber-300">
                                <span>{{ $dev->name }} ({{ $dev->device_type }})</span>
                                <span class="text-[11px] font-mono text-slate-400">
                                    {{ $dev->last_seen_at ? \Carbon\Carbon::parse($dev->last_seen_at)->diffForHumans() : 'Hech qachon' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-xs text-slate-500">Barcha qurilmalar so'nggi 24 soatda faol bo'lgan.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- 6. Oxirgi Amallar Lentasi (Recent Activity Feed) -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span>⚡</span>
                <span>Oxirgi Operatsiyalar Lentasi (Jonli oqim)</span>
            </h4>
            <span class="text-xs text-slate-500">Savdo, kirim, to'lov va xarajatlar</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-950/60">
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
                <tbody class="divide-y divide-slate-800/60">
                    @forelse ($dashboard['recent_activities'] as $act)
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase
                                    @if($act['type'] === 'SALE') bg-blue-950 text-blue-300 border border-blue-800
                                    @elseif($act['type'] === 'PURCHASE') bg-cyan-950 text-cyan-300 border border-cyan-800
                                    @elseif(str_starts_with($act['type'], 'PAYMENT')) bg-emerald-950 text-emerald-300 border border-emerald-800
                                    @else bg-rose-950 text-rose-300 border border-rose-800 @endif
                                ">
                                    {{ $act['type'] }}
                                </span>
                                <span class="ml-2 font-semibold text-white">{{ $act['title'] }}</span>
                            </td>
                            <td class="px-4 py-2.5 font-medium text-slate-200">{{ $act['party'] }}</td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-white">
                                @if($act['amount'] !== null)
                                    {{ number_format($act['amount'], 0, '.', ' ') }} so'm
                                @else
                                    <span class="text-slate-500 italic">***</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-emerald-400">
                                {{ number_format($act['paid_amount'], 0, '.', ' ') }} so'm
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-amber-400">
                                @if($act['debt_amount'] > 0)
                                    {{ number_format($act['debt_amount'], 0, '.', ' ') }} so'm
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-400">{{ $act['actor'] }}</td>
                            <td class="px-4 py-2.5 text-slate-500 font-mono text-[11px]">{{ $act['time'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Qoralama eslatma (Draft State)</h3>
                    <button wire:click="toggleDraftModal" class="text-slate-400 hover:text-white">&times;</button>
                </div>
                <p class="text-slate-400 text-xs">
                    Ushbu matn real-vaqt yangilanishi vaqtida ham yo'qolmaydi (Draft retention kafolati):
                </p>
                <div>
                    <textarea
                        wire:model="draftNote"
                        rows="4"
                        placeholder="Mijoz uchun eslatma yoki savat qoralamasi..."
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:ring-1 focus:ring-blue-500 text-xs"
                    >{{ $draftNote }}</textarea>
                    @if($draftNote)
                        <div class="mt-2 p-2 bg-slate-950 rounded-lg text-slate-300 text-[11px] font-mono border border-slate-800">
                            {{ $draftNote }}
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        wire:click="toggleDraftModal"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-semibold transition"
                    >
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
