<?php
/**
 * Lottery management screen.
 *
 * @package Power_Schedule_Manager
 *
 * @var array<string,mixed> $settings
 * @var array<int,array<string,string>> $rows
 * @var array<string,string> $overview
 * @var string $notice
 * @var string $webhook_secret_display
 */

defined( 'ABSPATH' ) || exit;

$webhook_url = rest_url( 'power-schedule-manager/v1/lottery-webhook' );
$admin_page_url = admin_url(
	'admin.php?page=power-schedule-manager-lottery'
);
$lottery_pagination_base = str_replace(
	'999999999',
	'%#%',
	add_query_arg(
		array(
			'section'   => 'results',
			'data_page' => '999999999',
		),
		$admin_page_url
	)
);
$region_labels = array(
	'north'   => __( 'Miền Bắc', 'power-schedule-manager' ),
	'central' => __( 'Miền Trung', 'power-schedule-manager' ),
	'south'   => __( 'Miền Nam', 'power-schedule-manager' ),
);
$shortcode_groups = array(
	__( 'Trang trung tâm và tra cứu', 'power-schedule-manager' ) => array(
		'power_schedule_lottery_results mode="hub"',
		'power_schedule_lottery_archive',
		'power_schedule_lottery_results mode="full" history_limit="10"',
	),
	__( 'Xổ số truyền thống', 'power-schedule-manager' ) => array(
		'power_schedule_lottery_north',
		'power_schedule_lottery_central',
		'power_schedule_lottery_south',
	),
	__( 'Vietlott', 'power-schedule-manager' ) => array(
		'power_schedule_lottery_mega645',
		'power_schedule_lottery_power655',
		'power_schedule_lottery_max3d',
		'power_schedule_lottery_max3d_plus',
		'power_schedule_lottery_max3d_pro',
		'power_schedule_lottery_keno',
	),
	__( 'Điện toán', 'power-schedule-manager' ) => array(
		'power_schedule_lottery_dientoan',
		'power_schedule_lottery_dientoan123',
		'power_schedule_lottery_dientoan6x36',
		'power_schedule_lottery_thantai',
	),
	__( 'Thống kê', 'power-schedule-manager' ) => array(
		'power_schedule_lottery_special_week limit="7"',
	),
);
$page_blueprint = array(
	array(
		'title'      => __( 'Trang trung tâm xổ số', 'power-schedule-manager' ),
		'slug'       => '/ket-qua-xo-so-hom-nay/',
		'shortcodes' => array( 'power_schedule_lottery_results mode="hub"' ),
		'note'       => __( 'Dùng làm trang tổng quan. Hub chỉ hiển thị bản tóm tắt và dẫn người đọc đến đúng trang sản phẩm.', 'power-schedule-manager' ),
	),
	array(
		'title'      => __( 'Xổ số ba miền', 'power-schedule-manager' ),
		'slug'       => '/xo-so-ba-mien/',
		'shortcodes' => array(
			'power_schedule_lottery_north',
			'power_schedule_lottery_central',
			'power_schedule_lottery_south',
		),
		'note'       => __( 'Tạo ba tab Bắc – Trung – Nam trong trình dựng trang, rồi đặt một shortcode vào từng tab.', 'power-schedule-manager' ),
	),
	array(
		'title'      => 'Mega 6/45',
		'slug'       => '/xo-so-mega-645/',
		'shortcodes' => array(
			'power_schedule_lottery_mega645',
			'power_schedule_lottery_mega645_history',
		),
		'note'       => __( 'Đặt kết quả mới nhất trước, lịch sử các kỳ ở bên dưới.', 'power-schedule-manager' ),
	),
	array(
		'title'      => 'Power 6/55',
		'slug'       => '/xo-so-power-655/',
		'shortcodes' => array(
			'power_schedule_lottery_power655',
			'power_schedule_lottery_power655_history',
		),
		'note'       => __( 'Dùng trang riêng để Jackpot và lịch sử không làm dài trang trung tâm.', 'power-schedule-manager' ),
	),
	array(
		'title'      => 'Max 3D',
		'slug'       => '/xo-so-max-3d/',
		'shortcodes' => array(
			'power_schedule_lottery_max3d_plus',
			'power_schedule_lottery_max3d_pro',
		),
		'note'       => __( 'Tạo hai tab Max 3D+ – Max 3D Pro và đặt đúng shortcode vào từng tab.', 'power-schedule-manager' ),
	),
	array(
		'title'      => 'Keno',
		'slug'       => '/xo-so-keno/',
		'shortcodes' => array(
			'power_schedule_lottery_keno',
			'power_schedule_lottery_keno_history',
		),
		'note'       => __( 'Giữ Keno ở trang riêng vì số kỳ và dữ liệu lịch sử dài.', 'power-schedule-manager' ),
	),
	array(
		'title'      => __( 'Xổ số Điện toán', 'power-schedule-manager' ),
		'slug'       => '/xo-so-dien-toan/',
		'shortcodes' => array(
			'power_schedule_lottery_dientoan123',
			'power_schedule_lottery_dientoan6x36',
			'power_schedule_lottery_thantai',
		),
		'note'       => __( 'Tạo ba tab 123 – 6x36 – Thần Tài và đặt đúng shortcode vào từng tab.', 'power-schedule-manager' ),
	),
	array(
		'title'      => __( 'Tra cứu kết quả cũ', 'power-schedule-manager' ),
		'slug'       => '/tra-cuu-ket-qua-xo-so/',
		'shortcodes' => array( 'power_schedule_lottery_archive' ),
		'note'       => __( 'Form cho phép chọn ngày và sản phẩm, sau đó chỉ tải đúng bảng kết quả cần xem.', 'power-schedule-manager' ),
	),
);
?>
<div class="wrap psm-admin-wrap psm-lottery-admin">
	<div class="psm-lottery-admin__hero">
		<div>
			<span class="psm-lottery-admin__eyebrow"><?php esc_html_e( 'XoSoAPI · dữ liệu cục bộ', 'power-schedule-manager' ); ?></span>
			<h1><?php esc_html_e( 'Quản lý kết quả xổ số', 'power-schedule-manager' ); ?></h1>
			<p><?php esc_html_e( 'Theo dõi đầy đủ xổ số ba miền, Vietlott và Điện toán trong một màn hình.', 'power-schedule-manager' ); ?></p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="psm_refresh_lottery">
			<?php wp_nonce_field( 'psm_refresh_lottery', '_psm_nonce' ); ?>
			<button type="submit" class="button button-primary psm-lottery-admin__refresh">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Cập nhật kết quả ngay', 'power-schedule-manager' ); ?>
			</button>
		</form>
	</div>

	<?php if ( in_array( $notice, array( 'settings_saved', 'refreshed' ), true ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Cấu hình hoặc dữ liệu xổ số đã được cập nhật.', 'power-schedule-manager' ); ?></p></div>
	<?php elseif ( 'api_error' === $notice ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Chưa thể đồng bộ. Dữ liệu đã lưu không bị mất; hãy kiểm tra API key, hạn mức và kết nối HTTPS.', 'power-schedule-manager' ); ?></p></div>
	<?php elseif ( 'invalid' === $notice ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Cấu hình không hợp lệ.', 'power-schedule-manager' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper psm-admin-page-nav" aria-label="<?php esc_attr_e( 'Điều hướng quản trị xổ số', 'power-schedule-manager' ); ?>">
		<a class="nav-tab <?php echo 'results' === $admin_section ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'section', 'results', $admin_page_url ) ); ?>"><?php esc_html_e( 'Kết quả đã lưu', 'power-schedule-manager' ); ?></a>
		<a class="nav-tab" href="<?php echo esc_url( add_query_arg( array( 'page' => Power_Schedule_Manager_Admin::DATA_SOURCES_SLUG, 'settings_tab' => 'lottery' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Cấu hình API', 'power-schedule-manager' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
		<a class="nav-tab <?php echo 'more' === $admin_section ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'section', 'more', $admin_page_url ) ); ?>"><?php esc_html_e( 'Hướng dẫn và shortcode', 'power-schedule-manager' ); ?></a>
	</nav>

	<?php if ( 'results' === $admin_section ) : ?>
	<div class="psm-lottery-admin__kpis">
		<article><span><?php esc_html_e( 'Bản ghi đã lưu', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $overview['total'] ); ?></strong></article>
		<article><span><?php esc_html_e( 'Nhóm sản phẩm', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $overview['products'] ); ?></strong></article>
		<article><span><?php esc_html_e( 'Đang cập nhật', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $overview['updating'] ); ?></strong></article>
		<article><span><?php esc_html_e( 'Gói Cơ Bản', 'power-schedule-manager' ); ?></span><strong>2.000/ngày</strong><small><?php esc_html_e( 'Hạn 29/01/2027', 'power-schedule-manager' ); ?></small></article>
		<article><span><?php esc_html_e( 'Lần đồng bộ gần nhất', 'power-schedule-manager' ); ?></span><strong class="is-date"><?php echo esc_html( $overview['last_success'] ?: '—' ); ?></strong></article>
	</div>
	<?php if ( ! empty( $settings['last_endpoint_report'] ) && is_array( $settings['last_endpoint_report'] ) ) : ?>
		<details class="psm-admin-details">
			<summary><?php esc_html_e( 'Trạng thái từng endpoint XoSoAPI', 'power-schedule-manager' ); ?></summary>
			<div class="psm-admin-details__body psm-provider-status">
				<?php foreach ( $settings['last_endpoint_report'] as $endpoint => $endpoint_result ) : ?>
					<?php
					if ( ! is_array( $endpoint_result ) ) {
						continue;
					}
					$endpoint_ok = ! empty( $endpoint_result['ok'] );
					?>
					<div class="psm-provider-status__item <?php echo $endpoint_ok ? 'is-ok' : 'is-error'; ?>">
						<strong><?php echo esc_html( ucfirst( (string) $endpoint ) ); ?></strong>
						<span><?php echo esc_html( (string) absint( $endpoint_result['count'] ?? 0 ) ); ?> <?php esc_html_e( 'kỳ đã đọc', 'power-schedule-manager' ); ?></span>
						<code><?php echo esc_html( (string) ( $endpoint_result['code'] ?? '' ) ); ?></code>
					</div>
				<?php endforeach; ?>
			</div>
		</details>
	<?php endif; ?>

	<section id="psm-lottery-results" class="psm-dashboard-panel psm-lottery-admin__results">
		<div class="psm-lottery-admin__section-head">
			<div>
				<h2><?php esc_html_e( 'Kết quả đã lưu theo từng sản phẩm', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Danh sách được cân bằng để Keno không che khuất kết quả của các sản phẩm khác.', 'power-schedule-manager' ); ?></p>
			</div>
			<span class="psm-lottery-admin__count"><?php echo esc_html( (string) count( $rows ) ); ?> <?php esc_html_e( 'mục', 'power-schedule-manager' ); ?></span>
		</div>
		<?php if ( array() === $rows ) : ?>
			<div class="psm-lottery-admin__empty"><?php esc_html_e( 'Chưa có dữ liệu. Hãy cấu hình khóa và chạy cập nhật đầu tiên.', 'power-schedule-manager' ); ?></div>
		<?php else : ?>
			<div class="psm-admin-table-wrap">
				<table class="widefat psm-lottery-admin__table">
					<thead><tr>
						<th><?php esc_html_e( 'Sản phẩm', 'power-schedule-manager' ); ?></th>
						<th><?php esc_html_e( 'Ngày / kỳ', 'power-schedule-manager' ); ?></th>
						<th><?php esc_html_e( 'Miền · tỉnh/đài', 'power-schedule-manager' ); ?></th>
						<th><?php esc_html_e( 'Kết quả chính', 'power-schedule-manager' ); ?></th>
						<th><?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?></th>
						<th><?php esc_html_e( 'Nhận dữ liệu', 'power-schedule-manager' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row['game_label'] ); ?></strong></td>
							<td>
								<strong><?php echo esc_html( $row['draw_date'] ); ?></strong>
								<?php if ( '' !== $row['provider_draw_id'] ) : ?>
									<small>#<?php echo esc_html( $row['provider_draw_id'] ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $region_labels[ $row['region'] ] ?? '—' ); ?>
								<small><?php echo esc_html( $row['province_name'] ?: $row['province_code'] ); ?></small>
							</td>
							<td class="psm-lottery-admin__result"><?php echo esc_html( $row['result_summary'] ); ?></td>
							<td><span class="psm-lottery-admin__status is-<?php echo esc_attr( $row['display_status'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span></td>
							<td><?php echo esc_html( $row['fetched_display'] ); ?><small><?php esc_html_e( 'Giờ Việt Nam', 'power-schedule-manager' ); ?></small></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php if ( isset( $admin_pages ) && $admin_pages > 1 ) : ?>
			<nav class="psm-admin-pagination" aria-label="<?php esc_attr_e( 'Chuyển trang kết quả đã lưu', 'power-schedule-manager' ); ?>">
				<span class="psm-admin-pagination__summary">
					<?php
					printf(
						/* translators: 1: first row, 2: last row, 3: total rows. */
						esc_html__( 'Đang xem %1$d–%2$d trong %3$d kết quả', 'power-schedule-manager' ),
						( $admin_page - 1 ) * $admin_per_page + 1,
						min( $admin_page * $admin_per_page, count( $all_rows ) ),
						count( $all_rows )
					);
					?>
				</span>
				<div class="psm-admin-pagination__links">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => $lottery_pagination_base,
							'format'    => '',
							'current'   => $admin_page,
							'total'     => $admin_pages,
							'prev_text' => __( 'Trước', 'power-schedule-manager' ),
							'next_text' => __( 'Sau', 'power-schedule-manager' ),
						)
					)
				);
				?>
				</div>
			</nav>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( in_array( $admin_section, array( 'settings', 'more' ), true ) ) : ?>
	<form id="psm-lottery-settings" class="psm-lottery-admin__settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_save_lottery_settings">
		<?php wp_nonce_field( 'psm_save_lottery_settings', '_psm_nonce' ); ?>
		<?php if ( 'more' === $admin_section ) : ?>
			<?php if ( ! empty( $settings['enabled'] ) ) : ?><input type="hidden" name="enabled" value="1"><?php endif; ?>
			<input type="hidden" name="default_region" value="<?php echo esc_attr( (string) $settings['default_region'] ); ?>">
		<?php endif; ?>
		<?php if ( 'settings' === $admin_section ) : ?>
		<section class="psm-dashboard-panel">
			<div class="psm-lottery-admin__section-head">
				<div>
					<h2><?php esc_html_e( 'Kết nối và tự động cập nhật', 'power-schedule-manager' ); ?></h2>
					<p><?php esc_html_e( 'Các thay đổi chỉ có hiệu lực sau khi bấm Lưu cấu hình.', 'power-schedule-manager' ); ?></p>
				</div>
				<span class="psm-lottery-admin__connection <?php echo 'missing' === $settings['api_key_source'] ? 'is-off' : 'is-on'; ?>">
					<?php echo 'missing' === $settings['api_key_source'] ? esc_html__( 'Chưa kết nối', 'power-schedule-manager' ) : esc_html__( 'Đã kết nối', 'power-schedule-manager' ); ?>
				</span>
			</div>
			<label class="psm-admin-switch">
				<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
				<span class="psm-admin-switch__track" aria-hidden="true"><span></span></span>
				<span><strong><?php esc_html_e( 'Tự động đồng bộ mỗi 15 phút', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Ước tính tối đa 384 request/ngày, phù hợp gói Cơ Bản 2.000 request/ngày.', 'power-schedule-manager' ); ?></small></span>
			</label>
			<div class="psm-lottery-admin__fields">
				<div>
					<label for="psm-lottery-api-key"><strong><?php esc_html_e( 'Khóa truy cập XoSoAPI', 'power-schedule-manager' ); ?></strong></label>
					<?php if ( 'environment' === $settings['api_key_source'] ) : ?>
						<p class="psm-lottery-admin__info"><?php esc_html_e( 'Khóa đang được quản lý an toàn bằng Docker Secret/Coolify.', 'power-schedule-manager' ); ?></p>
					<?php else : ?>
						<input id="psm-lottery-api-key" type="password" class="regular-text" name="api_key" autocomplete="new-password" placeholder="<?php echo 'missing' === $settings['api_key_source'] ? esc_attr__( 'Nhập khóa API', 'power-schedule-manager' ) : esc_attr__( 'Đã lưu — để trống để giữ nguyên', 'power-schedule-manager' ); ?>">
					<?php endif; ?>
				</div>
				<div>
					<label for="psm-lottery-region"><strong><?php esc_html_e( 'Miền mặc định', 'power-schedule-manager' ); ?></strong></label>
					<select id="psm-lottery-region" name="default_region">
						<option value=""><?php esc_html_e( 'Toàn quốc', 'power-schedule-manager' ); ?></option>
						<option value="north" <?php selected( $settings['default_region'], 'north' ); ?>><?php esc_html_e( 'Miền Bắc', 'power-schedule-manager' ); ?></option>
						<option value="central" <?php selected( $settings['default_region'], 'central' ); ?>><?php esc_html_e( 'Miền Trung', 'power-schedule-manager' ); ?></option>
						<option value="south" <?php selected( $settings['default_region'], 'south' ); ?>><?php esc_html_e( 'Miền Nam', 'power-schedule-manager' ); ?></option>
					</select>
				</div>
			</div>
			<?php if ( 'admin' === $settings['api_key_source'] ) : ?>
				<label class="psm-admin-check-card is-danger"><input type="checkbox" name="clear_api_key" value="1"><span><strong><?php esc_html_e( 'Xóa API key đã lưu', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Chỉ chọn khi bạn thực sự muốn ngắt kết nối.', 'power-schedule-manager' ); ?></small></span></label>
			<?php endif; ?>
			<div class="psm-lottery-admin__save">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu cấu hình', 'power-schedule-manager' ); ?></button>
				<span><?php esc_html_e( 'Số kỳ nhận gần nhất:', 'power-schedule-manager' ); ?> <strong><?php echo esc_html( (string) $settings['last_count'] ); ?></strong></span>
				<?php if ( '' !== $settings['last_error_code'] ) : ?>
					<span class="is-error"><?php esc_html_e( 'Lỗi gần nhất:', 'power-schedule-manager' ); ?> <code><?php echo esc_html( $settings['last_error_code'] ); ?></code></span>
				<?php endif; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( 'more' === $admin_section ) : ?>
		<div id="psm-lottery-more" class="psm-lottery-admin__details">
			<details>
				<summary><span><strong><?php esc_html_e( 'Webhook và bảo mật kết nối', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'URL, secret và tùy chọn tạo lại secret', 'power-schedule-manager' ); ?></small></span></summary>
				<div class="psm-lottery-admin__details-body">
					<?php if ( 'environment' === $settings['webhook_secret_source'] ) : ?>
						<p class="psm-lottery-admin__info"><?php esc_html_e( 'Webhook secret đang được quản lý bằng Docker Secret/Coolify.', 'power-schedule-manager' ); ?></p>
					<?php elseif ( '' !== $webhook_secret_display ) : ?>
						<label for="psm-lottery-webhook-secret"><strong><?php esc_html_e( 'Secret key', 'power-schedule-manager' ); ?></strong></label>
						<span class="psm-copy-field">
							<input id="psm-lottery-webhook-secret" type="password" class="regular-text code" value="<?php echo esc_attr( $webhook_secret_display ); ?>" autocomplete="off" readonly>
							<button type="button" class="button" data-psm-copy-shortcode data-copy-target="psm-lottery-webhook-secret"><?php esc_html_e( 'Sao chép', 'power-schedule-manager' ); ?></button>
						</span>
						<label class="psm-admin-check-card is-warning"><input type="checkbox" name="rotate_webhook_secret" value="1"><span><strong><?php esc_html_e( 'Tạo secret mới khi lưu', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Secret cũ sẽ hết hiệu lực ngay.', 'power-schedule-manager' ); ?></small></span></label>
					<?php else : ?>
						<p><?php esc_html_e( 'Secret sẽ được tạo tự động khi lưu cấu hình.', 'power-schedule-manager' ); ?></p>
					<?php endif; ?>
					<label for="psm-lottery-webhook-url"><strong><?php esc_html_e( 'Webhook URL', 'power-schedule-manager' ); ?></strong></label>
					<span class="psm-copy-field">
						<input id="psm-lottery-webhook-url" type="url" class="regular-text code" value="<?php echo esc_attr( $webhook_url ); ?>" readonly>
						<button type="button" class="button" data-psm-copy-shortcode data-copy-target="psm-lottery-webhook-url"><?php esc_html_e( 'Sao chép', 'power-schedule-manager' ); ?></button>
					</span>
					<p class="description"><?php esc_html_e( 'XoSoAPI gửi POST với chữ ký HMAC-SHA256 trong header X-Webhook-Signature.', 'power-schedule-manager' ); ?></p>
				</div>
			</details>

			<details>
				<summary><span><strong><?php esc_html_e( 'Cấu trúc page và thư viện shortcode', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Mẫu triển khai Hub, trang sản phẩm và trang tra cứu', 'power-schedule-manager' ); ?></small></span></summary>
				<div class="psm-lottery-admin__details-body">
					<p class="psm-lottery-admin__info"><?php esc_html_e( 'Chế độ Hub là mặc định và được khuyên dùng: trang trung tâm chỉ tóm tắt dữ liệu, còn bảng đầy đủ nằm trên từng page riêng. Chế độ full vẫn được giữ để tương thích với page cũ.', 'power-schedule-manager' ); ?></p>
					<section class="psm-lottery-page-blueprint" aria-labelledby="psm-lottery-page-blueprint-title">
						<header>
							<h3 id="psm-lottery-page-blueprint-title"><?php esc_html_e( 'Cấu trúc page đề xuất', 'power-schedule-manager' ); ?></h3>
							<p><?php esc_html_e( 'Tạo page theo slug gợi ý, sau đó sao chép shortcode tương ứng. Với page có tab, dùng thành phần Tabs của theme hoặc trình dựng trang.', 'power-schedule-manager' ); ?></p>
						</header>
						<div class="psm-lottery-page-blueprint__grid">
							<?php foreach ( $page_blueprint as $page_index => $page_item ) : ?>
								<article>
									<div>
										<span><?php echo esc_html( sprintf( __( 'Page %d', 'power-schedule-manager' ), $page_index + 1 ) ); ?></span>
										<h4><?php echo esc_html( $page_item['title'] ); ?></h4>
										<code><?php echo esc_html( $page_item['slug'] ); ?></code>
									</div>
									<p><?php echo esc_html( $page_item['note'] ); ?></p>
									<div class="psm-lottery-page-blueprint__shortcodes">
										<?php foreach ( $page_item['shortcodes'] as $page_shortcode_index => $page_shortcode ) : ?>
											<?php $page_shortcode_id = 'psm-lottery-page-shortcode-' . $page_index . '-' . $page_shortcode_index; ?>
											<div class="psm-lottery-shortcode-card">
												<input id="<?php echo esc_attr( $page_shortcode_id ); ?>" class="code" type="text" value="[<?php echo esc_attr( $page_shortcode ); ?>]" readonly>
												<button type="button" class="button button-small" data-psm-copy-shortcode data-copy-target="<?php echo esc_attr( $page_shortcode_id ); ?>"><?php esc_html_e( 'Sao chép', 'power-schedule-manager' ); ?></button>
											</div>
										<?php endforeach; ?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
					<h3><?php esc_html_e( 'Toàn bộ shortcode', 'power-schedule-manager' ); ?></h3>
					<?php foreach ( $shortcode_groups as $group_label => $shortcodes ) : ?>
						<h3><?php echo esc_html( $group_label ); ?></h3>
						<div class="psm-lottery-shortcode-grid">
							<?php foreach ( $shortcodes as $shortcode_index => $shortcode ) : ?>
								<?php $shortcode_id = 'psm-lottery-shortcode-' . substr( md5( $group_label ), 0, 8 ) . '-' . $shortcode_index; ?>
								<div class="psm-lottery-shortcode-card">
									<input id="<?php echo esc_attr( $shortcode_id ); ?>" class="code" type="text" value="[<?php echo esc_attr( $shortcode ); ?>]" readonly>
									<button type="button" class="button button-small" data-psm-copy-shortcode data-copy-target="<?php echo esc_attr( $shortcode_id ); ?>"><?php esc_html_e( 'Sao chép', 'power-schedule-manager' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</details>

			<details>
				<summary><span><strong><?php esc_html_e( 'Ghi chú triển khai', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Thông tin dành cho máy chủ production', 'power-schedule-manager' ); ?></small></span></summary>
				<div class="psm-lottery-admin__details-body">
					<p><?php esc_html_e( 'Nhập API key tại trang Nguồn dữ liệu. Plugin mã hóa khóa bằng WordPress salts, không hiển thị lại và không đưa khóa vào image, ZIP, Git hoặc bản backup plugin.', 'power-schedule-manager' ); ?></p>
				</div>
			</details>
		</div>
		<div class="psm-lottery-admin__save">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu thay đổi bảo mật', 'power-schedule-manager' ); ?></button>
		</div>
		<?php endif; ?>
	</form>
	<?php endif; ?>
</div>
