lab_seed() { . "$LAB_ROOT/bin/lib/up.sh"; lab_up >/dev/null; php_lab seed.php "$@"; }
