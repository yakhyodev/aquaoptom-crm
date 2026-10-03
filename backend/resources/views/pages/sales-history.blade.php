<x-layouts.app>
    <x-slot name="title">Savdo (Tarix) — AquaOptom CRM</x-slot>
    <x-slot name="header">Savdo — Amalga oshgan savdolar tarixi va tahlili</x-slot>

    <div class="space-y-6">
        <x-filter-bar searchPlaceholder="Chek raqami yoki mijoz bo'yicha qidirish...">
            <select class="bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">Barcha to'lov turlari</option>
                <option value="CASH">Naqd pul</option>
                <option value="CARD">Karta / Terminal</option>
                <option value="DEBT">Nasiya (Qarz)</option>
                <option value="BANK">Bank hisobi</option>
            </select>
            <input
                type="date"
                class="bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
        </x-filter-bar>

        <x-card title="Savdo operatsiyalari ro'yxati" subtitle="Tasdiqlangan barcha savdo hujjatlari">
            @php
                $sales = \App\Models\Sale::with(['customer', 'items'])->latest()->take(20)->get();
            @endphp

            @if ($sales->isEmpty())
                <x-empty-state
                    title="Hozircha savdolar mavjud emas"
                    description="Birinchi savdoni rasmiylashtirish uchun «Sotuv (Yangi chek)» bo'limiga o'ting."
                    icon="🛒"
                >
                    <a href="{{ route('sales.pos') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-md transition-all">
                        <span>+ Yangi savdo ochish</span>
                    </a>
                </x-empty-state>
            @else
                <x-table :headers="['Chek №', 'Sana', 'Mijoz', 'To\'lov turi', 'Summa (UZS)', 'Holat', 'Amallar']">
                    @foreach ($sales as $sale)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3 font-mono font-semibold text-white">{{ $sale->invoice_number }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $sale->created_at->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ $sale->customer?->name ?? 'Tezkor xaridor' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                    {{ $sale->payment_type === 'CASH' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : '' }}
                                    {{ $sale->payment_type === 'DEBT' ? 'bg-amber-950 text-amber-400 border border-amber-800' : '' }}
                                    {{ $sale->payment_type === 'CARD' ? 'bg-blue-950 text-blue-400 border border-blue-800' : '' }}
                                    {{ $sale->payment_type === 'BANK' ? 'bg-purple-950 text-purple-400 border border-purple-800' : '' }}">
                                    {{ $sale->payment_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono font-bold text-white">{{ number_format($sale->total_amount, 0, '.', ' ') }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">
                                    {{ $sale->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" class="text-blue-400 hover:text-blue-300 font-semibold text-xs">
                                    Ko'rish
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>
</x-layouts.app>
