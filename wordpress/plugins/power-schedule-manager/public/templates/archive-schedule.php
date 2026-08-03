<?php
/**
 * Power schedule archive template.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/archive-schedule.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

get_header();

$selected_date = isset( $_GET['psm_date'] )
	? sanitize_text_field(
		wp_unslash(
			(string) $_GET['psm_date']
		)
	)
	: '';

$selected_unit = isset( $_GET['psm_unit'] )
	? Power_Schedule_Manager_Units::sanitize_code(
		wp_unslash( $_GET['psm_unit'] )
	)
	: '';

$is_filtered = '' !== $selected_date
	|| '' !== $selected_unit;

$settings = get_option(
	POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
	array()
);

$archive_banner_ad = is_array( $settings )
	&& isset( $settings['archive_banner_ad'] )
	&& is_string( $settings['archive_banner_ad'] )
		? trim( $settings['archive_banner_ad'] )
		: '';

$archive_bottom_banner_ad = is_array( $settings )
	&& isset( $settings['archive_bottom_banner_ad'] )
	&& is_string( $settings['archive_bottom_banner_ad'] )
		? trim( $settings['archive_bottom_banner_ad'] )
		: '';
$ads_enabled = ! is_array( $settings )
	|| ! array_key_exists( 'ads_enabled', $settings )
	|| ! empty( $settings['ads_enabled'] );
if ( ! $ads_enabled ) {
	$archive_banner_ad = '';
	$archive_bottom_banner_ad = '';
}
?>

<main
	id="primary"
	class="psm-site-main psm-archive-schedule"
>
	<div class="psm-container">
		<?php
		echo ( new Power_Schedule_Manager_Shortcodes() ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			->render_page_hero_shortcode(
				array(
					'variant'         => 'schedule',
					'title'           => __( 'Lịch cúp điện Lâm Đồng', 'power-schedule-manager' ),
					'eyebrow'         => __( 'Lịch điện Lâm Đồng', 'power-schedule-manager' ),
					'description'     => __( 'Tra cứu lịch ngừng, giảm cung cấp điện theo khu vực và ngày; liên hệ EVNSPC để xác minh tình trạng cấp điện thực tế.', 'power-schedule-manager' ),
					'cta_label'       => __( 'Tra cứu theo khu vực', 'power-schedule-manager' ),
					'cta_url'         => '#psm-archive-search',
					'show_breadcrumb' => 'yes',
				)
			);
		?>

		<aside class="psm-evn-contact" aria-labelledby="psm-evn-contact-title">
			<div>
				<p><?php esc_html_e( 'Kênh xác minh chính thức', 'power-schedule-manager' ); ?></p>
				<h2 id="psm-evn-contact-title"><?php esc_html_e( 'Liên hệ EVNSPC khi cần xác minh hoặc báo sự cố điện', 'power-schedule-manager' ); ?></h2>
				<span><?php esc_html_e( 'Cúp Điện Lâm Đồng là nền tảng tổng hợp độc lập, không phải website hoặc ứng dụng chính thức của EVN/EVNSPC. Lịch công bố có thể thay đổi theo tình hình vận hành thực tế.', 'power-schedule-manager' ); ?></span>
			</div>
			<nav aria-label="<?php esc_attr_e( 'Thông tin liên hệ EVNSPC', 'power-schedule-manager' ); ?>">
				<a href="tel:19001006">1900 1006</a>
				<a href="tel:19009000">1900 9000</a>
				<a href="mailto:cskh@evnspc.vn">cskh@evnspc.vn</a>
				<a href="https://www.cskh.evnspc.vn/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Cổng CSKH EVNSPC', 'power-schedule-manager' ); ?></a>
			</nav>
		</aside>

		<section
			id="psm-archive-search"
			class="psm-archive-search"
			aria-labelledby="psm-archive-search-title"
		>
			<h2
				id="psm-archive-search-title"
				class="psm-section-title"
			>
				<?php
				esc_html_e(
					'Tra cứu lịch cúp điện theo khu vực',
					'power-schedule-manager'
				);
				?>
			</h2>

			<?php
			echo do_shortcode(
				'[' .
				Power_Schedule_Manager_Shortcodes::SEARCH_SHORTCODE .
				']'
			);
			?>
		</section>

		<?php if ( '' !== $archive_banner_ad ) : ?>
			<aside
				class="psm-ad-banner psm-ad-banner--archive-top"
				aria-label="<?php esc_attr_e( 'Quảng cáo', 'power-schedule-manager' ); ?>"
			>
				<span class="psm-ad-banner__label"><?php esc_html_e( 'Quảng cáo', 'power-schedule-manager' ); ?></span>
				<div class="psm-ad-banner__content"><?php echo wp_kses_post( do_shortcode( $archive_banner_ad ) ); ?></div>
			</aside>
		<?php endif; ?>

		<section
			class="psm-archive-results"
			aria-labelledby="psm-archive-results-title"
		>
			<h2
				id="psm-archive-results-title"
				class="psm-section-title"
			>
				<?php
				echo esc_html(
					$is_filtered
						? __(
							'Kết quả tra cứu',
							'power-schedule-manager'
						)
						: __(
							'Danh sách lịch cúp điện theo ngày và khu vực',
							'power-schedule-manager'
						)
				);
				?>
			</h2>

			<?php
			echo do_shortcode(
				'[' .
				Power_Schedule_Manager_Shortcodes::SCHEDULE_SHORTCODE .
				' date="today"' .
				' days="7"' .
				' title=""' .
				' show_unit="yes"' .
				' show_reason="yes"' .
				' show_status="yes"' .
				' show_map="no"' .
				' map_mode="none"' .
				' groups_per_page="10"' .
				' show_disclaimer="yes"' .
				' use_query="yes"' .
			']'
			);
			?>
		</section>

		<?php if ( '' !== $archive_bottom_banner_ad ) : ?>
			<aside
				class="psm-ad-banner psm-ad-banner--archive-bottom"
				aria-label="<?php esc_attr_e( 'Quảng cáo', 'power-schedule-manager' ); ?>"
			>
				<span class="psm-ad-banner__label"><?php esc_html_e( 'Quảng cáo', 'power-schedule-manager' ); ?></span>
				<div class="psm-ad-banner__content"><?php echo wp_kses_post( do_shortcode( $archive_bottom_banner_ad ) ); ?></div>
			</aside>
		<?php endif; ?>

		<section class="psm-information psm-information--seo">
			<h2 class="psm-section-title">
				<?php
				esc_html_e(
					'Lịch cúp điện theo từng điện lực tại Lâm Đồng',
					'power-schedule-manager'
				);
				?>
			</h2>

			<p>
				<?php
				esc_html_e(
					'Trang này tổng hợp lịch cúp điện theo từng ngày và từng đơn vị điện lực tại Lâm Đồng. Người dân có thể xem lịch hôm nay, ngày mai hoặc chọn ngày cụ thể để biết thời gian bắt đầu, thời gian kết thúc, khu vực ảnh hưởng, lý do và trạng thái hiện tại.',
					'power-schedule-manager'
				);
				?>
			</p>

			<p>
				<?php
				esc_html_e(
					'Dữ liệu được phân nhóm theo ngày và điện lực như Đà Lạt, Bảo Lộc, Đức Trọng, Di Linh, Lâm Hà, Đơn Dương và các khu vực liên quan, giúp hộ gia đình, cơ sở kinh doanh và doanh nghiệp chủ động sắp xếp sinh hoạt hoặc lịch làm việc.',
					'power-schedule-manager'
				);
				?>
			</p>
		</section>

		<section class="psm-information">
			<h2 class="psm-section-title">
				<?php
				esc_html_e(
					'Lưu ý khi tra cứu lịch cúp điện',
					'power-schedule-manager'
				);
				?>
			</h2>

			<ul>
				<li>
					<?php
					esc_html_e(
						'Thời gian được hiển thị theo múi giờ Việt Nam.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Lịch có thể thay đổi theo tình hình vận hành thực tế.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Trạng thái đang diễn ra được tính tự động; lịch đã kết thúc không xuất hiện trong danh sách hiện hành.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Nên kiểm tra lại thông báo mới nhất từ đơn vị điện lực trước khi sắp xếp công việc quan trọng.',
						'power-schedule-manager'
					);
					?>
				</li>
			</ul>
		</section>
	</div>
</main>

<?php
get_footer();
