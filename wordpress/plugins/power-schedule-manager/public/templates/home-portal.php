<?php
/**
 * Province-wide homepage portal.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$links = is_array( $psm_template_args['links'] ?? null )
	? $psm_template_args['links']
	: array();
$groups = is_array( $psm_template_args['groups'] ?? null )
	? $psm_template_args['groups']
	: array();
$power_summary = is_array( $psm_template_args['power_summary'] ?? null )
	? $psm_template_args['power_summary']
	: array();
$today_units = absint( $power_summary['today_units'] ?? 0 );
$services = array(
	'power' => array( 'icon' => '⚡', 'label' => 'Lịch điện', 'note' => 'Theo ngày và khu vực' ),
	'jobs' => array( 'icon' => '💼', 'label' => 'Việc làm', 'note' => 'Cơ hội mới tại địa phương' ),
	'coffee' => array( 'icon' => '☕', 'label' => 'Giá cà phê', 'note' => 'Thị trường trong nước' ),
	'weather' => array( 'icon' => '🌦', 'label' => 'Thời tiết', 'note' => 'Dự báo các khu vực' ),
	'news' => array( 'icon' => '📰', 'label' => 'Tin tức', 'note' => 'Nhịp sống Lâm Đồng' ),
	'lottery' => array( 'icon' => '🎫', 'label' => 'Xổ số', 'note' => 'Kết quả và tra cứu' ),
	'travel' => array( 'icon' => '🏕️', 'label' => 'Du lịch', 'note' => 'Điểm đến và trải nghiệm' ),
);
?>
<section class="psm-portal" aria-labelledby="psm-portal-services-title">
	<header class="psm-portal__heading">
		<div>
			<p><?php esc_html_e( 'Khám phá nhanh', 'power-schedule-manager' ); ?></p>
			<h2 id="psm-portal-services-title"><?php esc_html_e( 'Mọi thông tin Lâm Đồng trong một nơi', 'power-schedule-manager' ); ?></h2>
		</div>
		<span><?php esc_html_e( 'Chọn đúng nhu cầu để đi thẳng đến thông tin cần xem.', 'power-schedule-manager' ); ?></span>
	</header>

	<nav class="psm-portal__services" aria-label="<?php esc_attr_e( 'Tiện ích Cúp Điện Lâm Đồng', 'power-schedule-manager' ); ?>">
		<?php foreach ( $services as $service_key => $service ) : ?>
			<a href="<?php echo esc_url( (string) ( $links[ $service_key ] ?? '#' ) ); ?>">
				<span aria-hidden="true"><?php echo esc_html( $service['icon'] ); ?></span>
				<strong><?php echo esc_html( $service['label'] ); ?></strong>
				<small><?php echo esc_html( $service['note'] ); ?></small>
			</a>
		<?php endforeach; ?>
	</nav>

	<section class="psm-portal__today" aria-labelledby="psm-portal-today-title">
		<header>
			<p><?php esc_html_e( 'Cần biết hôm nay', 'power-schedule-manager' ); ?></p>
			<h2 id="psm-portal-today-title"><?php esc_html_e( 'Tiện ích cập nhật mỗi ngày', 'power-schedule-manager' ); ?></h2>
		</header>
		<div>
			<a href="<?php echo esc_url( (string) ( $links['weather'] ?? '#' ) ); ?>"><span>🌦</span><div><strong><?php esc_html_e( 'Thời tiết Lâm Đồng', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Xem dự báo theo khu vực', 'power-schedule-manager' ); ?></small></div><b>→</b></a>
			<a href="<?php echo esc_url( (string) ( $links['power'] ?? '#' ) ); ?>"><span>⚡</span><div><strong><?php esc_html_e( 'Lịch điện hôm nay', 'power-schedule-manager' ); ?></strong><small><?php echo esc_html( sprintf( __( '%d khu vực có lịch', 'power-schedule-manager' ), $today_units ) ); ?></small></div><b>→</b></a>
			<a href="<?php echo esc_url( (string) ( $links['coffee'] ?? '#' ) ); ?>"><span>☕</span><div><strong><?php esc_html_e( 'Giá cà phê', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'So sánh thị trường mới nhất', 'power-schedule-manager' ); ?></small></div><b>→</b></a>
			<a href="<?php echo esc_url( (string) ( $links['lottery'] ?? '#' ) ); ?>"><span>🎫</span><div><strong><?php esc_html_e( 'Kết quả xổ số', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Ba miền và Vietlott', 'power-schedule-manager' ); ?></small></div><b>→</b></a>
		</div>
	</section>

	<div class="psm-portal__editorial">
		<?php foreach ( $groups as $group_key => $group ) : ?>
			<?php $posts = is_array( $group['posts'] ?? null ) ? $group['posts'] : array(); ?>
			<section class="psm-portal-feed psm-portal-feed--<?php echo esc_attr( sanitize_html_class( (string) $group_key ) ); ?>">
				<header>
					<h2><?php echo esc_html( (string) ( $group['title'] ?? '' ) ); ?></h2>
					<a href="<?php echo esc_url( (string) ( $group['url'] ?? '#' ) ); ?>"><?php esc_html_e( 'Xem tất cả', 'power-schedule-manager' ); ?> →</a>
				</header>
				<?php if ( array() !== $posts ) : ?>
					<div>
						<?php foreach ( $posts as $post_index => $post ) : ?>
							<article class="<?php echo 0 === $post_index ? 'is-featured' : ''; ?>">
								<?php if ( ! empty( $post['thumbnail'] ) ) : ?><a class="psm-portal-feed__image" href="<?php echo esc_url( (string) $post['url'] ); ?>"><img src="<?php echo esc_url( (string) $post['thumbnail'] ); ?>" <?php if ( ! empty( $post['thumbnail_srcset'] ) ) : ?>srcset="<?php echo esc_attr( (string) $post['thumbnail_srcset'] ); ?>" sizes="(max-width: 760px) 92vw, 420px"<?php endif; ?> width="<?php echo esc_attr( (string) absint( $post['thumbnail_width'] ?? 0 ) ); ?>" height="<?php echo esc_attr( (string) absint( $post['thumbnail_height'] ?? 0 ) ); ?>" alt="" loading="lazy" decoding="async"></a><?php endif; ?>
								<div>
									<time><?php echo esc_html( (string) $post['date'] ); ?></time>
									<h3><a href="<?php echo esc_url( (string) $post['url'] ); ?>"><?php echo esc_html( (string) $post['title'] ); ?></a></h3>
									<?php if ( 0 === $post_index && '' !== (string) $post['excerpt'] ) : ?><p><?php echo esc_html( (string) $post['excerpt'] ); ?></p><?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<a class="psm-portal-feed__empty" href="<?php echo esc_url( (string) ( $group['url'] ?? '#' ) ); ?>"><?php esc_html_e( 'Chuyên mục đang được biên tập. Mở chuyên mục để bắt đầu đăng nội dung.', 'power-schedule-manager' ); ?> →</a>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
</section>
