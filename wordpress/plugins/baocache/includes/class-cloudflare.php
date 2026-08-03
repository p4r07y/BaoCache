<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cloudflare audit boundary.
 *
 * This class is intentionally read-only: it verifies an environment-provided
 * API token and reads one Zone record. Exact URL purge is separately opt-in;
 * this class never mutates rulesets, DNS, WAF, SSL, Workers or APO.
 */
final class BaoCache_Cloudflare {
	private const string API = 'https://api.cloudflare.com/client/v4';

	/** A configuration summary that never exposes the token or its source path. */
	public static function configuration(): array {
		$enabled = filter_var( getenv( 'BAOCACHE_CLOUDFLARE_AUDIT_ENABLED' ) ?: 'false', FILTER_VALIDATE_BOOLEAN );
		$zone_id = self::zone_id();
		$has_token = '' !== self::token();
		$ready = $enabled && '' !== $zone_id && $has_token;
		$purge_enabled = filter_var( getenv( 'BAOCACHE_CLOUDFLARE_PURGE_ENABLED' ) ?: 'false', FILTER_VALIDATE_BOOLEAN );
		$has_purge_token = '' !== self::purge_token();
		$missing = array();
		if ( ! $enabled ) $missing[] = 'BAOCACHE_CLOUDFLARE_AUDIT_ENABLED=true';
		if ( '' === $zone_id ) $missing[] = 'BAOCACHE_CLOUDFLARE_ZONE_ID';
		if ( ! $has_token ) $missing[] = 'BAOCACHE_CLOUDFLARE_API_TOKEN';

		return array(
			'enabled' => $enabled,
			'configured' => $ready,
			'missing' => $missing,
			'mode' => $ready && $purge_enabled && $has_purge_token ? 'exact-url-purge' : 'read-only',
			'purge_enabled' => $ready && $purge_enabled && $has_purge_token,
			'purge_missing' => array_values( array_filter( array(
				$purge_enabled ? '' : 'BAOCACHE_CLOUDFLARE_PURGE_ENABLED=true',
				$has_purge_token ? '' : 'BAOCACHE_CLOUDFLARE_PURGE_API_TOKEN',
			) ) ),
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
		$cache_rules = self::cache_rule_diagnostics();
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
			'cache_rules' => $cache_rules,
			'purge_enabled' => ! empty( $configuration['purge_enabled'] ),
		);
	}

	/** Read only: exposes a bounded cache-rule count, never rule expressions. */
	private static function cache_rule_diagnostics(): array {
		$response = self::get( '/zones/' . rawurlencode( self::zone_id() ) . '/rulesets/phases/http_request_cache_settings/entrypoint' );
		if ( ! $response['success'] || ! is_array( $response['result'] ) ) return array( 'state' => 'unavailable', 'count' => 0, 'http_status' => (int) $response['http_status'] );
		return array( 'state' => 'observed', 'count' => count( (array) ( $response['result']['rules'] ?? array() ) ), 'http_status' => (int) $response['http_status'] );
	}

	/** @return array<string, mixed>|WP_Error */
	public static function purge_exact_url( string $url ): array|WP_Error {
		$configuration = self::configuration();
		if ( empty( $configuration['purge_enabled'] ) ) return new WP_Error( 'cloudflare_purge_disabled', __( 'Cloudflare exact URL purge chưa được bật trong Coolify.', 'baocache' ) );
		$url = esc_url_raw( $url );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $url || $host !== $home_host || ! in_array( $scheme, array( 'http', 'https' ), true ) || false !== strpos( $url, '#' ) ) return new WP_Error( 'invalid_cloudflare_purge_url', __( 'Chỉ được purge một URL công khai cùng domain, không fragment.', 'baocache' ) );
		$response = self::post( '/zones/' . rawurlencode( self::zone_id() ) . '/purge_cache', array( 'files' => array( $url ) ), self::purge_token() );
		if ( ! $response['success'] ) return new WP_Error( 'cloudflare_purge_failed', __( 'Cloudflare không chấp nhận purge URL. Kiểm tra quyền Cache Purge và URL.', 'baocache' ) );
		return array( 'purged' => true, 'request_id' => sanitize_key( (string) ( $response['result']['id'] ?? '' ) ) );
	}

	private static function get( string $path ): array {
		return self::request( 'GET', $path );
	}

	private static function post( string $path, array $body, ?string $token = null ): array {
		return self::request( 'POST', $path, $body, $token );
	}

	private static function request( string $method, string $path, ?array $body = null, ?string $token = null ): array {
		$args = array(
			'method' => $method,
			'timeout' => 10,
			'redirection' => 0,
			'headers' => array( 'Authorization' => 'Bearer ' . ( $token ?? self::token() ), 'Accept' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request(
			self::API . $path,
			$args
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

	/** Kept separate from the audit token so Cache Purge is never granted to audit. */
	private static function purge_token(): string {
		return trim( (string) getenv( 'BAOCACHE_CLOUDFLARE_PURGE_API_TOKEN' ) );
	}
}
