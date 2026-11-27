// Customizer (Chromium only): new controls, live preview, contrast and "text not in title" warnings, logo switch, saving.
const { test, expect } = require('@playwright/test');
const { adminUrl, watchErrors, setState } = require('../../../tests/browser/lib');
const { execFileSync } = require('child_process');

test.skip(({ browserName }) => browserName !== 'chromium', 'Customizer test runs in Chromium only');
test.afterAll(() => setState('ltr'));

const PFX = 'bungaku_theme_';

test('title accent and logo controls behave in the Customizer', async ({ page }) => {
  setState('ltr');
  await page.setViewportSize({ width: 1400, height: 900 });
  const errors = watchErrors(page);
  await page.goto(adminUrl('/wp-admin/customize.php?theme=bungaku-theme'), { waitUntil: 'load' });

  const ready = () => page.waitForFunction(() => { const w = wp.customize.previewer.targetWindow(); return w && w.document.readyState === 'complete' && w.document.querySelector('#masthead'); }, null, { timeout: 60000 });
  const set = (id, v) => page.evaluate(([i, x]) => wp.customize(i).set(x), [id, v]);
  const prev = (fn, arg) => page.evaluate(fn, arg);
  // Notification text: .get(code).message is undefined on this object, so walk the collection.
  const note = (ctl, code) => page.evaluate(([c, k]) => {
    const ct = wp.customize.control(c); if (!ct.notifications.has(k)) return null;
    let msg = null; ct.notifications.each((n) => { if (n.code === k) msg = n.message || n.params && n.params.message || ''; });
    return String(msg || ct.container.find('[data-code="' + k + '"]').text() || 'present').trim().replace(/\s+/g, ' ');
  }, [ctl, code]);
  const refreshed = async () => { await page.waitForTimeout(1500); await ready(); await page.waitForTimeout(700); };
  await ready(); await page.waitForTimeout(2000);

  // 1. controls exist and the logo control explains the default
  expect(await prev(() => ['bungaku_theme_title_accent_text', 'bungaku_theme_title_accent_color', 'bungaku_theme_show_logo'].map((i) => !!wp.customize.control(i)))).toEqual([true, true, true]);
  expect(await prev(() => (wp.customize.control('custom_logo').params.description || '').length), 'logo control has a description').toBeGreaterThan(10);

  // 2. accent text wraps the matching part of the title in the preview
  await set(PFX + 'title_accent_text', 'Aku'); await refreshed();
  expect(await prev(() => { const e = wp.customize.previewer.targetWindow().document.querySelector('.site-title-accent'); return e && e.textContent; })).toBe('Aku');

  // 3. the colour updates live (postMessage, no reload)
  await set(PFX + 'title_accent_color', '#123456'); await page.waitForTimeout(500);
  const live = await prev(() => { const w = wp.customize.previewer.targetWindow(); return [w.document.documentElement.style.getPropertyValue('--bungaku-title-accent-color'), w.getComputedStyle(w.document.querySelector('.site-title-accent')).color]; });
  expect(live).toEqual(['#123456', 'rgb(18, 52, 86)']);

  // 4. contrast warnings
  const low = PFX + 'low_contrast', cc = PFX + 'title_accent_color';
  expect(await note(cc, low), '#123456 on white is fine').toBeNull();
  await set(cc, '#f9571b'); await page.waitForTimeout(400);
  expect(await note(cc, low), '#f9571b on white: 3.28:1, large text only').toContain('3.28');
  await set(PFX + 'header_bg', '#0d254c'); await set(cc, '#123456'); await page.waitForTimeout(400);
  expect(await note(cc, low), 'dark on dark').toContain('1.19');
  await set(PFX + 'header_bg', '#ffffff'); await set(cc, '#cc4a0a'); await page.waitForTimeout(400);
  expect(await note(cc, low), 'the default colour raises no warning').toBeNull();

  // 5. "text is not in the title" warning, including names with an apostrophe (stored HTML-escaped)
  const missing = PFX + 'accent_missing', at = PFX + 'title_accent_text';
  await set(at, 'xyz'); await page.waitForTimeout(400);
  expect(await note(at, missing), 'text absent from the title').not.toBeNull();
  await set('blogname', 'St Mary&#039;s Cathedral'); await set(at, "mary's"); await page.waitForTimeout(400);
  expect(await note(at, missing), 'apostrophe in the site name still matches').toBeNull();
  await set(at, ''); await page.waitForTimeout(400);
  expect(await note(at, missing), 'empty text: no warning').toBeNull();
  await set('blogname', 'BungAku Lab'); await set(at, 'Aku'); await page.waitForTimeout(400);
  expect(await note(at, missing), 'matching text: no warning').toBeNull();

  // 6. logo switch in the preview
  await set(PFX + 'show_logo', false); await refreshed();
  expect(await prev(() => !!wp.customize.previewer.targetWindow().document.querySelector('.custom-logo-link')), 'logo hidden').toBe(false);
  await set(PFX + 'show_logo', true); await refreshed();
  const logo = await prev(() => { const i = wp.customize.previewer.targetWindow().document.querySelector('.custom-logo'); return i && [i.getAttribute('src').split('/').pop(), i.getAttribute('width'), i.getAttribute('height')]; });
  expect(logo, 'bundled default logo is back').toEqual(['bungaku-logo-mark.png', '96', '68']);

  // 7. saving: an over-long accent text is cut to 60 characters in the database
  await set(at, 'Aku' + 'x'.repeat(80)); await set(cc, '#cc4a0a'); await set(PFX + 'show_logo', false);
  const saved = await page.evaluate(() => new Promise((res) => wp.customize.previewer.save().done(() => res('saved')).fail((e) => res('rejected ' + JSON.stringify(e).slice(0, 120)))));
  expect(saved).toBe('saved');
  const len = execFileSync(process.env.LAB_BIN, ['eval', 'echo mb_strlen( get_theme_mod( "bungaku_theme_title_accent_text" ) );'], { env: process.env }).toString().trim();
  expect(Number(len), 'saved accent text length').toBe(60);

  expect(errors).toEqual([]);
});
