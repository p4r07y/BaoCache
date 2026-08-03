<?php
/**
 * Cached administration dashboard statistics.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds lightweight operational statistics for the administration dashboard.
 */
final class Power_Schedule_Manager_Dashboard_Stats {

	/**
	 * Cache namespace.
	 */
	private const string CACHE_NAMESPACE = 'dashboard_stats';

	/**
	 * Cache key version.
	 */
	private const int CACHE_VERSION = 5;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Return the cached dashboard snapshot.
	 *
	 * @return array{
	 *     today: int,
	 *     ongoing: int,
	 *     upcoming_seven_days: int,
	 *     pending_publication: int,
	 *     generated_at_utc: string
	 * }
	 */
	public static function snapshot(): array {
		$snapshot = Power_Schedule_Manager_Cache::remember(
			self::CACHE_NAMESPACE,
			array(
				'version' => self::CACHE_VERSION,
			),
			array( self::class, 'calculate' ),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);

		return is_array( $snapshot )
			? self::normalize_snapshot( $snapshot )
			: self::empty_snapshot();
	}

	/**
	 * Invalidate dashboard statistics after a schedule post status changes.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 *
	 * @return void
	 */
	public static function invalidate_on_post_transition(
		string $new_status,
		string $old_status,
		WP_Post $post
	): void {
		if (
			$new_status === $old_status
			|| Power_Schedule_Manager_Post_Type::POST_TYPE
				!== $post->post_type
		) {
			return;
		}

		Power_Schedule_Manager_Cache::delete(
			self::CACHE_NAMESPACE,
			array(
				'version' => self::CACHE_VERSION,
			)
		);
	}

	/**
	 * Calculate the current operational snapshot.
	 *
	 * @return array{
	 *     today: int,
	 *     ongoing: int,
	 *     upcoming_seven_days: int,
	 *     pending_publication: int,
	 *     generated_at_utc: string
	 * }
	 */
	public static function calculate(): array {
		global $wpdb;

		$timezone = new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		);
		$utc      = new DateTimeZone( 'UTC' );
		$now      = new DateTimeImmutable( 'now', $utc );
		$today    = new DateTimeImmutable( 'today', $timezone );

		$day_start_utc = $today
			->setTimezone( $utc )
			->format( 'Y-m-d H:i:s' );

		$range_end_utc = $today
			->modify( '+7 days' )
			->setTimezone( $utc )
			->format( 'Y-m-d H:i:s' );

		$now_utc    = $now->format( 'Y-m-d H:i:s' );
		$local_date = $today->format( 'Y-m-d' );
		$table      = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$sql = $wpdb->prepare(
			"SELECT
				COALESCE(
					SUM(
						CASE
							WHEN local_date = %s
								AND status NOT IN (%s, %s)
							THEN 1
							ELSE 0
						END
					),
					0
				) AS today_count,
				COALESCE(
					SUM(
						CASE
							WHEN start_at_utc <= %s
								AND end_at_utc > %s
								AND status NOT IN (%s, %s)
							THEN 1
							ELSE 0
						END
					),
					0
				) AS ongoing_count,
				COALESCE(
					SUM(
						CASE
							WHEN start_at_utc >= %s
								AND start_at_utc < %s
								AND status NOT IN (%s, %s)
							THEN 1
							ELSE 0
						END
					),
					0
				) AS upcoming_count
			FROM {$table}
			WHERE deleted_at_utc IS NULL
				AND end_at_utc > %s
				AND start_at_utc < %s",
			$local_date,
			Power_Schedule_Manager_Status::CANCELLED,
			Power_Schedule_Manager_Status::REMOVED,
			$now_utc,
			$now_utc,
			Power_Schedule_Manager_Status::CANCELLED,
			Power_Schedule_Manager_Status::REMOVED,
			$now_utc,
			$range_end_utc,
			Power_Schedule_Manager_Status::CANCELLED,
			Power_Schedule_Manager_Status::REMOVED,
			$day_start_utc,
			$range_end_utc
		);

		$row = $wpdb->get_row(
			$sql,
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			$row = array();
		}

		$post_counts = wp_count_posts(
			Power_Schedule_Manager_Post_Type::POST_TYPE
		);

		$pending_publication = isset( $post_counts->draft )
			? absint( $post_counts->draft )
			: 0;

		$units_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$recent_events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					events.id,
					events.post_id,
					events.unit_code,
					units.name AS unit_name,
					events.local_date,
					events.start_at_utc,
					events.end_at_utc,
					events.area,
					events.status,
					posts.post_status
				FROM {$table} AS events
				LEFT JOIN {$units_table} AS units
					ON units.code = events.unit_code
				LEFT JOIN {$wpdb->posts} AS posts
					ON posts.ID = events.post_id
				WHERE events.deleted_at_utc IS NULL
					AND events.end_at_utc > %s
					AND events.status NOT IN (%s, %s)
				ORDER BY events.start_at_utc ASC, events.id ASC
				LIMIT 8",
				$now_utc,
				Power_Schedule_Manager_Status::CANCELLED,
				Power_Schedule_Manager_Status::REMOVED
			),
			ARRAY_A
		);

		$imports_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);

		$recent_imports = $wpdb->get_results(
			"SELECT
				id,
				user_id,
				unit_code,
				status,
				found_count,
				inserted_count,
				updated_count,
				unchanged_count,
				error_count,
				started_at_utc,
				finished_at_utc
			FROM {$imports_table}
			WHERE status IN ('completed', 'failed')
			ORDER BY started_at_utc DESC, id DESC
			LIMIT 4",
			ARRAY_A
		);

		return self::normalize_snapshot(
			array(
				'today' =>
					$row['today_count'] ?? 0,
				'ongoing' =>
					$row['ongoing_count'] ?? 0,
				'upcoming_seven_days' =>
					$row['upcoming_count'] ?? 0,
				'pending_publication' =>
					$pending_publication,
				'recent_events' =>
					is_array( $recent_events )
						? $recent_events
						: array(),
				'recent_imports' =>
					is_array( $recent_imports )
						? $recent_imports
						: array(),
				'generated_at_utc' =>
					Power_Schedule_Manager_Database::utc_now(),
			)
		);
	}

	/**
	 * Normalize a dashboard snapshot.
	 *
	 * @param array<string, mixed> $snapshot Raw snapshot.
	 *
	 * @return array{
	 *     today: int,
	 *     ongoing: int,
	 *     upcoming_seven_days: int,
	 *     pending_publication: int,
	 *     generated_at_utc: string
	 * }
	 */
	private static function normalize_snapshot(
		array $snapshot
	): array {
		return array(
			'today' =>
				absint( $snapshot['today'] ?? 0 ),
			'ongoing' =>
				absint( $snapshot['ongoing'] ?? 0 ),
			'upcoming_seven_days' =>
				absint(
					$snapshot['upcoming_seven_days'] ?? 0
				),
			'pending_publication' =>
				absint(
					$snapshot['pending_publication'] ?? 0
				),
			'recent_events' =>
				self::normalize_rows(
					$snapshot['recent_events'] ?? array()
				),
			'recent_imports' =>
				self::normalize_rows(
					$snapshot['recent_imports'] ?? array()
				),
			'generated_at_utc' =>
				is_string(
					$snapshot['generated_at_utc'] ?? null
				)
					? $snapshot['generated_at_utc']
					: '',
		);
	}

	/**
	 * Return an empty dashboard snapshot.
	 *
	 * @return array{
	 *     today: int,
	 *     ongoing: int,
	 *     upcoming_seven_days: int,
	 *     pending_publication: int,
	 *     generated_at_utc: string
	 * }
	 */
	private static function empty_snapshot(): array {
		return array(
			'today'                  => 0,
			'ongoing'                => 0,
			'upcoming_seven_days'    => 0,
			'pending_publication'    => 0,
			'recent_events'         => array(),
			'recent_imports'        => array(),
			'generated_at_utc'       => '',
		);
	}

	/**
	 * Keep only scalar values in cached dashboard rows.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array<int, array<string, scalar|null>>
	 */
	private static function normalize_rows( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$normalized[] = array_filter(
				$row,
				static fn ( mixed $value ): bool =>
					is_scalar( $value ) || null === $value
			);
		}

		return $normalized;
	}
}
