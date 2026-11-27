#!/usr/bin/env bash
# Shared settings and helpers. Sourced by bin/lab and every bin/lib/*.sh.

LAB_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Environment wins over lab.env, which wins over versions.env.
_lab_env_keep=$(env | grep -E '^(LAB|WP|DB|PHP|NODE|PLAYWRIGHT|MARIADB)_[A-Z_]*=.+' || true)   # empty values never override
set -a
# shellcheck disable=SC1091
. "$LAB_ROOT/versions.env"
[ -f "$LAB_ROOT/lab.env" ] && . "$LAB_ROOT/lab.env"
if [ -n "$_lab_env_keep" ]; then
  while IFS= read -r _l; do export "${_l?}"; done <<<"$_lab_env_keep"
fi
set +a

LAB_HOME="${LAB_HOME:-$LAB_ROOT/.lab}"
LAB_HOST="${LAB_HOST:-127.0.0.1}"
LAB_PORT="${LAB_PORT:-8080}"
LAB_BIND="${LAB_BIND:-$LAB_HOST}"   # address the web server binds to (0.0.0.0 in Docker)
LAB_URL="http://${LAB_HOST}:${LAB_PORT}"
LAB_WORKERS="${LAB_WORKERS:-4}"
LAB_BROWSERS="${LAB_BROWSERS:-chromium}"
WP_DIR="$LAB_HOME/wp"
DIST_DIR="$LAB_HOME/dist"
RUN_DIR="$LAB_HOME/run"
STATE_DIR="$LAB_HOME/state"
REPORT_DIR="$LAB_HOME/reports"
DB_DIR="$LAB_HOME/db"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_SOCKET="$RUN_DIR/mysqld.sock"
export LAB_ROOT LAB_HOME LAB_HOST LAB_BIND LAB_PORT LAB_URL WP_DIR DIST_DIR RUN_DIR STATE_DIR REPORT_DIR \
  DB_MODE DB_NAME DB_USER DB_PASS DB_HOST DB_PORT DB_SOCKET LAB_BROWSERS

if [ -t 1 ]; then _c_ok=$'\033[32m'; _c_warn=$'\033[33m'; _c_err=$'\033[31m'; _c_dim=$'\033[2m'; _c_off=$'\033[0m'
else _c_ok=; _c_warn=; _c_err=; _c_dim=; _c_off=; fi

say()  { printf '%s\n' "$*"; }
info() { printf '%s==>%s %s\n' "$_c_dim" "$_c_off" "$*"; }
ok()   { printf '%s ok %s %s\n' "$_c_ok" "$_c_off" "$*"; }
warn() { printf '%sWARN%s %s\n' "$_c_warn" "$_c_off" "$*" >&2; }
die()  { printf '%sFAIL%s %s\n' "$_c_err" "$_c_off" "$*" >&2; exit 1; }

have()      { command -v "$1" >/dev/null 2>&1; }
need()      { have "$1" || die "missing command: $1 ($2)"; }
is_root()   { [ "$(id -u)" = 0 ]; }
mkstate()   { mkdir -p "$LAB_HOME" "$DIST_DIR" "$RUN_DIR" "$STATE_DIR" "$REPORT_DIR" "$LAB_HOME/cache"; }
version_ge() { [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]; } # version_ge have want

# Read one key from a target's target.json: target_get <target> <jq-ish path as php expr>
# Uses PHP so no jq dependency. Example: target_get bungaku-theme '["slug"]'
target_dir()  { [ -d "$LAB_ROOT/targets/$1" ] || die "unknown target '$1' (see: ls targets/)"; echo "$LAB_ROOT/targets/$1"; }
target_json() { echo "$(target_dir "$1")/target.json"; }
target_get() {
  php -r '$j=json_decode(file_get_contents($argv[1]),true); $v=$j; foreach(array_filter(explode(".",$argv[2]),"strlen") as $k){ if(!is_array($v)||!array_key_exists($k,$v)){ exit(3);} $v=$v[$k]; } echo is_scalar($v)?$v:json_encode($v);' "$(target_json "$1")" "$2"
}
target_slug() { target_get "$1" slug; }
target_type() { target_get "$1" type; }

php_lab() { # php_lab <script> [args] : run a lab PHP helper that loads WordPress
  php -d display_errors=stderr -d memory_limit=512M "$LAB_ROOT/php/$1" "${@:2}"
}

current_file() { echo "$STATE_DIR/current.json"; }
current_get()  { [ -f "$(current_file)" ] && php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j[$argv[2]] ?? "";' "$(current_file)" "$1"; }

http_ok() { curl -fsS -o /dev/null --max-time "${2:-5}" "$1" 2>/dev/null; }

# Unix socket paths are limited to ~107 bytes; fall back to /tmp when LAB_HOME is deep.
if [ "${#DB_SOCKET}" -gt 100 ]; then
  DB_SOCKET="/tmp/bungaku-lab-$(printf '%s' "$LAB_HOME" | cksum | cut -d' ' -f1).sock"; export DB_SOCKET
fi
