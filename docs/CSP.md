# Cloudflare Content Security Policy

Cloudflare currently owns the public CSP headers for this deployment. BaoCache
beta61 can also emit a static policy, but **only one owner may be enabled**.
Choose Cloudflare or BaoCache; do not add a second CSP in Nginx. Browsers
intersect multiple policies, so an old restrictive Cloudflare policy would
continue blocking resources even if the origin policy allowed them.

In Cloudflare **Rules → Transform Rules → Modify Response Header**, replace the
current `Content-Security-Policy-Report-Only` value with the single line in
`cloudflare-csp-report-only.txt`. This is the place to add Analytics origins;
do not add a second policy in `nginx/default.conf`.

If DevTools still reports `script-src 'self'` or `connect-src 'self'`, the
Cloudflare rule has not been replaced yet. Replace the full existing Report-Only
header value; do not append another CSP header. The sample deliberately contains
both `script-src` and `script-src-elem` because an omitted `*-elem` directive
falls back to `script-src` in browsers.

For BaoCache Analytics, the minimum additions are:

```text
script-src-elem https://www.googletagmanager.com https://www.clarity.ms
frame-src https://www.googletagmanager.com
```

This repository uses the more precise `script-src-elem` directive. If the
Cloudflare rule only has `script-src`, put the same two hosts there instead.

If BaoCache is selected as the owner, open **Cache → Security · Content Security
Policy** in wp-admin, keep **Report-Only** while reviewing the response, then
enter each additional origin in its matching source field. BaoCache automatically
adds the minimum origins for enabled Analytics, Clarity and consent-gated
adapters; YouTube, Vimeo and Cloudflare Insights are intentionally not added
without evidence and can be entered explicitly when used.

In beta62, **Collect aggregate violation reports** is opt-in. It adds a
same-origin `report-uri` only in Report-Only mode and stores directive,
normalized blocked origin, disposition, count and timestamps for at most 30
days. No page URL, path, query, referrer or visitor identifier is retained.
Review the policy fingerprint/diff before changing sources. In beta63, BaoCache
can show a source candidate only after repeat Report-Only evidence: at least two
reports separated by one minute, or three reports. It maps only a safe HTTPS
origin to the matching directive field. It never recommends `inline`, `eval`,
`data`, `blob`, a scheme-only source or the site host. **Thêm source** is an
explicit administrator action and keeps Report-Only; **Bỏ qua** applies only to
the current policy fingerprint. BaoCache never promotes Enforce automatically.

The sample policy also includes the required `connect-src` entries for Google
Analytics and Clarity, including `analytics.google.com`. Keep them only if the
corresponding integration is enabled. `frame-src` is needed for the optional
GTM `<noscript>` iframe; the normal deferred bootstrap does not require
`unsafe-inline` for its own code.

Keep the existing small enforced policy while testing:

```text
object-src 'none'; base-uri 'self'; frame-ancestors 'self'; upgrade-insecure-requests
```

Test the report-only policy on public pages, maps, analytics, PWA installation,
OneSignal subscription and Turnstile. Investigate every unexpected origin before
adding it. After a clean observation period, the report-only value can replace
the enforced policy.

BaoCache Analytics itself does not require executable inline JavaScript. The
sample keeps `unsafe-inline` only because the current WordPress/Blocksy frontend
still emits inline scripts and styles; the Report-Only phase identifies those
before an enforced policy is tightened. `script-src-attr 'none'` still blocks
inline event-handler attributes. A nonce-per-request policy must not be used
while FastCGI serves cached HTML because the nonce would be reused.
