<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="font-bold text-slate-900 text-base">Mahsulot yoki yangi hajm qo‘shish</h3>
                    <button wire:click="close" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="save" class="p-5 space-y-3.5">
                    @if ($errorMessage)
                        <div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <p class="text-xs text-slate-600">Mavjud mahsulotga yangi litr qo‘shish uchun uning nomini yozing. Hajmdan «+ Boshqa hajm...» ni tanlang.</p>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Mahsulot Nomi <span class="text-rose-500">*</span>
                        </label>
                        <input wire:model="productName" type="text" placeholder="Masalan: Fanta, Dinay..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @error('productName') <span class="text-xs text-rose-500 mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Hajmi <span class="text-rose-500">*</span>
                        </label>
                        <select wire:model.live="volumeInput" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 mb-1.5">
                            @forelse ($volumes as $volume)
                                <option value="{{ $volume->name }}">{{ $volume->name }} ({{ $volume->value_ml }} ml)</option>
                            @empty
                                <option value="0.5 L">0.5 L (500 ml)</option>
                            @endforelse
                            <option value="custom">+ Boshqa hajm...</option>
                        </select>

                        @if ($volumeInput === 'custom')
                            <input wire:model="customVolumeInput" type="text" placeholder="Masalan: 0.75 L yoki 750 ml" class="w-full px-3.5 py-2 bg-slate-50 border border-blue-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @endif
                        @error('volumeInput') <span class="text-xs text-rose-500 mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Sotuv Narxi (1 dona uchun, so'm)
                        </label>
                        <input wire:model="defaultPrice" type="number" placeholder="Ixtiyoriy, masalan: 7000" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                        <button type="button" wire:click="close" class="px-3.5 py-2 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            Bekor qilish
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition-all">
                            Saqlash va Tanlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
