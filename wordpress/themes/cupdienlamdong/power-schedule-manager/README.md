# Plugin template overrides

Only copy a template from `power-schedule-manager/public/templates` into this
directory when its HTML structure must differ for this site. Keep data access,
API calls, cron jobs, cache invalidation and other business logic in the plugin.

The plugin already searches this child-theme directory before its own default
templates. Avoid copying every template because unchanged copies make parent and
plugin upgrades harder to review.

