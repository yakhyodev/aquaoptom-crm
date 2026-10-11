const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

// Exercise the actual Alpine component with only storage/network dependencies stubbed.
const source = fs.readFileSync(path.join(__dirname, '../resources/js/offline/aqua-pos.js'), 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replace('export function aquaPos()', 'function aquaPos()');
const context = vm.createContext({ AquaDB: class {}, navigator: { onLine: false } });
vm.runInContext(source + '\nthis.createPos = aquaPos;', context);
(async () => {
    const pos = context.createPos();
    pos.catalog = [
        {id: 1, volume_ml: 500, volume_name: '0.5 L'},
        {id: 2, volume_ml: 5000, volume_name: '5 L'},
        {id: 3, volume_ml: 2500, volume_name: 'Custom 2.5 L'},
        {id: 4, volume_litres: '18.900', volume_name: '18.9 L'},
        {id: 5, volume_ml: 19000, volume_name: '19 L'},
    ];
    pos.selectedVolume = '5000';
    assert.equal(JSON.stringify(pos.filteredCatalog.map(row => row.id)), '[2]');
    pos.selectedVolume = '2500';
    assert.equal(JSON.stringify(pos.filteredCatalog.map(row => row.id)), '[3]');
    assert.equal(JSON.stringify(pos.availableVolumes.map(row => row.key)), '["500","2500","5000","18900","19000"]');
    pos.selectedVolume = 'all';

    pos.cart = [{ variant_id: 1, product_name: 'Water', quantity: 1, sale_price: 5000 }];
    pos.stockAllocations.set(1, { allocated_quantity: 500, consumed_quantity: 0, returned_quantity: 0 });
    const alerts = [];
    pos.showAlert = (type, message) => alerts.push(message);
    pos.saveCartDraft = async () => {};
    await pos.updateQuantity(0, '');
    assert.equal(pos.cart.length, 1, 'Clearing the input must not delete the item');
    await pos.updateQuantity(0, '200');
    assert.equal(pos.cart[0].quantity, 200);
    assert.equal(pos.paidAmount, 1000000, 'Full payment must follow a typed bulk quantity');
    assert.equal(pos.debtAmount, 0);
    for (const value of ['1.5', '0', '-1', 'abc', '501']) {
        await pos.updateQuantity(0, value);
        assert.equal(pos.cart[0].quantity, 200, 'Invalid quantity must preserve the previous draft');
    }
    await pos.updatePrice(0, '100.5');
    assert.equal(pos.cart[0].sale_price, 5000);
    await pos.updatePrice(0, '6000');
    assert.equal(pos.cart[0].sale_price, 6000);
    assert.equal(pos.paidAmount, 1200000, 'Full payment must follow a price change');
    assert.equal(pos.cart[0].is_system_price, false);
    assert.equal(alerts.length, 7);
    pos.stockAllocations.set(2, { allocated_quantity: 500, consumed_quantity: 0, returned_quantity: 0 });
    await pos.addToCart({id: 2, product_name: 'Fanta', default_sale_price: 5000});
    assert.equal(pos.paidAmount, 1205000, 'Adding a second product must not create unexpected debt');
    assert.equal(pos.debtAmount, 0);
    pos.onPaymentMethodChange('PARTIAL');
    pos.paidAmount = 200000;
    await pos.updateQuantity(0, '250');
    assert.equal(pos.paidAmount, 200000, 'Partial payment preserves the explicitly entered amount');
    assert.equal(pos.debtAmount, 1305000);
    pos.onPaymentMethodChange('DEBT');
    assert.equal(pos.paidAmount, 0);
    assert.equal(pos.debtAmount, pos.totalAmount);
    pos.switchSaleMode('quick');
    assert.equal(pos.paymentMode, 'FULL');
    assert.equal(pos.paidAmount, pos.totalAmount);
    await pos.removeFromCart(1);
    assert.equal(pos.paidAmount, 1500000);
    assert.equal(pos.debtAmount, 0);
    console.log('PWA KEYBOARD QUANTITY TESTS PASSED');

    const selectSource = fs.readFileSync(path.join(__dirname, '../resources/js/searchable-select.js'), 'utf8').replace('export function', 'function');
    vm.runInContext(selectSource + '\nthis.createSelect = searchableSelect;', context);
    const options = Array.from({length: 300}, (_, id) => ({value: id, label: `Mahsulot ${300 - id}`}));
    options.push({value: 400, label: 'O‘tkir · Bahor Market · +998 (90) 111-22-33'});
    const picker = context.createSelect(options, '');
    assert.equal(picker.results.length, 20, 'Hundreds of options must not fill the screen');
    picker.query = "o'tkir bahor 901112233";
    assert.equal(picker.results.length, 1, 'Names, store names and formatted phones can be combined');
    picker.choose(picker.results[0]);
    assert.equal(picker.selected, '400');
    picker.typeQuery('boshqa mahsulot');
    assert.equal(picker.selected, null, 'Searching for another item must invalidate the previous selection');
    assert.equal(picker.query, 'boshqa mahsulot');
    picker.clear();
    assert.equal(picker.results[0].label, 'Mahsulot 1', 'Options must be alphabetical with natural numeric order');
    pos.customers = [{id: 10, name: 'Akmal', store_name: 'Bahor', phone: '+998 (90) 111-22-33'}];
    pos.customerQuery = 'akmal 901112233';
    assert.equal(pos.filteredCustomers.length, 1, 'Offline customer search supports store names and formatted phone numbers');

    const syncSource = fs.readFileSync(path.join(__dirname, '../resources/js/offline/aqua-sync.js'), 'utf8')
        .replace(/^import .*;\r?\n/gm, '').replace('export class AquaSync', 'class AquaSync');
    const oldLease = {device_uuid: 'device-one', lease_token: 'old', api_token: 'keep', expires_at: new Date(0).toISOString()};
    const records = new Map([['device_lease:current', oldLease], ['outbox:one', {operation_id: 'original', status: 'PENDING'}]]);
    const calls = [];
    const db = {acquireSyncLock: async () => true, releaseSyncLock: async () => {},
        get: async (table, key) => records.get(`${table}:${key}`), put: async (table, row) => records.set(`${table}:${row.key}`, row)};
    context.fetch = async (url, request) => {
        if (url.endsWith('/sync/renew-lease')) {
            calls.push('renew');
            assert.equal(JSON.parse(request.body).device_uuid, oldLease.device_uuid);
            return {ok: true, json: async () => ({data: {lease_token: 'new', expires_at: new Date(Date.now() + 86400000).toISOString()}})};
        }
        return {ok: true, json: async () => ({})};
    };
    context.AbortSignal = AbortSignal;
    vm.runInContext(syncSource + '\nthis.SyncEngine = AquaSync;', context);
    const engine = new context.SyncEngine(db);
    engine.checkHealth = async () => ({isOnline: true});
    engine.pushPendingQueue = async () => {calls.push('push'); return {};};
    engine.pullServerChanges = async () => {calls.push('pull'); return {};};
    assert.equal((await engine.syncNow('manual', true)).status, 'SUCCESS');
    assert.deepEqual(calls, ['push', 'renew', 'pull'], 'Pending operations must retain their original lease before renewal');
    assert.equal(records.get('outbox:one').operation_id, 'original');
    assert.equal(records.get('device_lease:current').api_token, 'keep');
    assert.equal(records.get('device_lease:current').lease_token, 'new');
    console.log('SEARCH AND SAFE LEASE RENEWAL TESTS PASSED');
})().catch(error => { console.error(error); process.exitCode = 1; });
