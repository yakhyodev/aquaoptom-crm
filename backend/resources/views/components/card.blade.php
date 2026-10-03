@props(['title' => null, 'subtitle' => null, 'actions' => null])

<div {{ $attributes->merge(['class' => 'bg-slate-900 border border-slate-800 rounded-2xl shadow-sm overflow-hidden']) }}>
    @if ($title || $actions)
        <div class="px-5 py-4 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($title)
                    <h3 class="text-sm font-semibold text-white tracking-tight">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-slate-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($actions)
                <div class="flex items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="p-5">
        {{ $slot }}
    </div>
</div>
