@props(['headers' => [], 'emptyMessage' => 'Hali ma\'lumotlar kiritilmagan'])

<div class="overflow-x-auto w-full border border-slate-200 rounded-xl bg-white/60">
    <table {{ $attributes->merge(['class' => 'w-full text-left text-xs text-slate-700']) }}>
        @if (!empty($headers))
            <thead class="bg-slate-50 text-[11px] font-semibold text-slate-600  border-b border-slate-200">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-slate-200">
            {{ $slot }}
        </tbody>
    </table>
</div>
