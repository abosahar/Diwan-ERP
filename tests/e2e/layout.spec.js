import { test, expect } from '@playwright/test';

/*
 * The page never scrolls sideways: wide content (tables, long names, amounts)
 * scrolls or truncates inside its own card. Checked at phone, tablet, laptop and
 * desktop widths, with the sidebar expanded and collapsed (kept in localStorage).
 */
const PAGES = [
    '/dashboard', '/sales/invoices', '/inventory/products',
    // Date fields in narrow cards and toolbars, a payroll button row, settings rows, document lines.
    '/hr/leaves', '/manufacturing/reports', '/hr/payroll', '/hr/settings', '/purchases/invoices/create', '/sales/invoices/create',
];
const WIDTHS = [375, 768, 1024, 1440];

const sidewaysOverflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

for (const collapsed of [false, true]) {
    test(`no sideways scrolling, sidebar ${collapsed ? 'collapsed' : 'expanded'}`, async ({ page }) => {
        await page.addInitScript((value) => localStorage.setItem('diwan.sidebar.collapsed', value), collapsed ? '1' : '0');
        for (const width of WIDTHS) {
            await page.setViewportSize({ width, height: 900 });
            for (const path of PAGES) {
                await page.goto(path, { waitUntil: 'networkidle' });
                expect.soft(await sidewaysOverflow(page), `${path} at ${width}px`).toBe(0);
            }
        }
    });
}

test('phones: list tables scroll inside their card', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    for (const path of ['/sales/invoices', '/inventory/products']) {
        await page.goto(path, { waitUntil: 'networkidle' });
        const scroller = page.locator('table').first().locator('xpath=..');
        const { visible, content } = await scroller.evaluate((el) => ({ visible: el.clientWidth, content: el.scrollWidth }));
        expect(content, `${path}: the table is wider than the phone`).toBeGreaterThan(visible);
        expect(visible, `${path}: the card fits the phone`).toBeLessThanOrEqual(375);

        // RTL: scrolling towards the end side is a negative scrollLeft.
        const moved = await scroller.evaluate((el) => { el.scrollLeft = -100; return el.scrollLeft; });
        expect(moved, `${path}: the table scrolls inside its card`).toBeLessThan(0);
        expect(await sidewaysOverflow(page), path).toBe(0);
    }
});

test('phones: invoice lines scroll in their card and the product list is not cut off', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    for (const [path, placeholder] of [['/purchases/invoices/create', 'اختر أو اكتب اسم منتج للإنشاء...'], ['/sales/invoices/create', 'اختر أو انشئ منتج...']]) {
        await page.goto(path, { waitUntil: 'networkidle' });
        const scroller = page.locator('table').first().locator('xpath=..');
        const moved = await scroller.evaluate((el) => { el.scrollLeft = -100; return el.scrollLeft; });
        expect(moved, `${path}: the lines scroll inside their card`).toBeLessThan(0);

        await page.getByPlaceholder(placeholder).first().click();
        const list = page.locator('[data-product-list]');
        await expect(list).toBeVisible();
        // A scroll box around the table would clip the list; its far edge must still be on top.
        const shown = await list.evaluate((el) => {
            const r = el.getBoundingClientRect();
            const x = r.left + r.width / 2;
            return [r.top + 4, r.bottom - 4].every((y) => el.contains(document.elementFromPoint(x, y)));
        });
        expect(shown, `${path}: the whole product list is visible`).toBe(true);
    }
});
