# BaoCache Cloudflare integration

BaoCache can verify an environment-provided audit token, read one Zone and count
Cache Rules without returning rule content. Exact URL purge is a separate,
disabled-by-default control with its own token. BaoCache never alters Cache
Rules, DNS, WAF, SSL, Workers or APO.

## Configure in Coolify

Add these **runtime** variables to the Docker Compose resource, then redeploy:

| Variable | Value | Coolify type |
| --- | --- | --- |
| `BAOCACHE_CLOUDFLARE_AUDIT_ENABLED` | `true` | Normal |
| `BAOCACHE_CLOUDFLARE_ZONE_ID` | The exact 32-character Zone ID | Normal |
| `BAOCACHE_CLOUDFLARE_API_TOKEN` | Scoped Cloudflare API token | **Secret** |
| `BAOCACHE_CLOUDFLARE_PURGE_ENABLED` | `false` by default | Normal |
| `BAOCACHE_CLOUDFLARE_PURGE_API_TOKEN` | Separate Cache Purge token | **Secret** |

The token is read only at audit time. It is never copied into a WordPress option,
shown in wp-admin, included in BaoCache JSON export, or written to the BaoCache
activity log. Do not add it to Git, `.env.example`, `wp-config.php` or a
Docker build argument.

Create the audit token scoped to the one website Zone with these minimum permissions:

- `Zone` → `Zone` → `Read`
- `Zone` → `Zone Rulesets` → `Read`

If exact URL purge is needed, create a separate token for the same Zone with:

- `Zone` → `Cache Purge`

Set `BAOCACHE_CLOUDFLARE_PURGE_ENABLED=true` only after checking the audit in
staging. BaoCache accepts a single same-site public URL and never exposes purge
all, tag, host or prefix controls.

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

No Cloudflare cache-purge permission is required for audit-only use. The exact
URL purge control remains unavailable unless its separate secret and flag are
both present.
