<?php
/**
 * Compact map-link summary on the schedule edit screen.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows reusable place links without duplicating the full place editor.
 */
final class Power_Schedule_Manager_Map_Editor {

	/**
	 * Register the read-only meta box.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'add_meta_boxes_' .
				Power_Schedule_Manager_Post_Type::POST_TYPE,
			array( $this, 'register_meta_box' )
		);
	}

	/**
	 * Register the map summary for users who can edit the schedule.
	 *
	 * @param WP_Post $post Current schedule post.
	 * @return void
	 */
	public function register_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		add_meta_box(
			'power-schedule-manager-map',
			__( 'Bản đồ khu vực ngừng điện', 'power-schedule-manager' ),
			array( $this, 'render_meta_box' ),
			Power_Schedule_Manager_Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render linked library places for every event in this post.
	 *
	 * @param WP_Post $post Current schedule post.
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ): void {
		$events = self::get_post_events( $post->ID );

		echo '<div class="psm-map-summary">';
		echo '<div class="psm-map-summary__intro">';
		echo '<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>';
		echo '<div><strong>';
		esc_html_e(
			'Vị trí được quản lý tập trung trong Thư viện bản đồ',
			'power-schedule-manager'
		);
		echo '</strong><p>';
		esc_html_e(
			'Tên đường và bí danh được tự đối chiếu với khu vực của từng khung giờ. Chỉnh địa điểm một lần để các lịch cũ và mới cùng sử dụng.',
			'power-schedule-manager'
		);
		echo '</p></div></div>';

		if ( array() === $events ) {
			echo '<p>';
			esc_html_e(
				'Chưa có sự kiện lịch điện để kiểm tra liên kết bản đồ.',
				'power-schedule-manager'
			);
			echo '</p></div>';

			return;
		}

		echo '<div class="psm-map-summary__events">';

		foreach ( $events as $event ) {
			self::render_event( $event );
		}

		echo '</div>';

		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			(string) ( $events[0]['unit_code'] ?? '' )
		);

		$library_url = add_query_arg(
			array_filter(
				array(
					'page'      =>
						Power_Schedule_Manager_Place_Library::MENU_SLUG,
					'unit_code' => $unit_code,
				)
			),
			admin_url( 'admin.php' )
		);

		echo '<p class="psm-map-summary__actions">';
		echo '<a class="button button-primary" href="';
		echo esc_url( $library_url );
		echo '"><span class="dashicons dashicons-location-alt" aria-hidden="true"></span>';
		esc_html_e( 'Mở thư viện bản đồ', 'power-schedule-manager' );
		echo '</a></p></div>';
	}

	/**
	 * Render one event and its linked reusable places.
	 *
	 * @param array<string,mixed> $event Event row.
	 * @return void
	 */
	private static function render_event( array $event ): void {
		$event_id = absint( $event['id'] ?? 0 );

		if ( $event_id < 1 ) {
			return;
		}

		$places = Power_Schedule_Manager_Place_Library::event_locations(
			$event_id
		);

		echo '<section class="psm-map-summary__event">';
		echo '<div class="psm-map-summary__event-heading"><div>';
		echo '<strong>';
		echo esc_html(
			sprintf(
				/* translators: 1: Start time, 2: End time. */
				__( '%1$s – %2$s', 'power-schedule-manager' ),
				self::format_utc_time(
					(string) ( $event['start_at_utc'] ?? '' )
				),
				self::format_utc_time(
					(string) ( $event['end_at_utc'] ?? '' )
				)
			)
		);
		echo '</strong><span>';
		echo esc_html( (string) ( $event['area'] ?? '' ) );
		echo '</span></div>';

		if ( array() === $places ) {
			echo '<em class="psm-map-summary__state is-unlinked">';
			esc_html_e( 'Chưa có bản đồ', 'power-schedule-manager' );
			echo '</em>';
		} else {
			echo '<em class="psm-map-summary__state is-linked">';
			echo esc_html(
				sprintf(
					/* translators: %d: Linked place count. */
					_n(
						'%d địa điểm',
						'%d địa điểm',
						count( $places ),
						'power-schedule-manager'
					),
					count( $places )
				)
			);
			echo '</em>';
		}

		echo '</div>';

		if ( array() !== $places ) {
			echo '<ul class="psm-map-summary__places">';

			foreach ( $places as $place ) {
				if ( ! is_array( $place ) ) {
					continue;
				}

				$edit_url = add_query_arg(
					array(
						'page'     =>
							Power_Schedule_Manager_Place_Library::EDIT_MENU_SLUG,
						'place_id' => absint( $place['id'] ?? 0 ),
					),
					admin_url( 'admin.php' )
				);

				echo '<li><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>';
				echo '<a href="' . esc_url( $edit_url ) . '">';
				echo esc_html( (string) ( $place['label'] ?? '' ) );
				echo '</a></li>';
			}

			echo '</ul>';
		} else {
			echo '<p class="description">';
			esc_html_e(
				'Tạo địa điểm đúng đơn vị và thêm tên đường hoặc bí danh xuất hiện trong khu vực; plugin sẽ tự liên kết lại.',
				'power-schedule-manager'
			);
			echo '</p>';
		}

		echo '</section>';
	}

	/**
	 * Get current schedule events.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_post_events( int $post_id ): array {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, unit_code, area, start_at_utc, end_at_utc
				FROM {$table}
				WHERE post_id = %d
					AND deleted_at_utc IS NULL
				ORDER BY start_at_utc ASC, id ASC",
				$post_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Format UTC datetime in Vietnam time.
	 *
	 * @param string $value UTC datetime.
	 * @return string
	 */
	private static function format_utc_time( string $value ): string {
		if ( '' === $value ) {
			return '—';
		}

		try {
			return ( new DateTimeImmutable(
				$value,
				new DateTimeZone( 'UTC' )
			) )
				->setTimezone(
					new DateTimeZone(
						POWER_SCHEDULE_MANAGER_TIMEZONE
					)
				)
				->format( 'H:i d/m/Y' );
		} catch ( Exception ) {
			return '—';
		}
	}
}
