<div class="space-y-6">
    <!-- Xabarlar -->
    @if ($successMessage)
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="font-medium text-sm">{{ $successMessage }}</span>
            </div>
            <button wire:click="$set('successMessage', null)" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="font-medium text-sm">{{ $errorMessage }}</span>
            </div>
            <button wire:click="$set('errorMessage', null)" class="text-rose-500 hover:text-rose-700">&times;</button>
        </div>
    @endif

    <!-- Yuqori qism: Boshqaruv va qidiruv -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="flex-1 flex flex-wrap items-center gap-3">
            <!-- Qidiruv -->
            <div class="relative min-w-[240px] flex-1">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Mahsulot nomi, SKU, shtrixkod..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            </div>

            <!-- Hajm filtri -->
            <select wire:model.live="volumeFilter" class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                <option value="">Barcha hajmlar</option>
                @foreach ($volumes as $vol)
                    <option value="{{ $vol->id }}">{{ $vol->name }} ({{ $vol->value_ml }} ml)</option>
                @endforeach
            </select>

            <!-- Holat filtri -->
            <select wire:model.live="statusFilter" class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                <option value="active">Faqat faollar</option>
                <option value="archived">Arxivlanganlar</option>
                <option value="all">Barchasi</option>
            </select>

            <!-- Checkbox filtrlar -->
            <label class="flex items-center space-x-2 text-xs font-medium text-slate-600 cursor-pointer bg-slate-50 px-3 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100">
                <input type="checkbox" wire:model.live="filterLowStock" class="rounded text-blue-600 focus:ring-blue-500">
                <span>Kam qoldiq</span>
            </label>

            <label class="flex items-center space-x-2 text-xs font-medium text-slate-600 cursor-pointer bg-slate-50 px-3 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100">
                <input type="checkbox" wire:model.live="filterMissingPrice" class="rounded text-amber-600 focus:ring-amber-500">
                <span>Narxi yo'q</span>
            </label>
        </div>

        <!-- Yangi mahsulot qo'shish -->
        <button wire:click="openCreateModal" class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow transition-all space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Yangi Mahsulot</span>
        </button>
    </div>

    <!-- Mahsulotlar Jadvali -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Mahsulot</th>
                        <th class="px-6 py-4">Hajmi</th>
                        <th class="px-6 py-4">SKU / Kod</th>
                        <th class="px-6 py-4">Tizim Narxi</th>
                        <th class="px-6 py-4">Qoldiq / Min chegara</th>
                        <th class="px-6 py-4">Holat</th>
                        <th class="px-6 py-4 text-right">Amallar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($variants as $variant)
                        <tr class="hover:bg-slate-50/60 transition-colors {{ $variant->status === 'archived' ? 'opacity-60 bg-slate-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">{{ $variant->product->name }}</div>
                                <div class="text-xs text-slate-400">{{ $variant->product->code }}</div>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-700">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                                    {{ $variant->volume->name }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-500">
                                {{ $variant->sku }}
                            </td>
                            <td class="px-6 py-4">
                                @if ($variant->isSystemPriceSet())
                                    <div class="font-bold text-slate-900">{{ number_format($variant->default_sale_price, 0, '.', ' ') }} so'm</div>
                                    <div class="text-[11px] text-slate-400">v{{ $variant->version }}</div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-800">
                                        Narx belgilanmagan
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $qty = $variant->balance->quantity ?? 0;
                                    $isLow = $qty <= $variant->minimum_stock;
                                @endphp
                                <div class="font-medium {{ $isLow ? 'text-rose-600 font-bold' : 'text-slate-800' }}">
                                    {{ number_format($qty, 0, '.', ' ') }} dona
                                </div>
                                <div class="text-[11px] text-slate-400">Min: {{ $variant->minimum_stock }} dona</div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($variant->status === 'active')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Faol
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        Arxiv
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <!-- Narx tahrirlash -->
                                <button wire:click="openPriceModal({{ $variant->id }})" title="Narx belgilash / tahrirlash" class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>

                                <!-- Arxivlash / Qayta faollashtirish -->
                                <button wire:click="toggleArchive({{ $variant->id }})" title="{{ $variant->status === 'active' ? 'Arxivlash' : 'Faollashtirish' }}" class="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                </button>

                                <!-- O'chirish -->
                                <button wire:click="deleteVariant({{ $variant->id }})" wire:confirm="Rostdan ham ushbu mahsulot variantini o'chirmoqchimisiz? Agar tarixda ishlatilgan bo'lsa tizim ruxsat bermaydi." title="O'chirish" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    <p class="font-medium text-slate-500">Hech qanday mahsulot varianti topilmadi</p>
                                    <p class="text-xs text-slate-400">Qidiruv parametrlarini o'zgartiring yoki yangi mahsulot qo'shing</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($variants->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $variants->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL 1: Yangi Mahsulot va Variant Qo'shish -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="font-bold text-slate-900 text-lg">Yangi Mahsulot Qo'shish</h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="saveProduct" class="p-6 space-y-4">
                    <!-- Mahsulot Nomi -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Mahsulot Nomi <span class="text-rose-500">*</span>
                        </label>
                        <input wire:model="newProductName" type="text" placeholder="Masalan: Fanta, Coca-Cola, Chortoq..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <p class="text-[11px] text-slate-400 mt-1">Nom yetarli: kod va ID avtomatik beriladi. Duplicate'lar avtomatik birlashtiriladi.</p>
                        @error('newProductName') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Hajm Tanlash -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Hajmi <span class="text-rose-500">*</span>
                        </label>
                        <select wire:model.live="newVolumeInput" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 mb-2">
                            <option value="0.25 L">0.25 L (250 ml)</option>
                            <option value="0.33 L">0.33 L (330 ml)</option>
                            <option value="0.5 L">0.5 L (500 ml)</option>
                            <option value="1 L">1 L (1000 ml)</option>
                            <option value="1.5 L">1.5 L (1500 ml)</option>
                            <option value="2 L">2 L (2000 ml)</option>
                            <option value="2.25 L">2.25 L (2250 ml)</option>
                            <option value="5 L">5 L (5000 ml)</option>
                            <option value="18.9 L">18.9 L (Katta kapsula)</option>
                            <option value="custom">+ Yangi hajm kiritish...</option>
                        </select>

                        @if ($newVolumeInput === 'custom')
                            <input wire:model="customVolumeInput" type="text" placeholder="Masalan: 0.75 L yoki 750 ml" class="w-full px-4 py-2.5 bg-slate-50 border border-blue-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <p class="text-[11px] text-slate-400 mt-1">0.5, 0,5 L, 500 ml formatlari avtomatik normallashtiriladi.</p>
                        @endif
                        @error('newVolumeInput') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Standart Sotuv Narxi -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Standart Tizim Narxi (1 dona uchun, so'm)
                        </label>
                        <input wire:model="newDefaultPrice" type="number" placeholder="Ixtiyoriy, masalan: 6500" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <p class="text-[11px] text-slate-400 mt-1">Bo'sh qoldirilsa, savdoda erkin narx kiritilishi talab etiladi.</p>
                        @error('newDefaultPrice') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Minimal Chegara va Shtrixkod -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Min Qoldiq Chegarasi
                            </label>
                            <input wire:model="newMinimumStock" type="number" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Shtrixkod (Ixtiyoriy)
                            </label>
                            <input wire:model="newBarcode" type="text" placeholder="Barcode" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            Bekor qilish
                        </button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition-all">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL 2: Tizim Narxini Tahrirlash va Tarix -->
    @if ($showPriceModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg">Tizim Narxini O'zgartirish</h3>
                        <p class="text-xs text-slate-500">Har bir narx o'zgarishi versiyalanadi va tarixda saqlanadi</p>
                    </div>
                    <button wire:click="$set('showPriceModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="savePrice" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Joriy Narx</label>
                        <div class="text-base font-bold text-slate-800 bg-slate-100 px-4 py-2 rounded-xl">
                            {{ $editingCurrentPrice ? number_format($editingCurrentPrice, 0, '.', ' ') . " so'm" : "Belgilanmagan" }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Yangi Sotuv Narxi (so'm) <span class="text-rose-500">*</span>
                        </label>
                        <input wire:model="newPriceInput" type="number" placeholder="Yangi narx..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-bold">
                        @error('newPriceInput') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            O'zgartirish Sababi (Ixtiyoriy)
                        </label>
                        <input wire:model="priceChangeReason" type="text" placeholder="Masalan: Zavod narxi oshdi / Yangi partiya" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <!-- Narx Tarixi -->
                    @if (!empty($editingPriceHistory))
                        <div class="pt-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Oxirgi O'zgarishlar Tarixi</label>
                            <div class="max-h-40 overflow-y-auto space-y-2 pr-1 text-xs">
                                @foreach ($editingPriceHistory as $history)
                                    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-between">
                                        <div>
                                            <span class="font-bold text-slate-800">{{ number_format($history['new_price'], 0, '.', ' ') }} so'm</span>
                                            <span class="text-slate-400">({{ $history['old_price'] ? number_format($history['old_price'], 0, '.', ' ') : '0' }} dan)</span>
                                            <div class="text-[11px] text-slate-500 mt-0.5">{{ $history['reason'] ?? 'Sabab ko\'rsatilmagan' }}</div>
                                        </div>
                                        <div class="text-right text-[11px] text-slate-400 font-mono">
                                            <div>v{{ $history['version'] }}</div>
                                            <div>{{ \Carbon\Carbon::parse($history['changed_at'])->format('d.m.Y H:i') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                        <button type="button" wire:click="$set('showPriceModal', false)" class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            Bekor qilish
                        </button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition-all">
                            Narxni Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
