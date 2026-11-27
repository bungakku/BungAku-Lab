#!/usr/bin/env node
/**
 * compare.js <rundir> <target> <baseLabel> <newLabel> [allow.json]
 * Reads <rundir>/<state>/{base,new}/<page>.html, normalises both sides and reports differences.
 * A difference passes only when every changed line matches a rule of the allow file.
 * Exit code 1 on any unexpected difference or status mismatch.
 */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const [rundir, target, baseLabel, newLabel, allowFile] = process.argv.slice(2);
const root = path.resolve(__dirname, '..', '..');
const t = JSON.parse(fs.readFileSync(path.join(root, 'targets', target, 'target.json'), 'utf8'));
const allow = allowFile ? JSON.parse(fs.readFileSync(allowFile, 'utf8')) : { rules: [] };
const rules = (allow.rules || []).map((r) => ({ ...r, re: new RegExp(r.match) }));
const norm = t.normalise || {};
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const ver = (l) => l.replace(/^v/, '');

const normalise = (html, label) => {
  const stripRe = (norm.strip_lines || []).map((s) => new RegExp(s));
  const v = new RegExp(esc(ver(label)), 'g');
  const extra = (norm.replace || []).map(([a, b]) => [new RegExp(a, 'g'), b]);
  return html.split('\n').filter((l) => !stripRe.some((r) => r.test(l))).map((l) => {
    l = l.replace(v, 'VER');
    for (const [a, b] of extra) l = l.replace(a, b);
    return l;
  }).join('\n');
};

const out = { same: 0, allowed: 0, unexpected: 0, total: 0, used: {}, problems: [] };
const states = Object.keys(t.states);
for (const st of states) {
  const sdir = path.join(rundir, st);
  const bs = JSON.parse(fs.readFileSync(path.join(sdir, 'base', '_status.json'), 'utf8'));
  const ns = JSON.parse(fs.readFileSync(path.join(sdir, 'new', '_status.json'), 'utf8'));
  for (const page of Object.keys(t.pages)) {
    out.total++;
    const want = t.pages[page].status || 200;
    if (bs[page] !== ns[page] || ns[page] !== want) {
      out.unexpected++;
      out.problems.push(`${st}/${page}: status base ${bs[page]}, new ${ns[page]}, expected ${want}`);
      continue;
    }
    const a = normalise(fs.readFileSync(path.join(sdir, 'base', page + '.html'), 'utf8'), baseLabel);
    const b = normalise(fs.readFileSync(path.join(sdir, 'new', page + '.html'), 'utf8'), newLabel);
    if (a === b) { out.same++; continue; }
    const fa = path.join(sdir, `${page}.base.norm`); const fb = path.join(sdir, `${page}.new.norm`);
    fs.writeFileSync(fa, a); fs.writeFileSync(fb, b);
    const d = spawnSync('diff', ['--unified=0', fa, fb], { encoding: 'utf8' });
    const changed = (d.stdout || '').split('\n').filter((l) => /^[+-]/.test(l) && !/^(\+\+\+|---)/.test(l));
    const bad = [];
    const used = new Set();
    for (const line of changed) {
      const body = line.slice(1);
      const rule = rules.find((r) => r.re.test(body) && (!r.pages || r.pages.includes('*') || r.pages.includes(page)) && (!r.states || r.states.includes('*') || r.states.includes(st)));
      if (rule) used.add(rule.id); else bad.push(line.length > 160 ? line.slice(0, 157) + '...' : line);
    }
    if (bad.length) {
      out.unexpected++;
      out.problems.push(`${st}/${page}: ${bad.length} unexplained changed line(s)\n      ` + bad.slice(0, 4).join('\n      '));
    } else {
      out.allowed++;
      used.forEach((id) => { out.used[id] = (out.used[id] || 0) + 1; });
    }
  }
}
console.log(`parity ${baseLabel} -> ${newLabel}: ${out.total} pages (${states.join(', ')}): identical ${out.same}, explained ${out.allowed}, unexplained ${out.unexpected}`);
for (const [id, n] of Object.entries(out.used)) console.log(`  rule ${id}: ${n} pages`);
for (const p of out.problems) console.log('  FAIL ' + p);
console.log(`RESULT parity pass=${out.same + out.allowed} fail=${out.unexpected} warn=0`);
process.exit(out.unexpected ? 1 : 0);
