<?php

use Livewire\Component;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductPackage;
use App\Models\Customer;
use App\Services\Sales\CreateSaleService;

new class extends Component
{
    public ?int $customer_id = null;
    public string $payment_type = 'CASH'; // CASH, CARD, BANK, DEBT
    public string $search = '';

    // Savat qatorlari (Keyboard-first optom jadval)
    public array $cart = []; // [ variant_id => [ variant_id, product_name, volume_name, package_name, units_per_package, package_qty, total_units, stock, default_price, sale_price, is_system_price, line_total ] ]

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount()
    {
        $firstCustomer = Customer::first();
        if ($firstCustomer) {
            $this->customer_id = $firstCustomer->id;
        }
    }

    public function addToCart(int $variantId)
    {
        $variant = ProductVariant::with(['product', 'volume', 'packages', 'balance'])->find($variantId);
        if (!$variant) return;

        $stock = $variant->balance ? (float)$variant->balance->quantity : 0.0;
        if ($stock <= 0) {
            $this->errorMessage = "Omborda '{$variant->product->name} ({$variant->volume->name})' qolmagan!";
            return;
        }

        $yashik = $variant->packages->firstWhere('name', 'yashik');
        $unitsPerYashik = $yashik ? $yashik->units_per_package : 12;

        if (isset($this->cart[$variantId])) {
            $this->cart[$variantId]['package_qty'] += 5; // Optom bo'lgani uchun 5 yashikdan qo'shiladi
            $this->recalcItem($variantId);
        } else {
            $defaultPrice = $variant->default_sale_price ?: 7000;
            $this->cart[$variantId] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'volume_name' => $variant->volume->name ?? 'N/A',
                'package_name' => 'yashik',
                'units_per_package' => $unitsPerYashik,
                'package_qty' => 5,
                'total_units' => 5 * $unitsPerYashik,
                'stock' => $stock,
                'default_price' => $defaultPrice,
                'sale_price' => $defaultPrice,
                'is_system_price' => true,
                'line_total' => (5 * $unitsPerYashik) * $defaultPrice,
            ];
        }
        $this->errorMessage = null;
    }

    public function recalcItem(int $variantId)
    {
        if (!isset($this->cart[$variantId])) return;

        $item = &$this->cart[$variantId];
        $item['total_units'] = $item['package_qty'] * $item['units_per_package'];
        $item['line_total'] = $item['total_units'] * $item['sale_price'];
    }

    public function updatePackageType(int $variantId, string $pkgName)
    {
        if (!isset($this->cart[$variantId])) return;

        $units = ($pkgName === 'yashik') ? 12 : (($pkgName === 'blok') ? 6 : 1);
        $this->cart[$variantId]['package_name'] = $pkgName;
        $this->cart[$variantId]['units_per_package'] = $units;
        $this->recalcItem($variantId);
    }

    public function updatePackageQty(int $variantId, $qty)
    {
        if (!isset($this->cart[$variantId])) return;
        $qty = max(1, (int)$qty);
        $this->cart[$variantId]['package_qty'] = $qty;
        $this->recalcItem($variantId);
    }

    public function toggleSystemPrice(int $variantId, bool $checked)
    {
        if (!isset($this->cart[$variantId])) return;

        $this->cart[$variantId]['is_system_price'] = $checked;
        if ($checked) {
            $this->cart[$variantId]['sale_price'] = $this->cart[$variantId]['default_price'];
        }
        $this->recalcItem($variantId);
    }

    public function updateManualPrice(int $variantId, $price)
    {
        if (!isset($this->cart[$variantId])) return;
        $this->cart[$variantId]['sale_price'] = max(0, (int)$price);
        $this->recalcItem($variantId);
    }

    public function removeItem(int $variantId)
    {
        unset($this->cart[$variantId]);
    }

    public function clearCart()
    {
        $this->cart = [];
    }

    public function getTotalAmountProperty(): int
    {
        return array_sum(array_column($this->cart, 'line_total'));
    }

    public function checkout(CreateSaleService $saleService)
    {
        if (empty($this->cart)) return;

        $this->errorMessage = null;

        $items = [];
        foreach ($this->cart as $item) {
            $items[] = [
                'variant_id' => $item['variant_id'],
                'package_id' => null,
                'package_quantity' => $item['package_qty'],
                'quantity' => $item['total_units'],
                'sale_price' => $item['sale_price'],
                'is_system_price' => $item['is_system_price'],
            ];
        }

        try {
            $sale = $saleService->execute(
                customerId: $this->customer_id,
                items: $items,
                paymentType: $this->payment_type,
                source: 'Web (Livewire)'
            );

            $formattedTotal = number_format($sale->total_amount, 0, '.', ' ');
            $formattedProfit = number_format($sale->gross_profit, 0, '.', ' ');

            $this->successMessage = "✓ Savdo yakunlandi! Chek: {$sale->invoice_number} | Jami: {$formattedTotal} so'm (Haqiqiy Yalpi Foyda: +{$formattedProfit} so'm). Telegram kanalga xabar yuborildi!";
            $this->cart = [];
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function with()
    {
        $query = ProductVariant::with(['product', 'volume', 'balance'])
            ->where('status', 'active');

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })->orWhere('sku', 'like', '%' . $this->search . '%');
        }

        return [
            'variants' => $query->get(),
            'customers' => Customer::all(),
        ];
    }
};
?>

<div class="space-y-5">
    <!-- Yuqori boshqaruv paneli -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-black text-white flex items-center gap-2">
                🛒 Optom Savdo (Keyboard-First POS)
            </h2>
            <p class="text-xs text-slate-400">Jadval ko'rinishidagi tezkor optom kassa, yashik/blok hisobi va tizim narxi</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Xaridor tanlash -->
            <div>
                <select wire:model="customer_id" class="bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none">
                    <option value="">Noma'lum xaridor</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} (Qarz: {{ number_format($c->current_debt, 0, '.', ' ') }})</option>
                    @endforeach
                </select>
            </div>

            <!-- To'lov turi -->
            <div class="flex gap-1 bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-semibold">
                <button type="button" wire:click="$set('payment_type', 'CASH')"
                    class="px-3 py-1.5 rounded-lg {{ $payment_type === 'CASH' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400' }}">
                    Naqd
                </button>
                <button type="button" wire:click="$set('payment_type', 'CARD')"
                    class="px-3 py-1.5 rounded-lg {{ $payment_type === 'CARD' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400' }}">
                    Karta
                </button>
                <button type="button" wire:click="$set('payment_type', 'DEBT')"
                    class="px-3 py-1.5 rounded-lg {{ $payment_type === 'DEBT' ? 'bg-amber-600 text-white font-bold' : 'text-slate-400' }}">
                    Nasiya (Qarz)
                </button>
            </div>
        </div>
    </div>

    @if ($successMessage)
        <div class="p-4 bg-emerald-950/80 border border-emerald-800 text-emerald-300 rounded-2xl text-xs font-bold flex items-center justify-between">
            <span>{{ $successMessage }}</span>
            <button wire:click="$set('successMessage', null)" class="text-emerald-400 hover:text-white">✕</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 bg-red-950/80 border border-red-800 text-red-300 rounded-2xl text-xs font-bold flex items-center justify-between">
            <span>{{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-red-400 hover:text-white">✕</button>
        </div>
    @endif

    <!-- Tezkor Qidiruv va Tovar Qo'shish -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl space-y-4">
        <div class="flex items-center gap-3">
            <span class="text-slate-400">🔍</span>
            <input type="text" wire:model.live.debounce.200ms="search" placeholder="Mahsulot yoki SKU yozing: Fanta, Cola, FANTA-500..."
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none">
        </div>

        <!-- Tezkor tanlash paneli -->
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach($variants as $v)
                <button type="button" wire:click="addToCart({{ $v->id }})"
                    class="px-3 py-2 bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-cyan-500 rounded-xl text-left transition-all shrink-0 flex items-center gap-2">
                    <span class="text-xs font-bold text-white">{{ $v->product->name }}</span>
                    <span class="text-[10px] text-cyan-400 bg-cyan-950 px-1.5 py-0.5 rounded font-mono">{{ $v->volume->name }}</span>
                    <span class="text-[10px] text-slate-500 font-mono">({{ $v->balance ? (int)$v->balance->quantity : 0 }} dona)</span>
                </button>
            @endforeach
        </div>
    </div>

    <!-- Optom Savdo Jadvali (Keyboard-First POS Table) -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-sm font-bold text-white uppercase">📋 Savdo Ro'yxati (Line Items)</h3>
            @if(!empty($cart))
                <button wire:click="clearCart" class="text-xs text-red-400 hover:underline">Savatni tozalash</button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950 text-slate-400 font-bold uppercase border-b border-slate-800">
                    <tr>
                        <th class="p-3">Mahsulot</th>
                        <th class="p-3">Hajm</th>
                        <th class="p-3">Qadoq</th>
                        <th class="p-3 text-right">Omborda</th>
                        <th class="p-3 text-right">Miqdor (Qadoq)</th>
                        <th class="p-3 text-right">Jami Dona</th>
                        <th class="p-3 text-center">☑ Tizim narxi</th>
                        <th class="p-3 text-right">1 Dona Narxi</th>
                        <th class="p-3 text-right">Jami Summa</th>
                        <th class="p-3 text-center">O'chirish</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 bg-slate-900/60">
                    @forelse($cart as $vId => $item)
                        @php
                            $diff = $item['sale_price'] - $item['default_price'];
                        @endphp
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3 font-bold text-white">{{ $item['product_name'] }}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded bg-cyan-950 text-cyan-400 border border-cyan-800 font-mono">{{ $item['volume_name'] }}</span></td>
                            <td class="p-3">
                                <select wire:change="updatePackageType({{ $vId }}, $event.target.value)"
                                    class="bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white">
                                    <option value="dona" {{ $item['package_name'] === 'dona' ? 'selected' : '' }}>dona</option>
                                    <option value="blok" {{ $item['package_name'] === 'blok' ? 'selected' : '' }}>blok (6)</option>
                                    <option value="yashik" {{ $item['package_name'] === 'yashik' ? 'selected' : '' }}>yashik (12)</option>
                                </select>
                            </td>
                            <td class="p-3 text-right font-mono text-slate-400">{{ (int)$item['stock'] }} dona</td>
                            <td class="p-3 text-right">
                                <input type="number" min="1" value="{{ $item['package_qty'] }}"
                                    wire:change="updatePackageQty({{ $vId }}, $event.target.value)"
                                    class="w-20 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white text-right font-mono font-bold">
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-cyan-400">{{ $item['total_units'] }} dona</td>
                            <td class="p-3 text-center">
                                <input type="checkbox" {{ $item['is_system_price'] ? 'checked' : '' }}
                                    wire:click="toggleSystemPrice({{ $vId }}, {{ $item['is_system_price'] ? 'false' : 'true' }})"
                                    class="rounded border-slate-700 bg-slate-950 text-cyan-600 focus:ring-0">
                            </td>
                            <td class="p-3 text-right">
                                <input type="number" value="{{ $item['sale_price'] }}"
                                    {{ $item['is_system_price'] ? 'readonly' : '' }}
                                    wire:change="updateManualPrice({{ $vId }}, $event.target.value)"
                                    class="w-24 bg-slate-950 border {{ $item['is_system_price'] ? 'border-slate-800 text-slate-400' : 'border-cyan-500 text-cyan-300 font-bold' }} rounded-lg px-2 py-1 text-xs text-right font-mono">
                                @if(!$item['is_system_price'] && $diff != 0)
                                    <span class="text-[9px] block {{ $diff < 0 ? 'text-amber-400' : 'text-emerald-400' }}">
                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, '.', ' ') }} so'm
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-white text-sm">
                                {{ number_format($item['line_total'], 0, '.', ' ') }} so'm
                            </td>
                            <td class="p-3 text-center">
                                <button wire:click="removeItem({{ $vId }})" class="text-slate-500 hover:text-red-400 text-xs px-2">✕</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-500">Savat bo'sh. Qidiruv orqali tovar tanlang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Jami va Savdoni Yakunlash -->
        <div class="border-t border-slate-800 pt-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs text-slate-400 font-semibold">Jami Optom Savdo Summasi:</span>
                <div class="text-2xl font-black text-cyan-400 font-mono">
                    {{ number_format($this->totalAmount, 0, '.', ' ') }} so'm
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" wire:click="checkout" wire:loading.attr="disabled"
                    {{ empty($cart) ? 'disabled' : '' }}
                    class="px-8 py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-2xl shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-2">
                    <span wire:loading.remove>💳 Savdoni Yakunlash (Kanalga Xabar)</span>
                    <span wire:loading>Saqlanmoqda...</span>
                </button>
            </div>
        </div>
    </div>
</div>