# lab install: fetch WordPress core, npm packages, browsers; create database; install WordPress.
. "$LAB_ROOT/bin/lib/svc.sh"

core_version() { sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$1/wp-includes/version.php" 2>/dev/null; }

core_fetch() { # core_fetch VER SOURCE -> unpacked tree in $LAB_HOME/cache/core-tmp/wordpress
  local ver="$1" src="$2" tmp="$LAB_HOME/cache/core-tmp" how=""
  rm -rf "$tmp"; mkdir -p "$tmp"
  case "$src" in
    dir:*) info "copying core from ${src#dir:}" >&2; mkdir -p "$tmp/wordpress"; cp -a "${src#dir:}/." "$tmp/wordpress/"; how="dir" ;;
    zip|auto)
      local url="https://wordpress.org/wordpress-$ver.zip"; [ "$ver" = trunk ] && url="https://wordpress.org/nightly-builds/wordpress-latest.zip"
      info "downloading $url" >&2
      if curl -fsSL --connect-timeout 10 --max-time 240 "$url" -o "$tmp/wp.zip" && unzip -q "$tmp/wp.zip" -d "$tmp"; then how="zip"
      elif [ "$src" = zip ]; then die "could not download $url"
      else warn "zip download failed, falling back to git"; fi ;;
  esac
  if [ -z "$how" ] && { [ "$src" = git ] || [ "$src" = auto ]; }; then
    local br=(); [ "$ver" != trunk ] && br=(--branch "$ver")
    info "cloning WordPress/WordPress ${ver}" >&2
    git -c advice.detachedHead=false clone --quiet --depth 1 "${br[@]}" https://github.com/WordPress/WordPress.git "$tmp/wordpress" || die "git clone failed (check the version tag '$ver')"
    rm -rf "$tmp/wordpress/.git"; how="git"
  fi
  [ -f "$tmp/wordpress/wp-load.php" ] || die "fetched core looks wrong (no wp-load.php)"
  echo "$how"
}

lab_install() {
  local ver="$WP_VERSION" src="$WP_SOURCE" want_browsers=1 blist="$LAB_BROWSERS"
  while [ $# -gt 0 ]; do case "$1" in
    --wp) ver="$2"; shift 2 ;; --source) src="$2"; shift 2 ;;
    --no-browsers) want_browsers=0; shift ;; --browsers) blist="$2"; shift 2 ;;
    *) die "unknown option: $1" ;; esac; done
  mkstate

  have php && have node && have git && have curl && have unzip || die "missing tools; run: bin/lab doctor"

  # 1. WordPress core
  local have_v; have_v="$(core_version "$WP_DIR")"
  if [ -n "$have_v" ] && [ "${LAB_FORCE_CORE:-0}" != 1 ] && { [ "$ver" = trunk ] || [[ "$have_v" == "$ver"* ]]; }; then
    ok "WordPress $have_v already installed"
  else
    local how; how="$(core_fetch "$ver" "$src")" || exit 1
    svc_web_stop; svc_db_stop
    rm -rf "$WP_DIR"; mv "$LAB_HOME/cache/core-tmp/wordpress" "$WP_DIR"; rm -rf "$LAB_HOME/cache/core-tmp"
    printf '{"version":"%s","requested":"%s","source":"%s"}\n' "$(core_version "$WP_DIR")" "$ver" "$how" >"$STATE_DIR/core.json"
    ok "WordPress $(core_version "$WP_DIR") unpacked ($how)"
    # A new core directory has no theme/plugin links and the database is rebuilt: forget what was active.
    [ -n "$have_v" ] && { info "core changed: the database is rebuilt"; rm -rf "$DB_DIR"; }
    rm -f "$STATE_DIR/ids.json" "$STATE_DIR/current.json" "$STATE_DIR/state.json"
  fi

  # 2. Node packages and browsers
  if [ ! -d "$LAB_ROOT/node_modules/@playwright/test" ]; then
    info "npm ci"; (cd "$LAB_ROOT" && { [ -f package-lock.json ] && npm ci --no-audit --no-fund --loglevel=error || npm install --no-audit --no-fund --loglevel=error; }) || die "npm install failed"
  fi
  if [ "$want_browsers" = 1 ]; then
    info "browsers: $blist"
    (cd "$LAB_ROOT" && npx --no-install playwright install ${blist//,/ }) >"$LAB_HOME/cache/playwright-install.log" 2>&1 \
      && ok "browsers ready ($blist)" || { warn "browser install failed (see $LAB_HOME/cache/playwright-install.log); on Linux you may need: sudo npx playwright install-deps"; }
  fi

  # 3. Database and WordPress
  svc_db_start
  php "$LAB_ROOT/php/db-init.php" schema >/dev/null || die "database setup failed"
  svc_config
  php_lab install.php || die "WordPress install failed"
  ok "install finished. Next: bin/lab up && bin/lab check <target> <ref>"
}
