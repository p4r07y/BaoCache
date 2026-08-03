<?php
/**
 * Plugin query cache management.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides versioned cache storage and targeted invalidation.
 *
 * Redis or another persistent object cache is used when available. WordPress
 * transients provide a fallback when persistent object caching is disabled.
 */
final class Power_Schedule_Manager_Cache {

	/**
	 * WordPress object-cache group.
	 */
	private const string GROUP = 'power_schedule_manager';

	/**
	 * Transient prefix.
	 */
	private const string TRANSIENT_PREFIX = 'psm_cache_';

	/**
	 * Default query-cache lifetime: five minutes.
	 */
	public const int DEFAULT_TTL = 300;

	/**
	 * Maximum cache lifetime: one hour.
	 */
	private const int MAX_TTL = 3600;

	/**
	 * Cache envelope marker.
	 */
	private const string ENVELOPE_MARKER = 'psm_cache_v1';

	/** Browser-visible plugin pages use a short Nginx FastCGI micro-cache. */
	private const int FASTCGI_PUBLIC_TTL = 60;

	/**
	 * Register cache invalidation hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'send_headers',
			array( $this, 'set_fastcgi_cache_ttl' ),
			20
		);

		add_action(
			'power_schedule_manager_import_completed',
			array( $this, 'invalidate_after_import' ),
			10,
			2
		);

		add_action(
			'power_schedule_manager_event_updated',
			array( $this, 'invalidate_after_event_change' ),
			10,
			2
		);

		add_action(
			'power_schedule_manager_event_deleted',
			array( $this, 'invalidate_after_event_change' ),
			10,
			2
		);

		add_action(
			'created_' . Power_Schedule_Manager_Taxonomy::TAXONOMY,
			array( $this, 'invalidate_after_term_change' )
		);

		add_action(
			'edited_' . Power_Schedule_Manager_Taxonomy::TAXONOMY,
			array( $this, 'invalidate_after_term_change' )
		);

		add_action(
			'delete_' . Power_Schedule_Manager_Taxonomy::TAXONOMY,
			array( $this, 'invalidate_after_term_change' )
		);

		add_action(
			'updated_option',
			array( $this, 'invalidate_after_settings_change' ),
			10,
			3
		);

		add_action(
			'transition_post_status',
			array( $this, 'invalidate_after_post_transition' ),
			20,
			3
		);
	}

	/**
	 * Let stock Nginx use a short TTL for pages backed by frequently refreshed
	 * plugin data. X-Accel-Expires controls FastCGI cache storage and is consumed
	 * by Nginx instead of being exposed as a public response header.
	 */
	public function set_fastcgi_cache_ttl(): void {
		if (
			is_admin()
			|| is_user_logged_in()
			|| wp_doing_ajax()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| headers_sent()
			|| ! Power_Schedule_Manager_Assets::request_has_plugin_surface()
		) {
			return;
		}

		$ttl = (int) apply_filters(
			'power_schedule_manager_fastcgi_public_ttl',
			self::FASTCGI_PUBLIC_TTL
		);

		if ( $ttl < 1 || $ttl > HOUR_IN_SECONDS ) {
			return;
		}

		header( 'X-Accel-Expires: ' . $ttl );
	}

	/**
	 * Retrieve cached data.
	 *
	 * The found flag distinguishes a cached false/null value from a miss.
	 *
	 * @param string $namespace Cache namespace.
	 * @param array  $parts     Cache key parts.
	 * @param bool   $found     Whether a valid entry was found.
	 *
	 * @return mixed
	 */
	public static function get(
		string $namespace,
		array $parts,
		bool &$found = false
	): mixed {
		$found = false;
		$key   = self::key( $namespace, $parts );

		if ( wp_using_ext_object_cache() ) {
			$cache_found = false;
			$envelope    = wp_cache_get(
				$key,
				self::GROUP,
				false,
				$cache_found
			);

			if (
				$cache_found
				&& self::is_valid_envelope( $envelope )
			) {
				$found = true;

				return $envelope['value'];
			}

			return null;
		}

		$envelope = get_transient(
			self::transient_name( $key )
		);

		if ( self::is_valid_envelope( $envelope ) ) {
			$found = true;

			return $envelope['value'];
		}

		return null;
	}

	/**
	 * Store data in cache.
	 *
	 * @param string $namespace Cache namespace.
	 * @param array  $parts     Cache key parts.
	 * @param mixed  $value     Value.
	 * @param int    $ttl       Lifetime in seconds.
	 *
	 * @return bool
	 */
	public static function set(
		string $namespace,
		array $parts,
		mixed $value,
		int $ttl = self::DEFAULT_TTL
	): bool {
		$key = self::key( $namespace, $parts );
		$ttl = self::sanitize_ttl( $ttl );

		$envelope = array(
			'marker'     => self::ENVELOPE_MARKER,
			'value'      => $value,
			'created_at' => time(),
			'expires_at' => time() + $ttl,
		);

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_set(
				$key,
				$envelope,
				self::GROUP,
				$ttl
			);
		}

		return set_transient(
			self::transient_name( $key ),
			$envelope,
			$ttl
		);
	}

	/**
	 * Delete one cache entry.
	 *
	 * @param string $namespace Cache namespace.
	 * @param array  $parts     Cache key parts.
	 *
	 * @return bool
	 */
	public static function delete(
		string $namespace,
		array $parts
	): bool {
		$key = self::key( $namespace, $parts );

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_delete(
				$key,
				self::GROUP
			);
		}

		return delete_transient(
			self::transient_name( $key )
		);
	}

	/**
	 * Return cached data or calculate and store it.
	 *
	 * @param string   $namespace Cache namespace.
	 * @param array    $parts     Cache key parts.
	 * @param callable $callback  Value callback.
	 * @param int      $ttl       Lifetime.
	 *
	 * @return mixed
	 */
	public static function remember(
		string $namespace,
		array $parts,
		callable $callback,
		int $ttl = self::DEFAULT_TTL
	): mixed {
		$found = false;
		$value = self::get(
			$namespace,
			$parts,
			$found
		);

		if ( $found ) {
			return $value;
		}

		$value = $callback();

		self::set(
			$namespace,
			$parts,
			$value,
			$ttl
		);

		return $value;
	}

	/**
	 * Increment the plugin cache generation.
	 *
	 * Old entries become unreachable immediately and expire naturally. This
	 * avoids wildcard deletion and never purges another plugin's cache.
	 *
	 * @return int New generation.
	 */
	public static function invalidate_all(): int {
		$current = self::generation();

		$new_generation = max(
			$current + 1,
			time()
		);

		update_option(
			POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
			$new_generation,
			false
		);

		wp_cache_set(
			'generation',
			$new_generation,
			self::GROUP
		);

		/**
		 * Fires when the plugin cache generation changes.
		 *
		 * @param int $new_generation New generation.
		 */
		do_action(
			'power_schedule_manager_cache_invalidated',
			$new_generation
		);

		return $new_generation;
	}

	/**
	 * Return current cache generation.
	 *
	 * @return int
	 */
	public static function generation(): int {
		$cached = wp_cache_get(
			'generation',
			self::GROUP
		);

		if ( is_numeric( $cached ) && (int) $cached > 0 ) {
			return (int) $cached;
		}

		$stored = get_option(
			POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
			1
		);

		$generation = is_numeric( $stored )
			? max( 1, (int) $stored )
			: 1;

		wp_cache_set(
			'generation',
			$generation,
			self::GROUP
		);

		return $generation;
	}

	/**
	 * Invalidate cache after import.
	 *
	 * @param int                  $run_id Import run ID.
	 * @param array<string, mixed> $result Import result.
	 *
	 * @return void
	 */
	public function invalidate_after_import(
		int $run_id,
		array $result
	): void {
		self::invalidate_all();

		/**
		 * Allows a site-specific FastCGI cache integration to purge only
		 * affected schedule URLs.
		 *
		 * The plugin itself does not purge the entire website.
		 *
		 * @param string               $reason  Invalidation reason.
		 * @param array<string, mixed> $context Context.
		 */
		do_action(
			'power_schedule_manager_page_cache_purge',
			'import',
			array(
				'run_id' => $run_id,
				'posts'  => isset( $result['posts'] )
					&& is_array( $result['posts'] )
					? array_map( 'absint', $result['posts'] )
					: array(),
			)
		);
	}

	/**
	 * Invalidate after one event changes.
	 *
	 * @param int                  $event_id Event ID.
	 * @param array<string, mixed> $context  Event context.
	 *
	 * @return void
	 */
	public function invalidate_after_event_change(
		int $event_id,
		array $context = array()
	): void {
		self::invalidate_all();

		do_action(
			'power_schedule_manager_page_cache_purge',
			'event',
			array_merge(
				$context,
				array(
					'event_id' => absint( $event_id ),
				)
			)
		);
	}

	/**
	 * Invalidate after a service-area term changes.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return void
	 */
	public function invalidate_after_term_change(
		int $term_id
	): void {
		self::invalidate_all();

		do_action(
			'power_schedule_manager_page_cache_purge',
			'term',
			array(
				'term_id' => absint( $term_id ),
			)
		);
	}

	/**
	 * Invalidate public widgets when a schedule is published or hidden.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function invalidate_after_post_transition(
		string $new_status,
		string $old_status,
		WP_Post $post
	): void {
		if (
			Power_Schedule_Manager_Post_Type::POST_TYPE
				!== $post->post_type
			|| $new_status === $old_status
		) {
			return;
		}

		self::invalidate_all();

		do_action(
			'power_schedule_manager_page_cache_purge',
			'post_status',
			array(
				'post_id'    => $post->ID,
				'old_status' => $old_status,
				'new_status' => $new_status,
			)
		);
	}

	/**
	 * Invalidate when plugin settings change.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 *
	 * @return void
	 */
	public function invalidate_after_settings_change(
		string $option,
		mixed $old_value,
		mixed $new_value
	): void {
		if ( POWER_SCHEDULE_MANAGER_SETTINGS_OPTION !== $option ) {
			return;
		}

		if ( $old_value === $new_value ) {
			return;
		}

		self::invalidate_all();

		do_action(
			'power_schedule_manager_page_cache_purge',
			'settings',
			array()
		);
	}

	/**
	 * Build a versioned cache key.
	 *
	 * @param string $namespace Cache namespace.
	 * @param array  $parts     Key parts.
	 *
	 * @return string
	 */
	public static function key(
		string $namespace,
		array $parts
	): string {
		$namespace = sanitize_key( $namespace );

		if (
			'' === $namespace
			|| strlen( $namespace ) > 40
		) {
			throw new InvalidArgumentException(
				'invalid_cache_namespace'
			);
		}

		$normalized_parts = self::normalize_key_parts(
			$parts
		);

		$payload = wp_json_encode(
			array(
				'generation' => self::generation(),
				'namespace'  => $namespace,
				'parts'      => $normalized_parts,
			),
			JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $payload ) ) {
			throw new RuntimeException(
				'cache_key_encoding_failed'
			);
		}

		return 'v'
			. self::generation()
			. ':'
			. $namespace
			. ':'
			. hash( 'sha256', $payload );
	}

	/**
	 * Normalize cache-key values.
	 *
	 * @param array $parts Key parts.
	 *
	 * @return array
	 */
	private static function normalize_key_parts(
		array $parts
	): array {
		$normalized = array();

		ksort( $parts );

		foreach ( $parts as $key => $value ) {
			$key = is_string( $key )
				? sanitize_key( $key )
				: (string) (int) $key;

			if ( is_array( $value ) ) {
				$normalized[ $key ] =
					self::normalize_key_parts( $value );
				continue;
			}

			if (
				null === $value
				|| is_bool( $value )
				|| is_int( $value )
				|| is_float( $value )
			) {
				$normalized[ $key ] = $value;
				continue;
			}

			if ( is_string( $value ) ) {
				$normalized[ $key ] = substr(
					$value,
					0,
					500
				);
			}
		}

		return $normalized;
	}

	/**
	 * Validate cache envelope.
	 *
	 * @param mixed $envelope Cached value.
	 *
	 * @return bool
	 */
	private static function is_valid_envelope(
		mixed $envelope
	): bool {
		return is_array( $envelope )
			&& isset(
				$envelope['marker'],
				$envelope['expires_at']
			)
			&& self::ENVELOPE_MARKER === $envelope['marker']
			&& is_numeric( $envelope['expires_at'] )
			&& (int) $envelope['expires_at'] >= time()
			&& array_key_exists( 'value', $envelope );
	}

	/**
	 * Create a transient-safe name.
	 *
	 * @param string $cache_key Internal cache key.
	 *
	 * @return string
	 */
	private static function transient_name(
		string $cache_key
	): string {
		return self::TRANSIENT_PREFIX
			. hash( 'sha256', $cache_key );
	}

	/**
	 * Normalize cache lifetime.
	 *
	 * @param int $ttl Requested lifetime.
	 *
	 * @return int
	 */
	private static function sanitize_ttl( int $ttl ): int {
		return min(
			self::MAX_TTL,
			max( 1, $ttl )
		);
	}
}
