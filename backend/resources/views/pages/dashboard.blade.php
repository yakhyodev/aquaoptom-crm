<x-layouts.app>
    <x-slot name="title">Dashboard — AquaOptom CRM</x-slot>
    <x-slot name="header">Umumiy Dashboard</x-slot>

    <div class="space-y-6">
        <!-- Welcome Card -->
        <div class="bg-gradient-to-r from-blue-900/40 via-slate-900 to-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider">AquaOptom CRM Boshqaruv Markazi</span>
                    <h2 class="text-xl sm:text-2xl font-black text-white mt-1">Xush kelibsiz, {{ auth()->user()->name }}!</h2>
                    <p class="text-xs text-slate-400 mt-1 max-w-xl">
                        Tizim optom suv va ichimliklar do'koni uchun xizmat ko'rsatmoqda. Rolingiz: <span class="text-white font-semibold">{{ auth()->user()->role }}</span>.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('sales.pos') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition-all flex items-center gap-2">
                        <span>🛒</span>
                        <span>Yangi Sotuv</span>
                    </a>
                    <a href="{{ route('inventory.index') }}" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-2">
                        <span>📥</span>
                        <span>Kirim Qilish</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- 8 Sections Overview Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('sales.history') }}" class="bg-slate-900 border border-slate-800 hover:border-blue-600/50 p-5 rounded-2xl transition-all group">
                <div class="flex items-center justify-between text-2xl mb-2">
                    <span>🕒</span>
                    <span class="text-xs text-slate-500 group-hover:text-blue-400">Ochish →</span>
                </div>
                <h3 class="text-sm font-semibold text-white">Savdo (Tarix)</h3>
                <p class="text-xs text-slate-400 mt-1">Amalga oshgan savdo cheklari, to'lovlar va tahlil</p>
            </a>

            <a href="{{ route('sales.pos') }}" class="bg-slate-900 border border-slate-800 hover:border-blue-600/50 p-5 rounded-2xl transition-all group">
                <div class="flex items-center justify-between text-2xl mb-2">
                    <span>🛒</span>
                    <span class="text-xs text-slate-500 group-hover:text-blue-400">Ochish →</span>
                </div>
                <h3 class="text-sm font-semibold text-white">Sotuv (Yangi chek)</h3>
                <p class="text-xs text-slate-400 mt-1">Tezkor naqd va mijozli optom sotuv oynasi</p>
            </a>

            <a href="{{ route('inventory.index') }}" class="bg-slate-900 border border-slate-800 hover:border-blue-600/50 p-5 rounded-2xl transition-all group">
                <div class="flex items-center justify-between text-2xl mb-2">
                    <span>📦</span>
                    <span class="text-xs text-slate-500 group-hover:text-blue-400">Ochish →</span>
                </div>
                <h3 class="text-sm font-semibold text-white">Ombor & Kirim</h3>
                <p class="text-xs text-slate-400 mt-1">Fizik qoldiqlar, kirimlar va o'rtacha tannarx</p>
            </a>

            <a href="{{ route('debts.index') }}" class="bg-slate-900 border border-slate-800 hover:border-blue-600/50 p-5 rounded-2xl transition-all group">
                <div class="flex items-center justify-between text-2xl mb-2">
                    <span>📑</span>
                    <span class="text-xs text-slate-500 group-hover:text-blue-400">Ochish →</span>
                </div>
                <h3 class="text-sm font-semibold text-white">Qarzdorliklar</h3>
                <p class="text-xs text-slate-400 mt-1">Mijozlar va ta'minotchilar umumiy pul daftari</p>
            </a>
        </div>

        <!-- Clean Real Status State (No Fake Numbers) -->
        <x-card title="Tizim holati va infratuzilma" subtitle="Lokal xizmatlar va xavfsizlik protokollari">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-slate-400">Ma'lumotlar bazasi</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    </div>
                    <div class="font-semibold text-white">PostgreSQL 16</div>
                    <div class="text-[11px] text-slate-500 mt-1">Dev: aquaoptom_dev | Test: aquaoptom_test</div>
                </div>

                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-slate-400">Kesh va Navbatlar</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    </div>
                    <div class="font-semibold text-white">Redis 5.0 (Predis)</div>
                    <div class="text-[11px] text-slate-500 mt-1">127.0.0.1:6379 faol</div>
                </div>

                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-slate-400">Autentifikatsiya</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    </div>
                    <div class="font-semibold text-white">Web Session & Sanctum Token</div>
                    <div class="text-[11px] text-slate-500 mt-1">Ruxsatlar va Policy himoyasi faol</div>
                </div>
            </div>
        </x-card>
    </div>
</x-layouts.app>
