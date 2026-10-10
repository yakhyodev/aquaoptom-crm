@props(['options', 'model', 'id', 'label', 'placeholder' => 'Nomini yozib qidiring', 'createEvent' => null, 'createValue' => null])
<div class="trade-field relative" wire:key="{{ $id }}-{{ md5(json_encode($options)) }}" x-data="aquaSearchSelect(@js($options), $wire.entangle('{{ $model }}').live)" @click.outside="open = false" @keydown.escape.prevent.stop="open = false">
    <label for="{{ $id }}">{{ $label }}</label>
    <div class="flex gap-2">
        <input id="{{ $id }}" type="search" role="combobox" autocomplete="off" aria-autocomplete="list" aria-controls="{{ $id }}-results" :aria-expanded="open" :aria-activedescendant="open && results.length ? '{{ $id }}-option-' + active : null" :value="open ? query : currentLabel" placeholder="{{ $placeholder }}" @focus="open = true; query = ''; active = 0" @input="query = $event.target.value; open = true; active = 0" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="if (open && results[active]) choose(results[active])" @keydown.tab="open = false">
        <button type="button" x-show="selected" @click="clear(); $el.previousElementSibling.focus()" class="trade-button trade-button-secondary" aria-label="{{ $label }} tanlovini tozalash">×</button>
    </div>
    <div x-show="open" x-cloak x-transition class="absolute top-full left-0 right-0 z-30 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-300 bg-white shadow-xl" role="listbox" id="{{ $id }}-results">
        <template x-for="(item, index) in results" :key="item.value"><button type="button" role="option" :id="'{{ $id }}-option-' + index" :aria-selected="String(selected) === String(item.value)" @click="choose(item)" @mouseenter="active = index" class="block w-full px-3 py-3 text-left text-sm border-b border-slate-100" :class="active === index ? 'bg-blue-50 text-blue-700' : 'text-slate-900'" x-text="item.label"></button></template>
        <p x-show="!results.length" class="p-3 text-sm text-slate-600">Topilmadi. Boshqa nom yoki telefon bilan qidiring.</p>
        <p x-show="matches.length > 20" class="p-3 text-xs text-slate-600">Birinchi 20 ta natija. Nomini aniqroq yozing.</p>
        @if($createEvent)<button type="button" @click="open = false; $dispatch('{{ $createEvent }}')" class="block w-full p-3 text-left text-sm font-bold text-blue-700">+ Ro‘yxatda yo‘q — yangisini qo‘shish</button>@endif
        @if($createValue)<button type="button" @click="choose({value: @js($createValue)})" class="block w-full p-3 text-left text-sm font-bold text-blue-700">+ Yangi mahsulot nomini yozish</button>@endif
    </div>
</div>
