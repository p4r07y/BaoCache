#!/usr/bin/env sh

# Prove that the release ZIP contains the same plugin files as source.
set -eu

repository_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
plugins_dir="$repository_dir/wordpress/plugins"
plugin_dir="$plugins_dir/baocache"
artifact="$plugins_dir/baocache.zip"
temporary_dir=$(mktemp -d "${TMPDIR:-/tmp}/baocache-verify.XXXXXX")
trap 'rm -rf "$temporary_dir"' EXIT HUP INT TERM

[ -f "$artifact" ] || {
  echo "Release artifact was not found: $artifact" >&2
  exit 1
}

unzip -tq "$artifact" >/dev/null
unzip -qq "$artifact" -d "$temporary_dir"

find "$plugin_dir" -name '.DS_Store' -prune -o -type f -print | \
  sed "s#^$plugin_dir/##" | sort > "$temporary_dir/source-files.txt"
find "$temporary_dir/baocache" -type f -print | \
  sed "s#^$temporary_dir/baocache/##" | sort > "$temporary_dir/zip-files.txt"

diff -u "$temporary_dir/source-files.txt" "$temporary_dir/zip-files.txt"
while IFS= read -r relative_file; do
  cmp -s "$plugin_dir/$relative_file" "$temporary_dir/baocache/$relative_file" || {
    echo "ZIP content differs from source: $relative_file" >&2
    exit 1
  }
done < "$temporary_dir/source-files.txt"

echo "ZIP/source parity verified"
