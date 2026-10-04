<div class="space-y-6">
    <!-- Header & Tabs -->
    <div class="sticky top-16 z-20 bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Ombor & Zaxiralar Boshqaruvi</h1>
                <p class="text-sm text-slate-500 mt-1">Yangi tovar qabul qilish uchun «Kirim qilish» tugmasini bosing. Qoldiqlar va kalkulyator alohida oynalarda.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    wire:click="$set('activeTab', 'inward')"
                    class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    + Kirim qilish
                </button>
                <button
                    wire:click="$set('activeTab', 'balances')"
                    class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 {{ $activeTab === 'balances' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Ombor Qoldiqlari
                </button>
                <button
                    wire:click="$set('activeTab', 'calculator')"
                    class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 {{ $activeTab === 'calculator' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Interaktiv Kalkulyator
                </button>
                <button
                    wire:click="$set('activeTab', 'returns')"
                    class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 {{ $activeTab === 'returns' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    Qaytarishlar
                </button>
                <button
                    wire:click="$set('activeTab', 'damages')"
                    class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 {{ $activeTab === 'damages' ? 'bg-rose-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Brak & Yaroqsiz
                </button>
                <button
                    wire:click="$set('activeTab', 'audits')"
                    class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 {{ $activeTab === 'audits' ? 'bg-purple-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Inventarizatsiya
                </button>
            </div>
        </div>
    </div>

    @if($activeTab === 'balances')
        <!-- TAB 1: OMBOR QOLDIQLARI -->
        <div class="space-y-6">
            <!-- 1. Agregatlar paneli (Butun filtrlangan baza bo'yicha) -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white rounded-xl p-5 shadow-sm border border-slate-700">
                <div class="flex items-center justify-between border-b border-slate-700/60 pb-3 mb-4">
                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Butun filtrlangan baza bo'yicha umumiy jamlama (Faqat joriy sahifa emas)
                    </span>
                    <span class="text-xs text-slate-400">Jami turlar: <strong class="text-white">{{ number_format($totals['total_variants_count']) }}</strong></span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <!-- Fizik qoldiq -->
                    <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/50">
                        <div class="text-xs text-slate-400">Jismoniy Qoldiq</div>
                        <div class="text-lg font-bold text-white mt-0.5">{{ number_format($totals['total_physical_units']) }} <span class="text-xs font-normal text-slate-400">dona</span></div>
                    </div>

                    <!-- Erkin sotuv qoldig'i -->
                    <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/50">
                        <div class="text-xs text-slate-400">Erkin Qoldiq</div>
                        <div class="text-lg font-bold text-emerald-400 mt-0.5">{{ number_format($totals['total_free_units']) }} <span class="text-xs font-normal text-slate-400">dona</span></div>
                    </div>

                    <!-- Ajratilgan qoldiq -->
                    <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/50">
                        <div class="text-xs text-slate-400">Ajratilgan (Rezerv)</div>
                        <div class="text-lg font-bold text-amber-400 mt-0.5">{{ number_format($totals['total_allocated_units']) }} <span class="text-xs font-normal text-slate-400">dona</span></div>
                    </div>

                    <!-- Tannarx qiymati (Rolga bog'liq) -->
                    @if($this->canViewCost)
                        <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/50">
                            <div class="text-xs text-slate-400">Jami Tannarx Qiymati</div>
                            <div class="text-lg font-bold text-indigo-300 mt-0.5">{{ number_format($totals['total_cost_value']) }} <span class="text-xs font-normal text-slate-400">so'm</span></div>
                        </div>
                    @endif

                    <!-- Tizim sotuv qiymati -->
                    <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/50">
                        <div class="text-xs text-slate-400">Tizim Sotuv Qiymati</div>
                        <div class="text-lg font-bold text-sky-400 mt-0.5">{{ number_format($totals['total_sale_value']) }} <span class="text-xs font-normal text-slate-400">so'm</span></div>
                    </div>

                    <!-- Muammolar / Ogohlantirishlar -->
                    <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/50 flex flex-col justify-between">
                        <div class="text-xs text-slate-400">Ogohlantirishlar</div>
                        <div class="flex items-center gap-2 mt-1 text-xs">
                            @if($totals['low_stock_count'] > 0)
                                <span class="px-1.5 py-0.5 bg-red-900/60 text-red-300 rounded font-medium" title="Kam qoldiq">Kam: {{ $totals['low_stock_count'] }}</span>
                            @endif
                            @if($totals['missing_price_count'] > 0)
                                <span class="px-1.5 py-0.5 bg-amber-900/60 text-amber-300 rounded font-medium" title="Sotuv narxi belgilanmagan">Narxsiz: {{ $totals['missing_price_count'] }}</span>
                            @endif
                            @if($totals['stale_allocations_count'] > 0)
                                <span class="px-1.5 py-0.5 bg-purple-900/60 text-purple-300 rounded font-medium" title="Eskirgan ajratma">Eskirgan: {{ $totals['stale_allocations_count'] }}</span>
                            @endif
                            @if($totals['low_stock_count'] == 0 && $totals['missing_price_count'] == 0 && $totals['stale_allocations_count'] == 0)
                                <span class="text-emerald-400 font-medium">Barchasi joyida ✓</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Filtrlar paneli -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <!-- Qidiruv -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Qidiruv (Nom, kod, SKU, shtrix-kod, hajm)</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Masalan: Fanta, PRD-0001, 1L, FANTA-1000..."
                                class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>

                    <!-- Min / Max qoldiq -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Qoldiq oralig'i (dona)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" wire:model.live.debounce.400ms="minStock" placeholder="Min" class="w-full px-2.5 py-2 text-sm border border-slate-300 rounded-lg">
                            <input type="number" wire:model.live.debounce.400ms="maxStock" placeholder="Max" class="w-full px-2.5 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                    </div>

                    <!-- Chegara / Threshold -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Chegara holati</label>
                        <select wire:model.live="thresholdFilter" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white">
                            <option value="all">Barcha chegaralar</option>
                            <option value="below_threshold">Chegaradan past (Kam qoldiq)</option>
                            <option value="normal">Yetarli qoldiq</option>
                        </select>
                    </div>
                </div>

                <!-- 2-qator filtrlar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3 pt-2 border-t border-slate-100">
                    <!-- Nol qoldiq -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Mavjudlik</label>
                        <select wire:model.live="zeroStockFilter" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white">
                            <option value="all">Barchasi</option>
                            <option value="non_zero">Qoldiq > 0</option>
                            <option value="zero_only">Qoldiq = 0 (Tugagan)</option>
                        </select>
                    </div>

                    <!-- Narx holati -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Sotuv narxi</label>
                        <select wire:model.live="priceFilter" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white">
                            <option value="all">Barchasi</option>
                            <option value="has_price">Narxi belgilangan</option>
                            <option value="no_price">Narxi belgilanmagan</option>
                        </select>
                    </div>

                    <!-- Holati (Arxiv) -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Arxiv holati</label>
                        <select wire:model.live="statusFilter" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white">
                            <option value="active">Faqat faollar</option>
                            <option value="archived">Arxivlanganlar</option>
                            <option value="all">Barcha tovarlar</option>
                        </select>
                    </div>

                    <!-- Sekin sotiladigan -->
                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-slate-700 font-medium">
                            <input type="checkbox" wire:model.live="slowMovingFilter" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Sekin sotiladigan (30 kun)
                        </label>
                    </div>

                    <!-- Filtrlarni tozalash -->
                    <div class="flex items-center justify-end pt-5">
                        <button wire:click="resetFilters" class="text-xs text-slate-500 hover:text-slate-800 underline font-medium">
                            Filtrlarni tozalash
                        </button>
                    </div>
                </div>

                <!-- Hajmlar checkboxlari -->
                <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 mr-1">Hajmlar:</span>
                    @foreach($allVolumes as $vol)
                        <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs border cursor-pointer transition-all {{ in_array($vol->id, $selectedVolumeIds) ? 'bg-blue-50 border-blue-300 text-blue-700 font-medium' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' }}">
                            <input type="checkbox" value="{{ $vol->id }}" wire:model.live="selectedVolumeIds" class="hidden">
                            <span>{{ $vol->name ?? round($vol->value_ml / 1000, 2).'L' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- 3. Ombor Ro'yxati Jadvali -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                <th class="p-3 cursor-pointer hover:text-blue-600" wire:click="setSort('name')">
                                    Mahsulot / Hajm
                                    @if($sortBy === 'name') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                </th>
                                <th class="p-3 cursor-pointer hover:text-blue-600" wire:click="setSort('sku')">
                                    SKU / Kod
                                    @if($sortBy === 'sku') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                </th>
                                <th class="p-3 cursor-pointer hover:text-blue-600 text-right" wire:click="setSort('quantity')">
                                    Fizik Qoldiq
                                    @if($sortBy === 'quantity') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                </th>
                                <th class="p-3 text-right">Erkin Qoldiq</th>
                                <th class="p-3 text-right">Ajratilgan</th>
                                <th class="p-3 text-center">Chegara</th>
                                <th class="p-3 text-center">Holat</th>
                                <th class="p-3 cursor-pointer hover:text-blue-600 text-right" wire:click="setSort('price')">
                                    Tizim Narxi
                                    @if($sortBy === 'price') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                </th>
                                @if($this->canViewCost)
                                    <th class="p-3 cursor-pointer hover:text-blue-600 text-right" wire:click="setSort('cost')">
                                        WAC Tannarx
                                        @if($sortBy === 'cost') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                    </th>
                                    <th class="p-3 cursor-pointer hover:text-blue-600 text-right" wire:click="setSort('total_value')">
                                        Ombor Qiymati
                                        @if($sortBy === 'total_value') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                    </th>
                                @endif
                                <th class="p-3 text-right">Sotuv Qiymati</th>
                                <th class="p-3 text-center">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($stockList as $item)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="p-3">
                                        <div class="font-semibold text-slate-900">{{ $item->product?->name ?? 'Noma\'lum' }}</div>
                                        <div class="text-[11px] text-slate-500 font-mono">{{ $item->volume?->name ?? round(($item->volume?->value_ml ?? 0)/1000, 2).'L' }}</div>
                                    </td>
                                    <td class="p-3 font-mono text-[11px] text-slate-600">
                                        <div>{{ $item->sku }}</div>
                                        @if($item->barcode) <div class="text-slate-400 text-[10px]">{{ $item->barcode }}</div> @endif
                                    </td>
                                    <td class="p-3 text-right font-bold {{ $item->physical_quantity <= 0 ? 'text-red-500' : 'text-slate-900' }}">
                                        {{ number_format($item->physical_quantity) }}
                                    </td>
                                    <td class="p-3 text-right font-semibold text-emerald-600">
                                        {{ number_format($item->free_quantity) }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <span class="font-medium {{ $item->allocated_quantity > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                            {{ number_format($item->allocated_quantity) }}
                                        </span>
                                        @if($item->has_stale_allocation)
                                            <span class="ml-1 inline-block w-2 h-2 rounded-full bg-purple-500" title="Eskirgan offline ajratma mavjud!"></span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center text-slate-500 font-mono">
                                        {{ $item->minimum_stock > 0 ? number_format($item->minimum_stock) : '-' }}
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($item->is_out_of_stock)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-800">Qoldiq 0</span>
                                        @elseif($item->is_low_stock)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">Kam qoldiq</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700">Yetarli</span>
                                        @endif

                                        @if(! $item->has_price)
                                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600" title="Sotuv narxi belgilanmagan">Narxsiz</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right font-mono">
                                        @if($item->has_price)
                                            <span class="font-semibold text-slate-800">{{ number_format($item->default_sale_price) }}</span>
                                        @else
                                            <span class="text-slate-400 italic">Belgilanmagan</span>
                                        @endif
                                    </td>
                                    @if($this->canViewCost)
                                        <td class="p-3 text-right font-mono text-slate-700">
                                            {{ $item->wac_cost ? number_format($item->wac_cost) : '-' }}
                                        </td>
                                        <td class="p-3 text-right font-mono font-semibold text-indigo-700">
                                            {{ $item->total_cost_value ? number_format($item->total_cost_value) : '-' }}
                                        </td>
                                    @endif
                                    <td class="p-3 text-right font-mono font-semibold text-sky-700">
                                        {{ $item->has_price ? number_format($item->total_sale_value) : '-' }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <button
                                            wire:click="openVariantDetail({{ $item->id }})"
                                            class="px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md transition-colors">
                                            Tafsilot
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="p-8 text-center text-slate-400">
                                        Tanlangan filtrlar bo'yicha hech qanday tovar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-slate-200 bg-slate-50/50">
                    {{ $stockList->links() }}
                </div>
            </div>
        </div>
    @elseif($activeTab === 'calculator')
        <!-- TAB 2: INTERAKTIV KALKULYATOR -->
        <div class="space-y-6">
            <!-- Xabarlar -->
            @if($calculatorMessage)
                <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm font-medium flex items-center justify-between">
                    <span>{{ $calculatorMessage }}</span>
                    <button wire:click="$set('calculatorMessage', null)" class="text-emerald-500 hover:text-emerald-700 font-bold">×</button>
                </div>
            @endif
            @if($calculatorError)
                <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm font-medium flex items-center justify-between">
                    <span>{{ $calculatorError }}</span>
                    <button wire:click="$set('calculatorError', null)" class="text-red-500 hover:text-red-700 font-bold">×</button>
                </div>
            @endif

            <!-- Boshqaruv & Filtrlash paneli -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Kalkulyator Filtrlari</h2>
                        <p class="text-xs text-slate-500">Hisob-kitob qilish uchun mahsulotlar va hajmlarni tanlang</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="selectAllCalculator" class="px-3 py-1.5 text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg transition-colors">
                            Barchasini Tanlash
                        </button>
                        <button wire:click="clearCalculator" class="px-3 py-1.5 text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg transition-colors">
                            Tozalash
                        </button>
                    </div>
                </div>

                <!-- Mahsulotlar checkboxlari -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Mahsulotlar:</label>
                        <div class="text-[11px] space-x-2">
                            <button wire:click="selectAllProducts" class="text-blue-600 hover:underline">Barcha mahsulotlar</button>
                            <button wire:click="clearProducts" class="text-slate-400 hover:underline">Bekor qilish</button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($allProducts as $p)
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs border cursor-pointer transition-all {{ in_array($p->id, $calcSelectedProductIds) ? 'bg-indigo-50 border-indigo-300 text-indigo-800 font-semibold shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' }}">
                                <input type="checkbox" value="{{ $p->id }}" wire:model.live="calcSelectedProductIds" class="hidden">
                                <span>{{ $p->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Hajmlar checkboxlari -->
                <div class="pt-3 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Hajmlar (Litrlar):</label>
                        <div class="text-[11px] space-x-2">
                            <button wire:click="selectAllVolumes" class="text-blue-600 hover:underline">Barcha hajmlar</button>
                            <button wire:click="clearVolumes" class="text-slate-400 hover:underline">Bekor qilish</button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($allVolumes as $vol)
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs border cursor-pointer transition-all {{ in_array($vol->id, $calcSelectedVolumeIds) ? 'bg-blue-50 border-blue-300 text-blue-800 font-semibold shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' }}">
                                <input type="checkbox" value="{{ $vol->id }}" wire:model.live="calcSelectedVolumeIds" class="hidden">
                                <span>{{ $vol->name ?? round($vol->value_ml / 1000, 2).'L' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Kalkulyator Natijalari (Kutilayotgan Yalpi Foyda - Hech qachon haqiqiy pul yoki kassa deb nomlanmaydi!) -->
            <div class="bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 text-white rounded-xl p-6 shadow-sm border border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-5">
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">Kutilayotgan Foyda & Qiymat Hisob-kitobi</h3>
                        <p class="text-xs text-slate-400">Sotilmagan ombor zaxirasining kutilayotgan yalpi rentabelligi</p>
                    </div>
                    <span class="text-xs bg-indigo-900/60 text-indigo-300 px-3 py-1 rounded-full font-semibold border border-indigo-700/50">
                        Tanlangan: {{ $calcResults['selected_count'] }} ta variant
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Mavjud dona -->
                    <div class="bg-slate-800/70 rounded-xl p-4 border border-slate-700/60">
                        <div class="text-xs font-medium text-slate-400">Mavjud Dona</div>
                        <div class="text-2xl font-black text-white mt-1">{{ number_format($calcResults['total_quantity_units']) }} <span class="text-sm font-normal text-slate-400">dona</span></div>
                        <div class="text-xs text-slate-400 mt-2 flex justify-between">
                            <span>Erkin: <strong class="text-emerald-400">{{ number_format($calcResults['total_free_units']) }}</strong></span>
                            <span>Ajratilgan: <strong class="text-amber-400">{{ number_format($calcResults['total_allocated_units']) }}</strong></span>
                        </div>
                    </div>

                    <!-- Jami tannarx qiymati -->
                    @if($this->canViewCost)
                        <div class="bg-slate-800/70 rounded-xl p-4 border border-slate-700/60">
                            <div class="text-xs font-medium text-slate-400">Jami Tannarx Qiymati</div>
                            <div class="text-2xl font-black text-indigo-300 mt-1">{{ number_format($calcResults['total_cost_value']) }} <span class="text-sm font-normal text-slate-400">so'm</span></div>
                            <div class="text-xs text-slate-400 mt-2">
                                Joriy o'rtacha (WAC) asosida
                            </div>
                        </div>
                    @endif

                    <!-- Tizim sotuv qiymati -->
                    <div class="bg-slate-800/70 rounded-xl p-4 border border-slate-700/60">
                        <div class="text-xs font-medium text-slate-400">Tizim Sotuv Qiymati</div>
                        <div class="text-2xl font-black text-sky-400 mt-1">{{ number_format($calcResults['total_potential_sale_value']) }} <span class="text-sm font-normal text-slate-400">so'm</span></div>
                        <div class="text-xs text-slate-400 mt-2">
                            Standart yoki simulyatsiya narxida
                        </div>
                    </div>

                    <!-- Kutilayotgan Yalpi Foyda -->
                    @if($this->canViewCost)
                        <div class="bg-slate-800/70 rounded-xl p-4 border border-indigo-700/60 bg-gradient-to-br from-indigo-900/40 to-slate-800/60">
                            <div class="text-xs font-medium text-emerald-300">Kutilayotgan Yalpi Foyda</div>
                            <div class="text-2xl font-black text-emerald-400 mt-1">{{ number_format($calcResults['expected_gross_profit']) }} <span class="text-sm font-normal text-slate-400">so'm</span></div>
                            <div class="text-xs text-emerald-200/80 mt-2 flex items-center justify-between">
                                <span>Kutilayotgan Marja:</span>
                                <strong class="text-emerald-300 text-sm font-bold">{{ $calcResults['potential_margin_percent'] }}%</strong>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Narxsiz variantlar bo'yicha ogohlantirish -->
                @if($calcResults['missing_price_count'] > 0)
                    <div class="mt-4 p-3 bg-amber-950/60 border border-amber-700/60 rounded-lg text-amber-200 text-xs flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <strong>Diqqat:</strong> {{ $calcResults['missing_price_count'] }} ta variant uchun tizim sotuv narxi belgilanmagan (jami {{ number_format($calcResults['missing_price_units']) }} dona).
                            Kutilayotgan sotuv qiymati va yalpi foyda faqat narxi mavjud qism bo'yicha hisoblandi (to'liq emas). Ushbu tovarlar nol narxga tenglashtirilmadi!
                        </div>
                    </div>
                @endif
            </div>

            <!-- Jadval va Simulyatsiya Narxlari -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Variantlar Rentabelligi & Narx Simulyatsiyasi</h3>
                        <p class="text-xs text-slate-500">Taxminiy narx kiritish faqat simulyatsiya; katalogga saqlash uchun alohida ruxsat talab qilinadi</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(!empty($simulationPrices))
                            <button wire:click="clearSimulationPrices" class="px-3 py-1.5 text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg transition-colors">
                                Simulyatsiyani Bekor Qilish
                            </button>
                        @endif
                        @if($this->canManagePrices && !empty($simulationPrices))
                            <button wire:click="applySimulationPricesToCatalog" class="px-3 py-1.5 text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 rounded-lg transition-colors shadow-xs">
                                Tizim Narxlariga Saqlash
                            </button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                <th class="p-3">Mahsulot / Hajm</th>
                                <th class="p-3 text-right">Qoldiq (Dona)</th>
                                <th class="p-3 text-right">Qoldiq (Yashik)</th>
                                @if($this->canViewCost)
                                    <th class="p-3 text-right">WAC Tannarx</th>
                                @endif
                                <th class="p-3 text-right">Tizim Narxi</th>
                                <th class="p-3 text-center w-36">Taxminiy Narx (Simulyatsiya)</th>
                                <th class="p-3 text-right">Kutilayotgan Sotuv</th>
                                @if($this->canViewCost)
                                    <th class="p-3 text-right">Kutilayotgan Foyda</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($calcResults['items'] as $item)
                                <tr class="hover:bg-slate-50/80 transition-colors {{ !$item['has_price'] ? 'bg-amber-50/40' : '' }}">
                                    <td class="p-3">
                                        <div class="font-semibold text-slate-900">{{ $item['product_name'] }}</div>
                                        <div class="text-[11px] text-slate-500 font-mono">{{ $item['volume_name'] }} • {{ $item['sku'] }}</div>
                                    </td>
                                    <td class="p-3 text-right font-bold text-slate-900">
                                        {{ number_format($item['stock_units']) }}
                                    </td>
                                    <td class="p-3 text-right font-mono text-slate-500">
                                        {{ $item['stock_yashik'] }}
                                    </td>
                                    @if($this->canViewCost)
                                        <td class="p-3 text-right font-mono text-slate-600">
                                            {{ $item['wac_cost'] ? number_format($item['wac_cost']) : '-' }}
                                        </td>
                                    @endif
                                    <td class="p-3 text-right font-mono">
                                        {{ $item['system_sale_price'] > 0 ? number_format($item['system_sale_price']) : '-' }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <input
                                            type="number"
                                            placeholder="{{ $item['system_sale_price'] > 0 ? $item['system_sale_price'] : 'Narx kiritish' }}"
                                            value="{{ $simulationPrices[$item['variant_id']] ?? '' }}"
                                            wire:change="setSimulationPrice({{ $item['variant_id'] }}, $event.target.value)"
                                            class="w-32 px-2.5 py-1 text-xs border rounded text-right font-mono focus:ring-1 focus:ring-blue-500 {{ isset($simulationPrices[$item['variant_id']]) ? 'border-indigo-500 bg-indigo-50/50 font-bold' : 'border-slate-300' }}">
                                    </td>
                                    <td class="p-3 text-right font-mono font-semibold text-sky-700">
                                        {{ $item['potential_sale'] !== null ? number_format($item['potential_sale']) : '-' }}
                                    </td>
                                    @if($this->canViewCost)
                                        <td class="p-3 text-right font-mono font-bold {{ ($item['potential_profit'] ?? 0) < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                            {{ $item['potential_profit'] !== null ? number_format($item['potential_profit']) : '-' }}
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-slate-400">
                                        Kalkulyatsiyada hisoblash uchun yuqoridagi filtrlardan mahsulot yoki hajmlarni tanlang.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @elseif($activeTab === 'inward')
        <!-- TAB 3: TOVAR KIRIMI (QUICK INWARD) -->
        <div class="space-y-4">
            @livewire('quick-inward')
        </div>
    @elseif($activeTab === 'returns')
        <!-- TAB 4: QAYTARISHLAR (RETURNS) -->
        <div class="space-y-6">
            @if($saleReturnSuccess)
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm flex items-center justify-between">
                    <span>{{ $saleReturnSuccess }}</span>
                    <button wire:click="$set('saleReturnSuccess', null)" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
                </div>
            @endif
            @if($saleReturnError)
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm flex items-center justify-between">
                    <span>{{ $saleReturnError }}</span>
                    <button wire:click="$set('saleReturnError', null)" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                </div>
            @endif
            @if($supplierReturnSuccess)
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm flex items-center justify-between">
                    <span>{{ $supplierReturnSuccess }}</span>
                    <button wire:click="$set('supplierReturnSuccess', null)" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
                </div>
            @endif
            @if($supplierReturnError)
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm flex items-center justify-between">
                    <span>{{ $supplierReturnError }}</span>
                    <button wire:click="$set('supplierReturnError', null)" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- 1. Sotuv Qaytarishlari -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                Mijoz Qaytarishlari (Sale Returns)
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Original sotuv narxi va cost snapshot asosida</p>
                        </div>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5">
                                + Qaytarish Qilish
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-lg z-30 p-2 text-xs space-y-1">
                                <div class="font-semibold text-slate-600 px-2 py-1 border-b">So'nggi savdolar:</div>
                                @forelse($recentSales as $sale)
                                    <button wire:click="openSaleReturnModal({{ $sale->id }}); open = false" class="w-full text-left px-2 py-1.5 rounded hover:bg-amber-50 flex items-center justify-between">
                                        <span class="font-mono font-medium">{{ $sale->invoice_number }}</span>
                                        <span class="text-slate-500 truncate max-w-[120px]">{{ $sale->customer->name ?? 'Mehmon' }}</span>
                                    </button>
                                @empty
                                    <div class="px-2 py-2 text-slate-400 text-center">Savdolar topilmadi</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-96 border rounded-lg">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b">
                                <tr>
                                    <th class="p-2.5">Qaytarish #</th>
                                    <th class="p-2.5">Savdo Cheki</th>
                                    <th class="p-2.5">Mijoz</th>
                                    <th class="p-2.5 text-right">Tovar Qiymati</th>
                                    <th class="p-2.5 text-right">Qaytarilgan Pul</th>
                                    <th class="p-2.5 text-right">Qarz Kamaydi</th>
                                    <th class="p-2.5">Sana</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($saleReturns as $sr)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="p-2.5 font-mono font-semibold text-amber-700">{{ $sr->return_number }}</td>
                                        <td class="p-2.5 font-mono text-slate-600">{{ $sr->sale->invoice_number ?? '-' }}</td>
                                        <td class="p-2.5 text-slate-800 font-medium">{{ $sr->customer->name ?? 'Mehmon' }}</td>
                                        <td class="p-2.5 text-right font-mono font-semibold text-slate-900">{{ number_format($sr->total_amount) }}</td>
                                        <td class="p-2.5 text-right font-mono text-rose-600">{{ number_format($sr->refund_amount) }}</td>
                                        <td class="p-2.5 text-right font-mono text-emerald-600">{{ number_format($sr->debt_deduction_amount) }}</td>
                                        <td class="p-2.5 text-slate-500 text-[11px]">{{ $sr->posted_at ? $sr->posted_at->format('Y-m-d H:i') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-6 text-center text-slate-400">Hozircha sotuv qaytarishlari qayd etilmagan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. Ta'minotchiga Qaytarishlar -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                Ta'minotchi Qaytarishlari (Supplier Returns)
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Tijoriy kredit va joriy WAC chiqimi farqi bilan</p>
                        </div>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5">
                                + Ta'minotchiga Qaytarish
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-lg z-30 p-2 text-xs space-y-1">
                                <div class="font-semibold text-slate-600 px-2 py-1 border-b">So'nggi kirimlar:</div>
                                @forelse($recentPurchases as $purchase)
                                    <button wire:click="openSupplierReturnModal({{ $purchase->id }}); open = false" class="w-full text-left px-2 py-1.5 rounded hover:bg-blue-50 flex items-center justify-between">
                                        <span class="font-mono font-medium">{{ $purchase->invoice_number }}</span>
                                        <span class="text-slate-500 truncate max-w-[120px]">{{ $purchase->supplier->name ?? 'Noma\'lum' }}</span>
                                    </button>
                                @empty
                                    <div class="px-2 py-2 text-slate-400 text-center">Kirimlar topilmadi</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-96 border rounded-lg">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b">
                                <tr>
                                    <th class="p-2.5">Qaytarish #</th>
                                    <th class="p-2.5">Kirim Nakladnoy</th>
                                    <th class="p-2.5">Ta'minotchi</th>
                                    <th class="p-2.5 text-right">Tijoriy Kredit</th>
                                    <th class="p-2.5 text-right">WAC Chiqim</th>
                                    <th class="p-2.5 text-right">Farq</th>
                                    <th class="p-2.5">Sana</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($purchaseReturns as $pr)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="p-2.5 font-mono font-semibold text-blue-700">{{ $pr->return_number }}</td>
                                        <td class="p-2.5 font-mono text-slate-600">{{ $pr->purchase->invoice_number ?? '-' }}</td>
                                        <td class="p-2.5 text-slate-800 font-medium">{{ $pr->supplier->name ?? '-' }}</td>
                                        <td class="p-2.5 text-right font-mono font-semibold text-emerald-600">{{ number_format($pr->total_credit_amount) }}</td>
                                        <td class="p-2.5 text-right font-mono text-slate-700">{{ number_format($pr->total_cost_amount) }}</td>
                                        <td class="p-2.5 text-right font-mono font-semibold {{ $pr->cost_discrepancy >= 0 ? 'text-indigo-600' : 'text-rose-600' }}">{{ number_format($pr->cost_discrepancy) }}</td>
                                        <td class="p-2.5 text-slate-500 text-[11px]">{{ $pr->posted_at ? $pr->posted_at->format('Y-m-d H:i') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-6 text-center text-slate-400">Hozircha ta'minotchi qaytarishlari qayd etilmagan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @elseif($activeTab === 'damages')
        <!-- TAB 5: BRAK VA YAROQSIZ TOVAR CHIQIMI -->
        <div class="space-y-6">
            @if($damageSuccess)
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm flex items-center justify-between">
                    <span>{{ $damageSuccess }}</span>
                    <button wire:click="$set('damageSuccess', null)" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
                </div>
            @endif
            @if($damageError)
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm flex items-center justify-between">
                    <span>{{ $damageError }}</span>
                    <button wire:click="$set('damageError', null)" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                </div>
            @endif

            <div class="bg-rose-50/70 border border-rose-200 rounded-xl p-4 flex items-start gap-3 text-rose-900 text-sm">
                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <strong class="font-semibold">Muhim qoida:</strong> Brak chiqimi faqat ombordagi WAC tannarx yo'qotishi hisoblanadi. U kassa harakati (Cash movement) yoki kassa operatsion xarajati (Cash expense) EMAS! Zaxira ajratmalariga daxl qilolmaydi.
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                            Brak & Yaroqsiz Tovar Chiqimlari Jurnali
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Yaroqsiz idishlar, sinishlar va yo'qotishlar hisobi</p>
                    </div>
                    <button wire:click="openDamageModal()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-2">
                        + Brakka Chiqarish
                    </button>
                </div>

                <div class="overflow-x-auto border rounded-lg">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b">
                            <tr>
                                <th class="p-3">Hujjat #</th>
                                <th class="p-3">Tovarlar</th>
                                <th class="p-3 text-right">Jami Dona</th>
                                <th class="p-3 text-right">Tannarx Yo'qotishi (WAC)</th>
                                <th class="p-3">Sabab</th>
                                <th class="p-3">Mas'ul</th>
                                <th class="p-3">Sana</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($damageRecords as $dr)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="p-3 font-mono font-semibold text-rose-700">{{ $dr->damage_number }}</td>
                                    <td class="p-3 text-slate-700">
                                        @foreach($dr->items as $dItem)
                                            <div class="font-medium">{{ $dItem->variant->product->name ?? 'Mahsulot' }} ({{ $dItem->variant->volume->name ?? '' }}): {{ number_format($dItem->quantity) }} dona</div>
                                        @endforeach
                                    </td>
                                    <td class="p-3 text-right font-mono font-bold text-slate-800">{{ number_format($dr->total_quantity) }}</td>
                                    <td class="p-3 text-right font-mono font-bold text-rose-600">{{ number_format($dr->total_loss_value) }} so'm</td>
                                    <td class="p-3 text-slate-600">{{ $dr->reason }}</td>
                                    <td class="p-3 text-slate-500">{{ $dr->creator->name ?? '-' }}</td>
                                    <td class="p-3 text-slate-500 text-[11px]">{{ $dr->posted_at ? $dr->posted_at->format('Y-m-d H:i') : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400">Brak chiqimlari mavjud emas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @elseif($activeTab === 'audits')
        <!-- TAB 6: INVENTARIZATSIYA VA TUZATISHLAR -->
        <div class="space-y-6">
            @if($auditSuccess)
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm flex items-center justify-between">
                    <span>{{ $auditSuccess }}</span>
                    <button wire:click="$set('auditSuccess', null)" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
                </div>
            @endif
            @if($auditError)
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm flex items-center justify-between">
                    <span>{{ $auditError }}</span>
                    <button wire:click="$set('auditError', null)" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                </div>
            @endif

            @if($selectedAudit)
                <!-- AKTIV INVENTARIZATSIYA VARAQASI -->
                <div class="bg-white rounded-xl shadow-sm border border-purple-200 p-5 space-y-5">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <h2 class="text-xl font-bold text-purple-950 font-mono">{{ $selectedAudit->audit_number }}</h2>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $selectedAudit->status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800' }}">
                                    {{ $selectedAudit->status }}
                                </span>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $selectedAudit->device_freeze_status === 'ACKNOWLEDGED' || $selectedAudit->device_freeze_status === 'NOT_REQUIRED' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800' }}">
                                    Freeze: {{ $selectedAudit->device_freeze_status }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Boshlangan: {{ $selectedAudit->started_at ? $selectedAudit->started_at->format('Y-m-d H:i') : '-' }} | Izoh: {{ $selectedAudit->notes }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="closeAuditView" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold">
                                Ro'yxatga Qaytish
                            </button>
                            @if($selectedAudit->status !== 'COMPLETED')
                                <button wire:click="saveAuditCounts" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-semibold shadow-xs">
                                    Natijalarni Saqlash
                                </button>
                                <button wire:click="executeApplyAudit(false)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-xs">
                                    Tasdiqlash & Muvofiqlashtirish
                                </button>
                                @if($selectedAudit->device_freeze_status === 'PENDING_ACK')
                                    <button wire:click="executeApplyAudit(true)" class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold" title="Uzilgan qurilmalarni kutmasdan majburiy tasdiqlash">
                                        Majburiy Tasdiqlash
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if($selectedAudit->device_freeze_status === 'PENDING_ACK')
                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 text-xs space-y-2">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                Uzilgan qurilmalar muzlatish tasdig'i (Freeze ACK) kutilmoqda!
                            </div>
                            <p>Ushbu tovarlar bo'yicha qurilmalarga rezerv ajratilgan. Inventarizatsiyani tasdiqlash uchun qurilmalar aloqaga chiqishi yoki freeze tasdig'i olinishi shart.</p>
                            @if(!empty($devicesPendingFreeze))
                                <div class="flex flex-wrap gap-2 mt-2">
                                    @foreach($devicesPendingFreeze as $dev)
                                        <button wire:click="acknowledgeDeviceFreezeAction({{ $dev->id }})" class="px-2.5 py-1 bg-white border border-amber-300 rounded text-amber-800 hover:bg-amber-100 flex items-center gap-1 font-mono">
                                            <span>#{{ $dev->device_code }} ({{ $dev->name }})</span>
                                            <span class="text-emerald-600 font-bold ml-1">✓ ACK</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Sanash jadvali -->
                    <div class="overflow-x-auto border rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-700 border-b">
                                <tr>
                                    <th class="p-3">Tovar Nomi & Hajmi</th>
                                    <th class="p-3 text-right">Kutilayotgan Qoldiq</th>
                                    <th class="p-3 text-right w-36">Sanalgan Qoldiq</th>
                                    <th class="p-3 text-right">Farq (Dona)</th>
                                    <th class="p-3 text-right">WAC Tannarx</th>
                                    <th class="p-3 text-right">Farq Qiymati</th>
                                    <th class="p-3">Sabab / Izoh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($selectedAudit->items as $aItem)
                                    @php
                                        $exp = (int) $aItem->expected_quantity;
                                        $cnt = (int) ($auditCountInputs[$aItem->product_variant_id] ?? $exp);
                                        $diff = $cnt - $exp;
                                        $diffVal = $diff * (int) $aItem->unit_cost;
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 {{ $diff !== 0 ? 'bg-amber-50/30' : '' }}">
                                        <td class="p-3 font-medium text-slate-900">
                                            {{ $aItem->variant->product->name ?? 'Mahsulot' }}
                                            <span class="text-slate-500 font-normal">({{ $aItem->variant->volume->name ?? '' }})</span>
                                        </td>
                                        <td class="p-3 text-right font-mono font-semibold text-slate-700">{{ number_format($exp) }}</td>
                                        <td class="p-3 text-right">
                                            @if($selectedAudit->status !== 'COMPLETED')
                                                <input
                                                    type="number"
                                                    wire:model="auditCountInputs.{{ $aItem->product_variant_id }}"
                                                    class="w-24 px-2 py-1 text-right font-mono font-bold border rounded-lg focus:ring-purple-500 focus:border-purple-500"
                                                >
                                            @else
                                                <span class="font-mono font-bold text-slate-800">{{ number_format($cnt) }}</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-right font-mono font-bold {{ $diff > 0 ? 'text-emerald-600' : ($diff < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                                            {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                                        </td>
                                        <td class="p-3 text-right font-mono text-slate-600">{{ number_format($aItem->unit_cost) }}</td>
                                        <td class="p-3 text-right font-mono font-bold {{ $diffVal > 0 ? 'text-emerald-600' : ($diffVal < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                                            {{ number_format($diffVal) }} so'm
                                        </td>
                                        <td class="p-3">
                                            @if($selectedAudit->status !== 'COMPLETED')
                                                <input
                                                    type="text"
                                                    wire:model="auditItemReasons.{{ $aItem->product_variant_id }}"
                                                    placeholder="Izoh..."
                                                    class="w-full px-2 py-1 text-xs border rounded-lg focus:ring-purple-500 focus:border-purple-500"
                                                >
                                            @else
                                                <span class="text-slate-500">{{ $aItem->reason ?: '-' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <!-- INVENTARIZATSIYALAR RO'YXATI -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                                Inventarizatsiya & Sanash Tarixi
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Ombor qoldiqlarini haqiqiy sanash va farqlarni muvofiqlashtirish</p>
                        </div>
                        <button wire:click="prepareNewAudit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-2">
                            + Yangi Sanash Boshlash
                        </button>
                    </div>

                    <div class="overflow-x-auto border rounded-lg">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b">
                                <tr>
                                    <th class="p-3">Audit #</th>
                                    <th class="p-3">Holati</th>
                                    <th class="p-3">Qurilmalar Freeze</th>
                                    <th class="p-3 text-right">Kutilgan Dona</th>
                                    <th class="p-3 text-right">Sanalgan Dona</th>
                                    <th class="p-3 text-right">Farq (Dona)</th>
                                    <th class="p-3 text-right">Farq Qiymati</th>
                                    <th class="p-3">Boshlangan Sana</th>
                                    <th class="p-3 text-right">Amal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($inventoryAudits as $ia)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="p-3 font-mono font-semibold text-purple-700">{{ $ia->audit_number }}</td>
                                        <td class="p-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $ia->status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800' }}">
                                                {{ $ia->status }}
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $ia->device_freeze_status === 'ACKNOWLEDGED' || $ia->device_freeze_status === 'NOT_REQUIRED' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800' }}">
                                                {{ $ia->device_freeze_status }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right font-mono text-slate-600">{{ number_format($ia->total_expected_qty) }}</td>
                                        <td class="p-3 text-right font-mono font-semibold text-slate-800">{{ number_format($ia->total_counted_qty) }}</td>
                                        <td class="p-3 text-right font-mono font-bold {{ $ia->total_discrepancy_qty > 0 ? 'text-emerald-600' : ($ia->total_discrepancy_qty < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                                            {{ $ia->total_discrepancy_qty > 0 ? '+' : '' }}{{ number_format($ia->total_discrepancy_qty) }}
                                        </td>
                                        <td class="p-3 text-right font-mono font-bold {{ $ia->total_discrepancy_value > 0 ? 'text-emerald-600' : ($ia->total_discrepancy_value < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                                            {{ number_format($ia->total_discrepancy_value) }} so'm
                                        </td>
                                        <td class="p-3 text-slate-500 text-[11px]">{{ $ia->started_at ? $ia->started_at->format('Y-m-d H:i') : '-' }}</td>
                                        <td class="p-3 text-right">
                                            <button wire:click="viewAudit({{ $ia->id }})" class="px-2.5 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded font-semibold text-xs">
                                                Ochish
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="p-8 text-center text-slate-400">Hozircha inventarizatsiya o'tkazilmagan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- 1. SOTUV QAYTARISH MODALI -->
    @if($showSaleReturnModal && $selectedSaleForReturn)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-2xl w-full flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b bg-amber-50/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Sotuvni Qaytarish (#{{ $selectedSaleForReturn->invoice_number }})</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Mijoz: {{ $selectedSaleForReturn->customer->name ?? 'Mehmon' }} | To'langan: {{ number_format($selectedSaleForReturn->paid_amount) }} so'm</p>
                    </div>
                    <button wire:click="closeSaleReturnModal" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Qaytariladigan tovarlar:</label>
                        @foreach($selectedSaleForReturn->items as $item)
                            @php
                                $alreadyReturned = (int) \App\Models\SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity');
                                $available = (int) $item->quantity - $alreadyReturned;
                            @endphp
                            <div class="p-3 bg-slate-50 rounded-xl border flex items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="font-bold text-slate-800">{{ $item->variant->product->name ?? 'Mahsulot' }} ({{ $item->variant->volume->name ?? '' }})</div>
                                    <div class="text-slate-500 text-[11px]">Sotilgan: {{ (int)$item->quantity }} dona | Qaytarishga mavjud: {{ $available }} dona | Narx: {{ number_format($item->sale_price) }} so'm</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-1.5 text-rose-600 text-[11px] font-semibold cursor-pointer">
                                        <input type="checkbox" wire:model="saleReturnDamaged.{{ $item->id }}" class="rounded text-rose-600">
                                        Brak / Yaroqsiz
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="{{ $available }}"
                                        wire:model="saleReturnQuantities.{{ $item->id }}"
                                        class="w-20 px-2 py-1 text-right font-mono font-bold border rounded-lg focus:ring-amber-500"
                                    >
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Qaytarish Sababi:</label>
                            <input type="text" wire:model="saleReturnReason" class="w-full px-3 py-2 text-xs border rounded-lg focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Kassadan qaytariladigan naqd pul (so'm):</label>
                            <input type="number" min="0" max="{{ $selectedSaleForReturn->paid_amount }}" wire:model="saleReturnRefundAmount" class="w-full px-3 py-2 text-xs border rounded-lg font-mono font-bold focus:ring-amber-500">
                            <p class="text-[11px] text-slate-500 mt-1">Qolgan summa mijoz qarzidan chegiriladi.</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-slate-50 border-t flex justify-end gap-2">
                    <button wire:click="closeSaleReturnModal" class="px-4 py-2 border rounded-xl text-xs font-semibold hover:bg-slate-100">Bekor qilish</button>
                    <button wire:click="submitSaleReturn" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold shadow-xs">Qaytarishni Tasdiqlash</button>
                </div>
            </div>
        </div>
    @endif

    <!-- 2. TA'MINOTCHIGA QAYTARISH MODALI -->
    @if($showSupplierReturnModal && $selectedPurchaseForReturn)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-2xl w-full flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b bg-blue-50/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Ta'minotchiga Qaytarish (#{{ $selectedPurchaseForReturn->invoice_number }})</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Ta'minotchi: {{ $selectedPurchaseForReturn->supplier->name ?? '-' }}</p>
                    </div>
                    <button wire:click="closeSupplierReturnModal" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Qaytariladigan tovarlar:</label>
                        @foreach($selectedPurchaseForReturn->items as $item)
                            @php
                                $alreadyReturned = (int) \App\Models\PurchaseReturnItem::where('purchase_item_id', $item->id)->sum('quantity');
                                $available = (int) $item->quantity - $alreadyReturned;
                            @endphp
                            <div class="p-3 bg-slate-50 rounded-xl border flex items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="font-bold text-slate-800">{{ $item->variant->product->name ?? 'Mahsulot' }} ({{ $item->variant->volume->name ?? '' }})</div>
                                    <div class="text-slate-500 text-[11px]">Kirim: {{ (int)$item->quantity }} dona | Qaytarishga mavjud: {{ $available }} dona | Xarid narxi: {{ number_format($item->unit_cost) }} so'm</div>
                                </div>
                                <input
                                    type="number"
                                    min="0"
                                    max="{{ $available }}"
                                    wire:model="supplierReturnQuantities.{{ $item->id }}"
                                    class="w-24 px-2 py-1 text-right font-mono font-bold border rounded-lg focus:ring-blue-500"
                                >
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Qaytarish Sababi:</label>
                        <input type="text" wire:model="supplierReturnReason" class="w-full px-3 py-2 text-xs border rounded-lg focus:ring-blue-500">
                    </div>
                </div>
                <div class="p-4 bg-slate-50 border-t flex justify-end gap-2">
                    <button wire:click="closeSupplierReturnModal" class="px-4 py-2 border rounded-xl text-xs font-semibold hover:bg-slate-100">Bekor qilish</button>
                    <button wire:click="submitSupplierReturn" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs">Ta'minotchiga Qaytarish</button>
                </div>
            </div>
        </div>
    @endif

    <!-- 3. BRAKKA CHIQARISH MODALI -->
    @if($showDamageModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b bg-rose-50/60 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-rose-950">Brak / Yaroqsiz Chiqimi</h3>
                    <button wire:click="closeDamageModal" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>
                <div class="p-5 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tovar Varianti:</label>
                        <select wire:model="damageVariantId" class="w-full px-3 py-2 border rounded-lg focus:ring-rose-500">
                            <option value="">-- Tanlang --</option>
                            @foreach(\App\Models\ProductVariant::with(['product', 'volume'])->where('status', 'active')->get() as $pv)
                                <option value="{{ $pv->id }}">{{ $pv->product->name ?? '' }} ({{ $pv->volume->name ?? '' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Brak Soni (Dona):</label>
                        <input type="number" min="1" wire:model="damageQuantity" class="w-full px-3 py-2 border rounded-lg font-mono font-bold focus:ring-rose-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Sababi:</label>
                        <input type="text" wire:model="damageReason" class="w-full px-3 py-2 border rounded-lg focus:ring-rose-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Qo'shimcha izoh:</label>
                        <textarea wire:model="damageNotes" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-rose-500"></textarea>
                    </div>
                </div>
                <div class="p-4 bg-slate-50 border-t flex justify-end gap-2">
                    <button wire:click="closeDamageModal" class="px-4 py-2 border rounded-xl text-xs font-semibold hover:bg-slate-100">Bekor qilish</button>
                    <button wire:click="submitDamageDisposal" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold shadow-xs">Brakka Chiqarish</button>
                </div>
            </div>
        </div>
    @endif

    <!-- VARIANT TAFSILOTI VA HARAKATLAR MODALI -->
    @if($showDetailModal && $variantDetail)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-slate-900">{{ $variantDetail['product_name'] }}</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 font-mono">{{ $variantDetail['volume_name'] }}</span>
                            <span class="text-xs font-mono text-slate-500">({{ $variantDetail['sku'] }})</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Tovar kartasi, qurilmalar ajratmasi va manba hujjatli harakatlar daftari</p>
                    </div>
                    <button wire:click="closeVariantDetail" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto space-y-6">
                    <!-- Qoldiqlar kartalari -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                            <div class="text-xs text-slate-500">Jismoniy Qoldiq</div>
                            <div class="text-xl font-black text-slate-900 mt-1">{{ number_format($variantDetail['physical_quantity']) }} <span class="text-xs font-normal">dona</span></div>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-200">
                            <div class="text-xs text-emerald-700 font-medium">Erkin Qoldiq</div>
                            <div class="text-xl font-black text-emerald-800 mt-1">{{ number_format($variantDetail['free_quantity']) }} <span class="text-xs font-normal">dona</span></div>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-3 border border-amber-200">
                            <div class="text-xs text-amber-700 font-medium">Qurilmalarga Ajratilgan</div>
                            <div class="text-xl font-black text-amber-800 mt-1">{{ number_format($variantDetail['allocated_quantity']) }} <span class="text-xs font-normal">dona</span></div>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                            <div class="text-xs text-slate-500">Minimal Chegara</div>
                            <div class="text-xl font-black text-slate-900 mt-1">{{ $variantDetail['minimum_stock'] > 0 ? number_format($variantDetail['minimum_stock']) : '-' }}</div>
                        </div>
                    </div>

                    <!-- Narx va qiymat (Agar ruxsat bo'lsa) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50/70 p-3 rounded-xl border border-slate-200 text-xs">
                        <div>
                            <span class="text-slate-500 block">Tizim Sotuv Narxi:</span>
                            <strong class="text-sm font-bold text-slate-900 font-mono">{{ $variantDetail['has_price'] ? number_format($variantDetail['default_sale_price']) . ' so\'m' : 'Belgilanmagan' }}</strong>
                        </div>
                        @if($this->canViewCost)
                            <div>
                                <span class="text-slate-500 block">WAC O'rtacha Tannarx:</span>
                                <strong class="text-sm font-bold text-slate-900 font-mono">{{ $variantDetail['wac_cost'] ? number_format($variantDetail['wac_cost']) . ' so\'m' : '-' }}</strong>
                            </div>
                            <div>
                                <span class="text-slate-500 block">Jami Ombor Qiymati:</span>
                                <strong class="text-sm font-bold text-indigo-700 font-mono">{{ $variantDetail['total_cost_value'] ? number_format($variantDetail['total_cost_value']) . ' so\'m' : '-' }}</strong>
                            </div>
                        @endif
                        <div>
                            <span class="text-slate-500 block">Oxirgi Kirim / Sotuv:</span>
                            <div class="text-[11px] text-slate-700 font-mono">
                                <div>K: {{ $variantDetail['last_inward_at'] ?? 'Mavjud emas' }}</div>
                                <div>S: {{ $variantDetail['last_sale_at'] ?? 'Mavjud emas' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Qurilmalarga ajratilgan zaxira ro'yxati -->
                    @if(!empty($variantDetail['allocations']))
                        <div>
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                <span>Qurilmalardagi Zaxira Ajratmalari</span>
                                @if($variantDetail['has_stale_allocation'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800">Eskirgan ajratma mavjud!</span>
                                @endif
                            </h4>
                            <div class="overflow-x-auto border border-slate-200 rounded-lg">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                                        <tr>
                                            <th class="p-2.5">Qurilma</th>
                                            <th class="p-2.5 text-right">Ajratilgan</th>
                                            <th class="p-2.5 text-right">Sotilgan</th>
                                            <th class="p-2.5 text-right">Qaytarilgan</th>
                                            <th class="p-2.5 text-right font-bold text-slate-900">Qolgan Zaxira</th>
                                            <th class="p-2.5">Oxirgi Aloqa / Lease</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($variantDetail['allocations'] as $a)
                                            <tr class="{{ $a['is_stale'] ? 'bg-purple-50/50' : '' }}">
                                                <td class="p-2.5">
                                                    <span class="font-bold text-slate-800">{{ $a['device_code'] }}</span>
                                                    <span class="text-slate-500 text-[11px]">({{ $a['device_name'] }})</span>
                                                </td>
                                                <td class="p-2.5 text-right font-mono">{{ number_format($a['allocated_quantity']) }}</td>
                                                <td class="p-2.5 text-right font-mono text-slate-500">{{ number_format($a['consumed_quantity']) }}</td>
                                                <td class="p-2.5 text-right font-mono text-slate-500">{{ number_format($a['returned_quantity']) }}</td>
                                                <td class="p-2.5 text-right font-mono font-bold text-amber-600">{{ number_format($a['remaining_quantity']) }}</td>
                                                <td class="p-2.5 text-[11px] font-mono text-slate-500">
                                                    <div>Sync: {{ $a['last_sync_at'] ?? 'Hech qachon' }}</div>
                                                    <div>Lease: {{ $a['lease_expires_at'] ?? 'Mavjud emas' }}</div>
                                                    @if($a['is_stale'])
                                                        <span class="text-[10px] font-bold text-purple-700">⚠ Stale Snapshot</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Harakatlar tarixi -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Manba Hujjatga Bog'langan Harakat Tarixi (Asia/Tashkent)</h4>
                        <div class="overflow-x-auto border border-slate-200 rounded-lg">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th class="p-2.5">Sana-Vaqt</th>
                                        <th class="p-2.5">Harakat Turi</th>
                                        <th class="p-2.5 text-right">O'zgarish</th>
                                        <th class="p-2.5 text-right">Qoldiq</th>
                                        @if($this->canViewCost)
                                            <th class="p-2.5 text-right">Tannarx</th>
                                            <th class="p-2.5 text-right">Qoldiq Qiymati</th>
                                        @endif
                                        <th class="p-2.5">Manba Hujjat</th>
                                        <th class="p-2.5">Mas'ul</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @if($movements && $movements->count() > 0)
                                        @foreach($movements as $m)
                                            <tr class="hover:bg-slate-50/70">
                                                <td class="p-2.5 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                                    {{ $m['created_at'] }}
                                                </td>
                                                <td class="p-2.5">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $m['is_positive'] ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                        {{ $m['type_label'] }}
                                                    </span>
                                                </td>
                                                <td class="p-2.5 text-right font-mono font-bold {{ $m['is_positive'] ? 'text-emerald-600' : 'text-rose-600' }}">
                                                    {{ $m['is_positive'] ? '+' : '-' }}{{ number_format(abs($m['quantity'])) }}
                                                </td>
                                                <td class="p-2.5 text-right font-mono font-semibold text-slate-800">
                                                    {{ number_format($m['balance_after_quantity']) }}
                                                </td>
                                                @if($this->canViewCost)
                                                    <td class="p-2.5 text-right font-mono text-slate-600">
                                                        {{ $m['unit_cost'] ? number_format($m['unit_cost']) : '-' }}
                                                    </td>
                                                    <td class="p-2.5 text-right font-mono text-indigo-700">
                                                        {{ $m['balance_after_value'] ? number_format($m['balance_after_value']) : '-' }}
                                                    </td>
                                                @endif
                                                <td class="p-2.5 text-slate-700 font-medium">
                                                    {{ $m['reference_title'] }}
                                                </td>
                                                <td class="p-2.5 text-slate-500 text-[11px]">
                                                    {{ $m['creator_name'] }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="7" class="p-6 text-center text-slate-400">
                                                Ushbu variant uchun hech qanday harakat qayd etilmagan.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Harakatlar paginationi -->
                        @if($movements && $movements->hasPages())
                            <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                                <span>Sahifa: {{ $movements->currentPage() }} / {{ $movements->lastPage() }}</span>
                                <div class="space-x-1">
                                    <button
                                        wire:click="setMovementsPage({{ $movements->currentPage() - 1 }})"
                                        @disabled($movements->onFirstPage())
                                        class="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-slate-100">
                                        Oldingi
                                    </button>
                                    <button
                                        wire:click="setMovementsPage({{ $movements->currentPage() + 1 }})"
                                        @disabled(!$movements->hasMorePages())
                                        class="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-slate-100">
                                        Keyingi
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                    <button wire:click="closeVariantDetail" class="px-5 py-2 bg-slate-800 text-white rounded-xl text-sm font-semibold hover:bg-slate-900 transition-colors">
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
