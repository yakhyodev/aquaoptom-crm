@props([
    'title' => 'Hali operatsiyalar mavjud emas',
    'description' => 'Ushbu bo\'lim uchun boshlang\'ich ma\'lumotlar navbatdagi bosqichlarda yaratiladi.',
    'icon' => '📦'
])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-4 border border-dashed border-slate-200 rounded-2xl bg-white/30']) }}>
    <div class="text-4xl mb-3">{{ $icon }}</div>
    <h4 class="text-sm font-semibold text-slate-900 tracking-tight">{{ $title }}</h4>
    <p class="text-xs text-slate-600 max-w-sm mx-auto mt-1">{{ $description }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</div>
