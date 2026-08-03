<?php
/**
 * Versioned public application API.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes a deliberately small, read-only contract for future applications.
 */
final class Power_Schedule_Manager_API {

	public const string REST_NAMESPACE = 'power-schedule/v1';

	private const int MAX_RANGE_DAYS = 31;

	private const int MAX_PAGE_SIZE = 100;

	private const int DEFAULT_PAGE_SIZE = 25;

	private const int DEFAULT_RATE_LIMIT = 180;

	private const string CLIENT_TOKENS_OPTION =
		'power_schedule_manager_api_client_tokens';

	private const string NEW_TOKEN_TRANSIENT_PREFIX =
		'psm_api_new_token_';

	private const string SECURITY_STATS_TRANSIENT =
		'psm_application_api_security_stats';

	/**
	 * Register API routes.
	 */
	public function register(): void {
		add_action(
			'rest_api_init',
			array( $this, 'register_routes' )
		);
		add_action(
			'admin_post_psm_create_api_client_token',
			array( $this, 'create_client_token' )
		);
		add_action(
			'admin_post_psm_revoke_api_client_token',
			array( $this, 'revoke_client_token' )
		);
	}

	/**
	 * Register the stable v1 read contract.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/',
			$this->route( array( $this, 'get_meta' ) )
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/units',
			$this->route( array( $this, 'get_units' ) )
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/schedules',
			$this->route(
				array( $this, 'get_schedules' ),
				$this->schedule_arguments()
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/schedules/(?P<event_id>\d+)',
			$this->route(
				array( $this, 'get_schedule' ),
				array(
					'event_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn (
							mixed $value
						): bool => is_numeric( $value )
							&& (int) $value > 0,
					),
				)
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/schedules/(?P<event_id>\d+)/locations',
			$this->route(
				array( $this, 'get_schedule_locations' ),
				array(
					'event_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn (
							mixed $value
						): bool => is_numeric( $value )
							&& (int) $value > 0,
					),
				)
			)
		);
	}

	/**
	 * Whether the future application API is intentionally exposed.
	 */
	public static function enabled(): bool {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);

		return is_array( $settings )
			&& ! empty( $settings['api_enabled'] );
	}

	/**
	 * Hide disabled API routes and rate-limit enabled public access.
	 */
	public static function authorize_public_api(): bool|WP_Error {
		if ( ! self::enabled() ) {
			self::record_security_event( 'disabled' );
			return new WP_Error(
				'psm_api_disabled',
				__( 'Không tìm thấy API.', 'power-schedule-manager' ),
				array( 'status' => 404 )
			);
		}

		$client = self::authenticate_client_token();
		if ( is_wp_error( $client ) ) {
			self::record_security_event( 'authentication_required' );
			return $client;
		}

		$rate_limit = self::rate_limit( $client );
		self::record_security_event(
			is_wp_error( $rate_limit ) ? 'rate_limited' : 'allowed'
		);

		return $rate_limit;
	}

	/**
	 * Shared public rate limit used by all plugin REST endpoints.
	 */
	public static function rate_limit( array $client = array() ): bool|WP_Error {
		$configured_limit = absint(
			$client['rate_limit'] ?? self::DEFAULT_RATE_LIMIT
		);
		$limit = (int) apply_filters(
			'power_schedule_manager_api_rate_limit',
			$configured_limit > 0
				? $configured_limit
				: self::DEFAULT_RATE_LIMIT,
			$client
		);
		$limit = min( 3000, max( 30, $limit ) );
		$bucket = (int) floor( time() / MINUTE_IN_SECONDS );
		$client_id = sanitize_key( (string) ( $client['id'] ?? '' ) );
		$identifier_source = $client_id;
		if ( '' === $identifier_source ) {
			$address = isset( $_SERVER['REMOTE_ADDR'] )
				&& is_string( $_SERVER['REMOTE_ADDR'] )
					? trim( $_SERVER['REMOTE_ADDR'] )
					: 'unknown';
			$address = trim(
				(string) apply_filters(
					'power_schedule_manager_api_client_address',
					$address
				)
			);
			if (
				'unknown' !== $address
				&& false === filter_var(
					$address,
					FILTER_VALIDATE_IP
				)
			) {
				$address = 'unknown';
			}
			$identifier_source = 'ip:' . $address;
		}
		$identifier = hash_hmac(
			'sha256',
			$identifier_source,
			wp_salt( 'nonce' )
		);
		$key = 'api_rate_' . $bucket . '_' . substr(
			$identifier,
			0,
			24
		);
		if ( wp_using_ext_object_cache() ) {
			$count = wp_cache_get( $key, 'power_schedule_manager' );

			if ( false === $count ) {
				$added = wp_cache_add(
					$key,
					1,
					'power_schedule_manager',
					MINUTE_IN_SECONDS + 10
				);

				$count = $added
					? 1
					: wp_cache_incr(
						$key,
						1,
						'power_schedule_manager'
					);
			} else {
				$count = wp_cache_incr(
					$key,
					1,
					'power_schedule_manager'
				);
			}

			if ( false === $count ) {
				$count = self::increment_transient_rate_limit( $key );
			}
		} else {
			$count = self::increment_transient_rate_limit( $key );
		}

		if ( (int) $count <= $limit ) {
			return true;
		}

		return new WP_Error(
			'psm_api_rate_limited',
			__(
				'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.',
				'power-schedule-manager'
			),
			array(
				'status'  => 429,
				'headers' => array( 'Retry-After' => '60' ),
			)
		);
	}

	/**
	 * Return API client records without ever exposing a plaintext token.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function client_tokens(): array {
		$tokens = get_option( self::CLIENT_TOKENS_OPTION, array() );
		if ( ! is_array( $tokens ) ) {
			return array();
		}

		$tokens = array_values(
			array_filter( $tokens, 'is_array' )
		);
		usort(
			$tokens,
			static fn ( array $left, array $right ): int =>
				strcmp(
					(string) ( $right['created_at_utc'] ?? '' ),
					(string) ( $left['created_at_utc'] ?? '' )
				)
		);

		return $tokens;
	}

	/**
	 * Consume the one-time plaintext token after creation.
	 *
	 * @return array<string,string>
	 */
	public static function consume_new_client_token(): array {
		if ( ! is_user_logged_in() ) {
			return array();
		}
		$key = self::NEW_TOKEN_TRANSIENT_PREFIX . get_current_user_id();
		$value = get_transient( $key );
		delete_transient( $key );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Create a separately revocable client credential.
	 */
	public function create_client_token(): never {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die( esc_html__( 'Bạn không có quyền.', 'power-schedule-manager' ) );
		}
		check_admin_referer( 'psm_create_api_client_token' );

		$name = isset( $_POST['client_name'] )
			? sanitize_text_field(
				wp_unslash( (string) $_POST['client_name'] )
			)
			: '';
		if ( '' === $name ) {
			$this->redirect_to_api_settings( 'api_token_invalid' );
		}
		$rate_limit = min(
			3000,
			max( 30, absint( $_POST['client_rate_limit'] ?? 120 ) )
		);
		$allowed_origin = isset( $_POST['client_origin'] )
			? untrailingslashit(
				esc_url_raw(
					wp_unslash( (string) $_POST['client_origin'] ),
					array( 'https' )
				)
			)
			: '';
		$expires = isset( $_POST['client_expires'] )
			? sanitize_text_field(
				wp_unslash( (string) $_POST['client_expires'] )
			)
			: '';
		$expires_at_utc = '';
		if ( '' !== $expires ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $expires );
			if ( false === $date ) {
				$this->redirect_to_api_settings( 'api_token_invalid' );
			}
			$expires_at_utc = get_gmt_from_date(
				$expires . ' 23:59:59'
			);
		}

		try {
			$plaintext = 'psm_' . bin2hex( random_bytes( 24 ) );
		} catch ( Throwable ) {
			$this->redirect_to_api_settings( 'api_token_error' );
		}
		$id = wp_generate_uuid4();
		$tokens = self::client_tokens();
		$tokens[] = array(
			'id'             => $id,
			'name'           => self::limit_text( $name, 100 ),
			'prefix'         => substr( $plaintext, 0, 12 ),
			'token_hash'     => self::token_hash( $plaintext ),
			'rate_limit'     => $rate_limit,
			'allowed_origin' => $allowed_origin,
			'created_at_utc' => current_time( 'mysql', true ),
			'expires_at_utc' => $expires_at_utc,
			'last_used_at_utc' => '',
			'revoked_at_utc' => '',
		);
		update_option( self::CLIENT_TOKENS_OPTION, $tokens, false );
		set_transient(
			self::NEW_TOKEN_TRANSIENT_PREFIX . get_current_user_id(),
			array(
				'name'  => $name,
				'token' => $plaintext,
			),
			10 * MINUTE_IN_SECONDS
		);
		$this->redirect_to_api_settings( 'api_token_created' );
	}

	/**
	 * Revoke one client without affecting other integrations.
	 */
	public function revoke_client_token(): never {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die( esc_html__( 'Bạn không có quyền.', 'power-schedule-manager' ) );
		}
		$id = isset( $_POST['client_id'] )
			? sanitize_text_field(
				wp_unslash( (string) $_POST['client_id'] )
			)
			: '';
		check_admin_referer( 'psm_revoke_api_client_token_' . $id );
		$tokens = self::client_tokens();
		foreach ( $tokens as &$token ) {
			if (
				hash_equals(
					(string) ( $token['id'] ?? '' ),
					$id
				)
			) {
				$token['revoked_at_utc'] = current_time( 'mysql', true );
				break;
			}
		}
		unset( $token );
		update_option( self::CLIENT_TOKENS_OPTION, $tokens, false );
		$this->redirect_to_api_settings( 'api_token_revoked' );
	}

	/**
	 * Authenticate the bearer credential and return its client record.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private static function authenticate_client_token(): array|WP_Error {
		$authorization = isset( $_SERVER['HTTP_AUTHORIZATION'] )
			&& is_string( $_SERVER['HTTP_AUTHORIZATION'] )
				? trim( $_SERVER['HTTP_AUTHORIZATION'] )
				: '';
		if (
			'' === $authorization
			&& isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] )
			&& is_string( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] )
		) {
			$authorization = trim(
				$_SERVER['REDIRECT_HTTP_AUTHORIZATION']
			);
		}
		if (
			1 !== preg_match(
				'/^Bearer\s+([A-Za-z0-9_]+)$/i',
				$authorization,
				$matches
			)
		) {
			return self::authentication_error();
		}

		$presented_hash = self::token_hash( (string) $matches[1] );
		foreach ( self::client_tokens() as $client ) {
			$stored_hash = (string) ( $client['token_hash'] ?? '' );
			if (
				'' === $stored_hash
				|| ! hash_equals( $stored_hash, $presented_hash )
				|| '' !== (string) ( $client['revoked_at_utc'] ?? '' )
			) {
				continue;
			}
			$expires = (string) ( $client['expires_at_utc'] ?? '' );
			if (
				'' !== $expires
				&& strtotime( $expires . ' UTC' ) < time()
			) {
				return new WP_Error(
					'psm_api_token_expired',
					__( 'Token API đã hết hạn.', 'power-schedule-manager' ),
					array( 'status' => 401 )
				);
			}
			$origin_error = self::validate_client_origin( $client );
			if ( is_wp_error( $origin_error ) ) {
				return $origin_error;
			}
			self::touch_client_token( (string) $client['id'] );

			return $client;
		}

		return self::authentication_error();
	}

	/**
	 * Return a generic authentication response to avoid token enumeration.
	 */
	private static function authentication_error(): WP_Error {
		return new WP_Error(
			'psm_api_token_required',
			__(
				'API yêu cầu token Bearer hợp lệ.',
				'power-schedule-manager'
			),
			array( 'status' => 401 )
		);
	}

	/**
	 * Enforce a configured browser origin when an Origin header is present.
	 */
	private static function validate_client_origin(
		array $client
	): bool|WP_Error {
		$allowed = untrailingslashit(
			(string) ( $client['allowed_origin'] ?? '' )
		);
		$origin = isset( $_SERVER['HTTP_ORIGIN'] )
			&& is_string( $_SERVER['HTTP_ORIGIN'] )
				? untrailingslashit( trim( $_SERVER['HTTP_ORIGIN'] ) )
				: '';
		if (
			'' !== $allowed
			&& '' !== $origin
			&& ! hash_equals( strtolower( $allowed ), strtolower( $origin ) )
		) {
			return new WP_Error(
				'psm_api_origin_not_allowed',
				__( 'Origin không được phép dùng token này.', 'power-schedule-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Persist last use at most once per five minutes per client.
	 */
	private static function touch_client_token( string $id ): void {
		$key = 'psm_api_touch_' . substr( md5( $id ), 0, 20 );
		if ( false !== get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
		$tokens = self::client_tokens();
		foreach ( $tokens as &$token ) {
			if ( hash_equals( (string) ( $token['id'] ?? '' ), $id ) ) {
				$token['last_used_at_utc'] = current_time( 'mysql', true );
				break;
			}
		}
		unset( $token );
		update_option( self::CLIENT_TOKENS_OPTION, $tokens, false );
	}

	/**
	 * Store only a keyed digest of each high-entropy credential.
	 */
	private static function token_hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * Limit a label without requiring the admin service helper.
	 */
	private static function limit_text( string $value, int $length ): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, $length )
			: substr( $value, 0, $length );
	}

	/**
	 * Return to the API configuration panel.
	 */
	private function redirect_to_api_settings( string $notice ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => Power_Schedule_Manager_Admin::DATA_SOURCES_SLUG,
					'settings_tab' => 'application-api',
					'psm_notice'   => $notice,
				),
				admin_url( 'admin.php' )
			) . '#application-api'
		);
		exit;
	}

	/**
	 * Increment the fallback rate-limit counter.
	 */
	private static function increment_transient_rate_limit(
		string $key
	): int {
		$transient_key = 'psm_' . $key;
		$count = absint( get_transient( $transient_key ) ) + 1;
		set_transient(
			$transient_key,
			$count,
			MINUTE_IN_SECONDS + 10
		);

		return $count;
	}

	/**
	 * Return privacy-preserving aggregate API traffic counters.
	 *
	 * @return array<string,mixed>
	 */
	public static function security_stats(): array {
		$stats = get_transient( self::SECURITY_STATS_TRANSIENT );
		$stats = is_array( $stats ) ? $stats : array();
		$paths = isset( $stats['paths'] ) && is_array( $stats['paths'] )
			? array_map( 'absint', $stats['paths'] )
			: array();
		arsort( $paths );

		return array(
			'since'          => sanitize_text_field(
				(string) ( $stats['since'] ?? '' )
			),
			'total'          => absint( $stats['total'] ?? 0 ),
			'allowed'        => absint( $stats['allowed'] ?? 0 ),
			'blocked'        => absint( $stats['blocked'] ?? 0 ),
			'suspicious'     => absint( $stats['suspicious'] ?? 0 ),
			'unique_clients' => isset( $stats['clients'] )
				&& is_array( $stats['clients'] )
					? count( $stats['clients'] )
					: 0,
			'paths'          => array_slice( $paths, 0, 6, true ),
		);
	}

	/**
	 * Reset the anonymous aggregate counters from the administration screen.
	 */
	public static function reset_security_stats(): void {
		delete_transient( self::SECURITY_STATS_TRANSIENT );
	}

	/**
	 * Record bounded counters without storing raw IP addresses or user agents.
	 */
	private static function record_security_event( string $result ): void {
		$stats = get_transient( self::SECURITY_STATS_TRANSIENT );
		$stats = is_array( $stats ) ? $stats : array();
		if ( empty( $stats['since'] ) ) {
			$stats['since'] = gmdate( 'Y-m-d H:i:s' );
		}
		$stats['total'] = absint( $stats['total'] ?? 0 ) + 1;
		if ( 'allowed' === $result ) {
			$stats['allowed'] = absint( $stats['allowed'] ?? 0 ) + 1;
		} else {
			$stats['blocked'] = absint( $stats['blocked'] ?? 0 ) + 1;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			&& is_string( $_SERVER['HTTP_USER_AGENT'] )
				? strtolower( trim( $_SERVER['HTTP_USER_AGENT'] ) )
				: '';
		if (
			'' === $user_agent
			|| 1 === preg_match(
				'/(?:bot|crawl|spider|scrapy|curl|wget|python|httpclient|axios|go-http-client)/',
				$user_agent
			)
		) {
			$stats['suspicious'] = absint(
				$stats['suspicious'] ?? 0
			) + 1;
		}

		$request_path = isset( $_SERVER['REQUEST_URI'] )
			&& is_string( $_SERVER['REQUEST_URI'] )
				? (string) wp_parse_url(
					wp_unslash( $_SERVER['REQUEST_URI'] ),
					PHP_URL_PATH
				)
				: '/';
		$request_path = sanitize_text_field( $request_path ?: '/' );
		$stats['paths'] = isset( $stats['paths'] )
			&& is_array( $stats['paths'] )
				? $stats['paths']
				: array();
		$stats['paths'][ $request_path ] = absint(
			$stats['paths'][ $request_path ] ?? 0
		) + 1;
		if ( count( $stats['paths'] ) > 20 ) {
			arsort( $stats['paths'] );
			$stats['paths'] = array_slice(
				$stats['paths'],
				0,
				20,
				true
			);
		}

		$address = isset( $_SERVER['REMOTE_ADDR'] )
			&& is_string( $_SERVER['REMOTE_ADDR'] )
				? trim( $_SERVER['REMOTE_ADDR'] )
				: 'unknown';
		$address = trim(
			(string) apply_filters(
				'power_schedule_manager_api_client_address',
				$address
			)
		);
		if (
			'unknown' !== $address
			&& false === filter_var( $address, FILTER_VALIDATE_IP )
		) {
			$address = 'unknown';
		}
		$client = substr(
			hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ),
			0,
			20
		);
		$stats['clients'] = isset( $stats['clients'] )
			&& is_array( $stats['clients'] )
				? $stats['clients']
				: array();
		if (
			isset( $stats['clients'][ $client ] )
			|| count( $stats['clients'] ) < 500
		) {
			$stats['clients'][ $client ] = absint(
				$stats['clients'][ $client ] ?? 0
			) + 1;
		}

		set_transient(
			self::SECURITY_STATS_TRANSIENT,
			$stats,
			8 * DAY_IN_SECONDS
		);
	}

	/**
	 * API discovery metadata without exposing WordPress internals.
	 */
	public function get_meta(
		WP_REST_Request $request
	): WP_REST_Response {
		return $this->response(
			$request,
			array(
				'api_version' => 'v1',
				'timezone'    => POWER_SCHEDULE_MANAGER_TIMEZONE,
				'language'    => 'vi',
				'links'       => array(
					'units'     => rest_url(
						self::REST_NAMESPACE . '/units'
					),
					'schedules' => rest_url(
						self::REST_NAMESPACE . '/schedules'
					),
				),
			),
			300
		);
	}

	/**
	 * Return public electricity units.
	 */
	public function get_units(
		WP_REST_Request $request
	): WP_REST_Response {
		$items = Power_Schedule_Manager_Cache::remember(
			'api_units',
			array( 'version' => 1 ),
			static function (): array {
				$units = Power_Schedule_Manager_Units::all( true );

				return array_map(
					static function ( array $unit ): array {
						$term_id = Power_Schedule_Manager_Units::
							find_term_id_by_unit_code(
								(string) $unit['code']
							);
						$url = $term_id > 0
							? get_term_link(
								$term_id,
								Power_Schedule_Manager_Taxonomy::TAXONOMY
							)
							: '';

						return array(
							'code'     => (string) $unit['code'],
							'name'     => (string) $unit['name'],
							'slug'     => (string) $unit['slug'],
							'region'   => (string) $unit['region'],
							'timezone' => (string) $unit['timezone'],
							'url'      => is_wp_error( $url )
								? ''
								: (string) $url,
						);
					},
					$units
				);
			},
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);

		return $this->response(
			$request,
			array(
				'count' => count( $items ),
				'items' => $items,
			),
			300
		);
	}

	/**
	 * Return one cursor-paginated public schedule page.
	 */
	public function get_schedules(
		WP_REST_Request $request
	): WP_REST_Response|WP_Error {
		try {
			$range = $this->date_range( $request );
			$cursor = $this->decode_cursor(
				(string) $request->get_param( 'cursor' )
			);
			$unit_code = Power_Schedule_Manager_Units::sanitize_code(
				(string) $request->get_param( 'unit' )
			);

			if (
				$request->has_param( 'unit' )
				&& '' !== trim(
					(string) $request->get_param( 'unit' )
				)
				&& '' === $unit_code
			) {
				throw new InvalidArgumentException(
					'invalid_unit_code'
				);
			}
			$limit = min(
				self::MAX_PAGE_SIZE,
				max(
					1,
					absint(
						$request->get_param( 'limit' )
							?: self::DEFAULT_PAGE_SIZE
					)
				)
			);
			$include_completed = rest_sanitize_boolean(
				$request->get_param( 'include_completed' )
			);
			$page = Power_Schedule_Manager_Cache::remember(
				'api_schedules',
				array(
					'from'              => $range['from'],
					'to'                => $range['to'],
					'unit'              => $unit_code,
					'limit'             => $limit,
					'cursor_start'      => $cursor['start'],
					'cursor_id'         => $cursor['id'],
					'include_completed' => $include_completed,
				),
				static fn (): array =>
					Power_Schedule_Manager_Repository::
						query_public_api_page(
							$range['from'],
							$range['to'],
							'' !== $unit_code ? $unit_code : null,
							$limit,
							$cursor['start'],
							$cursor['id'],
							$include_completed
						),
				60
			);
		} catch ( InvalidArgumentException ) {
			return new WP_Error(
				'psm_api_invalid_query',
				__(
					'Khoảng ngày, đơn vị hoặc cursor không hợp lệ.',
					'power-schedule-manager'
				),
				array( 'status' => 400 )
			);
		}

		$items = array_map(
			array( $this, 'format_event' ),
			$page['items']
		);
		$next_cursor = null;

		if ( ! empty( $page['has_more'] ) && array() !== $page['items'] ) {
			$last = $page['items'][ count( $page['items'] ) - 1 ];
			$next_cursor = $this->encode_cursor(
				(string) $last['start_at_utc'],
				absint( $last['id'] )
			);
		}

		return $this->response(
			$request,
			array(
				'date_from'  => $range['from'],
				'date_to'    => $range['to'],
				'count'      => count( $items ),
				'items'      => $items,
				'pagination' => array(
					'limit'       => $limit,
					'has_more'    => ! empty( $page['has_more'] ),
					'next_cursor' => $next_cursor,
				),
			),
			60
		);
	}

	/**
	 * Return one public event.
	 */
	public function get_schedule(
		WP_REST_Request $request
	): WP_REST_Response|WP_Error {
		$event = Power_Schedule_Manager_Repository::find_by_id(
			absint( $request->get_param( 'event_id' ) )
		);

		if ( ! $this->event_is_public( $event ) ) {
			return new WP_Error(
				'psm_api_schedule_not_found',
				__(
					'Không tìm thấy lịch cúp điện công khai.',
					'power-schedule-manager'
				),
				array( 'status' => 404 )
			);
		}

		$unit = Power_Schedule_Manager_Units::find_by_code(
			(string) $event['unit_code']
		);

		if ( null === $unit ) {
			return new WP_Error(
				'psm_api_schedule_not_found',
				__(
					'Không tìm thấy lịch cúp điện công khai.',
					'power-schedule-manager'
				),
				array( 'status' => 404 )
			);
		}

		$event['unit_name'] = $unit['name'];
		$event['unit_slug'] = $unit['slug'];

		return $this->response(
			$request,
			array( 'item' => $this->format_event( $event ) ),
			300
		);
	}

	/**
	 * Return reusable GeoJSON/coordinates linked to one public event.
	 */
	public function get_schedule_locations(
		WP_REST_Request $request
	): WP_REST_Response|WP_Error {
		$event_id = absint( $request->get_param( 'event_id' ) );
		$event = Power_Schedule_Manager_Repository::find_by_id( $event_id );

		if ( ! $this->event_is_public( $event ) ) {
			return new WP_Error(
				'psm_api_schedule_not_found',
				__(
					'Không tìm thấy lịch cúp điện công khai.',
					'power-schedule-manager'
				),
				array( 'status' => 404 )
			);
		}

		$locations = Power_Schedule_Manager_Cache::remember(
			'event_locations',
			array(
				'event_id' => $event_id,
				'public'   => true,
			),
			static fn (): array => Power_Schedule_Manager_Map::locations(
				$event_id,
				true
			),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);

		return $this->response(
			$request,
			array(
				'event_id'  => $event_id,
				'count'     => count( $locations ),
				'locations' => $locations,
			),
			300
		);
	}

	/**
	 * Build a read-only route definition.
	 *
	 * @param callable             $callback Route callback.
	 * @param array<string,mixed> $args Route arguments.
	 * @return array<string,mixed>
	 */
	private function route(
		callable $callback,
		array $args = array()
	): array {
		return array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => $callback,
			'permission_callback' => array(
				self::class,
				'authorize_public_api',
			),
			'args'                => $args,
		);
	}

	/**
	 * Public schedule query schema.
	 *
	 * @return array<string,mixed>
	 */
	private function schedule_arguments(): array {
		return array(
			'from' => array(
				'type' => 'string',
			),
			'to' => array(
				'type' => 'string',
			),
			'unit' => array(
				'type'              => 'string',
				'validate_callback' => static function (
					mixed $value
				): bool {
					if ( ! is_scalar( $value ) ) {
						return false;
					}

					$value = trim( (string) $value );

					return '' === $value
						|| '' !== Power_Schedule_Manager_Units::
							sanitize_code( $value );
				},
				'sanitize_callback' => static fn ( mixed $value ): string =>
					Power_Schedule_Manager_Units::sanitize_code(
						is_scalar( $value ) ? (string) $value : ''
					),
			),
			'limit' => array(
				'type'              => 'integer',
				'default'           => self::DEFAULT_PAGE_SIZE,
				'minimum'           => 1,
				'maximum'           => self::MAX_PAGE_SIZE,
				'sanitize_callback' => 'absint',
			),
			'cursor' => array(
				'type' => 'string',
			),
			'include_completed' => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);
	}

	/**
	 * Resolve and validate a maximum 31-day local date range.
	 *
	 * @return array{from:string,to:string}
	 */
	private function date_range(
		WP_REST_Request $request
	): array {
		$timezone = new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		);
		$today = new DateTimeImmutable( 'today', $timezone );
		$from_value = trim( (string) $request->get_param( 'from' ) );
		$to_value = trim( (string) $request->get_param( 'to' ) );
		$from = '' !== $from_value
			? Power_Schedule_Manager_Validator::validate_local_date(
				$from_value
			)
			: $today->format( 'Y-m-d' );
		$to = '' !== $to_value
			? Power_Schedule_Manager_Validator::validate_local_date(
				$to_value
			)
			: $today->modify( '+7 days' )->format( 'Y-m-d' );
		$from_date = new DateTimeImmutable( $from, $timezone );
		$to_date = new DateTimeImmutable( $to, $timezone );

		if (
			$to_date < $from_date
			|| (int) $from_date->diff( $to_date )->format( '%a' )
				>= self::MAX_RANGE_DAYS
		) {
			throw new InvalidArgumentException( 'invalid_date_range' );
		}

		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Convert one database row to the stable public representation.
	 *
	 * @param array<string,mixed> $event Event row.
	 * @return array<string,mixed>
	 */
	private function format_event( array $event ): array {
		$status = Power_Schedule_Manager_Status::calculate(
			(string) $event['start_at_utc'],
			(string) $event['end_at_utc'],
			(string) $event['status']
		);
		$event_id = absint( $event['id'] );
		$post_id = absint( $event['post_id'] );

		return array(
			'id'          => $event_id,
			'local_date'  => (string) $event['local_date'],
			'start_at'    => gmdate(
				DATE_ATOM,
				strtotime( (string) $event['start_at_utc'] . ' UTC' )
			),
			'end_at'      => gmdate(
				DATE_ATOM,
				strtotime( (string) $event['end_at_utc'] . ' UTC' )
			),
			'timezone'    => POWER_SCHEDULE_MANAGER_TIMEZONE,
			'area'        => (string) $event['area'],
			'reason'      => (string) $event['reason'],
			'status'      => array(
				'code'  => $status,
				'label' => Power_Schedule_Manager_Status::label(
					$status
				),
			),
			'unit'        => array(
				'code' => (string) $event['unit_code'],
				'name' => (string) $event['unit_name'],
				'slug' => (string) $event['unit_slug'],
			),
			'updated_at'  => gmdate(
				DATE_ATOM,
				strtotime( (string) $event['updated_at_utc'] . ' UTC' )
			),
			'links'       => array(
				'web' => $post_id > 0
					? (string) get_permalink( $post_id )
					: '',
				'map' => rest_url(
					self::REST_NAMESPACE
						. '/schedules/'
						. $event_id
						. '/locations'
				),
			),
		);
	}

	/**
	 * Verify public visibility without revealing why a row is hidden.
	 *
	 * @param array<string,mixed>|null $event Event row.
	 */
	private function event_is_public( ?array $event ): bool {
		if ( null === $event || null !== $event['deleted_at_utc'] ) {
			return false;
		}

		if (
			in_array(
				(string) $event['status'],
				array(
					Power_Schedule_Manager_Status::CANCELLED,
					Power_Schedule_Manager_Status::REMOVED,
				),
				true
			)
			|| ! Power_Schedule_Manager_Units::is_public(
				(string) $event['unit_code']
			)
		) {
			return false;
		}

		return 'publish' === get_post_status(
			absint( $event['post_id'] )
		);
	}

	/**
	 * Return a cacheable response with conditional ETag handling.
	 */
	private function response(
		WP_REST_Request $request,
		array $data,
		int $ttl
	): WP_REST_Response {
		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$etag = '"' . hash(
			'sha256',
			is_string( $json ) ? $json : ''
		) . '"';
		$not_modified = hash_equals(
			$etag,
			trim( $request->get_header( 'if-none-match' ) )
		);
		$response = new WP_REST_Response(
			$not_modified ? null : $data,
			$not_modified ? 304 : 200
		);
		$response->header( 'ETag', $etag );
		$response->header(
			'Cache-Control',
			'private, max-age=' . max( 0, $ttl )
				. ', stale-while-revalidate=30'
		);
		$response->header( 'Vary', 'Authorization' );
		$response->header( 'X-API-Version', 'v1' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header(
			'X-Robots-Tag',
			'noindex, nofollow, nosnippet'
		);

		return $response;
	}

	/**
	 * Encode a signed opaque cursor.
	 */
	private function encode_cursor(
		string $start_at_utc,
		int $event_id
	): string {
		$payload = wp_json_encode(
			array(
				's' => $start_at_utc,
				'i' => $event_id,
			)
		);
		$payload = is_string( $payload ) ? $payload : '';
		$encoded = $this->base64url_encode( $payload );
		$signature = hash_hmac(
			'sha256',
			$encoded,
			wp_salt( 'nonce' ),
			true
		);

		return $encoded . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Decode and verify a cursor.
	 *
	 * @return array{start:string|null,id:int}
	 */
	private function decode_cursor( string $cursor ): array {
		$cursor = trim( $cursor );

		if ( '' === $cursor ) {
			return array(
				'start' => null,
				'id'    => 0,
			);
		}

		$parts = explode( '.', $cursor, 2 );

		if ( 2 !== count( $parts ) ) {
			throw new InvalidArgumentException( 'invalid_cursor' );
		}

		$expected = hash_hmac(
			'sha256',
			$parts[0],
			wp_salt( 'nonce' ),
			true
		);
		$provided = $this->base64url_decode( $parts[1] );

		if ( ! hash_equals( $expected, $provided ) ) {
			throw new InvalidArgumentException( 'invalid_cursor' );
		}

		$decoded = json_decode(
			$this->base64url_decode( $parts[0] ),
			true
		);

		if (
			! is_array( $decoded )
			|| ! isset( $decoded['s'], $decoded['i'] )
			|| ! is_string( $decoded['s'] )
			|| absint( $decoded['i'] ) < 1
		) {
			throw new InvalidArgumentException( 'invalid_cursor' );
		}

		return array(
			'start' => $decoded['s'],
			'id'    => absint( $decoded['i'] ),
		);
	}

	/**
	 * Base64url encode without padding.
	 */
	private function base64url_encode( string $value ): string {
		return rtrim(
			strtr( base64_encode( $value ), '+/', '-_' ),
			'='
		);
	}

	/**
	 * Strict base64url decode.
	 */
	private function base64url_decode( string $value ): string {
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_-]+\z/', $value ) ) {
			throw new InvalidArgumentException( 'invalid_cursor' );
		}

		$padding = strlen( $value ) % 4;

		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		$decoded = base64_decode(
			strtr( $value, '-_', '+/' ),
			true
		);

		if ( false === $decoded ) {
			throw new InvalidArgumentException( 'invalid_cursor' );
		}

		return $decoded;
	}
}
