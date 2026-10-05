<x-layouts.app>
    <x-slot name="title">Kassa va xarajatlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Kassa va xarajatlar — Pul harakati va kun yakuni</x-slot>
    <x-workspace-guide icon="💵" title="Kassada qancha pul bor?" description="Naqd pul, karta va bankdagi mablag‘ni ko‘ring. Har chiqimdan keyin qolgan pul shu yerda yangilanadi." :steps="['Qolgan pulni ko‘ring', 'Xarajat yoki boshqa amalni tanlang', 'Summani yozing va tasdiqlang']" />

    <div class="space-y-6">
        <livewire:cash.cash-manager />
    </div>
</x-layouts.app>
