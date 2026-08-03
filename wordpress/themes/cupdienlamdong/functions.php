<?php
/**
 * Cúp Điện Lâm Đồng child-theme bootstrap.
 *
 * @package CupDienLamDong
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the child stylesheet.
 */
function cupdienlamdong_enqueue_child_style(): void {
	$relative_path = '/assets/css/site.css';
	$file_path     = get_stylesheet_directory() . $relative_path;

	if ( ! is_file( $file_path ) ) {
		return;
	}

	wp_enqueue_style(
		'cupdienlamdong-child',
		get_stylesheet_directory_uri() . $relative_path,
		array(),
		(string) filemtime( $file_path )
	);
}
add_action( 'wp_enqueue_scripts', 'cupdienlamdong_enqueue_child_style', 30 );

/**
 * Copy Blocksy Customizer settings once.
 */
function cupdienlamdong_copy_parent_theme_mods_once(): void {
	$child_mods = get_option( 'theme_mods_cupdienlamdong', array() );

	if ( is_array( $child_mods ) && array() !== $child_mods ) {
		return;
	}

	$parent_mods = get_option( 'theme_mods_blocksy', array() );

	if ( is_array( $parent_mods ) && array() !== $parent_mods ) {
		update_option( 'theme_mods_cupdienlamdong', $parent_mods, false );
	}
}
add_action( 'after_switch_theme', 'cupdienlamdong_copy_parent_theme_mods_once' );

/**
 * Render the community notice immediately after the opening body tag.
 */
function cupdienlamdong_render_community_topbar(): void {
	if ( is_admin() ) {
		return;
	}
	?>
	<aside
		class="cdld-topbar"
		role="note"
		aria-label="<?php echo esc_attr__( 'Thông báo cộng đồng', 'cupdienlamdong' ); ?>"
	>
		<div class="cdld-topbar__inner">
			<div class="cdld-topbar__message">
				<span class="cdld-topbar__icon" aria-hidden="true">📢</span>

				<span>
					<?php
					echo esc_html__(
						'Website cộng đồng hỗ trợ tra cứu lịch cúp điện. Không trực thuộc EVN hoặc Công ty Điện lực Lâm Đồng.',
						'cupdienlamdong'
					);
					?>
				</span>
			</div>

			<div class="cdld-topbar__actions">
				<a
					href="https://www.facebook.com/cupdienlamdong/"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php echo esc_html__( 'Theo dõi Facebook', 'cupdienlamdong' ); ?>
				</a>

				<a
					href="https://www.facebook.com/groups/cupdienlamdong/"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php echo esc_html__( 'Tham gia cộng đồng', 'cupdienlamdong' ); ?>
				</a>
			</div>
		</div>
	</aside>
	<?php
}
add_action( 'wp_body_open', 'cupdienlamdong_render_community_topbar', 20 );