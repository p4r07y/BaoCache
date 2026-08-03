<?php
defined( 'ABSPATH' ) || exit;

/** Evidence-driven critical-image candidates and reversible promotion. */
final class BaoCache_Critical_Images {
	private const string SNAPSHOT_OPTION = 'baocache_critical_image_snapshot';
	private const string APPLICATION_OPTION = 'baocache_critical_image_application';

	/** @return array<string, mixed> */
	public static function snapshot(): array {
		$value = get_option( self::SNAPSHOT_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return array<string, mixed> */
	public static function application(): array {
		$value = get_option( self::APPLICATION_OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Analyze one public HTML response. Scores are candidate confidence, never an
	 * LCP claim. Raw HTML and the page URL are not persisted.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function analyze( string $html ): array|WP_Error {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'dom_unavailable', __( 'PHP DOM extension chưa sẵn sàng để phân tích ảnh.', 'baocache' ) );
		}
		if ( '' === trim( $html ) ) {
			return new WP_Error( 'empty_html', __( 'Frontend không trả HTML để phân tích ảnh.', 'baocache' ) );
		}
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return new WP_Error( 'invalid_html', __( 'Không thể đọc DOM frontend.', 'baocache' ) );
		}

		$candidates = array();
		$seen = array();
		$images = $dom->getElementsByTagName( 'img' );
		foreach ( $images as $index => $image ) {
			if ( 20 <= $index || ! $image instanceof DOMElement ) {
				break;
			}
			$raw_src = trim( $image->getAttribute( 'src' ) );
			if ( '' === $raw_src ) {
				$raw_src = trim( $image->getAttribute( 'data-src' ) );
			}
			$url = self::same_site_image_url( $raw_src );
			if ( '' === $url || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$width = absint( $image->getAttribute( 'width' ) );
			$height = absint( $image->getAttribute( 'height' ) );
			$loading = sanitize_key( $image->getAttribute( 'loading' ) );
			$priority = sanitize_key( $image->getAttribute( 'fetchpriority' ) );
			$decoding = sanitize_key( $image->getAttribute( 'decoding' ) );
			$class = strtolower( sanitize_text_field( $image->getAttribute( 'class' ) ) );
			$alt = strtolower( sanitize_text_field( $image->getAttribute( 'alt' ) ) );
			$parent_class = $image->parentNode instanceof DOMElement ? strtolower( sanitize_text_field( $image->parentNode->getAttribute( 'class' ) ) ) : '';
			$markers = trim( $class . ' ' . $parent_class . ' ' . $alt );
			$is_logo = str_contains( $markers, 'logo' ) || str_contains( $markers, 'avatar' ) || str_contains( $markers, 'icon' );
			$is_hero = str_contains( $markers, 'hero' ) || str_contains( $markers, 'banner' ) || str_contains( $markers, 'masthead' ) || str_contains( $markers, 'cover' );
			$is_slider = str_contains( $markers, 'slider' ) || str_contains( $markers, 'carousel' ) || str_contains( $markers, 'slide' );
			$score = max( 0, 34 - ( $index * 5 ) );
			$reasons = array( sprintf( __( 'Ảnh thứ %d trong DOM', 'baocache' ), $index + 1 ) );
			if ( 0 < $width && 0 < $height ) {
				$area = $width * $height;
				if ( 180000 <= $area ) {
					$score += 24;
					$reasons[] = sprintf( __( 'kích thước %1$d×%2$d', 'baocache' ), $width, $height );
				} elseif ( 40000 > $area ) {
					$score -= 16;
					$reasons[] = __( 'ảnh nhỏ', 'baocache' );
				}
			} else {
				$score -= 4;
				$reasons[] = __( 'thiếu width/height', 'baocache' );
			}
			if ( $is_hero ) {
				$score += 24;
				$reasons[] = __( 'hero/banner marker', 'baocache' );
			}
			if ( $is_slider ) {
				$score += 10;
				$reasons[] = __( 'slider/carousel marker', 'baocache' );
			}
			if ( $is_logo ) {
				$score -= 24;
				$reasons[] = __( 'logo/icon marker', 'baocache' );
			}
			if ( 'high' === $priority ) {
				$score += 8;
				$reasons[] = 'fetchpriority=high';
			}
			if ( 'lazy' === $loading ) {
				$reasons[] = 'loading=lazy';
			}
			$score = min( 99, max( 1, $score ) );
			$kind = $is_logo ? 'logo' : ( $is_slider ? 'slider' : ( $is_hero ? 'hero' : ( 0 === $index ? 'first-image' : 'image' ) ) );
			$candidate = array(
				'url' => $url,
				'path' => self::safe_path( $url ),
				'kind' => $kind,
				'confidence' => $score,
				'position' => $index + 1,
				'width' => $width,
				'height' => $height,
				'loading' => in_array( $loading, array( 'lazy', 'eager' ), true ) ? $loading : 'default',
				'fetchpriority' => in_array( $priority, array( 'high', 'low', 'auto' ), true ) ? $priority : 'default',
				'decoding' => in_array( $decoding, array( 'sync', 'async', 'auto' ), true ) ? $decoding : 'default',
				'has_dimensions' => 0 < $width && 0 < $height,
				'has_srcset' => '' !== trim( $image->getAttribute( 'srcset' ) ),
				'reasons' => array_slice( $reasons, 0, 6 ),
			);
			$candidate['fingerprint'] = substr( hash( 'sha256', (string) wp_json_encode( $candidate ) ), 0, 16 );
			$candidates[] = $candidate;
		}
		usort( $candidates, static fn( array $a, array $b ): int => (int) $b['confidence'] <=> (int) $a['confidence'] );
		$snapshot = array(
			'schema' => 1,
			'scanned_at' => time(),
			'scope' => 'front-page',
			'candidate_count' => count( $candidates ),
			'candidates' => array_slice( $candidates, 0, 8 ),
		);
		$snapshot['fingerprint'] = substr( hash( 'sha256', (string) wp_json_encode( $snapshot['candidates'] ) ), 0, 16 );
		update_option( self::SNAPSHOT_OPTION, $snapshot, false );
		return $snapshot;
	}

	/** @return array<string, mixed>|WP_Error */
	public static function apply( string $candidate_fingerprint ): array|WP_Error {
		$snapshot = self::snapshot();
		$candidate = null;
		foreach ( (array) ( $snapshot['candidates'] ?? array() ) as $item ) {
			if ( is_array( $item ) && hash_equals( (string) ( $item['fingerprint'] ?? '' ), $candidate_fingerprint ) ) {
				$candidate = $item;
				break;
			}
		}
		if ( ! is_array( $candidate ) || 20 > (int) ( $candidate['confidence'] ?? 0 ) ) {
			return new WP_Error( 'stale_candidate', __( 'Candidate không còn hợp lệ. Hãy quét lại trước khi áp dụng.', 'baocache' ) );
		}
		$url = self::same_site_image_url( (string) ( $candidate['url'] ?? '' ) );
		if ( '' === $url ) {
			return new WP_Error( 'invalid_candidate', __( 'Chỉ có thể áp dụng ảnh cùng website.', 'baocache' ) );
		}
		$settings = BaoCache_Settings::get();
		$existing = self::application();
		$before = array( 'lcp_image' => (string) $settings['lcp_image'], 'lcp_scope' => (string) $settings['lcp_scope'] );
		$existing_after = is_array( $existing['after'] ?? null ) ? $existing['after'] : array();
		if ( ! empty( $existing['applied_at'] ) && empty( $existing['rolled_back_at'] ) && is_array( $existing['before'] ?? null ) && (string) $settings['lcp_image'] === (string) ( $existing_after['lcp_image'] ?? '' ) && (string) $settings['lcp_scope'] === (string) ( $existing_after['lcp_scope'] ?? '' ) ) {
			$before = $existing['before'];
		}
		$settings['lcp_image'] = $url;
		$settings['lcp_scope'] = 'front-page';
		update_option( BAOCACHE_OPTION, $settings, false );
		$record = array(
			'applied_at' => time(),
			'candidate_fingerprint' => $candidate_fingerprint,
			'snapshot_fingerprint' => (string) ( $snapshot['fingerprint'] ?? '' ),
			'before' => $before,
			'after' => array( 'lcp_image' => $url, 'lcp_scope' => 'front-page' ),
			'rolled_back_at' => 0,
		);
		update_option( self::APPLICATION_OPTION, $record, false );
		return array( 'candidate' => $candidate, 'application' => $record );
	}

	/** @return array<string, mixed>|WP_Error */
	public static function rollback(): array|WP_Error {
		$record = self::application();
		if ( empty( $record['applied_at'] ) || ! empty( $record['rolled_back_at'] ) ) {
			return new WP_Error( 'nothing_to_rollback', __( 'Không có thay đổi Critical Image đang hoạt động để rollback.', 'baocache' ) );
		}
		$settings = BaoCache_Settings::get();
		$after = is_array( $record['after'] ?? null ) ? $record['after'] : array();
		if ( (string) $settings['lcp_image'] !== (string) ( $after['lcp_image'] ?? '' ) || (string) $settings['lcp_scope'] !== (string) ( $after['lcp_scope'] ?? '' ) ) {
			return new WP_Error( 'stale_application', __( 'Cấu hình đã thay đổi sau lần áp dụng; rollback tự động bị chặn để không ghi đè chỉnh sửa mới.', 'baocache' ) );
		}
		$before = is_array( $record['before'] ?? null ) ? $record['before'] : array();
		$settings['lcp_image'] = (string) ( $before['lcp_image'] ?? '' );
		$settings['lcp_scope'] = in_array( (string) ( $before['lcp_scope'] ?? '' ), array( 'front-page', 'everywhere' ), true ) ? (string) $before['lcp_scope'] : 'front-page';
		update_option( BAOCACHE_OPTION, $settings, false );
		$record['rolled_back_at'] = time();
		update_option( self::APPLICATION_OPTION, $record, false );
		return $record;
	}

	/** @return array<string, bool> */
	public static function verify_html( string $html, string $url ): array {
		$needle = self::same_site_image_url( $url );
		$result = array( 'present' => false, 'preload' => false, 'fetchpriority' => false );
		if ( '' === $needle || ! class_exists( 'DOMDocument' ) ) {
			return $result;
		}
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return $result;
		}
		foreach ( $dom->getElementsByTagName( 'link' ) as $link ) {
			if ( $link instanceof DOMElement && 'preload' === strtolower( trim( $link->getAttribute( 'rel' ) ) ) && $needle === self::same_site_image_url( $link->getAttribute( 'href' ) ) ) {
				$result['preload'] = true;
			}
		}
		foreach ( $dom->getElementsByTagName( 'img' ) as $image ) {
			if ( ! $image instanceof DOMElement ) {
				continue;
			}
			$src = self::same_site_image_url( $image->getAttribute( 'src' ) );
			if ( $needle !== $src ) {
				continue;
			}
			$result['present'] = true;
			$result['fetchpriority'] = 'high' === strtolower( trim( $image->getAttribute( 'fetchpriority' ) ) );
			break;
		}
		return $result;
	}

	private static function same_site_image_url( string $url ): string {
		$url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $url || str_starts_with( $url, 'data:' ) || str_starts_with( $url, 'blob:' ) ) {
			return '';
		}
		if ( str_starts_with( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		} elseif ( str_starts_with( $url, '/' ) ) {
			$url = home_url( $url );
		} elseif ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = home_url( '/' . ltrim( $url, '/' ) );
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $host || $host !== $home_host ) {
			return '';
		}
		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' !== (string) wp_parse_url( $url, PHP_URL_QUERY ) || ! preg_match( '/\.(?:avif|gif|jpe?g|png|svg|webp)$/', $path ) ) {
			return '';
		}
		return esc_url_raw( $url );
	}

	private static function safe_path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '/' . ltrim( sanitize_text_field( $path ), '/' );
	}
}
