<?php
/**
 * Single power schedule template.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/single-schedule.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

get_header();

$post_id = get_queried_object_id();

$unit_code = sanitize_text_field(
	(string) get_post_meta(
		$post_id,
		Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
		true
	)
);

$local_date = sanitize_text_field(
	(string) get_post_meta(
		$post_id,
		Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
		true
	)
);

$last_updated_utc = sanitize_text_field(
	(string) get_post_meta(
		$post_id,
		Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC,
		true
	)
);

$unit = '' !== $unit_code
	? Power_Schedule_Manager_Units::find_by_code( $unit_code )
	: null;

$can_view_internal = current_user_can(
	Power_Schedule_Manager_Capabilities::EDIT_POSTS
);

$unit_is_public = is_array( $unit )
	&& ! empty( $unit['is_public'] );

$valid_date = 1 === preg_match(
	'/\A\d{4}-\d{2}-\d{2}\z/',
	$local_date
);

$events = array();

if (
	$valid_date
	&& ( $unit_is_public || $can_view_internal )
) {
	try {
		$events = Power_Schedule_Manager_Repository::query(
			$local_date,
			$local_date,
			$unit_code,
			array(),
			500,
			0
		);
	} catch ( InvalidArgumentException ) {
		$events = array();
	}
}

/*
 * Determine which events have map data using one grouped query.
 */
if ( array() !== $events ) {
	$event_ids = array();

	foreach ( $events as $event ) {
		if ( is_array( $event ) ) {
			$event_id = absint( $event['id'] ?? 0 );

			if ( $event_id > 0 ) {
				$event_ids[ $event_id ] = $event_id;
			}
		}
	}

	$events_with_map = array();

	if ( array() !== $event_ids ) {
		global $wpdb;

		$locations_table =
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::EVENT_LOCATIONS
			);
		$event_places_table =
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::EVENT_PLACES
			);
		$places_table =
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::PLACES
			);

		$placeholders = implode(
			',',
			array_fill(
				0,
				count( $event_ids ),
				'%d'
			)
		);

		$sql = "SELECT DISTINCT links.event_id
			FROM {$event_places_table} AS links
			INNER JOIN {$places_table} AS places
				ON places.id = links.place_id
			WHERE links.event_id IN ({$placeholders})
				AND places.status = 'active'
				AND (
					NULLIF(TRIM(places.geojson), '') IS NOT NULL
					OR (
						places.center_lat IS NOT NULL
						AND places.center_lng IS NOT NULL
					)
				)
			UNION
			SELECT DISTINCT event_id
			FROM {$locations_table}
			WHERE event_id IN ({$placeholders})
				AND (
					NULLIF(TRIM(geojson), '') IS NOT NULL
					OR (
						center_lat IS NOT NULL
						AND center_lng IS NOT NULL
					)
				)";

		$mapped_ids = $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				array_merge(
					array_values( $event_ids ),
					array_values( $event_ids )
				)
			)
		);

		if ( is_array( $mapped_ids ) ) {
			foreach ( $mapped_ids as $mapped_id ) {
				$mapped_id = absint( $mapped_id );

				if ( $mapped_id > 0 ) {
					$events_with_map[ $mapped_id ] = true;
				}
			}
		}
	}

	foreach ( $events as $index => $event ) {
		if ( ! is_array( $event ) ) {
			continue;
		}

		$event_id = absint( $event['id'] ?? 0 );

		$events[ $index ]['has_map'] = isset(
			$events_with_map[ $event_id ]
		);
	}
}

/**
 * Format the schedule date.
 */
$display_date = '';
$display_title = get_the_title( $post_id );

if ( $valid_date ) {
	$date = DateTimeImmutable::createFromFormat(
		'!Y-m-d',
		$local_date,
		new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		)
	);

	if ( $date instanceof DateTimeImmutable ) {
		$display_date = $date->format( 'd/m/Y' );

		if ( is_array( $unit ) ) {
			$display_title =
				Power_Schedule_Manager_Post_Type::build_schedule_title(
					(string) $unit['name'],
					$date
				);
		}
	}
}

/**
 * Format the last update timestamp.
 */
$last_updated_label = '';

if ( '' !== $last_updated_utc ) {
	try {
		$updated_date = new DateTimeImmutable(
			$last_updated_utc,
			new DateTimeZone( 'UTC' )
		);

		$last_updated_label = $updated_date
			->setTimezone(
				new DateTimeZone(
					POWER_SCHEDULE_MANAGER_TIMEZONE
				)
			)
			->format( 'H:i d/m/Y' );
	} catch ( Exception ) {
		$last_updated_label = '';
	}
}

$archive_url = get_post_type_archive_link(
	Power_Schedule_Manager_Post_Type::POST_TYPE
);

$unit_name = is_array( $unit )
	&& isset( $unit['name'] )
	? sanitize_text_field( (string) $unit['name'] )
	: $unit_code;

$unit_location_name = '' !== $unit_name
	? Power_Schedule_Manager_Post_Type::location_name( $unit_name )
	: '';

$seo_event_count = 0;
$seo_map_count = 0;
$seo_first_start = null;
$seo_last_end = null;
$seo_area_samples = array();
$seo_area_keys = array();
$seo_timezone = new DateTimeZone(
	POWER_SCHEDULE_MANAGER_TIMEZONE
);

foreach ( $events as $seo_event ) {
	if ( ! is_array( $seo_event ) ) {
		continue;
	}

	try {
		$seo_start_utc =
			Power_Schedule_Manager_Validator::parse_utc_datetime(
				$seo_event['start_at_utc'] ?? '',
				'start_at_utc'
			);
		$seo_end_utc =
			Power_Schedule_Manager_Validator::parse_utc_datetime(
				$seo_event['end_at_utc'] ?? '',
				'end_at_utc'
			);
		$seo_status = Power_Schedule_Manager_Status::calculate(
			$seo_start_utc,
			$seo_end_utc,
			(string) ( $seo_event['status'] ?? '' )
		);
	} catch ( InvalidArgumentException ) {
		continue;
	}

	$seo_post_id = absint( $seo_event['post_id'] ?? 0 );

	if (
		! Power_Schedule_Manager_Status::is_publicly_visible( $seo_status )
		|| $seo_post_id < 1
		|| 'publish' !== get_post_status( $seo_post_id )
	) {
		continue;
	}

	$seo_local_start = $seo_start_utc->setTimezone( $seo_timezone );
	$seo_local_end = $seo_end_utc->setTimezone( $seo_timezone );

	if ( $seo_local_start->format( 'Y-m-d' ) !== $local_date ) {
		continue;
	}

	++$seo_event_count;

	if ( ! empty( $seo_event['has_map'] ) ) {
		++$seo_map_count;
	}

	if (
		! $seo_first_start instanceof DateTimeImmutable
		|| $seo_local_start < $seo_first_start
	) {
		$seo_first_start = $seo_local_start;
	}

	if (
		! $seo_last_end instanceof DateTimeImmutable
		|| $seo_local_end > $seo_last_end
	) {
		$seo_last_end = $seo_local_end;
	}

	$seo_area = sanitize_textarea_field(
		(string) ( $seo_event['area'] ?? '' )
	);
	$seo_area = preg_replace(
		'/\s+/u',
		' ',
		trim( $seo_area )
	);
	$seo_area = is_string( $seo_area ) ? $seo_area : '';
	$seo_area_key = '' !== $seo_area
		? hash(
			'sha256',
			strtolower(
				remove_accents( $seo_area )
			)
		)
		: '';

	if (
		'' !== $seo_area_key
		&& ! isset( $seo_area_keys[ $seo_area_key ] )
		&& count( $seo_area_samples ) < 3
	) {
		$seo_area_keys[ $seo_area_key ] = true;
		$seo_area_samples[] = wp_trim_words(
			$seo_area,
			18,
			'…'
		);
	}
}

$today_local_date = ( new DateTimeImmutable(
	'today',
	new DateTimeZone(
		POWER_SCHEDULE_MANAGER_TIMEZONE
	)
) )->format( 'Y-m-d' );

$unit_archive_url = is_string( $archive_url )
	&& '' !== $unit_code
		? add_query_arg(
			array(
				'psm_unit' => $unit_code,
			),
			$archive_url
		)
		: '';

$current_archive_url = is_string( $archive_url )
	&& '' !== $unit_code
	&& $valid_date
		? add_query_arg(
			array(
				'psm_unit' => $unit_code,
				'psm_date' => $local_date,
			),
			$archive_url
		)
		: $unit_archive_url;

/*
 * Find the nearest published schedule of the same electricity unit.
 * Past dates are intentionally excluded from backward navigation.
 */
$find_adjacent_schedule = static function (
	string $direction
) use (
	$unit_code,
	$local_date,
	$today_local_date
): array {
	if (
		'' === $unit_code
		|| 1 !== preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $local_date )
		|| ! in_array( $direction, array( 'previous', 'next' ), true )
	) {
		return array();
	}

	$is_previous = 'previous' === $direction;

	if ( $is_previous && $local_date <= $today_local_date ) {
		return array();
	}

	$meta_query = array(
		'relation'    => 'AND',
		'unit_clause' => array(
			'key'     =>
				Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
			'value'   => $unit_code,
			'compare' => '=',
		),
		'date_clause' => array(
			'key'     =>
				Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
			'value'   => $local_date,
			'compare' => $is_previous ? '<' : '>',
			'type'    => 'DATE',
		),
	);

	if ( $is_previous ) {
		$meta_query['current_clause'] = array(
			'key'     =>
				Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
			'value'   => $today_local_date,
			'compare' => '>=',
			'type'    => 'DATE',
		);
	}

	$query = new WP_Query(
		array(
			'post_type'              =>
				Power_Schedule_Manager_Post_Type::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => $meta_query,
			'orderby'                => array(
				'date_clause' => $is_previous ? 'DESC' : 'ASC',
			),
		)
	);

	$target_post_id = isset( $query->posts[0] )
		? absint( $query->posts[0] )
		: 0;

	if ( $target_post_id < 1 ) {
		return array();
	}

	$target_url = get_permalink( $target_post_id );
	$target_date = sanitize_text_field(
		(string) get_post_meta(
			$target_post_id,
			Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
			true
		)
	);

	if (
		! is_string( $target_url )
		|| '' === $target_url
		|| 1 !== preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $target_date )
	) {
		return array();
	}

	$target_date_object = DateTimeImmutable::createFromFormat(
		'!Y-m-d',
		$target_date,
		new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		)
	);

	return array(
		'url'   => $target_url,
		'label' => $target_date_object instanceof DateTimeImmutable
			? $target_date_object->format( 'd/m/Y' )
			: $target_date,
	);
};

$previous_schedule = $find_adjacent_schedule( 'previous' );
$next_schedule = $find_adjacent_schedule( 'next' );

$terms = get_the_terms(
	$post_id,
	Power_Schedule_Manager_Taxonomy::TAXONOMY
);

$breadcrumb_term     = null;
$breadcrumb_term_url = '';

if (
	is_array( $terms )
	&& ! is_wp_error( $terms )
) {
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}

		$term_url = get_term_link( $term );

		if ( is_wp_error( $term_url ) ) {
			continue;
		}

		$breadcrumb_term     = $term;
		$breadcrumb_term_url = $term_url;
		break;
	}
}

$settings = get_option(
	POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
	array()
);

$single_top_banner_ad = is_array( $settings )
	&& isset( $settings['single_top_banner_ad'] )
	&& is_string( $settings['single_top_banner_ad'] )
		? trim( $settings['single_top_banner_ad'] )
		: '';

$single_bottom_banner_ad = is_array( $settings )
	&& isset( $settings['single_bottom_banner_ad'] )
	&& is_string( $settings['single_bottom_banner_ad'] )
		? trim( $settings['single_bottom_banner_ad'] )
		: '';
$ads_enabled = ! is_array( $settings )
	|| ! array_key_exists( 'ads_enabled', $settings )
	|| ! empty( $settings['ads_enabled'] );
if ( ! $ads_enabled ) {
	$single_top_banner_ad = '';
	$single_bottom_banner_ad = '';
}
?>

<main
	id="primary"
	class="psm-site-main psm-single-schedule"
>
	<div class="psm-container">
		<?php
		echo ( new Power_Schedule_Manager_Shortcodes() ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			->render_page_hero_shortcode(
				array(
					'variant'                 => 'schedule',
					'eyebrow'                 => __( 'Lịch điện theo khu vực và ngày', 'power-schedule-manager' ),
					'title'                   => $display_title,
					'description'             => sprintf(
						/* translators: 1: Unit name, 2: Schedule date. */
						__( 'Xem thời gian, khu vực và lý do ngừng cung cấp điện của %1$s ngày %2$s.', 'power-schedule-manager' ),
						$unit_name,
						$display_date
					),
					'cta_label'               => __( 'Xem chi tiết lịch', 'power-schedule-manager' ),
					'cta_url'                 => '#psm-schedule-details',
					'breadcrumb_parent_label' => __( 'Lịch cúp điện', 'power-schedule-manager' ),
					'breadcrumb_parent_url'   => is_string( $archive_url ) ? $archive_url : '',
					'meta_1_label'            => __( 'Khu vực', 'power-schedule-manager' ),
					'meta_1_value'            => $unit_name,
					'meta_1_detail'           => $breadcrumb_term instanceof WP_Term ? $breadcrumb_term->name : '',
					'meta_2_label'            => __( 'Ngày áp dụng', 'power-schedule-manager' ),
					'meta_2_value'            => $display_date,
					'meta_2_detail'           => '' !== $last_updated_label ? __( 'Dữ liệu đã cập nhật', 'power-schedule-manager' ) : '',
				)
			);
		?>

		<article
			id="post-<?php echo esc_attr( (string) $post_id ); ?>"
			<?php post_class( 'psm-schedule-article', $post_id ); ?>
		>
			<header class="psm-schedule-header">
				<div class="psm-schedule-header__meta">
					<?php if ( '' !== $last_updated_label ) : ?>
						<p class="psm-schedule-updated">
							<?php esc_html_e( 'Dữ liệu gần nhất', 'power-schedule-manager' ); ?>
							<time datetime="<?php echo esc_attr( $last_updated_utc ); ?>">
								<?php echo esc_html( $last_updated_label ); ?>
							</time>
						</p>
					<?php endif; ?>

					<?php if (
						is_array( $terms )
						&& ! is_wp_error( $terms )
						&& array() !== $terms
					) : ?>
						<div class="psm-schedule-areas">
							<span>
								<?php esc_html_e( 'Đơn vị điện lực:', 'power-schedule-manager' ); ?>
							</span>

							<ul>
								<?php foreach ( $terms as $term ) : ?>
									<?php
									$term_link = get_term_link( $term );

									if ( is_wp_error( $term_link ) ) {
										continue;
									}
									?>

									<li>
										<a href="<?php echo esc_url( $term_link ); ?>">
											<?php echo esc_html( $term->name ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>

				<nav
					class="psm-adjacent-nav"
					aria-label="<?php esc_attr_e( 'Điều hướng lịch cúp điện theo ngày', 'power-schedule-manager' ); ?>"
				>
					<?php if ( isset( $previous_schedule['url'] ) ) : ?>
						<a
							class="psm-adjacent-nav__item psm-adjacent-nav__item--previous"
							href="<?php echo esc_url( $previous_schedule['url'] ); ?>"
							rel="prev"
						>
							<span aria-hidden="true">←</span>
							<span>
								<small><?php esc_html_e( 'Lịch trước', 'power-schedule-manager' ); ?></small>
								<strong><?php echo esc_html( $previous_schedule['label'] ); ?></strong>
							</span>
						</a>
					<?php else : ?>
						<span
							class="psm-adjacent-nav__item psm-adjacent-nav__item--disabled"
							aria-disabled="true"
						>
							<span aria-hidden="true">←</span>
							<span>
								<small><?php esc_html_e( 'Lịch trước', 'power-schedule-manager' ); ?></small>
								<strong><?php esc_html_e( 'Không có', 'power-schedule-manager' ); ?></strong>
							</span>
						</span>
					<?php endif; ?>

					<?php if (
						is_string( $current_archive_url )
						&& '' !== $current_archive_url
					) : ?>
						<a
							class="psm-adjacent-nav__item psm-adjacent-nav__item--overview"
							href="<?php echo esc_url( $current_archive_url ); ?>"
						>
							<span>
								<small>
									<?php
									echo esc_html(
										'' !== $display_date
											? sprintf(
												/* translators: %s: Schedule date. */
												__(
													'Lịch ngày %s',
													'power-schedule-manager'
												),
												$display_date
											)
											: __(
												'Lịch cúp điện',
												'power-schedule-manager'
											)
									);
									?>
								</small>
								<strong>
									<?php esc_html_e( 'Xem danh sách', 'power-schedule-manager' ); ?>
								</strong>
							</span>
						</a>
					<?php endif; ?>

					<?php if ( isset( $next_schedule['url'] ) ) : ?>
						<a
							class="psm-adjacent-nav__item psm-adjacent-nav__item--next"
							href="<?php echo esc_url( $next_schedule['url'] ); ?>"
							rel="next"
						>
							<span>
								<small><?php esc_html_e( 'Lịch tiếp', 'power-schedule-manager' ); ?></small>
								<strong><?php echo esc_html( $next_schedule['label'] ); ?></strong>
							</span>
							<span aria-hidden="true">→</span>
						</a>
					<?php else : ?>
						<span
							class="psm-adjacent-nav__item psm-adjacent-nav__item--next psm-adjacent-nav__item--disabled"
							aria-disabled="true"
						>
							<span>
								<small><?php esc_html_e( 'Lịch tiếp', 'power-schedule-manager' ); ?></small>
								<strong><?php esc_html_e( 'Chưa có', 'power-schedule-manager' ); ?></strong>
							</span>
							<span aria-hidden="true">→</span>
						</span>
					<?php endif; ?>
				</nav>
			</header>

			<?php if ( '' !== $single_top_banner_ad ) : ?>
				<aside
					class="psm-ad-banner psm-ad-banner--single-top"
					aria-label="<?php esc_attr_e( 'Quảng cáo', 'power-schedule-manager' ); ?>"
				>
					<span class="psm-ad-banner__label"><?php esc_html_e( 'Quảng cáo', 'power-schedule-manager' ); ?></span>
					<div class="psm-ad-banner__content"><?php echo wp_kses_post( do_shortcode( $single_top_banner_ad ) ); ?></div>
				</aside>
			<?php endif; ?>

			<div id="psm-schedule-details" class="psm-schedule-content">
				<?php
				echo Power_Schedule_Manager_Renderer::schedule(
					$events,
					array(
						'title'           => '',
						'caption'         => sprintf(
							/* translators: 1: Unit name, 2: Schedule date. */
							__(
								'Lịch cúp điện theo kế hoạch của %1$s ngày %2$s',
								'power-schedule-manager'
							),
							$unit_name,
							$display_date
						),
						'show_unit'       => false,
						'show_reason'     => true,
						'show_status'     => true,
						'show_map'        => true,
						'map_mode'        => 'row_buttons',
						'show_details'    => false,
						'show_completed'  => true,
						'require_publish' => true,
						'show_disclaimer' => true,
					)
				);
				?>
			</div>

			<aside class="psm-evn-contact" aria-labelledby="psm-evn-contact-title">
				<div>
					<p><?php esc_html_e( 'Kênh hỗ trợ chính thức', 'power-schedule-manager' ); ?></p>
					<h2 id="psm-evn-contact-title"><?php esc_html_e( 'Cần xác minh lịch hoặc báo sự cố?', 'power-schedule-manager' ); ?></h2>
					<span><?php esc_html_e( 'Website này chỉ tổng hợp lịch dự kiến. EVNSPC là đơn vị tiếp nhận yêu cầu và xác nhận tình trạng cấp điện thực tế.', 'power-schedule-manager' ); ?></span>
				</div>
				<nav aria-label="<?php esc_attr_e( 'Liên hệ EVNSPC', 'power-schedule-manager' ); ?>">
					<a href="tel:19001006">1900 1006</a>
					<a href="tel:19009000">1900 9000</a>
					<a href="https://www.cskh.evnspc.vn/" target="_blank" rel="noopener external"><?php esc_html_e( 'CSKH EVNSPC', 'power-schedule-manager' ); ?></a>
				</nav>
			</aside>

			<section
				class="psm-schedule-summary"
				aria-labelledby="psm-schedule-summary-title"
			>
				<header class="psm-schedule-summary__header">
					<p class="psm-schedule-summary__eyebrow">
						<?php esc_html_e( 'Thông tin giúp bạn chủ động', 'power-schedule-manager' ); ?>
					</p>

					<h2
						id="psm-schedule-summary-title"
						class="psm-section-title"
					>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: Location name, 2: Schedule date. */
								__(
									'Thông tin lịch cúp điện %1$s ngày %2$s',
									'power-schedule-manager'
								),
								'' !== $unit_location_name
									? $unit_location_name
									: $unit_name,
								$display_date
							)
						);
						?>
					</h2>
				</header>

				<div class="psm-schedule-summary__grid">
					<div class="psm-schedule-summary__content">
						<h3>
							<?php esc_html_e( 'Tóm tắt lịch trong ngày', 'power-schedule-manager' ); ?>
						</h3>

						<?php if (
							$seo_event_count > 0
							&& $seo_first_start instanceof DateTimeImmutable
							&& $seo_last_end instanceof DateTimeImmutable
						) : ?>
							<p>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: Unit name, 2: Date, 3: Number of time ranges, 4: Earliest start, 5: Latest end. */
										_n(
											'Trong ngày %2$s, %1$s công bố %3$d khung giờ ngừng hoặc giảm cung cấp điện. Lịch sớm nhất bắt đầu lúc %4$s và thời gian kết thúc muộn nhất dự kiến là %5$s.',
											'Trong ngày %2$s, %1$s công bố %3$d khung giờ ngừng hoặc giảm cung cấp điện. Lịch sớm nhất bắt đầu lúc %4$s và thời gian kết thúc muộn nhất dự kiến là %5$s.',
											$seo_event_count,
											'power-schedule-manager'
										),
										$unit_name,
										$display_date,
										$seo_event_count,
										$seo_first_start->format( 'H:i' ),
										$seo_last_end->format( 'H:i' )
									)
								);
								?>
							</p>
						<?php endif; ?>

						<?php if ( array() !== $seo_area_samples ) : ?>
							<p>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Representative affected areas. */
										__(
											'Một số khu vực được nêu trong thông báo gồm: %s. Bảng phía trên cung cấp đầy đủ từng địa điểm, khung giờ, lý do và trạng thái.',
											'power-schedule-manager'
										),
										implode( '; ', $seo_area_samples )
									)
								);
								?>
							</p>
						<?php endif; ?>

						<?php if ( $seo_map_count > 0 ) : ?>
							<p>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: Number of schedules with map data. */
										_n(
											'%d khung giờ có dữ liệu bản đồ. Chọn “Xem bản đồ” tại dòng tương ứng để đối chiếu tuyến đường hoặc khu vực bị ảnh hưởng.',
											'%d khung giờ có dữ liệu bản đồ. Chọn “Xem bản đồ” tại dòng tương ứng để đối chiếu tuyến đường hoặc khu vực bị ảnh hưởng.',
											$seo_map_count,
											'power-schedule-manager'
										),
										$seo_map_count
									)
								);
								?>
							</p>
						<?php endif; ?>
					</div>

					<aside class="psm-schedule-summary__tips">
						<h3>
							<?php esc_html_e( 'Nên chuẩn bị trước khung giờ dự kiến', 'power-schedule-manager' ); ?>
						</h3>

						<ul>
							<li>
								<?php esc_html_e( 'Sạc điện thoại và các thiết bị cần thiết trước khi lịch bắt đầu.', 'power-schedule-manager' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Lưu công việc đang thực hiện và chủ động phương án điện dự phòng nếu cần.', 'power-schedule-manager' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Kiểm tra lại trạng thái mới nhất vì thời gian thực tế có thể thay đổi.', 'power-schedule-manager' ); ?>
							</li>
						</ul>
					</aside>
				</div>

				<div class="psm-status-guide">
					<h3>
						<?php esc_html_e( 'Cách hiểu trạng thái lịch điện', 'power-schedule-manager' ); ?>
					</h3>

					<div class="psm-status-guide__items">
						<div class="psm-status-guide__item psm-status-guide__item--scheduled">
							<strong><?php esc_html_e( 'Sắp cúp', 'power-schedule-manager' ); ?></strong>
							<span><?php esc_html_e( 'Khung giờ dự kiến chưa bắt đầu.', 'power-schedule-manager' ); ?></span>
						</div>

						<div class="psm-status-guide__item psm-status-guide__item--ongoing">
							<strong><?php esc_html_e( 'Đang cúp', 'power-schedule-manager' ); ?></strong>
							<span><?php esc_html_e( 'Thời điểm hiện tại nằm trong khung giờ đã thông báo.', 'power-schedule-manager' ); ?></span>
						</div>

						<div class="psm-status-guide__item psm-status-guide__item--completed">
							<strong><?php esc_html_e( 'Đã có điện', 'power-schedule-manager' ); ?></strong>
							<span><?php esc_html_e( 'Khung giờ dự kiến đã kết thúc.', 'power-schedule-manager' ); ?></span>
						</div>
					</div>
				</div>
			</section>

			<?php if ( '' !== $single_bottom_banner_ad ) : ?>
				<aside
					class="psm-ad-banner psm-ad-banner--single-bottom"
					aria-label="<?php esc_attr_e( 'Quảng cáo', 'power-schedule-manager' ); ?>"
				>
					<span class="psm-ad-banner__label"><?php esc_html_e( 'Quảng cáo', 'power-schedule-manager' ); ?></span>
					<div class="psm-ad-banner__content"><?php echo wp_kses_post( do_shortcode( $single_bottom_banner_ad ) ); ?></div>
				</aside>
			<?php endif; ?>

			<?php
			$content = get_post_field(
				'post_content',
				$post_id
			);

			if (
				is_string( $content )
				&& '' !== trim( $content )
			) :
				?>
				<div class="psm-schedule-editor-content">
					<?php
					echo wp_kses_post(
						apply_filters(
							'the_content',
							$content
						)
					);
					?>
				</div>
			<?php endif; ?>

		</article>
	</div>
</main>

<?php
get_footer();
