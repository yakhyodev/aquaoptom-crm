<div class="space-y-6">
    <!-- Sarlavha va tablar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-tight">Boshlang‘ich qoldiqlar (Hisob ochilishi)</h1>
            <p class="text-xs text-slate-400 mt-1">Ombor tovarlari, kassa pullari hamda taraflar qarz va avanslarini idempotent ochilish hujjati orqali kiritish.</p>
        </div>

        <div class="flex items-center gap-2">
            @if ($activeTab === 'stock')
                <button wire:click="openStockModal" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-semibold shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Ombor qoldig‘i kiritish
                </button>
            @elseif ($activeTab === 'cash')
                <button wire:click="openCashModal" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-semibold shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Kassa qoldig‘i kiritish
                </button>
            @elseif ($activeTab === 'customers')
                <button wire:click="openCustomerModal" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-semibold shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Mijoz qarz/avansi kiritish
                </button>
            @elseif ($activeTab === 'suppliers')
                <button wire:click="openSupplierModal" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-semibold shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Ta’minotchi hisobi kiritish
                </button>
            @endif
        </div>
    </div>

    <!-- Alert xabarlar -->
    @if ($successMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-800 text-emerald-300 text-xs flex items-center justify-between">
            <span>{{ $successMessage }}</span>
            <button wire:click="$set('successMessage', null)" class="text-emerald-400 hover:text-emerald-200 text-sm font-bold">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-800 text-rose-300 text-xs flex items-center justify-between">
            <span>{{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-rose-200 text-sm font-bold">&times;</button>
        </div>
    @endif

    <!-- Tab tugmalari -->
    <div class="flex items-center gap-2 border-b border-slate-800 pb-3 overflow-x-auto text-xs">
        <button wire:click="switchTab('stock')" class="px-3.5 py-2 rounded-xl font-medium transition {{ $activeTab === 'stock' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            📦 Ombor tovarlari
        </button>
        <button wire:click="switchTab('cash')" class="px-3.5 py-2 rounded-xl font-medium transition {{ $activeTab === 'cash' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            💰 Kassa hisoblari
        </button>
        <button wire:click="switchTab('customers')" class="px-3.5 py-2 rounded-xl font-medium transition {{ $activeTab === 'customers' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            👥 Mijozlar qarzdorligi
        </button>
        <button wire:click="switchTab('suppliers')" class="px-3.5 py-2 rounded-xl font-medium transition {{ $activeTab === 'suppliers' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            🏭 Ta’minotchilar hisobi
        </button>
        <button wire:click="switchTab('history')" class="px-3.5 py-2 rounded-xl font-medium transition {{ $activeTab === 'history' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            📋 Ochilish hujjatlari tarixi
        </button>
    </div>

    <!-- 1. Ombor tovarlari TAB -->
    @if ($activeTab === 'stock')
        <x-card title="Mavjud mahsulot variantlari va ombor qoldig‘i">
            <x-slot:subtitle>Har bir variant uchun donabay qoldiq, jami qiymat va o‘rtacha tannarx (WAC).</x-slot:subtitle>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Mahsulot</th>
                            <th class="px-4 py-3">Hajm</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3 text-right">Qoldiq (Dona)</th>
                            <th class="px-4 py-3 text-right">WAC Tannarx</th>
                            <th class="px-4 py-3 text-right">Jami Qiymat</th>
                            <th class="px-4 py-3 text-right">Amal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($variants as $variant)
                            @php
                                $qty = $variant->balance ? (int)$variant->balance->quantity : 0;
                                $wac = $variant->balance ? (int)$variant->balance->average_cost : 0;
                                $val = $variant->balance ? (int)$variant->balance->total_value : 0;
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-medium text-white">{{ $variant->product->name }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-300">{{ $variant->volume->name }}</span></td>
                                <td class="px-4 py-3 font-mono text-slate-400">{{ $variant->sku }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $qty > 0 ? 'text-emerald-400' : 'text-slate-500' }}">{{ number_format($qty, 0, '', ' ') }} dona</td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ number_format($wac, 0, '', ' ') }} so‘m</td>
                                <td class="px-4 py-3 text-right font-semibold text-cyan-400">{{ number_format($val, 0, '', ' ') }} so‘m</td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="$set('stockVariantId', {{ $variant->id }}); openStockModal();" class="text-xs text-cyan-400 hover:text-cyan-300 font-medium">Qoldiq qo‘shish</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">Mahsulot variantlari mavjud emas. Avval katalogdan mahsulot qo‘shing.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <!-- 2. Kassa hisoblari TAB -->
    @if ($activeTab === 'cash')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @foreach ($cashAccounts as $account)
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase tracking-wider font-semibold px-2 py-0.5 rounded-md {{ $account->type === 'CASH' ? 'bg-amber-950 text-amber-400 border border-amber-800/50' : ($account->type === 'CARD' ? 'bg-cyan-950 text-cyan-400 border border-cyan-800/50' : 'bg-indigo-950 text-indigo-400 border border-indigo-800/50') }}">
                                {{ $account->type }}
                            </span>
                            @if ($account->is_default)
                                <span class="text-[10px] text-slate-500">Asosiy</span>
                            @endif
                        </div>
                        <h3 class="text-base font-bold text-white mt-3">{{ $account->name }}</h3>
                        <p class="text-xs text-slate-400 mt-1">Joriy pul balansi:</p>
                        <p class="text-2xl font-black text-white mt-2">{{ number_format($account->balance, 0, '', ' ') }} <span class="text-xs font-normal text-slate-400">so‘m</span></p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-800">
                        <button wire:click="openCashModal({{ $account->id }})" class="w-full py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition text-center">
                            Boshlang‘ich summa kiritish
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- 3. Mijozlar qarzdorligi TAB -->
    @if ($activeTab === 'customers')
        <x-card title="Mijozlar hisob-kitoblari (Signed Balance)">
            <x-slot:subtitle>Musbat qiymat = Mijoz qarzi (qizil). Manfiy qiymat = Mijoz avansi / oldindan to‘lovi (yashil).</x-slot:subtitle>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Mijoz</th>
                            <th class="px-4 py-3">Telefon</th>
                            <th class="px-4 py-3">Do‘kon / Manzil</th>
                            <th class="px-4 py-3 text-right">Signed Balans</th>
                            <th class="px-4 py-3 text-right">Holat</th>
                            <th class="px-4 py-3 text-right">Amal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($customers as $customer)
                            @php
                                $bal = (int)$customer->current_debt;
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-medium text-white">{{ $customer->name }}</td>
                                <td class="px-4 py-3 text-slate-400 font-mono">{{ $customer->phone ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $customer->store_name ?: $customer->address ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold {{ $bal > 0 ? 'text-rose-400' : ($bal < 0 ? 'text-emerald-400' : 'text-slate-500') }}">
                                    {{ $bal > 0 ? '+' : '' }}{{ number_format($bal, 0, '', ' ') }} so‘m
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($bal > 0)
                                        <span class="px-2 py-0.5 rounded-md bg-rose-950/80 text-rose-300 border border-rose-800/50 text-[10px] font-semibold">QARZ</span>
                                    @elseif ($bal < 0)
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-950/80 text-emerald-300 border border-emerald-800/50 text-[10px] font-semibold">AVANS</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-400 text-[10px]">NOQARZ</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="$set('customerId', {{ $customer->id }}); openCustomerModal();" class="text-xs text-cyan-400 hover:text-cyan-300 font-medium">Qoldiq kiritish</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Mijozlar mavjud emas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $customers->links() }}
            </div>
        </x-card>
    @endif

    <!-- 4. Ta’minotchilar TAB -->
    @if ($activeTab === 'suppliers')
        <x-card title="Ta’minotchilar oldidagi majburiyatlar (Signed Balance)">
            <x-slot:subtitle>Musbat qiymat = Bizning qarzimiz (qizil). Manfiy qiymat = Bizning avansimiz / haqdorligimiz (yashil).</x-slot:subtitle>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Ta’minotchi</th>
                            <th class="px-4 py-3">Kompaniya</th>
                            <th class="px-4 py-3">Telefon</th>
                            <th class="px-4 py-3 text-right">Signed Balans</th>
                            <th class="px-4 py-3 text-right">Holat</th>
                            <th class="px-4 py-3 text-right">Amal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($suppliers as $supplier)
                            @php
                                $bal = (int)$supplier->balance;
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-medium text-white">{{ $supplier->name }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $supplier->company_name ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-400 font-mono">{{ $supplier->phone ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold {{ $bal > 0 ? 'text-rose-400' : ($bal < 0 ? 'text-emerald-400' : 'text-slate-500') }}">
                                    {{ $bal > 0 ? '+' : '' }}{{ number_format($bal, 0, '', ' ') }} so‘m
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($bal > 0)
                                        <span class="px-2 py-0.5 rounded-md bg-rose-950/80 text-rose-300 border border-rose-800/50 text-[10px] font-semibold">QARZDORMIZ</span>
                                    @elseif ($bal < 0)
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-950/80 text-emerald-300 border border-emerald-800/50 text-[10px] font-semibold">HAQDORMIZ (AVANS)</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-400 text-[10px]">NOQARZ</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="$set('supplierId', {{ $supplier->id }}); openSupplierModal();" class="text-xs text-cyan-400 hover:text-cyan-300 font-medium">Qoldiq kiritish</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Ta’minotchilar mavjud emas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $suppliers->links() }}
            </div>
        </x-card>
    @endif

    <!-- 5. Hujjatlar tarixi TAB -->
    @if ($activeTab === 'history')
        <x-card title="Boshlang‘ich hisob ochilish hujjatlari">
            <x-slot:subtitle>Idempotent operation_id va hujjat raqamlari bilan tasdiqlangan ochilish amallari.</x-slot:subtitle>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Hujjat Raqami</th>
                            <th class="px-4 py-3">Turi</th>
                            <th class="px-4 py-3">Izoh</th>
                            <th class="px-4 py-3 text-right">Jami Summa</th>
                            <th class="px-4 py-3">Mas’ul</th>
                            <th class="px-4 py-3 text-right">Sana</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($documents as $doc)
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-mono font-semibold text-cyan-400">{{ $doc->document_number }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-300 text-[10px] font-semibold">{{ $doc->type }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $doc->notes ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-white">{{ number_format($doc->total_amount, 0, '', ' ') }} so‘m</td>
                                <td class="px-4 py-3 text-slate-400">{{ $doc->creator ? $doc->creator->name : 'Tizim' }}</td>
                                <td class="px-4 py-3 text-right text-slate-400">{{ $doc->created_at->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Hozircha ochilish hujjatlari mavjud emas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $documents->links() }}
            </div>
        </x-card>
    @endif

    <!-- MODAL: Ombor Qoldig‘i -->
    @if ($showStockModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white">Ombor boshlang‘ich qoldig‘ini kiritish</h3>
                    <button wire:click="$set('showStockModal', false)" class="text-slate-400 hover:text-white text-lg">&times;</button>
                </div>

                <form wire:submit="submitStockOpening" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Mahsulot varianti</label>
                        <select wire:model="stockVariantId" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">-- Variantni tanlang --</option>
                            @foreach ($variants as $v)
                                <option value="{{ $v->id }}">{{ $v->product->name }} — {{ $v->volume->name }} ({{ $v->sku }})</option>
                            @endforeach
                        </select>
                        @error('stockVariantId') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Miqdor (Dona)</label>
                            <input type="number" step="1" min="1" wire:model="stockQuantity" placeholder="Masalan: 100" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                            @error('stockQuantity') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">1 dona tannarxi (So‘m)</label>
                            <input type="number" step="1" min="0" wire:model="stockUnitCost" placeholder="Masalan: 5000" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                            @error('stockUnitCost') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    @if ($stockQuantity > 0 && $stockUnitCost >= 0)
                        <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-300">
                            <div class="flex justify-between">
                                <span>Jami ombor qiymati:</span>
                                <span class="font-bold text-cyan-400">{{ number_format($stockQuantity * $stockUnitCost, 0, '', ' ') }} so‘m</span>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                        <button type="button" wire:click="$set('showStockModal', false)" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300">Bekor qilish</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold">Qoldiqni saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL: Kassa Qoldig‘i -->
    @if ($showCashModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white">Kassa boshlang‘ich pul qoldig‘i</h3>
                    <button wire:click="$set('showCashModal', false)" class="text-slate-400 hover:text-white text-lg">&times;</button>
                </div>

                <form wire:submit="submitCashOpening" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Kassa hisobi</label>
                        <select wire:model="cashAccountId" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">-- Hisobni tanlang --</option>
                            @foreach ($cashAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} (Hozirgi: {{ number_format($acc->balance, 0, '', ' ') }} so‘m)</option>
                            @endforeach
                        </select>
                        @error('cashAccountId') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Boshlang‘ich summa (So‘m)</label>
                        <input type="number" step="1" min="1" wire:model="cashAmount" placeholder="Masalan: 500000" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                        @error('cashAmount') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                        <button type="button" wire:click="$set('showCashModal', false)" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300">Bekor qilish</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold">Kassaga kiritish</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL: Mijoz Qoldig‘i -->
    @if ($showCustomerModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white">Mijoz boshlang‘ich hisobini kiritish</h3>
                    <button wire:click="$set('showCustomerModal', false)" class="text-slate-400 hover:text-white text-lg">&times;</button>
                </div>

                <form wire:submit="submitCustomerOpening" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Mijoz</label>
                        <select wire:model="customerId" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">-- Mijozni tanlang --</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} (Hozirgi qarz: {{ number_format($c->current_debt, 0, '', ' ') }} so‘m)</option>
                            @endforeach
                        </select>
                        @error('customerId') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Qoldiq turi</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-3 rounded-xl border {{ $customerBalanceType === 'DEBT' ? 'border-rose-500 bg-rose-950/30 text-rose-300' : 'border-slate-800 bg-slate-950 text-slate-400' }} cursor-pointer">
                                <input type="radio" wire:model.live="customerBalanceType" value="DEBT" class="text-rose-500">
                                <div>
                                    <span class="font-bold block">Qarz (Musbat)</span>
                                    <span class="text-[10px] text-slate-400">Mijoz bizdan qarzdor</span>
                                </div>
                            </label>

                            <label class="flex items-center gap-2 p-3 rounded-xl border {{ $customerBalanceType === 'ADVANCE' ? 'border-emerald-500 bg-emerald-950/30 text-emerald-300' : 'border-slate-800 bg-slate-950 text-slate-400' }} cursor-pointer">
                                <input type="radio" wire:model.live="customerBalanceType" value="ADVANCE" class="text-emerald-500">
                                <div>
                                    <span class="font-bold block">Avans (Manfiy)</span>
                                    <span class="text-[10px] text-slate-400">Oldindan to‘langan pul</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Summa (So‘m)</label>
                        <input type="number" step="1" min="1" wire:model="customerAmount" placeholder="Masalan: 250000" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                        @error('customerAmount') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                        <button type="button" wire:click="$set('showCustomerModal', false)" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300">Bekor qilish</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL: Ta’minotchi Qoldig‘i -->
    @if ($showSupplierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white">Ta’minotchi boshlang‘ich hisobini kiritish</h3>
                    <button wire:click="$set('showSupplierModal', false)" class="text-slate-400 hover:text-white text-lg">&times;</button>
                </div>

                <form wire:submit="submitSupplierOpening" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Ta’minotchi</label>
                        <select wire:model="supplierId" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">-- Ta’minotchini tanlang --</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} (Hozirgi qarzimiz: {{ number_format($s->balance, 0, '', ' ') }} so‘m)</option>
                            @endforeach
                        </select>
                        @error('supplierId') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Qoldiq turi</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-3 rounded-xl border {{ $supplierBalanceType === 'PAYABLE' ? 'border-rose-500 bg-rose-950/30 text-rose-300' : 'border-slate-800 bg-slate-950 text-slate-400' }} cursor-pointer">
                                <input type="radio" wire:model.live="supplierBalanceType" value="PAYABLE" class="text-rose-500">
                                <div>
                                    <span class="font-bold block">Bizning qarzimiz</span>
                                    <span class="text-[10px] text-slate-400">Ta’minotchiga to‘lashimiz kerak</span>
                                </div>
                            </label>

                            <label class="flex items-center gap-2 p-3 rounded-xl border {{ $supplierBalanceType === 'ADVANCE' ? 'border-emerald-500 bg-emerald-950/30 text-emerald-300' : 'border-slate-800 bg-slate-950 text-slate-400' }} cursor-pointer">
                                <input type="radio" wire:model.live="supplierBalanceType" value="ADVANCE" class="text-emerald-500">
                                <div>
                                    <span class="font-bold block">Avansimiz</span>
                                    <span class="text-[10px] text-slate-400">Oldindan to‘langan summa</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Summa (So‘m)</label>
                        <input type="number" step="1" min="1" wire:model="supplierAmount" placeholder="Masalan: 300000" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                        @error('supplierAmount') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                        <button type="button" wire:click="$set('showSupplierModal', false)" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300">Bekor qilish</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
