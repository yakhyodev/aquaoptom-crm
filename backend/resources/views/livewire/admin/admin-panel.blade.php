<div class="space-y-6">
    <!-- Header & Navigation Tabs -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider">AquaOptom CRM Boshqaruv Markazi</span>
                <h2 class="text-xl sm:text-2xl font-black text-white mt-1">Admin Panel va Tizim Sozlamalari</h2>
                <p class="text-xs text-slate-400 mt-1">
                    Faqat do'kon egasi (OWNER) va administrator (ADMIN) uchun to'liq boshqaruv markazi.
                </p>
            </div>
            <a href="{{ route('admin.opening-balances') }}" class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold text-xs shadow-sm transition flex items-center gap-1.5">
                <span>⚖</span>
                <span>Boshlang'ich qoldiqlar &rarr;</span>
            </a>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-800 pt-4 text-xs font-semibold">
            <button
                type="button"
                wire:click="setTab('settings')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'settings' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
            >
                <span>⚙</span>
                <span>Do'kon Sozlamalari</span>
            </button>

            <button
                type="button"
                wire:click="setTab('users')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'users' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
            >
                <span>👥</span>
                <span>Xodimlar & Huquqlar</span>
            </button>

            <button
                type="button"
                wire:click="setTab('conflicts')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'conflicts' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
            >
                <span>⚠</span>
                <span>NEEDS_REVIEW Konfliktlar</span>
                @if($telemetry['conflicts']['needs_review'] > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white">
                        {{ $telemetry['conflicts']['needs_review'] }}
                    </span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('devices')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'devices' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
            >
                <span>📱</span>
                <span>Offline Qurilmalar & Ajratmalar</span>
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'audit' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
            >
                <span>📜</span>
                <span>Audit Jurnali</span>
            </button>

            <button
                type="button"
                wire:click="setTab('telemetry')"
                class="px-4 py-2 rounded-xl transition flex items-center gap-2 {{ $activeTab === 'telemetry' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
            >
                <span>📡</span>
                <span>Infratuzilma & Telemetriya</span>
            </button>
        </div>
    </div>

    <!-- TAB 1: SOZLAMALAR (Settings) -->
    @if($activeTab === 'settings')
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div>
                    <h3 class="text-base font-bold text-white">Asosiy Tizim va Savdo Sozlamalari</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Ushbu sozlamalar barcha sotuv, kassa, ombor va offline qurilmalar uchun umumiy hisoblanadi.</p>
                </div>
                @if($settingsSuccessMessage)
                    <div class="px-3.5 py-2 bg-emerald-950/80 border border-emerald-800 text-emerald-400 text-xs rounded-xl font-medium animate-fade">
                        ✓ {{ $settingsSuccessMessage }}
                    </div>
                @endif
            </div>

            <form wire:submit.prevent="saveSettings" class="space-y-6 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1.5">Do'kon nomi</label>
                        <input
                            type="text"
                            wire:model="storeName"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        />
                        @error('storeName') <span class="text-rose-400 text-[11px] mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1.5">Aloqa telefoni</label>
                        <input
                            type="text"
                            wire:model="storePhone"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        />
                        @error('storePhone') <span class="text-rose-400 text-[11px] mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1.5">Yuridik manzil</label>
                        <input
                            type="text"
                            wire:model="storeAddress"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        />
                        @error('storeAddress') <span class="text-rose-400 text-[11px] mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1.5">Vaqt mintaqasi (Timezone)</label>
                        <input
                            type="text"
                            wire:model="timezone"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                        <span class="text-slate-500 text-[11px] mt-1">Do'kon kuni hisobotlari va smenalar ushbu mintaqa bo'yicha hisoblanadi.</span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1.5">Kam qoldiq chegarasi (dona)</label>
                        <input
                            type="number"
                            wire:model="lowStockThreshold"
                            min="0"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                        <span class="text-slate-500 text-[11px] mt-1">Ushbu miqdor yoki undan kam qolgan tovarlar dashboardda ogohlantiriladi.</span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1.5">Offline qurilma aloqasiz qolish chegarasi (soat)</label>
                        <input
                            type="number"
                            wire:model="offlineReconcileTimeoutHours"
                            min="1"
                            max="168"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                        <span class="text-slate-500 text-[11px] mt-1">Shu vaqtdan oshgan qurilmalar to'liqlik ko'rsatkichini kamaytiradi.</span>
                    </div>
                </div>

                <!-- Strict Credit Mode Toggle -->
                <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-white">Qat'iy kredit rejimi (Strict Credit Mode)</div>
                        <div class="text-slate-400 text-[11px] mt-0.5">
                            Yoqilsa, mijoz belgilangan kredit limitidan oshiqcha nasiyaga tovar sotib ololmaydi va offline savdolarda ham kredit byudjeti qat'iy tekshiriladi.
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="strictCreditMode" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-800">
                    <button
                        type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold text-xs shadow-lg shadow-blue-500/20 transition"
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
                <div class="p-3.5 bg-emerald-950/80 border border-emerald-800 text-emerald-400 text-xs rounded-xl font-medium">
                    ✓ {{ session('user_message') }}
                </div>
            @endif
            @if(session('user_error'))
                <div class="p-3.5 bg-rose-950/80 border border-rose-800 text-rose-400 text-xs rounded-xl font-medium">
                    ⚠ {{ session('user_error') }}
                </div>
            @endif

            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-base font-bold text-white">Do'kon Xodimlari va Rollari</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Xodimlarning holati, rollari va to'g'ridan-to'g'ri granular ruxsatlari</p>
                    </div>
                    <button
                        type="button"
                        wire:click="$set('showAddUserModal', true)"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-md transition flex items-center gap-1.5"
                    >
                        <span>+</span>
                        <span>Yangi xodim qo'shish</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-950/60">
                            <tr>
                                <th class="px-4 py-3">Xodim</th>
                                <th class="px-4 py-3">Email / Telefon</th>
                                <th class="px-4 py-3">Rol</th>
                                <th class="px-4 py-3">Holat</th>
                                <th class="px-4 py-3">Tannarx Huquqi</th>
                                <th class="px-4 py-3 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @foreach ($users as $u)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-3 font-semibold text-white">
                                        {{ $u->name }}
                                        @if($u->id === auth()->id())
                                            <span class="text-[10px] text-cyan-400 ml-1 font-mono">(Siz)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-300 font-mono text-[11px]">
                                        <div>{{ $u->email }}</div>
                                        @if($u->phone) <div class="text-slate-500">{{ $u->phone }}</div> @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if(!$u->isOwner())
                                            <select
                                                wire:change="updateUserRole({{ $u->id }}, $event.target.value)"
                                                class="px-2.5 py-1 bg-slate-950 border border-slate-800 rounded-lg text-xs font-mono font-bold uppercase text-blue-300 focus:outline-none"
                                            >
                                                <option value="SALES_MANAGER" @selected($u->role === 'SALES_MANAGER')>SALES_MANAGER</option>
                                                <option value="WAREHOUSE_MANAGER" @selected($u->role === 'WAREHOUSE_MANAGER')>WAREHOUSE_MANAGER</option>
                                                <option value="CASHIER" @selected($u->role === 'CASHIER')>CASHIER</option>
                                                <option value="ADMIN" @selected($u->role === 'ADMIN')>ADMIN</option>
                                            </select>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase bg-amber-950 text-amber-300 border border-amber-800">
                                                OWNER
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($u->isActive())
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">
                                                Faol
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-950 text-rose-400 border border-rose-800">
                                                Bloklangan
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($u->hasPermission('view_cost_price'))
                                            <span class="text-xs text-emerald-400 font-semibold">✔ Ruxsatli</span>
                                        @else
                                            <span class="text-xs text-slate-500">❌ Yashirin</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        @if(!$u->isOwner())
                                            <button
                                                type="button"
                                                wire:click="openPermissionModal({{ $u->id }})"
                                                class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-medium border border-slate-700 transition"
                                            >
                                                Huquqlar ({{ $u->directPermissions->count() }})
                                            </button>

                                            @if($u->id !== auth()->id())
                                                <button
                                                    type="button"
                                                    wire:click="toggleUserStatus({{ $u->id }})"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-medium transition {{ $u->isActive() ? 'bg-rose-950 hover:bg-rose-900 text-rose-300 border border-rose-800' : 'bg-emerald-950 hover:bg-emerald-900 text-emerald-300 border border-emerald-800' }}"
                                                >
                                                    {{ $u->isActive() ? 'Bloklash' : 'Faollashtirish' }}
                                                </button>
                                            @endif
                                        @else
                                            <span class="text-slate-600 text-[11px] italic">Boshqaruvchi</span>
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
                <div class="p-3.5 bg-emerald-950/80 border border-emerald-800 text-emerald-400 text-xs rounded-xl font-medium">
                    ✓ {{ session('conflict_message') }}
                </div>
            @endif

            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Sinxronizatsiya Mojarolari (NEEDS_REVIEW)</h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Offline qurilmalardan kechikib kelgan, yopiq smena yoki limit buzilishi sababli server qabul qilmagan operatsiyalar.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-950/60">
                            <tr>
                                <th class="px-4 py-3">Operatsiya ID & Turi</th>
                                <th class="px-4 py-3">Qurilma & Xodim</th>
                                <th class="px-4 py-3">Xato sababi (Error Code)</th>
                                <th class="px-4 py-3">Holat</th>
                                <th class="px-4 py-3">Vaqt</th>
                                <th class="px-4 py-3 text-right">Amal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse ($conflicts as $cnf)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-white">{{ $cnf->operation_type }}</div>
                                        <div class="font-mono text-[10px] text-slate-500">{{ $cnf->operation_id }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $cnf->device?->device_name ?? 'Noma\'lum qurilma' }}</div>
                                        <div class="text-[10px] text-slate-500">{{ $cnf->user?->name ?? 'Tizim' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-800">
                                            {{ $cnf->error_code ?? 'GENERAL_CONFLICT' }}
                                        </span>
                                        <div class="text-[11px] text-slate-400 mt-0.5">{{ $cnf->error_message }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase
                                            @if($cnf->status === 'NEEDS_REVIEW') bg-rose-950 text-rose-300 border border-rose-800 animate-pulse
                                            @elseif($cnf->status === 'RESOLVED') bg-emerald-950 text-emerald-300 border border-emerald-800
                                            @else bg-slate-800 text-slate-400 @endif
                                        ">
                                            {{ $cnf->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[11px] text-slate-500">
                                        {{ $cnf->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($cnf->status === 'NEEDS_REVIEW')
                                            <button
                                                type="button"
                                                wire:click="openConflictModal({{ $cnf->id }})"
                                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold shadow transition"
                                            >
                                                Ko'rib chiqish &rarr;
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-500">
                                                Hal qildi: {{ $cnf->resolver?->name ?? 'Admin' }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">
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
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-white">Tizim Amallari Audit Jurnali</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Narx o'zgarishi, ruxsatlar, konfliktlar va boshqaruv harakatlarining o'zgarmas tarixi.</p>
                </div>

                <!-- Filters -->
                <div class="flex items-center gap-2 text-xs">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="auditActionFilter"
                        placeholder="Amal bo'yicha qidiruv (masalan: SETTING)..."
                        class="px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-blue-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-950/60">
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
                    <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                        @forelse ($auditLogs as $log)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-4 py-2.5 font-bold text-cyan-400">{{ $log->action }}</td>
                                <td class="px-4 py-2.5 font-sans text-slate-200">{{ $log->user?->name ?? 'Tizim' }}</td>
                                <td class="px-4 py-2.5 text-slate-400">
                                    {{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-500 max-w-xs truncate">
                                    {{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE) }}
                                </td>
                                <td class="px-4 py-2.5 text-emerald-400 max-w-xs truncate">
                                    {{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $log->ip_address }}</td>
                                <td class="px-4 py-2.5 text-slate-400">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-500">
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
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <span>📤</span>
                            <span>Outbox Navbati</span>
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono {{ $telemetry['outbox']['worker_health'] === 'HEALTHY' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-blue-950 text-blue-300 border border-blue-800' }}">
                            {{ $telemetry['outbox']['worker_health'] }}
                        </span>
                    </div>
                    <div class="space-y-2.5 text-xs text-slate-300">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Kutilayotgan hodisalar:</span>
                            <span class="font-mono font-bold text-white">{{ $telemetry['outbox']['pending'] }} ta</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Xatolik bergan (Failed):</span>
                            <span class="font-mono font-bold text-rose-400">{{ $telemetry['outbox']['failed'] }} ta</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Muvaffaqiyatli tarqatilgan:</span>
                            <span class="font-mono font-bold text-emerald-400">{{ $telemetry['outbox']['published'] }} ta</span>
                        </div>
                        <div class="pt-2 border-t border-slate-800 flex justify-between text-[11px]">
                            <span class="text-slate-500">Oxirgi nashr:</span>
                            <span class="font-mono text-slate-400">{{ $telemetry['outbox']['last_published_at'] ?? 'Yo\'q' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Bazalar va Xotira -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <span>🗄</span>
                            <span>Ma'lumotlar Bazasi & Kesh</span>
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-950 text-emerald-400 border border-emerald-800">
                            {{ $telemetry['database']['status'] }}
                        </span>
                    </div>
                    <div class="space-y-2.5 text-xs text-slate-300">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Drayver:</span>
                            <span class="font-mono font-bold text-white">{{ $telemetry['database']['driver'] }} (PG 16)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Baza hajmi:</span>
                            <span class="font-mono font-bold text-cyan-400">{{ $telemetry['database']['size'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Redis (Predis):</span>
                            <span class="font-mono font-bold text-emerald-400">{{ $telemetry['redis']['status'] }} ({{ $telemetry['redis']['port'] }})</span>
                        </div>
                        <div class="pt-2 border-t border-slate-800 flex justify-between text-[11px]">
                            <span class="text-slate-500">Real-vaqt kanallari:</span>
                            <span class="font-mono text-slate-400">store.operations, finance</span>
                        </div>
                    </div>
                </div>

                <!-- Zaxira va Eksportlar -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <span>💾</span>
                            <span>Eksport & Zaxira Nusxalari</span>
                        </h4>
                        <span class="text-xs text-slate-400 font-mono">{{ $telemetry['backup']['total_exports'] }} ta</span>
                    </div>
                    <div class="space-y-2.5 text-xs text-slate-300">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Oxirgi eksport vaqti:</span>
                            <span class="font-mono text-white">{{ $telemetry['backup']['last_export_at'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Jami faol qurilmalar:</span>
                            <span class="font-mono text-emerald-400">{{ $telemetry['devices']['active'] }} / {{ $telemetry['devices']['total'] }} ta</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Kechikkan qurilmalar:</span>
                            <span class="font-mono {{ $telemetry['devices']['stale'] > 0 ? 'text-amber-400' : 'text-slate-400' }}">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-4 text-xs max-h-[85vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-white">Foydalanuvchi Huquqlari: {{ $targetUser?->name }}</h3>
                        <p class="text-slate-400 text-[11px]">Rol: <span class="font-mono font-bold text-blue-400">{{ $targetUser?->role }}</span></p>
                    </div>
                    <button wire:click="closePermissionModal" class="text-slate-400 hover:text-white text-base">&times;</button>
                </div>

                <div class="space-y-3">
                    @foreach ($allPermissions as $perm)
                        @php
                            $isOverridden = array_key_exists($perm->name, $userPermissionOverrides);
                            $overrideVal = $userPermissionOverrides[$perm->name] ?? null;
                            $hasEffective = $targetUser?->hasPermission($perm->name);
                        @endphp
                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-white flex items-center gap-2">
                                    <span>{{ $perm->display_name }}</span>
                                    <span class="text-[10px] font-mono text-slate-500">({{ $perm->name }})</span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $perm->description }}</div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="setPermissionOverride('{{ $perm->name }}', true)"
                                    class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition {{ $isOverridden && $overrideVal === true ? 'bg-emerald-600 text-white shadow' : 'bg-slate-800 text-slate-400 hover:text-white' }}"
                                >
                                    Ruxsat berish
                                </button>
                                <button
                                    type="button"
                                    wire:click="setPermissionOverride('{{ $perm->name }}', false)"
                                    class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition {{ $isOverridden && $overrideVal === false ? 'bg-rose-600 text-white shadow' : 'bg-slate-800 text-slate-400 hover:text-white' }}"
                                >
                                    Cheklash
                                </button>
                                @if($isOverridden)
                                    <button
                                        type="button"
                                        wire:click="setPermissionOverride('{{ $perm->name }}', null)"
                                        class="px-2 py-1 rounded-lg text-[10px] text-slate-500 hover:text-slate-300"
                                        title="Rol standartiga qaytarish"
                                    >
                                        Qaytarish
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-3 border-t border-slate-800">
                    <button
                        type="button"
                        wire:click="closePermissionModal"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl font-semibold transition"
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-white">Mojaroni Hal Qilish (Resolution)</h3>
                        <p class="text-slate-400 text-[11px]">Operatsiya: <span class="font-mono text-cyan-400">{{ $conflictItem?->operation_id }}</span></p>
                    </div>
                    <button wire:click="closeConflictModal" class="text-slate-400 hover:text-white text-base">&times;</button>
                </div>

                @if($conflictErrorMessage)
                    <div class="p-3 bg-rose-950 border border-rose-800 text-rose-300 rounded-xl text-xs">
                        ⚠ {{ $conflictErrorMessage }}
                    </div>
                @endif

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-1.5 font-mono text-[11px]">
                    <div>Qurilma: <span class="text-white">{{ $conflictItem?->device?->device_name }}</span></div>
                    <div>Turi: <span class="text-amber-400">{{ $conflictItem?->operation_type }}</span></div>
                    <div>Xato: <span class="text-rose-400">{{ $conflictItem?->error_code }}</span> ({{ $conflictItem?->error_message }})</div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Qaror (Action)</label>
                        <select
                            wire:model="conflictAction"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        >
                            <option value="APPROVED_OVERRIDE">APPROVED_OVERRIDE — Majburiy tasdiqlash va ombor/daftarni yangilash</option>
                            <option value="REJECT">REJECT — Rad etish (Operatsiyani bekor qilish)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Hal qilish sababi / izohi (Audit uchun shart)</label>
                        <textarea
                            wire:model="conflictReason"
                            rows="3"
                            placeholder="Nima sababdan ushbu qaror qabul qilinganini batafsil yozing..."
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        ></textarea>
                        @error('conflictReason') <span class="text-rose-400 text-[11px] mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                    <button
                        type="button"
                        wire:click="closeConflictModal"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium"
                    >
                        Bekor qilish
                    </button>
                    <button
                        type="button"
                        wire:click="resolveConflict"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold shadow transition"
                    >
                        Qarorni Tasdiqlash
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: Yangi Xodim Qo'shish (Add User Modal) -->
    @if($showAddUserModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white">Yangi Xodim Qo'shish</h3>
                    <button wire:click="$set('showAddUserModal', false)" class="text-slate-400 hover:text-white text-base">&times;</button>
                </div>

                <form wire:submit.prevent="createUser" class="space-y-3">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Xodim ismi</label>
                        <input
                            type="text"
                            wire:model="newUserName"
                            required
                            placeholder="Masalan: Jamshid Karimov"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        />
                        @error('newUserName') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Email manzili</label>
                        <input
                            type="email"
                            wire:model="newUserEmail"
                            required
                            placeholder="jamshid@aquaoptom.uz"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        />
                        @error('newUserEmail') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Telefon raqami (ixtiyoriy)</label>
                        <input
                            type="text"
                            wire:model="newUserPhone"
                            placeholder="+998901234567"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Roli</label>
                        <select
                            wire:model="newUserRole"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        >
                            <option value="SALES_MANAGER">Sotuvchi (SALES_MANAGER)</option>
                            <option value="WAREHOUSE_MANAGER">Omborchi (WAREHOUSE_MANAGER)</option>
                            <option value="CASHIER">Kassir / Moliya (CASHIER)</option>
                            <option value="ADMIN">Administrator (ADMIN)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Parol</label>
                        <input
                            type="password"
                            wire:model="newUserPassword"
                            required
                            minlength="8"
                            placeholder="Kamida 8 ta belgi"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-blue-500"
                        />
                        @error('newUserPassword') <span class="text-rose-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                        <button
                            type="button"
                            wire:click="$set('showAddUserModal', false)"
                            class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium"
                        >
                            Bekor qilish
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-semibold shadow transition"
                        >
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
