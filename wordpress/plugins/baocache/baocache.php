<?php
/**
 * Plugin Name:       BaoCache
 * Plugin URI:        https://cupdienlamdong.com/
 * Description:       WordPress Performance Control Plane for Nginx FastCGI, Redis and Docker.
 * Version:           0.3.0-beta.83
 * Requires at least: 6.7
 * Requires PHP:      8.3
 * Author:            Nguyễn Hoàng Thái Bảo
 * Text Domain:       baocache
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * BaoCache is an independent performance plugin. It does not replace an
 * object-cache drop-in or WordPress business plugins.
 */

defined( 'ABSPATH' ) || exit;

define( 'BAOCACHE_VERSION', '0.3.0-beta.83' );
define( 'BAOCACHE_FILE', __FILE__ );
define( 'BAOCACHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BAOCACHE_URL', plugin_dir_url( __FILE__ ) );
define( 'BAOCACHE_OPTION', 'baocache_settings' );

require_once BAOCACHE_PATH . 'includes/class-settings.php';
require_once BAOCACHE_PATH . 'includes/class-uninstall-manager.php';
require_once BAOCACHE_PATH . 'includes/class-database-health.php';
require_once BAOCACHE_PATH . 'includes/class-diagnostics.php';
require_once BAOCACHE_PATH . 'includes/class-runtime.php';
require_once BAOCACHE_PATH . 'includes/class-frontend-metrics.php';
require_once BAOCACHE_PATH . 'includes/class-warmup.php';
add_filter( 'cron_schedules', array( 'BaoCache_Warmup', 'schedules' ) );
require_once BAOCACHE_PATH . 'includes/class-metrics.php';
require_once BAOCACHE_PATH . 'includes/class-activity.php';
require_once BAOCACHE_PATH . 'includes/class-purge.php';
require_once BAOCACHE_PATH . 'includes/class-cloudflare.php';
require_once BAOCACHE_PATH . 'includes/class-analytics.php';
require_once BAOCACHE_PATH . 'includes/class-csp.php';
require_once BAOCACHE_PATH . 'includes/class-critical-images.php';
require_once BAOCACHE_PATH . 'includes/class-resource-hints.php';
require_once BAOCACHE_PATH . 'includes/class-third-party-optimizer.php';
require_once BAOCACHE_PATH . 'includes/class-commerce-optimizer.php';
require_once BAOCACHE_PATH . 'includes/class-theme-builder-adapters.php';
require_once BAOCACHE_PATH . 'includes/class-optimization-advisor.php';
require_once BAOCACHE_PATH . 'includes/class-admin.php';
require_once BAOCACHE_PATH . 'includes/class-render-blocking.php';
require_once BAOCACHE_PATH . 'includes/class-plugin.php';

register_activation_hook(
	BAOCACHE_FILE,
	static function (): void {
	if ( false === get_option( BAOCACHE_OPTION, false ) ) {
		add_option( BAOCACHE_OPTION, BaoCache_Settings::defaults(), '', false );
	}
	BaoCache_Uninstall_Manager::save_policy( BaoCache_Settings::get() );
	update_option( BaoCache_Uninstall_Manager::SCHEMA_OPTION, BaoCache_Uninstall_Manager::SCHEMA_VERSION, false );
	BaoCache_Warmup::activate();
	BaoCache_Metrics::activate();
}
);

register_deactivation_hook( BAOCACHE_FILE, static function (): void {
	BaoCache_Warmup::deactivate();
	BaoCache_Metrics::deactivate();
	wp_clear_scheduled_hook( 'baocache_hardening_probe_tick' );
} );

BaoCache_Plugin::instance()->register();
