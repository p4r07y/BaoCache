<?php
defined( 'ABSPATH' ) || exit;

/** Runtime measurements gathered directly from the configured Redis service. */
final class BaoCache_Diagnostics {
	public static function fastcgi_metrics(): array {
		$cached = get_transient( 'baocache_fastcgi_metrics' );
		if ( is_array( $cached ) ) return $cached;
		$result = array( 'available' => false, 'total' => 0, 'hit_ratio' => null, 'statuses' => array(), 'bypass_reasons' => array() );
		$url = getenv( 'BAOCACHE_METRICS_URL' ); $token_file = getenv( 'BAOCACHE_METRICS_TOKEN_FILE' );
		$token = is_string( $token_file ) && is_readable( $token_file ) ? trim( (string) file_get_contents( $token_file ) ) : '';
		if ( ! is_string( $url ) || '' === $url || '' === $token ) return $result;
		$response = wp_remote_get( $url, array( 'timeout' => 1, 'headers' => array( 'X-BaoCache-Metrics' => $token ) ) );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( is_array( $data ) ) $result = array( 'available' => true, 'total' => absint( $data['total'] ?? 0 ), 'hit_ratio' => isset( $data['hit_ratio'] ) ? (float) $data['hit_ratio'] : null, 'statuses' => is_array( $data['statuses'] ?? null ) ? $data['statuses'] : array(), 'bypass_reasons' => is_array( $data['bypass_reasons'] ?? null ) ? $data['bypass_reasons'] : array() );
		}
		set_transient( 'baocache_fastcgi_metrics', $result, MINUTE_IN_SECONDS );
		return $result;
	}

	public static function redis_metrics(): array {
		$cached = get_transient( 'baocache_redis_metrics' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$metrics = array(
			'connected' => false,
			'latency_ms' => null,
			'memory' => null,
			'memory_max' => null,
			'hit_ratio' => null,
			'error' => '',
		);
		if ( ! class_exists( 'Redis' ) || ! defined( 'WP_REDIS_HOST' ) ) {
			$metrics['error'] = __( 'PhpRedis hoặc cấu hình Redis chưa sẵn sàng.', 'baocache' );
			return $metrics;
		}

		try {
			$client = new Redis();
			$started = hrtime( true );
			$connected = $client->connect(
				(string) WP_REDIS_HOST,
				defined( 'WP_REDIS_PORT' ) ? (int) WP_REDIS_PORT : 6379,
				min( 1.0, defined( 'WP_REDIS_TIMEOUT' ) ? (float) WP_REDIS_TIMEOUT : 1.0 )
			);
			if ( ! $connected ) {
				throw new RuntimeException( 'Redis connection failed.' );
			}
			if ( defined( 'WP_REDIS_PASSWORD' ) && '' !== (string) WP_REDIS_PASSWORD ) {
				$client->auth( (string) WP_REDIS_PASSWORD );
			}
			if ( defined( 'WP_REDIS_DATABASE' ) ) {
				$client->select( (int) WP_REDIS_DATABASE );
			}
			$client->ping();
			$metrics['latency_ms'] = round( ( hrtime( true ) - $started ) / 1000000, 2 );
			$memory = $client->info( 'memory' );
			$stats = $client->info( 'stats' );
			$metrics['connected'] = true;
			$metrics['memory'] = isset( $memory['used_memory'] ) ? (int) $memory['used_memory'] : null;
			$metrics['memory_max'] = isset( $memory['maxmemory'] ) ? (int) $memory['maxmemory'] : null;
			$hits = isset( $stats['keyspace_hits'] ) ? (int) $stats['keyspace_hits'] : 0;
			$misses = isset( $stats['keyspace_misses'] ) ? (int) $stats['keyspace_misses'] : 0;
			if ( $hits + $misses > 0 ) {
				$metrics['hit_ratio'] = round( ( $hits / ( $hits + $misses ) ) * 100, 1 );
			}
			$client->close();
			set_transient( 'baocache_redis_metrics', $metrics, MINUTE_IN_SECONDS );
		} catch ( Throwable $exception ) {
			$metrics['error'] = __( 'Không thể đọc Redis runtime metrics.', 'baocache' );
			set_transient( 'baocache_redis_metrics', $metrics, MINUTE_IN_SECONDS );
		}

		return $metrics;
	}

	/** PHP signals exposed without changing OPcache/JIT configuration. */
	public static function php_runtime(): array {
		$result = array( 'opcache_enabled' => false, 'opcache_used' => null, 'opcache_total' => null, 'opcache_hit_rate' => null, 'jit_enabled' => false );
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return $result;
		}
		$status = @opcache_get_status( false );
		if ( ! is_array( $status ) || empty( $status['opcache_enabled'] ) ) {
			return $result;
		}
		$result['opcache_enabled'] = true;
		$memory = is_array( $status['memory_usage'] ?? null ) ? $status['memory_usage'] : array();
		$statistics = is_array( $status['opcache_statistics'] ?? null ) ? $status['opcache_statistics'] : array();
		$result['opcache_used'] = isset( $memory['used_memory'] ) ? (int) $memory['used_memory'] : null;
		$result['opcache_total'] = isset( $memory['used_memory'], $memory['free_memory'], $memory['wasted_memory'] ) ? (int) $memory['used_memory'] + (int) $memory['free_memory'] + (int) $memory['wasted_memory'] : null;
		$result['opcache_hit_rate'] = isset( $statistics['opcache_hit_rate'] ) ? round( (float) $statistics['opcache_hit_rate'], 1 ) : null;
		$config = function_exists( 'opcache_get_configuration' ) ? @opcache_get_configuration() : array();
		$result['jit_enabled'] = is_array( $config ) && ! empty( $config['directives']['opcache.jit'] ) && '0' !== (string) $config['directives']['opcache.jit'];
		return $result;
	}

	public static function bytes( ?int $bytes ): string {
		if ( null === $bytes || $bytes < 0 ) {
			return __( 'Chưa có dữ liệu', 'baocache' );
		}
		return size_format( $bytes, 1 );
	}
}
