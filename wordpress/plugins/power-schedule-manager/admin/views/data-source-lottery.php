<?php
/**
 * Lottery integration settings panel.
 *
 * @package Power_Schedule_Manager
 *
 * @var array<string,mixed> $settings
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="psm-dashboard-panel psm-data-source-panel" data-psm-settings-panel="lottery">
	<header class="psm-data-source-panel__header">
		<div>
			<div>
				<h2><?php esc_html_e( 'Xổ số — XoSoAPI', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Đồng bộ xổ số ba miền, Vietlott, Keno và Điện toán về cơ sở dữ liệu WordPress.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>
		<span class="psm-lottery-admin__connection <?php echo 'missing' === $settings['api_key_source'] ? 'is-off' : 'is-on'; ?>">
			<?php echo 'missing' === $settings['api_key_source'] ? esc_html__( 'Chưa kết nối', 'power-schedule-manager' ) : esc_html__( 'Đã kết nối', 'power-schedule-manager' ); ?>
		</span>
	</header>

	<form class="psm-data-source-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_save_lottery_settings">
		<input type="hidden" name="settings_location" value="data-sources">
		<?php wp_nonce_field( 'psm_save_lottery_settings', '_psm_nonce' ); ?>

		<label class="psm-admin-switch">
			<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
			<span class="psm-admin-switch__track" aria-hidden="true"><span></span></span>
			<span>
				<strong><?php esc_html_e( 'Tự động đồng bộ mỗi 15 phút', 'power-schedule-manager' ); ?></strong>
				<small><?php esc_html_e( 'Chỉ gọi API ở máy chủ; frontend luôn đọc dữ liệu đã lưu.', 'power-schedule-manager' ); ?></small>
			</span>
		</label>

		<div class="psm-data-source-form__grid">
			<div>
				<label for="psm-source-lottery-key"><strong><?php esc_html_e( 'Khóa truy cập XoSoAPI', 'power-schedule-manager' ); ?></strong></label>
				<?php if ( 'environment' === $settings['api_key_source'] ) : ?>
					<p class="psm-lottery-admin__info"><?php esc_html_e( 'Khóa đang được quản lý bằng Docker Secret hoặc biến môi trường.', 'power-schedule-manager' ); ?></p>
				<?php else : ?>
					<input id="psm-source-lottery-key" type="password" class="regular-text" name="api_key" autocomplete="new-password" placeholder="<?php echo 'missing' === $settings['api_key_source'] ? esc_attr__( 'Nhập khóa API', 'power-schedule-manager' ) : esc_attr__( 'Đã lưu — để trống để giữ nguyên', 'power-schedule-manager' ); ?>">
				<?php endif; ?>
			</div>
			<div>
				<label for="psm-source-lottery-region"><strong><?php esc_html_e( 'Miền mặc định', 'power-schedule-manager' ); ?></strong></label>
				<select id="psm-source-lottery-region" name="default_region">
					<option value=""><?php esc_html_e( 'Toàn quốc', 'power-schedule-manager' ); ?></option>
					<option value="north" <?php selected( $settings['default_region'], 'north' ); ?>><?php esc_html_e( 'Miền Bắc', 'power-schedule-manager' ); ?></option>
					<option value="central" <?php selected( $settings['default_region'], 'central' ); ?>><?php esc_html_e( 'Miền Trung', 'power-schedule-manager' ); ?></option>
					<option value="south" <?php selected( $settings['default_region'], 'south' ); ?>><?php esc_html_e( 'Miền Nam', 'power-schedule-manager' ); ?></option>
				</select>
			</div>
		</div>

		<?php if ( 'admin' === $settings['api_key_source'] ) : ?>
			<label class="psm-admin-check-card is-danger">
				<input type="checkbox" name="clear_api_key" value="1">
				<span><strong><?php esc_html_e( 'Xóa API key đã lưu', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Lịch tự động sẽ dừng nhận dữ liệu mới.', 'power-schedule-manager' ); ?></small></span>
			</label>
		<?php endif; ?>

		<footer class="psm-data-source-form__actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu kết nối xổ số', 'power-schedule-manager' ); ?></button>
			<span><?php esc_html_e( 'Số kỳ lần gần nhất:', 'power-schedule-manager' ); ?> <strong><?php echo esc_html( (string) $settings['last_count'] ); ?></strong></span>
			<?php if ( '' !== (string) $settings['last_error_code'] ) : ?>
				<code><?php echo esc_html( (string) $settings['last_error_code'] ); ?></code>
			<?php endif; ?>
		</footer>
	</form>
</section>
