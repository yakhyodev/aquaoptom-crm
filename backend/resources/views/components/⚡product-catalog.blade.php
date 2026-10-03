<?php

use Livewire\Component;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PriceHistory;
use Carbon\Carbon;

new class extends Component
{
    public ?int $editingVariantId = null;
    public ?int $editingPrice = null;
    public string $search = '';
    public ?int $historyVariantId = null;
    public ?string $successMessage = null;

    public function startEdit(int $vId, ?int $currentPrice)
    {
        $this->editingVariantId = $vId;
        $this->editingPrice = $currentPrice ?? 0;
    }

    public function cancelEdit()
    {
        $this->editingVariantId = null;
        $this->editingPrice = null;
    }

    public function savePrice(int $vId)
    {
        $variant = ProductVariant::find($vId);
        if ($variant) {
            $oldPrice = (int) $variant->default_sale_price;
            $newPrice = (int) $this->editingPrice;

            if ($oldPrice !== $newPrice) {
                $variant->default_sale_price = $newPrice;
                $variant->save();

                // Tarixiy narx o'zgarishini qayd etish (Master Architecture Item 9)
                PriceHistory::create([
                    'product_variant_id' => $variant->id,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => auth()->id() ?? 1,
                    'changed_at' => Carbon::now(),
                ]);

                $this->successMessage = "✓ {$variant->sku} uchun standart narx " . number_format($newPrice, 0, '.', ' ') . " so'mga yangilandi va tarixga yozildi!";
            }
        }

        $this->editingVariantId = null;
        $this->editingPrice = null;
    }

    public function viewHistory(int $vId)
    {
        if ($this->historyVariantId === $vId) {
            $this->historyVariantId = null;
        } else {
            $this->historyVariantId = $vId;
        }
    }

    public function with()
    {
        $query = ProductVariant::with(['product', 'volume', 'packages', 'balance', 'priceHistories'])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->select('product_variants.*')
            ->orderBy('products.name');

        if (!empty(trim($this->search))) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('products.name', 'like', $term)
                  ->orWhere('product_variants.sku', 'like', $term);
            });
        }

        $variants = $query->get();

        return [
            'variants' => $variants,
        ];
    }
};
?>

<div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs uppercase font-black text-cyan-400 bg-cyan-950/80 px-2.5 py-1 rounded-full border border-cyan-900">
                    Katalog & Narxlar • Master Prompt #9
                </span>
                <h2 class="text-xl font-bold text-white">Mahsulotlar Katalogi & Standart Tizim Narxlari</h2>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Har bir mahsulot va litr uchun standart ulgurji narxni (default wholesale price) boshqaring. Barcha narx o'zgarishlari avtomatik tarixga yoziladi.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative">
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="Mahsulot yoki SKU qidirish..."
                    class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none w-56">
                @if($search)
                    <button wire:click="$set('search', '')" class="absolute right-2.5 top-1.5 text-slate-500 hover:text-white text-xs">✕</button>
                @endif
            </div>

            <span class="text-xs text-cyan-400 bg-cyan-950 px-3 py-1.5 rounded-xl border border-cyan-800 font-mono font-bold">
                Jami: {{ count($variants) }} ta hajm
            </span>
        </div>
    </div>

    @if ($successMessage)
        <div class="p-3.5 bg-emerald-950/80 border border-emerald-800 text-emerald-300 rounded-xl text-xs font-bold flex items-center justify-between shadow-lg">
            <span>{{ $successMessage }}</span>
            <button wire:click="$set('successMessage', null)" class="text-emerald-400 hover:text-white font-bold ml-4">✕</button>
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-slate-800">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-950 text-slate-400 font-bold uppercase border-b border-slate-800">
                <tr>
                    <th class="p-3.5">#</th>
                    <th class="p-3.5">Mahsulot Nomi</th>
                    <th class="p-3.5">Hajmi</th>
                    <th class="p-3.5">SKU / Kod</th>
                    <th class="p-3.5 text-right">Qoldiq (Dona)</th>
                    <th class="p-3.5 text-right">Qoldiq (Yashik)</th>
                    <th class="p-3.5 text-right">Tannarx (WAC)</th>
                    <th class="p-3.5 text-right">Standart Tizim Narxi</th>
                    <th class="p-3.5 text-right">1 dona foydasi</th>
                    <th class="p-3.5 text-center">Tarix / Harakat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 bg-slate-900/60">
                @forelse($variants as $idx => $v)
                    @php
                        $stock = $v->balance ? (float)$v->balance->quantity : 0.0;
                        $wac = $v->balance ? (int)$v->balance->average_cost : 0;
                        $salePrice = (int)$v->default_sale_price;
                        $unitProfit = $salePrice > 0 ? ($salePrice - $wac) : 0;
                        $marginPercent = ($salePrice > 0) ? round(($unitProfit / $salePrice) * 100, 1) : 0;

                        // Yashik
                        $yashik = $v->packages->firstWhere('name', 'yashik');
                        $yashikUnits = $yashik ? $yashik->units_per_package : 12;
                        $yashikCount = $yashikUnits > 0 ? round($stock / $yashikUnits, 1) : 0;

                        $hasHistory = $v->priceHistories && $v->priceHistories->isNotEmpty();
                    @endphp
                    <tr class="hover:bg-slate-800/40 transition-colors {{ $editingVariantId === $v->id ? 'bg-cyan-950/20' : '' }}">
                        <td class="p-3.5 text-slate-500 font-mono">{{ $idx + 1 }}</td>
                        <td class="p-3.5">
                            <span class="font-bold text-white text-sm">🥤 {{ $v->product->name }}</span>
                            <span class="block text-[10px] text-slate-500 font-mono">{{ $v->product->code }}</span>
                        </td>
                        <td class="p-3.5">
                            <span class="px-2.5 py-1 rounded-lg bg-cyan-950 text-cyan-400 border border-cyan-800 font-mono font-bold text-xs">
                                {{ $v->volume->name ?? (string)($v->volume->value_ml / 1000) . ' L' }}
                            </span>
                        </td>
                        <td class="p-3.5 font-mono text-slate-400 text-[11px]">{{ $v->sku }}</td>
                        <td class="p-3.5 text-right font-mono font-bold {{ $stock <= 50 ? 'text-amber-400' : 'text-slate-200' }}">
                            {{ number_format($stock, 0, '.', ' ') }} dona
                        </td>
                        <td class="p-3.5 text-right font-mono text-purple-400 text-[11px]">
                            {{ number_format($yashikCount, 1, '.', ' ') }} yashik
                        </td>
                        <td class="p-3.5 text-right font-mono text-slate-400">
                            {{ number_format($wac, 0, '.', ' ') }} so'm
                        </td>
                        <td class="p-3.5 text-right font-mono font-bold">
                            @if($editingVariantId === $v->id)
                                <div class="flex items-center justify-end gap-1.5">
                                    <input type="number" wire:model="editingPrice"
                                        class="w-28 bg-slate-950 border border-cyan-500 rounded-lg px-2.5 py-1 text-xs text-white text-right focus:outline-none focus:ring-1 focus:ring-cyan-500">
                                    <button wire:click="savePrice({{ $v->id }})" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs font-bold" title="Saqlash">
                                        ✓
                                    </button>
                                    <button wire:click="cancelEdit" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-400 rounded text-xs" title="Bekor qilish">
                                        ✕
                                    </button>
                                </div>
                            @else
                                @if($salePrice > 0)
                                    <span class="text-cyan-400 text-sm">{{ number_format($salePrice, 0, '.', ' ') }} so'm</span>
                                @else
                                    <span class="text-amber-400 text-[11px] italic">Belgilanmagan</span>
                                @endif
                            @endif
                        </td>
                        <td class="p-3.5 text-right font-mono font-semibold">
                            @if($salePrice > 0)
                                <span class="text-emerald-400 block">+{{ number_format($unitProfit, 0, '.', ' ') }} so'm</span>
                                <span class="text-[10px] text-emerald-600">({{ $marginPercent }}% marja)</span>
                            @else
                                <span class="text-slate-600">-</span>
                            @endif
                        </td>
                        <td class="p-3.5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                @if($editingVariantId !== $v->id)
                                    <button wire:click="startEdit({{ $v->id }}, {{ $salePrice }})"
                                        class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-[11px] transition-all flex items-center gap-1">
                                        ✏️ O'zgartirish
                                    </button>
                                @endif

                                @if($hasHistory)
                                    <button wire:click="viewHistory({{ $v->id }})"
                                        class="px-2 py-1 bg-slate-800/80 hover:bg-slate-700 text-purple-300 rounded-lg text-[10px] transition-all"
                                        title="Narx o'zgarish tarixi">
                                        🕒 {{ count($v->priceHistories) }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- Narx O'zgarish Tarixi (Expandable Accordion) -->
                    @if($historyVariantId === $v->id)
                        <tr class="bg-slate-950/90 border-b border-purple-900/40">
                            <td colspan="10" class="p-4">
                                <div class="bg-slate-900 p-3 rounded-xl border border-slate-800 space-y-2">
                                    <div class="flex items-center justify-between text-xs border-b border-slate-800 pb-2">
                                        <span class="font-bold text-purple-300 flex items-center gap-1.5">
                                            🕒 {{ $v->product->name }} ({{ $v->volume->name ?? '' }}) narx o'zgarish tarixi:
                                        </span>
                                        <button wire:click="viewHistory({{ $v->id }})" class="text-slate-500 hover:text-white text-xs">Yopish ✕</button>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach($v->priceHistories->sortByDesc('changed_at') as $hist)
                                            <div class="flex items-center justify-between text-[11px] bg-slate-950 p-2 rounded-lg border border-slate-800/80 font-mono">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-slate-500">{{ $hist->changed_at ? $hist->changed_at->format('Y-m-d H:i') : '-' }}</span>
                                                    <span class="text-slate-400">Eski: <strong class="text-slate-300">{{ number_format($hist->old_price, 0, '.', ' ') }}</strong> so'm</span>
                                                    <span class="text-slate-600">&rarr;</span>
                                                    <span class="text-cyan-400">Yangi: <strong>{{ number_format($hist->new_price, 0, '.', ' ') }}</strong> so'm</span>
                                                </div>
                                                <span class="text-[10px] text-slate-500">ID: #{{ $hist->changed_by }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="10" class="p-8 text-center text-slate-500">Mahsulot topilmadi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>