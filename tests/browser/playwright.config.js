// Playwright runner for the lab. Started by `bin/lab test browser` (it exports LAB_* and STATE_DIR).
const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const target = process.env.LAB_TARGET || '';
const reports = process.env.LAB_REPORT_DIR || path.join(root, '.lab', 'reports');
const names = (process.env.LAB_BROWSERS || 'chromium').split(',').map((s) => s.trim()).filter(Boolean);
const device = { chromium: 'Desktop Chrome', firefox: 'Desktop Firefox', webkit: 'Desktop Safari' };

module.exports = defineConfig({
  testDir: root,
  // Generic specs run for every target; a target adds its own under targets/<name>/specs/.
  testMatch: ['tests/browser/generic/**/*.spec.js', `targets/${target}/specs/**/*.spec.js`],
  fullyParallel: false,
  workers: 1,               // one WordPress site, one database: tests change its state, so they run one at a time
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['json']],
  outputDir: path.join(reports, 'browser-artifacts'),
  use: {
    baseURL: process.env.LAB_URL || 'http://127.0.0.1:8080',
    screenshot: 'only-on-failure',
    trace: 'off',
  },
  projects: names.map((n) => ({ name: n, use: { ...devices[device[n]] } })),
});
