#!/usr/bin/env sh

# Read-only public staging smoke test. It never sends cookies, forms or purges.
set -eu

staging_url=${BAOCACHE_STAGING_URL:-}
[ -n "$staging_url" ] || { echo "Set BAOCACHE_STAGING_URL, for example https://staging.example.com" >&2; exit 2; }

base_url=${staging_url%/}
# Homepage is always checked. Add real business routes explicitly, for example:
# BAOCACHE_STAGING_PATHS='/ /account/ /cart/ /checkout/'
paths=${BAOCACHE_STAGING_PATHS:-/}
failed=0

for path in $paths; do
	response=$(curl --fail --silent --show-error --location --max-time 15 --output /dev/null --write-out '%{http_code} %{url_effective}' "$base_url$path") || { echo "FAIL $path request" >&2; failed=1; continue; }
	status=${response%% *}
	case "$status" in
		2??|3??) echo "PASS $path HTTP $status" ;;
		*) echo "FAIL $path HTTP $status" >&2; failed=1 ;;
	esac
done

[ "$failed" -eq 0 ] || exit 1
echo "Public staging smoke test passed; record functional and rollback evidence separately."
