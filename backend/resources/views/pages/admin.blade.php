<x-layouts.app>
    <x-slot name="title">Admin panel — AquaOptom CRM</x-slot>
    <x-slot name="header">Admin panel — Foydalanuvchilar va do'kon sozlamalari</x-slot>
    <x-workspace-guide icon="⚙" title="Do‘konni o‘zingizga moslang" description="Do‘kon ma’lumotlari, xodimlar, ruxsatlar va ulangan qurilmalar." :steps="['Kerakli sozlamani tanlang', 'Ma’lumotlarni o‘zgartiring', 'Saqlashni bosing']" />

    <livewire:admin.admin-panel />
</x-layouts.app>
