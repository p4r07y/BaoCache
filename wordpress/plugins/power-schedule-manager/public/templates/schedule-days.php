<?php
/**
 * Upcoming days containing schedules.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$title = (string) ( $psm_template_args['title'] ?? '' );
$items = isset( $psm_template_args['items'] )
	&& is_array( $psm_template_args['items'] )
		? $psm_template_args['items']
		: array();
$show_count = true === ( $psm_template_args['show_count'] ?? false );
$title_id = wp_unique_id( 'psm-days-title-' );
?>
<nav class="psm-widget psm-days"<?php echo '' !== $title ? ' aria-labelledby="' . esc_attr( $title_id ) . '"' : ''; ?>>
	<?php if ( '' !== $title ) : ?>
		<h2 id="<?php echo esc_attr( $title_id ); ?>" class="psm-widget__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( array() === $items ) : ?>
		<p class="psm-widget-empty"><?php esc_html_e( 'Chưa có ngày nào có lịch cúp điện trong khoảng đã chọn.', 'power-schedule-manager' ); ?></p>
	<?php else : ?>
		<ul class="psm-days__list">
			<?php foreach ( $items as $item ) : ?>
				<li>
					<a href="<?php echo esc_url( (string) $item['url'] ); ?>">
						<span class="psm-days__label"><?php echo esc_html( (string) $item['label'] ); ?></span>
						<time datetime="<?php echo esc_attr( (string) $item['iso'] ); ?>"><?php echo esc_html( (string) $item['date'] ); ?></time>
						<?php if ( $show_count ) : ?>
							<small><?php
								echo esc_html(
									sprintf(
										/* translators: %d: Number of schedules. */
										_n( '%d lịch', '%d lịch', absint( $item['count'] ), 'power-schedule-manager' ),
										absint( $item['count'] )
									)
								);
							?></small>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</nav>
