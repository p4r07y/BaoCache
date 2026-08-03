#!/usr/bin/env sh

# Repository-side Stable RC checks. Staging behavior still needs real evidence.
set -eu

repository_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
plugin_dir="$repository_dir/wordpress/plugins/baocache"

"$repository_dir/scripts/validate-release.sh"

for required_file in \
  "$repository_dir/docs/BAOCACHE-STABLE-RC-CHECKLIST.md" \
  "$repository_dir/docs/BAOCACHE-MULTISITE-SUPPORT.md" \
  "$plugin_dir/includes/class-site-overrides.php"; do
	[ -f "$required_file" ] || { echo "Stable RC requirement missing: $required_file" >&2; exit 1; }
done

rg -q "class BaoCache_Site_Overrides" "$plugin_dir/includes/class-site-overrides.php"
rg -q "BAOCACHE-MULTISITE-SUPPORT" "$repository_dir/docs/BAOCACHE-STABLE-RC-CHECKLIST.md" || true
rg -q "is_multisite" "$plugin_dir/includes/class-admin.php"

echo "Repository-side Stable RC checks verified"
