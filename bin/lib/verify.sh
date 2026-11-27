# lab verify <target> <tag> : the built zip must match the published GitHub release asset, byte for byte in content.
lab_verify() {
  [ $# -ge 2 ] || die "usage: lab verify <target> <tag>"
  local t="$1" tag="$2" slug url zipname; slug="$(target_slug "$t")"
  url="$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo str_replace("{tag}",$argv[2],$j["release_url"]);' "$(target_json "$t")" "$tag")"
  zipname="$(basename "$url")"
  [ -f "$DIST_DIR/$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo str_replace("{label}",$argv[2],$j["zip_name"]);' "$(target_json "$t")" "$tag")" ] || lab_build "$t" "$tag" || exit 1
  local mine="$DIST_DIR/$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo str_replace("{label}",$argv[2],$j["zip_name"]);' "$(target_json "$t")" "$tag")"
  local w="$DIST_DIR/.verify-$tag"; rm -rf "$w"; mkdir -p "$w/a" "$w/b"
  info "downloading $url"
  curl -fsSL --connect-timeout 10 --max-time 180 "$url" -o "$w/release.zip" || die "could not download the release asset (not published yet, or network blocked)"
  unzip -q "$mine" -d "$w/a" && unzip -q "$w/release.zip" -d "$w/b" || die "unzip failed"
  if diff -r "$w/a" "$w/b" >"$w/diff.txt"; then
    ok "published $zipname matches the lab build ($(unzip -Z1 "$w/release.zip" | grep -vc '/$') files)"; rm -rf "$w"
  else head -20 "$w/diff.txt"; die "published zip differs from the lab build (details: $w/diff.txt)"; fi
}
