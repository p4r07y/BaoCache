<?php
/**
 * Bounded Cloudflare page-cache invalidation.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queues affected public URLs and purges them in small Cloudflare API batches.
 */
final class Power_Schedule_Manager_Cloudflare {

	public const string WORKER_HOOK =
		'power_schedule_manager_cloudflare_purge';

	private const string QUEUE_OPTION =
		'power_schedule_manager_cloudflare_queue';

	private const string RETRY_TRANSIENT =
		'power_schedule_manager_cloudflare_retry';

	private const int BATCH_SIZE = 30;

	private const int MAX_QUEUE_SIZE = 500;

	/**
	 * Register invalidation and worker hooks.
	 */
	public function register(): void {
		add_action(
			'power_schedule_manager_page_cache_purge',
			array( $this, 'enqueue' ),
			20,
			2
		);

		add_action(
			self::WORKER_HOOK,
			array( $this, 'process' )
		);
	}

	/**
	 * Add only affected public URLs to the queue.
	 *
	 * @param string               $reason Invalidation reason.
	 * @param array<string,mixed> $context Event context.
	 */
	public function enqueue( string $reason, array $context ): void {
		if ( ! self::is_configured() ) {
			return;
		}

		$urls = self::urls_for_context( $reason, $context );
		if ( array() === $urls ) {
			return;
		}

		$queued = get_option( self::QUEUE_OPTION, array() );
		$queued = is_array( $queued ) ? $queued : array();

		foreach ( $urls as $url ) {
			$queued[ hash( 'sha256', $url ) ] = $url;
		}

		if ( count( $queued ) > self::MAX_QUEUE_SIZE ) {
			$queued = array_slice(
				$queued,
				-self::MAX_QUEUE_SIZE,
				null,
				true
			);
		}

		update_option( self::QUEUE_OPTION, $queued, false );
		self::schedule_worker( 45 );
	}

	/**
	 * Purge one bounded batch and preserve it for retry on failure.
	 */
	public function process(): void {
		if ( ! self::is_configured() ) {
			return;
		}

		$queued = get_option( self::QUEUE_OPTION, array() );
		$queued = is_array( $queued ) ? $queued : array();

		if ( array() === $queued ) {
			delete_transient( self::RETRY_TRANSIENT );
			return;
		}

		$batch = array_slice(
			$queued,
			0,
			self::BATCH_SIZE,
			true
		);
		$result = self::purge_urls( array_values( $batch ) );

		if ( is_wp_error( $result ) ) {
			$attempt = min(
				6,
				1 + absint( get_transient( self::RETRY_TRANSIENT ) )
			);
			set_transient(
				self::RETRY_TRANSIENT,
				$attempt,
				HOUR_IN_SECONDS
			);
			self::schedule_worker(
				min( HOUR_IN_SECONDS, 60 * ( 2 ** $attempt ) )
			);
			return;
		}

		foreach ( array_keys( $batch ) as $key ) {
			unset( $queued[ $key ] );
		}

		update_option( self::QUEUE_OPTION, $queued, false );
		delete_transient( self::RETRY_TRANSIENT );

		if ( array() !== $queued ) {
			self::schedule_worker( 10 );
		}
	}

	/**
	 * Return non-sensitive integration state for the settings screen.
	 *
	 * @return array{enabled:bool,zone:bool,token:bool,queued:int,turnstile_enabled:bool,turnstile_site_key:bool,turnstile_secret:bool}
	 */
	public static function status(): array {
		$settings = self::settings();
		$queue = get_option( self::QUEUE_OPTION, array() );

		return array(
			'enabled' => ! empty( $settings['cloudflare_enabled'] ),
			'zone'    => self::valid_zone_id(
				(string) ( $settings['cloudflare_zone_id'] ?? '' )
			),
			'token'   => self::api_token() !== '',
			'queued'  => is_array( $queue ) ? count( $queue ) : 0,
			'turnstile_enabled' => ! empty(
				$settings['cloudflare_turnstile_enabled']
			),
			'turnstile_site_key' => '' !== trim(
				(string) (
					$settings['cloudflare_turnstile_site_key'] ?? ''
				)
			),
			'turnstile_secret' => '' !== Power_Schedule_Manager_Secrets::resolve(
				'POWER_SCHEDULE_MANAGER_TURNSTILE_SECRET',
				(string) (
					$settings['cloudflare_turnstile_secret_encrypted']
					?? ''
				)
			),
		);
	}

	/**
	 * Send one exact-URL purge request.
	 *
	 * @param array<int,string> $urls Public URLs.
	 * @return int|WP_Error
	 */
	private static function purge_urls( array $urls ): int|WP_Error {
		$settings = self::settings();
		$zone_id = (string) ( $settings['cloudflare_zone_id'] ?? '' );
		$token = self::api_token();
		$urls = array_values(
			array_filter(
				array_unique( $urls ),
				array( self::class, 'is_same_site_url' )
			)
		);

		if (
			! self::valid_zone_id( $zone_id )
			|| '' === $token
			|| array() === $urls
		) {
			return new WP_Error( 'cloudflare_not_configured' );
		}

		$body = wp_json_encode( array( 'files' => $urls ) );
		if ( ! is_string( $body ) ) {
			return new WP_Error( 'cloudflare_encode_failed' );
		}

		$response = wp_safe_remote_post(
			'https://api.cloudflare.com/client/v4/zones/'
				. rawurlencode( $zone_id )
				. '/purge_cache',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'        => $body,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode(
			(string) wp_remote_retrieve_body( $response ),
			true
		);

		if (
			$code < 200
			|| $code >= 300
			|| ! is_array( $data )
			|| true !== ( $data['success'] ?? false )
		) {
			return new WP_Error(
				'cloudflare_purge_failed',
				sprintf(
					'Cloudflare cache purge failed (HTTP %d).',
					$code
				)
			);
		}

		return $code;
	}

	/**
	 * Resolve public URLs affected by a plugin mutation.
	 *
	 * @param string               $reason Invalidation reason.
	 * @param array<string,mixed> $context Context.
	 * @return array<int,string>
	 */
	private static function urls_for_context(
		string $reason,
		array $context
	): array {
		$urls = array(
			home_url( '/' ),
			get_post_type_archive_link(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			),
		);

		$post_ids = array();
		if ( isset( $context['posts'] ) && is_array( $context['posts'] ) ) {
			$post_ids = array_map( 'absint', $context['posts'] );
		}
		if ( isset( $context['post_id'] ) ) {
			$post_ids[] = absint( $context['post_id'] );
		}

		foreach ( array_unique( $post_ids ) as $post_id ) {
			if ( $post_id < 1 ) {
				continue;
			}
			$permalink = get_permalink( $post_id );
			if ( is_string( $permalink ) ) {
				$urls[] = $permalink;
			}
		}

		if ( 'term' === $reason && ! empty( $context['term_id'] ) ) {
			$link = get_term_link(
				absint( $context['term_id'] ),
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			);
			if ( ! is_wp_error( $link ) ) {
				$urls[] = $link;
			}
		}

		return array_values(
			array_filter(
				array_unique( $urls ),
				array( self::class, 'is_same_site_url' )
			)
		);
	}

	/**
	 * Restrict purges to this WordPress host.
	 */
	private static function is_same_site_url( mixed $url ): bool {
		if ( ! is_string( $url ) || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $home_host )
			&& is_string( $url_host )
			&& strtolower( $home_host ) === strtolower( $url_host );
	}

	/**
	 * Schedule one non-duplicated worker.
	 */
	private static function schedule_worker( int $delay ): void {
		if ( false !== wp_next_scheduled( self::WORKER_HOOK ) ) {
			return;
		}

		wp_schedule_single_event(
			time() + max( 5, $delay ),
			self::WORKER_HOOK
		);
	}

	/**
	 * Whether all required Cloudflare values are present.
	 */
	private static function is_configured(): bool {
		$status = self::status();

		return $status['enabled'] && $status['zone'] && $status['token'];
	}

	/**
	 * Read plugin settings.
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
	 * Read the secret from server configuration only.
	 */
	private static function api_token(): string {
		$settings = self::settings();

		return Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_CLOUDFLARE_API_TOKEN',
			(string) (
				$settings['cloudflare_api_token_encrypted']
				?? ''
			)
		);
	}

	/**
	 * Validate a Cloudflare zone identifier.
	 */
	private static function valid_zone_id( string $zone_id ): bool {
		return 1 === preg_match( '/\A[a-f0-9]{32}\z/', $zone_id );
	}
}
