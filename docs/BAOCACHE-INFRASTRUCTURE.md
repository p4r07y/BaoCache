# BaoCache infrastructure contract

BaoCache is deployed inside the same Coolify Docker Compose application as WordPress. It does not require an exposed Redis port, a custom Docker network, or a Cloudflare token.

## Current compatible topology

```text
Coolify proxy → nginx:80 → wordpress:9000 → redis:6379
                              │
                              └→ Managed MySQL (TLS)

baocache-secrets ─→ private secret volume (read-only in WordPress/Nginx)
nginx ─→ private runtime volume ← baocache-metrics
```

| Component | Contract |
| --- | --- |
| Coolify | Owns the application network. Compose must not declare an external/custom network. |
| Nginx | Is the only FastCGI HTML/page-cache layer, adds `X-FastCGI-Cache`, and contains the pinned Open Source cache-purge module. |
| WordPress/PHP | Runs BaoCache and PhpRedis. It reads only the existing `WP_REDIS_*` configuration. |
| Redis | Is private to the Compose application; password stays in a Docker secret. |
| BaoCache | Never stores, exports or displays the Redis password or the generated purge password. |

## What v0.2.0 verifies at runtime

BaoCache connects using the same WordPress Redis configuration and reports:

- successful Redis connection;
- measured PHP-to-Redis round-trip latency;
- Redis `keyspace_hits`/`keyspace_misses` ratio when counters exist;
- `used_memory` and `maxmemory` when Redis exposes them.

The values are cached in WordPress for one minute to avoid turning the admin dashboard into monitoring load.

## FastCGI visibility boundary

`X-FastCGI-Cache` tells an individual client request whether it was HIT, MISS, BYPASS, EXPIRED, STALE, UPDATING or REVALIDATED. It does **not** provide an aggregate 24-hour hit ratio.

BaoCache v0.2 uses a private `baocache-metrics` observer. On first boot,
`baocache-secrets` creates a cryptographically random token in a separate
private named volume; WordPress receives that volume read-only and the token is
never placed in Git, an image layer or a Coolify variable. Nginx records only a
timestamp and cache status; the observer stores a 24-hour SQLite window. It has
no public port and WordPress reads only its aggregate JSON response. No client
IP, cookie, authorization header or request URL is retained.

The transient Nginx event file is compacted after 8 MB; the observer ingests it every five seconds before compaction. The 24-hour aggregate is kept in SQLite on the private named volume.

## FastCGI purge boundary

The Nginx image compiles the pinned `nginx-modules/ngx_cache_purge` source as a
dynamic module for Nginx 1.28. The `/__baocache/purge` endpoint accepts only
`POST`, requires a generated Basic Auth password and is never exposed in the
BaoCache UI as a wildcard/full-cache control. WordPress calls it by the Docker
service name and preserves the public `Host` header, so it deletes only the
exact FastCGI key for a same-site canonical URL.

The password is created once in the private secret volume. The matching
`htpasswd` hash is regenerated if it becomes stale and is readable only by
containers mounted to that private volume, including the unprivileged Nginx
worker. Neither value can be set, read or exported from wp-admin. If the named
volume is intentionally removed, new credentials are generated at the next
deployment.

This purge does not invalidate Cloudflare edge cache. BaoCache deliberately has
no Cloudflare token; configure a conservative HTML edge TTL or purge the URL in
Cloudflare separately when immediate edge invalidation is required.

## Deployment verification

1. Deploy with Coolify as a Docker Compose application, domain attached only to `nginx:80`.
2. Confirm `baocache-secrets`, `wordpress`, `redis`, `nginx` and
   `baocache-metrics` health checks are healthy.
3. In WordPress Admin → BaoCache, confirm Redis says `Connected` and the technical report contains live latency/memory values.
4. Inspect a public response header `X-FastCGI-Cache` separately. A second request to a cacheable, anonymous page should normally become `HIT`.
5. In BaoCache → Cache, click **Xác minh endpoint** first. A non-mutating 404
   confirms the live purge image/module, Docker DNS and generated secret.
6. Purge that exact URL. The action reports its HTTP code beside the button and
   as a short toast; it does not reload the dashboard or create a persistent
   admin notice. `401/403` indicates a generated-secret/volume mismatch,
   `5xx` indicates the Nginx image/module path, and `404` means that exact key
   was not cached and is therefore a successful no-op.
7. A subsequent anonymous
   request should return `MISS` or `EXPIRED`, then return `HIT` again.

BaoCache retains deployment evidence only after the non-mutating verification:
check time, HTTP code, bounded result and state. It never retains the internal
endpoint, purge URI/cache key, response body, headers or generated secret in
WordPress, exports or activity logs.

## CSP evidence lifecycle

When CSP Report-Only collection is enabled, BaoCache retains aggregate groups
for 30 days only. A group contains directive, normalized blocked origin,
disposition, count and timestamps—never page URL, path, query, referrer, IP,
cookie or report body. Dismissed recommendations also expire after 30 days.
Applied recommendation ledger records are retained for 90 days so that a recent
change can be reviewed without silently changing policy. A daily WordPress Cron
review and the manual **Rà soát retention** action prune expired records; JSON
exports include only counts, timestamps, retention metadata and a policy
fingerprint, not raw CSP evidence.

## CSP owner and Enforce boundary

Header Inspector records only whether a public CSP exists, its disposition,
the count of observed CSP header types and whether one header exactly begins
with BaoCache's current local policy. It does not persist policy text and never
attributes an external policy to Cloudflare, Nginx or another plugin. Seeing an
external policy, duplicate header type, or both Enforce and Report-Only keeps
the staged Enforce checklist in review state. The checklist is diagnostic only:
BaoCache never promotes Report-Only to Enforce, rewrites a public response, or
writes Cloudflare/Nginx configuration.

## Enforce transition control

Switching from Report-Only to Enforce requires a checked operator
acknowledgement for the current policy fingerprint and a passing public-policy
readiness gate. The same validation applies to the JavaScript save action and
the standard WordPress settings endpoint, so it cannot be bypassed by posting
directly to `options.php`. The gate requires Report-Only observation, aggregate
evidence enabled, no retained reports and no public owner conflict. This is a
deployment guard, not a compatibility guarantee: the operator must still test
staging and can manually return to Report-Only if production has a problem.

## Post-enforcement public probe

After Enforce is enabled, the CSP panel can make a same-site, no-cookie public
request. The probe stores only a timestamp, HTTP status, response time,
observed disposition, whether a single policy matched the current local
fingerprint and whether a conflict was detected. It never stores the policy
header, response body, URL path, query string or visitor data. A non-2xx
response, missing/mismatched policy or duplicate owner is reported as FAIL;
the probe does not change settings.

An administrator can explicitly confirm **Quay lại Report-Only** from the same
panel. This writes the normal settings option, enables aggregate collection,
records an operator warning and leaves all other CSP source settings intact.
There is no automatic rollback, so a production incident remains an
operator-controlled decision.

## Scheduled CSP canary

Beta70 adds an opt-in daily canary while CSP is explicitly in Enforce mode.
WordPress Cron performs the same bounded same-site probe as the manual action
and stores only status, timing, disposition, match/conflict flags, outcome and
source. The canary is disabled automatically when Enforce is disabled and has
no rollback or policy-writing path. Operators review the compact 30-day probe
history and Activity Log before making any manual rollback decision.

Beta71 compares the latest two scheduled probe records and exposes only a
metadata diff. A failed scheduled canary can be acknowledged by an authorized
operator; the acknowledgement is tied to that record fingerprint and becomes
stale when a new failure is recorded. This is an audit action, not an
automatic recovery mechanism.

Beta72 exposes a seven-sample scheduled-canary trend and a read-only
remediation checklist. Counts, latency and failure streak are computed from
retained probe metadata; no health score is inferred. The checklist directs
operators to inspect public headers, staging flows or manually select
Report-Only when appropriate, and has no mutation or automatic rollback path.

Beta73 adds a bounded 30-day acknowledgement history for scheduled canary
failures. It retains only the probe fingerprint, timestamps, outcome and
source, and never stores policy text, response bodies or URLs. Each remediation
step can also be marked complete with a sanitized short operator note. That
state is keyed to the current trend fingerprint, so a newer sample naturally
creates a fresh review context; no remediation, purge or rollback is automatic.

Beta74 adds a dedicated Security workspace and an opt-in same-site asset
version masking filter. It can remove `?ver=` or replace it with a deterministic
short `?v=` fingerprint while leaving the diagnostic inventory's real version,
third-party URLs and cache configuration untouched. Security status is split
between WordPress hooks BaoCache can control and Nginx/Coolify/Cloudflare
boundaries that require runtime verification or operator action.

## Deploy maintenance behaviour

Nginx now starts once the WordPress container has started; it no longer waits
for PHP's health check before exposing `/healthz`. If PHP is still booting or
returns a 502/503/504, Nginx returns a short Vietnamese maintenance page with
HTTP 503 and a `Retry-After` header. This keeps the application routable during
most of the startup window.

For the smaller interval where Traefik has no healthy Nginx backend at all,
deploy the independent maintenance resource and attach the Traefik error
middleware described in [Coolify maintenance setup](COOLIFY-MAINTENANCE.md).
