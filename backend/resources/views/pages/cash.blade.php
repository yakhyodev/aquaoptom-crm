<x-layouts.app>
    <x-slot name="title">Kassa va xarajatlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Kassa va xarajatlar</x-slot>
    <x-workspace-guide icon="💵" title="Kassada qancha pul bor?" description="Bitta kassa: pul qo‘shish, xarajat yozish va qolgan pulni ko‘rish." :steps="['Qolgan pulni ko‘ring', 'Xarajat yoki boshqa amalni tanlang', 'Summani yozing va tasdiqlang']" />

    <div class="space-y-6">
        <livewire:cash.cash-manager />
    </div>
</x-layouts.app>
