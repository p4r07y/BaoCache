<?php
/**
 * Phân quyền của Cúp Điện Lâm Đồng.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quản lý capability của plugin.
 *
 * Nguyên tắc:
 * - Administrator có toàn bộ quyền.
 * - Editor được tạo, sửa, xuất bản và nhập lịch.
 * - Editor không được xóa lịch, quản lý đơn vị, xem log hoặc sửa cài đặt.
 * - Capability không bị xóa khi deactivate.
 */
final class Power_Schedule_Manager_Capabilities {

	/**
	 * Phiên bản capability.
	 */
	public const string VERSION = '1.0.0';

	/**
	 * Option lưu phiên bản capability.
	 */
	public const string VERSION_OPTION =
		'power_schedule_manager_capability_version';

	/*
	 * Primitive capability của CPT.
	 */
	public const string EDIT_POSTS =
		'edit_psm_schedules';

	public const string EDIT_OTHERS_POSTS =
		'edit_others_psm_schedules';

	public const string EDIT_PRIVATE_POSTS =
		'edit_private_psm_schedules';

	public const string EDIT_PUBLISHED_POSTS =
		'edit_published_psm_schedules';

	public const string PUBLISH_POSTS =
		'publish_psm_schedules';

	public const string READ_PRIVATE_POSTS =
		'read_private_psm_schedules';

	public const string DELETE_POSTS =
		'delete_psm_schedules';

	public const string DELETE_PRIVATE_POSTS =
		'delete_private_psm_schedules';

	public const string DELETE_PUBLISHED_POSTS =
		'delete_published_psm_schedules';

	public const string DELETE_OTHERS_POSTS =
		'delete_others_psm_schedules';

	/*
	 * Capability nghiệp vụ.
	 */
	public const string IMPORT_SCHEDULES =
		'import_psm_schedules';

	public const string MANAGE_UNITS =
		'manage_psm_units';

	public const string VIEW_LOGS =
		'view_psm_logs';

	public const string MANAGE_SETTINGS =
		'manage_psm_settings';

	/**
	 * Capability của administrator.
	 *
	 * @var array<int, string>
	 */
	private const array ADMINISTRATOR_CAPABILITIES = array(
		self::EDIT_POSTS,
		self::EDIT_OTHERS_POSTS,
		self::EDIT_PRIVATE_POSTS,
		self::EDIT_PUBLISHED_POSTS,
		self::PUBLISH_POSTS,
		self::READ_PRIVATE_POSTS,
		self::DELETE_POSTS,
		self::DELETE_PRIVATE_POSTS,
		self::DELETE_PUBLISHED_POSTS,
		self::DELETE_OTHERS_POSTS,
		self::IMPORT_SCHEDULES,
		self::MANAGE_UNITS,
		self::VIEW_LOGS,
		self::MANAGE_SETTINGS,
	);

	/**
	 * Capability của editor.
	 *
	 * Editor không được xóa dữ liệu hoặc thay đổi cấu hình hệ thống.
	 *
	 * @var array<int, string>
	 */
	private const array EDITOR_CAPABILITIES = array(
		self::EDIT_POSTS,
		self::EDIT_OTHERS_POSTS,
		self::EDIT_PRIVATE_POSTS,
		self::EDIT_PUBLISHED_POSTS,
		self::PUBLISH_POSTS,
		self::READ_PRIVATE_POSTS,
		self::IMPORT_SCHEDULES,
	);

	/**
	 * Cài capability runtime bị lỗi hay không.
	 */
	private bool $install_failed = false;

	/**
	 * Đăng ký kiểm tra capability version.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_init',
			array( $this, 'maybe_install' ),
			5
		);
	}

	/**
	 * Cài capability khi activation.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Khi administrator role không tồn tại.
	 */
	public static function install(): void {
		$administrator = get_role(
			'administrator'
		);

		if ( ! $administrator instanceof WP_Role ) {
			throw new RuntimeException(
				'administrator_role_not_found'
			);
		}

		self::add_capabilities_to_role(
			$administrator,
			self::ADMINISTRATOR_CAPABILITIES
		);

		$editor = get_role( 'editor' );

		if ( $editor instanceof WP_Role ) {
			self::add_capabilities_to_role(
				$editor,
				self::EDITOR_CAPABILITIES
			);
		}

		self::store_version();
	}

	/**
	 * Cài lại capability khi version thay đổi.
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		$installed_version = get_option(
			self::VERSION_OPTION,
			'0.0.0'
		);

		if (
			is_string( $installed_version )
			&& version_compare(
				$installed_version,
				self::VERSION,
				'>='
			)
		) {
			return;
		}

		try {
			self::install();
		} catch ( Throwable $throwable ) {
			$this->install_failed = true;

			self::log_debug_error(
				'capability_install',
				$throwable
			);

			add_action(
				'admin_notices',
				array(
					$this,
					'render_install_error_notice',
				)
			);
		}
	}

	/**
	 * Hiển thị lỗi cài capability.
	 *
	 * @return void
	 */
	public function render_install_error_notice(): void {
		if (
			! $this->install_failed
			|| ! current_user_can( 'activate_plugins' )
		) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Cúp Điện Lâm Đồng chưa thể cập nhật quyền người dùng. Một số chức năng quản trị có thể tạm thời không khả dụng.',
				'power-schedule-manager'
			)
		);
	}

	/**
	 * Xóa capability của plugin khỏi tất cả role.
	 *
	 * Chỉ được gọi bởi uninstall khi người dùng đã chọn xóa dữ liệu.
	 * Không gọi khi deactivate.
	 *
	 * @return void
	 */
	public static function remove(): void {
		$wp_roles = wp_roles();

		if ( ! $wp_roles instanceof WP_Roles ) {
			return;
		}

		foreach (
			array_keys( $wp_roles->roles )
			as $role_name
		) {
			$role = get_role(
				(string) $role_name
			);

			if ( ! $role instanceof WP_Role ) {
				continue;
			}

			foreach (
				self::all_capabilities()
				as $capability
			) {
				$role->remove_cap(
					$capability
				);
			}
		}

		delete_option(
			self::VERSION_OPTION
		);
	}

	/**
	 * Capability map dùng khi đăng ký CPT.
	 *
	 * Ba capability số ít là meta capability và không được thêm trực tiếp
	 * vào role. WordPress map chúng sang primitive capability.
	 *
	 * @return array<string, string>
	 */
	public static function post_type_capabilities(): array {
		return array(
			'edit_post' =>
				'edit_psm_schedule',

			'read_post' =>
				'read_psm_schedule',

			'delete_post' =>
				'delete_psm_schedule',

			'edit_posts' =>
				self::EDIT_POSTS,

			'edit_others_posts' =>
				self::EDIT_OTHERS_POSTS,

			'publish_posts' =>
				self::PUBLISH_POSTS,

			'read_private_posts' =>
				self::READ_PRIVATE_POSTS,

			'delete_posts' =>
				self::DELETE_POSTS,

			'delete_private_posts' =>
				self::DELETE_PRIVATE_POSTS,

			'delete_published_posts' =>
				self::DELETE_PUBLISHED_POSTS,

			'delete_others_posts' =>
				self::DELETE_OTHERS_POSTS,

			'edit_private_posts' =>
				self::EDIT_PRIVATE_POSTS,

			'edit_published_posts' =>
				self::EDIT_PUBLISHED_POSTS,

			'create_posts' =>
				self::EDIT_POSTS,
		);
	}

	/**
	 * Capability map dùng cho taxonomy đơn vị/khu vực.
	 *
	 * Editor được gán term vào lịch nhưng không được tạo, sửa hoặc xóa term.
	 *
	 * @return array<string, string>
	 */
	public static function taxonomy_capabilities(): array {
		return array(
			'manage_terms' =>
				self::MANAGE_UNITS,

			'edit_terms' =>
				self::MANAGE_UNITS,

			'delete_terms' =>
				self::MANAGE_UNITS,

			'assign_terms' =>
				self::EDIT_POSTS,
		);
	}

	/**
	 * Kiểm tra quyền nhập lịch.
	 *
	 * @return bool
	 */
	public static function current_user_can_import(): bool {
		return current_user_can(
			self::IMPORT_SCHEDULES
		);
	}

	/**
	 * Kiểm tra quyền quản lý đơn vị.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_units(): bool {
		return current_user_can(
			self::MANAGE_UNITS
		);
	}

	/**
	 * Kiểm tra quyền xem log.
	 *
	 * @return bool
	 */
	public static function current_user_can_view_logs(): bool {
		return current_user_can(
			self::VIEW_LOGS
		);
	}

	/**
	 * Kiểm tra quyền thay đổi cài đặt.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_settings(): bool {
		return current_user_can(
			self::MANAGE_SETTINGS
		);
	}

	/**
	 * Thêm capability vào role.
	 *
	 * @param WP_Role            $role         Role.
	 * @param array<int, string> $capabilities Capability.
	 * @return void
	 */
	private static function add_capabilities_to_role(
		WP_Role $role,
		array $capabilities
	): void {
		foreach ( $capabilities as $capability ) {
			$role->add_cap(
				$capability,
				true
			);
		}
	}

	/**
	 * Lấy toàn bộ capability của plugin.
	 *
	 * @return array<int, string>
	 */
	private static function all_capabilities(): array {
		return array_values(
			array_unique(
				self::ADMINISTRATOR_CAPABILITIES
			)
		);
	}

	/**
	 * Lưu capability version.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Khi option không được lưu.
	 */
	private static function store_version(): void {
		$updated = update_option(
			self::VERSION_OPTION,
			self::VERSION,
			false
		);

		$stored = get_option(
			self::VERSION_OPTION,
			''
		);

		if (
			! $updated
			&& self::VERSION !== $stored
		) {
			throw new RuntimeException(
				'capability_version_not_saved'
			);
		}
	}

	/**
	 * Ghi lỗi khi WP_DEBUG bật.
	 *
	 * @param string    $context   Ngữ cảnh.
	 * @param Throwable $throwable Exception.
	 * @return void
	 */
	private static function log_debug_error(
		string $context,
		Throwable $throwable
	): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context = preg_replace(
			'/[^a-z0-9_-]/',
			'',
			strtolower( $context )
		);

		error_log(
			sprintf(
				'Cúp Điện Lâm Đồng capabilities [%1$s]: %2$s',
				substr( (string) $context, 0, 80 ),
				power_schedule_manager_sanitize_log_value(
					$throwable->getMessage()
				)
			)
		);
	}
}
