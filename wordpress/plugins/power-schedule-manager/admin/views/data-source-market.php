<?php
/**
 * Market data-source settings panels.
 *
 * @package Power_Schedule_Manager
 *
 * @var array<string,mixed> $market_settings
 */

defined( 'ABSPATH' ) || exit;

$market_report = isset( $market_settings['last_refresh_report'] )
	&& is_array( $market_settings['last_refresh_report'] )
		? $market_settings['last_refresh_report']
		: array();
$market_status_labels = array(
	'gold_domestic'  => __( 'Vàng trong nước · Giavang.now', 'power-schedule-manager' ),
	'gold_world'     => __( 'Vàng thế giới · Gold API', 'power-schedule-manager' ),
	'gold_primary'   => __( 'Vàng và tỷ giá · WiFeed', 'power-schedule-manager' ),
	'exchange_rates' => __( 'Tỷ giá tham chiếu · WiFeed', 'power-schedule-manager' ),
	'coffee_world'   => __( 'Cà phê thế giới · Commodities-API', 'power-schedule-manager' ),
);
$market_error_labels = array(
	'market_provider_unavailable'      => __( 'Máy chủ WordPress không kết nối được nhà cung cấp', 'power-schedule-manager' ),
	'market_provider_auth_failed'      => __( 'Khóa API bị từ chối', 'power-schedule-manager' ),
	'market_provider_rate_limited'     => __( 'Đã chạm hạn mức gọi API', 'power-schedule-manager' ),
	'market_provider_invalid_http'     => __( 'Nhà cung cấp trả về lỗi HTTP', 'power-schedule-manager' ),
	'market_provider_invalid_json'     => __( 'Phản hồi API không đúng định dạng', 'power-schedule-manager' ),
	'wifeed_production_key_required'   => __( 'Cần WiFeed API key hợp lệ', 'power-schedule-manager' ),
	'commodities_api_key_required'     => __( 'Chưa có Commodities-API key', 'power-schedule-manager' ),
	'commodities_api_invalid_response' => __( 'Commodities-API từ chối yêu cầu hoặc gói chưa hỗ trợ mã hàng', 'power-schedule-manager' ),
	'commodities_api_no_valid_rows'    => __( 'API không trả về giá ROBUSTA/COFFEE hợp lệ', 'power-schedule-manager' ),
);
$render_market_status = static function ( array $services ) use (
	$market_report,
	$market_status_labels,
	$market_error_labels
): void {
	$rows = array_intersect_key( $market_report, array_flip( $services ) );
	if ( array() === $rows ) {
		return;
	}
	?>
	<div class="psm-source-health" aria-label="<?php esc_attr_e( 'Trạng thái kết nối gần nhất', 'power-schedule-manager' ); ?>">
		<strong class="psm-source-health__title"><?php esc_html_e( 'Kết quả kiểm tra gần nhất', 'power-schedule-manager' ); ?></strong>
		<div class="psm-source-health__grid">
			<?php foreach ( $rows as $service => $row ) : ?>
				<?php
				$ok = ! empty( $row['ok'] );
				$code = isset( $row['code'] ) ? sanitize_key( (string) $row['code'] ) : '';
				$detail = $ok
					? __( 'Kết nối tốt', 'power-schedule-manager' )
					: ( $market_error_labels[ $code ] ?? sprintf(
						/* translators: %s: safe provider error code. */
						__( 'Lỗi nhà cung cấp: %s', 'power-schedule-manager' ),
						$code
					) );
				if ( str_starts_with( $code, 'commodities_api_' ) && ! isset( $market_error_labels[ $code ] ) ) {
					$detail = sprintf(
						/* translators: %s: Commodities-API error code. */
						__( 'Commodities-API từ chối yêu cầu (mã %s)', 'power-schedule-manager' ),
						substr( $code, strlen( 'commodities_api_' ) )
					);
				}
				?>
				<div class="psm-source-health__item <?php echo $ok ? 'is-ok' : 'is-error'; ?>">
					<span class="psm-source-health__dot" aria-hidden="true"></span>
					<span><strong><?php echo esc_html( $market_status_labels[ $service ] ?? $service ); ?></strong><small><?php echo esc_html( $detail ); ?></small></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
};
?>
<section class="psm-dashboard-panel psm-data-source-panel psm-data-source-panel--gold" data-psm-settings-panel="gold">
	<header class="psm-data-source-panel__header">
		<div>
			<h2><?php esc_html_e( 'Giá vàng và tỷ giá', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Giavang.now cung cấp bảng trong nước, Gold API cung cấp XAU/USD. WiFeed chỉ cần khi dùng tỷ giá hoặc chọn làm nguồn thay thế.', 'power-schedule-manager' ); ?></p>
		</div>
		<span class="psm-data-source-panel__badge"><?php esc_html_e( 'Kết nối từ máy chủ', 'power-schedule-manager' ); ?></span>
	</header>
	<?php $render_market_status( array( 'gold_domestic', 'gold_world', 'gold_primary', 'exchange_rates' ) ); ?>

	<form class="psm-data-source-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_save_world_gold_settings">
		<input type="hidden" name="return_page" value="data_sources_gold">
		<input type="hidden" name="fx_provider" value="wifeed">
		<?php wp_nonce_field( 'psm_save_world_gold_settings', '_psm_nonce' ); ?>

		<div class="psm-data-source-form__grid">
			<div>
				<label for="psm-source-gold-provider"><strong><?php esc_html_e( 'Nguồn vàng chính', 'power-schedule-manager' ); ?></strong></label>
				<select id="psm-source-gold-provider" name="gold_provider">
					<option value="vang_today" <?php selected( $market_settings['gold_provider'], 'vang_today' ); ?>><?php esc_html_e( 'Giavang.now + Gold API — khuyên dùng', 'power-schedule-manager' ); ?></option>
					<option value="wifeed" <?php selected( $market_settings['gold_provider'], 'wifeed' ); ?>><?php esc_html_e( 'WiFeed — trong nước, thế giới và tỷ giá', 'power-schedule-manager' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'VNAppMob đã được loại khỏi luồng vàng mới vì trùng dữ liệu và cần thêm khóa riêng. Dữ liệu cũ vẫn được giữ nguyên.', 'power-schedule-manager' ); ?></p>
			</div>
			<div>
				<label for="psm-source-wifeed-key"><strong><?php esc_html_e( 'WiFeed API key', 'power-schedule-manager' ); ?></strong></label>
				<input id="psm-source-wifeed-key" type="password" name="wifeed_api_key" autocomplete="new-password" class="regular-text" placeholder="<?php echo 'missing' === $market_settings['wifeed_key_source'] ? esc_attr__( 'Chỉ cần khi chọn WiFeed', 'power-schedule-manager' ) : esc_attr__( 'Đã lưu — để trống để giữ nguyên', 'power-schedule-manager' ); ?>">
				<?php if ( 'missing' !== $market_settings['wifeed_key_source'] ) : ?>
					<label class="psm-data-source-form__clear"><input type="checkbox" name="clear_wifeed_api_key" value="1"> <?php esc_html_e( 'Xóa khóa WiFeed đã lưu', 'power-schedule-manager' ); ?></label>
				<?php endif; ?>
			</div>
		</div>

		<div class="psm-data-source-form__toggles">
			<label><input type="checkbox" name="gold_api_enabled" value="1" <?php checked( ! empty( $market_settings['gold_api_enabled'] ) ); ?>> <span><strong><?php esc_html_e( 'Tự động cập nhật giá vàng', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Chu kỳ 5 phút, có khóa chống gọi trùng.', 'power-schedule-manager' ); ?></small></span></label>
			<label><input type="checkbox" name="fx_api_enabled" value="1" <?php checked( ! empty( $market_settings['fx_api_enabled'] ) ); ?>> <span><strong><?php esc_html_e( 'Cập nhật tỷ giá tham chiếu từ WiFeed', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Chu kỳ tối đa mỗi 30 phút và dùng chung khóa WiFeed.', 'power-schedule-manager' ); ?></small></span></label>
		</div>

		<footer class="psm-data-source-form__actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu nguồn giá vàng', 'power-schedule-manager' ); ?></button>
			<a href="https://giavang.now/api" target="_blank" rel="noopener external"><?php esc_html_e( 'Tài liệu Giavang.now', 'power-schedule-manager' ); ?></a>
			<a href="https://gold-api.com/docs" target="_blank" rel="noopener external"><?php esc_html_e( 'Tài liệu Gold API', 'power-schedule-manager' ); ?></a>
		</footer>
	</form>

	<form class="psm-data-source-refresh" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_refresh_world_gold">
		<input type="hidden" name="return_page" value="data_sources_gold">
		<?php wp_nonce_field( 'psm_refresh_world_gold', '_psm_nonce' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Kiểm tra và cập nhật ngay', 'power-schedule-manager' ); ?></button>
	</form>
</section>

<section class="psm-dashboard-panel psm-data-source-panel psm-data-source-panel--coffee" data-psm-settings-panel="coffee">
	<header class="psm-data-source-panel__header">
		<div>
			<h2><?php esc_html_e( 'Cà phê thế giới', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Commodities-API cung cấp mã ROBUSTA và COFFEE. Giá thu mua trong nước vẫn được biên tập từ nguồn có quyền sử dụng.', 'power-schedule-manager' ); ?></p>
		</div>
	</header>
	<?php $render_market_status( array( 'coffee_world' ) ); ?>

	<form class="psm-data-source-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_save_world_gold_settings">
		<input type="hidden" name="return_page" value="data_sources_coffee">
		<?php wp_nonce_field( 'psm_save_world_gold_settings', '_psm_nonce' ); ?>

		<label class="psm-admin-switch">
			<input type="checkbox" name="commodities_api_enabled" value="1" <?php checked( ! empty( $market_settings['commodities_api_enabled'] ) ); ?>>
			<span class="psm-admin-switch__track" aria-hidden="true"><span></span></span>
			<span><strong><?php esc_html_e( 'Tự động cập nhật cà phê thế giới', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Giới hạn tối đa một lần gọi mỗi 30 phút.', 'power-schedule-manager' ); ?></small></span>
		</label>

		<div class="psm-data-source-form__grid">
			<div>
				<label for="psm-source-commodities-key"><strong><?php esc_html_e( 'Commodities-API key', 'power-schedule-manager' ); ?></strong></label>
				<input id="psm-source-commodities-key" type="password" name="commodities_api_key" autocomplete="new-password" class="regular-text" placeholder="<?php echo 'missing' === $market_settings['commodities_key_source'] ? esc_attr__( 'Nhập API key', 'power-schedule-manager' ) : esc_attr__( 'Đã lưu — để trống để giữ nguyên', 'power-schedule-manager' ); ?>">
				<?php if ( 'missing' !== $market_settings['commodities_key_source'] ) : ?>
					<label class="psm-data-source-form__clear"><input type="checkbox" name="clear_commodities_api_key" value="1"> <?php esc_html_e( 'Xóa khóa đã lưu', 'power-schedule-manager' ); ?></label>
				<?php endif; ?>
			</div>
			<div class="psm-data-source-panel__note">
				<strong><?php esc_html_e( 'Dữ liệu nào không tự động?', 'power-schedule-manager' ); ?></strong>
				<p><?php esc_html_e( 'Giá cà phê Lâm Đồng và các tỉnh Tây Nguyên phụ thuộc chất lượng, đại lý và thời điểm chốt nên tiếp tục nhập từ nguồn có quyền sử dụng.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>

		<footer class="psm-data-source-form__actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu nguồn cà phê', 'power-schedule-manager' ); ?></button>
			<a href="https://www.commodities-api.com/documentation" target="_blank" rel="noopener external"><?php esc_html_e( 'Tài liệu Commodities-API', 'power-schedule-manager' ); ?></a>
		</footer>
	</form>

	<form class="psm-data-source-refresh" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_refresh_world_gold">
		<input type="hidden" name="return_page" value="data_sources_coffee">
		<?php wp_nonce_field( 'psm_refresh_world_gold', '_psm_nonce' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Kiểm tra nguồn cà phê ngay', 'power-schedule-manager' ); ?></button>
	</form>
</section>
