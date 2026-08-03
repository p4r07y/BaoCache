<?php
/**
 * Cúp Điện Lâm Đồng uninstall handler.
 *
 * Data is preserved by default to prevent accidental data loss.
 *
 * @package Power_Schedule_Manager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove scheduled maintenance tasks.
 *
 * The literal hook name is used because WordPress does not automatically
 * load the main plugin bootstrap while executing uninstall.php.
 */
wp_clear_scheduled_hook(
	'power_schedule_manager_daily_maintenance'
);

wp_clear_scheduled_hook(
	'power_schedule_manager_process_notifications'
);

wp_clear_scheduled_hook(
	'power_schedule_manager_weather_refresh'
);

/**
 * Remove temporary migration lock.
 *
 * This option is operational state rather than user data or configuration.
 */
delete_option(
	'power_schedule_manager_migration_lock'
);

delete_option(
	'psm_place_link_match_lock'
);

/**
 * Clear the runtime object-cache group when supported.
 *
 * This does not delete persistent plugin records or flush the entire
 * WordPress object cache.
 */
if ( function_exists( 'wp_cache_flush_group' ) ) {
	wp_cache_flush_group(
		'power_schedule_manager'
	);
}

/**
 * Intentionally preserved:
 *
 * - power_schedule_manager_version
 * - power_schedule_manager_schema_version
 * - power_schedule_manager_seed_version
 * - power_schedule_manager_settings
 * - power_schedule_manager_brand_version
 * - power_schedule_manager_cache_version
 * - Custom database tables
 * - Schedule posts
 * - Power-area taxonomy terms
 * - Custom capabilities and role assignments
 *
 * Permanent deletion must never happen automatically during uninstall.
 */
