/**
 * AquaOptom CRM — Offline POS (Pure JS / Alpine.js Layer)
 * Independent of network connection, fully powered by IndexedDB.
 */

import { AquaDB } from './aqua-db.js';
import { AquaSync } from './aqua-sync.js';

export function aquaPos() {
    return {
        // Asosiy holatlar
        db: new AquaDB(),
        syncEngine: null,
        isOnline: navigator.onLine,
        isProcessing: false,
        isBootstrapping: false,
        needsDeviceSetup: false,
        isSyncing: false,
        lastSyncTime: null,
        leaseWarning: null,

        // Xavfsizlik va PIN qulf
        isLocked: false,
        pinInput: '',
        currentPin: '1234',

        // Kassa va Qurilma ma'lumotlari
        deviceLease: null,
        warehouseName: 'Asosiy Ombor',
        permissions: [],

        // Katalog va Mijozlar
        catalog: [],
        customers: [],
        customerQuery: '',
        stockAllocations: new Map(),
        creditAllocations: new Map(),

        // Qidiruv va Filtrlar
        searchQuery: '',
        selectedVolume: 'all',

        // Savat va Qoralama
        operationId: null,
        cart: [],
        selectedCustomerId: '',
        saleMode: 'quick',
        paymentMethod: 'CASH',
        paymentMode: 'FULL',
        paidAmount: 0,
        notes: '',

        // Modallar va Kvitansiya
        showReceiptModal: false,
        completedSale: null,
        showNewCustomerModal: false,
        newCustomer: {
            name: '',
            phone: '',
            store_name: ''
        },

        // Bekor qilish (Void) modali
        showVoidModal: false,
        voidSaleTarget: null,
        voidReason: 'Mijoz tovardan voz kechdi',

        // Outbox (Navbat) modali
        showOutboxModal: false,
        outboxItems: [],
        outboxFilter: 'all',
        needsReviewCount: 0,
        recentSales: [],

        // Tizim xabarlari va Navbat
        outboxCount: 0,
        storageInfo: { usedMB: 0, totalMB: 0, percent: 0, isPersistent: false },
        alertMessage: null,

        // Ko'p tabli sinxronlash kanali
        broadcastChannel: null,

        /**
         * 1. Initsializatsiya
         */
        async init() {
            try {
                await this.db.open();
                this.syncEngine = new AquaSync(this.db);

                // Tarmoq holatini kuzatish va avto-sync
                window.addEventListener('online', () => {
                    this.isOnline = true;
                    this.showAlert('info', "Internet aloqasi tiklandi. Avtomatik sinxronlash boshlanmoqda...");
                    this.triggerAutoSync('online_reconnect');
                });
                window.addEventListener('offline', () => {
                    this.isOnline = false;
                    this.showAlert('warning', "Internet uzildi. Offline rejim faol: Barcha savdolar lokal saqlanadi.");
                });

                // App reopen / visibility change
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible' && this.isOnline) {
                        this.triggerAutoSync('visibility_change');
                    }
                });
                window.addEventListener('focus', () => {
                    if (this.isOnline) {
                        this.triggerAutoSync('window_focus');
                    }
                });

                // Multi-tab sinxronlash (BroadcastChannel)
                if (window.BroadcastChannel) {
                    this.broadcastChannel = new BroadcastChannel('aqua_pos_channel');
                    this.broadcastChannel.onmessage = async (event) => {
                        if (event.data?.type === 'SYNC_COMPLETED' || event.data?.type === 'ALLOCATIONS_UPDATED' || event.data?.type === 'SALE_COMPLETED') {
                            await this.loadLocalData(false);
                            await this.updateOutboxCount();
                        }
                    };
                }

                // Periodic auto-sync (har 30 soniyada, agar online bo'lsa)
                setInterval(() => {
                    if (this.isOnline && !this.isSyncing) {
                        this.triggerAutoSync('periodic_interval');
                    }
                }, 30000);

                // Background Sync API (agar brauzerda mavjud bo'lsa)
                if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator && 'SyncManager' in window) {
                    navigator.serviceWorker.ready.then((reg) => {
                        reg.sync.register('aqua-sync').catch(() => {});
                    }).catch(() => {});
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        if (event.data?.type === 'BACKGROUND_SYNC_TRIGGERED') {
                            this.triggerAutoSync('background_sync');
                        }
                    });
                }

                // PIN tekshiruvi (meta'dan)
                const savedPin = await this.db.get('meta', 'pos_pin');
                if (savedPin) {
                    this.currentPin = savedPin.pin;
                }

                // Xotira kvotasini tekshirish va doimiylik so'rash
                await this.checkStorage();

                // Lokal ma'lumotlarni yuklash
                await this.loadLocalData(true);

                // Qoralama savatni tiklash
                await this.restoreCartDraft();

                // Navbat sonini yangilash
                await this.updateOutboxCount();

            } catch (err) {
                console.error("POS initsializatsiyasida xatolik:", err);
                this.showAlert('error', "Lokal bazani ochishda xatolik: " + err.message);
            }
        },

        /**
         * 2. Lokal ma'lumotlarni IndexedDB'dan yuklash
         */
        async loadLocalData(initial = false) {
            // A. Qurilma lease ma'lumotlari
            const lease = await this.db.get('device_lease', 'current');
            if (lease) {
                this.deviceLease = lease;
                this.warehouseName = lease.warehouse_name || 'Asosiy Ombor';
                this.permissions = lease.permissions || [];

                // Lease muddati ogohlantirishi
                if (lease.expires_at) {
                    const expiresAt = new Date(lease.expires_at).getTime();
                    const now = Date.now();
                    const diffHours = (expiresAt - now) / (1000 * 60 * 60);
                    if (diffHours < 0) {
                        this.leaseWarning = "Internetsiz ishlash muddati tugagan. Internetga ulanib, qurilma ruxsatini yangilang.";
                    } else if (diffHours < 4) {
                        this.leaseWarning = `Diqqat: Qurilma ruxsat muddati ${Math.ceil(diffHours)} soatda tugaydi.`;
                    } else {
                        this.leaseWarning = null;
                    }
                }
            }

            // B. Tovar ajratmalari (Stock Allocations)
            const allocList = await this.db.getAll('stock_allocations');
            this.stockAllocations = new Map();
            for (const alloc of allocList) {
                this.stockAllocations.set(alloc.product_variant_id, alloc);
            }

            // C. Kredit ajratmalari (Credit Allocations)
            const credList = await this.db.getAll('credit_allocations');
            this.creditAllocations = new Map();
            for (const cred of credList) {
                this.creditAllocations.set(cred.customer_id, cred);
            }

            // D. Katalog
            const catList = await this.db.getAll('catalog');
            this.catalog = catList.filter(item => String(item.status || '').toUpperCase() === 'ACTIVE').sort((a, b) =>
                a.product_name.localeCompare(b.product_name, 'uz', { numeric: true }) || Number(a.volume_litres) - Number(b.volume_litres));

            // E. Mijozlar
            this.customers = (await this.db.getAll('customers')).sort((a, b) => a.name.localeCompare(b.name, 'uz', { numeric: true }));

            // F. Oxirgi savdolar
            const allSales = await this.db.getAll('sales');
            allSales.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            this.recentSales = allSales.slice(0, 10);

            // G. Oxirgi muvaffaqiyatli sync vaqti
            const lastSync = await this.db.get('meta', 'last_successful_sync');
            if (lastSync) {
                this.lastSyncTime = lastSync.value;
            }

            // Agar birinchi marta kirayotgan bo'lsa va katalog bo'sh bo'lsa, avtomatik bootstrap chaqiramiz
            if (initial && this.catalog.length === 0 && this.isOnline) {
                await this.bootstrapFromServer();
            }
        },

        /**
         * 3. Serverdan ma'lumotlarni tortib olish (Online Bootstrap)
         */
        async bootstrapFromServer() {
            if (this.isBootstrapping) return;
            if (!this.isOnline) {
                this.showAlert('warning', "Offline rejimdasiz. Serverdan yuklab bo'lmaydi.");
                return;
            }

            this.isBootstrapping = true;
            try {
                // Qurilma tokeni yoki UUID ni local lease'dan yoki standart cookie/sessiyadan olamiz
                const response = await fetch('/api/sync/bootstrap', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        device_uuid: this.deviceLease?.device_uuid || undefined
                    })
                });

                if (response.status === 422 || response.status === 404) {
                    this.needsDeviceSetup = true;
                    this.showAlert('warning', 'Internetsiz sotuv uchun avval qurilmani xodimga biriktiring. Quyidagi yo‘riqnomaga amal qiling.');
                    return;
                }
                if (response.status === 401 || response.status === 419) {
                    this.showAlert('warning', 'Kirish muddati tugagan. Bosh sahifaga qaytib, tizimga qayta kiring.');
                    return;
                }
                if (response.status === 403) {
                    this.showAlert('warning', 'Bu qurilma yoki internetsiz sotuv uchun ruxsat yetarli emas. Do‘kon egasiga murojaat qiling.');
                    return;
                }
                if (!response.ok) {
                    throw new Error('Ma’lumotlar yuklanmadi. Internetni tekshirib, qayta urinib ko‘ring.');
                }

                const data = await response.json();
                const b = data.data || data.bootstrap;
                if (!data.success || !b) {
                    throw new Error('Ma’lumotlar to‘liq kelmadi. Qayta yuklashga urinib ko‘ring.');
                }
                if (data.success && b) {
                    await this.db.applyBootstrap(b);
                    this.needsDeviceSetup = false;

                    await this.loadLocalData(false);
                    this.showAlert('success', "Katalog va qurilma ajratmalari serverdan muvaffaqiyatli yuklandi!");
                }
            } catch (err) {
                console.error("Bootstrap xatoligi:", err);
                this.showAlert('error', "Serverdan yuklashda xatolik: " + err.message);
            } finally {
                this.isBootstrapping = false;
            }
        },

        /**
         * 4. Filtr va Qidiruv natijalari
         */
        volumeKey(item) {
            const millilitres = Number(item.volume_ml ?? Number(item.volume_litres) * 1000);
            return Number.isFinite(millilitres) && millilitres > 0 ? String(Math.round(millilitres)) : null;
        },

        get availableVolumes() {
            const volumes = new Map();
            for (const item of this.catalog) {
                const key = this.volumeKey(item);
                if (key) volumes.set(key, {key, label: `${Number(key) / 1000} L`});
            }
            return [...volumes.values()].sort((a, b) => Number(a.key) - Number(b.key));
        },

        get filteredCatalog() {
            let list = this.catalog;

            if (this.selectedVolume !== 'all') {
                list = list.filter(item => this.volumeKey(item) === this.selectedVolume);
            }

            // Qidiruv filtri
            if (this.searchQuery.trim()) {
                const terms = this.searchQuery.toLocaleLowerCase('uz').trim().split(/\s+/);
                list = list.filter(item => terms.every(term =>
                    `${item.product_name} ${item.volume_name} ${item.sku || ''} ${item.barcode || ''}`.toLocaleLowerCase('uz').includes(term)));
            }

            return list;
        },

        /**
         * Tovar bo'yicha mavjud sotish kvotasi
         */
        getAvailableStock(variantId) {
            const alloc = this.stockAllocations.get(variantId);
            if (!alloc) return 0;
            return Math.max(0, (alloc.allocated_quantity || 0) - (alloc.consumed_quantity || 0) - (alloc.returned_quantity || 0));
        },

        /**
         * 5. Savatga tovar qo'shish
         */
        async addToCart(variant, qty = 1) {
            const available = this.getAvailableStock(variant.id);
            const existingIndex = this.cart.findIndex(i => i.variant_id === variant.id);

            const currentInCart = existingIndex >= 0 ? this.cart[existingIndex].quantity : 0;
            const targetQty = currentInCart + qty;

            // Ajratma limitini tekshirish
            if (targetQty > available) {
                this.showAlert('warning', `'${variant.product_name}' uchun mavjud ajratma ${available} dona! Undan ortiq qo'sha olmaysiz.`);
                return;
            }

            if (existingIndex >= 0) {
                this.cart[existingIndex].quantity = targetQty;
            } else {
                this.cart.push({
                    variant_id: variant.id,
                    product_name: variant.product_name,
                    volume_name: variant.volume_name,
                    sku: variant.sku,
                    quantity: qty,
                    sale_price: parseInt(variant.default_sale_price) || 0,
                    is_system_price: true
                });
            }

            this.autoAdjustPayment();
            await this.saveCartDraft();
        },

        /**
         * Savatdagi miqdorni o'zgartirish
         */
        async updateQuantity(index, val) {
            const item = this.cart[index];
            if (!item) return;

            const qty = Number(val);
            if (String(val).trim() === '' || !Number.isSafeInteger(qty) || qty < 1) {
                this.showAlert('warning', 'Dona sonini 1 yoki undan katta butun raqam bilan yozing.');
                return;
            }
            const available = this.getAvailableStock(item.variant_id);
            if (qty > available) {
                this.showAlert('warning', `'${item.product_name}' uchun mavjud miqdor ${available} dona. Miqdor o'zgartirilmadi.`);
                return;
            }
            item.quantity = qty;

            this.autoAdjustPayment();
            await this.saveCartDraft();
        },

        /**
         * Narxni o'zgartirish (agar manual_price huquqi bo'lsa)
         */
        async updatePrice(index, val) {
            const item = this.cart[index];
            if (!item) return;

            const price = Number(val);
            if (String(val).trim() === '' || !Number.isSafeInteger(price) || price < 0) {
                this.showAlert('warning', 'Narxni 0 yoki undan katta butun raqam bilan yozing.');
                return;
            }
            if (price >= 0) {
                item.sale_price = price;
                item.is_system_price = false;
            }
            this.autoAdjustPayment();
            await this.saveCartDraft();
        },

        /**
         * Savatdan o'chirish
         */
        async removeFromCart(index) {
            this.cart.splice(index, 1);
            this.autoAdjustPayment();
            await this.saveCartDraft();
        },

        /**
         * Savatni butunlay tozalash
         */
        async clearCart() {
            this.cart = [];
            this.paidAmount = 0;
            this.notes = '';
            this.selectedCustomerId = '';
            this.generateNewOperationId();
            await this.db.delete('cart_draft', 'current_cart');
        },

        /**
         * 6. Hisob-kitoblar (Total, Debt)
         */
        get filteredCustomers() {
            const normalize = value => String(value || '').toLocaleLowerCase('uz').normalize('NFKD').replace(/[‘’ʻʼ`']/g, '').trim();
            const terms = normalize(this.customerQuery).split(/\s+/).filter(Boolean);
            return this.customers.filter(customer => {
                const text = normalize(`${customer.name} ${customer.store_name || ''} ${customer.phone || ''} ${customer.address || ''}`);
                const phone = (customer.phone || '').replace(/\D/g, '');
                return terms.every(term => text.includes(term) || (/^[\d+()-]+$/.test(term) && phone.includes(term.replace(/\D/g, ''))));
            }).slice(0, 20);
        },

        get selectedCustomerLabel() {
            const customer = this.customers.find(item => String(item.id) === String(this.selectedCustomerId));
            return customer ? [customer.name, customer.store_name, customer.phone].filter(Boolean).join(' · ') : '';
        },

        get totalAmount() {
            return this.cart.reduce((sum, it) => sum + (it.quantity * it.sale_price), 0);
        },

        get debtAmount() {
            return Math.max(0, this.totalAmount - (parseInt(this.paidAmount) || 0));
        },

        autoAdjustPayment() {
            if (this.paymentMode === 'DEBT') {
                this.paidAmount = 0;
            } else if (this.paymentMode === 'FULL') {
                this.paidAmount = this.totalAmount;
            } else {
                this.paidAmount = Math.min(this.totalAmount, Math.max(0, Number(this.paidAmount) || 0));
            }
        },

        onPaymentMethodChange(method) {
            this.paymentMode = method === 'CASH' ? 'FULL' : method;
            this.paymentMethod = this.paymentMode === 'DEBT' ? 'DEBT' : 'CASH';
            if (this.paymentMode === 'PARTIAL') this.paidAmount = 0;
            this.autoAdjustPayment();
            this.saveCartDraft();
        },

        switchSaleMode(mode) {
            this.saleMode = mode;
            if (mode === 'quick') {
                this.selectedCustomerId = '';
                this.onPaymentMethodChange('FULL');
            }
            this.saveCartDraft();
        },

        /**
         * 7. Qoralama savatni saqlash va tiklash
         */
        generateNewOperationId() {
            if (window.crypto && window.crypto.randomUUID) {
                this.operationId = window.crypto.randomUUID();
            } else {
                // Fallback UUID v4
                this.operationId = '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c =>
                    (+c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> +c / 4).toString(16)
                );
            }
        },

        async saveCartDraft() {
            if (!this.operationId) {
                this.generateNewOperationId();
            }

            const draft = {
                key: 'current_cart',
                operation_id: this.operationId,
                customer_id: this.selectedCustomerId || null,
                sale_mode: this.saleMode,
                cart: this.cart,
                payment_method: this.paymentMethod,
                payment_mode: this.paymentMode,
                paid_amount: this.paidAmount,
                notes: this.notes,
                updated_at: new Date().toISOString()
            };

            await this.db.put('cart_draft', draft);
        },

        async restoreCartDraft() {
            const draft = await this.db.get('cart_draft', 'current_cart');
            if (draft) {
                this.operationId = draft.operation_id || null;
                this.selectedCustomerId = draft.customer_id || '';
                this.saleMode = draft.sale_mode || (this.selectedCustomerId ? 'customer' : 'quick');
                this.cart = Array.isArray(draft.cart) ? draft.cart : [];
                this.paymentMethod = draft.payment_method || 'CASH';
                this.paidAmount = draft.paid_amount || 0;
                this.paymentMode = draft.payment_mode || (this.paymentMethod === 'DEBT' ? 'DEBT' : (this.paidAmount < this.totalAmount ? 'PARTIAL' : 'FULL'));
                this.autoAdjustPayment();
                this.notes = draft.notes || '';
            }

            if (!this.operationId) {
                this.generateNewOperationId();
            }
        },

        /**
         * 8. ASOSIY SOTUVNI YAKUNLASH (ATOMIC LOCAL SALE)
         */
        async completeSale() {
            // Ko'p bosishdan himoya (Multi-click protection)
            if (this.isProcessing) return;

            const leaseExpiry = new Date(this.deviceLease?.expires_at).getTime();
            if (!Number.isFinite(leaseExpiry) || leaseExpiry <= Date.now()) {
                this.showAlert('warning', 'Avval internetga ulanib, qurilma ruxsatini yangilang. Savatingiz saqlanib turibdi.');
                return;
            }
            this.autoAdjustPayment();

            if (this.saleMode === 'customer' && !this.selectedCustomerId) {
                this.showAlert('warning', 'Mijozni tanlang yoki yangi mijoz qo‘shing. Mijozsiz sotish uchun «Tezkor sotuv»ni tanlang.');
                return;
            }
            if (this.paymentMode === 'PARTIAL' && (!Number.isSafeInteger(Number(this.paidAmount)) || this.paidAmount <= 0 || this.paidAmount >= this.totalAmount)) {
                this.showAlert('warning', 'Qisman to‘lov jami summadan kam va 0 dan katta bo‘lsin. Aks holda to‘liq to‘lov yoki to‘liq nasiyani tanlang.');
                return;
            }
            this.isProcessing = true;

            try {
                if (this.cart.length === 0) {
                    throw new Error("Savat bo'sh! Kamida bitta tovar tanlang.");
                }

                // Mijoz va nasiya tekshiruvi:
                // "Guest tezkor savdo faqat to‘liq to‘lov; mijozsiz DEBT o‘tmaydi"
                if (this.debtAmount > 0 && !this.selectedCustomerId) {
                    throw new Error("Noma'lum xaridorga (mijozsiz) nasiya berish taqiqlangan! To'liq to'lov qiling yoki mijozni tanlang.");
                }

                const selectedCust = this.customers.find(c => String(c.id) === String(this.selectedCustomerId));

                // Receipt data tayyorlash
                const receipt = {
                    operation_id: this.operationId,
                    items: this.cart.map(c => ({ ...c })),
                    total_amount: this.totalAmount,
                    paid_amount: this.paidAmount,
                    debt_amount: this.debtAmount,
                    customer_name: selectedCust ? selectedCust.name : 'Tezkor xaridor (Naqd)',
                    payment_method: this.paymentMethod,
                    notes: this.notes,
                    device_code: this.deviceLease?.device_code || 'DEV-OFFLINE',
                    created_at: new Date().toISOString(),
                    is_offline: true
                };

                // IndexedDB bitta atomik tranzaksiya ichida bajarish:
                const result = await this.db.executeSaleTransaction({
                    operationId: this.operationId,
                    items: this.cart,
                    customerId: selectedCust ? selectedCust.id : null,
                    customerUuid: selectedCust ? selectedCust.uuid : null,
                    customerName: selectedCust ? selectedCust.name : 'Tezkor xaridor',
                    totalAmount: this.totalAmount,
                    paidAmount: this.paidAmount,
                    debtAmount: this.debtAmount,
                    paymentMethod: this.paymentMethod,
                    notes: this.notes,
                    receiptData: receipt
                });

                // Savdo muvaffaqiyatli saqlandi!
                receipt.local_invoice_number = result.local_invoice_number;
                this.completedSale = receipt;
                this.showReceiptModal = true;

                // Savatni tozalaymiz va yangi operation_id beramiz
                this.cart = [];
                this.paidAmount = 0;
                this.notes = '';
                this.selectedCustomerId = '';
                this.paymentMode = 'FULL';
                this.paymentMethod = 'CASH';
                this.customerQuery = '';
                this.generateNewOperationId();

                // Lokal xotirani qayta yuklaymiz (kamaygan ajratmalar bilan)
                await this.loadLocalData(false);
                await this.updateOutboxCount();

                // Boshqa ochiq tablarga xabar beramiz
                if (this.broadcastChannel) {
                    this.broadcastChannel.postMessage({ type: 'SALE_COMPLETED', operation_id: result.operation_id });
                }

                this.showAlert('success', `Savdo #${result.local_invoice_number} muvaffaqiyatli rasmiylashtirildi va navbatga olindi!`);

            } catch (err) {
                console.error("Savdoni yakunlashda xatolik:", err);
                this.showAlert('error', err.message || "Savdoni saqlashda xatolik yuz berdi!");
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * 9. Yangi offline mijoz yaratish (UUID bilan)
         */
        async createOfflineCustomer() {
            if (!this.newCustomer.name || !this.newCustomer.name.trim()) {
                this.showAlert('warning', "Mijoz ismi kiritilishi shart!");
                return;
            }

            try {
                const clientUuid = window.crypto?.randomUUID ? window.crypto.randomUUID() : this.generateUuidFallback();

                const createdCust = await this.db.createOfflineCustomer({
                    clientUuid: clientUuid,
                    name: this.newCustomer.name.trim(),
                    phone: this.newCustomer.phone ? this.newCustomer.phone.trim() : '',
                    storeName: this.newCustomer.store_name ? this.newCustomer.store_name.trim() : ''
                });

                // Yangi mijozni ro'yxatga qo'shamiz va savatga tanlaymiz
                this.customers.push(createdCust);
                this.customers.sort((a, b) => a.name.localeCompare(b.name, 'uz', { numeric: true }));
                this.selectedCustomerId = createdCust.id;

                this.newCustomer = { name: '', phone: '', store_name: '' };
                this.showNewCustomerModal = false;

                await this.updateOutboxCount();
                this.showAlert('success', `Yangi mijoz '${createdCust.name}' lokal yaratildi va navbatga olindi!`);
            } catch (err) {
                console.error("Mijoz yaratishda xatolik:", err);
                this.showAlert('error', "Mijoz yaratishda xatolik: " + err.message);
            }
        },

        generateUuidFallback() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        /**
         * 10. Navbat sonini yangilash
         */
        async updateOutboxCount() {
            const items = await this.db.getAll('sync_outbox');
            this.outboxCount = items.filter(i => i.status === 'PENDING').length;
        },

        /**
         * 11. Xotira holati (Storage Quota)
         */
        async checkStorage() {
            this.storageInfo = await this.db.getStorageQuota();
            if (!this.storageInfo.isPersistent) {
                const granted = await this.db.requestPersistence();
                this.storageInfo.isPersistent = granted;
            }
        },

        /**
         * 12. Favqulodda eksport (Disaster Recovery JSON)
         */
        async exportPendingBackup() {
            try {
                const data = await this.db.exportPendingData();
                const jsonStr = JSON.stringify(data, null, 2);
                const blob = new Blob([jsonStr], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `aqua_pos_backup_${new Date().toISOString().slice(0, 10)}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showAlert('info', "Offline navbat zaxira nusxasi yuklab olindi!");
            } catch (err) {
                this.showAlert('error', "Eksportda xatolik: " + err.message);
            }
        },

        /**
         * 13. PIN himoyasi (Qulflash / Ochish)
         */
        lockSession() {
            this.isLocked = true;
            this.pinInput = '';
        },

        unlockSession() {
            if (this.pinInput === this.currentPin) {
                this.isLocked = false;
                this.pinInput = '';
            } else {
                this.showAlert('error', "PIN kod noto'g'ri!");
                this.pinInput = '';
            }
        },

        appendPin(digit) {
            if (this.pinInput.length < 6) {
                this.pinInput += digit;
            }
        },

        clearPin() {
            this.pinInput = '';
        },

        /**
         * 14. Qo'lda (Manual) Sinxronizatsiya chaqirish
         */
        async syncNow() {
            if (!this.deviceLease || this.needsDeviceSetup) {
                await this.bootstrapFromServer();
                return;
            }
            if (!this.syncEngine) this.syncEngine = new AquaSync(this.db);
            if (this.isSyncing) return;

            this.isProcessing = true;
            this.isSyncing = true;
            try {
                const res = await this.syncEngine.syncNow('manual', true);
                if (res.status === 'SUCCESS') {
                    await this.loadLocalData(false);
                    await this.updateOutboxCount();
                    const pushed = res.pushResult?.pushedCount || 0;
                    const pulled = res.pullResult?.pulledCount || 0;
                    this.showAlert('success', `Sinxronlash yakunlandi: ${pushed} ta yuborildi, ${pulled} ta o'zgarish yangilandi.`);
                } else if (res.status === 'PARTIAL') {
                    await this.loadLocalData(false);
                    await this.updateOutboxCount();
                    this.showAlert('warning', res.message);
                } else if (res.status === 'OFFLINE') {
                    this.showAlert('warning', res.message);
                } else if (res.status === 'LOCKED') {
                    this.showAlert('info', res.message);
                } else {
                    this.showAlert('error', res.message || 'Sinxronlashda xatolik yuz berdi.');
                }
            } catch (err) {
                console.error("Manual sync xatolik:", err);
                this.showAlert('error', "Sinxronlash xatosi: " + err.message);
            } finally {
                this.isProcessing = false;
                this.isSyncing = false;
            }
        },

        /**
         * 15. Avtomatik Fon Sinxronlash (Reconnect, Reopen, Periodic)
         */
        async triggerAutoSync(source = 'auto') {
            if (!this.isOnline || this.isSyncing) return;
            if (!this.syncEngine) this.syncEngine = new AquaSync(this.db);

            try {
                const res = await this.syncEngine.syncNow(source);
                if (res.status === 'SUCCESS') {
                    await this.loadLocalData(false);
                    await this.updateOutboxCount();
                }
            } catch (e) {
                console.warn(`Auto sync (${source}) xatolik:`, e.message);
            }
        },

        /**
         * 16. Outbox Navbati Modali
         */
        async openOutboxModal() {
            await this.refreshOutboxItems();
            this.showOutboxModal = true;
        },

        async refreshOutboxItems() {
            const allItems = await this.db.getAll('sync_outbox');
            allItems.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            this.outboxItems = allItems;
            this.outboxCount = allItems.filter(i => i.status === 'PENDING').length;
            this.needsReviewCount = allItems.filter(i => i.status === 'NEEDS_REVIEW').length;
        },

        filteredOutboxItems() {
            if (this.outboxFilter === 'all') return this.outboxItems;
            return this.outboxItems.filter(i => i.status === this.outboxFilter);
        },

        /**
         * 17. Offline Savdoni Bekor Qilish (Void)
         */
        openVoidModal(sale) {
            this.voidSaleTarget = sale;
            this.voidReason = 'Mijoz tovardan voz kechdi';
            this.showVoidModal = true;
        },

        async confirmVoidSale() {
            if (!this.voidSaleTarget) return;
            try {
                this.isProcessing = true;
                const res = await this.db.voidOfflineSale(this.voidSaleTarget.operation_id, this.voidReason);
                this.showVoidModal = false;
                this.voidSaleTarget = null;
                await this.loadLocalData(false);
                await this.updateOutboxCount();
                if (this.broadcastChannel) {
                    this.broadcastChannel.postMessage({ type: 'SALE_COMPLETED' });
                }
                this.showAlert('info', `Savdo bekor qilindi va tuzatish navbatga olindi.`);
            } catch (err) {
                this.showAlert('error', "Bekor qilishda xatolik: " + err.message);
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Tizim bildirishnomalari (Toast alert)
         */
        showAlert(type, text) {
            this.alertMessage = { type, text };
            setTimeout(() => {
                if (this.alertMessage && this.alertMessage.text === text) {
                    this.alertMessage = null;
                }
            }, 6000);
        },

        closeAlert() {
            this.alertMessage = null;
        },

        printReceipt() {
            window.print();
        }
    };
}
