<?php
/**
 * Xử lý quá trình vô hiệu hóa Cúp Điện Lâm Đồng.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thực hiện các tác vụ khi plugin bị vô hiệu hóa.
 *
 * Deactivate không phải uninstall. Class này không xóa dữ liệu.
 */
final class Power_Schedule_Manager_Deactivator {

	/**
	 * Danh sách cron hook dự phòng.
	 *
	 * Danh sách này phải được giữ đồng bộ với class-cron.php.
	 *
	 * @var array<int, string>
	 */
	private const array CRON_HOOKS = array(
		'power_schedule_manager_daily_maintenance',
		'power_schedule_manager_process_notifications',
		'power_schedule_manager_cloudflare_purge',
		'power_schedule_manager_weather_refresh',
	);

	/**
	 * Vô hiệu hóa plugin.
	 *
	 * @param bool $network_wide Có vô hiệu hóa toàn mạng hay không.
	 * @return void
	 */
	public static function deactivate(
		bool $network_wide = false
	): void {
		self::safe_do_action(
			'power_schedule_manager_before_deactivation',
			$network_wide
		);

		self::clear_cron_events();
		self::invalidate_plugin_cache();
		self::flush_rewrite_rules();

		self::safe_do_action(
			'power_schedule_manager_deactivated',
			$network_wide
		);
	}

	/**
	 * Hủy cron event của plugin.
	 *
	 * Ưu tiên dùng Cron service. Nếu file cron bị thiếu hoặc lỗi,
	 * sử dụng danh sách hook dự phòng.
	 *
	 * @return void
	 */
	private static function clear_cron_events(): void {
		$cleared_by_service = false;

		try {
			if (
				class_exists( 'Power_Schedule_Manager_Cron' )
				&& is_callable(
					array(
						'Power_Schedule_Manager_Cron',
						'clear_scheduled_events',
					)
				)
			) {
				Power_Schedule_Manager_Cron::clear_scheduled_events();

				$cleared_by_service = true;
			}
		} catch ( Throwable $throwable ) {
			self::log_debug_error(
				'cron_service_cleanup',
				$throwable
			);
		}

		if ( $cleared_by_service ) {
			return;
		}

		foreach ( self::CRON_HOOKS as $hook_name ) {
			wp_clear_scheduled_hook(
				$hook_name
			);
		}
	}

	/**
	 * Tăng cache generation của riêng plugin.
	 *
	 * Không gọi wp_cache_flush() vì thao tác đó có thể xóa cache
	 * của toàn bộ website.
	 *
	 * @return void
	 */
	private static function invalidate_plugin_cache(): void {
		$current_version = get_option(
			POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
			1
		);

		$current_version = is_numeric( $current_version )
			? max( 1, (int) $current_version )
			: 1;

		$new_version = $current_version >= PHP_INT_MAX - 1
			? 1
			: $current_version + 1;

		$updated = update_option(
			POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
			$new_version,
			false
		);

		if ( $updated ) {
			return;
		}

		$stored_version = get_option(
			POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
			false
		);

		if ( $new_version !== (int) $stored_version ) {
			self::log_debug_message(
				'cache_version_not_updated'
			);
		}
	}

	/**
	 * Làm mới rewrite rules.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules(): void {
		try {
			flush_rewrite_rules( false );
		} catch ( Throwable $throwable ) {
			self::log_debug_error(
				'rewrite_flush',
				$throwable
			);
		}
	}

	/**
	 * Chạy action nhưng không cho exception từ extension khác
	 * làm gián đoạn quá trình deactivate.
	 *
	 * @param string $hook_name   Tên hook.
	 * @param bool   $network_wide Trạng thái network deactivation.
	 * @return void
	 */
	private static function safe_do_action(
		string $hook_name,
		bool $network_wide
	): void {
		try {
			do_action(
				$hook_name,
				$network_wide
			);
		} catch ( Throwable $throwable ) {
			self::log_debug_error(
				$hook_name,
				$throwable
			);
		}
	}

	/**
	 * Ghi exception vào debug log.
	 *
	 * Không hiển thị lỗi trong giao diện deactivate.
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
				'Cúp Điện Lâm Đồng deactivation [%1$s]: %2$s',
				substr( (string) $context, 0, 80 ),
				power_schedule_manager_sanitize_log_value(
					$throwable->getMessage()
				)
			)
		);
	}

	/**
	 * Ghi thông báo kỹ thuật vào debug log.
	 *
	 * @param string $message Mã thông báo.
	 * @return void
	 */
	private static function log_debug_message(
		string $message
	): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$message = preg_replace(
			'/[^a-z0-9_-]/',
			'',
			strtolower( $message )
		);

		error_log(
			sprintf(
				'Cúp Điện Lâm Đồng deactivation: %s',
				substr( (string) $message, 0, 100 )
			)
		);
	}

	/**
	 * Ngăn khởi tạo class.
	 */
	private function __construct() {
	}
}
