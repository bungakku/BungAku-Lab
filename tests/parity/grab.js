#!/usr/bin/env node
/**
 * grab.js <outdir> <target> <state>
 * Fetches every page of the target (target.json "pages") over HTTP and stores the HTML.
 * State "rtl" sends the lab_rtl cookie (handled by the lab-rtl mu-plugin).
 */
const fs = require('fs');
const path = require('path');
const [outdir, target, state] = process.argv.slice(2);
const root = path.resolve(__dirname, '..', '..');
const t = JSON.parse(fs.readFileSync(path.join(root, 'targets', target, 'target.json'), 'utf8'));
const ids = JSON.parse(fs.readFileSync(path.join(process.env.STATE_DIR, 'ids.json'), 'utf8'));
const base = process.env.LAB_URL;
const rtl = !!(t.states[state] || {}).rtl;
const expand = (p) => p.replace(/\{(\w+)\}/g, (m, k) => (ids[k] !== undefined ? ids[k] : m));

(async () => {
  fs.mkdirSync(outdir, { recursive: true });
  const meta = {};
  for (const [name, def] of Object.entries(t.pages)) {
    const res = await fetch(base + expand(def.path), { redirect: 'manual', headers: rtl ? { cookie: 'lab_rtl=1' } : {} });
    fs.writeFileSync(path.join(outdir, name + '.html'), await res.text());
    meta[name] = res.status;
  }
  fs.writeFileSync(path.join(outdir, '_status.json'), JSON.stringify(meta));
})().catch((e) => { console.error('grab failed:', e.message); process.exit(1); });
