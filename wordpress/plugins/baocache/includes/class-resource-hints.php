<?php
defined( 'ABSPATH' ) || exit;

/**
 * Evidence-driven origin hint advisor.
 *
 * Resource Timing intentionally gives BaoCache an origin, not an exact asset
 * URL. Therefore this class can safely recommend connection hints, but never
 * invents a preload URL or claims that a font is the LCP resource.
 */
final class BaoCache_Resource_Hints {
	private const string APPLICATION_OPTION = 'baocache_resource_hint_application';
	private const int MAX_RECOMMENDATIONS = 8;
	private const int MAX_APPLIED = 3;

	/** @return array<int,array<string,mixed>> */
	public static function recommendations( ?array $settings = null ): array {
		$settings = is_array( $settings ) ? $settings : BaoCache_Settings::get();
		$sample = BaoCache_Frontend_Metrics::latest();
		$groups = is_array( $sample['groups'] ?? null ) ? $sample['groups'] : array();
		if ( empty( $groups ) ) {
			return array();
		}
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$existing = array_map( array( __CLASS__, 'normalize_origin' ), array_merge(
			BaoCache_Settings::lines( (string) ( $settings['preconnect'] ?? '' ) ),
			BaoCache_Settings::lines( (string) ( $settings['dns_prefetch'] ?? '' ) )
		) );
		$seen = array();
		$items = array();
		foreach ( $groups as $group ) {
			$source = strtolower( sanitize_text_field( (string) ( $group['source'] ?? '' ) ) );
			$origin = self::origin_from_source( $source );
			if ( '' === $origin || $source === $site_host || 'same-site' === $source || in_array( self::normalize_origin( $origin ), $existing, true ) ) {
				continue;
			}
			$type = sanitize_key( (string) ( $group['type'] ?? 'other' ) );
			$extension = sanitize_key( (string) ( $group['extension'] ?? 'other' ) );
			$count = min( 250, max( 1, absint( $group['count'] ?? 0 ) ) );
			$duration = min( 600000, max( 0, absint( $group['duration_ms'] ?? 0 ) ) );
			$bytes = min( 100 * MB_IN_BYTES, max( 0, absint( $group['transfer_bytes'] ?? 0 ) ) );
			$score = 35 + min( 30, $count * 3 ) + min( 20, (int) floor( $duration / 100 ) ) + ( $bytes >= 10000 ? 10 : 0 );
			$is_font = 'font' === $type || 'font' === $extension;
			if ( $is_font ) {
				$score += 10;
			}
			$score = min( 99, $score );
			$action = 70 <= $score ? 'preconnect' : 'dns-prefetch';
			$key = $action . '|' . $source;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$fingerprint = hash( 'sha256', implode( '|', array( $source, $type, $extension, $count, $duration, $bytes ) ) );
			$items[] = array(
				'id' => substr( $fingerprint, 0, 16 ),
				'fingerprint' => $fingerprint,
				'origin' => $origin,
				'source' => $source,
				'action' => $action,
				'type' => $type,
				'extension' => $extension,
				'count' => $count,
				'duration_ms' => $duration,
				'transfer_bytes' => $bytes,
				'score' => $score,
				'is_font' => $is_font,
				'reason' => $is_font ? __( 'Font origin đã xuất hiện trong Resource Timing.', 'baocache' ) : __( 'External origin có request và thời gian tải quan sát được.', 'baocache' ),
			);
		}
		usort( $items, static fn( array $a, array $b ): int => (int) $b['score'] <=> (int) $a['score'] );
		return array_slice( $items, 0, self::MAX_RECOMMENDATIONS );
	}

	/** @return array<string,mixed>|WP_Error */
	public static function apply_safe_origins( array $ids ): array|WP_Error {
		$ids = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'baocache_hints_empty', __( 'Chưa chọn origin để áp dụng.', 'baocache' ) );
		}
		$settings = BaoCache_Settings::get();
		$recommendations = self::recommendations( $settings );
		$selected = array_values( array_filter( $recommendations, static fn( array $item ): bool => in_array( (string) $item['id'], $ids, true ) ) );
		$selected = array_slice( $selected, 0, self::MAX_APPLIED );
		if ( empty( $selected ) ) {
			return new WP_Error( 'baocache_hints_stale', __( 'Recommendation đã thay đổi hoặc evidence đã cũ. Hãy phân tích lại.', 'baocache' ) );
		}
		$before = array( 'preconnect' => (string) $settings['preconnect'], 'dns_prefetch' => (string) $settings['dns_prefetch'] );
		$preconnect = BaoCache_Settings::lines( (string) $settings['preconnect'] );
		$dns = BaoCache_Settings::lines( (string) $settings['dns_prefetch'] );
		foreach ( $selected as $item ) {
			if ( 'preconnect' === $item['action'] ) {
				$preconnect[] = (string) $item['origin'];
			} else {
				$dns[] = (string) $item['origin'];
			}
		}
		$next = BaoCache_Settings::sanitize( array_merge( $settings, array( 'preconnect' => implode( "\n", $preconnect ), 'dns_prefetch' => implode( "\n", $dns ) ) ) );
		$after = array( 'preconnect' => (string) $next['preconnect'], 'dns_prefetch' => (string) $next['dns_prefetch'] );
		update_option( BAOCACHE_OPTION, $next, false );
		$record = array( 'applied_at' => time(), 'before' => $before, 'after' => $after, 'ids' => array_map( static fn( array $item ): string => (string) $item['id'], $selected ), 'fingerprint' => hash( 'sha256', wp_json_encode( $after ) ?: '' ), 'plugin_version' => BAOCACHE_VERSION );
		update_option( self::APPLICATION_OPTION, $record, false );
		return array( 'record' => $record, 'count' => count( $selected ) );
	}

	/** @return array<string,mixed>|WP_Error */
	public static function rollback(): array|WP_Error {
		$record = get_option( self::APPLICATION_OPTION, array() );
		$record = is_array( $record ) ? $record : array();
		if ( empty( $record['before'] ) || ! is_array( $record['before'] ) ) {
			return new WP_Error( 'baocache_hints_no_application', __( 'Không có lần áp dụng origin hint để rollback.', 'baocache' ) );
		}
		$current = BaoCache_Settings::get();
		$after = is_array( $record['after'] ?? null ) ? $record['after'] : array();
		if ( (string) $current['preconnect'] !== (string) ( $after['preconnect'] ?? '' ) || (string) $current['dns_prefetch'] !== (string) ( $after['dns_prefetch'] ?? '' ) ) {
			return new WP_Error( 'baocache_hints_stale', __( 'Cấu hình origin hint đã thay đổi sau khi apply; rollback bị chặn để tránh ghi đè chỉnh sửa mới.', 'baocache' ) );
		}
		$next = BaoCache_Settings::sanitize( array_merge( $current, array( 'preconnect' => (string) ( $record['before']['preconnect'] ?? '' ), 'dns_prefetch' => (string) ( $record['before']['dns_prefetch'] ?? '' ) ) ) );
		update_option( BAOCACHE_OPTION, $next, false );
		delete_option( self::APPLICATION_OPTION );
		return array( 'rolled_back' => true );
	}

	/** @return array<string,mixed> */
	public static function application(): array {
		$value = get_option( self::APPLICATION_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	private static function origin_from_source( string $source ): string {
		if ( '' === $source || ! preg_match( '/\A(?:[a-z0-9-]+\.)+[a-z]{2,63}\z/i', $source ) ) {
			return '';
		}
		return 'https://' . $source;
	}

	private static function normalize_origin( string $url ): string {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return ( '' !== $scheme && '' !== $host ) ? $scheme . '://' . $host : strtolower( untrailingslashit( trim( $url ) ) );
	}
}
