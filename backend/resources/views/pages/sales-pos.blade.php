<x-layouts.app>
    <x-slot name="title">Sotuv — AquaOptom</x-slot>
    <x-slot name="header">Yangi sotuv</x-slot>
    <div class="trade-offline-link"><span>Internet uzilganda ham sotish kerakmi?</span><a href="{{ route('pos.pwa') }}">Offline Kassa (PWA) →</a></div>
    <livewire:sales.optom-pos />
</x-layouts.app>
