<x-layouts.app>
    <x-slot name="title">Admin panel — AquaOptom CRM</x-slot>
    <x-slot name="header">Admin panel — Foydalanuvchilar va do'kon sozlamalari</x-slot>

    <div class="space-y-6">
        <!-- Quick navigation to Opening Balances -->
        <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-white">Boshlang‘ich qoldiqlar (Hisob ochilishi)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Ombor tovarlari, kassa pullari, mijozlar va ta’minotchilar boshlang‘ich balanslarini kiritish.</p>
            </div>
            <a href="{{ route('admin.opening-balances') }}" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold text-xs shadow-sm transition">
                Ochilish balanslarini boshqarish &rarr;
            </a>
        </div>

        <!-- Users Management Card -->
        <x-card title="Xodimlar va Rollar Boshqaruvi" subtitle="Tizimga kirish huquqiga ega barcha foydalanuvchilar">
            <x-slot name="actions">
                <button
                    type="button"
                    onclick="document.getElementById('add-user-modal').classList.remove('hidden')"
                    class="px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5"
                >
                    <span>+</span>
                    <span>Yangi xodim</span>
                </button>
            </x-slot>

            @php
                $users = \App\Models\User::latest()->get();
            @endphp

            <x-table :headers="['Ism', 'Email / Telefon', 'Rol', 'Holat', 'Tannarx huquqi', 'Yaratilgan sana', 'Amallar']">
                @foreach ($users as $u)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-4 py-3 font-semibold text-white">{{ $u->name }}</td>
                        <td class="px-4 py-3 text-slate-300">
                            <div>{{ $u->email }}</div>
                            @if ($u->phone)
                                <div class="text-[11px] text-slate-500 font-mono">{{ $u->phone }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase
                                {{ $u->isOwner() ? 'bg-amber-950 text-amber-300 border border-amber-800' : 'bg-blue-950 text-blue-300 border border-blue-800' }}">
                                {{ $u->role }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($u->isActive())
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
                            @if ($u->can('view_cost_price'))
                                <span class="text-xs text-emerald-400 font-semibold">✔ Ruxsatli</span>
                            @else
                                <span class="text-xs text-slate-500">❌ Yashirin</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-400">{{ $u->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if (!$u->isOwner() && auth()->user()->id !== $u->id)
                                <form method="POST" action="{{ route('admin.users.toggle-status', $u->id) }}" class="inline">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="text-xs {{ $u->isActive() ? 'text-rose-400 hover:text-rose-300' : 'text-emerald-400 hover:text-emerald-300' }}"
                                    >
                                        {{ $u->isActive() ? 'Bloklash' : 'Faollashtirish' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-[11px] text-slate-600">Asosiy egasi</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <!-- Roles & Permissions Matrix -->
        <x-card title="Rollar va Huquqlar Matritsasi" subtitle="Do'konda amal qilayotgan 5 asosiy rol">
            @php
                $roles = \App\Models\Role::with('permissions')->get();
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($roles as $role)
                    <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-mono text-xs font-bold text-cyan-400">{{ $role->name }}</span>
                            <span class="text-[10px] text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full font-bold">
                                {{ $role->permissions->count() }} ta ruxsat
                            </span>
                        </div>
                        <h4 class="text-sm font-semibold text-white">{{ $role->display_name }}</h4>
                        <p class="text-xs text-slate-400 mt-1 mb-3">{{ $role->description }}</p>
                        <div class="space-y-1">
                            @foreach ($role->permissions->take(4) as $perm)
                                <div class="text-[11px] text-slate-300 flex items-center gap-1.5">
                                    <span class="text-blue-400">▪</span>
                                    <span>{{ $perm->display_name }}</span>
                                </div>
                            @endforeach
                            @if ($role->permissions->count() > 4)
                                <div class="text-[10px] text-slate-500 italic">va yana {{ $role->permissions->count() - 4 }} ta...</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <!-- Modal: Add New User -->
    <x-modal id="add-user-modal" title="Yangi xodim qo'shish" maxWidth="md">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4 text-xs">
            @csrf
            <div>
                <label for="new_name" class="block font-medium text-slate-300 mb-1">Xodim ismi</label>
                <input
                    type="text"
                    name="name"
                    id="new_name"
                    required
                    placeholder="Masalan: Sardor Aliyev"
                    class="w-full px-3 py-2 bg-slate-950/80 border border-slate-800 rounded-xl text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="new_email" class="block font-medium text-slate-300 mb-1">Email manzili</label>
                <input
                    type="email"
                    name="email"
                    id="new_email"
                    required
                    placeholder="sardor@aquaoptom.uz"
                    class="w-full px-3 py-2 bg-slate-950/80 border border-slate-800 rounded-xl text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="new_phone" class="block font-medium text-slate-300 mb-1">Telefon raqami (ixtiyoriy)</label>
                <input
                    type="text"
                    name="phone"
                    id="new_phone"
                    placeholder="+998901234567"
                    class="w-full px-3 py-2 bg-slate-950/80 border border-slate-800 rounded-xl text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="new_role" class="block font-medium text-slate-300 mb-1">Xodim roli</label>
                <select
                    name="role"
                    id="new_role"
                    required
                    class="w-full px-3 py-2 bg-slate-950/80 border border-slate-800 rounded-xl text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="SALES_MANAGER">Sotuvchi (Kassir)</option>
                    <option value="WAREHOUSE_MANAGER">Omborchi</option>
                    <option value="CASHIER">Moliya xodimi / Kassa</option>
                    <option value="ADMIN">Administrator</option>
                </select>
            </div>

            <div>
                <label for="new_password" class="block font-medium text-slate-300 mb-1">Parol</label>
                <input
                    type="password"
                    name="password"
                    id="new_password"
                    required
                    minlength="8"
                    placeholder="Kamida 8 ta belgi"
                    class="w-full px-3 py-2 bg-slate-950/80 border border-slate-800 rounded-xl text-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                <button
                    type="button"
                    onclick="document.getElementById('add-user-modal').classList.add('hidden')"
                    class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium"
                >
                    Bekor qilish
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-semibold shadow-md"
                >
                    Saqlash
                </button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>
