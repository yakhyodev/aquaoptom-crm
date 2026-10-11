/**
 * AquaOptom CRM — PWA Auto Sync, Disruption & Offline Protocol Tests
 * Runs in Node.js using fake-indexeddb
 */

const { indexedDB, IDBKeyRange } = require('fake-indexeddb');
const assert = require('assert');

// Global indexedDB fake inject
global.indexedDB = indexedDB;
global.IDBKeyRange = IDBKeyRange;

const DB_NAME = 'AquaOptomDB_SyncTest';
const DB_VERSION = 1;

function openTestDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onerror = () => reject(req.error);
        req.onsuccess = () => resolve(req.result);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('device_lease')) {
                db.createObjectStore('device_lease', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('stock_allocations')) {
                db.createObjectStore('stock_allocations', { keyPath: 'product_variant_id' });
            }
            if (!db.objectStoreNames.contains('credit_allocations')) {
                db.createObjectStore('credit_allocations', { keyPath: 'customer_id' });
            }
            if (!db.objectStoreNames.contains('catalog')) {
                db.createObjectStore('catalog', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('customers')) {
                const cs = db.createObjectStore('customers', { keyPath: 'id' });
                cs.createIndex('uuid', 'uuid', { unique: true });
            }
            if (!db.objectStoreNames.contains('cart_draft')) {
                db.createObjectStore('cart_draft', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('sales')) {
                const ss = db.createObjectStore('sales', { keyPath: 'local_id', autoIncrement: true });
                ss.createIndex('operation_id', 'operation_id', { unique: true });
                ss.createIndex('sync_status', 'sync_status', { unique: false });
            }
            if (!db.objectStoreNames.contains('sync_outbox')) {
                const os = db.createObjectStore('sync_outbox', { keyPath: 'operation_id' });
                os.createIndex('status', 'status', { unique: false });
                os.createIndex('created_at', 'created_at', { unique: false });
            }
            if (!db.objectStoreNames.contains('meta')) {
                db.createObjectStore('meta', { keyPath: 'key' });
            }
        };
    });
}

// Helpers
async function putItem(storeName, item) {
    const db = await openTestDb();
    return new Promise((res, rej) => {
        const tx = db.transaction(storeName, 'readwrite');
        tx.objectStore(storeName).put(item);
        tx.oncomplete = () => res();
        tx.onerror = () => rej(tx.error);
    });
}

async function getItem(storeName, key) {
    const db = await openTestDb();
    return new Promise((res, rej) => {
        const tx = db.transaction(storeName, 'readonly');
        const req = tx.objectStore(storeName).get(key);
        req.onsuccess = () => res(req.result || null);
        req.onerror = () => rej(req.error);
    });
}

async function getAllItems(storeName) {
    const db = await openTestDb();
    return new Promise((res, rej) => {
        const tx = db.transaction(storeName, 'readonly');
        const req = tx.objectStore(storeName).getAll();
        req.onsuccess = () => res(req.result || []);
        req.onerror = () => rej(req.error);
    });
}

// Import AquaDB logic
const { AquaDB } = require('../resources/js/offline/aqua-db.js');

async function runTests() {
    console.log("=== AquaOptom CRM: Prompt 14 Sync & Disruption Tests ===\n");
    let passed = 0;
    const aquaDb = new AquaDB(DB_NAME, DB_VERSION);
    await aquaDb.open();
    const fs = require('node:fs');
    const vm = require('node:vm');
    const path = require('node:path');
    const source = fs.readFileSync(path.join(__dirname, '../resources/js/offline/aqua-sync.js'), 'utf8')
        .replace(/^import .*;\r?\n/gm, '').replace('export class AquaSync', 'class AquaSync');
    const sandbox = vm.createContext({console, Date, Math});
    vm.runInContext(source + '\nthis.AquaSync = AquaSync;', sandbox);
    const writes = [];
    let released = false;
    const engine = new sandbox.AquaSync({
        acquireSyncLock: async () => true, releaseSyncLock: async () => {released = true;},
        get: async () => null, put: async (store, row) => writes.push(row),
    });
    engine.checkHealth = async () => ({isOnline: true});
    engine.pushPendingQueue = async () => ({pushedCount: 1});
    engine.pullServerChanges = async () => ({skipped: true, status: 503});
    const failedPull = await engine.syncNow();
    assert.strictEqual(failedPull.status, 'PARTIAL');
    assert.strictEqual(writes.length, 0, 'A failed pull must not advance successful sync time');
    assert.strictEqual(released, true);
    engine.pullServerChanges = async () => ({pulledCount: 2});
    assert.strictEqual((await engine.syncNow()).status, 'SUCCESS');
    assert.strictEqual(writes[0].key, 'last_successful_sync');


    // -------------------------------------------------------------
    // Test 1: Multi-tab Mutex Lock & Stale Lock Recovery
    // -------------------------------------------------------------
    try {
        console.log("Test 1: Multi-tab mutex lock acquisition and stale lock recovery...");

        // Tab A lock oladi
        const acquiredA = await aquaDb.acquireSyncLock('tab_A', 30000);
        assert.strictEqual(acquiredA, true, "Tab A lock olishi kerak");

        // Tab B ayni vaqtda lock ololmasligi kerak
        const acquiredB = await aquaDb.acquireSyncLock('tab_B', 30000);
        assert.strictEqual(acquiredB, false, "Tab B bloklanishi kerak, chunki Tab A faol");

        // Tab A lockni bo'shatadi
        await aquaDb.releaseSyncLock('tab_A');

        // Endi Tab B lock olishi mumkin
        const acquiredB2 = await aquaDb.acquireSyncLock('tab_B', 30000);
        assert.strictEqual(acquiredB2, true, "Tab A bo'shatgach, Tab B lock olishi kerak");

        // Stale lock recovery sinovi:
        // Lock 40 soniya oldin olingan deb simulyatsiya qilamiz
        const metaLock = await getItem('meta', 'sync_lock');
        metaLock.acquired_at = Date.now() - 40000; // 40s eski (timeout 30s)
        await putItem('meta', metaLock);

        // Tab C kelib stale lockni buzib (recovery) o'ziga olishi kerak
        const acquiredC = await aquaDb.acquireSyncLock('tab_C', 30000);
        assert.strictEqual(acquiredC, true, "Stale lock avtomatik tiklanib Tab C ga berilishi kerak");

        await aquaDb.releaseSyncLock('tab_C');
        console.log("  [PASS] Mutex lock va stale lock recovery to'g'ri ishladi.\n");
        passed++;
    } catch (e) {
        console.error("  [FAIL] Test 1 xatosi:", e.message);
        process.exit(1);
    }

    // -------------------------------------------------------------
    // Test 2: Pending Push & Atomic ACK Processing (Safe Retention)
    // -------------------------------------------------------------
    try {
        console.log("Test 2: Pending push and atomic ACK processing with safe retention...");

        // 1. Tovar va kredit ajratmalarini kiritamiz
        await putItem('stock_allocations', {
            product_variant_id: 101,
            allocated_quantity: 100,
            consumed_quantity: 0,
            returned_quantity: 0
        });
        await putItem('customers', {
            id: 201,
            name: 'Bekzod Aka',
            current_debt: 0,
            debt_limit: 500000,
            is_strict_credit_limit: true
        });
        await putItem('credit_allocations', {
            customer_id: 201,
            allocated_credit: 500000,
            consumed_credit: 0
        });

        // 2. Savdo o'tkazamiz
        const saleOpId = 'sale-ack-op-001';
        await aquaDb.executeSaleTransaction({
            operationId: saleOpId,
            items: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 20, sale_price: 6000 }],
            customerId: 201,
            totalAmount: 120000,
            paidAmount: 50000,
            debtAmount: 70000,
            paymentMethod: 'CASH',
            notes: 'Test ACK sale'
        });

        // Outbox tekshiruvi: status PENDING bo'lishi kerak
        const outboxBefore = await getItem('sync_outbox', saleOpId);
        assert.strictEqual(outboxBefore.status, 'PENDING');

        // 3. Serverdan ACK kelishini simulyatsiya qilamiz
        const serverResults = [
            {
                operation_id: saleOpId,
                status: 'APPLIED',
                server_document_id: 888,
                server_document_number: 'INV-2026-000888',
                data: { posted_at: '2026-10-04T05:00:00Z' }
            }
        ];

        const ackStats = await aquaDb.applyPushResults(serverResults);
        assert.strictEqual(ackStats.applied, 1, "1 ta amal APPLIED bo'lishi kerak");

        // 4. Qat'iy retention tekshiruvi:
        // "ACK chek/payloadni xavfsiz retention davomida saqlang, faqat queue flagni yakunlang; backup tiklash uchun history kerak."
        const outboxAfter = await getItem('sync_outbox', saleOpId);
        assert.ok(outboxAfter, "Outbox yozuvi O'CHIRILMASLIGI SHART!");
        assert.strictEqual(outboxAfter.status, 'APPLIED', "Status APPLIED ga o'tgan bo'lishi kerak");
        assert.strictEqual(outboxAfter.server_document_number, 'INV-2026-000888');
        assert.ok(outboxAfter.applied_at, "applied_at vaqti yozilgan bo'lishi kerak");

        // Sales jadvalidagi chek ham SERVER_SYNCED bo'lishi kerak
        const allSales = await getAllItems('sales');
        const updatedSale = allSales.find(s => s.operation_id === saleOpId);
        assert.strictEqual(updatedSale.sync_status, 'SERVER_SYNCED');
        assert.strictEqual(updatedSale.server_document_number, 'INV-2026-000888');

        console.log("  [PASS] Atomic ACK va xavfsiz outbox retention to'g'ri ishladi.\n");
        passed++;
    } catch (e) {
        console.error("  [FAIL] Test 2 xatosi:", e.message);
        process.exit(1);
    }

    // -------------------------------------------------------------
    // Test 3: Timeout and Idempotent Replay (Zero Duplicates)
    // -------------------------------------------------------------
    try {
        console.log("Test 3: Timeout handling and idempotent replay without duplicates...");

        const replayOpId = 'sale-replay-op-002';
        await aquaDb.executeSaleTransaction({
            operationId: replayOpId,
            items: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 5, sale_price: 6000 }],
            customerId: 201,
            totalAmount: 30000,
            paidAmount: 30000,
            debtAmount: 0,
            paymentMethod: 'CASH'
        });

        // Serverga yuborildi, lekin response yo'lda tushib qoldi deb hisoblaymiz.
        // Client qayta yuborganda server RETRY_SUCCESS qaytaradi:
        const replayResults = [
            {
                operation_id: replayOpId,
                status: 'RETRY_SUCCESS',
                server_document_id: 889,
                server_document_number: 'INV-2026-000889',
                data: { posted_at: '2026-10-04T05:01:00Z' }
            }
        ];

        const replayStats = await aquaDb.applyPushResults(replayResults);
        assert.strictEqual(replayStats.retrySuccess, 1, "RETRY_SUCCESS deb qabul qilinishi kerak");

        const outboxItem = await getItem('sync_outbox', replayOpId);
        assert.strictEqual(outboxItem.status, 'APPLIED');
        assert.strictEqual(outboxItem.server_document_number, 'INV-2026-000889');

        // Takroriy chek ko'paymaganini tekshiramiz
        const salesMatching = (await getAllItems('sales')).filter(s => s.operation_id === replayOpId);
        assert.strictEqual(salesMatching.length, 1, "Savdo dublikat bo'lmagan bo'lishi kerak");

        console.log("  [PASS] Idempotent replay va timeoutdan keyin dublikatsizlik tasdiqlandi.\n");
        passed++;
    } catch (e) {
        console.error("  [FAIL] Test 3 xatosi:", e.message);
        process.exit(1);
    }

    // -------------------------------------------------------------
    // Test 4: Incremental Cursor Pull & Delta Feed
    // -------------------------------------------------------------
    try {
        console.log("Test 4: Incremental cursor pull and delta feed application...");

        await putItem('meta', { key: 'last_cursor', value: 10 });

        const pulledEvents = [
            {
                aggregate_type: 'VARIANT',
                action: 'UPDATED',
                payload: { id: 101, name: 'Fanta 0.5L Yangi Dizayn', default_sale_price: 6500 }
            },
            {
                aggregate_type: 'CUSTOMER',
                action: 'UPDATED',
                payload: { id: 201, name: 'Bekzod Aka (VIP)', current_debt: 70000, debt_limit: 1000000 }
            }
        ];

        const pullRes = await aquaDb.applyPulledChanges(pulledEvents, 25);
        assert.strictEqual(pullRes.appliedEventsCount, 2);
        assert.strictEqual(pullRes.nextCursor, 25);

        // Kursor va lokal baza tekshiruvi
        const newCursor = await getItem('meta', 'last_cursor');
        assert.strictEqual(newCursor.value, 25);

        const updatedCust = await getItem('customers', 201);
        assert.strictEqual(updatedCust.name, 'Bekzod Aka (VIP)');
        assert.strictEqual(updatedCust.debt_limit, 1000000);

        console.log("  [PASS] Kursor pull va delta o'zgarishlar lokal bazaga muvaffaqiyatli qo'llandi.\n");
        passed++;
    } catch (e) {
        console.error("  [FAIL] Test 4 xatosi:", e.message);
        process.exit(1);
    }

    // -------------------------------------------------------------
    // Test 5: Remaining Pending Overlay
    // -------------------------------------------------------------
    try {
        console.log("Test 5: Remaining pending overlay calculation...");

        // Pending savdo yaratamiz
        const pendingOpId = 'sale-pending-overlay-003';
        await aquaDb.executeSaleTransaction({
            operationId: pendingOpId,
            items: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 12, sale_price: 6500 }],
            customerId: 201,
            totalAmount: 78000,
            paidAmount: 28000,
            debtAmount: 50000,
            paymentMethod: 'CASH'
        });

        const overlay = await aquaDb.getPendingOverlay();
        assert.strictEqual(overlay.pendingCount >= 1, true);
        assert.strictEqual(overlay.pendingStockConsumed.get(101), 12, "Pending sarflangan tovar 12 dona bo'lishi kerak");
        assert.strictEqual(overlay.pendingCustomerDebt.get(201), 50000, "Pending nasiya 50,000 so'm bo'lishi kerak");

        console.log("  [PASS] Pending overlay qoldiqlari to'g'ri hisoblandi.\n");
        passed++;
    } catch (e) {
        console.error("  [FAIL] Test 5 xatosi:", e.message);
        process.exit(1);
    }

    // -------------------------------------------------------------
    // Test 6: Offline Void / Cancel (Original Retained & Correction Queued)
    // -------------------------------------------------------------
    try {
        console.log("Test 6: Offline void/cancellation: original retained, correction queued...");

        const voidTestOpId = 'sale-to-void-004';
        await aquaDb.executeSaleTransaction({
            operationId: voidTestOpId,
            items: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 10, sale_price: 6500 }],
            customerId: 201,
            totalAmount: 65000,
            paidAmount: 65000,
            debtAmount: 0,
            paymentMethod: 'CASH'
        });

        // Bekor qilishdan oldingi ombor sarfi
        const stockBeforeVoid = await getItem('stock_allocations', 101);
        const consumedBefore = stockBeforeVoid.consumed_quantity;

        // Offline bekor qilish amali
        const voidRes = await aquaDb.voidOfflineSale(voidTestOpId, "Xaridor fikridan qaytdi");
        assert.strictEqual(voidRes.success, true);
        assert.ok(voidRes.void_operation_id);

        // 1. Ombor ajratmasi tiklangan bo'lishi kerak
        const stockAfterVoid = await getItem('stock_allocations', 101);
        assert.strictEqual(stockAfterVoid.consumed_quantity, consumedBefore - 10, "10 dona ajratma qaytarilgan bo'lishi kerak");

        // 2. Savdo statusi CANCELLED bo'lishi kerak
        const allSales = await getAllItems('sales');
        const voidedSale = allSales.find(s => s.operation_id === voidTestOpId);
        assert.strictEqual(voidedSale.status, 'CANCELLED');
        assert.strictEqual(voidedSale.sync_status, 'CANCELLED_LOCALLY');
        assert.strictEqual(voidedSale.cancellation_reason, "Xaridor fikridan qaytdi");

        // 3. Outbox tekshiruvi:
        // Asl CREATE_SALE saqlangan (DELETE EMAS!)
        const originalOutbox = await getItem('sync_outbox', voidTestOpId);
        assert.ok(originalOutbox, "Asl CREATE_SALE navbatdan o'chirilmasligi shart!");

        // Yangi VOID_SALE yozuvi navbatga qo'shilgan
        const voidOutbox = await getItem('sync_outbox', voidRes.void_operation_id);
        assert.ok(voidOutbox, "VOID_SALE navbatga qo'shilgan bo'lishi kerak");
        assert.strictEqual(voidOutbox.type, 'VOID_SALE');
        assert.strictEqual(voidOutbox.payload.original_operation_id, voidTestOpId);

        console.log("  [PASS] Offline savdoni bekor qilish va asl yozuvni saqlagan holda tuzatish muvaffaqiyatli o'tdi.\n");
        passed++;
    } catch (e) {
        console.error("  [FAIL] Test 6 xatosi:", e.message);
        process.exit(1);
    }

    // -------------------------------------------------------------
    // Test 7: NEEDS_REVIEW Handling in Outbox
    // -------------------------------------------------------------
    try {
        console.log("Test 7: NEEDS_REVIEW status handling in outbox...");

        const conflictOpId = 'sale-conflict-005';
        await aquaDb.executeSaleTransaction({
            operationId: conflictOpId,
            items: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 2, sale_price: 6500 }],
            customerId: 201,
            totalAmount: 13000,
            paidAmount: 13000,
            debtAmount: 0,
            paymentMethod: 'CASH'
        });

        // Server NEEDS_REVIEW qaytardi (masalan kech kelgan yopilgan smena)
        const conflictResults = [
            {
                operation_id: conflictOpId,
                status: 'NEEDS_REVIEW',
                conflict_id: 99,
                error_code: 'LATE_CLOSED_SESSION',
                message: "Kassa smenasi yopilgandan so'ng kelgan savdo. Admin tekshiruvi kerak."
            }
        ];

        const conflictStats = await aquaDb.applyPushResults(conflictResults);
        assert.strictEqual(conflictStats.needsReview, 1);

        const outboxConflict = await getItem('sync_outbox', conflictOpId);
        assert.strictEqual(outboxConflict.status, 'NEEDS_REVIEW');
        assert.strictEqual(outboxConflict.conflict_id, 99);
        assert.strictEqual(outboxConflict.error_code, 'LATE_CLOSED_SESSION');

        console.log("  [PASS] NEEDS_REVIEW holati to'liq saqlanib qayd etildi.\n");
        passed++;
    } catch (e) {
        console.error("  [FAIL] Test 7 xatosi:", e.message);
        process.exit(1);
    }

    console.log(`===============================================`);
    console.log(`Barcha ${passed}/7 ta sinov 100% muvaffaqiyatli o'tdi!`);
    console.log(`===============================================`);
}

runTests();
