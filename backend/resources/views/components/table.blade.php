@props(['headers' => [], 'emptyMessage' => 'Hali ma\'lumotlar kiritilmagan'])

<div class="overflow-x-auto w-full border border-slate-800 rounded-xl bg-slate-900/60">
    <table {{ $attributes->merge(['class' => 'w-full text-left text-xs text-slate-300']) }}>
        @if (!empty($headers))
            <thead class="bg-slate-800/80 text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-slate-800/70">
            {{ $slot }}
        </tbody>
    </table>
</div>
