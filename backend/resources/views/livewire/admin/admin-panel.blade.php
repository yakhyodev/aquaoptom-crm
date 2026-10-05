<div class="space-y-6">
    <!-- Header & Navigation Tabs -->
    <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-xs font-bold text-cyan-700 ">AquaOptom CRM Boshqaruv Markazi</span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Do‘kon sozlamalari</h2>
                <p class="text-xs text-slate-600 mt-1">
                    Xodimlarni boshqaring, do‘kon ma’lumotlarini o‘zgartiring va qurilmalarni ulang.
                </p>
            </div>
            <a href="{{ route('admin.opening-balances') }}" class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold text-xs shadow-sm transition flex items-center gap-1.5">
                <span>⚖</span>
                <span>Boshlang'ich qoldiqlar &rarr;</span>
            </a>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4 text-xs font-semibold">
            <button
                type="button"
                wire:click="setTab('settings')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'settings' ? 'bg-blue-600 text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
            >
                <span>⚙</span>
                <span>Do'kon Sozlamalari</span>
            </button>

            <button
                type="button"
                wire:click="setTab('users')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'users' ? 'bg-blue-600 text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
            >
                <span>👥</span>
                <span>Xodimlar va ruxsatlar</span>
            </button>

            <button
                type="button"
                wire:click="setTab('conflicts')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'conflicts' ? 'bg-blue-600 text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
            >
                <span>⚠</span>
                <span>Tekshirish kerak</span>
                @if($telemetry['conflicts']['needs_review'] > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-xs font-bold bg-rose-500 text-slate-900">
                        {{ $telemetry['conflicts']['needs_review'] }}
                    </span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('devices')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'devices' ? 'bg-blue-600 text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
            >
                <span>📱</span>
                <span>Telefon va kompyuterlar</span>
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'audit' ? 'bg-blue-600 text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
            >
                <span>📜</span>
                <span>Amallar tarixi</span>
            </button>

            <button
                type="button"
                wire:click="setTab('telemetry')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'telemetry' ? 'bg-blue-600 text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
            >
                <span>📡</span>
                <span>Texnik holat</span>
            </button>
        </div>
    </div>

    <!-- TAB 1: SOZLAMALAR (Settings) -->
    @if($activeTab === 'settings')
        <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Asosiy Tizim va Savdo Sozlamalari</h3>
                    <p class="text-xs text-slate-600 mt-0.5">Ushbu sozlamalar barcha sotuv, kassa, ombor va offline qurilmalar uchun umumiy hisoblanadi.</p>
                </div>
                @if($settingsSuccessMessage)
                    <div class="px-3.5 py-2 bg-emerald-50/80 border border-emerald-200 text-emerald-700 text-xs rounded-xl font-medium animate-fade">
                        ✓ {{ $settingsSuccessMessage }}
                    </div>
                @endif
            </div>

            <form wire:submit.prevent="saveSettings" class="space-y-6 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1.5">Do'kon nomi</label>
                        <input
                            type="text"
                            wire:model="storeName"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        />
                        @error('storeName') <span class="text-rose-700 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1.5">Aloqa telefoni</label>
                        <input
                            type="text"
                            wire:model="storePhone"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        />
                        @error('storePhone') <span class="text-rose-700 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1.5">Yuridik manzil</label>
                        <input
                            type="text"
                            wire:model="storeAddress"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        />
                        @error('storeAddress') <span class="text-rose-700 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1.5">Do‘kon vaqti</label>
                        <input
                            type="text"
                            wire:model="timezone"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono focus:outline-none focus:border-blue-500"
                        />
                        <span class="text-slate-600 text-xs mt-1">Do'kon kuni hisobotlari va smenalar ushbu mintaqa bo'yicha hisoblanadi.</span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1.5">Kam qoldiq chegarasi (dona)</label>
                        <input
                            type="number"
                            wire:model="lowStockThreshold"
                            min="0"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono focus:outline-none focus:border-blue-500"
                        />
                        <span class="text-slate-600 text-xs mt-1">Ushbu miqdor yoki undan kam qolgan tovarlar dashboardda ogohlantiriladi.</span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1.5">Offline qurilma aloqasiz qolish chegarasi (soat)</label>
                        <input
                            type="number"
                            wire:model="offlineReconcileTimeoutHours"
                            min="1"
                            max="168"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono focus:outline-none focus:border-blue-500"
                        />
                        <span class="text-slate-600 text-xs mt-1">Shu vaqtdan oshgan qurilmalar to'liqlik ko'rsatkichini kamaytiradi.</span>
                    </div>
                </div>

                <!-- Strict Credit Mode Toggle -->
                <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-900">Qat'iy kredit rejimi (Strict Credit Mode)</div>
                        <div class="text-slate-600 text-xs mt-0.5">
                            Yoqilsa, mijoz belgilangan kredit limitidan oshiqcha nasiyaga tovar sotib ololmaydi va offline savdolarda ham kredit byudjeti qat'iy tekshiriladi.
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="strictCreditMode" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-100 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200">
                    <button
                        type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-xl font-bold text-xs shadow-lg shadow-blue-500/20 transition"
                    >
                        Sozlamalarni Saqlash
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- TAB 2: XODIMLAR VA HUQUQLAR (Users & Permissions) -->
    @if($activeTab === 'users')
        <div class="space-y-6">
            @if(session('user_message'))
                <div class="p-3.5 bg-emerald-50/80 border border-emerald-200 text-emerald-700 text-xs rounded-xl font-medium">
                    ✓ {{ session('user_message') }}
                </div>
            @endif
            @if(session('user_error'))
                <div class="p-3.5 bg-rose-50/80 border border-rose-200 text-rose-700 text-xs rounded-xl font-medium">
                    ⚠ {{ session('user_error') }}
                </div>
            @endif

            <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Do'kon Xodimlari va Rollari</h3>
                        <p class="text-xs text-slate-600 mt-0.5">Xodimlarning holati, rollari va to'g'ridan-to'g'ri granular ruxsatlari</p>
                    </div>
                    <button
                        type="button"
                        wire:click="$set('showAddUserModal', true)"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-slate-900 font-semibold text-xs rounded-xl shadow-md transition flex items-center gap-1.5"
                    >
                        <span>+</span>
                        <span>Yangi xodim qo'shish</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="text-xs  text-slate-600 border-b border-slate-200 bg-slate-50/60">
                            <tr>
                                <th class="px-4 py-3">Xodim</th>
                                <th class="px-4 py-3">Email / Telefon</th>
                                <th class="px-4 py-3">Rol</th>
                                <th class="px-4 py-3">Holat</th>
                                <th class="px-4 py-3">Tannarx Huquqi</th>
                                <th class="px-4 py-3 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60">
                            @foreach ($users as $u)
                                <tr class="hover:bg-slate-100/30 transition">
                                    <td class="px-4 py-3 font-semibold text-slate-900">
                                        {{ $u->name }}
                                        @if($u->id === auth()->id())
                                            <span class="text-xs text-cyan-700 ml-1 font-mono">(Siz)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 font-mono text-xs">
                                        <div>{{ $u->email }}</div>
                                        @if($u->phone) <div class="text-slate-600">{{ $u->phone }}</div> @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if(!$u->isOwner())
                                            <select
                                                wire:change="updateUserRole({{ $u->id }}, $event.target.value)"
                                                class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono font-bold uppercase text-blue-700 focus:outline-none"
                                            >
                                                <option value="SALES_MANAGER" @selected($u->role === 'SALES_MANAGER')>Sotuvchi</option>
                                                <option value="WAREHOUSE_MANAGER" @selected($u->role === 'WAREHOUSE_MANAGER')>Omborchi</option>
                                                <option value="CASHIER" @selected($u->role === 'CASHIER')>Kassir</option>
                                                <option value="ADMIN" @selected($u->role === 'ADMIN')>Administrator</option>
                                            </select>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                                OWNER
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($u->isActive())
                                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Faol
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                Bloklangan
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($u->hasPermission('view_cost_price'))
                                            <span class="text-xs text-emerald-700 font-semibold">✔ Ruxsatli</span>
                                        @else
                                            <span class="text-xs text-slate-600">❌ Yashirin</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        @if(!$u->isOwner())
                                            <button
                                                type="button"
                                                wire:click="openPermissionModal({{ $u->id }})"
                                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-medium border border-slate-300 transition"
                                            >
                                                Huquqlar ({{ $u->directPermissions->count() }})
                                            </button>

                                            @if($u->id !== auth()->id())
                                                <button
                                                    type="button"
                                                    wire:click="toggleUserStatus({{ $u->id }})"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-medium transition {{ $u->isActive() ? 'bg-rose-50 hover:bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 hover:bg-emerald-50 text-emerald-700 border border-emerald-200' }}"
                                                >
                                                    {{ $u->isActive() ? 'Bloklash' : 'Faollashtirish' }}
                                                </button>
                                            @endif
                                        @else
                                            <span class="text-slate-600 text-xs italic">Boshqaruvchi</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: KONFLIKTLAR (NEEDS_REVIEW Resolution) -->
    @if($activeTab === 'conflicts')
        <div class="space-y-6">
            @if(session('conflict_message'))
                <div class="p-3.5 bg-emerald-50/80 border border-emerald-200 text-emerald-700 text-xs rounded-xl font-medium">
                    ✓ {{ session('conflict_message') }}
                </div>
            @endif

            <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Tekshirish kerak bo‘lgan yozuvlar</h3>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Offline qurilmalardan kechikib kelgan, yopiq smena yoki limit buzilishi sababli server qabul qilmagan operatsiyalar.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="text-xs  text-slate-600 border-b border-slate-200 bg-slate-50/60">
                            <tr>
                                <th class="px-4 py-3">Operatsiya ID & Turi</th>
                                <th class="px-4 py-3">Qurilma & Xodim</th>
                                <th class="px-4 py-3">Xato sababi (Error Code)</th>
                                <th class="px-4 py-3">Holat</th>
                                <th class="px-4 py-3">Vaqt</th>
                                <th class="px-4 py-3 text-right">Amal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60">
                            @forelse ($conflicts as $cnf)
                                <tr class="hover:bg-slate-100/30 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">{{ $cnf->operation_type }}</div>
                                        <div class="font-mono text-xs text-slate-600">{{ $cnf->operation_id }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $cnf->device?->device_name ?? 'Noma\'lum qurilma' }}</div>
                                        <div class="text-xs text-slate-600">{{ $cnf->user?->name ?? 'Tizim' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ $cnf->error_code ?? 'GENERAL_CONFLICT' }}
                                        </span>
                                        <div class="text-xs text-slate-600 mt-0.5">{{ $cnf->error_message }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold font-mono uppercase
                                            @if($cnf->status === 'NEEDS_REVIEW') bg-rose-50 text-rose-700 border border-rose-200 animate-pulse
                                            @elseif($cnf->status === 'RESOLVED') bg-emerald-50 text-emerald-700 border border-emerald-200
                                            @else bg-slate-100 text-slate-600 @endif
                                        ">
                                            {{ $cnf->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                        {{ $cnf->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($cnf->status === 'NEEDS_REVIEW')
                                            <button
                                                type="button"
                                                wire:click="openConflictModal({{ $cnf->id }})"
                                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-lg text-xs font-bold shadow transition"
                                            >
                                                Ko'rib chiqish &rarr;
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-600">
                                                Hal qildi: {{ $cnf->resolver?->name ?? 'Admin' }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-600">
                                        Hozirda ko'rib chiqish kutilayotgan konfliktlar mavjud emas. Barcha offline amallar toza sinxronlangan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $conflicts->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 4: OFFLINE QURILMALAR VA AJRATMALAR -->
    @if($activeTab === 'devices')
        <div class="space-y-6">
            <livewire:devices.device-manager />
        </div>
    @endif

    <!-- TAB 5: AUDIT JURNALI (Audit Log) -->
    @if($activeTab === 'audit')
        <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Tizim Amallari Amallar tarixi</h3>
                    <p class="text-xs text-slate-600 mt-0.5">Narx o'zgarishi, ruxsatlar, konfliktlar va boshqaruv harakatlarining o'zgarmas tarixi.</p>
                </div>

                <!-- Filters -->
                <div class="flex items-center gap-2 text-xs">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="auditActionFilter"
                        placeholder="Amal bo'yicha qidiruv (masalan: SETTING)..."
                        class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs focus:outline-none focus:border-blue-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="text-xs  text-slate-600 border-b border-slate-200 bg-slate-50/60">
                        <tr>
                            <th class="px-4 py-3">Amal</th>
                            <th class="px-4 py-3">Mas'ul xodim</th>
                            <th class="px-4 py-3">Obyekt (Entity)</th>
                            <th class="px-4 py-3">Oldingi qiymat</th>
                            <th class="px-4 py-3">Yangi qiymat</th>
                            <th class="px-4 py-3">IP manzil</th>
                            <th class="px-4 py-3">Sana</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 font-mono text-xs">
                        @forelse ($auditLogs as $log)
                            <tr class="hover:bg-slate-100/30 transition">
                                <td class="px-4 py-2.5 font-bold text-cyan-700">{{ $log->action }}</td>
                                <td class="px-4 py-2.5 font-sans text-slate-800">{{ $log->user?->name ?? 'Tizim' }}</td>
                                <td class="px-4 py-2.5 text-slate-600">
                                    {{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate">
                                    {{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE) }}
                                </td>
                                <td class="px-4 py-2.5 text-emerald-700 max-w-xs truncate">
                                    {{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $log->ip_address }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-600">
                                    Audit yozuvlari topilmadi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $auditLogs->links() }}
            </div>
        </div>
    @endif

    <!-- TAB 6: TELEMETRIYA VA SALOMATLIK (Telemetry) -->
    @if($activeTab === 'telemetry')
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Outbox Holati -->
                <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span>📤</span>
                            <span>Outbox Navbati</span>
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold font-mono {{ $telemetry['outbox']['worker_health'] === 'HEALTHY' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                            {{ $telemetry['outbox']['worker_health'] }}
                        </span>
                    </div>
                    <div class="space-y-2.5 text-xs text-slate-700">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Kutilayotgan hodisalar:</span>
                            <span class="font-mono font-bold text-slate-900">{{ $telemetry['outbox']['pending'] }} ta</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Xatolik bergan (Failed):</span>
                            <span class="font-mono font-bold text-rose-700">{{ $telemetry['outbox']['failed'] }} ta</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Muvaffaqiyatli tarqatilgan:</span>
                            <span class="font-mono font-bold text-emerald-700">{{ $telemetry['outbox']['published'] }} ta</span>
                        </div>
                        <div class="pt-2 border-t border-slate-200 flex justify-between text-xs">
                            <span class="text-slate-600">Oxirgi nashr:</span>
                            <span class="font-mono text-slate-600">{{ $telemetry['outbox']['last_published_at'] ?? 'Yo\'q' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Bazalar va Xotira -->
                <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span>🗄</span>
                            <span>Ma'lumotlar Bazasi & Kesh</span>
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold font-mono bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {{ $telemetry['database']['status'] }}
                        </span>
                    </div>
                    <div class="space-y-2.5 text-xs text-slate-700">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Drayver:</span>
                            <span class="font-mono font-bold text-slate-900">{{ $telemetry['database']['driver'] }} (PG 16)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Baza hajmi:</span>
                            <span class="font-mono font-bold text-cyan-700">{{ $telemetry['database']['size'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Redis (Predis):</span>
                            <span class="font-mono font-bold text-emerald-700">{{ $telemetry['redis']['status'] }} ({{ $telemetry['redis']['port'] }})</span>
                        </div>
                        <div class="pt-2 border-t border-slate-200 flex justify-between text-xs">
                            <span class="text-slate-600">Real-vaqt kanallari:</span>
                            <span class="font-mono text-slate-600">store.operations, finance</span>
                        </div>
                    </div>
                </div>

                <!-- Zaxira va Eksportlar -->
                <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span>💾</span>
                            <span>Eksport & Zaxira Nusxalari</span>
                        </h4>
                        <span class="text-xs text-slate-600 font-mono">{{ $telemetry['backup']['total_exports'] }} ta</span>
                    </div>
                    <div class="space-y-2.5 text-xs text-slate-700">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Oxirgi eksport vaqti:</span>
                            <span class="font-mono text-slate-900">{{ $telemetry['backup']['last_export_at'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Jami faol qurilmalar:</span>
                            <span class="font-mono text-emerald-700">{{ $telemetry['devices']['active'] }} / {{ $telemetry['devices']['total'] }} ta</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Kechikkan qurilmalar:</span>
                            <span class="font-mono {{ $telemetry['devices']['stale'] > 0 ? 'text-amber-700' : 'text-slate-600' }}">
                                {{ $telemetry['devices']['stale'] }} ta
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 1: Huquqlarni Override Qilish (Permission Overrides Modal) -->
    @if($showPermissionModal && $permissionTargetUserId)
        @php
            $targetUser = \App\Models\User::find($permissionTargetUserId);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-4 text-xs max-h-[85vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Foydalanuvchi Huquqlari: {{ $targetUser?->name }}</h3>
                        <p class="text-slate-600 text-xs">Rol: <span class="font-mono font-bold text-blue-700">{{ $targetUser?->role }}</span></p>
                    </div>
                    <button wire:click="closePermissionModal" class="text-slate-600 hover:text-slate-900 text-base">&times;</button>
                </div>

                <div class="space-y-3">
                    @foreach ($allPermissions as $perm)
                        @php
                            $isOverridden = array_key_exists($perm->name, $userPermissionOverrides);
                            $overrideVal = $userPermissionOverrides[$perm->name] ?? null;
                            $hasEffective = $targetUser?->hasPermission($perm->name);
                        @endphp
                        <div class="p-3 rounded-xl bg-slate-50/60 border border-slate-200 flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-slate-900 flex items-center gap-2">
                                    <span>{{ $perm->display_name }}</span>
                                    <span class="text-xs font-mono text-slate-600">({{ $perm->name }})</span>
                                </div>
                                <div class="text-xs text-slate-600 mt-0.5">{{ $perm->description }}</div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="setPermissionOverride('{{ $perm->name }}', true)"
                                    class="px-2.5 py-1 rounded-lg font-bold text-xs transition {{ $isOverridden && $overrideVal === true ? 'bg-emerald-600 text-slate-900 shadow' : 'bg-slate-100 text-slate-600 hover:text-slate-900' }}"
                                >
                                    Ruxsat berish
                                </button>
                                <button
                                    type="button"
                                    wire:click="setPermissionOverride('{{ $perm->name }}', false)"
                                    class="px-2.5 py-1 rounded-lg font-bold text-xs transition {{ $isOverridden && $overrideVal === false ? 'bg-rose-600 text-slate-900 shadow' : 'bg-slate-100 text-slate-600 hover:text-slate-900' }}"
                                >
                                    Cheklash
                                </button>
                                @if($isOverridden)
                                    <button
                                        type="button"
                                        wire:click="setPermissionOverride('{{ $perm->name }}', null)"
                                        class="px-2 py-1 rounded-lg text-xs text-slate-600 hover:text-slate-700"
                                        title="Rol standartiga qaytarish"
                                    >
                                        Qaytarish
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-3 border-t border-slate-200">
                    <button
                        type="button"
                        wire:click="closePermissionModal"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-semibold transition"
                    >
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 2: Mojaroni Hal Qilish (Conflict Resolution Modal) -->
    @if($showConflictModal && $selectedConflictId)
        @php
            $conflictItem = \App\Models\SyncConflict::with('device')->find($selectedConflictId);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Mojaroni Hal Qilish (Resolution)</h3>
                        <p class="text-slate-600 text-xs">Operatsiya: <span class="font-mono text-cyan-700">{{ $conflictItem?->operation_id }}</span></p>
                    </div>
                    <button wire:click="closeConflictModal" class="text-slate-600 hover:text-slate-900 text-base">&times;</button>
                </div>

                @if($conflictErrorMessage)
                    <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs">
                        ⚠ {{ $conflictErrorMessage }}
                    </div>
                @endif

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5 font-mono text-xs">
                    <div>Qurilma: <span class="text-slate-900">{{ $conflictItem?->device?->device_name }}</span></div>
                    <div>Turi: <span class="text-amber-700">{{ $conflictItem?->operation_type }}</span></div>
                    <div>Xato: <span class="text-rose-700">{{ $conflictItem?->error_code }}</span> ({{ $conflictItem?->error_message }})</div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Qaror (Action)</label>
                        <select
                            wire:model="conflictAction"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        >
                            <option value="APPROVED_OVERRIDE">APPROVED_OVERRIDE — Majburiy tasdiqlash va ombor/daftarni yangilash</option>
                            <option value="REJECT">REJECT — Rad etish (Operatsiyani bekor qilish)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Hal qilish sababi / izohi (Audit uchun shart)</label>
                        <textarea
                            wire:model="conflictReason"
                            rows="3"
                            placeholder="Nima sababdan ushbu qaror qabul qilinganini batafsil yozing..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        ></textarea>
                        @error('conflictReason') <span class="text-rose-700 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button
                        type="button"
                        wire:click="closeConflictModal"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium"
                    >
                        Bekor qilish
                    </button>
                    <button
                        type="button"
                        wire:click="resolveConflict"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-xl font-bold shadow transition"
                    >
                        Qarorni Tasdiqlash
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: Yangi Xodim Qo'shish (Add User Modal) -->
    @if($showAddUserModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-slate-50 border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-sm font-bold text-slate-900">Yangi Xodim Qo'shish</h3>
                    <button wire:click="$set('showAddUserModal', false)" class="text-slate-600 hover:text-slate-900 text-base">&times;</button>
                </div>

                <form wire:submit.prevent="createUser" class="space-y-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Xodim ismi</label>
                        <input
                            type="text"
                            wire:model="newUserName"
                            required
                            placeholder="Masalan: Jamshid Karimov"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        />
                        @error('newUserName') <span class="text-rose-700 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Email manzili</label>
                        <input
                            type="email"
                            wire:model="newUserEmail"
                            required
                            placeholder="jamshid@aquaoptom.uz"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        />
                        @error('newUserEmail') <span class="text-rose-700 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Telefon raqami (ixtiyoriy)</label>
                        <input
                            type="text"
                            wire:model="newUserPhone"
                            placeholder="+998901234567"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Roli</label>
                        <select
                            wire:model="newUserRole"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        >
                            <option value="SALES_MANAGER">Sotuvchi (SALES_MANAGER)</option>
                            <option value="WAREHOUSE_MANAGER">Omborchi (WAREHOUSE_MANAGER)</option>
                            <option value="CASHIER">Kassir / Moliya (CASHIER)</option>
                            <option value="ADMIN">Administrator (ADMIN)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Parol</label>
                        <input
                            type="password"
                            wire:model="newUserPassword"
                            required
                            minlength="8"
                            placeholder="Kamida 8 ta belgi"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-blue-500"
                        />
                        @error('newUserPassword') <span class="text-rose-700 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button
                            type="button"
                            wire:click="$set('showAddUserModal', false)"
                            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium"
                        >
                            Bekor qilish
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-slate-900 rounded-xl font-semibold shadow transition"
                        >
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
