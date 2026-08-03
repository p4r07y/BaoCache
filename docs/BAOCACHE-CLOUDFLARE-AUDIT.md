# BaoCache Cloudflare read-only audit

BaoCache can verify an environment-provided Cloudflare API token and read one
Zone record. It is an optional diagnostic: it is disabled by default and it does
not make Cloudflare cache HTML, alter a Cache Rule, purge cache, or manage DNS,
WAF, SSL, Workers or APO.

## Configure in Coolify

Add these **runtime** variables to the Docker Compose resource, then redeploy:

| Variable | Value | Coolify type |
| --- | --- | --- |
| `BAOCACHE_CLOUDFLARE_AUDIT_ENABLED` | `true` | Normal |
| `BAOCACHE_CLOUDFLARE_ZONE_ID` | The exact 32-character Zone ID | Normal |
| `BAOCACHE_CLOUDFLARE_API_TOKEN` | Scoped Cloudflare API token | **Secret** |

The token is read only at audit time. It is never copied into a WordPress option,
shown in wp-admin, included in BaoCache JSON export, or written to the BaoCache
activity log. Do not add it to Git, `.env.example`, `wp-config.php` or a
Docker build argument.

Create a token scoped to the one website Zone with this minimum permission:

- `Zone` → `Zone` → `Read`

BaoCache calls only Cloudflare's token verification endpoint and the Zone details
endpoint. The exact permission catalog and Zone endpoint are documented by
Cloudflare: [API token permissions](https://developers.cloudflare.com/fundamentals/api/reference/permissions/)
and [Zone details](https://developers.cloudflare.com/api/resources/zones/methods/get/).

## Use the audit

Open **BaoCache → Tổng quan** and select **Chạy audit** in the Cloudflare panel.
Successful output includes only token-verification status, Zone name, state,
type, paused state and Development Mode. It never displays a token, account
identifier, raw API response or cache-rule content.

The Header Inspector remains the source for observed response headers such as
`CF-Cache-Status`. A `DYNAMIC` status means that response was proxied but the
HTML was not edge-cached; it does not indicate a failing Cloudflare connection.

## Failure states

- **Not configured:** Coolify variables are missing or audit is left disabled.
- **Token failed:** the token cannot be verified; check the Secret value without
  pasting it into wp-admin or logs.
- **Zone failed:** the token is valid but cannot read the supplied Zone. Check
  the 32-character Zone ID and the token's one-Zone `Zone → Zone → Read` scope.

No Cloudflare cache-purge permission is required by this release. If a future
Cloudflare purge capability is approved, it will be a separate explicit module
with a separate least-privilege secret and exact-URL-only behaviour.
