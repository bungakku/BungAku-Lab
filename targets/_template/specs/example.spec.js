// Put your target's browser specs in targets/<name>/specs/*.spec.js. Generic specs (pages, axe) already run for every target.
const { test, expect } = require('@playwright/test');
const { setState } = require('../../../tests/browser/lib');

test.afterEach(() => setState('ltr'));

test('example: the home page renders', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('body')).toBeVisible();
});
