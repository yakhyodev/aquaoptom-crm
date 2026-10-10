<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tezkor sotuv — AquaOptom CRM</title>

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0284c7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AquaOptom POS">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <x-theme-init />

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
<body class="offline-workspace bg-slate-50 text-slate-900 min-h-screen font-sans antialiased flex flex-col select-none" x-data="aquaPos()" x-cloak>

    <!-- 1. HEADER BAR -->
    <header class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 flex flex-wrap gap-2 items-center justify-between shadow-md sticky top-0 z-30">
        <!-- Brand & Device info -->
        <div class="flex items-center gap-3">
            <a href="/dashboard" class="flex items-center gap-2.5 group" title="Boshqaruv paneliga qaytish">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-slate-900 text-base shadow-sm group-hover:scale-105 transition-transform">
                    💧
                </div>
                <div>
                    <h1 class="text-sm font-black text-slate-900 leading-tight flex items-center gap-1.5">
                        AquaOptom POS
                        <span class="text-xs bg-cyan-50 text-cyan-700 border border-cyan-200/60 px-1.5 py-0.5 rounded font-mono">PWA</span>
                    </h1>
                    <p class="text-xs text-slate-600 flex items-center gap-1">
                        <span x-text="deviceLease ? deviceLease.device_code : 'Qurilma ulanmagan'"></span> •
                        <span x-text="warehouseName"></span>
                    </p>
                </div>
            </a>
        </div>

        <!-- Status Badges & Quick Tools -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs">
            <x-theme-toggle />
            <!-- Online / Offline Indicator -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold border transition-colors"
                 :class="isOnline ? 'bg-emerald-50/80 text-emerald-700 border-emerald-200/80' : 'bg-rose-50/80 text-rose-700 border-rose-200/80 animate-pulse'">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-500 shadow-sm shadow-emerald-500' : 'bg-rose-500 shadow-sm shadow-rose-500'"></span>
                <span x-text="isOnline ? 'Internet bor' : 'Internet yo‘q'" class="tracking-wide"></span>
            </div>

            <!-- Outbox queue counter & modal trigger -->
            <button type="button"
                    @click="openOutboxModal()"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-medium transition-colors"
                    :class="outboxCount > 0 ? 'bg-amber-50/70 text-amber-700 border-amber-200 hover:bg-amber-50/60' : 'bg-slate-100/60 text-slate-700 border-slate-300/60 hover:bg-slate-100'"
                    title="Navbatdagi amallar va sinxronlash holati">
                <span>📦</span>
                <span class="hidden sm:inline">Navbat:</span>
                <span class="font-bold font-mono" x-text="outboxCount"></span>
                <template x-if="needsReviewCount > 0">
                    <span class="bg-rose-600 text-slate-900 text-xs px-1 rounded-full font-bold animate-pulse" title="Admin tekshiruvi kutilmoqda" x-text="`! ${needsReviewCount}`"></span>
                </template>
            </button>

            <!-- Sync Now Button -->
            <button type="button"
                    @click="syncNow()"
                    :disabled="isSyncing || isBootstrapping || !isOnline"
                    class="px-2.5 py-1 rounded-lg bg-cyan-600/20 text-cyan-700 border border-cyan-500/40 hover:bg-cyan-600/30 font-medium transition-colors flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Ma’lumotlarni internet orqali yangilash">
                <span :class="isSyncing ? 'animate-spin' : ''">🔄</span>
                <span class="hidden md:inline" x-text="isSyncing ? 'Sinxronlanmoqda...' : 'Sinxronlash'"></span>
            </button>

            <!-- Last Sync Timestamp (desktop) -->
            <div x-show="lastSyncTime" class="hidden xl:flex items-center gap-1 text-xs text-slate-600">
                <span>🕒</span>
                <span x-text="`Sync: ${new Date(lastSyncTime).toLocaleTimeString('uz-UZ', { hour: '2-digit', minute: '2-digit' })}`"></span>
            </div>

            <!-- Storage Quota Badge -->
            <div class="hidden lg:flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100/40 text-slate-600 border border-slate-200 text-xs"
                 :title="`Ishlatilgan: ${storageInfo.usedMB} MB / Jami: ${storageInfo.totalMB} MB (${storageInfo.isPersistent ? 'Doimiy saqlash faol' : 'Vaqtinchalik'})`">
                <span>💾</span>
                <span x-text="`${storageInfo.usedMB} MB`"></span>
                <span x-show="storageInfo.isPersistent" class="text-emerald-700 font-bold" title="Persistent Storage">✓</span>
            </div>

            <!-- Emergency Export -->
            <button type="button"
                    @click="exportPendingBackup()"
                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 hover:text-slate-900 transition-colors"
                    title="Favqulodda zaxira (JSON eksport)">
                📥
            </button>

            <!-- PIN Lock -->
            <button type="button"
                    @click="lockSession()"
                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 hover:text-slate-900 transition-colors"
                    title="Kassani qulflash">
                🔒
            </button>
        </div>
    </header>

    <section x-show="!deviceLease || needsDeviceSetup" class="px-4 py-3 bg-blue-50 text-blue-700 border-b border-blue-200" aria-label="Internetsiz sotuvni tayyorlash">
        <h2 class="text-sm font-bold">Bu qurilmani internetsiz sotuvga tayyorlang</h2>
        <p class="text-xs mt-1">Do‘kon egasi «Sozlamalar → Qurilmalar» bo‘limida qurilmani xodimga biriktiradi va sotiladigan mahsulotlarni ajratadi. Keyin ma’lumotlarni shu brauzerga yuklang.</p>
        <p class="text-xs mt-1">Bitta xodimga bir nechta qurilma biriktirilgan bo‘lsa, egasi bilan qurilma ulanishini aniqlashtiring.</p>
        <div class="flex flex-wrap gap-2 mt-2">
            <a href="/admin" class="px-3 py-1.5 rounded-lg bg-white border border-blue-200 font-semibold text-xs">Sozlamalarni ochish</a>
            <button type="button" @click="bootstrapFromServer()" :disabled="isBootstrapping || !isOnline" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white font-semibold text-xs disabled:opacity-50" x-text="isBootstrapping ? 'Yuklanmoqda…' : 'Ma’lumotlarni yuklash'"></button>
            <a href="/sotuv" class="px-3 py-1.5 rounded-lg border border-blue-200 font-semibold text-xs">Internet orqali sotuv qilish →</a>
        </div>
    </section>

    <!-- 1.1 LEASE EXPIRY WARNING BANNER -->
    <div x-show="leaseWarning"
         x-transition
         class="bg-amber-50 text-amber-700 border-b border-amber-200 px-4 py-2 text-xs font-semibold flex items-center justify-between z-20">
        <div class="flex items-center gap-2">
            <span>⚠️</span>
            <span x-text="leaseWarning"></span>
        </div>
        <button type="button" @click="syncNow()" :disabled="isProcessing || !isOnline" class="underline text-amber-700 hover:text-slate-900">Qurilma ruxsatini yangilash</button>
    </div>

    <!-- 2. GLOBAL ALERT BANNER -->
    <div x-show="alertMessage"
         x-transition
         class="px-4 py-2 text-xs flex items-center justify-between font-medium border-b z-20"
         :class="{
             'bg-rose-50/90 text-rose-700 border-rose-200': alertMessage && alertMessage.type === 'error',
             'bg-emerald-50/90 text-emerald-700 border-emerald-200': alertMessage && alertMessage.type === 'success',
             'bg-amber-50/90 text-amber-700 border-amber-200': alertMessage && alertMessage.type === 'warning',
             'bg-blue-50/90 text-blue-700 border-blue-200': alertMessage && alertMessage.type === 'info'
         }">
        <div class="flex items-center gap-2">
            <span x-text="alertMessage && alertMessage.type === 'error' ? '⚠️' : (alertMessage && alertMessage.type === 'success' ? '✅' : 'ℹ️')"></span>
            <span x-text="alertMessage ? alertMessage.text : ''"></span>
        </div>
        <button type="button" @click="closeAlert()" class="text-slate-600 hover:text-slate-900 p-1">✕</button>
    </div>

    <!-- 3. MAIN POS WORKSPACE -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">

        <!-- A. LEFT COLUMN: CATALOG & SEARCH (60% on desktop) -->
        <section class="flex-1 flex flex-col bg-slate-50 border-r border-slate-200 overflow-hidden">
            <!-- Search & Filter Header -->
            <div class="p-3 sm:p-4 border-b border-slate-200/80 bg-slate-50/40 space-y-3">
                <!-- Search input -->
                <div class="relative">
                    <input type="text"
                           aria-label="Mahsulotni qidirish" x-model="searchQuery"
                           placeholder="Mahsulot nomi, litri, SKU yoki shtrix-kod..."
                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-colors">
                    <span x-show="searchQuery"
                          @click="searchQuery = ''"
                          class="absolute right-3 top-2.5 text-slate-600 hover:text-slate-700 cursor-pointer text-sm">✕</span>
                </div>

                <!-- Volume chips filter -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
                    <button type="button"
                            @click="selectedVolume = 'all'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === 'all' ? 'bg-cyan-600 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                        Barchasi
                    </button>
                    <button type="button"
                            @click="selectedVolume = '0.5'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '0.5' ? 'bg-cyan-600 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                        0.5 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '1.0'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '1.0' ? 'bg-cyan-600 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                        1.0 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '1.5'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '1.5' ? 'bg-cyan-600 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                        1.5 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '5'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '5' ? 'bg-cyan-600 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                        5 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '10'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '10' ? 'bg-cyan-600 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                        10 L
                    </button>
                    <button type="button"
                            @click="selectedVolume = '18.9'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition-colors shrink-0"
                            :class="selectedVolume === '18.9' ? 'bg-cyan-600 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                        18.9 L / 19 L
                    </button>
                </div>
            </div>

            <!-- Product Cards Grid -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4">
                <template x-if="filteredCatalog.length === 0">
                    <div class="h-64 flex flex-col items-center justify-center text-center p-6 text-slate-600">
                        <span class="text-4xl mb-2">📦</span>
                        <p class="font-medium text-sm" x-text="!deviceLease || needsDeviceSetup ? 'Avval qurilmani tayyorlab, mahsulotlarni yuklang' : (catalog.length ? 'Qidiruvga mos mahsulot topilmadi' : 'Hali mahsulotlar yuklanmagan')"></p>
                        <p class="text-xs text-slate-600 mt-1" x-show="isOnline && deviceLease && !needsDeviceSetup">
                            Internet ulanganda yuqoridagi «Sinxronlash» tugmasini bosing
                        </p>
                    </div>
                </template>

                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3">
                    <template x-for="item in filteredCatalog" :key="item.id">
                        <div @click="getAvailableStock(item.id) > 0 ? addToCart(item) : showAlert('warning', `'${item.product_name}' uchun sotish limiti tugagan!`)"
                             class="rounded-xl border p-3 flex flex-col justify-between transition-all cursor-pointer relative overflow-hidden group select-none"
                             :class="getAvailableStock(item.id) > 0 ? 'bg-slate-50 border-slate-200 hover:border-cyan-500/60 hover:bg-slate-100/70' : 'bg-slate-50/40 border-slate-200/40 opacity-60 cursor-not-allowed'">

                            <!-- Top badge: Volume & Limit -->
                            <div class="flex items-start justify-between gap-1 mb-2">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-slate-100 text-cyan-700 border border-slate-300/60"
                                      x-text="item.volume_name || `${item.volume_litres}L`"></span>

                                <!-- Quota badge -->
                                <span class="text-xs font-bold px-1.5 py-0.5 rounded"
                                      :class="getAvailableStock(item.id) > 0 ? 'bg-emerald-50/80 text-emerald-700 border border-emerald-200/80' : 'bg-rose-50/80 text-rose-700 border border-rose-200/80'"
                                      :title="`Qurilmaga ajratilgan erkin qoldiq`">
                                    <span x-text="getAvailableStock(item.id) > 0 ? `${getAvailableStock(item.id)} dona` : 'Tugagan'"></span>
                                </span>
                            </div>

                            <!-- Product Name & SKU -->
                            <div class="mb-3">
                                <h3 class="text-sm font-bold text-slate-900 group-hover:text-cyan-700 transition-colors line-clamp-2"
                                    x-text="item.product_name"></h3>
                                <p class="text-xs font-mono text-slate-600 mt-0.5" x-text="item.sku"></p>
                            </div>

                            <!-- Bottom: Price and Add button -->
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200/60">
                                <div>
                                    <span class="text-xs text-slate-600">Narxi:</span>
                                    <p class="text-sm font-extrabold text-cyan-700"
                                       x-text="`${(item.default_sale_price || 0).toLocaleString('uz-UZ')} so'm`"></p>
                                </div>
                                <div class="w-7 h-7 rounded-lg bg-cyan-600/20 text-cyan-700 flex items-center justify-center font-bold text-sm group-hover:bg-cyan-600 group-hover:text-slate-900 transition-colors"
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
        <section class="w-full lg:w-[440px] xl:w-[480px] flex flex-col bg-slate-50 border-t lg:border-t-0 lg:border-l border-slate-200 overflow-hidden shadow-xl shrink-0">

            <!-- Customer Selector Bar -->
            <div class="grid grid-cols-2 gap-2 p-3 border-b border-slate-200">
                <button type="button" @click="switchSaleMode('quick')" :aria-pressed="saleMode === 'quick'" class="trade-button" :class="saleMode === 'quick' ? 'trade-button-primary' : 'trade-button-secondary'">⚡ Tezkor sotuv</button>
                <button type="button" @click="switchSaleMode('customer')" :aria-pressed="saleMode === 'customer'" class="trade-button" :class="saleMode === 'customer' ? 'trade-button-primary' : 'trade-button-secondary'">👤 Mijozga sotuv</button>
            </div>
            <p x-show="saleMode === 'quick'" class="p-3 text-xs text-slate-600">Mijoz tanlanmaydi. Jami summa to‘liq to‘lanadi. Donani klaviaturada yozishingiz mumkin.</p>
            <div x-show="saleMode === 'customer'">
            <div class="p-3 border-b border-slate-200 bg-slate-50/70 space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                    <span>Xaridor (Mijoz):</span>
                    <button type="button"
                            @click="showNewCustomerModal = true"
                            class="text-cyan-700 hover:text-cyan-700 flex items-center gap-1 font-bold">
                        <span>+</span> Yangi mijoz
                    </button>
                </div>
                <div class="relative">
                    <div x-show="selectedCustomerId" class="flex items-center justify-between gap-2 p-2"><strong class="text-sm" x-text="selectedCustomerLabel"></strong><button type="button" @click="selectedCustomerId = ''; customerQuery = ''; saveCartDraft()" class="trade-button trade-button-secondary">Almashtirish</button></div>
                    <div x-show="!selectedCustomerId">
                        <input type="search" x-model="customerQuery" aria-label="Mijozni qidirish" placeholder="Ism, telefon yoki do‘kon nomini yozing" class="w-full rounded-xl border border-slate-300 p-3 text-sm" @keydown.enter.prevent="if (filteredCustomers[0]) { selectedCustomerId = String(filteredCustomers[0].id); saveCartDraft(); }">
                        <div class="max-h-48 overflow-y-auto mt-2">
                            <template x-for="c in filteredCustomers" :key="c.id"><button type="button" @click="selectedCustomerId = String(c.id); saveCartDraft()" class="block w-full p-3 text-left text-sm border-b border-slate-200" x-text="[c.name, c.store_name, c.phone].filter(Boolean).join(' · ')"></button></template>
                            <p x-show="!filteredCustomers.length" class="p-3 text-sm">Topilmadi. «Yangi mijoz» orqali qo‘shing.</p>
                        </div>
                        <p class="text-xs text-slate-600 mt-2">Birinchi 20 ta mijoz alifbo bo‘yicha. Keraklisini yozib qidiring.</p>
                    </div>
                </div>
            </div>
            </div>

            <!-- Cart Table Items -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                <div class="flex items-center justify-between text-xs text-slate-600 pb-1 border-b border-slate-200">
                    <span class="font-bold">Savat (<span x-text="cart.length"></span> ta tovar)</span>
                    <button type="button"
                            x-show="cart.length > 0"
                            @click="clearCart()"
                            class="text-rose-700 hover:text-rose-700 font-semibold text-xs">
                        Savatni tozalash
                    </button>
                </div>

                <template x-if="cart.length === 0">
                    <div class="h-44 flex flex-col items-center justify-center text-center p-4 text-slate-600">
                        <span class="text-3xl mb-1">🛒</span>
                        <p class="text-xs">Savat bo'sh</p>
                        <p class="text-xs text-slate-600 mt-0.5">Katalogdan mahsulotlarni tanlang</p>
                    </div>
                </template>

                <!-- Cart rows -->
                <template x-for="(item, index) in cart" :key="item.variant_id">
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col gap-2 shadow-sm">
                        <!-- Row 1: Name and Delete button -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 leading-tight" x-text="item.product_name"></h4>
                                <span class="text-xs text-cyan-700 font-medium" x-text="item.volume_name"></span>
                            </div>
                            <button type="button"
                                    @click="removeFromCart(index)"
                                    class="text-slate-600 hover:text-rose-700 p-1 text-xs">
                                🗑️
                            </button>
                        </div>

                        <!-- Row 2: Qty, Unit Price, Line Total -->
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <!-- Qty buttons -->
                            <div class="flex items-center border border-slate-300 rounded-lg overflow-hidden bg-slate-50">
                                <button type="button"
                                        @click="updateQuantity(index, item.quantity - 1)"
                                        class="px-2.5 py-1 text-slate-700 hover:bg-slate-100 font-bold">-</button>
                                <input type="number"
                                       :value="item.quantity"
                                       @change="await updateQuantity(index, $event.target.value); $el.value = item.quantity"
                                       @keydown.enter.prevent="$el.blur()"
                                       @focus="$el.select()"
                                       aria-label="Mahsulot donasi" inputmode="numeric" step="1"
                                       min="1"
                                       class="w-20 text-center bg-transparent text-slate-900 font-bold text-xs focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button"
                                        @click="updateQuantity(index, item.quantity + 1)"
                                        class="px-2.5 py-1 text-slate-700 hover:bg-slate-100 font-bold">+</button>
                            </div>

                            <!-- Unit Price (editable if permitted) -->
                            <div class="flex items-center gap-1">
                                <span class="text-xs text-slate-600">×</span>
                                <input type="number"
                                       :value="item.sale_price"
                                       @change="await updatePrice(index, $event.target.value); $el.value = item.sale_price" aria-label="Bir dona narxi"
                                       class="w-20 bg-slate-50 border border-slate-300 rounded px-1.5 py-1 text-right text-xs font-semibold text-slate-800 focus:outline-none focus:border-cyan-500">
                            </div>

                            <!-- Line Total -->
                            <div class="text-right font-extrabold text-cyan-700 min-w-[70px]">
                                <span x-text="`${(item.quantity * item.sale_price).toLocaleString('uz-UZ')}`"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Payment & Checkout Panel -->
            <div class="p-3.5 border-t border-slate-200 bg-slate-50/90 space-y-3">
                <!-- Payment Method Buttons -->
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">To'lov usuli:</label>
                    <div class="grid grid-cols-3 gap-1.5 text-xs font-bold" x-show="saleMode === 'customer'">
                        <button type="button"
                                @click="onPaymentMethodChange('CASH')"
                                class="py-2 rounded-lg border transition-colors flex flex-col items-center gap-0.5"
                                :class="paymentMode === 'FULL' ? 'bg-emerald-600 text-white border-emerald-500 shadow-sm' : 'bg-slate-50 text-slate-600 border-slate-200 hover:text-slate-900'">
                            <span>💵</span>
                            <span class="text-xs">To‘liq to‘lov</span>
                        </button>
                        <button type="button" @click="onPaymentMethodChange('PARTIAL')" class="py-2 rounded-lg border transition-colors" :class="paymentMode === 'PARTIAL' ? 'bg-blue-600 text-white border-blue-500' : 'bg-slate-50 text-slate-600 border-slate-200'">Qisman to‘lov</button>
                        <button type="button"
                                @click="onPaymentMethodChange('DEBT')"
                                class="py-2 rounded-lg border transition-colors flex flex-col items-center gap-0.5"
                                :class="paymentMode === 'DEBT' ? 'bg-amber-600 text-white border-amber-500 shadow-sm' : 'bg-slate-50 text-slate-600 border-slate-200 hover:text-slate-900'">
                            <span>⏳</span>
                            <span class="text-xs">To‘liq nasiya</span>
                        </button>
                    </div>
                </div>

                <!-- Paid Amount & Debt Calculation -->
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <label for="offline-paid-amount" class="text-xs font-semibold text-slate-600 block mb-1">Olingan pul (so'm):</label>
                        <input id="offline-paid-amount" type="number"
                               x-model.number="paidAmount"
                               :readonly="paymentMode !== 'PARTIAL'" min="0" :max="totalAmount" step="1" inputmode="numeric"
                               @input="saveCartDraft()"
                               class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-sm font-bold text-slate-900 text-right focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Qarz (Nasiya):</label>
                        <div class="w-full bg-slate-50/60 border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm font-bold text-right"
                             :class="debtAmount > 0 ? 'text-amber-700' : 'text-slate-600'"
                             x-text="`${debtAmount.toLocaleString('uz-UZ')} so'm`">
                        </div>
                    </div>
                </div>

                <!-- Total Amount Banner -->
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-slate-600  font-bold">Jami to'lov:</span>
                        <p class="text-xs text-slate-600" x-text="paymentMode === 'FULL' ? 'Hammasi hozir to‘lanadi. Qarz qolmaydi.' : 'Olingan pul va qoladigan qarzni tekshiring.'"></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xl font-black text-cyan-700"
                              x-text="`${totalAmount.toLocaleString('uz-UZ')} so'm`"></span>
                    </div>
                </div>

                <!-- Complete Sale Action Button (Multi-click protection) -->
                <button type="button"
                        @click="completeSale()"
                        :disabled="isProcessing || cart.length === 0"
                        class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-900 font-extrabold text-sm shadow-lg shadow-cyan-600/20 flex items-center justify-center gap-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isProcessing">💾 Savdoni Yakunlash (Chek)</span>
                    <span x-show="isProcessing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-slate-900" viewBox="0 0 24 24" fill="none">
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
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-50 border border-slate-200 rounded-2xl max-w-sm w-full p-5 shadow-2xl flex flex-col gap-4"
             id="receipt-printable">
            <!-- Modal Header -->
            <div class="text-center border-b border-slate-200 pb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 text-slate-900 flex items-center justify-center text-lg mx-auto mb-2">
                    💧
                </div>
                <h3 class="text-base font-black text-slate-900">AquaOptom CRM</h3>
                <p class="text-xs text-slate-600">Optom Suv Do'koni</p>
                <div class="mt-2 inline-block px-2.5 py-0.5 rounded bg-amber-50/80 border border-amber-200/80 text-amber-700 text-xs font-bold ">
                    ⚠️ Internetga yuborilishi kutilayotgan chek
                </div>
            </div>

            <!-- Receipt Info -->
            <div class="text-xs space-y-1 font-mono text-slate-700">
                <div class="flex justify-between">
                    <span class="text-slate-600">Chek raqami:</span>
                    <span class="font-bold text-slate-900" x-text="completedSale ? completedSale.local_invoice_number : ''"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Vaqt:</span>
                    <span x-text="completedSale ? new Date(completedSale.created_at).toLocaleString('uz-UZ') : ''"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Xaridor:</span>
                    <span class="font-semibold text-cyan-700" x-text="completedSale ? completedSale.customer_name : ''"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">To'lov:</span>
                    <span x-text="completedSale ? (completedSale.payment_method === 'DEBT' ? 'Nasiya' : 'To‘langan') : ''"></span>
                </div>
            </div>

            <!-- Items list -->
            <div class="border-t border-b border-slate-200 py-2 max-h-48 overflow-y-auto space-y-1.5 text-xs font-mono">
                <template x-for="it in (completedSale ? completedSale.items : [])" :key="it.variant_id">
                    <div class="flex justify-between">
                        <span class="truncate pr-2" x-text="`${it.product_name} (${it.quantity} dona)`"></span>
                        <span class="font-bold shrink-0" x-text="`${(it.quantity * it.sale_price).toLocaleString('uz-UZ')}`"></span>
                    </div>
                </template>
            </div>

            <!-- Totals -->
            <div class="space-y-1 text-xs font-mono">
                <div class="flex justify-between text-slate-600">
                    <span>Jami summa:</span>
                    <span class="font-bold text-slate-900" x-text="completedSale ? `${completedSale.total_amount.toLocaleString('uz-UZ')} so'm` : ''"></span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>To'landi:</span>
                    <span class="text-emerald-700 font-bold" x-text="completedSale ? `${completedSale.paid_amount.toLocaleString('uz-UZ')} so'm` : ''"></span>
                </div>
                <div class="flex justify-between text-slate-600" x-show="completedSale && completedSale.debt_amount > 0">
                    <span>Nasiya qoldiq:</span>
                    <span class="text-amber-700 font-bold" x-text="completedSale ? `${completedSale.debt_amount.toLocaleString('uz-UZ')} so'm` : ''"></span>
                </div>
            </div>

            <!-- Modal Action Buttons (No-print) -->
            <div class="flex gap-2 pt-2 border-t border-slate-200">
                <button type="button"
                        @click="printReceipt()"
                        class="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors">
                    🖨️ Chop etish
                </button>
                <button type="button"
                        @click="showReceiptModal = false; completedSale = null"
                        class="flex-1 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-slate-900 text-xs font-bold transition-colors">
                    Yangi savdo
                </button>
            </div>
        </div>
    </div>

    <!-- 5. NEW CUSTOMER INLINE MODAL -->
    <div x-show="showNewCustomerModal"
         x-transition
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-50 border border-slate-200 rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <h3 class="text-sm font-bold text-slate-900">Yangi mijoz qo‘shish</h3>
                <button type="button" @click="showNewCustomerModal = false" class="text-slate-600 hover:text-slate-900">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-600 mb-1 font-medium">Mijoz ismi / Vakil *</label>
                    <input type="text"
                           x-model="newCustomer.name"
                           placeholder="Masalan: Sardor aka"
                           class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-slate-900 focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-slate-600 mb-1 font-medium">Telefon raqami</label>
                    <input type="tel"
                           x-model="newCustomer.phone"
                           placeholder="+998901234567"
                           class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-slate-900 focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-slate-600 mb-1 font-medium">Do'kon / Savdo nuqtasi nomi</label>
                    <input type="text"
                           x-model="newCustomer.store_name"
                           placeholder="Masalan: Omad Market"
                           class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-slate-900 focus:outline-none focus:border-cyan-500">
                </div>
            </div>

            <div class="flex gap-2 pt-2 border-t border-slate-200">
                <button type="button"
                        @click="showNewCustomerModal = false"
                        class="flex-1 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                    Bekor qilish
                </button>
                <button type="button"
                        @click="createOfflineCustomer()"
                        class="flex-1 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-slate-900 text-xs font-bold">
                    Saqlash va Tanlash
                </button>
            </div>
        </div>
    </div>

    <!-- 5.1 SYNC OUTBOX & AUDIT MODAL -->
    <div x-show="showOutboxModal"
         x-transition
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
        <div class="bg-slate-50 border border-slate-200 rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <span class="text-lg">📦</span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            Lokal Navbat va Sinxronlash
                            <span class="text-xs px-2 py-0.5 rounded-full font-mono font-semibold"
                                  :class="outboxCount > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                                  x-text="`${outboxCount} ta kutilmoqda`"></span>
                        </h3>
                        <p class="text-xs text-slate-600">
                            Har bir amal barqaror <code class="font-mono text-cyan-700">operation_id</code> bilan saqlanadi va server tasdig'idan (ACK) keyin ham arxivlanadi.
                        </p>
                    </div>
                </div>
                <button type="button" @click="showOutboxModal = false" class="text-slate-600 hover:text-slate-900 p-1">✕</button>
            </div>

            <!-- Filter tabs -->
            <div class="px-5 py-2.5 bg-slate-50/30 border-b border-slate-200 flex flex-wrap gap-1.5 text-xs">
                <button type="button"
                        @click="outboxFilter = 'all'"
                        class="px-2.5 py-1 rounded-lg font-medium transition-colors"
                        :class="outboxFilter === 'all' ? 'bg-cyan-600 text-slate-900 font-bold' : 'bg-slate-100 text-slate-600 hover:text-slate-900'">
                    Barchasi (<span x-text="outboxItems.length"></span>)
                </button>
                <button type="button"
                        @click="outboxFilter = 'PENDING'"
                        class="px-2.5 py-1 rounded-lg font-medium transition-colors"
                        :class="outboxFilter === 'PENDING' ? 'bg-amber-600 text-slate-900 font-bold' : 'bg-slate-100 text-amber-700 hover:text-slate-900'">
                    Kutilmoqda (<span x-text="outboxItems.filter(i => i.status === 'PENDING').length"></span>)
                </button>
                <button type="button"
                        @click="outboxFilter = 'APPLIED'"
                        class="px-2.5 py-1 rounded-lg font-medium transition-colors"
                        :class="outboxFilter === 'APPLIED' ? 'bg-emerald-600 text-slate-900 font-bold' : 'bg-slate-100 text-emerald-700 hover:text-slate-900'">
                    Yuborildi (<span x-text="outboxItems.filter(i => i.status === 'APPLIED').length"></span>)
                </button>
                <button type="button"
                        @click="outboxFilter = 'NEEDS_REVIEW'"
                        class="px-2.5 py-1 rounded-lg font-medium transition-colors"
                        :class="outboxFilter === 'NEEDS_REVIEW' ? 'bg-rose-600 text-slate-900 font-bold' : 'bg-slate-100 text-rose-700 hover:text-slate-900'">
                    Tekshiruvda (<span x-text="outboxItems.filter(i => i.status === 'NEEDS_REVIEW').length"></span>)
                </button>
                <button type="button"
                        @click="outboxFilter = 'FAILED'"
                        class="px-2.5 py-1 rounded-lg font-medium transition-colors"
                        :class="outboxFilter === 'FAILED' ? 'bg-red-600 text-slate-900 font-bold' : 'bg-slate-100 text-red-700 hover:text-slate-900'">
                    Xatolik (<span x-text="outboxItems.filter(i => i.status === 'FAILED').length"></span>)
                </button>
            </div>

            <!-- Outbox items list -->
            <div class="flex-1 overflow-y-auto p-5 space-y-2.5 divide-y divide-slate-200/40">
                <template x-if="filteredOutboxItems().length === 0">
                    <div class="text-center py-10 text-slate-600 text-xs">
                        Ushbu filtr bo'yicha amallar mavjud emas.
                    </div>
                </template>

                <template x-for="item in filteredOutboxItems()" :key="item.operation_id">
                    <div class="pt-2.5 first:pt-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded font-mono font-bold text-xs"
                                      :class="{
                                          'bg-blue-50 text-blue-700 border border-blue-200': item.type === 'CREATE_SALE',
                                          'bg-purple-50 text-purple-700 border border-purple-200': item.type === 'VOID_SALE',
                                          'bg-emerald-50 text-emerald-700 border border-emerald-200': item.type === 'CREATE_CUSTOMER',
                                          'bg-amber-50 text-amber-700 border border-amber-200': item.type === 'CUSTOMER_PAYMENT'
                                      }"
                                      x-text="item.type"></span>

                                <span class="font-mono text-xs text-slate-600" x-text="`ID: ${item.operation_id.slice(0, 8)}...`"></span>

                                <span class="text-xs text-slate-600" x-text="new Date(item.created_at).toLocaleTimeString('uz-UZ')"></span>
                            </div>

                            <!-- Details description -->
                            <div class="text-slate-700 text-xs">
                                <template x-if="item.type === 'CREATE_SALE'">
                                    <span>
                                        Savdo: <span class="font-semibold text-slate-900" x-text="`${(item.payload.items || []).reduce((acc, i) => acc + (i.quantity || 0), 0)} dona`"></span> •
                                        To'lov: <span class="font-semibold text-slate-900" x-text="item.payload.payment_method === 'DEBT' ? 'Nasiya' : 'To‘langan'"></span>
                                        <template x-if="item.payload.paid_amount">
                                            <span x-text="`(${parseInt(item.payload.paid_amount).toLocaleString('uz-UZ')} so'm)`"></span>
                                        </template>
                                    </span>
                                </template>
                                <template x-if="item.type === 'VOID_SALE'">
                                    <span class="text-rose-700">
                                        Asl savdo (#<span x-text="(item.payload.original_operation_id || '').slice(0, 8)"></span>...) bekor qilindi. Sabab: <span x-text="item.payload.reason"></span>
                                    </span>
                                </template>
                                <template x-if="item.type === 'CREATE_CUSTOMER'">
                                    <span>
                                        Yangi mijoz: <strong class="text-slate-900" x-text="item.payload.name"></strong> (<span x-text="item.payload.phone || 'telefonsiz'"></span>)
                                    </span>
                                </template>
                            </div>

                            <!-- Error / Needs Review details -->
                            <template x-if="item.status === 'NEEDS_REVIEW'">
                                <div class="bg-rose-50/60 border border-rose-200 rounded-lg p-2 text-xs text-rose-700 space-y-0.5">
                                    <div class="font-bold flex items-center gap-1">
                                        <span>⚠️</span> Admin tekshiruvi kutilmoqda: <span x-text="item.error_code || 'NEEDS_REVIEW'"></span>
                                    </div>
                                    <div x-text="item.error_message || 'Saqlangan savdoni mas’ul xodim tekshirishi kerak.'"></div>
                                </div>
                            </template>
                            <template x-if="item.status === 'FAILED'">
                                <div class="text-xs text-red-700">
                                    Xatolik: <span x-text="item.error_message"></span> (Retry: <span x-text="item.retry_count"></span>)
                                </div>
                            </template>
                        </div>

                        <!-- Status badge & actions -->
                        <div class="flex items-center gap-2 self-end sm:self-center">
                            <template x-if="item.status === 'PENDING'">
                                <span class="px-2.5 py-1 rounded-full bg-amber-50/80 text-amber-700 border border-amber-200 text-xs font-bold">
                                    ⏳ Kutilmoqda
                                </span>
                            </template>
                            <template x-if="item.status === 'APPLIED'">
                                <div class="text-right">
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-50/80 text-emerald-700 border border-emerald-200 text-xs font-bold">
                                        ✅ Yuborildi
                                    </span>
                                    <div class="text-xs text-slate-600 font-mono mt-0.5" x-text="item.server_document_number"></div>
                                </div>
                            </template>
                            <template x-if="item.status === 'NEEDS_REVIEW'">
                                <span class="px-2.5 py-1 rounded-full bg-rose-50/80 text-rose-700 border border-rose-200 text-xs font-bold">
                                    🔍 Ko'rib chiqilmoqda
                                </span>
                            </template>
                            <template x-if="item.status === 'CONFLICT'">
                                <span class="px-2.5 py-1 rounded-full bg-purple-50/80 text-purple-700 border border-purple-200 text-xs font-bold">
                                    ⚡ Mojaro
                                </span>
                            </template>

                            <!-- Void action for CREATE_SALE items if not yet voided -->
                            <template x-if="item.type === 'CREATE_SALE' && !outboxItems.some(o => o.type === 'VOID_SALE' && o.payload?.original_operation_id === item.operation_id)">
                                <button type="button"
                                        @click="openVoidModal({ operation_id: item.operation_id, invoice_number: item.server_document_number || 'Lokal chek', total_amount: item.payload.total_amount })"
                                        class="px-2 py-1 rounded bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-700 border border-slate-300 hover:border-rose-200 text-xs font-semibold transition-colors"
                                        title="Ushbu savdoni bekor qilish">
                                    Bekor qilish
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3">
                <button type="button"
                        @click="exportPendingBackup()"
                        class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold flex items-center gap-1.5 transition-colors">
                    <span>📥</span> JSON zaxirani yuklash
                </button>

                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="syncNow()"
                            :disabled="isSyncing || !isOnline"
                            class="px-3.5 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-slate-900 text-xs font-bold flex items-center gap-1.5 disabled:opacity-40 transition-colors">
                        <span :class="isSyncing ? 'animate-spin' : ''">🔄</span>
                        <span x-text="isSyncing ? 'Sinxronlanmoqda...' : 'Hozir sinxronlash'"></span>
                    </button>
                    <button type="button"
                            @click="showOutboxModal = false"
                            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 5.2 VOID / CANCEL OFFLINE SALE MODAL -->
    <div x-show="showVoidModal"
         x-transition
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-50 border border-slate-200 rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <h3 class="text-sm font-bold text-rose-700 flex items-center gap-2">
                    <span>🛑</span> Savdoni Bekor Qilish
                </h3>
                <button type="button" @click="showVoidModal = false; voidSaleTarget = null" class="text-slate-600 hover:text-slate-900">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="bg-slate-50/60 p-3 rounded-xl border border-slate-200 space-y-1">
                    <div class="text-slate-600">Bekor qilinayotgan chek:</div>
                    <div class="font-mono font-bold text-slate-900 text-sm" x-text="voidSaleTarget ? (voidSaleTarget.local_invoice_number || voidSaleTarget.invoice_number || voidSaleTarget.operation_id) : ''"></div>
                </div>

                <div class="bg-amber-50/40 border border-amber-200/60 p-2.5 rounded-xl text-xs text-amber-700">
                    ℹ️ <strong>Arxitektura qoidasi:</strong> Haqiqiy savdo navbatdan o'chirilmaydi! Original saqlanadi, unga bog'langan <code>VOID_SALE</code> tuzatish amali serverga yuboriladi va sarflangan ombor rezervi darhol tiklanadi.
                </div>

                <div>
                    <label class="block text-slate-600 mb-1 font-medium">Bekor qilish sababi *</label>
                    <input type="text"
                           x-model="voidReason"
                           placeholder="Masalan: Mijoz tovardan voz kechdi"
                           class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-slate-900 focus:outline-none focus:border-rose-500">
                </div>
            </div>

            <div class="flex gap-2 pt-2 border-t border-slate-200">
                <button type="button"
                        @click="showVoidModal = false; voidSaleTarget = null"
                        class="flex-1 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                    Qaytish
                </button>
                <button type="button"
                        @click="confirmVoidSale()"
                        :disabled="isProcessing"
                        class="flex-1 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-slate-900 text-xs font-bold disabled:opacity-50">
                    Bekor qilishni tasdiqlash
                </button>
            </div>
        </div>
    </div>

    <!-- 6. PIN LOCK SCREEN OVERLAY -->
    <div x-show="isLocked"
         x-transition
         class="fixed inset-0 z-50 bg-slate-50/95 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-slate-50 border border-slate-200 rounded-3xl max-w-xs w-full p-6 text-center space-y-4 shadow-2xl">
            <div class="w-12 h-12 rounded-2xl bg-cyan-600/20 text-cyan-700 flex items-center justify-center text-2xl mx-auto">
                🔒
            </div>
            <div>
                <h3 class="text-base font-black text-slate-900">Kassa Qulflangan</h3>
                <p class="text-xs text-slate-600 mt-1">Davom ettirish uchun PIN kodni kiriting (Standart: 1234)</p>
            </div>

            <!-- PIN Display Dots -->
            <div class="flex justify-center gap-2 py-2">
                <template x-for="i in 4" :key="i">
                    <div class="w-3.5 h-3.5 rounded-full border border-slate-600 transition-colors"
                         :class="pinInput.length >= i ? 'bg-cyan-400 border-cyan-400' : 'bg-transparent'"></div>
                </template>
            </div>

            <!-- Numeric keypad -->
            <div class="grid grid-cols-3 gap-2 text-base font-bold text-slate-900 max-w-[200px] mx-auto">
                <template x-for="num in [1,2,3,4,5,6,7,8,9]" :key="num">
                    <button type="button"
                            @click="appendPin(num)"
                            class="h-12 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center active:scale-95 transition-transform"
                            x-text="num"></button>
                </template>
                <button type="button"
                        @click="clearPin()"
                        class="h-12 rounded-xl bg-slate-100/60 hover:bg-slate-100 text-xs text-rose-700 flex items-center justify-center font-semibold">
                    Tozalash
                </button>
                <button type="button"
                        @click="appendPin(0)"
                        class="h-12 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center active:scale-95 transition-transform">
                    0
                </button>
                <button type="button"
                        @click="unlockSession()"
                        class="h-12 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-xs text-slate-900 flex items-center justify-center font-bold">
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
