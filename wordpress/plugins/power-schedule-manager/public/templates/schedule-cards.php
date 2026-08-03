<?php
/**
 * Mobile schedule cards grouped by date and unit.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/schedule-cards.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$events = isset( $psm_template_args['events'] )
	&& is_array( $psm_template_args['events'] )
	? $psm_template_args['events']
	: array();

$show_reason = ! isset( $psm_template_args['show_reason'] )
	|| (bool) $psm_template_args['show_reason'];

$show_status = ! isset( $psm_template_args['show_status'] )
	|| (bool) $psm_template_args['show_status'];

$show_map = ! isset( $psm_template_args['show_map'] )
	|| (bool) $psm_template_args['show_map'];

$map_mode = isset( $psm_template_args['map_mode'] )
	&& is_string( $psm_template_args['map_mode'] )
	? sanitize_key( $psm_template_args['map_mode'] )
	: 'row_buttons';

$show_row_map = $show_map && 'row_buttons' === $map_mode;

$show_details = ! isset( $psm_template_args['show_details'] )
	|| (bool) $psm_template_args['show_details'];

$groups = array();

foreach ( $events as $event ) {
	if ( ! is_array( $event ) ) {
		continue;
	}

	$local_date   = sanitize_text_field(
		(string) ( $event['local_date'] ?? '' )
	);
	$display_date = sanitize_text_field(
		(string) ( $event['display_date'] ?? '' )
	);
	$unit_code    = sanitize_text_field(
		(string) ( $event['unit_code'] ?? '' )
	);
	$unit_name    = sanitize_text_field(
		(string) ( $event['unit_name'] ?? '' )
	);
	$group_key    = $local_date . '|' . $unit_code;

	if ( ! isset( $groups[ $group_key ] ) ) {
		$groups[ $group_key ] = array(
			'local_date'   => $local_date,
			'display_date' => $display_date,
			'unit_name'    => $unit_name,
			'details_url'  => '',
			'detail_label' => trim( $display_date . ' ' . $unit_name ),
			'events'       => array(),
		);
	}

	if (
		'' === $groups[ $group_key ]['details_url']
		&& isset( $event['post_url'] )
		&& is_string( $event['post_url'] )
		&& '' !== $event['post_url']
	) {
		$groups[ $group_key ]['details_url'] = esc_url(
			$event['post_url']
		);
	}

	$groups[ $group_key ]['events'][] = $event;
}

$group_page = isset( $psm_template_args['group_page'] )
	? max( 1, absint( $psm_template_args['group_page'] ) )
	: 1;

$groups_per_page = isset( $psm_template_args['groups_per_page'] )
	? max( 0, absint( $psm_template_args['groups_per_page'] ) )
	: 0;

$pre_paginated = ! empty(
	$psm_template_args['pre_paginated']
);

if ( $groups_per_page > 0 && ! $pre_paginated ) {
	$groups = array_slice(
		$groups,
		( $group_page - 1 ) * $groups_per_page,
		$groups_per_page,
		true
	);
}
?>

<div class="psm-card-groups">
	<?php foreach ( $groups as $group ) : ?>
		<?php
		$group_events = isset( $group['events'] )
			&& is_array( $group['events'] )
			? $group['events']
			: array();
		?>

		<section class="psm-card-group">
			<header class="psm-card-group__header">
				<div class="psm-card-group__summary">
					<div class="psm-card-group__summary-top">
						<h3>
							<?php echo esc_html( (string) $group['display_date'] ); ?>
						</h3>
						<span class="psm-group-count-badge">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: Number of events. */
									_n(
										'%d khung giờ',
										'%d khung giờ',
										count( $group_events ),
										'power-schedule-manager'
									),
									count( $group_events )
								)
							);
							?>
						</span>
					</div>

					<?php if ( '' !== (string) $group['unit_name'] ) : ?>
						<p class="psm-card-group__unit">
							<?php echo esc_html( (string) $group['unit_name'] ); ?>
						</p>
					<?php endif; ?>
				</div>

				<div class="psm-card-group__meta">
					<?php if ( $show_details && '' !== (string) $group['details_url'] ) : ?>
							<a
								href="<?php echo esc_url( (string) $group['details_url'] ); ?>"
								aria-label="<?php
							echo esc_attr(
								sprintf(
									/* translators: %s: Schedule date and unit. */
									__( 'Xem chi tiết lịch cúp điện %s', 'power-schedule-manager' ),
									(string) $group['detail_label']
								)
							);
							?>"
						>
							<span><?php esc_html_e( 'Xem chi tiết', 'power-schedule-manager' ); ?></span>
							<span aria-hidden="true">→</span>
						</a>
					<?php endif; ?>
				</div>
			</header>

			<div class="psm-cards">
				<?php foreach ( $group_events as $event ) : ?>
					<?php
					if ( ! is_array( $event ) ) {
						continue;
					}

					$event_id = absint( $event['id'] ?? 0 );
					$row_class = sanitize_html_class(
						(string) (
							$event['row_class']
							?? 'psm-status--scheduled'
						)
					);
					$status = sanitize_key(
						(string) ( $event['status'] ?? 'scheduled' )
					);
					$display_time = sanitize_text_field(
						(string) ( $event['display_time_range'] ?? '' )
					);
					$area = sanitize_textarea_field(
						(string) ( $event['area'] ?? '' )
					);
					$reason = sanitize_textarea_field(
						(string) ( $event['reason'] ?? '' )
					);
					$status_label = sanitize_text_field(
						(string) ( $event['status_label'] ?? '' )
					);
					$status_description = sanitize_text_field(
						(string) ( $event['status_description'] ?? '' )
					);
					$has_map = ! empty( $event['has_map'] )
						&& $event_id > 0;
					$map_url = isset( $event['map_url'] )
						? esc_url( (string) $event['map_url'] )
						: '';
					?>

					<article class="psm-card <?php echo esc_attr( $row_class ); ?>">
						<header class="psm-card__header">
							<p class="psm-card__time">
								<?php echo esc_html( $display_time ); ?>
							</p>

							<?php if ( $show_status ) : ?>
								<span
									class="psm-status <?php echo esc_attr( $row_class ); ?>"
									title="<?php echo esc_attr( $status_description ); ?>"
								>
									<span class="psm-status__dot" aria-hidden="true"></span>
									<?php echo esc_html( $status_label ); ?>
								</span>
							<?php endif; ?>
						</header>

						<div class="psm-card__body">
							<div class="psm-card__field psm-card__field--area">
								<span class="psm-card__label">
									<?php esc_html_e( 'Khu vực', 'power-schedule-manager' ); ?>
								</span>

								<span class="psm-card__value">
									<?php echo '' !== $area ? esc_html( $area ) : '—'; ?>

									<?php if ( $show_row_map && $has_map && '' !== $map_url ) : ?>
										<button
											type="button"
											class="psm-map-link"
											data-psm-map-trigger
											data-event-id="<?php echo esc_attr( (string) $event_id ); ?>"
											data-map-url="<?php echo esc_url( $map_url ); ?>"
											data-event-status="<?php echo esc_attr( $status ); ?>"
											aria-expanded="false"
										>
											<span><?php esc_html_e( 'Xem bản đồ tuyến đường', 'power-schedule-manager' ); ?></span>
											<span aria-hidden="true">→</span>
										</button>
									<?php endif; ?>
								</span>
							</div>

							<?php if ( $show_reason ) : ?>
								<div class="psm-card__field">
									<span class="psm-card__label">
										<?php esc_html_e( 'Lý do', 'power-schedule-manager' ); ?>
									</span>

									<span class="psm-card__value">
										<?php echo '' !== $reason ? esc_html( $reason ) : '—'; ?>
									</span>
								</div>
							<?php endif; ?>
						</div>

					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
