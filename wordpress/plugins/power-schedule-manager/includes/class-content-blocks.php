<?php
/**
 * Reusable, administrator-managed frontend content blocks.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides editable SSR content without allowing arbitrary PHP execution.
 */
final class Power_Schedule_Manager_Content_Blocks {

	public const string POST_TYPE = 'psm_content_block';

	public const string SHORTCODE = 'power_schedule_content';

	private static bool $rendering = false;

	/** Register hooks. */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 9 );
		add_action( 'init', array( $this, 'register_shortcode' ), 20 );
		add_filter(
			'manage_' . self::POST_TYPE . '_posts_columns',
			array( $this, 'columns' )
		);
		add_action(
			'manage_' . self::POST_TYPE . '_posts_custom_column',
			array( $this, 'column_content' ),
			10,
			2
		);
	}

	/** Register the private editorial content type. */
	public function register_post_type(): void {
		$manage = Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS;

		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Khối nội dung', 'power-schedule-manager' ),
					'singular_name' => __( 'Khối nội dung', 'power-schedule-manager' ),
					'add_new_item'  => __( 'Thêm khối nội dung', 'power-schedule-manager' ),
					'edit_item'     => __( 'Sửa khối nội dung', 'power-schedule-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => Power_Schedule_Manager_Admin::MENU_SLUG,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'          => $manage,
					'read_post'          => $manage,
					'delete_post'        => $manage,
					'edit_posts'         => $manage,
					'edit_others_posts'  => $manage,
					'create_posts'       => $manage,
					'publish_posts'      => $manage,
					'read_private_posts' => $manage,
					'delete_posts'       => $manage,
					'delete_private_posts' => $manage,
					'delete_published_posts' => $manage,
					'delete_others_posts' => $manage,
					'edit_private_posts' => $manage,
					'edit_published_posts' => $manage,
				),
			)
		);
	}

	/** Register the safe content shortcode. */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Render a published block by slug as server-side HTML.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		if ( self::$rendering ) {
			return '';
		}

		$attributes = shortcode_atts(
			array( 'slug' => '' ),
			is_array( $attributes ) ? $attributes : array(),
			self::SHORTCODE
		);
		$slug = sanitize_title( (string) $attributes['slug'] );
		if ( '' === $slug ) {
			return '';
		}

		$block = get_page_by_path( $slug, OBJECT, self::POST_TYPE );
		if ( ! $block instanceof WP_Post || 'publish' !== $block->post_status ) {
			return '';
		}

		self::$rendering = true;
		try {
			$content = apply_filters( 'the_content', $block->post_content );
		} finally {
			self::$rendering = false;
		}
		$content = is_string( $content ) ? $content : '';
		$content = preg_replace( '/<h1\b/i', '<h2', $content );
		$content = is_string( $content ) ? $content : '';
		$content = preg_replace( '/<\/h1>/i', '</h2>', $content );
		$content = is_string( $content ) ? $content : '';

		return '<section class="psm-content-block psm-content-block--'
			. esc_attr( sanitize_html_class( $slug ) ) . '">'
			. wp_kses_post( $content ) . '</section>';
	}

	/** @param array<string,string> $columns List-table columns. */
	public function columns( array $columns ): array {
		$columns['psm_shortcode'] = __( 'Shortcode', 'power-schedule-manager' );

		return $columns;
	}

	/** Render the copyable shortcode column. */
	public function column_content( string $column, int $post_id ): void {
		if ( 'psm_shortcode' !== $column ) {
			return;
		}

		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			echo '<code>[power_schedule_content slug="'
				. esc_attr( $post->post_name ) . '"]</code>';
		}
	}
}
