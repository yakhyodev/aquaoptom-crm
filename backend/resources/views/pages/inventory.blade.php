<x-layouts.app>
    <x-slot name="title">Ombor & Qoldiqlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Ombor</x-slot>
    <x-workspace-guide icon="📦" title="Omborda nima va qancha bor?" description="Avvaldan bor mahsulotlarni «Boshlang‘ich qoldiq» orqali kiriting. Keyin kelgan mahsulot uchun «Kirim qilish»ni bosing." :steps="['Avvaldan bor mahsulotlarni kiriting', 'Qoldiq va narxni ko‘ring', 'Yangi kelgan tovarni kirim qiling']" />

    <div class="space-y-6">
        @livewire('inventory.stock-manager')
    </div>
</x-layouts.app>
