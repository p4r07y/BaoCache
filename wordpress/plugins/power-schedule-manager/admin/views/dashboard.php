<?php
/**
 * Administration dashboard view.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$stats = isset( $psm_admin_args['stats'] )
	&& is_array( $psm_admin_args['stats'] )
	? $psm_admin_args['stats']
	: array();

$today_count = absint(
	$stats['today'] ?? 0
);

$ongoing_count = absint(
	$stats['ongoing'] ?? 0
);

$upcoming_count = absint(
	$stats['upcoming_seven_days'] ?? 0
);

$pending_publication = absint(
	$stats['pending_publication'] ?? 0
);

$recent_events = isset( $stats['recent_events'] )
	&& is_array( $stats['recent_events'] )
	? $stats['recent_events']
	: array();

$recent_imports = isset( $stats['recent_imports'] )
	&& is_array( $stats['recent_imports'] )
	? $stats['recent_imports']
	: array();

$schedule_list_url = add_query_arg(
	array(
		'post_type' => Power_Schedule_Manager_Post_Type::POST_TYPE,
	),
	admin_url( 'edit.php' )
);

$today_schedule_url = add_query_arg(
	array(
		'post_type'      => Power_Schedule_Manager_Post_Type::POST_TYPE,
		'psm_admin_date' => wp_date(
			'Y-m-d',
			null,
			new DateTimeZone(
				POWER_SCHEDULE_MANAGER_TIMEZONE
			)
		),
	),
	admin_url( 'edit.php' )
);

$draft_schedule_url = add_query_arg(
	array(
		'post_type'   => Power_Schedule_Manager_Post_Type::POST_TYPE,
		'post_status' => 'draft',
	),
	admin_url( 'edit.php' )
);

$import_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::IMPORT_SLUG,
	),
	admin_url( 'admin.php' )
);

$history_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::HISTORY_SLUG,
	),
	admin_url( 'admin.php' )
);

$system_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::SYSTEM_SLUG,
	),
	admin_url( 'admin.php' )
);

$places_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Place_Library::MENU_SLUG,
	),
	admin_url( 'admin.php' )
);

$public_schedule_url = home_url( '/lich-cup-dien/' );

?>

<div class="wrap psm-admin-wrap psm-dashboard-home">
	<header class="psm-dashboard-header">
		<div>
			<h1><?php esc_html_e( 'Tổng quan lịch cúp điện', 'power-schedule-manager' ); ?></h1>
			<p><?php esc_html_e( 'Theo dõi lịch sắp tới và những nội dung cần xử lý.', 'power-schedule-manager' ); ?></p>
		</div>

		<div class="psm-dashboard-header__actions">
			<?php if ( current_user_can( Power_Schedule_Manager_Capabilities::IMPORT_SCHEDULES ) ) : ?>
				<a class="button button-primary psm-dashboard-header__action" href="<?php echo esc_url( $import_url ); ?>">
					<span class="dashicons dashicons-database-import" aria-hidden="true"></span>
					<?php esc_html_e( 'Nhập dữ liệu mới', 'power-schedule-manager' ); ?>
				</a>
			<?php endif; ?>

			<details class="psm-dashboard-more-actions">
				<summary class="button button-secondary psm-dashboard-more-actions__trigger">
					<?php esc_html_e( 'Tác vụ khác', 'power-schedule-manager' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</summary>
				<nav class="psm-dashboard-more-actions__menu" aria-label="<?php esc_attr_e( 'Tác vụ khác', 'power-schedule-manager' ); ?>">
					<a href="<?php echo esc_url( $schedule_list_url ); ?>"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><span><?php esc_html_e( 'Quản lý lịch', 'power-schedule-manager' ); ?></span></a>
					<?php if ( current_user_can( Power_Schedule_Manager_Capabilities::MANAGE_UNITS ) ) : ?>
						<a href="<?php echo esc_url( $places_url ); ?>"><span class="dashicons dashicons-location-alt" aria-hidden="true"></span><span><?php esc_html_e( 'Thư viện bản đồ', 'power-schedule-manager' ); ?></span></a>
					<?php endif; ?>
					<?php if ( current_user_can( Power_Schedule_Manager_Capabilities::VIEW_LOGS ) ) : ?>
						<a href="<?php echo esc_url( $history_url ); ?>"><span class="dashicons dashicons-backup" aria-hidden="true"></span><span><?php esc_html_e( 'Lịch sử đồng bộ', 'power-schedule-manager' ); ?></span></a>
					<?php endif; ?>
					<?php if ( current_user_can( Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS ) ) : ?>
						<a href="<?php echo esc_url( $system_url ); ?>"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><span><?php esc_html_e( 'Kiểm tra hệ thống', 'power-schedule-manager' ); ?></span></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $public_schedule_url ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" aria-hidden="true"></span><span><?php esc_html_e( 'Xem lịch cúp điện', 'power-schedule-manager' ); ?></span></a>
				</nav>
			</details>
		</div>
	</header>

	<nav class="psm-overview-metrics" aria-label="<?php esc_attr_e( 'Chỉ số lịch cúp điện', 'power-schedule-manager' ); ?>">
		<a href="<?php echo esc_url( $today_schedule_url ); ?>">
			<span class="psm-overview-metric__icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
			<span class="psm-overview-metric__content">
				<span><?php esc_html_e( 'Hôm nay', 'power-schedule-manager' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $today_count ) ); ?></strong>
				<small><?php esc_html_e( 'lịch trong ngày', 'power-schedule-manager' ); ?></small>
			</span>
		</a>
		<a href="<?php echo esc_url( $schedule_list_url ); ?>">
			<span class="psm-overview-metric__icon dashicons dashicons-controls-play" aria-hidden="true"></span>
			<span class="psm-overview-metric__content">
				<span><?php esc_html_e( 'Đang diễn ra', 'power-schedule-manager' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $ongoing_count ) ); ?></strong>
				<small><?php esc_html_e( 'cần theo dõi', 'power-schedule-manager' ); ?></small>
			</span>
		</a>
		<a href="<?php echo esc_url( $schedule_list_url ); ?>">
			<span class="psm-overview-metric__icon dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
			<span class="psm-overview-metric__content">
				<span><?php esc_html_e( '7 ngày tới', 'power-schedule-manager' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $upcoming_count ) ); ?></strong>
				<small><?php esc_html_e( 'lịch sắp tới', 'power-schedule-manager' ); ?></small>
			</span>
		</a>
		<a class="<?php echo $pending_publication > 0 ? 'needs-attention' : ''; ?>" href="<?php echo esc_url( $draft_schedule_url ); ?>">
			<span class="psm-overview-metric__icon dashicons dashicons-warning" aria-hidden="true"></span>
			<span class="psm-overview-metric__content">
				<span><?php esc_html_e( 'Chờ xuất bản', 'power-schedule-manager' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $pending_publication ) ); ?></strong>
				<small><?php esc_html_e( 'bài chưa xuất bản', 'power-schedule-manager' ); ?></small>
			</span>
		</a>
	</nav>

	<div class="psm-dashboard-operations">
		<section class="psm-dashboard-panel psm-recent-schedules">
			<div class="psm-panel-heading">
				<div>
					<h2><?php esc_html_e( 'Lịch cúp điện gần nhất', 'power-schedule-manager' ); ?></h2>
					<p><?php esc_html_e( 'Các sự kiện đang diễn ra hoặc sắp tới, ưu tiên theo thời gian bắt đầu.', 'power-schedule-manager' ); ?></p>
				</div>
				<a href="<?php echo esc_url( $schedule_list_url ); ?>">
					<?php esc_html_e( 'Xem tất cả', 'power-schedule-manager' ); ?>
				</a>
			</div>

			<?php if ( array() === $recent_events ) : ?>
				<p class="psm-dashboard-empty"><?php esc_html_e( 'Chưa có lịch cúp điện sắp tới.', 'power-schedule-manager' ); ?></p>
			<?php else : ?>
				<div class="psm-dashboard-table-wrap">
					<table class="widefat striped psm-dashboard-table">
						<colgroup>
							<col class="psm-dashboard-table__date">
							<col class="psm-dashboard-table__unit">
							<col class="psm-dashboard-table__time">
							<col class="psm-dashboard-table__area">
							<col class="psm-dashboard-table__publication">
							<col class="psm-dashboard-table__actions">
						</colgroup>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Ngày', 'power-schedule-manager' ); ?></th>
								<th><?php esc_html_e( 'Đơn vị', 'power-schedule-manager' ); ?></th>
								<th><?php esc_html_e( 'Thời gian', 'power-schedule-manager' ); ?></th>
								<th><?php esc_html_e( 'Khu vực', 'power-schedule-manager' ); ?></th>
								<th><?php esc_html_e( 'Xuất bản', 'power-schedule-manager' ); ?></th>
								<th><?php esc_html_e( 'Thao tác', 'power-schedule-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_events as $event ) : ?>
								<?php
								$post_id = absint( $event['post_id'] ?? 0 );
								$edit_url = $post_id > 0 ? get_edit_post_link( $post_id, 'raw' ) : '';
								$view_url = $post_id > 0 && 'publish' === ( $event['post_status'] ?? '' )
									? get_permalink( $post_id )
									: '';
								$start = strtotime( (string) ( $event['start_at_utc'] ?? '' ) . ' UTC' );
								$end = strtotime( (string) ( $event['end_at_utc'] ?? '' ) . ' UTC' );
								?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'd/m/Y', (string) ( $event['local_date'] ?? '' ), false ) ); ?></td>
									<td><?php echo esc_html( (string) ( $event['unit_name'] ?? $event['unit_code'] ?? '—' ) ); ?></td>
									<td><?php echo esc_html( $start && $end ? wp_date( 'H:i', $start, new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE ) ) . '–' . wp_date( 'H:i', $end, new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE ) ) : '—' ); ?></td>
									<td class="psm-dashboard-area">
										<span
											class="psm-dashboard-area__text"
											title="<?php echo esc_attr( (string) ( $event['area'] ?? '—' ) ); ?>"
										>
											<?php echo esc_html( (string) ( $event['area'] ?? '—' ) ); ?>
										</span>
									</td>
									<td>
										<?php if ( 'publish' === ( $event['post_status'] ?? '' ) ) : ?>
											<span class="psm-status psm-status--success"><?php esc_html_e( 'Đã xuất bản', 'power-schedule-manager' ); ?></span>
										<?php elseif ( is_string( $edit_url ) && '' !== $edit_url ) : ?>
											<a class="psm-status psm-status--warning" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Bản nháp', 'power-schedule-manager' ); ?></a>
										<?php else : ?>
											<span class="psm-status psm-status--neutral"><?php esc_html_e( 'Chưa liên kết', 'power-schedule-manager' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<div class="psm-dashboard-row-actions">
											<?php if ( is_string( $view_url ) && '' !== $view_url ) : ?>
												<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Xem', 'power-schedule-manager' ); ?></a>
											<?php endif; ?>
											<?php if ( is_string( $edit_url ) && '' !== $edit_url ) : ?>
												<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Sửa', 'power-schedule-manager' ); ?></a>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>

		<aside class="psm-dashboard-panel psm-recent-activity">
			<div class="psm-panel-heading">
				<div>
					<h2><?php esc_html_e( 'Hoạt động gần đây', 'power-schedule-manager' ); ?></h2>
					<p><?php esc_html_e( 'Các lần nhập dữ liệu mới nhất.', 'power-schedule-manager' ); ?></p>
				</div>
			</div>

			<?php if ( array() === $recent_imports ) : ?>
				<p class="psm-dashboard-empty"><?php esc_html_e( 'Chưa có lịch sử đồng bộ.', 'power-schedule-manager' ); ?></p>
			<?php else : ?>
				<ul class="psm-activity-list">
					<?php foreach ( $recent_imports as $run ) : ?>
						<?php
						$is_failed = 'failed' === ( $run['status'] ?? '' );
						$started = strtotime( (string) ( $run['started_at_utc'] ?? '' ) . ' UTC' );
						?>
						<li class="<?php echo $is_failed ? 'is-error' : 'is-success'; ?>">
							<span class="dashicons <?php echo $is_failed ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>
							<div class="psm-activity-body">
								<div class="psm-activity-header">
									<div class="psm-activity-summary">
										<strong><?php echo esc_html( $is_failed ? __( 'Đồng bộ thất bại', 'power-schedule-manager' ) : __( 'Đồng bộ thành công', 'power-schedule-manager' ) ); ?></strong>
										<span class="psm-activity-unit"><?php echo esc_html( (string) ( $run['unit_code'] ?? '—' ) ); ?></span>
									</div>
									<time datetime="<?php echo esc_attr( $started ? gmdate( 'c', $started ) : '' ); ?>"><?php echo esc_html( $started ? wp_date( 'H:i d/m', $started, new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE ) ) : '—' ); ?></time>
								</div>
								<div class="psm-activity-counts">
									<span><strong><?php echo esc_html( number_format_i18n( absint( $run['inserted_count'] ?? 0 ) ) ); ?></strong><?php esc_html_e( 'Mới', 'power-schedule-manager' ); ?></span>
									<span><strong><?php echo esc_html( number_format_i18n( absint( $run['updated_count'] ?? 0 ) ) ); ?></strong><?php esc_html_e( 'Cập nhật', 'power-schedule-manager' ); ?></span>
									<span><strong><?php echo esc_html( number_format_i18n( absint( $run['unchanged_count'] ?? 0 ) ) ); ?></strong><?php esc_html_e( 'Không đổi', 'power-schedule-manager' ); ?></span>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="button psm-activity-all" href="<?php echo esc_url( $history_url ); ?>"><?php esc_html_e( 'Xem toàn bộ hoạt động', 'power-schedule-manager' ); ?></a>
			<?php endif; ?>
		</aside>
	</div>

</div>
