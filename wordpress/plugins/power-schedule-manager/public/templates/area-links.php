<?php
/**
 * Reusable public electricity-area navigation.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/area-links.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$items = isset( $psm_template_args['items'] )
	&& is_array( $psm_template_args['items'] )
	? $psm_template_args['items']
	: array();

$title = isset( $psm_template_args['title'] )
	&& is_string( $psm_template_args['title'] )
	? $psm_template_args['title']
	: '';

$description = isset( $psm_template_args['description'] )
	&& is_string( $psm_template_args['description'] )
	? $psm_template_args['description']
	: '';

$columns = min(
	6,
	max( 1, absint( $psm_template_args['columns'] ?? 4 ) )
);

$theme = isset( $psm_template_args['theme'] )
	&& 'dark' === $psm_template_args['theme']
	? 'dark'
	: 'light';

$show_icon = ! empty( $psm_template_args['show_icon'] );
$show_code = ! empty( $psm_template_args['show_code'] );
$initial = min( 100, max( 0, absint( $psm_template_args['initial'] ?? 0 ) ) );
$has_more = $initial > 0 && count( $items ) > $initial;
$remaining_count = $has_more ? count( $items ) - $initial : 0;
$grid_id = wp_unique_id( 'psm-area-links-grid-' );
$title_id = wp_unique_id( 'psm-area-links-title-' );
?>

<nav
	class="psm-area-links psm-area-links--<?php echo esc_attr( $theme ); ?>"
	style="--psm-area-link-columns: <?php echo esc_attr( (string) $columns ); ?>;"
	<?php if ( '' !== $title ) : ?>
		aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
	<?php else : ?>
		aria-label="<?php esc_attr_e( 'Tra cứu lịch cúp điện theo khu vực', 'power-schedule-manager' ); ?>"
	<?php endif; ?>
>
	<?php if ( '' !== $title ) : ?>
		<div class="psm-area-links__heading">
			<?php if ( $show_icon ) : ?>
				<span class="psm-area-links__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false">
						<path d="M12 22s7-6.1 7-13a7 7 0 1 0-14 0c0 6.9 7 13 7 13Z"></path>
						<circle cx="12" cy="9" r="2.5"></circle>
					</svg>
				</span>
			<?php endif; ?>

			<div class="psm-area-links__heading-copy">
				<h2 id="<?php echo esc_attr( $title_id ); ?>">
					<?php echo esc_html( $title ); ?>
				</h2>

				<?php if ( '' !== $description ) : ?>
					<p><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<ul id="<?php echo esc_attr( $grid_id ); ?>" class="psm-area-links__grid">
		<?php foreach ( $items as $item_index => $item ) : ?>
			<?php if ( is_array( $item ) ) : ?>
				<li<?php echo $has_more && $item_index >= $initial ? ' hidden data-psm-area-extra' : ''; ?>>
					<a
						href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"
						aria-label="<?php
						echo esc_attr(
							sprintf(
								/* translators: %s: Electricity area name. */
								__( 'Xem lịch cúp điện %s', 'power-schedule-manager' ),
								(string) ( $item['label'] ?? '' )
							)
						);
						?>"
					>
						<span class="psm-area-links__label">
							<span><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></span>
							<?php if ( $show_code ) : ?>
								<small><?php echo esc_html( (string) ( $item['code'] ?? '' ) ); ?></small>
							<?php endif; ?>
						</span>
						<span aria-hidden="true">→</span>
					</a>
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
	<?php if ( $has_more ) : ?>
		<button class="psm-area-links__more" type="button" data-psm-area-more aria-controls="<?php echo esc_attr( $grid_id ); ?>" aria-expanded="false">
			<span data-psm-area-more-label><?php
				echo esc_html(
					sprintf(
						/* translators: %d: Number of hidden electricity areas. */
						__( 'Xem thêm %d khu vực', 'power-schedule-manager' ),
						$remaining_count
					)
				);
			?></span>
			<span aria-hidden="true">↓</span>
		</button>
	<?php endif; ?>
</nav>
