<x-layouts.app>
    <x-slot name="title">Sotuv — AquaOptom</x-slot>
    <x-slot name="header">Yangi sotuv</x-slot>
    <div class="trade-offline-link"><span>Internet yo‘q paytda oldindan ulangan qurilmadan soting.</span><a href="{{ route('pos.pwa') }}">Internetsiz sotuv oynasi →</a></div>
    <livewire:sales.optom-pos />
</x-layouts.app>
