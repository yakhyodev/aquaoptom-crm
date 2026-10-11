<div class="space-y-6" wire:poll.30s>
    <!-- Header & Period Filter Toolbar -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Do‘kon hisobotlari</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Davrni tanlang. Savdo, foyda, pul va qarzlarni ko‘ring yoki faylga yuklang.
                </p>
            </div>
            <!-- Export Buttons -->
            <div class="flex items-center gap-2">
                <button
                    wire:click="export('xlsx')" wire:loading.attr="disabled" wire:target="export"
                    class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-slate-900 rounded-lg text-xs font-semibold shadow-xs flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span wire:loading.remove wire:target="export">Excel (.xlsx) yuklash</span><span wire:loading wire:target="export">Tayyorlanyapti…</span>
                </button>
                <button
                    wire:click="export('pdf')"
                    class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-slate-900 rounded-lg text-xs font-semibold shadow-xs flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>PDF yuklash</span>
                </button>
            </div>
        </div>

        <!-- Period Presets Bar -->
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
            <span class="text-xs font-semibold text-slate-600 mr-1">Davr:</span>
            <button wire:click="setPeriod('today')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $period === 'today' ? 'bg-blue-600 text-slate-900 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Bugun
            </button>
            <button wire:click="setPeriod('yesterday')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $period === 'yesterday' ? 'bg-blue-600 text-slate-900 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Kecha
            </button>
            <button wire:click="setPeriod('this_week')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $period === 'this_week' ? 'bg-blue-600 text-slate-900 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Bu Hafta
            </button>
            <button wire:click="setPeriod('this_month')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $period === 'this_month' ? 'bg-blue-600 text-slate-900 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Bu Oy
            </button>
            <button wire:click="setPeriod('last_month')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $period === 'last_month' ? 'bg-blue-600 text-slate-900 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                O'tgan Oy
            </button>
            <button wire:click="setPeriod('month')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $period === 'month' ? 'bg-blue-600 text-slate-900 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Yil + Oy
            </button>
            <button wire:click="setPeriod('custom')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $period === 'custom' ? 'bg-blue-600 text-slate-900 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Ixtiyoriy Sana
            </button>

            <!-- Custom Date Inputs -->
            @if($period === 'custom')
                <div class="flex items-center gap-2 ml-auto">
                    <input type="date" wire:model.live="fromDate" class="px-2.5 py-1 text-xs border border-slate-300 rounded-lg">
                    <span class="text-xs text-slate-600">—</span>
                    <input type="date" wire:model.live="toDate" class="px-2.5 py-1 text-xs border border-slate-300 rounded-lg">
                </div>
            @elseif($period === 'month')
                <div class="flex items-center gap-2 ml-auto">
                    <input type="month" wire:model.live="month" class="px-2.5 py-1 text-xs border border-slate-300 rounded-lg">
                </div>
            @endif
        </div>

        <!-- Navigation Tabs Bar -->
        <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-slate-100">
            <button wire:click="setTab('sales')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'sales' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Savdolar
            </button>
            <button wire:click="setTab('profit_loss')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'profit_loss' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Foyda va xarajatlar
            </button>
            <button wire:click="setTab('purchases')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'purchases' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Mahsulot kirimi
            </button>
            <button wire:click="setTab('inventory')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'inventory' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Ombor qiymati
            </button>
            <button wire:click="setTab('statements')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'statements' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Mijoz va yetkazuvchi hisobi
            </button>
            <button wire:click="setTab('cash')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'cash' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Kassa
            </button>
            <button wire:click="setTab('staff')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'staff' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Xodimlar
            </button>
            <button wire:click="setTab('sync')" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'sync' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-600 hover:bg-slate-50' }}">
                Qurilmalar
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    @if($errorMessage)
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs flex items-center justify-between">
            <span>{{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-rose-700 font-bold">&times;</button>
        </div>
    @endif
    @if($successMessage)
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs flex items-center justify-between">
            <span>{{ $successMessage }}</span>
            <button wire:click="$set('successMessage', null)" class="text-emerald-700 font-bold">&times;</button>
        </div>
    @endif

    <!-- TAB 1: SAVDO DINAMIKASI -->
    @if($activeTab === 'sales' && $salesReport)
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-slate-600  block">Jami Savdo</span>
                    <span class="text-lg font-bold text-slate-900 font-mono mt-1 block">
                        {{ number_format($salesReport['kpi']['total_gross_sales']) }}
                    </span>
                    <span class="text-xs text-slate-600">so'm (brutto)</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-rose-600  block">Qaytarishlar</span>
                    <span class="text-lg font-bold text-rose-700 font-mono mt-1 block">
                        {{ number_format($salesReport['kpi']['total_returns']) }}
                    </span>
                    <span class="text-xs text-slate-600">{{ $salesReport['kpi']['total_returns_count'] }} ta qaytarish</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-blue-600  block">Sotilgan mahsulotlar summasi</span>
                    <span class="text-lg font-bold text-blue-700 font-mono mt-1 block">
                        {{ number_format($salesReport['kpi']['net_sales']) }}
                    </span>
                    <span class="text-xs text-slate-600">Qaytarishlar ayrilgan sotuv summasi; nasiya ham kiradi</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-emerald-600  block">Sotuvda to‘langan pul</span>
                    <span class="text-lg font-bold text-emerald-700 font-mono mt-1 block">
                        {{ number_format($salesReport['kpi']['cash_at_pos'] + $salesReport['kpi']['card_at_pos'] + $salesReport['kpi']['bank_at_pos']) }}
                    </span>
                    <span class="text-xs text-slate-600">Kassaga tushgan summa</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-amber-600  block">Dastlabki Nasiya</span>
                    <span class="text-lg font-bold text-amber-700 font-mono mt-1 block">
                        {{ number_format($salesReport['kpi']['total_initial_debt']) }}
                    </span>
                    <span class="text-xs text-slate-600">nasiyaga berilgan</span>
                </div>
            </div>

            <!-- Mahsulotlar kesimida jadval -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800">
                        Mahsulotlar va Hajmlar Kesimida Savdo Aylanmasi
                    </h3>
                    <span class="text-xs text-slate-600">
                        Top 50 ta yetakchi pozitsiya
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-3">Mahsulot</th>
                                <th class="p-3">Hajm</th>
                                <th class="p-3">SKU</th>
                                <th class="p-3 text-right">Sotilgan Dona</th>
                                <th class="p-3 text-right">Cheklar Soni</th>
                                <th class="p-3 text-right">Jami Tushum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($salesReport['product_breakdown'] as $pb)
                                <tr>
                                    <td class="p-3 font-semibold text-slate-900">{{ $pb['product_name'] }}</td>
                                    <td class="p-3 text-slate-600">{{ $pb['volume_name'] }}</td>
                                    <td class="p-3 font-mono text-slate-600">{{ $pb['sku'] }}</td>
                                    <td class="p-3 text-right font-mono font-bold text-slate-900">{{ number_format($pb['units_sold']) }}</td>
                                    <td class="p-3 text-right font-mono text-slate-600">{{ number_format($pb['sales_count']) }}</td>
                                    <td class="p-3 text-right font-mono font-bold text-emerald-600">{{ number_format($pb['total_revenue']) }} so'm</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-600">Tanlangan davrda sotuvlar mavjud emas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 2: YALPI VA OPERATSION NATIJA (P&L) -->
    @elseif($activeTab === 'profit_loss' && $pnlReport)
        <div class="space-y-6">
            @if(!$canViewCost)
                <div class="p-8 bg-amber-50 border border-amber-200 rounded-xl text-center text-amber-900 space-y-2">
                    <strong class="text-base font-bold block">Kirish cheklangan</strong>
                    <p class="text-xs">Sizning rolingizda tannarx va moliyaviy foydani ko'rish huquqi (view_cost_price) mavjud emas.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                        <span class="text-xs font-semibold text-slate-600  block">Sof Savdo Tushumi</span>
                        <span class="text-2xl font-bold text-slate-900 font-mono mt-1 block">
                            {{ number_format($pnlReport['net_sales']) }}
                        </span>
                        <span class="text-xs text-slate-600">Brutto: {{ number_format($pnlReport['gross_sales']) }} - Qaytarish: {{ number_format($pnlReport['returns']) }}</span>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                        <span class="text-xs font-semibold text-slate-600  block">Tannarx (COGS)</span>
                        <span class="text-2xl font-bold text-slate-700 font-mono mt-1 block">
                            {{ number_format($pnlReport['cogs']) }}
                        </span>
                        <span class="text-xs text-slate-600">O‘rtacha kirim narxi bo'yicha hisoblangan</span>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                        <span class="text-xs font-semibold text-emerald-600  block">Yalpi Foyda</span>
                        <span class="text-2xl font-bold text-emerald-700 font-mono mt-1 block">
                            {{ number_format($pnlReport['gross_profit']) }}
                        </span>
                        <span class="text-xs text-emerald-600 font-semibold">Marja: {{ $pnlReport['gross_margin_percent'] }}%</span>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                        <span class="text-xs font-semibold text-blue-600  block">Sof Operatsion Foyda</span>
                        <span class="text-2xl font-bold font-mono mt-1 block {{ $pnlReport['operating_profit'] >= 0 ? 'text-blue-700' : 'text-rose-700' }}">
                            {{ number_format($pnlReport['operating_profit']) }}
                        </span>
                        <span class="text-xs font-semibold {{ $pnlReport['operating_profit'] >= 0 ? 'text-blue-600' : 'text-rose-600' }}">
                            Rentabellik: {{ $pnlReport['operating_margin_percent'] }}%
                        </span>
                    </div>
                </div>

                <!-- Foyda va Zarar Tafsilot Jadvali -->
                <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100">
                        <h3 class="text-sm font-bold text-slate-800">
                            Moliyaviy Natijalar Tuzilishi
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b">
                                <tr>
                                    <th class="p-3">Modda</th>
                                    <th class="p-3 text-right">Summa (so'm)</th>
                                    <th class="p-3">Izoh / Qoida</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono">
                                <tr>
                                    <td class="p-3 font-sans font-medium text-slate-800">1. Jami Savdo (Brutto)</td>
                                    <td class="p-3 text-right font-bold text-slate-900">+{{ number_format($pnlReport['gross_sales']) }}</td>
                                    <td class="p-3 font-sans text-slate-600">Davrdagi barcha cheklar summasi</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-sans font-medium text-rose-700">2. Qaytarilgan Tovarlar (Returns)</td>
                                    <td class="p-3 text-right font-bold text-rose-700">-{{ number_format($pnlReport['returns']) }}</td>
                                    <td class="p-3 font-sans text-slate-600">Mijozlar qaytargan tovar summalari</td>
                                </tr>
                                <tr class="bg-slate-50/50">
                                    <td class="p-3 font-sans font-bold text-slate-900">= Sof Savdo Tushumi (Net Sales)</td>
                                    <td class="p-3 text-right font-bold text-blue-700">{{ number_format($pnlReport['net_sales']) }}</td>
                                    <td class="p-3 font-sans text-slate-600">Haqiqiy sotuv aylanmasi</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-sans font-medium text-slate-700">3. Sotilgan Mahsulotlar Tannarxi (COGS)</td>
                                    <td class="p-3 text-right font-bold text-slate-700">-{{ number_format($pnlReport['cogs']) }}</td>
                                    <td class="p-3 font-sans text-slate-600">O‘rtacha kirim narxi o'rtacha xarid qiymati (yaroqli qaytarishlar ayirilgan)</td>
                                </tr>
                                <tr class="bg-emerald-50/50">
                                    <td class="p-3 font-sans font-bold text-emerald-900">= Yalpi Foyda</td>
                                    <td class="p-3 text-right font-bold text-emerald-700">{{ number_format($pnlReport['gross_profit']) }}</td>
                                    <td class="p-3 font-sans text-emerald-700 font-semibold">Yalpi marja: {{ $pnlReport['gross_margin_percent'] }}%</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-sans font-medium text-rose-700">4. Operatsion Xarajatlar (Expenses)</td>
                                    <td class="p-3 text-right font-bold text-rose-700">-{{ number_format($pnlReport['operating_expenses']) }}</td>
                                    <td class="p-3 font-sans text-slate-600">Kassa operatsion xarajatlari (Egasi yechgan pul/draw kirmaydi)</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-sans font-medium text-rose-700">5. Brak va Yaroqsiz Tovarlar Yo'qotishi</td>
                                    <td class="p-3 text-right font-bold text-rose-700">-{{ number_format($pnlReport['damage_loss']) }}</td>
                                    <td class="p-3 font-sans text-slate-600">Ombordan yaroqsiz chiqimlar tannarxi (Kassa xarajati emas)</td>
                                </tr>
                                <tr class="bg-blue-50/60 border-t-2 border-blue-200">
                                    <td class="p-3 font-sans font-bold text-blue-950">= Sof Operatsion Natija (Operating Profit)</td>
                                    <td class="p-3 text-right font-bold text-base {{ $pnlReport['operating_profit'] >= 0 ? 'text-blue-800' : 'text-rose-800' }}">
                                        {{ number_format($pnlReport['operating_profit']) }} so'm
                                    </td>
                                    <td class="p-3 font-sans font-bold {{ $pnlReport['operating_profit'] >= 0 ? 'text-blue-800' : 'text-rose-800' }}">
                                        Sof rentabellik: {{ $pnlReport['operating_margin_percent'] }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

    <!-- TAB 3: KIRIM VA TA'MINOTCHILAR -->
    @elseif($activeTab === 'purchases' && !$canViewCost)
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-slate-600">Kirim narxlarini ko‘rish uchun do‘kon egasi ruxsat berishi kerak.</div>
    @elseif($activeTab === 'purchases' && $purchasesReport)
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-slate-600  block">Jami Tovar Kirimi</span>
                    <span class="text-xl font-bold text-slate-900 font-mono mt-1 block">
                        {{ number_format($purchasesReport['total_inward_amount']) }}
                    </span>
                    <span class="text-xs text-slate-600">{{ $purchasesReport['inward_count'] }} ta kirim hujjati</span>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-rose-600  block">Ta'minotchiga Qaytarilgan</span>
                    <span class="text-xl font-bold text-rose-700 font-mono mt-1 block">
                        {{ number_format($purchasesReport['total_supplier_returns_credit']) }}
                    </span>
                    <span class="text-xs text-slate-600">{{ $purchasesReport['total_supplier_returns_count'] }} ta qaytarish</span>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-emerald-600  block">To'langan Summa</span>
                    <span class="text-xl font-bold text-emerald-700 font-mono mt-1 block">
                        {{ number_format($purchasesReport['total_paid_amount']) }}
                    </span>
                    <span class="text-xs text-slate-600">kirim vaqtida to'langan</span>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-amber-600  block">Nasiyaga Olingan</span>
                    <span class="text-xl font-bold text-amber-700 font-mono mt-1 block">
                        {{ number_format($purchasesReport['total_debt_amount']) }}
                    </span>
                    <span class="text-xs text-slate-600">ta'minotchi oldidagi qarz</span>
                </div>
            </div>
        </div>

    <!-- TAB 4: OMBOR VA TARIXIY QOLDIQLAR (VALUATION) -->
    @elseif($activeTab === 'inventory' && $inventoryReport)
        <div class="space-y-6">
            <div class="bg-purple-50/70 border border-purple-200 rounded-xl p-4 text-purple-900 text-xs">
                <strong>Qat'iy buxgalteriya qoidasi:</strong> Tanlangan davr: boshidagi qoldiq + kirim − chiqim + sanashdagi farq = oxiridagi qoldiq. Sanashdagi farq oddiy kirimga qo‘shilmaydi.
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-slate-600  block">Davr Boshi Qoldiq</span>
                    <span class="text-lg font-bold text-slate-900 font-mono mt-1 block">
                        {{ number_format($inventoryReport['summary']['total_opening_units']) }}
                    </span>
                    <span class="text-xs text-slate-600">dona</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-emerald-600  block">Davr Kirimi</span>
                    <span class="text-lg font-bold text-emerald-700 font-mono mt-1 block">
                        +{{ number_format($inventoryReport['summary']['total_inward_units']) }}
                    </span>
                    <span class="text-xs text-slate-600">dona</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-rose-600  block">Davr Chiqimi</span>
                    <span class="text-lg font-bold text-rose-700 font-mono mt-1 block">
                        -{{ number_format($inventoryReport['summary']['total_outward_units']) }}
                    </span>
                    <span class="text-xs text-slate-600">dona (savdo + brak)</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-purple-600  block">Davr Yakuni Qiymati</span>
                    <span class="text-lg font-bold text-purple-700 font-mono mt-1 block">
                        {{ $canViewCost ? number_format($inventoryReport['summary']['total_closing_valuation']) : 'Yashirilgan' }}
                    </span>
                    <span class="text-xs text-slate-600">{{ number_format($inventoryReport['summary']['total_closing_units']) }} dona (O‘rtacha kirim narxi qiymati)</span>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800">
                        Mahsulotlar Bo'yicha Qoldiqlar va Harakatlar Daftari
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-3">Mahsulot</th>
                                <th class="p-3">Hajm</th>
                                <th class="p-3 text-right">Davr Boshi</th>
                                <th class="p-3 text-right">Kirim</th>
                                <th class="p-3 text-right">Chiqim</th>
                                <th class="p-3 text-right">Tuzatish</th>
                                <th class="p-3 text-right font-bold">Yakuniy Qoldiq</th>
                                <th class="p-3 text-right">O‘rtacha kirim narxi Tannarx</th>
                                <th class="p-3 text-right font-bold">Yakuniy Qiymat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @forelse($inventoryReport['rows'] as $ir)
                                <tr>
                                    <td class="p-3 font-sans font-medium text-slate-900">{{ $ir['product_name'] }}</td>
                                    <td class="p-3 font-sans text-slate-600">{{ $ir['volume_name'] }}</td>
                                    <td class="p-3 text-right text-slate-600">{{ number_format($ir['opening_units']) }}</td>
                                    <td class="p-3 text-right text-emerald-600">+{{ number_format($ir['inward_units']) }}</td>
                                    <td class="p-3 text-right text-rose-600">-{{ number_format($ir['outward_units']) }}</td>
                                    <td class="p-3 text-right text-purple-600">{{ $ir['adjustment_units'] > 0 ? '+'.$ir['adjustment_units'] : $ir['adjustment_units'] }}</td>
                                    <td class="p-3 text-right font-bold text-slate-900">{{ number_format($ir['closing_units']) }}</td>
                                    <td class="p-3 text-right text-slate-600">{{ $canViewCost ? number_format($ir['wac_cost']) : '—' }}</td>
                                    <td class="p-3 text-right font-bold text-purple-700">{{ $canViewCost ? number_format($ir['closing_valuation']).' so‘m' : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="p-8 text-center text-slate-600 font-sans">
                                        Ombor harakatlari topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 5: TARAF KO'CHIRMALARI (STATEMENTS) -->
    @elseif($activeTab === 'statements' && $statementsReport)
        <div class="space-y-6">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-600">Taraf turi:</label>
                    <select wire:model.live="statementPartyType" class="px-3 py-1.5 text-xs border border-slate-300 rounded-lg">
                        <option value="customer">Mijozlar (Bizga qarzdor / Avans)</option>
                        <option value="supplier">Ta'minotchilar (Biz qarzdormiz / Avans)</option>
                    </select>
                </div>

                <div class="min-w-64 flex-1">
                    <x-searchable-select id="report-party" model="statementPartyId" label="Kimning hisobini ko‘ramiz?" placeholder="Barchasi yoki ism / telefon bilan qidiring"
                        :options="($statementPartyType === 'customer' ? $customers : $suppliers)->map(fn ($party) => ['value' => $party->id, 'label' => $party->display_name])->all()" />
                    <p class="text-xs text-slate-600 mt-1">Tanlovni × bilan tozalash — barcha mijozlar yoki yetkazuvchilar.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800">
                        {{ $statementPartyType === 'customer' ? 'Mijozlar' : 'Ta\'minotchilar' }}ning Davriy Hisob-Kitob Ko'chirmasi
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-3">Taraf Nomi</th>
                                <th class="p-3">Telefon</th>
                                <th class="p-3 text-right">Davr Boshi Balansi</th>
                                <th class="p-3 text-right">Qarz oshdi (+)</th>
                                <th class="p-3 text-right">Qarz kamaydi (−)</th>
                                <th class="p-3 text-right font-bold">Davr Yakuni Balansi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @forelse($statementsReport['statements'] as $st)
                                <tr>
                                    <td class="p-3 font-sans font-semibold text-slate-900">{{ $st['party_name'] }}</td>
                                    <td class="p-3 font-sans text-slate-600">{{ $st['party_phone'] ?: '-' }}</td>
                                    <td class="p-3 text-right {{ $st['opening_balance'] > 0 ? 'text-amber-700' : ($st['opening_balance'] < 0 ? 'text-blue-700' : 'text-slate-600') }}">
                                        {{ number_format($st['opening_balance']) }}
                                    </td>
                                    <td class="p-3 text-right text-emerald-600">+{{ number_format($statementPartyType === 'supplier' ? $st['period_credits'] : $st['period_debits']) }}</td>
                                    <td class="p-3 text-right text-rose-600">-{{ number_format($statementPartyType === 'supplier' ? $st['period_debits'] : $st['period_credits']) }}</td>
                                    <td class="p-3 text-right font-bold {{ $st['closing_balance'] > 0 ? 'text-amber-700' : ($st['closing_balance'] < 0 ? 'text-blue-700' : 'text-slate-600') }}">
                                        {{ number_format($st['closing_balance']) }} so'm
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-600 font-sans">
                                        Ko'chirma ma'lumotlari topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 6: KASSA VA SMENALAR -->
    @elseif($activeTab === 'cash' && $cashReport)
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-slate-600  block">Boshlang'ich Kassa</span>
                    <span class="text-lg font-bold text-slate-900 font-mono mt-1 block">
                        {{ number_format($cashReport['summary']['total_opening_cash']) }}
                    </span>
                    <span class="text-xs text-slate-600">so'm</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-emerald-600  block">Jami Pul Kirimi</span>
                    <span class="text-lg font-bold text-emerald-700 font-mono mt-1 block">
                        +{{ number_format($cashReport['summary']['total_inflows']) }}
                    </span>
                    <span class="text-xs text-slate-600">savdo + qarz to'lovlari</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-rose-600  block">Jami Pul Chiqimi</span>
                    <span class="text-lg font-bold text-rose-700 font-mono mt-1 block">
                        -{{ number_format($cashReport['summary']['total_outflows']) }}
                    </span>
                    <span class="text-xs text-slate-600">xarajatlar + to'lovlar</span>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                    <span class="text-xs font-semibold text-blue-600  block">Yakuniy Kassa Qoldig'i</span>
                    <span class="text-lg font-bold text-blue-700 font-mono mt-1 block">
                        {{ number_format($cashReport['summary']['total_closing_cash']) }}
                    </span>
                    <span class="text-xs text-slate-600">so'm</span>
                </div>
            </div>

            <!-- Hisoblar bo'yicha jadval -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800">Kassa Hisoblari Bo'yicha Aylanma</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-3">Hisob Nomi</th>
                                <th class="p-3">Turi</th>
                                <th class="p-3 text-right">Boshlang'ich Balans</th>
                                <th class="p-3 text-right">Kirimlar</th>
                                <th class="p-3 text-right">Chiqimlar</th>
                                <th class="p-3 text-right font-bold">Yakuniy Balans</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @foreach($cashReport['account_summaries'] as $acc)
                                <tr>
                                    <td class="p-3 font-sans font-semibold text-slate-900">{{ $acc['account_name'] }}</td>
                                    <td class="p-3 font-sans">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-700">{{ $acc['account_type'] }}</span>
                                    </td>
                                    <td class="p-3 text-right text-slate-600">{{ number_format($acc['opening_balance']) }}</td>
                                    <td class="p-3 text-right text-emerald-600">+{{ number_format($acc['inflows']) }}</td>
                                    <td class="p-3 text-right text-rose-600">-{{ number_format($acc['outflows']) }}</td>
                                    <td class="p-3 text-right font-bold text-blue-700">{{ number_format($acc['closing_balance']) }} so'm</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 7: XODIMLAR FAOLIYATI -->
    @elseif($activeTab === 'staff' && $staffReport)
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800">Xodimlar Kesimida Savdo Ko'rsatkichlari</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-3">Xodim F.I.Sh</th>
                                <th class="p-3">Roli</th>
                                <th class="p-3 text-right">Cheklar Soni</th>
                                <th class="p-3 text-right">Jami Savdo</th>
                                <th class="p-3 text-right">Qabul qilingan pul</th>
                                <th class="p-3 text-right">Nasiya Chiqarilgan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @forelse($staffReport['staff'] as $st)
                                <tr>
                                    <td class="p-3 font-sans font-semibold text-slate-900">{{ $st['name'] }}</td>
                                    <td class="p-3 font-sans text-slate-600">{{ $st['role'] }}</td>
                                    <td class="p-3 text-right text-slate-700 font-bold">{{ number_format($st['orders_count']) }}</td>
                                    <td class="p-3 text-right font-bold text-blue-700">{{ number_format($st['total_amount']) }}</td>
                                    <td class="p-3 text-right text-emerald-600">{{ number_format($st['paid_cash'] + $st['paid_card'] + $st['paid_bank']) }}</td>
                                    <td class="p-3 text-right text-amber-600">{{ number_format($st['debt_amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-600 font-sans">
                                        Xodimlar faoliyati bo'yicha ma'lumot mavjud emas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 8: QURILMALAR VA SYNC -->
    @elseif($activeTab === 'sync' && $syncReport)
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800">Offline Qurilmalar va Sinxronizatsiya Holati</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-3">Qurilma Kodi</th>
                                <th class="p-3">Nomi</th>
                                <th class="p-3">Turi</th>
                                <th class="p-3">Holati</th>
                                <th class="p-3">Oxirgi Aloqa</th>
                                <th class="p-3 text-right">Faol Rezervlar (Dona)</th>
                                <th class="p-3">Freeze Holati</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($syncReport['devices'] as $d)
                                <tr>
                                    <td class="p-3 font-mono font-bold text-blue-700">{{ $d['device_code'] }}</td>
                                    <td class="p-3 font-medium text-slate-800">{{ $d['device_name'] }}</td>
                                    <td class="p-3 text-slate-600">{{ $d['device_type'] }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold {{ $d['status'] === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $d['status'] }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono text-slate-600">{{ $d['last_seen_at'] }}</td>
                                    <td class="p-3 text-right font-mono font-bold text-slate-900">{{ number_format($d['total_reserved_units']) }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold {{ $d['freeze_acknowledged'] ? 'bg-blue-100 text-blue-800' : ($d['freeze_requested'] ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                            {{ $d['freeze_acknowledged'] ? 'ACK' : ($d['freeze_requested'] ? 'REQUESTED' : 'NORMAL') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-600">Qurilmalar topilmadi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
