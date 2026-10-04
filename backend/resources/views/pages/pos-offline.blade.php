<!DOCTYPE html>
<html lang="uz" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kassa (Offline POS) — AquaOptom CRM</title>

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0284c7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AquaOptom POS">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <!-- Local Built CSS & JS via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
        @media print {
            body * { visibility: hidden; }
            #receipt-printable, #receipt-printable * { visibility: visible; }
            #receipt-printable { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans antialiased flex flex-col select-none" x-data="aquaPos()" x-cloak>

    <!-- 1. HEADER BAR -->
    <header class="bg-slate-900 border-b border-slate-800 px-4 py-2.5 flex items-center justify-between shadow-md sticky top-0 z-30">
        <!-- Brand & Device info -->
        <div class="flex items-center gap-3">
            <a href="/dashboard" class="flex items-center gap-2.5 group" title="Boshqaruv paneliga qaytish">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white text-base shadow-sm group-hover:scale-105 transition-transform">
                    💧
                </div>
                <div>
                    <h1 class="text-sm font-black text-white leading-tight flex items-center gap-1.5">
                        AquaOptom POS
                        <span class="text-[10px] bg-cyan-950 text-cyan-400 border border-cyan-800/60 px-1.5 py-0.5 rounded font-mono">PWA</span>
                    </h1>
                    <p class="text-[10px] text-slate-400 flex items-center gap-1">
                        <span x-text="deviceLease ? deviceLease.device_code : 'DEV-LOCAL'"></span> •
                        <span x-text="warehouseName"></span>
                    </p>
                </div>
            </a>
        </div>

        <!-- Status Badges & Quick Tools -->
        <div class="flex items-center gap-2 sm:gap-3 text-xs">
            <!-- Online / Offline Indicator -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold border transition-colors"
                 :class="isOnline ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/80' : 'bg-rose-950/80 text-rose-400 border-rose-800/80 animate-pulse'">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-500 shadow-sm shadow-emerald-500' : 'bg-rose-500 shadow-sm shadow-rose-500'"></span>
                <span x-text="isOnline ? 'ONLINE' : 'OFFLINE'" class="tracking-wide"></span>
            </div>

            <!-- Outbox queue counter -->
            <button type="button"
                    @click="showAlert('info', `Lokal navbatda ${outboxCount} ta amal kutilmoqda. Internet ulanganda avtomatik sinxronlanadi.`)"
                    class="flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-medium transition-colors"
                    :class="outboxCount > 0 ? 'bg-amber-950/60 text-amber-300 border-amber-800 hover:bg-amber-900/60' : 'bg-slate-800/60 text-slate-400 border-slate-700/60'">
                <span>⏳</span>
                <span class="hidden sm:inline">Navbat:</span>
                <span class="font-bold font-mono" x-text="outboxCount"></span>
            </button>

            <!-- Sync / Refresh from Server (Only if online) -->
            <button type="button"
                    x-show="isOnline"
                    @click="bootstrapFromServer()"
                    :disabled="isBootstrapping"
                    class="px-2.5 py-1 rounded-lg bg-blue-600/20 text-blue-300 border border-blue-500/30 hover:bg-blue-600/30 font-medium transition-colors flex items-center gap-1 disabled:opacity-50"
                    title="Serverdan katalog va ruxsatlarni yangilash">
                <span :class="isBootstrapping ? 'animate-spin' : ''">🔄</span>
                <span class="hidden md:inline" x-text="isBootstrapping ? 'Yuklanmoqda...' : 'Yangilash'"></span>
            </button>

            <!-- Storage Quota Badge -->
            <div class="hidden lg:flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-800/40 text-slate-400 border border-slate-800 text-[11px]"
                 :title="`Ishlatilgan: ${storageInfo.usedMB} MB / Jami: ${storageInfo.totalMB} MB (${storageInfo.isPersistent ? 'Doimiy saqlash faol' : 'Vaqtinchalik'})`">
                <span>💾</span>
                <span x-text="`${storageInfo.usedMB} MB`"></span>
                <span x-show="storageInfo.isPersistent" class="text-emerald-400 font-bold" title="Persistent Storage">✓</span>
            </div>

            <!-- Emergency Export -->
            <button type="button"
                    @click="exportPendingBackup()"
                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 hover:text-white transition-colors"
                    title="Favqulodda zaxira (JSON eksport)">
                📥
            </button>

            <!-- PIN Lock -->
            <button type="button"
                    @click="lockSession()"
                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 hover:text-white transition-colors"
                    title="Kassani qulflash">
                🔒
            </button>
        </div>
    </header>

    <!-- 2. GLOBAL ALERT BANNER -->
    <div x-show="alertMessage"
         x-transition
         class="px-4 py-2 text-xs flex items-center justify-between font-medium border-b z-20"
         :class="{
             'bg-rose-950/90 text-rose-200 border-rose-800': alertMessage && alertMessage.type === 'error',
             'bg-emerald-950/90 text-emerald-200 border-emerald-800': alertMessage && alertMessage.type === 'success',
             'bg-amber-950/90 text-amber-200 border-amber-800': alertMessage && alertMessage.type === 'warning',
             'bg-blue-950/90 text-blue-200 border-blue-800': alertMessage && alertMessage.type === 'info'
         }">
        <div class="flex items-center gap-2">
            <span x-text="alertMessage && alertMessage.type === 'error' ? '⚠️' : (alertMessage && alertMessage.type === 'success' ? '✅' : 'ℹ️')"></span>
            <span x-text="alertMessage ? alertMessage.text : ''"></span>
        </div>
        <button type="button" @click="closeAlert()" class="text-slate-400 hover:text-white p-1">✕</button>
    </div>

    <!-- 3. MAIN POS WORKSPACE -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">

        <!-- A. LEFT COLUMN: CATALOG & SEARCH (60% on desktop) -->
        <section class="flex-1 flex flex-col bg-slate-950 border-r border-slate-800 overflow-hidden">
            <!-- Search & Filter Header -->
            <div class="p-3 sm:p-4 border-b border-slate-800/80 bg-slate-900/40 space-y-3">
                <!-- Search input -->
                <div class="relative">
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Mahsulot nomi, litri, SKU yoki shtrix-kod..."
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-colors">
                    <span x-show="searchQuery"
                          @click="searchQuery = ''"
                          class="absolute right-3 top-2.5 text-slate-500 hover:text-slate-300 cursor-pointer text-sm">✕</span>
                </div>

                <!-- Volume chips filter -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
                    <button type="button"
                            @click="selectedVolume = 'all'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === 'all' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white'">
                        Barchasi
                    </button>
                    <button type="button"
                            @click="selectedVolume = '0.5'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '0.5' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white'">
                        0.5 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '1.0'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '1.0' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white'">
                        1.0 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '1.5'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '1.5' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white'">
                        1.5 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '5'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '5' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white'">
                        5 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '10'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '10' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white'">
                        10 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '18.9'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '18.9' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white'">
                        18.9 L / 19 L
                    </button>
                </div>
            </div>

            <!-- Product Cards Grid -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4">
                <template x-if="filteredCatalog.length === 0">
                    <div class="h-64 flex flex-col items-center justify-center text-center p-6 text-slate-500">
                        <span class="text-4xl mb-2">📦</span>
                        <p class="font-medium text-sm">Tovar topilmadi yoki katalog bo'sh</p>
                        <p class="text-xs text-slate-600 mt-1" x-show="isOnline">
                            Internet ulanganda yuqoridagi "Yangilash" tugmasini bosing
                        </p>
                    </div>
                </template>

                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3">
                    <template x-for="item in filteredCatalog" :key="item.id">
                        <div @click="getAvailableStock(item.id) > 0 ? addToCart(item) : showAlert('warning', `'${item.product_name}' uchun sotish limiti tugagan!`)"
                             class="rounded-xl border p-3 flex flex-col justify-between transition-all cursor-pointer relative overflow-hidden group select-none"
                             :class="getAvailableStock(item.id) > 0 ? 'bg-slate-900 border-slate-800 hover:border-cyan-500/60 hover:bg-slate-800/70' : 'bg-slate-900/40 border-slate-800/40 opacity-60 cursor-not-allowed'">

                            <!-- Top badge: Volume & Limit -->
                            <div class="flex items-start justify-between gap-1 mb-2">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-slate-800 text-cyan-400 border border-slate-700/60"
                                      x-text="item.volume_name || `${item.volume_litres}L`"></span>

                                <!-- Quota badge -->
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                      :class="getAvailableStock(item.id) > 0 ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-800/80' : 'bg-rose-950/80 text-rose-300 border border-rose-800/80'"
                                      :title="`Qurilmaga ajratilgan erkin qoldiq`">
                                    <span x-text="getAvailableStock(item.id) > 0 ? `${getAvailableStock(item.id)} dona` : 'Tugagan'"></span>
                                </span>
                            </div>

                            <!-- Product Name & SKU -->
                            <div class="mb-3">
                                <h3 class="text-sm font-bold text-slate-100 group-hover:text-cyan-300 transition-colors line-clamp-2"
                                    x-text="item.product_name"></h3>
                                <p class="text-[10px] font-mono text-slate-500 mt-0.5" x-text="item.sku"></p>
                            </div>

                            <!-- Bottom: Price and Add button -->
                            <div class="flex items-center justify-between pt-2 border-t border-slate-800/60">
                                <div>
                                    <span class="text-xs text-slate-400">Narxi:</span>
                                    <p class="text-sm font-extrabold text-cyan-400"
                                       x-text="`${(item.default_sale_price || 0).toLocaleString('uz-UZ')} so'm`"></p>
                                </div>
                                <div class="w-7 h-7 rounded-lg bg-cyan-600/20 text-cyan-400 flex items-center justify-center font-bold text-sm group-hover:bg-cyan-600 group-hover:text-white transition-colors"
                                     x-show="getAvailableStock(item.id) > 0">
                                    +
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- B. RIGHT COLUMN: CART, CUSTOMER & PAYMENT (40% on desktop) -->
        <section class="w-full lg:w-[440px] xl:w-[480px] flex flex-col bg-slate-900 border-t lg:border-t-0 lg:border-l border-slate-800 overflow-hidden shadow-xl shrink-0">

            <!-- Customer Selector Bar -->
            <div class="p-3 border-b border-slate-800 bg-slate-900/70 space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-300">
                    <span>Xaridor (Mijoz):</span>
                    <button type="button"
                            @click="showNewCustomerModal = true"
                            class="text-cyan-400 hover:text-cyan-300 flex items-center gap-1 font-bold">
                        <span>+</span> Yangi mijoz (UUID)
                    </button>
                </div>
                <div class="relative">
                    <select x-model="selectedCustomerId"
                            @change="saveCartDraft()"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500 font-medium">
                        <option value="">Tezkor xaridor (Nomsiz / Naqd)</option>
                        <template x-for="c in customers" :key="c.id">
                            <option :value="c.id"
                                    x-text="`${c.name} ${c.store_name ? `(${c.store_name})` : ''} ${c.current_debt > 0 ? `[Qarz: ${c.current_debt.toLocaleString('uz-UZ')}]` : ''}`">
                            </option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Cart Table Items -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                <div class="flex items-center justify-between text-xs text-slate-400 pb-1 border-b border-slate-800">
                    <span class="font-bold">Savat (<span x-text="cart.length"></span> ta tovar)</span>
                    <button type="button"
                            x-show="cart.length > 0"
                            @click="clearCart()"
                            class="text-rose-400 hover:text-rose-300 font-semibold text-[11px]">
                        Savatni tozalash
                    </button>
                </div>

                <template x-if="cart.length === 0">
                    <div class="h-44 flex flex-col items-center justify-center text-center p-4 text-slate-500">
                        <span class="text-3xl mb-1">🛒</span>
                        <p class="text-xs">Savat bo'sh</p>
                        <p class="text-[11px] text-slate-600 mt-0.5">Katalogdan mahsulotlarni tanlang</p>
                    </div>
                </template>

                <!-- Cart rows -->
                <template x-for="(item, index) in cart" :key="item.variant_id">
                    <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col gap-2 shadow-sm">
                        <!-- Row 1: Name and Delete button -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="text-xs font-bold text-slate-100 leading-tight" x-text="item.product_name"></h4>
                                <span class="text-[10px] text-cyan-400 font-medium" x-text="item.volume_name"></span>
                            </div>
                            <button type="button"
                                    @click="removeFromCart(index)"
                                    class="text-slate-500 hover:text-rose-400 p-1 text-xs">
                                🗑️
                            </button>
                        </div>

                        <!-- Row 2: Qty, Unit Price, Line Total -->
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <!-- Qty buttons -->
                            <div class="flex items-center border border-slate-700 rounded-lg overflow-hidden bg-slate-900">
                                <button type="button"
                                        @click="updateQuantity(index, item.quantity - 1)"
                                        class="px-2.5 py-1 text-slate-300 hover:bg-slate-800 font-bold">-</button>
                                <input type="number"
                                       :value="item.quantity"
                                       @input="updateQuantity(index, $event.target.value)"
                                       min="1"
                                       class="w-12 text-center bg-transparent text-white font-bold text-xs focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button"
                                        @click="updateQuantity(index, item.quantity + 1)"
                                        class="px-2.5 py-1 text-slate-300 hover:bg-slate-800 font-bold">+</button>
                            </div>

                            <!-- Unit Price (editable if permitted) -->
                            <div class="flex items-center gap-1">
                                <span class="text-[11px] text-slate-400">×</span>
                                <input type="number"
                                       :value="item.sale_price"
                                       @change="updatePrice(index, $event.target.value)"
                                       class="w-20 bg-slate-900 border border-slate-700 rounded px-1.5 py-1 text-right text-xs font-semibold text-slate-200 focus:outline-none focus:border-cyan-500">
                            </div>

                            <!-- Line Total -->
                            <div class="text-right font-extrabold text-cyan-300 min-w-[70px]">
                                <span x-text="`${(item.quantity * item.sale_price).toLocaleString('uz-UZ')}`"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Payment & Checkout Panel -->
            <div class="p-3.5 border-t border-slate-800 bg-slate-950/90 space-y-3">
                <!-- Payment Method Buttons -->
                <div>
                    <label class="text-[11px] font-semibold text-slate-400 block mb-1">To'lov usuli:</label>
                    <div class="grid grid-cols-4 gap-1.5 text-xs font-bold">
                        <button type="button"
                                @click="onPaymentMethodChange('CASH')"
                                class="py-2 rounded-lg border transition-colors flex flex-col items-center gap-0.5"
                                :class="paymentMethod === 'CASH' ? 'bg-emerald-600 text-white border-emerald-500 shadow-sm' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'">
                            <span>💵</span>
                            <span class="text-[10px]">Naqd</span>
                        </button>
                        <button type="button"
                                @click="onPaymentMethodChange('CARD')"
                                class="py-2 rounded-lg border transition-colors flex flex-col items-center gap-0.5"
                                :class="paymentMethod === 'CARD' ? 'bg-blue-600 text-white border-blue-500 shadow-sm' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'">
                            <span>💳</span>
                            <span class="text-[10px]">Karta</span>
                        </button>
                        <button type="button"
                                @click="onPaymentMethodChange('BANK')"
                                class="py-2 rounded-lg border transition-colors flex flex-col items-center gap-0.5"
                                :class="paymentMethod === 'BANK' ? 'bg-indigo-600 text-white border-indigo-500 shadow-sm' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'">
                            <span>🏦</span>
                            <span class="text-[10px]">Bank</span>
                        </button>
                        <button type="button"
                                @click="onPaymentMethodChange('DEBT')"
                                class="py-2 rounded-lg border transition-colors flex flex-col items-center gap-0.5"
                                :class="paymentMethod === 'DEBT' ? 'bg-amber-600 text-white border-amber-500 shadow-sm' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'">
                            <span>⏳</span>
                            <span class="text-[10px]">Nasiya</span>
                        </button>
                    </div>
                </div>

                <!-- Paid Amount & Debt Calculation -->
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <label class="text-[11px] font-semibold text-slate-400 block mb-1">Olingan pul (so'm):</label>
                        <input type="number"
                               x-model.number="paidAmount"
                               @input="saveCartDraft()"
                               class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-sm font-bold text-white text-right focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold text-slate-400 block mb-1">Qarz (Nasiya):</label>
                        <div class="w-full bg-slate-900/60 border border-slate-800 rounded-lg px-2.5 py-1.5 text-sm font-bold text-right"
                             :class="debtAmount > 0 ? 'text-amber-400' : 'text-slate-400'"
                             x-text="`${debtAmount.toLocaleString('uz-UZ')} so'm`">
                        </div>
                    </div>
                </div>

                <!-- Total Amount Banner -->
                <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider font-bold">Jami to'lov:</span>
                        <p class="text-xs text-slate-500">Tannarx (WAC): <span class="text-slate-400">~serverda</span></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xl font-black text-cyan-400"
                              x-text="`${totalAmount.toLocaleString('uz-UZ')} so'm`"></span>
                    </div>
                </div>

                <!-- Complete Sale Action Button (Multi-click protection) -->
                <button type="button"
                        @click="completeSale()"
                        :disabled="isProcessing || cart.length === 0"
                        class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-extrabold text-sm shadow-lg shadow-cyan-600/20 flex items-center justify-center gap-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isProcessing">💾 Savdoni Yakunlash (Chek)</span>
                    <span x-show="isProcessing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Tranzaksiya saqlanmoqda...
                    </span>
                </button>
            </div>
        </section>
    </div>

    <!-- 4. RECEIPT MODAL (Elektron kvitansiya & Chop etish) -->
    <div x-show="showReceiptModal"
         x-transition
         class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-5 shadow-2xl flex flex-col gap-4"
             id="receipt-printable">
            <!-- Modal Header -->
            <div class="text-center border-b border-slate-800 pb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 text-white flex items-center justify-center text-lg mx-auto mb-2">
                    💧
                </div>
                <h3 class="text-base font-black text-white">AquaOptom CRM</h3>
                <p class="text-[11px] text-slate-400">Optom Suv Do'koni</p>
                <div class="mt-2 inline-block px-2.5 py-0.5 rounded bg-amber-950/80 border border-amber-800/80 text-amber-300 text-[10px] font-bold uppercase tracking-wider">
                    ⚠️ LOKAL CHEK (OFFLINE NAVBATDA)
                </div>
            </div>

            <!-- Receipt Info -->
            <div class="text-xs space-y-1 font-mono text-slate-300">
                <div class="flex justify-between">
                    <span class="text-slate-500">Chek raqami:</span>
                    <span class="font-bold text-white" x-text="completedSale ? completedSale.local_invoice_number : ''"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Vaqt:</span>
                    <span x-text="completedSale ? new Date(completedSale.created_at).toLocaleString('uz-UZ') : ''"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Xaridor:</span>
                    <span class="font-semibold text-cyan-300" x-text="completedSale ? completedSale.customer_name : ''"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">To'lov:</span>
                    <span x-text="completedSale ? completedSale.payment_method : ''"></span>
                </div>
            </div>

            <!-- Items list -->
            <div class="border-t border-b border-slate-800 py-2 max-h-48 overflow-y-auto space-y-1.5 text-xs font-mono">
                <template x-for="it in (completedSale ? completedSale.items : [])" :key="it.variant_id">
                    <div class="flex justify-between">
                        <span class="truncate pr-2" x-text="`${it.product_name} (${it.quantity} dona)`"></span>
                        <span class="font-bold shrink-0" x-text="`${(it.quantity * it.sale_price).toLocaleString('uz-UZ')}`"></span>
                    </div>
                </template>
            </div>

            <!-- Totals -->
            <div class="space-y-1 text-xs font-mono">
                <div class="flex justify-between text-slate-400">
                    <span>Jami summa:</span>
                    <span class="font-bold text-white" x-text="completedSale ? `${completedSale.total_amount.toLocaleString('uz-UZ')} so'm` : ''"></span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>To'landi:</span>
                    <span class="text-emerald-400 font-bold" x-text="completedSale ? `${completedSale.paid_amount.toLocaleString('uz-UZ')} so'm` : ''"></span>
                </div>
                <div class="flex justify-between text-slate-400" x-show="completedSale && completedSale.debt_amount > 0">
                    <span>Nasiya qoldiq:</span>
                    <span class="text-amber-400 font-bold" x-text="completedSale ? `${completedSale.debt_amount.toLocaleString('uz-UZ')} so'm` : ''"></span>
                </div>
            </div>

            <!-- Modal Action Buttons (No-print) -->
            <div class="flex gap-2 pt-2 border-t border-slate-800">
                <button type="button"
                        @click="printReceipt()"
                        class="flex-1 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-colors">
                    🖨️ Chop etish
                </button>
                <button type="button"
                        @click="showReceiptModal = false; completedSale = null"
                        class="flex-1 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold transition-colors">
                    Yangi savdo
                </button>
            </div>
        </div>
    </div>

    <!-- 5. NEW CUSTOMER INLINE MODAL -->
    <div x-show="showNewCustomerModal"
         x-transition
         class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <h3 class="text-sm font-bold text-white">Yangi mijoz qo'shish (Offline UUID)</h3>
                <button type="button" @click="showNewCustomerModal = false" class="text-slate-400 hover:text-white">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 mb-1 font-medium">Mijoz ismi / Vakil *</label>
                    <input type="text"
                           x-model="newCustomer.name"
                           placeholder="Masalan: Sardor aka"
                           class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1 font-medium">Telefon raqami</label>
                    <input type="tel"
                           x-model="newCustomer.phone"
                           placeholder="+998901234567"
                           class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1 font-medium">Do'kon / Savdo nuqtasi nomi</label>
                    <input type="text"
                           x-model="newCustomer.store_name"
                           placeholder="Masalan: Omad Market"
                           class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
            </div>

            <div class="flex gap-2 pt-2 border-t border-slate-800">
                <button type="button"
                        @click="showNewCustomerModal = false"
                        class="flex-1 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold">
                    Bekor qilish
                </button>
                <button type="button"
                        @click="createOfflineCustomer()"
                        class="flex-1 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold">
                    Saqlash va Tanlash
                </button>
            </div>
        </div>
    </div>

    <!-- 6. PIN LOCK SCREEN OVERLAY -->
    <div x-show="isLocked"
         x-transition
         class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xs w-full p-6 text-center space-y-4 shadow-2xl">
            <div class="w-12 h-12 rounded-2xl bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-2xl mx-auto">
                🔒
            </div>
            <div>
                <h3 class="text-base font-black text-white">Kassa Qulflangan</h3>
                <p class="text-xs text-slate-400 mt-1">Davom ettirish uchun PIN kodni kiriting (Standart: 1234)</p>
            </div>

            <!-- PIN Display Dots -->
            <div class="flex justify-center gap-2 py-2">
                <template x-for="i in 4" :key="i">
                    <div class="w-3.5 h-3.5 rounded-full border border-slate-600 transition-colors"
                         :class="pinInput.length >= i ? 'bg-cyan-400 border-cyan-400' : 'bg-transparent'"></div>
                </template>
            </div>

            <!-- Numeric keypad -->
            <div class="grid grid-cols-3 gap-2 text-base font-bold text-white max-w-[200px] mx-auto">
                <template x-for="num in [1,2,3,4,5,6,7,8,9]" :key="num">
                    <button type="button"
                            @click="appendPin(num)"
                            class="h-12 rounded-xl bg-slate-800 hover:bg-slate-700 flex items-center justify-center active:scale-95 transition-transform"
                            x-text="num"></button>
                </template>
                <button type="button"
                        @click="clearPin()"
                        class="h-12 rounded-xl bg-slate-800/60 hover:bg-slate-800 text-xs text-rose-400 flex items-center justify-center font-semibold">
                    Tozalash
                </button>
                <button type="button"
                        @click="appendPin(0)"
                        class="h-12 rounded-xl bg-slate-800 hover:bg-slate-700 flex items-center justify-center active:scale-95 transition-transform">
                    0
                </button>
                <button type="button"
                        @click="unlockSession()"
                        class="h-12 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-xs text-white flex items-center justify-center font-bold">
                    Ochish
                </button>
            </div>
        </div>
    </div>

    <!-- Service Worker Registration Script -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').then((reg) => {
                    console.log('AquaOptom POS SW scope:', reg.scope);
                }).catch((err) => {
                    console.warn('AquaOptom POS SW register error:', err);
                });
            });
        }
    </script>
</body>
</html>
