<?php
/**
 * Current schedule alert.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$title = (string) ( $psm_template_args['title'] ?? '' );
$unit_name = (string) ( $psm_template_args['unit_name'] ?? '' );
$ongoing = absint( $psm_template_args['ongoing'] ?? 0 );
$upcoming = absint( $psm_template_args['upcoming'] ?? 0 );
$next_display = (string) ( $psm_template_args['next_display'] ?? '' );
$next_iso = (string) ( $psm_template_args['next_iso'] ?? '' );
$upcoming_events = isset( $psm_template_args['upcoming_events'] )
	&& is_array( $psm_template_args['upcoming_events'] )
		? $psm_template_args['upcoming_events']
		: array();
$event_list_label = (string) (
	$psm_template_args['event_list_label']
	?? __( 'Các khu vực cần chú ý', 'power-schedule-manager' )
);
$archive_url = (string) ( $psm_template_args['archive_url'] ?? '' );
$state = $ongoing > 0 ? 'ongoing' : ( $upcoming > 0 ? 'upcoming' : 'clear' );
$title_id = wp_unique_id( 'psm-alert-title-' );
?>
<section class="psm-widget psm-alert psm-alert--<?php echo esc_attr( $state ); ?>"<?php echo '' !== $title ? ' aria-labelledby="' . esc_attr( $title_id ) . '"' : ''; ?> aria-live="polite">
	<div class="psm-alert__icon" aria-hidden="true"></div>
	<div class="psm-alert__content">
		<?php if ( '' !== $title ) : ?>
			<h2 id="<?php echo esc_attr( $title_id ); ?>" class="psm-widget__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>

		<?php if ( array() !== $upcoming_events ) : ?>
			<p class="psm-alert__list-title"><?php echo esc_html( $event_list_label ); ?></p>
			<ul class="psm-alert__upcoming" aria-label="<?php echo esc_attr( $event_list_label ); ?>">
				<?php foreach ( $upcoming_events as $event ) : ?>
					<li>
						<span>
							<strong><?php echo esc_html( (string) ( $event['unit_name'] ?? '' ) ); ?></strong>
							<?php echo esc_html( (string) ( $event['area'] ?? '' ) ); ?>
						</span>
						<time datetime="<?php echo esc_attr( (string) ( $event['start_iso'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) ( $event['display_date'] ?? '' ) ); ?>
							<br>
							<?php echo esc_html( (string) ( $event['display_time_range'] ?? '' ) ); ?>
						</time>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $ongoing > 0 ) : ?>
			<p><?php
				echo esc_html(
					sprintf(
						/* translators: 1: Scope name, 2: Number of schedules. */
						__( '%1$s hiện có %2$d lịch nằm trong thời gian cúp điện dự kiến.', 'power-schedule-manager' ),
						'' !== $unit_name ? $unit_name : __( 'Các khu vực', 'power-schedule-manager' ),
						$ongoing
					)
				);
			?></p>
		<?php elseif ( '' !== $next_display ) : ?>
			<p>
				<?php esc_html_e( 'Lịch tiếp theo dự kiến bắt đầu lúc', 'power-schedule-manager' ); ?>
				<time datetime="<?php echo esc_attr( $next_iso ); ?>"><strong><?php echo esc_html( $next_display ); ?></strong></time>.
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Chưa ghi nhận lịch cúp điện đang diễn ra hoặc sắp tới.', 'power-schedule-manager' ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $archive_url ) : ?>
			<a class="psm-widget__more" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Kiểm tra lịch chi tiết', 'power-schedule-manager' ); ?> <span aria-hidden="true">→</span></a>
		<?php endif; ?>
	</div>
</section>
