const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const evidence = [];
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
    try {
        await page.goto(process.env.PREVIEW_URL + '/login');
        await page.locator('#email').fill('owner@preview.aquaoptom.test');
        await page.locator('#password').fill(process.env.PREVIEW_OWNER_PASSWORD);
        await page.locator('button[type=submit]').click();
        await page.waitForURL('**/dashboard');
        await page.goto(process.env.PREVIEW_URL + '/ombor');
        const search = page.getByPlaceholder('Masalan: Fanta, PRD-0001, 1L, FANTA-1000...');
        await search.fill('Cola');
        await page.getByPlaceholder('Min', { exact: true }).fill('56');
        await checkContrast(search, 'Warehouse search');
        await checkContrast(page.getByPlaceholder('Min', { exact: true }), 'Warehouse quantity');
        for (const select of await page.locator('main select').all()) await checkContrast(select, 'Warehouse dropdown');
        await page.screenshot({ path: 'ui-evidence/warehouse.png', fullPage: true });
        await page.getByRole('button', { name: '+ Kirim qilish', exact: true }).click();
        await page.getByRole('button', { name: 'Yangi Mahsulot' }).click();
        await page.getByPlaceholder('Masalan: Fanta, Dinay...').fill('Fanta');
        await page.locator('select').filter({ has: page.locator('option[value="custom"]') }).selectOption('custom');
        const volume = page.getByPlaceholder('Masalan: 0.75 L yoki 750 ml');
        await volume.fill('0.75 L');
        await checkContrast(volume, 'Custom volume');
        await page.screenshot({ path: 'ui-evidence/custom-volume.png', fullPage: true });
        await page.getByRole('button', { name: 'Bekor qilish', exact: true }).click();
        await page.goto(process.env.PREVIEW_URL + '/sotuv');
        const customerSearch = page.getByPlaceholder('Ism, telefon, do‘kon nomi yoki manzil');
        await customerSearch.fill('No matching customer');
        await page.getByText('Mijoz topilmadi.', { exact: false }).waitFor();
        await checkContrast(customerSearch, 'Existing customer search');
        await page.getByRole('button', { name: '+ Yangi Mijoz', exact: true }).click();
        const customerName = page.getByPlaceholder('Masalan: Dilshod aka');
        await customerName.fill('Dilshod aka');
        await page.getByPlaceholder('+998901234567', { exact: true }).fill('+998901234567');
        await page.getByPlaceholder("Do'kon", { exact: true }).fill('Bahor Market');
        await page.getByPlaceholder('Manzil', { exact: true }).fill('Toshkent');
        await checkContrast(customerName, 'New customer form');
        await page.screenshot({ path: 'ui-evidence/new-customer.png', fullPage: true });
        await page.getByRole('button', { name: 'Bekor qilish', exact: true }).click();
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(process.env.PREVIEW_URL + '/ombor');
        await page.getByRole('button', { name: '+ Kirim qilish', exact: true }).waitFor();
        await page.screenshot({ path: 'ui-evidence/mobile-warehouse.png', fullPage: true });
        console.log('Browser UI checks passed; no business data created.');
    } finally {
        fs.writeFileSync('ui-evidence/contrast.json', JSON.stringify(evidence, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
