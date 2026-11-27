// axe-core on the pages and widths named in target.json "axe".
//  - scope (a CSS selector): every violation fails, whatever its impact.
//  - the whole page: only the impacts in fail_impacts fail (default serious, critical); the rest are reported as advisories.
const fs = require('fs');
const { test, expect } = require('@playwright/test');
const { target, pages } = require('../lib');

const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const cfg = Object.assign({ widths: [360, 1280], pages: ['home'], fail_impacts: ['serious', 'critical'] }, target().axe || {});
const byName = Object.fromEntries(pages());

const run = async (page, path, w, scope) => {
  await page.setViewportSize({ width: w, height: 900 });
  await page.goto(path, { waitUntil: 'load' });
  await page.addScriptTag({ content: axeSource });
  return page.evaluate(async (sc) => {
    const r = await window.axe.run(sc || document);
    return { v: r.violations.map((v) => ({ impact: v.impact, text: `${v.id} (${v.impact}) x${v.nodes.length}: ${v.nodes[0].target.join(' ').slice(0, 80)}` })), passes: r.passes.length };
  }, scope);
};

for (const name of cfg.pages) {
  for (const w of cfg.widths) {
    if (cfg.scope) {
      test(`axe ${name} ${w}px in ${cfg.scope}: no violations`, async ({ page }) => {
        const res = await run(page, byName[name].path, w, cfg.scope);
        expect(res.v.map((x) => x.text), 'accessibility violations').toEqual([]);
        expect(res.passes, 'axe actually ran rules').toBeGreaterThan(5);
      });
    }
    test(`axe ${name} ${w}px whole page: no ${cfg.fail_impacts.join('/')} violations`, async ({ page }, info) => {
      const res = await run(page, byName[name].path, w, null);
      expect(res.v.filter((x) => cfg.fail_impacts.includes(x.impact)).map((x) => x.text), 'blocking violations').toEqual([]);
      for (const x of res.v.filter((y) => !cfg.fail_impacts.includes(y.impact))) info.annotations.push({ type: 'advisory', description: x.text });
    });
  }
}
