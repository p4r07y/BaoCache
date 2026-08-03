<?php
/**
 * Read-only operational and database diagnostics.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds a bounded, cacheable health snapshot for administrators.
 *
 * This class never repairs or deletes data. Diagnostic queries use indexed
 * joins and return counts plus small samples, making the screen safe to open
 * on a large production database.
 */
final class Power_Schedule_Manager_System_Health {

	/**
	 * Cache lifetime for one health snapshot.
	 */
	private const int CACHE_TTL = 60;

	/**
	 * Maximum sample identifiers returned by one integrity check.
	 */
	private const int SAMPLE_LIMIT = 12;

	/**
	 * Cached snapshot in the current request.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $request_cache = null;

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}

	/**
	 * Return system, integrity, and attention diagnostics.
	 *
	 * @return array<string,mixed>
	 */
	public static function snapshot(): array {
		if ( null !== self::$request_cache ) {
			return self::$request_cache;
		}

		$cache_key = 'system_health_'
			. (string) get_option(
				POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
				'1'
			);

		$found  = false;
		$cached = Power_Schedule_Manager_Cache::get(
			'system_health',
			array( $cache_key ),
			$found
		);

		if ( $found && is_array( $cached ) ) {
			self::$request_cache = $cached;

			return $cached;
		}

		$missing_tables = Power_Schedule_Manager_Database::missing_tables();
		$integrity      = array() === $missing_tables
			? self::integrity_checks()
			: array();

		$snapshot = array(
			'generated_at_utc' => Power_Schedule_Manager_Database::utc_now(),
			'environment'      => self::environment_checks( $missing_tables ),
			'tables'           => self::table_statistics(),
			'integrity'        => $integrity,
			'attention'        => self::attention_queue( $integrity ),
			'benchmark'        => array() === $missing_tables
				? Power_Schedule_Manager_Benchmark::run()
				: array(),
		);

		Power_Schedule_Manager_Cache::set(
			'system_health',
			array( $cache_key ),
			$snapshot,
			self::CACHE_TTL
		);

		self::$request_cache = $snapshot;

		return $snapshot;
	}

	/**
	 * Environment and background-processing checks.
	 *
	 * @param array<string,string> $missing_tables Missing plugin tables.
	 * @return array<int,array<string,mixed>>
	 */
	private static function environment_checks( array $missing_tables ): array {
		$stored_schema = (string) get_option(
			POWER_SCHEDULE_MANAGER_SCHEMA_OPTION,
			''
		);

		$next_cron = Power_Schedule_Manager_Cron::next_run_timestamp();
		$debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$settings = is_array( $settings ) ? $settings : array();
		$enabled_channels = array_values(
			array_filter(
				array(
					! empty( $settings['push_enabled'] )
						? 'Web Push'
						: '',
					! empty( $settings['telegram_enabled'] )
						? 'Telegram'
						: '',
					! empty( $settings['webhook_enabled'] )
						? 'Webhook'
						: '',
					! empty( $settings['zalo_enabled'] )
						? 'Zalo OA'
						: '',
				)
			)
		);
		$notification_issues = array();
		$onesignal_rest_api_key = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_ONESIGNAL_REST_API_KEY',
			(string) ( $settings['onesignal_rest_api_key_encrypted'] ?? '' )
		);
		$telegram_bot_token = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_TELEGRAM_BOT_TOKEN',
			(string) ( $settings['telegram_bot_token_encrypted'] ?? '' )
		);
		$webhook_secret = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_WEBHOOK_SECRET',
			(string) ( $settings['webhook_secret_encrypted'] ?? '' )
		);
		$zalo_access_token = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_ZALO_ACCESS_TOKEN',
			(string) ( $settings['zalo_access_token_encrypted'] ?? '' )
		);
		if (
			! empty( $settings['push_enabled'] )
			&& (
				1 !== preg_match(
					'/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/i',
					Power_Schedule_Manager_Assets::onesignal_app_id()
				)
				|| (
					! empty( $settings['push_delivery_enabled'] )
					&& '' === $onesignal_rest_api_key
				)
			)
		) {
			$notification_issues[] = 'Web Push';
		}

		if (
			! empty( $settings['telegram_enabled'] )
			&& (
				'' === $telegram_bot_token
				|| '' === trim(
					(string) (
						$settings['telegram_chat_id'] ?? ''
					)
				)
			)
		) {
			$notification_issues[] = 'Telegram';
		}
		if (
			! empty( $settings['webhook_enabled'] )
			&& (
				'' === $webhook_secret
				|| ! wp_http_validate_url(
					(string) ( $settings['webhook_url'] ?? '' )
				)
			)
		) {
			$notification_issues[] = 'Webhook';
		}
		if (
			! empty( $settings['zalo_enabled'] )
			&& (
				'' === $zalo_access_token
				|| '' === trim(
					(string) (
						$settings['zalo_recipient_id'] ?? ''
					)
				)
			)
		) {
			$notification_issues[] = 'Zalo OA';
		}
		$required_assets = array(
			'public/assets/frontend.css',
			'public/assets/frontend.js',
			'public/assets/frontend.min.css',
			'public/assets/frontend.min.js',
			'public/assets/frontend-schedule.min.css',
			'public/assets/frontend-layout.min.css',
			'public/assets/frontend-content.min.css',
			'public/assets/frontend-refinements.min.css',
			'public/assets/frontend-lottery.min.css',
			'public/assets/frontend-market.min.css',
			'public/assets/frontend-weather.min.css',
			'public/assets/frontend-portal.min.css',
			'public/assets/frontend-community.min.css',
			'public/assets/vendor/leaflet/leaflet.css',
			'public/assets/vendor/leaflet/leaflet.js',
			'admin/assets/admin.css',
			'admin/assets/admin.js',
			'admin/assets/dashboard.css',
			'admin/assets/osm-road-editor.js',
		);

		if ( ! empty( $settings['push_enabled'] ) ) {
			$required_assets[] = 'public/assets/push.css';
			$required_assets[] = 'public/assets/push.js';
			$required_assets[] = 'public/assets/push.min.css';
			$required_assets[] = 'public/assets/push.min.js';
			$required_assets[] = 'public/push/OneSignalSDKWorker.js';
		}

		if ( ! empty( $settings['pwa_enabled'] ) ) {
			$required_assets[] = 'public/assets/pwa.css';
			$required_assets[] = 'public/assets/pwa.js';
			$required_assets[] = 'public/assets/pwa.min.css';
			$required_assets[] = 'public/assets/pwa.min.js';
			$required_assets[] = 'public/assets/pwa-icon.svg';
		}

		$missing_assets = array_values(
			array_filter(
				$required_assets,
				static fn ( string $relative_path ): bool =>
					! is_readable(
						POWER_SCHEDULE_MANAGER_PATH
							. $relative_path
				)
			)
		);
		$leaflet_files = array(
			'public/assets/vendor/leaflet/leaflet.js' => array(
				'minimum_size' => 100000,
				'signature'    => '1.9.4',
			),
			'public/assets/vendor/leaflet/leaflet.css' => array(
				'minimum_size' => 10000,
				'signature'    => '.leaflet-container',
			),
		);
		$invalid_leaflet_files = array();

		foreach ( $leaflet_files as $relative_path => $validation ) {
			$absolute_path = POWER_SCHEDULE_MANAGER_PATH . $relative_path;
			$content = is_readable( $absolute_path )
				? file_get_contents( $absolute_path )
				: false;
			if (
				false === $content
				|| strlen( $content ) < $validation['minimum_size']
				|| false === strpos( $content, $validation['signature'] )
			) {
				$invalid_leaflet_files[] = $relative_path;
			}
		}

		$map_provider = sanitize_key(
			(string) ( $settings['map_provider'] ?? 'osm' )
		);
		$map_configuration =
			Power_Schedule_Manager_Assets::provider_configuration(
				$settings
			);
		$cloudflare = Power_Schedule_Manager_Cloudflare::status();
		$cloudflare_ready = $cloudflare['zone']
			&& $cloudflare['token'];
		$lottery_settings = get_option(
			'power_schedule_manager_lottery_settings',
			array()
		);
		$lottery_settings = is_array( $lottery_settings )
			? $lottery_settings
			: array();
		$lottery_enabled = ! empty( $lottery_settings['enabled'] );
		$lottery_key_source = Power_Schedule_Manager_Secrets::source(
			'POWER_SCHEDULE_MANAGER_XOSO_API_KEY',
			(string) ( $lottery_settings['api_key_encrypted'] ?? '' )
		);
		$lottery_scheduled = wp_next_scheduled(
			'power_schedule_manager_refresh_lottery'
		);

		return array(
			self::check(
				'plugin',
				__( 'Cúp Điện Lâm Đồng', 'power-schedule-manager' ),
				'good',
				sprintf(
					'v%s',
					POWER_SCHEDULE_MANAGER_VERSION
				),
				sprintf(
					/* translators: %s: Target schema version. */
					__( 'Schema dữ liệu mục tiêu: %s.', 'power-schedule-manager' ),
					POWER_SCHEDULE_MANAGER_SCHEMA_VERSION
				)
			),
			self::check(
				'php',
				'PHP',
				version_compare(
					PHP_VERSION,
					POWER_SCHEDULE_MANAGER_MIN_PHP_VERSION,
					'>='
				) ? 'good' : 'error',
				PHP_VERSION,
				sprintf(
					/* translators: %s: Minimum PHP version. */
					__( 'Yêu cầu tối thiểu PHP %s.', 'power-schedule-manager' ),
					POWER_SCHEDULE_MANAGER_MIN_PHP_VERSION
				)
			),
			self::check(
				'database',
				__( 'Database', 'power-schedule-manager' ),
				Power_Schedule_Manager_Database::is_supported_server()
					? 'good'
					: 'error',
				sprintf(
					'%1$s %2$s',
					strtoupper(
						Power_Schedule_Manager_Database::server_family()
					),
					Power_Schedule_Manager_Database::server_version()
				),
				Power_Schedule_Manager_Database::supports_utf8mb4()
					? __( 'Kết nối hỗ trợ utf8mb4.', 'power-schedule-manager' )
					: __( 'Kết nối chưa xác nhận hỗ trợ utf8mb4.', 'power-schedule-manager' )
			),
			self::check(
				'schema',
				__( 'Schema plugin', 'power-schedule-manager' ),
				array() === $missing_tables
					&& POWER_SCHEDULE_MANAGER_SCHEMA_VERSION === $stored_schema
						? 'good'
						: 'error',
				'' !== $stored_schema ? $stored_schema : '—',
				array() === $missing_tables
					? sprintf(
						/* translators: %s: Expected schema version. */
						__( 'Phiên bản yêu cầu: %s.', 'power-schedule-manager' ),
						POWER_SCHEDULE_MANAGER_SCHEMA_VERSION
					)
					: sprintf(
						/* translators: %d: Missing table count. */
						_n(
							'Thiếu %d bảng dữ liệu.',
							'Thiếu %d bảng dữ liệu.',
							count( $missing_tables ),
							'power-schedule-manager'
						),
						count( $missing_tables )
					)
			),
			self::check(
				'object_cache',
				__( 'Object cache', 'power-schedule-manager' ),
				wp_using_ext_object_cache() ? 'good' : 'warning',
				wp_using_ext_object_cache()
					? __( 'Đang hoạt động', 'power-schedule-manager' )
					: __( 'Chưa bật', 'power-schedule-manager' ),
				wp_using_ext_object_cache()
					? __( 'Query frontend và dashboard dùng cache dùng chung.', 'power-schedule-manager' )
					: __( 'Website vẫn chạy, nhưng nên bật Redis object cache khi lưu lượng tăng.', 'power-schedule-manager' )
			),
			self::check(
				'cron',
				__( 'Tác vụ bảo trì', 'power-schedule-manager' ),
				null !== $next_cron ? 'good' : 'error',
				null !== $next_cron
					? wp_date(
						'H:i d/m/Y',
						$next_cron,
						new DateTimeZone(
							POWER_SCHEDULE_MANAGER_TIMEZONE
						)
					)
					: __( 'Chưa được lên lịch', 'power-schedule-manager' ),
				defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON
					? __( 'WP-Cron nội bộ đang tắt; máy chủ phải gọi wp-cron.php định kỳ.', 'power-schedule-manager' )
					: __( 'WordPress chịu trách nhiệm kích hoạt tác vụ.', 'power-schedule-manager' )
			),
			self::check(
				'notifications',
				__( 'Kênh thông báo', 'power-schedule-manager' ),
				array() !== $notification_issues
					? 'error'
					: (
						array() !== $enabled_channels
							? 'good'
							: 'warning'
					),
				array() !== $enabled_channels
					? implode( ', ', $enabled_channels )
					: __( 'Chưa bật', 'power-schedule-manager' ),
				array() !== $notification_issues
					? sprintf(
						/* translators: %s: Misconfigured channels. */
						__(
							'Thiếu token, secret hoặc người nhận cho: %s.',
							'power-schedule-manager'
						),
						implode( ', ', $notification_issues )
					)
					: (
						array() !== $enabled_channels
							? __( 'Thông báo được gửi qua hàng đợi có chống trùng và tự thử lại.', 'power-schedule-manager' )
							: __( 'Website vẫn hoạt động; bật Telegram hoặc webhook nếu cần cảnh báo sau khi nhập.', 'power-schedule-manager' )
					)
			),
			self::check(
				'lottery',
				__( 'Đồng bộ kết quả xổ số', 'power-schedule-manager' ),
				$lottery_enabled
					? (
						'missing' !== $lottery_key_source
						&& false !== $lottery_scheduled
							? 'good'
							: 'error'
					)
					: 'warning',
				$lottery_enabled
					? (
						'missing' !== $lottery_key_source
							? __( 'Đã bật', 'power-schedule-manager' )
							: __( 'Thiếu API key', 'power-schedule-manager' )
					)
					: __( 'Chưa bật', 'power-schedule-manager' ),
				$lottery_enabled
					? (
						'' !== (string) (
							$lottery_settings['last_success_at_utc'] ?? ''
						)
							? sprintf(
								/* translators: %s: Last successful sync time in UTC. */
								__( 'Đồng bộ thành công gần nhất: %s UTC.', 'power-schedule-manager' ),
								(string) $lottery_settings['last_success_at_utc']
							)
							: __( 'Chưa có lần đồng bộ thành công; kiểm tra API key và chạy thử thủ công.', 'power-schedule-manager' )
					)
					: __( 'Bật khi website cần công bố kết quả xổ số đã xác minh.', 'power-schedule-manager' )
			),
			self::check(
				'pwa',
				__( 'Ứng dụng PWA', 'power-schedule-manager' ),
				! empty( $settings['pwa_enabled'] )
					? (
						is_ssl()
							? 'good'
							: 'error'
					)
					: 'warning',
				! empty( $settings['pwa_enabled'] )
					? __( 'Đã bật', 'power-schedule-manager' )
					: __( 'Chưa bật', 'power-schedule-manager' ),
				! empty( $settings['pwa_enabled'] )
					? (
						! is_ssl()
							? __( 'PWA cần HTTPS để cài đặt và đăng ký service worker.', 'power-schedule-manager' )
							: (
								'' === trim(
									(string) (
										$settings['pwa_icon_url'] ?? ''
									)
								)
								&& '' === get_site_icon_url( 512 )
									? __( 'Đang dùng biểu tượng SVG dự phòng; nên cấu hình Site Icon 512×512 để nhận diện tốt hơn.', 'power-schedule-manager' )
									: __( 'Manifest và giao diện gợi ý cài đặt đã sẵn sàng.', 'power-schedule-manager' )
							)
					)
					: __( 'Có thể bật khi muốn người dùng thêm website vào màn hình chính.', 'power-schedule-manager' )
			),
			self::check(
				'public_api',
				__( 'API ứng dụng', 'power-schedule-manager' ),
				! empty( $settings['api_enabled'] )
					? 'good'
					: 'warning',
				! empty( $settings['api_enabled'] )
					? __( 'API v1 đang mở', 'power-schedule-manager' )
					: __( 'Chưa bật', 'power-schedule-manager' ),
				! empty( $settings['api_enabled'] )
					? sprintf(
						/* translators: %d: Requests per minute. */
						__( 'Chỉ đọc, giới hạn %d yêu cầu/phút/IP.', 'power-schedule-manager' ),
						min(
							3000,
							max(
								30,
								absint(
									$settings['api_rate_limit'] ?? 180
								)
							)
						)
					)
					: __( 'Giữ tắt cho đến khi ứng dụng hoặc đối tác bắt đầu sử dụng.', 'power-schedule-manager' )
			),
			self::check(
				'map_provider',
				__( 'Nguồn tile bản đồ', 'power-schedule-manager' ),
				'disabled' === $map_provider
					? 'warning'
					: (
						! empty( $map_configuration['enabled'] )
							? 'good'
							: 'error'
					),
				(string) ( $map_configuration['provider'] ?? $map_provider ),
				'disabled' === $map_provider
					? __( 'Bản đồ đang tắt; dữ liệu GeoJSON vẫn được giữ.', 'power-schedule-manager' )
					: (
						! empty( $map_configuration['enabled'] )
							? __( 'Leaflet đã có cấu hình tile hợp lệ.', 'power-schedule-manager' )
							: __( 'Thiếu khóa API, style hoặc URL tile hợp lệ cho nhà cung cấp đã chọn.', 'power-schedule-manager' )
					)
			),
			self::check(
				'cloudflare',
				__( 'Cloudflare cache purge', 'power-schedule-manager' ),
				$cloudflare['enabled']
					? ( $cloudflare_ready ? 'good' : 'error' )
					: 'warning',
				$cloudflare['enabled']
					? (
						$cloudflare_ready
							? __( 'Đã cấu hình', 'power-schedule-manager' )
							: __( 'Thiếu cấu hình', 'power-schedule-manager' )
					)
					: __( 'Chưa bật', 'power-schedule-manager' ),
				$cloudflare['enabled']
					? (
						$cloudflare_ready
							? sprintf(
								/* translators: %d: Queued URL count. */
								__( '%d URL đang chờ xóa cache theo lô.', 'power-schedule-manager' ),
								absint( $cloudflare['queued'] )
							)
							: __( 'Cần Zone ID và Cloudflare API token trong trang cài đặt.', 'power-schedule-manager' )
					)
					: __( 'Chỉ cần bật khi website dùng Cloudflare cache trang.', 'power-schedule-manager' )
			),
			self::check(
				'file_editor',
				__( 'Sửa file trong quản trị', 'power-schedule-manager' ),
				defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT
					? 'good'
					: 'warning',
				defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT
					? __( 'Đã khóa', 'power-schedule-manager' )
					: __( 'Đang cho phép', 'power-schedule-manager' ),
				__( 'Production nên đặt DISALLOW_FILE_EDIT thành true.', 'power-schedule-manager' )
			),
			self::check(
				'https',
				__( 'HTTPS', 'power-schedule-manager' ),
				'https' === wp_parse_url( home_url( '/' ), PHP_URL_SCHEME )
					? 'good'
					: 'error',
				'https' === wp_parse_url( home_url( '/' ), PHP_URL_SCHEME )
					? __( 'Đang sử dụng', 'power-schedule-manager' )
					: __( 'Chưa xác nhận', 'power-schedule-manager' ),
				__( 'Frontend, trang quản trị và tile bản đồ production nên dùng HTTPS.', 'power-schedule-manager' )
			),
			self::check(
				'debug_display',
				__( 'Hiển thị lỗi PHP', 'power-schedule-manager' ),
				defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY
					? 'warning'
					: 'good',
				defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY
					? __( 'Đang bật', 'power-schedule-manager' )
					: __( 'Đã tắt', 'power-schedule-manager' ),
				__( 'Production không nên đưa thông báo lỗi kỹ thuật ra màn hình công khai.', 'power-schedule-manager' )
			),
			self::check(
				'debug_mode',
				__( 'Chế độ gỡ lỗi', 'power-schedule-manager' ),
				$debug_enabled ? 'warning' : 'good',
				$debug_enabled
					? __( 'WP_DEBUG đang bật', 'power-schedule-manager' )
					: __( 'Đã tắt', 'power-schedule-manager' ),
				$debug_enabled
					? __( 'Chỉ bật WP_DEBUG tạm thời khi điều tra lỗi; production nên tắt sau khi hoàn tất.', 'power-schedule-manager' )
					: __( 'Website không ghi log gỡ lỗi liên tục trong vận hành bình thường.', 'power-schedule-manager' )
			),
			self::check(
				'plugin_assets',
				__( 'Tệp giao diện plugin', 'power-schedule-manager' ),
				array() === $missing_assets ? 'good' : 'error',
				array() === $missing_assets
					? __( 'Đầy đủ', 'power-schedule-manager' )
					: sprintf(
						/* translators: %d: Missing asset count. */
						_n(
							'Thiếu %d tệp',
							'Thiếu %d tệp',
							count( $missing_assets ),
							'power-schedule-manager'
						),
						count( $missing_assets )
					),
				array() === $missing_assets
					? __( 'CSS, JavaScript và thư viện bản đồ cần thiết đều có thể đọc.', 'power-schedule-manager' )
					: implode( ', ', $missing_assets )
			),
			self::check(
				'leaflet_integrity',
				__( 'Thư viện bản đồ Leaflet', 'power-schedule-manager' ),
				array() === $invalid_leaflet_files ? 'good' : 'error',
				array() === $invalid_leaflet_files
					? 'Leaflet 1.9.4'
					: __( 'Tệp không hợp lệ', 'power-schedule-manager' ),
				array() === $invalid_leaflet_files
					? __( 'JavaScript và CSS Leaflet 1.9.4 có thể đọc và có cấu trúc hợp lệ.', 'power-schedule-manager' )
					: sprintf(
						/* translators: %s: Invalid Leaflet asset paths. */
						__(
							'Cần tải lại đúng tệp: %s.',
							'power-schedule-manager'
						),
						implode( ', ', $invalid_leaflet_files )
					)
			),
			self::check(
				'permalinks',
				__( 'Đường dẫn thân thiện', 'power-schedule-manager' ),
				'' !== (string) get_option( 'permalink_structure', '' )
					? 'good'
					: 'warning',
				'' !== (string) get_option( 'permalink_structure', '' )
					? __( 'Đang hoạt động', 'power-schedule-manager' )
					: __( 'Đường dẫn mặc định', 'power-schedule-manager' ),
				__( 'SEO trang lịch cần cấu trúc permalink khác chế độ Plain.', 'power-schedule-manager' )
			),
		);
	}

	/**
	 * Count rows only for tables that exist.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function table_statistics(): array {
		global $wpdb;

		$statistics  = array();
		$table_names = array_values(
			Power_Schedule_Manager_Database::tables()
		);
		$estimates   = array();

		if ( array() !== $table_names ) {
			$placeholders = implode(
				',',
				array_fill( 0, count( $table_names ), '%s' )
			);

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT TABLE_NAME, TABLE_ROWS
					FROM information_schema.TABLES
					WHERE TABLE_SCHEMA = DATABASE()
						AND TABLE_NAME IN ({$placeholders})",
					$table_names
				),
				ARRAY_A
			);

			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$name = isset( $row['TABLE_NAME'] )
						? (string) $row['TABLE_NAME']
						: '';

					if ( '' !== $name ) {
						$estimates[ $name ] = absint(
							$row['TABLE_ROWS'] ?? 0
						);
					}
				}
			}
		}

		foreach ( Power_Schedule_Manager_Database::tables() as $key => $table ) {
			if ( ! array_key_exists( $table, $estimates ) ) {
				$statistics[] = array(
					'key'    => $key,
					'status' => 'missing',
					'rows'   => null,
				);
				continue;
			}

			$statistics[] = array(
				'key'    => $key,
				'status' => 'available',
				'rows'   => $estimates[ $table ],
			);
		}

		return $statistics;
	}

	/**
	 * Run bounded relational-integrity checks.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function integrity_checks(): array {
		global $wpdb;

		$events       = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$units        = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$imports      = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$places       = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$aliases      = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACE_ALIASES
		);
		$event_places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);
		$locations    = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_LOCATIONS
		);
		$posts        = $wpdb->posts;
		$post_type    = Power_Schedule_Manager_Post_Type::POST_TYPE;
		$stale_before = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		return array(
			self::integrity_result(
				'event_unit_mismatch',
				__( 'Sự kiện có mã và ID đơn vị không đồng nhất', 'power-schedule-manager' ),
				"SELECT events.id
					FROM {$events} AS events
					LEFT JOIN {$units} AS units
						ON units.code = events.unit_code
					WHERE units.id IS NULL
						OR units.id <> events.unit_id",
				array()
				),
			self::integrity_result(
				'place_unit_mismatch',
				__( 'Địa điểm có mã và ID đơn vị không đồng nhất', 'power-schedule-manager' ),
				"SELECT places.id
					FROM {$places} AS places
					LEFT JOIN {$units} AS units
						ON units.code = places.unit_code
					WHERE units.id IS NULL
						OR units.id <> places.unit_id",
				array()
			),
			self::integrity_result(
				'orphan_events',
				__( 'Sự kiện thiếu bài lịch hợp lệ', 'power-schedule-manager' ),
				"SELECT events.id
					FROM {$events} AS events
					LEFT JOIN {$posts} AS posts
						ON posts.ID = events.post_id
						AND posts.post_type = %s
						AND posts.post_status <> 'trash'
					WHERE events.deleted_at_utc IS NULL
						AND (events.post_id = 0 OR posts.ID IS NULL)",
				array( $post_type )
			),
			self::integrity_result(
				'orphan_event_places',
				__( 'Liên kết bản đồ mồ côi', 'power-schedule-manager' ),
				"SELECT links.event_id
					FROM {$event_places} AS links
					LEFT JOIN {$events} AS events ON events.id = links.event_id
					LEFT JOIN {$places} AS places ON places.id = links.place_id
					WHERE events.id IS NULL OR places.id IS NULL",
				array()
			),
			self::integrity_result(
				'orphan_aliases',
				__( 'Bí danh địa điểm mồ côi', 'power-schedule-manager' ),
				"SELECT aliases.id
					FROM {$aliases} AS aliases
					LEFT JOIN {$places} AS places ON places.id = aliases.place_id
					WHERE places.id IS NULL",
				array()
			),
			self::integrity_result(
				'orphan_legacy_locations',
				__( 'Vị trí cũ thiếu sự kiện', 'power-schedule-manager' ),
				"SELECT locations.id
					FROM {$locations} AS locations
					LEFT JOIN {$events} AS events ON events.id = locations.event_id
					WHERE events.id IS NULL",
				array()
			),
			self::integrity_result(
				'stale_imports',
				__( 'Lần nhập bị treo quá một giờ', 'power-schedule-manager' ),
				"SELECT id
					FROM {$imports}
					WHERE status = 'running'
						AND started_at_utc < %s",
				array( $stale_before )
			),
			self::integrity_result(
				'pending_places',
				__( 'Địa điểm đang chờ hoàn thiện', 'power-schedule-manager' ),
				"SELECT places.id
					FROM {$places} AS places
					WHERE places.status = 'pending'",
				array()
			),
			self::integrity_result(
				'active_places_without_geometry',
				__( 'Địa điểm đang dùng nhưng thiếu hình học', 'power-schedule-manager' ),
				"SELECT places.id
					FROM {$places} AS places
					WHERE places.status = 'active'
						AND (places.geojson IS NULL OR places.geojson = '')
						AND (places.center_lat IS NULL OR places.center_lng IS NULL)",
				array()
			),
			self::integrity_result(
				'events_without_place_links',
				__(
					'Lịch sắp tới chưa liên kết thư viện bản đồ',
					'power-schedule-manager'
				),
				"SELECT events.id
					FROM {$events} AS events
					LEFT JOIN {$event_places} AS links
						ON links.event_id = events.id
					WHERE events.deleted_at_utc IS NULL
						AND events.end_at_utc >= %s
						AND links.event_id IS NULL",
				array( Power_Schedule_Manager_Database::utc_now() )
			),
		);
	}

	/**
	 * Run one count and sample query from a fixed internal SQL template.
	 *
	 * @param string             $key       Check key.
	 * @param string             $label     Human label.
	 * @param string             $base_sql  SELECT query returning one ID column.
	 * @param array<int,scalar>  $values    Prepared values.
	 * @return array<string,mixed>
	 */
	private static function integrity_result(
		string $key,
		string $label,
		string $base_sql,
		array $values
	): array {
		global $wpdb;

		$count_sql = "SELECT COUNT(*) FROM ({$base_sql}) AS psm_integrity";
		$sample_sql = "{$base_sql} LIMIT " . self::SAMPLE_LIMIT;

		if ( array() !== $values ) {
			$count_sql  = $wpdb->prepare( $count_sql, $values );
			$sample_sql = $wpdb->prepare( $sample_sql, $values );
		}

		$count = (int) $wpdb->get_var( $count_sql );
		$ids   = $count > 0
			? $wpdb->get_col( $sample_sql )
			: array();

		return array(
			'key'     => $key,
			'label'   => $label,
			'status'  => $count > 0 ? 'warning' : 'good',
			'count'   => $count,
			'samples' => is_array( $ids )
				? array_map( 'absint', $ids )
				: array(),
		);
	}

	/**
	 * Build a prioritised, actionable queue.
	 *
	 * @param array<int,array<string,mixed>> $integrity Integrity results.
	 * @return array<int,array<string,mixed>>
	 */
	private static function attention_queue( array $integrity ): array {
		global $wpdb;

		$queue = array();

		foreach ( $integrity as $check ) {
			$count = absint( $check['count'] ?? 0 );

			if ( $count < 1 ) {
				continue;
			}

			$queue[] = array(
				'severity' => 'warning',
				'title'    => (string) ( $check['label'] ?? '' ),
				'count'    => $count,
				'target'   => in_array(
					(string) ( $check['key'] ?? '' ),
					array(
						'pending_places',
						'active_places_without_geometry',
						'events_without_place_links',
					),
					true
				) ? 'places' : 'integrity',
			);
		}

		$imports = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$failed  = (int) $wpdb->get_var(
			"SELECT COUNT(*)
				FROM {$imports}
				WHERE status = 'failed'
					AND started_at_utc >= UTC_TIMESTAMP() - INTERVAL 7 DAY"
		);

		if ( $failed > 0 ) {
			$queue[] = array(
				'severity' => 'error',
				'title'    => __( 'Lần nhập thất bại trong 7 ngày', 'power-schedule-manager' ),
				'count'    => $failed,
				'target'   => 'history',
			);
		}

		$drafts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type = %s AND post_status = 'draft'",
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		);

		if ( $drafts > 0 ) {
			$queue[] = array(
				'severity' => 'warning',
				'title'    => __( 'Lịch đang chờ xuất bản', 'power-schedule-manager' ),
				'count'    => $drafts,
				'target'   => 'drafts',
			);
		}

		$notifications = Power_Schedule_Manager_Notifications::statistics();

		if ( $notifications['failed'] > 0 ) {
			$queue[] = array(
				'severity' => 'error',
				'title'    => __( 'Thông báo gửi thất bại', 'power-schedule-manager' ),
				'count'    => $notifications['failed'],
				'target'   => 'integrity',
			);
		}

		if ( $notifications['pending'] > 50 ) {
			$queue[] = array(
				'severity' => 'warning',
				'title'    => __( 'Hàng đợi thông báo đang tồn đọng', 'power-schedule-manager' ),
				'count'    => $notifications['pending'],
				'target'   => 'integrity',
			);
		}

		usort(
			$queue,
			static function ( array $left, array $right ): int {
				$weight = array( 'error' => 0, 'warning' => 1 );

				return ( $weight[ $left['severity'] ] ?? 2 )
					<=> ( $weight[ $right['severity'] ] ?? 2 );
			}
		);

		return $queue;
	}

	/**
	 * Format one environment check.
	 *
	 * @return array<string,string>
	 */
	private static function check(
		string $key,
		string $label,
		string $status,
		string $value,
		string $description
	): array {
		return compact(
			'key',
			'label',
			'status',
			'value',
			'description'
		);
	}
}
