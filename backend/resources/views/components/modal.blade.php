@props(['id', 'title' => null, 'maxWidth' => 'md'])

@php
$widthClasses = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
][$maxWidth] ?? 'max-w-md';
@endphp

<div
    id="{{ $id }}"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden"
    role="dialog"
    aria-modal="true"
>
    <div class="relative w-full {{ $widthClasses }} bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden">
        @if ($title)
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">{{ $title }}</h3>
                <button
                    type="button"
                    onclick="document.getElementById('{{ $id }}').classList.add('hidden')"
                    class="text-slate-400 hover:text-white text-lg leading-none p-1 rounded-lg hover:bg-slate-800"
                >
                    &times;
                </button>
            </div>
        @endif

        <div class="p-5">
            {{ $slot }}
        </div>
    </div>
</div>
