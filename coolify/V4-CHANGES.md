# V4 file list

## Coolify Docker normalization

- Removed the fixed Compose project name and custom `backend` network so Coolify
  owns the application network lifecycle.
- Replaced the PHP-FPM health check's PID assumption with a real local port check.
- Added explicit shutdown grace periods and disabled bundled-theme syncing in the
  cron worker.
- Removed duplicated and malformed directives from `99-wordpress.ini`.
- Standardized Dockerfiles on BuildKit syntax, deterministic copy permissions and
  WP-CLI download retries.
- Extended local validation to reject custom Compose networks and malformed PHP
  configuration.

## Redis and FastCGI cache completion

- Builds Redis Object Cache 2.8.0 from WordPress.org with a pinned SHA-256.
- Compiles PhpRedis 6.3.0 and verifies the extension during the image build.
- Synchronizes the plugin and `object-cache.php` drop-in into new and existing
  WordPress volumes, then activates the diagnostic plugin when WordPress exists.
- Enables graceful Redis fallback and namespace-scoped cache flushing.
- Expands FastCGI bypass matching and no longer caches redirects or 404 pages.

## Power Schedule Manager 0.31.0 integration

- Bundles the complete plugin at `wordpress/plugins/power-schedule-manager`.
- Uses root-relative Docker build contexts so Coolify can build this repository
  directly without a parent `coolify-prebuild` directory.
- Adds separate Docker Secrets for VNAppMob `gold` and `exchange_rate` scopes.
- Uses a 60-second FastCGI micro-cache for plugin-driven public pages while
  retaining the ten-minute default for ordinary WordPress content.
- Keeps XoSoAPI credentials and webhook signatures server-side and records safe
  per-endpoint diagnostics in WordPress administration.

## Added

- `php/docker-entrypoint-v4.sh`
- `php/conf.d/10-opcache.ini`
- `php/php-fpm.d/zz-wordpress.conf`
- `redis/Dockerfile`
- `redis/redis.conf`

## Reworked

- `docker-compose.yml`
- `php/Dockerfile`
- `php/check-managed-db.php`
- `php/conf.d/99-wordpress.ini`
- `nginx/Dockerfile`
- `nginx/nginx.conf`
- `nginx/default.conf`
- `README.md`
- `.env.example`
- `.dockerignore`
- `.gitignore`
- `scripts/validate.sh`

## Main changes

- Removed every local MariaDB component.
- Added DigitalOcean Managed MySQL variables and runtime CA secret.
- Installed the DigitalOcean CA into the PHP container trust store.
- Added an explicit CA/TLS Managed MySQL diagnostic.
- Added PHP 8.5-FPM, OPcache and FPM pool tuning.
- Restored production Nginx gzip, security rules and FastCGI cache.
- Restored Redis LFU cache configuration without persistence.
- Added repeatable plugin synchronization for an existing WordPress volume.
- Added the repository-managed `cupdienlamdong` Blocksy child theme, automatic
  synchronization and safe activation when the Blocksy parent is installed.
- Moved integration credentials out of Docker Compose and into encrypted plugin
  administration settings.
- Added a persistent Coolify-generated WordPress table prefix.
- Retained a single dedicated WordPress cron worker and Coolify health checks.

## Automatic security update

- `docker-compose.yml`: eight persistent Coolify-generated WordPress keys/salts,
  PID limits, init processes and bounded container logs.
- `nginx/nginx.conf`: authorization-aware FastCGI cache bypass.
- `nginx/default.conf`: HSTS and browser security headers, hidden PHP signature,
  and `Set-Cookie` cache protection.
- `.env.example`: local placeholders for automatically generated secrets.
- `README.md`: documents every automatic security setting and affected file.

## CA compatibility fix

- `php/docker-entrypoint-v4.sh`: accepts direct PEM, quoted Coolify multiline PEM,
  escaped-newline PEM and legacy base64 PEM; validates the result with OpenSSL.
- `php/Dockerfile`: adds OpenSSL for certificate validation.
- `README.md`: corrects the Coolify Multiline instructions.

## Plugin synchronization fix

- `php/docker-entrypoint-v4.sh`: ensures WordPress exists in a new named volume,
  then synchronizes bundled plugins directly to the live plugin directory.
- `php/Dockerfile`: accepts folders and automatically extracts plugin ZIP files.
- `README.md`: adds plugin synchronization logs and troubleshooting.

## Wordfence Extended Protection

- `php/conf.d/99-wordpress.ini`: enables per-directory `.user.ini` processing.
- `nginx/default.conf`: denies public access to `.user.ini` and
  `wordfence-waf.php`, blocks sensitive backup/config files, and prevents PHP
  execution in uploads and cache directories.
- `nginx/nginx.conf`: trusts only internal proxy networks when restoring the real
  visitor address for Wordfence.
- `README.md`: documents the Wordfence Nginx optimization flow.

## Compose correction and WordPress hardening

- `docker-compose.yml`: retains valid YAML spacing, escapes `$_SERVER` for Docker
  Compose, keeps `MYSQLI_CLIENT_SSL`, disables unfiltered HTML and production
  debug logging, enables cache, retains minor core updates, and sets trash expiry.
- The unsupported `MYSQL_SSL_CA` constant from the submitted draft was not used;
  the CA remains mounted as a Docker secret and TLS is enabled with
  `MYSQL_CLIENT_FLAGS`.

## Nginx FastCGI compatibility

- `nginx/default.conf`: only uses status values accepted by
  `fastcgi_cache_use_stale`. Unlike `proxy_cache_use_stale`, the FastCGI
  directive does not accept `http_502` or `http_504`.

## Automatic Redis credentials

- `docker-compose.yml`: replaces manual `REDIS_PASSWORD` and the fixed `wp`
  namespace with persistent Coolify-generated values.
- `.env.example`: adds local equivalents of the Coolify magic variables.
- `README.md`: documents `SERVICE_PASSWORD_64_REDIS` and
  `SERVICE_USER_REDISPREFIX`.

## Build-time Nginx validation

- `nginx/Dockerfile`: runs `nginx -t` during image build with a temporary local
  upstream, because the Compose DNS name `wordpress` only exists at runtime.
  The final image restores `wordpress:9000`.
- `scripts/validate.sh`: explicitly rejects `http_502` and `http_504` when they
  are used with `fastcgi_cache_use_stale`.
# BaoCache (0.1.0)

- Added the standalone `baocache` plugin. It is independent of the electricity
  schedule plugin and theme, and is activated automatically by the Coolify
  deployment configuration.
- BaoCache complements rather than replaces Nginx FastCGI caching and Redis
  Object Cache: diagnostics, safe WordPress reductions, asset controls, resource
  hints, optional cache TTL suggestions and object-cache flush are local only.

## BaoCache purge foundation (0.3.0-beta.6)

- Builds pinned `ngx_cache_purge` support into the Nginx 1.28 image.
- Generates metrics and purge credentials once in a private named volume; no
  corresponding Coolify variable or repository secret is required.
- Adds a Docker-only authenticated exact-URL purge endpoint and a BaoCache admin
  control. Wildcard and whole-zone purge are not exposed.
- Queues invalidation for changed posts, the home page and post categories; the
  WordPress cron worker now runs every minute.
