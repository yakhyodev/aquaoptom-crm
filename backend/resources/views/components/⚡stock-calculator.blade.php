<?php

use Livewire\Component;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryCalculatorService;

new class extends Component
{
    public array $selectedVariantIds = [];
    public string $search = '';

    public function mount()
    {
        // Boshlang'ich holatda ombordagi barcha variantlar tanlanadi
        $this->selectedVariantIds = ProductVariant::pluck('id')->toArray();
    }

    public function selectAll(bool $select)
    {
        if ($select) {
            $this->selectedVariantIds = ProductVariant::pluck('id')->toArray();
        } else {
            $this->selectedVariantIds = [];
        }
    }

    public function toggleProductAll(int $productId, bool $select)
    {
        $vIds = ProductVariant::where('product_id', $productId)->pluck('id')->toArray();
        if ($select) {
            $this->selectedVariantIds = array_values(array_unique(array_merge($this->selectedVariantIds, $vIds)));
        } else {
            $this->selectedVariantIds = array_values(array_diff($this->selectedVariantIds, $vIds));
        }
    }

    public function toggleVariant(int $vId)
    {
        if (in_array($vId, $this->selectedVariantIds)) {
            $this->selectedVariantIds = array_values(array_diff($this->selectedVariantIds, [$vId]));
        } else {
            $this->selectedVariantIds[] = $vId;
        }
    }

    public function with(InventoryCalculatorService $calculator)
    {
        $productsQuery = Product::with(['variants.volume', 'variants.balance', 'variants.packages']);
        if (!empty(trim($this->search))) {
            $productsQuery->where('name', 'like', '%' . trim($this->search) . '%');
        }
        $products = $productsQuery->orderBy('name')->get();

        $calcResult = $calculator->calculate($this->selectedVariantIds);

        // Yashiklar jami hisobi
        $totalYashik = 0;
        foreach ($calcResult['items'] as $it) {
            $totalYashik += $it['stock_yashik'];
        }

        return [
            'products' => $products,
            'calc' => $calcResult,
            'totalYashik' => $totalYashik,
        ];
    }
};
?>

<div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs uppercase font-black text-purple-400 bg-purple-950/80 px-2.5 py-1 rounded-full border border-purple-900">
                    Interaktiv Tahlil • Master Prompt #53
                </span>
                <h2 class="text-xl font-bold text-white">Ombordagi Mahsulotlar Kalkulyatori</h2>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Litrlar bo'yicha checkboxlarni tanlab, real-vaqtda qancha sarmoya tikilgani (WAC), kutilayotgan sotuv summasi va kutilayotgan yalpi foydani hisoblang
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="Mahsulot qidirish..."
                    class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none w-48">
                @if($search)
                    <button wire:click="$set('search', '')" class="absolute right-2.5 top-1.5 text-slate-500 hover:text-white text-xs">✕</button>
                @endif
            </div>

            <button type="button" wire:click="selectAll(true)" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-200 transition-all">
                Hammasini tanlash
            </button>
            <button type="button" wire:click="selectAll(false)" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-400 hover:text-red-400 transition-all">
                Tozalash
            </button>
        </div>
    </div>

    <!-- Ogohlantirish: Agar sotuv narxi belgilanmagan variant bo'lsa -->
    @if ($calc['missing_price_count'] > 0)
        <div class="p-4 bg-amber-950/70 border border-amber-800/80 text-amber-200 rounded-2xl text-xs flex items-center gap-3 shadow-lg">
            <span class="text-xl">⚠️</span>
            <div>
                <span class="font-bold">Eslatma:</span> Tanlanganlar orasida <strong class="text-amber-100 font-mono">{{ $calc['missing_price_count'] }} ta</strong> variantning standart sotuv narxi belgilanmagan! Kutilayotgan potensial tushum to'liq hisoblanmasligi mumkin. 
                <a href="/catalog" class="underline text-amber-300 font-bold ml-1 hover:text-white">Katalogda narx belgilash &rarr;</a>
            </div>
        </div>
    @endif

    <!-- 4 ta Asosiy Moliyaviy Metrika Kartasi -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 bg-slate-950 p-5 rounded-2xl border border-slate-800/80 shadow-inner">
        <!-- Kirim Qiymati (WAC Investment) -->
        <div class="border-l-2 border-slate-700 pl-3.5 space-y-1">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Kirim Qiymati (Tannarx):</span>
                <span class="text-[10px] text-slate-500 font-mono">WAC</span>
            </div>
            <div class="text-xl font-black text-white tracking-tight">
                {{ number_format($calc['total_cost_value'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-slate-500">Omborga tikilgan jami sarmoya</p>
        </div>

        <!-- Kutilayotgan Sotuv Summasi -->
        <div class="border-l-2 border-cyan-500 pl-3.5 space-y-1">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Kutilayotgan Sotuv:</span>
                <span class="text-[10px] text-cyan-400 font-mono">Standart</span>
            </div>
            <div class="text-xl font-black text-cyan-400 tracking-tight">
                {{ number_format($calc['total_potential_sale_value'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-slate-500">Standart tizim narxidagi tushum</p>
        </div>

        <!-- Potensial Yalpi Foyda -->
        <div class="border-l-2 border-emerald-500 pl-3.5 space-y-1">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Kutilayotgan Yalpi Foyda:</span>
                <span class="text-[10px] bg-emerald-950 text-emerald-400 font-mono px-1.5 py-0.5 rounded border border-emerald-800">
                    {{ $calc['potential_margin_percent'] }}%
                </span>
            </div>
            <div class="text-xl font-black text-emerald-400 tracking-tight">
                +{{ number_format($calc['potential_gross_profit'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">so'm</span>
            </div>
            <p class="text-[11px] text-emerald-500/80 font-semibold">Potensial sof daromad</p>
        </div>

        <!-- Tanlangan Tovar Miqdori -->
        <div class="border-l-2 border-purple-500 pl-3.5 space-y-1">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Tanlangan Qoldiq:</span>
                <span class="text-[10px] text-purple-400 font-mono">{{ $calc['selected_count'] }} litr turi</span>
            </div>
            <div class="text-xl font-black text-purple-400 tracking-tight">
                {{ number_format($calc['total_quantity_units'], 0, '.', ' ') }} <span class="text-xs font-normal text-slate-400">dona</span>
            </div>
            <p class="text-[11px] text-slate-500">
                ~ <strong class="text-slate-300 font-mono">{{ number_format($totalYashik, 1, '.', ' ') }}</strong> yashik ekvivalenti
            </p>
        </div>
    </div>

    <!-- Mahsulotlar (Brand) va Litrlar Checkboxlari -->
    <div class="space-y-4">
        @forelse($products as $prod)
            @php
                $prodVIds = $prod->variants->pluck('id')->toArray();
                $allProdChecked = !empty($prodVIds) && empty(array_diff($prodVIds, $selectedVariantIds));
                
                // Ushbu brend bo'yicha tanlangan litrlar kutilayotgan sotuv summasi
                $brandCost = 0;
                $brandSale = 0;
                $brandUnits = 0;
                foreach($prod->variants as $v) {
                    if (in_array($v->id, $selectedVariantIds)) {
                        $stock = $v->balance ? (float)$v->balance->quantity : 0.0;
                        $wac = $v->balance ? (int)$v->balance->average_cost : 0;
                        $price = (int)$v->default_sale_price;
                        $brandCost += ($stock * $wac);
                        $brandSale += ($stock * $price);
                        $brandUnits += $stock;
                    }
                }
                $brandProfit = $brandSale - $brandCost;
            @endphp

            <div class="bg-slate-950 border border-slate-800/90 rounded-2xl p-4 space-y-3 hover:border-slate-700/80 transition-all">
                <!-- Brand darajasidagi sarlavha va toggle -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 pb-3">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" {{ $allProdChecked ? 'checked' : '' }}
                            wire:click="toggleProductAll({{ $prod->id }}, {{ $allProdChecked ? 'false' : 'true' }})"
                            class="w-4 h-4 rounded border-slate-700 bg-slate-900 text-cyan-600 focus:ring-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-white">🥤 {{ $prod->name }}</span>
                            <span class="text-[10px] text-slate-500 font-mono bg-slate-900 px-2 py-0.5 rounded border border-slate-800">{{ $prod->code }}</span>
                        </div>
                    </label>

                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <div class="text-slate-400">
                            Tanlangan qoldiq: <span class="text-purple-400 font-mono font-bold">{{ number_format($brandUnits, 0, '.', ' ') }} dona</span>
                        </div>
                        <div class="text-slate-400">
                            Kutilayotgan sotuv: <span class="text-cyan-400 font-mono font-bold">{{ number_format($brandSale, 0, '.', ' ') }} so'm</span>
                        </div>
                        <div class="text-slate-400">
                            Foyda: <span class="text-emerald-400 font-mono font-bold">+{{ number_format($brandProfit, 0, '.', ' ') }} so'm</span>
                        </div>
                    </div>
                </div>

                <!-- Litrlar / Variantlar darajasidagi grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($prod->variants as $v)
                        @php
                            $isChecked = in_array($v->id, $selectedVariantIds);
                            $vStock = $v->balance ? (float)$v->balance->quantity : 0.0;
                            $vWac = $v->balance ? (int)$v->balance->average_cost : 0;
                            $vSalePrice = (int)$v->default_sale_price;
                            $vCostTotal = (int) round($vStock * $vWac);
                            $vSaleTotal = (int) round($vStock * $vSalePrice);
                            $vProfit = $vSaleTotal - $vCostTotal;

                            // Yashik ekvivalenti
                            $yashik = $v->packages->firstWhere('name', 'yashik');
                            $unitsYashik = $yashik ? $yashik->units_per_package : 12;
                            $yashikQty = $unitsYashik > 0 ? round($vStock / $unitsYashik, 1) : 0;
                        @endphp

                        <label class="flex flex-col justify-between p-3 rounded-xl border {{ $isChecked ? 'border-cyan-500/80 bg-cyan-950/20' : 'border-slate-800/80 bg-slate-900/40 opacity-70' }} cursor-pointer hover:border-cyan-500/50 transition-all select-none">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <input type="checkbox" {{ $isChecked ? 'checked' : '' }}
                                        wire:click="toggleVariant({{ $v->id }})"
                                        class="w-4 h-4 rounded border-slate-700 bg-slate-900 text-cyan-600 focus:ring-0">
                                    <div>
                                        <div class="text-xs font-black text-white">
                                            {{ $v->volume->name ?? (string)($v->volume->value_ml / 1000) . ' L' }}
                                        </div>
                                        <span class="text-[10px] text-slate-500 font-mono">{{ $v->sku }}</span>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <span class="text-xs font-bold font-mono {{ $vStock > 0 ? 'text-purple-300' : 'text-slate-600' }}">
                                        {{ number_format($vStock, 0, '.', ' ') }} dona
                                    </span>
                                    <span class="block text-[10px] text-slate-500 font-mono">
                                        ({{ $yashikQty }} yashik)
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                                <div>
                                    <span class="text-slate-500 text-[10px] block">Tannarx (WAC):</span>
                                    <span class="font-mono text-slate-300">{{ number_format($vWac, 0, '.', ' ') }} so'm</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-slate-500 text-[10px] block">Sotuv narxi:</span>
                                    @if($vSalePrice > 0)
                                        <span class="font-mono font-bold text-cyan-400">{{ number_format($vSalePrice, 0, '.', ' ') }} so'm</span>
                                    @else
                                        <span class="font-mono text-amber-400 font-bold text-[10px]">Belgilanmagan!</span>
                                    @endif
                                </div>
                            </div>

                            @if($isChecked && $vStock > 0 && $vSalePrice > 0)
                                <div class="mt-2 pt-1 border-t border-slate-800/50 flex items-center justify-between text-[10px] text-slate-400">
                                    <span>Kutilayotgan tushum:</span>
                                    <span class="font-mono font-semibold text-emerald-400">+{{ number_format($vProfit, 0, '.', ' ') }} so'm</span>
                                </div>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-12 bg-slate-950 rounded-2xl border border-slate-800 text-slate-500 text-xs">
                Mahsulot topilmadi.
            </div>
        @endforelse
    </div>

    <!-- Tanlangan Pozitsiyalarning To'liq Jadvali -->
    @if (!empty($calc['items']))
        <div class="border-t border-slate-800 pt-6 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    📋 Tanlangan Pozitsiyalar Tahlil Jadvali
                    <span class="text-xs font-normal text-slate-400">({{ count($calc['items']) }} ta pozitsiya)</span>
                </h3>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-950 text-slate-400 font-bold uppercase border-b border-slate-800">
                        <tr>
                            <th class="p-3">#</th>
                            <th class="p-3">Mahsulot</th>
                            <th class="p-3">Hajm</th>
                            <th class="p-3 text-right">Qoldiq (Dona)</th>
                            <th class="p-3 text-right">Qoldiq (Yashik)</th>
                            <th class="p-3 text-right">Tannarx (WAC)</th>
                            <th class="p-3 text-right">Sotuv Narxi</th>
                            <th class="p-3 text-right">Sarmoya Qiymati</th>
                            <th class="p-3 text-right">Potensial Sotuv</th>
                            <th class="p-3 text-right text-emerald-400">Potensial Foyda</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 bg-slate-900/60">
                        @foreach($calc['items'] as $idx => $it)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="p-3 text-slate-500 font-mono">{{ $idx + 1 }}</td>
                                <td class="p-3 font-bold text-white">{{ $it['product_name'] }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded bg-cyan-950 text-cyan-400 border border-cyan-800 font-mono font-bold text-[11px]">
                                        {{ $it['volume_name'] }}
                                    </span>
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-slate-200">
                                    {{ number_format($it['stock_units'], 0, '.', ' ') }}
                                </td>
                                <td class="p-3 text-right font-mono text-purple-400">
                                    {{ number_format($it['stock_yashik'], 1, '.', ' ') }}
                                </td>
                                <td class="p-3 text-right font-mono text-slate-400">
                                    {{ number_format($it['wac_cost'], 0, '.', ' ') }} so'm
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-cyan-400">
                                    @if($it['has_price'])
                                        {{ number_format($it['sale_price'], 0, '.', ' ') }} so'm
                                    @else
                                        <span class="text-amber-400 font-normal">Belgilanmagan</span>
                                    @endif
                                </td>
                                <td class="p-3 text-right font-mono text-slate-300">
                                    {{ number_format($it['cost_value'], 0, '.', ' ') }} so'm
                                </td>
                                <td class="p-3 text-right font-mono text-cyan-300">
                                    {{ number_format($it['potential_sale'], 0, '.', ' ') }} so'm
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-emerald-400">
                                    +{{ number_format($it['potential_profit'], 0, '.', ' ') }} so'm
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>