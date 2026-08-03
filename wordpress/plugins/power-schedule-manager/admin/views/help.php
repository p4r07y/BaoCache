<?php
/**
 * Administration usage guide.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$archive_url = get_post_type_archive_link(
	Power_Schedule_Manager_Post_Type::POST_TYPE
);

$import_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::IMPORT_SLUG,
	),
	admin_url( 'admin.php' )
);

$schedule_list_url = add_query_arg(
	array(
		'post_type' => Power_Schedule_Manager_Post_Type::POST_TYPE,
	),
	admin_url( 'edit.php' )
);

$settings_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::SETTINGS_SLUG,
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

$backup_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::BACKUP_SLUG,
	),
	admin_url( 'admin.php' )
);

$places_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Place_Library::MENU_SLUG,
	),
	admin_url( 'admin.php' )
);
?>

<div class="wrap psm-admin-wrap psm-help-page">
	<header class="psm-help-hero">
		<div>
			<span class="psm-help-hero__eyebrow">
				<?php esc_html_e( 'Trung tâm trợ giúp', 'power-schedule-manager' ); ?>
			</span>
			<h1>
				<?php
				esc_html_e(
					'Hướng dẫn sử dụng Cúp Điện Lâm Đồng',
					'power-schedule-manager'
				);
				?>
			</h1>

			<p class="psm-admin-lead">
				<?php
				esc_html_e(
					'Nhập lịch, kiểm tra dữ liệu, quản lý bản đồ, shortcode, SEO và vận hành hệ thống an toàn.',
					'power-schedule-manager'
				);
				?>
			</p>
		</div>

		<div class="psm-help-hero__actions">
			<?php if (
				current_user_can(
					Power_Schedule_Manager_Capabilities::IMPORT_SCHEDULES
				)
			) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $import_url ); ?>">
					<?php esc_html_e( 'Nhập dữ liệu', 'power-schedule-manager' ); ?>
				</a>
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( $system_url ); ?>">
				<?php esc_html_e( 'Kiểm tra hệ thống', 'power-schedule-manager' ); ?>
			</a>
		</div>
	</header>

	<section class="psm-help-start" aria-labelledby="psm-help-start-title">
		<div class="psm-help-start__heading">
			<div>
				<span><?php esc_html_e( 'Bắt đầu theo công việc', 'power-schedule-manager' ); ?></span>
				<h2 id="psm-help-start-title"><?php esc_html_e( 'Bạn cần làm gì hôm nay?', 'power-schedule-manager' ); ?></h2>
			</div>
			<label class="psm-help-search" for="psm-help-search">
				<span class="screen-reader-text"><?php esc_html_e( 'Tìm trong hướng dẫn', 'power-schedule-manager' ); ?></span>
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input id="psm-help-search" type="search" data-psm-help-search placeholder="<?php esc_attr_e( 'Tìm: nhập lịch, shortcode, bản đồ, lỗi…', 'power-schedule-manager' ); ?>" autocomplete="off">
			</label>
		</div>

		<div class="psm-help-start__cards">
			<?php if ( current_user_can( Power_Schedule_Manager_Capabilities::IMPORT_SCHEDULES ) ) : ?>
				<a href="<?php echo esc_url( $import_url ); ?>" class="psm-help-start-card psm-help-start-card--primary">
					<span class="dashicons dashicons-upload" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Cập nhật lịch điện', 'power-schedule-manager' ); ?></strong>
					<span><?php esc_html_e( 'Chuẩn bị nguồn, xem trước và xác nhận nhập an toàn.', 'power-schedule-manager' ); ?></span>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( $history_url ); ?>" class="psm-help-start-card">
				<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Kiểm tra cập nhật', 'power-schedule-manager' ); ?></strong>
				<span><?php esc_html_e( 'Xem lịch mới, lịch thay đổi và những lần nhập cần xử lý.', 'power-schedule-manager' ); ?></span>
			</a>

			<a href="<?php echo esc_url( $places_url ); ?>" class="psm-help-start-card">
				<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Quản lý bản đồ', 'power-schedule-manager' ); ?></strong>
				<span><?php esc_html_e( 'Chuẩn hóa địa điểm, tuyến đường và kiểm tra trước khi hiển thị.', 'power-schedule-manager' ); ?></span>
			</a>

			<a href="<?php echo esc_url( $backup_url ); ?>" class="psm-help-start-card">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Sao lưu an toàn', 'power-schedule-manager' ); ?></strong>
				<span><?php esc_html_e( 'Tạo bản sao trước thay đổi lớn và phục hồi có kiểm soát.', 'power-schedule-manager' ); ?></span>
			</a>
		</div>

		<p class="psm-help-search-status" data-psm-help-search-status aria-live="polite"></p>
	</section>

	<nav
		class="psm-dashboard-panel psm-help-toc"
		aria-label="<?php
		esc_attr_e(
			'Mục lục hướng dẫn',
			'power-schedule-manager'
		);
		?>"
	>
		<h2>
			<?php
			esc_html_e(
				'Mục lục',
				'power-schedule-manager'
			);
			?>
		</h2>

		<p><?php esc_html_e( 'Chọn một chủ đề bên dưới hoặc dùng ô tìm nhanh ở trên.', 'power-schedule-manager' ); ?></p>
		<ul>
			<li><a href="#psm-help-workflow">1. Nhập lịch an toàn</a></li>
			<li><a href="#psm-help-shortcodes">2. Shortcode</a></li>
			<li><a href="#psm-help-units">3. Mã đơn vị</a></li>
			<li><a href="#psm-help-map">4. Bản đồ</a></li>
			<li><a href="#psm-help-publishing">5. Xuất bản</a></li>
			<li><a href="#psm-help-seo">6. SEO</a></li>
			<li><a href="#psm-help-theme">7. Theme</a></li>
			<li><a href="#psm-help-operations">8. Vận hành</a></li>
		</ul>
	</nav>

	<section
		id="psm-help-workflow"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php
			esc_html_e(
				'1. Quy trình nhập lịch an toàn',
				'power-schedule-manager'
			);
			?>
		</h2>

		<ol class="psm-dashboard-steps">
			<li>
				<strong>Chuẩn bị dữ liệu nguồn</strong>
				<p>
					Sao chép nguyên thông báo lịch từ nguồn điện lực, gồm
					đơn vị, khu vực, thời gian và lý do.
				</p>
			</li>

			<li>
				<strong>Chọn đúng đơn vị điện lực</strong>
				<p>
					Mã đơn vị phải khớp với nội dung lịch được nhập.
				</p>
			</li>

			<li>
				<strong>Kiểm tra bản xem trước</strong>
				<p>
					Kiểm tra thời gian, khu vực, lý do, sự kiện cập nhật
					và cảnh báo có thể trùng.
				</p>
			</li>

			<li>
				<strong>Xác nhận nhập</strong>
				<p>
					Database chỉ thay đổi sau khi quản trị viên xác nhận.
				</p>
			</li>

			<li>
				<strong>Kiểm tra trang công khai</strong>
				<p>
					Mở lịch vừa xuất bản, kiểm tra nội dung, trạng thái,
					đường dẫn và bản đồ nếu có.
				</p>
			</li>
		</ol>

		<p class="psm-dashboard-actions">
			<?php if (
				current_user_can(
					Power_Schedule_Manager_Capabilities::IMPORT_SCHEDULES
				)
			) : ?>
				<a
					class="button button-primary"
					href="<?php echo esc_url( $import_url ); ?>"
				>
					Nhập dữ liệu nguồn
				</a>
			<?php endif; ?>

			<a
				class="button"
				href="<?php echo esc_url( $schedule_list_url ); ?>"
			>
				Xem danh sách lịch
			</a>
		</p>
	</section>

	<section
		id="psm-help-shortcodes"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php
			esc_html_e(
				'2. Cách dùng shortcode',
				'power-schedule-manager'
			);
			?>
		</h2>

		<p>
			Dán shortcode vào khối Shortcode của WordPress, widget hoặc nội
			dung trang.
		</p>

		<div class="notice notice-info inline">
			<p><strong>Cấu trúc Page chuẩn:</strong> một Hero/H1 → một shortcode dữ liệu hoặc form → nội dung H2/FAQ. Khi chọn Plugin quản lý Hero, plugin tự đồng bộ trạng thái tắt Page Title của Blocksy sau khi bấm Cập nhật.</p>
			<p>CTA mặc định liên kết tới anchor thật: lịch điện <code>#tra-cuu</code>, giá vàng <code>#bang-gia</code>, cà phê <code>#gia-lam-dong</code>, xổ số <code>#ket-qua</code>, tra cứu xổ số <code>#tra-cuu</code> và thời tiết <code>#du-bao</code>.</p>
		</div>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col">Shortcode</th>
					<th scope="col">Công dụng</th>
				</tr>
			</thead>

			<tbody>
				<tr>
					<td><code>[power_schedule]</code></td>
					<td>Hiển thị lịch hôm nay của tất cả đơn vị công khai.</td>
				</tr>

				<tr>
					<td>
						<code>[power_schedule date="today"]</code>
					</td>
					<td>Hiển thị lịch hôm nay.</td>
				</tr>

				<tr>
					<td>
						<code>[power_schedule date="tomorrow"]</code>
					</td>
					<td>Hiển thị lịch ngày mai.</td>
				</tr>

				<tr>
					<td>
						<code>[power_schedule unit="PB0301" days="7"]</code>
					</td>
					<td>
						Hiển thị lịch Điện lực Đà Lạt trong 7 ngày.
					</td>
				</tr>

				<tr>
					<td>
						<code>[power_schedule date="2026-07-24" days="3"]</code>
					</td>
					<td>
						Hiển thị 3 ngày, bắt đầu từ ngày 24/07/2026.
					</td>
				</tr>

				<tr>
					<td><code>[power_schedule_search]</code></td>
					<td>Hiển thị biểu mẫu tra cứu đơn vị và ngày.</td>
				</tr>

				<tr>
					<td><code>[power_schedule_home]</code></td>
					<td>
						Hiển thị luồng tra cứu trang chủ tổng hợp theo cấu
						hình trong tab Shortcode.
					</td>
				</tr>

				<tr>
					<td><code>[power_schedule_next]</code></td>
					<td>Hiển thị lịch đang diễn ra hoặc sắp tới gần nhất.</td>
				</tr>

				<tr>
					<td><code>[power_schedule_alert unit="PB0301"]</code></td>
					<td>Hiển thị cảnh báo trạng thái của một đơn vị.</td>
				</tr>

				<tr>
					<td><code>[power_schedule_days days="7"]</code></td>
					<td>Hiển thị các ngày sắp tới thực sự có lịch.</td>
				</tr>

					<tr>
						<td><code>[power_schedule_recent_updates limit="5"]</code></td>
						<td>Hiển thị các lịch công khai vừa được cập nhật.</td>
					</tr>

					<tr>
						<td><code>[power_schedule_sponsor]</code></td>
						<td>Trang Hợp tác có form bảo vệ, không thu hay hỏi số tiền. Dùng Hero preset <code>sponsor</code>; CTA đi tới <code>#psm-sponsor-form</code>.</td>
					</tr>

					<tr>
						<td><code>[power_schedule_coffee_lam_dong]</code></td>
						<td>Giá cà phê Lâm Đồng theo ngày và nguồn đã nhập trong menu Giá thị trường.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_coffee_domestic]</code></td>
						<td>Bảng so sánh giá cà phê trong nước.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_coffee_dak_lak]</code><br><code>[power_schedule_coffee_gia_lai]</code><br><code>[power_schedule_coffee_dak_nong]</code></td>
						<td>Mỗi shortcode chỉ hiển thị một vùng cà phê nội địa, không gộp chung bảng.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_coffee_futures]</code></td>
						<td>Bảng hợp đồng cà phê thế giới từ nguồn được cấp phép.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_coffee_robusta]</code><br><code>[power_schedule_coffee_arabica]</code></td>
						<td>Mỗi shortcode chỉ hiển thị một dòng hàng hóa quốc tế từ Commodities-API.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_coffee_overview]</code></td>
						<td>Trang tổng hợp có tóm tắt Lâm Đồng, bảng trong nước, cà phê thế giới và tỷ giá. <code>[power_schedule_coffee_prices]</code> là bí danh tương thích.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_gold_domestic]</code></td>
						<td>Một bảng giá vàng trong nước theo nguồn quản trị viên đã chọn.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_gold_world]</code></td>
						<td>Giá vàng thế giới XAU/USD, giữ nguyên đơn vị USD/oz.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_gold_api]</code></td>
						<td>Giá vàng thế giới từ Gold API khi cần một bảng XAU/USD độc lập.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_exchange_rates]</code></td>
						<td>Tỷ giá USD/VND tham chiếu theo nguồn WiFeed đã cấu hình.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_gold_iframe]</code><br><code>[power_schedule_market_iframe src="https://..."]</code></td>
						<td>Iframe responsive cho biểu đồ XAU/USD hoặc một nguồn HTTPS được chỉ định.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_gold_overview]</code></td>
						<td>Trang tổng hợp khuyên dùng: một bảng vàng trong nước và một khối giá thế giới, không lặp thương hiệu. <code>[power_schedule_gold_prices]</code> và <code>[power_schedule_gold_comparison]</code> là bí danh tương thích.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_lottery_results history_limit="10"]</code></td>
						<td>Trang tổng hợp ba miền, Vietlott, Điện toán, bộ chọn ngày và tối thiểu 10 kỳ trước. <code>[power_schedule_lottery]</code> là bí danh tương thích.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_lottery_latest type="north"]</code><br><code>[power_schedule_lottery_history type="north" limit="10"]</code></td>
						<td>Shortcode lõi cho một kết quả mới nhất hoặc lịch sử theo loại. Dùng shortcode preset bên dưới khi không cần cấu hình động.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_lottery_archive]</code></td>
						<td>Công cụ tra cứu kết quả cũ theo sản phẩm và ngày. Dùng trên Page có Hero <code>variant="lottery_lookup"</code>.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_lottery_mega645_history limit="10"]</code><br><code>[power_schedule_lottery_power655_history limit="10"]</code><br><code>[power_schedule_lottery_max3d_history limit="10"]</code><br><code>[power_schedule_lottery_max3d_plus_history limit="10"]</code><br><code>[power_schedule_lottery_max3d_pro_history limit="10"]</code><br><code>[power_schedule_lottery_keno_history]</code></td>
						<td>Lịch sử riêng theo sản phẩm Vietlott; chỉ đặt trên Page chi tiết tương ứng.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_lottery_dientoan123_history limit="10"]</code><br><code>[power_schedule_lottery_dientoan6x36_history limit="10"]</code><br><code>[power_schedule_lottery_thantai_history limit="10"]</code></td>
						<td>Lịch sử riêng của Điện toán 123, 6x36 và Thần Tài.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_lottery_special_week limit="7"]</code></td>
						<td>Bảng Đặc Biệt Tuần độc lập, đặt riêng bên ngoài shortcode tổng.</td>
					</tr>
					<tr>
						<td>
							<code>[power_schedule_lottery_north]</code><br>
							<code>[power_schedule_lottery_central]</code><br>
							<code>[power_schedule_lottery_south]</code>
						</td>
						<td>Ba giao diện bảng riêng cho xổ số miền Bắc, miền Trung và miền Nam.</td>
					</tr>
					<tr>
						<td>
							<code>[power_schedule_lottery_mega645]</code><br>
							<code>[power_schedule_lottery_power655]</code><br>
							<code>[power_schedule_lottery_max3d]</code><br>
							<code>[power_schedule_lottery_max3d_plus]</code><br>
							<code>[power_schedule_lottery_max3d_pro]</code>
						</td>
						<td>Giao diện kết quả riêng cho từng sản phẩm Vietlott.</td>
					</tr>
					<tr>
						<td>
							<code>[power_schedule_lottery_keno]</code>
						</td>
						<td>Kỳ Keno mới nhất; các kỳ trước nằm trong bảng lịch sử tổng hợp.</td>
					</tr>
					<tr>
						<td>
							<code>[power_schedule_lottery_dientoan]</code><br>
							<code>[power_schedule_lottery_dientoan123]</code><br>
							<code>[power_schedule_lottery_dientoan6x36]</code><br>
							<code>[power_schedule_lottery_thantai]</code>
						</td>
						<td>Điện toán tổng hợp hoặc giao diện riêng cho 123, 6x36 và Thần Tài.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_lottery province="Lâm Đồng" limit="3"]</code></td>
						<td>Shortcode tổng quát, giới hạn kết quả theo tỉnh và số kỳ quay.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_page_hero variant="lottery" cta_url="#ket-qua"]</code><br><code>[power_schedule_page_hero variant="lottery_lookup" cta_url="#tra-cuu"]</code></td>
						<td>Hero xổ số dùng đúng một lần ở đầu Page. Không chèn shortcode Hero nếu Page đã chọn “Plugin tự chèn Utility Hero”.</td>
					</tr>
					<tr>
						<td><code>[power_schedule_market_prices market="coffee_lam_dong" limit="20" show_content="yes"]</code></td>
						<td>Shortcode tổng quát; hỗ trợ title, description, limit, show_content, show_footer và show_table. Nội dung giải thích mặc định được tắt ở shortcode đơn để tránh lặp.</td>
					</tr>

				<tr>
					<td>
						<code>[power_schedule_areas region="lam-dong" title="Xem lịch điện tại Lâm Đồng theo khu vực hôm nay" description="Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới." columns="4" link_to="schedule"]</code>
					</td>
					<td>
						Hiển thị lưới khu vực công khai để dùng ở trang chủ.
					</td>
				</tr>

				<tr>
					<td>
						<code>[power_schedule_areas region="lam-dong" title="Xem lịch điện tại Lâm Đồng theo khu vực hôm nay" description="Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới." columns="4" theme="dark" link_to="schedule"]</code>
					</td>
					<td>
						Biến thể nền tối tương tự khối khu vực trong giao diện mẫu.
					</td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Công thức Page xổ số khuyên dùng', 'power-schedule-manager' ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Page', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Nội dung shortcode', 'power-schedule-manager' ); ?></th></tr></thead>
			<tbody>
				<tr><td>Kết quả hôm nay</td><td><code>[power_schedule_page_hero variant="lottery" cta_url="#ket-qua"]</code><br><code>[power_schedule_lottery_overview mode="hub"]</code></td></tr>
				<tr><td>Xổ số ba miền</td><td><code>[power_schedule_page_hero variant="lottery" cta_url="#ket-qua"]</code><br><code>[power_schedule_lottery_north]</code><br><code>[power_schedule_lottery_central]</code><br><code>[power_schedule_lottery_south]</code></td></tr>
				<tr><td>Mega 6/45</td><td><code>[power_schedule_lottery_mega645]</code><br><code>[power_schedule_lottery_mega645_history limit="10"]</code></td></tr>
				<tr><td>Power 6/55</td><td><code>[power_schedule_lottery_power655]</code><br><code>[power_schedule_lottery_power655_history limit="10"]</code></td></tr>
				<tr><td>Max 3D</td><td><code>[power_schedule_lottery_max3d]</code><br><code>[power_schedule_lottery_max3d_plus]</code><br><code>[power_schedule_lottery_max3d_pro]</code></td></tr>
				<tr><td>Keno</td><td><code>[power_schedule_lottery_keno]</code><br><code>[power_schedule_lottery_keno_history]</code></td></tr>
				<tr><td>Điện toán</td><td><code>[power_schedule_lottery_dientoan]</code><br><code>[power_schedule_lottery_dientoan123_history limit="10"]</code><br><code>[power_schedule_lottery_dientoan6x36_history limit="10"]</code><br><code>[power_schedule_lottery_thantai_history limit="10"]</code></td></tr>
				<tr><td>Tra cứu kết quả cũ</td><td><code>[power_schedule_page_hero variant="lottery_lookup" cta_url="#tra-cuu"]</code><br><code>[power_schedule_lottery_archive]</code></td></tr>
			</tbody>
		</table>
		<p><?php esc_html_e( 'Nếu chọn “Plugin tự chèn Utility Hero” trong hộp Cúp Điện Lâm Đồng của Page, hãy bỏ dòng power_schedule_page_hero khỏi nội dung. CTA xổ số trỏ #ket-qua; CTA tra cứu trỏ #tra-cuu. Mỗi Page chỉ có một H1.', 'power-schedule-manager' ); ?></p>

		<h3><?php esc_html_e( 'Thuộc tính đầy đủ của Hero shortcode', 'power-schedule-manager' ); ?></h3>
		<pre><code>[power_schedule_page_hero
	variant="lottery"
	eyebrow="KẾT QUẢ MỚI NHẤT"
	title="Kết quả xổ số hôm nay"
	description="Kết quả được sắp xếp theo sản phẩm và kỳ quay."
	cta_label="Xem kết quả"
	cta_url="#ket-qua"
	show_breadcrumb="yes"
	meta_1_label="Trạng thái"
	meta_1_value="Đang cập nhật"
	meta_1_detail="Theo dữ liệu đã lưu"
]</code></pre>
		<p><?php esc_html_e( 'Preset hỗ trợ: schedule, gold, coffee, lottery, lottery_lookup, weather, participate và sponsor. Có thể thêm breadcrumb_parent_label, breadcrumb_parent_url và ba nhóm meta_1/meta_2/meta_3 gồm label, value, detail. Để trống meta để plugin lấy dữ liệu thật; không nhập số liệu giả.', 'power-schedule-manager' ); ?></p>

		<p>
			Bốn shortcode dạng widget đều hỗ trợ <code>unit</code> bằng mã
			hoặc slug đơn vị. Bỏ <code>unit</code> để lấy toàn bộ đơn vị
			công khai. <code>power_schedule_next</code> hỗ trợ
			<code>show_reason</code> và <code>show_map</code>;
			<code>power_schedule_days</code> hỗ trợ <code>days="1–31"</code>
			và <code>show_count</code>; <code>power_schedule_recent_updates</code>
			hỗ trợ <code>limit="1–20"</code> và <code>show_area</code>.
				Shortcode <code>power_schedule_home</code> tổng hợp các khối động,
				tạo một H1 trong Hero và nội dung hướng dẫn cuối trang. Vì vậy,
				hãy tắt tiêu đề trang mặc định của theme để tránh có hai H1.
			</p>

		<p>
			Shortcode khu vực hỗ trợ <code>region</code>,
			<code>title</code>, <code>description</code>,
			<code>columns</code> từ 1–6,
			<code>limit</code>, <code>theme="light|dark"</code>,
			<code>units="PB0301,PB0302"</code>,
			<code>exclude="PB0211,PC13ZZ"</code>,
			<code>sort="default|name|code"</code>,
			<code>order="asc|desc"</code>,
			<code>label="short|full"</code>,
			<code>show_code="yes|no"</code>,
			<code>show_icon="yes|no"</code> và
			<code>link_to="area|schedule"</code>. Dữ liệu lấy từ các
			đơn vị công khai và có cache, không cần viết lại HTML.
			Số đơn vị được lấy động từ database, không nên ghi cố định
			trong nội dung trang.
			Mặc định <code>link_to="schedule"</code> để mở ngay danh sách
			lịch đã lọc; dùng <code>link_to="area"</code> khi muốn liên kết
			đến trang lưu trữ SEO của từng đơn vị.
		</p>

		<h3>Ẩn hoặc hiện các thành phần</h3>

		<pre><code>[power_schedule
	unit="PB0301"
	days="7"
	show_unit="no"
	show_reason="yes"
	show_status="yes"
	show_map="no"
	map_mode="none"
	show_disclaimer="yes"
]</code></pre>

		<p>
			Các thuộc tính dạng bật/tắt nhận giá trị
			<code>yes</code> hoặc <code>no</code>. Số ngày tối đa cho
			một truy vấn là <strong>31 ngày</strong>.
		</p>

		<p>
			Ở trang danh sách công khai, nên để
			<code>show_map="no"</code> và <code>map_mode="none"</code>
			để bảng lịch gọn, nhanh và dễ đọc. Bản đồ nên hiển thị trong
			trang chi tiết từng ngày/khu vực, nơi người xem có đủ ngữ cảnh
			trước khi mở bản đồ.
		</p>
	</section>

	<section
		id="psm-help-units"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php
			esc_html_e(
				'3. Mã đơn vị điện lực',
				'power-schedule-manager'
			);
			?>
		</h2>

		<p>
			Mỗi khu vực sử dụng một mã riêng. Ví dụ:
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col">Mã</th>
					<th scope="col">Đơn vị</th>
					<th scope="col">Ví dụ shortcode</th>
				</tr>
			</thead>

			<tbody>
				<tr>
					<td><code>PB0301</code></td>
					<td>Điện lực Đà Lạt</td>
					<td>
						<code>[power_schedule unit="PB0301" days="7"]</code>
					</td>
				</tr>

				<tr>
					<td><code>PB0302</code></td>
					<td>Điện lực Bảo Lộc</td>
					<td>
						<code>[power_schedule unit="PB0302" days="7"]</code>
					</td>
				</tr>

				<tr>
					<td><code>PB0305</code></td>
					<td>Điện lực Đức Trọng</td>
					<td>
						<code>[power_schedule unit="PB0305" days="7"]</code>
					</td>
				</tr>
			</tbody>
		</table>

		<p>
			Shortcode có thể dùng tương tự với tất cả mã đơn vị đang được
			đặt ở trạng thái công khai.
		</p>

		<div class="notice notice-info inline">
			<p>
				Đơn vị nội bộ không xuất hiện trong biểu mẫu frontend và
				không thể truy vấn công khai bằng shortcode.
			</p>
		</div>
	</section>

	<section
		id="psm-help-map"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php
			esc_html_e(
				'4. Cách dùng bản đồ',
				'power-schedule-manager'
			);
			?>
		</h2>

		<ol>
			<li>Mở <strong>Thư viện bản đồ</strong> và chọn đúng đơn vị điện lực.</li>
			<li>Tạo tên chuẩn, thêm các cách viết khác vào phần bí danh.</li>
			<li>Tìm tuyến từ OpenStreetMap hoặc nhập GeoJSON đã kiểm tra.</li>
			<li>Xem trực tiếp hình học trên bản đồ trước khi lưu.</li>
			<li>Khớp lại thư viện để liên kết tên đường với các lịch đã có.</li>
			<li>Mở trang chi tiết lịch để xác nhận nút xem bản đồ hoạt động.</li>
		</ol>

		<p>
			Có thể mô tả:
		</p>

		<ul class="ul-disc">
			<li>Một điểm hoặc công trình.</li>
			<li>Một đoạn đường bằng LineString.</li>
			<li>Một phạm vi bằng Polygon.</li>
			<li>Nhiều khu vực bằng FeatureCollection.</li>
		</ul>

		<p>
			Bản đồ chỉ tải sau khi khách truy cập nhấn
			<strong>Xem trên bản đồ</strong>. Plugin không tự gửi dữ liệu
			lịch điện đến dịch vụ geocoding.
		</p>

		<div class="psm-help-callout psm-help-callout--warning">
			<strong><?php esc_html_e( 'Giới hạn cần thông báo rõ', 'power-schedule-manager' ); ?></strong>
			<p>
				<?php
				esc_html_e(
					'Tuyến đường được đánh dấu chỉ nhằm mô phỏng khu vực có thể bị ảnh hưởng, không xác định chính xác phạm vi ngừng điện. Tên đường có thể trùng ở nhiều địa phương nên luôn phải chọn đúng đơn vị và kiểm tra trực quan trước khi lưu.',
					'power-schedule-manager'
				);
				?>
			</p>
		</div>

		<p>
			<a
				class="button"
				href="<?php echo esc_url( $places_url ); ?>"
			>
				Mở thư viện bản đồ
			</a>
			<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
				Mở cài đặt bản đồ
			</a>
		</p>
	</section>

	<section
		id="psm-help-publishing"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php
			esc_html_e(
				'5. Xuất bản và đường dẫn',
				'power-schedule-manager'
			);
			?>
		</h2>

		<p>
			Mỗi đơn vị và ngày có một bài lịch điện. Ví dụ:
		</p>

		<pre><code>https://domain.com/lich-cup-dien/da-lat-24-07-2026/</code></pre>

		<p>
			Ngày được trình bày theo định dạng Việt Nam:
			<code>ngày-tháng-năm</code>.
		</p>

		<ul class="ul-disc">
			<li>Dữ liệu nguồn hợp lệ được tự động xuất bản sau khi xác nhận import.</li>
			<li>Chỉ bài đã xuất bản mới có permalink frontend.</li>
			<li>Không đổi slug thủ công nếu không thực sự cần thiết.</li>
			<li>Không tạo nhiều bài cho cùng một đơn vị và ngày.</li>
			<li>
				Trang danh sách mặc định ưu tiên lịch đang diễn ra và sắp tới;
				lịch đã kết thúc không bị xóa ngay để còn phục vụ kiểm tra.
			</li>
			<li>
				Trong trang chi tiết, “Lịch trước/Lịch tiếp” luôn trỏ tới ngày
				gần nhất thực sự có lịch đã xuất bản của cùng đơn vị, tự bỏ qua
				ngày trống và không dẫn ngược về trước ngày hiện tại.
			</li>
		</ul>

		<?php if ( is_string( $archive_url ) ) : ?>
			<p>
				<a
					class="button"
					href="<?php echo esc_url( $archive_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					Xem trang lịch điện ngoài website
				</a>
			</p>
		<?php endif; ?>
	</section>

	<section
		id="psm-help-seo"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php
			esc_html_e(
				'6. SEO và Rank Math',
				'power-schedule-manager'
			);
			?>
		</h2>

		<ul class="ul-disc">
			<li>
				Plugin tương thích bộ lọc title, description, canonical,
				robots và JSON-LD của Rank Math.
			</li>
			<li>
				Không thêm metadata SEO thủ công vào template.
			</li>
			<li>
				Trang có query lọc được đặt <code>noindex, follow</code>
				để tránh nội dung trùng.
			</li>
			<li>
				Archive, taxonomy và bài chi tiết có tiêu đề H1 rõ ràng.
			</li>
			<li>
				Chỉ xuất bản dữ liệu đã được kiểm tra và có nội dung hữu ích.
			</li>
		</ul>

		<div class="notice notice-warning inline">
			<p>
				Nếu Rank Math đang hoạt động, không thêm một plugin SEO khác
				cùng lúc để tránh trùng canonical và schema.
			</p>
		</div>
	</section>

	<section
		id="psm-help-theme"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php
			esc_html_e(
				'7. Tùy biến giao diện bằng theme',
				'power-schedule-manager'
			);
			?>
		</h2>

		<p>
			Không sửa trực tiếp template trong plugin. Sao chép file cần
			tùy biến vào:
		</p>

		<pre><code>cup-dien-lam-dong/
└── power-schedule-manager/
    ├── single-schedule.php
    ├── archive-schedule.php
    ├── taxonomy-area.php
    ├── schedule-table.php
    ├── schedule-cards.php
    ├── search-form.php
    ├── empty-state.php
    ├── area-links.php
    ├── next-schedule.php
    ├── schedule-alert.php
    ├── schedule-days.php
    ├── recent-updates.php
    └── map.php</code></pre>

		<p>
			Theme con được ưu tiên trước theme cha. Khi plugin cập nhật,
			các file override trong theme không bị ghi đè.
		</p>
	</section>

	<section
		id="psm-help-operations"
		class="psm-dashboard-panel"
		data-psm-help-section
	>
		<h2>
			<?php esc_html_e( '8. Vận hành, kiểm tra và xử lý sự cố', 'power-schedule-manager' ); ?>
		</h2>

		<div class="psm-help-feature-grid">
			<article>
				<span class="dashicons dashicons-database-view" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Trung tâm hệ thống', 'power-schedule-manager' ); ?></h3>
				<p><?php esc_html_e( 'Kiểm tra phiên bản PHP, database, cache, cron, schema và quan hệ dữ liệu mà không tự sửa bản ghi.', 'power-schedule-manager' ); ?></p>
				<a href="<?php echo esc_url( $system_url ); ?>"><?php esc_html_e( 'Mở kiểm tra hệ thống', 'power-schedule-manager' ); ?></a>
			</article>

			<article>
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Điều tra lần nhập', 'power-schedule-manager' ); ?></h3>
				<p><?php esc_html_e( 'Lọc theo đơn vị, trạng thái và thời gian; mở từng lần nhập để xem số mới, cập nhật, không đổi và lỗi.', 'power-schedule-manager' ); ?></p>
				<a href="<?php echo esc_url( $history_url ); ?>"><?php esc_html_e( 'Mở lịch sử đồng bộ', 'power-schedule-manager' ); ?></a>
			</article>

			<article>
				<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Dữ liệu bản đồ tái sử dụng', 'power-schedule-manager' ); ?></h3>
				<p><?php esc_html_e( 'Tên chuẩn, bí danh và hình học được lưu độc lập với lịch điện để dùng lại khi tuyến đường xuất hiện trong lịch mới.', 'power-schedule-manager' ); ?></p>
				<a href="<?php echo esc_url( $places_url ); ?>"><?php esc_html_e( 'Quản lý thư viện bản đồ', 'power-schedule-manager' ); ?></a>
			</article>

			<article>
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Sao lưu và phục hồi', 'power-schedule-manager' ); ?></h3>
				<p><?php esc_html_e( 'Tạo file backup có checksum cho cấu hình, lịch điện và bản đồ; phục hồi an toàn trên staging hoặc website mới.', 'power-schedule-manager' ); ?></p>
				<a href="<?php echo esc_url( $backup_url ); ?>"><?php esc_html_e( 'Mở trung tâm backup', 'power-schedule-manager' ); ?></a>
			</article>
		</div>

		<h3><?php esc_html_e( 'Thứ tự kiểm tra khi có lỗi', 'power-schedule-manager' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Tạo một bản backup plugin trước khi nhập hoặc thay đổi dữ liệu lớn.', 'power-schedule-manager' ); ?></li>
			<li><?php esc_html_e( 'Mở Trung tâm hệ thống và xử lý các mục màu đỏ trước.', 'power-schedule-manager' ); ?></li>
			<li><?php esc_html_e( 'Mở chi tiết lần nhập gần nhất, không nhập lại nhiều lần khi chưa biết nguyên nhân.', 'power-schedule-manager' ); ?></li>
			<li><?php esc_html_e( 'Kiểm tra đúng mã đơn vị, trạng thái bài đã xuất bản và thời gian theo múi giờ Việt Nam.', 'power-schedule-manager' ); ?></li>
			<li><?php esc_html_e( 'Sau khi sửa dữ liệu, xóa cache liên quan rồi kiểm tra lại bằng cửa sổ riêng tư.', 'power-schedule-manager' ); ?></li>
		</ol>

		<div class="psm-help-callout">
			<strong><?php esc_html_e( 'Bảo trì dữ liệu', 'power-schedule-manager' ); ?></strong>
			<p><?php esc_html_e( 'Payload nguồn được dọn theo thời hạn cấu hình; lịch sử thống kê được giữ để điều tra; lịch hoàn tất được tính theo thời gian dự kiến và không phải xác nhận thực tế đã có điện.', 'power-schedule-manager' ); ?></p>
		</div>
	</section>
</div>
