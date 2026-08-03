# BaoCache product roadmap

## Product position

**BaoCache — WordPress Performance Control Plane for Nginx FastCGI, Redis and Docker.**

BaoCache is not a WP Rocket clone. Its job is to make a real infrastructure
stack observable, controllable and safe from WordPress, without claiming data it
cannot verify.

## Product architecture — general engine, optional adapters

BaoCache must install cleanly on a new WordPress website. Its core never contains
a domain name, a theme name, a custom-plugin slug or a project-specific route as
a condition for an optimization.

- **Evidence first:** recommendations derive from observed routes, registered
  asset handles and dependencies, HTTP/MIME/cache responses, DOM markers,
  frontend probes, network-origin observations and CSP reports.
- **Generic core:** an optimization works from WordPress APIs and observed
  evidence even when no adapter is active.
- **Adapters are additive:** WooCommerce, Blocksy, Elementor, Bricks or any
  custom-plugin adapter may add metadata and known constraints only. An adapter
  cannot become a required branch in the engine or silently enable a rule.
- **Site Overrides are explicit:** site-specific routes, selectors, exclusions,
  CDN origins and business rules belong to an exported Site Override profile;
  they are never shipped as BaoCache defaults.
- **Safe promotion:** every frontend-affecting change has a snapshot, diff,
  canary/post-change probe and a reversible rollback path. BaoCache never
  mutates Cloudflare, Nginx or another vendor unless the user grants the
  integration permission and explicitly confirms the action.

## Dashboard rules

The dashboard must separate, never blend:

| Area | What it may show |
| --- | --- |
| Configuration | Deterministic configuration checks and compatibility results. |
| Runtime health | Verified FastCGI, Redis, PHP and WordPress runtime signals. |
| Field data | Core Web Vitals only when a real CrUX/RUM source is configured. |
| Lab data | Lighthouse results only after a recorded local/remote run. |

No combined “95/100 performance score” will be shown. No HIT ratio, LCP, TTFB,
Redis latency or warm queue count will be shown until BaoCache collects it from a
verified source.

## Roadmap status — beta75

| Trạng thái | Phạm vi | Thống kê |
| --- | --- | --- |
| Đã triển khai | Các mốc beta được ghi nhận từ beta29 đến beta50: runtime metrics, Cloudflare read-only audit, hardening/probe, Asset Explorer, Resource Timing, Render Blocking, Critical CSS, Context QA và Staging Compatibility QA | **22 mốc beta** |
| Đã triển khai | beta51 — Per-rule Compatibility Gates: gate riêng cho defer, async CSS và delay; production chỉ chạy khi QA PASS + rollback verified | **1 mốc** |
| Đã triển khai | beta52 — Immutable Rule Evidence & Stale Gate Invalidation: hash evidence không đảo ngược và tự chặn gate cũ khi dependency/fingerprint thay đổi | **1 mốc** |
| Đã triển khai | beta53 — Evidence-aware Rule History & Diff Drawer: lịch sử bounded theo rule, diff nhóm thay đổi và re-approval rõ ràng | **1 mốc** |
| Đã triển khai | beta54 — Evidence Retention Policy, Export-safe History & Stale Gate Acknowledgement: giữ lịch sử 90 ngày/200 bản ghi, export đã lọc và acknowledgement chỉ để audit | **1 mốc** |
| Đã triển khai | beta55 — Automated Evidence Expiry Review & Operator Audit Trail: review gate theo giờ, hết hạn sau 90 ngày, ghi audit an toàn và giữ production block | **1 mốc** |
| Đã triển khai | beta56 — Analytics Bootstrap & Consent-safe Events: tự nhận GA4/GTM từ một ID, inject CSP-safe, Clarity opt-in và dataLayer event có consent | **1 mốc** |
| Đã triển khai | beta57 — Analytics UX & Configuration Diagnostics: nhãn theo provider, live ID detection, event inventory, preview/copy và hướng dẫn CSP Cloudflare | **1 mốc** |
| Đã triển khai | beta58 — Public Analytics Evidence: probe HTML public, CSP response header và Google ID ngoài cấu hình để phát hiện double tracking | **1 mốc** |
| Đã triển khai | beta59 — Analytics Adapter Hooks: adapter opt-in, consent-gated cho WooCommerce, form, OneSignal bridge và Power Schedule Manager; chỉ đưa event chuẩn hoá vào dataLayer | **1 mốc** |
| Đã triển khai | beta60 — Analytics Adapter Preview & Event Evidence: phân biệt GTM/GA4 ngoài cấu hình và xác minh bootstrap event/adapter có trong HTML public, không giả nhận vendor conversion | **1 mốc** |
| Đã triển khai | beta61 — CSP Manager & Analytics Migration Checklist: CSP Report-Only/Enforce tĩnh, source controls, auto-origins theo integration và acknowledgement audit-safe cho duplicate tags | **1 mốc** |
| Đã triển khai | beta62 — CSP Violation Evidence & Policy Diff: báo cáo tổng hợp opt-in, retention 30 ngày, fingerprint directive và clear action; không tự promote Enforce | **1 mốc** |
| Đã triển khai | beta63 — Evidence-based CSP Source Recommendations: chỉ đề xuất HTTPS origin có Report-Only evidence lặp lại; áp dụng/bỏ qua là thao tác quản trị rõ ràng, không tự thêm source hay chuyển Enforce | **1 mốc** |
| Đã hoàn thiện | Core engine và guardrails: snapshot/diff/gate/canary/rollback, CSP Report-Only workflow, header/cache/runtime evidence | **~95% core / ~98% reliability** |
| Ưu tiên kế tiếp | Tự động phát hiện, recommendation có thể áp dụng, và UX thương mại theo evidence | **8 nhóm giá trị trực tiếp** |
| Stable candidate | Cần staging acceptance thật, rollback thực tế, multi-page scope và kiểm thử install sạch | **~85–90% sẵn sàng kiến trúc** |

**Tổng mốc beta đã triển khai: 35.**

Các tỷ lệ là đánh giá phạm vi sản phẩm, không phải PageSpeed, health score hay
phần trăm hoàn thành tự động. Stable vẫn yêu cầu QA thật trên staging và ít nhất
một rollback thực tế thành công.

## Quyết định: Google OAuth

BaoCache sẽ bổ sung **Google OAuth read-only sau lớp evidence runtime**, không
đặt OAuth client secret hay refresh token trong plugin repository hoặc form
wp-admin. Client secret và encryption key sẽ thuộc Coolify secret; refresh
token chỉ được lưu mã hóa, không export/log. Luồng ban đầu chỉ liệt kê GA4
property/web data stream và Search Console property đã xác minh, sau đó quản
trị viên xác nhận trước khi BaoCache điền Measurement ID. Google Ads tách thành
module sau vì còn cần developer token. OAuth chỉ thay phần integrations/reporting,
không thay metadata SEO, schema hay sitemap engine của Rank Math.

The dashboard is an operational view: compact KPI cards, verified System Health,
safe Quick Actions and actionable recommendations. It must not repeat the same
runtime state in decorative cards or present theme information as a core KPI.
Transient success feedback must use a short non-layout toast; durable banners
are reserved for actionable warnings, compatibility failures and errors.

## Commercial roadmap — automation after beta76

**beta76 is the final planned expansion of the current evidence layer.** It
identifies externally injected first-party Google Tag Gateway paths and likely
owner boundaries without editing Cloudflare. Afterwards, evidence becomes shared
infrastructure rather than a feature stream.

| Milestone | Direct customer value | Safe delivery boundary |
| --- | --- | --- |
| **beta77 — Automatic Critical Images (delivered)** | Ranks same-site front-page image candidates from bounded DOM evidence and can safely apply `fetchpriority`, eager loading and preload. | Confidence is not an LCP claim; raw HTML is not stored; apply must pass a public post-change probe or is rolled back immediately. |
| **beta77.1 — Data Retention & BaoCache Database Health (delivered)** | Keeps configuration by default across reinstall, provides explicit full removal, self-checks owned schema/config/cron, and reports autoload size read-only. | Runtime is always disposable; exact cleanup registry only; no wildcard option deletion, no `DROP TABLE`, and no WordPress/third-party repair. |
| **beta78 — Automatic Resource & Font Hints (delivered)** | Evidence-driven origin recommendations with deduplication, bounded apply and rollback. | Exact preload/font-display changes require separate asset/CSS evidence; no blind preload. |
| **beta79 — Third-party Optimizer** | Classify third-party scripts by origin, cost and page context; offer delay/consent/context rules. | Handle/dependency-aware only; preview, staging QA and rollback required. |
| **beta80 — Commerce Optimizer** | Generic cart/checkout detection plus optional WooCommerce metadata for fragment, asset and cache-bypass recommendations. | Checkout/authenticated routes are protected by default; no adapter-only core logic. |
| **beta81 — Theme & Builder Adapters** | Optional Blocksy, Elementor and Bricks metadata/constraints for clearer recommendations. | Core uses observed handles/DOM; adapters add labels and exclusions only. |
| **beta82 — Cloudflare Integration** | Read-only audit evolves into explicit, opt-in URL purge and cache-rule diagnostics. | Coolify secrets only; least privilege; no DNS/WAF/SSL/APO mutation without separate confirmation. |
| **beta83 — Optimization Advisor** | Risk-ranked recommendations with expected mechanism, evidence, scope, preview and one-click safe apply. | Deterministic rules first; any AI explanation is advisory and cannot bypass gates. |
| **Stable release candidate** | Clean-install onboarding, Site Overrides import/export, multisite support statement and commercial UX polish. | Test matrix across fresh WordPress, common themes/builders, cache layers and rollback paths. |

### Definition of “automatic”

Automation means BaoCache can discover, explain and prepare a safe scoped
change. It does **not** mean it can guess an LCP image, remove an asset as
unused, rewrite third-party code, or alter an external provider without
evidence and confirmation.

## v0.2 — Runtime visibility

- **Beta delivered:** Redis connection, latency, memory and hit/miss metrics;
  same-origin header inspector; WordPress Site Health test; JSON diagnostic export.
- **Stable delivered:** private Nginx event feed, 24-hour FastCGI HIT/MISS/BYPASS
  aggregation, and cache-bypass visibility. The observer has no public port,
  generates its own first-boot token in a private volume, persists only
  timestamps/statuses, and deletes events older than 24 hours.

## v0.3.x — Diagnostics, Inspector & action-first dashboard

- **Beta delivered:** Header Inspector now reports one verified response with
  PASS/WARN/FAIL, HTTP status/time, FastCGI, Cloudflare status, Cache-Control,
  Age, ETag, Last-Modified, compression, Content-Length, Server-Timing and
  CF-Ray when present.
- **Beta delivered:** Site Diagnostics separates current Redis, FastCGI observer,
  PHP OPcache/JIT, Warm Queue cron, protected FastCGI purge configuration and
  metrics-collector state. It is not a merged health score.
- **Beta delivered:** dashboard cards navigate to their related surface; Quick
  Actions run Header Check, Asset Scan and Warm Queue work instead of acting as
  navigation shortcuts.
- **Beta delivered:** custom Cron schedules are registered during bootstrap and
  validated before scheduling; Docker-internal frontend requests preserve the
  public HTTPS scheme so canonical redirects cannot suppress an asset capture.

## v0.3.x — Safe Asset Manager

- **Beta delivered:** sampled frontend asset inventory with registered
  dependencies, dependency-aware rule preview and a five-revision configuration
  history with one-click rollback. Revision restore forms are deliberately kept
  outside the WordPress Settings API form so asset history cannot interrupt a
  normal configuration save.
- **Beta delivered:** bounded visual dependency map, showing both direct
  dependencies and handles that would block a safe unload.
- **Beta delivered:** Asset Explorer foundation: Docker-internal frontend scan,
  searchable/filterable source groups, Inventory/Rules/Dependencies/Analysis
  views and exportable sampled inventory.
- **Beta delivered:** same-site on-disk asset sizes, explicit source grouping and
  safe URL-sample rule drafts. The inventory labels a page as a sample, not as
  site-wide scope, until a multi-page scan exists.
- **Still required before stable:** richer conditional rule builder and browser
  timing capture for verifiable payload size, waterfall and render-blocking
  analysis. BaoCache will not label an asset “unused” until it has evidence.

## v0.4 — Historical Runtime Metrics

- **Foundation delivered:** bounded WordPress options storage records a real
  hourly observation (FastCGI rolling-24h state, Redis latency/hit rate,
  OPcache and Warm Queue/Cron) for 30 days. The collector has no public port.
- **Foundation delivered:** a bounded durable Activity Feed records each
  administrator action, its outcome and safe context. It excludes token values,
  generated Docker secrets and URL query strings.
- **Beta delivered:** Run Diagnostics refreshes and stores an actual current-hour
  observation on demand; repeated runs replace that hour rather than creating
  an artificial timeline.
- **Beta delivered:** 24h/7d/30d Runtime History remains in collection mode
  until it has four samples and 75% observed coverage. Only ready ranges render
  FastCGI HIT and Redis latency sparklines from those real points.
- **Beta delivered:** FastCGI BYPASS diagnostics aggregate verified Nginx
  observer categories (`method`, `query`, `path`, `cookie`, `authorization`) for
  the last 24 hours. The observer stores no URL, query string, cookie value,
  Authorization value or visitor identifier.

## v0.5 — Frontend Control

- Defer by handle (already available as the first safe control).
- **Beta delivered:** Delay until interaction with compatibility exclusions for
  inline/localized, conditional/module and dependency handles.
- **Beta delivered (beta.35):** a signed, 30-minute administrator-only Delay
  preview runs outside public FastCGI cache and shows local load/failure plus
  generic error/rejection observation. It sends and persists no browser errors.
- Still required: staging evidence across representative frontend flows before
  treating a delayed handle as production-safe.

## v0.6 — Critical resources

- **Beta delivered:** Critical Resource Diagnostics verifies what BaoCache can
  actually emit: supported preload resource classes, redundant connection hints
  and the configuration state of the administrator-provided LCP assist. It
  never labels an image as LCP without an external measurement.
- Still required: LCP image detection from a real field/lab measurement,
  lazy-load exclusion evidence and measured resource-hint recommendations.

### WordPress hardening and security boundary

- **Beta 42 delivered:** Hardening Verification exposes individual policy states
  for RSS, feed links, REST user enumeration, REST discovery, Generator metadata
  and X-Pingback. It deliberately avoids a single security score; public response
  confirmation still belongs to Header Inspector after a purge.
- **Beta 43 delivered:** Public Response Probe performs an administrator-triggered
  same-site check of the homepage, configured feed and REST users endpoint. It
  returns only status, selected header/HTML findings and aggregate timing; it
  does not store response bodies, cookies, URLs or visitor identifiers.
- **Beta 44 delivered:** Probe results retain up to ten sanitized snapshots and
  appear in Activity Logs as an aggregate pass count. History is intentionally
  not a public monitor and contains no URL or response body.
- **Beta 45 delivered:** Consecutive snapshots are diffed by check label. Only a
  PASS-to-WARN/INFO transition is a regression; improvements are reported
  separately and response timing changes alone do not trigger an alert.
- **Beta 46 delivered:** Public Response Probe can run on an opt-in hourly,
  six-hourly or daily schedule. A baseline is created manually from a probe
  with no WARN/FAIL checks, so a transient error cannot silently become the
  comparison standard. Scheduled checks use the same same-site/no-cookie
  policy, tag baselines with the WordPress environment to protect staging vs
  production, and expose the next run/regression state in Site Diagnostics.
- **Beta 47 delivered:** The retained probe snapshots now have a compact History
  table with source, response time and change counts. Administrators can
  acknowledge a regression without deleting it; acknowledgement is stored
  separately and recorded in the safe Activity Log.
- **Beta 48 delivered:** Probe History now exposes check-level details and diffs.
  Render Blocking Optimization imports a real Lighthouse/PageSpeed report,
  maps URLs to the sampled WordPress registry, previews native defer safety,
  supports URL/context exclusions and opt-in async CSS with a noscript fallback.
  Critical CSS is only staged after validation and a theme/plugin/Customizer
  fingerprint match; no automatic CSS generation or broad HTML regex is used.
- **Beta 40 delivered:** RSS Feed policy supports Keep, redirect to the home page
  and 410 Gone. Public `/wp-json/wp/vN/users` REST routes return a neutral 404
  while administrators with `list_users` retain access to the endpoint.
- **Beta 40 delivered:** login errors can be reduced to a generic message and
  X-Pingback is removed from both WordPress header arrays and the PHP response.
  The Generator Tag is removed together with the existing shortlink discovery
  control; asset query-string versions remain separate cache-busting metadata.
- **Beta delivered:** opt-in XML-RPC, self-pingback, trackback, RSD, WLW,
  shortlink/generator, attachment-page, author-enumeration, Application
  Password and file-editor controls. Attachment and author redirects remain
  disabled by default because they can change SEO/content behavior.
- **Beta delivered:** Wordfence detection is informational. BaoCache does not
  duplicate firewall, malware scan, login security, 2FA, CAPTCHA or brute-force
  modules.
- **Beta delivered:** security response headers are managed by the bundled Nginx
  layer and shown as a policy/diagnostic surface. PHP does not add duplicate
  headers to cached responses.

### UI/UX control-plane pass

- **Beta 41 delivered:** sidebar navigation is grouped into Performance,
  WordPress, Operations and Integrations. WordPress hardening, Warmup,
  Diagnostics, Logs and Cloudflare no longer compete for the Cache pane.
- **Beta 41 delivered:** RSS copy now states the compatibility-safe default;
  feed links and the REST API discovery link can be removed independently.
  The REST API itself remains available.
- **Beta delivered:** secondary Hardening and Performance Headers settings use
  accessible disclosures with a live enabled-count/status summary. The Save
  action remains visible during long settings sessions and still uses the
  non-layout toast feedback flow.
- Still required: a compact mobile navigation mode and an Asset Explorer detail
  drawer; neither should add guessed runtime data or duplicate settings.

## v0.7 — Cache orchestration

- **Foundation delivered:** a pinned Open Source Nginx cache-purge module,
  Docker-only authenticated exact-URL purge, and queued invalidation of changed
  posts, home page and category pages.
- **Beta delivered:** optional same-site sitemap discovery, a persistent warm
  queue, 1/2/5 URL-per-minute rate limiting, bounded retries, runtime status and
  valid-XML sitemap detection across common SEO-plugin/Core paths.
- **Current validation:** saving the queue configuration reports success
  explicitly without reloading the page. When an administrator enables the
  queue or changes its sitemap, BaoCache offers the separate "Đọc sitemap ngay"
  action in that toast. This avoids an unannounced crawl while a setting is
  being edited.
- Cache analytics from verified Nginx data.

## v0.8 — Cloudflare opt-in

- Read-only diagnostics first: surface `CF-Cache-Status`, cache headers and
  edge-cache state without a token.
- **Beta delivered:** optional Coolify-environment audit verifies an API token
  and reads one Cloudflare Zone. It has no purge or configuration-mutation
  endpoint, no wp-admin token input, and never places the token in settings,
  exports or activity logs.
- **Beta delivered:** the audit also reports verified Zone type, paused state and
  Development Mode without expanding token permissions beyond `Zone → Zone → Read`.
- Optional URL purge only after an operator supplies a Coolify secret. Tokens
  are never entered in wp-admin, written to exports or application logs.
- BaoCache will not manage DNS, WAF, SSL, Workers, APO or Cache Everything.

## v1.0 release bar

Only call BaoCache 1.0 after it has automatic compatibility protection, rollback,
runtime diagnostics, smart purge/warming, stable delayed JavaScript, critical
image optimization, import/export configuration, and a clear multisite support
statement.

## Production release process

- **Beta delivered:** repository prebuild validation is available through
  `scripts/validate.sh` and the GitHub Actions `Coolify prebuild` workflow. It
  renders Compose, validates shell/PHP/Python syntax, and builds the same Nginx,
  PHP/WordPress, Redis, metrics and secrets images Coolify consumes.
- Still required before declaring a release stable: a staging acceptance run
  against a deployed stack, including anonymous FastCGI MISS→HIT, exact-URL
  purge, Redis connection, asset scan, warm queue and maintenance fallback.
- **Delivered:** `scripts/smoke-test.sh` and the staging acceptance checklist
  provide a repeatable public FastCGI HIT release gate without reading Docker
  secrets. The remaining work is to execute and record that gate on a real
  staging deployment.

## P1 — Asset Explorer reliability

- **Delivered (beta.32):** administrator-triggered scans use a short-lived
  one-time header token through internal Nginx. An unrelated frontend request
  cannot replace the pending result, and a completed inventory is explicitly
  marked as internally verified.
- File size remains on-disk evidence and “head script” remains a placement
  signal, not a render-blocking verdict.

## v0.3.x — beta49 Render Blocking Strategy Ledger & Context QA

- **Delivered:** runtime defer/async eligibility now uses one shared context
  evaluator. Admin, login, preview, checkout, feed, REST, AJAX and authenticated
  requests remain hard stops by default.
- **Delivered:** administrator Context QA checks a path and optional handle with
  explicit preview flags, returning PASS/BYPASS reasons without changing settings.
- **Delivered:** Strategy Ledger records the strategy, reason, context and
  rollback state; stale configured handles are surfaced when the latest sampled
  Inventory cannot verify them.
- **Next:** staging QA of menu, forms, maps, analytics and chat with the ledger
  and rollback path; no automatic strategy is enabled from an audit import.

## v0.3.x — beta50 Staging Compatibility QA

- **Delivered:** Assets and Resource Hints now render as full-width workspaces;
  inventory tables keep their readable columns and hint fields no longer inherit
  a compressed settings column.
- **Delivered:** a persistent manual QA checklist covers menu/navigation, forms,
  maps, analytics/consent, chat widgets, checkout, login/authenticated sessions
  and rollback/recovery.
- **Delivered:** QA results are explicitly PASS/FAIL/skip, tagged with the
  current environment and BaoCache version, and logged without URLs, tokens,
  cookies or browser identifiers.
- **Gate:** do not enable a new defer/async/delay rule in production until the
  relevant staging rows are PASS and a rollback path has been exercised.
- **Gate:** beta51 implements per-rule compatibility gates; production promotion
  still requires a real staging QA pass and verified rollback for each enabled strategy.

## v0.3.x — beta51 Per-rule Compatibility Gates

- **Delivered:** every configured defer, async CSS and delay handle has an
  independent gate with QA state and rollback verification.
- **Delivered:** production runtime bypasses a rule unless QA is PASS and the
  rollback checkbox has been verified; staging/development remain available for
  compatibility testing.
- **Delivered:** the Render Blocking panel exposes gate status and records only
  handle, strategy, state, environment and plugin version.
- **Delivered (beta52):** every gate stores only an immutable evidence reference
  plus dependency/asset fingerprints; raw source URLs are never persisted.
- **Delivered (beta52):** runtime compares the current fingerprints on every
  eligibility check and marks changed or legacy gates **Stale**, blocking them in
  production until the rule is reviewed and saved again.
- **Delivered (beta53):** bounded history records previous/current evidence
  references, QA, rollback, environment and changed groups without raw URLs.
- **Delivered (beta53):** each rule gets a keyboard-accessible Diff Drawer before
  re-approval; production promotion remains explicit and never automatic.
- **Delivered (beta54):** history is pruned to 90 days and 200 entries, exports
  contain only sanitized evidence metadata, and operators can acknowledge stale
  gates without changing the production block.
- **Delivered (beta55):** an hourly review marks evidence older than the retention
  policy as expired, keeps production blocked, and records only aggregate counts,
  handles and environment metadata in the operator audit trail. A manual review
  action is available from the gate panel.
- **Delivered (beta59):** opt-in analytics adapters detect active WooCommerce,
  form, OneSignal and Power Schedule Manager integrations. They emit only a
  bounded, consent-gated event name and adapter metadata to `dataLayer`; no
  form values, visitor records or vendor API call is stored by BaoCache.
- **Delivered (beta60):** public evidence separates unexpected external GTM
  containers from GA4 Measurement IDs, and confirms whether the configured
  Auto Events/adapters script is present in HTML. Browser event receipt and
  vendor conversion remain explicitly outside this local diagnostic.
- **Delivered (beta61):** optional static CSP manager with Report-Only default,
  explicit source directives, one-owner guard and auto-origin suggestions only
  for enabled integrations. Analytics migration checklist records a
  duplicate-tag acknowledgement without hiding or mutating external tags.
- **Delivered (beta62):** CSP violation reports are opt-in and aggregate-only;
  reports retain no document URL/path, query, referrer or visitor data. Policy
  snapshots expose a fingerprint and directive-level changes, with no automatic
  Enforce promotion.
- **Delivered (beta63):** CSP source recommendations require repeat
  Report-Only evidence and map only safe HTTPS origins to one directive. Apply
  and dismiss are explicit operator actions, scoped to a policy fingerprint;
  BaoCache never auto-appends a source or promotes Enforce.
- **Delivered (beta64):** FastCGI URL purge now follows the same action-local
  feedback model as configuration saves: progress on the button, a compact
  toast and an inline HTTP diagnostic, with no redirect banner. CSP source
  changes receive an immutable rollback ledger; rollback is blocked as stale
  after any fingerprint or source-list change. Header Inspector records only
  an exact BaoCache policy match or an external/unknown observation, never an
  inferred Cloudflare owner.
- **Delivered (beta65):** the non-mutating FastCGI verify probe stores only
  timestamp, HTTP code, result and a bounded state. Cache and Site Diagnostics
  distinguish local configuration from a live verified endpoint and show
  code-specific remediation without retaining an endpoint, cache key, response,
  header or secret.
- **Delivered (beta66):** CSP aggregate reports and dismissed recommendation
  evidence expire after 30 days; applied-change ledger records expire after 90
  days, with daily Cron and an on-demand operator review. Expiry never changes
  the current policy or promotes Enforce.
- **Delivered (beta66):** exports include CSP evidence metadata only: counts,
  retention policy, review metadata and a policy fingerprint. Raw report groups,
  blocked origins and report bodies never leave the site.
- **Delivered (beta67):** Header Inspector records only bounded public CSP
  topology: present/mode/count and an exact local-policy match. It detects an
  external/unknown policy, duplicate policy type or simultaneous Enforce and
  Report-Only headers without storing policy text.
- **Delivered (beta67):** staged Enforce readiness is read-only. It evaluates
  local enablement, Report-Only collection, a public exact match, active retained
  evidence and one-owner conflict, but can never change mode or modify edge/Nginx
  configuration.
- **Delivered (beta68):** a Report-Only → Enforce transition requires an
  operator checklist acknowledgement bound to the current policy fingerprint
  and a passing public-policy readiness gate. It is enforced on both AJAX and
  standard WordPress settings saves.
- **Delivered (beta68):** the deployment checklist calls out public owner,
  staging flows, retained evidence and manual Report-Only rollback. It never
  calls external APIs or changes mode automatically.
- **Delivered (beta69):** the CSP panel now provides a metadata-only
  post-enforcement public probe. It checks HTTP status, policy disposition,
  exact local-policy match and duplicate/external conflicts without storing
  policy text or response bodies.
- **Delivered (beta69):** Enforce has an explicit, confirmed manual rollback
  action to Report-Only. Probe failures never auto-demote production policy;
  both probe and rollback are recorded in the operator Activity Log.
- **Delivered (beta70):** CSP post-enforcement probes now retain a sanitized,
  bounded history for 30 days (maximum 50 entries), including whether a check
  was manual or scheduled. Exports contain the same metadata only; no policy,
  response body, URL or secret is retained.
- **Delivered (beta70):** Enforce operators can opt into one daily public CSP
  canary. It runs only while Enforce, the canary toggle and WordPress Cron are
  active. A failed canary records a warning/failed activity event and never
  rolls back or rewrites policy automatically.
- **Delivered (beta70):** QA save/reset reloads preserve the current workspace
  (`#diagnostics`) so the operator returns to the checklist instead of the
  Overview tab. Sidebar tab changes also use a local deep-link fragment.
- **Delivered (beta71):** the two newest probe records are compared using
  bounded metadata only. Scheduled failure, repeated failure and changed
  status/mode/match/conflict fields are shown as a regression; no raw policy,
  response body or URL is retained.
- **Delivered (beta71):** a failed scheduled canary can be explicitly marked
  “đã xem” by an operator. The acknowledgement is fingerprint-bound to that
  probe and is invalidated by the next failure. It never changes CSP mode or
  triggers rollback.
- **Delivered (beta71):** an empty Staging Compatibility QA now shows
  “Chưa test” instead of the misleading `0/8 PASS`; PASS/FAIL counts appear
  only after a result has been saved.
- **Delivered (beta72):** the CSP panel now shows a bounded seven-sample
  scheduled-canary trend with real PASS/WARN/FAIL counts, average response
  time and consecutive-failure streak. Empty windows stay explicitly empty;
  BaoCache does not manufacture a score.
- **Delivered (beta72):** an operator remediation checklist is derived from
  observed conflict, status and policy-match metadata. It points to Header
  Inspector, staging QA and the manual Report-Only path, but never applies a
  source, purges a cache or rolls back automatically.
- **Delivered (beta73):** scheduled canary acknowledgements now have a bounded
  30-day history. Each record keeps only the probe fingerprint, timing and
  source metadata, so an operator can audit what was reviewed without exposing
  policy text or response data.
- **Delivered (beta73):** the remediation checklist accepts a completion flag
  and a short sanitized operator note per step. Notes are scoped to the current
  trend fingerprint and expire with the bounded state; new samples create a
  fresh context. BaoCache never applies the remediation or rolls back CSP.
- **Delivered (beta74):** the sidebar now exposes a dedicated Security workspace
  for WordPress hardening, CSP and the infrastructure boundary. Nginx/Coolify
  controls remain read-only guidance instead of pretending PHP can change them.
- **Delivered (beta74):** Asset Version Masking can keep, remove or replace a
  same-site `?ver=` query with a short deterministic fingerprint. The real
  version remains in Asset Inventory for diagnostics and third-party URLs are
  never rewritten.
- **Delivered (beta75):** CSP is split into Basic, Sources, Evidence,
  Diagnostics and Advanced workspaces. Routine policy controls stay compact;
  fingerprints, retention, canary history and operator remediation remain
  available without overwhelming ordinary administrators.
- **Delivered (beta76):** External Tag Gateway & Injector Detection classifies
  bounded public HTML evidence into BaoCache, Cloudflare Google Tag Gateway
  candidates, known plugin markers and unknown theme/wp_head snippets. It does
  not store HTML, make ownership claims, disable tags or alter Cloudflare. The
  Analytics Inspector presents status, owner candidate, confidence, evidence,
  risk and a manual next step rather than a raw HTML/tag table.
- **Delivered (beta77):** Automatic Critical Images scans at most the first 20
  frontend image nodes, stores a bounded candidate snapshot and ranks likely
  hero/slider/first-image candidates from position, dimensions and generic DOM
  markers. Core contains no theme or plugin slug conditions.
- **Delivered (beta77):** applying a same-site candidate changes only the
  existing front-page image preload/eager/fetchpriority runtime. A cache-bypass
  public probe must verify the image, preload and priority output; otherwise
  BaoCache immediately restores the prior configuration. Manual rollback is
  blocked when settings changed after apply.
- **Delivered (beta78):** Automatic Resource & Font Hints analyzes the latest
  administrator opt-in Resource Timing sample, fingerprints bounded origin
  evidence, deduplicates existing hints and caps recommendations. Operators can
  explicitly apply up to three safe preconnect/DNS-prefetch origins; the action
  stores before/after state and supports stale-guarded rollback. Exact preload
  URLs, font-display changes and LCP claims remain out of scope without asset or
  CSS evidence.

## P2 — Commercial optimization engine

- **Next:** beta79 — Automatic Font Optimization, only after exact font asset
  evidence is available. It must support preview, snapshot, post-change probe
  and rollback; never preload from an origin-only timing sample.

## P1 — Browser timing sample

- **Delivered (beta.33):** an administrator opt-in can collect one bounded
  anonymous frontend Resource Timing summary every 15 minutes. The browser
  groups data before sending; BaoCache stores no raw resource/page URL, query,
  cookie, IP or visitor identifier.
- **Delivered (beta.34):** administrators can clear every retained browser
  timing summary and reset its global rate limit from the Analysis tab.
- **Next:** compare several retained samples only after staging confirms that
  the endpoint, CSP and frontend behaviour are compatible. Do not label a
  resource render-blocking or unused from these aggregates alone.

## Explicitly deferred

Remove Unused CSS is deferred until a Chromium rendering worker exists. It must
be based on rendered-page analysis, not string matching or a global CSS toggle.
