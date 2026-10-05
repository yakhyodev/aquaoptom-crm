<x-layouts.app>
    <x-slot name="title">Qarzdorliklar va To'lovlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Qarzlar va to‘lovlar</x-slot>
    <x-workspace-guide icon="🤝" title="Qarzlar va to‘lovlar" description="Mijoz qarzini to‘ladimi yoki yetkazuvchiga pul bermoqchimisiz? Kerakli amalni tanlang." :steps="['Kerakli tomonni tanlang', 'Ism yoki do‘konni toping', 'Summani yozing va qolgan qarzni ko‘ring']" />

    <div class="space-y-6">
        <livewire:debts.debts-manager />
    </div>
</x-layouts.app>
