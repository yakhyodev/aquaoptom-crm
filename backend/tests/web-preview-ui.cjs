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
        await page.screenshot({ path: 'ui-evidence/' + name + '.png', fullPage: true });
    }
    try {
        await page.goto(process.env.PREVIEW_URL + '/login');
        await page.locator('#email').fill('admin');
        await page.locator('#password').fill(process.env.PREVIEW_OWNER_PASSWORD);
        await screenshot('login');
        await page.locator('button[type=submit]').click();
        await page.waitForURL('**/dashboard');
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
        await page.locator('#sale-quantity-0').fill('10');
        await waitTotal(70000);
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
        console.log('Preview transactions passed: received 150 units, sold 10 units, paid 20000, debt 50000, remaining stock 140.');
    } finally {

        await page.screenshot({ path: 'ui-evidence/last-page.png', fullPage: true });
        fs.writeFileSync('ui-evidence/browser-errors.json', JSON.stringify(failures, null, 2));
        fs.writeFileSync('ui-evidence/contrast.json', JSON.stringify(evidence, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
