<?php
/**
 * Bounded database benchmark.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Measures representative read queries without mutating production data.
 */
final class Power_Schedule_Manager_Benchmark {

	private const int SLOW_MS = 100;

	private function __construct() {
	}

	/**
	 * Run small indexed samples.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function run(): array {
		global $wpdb;

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$queue  = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$sample_unit = (string) $wpdb->get_var(
			"SELECT code FROM {$units}
				WHERE is_public=1
				ORDER BY sort_order ASC,id ASC
				LIMIT 1"
		);

		$queries = array(
			'upcoming_events' => array(
				'label' => __( 'Lịch sắp tới theo trạng thái/thời gian', 'power-schedule-manager' ),
				'sql'   => $wpdb->prepare(
					"SELECT id FROM {$events}
					WHERE status=%s AND start_at_utc >= %s
					ORDER BY start_at_utc ASC LIMIT 100",
					Power_Schedule_Manager_Status::SCHEDULED,
					Power_Schedule_Manager_Database::utc_now()
				),
			),
			'active_places' => array(
				'label' => __( 'Thư viện địa điểm theo đơn vị', 'power-schedule-manager' ),
				'sql'   => $wpdb->prepare(
					"SELECT id FROM {$places}
					WHERE unit_code=%s AND status='active'
					ORDER BY id ASC LIMIT 100",
					$sample_unit
				),
			),
			'notification_queue' => array(
				'label' => __( 'Hàng đợi thông báo', 'power-schedule-manager' ),
				'sql'   => "SELECT id FROM {$queue}
					WHERE status IN ('pending','retry')
					ORDER BY available_at_utc ASC,id ASC LIMIT 10",
			),
		);

		$result = array();
		foreach ( $queries as $key => $query ) {
			$started = hrtime( true );
			$rows    = $wpdb->get_col( $query['sql'] );
			$ms      = round( ( hrtime( true ) - $started ) / 1000000, 2 );
			$result[] = array(
				'key'    => $key,
				'label'  => $query['label'],
				'ms'     => $ms,
				'rows'   => is_array( $rows ) ? count( $rows ) : 0,
				'status' => $ms >= self::SLOW_MS ? 'warning' : 'good',
			);
		}

		return $result;
	}
}
