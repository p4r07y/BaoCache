<?php
defined( 'ABSPATH' ) || exit;

/**
 * Commerce evidence is deliberately read-only. It identifies routes and assets
 * that must remain protected before any cache or asset recommendation exists.
 */
final class BaoCache_Commerce_Optimizer {
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
