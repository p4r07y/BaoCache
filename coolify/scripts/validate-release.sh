#!/usr/bin/env sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
plugin_file="$repo_root/wordpress/plugins/baocache/baocache.php"
readme_file="$repo_root/wordpress/plugins/baocache/readme.txt"
roadmap_file="$repo_root/docs/BAOCACHE-ROADMAP.md"

version=$(sed -n 's/^ \* Version:[[:space:]]*//p' "$plugin_file" | head -1)
constant=$(sed -n "s/define( 'BAOCACHE_VERSION', '\([^']*\)' );/\1/p" "$plugin_file" | head -1)
stable_tag=$(sed -n 's/^Stable tag: //p' "$readme_file" | head -1)
roadmap_version=$(sed -n 's/^## Roadmap status — //p' "$roadmap_file" | head -1)

if [ -z "$version" ] || [ "$version" != "$constant" ] || [ "$version" != "$stable_tag" ]; then
  echo "Release version mismatch: header=$version constant=$constant stable_tag=$stable_tag" >&2
  exit 1
fi

case "$roadmap_version" in
  *beta78*) ;;
  *) echo "Roadmap status does not identify the current beta78 release." >&2; exit 1 ;;
esac

if find "$repo_root/wordpress/plugins" -type d -name .git -print -quit | grep -q .; then
  echo "Nested Git repository found inside the WordPress plugin tree." >&2
  exit 1
fi

sh "$repo_root/scripts/verify-baocache-zip.sh"
echo "Release metadata, roadmap status and BaoCache ZIP are consistent ($version)."
