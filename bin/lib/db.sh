. "$LAB_ROOT/bin/lib/svc.sh"
lab_db() {
  local c; c="$(svc_find mariadb mysql 2>/dev/null || { . "$LAB_ROOT/bin/lib/svc.sh"; svc_find mariadb mysql; })" || die "mariadb client not found"
  if [ "$DB_MODE" = own ]; then
    [ $# -gt 0 ] && exec "$c" --no-defaults -S "$DB_SOCKET" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$*"
    exec "$c" --no-defaults -S "$DB_SOCKET" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
  fi
  [ $# -gt 0 ] && exec "$c" --no-defaults -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$*"
  exec "$c" --no-defaults -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
}
