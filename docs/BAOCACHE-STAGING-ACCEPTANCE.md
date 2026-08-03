# BaoCache staging acceptance

Run these checks after every infrastructure release, on a staging domain before
deploying the same commit to production. They validate observed behaviour; they
do not expose Docker or Coolify secrets.

## 1. Run the public cache smoke test

Use a cacheable public page. Do not use a logged-in browser session, an admin
URL, a URL with a query string, or a cart/checkout/account URL.

```sh
sh scripts/smoke-test.sh https://staging.example.com
```

It makes two anonymous requests and requires the second response to have
`X-FastCGI-Cache: HIT`. The first response can be `MISS`, `EXPIRED` or already
`HIT`; it is not a 24-hour hit ratio.

If the header is absent, the domain may be routing to a different proxy. If the
second request is `BYPASS`, open BaoCache → Diagnostics → FastCGI BYPASS and fix
the reported reason. Never make query strings or authenticated requests
cacheable merely to make this test pass.

## 2. Verify BaoCache diagnostics

From wp-admin → BaoCache → Dashboard, confirm:

- Redis reports **Connected** with a latency value.
- PHP OPcache reports **Enabled**.
- FastCGI Observer receives events.
- Warm Queue Cron has a next run when the feature is enabled.
- FastCGI Purge reports **Protected**.

Use Header Inspector on the exact staging homepage. A FastCGI `HIT` is expected
after the smoke test. `CF-Cache-Status: DYNAMIC` is valid when Cloudflare is
proxying but HTML edge caching has not been enabled.

## 3. Confirm safe operations

1. Purge one non-homepage public URL through BaoCache.
2. Request it twice anonymously; it should return `MISS`/`HIT` (or equivalent
   fill status then `HIT`).
3. Flush Redis object cache, then confirm the site and wp-admin still work.
4. If Warm Queue is enabled, read the detected sitemap and enqueue a small
   batch. Confirm the queue moves without errors in the activity log.
5. Enable maintenance mode and verify the public maintenance page is `503`, has
   `Retry-After`, `Cache-Control: no-store`, and `X-Robots-Tag: noindex`; then
   disable it again.

## 4.1 Verify hardening changes individually

Only enable the hardening controls that match the site’s integrations. On a
staging copy, verify XML-RPC clients, mobile/app integrations, pingbacks,
trackbacks, Application Passwords, attachment URLs and author archives before
enabling their corresponding controls. Confirm plugin/theme editing remains
available through deployment Git rather than wp-admin when File Editor is
disabled.

If Wordfence is active, confirm BaoCache shows the boundary note and leave
firewall, malware scan, login security, 2FA, CAPTCHA and brute-force tests to
Wordfence. Header Inspector must still show the Nginx security headers without
duplicate PHP headers.

## 5. Check the frontend conservatively

Test a representative homepage, post, archive and the business-critical plugin
page in an incognito browser. If asset rules, defer or delay JavaScript changed,
also test menus, forms, analytics consent, maps and checkout-like actions.
Rollback the individual BaoCache rule if a regression appears; do not turn on
global minification or defer-all as a workaround.

## Release gate

A production deploy is ready only when the source prebuild is green, this
acceptance check passes, and the backup/rollback path is known. Record the
deployed commit and the test timestamp in the deployment change note.
