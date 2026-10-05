@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'bg-rose-50 border border-rose-200 rounded-xl p-3.5 mb-4 text-xs text-rose-800']) }}>
        <div class="flex items-center gap-2 font-semibold text-rose-800 mb-1">
            <span>⚠</span>
            <span>Iltimos, kiritilgan ma'lumotlarni tekshiring:</span>
        </div>
        <ul class="list-disc list-inside space-y-0.5 pl-1 text-[11px] text-rose-800">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
