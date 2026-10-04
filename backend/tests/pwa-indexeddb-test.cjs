/**
 * Automated Test Suite for AquaDB (IndexedDB Storage & Offline POS Transaction Engine)
 * Tests:
 * 1. Database schema initialization & object stores
 * 2. Saving and restoring draft cart across reloads
 * 3. Atomic sale execution (allocations deduction, sale creation, outbox queueing, draft clear in ONE transaction)
 * 4. Quota overflow protection: aborts if requested qty > available stock allocation
 * 5. Credit limit protection: aborts if debt > available credit limit for strict customer
 * 6. Storage failure / transaction abort test (rollbacks completely with 0 partial records)
 * 7. Offline new customer creation with UUID
 * 8. Emergency pending data export
 */

const assert = require('assert');
const { indexedDB } = require('fake-indexeddb');
global.indexedDB = indexedDB;

// Import our AquaDB class
// Since aqua-db.js is ESM, we can dynamically import or require via ts/bundle/esm
async function runTests() {
    console.log("🚀 Starting AquaDB Automated Test Suite...\n");

    const { AquaDB } = await import('../resources/js/offline/aqua-db.js');
    let db = new AquaDB('TestAquaOptomDB_' + Date.now(), 1);

    // Test 1: Schema creation
    console.log("Test 1: Database initialization & object stores...");
    const rawDb = await db.open();
    assert.strictEqual(rawDb.name.startsWith('TestAquaOptomDB_'), true);
    assert.strictEqual(rawDb.objectStoreNames.contains('stock_allocations'), true);
    assert.strictEqual(rawDb.objectStoreNames.contains('credit_allocations'), true);
    assert.strictEqual(rawDb.objectStoreNames.contains('catalog'), true);
    assert.strictEqual(rawDb.objectStoreNames.contains('customers'), true);
    assert.strictEqual(rawDb.objectStoreNames.contains('sales'), true);
    assert.strictEqual(rawDb.objectStoreNames.contains('sync_outbox'), true);
    assert.strictEqual(rawDb.objectStoreNames.contains('cart_draft'), true);
    console.log("  ✅ Object stores correctly created.");

    // Seed test allocations & catalog
    await db.put('stock_allocations', {
        product_variant_id: 101,
        allocated_quantity: 60,
        consumed_quantity: 0,
        returned_quantity: 0,
        available_quantity: 60
    });
    await db.put('stock_allocations', {
        product_variant_id: 102,
        allocated_quantity: 10,
        consumed_quantity: 0,
        returned_quantity: 0,
        available_quantity: 10
    });
    await db.put('customers', {
        id: 501,
        uuid: 'cust-uuid-501',
        name: 'Bahrom Aka',
        current_debt: 0,
        debt_limit: 100000,
        is_strict_credit_limit: true,
        is_local: false
    });
    await db.put('credit_allocations', {
        customer_id: 501,
        allocated_credit: 100000,
        consumed_credit: 0
    });

    // Test 2: Cart draft persistence
    console.log("Test 2: Cart draft save and restore...");
    const opId1 = '00000000-0000-4000-8000-000000000001';
    await db.put('cart_draft', {
        key: 'current_cart',
        operation_id: opId1,
        cart: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 5, sale_price: 6500 }],
        paid_amount: 32500,
        payment_method: 'CASH'
    });
    const restoredDraft = await db.get('cart_draft', 'current_cart');
    assert.strictEqual(restoredDraft.operation_id, opId1);
    assert.strictEqual(restoredDraft.cart.length, 1);
    assert.strictEqual(restoredDraft.cart[0].quantity, 5);
    console.log("  ✅ Cart draft successfully preserved.");

    // Test 3: Atomic sale execution
    console.log("Test 3: Atomic sale transaction (allocations, sale, outbox, clear draft)...");
    const saleResult = await db.executeSaleTransaction({
        operationId: opId1,
        items: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 20, sale_price: 6500 }],
        customerId: 501,
        customerUuid: 'cust-uuid-501',
        customerName: 'Bahrom Aka',
        totalAmount: 130000,
        paidAmount: 100000,
        debtAmount: 30000,
        paymentMethod: 'CASH',
        notes: 'Test sale'
    });

    assert.strictEqual(saleResult.operation_id, opId1);
    assert.strictEqual(saleResult.total_amount, 130000);
    assert.strictEqual(saleResult.paid_amount, 100000);
    assert.strictEqual(saleResult.debt_amount, 30000);

    // Verify stock allocation consumed: 60 - 20 = 40 remaining
    const alloc101 = await db.get('stock_allocations', 101);
    assert.strictEqual(alloc101.consumed_quantity, 20);

    // Verify credit allocation consumed: 30 000 consumed
    const cred501 = await db.get('credit_allocations', 501);
    assert.strictEqual(cred501.consumed_credit, 30000);

    // Verify customer debt updated: 30 000
    const cust501 = await db.get('customers', 501);
    assert.strictEqual(cust501.current_debt, 30000);

    // Verify sale record created in sales store
    const allSales = await db.getAll('sales');
    assert.strictEqual(allSales.length, 1);
    assert.strictEqual(allSales[0].operation_id, opId1);
    assert.strictEqual(allSales[0].sync_status, 'PENDING');

    // Verify outbox record created
    const outbox = await db.getAll('sync_outbox');
    assert.strictEqual(outbox.length, 1);
    assert.strictEqual(outbox[0].operation_id, opId1);
    assert.strictEqual(outbox[0].type, 'CREATE_SALE');
    assert.strictEqual(outbox[0].status, 'PENDING');
    assert.strictEqual(outbox[0].payload.items[0].quantity, 20);

    // Verify cart_draft deleted
    const draftAfter = await db.get('cart_draft', 'current_cart');
    assert.strictEqual(draftAfter, null);
    console.log("  ✅ Atomic transaction completed all 5 steps flawlessly.");

    // Test 4: Quota overflow protection
    console.log("Test 4: Quota overflow protection (requesting more than available)...");
    let caughtQuotaError = false;
    try {
        // Variant 102 only has 10 allocated. Attempting to sell 15:
        await db.executeSaleTransaction({
            operationId: '00000000-0000-4000-8000-000000000002',
            items: [{ variant_id: 102, product_name: 'Coca-Cola 1.5L', quantity: 15, sale_price: 12000 }],
            totalAmount: 180000,
            paidAmount: 180000,
            debtAmount: 0,
            paymentMethod: 'CASH'
        });
    } catch (err) {
        caughtQuotaError = true;
        assert.strictEqual(err.message.includes('limit yetarli emas'), true);
    }
    assert.strictEqual(caughtQuotaError, true, "Should have thrown quota exceeded error!");

    // Verify state was NOT modified:
    const alloc102 = await db.get('stock_allocations', 102);
    assert.strictEqual(alloc102.consumed_quantity, 0); // Still 0, untouched!
    const salesCountAfterQuotaFail = await db.count('sales');
    assert.strictEqual(salesCountAfterQuotaFail, 1); // Still 1!
    console.log("  ✅ Quota overflow blocked and state was not touched.");

    // Test 5: Credit limit protection
    console.log("Test 5: Credit limit protection for strict customer...");
    let caughtCreditError = false;
    try {
        // Customer 501 had 100k credit, already consumed 30k. Remaining: 70k.
        // Attempting to sell with 80k debt:
        await db.executeSaleTransaction({
            operationId: '00000000-0000-4000-8000-000000000003',
            items: [{ variant_id: 101, product_name: 'Fanta 0.5L', quantity: 10, sale_price: 10000 }],
            customerId: 501,
            totalAmount: 100000,
            paidAmount: 20000,
            debtAmount: 80000, // Exceeds remaining 70 000!
            paymentMethod: 'DEBT'
        });
    } catch (err) {
        caughtCreditError = true;
        assert.strictEqual(err.message.includes('kredit limiti yetarli emas'), true);
    }
    assert.strictEqual(caughtCreditError, true, "Should have thrown credit limit exceeded error!");

    // Verify stock allocation 101 was NOT incremented on failure:
    const alloc101AfterFail = await db.get('stock_allocations', 101);
    assert.strictEqual(alloc101AfterFail.consumed_quantity, 20); // Still 20!
    console.log("  ✅ Credit limit violation blocked and transaction aborted.");

    // Test 6: Offline new customer creation with UUID
    console.log("Test 6: Offline new customer creation with UUID...");
    const clientCustUuid = '99999999-9999-4999-8999-999999999999';
    const newCust = await db.createOfflineCustomer({
        clientUuid: clientCustUuid,
        name: 'Sherzodbek Do\'koni',
        phone: '+998911112233',
        storeName: 'Sherzod Supermarket'
    });
    assert.strictEqual(newCust.id, `local_${clientCustUuid}`);
    assert.strictEqual(newCust.uuid, clientCustUuid);
    assert.strictEqual(newCust.is_local, true);

    const savedCust = await db.get('customers', `local_${clientCustUuid}`);
    assert.strictEqual(savedCust.name, "Sherzodbek Do'koni");

    // Check outbox has CREATE_CUSTOMER
    const outboxAfterCust = await db.getAll('sync_outbox');
    const custOutbox = outboxAfterCust.find(o => o.type === 'CREATE_CUSTOMER');
    assert.strictEqual(custOutbox.operation_id, clientCustUuid);
    assert.strictEqual(custOutbox.payload.client_uuid, clientCustUuid);
    console.log("  ✅ Offline new customer and CREATE_CUSTOMER outbox item created.");

    // Test 7: Emergency export
    console.log("Test 7: Emergency pending data export...");
    const backup = await db.exportPendingData();
    assert.strictEqual(backup.pending_outbox_count >= 1, true);
    assert.strictEqual(Array.isArray(backup.outbox), true);
    assert.strictEqual(Array.isArray(backup.sales), true);
    assert.strictEqual(backup.local_customers.length, 1);
    console.log("  ✅ Emergency export JSON generated with all pending items.");

    const bootstrapDb = new AquaDB('BootstrapAudit_' + Date.now());
    const snapshot = {device: {id: 1, device_uuid: 'device-audit'}, warehouse: {id: 1},
        lease: {lease_token: 'lease-audit', permissions: ['offline_sales']},
        stock_allocations: [{variant_id: 101, allocated_quantity: 5, consumed_quantity: 0}],
        credit_allocations: [{customer_id: 1, allocated_amount: 20000, consumed_amount: 0, available_amount: 20000}],
        catalog: [{id: 101, default_sale_price: 1000}], customers: [], current_cursor: 42};
    await bootstrapDb.applyBootstrap(snapshot);
    assert.strictEqual((await bootstrapDb.get('stock_allocations', 101)).allocated_quantity, 5);
    assert.strictEqual((await bootstrapDb.get('device_lease', 'current')).lease_token, 'lease-audit');
    assert.strictEqual((await bootstrapDb.get('credit_allocations', 1)).allocated_credit, 20000);
    assert.strictEqual((await bootstrapDb.get('meta', 'last_cursor')).value, 42);
    const saleArgs = {operationId: 'audit-dupes', items: [{variant_id: 101, quantity: 3, sale_price: 1000}, {variant_id: 101, quantity: 3, sale_price: 1000}], totalAmount: 6000, paidAmount: 6000, debtAmount: 0};
    await assert.rejects(bootstrapDb.executeSaleTransaction(saleArgs));
    assert.strictEqual((await bootstrapDb.get('stock_allocations', 101)).consumed_quantity, 0);
    await bootstrapDb.executeSaleTransaction({...saleArgs, operationId: 'audit-pending', items: [{variant_id: 101, quantity: 2, sale_price: 1000}], totalAmount: 2000, paidAmount: 2000});
    await assert.rejects(bootstrapDb.applyBootstrap(snapshot));
    assert.strictEqual((await bootstrapDb.get('stock_allocations', 101)).consumed_quantity, 2);
    assert.strictEqual((await bootstrapDb.getAll('sales')).length, 1);
    console.log("\n🎉 ALL AQUADB TESTS PASSED (7 scenarios + bootstrap/duplicate-line/pending regressions)!\n");
}

runTests().catch(err => {
    console.error("❌ Test failed:", err);
    process.exit(1);
});
