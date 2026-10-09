<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="inline-customer-title" x-data x-on:keydown.escape.window="$wire.close()" x-init="$nextTick(() => $el.querySelector('form input').focus())" class="trade-dialog bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 id="inline-customer-title" class="font-bold text-slate-900 text-base">Yangi mijoz</h3>
                    <button type="button" aria-label="Oynani yopish" wire:click="close" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="save" class="p-5 space-y-3.5">
                    @if ($errorMessage)
                        <div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <div>
                        <label for="inline-customer-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Mijoz Ismi <span class="text-rose-500">*</span>
                        </label>
                        <input id="inline-customer-name" wire:model="name" type="text" placeholder="Masalan: Dilshod aka" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @error('name') <span class="text-xs text-rose-500 mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="inline-customer-phone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Telefon Raqami
                        </label>
                        <input id="inline-customer-phone" wire:model.live.debounce.400ms="phone" type="tel" inputmode="tel" placeholder="+998901234567" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        
                        @if ($phoneWarning)
                            <div class="mt-1.5 p-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-[11px]">
                                {{ $phoneWarning }}
                            </div>
                        @endif
                        @error('phone') <span class="text-xs text-rose-500 mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label for="inline-customer-storeName" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Do'kon Nomi
                            </label>
                            <input id="inline-customer-storeName" wire:model="storeName" type="text" placeholder="Do'kon" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            @error('storeName') <span class="text-xs text-rose-500 mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="inline-customer-address" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Manzil
                            </label>
                            <input id="inline-customer-address" wire:model="address" type="text" placeholder="Manzil" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            @error('address') <span class="text-xs text-rose-500 mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400">Telefon kiritilmasa do'kon yoki manzil kiritilishi shart.</p>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                        <button type="button" wire:click="close" class="px-3.5 py-2 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            Bekor qilish
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition-all">
                            Saqlash va tanlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
