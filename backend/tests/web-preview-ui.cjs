const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const evidence = [];
    const failures = [];
    page.on('pageerror', error => failures.push(error.message));
    page.on('response', response => { if (response.status() >= 400) failures.push(response.status() + ' ' + new URL(response.url()).pathname); });
    fs.mkdirSync('ui-evidence', { recursive: true });
    async function checkContrast(locator, label) {
        const result = await locator.evaluate(el => {
            const context = document.createElement('canvas').getContext('2d');
            function rgb(color) {
                context.clearRect(0, 0, 1, 1);
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);
                return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
            }
            function luminance(values) {
                const linear = values.map(v => { const c = v / 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; });
                return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
            }
            let parent = el;
            while (parent && getComputedStyle(parent).backgroundColor === 'rgba(0, 0, 0, 0)') parent = parent.parentElement;
            const background = getComputedStyle(parent || document.body).backgroundColor;
            const foreground = getComputedStyle(el).color;
            const placeholder = getComputedStyle(el, '::placeholder').color;
            function ratio(color) {
                const values = [luminance(rgb(background)), luminance(rgb(color))].sort((a, b) => a - b);
                return (values[1] + 0.05) / (values[0] + 0.05);
            }
            return { foreground, background, textRatio: ratio(foreground), placeholderRatio: ratio(placeholder) };
        });
        evidence.push({ label, ...result });
        assert(result.textRatio >= 4.5, label + ': unreadable text ' + result.textRatio);
        if (await locator.getAttribute('placeholder')) assert(result.placeholderRatio >= 4.5, label + ': unreadable placeholder');
    }
    async function waitTotal(value) {
        await page.waitForFunction(value => document.querySelector('.trade-total > strong')?.textContent.replace(/\D/g, '') === value, String(value));
    }
    async function screenshot(name) {
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: 'ui-evidence/' + name + '.png', fullPage: true, animations: 'disabled' });
    }
    async function checkMobileSearch(route) {
        const selector = { '/kassa': '#cash-search', '/qarzdorliklar': '#debt-search' }[route];
        if (!selector) return;
        const width = await page.locator(selector).evaluate(el => el.getBoundingClientRect().width);
        assert(width >= 240, route + ': search field is too narrow to type a name or phone number (' + width + 'px)');
    }
    try {
        await page.goto(process.env.PREVIEW_URL + '/login');
        await page.locator('#email').fill('admin');
        await page.locator('#password').fill(process.env.PREVIEW_OWNER_PASSWORD);
        await screenshot('login');
        await page.locator('button[type=submit]').click();
        await page.waitForURL('**/dashboard');
        if (process.env.BROWSER_SCENARIO === 'read_only') {
            assert.match(await page.locator('[data-testid="dashboard-cash"]').textContent(), /^\s*0\s*so/);
            await screenshot('clean-dashboard');
            for (const route of ['/kassa', '/qarzdorliklar', '/inward', '/sotuv', '/ombor', '/savdo', '/hisobotlar', '/yordam']) {
                await page.setViewportSize({ width: 390, height: 844 });
                const response = await page.goto(process.env.PREVIEW_URL + route);
                assert.equal(response.status(), 200, route + ': clean panel failed to open');
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), route + ': mobile overflow');
                await checkMobileSearch(route);
                assert(!/Fanta test|Sinov mijoz|Sinov yetkazuvchisi/.test(await page.locator('main').innerText()), route + ': unwanted fixture');
                await screenshot('clean-' + route.replaceAll('/', '-') + '-mobile');
            }
            assert.equal(failures.length, 0, 'Browser errors: ' + failures.join('; '));
            console.log('Read-only clean preview passed: no business fixtures, zero cash, all main screens available.');
            return;
        }
        await page.goto(process.env.PREVIEW_URL + '/inward');
        await page.getByRole('button', { name: '+ Yangi Mahsulot / hajm', exact: true }).click();
        await page.getByPlaceholder('Masalan: Fanta, Dinay...').fill('Fanta test');
        await page.locator('select').filter({ has: page.locator('option[value="custom"]') }).selectOption('custom');
        const volume = page.getByPlaceholder('Masalan: 0.75 L yoki 750 ml');
        await volume.fill('0.75 L');
        await checkContrast(volume, 'Custom volume');
        await page.getByPlaceholder('Ixtiyoriy, masalan: 7000').fill('7000');
        await screenshot('custom-volume');
        await page.getByRole('button', { name: 'Saqlash va tanlash', exact: true }).click();
        const quantity = page.locator('#inward-quantity-0');
        await quantity.fill('150');
        await page.locator('#inward-cost-0').fill('5000');
        await waitTotal(750000);
        await checkContrast(quantity, 'Receiving quantity');
        await checkContrast(page.locator('#inward-cost-0'), 'Receiving price');
        await page.getByRole('button', { name: '+ Yangi yetkazuvchi', exact: true }).click();
        await page.getByPlaceholder('Masalan: Jamshid (Sklad)').fill('Sinov yetkazuvchisi');
        await page.getByRole('button', { name: 'Saqlash va tanlash', exact: true }).click();
        await page.getByRole('button', { name: '✓ Omborga kirim qilish', exact: true }).waitFor({ state: 'visible' });
        await screenshot('receiving-desktop');
        await page.setViewportSize({ width: 390, height: 844 });
        await screenshot('receiving-mobile');
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'Receiving overflows mobile viewport');
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.getByRole('button', { name: '✓ Omborga kirim qilish', exact: true }).click();
        await page.getByRole('heading', { name: 'Mahsulotlar omborga qo‘shildi!', exact: true }).waitFor();
        await screenshot('receiving-success');
        await page.goto(process.env.PREVIEW_URL + '/sotuv');
        await page.getByLabel('Mahsulot', { exact: true }).selectOption({ label: 'Fanta test' });
        await page.getByLabel('Hajmi / litri', { exact: true }).selectOption({ label: '0.75 L' });
        await page.getByRole('button', { name: '+ Qo‘shish', exact: true }).click();
        await page.locator('#sale-quantity-0').fill('200');
        await waitTotal(1400000);
        assert.equal(await page.locator('#sale-quantity-0').inputValue(), '200');
        await screenshot('quick-sale-200-keyboard');
        await page.locator('#sale-quantity-0').fill('10');
        await waitTotal(70000);
        await page.getByRole('button', { name: /Mijozga sotuv/ }).click();
        await page.getByRole('button', { name: '+ Yangi Mijoz', exact: true }).click();
        const customerName = page.getByPlaceholder('Masalan: Dilshod aka');
        await customerName.fill('Sinov mijoz');
        await page.getByPlaceholder('+998901234567', { exact: true }).fill('+998900000001');
        await page.getByPlaceholder("Do'kon", { exact: true }).fill('Sinov Market');
        await page.getByPlaceholder('Manzil', { exact: true }).fill('Sinov manzili');
        await checkContrast(customerName, 'New customer form');
        await screenshot('new-customer');
        await page.getByRole('button', { name: 'Saqlash va tanlash', exact: true }).click();
        await page.getByText('Qisman to‘lov', { exact: true }).click();
        await page.locator('#sale-paid').fill('20000');
        await page.waitForFunction(() => document.querySelector('.trade-debt strong')?.textContent.replace(/\D/g, '') === '50000');
        await checkContrast(page.locator('#sale-quantity-0'), 'Sale quantity');
        await checkContrast(page.locator('#sale-price-0'), 'Sale price');
        await screenshot('sale-desktop');
        await page.setViewportSize({ width: 390, height: 844 });
        await screenshot('sale-mobile');
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'Sale overflows mobile viewport');
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.getByRole('button', { name: '✓ Sotuvni yakunlash', exact: true }).click();
        await page.getByRole('heading', { name: 'Sotuv saqlandi!', exact: true }).waitFor();
        await screenshot('sale-success');
        const search = page.getByPlaceholder('Ism, telefon, do‘kon nomi yoki manzil');
        await search.fill('Sinov Market');
        const result = page.locator('.trade-search-results button').filter({ hasText: 'Sinov Market' });
        await result.click();
        await page.locator('.trade-selected-customer').filter({ hasText: '50 000' }).waitFor();
        await checkContrast(search, 'Existing customer search');
        await page.goto(process.env.PREVIEW_URL + '/ombor');
        await page.locator('tbody tr').filter({ hasText: 'Fanta test' }).filter({ hasText: '140' }).waitFor();
        await screenshot('warehouse-after-sale');
        assert.equal(failures.length, 0, 'Browser errors: ' + failures.join('; '));
        await page.goto(process.env.PREVIEW_URL + '/kassa');
        await page.getByRole('button', { name: /Xarajat yozish/ }).click();
        await page.locator('#cash-expense-amount').fill('5000');
        await page.getByPlaceholder("Masalan: Gazel yoqilg'isi uchun").fill('Sinov transport xarajati');
        await page.getByRole('button', { name: 'Xarajatni Chiqim Qilish', exact: true }).dblclick();
        await page.getByRole('dialog').waitFor({state:'hidden'});
        await page.getByText('Xarajat saqlandi:', { exact: false }).waitFor();
        await page.locator('.app-cash-pill').filter({ hasText: '15 000' }).waitFor();
        await screenshot('cash-after-expense');
        await page.goto(process.env.PREVIEW_URL + '/dashboard');
        assert.match(await page.locator('[data-testid="dashboard-cash"]').textContent(), /15 000/);
        assert.match(await page.locator('[data-testid="dashboard-expenses"]').textContent(), /5 000/);
        await screenshot('dashboard-after-operations');
        await page.goto(process.env.PREVIEW_URL + '/qarzdorliklar');
        await page.getByRole('button', {name: /Yetkazuvchiga pul bermoqchiman/}).click();
        await page.getByRole('button', {name: 'Qarzni to‘lash', exact: true}).click();
        let dialog = page.getByRole('dialog');
        await dialog.locator('#supplierPaymentAmount-input').fill('20000');
        await dialog.getByRole('alert').filter({hasText: 'Kassada pul yetmaydi.'}).waitFor();
        await screenshot('supplier-shortage-visible');
        await dialog.getByRole('button', {name: 'Tasdiqlash va chiqim qilish', exact: true}).click();
        await dialog.getByRole('alert').filter({hasText: "Kassada yetarli mablag' mavjud emas"}).waitFor();
        await dialog.locator('#supplierPaymentAmount-input').fill('5000');
        await dialog.getByRole('button', {name: 'Tasdiqlash va chiqim qilish', exact: true}).dblclick();
        await dialog.waitFor({state:'hidden'});
        await page.locator('.app-cash-pill').filter({hasText:'10 000'}).waitFor();
        await page.getByText('745 000 so‘m', {exact:true}).waitFor();
        await screenshot('supplier-payment-closed');
        await page.getByRole('button', {name:/Mijoz qarzini to‘ladi/}).click();
        await page.getByRole('button', {name:'Qarz to‘lovini olish',exact:true}).click();
        dialog = page.getByRole('dialog');
        await dialog.locator('#paymentAmount-input').fill('10000');
        await dialog.getByRole('button', {name:'To‘lovni qabul qilish',exact:true}).dblclick();
        await dialog.waitFor({state:'hidden'});
        await page.locator('.app-cash-pill').filter({hasText:'20 000'}).waitFor();
        await page.getByText('40 000 so‘m', {exact:true}).waitFor();
        await screenshot('customer-payment-closed');
        const sections = ['/dashboard', '/savdo', '/qarzdorliklar', '/kassa', '/hisobotlar', '/admin', '/admin/opening-balances', '/catalog', '/mijozlar', '/yetkazuvchilar', '/yordam'];
        for (const route of sections) {
            await page.setViewportSize({ width: 390, height: 844 });
            const response = await page.goto(process.env.PREVIEW_URL + route);
            assert.equal(response.status(), 200, route + ': failed to open');
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), route + ': mobile overflow');
            await checkMobileSearch(route);
            const input = page.locator('main input:visible:not([type=checkbox]):not([type=radio])').first();
            if (await input.count()) await checkContrast(input, route + ' form');
            await screenshot('section-' + route.replaceAll('/', '-') + '-mobile');
        }
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto(process.env.PREVIEW_URL + '/yordam');
        assert.equal(await page.locator('.guide-tile').count(), 9);
        await screenshot('shop-map-desktop');
        assert.equal(failures.length, 0, 'Browser errors: ' + failures.join('; '));
        console.log('Preview transactions passed: received 150 units, sold 10 units, paid 20000, debt 50000, remaining stock 140.');
    } finally {

        await page.screenshot({ path: 'ui-evidence/last-page.png', fullPage: true });
        fs.writeFileSync('ui-evidence/browser-errors.json', JSON.stringify(failures, null, 2));
        fs.writeFileSync('ui-evidence/contrast.json', JSON.stringify(evidence, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
