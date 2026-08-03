<?php
/**
 * Compact nearest schedule.
 *
 * Override in:
 * cup-dien-lam-dong/power-schedule-manager/next-schedule.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$event = isset( $psm_template_args['event'] )
	&& is_array( $psm_template_args['event'] )
		? $psm_template_args['event']
		: null;
$events = isset( $psm_template_args['events'] )
	&& is_array( $psm_template_args['events'] )
	? $psm_template_args['events']
	: array();
if ( array() === $events && null !== $event ) {
	$events = array( $event );
}
$title = (string) ( $psm_template_args['title'] ?? '' );
$archive_url = (string) ( $psm_template_args['archive_url'] ?? '' );
$show_reason = true === ( $psm_template_args['show_reason'] ?? false );
$show_map = true === ( $psm_template_args['show_map'] ?? false );
$featured = true === ( $psm_template_args['featured'] ?? false );
$title_id = wp_unique_id( 'psm-next-title-' );
?>
<section class="psm-widget psm-next<?php echo $featured ? ' psm-next--featured' : ''; ?>"<?php echo '' !== $title ? ' aria-labelledby="' . esc_attr( $title_id ) . '"' : ''; ?>>
	<?php if ( '' !== $title ) : ?>
		<h2 id="<?php echo esc_attr( $title_id ); ?>" class="psm-widget__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( array() === $events ) : ?>
		<p class="psm-widget-empty"><?php esc_html_e( 'Hiện chưa có lịch cúp điện phù hợp.', 'power-schedule-manager' ); ?></p>
	<?php else : ?>
		<ol class="psm-next__list">
		<?php foreach ( $events as $event ) : ?>
			<li>
				<div class="psm-next__top">
					<div>
						<time datetime="<?php echo esc_attr( (string) $event['start_iso'] ); ?>" class="psm-next__date">
							<?php echo esc_html( (string) $event['display_date'] ); ?>
						</time>
						<strong class="psm-next__time"><?php echo esc_html( (string) $event['display_time_range'] ); ?></strong>
					</div>
					<span class="psm-status-text <?php echo esc_attr( (string) $event['row_class'] ); ?>">
						<span aria-hidden="true"></span><?php echo esc_html( (string) $event['status_label'] ); ?>
					</span>
				</div>
				<p class="psm-next__unit"><?php echo esc_html( (string) $event['unit_name'] ); ?></p>
				<p class="psm-next__area"><?php echo esc_html( (string) $event['area'] ); ?></p>

				<?php if ( $show_reason && '' !== (string) $event['reason'] ) : ?>
					<p class="psm-next__reason">
						<strong><?php esc_html_e( 'Lý do:', 'power-schedule-manager' ); ?></strong>
						<?php echo esc_html( (string) $event['reason'] ); ?>
					</p>
				<?php endif; ?>

				<div class="psm-widget__links">
					<?php if ( '' !== (string) $event['post_url'] ) : ?>
						<a href="<?php echo esc_url( (string) $event['post_url'] ); ?>"><?php esc_html_e( 'Xem chi tiết', 'power-schedule-manager' ); ?> <span aria-hidden="true">→</span></a>
					<?php endif; ?>
					<?php if ( $show_map && true === $event['has_map'] && '' !== (string) $event['post_url'] ) : ?>
						<a href="<?php echo esc_url( (string) $event['post_url'] . '#ban-do-lich-dien' ); ?>"><?php esc_html_e( 'Xem bản đồ', 'power-schedule-manager' ); ?></a>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<?php if ( '' !== $archive_url ) : ?>
		<a class="psm-widget__more" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Xem toàn bộ lịch cúp điện', 'power-schedule-manager' ); ?> <span aria-hidden="true">→</span></a>
	<?php endif; ?>
</section>
