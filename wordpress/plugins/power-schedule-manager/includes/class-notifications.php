<?php
/**
 * Durable outbound notifications.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queues import summaries and delivers them outside the import transaction.
 */
final class Power_Schedule_Manager_Notifications {

	public const string WORKER_HOOK =
		'power_schedule_manager_process_notifications';

	private const int BATCH_SIZE = 10;

	private const int MAX_ATTEMPTS = 5;

	/**
	 * Register queue hooks.
	 */
	public function register(): void {
		add_action(
			'power_schedule_manager_import_completed',
			array( $this, 'enqueue_import' ),
			30,
			2
		);

		add_action(
			self::WORKER_HOOK,
			array( $this, 'process' )
		);

		add_action(
			'power_schedule_manager_daily_maintenance_completed',
			array( $this, 'enqueue_health_alert' ),
			20,
			2
		);

		add_action(
			'power_schedule_manager_lottery_draws_changed',
			array( $this, 'enqueue_lottery_updates' ),
			20,
			1
		);
	}

	/**
	 * Queue one compact push per changed lottery product.
	 *
	 * @param array<int,array<string,string>> $draws Changed lottery draws.
	 */
	public function enqueue_lottery_updates( array $draws ): void {
		$settings = self::settings();
		if ( empty( $settings['lottery_push_delivery_enabled'] ) ) {
			return;
		}

		$groups = array();
		foreach ( $draws as $draw ) {
			$topic = self::lottery_topic(
				(string) ( $draw['game_type'] ?? '' ),
				(string) ( $draw['region'] ?? '' )
			);
			if ( '' === $topic ) {
				continue;
			}
			$groups[ $topic ][] = $draw;
		}

		foreach ( $groups as $topic => $group ) {
			$keys = array();
			$dates = array();
			foreach ( $group as $draw ) {
				$keys[] = array(
					'draw_key' => (string) ( $draw['draw_key'] ?? '' ),
					'data_hash' => (string) ( $draw['data_hash'] ?? '' ),
				);
				$dates[] = (string) ( $draw['draw_date'] ?? '' );
			}
			sort( $keys );
			sort( $dates, SORT_STRING );
			$hash_source = wp_json_encode(
				array( 'topic' => $topic, 'draws' => $keys ),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
			if ( ! is_string( $hash_source ) ) {
				continue;
			}

			self::enqueue(
				'onesignal',
				array(
					'type'              => 'lottery_draws_updated',
					'lottery_topic'     => $topic,
					'draw_count'        => count( $group ),
					'date_from'         => $dates[0] ?? '',
					'date_to'           => $dates[ count( $dates ) - 1 ] ?? '',
					'url'               => self::lottery_url( $topic ),
					'notification_hash' => hash( 'sha256', $hash_source ),
				),
				hash( 'sha256', $hash_source )
			);
		}

		self::schedule_worker();
	}

	/**
	 * Queue one aggregate message for every enabled channel.
	 *
	 * @param int                  $run_id Import run ID.
	 * @param array<string,mixed> $result Import result.
	 */
	public function enqueue_import( int $run_id, array $result ): void {
		$settings = self::settings();
		$payload  = self::import_payload( $run_id, $result );

		foreach ( array( 'telegram', 'webhook', 'zalo' ) as $channel ) {
			if ( empty( $settings[ $channel . '_enabled' ] ) ) {
				continue;
			}

			self::enqueue(
				$channel,
				$payload,
				'import|' . $run_id . '|' . wp_json_encode( $result )
			);
		}

		/*
		 * Browser subscribers choose their own electricity areas. Do not create
		 * an alert when an import made no schedule changes; this prevents a
		 * routine unchanged import becoming unwanted push traffic.
		 */
		if (
			! empty( $settings['push_delivery_enabled'] )
		) {
			$push_payload = self::push_payload_for_import( $run_id, $payload );
			if ( null !== $push_payload ) {
				self::enqueue(
					'onesignal',
					$push_payload,
					(string) $push_payload['notification_hash']
				);
			}
		}

		self::schedule_worker();
	}

	/**
	 * Queue one daily health alert only when an actionable issue exists.
	 *
	 * @param array<string,int> $cleanup Cleanup counters.
	 * @param float             $duration Maintenance duration.
	 */
	public function enqueue_health_alert(
		array $cleanup,
		float $duration
	): void {
		$snapshot = Power_Schedule_Manager_System_Health::snapshot();
		$attention = isset( $snapshot['attention'] )
			&& is_array( $snapshot['attention'] )
				? array_slice( $snapshot['attention'], 0, 10 )
				: array();

		if ( array() === $attention ) {
			return;
		}

		$issues = array();

		foreach ( $attention as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$issues[] = array(
				'severity' => sanitize_key(
					(string) ( $item['severity'] ?? 'warning' )
				),
				'title'    => sanitize_text_field(
					(string) ( $item['title'] ?? '' )
				),
				'count'    => absint( $item['count'] ?? 0 ),
			);
		}

		if ( array() === $issues ) {
			return;
		}

		$settings = self::settings();
		$payload  = array(
			'type'            => 'system_health_attention',
			'issues'          => $issues,
			'cleanup'         => array_map( 'absint', $cleanup ),
			'duration_ms'     => (int) round( max( 0.0, $duration ) * 1000 ),
			'finished_at_utc' =>
				Power_Schedule_Manager_Database::utc_now(),
			'url'             => admin_url(
				'admin.php?page=power-schedule-manager-system-health'
			),
		);
		$dedupe = 'health|'
			. gmdate( 'Y-m-d' )
			. '|'
			. wp_json_encode( $issues );

		foreach ( array( 'telegram', 'webhook', 'zalo' ) as $channel ) {
			if ( empty( $settings[ $channel . '_enabled' ] ) ) {
				continue;
			}

			self::enqueue( $channel, $payload, $dedupe );
		}

		self::schedule_worker();
	}

	/**
	 * Process a bounded queue batch.
	 */
	public function process(): void {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::NOTIFICATIONS
			)
		) {
			return;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$now   = Power_Schedule_Manager_Database::utc_now();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$table}
				WHERE status IN ('pending','retry')
					AND available_at_utc <= %s
					AND (locked_at_utc IS NULL
						OR locked_at_utc < UTC_TIMESTAMP() - INTERVAL 10 MINUTE)
				ORDER BY id ASC
				LIMIT %d",
				$now,
				self::BATCH_SIZE
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$this->process_row( $row );
		}

		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}
				WHERE status IN ('pending','retry')"
		);

		if ( $remaining > 0 ) {
			self::schedule_worker( 60 );
		}
	}

	/**
	 * Queue statistics for health checks.
	 *
	 * @return array<string,int>
	 */
	public static function statistics(): array {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::NOTIFICATIONS
			)
		) {
			return array( 'pending' => 0, 'failed' => 0, 'sent' => 0 );
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$rows  = $wpdb->get_results(
			"SELECT status, COUNT(*) AS total
				FROM {$table}
				GROUP BY status",
			ARRAY_A
		);
		$out   = array( 'pending' => 0, 'failed' => 0, 'sent' => 0 );

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			if ( 'retry' === $status ) {
				$status = 'pending';
			}
			if ( isset( $out[ $status ] ) ) {
				$out[ $status ] += absint( $row['total'] ?? 0 );
			}
		}

		return $out;
	}

	/**
	 * Return a bounded operational queue snapshot.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 20 ): array {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::NOTIFICATIONS
			)
		) {
			return array();
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$limit = min( 50, max( 1, $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,channel,status,attempts,last_attempt_at_utc,
					response_code,onesignal_message_id,last_error,
					available_at_utc,sent_at_utc,updated_at_utc
				FROM {$table}
				ORDER BY id DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Requeue a bounded batch of terminal failures.
	 *
	 * @return int Updated rows.
	 */
	public static function retry_failed(): int {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::NOTIFICATIONS
			)
		) {
			return 0;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$now = Power_Schedule_Manager_Database::utc_now();
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status='retry',attempts=0,available_at_utc=%s,
					locked_at_utc=NULL,last_error=NULL,updated_at_utc=%s
				WHERE status='failed'
				ORDER BY id ASC
				LIMIT 100",
				$now,
				$now
			)
		);

		$updated = false === $result ? 0 : (int) $result;
		if ( $updated > 0 ) {
			self::schedule_worker();
		}

		return $updated;
	}

	/**
	 * Send a connection test without creating a durable queue row.
	 *
	 * @param string $channel Notification channel.
	 * @return int|WP_Error
	 */
	public static function test_channel( string $channel ): int|WP_Error {
		if (
			! in_array(
				$channel,
				array( 'telegram', 'webhook', 'zalo' ),
				true
			)
		) {
			return new WP_Error( 'unsupported_notification_channel' );
		}

		$payload = array(
			'type'       => 'connection_test',
			'run_id'     => 0,
			'unit_code'  => 'TEST',
			'unit_name'  => __(
				'Kiểm tra kết nối Cúp Điện Lâm Đồng',
				'power-schedule-manager'
			),
			'found'      => 0,
			'inserted'   => 0,
			'updated'    => 0,
			'unchanged'  => 0,
			'finished_at_utc' =>
				Power_Schedule_Manager_Database::utc_now(),
			'url'        => home_url( '/lich-cup-dien/' ),
		);

		return ( new self() )->deliver( $channel, $payload );
	}

	/**
	 * Persist one idempotent queue item.
	 *
	 * @param string               $channel Channel.
	 * @param array<string,mixed> $payload Payload.
	 * @param string               $dedupe Dedupe source.
	 */
	private static function enqueue(
		string $channel,
		array $payload,
		string $dedupe
	): void {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$now   = Power_Schedule_Manager_Database::utc_now();
		$json  = wp_json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $json ) || strlen( $json ) > 65535 ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table}
					(channel,dedupe_hash,notification_hash,status,payload,attempts,
					available_at_utc,created_at_utc,updated_at_utc)
				VALUES (%s,UNHEX(%s),UNHEX(%s),'pending',%s,0,%s,%s,%s)",
				sanitize_key( $channel ),
				hash( 'sha256', $channel . '|' . $dedupe ),
				hash( 'sha256', $dedupe ),
				$json,
				$now,
				$now,
				$now
			)
		);
	}

	/**
	 * Claim and send one row.
	 *
	 * @param array<string,mixed> $row Queue row.
	 */
	private function process_row( array $row ): void {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$id    = absint( $row['id'] ?? 0 );
		$now   = Power_Schedule_Manager_Database::utc_now();

		if ( $id < 1 ) {
			return;
		}

		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status='sending', locked_at_utc=%s, updated_at_utc=%s
				WHERE id=%d AND status IN ('pending','retry')",
				$now,
				$now,
				$id
			)
		);

		if ( 1 !== $claimed ) {
			return;
		}

		$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
		$result  = is_array( $payload )
			? $this->deliver( (string) $row['channel'], $payload )
			: new WP_Error( 'invalid_notification_payload' );
		$attempt = absint( $row['attempts'] ?? 0 ) + 1;

		if ( ! is_wp_error( $result ) ) {
			$response_code = is_array( $result )
				? absint( $result['response_code'] ?? 0 )
				: absint( $result );
			$message_id = is_array( $result )
				? self::limit_text(
					sanitize_text_field(
						(string) ( $result['message_id'] ?? '' )
					),
					64
				)
				: '';
			$cancelled = is_array( $result )
				&& ! empty( $result['cancelled'] );
			$wpdb->update(
				$table,
				array(
					'status'         => $cancelled ? 'cancelled' : 'sent',
					'attempts'       => $attempt,
					'last_attempt_at_utc' => $now,
					'locked_at_utc'  => null,
					'response_code'  => $response_code,
					'onesignal_message_id' => $message_id,
					'last_error'     => null,
					'sent_at_utc'    => $now,
					'updated_at_utc' => $now,
				),
				array( 'id' => $id )
			);
			return;
		}

		$retry = self::should_retry( $result ) && $attempt < self::MAX_ATTEMPTS;
		$wpdb->update(
			$table,
			array(
				'status'           => $retry ? 'retry' : 'failed',
				'attempts'         => $attempt,
				'last_attempt_at_utc' => $now,
				'locked_at_utc'    => null,
				'response_code'    => self::error_response_code( $result ),
				'last_error'       => substr(
					sanitize_text_field( $result->get_error_message() ),
					0,
					1000
				),
				'available_at_utc' => gmdate(
					'Y-m-d H:i:s',
					$retry
						? time() + min( HOUR_IN_SECONDS, 60 * ( 2 ** $attempt ) )
						: time()
				),
				'updated_at_utc'   => $now,
			),
			array( 'id' => $id )
		);
	}

	/** Retry only transport failures, throttling, and temporary server errors. */
	private static function should_retry( WP_Error $error ): bool {
		$code = self::error_response_code( $error );
		if ( 429 === $code || ( $code >= 500 && $code <= 599 ) ) {
			return true;
		}

		return in_array(
			$error->get_error_code(),
			array( 'http_request_failed', 'http_request_timeout' ),
			true
		);
	}

	/** Extract an HTTP response code attached to a delivery error. */
	private static function error_response_code( WP_Error $error ): int {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['response_code'] ) ) {
			return absint( $data['response_code'] );
		}

		if ( preg_match( '/notification_http_([0-9]{3})$/', $error->get_error_code(), $matches ) ) {
			return absint( $matches[1] );
		}

		return 0;
	}

	/**
	 * Deliver through an allowlisted adapter.
	 *
	 * @param string               $channel Channel.
	 * @param array<string,mixed> $payload Payload.
	 * @return int|WP_Error
	 */
	private function deliver( string $channel, array $payload ): array|int|WP_Error {
		return match ( $channel ) {
			'telegram' => $this->send_telegram( $payload ),
			'webhook'  => $this->send_webhook( $payload ),
			'zalo'     => $this->send_zalo( $payload ),
			'onesignal' => $this->send_onesignal( $payload ),
			default    => new WP_Error( 'unsupported_notification_channel' ),
		};
	}

	/**
	 * Send an update to subscribers following the imported electricity area.
	 *
	 * The REST API key remains server-side. Visitors only receive the public
	 * App ID; their browser writes the matching OneSignal tag after an explicit
	 * area selection.
	 */
	private function send_onesignal( array $payload ): array|WP_Error {
		$settings = self::settings();
		$app_id = Power_Schedule_Manager_Assets::onesignal_app_id();
		$api_key = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_ONESIGNAL_REST_API_KEY',
			(string) ( $settings['onesignal_rest_api_key_encrypted'] ?? '' )
		);
		$is_lottery = 'lottery_draws_updated' === ( $payload['type'] ?? '' );
		$unit_code = sanitize_text_field( (string) ( $payload['unit_code'] ?? '' ) );
		$lottery_topic = self::lottery_topic(
			(string) ( $payload['lottery_topic'] ?? '' )
		);

		if (
			'' === $app_id
			|| '' === $api_key
			|| ( ! $is_lottery && '' === $unit_code )
			|| ( $is_lottery && '' === $lottery_topic )
		) {
			return new WP_Error( 'onesignal_not_configured' );
		}

		$title = self::notification_template(
			(string) (
				$is_lottery
					? ( $settings['lottery_push_notification_title'] ?? 'Kết quả xổ số đã cập nhật: %lottery_name%' )
					: ( $settings['push_notification_title']
					?? 'Cập nhật lịch cúp điện %unit_name%'
					)
			),
			$payload
		);
		$message = self::notification_template(
			(string) (
				$is_lottery
					? ( $settings['lottery_push_notification_message'] ?? 'Đã cập nhật %draw_count% kết quả %lottery_name% ngày %date_from%. Nhấn để xem chi tiết.' )
					: ( $settings['push_notification_message']
					?? 'Có %event_count% lịch cúp điện mới hoặc vừa cập nhật, áp dụng từ %date_from% đến %date_to%. Nhấn để xem chi tiết.'
					)
			),
			$payload
		);

		return $this->post_onesignal(
			'https://api.onesignal.com/notifications?c=push',
			array(
				'app_id'         => $app_id,
				'target_channel' => 'push',
				'headings'       => array( 'en' => $title ),
				'contents'       => array( 'en' => $message ),
				'url'            => esc_url_raw( (string) ( $payload['url'] ?? '' ) ),
				'data'           => $is_lottery
					? array( 'lottery_topic' => $lottery_topic )
					: array( 'unit_code' => $unit_code ),
				'filters'        => $is_lottery
					? self::lottery_filters( $lottery_topic )
					: array(
						array(
							'field'    => 'tag',
							'key'      => self::onesignal_area_tag( $unit_code ),
							'relation' => '=',
							'value'    => '1',
						),
					),
			),
			array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Key ' . $api_key,
			)
		);
	}

	/**
	 * Create an OneSignal message and retain its ID for the operational queue.
	 *
	 * A successful 200 without an ID means the filter currently has no eligible
	 * subscriptions. It is recorded as cancelled rather than retried.
	 *
	 * @return array{response_code:int,message_id:string,cancelled:bool}|WP_Error
	 */
	private function post_onesignal(
		string $url,
		array $data,
		array $headers
	): array|WP_Error {
		$body = wp_json_encode( $data );
		if ( ! is_string( $body ) ) {
			return new WP_Error( 'onesignal_encode_failed' );
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'limit_response_size' => 1048576,
				'headers'             => $headers,
				'body'                => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'notification_http_' . $code,
				sprintf( 'HTTP %d', $code ),
				array( 'response_code' => $code )
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$message_id = is_array( $decoded )
			? sanitize_text_field( (string) ( $decoded['id'] ?? '' ) )
			: '';

		return array(
			'response_code' => $code,
			'message_id'    => self::limit_text( $message_id, 64 ),
			'cancelled'     => '' === $message_id,
		);
	}

	/** Create the shared OneSignal tag name for a public electricity area. */
	private static function onesignal_area_tag( string $unit_code ): string {
		return 'psm_area_' . strtolower( preg_replace(
			'/[^A-Za-z0-9_]/',
			'',
			$unit_code
		) );
	}

	/** Normalize an allowed lottery preference topic. */
	private static function lottery_topic(
		string $topic,
		string $traditional_region = ''
	): string {
		$topic = sanitize_key( $topic );
		if ( 'traditional' === $topic ) {
			$topic = sanitize_key( $traditional_region );
		}

		return in_array(
			$topic,
			array(
				'north', 'central', 'south', 'mega645', 'power655',
				'max3d', 'max3dplus', 'max3dpro', 'keno',
				'dientoan123', 'dientoan6x36', 'thantai',
			),
			true
		) ? $topic : '';
	}

	/** Build the OneSignal OR filter for a product and the all-lottery topic. */
	private static function lottery_filters( string $topic ): array {
		return array(
			array(
				'field'    => 'tag',
				'key'      => 'psm_lottery_all',
				'relation' => '=',
				'value'    => '1',
			),
			array( 'operator' => 'OR' ),
			array(
				'field'    => 'tag',
				'key'      => 'psm_lottery_' . $topic,
				'relation' => '=',
				'value'    => '1',
			),
		);
	}

	/** Resolve a public result page for a lottery product. */
	private static function lottery_url( string $topic ): string {
		$paths = array(
			'north' => '/xo-so-mien-bac/',
			'central' => '/xo-so-mien-trung/',
			'south' => '/xo-so-mien-nam/',
			'mega645' => '/xo-so-mega-645/',
			'power655' => '/xo-so-power-655/',
			'max3d' => '/xo-so-max-3d/',
			'max3dplus' => '/xo-so-max-3d/',
			'max3dpro' => '/xo-so-max-3d/',
			'keno' => '/xo-so-keno/',
			'dientoan123' => '/xo-so-dien-toan/',
			'dientoan6x36' => '/xo-so-dien-toan/',
			'thantai' => '/xo-so-dien-toan/',
		);

		return home_url( $paths[ $topic ] ?? '/ket-qua-xo-so/' );
	}

	/** Return the human-readable product name for notification templates. */
	private static function lottery_name( string $topic ): string {
		return array(
			'north' => 'Xổ số Miền Bắc',
			'central' => 'Xổ số Miền Trung',
			'south' => 'Xổ số Miền Nam',
			'mega645' => 'Mega 6/45',
			'power655' => 'Power 6/55',
			'max3d' => 'Max 3D',
			'max3dplus' => 'Max 3D+',
			'max3dpro' => 'Max 3D Pro',
			'keno' => 'Keno',
			'dientoan123' => 'Điện toán 123',
			'dientoan6x36' => 'Điện toán 6x36',
			'thantai' => 'Thần Tài 4',
		)[ $topic ] ?? 'Xổ số';
	}

	/** Render the small, admin-controlled push notification template. */
	private static function notification_template(
		string $template,
		array $payload
	): string {
		if ( 'Lịch cúp điện đã cập nhật: %unit_name%' === $template ) {
			$template = 'Cập nhật lịch cúp điện %unit_name%';
		}
		if ( 'Đã cập nhật %event_count% lịch cúp điện từ ngày %date_from% đến %date_to%. Nhấn để xem chi tiết.' === $template ) {
			$template = 'Có %event_count% lịch cúp điện mới hoặc vừa cập nhật, áp dụng từ %date_from% đến %date_to%. Nhấn để xem chi tiết.';
		}
		$date_from = self::format_local_date(
			(string) ( $payload['date_from'] ?? '' )
		);
		$date_to = self::format_local_date(
			(string) ( $payload['date_to'] ?? '' )
		);
		if ( '' !== $date_from && $date_from === $date_to ) {
			$template = str_replace(
				'áp dụng từ %date_from% đến %date_to%',
				'trong ngày %date_from%',
				$template
			);
		}

		$replacements = array(
			'%unit_name%' => sanitize_text_field(
				(string) ( $payload['unit_name'] ?? '' )
			),
			'%unit_code%' => sanitize_text_field(
				(string) ( $payload['unit_code'] ?? '' )
			),
			'%found%' => (string) absint( $payload['found'] ?? 0 ),
			'%inserted%' => (string) absint( $payload['inserted'] ?? 0 ),
			'%updated%' => (string) absint( $payload['updated'] ?? 0 ),
			'%event_count%' => (string) absint( $payload['event_count'] ?? 0 ),
			'%draw_count%' => (string) absint( $payload['draw_count'] ?? 0 ),
			'%lottery_name%' => self::lottery_name(
				self::lottery_topic( (string) ( $payload['lottery_topic'] ?? '' ) )
			),
			'%date_from%' => $date_from,
			'%date_to%' => $date_to,
			'%url%' => esc_url_raw( (string) ( $payload['url'] ?? '' ) ),
		);
		$template = strtr( sanitize_textarea_field( $template ), $replacements );

		return self::limit_text( $template, 240 );
	}

	/** Format an ISO local date for the Vietnamese notification copy. */
	private static function format_local_date( string $date ): string {
		$date = sanitize_text_field( $date );
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		return false === $parsed ? $date : $parsed->format( 'd/m/Y' );
	}

	/** Limit text safely when mbstring is unavailable. */
	private static function limit_text( string $text, int $limit ): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $text, 0, $limit, 'UTF-8' )
			: substr( $text, 0, $limit );
	}

	/**
	 * Send Telegram text.
	 */
	private function send_telegram( array $payload ): int|WP_Error {
		$settings = self::settings();
		$token = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_TELEGRAM_BOT_TOKEN',
			(string) ( $settings['telegram_bot_token_encrypted'] ?? '' )
		);
		$chat_id  = trim( (string) ( $settings['telegram_chat_id'] ?? '' ) );

		if ( '' === $token || '' === $chat_id ) {
			return new WP_Error( 'telegram_not_configured' );
		}

		if (
			1 !== preg_match(
				'/\A[0-9]{6,12}:[A-Za-z0-9_-]{20,}\z/',
				$token
			)
		) {
			return new WP_Error( 'telegram_invalid_token' );
		}

		return self::post_json(
			'https://api.telegram.org/bot'
				. $token
				. '/sendMessage',
			array(
				'chat_id'                  => $chat_id,
				'text'                     => self::message( $payload ),
				'disable_web_page_preview' => true,
			)
		);
	}

	/**
	 * Send signed generic webhook.
	 */
	private function send_webhook( array $payload ): int|WP_Error {
		$settings = self::settings();
		$url      = trim( (string) ( $settings['webhook_url'] ?? '' ) );
		$secret = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_WEBHOOK_SECRET',
			(string) ( $settings['webhook_secret_encrypted'] ?? '' )
		);

		if (
			'' === $secret
			|| ! wp_http_validate_url( $url )
			|| ! str_starts_with( strtolower( $url ), 'https://' )
		) {
			return new WP_Error( 'webhook_not_configured' );
		}

		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) ) {
			return new WP_Error( 'webhook_encode_failed' );
		}

		return self::remote_post(
			$url,
			$body,
			array(
				'Content-Type'            => 'application/json',
				'X-PSM-Signature-SHA256' => hash_hmac( 'sha256', $body, $secret ),
			)
		);
	}

	/**
	 * Send a Zalo OA customer-service message.
	 */
	private function send_zalo( array $payload ): int|WP_Error {
		$settings = self::settings();
		$token = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_ZALO_ACCESS_TOKEN',
			(string) ( $settings['zalo_access_token_encrypted'] ?? '' )
		);
		$user_id  = trim( (string) ( $settings['zalo_recipient_id'] ?? '' ) );

		if ( '' === $token || '' === $user_id ) {
			return new WP_Error( 'zalo_not_configured' );
		}

		$endpoint = (string) apply_filters(
			'power_schedule_manager_zalo_endpoint',
			'https://openapi.zalo.me/v3.0/oa/message/cs'
		);

		if (
			! wp_http_validate_url( $endpoint )
			|| ! str_starts_with(
				strtolower( $endpoint ),
				'https://'
			)
		) {
			return new WP_Error( 'zalo_invalid_endpoint' );
		}

		$body = wp_json_encode(
			array(
				'recipient' => array( 'user_id' => $user_id ),
				'message'   => array( 'text' => self::message( $payload ) ),
			)
		);

		return is_string( $body )
			? self::remote_post(
				$endpoint,
				$body,
				array(
					'Content-Type' => 'application/json',
					'access_token' => $token,
				)
			)
			: new WP_Error( 'zalo_encode_failed' );
	}

	/**
	 * POST JSON through WordPress safe HTTP API.
	 */
	private static function post_json( string $url, array $data ): int|WP_Error {
		$body = wp_json_encode( $data );
		return is_string( $body )
			? self::remote_post(
				$url,
				$body,
				array( 'Content-Type' => 'application/json' )
			)
			: new WP_Error( 'notification_encode_failed' );
	}

	/**
	 * Validate a remote response.
	 */
	private static function remote_post(
		string $url,
		string $body,
		array $headers
	): int|WP_Error {
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'limit_response_size' => 1048576,
				'headers'             => $headers,
				'body'                => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300
			? $code
			: new WP_Error(
				'notification_http_' . $code,
				sprintf( 'HTTP %d', $code ),
				array( 'response_code' => $code )
			);
	}

	/**
	 * Build one semantic OneSignal job for a completed import.
	 *
	 * An event revision is written only for a newly-created event or a changed
	 * schedule payload. Unchanged imports merely touch last-seen metadata and
	 * therefore produce no row here. One import run becomes one area-level
	 * message, no matter how many events changed in that run.
	 *
	 * @param int                  $run_id Import run ID.
	 * @param array<string,mixed> $base   Aggregate import payload.
	 * @return array<string,mixed>|null
	 */
	private static function push_payload_for_import(
		int $run_id,
		array $base
	): ?array {
		global $wpdb;

		$revisions = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_REVISIONS
		);
		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT revisions.event_id,
					LOWER(HEX(revisions.content_hash)) AS content_hash,
					events.local_date,events.start_at_utc,events.end_at_utc,
					events.area,events.reason,events.status
				FROM {$revisions} AS revisions
				INNER JOIN {$events} AS events ON events.id=revisions.event_id
				WHERE revisions.import_run_id=%d
					AND revisions.change_type IN ('created','updated')
				ORDER BY revisions.event_id ASC",
				$run_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || array() === $rows ) {
			return null;
		}

		$fingerprint_events = array();
		$local_dates = array();
		foreach ( $rows as $row ) {
			$fingerprint_events[] = array(
				'event_id' => absint( $row['event_id'] ?? 0 ),
				'content_hash' => strtolower(
					sanitize_text_field( (string) ( $row['content_hash'] ?? '' ) )
				),
			);
			$date = sanitize_text_field( (string) ( $row['local_date'] ?? '' ) );
			if ( '' !== $date ) {
				$local_dates[] = $date;
			}
		}

		sort( $local_dates, SORT_STRING );
		$hash_source = wp_json_encode(
			array(
				'unit_code' => (string) ( $base['unit_code'] ?? '' ),
				'events' => $fingerprint_events,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		if ( ! is_string( $hash_source ) ) {
			return null;
		}

		$base['event_count'] = count( $fingerprint_events );
		$base['date_from'] = $local_dates[0] ?? '';
		$base['date_to'] = $local_dates[ count( $local_dates ) - 1 ] ?? '';
		$base['notification_hash'] = hash( 'sha256', $hash_source );

		return $base;
	}

	/**
	 * Build safe aggregate import payload.
	 */
	private static function import_payload(
		int $run_id,
		array $result
	): array {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$run   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT unit_code, found_count, inserted_count,
					updated_count, unchanged_count, finished_at_utc
				FROM {$table} WHERE id=%d LIMIT 1",
				$run_id
			),
			ARRAY_A
		);
		$code = sanitize_text_field( (string) ( $run['unit_code'] ?? '' ) );
		$unit = Power_Schedule_Manager_Units::find_by_code( $code );
		$term_id = Power_Schedule_Manager_Units::find_term_id_by_unit_code(
			$code
		);
		$unit_url = null !== $term_id
			? get_term_link(
				$term_id,
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
			: false;

		return array(
			'type'       => 'schedule_import_completed',
			'run_id'     => $run_id,
			'unit_code'  => $code,
			'unit_name'  => sanitize_text_field(
				(string) ( $unit['name'] ?? $code )
			),
			'found'      => absint( $run['found_count'] ?? 0 ),
			'inserted'   => absint( $result['inserted'] ?? 0 ),
			'updated'    => absint( $result['updated'] ?? 0 ),
			'unchanged'  => absint( $result['unchanged'] ?? 0 ),
			'finished_at_utc' => sanitize_text_field(
				(string) ( $run['finished_at_utc'] ?? '' )
			),
			'url'        => is_string( $unit_url )
				? $unit_url
				: ( get_post_type_archive_link(
					Power_Schedule_Manager_Post_Type::POST_TYPE
				) ?: home_url( '/lich-cup-dien/' ) ),
		);
	}

	/**
	 * Human-readable message.
	 */
	private static function message( array $payload ): string {
		if ( 'connection_test' === ( $payload['type'] ?? '' ) ) {
			return sprintf(
				"✅ Kết nối thông báo Cúp Điện Lâm Đồng hoạt động.\nThời gian UTC: %s\n%s",
				(string) ( $payload['finished_at_utc'] ?? '' ),
				esc_url_raw( (string) ( $payload['url'] ?? '' ) )
			);
		}

		if ( 'system_health_attention' === ( $payload['type'] ?? '' ) ) {
			$lines = array(
				'⚠️ Cúp Điện Lâm Đồng có nội dung cần xử lý:',
			);

			foreach (
				is_array( $payload['issues'] ?? null )
					? $payload['issues']
					: array()
				as $issue
			) {
				if ( ! is_array( $issue ) ) {
					continue;
				}

				$lines[] = sprintf(
					'• %s: %d',
					(string) ( $issue['title'] ?? '' ),
					absint( $issue['count'] ?? 0 )
				);
			}

			$lines[] = esc_url_raw( (string) ( $payload['url'] ?? '' ) );

			return implode( "\n", $lines );
		}

		return sprintf(
			"Đã cập nhật lịch cúp điện %s (%s).\nTìm thấy: %d · Mới: %d · Cập nhật: %d · Không đổi: %d\n%s",
			(string) ( $payload['unit_name'] ?? '' ),
			(string) ( $payload['unit_code'] ?? '' ),
			absint( $payload['found'] ?? 0 ),
			absint( $payload['inserted'] ?? 0 ),
			absint( $payload['updated'] ?? 0 ),
			absint( $payload['unchanged'] ?? 0 ),
			esc_url_raw( (string) ( $payload['url'] ?? '' ) )
		);
	}

	/**
	 * Read settings.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings(): array {
		$value = get_option( POWER_SCHEDULE_MANAGER_SETTINGS_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Schedule one non-duplicated worker.
	 */
	private static function schedule_worker( int $delay = 10 ): void {
		if ( false === wp_next_scheduled( self::WORKER_HOOK ) ) {
			wp_schedule_single_event(
				time() + max( 1, $delay ),
				self::WORKER_HOOK,
				array(),
				true
			);
		}
	}
}
