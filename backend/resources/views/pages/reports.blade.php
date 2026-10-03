<x-layouts.app>
    <x-slot name="title">Hisobotlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Hisobotlar — Savdo, foyda va pul tahlili</x-slot>

    <div class="space-y-6">
        <x-filter-bar searchPlaceholder="Hisobot turi bo'yicha saralash...">
            <input
                type="date"
                class="bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <input
                type="date"
                class="bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <button type="button" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700">
                Excel Eksport
            </button>
        </x-filter-bar>

        <x-card title="Moliyaviy va Savdo Hisobotlari" subtitle="Yalpi foyda, aylanma va rentabellik ko'rsatkichlari">
            <x-empty-state
                title="Hozircha davr hisoboti shakllanmagan"
                description="Ushbu bo'lim uchun to'liq tahliliy grafiklar va eksport mexanizmlari 17-bosqichda to'liq tatbiq etiladi."
                icon="📊"
            />
        </x-card>
    </div>
</x-layouts.app>
