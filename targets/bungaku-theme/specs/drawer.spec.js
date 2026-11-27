// Mobile menu drawer: open, focus, sub-menu, Escape, close button; the JS globals use the new prefix.
const { test, expect } = require('@playwright/test');
const { watchErrors, overflowX } = require('../../../tests/browser/lib');

test('mobile drawer works by keyboard and pointer at 375px', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  const errors = watchErrors(page);
  await page.goto('/', { waitUntil: 'load' });

  expect(await page.evaluate(() => typeof window.bungakuThemeVars), 'localized script object').toBe('object');
  expect(await page.evaluate(() => typeof window.mecThemeVars), 'old global must be gone').toBe('undefined');

  const toggle = page.locator('.menu-toggle');
  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.mobile-menu-panel')).toBeVisible();
  expect(await page.evaluate(() => !!document.activeElement.closest('.mobile-menu-panel')), 'focus moves into the panel').toBe(true);

  const parent = page.locator('.mobile-menu-panel li.menu-item-has-children > a, .main-navigation li.menu-item-has-children > a').first();
  await parent.click();
  await expect(parent, 'sub-menu opens').toHaveAttribute('aria-expanded', 'true');

  await page.keyboard.press('Escape');
  await expect(toggle, 'Escape closes the drawer').toHaveAttribute('aria-expanded', 'false');

  await toggle.click();
  await page.locator('.mobile-menu-close').click();
  await expect(toggle, 'close button closes the drawer').toHaveAttribute('aria-expanded', 'false');

  expect(await overflowX(page), 'horizontal overflow').toBeLessThanOrEqual(0);
  expect(errors).toEqual([]);
});
