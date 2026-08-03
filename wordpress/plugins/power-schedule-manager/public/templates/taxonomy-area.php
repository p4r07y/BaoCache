<?php
/**
 * Power area taxonomy archive.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/taxonomy-area.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$term = get_queried_object();

if (
	! $term instanceof WP_Term
	|| Power_Schedule_Manager_Taxonomy::TAXONOMY
		!== $term->taxonomy
) {
	global $wp_query;

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();

	$template_404 = get_404_template();

	if ( is_string( $template_404 ) && '' !== $template_404 ) {
		include $template_404;
	}

	return;
}

$unit_code = sanitize_text_field(
	(string) get_term_meta(
		$term->term_id,
		Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
		true
	)
);

$is_public = (bool) get_term_meta(
	$term->term_id,
	Power_Schedule_Manager_Taxonomy::META_IS_PUBLIC,
	true
);

$can_view_internal = current_user_can(
	Power_Schedule_Manager_Capabilities::EDIT_POSTS
);

/*
 * Prevent public access to internal electricity units.
 */
if ( ! $is_public && ! $can_view_internal ) {
	global $wp_query;

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();

	$template_404 = get_404_template();

	if ( is_string( $template_404 ) && '' !== $template_404 ) {
		include $template_404;
	}

	return;
}

$unit = '' !== $unit_code
	? Power_Schedule_Manager_Units::find_by_code( $unit_code )
	: null;

$unit_name = is_array( $unit )
	&& isset( $unit['name'] )
	? sanitize_text_field( (string) $unit['name'] )
	: $term->name;

$timezone_name = is_array( $unit )
	&& ! empty( $unit['timezone'] )
	? (string) $unit['timezone']
	: POWER_SCHEDULE_MANAGER_TIMEZONE;

try {
	$timezone = new DateTimeZone( $timezone_name );
} catch ( Exception ) {
	$timezone = new DateTimeZone(
		POWER_SCHEDULE_MANAGER_TIMEZONE
	);
}

$today = new DateTimeImmutable(
	'today',
	$timezone
);

$selected_date = $today->format( 'Y-m-d' );

if ( isset( $_GET['psm_date'] ) ) {
	$requested_date = sanitize_text_field(
		wp_unslash(
			(string) $_GET['psm_date']
		)
	);

	try {
		$selected_date =
			Power_Schedule_Manager_Validator::validate_local_date(
				$requested_date
			);
	} catch ( InvalidArgumentException ) {
		$selected_date = $today->format( 'Y-m-d' );
	}
}

$display_date = $selected_date;

$parsed_date = DateTimeImmutable::createFromFormat(
	'!Y-m-d',
	$selected_date,
	$timezone
);

if ( $parsed_date instanceof DateTimeImmutable ) {
	$display_date = $parsed_date->format( 'd/m/Y' );
}

$term_url = get_term_link( $term );

if ( is_wp_error( $term_url ) ) {
	$term_url = '';
}

$archive_url = get_post_type_archive_link(
	Power_Schedule_Manager_Post_Type::POST_TYPE
);

$description = term_description( $term );

$shortcodes = new Power_Schedule_Manager_Shortcodes();

$schedule_output = '';

if ( '' !== $unit_code ) {
	$schedule_output =
		$shortcodes->render_schedule_shortcode(
			array(
				'date'            => $selected_date,
				'unit'            => $unit_code,
				'days'            => '7',
				'title'           => '',
				'show_unit'       => 'no',
				'show_reason'     => 'yes',
				'show_status'     => 'yes',
				'show_map'        => 'no',
				'map_mode'        => 'none',
				'show_disclaimer' => 'yes',
				'use_query'       => 'no',
			)
		);
}

get_header();
?>

<main
	id="primary"
	class="psm-site-main psm-taxonomy-area"
>
	<div class="psm-container">
		<?php
		$area_title = sprintf(
			/* translators: %s: Electricity unit name. */
			__( 'Lịch cúp điện %s', 'power-schedule-manager' ),
			$unit_name
		);
		$area_description = sprintf(
			/* translators: %s: Electricity unit name. */
			__( 'Tra cứu thời gian, địa điểm và lý do ngừng cung cấp điện thuộc phạm vi %s.', 'power-schedule-manager' ),
			$unit_name
		);
		echo $shortcodes->render_page_hero_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'variant'                 => 'schedule',
				'eyebrow'                 => __( 'Khu vực điện lực', 'power-schedule-manager' ),
				'title'                   => $area_title,
				'description'             => $area_description,
				'cta_label'               => __( 'Chọn ngày tra cứu', 'power-schedule-manager' ),
				'cta_url'                 => '#psm-area-filter',
				'breadcrumb_parent_label' => __( 'Lịch cúp điện', 'power-schedule-manager' ),
				'breadcrumb_parent_url'   => is_string( $archive_url ) ? $archive_url : '',
				'meta_1_label'            => __( 'Khu vực', 'power-schedule-manager' ),
				'meta_1_value'            => $unit_name,
				'meta_1_detail'           => '' !== $unit_code ? $unit_code : __( 'Đơn vị công khai', 'power-schedule-manager' ),
			)
		);
		?>

		<?php if ( '' !== trim( $description ) || ! $is_public ) : ?>
			<div class="psm-area-introduction">
				<?php echo wp_kses_post( $description ); ?>
				<?php if ( ! $is_public ) : ?>
					<p class="psm-internal-notice"><?php esc_html_e( 'Đây là đơn vị nội bộ và chỉ người có quyền quản trị mới xem được trang này.', 'power-schedule-manager' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<section
			id="psm-area-filter"
			class="psm-area-filter"
			aria-labelledby="psm-area-filter-title"
		>
			<h2
				id="psm-area-filter-title"
				class="psm-section-title"
			>
				<?php
				esc_html_e(
					'Chọn ngày bắt đầu',
					'power-schedule-manager'
				);
				?>
			</h2>

			<?php if ( '' !== $term_url ) : ?>
				<form
					method="get"
					action="<?php echo esc_url( $term_url ); ?>"
					class="psm-search psm-area-date-filter"
				>
					<div class="psm-search__field">
						<label for="psm-area-date">
							<?php
							esc_html_e(
								'Ngày tra cứu',
								'power-schedule-manager'
							);
							?>
						</label>

						<input
							type="date"
							id="psm-area-date"
							name="psm_date"
							value="<?php echo esc_attr( $selected_date ); ?>"
							required
						>
					</div>

					<div class="psm-search__actions">
						<button
							type="submit"
							class="psm-button psm-button--search"
						>
							<?php
							esc_html_e(
								'Tra cứu',
								'power-schedule-manager'
							);
							?>
						</button>

						<a
							class="psm-button psm-button--secondary"
							href="<?php echo esc_url( $term_url ); ?>"
						>
							<?php
							esc_html_e(
								'Về hôm nay',
								'power-schedule-manager'
							);
							?>
						</a>
					</div>
				</form>
			<?php endif; ?>
		</section>

		<section
			class="psm-area-results"
			aria-labelledby="psm-area-results-title"
		>
			<h2
				id="psm-area-results-title"
				class="psm-section-title"
			>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Unit name, 2: Start date. */
						__(
							'Lịch điện %1$s từ ngày %2$s',
							'power-schedule-manager'
						),
						$unit_name,
						$display_date
					)
				);
				?>
			</h2>

			<?php if ( '' !== $schedule_output ) : ?>
				<?php echo $schedule_output; ?>
			<?php else : ?>
				<?php
				echo Power_Schedule_Manager_Renderer::empty_state(
					__(
						'Khu vực này chưa được liên kết với một đơn vị điện lực hợp lệ.',
						'power-schedule-manager'
					),
					true
				);
				?>
			<?php endif; ?>
		</section>

		<section class="psm-information">
			<h2 class="psm-section-title">
				<?php
				esc_html_e(
					'Thông tin tra cứu',
					'power-schedule-manager'
				);
				?>
			</h2>

			<ul>
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: Unit name. */
							__(
								'Kết quả chỉ bao gồm lịch thuộc %s.',
								'power-schedule-manager'
							),
							$unit_name
						)
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Mỗi lần tra cứu hiển thị tối đa 7 ngày kể từ ngày được chọn.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Thời gian được hiển thị theo múi giờ Việt Nam.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Lịch có thể thay đổi theo điều kiện vận hành thực tế.',
						'power-schedule-manager'
					);
					?>
				</li>
			</ul>
		</section>

		<?php if ( is_string( $archive_url ) ) : ?>
			<footer class="psm-area-footer">
				<a
					class="psm-back-link"
					href="<?php echo esc_url( $archive_url ); ?>"
				>
					<span aria-hidden="true">←</span>

					<?php
					esc_html_e(
						'Xem lịch của tất cả đơn vị điện lực',
						'power-schedule-manager'
					);
					?>
				</a>
			</footer>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
