<?php
/**
 * Public schedule empty state.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/empty-state.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$message = isset( $psm_template_args['message'] )
	&& is_string( $psm_template_args['message'] )
	? sanitize_text_field(
		$psm_template_args['message']
	)
	: '';

if ( '' === $message ) {
	$message = __(
		'Hiện chưa có lịch cúp điện phù hợp.',
		'power-schedule-manager'
	);
}

$show_disclaimer =
	! isset( $psm_template_args['show_disclaimer'] )
	|| (bool) $psm_template_args['show_disclaimer'];

$archive_url = get_post_type_archive_link(
	Power_Schedule_Manager_Post_Type::POST_TYPE
);

$is_archive_page = is_post_type_archive(
	Power_Schedule_Manager_Post_Type::POST_TYPE
);

$has_query_filter = isset(
	$_GET['psm_unit']
)
	|| isset(
		$_GET['psm_date']
	);
?>

<div
	class="psm-empty"
	role="status"
	aria-live="polite"
>
	<span
		class="psm-empty__icon"
		aria-hidden="true"
	>
		<svg
			viewBox="0 0 24 24"
			width="32"
			height="32"
			focusable="false"
			aria-hidden="true"
		>
			<path
				fill="currentColor"
				d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-2 .89-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.11-.9-2-2-2Zm0 16H5V9h14v11Zm0-13H5V6h14v1Z"
			/>
		</svg>
	</span>

	<div class="psm-empty__content">
		<p class="psm-empty__message">
			<?php echo esc_html( $message ); ?>
		</p>

		<?php if (
			$has_query_filter
			&& is_string( $archive_url )
			&& '' !== $archive_url
		) : ?>
			<p class="psm-empty__actions">
				<a
					class="psm-button psm-button--secondary"
					href="<?php echo esc_url( $archive_url ); ?>"
				>
					<?php
					esc_html_e(
						'Xóa bộ lọc và xem lịch mới nhất',
						'power-schedule-manager'
					);
					?>
				</a>
			</p>
		<?php elseif (
			! $is_archive_page
			&& is_string( $archive_url )
			&& '' !== $archive_url
		) : ?>
			<p class="psm-empty__actions">
				<a
					class="psm-button psm-button--secondary"
					href="<?php echo esc_url( $archive_url ); ?>"
				>
					<?php
					esc_html_e(
						'Tra cứu khu vực khác',
						'power-schedule-manager'
					);
					?>
				</a>
			</p>
		<?php endif; ?>
	</div>
</div>

<?php if ( $show_disclaimer ) : ?>
	<?php
	echo Power_Schedule_Manager_Renderer::disclaimer();
	?>
<?php endif; ?>
