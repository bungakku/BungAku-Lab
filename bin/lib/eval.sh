lab_eval() {
  [ $# -ge 1 ] || die "usage: lab eval '<php code>' | lab eval FILE.php"
  if [ -f "$1" ]; then php_lab eval.php --file "$@"; else php_lab eval.php --code "$1"; fi
}
