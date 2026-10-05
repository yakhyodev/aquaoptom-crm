<div class="space-y-6" wire:poll.30s>
    <!-- Header & Period Filter Toolbar -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Sotilgan mahsulotlar tarixi</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Sana yoki mijozni tanlang. Nima sotilgani, to‘langan pul va qolgan qarzni ko‘ring.
                </p>
            </div>
            <!-- Export Buttons -->
            <div class="flex items-center gap-2">
                <button
                    wire:click="export('csv')"
                    class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-slate-900 rounded-lg text-xs font-semibold shadow-xs flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Excel (CSV) Yuklab Olish</span>
                </button>
                <button
                    wire:click="export('pdf')"
                    class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-slate-900 rounded-lg text-xs font-semibold shadow-xs flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>PDF Yuklab Olish</span>
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

    <!-- KPI Summary Cards (Aggregated across whole filtered dataset) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-600  block">Jami Savdo</span>
            <span class="text-lg font-bold text-slate-900 font-mono mt-1 block">
                {{ number_format($summary['kpi']['total_gross_sales']) }}
            </span>
            <span class="text-xs text-slate-600">so'm (brutto)</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-emerald-600  block">Dastlabki Naqd</span>
            <span class="text-lg font-bold text-emerald-700 font-mono mt-1 block">
                {{ number_format($summary['kpi']['cash_at_pos']) }}
            </span>
            <span class="text-xs text-slate-600">POS naqd tushum</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-blue-600  block">Dastlabki Karta/Bank</span>
            <span class="text-lg font-bold text-blue-700 font-mono mt-1 block">
                {{ number_format($summary['kpi']['card_at_pos'] + $summary['kpi']['bank_at_pos']) }}
            </span>
            <span class="text-xs text-slate-600">karta + bank</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-amber-600  block">Savdoda qolgan qarz</span>
            <span class="text-lg font-bold text-amber-700 font-mono mt-1 block">
                {{ number_format($summary['kpi']['total_initial_debt']) }}
            </span>
            <span class="text-xs text-slate-600">savdodagi qarz</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-rose-600  block">Qaytarilgan mahsulotlar</span>
            <span class="text-lg font-bold text-rose-700 font-mono mt-1 block">
                {{ number_format($summary['kpi']['total_returns']) }}
            </span>
            <span class="text-xs text-slate-600">{{ $summary['kpi']['total_returns_count'] }} ta qaytarish</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-purple-600  block">Qaytarishdan keyingi savdo</span>
            <span class="text-lg font-bold text-purple-700 font-mono mt-1 block">
                {{ number_format($summary['kpi']['net_sales']) }}
            </span>
            <span class="text-xs text-slate-600">O'rtacha chek: {{ number_format($summary['kpi']['average_ticket']) }}</span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Mijoz bo'yicha</label>
                <select wire:model.live="customerId" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg">
                    <option value="">Barcha mijozlar</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Xodim (Kassir)</label>
                <select wire:model.live="staffId" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg">
                    <option value="">Barcha xodimlar</option>
                    @foreach($staffUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} (<x-enum-label :value="$u->role" />)</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">To'lov turi</label>
                <select wire:model.live="paymentMethod" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg">
                    <option value="">Barcha turlar</option>
                    <option value="CASH">Naqd pul</option>
                    <option value="CARD">Karta / Terminal</option>
                    <option value="BANK">Bank hisobi</option>
                    <option value="DEBT">To'liq Nasiya</option>
                    <option value="MIXED">Aralash</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Holati</label>
                <select wire:model.live="status" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg">
                    <option value="">Barchasi</option>
                    <option value="COMPLETED">Yakunlangan</option>
                    <option value="REFUNDED">To'liq qaytarilgan</option>
                    <option value="PARTIALLY_REFUNDED">Qisman qaytarilgan</option>
                    <option value="CANCELLED">Bekor qilingan</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Qidiruv</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Chek # yoki izoh..."
                    class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg"
                >
            </div>
        </div>
    </div>

    <!-- Sales Data Table -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800">
                Savdolar Ro'yxati (Jami: {{ $sales->total() }} ta)
            </h2>
            <span class="text-xs text-slate-600">
                Ko'rsatilmoqda: {{ $sales->firstItem() ?? 0 }}-{{ $sales->lastItem() ?? 0 }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 border-b">
                    <tr>
                        <th class="p-3">Chek #</th>
                        <th class="p-3">Vaqt (Toshkent)</th>
                        <th class="p-3">Mijoz</th>
                        <th class="p-3">To'lov Turi</th>
                        <th class="p-3 text-right">Jami Summa</th>
                        <th class="p-3 text-right">Dastlabki To'langan</th>
                        <th class="p-3 text-right">Savdoda qolgan qarz</th>
                        <th class="p-3">Holati</th>
                        <th class="p-3">Mas'ul</th>
                        <th class="p-3 text-right">Amal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sales as $sale)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-3 font-mono font-bold text-blue-700">
                                {{ $sale->invoice_number }}
                            </td>
                            <td class="p-3 text-slate-600">
                                {{ Carbon\Carbon::parse($sale->posted_at ?: $sale->created_at)->setTimezone('Asia/Tashkent')->format('d.m.Y H:i') }}
                            </td>
                            <td class="p-3 font-medium text-slate-900">
                                {{ $sale->customer?->name ?: 'Tezkor xaridor' }}
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-xs font-bold
                                    {{ $sale->payment_method === 'CASH' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $sale->payment_method === 'CARD' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $sale->payment_method === 'BANK' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $sale->payment_method === 'DEBT' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ !in_array($sale->payment_method, ['CASH','CARD','BANK','DEBT']) ? 'bg-slate-100 text-slate-700' : '' }}">
                                    <x-enum-label :value="$sale->payment_method ?: $sale->payment_type ?: 'CASH'" />
                                </span>
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-900">
                                {{ number_format($sale->total_amount) }}
                            </td>
                            <td class="p-3 text-right font-mono font-semibold text-emerald-600">
                                {{ number_format($sale->paid_amount) }}
                            </td>
                            <td class="p-3 text-right font-mono font-semibold text-amber-600">
                                {{ number_format($sale->debt_amount) }}
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $sale->status === 'COMPLETED' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : '' }}
                                    {{ $sale->status === 'CANCELLED' ? 'bg-rose-50 text-rose-700 border border-rose-200' : '' }}
                                    {{ str_contains($sale->status, 'REFUND') ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}">
                                    <x-enum-label :value="$sale->status" />
                                </span>
                            </td>
                            <td class="p-3 text-slate-600">
                                {{ $sale->creator?->name ?: '-' }}
                            </td>
                            <td class="p-3 text-right">
                                <button
                                    wire:click="openSaleDetail({{ $sale->id }})"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold">
                                    Ko'rish
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-600">
                                Tanlangan filtrlar va davr bo'yicha savdo operatsiyalari topilmadi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sales->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $sales->links() }}
            </div>
        @endif
    </div>

    <!-- Sale Detail Modal -->
    @if($showDetailModal && $selectedSale)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden space-y-4 p-6">
                <div class="flex items-center justify-between border-b pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-mono">
                            Chek #{{ $selectedSale->invoice_number }}
                        </h3>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Sana: {{ Carbon\Carbon::parse($selectedSale->posted_at ?: $selectedSale->created_at)->setTimezone('Asia/Tashkent')->format('Y-m-d H:i:s') }}
                        </p>
                    </div>
                    <button wire:click="closeSaleDetail" class="text-slate-600 hover:text-slate-600 font-bold text-lg">&times;</button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl text-xs">
                    <div>
                        <span class="text-slate-600 block">Mijoz:</span>
                        <strong class="text-slate-800">{{ $selectedSale->customer?->name ?: 'Tezkor xaridor' }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-600 block">Kassir:</span>
                        <strong class="text-slate-800">{{ $selectedSale->creator?->name ?: '-' }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-600 block">Kassa Hisobi:</span>
                        <strong class="text-slate-800">{{ $selectedSale->cashAccount?->name ?: '-' }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-600 block">Holati:</span>
                        <strong class="text-slate-800">{{ $selectedSale->status }}</strong>
                    </div>
                </div>

                <!-- Mahsulotlar Jadvali -->
                <div class="overflow-x-auto border rounded-xl">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-2.5">Mahsulot</th>
                                <th class="p-2.5 text-right">Dona</th>
                                <th class="p-2.5 text-right">Sotuv Narxi</th>
                                @if($canViewCost)
                                    <th class="p-2.5 text-right">Tannarx (Snapshot)</th>
                                @endif
                                <th class="p-2.5 text-right">Jami</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($selectedSale->items as $item)
                                <tr>
                                    <td class="p-2.5">
                                        <div class="font-medium text-slate-900">{{ $item->variant?->product?->name }}</div>
                                        <div class="text-xs text-slate-600 font-mono">{{ $item->variant?->volume?->name }} • {{ $item->variant?->sku }}</div>
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-bold">{{ number_format($item->quantity) }}</td>
                                    <td class="p-2.5 text-right font-mono">{{ number_format($item->sale_price) }}</td>
                                    @if($canViewCost)
                                        <td class="p-2.5 text-right font-mono text-slate-600">{{ number_format($item->purchase_cost_snapshot) }}</td>
                                    @endif
                                    <td class="p-2.5 text-right font-mono font-bold text-slate-900">{{ number_format($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 font-bold border-t">
                            <tr>
                                <td colspan="{{ $canViewCost ? 4 : 3 }}" class="p-2.5 text-right">Jami:</td>
                                <td class="p-2.5 text-right font-mono text-slate-900">{{ number_format($selectedSale->total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- To'lov va Qarz Tafsiloti -->
                <div class="p-3 bg-amber-50/50 border border-amber-200 rounded-xl flex items-center justify-between text-xs font-mono">
                    <div>
                        <span class="text-slate-600">To'langan:</span>
                        <strong class="text-emerald-700 ml-1">{{ number_format($selectedSale->paid_amount) }} so'm</strong>
                    </div>
                    <div>
                        <span class="text-slate-600">Nasiya (Qarz):</span>
                        <strong class="text-amber-700 ml-1">{{ number_format($selectedSale->debt_amount) }} so'm</strong>
                    </div>
                </div>

                <!-- Qaytarishlar (Agar mavjud bo'lsa) -->
                @if($selectedSale->returns->isNotEmpty())
                    <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl space-y-2">
                        <strong class="text-rose-900 text-xs flex items-center gap-1">
                            Ushbu savdo bo'yicha qaytarishlar mavjud:
                        </strong>
                        @foreach($selectedSale->returns as $ret)
                            <div class="text-xs text-rose-800 flex justify-between font-mono">
                                <span>#{{ $ret->return_number }} ({{ $ret->posted_at?->format('Y-m-d H:i') }})</span>
                                <span>-{{ number_format($ret->total_amount) }} so'm (Naqd refund: {{ number_format($ret->refund_amount) }}, Qarz kamayishi: {{ number_format($ret->debt_reduction_amount) }})</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end pt-2">
                    <button wire:click="closeSaleDetail" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold">
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
