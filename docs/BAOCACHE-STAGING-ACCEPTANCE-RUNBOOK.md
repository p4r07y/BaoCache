# BaoCache staging acceptance runbook

Run this after deploying the committed ZIP to staging. Record PASS, FAIL or
Not Applicable in **BaoCache → Staging Compatibility QA**; do not infer a PASS
from an empty error log.

1. Activate BaoCache on a fresh staging WordPress site and open every workspace.
2. Export Site Overrides, import them into a second staging site, verify the
   intended rules, then use **Rollback import** and verify the prior settings.
3. Run Asset Inventory. Verify that a zero-candidate Resource Hints result says
   there is nothing to apply; it is valid evidence, not a failure.
4. For every enabled Resource Hints, Third-party, Commerce or Adapter change:
   test apply, a logged-out frontend request, the affected context, and rollback.
5. Test login, account, cart, checkout, menu, forms, maps, analytics and chat.
6. If Cloudflare is configured, audit Zone/Rulesets permissions. Test exactly one
   staging URL purge only if the separate purge token and flag are enabled.
7. Verify uninstall policy on the fresh staging site. It must remove only exact
   registered BaoCache data and preserve or remove configuration as selected.

Any FAIL blocks the stable tag. Attach the environment name, date and rollback
result to the QA record before retesting.
