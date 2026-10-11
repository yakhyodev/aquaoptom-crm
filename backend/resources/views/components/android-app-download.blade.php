<aside x-data="{ android: /Android/i.test(navigator.userAgent), hidden: false }" x-init="try { hidden = localStorage.getItem('aqua-apk-dismissed') === '{{ now()->format('Y-m-d') }}' } catch (e) {}" x-show="android && !hidden" x-cloak class="apk-offer" aria-label="Android ilovasi">
    <span aria-hidden="true">📱</span>
    <div><strong>Telefonda qulayroq ishlang</strong><p>AquaOptom Android sinov ilovasini o‘rnating. Ma’lumotlar shu panel bilan sinxronlanadi.</p></div>
    <a href="{{ config('app.android_download_url') }}" class="trade-button trade-button-primary" download>APK yuklash ↓</a>
    <button type="button" aria-label="Taklifni yopish" @click="hidden = true; try { localStorage.setItem('aqua-apk-dismissed', '{{ now()->format('Y-m-d') }}') } catch (e) {}">×</button>
</aside>
