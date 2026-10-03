<div class="space-y-6">
    <!-- Inline modallar -->
    <livewire:modals.inline-product-modal />
    <livewire:modals.inline-customer-modal />

    <!-- Xabarnomalar -->
    @if ($errorMessage)
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="font-medium">{{ $errorMessage }}</span>
            </div>
            <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-rose-600 text-lg">&times;</button>
        </div>
    @endif

    @if ($posMessage)
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="font-medium">{{ $posMessage }}</span>
            </div>
            <button wire:click="$set('posMessage', null)" class="text-emerald-400 hover:text-emerald-600 text-lg">&times;</button>
        </div>
    @endif

    <!-- Muvaffaqiyatli Chek Kvitansiyasi (Elektron Hujjat) -->
    @if ($completedSale)
        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 text-white shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400 font-bold">✓</span>
                    <div>
                        <h3 class="text-base font-bold text-white">Savdo Muvaffaqiyatli Yakunlandi!</h3>
                        <p class="text-xs text-slate-400 font-mono">Elektron Chek: #{{ $completedSale['invoice_number'] ?? '' }}</p>
                    </div>
                </div>
                <button wire:click="$set('completedSale', null)" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-slate-400 block">Xaridor:</span>
                    <span class="font-bold text-white text-sm">{{ $completedSale['customer_name'] ?? 'Tezkor xaridor' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">Jami Summa:</span>
                    <span class="font-black text-emerald-400 text-sm">{{ number_format($completedSale['total_amount'] ?? 0, 0, '', ' ') }} so‘m</span>
                </div>
                <div>
                    <span class="text-slate-400 block">To‘landi (Kassa):</span>
                    <span class="font-semibold text-white text-sm">{{ number_format($completedSale['paid_amount'] ?? 0, 0, '', ' ') }} so‘m</span>
                </div>
                <div>
                    <span class="text-slate-400 block">Nasiya (Qarz):</span>
                    <span class="font-bold text-rose-400 text-sm">{{ number_format($completedSale['debt_amount'] ?? 0, 0, '', ' ') }} so‘m</span>
                </div>
            </div>

            @if (!empty($completedSale['items']))
                <div class="pt-3 border-t border-slate-800 text-xs text-slate-300">
                    <div class="font-semibold text-slate-400 mb-1">Xarid tarkibi:</div>
                    <div class="space-y-1">
                        @foreach ($completedSale['items'] as $it)
                            <div class="flex justify-between font-mono text-[11px]">
                                <span>{{ $it['product_name'] ?? $it['display_name'] ?? 'Tovar' }} ({{ $it['quantity'] }} dona × {{ number_format($it['sale_price'] ?? $it['price'] ?? 0, 0, '', ' ') }})</span>
                                <span class="font-bold">{{ number_format($it['line_total'] ?? $it['total'] ?? 0, 0, '', ' ') }} so‘m</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Chap: Savat va Chek Qoralamasi (2/3) -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Xaridor tanlash paneli -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Xaridor:</span>
                        @if ($selectedCustomerId)
                            <div class="font-bold text-slate-900 text-base mt-0.5 flex items-center space-x-2">
                                <span>{{ $selectedCustomerName }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $customerCurrentDebt > 0 ? 'bg-rose-100 text-rose-700' : ($customerCurrentDebt < 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $customerCurrentDebt > 0 ? 'Qarzi: ' . number_format($customerCurrentDebt, 0, '', ' ') . ' so‘m' : ($customerCurrentDebt < 0 ? 'Avansi: ' . number_format(abs($customerCurrentDebt), 0, '', ' ') . ' so‘m' : 'Qarzsiz') }}
                                </span>
                                <button wire:click="selectExistingCustomer(null)" class="text-xs text-rose-500 hover:text-rose-700 underline font-normal">(Bekor qilish)</button>
                            </div>
                        @else
                            <span class="text-sm font-medium text-slate-400 flex items-center gap-1.5 mt-0.5">
                                <span>Tezkor savdo (Noma'lum xaridor — faqat to‘liq to‘lov)</span>
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="button" wire:click="$dispatch('open-inline-customer-modal')" class="inline-flex items-center px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-xl transition-colors space-x-1.5">
                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            <span>+ Yangi Mijoz</span>
                        </button>
                    </div>
                </div>

                <!-- Tezkor mijozlar ro'yxati -->
                @if (empty($selectedCustomerId) && count($recentCustomers) > 0)
                    <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-100">
                        <span class="text-[11px] text-slate-400 font-medium py-1">Tezkor tanlash:</span>
                        @foreach ($recentCustomers as $rc)
                            <button type="button" wire:click="selectExistingCustomer({{ $rc->id }})" class="px-2.5 py-1 rounded-lg text-xs bg-slate-50 hover:bg-blue-50 hover:text-blue-700 text-slate-700 border border-slate-200 font-medium transition-colors">
                                {{ $rc->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Savatdagi qatorlar -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="font-bold text-slate-900 text-sm">Savdo Qoralamasi (Savat)</h3>
                    <div class="flex items-center space-x-2">
                        <button type="button" wire:click="$dispatch('open-inline-product-modal')" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-semibold rounded-lg transition-colors space-x-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>+ Yangi Tovar</span>
                        </button>
                        @if (!empty($items))
                            <button type="button" wire:click="clearDraft" class="text-xs text-slate-400 hover:text-rose-500 font-medium transition-colors px-2 py-1">
                                Tozalash
                            </button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50/50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="px-4 py-3">Mahsulot</th>
                                <th class="px-3 py-3 text-center">Miqdor (dona)</th>
                                <th class="px-3 py-3 text-center">Narx turi</th>
                                <th class="px-3 py-3 text-right">Narx (so'm)</th>
                                <th class="px-4 py-3 text-right">Jami</th>
                                <th class="px-3 py-3 text-center">Amal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($items as $index => $item)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-slate-900">{{ $item['display_name'] }}</div>
                                        <div class="text-[11px] font-mono text-slate-400">{{ $item['sku'] }}</div>
                                    </td>
                                    <td class="px-3 py-3.5 text-center">
                                        <div class="inline-flex items-center space-x-1 bg-slate-100 rounded-lg p-1">
                                            <button type="button" wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] - 1 }})" class="w-6 h-6 flex items-center justify-center rounded bg-white text-slate-700 hover:bg-slate-200 text-xs font-bold">-</button>
                                            <span class="w-10 text-center font-bold text-slate-900 text-xs">{{ $item['quantity'] }}</span>
                                            <button type="button" wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})" class="w-6 h-6 flex items-center justify-center rounded bg-white text-slate-700 hover:bg-slate-200 text-xs font-bold">+</button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-center">
                                        <label class="inline-flex items-center gap-1 cursor-pointer text-xs">
                                            <input type="checkbox" wire:click="toggleSystemPrice({{ $index }})" {{ !empty($item['is_system_price']) ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                            <span class="text-[11px] {{ !empty($item['is_system_price']) ? 'font-semibold text-blue-600' : 'text-slate-500' }}">
                                                {{ !empty($item['is_system_price']) ? 'Tizim' : 'Erkin' }}
                                            </span>
                                        </label>
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-medium text-slate-800">
                                        @if (!empty($item['is_system_price']))
                                            <span class="font-bold text-slate-900">{{ number_format($item['price'], 0, '.', ' ') }}</span>
                                        @else
                                            <input type="number" wire:change="updatePrice({{ $index }}, $event.target.value)" value="{{ $item['price'] }}" class="w-24 px-2 py-1 text-right text-xs rounded border border-slate-300 focus:ring-1 focus:ring-blue-500 font-bold">
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-bold text-slate-900">
                                        {{ number_format($item['total'], 0, '.', ' ') }} so'm
                                    </td>
                                    <td class="px-3 py-3.5 text-center">
                                        <button type="button" wire:click="removeItem({{ $index }})" class="text-slate-400 hover:text-rose-500 p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                        <div class="flex flex-col items-center justify-center space-y-1">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            <p class="font-medium text-slate-500 text-xs">Savat bo'sh</p>
                                            <p class="text-[11px] text-slate-400">O'ng tarafdagi tezkor tovarlardan tanlang yoki yangi mahsulot qo'shing</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- O'ng: To'lov paneli va Tezkor tovarlar (1/3) -->
        <div class="space-y-4">
            <!-- Jami summa va Checkout paneli -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-5 rounded-2xl shadow-xl border border-slate-700 space-y-4">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">Jami Savdo Summasi:</span>
                    <div class="text-3xl font-black text-emerald-400 tracking-tight">
                        {{ number_format($totalAmount, 0, '.', ' ') }} <span class="text-sm font-medium text-emerald-200">so‘m</span>
                    </div>
                </div>

                <!-- To'lov rejimlari (Tabs) -->
                <div class="pt-3 border-t border-slate-700/80 space-y-3">
                    <span class="text-xs font-semibold text-slate-300 block">To‘lov rejimi:</span>
                    <div class="grid grid-cols-3 gap-1 bg-slate-800 p-1 rounded-xl text-xs font-semibold">
                        <button type="button" wire:click="setPaymentType('FULL')" class="py-1.5 rounded-lg transition-colors {{ $paymentType === 'FULL' ? 'bg-emerald-500 text-white' : 'text-slate-400 hover:text-white' }}">
                            To‘liq
                        </button>
                        <button type="button" wire:click="setPaymentType('PARTIAL')" @if(!$selectedCustomerId) disabled title="Mijoz tanlanishi shart" @endif class="py-1.5 rounded-lg transition-colors {{ $paymentType === 'PARTIAL' ? 'bg-amber-500 text-white' : 'text-slate-400 hover:text-white disabled:opacity-40' }}">
                            Qisman
                        </button>
                        <button type="button" wire:click="setPaymentType('DEBT')" @if(!$selectedCustomerId) disabled title="Mijoz tanlanishi shart" @endif class="py-1.5 rounded-lg transition-colors {{ $paymentType === 'DEBT' ? 'bg-rose-500 text-white' : 'text-slate-400 hover:text-white disabled:opacity-40' }}">
                            Nasiya
                        </button>
                    </div>

                    <!-- To'lov summasi kiritish (Qisman to'lovda) -->
                    @if ($paymentType === 'PARTIAL')
                        <div class="space-y-1">
                            <label class="text-[11px] text-slate-300">Hozir to‘lanayotgan summa:</label>
                            <input type="number" wire:model.live="paidAmount" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-xl text-emerald-400 font-bold text-sm focus:ring-1 focus:ring-emerald-500">
                        </div>
                    @endif

                    <!-- To'lov usuli va Kassa tanlash -->
                    @if ($paidAmount > 0)
                        <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
                            <div>
                                <label class="text-[11px] text-slate-400 block mb-1">To‘lov usuli:</label>
                                <select wire:model.live="paymentMethod" class="w-full bg-slate-900 border border-slate-600 rounded-xl px-2.5 py-1.5 text-xs text-white">
                                    <option value="CASH">Naqd pul</option>
                                    <option value="CARD">Karta / Terminal</option>
                                    <option value="BANK">Bank o‘tkazmasi</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-400 block mb-1">Kassa hisobi:</label>
                                <select wire:model.live="cashAccountId" class="w-full bg-slate-900 border border-slate-600 rounded-xl px-2.5 py-1.5 text-xs text-white">
                                    @foreach ($cashAccounts as $ca)
                                        <option value="{{ $ca->id }}">{{ $ca->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <!-- Moliyaviy xulosa snapshot -->
                    <div class="pt-3 border-t border-slate-700/80 space-y-1.5 text-xs text-slate-300 font-mono">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Hozir to‘lanadi:</span>
                            <span class="font-bold text-emerald-400">{{ number_format($paidAmount, 0, '', ' ') }} so‘m</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Yangi nasiya:</span>
                            <span class="font-bold text-rose-400">{{ number_format($debtAmount, 0, '', ' ') }} so‘m</span>
                        </div>
                        @if ($selectedCustomerId)
                            <div class="flex justify-between">
                                <span class="text-slate-400">Eski hisob:</span>
                                <span>{{ number_format($customerCurrentDebt, 0, '', ' ') }} so‘m</span>
                            </div>
                            <div class="flex justify-between font-bold pt-1 border-t border-slate-700 text-white">
                                <span>Yakuniy qarz:</span>
                                <span class="{{ $finalCustomerDebt > 0 ? 'text-rose-400' : 'text-emerald-400' }}">{{ number_format($finalCustomerDebt, 0, '', ' ') }} so‘m</span>
                            </div>
                        @endif
                    </div>

                    <!-- Tasdiqlash tugmasi -->
                    <button type="button" wire:click="checkout" wire:loading.attr="disabled" class="w-full py-3 px-4 bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-slate-950 font-black text-sm rounded-xl shadow-lg transition-all flex items-center justify-center space-x-2 disabled:opacity-50 mt-2">
                        <span wire:loading.remove wire:target="checkout">✓ Tasdiqlash va Chek Chiqarish</span>
                        <span wire:loading wire:target="checkout">Bajarilmoqda...</span>
                    </button>
                </div>
            </div>

            <!-- Tezkor tovarlar katalogi -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
                <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Tezkor Tovarlar</h4>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($recentVariants as $rv)
                        <button type="button" wire:click="onProductCreated({{ json_encode([
                            'variant_id' => $rv->id,
                            'sku' => $rv->sku,
                            'display_name' => $rv->product->name . ' ' . $rv->volume->name,
                            'sale_price' => $rv->default_sale_price,
                        ]) }})" class="p-2.5 rounded-xl border border-slate-200 text-left hover:border-blue-500 hover:bg-blue-50/50 transition-all group">
                            <div class="font-bold text-slate-800 text-xs group-hover:text-blue-700 truncate">{{ $rv->product->name }}</div>
                            <div class="text-[11px] text-slate-500 font-medium">{{ $rv->volume->name }}</div>
                            <div class="text-[11px] font-bold text-slate-900 mt-1">
                                {{ $rv->default_sale_price ? number_format($rv->default_sale_price, 0, '.', ' ') . " so'm" : "Narxsiz" }}
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
