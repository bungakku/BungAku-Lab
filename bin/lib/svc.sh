#!/usr/bin/env bash
# Service helpers: the private MariaDB (own mode), the PHP web server, wp-config.

svc_find() { # svc_find name...  -> first executable found on PATH or in the sbin dirs
  local n d
  for n in "$@"; do
    have "$n" && { command -v "$n"; return 0; }
    for d in /usr/sbin /usr/local/sbin /usr/local/mysql/bin /usr/local/bin; do [ -x "$d/$n" ] && { echo "$d/$n"; return 0; }; done
  done
  return 1
}

svc_db_user_flag() { # as root, mariadbd must drop to an unprivileged user
  if is_root; then id mysql >/dev/null 2>&1 && echo "--user=mysql" || echo "--user=nobody"; fi
}

svc_db_pid()   { [ -f "$RUN_DIR/mysqld.pid" ] && cat "$RUN_DIR/mysqld.pid" 2>/dev/null; }
svc_db_alive() { local p; p="$(svc_db_pid)"; [ -n "${p:-}" ] && kill -0 "$p" 2>/dev/null; }

svc_db_init() { # create the data directory once
  [ "$DB_MODE" = own ] || return 0
  [ -d "$DB_DIR/mysql" ] && return 0
  local inst; inst="$(svc_find mariadb-install-db mysql_install_db)" || die "mariadb-install-db not found (install the MariaDB server package)"
  info "initialising database directory"
  mkdir -p "$DB_DIR" "$RUN_DIR"
  local uflag; uflag="$(svc_db_user_flag)"
  [ -n "$uflag" ] && chown -R "${uflag#--user=}" "$DB_DIR" "$RUN_DIR" 2>/dev/null
  "$inst" --no-defaults --datadir="$DB_DIR" --auth-root-authentication-method=normal --skip-test-db ${uflag:+"$uflag"} \
    >"$RUN_DIR/install-db.log" 2>&1 || { tail -5 "$RUN_DIR/install-db.log" >&2; die "database initialisation failed"; }
}

svc_db_start() {
  [ "$DB_MODE" = own ] || return 0
  svc_db_alive && return 0
  svc_db_init
  local bin; bin="$(svc_find mariadbd mysqld)" || die "mariadbd not found (install the MariaDB server package)"
  local uflag; uflag="$(svc_db_user_flag)"
  [ -n "$uflag" ] && chown -R "${uflag#--user=}" "$DB_DIR" "$RUN_DIR" 2>/dev/null
  rm -f "$DB_SOCKET"
  # Small and private: socket only, no TCP, modest buffers (fits a 4 GB laptop).
  nohup setsid "$bin" --no-defaults ${uflag:+"$uflag"} --datadir="$DB_DIR" --socket="$DB_SOCKET" \
    --pid-file="$RUN_DIR/mysqld.pid" --skip-networking --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_520_ci --innodb-buffer-pool-size=64M --max-connections=40 \
    --performance-schema=OFF --skip-name-resolve --log-error="$RUN_DIR/mariadb.log" >/dev/null 2>&1 &
  local i; for i in $(seq 1 60); do [ -S "$DB_SOCKET" ] && php_db_ping && { ok "database up (MariaDB, socket)"; return 0; }; sleep 0.5; done
  tail -5 "$RUN_DIR/mariadb.log" >&2 2>/dev/null; die "database did not start"
}

svc_db_stop() {
  [ "$DB_MODE" = own ] || return 0
  svc_db_alive || return 0
  local p; p="$(svc_db_pid)"; kill "$p" 2>/dev/null
  local i; for i in $(seq 1 40); do kill -0 "$p" 2>/dev/null || break; sleep 0.25; done
  kill -0 "$p" 2>/dev/null && kill -9 "$p" 2>/dev/null
  ok "database stopped"
}

php_db_ping() { php "$LAB_ROOT/php/db-init.php" ping >/dev/null 2>&1; }

svc_web_pid()   { [ -f "$RUN_DIR/web.pid" ] && cat "$RUN_DIR/web.pid" 2>/dev/null; }
svc_web_alive() { local p; p="$(svc_web_pid)"; [ -n "${p:-}" ] && kill -0 "$p" 2>/dev/null; }

svc_config() { # (re)write wp-config.php and mu-plugin links; cheap and idempotent
  [ -f "$WP_DIR/wp-load.php" ] || die "WordPress is not installed yet (run: bin/lab install)"
  php "$LAB_ROOT/php/config.php"
  mkdir -p "$WP_DIR/wp-content/mu-plugins"
  local f; for f in "$LAB_ROOT"/mu-plugins/*.php; do ln -sfn "$f" "$WP_DIR/wp-content/mu-plugins/$(basename "$f")"; done
}

svc_web_start() {
  svc_web_alive && return 0
  http_ok "$LAB_URL/" 2 && die "port $LAB_PORT is already in use by something else (set LAB_PORT)"
  : >"$RUN_DIR/web.log"
  # realpath_cache and opcache are off on purpose: `lab use` swaps the theme symlink at run time.
  PHP_CLI_SERVER_WORKERS="$LAB_WORKERS" nohup setsid php -d realpath_cache_size=0 -d opcache.enable=0 \
    -d display_errors=0 -d log_errors=1 -d "error_log=$REPORT_DIR/php-error.log" -d memory_limit=256M \
    -d max_execution_time=120 -S "$LAB_BIND:$LAB_PORT" -t "$WP_DIR" "$LAB_ROOT/php/router.php" \
    >>"$RUN_DIR/web.log" 2>&1 &
  echo $! >"$RUN_DIR/web.pid"
  local i; for i in $(seq 1 40); do http_ok "$LAB_URL/wp-login.php" 3 && { ok "web server up at $LAB_URL"; return 0; }; sleep 0.5; done
  tail -5 "$RUN_DIR/web.log" >&2; die "web server did not answer at $LAB_URL"
}

svc_web_stop() {
  svc_web_alive || return 0
  local p; p="$(svc_web_pid)"; kill "$p" 2>/dev/null; sleep 0.3; kill -9 "$p" 2>/dev/null; rm -f "$RUN_DIR/web.pid"
  ok "web server stopped"
}
