lab_up() {
  mkstate; . "$LAB_ROOT/bin/lib/svc.sh"
  svc_db_start
  php "$LAB_ROOT/php/db-init.php" schema >/dev/null || die "could not prepare the database"
  svc_config
  svc_web_start
}
