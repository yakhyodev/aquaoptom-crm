<x-layouts.app>
    <x-slot name="title">Qarzdorliklar — AquaOptom CRM</x-slot>
    <x-slot name="header">Qarzdorliklar — Signed hisob daftari</x-slot>

    <div class="space-y-6">
        <x-filter-bar searchPlaceholder="Mijoz yoki ta'minotchi bo'yicha qidirish...">
            <select class="bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="all">Barcha qarzdorlar</option>
                <option value="customers">Faqat mijozlar qarzi (Bizga berishi kerak)</option>
                <option value="suppliers">Faqat ta'minotchilar (Biz berishimiz kerak)</option>
            </select>
        </x-filter-bar>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Mijozlar qarzi -->
            <x-card title="Mijozlar hisob daftari" subtitle="Musbat = qarz, Manfiy = avans (max(0) bilan yo'qotilmaydi)">
                @php
                    $customers = \App\Models\Customer::latest()->take(10)->get();
                @endphp

                @if ($customers->isEmpty())
                    <x-empty-state
                        title="Hozircha mijozlar mavjud emas"
                        description="Mijozli sotuv amalga oshirilganda bu yerda avtomatik signed balans shakllanadi."
                        icon="👥"
                    />
                @else
                    <x-table :headers="['Mijoz', 'Telefon', 'Umumiy qoldiq (UZS)', 'Amal']">
                        @foreach ($customers as $c)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-semibold text-white">{{ $c->name }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $c->phone ?? '-' }}</td>
                                <td class="px-4 py-3 font-mono font-bold text-slate-200">
                                    0 so'm
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="text-xs text-blue-400 hover:text-blue-300">
                                        Ko'chirma
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>

            <!-- Ta'minotchilar qarzi -->
            <x-card title="Ta'minotchilar oldidagi majburiyatimiz" subtitle="Tovar kirimi bo'yicha to'lanishi lozim bo'lgan summalar">
                @php
                    $suppliers = \App\Models\Supplier::latest()->take(10)->get();
                @endphp

                @if ($suppliers->isEmpty())
                    <x-empty-state
                        title="Hozircha ta'minotchilar mavjud emas"
                        description="Tovar kirimi qilinganda ta'minotchi majburiyati shakllanadi."
                        icon="🏭"
                    />
                @else
                    <x-table :headers="['Ta\'minotchi', 'Telefon', 'Bizning qarz (UZS)', 'Amal']">
                        @foreach ($suppliers as $s)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-semibold text-white">{{ $s->name }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $s->phone ?? '-' }}</td>
                                <td class="px-4 py-3 font-mono font-bold text-slate-200">
                                    0 so'm
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="text-xs text-blue-400 hover:text-blue-300">
                                        To'lash
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
        </div>

        <!-- Mijozlarni to'liq boshqarish va qidiruv -->
        <div class="mt-8">
            <h3 class="text-base font-bold text-slate-800 mb-3">Mijozlar Katalogi va Qarz Cheklovlari</h3>
            <livewire:parties.customer-manager />
        </div>
    </div>
</x-layouts.app>
