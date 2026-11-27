lab_reset() {
  . "$LAB_ROOT/bin/lib/up.sh"; . "$LAB_ROOT/bin/lib/use.sh"
  lab_up
  php "$LAB_ROOT/php/db-init.php" drop >/dev/null || die "could not drop the database"
  php_lab install.php || die "WordPress install failed"
  rm -f "$STATE_DIR/ids.json"
  local t l; t="$(current_get target)"; l="$(current_get label)"
  [ -n "$t" ] && [ -n "$l" ] && lab_use "$t" "$l"
  ok "reset done (fresh database)"
}
