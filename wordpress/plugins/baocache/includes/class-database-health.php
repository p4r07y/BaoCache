<?php
defined( 'ABSPATH' ) || exit;

/** Read-only database observability plus idempotent repair of BaoCache-owned state. */
final class BaoCache_Database_Health {
	public static function inspect(): array {
		$settings = get_option( defined( 'BAOCACHE_OPTION' ) ? BAOCACHE_OPTION : 'baocache_settings', false );
		$schema = (int) get_option( BaoCache_Uninstall_Manager::SCHEMA_OPTION, 0 );
		$queue = get_option( 'baocache_warm_queue', array() );
		$queue = is_array( $queue ) ? $queue : array();
		return array(
			'status' => $schema === BaoCache_Uninstall_Manager::SCHEMA_VERSION && is_array( $settings ) ? 'healthy' : 'attention',
			'schema_current' => $schema,
			'schema_expected' => BaoCache_Uninstall_Manager::SCHEMA_VERSION,
			'custom_tables' => array( 'present' => 0, 'required' => 0 ),
			'custom_indexes' => array( 'present' => 0, 'required' => 0 ),
			'pending_migrations' => $schema < BaoCache_Uninstall_Manager::SCHEMA_VERSION ? 1 : 0,
			'configuration_valid' => is_array( $settings ),
			'queue_jobs' => count( $queue ),
			'cron' => array(
				'warmup' => (bool) wp_next_scheduled( 'baocache_warmup_tick' ),
				'metrics' => (bool) wp_next_scheduled( 'baocache_runtime_snapshot' ),
			),
			'autoload' => self::autoload_inspector(),
		);
	}

	public static function repair(): array {
		$actions = array();
		$option = defined( 'BAOCACHE_OPTION' ) ? BAOCACHE_OPTION : 'baocache_settings';
		if ( false === get_option( $option, false ) ) {
			add_option( $option, BaoCache_Settings::defaults(), '', false );
			$actions[] = 'configuration_created';
		}
		if ( (int) get_option( BaoCache_Uninstall_Manager::SCHEMA_OPTION, 0 ) !== BaoCache_Uninstall_Manager::SCHEMA_VERSION ) {
			update_option( BaoCache_Uninstall_Manager::SCHEMA_OPTION, BaoCache_Uninstall_Manager::SCHEMA_VERSION, false );
			$actions[] = 'schema_version_updated';
		}
		BaoCache_Warmup::activate();
		BaoCache_Metrics::activate();
		$actions[] = 'owned_cron_verified';
		return array( 'actions' => $actions, 'health' => self::inspect() );
	}

	private static function autoload_inspector(): array {
		global $wpdb;
		$allowed = array( 'yes', 'on', 'auto-on', 'auto' );
		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and placeholder list are controlled.
		$sql = $wpdb->prepare( "SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload IN ($placeholders) ORDER BY bytes DESC LIMIT 10", ...$allowed );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is provided by WordPress.
		$total = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS items, COALESCE(SUM(LENGTH(option_value)),0) AS bytes FROM {$wpdb->options} WHERE autoload IN ($placeholders)", ...$allowed ), ARRAY_A );
		$largest = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$largest[] = array( 'name' => sanitize_text_field( (string) $row['option_name'] ), 'bytes' => max( 0, (int) $row['bytes'] ) );
		}
		return array( 'items' => (int) ( $total['items'] ?? 0 ), 'bytes' => (int) ( $total['bytes'] ?? 0 ), 'largest' => $largest, 'read_only' => true );
	}
}
