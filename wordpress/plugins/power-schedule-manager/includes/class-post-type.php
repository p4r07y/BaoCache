<?php
/**
 * Custom Post Type lịch cúp điện.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Đăng ký CPT đại diện cho lịch theo đơn vị và ngày.
 *
 * Custom table là nguồn dữ liệu sự kiện chính.
 * CPT cung cấp URL, nội dung biên tập và bề mặt SEO.
 */
final class Power_Schedule_Manager_Post_Type {

	/**
	 * Tên CPT.
	 */
	public const string POST_TYPE = 'psm_schedule';

	/**
	 * Archive và permalink slug.
	 */
	public const string REWRITE_SLUG = 'lich-cup-dien';

	/**
	 * REST base.
	 */
	public const string REST_BASE = 'power-schedules';

	/**
	 * Định dạng ngày trong permalink.
	 */
	public const string PERMALINK_DATE_FORMAT = 'd-m-Y';

	/**
	 * Định dạng ngày hiển thị.
	 */
	public const string DISPLAY_DATE_FORMAT = 'd/m/Y';

	/**
	 * Meta key mã đơn vị.
	 */
	public const string META_UNIT_CODE = '_psm_unit_code';

	/**
	 * Meta key ngày địa phương.
	 */
	public const string META_LOCAL_DATE = '_psm_local_date';

	/**
	 * Meta key số sự kiện.
	 */
	public const string META_EVENT_COUNT = '_psm_event_count';

	/**
	 * Meta key thời điểm cập nhật UTC.
	 */
	public const string META_LAST_UPDATED_UTC =
		'_psm_last_updated_utc';

	/**
	 * Đăng ký WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'init',
			array( $this, 'register_post_type' ),
			10
		);

		add_action(
			'init',
			array( $this, 'register_post_meta' ),
			11
		);

		add_filter(
			'enter_title_here',
			array( $this, 'filter_title_placeholder' ),
			10,
			2
		);
	}

	/**
	 * Đăng ký CPT.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Khi WordPress không thể đăng ký CPT.
	 */
	public function register_post_type(): void {
		$result = register_post_type(
			self::POST_TYPE,
			array(
				'labels' => $this->labels(),

				'description' => __(
					'Lịch ngừng, giảm cung cấp điện theo đơn vị và ngày.',
					'power-schedule-manager'
				),

				/*
				 * Public và SEO.
				 */
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'has_archive'         => self::REWRITE_SLUG,
				'query_var'           => 'psm_schedule',

				/*
				 * Giao diện quản trị.
				 *
				 * Menu chính được tạo bởi class-admin.php.
				 */
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_admin_bar' => true,
				'show_in_nav_menus' => true,
				'menu_icon'         => 'dashicons-calendar-alt',

				/*
				 * REST và Block Editor.
				 */
				'show_in_rest'   => true,
				'rest_base'      => self::REST_BASE,
				'rest_namespace' => 'wp/v2',

				/*
				 * Permalink:
				 * /lich-cup-dien/{unit-slug}-{dd-mm-yyyy}/
				 */
				'rewrite' => array(
					'slug'       => self::REWRITE_SLUG,
					'with_front' => false,
					'feeds'      => false,
					'pages'      => true,
					'ep_mask'    => EP_PERMALINK,
				),

				/*
				 * Một post đại diện cho một đơn vị/ngày.
				 */
				'hierarchical' => false,

				/*
				 * Nội dung biên tập hỗ trợ SEO.
				 *
				 * Bảng lịch chi tiết được render từ custom table.
				 */
				'supports' => array(
					'title',
					'editor',
					'excerpt',
					'author',
					'revisions',
				),

				/*
				 * Capability riêng.
				 */
				'capability_type' => array(
					'psm_schedule',
					'psm_schedules',
				),

				'capabilities' =>
					Power_Schedule_Manager_Capabilities
						::post_type_capabilities(),

				'map_meta_cap' => true,

				/*
				 * Không xóa lịch khi xóa tài khoản tác giả.
				 */
				'delete_with_user' => false,

				/*
				 * Cho phép WordPress Export.
				 */
				'can_export' => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException(
				'post_type_registration_failed'
			);
		}
	}

	/**
	 * Tạo slug chi tiết theo đơn vị và ngày.
	 *
	 * Ví dụ:
	 * - Unit slug: da-lat
	 * - Date: 24/07/2026
	 * - Result: da-lat-24-07-2026
	 *
	 * @param string            $unit_slug Slug đơn vị.
	 * @param DateTimeInterface $date      Ngày lịch.
	 * @return string
	 *
	 * @throws InvalidArgumentException Khi unit slug không hợp lệ.
	 */
	public static function build_schedule_slug(
		string $unit_slug,
		DateTimeInterface $date
	): string {
		$unit_slug = sanitize_title(
			$unit_slug
		);

		if ( '' === $unit_slug ) {
			throw new InvalidArgumentException(
				'Schedule unit slug cannot be empty.'
			);
		}

		$date_part = $date->format(
			self::PERMALINK_DATE_FORMAT
		);

		return sanitize_title(
			$unit_slug . '-' . $date_part
		);
	}

	/**
	 * Tạo tiêu đề mặc định cho lịch.
	 *
	 * Ví dụ:
	 * Lịch cúp điện Đà Lạt ngày 24/07/2026
	 *
	 * @param string            $unit_name Tên đơn vị.
	 * @param DateTimeInterface $date      Ngày lịch.
	 * @return string
	 *
	 * @throws InvalidArgumentException Khi tên đơn vị rỗng.
	 */
	public static function build_schedule_title(
		string $unit_name,
		DateTimeInterface $date
	): string {
		$unit_name = self::location_name( $unit_name );

		if ( '' === $unit_name ) {
			throw new InvalidArgumentException(
				'Schedule unit name cannot be empty.'
			);
		}

		return sprintf(
			/* translators: 1: Electricity unit name, 2: Schedule date. */
			__(
				'Lịch cúp điện %1$s ngày %2$s',
				'power-schedule-manager'
			),
			$unit_name,
			$date->format(
				self::DISPLAY_DATE_FORMAT
			)
		);
	}

	/**
	 * Return the public locality name without an organizational prefix.
	 *
	 * Examples:
	 * - "Điện lực Đà Lạt" becomes "Đà Lạt".
	 * - "Điện lực Bảo Lộc" becomes "Bảo Lộc".
	 *
	 * @param string $unit_name Electricity unit name.
	 * @return string
	 */
	public static function location_name( string $unit_name ): string {
		$unit_name = sanitize_text_field( $unit_name );
		$location = preg_replace(
			'/^\s*Điện\s+lực\s+/iu',
			'',
			$unit_name
		);
		$location = is_string( $location )
			? trim( $location )
			: $unit_name;

		return '' !== $location ? $location : $unit_name;
	}

	/**
	 * Đăng ký metadata nội bộ.
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$common_arguments = array(
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => array(
				$this,
				'authorize_meta_update',
			),
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_UNIT_CODE,
			array_merge(
				$common_arguments,
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => array(
						$this,
						'sanitize_unit_code',
					),
				)
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_LOCAL_DATE,
			array_merge(
				$common_arguments,
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => array(
						$this,
						'sanitize_local_date',
					),
				)
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_EVENT_COUNT,
			array_merge(
				$common_arguments,
				array(
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => array(
						$this,
						'sanitize_event_count',
					),
				)
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_LAST_UPDATED_UTC,
			array_merge(
				$common_arguments,
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => array(
						$this,
						'sanitize_utc_datetime',
					),
				)
			)
		);
	}

	/**
	 * Kiểm tra quyền cập nhật protected metadata.
	 *
	 * Nonce vẫn phải được controller kiểm tra riêng.
	 *
	 * @param bool               $allowed   Kết quả hiện tại.
	 * @param string             $meta_key  Meta key.
	 * @param int                $object_id Post ID.
	 * @param int                $user_id   User ID.
	 * @param string             $cap       Capability.
	 * @param array<int, string> $caps      Primitive capabilities.
	 * @return bool
	 */
	public function authorize_meta_update(
		bool $allowed,
		string $meta_key,
		int $object_id,
		int $user_id = 0,
		string $cap = '',
		array $caps = array()
	): bool {
		unset(
			$allowed,
			$meta_key,
			$user_id,
			$cap,
			$caps
		);

		if ( $object_id > 0 ) {
			return current_user_can(
				'edit_post',
				$object_id
			);
		}

		return current_user_can(
			Power_Schedule_Manager_Capabilities::EDIT_POSTS
		);
	}

	/**
	 * Làm sạch mã đơn vị.
	 *
	 * @param mixed $value Giá trị đầu vào.
	 * @return string
	 */
	public function sanitize_unit_code(
		mixed $value
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strtoupper(
			sanitize_text_field(
				(string) $value
			)
		);

		if (
			1 !== preg_match(
				'/^[A-Z0-9_-]{2,32}$/',
				$value
			)
		) {
			return '';
		}

		return $value;
	}

	/**
	 * Làm sạch ngày địa phương.
	 *
	 * Database sử dụng Y-m-d, ví dụ 2026-07-24.
	 *
	 * @param mixed $value Giá trị đầu vào.
	 * @return string
	 */
	public function sanitize_local_date(
		mixed $value
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field(
			(string) $value
		);

		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$value,
			new DateTimeZone(
				POWER_SCHEDULE_MANAGER_TIMEZONE
			)
		);

		if (
			false === $date
			|| $date->format( 'Y-m-d' ) !== $value
			|| self::date_has_errors()
		) {
			return '';
		}

		return $value;
	}

	/**
	 * Làm sạch số sự kiện.
	 *
	 * @param mixed $value Giá trị đầu vào.
	 * @return int
	 */
	public function sanitize_event_count(
		mixed $value
	): int {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}

		return max(
			0,
			(int) $value
		);
	}

	/**
	 * Làm sạch datetime UTC.
	 *
	 * @param mixed $value Giá trị đầu vào.
	 * @return string
	 */
	public function sanitize_utc_datetime(
		mixed $value
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field(
			(string) $value
		);

		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$value,
			new DateTimeZone( 'UTC' )
		);

		if (
			false === $date
			|| $date->format( 'Y-m-d H:i:s' ) !== $value
			|| self::date_has_errors()
		) {
			return '';
		}

		return $value;
	}

	/**
	 * Kiểm tra lỗi DateTime.
	 *
	 * @return bool
	 */
	private static function date_has_errors(): bool {
		$errors = DateTimeImmutable::getLastErrors();

		return is_array( $errors )
			&& (
				(int) $errors['warning_count'] > 0
				|| (int) $errors['error_count'] > 0
			);
	}

	/**
	 * Thay placeholder trường tiêu đề.
	 *
	 * @param string  $placeholder Placeholder hiện tại.
	 * @param WP_Post $post        Post hiện tại.
	 * @return string
	 */
	public function filter_title_placeholder(
		string $placeholder,
		WP_Post $post
	): string {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $placeholder;
		}

		return __(
			'Ví dụ: Lịch cúp điện Đà Lạt ngày 24/07/2026',
			'power-schedule-manager'
		);
	}

	/**
	 * Label CPT.
	 *
	 * @return array<string, string>
	 */
	private function labels(): array {
		return array(
			'name' => _x(
				'Lịch cúp điện',
				'Post type general name',
				'power-schedule-manager'
			),
			'singular_name' => _x(
				'Lịch cúp điện',
				'Post type singular name',
				'power-schedule-manager'
			),
			'menu_name' => _x(
				'Lịch cúp điện',
				'Admin menu text',
				'power-schedule-manager'
			),
			'name_admin_bar' => _x(
				'Lịch cúp điện',
				'Admin bar text',
				'power-schedule-manager'
			),
			'add_new' => __(
				'Thêm lịch',
				'power-schedule-manager'
			),
			'add_new_item' => __(
				'Thêm lịch cúp điện',
				'power-schedule-manager'
			),
			'new_item' => __(
				'Lịch mới',
				'power-schedule-manager'
			),
			'edit_item' => __(
				'Chỉnh sửa lịch',
				'power-schedule-manager'
			),
			'view_item' => __(
				'Xem lịch',
				'power-schedule-manager'
			),
			'view_items' => __(
				'Xem lịch cúp điện',
				'power-schedule-manager'
			),
			'all_items' => __(
				'Tất cả lịch',
				'power-schedule-manager'
			),
			'search_items' => __(
				'Tìm lịch cúp điện',
				'power-schedule-manager'
			),
			'not_found' => __(
				'Không tìm thấy lịch cúp điện.',
				'power-schedule-manager'
			),
			'not_found_in_trash' => __(
				'Không có lịch trong thùng rác.',
				'power-schedule-manager'
			),
			'archives' => __(
				'Kho lưu trữ lịch cúp điện',
				'power-schedule-manager'
			),
			'attributes' => __(
				'Thuộc tính lịch',
				'power-schedule-manager'
			),
			'filter_items_list' => __(
				'Lọc danh sách lịch',
				'power-schedule-manager'
			),
			'items_list_navigation' => __(
				'Điều hướng danh sách lịch',
				'power-schedule-manager'
			),
			'items_list' => __(
				'Danh sách lịch cúp điện',
				'power-schedule-manager'
			),
			'item_published' => __(
				'Lịch đã được xuất bản.',
				'power-schedule-manager'
			),
			'item_published_privately' => __(
				'Lịch đã được xuất bản riêng tư.',
				'power-schedule-manager'
			),
			'item_reverted_to_draft' => __(
				'Lịch đã chuyển về bản nháp.',
				'power-schedule-manager'
			),
			'item_scheduled' => __(
				'Lịch đã được lên lịch xuất bản.',
				'power-schedule-manager'
			),
			'item_updated' => __(
				'Lịch đã được cập nhật.',
				'power-schedule-manager'
			),
			'item_link' => __(
				'Liên kết lịch',
				'power-schedule-manager'
			),
			'item_link_description' => __(
				'Liên kết đến lịch cúp điện.',
				'power-schedule-manager'
			),
		);
	}
}
