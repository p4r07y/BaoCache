<?php
/**
 * Configurable homepage schedule hub.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/home-hub.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$sections = isset( $psm_template_args['sections'] )
	&& is_array( $psm_template_args['sections'] )
	? $psm_template_args['sections']
	: array();

if ( array() === $sections ) {
	return;
}
?>

<div class="psm-home-hub">
	<?php if ( ! empty( $sections['hero'] ) ) : ?>
		<section class="psm-home-hub__section psm-home-hub__section--hero">
			<?php
			echo $sections['hero']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</section>
	<?php endif; ?>

	<div class="psm-home-hub__body">
		<?php foreach ( array( 'portal', 'search', 'ad_top', 'weather' ) as $section_key ) : ?>
			<?php if ( ! empty( $sections[ $section_key ] ) ) : ?>
				<section class="psm-home-hub__section psm-home-hub__section--<?php echo esc_attr( $section_key ); ?>">
					<?php
					echo $sections[ $section_key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</section>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ( ! empty( $sections['days'] ) || ! empty( $sections['areas'] ) || ! empty( $sections['schedule_summary'] ) ) : ?>
			<section class="psm-home-power-zone" aria-labelledby="psm-home-power-zone-title">
				<header>
					<p><?php esc_html_e( 'Tiện ích thiết yếu', 'power-schedule-manager' ); ?></p>
					<h2 id="psm-home-power-zone-title"><?php esc_html_e( 'Lịch điện tại Lâm Đồng', 'power-schedule-manager' ); ?></h2>
					<span><?php esc_html_e( 'Tra cứu theo ngày và khu vực khi bạn cần chuẩn bị cho sinh hoạt hoặc công việc.', 'power-schedule-manager' ); ?></span>
				</header>
				<?php foreach ( array( 'days', 'areas', 'schedule_summary' ) as $section_key ) : ?>
					<?php if ( ! empty( $sections[ $section_key ] ) ) : ?>
						<section class="psm-home-hub__section psm-home-hub__section--<?php echo esc_attr( $section_key ); ?>">
							<?php
							echo $sections[ $section_key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</section>
					<?php endif; ?>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $sections['ad_bottom'] ) ) : ?>
			<section class="psm-home-hub__section psm-home-hub__section--ad-bottom">
				<?php
				echo $sections['ad_bottom']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $sections['content'] ) ) : ?>
			<section class="psm-home-hub__section psm-home-hub__section--content">
				<?php
				echo $sections['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</section>
		<?php endif; ?>
	</div>
</div>
