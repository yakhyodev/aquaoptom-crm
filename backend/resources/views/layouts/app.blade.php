<!DOCTYPE html>
<html lang="uz">
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
<body class="app-shell min-h-screen font-sans antialiased flex flex-col md:flex-row">

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
        <div class="app-brand p-4 border-b border-slate-800 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white text-lg shadow-md shadow-cyan-500/20">
                    💧
                </div>
                <div>
                    <h1 class="text-base font-black tracking-tight text-white leading-tight">AquaOptom CRM</h1>
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
        <nav class="app-nav flex-1 overflow-y-auto p-3" aria-label="Do‘kon bo‘limlari">
            <a href="{{ route('dashboard') }}" class="app-nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"><span>◈</span><span>Bosh sahifa<small>Do‘konning hozirgi holati</small></span></a>
            <p class="app-nav-group">Har kuni ishlatiladi</p>
            <a href="{{ route('sales.pos') }}" class="app-nav-link {{ request()->routeIs('sales.pos') ? 'is-active' : '' }}"><span>🛒</span><span>Sotuv qilish<small>Tezkor yoki mijozga</small></span></a>
            <a href="{{ route('inventory.inward') }}" class="app-nav-link {{ request()->routeIs('inventory.inward') ? 'is-active' : '' }}"><span>📥</span><span>Mahsulot kirimi<small>Kelgan mahsulotni yozish</small></span></a>
            <a href="{{ route('inventory.index') }}" class="app-nav-link {{ request()->routeIs('inventory.index') ? 'is-active' : '' }}"><span>📦</span><span>Ombor<small>Nima va qancha bor?</small></span></a>
            <a href="{{ route('debts.index') }}" class="app-nav-link {{ request()->routeIs('debts.index') ? 'is-active' : '' }}"><span>🤝</span><span>Qarzlar va to‘lovlar<small>Kimga va qancha qarz?</small></span></a>
            @can('view_cash')
                <a href="{{ route('cash.index') }}" class="app-nav-link {{ request()->routeIs('cash.index') ? 'is-active' : '' }}"><span>💵</span><span>Kassa va xarajatlar<small>Pul kirimi, chiqimi, qoldiq</small></span></a>
            @endcan
            <p class="app-nav-group">Natijalarni ko‘rish</p>
            <a href="{{ route('sales.history') }}" class="app-nav-link {{ request()->routeIs('sales.history') ? 'is-active' : '' }}"><span>🕒</span><span>Savdolar tarixi<small>Oldingi sotuv va cheklar</small></span></a>
            @can('view_reports')
                <a href="{{ route('reports.index') }}" class="app-nav-link {{ request()->routeIs('reports.index') ? 'is-active' : '' }}"><span>📈</span><span>Hisobotlar<small>Savdo, foyda va pul</small></span></a>
            @endcan
            @if(auth()->user()?->hasRole(['OWNER', 'ADMIN']))
                <a href="{{ route('admin.index') }}" class="app-nav-link {{ request()->routeIs('admin.*') ? 'is-active' : '' }}"><span>⚙</span><span>Sozlamalar<small>Xodimlar va qurilmalar</small></span></a>
            @endif
            <a href="{{ route('guide') }}" class="app-nav-link app-nav-help {{ request()->routeIs('guide') ? 'is-active' : '' }}"><span>?</span><span>Qanday ishlaydi?<small>Do‘kon xaritasi va yordam</small></span></a>
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
                                {{ match(auth()->user()->role) { 'OWNER' => 'Do‘kon egasi', 'ADMIN' => 'Administrator', 'CASHIER' => 'Kassir', 'SALES_MANAGER' => 'Sotuvchi', 'WAREHOUSE_MANAGER' => 'Omborchi', default => 'Xodim' } }}
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
        <header class="app-topbar px-4 py-3 sticky top-0 z-30 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    onclick="toggleMobileSidebar()"
                    class="app-menu-button md:hidden p-2 rounded-xl"
                    aria-label="Menyu"
                >
                    ☰
                </button>
                <div class="app-breadcrumb">
                    {{ $header ?? 'AquaOptom Boshqaruv Markazi' }}
                </div>
            </div>

            <div class="app-header-tools">
                @can('view_cash')<livewire:cash.balance-summary />@endcan
                @if(config('app.preview_mode') && app()->environment(['local', 'staging', 'testing']))<span class="app-test-badge">Sinov rejimi</span>@endif
                <span class="app-connection" x-data="{ online: navigator.onLine }" @online.window="online = true" @offline.window="online = false"><i :class="online ? 'is-online' : 'is-offline'"></i><span x-text="online ? 'Internet bor' : 'Internet yo‘q'"></span></span>
                <a href="{{ route('guide') }}" class="app-help-button" aria-label="Do‘kon xaritasini ochish">?</a>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="app-main flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
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
                document.body.style.overflow = 'hidden';
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
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
