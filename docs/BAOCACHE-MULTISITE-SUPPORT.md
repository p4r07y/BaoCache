# BaoCache multisite support statement

BaoCache 0.3.0-beta.83 is supported for a standard single-site WordPress
installation. WordPress multisite is not yet a supported production topology.

The Stable RC multisite gate requires a separate network test covering network
activation, per-site settings isolation, frontend cache invalidation, uninstall
policy, Site Overrides import/export and rollback. Until that evidence is
recorded as PASS, do not network-activate BaoCache in production.
