<?php
/**
 * Desktop schedule table grouped by date and unit.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/schedule-table.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$events = isset( $psm_template_args['events'] )
	&& is_array( $psm_template_args['events'] )
	? $psm_template_args['events']
	: array();

$caption = isset( $psm_template_args['caption'] )
	&& is_string( $psm_template_args['caption'] )
	? $psm_template_args['caption']
	: __(
		'Danh sách lịch cúp điện',
		'power-schedule-manager'
	);

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

$render_group_details = static function ( array $group ): void {
	$details_url = isset( $group['details_url'] )
		&& is_string( $group['details_url'] )
		? $group['details_url']
		: '';

	if ( '' === $details_url ) {
		return;
	}

	$detail_label = isset( $group['detail_label'] )
		&& is_string( $group['detail_label'] )
		? $group['detail_label']
		: '';
	?>
	<a
		class="psm-group-detail-link"
		href="<?php echo esc_url( $details_url ); ?>"
		aria-label="<?php
		echo esc_attr(
			sprintf(
				/* translators: %s: Schedule date and unit. */
				__(
					'Xem chi tiết lịch cúp điện %s',
					'power-schedule-manager'
				),
				$detail_label
			)
		);
		?>"
	>
		<span><?php esc_html_e( 'Xem chi tiết', 'power-schedule-manager' ); ?></span>
		<span aria-hidden="true">→</span>
	</a>
	<?php
};

?>

<div class="psm-table-wrap">
	<table class="psm-table psm-table--grouped">
		<caption class="screen-reader-text">
			<?php echo esc_html( $caption ); ?>
		</caption>

		<thead>
			<tr>
				<th scope="col" class="psm-table__time">
					<?php esc_html_e( 'Thời gian', 'power-schedule-manager' ); ?>
				</th>

				<th scope="col" class="psm-table__area">
					<?php esc_html_e( 'Khu vực ảnh hưởng', 'power-schedule-manager' ); ?>
				</th>

				<?php if ( $show_reason ) : ?>
					<th scope="col" class="psm-table__reason">
						<?php esc_html_e( 'Lý do', 'power-schedule-manager' ); ?>
					</th>
				<?php endif; ?>

				<?php if ( $show_status ) : ?>
					<th scope="col" class="psm-table__status">
						<?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?>
					</th>
				<?php endif; ?>

			</tr>
		</thead>

		<?php foreach ( $groups as $group ) : ?>
			<?php
			$group_events = isset( $group['events'] )
				&& is_array( $group['events'] )
				? $group['events']
				: array();
			?>

			<tbody class="psm-table__group">
				<tr class="psm-table__group-row">
					<th
						scope="rowgroup"
						class="psm-table__group-date-cell"
					>
						<span class="psm-table__group-date">
							<?php echo esc_html( (string) $group['display_date'] ); ?>
						</span>
					</th>

					<td class="psm-table__group-area-cell">
						<div class="psm-table__group-main">
							<?php if ( '' !== (string) $group['unit_name'] ) : ?>
								<span class="psm-table__group-unit">
									<?php echo esc_html( (string) $group['unit_name'] ); ?>
								</span>
							<?php endif; ?>

							<span class="psm-table__group-count psm-group-count-badge">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: Number of time ranges. */
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

							<?php if (
								$show_details
								&& ! $show_status
								&& '' !== (string) $group['details_url']
							) : ?>
								<?php $render_group_details( $group ); ?>
							<?php endif; ?>
						</div>
					</td>

					<?php if ( $show_reason ) : ?>
						<td class="psm-table__group-reason-cell"></td>
					<?php endif; ?>

					<?php if ( $show_status ) : ?>
						<td class="psm-table__group-status-cell">
							<?php if (
								$show_details
								&& '' !== (string) $group['details_url']
							) : ?>
								<?php $render_group_details( $group ); ?>
							<?php endif; ?>
						</td>
					<?php endif; ?>
				</tr>

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
					$start_iso = sanitize_text_field(
						(string) ( $event['start_iso'] ?? '' )
					);
					$end_iso = sanitize_text_field(
						(string) ( $event['end_iso'] ?? '' )
					);
					$start_time = sanitize_text_field(
						(string) ( $event['start_time'] ?? '' )
					);
					$end_time = sanitize_text_field(
						(string) ( $event['end_time'] ?? '' )
					);
					$end_date = sanitize_text_field(
						(string) ( $event['end_date'] ?? '' )
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

					<tr class="<?php echo esc_attr( $row_class ); ?>">
						<td class="psm-table__time" data-label="<?php esc_attr_e( 'Thời gian', 'power-schedule-manager' ); ?>">
							<span class="psm-time-range">
								<time datetime="<?php echo esc_attr( $start_iso ); ?>">
									<?php echo esc_html( $start_time ); ?>
								</time>

								<span aria-hidden="true">–</span>

								<time datetime="<?php echo esc_attr( $end_iso ); ?>">
									<?php echo esc_html( $end_time ); ?>
								</time>
							</span>

							<?php if ( ! empty( $event['crosses_date'] ) && '' !== $end_date ) : ?>
								<span class="psm-time-range__next-date">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: End date. */
											__( 'đến ngày %s', 'power-schedule-manager' ),
											$end_date
										)
									);
									?>
								</span>
							<?php endif; ?>
						</td>

						<td class="psm-table__area" data-label="<?php esc_attr_e( 'Khu vực', 'power-schedule-manager' ); ?>">
							<div class="psm-area-content">
								<span>
									<?php echo '' !== $area ? esc_html( $area ) : '—'; ?>
								</span>

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
							</div>
						</td>

						<?php if ( $show_reason ) : ?>
							<td class="psm-table__reason" data-label="<?php esc_attr_e( 'Lý do', 'power-schedule-manager' ); ?>">
								<?php echo '' !== $reason ? esc_html( $reason ) : '—'; ?>
							</td>
						<?php endif; ?>

						<?php if ( $show_status ) : ?>
							<td class="psm-table__status" data-label="<?php esc_attr_e( 'Trạng thái', 'power-schedule-manager' ); ?>">
								<span
									class="psm-status <?php echo esc_attr( $row_class ); ?>"
									title="<?php echo esc_attr( $status_description ); ?>"
								>
									<span class="psm-status__dot" aria-hidden="true"></span>
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
						<?php endif; ?>

					</tr>
				<?php endforeach; ?>
			</tbody>
		<?php endforeach; ?>
	</table>
</div>
