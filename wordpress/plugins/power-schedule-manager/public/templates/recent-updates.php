<?php
/**
 * Recently updated schedules.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$title = (string) ( $psm_template_args['title'] ?? '' );
$events = isset( $psm_template_args['events'] )
	&& is_array( $psm_template_args['events'] )
		? $psm_template_args['events']
		: array();
$show_area = true === ( $psm_template_args['show_area'] ?? false );
$archive_url = (string) ( $psm_template_args['archive_url'] ?? '' );
$title_id = wp_unique_id( 'psm-recent-title-' );
?>
<section class="psm-widget psm-recent"<?php echo '' !== $title ? ' aria-labelledby="' . esc_attr( $title_id ) . '"' : ''; ?>>
	<?php if ( '' !== $title ) : ?>
		<h2 id="<?php echo esc_attr( $title_id ); ?>" class="psm-widget__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( array() === $events ) : ?>
		<p class="psm-widget-empty"><?php esc_html_e( 'Hiện chưa có lịch mới cập nhật.', 'power-schedule-manager' ); ?></p>
	<?php else : ?>
		<ul class="psm-recent__list">
			<?php foreach ( $events as $event ) : ?>
				<li>
					<div class="psm-recent__meta">
						<strong><?php echo esc_html( (string) $event['unit_name'] ); ?></strong>
						<span class="psm-status-text <?php echo esc_attr( (string) $event['row_class'] ); ?>"><span aria-hidden="true"></span><?php echo esc_html( (string) $event['status_label'] ); ?></span>
					</div>
					<p>
						<time datetime="<?php echo esc_attr( (string) $event['start_iso'] ); ?>"><?php echo esc_html( (string) $event['display_date'] ); ?></time>
						<span aria-hidden="true"> · </span>
						<?php echo esc_html( (string) $event['display_time_range'] ); ?>
					</p>
					<?php if ( $show_area ) : ?>
						<p class="psm-recent__area"><?php echo esc_html( (string) $event['area'] ); ?></p>
					<?php endif; ?>
					<div class="psm-recent__footer">
						<?php if ( '' !== (string) $event['updated_display'] ) : ?>
							<small><?php esc_html_e( 'Cập nhật', 'power-schedule-manager' ); ?> <time datetime="<?php echo esc_attr( (string) $event['updated_iso'] ); ?>"><?php echo esc_html( (string) $event['updated_display'] ); ?></time></small>
						<?php endif; ?>
						<?php if ( '' !== (string) $event['post_url'] ) : ?>
							<a href="<?php echo esc_url( (string) $event['post_url'] ); ?>"><?php esc_html_e( 'Chi tiết', 'power-schedule-manager' ); ?> <span aria-hidden="true">→</span></a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( '' !== $archive_url ) : ?>
		<a class="psm-widget__more" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Xem tất cả lịch', 'power-schedule-manager' ); ?> <span aria-hidden="true">→</span></a>
	<?php endif; ?>
</section>
