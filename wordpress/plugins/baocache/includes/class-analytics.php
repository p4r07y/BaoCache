<?php
defined( 'ABSPATH' ) || exit;

/**
 * Consent-aware analytics bootstrap.
 *
 * IDs are intentionally not treated as secrets: Google and Clarity identifiers
 * are sent to public visitors by design. BaoCache never calls a vendor API,
 * claims a realtime receipt, or stores visitor events server-side.
 */
final class BaoCache_Analytics {
	private static array $queued_events = array();

	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_bootstrap_config' ), 1 );
		add_action( 'wp_body_open', array( $this, 'print_gtm_noscript' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_event_listener' ), 20 );
	}

	public static function status( ?array $settings = null ): array {
		$settings = is_array( $settings ) ? $settings : BaoCache_Settings::get();
		$id = (string) ( $settings['analytics_id'] ?? '' );
		$type = BaoCache_Settings::tracking_id_type( $id );
		$enabled = ! empty( $settings['analytics_enabled'] ) && in_array( $type, array( 'ga4', 'gtm' ), true );
		$clarity_id = (string) ( $settings['clarity_project_id'] ?? '' );
		$clarity_enabled = ! empty( $settings['clarity_enabled'] ) && '' !== $clarity_id;
		$consent = (string) ( $settings['analytics_consent_mode'] ?? 'unset' );
		$adapters = self::adapters( $settings );
		$enabled_adapters = array_values( array_keys( array_filter( $adapters, static fn( array $adapter ): bool => ! empty( $adapter['enabled'] ) ) ) );
		return array(
			'id' => $id,
			'type' => $type,
			'enabled' => $enabled,
			'clarity_enabled' => $clarity_enabled,
			'clarity_id' => $clarity_id,
			'consent' => in_array( $consent, array( 'unset', 'denied', 'granted' ), true ) ? $consent : 'unset',
			'auto_events' => $enabled && ! empty( $settings['analytics_auto_events'] ) && in_array( $consent, array( 'denied', 'granted' ), true ),
			'adapters' => $adapters,
			'enabled_adapters' => $enabled && ! empty( $settings['analytics_auto_events'] ) && in_array( $consent, array( 'denied', 'granted' ), true ) ? $enabled_adapters : array(),
		);
	}

	/**
	 * Report only integrations which are active in this WordPress process.
	 * This deliberately does not scan the filesystem or call external vendors.
	 */
	public static function adapters( ?array $settings = null ): array {
		$settings = is_array( $settings ) ? $settings : BaoCache_Settings::get();
		$selected = is_array( $settings['analytics_adapters'] ?? null ) ? $settings['analytics_adapters'] : array();
		$available = array(
			'woocommerce' => class_exists( 'WooCommerce' ),
			'forms' => defined( 'WPCF7_VERSION' ) || class_exists( 'GFCommon' ) || class_exists( 'FluentForm\\App\\Services\\FormBuilder\\FormBuilder' ),
			'onesignal' => defined( 'ONESIGNAL_VERSION' ) || defined( 'ONESIGNAL_PLUGIN_VERSION' ),
			'power-schedule-manager' => defined( 'POWER_SCHEDULE_MANAGER_VERSION' ),
		);
		$labels = array(
			'woocommerce' => __( 'WooCommerce', 'baocache' ),
			'forms' => __( 'Contact form', 'baocache' ),
			'onesignal' => __( 'OneSignal', 'baocache' ),
			'power-schedule-manager' => __( 'Power Schedule Manager', 'baocache' ),
		);
		$result = array();
		foreach ( $available as $key => $is_available ) {
			$result[ $key ] = array(
				'label' => $labels[ $key ],
				'available' => $is_available,
				'enabled' => $is_available && in_array( $key, $selected, true ),
			);
		}
		return $result;
	}

	/** Extension point for a first-party plugin during a public frontend request. */
	public static function queue_event( string $event, array $parameters = array() ): void {
		$event = sanitize_key( $event );
		if ( '' === $event ) {
			return;
		}
		$clean = array();
		foreach ( array_slice( $parameters, 0, 12, true ) as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' !== $key && is_scalar( $value ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		self::$queued_events[] = array( 'event' => $event, 'parameters' => $clean );
		self::$queued_events = array_slice( self::$queued_events, -20 );
	}

	public function print_bootstrap_config(): void {
		if ( ! $this->public_context() ) {
			return;
		}
		$status = self::status();
		if ( ! $status['enabled'] && ! $status['clarity_enabled'] ) {
			return;
		}
		$config = array(
			'provider' => $status['type'],
			'id' => $status['id'],
			'consent' => $status['consent'],
			'clarity' => $status['clarity_enabled'] ? $status['clarity_id'] : '',
			'events' => $status['auto_events'],
			'adapters' => $status['enabled_adapters'],
			'serverEvents' => $status['auto_events'] ? self::$queued_events : array(),
			'is404' => is_404(),
		);
		wp_register_script( 'baocache-analytics-bootstrap', BAOCACHE_URL . 'assets/baocache-analytics-bootstrap.js', array(), BAOCACHE_VERSION, array( 'in_footer' => false, 'strategy' => 'defer' ) );
		wp_enqueue_script( 'baocache-analytics-bootstrap' );
		// A meta element keeps configuration CSP-safe: no executable inline script.
		echo '<meta name="baocache-analytics-config" content="' . esc_attr( (string) wp_json_encode( $config ) ) . '">';
	}

	public function print_gtm_noscript(): void {
		if ( ! $this->public_context() ) {
			return;
		}
		$status = self::status();
		if ( ! $status['enabled'] || 'gtm' !== $status['type'] ) {
			return;
		}
		$url = 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $status['id'] );
		// `hidden` avoids the inline style attribute in Google's stock noscript
		// snippet, so BaoCache itself does not require `style-src 'unsafe-inline'`.
		echo '<noscript><iframe src="' . esc_url( $url ) . '" height="0" width="0" hidden title="Google Tag Manager"></iframe></noscript>';
	}

	public function enqueue_event_listener(): void {
		if ( ! $this->public_context() ) {
			return;
		}
		$status = self::status();
		if ( ! $status['auto_events'] ) {
			return;
		}
		wp_register_script( 'baocache-analytics-bootstrap', BAOCACHE_URL . 'assets/baocache-analytics-bootstrap.js', array(), BAOCACHE_VERSION, array( 'in_footer' => false, 'strategy' => 'defer' ) );
		wp_enqueue_script( 'baocache-analytics-events', BAOCACHE_URL . 'assets/baocache-analytics-events.js', array( 'baocache-analytics-bootstrap' ), BAOCACHE_VERSION, true );
		if ( ! empty( $status['enabled_adapters'] ) ) {
			wp_enqueue_script( 'baocache-analytics-adapters', BAOCACHE_URL . 'assets/baocache-analytics-adapters.js', array( 'baocache-analytics-events' ), BAOCACHE_VERSION, true );
		}
	}

	private function public_context(): bool {
		return ! is_admin() && ! is_feed() && ! wp_doing_ajax() && ! current_user_can( 'manage_options' );
	}
}
