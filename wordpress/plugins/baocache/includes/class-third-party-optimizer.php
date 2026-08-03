<?php
defined( 'ABSPATH' ) || exit;

/** Conservative, evidence-driven third-party script delay recommendations. */
final class BaoCache_Third_Party_Optimizer {
	private const SNAPSHOT_OPTION = 'baocache_third_party_snapshot';
	private const APPLICATION_OPTION = 'baocache_third_party_application';

	public static function snapshot(): array {
		$value = get_option( self::SNAPSHOT_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function application(): array {
		$value = get_option( self::APPLICATION_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return array<string, mixed>|WP_Error */
	public static function scan(): array|WP_Error {
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory ) && is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		if ( empty( $assets ) ) return new WP_Error( 'no_third_party_evidence', __( 'Chưa có Asset Inventory evidence. Hãy quét asset trước khi phân tích third-party script.', 'baocache' ) );
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$delay = BaoCache_Settings::lines( (string) BaoCache_Settings::get()['delay_handles'] );
		$defer = BaoCache_Settings::lines( (string) BaoCache_Settings::get()['defer_handles'] );
		$candidates = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || 'script' !== (string) ( $asset['type'] ?? '' ) || ! empty( $asset['inline'] ) ) continue;
			$handle = sanitize_key( (string) ( $asset['handle'] ?? '' ) );
			$source = trim( (string) ( $asset['source'] ?? '' ) );
			$parts = wp_parse_url( $source );
			$host = strtolower( (string) ( $parts['host'] ?? '' ) );
			if ( '' === $handle || '' === $host || $host === $home_host || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) continue;
			$dependencies = array_values( array_filter( array_map( 'sanitize_key', (array) ( $asset['dependencies'] ?? array() ) ) ) );
			if ( ! empty( $dependencies ) || in_array( $handle, $delay, true ) || in_array( $handle, $defer, true ) || self::sensitive_handle( $handle ) ) continue;
			$candidates[] = array( 'handle' => $handle, 'host' => $host, 'source' => $source, 'dependencies' => $dependencies, 'strategy' => 'delay', 'risk' => self::risk( $handle ), 'reason' => __( 'Third-party script độc lập trong Asset Inventory; chưa được defer/delay.', 'baocache' ) );
		}
		$candidates = array_slice( $candidates, 0, 8 );
		$fingerprint = substr( hash( 'sha256', (string) wp_json_encode( $candidates ) ), 0, 16 );
		$snapshot = array( 'schema' => 1, 'scanned_at' => time(), 'inventory_captured_at' => (int) ( $inventory['captured_at'] ?? 0 ), 'candidate_count' => count( $candidates ), 'candidates' => $candidates, 'fingerprint' => $fingerprint );
		update_option( self::SNAPSHOT_OPTION, $snapshot, false );
		return $snapshot;
	}

	public static function apply( string $fingerprint ): array|WP_Error {
		$snapshot = self::snapshot();
		if ( '' === $fingerprint || ! hash_equals( (string) ( $snapshot['fingerprint'] ?? '' ), $fingerprint ) ) return new WP_Error( 'stale_third_party_evidence', __( 'Third-party evidence đã cũ. Hãy quét lại Asset Inventory.', 'baocache' ) );
		$candidates = is_array( $snapshot['candidates'] ?? null ) ? $snapshot['candidates'] : array();
		if ( empty( $candidates ) ) return new WP_Error( 'no_third_party_candidates', __( 'Không có third-party script đủ an toàn để đề xuất delay.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		$before = (string) $settings['delay_handles'];
		$handles = BaoCache_Settings::lines( $before );
		foreach ( $candidates as $candidate ) {
			$handle = sanitize_key( (string) ( $candidate['handle'] ?? '' ) );
			if ( '' !== $handle && ! in_array( $handle, $handles, true ) ) $handles[] = $handle;
		}
		$settings['delay_handles'] = implode( "\n", $handles );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record = array( 'applied_at' => time(), 'snapshot_fingerprint' => $fingerprint, 'before' => $before, 'after' => $settings['delay_handles'], 'rolled_back_at' => 0 );
		update_option( self::APPLICATION_OPTION, $record, false );
		return array( 'snapshot' => $snapshot, 'application' => $record );
	}

	public static function rollback(): array|WP_Error {
		$record = self::application();
		if ( empty( $record['applied_at'] ) || ! empty( $record['rolled_back_at'] ) ) return new WP_Error( 'nothing_to_rollback', __( 'Không có third-party delay apply đang hoạt động để rollback.', 'baocache' ) );
		$settings = BaoCache_Settings::get();
		if ( (string) $settings['delay_handles'] !== (string) ( $record['after'] ?? '' ) ) return new WP_Error( 'stale_third_party_application', __( 'Delay handles đã thay đổi sau apply; rollback tự động bị chặn.', 'baocache' ) );
		$settings['delay_handles'] = (string) ( $record['before'] ?? '' );
		update_option( BAOCACHE_OPTION, $settings, false );
		$record['rolled_back_at'] = time();
		update_option( self::APPLICATION_OPTION, $record, false );
		return $record;
	}

	private static function sensitive_handle( string $handle ): bool {
		return (bool) preg_match( '/(?:jquery|woocommerce|wc-|cart|checkout|payment|stripe|paypal|recaptcha|captcha|map|menu|navigation|consent|cookie)/i', $handle );
	}

	private static function risk( string $handle ): string {
		return preg_match( '/(?:analytics|gtag|gtm|pixel|clarity|hotjar|chat|intercom|tawk|onesignal)/i', $handle ) ? 'low' : 'review';
	}
}
