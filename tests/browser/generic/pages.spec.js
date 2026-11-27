// Every page of the target, LTR and RTL: status, no script errors, no PHP messages, no horizontal scroll at three widths.
const { test, expect } = require('@playwright/test');
const { pages, watchErrors, rtlCookie, overflowX } = require('../lib');

const WIDTHS = [360, 768, 1280];

for (const dir of ['ltr', 'rtl']) {
  test.describe(`pages ${dir}`, () => {
    for (const [name, def] of pages()) {
      test(`${name}`, async ({ page, context }) => {
        if (dir === 'rtl') await rtlCookie(context);
        const errors = watchErrors(page);
        await page.setViewportSize({ width: WIDTHS[0], height: 800 });
        const res = await page.goto(def.path, { waitUntil: 'load' });
        expect(res.status(), 'HTTP status').toBe(def.status);
        const html = await page.content();
        expect(html, 'PHP message in the HTML').not.toMatch(/(Warning|Notice|Fatal error|Deprecated|Parse error):\s.*\bon line \d+/);
        expect(await page.title(), 'document title').not.toBe('');
        expect(await page.locator('html').getAttribute('lang'), 'html lang').toBeTruthy();
        // WordPress prints dir only for RTL, so check the computed direction instead of the attribute.
        expect(await page.evaluate(() => getComputedStyle(document.documentElement).direction), 'text direction').toBe(dir);
        for (const w of WIDTHS) {
          await page.setViewportSize({ width: w, height: 800 });
          await page.waitForTimeout(150);
          expect(await overflowX(page), `horizontal overflow at ${w}px`).toBeLessThanOrEqual(0);
        }
        expect(errors, 'script errors / failed resources').toEqual([]);
      });
    }
  });
}
