<?php
defined( 'ABSPATH' ) || exit;

/** Safe, low-rate cache warming through the private Docker Nginx service. */
final class BaoCache_Warmup {
	private const string TICK_HOOK = 'baocache_warmup_tick';
	private const string SITEMAP_HOOK = 'baocache_warmup_sitemap';
	private const string QUEUE_OPTION = 'baocache_warm_queue';
	private const string STATUS_OPTION = 'baocache_warm_status';
	private const int QUEUE_LIMIT = 1000;
	private const int SITEMAP_URL_LIMIT = 500;

	public function register(): void {
		add_action( self::TICK_HOOK, array( $this, 'process_queue' ) );
		add_action( self::SITEMAP_HOOK, array( $this, 'refresh_sitemap' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ), 20 );
	}

	public static function activate(): void {
		( new self() )->ensure_scheduled();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::TICK_HOOK );
		wp_clear_scheduled_hook( self::SITEMAP_HOOK );
	}

	/** Registered during plugin bootstrap, before activation or settings saves. */
	public static function schedules( array $schedules ): array {
		$schedules['baocache_minute'] = array( 'interval' => MINUTE_IN_SECONDS, 'display' => __( 'BaoCache mỗi phút', 'baocache' ) );
		$schedules['baocache_six_hours'] = array( 'interval' => 6 * HOUR_IN_SECONDS, 'display' => __( 'BaoCache mỗi 6 giờ', 'baocache' ) );
		return $schedules;
	}

	public function ensure_scheduled(): void {
		$settings = BaoCache_Settings::get();
		if ( ! $settings['warm_enabled'] ) {
			wp_clear_scheduled_hook( self::TICK_HOOK );
			wp_clear_scheduled_hook( self::SITEMAP_HOOK );
			return;
		}
		$schedules = wp_get_schedules();
		if ( ! isset( $schedules['baocache_minute'], $schedules['baocache_six_hours'] ) ) {
			self::update_status( array( 'last_schedule_error' => __( 'Không đăng ký được lịch BaoCache trong WordPress Cron.', 'baocache' ) ) );
			return;
		}
		if ( ! wp_next_scheduled( self::TICK_HOOK ) ) {
			$result = wp_schedule_event( time() + MINUTE_IN_SECONDS, 'baocache_minute', self::TICK_HOOK, array(), true );
			if ( is_wp_error( $result ) ) {
				self::update_status( array( 'last_schedule_error' => $result->get_error_message() ) );
				return;
			}
		}
		$wanted = (string) $settings['warm_schedule'];
		if ( wp_get_schedule( self::SITEMAP_HOOK ) !== $wanted ) {
			wp_clear_scheduled_hook( self::SITEMAP_HOOK );
			$result = wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), $wanted, self::SITEMAP_HOOK, array(), true );
			if ( is_wp_error( $result ) ) {
				self::update_status( array( 'last_schedule_error' => $result->get_error_message() ) );
				return;
			}
		}
		self::update_status( array( 'last_schedule_error' => '' ) );
	}

	public static function enqueue_urls( array $urls, string $source = 'manual' ): int {
		$settings = BaoCache_Settings::get();
		if ( ! $settings['warm_enabled'] ) {
			return 0;
		}
		$current = get_option( self::QUEUE_OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$known = array();
		foreach ( $current as $item ) {
			if ( is_array( $item ) && ! empty( $item['url'] ) ) {
				$known[ (string) $item['url'] ] = true;
			}
		}
		$added = 0;
		foreach ( $urls as $url ) {
			$url = esc_url_raw( is_string( $url ) ? $url : '', array( 'http', 'https' ) );
			if ( '' === $url || isset( $known[ $url ] ) || ! self::same_site( $url ) ) {
				continue;
			}
			$current[] = array( 'url' => $url, 'source' => sanitize_key( $source ), 'attempts' => 0, 'queued_at' => time() );
			$known[ $url ] = true;
			$added++;
			if ( count( $current ) >= self::QUEUE_LIMIT ) {
				break;
			}
		}
		update_option( self::QUEUE_OPTION, array_slice( $current, 0, self::QUEUE_LIMIT ), false );
		self::update_status( array( 'queued' => count( $current ), 'last_enqueue_at' => time(), 'last_source' => sanitize_key( $source ) ) );
		return $added;
	}

	public function refresh_sitemap(): int {
		$settings = BaoCache_Settings::get();
		if ( ! $settings['warm_enabled'] ) {
			return 0;
		}
		$sitemap = $this->resolve_sitemap( (string) $settings['warm_sitemap'] );
		if ( '' === $sitemap ) {
			self::update_status( array( 'last_sitemap_at' => time(), 'last_sitemap_error' => __( 'Không phát hiện sitemap XML hợp lệ cùng domain.', 'baocache' ) ) );
			return 0;
		}
		$urls = $this->sitemap_urls( $sitemap );
		$added = self::enqueue_urls( $urls, 'sitemap' );
		self::update_status( array( 'last_sitemap_at' => time(), 'last_sitemap_urls' => count( $urls ), 'detected_sitemap' => $sitemap, 'last_sitemap_error' => '' ) );
		return $added;
	}

	public function process_queue(): void {
		$settings = BaoCache_Settings::get();
		if ( ! $settings['warm_enabled'] || get_transient( 'baocache_warmup_lock' ) ) {
			return;
		}
		set_transient( 'baocache_warmup_lock', '1', 55 );
		$queue = get_option( self::QUEUE_OPTION, array() );
		$queue = is_array( $queue ) ? $queue : array();
		$batch = array_splice( $queue, 0, (int) $settings['warm_batch'] );
		$warmed = 0;
		$failed = 0;
		foreach ( $batch as $item ) {
			$url = is_array( $item ) ? (string) ( $item['url'] ?? '' ) : '';
			$response = $this->request( $url );
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 400 ) {
				$warmed++;
				continue;
			}
			$failed++;
			$attempts = absint( is_array( $item ) ? ( $item['attempts'] ?? 0 ) : 0 ) + 1;
			if ( '' !== $url && $attempts < 3 ) {
				$item['attempts'] = $attempts;
				$queue[] = $item;
			}
		}
		update_option( self::QUEUE_OPTION, array_slice( $queue, 0, self::QUEUE_LIMIT ), false );
		self::update_status( array( 'queued' => count( $queue ), 'last_run_at' => time(), 'last_warmed' => $warmed, 'last_failed' => $failed ) );
		delete_transient( 'baocache_fastcgi_metrics' );
	}

	public static function status(): array {
		$status = get_option( self::STATUS_OPTION, array() );
		$status = is_array( $status ) ? $status : array();
		$status['queued'] = count( (array) get_option( self::QUEUE_OPTION, array() ) );
		$status['scheduled'] = (bool) wp_next_scheduled( self::TICK_HOOK );
		return $status;
	}

	private function sitemap_urls( string $sitemap_url ): array {
		$pending = array( $sitemap_url );
		$seen = array();
		$urls = array();
		while ( ! empty( $pending ) && count( $urls ) < self::SITEMAP_URL_LIMIT ) {
			$current = array_shift( $pending );
			if ( isset( $seen[ $current ] ) || ! self::same_site( $current ) ) continue;
			$seen[ $current ] = true;
			$response = $this->request( $current );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) continue;
			$xml = $this->parse_xml( (string) wp_remote_retrieve_body( $response ) );
			if ( ! $xml ) continue;
			if ( 'sitemapindex' === $xml->getName() ) {
				foreach ( $xml->sitemap as $node ) {
					$child = esc_url_raw( trim( (string) $node->loc ), array( 'http', 'https' ) );
					if ( '' !== $child && count( $pending ) < 20 ) $pending[] = $child;
				}
			} else {
				foreach ( $xml->url as $node ) {
					$url = esc_url_raw( trim( (string) $node->loc ), array( 'http', 'https' ) );
					if ( '' !== $url && self::same_site( $url ) ) $urls[] = $url;
					if ( count( $urls ) >= self::SITEMAP_URL_LIMIT ) break;
				}
			}
		}
		return array_values( array_unique( $urls ) );
	}

	private function resolve_sitemap( string $preferred ): string {
		$candidates = array_filter( array_unique( array_merge( array( $preferred ), $this->sitemap_candidates() ) ) );
		/** This filter permits a theme or SEO plugin integration to add a same-site sitemap path. */
		$candidates = apply_filters( 'baocache_sitemap_candidates', $candidates );
		if ( ! is_array( $candidates ) ) {
			return '';
		}
		foreach ( $candidates as $candidate ) {
			$candidate = esc_url_raw( is_string( $candidate ) ? $candidate : '', array( 'http', 'https' ) );
			if ( '' === $candidate || ! self::same_site( $candidate ) ) continue;
			$response = $this->request( $candidate );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) continue;
			$xml = $this->parse_xml( (string) wp_remote_retrieve_body( $response ) );
			if ( $xml && in_array( $xml->getName(), array( 'urlset', 'sitemapindex' ), true ) ) return $candidate;
		}
		return '';
	}

	private function sitemap_candidates(): array {
		return array(
			home_url( '/sitemap_index.xml' ), // Yoast SEO, Rank Math and common XML sitemap plugins.
			home_url( '/wp-sitemap.xml' ), // WordPress core sitemap.
			home_url( '/sitemap.xml' ), // Common custom / AIOSEO-style sitemap path.
			home_url( '/sitemap-index.xml' ),
		);
	}

	private function request( string $url ): array|WP_Error {
		if ( ! self::same_site( $url ) ) {
			return new WP_Error( 'baocache_invalid_warm_url' );
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$target = 'http://nginx' . ( '' === $path ? '/' : $path ) . ( '' !== $query ? '?' . $query : '' );
		$scheme = (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
		return wp_remote_get( $target, array( 'timeout' => 8, 'redirection' => 0, 'headers' => array( 'Host' => (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ), 'X-Forwarded-Proto' => '' !== $scheme ? $scheme : 'https', 'Cookie' => '', 'User-Agent' => 'BaoCache-Warmup/' . BAOCACHE_VERSION ) ) );
	}

	private function parse_xml( string $body ): ?SimpleXMLElement {
		if ( '' === $body || strlen( $body ) > 5 * 1024 * 1024 ) return null;
		$previous = libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $xml instanceof SimpleXMLElement ? $xml : null;
	}

	private static function same_site( string $url ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return '' !== $host && $host === $site_host;
	}

	private static function update_status( array $updates ): void {
		$status = get_option( self::STATUS_OPTION, array() );
		update_option( self::STATUS_OPTION, wp_parse_args( $updates, is_array( $status ) ? $status : array() ), false );
	}
}
