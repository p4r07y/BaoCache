<?php
/**
 * Central external-data settings screen.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$notice = isset( $_GET['psm_notice'] ) && is_scalar( $_GET['psm_notice'] )
	? sanitize_key( wp_unslash( (string) $_GET['psm_notice'] ) )
	: '';
?>
<div class="wrap psm-admin-wrap psm-data-sources-admin">
	<header class="psm-admin-page-hero">
		<div>
			<span class="psm-admin-page-hero__eyebrow"><?php esc_html_e( 'Cài đặt tích hợp', 'power-schedule-manager' ); ?></span>
			<h1><?php esc_html_e( 'Nguồn dữ liệu và API', 'power-schedule-manager' ); ?></h1>
			<p><?php esc_html_e( 'Quản lý kết nối xổ số, giá vàng, tỷ giá và cà phê ở một nơi. Các trang dữ liệu chỉ còn tập trung vào nhập liệu, đồng bộ và kiểm tra kết quả.', 'power-schedule-manager' ); ?></p>
		</div>
	</header>

	<?php if ( in_array( $notice, array( 'settings_saved', 'refreshed', 'gold_refreshed', 'api_saved', 'api_stats_reset' ), true ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Cấu hình nguồn dữ liệu đã được cập nhật.', 'power-schedule-manager' ); ?></p></div>
	<?php elseif ( 'api_partial' === $notice ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Nguồn chính đã cập nhật thành công, nhưng một nguồn phụ đang lỗi. Xem trạng thái từng nguồn bên dưới để xử lý đúng khóa hoặc hạn mức.', 'power-schedule-manager' ); ?></p></div>
	<?php elseif ( in_array( $notice, array( 'invalid', 'api_error', 'gold_api_error' ), true ) ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Nguồn đang kiểm tra chưa kết nối được. Cấu hình và dữ liệu cũ vẫn được giữ nguyên; xem trạng thái chi tiết bên dưới.', 'power-schedule-manager' ); ?></p></div>
	<?php endif; ?>

	<nav class="psm-settings-tabs" aria-label="<?php esc_attr_e( 'Nhóm nguồn dữ liệu', 'power-schedule-manager' ); ?>" data-psm-settings-tabs>
		<button type="button" class="is-active" data-psm-settings-tab="lottery"><?php esc_html_e( 'Xổ số', 'power-schedule-manager' ); ?></button>
		<button type="button" data-psm-settings-tab="gold"><?php esc_html_e( 'Giá vàng & tỷ giá', 'power-schedule-manager' ); ?></button>
		<button type="button" data-psm-settings-tab="coffee"><?php esc_html_e( 'Cà phê', 'power-schedule-manager' ); ?></button>
		<button type="button" data-psm-settings-tab="application-api"><?php esc_html_e( 'API ứng dụng & bảo mật', 'power-schedule-manager' ); ?></button>
	</nav>

	<div class="psm-data-sources-admin__panels">
		<?php do_action( 'power_schedule_manager_render_data_source_settings' ); ?>
		<?php require POWER_SCHEDULE_MANAGER_PATH . 'admin/views/data-source-application-api.php'; ?>
	</div>
</div>
