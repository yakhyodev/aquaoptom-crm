<x-layouts.app>
    <x-slot name="title">Boshlang‘ich qoldiqlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Boshlang‘ich qoldiqlar (Hisob ochilishi)</x-slot>
    <x-workspace-guide icon="⚖" title="Oldindan bor hisoblarni kiriting" description="Do‘konda avvaldan bor mahsulot, pul va qarzlarni boshlang‘ich hisobga yozing." :steps="['Hisob turini tanlang', 'Haqiqiy qoldiqni yozing', 'Tekshirib tasdiqlang']" />

    <livewire:admin.opening-balances-manager />
</x-layouts.app>
