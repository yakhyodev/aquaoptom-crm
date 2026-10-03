<div class="space-y-6">
    <!-- Inline modallar -->
    <livewire:modals.inline-product-modal />
    <livewire:modals.inline-customer-modal />

    <!-- Xabarnoma -->
    @if ($posMessage)
        <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="font-medium">{{ $posMessage }}</span>
            </div>
            <button wire:click="$set('posMessage', null)" class="text-blue-400 hover:text-blue-600">&times;</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Chap: Savat va Chek Qoralamasi -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Mijoz tanlash bloki -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Tanlangan Mijoz:</span>
                    @if ($selectedCustomerId)
                        <div class="font-bold text-slate-900 text-base mt-0.5 flex items-center space-x-2">
                            <span>{{ $selectedCustomerName }}</span>
                            <button wire:click="$set('selectedCustomerId', null); $set('selectedCustomerName', null)" class="text-xs text-rose-500 hover:text-rose-700 underline font-normal">(Bekor qilish)</button>
                        </div>
                    @else
                        <span class="text-sm font-medium text-slate-400">Mijoz tanlanmagan (Tezkor naqd savdo)</span>
                    @endif
                </div>

                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="$dispatch('open-inline-customer-modal')" class="inline-flex items-center px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-xl transition-colors space-x-1.5">
                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        <span>+ Yangi Mijoz</span>
                    </button>
                </div>
            </div>

            <!-- Savatdagi qatorlar -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="font-bold text-slate-900 text-sm">Savdo Qoralamasi (Savat)</h3>
                    <div class="flex items-center space-x-2">
                        <button type="button" wire:click="$dispatch('open-inline-product-modal')" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-semibold rounded-lg transition-colors space-x-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>+ Yangi Mahsulot</span>
                        </button>
                        @if (!empty($items))
                            <button type="button" wire:click="clearDraft" class="text-xs text-slate-400 hover:text-rose-500 font-medium transition-colors">
                                Tozalash
                            </button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50/50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="px-5 py-3">Mahsulot</th>
                                <th class="px-4 py-3 text-center">Miqdor (dona)</th>
                                <th class="px-4 py-3 text-right">Narx (so'm)</th>
                                <th class="px-5 py-3 text-right">Jami</th>
                                <th class="px-4 py-3 text-center">Amal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($items as $index => $item)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-slate-900">{{ $item['display_name'] }}</div>
                                        <div class="text-[11px] font-mono text-slate-400">{{ $item['sku'] }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <div class="inline-flex items-center space-x-1 bg-slate-100 rounded-lg p-1">
                                            <button wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] - 1 }})" class="w-6 h-6 flex items-center justify-center rounded bg-white text-slate-700 hover:bg-slate-200 text-xs font-bold">-</button>
                                            <span class="w-10 text-center font-bold text-slate-900 text-xs">{{ $item['quantity'] }}</span>
                                            <button wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})" class="w-6 h-6 flex items-center justify-center rounded bg-white text-slate-700 hover:bg-slate-200 text-xs font-bold">+</button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-medium text-slate-800">
                                        {{ number_format($item['price'], 0, '.', ' ') }} so'm
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-bold text-slate-900">
                                        {{ number_format($item['total'], 0, '.', ' ') }} so'm
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <button wire:click="removeItem({{ $index }})" class="text-slate-400 hover:text-rose-500 p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-12 text-center text-slate-400">
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

        <!-- O'ng: Tezkor tovarlar va Jami xulosa -->
        <div class="space-y-4">
            <!-- Jami summa -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-5 rounded-2xl shadow-lg border border-slate-700">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">To'lanishi Kerak:</span>
                <div class="text-2xl font-black text-emerald-400">
                    {{ number_format($totalAmount, 0, '.', ' ') }} so'm
                </div>
                <div class="mt-4 pt-4 border-t border-slate-700 flex items-center justify-between text-xs text-slate-300">
                    <span>Qatorlar soni:</span>
                    <span class="font-bold">{{ count($items) }} xil tovar</span>
                </div>
            </div>

            <!-- Tezkor tovarlar katalogi -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
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
