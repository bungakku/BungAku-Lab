# lab selftest <target> <label> : plant two faults in a copy of a good build and prove the suites catch them.
. "$LAB_ROOT/bin/lib/test.sh"
lab_selftest() {
  [ $# -ge 2 ] || die "usage: lab selftest <target> <good-label>   (the build must exist or be a git tag)"
  local t="$1" good="$2" slug main bad="$2-selftest"; slug="$(target_slug "$t")"
  main="$(target_get "$t" selftest.php_file 2>/dev/null || echo functions.php)"
  mkstate; lab_up >/dev/null; _ensure_build "$t" "$good"
  local w="$DIST_DIR/.selftest"; rm -rf "$w"; mkdir -p "$w"; cp -a "$DIST_DIR/tree/$good/$slug" "$w/$slug"
  [ -f "$w/$slug/$main" ] || die "selftest.php_file '$main' not found in the build (set it in target.json)"
  # Fault 1: a translation call with a foreign text domain. Fault 2: one extra line of HTML in every front-end page.
  printf "\nfunction lab_selftest_fault() { return __( 'Lab selftest', 'lab-wrong-domain' ); }\nadd_action( 'wp_footer', function () { echo '<!-- lab-selftest-fault -->'; } );\n" >>"$w/$slug/$main"
  rm -rf "$DIST_DIR/tree/$bad"; mkdir -p "$DIST_DIR/tree/$bad"; cp -a "$w/$slug" "$DIST_DIR/tree/$bad/$slug"
  (cd "$w" && rm -f "$DIST_DIR/$(_t_zip "$t" "$bad")" && zip -rq "$DIST_DIR/$(_t_zip "$t" "$bad")" "$slug"); rm -rf "$w"
  local out1 out2 rc=0
  out1="$(php "$LAB_ROOT/tests/static/run.php" "$t" "$DIST_DIR/tree/$bad" "$DIST_DIR/$(_t_zip "$t" "$bad")" "$bad" 2>&1)"
  if grep -q "FAIL text domain" <<<"$out1"; then ok "static suite caught the wrong text domain"; else warn "static suite MISSED the wrong text domain"; rc=1; fi
  out2="$(lab_test parity "$t" --label "$bad" --base "$good" --allow none 2>&1)"
  if grep -qE 'unexplained [1-9]' <<<"$out2"; then ok "parity suite caught the extra footer comment"; else warn "parity suite MISSED the extra footer comment"; rc=1; fi
  lab_use "$t" "$good" >/dev/null
  rm -rf "$DIST_DIR/tree/$bad" "$DIST_DIR/$(_t_zip "$t" "$bad")"
  [ $rc -eq 0 ] && ok "selftest passed: the suites can fail" || die "selftest failed: a suite does not catch a planted fault"
}
