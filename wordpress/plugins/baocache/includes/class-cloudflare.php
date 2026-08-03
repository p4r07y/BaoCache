<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cloudflare audit boundary.
 *
 * This class is intentionally read-only: it verifies an environment-provided
 * API token and reads one Zone record. It contains no cache-purge, ruleset
 * mutation, DNS, WAF, SSL, Worker or APO endpoint.
 */
final class BaoCache_Cloudflare {
	private const string API = 'https://api.cloudflare.com/client/v4';

	/** A configuration summary that never exposes the token or its source path. */
	public static function configuration(): array {
		$enabled = filter_var( getenv( 'BAOCACHE_CLOUDFLARE_AUDIT_ENABLED' ) ?: 'false', FILTER_VALIDATE_BOOLEAN );
		$zone_id = self::zone_id();
		$has_token = '' !== self::token();
		$ready = $enabled && '' !== $zone_id && $has_token;
		$missing = array();
		if ( ! $enabled ) $missing[] = 'BAOCACHE_CLOUDFLARE_AUDIT_ENABLED=true';
		if ( '' === $zone_id ) $missing[] = 'BAOCACHE_CLOUDFLARE_ZONE_ID';
		if ( ! $has_token ) $missing[] = 'BAOCACHE_CLOUDFLARE_API_TOKEN';

		return array(
			'enabled' => $enabled,
			'configured' => $ready,
			'missing' => $missing,
			'mode' => 'read-only',
		);
	}

	/**
	 * Verifies the token and reads a Zone. The returned data is deliberately
	 * small so it remains safe for the UI and activity log.
	 */
	public static function audit(): array {
		$configuration = self::configuration();
		if ( ! $configuration['configured'] ) {
			return array( 'success' => false, 'state' => 'not_configured', 'message' => __( 'Cloudflare audit chưa được cấu hình đầy đủ trong Coolify.', 'baocache' ) );
		}

		$token_check = self::get( '/user/tokens/verify' );
		if ( ! $token_check['success'] ) {
			return array( 'success' => false, 'state' => 'token_failed', 'http_status' => $token_check['http_status'], 'message' => __( 'Không xác minh được Cloudflare API token. Kiểm tra Coolify Secret và quyền Zone Read.', 'baocache' ) );
		}

		$zone = self::get( '/zones/' . rawurlencode( self::zone_id() ) );
		if ( ! $zone['success'] || ! is_array( $zone['result'] ) ) {
			return array( 'success' => false, 'state' => 'zone_failed', 'http_status' => $zone['http_status'], 'message' => __( 'Token đã hợp lệ nhưng không đọc được Zone. Kiểm tra Zone ID và phạm vi Zone Read.', 'baocache' ) );
		}

		$result = $zone['result'];
		return array(
			'success' => true,
			'state' => 'passed',
			'mode' => 'read-only',
			'token_verified' => true,
			'zone' => sanitize_text_field( (string) ( $result['name'] ?? '' ) ),
			'zone_status' => sanitize_key( (string) ( $result['status'] ?? '' ) ),
			'zone_type' => sanitize_key( (string) ( $result['type'] ?? '' ) ),
			'paused' => ! empty( $result['paused'] ),
			// Cloudflare returns seconds: positive means active, negative/zero means off.
			'development_mode' => (int) ( $result['development_mode'] ?? 0 ) > 0,
		);
	}

	private static function get( string $path ): array {
		$response = wp_remote_get(
			self::API . $path,
			array(
				'timeout' => 10,
				'redirection' => 0,
				'headers' => array(
					'Authorization' => 'Bearer ' . self::token(),
					'Accept' => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'http_status' => 0, 'result' => null );
		}
		$http_status = (int) wp_remote_retrieve_response_code( $response );
		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return array(
			'success' => 200 <= $http_status && $http_status < 300 && is_array( $payload ) && ! empty( $payload['success'] ),
			'http_status' => $http_status,
			'result' => is_array( $payload ) ? ( $payload['result'] ?? null ) : null,
		);
	}

	private static function zone_id(): string {
		$zone_id = trim( (string) getenv( 'BAOCACHE_CLOUDFLARE_ZONE_ID' ) );
		return preg_match( '/^[a-f0-9]{32}$/i', $zone_id ) ? strtolower( $zone_id ) : '';
	}

	private static function token(): string {
		$token = trim( (string) getenv( 'BAOCACHE_CLOUDFLARE_API_TOKEN' ) );
		if ( '' !== $token ) return $token;
		$path = trim( (string) getenv( 'BAOCACHE_CLOUDFLARE_API_TOKEN_FILE' ) );
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) return '';
		$size = filesize( $path );
		if ( false === $size || $size < 1 || $size > 8192 ) return '';
		$value = file_get_contents( $path );
		return is_string( $value ) ? trim( $value ) : '';
	}
}
