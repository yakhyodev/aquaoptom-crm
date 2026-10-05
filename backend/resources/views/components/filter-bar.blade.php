@props(['searchPlaceholder' => 'Qidirish...'])

<div {{ $attributes->merge(['class' => 'bg-white border border-slate-200 p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 mb-5']) }}>
    <div class="flex-1 min-w-[200px]">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs">
                🔍
            </span>
            <input
                type="text"
                {{ $attributes->whereStartsWith('wire:model') }}
                placeholder="{{ $searchPlaceholder }}"
                class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            />
        </div>
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
