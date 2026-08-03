<?php
defined( 'ABSPATH' ) || exit;

/**
 * Optional, nonce-free CSP manager.
 *
 * A static policy is safe with FastCGI HTML caching. BaoCache never creates a
 * per-request nonce and never emits a second policy when an origin policy is
 * already present in this request. Cloudflare must not also own the same
 * policy; two CSP headers are intersected by browsers.
 */
final class BaoCache_CSP {
	private const string RECOMMENDATION_DISMISSED_OPTION = 'baocache_csp_recommendations_dismissed';
	private const string RECOMMENDATION_APPLIED_OPTION = 'baocache_csp_recommendations_applied';
	private const int REPORT_RETENTION_DAYS = 30;
	private const int LEDGER_RETENTION_DAYS = 90;
	private const string REVIEW_HOOK = 'baocache_csp_evidence_review_tick';
	private const string ENFORCE_ACK_OPTION = 'baocache_csp_enforce_acknowledgement';
	private const string POST_PROBE_OPTION = 'baocache_csp_post_enforcement_probe';
	private const string POST_PROBE_HISTORY_OPTION = 'baocache_csp_post_enforcement_probe_history';
	private const string POST_PROBE_ACK_OPTION = 'baocache_csp_post_probe_ack';
	private const string POST_PROBE_ACK_HISTORY_OPTION = 'baocache_csp_post_probe_ack_history';
	private const string POST_REMEDIATION_OPTION = 'baocache_csp_post_probe_remediation';
	public function register(): void {
		add_action( 'send_headers', array( $this, 'send_policy' ), 40 );
		add_action( 'rest_api_init', array( $this, 'register_report_route' ) );
		add_action( 'init', array( $this, 'ensure_review_schedule' ), 27 );
		add_action( self::REVIEW_HOOK, array( $this, 'review_evidence' ) );
	}

	public function ensure_review_schedule(): void {
		if ( ! wp_next_scheduled( self::REVIEW_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::REVIEW_HOOK );
		}
	}

	public function send_policy(): void {
		$settings = BaoCache_Settings::get();
		if ( empty( $settings['csp_enabled'] ) || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || headers_sent() ) {
			return;
		}
		foreach ( headers_list() as $existing ) {
			if ( 0 === strncasecmp( $existing, 'Content-Security-Policy:', 23 ) || 0 === strncasecmp( $existing, 'Content-Security-Policy-Report-Only:', 34 ) ) {
				return;
			}
		}
		$policy = self::build_policy( $settings );
		$header = 'enforce' === (string) $settings['csp_mode'] ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
		if ( ! empty( $settings['csp_collect_reports'] ) && 'report' === (string) $settings['csp_mode'] ) {
			$policy .= '; report-uri ' . esc_url_raw( rest_url( 'baocache/v1/csp-report' ) );
		}
		header( $header . ': ' . $policy, true );
	}

	public function register_report_route(): void {
		register_rest_route(
			'baocache/v1',
			'/csp-report',
			array(
				'methods' => 'POST',
				'permission_callback' => '__return_true',
				'callback' => array( $this, 'receive_report' ),
			)
		);
	}

	public function receive_report( WP_REST_Request $request ): WP_REST_Response {
		$settings = BaoCache_Settings::get();
		if ( empty( $settings['csp_enabled'] ) || empty( $settings['csp_collect_reports'] ) || 'report' !== (string) ( $settings['csp_mode'] ?? 'report' ) ) {
			return new WP_REST_Response( array( 'received' => false ), 202 );
		}
		$rate = (int) get_transient( 'baocache_csp_report_rate' );
		if ( $rate >= 120 ) {
			return new WP_REST_Response( array( 'received' => false ), 202 );
		}
		set_transient( 'baocache_csp_report_rate', $rate + 1, MINUTE_IN_SECONDS );
		if ( (int) $request->get_header( 'content-length' ) > 32768 ) {
			return new WP_REST_Response( array( 'received' => false ), 202 );
		}
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$decoded = json_decode( (string) $request->get_body(), true );
			$payload = is_array( $decoded ) ? $decoded : array();
		}
		$payload = is_array( $payload ) ? $payload : array();
		$report = is_array( $payload['csp-report'] ?? null ) ? $payload['csp-report'] : ( is_array( $payload['body'] ?? null ) ? $payload['body'] : $payload );
		$directive = sanitize_key( (string) ( $report['effective-directive'] ?? $report['violated-directive'] ?? $report['directive'] ?? 'unknown' ) );
		$directive = in_array( $directive, array( 'base-uri', 'child-src', 'connect-src', 'default-src', 'font-src', 'form-action', 'frame-ancestors', 'frame-src', 'img-src', 'media-src', 'object-src', 'script-src', 'script-src-attr', 'script-src-elem', 'style-src', 'style-src-attr', 'style-src-elem', 'worker-src' ), true ) ? $directive : 'unknown';
		$blocked = (string) ( $report['blocked-uri'] ?? $report['blockedURL'] ?? '' );
		$blocked_origin = self::blocked_origin( $blocked );
		$disposition = 'enforce' === strtolower( (string) ( $report['disposition'] ?? '' ) ) ? 'enforce' : 'report';
		self::record_report( $directive, $blocked_origin, $disposition );
		return new WP_REST_Response( array( 'received' => true ), 204 );
	}

	public static function reports(): array {
		$value = get_option( 'baocache_csp_reports', array() );
		$reports = is_array( $value ) ? array_values( $value ) : array();
		$active = self::prune_reports( $reports );
		if ( count( $active ) !== count( $reports ) ) {
			update_option( 'baocache_csp_reports', $active, false );
		}
		return $active;
	}

	public static function clear_reports(): void {
		delete_option( 'baocache_csp_reports' );
	}

	/**
	 * A bounded observation from Header Inspector. It stores no policy text;
	 * the source can be BaoCache only when the public header exactly matches the
	 * current local policy. Any other policy remains external/unknown.
	 */
	public static function owner_observation(): array {
		$value = get_option( 'baocache_csp_owner_observation', array() );
		return is_array( $value ) ? $value : array();
	}

	public static function record_owner_observation( bool $present, string $mode, bool $matches_baocache, array $metadata = array() ): void {
		update_option( 'baocache_csp_owner_observation', array(
			'checked_at' => time(),
			'present' => $present,
			'mode' => in_array( $mode, array( 'report-only', 'enforce' ), true ) ? $mode : 'none',
			'matches_baocache' => $matches_baocache,
			'enforce_present' => ! empty( $metadata['enforce_present'] ),
			'report_present' => ! empty( $metadata['report_present'] ),
			'enforce_count' => min( 9, max( 0, (int) ( $metadata['enforce_count'] ?? 0 ) ) ),
			'report_count' => min( 9, max( 0, (int) ( $metadata['report_count'] ?? 0 ) ) ),
		), false );
	}

	/**
	 * Read-only promotion readiness. This is intentionally conservative: it
	 * cannot change CSP mode and does not claim that zero reports proves a full
	 * compatibility test.
	 *
	 * @return array{state: string, title: string, detail: string, checks: array<string, bool|null>}
	 */
	public static function enforce_readiness( ?array $settings = null ): array {
		$settings = is_array( $settings ) ? $settings : BaoCache_Settings::get();
		$observation = self::owner_observation();
		$reports = self::reports();
		$enabled = ! empty( $settings['csp_enabled'] );
		$report_mode = 'report' === (string) ( $settings['csp_mode'] ?? 'report' );
		$collecting = ! empty( $settings['csp_collect_reports'] );
		$public_match = ! empty( $observation['present'] ) && ! empty( $observation['matches_baocache'] ) && 'report-only' === (string) ( $observation['mode'] ?? 'none' );
		$conflict = ( ! empty( $observation['enforce_present'] ) && ! empty( $observation['report_present'] ) )
			|| (int) ( $observation['enforce_count'] ?? 0 ) > 1
			|| (int) ( $observation['report_count'] ?? 0 ) > 1
			|| ( ! empty( $observation['present'] ) && empty( $observation['matches_baocache'] ) );
		$checks = array(
			'enabled' => $enabled,
			'report_mode' => $report_mode,
			'collecting' => $collecting,
			'public_match' => $public_match,
			'no_active_reports' => empty( $reports ),
			'no_conflict' => ! $conflict,
		);
		if ( ! $enabled ) {
			return array( 'state' => 'neutral', 'title' => __( 'CSP is not enabled', 'baocache' ), 'detail' => __( 'Enable Report-Only first; BaoCache will not enable or enforce a policy from this diagnostic.', 'baocache' ), 'checks' => $checks );
		}
		if ( 'enforce' === (string) ( $settings['csp_mode'] ?? '' ) ) {
			return array( 'state' => $conflict ? 'warn' : 'good', 'title' => $conflict ? __( 'Enforce needs policy-owner review', 'baocache' ) : __( 'CSP is explicitly enforced', 'baocache' ), 'detail' => $conflict ? __( 'Public headers indicate another or multiple CSP policies. Resolve the owner conflict manually; BaoCache will not rewrite the response.', 'baocache' ) : __( 'Mode was chosen by an operator. BaoCache does not auto-demote or auto-promote this policy.', 'baocache' ), 'checks' => $checks );
		}
		if ( $conflict ) {
			return array( 'state' => 'warn', 'title' => __( 'Public policy conflict detected', 'baocache' ), 'detail' => __( 'A public CSP is external/unknown or more than one policy type was observed. Keep Report-Only and choose one policy owner before considering Enforce.', 'baocache' ), 'checks' => $checks );
		}
		if ( ! $public_match ) {
			return array( 'state' => 'warn', 'title' => __( 'Public Report-Only header not verified', 'baocache' ), 'detail' => __( 'Run Header Inspector on a public URL. Local settings alone do not prove which CSP header visitors receive.', 'baocache' ), 'checks' => $checks );
		}
		if ( ! $collecting || ! empty( $reports ) ) {
			return array( 'state' => 'warn', 'title' => __( 'Continue observing Report-Only', 'baocache' ), 'detail' => ! $collecting ? __( 'Enable aggregate Report-Only evidence before any operator considers Enforce.', 'baocache' ) : __( 'Active CSP reports still need review. BaoCache will not suppress, apply or promote them automatically.', 'baocache' ), 'checks' => $checks );
		}
		return array( 'state' => 'good', 'title' => __( 'Ready for operator Enforce review', 'baocache' ), 'detail' => __( 'Public Report-Only matches BaoCache and there are no active retained reports. This is not a compatibility guarantee; an operator must still explicitly select Enforce and save.', 'baocache' ), 'checks' => $checks );
	}

	/** @return array{matched: bool, acknowledged_at: int, fingerprint: string} */
	public static function enforce_acknowledgement( ?array $settings = null ): array {
		$fingerprint = (string) self::policy_snapshot( $settings )['fingerprint'];
		$record = get_option( self::ENFORCE_ACK_OPTION, array() );
		$record = is_array( $record ) ? $record : array();
		return array(
			'matched' => ! empty( $record['acknowledged_at'] ) && hash_equals( $fingerprint, (string) ( $record['fingerprint'] ?? '' ) ),
			'acknowledged_at' => (int) ( $record['acknowledged_at'] ?? 0 ),
			'fingerprint' => $fingerprint,
		);
	}

	/** Stores acknowledgement metadata only after the admin validated readiness. */
	public static function record_enforce_acknowledgement( ?array $settings = null ): void {
		$snapshot = self::policy_snapshot( $settings );
		update_option( self::ENFORCE_ACK_OPTION, array( 'fingerprint' => (string) $snapshot['fingerprint'], 'acknowledged_at' => time(), 'plugin_version' => BAOCACHE_VERSION ), false );
	}

	/** @return array<string, mixed> */
	public static function post_enforcement_probe(): array {
		$value = get_option( self::POST_PROBE_OPTION, array() );
		return is_array( $value ) ? array_intersect_key( $value, array( 'checked_at' => true, 'status_code' => true, 'mode' => true, 'present' => true, 'matches' => true, 'conflict' => true, 'outcome' => true, 'response_ms' => true, 'source' => true ) ) : array();
	}

	/** Stores probe metadata only; no policy text or response body. */
	public static function record_post_enforcement_probe( array $result, string $source = 'manual' ): void {
		$record = array(
			'checked_at' => time(),
			'status_code' => (int) ( $result['status_code'] ?? 0 ),
			'mode' => in_array( (string) ( $result['mode'] ?? 'none' ), array( 'enforce', 'report-only', 'none' ), true ) ? (string) $result['mode'] : 'none',
			'present' => ! empty( $result['present'] ),
			'matches' => ! empty( $result['matches'] ),
			'conflict' => ! empty( $result['conflict'] ),
			'outcome' => in_array( (string) ( $result['outcome'] ?? 'warn' ), array( 'pass', 'warn', 'fail' ), true ) ? (string) $result['outcome'] : 'warn',
			'response_ms' => max( 0, min( 60000, (int) ( $result['response_ms'] ?? 0 ) ) ),
			'source' => in_array( $source, array( 'manual', 'scheduled' ), true ) ? $source : 'manual',
		);
		update_option( self::POST_PROBE_OPTION, $record, false );
		$history = get_option( self::POST_PROBE_HISTORY_OPTION, array() );
		$history = is_array( $history ) ? array_values( $history ) : array();
		array_unshift( $history, $record );
		$cutoff = time() - 30 * DAY_IN_SECONDS;
		$history = array_values( array_filter( array_slice( $history, 0, 50 ), static fn( mixed $item ): bool => is_array( $item ) && (int) ( $item['checked_at'] ?? 0 ) >= $cutoff ) );
		update_option( self::POST_PROBE_HISTORY_OPTION, $history, false );
	}

	/** @return list<array<string, mixed>> */
	public static function post_enforcement_probe_history(): array {
		$value = get_option( self::POST_PROBE_HISTORY_OPTION, array() );
		if ( ! is_array( $value ) ) return array();
		$cutoff = time() - 30 * DAY_IN_SECONDS;
		$active = array_values( array_filter( array_slice( $value, 0, 50 ), static fn( mixed $item ): bool => is_array( $item ) && (int) ( $item['checked_at'] ?? 0 ) >= $cutoff ) );
		if ( count( $active ) !== count( $value ) ) {
			update_option( self::POST_PROBE_HISTORY_OPTION, $active, false );
		}
		$allowed = array( 'checked_at' => true, 'status_code' => true, 'mode' => true, 'present' => true, 'matches' => true, 'conflict' => true, 'outcome' => true, 'response_ms' => true, 'source' => true );
		return array_values( array_map( static fn( mixed $item ): array => is_array( $item ) ? array_intersect_key( $item, $allowed ) : array(), $active ) );
	}

	/**
	 * Compare the two newest public probes using metadata only. A regression is
	 * a failed scheduled canary after a pass (or without a previous baseline).
	 * Repeated failures remain visible until an operator acknowledges them.
	 *
	 * @return array{available: bool, regression: bool, repeated_failure: bool, latest: array, previous: array, changed: list<string>, fingerprint: string}
	 */
	public static function post_enforcement_probe_regression(): array {
		$history = self::post_enforcement_probe_history();
		$latest = is_array( $history[0] ?? null ) ? $history[0] : array();
		$previous = is_array( $history[1] ?? null ) ? $history[1] : array();
		$failed = 'fail' === (string) ( $latest['outcome'] ?? '' );
		$previous_failed = 'fail' === (string) ( $previous['outcome'] ?? '' );
		$changed = array();
		foreach ( array( 'status_code', 'mode', 'present', 'matches', 'conflict', 'outcome' ) as $field ) {
			if ( isset( $latest[ $field ], $previous[ $field ] ) && (string) $latest[ $field ] !== (string) $previous[ $field ] ) {
				$changed[] = $field;
			}
		}
		$fingerprint = self::probe_fingerprint( $latest );
		return array(
			'available' => ! empty( $latest ),
			'regression' => $failed && 'scheduled' === (string) ( $latest['source'] ?? '' ),
			'repeated_failure' => $failed && $previous_failed,
			'latest' => $latest,
			'previous' => $previous,
			'changed' => $changed,
			'fingerprint' => $fingerprint,
		);
	}

	/**
	 * Return a small, scheduled-canary-only trend window. This is descriptive
	 * telemetry, not a health score and never infers visitor impact.
	 *
	 * @return array{available: bool, window: int, pass: int, warn: int, fail: int, failure_streak: int, avg_response_ms: int, latest_at: int, samples: list<array<string, mixed>>}
	 */
	public static function post_enforcement_probe_trend( int $limit = 7 ): array {
		$limit = max( 3, min( 14, $limit ) );
		$samples = array_values( array_filter( self::post_enforcement_probe_history(), static fn( array $item ): bool => 'scheduled' === (string) ( $item['source'] ?? '' ) ) );
		$samples = array_slice( $samples, 0, $limit );
		$counts = array( 'pass' => 0, 'warn' => 0, 'fail' => 0 );
		$total_ms = 0;
		$streak = 0;
		foreach ( $samples as $index => $sample ) {
			$outcome = (string) ( $sample['outcome'] ?? 'warn' );
			if ( isset( $counts[ $outcome ] ) ) {
				$counts[ $outcome ]++;
			}
			$total_ms += (int) ( $sample['response_ms'] ?? 0 );
			if ( 0 === $index && 'fail' === $outcome ) {
				$streak = 1;
			} elseif ( $index > 0 && $streak === $index && 'fail' === $outcome ) {
				$streak++;
			}
		}
		return array(
			'available' => ! empty( $samples ),
			'window' => count( $samples ),
			'pass' => $counts['pass'],
			'warn' => $counts['warn'],
			'fail' => $counts['fail'],
			'failure_streak' => $streak,
			'avg_response_ms' => empty( $samples ) ? 0 : (int) round( $total_ms / count( $samples ) ),
			'latest_at' => (int) ( $samples[0]['checked_at'] ?? 0 ),
			'samples' => $samples,
		);
	}

	/** @return list<array{id: string, priority: string, title: string, detail: string}> */
	public static function post_enforcement_remediation(): array {
		$trend = self::post_enforcement_probe_trend();
		$latest = $trend['samples'][0] ?? array();
		if ( empty( $latest ) ) {
			return array( array( 'id' => 'collect', 'priority' => 'info', 'title' => __( 'Chưa có scheduled canary', 'baocache' ), 'detail' => __( 'Bật canary và chờ WordPress Cron chạy để tạo trend thực. Không suy đoán trạng thái từ dữ liệu trống.', 'baocache' ) ) );
		}
		$steps = array();
		if ( 'fail' === (string) ( $latest['outcome'] ?? '' ) ) {
			if ( ! empty( $latest['conflict'] ) ) {
				$steps[] = array( 'id' => 'owner', 'priority' => 'critical', 'title' => __( 'Kiểm tra CSP owner và header trùng', 'baocache' ), 'detail' => __( 'Chạy Header Inspector, xác định Cloudflare/Nginx/plugin nào đang phát policy thứ hai và chỉ giữ một owner.', 'baocache' ) );
			}
			if ( (int) ( $latest['status_code'] ?? 0 ) < 200 || (int) ( $latest['status_code'] ?? 0 ) >= 400 ) {
				$steps[] = array( 'id' => 'response', 'priority' => 'high', 'title' => __( 'Kiểm tra public response', 'baocache' ), 'detail' => __( 'Xác minh HTTP 2xx/3xx, maintenance page, proxy và Nginx trước khi kết luận CSP là nguyên nhân.', 'baocache' ) );
			}
			if ( empty( $latest['matches'] ) ) {
				$steps[] = array( 'id' => 'policy', 'priority' => 'high', 'title' => __( 'So sánh policy public với BaoCache', 'baocache' ), 'detail' => __( 'Dùng Header Inspector để đối chiếu policy hiện tại; không thêm source đoán và không sửa trực tiếp response.', 'baocache' ) );
			}
			$steps[] = array( 'id' => 'staging', 'priority' => 'medium', 'title' => __( 'Chạy lại staging Compatibility QA', 'baocache' ), 'detail' => __( 'Kiểm tra menu, form, map, analytics/chat, login và checkout trước khi giữ Enforce.', 'baocache' ) );
			$steps[] = array( 'id' => 'rollback', 'priority' => 'operator', 'title' => __( 'Chuẩn bị rollback thủ công', 'baocache' ), 'detail' => __( 'Nếu frontend bị ảnh hưởng, operator chọn Report-Only và lưu; BaoCache không thực hiện bước này tự động.', 'baocache' ) );
		} elseif ( $trend['window'] < 3 ) {
			$steps[] = array( 'id' => 'observe', 'priority' => 'info', 'title' => __( 'Tiếp tục quan sát', 'baocache' ), 'detail' => __( 'Cần thêm scheduled samples trước khi đánh giá trend ổn định.', 'baocache' ) );
		}
		return $steps;
	}

	/** @return array{matched: bool, acknowledged_at: int, fingerprint: string} */
	public static function post_probe_acknowledgement(): array {
		$regression = self::post_enforcement_probe_regression();
		$fingerprint = (string) $regression['fingerprint'];
		$record = get_option( self::POST_PROBE_ACK_OPTION, array() );
		$record = is_array( $record ) ? $record : array();
		return array(
			'matched' => '' !== $fingerprint && ! empty( $record['acknowledged_at'] ) && hash_equals( $fingerprint, (string) ( $record['fingerprint'] ?? '' ) ),
			'acknowledged_at' => (int) ( $record['acknowledged_at'] ?? 0 ),
			'fingerprint' => $fingerprint,
		);
	}

	/** Store acknowledgement metadata only; never suppress or change a probe. */
	public static function record_post_probe_acknowledgement(): bool {
		$regression = self::post_enforcement_probe_regression();
		$latest = $regression['latest'];
		if ( empty( $latest ) || 'scheduled' !== (string) ( $latest['source'] ?? '' ) || 'fail' !== (string) ( $latest['outcome'] ?? '' ) ) {
			return false;
		}
		$acknowledged_at = time();
		$record = array( 'fingerprint' => (string) $regression['fingerprint'], 'acknowledged_at' => $acknowledged_at, 'probe_checked_at' => (int) ( $latest['checked_at'] ?? 0 ), 'outcome' => 'fail', 'source' => 'scheduled', 'plugin_version' => BAOCACHE_VERSION );
		update_option( self::POST_PROBE_ACK_OPTION, $record, false );
		$history = get_option( self::POST_PROBE_ACK_HISTORY_OPTION, array() );
		$history = is_array( $history ) ? array_values( $history ) : array();
		array_unshift( $history, $record );
		$cutoff = time() - 30 * DAY_IN_SECONDS;
		$history = array_values( array_filter( array_slice( $history, 0, 50 ), static fn( mixed $item ): bool => is_array( $item ) && (int) ( $item['acknowledged_at'] ?? 0 ) >= $cutoff ) );
		update_option( self::POST_PROBE_ACK_HISTORY_OPTION, $history, false );
		return true;
	}

	/** @return list<array<string, mixed>> */
	public static function post_probe_acknowledgement_history(): array {
		$value = get_option( self::POST_PROBE_ACK_HISTORY_OPTION, array() );
		if ( ! is_array( $value ) ) return array();
		$cutoff = time() - 30 * DAY_IN_SECONDS;
		$active = array_values( array_filter( array_slice( $value, 0, 50 ), static fn( mixed $item ): bool => is_array( $item ) && (int) ( $item['acknowledged_at'] ?? 0 ) >= $cutoff ) );
		if ( count( $active ) !== count( $value ) ) update_option( self::POST_PROBE_ACK_HISTORY_OPTION, $active, false );
		$allowed = array( 'fingerprint' => true, 'acknowledged_at' => true, 'probe_checked_at' => true, 'outcome' => true, 'source' => true );
		return array_values( array_map( static fn( mixed $item ): array => is_array( $item ) ? array_intersect_key( $item, $allowed ) : array(), $active ) );
	}

	/** @return array{fingerprint: string, steps: array<string, array<string, mixed>>} */
	public static function remediation_state(): array {
		$trend = self::post_enforcement_probe_trend();
		$latest = $trend['samples'][0] ?? array();
		$fingerprint = self::probe_fingerprint( is_array( $latest ) ? $latest : array() );
		$all = get_option( self::POST_REMEDIATION_OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$state = is_array( $all[ $fingerprint ] ?? null ) ? $all[ $fingerprint ] : array();
		$steps = is_array( $state['steps'] ?? null ) ? $state['steps'] : array();
		return array( 'fingerprint' => $fingerprint, 'steps' => $steps );
	}

	/** Store one operator-owned remediation step for the current trend fingerprint. */
	public static function save_remediation_step( string $step_id, bool $completed, string $note ): bool {
		$step_id = sanitize_key( $step_id );
		$valid = false;
		foreach ( self::post_enforcement_remediation() as $step ) {
			if ( $step_id === (string) ( $step['id'] ?? '' ) ) { $valid = true; break; }
		}
		if ( ! $valid ) return false;
		$state = self::remediation_state();
		if ( '' === (string) $state['fingerprint'] ) return false;
		$all = get_option( self::POST_REMEDIATION_OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$record = is_array( $all[ $state['fingerprint'] ] ?? null ) ? $all[ $state['fingerprint'] ] : array( 'updated_at' => 0, 'steps' => array() );
		$record['updated_at'] = time();
		$record['steps'] = is_array( $record['steps'] ?? null ) ? $record['steps'] : array();
		$record['steps'][ $step_id ] = array( 'completed' => $completed, 'note' => substr( sanitize_textarea_field( $note ), 0, 300 ), 'updated_at' => time() );
		$all[ $state['fingerprint'] ] = $record;
		$cutoff = time() - 30 * DAY_IN_SECONDS;
		foreach ( $all as $fingerprint => $item ) if ( ! is_array( $item ) || (int) ( $item['updated_at'] ?? 0 ) < $cutoff ) unset( $all[ $fingerprint ] );
		update_option( self::POST_REMEDIATION_OPTION, $all, false );
		return true;
	}

	/** @return string */
	private static function probe_fingerprint( array $record ): string {
		$fields = array();
		foreach ( array( 'checked_at', 'status_code', 'mode', 'present', 'matches', 'conflict', 'outcome', 'response_ms', 'source' ) as $field ) {
			$fields[] = $field . '=' . (string) ( $record[ $field ] ?? '' );
		}
		return empty( $record ) ? '' : hash( 'sha256', implode( '|', $fields ) );
	}

	/**
	 * Return only repeatable Report-Only evidence that can be mapped to one
	 * explicit, HTTPS source field. Inline/eval/data/blob and same-site evidence
	 * are intentionally never turned into a recommendation.
	 */
	public static function recommendations( ?array $settings = null ): array {
		$settings = is_array( $settings ) ? $settings : BaoCache_Settings::get();
		if ( empty( $settings['csp_enabled'] ) || 'report' !== (string) ( $settings['csp_mode'] ?? '' ) || empty( $settings['csp_collect_reports'] ) ) {
			return array();
		}
		$dismissed = self::active_dismissals();
		$fingerprint = (string) self::policy_snapshot( $settings )['fingerprint'];
		$recommendations = array();
		foreach ( self::reports() as $report ) {
			$field = self::recommendation_field( (string) ( $report['directive'] ?? '' ) );
			$origin = self::recommendation_origin( (string) ( $report['blocked_origin'] ?? '' ) );
			$count = (int) ( $report['count'] ?? 0 );
			$first_at = (int) ( $report['first_at'] ?? 0 );
			$last_at = (int) ( $report['last_at'] ?? 0 );
			if ( '' === $field || '' === $origin || $count < 2 || ( $count < 3 && $last_at - $first_at < MINUTE_IN_SECONDS ) ) {
				continue;
			}
			$identity = hash( 'sha256', $field . '|' . $origin );
			if ( isset( $dismissed[ $identity ] ) && hash_equals( $fingerprint, (string) ( $dismissed[ $identity ]['fingerprint'] ?? '' ) ) ) {
				continue;
			}
			$current = self::merge_sources( $settings[ 'csp_' . $field . '_sources' ] ?? '', self::suggested_sources( $settings )[ $field ] ?? array() );
			if ( self::source_is_allowed( $current, $origin ) ) {
				continue;
			}
			$recommendations[] = array(
				'id' => $identity,
				'field' => $field,
				'directive' => (string) ( $report['directive'] ?? '' ),
				'origin' => $origin,
				'count' => $count,
				'first_at' => $first_at,
				'last_at' => $last_at,
			);
		}
		usort( $recommendations, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );
		return array_slice( $recommendations, 0, 12 );
	}

	/** @return array{success: bool, message: string} */
	public static function apply_recommendation( string $identity ): array {
		$settings = BaoCache_Settings::get();
		foreach ( self::recommendations( $settings ) as $recommendation ) {
			if ( ! hash_equals( (string) $recommendation['id'], $identity ) ) {
				continue;
			}
			$key = 'csp_' . $recommendation['field'] . '_sources';
			$before_sources = (string) ( $settings[ $key ] ?? '' );
			$settings[ $key ] = trim( (string) ( $settings[ $key ] ?? '' ) . "\n" . $recommendation['origin'] );
			$settings = BaoCache_Settings::sanitize( $settings );
			update_option( BAOCACHE_OPTION, $settings, false );
			self::record_policy_snapshot( $settings );
			$record = array(
				'id' => wp_generate_uuid4(),
				'recommendation_id' => $identity,
				'field' => $recommendation['field'],
				'origin' => $recommendation['origin'],
				'before_sources' => $before_sources,
				'after_sources' => (string) ( $settings[ $key ] ?? '' ),
				'fingerprint' => (string) self::policy_snapshot( $settings )['fingerprint'],
				'applied_at' => time(),
			);
			self::record_applied_recommendation( $record );
			return array( 'success' => true, 'record_id' => $record['id'], 'message' => sprintf( __( 'Đã thêm %1$s vào %2$s. CSP vẫn ở Report-Only.', 'baocache' ), $recommendation['origin'], $recommendation['field'] ) );
		}
		return array( 'success' => false, 'message' => __( 'Recommendation không còn hợp lệ. Hãy tải lại evidence CSP.', 'baocache' ) );
	}

	/**
	 * Return applied recommendations with a conservative rollback gate. A manual
	 * edit or any policy change after applying is treated as stale; BaoCache
	 * never overwrites an operator's later CSP work.
	 */
	public static function applied_recommendations(): array {
		$settings = BaoCache_Settings::get();
		$current_fingerprint = (string) self::policy_snapshot( $settings )['fingerprint'];
		$records = get_option( self::RECOMMENDATION_APPLIED_OPTION, array() );
		$records = is_array( $records ) ? $records : array();
		$active = array();
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! empty( $record['rolled_back_at'] ) || empty( $record['id'] ) || empty( $record['field'] ) ) {
				continue;
			}
			$key = 'csp_' . sanitize_key( (string) $record['field'] ) . '_sources';
			$record['evidence_expired'] = (int) ( $record['applied_at'] ?? 0 ) < ( time() - self::REPORT_RETENTION_DAYS * DAY_IN_SECONDS );
			$record['stale'] = ! hash_equals( (string) ( $record['fingerprint'] ?? '' ), $current_fingerprint )
				|| (string) ( $settings[ $key ] ?? '' ) !== (string) ( $record['after_sources'] ?? '' )
				|| ! empty( $record['evidence_expired'] );
			$active[] = $record;
		}
		return array_slice( $active, 0, 8 );
	}

	/** @return array{success: bool, message: string} */
	public static function rollback_recommendation( string $record_id ): array {
		$records = get_option( self::RECOMMENDATION_APPLIED_OPTION, array() );
		$records = is_array( $records ) ? $records : array();
		$settings = BaoCache_Settings::get();
		$current_fingerprint = (string) self::policy_snapshot( $settings )['fingerprint'];
		foreach ( $records as $index => $record ) {
			if ( ! is_array( $record ) || ! hash_equals( (string) ( $record['id'] ?? '' ), $record_id ) || ! empty( $record['rolled_back_at'] ) ) {
				continue;
			}
			$field = sanitize_key( (string) ( $record['field'] ?? '' ) );
			$key = 'csp_' . $field . '_sources';
			if ( '' === $field || (int) ( $record['applied_at'] ?? 0 ) < ( time() - self::REPORT_RETENTION_DAYS * DAY_IN_SECONDS ) || ! hash_equals( (string) ( $record['fingerprint'] ?? '' ), $current_fingerprint ) || (string) ( $settings[ $key ] ?? '' ) !== (string) ( $record['after_sources'] ?? '' ) ) {
				return array( 'success' => false, 'message' => __( 'Rollback bị chặn vì CSP đã thay đổi sau khi áp dụng. Rà soát source thủ công để không ghi đè cấu hình mới.', 'baocache' ) );
			}
			$settings[ $key ] = (string) ( $record['before_sources'] ?? '' );
			$settings = BaoCache_Settings::sanitize( $settings );
			update_option( BAOCACHE_OPTION, $settings, false );
			self::record_policy_snapshot( $settings );
			$records[ $index ]['rolled_back_at'] = time();
			$records[ $index ]['rollback_fingerprint'] = (string) self::policy_snapshot( $settings )['fingerprint'];
			update_option( self::RECOMMENDATION_APPLIED_OPTION, array_slice( $records, 0, 30 ), false );
			$origin = (string) ( $record['origin'] ?? '' );
			return array( 'success' => true, 'message' => sprintf( __( 'Đã rollback source %s về trạng thái trước khi áp dụng.', 'baocache' ), $origin ) );
		}
		return array( 'success' => false, 'message' => __( 'Không tìm thấy CSP recommendation đã áp dụng.', 'baocache' ) );
	}

	private static function record_applied_recommendation( array $record ): void {
		$records = get_option( self::RECOMMENDATION_APPLIED_OPTION, array() );
		$records = is_array( $records ) ? $records : array();
		array_unshift( $records, $record );
		$records = self::prune_applied_records( $records );
		update_option( self::RECOMMENDATION_APPLIED_OPTION, array_slice( $records, 0, 30 ), false );
	}

	public static function dismiss_recommendation( string $identity ): bool {
		foreach ( self::recommendations() as $recommendation ) {
			if ( ! hash_equals( (string) $recommendation['id'], $identity ) ) {
				continue;
			}
			$dismissed = self::active_dismissals();
			$dismissed[ $identity ] = array( 'fingerprint' => (string) self::policy_snapshot()['fingerprint'], 'dismissed_at' => time() );
			update_option( self::RECOMMENDATION_DISMISSED_OPTION, array_slice( $dismissed, -100, null, true ), false );
			return true;
		}
		return false;
	}

	public static function policy_snapshot( ?array $settings = null ): array {
		$policy = self::build_policy( $settings );
		$directives = array();
		foreach ( array_filter( array_map( 'trim', explode( ';', $policy ) ) ) as $directive ) {
			$parts = preg_split( '/\s+/', $directive, 2 );
			$directives[ (string) ( $parts[0] ?? 'unknown' ) ] = (string) ( $parts[1] ?? '' );
		}
		return array(
			'at' => time(),
			'fingerprint' => hash( 'sha256', $policy ),
			'directives' => $directives,
		);
	}

	public static function policy_history(): array {
		$value = get_option( 'baocache_csp_policy_history', array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	public static function record_policy_snapshot( ?array $settings = null ): void {
		$snapshot = self::policy_snapshot( $settings );
		$history = self::policy_history();
		if ( ! empty( $history[0]['fingerprint'] ) && hash_equals( (string) $history[0]['fingerprint'], (string) $snapshot['fingerprint'] ) ) {
			return;
		}
		array_unshift( $history, $snapshot );
		update_option( 'baocache_csp_policy_history', array_slice( $history, 0, 10 ), false );
	}

	public static function build_policy( ?array $settings = null ): string {
		$settings = is_array( $settings ) ? $settings : BaoCache_Settings::get();
		$suggested = self::suggested_sources( $settings );
		$script = self::merge_sources( $settings['csp_script_sources'] ?? '', $suggested['script'] );
		$style = self::merge_sources( $settings['csp_style_sources'] ?? '', $suggested['style'] );
		$img = self::merge_sources( $settings['csp_img_sources'] ?? '', $suggested['img'] );
		$font = self::merge_sources( $settings['csp_font_sources'] ?? '', $suggested['font'] );
		$connect = self::merge_sources( $settings['csp_connect_sources'] ?? '', $suggested['connect'] );
		$frame = self::merge_sources( $settings['csp_frame_sources'] ?? '', $suggested['frame'] );
		$worker = self::source_list( $settings['csp_worker_sources'] ?? '' );
		$directives = array(
			"default-src 'self'",
			"base-uri 'self'",
			"object-src 'none'",
			"frame-ancestors 'self'",
			"form-action 'self'",
			'script-src ' . implode( ' ', $script ),
			'style-src ' . implode( ' ', $style ),
			'img-src ' . implode( ' ', $img ),
			'font-src ' . implode( ' ', $font ),
			'connect-src ' . implode( ' ', $connect ),
			'frame-src ' . implode( ' ', $frame ),
			'worker-src ' . implode( ' ', $worker ),
		);
		return implode( '; ', array_filter( $directives ) );
	}

	/** Return defaults with vendor origins only when the related feature is on. */
	public static function defaults(): array {
		return array(
			'csp_enabled' => false,
			'csp_mode' => 'report',
			'csp_script_sources' => "'self'",
			'csp_style_sources' => "'self'",
			'csp_img_sources' => "'self' data:",
			'csp_font_sources' => "'self' data:",
			'csp_connect_sources' => "'self'",
			'csp_frame_sources' => "'self'",
			'csp_worker_sources' => "'self' blob:",
			'csp_collect_reports' => false,
		);
	}

	public static function suggested_sources( ?array $settings = null ): array {
		$settings = is_array( $settings ) ? $settings : BaoCache_Settings::get();
		$status = BaoCache_Analytics::status( $settings );
		$script = array( "'self'" );
		$style = array( "'self'" );
		$img = array( "'self'", 'data:' );
		$font = array( "'self'", 'data:' );
		$connect = array( "'self'" );
		$frame = array( "'self'" );
		if ( $status['enabled'] && 'gtm' === $status['type'] ) {
			$script[] = 'https://www.googletagmanager.com';
			$connect[] = 'https://www.googletagmanager.com';
			$frame[] = 'https://www.googletagmanager.com';
		}
		if ( $status['enabled'] && in_array( $status['type'], array( 'ga4', 'gtm' ), true ) ) {
			$connect[] = 'https://www.google-analytics.com';
			$connect[] = 'https://analytics.google.com';
			$img[] = 'https://www.google-analytics.com';
		}
		if ( $status['clarity_enabled'] ) {
			$script[] = 'https://www.clarity.ms';
			$connect[] = 'https://www.clarity.ms';
			$connect[] = 'https://*.clarity.ms';
			$img[] = 'https://www.clarity.ms';
		}
		if ( in_array( 'onesignal', (array) ( $status['enabled_adapters'] ?? array() ), true ) || in_array( 'power-schedule-manager', (array) ( $status['enabled_adapters'] ?? array() ), true ) ) {
			$script[] = 'https://cdn.onesignal.com';
			$connect[] = 'https://*.onesignal.com';
		}
		return array(
			'script' => array_values( array_unique( $script ) ),
			'style' => array_values( array_unique( $style ) ),
			'img' => array_values( array_unique( $img ) ),
			'font' => array_values( array_unique( $font ) ),
			'connect' => array_values( array_unique( $connect ) ),
			'frame' => array_values( array_unique( $frame ) ),
		);
	}

	private static function source_list( mixed $value ): array {
		$tokens = preg_split( '/[\s,]+/', trim( (string) $value ) ) ?: array();
		$allowed = array();
		foreach ( $tokens as $token ) {
			$token = trim( $token );
			if ( '' === $token ) {
				continue;
			}
			if ( in_array( $token, array( "'self'", "'none'", "'unsafe-inline'", "'unsafe-eval'", 'data:', 'blob:', 'https:', 'http:' ), true ) ) {
				$allowed[] = $token;
				continue;
			}
			$scheme = wp_parse_url( $token, PHP_URL_SCHEME );
			$host = wp_parse_url( $token, PHP_URL_HOST );
			if ( in_array( $scheme, array( 'https', 'http' ), true ) && is_string( $host ) && preg_match( '/^(?:\*\.)?[a-z0-9.-]+$/i', $host ) ) {
				$allowed[] = esc_url_raw( $token );
			}
		}
		return array_values( array_unique( $allowed ) );
	}

	private static function record_report( string $directive, string $blocked_origin, string $disposition ): void {
		$now = time();
		$reports = self::reports();
		$key = $directive . '|' . $blocked_origin . '|' . $disposition;
		$found = false;
		foreach ( $reports as &$item ) {
			if ( $key === (string) ( $item['key'] ?? '' ) ) {
				$item['count'] = min( 9999, (int) ( $item['count'] ?? 0 ) + 1 );
				$item['last_at'] = $now;
				$found = true;
				break;
			}
		}
		unset( $item );
		if ( ! $found ) {
			$reports[] = array( 'key' => $key, 'directive' => $directive, 'blocked_origin' => $blocked_origin, 'disposition' => $disposition, 'count' => 1, 'first_at' => $now, 'last_at' => $now );
		}
		$reports = self::prune_reports( $reports, $now );
		usort( $reports, static fn( array $a, array $b ): int => (int) ( $b['last_at'] ?? 0 ) <=> (int) ( $a['last_at'] ?? 0 ) );
		update_option( 'baocache_csp_reports', array_slice( $reports, 0, 100 ), false );
	}

	/**
	 * Daily bounded evidence review. It deletes expired reports/dismissals and
	 * old immutable ledger records; it never modifies the active CSP policy.
	 *
	 * @return array{reports_removed: int, dismissals_removed: int, ledger_removed: int, active_reports: int, review_at: int}
	 */
	public function review_evidence(): array {
		$now = time();
		$reports = get_option( 'baocache_csp_reports', array() );
		$reports = is_array( $reports ) ? array_values( $reports ) : array();
		$active_reports = self::prune_reports( $reports, $now );
		$reports_removed = count( $reports ) - count( $active_reports );
		if ( $reports_removed > 0 ) {
			update_option( 'baocache_csp_reports', $active_reports, false );
		}

		$dismissed = get_option( self::RECOMMENDATION_DISMISSED_OPTION, array() );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();
		$active_dismissals = self::prune_dismissals( $dismissed, $now );
		$dismissals_removed = count( $dismissed ) - count( $active_dismissals );
		if ( $dismissals_removed > 0 ) {
			update_option( self::RECOMMENDATION_DISMISSED_OPTION, $active_dismissals, false );
		}

		$ledger = get_option( self::RECOMMENDATION_APPLIED_OPTION, array() );
		$ledger = is_array( $ledger ) ? array_values( $ledger ) : array();
		$active_ledger = self::prune_applied_records( $ledger, $now );
		$ledger_removed = count( $ledger ) - count( $active_ledger );
		if ( $ledger_removed > 0 ) {
			update_option( self::RECOMMENDATION_APPLIED_OPTION, $active_ledger, false );
		}

		$review = array( 'reports_removed' => $reports_removed, 'dismissals_removed' => $dismissals_removed, 'ledger_removed' => $ledger_removed, 'active_reports' => count( $active_reports ), 'review_at' => $now );
		update_option( 'baocache_csp_evidence_review', $review, false );
		return $review;
	}

	/** Metadata-only data appropriate for an operator export. */
	public static function export_evidence_summary(): array {
		$reports = self::reports();
		$last_seen = 0;
		foreach ( $reports as $report ) {
			$last_seen = max( $last_seen, (int) ( $report['last_at'] ?? 0 ) );
		}
		$review = get_option( 'baocache_csp_evidence_review', array() );
		return array(
			'retention_days' => self::REPORT_RETENTION_DAYS,
			'active_report_groups' => count( $reports ),
			'last_seen_at' => $last_seen,
			'policy_fingerprint' => (string) self::policy_snapshot()['fingerprint'],
			'last_review' => is_array( $review ) ? array_intersect_key( $review, array( 'reports_removed' => true, 'dismissals_removed' => true, 'ledger_removed' => true, 'active_reports' => true, 'review_at' => true ) ) : array(),
		);
	}

	private static function prune_reports( array $reports, ?int $now = null ): array {
		$cutoff = ( $now ?? time() ) - self::REPORT_RETENTION_DAYS * DAY_IN_SECONDS;
		return array_values( array_filter( $reports, static fn( mixed $item ): bool => is_array( $item ) && (int) ( $item['last_at'] ?? 0 ) >= $cutoff ) );
	}

	private static function active_dismissals(): array {
		$dismissed = get_option( self::RECOMMENDATION_DISMISSED_OPTION, array() );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();
		$active = self::prune_dismissals( $dismissed );
		if ( count( $active ) !== count( $dismissed ) ) {
			update_option( self::RECOMMENDATION_DISMISSED_OPTION, $active, false );
		}
		return $active;
	}

	private static function prune_dismissals( array $dismissed, ?int $now = null ): array {
		$cutoff = ( $now ?? time() ) - self::REPORT_RETENTION_DAYS * DAY_IN_SECONDS;
		return array_filter( $dismissed, static fn( mixed $item ): bool => is_array( $item ) && (int) ( $item['dismissed_at'] ?? 0 ) >= $cutoff && '' !== (string) ( $item['fingerprint'] ?? '' ) );
	}

	private static function prune_applied_records( array $records, ?int $now = null ): array {
		$cutoff = ( $now ?? time() ) - self::LEDGER_RETENTION_DAYS * DAY_IN_SECONDS;
		return array_values( array_filter( $records, static fn( mixed $record ): bool => is_array( $record ) && (int) ( $record['applied_at'] ?? 0 ) >= $cutoff ) );
	}

	private static function blocked_origin( string $blocked ): string {
		$blocked = trim( $blocked );
		if ( '' === $blocked ) {
			return 'unknown';
		}
		if ( in_array( strtolower( $blocked ), array( 'inline', 'eval', 'wasm-eval', 'data', 'blob' ), true ) ) {
			return strtolower( $blocked );
		}
		$host = wp_parse_url( $blocked, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			return strtolower( preg_replace( '/[^a-z0-9.*:-]/i', '', $host ) ?: 'unknown' );
		}
		$scheme = wp_parse_url( $blocked, PHP_URL_SCHEME );
		return is_string( $scheme ) && '' !== $scheme ? strtolower( $scheme ) . ':' : 'unknown';
	}

	private static function recommendation_field( string $directive ): string {
		return match ( $directive ) {
			'script-src', 'script-src-elem', 'script-src-attr' => 'script',
			'style-src', 'style-src-elem', 'style-src-attr' => 'style',
			'img-src' => 'img',
			'font-src' => 'font',
			'connect-src' => 'connect',
			'frame-src', 'child-src' => 'frame',
			'worker-src' => 'worker',
			default => '',
		};
	}

	private static function recommendation_origin( string $blocked_origin ): string {
		$blocked_origin = strtolower( trim( $blocked_origin ) );
		if ( ! preg_match( '/^(?:\*\.)?[a-z0-9.-]+$/', $blocked_origin ) ) {
			return '';
		}
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $site_host || $blocked_origin === $site_host || str_ends_with( $blocked_origin, '.' . $site_host ) ) {
			return '';
		}
		return 'https://' . $blocked_origin;
	}

	private static function source_is_allowed( array $sources, string $origin ): bool {
		if ( in_array( "'none'", $sources, true ) ) {
			return false;
		}
		if ( in_array( 'https:', $sources, true ) || in_array( $origin, $sources, true ) ) {
			return true;
		}
		$host = (string) wp_parse_url( $origin, PHP_URL_HOST );
		foreach ( $sources as $source ) {
			$allowed_host = (string) wp_parse_url( $source, PHP_URL_HOST );
			if ( str_starts_with( $allowed_host, '*.' ) && str_ends_with( $host, substr( $allowed_host, 1 ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Merge automatic origins without ever overriding an explicit 'none'. */
	private static function merge_sources( mixed $configured, array $suggested ): array {
		$configured_tokens = self::lines( $configured );
		if ( in_array( "'none'", $configured_tokens, true ) ) {
			return self::source_list( "'none'" );
		}
		return self::source_list( implode( ' ', array_merge( $configured_tokens, $suggested ) ) );
	}

	private static function lines( mixed $value ): array {
		return preg_split( '/[\s,]+/', trim( (string) $value ) ) ?: array();
	}
}
