<?php
/**
 * Homepage hero for the combined schedule shortcode.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$title = sanitize_text_field(
	(string) ( $psm_template_args['title'] ?? '' )
);
$description = sanitize_text_field(
	(string) ( $psm_template_args['description'] ?? '' )
);
$archive_url = (string) ( $psm_template_args['archive_url'] ?? '' );
$summary = isset( $psm_template_args['summary'] )
	&& is_array( $psm_template_args['summary'] )
	? $psm_template_args['summary']
	: array();
$updated = sanitize_text_field(
	(string) ( $psm_template_args['updated'] ?? '' )
);
$title_id = wp_unique_id( 'psm-home-hero-title-' );
$today_count = absint( $summary['today_units'] ?? 0 );
$balanced_default_title =
	'Cúp Điện Lâm Đồng' === $title;
?>
<section class="psm-home-hero" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
	<div class="psm-home-hero__inner">
		<div class="psm-home-hero__content">
			<p class="psm-home-hero__eyebrow"><?php esc_html_e( 'Nền tảng thông tin cộng đồng', 'power-schedule-manager' ); ?></p>
			<div class="psm-home-hero__quick-facts" aria-label="<?php esc_attr_e( 'Tiện ích tra cứu', 'power-schedule-manager' ); ?>">
				<span><?php esc_html_e( 'Tin địa phương', 'power-schedule-manager' ); ?></span>
				<span><?php esc_html_e( 'Tiện ích thiết yếu', 'power-schedule-manager' ); ?></span>
				<span><?php esc_html_e( 'Khám phá mỗi ngày', 'power-schedule-manager' ); ?></span>
			</div>
			<h1 id="<?php echo esc_attr( $title_id ); ?>">
				<?php if ( $balanced_default_title ) : ?>
					<span><?php esc_html_e( 'Lâm Đồng', 'power-schedule-manager' ); ?></span>
					<span><?php esc_html_e( 'Hôm Nay', 'power-schedule-manager' ); ?></span>
				<?php else : ?>
					<?php echo esc_html( $title ); ?>
				<?php endif; ?>
			</h1>
			<?php if ( '' !== $description ) : ?>
				<p class="psm-home-hero__lead"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<form class="psm-home-hero__global-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $title_id . '-search' ); ?>"><?php esc_html_e( 'Tìm kiếm trên Cúp Điện Lâm Đồng', 'power-schedule-manager' ); ?></label>
				<input id="<?php echo esc_attr( $title_id . '-search' ); ?>" type="search" name="s" placeholder="<?php esc_attr_e( 'Tìm tin tức, việc làm, địa điểm…', 'power-schedule-manager' ); ?>">
				<button type="submit"><?php esc_html_e( 'Tìm kiếm', 'power-schedule-manager' ); ?></button>
			</form>
		</div>

		<aside class="psm-home-hero__visual psm-home-hero__visual--portal" aria-label="<?php esc_attr_e( 'Tiện ích nổi bật hôm nay', 'power-schedule-manager' ); ?>">
			<div class="psm-home-hero__visual-heading">
				<span>
					<small><?php esc_html_e( 'Gợi ý nhanh', 'power-schedule-manager' ); ?></small>
					<strong><?php esc_html_e( 'Hôm nay tại Lâm Đồng', 'power-schedule-manager' ); ?></strong>
				</span>
			</div>
			<div class="psm-home-hero__portal-links">
				<a href="<?php echo esc_url( home_url( '/thoi-tiet-lam-dong/' ) ); ?>"><span>🌦</span><strong><?php esc_html_e( 'Xem thời tiết', 'power-schedule-manager' ); ?></strong><b>→</b></a>
				<a href="<?php echo esc_url( home_url( '/gia-ca-phe-hom-nay/' ) ); ?>"><span>☕</span><strong><?php esc_html_e( 'Giá cà phê', 'power-schedule-manager' ); ?></strong><b>→</b></a>
				<a href="<?php echo esc_url( home_url( '/viec-lam/' ) ); ?>"><span>💼</span><strong><?php esc_html_e( 'Việc làm mới', 'power-schedule-manager' ); ?></strong><b>→</b></a>
				<a href="<?php echo esc_url( $archive_url ); ?>"><span>⚡</span><strong><?php echo esc_html( sprintf( __( 'Lịch điện · %d khu vực', 'power-schedule-manager' ), $today_count ) ); ?></strong><b>→</b></a>
			</div>
			<?php if ( '' !== $updated ) : ?>
				<div class="psm-home-hero__visual-meta">
					<span>
						<small><?php esc_html_e( 'Dữ liệu lịch điện ghi nhận lúc', 'power-schedule-manager' ); ?></small>
						<strong><?php echo esc_html( $updated ); ?></strong>
					</span>
				</div>
			<?php endif; ?>
		</aside>
	</div>
</section>
