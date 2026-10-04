<x-layouts.app>
    <x-slot name="title">Sotuv (Yangi chek) — AquaOptom CRM</x-slot>
    <x-slot name="header">Sotuv — Yangi optom savdo operatsiyasi</x-slot>

    <div class="space-y-6">
        <!-- POS Mode Switcher Banner -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-xl shrink-0">
                    ⚡
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        Online / Offline Kassa Rejimlari
                        <span class="text-[10px] bg-emerald-950 text-emerald-400 border border-emerald-800 px-1.5 py-0.5 rounded font-mono">Livewire faol</span>
                    </h3>
                    <p class="text-xs text-slate-400">
                        Internet beqaror yoki oflayn ishlash uchun to'liq internetsiz ishlaydigan PWA kassa oynasiga o'ting.
                    </p>
                </div>
            </div>
            <a href="{{ route('pos.pwa') }}"
               class="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-md shadow-cyan-600/20 transition-all shrink-0">
                <span>🛒</span>
                <span>Offline Kassa (PWA)ga O'tish</span>
                <span>→</span>
            </a>
        </div>

        @livewire('optom-pos')
    </div>
</x-layouts.app>
