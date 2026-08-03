<?php
/**
 * Plugin administration controller.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers administration menus and page controllers.
 */
final class Power_Schedule_Manager_Admin {

	/**
	 * Main menu slug.
	 */
	public const string MENU_SLUG =
		'power-schedule-manager';

	/**
	 * Import page slug.
	 */
	public const string IMPORT_SLUG =
		'power-schedule-manager-import';

	/**
	 * History page slug.
	 */
	public const string HISTORY_SLUG =
		'power-schedule-manager-history';

	/**
	 * System health page slug.
	 */
	public const string SYSTEM_SLUG =
		'power-schedule-manager-system';

	/**
	 * Backup and restore page slug.
	 */
	public const string BACKUP_SLUG =
		'power-schedule-manager-backup';

	/**
	 * Settings page slug.
	 */
	public const string SETTINGS_SLUG =
		'power-schedule-manager-settings';

	/**
	 * External data-source settings page slug.
	 */
	public const string DATA_SOURCES_SLUG =
		'power-schedule-manager-data-sources';

	/**
	 * Help page slug.
	 */
	public const string HELP_SLUG =
		'power-schedule-manager-help';

	/**
	 * Plugin admin screen IDs.
	 *
	 * @var array<int, string>
	 */
	private array $screen_ids = array();

	/**
	 * Register administration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'plugin_action_links_'
				. POWER_SCHEDULE_MANAGER_BASENAME,
			array( $this, 'plugin_action_links' )
		);

		add_action(
			'admin_menu',
			array( $this, 'register_menus' )
		);

		add_action(
			'admin_menu',
			array( $this, 'reorder_submenus' ),
			999
		);

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_assets' )
		);

		add_action(
			'admin_init',
			array( $this, 'register_settings' )
		);

		add_action(
			'admin_init',
			array( $this, 'register_privacy_policy_content' ),
			20
		);

		add_action(
			'wp_ajax_psm_test_map_tile',
			array( $this, 'ajax_test_map_tile' )
		);

		add_action(
			'wp_ajax_psm_test_notification',
			array( $this, 'ajax_test_notification' )
		);

		add_action(
			'admin_post_psm_retry_notifications',
			array( $this, 'retry_failed_notifications' )
		);

		add_action(
			'admin_post_psm_process_cloudflare_queue',
			array( $this, 'process_cloudflare_queue' )
		);

		add_action(
			'admin_post_psm_save_application_api',
			array( $this, 'save_application_api' )
		);

		add_action(
			'admin_post_psm_reset_application_api_stats',
			array( $this, 'reset_application_api_stats' )
		);

		add_action(
			'transition_post_status',
			array(
				'Power_Schedule_Manager_Dashboard_Stats',
				'invalidate_on_post_transition',
			),
			10,
			3
		);

		$this->register_subservices();
	}

	/**
	 * Add system and settings shortcuts to the plugin row.
	 *
	 * @param array<int|string,string> $links Existing plugin action links.
	 * @return array<int|string,string>
	 */
	public function plugin_action_links( array $links ): array {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			return $links;
		}

		$system_url = add_query_arg(
			array(
				'page' => self::SYSTEM_SLUG,
			),
			admin_url( 'admin.php' )
		);

		$settings_url = add_query_arg(
			array(
				'page' => self::SETTINGS_SLUG,
			),
			admin_url( 'admin.php' )
		);

		$backup_url = add_query_arg(
			array(
				'page' => self::BACKUP_SLUG,
			),
			admin_url( 'admin.php' )
		);

		$shortcuts = array(
			'system_health' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $system_url ),
				esc_html__(
					'Trạng thái hệ thống',
					'power-schedule-manager'
				)
			),
			'backup'        => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $backup_url ),
				esc_html__(
					'Sao lưu',
					'power-schedule-manager'
				)
			),
			'settings'      => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_url ),
				esc_html__(
					'Cài đặt',
					'power-schedule-manager'
				)
			),
		);

		return $shortcuts + $links;
	}

	/**
	 * Register plugin administration menus.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		$main_screen = add_menu_page(
			__(
				'Cúp Điện Lâm Đồng',
				'power-schedule-manager'
			),
			__(
				'Cúp Điện Lâm Đồng',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::EDIT_POSTS,
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-calendar-alt',
			26
		);

		$dashboard_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Tổng quan lịch điện',
				'power-schedule-manager'
			),
			__(
				'Tổng quan',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::EDIT_POSTS,
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' )
		);

		$import_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Nhập dữ liệu lịch điện',
				'power-schedule-manager'
			),
			__(
				'Nhập dữ liệu',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::IMPORT_SCHEDULES,
			self::IMPORT_SLUG,
			array( $this, 'render_import_page' )
		);

		$history_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Lịch sử nhập dữ liệu',
				'power-schedule-manager'
			),
			__(
				'Lịch sử đồng bộ',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::VIEW_LOGS,
			self::HISTORY_SLUG,
			array( $this, 'render_history_page' )
		);

		$system_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Trung tâm kiểm tra hệ thống',
				'power-schedule-manager'
			),
			__(
				'Trạng thái hệ thống',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::SYSTEM_SLUG,
			array( $this, 'render_system_page' )
		);

		$backup_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Sao lưu và khôi phục dữ liệu',
				'power-schedule-manager'
			),
			__(
				'Sao lưu & khôi phục',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::BACKUP_SLUG,
			array( $this, 'render_backup_page' )
		);

		$settings_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Cài đặt lịch điện',
				'power-schedule-manager'
			),
			__(
				'Cài đặt',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);

		$data_sources_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Nguồn dữ liệu và API',
				'power-schedule-manager'
			),
			__(
				'Nguồn dữ liệu & API',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::DATA_SOURCES_SLUG,
			array( $this, 'render_data_sources_page' )
		);

		$help_screen = add_submenu_page(
			self::MENU_SLUG,
			__(
				'Hướng dẫn sử dụng Cúp Điện Lâm Đồng',
				'power-schedule-manager'
			),
			__(
				'Hướng dẫn sử dụng',
				'power-schedule-manager'
			),
			Power_Schedule_Manager_Capabilities::EDIT_POSTS,
			self::HELP_SLUG,
			array( $this, 'render_help_page' )
		);

		$screens = array(
			$main_screen,
			$dashboard_screen,
			$import_screen,
			$history_screen,
			$system_screen,
			$backup_screen,
			$settings_screen,
			$data_sources_screen,
			$help_screen,
		);

		$this->screen_ids = array_values(
			array_filter(
				$screens,
				'is_string'
			)
		);
	}

	/**
	 * Order plugin submenus by the normal editorial workflow.
	 *
	 * @return void
	 */
	public function reorder_submenus(): void {
		global $submenu;

		if (
			! isset( $submenu[ self::MENU_SLUG ] )
			|| ! is_array( $submenu[ self::MENU_SLUG ] )
		) {
			return;
		}

		$weights = array(
			self::MENU_SLUG => 10,
			'edit.php?post_type=psm_schedule' => 20,
			self::IMPORT_SLUG => 30,
			Power_Schedule_Manager_Place_Library::MENU_SLUG => 40,
			Power_Schedule_Manager_Place_Library::EDIT_MENU_SLUG => 41,
			self::HISTORY_SLUG => 50,
			self::SYSTEM_SLUG => 60,
			Power_Schedule_Manager_Lottery::MENU_SLUG => 70,
			'power-schedule-manager-coffee-prices' => 71,
			'power-schedule-manager-gold-prices' => 72,
			self::BACKUP_SLUG => 90,
			self::SETTINGS_SLUG => 100,
			self::DATA_SOURCES_SLUG => 101,
			self::HELP_SLUG => 110,
		);

		usort(
			$submenu[ self::MENU_SLUG ],
			static function ( array $left, array $right ) use (
				$weights
			): int {
				$left_slug  = (string) ( $left[2] ?? '' );
				$right_slug = (string) ( $right[2] ?? '' );

				$get_weight = static function (
					string $slug
				) use ( $weights ): int {
					if ( isset( $weights[ $slug ] ) ) {
						return $weights[ $slug ];
					}

					if ( str_starts_with( $slug, 'edit.php?post_type=' ) ) {
						return 20;
					}

					if ( str_starts_with( $slug, 'edit-tags.php?taxonomy=' ) ) {
						return 50;
					}

					return 90;
				};

				return $get_weight( $left_slug )
					<=> $get_weight( $right_slug );
			}
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'power_schedule_manager_settings',
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'description'       => __(
					'Cài đặt Cúp Điện Lâm Đồng.',
					'power-schedule-manager'
				),
				'sanitize_callback' => array(
					$this,
					'sanitize_settings',
				),
				'default'           => self::default_settings(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Suggest transparent privacy-policy text for external map services.
	 *
	 * @return void
	 */
	public function register_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = wp_kses_post(
			__(
				'<p>Cúp Điện Lâm Đồng chỉ tải bản đồ khi một lịch cúp điện có dữ liệu vị trí và người truy cập mở bản đồ. Khi sử dụng OpenStreetMap, MapTiler, Stadia Maps hoặc máy chủ tile tùy chỉnh, trình duyệt có thể gửi địa chỉ IP, User-Agent và thông tin yêu cầu kỹ thuật đến nhà cung cấp đã cấu hình.</p><p>Khi quản trị viên chủ động tìm một tuyến đường trong thư viện bản đồ, máy chủ website gửi tên đường và khung tọa độ tìm kiếm đến dịch vụ Overpass API của OpenStreetMap. Plugin không tự động gửi nội dung lịch điện hoặc thông tin tài khoản WordPress trong yêu cầu này.</p><p>Biểu mẫu hợp tác có thể lưu tên, email, số điện thoại, đơn vị, nhu cầu và lời nhắn để quản trị viên phản hồi. Địa chỉ IP không được lưu thô; plugin chỉ lưu giá trị băm dùng để hạn chế gửi biểu mẫu liên tục. Dữ liệu khai báo hỗ trợ từ phiên bản cũ có thể tiếp tục được giữ theo thời hạn lưu để đối soát, nhưng không còn được tiếp nhận từ shortcode công khai.</p><p>Quản trị viên có thể tắt bản đồ hoặc sử dụng máy chủ tile riêng trong phần cài đặt plugin. Cần bổ sung nhà cung cấp đang sử dụng vào chính sách quyền riêng tư của website.</p>',
				'power-schedule-manager'
			)
		);

		wp_add_privacy_policy_content(
			__( 'Cúp Điện Lâm Đồng', 'power-schedule-manager' ),
			$content
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $settings Raw settings.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_settings(
		mixed $settings
	): array {
		if (
			! Power_Schedule_Manager_Capabilities::current_user_can_manage_settings()
			|| ! is_array( $settings )
		) {
			return get_option(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				self::default_settings()
			);
		}

		$current_settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			self::default_settings()
		);
		$current_settings = is_array( $current_settings )
			? $current_settings
			: self::default_settings();

		$maptiler_key_encrypted = self::sanitize_secret_setting(
			$settings,
			$current_settings,
			'maptiler_key',
			'maptiler_key_encrypted',
			'maptiler_key_clear'
		);
		$stadia_key_encrypted = self::sanitize_secret_setting(
			$settings,
			$current_settings,
			'stadia_key',
			'stadia_key_encrypted',
			'stadia_key_clear'
		);
		$cloudflare_api_token_encrypted =
			self::sanitize_secret_setting(
				$settings,
				$current_settings,
				'cloudflare_api_token',
				'cloudflare_api_token_encrypted',
				'cloudflare_api_token_clear'
			);
		$cloudflare_turnstile_secret_encrypted =
			self::sanitize_secret_setting(
				$settings,
				$current_settings,
				'cloudflare_turnstile_secret',
				'cloudflare_turnstile_secret_encrypted',
				'cloudflare_turnstile_secret_clear'
			);
		$onesignal_rest_api_key_encrypted = self::sanitize_secret_setting(
			$settings,
			$current_settings,
			'onesignal_rest_api_key',
			'onesignal_rest_api_key_encrypted',
			'onesignal_rest_api_key_clear'
		);
		$telegram_bot_token_encrypted = self::sanitize_secret_setting(
			$settings,
			$current_settings,
			'telegram_bot_token',
			'telegram_bot_token_encrypted',
			'telegram_bot_token_clear'
		);
		$webhook_secret_encrypted = self::sanitize_secret_setting(
			$settings,
			$current_settings,
			'webhook_secret',
			'webhook_secret_encrypted',
			'webhook_secret_clear'
		);
		$zalo_access_token_encrypted = self::sanitize_secret_setting(
			$settings,
			$current_settings,
			'zalo_access_token',
			'zalo_access_token_encrypted',
			'zalo_access_token_clear'
		);

		$provider = isset( $settings['map_provider'] )
			&& is_string( $settings['map_provider'] )
			? sanitize_key( $settings['map_provider'] )
			: 'osm';

		if (
			! in_array(
				$provider,
				array(
					'osm',
					'maptiler',
					'stadia',
					'custom',
					'disabled',
				),
				true
			)
		) {
			$provider = 'osm';
		}

		$tile_url = isset( $settings['map_tile_url'] )
			&& is_string( $settings['map_tile_url'] )
			? trim( wp_unslash( $settings['map_tile_url'] ) )
			: '';

		if (
			'custom' === $provider
			&& ! self::valid_tile_template( $tile_url )
		) {
			add_settings_error(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				'power_schedule_manager_invalid_tile_url',
				__(
					'URL tile tùy chỉnh không hợp lệ. Bản đồ đã được tắt để tránh lỗi ngoài website.',
					'power-schedule-manager'
				),
				'error'
			);

			$provider = 'disabled';
		}

		if (
			'custom' !== $provider
			|| ! self::valid_tile_template( $tile_url )
		) {
			$tile_url = '';
		}

		$attribution = isset( $settings['map_attribution'] )
			&& is_string( $settings['map_attribution'] )
			? wp_kses(
				wp_unslash( $settings['map_attribution'] ),
				array(
					'a' => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
				)
			)
			: '';

		$max_zoom = isset( $settings['map_max_zoom'] )
			&& is_numeric( $settings['map_max_zoom'] )
			? min(
				20,
				max( 1, (int) $settings['map_max_zoom'] )
			)
			: 18;

		$maptiler_style =
			Power_Schedule_Manager_Assets::sanitize_maptiler_style(
				$settings['maptiler_style'] ?? 'streets-v4'
			);
		if ( '' === $maptiler_style ) {
			$maptiler_style = 'streets-v4';
		}
		$stadia_style =
			Power_Schedule_Manager_Assets::sanitize_stadia_style(
				$settings['stadia_style'] ?? 'alidade_smooth'
			);

		$import_post_status = isset( $settings['import_post_status'] )
			&& is_string( $settings['import_post_status'] )
			&& 'draft' === sanitize_key( $settings['import_post_status'] )
				? 'draft'
				: 'publish';

		$disclaimer_text = isset( $settings['disclaimer_text'] )
			&& is_string( $settings['disclaimer_text'] )
				? sanitize_textarea_field(
					wp_unslash( $settings['disclaimer_text'] )
				)
				: '';

		if ( function_exists( 'mb_substr' ) ) {
			$disclaimer_text = mb_substr(
				$disclaimer_text,
				0,
				1600,
				'UTF-8'
			);
		} else {
			$disclaimer_text = substr( $disclaimer_text, 0, 1600 );
		}

		$archive_banner_ad = isset( $settings['archive_banner_ad'] )
			&& is_string( $settings['archive_banner_ad'] )
				? self::sanitize_ad_content(
					wp_unslash( $settings['archive_banner_ad'] )
				)
				: '';

		$archive_bottom_banner_ad = isset( $settings['archive_bottom_banner_ad'] )
			&& is_string( $settings['archive_bottom_banner_ad'] )
				? self::sanitize_ad_content(
					wp_unslash( $settings['archive_bottom_banner_ad'] )
				)
				: '';

		$single_top_banner_ad = isset( $settings['single_top_banner_ad'] )
			&& is_string( $settings['single_top_banner_ad'] )
				? self::sanitize_ad_content(
					wp_unslash( $settings['single_top_banner_ad'] )
				)
				: '';

		$single_bottom_banner_ad = isset( $settings['single_bottom_banner_ad'] )
			&& is_string( $settings['single_bottom_banner_ad'] )
				? self::sanitize_ad_content(
					wp_unslash( $settings['single_bottom_banner_ad'] )
				)
				: '';

		$home_top_banner_ad = isset( $settings['home_top_banner_ad'] )
			&& is_string( $settings['home_top_banner_ad'] )
				? self::sanitize_ad_content(
					wp_unslash( $settings['home_top_banner_ad'] )
				)
				: '';

		$home_bottom_banner_ad = isset( $settings['home_bottom_banner_ad'] )
			&& is_string( $settings['home_bottom_banner_ad'] )
				? self::sanitize_ad_content(
					wp_unslash( $settings['home_bottom_banner_ad'] )
				)
				: '';

		$raw_payload_retention_days = min(
			90,
			max( 7, absint( $settings['raw_payload_retention_days'] ?? 30 ) )
		);
		$import_log_retention_months = min(
			36,
			max( 3, absint( $settings['import_log_retention_months'] ?? 12 ) )
		);
		$completed_retention_months = min(
			60,
			max( 12, absint( $settings['completed_retention_months'] ?? 24 ) )
		);
		$cancelled_retention_months = min(
			36,
			max( 3, absint( $settings['cancelled_retention_months'] ?? 12 ) )
		);

		$home_area_theme = isset( $settings['home_area_theme'] )
			&& is_string( $settings['home_area_theme'] )
			&& 'dark' === sanitize_key( $settings['home_area_theme'] )
				? 'dark'
				: 'light';

		$home_area_region = isset( $settings['home_area_region'] )
			&& is_string( $settings['home_area_region'] )
				? sanitize_key( $settings['home_area_region'] )
				: 'lam-dong';

		$home_area_title = isset( $settings['home_area_title'] )
			&& is_string( $settings['home_area_title'] )
				? sanitize_text_field(
					wp_unslash( $settings['home_area_title'] )
				)
				: '';

		$home_area_description =
			isset( $settings['home_area_description'] )
			&& is_string( $settings['home_area_description'] )
				? sanitize_text_field(
					wp_unslash( $settings['home_area_description'] )
				)
				: '';

		$home_hero_title = isset( $settings['home_hero_title'] )
			&& is_string( $settings['home_hero_title'] )
				? sanitize_text_field(
					wp_unslash( $settings['home_hero_title'] )
				)
				: '';

		$home_hero_description =
			isset( $settings['home_hero_description'] )
			&& is_string( $settings['home_hero_description'] )
				? sanitize_text_field(
					wp_unslash( $settings['home_hero_description'] )
				)
				: '';

		if ( in_array(
			$home_hero_title,
			array(
				'Chủ động theo dõi lịch cúp điện tại Lâm Đồng',
				'Chủ động theo dõi lịch điện tại Lâm Đồng mới nhất',
				'Chủ động theo dõi lịch điện mới nhất tại Lâm Đồng',
				'Chủ động theo dõi lịch điện tại Lâm Đồng',
				'Hôm nay khu vực của bạn có cúp điện không?',
				'Tra cứu lịch cúp điện Lâm Đồng',
			),
			true
		) ) {
			$home_hero_title = 'Cúp Điện Lâm Đồng';
		}

		$home_seo_heading = isset( $settings['home_seo_heading'] )
			&& is_string( $settings['home_seo_heading'] )
				? sanitize_text_field(
					wp_unslash( $settings['home_seo_heading'] )
				)
				: '';

		$home_seo_intro = isset( $settings['home_seo_intro'] )
			&& is_string( $settings['home_seo_intro'] )
				? sanitize_textarea_field(
					wp_unslash( $settings['home_seo_intro'] )
				)
				: '';

		$home_seo_extra = isset( $settings['home_seo_extra'] )
			&& is_string( $settings['home_seo_extra'] )
				? sanitize_textarea_field(
					wp_unslash( $settings['home_seo_extra'] )
				)
				: '';

		$sponsor_title = isset( $settings['sponsor_title'] )
			&& is_scalar( $settings['sponsor_title'] )
				? sanitize_text_field(
					wp_unslash( (string) $settings['sponsor_title'] )
				)
				: '';
		$sponsor_description =
			isset( $settings['sponsor_description'] )
			&& is_scalar( $settings['sponsor_description'] )
				? sanitize_textarea_field(
					wp_unslash(
						(string) $settings['sponsor_description']
					)
				)
				: '';
		$sponsor_email = isset( $settings['sponsor_email'] )
			&& is_scalar( $settings['sponsor_email'] )
				? sanitize_email(
					wp_unslash( (string) $settings['sponsor_email'] )
				)
				: '';
		$sponsor_media_kit_url =
			isset( $settings['sponsor_media_kit_url'] )
			&& is_scalar( $settings['sponsor_media_kit_url'] )
				? esc_url_raw(
					wp_unslash(
						(string) $settings['sponsor_media_kit_url']
					),
					array( 'https' )
				)
				: '';

		$telegram_chat_id = isset( $settings['telegram_chat_id'] )
			&& is_scalar( $settings['telegram_chat_id'] )
				? substr(
					sanitize_text_field(
						wp_unslash(
							(string) $settings['telegram_chat_id']
						)
					),
					0,
					100
				)
				: '';

		$push_onesignal_app_id = isset(
			$settings['push_onesignal_app_id']
		)
			&& is_scalar( $settings['push_onesignal_app_id'] )
				? strtolower(
					sanitize_text_field(
						wp_unslash(
							(string) $settings['push_onesignal_app_id']
						)
					)
				)
				: '';

		if (
			'' !== $push_onesignal_app_id
			&& 1 !== preg_match(
				'/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/',
				$push_onesignal_app_id
			)
		) {
			$push_onesignal_app_id = '';
			add_settings_error(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				'power_schedule_manager_invalid_onesignal_app_id',
				__(
					'OneSignal App ID không đúng định dạng UUID.',
					'power-schedule-manager'
				),
				'error'
			);
		}

		$push_button_label = isset( $settings['push_button_label'] )
			&& is_scalar( $settings['push_button_label'] )
				? sanitize_text_field(
					wp_unslash(
						(string) $settings['push_button_label']
					)
				)
				: '';

		if ( function_exists( 'mb_substr' ) ) {
			$push_button_label = mb_substr(
				$push_button_label,
				0,
				80,
				'UTF-8'
			);
		} else {
			$push_button_label = substr( $push_button_label, 0, 80 );
		}

		$push_notification_title = self::limit_setting_text(
			sanitize_text_field(
				(string) (
					$settings['push_notification_title']
						?? 'Cập nhật lịch cúp điện %unit_name%'
				)
			),
			120
		);
		$push_notification_message = self::limit_setting_text(
			sanitize_textarea_field(
				(string) (
					$settings['push_notification_message']
						?? 'Có %event_count% lịch cúp điện mới hoặc vừa cập nhật, áp dụng từ %date_from% đến %date_to%. Nhấn để xem chi tiết.'
				)
			),
			240
		);
		$lottery_push_notification_title = self::limit_setting_text(
			sanitize_text_field(
				(string) (
					$settings['lottery_push_notification_title']
						?? 'Kết quả xổ số đã cập nhật: %lottery_name%'
				)
			),
			120
		);
		$lottery_push_notification_message = self::limit_setting_text(
			sanitize_textarea_field(
				(string) (
					$settings['lottery_push_notification_message']
						?? 'Đã cập nhật %draw_count% kết quả %lottery_name% ngày %date_from%. Nhấn để xem chi tiết.'
				)
			),
			240
		);

		$webhook_url = isset( $settings['webhook_url'] )
			&& is_scalar( $settings['webhook_url'] )
				? esc_url_raw(
					wp_unslash( (string) $settings['webhook_url'] ),
					array( 'https' )
				)
				: '';

		if (
			'' !== $webhook_url
			&& (
				! wp_http_validate_url( $webhook_url )
				|| ! str_starts_with(
					strtolower( $webhook_url ),
					'https://'
				)
			)
		) {
			$webhook_url = '';
			add_settings_error(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				'power_schedule_manager_invalid_webhook',
				__(
					'Webhook phải là URL HTTPS công khai hợp lệ.',
					'power-schedule-manager'
				),
				'error'
			);
		}

		$zalo_recipient_id = isset( $settings['zalo_recipient_id'] )
			&& is_scalar( $settings['zalo_recipient_id'] )
				? substr(
					sanitize_text_field(
						wp_unslash(
							(string) $settings['zalo_recipient_id']
						)
					),
					0,
					191
				)
				: '';

		$cloudflare_zone_id = isset( $settings['cloudflare_zone_id'] )
			&& is_scalar( $settings['cloudflare_zone_id'] )
				? strtolower(
					sanitize_text_field(
						wp_unslash(
							(string) $settings['cloudflare_zone_id']
						)
					)
				)
				: '';

		if (
			'' !== $cloudflare_zone_id
			&& 1 !== preg_match(
				'/\A[a-f0-9]{32}\z/',
				$cloudflare_zone_id
			)
		) {
			$cloudflare_zone_id = '';
			add_settings_error(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				'power_schedule_manager_invalid_cloudflare_zone',
				__(
					'Cloudflare Zone ID phải gồm đúng 32 ký tự hexadecimal.',
					'power-schedule-manager'
				),
				'error'
			);
		}

		$cloudflare_turnstile_site_key = isset(
			$settings['cloudflare_turnstile_site_key']
		)
			&& is_scalar( $settings['cloudflare_turnstile_site_key'] )
				? trim(
					sanitize_text_field(
						wp_unslash(
							(string) $settings['cloudflare_turnstile_site_key']
						)
					)
				)
				: '';

		if (
			'' !== $cloudflare_turnstile_site_key
			&& (
				strlen( $cloudflare_turnstile_site_key ) > 128
				|| 1 !== preg_match(
					'/\A[0-9A-Za-z_-]+\z/',
					$cloudflare_turnstile_site_key
				)
			)
		) {
			$cloudflare_turnstile_site_key = '';
			add_settings_error(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				'power_schedule_manager_invalid_turnstile_site_key',
				__(
					'Turnstile Site Key không đúng định dạng.',
					'power-schedule-manager'
				),
				'error'
			);
		}

		if ( function_exists( 'mb_substr' ) ) {
			$home_area_title = mb_substr(
				$home_area_title,
				0,
				160,
				'UTF-8'
			);

			$home_area_description = mb_substr(
				$home_area_description,
				0,
				240,
				'UTF-8'
			);

			$home_hero_title = mb_substr(
				$home_hero_title,
				0,
				160,
				'UTF-8'
			);

			$home_hero_description = mb_substr(
				$home_hero_description,
				0,
				260,
				'UTF-8'
			);
			$home_seo_heading = mb_substr(
				$home_seo_heading,
				0,
				160,
				'UTF-8'
			);
			$home_seo_intro = mb_substr(
				$home_seo_intro,
				0,
				1200,
				'UTF-8'
			);
			$home_seo_extra = mb_substr(
				$home_seo_extra,
				0,
				4000,
				'UTF-8'
			);
		} else {
			$home_area_title = substr( $home_area_title, 0, 160 );
			$home_area_description = substr(
				$home_area_description,
				0,
				240
			);
			$home_hero_title = substr(
				$home_hero_title,
				0,
				160
			);
			$home_hero_description = substr(
				$home_hero_description,
				0,
				260
			);
			$home_seo_heading = substr( $home_seo_heading, 0, 160 );
			$home_seo_intro = substr( $home_seo_intro, 0, 1200 );
			$home_seo_extra = substr( $home_seo_extra, 0, 4000 );
		}

		$pwa_icon_url = isset( $settings['pwa_icon_url'] )
			&& is_string( $settings['pwa_icon_url'] )
			? esc_url_raw(
				trim( wp_unslash( $settings['pwa_icon_url'] ) ),
				array( 'https' )
			)
			: '';
		$pwa_theme_color = isset( $settings['pwa_theme_color'] )
			&& is_string( $settings['pwa_theme_color'] )
			&& preg_match(
				'/^#[0-9a-f]{6}$/i',
				$settings['pwa_theme_color']
			)
			? strtolower( $settings['pwa_theme_color'] )
			: '#075985';
		$pwa_background_color =
			isset( $settings['pwa_background_color'] )
			&& is_string( $settings['pwa_background_color'] )
			&& preg_match(
				'/^#[0-9a-f]{6}$/i',
				$settings['pwa_background_color']
			)
			? strtolower( $settings['pwa_background_color'] )
			: '#ffffff';

		return array(
			'import_post_status'             => $import_post_status,
			'disclaimer_text'                => $disclaimer_text,
			'ads_enabled'                    => ! empty( $settings['ads_enabled'] ),
			'raw_payload_retention_days'     => $raw_payload_retention_days,
			'import_log_retention_months'    => $import_log_retention_months,
			'completed_retention_months'     => $completed_retention_months,
			'cancelled_retention_months'     => $cancelled_retention_months,
			'push_enabled'                   => ! empty( $settings['push_enabled'] ),
			'push_onesignal_app_id'          => $push_onesignal_app_id,
			'push_button_label'              => $push_button_label,
			'push_delivery_enabled'          => ! empty( $settings['push_delivery_enabled'] ),
			'onesignal_rest_api_key_encrypted' =>
				$onesignal_rest_api_key_encrypted,
			'push_notification_title'        => $push_notification_title,
			'push_notification_message'      => $push_notification_message,
			'lottery_push_delivery_enabled'  => ! empty( $settings['lottery_push_delivery_enabled'] ),
			'lottery_push_notification_title' => $lottery_push_notification_title,
			'lottery_push_notification_message' => $lottery_push_notification_message,
			'api_enabled'                    => ! empty(
				$settings['api_enabled']
			),
			'api_require_authentication'     => ! empty(
				$settings['api_require_authentication']
			),
			'api_rate_limit'                 => min(
				3000,
				max(
					30,
					absint( $settings['api_rate_limit'] ?? 180 )
				)
			),
			'pwa_enabled'                    => ! empty(
				$settings['pwa_enabled']
			),
			'pwa_prompt_enabled'             => ! empty(
				$settings['pwa_prompt_enabled']
			),
			'pwa_service_worker_enabled'     => ! empty(
				$settings['pwa_service_worker_enabled']
			),
			'pwa_app_name'                   => self::limit_setting_text(
				sanitize_text_field(
					(string) (
						$settings['pwa_app_name']
							?? 'Cúp Điện Lâm Đồng'
					)
				),
				80
			),
			'pwa_short_name'                 => self::limit_setting_text(
				sanitize_text_field(
					(string) (
						$settings['pwa_short_name']
							?? 'Cúp điện LĐ'
					)
				),
				30
			),
			'pwa_description'                => self::limit_setting_text(
				sanitize_text_field(
					(string) (
						$settings['pwa_description']
							?? 'Tra cứu lịch điện tại Lâm Đồng theo ngày và khu vực.'
					)
				),
				240
			),
			'pwa_icon_url'                   => $pwa_icon_url,
			'pwa_theme_color'                => $pwa_theme_color,
			'pwa_background_color'           => $pwa_background_color,
			'pwa_visit_threshold'            => min(
				10,
				max(
					2,
					absint( $settings['pwa_visit_threshold'] ?? 3 )
				)
			),
			'pwa_prompt_delay_seconds'       => min(
				30,
				max(
					2,
					absint(
						$settings['pwa_prompt_delay_seconds'] ?? 8
					)
				)
			),
			'pwa_prompt_cooldown_days'       => min(
				365,
				max(
					1,
					absint(
						$settings['pwa_prompt_cooldown_days'] ?? 30
					)
				)
			),
			'pwa_prompt_title'               =>
				self::limit_setting_text(
					sanitize_text_field(
						(string) (
							$settings['pwa_prompt_title']
								?? 'Thêm Cúp Điện Lâm Đồng vào màn hình chính'
						)
					),
					120
				),
			'pwa_prompt_message'             =>
				self::limit_setting_text(
					sanitize_text_field(
						(string) (
							$settings['pwa_prompt_message']
								?? 'Tra cứu nhanh hơn và nhận thông báo lịch cúp điện trên thiết bị này.'
						)
					),
					240
				),
			'telegram_enabled'               => ! empty( $settings['telegram_enabled'] ),
			'telegram_chat_id'               => $telegram_chat_id,
			'telegram_bot_token_encrypted'   =>
				$telegram_bot_token_encrypted,
			'webhook_enabled'                => ! empty( $settings['webhook_enabled'] ),
			'webhook_url'                    => $webhook_url,
			'webhook_secret_encrypted'       => $webhook_secret_encrypted,
			'zalo_enabled'                   => ! empty( $settings['zalo_enabled'] ),
			'zalo_recipient_id'              => $zalo_recipient_id,
			'zalo_access_token_encrypted'    =>
				$zalo_access_token_encrypted,
			'cloudflare_enabled'             => ! empty(
				$settings['cloudflare_enabled']
			),
			'cloudflare_zone_id'             => $cloudflare_zone_id,
			'cloudflare_api_token_encrypted' =>
				$cloudflare_api_token_encrypted,
			'cloudflare_turnstile_enabled'   => ! empty(
				$settings['cloudflare_turnstile_enabled']
			),
			'cloudflare_turnstile_site_key'  =>
				$cloudflare_turnstile_site_key,
			'cloudflare_turnstile_secret_encrypted' =>
				$cloudflare_turnstile_secret_encrypted,
			'archive_banner_ad'              => $archive_banner_ad,
			'archive_bottom_banner_ad'       => $archive_bottom_banner_ad,
			'single_top_banner_ad'           => $single_top_banner_ad,
			'single_bottom_banner_ad'        => $single_bottom_banner_ad,
			'home_top_banner_ad'             => $home_top_banner_ad,
			'home_bottom_banner_ad'          => $home_bottom_banner_ad,
			'map_provider'                   => $provider,
			'map_tile_url'                   => $tile_url,
			'map_attribution'                => $attribution,
			'map_max_zoom'                   => $max_zoom,
			'maptiler_style'                 => $maptiler_style,
			'maptiler_key_encrypted'         =>
				$maptiler_key_encrypted,
			'stadia_style'                   => $stadia_style,
			'stadia_key_encrypted'           =>
				$stadia_key_encrypted,
			'weather_default_label'          => self::limit_setting_text(
				sanitize_text_field(
					(string) (
						$settings['weather_default_label']
							?? 'Lâm Đồng'
					)
				),
				100
			),
			'weather_default_lat'            => min(
				90,
				max(
					-90,
					(float) (
						$settings['weather_default_lat']
							?? 11.5753
					)
				)
			),
			'weather_default_lon'            => min(
				180,
				max(
					-180,
					(float) (
						$settings['weather_default_lon']
							?? 108.1429
					)
				)
			),
			'weather_default_zoom'           => min(
				15,
				max(
					3,
					absint(
						$settings['weather_default_zoom'] ?? 7
					)
				)
			),
			'weather_default_height'         => min(
				760,
				max(
					320,
					absint(
						$settings['weather_default_height'] ?? 520
					)
				)
			),
			'home_show_hero'                 => ! empty(
				$settings['home_show_hero']
			),
			'home_show_search'               => ! empty(
				$settings['home_show_search']
			),
			'home_show_alert'                => ! empty(
				$settings['home_show_alert']
			),
			'home_show_days'                 => ! empty(
				$settings['home_show_days']
			),
			'home_show_areas'                => ! empty(
				$settings['home_show_areas']
			),
			'home_show_next'                 => ! empty(
				$settings['home_show_alert']
			),
			'home_show_recent'               => ! empty(
				$settings['home_show_alert']
			),
			'home_show_content'              => ! empty(
				$settings['home_show_content']
			),
			'home_hero_title'                => $home_hero_title,
			'home_hero_description'          =>
				$home_hero_description,
			'home_seo_heading'               => $home_seo_heading,
			'home_seo_intro'                 => $home_seo_intro,
			'home_seo_extra'                 => $home_seo_extra,
			'home_days'                      => min(
				31,
				max( 8, absint( $settings['home_days'] ?? 31 ) )
			),
			'home_recent_limit'              => min(
				3,
				max( 1, absint( $settings['home_recent_limit'] ?? 3 ) )
			),
			'home_area_region'               => $home_area_region,
			'home_area_columns'              => min(
				6,
				max( 1, absint( $settings['home_area_columns'] ?? 4 ) )
			),
			'home_area_initial'              => min(
				100,
				max( 0, absint( $settings['home_area_initial'] ?? 16 ) )
			),
			'home_area_theme'                => $home_area_theme,
			'home_area_title'                => $home_area_title,
			'home_area_description'          => $home_area_description,
			'sponsor_enabled'                => ! empty(
				$settings['sponsor_enabled']
			),
			'sponsor_title'                  => self::limit_setting_text(
				$sponsor_title,
				160
			),
			'sponsor_description'            => self::limit_setting_text(
				$sponsor_description,
				800
			),
			'sponsor_email'                  => $sponsor_email,
			'sponsor_media_kit_url'          => $sponsor_media_kit_url,
		);
	}

	/**
	 * Test a map tile from the WordPress server.
	 *
	 * @return void
	 */
	public function ajax_test_map_tile(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_send_json_error(
				array(
					'message' => __(
						'Bạn không có quyền kiểm tra máy chủ tile.',
						'power-schedule-manager'
					),
				),
				403
			);
		}

		check_ajax_referer(
			'psm_test_map_tile',
			'nonce'
		);

		$provider = isset( $_POST['provider'] )
			&& is_scalar( $_POST['provider'] )
			? sanitize_key(
				wp_unslash( (string) $_POST['provider'] )
			)
			: 'osm';

		$stored_settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$stored_settings = is_array( $stored_settings )
			? $stored_settings
			: array();

		$configuration = Power_Schedule_Manager_Assets::provider_configuration(
			array_merge(
				$stored_settings,
				array(
				'map_provider'   => $provider,
				'map_tile_url'   => isset( $_POST['tile_url'] )
					&& is_scalar( $_POST['tile_url'] )
						? trim(
							wp_unslash( (string) $_POST['tile_url'] )
						)
						: '',
				'maptiler_style' => isset( $_POST['maptiler_style'] )
					&& is_scalar( $_POST['maptiler_style'] )
						? wp_unslash( (string) $_POST['maptiler_style'] )
						: '',
				'stadia_style'   => isset( $_POST['stadia_style'] )
					&& is_scalar( $_POST['stadia_style'] )
						? wp_unslash( (string) $_POST['stadia_style'] )
						: '',
				'map_max_zoom'   => 19,
				)
			)
		);

		$template = (string) ( $configuration['tile_url'] ?? '' );
		$temporary_key = '';
		if (
			'maptiler' === $provider
			&& isset( $_POST['maptiler_key'] )
			&& is_scalar( $_POST['maptiler_key'] )
		) {
			$temporary_key = trim(
				wp_unslash( (string) $_POST['maptiler_key'] )
			);
			if ( '' !== $temporary_key ) {
				$style = Power_Schedule_Manager_Assets::sanitize_maptiler_style(
					$_POST['maptiler_style'] ?? 'streets-v4'
				);
				$template = 'https://api.maptiler.com/maps/'
					. $style
					. '/256/{z}/{x}/{y}.png?key='
					. rawurlencode( $temporary_key );
			}
		} elseif (
			'stadia' === $provider
			&& isset( $_POST['stadia_key'] )
			&& is_scalar( $_POST['stadia_key'] )
		) {
			$temporary_key = trim(
				wp_unslash( (string) $_POST['stadia_key'] )
			);
			if ( '' !== $temporary_key ) {
				$style = Power_Schedule_Manager_Assets::sanitize_stadia_style(
					$_POST['stadia_style'] ?? 'alidade_smooth'
				);
				$template = 'https://tiles.stadiamaps.com/tiles/'
					. $style
					. '/{z}/{x}/{y}.png?api_key='
					. rawurlencode( $temporary_key );
			}
		}

		if ( '' === $template ) {
			wp_send_json_error(
				array(
					'message' => in_array(
						$provider,
						array( 'maptiler', 'stadia' ),
						true
					)
						? __(
							'Chưa có API key hợp lệ. Nhập key ở trường bên trên hoặc khai báo key trong cấu hình máy chủ, sau đó kiểm tra lại.',
							'power-schedule-manager'
						)
						: __(
							'URL tile hoặc phong cách bản đồ chưa hợp lệ.',
							'power-schedule-manager'
						),
				),
				400
			);
		}

		$test_url = strtr(
			$template,
			array(
				'{z}' => '0',
				'{x}' => '0',
				'{y}' => '0',
				'{s}' => 'a',
			)
		);

		$response = wp_safe_remote_get(
			$test_url,
			array(
				'timeout'             => 8,
				'redirection'         => 2,
				'limit_response_size' => 1048576,
				'headers'             => array(
					'Accept'     => 'image/*',
					'User-Agent' => 'Cúp Điện Lâm Đồng/'
						. POWER_SCHEDULE_MANAGER_VERSION
						. '; '
						. home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Safe HTTP error message. */
						__(
							'Máy chủ WordPress không kết nối được tile: %s',
							'power-schedule-manager'
						),
						sanitize_text_field(
							$response->get_error_message()
						)
					),
				),
				502
			);
		}

		$status_code = wp_remote_retrieve_response_code(
			$response
		);

		$content_type = strtolower(
			(string) wp_remote_retrieve_header(
				$response,
				'content-type'
			)
		);

		if (
			$status_code < 200
			|| $status_code >= 300
			|| ! str_starts_with( $content_type, 'image/' )
		) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: HTTP code, 2: Content type. */
						__(
							'Tile phản hồi không hợp lệ (HTTP %1$d, %2$s).',
							'power-schedule-manager'
						),
						$status_code,
						'' !== $content_type
							? $content_type
							: 'unknown'
					),
				),
				502
			);
		}

		wp_send_json_success(
			array(
				'message' => __(
					'Máy chủ tile phản hồi hợp lệ từ WordPress.',
					'power-schedule-manager'
				),
			)
		);
	}

	/**
	 * Test one configured notification adapter.
	 */
	public function ajax_test_notification(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_send_json_error(
				array(
					'message' => __(
						'Bạn không có quyền kiểm tra kênh thông báo.',
						'power-schedule-manager'
					),
				),
				403
			);
		}

		check_ajax_referer(
			'psm_test_notification',
			'nonce'
		);

		$channel = isset( $_POST['channel'] )
			&& is_scalar( $_POST['channel'] )
				? sanitize_key(
					wp_unslash( (string) $_POST['channel'] )
				)
				: '';

		$result = Power_Schedule_Manager_Notifications::test_channel(
			$channel
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Safe connection error. */
						__(
							'Không gửi được thông báo thử: %s',
							'power-schedule-manager'
						),
						sanitize_text_field(
							$result->get_error_message()
						)
					),
				),
				502
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: HTTP status code. */
					__(
						'Đã gửi thông báo thử thành công (HTTP %d).',
						'power-schedule-manager'
					),
					absint( $result )
				),
			)
		);
	}

	/**
	 * Requeue failed outbound notifications.
	 */
	public function retry_failed_notifications(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);
		check_admin_referer( 'psm_retry_notifications' );

		$count = Power_Schedule_Manager_Notifications::retry_failed();
		$url = add_query_arg(
			array(
				'page'        => self::SYSTEM_SLUG,
				'psm_retried' => $count,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::EDIT_POSTS
		);

		$this->render_view(
			'dashboard.php',
			array(
				'units'         =>
					Power_Schedule_Manager_Units::all(),
				'next_cron_run' =>
					Power_Schedule_Manager_Cron::next_run_timestamp(),
				'stats'          =>
					Power_Schedule_Manager_Dashboard_Stats::snapshot(),
			)
		);
	}

	/**
	 * Render import workflow.
	 *
	 * @return void
	 */
	public function render_import_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::IMPORT_SCHEDULES
		);

		$view_arguments = array(
			'units'   => Power_Schedule_Manager_Units::all(),
			'preview' => null,
			'result'  => null,
			'error'   => '',
			'form'    => array(
				'unit_code'  => '',
				'source'     => 'evn',
				'source_url' => '',
				'payload'    => '',
			),
		);

		$action = self::posted_action();

		try {
			if ( 'preview' === $action ) {
				check_admin_referer(
					'psm_import_preview',
					'psm_nonce'
				);

				$form = self::import_form_data();
				$view_arguments['form'] = $form;

				$parsed = Power_Schedule_Manager_Parser::parse(
					$form['payload'],
					$form['unit_code'],
					$form['source'],
					true
				);

				if (
					isset( $parsed['errors'] )
					&& is_array( $parsed['errors'] )
					&& array() !== $parsed['errors']
				) {
					$first_error = $parsed['errors'][0];
					$message     = is_array( $first_error )
						? sanitize_text_field(
							(string) ( $first_error['message'] ?? '' )
						)
						: '';

					throw new InvalidArgumentException(
						'' !== $message
							? $message
							: 'import_contains_parser_errors'
					);
				}

				$view_arguments['preview'] =
					Power_Schedule_Manager_Preview::create(
						$parsed,
						$form['payload']
					);
			} elseif ( 'confirm' === $action ) {
				check_admin_referer(
					'psm_import_confirm',
					'psm_nonce'
				);

				$form = self::import_form_data();

				$token = isset( $_POST['preview_token'] )
					&& is_scalar( $_POST['preview_token'] )
					? sanitize_text_field(
						wp_unslash(
							(string) $_POST['preview_token']
						)
					)
					: '';

				$resolutions =
					self::duplicate_resolutions_from_request();

				$view_arguments['form'] = $form;
				$view_arguments['result'] =
					Power_Schedule_Manager_Importer::import(
						$form['payload'],
						$form['unit_code'],
						$token,
						$form['source'],
						$form['source_url'],
						$resolutions
					);
			}
		} catch ( Throwable $throwable ) {
			Power_Schedule_Manager_Logger::error(
				'admin_import_failed',
				$throwable,
				array(
					'user_id'   => get_current_user_id(),
					'unit_code' =>
						$view_arguments['form']['unit_code'],
				)
			);

			$view_arguments['error'] =
				self::user_facing_error( $throwable );
		}

		$view = null !== $view_arguments['preview']
			? 'import-preview.php'
			: 'import-form.php';

		$this->render_view(
			$view,
			$view_arguments
		);
	}

	/**
	 * Render import history.
	 *
	 * @return void
	 */
	public function render_history_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::VIEW_LOGS
		);

		$run_id = isset( $_GET['run_id'] )
			? absint( $_GET['run_id'] )
			: 0;

		if ( $run_id > 0 ) {
			$this->render_view(
				'import-run-detail.php',
				array(
					'run'      =>
						Power_Schedule_Manager_Logger::find_import_run(
							$run_id,
							false
						),
					'activity' =>
						Power_Schedule_Manager_Logger::import_run_activity(
							$run_id
						),
				)
			);

			return;
		}

		$page = isset( $_GET['paged'] )
			? max( 1, absint( $_GET['paged'] ) )
			: 1;

		$history = Power_Schedule_Manager_Logger::import_history(
			array(
				'unit_code' => self::query_value( 'unit_code' ),
				'status'    => self::query_value( 'status' ),
				'date_from' => self::query_value( 'date_from' ),
				'date_to'   => self::query_value( 'date_to' ),
				'page'      => $page,
				'per_page'  => 15,
			)
		);

		$this->render_view(
			'sync-history.php',
			array(
				'history' => $history,
				'units'   => Power_Schedule_Manager_Units::all(),
			)
		);
	}

	/**
	 * Render read-only production diagnostics and the attention queue.
	 *
	 * @return void
	 */
	public function render_system_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);

		$this->render_view(
			'system-health.php',
				array(
					'health'        =>
						Power_Schedule_Manager_System_Health::snapshot(),
					'notifications' =>
						Power_Schedule_Manager_Notifications::recent( 20 ),
				)
			);
	}

	/**
	 * Render the backup and recovery centre.
	 *
	 * @return void
	 */
	public function render_backup_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);

		$this->render_view(
			'backup.php',
			array(
				'summary' => Power_Schedule_Manager_Backup::summary(),
				'cloud'   => Power_Schedule_Manager_Backup::cloud_summary(),
				'notice'  => Power_Schedule_Manager_Backup::consume_notice(),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);

		$this->render_view(
			'settings.php',
			array(
				'settings' => get_option(
					POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
					self::default_settings()
				),
			)
		);
	}

	/**
	 * Render one central screen for every external data integration.
	 */
	public function render_data_sources_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);

		$this->render_view(
			'data-sources.php',
			array()
		);
	}

	/**
	 * Save the read-only application API without resubmitting all settings.
	 */
	public function save_application_api(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);
		check_admin_referer( 'psm_save_application_api' );

		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$settings = is_array( $settings ) ? $settings : array();
		$settings['api_enabled'] = isset( $_POST['api_enabled'] );
		// API clients now use independently revocable Bearer tokens.
		$settings['api_require_authentication'] = true;
		$settings['api_rate_limit'] = min(
			3000,
			max(
				30,
				absint( $_POST['api_rate_limit'] ?? 180 )
			)
		);
		update_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			$settings,
			false
		);

		$this->redirect_data_sources( 'api_saved', 'application-api' );
	}

	/**
	 * Clear anonymous aggregate API security counters.
	 */
	public function reset_application_api_stats(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);
		check_admin_referer( 'psm_reset_application_api_stats' );
		Power_Schedule_Manager_API::reset_security_stats();
		$this->redirect_data_sources( 'api_stats_reset', 'application-api' );
	}

	/**
	 * Redirect back to one central data-source tab.
	 */
	private function redirect_data_sources(
		string $notice,
		string $tab
	): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::DATA_SOURCES_SLUG,
					'settings_tab' => sanitize_key( $tab ),
					'psm_notice'   => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Process one bounded Cloudflare purge batch on administrator request.
	 */
	public function process_cloudflare_queue(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
		);
		check_admin_referer( 'psm_process_cloudflare_queue' );

		$before = Power_Schedule_Manager_Cloudflare::status();
		$worker = new Power_Schedule_Manager_Cloudflare();
		$worker->process();
		$after = Power_Schedule_Manager_Cloudflare::status();

		$notice = $after['queued'] < $before['queued']
			? 'cloudflare_processed'
			: (
				0 === $before['queued']
					? 'cloudflare_empty'
					: 'cloudflare_retry'
			);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::SETTINGS_SLUG,
					'psm_notice' => $notice,
				),
				admin_url( 'admin.php' )
			) . '#psm-cloudflare'
		);
		exit;
	}

	/**
	 * Render usage documentation.
	 *
	 * @return void
	 */
	public function render_help_page(): void {
		$this->assert_capability(
			Power_Schedule_Manager_Capabilities::EDIT_POSTS
		);

		$this->render_view( 'help.php' );
	}

	/**
	 * Enqueue administration assets only on plugin screens.
	 *
	 * @param string $hook_suffix Current screen hook.
	 *
	 * @return void
	 */
	public function enqueue_assets(
		string $hook_suffix
	): void {
		$screen = get_current_screen();

		$is_schedule_screen = $screen instanceof WP_Screen
			&& Power_Schedule_Manager_Post_Type::POST_TYPE
				=== $screen->post_type;

		if (
			! in_array(
				$hook_suffix,
				$this->screen_ids,
				true
			)
			&& ! $is_schedule_screen
		) {
			return;
		}

		$css_path = POWER_SCHEDULE_MANAGER_PATH
			. 'admin/assets/admin.css';

		$js_path = POWER_SCHEDULE_MANAGER_PATH
			. 'admin/assets/admin.js';

		wp_enqueue_style(
			'power-schedule-manager-admin',
			POWER_SCHEDULE_MANAGER_URL
				. 'admin/assets/admin.css',
			array(),
			self::asset_version( $css_path ) . '.import-ui-1'
		);

		if (
			isset( $_GET['page'] )
			&& is_scalar( $_GET['page'] )
			&& self::MENU_SLUG === sanitize_key(
				wp_unslash( (string) $_GET['page'] )
			)
		) {
			$dashboard_css_path = POWER_SCHEDULE_MANAGER_PATH
				. 'admin/assets/dashboard.css';

			wp_enqueue_style(
				'power-schedule-manager-dashboard',
				POWER_SCHEDULE_MANAGER_URL
					. 'admin/assets/dashboard.css',
				array( 'power-schedule-manager-admin' ),
				self::asset_version( $dashboard_css_path )
					. '.header-actions-3'
			);
		}

		wp_enqueue_script(
			'power-schedule-manager-admin',
			POWER_SCHEDULE_MANAGER_URL
				. 'admin/assets/admin.js',
			array(),
			self::asset_version( $js_path ) . '.import-basic-1',
			true
		);

		wp_localize_script(
			'power-schedule-manager-admin',
			'PowerScheduleManagerAdmin',
			array(
					'ajaxNonce' => wp_create_nonce(
						'psm_test_map_tile'
					),
					'notificationNonce' => wp_create_nonce(
						'psm_test_notification'
					),
				'strings' => array(
					'confirmImport' => __(
						'Xác nhận lưu dữ liệu đã kiểm tra?',
						'power-schedule-manager'
					),
					'previewing' => __(
						'Đang phân tích dữ liệu...',
						'power-schedule-manager'
					),
					'unitSelectRequired' => __(
						'Hãy chọn đơn vị điện lực trước khi kiểm tra dữ liệu.',
						'power-schedule-manager'
					),
				),
			)
		);
	}

	/**
	 * Register admin subservices.
	 *
	 * @return void
	 */
	private function register_subservices(): void {
		$classes = array(
			'Power_Schedule_Manager_Schedule_List',
			'Power_Schedule_Manager_Map_Editor',
		);

		foreach ( $classes as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			$service = new $class_name();

			if ( is_callable( array( $service, 'register' ) ) ) {
				$service->register();
			}
		}
	}

	/**
	 * Render allowlisted admin view.
	 *
	 * @param string               $filename  View filename.
	 * @param array<string, mixed> $arguments View arguments.
	 *
	 * @return void
	 */
	private function render_view(
		string $filename,
		array $arguments = array()
	): void {
		if (
			1 !== preg_match(
				'/\A[a-z0-9-]+\.php\z/',
				$filename
			)
		) {
			wp_die(
				esc_html__(
					'Admin view không hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		$view = POWER_SCHEDULE_MANAGER_PATH
			. 'admin/views/'
			. $filename;

		$real_view = realpath( $view );
		$view_root = realpath(
			POWER_SCHEDULE_MANAGER_PATH
				. 'admin/views'
		);

		if (
			false === $real_view
			|| false === $view_root
			|| ! str_starts_with(
				wp_normalize_path( $real_view ),
				wp_normalize_path(
					untrailingslashit( $view_root )
				) . '/'
			)
			|| ! is_readable( $real_view )
		) {
			printf(
				'<div class="wrap"><div class="notice notice-error"><p>%s</p></div></div>',
				esc_html__(
					'Không tìm thấy giao diện quản trị cần thiết.',
					'power-schedule-manager'
				)
			);

			return;
		}

		$psm_admin_args = $arguments;

		require $real_view;
	}

	/**
	 * Return sanitized import form data.
	 *
	 * Raw payload is not sanitized before parsing because the parser must
	 * support copied HTML. It is still size-limited and sanitized during parse.
	 *
	 * @return array{
	 *     unit_code: string,
	 *     source: string,
	 *     source_url: string,
	 *     payload: string
	 * }
	 */
	private static function import_form_data(): array {
		$payload = isset( $_POST['payload'] )
			&& is_scalar( $_POST['payload'] )
			? wp_unslash( (string) $_POST['payload'] )
			: '';
		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$_POST['unit_code'] ?? ''
		);

		return array(
			'unit_code'  => $unit_code,
			'source'     => 'evn',
			'source_url' => '',
			'payload'    => $payload,
		);
	}

	/**
	 * Read duplicate resolutions from POST.
	 *
	 * @return array<string, string>
	 */
	private static function duplicate_resolutions_from_request(): array {
		if (
			! isset( $_POST['duplicate_resolution'] )
			|| ! is_array( $_POST['duplicate_resolution'] )
		) {
			return array();
		}

		$resolutions = array();

		foreach (
			wp_unslash( $_POST['duplicate_resolution'] )
			as $hash => $decision
		) {
			if (
				is_string( $hash )
				&& is_string( $decision )
			) {
				$resolutions[ $hash ] =
					sanitize_key( $decision );
			}
		}

		return $resolutions;
	}

	/**
	 * Return posted action.
	 *
	 * @return string
	 */
	private static function posted_action(): string {
		if (
			'POST' !== strtoupper(
				(string) ( $_SERVER['REQUEST_METHOD'] ?? '' )
			)
			|| ! isset( $_POST['psm_action'] )
			|| ! is_scalar( $_POST['psm_action'] )
		) {
			return '';
		}

		return sanitize_key(
			wp_unslash(
				(string) $_POST['psm_action']
			)
		);
	}

	/**
	 * Require administration capability.
	 *
	 * @param string $capability Capability.
	 *
	 * @return void
	 */
	private function assert_capability(
		string $capability
	): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die(
				esc_html__(
					'Bạn không có quyền truy cập trang này.',
					'power-schedule-manager'
				),
				esc_html__(
					'Không đủ quyền',
					'power-schedule-manager'
				),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Convert internal failure to safe user-facing message.
	 *
	 * @param Throwable $throwable Error.
	 *
	 * @return string
	 */
	private static function user_facing_error(
		Throwable $throwable
	): string {
		$message = $throwable->getMessage();

		if (
			$throwable instanceof InvalidArgumentException
			&& '' !== trim( $message )
			&& ! str_starts_with( $message, 'import_' )
		) {
			return sanitize_text_field( $message );
		}

		return match ( $message ) {
			'import_unit_required' =>
				__(
					'Vui lòng chọn đơn vị điện lực trước khi kiểm tra dữ liệu.',
					'power-schedule-manager'
				),
			'import_preview_token_invalid_or_expired' =>
				__(
					'Phiên xem trước đã hết hạn hoặc dữ liệu đã thay đổi. Vui lòng xem trước lại.',
					'power-schedule-manager'
				),
			'import_duplicate_resolution_required' =>
				__(
					'Vui lòng chọn cách xử lý cho từng lịch có thể trùng.',
					'power-schedule-manager'
				),
			'import_already_running_for_unit' =>
				__(
					'Đơn vị này đang có một tiến trình nhập khác. Vui lòng thử lại sau.',
					'power-schedule-manager'
				),
			'import_plugin_write_locked' =>
				__(
					'Dữ liệu plugin đang được khôi phục hoặc có tác vụ ghi khác. Vui lòng đợi thao tác đó hoàn tất rồi nhập lại.',
					'power-schedule-manager'
				),
			'import_unit_not_found' =>
				__(
					'Không nhận diện được đơn vị điện lực từ dữ liệu. Hãy kiểm tra dòng “Đơn vị:” rồi xem trước lại.',
					'power-schedule-manager'
				),
			'import_contains_parser_errors',
			'import_preview_has_blocking_errors' =>
				__(
					'Dữ liệu còn lỗi và chưa thể nhập.',
					'power-schedule-manager'
				),
			default =>
				__(
					'Không thể hoàn tất thao tác. Vui lòng kiểm tra dữ liệu và thử lại.',
					'power-schedule-manager'
				),
		};
	}

	/**
	 * Read sanitized query value.
	 *
	 * @param string $key Query key.
	 *
	 * @return string
	 */
	private static function query_value(
		string $key
	): string {
		if (
			! isset( $_GET[ $key ] )
			|| ! is_scalar( $_GET[ $key ] )
		) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash(
				(string) $_GET[ $key ]
			)
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_settings(): array {
		return array(
			'import_post_status'             => 'publish',
			'disclaimer_text'                => '',
			'ads_enabled'                    => true,
			'raw_payload_retention_days'     => 30,
			'import_log_retention_months'    => 12,
			'completed_retention_months'     => 24,
			'cancelled_retention_months'     => 12,
			'push_enabled'                   => false,
			'push_onesignal_app_id'          => defined(
				'POWER_SCHEDULE_MANAGER_ONESIGNAL_APP_ID'
			)
				? POWER_SCHEDULE_MANAGER_ONESIGNAL_APP_ID
				: '',
			'push_button_label'              =>
				'Nhận thông báo lịch cúp điện',
			'push_delivery_enabled'          => false,
			'onesignal_rest_api_key_encrypted' => '',
			'push_notification_title'        =>
				'Cập nhật lịch cúp điện %unit_name%',
			'push_notification_message'      =>
				'Có %event_count% lịch cúp điện mới hoặc vừa cập nhật, áp dụng từ %date_from% đến %date_to%. Nhấn để xem chi tiết.',
			'lottery_push_delivery_enabled'  => false,
			'lottery_push_notification_title' =>
				'Kết quả xổ số đã cập nhật: %lottery_name%',
			'lottery_push_notification_message' =>
				'Đã cập nhật %draw_count% kết quả %lottery_name% ngày %date_from%. Nhấn để xem chi tiết.',
			'api_enabled'                    => false,
			'api_require_authentication'     => false,
			'api_rate_limit'                 => 180,
			'pwa_enabled'                    => false,
			'pwa_prompt_enabled'             => true,
			'pwa_service_worker_enabled'     => true,
			'pwa_app_name'                   => 'Cúp Điện Lâm Đồng',
			'pwa_short_name'                 => 'Cúp Điện LĐ',
			'pwa_description'                =>
				'Cúp Điện Lâm Đồng hỗ trợ tra cứu lịch điện theo ngày và khu vực.',
			'pwa_icon_url'                   => '',
			'pwa_theme_color'                => '#075985',
			'pwa_background_color'           => '#ffffff',
			'pwa_visit_threshold'            => 3,
			'pwa_prompt_delay_seconds'       => 8,
			'pwa_prompt_cooldown_days'       => 30,
			'pwa_prompt_title'               =>
				'Thêm Cúp Điện Lâm Đồng vào màn hình chính',
			'pwa_prompt_message'             =>
				'Tra cứu nhanh hơn và nhận thông báo lịch cúp điện trên thiết bị này.',
			'telegram_enabled'               => false,
			'telegram_chat_id'               => '',
			'telegram_bot_token_encrypted'   => '',
			'webhook_enabled'                => false,
			'webhook_url'                    => '',
			'webhook_secret_encrypted'       => '',
			'zalo_enabled'                   => false,
			'zalo_recipient_id'              => '',
			'zalo_access_token_encrypted'    => '',
			'cloudflare_enabled'             => false,
			'cloudflare_zone_id'             => '',
			'cloudflare_api_token_encrypted' => '',
			'cloudflare_turnstile_enabled'   => false,
			'cloudflare_turnstile_site_key'  => '',
			'cloudflare_turnstile_secret_encrypted' => '',
			'archive_banner_ad'              => '',
			'archive_bottom_banner_ad'       => '',
			'single_top_banner_ad'           => '',
			'single_bottom_banner_ad'        => '',
			'home_top_banner_ad'             => '',
			'home_bottom_banner_ad'          => '',
			'map_provider'                   => 'osm',
			'map_tile_url'                   => '',
			'map_attribution'                => '',
			'map_max_zoom'                   => 19,
			'maptiler_style'                 => 'streets-v4',
			'maptiler_key_encrypted'         => '',
			'stadia_style'                   => 'alidade_smooth',
			'stadia_key_encrypted'           => '',
			'weather_default_label'          => 'Lâm Đồng',
			'weather_default_lat'            => 11.5753,
			'weather_default_lon'            => 108.1429,
			'weather_default_zoom'           => 7,
			'weather_default_height'         => 520,
			'home_show_hero'                 => true,
			'home_show_search'               => true,
			'home_show_alert'                => true,
			'home_show_days'                 => true,
			'home_show_areas'                => true,
			'home_show_next'                 => true,
			'home_show_recent'               => true,
			'home_show_content'              => true,
			'home_hero_title'                =>
				'Cúp Điện Lâm Đồng',
			'home_hero_description'          =>
				'Tin tức, việc làm, thời tiết, giá nông sản và tiện ích địa phương được cập nhật mỗi ngày.',
			'home_seo_heading'               =>
				'Cúp Điện Lâm Đồng — cổng thông tin và tiện ích địa phương',
			'home_seo_intro'                 =>
				'Cập nhật tin tức, việc làm, thời tiết, giá nông sản, kết quả xổ số, du lịch và lịch điện tại Lâm Đồng trong một bố cục thống nhất, dễ đọc trên điện thoại lẫn máy tính.',
			'home_seo_extra'                 =>
				'Cúp Điện Lâm Đồng là nền tảng thông tin cộng đồng độc lập. Mỗi chuyên mục được tổ chức theo nhu cầu hằng ngày và cần ghi rõ nguồn khi sử dụng dữ liệu từ đơn vị khác. Riêng lịch điện được tổng hợp từ thông tin đã công bố của EVN và đơn vị điện lực, không phải xác nhận cấp điện theo thời gian thực.',
			'home_days'                      => 31,
			'home_recent_limit'              => 3,
			'home_area_region'               => 'lam-dong',
			'home_area_columns'              => 4,
			'home_area_initial'              => 16,
			'home_area_theme'                => 'light',
			'home_area_title'                =>
				'Lịch điện theo khu vực tại Lâm Đồng hôm nay',
			'home_area_description'          =>
				'Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới.',
			'sponsor_enabled'                => true,
			'sponsor_title'                  =>
				'Hợp tác cùng Cúp Điện Lâm Đồng',
			'sponsor_description'            =>
				'Trao đổi các sáng kiến nội dung, truyền thông và hợp tác phù hợp với một nền tảng thông tin cộng đồng độc lập.',
			'sponsor_email'                  => '',
			'sponsor_media_kit_url'          => '',
		);
	}

	/**
	 * Limit a sanitized setting without assuming mbstring is installed.
	 */
	private static function limit_setting_text(
		string $value,
		int $length
	): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, $length, 'UTF-8' )
			: substr( $value, 0, $length );
	}

	/**
	 * Sanitize one write-only API credential.
	 *
	 * Blank input preserves the current value. The clear checkbox explicitly
	 * removes it. Encryption errors preserve the previous value.
	 *
	 * @param array<string,mixed> $submitted Submitted settings.
	 * @param array<string,mixed> $current Current settings.
	 */
	private static function sanitize_secret_setting(
		array $submitted,
		array $current,
		string $input_key,
		string $encrypted_key,
		string $clear_key
	): string {
		$existing = isset( $current[ $encrypted_key ] )
			&& is_string( $current[ $encrypted_key ] )
				? $current[ $encrypted_key ]
				: '';
		$result = Power_Schedule_Manager_Secrets::update(
			$submitted[ $input_key ] ?? '',
			$existing,
			! empty( $submitted[ $clear_key ] )
		);

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				'power_schedule_manager_secret_error_' . $input_key,
				$result->get_error_message(),
				'error'
			);

			return $existing;
		}

		return $result;
	}

	/**
	 * Validate custom tile template.
	 *
	 * @param string $url Template.
	 *
	 * @return bool
	 */
	private static function valid_tile_template(
		string $url
	): bool {
		if (
			'' === $url
			|| strlen( $url ) > 2048
			|| ! str_starts_with( $url, 'https://' )
			|| ! str_contains( $url, '{z}' )
			|| ! str_contains( $url, '{x}' )
			|| ! str_contains( $url, '{y}' )
			|| preg_match( '/["\'<>\x00\\\\]/', $url )
		) {
			return false;
		}

		$test_url = str_replace(
			array( '{z}', '{x}', '{y}', '{s}' ),
			array( '1', '1', '1', 'a' ),
			$url
		);

		return false !== wp_http_validate_url( $test_url );
	}

	/**
	 * Sanitize frontend ad/sidebar content.
	 *
	 * Script tags are intentionally not allowed. Use safe HTML, images,
	 * links, or a shortcode provided by a dedicated ad manager plugin.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private static function sanitize_ad_content( string $content ): string {
		$content = trim( $content );

		if ( '' === $content ) {
			return '';
		}

		if ( strlen( $content ) > 5000 ) {
			$content = substr( $content, 0, 5000 );
		}

		return wp_kses_post( $content );
	}

	/**
	 * Asset version.
	 *
	 * @param string $path Asset path.
	 *
	 * @return string
	 */
	private static function asset_version(
		string $path
	): string {
		$modified = is_file( $path )
			? filemtime( $path )
			: false;

		return is_int( $modified ) && $modified > 0
			? POWER_SCHEDULE_MANAGER_VERSION . '.' . $modified
			: POWER_SCHEDULE_MANAGER_VERSION;
	}
}
