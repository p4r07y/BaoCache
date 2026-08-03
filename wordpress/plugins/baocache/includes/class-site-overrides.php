<?php
defined( 'ABSPATH' ) || exit;

/** Portable, non-secret per-site overrides with exact rollback. */
final class BaoCache_Site_Overrides {
	private const APPLICATION_OPTION = 'baocache_site_overrides_application';
	private const KEYS = array( 'asset_rules', 'render_blocking_exclude_handles', 'render_blocking_exclude_urls', 'render_blocking_exclude_contexts', 'defer_handles', 'async_style_handles', 'delay_handles', 'preconnect', 'dns_prefetch', 'preload', 'lcp_image', 'lcp_scope' );

	public static function profile(): array {
		return array( 'kind' => 'baocache-site-overrides', 'schema' => 1, 'overrides' => self::values( BaoCache_Settings::get() ) );
	}

	public static function application(): array { $value = get_option( self::APPLICATION_OPTION, array() ); return is_array( $value ) ? $value : array(); }

	/** @return array<string, mixed>|WP_Error */
	public static function import( string $json ): array|WP_Error {
		if ( strlen( $json ) > 65536 ) return new WP_Error( 'site_override_too_large', __( 'Site Override profile quá lớn.', 'baocache' ) );
		$profile = json_decode( $json, true );
		if ( ! is_array( $profile ) || 'baocache-site-overrides' !== (string) ( $profile['kind'] ?? '' ) || 1 !== (int) ( $profile['schema'] ?? 0 ) || ! is_array( $profile['overrides'] ?? null ) ) return new WP_Error( 'invalid_site_override', __( 'Site Override profile không hợp lệ.', 'baocache' ) );
		$current = BaoCache_Settings::get();
		$before = self::values( $current );
		$sanitized = BaoCache_Settings::sanitize( array_merge( $current, $profile['overrides'] ) );
		$after = self::values( $sanitized );
		if ( $before === $after ) return new WP_Error( 'site_override_no_change', __( 'Site Override không có thay đổi hợp lệ để áp dụng.', 'baocache' ) );
		update_option( BAOCACHE_OPTION, array_merge( $current, $after ), false );
		$record = array( 'applied_at' => time(), 'before' => $before, 'after' => $after, 'rolled_back_at' => 0 );
		update_option( self::APPLICATION_OPTION, $record, false );
		return array( 'changed' => count( $after ), 'application' => $record );
	}

	/** @return array<string, mixed>|WP_Error */
	public static function rollback(): array|WP_Error {
		$record = self::application();
		if ( empty( $record['applied_at'] ) || ! empty( $record['rolled_back_at'] ) ) return new WP_Error( 'no_site_override_rollback', __( 'Không có Site Override import để rollback.', 'baocache' ) );
		$current = BaoCache_Settings::get();
		if ( self::values( $current ) !== (array) ( $record['after'] ?? array() ) ) return new WP_Error( 'stale_site_override', __( 'Site Overrides đã thay đổi sau import; rollback tự động bị chặn.', 'baocache' ) );
		update_option( BAOCACHE_OPTION, array_merge( $current, (array) $record['before'] ), false );
		$record['rolled_back_at'] = time(); update_option( self::APPLICATION_OPTION, $record, false ); return $record;
	}

	private static function values( array $settings ): array { $values = array(); foreach ( self::KEYS as $key ) $values[ $key ] = $settings[ $key ] ?? ( 'asset_rules' === $key ? array() : '' ); return $values; }
}
