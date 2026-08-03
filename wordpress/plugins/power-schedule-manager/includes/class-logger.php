<?php
/**
 * Import history and protected logging.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads import history and emits protected diagnostic events.
 */
final class Power_Schedule_Manager_Logger {

	/**
	 * Maximum history rows per query.
	 */
	private const int MAX_QUERY_LIMIT = 100;

	/**
	 * Import-run statuses.
	 *
	 * @var array<string, true>
	 */
	private const array ALLOWED_RUN_STATUSES = array(
		'preview'   => true,
		'running'   => true,
		'completed' => true,
		'failed'    => true,
	);

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Return paginated import history.
	 *
	 * Raw payload is never included in list queries.
	 *
	 * @param array<string, mixed> $arguments Query arguments.
	 *
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     total: int,
	 *     page: int,
	 *     per_page: int,
	 *     total_pages: int
	 * }
	 */
	public static function import_history(
		array $arguments = array()
	): array {
		self::assert_can_view_logs();

		global $wpdb;

		$arguments = wp_parse_args(
			$arguments,
			array(
				'unit_code' => '',
				'status'    => '',
				'date_from' => '',
				'date_to'   => '',
				'user_id'   => 0,
				'page'      => 1,
				'per_page'  => 20,
			)
		);

		$page = max(
			1,
			absint( $arguments['page'] )
		);

		$per_page = min(
			self::MAX_QUERY_LIMIT,
			max( 1, absint( $arguments['per_page'] ) )
		);

		$offset = ( $page - 1 ) * $per_page;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);

		$where  = array( '1 = 1' );
		$values = array();

		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$arguments['unit_code']
		);

		if ( '' !== $unit_code ) {
			$where[]  = 'unit_code = %s';
			$values[] = $unit_code;
		}

		$status = self::sanitize_run_status(
			$arguments['status']
		);

		if ( '' !== $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		$date_from = self::sanitize_optional_date(
			$arguments['date_from']
		);

		if ( '' !== $date_from ) {
			$where[]  = 'started_at_utc >= %s';
			$values[] = $date_from . ' 00:00:00';
		}

		$date_to = self::sanitize_optional_date(
			$arguments['date_to']
		);

		if ( '' !== $date_to ) {
			$where[]  = 'started_at_utc <= %s';
			$values[] = $date_to . ' 23:59:59';
		}

		$user_id = absint( $arguments['user_id'] );

		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $user_id;
		}

		$where_sql = implode(
			' AND ',
			$where
		);

		$count_sql = "SELECT COUNT(*)
			FROM {$table}
			WHERE {$where_sql}";

		$total = array() === $values
			? (int) $wpdb->get_var( $count_sql )
			: (int) $wpdb->get_var(
				$wpdb->prepare(
					$count_sql,
					$values
				)
			);

		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$sql = "SELECT
				id,
				run_uuid,
				user_id,
				unit_id,
				unit_code,
				source,
				source_url,
				status,
				LOWER(HEX(payload_hash)) AS payload_hash,
				LOWER(HEX(preview_token_hash)) AS preview_token_hash,
				payload_bytes,
				found_count,
				inserted_count,
				updated_count,
				unchanged_count,
				duplicate_count,
				warning_count,
				error_count,
				error_code,
				error_message,
				started_at_utc,
				finished_at_utc,
				expires_at_utc,
				created_at_utc,
				updated_at_utc
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY started_at_utc DESC, id DESC
			LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$query_values
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		return array(
			'items'       => array_map(
				array( self::class, 'cast_run_row' ),
				$rows
			),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total > 0
				? (int) ceil( $total / $per_page )
				: 0,
		);
	}

	/**
	 * Find one import run.
	 *
	 * Viewing raw payload requires settings-management permission because it
	 * may contain operational or customer-related text.
	 *
	 * @param int  $run_id          Import run ID.
	 * @param bool $include_payload Include raw payload.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_import_run(
		int $run_id,
		bool $include_payload = false
	): ?array {
		self::assert_can_view_logs();

		if (
			$include_payload
			&& ! Power_Schedule_Manager_Capabilities::current_user_can_manage_settings()
		) {
			throw new RuntimeException(
				'raw_payload_permission_denied'
			);
		}

		global $wpdb;

		$run_id = absint( $run_id );

		if ( $run_id < 1 ) {
			return null;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);

		$payload_column = $include_payload
			? ', raw_payload'
			: '';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					id,
					run_uuid,
					user_id,
					unit_id,
					unit_code,
					source,
					source_url,
					status,
					LOWER(HEX(payload_hash)) AS payload_hash,
					LOWER(HEX(preview_token_hash)) AS preview_token_hash,
					payload_bytes,
					found_count,
					inserted_count,
					updated_count,
					unchanged_count,
					duplicate_count,
					warning_count,
					error_count,
					error_code,
					error_message,
					started_at_utc,
					finished_at_utc,
					expires_at_utc,
					created_at_utc,
					updated_at_utc
					{$payload_column}
				FROM {$table}
				WHERE id = %d
				LIMIT 1",
				$run_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$run = self::cast_run_row( $row );

		if (
			$include_payload
			&& array_key_exists( 'raw_payload', $row )
		) {
			$run['raw_payload'] = is_string( $row['raw_payload'] )
				? $row['raw_payload']
				: null;
		}

		return $run;
	}

	/**
	 * Return bounded event and revision activity for one import run.
	 *
	 * Raw payload and hash secrets are intentionally excluded. The detail
	 * screen is an investigation aid, not a payload export endpoint.
	 *
	 * @param int $run_id Import run ID.
	 * @return array{
	 *     events: array<int,array<string,mixed>>,
	 *     revisions: array<int,array<string,mixed>>,
	 *     events_total: int,
	 *     revisions_total: int
	 * }
	 */
	public static function import_run_activity( int $run_id ): array {
		self::assert_can_view_logs();

		global $wpdb;

		$run_id = absint( $run_id );

		if ( $run_id < 1 ) {
			return array(
				'events'          => array(),
				'revisions'       => array(),
				'events_total'    => 0,
				'revisions_total' => 0,
			);
		}

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$revisions = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_REVISIONS
		);

		$events_total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$events} WHERE import_run_id = %d",
				$run_id
			)
		);

		$revisions_total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$revisions} WHERE import_run_id = %d",
				$run_id
			)
		);

		$event_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					post_id,
					unit_code,
					local_date,
					start_at_utc,
					end_at_utc,
					area,
					reason,
					status,
					sync_count,
					updated_at_utc
				FROM {$events}
				WHERE import_run_id = %d
				ORDER BY start_at_utc ASC, id ASC
				LIMIT 100",
				$run_id
			),
			ARRAY_A
		);

		$revision_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					event_id,
					user_id,
					revision_number,
					change_type,
					created_at_utc
				FROM {$revisions}
				WHERE import_run_id = %d
				ORDER BY created_at_utc ASC, id ASC
				LIMIT 100",
				$run_id
			),
			ARRAY_A
		);

		return array(
			'events'          => is_array( $event_rows )
				? $event_rows
				: array(),
			'revisions'       => is_array( $revision_rows )
				? $revision_rows
				: array(),
			'events_total'    => $events_total,
			'revisions_total' => $revisions_total,
		);
	}

	/**
	 * Return aggregate import statistics.
	 *
	 * @param string $date_from UTC date in Y-m-d.
	 * @param string $date_to   UTC date in Y-m-d.
	 *
	 * @return array<string, int>
	 */
	public static function statistics(
		string $date_from,
		string $date_to
	): array {
		self::assert_can_view_logs();

		global $wpdb;

		$date_from =
			Power_Schedule_Manager_Validator::validate_local_date(
				$date_from
			);

		$date_to =
			Power_Schedule_Manager_Validator::validate_local_date(
				$date_to
			);

		if ( $date_to < $date_from ) {
			throw new InvalidArgumentException(
				'invalid_statistics_date_range'
			);
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_runs,
					SUM(status = 'completed') AS completed_runs,
					SUM(status = 'failed') AS failed_runs,
					COALESCE(SUM(found_count), 0) AS found_count,
					COALESCE(SUM(inserted_count), 0) AS inserted_count,
					COALESCE(SUM(updated_count), 0) AS updated_count,
					COALESCE(SUM(unchanged_count), 0) AS unchanged_count,
					COALESCE(SUM(duplicate_count), 0) AS duplicate_count,
					COALESCE(SUM(warning_count), 0) AS warning_count,
					COALESCE(SUM(error_count), 0) AS error_count
				FROM {$table}
				WHERE started_at_utc BETWEEN %s AND %s",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return self::empty_statistics();
		}

		return array(
			'total_runs'      => (int) ( $row['total_runs'] ?? 0 ),
			'completed_runs'  => (int) ( $row['completed_runs'] ?? 0 ),
			'failed_runs'     => (int) ( $row['failed_runs'] ?? 0 ),
			'found_count'     => (int) ( $row['found_count'] ?? 0 ),
			'inserted_count'  => (int) ( $row['inserted_count'] ?? 0 ),
			'updated_count'   => (int) ( $row['updated_count'] ?? 0 ),
			'unchanged_count' => (int) ( $row['unchanged_count'] ?? 0 ),
			'duplicate_count' => (int) ( $row['duplicate_count'] ?? 0 ),
			'warning_count'   => (int) ( $row['warning_count'] ?? 0 ),
			'error_count'     => (int) ( $row['error_count'] ?? 0 ),
		);
	}

	/**
	 * Record a protected diagnostic error.
	 *
	 * No database row is created because import history already owns database
	 * logging. Other integrations may listen to the diagnostic action.
	 *
	 * @param string               $code      Error code.
	 * @param Throwable|string     $error     Error.
	 * @param array<string, mixed> $context   Non-sensitive context.
	 *
	 * @return void
	 */
	public static function error(
		string $code,
		Throwable|string $error,
		array $context = array()
	): void {
		$code = sanitize_key( $code );

		if ( '' === $code ) {
			$code = 'unknown_error';
		}

		$message = $error instanceof Throwable
			? $error->getMessage()
			: $error;

		$message = preg_replace(
			'/[\r\n\0]+/',
			' ',
			wp_strip_all_tags( $message )
		) ?? '';

		$message = substr( $message, 0, 1000 );
		$context = self::sanitize_context( $context );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Cúp Điện Lâm Đồng [%s]: %s',
					$code,
					$message
				)
			);
		}

		/**
		 * Fires when the plugin records a protected diagnostic error.
		 *
		 * Listeners must not expose the message directly to frontend users.
		 *
		 * @param string               $code    Error code.
		 * @param string               $message Sanitized message.
		 * @param array<string, mixed> $context Sanitized context.
		 */
		do_action(
			'power_schedule_manager_diagnostic_error',
			$code,
			$message,
			$context
		);
	}

	/**
	 * Require log-view permission.
	 *
	 * @return void
	 */
	private static function assert_can_view_logs(): void {
		if (
			! is_user_logged_in()
			|| ! Power_Schedule_Manager_Capabilities::current_user_can_view_logs()
		) {
			throw new RuntimeException(
				'import_log_permission_denied'
			);
		}
	}

	/**
	 * Sanitize optional query date.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_optional_date(
		mixed $value
	): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		return Power_Schedule_Manager_Validator::validate_local_date(
			$value
		);
	}

	/**
	 * Sanitize optional import-run status.
	 *
	 * @param mixed $status Raw status.
	 *
	 * @return string
	 */
	private static function sanitize_run_status(
		mixed $status
	): string {
		if ( ! is_string( $status ) ) {
			return '';
		}

		$status = sanitize_key( $status );

		return isset( self::ALLOWED_RUN_STATUSES[ $status ] )
			? $status
			: '';
	}

	/**
	 * Cast import-run database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 *
	 * @return array<string, mixed>
	 */
	private static function cast_run_row(
		array $row
	): array {
		return array(
			'id'                 => (int) ( $row['id'] ?? 0 ),
			'run_uuid'           => (string) ( $row['run_uuid'] ?? '' ),
			'user_id'            => (int) ( $row['user_id'] ?? 0 ),
			'unit_id'            => (int) ( $row['unit_id'] ?? 0 ),
			'unit_code'          => (string) ( $row['unit_code'] ?? '' ),
			'source'             => (string) ( $row['source'] ?? '' ),
			'source_url'         => (string) ( $row['source_url'] ?? '' ),
			'status'             => (string) ( $row['status'] ?? '' ),
			'payload_hash'       => (string) ( $row['payload_hash'] ?? '' ),
			'preview_token_hash' =>
				(string) ( $row['preview_token_hash'] ?? '' ),
			'payload_bytes'      => (int) ( $row['payload_bytes'] ?? 0 ),
			'found_count'        => (int) ( $row['found_count'] ?? 0 ),
			'inserted_count'     => (int) ( $row['inserted_count'] ?? 0 ),
			'updated_count'      => (int) ( $row['updated_count'] ?? 0 ),
			'unchanged_count'    => (int) ( $row['unchanged_count'] ?? 0 ),
			'duplicate_count'    => (int) ( $row['duplicate_count'] ?? 0 ),
			'warning_count'      => (int) ( $row['warning_count'] ?? 0 ),
			'error_count'        => (int) ( $row['error_count'] ?? 0 ),
			'error_code'         => (string) ( $row['error_code'] ?? '' ),
			'error_message'      => isset( $row['error_message'] )
				? (string) $row['error_message']
				: null,
			'started_at_utc'     => (string) ( $row['started_at_utc'] ?? '' ),
			'finished_at_utc'    => isset( $row['finished_at_utc'] )
				? (string) $row['finished_at_utc']
				: null,
			'expires_at_utc'     => isset( $row['expires_at_utc'] )
				? (string) $row['expires_at_utc']
				: null,
			'created_at_utc'     => (string) ( $row['created_at_utc'] ?? '' ),
			'updated_at_utc'     => (string) ( $row['updated_at_utc'] ?? '' ),
		);
	}

	/**
	 * Sanitize diagnostic context.
	 *
	 * Only scalar, non-sensitive values are retained.
	 *
	 * @param array<string, mixed> $context Raw context.
	 *
	 * @return array<string, int|float|string|bool|null>
	 */
	private static function sanitize_context(
		array $context
	): array {
		$sanitized = array();
		$blocked_keys = array(
			'password',
			'token',
			'nonce',
			'raw_payload',
			'authorization',
			'cookie',
		);

		foreach ( $context as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$key = sanitize_key( $key );

			if (
				'' === $key
				|| in_array( $key, $blocked_keys, true )
			) {
				continue;
			}

			if (
				null === $value
				|| is_bool( $value )
				|| is_int( $value )
				|| is_float( $value )
			) {
				$sanitized[ $key ] = $value;
				continue;
			}

			if ( is_string( $value ) ) {
				$sanitized[ $key ] = substr(
					wp_strip_all_tags( $value ),
					0,
					500
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Return empty statistics.
	 *
	 * @return array<string, int>
	 */
	private static function empty_statistics(): array {
		return array(
			'total_runs'      => 0,
			'completed_runs'  => 0,
			'failed_runs'     => 0,
			'found_count'     => 0,
			'inserted_count'  => 0,
			'updated_count'   => 0,
			'unchanged_count' => 0,
			'duplicate_count' => 0,
			'warning_count'   => 0,
			'error_count'     => 0,
		);
	}
}
