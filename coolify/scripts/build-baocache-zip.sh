#!/usr/bin/env sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
output=${1:-"$repo_root/wordpress/plugins/baocache.zip"}
plugin_dir="$repo_root/wordpress/plugins/baocache"

if [ ! -f "$plugin_dir/baocache.php" ]; then
  echo "BaoCache plugin source is missing: $plugin_dir" >&2
  exit 1
fi

output_dir=$(dirname -- "$output")
mkdir -p "$output_dir"
staging_dir=$(mktemp -d "${TMPDIR:-/tmp}/baocache-zip.XXXXXX")
trap 'rm -rf "$staging_dir"' EXIT HUP INT TERM

mkdir -p "$staging_dir/wordpress/plugins"
cp -R "$plugin_dir" "$staging_dir/wordpress/plugins/baocache"
find "$staging_dir/wordpress/plugins/baocache" -name .DS_Store -type f -delete

staged_output="$staging_dir/baocache.zip"
( cd "$staging_dir/wordpress/plugins" && zip -qr -X "$staged_output" baocache )
cp "$staged_output" "$output"
echo "Built $output"
