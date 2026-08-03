<?php
/**
 * Frontend schedule renderer.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders accessible schedule tables and mobile cards.
 */
final class Power_Schedule_Manager_Renderer {

	/**
	 * Prepare repository rows for compact public widgets.
	 *
	 * Keeping this transformation in the renderer ensures shortcodes and
	 * full schedule templates use identical status, timezone and URL rules.
	 *
	 * @param array<int,array<string,mixed>> $events Events.
	 * @param string                         $timezone Display timezone.
	 * @return array<int,array<string,mixed>>
	 */
	public static function prepare_public_events(
		array $events,
		string $timezone = POWER_SCHEDULE_MANAGER_TIMEZONE
	): array {
		return self::prepare_events(
			$events,
			$timezone,
			false,
			true
		);
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Render a schedule event collection.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 * @param array<string, mixed>             $arguments Display arguments.
	 *
	 * @return string
	 */
	public static function schedule(
		array $events,
		array $arguments = array()
	): string {
		$arguments = wp_parse_args(
			$arguments,
			array(
				'title'           => '',
				'caption'         => __(
					'Danh sách lịch ngừng, giảm cung cấp điện',
					'power-schedule-manager'
				),
				'timezone'        =>
					POWER_SCHEDULE_MANAGER_TIMEZONE,
				'show_unit'       => true,
				'show_reason'     => true,
				'show_status'     => true,
				'show_map'        => true,
				'map_mode'        => 'row_buttons',
				'show_details'    => true,
				'show_completed'  => false,
				'require_publish' => true,
				'show_disclaimer' => true,
				'group_page'      => 1,
				'groups_per_page' => 0,
				'pre_paginated'   => false,
				'total_groups'    => null,
				'empty_message'   => __(
					'Hiện chưa có lịch ngừng, giảm cung cấp điện phù hợp.',
					'power-schedule-manager'
				),
			)
		);

		$timezone = self::sanitize_timezone(
			$arguments['timezone']
		);

		$events = self::prepare_events(
			$events,
			$timezone,
			(bool) $arguments['show_completed'],
			(bool) $arguments['require_publish']
		);

		if ( array() === $events ) {
			return self::empty_state(
				(string) $arguments['empty_message'],
				(bool) $arguments['show_disclaimer']
			);
		}

		$pre_paginated = (bool) $arguments['pre_paginated'];
		$total_groups = $pre_paginated
			&& null !== $arguments['total_groups']
				? max( 0, absint( $arguments['total_groups'] ) )
				: self::count_groups( $events );
		$group_page   = max(
			1,
			absint( $arguments['group_page'] )
		);
		$groups_per_page = max(
			0,
			absint( $arguments['groups_per_page'] )
		);

		ob_start();
		?>
		<section class="psm-schedule" aria-label="<?php echo esc_attr( (string) $arguments['caption'] ); ?>">
			<?php if ( '' !== trim( (string) $arguments['title'] ) ) : ?>
				<h2 class="psm-schedule__title">
					<?php echo esc_html( (string) $arguments['title'] ); ?>
				</h2>
			<?php endif; ?>

			<div class="psm-schedule__desktop">
				<?php
				echo self::desktop_table(
					$events,
					$arguments
				);
				?>
			</div>

			<div class="psm-schedule__mobile">
				<?php
				echo self::mobile_cards(
					$events,
					$arguments
				);
				?>
			</div>

			<?php if ( (bool) $arguments['show_map'] ) : ?>
				<?php
				echo Power_Schedule_Manager_Template_Loader::render_part(
					'map'
				);
				?>
			<?php endif; ?>

			<?php if ( (bool) $arguments['show_disclaimer'] ) : ?>
				<?php echo self::disclaimer(); ?>
			<?php endif; ?>

			<?php
			echo self::pagination(
				$total_groups,
				$groups_per_page,
				$group_page
			);
			?>
		</section>
		<?php

		$output = ob_get_clean();

		return is_string( $output ) ? $output : '';
	}

	/**
	 * Render the desktop schedule table through an overrideable template.
	 *
	 * @param array<int, array<string, mixed>> $events    Prepared events.
	 * @param array<string, mixed>             $arguments Display arguments.
	 *
	 * @return string
	 */
	private static function desktop_table(
		array $events,
		array $arguments
	): string {
		return Power_Schedule_Manager_Template_Loader::render_part(
			'schedule-table',
			array(
				'events'      => $events,
				'caption'     => (string) $arguments['caption'],
				'show_unit'   => (bool) $arguments['show_unit'],
				'show_reason' => (bool) $arguments['show_reason'],
				'show_status' => (bool) $arguments['show_status'],
				'show_map'    => (bool) $arguments['show_map'],
				'map_mode'    => self::sanitize_map_mode(
					(string) $arguments['map_mode']
				),
					'show_details' => (bool)
						$arguments['show_details'],
					'group_page'   => max(
						1,
						absint( $arguments['group_page'] )
					),
					'groups_per_page' => max(
						0,
						absint( $arguments['groups_per_page'] )
					),
					'pre_paginated' => (bool)
						$arguments['pre_paginated'],
				)
			);
	}

	/**
	 * Render mobile schedule cards through an overrideable template.
	 *
	 * @param array<int, array<string, mixed>> $events    Prepared events.
	 * @param array<string, mixed>             $arguments Display arguments.
	 *
	 * @return string
	 */
	private static function mobile_cards(
		array $events,
		array $arguments
	): string {
		return Power_Schedule_Manager_Template_Loader::render_part(
			'schedule-cards',
			array(
				'events'      => $events,
				'show_unit'   => (bool) $arguments['show_unit'],
				'show_reason' => (bool) $arguments['show_reason'],
				'show_status' => (bool) $arguments['show_status'],
				'show_map'    => (bool) $arguments['show_map'],
				'map_mode'    => self::sanitize_map_mode(
					(string) $arguments['map_mode']
				),
					'show_details' => (bool)
						$arguments['show_details'],
					'group_page'   => max(
						1,
						absint( $arguments['group_page'] )
					),
					'groups_per_page' => max(
						0,
						absint( $arguments['groups_per_page'] )
					),
					'pre_paginated' => (bool)
						$arguments['pre_paginated'],
				)
			);
	}

	/**
	 * Count date/unit groups in prepared events.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 *
	 * @return int
	 */
	private static function count_groups( array $events ): int {
		$groups = array();

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$key = (string) ( $event['local_date'] ?? '' )
				. '|'
				. (string) ( $event['unit_code'] ?? '' );

			if ( '|' !== $key ) {
				$groups[ $key ] = true;
			}
		}

		return count( $groups );
	}

	/**
	 * Render public group pagination.
	 *
	 * @param int $total_groups Total groups.
	 * @param int $per_page Groups per page.
	 * @param int $current_page Current page.
	 *
	 * @return string
	 */
	private static function pagination(
		int $total_groups,
		int $per_page,
		int $current_page
	): string {
		if ( $per_page < 1 || $total_groups <= $per_page ) {
			return '';
		}

		$total_pages  = (int) ceil( $total_groups / $per_page );
		$current_page = min(
			$total_pages,
			max( 1, $current_page )
		);
		$placeholder  = 999999999;
		$base_url     = str_replace(
			(string) $placeholder,
			'%#%',
			esc_url(
				add_query_arg(
					'psm_page',
					$placeholder
				)
			)
		);

		$links = paginate_links(
			array(
				'base'      => $base_url,
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'type'      => 'array',
				'prev_text' => __(
					'← Trước',
					'power-schedule-manager'
				),
				'next_text' => __(
					'Sau →',
					'power-schedule-manager'
				),
			)
		);

		if ( ! is_array( $links ) || array() === $links ) {
			return '';
		}

		ob_start();
		?>
		<nav
			class="psm-pagination"
			aria-label="<?php esc_attr_e( 'Phân trang lịch cúp điện', 'power-schedule-manager' ); ?>"
		>
			<?php foreach ( $links as $link ) : ?>
				<?php echo wp_kses_post( $link ); ?>
			<?php endforeach; ?>
		</nav>
		<?php

		$output = ob_get_clean();

		return is_string( $output ) ? $output : '';
	}

	/**
	 * Sanitize frontend map display mode.
	 *
	 * @param string $mode Raw mode.
	 * @return string
	 */
	private static function sanitize_map_mode( string $mode ): string {
		return in_array(
			$mode,
			array( 'row_buttons', 'modal_only', 'none' ),
			true
		)
			? $mode
			: 'row_buttons';
	}

	/**
	 * Prepare events for safe display.
	 *
	 * @param array<int, array<string, mixed>> $events   Events.
	 * @param string                           $timezone Timezone.
	 * @param bool                             $show_completed Include ended events.
	 * @param bool                             $require_publish Require a published linked post.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function prepare_events(
		array $events,
		string $timezone,
		bool $show_completed,
		bool $require_publish
	): array {
		$prepared = array();

		if ( $require_publish ) {
			$post_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							static fn ( mixed $event ): int =>
								is_array( $event )
									? absint( $event['post_id'] ?? 0 )
									: 0,
							$events
						)
					)
				)
			);

			if (
				array() !== $post_ids
				&& function_exists( '_prime_post_caches' )
			) {
				_prime_post_caches( $post_ids, false, false );
			}
		}

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			try {
				$start = Power_Schedule_Manager_Validator::parse_utc_datetime(
					$event['start_at_utc'] ?? '',
					'start_at_utc'
				);

				$end = Power_Schedule_Manager_Validator::parse_utc_datetime(
					$event['end_at_utc'] ?? '',
					'end_at_utc'
				);

				$status =
					Power_Schedule_Manager_Status::calculate(
						$start,
						$end,
						(string) ( $event['status'] ?? '' )
					);
			} catch ( InvalidArgumentException ) {
				continue;
			}

			if (
				! Power_Schedule_Manager_Status::is_publicly_visible(
					$status
				)
				|| (
					! $show_completed
					&& Power_Schedule_Manager_Status::COMPLETED
					=== $status
				)
			) {
				continue;
			}

			$post_id = absint( $event['post_id'] ?? 0 );

			if (
				$require_publish
				&& (
					$post_id < 1
					|| 'publish' !== get_post_status( $post_id )
				)
			) {
				continue;
			}

			$zone        = new DateTimeZone( $timezone );
			$local_start = $start->setTimezone( $zone );
			$local_end   = $end->setTimezone( $zone );

			$unit_name = '';

			if (
				isset( $event['unit_name'] )
				&& is_string( $event['unit_name'] )
			) {
				$unit_name = $event['unit_name'];
			} elseif (
				isset( $event['unit_code'] )
				&& is_string( $event['unit_code'] )
			) {
				$unit = Power_Schedule_Manager_Units::find_by_code(
					$event['unit_code']
				);

				if ( null !== $unit ) {
					$unit_name = (string) $unit['name'];
				}
			}

			$url     = '';

			if (
				$post_id > 0
				&& 'publish' === get_post_status( $post_id )
			) {
				$permalink = get_permalink( $post_id );

				if ( is_string( $permalink ) ) {
					$url = $permalink;
				}
			}

			$has_map = isset( $event['has_map'] )
				&& true === (bool) $event['has_map'];
			$updated_iso = '';
			$updated_display = '';

			try {
				$updated = Power_Schedule_Manager_Validator::parse_utc_datetime(
					$event['updated_at_utc'] ?? '',
					'updated_at_utc'
				)->setTimezone( $zone );
				$updated_iso = $updated->format( DATE_ATOM );
				$updated_display = $updated->format( 'H:i d/m/Y' );
			} catch ( InvalidArgumentException ) {
				// An invalid optional update time must not hide a valid event.
			}

			$prepared[] = array(
				'id'                 => absint( $event['id'] ?? 0 ),
				'unit_code'          =>
					(string) ( $event['unit_code'] ?? '' ),
				'unit_name'          => $unit_name,
				'local_date'         => $local_start->format( 'Y-m-d' ),
				'display_date'       => $local_start->format( 'd/m/Y' ),
				'start_time'         => $local_start->format( 'H:i' ),
				'end_time'           => $local_end->format( 'H:i' ),
				'end_date'           => $local_end->format( 'd/m/Y' ),
				'start_iso'          => $local_start->format( DATE_ATOM ),
				'end_iso'            => $local_end->format( DATE_ATOM ),
				'crosses_date'       =>
					$local_start->format( 'Y-m-d' )
					!== $local_end->format( 'Y-m-d' ),
				'display_time_range' =>
					self::display_time_range(
						$local_start,
						$local_end
					),
				'area'               =>
					(string) ( $event['area'] ?? '' ),
				'reason'             =>
					(string) ( $event['reason'] ?? '' ),
				'status'             => $status,
				'status_label'       =>
					Power_Schedule_Manager_Status::label(
						$status
					),
				'status_description' =>
					Power_Schedule_Manager_Status::description(
						$status
					),
				'row_class'          =>
					Power_Schedule_Manager_Status::css_class(
						$status
					),
				'post_url'           => $url,
				'has_map'            => $has_map,
				'map_url'            => rest_url(
					sprintf(
						'power-schedule-manager/v1/events/%d/locations',
						absint( $event['id'] ?? 0 )
					)
				),
				'updated_iso'        => $updated_iso,
				'updated_display'    => $updated_display,
			);
		}

		return $prepared;
	}

	/**
	 * Render empty state.
	 *
	 * @param string $message         Empty message.
	 * @param bool   $show_disclaimer Show disclaimer.
	 *
	 * @return string
	 */
	public static function empty_state(
		string $message,
		bool $show_disclaimer = true
	): string {
		$message = sanitize_text_field( $message );

		if ( '' === $message ) {
			$message = __(
				'Hiện chưa có lịch ngừng, giảm cung cấp điện phù hợp.',
				'power-schedule-manager'
			);
		}

		return Power_Schedule_Manager_Template_Loader::render_part(
			'empty-state',
			array(
				'message'         => $message,
				'show_disclaimer' => $show_disclaimer,
			)
		);
	}

	/**
	 * Render a consistent updating state for external or scheduled data.
	 *
	 * @param string $message Optional explanatory message.
	 *
	 * @return string
	 */
	public static function updating_state( string $message = '' ): string {
		$message = sanitize_text_field( $message );

		if ( '' === $message ) {
			$message = __(
				'Dữ liệu mới sẽ hiển thị ngay sau khi được cập nhật.',
				'power-schedule-manager'
			);
		}

		return sprintf(
			'<div class="psm-data-state psm-data-state--updating" role="status" aria-live="polite"><span class="psm-data-state__indicator" aria-hidden="true"></span><div class="psm-data-state__content"><strong>%1$s</strong><p>%2$s</p></div></div>',
			esc_html__(
				'Đang cập nhật',
				'power-schedule-manager'
			),
			esc_html( $message )
		);
	}

	/**
	 * Render public disclaimer.
	 *
	 * @return string
	 */
	public static function disclaimer(): string {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$message = is_array( $settings )
			&& isset( $settings['disclaimer_text'] )
			&& is_string( $settings['disclaimer_text'] )
			&& '' !== trim( $settings['disclaimer_text'] )
				? $settings['disclaimer_text']
				: '';
		if ( '' !== $message ) {
			return sprintf(
				'<aside class="psm-disclaimer" role="note"><p>%s</p></aside>',
				esc_html( $message )
			);
		}

		return '<aside class="psm-disclaimer" role="note">'
			. '<strong>' . esc_html__( 'Lưu ý về nguồn thông tin', 'power-schedule-manager' ) . '</strong>'
			. '<p>' . esc_html__( 'Cúp Điện Lâm Đồng là nền tảng tổng hợp và hiển thị thông tin từ các nguồn dữ liệu chính thức của EVN và đơn vị điện lực, không phải website hoặc ứng dụng chính thức của EVN/EVNSPC. Lịch ngừng, giảm cung cấp điện có thể được cập nhật hoặc điều chỉnh theo thông báo mới nhất của đơn vị điện lực. Thời gian và trạng thái trên website phản ánh kế hoạch dự kiến, không xác nhận tình trạng cấp điện thực tế.', 'power-schedule-manager' ) . '</p>'
			. '<p>' . esc_html__( 'Khi cần hỗ trợ, phản ánh sự cố hoặc xác minh thông tin, vui lòng liên hệ trực tiếp Trung tâm Chăm sóc khách hàng EVNSPC:', 'power-schedule-manager' )
			. ' <a href="tel:19001006">1900 1006</a>, <a href="tel:19009000">1900 9000</a>, '
			. '<a href="https://www.cskh.evnspc.vn/" target="_blank" rel="noopener external">'
			. esc_html__( 'website CSKH EVNSPC', 'power-schedule-manager' ) . '</a> '
			. esc_html__( 'hoặc ứng dụng CSKH EVNSPC.', 'power-schedule-manager' ) . '</p></aside>';
	}

	/**
	 * Format local time range.
	 *
	 * @param DateTimeImmutable $start Start.
	 * @param DateTimeImmutable $end   End.
	 *
	 * @return string
	 */
	private static function display_time_range(
		DateTimeImmutable $start,
		DateTimeImmutable $end
	): string {
		if (
			$start->format( 'Y-m-d' )
			=== $end->format( 'Y-m-d' )
		) {
			return sprintf(
				'%s – %s',
				$start->format( 'H:i' ),
				$end->format( 'H:i' )
			);
		}

		return sprintf(
			'%1$s %2$s – %3$s %4$s',
			$start->format( 'H:i' ),
			$start->format( 'd/m/Y' ),
			$end->format( 'H:i' ),
			$end->format( 'd/m/Y' )
		);
	}

	/**
	 * Sanitize display timezone.
	 *
	 * @param mixed $timezone Raw timezone.
	 *
	 * @return string
	 */
	private static function sanitize_timezone(
		mixed $timezone
	): string {
		if (
			! is_string( $timezone )
			|| ! in_array(
				$timezone,
				timezone_identifiers_list(),
				true
			)
		) {
			return POWER_SCHEDULE_MANAGER_TIMEZONE;
		}

		return $timezone;
	}
}
