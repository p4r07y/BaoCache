<?php
/**
 * Register the electrical service area taxonomy.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the public electrical service area taxonomy.
 */
final class Power_Schedule_Manager_Taxonomy {

	/**
	 * Taxonomy name stored by WordPress.
	 */
	public const string TAXONOMY = 'psm_power_area';

	/**
	 * Public taxonomy archive slug.
	 */
	public const string REWRITE_SLUG = 'khu-vuc-dien-luc';

	/**
	 * REST API base.
	 */
	public const string REST_BASE = 'power-areas';

	/**
	 * Unit code term metadata.
	 */
	public const string META_UNIT_CODE = '_psm_unit_code';

	/**
	 * Whether schedules from this unit are publicly visible.
	 */
	public const string META_IS_PUBLIC = '_psm_is_public';

	/**
	 * Administrative display order.
	 */
	public const string META_DISPLAY_ORDER = '_psm_display_order';

	/**
	 * Optional short area description.
	 */
	public const string META_SHORT_DESCRIPTION = '_psm_short_description';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'init',
			array( $this, 'register_taxonomy' ),
			9
		);

		add_action(
			'init',
			array( $this, 'register_term_meta' ),
			10
		);

		add_filter(
			'manage_edit-' . self::TAXONOMY . '_columns',
			array( $this, 'filter_admin_columns' )
		);

		add_filter(
			'manage_' . self::TAXONOMY . '_custom_column',
			array( $this, 'render_admin_column' ),
			10,
			3
		);
	}

	/**
	 * Register the taxonomy immediately.
	 *
	 * This method is public because the activation process must register the
	 * taxonomy before flushing rewrite rules.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		if ( taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		register_taxonomy(
			self::TAXONOMY,
			array( Power_Schedule_Manager_Post_Type::POST_TYPE ),
			array(
				'labels'                => $this->labels(),
				'description'           => __(
					'Phân loại lịch cúp điện theo khu vực hoặc đơn vị điện lực.',
					'power-schedule-manager'
				),
				'public'                => true,
				'publicly_queryable'    => true,
				'hierarchical'          => true,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'show_in_nav_menus'     => true,
				'show_tagcloud'         => false,
				'show_in_quick_edit'    => true,
				'show_admin_column'     => true,
				'show_in_rest'          => true,
				'rest_base'             => self::REST_BASE,
				'rest_namespace'        => 'wp/v2',
				'query_var'             => self::TAXONOMY,
				'capabilities'          => Power_Schedule_Manager_Capabilities::taxonomy_capabilities(),
				'rewrite'               => array(
					'slug'         => self::REWRITE_SLUG,
					'with_front'   => false,
					'hierarchical' => true,
				),
				'update_count_callback' => '_update_post_term_count',
				'sort'                  => false,
			)
		);
	}

	/**
	 * Register protected taxonomy metadata.
	 *
	 * Metadata is exposed through REST only to users who have permission to
	 * manage electrical service areas.
	 *
	 * @return void
	 */
	public function register_term_meta(): void {
		register_term_meta(
			self::TAXONOMY,
			self::META_UNIT_CODE,
			array(
				'type'              => 'string',
				'description'       => __(
					'Mã đơn vị điện lực duy nhất.',
					'power-schedule-manager'
				),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => array(
					self::class,
					'sanitize_unit_code',
				),
				'auth_callback'     => array(
					self::class,
					'authorize_meta_update',
				),
				'show_in_rest'      => array(
					'schema' => array(
						'type'        => 'string',
						'maxLength'   => 32,
						'description' => __(
							'Mã đơn vị điện lực.',
							'power-schedule-manager'
						),
					),
				),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			self::META_IS_PUBLIC,
			array(
				'type'              => 'boolean',
				'description'       => __(
					'Cho phép hiển thị lịch của khu vực ngoài website.',
					'power-schedule-manager'
				),
				'single'            => true,
				'default'           => true,
				'sanitize_callback' => array(
					self::class,
					'sanitize_boolean',
				),
				'auth_callback'     => array(
					self::class,
					'authorize_meta_update',
				),
				'show_in_rest'      => array(
					'schema' => array(
						'type'        => 'boolean',
						'description' => __(
							'Trạng thái hiển thị công khai.',
							'power-schedule-manager'
						),
					),
				),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			self::META_DISPLAY_ORDER,
			array(
				'type'              => 'integer',
				'description'       => __(
					'Thứ tự hiển thị khu vực.',
					'power-schedule-manager'
				),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => array(
					self::class,
					'sanitize_display_order',
				),
				'auth_callback'     => array(
					self::class,
					'authorize_meta_update',
				),
				'show_in_rest'      => array(
					'schema' => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'maximum'     => 65535,
						'description' => __(
							'Thứ tự hiển thị khu vực.',
							'power-schedule-manager'
						),
					),
				),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			self::META_SHORT_DESCRIPTION,
			array(
				'type'              => 'string',
				'description'       => __(
					'Mô tả ngắn của khu vực điện lực.',
					'power-schedule-manager'
				),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => array(
					self::class,
					'sanitize_short_description',
				),
				'auth_callback'     => array(
					self::class,
					'authorize_meta_update',
				),
				'show_in_rest'      => array(
					'schema' => array(
						'type'        => 'string',
						'maxLength'   => 500,
						'description' => __(
							'Mô tả ngắn của khu vực.',
							'power-schedule-manager'
						),
					),
				),
			)
		);
	}

	/**
	 * Return taxonomy labels.
	 *
	 * @return array<string, string>
	 */
	private function labels(): array {
		return array(
			'name'                       => _x(
				'Khu vực điện lực',
				'taxonomy general name',
				'power-schedule-manager'
			),
			'singular_name'              => _x(
				'Khu vực điện lực',
				'taxonomy singular name',
				'power-schedule-manager'
			),
			'menu_name'                  => __(
				'Khu vực điện lực',
				'power-schedule-manager'
			),
			'all_items'                  => __(
				'Tất cả khu vực',
				'power-schedule-manager'
			),
			'edit_item'                  => __(
				'Chỉnh sửa khu vực',
				'power-schedule-manager'
			),
			'view_item'                  => __(
				'Xem khu vực',
				'power-schedule-manager'
			),
			'update_item'                => __(
				'Cập nhật khu vực',
				'power-schedule-manager'
			),
			'add_new_item'               => __(
				'Thêm khu vực mới',
				'power-schedule-manager'
			),
			'new_item_name'              => __(
				'Tên khu vực mới',
				'power-schedule-manager'
			),
			'parent_item'                => __(
				'Khu vực cha',
				'power-schedule-manager'
			),
			'parent_item_colon'          => __(
				'Khu vực cha:',
				'power-schedule-manager'
			),
			'search_items'               => __(
				'Tìm khu vực',
				'power-schedule-manager'
			),
			'popular_items'              => __(
				'Khu vực phổ biến',
				'power-schedule-manager'
			),
			'separate_items_with_commas' => __(
				'Phân tách khu vực bằng dấu phẩy',
				'power-schedule-manager'
			),
			'add_or_remove_items'        => __(
				'Thêm hoặc xóa khu vực',
				'power-schedule-manager'
			),
			'choose_from_most_used'      => __(
				'Chọn từ khu vực được dùng nhiều nhất',
				'power-schedule-manager'
			),
			'not_found'                  => __(
				'Không tìm thấy khu vực.',
				'power-schedule-manager'
			),
			'no_terms'                   => __(
				'Chưa có khu vực',
				'power-schedule-manager'
			),
			'filter_by_item'             => __(
				'Lọc theo khu vực',
				'power-schedule-manager'
			),
			'items_list_navigation'      => __(
				'Điều hướng danh sách khu vực',
				'power-schedule-manager'
			),
			'items_list'                 => __(
				'Danh sách khu vực',
				'power-schedule-manager'
			),
			'back_to_items'              => __(
				'Quay lại danh sách khu vực',
				'power-schedule-manager'
			),
			'item_link'                  => __(
				'Liên kết khu vực',
				'power-schedule-manager'
			),
			'item_link_description'      => __(
				'Liên kết đến trang khu vực điện lực.',
				'power-schedule-manager'
			),
		);
	}

	/**
	 * Sanitize a unit code.
	 *
	 * Unit codes are normalized to uppercase ASCII characters. Only letters,
	 * numbers, underscores and hyphens are accepted.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_unit_code( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strtoupper(
			trim( (string) $value )
		);

		$value = preg_replace(
			'/[^A-Z0-9_-]/',
			'',
			$value
		);

		if ( ! is_string( $value ) ) {
			return '';
		}

		return substr( $value, 0, 32 );
	}

	/**
	 * Sanitize a Boolean metadata value.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return bool
	 */
	public static function sanitize_boolean( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 1 === (int) $value;
		}

		if ( is_string( $value ) ) {
			return in_array(
				strtolower( trim( $value ) ),
				array( '1', 'true', 'yes', 'on' ),
				true
			);
		}

		return false;
	}

	/**
	 * Sanitize the display order.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int
	 */
	public static function sanitize_display_order( mixed $value ): int {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}

		return min(
			65535,
			max( 0, absint( $value ) )
		);
	}

	/**
	 * Sanitize the short description.
	 *
	 * Plain text is used to prevent stored XSS in admin and REST responses.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_short_description( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_textarea_field(
			(string) $value
		);

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 500 );
		}

		return substr( $value, 0, 500 );
	}

	/**
	 * Authorize term metadata changes.
	 *
	 * The signature accepts the arguments supplied by WordPress metadata
	 * authorization filters while remaining compatible with REST callbacks.
	 *
	 * @param bool   $allowed     Existing authorization result.
	 * @param string $meta_key    Metadata key.
	 * @param int    $object_id   Term ID.
	 * @param int    $user_id     User ID.
	 * @param string $capability  Requested capability.
	 * @param array  $capability_args Additional capability arguments.
	 *
	 * @return bool
	 */
	public static function authorize_meta_update(
		bool $allowed = false,
		string $meta_key = '',
		int $object_id = 0,
		int $user_id = 0,
		string $capability = '',
		array $capability_args = array()
	): bool {
		unset(
			$allowed,
			$meta_key,
			$object_id,
			$user_id,
			$capability,
			$capability_args
		);

		return current_user_can(
			Power_Schedule_Manager_Capabilities::MANAGE_UNITS
		);
	}

	/**
	 * Add useful taxonomy columns to the administration screen.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public function filter_admin_columns( array $columns ): array {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;

			if ( 'name' === $key ) {
				$updated['psm_unit_code'] = __(
					'Mã đơn vị',
					'power-schedule-manager'
				);

				$updated['psm_public'] = __(
					'Công khai',
					'power-schedule-manager'
				);
			}
		}

		return $updated;
	}

	/**
	 * Render custom taxonomy columns.
	 *
	 * @param string $content     Existing content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 *
	 * @return string
	 */
	public function render_admin_column(
		string $content,
		string $column_name,
		int $term_id
	): string {
		if ( 'psm_unit_code' === $column_name ) {
			$unit_code = get_term_meta(
				$term_id,
				self::META_UNIT_CODE,
				true
			);

			if ( ! is_string( $unit_code ) || '' === $unit_code ) {
				return '&mdash;';
			}

			return esc_html( $unit_code );
		}

		if ( 'psm_public' === $column_name ) {
			$is_public = get_term_meta(
				$term_id,
				self::META_IS_PUBLIC,
				true
			);

			return self::sanitize_boolean( $is_public )
				? esc_html__( 'Có', 'power-schedule-manager' )
				: esc_html__( 'Không', 'power-schedule-manager' );
		}

		return $content;
	}

	/**
	 * Build a normalized public term slug.
	 *
	 * Example:
	 *
	 * Điện lực Đà Lạt -> da-lat
	 *
	 * @param string $area_name Area name.
	 *
	 * @return string
	 */
	public static function build_term_slug( string $area_name ): string {
		$area_name = trim( $area_name );

		$area_name = preg_replace(
			'/\A(?:điện\s+lực|công\s+ty\s+điện\s+lực)\s+/iu',
			'',
			$area_name
		);

		if ( ! is_string( $area_name ) || '' === $area_name ) {
			return '';
		}

		return sanitize_title( $area_name );
	}
}
