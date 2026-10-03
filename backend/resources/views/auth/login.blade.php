<!DOCTYPE html>
<html lang="uz" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title>Tizimga kirish — AquaOptom CRM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-600 items-center justify-center text-white text-3xl shadow-lg shadow-cyan-500/30 mb-3">
                💧
            </div>
            <h1 class="text-xl font-black text-white tracking-tight">AquaOptom CRM</h1>
            <p class="text-xs text-slate-400 mt-1">Optom Suv va Ichimliklar Savdosi Boshqaruvi</p>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl">
            <h2 class="text-sm font-semibold text-white mb-5">Xodimlar uchun tizimga kirish</h2>

            <x-validation-errors />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-medium text-slate-300 mb-1.5">
                        Email yoki Telefon raqami
                    </label>
                    <input
                        type="text"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="owner@aquaoptom.uz yoki +99890..."
                        class="w-full px-3.5 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-medium text-slate-300">
                            Parol
                        </label>
                    </div>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full px-3.5 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                        <input
                            type="checkbox"
                            name="remember"
                            class="rounded border-slate-700 bg-slate-800 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
                        />
                        <span>Eslab qolish</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full py-2.5 px-4 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer"
                >
                    Tizimga kirish
                </button>
            </form>
        </div>

        <!-- Footer Notice -->
        <p class="text-center text-[11px] text-slate-500 mt-6">
            Birlamchi kirish uchun konsolda <code class="text-slate-400 bg-slate-900 px-1 py-0.5 rounded">php artisan app:bootstrap-owner</code> buyrug'idan foydalaning.
        </p>
    </div>
</body>
</html>
