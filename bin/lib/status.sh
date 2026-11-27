lab_status() {
  . "$LAB_ROOT/bin/lib/svc.sh"
  local cv="-"; [ -f "$WP_DIR/wp-includes/version.php" ] && cv="$(sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$WP_DIR/wp-includes/version.php")"
  printf '%-8s %s\n' home "$LAB_HOME"
  printf '%-8s WordPress %s\n' core "$cv"
  printf '%-8s %s\n' php "$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo missing)"
  if [ "$DB_MODE" = own ]; then
    if svc_db_alive; then printf '%-8s %s (own, socket, pid %s)\n' db "$(php "$LAB_ROOT/php/db-init.php" version 2>/dev/null || echo running)" "$(svc_db_pid)"
    else printf '%-8s stopped (own)\n' db; fi
  else printf '%-8s external %s:%s %s\n' db "$DB_HOST" "$DB_PORT" "$(php "$LAB_ROOT/php/db-init.php" version 2>/dev/null || echo unreachable)"; fi
  if http_ok "$LAB_URL/wp-login.php" 2; then printf '%-8s up at %s\n' web "$LAB_URL"; else printf '%-8s down\n' web; fi
  local t l; t="$(current_get target)"; l="$(current_get label)"
  if [ -n "$t" ]; then printf '%-8s %s @ %s\n' under-test "$t" "$l"; else printf '%-8s nothing yet (use: bin/lab use <target> <label>)\n' under-test; fi
  printf '%-8s %s\n' builds "$(ls "$DIST_DIR"/*.zip 2>/dev/null | wc -l) zip(s) in $DIST_DIR"
  printf '%-8s %s\n' browsers "$LAB_BROWSERS"
}
