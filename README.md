# BungAku-Lab

A small, reusable test lab for WordPress themes and plugins: **PHP + MariaDB + WordPress core + headless browsers (Playwright)**, driven by one command, `bin/lab`.

It answers one question before every release: *does the package that will be published still work, and did anything change that I did not mean to change?*

```
bin/lab check bungaku-theme v1.8.0 --base v1.7.99
```

builds the package from the git tag, installs it into a real WordPress, and runs four suites:

| Suite | What it does | Needs |
| --- | --- | --- |
| **static** | The built zip itself: one root folder, no repo-only files, version equal in every place, `php -l` / `node --check`, forbidden strings absent, text domain on every translation call, required headers | PHP, Node |
| **php** | Inside WordPress: the build is active and loads, every Customizer callback is callable, the settings inventory equals the committed snapshot, target checks (for BungAku-Theme: title-accent cases, GitHub updater), `debug.log` stays clean | WordPress + database |
| **browser** | Playwright: every page in LTR and RTL at 360 / 768 / 1280 px (status, script errors, PHP messages, horizontal overflow), axe accessibility, plus the target's own specs (header, mobile drawer, accent colour, Customizer) | a browser |
| **parity** | The same pages served by the previous and the new build in three states (front page = posts, static page, RTL), normalised and diffed. Every changed line must be explained by a rule in an allow file | two builds |

Everything that fails prints one line saying what and where; the full logs are kept under `.lab/reports/`.

---

## 1. Lanes: where to run it

Pick by what your machine can do. They all run the same suites.

| Lane | How | Status in this repo |
| --- | --- | --- |
| **B. Linux native** (also WSL2 on Windows 11) | `bin/lab install` starts a private MariaDB (socket only, no TCP port) and PHP's built-in server | **Verified** on Ubuntu 24.04, PHP 8.3.6, MariaDB 10.11.14, Node 22, Playwright 1.63.0 with Chromium 158, WordPress 7.1.5 and 7.0.2 (BungAku-Theme v1.8.0 passes every suite on both) |
| **A. GitHub Actions** | `.github/workflows/lab.yml`: PHP matrix, real MariaDB service, Chromium + Firefox + WebKit, reports as artifacts | **Verified** (run 4 min 43 s, 6 jobs in parallel): PHP 7.4, 8.1, 8.3, 8.4 on WordPress 7.1.5 and PHP 8.3 on WordPress trunk (7.3-alpha), each with Chromium, Firefox and WebKit, plus a floor job, WordPress 5.0 on PHP 7.3 with Chromium only. BungAku-Theme v1.8.0 vs v1.7.99 passes every suite on all six (static 22, php 29, parity 30, browser 112 or 38 on the Chromium-only floor job) |
| **Docker** | `docker-compose.yml`: a `db` service (MariaDB) and a `lab` service (PHP + Node + Playwright image) | ⚠ **Not run.** `docker compose config` accepts the file; no Docker daemon was available to build or start it |

Not included: a WordPress Playground lane. Playground runs PHP as WebAssembly on SQLite, which differs from production (MariaDB), so it would not test what you ship.

Not verified yet: WordPress older than 7.0, Windows, the Docker lane, and real Safari (WebKit is the engine, not the browser). Run the workflow by hand: Actions > lab > Run workflow, give the tag and the previous tag.

**Windows 10 note.** Playwright's supported systems are Windows 11, Windows Server 2019+ and WSL. On Windows 10, or on a machine with about 4 GB of RAM, use Lane A (GitHub Actions) or a Linux install. Docker Desktop on Windows needs about 8 GB for its virtual machine.

---

## 2. Requirements (Lane B)

| Tool | Version | Check |
| --- | --- | --- |
| bash, curl, git, tar, unzip, zip, diff | any recent | `bin/lab doctor` |
| PHP CLI | 7.4 or newer, **8.3 recommended**, with `mysqli mbstring dom xml json openssl fileinfo` (recommended: `curl zip gd intl exif`) | `bin/lab doctor` |
| MariaDB server | 10.11, 11.4 or 11.8 (the versions WordPress recommends) | `bin/lab doctor` |
| Node.js | 20 or newer (Playwright 1.63 requires it) | `bin/lab doctor` |
| Memory | about 1.5 GB free with Chromium | `bin/lab doctor` |

Ubuntu / Debian:

```bash
sudo apt install php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd php-intl \
                 mariadb-server git unzip zip curl
# Node 20+ from your usual source (nvm, NodeSource, ...)
```

The system MariaDB service does not need to run; the lab starts its own private copy inside `.lab/`.

Browser system libraries (Linux only, once): `sudo npx playwright install-deps`.

---

## 3. Quick start

```bash
git clone https://github.com/bungakku/BungAku-Lab.git && cd BungAku-Lab
bin/lab doctor                      # prerequisites
bin/lab install                     # WordPress core, npm packages, Chromium, database, WordPress install
bin/lab check bungaku-theme latest  # build the newest tag, seed, run all suites
```

Before a release:

```bash
bin/lab check bungaku-theme v1.8.1 --base v1.8.0
```

`check` accepts a tag (built from the target's GitHub repo) or `latest`. To test work that is not tagged yet:

```bash
bin/lab build bungaku-theme --src /path/to/BungAku-Theme --label v1.8.1-rc
bin/lab test all bungaku-theme --label v1.8.1-rc
```

After you publish, prove the published zip is the zip you tested:

```bash
bin/lab verify bungaku-theme v1.8.1
```

Measured in the sandbox on a fresh directory (browsers already present, GitHub reachable): install about 10 s, full `check` with parity about 80 s.

---

## 4. Commands

| Command | What it does |
| --- | --- |
| `doctor` | Checks prerequisites and versions; exits 1 when something required is missing |
| `install [--wp VER] [--source auto\|zip\|git\|dir:PATH] [--no-browsers] [--browsers LIST]` | Fetches WordPress core into `.lab/wp`, runs `npm ci`, installs browsers, creates the database, installs WordPress. Idempotent. A different `--wp` version replaces core and rebuilds the database |
| `up` / `down` / `status` / `url` | Start or stop the database and web server; show versions and state; print the site URL |
| `reset` | Fresh database and WordPress install; core and builds stay; the current build is re-activated |
| `clean [--all]` | Delete reports, builds and cache; `--all` removes the whole `.lab/` directory |
| `build <target> [REF\|latest] [--src DIR] [--zip FILE] [--label NAME]` | Builds `.lab/dist/<zip>` and an unpacked tree. From a git tag, a local directory, or an existing zip (checked as it is) |
| `verify <target> TAG` | Downloads the published GitHub release asset and compares its content with the lab build |
| `use <target> LABEL` | Links a build into `wp-content/` and activates it |
| `seed [--force]` | Demo content: 12 posts with fixed distinct dates, 3 categories, tags, 2 pages, a main and a footer menu, one media file |
| `state <target> NAME [--mods JSON]` | Puts the site into a named, repeatable state (target setup, front page, optional theme mods) |
| `test <suite\|all> <target> [options]` | Runs suites. Options: `--label`, `--base`, `--allow FILE\|none`, `--mods-base JSON`, `--mods-new JSON`, `--browsers chromium,firefox,webkit`, `--grep TEXT`, `--update`, `--verbose` |
| `check <target> REF [--base REF2] [--allow FILE] [--rebuild] [--verify]` | Build, activate, seed and run everything (parity when `--base` is given) |
| `selftest <target> LABEL` | Plants two faults in a copy of a good build and proves the static and parity suites catch them |
| `eval '<php>'` / `eval FILE` | Runs PHP inside WordPress |
| `db [SQL]` | MariaDB shell or a single query (own-database mode) |

Exit code 0 = every suite passed, 1 = something failed. Advisory findings (WARN) never fail a run.

---

## 5. Reading the results

```
suite     result   pass  fail  warn
static    PASS       22     0     2
php       PASS       29     0     0
browser   PASS       38     0     6
parity    PASS       30     0     0
reports: .lab/reports/run-20261126-235215-all
```

* **FAIL** lines name the check and the evidence. Re-run one suite with `--verbose` to see every check, or open `.lab/reports/<run>/<suite>.log`.
* **WARN** is information, not a failure. Examples on BungAku-Theme v1.8.0: style.css has no `Requires at least` / `Requires PHP` header; axe reports two moderate whole-page findings (heading order in the Recent Posts widget, a duplicate search landmark at 360 px). Fix them when you choose to.
* A failing browser test leaves a screenshot in `.lab/reports/<run>/browser-artifacts/`.
* `debug.log`: after the php and browser suites the lab fails the run if WordPress logged any PHP warning, notice, deprecation or fatal error. A copy is kept as `debug-<suite>.log`.

### Parity and allow files

Parity is how a release proves it changed only what it meant to. Both builds are served in the same WordPress, under the same slug, in three states; version strings and build fingerprints are normalised; then every changed line must match a rule:

```json
{ "rules": [ { "id": "bundled-logo-link-added", "match": "class=\"custom-logo-link\".*bungaku-logo-mark\\.png" } ] }
```

Save it as `expected/<target>/parity-allow-<label>.json` and `check` picks it up automatically. Rules may be limited with `"pages": [...]` and `"states": [...]`. The output shows how many pages each rule explained: a rule that explains nothing is stale. `--allow none` compares strictly.

Example from BungAku-Theme v1.8.0: against v1.7.99, default settings give 30 pages explained by two rules (the new default logo link and the reflowed line around it); with *Show a logo in the header* switched off (`--mods-new '{"show_logo": false}' --allow none`) all 30 pages are identical.

### Settings snapshot

For themes with a Customizer the php suite compares every registered setting and control (id, default, transport, sanitize callback, section) with `expected/<target>/inventory.json`. When a release changes settings on purpose:

```bash
bin/lab test php bungaku-theme --update     # rewrite the snapshot
git diff expected/                          # review it, then commit it with the release
```

---

## 6. Configuration

Order of precedence: environment variable, then `lab.env` (not committed, see `lab.env.example`), then `versions.env`.

| Variable | Default | Meaning |
| --- | --- | --- |
| `WP_VERSION` | `7.1.5` | WordPress under test: a tag, a release candidate tag (`7.2-RC2`), or `trunk` |
| `WP_SOURCE` | `auto` | `auto` = wordpress.org zip, then the GitHub mirror; `zip`; `git`; `dir:/path` |
| `LAB_HOME` | `./.lab` | Where everything generated lives |
| `LAB_HOST`, `LAB_PORT` | `127.0.0.1`, `8080` | Site address (one lab per port: use another `LAB_HOME` and `LAB_PORT` to run two) |
| `LAB_BROWSERS` | `chromium` | `chromium`, `firefox`, `webkit`, comma separated |
| `DB_MODE` | `own` | `own` = private MariaDB over a socket; `external` = an existing server (`DB_HOST`, `DB_PORT`, `DB_ROOT_USER`, `DB_ROOT_PASS` to create the database and user) |
| `LAB_ALLOW_HTTP` | unset | Set to `1` to let WordPress make outgoing requests (they are blocked by default) |

Matrix examples:

```bash
WP_VERSION=7.0.2 bin/lab install && bin/lab check bungaku-theme latest   # another core
bin/lab test browser bungaku-theme --browsers chromium,firefox,webkit    # more browsers
bin/lab install --browsers firefox,webkit                                 # first install them
```

Pinned versions live only in `versions.env` and `package.json` (`@playwright/test` and `axe-core` are exact versions, with a lock file).

---

## 7. What the lab changes about WordPress

It is a throw-away test site, configured for repeatable results:

* `wp-config.php` is generated by `bin/lab up`; `WP_DEBUG` is on, errors go to `.lab/reports/debug.log`, never to the page.
* Cron, automatic updates and outgoing HTTP requests are off. `wp_mail()` reports success without sending (mu-plugin `lab-mail`).
* The site listens on loopback only (Docker: published on localhost). An mu-plugin (`lab-autologin`) lets the tests log in as admin with a secret token, only from 127.0.0.1 / ::1. Never expose this site to a network.
* RTL is forced per request with the cookie `lab_rtl=1` (mu-plugin `lab-rtl`); no language pack is needed.
* The web server runs with `realpath_cache_size=0` and opcache off because `lab use` swaps the theme symlink at run time.

---

## 8. Repository layout

```
bin/lab, bin/lib/*.sh      the command line (bash)
php/                       helpers that load WordPress: install, seed, use, state, config, router
mu-plugins/                autologin, rtl, mail (linked into the test site)
seed/                      demo content and the test logo
tests/static  tests/php  tests/parity  tests/browser    the suites; browser/lib.js is shared by specs
targets/<name>/            one folder per project under test (target.json, setup.php, checks/, specs/)
targets/_template/         start here for a new project; targets/README.md explains it
expected/<name>/           committed snapshots: settings inventory, parity allow files
docker/, docker-compose.yml, .github/workflows/lab.yml   the other two lanes
.lab/                      generated, ignored: wp, db, dist (builds), reports, run, state
```

### Adding another project

See `targets/README.md`. In short: copy `targets/_template`, fill in `target.json`, build from a local checkout, make `static` green, write `setup.php` and the checks and specs that are specific to the project. Plugin targets (`"type": "plugin"`) are supported: the build is linked into `wp-content/plugins/` and activated. A plugin target was smoke-tested once with a three-file plugin (static, php and browser suites ran); no real plugin has been run through the lab yet.

---

## 9. Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| `port 8080 is already in use` | Another program owns it. Set `LAB_PORT` (and `LAB_HOME` if a second lab runs) |
| `database did not start` | Read `.lab/run/mariadb.log`. Running as root needs a `mysql` user (the MariaDB package creates it). A stale socket: `bin/lab down`, then `bin/lab up` |
| `mariadb-install-db not found` | Install the MariaDB *server* package, or use `DB_MODE=external` |
| `zip download failed, falling back to git` | wordpress.org was unreachable; the GitHub mirror is used instead. Expected behind restrictive proxies |
| Browser fails to launch (Linux) | `sudo npx playwright install-deps`, then `bin/lab doctor` |
| `browser firefox missing` | `bin/lab install --browsers firefox` (needs access to the Playwright download host) |
| A theme change does not show up | The theme is a symlink to a build in `.lab/dist/tree/<label>`; rebuild (`check --rebuild`) or `bin/lab build ... --label NAME` again, then `bin/lab use` |
| Menus or widgets missing after `use` | Run `bin/lab state <target> ltr`; `use` only activates, `state` applies the target setup |
| Results differ between runs | Seeded posts need distinct dates (the lab sets them); do not edit content in the running site, run `bin/lab reset` instead |
| `FAIL debug.log has N PHP warning...` | Real PHP messages from WordPress or the target; the offending lines are printed and saved in the run folder |
| Windows: `bad interpreter` / `\r` errors | Line endings were converted; `.gitattributes` forces LF, run `git add --renormalize .` |
| Out of memory | Close other programs; the lab itself uses a 64 MB InnoDB buffer, Chromium is the large part |

Everything the lab writes is under `LAB_HOME`; `bin/lab clean --all` returns the machine to its previous state.

---

## 10. Maintenance

* **New WordPress release.** Change `WP_VERSION` in `versions.env`, run `bin/lab install` and `bin/lab check <target> latest`. Compare with the previous version before committing the change.
* **New Playwright release.** Change the version in `package.json`, `versions.env`, `docker/Dockerfile` default and `docker-compose.yml` together, `npm install`, run `bin/lab doctor` and a full check. Browser versions follow Playwright.
* **Release routine for a target.** `bin/lab check <target> <tag> --base <previous>`; add or adjust `expected/<target>/parity-allow-<tag>.json` for intended changes; `test php --update` when settings changed; publish; `bin/lab verify <target> <tag>`.
* **Keep the lab honest.** Run `bin/lab selftest <target> <label>` after changing a suite: it must still catch the planted faults.
* **Targets age.** When a theme or plugin gains a feature, add a check or spec for it in the same release; when it renames something, update `forbidden_content` and the parity normalisation.

---

## 11. Limits

* One WordPress site per `LAB_HOME`; tests run one at a time because they change the site's state.
* Chromium is the only browser that has run. WebKit is a stand-in for Safari, not Safari itself; real devices and screen readers are outside the lab.
* The media-library upload flow of the Customizer logo control is not driven by a test; the logo is set through the database.
* Plugin targets are supported but only smoke-tested.
* PHP helpers are written to run on PHP 7.4 and newer but were executed only on 8.3.

License: GPL-2.0-or-later (see `LICENSE`).
