<?php
defined( 'ABSPATH' ) || exit;

/**
 * Commerce evidence is deliberately read-only. It identifies routes and assets
 * that must remain protected before any cache or asset recommendation exists.
 */
final class BaoCache_Commerce_Optimizer {
	private const SNAPSHOT_OPTION = 'baocache_commerce_snapshot';
	private const APPLICATION_OPTION = 'baocache_commerce_application';

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
		$report['candidate_count'] = count( (array) $report['protected_routes'] );
		update_option( self::SNAPSHOT_OPTION, $report, false );
		return $report;
	}

	/** @return array<string, mixed>|WP_Error */
	public static function apply( string $fingerprint ): array|WP_Error {
		$snapshot = self::snapshot();
		if ( '' === $fingerprint || ! hash_equals( (string) ( $snapshot['fingerprint'] ?? '' ), $fingerprint ) ) return new WP_Error( 'stale_commerce_evidence', __( 'Commerce evidence đã cũ. Hãy quét lại trước khi áp dụng.', 'baocache' ) );
		$routes = is_array( $snapshot['protected_routes'] ?? null ) ? $snapshot['protected_routes'] : array();
		if ( empty( $routes ) ) return new WP_Error( 'no_commerce_routes', __( 'Chưa có commerce route được metadata xác minh để áp dụng.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		$before = (string) $settings['render_blocking_exclude_urls'];
		$paths = BaoCache_Settings::lines( $before );
		foreach ( $routes as $route ) {
			$path = (string) ( is_array( $route ) ? ( $route['path'] ?? '' ) : '' );
			if ( '' !== $path && ! in_array( $path, $paths, true ) ) $paths[] = $path;
		}
		$settings['render_blocking_exclude_urls'] = implode( "\n", $paths );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record = array( 'applied_at' => time(), 'snapshot_fingerprint' => $fingerprint, 'before' => $before, 'after' => $settings['render_blocking_exclude_urls'], 'rolled_back_at' => 0 );
		update_option( self::APPLICATION_OPTION, $record, false );
		return array( 'snapshot' => $snapshot, 'application' => $record );
	}

	/** @return array<string, mixed>|WP_Error */
	public static function rollback(): array|WP_Error {
		$record = self::application();
		if ( empty( $record['applied_at'] ) || ! empty( $record['rolled_back_at'] ) ) return new WP_Error( 'nothing_to_rollback', __( 'Không có commerce protection apply đang hoạt động để rollback.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		if ( (string) $settings['render_blocking_exclude_urls'] !== (string) ( $record['after'] ?? '' ) ) return new WP_Error( 'stale_commerce_application', __( 'Exclusion URL đã thay đổi sau apply; rollback tự động bị chặn.', 'baocache' ) );
		$settings['render_blocking_exclude_urls'] = (string) ( $record['before'] ?? '' );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record['rolled_back_at'] = time();
		update_option( self::APPLICATION_OPTION, $record, false );
		return $record;
	}

	/** @return array<string, mixed> */
	public static function report(): array {
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory ) && is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		$woocommerce_active = class_exists( 'WooCommerce' );
		$routes = self::protected_routes( $woocommerce_active );
		$fragment_handles = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || 'script' !== (string) ( $asset['type'] ?? '' ) ) continue;
			$handle = sanitize_key( (string) ( $asset['handle'] ?? '' ) );
			if ( '' !== $handle && preg_match( '/(?:woocommerce|\bwc-|cart|checkout|fragment|payment)/i', $handle ) ) $fragment_handles[] = $handle;
		}
		$fragment_handles = array_slice( array_values( array_unique( $fragment_handles ) ), 0, 12 );
		return array(
			'schema' => 1,
			'generated_at' => time(),
			'inventory_captured_at' => (int) ( $inventory['captured_at'] ?? 0 ),
			'woocommerce_active' => $woocommerce_active,
			'protected_contexts' => array( 'authenticated', 'admin', 'login', 'preview', 'checkout', 'ajax', 'rest' ),
			'protected_routes' => $routes,
			'fragment_handles' => $fragment_handles,
			'evidence_status' => empty( $assets ) ? 'inventory_missing' : 'inventory_observed',
			'fingerprint' => substr( hash( 'sha256', (string) wp_json_encode( array( $routes, $fragment_handles, $woocommerce_active, (int) ( $inventory['captured_at'] ?? 0 ) ) ) ), 0, 16 ),
		);
	}

	/** @return array<int, array{path: string, source: string}> */
	private static function protected_routes( bool $woocommerce_active ): array {
		$routes = array();
		if ( $woocommerce_active && function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
				$page_id = absint( wc_get_page_id( $page ) );
				$url = $page_id > 0 ? get_permalink( $page_id ) : '';
				$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
				if ( '' !== $path && '/' === substr( $path, 0, 1 ) ) $routes[] = array( 'path' => untrailingslashit( $path ) . '/', 'source' => 'WooCommerce page metadata' );
			}
		}
		return array_values( array_unique( $routes, SORT_REGULAR ) );
	}
}
