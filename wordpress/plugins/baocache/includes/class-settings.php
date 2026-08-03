<?php
defined( 'ABSPATH' ) || exit;

final class BaoCache_Settings {
	public static function defaults(): array {
		return array(
			'public_ttl'       => 0,
			'defer_handles'    => '',
			'async_style_handles' => '',
			'delay_handles'    => '',
			'render_blocking_exclude_handles' => '',
			'render_blocking_exclude_urls' => '',
			'render_blocking_exclude_contexts' => 'authenticated\nadmin\npreview\ncheckout',
			'delay_timeout'    => 10000,
			'disable_emoji'    => true,
			'disable_embeds'   => false,
			'dashicons_guests' => true,
			'disable_xmlrpc'   => false,
			'disable_self_pingback' => true,
			'disable_trackbacks' => true,
			'hide_login_errors' => true,
			'remove_rsd'       => true,
			'remove_wlw'       => true,
			'remove_shortlink' => true,
			'remove_generator' => true,
			'remove_x_pingback' => true,
			'remove_feed_links' => false,
			'remove_rest_api_link' => true,
			'disable_attachment_pages' => false,
			'disable_author_enumeration' => false,
			'disable_rest_user_enumeration' => true,
			'rss_mode'         => 'keep',
			'disable_application_passwords' => false,
			'disable_file_editor' => true,
			'asset_version_masking' => 'off',
			'heartbeat'        => 60,
			'preconnect'       => '',
			'dns_prefetch'     => '',
			'preload'          => '',
			'lcp_image'        => '',
			'lcp_scope'        => 'front-page',
			'asset_rules'      => array(),
			'frontend_timing_enabled' => false,
			'warm_enabled'     => false,
			'warm_sitemap'     => home_url( '/sitemap_index.xml' ),
			'warm_batch'       => 2,
			'warm_schedule'    => 'baocache_six_hours',
			'probe_enabled'    => false,
			'probe_schedule'   => 'manual',
			'analytics_enabled' => false,
			'analytics_id' => '',
			'analytics_consent_mode' => 'unset',
			'analytics_auto_events' => false,
			// Adapters are deliberately opt-in: each one can emit a small, public
			// dataLayer event but BaoCache never stores the visitor event.
			'analytics_adapters' => array(),
			'analytics_duplicate_ack' => false,
			'clarity_enabled' => false,
			'clarity_project_id' => '',
			'csp_enabled' => false,
			'csp_mode' => 'report',
			'csp_script_sources' => "'self'",
			'csp_style_sources' => "'self'",
			'csp_img_sources' => "'self' data:",
			'csp_font_sources' => "'self' data:",
			'csp_connect_sources' => "'self'",
			'csp_frame_sources' => "'self'",
			'csp_worker_sources' => "'self' blob:",
			'csp_collect_reports' => false,
			'csp_canary_enabled' => false,
			'uninstall_keep_configuration' => true,
			'uninstall_keep_diagnostics' => false,
			'uninstall_remove_everything' => false,
		);
	}

	public static function get(): array {
		$value = get_option( BAOCACHE_OPTION, array() );
		$settings = wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
		if ( '' === (string) $settings['warm_sitemap'] ) {
			$settings['warm_sitemap'] = home_url( '/sitemap_index.xml' );
		}
		return $settings;
	}

	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$settings = self::defaults();
		$settings['public_ttl'] = min( 3600, max( 0, absint( $input['public_ttl'] ?? 0 ) ) );
		$settings['defer_handles'] = self::sanitize_lines( $input['defer_handles'] ?? '' );
		$settings['async_style_handles'] = self::sanitize_lines( $input['async_style_handles'] ?? '' );
		$settings['delay_handles'] = self::sanitize_lines( $input['delay_handles'] ?? '' );
		$settings['render_blocking_exclude_handles'] = self::sanitize_lines( $input['render_blocking_exclude_handles'] ?? '' );
		$settings['render_blocking_exclude_urls'] = self::sanitize_url_prefixes( $input['render_blocking_exclude_urls'] ?? '' );
		$settings['render_blocking_exclude_contexts'] = self::sanitize_contexts( $input['render_blocking_exclude_contexts'] ?? '' );
		$settings['delay_timeout'] = in_array( absint( $input['delay_timeout'] ?? 10000 ), array( 5000, 10000, 15000 ), true ) ? absint( $input['delay_timeout'] ) : 10000;
		$settings['disable_emoji'] = ! empty( $input['disable_emoji'] );
		$settings['disable_embeds'] = ! empty( $input['disable_embeds'] );
		$settings['dashicons_guests'] = ! empty( $input['dashicons_guests'] );
		$settings['disable_xmlrpc'] = ! empty( $input['disable_xmlrpc'] );
		$settings['disable_self_pingback'] = ! empty( $input['disable_self_pingback'] );
		$settings['disable_trackbacks'] = ! empty( $input['disable_trackbacks'] );
		$settings['hide_login_errors'] = ! empty( $input['hide_login_errors'] );
		$settings['remove_rsd'] = ! empty( $input['remove_rsd'] );
		$settings['remove_wlw'] = ! empty( $input['remove_wlw'] );
		$settings['remove_shortlink'] = ! empty( $input['remove_shortlink'] );
		// The UI exposes shortlink and generator as one discovery control so a
		// normal settings save cannot accidentally re-enable the generator tag.
		$settings['remove_generator'] = ! empty( $input['remove_generator'] ) || ! empty( $input['remove_shortlink'] );
		$settings['remove_x_pingback'] = ! empty( $input['remove_x_pingback'] );
		$settings['remove_feed_links'] = ! empty( $input['remove_feed_links'] );
		$settings['remove_rest_api_link'] = ! empty( $input['remove_rest_api_link'] );
		$settings['disable_attachment_pages'] = ! empty( $input['disable_attachment_pages'] );
		$settings['disable_author_enumeration'] = ! empty( $input['disable_author_enumeration'] );
		$settings['disable_rest_user_enumeration'] = ! empty( $input['disable_rest_user_enumeration'] );
		$settings['rss_mode'] = in_array( (string) ( $input['rss_mode'] ?? 'keep' ), array( 'keep', 'redirect', 'gone' ), true ) ? (string) $input['rss_mode'] : 'keep';
		$settings['disable_application_passwords'] = ! empty( $input['disable_application_passwords'] );
		$settings['disable_file_editor'] = ! empty( $input['disable_file_editor'] );
		$settings['asset_version_masking'] = in_array( (string) ( $input['asset_version_masking'] ?? 'off' ), array( 'off', 'remove', 'fingerprint' ), true ) ? (string) $input['asset_version_masking'] : 'off';
		$settings['heartbeat'] = in_array( absint( $input['heartbeat'] ?? 60 ), array( 15, 30, 60 ), true )
			? absint( $input['heartbeat'] )
			: 60;
		$settings['preconnect'] = self::sanitize_urls( $input['preconnect'] ?? '' );
		$settings['dns_prefetch'] = self::sanitize_urls( $input['dns_prefetch'] ?? '' );
		$settings['preload'] = self::sanitize_urls( $input['preload'] ?? '' );
		$settings['lcp_image'] = self::sanitize_same_site_url( $input['lcp_image'] ?? '' );
		$settings['lcp_scope'] = in_array( (string) ( $input['lcp_scope'] ?? '' ), array( 'front-page', 'everywhere' ), true ) ? (string) $input['lcp_scope'] : 'front-page';
		$settings['asset_rules'] = self::sanitize_rules( $input['asset_rules'] ?? array() );
		$settings['frontend_timing_enabled'] = ! empty( $input['frontend_timing_enabled'] );
		$settings['warm_enabled'] = ! empty( $input['warm_enabled'] );
		$settings['warm_sitemap'] = self::sanitize_same_site_url( $input['warm_sitemap'] ?? '' );
		$settings['warm_batch'] = in_array( absint( $input['warm_batch'] ?? 2 ), array( 1, 2, 5 ), true ) ? absint( $input['warm_batch'] ) : 2;
		$settings['warm_schedule'] = in_array( (string) ( $input['warm_schedule'] ?? '' ), array( 'hourly', 'baocache_six_hours', 'twicedaily' ), true ) ? (string) $input['warm_schedule'] : 'baocache_six_hours';
		$settings['probe_enabled'] = ! empty( $input['probe_enabled'] );
		$settings['probe_schedule'] = in_array( (string) ( $input['probe_schedule'] ?? 'manual' ), array( 'manual', 'hourly', 'baocache_six_hours', 'baocache_daily' ), true ) ? (string) $input['probe_schedule'] : 'manual';
		$settings['analytics_enabled'] = ! empty( $input['analytics_enabled'] );
		$settings['analytics_id'] = self::sanitize_tracking_id( $input['analytics_id'] ?? '' );
		$settings['analytics_consent_mode'] = in_array( (string) ( $input['analytics_consent_mode'] ?? 'unset' ), array( 'unset', 'denied', 'granted' ), true ) ? (string) $input['analytics_consent_mode'] : 'unset';
		$settings['analytics_auto_events'] = ! empty( $input['analytics_auto_events'] );
		$settings['analytics_adapters'] = self::sanitize_analytics_adapters( $input['analytics_adapters'] ?? array() );
		$settings['analytics_duplicate_ack'] = ! empty( $input['analytics_duplicate_ack'] );
		$settings['clarity_enabled'] = ! empty( $input['clarity_enabled'] );
		$settings['clarity_project_id'] = self::sanitize_clarity_project_id( $input['clarity_project_id'] ?? '' );
		$settings['csp_enabled'] = ! empty( $input['csp_enabled'] );
		$settings['csp_mode'] = in_array( (string) ( $input['csp_mode'] ?? 'report' ), array( 'report', 'enforce' ), true ) ? (string) $input['csp_mode'] : 'report';
		$settings['csp_collect_reports'] = ! empty( $input['csp_collect_reports'] ) && 'report' === $settings['csp_mode'];
		$settings['csp_canary_enabled'] = ! empty( $input['csp_canary_enabled'] ) && ! empty( $settings['csp_enabled'] ) && 'enforce' === $settings['csp_mode'];
		// Turning off configuration retention is intentionally equivalent to the
		// explicit destructive policy; there is no ambiguous half-delete mode.
		$settings['uninstall_remove_everything'] = ! empty( $input['uninstall_remove_everything'] ) || empty( $input['uninstall_keep_configuration'] );
		$settings['uninstall_keep_configuration'] = ! $settings['uninstall_remove_everything'] && ! empty( $input['uninstall_keep_configuration'] );
		$settings['uninstall_keep_diagnostics'] = ! $settings['uninstall_remove_everything'] && ! empty( $input['uninstall_keep_diagnostics'] );
		foreach ( array( 'script', 'style', 'img', 'font', 'connect', 'frame', 'worker' ) as $csp_type ) {
			$settings[ 'csp_' . $csp_type . '_sources' ] = self::sanitize_csp_sources( $input[ 'csp_' . $csp_type . '_sources' ] ?? $settings[ 'csp_' . $csp_type . '_sources' ] );
		}

		return $settings;
	}

	public static function lines( string $value ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $value ) ?: array();
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	public static function tracking_id_type( string $value ): string {
		$value = strtoupper( trim( $value ) );
		if ( preg_match( '/^G-[A-Z0-9]{6,}$/' , $value ) ) return 'ga4';
		if ( preg_match( '/^GTM-[A-Z0-9]{4,}$/' , $value ) ) return 'gtm';
		return '' === $value ? 'none' : 'invalid';
	}

	public static function valid_tracking_id( string $value ): bool {
		return in_array( self::tracking_id_type( $value ), array( 'ga4', 'gtm' ), true );
	}

	private static function sanitize_tracking_id( mixed $value ): string {
		$value = strtoupper( preg_replace( '/\s+/', '', (string) $value ) ?: '' );
		return self::valid_tracking_id( $value ) ? $value : '';
	}

	/** Keep the saved adapter list finite, predictable and free of plugin slugs. */
	private static function sanitize_analytics_adapters( mixed $value ): array {
		$allowed = array( 'woocommerce', 'forms', 'onesignal', 'power-schedule-manager' );
		$value = is_array( $value ) ? $value : array();
		$value = array_map( 'sanitize_key', $value );
		return array_values( array_intersect( $allowed, array_unique( $value ) ) );
	}

	public static function valid_clarity_project_id( string $value ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9]{5,40}$/', trim( $value ) );
	}

	private static function sanitize_clarity_project_id( mixed $value ): string {
		$value = trim( sanitize_text_field( (string) $value ) );
		return self::valid_clarity_project_id( $value ) ? $value : '';
	}

	private static function sanitize_csp_sources( mixed $value ): string {
		$tokens = preg_split( '/[\s,]+/', trim( (string) $value ) ) ?: array();
		$allowed = array();
		foreach ( $tokens as $token ) {
			$token = trim( $token );
			if ( '' === $token ) {
				continue;
			}
			if ( in_array( $token, array( "'self'", "'none'", "'unsafe-inline'", "'unsafe-eval'", 'data:', 'blob:', 'https:', 'http:' ), true ) ) {
				$allowed[] = $token;
				continue;
			}
			$scheme = wp_parse_url( $token, PHP_URL_SCHEME );
			$host = wp_parse_url( $token, PHP_URL_HOST );
			if ( in_array( $scheme, array( 'https', 'http' ), true ) && is_string( $host ) && preg_match( '/^(?:\*\.)?[a-z0-9.-]+$/i', $host ) ) {
				$allowed[] = esc_url_raw( $token );
			}
		}
		return implode( "\n", array_values( array_unique( $allowed ) ) );
	}

	/**
	 * A preload without a valid `as` value is silently ignored by browsers in
	 * practice. Keep the supported resource classes in one place so the runtime
	 * and the administrator diagnostics always agree.
	 */
	public static function preload_as( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return match ( $extension ) {
			'woff', 'woff2', 'ttf', 'otf' => 'font',
			'css' => 'style',
			'js' => 'script',
			'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg' => 'image',
			default => '',
		};
	}

	private static function sanitize_lines( mixed $value ): string {
		return implode( "\n", array_map( 'sanitize_key', self::lines( is_string( $value ) ? $value : '' ) ) );
	}

	private static function sanitize_urls( mixed $value ): string {
		$urls = array();
		foreach ( self::lines( is_string( $value ) ? $value : '' ) as $url ) {
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}
		return implode( "\n", array_unique( $urls ) );
	}

	private static function sanitize_url_prefixes( mixed $value ): string {
		$prefixes = array();
		foreach ( self::lines( is_string( $value ) ? $value : '' ) as $prefix ) {
			$prefix = '/' . ltrim( (string) wp_parse_url( '/' . ltrim( $prefix, '/' ), PHP_URL_PATH ), '/' );
			$prefix = '/' . ltrim( sanitize_text_field( $prefix ), '/' );
			if ( '/' !== $prefix ) $prefixes[] = $prefix;
		}
		return implode( "\n", array_unique( $prefixes ) );
	}

	private static function sanitize_contexts( mixed $value ): string {
		$allowed = array( 'authenticated', 'admin', 'preview', 'checkout', 'login', 'feed', 'rest' );
		$contexts = array();
		foreach ( self::lines( is_string( $value ) ? $value : '' ) as $context ) {
			$context = sanitize_key( $context );
			if ( in_array( $context, $allowed, true ) ) $contexts[] = $context;
		}
		return implode( "\n", array_unique( $contexts ) );
	}

	private static function sanitize_same_site_url( mixed $value ): string {
		$url = esc_url_raw( is_string( $value ) ? trim( $value ) : '', array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return '' !== $host && $host === $site_host ? $url : '';
	}

	private static function sanitize_rules( mixed $rules ): array {
		$clean = array();
		if ( ! is_array( $rules ) ) {
			return $clean;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$type = (string) ( $rule['type'] ?? '' );
			$scope = (string) ( $rule['scope'] ?? '' );
			$handle = sanitize_key( (string) ( $rule['handle'] ?? '' ) );
			$value = sanitize_text_field( (string) ( $rule['value'] ?? '' ) );
			if ( ! in_array( $type, array( 'script', 'style' ), true ) || '' === $handle ) {
				continue;
			}
			if ( ! in_array( $scope, array( 'everywhere', 'front-page', 'page', 'post-type', 'url-prefix', 'has-shortcode', 'missing-shortcode', 'has-block', 'missing-block' ), true ) ) {
				$scope = 'everywhere';
			}
			if ( 'url-prefix' === $scope ) {
				$value = '/' . ltrim( $value, '/' );
			}
			if ( in_array( $scope, array( 'has-shortcode', 'missing-shortcode' ), true ) ) {
				$value = sanitize_key( $value );
			}
			if ( in_array( $scope, array( 'has-block', 'missing-block' ), true ) ) {
				$value = strtolower( (string) preg_replace( '/[^a-z0-9_\/-]/', '', $value ) );
			}
			if ( in_array( $scope, array( 'has-shortcode', 'missing-shortcode', 'has-block', 'missing-block' ), true ) && '' === $value ) {
				continue;
			}
			$clean[] = compact( 'type', 'handle', 'scope', 'value' );
		}

		return array_slice( $clean, 0, 100 );
	}
}
