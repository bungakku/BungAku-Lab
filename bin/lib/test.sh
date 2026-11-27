# lab test <static|php|browser|parity|all> <target> [options]
. "$LAB_ROOT/bin/lib/svc.sh"; . "$LAB_ROOT/bin/lib/up.sh"; . "$LAB_ROOT/bin/lib/use.sh"; . "$LAB_ROOT/bin/lib/build.sh"

_t_zip()  { php -r '$j=json_decode(file_get_contents($argv[1]),true); echo str_replace("{label}",$argv[2],$j["zip_name"]);' "$(target_json "$1")" "$2"; }
_t_states() { php -r '$j=json_decode(file_get_contents($argv[1]),true); echo implode(" ",array_keys($j["states"]));' "$(target_json "$1")"; }
_is_ref()  { [[ "$1" =~ ^v?[0-9]+\.[0-9]+ ]]; }

_ensure_build() { # target label : build from the git tag when the label looks like one
  [ -d "$DIST_DIR/tree/$2/$(target_slug "$1")" ] && return 0
  _is_ref "$2" && { lab_build "$1" "$2" || exit 1; return 0; }
  die "no build '$2' for $1 (run: bin/lab build $1 --src DIR --label $2)"
}

_ensure_active() { # target label
  lab_up >/dev/null
  _ensure_build "$1" "$2"
  local kind=themes; [ "$(target_type "$1")" = plugin ] && kind=plugins
  # re-activate when another build is current, or when the link vanished (core was replaced)
  if [ "$(current_get target)/$(current_get label)" != "$1/$2" ] || [ ! -L "$WP_DIR/wp-content/$kind/$(target_slug "$1")" ]; then lab_use "$1" "$2" >/dev/null || exit 1; fi
  [ -f "$STATE_DIR/ids.json" ] || php_lab seed.php >/dev/null || exit 1
}

_gate_debuglog() { # fails the run on PHP warnings/notices/deprecations/fatals written during the suite
  local f="$REPORT_DIR/debug.log" n=0
  [ -f "$f" ] && n="$(grep -cE 'PHP (Warning|Notice|Deprecated|Fatal error|Parse error)' "$f")"
  if [ "$n" -gt 0 ]; then
    echo "  FAIL debug.log has $n PHP warning/notice/deprecated/fatal line(s)"; grep -E 'PHP (Warning|Notice|Deprecated|Fatal error|Parse error)' "$f" | sort | uniq -c | sort -rn | head -4 | sed 's/^/       /'
    cp "$f" "$_run/debug-$1.log"; return 1
  fi
  echo "  PASS debug.log is clean after the $1 suite"; return 0
}

_suite_static() { local t="$1" l="$2"; _ensure_build "$t" "$l"
  php "$LAB_ROOT/tests/static/run.php" "$t" "$DIST_DIR/tree/$l" "$DIST_DIR/$(_t_zip "$t" "$l")" "$l"; }

_suite_php() { local t="$1" l="$2"; _ensure_active "$t" "$l"; php_lab state.php "$t" ltr >/dev/null; : >"$REPORT_DIR/debug.log"
  php -d display_errors=stderr -d memory_limit=512M "$LAB_ROOT/tests/php/run.php" "$t" "$l" ${_update:+--update}; local rc=$?
  _gate_debuglog php || rc=1; return $rc; }

_suite_browser() { local t="$1" l="$2"; _ensure_active "$t" "$l"; php_lab state.php "$t" ltr >/dev/null; : >"$REPORT_DIR/debug.log"
  local json="$_run/browser.json"; local g=(); [ -n "$_grep" ] && g=(--grep "$_grep")
  (cd "$LAB_ROOT" && LAB_TARGET="$t" LAB_LABEL="$l" LAB_REPORT_DIR="$_run" LAB_BIN="$LAB_ROOT/bin/lab" PLAYWRIGHT_JSON_OUTPUT_NAME="$json" \
     npx --no-install playwright test -c tests/browser/playwright.config.js "${g[@]}") >"$_run/browser.playwright.log" 2>&1; local rc=$?
  node "$LAB_ROOT/tests/browser/summarize.js" "$json" "$_run/browser.playwright.log" || rc=1
  php_lab state.php "$t" ltr >/dev/null
  _gate_debuglog browser || rc=1; return $rc; }

_suite_parity() { local t="$1" l="$2"
  [ -n "$_base" ] || { echo "RESULT parity pass=0 fail=1 warn=0"; echo "  FAIL parity needs --base LABEL"; return 1; }
  _ensure_build "$t" "$_base"; _ensure_build "$t" "$l"; lab_up >/dev/null
  [ -f "$STATE_DIR/ids.json" ] || php_lab seed.php >/dev/null
  local allow="$_allow"   # --allow none = compare strictly, ignore the expected/ file
  if [ "$allow" = none ]; then allow=""; elif [ -z "$allow" ] && [ -f "$LAB_ROOT/expected/$t/parity-allow-$l.json" ]; then allow="$LAB_ROOT/expected/$t/parity-allow-$l.json"; fi
  local rd="$_run/parity"; rm -rf "$rd"; mkdir -p "$rd"
  local st side lab mods
  for st in $(_t_states "$t"); do
    for side in base new; do
      if [ "$side" = base ]; then lab="$_base"; mods="$_mods_base"; else lab="$l"; mods="$_mods_new"; fi
      lab_use "$t" "$lab" >/dev/null || return 1
      php_lab state.php "$t" "$st" "$mods" >/dev/null || return 1
      node "$LAB_ROOT/tests/parity/grab.js" "$rd/$st/$side" "$t" "$st" || return 1
    done
  done
  lab_use "$t" "$l" >/dev/null; php_lab state.php "$t" ltr >/dev/null
  [ -n "$allow" ] && echo "  allow file: ${allow#$LAB_ROOT/}"
  node "$LAB_ROOT/tests/parity/compare.js" "$rd" "$t" "$_base" "$l" ${allow:+"$allow"}; }

lab_test() {
  [ $# -ge 2 ] || die "usage: lab test <static|php|browser|parity|all> <target> [--label L] [--base L] [--allow FILE] [--mods-base JSON] [--mods-new JSON] [--browsers LIST] [--grep TEXT] [--update] [--verbose]"
  local suite="$1" t="$2"; shift 2
  local label="" _base="" _allow="" _mods_base="{}" _mods_new="{}" _grep="" _update="" verbose=0
  while [ $# -gt 0 ]; do case "$1" in
    --label) label="$2"; shift 2 ;; --base) _base="$2"; shift 2 ;; --allow) _allow="$2"; shift 2 ;;
    --mods-base) _mods_base="$2"; shift 2 ;; --mods-new) _mods_new="$2"; shift 2 ;;
    --browsers) LAB_BROWSERS="$2"; export LAB_BROWSERS; shift 2 ;; --grep) _grep="$2"; shift 2 ;;
    --update) _update=1; shift ;; --verbose|-v) verbose=1; shift ;; *) die "unknown option: $1" ;; esac; done
  target_dir "$t" >/dev/null; mkstate
  [ -z "$label" ] && { [ "$(current_get target)" = "$t" ] && label="$(current_get label)"; }
  [ -n "$label" ] || die "which build? add --label LABEL (or run: bin/lab use $t LABEL first)"
  local _run="$REPORT_DIR/run-$(date +%Y%m%d-%H%M%S)-$suite"; mkdir -p "$_run"
  local list="$suite"; [ "$suite" = all ] && { list="static php browser"; [ -n "$_base" ] && list="$list parity"; }
  local s rc total=0 summary=()
  info "$t @ $label  (WordPress $(sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$WP_DIR/wp-includes/version.php" 2>/dev/null), PHP $(php -r 'echo PHP_VERSION;'), browsers: $LAB_BROWSERS)"
  for s in $list; do
    case "$s" in static|php|browser|parity) ;; *) die "unknown suite: $s" ;; esac
    echo "== $s"
    if [ "$verbose" = 1 ]; then "_suite_$s" "$t" "$label" 2>&1 | tee "$_run/$s.log"; rc=${PIPESTATUS[0]}
    else "_suite_$s" "$t" "$label" 2>&1 | tee "$_run/$s.log" | grep -E '^\s*(FAIL|WARN)|^RESULT|^\s+rule |^parity|allow file|^\s+[-+] '; rc=${PIPESTATUS[0]}; fi
    summary+=("$s|$rc|$(grep -h '^RESULT' "$_run/$s.log" | tail -1)")
    [ "$rc" -ne 0 ] && total=1
  done
  echo; printf '%-9s %-6s %5s %5s %5s\n' suite result pass fail warn
  local row p f w
  for row in "${summary[@]}"; do
    IFS='|' read -r s rc line <<<"$row"
    p="$(sed -n 's/.*pass=\([0-9]*\).*/\1/p' <<<"$line")"; f="$(sed -n 's/.*fail=\([0-9]*\).*/\1/p' <<<"$line")"; w="$(sed -n 's/.*warn=\([0-9]*\).*/\1/p' <<<"$line")"
    printf '%-9s %-6s %5s %5s %5s\n' "$s" "$([ "$rc" -eq 0 ] && echo PASS || echo FAIL)" "${p:--}" "${f:--}" "${w:--}"
  done
  echo "reports: $_run"
  return $total
}
