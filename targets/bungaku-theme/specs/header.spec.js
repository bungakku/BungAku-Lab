// Header at every breakpoint, LTR and RTL: no overflow, logo and title level with each other, correct order; logo switch.
const { test, expect } = require('@playwright/test');
const { setState, rtlCookie, overflowX, watchErrors } = require('../../../tests/browser/lib');

const WIDTHS = [360, 412, 480, 481, 768, 769, 1280];
test.afterEach(() => setState('ltr'));

const measure = (page) => page.evaluate(() => {
  const r = (s) => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { l: b.left, t: b.top, w: b.width, h: b.height }; };
  return { logo: r('.custom-logo'), title: r('.site-title'), header: r('#masthead'), vw: window.innerWidth };
});

for (const dir of ['ltr', 'rtl']) {
  test(`header layout ${dir}, 360-1280px`, async ({ page, context }) => {
    setState('ltr');
    if (dir === 'rtl') await rtlCookie(context);
    const errors = watchErrors(page);
    await page.goto('/', { waitUntil: 'load' });
    for (const w of WIDTHS) {
      await page.setViewportSize({ width: w, height: 900 });
      await page.waitForTimeout(200);
      const m = await measure(page);
      const at = `at ${w}px`;
      expect(await overflowX(page), `overflow ${at}`).toBeLessThanOrEqual(0);
      expect(m.logo, `default logo present ${at}`).not.toBeNull();
      expect([Math.round(m.logo.w), Math.round(m.logo.h)], `logo size ${at}`).toEqual([96, 68]);
      expect(m.header.l, `header starts at the edge ${at}`).toBeGreaterThanOrEqual(0);
      expect(m.header.l + m.header.w, `header inside viewport ${at}`).toBeLessThanOrEqual(m.vw + 1);
      // logo and title are level: centres within 4px (measured 2.5-3.5px on v1.8.0)
      const dy = Math.abs(m.logo.t + m.logo.h / 2 - (m.title.t + m.title.h / 2));
      expect(dy, `logo/title vertical centres ${at}`).toBeLessThanOrEqual(4);
      // reading order: title follows the logo in LTR and precedes it in RTL
      if (dir === 'ltr') expect(m.title.l, `title after logo ${at}`).toBeGreaterThanOrEqual(m.logo.l + m.logo.w - 1);
      else expect(m.title.l + m.title.w, `title before logo ${at}`).toBeLessThanOrEqual(m.logo.l + 1);
    }
    expect(errors).toEqual([]);
  });
}

test('"Show a logo in the header" off removes the logo and shortens the header', async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 900 });
  setState('ltr');
  await page.goto('/', { waitUntil: 'load' });
  const on = await measure(page);
  setState('ltr', { show_logo: false });
  await page.goto('/', { waitUntil: 'load' });
  const off = await measure(page);
  expect(await page.locator('.custom-logo-link').count(), 'logo link when switched off').toBe(0);
  expect(off.header.h, 'header height when the logo is off').toBeLessThan(on.header.h);
});

test('an uploaded logo replaces the bundled default', async ({ page }) => {
  const ids = require('../../../tests/browser/lib').ids();
  setState('ltr', { custom_logo: ids.logo });
  await page.goto('/', { waitUntil: 'load' });
  const src = await page.locator('.custom-logo').getAttribute('src');
  expect(src, 'logo source').toContain('lab-test-logo');
  expect(src).not.toContain('bungaku-logo-mark');
});
