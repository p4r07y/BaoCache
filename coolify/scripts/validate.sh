#!/usr/bin/env sh
set -eu

if [ ! -f .env ]; then
  echo "Copy .env.example to .env and set the required values first." >&2
  exit 1
fi

docker compose --env-file .env config -q

required_files="
docker-compose.yml
php/Dockerfile
php/docker-entrypoint-v4.sh
php/check-managed-db.php
php/conf.d/99-wordpress.ini
php/conf.d/10-opcache.ini
php/php-fpm.d/zz-wordpress.conf
nginx/Dockerfile
nginx/nginx.conf
nginx/default.conf
secrets/Dockerfile
secrets/entrypoint.sh
metrics/Dockerfile
metrics/server.py
scripts/smoke-test.sh
scripts/build-baocache-zip.sh
scripts/verify-baocache-zip.sh
scripts/validate-release.sh
redis/Dockerfile
redis/redis.conf
redis/redis-secure-entrypoint.sh
wordpress/plugins/power-schedule-manager/power-schedule-manager.php
wordpress/plugins/baocache/baocache.php
wordpress/plugins/baocache/includes/class-cloudflare.php
wordpress/plugins/baocache/includes/class-analytics.php
wordpress/plugins/baocache/includes/class-csp.php
wordpress/plugins/baocache/includes/class-critical-images.php
wordpress/plugins/baocache/includes/class-resource-hints.php
wordpress/plugins/baocache/includes/class-uninstall-manager.php
wordpress/plugins/baocache/includes/class-database-health.php
wordpress/plugins/baocache/uninstall.php
wordpress/plugins/baocache/includes/class-frontend-metrics.php
wordpress/plugins/baocache/includes/class-warmup.php
wordpress/plugins/baocache/assets/baocache-resource-timing.js
wordpress/plugins/baocache/assets/baocache-async-css.js
wordpress/plugins/baocache/assets/baocache-analytics-bootstrap.js
wordpress/plugins/baocache/assets/baocache-analytics-events.js
wordpress/plugins/baocache/assets/baocache-analytics-adapters.js
wordpress/themes/cupdienlamdong/style.css
wordpress/themes/cupdienlamdong/functions.php
"

for required_file in $required_files; do
  if [ ! -f "$required_file" ]; then
    echo "Missing required file: $required_file" >&2
    exit 1
  fi
done

sh -n php/docker-entrypoint-v4.sh
sh -n redis/redis-secure-entrypoint.sh
sh -n secrets/entrypoint.sh
sh -n scripts/validate.sh
sh -n scripts/smoke-test.sh

if grep -Eq '^[[:space:]]*networks:' docker-compose.yml; then
  echo "Invalid Coolify Compose configuration: remove custom networks." >&2
  exit 1
fi

php_section_count=$(grep -c '^\[PHP\]$' php/conf.d/99-wordpress.ini || true)
if [ "$php_section_count" -ne 1 ]; then
  echo "Invalid PHP configuration: expected exactly one [PHP] section." >&2
  exit 1
fi

if grep -E 'fastcgi_cache_use_stale[^;]*(http_502|http_504)' \
  nginx/nginx.conf nginx/default.conf >/dev/null; then
  echo "Invalid Nginx FastCGI stale status: remove http_502 and http_504." >&2
  exit 1
fi

if ! grep -q 'REDIS_CACHE_SHA256=' php/Dockerfile \
  || ! grep -q 'docker-php-ext-enable redis' php/Dockerfile \
  || ! grep -q '/opt/bundled-dropins/object-cache.php' php/Dockerfile \
  || ! grep -q '/opt/bundled-plugins/baocache' php/Dockerfile \
  || ! grep -q 'baocache-metrics' docker-compose.yml \
  || ! grep -q 'BAOCACHE_METRICS_TOKEN_FILE: /run/baocache-secrets/metrics-token' docker-compose.yml \
  || ! grep -q 'baocache-secrets' docker-compose.yml \
  || ! grep -q 'fastcgi_cache_purge WORDPRESS' nginx/default.conf \
  || ! grep -q 'http_x_baocache_purge_uri' nginx/default.conf \
  || ! grep -q 'htpasswd -iBc' secrets/entrypoint.sh \
  || ! grep -q 'location = /wp-admin/options.php' nginx/default.conf \
  || ! grep -q 'baocache_warmup_tick' wordpress/plugins/baocache/includes/class-warmup.php \
  || ! grep -q 'AUTO_ACTIVATE_BUNDLED_PLUGINS: "redis-cache power-schedule-manager baocache"' docker-compose.yml; then
  echo "Redis Object Cache build, drop-in or activation configuration is incomplete." >&2
  exit 1
fi

if command -v php >/dev/null 2>&1; then
  php -l php/check-managed-db.php >/dev/null
  find wordpress/plugins/power-schedule-manager \
    wordpress/plugins/baocache \
    wordpress/themes/cupdienlamdong \
    -type f -name '*.php' -exec php -l {} \; >/dev/null
fi

if command -v python3 >/dev/null 2>&1; then
  python3 -c "import ast, pathlib; ast.parse(pathlib.Path('metrics/server.py').read_text(encoding='utf-8'))"
fi

if ! grep -q 'baocache_skip_reason' nginx/nginx.conf \
	|| ! grep -q 'bypass_reasons' metrics/server.py \
	|| ! grep -q 'class BaoCache_Cloudflare' wordpress/plugins/baocache/includes/class-cloudflare.php \
	|| ! grep -q 'class BaoCache_Frontend_Metrics' wordpress/plugins/baocache/includes/class-frontend-metrics.php \
	|| ! grep -q 'frontend_timing_enabled' wordpress/plugins/baocache/includes/class-settings.php \
  || ! grep -q 'BAOCACHE_CLOUDFLARE_API_TOKEN' docker-compose.yml; then
  echo "BaoCache runtime diagnostics or Cloudflare audit configuration is incomplete." >&2
  exit 1
fi

if ! grep -q 'baocache/v1' wordpress/plugins/baocache/includes/class-csp.php \
  || ! grep -q 'csp_collect_reports' wordpress/plugins/baocache/includes/class-settings.php \
  || ! grep -q 'policy_snapshot' wordpress/plugins/baocache/includes/class-csp.php \
  || ! grep -q 'recommendations' wordpress/plugins/baocache/includes/class-csp.php \
  || ! grep -q 'verify_endpoint' wordpress/plugins/baocache/includes/class-purge.php; then
  echo "BaoCache CSP evidence and policy diff configuration is incomplete." >&2
  exit 1
fi

echo "Docker, PHP, Nginx, Redis and repository configuration are valid."
