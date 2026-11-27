# lab doctor: prerequisites and versions. Exit 1 when something required is missing.
lab_doctor() {
  . "$LAB_ROOT/bin/lib/svc.sh"
  local fails=0 warns=0
  _ok()   { ok "$*"; }
  _warn() { warn "$*"; warns=$((warns+1)); }
  _fail() { printf '%sFAIL%s %s\n' "$_c_err" "$_c_off" "$*" >&2; fails=$((fails+1)); }

  for c in bash curl git tar unzip zip; do have "$c" && _ok "$c" || _fail "$c not found"; done

  if have php; then
    local pv; pv="$(php -r 'echo PHP_VERSION;')"
    if version_ge "$pv" "$PHP_MIN"; then
      version_ge "$pv" "$PHP_RECOMMENDED" && _ok "php $pv" || _warn "php $pv works, $PHP_RECOMMENDED or newer is recommended for WordPress 7"
    else _fail "php $pv is older than $PHP_MIN"; fi
    local m miss=""; for m in mysqli mbstring dom xml json openssl fileinfo; do php -m | grep -qix "$m" || miss="$miss $m"; done
    [ -z "$miss" ] && _ok "php extensions (required)" || _fail "php extensions missing:$miss"
    miss=""; for m in curl zip gd intl exif; do php -m | grep -qix "$m" || miss="$miss $m"; done
    [ -z "$miss" ] && _ok "php extensions (recommended)" || _warn "php extensions missing (some WordPress features will be off):$miss"
  else _fail "php not found"; fi

  if have node; then
    local nv; nv="$(node -p 'process.versions.node')"
    version_ge "$nv" "$NODE_MIN" && _ok "node $nv" || _fail "node $nv is older than $NODE_MIN (Playwright needs it)"
  else _fail "node not found"; fi

  if [ "$DB_MODE" = own ]; then
    local s i; s="$(svc_find mariadbd mysqld)" && _ok "database server: $s" || _fail "MariaDB server not found (mariadbd)"
    svc_find mariadb-install-db mysql_install_db >/dev/null && _ok "mariadb-install-db" || _fail "mariadb-install-db not found"
    svc_find mariadb mysql >/dev/null && _ok "mariadb client" || _warn "mariadb client not found ('lab db' will not work)"
    if is_root && ! id mysql >/dev/null 2>&1; then _warn "running as root and no 'mysql' user: the database will run as 'nobody'"; fi
  else
    php "$LAB_ROOT/php/db-init.php" ping >/dev/null 2>&1 && _ok "external database reachable ($DB_HOST:$DB_PORT)" || _warn "external database not reachable yet ($DB_HOST:$DB_PORT)"
  fi

  if [ -f "$LAB_ROOT/node_modules/@playwright/test/package.json" ]; then
    local pw; pw="$(node -p "require('$LAB_ROOT/node_modules/@playwright/test/package.json').version")"
    [ "$pw" = "$PLAYWRIGHT_VERSION" ] && _ok "@playwright/test $pw" || _warn "@playwright/test $pw, pinned $PLAYWRIGHT_VERSION (run: npm ci)"
    local b; for b in ${LAB_BROWSERS//,/ }; do
      node -e "const p=require('$LAB_ROOT/node_modules/@playwright/test'); const e=p['$b'].executablePath(); process.exit(require('fs').existsSync(e)?0:1)" 2>/dev/null \
        && _ok "browser $b installed" || _warn "browser $b missing (run: bin/lab install --browsers $b)"
    done
  else _warn "node modules not installed (run: bin/lab install)"; fi

  local mem; mem="$(awk '/MemAvailable/ {printf "%d", $2/1024}' /proc/meminfo 2>/dev/null)"
  [ -n "$mem" ] && { [ "$mem" -ge 1500 ] && _ok "memory available: ${mem} MB" || _warn "only ${mem} MB of memory available (lab needs about 1.5 GB with Chromium)"; }

  if http_ok "$LAB_URL/wp-login.php" 2; then _ok "$LAB_URL answers (lab is up)"
  elif http_ok "$LAB_URL/" 2; then _warn "port $LAB_PORT answers but it is not the lab's WordPress"
  else _ok "port $LAB_PORT is free"; fi

  [ -f "$WP_DIR/wp-includes/version.php" ] && _ok "WordPress core: $(sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$WP_DIR/wp-includes/version.php")" || _warn "WordPress core not installed (run: bin/lab install)"
  say "doctor: $fails failure(s), $warns warning(s)"
  [ "$fails" -eq 0 ]
}
