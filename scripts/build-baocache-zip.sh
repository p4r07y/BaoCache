#!/usr/bin/env sh

# Build the distributable from the exact tracked plugin directory.
set -eu

repository_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
plugins_dir="$repository_dir/wordpress/plugins"
plugin_dir="$plugins_dir/baocache"
artifact="$plugins_dir/baocache.zip"

[ -f "$plugin_dir/baocache.php" ] || {
  echo "BaoCache plugin source was not found: $plugin_dir" >&2
  exit 1
}

temporary_dir=$(mktemp -d "${TMPDIR:-/tmp}/baocache-build.XXXXXX")
temporary_artifact="$temporary_dir/baocache.zip"
trap 'rm -rf "$temporary_dir"' EXIT HUP INT TERM

(
  cd "$plugins_dir"
  zip -qr -X "$temporary_artifact" baocache \
    -x 'baocache/.DS_Store' 'baocache/**/.DS_Store'
)

mv "$temporary_artifact" "$artifact"
trap - EXIT HUP INT TERM
rmdir "$temporary_dir"
echo "Built $artifact"
