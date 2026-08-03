# BaoCache Stable RC checklist

Stable RC is blocked until every required gate below has a recorded result. A
missing result is not a PASS.

Use [the staging acceptance runbook](BAOCACHE-STAGING-ACCEPTANCE-RUNBOOK.md) to
collect the manual evidence required by the Open gates.

| Gate | Required evidence | Status |
| --- | --- | --- |
| Clean install | Fresh WordPress activates BaoCache, opens the dashboard and removes only registered BaoCache data according to the selected uninstall policy. | Open |
| ZIP parity | `scripts/validate-release.sh` passes for the shipped ZIP. | Automated |
| Asset safety | Asset Inventory, Resource Hints, Third-party, Commerce and adapters each preserve their fingerprint/apply/rollback boundary. | Open on staging |
| Critical contexts | Logged-in, login, cart, checkout, account, menu, form, map, analytics and chat are PASS in Compatibility QA. | Open on staging |
| Cloudflare | Audit token scope works; exact URL purge is checked only if its separate token and Coolify flag are enabled. | Open on staging |
| Site Overrides | Export/import profile is validated without carrying credentials, runtime metrics, logs, identities or another site's domain. | Open |
| Multisite | Network support statement is published after a separate WordPress multisite test; until then BaoCache is single-site only. | Open — single-site only |

## Release decision

Only a named maintainer may change an Open gate to PASS after recording its
environment, date, observed result and rollback result. Any FAIL blocks the
stable tag until it is fixed and re-tested.
