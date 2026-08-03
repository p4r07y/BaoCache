<?php
defined( 'ABSPATH' ) || exit;

/** Evidence-bounded recommendations for connection and font hints. */
final class BaoCache_Resource_Hints {
	private const SNAPSHOT_OPTION = 'baocache_resource_hint_snapshot';
	private const APPLICATION_OPTION = 'baocache_resource_hint_application';

	/** @return array<string, mixed> */
	public static function snapshot(): array {
		$value = get_option( self::SNAPSHOT_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return array<string, mixed> */
	public static function application(): array {
		$value = get_option( self::APPLICATION_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return array<string, mixed>|WP_Error */
	public static function scan(): array|WP_Error {
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory ) && is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		if ( empty( $assets ) ) {
			return new WP_Error( 'no_asset_evidence', __( 'Chưa có Asset Inventory evidence. Hãy quét asset trước khi đề xuất resource hint.', 'baocache' ) );
		}

		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$origins = array();
		$fonts = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) continue;
			$url = trim( (string) ( $asset['source'] ?? '' ) );
			if ( '' === $url || str_starts_with( $url, 'Inline ' ) ) continue;
			if ( str_starts_with( $url, '/' ) ) $url = home_url( $url );
			$parts = wp_parse_url( $url );
			$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
			$host = strtolower( (string) ( $parts['host'] ?? '' ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host || $host === $home_host ) continue;
			$origin = $scheme . '://' . $host;
			if ( isset( $parts['port'] ) ) $origin .= ':' . (int) $parts['port'];
			$origins[ $origin ] = array( 'type' => 'preconnect', 'value' => $origin, 'source' => $url, 'handle' => sanitize_key( (string) ( $asset['handle'] ?? '' ) ), 'confidence' => 70 );
			$extension = strtolower( pathinfo( (string) ( $parts['path'] ?? '' ), PATHINFO_EXTENSION ) );
			if ( in_array( $extension, array( 'woff', 'woff2', 'ttf', 'otf' ), true ) ) {
				$fonts[ $url ] = array( 'type' => 'preload', 'value' => $url, 'as' => 'font', 'source' => $url, 'handle' => sanitize_key( (string) ( $asset['handle'] ?? '' ) ), 'confidence' => 85 );
			}
		}

		$candidates = array_merge( array_slice( array_values( $origins ), 0, 6 ), array_slice( array_values( $fonts ), 0, 4 ) );
		$fingerprint = substr( hash( 'sha256', (string) wp_json_encode( $candidates ) ), 0, 16 );
		$snapshot = array( 'schema' => 1, 'scanned_at' => time(), 'inventory_captured_at' => (int) ( $inventory['captured_at'] ?? 0 ), 'candidate_count' => count( $candidates ), 'candidates' => $candidates, 'fingerprint' => $fingerprint );
		update_option( self::SNAPSHOT_OPTION, $snapshot, false );
		return $snapshot;
	}

	/** @return array<string, mixed>|WP_Error */
	public static function apply( string $fingerprint ): array|WP_Error {
		$snapshot = self::snapshot();
		if ( '' === $fingerprint || ! hash_equals( (string) ( $snapshot['fingerprint'] ?? '' ), $fingerprint ) ) {
			return new WP_Error( 'stale_hint_evidence', __( 'Evidence resource hint đã cũ. Hãy quét lại Asset Inventory.', 'baocache' ) );
		}
		$candidates = is_array( $snapshot['candidates'] ?? null ) ? $snapshot['candidates'] : array();
		if ( empty( $candidates ) ) return new WP_Error( 'no_hint_candidates', __( 'Không có recommendation hợp lệ để áp dụng.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		$before = array( 'preconnect' => (string) $settings['preconnect'], 'dns_prefetch' => (string) $settings['dns_prefetch'], 'preload' => (string) $settings['preload'] );
		$preconnect = BaoCache_Settings::lines( $before['preconnect'] );
		$preload = BaoCache_Settings::lines( $before['preload'] );
		foreach ( $candidates as $candidate ) {
			$type = (string) ( $candidate['type'] ?? '' );
			$value = (string) ( $candidate['value'] ?? '' );
			if ( 'preconnect' === $type && '' !== $value ) $preconnect[] = $value;
			if ( 'preload' === $type && 'font' === (string) ( $candidate['as'] ?? '' ) && '' !== $value ) $preload[] = $value;
		}
		$settings['preconnect'] = implode( "\n", array_values( array_unique( $preconnect ) ) );
		$settings['preload'] = implode( "\n", array_values( array_unique( $preload ) ) );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record = array( 'applied_at' => time(), 'snapshot_fingerprint' => $fingerprint, 'before' => $before, 'after' => array( 'preconnect' => $settings['preconnect'], 'dns_prefetch' => $settings['dns_prefetch'], 'preload' => $settings['preload'] ), 'rolled_back_at' => 0 );
		update_option( self::APPLICATION_OPTION, $record, false );
		return array( 'snapshot' => $snapshot, 'application' => $record );
	}

	/** @return array<string, mixed>|WP_Error */
	public static function rollback(): array|WP_Error {
		$record = self::application();
		if ( empty( $record['applied_at'] ) || ! empty( $record['rolled_back_at'] ) ) return new WP_Error( 'nothing_to_rollback', __( 'Không có Resource Hint apply đang hoạt động để rollback.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		$after = is_array( $record['after'] ?? null ) ? $record['after'] : array();
		foreach ( array( 'preconnect', 'dns_prefetch', 'preload' ) as $key ) {
			if ( (string) $settings[ $key ] !== (string) ( $after[ $key ] ?? '' ) ) return new WP_Error( 'stale_application', __( 'Cấu hình Resource Hint đã thay đổi sau apply; rollback tự động bị chặn.', 'baocache' ) );
		}
		$before = is_array( $record['before'] ?? null ) ? $record['before'] : array();
		foreach ( array( 'preconnect', 'dns_prefetch', 'preload' ) as $key ) $settings[ $key ] = (string) ( $before[ $key ] ?? '' );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record['rolled_back_at'] = time();
		update_option( self::APPLICATION_OPTION, $record, false );
		return $record;
	}
}
