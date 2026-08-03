<?php
defined( 'ABSPATH' ) || exit;

/** Bounded operational audit trail. It deliberately excludes secrets and query strings. */
final class BaoCache_Activity {
	private const string OPTION = 'baocache_activity_log';
	private const int LIMIT = 200;

	public static function log( string $action, string $outcome, string $detail, array $context = array() ): void {
		$user = wp_get_current_user();
		$clean_context = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || is_array( $value ) || is_object( $value ) ) {
				continue;
			}
			$clean_context[ $key ] = sanitize_text_field( (string) $value );
		}
		$items = get_option( self::OPTION, array() );
		$items = is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
		array_unshift( $items, array(
			'at' => time(),
			'action' => sanitize_key( $action ),
			'outcome' => in_array( $outcome, array( 'success', 'warning', 'failed' ), true ) ? $outcome : 'warning',
			'detail' => sanitize_text_field( $detail ),
			'actor_id' => $user instanceof WP_User ? (int) $user->ID : 0,
			'actor' => $user instanceof WP_User && $user->exists() ? sanitize_text_field( $user->display_name ) : __( 'System', 'baocache' ),
			'context' => $clean_context,
		) );
		update_option( self::OPTION, array_slice( $items, 0, self::LIMIT ), false );
	}

	/** @return array<int,array<string,mixed>> */
	public static function recent( int $limit = 8 ): array {
		$items = get_option( self::OPTION, array() );
		if ( ! is_array( $items ) ) {
			return array();
		}
		return array_slice( array_values( array_filter( $items, 'is_array' ) ), 0, max( 1, min( self::LIMIT, $limit ) ) );
	}

	/** Removes query strings and credentials before a URL can enter the log. */
	public static function safe_path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === $path ? '/' : '/' . ltrim( $path, '/' );
	}
}
