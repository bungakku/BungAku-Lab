# lab use <target> <label> : link a build into wp-content and activate it.
lab_use() {
  [ $# -ge 2 ] || die "usage: lab use <target> <label>"
  local t="$1" label="$2" slug type kind
  slug="$(target_slug "$t")"; type="$(target_type "$t")"; kind=themes; [ "$type" = plugin ] && kind=plugins
  local tree="$DIST_DIR/tree/$label/$slug"
  [ -d "$tree" ] || die "no build '$label' for $t (run: bin/lab build $t <ref>)"
  . "$LAB_ROOT/bin/lib/up.sh"; lab_up >/dev/null
  local dest="$WP_DIR/wp-content/$kind/$slug"
  if [ -e "$dest" ] && [ ! -L "$dest" ]; then die "$dest is a real directory, not a lab link; move it away first"; fi
  ln -sfn "$tree" "$dest"
  php_lab use.php "$t" "$label" || die "activation failed"
  printf '{"target":"%s","label":"%s","type":"%s","slug":"%s"}\n' "$t" "$label" "$type" "$slug" >"$(current_file)"
  ok "using $t @ $label"
}
