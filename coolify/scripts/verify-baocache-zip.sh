#!/usr/bin/env sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
zip_file=${1:-"$repo_root/wordpress/plugins/baocache.zip"}
plugin_dir="$repo_root/wordpress/plugins/baocache"
extract_dir=$(mktemp -d "${TMPDIR:-/tmp}/baocache-zip-check.XXXXXX")
trap 'rm -rf "$extract_dir"' EXIT HUP INT TERM

if [ ! -f "$zip_file" ]; then
  echo "BaoCache ZIP is missing: $zip_file" >&2
  exit 1
fi

unzip -q "$zip_file" -d "$extract_dir"
source_copy="$extract_dir/source-baocache"
cp -R "$plugin_dir" "$source_copy"
find "$source_copy" -name .DS_Store -type f -delete
if ! diff -qr "$source_copy" "$extract_dir/baocache" >/dev/null; then
  echo "BaoCache ZIP is out of sync with wordpress/plugins/baocache." >&2
  diff -qr "$source_copy" "$extract_dir/baocache" || true
  exit 1
fi

echo "BaoCache ZIP matches wordpress/plugins/baocache."
