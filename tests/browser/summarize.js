#!/usr/bin/env node
/** summarize.js <playwright.json> <playwright.log> : print FAIL lines and a RESULT line. */
const fs = require('fs');
const [jsonFile, logFile] = process.argv.slice(2);
let pass = 0, fail = 0, skip = 0, warn = 0;
if (!fs.existsSync(jsonFile)) {
  console.log('  FAIL the browser run produced no report. Last log lines:');
  console.log(fs.existsSync(logFile) ? fs.readFileSync(logFile, 'utf8').split('\n').slice(-12).join('\n') : '(no log)');
  console.log('RESULT browser pass=0 fail=1 warn=0');
  process.exit(1);
}
const rep = JSON.parse(fs.readFileSync(jsonFile, 'utf8'));
const walk = (suite, trail) => {
  for (const spec of suite.specs || []) {
    for (const t of spec.tests || []) {
      const r = (t.results || [])[t.results.length - 1] || {};
      if (t.status === 'skipped' || r.status === 'skipped') { skip++; continue; }
      if (t.status === 'expected') {
        pass++;
        for (const a of t.annotations || []) if (a.type === 'advisory') { warn++; console.log(`  WARN [${t.projectName}] ${spec.title} :: ${a.description}`); }
        continue;
      }
      fail++;
      const lines = ((r.error && r.error.message) || 'failed').replace(/\u001b\[[0-9;]*m/g, '').split('\n').map((l) => l.trim()).filter(Boolean);
      // First line plus the Expected/Received pair, so a CI log alone shows what differed.
      const detail = lines.filter((l) => /^(Expected|Received)\b/.test(l)).join(' ');
      const msg = lines[0] + (detail ? ` | ${detail}` : '');
      console.log(`  FAIL [${t.projectName}] ${[...trail, spec.title].join(' > ')} :: ${msg}`);
    }
  }
  for (const s of suite.suites || []) walk(s, s.title && !/\.spec\.js$/.test(s.title) ? [...trail, s.title] : trail);
};
for (const s of rep.suites || []) walk(s, []);
console.log(`  ${pass + fail + skip} browser tests, ${skip} skipped`);
console.log(`RESULT browser pass=${pass} fail=${fail} warn=${warn}`);
process.exit(fail ? 1 : 0);
