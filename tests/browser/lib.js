// Helpers shared by every browser spec (generic and per target).
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..', '..');
const BASE = process.env.LAB_URL;
const TARGET = process.env.LAB_TARGET;

const target = () => JSON.parse(fs.readFileSync(path.join(ROOT, 'targets', TARGET, 'target.json'), 'utf8'));
const ids = () => JSON.parse(fs.readFileSync(path.join(process.env.STATE_DIR, 'ids.json'), 'utf8'));
const expand = (p) => { const i = ids(); return p.replace(/\{(\w+)\}/g, (m, k) => (i[k] !== undefined ? i[k] : m)); };
const pages = () => Object.entries(target().pages).map(([name, d]) => [name, { path: expand(d.path), status: d.status || 200 }]);

/** Put the site into a named state, with optional theme mods (without the target prefix). */
const setState = (name, mods = {}) =>
  execFileSync(process.env.LAB_BIN, ['state', TARGET, name, '--mods', JSON.stringify(mods)], { env: process.env, stdio: 'pipe' });

const adminUrl = (to) => {
  const salts = JSON.parse(fs.readFileSync(path.join(process.env.STATE_DIR, 'salts.json'), 'utf8'));
  return `${BASE}/?__lab_login=${salts.LAB_LOGIN_TOKEN}&__lab_to=${encodeURIComponent(to)}`;
};

/** Collect script errors and failed same-origin sub-resources. Returns the live array. */
const watchErrors = (page) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push('pageerror: ' + String(e).slice(0, 160)));
  page.on('console', (m) => {
    if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push('console: ' + m.text().slice(0, 160));
  });
  page.on('response', (r) => {
    if (r.url().startsWith(BASE) && r.status() >= 400 && r.request().resourceType() !== 'document') errors.push(`HTTP ${r.status()}: ${r.url().replace(BASE, '')}`);
  });
  return errors;
};

const rtlCookie = (ctx) => ctx.addCookies([{ name: 'lab_rtl', value: '1', url: BASE }]);
const overflowX = (page) => page.evaluate(() => Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - window.innerWidth);

module.exports = { ROOT, BASE, TARGET, target, ids, expand, pages, setState, adminUrl, watchErrors, rtlCookie, overflowX };
