<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small, durable hourly runtime snapshots. These are observations, never
 * synthetic traffic or a calculated performance score.
 */
final class BaoCache_Metrics {
	private const string HOOK = 'baocache_runtime_snapshot';
	private const string OPTION = 'baocache_runtime_snapshots';
	private const int MAX_SNAPSHOTS = 24 * 30;

	public function register(): void {
		add_action( self::HOOK, array( $this, 'snapshot' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ), 20 );
	}

	public static function activate(): void {
		( new self() )->ensure_scheduled();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', self::HOOK );
		}
	}

	/** Store one actual observation for the current UTC hour, retaining 30 days. */
	/** @return array<string,mixed> */
	public function snapshot(): array {
		if ( get_transient( 'baocache_runtime_snapshot_lock' ) ) {
			return array();
		}
		set_transient( 'baocache_runtime_snapshot_lock', '1', MINUTE_IN_SECONDS );
		delete_transient( 'baocache_fastcgi_metrics' );
		delete_transient( 'baocache_redis_metrics' );
		$recorded_at = time();
		$fastcgi = BaoCache_Diagnostics::fastcgi_metrics();
		$redis = BaoCache_Diagnostics::redis_metrics();
		$php = BaoCache_Diagnostics::php_runtime();
		$warmup = BaoCache_Warmup::status();
		$record = array(
			'recorded_at' => $recorded_at,
			'hour' => (int) floor( $recorded_at / HOUR_IN_SECONDS ),
			'fastcgi' => array( 'available' => (bool) $fastcgi['available'], 'total_24h' => (int) $fastcgi['total'], 'hit_ratio_24h' => $fastcgi['hit_ratio'] ),
			'redis' => array( 'connected' => (bool) $redis['connected'], 'latency_ms' => $redis['latency_ms'], 'hit_ratio' => $redis['hit_ratio'], 'memory' => $redis['memory'] ),
			'warm_queue' => array( 'scheduled' => ! empty( $warmup['scheduled'] ), 'queued' => (int) ( $warmup['queued'] ?? 0 ), 'last_run_at' => (int) ( $warmup['last_run_at'] ?? 0 ) ),
			'php' => array( 'opcache_enabled' => (bool) $php['opcache_enabled'], 'opcache_hit_rate' => $php['opcache_hit_rate'] ),
		);
		$history = get_option( self::OPTION, array() );
		$history = is_array( $history ) ? array_values( array_filter( $history, 'is_array' ) ) : array();
		$history = array_values( array_filter( $history, static fn( array $item ): bool => (int) ( $item['hour'] ?? -1 ) !== $record['hour'] ) );
		array_unshift( $history, $record );
		update_option( self::OPTION, array_slice( $history, 0, self::MAX_SNAPSHOTS ), false );
		delete_transient( 'baocache_runtime_snapshot_lock' );
		return $record;
	}

	/** @return array<int,array<string,mixed>> */
	public static function history( int $hours = self::MAX_SNAPSHOTS ): array {
		$hours = max( 1, min( self::MAX_SNAPSHOTS, $hours ) );
		$since = time() - ( $hours * HOUR_IN_SECONDS );
		$history = get_option( self::OPTION, array() );
		if ( ! is_array( $history ) ) {
			return array();
		}
		return array_values( array_filter( $history, static fn( mixed $item ): bool => is_array( $item ) && (int) ( $item['recorded_at'] ?? 0 ) >= $since ) );
	}

	/**
	 * Returns only observed points. A range is usable only after 75% of its
	 * duration is covered and four snapshots exist; this prevents invented charts.
	 *
	 * @return array<string,mixed>
	 */
	public static function window( int $hours ): array {
		$hours = max( 1, min( self::MAX_SNAPSHOTS, $hours ) );
		$items = array_reverse( self::history( $hours ) );
		$first = $items[0] ?? array();
		$last = $items ? $items[ count( $items ) - 1 ] : array();
		$coverage_seconds = max( 0, (int) ( $last['recorded_at'] ?? 0 ) - (int) ( $first['recorded_at'] ?? 0 ) );
		$required_seconds = (int) floor( $hours * HOUR_IN_SECONDS * 0.75 );
		$series = array( 'fastcgi_hit_ratio' => array(), 'redis_latency' => array(), 'redis_hit_ratio' => array() );
		foreach ( $items as $item ) {
			$at = (int) ( $item['recorded_at'] ?? 0 );
			foreach ( array( 'fastcgi_hit_ratio' => array( 'fastcgi', 'hit_ratio_24h' ), 'redis_latency' => array( 'redis', 'latency_ms' ), 'redis_hit_ratio' => array( 'redis', 'hit_ratio' ) ) as $key => $path ) {
				$value = $item[ $path[0] ][ $path[1] ] ?? null;
				if ( is_numeric( $value ) ) {
					$series[ $key ][] = array( 'at' => $at, 'value' => (float) $value );
				}
			}
		}
		return array(
			'hours' => $hours,
			'samples' => count( $items ),
			'coverage_seconds' => $coverage_seconds,
			'required_seconds' => $required_seconds,
			'ready' => count( $items ) >= 4 && $coverage_seconds >= $required_seconds,
			'series' => $series,
		);
	}
}
