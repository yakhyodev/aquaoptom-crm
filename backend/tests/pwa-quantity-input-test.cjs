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
    pos.cart = [{ variant_id: 1, product_name: 'Water', quantity: 1, sale_price: 5000 }];
    pos.stockAllocations.set(1, { allocated_quantity: 500, consumed_quantity: 0, returned_quantity: 0 });
    const alerts = [];
    pos.showAlert = (type, message) => alerts.push(message);
    pos.autoAdjustPayment = () => {};
    pos.saveCartDraft = async () => {};
    await pos.updateQuantity(0, '');
    assert.equal(pos.cart.length, 1, 'Clearing the input must not delete the item');
    await pos.updateQuantity(0, '200');
    assert.equal(pos.cart[0].quantity, 200);
    for (const value of ['1.5', '0', '-1', 'abc', '501']) {
        await pos.updateQuantity(0, value);
        assert.equal(pos.cart[0].quantity, 200, 'Invalid quantity must preserve the previous draft');
    }
    await pos.updatePrice(0, '100.5');
    assert.equal(pos.cart[0].sale_price, 5000);
    await pos.updatePrice(0, '6000');
    assert.equal(pos.cart[0].sale_price, 6000);
    assert.equal(pos.cart[0].is_system_price, false);
    assert.equal(alerts.length, 7);
    console.log('PWA KEYBOARD QUANTITY TESTS PASSED');
})().catch(error => { console.error(error); process.exitCode = 1; });
