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

    <!-- Boshqaruv va qidiruv -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="flex-1 flex flex-wrap items-center gap-3">
            <div class="relative min-w-[280px] flex-1">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Ta'minotchi, kompaniya, telefon..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            </div>

            <select wire:model.live="statusFilter" class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                <option value="active">Faqat faollar</option>
                <option value="archived">Arxivlanganlar</option>
                <option value="all">Barchasi</option>
            </select>
        </div>

        <button wire:click="openCreateModal" class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow transition-all space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Yangi Ta'minotchi</span>
        </button>
    </div>

    <!-- Ta'minotchilar Jadvali -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Ta'minotchi / Zavod</th>
                        <th class="px-6 py-4">Telefon</th>
                        <th class="px-6 py-4">Manzil</th>
                        <th class="px-6 py-4">Bizning Majburiyat (Qarz)</th>
                        <th class="px-6 py-4">Holat</th>
                        <th class="px-6 py-4 text-right">Amallar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($suppliers as $supplier)
                        <tr class="hover:bg-slate-50/60 transition-colors {{ $supplier->status === 'archived' ? 'opacity-60 bg-slate-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">{{ $supplier->name }}</div>
                                @if ($supplier->company_name)
                                    <div class="text-xs text-blue-600 font-medium">«{{ $supplier->company_name }}»</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-mono text-xs">
                                {{ $supplier->phone ?: '—' }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-xs">
                                {{ $supplier->address ?: '—' }}
                            </td>
                            <td class="px-6 py-4">
                                @if ($supplier->balance > 0)
                                    <span class="font-bold text-amber-600">{{ number_format($supplier->balance, 0, '.', ' ') }} so'm</span>
                                @elseif ($supplier->balance < 0)
                                    <span class="font-bold text-emerald-600">Avansimiz: {{ number_format(abs($supplier->balance), 0, '.', ' ') }} so'm</span>
                                @else
                                    <span class="text-slate-400 font-medium">0 so'm</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($supplier->status === 'active')
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
                                <button wire:click="toggleArchive({{ $supplier->id }})" title="{{ $supplier->status === 'active' ? 'Arxivlash' : 'Faollashtirish' }}" class="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                </button>

                                <button wire:click="deleteSupplier({{ $supplier->id }})" wire:confirm="Rostdan ham ushbu ta'minotchini o'chirmoqchimisiz? Agar tarixda ishlatilgan bo'lsa tizim ruxsat bermaydi." title="O'chirish" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                <p class="font-medium text-slate-500">Ta'minotchilar topilmadi</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL: Yangi Ta'minotchi Qo'shish -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg">Yangi Ta'minotchi Qo'shish</h3>
                        <p class="text-xs text-slate-500">Zavod yoki yetkazib beruvchi kontragent</p>
                    </div>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="saveSupplier" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Mas'ul Shaxs / Ism <span class="text-rose-500">*</span>
                        </label>
                        <input wire:model="name" type="text" placeholder="Masalan: Sardor (Diler)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Kompaniya / Zavod Nomi
                        </label>
                        <input wire:model="companyName" type="text" placeholder="Masalan: Coca-Cola Ichimligi Uzbekiston" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Telefon Raqami
                            </label>
                            <input wire:model="phone" type="text" placeholder="+998901234567" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Manzil / Baza
                            </label>
                            <input wire:model="address" type="text" placeholder="Masalan: Sergeli ombori" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
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
</div>
