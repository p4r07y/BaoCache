<?php
/**
 * Plugin Name:       Cúp Điện Lâm Đồng
 * Plugin URI:        https://lamdonghomnay.com/
 * Description:       Vận hành cổng thông tin, dữ liệu thị trường và tiện ích số dành cho Lâm Đồng.
 * Version:           0.38.16
 * Requires at least: 6.7
 * Requires PHP:      8.3
 * Author:            Nguyễn Hoàng Thái Bảo
 * Text Domain:       power-schedule-manager
 * Domain Path:       /languages
 * Update URI:        https://lamdonghomnay.com/power-schedule-manager/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Phiên bản plugin.
 */
define(
	'POWER_SCHEDULE_MANAGER_VERSION',
	'0.38.16'
);

/**
 * OneSignal App ID công khai của website.
 *
 * App ID không phải khóa bí mật. Có thể ghi đè hằng này trong wp-config.php
 * trước khi WordPress tải plugin nếu triển khai source cho một tên miền khác.
 */
if ( ! defined( 'POWER_SCHEDULE_MANAGER_ONESIGNAL_APP_ID' ) ) {
	define(
		'POWER_SCHEDULE_MANAGER_ONESIGNAL_APP_ID',
		'476f7cb5-5508-4b3d-b99e-4767190a3de9'
	);
}

/** OneSignal Safari Web ID used by the public browser SDK. */
if ( ! defined( 'POWER_SCHEDULE_MANAGER_ONESIGNAL_SAFARI_WEB_ID' ) ) {
	define(
		'POWER_SCHEDULE_MANAGER_ONESIGNAL_SAFARI_WEB_ID',
		'web.onesignal.auto.110555e6-7aae-4d44-9896-bfe7a2b1c987'
	);
}

/**
 * OneSignal REST API key for server-side delivery.
 *
 * The preferred configuration is the write-only encrypted field in WordPress
 * administration. A server constant remains supported as a migration fallback.
 */

/**
 * Phiên bản schema database.
 *
 * Chỉ Power_Schedule_Manager_Migrator được phép cập nhật option tương ứng.
 */
define(
	'POWER_SCHEDULE_MANAGER_SCHEMA_VERSION',
	'1.7.3'
);

/**
 * Phiên bản dữ liệu seed.
 *
 * Chỉ Power_Schedule_Manager_Units được phép cập nhật option tương ứng.
 */
define(
	'POWER_SCHEDULE_MANAGER_SEED_VERSION',
	'1.0.0'
);

/**
 * Phiên bản PHP tối thiểu.
 */
define(
	'POWER_SCHEDULE_MANAGER_MIN_PHP_VERSION',
	'8.3'
);

/**
 * Phiên bản WordPress tối thiểu.
 */
define(
	'POWER_SCHEDULE_MANAGER_MIN_WP_VERSION',
	'6.7'
);

/**
 * Phiên bản MySQL tối thiểu.
 */
define(
	'POWER_SCHEDULE_MANAGER_MIN_MYSQL_VERSION',
	'8.4.0'
);

/**
 * Phiên bản MariaDB tối thiểu khi dùng môi trường tương thích dự phòng.
 */
define(
	'POWER_SCHEDULE_MANAGER_MIN_MARIADB_VERSION',
	'11.8.0'
);

/**
 * Múi giờ nghiệp vụ mặc định.
 *
 * Timestamp trong custom table vẫn được lưu ở UTC.
 */
define(
	'POWER_SCHEDULE_MANAGER_TIMEZONE',
	'Asia/Ho_Chi_Minh'
);

/**
 * File bootstrap chính.
 */
define(
	'POWER_SCHEDULE_MANAGER_FILE',
	__FILE__
);

/**
 * Tên tương đối của plugin trong WordPress.
 */
define(
	'POWER_SCHEDULE_MANAGER_BASENAME',
	plugin_basename( __FILE__ )
);

/**
 * Đường dẫn tuyệt đối tới thư mục plugin.
 */
define(
	'POWER_SCHEDULE_MANAGER_PATH',
	plugin_dir_path( __FILE__ )
);

/**
 * URL công khai tới thư mục plugin.
 */
define(
	'POWER_SCHEDULE_MANAGER_URL',
	plugin_dir_url( __FILE__ )
);

/**
 * Text domain.
 */
define(
	'POWER_SCHEDULE_MANAGER_TEXT_DOMAIN',
	'power-schedule-manager'
);

/**
 * Option lưu phiên bản plugin.
 */
define(
	'POWER_SCHEDULE_MANAGER_VERSION_OPTION',
	'power_schedule_manager_version'
);

/**
 * Option lưu phiên bản schema.
 */
define(
	'POWER_SCHEDULE_MANAGER_SCHEMA_OPTION',
	'power_schedule_manager_schema_version'
);

/**
 * Option lưu phiên bản dữ liệu seed.
 */
define(
	'POWER_SCHEDULE_MANAGER_SEED_OPTION',
	'power_schedule_manager_seed_version'
);

/**
 * Option lưu thiết lập plugin.
 */
define(
	'POWER_SCHEDULE_MANAGER_SETTINGS_OPTION',
	'power_schedule_manager_settings'
);

/**
 * Option đánh dấu lần chuẩn hóa nhận diện đã hoàn tất.
 */
define(
	'POWER_SCHEDULE_MANAGER_BRAND_VERSION_OPTION',
	'power_schedule_manager_brand_version'
);

/**
 * Option dùng làm migration lock.
 */
define(
	'POWER_SCHEDULE_MANAGER_MIGRATION_LOCK_OPTION',
	'power_schedule_manager_migration_lock'
);

/**
 * Option lưu cache generation.
 */
define(
	'POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION',
	'power_schedule_manager_cache_version'
);

/**
 * Kiểm tra phiên bản PHP.
 *
 * Bootstrap không sử dụng cú pháp riêng của PHP 8.5 trước bước kiểm tra này,
 * giúp WordPress có cơ hội hiển thị thông báo khi máy chủ không tương thích.
 *
 * @return bool
 */
function power_schedule_manager_php_is_compatible() {
	return version_compare(
		PHP_VERSION,
		POWER_SCHEDULE_MANAGER_MIN_PHP_VERSION,
		'>='
	);
}

/**
 * Kiểm tra phiên bản WordPress.
 *
 * @return bool
 */
function power_schedule_manager_wp_is_compatible() {
	global $wp_version;

	return isset( $wp_version )
		&& is_string( $wp_version )
		&& version_compare(
			$wp_version,
			POWER_SCHEDULE_MANAGER_MIN_WP_VERSION,
			'>='
		);
}

/**
 * Hiển thị thông báo PHP không tương thích.
 *
 * @return void
 */
function power_schedule_manager_render_php_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$message = sprintf(
		/* translators: 1: Required PHP version, 2: Current PHP version. */
		__(
			'Cúp Điện Lâm Đồng yêu cầu PHP %1$s trở lên. Máy chủ hiện đang sử dụng PHP %2$s.',
			'power-schedule-manager'
		),
		POWER_SCHEDULE_MANAGER_MIN_PHP_VERSION,
		PHP_VERSION
	);

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( $message )
	);
}

/**
 * Hiển thị thông báo WordPress không tương thích.
 *
 * @return void
 */
function power_schedule_manager_render_wordpress_notice() {
	global $wp_version;

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$current_version = isset( $wp_version ) && is_string( $wp_version )
		? $wp_version
		: __( 'không xác định', 'power-schedule-manager' );

	$message = sprintf(
		/* translators: 1: Required WordPress version, 2: Current version. */
		__(
			'Cúp Điện Lâm Đồng yêu cầu WordPress %1$s trở lên. Website hiện đang sử dụng WordPress %2$s.',
			'power-schedule-manager'
		),
		POWER_SCHEDULE_MANAGER_MIN_WP_VERSION,
		$current_version
	);

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( $message )
	);
}

/**
 * Hiển thị thông báo khi bản cài đặt thiếu file bắt buộc.
 *
 * @return void
 */
function power_schedule_manager_render_incomplete_installation_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__(
			'Cúp Điện Lâm Đồng chưa được cài đặt đầy đủ. Vui lòng tải lại toàn bộ thư mục plugin.',
			'power-schedule-manager'
		)
	);
}

/**
 * Hiển thị thông báo lỗi bootstrap.
 *
 * Không hiển thị exception, đường dẫn máy chủ hoặc stack trace.
 *
 * @return void
 */
function power_schedule_manager_render_boot_error_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__(
			'Cúp Điện Lâm Đồng không thể khởi động đầy đủ. Hãy kiểm tra debug log hoặc liên hệ người quản trị máy chủ.',
			'power-schedule-manager'
		)
	);
}

/**
 * Vô hiệu hóa plugin khi môi trường không tương thích.
 *
 * @return void
 */
function power_schedule_manager_deactivate_incompatible_plugin() {
	if ( ! is_admin() ) {
		return;
	}

	if (
		power_schedule_manager_php_is_compatible()
		&& power_schedule_manager_wp_is_compatible()
	) {
		return;
	}

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	deactivate_plugins(
		POWER_SCHEDULE_MANAGER_BASENAME,
		true
	);
}

/**
 * Làm sạch một giá trị trước khi ghi debug log.
 *
 * @param mixed $value Giá trị cần ghi.
 * @return string
 */
function power_schedule_manager_sanitize_log_value( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = str_replace(
		array( "\r", "\n", "\0" ),
		' ',
		(string) $value
	);

	return substr( $value, 0, 2000 );
}

/**
 * Chuyển các giá trị nhận diện cũ đã lưu trong option của plugin.
 *
 * Chỉ thay chuỗi thương hiệu; các nội dung biên tập khác được giữ nguyên.
 *
 * @return void
 */
function power_schedule_manager_migrate_brand_settings() {
	$brand_version = get_option(
		POWER_SCHEDULE_MANAGER_BRAND_VERSION_OPTION,
		''
	);

	if ( '1.0.0' === $brand_version ) {
		return;
	}

	$settings = get_option(
		POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
		array()
	);

	if ( is_array( $settings ) ) {
		$old_brands = array(
			'Lâm Đồng Số',
			'Lâm Đồng Hôm Nay',
			'Lâm Đồng Mới',
			'Lâm đồng mới',
			'LĐ Hôm Nay',
		);
		$new_brands = array(
			'Cúp Điện Lâm Đồng',
			'Cúp Điện Lâm Đồng',
			'Cúp Điện Lâm Đồng',
			'Cúp Điện Lâm Đồng',
			'Cúp Điện LĐ',
		);
		$updated     = false;

		$replace_brand = static function ( &$value ) use (
			&$replace_brand,
			$old_brands,
			$new_brands,
			&$updated
		): void {
			if ( is_array( $value ) ) {
				foreach ( $value as &$nested_value ) {
					$replace_brand( $nested_value );
				}
				unset( $nested_value );
				return;
			}

			if ( ! is_string( $value ) ) {
				return;
			}

			$normalized = str_replace(
				$old_brands,
				$new_brands,
				$value
			);

			if ( $normalized !== $value ) {
				$value   = $normalized;
				$updated = true;
			}
		};

		$replace_brand( $settings );

		if ( $updated ) {
			update_option(
				POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
				$settings,
				false
			);
		}
	}

	update_option(
		POWER_SCHEDULE_MANAGER_BRAND_VERSION_OPTION,
		'1.0.0',
		false
	);
}

/**
 * Dừng bootstrap nếu PHP không tương thích.
 */
if ( ! power_schedule_manager_php_is_compatible() ) {
	add_action(
		'admin_notices',
		'power_schedule_manager_render_php_notice'
	);

	add_action(
		'admin_init',
		'power_schedule_manager_deactivate_incompatible_plugin'
	);

	return;
}

/**
 * Dừng bootstrap nếu WordPress không tương thích.
 */
if ( ! power_schedule_manager_wp_is_compatible() ) {
	add_action(
		'admin_notices',
		'power_schedule_manager_render_wordpress_notice'
	);

	add_action(
		'admin_init',
		'power_schedule_manager_deactivate_incompatible_plugin'
	);

	return;
}

/**
 * Tải autoloader duy nhất của plugin.
 */
$power_schedule_manager_autoloader_file =
	POWER_SCHEDULE_MANAGER_PATH . 'includes/class-autoloader.php';

if ( ! is_readable( $power_schedule_manager_autoloader_file ) ) {
	add_action(
		'admin_notices',
		'power_schedule_manager_render_incomplete_installation_notice'
	);

	return;
}

require_once $power_schedule_manager_autoloader_file;

/**
 * Xác minh autoloader trước khi sử dụng.
 */
if (
	! class_exists( 'Power_Schedule_Manager_Autoloader', false )
	|| ! is_callable(
		array(
			'Power_Schedule_Manager_Autoloader',
			'register',
		)
	)
) {
	add_action(
		'admin_notices',
		'power_schedule_manager_render_incomplete_installation_notice'
	);

	return;
}

Power_Schedule_Manager_Autoloader::register();

add_action(
	'plugins_loaded',
	'power_schedule_manager_migrate_brand_settings',
	15
);

/**
 * Đăng ký activation hook.
 */
register_activation_hook(
	POWER_SCHEDULE_MANAGER_FILE,
	array(
		'Power_Schedule_Manager_Activator',
		'activate',
	)
);

/**
 * Đăng ký deactivation hook.
 */
register_deactivation_hook(
	POWER_SCHEDULE_MANAGER_FILE,
	array(
		'Power_Schedule_Manager_Deactivator',
		'deactivate',
	)
);

/**
 * Khởi động plugin.
 *
 * @return void
 */
function power_schedule_manager_boot() {
	try {
		if ( ! class_exists( 'Power_Schedule_Manager_Plugin' ) ) {
			throw new RuntimeException(
				'plugin_orchestrator_not_found'
			);
		}

		$plugin = Power_Schedule_Manager_Plugin::instance();

		if ( ! is_callable( array( $plugin, 'run' ) ) ) {
			throw new RuntimeException(
				'plugin_run_method_not_found'
			);
		}

		$plugin->run();
	} catch ( Throwable $throwable ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Cúp Điện Lâm Đồng bootstrap failure: %1$s in %2$s:%3$d',
					power_schedule_manager_sanitize_log_value(
						$throwable->getMessage()
					),
					power_schedule_manager_sanitize_log_value(
						$throwable->getFile()
					),
					(int) $throwable->getLine()
				)
			);
		}

		if ( is_admin() ) {
			add_action(
				'admin_notices',
				'power_schedule_manager_render_boot_error_notice'
			);
		}
	}
}

add_action(
	'plugins_loaded',
	'power_schedule_manager_boot',
	20
);
