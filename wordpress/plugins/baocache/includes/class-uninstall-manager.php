<?php
defined( 'ABSPATH' ) || exit;

/** Owns the exact, auditable list of data BaoCache may remove. */
final class BaoCache_Uninstall_Manager {
	public const string POLICY_OPTION = 'baocache_uninstall_policy';
	public const string SCHEMA_OPTION = 'baocache_schema_version';
	public const int SCHEMA_VERSION = 1;

	public static function defaults(): array {
		return array( 'keep_configuration' => true, 'keep_diagnostics' => false, 'remove_everything' => false );
	}

	public static function policy(): array {
		$value = get_option( self::POLICY_OPTION, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	public static function save_policy( array $settings ): void {
		$remove = ! empty( $settings['uninstall_remove_everything'] );
		update_option( self::POLICY_OPTION, array(
			'keep_configuration' => ! $remove && ! empty( $settings['uninstall_keep_configuration'] ),
			'keep_diagnostics'   => ! $remove && ! empty( $settings['uninstall_keep_diagnostics'] ),
			'remove_everything' => $remove,
		), false );
	}

	/** Runtime is disposable even when configuration is retained. */
	public static function runtime_options(): array {
		return apply_filters( 'baocache_uninstall_runtime_options', array(
			'baocache_warm_queue', 'baocache_warm_status', 'baocache_csp_owner_observation',
		) );
	}

	public static function diagnostic_options(): array {
		return apply_filters( 'baocache_uninstall_diagnostic_options', array(
			'baocache_activity_log', 'baocache_runtime_snapshots', 'baocache_frontend_timing_samples',
			'baocache_settings_history', 'baocache_hardening_probe_history', 'baocache_hardening_probe_baseline',
			'baocache_hardening_probe_acknowledged', 'baocache_compatibility_qa',
			'baocache_render_blocking_audit', 'baocache_render_blocking_log',
			'baocache_rule_compatibility_gates', 'baocache_rule_compatibility_gate_history',
			'baocache_rule_compatibility_gate_ack', 'baocache_rule_compatibility_gate_review',
			'baocache_csp_reports', 'baocache_csp_evidence_review', 'baocache_csp_policy_history',
			'baocache_csp_recommendations_dismissed', 'baocache_csp_recommendations_applied',
			'baocache_csp_enforce_acknowledgement', 'baocache_csp_post_enforcement_probe',
			'baocache_csp_post_enforcement_probe_history', 'baocache_csp_post_probe_ack',
			'baocache_csp_post_probe_ack_history', 'baocache_csp_post_probe_remediation',
			'baocache_fastcgi_purge_evidence',
			'baocache_critical_image_snapshot', 'baocache_critical_image_application',
		) );
	}

	public static function configuration_options(): array {
		return apply_filters( 'baocache_uninstall_configuration_options', array(
			defined( 'BAOCACHE_OPTION' ) ? BAOCACHE_OPTION : 'baocache_settings',
			self::POLICY_OPTION, self::SCHEMA_OPTION, 'baocache_critical_css',
		) );
	}

	public static function transients(): array {
		return apply_filters( 'baocache_uninstall_transients', array(
			'baocache_asset_inventory', 'baocache_asset_inventory_lock', 'baocache_asset_inventory_scan_token',
			'baocache_fastcgi_metrics', 'baocache_redis_metrics', 'baocache_runtime_snapshot_lock',
			'baocache_warmup_lock', 'baocache_hardening_probe_lock', 'baocache_csp_canary_lock',
			'baocache_csp_report_rate', 'baocache_frontend_timing_rate',
		) );
	}

	public static function cron_hooks(): array {
		return apply_filters( 'baocache_uninstall_cron_hooks', array(
			'baocache_warmup_tick', 'baocache_warmup_sitemap', 'baocache_runtime_snapshot',
			'baocache_hardening_probe_tick', 'baocache_rule_gate_review_tick',
			'baocache_csp_canary_tick', 'baocache_csp_evidence_review_tick', 'baocache_purge_url',
		) );
	}

	public static function clean_runtime(): array {
		$removed = 0;
		foreach ( self::runtime_options() as $option ) { $removed += delete_option( $option ) ? 1 : 0; }
		foreach ( self::transients() as $transient ) { $removed += delete_transient( $transient ) ? 1 : 0; }
		foreach ( self::cron_hooks() as $hook ) { wp_clear_scheduled_hook( $hook ); }
		return array( 'records_removed' => $removed, 'cron_hooks_cleared' => count( self::cron_hooks() ) );
	}

	public static function uninstall(): void {
		$policy = self::policy();
		self::clean_runtime();
		if ( empty( $policy['keep_diagnostics'] ) || ! empty( $policy['remove_everything'] ) ) {
			foreach ( self::diagnostic_options() as $option ) { delete_option( $option ); }
		}
		if ( ! empty( $policy['remove_everything'] ) || empty( $policy['keep_configuration'] ) ) {
			foreach ( self::configuration_options() as $option ) { delete_option( $option ); }
		}
		// No tables, capabilities, usermeta or generated files are registered in
		// beta77.1. Future modules must register exact targets through these filters.
		do_action( 'baocache_uninstall_cleanup', $policy );
	}
}
