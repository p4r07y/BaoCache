# BaoCache v1 delivery plan

## Definition of complete

BaoCache v1 is a WordPress performance control plane for Nginx FastCGI, Redis,
Docker and opt-in Cloudflare. It is complete when an administrator can install,
understand, verify, safely change and roll back supported optimizations without
being shown invented scores, secret fields or irreversible bulk actions.

The product remains a control plane. It does not become a second HTML page
cache, a WAF, a malware scanner, a generic script remover or an AI auto-tuner.

## Delivery order

| Phase | Outcome | UI/UX acceptance | Engineering gate |
| --- | --- | --- | --- |
| 0. Stable RC closure | Existing beta79–83 capabilities are trustworthy as one product. | One clear release-readiness state; no hidden beta feature or contradictory empty state. | Clean install, ZIP parity, Site Overrides export/import/rollback, single-site statement and staging QA. |
| 1. Information architecture | Dashboard becomes an operational home, not a collection of panels. | Navigation labels match user intent; one primary action per panel; durable warnings differ from transient success toasts; responsive at desktop/tablet/mobile. | Keyboard, focus, empty/loading/error states and no horizontal overflow. |
| 2. Optimization workflows | Every supported optimization follows one repeatable workflow. | Evidence → preview → scope → apply → verify → rollback is visible on the owning screen. | Fingerprint, audit entry, cache invalidation and stale-safe rollback for each mutation. |
| 3. Runtime & integrations | Operators can prove infrastructure state without exposing secrets. | Nginx/Redis/Cloudflare cards state source, freshness and missing configuration plainly. | Least-privilege secrets, bounded API responses, exact URL only for external purge and no vendor mutation outside approved controls. |
| 4. Production onboarding | A new site reaches a safe baseline in one guided flow. | First-run checklist, safe defaults, explainers and links to evidence; advanced controls stay secondary. | Fresh install, uninstall policy, upgrade path and support export all pass. |
| 5. v1 release | Stable tag is published only after acceptance evidence. | No beta badge, no “coming soon” copy in core paths, release notes explain limits. | Signed-off staging matrix, one rollback per mutation family and release artifact verification. |

## UI/UX backlog

### P0 — consistency and clarity

- Replace any remaining beta-specific card wording with capability language and
  show maturity only where it changes operator risk.
- Standardize every panel header: title, one-sentence outcome, scope badge,
  primary action and evidence freshness.
- Standardize empty states: distinguish **not configured**, **not scanned**,
  **scanned with zero candidates**, **blocked by gate**, and **failed**.
- Use compact toasts for completed actions; reserve banners for actionable
  warnings/errors. Never reflow the page on success.
- Keep Asset Explorer columns stable: dense desktop table, truncation with full
  accessible labels, and stacked details on narrow screens.

### P1 — guided workflows

- Add a release-readiness panel that links each open Stable RC gate to the
  owning screen or runbook.
- Add evidence freshness and “what changes if applied” to every optimizer.
- Make rollback state visible immediately after apply, including the condition
  that can make rollback stale.
- Add an onboarding checklist for Nginx, Redis, asset inventory and optional
  Cloudflare without requiring a token in wp-admin.

### P2 — accessibility and responsiveness

- Verify tab order, visible focus, semantic headings, form labels, status text
  and contrast for every state.
- Verify 1440px, 1024px, 768px and 390px layouts without clipped actions or
  horizontal table overflow.
- Use text alongside colour for PASS/WARN/FAIL and never rely on an icon alone.

## Product backlog after Stable RC

1. Finish Stable RC evidence before adding new optimization families.
2. Ship the v1 information-architecture and workflow pass as one UX release.
3. Ship guided onboarding and release-readiness as one UX release.
4. Run the v1 acceptance matrix and publish the stable release.

## Release discipline

Every roadmap commit runs:

```sh
scripts/build-baocache-zip.sh
scripts/verify-stable-rc.sh
```

Commit names use `Start v1 — scope`, `Harden v1 — scope` and `Release v1 —
scope`. No stable tag is created merely because all code paths exist; staging
evidence is required.
