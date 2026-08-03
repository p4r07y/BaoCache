=== BaoCache ===
Contributors: bao
Tags: nginx, redis, performance, coolify, cache
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 0.3.0-beta.77.1
License: GPL-2.0-or-later

BaoCache is a standalone WordPress performance engine for Nginx FastCGI cache,
Redis Object Cache and Coolify. The core is site- and theme-neutral: adapters
only add metadata and known constraints, while site-specific behavior belongs
to explicit Site Overrides.

== Description ==

BaoCache deliberately does not implement a second HTML page cache. Nginx remains responsible for FastCGI page cache and Redis Object Cache remains responsible for the WordPress object cache.

Features:

* Environment dashboard for Nginx, Redis, PhpRedis and the active theme.
* Safe WordPress bloat controls: emoji, oEmbed discovery, guest Dashicons and admin heartbeat interval.
* Native WordPress `defer` script strategy by registered script handle.
* Opt-in Delay JavaScript for safe, independent handles; scripts run on first interaction or a bounded fallback timer.
* Context-aware CSS/JavaScript dequeue rules that retain required dependencies.
* Smart asset scopes for URL, post type, and the presence or absence of a shortcode or block.
* Measured Asset Insights: largest local asset, third-party sources, duplicate registered sources and head-script placement.
* Preconnect, DNS-prefetch and preload controls.
* LCP image assistance for an administrator-verified WordPress attachment URL.
* Optional public TTL sent through `X-Accel-Expires`; it never replaces a TTL already sent by another plugin.
* Redis object-cache flush plus an authenticated exact-URL FastCGI purge when
  deployed with the bundled Nginx image.
* Optional same-site sitemap preload with a bounded Docker-only warm queue.
* Optional Cloudflare read-only audit using a Coolify environment secret; it verifies a token and reads one Zone only.
* One-field Google Analytics / Tag Manager setup: `G-` uses GA4 and `GTM-` uses GTM without editing a theme.
* Consent-aware, CSP-safe Analytics bootstrap plus optional Microsoft Clarity and bounded client-side dataLayer events.
* Optional static Content Security Policy manager: Report-Only first, explicit source directives, and integration-aware origin suggestions without nonces or a second header.
* Analytics migration checklist for duplicate GTM/GA4 evidence with audit-only acknowledgement; BaoCache never deletes or disables external tags automatically.
* Read-only external injector detection for BaoCache, first-party Google Tag Gateway, known public plugin markers and unknown Google-tag evidence; it stores no HTML and never disables another injector.
* Opt-in, consent-gated adapter hooks for WooCommerce, common form completion, OneSignal bridge and Power Schedule Manager; adapters add metadata only and never make a plugin-specific branch mandatory in the core engine.
* Optional, rate-limited browser Resource Timing summaries with no resource URLs, query strings, cookies, IP addresses or visitor identifiers.
* Safe WordPress hardening for RSS policy, REST user enumeration, generic login errors, X-Pingback and public generator metadata.
* Dedicated Security workspace with visible WordPress/CSP/infrastructure boundaries and opt-in same-site Asset Version Masking.
* Grouped control-plane navigation for WordPress, Warmup, Diagnostics, Logs and Cloudflare, while settings remain one atomic save transaction.
* Hardening Verification reports the active RSS, REST, Generator, Feed Link and X-Pingback policies without inventing a security score.
* Optional Public Response Probe checks the public homepage, feed and REST users endpoint without storing response bodies, URLs or identifiers.
* Stores up to ten sanitized probe snapshots and records the aggregate result in the administrator Activity Log.
* Detects PASS-to-WARN/INFO hardening regressions between consecutive probes without treating timing noise as a failure.
* Optional scheduled Public Response Probe with a manual PASS baseline; scheduled checks use the same same-site, no-cookie policy and never store URLs or response bodies.
* Probe History shows manual/scheduled source, response time and regression/improvement counts; administrators can acknowledge a warning without deleting its snapshot.
* Render Blocking Optimization imports PageSpeed/Lighthouse JSON, maps resources to WordPress handles where possible, previews native defer eligibility and supports opt-in async CSS with noscript fallback.
* Critical CSS can be staged only after validation and a theme/plugin fingerprint check; stale or malformed CSS is never inlined.
* Context QA checks a path, handle and safe-preview flags before a render-blocking strategy is applied; Strategy Ledger records applied and rolled-back entries without URLs or query strings.
* Staging Compatibility QA records manual PASS/FAIL results for menus, forms, maps, analytics, chat, checkout, login and rollback without pretending to be an automated browser test.
* Per-rule Compatibility Gates keep production defer, async CSS and delay disabled until the handle has QA PASS and a verified rollback; staging/development remain available for testing.

== Compatibility ==

Uses WordPress public hooks and APIs. It is theme-independent and designed for Blocksy, Astra and standard WordPress themes. Test script and asset rules on a staging copy before production.

== Installation ==

1. Deploy this repository with Coolify, or copy the `baocache` directory to `wp-content/plugins/`.
2. Activate BaoCache.
3. Open BaoCache in the WordPress admin menu.

== FAQ ==

= Does BaoCache replace Redis Object Cache? =

No. Redis Object Cache stays responsible for persistent object caching.

= Does it purge Nginx FastCGI cache? =

Yes, only with this repository's bundled Nginx image. It builds a pinned Open
Source purge module and provides a generated, Docker-only authenticated endpoint.
BaoCache purges exact same-site URLs; it does not expose a wildcard or whole-cache
purge control.

= Does it create unused CSS automatically? =

No. High-quality remove-unused-CSS needs page-specific rendering analysis. BaoCache keeps the first release deterministic and local.

= Does Asset Version Masking change the real asset version? =

No. It only changes the public same-site `?ver=` presentation when explicitly
enabled. Asset Inventory and WordPress registrations retain the real version so
diagnostics and invalidation remain reliable. Third-party URLs are untouched.

= Can it delay every JavaScript file? =

No. Delay JavaScript is intentionally opt-in. BaoCache leaves a handle untouched
when it has inline/localized code, module or conditional attributes, or another
queued asset depends on it. Test selected analytics, chat and widget handles while
logged out on staging before enabling them for visitors.

== Changelog ==

= 0.3.0-beta.77.1 =

* Added Settings → Advanced Data Retention. Configuration is retained by default, diagnostics history is optional, and destructive full removal requires an explicit opt-in warning.
* Added a registry-based UninstallManager. Runtime queue, locks, transients and cron are always cleared; only exact BaoCache-owned targets are eligible for removal.
* Added BaoCache Database Health and idempotent self-repair for the schema marker, missing configuration and owned cron. It reports truthfully that this release requires no custom tables or indexes.
* Added a read-only Autoload Options Inspector that reports option names and sizes but never deletes WordPress or third-party data.

= 0.3.0-beta.77 =

* Added Automatic Critical Images for the front page: a bounded public DOM scan ranks same-site image candidates without claiming they are LCP or storing raw HTML.
* Candidate apply is reversible and promotes only the existing safe runtime behaviour: image preload, eager loading, asynchronous decoding and high fetch priority.
* Every apply runs a cache-bypassed public post-change probe. Missing image, preload or fetchpriority output causes an immediate automatic rollback; later manual rollback is protected against stale settings.

= 0.3.0-beta.76 =

* Added read-only Analytics Inspector. Injector cards show status, owner candidate, bounded evidence, heuristic confidence, risk and a manual next step for BaoCache, Cloudflare Google Tag Gateway candidates, known plugin markers and unknown theme/wp_head snippets; no HTML, URLs or ownership claims are persisted.

= 0.3.0-beta.75 =

* Reorganized the CSP workspace into Basic, Sources, Evidence, Diagnostics and Advanced tabs, keeping routine policy controls compact while fingerprints, retention, canary trend and operator remediation remain available on demand.

= 0.3.0-beta.74 =

* Added an opt-in Security workspace with WordPress hardening, CSP and infrastructure boundary visibility.
* Added same-site Asset Version Masking: keep `?ver=`, remove it, or replace it with a short deterministic `?v=` fingerprint. Third-party URLs and the diagnostic inventory remain unchanged.

= 0.3.0-beta.73 =

* Added a bounded 30-day history of scheduled CSP canary acknowledgements, retaining only fingerprint and timing metadata.
* Added per-step operator completion state and a sanitized 300-character note bound to the current canary trend fingerprint; a new sample gets a new context and no action is automated.

= 0.3.0-beta.72 =

* Added a bounded seven-sample scheduled canary trend with PASS/WARN/FAIL counts, latency average and failure streak.
* Added an operator remediation checklist driven by observed CSP metadata; it never applies changes, purges or rolls back automatically.

= 0.3.0-beta.71 =

* Added metadata-only CSP probe regression comparison against the previous sample.
* Added explicit acknowledgement for failed scheduled CSP canaries; acknowledgement never changes policy or rolls back Enforce.
* Clarified Compatibility QA empty state: “Chưa test” is no longer shown as 0/8 PASS before a result exists.

= 0.3.0-beta.70 =

* Added bounded 30-day CSP post-enforcement probe history with manual/scheduled source labels.
* Added opt-in daily CSP canary checks for Enforce mode; canaries never auto-rollback or alter policy.
* Added export-safe CSP probe history metadata and compact history UI.
* Preserved the active BaoCache workspace after QA save/reset and other reloads via a local tab deep-link.

= 0.3.0-beta.69 =
* Adds a metadata-only post-enforcement public CSP probe. It checks the public response for one matching Enforce policy, reports conflicts and never stores policy text or response bodies.
* Adds an explicit manual rollback action from Enforce to Report-Only with confirmation and an operator activity record. Probe failures never auto-demote CSP.

= 0.3.0-beta.68 =
* Adds an Enforce deployment checklist and fingerprint-bound operator acknowledgement. A Report-Only to Enforce transition is rejected unless the acknowledgement is checked and the public-policy readiness gate passes.
* Applies the same Enforce validation to AJAX and standard WordPress settings saves. BaoCache still never changes mode itself, writes Cloudflare/Nginx configuration, or treats zero reports as a compatibility guarantee.

= 0.3.0-beta.67 =
* Adds public CSP policy conflict diagnostics from Header Inspector. It distinguishes an exact BaoCache policy, an external/unknown policy, multiple policies and simultaneous Enforce/Report-Only headers without storing policy text.
* Adds a read-only staged Enforce readiness checklist. It never changes CSP mode, never suppresses evidence and never writes Cloudflare/Nginx headers; an operator must explicitly select Enforce and save after testing.

= 0.3.0-beta.66 =
* Adds daily and on-demand CSP evidence retention review: aggregate Report-Only reports and dismissed recommendations expire after 30 days; immutable applied-change ledger records expire after 90 days without changing the active policy.
* Adds export-safe CSP evidence metadata only (counts, retention policy, timestamps and a policy fingerprint). Raw violation groups, blocked origins and report bodies are not exported.

= 0.3.0-beta.65 =
* Retains bounded, secret-free FastCGI purge deployment evidence from the non-mutating Verify endpoint: timestamp, HTTP code, state and result only.
* Separates local configuration from a verified live endpoint in Cache and Site Diagnostics, with compact code-specific remediation. It never retains the endpoint, cache key, response body, headers, password or visitor request data.

= 0.3.0-beta.64 =
* Makes exact FastCGI URL purge an in-place, nonce-protected action: the button shows progress, a compact toast and an inline HTTP diagnostic without reloading the dashboard or creating a durable admin banner.
* Keeps endpoint verification non-mutating, and gives a failed purge a direct “Verify endpoint” action so Docker DNS, Nginx module and generated-secret faults are diagnosable where the action happened.
* Adds a conservative CSP recommendation rollback ledger. Rollback is available only while the current policy fingerprint and the exact source list still match the original apply; later manual, Cloudflare or Nginx-owned changes are blocked for operator review.
* Adds a Header Inspector CSP ownership observation. It can identify an exact BaoCache policy match or report an external/unknown header, but never infers a Cloudflare owner or overwrites an edge policy.

= 0.3.0-beta.63 =
* Fixes Docker-internal FastCGI purge authentication: the Nginx worker can read the private htpasswd file, stale hashes are regenerated from the generated secret, and the purge key now uses a private request header rather than an encoded query argument.
* Adds an administrator-only non-mutating “Verify endpoint” check. HTTP 404 for an impossible key proves the live Nginx purge module, Docker DNS and secret are working before a real URL is purged.
* Adds evidence-based CSP source recommendations: only repeat Report-Only evidence for a safe HTTPS origin can be added or dismissed. BaoCache never recommends inline/eval/data, never enables CSP/Enforce, and records a policy snapshot after an explicit apply.

= 0.3.0-beta.62 =
* Adds opt-in aggregate CSP violation evidence through a same-origin REST endpoint; only directive, normalized blocked origin, disposition, count and timestamps are retained for 30 days.
* Adds CSP policy fingerprints and directive-level diff between the current and previous saved policy. BaoCache never promotes Report-Only to Enforce automatically.
* Adds an administrator-only clear action and keeps reports out of public exports and activity details.

= 0.3.0-beta.61 =
* Adds an optional static CSP manager with Report-Only as the safe default, Enforce mode after review, source controls for scripts, styles, images, fonts, connections, frames and workers, and automatic origins for enabled Analytics/Clarity/adapters.
* Keeps CSP nonce-free for FastCGI-cached HTML, skips the response when another origin CSP header is present, and warns administrators to choose one CSP owner (BaoCache, Cloudflare or Nginx).
* Adds an Analytics migration checklist and duplicate-tag acknowledgement. The acknowledgement is audit metadata only; it never deletes, disables or hides an external tag.

= 0.3.0-beta.60 =

* Splits public Analytics evidence into external GTM containers and external GA4 Measurement IDs, and verifies that opted-in Auto Events/adapter bridge files were emitted to public HTML.
* Does not call this evidence a vendor conversion or realtime receipt.

= 0.3.0-beta.59 =

* Adds Analytics Adapter integrations. Detected, enabled adapters only push bounded event names to dataLayer after consent; BaoCache stores no form data, URL, email or visitor event.

= 0.3.0-beta.58 =

* Adds a bounded public Analytics evidence probe: public bootstrap/config, CSP source directives and unexpected public Google IDs.
* Reports duplicate/unknown public GA/GTM IDs for administrator review without logging a response body or identifiers.
* Keeps GTM verification honest: public HTML evidence is separate from browser runtime, GTM Preview and GA4 Realtime.

= 0.3.0-beta.57 =

* Analytics UX: provider-aware labels, live G-/GTM- detection and Clarity naming.
* Added local configuration diagnostics and safe injected-script preview/copy.
* Added explicit supported Auto Events list and neutral Coming Soon integration card.
* Added inline CSP guidance pointing to Cloudflare Transform Rules.

= 0.3.0-beta.56 =
* Adds Analytics & Tracking under Integrations: GA4, GTM auto-detection and Microsoft Clarity injection without theme edits.
* Uses a local external bootstrap and a meta configuration element rather than executable inline code; invalid IDs are rejected before saving.
* Adds opt-in, consent-gated dataLayer events for outbound links, mailto, tel, downloads, search, comment submit, scroll, time on page and 404.
* Does not claim vendor-side connection, realtime receipt, Search Console OAuth or Google Ads integration without a verified API flow.

= 0.3.0-beta.55 =
* Adds an hourly automated evidence review for saved compatibility gates.
* Evidence older than the 90-day policy is marked expired and remains blocked in production until re-approved.
* Adds a manual “Rà soát ngay” action and a safe operator audit entry containing only gate counts, handles and environment metadata.
* Fixes a PHP output-boundary bug that could expose admin method source and adds a compact default dashboard visibility guard.
* Adds progressive layout guards so a delayed admin script does not expand every operational panel into one long page.

= 0.3.0-beta.54 =
* Enforces a 90-day, 200-entry retention policy for per-rule evidence history.
* Adds export-safe history fields and includes the policy in protected JSON reports; raw URLs and asset content remain excluded.
* Adds stale-gate acknowledgement and manual expiry cleanup. Acknowledgement is audit-only and never unblocks production.
* Hardens the admin workspace layout so Assets, Resource Hints and Warmup render as full-width operational workspaces.

= 0.3.0-beta.53 =
* Adds bounded evidence-aware gate history with previous/current evidence references and dependency/asset change flags.
* Adds a per-rule Diff Drawer; it shows QA, rollback, environment and hash references without raw URLs or asset content.
* Keeps production blocked for stale gates and requires an explicit re-approval after evidence changes.

= 0.3.0-beta.52 =
* Adds immutable per-rule evidence references using dependency and asset fingerprints; raw source URLs are never stored.
* Automatically marks a gate stale and blocks it in production when the handle inputs, dependencies or asset fingerprint change.
* Shows evidence reference and stale status in the Render Blocking gate table.

= 0.3.0-beta.51 =
* Adds a per-handle gate for defer, async CSS and delay strategies.
* Production now requires QA PASS plus a verified rollback for each rule; staging and development remain testable.
* Adds gate controls to the Render Blocking panel and records only handle, strategy, state, environment and plugin version.

= 0.3.0-beta.50 =
* Expands Assets and Resource Hints into full-width workspaces so inventory tables and hint fields are not compressed by the settings grid.
* Adds a staging-only Compatibility QA checklist with persistent PASS/FAIL/skip states, environment/version metadata and safe activity logging.
* Covers menu, form, map, analytics/consent, chat, checkout, login and rollback checks; no URLs, tokens or browser data are stored.

= 0.3.0-beta.49 =
* Centralizes render-blocking context eligibility for runtime and administrator QA, including hard-stop contexts such as admin, login, preview, checkout and authenticated sessions.
* Adds Context QA controls for path, handle and safe-preview flags, with persistent activity entries for PASS/BYPASS decisions.
* Adds a bounded Strategy Ledger with applied/rollback state and stale-handle diagnostics when configured strategies are absent from the latest Asset Inventory.

= 0.3.0-beta.48 =
* Adds Probe Detail History with check-level PASS/WARN/INFO values and regression/improvement diffs.
* Adds an audit-driven Render Blocking Optimization module for Lighthouse/PageSpeed JSON, handle mapping, native WordPress defer preview, URL/context exclusions and opt-in async CSS.
* Adds validated Critical CSS staging with theme/plugin/Customizer fingerprint invalidation; no CSS is generated or enabled automatically.

= 0.3.0-beta.47 =
* Adds Probe History UI for the retained ten sanitized snapshots.
* Adds administrator-only Alert Acknowledgement; acknowledging a regression marks it as reviewed and writes a safe activity entry without deleting or suppressing the underlying snapshot.
* Records whether each probe was manual or scheduled and exposes a stable probe identifier for the acknowledgement request.

= 0.3.0-beta.46 =
* Adds an opt-in Public Response Probe schedule (hourly, six-hourly or daily) with WordPress Cron validation and a lock to prevent overlapping probes.
* Adds a manual baseline action that can only use a probe with no WARN/FAIL checks; the baseline is tagged with the WordPress environment so a staging baseline is not silently reused in production.
* Adds scheduled-probe visibility to Site Diagnostics and keeps feed discovery, generator and REST discovery checks consistent with the manual probe.

= 0.3.0-beta.45 =
* Adds consecutive-probe diffing for hardening checks with explicit regression and improvement lists.
* Shows a warning toast and activity outcome only when a check moves from PASS to a non-PASS state.

= 0.3.0-beta.44 =
* Adds a ten-entry sanitized Public Response Probe history with last-probe visibility in Hardening Verification.
* Records only pass counts, check labels/states, response timing and timestamp; no URL, body, cookie or token is retained.

= 0.3.0-beta.43 =
* Adds an administrator-triggered Public Response Probe for RSS status, REST user enumeration, Generator metadata, feed links, REST discovery and X-Pingback.
* Limits the probe to same-site requests, strips cookies, follows no redirects and returns only sanitized checks and timing.

= 0.3.0-beta.42 =
* Adds a runtime-aware Hardening Verification panel with explicit PASS, WARN and INFO states.
* Distinguishes policy verification from public response verification and directs administrators to Header Inspector after purge.

= 0.3.0-beta.41 =
* Adds RSS Policy wording with Keep Feed (Recommended), Redirect to Homepage and Return 410 Gone choices.
* Adds Remove Feed Links and Remove REST API Discovery Link controls without disabling REST API itself.
* Renames the hardening group to Discovery & Privacy, clarifies the editor control and adds iconized Wordfence boundary status.
* Splits the admin navigation into Performance, WordPress, Operations and Integrations panes while keeping one atomic configuration save.

= 0.3.0-beta.40 =
* Adds RSS Feed policy controls: keep, redirect to the home page or return 410 Gone.
* Blocks public REST API user enumeration without disabling the rest of the WordPress REST API.
* Adds optional generic login errors and removes the X-Pingback response header from PHP responses.
* Clarifies the Generator Tag hardening control and adds a grouped Wordfence responsibility boundary in the UI.

= 0.3.0-beta.39 =
* Collapses secondary Hardening and Performance Headers panels into accessible disclosures, reducing dashboard length while preserving full controls.
* Adds an enabled-control summary and sticky Save action so the configuration state and primary action remain visible during long settings sessions.

= 0.3.0-beta.38 =
* Adds opt-in WordPress hardening for XML-RPC, self pingback, trackbacks, RSD, WLW, shortlink/generator output, attachment pages, author enumeration, Application Passwords and the file editor.
* Detects an active Wordfence installation and explains that firewall, malware scan, login security, 2FA, CAPTCHA and brute-force protection remain Wordfence responsibilities.
* Adds a Performance Headers view documenting the Nginx-managed security headers without duplicating them from PHP.

= 0.3.0-beta.37 =
* Adds Critical Resource Diagnostics for the configured LCP assistance, preload output and connection hints.
* Flags preload URLs that cannot produce a valid `as` resource class and redundant preconnect/DNS-prefetch origins without claiming an LCP or render-blocking measurement.

= 0.3.0-beta.36 =
* Removes the inline Browser Resource Timing configuration object. The external collector now receives its public endpoint and nonce through data attributes, reducing its CSP surface.

= 0.3.0-beta.35 =
* Adds a signed 30-minute Delay JavaScript preview for the current administrator only, with local handle load/failure and generic JavaScript error observation.
* Preview is never enabled for guests, is not persisted or exported, and can be ended immediately from the Delay JavaScript panel.

= 0.3.0-beta.34 =
* Adds an administrator-only clear action for all retained Browser Resource Timing summaries and its collection rate limit.

= 0.3.0-beta.33 =
* Adds disabled-by-default Browser Resource Timing summaries to the Asset Analysis tab: same-site/external hostname, resource class, request count, duration total and transfer total.
* Limits accepted public samples to one per site every 15 minutes, 20 groups per sample and 96 stored samples. No resource URL/path, query, cookie, IP, user ID, referrer, page URL or user-agent is sent or stored.
* Labels the data as a browser sample rather than a waterfall, Core Web Vitals score or render-blocking verdict.

= 0.3.0-beta.32 =
* Makes Asset Inventory scans deterministic: the internal Nginx request carries a short-lived, one-time scan token and only that verified frontend response can write the result.
* Shows a precise failure when the frontend did not complete the scan, and keeps the detailed message visible longer instead of falling back to a generic error.
* Preserves ordinary anonymous inventory capture when no administrator-initiated scan is running.

= 0.3.0-beta.31 =
* Adds real 24-hour FastCGI BYPASS diagnostics from the private Nginx observer: method, query string, excluded path, session cookie or Authorization header.
* Stores only a bounded reason category; it never records the request URL, query, cookie value, authorization value or visitor identifier.

= 0.3.0-beta.30 =
* Extends the read-only Cloudflare audit with verified Zone type and paused state, and correctly interprets Development Mode from Cloudflare's seconds value.
* No additional Cloudflare API permission or mutation endpoint is introduced.

= 0.3.0-beta.29 =
* Adds optional Cloudflare read-only audit from Coolify environment: verifies an API token and reads one Zone record without a wp-admin token field.
* Has no Cloudflare purge or configuration-mutation endpoint and excludes the token from settings, JSON export and activity logs.
* Reports only verified Zone state, type, paused state and Development Mode; Development Mode is interpreted from Cloudflare's runtime seconds value.

= 0.3.0-beta.28 =
* Extends Header Inspector with Expires, Vary and X-Accel-Expires plus response-level recommendations for HTTP errors, FastCGI status, Cloudflare mode, compression and slow responses.
* Recommendations link only to a relevant BaoCache section and are recorded through the existing safe activity trail; they never alter cache or Cloudflare settings.

= 0.3.0-beta.27 =
* Turns measured Asset Insights into safe suggestions: a large local asset can create a URL-scoped rule draft, and a head-script candidate can be added to a Defer draft.
* Suggestions never save settings, unload an asset or alter strategy automatically; review, preview and the normal Save action remain required.

= 0.3.0-beta.26 =
* Adds measured Asset Insights from a scanned frontend registry: largest local asset, third-party source count, duplicate registered sources and head-script placement.
* Distinguishes observed script placement from browser render-blocking, and does not label assets unused without browser timing or a Chromium worker.

= 0.3.0-beta.25 =
* Adds shortcode and block-aware asset rules, including “only load when it exists” scopes that unload a selected asset where its shortcode/block is absent.
* Keeps dependency protection and revision rollback; content conditions are evaluated on the rendered frontend request, not guessed from Asset Inventory.

= 0.3.0-beta.24 =
* Adds LCP image assistance: an explicitly verified same-site attachment URL receives `loading=eager` and `fetchpriority=high` within the selected scope.
* Does not claim automatic LCP detection or transform CSS backgrounds; preload is emitted only for the verified image URL.

= 0.3.0-beta.23 =
* Adds opt-in Delay JavaScript for named handles, with interaction/timeout loading and strictly sequential execution through a same-site external runner (CSP-friendly; no inline runner).
* Adds compatibility guards: scripts with inline/localized code, module or conditional attributes, and dependency roots are left untouched; logged-in visitors are never delayed.

= 0.3.0-beta.22 =
* Adds an Nginx 503 maintenance fallback while WordPress/PHP is starting or unavailable, with no-store, noindex and Retry-After headers.
* Ships a separate Coolify maintenance resource and documented Traefik error middleware for the proxy-level “No available server” window.

= 0.3.0-beta.21 =
* Adds gated 24-hour, 7-day and 30-day Runtime History ranges from durable hourly observations.
* A range stays in collection mode until it has at least four observations and 75% real time coverage; only then does it render FastCGI HIT and Redis latency sparklines.

= 0.3.0-beta.20 =
* Makes “Run Diagnostics” an actual protected runtime observation: refreshes verified FastCGI/Redis data, saves the current hourly snapshot and records the action.
* Multiple manual runs in the same hour replace that hour's observation instead of inventing a trend.

= 0.3.0-beta.19 =
* Adds a bounded, durable Activity Feed for configuration saves, Header Inspector, Asset Scan, Warm Queue, FastCGI purge and Redis flush.
* Records timestamp, administrator, outcome and safe operational context only; it never records tokens, Docker secrets or URL query strings.
* Includes the activity audit trail in the protected JSON export.

= 0.3.0-beta.18 =
* Registers BaoCache custom Cron schedules during plugin bootstrap, validates scheduling with WordPress and shows scheduler errors in Warm Queue runtime.
* Preserves the public HTTPS scheme on Docker-internal Asset Scan and Warm Queue requests, preventing a WordPress canonical HTTPS redirect from bypassing frontend capture.
* Fixes Asset Explorer inner pane visibility so Inventory, Rules, Dependencies and Analysis switch correctly.

= 0.3.0-beta.17 =
* Turns Header Inspector into a structured, per-response PASS/WARN/FAIL diagnostic for FastCGI, Cloudflare, cache, compression and HTTP headers.
* Adds current Site Diagnostics for Redis, FastCGI observer, OPcache, Warm Queue cron, protected purge configuration and the runtime metrics collector.
* Makes dashboard KPI cards keyboard-accessible navigation and makes Quick Actions execute diagnostics, header checks, asset scans and warm queue actions.
* Starts a bounded 30-day hourly observation store for FastCGI, Redis, OPcache and Warm Queue. No timeline is displayed until sufficient real observations exist.

= 0.3.0-beta.16 =
* Improves the internal Asset Scanner with an Nginx health probe and specific actionable errors.
* Adds verified on-disk sizes for same-site files, explicit source, loaded-on sample and a safe URL-prefix rule draft action.

= 0.3.0-beta.15 =
* Introduces the first Asset Explorer: verified internal scan, grouped Inventory, search/filter, Rules, Dependencies and Analysis tabs.
* Adds only measured inventory signals; payload size, waterfall and unused-asset claims remain deferred until browser timing or a rendering worker is available.

= 0.3.0-beta.14 =
* Saves BaoCache settings through a protected same-origin request with no page reload; the primary button briefly confirms success.
* Uses short right-corner toast feedback and an immediate sitemap action only after a Warm Queue change.

= 0.3.0-beta.13 =
* Replaces persistent configuration-success banners with non-blocking toast feedback and an inline saved-button state.
* Shows an immediate “Đọc sitemap ngay” action only when Warm Queue was enabled or its sitemap changed; sitemap queueing now runs without a page reload.

= 0.3.0-beta.12 =
* Adds a bounded dependency map that shows both direct dependencies and the assets that require each handle.

= 0.3.0-beta.11 =
* Adds an explicit settings-save notice for Warm Queue setup.
* Removes invalid nested forms from the Asset revision history, allowing the WordPress Settings API form to submit reliably.

= 0.3.0-beta.10 =
* Detects a valid same-site XML sitemap before warming: configured URL, `sitemap_index.xml`, WordPress core `wp-sitemap.xml`, and common sitemap paths.
* Shows the actual detected sitemap or a clear runtime error in the warm queue panel.

= 0.3.0-beta.9 =
* Uses the common same-site `sitemap_index.xml` default for scheduled warmup.
* Documents and supports Nginx protection for direct GET access to WordPress All Settings; normal settings POST requests remain available.

= 0.3.0-beta.8 =
* Rebuilds the dashboard as a compact operational view: structured KPIs, System Health, Quick Actions and actionable recommendations.
* Removes duplicate runtime presentation, reduces sidebar/header footprint and fixes the FastCGI purge form structure.

= 0.3.0-beta.7 =
* Adds sitemap preload scheduling and a rate-limited warm queue (1, 2 or 5 URLs per minute).
* Limits parsing to same-site sitemap URLs and retries failed warm requests at most twice.

= 0.3.0-beta.6 =
* Adds an authenticated, Docker-only FastCGI exact-URL purge control when the bundled Nginx image is deployed.
* Queues safe post, homepage and category invalidation after content changes; it never deletes cache files from PHP.

= 0.3.0-beta.5 =
* Added sampled URL-context checks to asset rule preview.

= 0.3.0-beta.4 =
* Moved the version indicator to the dashboard header.

= 0.3.0-beta.3 =
* Added dependency-aware asset rule preview.

= 0.3.0-beta.2 =
* Refined dashboard identity and author information.

= 0.3.0-beta.1 =
* Added sampled frontend asset inventory and five-revision configuration history.

= 0.2.0 =
* Added private 24-hour FastCGI cache aggregation, same-origin header inspection, Site Health Redis verification and JSON diagnostic export.

= 0.1.4 =
* Added verified Redis runtime latency, memory and hit-ratio diagnostics.

= 0.1.3 =
* Updated product positioning and infrastructure compatibility UI.

= 0.1.2 =
* Rebuilt the settings flow with an app-style sidebar and focused sections.

= 0.1.1 =
* Added a compact tabbed control flow and dashboard asset cache-busting.

= 0.1.0 =
* First standalone release.
