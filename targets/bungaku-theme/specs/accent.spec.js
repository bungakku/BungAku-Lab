// Site title accent: colour, size at both breakpoints, contrast of the default, switched off when empty.
const { test, expect } = require('@playwright/test');
const { setState } = require('../../../tests/browser/lib');

test.afterEach(() => setState('ltr'));

const accentStyle = (page) => page.evaluate(() => {
  const e = document.querySelector('.site-title-accent'); if (!e) return null;
  const s = getComputedStyle(e); return { color: s.color, size: s.fontSize, weight: s.fontWeight, text: e.textContent, bg: getComputedStyle(document.querySelector('#masthead')).backgroundColor };
});
const lum = (rgb) => { const c = rgb.match(/\d+/g).slice(0, 3).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };

test('default accent colour is #cc4a0a and passes 4.5:1 on the header background', async ({ page }) => {
  setState('ltr', { title_accent_text: 'Aku' });
  await page.setViewportSize({ width: 360, height: 800 });
  await page.goto('/', { waitUntil: 'load' });
  const a = await accentStyle(page);
  expect(a.text).toBe('Aku');
  expect(a.color).toBe('rgb(204, 74, 10)');
  expect(ratio(a.color, a.bg), 'contrast ratio').toBeGreaterThanOrEqual(4.5);
});

test('title is 20.8px up to 480px, 24px from 481px and 28.8px from 769px', async ({ page }) => {
  setState('ltr', { title_accent_text: 'Aku' });
  await page.goto('/', { waitUntil: 'load' });
  // Engines round 1.3rem differently (Chromium 20.8px, Firefox 20.8125px, WebKit 20.799999px), so compare numbers.
  for (const [w, size] of [[360, 20.8], [480, 20.8], [481, 24], [768, 24], [769, 28.8], [1280, 28.8]]) {
    await page.setViewportSize({ width: w, height: 800 });
    await page.waitForTimeout(150);
    expect(parseFloat((await accentStyle(page)).size), `font size at ${w}px`).toBeCloseTo(size, 1);
  }
});

test('a chosen colour is applied', async ({ page }) => {
  setState('ltr', { title_accent_text: 'Aku', title_accent_color: '#123456' });
  await page.goto('/', { waitUntil: 'load' });
  expect((await accentStyle(page)).color).toBe('rgb(18, 52, 86)');
});

test('empty accent text switches the feature off (no span)', async ({ page }) => {
  setState('ltr', { title_accent_text: '' });
  await page.goto('/', { waitUntil: 'load' });
  expect(await page.locator('.site-title-accent').count()).toBe(0);
});
