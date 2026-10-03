<div class="space-y-6">
    <!-- Notifications -->
    @if ($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-800 text-emerald-300 text-sm flex items-center justify-between shadow-lg">
            <div class="flex items-center gap-2">
                <span>✔</span>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-xl bg-rose-950/80 border border-rose-800 text-rose-300 text-sm flex items-center justify-between shadow-lg">
            <div class="flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ $errorMessage }}</span>
            </div>
            <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-white">&times;</button>
        </div>
    @endif

    <!-- Stock and Reservation Overview (Section 19.4 Visual Rule: 100 dona PC60/phone30/free10) -->
    <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>📦</span>
                    <span>Fizik ombor qoldig'i va Sotish huquqi rezervlari</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    100 dona qoidasi: Jami ombordagi tovar = Qurilmalarga ajratilgan rezervlar + Erkin sotish mumkin bo'lgan qoldiq.
                </p>
            </div>
            <button
                wire:click="openRegisterModal"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 self-start sm:self-auto"
            >
                <span>📱</span>
                <span>+ Yangi qurilma</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse ($stockBreakdown as $item)
                <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-sm text-white flex items-center justify-between">
                            <span>{{ $item['variant']->product->name }}</span>
                            <span class="text-xs font-mono text-cyan-400">{{ $item['variant']->volume->name }}</span>
                        </div>
                        <div class="mt-2 text-xs space-y-1">
                            <div class="flex justify-between text-slate-300">
                                <span>Omborda jami:</span>
                                <span class="font-mono font-bold text-white">{{ number_format($item['total_on_hand']) }} dona</span>
                            </div>
                            <div class="flex justify-between text-amber-400">
                                <span>Qurilmalarga rezerv:</span>
                                <span class="font-mono font-bold">{{ number_format($item['total_reserved']) }} dona</span>
                            </div>
                            <div class="flex justify-between text-emerald-400 border-t border-slate-800/80 pt-1">
                                <span>Erkin qoldiq (Free):</span>
                                <span class="font-mono font-bold">{{ number_format($item['free_stock']) }} dona</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center text-xs text-slate-500 py-3">Tovar variantlari mavjud emas</div>
            @endforelse
        </div>
    </div>

    <!-- Registered Devices Table & Cards -->
    <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>📱</span>
                    <span>Ro'yxatdan o'tgan qurilmalar va Offline holati</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Qurilmalar oxirgi aloqa vaqti, ruxsat guvohnomasi (lease) va faol ajratmalar.</p>
            </div>
            <span class="text-xs font-mono text-slate-400 bg-slate-800 px-2.5 py-1 rounded-lg">
                Jami: {{ $devices->count() }} ta qurilma
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 font-semibold bg-slate-950/40">
                        <th class="p-3">Qurilma / Kod</th>
                        <th class="p-3">Turi / Mas'ul</th>
                        <th class="p-3">Holat</th>
                        <th class="p-3">Oxirgi aloqa</th>
                        <th class="p-3">Ruxsat (Lease)</th>
                        <th class="p-3">Tovar rezervi</th>
                        <th class="p-3">Kredit rezervi</th>
                        <th class="p-3 text-right">Amallar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse ($devices as $dev)
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="p-3">
                                <div class="font-bold text-white text-sm">{{ $dev->name }}</div>
                                <div class="text-[11px] font-mono text-cyan-400">{{ $dev->device_code }}</div>
                                <div class="text-[10px] text-slate-500 font-mono truncate max-w-[140px]">{{ $dev->device_uuid }}</div>
                            </td>
                            <td class="p-3">
                                <div class="font-semibold text-slate-200">{{ $dev->device_type }}</div>
                                <div class="text-[11px] text-slate-400">
                                    {{ $dev->assignedUser ? $dev->assignedUser->name : 'Biriktirilmagan' }}
                                </div>
                            </td>
                            <td class="p-3">
                                @if ($dev->status === 'ACTIVE')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">
                                        Faol
                                    </span>
                                @elseif ($dev->status === 'REVOKED')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-950 text-rose-400 border border-rose-800">
                                        Bloklangan
                                    </span>
                                @elseif ($dev->status === 'LOST')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">
                                        Yo'qolgan
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400">
                                        {{ $dev->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-300">
                                @if ($dev->last_seen_at)
                                    <div>{{ $dev->last_seen_at->timezone('Asia/Tashkent')->format('d.m.Y H:i') }}</div>
                                    <div class="text-[10px] text-slate-500">{{ $dev->last_seen_at->diffForHumans() }}</div>
                                    @if ($dev->last_ip)
                                        <div class="text-[10px] text-slate-600 font-mono">{{ $dev->last_ip }}</div>
                                    @endif
                                @else
                                    <span class="text-slate-600">Aloqa bo'lmagan</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @php $activeLease = $dev->activeAuthorization; @endphp
                                @if ($activeLease)
                                    <div class="text-emerald-400 font-semibold text-[11px]">
                                        ✔ Faol ({{ $activeLease->user->name ?? 'Xodim' }})
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        Muddati: {{ $activeLease->expires_at->timezone('Asia/Tashkent')->format('H:i d.m') }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        Epoch: {{ $activeLease->epoch }}
                                    </div>
                                    <button
                                        wire:click="revokeLease({{ $dev->id }})"
                                        class="mt-1 text-[10px] text-rose-400 hover:underline"
                                    >
                                        Bekor qilish
                                    </button>
                                @else
                                    <span class="text-slate-500 text-[11px]">Guvohnoma yo'q</span>
                                    <div class="mt-0.5">
                                        <button
                                            wire:click="openLeaseModal({{ $dev->id }})"
                                            class="text-[10px] text-blue-400 hover:underline"
                                        >
                                            + Guvohnoma berish
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="p-3">
                                @if ($dev->activeInventoryAllocations->isNotEmpty())
                                    <div class="space-y-1">
                                        @foreach ($dev->activeInventoryAllocations as $alloc)
                                            <div class="flex items-center justify-between gap-1 text-[11px] bg-slate-950 px-2 py-1 rounded border border-slate-800">
                                                <span>{{ $alloc->productVariant->product->name ?? 'Tovar' }} ({{ $alloc->productVariant->volume->name ?? '' }}):</span>
                                                <span class="font-mono font-bold text-amber-400">{{ $alloc->available_quantity }} dona</span>
                                                <button
                                                    wire:click="returnStockAllocation({{ $alloc->id }})"
                                                    title="Omborga qaytarish"
                                                    class="text-slate-500 hover:text-rose-400 ml-1 text-xs"
                                                >
                                                    ↩
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-600 text-[11px]">Ajratma yo'q</span>
                                @endif
                                <div class="mt-1">
                                    <button
                                        wire:click="openStockAllocModal({{ $dev->id }})"
                                        class="text-[10px] text-cyan-400 hover:underline"
                                    >
                                        + Tovar ajratish
                                    </button>
                                </div>
                            </td>
                            <td class="p-3">
                                @if ($dev->activeCreditAllocations->isNotEmpty())
                                    <div class="space-y-1">
                                        @foreach ($dev->activeCreditAllocations as $cAlloc)
                                            <div class="text-[11px] bg-slate-950 px-2 py-1 rounded border border-slate-800">
                                                <span class="text-slate-400">
                                                    {{ $cAlloc->is_new_customer_budget ? 'Yangi mijozlar byudjeti' : ($cAlloc->customer->name ?? 'Mijoz') }}:
                                                </span>
                                                <span class="font-mono font-bold text-emerald-400">
                                                    {{ number_format($cAlloc->available_amount, 0, '.', ' ') }} so'm
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-600 text-[11px]">Kredit ajratilmagan</span>
                                @endif
                                <div class="mt-1">
                                    <button
                                        wire:click="openCreditAllocModal({{ $dev->id }})"
                                        class="text-[10px] text-emerald-400 hover:underline"
                                    >
                                        + Kredit ajratish
                                    </button>
                                </div>
                            </td>
                            <td class="p-3 text-right space-y-1">
                                @if ($dev->status === 'ACTIVE')
                                    <button
                                        wire:click="openReconcileModal({{ $dev->id }})"
                                        class="px-2.5 py-1 rounded bg-amber-950 text-amber-300 border border-amber-800 hover:bg-amber-900 text-[11px] block ml-auto"
                                    >
                                        Yo'qolgan / Reconcile
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-6 text-center text-slate-500">
                                Tizimda ro'yxatdan o'tgan qurilmalar mavjud emas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Register Device -->
    @if ($showRegisterModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white">Yangi qurilmani ro'yxatdan o'tkazish</h3>
                    <button wire:click="$set('showRegisterModal', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Qurilma nomi (Kassir/Telefon)</label>
                        <input
                            type="text"
                            wire:model="newDeviceName"
                            placeholder="Masalan: Kassa Kompyuter 1 yoki Sotuvchi Ali Telefoni"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:border-blue-500 focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">Qurilma turi</label>
                            <select
                                wire:model="newDeviceType"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-blue-500 focus:outline-none"
                            >
                                <option value="PC">Kompyuter (PC)</option>
                                <option value="MOBILE">Telefon (Mobile)</option>
                                <option value="TABLET">Planshet (Tablet)</option>
                                <option value="POS_TERMINAL">POS Terminal</option>
                                <option value="OTHER">Boshqa</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">Biriktirilgan xodim</label>
                            <select
                                wire:model="newDeviceUserId"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Biriktirilmagan</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                            <input type="checkbox" wire:model.live="allowNewCustDebt" class="rounded bg-slate-900 border-slate-700 text-blue-600" />
                            <span>Yangi offline mijozlarga nasiya savdoga ruxsat berish</span>
                        </label>

                        @if ($allowNewCustDebt)
                            <div>
                                <label class="block text-slate-400 mb-1">Yangi mijozlar umumiy nasiya byudjeti (so'm)</label>
                                <input
                                    type="number"
                                    wire:model="newCustDebtBudget"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white font-mono"
                                />
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Izoh</label>
                        <textarea
                            wire:model="newDeviceNotes"
                            rows="2"
                            placeholder="Qurilma haqida qo'shimcha ma'lumot..."
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button
                        wire:click="$set('showRegisterModal', false)"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-semibold"
                    >
                        Bekor qilish
                    </button>
                    <button
                        wire:click="registerDevice"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-md"
                    >
                        Ro'yxatdan o'tkazish
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Issue Signed Lease -->
    @if ($showLeaseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white">Ruxsat guvohnomasi (Lease) berish</h3>
                    <button wire:click="$set('showLeaseModal', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Mas'ul xodim</label>
                        <select
                            wire:model="leaseUserId"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:outline-none"
                        >
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Amal qilish muddati (soat)</label>
                        <select
                            wire:model="leaseDurationHours"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:outline-none"
                        >
                            <option value="8">8 soat (Bitta ish smenasi)</option>
                            <option value="12">12 soat</option>
                            <option value="24">24 soat (1 kun)</option>
                            <option value="48">48 soat (2 kun)</option>
                            <option value="72">72 soat (3 kun)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Beriladigan ruxsatlar (Snapshot)</label>
                        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                                <input type="checkbox" value="offline_sales" wire:model="leasePermissions" class="rounded bg-slate-900 border-slate-700 text-blue-600" />
                                <span>Offline rejimda sotish (offline_sales)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                                <input type="checkbox" value="sell_on_credit" wire:model="leasePermissions" class="rounded bg-slate-900 border-slate-700 text-blue-600" />
                                <span>Nasiyaga sotish (sell_on_credit)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                                <input type="checkbox" value="custom_sale_price" wire:model="leasePermissions" class="rounded bg-slate-900 border-slate-700 text-blue-600" />
                                <span>Erkin narx kiritish (custom_sale_price)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                                <input type="checkbox" value="view_cost_price" wire:model="leasePermissions" class="rounded bg-slate-900 border-slate-700 text-blue-600" />
                                <span>Tannarxni ko'rish (view_cost_price)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                                <input type="checkbox" value="view_debts" wire:model="leasePermissions" class="rounded bg-slate-900 border-slate-700 text-blue-600" />
                                <span>Qarzlarni ko'rish (view_debts)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button
                        wire:click="$set('showLeaseModal', false)"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-semibold"
                    >
                        Bekor qilish
                    </button>
                    <button
                        wire:click="issueLease"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-md"
                    >
                        Imzolash va Berish
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Grant Stock Allocation -->
    @if ($showStockAllocModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white">Tovar sotish huquqini ajratish (Stock Allocation)</h3>
                    <button wire:click="$set('showStockAllocModal', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Mahsulot varianti</label>
                        <select
                            wire:model="stockAllocVariantId"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:outline-none"
                        >
                            @foreach ($variants as $v)
                                @php
                                    $onHand = $v->balance ? (int) $v->balance->quantity : 0;
                                    $res = (int) \App\Models\InventoryAllocation::where('product_variant_id', $v->id)->where('status', 'ACTIVE')->sum(\Illuminate\Support\Facades\DB::raw('allocated_quantity - consumed_quantity - returned_quantity'));
                                    $free = max(0, $onHand - $res);
                                @endphp
                                <option value="{{ $v->id }}">
                                    {{ $v->product->name }} ({{ $v->volume->name }}) — Erkin: {{ $free }} dona (Omborda: {{ $onHand }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Ajratiladigan miqdor (dona)</label>
                        <input
                            type="number"
                            min="1"
                            wire:model="stockAllocQuantity"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Izoh</label>
                        <input
                            type="text"
                            wire:model="stockAllocNotes"
                            placeholder="Masalan: Ertalabki yetkazib berish uchun"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button
                        wire:click="$set('showStockAllocModal', false)"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-semibold"
                    >
                        Bekor qilish
                    </button>
                    <button
                        wire:click="grantStockAllocation"
                        class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-semibold shadow-md"
                    >
                        Ajratish (Rezerv qilish)
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Grant Credit Allocation -->
    @if ($showCreditAllocModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white">Kredit limiti ajratish (Credit Allocation)</h3>
                    <button wire:click="$set('showCreditAllocModal', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-300 p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                        <input type="checkbox" wire:model.live="creditIsNewCustBudget" class="rounded bg-slate-900 border-slate-700 text-blue-600" />
                        <span>Yangi offline mijozlar uchun umumiy byudjet sifatida</span>
                    </label>

                    @if (! $creditIsNewCustBudget)
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">Mijozni tanlang</label>
                            <select
                                wire:model="creditAllocCustomerId"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:outline-none"
                            >
                                @foreach ($customers as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->name }} (Limit: {{ number_format($c->debt_limit, 0, '.', ' ') }} so'm, Qarz: {{ number_format($c->current_debt, 0, '.', ' ') }} so'm)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Ajratiladigan kredit limiti (so'm)</label>
                        <input
                            type="number"
                            min="1000"
                            step="1000"
                            wire:model="creditAllocAmount"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Izoh</label>
                        <input
                            type="text"
                            wire:model="creditAllocNotes"
                            placeholder="Kredit ajratish sababi..."
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button
                        wire:click="$set('showCreditAllocModal', false)"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-semibold"
                    >
                        Bekor qilish
                    </button>
                    <button
                        wire:click="grantCreditAllocation"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-md"
                    >
                        Kredit Ajratish
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Reconcile Lost Device -->
    @if ($showReconcileModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-amber-400">Yo'qolgan qurilmani muvofiqlashtirish</h3>
                    <button wire:click="$set('showReconcileModal', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <p class="text-slate-300">
                        DIQQAT: Ushbu amal qurilmadagi barcha qolgan tovar va kredit ajratmalarini (rezervlarini) xavfsiz holda umumiy ombor erkin qoldig'iga qaytaradi va qurilmani bloklaydi.
                    </p>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">Muvofiqlashtirish sababi (Audit uchun)</label>
                        <textarea
                            wire:model="reconcileReason"
                            rows="3"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button
                        wire:click="$set('showReconcileModal', false)"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-semibold"
                    >
                        Bekor qilish
                    </button>
                    <button
                        wire:click="reconcileLostDevice"
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold shadow-md"
                    >
                        Muvofiqlashtirish va Qaytarish
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
