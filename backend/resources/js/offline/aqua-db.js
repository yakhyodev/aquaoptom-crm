/**
 * AquaOptom CRM — IndexedDB Local Database Layer
 * Zero-dependency, Transactional, Offline Storage Engine
 */

export class AquaDB {
    constructor(dbName = 'AquaOptomDB', version = 1) {
        this.dbName = dbName;
        this.version = version;
        this.db = null;
    }

    /**
     * Ma'lumotlar bazasini ochish va sxemani yaratish/migratsiya qilish
     */
    async open() {
        if (this.db) {
            return this.db;
        }

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);

            request.onerror = (event) => {
                console.error('IndexedDB ochishda xatolik:', event.target.error);
                reject(event.target.error);
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // 1. device_lease (Qurilma ruxsati, token, ombor)
                if (!db.objectStoreNames.contains('device_lease')) {
                    db.createObjectStore('device_lease', { keyPath: 'key' });
                }

                // 2. stock_allocations (Tovar zaxira ajratmalari va sarfi)
                if (!db.objectStoreNames.contains('stock_allocations')) {
                    db.createObjectStore('stock_allocations', { keyPath: 'product_variant_id' });
                }

                // 3. credit_allocations (Mijoz kredit limitlari)
                if (!db.objectStoreNames.contains('credit_allocations')) {
                    db.createObjectStore('credit_allocations', { keyPath: 'customer_id' });
                }

                // 4. catalog (Tovar variantlari: mahsulot, hajm, narx, shtrix-kod)
                if (!db.objectStoreNames.contains('catalog')) {
                    const catalogStore = db.createObjectStore('catalog', { keyPath: 'id' });
                    catalogStore.createIndex('barcode', 'barcode', { unique: false });
                    catalogStore.createIndex('sku', 'sku', { unique: false });
                }

                // 5. customers (Mijozlar: server va offline yaratilgan mijozlar)
                if (!db.objectStoreNames.contains('customers')) {
                    const customerStore = db.createObjectStore('customers', { keyPath: 'id' });
                    customerStore.createIndex('uuid', 'uuid', { unique: true });
                    customerStore.createIndex('phone', 'phone', { unique: false });
                }

                // 6. cart_draft (Joriy qoralama savat, operation_id)
                if (!db.objectStoreNames.contains('cart_draft')) {
                    db.createObjectStore('cart_draft', { keyPath: 'key' });
                }

                // 7. sales (Lokal rasmiylashtirilgan savdolar va cheklar)
                if (!db.objectStoreNames.contains('sales')) {
                    const salesStore = db.createObjectStore('sales', { keyPath: 'local_id', autoIncrement: true });
                    salesStore.createIndex('operation_id', 'operation_id', { unique: true });
                    salesStore.createIndex('sync_status', 'sync_status', { unique: false });
                }

                // 8. sync_outbox (Serverga yuboriladigan navbat)
                if (!db.objectStoreNames.contains('sync_outbox')) {
                    const outboxStore = db.createObjectStore('sync_outbox', { keyPath: 'operation_id' });
                    outboxStore.createIndex('status', 'status', { unique: false });
                    outboxStore.createIndex('created_at', 'created_at', { unique: false });
                }

                // 9. meta (Kursor, PIN, sozlamalar)
                if (!db.objectStoreNames.contains('meta')) {
                    db.createObjectStore('meta', { keyPath: 'key' });
                }
            };
        });
    }

    /**
     * Bitta ob'ektni olish
     */
    async get(storeName, key) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const req = store.get(key);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Barcha ob'ektlarni olish
     */
    async getAll(storeName) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Ob'ektni saqlash yoki yangilash
     */
    async put(storeName, value) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const req = store.put(value);
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Bitta ob'ektni o'chirish
     */
    async delete(storeName, key) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const req = store.delete(key);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Store'ni tozalash
     */
    async clear(storeName) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const req = store.clear();
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Bitta jadvaldagi elementlar sonini olish
     */
    async count(storeName) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const req = store.count();
            req.onsuccess = () => resolve(req.result || 0);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * ASOSIY ATOMIK SOTUV TRANZAKSIYASI (IndexedDB Transaction):
     * 1. Qoldiq ajratmasini (quota) tekshirish va kamaytirish
     * 2. Mijoz kredit limitini (agar nasiya bo'lsa) tekshirish va kamaytirish
     * 3. Lokal savdoni `sales` jadvaliga yozish
     * 4. Serverga yuborish navbatiga `sync_outbox` yozuvini kiritish
     * 5. `cart_draft` qoralama savatini tozalash
     * Barchasi BITTA tranzaksiyada! Storage failure bo'lsa, hech narsa saqlanmaydi!
     */
    async applyBootstrap(snapshot) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['device_lease', 'stock_allocations', 'credit_allocations', 'catalog', 'customers', 'meta', 'sync_outbox'], 'readwrite');
            tx.oncomplete = () => resolve();
            tx.onabort = () => reject(new Error('Avval saqlangan offline amallarni sinxronlang yoki tekshiruvini yakunlang.'));
            tx.onerror = () => reject(tx.error || new Error('Bootstrap saqlanmadi.'));
            const pending = tx.objectStore('sync_outbox').getAll();
            pending.onsuccess = () => {
                if (pending.result.some(row => !['ACKNOWLEDGED', 'APPLIED', 'SUCCESS', 'RETRY_SUCCESS'].includes(row.status))) {
                    tx.abort();
                    return;
                }
                const warehouse = snapshot.warehouse || {};
                tx.objectStore('device_lease').put({key: 'current', device_id: snapshot.device.id,
                    device_code: snapshot.device.device_code, device_uuid: snapshot.device.device_uuid,
                    warehouse_id: warehouse.id, warehouse_name: warehouse.name,
                    lease_token: snapshot.lease.lease_token, signature: snapshot.lease.signature,
                    valid_from: snapshot.lease.valid_from, expires_at: snapshot.lease.expires_at,
                    permissions: snapshot.lease.permissions || []});
                const stocks = tx.objectStore('stock_allocations');
                const credits = tx.objectStore('credit_allocations');
                const catalog = tx.objectStore('catalog');
                stocks.clear(); credits.clear(); catalog.clear();
                for (const row of snapshot.stock_allocations || []) {
                    stocks.put({...row, product_variant_id: row.variant_id});
                }
                for (const row of snapshot.credit_allocations || []) {
                    credits.put({...row, allocated_credit: row.allocated_amount,
                        consumed_credit: row.consumed_amount, available_credit: row.available_amount});
                }
                for (const row of snapshot.catalog || []) catalog.put(row);
                for (const row of snapshot.customers || []) tx.objectStore('customers').put({...row, is_local: false});
                tx.objectStore('meta').put({key: 'last_cursor', value: snapshot.current_cursor || 0});
            };
        });
    }

    async executeSaleTransaction({
        operationId,
        items,
        customerId,
        customerUuid,
        customerName,
        totalAmount,
        paidAmount,
        debtAmount,
        paymentMethod,
        notes = '',
        receiptData = {}
    }) {
        if ((await this.get('meta', 'recovery_hold'))?.value) {
            throw new Error('Recovery muvofiqlashtirish tugamaguncha yangi savdo to‘xtatilgan.');
        }
        const totals = items.map(item => Number(item.quantity) * Number(item.sale_price));
        if (items.some(item => !Number.isSafeInteger(Number(item.quantity)) || Number(item.quantity) <= 0
            || !Number.isSafeInteger(Number(item.sale_price)) || Number(item.sale_price) <= 0)
            || totals.some(value => !Number.isSafeInteger(value))
            || !Number.isSafeInteger(totalAmount) || totals.reduce((sum, value) => sum + value, 0) !== totalAmount
            || !Number.isSafeInteger(paidAmount) || paidAmount < 0 || paidAmount > totalAmount
            || debtAmount !== totalAmount - paidAmount || (debtAmount > 0 && !customerId && !customerUuid)) {
            throw new Error('Dona va so‘m butun musbat son bo‘lishi, savdo va to‘lov summalari mos kelishi shart.');
        }
        const stockItems = Object.values(items.reduce((map, item) => {
            const id = item.variant_id;
            map[id] = {...item, quantity: (map[id]?.quantity || 0) + Number(item.quantity)};
            return map;
        }, {}));
        const db = await this.open();

        return new Promise((resolve, reject) => {
            const storeNames = [
                'stock_allocations',
                'credit_allocations',
                'customers',
                'sales',
                'sync_outbox',
                'cart_draft'
            ];

            const tx = db.transaction(storeNames, 'readwrite');

            let isAborted = false;
            const abort = (errMsg) => {
                if (!isAborted) {
                    isAborted = true;
                    try {
                        tx.abort();
                    } catch (e) {
                        // ignore abort error if already closing
                    }
                    reject(new Error(errMsg));
                }
            };

            tx.onerror = (event) => {
                if (!isAborted) {
                    reject(new Error("Lokal tranzaksiya saqlashda xatolik yuz berdi: " + (event.target.error ? event.target.error.message : 'Storage error')));
                }
            };

            const stockStore = tx.objectStore('stock_allocations');
            const creditStore = tx.objectStore('credit_allocations');
            const customerStore = tx.objectStore('customers');
            const salesStore = tx.objectStore('sales');
            const outboxStore = tx.objectStore('sync_outbox');
            const draftStore = tx.objectStore('cart_draft');

            // 1. Tovar ajratmalarini (quota) birma-bir tekshiramiz va yangilaymiz
            let itemsChecked = 0;
            const totalItemsCount = stockItems.length;

            if (totalItemsCount === 0) {
                return abort("Savat bo'sh! Savdo qilish uchun mahsulot tanlang.");
            }

            for (const item of stockItems) {
                const variantId = parseInt(item.variant_id);
                const reqQty = parseInt(item.quantity);

                if (isNaN(reqQty) || reqQty <= 0) {
                    return abort(`Mahsulot '${item.product_name}' miqdori musbat butun son bo'lishi shart!`);
                }

                const stockReq = stockStore.get(variantId);
                stockReq.onsuccess = () => {
                    if (isAborted) return;
                    const alloc = stockReq.result;

                    // Agar qurilmada tovar ajratmasi bo'lmasa yoki qoldiq yetarli bo'lmasa:
                    if (!alloc) {
                        return abort(`Tovar '${item.product_name}' uchun qurilmada sotish huquqi (rezerv) mavjud emas!`);
                    }

                    const available = (alloc.allocated_quantity || 0) - (alloc.consumed_quantity || 0) - (alloc.returned_quantity || 0);
                    if (reqQty > available) {
                        return abort(`'${item.product_name}' uchun ajratilgan limit yetarli emas! Mavjud: ${available} dona, so'ralgan: ${reqQty} dona.`);
                    }

                    // Ajratmani sarflangan deb belgilaymiz
                    alloc.consumed_quantity = (alloc.consumed_quantity || 0) + reqQty;
                    stockStore.put(alloc);

                    itemsChecked++;
                    if (itemsChecked === totalItemsCount) {
                        // Barcha tovarlar tekshirildi, endi kredit limitini tekshiramiz
                        proceedWithCreditCheck();
                    }
                };
                stockReq.onerror = () => abort("Ombor ajratmasini o'qishda xatolik.");
            }

            // 2. Kredit limiti tekshiruvi (agar nasiya bo'lsa)
            function proceedWithCreditCheck() {
                if (isAborted) return;

                if (debtAmount > 0 && customerId) {
                    const custReq = customerStore.get(customerId);
                    custReq.onsuccess = () => {
                        if (isAborted) return;
                        const customer = custReq.result;

                        if (customer && customer.is_strict_credit_limit) {
                            const credReq = creditStore.get(customerId);
                            credReq.onsuccess = () => {
                                if (isAborted) return;
                                const credAlloc = credReq.result;
                                if (!credAlloc) {
                                    return abort(`Mijoz '${customer.name}' uchun qat'iy kredit limiti yoqilgan, lekin qurilmada kredit ajratmasi mavjud emas!`);
                                }

                                const availCredit = (credAlloc.allocated_credit || 0) - (credAlloc.consumed_credit || 0);
                                if (debtAmount > availCredit) {
                                    return abort(`Mijoz kredit limiti yetarli emas! Mavjud limit: ${availCredit.toLocaleString('uz-UZ')} so'm, so'ralgan nasiya: ${debtAmount.toLocaleString('uz-UZ')} so'm.`);
                                }

                                credAlloc.consumed_credit = (credAlloc.consumed_credit || 0) + debtAmount;
                                creditStore.put(credAlloc);

                                customer.current_debt = (customer.current_debt || 0) + debtAmount;
                                customerStore.put(customer);

                                finalizeSaleRecords();
                            };
                            credReq.onerror = () => abort("Kredit ajratmasini tekshirishda xatolik.");
                        } else {
                            if (customer) {
                                customer.current_debt = (customer.current_debt || 0) + debtAmount;
                                customerStore.put(customer);
                            }
                            finalizeSaleRecords();
                        }
                    };
                    custReq.onerror = () => abort("Mijoz ma'lumotlarini o'qishda xatolik.");
                } else {
                    finalizeSaleRecords();
                }
            }

            // 3. Sales va Outbox yozuvlarini saqlash
            function finalizeSaleRecords() {
                if (isAborted) return;

                const nowIso = new Date().toISOString();

                // Mahsulot qatorlarini tayyorlash (offline narx saqlanadi, tannarx taxminiy belgilanadi)
                const salePayloadItems = items.map((it) => ({
                    variant_id: parseInt(it.variant_id),
                    quantity: parseInt(it.quantity),
                    sale_price: parseInt(it.sale_price)
                }));

                const outboxPayload = {
                    items: salePayloadItems,
                    total_amount: parseInt(totalAmount) || 0,
                    paid_amount: parseInt(paidAmount) || 0,
                    debt_amount: parseInt(debtAmount) || 0,
                    payment_method: paymentMethod || 'CASH',
                    notes: notes || ''
                };

                if (customerUuid) {
                    outboxPayload.customer_client_uuid = customerUuid;
                } else if (customerId && !String(customerId).startsWith('local_')) {
                    outboxPayload.customer_id = parseInt(customerId);
                }

                // A. Local sale record
                const localSale = {
                    operation_id: operationId,
                    local_invoice_number: `OFFLINE-${Date.now().toString().slice(-6)}`,
                    customer_id: customerId || null,
                    customer_uuid: customerUuid || null,
                    customer_name: customerName || 'Tezkor xaridor',
                    items: items,
                    total_amount: parseInt(totalAmount) || 0,
                    paid_amount: parseInt(paidAmount) || 0,
                    debt_amount: parseInt(debtAmount) || 0,
                    payment_method: paymentMethod || 'CASH',
                    notes: notes,
                    sync_status: 'PENDING',
                    created_at: nowIso,
                    receipt_data: receiptData
                };

                const addSaleReq = salesStore.add(localSale);

                // B. Outbox push record
                const outboxItem = {
                    operation_id: operationId,
                    type: 'CREATE_SALE',
                    device_created_at: nowIso,
                    payload: outboxPayload,
                    status: 'PENDING',
                    retry_count: 0,
                    error_code: null,
                    error_message: null,
                    created_at: nowIso
                };

                outboxStore.put(outboxItem);

                // C. Cart draft tozalash
                draftStore.delete('current_cart');

                tx.oncomplete = () => {
                    resolve({
                        local_id: addSaleReq.result,
                        operation_id: operationId,
                        local_invoice_number: localSale.local_invoice_number,
                        total_amount: localSale.total_amount,
                        paid_amount: localSale.paid_amount,
                        debt_amount: localSale.debt_amount,
                        created_at: localSale.created_at
                    });
                };
            }
        });
    }

    /**
     * Yangi offline mijoz yaratish (Bitta atomik tranzaksiyada: customers + sync_outbox)
     */
    async createOfflineCustomer({ clientUuid, name, phone, storeName }) {
        const db = await this.open();

        return new Promise((resolve, reject) => {
            const tx = db.transaction(['customers', 'sync_outbox'], 'readwrite');

            tx.onerror = (e) => reject(new Error("Mijozni lokal saqlashda xatolik: " + (e.target.error?.message || 'Storage error')));

            const customerStore = tx.objectStore('customers');
            const outboxStore = tx.objectStore('sync_outbox');
            const nowIso = new Date().toISOString();

            const localCustId = `local_${clientUuid}`;

            const customerRecord = {
                id: localCustId,
                uuid: clientUuid,
                name: name.trim(),
                phone: phone ? phone.trim() : null,
                store_name: storeName ? storeName.trim() : null,
                current_debt: 0,
                debt_limit: 0,
                is_strict_credit_limit: false,
                is_local: true,
                created_at: nowIso
            };

            customerStore.put(customerRecord);

            const outboxRecord = {
                operation_id: clientUuid, // Mijoz uchun UUID aynan operation_id vazifasini bajaradi
                type: 'CREATE_CUSTOMER',
                device_created_at: nowIso,
                payload: {
                    client_uuid: clientUuid,
                    name: customerRecord.name,
                    phone: customerRecord.phone,
                    store_name: customerRecord.store_name
                },
                status: 'PENDING',
                retry_count: 0,
                error_code: null,
                error_message: null,
                created_at: nowIso
            };

            outboxStore.put(outboxRecord);

            tx.oncomplete = () => resolve(customerRecord);
        });
    }

    /**
     * Storage Quota holatini tekshirish
     */
    async getStorageQuota() {
        if (navigator.storage && navigator.storage.estimate) {
            const estimate = await navigator.storage.estimate();
            const usedMB = ((estimate.usage || 0) / (1024 * 1024)).toFixed(2);
            const totalMB = ((estimate.quota || 0) / (1024 * 1024)).toFixed(0);
            const percent = estimate.quota ? (((estimate.usage || 0) / estimate.quota) * 100).toFixed(1) : '0';
            const isPersisted = navigator.storage.persisted ? await navigator.storage.persisted() : false;

            return {
                usedMB: parseFloat(usedMB),
                totalMB: parseFloat(totalMB),
                percent: parseFloat(percent),
                isPersistent: isPersisted
            };
        }
        return { usedMB: 0, totalMB: 0, percent: 0, isPersistent: false };
    }

    /**
     * Persistent Storage so'rash
     */
    async requestPersistence() {
        if (navigator.storage && navigator.storage.persist) {
            return await navigator.storage.persist();
        }
        return false;
    }

    /**
     * Favqulodda eksport: Sinxronlanmagan barcha operatsiyalarni JSON fayl sifatida yuklab olish
     */
    async exportPendingData() {
        const outbox = await this.getAll('sync_outbox');
        const sales = await this.getAll('sales');
        const customers = await this.getAll('customers');
        const lease = await this.get('device_lease', 'current');

        const exportObj = {
            exported_at: new Date().toISOString(),
            device_code: lease?.device_code || 'UNKNOWN',
            pending_outbox_count: outbox.filter(o => o.status === 'PENDING').length,
            outbox: outbox,
            sales: sales,
            local_customers: customers.filter(c => c.is_local)
        };

        return exportObj;
    }

    /**
     * Offline bekor qilingan haqiqiy savdoni rasmiylashtirish (originalga bog'langan tuzatish).
     * Navbatdan aslo DELETE qilinmaydi! Original saqlanadi, yangi VOID_SALE yozuvi navbatga qo'shiladi.
     */
    async voidOfflineSale(operationId, reason = 'Kassir tomonidan offline bekor qilindi') {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['sales', 'stock_allocations', 'credit_allocations', 'customers', 'sync_outbox'], 'readwrite');
            let isAborted = false;
            const abort = (msg) => {
                if (!isAborted) {
                    isAborted = true;
                    try { tx.abort(); } catch (e) {}
                    reject(new Error(msg));
                }
            };
            tx.onerror = (e) => {
                if (!isAborted) reject(new Error("Bekor qilishda saqlash xatosi: " + (e.target.error?.message || 'Storage error')));
            };

            const salesStore = tx.objectStore('sales');
            const stockStore = tx.objectStore('stock_allocations');
            const creditStore = tx.objectStore('credit_allocations');
            const customerStore = tx.objectStore('customers');
            const outboxStore = tx.objectStore('sync_outbox');

            const salesIndex = salesStore.index('operation_id');
            const saleReq = salesIndex.get(operationId);

            saleReq.onsuccess = () => {
                if (isAborted) return;
                const sale = saleReq.result;
                if (!sale) {
                    return abort(`Savdo (#${operationId}) lokal bazada topilmadi!`);
                }
                if (sale.sync_status === 'CANCELLED' || sale.status === 'CANCELLED') {
                    return abort(`Savdo allaqachon bekor qilingan!`);
                }

                // 1. Tovar ajratmalarini (quota) qaytarish
                for (const item of (sale.items || [])) {
                    const vid = parseInt(item.variant_id);
                    const qty = parseInt(item.quantity) || 0;
                    const stReq = stockStore.get(vid);
                    stReq.onsuccess = () => {
                        if (stReq.result) {
                            const alloc = stReq.result;
                            alloc.consumed_quantity = Math.max(0, (alloc.consumed_quantity || 0) - qty);
                            stockStore.put(alloc);
                        }
                    };
                }

                // 2. Kredit ajratmasini qaytarish (agar nasiya bo'lsa)
                if (sale.debt_amount > 0 && sale.customer_id) {
                    const crReq = creditStore.get(sale.customer_id);
                    crReq.onsuccess = () => {
                        if (crReq.result) {
                            const cred = crReq.result;
                            cred.consumed_credit = Math.max(0, (cred.consumed_credit || 0) - sale.debt_amount);
                            creditStore.put(cred);
                        }
                    };
                    const cuReq = customerStore.get(sale.customer_id);
                    cuReq.onsuccess = () => {
                        if (cuReq.result) {
                            const cust = cuReq.result;
                            cust.current_debt = Math.max(0, (cust.current_debt || 0) - sale.debt_amount);
                            customerStore.put(cust);
                        }
                    };
                }

                // 3. Savdo holatini yangilash
                sale.status = 'CANCELLED';
                sale.sync_status = 'CANCELLED_LOCALLY';
                sale.cancelled_at = new Date().toISOString();
                sale.cancellation_reason = reason;
                salesStore.put(sale);

                // 4. Outbox ga VOID_SALE yozish (Original CREATE_SALE o'chirilmaydi!)
                const voidOpId = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : this.generateUuidFallback();
                const nowIso = new Date().toISOString();
                const voidOutboxItem = {
                    operation_id: voidOpId,
                    type: 'VOID_SALE',
                    device_created_at: nowIso,
                    payload: {
                        original_operation_id: operationId,
                        reason: reason
                    },
                    status: 'PENDING',
                    retry_count: 0,
                    error_code: null,
                    error_message: null,
                    created_at: nowIso
                };
                outboxStore.put(voidOutboxItem);

                tx.oncomplete = () => resolve({
                    success: true,
                    void_operation_id: voidOpId,
                    original_operation_id: operationId,
                    sale: sale
                });
            };
            saleReq.onerror = () => abort("Savdoni qidirishda xatolik.");
        });
    }

    /**
     * ACK ni lokal atomik yozish (Server push javobini qayd etish).
     * Barcha natijalar bitta tranzaksiyada saqlanadi. Outbox yozuvlari o'chirilmaydi!
     */
    async applyPushResults(results) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['sync_outbox', 'sales', 'customers', 'meta'], 'readwrite');
            tx.onerror = (e) => reject(new Error("ACK saqlashda xatolik: " + (e.target.error?.message || 'Storage error')));

            const outboxStore = tx.objectStore('sync_outbox');
            const salesStore = tx.objectStore('sales');
            const customerStore = tx.objectStore('customers');
            const salesIndex = salesStore.index('operation_id');

            const stats = { applied: 0, retrySuccess: 0, needsReview: 0, conflict: 0, failed: 0 };
            const nowIso = new Date().toISOString();

            for (const res of results) {
                const opId = res.operation_id;
                const status = res.status;

                const obReq = outboxStore.get(opId);
                obReq.onsuccess = () => {
                    const item = obReq.result;
                    if (!item) return;

                    item.last_sync_attempt = nowIso;
                    item.raw_response = res;

                    if (status === 'APPLIED' || status === 'RETRY_SUCCESS') {
                        item.status = 'APPLIED'; // Xavfsiz retention: navbatdan o'chirilmaydi!
                        item.server_document_id = res.server_document_id || res.data?.server_document_id || null;
                        item.server_document_number = res.server_document_number || res.data?.server_document_number || null;
                        item.applied_at = nowIso;
                        item.error_code = null;
                        item.error_message = null;

                        if (status === 'APPLIED') stats.applied++;
                        else stats.retrySuccess++;

                        // Tegishli savdo entitetini yangilash
                        if (item.type === 'CREATE_SALE' || item.type === 'VOID_SALE') {
                            const sReq = salesIndex.get(opId);
                            sReq.onsuccess = () => {
                                const sale = sReq.result;
                                if (sale) {
                                    sale.sync_status = 'SERVER_SYNCED';
                                    sale.server_document_id = item.server_document_id;
                                    sale.server_document_number = item.server_document_number;
                                    sale.server_posted_at = res.data?.posted_at || nowIso;
                                    salesStore.put(sale);
                                }
                            };
                        } else if (item.type === 'CREATE_CUSTOMER') {
                            // Lokal mijozni server ID bilan yangilash
                            const cReq = customerStore.get(`local_${opId}`);
                            cReq.onsuccess = () => {
                                const cust = cReq.result;
                                if (cust) {
                                    cust.is_local = false;
                                    cust.server_id = res.server_document_id;
                                    customerStore.put(cust);
                                }
                            };
                        }
                    } else if (status === 'NEEDS_REVIEW') {
                        item.status = 'NEEDS_REVIEW';
                        item.conflict_id = res.conflict_id;
                        item.error_code = res.error_code;
                        item.error_message = res.message;
                        stats.needsReview++;

                        const sReq = salesIndex.get(opId);
                        sReq.onsuccess = () => {
                            const sale = sReq.result;
                            if (sale) {
                                sale.sync_status = 'NEEDS_REVIEW';
                                sale.conflict_id = res.conflict_id;
                                sale.error_message = res.message;
                                salesStore.put(sale);
                            }
                        };
                    } else if (status === 'CONFLICT') {
                        item.status = 'CONFLICT';
                        item.error_code = res.error_code;
                        item.error_message = res.message;
                        stats.conflict++;

                        const sReq = salesIndex.get(opId);
                        sReq.onsuccess = () => {
                            const sale = sReq.result;
                            if (sale) {
                                sale.sync_status = 'CONFLICT';
                                sale.error_message = res.message;
                                salesStore.put(sale);
                            }
                        };
                    } else {
                        item.status = 'FAILED';
                        item.error_code = res.error_code || 'FAILED';
                        item.error_message = res.message;
                        item.retry_count = (item.retry_count || 0) + 1;
                        stats.failed++;

                        const sReq = salesIndex.get(opId);
                        sReq.onsuccess = () => {
                            const sale = sReq.result;
                            if (sale) {
                                sale.sync_status = 'FAILED';
                                sale.error_message = res.message;
                                salesStore.put(sale);
                            }
                        };
                    }

                    outboxStore.put(item);
                };
            }

            tx.oncomplete = () => resolve(stats);
        });
    }

    /**
     * Kursor bo'yicha serverdagi o'zgarishlarni lokal bazaga qo'llash (Pull delta feed)
     */
    async applyPulledChanges(events, nextCursor) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['catalog', 'customers', 'meta'], 'readwrite');
            tx.onerror = (e) => reject(new Error("Pull o'zgarishlarini saqlashda xatolik: " + (e.target.error?.message || 'Storage error')));

            const catalogStore = tx.objectStore('catalog');
            const customerStore = tx.objectStore('customers');
            const metaStore = tx.objectStore('meta');

            for (const ev of (events || [])) {
                const type = (ev.entity_type || ev.aggregate_type || '').toUpperCase();
                const action = (ev.action || '').toUpperCase();
                const data = {...(ev.payload || {})};
                data.id ??= Number(ev.entity_id) || ev.entity_id;

                if (type === 'PRODUCT_VARIANT' || type === 'VARIANT') {
                    if (action === 'DELETED' || ev.is_tombstone) {
                        if (data.id) catalogStore.delete(data.id);
                    } else if (data.id) {
                        const existing = catalogStore.get(data.id);
                        existing.onsuccess = () => catalogStore.put({...existing.result, ...data});
                    }
                } else if (type === 'CUSTOMER') {
                    if (action === 'DELETED' || ev.is_tombstone) {
                        if (data.id) customerStore.delete(data.id);
                    } else if (data.id) {
                        const existing = customerStore.get(data.id);
                        existing.onsuccess = () => customerStore.put({...existing.result, ...data});
                    }
                }
            }

            if (nextCursor !== undefined && nextCursor !== null) {
                metaStore.put({ key: 'last_cursor', value: nextCursor });
            }
            metaStore.put({ key: 'last_synced_at', value: new Date().toISOString() });

            tx.oncomplete = () => resolve({ appliedEventsCount: events?.length || 0, nextCursor });
        });
    }

    /**
     * Ko'p tabli/worker konkurentsiyasi uchun lokal lock/lease olish.
     * Agar lock band bo'lsa false qaytaradi; agar eski (stale > 30s) bo'lsa auto-recovery qiladi.
     */
    async acquireSyncLock(tabId = 'tab_1', timeoutMs = 30000) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction('meta', 'readwrite');
            const metaStore = tx.objectStore('meta');
            const req = metaStore.get('sync_lock');

            req.onsuccess = () => {
                const currentLock = req.result;
                const now = Date.now();

                if (currentLock) {
                    const age = now - (currentLock.acquired_at || 0);
                    if (age < timeoutMs && currentLock.holder_id !== tabId) {
                        return resolve(false); // Band, boshqa tab ishlayapti
                    }
                    // Aks holda avtomatik tiklanish (Stale lock recovery)
                }

                metaStore.put({
                    key: 'sync_lock',
                    holder_id: tabId,
                    acquired_at: now
                });
                tx.oncomplete = () => resolve(true);
            };
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Lokal lock/lease ni bo'shatish
     */
    async releaseSyncLock(tabId = 'tab_1') {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction('meta', 'readwrite');
            const metaStore = tx.objectStore('meta');
            const req = metaStore.get('sync_lock');

            req.onsuccess = () => {
                const currentLock = req.result;
                if (currentLock && currentLock.holder_id === tabId) {
                    metaStore.delete('sync_lock');
                }
                tx.oncomplete = () => resolve(true);
            };
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Qolgan pending operatsiyalar overlayi:
     * Server qoldiqlari ustiga hali ACK olinmagan lokal savdolarni qo'llab to'g'ri ko'rsatish
     */
    async getPendingOverlay() {
        const outbox = await this.getAll('sync_outbox');
        const pendingSales = outbox.filter(item => item.status === 'PENDING' && item.type === 'CREATE_SALE');

        const pendingStockConsumed = new Map();
        const pendingCustomerDebt = new Map();

        for (const item of pendingSales) {
            const payload = item.payload || {};
            for (const line of (payload.items || [])) {
                const vid = parseInt(line.variant_id);
                const qty = parseInt(line.quantity) || 0;
                pendingStockConsumed.set(vid, (pendingStockConsumed.get(vid) || 0) + qty);
            }
            if (payload.customer_id) {
                const cid = parseInt(payload.customer_id);
                const total = parseInt(payload.total_amount) || 0;
                const paid = parseInt(payload.paid_amount) || 0;
                const debt = parseInt(payload.debt_amount) || Math.max(0, total - paid);
                if (debt > 0) {
                    pendingCustomerDebt.set(cid, (pendingCustomerDebt.get(cid) || 0) + debt);
                }
            }
        }

        return {
            pendingStockConsumed,
            pendingCustomerDebt,
            pendingCount: pendingSales.length
        };
    }

    generateUuidFallback() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
}
