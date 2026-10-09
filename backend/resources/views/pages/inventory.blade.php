<x-layouts.app>
    <x-slot name="title">Ombor & Qoldiqlar — AquaOptom CRM</x-slot>
    <x-slot name="header">Ombor</x-slot>
    <x-workspace-guide icon="📦" title="Omborda nima va qancha bor?" description="Mahsulotni toping, donasini ko‘ring. Yangi mahsulot kelgan bo‘lsa «Kirim qilish»ni bosing." :steps="['Nom yoki litr bilan qidiring', 'Qoldiq va narxni ko‘ring', 'Kerak bo‘lsa narxni belgilang']" />

    <div class="space-y-6">
        @livewire('inventory.stock-manager')
    </div>
</x-layouts.app>
