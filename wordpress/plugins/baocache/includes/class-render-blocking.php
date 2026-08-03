<?php
defined( 'ABSPATH' ) || exit;

/**
 * Parses administrator-provided Lighthouse/PageSpeed JSON into a small,
 * handle-aware audit. It never fetches a third-party report or changes runtime
 * settings by itself.
 */
final class BaoCache_Render_Blocking {
	private const string AUDIT_OPTION = 'baocache_render_blocking_audit';
	private const string LOG_OPTION = 'baocache_render_blocking_log';
	private const string CRITICAL_OPTION = 'baocache_critical_css';
	private const string GATE_OPTION = 'baocache_rule_compatibility_gates';
	private const string GATE_HISTORY_OPTION = 'baocache_rule_compatibility_gate_history';
	private const string GATE_ACK_OPTION = 'baocache_rule_compatibility_gate_ack';
	private const string GATE_REVIEW_OPTION = 'baocache_rule_compatibility_gate_review';
	private const int GATE_HISTORY_MAX = 200;
	private const int GATE_HISTORY_RETENTION_DAYS = 90;

	public static function audit(): array {
		$value = get_option( self::AUDIT_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function parse( string $json, array $inventory = array(), string $snapshot = 'after' ): array|WP_Error {
		if ( strlen( $json ) > 2097152 ) {
			return new WP_Error( 'baocache_audit_large', __( 'Lighthouse JSON vượt quá giới hạn 2 MB.', 'baocache' ) );
		}
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['audits'] ) || ! is_array( $decoded['audits'] ) ) {
			return new WP_Error( 'baocache_audit_invalid', __( 'JSON không phải báo cáo Lighthouse/PageSpeed hợp lệ.', 'baocache' ) );
		}
		$snapshot = in_array( $snapshot, array( 'baseline', 'after' ), true ) ? $snapshot : 'after';
		$metrics = array();
		foreach ( array( 'first-contentful-paint' => 'fcp', 'largest-contentful-paint' => 'lcp', 'cumulative-layout-shift' => 'cls', 'total-blocking-time' => 'tbt' ) as $audit_id => $key ) {
			$audit = is_array( $decoded['audits'][ $audit_id ] ?? null ) ? $decoded['audits'][ $audit_id ] : array();
			$value = isset( $audit['numericValue'] ) && is_numeric( $audit['numericValue'] ) ? round( (float) $audit['numericValue'], 2 ) : null;
			if ( null !== $value ) {
				$metrics[ $key ] = $value;
			}
		}
		$items = array();
		$render_audit = is_array( $decoded['audits']['render-blocking-resources'] ?? null ) ? $decoded['audits']['render-blocking-resources'] : array();
		foreach ( (array) ( $render_audit['details']['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || '' === (string) ( $item['url'] ?? '' ) ) {
				continue;
			}
			$url = esc_url_raw( strtok( (string) $item['url'], '?' ) );
			if ( '' === $url ) {
				continue;
			}
			$match = self::map_asset( $url, $inventory );
			$items[] = array(
				'url' => $url,
				'host' => (string) ( wp_parse_url( $url, PHP_URL_HOST ) ?: '' ),
				'handle' => (string) ( $match['handle'] ?? '' ),
				'type' => (string) ( $match['type'] ?? self::resource_type( $url ) ),
				'size_bytes' => isset( $item['totalBytes'] ) && is_numeric( $item['totalBytes'] ) ? absint( $item['totalBytes'] ) : ( $match['size_bytes'] ?? null ),
				'wasted_ms' => isset( $item['wastedMs'] ) && is_numeric( $item['wastedMs'] ) ? round( (float) $item['wastedMs'], 2 ) : 0,
				'mapped' => ! empty( $match['handle'] ),
			);
		}
		$record = array( 'captured_at' => time(), 'snapshot' => $snapshot, 'metrics' => $metrics, 'items' => array_slice( $items, 0, 100 ), 'source' => 'lighthouse-json' );
		$current = self::audit();
		$current['snapshots'][ $snapshot ] = $record;
		$current['updated_at'] = time();
		$current['comparison'] = self::comparison( $current['snapshots'] );
		update_option( self::AUDIT_OPTION, $current, false );
		return $current;
	}

	public static function comparison( array $snapshots ): array {
		$before = is_array( $snapshots['baseline'] ?? null ) ? (array) $snapshots['baseline'] : array();
		$after = is_array( $snapshots['after'] ?? null ) ? (array) $snapshots['after'] : array();
		if ( empty( $before['metrics'] ) || empty( $after['metrics'] ) ) {
			return array();
		}
		$delta = array();
		foreach ( array( 'fcp', 'lcp', 'cls', 'tbt' ) as $key ) {
			if ( isset( $before['metrics'][ $key ], $after['metrics'][ $key ] ) ) {
				$delta[ $key ] = round( (float) $after['metrics'][ $key ] - (float) $before['metrics'][ $key ], 2 );
			}
		}
		$baseline_total = array_sum( array_map( static fn( array $item ): float => (float) ( $item['wasted_ms'] ?? 0 ), (array) ( $before['items'] ?? array() ) ) );
		$after_total = array_sum( array_map( static fn( array $item ): float => (float) ( $item['wasted_ms'] ?? 0 ), (array) ( $after['items'] ?? array() ) ) );
		$delta['estimated_savings_ms'] = round( max( 0, $baseline_total - $after_total ), 2 );
		return $delta;
	}

	public static function record_strategy( string $handle, string $strategy, string $reason, string $context, bool $rolled_back = false ): void {
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		$log[] = array( 'at' => time(), 'handle' => sanitize_key( $handle ), 'strategy' => sanitize_key( $strategy ), 'reason' => sanitize_text_field( $reason ), 'context' => sanitize_text_field( $context ), 'rolled_back' => $rolled_back );
		update_option( self::LOG_OPTION, array_slice( $log, -100 ), false );
	}

	public static function strategy_log(): array {
		$value = get_option( self::LOG_OPTION, array() );
		return is_array( $value ) ? array_reverse( array_slice( $value, -100 ) ) : array();
	}

	public static function compatibility_gates(): array {
		$value = get_option( self::GATE_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function gate_review(): array {
		$value = get_option( self::GATE_REVIEW_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** Review every saved gate without storing URLs or resource payloads. */
	public static function review_gate_evidence(): array {
		$stale = array();
		$gates = self::compatibility_gates();
		foreach ( $gates as $key => $record ) {
			if ( ! is_array( $record ) ) continue;
			$key_parts = explode( '__', (string) $key, 2 );
			$handle = sanitize_key( (string) ( $record['handle'] ?? ( $key_parts[0] ?? '' ) ) );
			$strategy = sanitize_key( (string) ( $record['strategy'] ?? ( $key_parts[1] ?? '' ) ) );
			$gate = self::compatibility_gate( $handle, $strategy );
			if ( ! empty( $gate['stale'] ) ) {
				$stale[] = array( 'handle' => $handle, 'strategy' => $strategy, 'reason' => (string) ( $gate['stale_reason'] ?? '' ), 'acknowledged' => ! empty( $gate['acknowledged'] ), 'evidence_ref' => (string) ( $gate['evidence_ref'] ?? '' ) );
			}
		}
		$review = array( 'reviewed_at' => time(), 'environment' => wp_get_environment_type(), 'total' => count( $gates ), 'stale_count' => count( $stale ), 'stale' => array_slice( $stale, 0, 100 ) );
		update_option( self::GATE_REVIEW_OPTION, $review, false );
		return $review;
	}

	public static function gate_history( string $handle = '', string $strategy = '', int $limit = 12 ): array {
		$value = get_option( self::GATE_HISTORY_OPTION, array() );
		$history = is_array( $value ) ? $value : array();
		$handle = sanitize_key( $handle );
		$strategy = sanitize_key( $strategy );
		if ( '' !== $handle || '' !== $strategy ) {
			$history = array_values( array_filter( $history, static function ( $entry ) use ( $handle, $strategy ): bool {
				if ( ! is_array( $entry ) ) return false;
				return ( '' === $handle || $handle === (string) ( $entry['handle'] ?? '' ) ) && ( '' === $strategy || $strategy === (string) ( $entry['strategy'] ?? '' ) );
			} ) );
		}
		return array_reverse( array_slice( $history, -max( 1, min( $limit, 50 ) ) ) );
	}

	public static function gate_history_policy(): array {
		self::prune_gate_history();
		$value = get_option( self::GATE_HISTORY_OPTION, array() );
		$history = is_array( $value ) ? $value : array();
		$oldest = 0;
		foreach ( $history as $entry ) {
			$at = (int) ( is_array( $entry ) ? ( $entry['at'] ?? 0 ) : 0 );
			if ( $at > 0 && ( 0 === $oldest || $at < $oldest ) ) $oldest = $at;
		}
		return array( 'retention_days' => self::GATE_HISTORY_RETENTION_DAYS, 'max_entries' => self::GATE_HISTORY_MAX, 'count' => count( $history ), 'oldest_at' => $oldest );
	}

	public static function prune_gate_history(): int {
		$value = get_option( self::GATE_HISTORY_OPTION, array() );
		$history = is_array( $value ) ? $value : array();
		$before = count( $history );
		$cutoff = time() - ( self::GATE_HISTORY_RETENTION_DAYS * DAY_IN_SECONDS );
		$history = array_values( array_filter( $history, static function ( $entry ) use ( $cutoff ): bool {
			return is_array( $entry ) && (int) ( $entry['at'] ?? 0 ) >= $cutoff;
		} ) );
		$history = array_slice( $history, -self::GATE_HISTORY_MAX );
		if ( $before !== count( $history ) ) update_option( self::GATE_HISTORY_OPTION, $history, false );
		return max( 0, $before - count( $history ) );
	}

	/** Append a privacy-safe record and enforce the retention policy on every write. */
	private static function append_gate_history( array $entry ): void {
		$value = get_option( self::GATE_HISTORY_OPTION, array() );
		$history = is_array( $value ) ? $value : array();
		$history[] = $entry;
		$cutoff = time() - ( self::GATE_HISTORY_RETENTION_DAYS * DAY_IN_SECONDS );
		$history = array_values( array_filter( $history, static function ( $item ) use ( $cutoff ): bool {
			return is_array( $item ) && (int) ( $item['at'] ?? 0 ) >= $cutoff;
		} ) );
		update_option( self::GATE_HISTORY_OPTION, array_slice( $history, -self::GATE_HISTORY_MAX ), false );
	}

	public static function export_gate_history( int $limit = 200 ): array {
		self::prune_gate_history();
		return array_values( array_map( static function ( $entry ): array {
			return array(
				'id' => (string) ( $entry['id'] ?? '' ), 'handle' => sanitize_key( (string) ( $entry['handle'] ?? '' ) ), 'strategy' => sanitize_key( (string) ( $entry['strategy'] ?? '' ) ), 'at' => (int) ( $entry['at'] ?? 0 ), 'qa' => in_array( (string) ( $entry['qa'] ?? 'pending' ), array( 'pending', 'pass', 'fail' ), true ) ? (string) $entry['qa'] : 'pending', 'rollback_verified' => ! empty( $entry['rollback_verified'] ), 'change' => sanitize_key( (string) ( $entry['change'] ?? '' ) ), 'changed' => is_array( $entry['changed'] ?? null ) ? array_intersect_key( $entry['changed'], array( 'rule' => true, 'dependency' => true, 'asset' => true ) ) : array(), 'previous_ref' => sanitize_text_field( (string) ( $entry['previous_ref'] ?? '' ) ), 'evidence_ref' => sanitize_text_field( (string) ( $entry['evidence_ref'] ?? '' ) ), 'environment' => sanitize_key( (string) ( $entry['environment'] ?? '' ) ), 'plugin_version' => sanitize_text_field( (string) ( $entry['plugin_version'] ?? '' ) ),
			);
		}, self::gate_history( '', '', $limit ) ) );
	}

	public static function acknowledge_stale_gate( string $handle, string $strategy ): array|WP_Error {
		$gate = self::compatibility_gate( $handle, $strategy );
		if ( empty( $gate['stale'] ) ) {
			return new WP_Error( 'baocache_gate_not_stale', __( 'Gate hiện không stale, không cần xác nhận.', 'baocache' ) );
		}
		$value = get_option( self::GATE_ACK_OPTION, array() );
		$acks = is_array( $value ) ? $value : array();
		$acks[ $gate['key'] ] = array( 'evidence_ref' => (string) $gate['evidence_ref'], 'acknowledged_at' => time(), 'environment' => wp_get_environment_type() );
		update_option( self::GATE_ACK_OPTION, $acks, false );
		self::append_gate_history( array(
			'id' => 'bch_' . substr( hash( 'sha256', $gate['key'] . '|ack|' . microtime( true ) ), 0, 20 ),
			'handle' => $gate['handle'], 'strategy' => $gate['strategy'], 'at' => time(), 'qa' => $gate['qa'], 'rollback_verified' => $gate['rollback_verified'],
			'change' => 'stale_acknowledged', 'changed' => array(), 'previous_ref' => (string) $gate['evidence_ref'], 'evidence_ref' => (string) $gate['evidence_ref'],
			'environment' => wp_get_environment_type(), 'plugin_version' => BAOCACHE_VERSION,
		) );
		return self::compatibility_gate( $handle, $strategy );
	}

	/**
	 * Build a non-reversible reference to the current rule inputs. Raw URLs and
	 * source paths are never persisted; only hashes are retained as evidence.
	 */
	public static function rule_evidence( string $handle, string $strategy ): array {
		$handle = sanitize_key( $handle );
		$strategy = sanitize_key( $strategy );
		$type = 'async-css' === $strategy ? 'style' : 'script';
		$source = '';
		$version = '';
		$dependencies = array();
		$size = 0;
		$evidence_source = 'registry';
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || sanitize_key( (string) ( $asset['handle'] ?? '' ) ) !== $handle || (string) ( $asset['type'] ?? '' ) !== $type ) {
				continue;
			}
			$source = (string) ( $asset['source'] ?? '' );
			$version = (string) ( $asset['version'] ?? '' );
			$dependencies = array_values( array_map( 'sanitize_key', (array) ( $asset['dependencies'] ?? array() ) ) );
			$size = absint( $asset['size_bytes'] ?? 0 );
			$evidence_source = 'inventory';
			break;
		}
		if ( 'inventory' !== $evidence_source ) {
			$registry = 'style' === $type ? wp_styles() : wp_scripts();
			$registered = isset( $registry->registered[ $handle ] ) ? $registry->registered[ $handle ] : null;
			if ( is_object( $registered ) ) {
				$source = (string) ( $registered->src ?? '' );
				$version = (string) ( $registered->ver ?? '' );
				$dependencies = array_values( array_map( 'sanitize_key', (array) ( $registered->deps ?? array() ) ) );
			}
		}
		$dependency_fingerprint = hash( 'sha256', wp_json_encode( $dependencies ) ?: '' );
		$asset_fingerprint = hash( 'sha256', wp_json_encode( array( 'type' => $type, 'source' => hash( 'sha256', $source ), 'version' => $version, 'size' => $size ) ) ?: '' );
		$rule_fingerprint = hash( 'sha256', wp_json_encode( array( 'handle' => $handle, 'strategy' => $strategy, 'dependency' => $dependency_fingerprint, 'asset' => $asset_fingerprint ) ) ?: '' );
		return array(
			'schema' => '1',
			'evidence_ref' => 'bc_' . substr( $rule_fingerprint, 0, 24 ),
			'rule_fingerprint' => $rule_fingerprint,
			'dependency_fingerprint' => $dependency_fingerprint,
			'asset_fingerprint' => $asset_fingerprint,
			'source' => $evidence_source,
			'captured_at' => time(),
		);
	}

	public static function compatibility_gate( string $handle, string $strategy ): array {
		$key = sanitize_key( $handle ) . '__' . sanitize_key( $strategy );
		$gate = self::compatibility_gates()[ $key ] ?? array();
		$qa = in_array( (string) ( $gate['qa'] ?? 'pending' ), array( 'pending', 'pass', 'fail' ), true ) ? (string) $gate['qa'] : 'pending';
		$rollback = ! empty( $gate['rollback_verified'] );
		$environment = wp_get_environment_type();
		$preview = in_array( $environment, array( 'staging', 'development' ), true );
		$current_evidence = self::rule_evidence( $handle, $strategy );
		$saved_evidence = is_array( $gate['evidence'] ?? null ) ? $gate['evidence'] : array();
		$evidence_expired = ! empty( $gate ) && (int) ( $gate['updated_at'] ?? 0 ) > 0 && ( time() - (int) $gate['updated_at'] ) > ( self::GATE_HISTORY_RETENTION_DAYS * DAY_IN_SECONDS );
		$stale = ! empty( $gate ) && ( $evidence_expired || '' === (string) ( $saved_evidence['rule_fingerprint'] ?? '' ) || ! hash_equals( (string) $saved_evidence['rule_fingerprint'], (string) $current_evidence['rule_fingerprint'] ) );
		$stale_reason = $stale ? ( $evidence_expired ? __( 'Evidence quá hạn theo chính sách retention.', 'baocache' ) : ( '' === (string) ( $saved_evidence['rule_fingerprint'] ?? '' ) ? __( 'Gate cũ chưa có bằng chứng bất biến.', 'baocache' ) : __( 'Dependency hoặc fingerprint asset đã thay đổi.', 'baocache' ) ) ) : '';
		$evidence_diff = array(
			'rule' => '' !== (string) ( $saved_evidence['rule_fingerprint'] ?? '' ) && ! hash_equals( (string) $saved_evidence['rule_fingerprint'], (string) $current_evidence['rule_fingerprint'] ),
			'dependency' => '' !== (string) ( $saved_evidence['dependency_fingerprint'] ?? '' ) && ! hash_equals( (string) $saved_evidence['dependency_fingerprint'], (string) $current_evidence['dependency_fingerprint'] ),
			'asset' => '' !== (string) ( $saved_evidence['asset_fingerprint'] ?? '' ) && ! hash_equals( (string) $saved_evidence['asset_fingerprint'], (string) $current_evidence['asset_fingerprint'] ),
		);
		$ack_value = get_option( self::GATE_ACK_OPTION, array() );
		$ack = is_array( $ack_value ) && is_array( $ack_value[ $key ] ?? null ) ? $ack_value[ $key ] : array();
		$acknowledged = $stale && '' !== (string) ( $saved_evidence['evidence_ref'] ?? '' ) && (string) ( $ack['evidence_ref'] ?? '' ) === (string) ( $saved_evidence['evidence_ref'] ?? '' );
		$allowed = $preview || ( ! $stale && 'pass' === $qa && $rollback );
		return array( 'key' => $key, 'handle' => sanitize_key( $handle ), 'strategy' => sanitize_key( $strategy ), 'qa' => $qa, 'rollback_verified' => $rollback, 'allowed' => $allowed, 'configured' => ! empty( $gate ), 'stale' => $stale, 'evidence_expired' => $evidence_expired, 'acknowledged' => $acknowledged, 'acknowledged_at' => $acknowledged ? (int) ( $ack['acknowledged_at'] ?? 0 ) : 0, 'stale_reason' => $stale_reason, 'evidence_diff' => $evidence_diff, 'evidence_ref' => (string) ( $saved_evidence['evidence_ref'] ?? '' ), 'current_evidence_ref' => (string) $current_evidence['evidence_ref'], 'environment' => $environment, 'updated_at' => (int) ( $gate['updated_at'] ?? 0 ) );
	}

	public static function save_compatibility_gate( string $handle, string $strategy, string $qa, bool $rollback_verified ): array {
		$gates = self::compatibility_gates();
		$key = sanitize_key( $handle ) . '__' . sanitize_key( $strategy );
		$evidence = self::rule_evidence( $handle, $strategy );
		$previous = is_array( $gates[ $key ] ?? null ) ? $gates[ $key ] : array();
		$previous_evidence = is_array( $previous['evidence'] ?? null ) ? $previous['evidence'] : array();
		$change_flags = array(
			'rule' => '' !== (string) ( $previous_evidence['rule_fingerprint'] ?? '' ) && (string) $previous_evidence['rule_fingerprint'] !== (string) $evidence['rule_fingerprint'],
			'dependency' => '' !== (string) ( $previous_evidence['dependency_fingerprint'] ?? '' ) && (string) $previous_evidence['dependency_fingerprint'] !== (string) $evidence['dependency_fingerprint'],
			'asset' => '' !== (string) ( $previous_evidence['asset_fingerprint'] ?? '' ) && (string) $previous_evidence['asset_fingerprint'] !== (string) $evidence['asset_fingerprint'],
		);
		$record = array( 'handle' => sanitize_key( $handle ), 'strategy' => sanitize_key( $strategy ), 'qa' => in_array( $qa, array( 'pending', 'pass', 'fail' ), true ) ? $qa : 'pending', 'rollback_verified' => $rollback_verified, 'evidence' => array( 'schema' => '1', 'evidence_ref' => $evidence['evidence_ref'], 'rule_fingerprint' => $evidence['rule_fingerprint'], 'dependency_fingerprint' => $evidence['dependency_fingerprint'], 'asset_fingerprint' => $evidence['asset_fingerprint'], 'source' => $evidence['source'] ), 'updated_at' => time(), 'environment' => wp_get_environment_type(), 'plugin_version' => BAOCACHE_VERSION );
		$gates[ $key ] = $record;
		update_option( self::GATE_OPTION, array_slice( $gates, -200, null, true ), false );
		$ack_value = get_option( self::GATE_ACK_OPTION, array() );
		$acks = is_array( $ack_value ) ? $ack_value : array();
		unset( $acks[ $key ] );
		update_option( self::GATE_ACK_OPTION, $acks, false );
		self::append_gate_history( array(
			'id' => 'bch_' . substr( hash( 'sha256', $key . '|' . microtime( true ) ), 0, 20 ),
			'handle' => sanitize_key( $handle ),
			'strategy' => sanitize_key( $strategy ),
			'at' => time(),
			'qa' => $record['qa'],
			'rollback_verified' => $rollback_verified,
			'change' => empty( $previous ) ? 'initial' : ( ! empty( array_filter( $change_flags ) ) ? 'evidence_changed' : 'reapproval' ),
			'changed' => $change_flags,
			'previous_ref' => (string) ( $previous_evidence['evidence_ref'] ?? '' ),
			'evidence_ref' => (string) $evidence['evidence_ref'],
			'environment' => wp_get_environment_type(),
			'plugin_version' => BAOCACHE_VERSION,
		) );
		return self::compatibility_gate( $handle, $strategy );
	}

	public static function gate_allows( string $handle, string $strategy ): bool {
		return ! empty( self::compatibility_gate( $handle, $strategy )['allowed'] );
	}

	public static function context_status( array $settings, string $path = '/', string $handle = '', array $context = array() ): array {
		$reasons = array();
		$path = '/' . ltrim( (string) wp_parse_url( '/' . ltrim( $path, '/' ), PHP_URL_PATH ), '/' );
		$excluded_handles = BaoCache_Settings::lines( (string) ( $settings['render_blocking_exclude_handles'] ?? '' ) );
		$excluded_urls = BaoCache_Settings::lines( (string) ( $settings['render_blocking_exclude_urls'] ?? '' ) );
		$excluded_contexts = BaoCache_Settings::lines( (string) ( $settings['render_blocking_exclude_contexts'] ?? '' ) );
		$handle_excluded = '' !== $handle && in_array( $handle, $excluded_handles, true );
		$url_excluded = false;
		$context_excluded = false;
		if ( $handle_excluded ) $reasons[] = __( 'Handle nằm trong exclusion.', 'baocache' );
		foreach ( $excluded_urls as $prefix ) {
			if ( '' !== $prefix && 0 === strpos( $path, $prefix ) ) {
				$url_excluded = true;
				$reasons[] = __( 'URL nằm trong exclusion prefix.', 'baocache' );
				break;
			}
		}
		$flags = array(
			'authenticated' => ! empty( $context['logged_in'] ),
			'admin' => ! empty( $context['admin'] ),
			'preview' => ! empty( $context['preview'] ),
			'checkout' => ! empty( $context['checkout'] ),
			'login' => str_contains( $path, 'wp-login.php' ),
			'feed' => ! empty( $context['feed'] ),
			'rest' => ! empty( $context['rest'] ),
			'ajax' => ! empty( $context['ajax'] ),
		);
		foreach ( $flags as $name => $active ) {
			if ( $active && in_array( $name, $excluded_contexts, true ) ) {
				$context_excluded = true;
				$reasons[] = sprintf( __( 'Context %s nằm trong exclusion.', 'baocache' ), $name );
			}
		}
		foreach ( array( 'admin', 'login', 'preview', 'checkout', 'authenticated', 'feed', 'rest', 'ajax' ) as $hard_stop ) {
			if ( ! empty( $flags[ $hard_stop ] ) ) $reasons[] = sprintf( __( 'Context %s không áp dụng tối ưu tự động.', 'baocache' ), $hard_stop );
		}
		return array( 'eligible' => empty( $reasons ), 'excluded' => $handle_excluded || $url_excluded || $context_excluded, 'path' => $path, 'handle' => $handle, 'reasons' => array_values( array_unique( $reasons ) ) );
	}

	public static function critical_css(): array {
		$value = get_option( self::CRITICAL_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function stage_critical_css( string $css, string $template = 'front-page', string $viewport = 'desktop' ): array|WP_Error {
		if ( ! self::valid_css( $css ) ) {
			return new WP_Error( 'baocache_critical_invalid', __( 'Critical CSS không hợp lệ hoặc vượt giới hạn an toàn.', 'baocache' ) );
		}
		$template = in_array( $template, array( 'front-page', 'page', 'post', 'archive', 'everywhere' ), true ) ? $template : 'front-page';
		$viewport = in_array( $viewport, array( 'mobile', 'tablet', 'desktop' ), true ) ? $viewport : 'desktop';
		$record = array( 'enabled' => true, 'template' => $template, 'viewport' => $viewport, 'fingerprint' => self::current_fingerprint(), 'validated_at' => time(), 'source' => 'admin-staged', 'css' => $css );
		update_option( self::CRITICAL_OPTION, $record, false );
		return $record;
	}

	public static function current_fingerprint(): string {
		$plugins = get_option( 'active_plugins', array() );
		$theme = wp_get_theme();
		$versions = array( 'wp' => get_bloginfo( 'version' ), 'theme' => $theme->get_stylesheet() . ':' . $theme->get( 'Version' ), 'mods' => md5( wp_json_encode( get_theme_mods() ) ?: '' ) );
		if ( ! function_exists( 'get_plugins' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_headers = function_exists( 'get_plugins' ) ? get_plugins() : array();
		foreach ( (array) $plugins as $plugin ) {
			$versions[] = (string) $plugin . ':' . (string) ( $plugin_headers[ $plugin ]['Version'] ?? '' );
		}
		return hash( 'sha256', wp_json_encode( $versions ) ?: '' );
	}

	public static function validated_critical_css(): string {
		$record = self::critical_css();
		if ( empty( $record['enabled'] ) || '' === (string) ( $record['css'] ?? '' ) || (int) ( $record['validated_at'] ?? 0 ) < 1 || ! hash_equals( self::current_fingerprint(), (string) ( $record['fingerprint'] ?? '' ) ) ) return '';
		$css = (string) $record['css'];
		return self::valid_css( $css ) ? $css : '';
	}

	private static function valid_css( string $css ): bool {
		return strlen( $css ) <= 150000 && false === stripos( $css, '</style' ) && false === stripos( $css, '<script' ) && false === stripos( $css, '@import' ) && substr_count( $css, '{' ) === substr_count( $css, '}' );
	}

	private static function map_asset( string $url, array $inventory ): array {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		foreach ( $inventory as $asset ) {
			if ( ! is_array( $asset ) || '' === (string) ( $asset['source'] ?? '' ) ) continue;
			$source = (string) $asset['source'];
			$source_path = (string) wp_parse_url( $source, PHP_URL_PATH );
			$source_host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
			if ( '' !== $path && $path === $source_path && ( '' === $host || '' === $source_host || $host === $source_host ) ) return $asset;
		}
		return array();
	}

	private static function resource_type( string $url ): string {
		$extension = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		return 'css' === $extension ? 'style' : ( 'js' === $extension ? 'script' : 'other' );
	}
}
