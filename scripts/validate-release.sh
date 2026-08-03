#!/usr/bin/env sh

# Validate the version and artifact gates required for every roadmap milestone.
set -eu

repository_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
plugin_file="$repository_dir/wordpress/plugins/baocache/baocache.php"
readme_file="$repository_dir/wordpress/plugins/baocache/readme.txt"
roadmap_file="$repository_dir/docs/BAOCACHE-ROADMAP.md"

header_version=$(sed -n 's/^ \* Version: *//p' "$plugin_file" | head -n 1)
constant_version=$(sed -n "s/^define( 'BAOCACHE_VERSION', '\([^']*\)' );/\1/p" "$plugin_file" | head -n 1)
stable_tag=$(sed -n 's/^Stable tag: *//p' "$readme_file" | head -n 1)
roadmap_beta=$(sed -n 's/^## Roadmap status — \(beta[0-9][0-9]*\)$/\1/p' "$roadmap_file" | head -n 1)
version_beta=$(printf '%s' "$header_version" | sed -n 's/^0\.3\.0-beta\.\([0-9][0-9]*\)$/beta\1/p')

[ -n "$header_version" ] && [ "$header_version" = "$constant_version" ] && [ "$header_version" = "$stable_tag" ] || {
  echo "Version mismatch: header=$header_version constant=$constant_version stable-tag=$stable_tag" >&2
  exit 1
}

[ -n "$version_beta" ] && [ "$version_beta" = "$roadmap_beta" ] || {
  echo "Roadmap mismatch: version=$header_version roadmap=$roadmap_beta" >&2
  exit 1
}

if find "$repository_dir/wordpress/plugins" -mindepth 2 -type d -name .git -print -quit | grep -q .; then
  echo "Nested Git repository found under wordpress/plugins" >&2
  exit 1
fi

"$repository_dir/scripts/verify-baocache-zip.sh"
echo "Release gate verified for $header_version"
