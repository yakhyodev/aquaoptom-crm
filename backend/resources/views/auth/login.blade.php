<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kirish — AquaOptom</title>
    <meta name="theme-color" content="#f4f6fa">
    <x-theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-screen">
    <div class="auth-theme-control"><x-theme-toggle /></div>
    @php($isPreview = config('app.preview_mode') && app()->environment(['local', 'staging', 'testing']))
    <main class="auth-shell">
        <div class="auth-intro"><div class="auth-brand"><span class="auth-logo">💧</span>AquaOptom</div><h1>Do‘kon ishlari.<br>Endi ancha oson.</h1><p>Mahsulot kirimi, savdo va mijozlar hisobi — hammasi bir joyda.</p><div class="auth-features"><span>📦 Ombor</span><span>🛒 Sotuv</span><span>📊 Hisobotlar</span></div></div>
        <section class="auth-card">
            <h2>Xush kelibsiz!</h2><p>Davom etish uchun panelga kiring.</p>
            <x-validation-errors />
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="trade-field"><label for="email">Login</label><input id="email" type="text" name="email" value="{{ old('email', $isPreview ? 'admin' : '') }}" placeholder="{{ $isPreview ? 'admin' : 'Email yoki telefon raqami' }}" required autofocus autocomplete="username"></div>
                <div class="trade-field"><label for="password">Parol</label><input id="password" type="password" name="password" placeholder="Parolni kiriting" required autocomplete="current-password"></div>
                <label class="auth-check"><input type="checkbox" name="remember"><span>Meni eslab qolish</span></label>
                <button type="submit">Panelga kirish →</button>
            </form>
            @if ($isPreview)<div class="auth-demo">Test paneli uchun<br>Login: <b>admin</b> · Parol: <b>admin1</b></div>@endif
            <x-android-app-download />
        </section>
    </main>
</body>
</html>
