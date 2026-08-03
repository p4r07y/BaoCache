<?php
defined( 'ABSPATH' ) || exit;

/** Optional adapter metadata from active integrations and observed handles only. */
final class BaoCache_Theme_Builder_Adapters {
	private const SNAPSHOT_OPTION = 'baocache_theme_builder_snapshot';
	private const APPLICATION_OPTION = 'baocache_theme_builder_application';

	public static function snapshot(): array {
		$value = get_option( self::SNAPSHOT_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function application(): array {
		$value = get_option( self::APPLICATION_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return array<string, mixed> */
	public static function scan(): array {
		$report = self::report();
		$report['scanned_at'] = time();
		$report['candidate_count'] = count( (array) $report['excluded_handles'] );
		update_option( self::SNAPSHOT_OPTION, $report, false );
		return $report;
	}

	/** @return array<string, mixed>|WP_Error */
	public static function apply( string $fingerprint ): array|WP_Error {
		$snapshot = self::snapshot();
		if ( '' === $fingerprint || ! hash_equals( (string) ( $snapshot['fingerprint'] ?? '' ), $fingerprint ) ) return new WP_Error( 'stale_adapter_evidence', __( 'Adapter evidence đã cũ. Hãy quét lại trước khi áp dụng.', 'baocache' ) );
		$handles = array_values( array_filter( array_map( 'sanitize_key', (array) ( $snapshot['excluded_handles'] ?? array() ) ) ) );
		if ( empty( $handles ) ) return new WP_Error( 'no_adapter_handles', __( 'Chưa có adapter handle đã quan sát để thêm vào exclusion.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		$before = (string) $settings['render_blocking_exclude_handles'];
		$excluded = BaoCache_Settings::lines( $before );
		foreach ( $handles as $handle ) if ( ! in_array( $handle, $excluded, true ) ) $excluded[] = $handle;
		$settings['render_blocking_exclude_handles'] = implode( "\n", $excluded );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record = array( 'applied_at' => time(), 'snapshot_fingerprint' => $fingerprint, 'before' => $before, 'after' => $settings['render_blocking_exclude_handles'], 'rolled_back_at' => 0 );
		update_option( self::APPLICATION_OPTION, $record, false );
		return array( 'snapshot' => $snapshot, 'application' => $record );
	}

	/** @return array<string, mixed>|WP_Error */
	public static function rollback(): array|WP_Error {
		$record = self::application();
		if ( empty( $record['applied_at'] ) || ! empty( $record['rolled_back_at'] ) ) return new WP_Error( 'nothing_to_rollback', __( 'Không có adapter exclusion đang hoạt động để rollback.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		if ( (string) $settings['render_blocking_exclude_handles'] !== (string) ( $record['after'] ?? '' ) ) return new WP_Error( 'stale_adapter_application', __( 'Exclusion handle đã thay đổi sau apply; rollback tự động bị chặn.', 'baocache' ) );
		$settings['render_blocking_exclude_handles'] = (string) ( $record['before'] ?? '' );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record['rolled_back_at'] = time();
		update_option( self::APPLICATION_OPTION, $record, false );
		return $record;
	}

	/** @return array<string, mixed> */
	public static function report(): array {
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory ) && is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		$handles = array_values( array_filter( array_map( static fn( mixed $asset ): string => is_array( $asset ) ? sanitize_key( (string) ( $asset['handle'] ?? '' ) ) : '', $assets ) ) );
		$theme = wp_get_theme();
		$theme_id = strtolower( (string) $theme->get_template() . ' ' . (string) $theme->get_stylesheet() );
		$adapters = array(
			'blocksy' => array( 'label' => 'Blocksy', 'active' => str_contains( $theme_id, 'blocksy' ), 'prefix' => 'ct-' ),
			'elementor' => array( 'label' => 'Elementor', 'active' => defined( 'ELEMENTOR_VERSION' ), 'prefix' => 'elementor-' ),
			'bricks' => array( 'label' => 'Bricks', 'active' => str_contains( $theme_id, 'bricks' ), 'prefix' => 'bricks-' ),
		);
		$observed = array();
		$excluded = array();
		foreach ( $adapters as $key => $adapter ) {
			$matches = array_values( array_filter( $handles, static fn( string $handle ): bool => str_starts_with( $handle, (string) $adapter['prefix'] ) ) );
			$detected = ! empty( $adapter['active'] ) || ! empty( $matches );
			$observed[ $key ] = array( 'label' => $adapter['label'], 'detected' => $detected, 'active' => ! empty( $adapter['active'] ), 'handles' => array_slice( $matches, 0, 12 ) );
			$excluded = array_merge( $excluded, $matches );
		}
		$excluded = array_values( array_unique( $excluded ) );
		return array(
			'schema' => 1, 'generated_at' => time(), 'inventory_captured_at' => (int) ( $inventory['captured_at'] ?? 0 ),
			'adapters' => $observed, 'excluded_handles' => $excluded,
			'evidence_status' => empty( $assets ) ? 'inventory_missing' : 'inventory_observed',
			'fingerprint' => substr( hash( 'sha256', (string) wp_json_encode( array( $theme_id, $observed, $excluded, (int) ( $inventory['captured_at'] ?? 0 ) ) ) ), 0, 16 ),
		);
	}
}
