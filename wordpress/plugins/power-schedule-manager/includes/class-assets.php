<?php
/**
 * Public asset management.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues frontend assets.
 */
final class Power_Schedule_Manager_Assets {

	/**
	 * Frontend stylesheet handle.
	 */
	public const string FRONTEND_STYLE =
		'power-schedule-manager-frontend';

	/**
	 * Frontend script handle.
	 */
	public const string FRONTEND_SCRIPT =
		'power-schedule-manager-frontend';

	/** Final layout contract, loaded after all optional component styles. */
	public const string FRONTEND_LAYOUT_STYLE =
		'power-schedule-manager-frontend-layout';

	/** Content frame contract, loaded after the layout and component styles. */
	public const string FRONTEND_CONTENT_STYLE =
		'power-schedule-manager-frontend-content';

	/** Small final component fixes loaded only with plugin frontend output. */
	public const string FRONTEND_REFINEMENTS_STYLE =
		'power-schedule-manager-frontend-refinements';

	/**
	 * OneSignal Web SDK handle.
	 */
	public const string PUSH_SCRIPT =
		'power-schedule-manager-onesignal';

	/**
	 * Floating push controller handles.
	 */
	public const string PUSH_CONTROLLER =
		'power-schedule-manager-push';

	public const string PUSH_STYLE =
		'power-schedule-manager-push';

	/**
	 * Whether public assets have been registered.
	 */
	private static bool $public_registered = false;

	/**
	 * Whether public assets have been enqueued.
	 */
	private static bool $public_enqueued = false;

	/** @var array<string,bool> Production component styles already enqueued. */
	private static array $component_styles = array();

	private const array STYLE_COMPONENTS = array(
		'schedule', 'lottery', 'market', 'weather', 'portal', 'community',
	);

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'wp_enqueue_scripts',
			array( self::class, 'register_public_assets' ),
			5
		);

		add_action(
			'wp_enqueue_scripts',
			array( self::class, 'maybe_enqueue_shortcode_assets' ),
			20
		);

		add_action(
			'wp_enqueue_scripts',
			array( self::class, 'enqueue_global_push_assets' ),
			25
		);

		add_action(
			'wp_footer',
			array( self::class, 'render_push_control' ),
			40
		);
	}

	/**
	 * Register frontend assets.
	 *
	 * Registration is idempotent and may safely be called during shortcode
	 * rendering if wp_enqueue_scripts has already fired.
	 *
	 * @return void
	 */
	public static function register_public_assets(): void {
		if (
			self::$public_registered
			&& wp_style_is( self::FRONTEND_STYLE, 'registered' )
			&& wp_script_is( self::FRONTEND_SCRIPT, 'registered' )
		) {
			return;
		}

		$debug_assets = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$css_file = $debug_assets ? 'frontend.css' : 'frontend.min.css';
		$js_file = $debug_assets ? 'frontend.js' : 'frontend.min.js';
		$css_path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $css_file;
		$js_path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $js_file;

		wp_register_style(
			self::FRONTEND_STYLE,
			POWER_SCHEDULE_MANAGER_URL
				. 'public/assets/' . $css_file,
			array(),
			self::asset_version( $css_path ),
			'all'
		);

		$refinements_file = $debug_assets
			? 'frontend-refinements.css'
			: 'frontend-refinements.min.css';
		$refinements_path = POWER_SCHEDULE_MANAGER_PATH
			. 'public/assets/' . $refinements_file;
		wp_register_style(
			self::FRONTEND_REFINEMENTS_STYLE,
			POWER_SCHEDULE_MANAGER_URL . 'public/assets/' . $refinements_file,
			array( self::FRONTEND_STYLE ),
			self::asset_version( $refinements_path )
		);

		if ( ! $debug_assets ) {
			foreach ( self::STYLE_COMPONENTS as $component ) {
				$file = 'frontend-' . $component . '.min.css';
				$path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $file;
				if ( is_file( $path ) ) {
					wp_register_style(
						self::FRONTEND_STYLE . '-' . $component,
						POWER_SCHEDULE_MANAGER_URL . 'public/assets/' . $file,
						array( self::FRONTEND_STYLE ),
						self::asset_version( $path )
					);
				}
			}
		}

		wp_register_script(
			self::FRONTEND_SCRIPT,
			POWER_SCHEDULE_MANAGER_URL
				. 'public/assets/' . $js_file,
			array(),
			self::asset_version( $js_path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		self::$public_registered = true;
	}

	/**
	 * Enqueue the scoped stylesheet in the document head, then add the script
	 * when the queried post contains one of the plugin shortcodes.
	 *
	 * Both files remain absent from unrelated pages. A render-time fallback
	 * covers dynamic widgets and template parts that are not visible here.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_shortcode_assets(): void {
		if ( is_admin() ) {
			return;
		}

		self::register_public_assets();

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if (
			! $post instanceof WP_Post
		) {
			return;
		}

		$page_presentation = 'page' === $post->post_type
			? get_post_meta( $post->ID, '_psm_page_presentation', true )
			: array();
		if (
			is_array( $page_presentation )
			&& 'plugin' === (string) ( $page_presentation['owner'] ?? '' )
		) {
			self::enqueue_public_assets();
			return;
		}

		if ( '' === trim( $post->post_content ) ) {
			return;
		}

		$shortcodes = array(
			Power_Schedule_Manager_Content_Blocks::SHORTCODE,
			'power_schedule_lottery',
			'power_schedule_lottery_latest',
			'power_schedule_lottery_results',
			'power_schedule_lottery_overview',
			'power_schedule_lottery_history',
			'power_schedule_lottery_archive',
			'power_schedule_lottery_special_week',
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
			'power_schedule_lottery_dientoan123_history',
			'power_schedule_lottery_dientoan6x36_history',
			'power_schedule_lottery_thantai_history',
			'power_schedule_lottery_dientoan',
			'power_schedule_lottery_dientoan123',
			'power_schedule_lottery_dientoan6x36',
			'power_schedule_lottery_thantai',
			'power_schedule_market_prices',
			'power_schedule_coffee_lam_dong',
			'power_schedule_coffee_domestic',
			'power_schedule_coffee_dak_lak',
			'power_schedule_coffee_gia_lai',
			'power_schedule_coffee_dak_nong',
			'power_schedule_coffee_futures',
			'power_schedule_coffee_live',
			'power_schedule_coffee_robusta',
			'power_schedule_coffee_arabica',
			'power_schedule_pepper_prices',
			'power_schedule_usd_vnd',
			'power_schedule_exchange_rates',
			'power_schedule_exchange_rates_vcb',
			'power_schedule_exchange_rates_ctg',
			'power_schedule_exchange_rates_tcb',
			'power_schedule_exchange_rates_bidv',
			'power_schedule_exchange_rates_stb',
			'power_schedule_exchange_rates_sbv',
			'power_schedule_gold_prices',
			'power_schedule_gold_world',
			'power_schedule_gold_domestic',
			'power_schedule_gold_api',
			'power_schedule_gold_vnappmob_sjc',
			'power_schedule_gold_vnappmob_doji',
			'power_schedule_gold_vnappmob_pnj',
			'power_schedule_coffee_prices',
			'power_schedule_coffee_overview',
			'power_schedule_gold_comparison',
			'power_schedule_gold_overview',
			'power_schedule_market_iframe',
			'power_schedule_gold_iframe',
			'power_schedule_weather',
			'power_schedule_weather_forecast',
			'power_schedule_weather_rain',
			'power_schedule_weather_wind',
			'power_schedule_weather_temperature',
			'power_schedule_weather_clouds',
			'power_schedule_weather_snow',
			'power_schedule_weather_thunderstorms',
		);

		if ( class_exists( 'Power_Schedule_Manager_Shortcodes' ) ) {
			$shortcodes = array_merge(
				$shortcodes,
				Power_Schedule_Manager_Shortcodes::tags()
			);
		}

		if ( class_exists( 'Power_Schedule_Manager_Sponsorship' ) ) {
			$shortcodes[] =
				Power_Schedule_Manager_Sponsorship::SPONSOR_SHORTCODE;
		}

		$shortcodes = array_unique(
			array_filter(
				$shortcodes,
				'is_string'
			)
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				self::enqueue_public_assets();
				return;
			}
		}
	}

	/**
	 * Enqueue public assets.
	 *
	 * This method is called by shortcodes and public templates only when
	 * plugin output is present.
	 *
	 * @return void
	 */
	public static function enqueue_public_assets( string|array|null $components = null ): void {

		self::register_public_assets();

		if (
			! wp_style_is(
				self::FRONTEND_STYLE,
				'registered'
			)
			|| ! wp_script_is(
				self::FRONTEND_SCRIPT,
				'registered'
			)
		) {
			return;
		}

		wp_enqueue_style(
			self::FRONTEND_STYLE
		);

		$components = null === $components
			? self::detect_style_components()
			: (array) $components;
		foreach ( array_unique( array_map( 'sanitize_key', $components ) ) as $component ) {
			if ( ! in_array( $component, self::STYLE_COMPONENTS, true ) ) {
				continue;
			}
			$handle = self::FRONTEND_STYLE . '-' . $component;
			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
				self::$component_styles[ $component ] = true;
			}
		}

		if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$layout_file = 'frontend-layout.min.css';
			$layout_path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $layout_file;
			if ( is_file( $layout_path ) ) {
				$dependencies = array( self::FRONTEND_STYLE );
				foreach ( array_keys( self::$component_styles ) as $component ) {
					$dependencies[] = self::FRONTEND_STYLE . '-' . $component;
				}
				wp_register_style(
					self::FRONTEND_LAYOUT_STYLE,
					POWER_SCHEDULE_MANAGER_URL . 'public/assets/' . $layout_file,
					array_values( array_unique( $dependencies ) ),
					self::asset_version( $layout_path )
				);
				wp_enqueue_style( self::FRONTEND_LAYOUT_STYLE );

				$content_file = 'frontend-content.min.css';
				$content_path = POWER_SCHEDULE_MANAGER_PATH
					. 'public/assets/' . $content_file;
				if ( is_file( $content_path ) ) {
					wp_register_style(
						self::FRONTEND_CONTENT_STYLE,
						POWER_SCHEDULE_MANAGER_URL
							. 'public/assets/' . $content_file,
						array( self::FRONTEND_LAYOUT_STYLE ),
						self::asset_version( $content_path )
					);
					wp_enqueue_style( self::FRONTEND_CONTENT_STYLE );
				}
			}
		}

		wp_enqueue_style( self::FRONTEND_REFINEMENTS_STYLE );

		if ( ! self::$public_enqueued ) {
			wp_enqueue_script( self::FRONTEND_SCRIPT );
		}

		/*
		 * A shortcode inside a widget, a template part, or a dynamic block can
		 * be rendered after wp_head. Ensure its stylesheet is still printed
		 * instead of leaving the HTML unstyled for the whole request.
		 */
		if ( did_action( 'wp_head' ) ) {
			add_action(
				'wp_footer',
				array( self::class, 'print_late_public_style' ),
				1
			);
		}

		if ( ! self::$public_enqueued ) {
			wp_localize_script(
				self::FRONTEND_SCRIPT,
				'PowerScheduleManager',
				self::frontend_configuration()
			);
		}

		self::$public_enqueued = true;
	}

	/** Determine the smallest stylesheet set needed before the content renders. */
	private static function detect_style_components(): array {
		$components = array();
		if (
			is_singular( Power_Schedule_Manager_Post_Type::POST_TYPE )
			|| is_post_type_archive( Power_Schedule_Manager_Post_Type::POST_TYPE )
			|| is_tax( Power_Schedule_Manager_Taxonomy::TAXONOMY )
		) {
			$components[] = 'schedule';
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $components;
		}
		$presentation = 'page' === $post->post_type
			? get_post_meta( $post->ID, '_psm_page_presentation', true ) : array();
		if ( is_array( $presentation ) && 'plugin' === (string) ( $presentation['owner'] ?? '' ) ) {
			$preset = (string) ( $presentation['preset'] ?? 'schedule' );
			$components[] = match ( $preset ) {
				'lottery', 'lottery_lookup' => 'lottery',
				'gold', 'coffee'             => 'market',
				'weather'                    => 'weather',
				'participate', 'sponsor'     => 'community',
				default                      => 'schedule',
			};
		}
		$content = strtolower( (string) $post->post_content );
		$markers = array(
			'core'      => array( '[power_schedule_page_hero' ),
			'lottery'   => array( '[power_schedule_lottery' ),
			'market'    => array( '[power_schedule_market', '[power_schedule_gold', '[power_schedule_coffee', '[power_schedule_pepper', '[power_schedule_exchange', '[power_schedule_usd_vnd' ),
			'weather'   => array( '[power_schedule_weather' ),
			'portal'    => array( '[power_schedule_home' ),
			'community' => array( '[power_schedule_sponsor', '[power_schedule_participate' ),
			'schedule'  => array( '[power_schedule]', '[power_schedule ', '[power_schedule_search', '[power_schedule_areas', '[power_schedule_next', '[power_schedule_alert', '[power_schedule_days', '[power_schedule_recent_updates', '[power_schedule_content' ),
		);
		foreach ( $markers as $component => $needles ) {
			foreach ( $needles as $needle ) {
				if ( str_contains( $content, $needle ) ) {
					$components[] = $component;
					break;
				}
			}
		}
		if ( in_array( 'portal', $components, true ) ) {
			$components[] = 'schedule';
		}
		return array_values( array_unique( $components ) );
	}

	/** Whether the current public request contains a plugin-owned interface. */
	public static function request_has_plugin_surface(): bool {
		return array() !== self::detect_style_components();
	}

	/**
	 * Print the frontend stylesheet for content rendered after wp_head.
	 *
	 * @return void
	 */
	public static function print_late_public_style(): void {
		$handles = array( self::FRONTEND_STYLE );
		foreach ( array_keys( self::$component_styles ) as $component ) {
			$handles[] = self::FRONTEND_STYLE . '-' . $component;
		}
		if ( wp_style_is( self::FRONTEND_LAYOUT_STYLE, 'registered' ) ) {
			$handles[] = self::FRONTEND_LAYOUT_STYLE;
		}
		$pending = array_values(
			array_filter(
				$handles,
				static fn ( string $handle ): bool => wp_style_is( $handle, 'enqueued' )
					&& ! wp_style_is( $handle, 'done' )
			)
		);
		if ( array() !== $pending ) {
			wp_print_styles( $pending );
		}
	}

	/**
	 * Build safe frontend JavaScript configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function frontend_configuration(): array {
		$map = self::map_configuration();

		return array(
			'restRoot' => esc_url_raw(
				rest_url(
					'power-schedule-manager/v1/'
				)
			),
			'map' => array(
				'enabled'     => $map['enabled'],
				'provider'    => $map['provider'],
				'tileUrl'     => $map['tile_url'],
				'attribution' => $map['attribution'],
				'maxZoom'     => $map['max_zoom'],
				'tileSize'    => $map['tile_size'],
				'zoomOffset'  => $map['zoom_offset'],
				'crossOrigin' => $map['cross_origin'],
				'leafletJs'   => esc_url_raw(
					POWER_SCHEDULE_MANAGER_URL
						. 'public/assets/vendor/leaflet/leaflet.js'
				),
				'leafletCss'  => esc_url_raw(
					POWER_SCHEDULE_MANAGER_URL
						. 'public/assets/vendor/leaflet/leaflet.css'
				),
			),
			'strings' => array(
				'loading' => __(
					'Đang tải bản đồ…',
					'power-schedule-manager'
				),
				'loadError' => __(
					'Không thể tải dữ liệu bản đồ.',
					'power-schedule-manager'
				),
				'emptyMap' => __(
					'Chưa có dữ liệu bản đồ cho khu vực này.',
					'power-schedule-manager'
				),
				'closeMap' => __(
					'Đóng bản đồ',
					'power-schedule-manager'
				),
				'mapLabel' => __(
					'Bản đồ khu vực có lịch cúp điện',
					'power-schedule-manager'
				),
			),
		);
	}

	/**
	 * Whether public browser push is fully configured.
	 */
	public static function push_enabled(): bool {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);

		if (
			! is_array( $settings )
			|| empty( $settings['push_enabled'] )
		) {
			return false;
		}

		return 1 === preg_match(
			'/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i',
			self::onesignal_app_id()
		);
	}

	/**
	 * Return the configured public OneSignal App ID.
	 *
	 * The database setting wins. The build-level constant is a safe fallback
	 * for existing installations whose saved settings predate this release.
	 */
	public static function onesignal_app_id(): string {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$app_id = is_array( $settings )
			? sanitize_text_field(
				(string) ( $settings['push_onesignal_app_id'] ?? '' )
			)
			: '';

		if (
			1 === preg_match(
				'/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/i',
				$app_id
			)
		) {
			return strtolower( $app_id );
		}

		$fallback = defined(
			'POWER_SCHEDULE_MANAGER_ONESIGNAL_APP_ID'
		)
			? sanitize_text_field(
				(string) POWER_SCHEDULE_MANAGER_ONESIGNAL_APP_ID
			)
			: '';

		return 1 === preg_match(
			'/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/i',
			$fallback
		)
			? strtolower( $fallback )
			: '';
	}

	/**
	 * Public URL of the dedicated OneSignal worker.
	 */
	public static function onesignal_worker_url(): string {
		return POWER_SCHEDULE_MANAGER_URL
			. 'public/push/OneSignalSDKWorker.js';
	}

	/**
	 * Scope dedicated to OneSignal so it cannot replace the PWA worker.
	 */
	public static function onesignal_worker_scope(): string {
		$path = wp_parse_url(
			POWER_SCHEDULE_MANAGER_URL . 'public/push/',
			PHP_URL_PATH
		);

		return is_string( $path ) && '' !== $path
			? trailingslashit( $path )
			: '/';
	}

	/**
	 * Load the lightweight push controller.
	 *
	 * The OneSignal SDK is deliberately not enqueued here. It is a sizeable
	 * third-party script and is fetched by the controller only after a visitor
	 * explicitly opens the notification preferences. This keeps it out of the
	 * critical page request and, importantly, never triggers a Slidedown prompt.
	 */
	public static function enqueue_global_push_assets(): void {
		if ( is_admin() || ! self::push_enabled() || ! self::request_has_plugin_surface() ) {
			return;
		}

		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$settings = is_array( $settings ) ? $settings : array();
		$app_id = self::onesignal_app_id();
		$worker_url = self::onesignal_worker_url();
		$worker_path = ltrim(
			(string) wp_make_link_relative( $worker_url ),
			'/'
		);
		$worker_scope = self::onesignal_worker_scope();

		$debug_assets = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$controller_file = $debug_assets ? 'push.js' : 'push.min.js';
		$style_file = $debug_assets ? 'push.css' : 'push.min.css';
		$controller_path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $controller_file;
		$style_path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $style_file;

		wp_enqueue_style(
			self::PUSH_STYLE,
			POWER_SCHEDULE_MANAGER_URL . 'public/assets/' . $style_file,
			array(),
			self::asset_version( $style_path )
		);
		wp_enqueue_script(
			self::PUSH_CONTROLLER,
			POWER_SCHEDULE_MANAGER_URL . 'public/assets/' . $controller_file,
			array(),
			self::asset_version( $controller_path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			self::PUSH_CONTROLLER,
			'PowerScheduleManagerPush',
			array(
				'enabled' => true,
				'sdkUrl'  => 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js',
				'init'    => array(
					'appId'              => $app_id,
					'safari_web_id'      => defined(
						'POWER_SCHEDULE_MANAGER_ONESIGNAL_SAFARI_WEB_ID'
					)
						? (string) POWER_SCHEDULE_MANAGER_ONESIGNAL_SAFARI_WEB_ID
						: '',
					'serviceWorkerPath'  => $worker_path,
					'serviceWorkerParam' => array(
						'scope' => $worker_scope,
					),
					'autoResubscribe'     => true,
					'notifyButton'        => array( 'enable' => false ),
				),
				'units'   => array_values(
					array_map(
						static fn ( array $unit ): array => array(
							'code' => (string) $unit['code'],
							'name' => (string) $unit['name'],
						),
						Power_Schedule_Manager_Units::all( true )
					)
				),
				'lotteryTopics' => array(
					array( 'code' => 'all', 'name' => __( 'Tất cả kết quả xổ số', 'power-schedule-manager' ) ),
					array( 'code' => 'north', 'name' => __( 'Xổ số Miền Bắc', 'power-schedule-manager' ) ),
					array( 'code' => 'central', 'name' => __( 'Xổ số Miền Trung', 'power-schedule-manager' ) ),
					array( 'code' => 'south', 'name' => __( 'Xổ số Miền Nam', 'power-schedule-manager' ) ),
					array( 'code' => 'mega645', 'name' => __( 'Mega 6/45', 'power-schedule-manager' ) ),
					array( 'code' => 'power655', 'name' => __( 'Power 6/55', 'power-schedule-manager' ) ),
					array( 'code' => 'max3d', 'name' => __( 'Max 3D', 'power-schedule-manager' ) ),
					array( 'code' => 'max3dplus', 'name' => __( 'Max 3D+', 'power-schedule-manager' ) ),
					array( 'code' => 'max3dpro', 'name' => __( 'Max 3D Pro', 'power-schedule-manager' ) ),
					array( 'code' => 'keno', 'name' => __( 'Keno', 'power-schedule-manager' ) ),
					array( 'code' => 'dientoan123', 'name' => __( 'Điện toán 123', 'power-schedule-manager' ) ),
					array( 'code' => 'dientoan6x36', 'name' => __( 'Điện toán 6x36', 'power-schedule-manager' ) ),
					array( 'code' => 'thantai', 'name' => __( 'Thần Tài 4', 'power-schedule-manager' ) ),
				),
				'label'   => sanitize_text_field(
					(string) (
						$settings['push_button_label']
							?? 'Nhận thông báo lịch cúp điện'
					)
				),
				'strings' => array(
					'enable'      => __(
						'Bật thông báo lịch cúp điện',
						'power-schedule-manager'
					),
					'disable'     => __(
						'Tắt thông báo lịch cúp điện',
						'power-schedule-manager'
					),
					'enabled'     => __(
						'Đã bật thông báo trên thiết bị này.',
						'power-schedule-manager'
					),
					'disabled'    => __(
						'Đã tắt thông báo trên thiết bị này.',
						'power-schedule-manager'
					),
					'blocked'     => __(
						'Thông báo đang bị trình duyệt chặn. Hãy cho phép trong cài đặt website.',
						'power-schedule-manager'
					),
					'unsupported' => __(
						'Trình duyệt này chưa hỗ trợ thông báo web.',
						'power-schedule-manager'
					),
					'loading'     => __(
						'Đang kết nối dịch vụ thông báo…',
						'power-schedule-manager'
					),
					'installFirst' => __(
						'Trên iPhone/iPad, hãy thêm website vào Màn hình chính rồi mở ứng dụng để bật thông báo.',
						'power-schedule-manager'
					),
					'error'       => __(
						'Chưa thể cập nhật thông báo. Vui lòng thử lại.',
						'power-schedule-manager'
					),
					'chooseAreas' => __(
						'Chọn khu vực bạn muốn nhận thông báo.',
						'power-schedule-manager'
					),
					'chooseOneArea' => __(
						'Hãy chọn ít nhất một khu vực.',
						'power-schedule-manager'
					),
					'saveAreas' => __(
						'Lưu khu vực và nhận thông báo',
						'power-schedule-manager'
					),
					'savedAreas' => __(
						'Đã lưu khu vực theo dõi trên thiết bị này.',
						'power-schedule-manager'
					),
					'preferences' => __(
						'Tùy chọn thông báo',
						'power-schedule-manager'
					),
					'close' => __( 'Đóng', 'power-schedule-manager' ),
				),
			)
		);
	}

	/**
	 * Render one accessible floating bell above the theme scroll-to-top control.
	 */
	public static function render_push_control(): void {
		if ( is_admin() || ! self::push_enabled() || ! self::request_has_plugin_surface() ) {
			return;
		}
		?>
		<div class="psm-push-fab" data-psm-push-root>
			<p class="psm-push-fab__message" data-psm-push-status role="status" aria-live="polite"></p>
			<section class="psm-push-fab__preferences" data-psm-push-preferences hidden aria-label="<?php esc_attr_e( 'Tùy chọn thông báo', 'power-schedule-manager' ); ?>">
				<div class="psm-push-fab__preferences-heading">
					<strong><?php esc_html_e( 'Theo dõi khu vực', 'power-schedule-manager' ); ?></strong>
					<button type="button" class="psm-push-fab__close" data-psm-push-close aria-label="<?php esc_attr_e( 'Đóng', 'power-schedule-manager' ); ?>">×</button>
				</div>
				<p><?php esc_html_e( 'Chọn nội dung bạn muốn theo dõi trên thiết bị này.', 'power-schedule-manager' ); ?></p>
				<strong class="psm-push-fab__group-title"><?php esc_html_e( 'Lịch cúp điện', 'power-schedule-manager' ); ?></strong>
				<div class="psm-push-fab__areas" data-psm-push-areas></div>
				<strong class="psm-push-fab__group-title"><?php esc_html_e( 'Kết quả xổ số', 'power-schedule-manager' ); ?></strong>
				<div class="psm-push-fab__areas" data-psm-push-lottery></div>
				<button type="button" class="psm-push-fab__save" data-psm-push-save>
					<?php esc_html_e( 'Lưu khu vực và nhận thông báo', 'power-schedule-manager' ); ?>
				</button>
			</section>
			<button
				type="button"
				class="psm-push-fab__button"
				data-psm-push-subscribe
				aria-label="<?php esc_attr_e( 'Bật thông báo lịch cúp điện', 'power-schedule-manager' ); ?>"
				title="<?php esc_attr_e( 'Nhận thông báo lịch cúp điện', 'power-schedule-manager' ); ?>"
			>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
				</svg>
				<span class="psm-push-fab__state" aria-hidden="true"></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Return normalized map-provider configuration.
	 *
	 * Supported providers:
	 * - osm
	 * - maptiler
	 * - stadia
	 * - custom
	 * - disabled
	 *
	 * @return array{
	 *     enabled: bool,
	 *     provider: string,
	 *     tile_url: string,
	 *     attribution: string,
	 *     max_zoom: int,
	 *     tile_size: int,
	 *     zoom_offset: int,
	 *     cross_origin: bool
	 * }
	 */
	public static function map_configuration(): array {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return self::provider_configuration( $settings );
	}

	/**
	 * Normalize one map-provider configuration.
	 *
	 * Provider credentials are deliberately read from server constants. Public
	 * browser tokens remain visible in tile requests, but are never copied into
	 * the WordPress options table or plugin backups.
	 *
	 * @param array<string,mixed> $settings Provider settings.
	 * @return array{
	 *     enabled: bool,
	 *     provider: string,
	 *     tile_url: string,
	 *     attribution: string,
	 *     max_zoom: int,
	 *     tile_size: int,
	 *     zoom_offset: int,
	 *     cross_origin: bool
	 * }
	 */
	public static function provider_configuration( array $settings ): array {
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

		if ( 'disabled' === $provider ) {
			return array(
				'enabled'      => false,
				'provider'     => 'disabled',
				'tile_url'     => '',
				'attribution'  => '',
				'max_zoom'     => 18,
				'tile_size'    => 256,
				'zoom_offset'  => 0,
				'cross_origin' => false,
			);
		}

		if ( 'maptiler' === $provider ) {
			$key = Power_Schedule_Manager_Secrets::resolve(
				'POWER_SCHEDULE_MANAGER_MAPTILER_KEY',
				(string) (
					$settings['maptiler_key_encrypted']
					?? ''
				)
			);
			$style = self::sanitize_maptiler_style(
				$settings['maptiler_style'] ?? 'streets-v4'
			);

			if ( '' === $key || '' === $style ) {
				return self::disabled_map_configuration();
			}

			return array(
				'enabled'      => true,
				'provider'     => 'maptiler',
				'tile_url'     => 'https://api.maptiler.com/maps/'
					. $style
					. '/256/{z}/{x}/{y}.png?key='
					. rawurlencode( $key ),
				'attribution'  =>
					'&copy; <a href="https://www.maptiler.com/copyright/" target="_blank" rel="noopener noreferrer">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
				'max_zoom'     => self::sanitize_max_zoom(
					$settings['map_max_zoom'] ?? 19
				),
				'tile_size'    => 256,
				'zoom_offset'  => 0,
				'cross_origin' => true,
			);
		}

		if ( 'stadia' === $provider ) {
			$key = Power_Schedule_Manager_Secrets::resolve(
				'POWER_SCHEDULE_MANAGER_STADIA_KEY',
				(string) (
					$settings['stadia_key_encrypted']
					?? ''
				)
			);
			$style = self::sanitize_stadia_style(
				$settings['stadia_style'] ?? 'alidade_smooth'
			);

			if ( '' === $key || '' === $style ) {
				return self::disabled_map_configuration();
			}

			return array(
				'enabled'      => true,
				'provider'     => 'stadia',
				'tile_url'     => 'https://tiles.stadiamaps.com/tiles/'
					. $style
					. '/{z}/{x}/{y}.png?api_key='
					. rawurlencode( $key ),
				'attribution'  =>
					'&copy; <a href="https://stadiamaps.com/" target="_blank" rel="noopener noreferrer">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank" rel="noopener noreferrer">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
				'max_zoom'     => self::sanitize_max_zoom(
					$settings['map_max_zoom'] ?? 20
				),
				'tile_size'    => 256,
				'zoom_offset'  => 0,
				'cross_origin' => true,
			);
		}

		if ( 'custom' === $provider ) {
			$tile_url = isset( $settings['map_tile_url'] )
				&& is_string( $settings['map_tile_url'] )
				? trim( $settings['map_tile_url'] )
				: '';

			if ( ! self::is_valid_tile_template( $tile_url ) ) {
				return array(
					'enabled'      => false,
					'provider'     => 'disabled',
					'tile_url'     => '',
					'attribution'  => '',
					'max_zoom'     => 18,
					'tile_size'    => 256,
					'zoom_offset'  => 0,
					'cross_origin' => false,
				);
			}

			$attribution = isset( $settings['map_attribution'] )
				&& is_string( $settings['map_attribution'] )
				? wp_kses(
					$settings['map_attribution'],
					array(
						'a' => array(
							'href'   => true,
							'target' => true,
							'rel'    => true,
						),
					)
				)
				: '';

			return array(
				'enabled'      => true,
				'provider'     => 'custom',
				'tile_url'     => $tile_url,
				'attribution'  => $attribution,
				'max_zoom'     => self::sanitize_max_zoom(
					$settings['map_max_zoom'] ?? 18
				),
				'tile_size'    => 256,
				'zoom_offset'  => 0,
				'cross_origin' => true,
			);
		}

		return array(
			'enabled'      => true,
			'provider'     => 'osm',
			'tile_url'     =>
				'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
			'attribution'  =>
				'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>',
			'max_zoom'     => self::sanitize_max_zoom(
				$settings['map_max_zoom'] ?? 19
			),
			'tile_size'    => 256,
			'zoom_offset'  => 0,
			'cross_origin' => true,
		);
	}

	/**
	 * Return a consistent disabled-provider payload.
	 *
	 * @return array<string,mixed>
	 */
	private static function disabled_map_configuration(): array {
		return array(
			'enabled'      => false,
			'provider'     => 'disabled',
			'tile_url'     => '',
			'attribution'  => '',
			'max_zoom'     => 18,
			'tile_size'    => 256,
			'zoom_offset'  => 0,
			'cross_origin' => false,
		);
	}

	/**
	 * Validate a MapTiler map ID.
	 */
	public static function sanitize_maptiler_style( mixed $value ): string {
		$style = sanitize_text_field( (string) $value );

		return 1 === preg_match(
			'/\A[A-Za-z0-9_-]+\z/',
			$style
		)
			? $style
			: '';
	}

	/**
	 * Validate a Stadia Maps raster style.
	 */
	public static function sanitize_stadia_style( mixed $value ): string {
		$style = sanitize_key( (string) $value );
		$allowed = array(
			'alidade_smooth',
			'alidade_smooth_dark',
			'outdoors',
			'osm_bright',
			'stamen_toner_lite',
			'stamen_terrain',
		);

		return in_array( $style, $allowed, true )
			? $style
			: 'alidade_smooth';
	}

	/**
	 * Validate an HTTPS tile URL template.
	 *
	 * Required placeholders:
	 * - {z}
	 * - {x}
	 * - {y}
	 *
	 * @param string $template Tile template.
	 *
	 * @return bool
	 */
	private static function is_valid_tile_template(
		string $template
	): bool {
		if (
			'' === $template
			|| strlen( $template ) > 2048
			|| ! str_starts_with( $template, 'https://' )
			|| ! str_contains( $template, '{z}' )
			|| ! str_contains( $template, '{x}' )
			|| ! str_contains( $template, '{y}' )
			|| str_contains( $template, '"' )
			|| str_contains( $template, "'" )
			|| str_contains( $template, '<' )
			|| str_contains( $template, '>' )
			|| str_contains( $template, '\\' )
			|| str_contains( $template, "\0" )
		) {
			return false;
		}

		$test_url = str_replace(
			array( '{z}', '{x}', '{y}', '{s}' ),
			array( '1', '1', '1', 'a' ),
			$template
		);

		return false !== wp_http_validate_url(
			$test_url
		);
	}

	/**
	 * Normalize maximum map zoom.
	 *
	 * @param mixed $value Raw zoom.
	 *
	 * @return int
	 */
	private static function sanitize_max_zoom(
		mixed $value
	): int {
		if ( ! is_numeric( $value ) ) {
			return 18;
		}

		return min(
			20,
			max( 1, (int) $value )
		);
	}

	/**
	 * Return cache-busting asset version.
	 *
	 * @param string $path Absolute file path.
	 *
	 * @return string
	 */
	private static function asset_version(
		string $path
	): string {
		if ( is_file( $path ) ) {
			$modified = filemtime( $path );

			if ( is_int( $modified ) && $modified > 0 ) {
				return POWER_SCHEDULE_MANAGER_VERSION
					. '.'
					. $modified;
			}
		}

		return POWER_SCHEDULE_MANAGER_VERSION;
	}
}
