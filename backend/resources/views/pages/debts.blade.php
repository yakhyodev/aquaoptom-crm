<x-layouts.app>
    <x-slot name="title">Qarzdorliklar va To'lovlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Qarzlar va to‘lovlar</x-slot>
    <x-workspace-guide icon="🤝" title="Qarzlar va to‘lovlar" description="Mijozlarning bizga qarzi va bizning yetkazuvchilarga qarzimiz alohida." :steps="['Kerakli tomonni tanlang', 'Ism yoki do‘konni toping', 'To‘lovni yozing yoki hisob tarixini oching']" />

    <div class="space-y-6">
        <livewire:debts.debts-manager />
    </div>
</x-layouts.app>
