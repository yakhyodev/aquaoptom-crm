<!DOCTYPE html>
<html lang="uz" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AquaOptom CRM — Optom Suv Do\'koni Boshqaruvi' }}</title>

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0284c7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AquaOptom">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <!-- Local Built CSS & JS via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans antialiased flex flex-col md:flex-row">

    <!-- Mobile Drawer Overlay -->
    <div
        id="mobile-overlay"
        onclick="toggleMobileSidebar()"
        class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm hidden md:hidden"
    ></div>

    <!-- Sidebar Navigation (Desktop Fixed + Mobile Off-canvas) -->
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 flex flex-col transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0"
    >
        <!-- Brand Logo -->
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white text-lg shadow-md shadow-cyan-500/20">
                    💧
                </div>
                <div>
                    <h1 class="text-sm font-black tracking-tight text-white leading-tight">AquaOptom CRM</h1>
                    <span class="text-[10px] text-cyan-400 font-semibold uppercase tracking-wider">Optom Ichimliklar</span>
                </div>
            </a>
            <button
                type="button"
                onclick="toggleMobileSidebar()"
                class="md:hidden text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800"
            >
                ✕
            </button>
        </div>

        <!-- Navigation Links (8 Sakkizta Rasmiy Menyu) -->
        <nav class="flex-1 overflow-y-auto p-3 space-y-1 text-xs font-medium">
            <!-- 1. Dashboard -->
            <a
                href="{{ route('dashboard') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
            >
                <span class="text-base">📊</span>
                <span>Dashboard</span>
            </a>

            <!-- 2. Savdo (Tarix va tahlil) -->
            <a
                href="{{ route('sales.history') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('sales.history') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
            >
                <span class="text-base">🕒</span>
                <span>Savdo (Tarix)</span>
            </a>

            <!-- 3. Sotuv (Yangi operatsiya) -->
            <a
                href="{{ route('sales.pos') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('sales.pos') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
            >
                <span class="text-base">🛒</span>
                <span>Sotuv (Yangi chek)</span>
            </a>

            <!-- 4. Ombor -->
            <a
                href="{{ route('inventory.index') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('inventory.index') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
            >
                <span class="text-base">📦</span>
                <span>Ombor & Kirim</span>
            </a>

            <!-- 5. Qarzdorliklar -->
            <a
                href="{{ route('debts.index') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('debts.index') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
            >
                <span class="text-base">📑</span>
                <span>Qarzdorliklar</span>
            </a>

            <!-- 6. Hisobotlar (Ruxsatli) -->
            @can('view_reports')
                <a
                    href="{{ route('reports.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('reports.index') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                >
                    <span class="text-base">📈</span>
                    <span>Hisobotlar</span>
                </a>
            @endcan

            <!-- 7. Kassa va xarajatlar (Ruxsatli) -->
            @if(auth()->user()?->hasPermission('view_cash') || auth()->user()?->hasRole(['OWNER', 'CASHIER']))
                <a
                    href="{{ route('cash.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('cash.index') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                >
                    <span class="text-base">💵</span>
                    <span>Kassa va xarajatlar</span>
                </a>
            @endif

            <!-- 8. Admin panel (Faqat Owner va Admin) -->
            @if(auth()->user()?->hasRole(['OWNER', 'ADMIN']))
                <div class="pt-2">
                    <span class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Tizim</span>
                    <a
                        href="{{ route('admin.index') }}"
                        class="mt-1 flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('admin.index') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                    >
                        <span class="text-base">⚙️</span>
                        <span>Admin panel</span>
                    </a>
                </div>
            @endif
        </nav>

        <!-- User Profile & Logout -->
        <div class="p-3 border-t border-slate-800 bg-slate-900/60">
            @auth
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[9px] px-1.5 py-0.5 rounded-md font-mono font-bold uppercase
                                {{ auth()->user()->isOwner() ? 'bg-amber-950 text-amber-300 border border-amber-800' : 'bg-blue-950 text-blue-300 border border-blue-800' }}">
                                {{ auth()->user()->role }}
                            </span>
                            @if(auth()->user()->can('view_cost_price'))
                                <span class="text-[9px] text-emerald-400" title="Tannarx ko'rish ruxsati bor">👁️</span>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            title="Tizimdan chiqish"
                            class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors"
                        >
                            🚪
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </aside>

    <!-- Main View Wrapper -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
        <!-- Top App Bar (Mobile Header & Breadcrumbs) -->
        <header class="bg-slate-900/80 border-b border-slate-800 px-4 py-3 sticky top-0 z-30 backdrop-blur-md flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    onclick="toggleMobileSidebar()"
                    class="md:hidden p-2 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800"
                    aria-label="Menyu"
                >
                    ☰
                </button>
                <div class="text-xs font-semibold text-slate-300">
                    {{ $header ?? 'AquaOptom Boshqaruv Markazi' }}
                </div>
            </div>

            <!-- Header Status Badges -->
            <div class="flex items-center gap-2">
                <div class="hidden sm:flex items-center gap-1.5 bg-slate-800/60 border border-slate-700/50 px-2.5 py-1 rounded-full text-[11px] text-slate-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>PostgreSQL & Redis faol</span>
                </div>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
            <x-validation-errors />
            {{ $slot }}
        </main>
    </div>

    <!-- Mobile Sidebar Toggle Script -->
    <script>
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isHidden = sidebar.classList.contains('-translate-x-full');

            if (isHidden) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }
    </script>

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').then((reg) => {
                    console.log('AquaOptom SW registered:', reg.scope);
                }).catch((err) => {
                    console.warn('AquaOptom SW registration error:', err);
                });
            });
        }
    </script>
</body>
</html>
