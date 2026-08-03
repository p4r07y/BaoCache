<?php
defined( 'ABSPATH' ) || exit;

/**
 * Coordinates exact FastCGI invalidation through the authenticated Docker-only
 * Nginx endpoint. It never attempts filesystem cache deletion from PHP.
 */
final class BaoCache_Purge {
	private const CRON_HOOK = 'baocache_purge_url';
	private const EVIDENCE_OPTION = 'baocache_fastcgi_purge_evidence';

	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'purge_url' ) );
		add_action( 'save_post', array( $this, 'queue_post_purge' ), 20, 3 );
		add_action( 'deleted_post', array( $this, 'queue_deleted_post_purge' ), 20, 2 );
	}

	public static function available(): bool {
		$url = getenv( 'BAOCACHE_PURGE_URL' );
		$password_file = getenv( 'BAOCACHE_PURGE_PASSWORD_FILE' );
		return is_string( $url ) && '' !== $url
			&& is_string( $password_file ) && is_readable( $password_file )
			&& '' !== trim( (string) file_get_contents( $password_file ) );
	}

	/**
	 * Bounded deployment evidence only. No endpoint, path, password, response
	 * body or header is retained; the evidence is safe for the admin surface.
	 */
	public static function evidence(): array {
		$value = get_option( self::EVIDENCE_OPTION, array() );
		$value = is_array( $value ) ? $value : array();
		return array(
			'configured' => self::available(),
			'checked_at' => (int) ( $value['checked_at'] ?? 0 ),
			'code' => (int) ( $value['code'] ?? 0 ),
			'state' => sanitize_key( (string) ( $value['state'] ?? '' ) ),
			'success' => ! empty( $value['success'] ),
		);
	}

	/** @return array{title: string, detail: string} */
	public static function remediation( array $evidence ): array {
		if ( ! empty( $evidence['success'] ) && 'verified' === (string) ( $evidence['state'] ?? '' ) ) {
			return array( 'title' => __( 'Live endpoint verified', 'baocache' ), 'detail' => __( 'Module, Docker DNS và generated secret đã được xác minh bằng probe không xóa cache.', 'baocache' ) );
		}
		return match ( (string) ( $evidence['state'] ?? '' ) ) {
			'unavailable' => array( 'title' => __( 'WordPress configuration missing', 'baocache' ), 'detail' => __( 'Kiểm tra BAOCACHE_PURGE_URL, BAOCACHE_PURGE_PASSWORD_FILE và secret volume read-only trong service wordpress.', 'baocache' ) ),
			'connection' => array( 'title' => __( 'Nginx unreachable from WordPress', 'baocache' ), 'detail' => __( 'Kiểm tra service nginx, Docker DNS nội bộ và healthcheck; không mở endpoint ra Internet.', 'baocache' ) ),
			'rejected' => array( 'title' => __( 'Nginx rejected the probe', 'baocache' ), 'detail' => __( 'Dùng HTTP code của lần xác minh: 401/403 là secret, 405 là image/config cũ, 5xx là image/module/cache-zone.', 'baocache' ) ),
			default => array( 'title' => __( 'Live endpoint not verified yet', 'baocache' ), 'detail' => __( 'Cấu hình local có thể sẵn sàng, nhưng cần chạy probe trước khi tin rằng image Nginx thực sự đang hoạt động.', 'baocache' ) ),
		};
	}

	public function queue_post_purge( int $post_id, WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		$this->queue_url( get_permalink( $post ) );
		if ( 'post' === $post->post_type ) {
			$this->queue_url( home_url( '/' ) );
			foreach ( get_the_category( $post_id ) as $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$this->queue_url( $link );
				}
			}
		}
	}

	public function queue_deleted_post_purge( int $post_id, WP_Post $post ): void {
		$this->queue_url( get_permalink( $post ) );
		if ( 'post' === $post->post_type ) {
			$this->queue_url( home_url( '/' ) );
		}
	}

	public function queue_url( string $url ): void {
		BaoCache_Warmup::enqueue_urls( array( $url ), 'content-change' );
		if ( ! self::available() || '' === $url ) {
			return;
		}
		$next = wp_next_scheduled( self::CRON_HOOK, array( $url ) );
		if ( ! $next ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $url ) );
		}
	}

	/** @return array{success: bool, code: int, state: string, message: string} */
	public function purge_url( string $url ): array {
		if ( ! self::available() || ! $this->is_same_site_url( $url ) ) {
			return array( 'success' => false, 'code' => 0, 'state' => 'unavailable', 'message' => __( 'Purge API chưa sẵn sàng hoặc URL không cùng website.', 'baocache' ) );
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = '' === $path ? '/' : $path;
		$result = $this->request_endpoint( $path );
		$code = $result['code'];
		if ( in_array( $code, array( 200, 404 ), true ) ) {
			BaoCache_Warmup::enqueue_urls( array( $url ), 'post-purge' );
			delete_transient( 'baocache_fastcgi_metrics' );
			return array( 'success' => true, 'code' => $code, 'state' => 200 === $code ? 'success' : 'empty', 'message' => 200 === $code ? __( 'Đã purge URL khỏi FastCGI cache.', 'baocache' ) : __( 'URL chưa có bản cache; không cần purge.', 'baocache' ) );
		}
		return array( 'success' => false, 'code' => $code, 'state' => $result['state'], 'message' => $result['message'] );
	}

	/**
	 * Authenticated, non-mutating verification. A 404 for this impossible key
	 * proves that WordPress can reach the current Nginx image, the dynamic purge
	 * module is loaded and the shared generated credentials are accepted.
	 *
	 * @return array{success: bool, code: int, state: string, message: string}
	 */
	public function verify_endpoint(): array {
		if ( ! self::available() ) {
			$result = array( 'success' => false, 'code' => 0, 'state' => 'unavailable', 'message' => __( 'Thiếu purge URL hoặc secret volume trong container WordPress.', 'baocache' ) );
			self::record_evidence( $result );
			return $result;
		}
		$result = $this->request_endpoint( '/__baocache/purge-probe-never-cached' );
		if ( 404 === $result['code'] ) {
			$result = array( 'success' => true, 'code' => 404, 'state' => 'verified', 'message' => __( 'Purge endpoint đã xác minh: module, Docker DNS và generated secret hoạt động.', 'baocache' ) );
		}
		self::record_evidence( $result );
		return $result;
	}

	/** @param array{success: bool, code: int, state: string, message: string} $result */
	private static function record_evidence( array $result ): void {
		update_option( self::EVIDENCE_OPTION, array(
			'checked_at' => time(),
			'code' => (int) $result['code'],
			'state' => sanitize_key( (string) $result['state'] ),
			'success' => ! empty( $result['success'] ),
		), false );
	}

	/** @return array{success: bool, code: int, state: string, message: string} */
	private function request_endpoint( string $path ): array {
		$endpoint = (string) getenv( 'BAOCACHE_PURGE_URL' );
		$password_file = (string) getenv( 'BAOCACHE_PURGE_PASSWORD_FILE' );
		$password = trim( (string) file_get_contents( $password_file ) );
		$site_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 3,
				'headers' => array(
					'Host' => $site_host,
					'Authorization' => 'Basic ' . base64_encode( 'baocache:' . $password ),
					'X-BaoCache-Purge-URI' => $path,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'code' => 0, 'state' => 'connection', 'message' => __( 'Không thể kết nối Nginx purge API trong Docker.', 'baocache' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, array( 200, 404 ), true ) ) {
			return array( 'success' => true, 'code' => $code, 'state' => 200 === $code ? 'purged' : 'empty', 'message' => '' );
		}
		$message = match ( $code ) {
			401, 403 => __( 'Nginx không chấp nhận generated secret. Redeploy để đồng bộ secret volume và purge image.', 'baocache' ),
			405 => __( 'Nginx purge endpoint không nhận POST. Image Nginx đang cũ hoặc cấu hình chưa được deploy.', 'baocache' ),
			500, 502, 503, 504 => __( 'Nginx purge module chưa sẵn sàng. Kiểm tra healthcheck Nginx rồi redeploy image BaoCache.', 'baocache' ),
			default => __( 'Nginx trả response purge không mong đợi. Kiểm tra healthcheck Nginx BaoCache.', 'baocache' ),
		};
		return array( 'success' => false, 'code' => $code, 'state' => 'rejected', 'message' => $message );
	}

	private function is_same_site_url( string $url ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return '' !== $host && $host === $site_host;
	}
}
