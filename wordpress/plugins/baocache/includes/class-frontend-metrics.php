<?php
defined( 'ABSPATH' ) || exit;

/**
 * Stores small, administrator-opt-in browser Resource Timing summaries. It deliberately
 * receives no URL, query, cookie, IP address or visitor identifier.
 */
final class BaoCache_Frontend_Metrics {
	private const string ROUTE_NAMESPACE = 'baocache/v1';
	private const string ROUTE = '/resource-timing';
	private const string OPTION = 'baocache_frontend_timing_samples';
	private const string RATE_LIMIT = 'baocache_frontend_timing_rate';
	private const int MAX_SAMPLES = 96;
	private const int MAX_GROUPS = 20;

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), PHP_INT_MAX );
		add_filter( 'script_loader_tag', array( $this, 'resource_timing_tag' ), 20, 3 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function enqueue(): void {
		if ( is_admin() || is_user_logged_in() || ! BaoCache_Settings::get()['frontend_timing_enabled'] ) {
			return;
		}
		wp_enqueue_script( 'baocache-resource-timing', BAOCACHE_URL . 'assets/baocache-resource-timing.js', array(), BAOCACHE_VERSION, true );
	}

	/** Keep public collector configuration on the external script tag, not inline JS. */
	public function resource_timing_tag( string $tag, string $handle, string $src ): string {
		if ( 'baocache-resource-timing' !== $handle ) {
			return $tag;
		}
		$attributes = sprintf(
			' data-baocache-timing-endpoint="%s" data-baocache-timing-nonce="%s"',
			esc_attr( esc_url_raw( rest_url( self::ROUTE_NAMESPACE . self::ROUTE ) ) ),
			esc_attr( wp_create_nonce( 'baocache_resource_timing' ) )
		);
		return str_replace( '<script ', '<script' . $attributes . ' ', $tag );
	}

	public function routes(): void {
		register_rest_route( self::ROUTE_NAMESPACE, self::ROUTE, array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'receive' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function receive( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! BaoCache_Settings::get()['frontend_timing_enabled'] ) {
			return new WP_Error( 'baocache_timing_disabled', __( 'Browser timing chưa được bật.', 'baocache' ), array( 'status' => 404 ) );
		}
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		if ( ! wp_verify_nonce( (string) ( $payload['nonce'] ?? '' ), 'baocache_resource_timing' ) ) {
			return new WP_Error( 'baocache_timing_forbidden', __( 'Browser timing không hợp lệ.', 'baocache' ), array( 'status' => 403 ) );
		}
		if ( get_transient( self::RATE_LIMIT ) ) {
			return new WP_REST_Response( array( 'accepted' => false, 'reason' => 'rate_limited' ), 202 );
		}
		$groups = $this->sanitize_groups( $payload['groups'] ?? array() );
		if ( empty( $groups ) ) {
			return new WP_Error( 'baocache_timing_empty', __( 'Browser không có Resource Timing hợp lệ.', 'baocache' ), array( 'status' => 422 ) );
		}
		set_transient( self::RATE_LIMIT, '1', 15 * MINUTE_IN_SECONDS );
		$sample = array( 'recorded_at' => time(), 'groups' => $groups );
		$samples = get_option( self::OPTION, array() );
		$samples = is_array( $samples ) ? array_values( array_filter( $samples, 'is_array' ) ) : array();
		array_unshift( $samples, $sample );
		update_option( self::OPTION, array_slice( $samples, 0, self::MAX_SAMPLES ), false );
		return new WP_REST_Response( array( 'accepted' => true ), 201 );
	}

	/** @return array<int,array<string,int|string>> */
	private function sanitize_groups( mixed $groups ): array {
		if ( ! is_array( $groups ) ) {
			return array();
		}
		$clean = array();
		foreach ( array_slice( $groups, 0, self::MAX_GROUPS ) as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$source = strtolower( sanitize_text_field( (string) ( $group['source'] ?? '' ) ) );
			if ( 'same-site' !== $source && ! preg_match( '/\A[a-z0-9.-]{1,253}\z/', $source ) ) {
				continue;
			}
			$type = sanitize_key( (string) ( $group['type'] ?? '' ) );
			if ( ! in_array( $type, array( 'script', 'style', 'image', 'font', 'fetch', 'other' ), true ) ) {
				continue;
			}
			$extension = sanitize_key( (string) ( $group['extension'] ?? 'other' ) );
			if ( ! in_array( $extension, array( 'js', 'css', 'image', 'font', 'json', 'other' ), true ) ) {
				$extension = 'other';
			}
			$clean[] = array(
				'source' => $source,
				'type' => $type,
				'extension' => $extension,
				'count' => min( 250, max( 1, absint( $group['count'] ?? 0 ) ) ),
				'duration_ms' => min( 600000, max( 0, absint( $group['duration_ms'] ?? 0 ) ) ),
				'transfer_bytes' => min( 100 * MB_IN_BYTES, max( 0, absint( $group['transfer_bytes'] ?? 0 ) ) ),
			);
		}
		return $clean;
	}

	/** @return array<string,mixed> */
	public static function latest(): array {
		$samples = get_option( self::OPTION, array() );
		return is_array( $samples ) && isset( $samples[0] ) && is_array( $samples[0] ) ? $samples[0] : array();
	}

	public static function clear(): void {
		delete_option( self::OPTION );
		delete_transient( self::RATE_LIMIT );
	}
}
