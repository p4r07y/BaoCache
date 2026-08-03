<?php
defined( 'ABSPATH' ) || exit;

final class BaoCache_Runtime {
	private const string DELAY_PREVIEW_COOKIE = 'baocache_delay_preview';
	private const int DELAY_PREVIEW_TTL = 1800;
	private array $settings = array();

	public function register(): void {
		$this->settings = BaoCache_Settings::get();
		add_action( 'init', array( $this, 'reduce_bloat' ), 1 );
		add_action( 'template_redirect', array( $this, 'redirect_hardened_pages' ), 1 );
		add_action( 'admin_menu', array( $this, 'remove_file_editor_menu' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'manage_assets' ), PHP_INT_MAX );
		add_filter( 'script_loader_tag', array( $this, 'delay_script_tag' ), 20, 3 );
		add_filter( 'style_loader_tag', array( $this, 'async_style_tag' ), 20, 4 );
		add_filter( 'script_loader_src', array( $this, 'mask_asset_version' ), 100, 2 );
		add_filter( 'style_loader_src', array( $this, 'mask_asset_version' ), 100, 2 );
		add_filter( 'xmlrpc_enabled', array( $this, 'xmlrpc_enabled' ) );
		add_filter( 'pre_ping', array( $this, 'remove_self_pingback' ) );
		add_filter( 'pings_open', array( $this, 'pings_open' ), 10, 2 );
		add_filter( 'wp_is_application_passwords_supported', array( $this, 'application_passwords_supported' ) );
		add_filter( 'user_has_cap', array( $this, 'file_editor_caps' ), 10, 4 );
		add_filter( 'rest_pre_dispatch', array( $this, 'protect_rest_users' ), 10, 3 );
		add_filter( 'login_errors', array( $this, 'login_errors' ) );
		add_filter( 'wp_headers', array( $this, 'filter_headers' ) );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_interval' ) );
		add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'print_preloads' ), 1 );
		add_action( 'wp_head', array( $this, 'print_validated_critical_css' ), 0 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'lcp_image_attributes' ), 20, 3 );
		add_action( 'wp_footer', array( $this, 'capture_asset_inventory' ), PHP_INT_MAX );
		add_action( 'send_headers', array( $this, 'remove_x_pingback_header' ), 1 );
		add_action( 'send_headers', array( $this, 'send_cache_policy' ), 30 );
	}

	public function reduce_bloat(): void {
		if ( $this->settings['disable_emoji'] ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		}

		if ( $this->settings['disable_embeds'] ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		}

		if ( $this->settings['remove_rsd'] ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
		if ( $this->settings['remove_wlw'] ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}
		if ( $this->settings['remove_shortlink'] ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 10 );
		}
		if ( $this->settings['remove_generator'] ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		if ( $this->settings['remove_feed_links'] || 'keep' !== $this->settings['rss_mode'] ) {
			remove_action( 'wp_head', 'feed_links', 2 );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
		if ( $this->settings['remove_rest_api_link'] ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head' );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}
	}

	public function xmlrpc_enabled( bool $enabled ): bool {
		return $this->settings['disable_xmlrpc'] ? false : $enabled;
	}

	public function remove_self_pingback( array $links ): array {
		if ( ! $this->settings['disable_self_pingback'] ) {
			return $links;
		}
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return array_values( array_filter( $links, static function ( string $link ) use ( $site_host ): bool {
			return strtolower( (string) wp_parse_url( $link, PHP_URL_HOST ) ) !== $site_host;
		} ) );
	}

	public function pings_open( bool $open, int $post_id ): bool {
		return $this->settings['disable_trackbacks'] ? false : $open;
	}

	public function application_passwords_supported( bool $supported ): bool {
		return $this->settings['disable_application_passwords'] ? false : $supported;
	}

	public function protect_rest_users( mixed $result, WP_REST_Server $server, WP_REST_Request $request ): mixed {
		$route = (string) $request->get_route();
		if ( ! $this->settings['disable_rest_user_enumeration'] || ( current_user_can( 'list_users' ) ) || ! preg_match( '#^/wp/v[0-9]+/users(?:/|$)#', $route ) ) {
			return $result;
		}
		return new WP_Error( 'baocache_rest_user_forbidden', __( 'Endpoint người dùng không công khai.', 'baocache' ), array( 'status' => 404 ) );
	}

	public function login_errors( string $errors ): string {
		return $this->settings['hide_login_errors'] ? __( 'Thông tin đăng nhập không hợp lệ.', 'baocache' ) : $errors;
	}

	public function filter_headers( array $headers ): array {
		if ( $this->settings['remove_x_pingback'] ) {
			unset( $headers['X-Pingback'], $headers['x-pingback'] );
		}
		return $headers;
	}

	/** Mask only the public version query on same-site assets; inventory keeps the real version. */
	public function mask_asset_version( string $src, string $handle = '' ): string {
		$mode = (string) ( $this->settings['asset_version_masking'] ?? 'off' );
		if ( 'off' === $mode || '' === $src || is_admin() ) return $src;
		$parts = wp_parse_url( $src );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$asset_host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( '' !== $asset_host && $asset_host !== $site_host ) return $src;
		$query = array();
		if ( ! empty( $parts['query'] ) ) parse_str( (string) $parts['query'], $query );
		if ( ! isset( $query['ver'] ) || ! is_scalar( $query['ver'] ) || '' === (string) $query['ver'] ) return $src;
		$version = (string) $query['ver'];
		unset( $query['ver'] );
		if ( 'fingerprint' === $mode ) $query['v'] = substr( hash( 'sha256', $version . '|' . (string) $handle ), 0, 8 );
		$base = (string) ( $parts['scheme'] ?? '' );
		if ( '' !== $base ) $base .= '://';
		if ( '' === $base && isset( $parts['host'] ) ) $base = '//';
		$base .= (string) ( $parts['host'] ?? '' );
		if ( isset( $parts['port'] ) ) $base .= ':' . (int) $parts['port'];
		$base .= (string) ( $parts['path'] ?? '' );
		$result = '' !== $base ? $base : (string) ( $parts['path'] ?? $src );
		if ( ! empty( $query ) ) $result .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		if ( isset( $parts['fragment'] ) ) $result .= '#' . (string) $parts['fragment'];
		return $result;
	}

	public function remove_x_pingback_header(): void {
		if ( $this->settings['remove_x_pingback'] && function_exists( 'header_remove' ) && ! headers_sent() ) {
			header_remove( 'X-Pingback' );
		}
	}

	public function file_editor_caps( array $allcaps, array $caps, array $args, WP_User $user ): array {
		if ( ! $this->settings['disable_file_editor'] ) {
			return $allcaps;
		}
		unset( $allcaps['edit_themes'], $allcaps['edit_plugins'], $allcaps['edit_files'] );
		return $allcaps;
	}

	public function remove_file_editor_menu(): void {
		if ( ! $this->settings['disable_file_editor'] ) {
			return;
		}
		remove_submenu_page( 'themes.php', 'theme-editor.php' );
		remove_submenu_page( 'plugins.php', 'plugin-editor.php' );
	}

	public function redirect_hardened_pages(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		if ( 'redirect' === $this->settings['rss_mode'] && is_feed() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		if ( 'gone' === $this->settings['rss_mode'] && is_feed() ) {
			status_header( 410 );
			nocache_headers();
			wp_die( esc_html__( 'RSS Feed đã được tắt.', 'baocache' ), esc_html__( 'Feed unavailable', 'baocache' ), array( 'response' => 410 ) );
		}
		if ( $this->settings['disable_attachment_pages'] && is_attachment() ) {
			$parent_id = (int) wp_get_post_parent_id( get_queried_object_id() );
			$target = $parent_id > 0 ? get_permalink( $parent_id ) : home_url( '/' );
			wp_safe_redirect( is_string( $target ) ? $target : home_url( '/' ), 301 );
			exit;
		}
		if ( $this->settings['disable_author_enumeration'] && is_author() ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}

	public function manage_assets(): void {
		if ( is_admin() ) {
			return;
		}

		foreach ( (array) $this->settings['asset_rules'] as $rule ) {
			if ( ! $this->rule_matches( $rule ) || $this->is_required_dependency( (string) $rule['handle'], (string) $rule['type'] ) ) {
				continue;
			}
			if ( 'script' === $rule['type'] ) {
				wp_dequeue_script( $rule['handle'] );
			} else {
				wp_dequeue_style( $rule['handle'] );
			}
		}

		if ( $this->settings['dashicons_guests'] && ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}

		if ( $this->render_blocking_applies() ) {
			foreach ( BaoCache_Settings::lines( (string) $this->settings['defer_handles'] ) as $handle ) {
				if ( $this->render_blocking_handle_excluded( $handle ) || ! BaoCache_Render_Blocking::gate_allows( $handle, 'defer' ) ) continue;
				wp_script_add_data( $handle, 'strategy', 'defer' );
			}
		}

		if ( $this->delay_is_enabled() ) {
			wp_enqueue_script( 'baocache-delay-runner', BAOCACHE_URL . 'assets/baocache-delay.js?timeout=' . (int) $this->settings['delay_timeout'] . '&preview=' . ( self::delay_preview_active() ? '1' : '0' ), array(), BAOCACHE_VERSION, true );
		}
	}

	/**
	 * Delay is intentionally narrower than defer. Only a bare, independent frontend
	 * script may be delayed; WordPress keeps normal execution for complex handles.
	 */
	public function delay_script_tag( string $tag, string $handle, string $src ): string {
		if ( ! $this->can_delay_handle( $handle, $src ) ) {
			return $tag;
		}

		return sprintf(
			'<script id="%1$s-js" type="text/baocache-delay" data-baocache-delay="1" data-baocache-handle="%2$s" data-baocache-src="%3$s"></script>',
			esc_attr( $handle ),
			esc_attr( $handle ),
			esc_url( $src )
		);
	}

	/**
	 * Async CSS is opt-in by handle. The noscript branch preserves a normal
	 * stylesheet for browsers that do not run the media flip.
	 */
	public function async_style_tag( string $html, string $handle, string $href, string $media ): string {
		if ( ! $this->render_blocking_applies() || $this->render_blocking_handle_excluded( $handle ) || ! BaoCache_Render_Blocking::gate_allows( $handle, 'async-css' ) || ! in_array( $handle, BaoCache_Settings::lines( (string) $this->settings['async_style_handles'] ), true ) || '' === $href ) {
			return $html;
		}
		$id = esc_attr( $handle . '-css' );
		$url = esc_url( $href );
		wp_enqueue_script( 'baocache-async-css', BAOCACHE_URL . 'assets/baocache-async-css.js', array(), BAOCACHE_VERSION, false );
		return '<link rel="stylesheet" id="' . $id . '" href="' . $url . '" media="print" data-baocache-async-css="1"><noscript><link rel="stylesheet" id="' . $id . '-noscript" href="' . $url . '"></noscript>';
	}

	public function heartbeat_interval( array $settings ): array {
		if ( is_admin() ) {
			$settings['interval'] = (int) $this->settings['heartbeat'];
		}
		return $settings;
	}

	public function resource_hints( array $urls, string $relation_type ): array {
		if ( ! in_array( $relation_type, array( 'preconnect', 'dns-prefetch' ), true ) ) {
			return $urls;
		}
		$key = 'preconnect' === $relation_type ? 'preconnect' : 'dns_prefetch';
		foreach ( BaoCache_Settings::lines( (string) $this->settings[ $key ] ) as $url ) {
			$urls[] = $url;
		}
		return array_values( array_unique( $urls ) );
	}

	public function print_preloads(): void {
		foreach ( BaoCache_Settings::lines( (string) $this->settings['preload'] ) as $url ) {
			$as = BaoCache_Settings::preload_as( $url );
			if ( '' === $as ) {
				continue;
			}
			echo '<link rel="preload" href="' . esc_url( $url ) . '" as="' . esc_attr( $as ) . '"';
			if ( 'font' === $as ) {
				echo ' crossorigin';
			}
			echo ">\n";
		}

		if ( $this->lcp_is_active() && ! in_array( (string) $this->settings['lcp_image'], BaoCache_Settings::lines( (string) $this->settings['preload'] ), true ) ) {
			echo '<link rel="preload" href="' . esc_url( (string) $this->settings['lcp_image'] ) . '" as="image" fetchpriority="high">' . "\n";
		}
	}

	public function print_validated_critical_css(): void {
		if ( ! $this->render_blocking_applies() ) return;
		$record = BaoCache_Render_Blocking::critical_css();
		$template = (string) ( $record['template'] ?? 'front-page' );
		if ( 'front-page' === $template && ! is_front_page() ) return;
		$css = BaoCache_Render_Blocking::validated_critical_css();
		if ( '' === $css ) return;
		if ( function_exists( 'wp_get_inline_style_tag' ) ) {
			echo wp_get_inline_style_tag( $css, array( 'id' => 'baocache-critical-css' ) );
		} else {
			echo '<style id="baocache-critical-css">' . $css . '</style>';
		}
	}

	/**
	 * Applies priority only to an image URL the administrator explicitly verified.
	 * It does not claim to detect LCP and intentionally does not alter CSS backgrounds.
	 */
	public function lcp_image_attributes( array $attributes, mixed $attachment, mixed $size ): array {
		if ( ! $this->lcp_is_active() || ! $attachment instanceof WP_Post ) {
			return $attributes;
		}

		$source = wp_get_attachment_image_url( $attachment->ID, $size );
		if ( ! is_string( $source ) || ! $this->same_asset_url( $source, (string) $this->settings['lcp_image'] ) ) {
			return $attributes;
		}

		$attributes['loading'] = 'eager';
		$attributes['fetchpriority'] = 'high';
		$attributes['decoding'] = 'async';
		return $attributes;
	}

	public function send_cache_policy(): void {
		$ttl = (int) $this->settings['public_ttl'];
		if ( $ttl < 1 || is_admin() || is_user_logged_in() || is_feed() || is_search() || is_preview() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || headers_sent() || $this->has_accel_header() ) {
			return;
		}
		header( 'X-Accel-Expires: ' . $ttl, true );
	}

	public function capture_asset_inventory(): void {
		$scan_token = get_transient( 'baocache_asset_inventory_scan_token' );
		$scan_header = isset( $_SERVER['HTTP_X_BAOCACHE_ASSET_SCAN'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_BAOCACHE_ASSET_SCAN'] ) : '';
		$requested_scan = is_string( $scan_token ) && '' !== $scan_token && hash_equals( $scan_token, $scan_header );

		if ( is_admin() || is_user_logged_in() || is_feed() || wp_doing_ajax() || get_transient( 'baocache_asset_inventory_lock' ) ) {
			return;
		}
		// While an administrator initiated a scan, do not let an unrelated visitor
		// overwrite the result. The short-lived token is only sent over Docker Nginx.
		if ( is_string( $scan_token ) && '' !== $scan_token && ! $requested_scan ) {
			return;
		}
		set_transient( 'baocache_asset_inventory_lock', '1', 10 * MINUTE_IN_SECONDS );
		$inventory = array();
		foreach ( array( 'script' => wp_scripts(), 'style' => wp_styles() ) as $type => $registry ) {
			foreach ( (array) $registry->queue as $handle ) {
				if ( ! isset( $registry->registered[ $handle ] ) ) continue;
				$asset = $registry->registered[ $handle ];
				$source = (string) $asset->src;
				$inventory[ $type . ':' . $handle ] = array(
					'type' => $type, 'handle' => $handle, 'source' => $source ? strtok( $source, '?' ) : __( 'Inline / core', 'baocache' ), 'inline' => '' === $source,
					'size_bytes' => $this->local_asset_size( $source ), 'version' => (string) ( $asset->ver ?? '' ),
					'dependencies' => array_values( array_map( 'strval', (array) $asset->deps ) ),
					'in_footer' => 'script' === $type && 1 === (int) $registry->get_data( $handle, 'group' ),
					'path' => (string) wp_parse_url( esc_url_raw( home_url( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ) ) ), PHP_URL_PATH ),
				);
			}
		}
		set_transient( 'baocache_asset_inventory', array( 'schema' => 5, 'captured_at' => time(), 'assets' => array_values( $inventory ), 'scan_verified' => $requested_scan ), DAY_IN_SECONDS );
		if ( $requested_scan ) {
			delete_transient( 'baocache_asset_inventory_scan_token' );
		}
	}

	/** Request one anonymous frontend page through Docker Nginx to refresh the sampled registry. */
	public static function scan_asset_inventory(): array|WP_Error {
		delete_transient( 'baocache_asset_inventory_lock' );
		delete_transient( 'baocache_asset_inventory' );
		$scan_token = wp_generate_password( 32, false, false );
		set_transient( 'baocache_asset_inventory_scan_token', $scan_token, 2 * MINUTE_IN_SECONDS );
		$health = wp_remote_get( 'http://nginx/healthz', array( 'timeout' => 4, 'redirection' => 0 ) );
		if ( is_wp_error( $health ) || 200 !== (int) wp_remote_retrieve_response_code( $health ) ) {
			delete_transient( 'baocache_asset_inventory_scan_token' );
			return new WP_Error( 'baocache_nginx_unavailable', __( 'Không kết nối được Nginx nội bộ. Kiểm tra service nginx và mạng Docker/Coolify.', 'baocache' ) );
		}
		$site_url = home_url( '/' );
		$path = (string) wp_parse_url( $site_url, PHP_URL_PATH );
		$host = (string) wp_parse_url( $site_url, PHP_URL_HOST );
		$port = (int) wp_parse_url( $site_url, PHP_URL_PORT );
		if ( 0 < $port ) {
			$host .= ':' . $port;
		}
		$target = 'http://nginx' . ( '' === $path ? '/' : $path ) . '?baocache_asset_scan=' . time();
		$scheme = (string) wp_parse_url( $site_url, PHP_URL_SCHEME );
		$response = wp_remote_get( $target, array(
			'timeout' => 15,
			'redirection' => 0,
			'headers' => array(
				'Host' => $host,
				'X-Forwarded-Proto' => '' !== $scheme ? $scheme : 'https',
				'X-BaoCache-Asset-Scan' => $scan_token,
				'Cookie' => '',
				'User-Agent' => 'BaoCache-Asset-Scan/' . BAOCACHE_VERSION,
			),
		) );
		if ( is_wp_error( $response ) ) {
			delete_transient( 'baocache_asset_inventory_scan_token' );
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			delete_transient( 'baocache_asset_inventory_scan_token' );
			return new WP_Error( 'baocache_asset_scan_failed', sprintf( __( 'Nginx trả HTTP %d khi quét frontend. Kiểm tra redirect HTTPS, cấu hình domain và log Nginx.', 'baocache' ), $code ) );
		}
		$inventory = get_transient( 'baocache_asset_inventory' );
		delete_transient( 'baocache_asset_inventory_scan_token' );
		if ( is_array( $inventory ) && ! empty( $inventory['scan_verified'] ) ) {
			return $inventory;
		}
		return new WP_Error( 'baocache_asset_scan_empty', __( 'Trang frontend đã phản hồi nhưng không hoàn tất phiên quét BaoCache. Kiểm tra theme có gọi wp_footer(), không có redirect nội bộ và request đến Nginx không bị chặn.', 'baocache' ) );
	}

	/** Local file size is useful inventory evidence; third-party transfer size is intentionally unknown here. */
	private function local_asset_size( string $source ): ?int {
		$path = (string) wp_parse_url( $source, PHP_URL_PATH );
		$host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $path || ( '' !== $host && $host !== $site_host ) || str_contains( $path, '..' ) ) {
			return null;
		}
		$root = realpath( ABSPATH );
		$file = realpath( ABSPATH . ltrim( $path, '/' ) );
		if ( false === $root || false === $file || ! str_starts_with( $file, $root . DIRECTORY_SEPARATOR ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			return null;
		}
		$size = filesize( $file );
		return false === $size ? null : (int) $size;
	}

	private function has_accel_header(): bool {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'X-Accel-Expires:' ) ) {
				return true;
			}
		}
		return false;
	}

	private function delay_is_enabled(): bool {
		return ! is_admin() && ( ! is_user_logged_in() || self::delay_preview_active() ) && ! is_feed() && ! wp_doing_ajax() && ! empty( BaoCache_Settings::lines( (string) $this->settings['delay_handles'] ) );
	}

	private function render_blocking_applies(): bool {
		$request_path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		$status = BaoCache_Render_Blocking::context_status( $this->settings, $request_path, '', array(
			'logged_in' => is_user_logged_in(),
			'admin' => is_admin(),
			'preview' => is_preview(),
			'checkout' => function_exists( 'is_checkout' ) && is_checkout(),
			'feed' => is_feed(),
			'rest' => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'ajax' => wp_doing_ajax(),
		) );
		return ! empty( $status['eligible'] );
	}

	private function render_blocking_handle_excluded( string $handle ): bool {
		$request_path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		$status = BaoCache_Render_Blocking::context_status( $this->settings, $request_path, $handle, array(
			'logged_in' => is_user_logged_in(),
			'admin' => is_admin(),
			'preview' => is_preview(),
			'checkout' => function_exists( 'is_checkout' ) && is_checkout(),
			'feed' => is_feed(),
			'rest' => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'ajax' => wp_doing_ajax(),
		) );
		return ! empty( $status['excluded'] );
	}

	/** A signed, administrator-only preview cookie never enables Delay for guests. */
	public static function delay_preview_active(): bool {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || empty( $_COOKIE[ self::DELAY_PREVIEW_COOKIE ] ) ) {
			return false;
		}
		$parts = explode( '|', (string) wp_unslash( $_COOKIE[ self::DELAY_PREVIEW_COOKIE ] ), 3 );
		if ( 3 !== count( $parts ) ) {
			return false;
		}
		$user_id = absint( $parts[0] );
		$expires = absint( $parts[1] );
		$expected = hash_hmac( 'sha256', $user_id . '|' . $expires, wp_salt( 'auth' ) );
		return $user_id === get_current_user_id() && $expires >= time() && hash_equals( $expected, $parts[2] );
	}

	public static function set_delay_preview( bool $enabled ): void {
		$expires = $enabled ? time() + self::DELAY_PREVIEW_TTL : time() - HOUR_IN_SECONDS;
		$value = '';
		if ( $enabled ) {
			$user_id = get_current_user_id();
			$value = $user_id . '|' . $expires . '|' . hash_hmac( 'sha256', $user_id . '|' . $expires, wp_salt( 'auth' ) );
		}
		setcookie( self::DELAY_PREVIEW_COOKIE, $value, array(
			'expires' => $expires,
			'path' => '/',
			'domain' => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
	}

	private function lcp_is_active(): bool {
		if ( is_admin() || is_feed() || wp_doing_ajax() || '' === (string) $this->settings['lcp_image'] ) {
			return false;
		}
		return 'everywhere' === (string) $this->settings['lcp_scope'] || is_front_page();
	}

	private function same_asset_url( string $first, string $second ): bool {
		$first_path = (string) wp_parse_url( strtok( $first, '?' ), PHP_URL_PATH );
		$second_path = (string) wp_parse_url( strtok( $second, '?' ), PHP_URL_PATH );
		$first_host = strtolower( (string) wp_parse_url( $first, PHP_URL_HOST ) );
		$second_host = strtolower( (string) wp_parse_url( $second, PHP_URL_HOST ) );
		return '' !== $first_path && $first_path === $second_path && $first_host === $second_host;
	}

	private function can_delay_handle( string $handle, string $src ): bool {
		if ( ! $this->delay_is_enabled() || '' === $src || ! in_array( $handle, BaoCache_Settings::lines( (string) $this->settings['delay_handles'] ), true ) || in_array( $handle, BaoCache_Settings::lines( (string) $this->settings['defer_handles'] ), true ) || ! BaoCache_Render_Blocking::gate_allows( $handle, 'delay' ) ) {
			return false;
		}

		$registry = wp_scripts();
		if ( ! isset( $registry->registered[ $handle ] ) || ! empty( $registry->get_data( $handle, 'before' ) ) || ! empty( $registry->get_data( $handle, 'after' ) ) || ! empty( $registry->get_data( $handle, 'data' ) ) || ! empty( $registry->get_data( $handle, 'conditional' ) ) || ! empty( $registry->get_data( $handle, 'type' ) ) ) {
			return false;
		}

		return ! $this->is_required_dependency( $handle, 'script' );
	}

	private function rule_matches( array $rule ): bool {
		$scope = (string) ( $rule['scope'] ?? 'everywhere' );
		$value = (string) ( $rule['value'] ?? '' );
		return match ( $scope ) {
			'front-page' => is_front_page(),
			'page' => is_page( array_filter( array_map( 'trim', explode( ',', $value ) ) ) ),
			'post-type' => is_singular( sanitize_key( $value ) ),
			'url-prefix' => 0 === strpos( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ), $value ),
			'has-shortcode' => $this->current_content_has_shortcode( $value ),
			'missing-shortcode' => ! $this->current_content_has_shortcode( $value ),
			'has-block' => $this->current_content_has_block( $value ),
			'missing-block' => ! $this->current_content_has_block( $value ),
			default => true,
		};
	}

	private function current_content_has_shortcode( string $shortcode ): bool {
		$post = get_queried_object();
		return $post instanceof WP_Post && '' !== $shortcode && has_shortcode( (string) $post->post_content, $shortcode );
	}

	private function current_content_has_block( string $block ): bool {
		$post = get_queried_object();
		return $post instanceof WP_Post && '' !== $block && has_block( $block, (string) $post->post_content );
	}

	private function is_required_dependency( string $handle, string $type ): bool {
		$registry = 'script' === $type ? wp_scripts() : wp_styles();
		foreach ( (array) $registry->queue as $queued_handle ) {
			if ( $queued_handle !== $handle && $this->depends_on( $registry, (string) $queued_handle, $handle ) ) {
				return true;
			}
		}
		return false;
	}

	private function depends_on( WP_Dependencies $registry, string $candidate, string $target, array $seen = array() ): bool {
		if ( isset( $seen[ $candidate ] ) || ! isset( $registry->registered[ $candidate ] ) ) {
			return false;
		}
		$seen[ $candidate ] = true;
		foreach ( (array) $registry->registered[ $candidate ]->deps as $dependency ) {
			if ( $dependency === $target || $this->depends_on( $registry, $dependency, $target, $seen ) ) {
				return true;
			}
		}
		return false;
	}
}
