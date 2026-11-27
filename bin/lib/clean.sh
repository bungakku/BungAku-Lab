lab_clean() {
  . "$LAB_ROOT/bin/lib/svc.sh"
  if [ "${1:-}" = --all ]; then
    svc_web_stop; svc_db_stop; rm -rf "$LAB_HOME"; ok "removed $LAB_HOME"
  else
    rm -rf "$REPORT_DIR" "$DIST_DIR" "$LAB_HOME/cache"; mkdir -p "$REPORT_DIR" "$DIST_DIR"; ok "reports, builds and cache removed (WordPress and database kept)"
  fi
}
