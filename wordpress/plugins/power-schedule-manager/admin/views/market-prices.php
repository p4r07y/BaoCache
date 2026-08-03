<?php
/**
 * Editorial market-price management.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$notice = isset( $_GET['psm_notice'] ) && is_scalar( $_GET['psm_notice'] )
	? sanitize_key( wp_unslash( (string) $_GET['psm_notice'] ) )
	: '';
$is_gold = isset( $page_kind ) && 'gold' === $page_kind;
$page_title = $is_gold
	? __( 'Giá vàng', 'power-schedule-manager' )
	: __( 'Giá nông sản và tỷ giá', 'power-schedule-manager' );
$page_label_lower = $is_gold
	? __( 'giá vàng', 'power-schedule-manager' )
	: __( 'giá thị trường', 'power-schedule-manager' );
$edit_row = isset( $edit_row ) && is_array( $edit_row )
	? $edit_row
	: array();
$editing = array() !== $edit_row;
$field = static function (
	string $key,
	string $default = ''
) use ( $edit_row ): string {
	$value = $edit_row[ $key ] ?? $default;

	return null === $value ? '' : (string) $value;
};
$page_url = admin_url(
	'admin.php?page=' . (
		$is_gold
			? 'power-schedule-manager-gold-prices'
			: 'power-schedule-manager-coffee-prices'
	)
);
if ( ! empty( $admin_market_filter ) ) {
	$page_url = add_query_arg(
		'market_filter',
		$admin_market_filter,
		$page_url
	);
}
$pagination_base = str_replace(
	'999999999',
	'%#%',
	add_query_arg( 'data_page', '999999999', $page_url )
);
$format_admin_price = static function ( mixed $value ): string {
	if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
		return '—';
	}
	$number = (float) $value;

	return number_format_i18n(
		$number,
		fmod( $number, 1.0 ) === 0.0 ? 0 : 2
	);
};
?>
<div class="wrap psm-admin-wrap psm-market-admin">
	<header class="psm-market-admin__hero">
		<div>
			<span class="psm-market-admin__eyebrow"><?php echo esc_html( $is_gold ? __( 'Dữ liệu giá kim loại', 'power-schedule-manager' ) : __( 'Dữ liệu nông sản', 'power-schedule-manager' ) ); ?></span>
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<p class="psm-admin-lead">
				<?php
				echo esc_html(
					$is_gold
						? __( 'Cập nhật giá mua, giá bán theo từng loại vàng và ngày niêm yết.', 'power-schedule-manager' )
						: __( 'Quản lý giá cà phê, hồ tiêu, tỷ giá tham chiếu và hợp đồng quốc tế trong một quy trình thống nhất.', 'power-schedule-manager' )
				);
				?>
			</p>
		</div>
		<div class="psm-market-admin__hero-stat"><span><?php esc_html_e( 'Dữ liệu đang quản lý', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( number_format_i18n( $admin_total ) ); ?></strong><small><?php esc_html_e( 'bản ghi theo bộ lọc hiện tại', 'power-schedule-manager' ); ?></small></div>
	</header>
	<nav class="nav-tab-wrapper psm-admin-page-nav psm-market-admin__nav" aria-label="<?php esc_attr_e( 'Điều hướng trang quản trị', 'power-schedule-manager' ); ?>">
		<a class="nav-tab" href="#psm-market-editor"><?php esc_html_e( 'Nhập dữ liệu', 'power-schedule-manager' ); ?></a>
		<a class="nav-tab" href="#psm-market-records"><?php esc_html_e( 'Dữ liệu đã lưu', 'power-schedule-manager' ); ?></a>
		<a class="nav-tab" href="#psm-market-automation"><?php esc_html_e( 'Shortcode & hướng dẫn', 'power-schedule-manager' ); ?></a>
		<a class="nav-tab" href="<?php echo esc_url( add_query_arg( array( 'page' => Power_Schedule_Manager_Admin::DATA_SOURCES_SLUG, 'settings_tab' => $is_gold ? 'gold' : 'coffee' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Nguồn dữ liệu & API', 'power-schedule-manager' ); ?></a>
	</nav>

	<?php if ( in_array( $notice, array( 'saved', 'deleted', 'settings_saved', 'gold_refreshed' ), true ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Dữ liệu đã được cập nhật.', 'power-schedule-manager' ); ?></p></div>
	<?php elseif ( 'gold_api_error' === $notice ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Chưa thể cập nhật nguồn dữ liệu tự động. Dữ liệu hiện có không bị thay đổi; hãy kiểm tra nhà cung cấp, API key, hạn mức và kết nối HTTPS.', 'power-schedule-manager' ); ?></p></div>
	<?php elseif ( '' !== $notice ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Chưa thể lưu. Hãy kiểm tra ngày, nhãn và giá trị.', 'power-schedule-manager' ); ?></p></div>
	<?php endif; ?>

	<div class="psm-admin-grid">
		<section id="psm-market-editor" class="psm-dashboard-panel">
			<h2>
				<?php
				echo esc_html(
					$editing
						? __( 'Sửa dòng giá', 'power-schedule-manager' )
						: __( 'Thêm hoặc cập nhật một dòng giá', 'power-schedule-manager' )
				);
				?>
			</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="psm_save_market_price">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="price_id" value="<?php echo esc_attr( $field( 'id' ) ); ?>">
				<?php endif; ?>
				<?php wp_nonce_field( 'psm_save_market_price', '_psm_nonce' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th><label for="psm-market"><?php esc_html_e( 'Bảng dữ liệu', 'power-schedule-manager' ); ?></label></th><td><select id="psm-market" name="market_code" required>
						<?php foreach ( $markets as $code => $config ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $field( 'market_code' ), $code ); ?>><?php echo esc_html( $config['title'] ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
					<tr><th><label for="psm-price-label"><?php esc_html_e( 'Nhãn dòng', 'power-schedule-manager' ); ?></label></th><td>
						<input id="psm-price-label" class="regular-text" name="label" maxlength="191" required list="psm-market-labels" value="<?php echo esc_attr( $field( 'label' ) ); ?>" placeholder="<?php esc_attr_e( 'Chọn gợi ý hoặc nhập nhãn chính xác', 'power-schedule-manager' ); ?>">
						<datalist id="psm-market-labels">
							<?php if ( $is_gold ) : ?>
								<option value="SJC"></option>
								<option value="DOJI"></option>
								<option value="Phú Quý"></option>
								<option value="BTMC"></option>
								<option value="PNJ"></option>
								<option value="XAU/USD"></option>
							<?php else : ?>
								<option value="Lâm Đồng"></option>
								<option value="Đắk Lắk"></option>
								<option value="Gia Lai"></option>
								<option value="Đắk Nông"></option>
								<option value="Bảo Lộc"></option>
								<option value="Di Linh"></option>
								<option value="Lâm Hà"></option>
								<option value="Đức Trọng"></option>
								<option value="Robusta London"></option>
								<option value="Arabica New York"></option>
								<option value="Arabica Brazil"></option>
								<option value="Hồ tiêu Lâm Đồng"></option>
								<option value="USD/VND"></option>
							<?php endif; ?>
						</datalist>
						<p class="description"><?php esc_html_e( 'Giữ nguyên một nhãn qua các ngày để plugin tạo đúng chuỗi lịch sử và tự tính thay đổi.', 'power-schedule-manager' ); ?></p>
					</td></tr>
					<tr><th><label for="psm-price-date"><?php esc_html_e( 'Ngày giá', 'power-schedule-manager' ); ?></label></th><td><input id="psm-price-date" type="date" name="price_date" required value="<?php echo esc_attr( $field( 'price_date', wp_date( 'Y-m-d' ) ) ); ?>"></td></tr>
					<tr><th><label for="psm-observed-at"><?php esc_html_e( 'Thời điểm ghi nhận', 'power-schedule-manager' ); ?></label></th><td><input id="psm-observed-at" type="datetime-local" name="observed_at" value="<?php echo esc_attr( $editing && '' !== $field( 'observed_at_utc' ) ? get_date_from_gmt( $field( 'observed_at_utc' ), 'Y-m-d\\TH:i' ) : wp_date( 'Y-m-d\\TH:i' ) ); ?>"><p class="description"><?php esc_html_e( 'Dùng cho giá trực tuyến; dữ liệu trong database được chuẩn hóa về UTC.', 'power-schedule-manager' ); ?></p></td></tr>
					<tr data-psm-market-group="general"><th><?php esc_html_e( 'Giá trị', 'power-schedule-manager' ); ?></th><td><div class="psm-field-grid">
						<label data-psm-price-field="price"><?php esc_html_e( 'Giá tham chiếu', 'power-schedule-manager' ); ?><input inputmode="decimal" name="price" value="<?php echo esc_attr( $field( 'price' ) ); ?>" placeholder="95800"></label>
						<label data-psm-price-field="buy"><?php esc_html_e( 'Giá mua', 'power-schedule-manager' ); ?><input inputmode="decimal" name="buy_price" value="<?php echo esc_attr( $field( 'buy_price' ) ); ?>" placeholder="137500000"></label>
						<label data-psm-price-field="sell"><?php esc_html_e( 'Giá bán', 'power-schedule-manager' ); ?><input inputmode="decimal" name="sell_price" value="<?php echo esc_attr( $field( 'sell_price' ) ); ?>" placeholder="141500000"></label>
						<label data-psm-price-field="change"><?php esc_html_e( 'Thay đổi', 'power-schedule-manager' ); ?><input inputmode="decimal" name="change_value" value="<?php echo esc_attr( $field( 'change_value' ) ); ?>" placeholder="<?php esc_attr_e( 'Tự tính nếu để trống', 'power-schedule-manager' ); ?>"></label>
					</div><p class="description"><?php esc_html_e( 'Nhập số không có dấu phân cách hàng nghìn. Ví dụ: 95800 hoặc 4051.70.', 'power-schedule-manager' ); ?></p></td></tr>
					<tr data-psm-market-group="futures" hidden><th><?php esc_html_e( 'Hợp đồng kỳ hạn', 'power-schedule-manager' ); ?></th><td>
						<div class="psm-field-grid">
							<label><?php esc_html_e( 'Kỳ hạn', 'power-schedule-manager' ); ?><input name="contract_code" maxlength="32" value="<?php echo esc_attr( $field( 'contract_code' ) ); ?>" placeholder="09/26"></label>
							<label><?php esc_html_e( 'Giá khớp', 'power-schedule-manager' ); ?><input inputmode="decimal" name="price" value="<?php echo esc_attr( $field( 'price' ) ); ?>"></label>
							<label><?php esc_html_e( 'Thay đổi', 'power-schedule-manager' ); ?><input inputmode="decimal" name="change_value" value="<?php echo esc_attr( $field( 'change_value' ) ); ?>"></label>
							<label><?php esc_html_e( 'Thay đổi (%)', 'power-schedule-manager' ); ?><input inputmode="decimal" name="change_percent" value="<?php echo esc_attr( $field( 'change_percent' ) ); ?>"></label>
							<label><?php esc_html_e( 'Cao nhất', 'power-schedule-manager' ); ?><input inputmode="decimal" name="high_price" value="<?php echo esc_attr( $field( 'high_price' ) ); ?>"></label>
							<label><?php esc_html_e( 'Thấp nhất', 'power-schedule-manager' ); ?><input inputmode="decimal" name="low_price" value="<?php echo esc_attr( $field( 'low_price' ) ); ?>"></label>
							<label><?php esc_html_e( 'Khối lượng', 'power-schedule-manager' ); ?><input type="number" min="0" name="volume" value="<?php echo esc_attr( $field( 'volume' ) ); ?>"></label>
							<label><?php esc_html_e( 'Mở cửa', 'power-schedule-manager' ); ?><input inputmode="decimal" name="open_price" value="<?php echo esc_attr( $field( 'open_price' ) ); ?>"></label>
							<label><?php esc_html_e( 'Hôm trước', 'power-schedule-manager' ); ?><input inputmode="decimal" name="previous_close" value="<?php echo esc_attr( $field( 'previous_close' ) ); ?>"></label>
							<label><?php esc_html_e( 'HĐ mở', 'power-schedule-manager' ); ?><input type="number" min="0" name="open_interest" value="<?php echo esc_attr( $field( 'open_interest' ) ); ?>"></label>
						</div>
						<p class="description"><?php esc_html_e( 'Mỗi kỳ hạn là một dòng riêng. Dữ liệu ICE phải đến từ feed/API có quyền sử dụng; plugin không thu thập tự động từ trang web bên thứ ba.', 'power-schedule-manager' ); ?></p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Đơn vị', 'power-schedule-manager' ); ?></th><td><input id="psm-market-unit" name="unit" maxlength="32" value="<?php echo esc_attr( $field( 'unit', $is_gold ? 'VND/lượng' : 'VND/kg' ) ); ?>"> <input id="psm-market-currency" name="currency" maxlength="8" value="<?php echo esc_attr( $field( 'currency', 'VND' ) ); ?>" size="8"></td></tr>
					<tr><th><?php esc_html_e( 'Nguồn', 'power-schedule-manager' ); ?></th><td><input class="regular-text" name="source_name" maxlength="191" value="<?php echo esc_attr( $field( 'source_name' ) ); ?>" placeholder="<?php esc_attr_e( 'Tên đơn vị cung cấp', 'power-schedule-manager' ); ?>"><br><input class="large-text" type="url" name="source_url" maxlength="2048" value="<?php echo esc_attr( $field( 'source_url' ) ); ?>" placeholder="https://"></td></tr>
					<tr><th><?php esc_html_e( 'Phương thức cập nhật', 'power-schedule-manager' ); ?></th><td><strong><?php esc_html_e( 'Biên tập có đối chiếu nguồn', 'power-schedule-manager' ); ?></strong><p class="description"><?php esc_html_e( 'Giá trong nước và hợp đồng cà phê được nhập từ nguồn có quyền sử dụng. XAU/USD có luồng Gold API riêng trong phần cấu hình cuối trang. Plugin không gắn nhãn API cho dữ liệu nhập tay.', 'power-schedule-manager' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Hiển thị', 'power-schedule-manager' ); ?></th><td><label><input type="checkbox" name="is_public" value="1" <?php checked( ! $editing || '1' === $field( 'is_public' ) ); ?>> <?php esc_html_e( 'Công khai trên shortcode', 'power-schedule-manager' ); ?></label></td></tr>
				</tbody></table>
				<?php submit_button( $editing ? __( 'Lưu thay đổi', 'power-schedule-manager' ) : __( 'Lưu dữ liệu giá', 'power-schedule-manager' ), 'primary', 'submit', false ); ?>
				<?php if ( $editing ) : ?>
					<a class="button" href="<?php echo esc_url( $page_url ); ?>"><?php esc_html_e( 'Hủy sửa', 'power-schedule-manager' ); ?></a>
				<?php endif; ?>
			</form>
		</section>
		<section id="psm-market-records" class="psm-dashboard-panel">
			<div class="psm-market-records__head"><h2>
				<?php
				printf(
					/* translators: %s: Commodity name. */
					esc_html__( 'Dữ liệu %s đã lưu', 'power-schedule-manager' ),
					esc_html( $page_label_lower )
				);
				?>
			</h2><span><?php printf( esc_html__( '%1$s bản ghi · Trang %2$s/%3$s', 'power-schedule-manager' ), esc_html( number_format_i18n( $admin_total ) ), esc_html( number_format_i18n( $admin_page ) ), esc_html( number_format_i18n( $admin_pages ) ) ); ?></span></div>
			<form class="psm-market-records__filters" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $is_gold ? 'power-schedule-manager-gold-prices' : 'power-schedule-manager-coffee-prices' ); ?>">
				<label for="psm-market-filter"><span><?php esc_html_e( 'Nhóm dữ liệu', 'power-schedule-manager' ); ?></span>
					<select id="psm-market-filter" name="market_filter">
						<option value=""><?php esc_html_e( 'Tất cả nhóm dữ liệu', 'power-schedule-manager' ); ?></option>
						<?php foreach ( $markets as $market_code => $market_config ) : ?>
							<option value="<?php echo esc_attr( $market_code ); ?>" <?php selected( $admin_market_filter, $market_code ); ?>><?php echo esc_html( (string) $market_config['title'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button class="button" type="submit"><?php esc_html_e( 'Lọc dữ liệu', 'power-schedule-manager' ); ?></button>
				<?php if ( ! empty( $admin_market_filter ) ) : ?><a class="button button-link" href="<?php echo esc_url( remove_query_arg( 'market_filter', $page_url ) ); ?>"><?php esc_html_e( 'Xóa bộ lọc', 'power-schedule-manager' ); ?></a><?php endif; ?>
			</form>
			<div class="psm-table-scroll psm-market-records__table"><table class="widefat"><thead><tr><th><?php esc_html_e( 'Ngày', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Dữ liệu', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Giá tham chiếu', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Mua / bán', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Trạng thái và nguồn', 'power-schedule-manager' ); ?></th><th class="psm-market-records__actions-column"><?php esc_html_e( 'Thao tác', 'power-schedule-manager' ); ?></th></tr></thead><tbody>
				<?php foreach ( $rows as $row ) : ?><tr>
					<td><time datetime="<?php echo esc_attr( (string) $row['price_date'] ); ?>"><?php echo esc_html( mysql2date( 'd/m/Y', (string) $row['price_date'] ) ); ?></time></td>
					<td><strong><?php echo esc_html( (string) $row['label'] ); ?></strong><small><?php echo esc_html( (string) ( $markets[ $row['market_code'] ]['title'] ?? $row['market_code'] ) ); ?></small></td>
					<td><strong class="psm-market-records__price"><?php echo esc_html( $format_admin_price( $row['price'] ?? $row['buy_price'] ?? null ) ); ?></strong><small><?php echo esc_html( (string) $row['unit'] ); ?></small></td>
					<td><span><?php echo esc_html( $format_admin_price( $row['buy_price'] ?? null ) ); ?></span><span class="psm-market-records__separator">/</span><span><?php echo esc_html( $format_admin_price( $row['sell_price'] ?? null ) ); ?></span></td>
					<td><span class="psm-market-visibility <?php echo ! empty( $row['is_public'] ) ? 'is-public' : 'is-hidden'; ?>"><?php echo ! empty( $row['is_public'] ) ? esc_html__( 'Công khai', 'power-schedule-manager' ) : esc_html__( 'Đang ẩn', 'power-schedule-manager' ); ?></span><?php if ( ! empty( $row['source_url'] ) ) : ?><a class="psm-market-records__source" href="<?php echo esc_url( (string) $row['source_url'] ); ?>" target="_blank" rel="noopener external"><?php echo esc_html( (string) ( $row['source_name'] ?: __( 'Mở nguồn', 'power-schedule-manager' ) ) ); ?></a><?php endif; ?></td>
					<td><div class="psm-row-actions"><a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', absint( $row['id'] ), $page_url ) ); ?>"><?php esc_html_e( 'Sửa', 'power-schedule-manager' ); ?></a><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Xóa dòng giá này?', 'power-schedule-manager' ) ); ?>');"><input type="hidden" name="action" value="psm_delete_market_price"><input type="hidden" name="price_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>"><?php wp_nonce_field( 'psm_delete_market_price_' . absint( $row['id'] ), '_psm_nonce' ); ?><button class="button-link-delete" type="submit"><?php esc_html_e( 'Xóa', 'power-schedule-manager' ); ?></button></form></div></td>
				</tr><?php endforeach; ?>
				<?php if ( array() === $rows ) : ?><tr><td colspan="6"><?php esc_html_e( 'Chưa có dữ liệu phù hợp với bộ lọc.', 'power-schedule-manager' ); ?></td></tr><?php endif; ?>
			</tbody></table></div>
			<?php if ( isset( $admin_pages ) && $admin_pages > 1 ) : ?>
				<nav class="psm-admin-pagination" aria-label="<?php esc_attr_e( 'Phân trang dữ liệu giá', 'power-schedule-manager' ); ?>">
					<span class="psm-admin-pagination__summary">
						<?php
						printf(
							/* translators: 1: First row, 2: Last row, 3: Total rows. */
							esc_html__( 'Hiển thị %1$s–%2$s trong %3$s bản ghi', 'power-schedule-manager' ),
							esc_html( number_format_i18n( ( $admin_page - 1 ) * $admin_per_page + 1 ) ),
							esc_html( number_format_i18n( min( $admin_page * $admin_per_page, $admin_total ) ) ),
							esc_html( number_format_i18n( $admin_total ) )
						);
						?>
					</span>
					<div class="psm-admin-pagination__links">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => $pagination_base . '#psm-market-records',
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
		<details id="psm-market-automation" class="psm-admin-details">
			<summary><?php esc_html_e( 'Shortcode và hướng dẫn xuất bản', 'power-schedule-manager' ); ?></summary>
			<div class="psm-admin-details__body">
			<aside class="psm-admin-settings-link">
				<h2><?php esc_html_e( 'Nguồn dữ liệu tập trung', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'API key, nhà cung cấp và lịch tự động cập nhật đã được chuyển về một màn hình cài đặt chung để tránh cấu hình trùng lặp.', 'power-schedule-manager' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => Power_Schedule_Manager_Admin::DATA_SOURCES_SLUG, 'settings_tab' => $is_gold ? 'gold' : 'coffee' ), admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'Mở Nguồn dữ liệu & API', 'power-schedule-manager' ); ?>
				</a>
			</aside>
			<?php if ( ! empty( $market_settings['last_refresh_report'] ) ) : ?>
				<h2><?php esc_html_e( 'Lần đồng bộ API gần nhất', 'power-schedule-manager' ); ?></h2>
				<div class="psm-provider-status">
				<?php foreach ( $market_settings['last_refresh_report'] as $service => $provider_result ) : ?>
					<?php
					if ( ! is_array( $provider_result ) ) {
						continue;
					}
					$provider_ok = ! empty( $provider_result['ok'] );
					?>
					<div class="psm-provider-status__item <?php echo $provider_ok ? 'is-ok' : 'is-error'; ?>">
						<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $service ) ) ); ?></strong>
						<span><?php echo $provider_ok ? esc_html__( 'Đã nhận dữ liệu', 'power-schedule-manager' ) : esc_html__( 'Chưa nhận dữ liệu', 'power-schedule-manager' ); ?></span>
						<code><?php echo esc_html( (string) ( $provider_result['code'] ?? '' ) ); ?></code>
					</div>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<h2><?php esc_html_e( 'Nguyên tắc xuất bản', 'power-schedule-manager' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Ghi đúng ngày, đơn vị tính và nguồn.', 'power-schedule-manager' ); ?></li>
				<li><?php esc_html_e( 'Không gọi dữ liệu là thời gian thực nếu chỉ cập nhật theo ngày.', 'power-schedule-manager' ); ?></li>
				<li><?php esc_html_e( 'Chỉ dùng API/feed được cấp phép hoặc nhập số liệu thủ công.', 'power-schedule-manager' ); ?></li>
				<li><?php esc_html_e( 'Giá chỉ mang tính tham khảo, không phải khuyến nghị giao dịch.', 'power-schedule-manager' ); ?></li>
			</ul>
			<h3><?php esc_html_e( 'Shortcode cho trang', 'power-schedule-manager' ); ?></h3>
			<?php if ( $is_gold ) : ?>
				<p><code>[power_schedule_gold_overview]</code></p>
				<p><code>[power_schedule_gold_domestic]</code></p>
				<p><code>[power_schedule_gold_world]</code></p>
				<p><code>[power_schedule_gold_api]</code></p>
				<p><code>[power_schedule_exchange_rates]</code></p>
				<p><code>[power_schedule_gold_iframe]</code></p>
				<p><code>[power_schedule_market_iframe src="https://..." title="..." height="560"]</code></p>
				<p><code>[power_schedule_gold_comparison]</code></p>
				<p class="description"><?php esc_html_e( 'Nên tạo Page “Giá vàng hôm nay” và chỉ dùng shortcode tổng hợp. Các shortcode còn lại dành cho vị trí cần một bảng riêng.', 'power-schedule-manager' ); ?></p>
				<p class="description"><?php esc_html_e( 'Plugin chưa tự tính chênh lệch quy đổi giữa USD/oz và VND/lượng khi chưa có tỷ giá USD/VND cùng thời điểm. Cách này tránh công bố một số liệu so sánh sai hoặc đã hết hạn.', 'power-schedule-manager' ); ?></p>
			<?php else : ?>
				<p><code>[power_schedule_coffee_overview]</code></p>
				<p><code>[power_schedule_coffee_lam_dong]</code></p>
				<p><code>[power_schedule_coffee_domestic]</code></p>
				<p><code>[power_schedule_coffee_dak_lak]</code></p>
				<p><code>[power_schedule_coffee_gia_lai]</code></p>
				<p><code>[power_schedule_coffee_dak_nong]</code></p>
				<p><code>[power_schedule_coffee_futures]</code></p>
				<p><code>[power_schedule_coffee_robusta]</code></p>
				<p><code>[power_schedule_coffee_arabica]</code></p>
				<p><code>[power_schedule_pepper_prices]</code></p>
				<p><code>[power_schedule_usd_vnd]</code></p>
				<p><code>[power_schedule_coffee_prices]</code></p>
				<p class="description"><?php esc_html_e( 'Với website địa phương, nên dùng một Page /gia-ca-phe-hom-nay/ gồm lịch sử Lâm Đồng và ba thị trường quốc tế. Chỉ tách trang khi mỗi chủ đề có nội dung phân tích riêng đủ giá trị.', 'power-schedule-manager' ); ?></p>
				<p class="description"><?php esc_html_e( 'Ba hợp đồng quốc tế không cùng đơn vị: Robusta London dùng USD/tấn, Arabica New York dùng cent/lb và Arabica Brazil dùng USD/bao 60kg. Plugin giữ nguyên đơn vị gốc để không tạo so sánh sai lệch.', 'power-schedule-manager' ); ?></p>
				<h3><?php esc_html_e( 'Trang tham khảo', 'power-schedule-manager' ); ?></h3>
				<ul>
					<li><a href="https://giacaphe.com/gia-ca-phe-lam-dong/" target="_blank" rel="noopener external"><?php esc_html_e( 'Giá cà phê Lâm Đồng', 'power-schedule-manager' ); ?></a></li>
					<li><a href="https://giacaphe.com/gia-ca-phe-dak-lak/" target="_blank" rel="noopener external"><?php esc_html_e( 'Giá cà phê Đắk Lắk', 'power-schedule-manager' ); ?></a></li>
					<li><a href="https://giacaphe.com/gia-ca-phe-gia-lai/" target="_blank" rel="noopener external"><?php esc_html_e( 'Giá cà phê Gia Lai', 'power-schedule-manager' ); ?></a></li>
					<li><a href="https://giacaphe.com/gia-ca-phe-dak-nong/" target="_blank" rel="noopener external"><?php esc_html_e( 'Giá cà phê Đắk Nông', 'power-schedule-manager' ); ?></a></li>
					<li><a href="https://giacaphe.com/gia-ca-phe-truc-tuyen/" target="_blank" rel="noopener external"><?php esc_html_e( 'Giá cà phê trực tuyến', 'power-schedule-manager' ); ?></a></li>
					<li><a href="https://giacaphe.com/gia-tieu-hom-nay/" target="_blank" rel="noopener external"><?php esc_html_e( 'Giá hồ tiêu', 'power-schedule-manager' ); ?></a></li>
					<li><a href="https://giacaphe.com/ty-gia-ngoai-te/" target="_blank" rel="noopener external"><?php esc_html_e( 'Tỷ giá USD/VND', 'power-schedule-manager' ); ?></a></li>
				</ul>
				<p class="description"><?php esc_html_e( 'Liên kết chỉ hỗ trợ đối chiếu. Không tự động sao chép hoặc tải lại nội dung khi chưa có API/feed và quyền sử dụng rõ ràng.', 'power-schedule-manager' ); ?></p>
			<?php endif; ?>
			</aside>
			</div>
		</details>
	</div>
</div>
