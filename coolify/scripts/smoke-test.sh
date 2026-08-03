#!/usr/bin/env sh
# Public, anonymous acceptance test for a deployed BaoCache stack.
set -eu

base_url=${1:-${BAOCACHE_SMOKE_URL:-}}

if [ -z "$base_url" ]; then
  echo "Usage: sh scripts/smoke-test.sh https://staging.example.com/" >&2
  echo "Or set BAOCACHE_SMOKE_URL before running this command." >&2
  exit 64
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "curl is required to run the BaoCache smoke test." >&2
  exit 69
fi

case "$base_url" in
  http://*|https://*) ;;
  *)
    echo "The URL must begin with http:// or https://." >&2
    exit 64
    ;;
esac

base_url=${base_url%/}
headers_file=$(mktemp "${TMPDIR:-/tmp}/baocache-smoke.XXXXXX")
trap 'rm -f "$headers_file"' EXIT HUP INT TERM

request_homepage() {
  : >"$headers_file"
  status=$(curl --silent --show-error --location --output /dev/null \
    --dump-header "$headers_file" --write-out '%{http_code}' \
    --connect-timeout 10 --max-time 45 \
    --header 'Cookie:' --header 'Cache-Control: no-cache' \
    --user-agent 'BaoCache-Staging-Smoke/1.0' \
    "$base_url/")

  case "$status" in
    2??|3??) ;;
    *)
      echo "FAIL: homepage returned HTTP $status." >&2
      exit 1
      ;;
  esac

  cache_status=$(awk '
    tolower($0) ~ /^x-fastcgi-cache:[[:space:]]*/ {
      value=$0
      sub(/^[^:]*:[[:space:]]*/, "", value)
      sub(/\r$/, "", value)
      result=value
    }
    END { print result }
  ' "$headers_file")

  if [ -z "$cache_status" ]; then
    echo "FAIL: X-FastCGI-Cache header is missing. Verify that this URL reaches BaoCache Nginx." >&2
    exit 1
  fi

  printf '%s' "$cache_status"
}

first_status=$(request_homepage)
second_status=$(request_homepage)

printf 'First anonymous request:  %s\n' "$first_status"
printf 'Second anonymous request: %s\n' "$second_status"

if [ "$second_status" != "HIT" ]; then
  echo "FAIL: the second anonymous request was not a FastCGI HIT." >&2
  echo "Check bypass diagnostics, cookies, query strings and the cacheable URL rules before release." >&2
  exit 1
fi

echo "PASS: public anonymous FastCGI cache is serving HIT responses."
