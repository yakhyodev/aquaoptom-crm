@props(['title' => null, 'subtitle' => null, 'actions' => null])

<div {{ $attributes->merge(['class' => 'bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden']) }}>
    @if ($title || $actions)
        <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($title)
                    <h3 class="text-sm font-semibold text-slate-900 tracking-tight">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-slate-600 mt-0.5">{{ $subtitle }}</p>
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
