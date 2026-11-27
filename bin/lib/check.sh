# lab check <target> REF [--base REF2] [--allow FILE] [--rebuild] [--verify] : the whole pre-release routine in one command.
. "$LAB_ROOT/bin/lib/test.sh"; . "$LAB_ROOT/bin/lib/verify.sh"
lab_check() {
  [ $# -ge 2 ] || die "usage: lab check <target> REF [--base REF2] [--allow FILE] [--rebuild] [--verify]"
  local t="$1" ref="$2"; shift 2
  . "$LAB_ROOT/bin/lib/build.sh"; ref="$(resolve_ref "$t" "$ref")"; info "ref: $ref"
  local args=(--label "$ref") rebuild=0 verify=0
  while [ $# -gt 0 ]; do case "$1" in
    --base) args+=(--base "$2"); shift 2 ;; --allow) args+=(--allow "$2"); shift 2 ;;
    --mods-base|--mods-new|--browsers|--grep) args+=("$1" "$2"); shift 2 ;;
    --rebuild) rebuild=1; shift ;; --verify) verify=1; shift ;; *) die "unknown option: $1" ;; esac; done
  mkstate; lab_up >/dev/null
  [ "$rebuild" = 1 ] && rm -rf "$DIST_DIR/tree/$ref"
  _ensure_build "$t" "$ref"
  lab_test all "$t" "${args[@]}"; local rc=$?
  if [ "$verify" = 1 ]; then lab_verify "$t" "$ref" || rc=1; fi
  return $rc
}
