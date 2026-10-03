<div class="space-y-6">
    <!-- Inline modallar -->
    <livewire:modals.inline-product-modal />
    <livewire:modals.inline-supplier-modal />

    <!-- Tab tugmalari -->
    <div class="flex items-center space-x-2 border-b border-slate-800 pb-3">
        <button wire:click="$set('activeTab', 'receiving')" class="px-4 py-2 text-xs font-semibold rounded-xl transition-all {{ $activeTab === 'receiving' ? 'bg-cyan-500 text-slate-950 font-bold shadow-sm' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            📥 Tovar Kirimi Qoralamasi
        </button>
        <button wire:click="$set('activeTab', 'catalog')" class="px-4 py-2 text-xs font-semibold rounded-xl transition-all {{ $activeTab === 'catalog' ? 'bg-cyan-500 text-slate-950 font-bold shadow-sm' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            📦 Mahsulotlar va Tizim Narxlari
        </button>
        <button wire:click="$set('activeTab', 'suppliers')" class="px-4 py-2 text-xs font-semibold rounded-xl transition-all {{ $activeTab === 'suppliers' ? 'bg-cyan-500 text-slate-950 font-bold shadow-sm' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
            🏭 Ta'minotchilar Ro'yxati
        </button>
    </div>

    <!-- Tab kontentlari -->
    @if ($activeTab === 'catalog')
        <livewire:catalog.product-manager />
    @elseif ($activeTab === 'suppliers')
        <livewire:parties.supplier-manager />
    @elseif ($activeTab === 'receiving')
        <!-- Tovar Kirimi Qoralamasi -->
        <div class="space-y-5">
            <!-- Muvaffaqiyatli kirim cheki (Receipt Card) -->
            @if ($successPurchase)
                <div class="p-5 rounded-2xl bg-emerald-950/60 border border-emerald-800 text-emerald-200 shadow-lg space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="p-2 rounded-xl bg-emerald-900/80 text-emerald-400">✓</span>
                            <div>
                                <h3 class="text-sm font-bold text-white">Kirim hujjati muvaffaqiyatli POST qilindi!</h3>
                                <p class="text-xs text-emerald-300 font-mono">Hujjat raqami: {{ $successPurchase['invoice_number'] }}</p>
                            </div>
                        </div>
                        <button wire:click="$set('successPurchase', null)" class="text-emerald-400 hover:text-white text-lg">&times;</button>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-emerald-800/60 text-xs">
                        <div>
                            <span class="text-slate-400 block">Ta'minotchi:</span>
                            <span class="font-semibold text-white">{{ $successPurchase['supplier_name'] }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Jami summa:</span>
                            <span class="font-bold text-white">{{ number_format($successPurchase['total_amount'], 0, '', ' ') }} so‘m</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">To‘landi (Kassa):</span>
                            <span class="font-semibold text-emerald-400">{{ number_format($successPurchase['paid_amount'], 0, '', ' ') }} so‘m</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Qarzga qoldi:</span>
                            <span class="font-semibold text-rose-400">{{ number_format($successPurchase['debt_amount'], 0, '', ' ') }} so‘m</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Xabarlar -->
            @if ($inwardMessage)
                <div class="p-3.5 rounded-xl bg-cyan-950/60 border border-cyan-800 text-cyan-300 text-xs flex items-center justify-between shadow-sm">
                    <span class="font-medium">{{ $inwardMessage }}</span>
                    <button wire:click="$set('inwardMessage', null)" class="text-cyan-400 hover:text-white">&times;</button>
                </div>
            @endif

            @if ($errorMessage)
                <div class="p-3.5 rounded-xl bg-rose-950/60 border border-rose-800 text-rose-300 text-xs flex items-center justify-between shadow-sm">
                    <span class="font-medium">{{ $errorMessage }}</span>
                    <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-white">&times;</button>
                </div>
            @endif

            <!-- 1. Ta'minotchi va Hujjat rekvizitlari -->
            <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white tracking-tight">1. Ta'minotchi va Hujjat Ma'lumotlari</h3>
                        <p class="text-xs text-slate-400">Tovarni yetkazib bergan zavod yoki distribyutorni tanlang.</p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="button" wire:click="$dispatch('open-inline-supplier-modal')" class="inline-flex items-center px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl transition-colors space-x-1">
                            <span class="text-cyan-400">+</span>
                            <span>Yangi Ta'minotchi</span>
                        </button>
                        <button type="button" wire:click="$dispatch('open-inline-product-modal')" class="inline-flex items-center px-3 py-1.5 bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 text-xs font-semibold rounded-xl transition-colors space-x-1">
                            <span>+</span>
                            <span>Yangi Mahsulot</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Ta'minotchi (Majburiy) *</label>
                        <select wire:model.live="selectedSupplierId" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">-- Ta'minotchini tanlang --</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} {{ $s->company_name ? "({$s->company_name})" : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Nakladnoy / Faktura raqami (Ixtiyoriy)</label>
                        <input type="text" wire:model="supplierInvoiceNumber" placeholder="Masalan: N-1052" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Kirim izohi (Ixtiyoriy)</label>
                        <input type="text" wire:model="notes" placeholder="Masalan: Fura yuk tushirildi" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- 2. Tovarlarni tanlash va qo'shish -->
            <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white tracking-tight">2. Kirim Tovarlari Qatorlari</h3>
                        <p class="text-xs text-slate-400">Mavjud katalogdan tanlang yoki yangi mahsulot qo‘shing.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <select wire:model="quickVariantId" class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:border-cyan-500 focus:outline-none min-w-[220px]">
                            <option value="">-- Mahsulotni tanlang --</option>
                            @foreach ($availableVariants as $av)
                                <option value="{{ $av->id }}">{{ $av->product->name }} — {{ $av->volume->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addSelectedVariant" class="px-3 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs">
                            + Qator qo‘shish
                        </button>
                    </div>
                </div>

                <!-- Tovarlar jadvali -->
                <div class="overflow-x-auto rounded-xl border border-slate-800">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                            <tr>
                                <th class="px-4 py-3">№</th>
                                <th class="px-4 py-3">Mahsulot va Hajm</th>
                                <th class="px-4 py-3 text-center">Miqdor (Dona)</th>
                                <th class="px-4 py-3 text-right">Kirim narxi (1 dona)</th>
                                <th class="px-4 py-3 text-right">Jami summa</th>
                                <th class="px-4 py-3 text-right">Yangi sotuv narxi</th>
                                <th class="px-4 py-3 text-center">Amal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse ($items as $index => $item)
                                @php
                                    $lineTotal = ((int)$item['quantity'] * (int)$item['unit_cost']);
                                @endphp
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3 text-slate-500 font-mono">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-white">{{ $item['display_name'] }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $item['sku'] ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input
                                            type="number"
                                            min="1"
                                            step="1"
                                            value="{{ $item['quantity'] }}"
                                            wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                            class="w-24 text-center px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 text-white font-bold focus:border-cyan-500 focus:outline-none"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            value="{{ $item['unit_cost'] }}"
                                            wire:change="updateUnitCost({{ $index }}, $event.target.value)"
                                            class="w-28 text-right px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 text-white font-mono focus:border-cyan-500 focus:outline-none"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-cyan-400">
                                        {{ number_format($lineTotal, 0, '', ' ') }} so‘m
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            wire:model="items.{{ $index }}.new_sale_price"
                                            placeholder="Ixtiyoriy"
                                            class="w-24 text-right px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-300 text-[11px] focus:border-cyan-500 focus:outline-none"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button wire:click="removeItem({{ $index }})" class="p-1 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-950/30 transition">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                        Kirim qoralamasi bo‘sh. Yuqoridagi ro‘yxatdan tovar tanlang yoki "+ Yangi Mahsulot" tugmasini bosing.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. To'lov, Xulosa va Kirimni Tasdiqlash -->
            @if (! empty($items))
                <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 shadow-sm space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-4">
                        <div>
                            <span class="text-xs text-slate-400">Jami kirim qilingan tovarlar:</span>
                            <div class="text-sm font-bold text-white">{{ count($items) }} xil tovar, {{ number_format(array_sum(array_column($items, 'quantity')), 0, '', ' ') }} dona</div>
                        </div>

                        <div class="text-right">
                            <span class="text-xs text-slate-400">Jami hisoblangan summa:</span>
                            <div class="text-2xl font-black text-cyan-400">{{ number_format($totalAmount, 0, '', ' ') }} so‘m</div>
                        </div>
                    </div>

                    <!-- To'lov bo'limi -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold text-white uppercase tracking-wider">To‘lov va Hisob-kitob</h4>
                            @if (! $canManageCash)
                                <span class="text-[11px] text-amber-400 bg-amber-950/60 border border-amber-800/60 px-2 py-0.5 rounded-md">
                                    Moliya huquqi yo‘q (Faqat to‘lovsiz kirim)
                                </span>
                            @endif
                        </div>

                        @if ($canManageCash)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div>
                                    <label class="block text-slate-300 font-medium mb-1">To‘lov shakli</label>
                                    <select wire:model.live="paymentType" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                                        <option value="UNPAID">To‘lovsiz (To‘liq nasiya qarz)</option>
                                        <option value="FULL">To‘liq to‘lov (Kassadan pul chiqadi)</option>
                                        <option value="PARTIAL">Qisman to‘lov</option>
                                    </select>
                                </div>

                                @if ($paymentType !== 'UNPAID')
                                    <div>
                                        <label class="block text-slate-300 font-medium mb-1">To‘lanadigan summa (So‘m)</label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="{{ $totalAmount }}"
                                            wire:model.live="paidAmount"
                                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono font-bold focus:border-cyan-500 focus:outline-none"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-slate-300 font-medium mb-1">Kassa hisobi</label>
                                        <select wire:model="cashAccountId" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-cyan-500 focus:outline-none">
                                            @foreach ($cashAccounts as $ca)
                                                <option value="{{ $ca->id }}">{{ $ca->name }} ({{ number_format($ca->balance, 0, '', ' ') }} so‘m)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 flex flex-wrap items-center justify-between text-xs gap-3">
                            <div class="flex items-center gap-6">
                                <div>
                                    <span class="text-slate-400">Hozir to‘lanadi:</span>
                                    <span class="font-bold text-emerald-400 ml-1 font-mono">{{ number_format($paidAmount, 0, '', ' ') }} so‘m</span>
                                </div>
                                <div>
                                    <span class="text-slate-400">Ta'minotchi qarziga yoziladi:</span>
                                    <span class="font-bold text-rose-400 ml-1 font-mono">{{ number_format(max(0, $totalAmount - $paidAmount), 0, '', ' ') }} so‘m</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="clearDraft" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold transition">
                                    Qoralamani tozalash
                                </button>
                                <button type="button" wire:click="postPurchase" class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold shadow-md transition flex items-center gap-1.5">
                                    <span>📥</span>
                                    <span>Kirimni tasdiqlash va qabul qilish</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
