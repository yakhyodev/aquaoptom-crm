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
            const totalItemsCount = items.length;

            if (totalItemsCount === 0) {
                return abort("Savat bo'sh! Savdo qilish uchun mahsulot tanlang.");
            }

            for (const item of items) {
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
                    paid_amount: parseInt(paidAmount) || 0,
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
}
