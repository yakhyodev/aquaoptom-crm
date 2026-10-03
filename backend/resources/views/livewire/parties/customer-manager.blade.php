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
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Ism, do'kon nomi, telefon yoki manzil..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            </div>

            <select wire:model.live="statusFilter" class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                <option value="active">Faqat faollar</option>
                <option value="archived">Arxivlanganlar</option>
                <option value="all">Barchasi</option>
            </select>
        </div>

        <button wire:click="openCreateModal" class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow transition-all space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            <span>+ Yangi Mijoz</span>
        </button>
    </div>

    <!-- Mijozlar Jadvali -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Mijoz / Do'kon</th>
                        <th class="px-6 py-4">Telefon</th>
                        <th class="px-6 py-4">Manzil</th>
                        <th class="px-6 py-4">Joriy Hisob (Qarz)</th>
                        <th class="px-6 py-4">Kredit Limiti</th>
                        <th class="px-6 py-4">Holat</th>
                        <th class="px-6 py-4 text-right">Amallar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-slate-50/60 transition-colors {{ $customer->status === 'archived' ? 'opacity-60 bg-slate-50/40' : '' }}">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">{{ $customer->name }}</div>
                                @if ($customer->store_name)
                                    <div class="text-xs text-blue-600 font-medium">«{{ $customer->store_name }}»</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-mono text-xs">
                                {{ $customer->phone ?: '—' }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-xs">
                                {{ $customer->address ?: '—' }}
                            </td>
                            <td class="px-6 py-4">
                                @if ($customer->current_debt > 0)
                                    <span class="font-bold text-rose-600">{{ number_format($customer->current_debt, 0, '.', ' ') }} so'm</span>
                                @elseif ($customer->current_debt < 0)
                                    <span class="font-bold text-emerald-600">Avans: {{ number_format(abs($customer->current_debt), 0, '.', ' ') }} so'm</span>
                                @else
                                    <span class="text-slate-400 font-medium">0 so'm</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-xs">
                                {{ $customer->debt_limit > 0 ? number_format($customer->debt_limit, 0, '.', ' ') . " so'm" : 'Cheklovsiz' }}
                            </td>
                            <td class="px-6 py-4">
                                @if ($customer->status === 'active')
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
                                <button wire:click="toggleArchive({{ $customer->id }})" title="{{ $customer->status === 'active' ? 'Arxivlash' : 'Faollashtirish' }}" class="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                </button>

                                <button wire:click="deleteCustomer({{ $customer->id }})" wire:confirm="Rostdan ham ushbu mijozni o'chirmoqchimisiz? Agar tarixiy hisobda bo'lsa tizim ruxsat bermaydi." title="O'chirish" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <p class="font-medium text-slate-500">Mijozlar topilmadi</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL: Yangi Mijoz Qo'shish -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg">Yangi Mijoz Qo'shish</h3>
                        <p class="text-xs text-slate-500">Optom xaridor profilini yaratish</p>
                    </div>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="saveCustomer" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Mijoz Ismi / F.I.Sh <span class="text-rose-500">*</span>
                        </label>
                        <input wire:model="name" type="text" placeholder="Masalan: Akmal aka, Jasur" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Telefon Raqami (Ixtiyoriy)
                        </label>
                        <input wire:model.live.debounce.400ms="phone" type="text" placeholder="+998901234567" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        
                        @if ($phoneWarning)
                            <div class="mt-2 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-start space-x-1.5">
                                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>{{ $phoneWarning }}</span>
                            </div>
                        @endif
                        @error('phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Do'kon Nomi
                            </label>
                            <input wire:model="storeName" type="text" placeholder="Masalan: Bahor Market" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            @error('storeName') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Manzil / Mo'ljal
                            </label>
                            <input wire:model="address" type="text" placeholder="Masalan: Chilonzor 9" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            @error('address') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400">Telefon bo'lmaganda do'kon nomi yoki manzil kiritilishi shart.</p>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Qarz (Kredit) Limiti (so'm)
                        </label>
                        <input wire:model="debtLimit" type="number" placeholder="Ixtiyoriy, masalan: 5000000" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
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
