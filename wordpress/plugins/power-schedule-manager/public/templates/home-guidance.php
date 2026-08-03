<?php
/**
 * Homepage portal guidance and source transparency.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$archive_url = (string) ( $psm_template_args['archive_url'] ?? '' );
$heading = sanitize_text_field(
	(string) (
		$psm_template_args['heading']
		?? 'Cúp Điện Lâm Đồng — cổng thông tin và tiện ích địa phương'
	)
);
$intro = sanitize_textarea_field(
	(string) ( $psm_template_args['intro'] ?? '' )
);
$extra = sanitize_textarea_field(
	(string) ( $psm_template_args['extra'] ?? '' )
);
$title_id = wp_unique_id( 'psm-home-guidance-title-' );
$section_prefix = wp_unique_id( 'psm-home-information-' );
?>
<section class="psm-home-guidance" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
	<div class="psm-home-guidance__intro">
		<p class="psm-home-guidance__eyebrow"><?php esc_html_e( 'Về Cúp Điện Lâm Đồng', 'power-schedule-manager' ); ?></p>
		<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $heading ); ?></h2>
		<?php if ( '' !== $intro ) : ?>
			<?php echo wpautop( esc_html( $intro ) ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Tin địa phương, tiện ích hằng ngày và gợi ý khám phá được sắp xếp theo nhu cầu để bạn tìm đúng thông tin nhanh hơn.', 'power-schedule-manager' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $extra ) : ?>
		<div class="psm-home-guidance__extra">
			<?php echo wpautop( esc_html( $extra ) ); ?>
		</div>
	<?php endif; ?>

	<div class="psm-home-guidance__steps">
		<article>
			<span aria-hidden="true">1</span>
			<h3><?php esc_html_e( 'Theo dõi địa phương', 'power-schedule-manager' ); ?></h3>
			<p><?php esc_html_e( 'Đọc tin mới, cơ hội việc làm và những thay đổi đáng chú ý tại Lâm Đồng.', 'power-schedule-manager' ); ?></p>
		</article>
		<article>
			<span aria-hidden="true">2</span>
			<h3><?php esc_html_e( 'Tra cứu tiện ích', 'power-schedule-manager' ); ?></h3>
			<p><?php esc_html_e( 'Xem thời tiết, giá cà phê, xổ số và lịch điện trong các chuyên mục riêng, dễ đọc.', 'power-schedule-manager' ); ?></p>
		</article>
		<article>
			<span aria-hidden="true">3</span>
			<h3><?php esc_html_e( 'Khám phá Lâm Đồng', 'power-schedule-manager' ); ?></h3>
			<p><?php esc_html_e( 'Tìm điểm đến, trải nghiệm và nội dung hữu ích cho người dân lẫn du khách.', 'power-schedule-manager' ); ?></p>
		</article>
	</div>

	<div class="psm-home-seo-content">
		<section id="<?php echo esc_attr( $section_prefix . '-organization' ); ?>">
			<h3><?php esc_html_e( 'Thông tin được tổ chức theo nhu cầu hằng ngày', 'power-schedule-manager' ); ?></h3>
			<p><?php esc_html_e( 'Trang chủ đóng vai trò bảng điều khiển chung. Tin tức, việc làm và du lịch được cập nhật theo chuyên mục; các tiện ích có trang riêng để bảng dữ liệu không kéo dài và người dùng có thể đi thẳng đến nội dung cần xem.', 'power-schedule-manager' ); ?></p>
		</section>
		<section id="<?php echo esc_attr( $section_prefix . '-sources' ); ?>">
			<h3><?php esc_html_e( 'Nguồn dữ liệu và tính độc lập', 'power-schedule-manager' ); ?></h3>
			<p><?php esc_html_e( 'Cúp Điện Lâm Đồng là nền tảng thông tin cộng đồng độc lập, không đại diện cho cơ quan nhà nước hoặc đơn vị cung cấp dữ liệu. Nội dung sử dụng dữ liệu từ bên thứ ba cần hiển thị nguồn và thời điểm ghi nhận phù hợp.', 'power-schedule-manager' ); ?></p>
			<p><?php esc_html_e( 'Riêng lịch điện được tổng hợp và hiển thị từ thông tin đã công bố của EVN và đơn vị điện lực. Kế hoạch có thể thay đổi; trạng thái trên website không xác nhận tình trạng cấp điện thực tế.', 'power-schedule-manager' ); ?></p>
		</section>
	</div>

	<section class="psm-home-faq" aria-labelledby="<?php echo esc_attr( $section_prefix . '-faq-title' ); ?>">
		<p class="psm-home-guidance__eyebrow"><?php esc_html_e( 'Câu hỏi thường gặp', 'power-schedule-manager' ); ?></p>
		<h2 id="<?php echo esc_attr( $section_prefix . '-faq-title' ); ?>"><?php esc_html_e( 'Sử dụng Cúp Điện Lâm Đồng', 'power-schedule-manager' ); ?></h2>
		<details>
			<summary><?php esc_html_e( 'Cúp Điện Lâm Đồng có phải cổng thông tin chính thức không?', 'power-schedule-manager' ); ?></summary>
			<p><?php esc_html_e( 'Không. Đây là nền tảng thông tin cộng đồng độc lập. Với thông tin quan trọng, bạn nên đối chiếu thêm nguồn được dẫn hoặc cơ quan phụ trách.', 'power-schedule-manager' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Tôi có thể xem miễn phí các tiện ích không?', 'power-schedule-manager' ); ?></summary>
			<p><?php esc_html_e( 'Có. Các nội dung công khai trên website được thiết kế để tra cứu nhanh và không yêu cầu đăng ký tài khoản.', 'power-schedule-manager' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Vì sao mỗi tiện ích có một trang riêng?', 'power-schedule-manager' ); ?></summary>
			<p><?php esc_html_e( 'Cách tổ chức này giúp trang chủ gọn, tải nhanh và không trộn các bảng dữ liệu dài. Bạn vẫn có thể chuyển giữa các chuyên mục bằng lối tắt ở đầu trang.', 'power-schedule-manager' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Làm sao phản hồi thông tin chưa chính xác?', 'power-schedule-manager' ); ?></summary>
			<p><?php esc_html_e( 'Hãy gửi đường dẫn nội dung, phần cần kiểm tra và nguồn đối chiếu qua trang liên hệ. Ban biên tập sẽ rà soát trước khi điều chỉnh.', 'power-schedule-manager' ); ?></p>
		</details>
	</section>

	<?php if ( '' !== $archive_url ) : ?>
		<a class="psm-home-guidance__link" href="<?php echo esc_url( $archive_url ); ?>">
			<?php esc_html_e( 'Tra cứu lịch điện tại Lâm Đồng', 'power-schedule-manager' ); ?>
			<span aria-hidden="true">→</span>
		</a>
	<?php endif; ?>
</section>
