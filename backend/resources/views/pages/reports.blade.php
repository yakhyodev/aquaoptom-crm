<x-layouts.app>
    <x-slot name="title">Tahlil va Hisobotlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Hisobotlar — Savdo, foyda va pul tahlili</x-slot>
    <x-workspace-guide icon="📈" title="Do‘kon qanday ishlayapti?" description="Sotilgan mahsulot, kelgan pul va foydani alohida ko‘ring. Sana filtri davriy natijalarga tegishli." :steps="['Davrni tanlang', 'Hisobot turini tanlang', 'Natijani ko‘ring yoki yuklang']" />

    @livewire('reports.report-dashboard')
</x-layouts.app>
