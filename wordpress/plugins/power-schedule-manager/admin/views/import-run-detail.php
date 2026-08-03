<?php
/**
 * Protected import-run investigation view.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$run = isset( $psm_admin_args['run'] ) && is_array( $psm_admin_args['run'] )
	? $psm_admin_args['run']
	: null;

$activity = isset( $psm_admin_args['activity'] ) && is_array( $psm_admin_args['activity'] )
	? $psm_admin_args['activity']
	: array();

$history_url = add_query_arg(
	array( 'page' => Power_Schedule_Manager_Admin::HISTORY_SLUG ),
	admin_url( 'admin.php' )
);

$local_datetime = static function ( mixed $value ): string {
	if ( ! is_string( $value ) || '' === $value ) {
		return '—';
	}

	try {
		return ( new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) ) )
			->setTimezone( new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE ) )
			->format( 'H:i:s d/m/Y' );
	} catch ( Exception ) {
		return '—';
	}
};
?>

<div class="wrap psm-admin-wrap psm-import-detail">
	<p>
		<a href="<?php echo esc_url( $history_url ); ?>">← <?php esc_html_e( 'Quay lại lịch sử đồng bộ', 'power-schedule-manager' ); ?></a>
	</p>

	<?php if ( null === $run ) : ?>
		<h1><?php esc_html_e( 'Không tìm thấy lần nhập', 'power-schedule-manager' ); ?></h1>
		<p><?php esc_html_e( 'Bản ghi có thể đã hết thời hạn lưu giữ hoặc ID không hợp lệ.', 'power-schedule-manager' ); ?></p>
	<?php else : ?>
		<div class="psm-system-health__header psm-editorial-hero psm-editorial-hero--detail">
			<div>
				<h1>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Import run ID. */
							__( 'Điều tra lần nhập #%d', 'power-schedule-manager' ),
							absint( $run['id'] ?? 0 )
						)
					);
					?>
				</h1>
				<p class="psm-admin-lead">
					<?php esc_html_e( 'Dấu vết xử lý, kết quả và các sự kiện bị tác động. Payload nguồn không được hiển thị trên màn hình này.', 'power-schedule-manager' ); ?>
				</p>
			</div>
			<span class="psm-status-badge psm-status-badge--<?php echo esc_attr( sanitize_key( (string) ( $run['status'] ?? '' ) ) ); ?>">
				<?php echo esc_html( (string) ( $run['status'] ?? '—' ) ); ?>
			</span>
		</div>

		<section class="psm-dashboard-panel">
			<h2><?php esc_html_e( 'Tóm tắt', 'power-schedule-manager' ); ?></h2>
			<div class="psm-run-summary">
				<div><span><?php esc_html_e( 'Đơn vị', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( (string) ( $run['unit_code'] ?? '—' ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Bắt đầu', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $local_datetime( $run['started_at_utc'] ?? null ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Kết thúc', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $local_datetime( $run['finished_at_utc'] ?? null ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Dung lượng nguồn', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( size_format( absint( $run['payload_bytes'] ?? 0 ) ) ); ?></strong></div>
			</div>

			<div class="psm-run-counters">
				<div><strong><?php echo esc_html( number_format_i18n( absint( $run['found_count'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Tìm thấy', 'power-schedule-manager' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( absint( $run['inserted_count'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Thêm mới', 'power-schedule-manager' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( absint( $run['updated_count'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Cập nhật', 'power-schedule-manager' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( absint( $run['unchanged_count'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Không đổi', 'power-schedule-manager' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( absint( $run['duplicate_count'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Có thể trùng', 'power-schedule-manager' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( absint( $run['error_count'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Lỗi', 'power-schedule-manager' ); ?></span></div>
			</div>

			<?php if ( ! empty( $run['error_message'] ) ) : ?>
				<div class="notice notice-error inline">
					<p><strong><?php echo esc_html( (string) ( $run['error_code'] ?? '' ) ); ?></strong> <?php echo esc_html( (string) $run['error_message'] ); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<section class="psm-dashboard-panel">
			<h2>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: Number of events. */
						__( 'Sự kiện bị tác động (%d)', 'power-schedule-manager' ),
						absint( $activity['events_total'] ?? 0 )
					)
				);
				?>
			</h2>

			<div class="psm-history-table-wrap">
				<table class="widefat striped psm-history-table">
					<thead><tr><th><?php esc_html_e( 'ID', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Ngày và giờ', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Khu vực', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Bài lịch', 'power-schedule-manager' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( (array) ( $activity['events'] ?? array() ) as $event ) : ?>
							<?php if ( is_array( $event ) ) : ?>
								<?php
								$event_post_id = absint( $event['post_id'] ?? 0 );
								$event_edit_url = $event_post_id > 0
									? get_edit_post_link( $event_post_id, 'raw' )
									: '';
								?>
								<tr>
									<td>#<?php echo esc_html( absint( $event['id'] ?? 0 ) ); ?></td>
									<td><?php echo esc_html( (string) ( $event['local_date'] ?? '' ) ); ?><br><small><?php echo esc_html( $local_datetime( $event['start_at_utc'] ?? null ) ); ?></small></td>
									<td><?php echo esc_html( (string) ( $event['area'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $event['status'] ?? '' ) ); ?></td>
									<td>
										<?php if ( is_string( $event_edit_url ) && '' !== $event_edit_url ) : ?>
											<a href="<?php echo esc_url( $event_edit_url ); ?>"><?php esc_html_e( 'Mở bài', 'power-schedule-manager' ); ?></a>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if ( empty( $activity['events'] ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'Lần nhập này không tạo hoặc cập nhật sự kiện.', 'power-schedule-manager' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="psm-dashboard-panel">
			<h2>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: Number of revisions. */
						__( 'Lịch sử thay đổi (%d)', 'power-schedule-manager' ),
						absint( $activity['revisions_total'] ?? 0 )
					)
				);
				?>
			</h2>
			<ul class="psm-revision-list">
				<?php foreach ( (array) ( $activity['revisions'] ?? array() ) as $revision ) : ?>
					<?php if ( is_array( $revision ) ) : ?>
						<li>
							<strong><?php echo esc_html( (string) ( $revision['change_type'] ?? '' ) ); ?></strong>
							<span><?php echo esc_html( sprintf( 'Event #%1$d · revision %2$d', absint( $revision['event_id'] ?? 0 ), absint( $revision['revision_number'] ?? 0 ) ) ); ?></span>
							<time><?php echo esc_html( $local_datetime( $revision['created_at_utc'] ?? null ) ); ?></time>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( empty( $activity['revisions'] ) ) : ?>
					<li><?php esc_html_e( 'Không có revision phát sinh.', 'power-schedule-manager' ); ?></li>
				<?php endif; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>
