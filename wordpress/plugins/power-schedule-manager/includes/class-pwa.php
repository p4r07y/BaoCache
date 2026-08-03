<?php
/**
 * Progressive Web App installation support.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides a manifest, root-scoped service worker, and respectful install UI.
 */
final class Power_Schedule_Manager_PWA {

	private const string MANIFEST_QUERY = 'psm_pwa_manifest';

	private const string WORKER_QUERY = 'psm_pwa_worker';

	private const string WORKER_PATH = '/psm-pwa-worker/';

	private const string SCRIPT_HANDLE =
		'power-schedule-manager-pwa';

	private const string STYLE_HANDLE =
		'power-schedule-manager-pwa';

	/**
	 * Register public hooks.
	 */
	public function register(): void {
		add_action(
			'template_redirect',
			array( $this, 'serve_endpoint' ),
			0
		);
		add_action(
			'wp_head',
			array( $this, 'render_head' ),
			2
		);
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_assets' ),
			30
		);
		add_action(
			'wp_footer',
			array( $this, 'render_install_prompt' ),
			30
		);
	}

	/**
	 * Whether the PWA feature is enabled.
	 */
	public static function enabled(): bool {
		$settings = self::settings();

		return ! empty( $settings['pwa_enabled'] )
			&& is_ssl();
	}

	/**
	 * Serve the dynamic manifest or service worker from the site root.
	 */
	public function serve_endpoint(): void {
		if (
			self::enabled()
			&& self::query_flag( self::MANIFEST_QUERY )
		) {
			$this->serve_manifest();
		}

		if (
			self::enabled()
			&& (
				self::query_flag( self::WORKER_QUERY )
				|| self::request_path_is( self::WORKER_PATH )
			)
		) {
			$this->serve_worker();
		}
	}

	/**
	 * Return the extensionless PWA worker endpoint.
	 *
	 * The endpoint intentionally has no .js suffix. Some Nginx/CDN
	 * configurations short-circuit missing static .js files before WordPress,
	 * while this virtual endpoint is reliably routed to template_redirect.
	 */
	public static function worker_url(): string {
		return home_url( self::WORKER_PATH );
	}

	/**
	 * Return the application scope, including a subdirectory installation.
	 */
	public static function worker_scope(): string {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return is_string( $path ) && '' !== $path
			? trailingslashit( $path )
			: '/';
	}

	/**
	 * Add install metadata to the document head.
	 */
	public function render_head(): void {
		if ( ! self::enabled() || ! Power_Schedule_Manager_Assets::request_has_plugin_surface() ) {
			return;
		}

		$settings = self::settings();
		$theme_color = self::color(
			$settings['pwa_theme_color'] ?? '#075985',
			'#075985'
		);
		$manifest_url = add_query_arg(
			self::MANIFEST_QUERY,
			'1',
			home_url( '/' )
		);
		$app_name = self::app_name( $settings );

		printf(
			'<link rel="manifest" href="%s">' . "\n",
			esc_url( $manifest_url )
		);
		printf(
			'<meta name="theme-color" content="%s">' . "\n",
			esc_attr( $theme_color )
		);
		printf(
			'<meta name="mobile-web-app-capable" content="yes">' . "\n"
		);
		printf(
			'<meta name="apple-mobile-web-app-capable" content="yes">' . "\n"
		);
		printf(
			'<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n"
		);
		printf(
			'<meta name="apple-mobile-web-app-title" content="%s">' . "\n",
			esc_attr( $app_name )
		);

		$apple_icon = self::icon_url( 180, $settings );

		if ( '' !== $apple_icon ) {
			printf(
				'<link rel="apple-touch-icon" href="%s">' . "\n",
				esc_url( $apple_icon )
			);
		}
	}

	/**
	 * Enqueue the lightweight install controller globally.
	 */
	public function enqueue_assets(): void {
		if ( ! self::enabled() || is_admin() || ! Power_Schedule_Manager_Assets::request_has_plugin_surface() ) {
			return;
		}

		$settings = self::settings();
		$debug_assets = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$script_file = $debug_assets ? 'pwa.js' : 'pwa.min.js';
		$style_file = $debug_assets ? 'pwa.css' : 'pwa.min.css';
		$script_path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $script_file;
		$style_path = POWER_SCHEDULE_MANAGER_PATH . 'public/assets/' . $style_file;

		wp_enqueue_style(
			self::STYLE_HANDLE,
			POWER_SCHEDULE_MANAGER_URL . 'public/assets/' . $style_file,
			array(),
			self::asset_version( $style_path )
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			POWER_SCHEDULE_MANAGER_URL . 'public/assets/' . $script_file,
			array(),
			self::asset_version( $script_path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'PowerScheduleManagerPWA',
			array(
				'enabled'       => true,
				'promptEnabled' => ! empty(
					$settings['pwa_prompt_enabled']
				),
				'workerEnabled' => ! empty(
					$settings['pwa_service_worker_enabled']
				),
				'workerUrl'     => esc_url_raw( self::worker_url() ),
				'workerScope'   => self::worker_scope(),
				'visitThreshold' => min(
					10,
					max(
						2,
						absint(
							$settings['pwa_visit_threshold'] ?? 3
						)
					)
				),
				'cooldownDays'  => min(
					365,
					max(
						1,
						absint(
							$settings['pwa_prompt_cooldown_days']
								?? 30
						)
					)
				),
				'delayMs'       => min(
					30000,
					max(
						2000,
						absint(
							$settings['pwa_prompt_delay_seconds']
								?? 8
						) * 1000
					)
				),
			)
		);
	}

	/**
	 * Render one accessible prompt shell. JavaScript decides whether to show.
	 */
	public function render_install_prompt(): void {
		if ( ! self::enabled() || ! Power_Schedule_Manager_Assets::request_has_plugin_surface() ) {
			return;
		}

		$settings = self::settings();

		if ( empty( $settings['pwa_prompt_enabled'] ) ) {
			return;
		}

		$title = sanitize_text_field(
			(string) (
				$settings['pwa_prompt_title']
				?? 'Thêm Cúp Điện Lâm Đồng vào màn hình chính'
			)
		);
		$message = sanitize_text_field(
			(string) (
				$settings['pwa_prompt_message']
				?? 'Tra cứu nhanh hơn và nhận thông báo lịch cúp điện trên thiết bị này.'
			)
		);
		?>
		<aside
			class="psm-pwa-prompt"
			data-psm-pwa-prompt
			role="dialog"
			aria-labelledby="psm-pwa-prompt-title"
			aria-describedby="psm-pwa-prompt-message"
			hidden
		>
			<button
				type="button"
				class="psm-pwa-prompt__close"
				data-psm-pwa-dismiss
				aria-label="<?php esc_attr_e( 'Đóng gợi ý cài ứng dụng', 'power-schedule-manager' ); ?>"
			>×</button>
			<div class="psm-pwa-prompt__icon" aria-hidden="true">⚡</div>
			<div class="psm-pwa-prompt__content">
				<strong id="psm-pwa-prompt-title"><?php echo esc_html( $title ); ?></strong>
				<p id="psm-pwa-prompt-message"><?php echo esc_html( $message ); ?></p>
				<p class="psm-pwa-prompt__ios" data-psm-pwa-ios-help hidden>
					<?php esc_html_e( 'Trên iPhone/iPad: bấm Chia sẻ, sau đó chọn “Thêm vào Màn hình chính”.', 'power-schedule-manager' ); ?>
				</p>
			</div>
			<div class="psm-pwa-prompt__actions">
				<button type="button" class="psm-pwa-prompt__install" data-psm-pwa-install>
					<?php esc_html_e( 'Thêm vào màn hình chính', 'power-schedule-manager' ); ?>
				</button>
				<button type="button" class="psm-pwa-prompt__later" data-psm-pwa-dismiss>
					<?php esc_html_e( 'Để sau', 'power-schedule-manager' ); ?>
				</button>
			</div>
		</aside>
		<?php
	}

	/**
	 * Output a standards-based web app manifest.
	 */
	private function serve_manifest(): never {
		$settings = self::settings();
		$app_name = self::app_name( $settings );
		$short_name = sanitize_text_field(
			(string) (
				$settings['pwa_short_name'] ?? 'Cúp Điện LĐ'
			)
		);
		if ( 'Cúp điện LĐ' === $short_name ) {
			$short_name = 'Cúp Điện LĐ';
		}
		$description = sanitize_text_field(
			(string) (
				$settings['pwa_description']
					?? 'Cúp Điện Lâm Đồng hỗ trợ tra cứu lịch điện theo ngày và khu vực.'
			)
		);
		if (
			'Tra cứu lịch điện tại Lâm Đồng theo ngày và khu vực.'
			=== $description
		) {
			$description =
				'Cúp Điện Lâm Đồng hỗ trợ tra cứu lịch điện theo ngày và khu vực.';
		}
		$icons = array();
		$seen_icons = array();

		foreach ( array( 192, 512 ) as $size ) {
			$url = self::icon_url( $size, $settings );

			if ( '' === $url || isset( $seen_icons[ $url ] ) ) {
				continue;
			}

			$is_svg = str_ends_with(
				strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
				'.svg'
			);
			$icons[] = array(
				'src'     => $url,
				'sizes'   => $is_svg ? 'any' : $size . 'x' . $size,
				'type'    => $is_svg
					? 'image/svg+xml'
					: 'image/png',
				'purpose' => 'any',
			);
			$seen_icons[ $url ] = true;
		}

		$manifest = array(
			'id'               => home_url( '/' ),
			'name'             => $app_name,
			'short_name'       => $short_name,
			'description'      => $description,
			'start_url'        => home_url( '/?source=pwa' ),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'orientation'      => 'any',
			'background_color' => self::color(
				$settings['pwa_background_color'] ?? '#ffffff',
				'#ffffff'
			),
			'theme_color'      => self::color(
				$settings['pwa_theme_color'] ?? '#075985',
				'#075985'
			),
			'lang'             => 'vi',
			'dir'              => 'ltr',
			'categories'       => array( 'utilities', 'productivity' ),
			'icons'            => $icons,
		);

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode(
			$manifest,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	/**
	 * Output the root-scoped PWA worker.
	 *
	 * OneSignal uses its own static worker and non-overlapping scope.
	 */
	private function serve_worker(): never {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		echo '"use strict";' . "\n";
		echo 'self.addEventListener("install",function(){self.skipWaiting();});' . "\n";
		echo 'self.addEventListener("activate",function(event){event.waitUntil(self.clients.claim());});' . "\n";
		echo 'self.addEventListener("fetch",function(event){'
			. 'if(event.request.mode==="navigate"){'
			. 'event.respondWith(fetch(event.request));'
			. '}});'
			. "\n";
		exit;
	}

	/**
	 * Retrieve plugin settings safely.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings(): array {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Return the configured app name.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private static function app_name( array $settings ): string {
		$name = sanitize_text_field(
			(string) (
				$settings['pwa_app_name'] ?? 'Cúp Điện Lâm Đồng'
			)
		);
		if ( 'Cúp điện Lâm Đồng' === $name ) {
			$name = 'Cúp Điện Lâm Đồng';
		}

		return '' !== $name ? $name : 'Cúp Điện Lâm Đồng';
	}

	/**
	 * Return a square icon from settings or the WordPress Site Icon.
	 *
	 * @param int                 $size Requested size.
	 * @param array<string,mixed> $settings Settings.
	 */
	private static function icon_url( int $size, array $settings ): string {
		$custom = esc_url_raw(
			(string) ( $settings['pwa_icon_url'] ?? '' ),
			array( 'https' )
		);

		if ( '' !== $custom ) {
			return $custom;
		}

		$site_icon = esc_url_raw(
			(string) get_site_icon_url( $size ),
			array( 'https' )
		);

		return '' !== $site_icon
			? $site_icon
			: esc_url_raw(
				POWER_SCHEDULE_MANAGER_URL
					. 'public/assets/pwa-icon.svg',
				array( 'https' )
			);
	}

	/**
	 * Normalize a six-digit hexadecimal color.
	 */
	private static function color( mixed $value, string $fallback ): string {
		$value = is_scalar( $value )
			? strtolower( trim( (string) $value ) )
			: '';

		return 1 === preg_match( '/\A#[a-f0-9]{6}\z/', $value )
			? $value
			: $fallback;
	}

	/**
	 * Test a scalar query flag without registering public query variables.
	 */
	private static function query_flag( string $key ): bool {
		return isset( $_GET[ $key ] )
			&& is_scalar( $_GET[ $key ] )
			&& '1' === sanitize_text_field(
				wp_unslash( (string) $_GET[ $key ] )
			);
	}

	/**
	 * Match a public virtual asset without relying on rewrite rules.
	 */
	private static function request_path_is( string $expected ): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$request_path = wp_parse_url(
			wp_unslash( (string) $_SERVER['REQUEST_URI'] ),
			PHP_URL_PATH
		);
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path )
			? '/' . trim( $home_path, '/' )
			: '';
		$expected_path = rtrim( $home_path, '/' ) . $expected;

		return is_string( $request_path )
			&& untrailingslashit( $request_path )
				=== untrailingslashit( $expected_path );
	}

	/**
	 * Use file modification time for reliable cache busting.
	 */
	private static function asset_version( string $path ): string {
		$modified = is_file( $path ) ? filemtime( $path ) : false;

		return false !== $modified
			? POWER_SCHEDULE_MANAGER_VERSION . '.' . (string) $modified
			: POWER_SCHEDULE_MANAGER_VERSION;
	}
}
