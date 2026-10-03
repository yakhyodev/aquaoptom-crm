<?php

use Livewire\Component;
use App\Models\Product;
use App\Models\Volume;
use App\Models\ProductVariant;
use App\Models\ProductPackage;
use App\Models\Supplier;
use App\Services\Catalog\CreateProductService;
use App\Services\Catalog\CreateVariantService;
use App\Services\Purchase\ReceivePurchaseService;

new class extends Component
{
    public string $name = '';
    public int $volume_ml = 500;
    public string $custom_volume_name = '';
    public ?int $custom_volume_ml = null;
    public bool $is_creating_new_volume = false;

    public string $package_name = 'dona'; // dona, blok, yashik
    public int $package_qty = 150;
    public int $units_per_package = 1;
    public int $cost_price = 5000; // 1 dona tannarxi

    public ?int $supplier_id = null;
    public string $invoice_number = '';

    public array $suggestions = [];
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount()
    {
        $firstSupplier = Supplier::first();
        if ($firstSupplier) {
            $this->supplier_id = $firstSupplier->id;
        }
    }

    public function updatedName($val)
    {
        $val = trim($val);
        if (strlen($val) > 0) {
            $this->suggestions = Product::where('name', 'like', '%' . $val . '%')
                ->limit(5)
                ->pluck('name')
                ->toArray();
        } else {
            $this->suggestions = [];
        }
    }

    public function selectName(string $selected)
    {
        $this->name = $selected;
        $this->suggestions = [];
    }

    public function updatedPackageName($val)
    {
        if ($val === 'yashik') {
            $this->units_per_package = 12;
        } elseif ($val === 'blok') {
            $this->units_per_package = 6;
        } else {
            $this->units_per_package = 1;
        }
    }

    public function getCalculatedUnitsProperty(): int
    {
        return $this->package_qty * $this->units_per_package;
    }

    public function getTotalCostProperty(): int
    {
        return $this->calculatedUnits * $this->cost_price;
    }

    public function save(
        CreateProductService $productService,
        CreateVariantService $variantService,
        ReceivePurchaseService $purchaseService
    ) {
        $this->validate([
            'name' => 'required|string|min:2',
            'package_qty' => 'required|integer|min:1',
            'cost_price' => 'required|integer|min:1',
        ]);

        $this->errorMessage = null;

        try {
            // 1. Dynamic Product Creation (agar yo'q bo'lsa)
            $product = $productService->execute(
                name: $this->name,
                source: 'PURCHASE_FLOW'
            );

            // 2. Volume tanlash yoki yangisini yaratish
            if ($this->is_creating_new_volume && $this->custom_volume_ml && $this->custom_volume_name) {
                $volume = $variantService->findOrCreateVolume($this->custom_volume_name, $this->custom_volume_ml);
            } else {
                $volume = Volume::where('value_ml', $this->volume_ml)->first();
                if (!$volume) {
                    $volName = ($this->volume_ml / 1000) . ' L';
                    $volume = $variantService->findOrCreateVolume($volName, $this->volume_ml);
                }
            }

            // 3. Dynamic Variant Creation (agar hali ulanmagan bo'lsa)
            $variant = $variantService->execute(
                product: $product,
                volume: $volume,
                defaultSalePrice: (int) round($this->cost_price * 1.3),
                source: 'PURCHASE_FLOW'
            );

            // 4. Qadoq ma'lumotlari
            $pkg = ProductPackage::where('product_variant_id', $variant->id)
                ->where('name', $this->package_name)
                ->first();

            $totalUnits = $this->calculatedUnits;

            // 5. Atomic Purchase Posting
            $purchase = $purchaseService->execute(
                supplierId: $this->supplier_id,
                items: [
                    [
                        'variant_id' => $variant->id,
                        'package_id' => $pkg?->id,
                        'package_quantity' => $this->package_qty,
                        'quantity' => $totalUnits,
                        'unit_cost' => $this->cost_price,
                    ]
                ],
                invoiceNumber: $this->invoice_number ?: null,
                source: 'Web (Livewire)'
            );

            $this->successMessage = "✓ Yuk qabul qilindi: {$product->name} ({$volume->name}) — {$this->package_qty} {$this->package_name} ({$totalUnits} dona). Telegram kanalga xabar yuborildi!";
            $this->name = '';
            $this->package_qty = 150;
            $this->cost_price = 5000;
            $this->suggestions = [];
            $this->is_creating_new_volume = false;
        } catch (\Exception $e) {
            $this->errorMessage = "Xatolik yuz berdi: " . $e->getMessage();
        }
    }

    public function with()
    {
        return [
            'volumes' => Volume::where('status', 'active')->orderBy('value_ml')->get(),
            'suppliers' => Supplier::all(),
        ];
    }
};
?>

<div class="max-w-3xl mx-auto bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-6">
    <div class="border-b border-slate-800 pb-4">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-xs uppercase font-bold text-blue-400 bg-blue-950/80 px-2.5 py-1 rounded-full border border-blue-900">Goods Receiving</span>
                <h2 class="text-xl font-bold text-white mt-2">Optom Kirim (Yuk Qabul Qilish)</h2>
                <p class="text-xs text-slate-400">Master katalogdan tanlang yoki shu yerning o'zida yangi mahsulot/hajm yaratib qabul qiling</p>
            </div>
            <span class="text-xs font-mono text-cyan-400 bg-cyan-950 border border-cyan-800 px-3 py-1 rounded-xl">
                Source: DRAFT → POSTED
            </span>
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

    <form wire:submit="save" class="space-y-5">
        <!-- Ta'minotchi va Nakladnoy raqami -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Ta'minotchi (Zavod / Firma)</label>
                <select wire:model="supplier_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
                    <option value="">Ta'minotchisiz</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Hisobvaraq (Nakladnoy #)</label>
                <input type="text" wire:model="invoice_number" placeholder="Masalan: NAK-2026-081"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <!-- Mahsulot Nomi (Dynamic Search / Creation) -->
        <div class="relative">
            <label class="block text-xs font-bold text-slate-300 mb-1.5">
                1. Mahsulot Nomi <span class="text-red-400">*</span>
            </label>
            <input type="text" wire:model.live.debounce.250ms="name" placeholder="Masalan: Fanta, Coca-Cola, Chortoq..." required
                class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none">

            <!-- Autocomplete Popover -->
            @if(!empty($suggestions))
                <div class="absolute top-full left-0 right-0 mt-1 bg-slate-800 border border-slate-700 rounded-xl shadow-2xl p-2 z-20 space-y-1">
                    @foreach($suggestions as $sug)
                        <div wire:click="selectName('{{ $sug }}')" class="p-2 hover:bg-slate-700 rounded-lg cursor-pointer flex items-center justify-between text-xs text-white">
                            <span>🥤 {{ $sug }}</span>
                            <span class="text-[10px] text-slate-400">Mavjud katalog</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Idish Hajmi (Litri) -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-bold text-slate-300">2. Idish Hajmi</label>
                <button type="button" wire:click="$toggle('is_creating_new_volume')" class="text-xs text-cyan-400 hover:underline">
                    {{ $is_creating_new_volume ? 'Mavjud hajmlar' : '+ Yangi hajm (masalan 2.25L)' }}
                </button>
            </div>

            @if(!$is_creating_new_volume)
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                    @foreach($volumes as $v)
                        <button type="button" wire:click="$set('volume_ml', {{ $v->value_ml }})"
                            class="p-2.5 rounded-xl border text-xs font-bold transition-all {{ $volume_ml == $v->value_ml ? 'border-blue-500 bg-blue-950/80 text-blue-400 shadow-md shadow-blue-500/20' : 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700' }}">
                            {{ $v->name }}
                        </button>
                    @endforeach
                </div>
            @else
                <div class="p-3 bg-slate-950 border border-slate-800 rounded-2xl grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] text-slate-400 block mb-1">Ko'rinish nomi (masalan: 2.25 L):</label>
                        <input type="text" wire:model="custom_volume_name" placeholder="2.25 L"
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-400 block mb-1">Qiymat ml (masalan: 2250):</label>
                        <input type="number" wire:model="custom_volume_ml" placeholder="2250"
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                    </div>
                </div>
            @endif
        </div>

        <!-- Qadoqlash va Miqdor -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">3. Qadoq Turi</label>
                <select wire:model.live="package_name" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none">
                    <option value="dona">📦 Dona (1 dona)</option>
                    <option value="blok">📦 Blok (6 dona)</option>
                    <option value="yashik">📦 Yashik (12 dona)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">
                    4. Miqdor ({{ $package_name }}) <span class="text-red-400">*</span>
                </label>
                <input type="number" wire:model.live="package_qty" min="1" required
                    class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 rounded-xl px-4 py-2.5 text-sm text-white font-mono font-bold focus:outline-none">
                <span class="text-[11px] text-cyan-400 mt-1 block">
                    = {{ $this->calculatedUnits }} dona omborga
                </span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">
                    5. Kirim Narxi (1 dona) <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                    <input type="number" wire:model.live="cost_price" min="1" required
                        class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 rounded-xl pl-4 pr-12 py-2.5 text-sm text-white font-mono font-bold focus:outline-none">
                    <span class="absolute right-3 top-2.5 text-xs text-slate-500">so'm</span>
                </div>
            </div>
        </div>

        <!-- Jami kalkulyatsiya bloki -->
        <div class="bg-blue-950/40 border border-blue-800/60 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs text-blue-300 font-semibold">Ushbu partiyaning jami summasi:</span>
                <div class="text-2xl font-black text-white mt-0.5">
                    {{ number_format($this->totalCost, 0, '.', ' ') }} so'm
                </div>
            </div>
            <div class="text-right">
                <span class="text-[11px] text-slate-400">{{ $package_qty }} {{ $package_name }} × {{ $units_per_package }} dona = {{ $this->calculatedUnits }} dona</span>
                <div class="text-xs text-emerald-400 font-bold mt-0.5">✓ WAC tannarx avtomatik yangilanadi</div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end pt-2">
            <button type="submit" wire:loading.attr="disabled"
                class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 active:scale-[0.98] text-white font-bold rounded-xl shadow-lg shadow-blue-600/30 transition-all flex items-center justify-center gap-2">
                <span wire:loading.remove>📥 Kirimni Tasdiqlash (POST)</span>
                <span wire:loading>Qabul qilinmoqda...</span>
            </button>
        </div>
    </form>
</div>