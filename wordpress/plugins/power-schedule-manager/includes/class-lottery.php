<?php
/**
 * Verified lottery-result synchronization and public shortcodes.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps lottery API access server-side and serves stored results on frontend.
 */
final class Power_Schedule_Manager_Lottery {

	public const string MENU_SLUG =
		'power-schedule-manager-lottery';

	private const string SETTINGS_OPTION =
		'power_schedule_manager_lottery_settings';

	private const string SAVE_ACTION =
		'psm_save_lottery_settings';

	private const string REFRESH_ACTION =
		'psm_refresh_lottery';

	private const string REFRESH_HOOK =
		'power_schedule_manager_refresh_lottery';

	private const string REFRESH_SCHEDULE =
		'power_schedule_manager_every_fifteen_minutes';

	private const string METADATA_VERSION_OPTION =
		'power_schedule_manager_lottery_metadata_version';

	private const string METADATA_CURSOR_OPTION =
		'power_schedule_manager_lottery_metadata_cursor';

	private const string METADATA_VERSION = '3';

	private const int METADATA_BATCH_SIZE = 200;

	private const array API_ENDPOINTS = array(
		'latest' =>
			'https://xosoapi.online/api/latest-draws-vn',
		'traditional' =>
			'https://xosoapi.online/api/v1/vietnam/draws?limit=30',
		'vietlott' =>
			'https://xosoapi.online/api/vietlott/draws?limit=300',
		'dientoan' =>
			'https://xosoapi.online/api/v1/dientoan/draws?limit=100',
	);

	private const string API_KEY_CONSTANT =
		'POWER_SCHEDULE_MANAGER_XOSO_API_KEY';

	private const string WEBHOOK_SECRET_CONSTANT =
		'POWER_SCHEDULE_MANAGER_XOSO_WEBHOOK_SECRET';

	private const int MAX_RESPONSE_BYTES = 2097152;

	private const int RETENTION_MONTHS = 24;

	/**
	 * Attach WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 74 );
		add_action(
			'admin_post_' . self::SAVE_ACTION,
			array( $this, 'save_settings' )
		);
		add_action(
			'admin_post_' . self::REFRESH_ACTION,
			array( $this, 'refresh_now' )
		);
		add_action(
			self::REFRESH_HOOK,
			array( $this, 'scheduled_refresh' )
		);
		add_action(
			'admin_init',
			array( $this, 'ensure_schedule' ),
			46
		);
		add_action(
			'admin_init',
			array( $this, 'backfill_legacy_metadata' ),
			48
		);
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_admin_assets' )
		);
		add_action(
			'power_schedule_manager_render_data_source_settings',
			array( $this, 'render_data_source_settings' ),
			10
		);
		add_action(
			'power_schedule_manager_daily_maintenance',
			array( $this, 'delete_expired_draws' ),
			30
		);
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_filter(
			'cron_schedules',
			array( $this, 'register_cron_schedule' )
		);
		add_filter(
			'the_content',
			array( $this, 'consolidate_overview_content' ),
			8
		);
		add_filter(
			'the_content',
			array( $this, 'append_lottery_disclaimer' ),
			99
		);

		add_shortcode(
			'power_schedule_lottery',
			array( $this, 'render_overview_shortcode' )
		);
		add_shortcode(
			'power_schedule_lottery_latest',
			array( $this, 'render_shortcode' )
		);
		add_shortcode(
			'power_schedule_lottery_results',
			array( $this, 'render_overview_shortcode' )
		);
		add_shortcode(
			'power_schedule_lottery_overview',
			array( $this, 'render_overview_shortcode' )
		);
		add_shortcode(
			'power_schedule_lottery_history',
			array( $this, 'render_history_shortcode' )
		);
		add_shortcode(
			'power_schedule_lottery_archive',
			array( $this, 'render_archive_shortcode' )
		);
		add_shortcode(
			'power_schedule_lottery_special_week',
			array( $this, 'render_special_week_shortcode' )
		);

		foreach (
			array(
				'power_schedule_lottery_north' => 'north',
				'power_schedule_lottery_central' => 'central',
				'power_schedule_lottery_south' => 'south',
				'power_schedule_lottery_mega645' => 'mega645',
				'power_schedule_lottery_power655' => 'power655',
				'power_schedule_lottery_max3d' => 'max3d',
				'power_schedule_lottery_max3d_plus' => 'max3dplus',
				'power_schedule_lottery_max3d_pro' => 'max3dpro',
				'power_schedule_lottery_keno' => 'keno',
				'power_schedule_lottery_keno_history' => 'keno_history',
				'power_schedule_lottery_dientoan' => 'dientoan',
				'power_schedule_lottery_dientoan123' => 'dientoan123',
				'power_schedule_lottery_dientoan6x36' => 'dientoan6x36',
				'power_schedule_lottery_thantai' => 'thantai',
			)
			as $shortcode => $preset
		) {
			add_shortcode(
				$shortcode,
				fn ( array|string $attributes = array() ): string =>
					$this->render_preset_shortcode( $preset, $attributes )
				);
		}

		foreach (
			array(
				'power_schedule_lottery_mega645_history' => array(
					'mega645',
					'Các kỳ Mega 6/45 trước',
				),
				'power_schedule_lottery_power655_history' => array(
					'power655',
					'Các kỳ Power 6/55 trước',
				),
				'power_schedule_lottery_max3d_history' => array(
					'max3d',
					'Các kỳ Max 3D trước',
				),
				'power_schedule_lottery_max3d_plus_history' => array(
					'max3dplus',
					'Các kỳ Max 3D+ trước',
				),
				'power_schedule_lottery_max3d_pro_history' => array(
					'max3dpro',
					'Các kỳ Max 3D Pro trước',
				),
				'power_schedule_lottery_dientoan123_history' => array(
					'dientoan123',
					'Các kỳ Điện toán 123 trước',
				),
				'power_schedule_lottery_dientoan6x36_history' => array(
					'dientoan6x36',
					'Các kỳ Điện toán 6x36 trước',
				),
				'power_schedule_lottery_thantai_history' => array(
					'thantai',
					'Các kỳ Thần Tài 4 trước',
				),
			)
			as $shortcode => $history
		) {
			add_shortcode(
				$shortcode,
				fn ( array|string $attributes = array() ): string =>
					$this->render_history_preset_shortcode(
						$history[0],
						$history[1],
						$attributes
					)
			);
		}
	}

	/**
	 * When the page uses the total shortcode, remove legacy individual lottery
	 * shortcodes from that same content so current and previous draws are not
	 * rendered twice.
	 */
	public function consolidate_overview_content( string $content ): string {
		if (
			is_admin()
			|| (
				! has_shortcode( $content, 'power_schedule_lottery_results' )
				&& ! has_shortcode(
					$content,
					'power_schedule_lottery_overview'
				)
			)
		) {
			return $content;
		}
		$legacy_tags = array(
			'power_schedule_lottery_north',
			'power_schedule_lottery_central',
			'power_schedule_lottery_south',
			'power_schedule_lottery_mega645',
			'power_schedule_lottery_power655',
			'power_schedule_lottery_max3d',
			'power_schedule_lottery_max3d_plus',
			'power_schedule_lottery_max3d_pro',
			'power_schedule_lottery_keno',
			'power_schedule_lottery_keno_history',
			'power_schedule_lottery_mega645_history',
			'power_schedule_lottery_power655_history',
			'power_schedule_lottery_max3d_history',
			'power_schedule_lottery_max3d_plus_history',
			'power_schedule_lottery_max3d_pro_history',
			'power_schedule_lottery_dientoan',
			'power_schedule_lottery_dientoan123',
			'power_schedule_lottery_dientoan6x36',
			'power_schedule_lottery_thantai',
			'power_schedule_lottery_dientoan123_history',
			'power_schedule_lottery_dientoan6x36_history',
			'power_schedule_lottery_thantai_history',
			'power_schedule_lottery_history',
		);
		$pattern = get_shortcode_regex( $legacy_tags );
		$cleaned = preg_replace( '/' . $pattern . '/s', '', $content );

		return is_string( $cleaned ) ? $cleaned : $content;
	}

	/**
	 * Append one lottery-specific legal notice after every rendered result.
	 *
	 * The filter runs after shortcode expansion so a page containing several
	 * product shortcodes still receives exactly one notice at the true end of
	 * the page instead of one notice per result card.
	 */
	public function append_lottery_disclaimer( string $content ): string {
		if (
			is_admin()
			|| ! is_singular()
			|| ! in_the_loop()
			|| ! is_main_query()
			|| str_contains( $content, 'psm-lottery-disclaimer' )
			|| (
				! str_contains( $content, 'psm-lottery' )
				&& ! str_contains( $content, 'psm-page-hero--lottery' )
			)
		) {
			return $content;
		}

		return $content . self::render_lottery_disclaimer();
	}

	/**
	 * Render the public lottery disclaimer used by every lottery page.
	 */
	private static function render_lottery_disclaimer(): string {
		return '<aside class="psm-lottery-disclaimer" role="note" aria-labelledby="psm-lottery-disclaimer-title">'
			. '<h2 id="psm-lottery-disclaimer-title"><span aria-hidden="true">⚠️</span> '
			. esc_html__( 'Tuyên bố miễn trừ trách nhiệm', 'power-schedule-manager' )
			. '</h2><p>'
			. esc_html__( 'Kết quả xổ số trên website được tổng hợp từ các nguồn dữ liệu công khai nhằm phục vụ tra cứu. Khi cần xác nhận trúng thưởng, người dùng phải đối chiếu với kết quả do đơn vị phát hành công bố chính thức.', 'power-schedule-manager' )
			. '</p><p>'
			. esc_html__( 'Nội dung không phải dự đoán, tư vấn tài chính hoặc khuyến nghị tham gia đặt cược. Website không chịu trách nhiệm cho quyết định được đưa ra chỉ dựa trên dữ liệu hiển thị tại đây.', 'power-schedule-manager' )
			. '</p></aside>';
	}

	/**
	 * Normalize legacy rows in small, resumable batches.
	 *
	 * Older provider payloads were stored before stable game metadata existed.
	 * A bounded admin-side migration prevents a large lottery table from
	 * blocking normal frontend or cron requests.
	 */
	public function backfill_legacy_metadata(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
			|| self::METADATA_VERSION
				=== (string) get_option(
					self::METADATA_VERSION_OPTION,
					''
				)
		) {
			return;
		}

		$lock_key = 'psm_lottery_metadata_backfill_lock';
		if ( ! wp_cache_add( $lock_key, 1, 'psm_migration', 30 ) ) {
			return;
		}

		try {
			global $wpdb;

			$table = Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			);
			if (
				! Power_Schedule_Manager_Database::table_exists(
					Power_Schedule_Manager_Database::LOTTERY_DRAWS
				)
			) {
				return;
			}

			$cursor = max(
				0,
				(int) get_option( self::METADATA_CURSOR_OPTION, 0 )
			);
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id,provider_draw_id,region,province_code,
						province_name,game_type,draw_date,draw_time,
						special_prize,results_json,source_payload_json
					FROM {$table}
					WHERE id > %d
					ORDER BY id ASC
					LIMIT %d",
					$cursor,
					self::METADATA_BATCH_SIZE
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || array() === $rows ) {
				delete_option( self::METADATA_CURSOR_OPTION );
				update_option(
					self::METADATA_VERSION_OPTION,
					self::METADATA_VERSION,
					false
				);

				return;
			}

			$fields = array(
				'provider_draw_id',
				'region',
				'province_code',
				'province_name',
				'game_type',
				'draw_date',
				'draw_time',
				'special_prize',
				'results_json',
			);
			$last_id = $cursor;
			$expanded_draws = array();

			foreach ( $rows as $stored_row ) {
				$last_id = max( $last_id, (int) $stored_row['id'] );
				$source_payload = json_decode(
					(string) ( $stored_row['source_payload_json'] ?? '' ),
					true,
					64
				);
				if ( is_array( $source_payload ) ) {
					foreach (
						self::normalize_payload( $source_payload )
						as $expanded_draw
					) {
						$expanded_draws[
							bin2hex( (string) $expanded_draw['draw_key'] )
						] = $expanded_draw;
					}
				}
				$normalized_row = self::hydrate_row_metadata( $stored_row );
				$changes = array();

				foreach ( $fields as $field ) {
					$before = (string) ( $stored_row[ $field ] ?? '' );
					$after = (string) ( $normalized_row[ $field ] ?? '' );
					if ( $before !== $after ) {
						$changes[ $field ] = $after;
					}
				}

				if ( array() !== $changes ) {
					$wpdb->update(
						$table,
						$changes,
						array( 'id' => (int) $stored_row['id'] ),
						array_fill( 0, count( $changes ), '%s' ),
						array( '%d' )
					);
				}
			}
			if ( array() !== $expanded_draws ) {
				$expanded_result = $this->store_draws(
					array_values( $expanded_draws ),
					'metadata-backfill'
				);
				if ( is_wp_error( $expanded_result ) ) {
					Power_Schedule_Manager_Logger::error(
						'lottery_metadata_expand_failed',
						$expanded_result->get_error_message()
					);
				}
			}

			update_option(
				self::METADATA_CURSOR_OPTION,
				$last_id,
				false
			);

			if ( count( $rows ) < self::METADATA_BATCH_SIZE ) {
				delete_option( self::METADATA_CURSOR_OPTION );
				update_option(
					self::METADATA_VERSION_OPTION,
					self::METADATA_VERSION,
					false
				);
				Power_Schedule_Manager_Cache::invalidate_all();
			}
		} finally {
			wp_cache_delete( $lock_key, 'psm_migration' );
		}
	}

	/**
	 * Add a schedule that stays comfortably below the free API allowance.
	 *
	 * @param array<string,array<string,int|string>> $schedules Schedules.
	 * @return array<string,array<string,int|string>>
	 */
	public function register_cron_schedule( array $schedules ): array {
		$schedules[ self::REFRESH_SCHEDULE ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __(
				'Mỗi 15 phút',
				'power-schedule-manager'
			),
		);

		return $schedules;
	}

	/**
	 * Register the management screen.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Power_Schedule_Manager_Admin::MENU_SLUG,
			__( 'Kết quả xổ số', 'power-schedule-manager' ),
			__( 'Kết quả xổ số', 'power-schedule-manager' ),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::MENU_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Load the shared admin design only on this page.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( false === str_contains( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		$path = POWER_SCHEDULE_MANAGER_PATH . 'admin/assets/admin.css';
		wp_enqueue_style(
			'power-schedule-manager-admin',
			POWER_SCHEDULE_MANAGER_URL . 'admin/assets/admin.css',
			array(),
			is_file( $path )
				? (string) filemtime( $path )
				: POWER_SCHEDULE_MANAGER_VERSION
		);

		$script_path = POWER_SCHEDULE_MANAGER_PATH . 'admin/assets/admin.js';
		wp_enqueue_script(
			'power-schedule-manager-admin',
			POWER_SCHEDULE_MANAGER_URL . 'admin/assets/admin.js',
			array(),
			is_file( $script_path )
				? (string) filemtime( $script_path )
				: POWER_SCHEDULE_MANAGER_VERSION,
			true
		);
	}

	/**
	 * Render the admin screen.
	 */
	public function render_admin_page(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die(
				esc_html__(
					'Bạn không có quyền truy cập trang này.',
					'power-schedule-manager'
				)
			);
		}

		$this->ensure_webhook_secret();
		$settings = self::settings();
		$admin_section = isset( $_GET['section'] )
			? sanitize_key( wp_unslash( (string) $_GET['section'] ) )
			: 'results';
		$admin_section = in_array(
			$admin_section,
			array( 'results', 'settings', 'more' ),
			true
		) ? $admin_section : 'results';
		if ( 'settings' === $admin_section ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'         =>
							Power_Schedule_Manager_Admin::DATA_SOURCES_SLUG,
						'settings_tab' => 'lottery',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		$all_rows = self::admin_overview_rows();
		$overview = self::admin_overview( $all_rows, $settings );
		$admin_page = isset( $_GET['data_page'] )
			? max( 1, absint( wp_unslash( (string) $_GET['data_page'] ) ) )
			: 1;
		$admin_per_page = 10;
		$admin_pages = max(
			1,
			(int) ceil( count( $all_rows ) / $admin_per_page )
		);
		$admin_page = min( $admin_page, $admin_pages );
		$rows = array_slice(
			$all_rows,
			( $admin_page - 1 ) * $admin_per_page,
			$admin_per_page
		);
		$webhook_secret_display = '';
		if (
			'admin' === $settings['webhook_secret_source']
			&& '' !== (string) $settings['webhook_secret_encrypted']
		) {
			$webhook_secret_display = Power_Schedule_Manager_Secrets::decrypt(
				(string) $settings['webhook_secret_encrypted']
			);
		}
		$notice = isset( $_GET['psm_notice'] ) && is_scalar( $_GET['psm_notice'] )
			? sanitize_key( wp_unslash( (string) $_GET['psm_notice'] ) )
			: '';

		require POWER_SCHEDULE_MANAGER_PATH . 'admin/views/lottery.php';
	}

	/**
	 * Render the lottery connection inside the central settings screen.
	 */
	public function render_data_source_settings(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			return;
		}

		$settings = self::settings();
		require POWER_SCHEDULE_MANAGER_PATH
			. 'admin/views/data-source-lottery.php';
	}

	/**
	 * Save encrypted credentials and synchronization preferences.
	 */
	public function save_settings(): void {
		$this->authorize( self::SAVE_ACTION );
		$settings = self::settings();

		$api_key = Power_Schedule_Manager_Secrets::update(
			$_POST['api_key'] ?? '',
			(string) $settings['api_key_encrypted'],
			isset( $_POST['clear_api_key'] )
		);
		$webhook_secret = isset( $_POST['rotate_webhook_secret'] )
			? Power_Schedule_Manager_Secrets::encrypt(
				self::generate_webhook_secret()
			)
			: (string) $settings['webhook_secret_encrypted'];

		if ( is_wp_error( $api_key ) || is_wp_error( $webhook_secret ) ) {
			$this->redirect( 'invalid' );
		}

		$settings['enabled'] = isset( $_POST['enabled'] );
		$settings['api_key_encrypted'] = $api_key;
		$settings['webhook_secret_encrypted'] = $webhook_secret;
		$settings['default_region'] = self::sanitize_region(
			$_POST['default_region'] ?? ''
		);

		self::persist_settings( $settings );
		$this->ensure_schedule();
		if (
			isset( $_POST['settings_location'] )
			&& 'data-sources' === sanitize_key(
				wp_unslash( (string) $_POST['settings_location'] )
			)
		) {
			$this->redirect_data_sources( 'settings_saved', 'lottery' );
		}
		$this->redirect( 'settings_saved' );
	}

	/**
	 * Refresh on an explicit administrator request.
	 */
	public function refresh_now(): void {
		$this->authorize( self::REFRESH_ACTION );
		$result = $this->refresh();

		$this->redirect(
			is_wp_error( $result ) ? 'api_error' : 'refreshed'
		);
	}

	/**
	 * Refresh from WP-Cron without surfacing exceptions.
	 */
	public function scheduled_refresh(): void {
		$result = $this->refresh();

		if ( is_wp_error( $result ) ) {
			error_log(
				'Cúp Điện Lâm Đồng lottery refresh: '
				. sanitize_text_field( $result->get_error_code() )
			);
		}
	}

	/**
	 * Keep the default schedule within the free API allowance.
	 */
	public function ensure_schedule(): void {
		$settings = self::settings();
		$scheduled = wp_next_scheduled( self::REFRESH_HOOK );

		if ( empty( $settings['enabled'] ) ) {
			if ( false !== $scheduled ) {
				wp_clear_scheduled_hook( self::REFRESH_HOOK );
			}
			return;
		}

		if (
			false !== $scheduled
			&& self::REFRESH_SCHEDULE !== wp_get_schedule(
				self::REFRESH_HOOK
			)
		) {
			wp_clear_scheduled_hook( self::REFRESH_HOOK );
			$scheduled = false;
		}

		if ( false === $scheduled ) {
			wp_schedule_event(
				time() + 2 * MINUTE_IN_SECONDS,
				self::REFRESH_SCHEDULE,
				self::REFRESH_HOOK
			);
		}
	}

	/**
	 * Fetch and store recent draws.
	 *
	 * @return int|WP_Error Number of normalized draws.
	 */
	public function refresh(): int|WP_Error {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return new WP_Error( 'lottery_table_missing' );
		}

		$settings = self::settings();
		$api_key = Power_Schedule_Manager_Secrets::resolve(
			self::API_KEY_CONSTANT,
			(string) $settings['api_key_encrypted']
		);

		if ( '' === $api_key ) {
			return new WP_Error( 'lottery_api_key_missing' );
		}

		$stored = 0;
		$successful_endpoints = 0;
		$last_error = 'lottery_no_draws';
		$draw_buffer = array();
		$endpoint_report = array();

		foreach ( self::API_ENDPOINTS as $endpoint_type => $endpoint_url ) {
			$endpoint_report[ $endpoint_type ] = array(
				'ok'    => false,
				'count' => 0,
				'code'  => 'not_requested',
			);
			$response = wp_safe_remote_get(
				$endpoint_url,
				array(
					'timeout'             => 12,
					'redirection'         => 1,
					'limit_response_size' => self::MAX_RESPONSE_BYTES,
					'headers'             => array(
						'Accept'    => 'application/json',
						'X-API-Key' => $api_key,
					),
					'user-agent'          => 'Power-Schedule-Manager/'
						. POWER_SCHEDULE_MANAGER_VERSION,
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = 'lottery_http_failed';
				$endpoint_report[ $endpoint_type ]['code'] = $last_error;
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status ) {
				$last_error = 429 === $status
					? 'lottery_rate_limited'
					: 'lottery_http_status';
				$endpoint_report[ $endpoint_type ]['code'] = $last_error;
				continue;
			}

			$payload = json_decode(
				wp_remote_retrieve_body( $response ),
				true,
				64
			);
			if ( ! is_array( $payload ) ) {
				$last_error = 'lottery_invalid_json';
				$endpoint_report[ $endpoint_type ]['code'] = $last_error;
				continue;
			}
			++$successful_endpoints;

			$draws = self::normalize_payload( $payload );
			$endpoint_report[ $endpoint_type ] = array(
				'ok'    => true,
				'count' => count( $draws ),
				'code'  => array() === $draws ? 'no_draws' : 'ok',
			);
			foreach ( $draws as $draw ) {
				$key = bin2hex( (string) $draw['draw_key'] );
				$draw['_source_url'] = $endpoint_url;
				if (
					! isset( $draw_buffer[ $key ] )
					|| self::result_completeness( $draw )
						> self::result_completeness(
							$draw_buffer[ $key ]
						)
				) {
					$draw_buffer[ $key ] = $draw;
				}
			}
		}
		$settings['last_endpoint_report'] = $endpoint_report;
		self::persist_settings( $settings );

		if ( 0 === $successful_endpoints || array() === $draw_buffer ) {
			return $this->record_error( $last_error );
		}

		$result = $this->store_draws(
			array_values( $draw_buffer ),
			'https://xosoapi.online/docs'
		);
		if ( is_wp_error( $result ) ) {
			return $this->record_error( $result->get_error_code() );
		}
		$stored = $result;

		$settings['last_success_at_utc'] =
			Power_Schedule_Manager_Database::utc_now();
		$settings['last_error_code'] = '';
		$settings['last_count'] = $stored;
		self::persist_settings( $settings );
		Power_Schedule_Manager_Cache::invalidate_all();

		return $stored;
	}

	/**
	 * Prefer a complete result over a placeholder returned by another endpoint.
	 *
	 * @param array<string,mixed> $draw Normalized draw.
	 */
	private static function result_completeness( array $draw ): int {
		$results = json_decode(
			(string) ( $draw['results_json'] ?? '' ),
			true,
			64
		);
		if ( ! is_array( $results ) ) {
			return 0;
		}
		$target = isset( $results['winning_numbers'] )
			&& is_array( $results['winning_numbers'] )
				? $results['winning_numbers']
				: (
					isset( $results['results'] )
					&& is_array( $results['results'] )
						? $results['results']
						: $results
				);
		$values = 0;
		if ( array_is_list( $target ) ) {
			foreach ( $target as $item ) {
				if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
					++$values;
					continue;
				}
				if ( ! is_array( $item ) ) {
					continue;
				}
				$value = $item['value']
					?? $item['number']
					?? $item['result']
					?? null;
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					++$values;
					continue;
				}
				$item_values = $item['values'] ?? null;
				if ( is_array( $item_values ) ) {
					foreach ( $item_values as $item_value ) {
						if (
							is_scalar( $item_value )
							&& '' !== trim( (string) $item_value )
						) {
							++$values;
						}
					}
				}
			}

			return $values;
		}
		foreach ( $target as $key => $value ) {
			$result_key = preg_replace(
				'/[^a-z0-9]+/',
				'',
				strtolower( remove_accents( (string) $key ) )
			) ?: '';
			if (
				0 === preg_match(
					'/^(db|g[1-8]|bo[1-3]|set[1-3]|first|second|third|prize(?:[1-8]|special)|giai[a-z0-9]*|special|specialprize|numbers|winningnumbers|result|ketqua|jackpot)$/',
					$result_key
				)
			) {
				continue;
			}
			if ( is_array( $value ) ) {
				array_walk_recursive(
					$value,
					static function ( mixed $nested ) use ( &$values ): void {
						if (
							is_scalar( $nested )
							&& '' !== trim( (string) $nested )
						) {
							++$values;
						}
					}
				);
			} elseif ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				++$values;
			}
		}

		return $values;
	}

	/**
	 * Register the signed provider webhook.
	 */
	public function register_rest_route(): void {
		register_rest_route(
			'power-schedule-manager/v1',
			'/lottery-webhook',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'webhook_status' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'receive_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Return a safe health response without exposing any credential.
	 */
	public function webhook_status(): WP_REST_Response {
		$settings = self::settings();

		return new WP_REST_Response(
			array(
				'ok'               => true,
				'service'          => 'Cúp Điện Lâm Đồng lottery webhook',
				'accepts'          => 'POST',
				'signature_header' => 'X-Webhook-Signature',
				'configured'       =>
					'missing' !== $settings['webhook_secret_source'],
			),
			200
		);
	}

	/**
	 * Verify the raw body before accepting a DRAW_COMPLETED webhook.
	 */
	public function receive_webhook(
		WP_REST_Request $request
	): WP_REST_Response|WP_Error {
		$settings = self::settings();
		$secret = Power_Schedule_Manager_Secrets::resolve(
			self::WEBHOOK_SECRET_CONSTANT,
			(string) $settings['webhook_secret_encrypted']
		);
		$signature = trim(
			(string) $request->get_header( 'x-webhook-signature' )
		);
		$raw_body = $request->get_body();

		if ( strlen( $raw_body ) > self::MAX_RESPONSE_BYTES ) {
			return new WP_Error(
				'lottery_payload_too_large',
				__( 'Payload webhook vượt quá giới hạn.', 'power-schedule-manager' ),
				array( 'status' => 413 )
			);
		}
		$signature = strtolower(
			(string) preg_replace( '/^sha256=/', '', $signature )
		);
		if (
			'' === $secret
			|| '' === $signature
			|| 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $signature )
			|| ! hash_equals(
				hash_hmac( 'sha256', $raw_body, $secret ),
				$signature
			)
		) {
			return new WP_Error(
				'lottery_invalid_signature',
				__( 'Chữ ký webhook không hợp lệ.', 'power-schedule-manager' ),
				array( 'status' => 401 )
			);
		}

		$payload = json_decode( $raw_body, true, 64 );
		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'lottery_invalid_payload',
				__( 'Payload webhook không hợp lệ.', 'power-schedule-manager' ),
				array( 'status' => 400 )
			);
		}

		$draws = self::normalize_payload( $payload );
		if ( array() === $draws ) {
			return new WP_Error(
				'lottery_no_draws',
				__( 'Webhook không chứa kỳ quay hợp lệ.', 'power-schedule-manager' ),
				array( 'status' => 422 )
			);
		}

		$stored = $this->store_draws( $draws, 'xosoapi-webhook' );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		Power_Schedule_Manager_Cache::invalidate_all();

		return new WP_REST_Response(
			array(
				'ok'     => true,
				'stored' => $stored,
			),
			200
		);
	}

	/**
	 * Convert provider payloads into a stable internal representation.
	 *
	 * The provider has multiple endpoints with slightly different envelopes,
	 * so this method recognizes documented field aliases without trusting
	 * arbitrary HTML from the response.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_payload( array $payload ): array {
		$candidates = array();
		self::collect_draw_candidates( $payload, $candidates, array(), 0 );
		$draws = array();

		foreach ( $candidates as $candidate ) {
			$row = self::normalize_draw( $candidate );
			if ( null === $row ) {
				continue;
			}
			$draws[ bin2hex( $row['draw_key'] ) ] = $row;
		}

		return array_values( $draws );
	}

	/**
	 * Walk only bounded JSON structures and collect draw-like objects.
	 *
	 * @param array<string|int,mixed> $node Current node.
	 * @param array<int,array<string,mixed>> $output Candidate rows.
	 * @param array<string,string> $context Parent labels.
	 */
	private static function collect_draw_candidates(
		array $node,
		array &$output,
		array $context,
		int $depth
	): void {
		if ( $depth > 8 || count( $output ) >= 200 ) {
			return;
		}

		if ( ! array_is_list( $node ) ) {
			foreach (
				array(
					'region',
					'mien',
					'province',
					'province_name',
					'station',
					'date',
					'draw_date',
					'drawDate',
					'game_type',
					'lottery_type',
					'product',
					'product_name',
					'productName',
					'product_code',
					'game',
					'type',
					'name',
				)
				as $key
			) {
				if ( isset( $node[ $key ] ) && is_scalar( $node[ $key ] ) ) {
					$context[ $key ] = (string) $node[ $key ];
				}
			}

			$grouped_products = self::grouped_product_candidates(
				$node,
				$context
			);
			foreach ( $grouped_products as $product_candidate ) {
				$output[] = $product_candidate;
			}

			$has_result = false;
			foreach (
				array(
					'results',
					'result',
					'prizes',
					'ket_qua',
					'giai_dac_biet',
					'special_prize',
					'db',
					'g1',
					'numbers',
					'winning_numbers',
					'winningNumbers',
					'winning_results',
					'winningResults',
				)
				as $key
			) {
				if ( array_key_exists( $key, $node ) ) {
					$has_result = true;
					break;
				}
			}
			if ( $has_result && array() === $grouped_products ) {
				$output[] = array_merge( $context, $node );
			}
		}

		foreach ( $node as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			$child_context = $context;
			if (
				is_string( $key )
				&& ! ctype_digit( $key )
				&& ! in_array(
					$key,
					array( 'data', 'draws', 'results', 'items' ),
					true
				)
			) {
				$child_context['container_label'] = $key;
			}
			self::collect_draw_candidates(
				$value,
				$output,
				$child_context,
				$depth + 1
			);
		}
	}

	/**
	 * Split provider envelopes that place multiple products in one result map.
	 *
	 * @param array<string|int,mixed> $node Current provider object.
	 * @param array<string,string>     $context Inherited draw metadata.
	 * @return array<int,array<string,mixed>>
	 */
	private static function grouped_product_candidates(
		array $node,
		array $context
	): array {
		$base = $context;
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && is_scalar( $value ) ) {
				$base[ $key ] = (string) $value;
			}
		}

		$candidates = array();
		foreach ( $node as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$game_type = self::container_game_type( $key );
			if ( '' === $game_type ) {
				continue;
			}
			$candidate = $base;
			$candidate['game_type'] = $game_type;
			$candidate['container_label'] = $key;
			if ( is_array( $value ) && ! array_is_list( $value ) ) {
				$candidate = array_merge( $candidate, $value );
				if (
					! array_key_exists( 'results', $candidate )
					&& ! array_key_exists( 'result', $candidate )
				) {
					$candidate['results'] = $value;
				}
			} else {
				$candidate['results'] = is_array( $value )
					? $value
					: array( $value );
			}
			$candidates[] = $candidate;
		}

		return $candidates;
	}

	/**
	 * Recognize only a product container key, not arbitrary result text.
	 */
	private static function container_game_type( string $key ): string {
		$compact = str_replace(
			' ',
			'',
			self::normalize_search_text( $key )
		);

		return match ( true ) {
			1 === preg_match(
				'/max\\s*[-_]?\\s*3d\\s*(?:\\+|plus|cong)/i',
				$key
			),
			str_contains( $compact, 'max3dplus' ) => 'max3dplus',
			str_contains( $compact, 'dientoan123' ),
			str_contains( $compact, 'dt123' ) => 'dientoan123',
			str_contains( $compact, 'dientoan6x36' ),
			str_contains( $compact, 'dt6x36' ) => 'dientoan6x36',
			str_contains( $compact, 'thantai4' ),
			str_contains( $compact, 'thantai' ),
			'tt4' === $compact => 'thantai',
			default => '',
		};
	}

	/**
	 * Normalize one draw-like object.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function normalize_draw( array $draw ): ?array {
		$results = self::first_value(
			$draw,
			array(
				'results',
				'result',
				'prizes',
				'ket_qua',
				'numbers',
				'winning_numbers',
				'winningNumbers',
				'winning_results',
				'winningResults',
			),
			array()
		);
		if ( array() === $results ) {
			$results = array_filter(
				$draw,
				static fn ( string|int $key ): bool =>
					is_string( $key )
					&& (
						str_starts_with( $key, 'giai' )
						|| 1 === preg_match(
							'/\Aprize[_-]?(?:[1-8]|special)\z/i',
							$key
						)
						|| 1 === preg_match( '/\A(?:db|g[1-8])\z/i', $key )
					),
				ARRAY_FILTER_USE_KEY
			);
		}
		if ( is_scalar( $results ) ) {
			$results = self::normalize_number_values( $results );
		}
		/*
		 * A traditional draw is published before its first prize is drawn.
		 * Keep that empty placeholder so the public shortcode can show an
		 * updating board instead of falling back to an older date.
		 */
		if ( ! is_array( $results ) ) {
			return null;
		}

		$date = self::normalize_date(
			self::first_scalar(
				$draw,
				array(
					'draw_date',
					'drawDate',
					'date',
					'ngay',
					'ngay_quay',
				)
			)
		);
		if ( '' === $date ) {
			return null;
		}
		$province = self::first_value(
			$draw,
			array( 'province', 'station', 'tinh' ),
			array()
		);
		$province_name = self::clean_text(
			is_array( $province )
				? self::first_scalar(
					$province,
					array( 'name', 'province_name', 'station_name', 'label' )
				)
				: self::first_scalar(
					$draw,
					array(
						'province_name',
						'province',
						'station_name',
						'station',
						'tinh',
						'container_label',
					)
				),
			191
		);
		$province_code = sanitize_key(
			is_array( $province )
				? self::first_scalar(
					$province,
					array( 'code', 'province_code', 'station_code', 'slug' )
				)
				: self::first_scalar(
					$draw,
					array(
						'province_code',
						'station_code',
						'slug',
						'code',
					)
				)
		);
		$region = self::sanitize_region(
			self::first_scalar( $draw, array( 'region', 'mien' ) )
		);
		if (
			'' === $region
			&& is_array( $province )
			&& isset( $province['region'] )
			&& is_array( $province['region'] )
		) {
			$region = self::sanitize_region(
				self::first_scalar(
					$province['region'],
					array( 'code', 'name' )
				)
			);
		}
		if ( '' === $region ) {
			$region = self::sanitize_region(
				self::first_scalar(
					$draw,
					array( 'container_label' )
				)
			);
		}
		if ( '' === $region ) {
			$region = self::infer_region( $province_name );
		}
		$explicit_game_type = self::first_scalar(
			$draw,
			array(
				'game_type',
				'lottery_type',
				'type',
				'product',
				'product_name',
				'productName',
				'product_code',
				'game',
			)
		);
		$game_evidence = implode(
			' ',
			array_filter(
				array(
					$explicit_game_type,
					self::first_scalar(
						$draw,
						array(
							'name',
							'title',
							'label',
							'product_name',
							'productName',
							'container_label',
							'slug',
						)
					),
					$province_code,
					$province_name,
					is_array( $province )
						? self::collect_game_type_evidence( $province )
						: '',
				),
				static fn ( string $part ): bool => '' !== trim( $part )
			)
		);
		$detected_game_type = self::detect_game_type_from_evidence(
			$game_evidence
		);
		$game_type = '' !== $detected_game_type
			? $detected_game_type
			: self::normalize_game_type( $explicit_game_type );
		$provider_draw_id = self::clean_text(
			self::first_scalar(
				$draw,
				array(
					'drawCode',
					'draw_code',
					'draw_number',
					'drawNumber',
					'draw_no',
					'drawNo',
					'draw_id',
					'_id',
					'id',
				)
			),
			191
		);
		$identity_draw_id = self::clean_text(
			self::first_scalar(
				$draw,
				array(
					'draw_id',
					'_id',
					'id',
					'drawCode',
					'draw_code',
					'draw_number',
					'drawNumber',
					'draw_no',
					'drawNo',
				)
			),
			191
		);
		$results = self::normalize_results_payload(
			$results,
			isset( $draw['prizes'] ) && is_array( $draw['prizes'] )
				? $draw['prizes']
				: array(),
			$game_type,
			$draw
		);
		$results_json = wp_json_encode(
			$results,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		if ( ! is_string( $results_json ) ) {
			return null;
		}

		$identity = implode(
			'|',
			array(
				$identity_draw_id,
				$region,
				$province_code ?: sanitize_title( $province_name ),
				$game_type,
				$date,
			)
		);
		$special = self::extract_special_prize( $draw, $results );

		return array(
			'provider_draw_id' => $provider_draw_id,
			'draw_key'         => hash( 'sha256', $identity, true ),
			'region'           => $region,
			'province_code'    => $province_code,
			'province_name'    => $province_name,
			'game_type'        => $game_type,
			'draw_date'        => $date,
			'draw_time'        => self::clean_text(
				self::first_scalar(
					$draw,
					array( 'draw_time', 'drawTime', 'time', 'gio_quay' )
				),
				16
			),
			'status'           => sanitize_key(
				self::first_scalar( $draw, array( 'status' ) )
			) ?: 'completed',
			'special_prize'    => $special,
			'results_json'     => $results_json,
			'source_payload_json' => wp_json_encode(
				$draw,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			),
			'observed_at_utc'  => Power_Schedule_Manager_Database::utc_now(),
			'data_hash'        => hash( 'sha256', $results_json, true ),
		);
	}

	/**
	 * Preserve the complete provider payload needed by product shortcodes.
	 *
	 * @param array<int|string,mixed> $results Provider result rows.
	 * @param array<int|string,mixed> $prizes Provider prize metadata.
	 * @param array<string,mixed>     $draw Full provider draw.
	 * @return array<int|string,mixed>
	 */
	private static function normalize_results_payload(
		array $results,
		array $prizes,
		string $game_type,
		array $draw
	): array {
		if ( 'traditional' === $game_type ) {
			return $results;
		}

		if (
			array() === $prizes
			&& array_is_list( $results )
			&& count(
				array_filter(
					$results,
					static fn ( mixed $item ): bool =>
						is_array( $item )
						&& (
							isset( $item['prizeCode'] )
							|| isset( $item['prize_code'] )
							|| isset( $item['prizeName'] )
							|| isset( $item['prize_name'] )
						)
				)
			) > 0
		) {
			$prizes = $results;
		}

		$payload = array(
			'results' => $results,
		);
		/*
		 * The detailed Vietlott endpoint keeps the prize structure in
		 * "results" and the actual draw balls in a sibling winningNumbers
		 * field. Preserve both instead of letting the prize rows hide the
		 * published result.
		 */
		$winning_numbers = self::first_value(
			$draw,
			array(
				'winning_numbers',
				'winningNumbers',
				'numbers',
				'winning_results',
				'winningResults',
			),
			array()
		);
		$winning_numbers = self::normalize_number_values( $winning_numbers );
		if ( array() !== $winning_numbers ) {
			$power_number = self::first_scalar(
				$draw,
				array(
					'power_number',
					'powerNumber',
					'special_number',
					'specialNumber',
					'bonus_number',
					'bonusNumber',
				)
			);
			if ( '' !== $power_number ) {
				$winning_numbers[] = $power_number;
			}
			$payload['winning_numbers'] = $winning_numbers;
		}
		if ( array() !== $prizes ) {
			$payload['prizes'] = $prizes;
		}
		$jackpot = self::first_scalar(
			$draw,
			array( 'jackpotVal', 'jackpot_value', 'jackpot' )
		);
		if ( '' !== $jackpot ) {
			$payload['jackpot_value'] = $jackpot;
		}

		return $payload;
	}

	/**
	 * Store normalized draws idempotently.
	 *
	 * @param array<int,array<string,mixed>> $draws Normalized draws.
	 *
	 * @return int|WP_Error
	 */
	private function store_draws(
		array $draws,
		string $source_url
	): int|WP_Error {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS
		);
		$now = Power_Schedule_Manager_Database::utc_now();
		$count = 0;
		$changed_draws = array();
		$upsert_alias = Power_Schedule_Manager_Database::upsert_row_alias();
		$upsert_columns = array(
			'provider_draw_id',
			'region',
			'province_code',
			'province_name',
			'game_type',
			'draw_date',
			'draw_time',
			'status',
			'special_prize',
			'results_json',
			'source_payload_json',
			'observed_at_utc',
			'fetched_at_utc',
			'data_hash',
			'updated_at_utc',
		);
		$upsert_assignments = array_map(
			static fn ( string $column ): string => sprintf(
				'`%1$s`=%2$s',
				$column,
				Power_Schedule_Manager_Database::upsert_value( $column )
			),
			$upsert_columns
		);
		$upsert_sql = implode( ",\n", $upsert_assignments );

		foreach ( $draws as $draw ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT results_json,source_payload_json,
						special_prize,status,data_hash
					FROM {$table} WHERE draw_key=%s LIMIT 1",
					(string) $draw['draw_key']
				),
				ARRAY_A
			);
			if (
				is_array( $existing )
				&& self::result_completeness( $existing )
					> self::result_completeness( $draw )
			) {
				foreach (
					array(
						'results_json',
						'source_payload_json',
						'special_prize',
						'status',
						'data_hash',
					)
					as $preserved_column
				) {
					$draw[ $preserved_column ] = (string) (
						$existing[ $preserved_column ]
							?? $draw[ $preserved_column ]
							?? ''
					);
				}
			}
			$changed = ! is_array( $existing )
				|| ! hash_equals(
					(string) ( $existing['data_hash'] ?? '' ),
					(string) $draw['data_hash']
				);
			$sql = $wpdb->prepare(
				"INSERT INTO {$table}
				(provider_draw_id,draw_key,region,province_code,
				province_name,game_type,draw_date,draw_time,status,
				special_prize,results_json,source_payload_json,
				provider_code,source_url,observed_at_utc,fetched_at_utc,
				data_hash,is_public,created_at_utc,updated_at_utc)
				VALUES
				(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,
				%s,%s,%s,1,%s,%s)
				{$upsert_alias}
				ON DUPLICATE KEY UPDATE
				{$upsert_sql}",
				(string) $draw['provider_draw_id'],
				(string) $draw['draw_key'],
				(string) $draw['region'],
				(string) $draw['province_code'],
				(string) $draw['province_name'],
				(string) $draw['game_type'],
				(string) $draw['draw_date'],
				(string) $draw['draw_time'],
				(string) $draw['status'],
				(string) $draw['special_prize'],
				(string) $draw['results_json'],
				(string) $draw['source_payload_json'],
				'xosoapi',
				esc_url_raw(
					(string) ( $draw['_source_url'] ?? $source_url )
				),
				(string) $draw['observed_at_utc'],
				$now,
				(string) $draw['data_hash'],
				$now,
				$now
			);
			$result = $wpdb->query( $sql );
			if ( false === $result ) {
				return new WP_Error( 'lottery_database_write_failed' );
			}
			if ( $changed ) {
				$changed_draws[] = array(
					'draw_key' => bin2hex( (string) $draw['draw_key'] ),
					'data_hash' => bin2hex( (string) $draw['data_hash'] ),
					'game_type' => sanitize_key( (string) $draw['game_type'] ),
					'region' => sanitize_key( (string) $draw['region'] ),
					'draw_date' => sanitize_text_field( (string) $draw['draw_date'] ),
					'province_name' => sanitize_text_field( (string) $draw['province_name'] ),
				);
			}
			++$count;
		}

		if ( array() !== $changed_draws ) {
			/**
			 * Fires only for newly stored or meaningfully changed draw data.
			 *
			 * @param array<int,array<string,string>> $changed_draws Changed draws.
			 */
			do_action( 'power_schedule_manager_lottery_draws_changed', $changed_draws );
		}

		return $count;
	}

	/**
	 * Render one structured page for all current results and one combined
	 * previous-draw table. Product shortcodes remain available independently.
	 */
	public function render_overview_shortcode(
		array|string $attributes = array()
	): string {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return '';
		}
		$attributes = shortcode_atts(
			array(
				'title'         => __( 'Kết quả xổ số hôm nay', 'power-schedule-manager' ),
				'description'   => __( 'Kết quả ba miền, Vietlott và Điện toán được sắp xếp theo từng nhóm sản phẩm.', 'power-schedule-manager' ),
				'mode'          => 'hub',
				'traditional'   => 'yes',
				'vietlott'      => 'yes',
				'dientoan'      => 'yes',
				'history'       => 'yes',
				'date_picker'   => 'yes',
				'history_limit' => '10',
				'traditional_url' => home_url( '/xo-so-ba-mien/' ),
				'mega645_url'   => home_url( '/xo-so-mega-645/' ),
				'power655_url'  => home_url( '/xo-so-power-655/' ),
				'max3d_url'     => home_url( '/xo-so-max-3d/' ),
				'keno_url'      => home_url( '/xo-so-keno/' ),
				'dientoan_url'  => home_url( '/xo-so-dien-toan/' ),
				'archive_url'   => home_url( '/tra-cuu-ket-qua-xo-so/' ),
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$enabled = static fn ( mixed $value ): bool =>
			'no' !== strtolower( trim( (string) $value ) );
		$base_id = wp_unique_id( 'psm-lottery-overview-' );
		$sections = array();
		$date_bounds = self::lottery_date_bounds();
		$selected_date = self::selected_lottery_date( $date_bounds );
		$date_attributes = '' !== $selected_date
			? array( 'date' => $selected_date )
			: array();
		$mode = sanitize_key( (string) $attributes['mode'] );
		if ( 'hub' === $mode ) {
			Power_Schedule_Manager_Assets::enqueue_public_assets( 'lottery' );

			return $this->render_lottery_hub(
				$attributes,
				$base_id,
				$selected_date,
				$date_bounds
			);
		}

		if ( $enabled( $attributes['traditional'] ) ) {
			$sections['ba-mien'] =
				$this->render_preset_safely( 'north', $date_attributes )
				. $this->render_preset_safely( 'central', $date_attributes )
				. $this->render_preset_safely( 'south', $date_attributes );
		}
		if ( $enabled( $attributes['vietlott'] ) ) {
			$sections['vietlott'] =
				$this->render_preset_safely( 'mega645', $date_attributes )
				. $this->render_preset_safely( 'power655', $date_attributes )
				. $this->render_preset_safely( 'max3dplus', $date_attributes )
				. $this->render_preset_safely( 'max3dpro', $date_attributes )
				. $this->render_preset_safely( 'keno', $date_attributes );
		}
		if ( $enabled( $attributes['dientoan'] ) ) {
			$dientoan_cards = $this->render_preset_safely(
				'dientoan123',
				$date_attributes,
				true
			)
				. $this->render_preset_safely(
					'dientoan6x36',
					$date_attributes,
					true
				)
				. $this->render_preset_safely(
					'thantai',
					$date_attributes,
					true
				);
			$sections['dien-toan'] = '' === $dientoan_cards
				? ''
				: '<div class="psm-dientoan-overview-grid psm-dientoan-overview-table" role="group" aria-label="'
				. esc_attr__(
					'Kết quả xổ số Điện toán',
					'power-schedule-manager'
				)
				. '">'
				. $dientoan_cards
				. '</div>';
		}
		if ( $enabled( $attributes['history'] ) ) {
			$history_limit = max(
				10,
				min( 50, absint( $attributes['history_limit'] ) )
			);
			$history_rows = array();
			foreach ( array( 'north', 'central', 'south' ) as $region ) {
				$region_rows = self::latest_rows(
					60,
					$region,
					'',
					array( 'traditional' ),
					$selected_date,
					true
				);
				$dates = array_values(
					array_unique(
						array_filter(
							array_column( $region_rows, 'draw_date' )
						)
					)
				);
				rsort( $dates );
				if ( count( $dates ) < 2 ) {
					continue;
				}
				foreach ( $region_rows as $region_row ) {
					if (
						$dates[0] !== (string) $region_row['draw_date']
						&& self::result_completeness( $region_row ) > 0
					) {
						$history_rows[] = $region_row;
					}
				}
			}
			foreach (
				array(
					'mega645',
					'power655',
					'max3d',
					'max3dplus',
					'max3dpro',
					'keno',
					'dientoan123',
					'dientoan6x36',
					'thantai',
				)
				as $game
			) {
				$game_rows = self::latest_rows(
					max( 30, $history_limit + 1 ),
					'',
					'',
					array( $game ),
					$selected_date,
					true
				);
				$game_rows = array_values(
					array_filter(
						$game_rows,
						static fn ( array $row ): bool =>
							self::result_completeness( $row ) > 0
					)
				);
				$history_rows = array_merge(
					$history_rows,
					array_slice( $game_rows, 1, $history_limit )
				);
			}
			usort(
				$history_rows,
				static fn ( array $a, array $b ): int =>
					strcmp(
						(string) ( $b['draw_date'] ?? '' )
							. (string) ( $b['provider_draw_id'] ?? '' ),
						(string) ( $a['draw_date'] ?? '' )
							. (string) ( $a['provider_draw_id'] ?? '' )
					)
			);
			$history_rows = array_slice(
				$history_rows,
				0,
				$history_limit
			);
			$sections['ky-truoc'] =
				Power_Schedule_Manager_Lottery_Renderer::render_history_overview(
					$history_rows,
					__( 'Các kỳ quay trước', 'power-schedule-manager' )
				);
		}
		Power_Schedule_Manager_Assets::enqueue_public_assets( 'lottery' );
		$labels = array(
			'ba-mien'   => __( 'Ba miền', 'power-schedule-manager' ),
			'vietlott'  => 'Vietlott',
			'dien-toan' => __( 'Điện toán', 'power-schedule-manager' ),
			'ky-truoc'  => __( 'Các kỳ trước', 'power-schedule-manager' ),
		);
		$html = '<section id="ket-qua" class="psm-lottery-overview" aria-labelledby="'
			. esc_attr( $base_id )
			. '-data-title"><header class="psm-data-section-header"><p>'
			. esc_html__( 'Dữ liệu xổ số', 'power-schedule-manager' )
			. '</p><h2 id="' . esc_attr( $base_id ) . '-data-title">'
			. esc_html__( 'Kết quả theo nhóm', 'power-schedule-manager' )
			. '</h2><nav aria-label="'
			. esc_attr__( 'Đi đến nhóm kết quả', 'power-schedule-manager' )
			. '">';
		foreach ( $sections as $anchor => $section ) {
			if ( '' === $section ) {
				continue;
			}
			$html .= '<a href="#' . esc_attr( $base_id . '-' . $anchor )
				. '">' . esc_html( $labels[ $anchor ] ) . '</a>';
		}
		$html .= '</nav></header>';
		if ( $enabled( $attributes['date_picker'] ) ) {
			$html .= '<aside class="psm-lottery-archive-toolbar" aria-label="'
				. esc_attr__( 'Tra cứu kết quả cũ', 'power-schedule-manager' )
				. '">' . self::render_lottery_date_picker(
					$selected_date,
					$date_bounds
				) . '</aside>';
		}
		$html .= '<div class="psm-lottery-overview__sections">';
		foreach ( $sections as $anchor => $section ) {
			if ( '' === $section ) {
				continue;
			}
			$html .= '<section id="' . esc_attr( $base_id . '-' . $anchor )
				. '" class="psm-lottery-overview__group" aria-label="'
				. esc_attr( $labels[ $anchor ] ) . '">' . $section
				. '</section>';
		}

		return $html . '</div>' . self::render_lottery_guide() . '</section>';
	}

	/**
	 * Render a concise navigation hub instead of concatenating every result.
	 *
	 * @param array<string,mixed>  $attributes    Shortcode attributes.
	 * @param array{min:string,max:string} $date_bounds Stored date range.
	 */
	private function render_lottery_hub(
		array $attributes,
		string $base_id,
		string $selected_date,
		array $date_bounds
	): string {
		$latest = static function (
			string $region,
			array $games
		) use ( $selected_date ): array {
			$rows = self::latest_rows(
				40,
				$region,
				'',
				$games,
				$selected_date
			);
			foreach ( $rows as $row ) {
				if ( self::result_completeness( $row ) > 0 ) {
					return self::decorate_admin_row( $row );
				}
			}

			return isset( $rows[0] ) && is_array( $rows[0] )
				? self::decorate_admin_row( $rows[0] )
				: array();
		};
		$item = static function ( string $label, array $row ): array {
			return array(
				'label'   => $label,
				'summary' => (string) (
					$row['result_summary']
						?? __( 'Chưa có dữ liệu mới', 'power-schedule-manager' )
				),
				'date'    => (string) ( $row['draw_date'] ?? '' ),
				'status'  => (string) (
					$row['status_label']
						?? __( 'Đang cập nhật', 'power-schedule-manager' )
				),
			);
		};
		$cards = array(
			array(
				'key'         => 'traditional',
				'eyebrow'     => __( 'Xổ số truyền thống', 'power-schedule-manager' ),
				'title'       => __( 'Xổ số ba miền', 'power-schedule-manager' ),
				'description' => __( 'Kết quả miền Bắc, miền Trung và miền Nam theo ngày quay.', 'power-schedule-manager' ),
				'url'         => (string) $attributes['traditional_url'],
				'items'       => array(
					$item(
						__( 'Miền Bắc', 'power-schedule-manager' ),
						$latest( 'north', array( 'traditional' ) )
					),
					$item(
						__( 'Miền Trung', 'power-schedule-manager' ),
						$latest( 'central', array( 'traditional' ) )
					),
					$item(
						__( 'Miền Nam', 'power-schedule-manager' ),
						$latest( 'south', array( 'traditional' ) )
					),
				),
			),
			array(
				'key'         => 'mega645',
				'eyebrow'     => 'Vietlott',
				'title'       => 'Mega 6/45',
				'description' => __( 'Dãy số trúng, mã kỳ quay và giá trị Jackpot mới nhất.', 'power-schedule-manager' ),
				'url'         => (string) $attributes['mega645_url'],
				'items'       => array(
					$item(
						__( 'Kỳ mới nhất', 'power-schedule-manager' ),
						$latest( '', array( 'mega645' ) )
					),
				),
			),
			array(
				'key'         => 'power655',
				'eyebrow'     => 'Vietlott',
				'title'       => 'Power 6/55',
				'description' => __( 'Theo dõi Jackpot 1, Jackpot 2 và kết quả kỳ gần nhất.', 'power-schedule-manager' ),
				'url'         => (string) $attributes['power655_url'],
				'items'       => array(
					$item(
						__( 'Kỳ mới nhất', 'power-schedule-manager' ),
						$latest( '', array( 'power655' ) )
					),
				),
			),
			array(
				'key'         => 'max3d',
				'eyebrow'     => 'Vietlott',
				'title'       => 'Max 3D',
				'description' => __( 'Hai bảng Max 3D+ và Max 3D Pro được tách bằng tab trên trang chi tiết.', 'power-schedule-manager' ),
				'url'         => (string) $attributes['max3d_url'],
				'items'       => array(
					$item( 'Max 3D+', $latest( '', array( 'max3dplus' ) ) ),
					$item( 'Max 3D Pro', $latest( '', array( 'max3dpro' ) ) ),
				),
			),
			array(
				'key'         => 'keno',
				'eyebrow'     => 'Vietlott',
				'title'       => 'Keno',
				'description' => __( 'Kết quả kỳ mới nhất và lịch sử nhiều kỳ trên trang riêng.', 'power-schedule-manager' ),
				'url'         => (string) $attributes['keno_url'],
				'items'       => array(
					$item(
						__( 'Kỳ mới nhất', 'power-schedule-manager' ),
						$latest( '', array( 'keno' ) )
					),
				),
			),
			array(
				'key'         => 'dientoan',
				'eyebrow'     => __( 'Xổ số Điện toán', 'power-schedule-manager' ),
				'title'       => __( 'Điện toán', 'power-schedule-manager' ),
				'description' => __( 'Điện toán 123, Điện toán 6x36 và Thần Tài trong một trang.', 'power-schedule-manager' ),
				'url'         => (string) $attributes['dientoan_url'],
				'items'       => array(
					$item(
						'123',
						$latest( '', array( 'dientoan123' ) )
					),
					$item(
						'6x36',
						$latest( '', array( 'dientoan6x36' ) )
					),
					$item(
						__( 'Thần Tài', 'power-schedule-manager' ),
						$latest( '', array( 'thantai' ) )
					),
				),
			),
		);
		$html = '<section id="ket-qua" class="psm-lottery-overview psm-lottery-overview--hub" aria-labelledby="'
			. esc_attr( $base_id ) . '-groups">';
		if ( 'no' !== strtolower( (string) $attributes['date_picker'] ) ) {
			$html .= '<aside class="psm-lottery-archive-toolbar" aria-label="'
				. esc_attr__( 'Tra cứu kết quả cũ', 'power-schedule-manager' )
				. '">' . self::render_lottery_date_picker(
					$selected_date,
					$date_bounds
				) . '</aside>';
		}
		$html .= '<section class="psm-lottery-hub" aria-labelledby="'
			. esc_attr( $base_id ) . '-groups"><header><p>'
			. esc_html__( 'Chọn nhóm kết quả', 'power-schedule-manager' )
			. '</p><h2 id="' . esc_attr( $base_id ) . '-groups">'
			. esc_html__( 'Tra cứu nhanh theo loại xổ số', 'power-schedule-manager' )
			. '</h2><span>'
			. esc_html__( 'Mỗi trang chi tiết giữ đúng bảng, lịch sử và cách đọc phù hợp với từng sản phẩm.', 'power-schedule-manager' )
			. '</span></header><div class="psm-lottery-hub__grid">';
		foreach ( $cards as $card ) {
			$url = esc_url( (string) $card['url'] );
			$html .= '<a class="psm-lottery-hub-card psm-lottery-hub-card--'
				. esc_attr( sanitize_html_class( (string) $card['key'] ) )
				. '" href="' . $url . '"><header><p>'
				. esc_html( (string) $card['eyebrow'] ) . '</p><h3>'
				. esc_html( (string) $card['title'] ) . '</h3></header><p>'
				. esc_html( (string) $card['description'] )
				. '</p><div class="psm-lottery-hub-card__results">';
			foreach ( $card['items'] as $preview ) {
				$date = (string) $preview['date'];
				$html .= '<div><span><strong>'
					. esc_html( (string) $preview['label'] )
					. '</strong><small>'
					. esc_html( (string) $preview['status'] )
					. '</small></span><b>'
					. esc_html( (string) $preview['summary'] )
					. '</b>'
					. ( '' !== $date
						? '<time datetime="' . esc_attr( $date ) . '">'
							. esc_html( mysql2date( 'd/m/Y', $date ) )
							. '</time>'
						: '' )
					. '</div>';
			}
			$html .= '</div><footer><span>'
				. esc_html__( 'Xem kết quả đầy đủ', 'power-schedule-manager' )
				. '</span><b aria-hidden="true">→</b></footer></a>';
		}
		$archive_url = esc_url( (string) $attributes['archive_url'] );
		$html .= '</div><a class="psm-lottery-hub__archive" href="'
			. $archive_url . '"><div><p>'
			. esc_html__( 'Kho kết quả', 'power-schedule-manager' )
			. '</p><h3>'
			. esc_html__( 'Tra cứu kết quả cũ', 'power-schedule-manager' )
			. '</h3><span>'
			. esc_html__( 'Chọn ngày và sản phẩm để tìm đúng kỳ quay đã lưu.', 'power-schedule-manager' )
			. '</span></div><strong>'
			. esc_html__( 'Mở trang tra cứu', 'power-schedule-manager' )
			. '</strong></a></section></section>';

		return $html;
	}

	/**
	 * Render one focused archive page with product and date controls.
	 */
	public function render_archive_shortcode(
		array|string $attributes = array()
	): string {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return '';
		}
		$attributes = shortcode_atts(
			array(
				'title' => __(
					'Tra cứu kết quả xổ số cũ',
					'power-schedule-manager'
				),
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$products = array(
			'north'       => __( 'Miền Bắc', 'power-schedule-manager' ),
			'central'     => __( 'Miền Trung', 'power-schedule-manager' ),
			'south'       => __( 'Miền Nam', 'power-schedule-manager' ),
			'mega645'     => 'Mega 6/45',
			'power655'    => 'Power 6/55',
			'max3dplus'   => 'Max 3D+',
			'max3dpro'    => 'Max 3D Pro',
			'keno'        => 'Keno',
			'dientoan123' => 'Điện toán 123',
			'dientoan6x36' => 'Điện toán 6x36',
			'thantai'     => __( 'Thần Tài 4', 'power-schedule-manager' ),
		);
		$selected_product = isset( $_GET['psm_lottery_game'] )
			&& is_scalar( $_GET['psm_lottery_game'] )
				? sanitize_key(
					wp_unslash( (string) $_GET['psm_lottery_game'] )
				)
				: 'north';
		if ( ! isset( $products[ $selected_product ] ) ) {
			$selected_product = 'north';
		}
		$bounds = self::lottery_date_bounds();
		$selected_date = self::selected_lottery_date( $bounds );
		if ( '' === $selected_date ) {
			$selected_date = $bounds['max'];
		}
		$action = remove_query_arg(
			array( 'psm_lottery_game', 'psm_lottery_date' )
		);
		$help_id = wp_unique_id( 'psm-lottery-archive-help-' );
		$html = '<section id="tra-cuu" class="psm-lottery-archive" aria-labelledby="'
			. esc_attr( $help_id ) . '-title"><header class="psm-data-section-header psm-data-section-header--tool"><p>'
			. esc_html__( 'Kho kết quả', 'power-schedule-manager' )
			. '</p><h2 id="' . esc_attr( $help_id ) . '-title">'
			. esc_html( (string) $attributes['title'] )
			. '</h2><span id="' . esc_attr( $help_id ) . '">'
			. esc_html__(
				'Chọn đúng sản phẩm và ngày quay để chỉ hiển thị bảng cần tra cứu.',
				'power-schedule-manager'
			)
			. '</span></header><form method="get" action="'
			. esc_url( $action ) . '"><label><span>'
			. esc_html__( 'Sản phẩm', 'power-schedule-manager' )
			. '</span><select name="psm_lottery_game">';
		foreach ( $products as $product => $label ) {
			$html .= '<option value="' . esc_attr( $product ) . '"'
				. selected( $selected_product, $product, false ) . '>'
				. esc_html( $label ) . '</option>';
		}
		$html .= '</select></label><label><span>'
			. esc_html__( 'Ngày quay', 'power-schedule-manager' )
			. '</span><input type="date" name="psm_lottery_date" value="'
			. esc_attr( $selected_date ) . '" min="'
			. esc_attr( $bounds['min'] ) . '" max="'
			. esc_attr( $bounds['max'] )
			. '"></label><button type="submit">'
			. esc_html__( 'Xem kết quả', 'power-schedule-manager' )
			. '</button></form><div class="psm-lottery-archive__result">'
			. $this->render_preset_shortcode(
				$selected_product,
				array(
					'date'  => $selected_date,
					'title' => (string) $products[ $selected_product ],
				)
			)
			. '</div></section>';
		Power_Schedule_Manager_Assets::enqueue_public_assets( 'lottery' );

		return $html;
	}

	/**
	 * Keep one malformed product payload from terminating the whole overview.
	 */
	private function render_preset_safely(
		string $preset,
		array $attributes,
		bool $skip_empty = false
	): string {
		try {
			$output = $this->render_preset_shortcode(
				$preset,
				$attributes
			);

			return $skip_empty
				&& str_contains( $output, 'psm-lottery-empty-card' )
					? ''
					: $output;
		} catch ( Throwable $error ) {
			Power_Schedule_Manager_Logger::error(
				'lottery_renderer_failed',
				$error,
				array( 'preset' => $preset )
			);

			$output = Power_Schedule_Manager_Lottery_Renderer::render(
				$preset,
				array()
			);

			return $skip_empty ? '' : $output;
		}
	}

	/**
	 * Add concise evergreen guidance after the dynamic lottery data.
	 */
	private static function render_lottery_guide(): string {
		return '<section class="psm-supporting-content psm-supporting-content--lottery"'
			. ' aria-label="Thông tin tra cứu kết quả xổ số">'
			. '<h2>'
			. esc_html__(
				'Cách tra cứu kết quả xổ số và Vietlott',
				'power-schedule-manager'
			)
			. '</h2><div><section><h3>'
			. esc_html__( 'Kiểm tra đúng ngày và kỳ quay', 'power-schedule-manager' )
			. '</h3><p>'
			. esc_html__(
				'Mỗi kết quả được lưu theo ngày, sản phẩm và mã kỳ quay. Người đọc có thể chọn ngày trong khoảng dữ liệu để tra cứu kết quả xổ số miền Bắc hôm nay, xổ số miền Trung theo tỉnh, xổ số miền Nam theo đài, Vietlott và xổ số Điện toán đã lưu.',
				'power-schedule-manager'
			)
			. '</p></section><section><h3>'
			. esc_html__( 'Phân biệt các nhóm sản phẩm', 'power-schedule-manager' )
			. '</h3><p>'
			. esc_html__(
				'Xổ số truyền thống hiển thị theo giải và tỉnh hoặc đài. Mega 6/45, Power 6/55, Max 3D, Max 3D+, Max 3D Pro và Keno hiển thị theo mã kỳ quay; Điện toán 123, Điện toán 6x36 và Thần Tài 4 được tách thành từng bảng để dễ đối chiếu.',
				'power-schedule-manager'
			)
			. '</p></section><section><h3>'
			. esc_html__( 'Trạng thái dữ liệu', 'power-schedule-manager' )
			. '</h3><p>'
			. esc_html__(
				'Kết quả đã công bố và kỳ quay mới nhất được ưu tiên hiển thị. Nội dung chỉ dùng để tra cứu kết quả chính thức, không phải dự đoán hoặc tư vấn đặt cược.',
				'power-schedule-manager'
			)
			. '</p></section><section><h3>'
			. esc_html__(
				'Cách xem xổ số miền Trung và miền Nam trên điện thoại',
				'power-schedule-manager'
			)
			. '</h3><p>'
			. esc_html__(
				'Mỗi giải được chia thành các ô tỉnh hoặc đài ngay trên màn hình, không cần kéo ngang. Tên đài nằm phía trên dãy số để người dùng vẫn nhận biết đúng kết quả khi xem trên điện thoại nhỏ.',
				'power-schedule-manager'
			)
			. '</p></section><section><h3>'
			. esc_html__(
				'Tra cứu kết quả Vietlott theo ngày và kỳ quay',
				'power-schedule-manager'
			)
			. '</h3><p>'
			. esc_html__(
				'Dùng bộ chọn ngày để xem lại Mega 6/45, Power 6/55, Max 3D hoặc Keno trong cơ sở dữ liệu. Mã kỳ quay, dãy số trúng, số lượng giải và giá trị giải được trình bày cùng nhau để hạn chế nhầm lẫn giữa các kỳ.',
				'power-schedule-manager'
			)
			. '</p></section><section><h3>'
			. esc_html__(
				'Kết quả đang quay trực tiếp được hiển thị thế nào?',
				'power-schedule-manager'
			)
			. '</h3><p>'
			. esc_html__(
				'Kỳ chưa công bố mang trạng thái đang chờ kết quả. Khi nguồn mới trả về một phần dãy số, bảng chuyển sang đang quay và chỉ đặt hiệu ứng tại các ô còn thiếu. Sau khi đủ cơ cấu giải, trạng thái đổi thành đã công bố.',
				'power-schedule-manager'
			)
			. '</p></section></div></section>';
	}

	/**
	 * Render stored results without contacting the provider.
	 */
	public function render_shortcode(
		array|string $attributes = array()
	): string {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return '';
		}

		Power_Schedule_Manager_Assets::enqueue_public_assets( 'lottery' );

		$attributes = shortcode_atts(
			array(
				'title'    => 'Kết quả xổ số mới nhất',
				'region'   => '',
				'province' => '',
				'limit'    => '6',
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$region = self::sanitize_region( $attributes['region'] );
		$province = self::clean_text( $attributes['province'], 191 );
		$limit = max( 1, min( 30, absint( $attributes['limit'] ) ) );
		$rows = self::latest_rows( $limit, $region, $province, array() );

		if ( array() === $rows ) {
			return '<div class="psm-lottery psm-lottery--empty">'
				. esc_html__(
					'Chưa có kết quả xổ số phù hợp.',
					'power-schedule-manager'
				)
				. '</div>';
		}

		$title_id = wp_unique_id( 'psm-lottery-title-' );

		ob_start();
		?>
		<section class="psm-lottery" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="psm-lottery__header">
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( (string) $attributes['title'] ); ?></h2>
				<p><?php esc_html_e( 'Kết quả xổ số mới nhất theo dữ liệu đã được xác minh.', 'power-schedule-manager' ); ?></p>
			</div>
			<div class="psm-lottery__grid">
				<?php foreach ( $rows as $row ) : ?>
					<article class="psm-lottery__draw">
						<header>
							<div>
								<strong><?php echo esc_html( $row['province_name'] ?: self::region_label( $row['region'] ) ); ?></strong>
								<time datetime="<?php echo esc_attr( $row['draw_date'] ); ?>"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $row['draw_date'] ) ) ); ?></time>
							</div>
							<span><?php echo esc_html( self::region_label( $row['region'] ) ); ?></span>
						</header>
						<?php echo self::render_results( $row['results_json'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render a purpose-built lottery layout from locally stored data.
	 */
	public function render_preset_shortcode(
		string $preset,
		array|string $attributes = array()
	): string {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'title' => '',
				'date'  => '',
				'limit' => match ( $preset ) {
					'north', 'central', 'south' => '12',
					'keno' => '10',
					'keno_history' => '10',
					/* Product shortcodes render one latest draw; history has its own shortcode. */
					'dientoan123' => '1',
					'dientoan' => '6',
					default => '1',
				},
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$limit = max( 1, min( 30, absint( $attributes['limit'] ) ) );
		$draw_date = self::normalize_date(
			sanitize_text_field( (string) $attributes['date'] )
		);
		$region = in_array(
			$preset,
			array( 'north', 'central', 'south' ),
			true
		) ? $preset : '';
		$game_types = '' === $region
			? self::preset_game_types( $preset )
			: array( 'traditional' );
		$query_limit = (
			'' === $region
			&& ! in_array(
				$preset,
				array( 'dientoan' ),
				true
			)
		) ? max( 30, $limit ) : ( 'dientoan' === $preset ? 100 : $limit );
		$rows = self::latest_rows(
			$query_limit,
			$region,
			'',
			$game_types,
			$draw_date
		);
		if ( 'dientoan' === $preset ) {
			$rows = self::latest_dientoan_products( $rows );
		}
		if ( 'max3dplus' === $preset ) {
			$plus_rows = array_values(
				array_filter(
					$rows,
					static fn ( array $row ): bool =>
						self::row_has_max3dplus_prizes( $row )
						&& self::result_completeness( $row ) > 0
				)
			);
			/*
			 * A provider may expose each Max 3D+ prize as a metadata-only row.
			 * Never mistake one of those rows for the complete draw board.
			 */
			$rows = $plus_rows;
		}
		if (
			'' === $region
			&& ! in_array(
				$preset,
				array( 'dientoan' ),
				true
			)
		) {
			$complete_rows = array_values(
				array_filter(
					$rows,
					static fn ( array $row ): bool =>
						self::result_completeness( $row ) > 0
				)
			);
			if ( array() !== $complete_rows ) {
				usort(
					$complete_rows,
					static function ( array $a, array $b ): int {
						$date_order = strcmp(
							(string) ( $b['draw_date'] ?? '' ),
							(string) ( $a['draw_date'] ?? '' )
						);
						if ( 0 !== $date_order ) {
							return $date_order;
						}

						return strnatcasecmp(
							(string) ( $b['provider_draw_id'] ?? '' ),
							(string) ( $a['provider_draw_id'] ?? '' )
						);
					}
				);
			}
			$rows = array_slice( $complete_rows, 0, $limit );
		}

		Power_Schedule_Manager_Assets::enqueue_public_assets( 'lottery' );

		return Power_Schedule_Manager_Lottery_Renderer::render(
			$preset,
			$rows,
			(string) $attributes['title']
		);
	}

	/**
	 * Keep exactly one useful row for each traditional computerized product.
	 * Complete results win over newer placeholders from the live endpoint.
	 *
	 * @param array<int,array<string,string>> $rows Stored provider rows.
	 * @return array<int,array<string,string>>
	 */
	private static function latest_dientoan_products( array $rows ): array {
		$products = array( 'dientoan123', 'dientoan6x36', 'thantai' );
		$selected = array();
		$fallback = array();
		foreach ( $rows as $row ) {
			$game = (string) ( $row['game_type'] ?? '' );
			if ( ! in_array( $game, $products, true ) ) {
				continue;
			}
			if ( ! isset( $fallback[ $game ] ) ) {
				$fallback[ $game ] = $row;
			}
			if (
				! isset( $selected[ $game ] )
				&& self::result_completeness( $row ) > 0
			) {
				$selected[ $game ] = $row;
			}
		}

		$output = array();
		foreach ( $products as $game ) {
			if ( isset( $selected[ $game ] ) ) {
				$output[] = $selected[ $game ];
			} elseif ( isset( $fallback[ $game ] ) ) {
				$output[] = $fallback[ $game ];
			}
		}

		return $output;
	}

	/**
	 * Return the latest stored draw status for the page hero.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function hero_summary(): array {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return array();
		}

		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS
		);
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT draw_date,draw_time,status,fetched_at_utc FROM %i WHERE is_public=1 ORDER BY draw_date DESC,fetched_at_utc DESC,id DESC LIMIT 1',
				$table
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return array();
		}

		$status = sanitize_key( (string) ( $row['status'] ?? '' ) );
		$status_label = in_array( $status, array( 'live', 'drawing', 'updating' ), true )
			? __( 'Đang cập nhật', 'power-schedule-manager' )
			: __( 'Đã có kết quả', 'power-schedule-manager' );
		$time = sanitize_text_field( (string) ( $row['draw_time'] ?? '' ) );
		if ( '' === $time && ! empty( $row['fetched_at_utc'] ) ) {
			$time = get_date_from_gmt(
				(string) $row['fetched_at_utc'],
				'H:i'
			);
		}
		$date_timestamp = strtotime( (string) ( $row['draw_date'] ?? '' ) );

		return array(
			array(
				'label'  => __( 'Trạng thái', 'power-schedule-manager' ),
				'value'  => $status_label,
				'detail' => '' !== $time ? $time : __( 'Theo kỳ quay', 'power-schedule-manager' ),
				'tone'   => in_array( $status, array( 'live', 'drawing', 'updating' ), true )
					? 'live' : '',
			),
			array(
				'label'  => __( 'Kỳ gần nhất', 'power-schedule-manager' ),
				'value'  => false !== $date_timestamp
					? wp_date( 'd/m/Y', $date_timestamp ) : '',
				'detail' => __( 'Dữ liệu đã lưu', 'power-schedule-manager' ),
				'tone'   => '',
			),
		);
	}

	/**
	 * Confirm that a stored Max 3D row contains the Max 3D+ prize category.
	 *
	 * @param array<string,string> $row Stored draw.
	 */
	private static function row_has_max3dplus_prizes( array $row ): bool {
		$decoded = json_decode(
			(string) ( $row['results_json'] ?? '' ),
			true,
			64
		);
		if ( ! is_array( $decoded ) ) {
			return false;
		}
		$prizes = $decoded['prizes']
			?? $decoded['prize_table']
			?? $decoded['giai_thuong']
			?? array();
		if ( ! is_array( $prizes ) ) {
			return false;
		}
		foreach ( $prizes as $prize ) {
			if ( ! is_array( $prize ) ) {
				continue;
			}
			$category = strtolower(
				(string) (
					$prize['category']
						?? $prize['product']
						?? ''
				)
			);
			if (
				str_contains( $category, '+' )
				|| str_contains( $category, 'plus' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render recent draws for any supported non-traditional product.
	 */
	public function render_history_shortcode(
		array|string $attributes = array()
	): string {
		$attributes = shortcode_atts(
			array(
				'game'  => 'keno',
				'title' => '',
				'limit' => '10',
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$game = sanitize_key( (string) $attributes['game'] );
		$games = array(
			'mega645' => array( 'mega645', 'Các kỳ Mega 6/45 trước' ),
			'power655' => array( 'power655', 'Các kỳ Power 6/55 trước' ),
			'max3d' => array( 'max3d', 'Các kỳ Max 3D trước' ),
			'max3dplus' => array( 'max3dplus', 'Các kỳ Max 3D+ trước' ),
			'max3dpro' => array( 'max3dpro', 'Các kỳ Max 3D Pro trước' ),
			'keno' => array( 'keno_history', 'Các kỳ Keno trước' ),
			'dientoan123' => array(
				'dientoan123',
				'Các kỳ Điện toán 123 trước',
			),
			'dientoan6x36' => array(
				'dientoan6x36',
				'Các kỳ Điện toán 6x36 trước',
			),
			'thantai' => array( 'thantai', 'Các kỳ Thần Tài 4 trước' ),
		);
		if ( ! isset( $games[ $game ] ) ) {
			return '';
		}

		return $this->render_history_preset_shortcode(
			$games[ $game ][0],
			'' !== trim( (string) $attributes['title'] )
				? (string) $attributes['title']
				: $games[ $game ][1],
			array( 'limit' => $attributes['limit'] )
		);
	}

	/**
	 * Render a product history using the same verified product-specific layout.
	 */
	private function render_history_preset_shortcode(
		string $preset,
		string $default_title,
		array|string $attributes = array()
	): string {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return '';
		}
		$attributes = shortcode_atts(
			array(
				'title' => $default_title,
				'limit' => '10',
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$limit = max( 10, min( 50, absint( $attributes['limit'] ) ) );
		$requested_limit = 'keno_history' === $preset ? $limit + 1 : $limit;
		$rows = self::latest_rows(
			max( 30, $requested_limit * 2 ),
			'',
			'',
			self::preset_game_types( $preset )
		);
		$rows = array_slice(
			array_values(
				array_filter(
					$rows,
					static fn ( array $row ): bool =>
						self::result_completeness( $row ) > 0
				)
			),
			0,
			$requested_limit
		);
		Power_Schedule_Manager_Assets::enqueue_public_assets( 'lottery' );

		return Power_Schedule_Manager_Lottery_Renderer::render_history_overview(
			$rows,
			(string) $attributes['title']
		);
	}

	/**
	 * Render the latest northern special prizes from locally stored draws.
	 */
	public function render_special_week_shortcode(
		array|string $attributes = array()
	): string {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return '';
		}
		$attributes = shortcode_atts(
			array(
				'title' => __( 'Bảng Đặc Biệt Tuần', 'power-schedule-manager' ),
				'limit' => '7',
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$limit = max( 7, min( 28, absint( $attributes['limit'] ) ) );
		$candidates = self::latest_rows(
			max( 60, $limit * 4 ),
			'north',
			'',
			array( 'traditional' )
		);
		$rows = array();
		$seen_dates = array();
		foreach ( $candidates as $candidate ) {
			$date = (string) ( $candidate['draw_date'] ?? '' );
			if ( '' === $date || isset( $seen_dates[ $date ] ) ) {
				continue;
			}
			$special = trim(
				(string) ( $candidate['special_prize'] ?? '' )
			);
			if ( '' === $special ) {
				$decoded = json_decode(
					(string) ( $candidate['results_json'] ?? '' ),
					true,
					64
				);
				if ( is_array( $decoded ) ) {
					$result_rows = isset( $decoded['results'] )
						&& is_array( $decoded['results'] )
							? $decoded['results']
							: $decoded;
					$special = self::extract_special_prize(
						array(),
						$result_rows
					);
				}
			}
			if ( '' === $special ) {
				continue;
			}
			$seen_dates[ $date ] = true;
			$digits = preg_replace( '/\D+/', '', $special ) ?? '';
			$tail = strlen( $digits ) >= 2 ? substr( $digits, -2 ) : $digits;
			$rows[] = array(
				'draw_date'     => $date,
				'special_prize' => $special,
				'last_two'      => $tail,
				'total'         => '' !== $tail
					? (string) array_sum( array_map( 'intval', str_split( $tail ) ) )
					: '',
			);
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}

		Power_Schedule_Manager_Assets::enqueue_public_assets( 'lottery' );

		return Power_Schedule_Manager_Lottery_Renderer::render_special_week(
			$rows,
			(string) $attributes['title']
		);
	}

	/**
	 * Return a balanced administration overview instead of letting frequent
	 * Keno draws hide all other products.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function admin_overview_rows(): array {
		$targets = array(
			'north|traditional' => 4,
			'central|traditional' => 6,
			'south|traditional' => 7,
			'|mega645' => 1,
			'|power655' => 1,
			'|max3d' => 1,
			'|max3dplus' => 1,
			'|max3dpro' => 1,
			'|keno' => 3,
			'|dientoan123' => 1,
			'|dientoan6x36' => 1,
			'|thantai' => 1,
		);
		$rows = array();
		$seen = array();
		$counts = array_fill_keys( array_keys( $targets ), 0 );
		$candidates = self::latest_rows( 1000, '', '' );
		$complete_groups = array();
		foreach ( $candidates as $candidate ) {
			if ( self::result_completeness( $candidate ) <= 0 ) {
				continue;
			}
			$station = 'traditional' === (string) $candidate['game_type']
				? (string) (
					$candidate['province_code']
						?: $candidate['province_name']
				)
				: '';
			$complete_groups[
				(string) $candidate['region']
				. '|' . (string) $candidate['game_type']
				. '|' . (string) $candidate['draw_date']
				. '|' . sanitize_key( $station )
			] = true;
		}
		foreach ( $candidates as $row ) {
			$station = 'traditional' === (string) $row['game_type']
				? (string) ( $row['province_code'] ?: $row['province_name'] )
				: '';
			$complete_key = (string) $row['region']
				. '|' . (string) $row['game_type']
				. '|' . (string) $row['draw_date']
				. '|' . sanitize_key( $station );
			if (
				isset( $complete_groups[ $complete_key ] )
				&& self::result_completeness( $row ) <= 0
			) {
				continue;
			}
			$keys = array(
				(string) $row['region'] . '|' . (string) $row['game_type'],
				'|' . (string) $row['game_type'],
			);
			foreach ( $keys as $target_key ) {
				if (
					! isset( $targets[ $target_key ] )
					|| $counts[ $target_key ] >= $targets[ $target_key ]
				) {
					continue;
				}
				$key = (string) ( $row['id'] ?? '' ) . '|' . $target_key;
				if ( '' === $key || isset( $seen[ $key ] ) ) {
					break;
				}
				$seen[ $key ] = true;
				++$counts[ $target_key ];
				$rows[] = self::decorate_admin_row( $row );
				break;
			}
		}
		usort(
			$rows,
			static fn ( array $a, array $b ): int =>
				strcmp(
					(string) $b['draw_date'] . (string) $b['fetched_at_utc'],
					(string) $a['draw_date'] . (string) $a['fetched_at_utc']
				)
		);

		return $rows;
	}

	/**
	 * Build concise statistics for the lottery administration screen.
	 *
	 * @param array<int,array<string,string>> $rows Overview rows.
	 * @param array<string,mixed>             $settings Module settings.
	 * @return array<string,string>
	 */
	private static function admin_overview(
		array $rows,
		array $settings
	): array {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS
		);
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE is_public = 1"
		);
		$products = array_unique(
			array_filter(
				array_column( $rows, 'game_type' )
			)
		);
		$updating = count(
			array_filter(
				$rows,
				static fn ( array $row ): bool =>
					'updating' === (string) ( $row['display_status'] ?? '' )
			)
		);

		return array(
			'total'        => number_format_i18n( $total ),
			'products'     => (string) count( $products ),
			'updating'     => (string) $updating,
			'last_success' => (string) (
				$settings['last_success_at_utc'] ?? ''
			),
		);
	}

	/**
	 * Add readable product, status and primary-result fields for admin.
	 *
	 * @param array<string,string> $row Stored row.
	 * @return array<string,string>
	 */
	private static function decorate_admin_row( array $row ): array {
		$decoded = json_decode(
			(string) ( $row['results_json'] ?? '' ),
			true,
			64
		);
		$result_rows = is_array( $decoded )
			&& isset( $decoded['results'] )
			&& is_array( $decoded['results'] )
				? $decoded['results']
				: $decoded;
		$values = array();
		if ( is_array( $result_rows ) ) {
			foreach ( $result_rows as $result_key => $item ) {
				if ( is_array( $item ) ) {
					$value = $item['value']
						?? $item['number']
						?? $item['result']
						?? null;
					if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
						$values[] = trim( (string) $value );
					} elseif (
						isset( $item['values'] )
						&& is_array( $item['values'] )
					) {
						foreach ( $item['values'] as $item_value ) {
							if (
								is_scalar( $item_value )
								&& '' !== trim( (string) $item_value )
							) {
								$values[] = trim( (string) $item_value );
							}
						}
					} elseif (
						! array_is_list( $result_rows )
						&& 1 === preg_match(
							'/^(db|g[1-8]|special|special_prize)$/',
							sanitize_key( (string) $result_key )
						)
					) {
						array_walk_recursive(
							$item,
							static function ( mixed $nested ) use ( &$values ): void {
								if (
									is_scalar( $nested )
									&& '' !== trim( (string) $nested )
								) {
									$values[] = trim( (string) $nested );
								}
							}
						);
					}
					continue;
				}
				if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
					$values[] = trim( (string) $item );
				}
			}
		}
		if ( array() === $values && '' !== (string) $row['special_prize'] ) {
			$values[] = (string) $row['special_prize'];
		}
		$values = array_values( array_unique( $values ) );
		$row['result_count'] = (string) count( $values );
		$row['result_summary'] = array() === $values
			? __( 'Đang cập nhật', 'power-schedule-manager' )
			: implode( ' · ', array_slice( $values, 0, 7 ) )
				. ( count( $values ) > 7 ? '…' : '' );
		$row['display_status'] = array() === $values
			? 'updating'
			: 'completed';
		$row['status_label'] = array() === $values
			? __( 'Đang cập nhật', 'power-schedule-manager' )
			: __( 'Đã có kết quả', 'power-schedule-manager' );
		$row['game_label'] = match ( (string) $row['game_type'] ) {
			'traditional' => __( 'Truyền thống', 'power-schedule-manager' ),
			'mega645' => 'Mega 6/45',
			'power655' => 'Power 6/55',
			'max3d' => 'Max 3D',
			'max3dplus' => 'Max 3D+',
			'max3dpro' => 'Max 3D Pro',
			'keno' => 'Keno',
			'dientoan123' => 'Điện toán 123',
			'dientoan6x36' => 'Điện toán 6x36',
			'thantai' => 'Thần Tài',
			default => ucfirst( (string) $row['game_type'] ),
		};
		$row['fetched_display'] = '' !== (string) $row['fetched_at_utc']
			? get_date_from_gmt(
				(string) $row['fetched_at_utc'],
				'd/m/Y H:i'
			)
			: '—';

		return $row;
	}

	/**
	 * Return the public date range already stored locally.
	 *
	 * @return array{min:string,max:string}
	 */
	private static function lottery_date_bounds(): array {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS
		);
		$bounds = $wpdb->get_row(
			"SELECT MIN(draw_date) AS min_date, MAX(draw_date) AS max_date
			FROM {$table} WHERE is_public = 1",
			ARRAY_A
		);

		return array(
			'min' => self::normalize_date(
				(string) ( $bounds['min_date'] ?? '' )
			),
			'max' => self::normalize_date(
				(string) ( $bounds['max_date'] ?? '' )
			),
		);
	}

	/**
	 * Read a bounded date selected by the visitor.
	 *
	 * @param array{min:string,max:string} $bounds Stored date range.
	 */
	private static function selected_lottery_date( array $bounds ): string {
		$requested = isset( $_GET['psm_lottery_date'] )
			? sanitize_text_field(
				wp_unslash( (string) $_GET['psm_lottery_date'] )
			)
			: '';
		$date = self::normalize_date( $requested );
		if (
			'' === $date
			|| ( '' !== $bounds['min'] && $date < $bounds['min'] )
			|| ( '' !== $bounds['max'] && $date > $bounds['max'] )
		) {
			return '';
		}

		return $date;
	}

	/**
	 * Render a no-JavaScript date selector backed only by local rows.
	 *
	 * @param array{min:string,max:string} $bounds Stored date range.
	 */
	private static function render_lottery_date_picker(
		string $selected_date,
		array $bounds
	): string {
		if ( '' === $bounds['min'] || '' === $bounds['max'] ) {
			return '';
		}
		$action = remove_query_arg( 'psm_lottery_date' );
		$help_id = wp_unique_id( 'psm-lottery-date-help-' );
		$field_value = '' !== $selected_date
			? $selected_date
			: $bounds['max'];
		$html = '<form class="psm-lottery-date-picker" method="get" action="'
			. esc_url( $action ) . '"><label for="psm-lottery-date">'
			. esc_html__( 'Tra cứu kết quả theo ngày', 'power-schedule-manager' )
			. '</label><div class="psm-lottery-date-picker__controls">'
			. '<input id="psm-lottery-date" type="date"'
			. ' name="psm_lottery_date" min="' . esc_attr( $bounds['min'] )
			. '" max="' . esc_attr( $bounds['max'] ) . '" value="'
			. esc_attr( $field_value ) . '" aria-describedby="'
			. esc_attr( $help_id ) . '"><button type="submit">'
			. esc_html__( 'Xem ngày này', 'power-schedule-manager' )
			. '</button>';
		if ( '' !== $selected_date ) {
			$html .= '<a href="' . esc_url( $action ) . '">'
				. esc_html__( 'Kỳ mới nhất', 'power-schedule-manager' )
				. '</a>';
		}

		return $html . '</div><small id="' . esc_attr( $help_id ) . '">'
			. sprintf(
				/* translators: 1: earliest date, 2: latest date. */
				esc_html__( 'Dữ liệu lưu từ %1$s đến %2$s.', 'power-schedule-manager' ),
				esc_html( wp_date( 'd/m/Y', strtotime( $bounds['min'] ) ) ),
				esc_html( wp_date( 'd/m/Y', strtotime( $bounds['max'] ) ) )
			)
			. '</small></form>';
	}

	/**
	 * Return recent stored rows.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function latest_rows(
		int $limit,
		string $region,
		string $province,
		array $game_types = array(),
		string $draw_date = '',
		bool $before_or_on = false
	): array {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS
		);
		$region = self::sanitize_region( $region );
		$province_filter = self::normalize_search_text( $province );
		$draw_date = self::normalize_date( $draw_date );
		$game_types = array_values(
			array_unique(
				array_filter(
					array_map(
						array( self::class, 'normalize_game_type' ),
						$game_types
					)
				)
			)
		);
		/*
		 * Older imports stored the three traditional computerized products under
		 * the shared "dientoan" type. Include those legacy rows in the SQL pool,
		 * then hydrate their exact product from source_payload_json before the
		 * strict post-query filter below. This keeps current shortcodes accurate
		 * without exposing one product in another product's result list.
		 */
		$query_game_types = $game_types;
		if (
			array_intersect(
				$game_types,
				array( 'dientoan123', 'dientoan6x36', 'thantai' )
			)
		) {
			/*
			 * Fetch the whole Điện toán family before hydrating legacy rows.
			 * Some older imports inferred the product from a parent envelope that
			 * mentioned all three games, so a valid DT123 row may have been stored
			 * temporarily as 6x36 (or vice versa). The strict filter below still
			 * returns only the requested product after source-payload hydration.
			 */
			$query_game_types = array_merge(
				$query_game_types,
				array( 'dientoan', 'dientoan123', 'dientoan6x36', 'thantai' )
			);
			$query_game_types = array_values(
				array_unique( $query_game_types )
			);
		}
		/*
		 * Keno publishes many draws per day. A small shared pool can therefore
		 * hide weekly Mega/Power products before metadata filtering runs.
		 */
		$pool_limit = '' !== $region || array() !== $game_types
			? max( 100, min( 1000, $limit * 20 ) )
			: 1000;
		$where = array( 'is_public = 1' );
		$parameters = array();
		if ( '' !== $region ) {
			$where[] = 'region = %s';
			$parameters[] = $region;
		}
		if ( array() !== $query_game_types ) {
			$where[] = 'game_type IN ('
				. implode( ',', array_fill( 0, count( $query_game_types ), '%s' ) )
				. ')';
			$parameters = array_merge( $parameters, $query_game_types );
		}
		if ( '' !== $draw_date ) {
			$where[] = $before_or_on ? 'draw_date <= %s' : 'draw_date = %s';
			$parameters[] = $draw_date;
		}
		$parameters[] = $pool_limit;
		$sql = "SELECT id,provider_draw_id,region,province_code,province_name,
			game_type,draw_date,draw_time,status,special_prize,results_json,
			fetched_at_utc,source_payload_json
			FROM {$table}
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY draw_date DESC, id DESC LIMIT %d";
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $parameters ),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$filtered = array();
		$seen = array();

		foreach ( $rows as $row ) {
			try {
				$row = self::hydrate_row_metadata( $row );
			} catch ( Throwable $error ) {
				Power_Schedule_Manager_Logger::error(
					'lottery_row_hydration_failed',
					$error,
					array(
						'row_id' => (int) ( $row['id'] ?? 0 ),
					)
				);
				continue;
			}
			if (
				array_intersect(
					$game_types,
					array( 'dientoan123', 'dientoan6x36', 'thantai' )
				)
			) {
				$row['game_type'] = self::refine_dientoan_game_type( $row );
			}
			if ( '' !== $region && $region !== $row['region'] ) {
				continue;
			}
			if (
				'' !== $province_filter
				&& ! str_contains(
					self::normalize_search_text(
						(string) $row['province_name']
					),
					$province_filter
				)
			) {
				continue;
			}
			if (
				array() !== $game_types
				&& ! in_array( $row['game_type'], $game_types, true )
			) {
				continue;
			}

			$dedupe_key = implode(
				'|',
				array(
					$row['game_type'],
					$row['draw_date'],
					$row['province_code'] ?: $row['province_name'],
					$row['provider_draw_id']
						?: hash( 'sha256', $row['results_json'] ),
				)
			);
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;
			$filtered[] = $row;
			if ( count( $filtered ) >= $limit ) {
				break;
			}
		}

		return $filtered;
	}

	/**
	 * Recover the exact Điện toán product from its result structure.
	 *
	 * Older imports could inherit a parent label mentioning all three products.
	 * G1/G2/G3 is uniquely Điện toán 123, six one/two-digit G1 values identify
	 * 6x36, and one four-digit value identifies Thần Tài 4.
	 *
	 * @param array<string,string> $row Stored draw.
	 */
	private static function refine_dientoan_game_type( array $row ): string {
		$current = self::normalize_game_type(
			(string) ( $row['game_type'] ?? '' )
		);
		$decoded = json_decode(
			(string) ( $row['results_json'] ?? '' ),
			true,
			64
		);
		if ( ! is_array( $decoded ) ) {
			return $current;
		}
		if ( isset( $decoded['results'] ) && is_array( $decoded['results'] ) ) {
			$decoded = $decoded['results'];
		}

		$prizes = array();
		if ( array_is_list( $decoded ) ) {
			foreach ( $decoded as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$code = preg_replace(
					'/[^a-z0-9]+/',
					'',
					strtolower(
						remove_accents(
							(string) ( $item['prizeCode'] ?? $item['prize_code'] ?? $item['code'] ?? $item['prize'] ?? $item['name'] ?? '' )
						)
					)
				) ?: '';
				$code = match ( $code ) {
					'bo1', 'set1', 'first' => 'g1',
					'bo2', 'set2', 'second' => 'g2',
					'bo3', 'set3', 'third' => 'g3',
					default => $code,
				};
				if ( '' !== $code ) {
					$prizes[ $code ] = $item['value']
						?? $item['number']
						?? $item['result']
						?? $item['values']
						?? array();
				}
			}
		} else {
			foreach ( $decoded as $key => $value ) {
				$clean_key = preg_replace(
					'/[^a-z0-9]+/',
					'',
					strtolower( remove_accents( (string) $key ) )
				) ?: '';
				$clean_key = match ( $clean_key ) {
					'bo1', 'set1', 'first' => 'g1',
					'bo2', 'set2', 'second' => 'g2',
					'bo3', 'set3', 'third' => 'g3',
					default => $clean_key,
				};
				$prizes[ $clean_key ] = $value;
			}
		}

		if ( isset( $prizes['g1'], $prizes['g2'], $prizes['g3'] ) ) {
			return 'dientoan123';
		}

		$scalars = array();
		$collect = static function ( mixed $value ) use ( &$scalars, &$collect ): void {
			if ( is_array( $value ) ) {
				foreach ( $value as $nested ) {
					$collect( $nested );
				}
				return;
			}
			if ( is_scalar( $value ) ) {
				$clean = preg_replace( '/\D+/', '', (string) $value );
				if ( is_string( $clean ) && '' !== $clean ) {
					$scalars[] = $clean;
				}
			}
		};
		$collect( $prizes['g1'] ?? $prizes['db'] ?? $decoded );
		if (
			3 === count( $scalars )
			&& array( 1, 2, 3 ) === array_map(
				'strlen',
				array_values( $scalars )
			)
		) {
			return 'dientoan123';
		}
		if (
			count( $scalars ) >= 6
			&& 6 === count(
				array_filter(
					array_slice( $scalars, 0, 6 ),
					static fn ( string $value ): bool => strlen( $value ) <= 2
				)
			)
		) {
			return 'dientoan6x36';
		}
		if ( 1 === count( $scalars ) && 4 === strlen( $scalars[0] ) ) {
			return 'thantai';
		}

		return $current;
	}

	/**
	 * Fill legacy metadata from the stored provider payload.
	 *
	 * @param array<string,string> $row Stored row.
	 * @return array<string,string>
	 */
	private static function hydrate_row_metadata( array $row ): array {
		$payload = json_decode(
			(string) ( $row['source_payload_json'] ?? '' ),
			true,
			64
		);
		if ( is_array( $payload ) ) {
			$normalized = self::normalize_draw( $payload );
			if ( is_array( $normalized ) ) {
				foreach (
					array(
						'provider_draw_id',
						'region',
						'province_code',
						'province_name',
						'game_type',
						'draw_date',
						'draw_time',
						'status',
						'special_prize',
						'results_json',
					)
					as $field
				) {
					if (
						'' !== (string) ( $normalized[ $field ] ?? '' )
						&& (
							'' === (string) ( $row[ $field ] ?? '' )
							|| in_array(
								$field,
								array(
									'provider_draw_id',
									'game_type',
									'draw_date',
									'results_json',
								),
								true
							)
						)
					) {
						$row[ $field ] = (string) $normalized[ $field ];
					}
				}
			}
		}

		$row['province_name'] = self::clean_text(
			(string) ( $row['province_name'] ?? '' ),
			191
		);
		$row['region'] = self::sanitize_region(
			(string) ( $row['region'] ?? '' )
		);
		if ( '' === $row['region'] ) {
			$row['region'] = self::infer_region( $row['province_name'] );
		}
		$evidence = implode(
			' ',
			array_filter(
				array(
					(string) ( $row['game_type'] ?? '' ),
					(string) ( $row['province_name'] ?? '' ),
					(string) ( $row['provider_draw_id'] ?? '' ),
					(string) ( $row['results_json'] ?? '' ),
					is_array( $payload )
						? self::collect_game_type_evidence( $payload )
						: '',
				),
				static fn ( string $part ): bool => '' !== trim( $part )
			)
		);
		$detected_game_type = self::detect_game_type_from_evidence(
			$evidence
		);
		$row['game_type'] = '' !== $detected_game_type
			? $detected_game_type
			: self::normalize_game_type(
				(string) ( $row['game_type'] ?? '' )
			);

		return array_map(
			static fn ( mixed $value ): string =>
				is_scalar( $value ) ? (string) $value : '',
			$row
		);
	}

	/**
	 * Render prize results from decoded JSON only.
	 */
	private static function render_results( string $json ): string {
		$results = json_decode( $json, true, 32 );
		if ( ! is_array( $results ) ) {
			return '';
		}

		$html = '<dl class="psm-lottery__prizes">';
		$shown = 0;
		foreach ( $results as $label => $value ) {
			if ( $shown >= 12 ) {
				break;
			}
			$values = is_array( $value ) ? $value : array( $value );
			$numbers = array();
			array_walk_recursive(
				$values,
				static function ( mixed $item ) use ( &$numbers ): void {
					if ( is_scalar( $item ) ) {
						$clean = preg_replace(
							'/[^0-9A-Za-z-]/u',
							'',
							(string) $item
						);
						if ( '' !== $clean ) {
							$numbers[] = $clean;
						}
					}
				}
			);
			if ( array() === $numbers ) {
				continue;
			}
			$html .= '<div><dt>'
				. esc_html( self::prize_label( (string) $label ) )
				. '</dt><dd>'
				. esc_html( implode( ' · ', array_slice( $numbers, 0, 20 ) ) )
				. '</dd></div>';
			++$shown;
		}
		$html .= '</dl>';

		return $html;
	}

	/**
	 * Remove old results in bounded batches without multi-table DELETE LIMIT.
	 */
	public function delete_expired_draws(): void {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::LOTTERY_DRAWS
			)
		) {
			return;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS
		);
		$cutoff = gmdate(
			'Y-m-d',
			strtotime( '-' . self::RETENTION_MONTHS . ' months' )
		);
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE draw_date < %s
				ORDER BY id ASC LIMIT 500",
				$cutoff
			)
		);
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( array() === $ids ) {
			return;
		}

		$wpdb->query(
			"DELETE FROM {$table} WHERE id IN ("
			. implode( ',', $ids )
			. ')'
		);
	}

	/**
	 * Settings with runtime credential metadata.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings(): array {
		$stored = get_option( self::SETTINGS_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$settings = wp_parse_args(
			$stored,
			array(
				'enabled'                    => false,
				'api_key_encrypted'           => '',
				'webhook_secret_encrypted'    => '',
				'default_region'              => '',
				'last_success_at_utc'         => '',
				'last_error_code'             => '',
				'last_count'                  => 0,
				'last_endpoint_report'        => array(),
			)
		);
		$settings['api_key_source'] =
			Power_Schedule_Manager_Secrets::source(
				self::API_KEY_CONSTANT,
				(string) $settings['api_key_encrypted']
			);
		$settings['webhook_secret_source'] =
			Power_Schedule_Manager_Secrets::source(
				self::WEBHOOK_SECRET_CONSTANT,
				(string) $settings['webhook_secret_encrypted']
			);

		return $settings;
	}

	/**
	 * Create a webhook secret once when Coolify has not supplied one.
	 */
	private function ensure_webhook_secret(): void {
		$settings = self::settings();
		if ( 'missing' !== $settings['webhook_secret_source'] ) {
			return;
		}

		$encrypted = Power_Schedule_Manager_Secrets::encrypt(
			self::generate_webhook_secret()
		);
		if ( is_wp_error( $encrypted ) ) {
			return;
		}

		$settings['webhook_secret_encrypted'] = $encrypted;
		self::persist_settings( $settings );
	}

	/**
	 * Generate a high-entropy secret suitable for HMAC-SHA256.
	 */
	private static function generate_webhook_secret(): string {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( Throwable ) {
			return wp_generate_password( 64, false, false );
		}
	}

	/**
	 * Persist only real settings, never derived credential-source metadata.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private static function persist_settings( array $settings ): void {
		unset(
			$settings['api_key_source'],
			$settings['webhook_secret_source']
		);
		update_option( self::SETTINGS_OPTION, $settings, false );
	}

	/**
	 * Store a generic provider error code without exposing request details.
	 */
	private function record_error( string $code ): WP_Error {
		$settings = self::settings();
		$settings['last_error_code'] = sanitize_key( $code );
		self::persist_settings( $settings );

		return new WP_Error( $code );
	}

	/**
	 * Verify capability and nonce.
	 */
	private function authorize( string $action ): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die( esc_html__( 'Không đủ quyền.', 'power-schedule-manager' ) );
		}
		check_admin_referer( $action, '_psm_nonce' );
	}

	/**
	 * Redirect to the management screen.
	 */
	private function redirect( string $notice ): never {
		wp_safe_redirect(
			add_query_arg(
				'psm_notice',
				sanitize_key( $notice ),
				admin_url( 'admin.php?page=' . self::MENU_SLUG )
			)
		);
		exit;
	}

	/**
	 * Return a saved integration to the central data-source settings screen.
	 */
	private function redirect_data_sources(
		string $notice,
		string $tab
	): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         =>
						Power_Schedule_Manager_Admin::DATA_SOURCES_SLUG,
					'settings_tab' => sanitize_key( $tab ),
					'psm_notice'   => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function first_scalar(
		array $data,
		array $keys
	): string {
		foreach ( $keys as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				return trim( (string) $data[ $key ] );
			}
		}

		return '';
	}

	private static function first_value(
		array $data,
		array $keys,
		mixed $default
	): mixed {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				return $data[ $key ];
			}
		}

		return $default;
	}

	private static function normalize_date( string $value ): string {
		$value = trim( $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $value, $matches ) ) {
			return $matches[0];
		}
		$date = DateTimeImmutable::createFromFormat( 'd/m/Y', $value );

		return $date instanceof DateTimeImmutable
			? $date->format( 'Y-m-d' )
			: '';
	}

	private static function sanitize_region( mixed $value ): string {
		$value = sanitize_title( is_scalar( $value ) ? (string) $value : '' );
		$map = array(
			'mien-bac'  => 'north',
			'mb'        => 'north',
			'north'     => 'north',
			'mien-trung' => 'central',
			'mt'         => 'central',
			'central'    => 'central',
			'mien-nam'   => 'south',
			'mn'         => 'south',
			'south'      => 'south',
		);

		return $map[ $value ] ?? '';
	}

	/**
	 * Infer the traditional-lottery region from a province or station label.
	 */
	private static function infer_region( mixed $value ): string {
		$label = self::normalize_search_text( $value );
		if ( '' === $label ) {
			return '';
		}

		$regions = array(
			'north' => array(
				'mien bac',
				'ha noi', 'hai phong', 'quang ninh', 'bac ninh',
				'nam dinh', 'thai binh',
			),
			'central' => array(
				'mien trung',
				'quang binh', 'quang tri', 'thua thien hue', 'da nang',
				'quang nam', 'quang ngai', 'binh dinh', 'phu yen',
				'khanh hoa', 'ninh thuan', 'dak lak', 'dak nong',
				'gia lai', 'kon tum',
			),
			'south' => array(
				'mien nam',
				'tp hcm', 'ho chi minh', 'lam dong', 'binh thuan',
				'dong nai', 'binh duong', 'tay ninh', 'long an',
				'tien giang', 'ben tre', 'vinh long', 'tra vinh',
				'can tho', 'hau giang', 'soc trang', 'bac lieu',
				'ca mau', 'kien giang', 'an giang', 'dong thap',
				'ba ria vung tau', 'vung tau', 'binh phuoc',
			),
		);

		foreach ( $regions as $region => $needles ) {
			foreach ( $needles as $needle ) {
				if ( str_contains( $label, $needle ) ) {
					return $region;
				}
			}
		}

		return '';
	}

	/**
	 * Normalize provider labels for stable comparisons.
	 */
	private static function normalize_search_text( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strtolower(
			remove_accents(
				wp_strip_all_tags( (string) $value )
			)
		);
		$value = (string) preg_replace( '/[^a-z0-9]+/', ' ', $value );

		return trim( preg_replace( '/\s+/', ' ', $value ) ?? '' );
	}

	private static function normalize_game_type( mixed $value ): string {
		$raw = is_scalar( $value ) ? (string) $value : '';
		$key = strtolower( remove_accents( trim( $raw ) ) );
		$key = (string) preg_replace( '/[^a-z0-9]+/', '-', $key );
		$key = trim( $key, '-' );
		$aliases = array(
			'' => 'traditional',
			'xsmb' => 'traditional',
			'xsmt' => 'traditional',
			'xsmn' => 'traditional',
			'truyen-thong' => 'traditional',
			'traditional' => 'traditional',
			'vietlott' => 'vietlott',
			'mega-6-45' => 'mega645',
			'mega6-45' => 'mega645',
			'mega6x45' => 'mega645',
			'mega-645' => 'mega645',
			'mega645' => 'mega645',
			'power-6-55' => 'power655',
			'power6-55' => 'power655',
			'power6x55' => 'power655',
			'power-655' => 'power655',
			'power655' => 'power655',
			'max-3d' => 'max3d',
			'max3d' => 'max3d',
			'max3d-plus' => 'max3dplus',
			'max-3d-plus' => 'max3dplus',
			'max3dplus' => 'max3dplus',
			'max-3d-pro' => 'max3dpro',
			'max3dpro' => 'max3dpro',
			'keno' => 'keno',
			'dien-toan' => 'dientoan',
			'dientoan' => 'dientoan',
			'dien-toan-123' => 'dientoan123',
			'dientoan-123' => 'dientoan123',
			'dientoan123' => 'dientoan123',
			'dt123' => 'dientoan123',
			'dien-toan-6x36' => 'dientoan6x36',
			'dientoan-6x36' => 'dientoan6x36',
			'dientoan6x36' => 'dientoan6x36',
			'dt6x36' => 'dientoan6x36',
			'than-tai' => 'thantai',
			'than-tai-4' => 'thantai',
			'thantai' => 'thantai',
			'tt4' => 'thantai',
		);

		if ( isset( $aliases[ $key ] ) ) {
			return $aliases[ $key ];
		}

		$compact = str_replace( '-', '', $key );
		if ( str_contains( $compact, 'mega' ) && str_contains( $compact, '645' ) ) {
			return 'mega645';
		}
		if ( str_contains( $compact, 'power' ) && str_contains( $compact, '655' ) ) {
			return 'power655';
		}
		if ( str_contains( $compact, 'max3dplus' ) ) {
			return 'max3dplus';
		}
		if ( str_contains( $compact, 'max3dpro' ) ) {
			return 'max3dpro';
		}
		if ( str_contains( $compact, 'max3d' ) ) {
			return 'max3d';
		}
		if ( str_contains( $compact, 'keno' ) ) {
			return 'keno';
		}
		if (
			str_contains( $compact, 'dientoan123' )
			|| (
				str_contains( $compact, '123' )
				&& str_contains( $compact, 'dientoan' )
			)
		) {
			return 'dientoan123';
		}
		if (
			str_contains( $compact, 'dientoan6x36' )
			|| str_contains( $compact, '6x36' )
		) {
			return 'dientoan6x36';
		}
		if ( str_contains( $compact, 'thantai' ) ) {
			return 'thantai';
		}

		return $key ?: 'traditional';
	}

	/**
	 * Collect bounded scalar evidence from an API payload.
	 *
	 * Providers do not use a stable field for the lottery product. Some put
	 * it in a parent key, others in a nested title or draw identifier.
	 */
	private static function collect_game_type_evidence(
		array $payload,
		int $depth = 0
	): string {
		if ( $depth > 6 ) {
			return '';
		}

		$parts = array();
		foreach ( $payload as $key => $value ) {
			if ( count( $parts ) >= 180 ) {
				break;
			}
			if ( is_string( $key ) ) {
				$parts[] = mb_substr( $key, 0, 100 );
			}
			if ( is_scalar( $value ) ) {
				$parts[] = mb_substr(
					wp_strip_all_tags( (string) $value ),
					0,
					220
				);
				continue;
			}
			if ( is_array( $value ) ) {
				$child = self::collect_game_type_evidence(
					$value,
					$depth + 1
				);
				if ( '' !== $child ) {
					$parts[] = $child;
				}
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Detect a known product without converting unknown evidence to a slug.
	 */
	private static function detect_game_type_from_evidence(
		string $evidence
	): string {
		$raw_evidence = strtolower( remove_accents( $evidence ) );
		if (
			1 === preg_match(
				'/max\\s*[-_]?\\s*3d\\s*(?:\\+|plus|cong)/i',
				$raw_evidence
			)
		) {
			return 'max3dplus';
		}
		$compact = str_replace(
			' ',
			'',
			self::normalize_search_text( $evidence )
		);

		$checks = array(
			'max3dplus' => array( 'max3dplus', 'max3d+' ),
			'max3dpro' => array( 'max3dpro' ),
			'power655' => array( 'power655', 'power6x55' ),
			'mega645' => array( 'mega645', 'mega6x45' ),
			'dientoan6x36' => array( 'dientoan6x36', '6x36' ),
			'dientoan123' => array( 'dientoan123', 'dt123' ),
			'thantai' => array( 'thantai4', 'thantai', 'tt4' ),
			'max3d' => array( 'max3d' ),
			'keno' => array( 'vietlottkeno', 'keno' ),
		);

		foreach ( $checks as $type => $needles ) {
			foreach ( $needles as $needle ) {
				if ( str_contains( $compact, $needle ) ) {
					return $type;
				}
			}
		}

		return '';
	}

	private static function preset_game_types( string $preset ): array {
		return match ( $preset ) {
			'mega645' => array( 'mega645' ),
			'power655' => array( 'power655' ),
			'max3d' => array( 'max3d' ),
			'max3dplus' => array( 'max3dplus', 'max3d' ),
			'max3dpro' => array( 'max3dpro' ),
			'keno' => array( 'keno' ),
			'keno_history' => array( 'keno' ),
			'dientoan' => array(
				'dientoan',
				'dientoan123',
				'dientoan6x36',
				'thantai',
			),
			'dientoan123' => array( 'dientoan123' ),
			'dientoan6x36' => array( 'dientoan6x36' ),
			'thantai' => array( 'thantai' ),
			default => array(),
		};
	}

	private static function region_label( string $region ): string {
		return match ( $region ) {
			'north' => __( 'Miền Bắc', 'power-schedule-manager' ),
			'central' => __( 'Miền Trung', 'power-schedule-manager' ),
			'south' => __( 'Miền Nam', 'power-schedule-manager' ),
			default => __( 'Toàn quốc', 'power-schedule-manager' ),
		};
	}

	/**
	 * Normalize provider number arrays and delimited number strings.
	 *
	 * @return array<int,string>
	 */
	private static function normalize_number_values( mixed $value ): array {
		$values = array();
		if ( is_array( $value ) ) {
			array_walk_recursive(
				$value,
				static function ( mixed $item ) use ( &$values ): void {
					if ( is_scalar( $item ) ) {
						$clean = trim( (string) $item );
						if ( '' !== $clean ) {
							$values[] = $clean;
						}
					}
				}
			);
		} elseif ( is_scalar( $value ) ) {
			$parts = preg_split(
				'/[\s,;|·\-]+/u',
				trim( (string) $value )
			);
			if ( is_array( $parts ) ) {
				$values = $parts;
			}
		}

		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $item ): string =>
						trim( sanitize_text_field( (string) $item ) ),
					$values
				),
				static fn ( string $item ): bool =>
					'' !== $item
						&& 1 === preg_match( '/\A\d{1,6}\z/', $item )
			)
		);
	}

	private static function clean_text(
		mixed $value,
		int $length
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return mb_substr(
			sanitize_text_field( (string) $value ),
			0,
			$length
		);
	}

	private static function extract_special_prize(
		array $draw,
		array $results
	): string {
		if ( array_is_list( $results ) ) {
			foreach ( $results as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$code = strtoupper(
					sanitize_key(
						(string) (
							$item['prizeCode']
							?? $item['prize_code']
							?? ''
						)
					)
				);
				if ( 'DB' === $code ) {
					$value = $item['value'] ?? null;
					if (
						null === $value
						&& isset( $item['values'] )
						&& is_array( $item['values'] )
					) {
						$value = reset( $item['values'] );
					}
					if ( null !== $value ) {
						return self::clean_text( $value, 191 );
					}
				}
			}
		}
		$value = self::first_value(
			$draw,
			array( 'special_prize', 'giai_dac_biet' ),
			self::first_value(
				$results,
				array(
					'special',
					'special_prize',
					'giai_dac_biet',
					'db',
				),
				''
			)
		);
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return self::clean_text( $value, 191 );
	}

	private static function prize_label( string $key ): string {
		$normalized = sanitize_key( $key );
		$labels = array(
			'db'            => 'Đặc biệt',
			'special'       => 'Đặc biệt',
			'special_prize' => 'Đặc biệt',
			'giai_dac_biet' => 'Đặc biệt',
			'giai_1'        => 'Giải nhất',
			'giai_2'        => 'Giải nhì',
			'giai_3'        => 'Giải ba',
			'giai_4'        => 'Giải tư',
			'giai_5'        => 'Giải năm',
			'giai_6'        => 'Giải sáu',
			'giai_7'        => 'Giải bảy',
			'giai_8'        => 'Giải tám',
			'g1'            => 'Giải nhất',
			'g2'            => 'Giải nhì',
			'g3'            => 'Giải ba',
			'g4'            => 'Giải tư',
			'g5'            => 'Giải năm',
			'g6'            => 'Giải sáu',
			'g7'            => 'Giải bảy',
			'g8'            => 'Giải tám',
		);

		return $labels[ $normalized ] ?? sanitize_text_field( $key );
	}
}
