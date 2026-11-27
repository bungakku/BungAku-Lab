# lab build <target> [REF|latest] [--src DIR] [--zip FILE] [--label NAME] [--version V]
# Produces .lab/dist/<zip_name> and the unpacked tree .lab/dist/tree/<label>/<slug>/.
# resolve_ref <target> <ref> : "latest" becomes the newest version tag of the target's repo.
resolve_ref() {
  [ "$2" = latest ] || { echo "$2"; return; }
  local repo tag; repo="$(target_get "$1" repo)" || die "target has no repo URL"
  tag="$(git ls-remote --tags --refs "$repo" 2>/dev/null | sed 's#.*refs/tags/##' | grep -E '^v?[0-9]+\.[0-9]+' | sort -V | tail -1)"
  [ -n "$tag" ] || die "could not list tags of $repo"
  echo "$tag"
}

lab_build() {
  [ $# -ge 1 ] || die "usage: lab build <target> [REF] [--src DIR] [--zip FILE] [--label NAME]"
  local t="$1"; shift
  local slug zipname; slug="$(target_slug "$t")"
  local ref="" src="" zip="" label="" version=""
  while [ $# -gt 0 ]; do case "$1" in
    --src) src="$2"; shift 2 ;; --zip) zip="$2"; shift 2 ;; --label) label="$2"; shift 2 ;; --version) version="$2"; shift 2 ;;
    -*) die "unknown option: $1" ;; *) ref="$1"; shift ;; esac; done
  mkstate; need php "php"; need zip "zip"; need unzip "unzip"
  [ -n "$ref" ] && ref="$(resolve_ref "$t" "$ref")"
  [ -n "$ref$src$zip" ] || die "give a REF (git tag), --src DIR or --zip FILE"
  [ -z "$label" ] && label="${ref:-$( [ -n "$zip" ] && basename "$zip" .zip || echo local )}"
  [ -z "$version" ] && [[ "$ref" =~ ^v?[0-9]+\.[0-9]+ ]] && version="${ref#v}"
  [ -z "$version" ] && [[ "$label" =~ ^v?[0-9]+\.[0-9]+ ]] && version="${label#v}"

  local work="$DIST_DIR/.work-$label" tree="$DIST_DIR/tree/$label"
  rm -rf "$work" "$tree"; mkdir -p "$work/$slug" "$tree"
  local from=""
  if [ -n "$zip" ]; then
    unzip -q "$zip" -d "$work/unz" || die "cannot unzip $zip"
    local root; root="$(ls "$work/unz")"; [ "$(echo "$root" | wc -l)" = 1 ] || die "zip must have exactly one root folder, found: $root"
    rm -rf "$work/$slug"; mv "$work/unz/$root" "$work/$slug"; from="zip $zip"; version=""   # already built: do not rewrite versions
  elif [ -n "$src" ]; then
    [ -d "$src" ] || die "no such directory: $src"
    _lab_copy_filtered "$t" "$src" "$work/$slug"; from="dir $src"
  else
    local repo; repo="$(target_get "$t" repo)" || die "target has no repo URL"
    info "cloning $repo @ $ref"
    git -c advice.detachedHead=false clone --quiet --depth 1 --branch "$ref" "$repo" "$work/src" || die "git clone failed for tag $ref"
    _lab_copy_filtered "$t" "$work/src" "$work/$slug"; from="git $ref"
  fi
  if [ -n "$version" ]; then php "$LAB_ROOT/php/build-version.php" "$(target_json "$t")" "$work/$slug" "$version" || die "version rewrite failed"; fi

  zipname="$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo str_replace("{label}",$argv[2],$j["zip_name"]);' "$(target_json "$t")" "$label")"
  rm -f "$DIST_DIR/$zipname"
  (cd "$work" && zip -rq "$DIST_DIR/$zipname" "$slug") || die "zip failed"
  cp -a "$work/$slug" "$tree/$slug"; rm -rf "$work"
  local n; n="$(unzip -Z1 "$DIST_DIR/$zipname" | grep -vc '/$')"
  ok "built $zipname ($n files, from $from, version ${version:-as-is}) -> $DIST_DIR"
}

_lab_copy_filtered() { # target srcdir dstdir
  local ex=() e
  while IFS= read -r e; do ex+=(--exclude="$e"); done < <(php -r '$j=json_decode(file_get_contents($argv[1]),true); foreach(($j["build"]["exclude"]??[]) as $x) echo $x,"\n";' "$(target_json "$1")")
  (cd "$2" && tar cf - "${ex[@]}" .) | (cd "$3" && tar xf -)
}
