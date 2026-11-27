# lab state <target> <name> [--mods JSON]
lab_state() {
  [ $# -ge 2 ] || die "usage: lab state <target> <name> [--mods JSON]"
  local t="$1" name="$2" mods="{}"; shift 2
  [ "${1:-}" = --mods ] && mods="$2"
  [ -f "$STATE_DIR/ids.json" ] || die "no seed data: run bin/lab seed first"
  php_lab state.php "$t" "$name" "$mods"
}
