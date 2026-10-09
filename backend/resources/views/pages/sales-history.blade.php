<x-layouts.app>
    <x-slot name="title">Savdo Tarixi va Tahlili — AquaOptom CRM</x-slot>
    <x-slot name="header">Savdolar tarixi</x-slot>
    <x-workspace-guide icon="🕒" title="Oldingi savdolarni toping" description="Bu yerda amalga oshgan sotuvlar turadi. Yangi sotuv uchun «Sotuv qilish» bo‘limiga o‘ting." :steps="['Davrni tanlang', 'Mijoz yoki chekni qidiring', 'Sotuv tafsilotlarini oching']" />

    @livewire('sales.sales-history')
</x-layouts.app>
