<?php
/**
 * Production system health and integrity centre.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$health = isset( $psm_admin_args['health'] )
	&& is_array( $psm_admin_args['health'] )
	? $psm_admin_args['health']
	: array();

$environment = isset( $health['environment'] )
	&& is_array( $health['environment'] )
	? $health['environment']
	: array();

$tables = isset( $health['tables'] )
	&& is_array( $health['tables'] )
	? $health['tables']
	: array();

$integrity = isset( $health['integrity'] )
	&& is_array( $health['integrity'] )
	? $health['integrity']
	: array();

$attention = isset( $health['attention'] )
	&& is_array( $health['attention'] )
	? $health['attention']
	: array();

$benchmark = isset( $health['benchmark'] )
	&& is_array( $health['benchmark'] )
	? $health['benchmark']
	: array();

$notifications = isset( $psm_admin_args['notifications'] )
	&& is_array( $psm_admin_args['notifications'] )
		? $psm_admin_args['notifications']
		: array();
$notification_status_labels = array(
	'pending' => __( 'Chờ gửi', 'power-schedule-manager' ),
	'retry'   => __( 'Chờ thử lại', 'power-schedule-manager' ),
	'sending' => __( 'Đang gửi', 'power-schedule-manager' ),
	'sent'    => __( 'Đã gửi', 'power-schedule-manager' ),
	'cancelled' => __( 'Không có thuê bao phù hợp', 'power-schedule-manager' ),
	'failed'  => __( 'Thất bại', 'power-schedule-manager' ),
);

$status_label = static function ( string $status ): string {
	return match ( $status ) {
		'good'    => __( 'Tốt', 'power-schedule-manager' ),
		'warning' => __( 'Cần chú ý', 'power-schedule-manager' ),
		'error'   => __( 'Có lỗi', 'power-schedule-manager' ),
		default   => __( 'Chưa rõ', 'power-schedule-manager' ),
	};
};

$history_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::HISTORY_SLUG,
	),
	admin_url( 'admin.php' )
);

$draft_url = add_query_arg(
	array(
		'post_type'   => Power_Schedule_Manager_Post_Type::POST_TYPE,
		'post_status' => 'draft',
	),
	admin_url( 'edit.php' )
);

$places_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Place_Library::MENU_SLUG,
	),
	admin_url( 'admin.php' )
);
?>

<div class="wrap psm-admin-wrap psm-system-health">
	<div class="psm-system-health__header">
		<div>
			<h1><?php esc_html_e( 'Trung tâm kiểm tra hệ thống', 'power-schedule-manager' ); ?></h1>
			<p class="psm-admin-lead">
				<?php esc_html_e( 'Theo dõi môi trường, tính toàn vẹn dữ liệu và các việc cần quản trị viên xử lý.', 'power-schedule-manager' ); ?>
			</p>
		</div>

		<div class="psm-system-health__meta">
			<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: Plugin version. */
						__( 'Phiên bản %s', 'power-schedule-manager' ),
						POWER_SCHEDULE_MANAGER_VERSION
					)
				);
				?>
			</strong>
			<span class="psm-health-readonly">
				<span class="dashicons dashicons-lock" aria-hidden="true"></span>
				<?php esc_html_e( 'Chỉ đọc · không tự sửa dữ liệu', 'power-schedule-manager' ); ?>
			</span>
		</div>
	</div>

	<nav class="psm-settings-tabs" aria-label="<?php esc_attr_e( 'Điều hướng trung tâm hệ thống', 'power-schedule-manager' ); ?>">
		<a href="#psm-health-status"><?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?></a>
		<a href="#psm-health-integrity"><?php esc_html_e( 'Toàn vẹn database', 'power-schedule-manager' ); ?></a>
		<a href="#psm-health-notifications"><?php esc_html_e( 'Thông báo', 'power-schedule-manager' ); ?></a>
		<a href="#psm-health-attention">
			<?php esc_html_e( 'Cần xử lý', 'power-schedule-manager' ); ?>
			<span class="psm-tab-count"><?php echo esc_html( number_format_i18n( count( $attention ) ) ); ?></span>
		</a>
	</nav>

	<section id="psm-health-status" class="psm-dashboard-panel">
		<div class="psm-panel-heading">
			<div>
				<h2><?php esc_html_e( 'Tình trạng vận hành', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Kết quả được cache 60 giây để tránh tạo tải lặp lại trên database.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>

		<div class="psm-health-grid">
			<?php foreach ( $environment as $check ) : ?>
				<?php
				if ( ! is_array( $check ) ) {
					continue;
				}

				$status = sanitize_key( (string) ( $check['status'] ?? '' ) );
				?>
				<article class="psm-health-card psm-health-card--<?php echo esc_attr( $status ); ?>">
					<div class="psm-health-card__top">
						<h3><?php echo esc_html( (string) ( $check['label'] ?? '' ) ); ?></h3>
						<span><?php echo esc_html( $status_label( $status ) ); ?></span>
					</div>
					<strong class="psm-health-card__value"><?php echo esc_html( (string) ( $check['value'] ?? '' ) ); ?></strong>
					<p><?php echo esc_html( (string) ( $check['description'] ?? '' ) ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section id="psm-health-integrity" class="psm-dashboard-panel">
		<div class="psm-panel-heading">
			<div>
				<h2><?php esc_html_e( 'Kiểm tra toàn vẹn database', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Mỗi phép kiểm tra chỉ đếm và lấy tối đa 12 ID mẫu; không quét hoặc tải toàn bộ nội dung.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>

		<div class="psm-integrity-list">
			<?php if ( array() === $integrity ) : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( 'Chưa thể kiểm tra quan hệ vì schema đang thiếu bảng.', 'power-schedule-manager' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $integrity as $check ) : ?>
					<?php
					if ( ! is_array( $check ) ) {
						continue;
					}

					$count   = absint( $check['count'] ?? 0 );
					$samples = isset( $check['samples'] ) && is_array( $check['samples'] )
						? $check['samples']
						: array();
					?>
					<div class="psm-integrity-row <?php echo $count > 0 ? 'is-warning' : 'is-good'; ?>">
						<span class="dashicons <?php echo $count > 0 ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>
						<div>
							<strong><?php echo esc_html( (string) ( $check['label'] ?? '' ) ); ?></strong>
							<?php if ( array() !== $samples ) : ?>
								<small>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: Sample database IDs. */
											__( 'ID mẫu: %s', 'power-schedule-manager' ),
											implode( ', ', array_map( 'absint', $samples ) )
										)
									);
									?>
								</small>
							<?php endif; ?>
						</div>
						<b><?php echo esc_html( number_format_i18n( $count ) ); ?></b>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<details class="psm-table-statistics">
			<summary><?php esc_html_e( 'Số hàng ước tính theo bảng', 'power-schedule-manager' ); ?></summary>
			<div class="psm-table-statistics__grid">
				<?php foreach ( $tables as $table ) : ?>
					<?php if ( is_array( $table ) ) : ?>
						<div>
							<code><?php echo esc_html( (string) ( $table['key'] ?? '' ) ); ?></code>
							<strong>
								<?php
								echo null === ( $table['rows'] ?? null )
									? esc_html__( 'Thiếu bảng', 'power-schedule-manager' )
									: esc_html(
										number_format_i18n(
											absint( $table['rows'] )
										)
									);
								?>
							</strong>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</details>
	</section>

	<section id="psm-health-notifications" class="psm-dashboard-panel">
		<div class="psm-panel-heading">
			<div>
				<h2><?php esc_html_e( 'Hàng đợi thông báo', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Hiển thị tối đa 20 tác vụ gần nhất. Payload và thông tin bí mật không được đưa ra giao diện.', 'power-schedule-manager' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="psm_retry_notifications">
				<?php wp_nonce_field( 'psm_retry_notifications' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Thử lại tác vụ lỗi', 'power-schedule-manager' ); ?></button>
			</form>
		</div>

		<?php
		$retried_count = isset( $_GET['psm_retried'] ) && is_scalar( $_GET['psm_retried'] )
			? absint( $_GET['psm_retried'] )
			: null;
		?>
		<?php if ( null !== $retried_count ) : ?>
			<div class="notice notice-success inline">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Requeued notification count. */
							__( 'Đã đưa %d tác vụ trở lại hàng đợi.', 'power-schedule-manager' ),
							$retried_count
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="psm-responsive-table">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Kênh', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lần thử', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'HTTP', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'OneSignal ID', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lần thử gần nhất UTC', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Cập nhật UTC', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lỗi gần nhất', 'power-schedule-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $notifications ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'Chưa có tác vụ thông báo.', 'power-schedule-manager' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $notifications as $notification ) : ?>
							<?php
							$notification_status = sanitize_key(
								(string) ( $notification['status'] ?? '' )
							);
							?>
							<tr>
								<td><?php echo esc_html( absint( $notification['id'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $notification['channel'] ?? '' ) ); ?></td>
								<td>
									<?php
									echo esc_html(
										$notification_status_labels[
											$notification_status
										] ?? $notification_status
									);
									?>
								</td>
								<td><?php echo esc_html( absint( $notification['attempts'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( absint( $notification['response_code'] ?? 0 ) ?: '—' ); ?></td>
								<td><?php echo esc_html( (string) ( $notification['onesignal_message_id'] ?? '' ) ?: '—' ); ?></td>
								<td><?php echo esc_html( (string) ( $notification['last_attempt_at_utc'] ?? '' ) ?: '—' ); ?></td>
								<td><?php echo esc_html( (string) ( $notification['updated_at_utc'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $notification['last_error'] ?? '' ) ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="psm-dashboard-panel">
		<div class="psm-panel-heading">
			<div>
				<h2><?php esc_html_e( 'Benchmark truy vấn đọc', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Ba truy vấn đại diện được giới hạn số hàng và không thay đổi dữ liệu. Từ 100 ms trở lên được đánh dấu cần theo dõi.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>
		<div class="psm-integrity-list">
			<?php foreach ( $benchmark as $sample ) : ?>
				<?php if ( is_array( $sample ) ) : ?>
					<div class="psm-integrity-row <?php echo 'warning' === ( $sample['status'] ?? '' ) ? 'is-warning' : 'is-good'; ?>">
						<span class="dashicons dashicons-performance" aria-hidden="true"></span>
						<div><strong><?php echo esc_html( (string) ( $sample['label'] ?? '' ) ); ?></strong></div>
						<b><?php echo esc_html( number_format_i18n( (float) ( $sample['ms'] ?? 0 ), 2 ) ); ?> ms</b>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</section>

	<section id="psm-health-attention" class="psm-dashboard-panel">
		<div class="psm-panel-heading">
			<div>
				<h2><?php esc_html_e( 'Hàng đợi cần xử lý', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Ưu tiên lỗi nhập gần đây, sau đó đến dữ liệu cần rà soát. Không có tác vụ tự động nguy hiểm.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>

		<?php if ( array() === $attention ) : ?>
			<div class="psm-attention-empty">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Không có việc tồn đọng', 'power-schedule-manager' ); ?></strong>
					<p><?php esc_html_e( 'Các phép kiểm tra hiện chưa phát hiện lỗi cần xử lý.', 'power-schedule-manager' ); ?></p>
				</div>
			</div>
		<?php else : ?>
			<div class="psm-attention-list">
				<?php foreach ( $attention as $item ) : ?>
					<?php
					if ( ! is_array( $item ) ) {
						continue;
					}

					$target = sanitize_key( (string) ( $item['target'] ?? '' ) );
					$url    = 'history' === $target
						? add_query_arg( 'status', 'failed', $history_url )
						: (
							'drafts' === $target
								? $draft_url
								: (
									'places' === $target
										? $places_url
										: '#psm-health-integrity'
								)
						);
					?>
					<a class="psm-attention-item psm-attention-item--<?php echo esc_attr( (string) ( $item['severity'] ?? 'warning' ) ); ?>" href="<?php echo esc_url( $url ); ?>">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<span><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( absint( $item['count'] ?? 0 ) ) ); ?></strong>
						<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
