<?php
/**
 * Secure import preview generation.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds read-only import previews and signed confirmation tokens.
 *
 * Preview generation does not write to the database.
 */
final class Power_Schedule_Manager_Preview {

	/**
	 * Possible duplicate classification.
	 */
	public const string ACTION_POSSIBLE_DUPLICATE =
		'possible_duplicate';

	/**
	 * Preview token lifetime: 15 minutes.
	 */
	private const int TOKEN_TTL = 900;

	/**
	 * Maximum accepted token length.
	 */
	private const int MAX_TOKEN_LENGTH = 4096;

	/**
	 * Maximum start-time difference for a possible duplicate.
	 */
	private const int DUPLICATE_TIME_WINDOW = 21600;

	/**
	 * Minimum area token similarity.
	 */
	private const float AREA_SIMILARITY_THRESHOLD = 0.60;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Build a preview from parser output.
	 *
	 * @param array<string, mixed> $parsed      Parser result.
	 * @param string               $raw_payload Original payload.
	 *
	 * @return array{
	 *     unit_code: string,
	 *     unit_name: string,
	 *     rows: array<int, array<string, mixed>>,
	 *     errors: array<int, mixed>,
	 *     warnings: array<int, mixed>,
	 *     counts: array<string, int>,
	 *     token: string,
	 *     expires_at: int
	 * }
	 *
	 * @throws RuntimeException When the user is not authorized.
	 * @throws InvalidArgumentException When parser output is invalid.
	 */
	public static function create(
		array $parsed,
		string $raw_payload
	): array {
		self::assert_user_can_preview();

		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$parsed['unit_code'] ?? ''
		);

		if ( '' === $unit_code ) {
			throw new InvalidArgumentException(
				'invalid_preview_unit_code'
			);
		}

		$events = $parsed['events'] ?? null;

		if ( ! is_array( $events ) ) {
			throw new InvalidArgumentException(
				'invalid_preview_events'
			);
		}

		$rows   = array();
		$counts = array(
			Power_Schedule_Manager_Repository::ACTION_NEW       => 0,
			Power_Schedule_Manager_Repository::ACTION_UPDATE    => 0,
			Power_Schedule_Manager_Repository::ACTION_UNCHANGED => 0,
			self::ACTION_POSSIBLE_DUPLICATE                     => 0,
			'error'                                             => 0,
		);

		foreach ( $events as $index => $event ) {
			if ( ! is_array( $event ) ) {
				++$counts['error'];

				continue;
			}

			try {
				$inspection = Power_Schedule_Manager_Repository::inspect(
					$event
				);

				$action     = $inspection['action'];
				$candidates = array();

				if (
					Power_Schedule_Manager_Repository::ACTION_NEW
					=== $action
				) {
					$candidates = self::possible_duplicates(
						$inspection['event']
					);

					if ( array() !== $candidates ) {
						$action =
							self::ACTION_POSSIBLE_DUPLICATE;
					}
				}

				++$counts[ $action ];

				$rows[] = array(
					'index'          => (int) $index,
					'action'         => $action,
					'action_label'   => self::action_label( $action ),
					'event'          => $inspection['event'],
					'existing'       => $inspection['existing'],
					'candidates'     => $candidates,
					'identity_hash'  => $inspection['identity_hash'],
					'content_hash'   => $inspection['content_hash'],
				);
			} catch ( InvalidArgumentException $exception ) {
				++$counts['error'];

				$rows[] = array(
					'index'        => (int) $index,
					'action'       => 'error',
					'action_label' => self::action_label( 'error' ),
					'event'        => $event,
					'existing'     => null,
					'candidates'   => array(),
					'error'        => $exception->getMessage(),
				);
			}
		}

		$issued_at  = time();
		$expires_at = $issued_at + self::TOKEN_TTL;
		$token      = self::create_token(
			$raw_payload,
			$unit_code,
			$events,
			$issued_at,
			$expires_at
		);

		$errors = isset( $parsed['errors'] )
			&& is_array( $parsed['errors'] )
			? $parsed['errors']
			: array();

		$warnings = isset( $parsed['warnings'] )
			&& is_array( $parsed['warnings'] )
			? $parsed['warnings']
			: array();

		$counts['error'] += count( $errors );

		return array(
			'unit_code' => $unit_code,
			'unit_name' => isset( $parsed['unit_name'] )
				&& is_string( $parsed['unit_name'] )
				? $parsed['unit_name']
				: '',
			'rows'       => $rows,
			'errors'     => $errors,
			'warnings'   => $warnings,
			'counts'     => $counts,
			'token'      => $token,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Verify a preview token before import.
	 *
	 * The importer must parse and validate the payload again, then pass the new
	 * event list here. Preview data from the browser must never be trusted.
	 *
	 * @param string                   $token       Signed token.
	 * @param string                   $raw_payload Submitted raw payload.
	 * @param string                   $unit_code   Submitted unit code.
	 * @param array<int, array<string, mixed>> $events Reparsed events.
	 *
	 * @return bool
	 */
	public static function verify_token(
		string $token,
		string $raw_payload,
		string $unit_code,
		array $events
	): bool {
		if (
			'' === $token
			|| strlen( $token ) > self::MAX_TOKEN_LENGTH
		) {
			return false;
		}

		$parts = explode( '.', $token );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$encoded_payload = $parts[0];
		$provided_signature = $parts[1];

		$json = self::base64url_decode(
			$encoded_payload
		);

		if ( null === $json ) {
			return false;
		}

		try {
			$claims = json_decode(
				$json,
				true,
				16,
				JSON_THROW_ON_ERROR
			);
		} catch ( JsonException ) {
			return false;
		}

		if ( ! is_array( $claims ) ) {
			return false;
		}

		$required_claims = array(
			'version',
			'user_id',
			'unit_code',
			'payload_hash',
			'events_hash',
			'issued_at',
			'expires_at',
		);

		foreach ( $required_claims as $claim ) {
			if ( ! array_key_exists( $claim, $claims ) ) {
				return false;
			}
		}

		$expected_signature = self::sign(
			$encoded_payload
		);

		if (
			! hash_equals(
				$expected_signature,
				$provided_signature
			)
		) {
			return false;
		}

		$now = time();

		if (
			1 !== (int) $claims['version']
			|| get_current_user_id() !== (int) $claims['user_id']
			|| $now < (int) $claims['issued_at']
			|| $now > (int) $claims['expires_at']
			|| (int) $claims['expires_at']
				- (int) $claims['issued_at'] > self::TOKEN_TTL
		) {
			return false;
		}

		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$unit_code
		);

		if (
			'' === $unit_code
			|| ! hash_equals(
				(string) $claims['unit_code'],
				$unit_code
			)
		) {
			return false;
		}

		if (
			! hash_equals(
				(string) $claims['payload_hash'],
				hash( 'sha256', $raw_payload )
			)
		) {
			return false;
		}

		return hash_equals(
			(string) $claims['events_hash'],
			self::events_hash( $events )
		);
	}

	/**
	 * Determine whether preview contains blocking errors.
	 *
	 * @param array<string, mixed> $preview Preview.
	 *
	 * @return bool
	 */
	public static function has_blocking_errors(
		array $preview
	): bool {
		$counts = $preview['counts'] ?? array();

		return is_array( $counts )
			&& isset( $counts['error'] )
			&& (int) $counts['error'] > 0;
	}

	/**
	 * Return translated action label.
	 *
	 * @param string $action Action.
	 *
	 * @return string
	 */
	public static function action_label( string $action ): string {
		return match ( $action ) {
			Power_Schedule_Manager_Repository::ACTION_NEW =>
				__( 'Thêm mới', 'power-schedule-manager' ),

			Power_Schedule_Manager_Repository::ACTION_UPDATE =>
				__( 'Cập nhật', 'power-schedule-manager' ),

			Power_Schedule_Manager_Repository::ACTION_UNCHANGED =>
				__(
					'Đã tồn tại — không ghi thêm',
					'power-schedule-manager'
				),

			self::ACTION_POSSIBLE_DUPLICATE =>
				__( 'Có thể trùng', 'power-schedule-manager' ),

			'error' =>
				__( 'Lỗi', 'power-schedule-manager' ),

			default =>
				__( 'Không xác định', 'power-schedule-manager' ),
		};
	}

	/**
	 * Create signed token.
	 *
	 * @param string                   $raw_payload Raw payload.
	 * @param string                   $unit_code   Unit code.
	 * @param array<int, array<string, mixed>> $events Events.
	 * @param int                      $issued_at   Issue time.
	 * @param int                      $expires_at  Expiry time.
	 *
	 * @return string
	 */
	private static function create_token(
		string $raw_payload,
		string $unit_code,
		array $events,
		int $issued_at,
		int $expires_at
	): string {
		$claims = array(
			'version'      => 1,
			'user_id'      => get_current_user_id(),
			'unit_code'    => $unit_code,
			'payload_hash' => hash( 'sha256', $raw_payload ),
			'events_hash'  => self::events_hash( $events ),
			'issued_at'    => $issued_at,
			'expires_at'   => $expires_at,
		);

		try {
			$json = wp_json_encode(
				$claims,
				JSON_UNESCAPED_SLASHES
				| JSON_THROW_ON_ERROR,
				16
			);
		} catch ( JsonException $exception ) {
			throw new RuntimeException(
				'preview_token_encoding_failed',
				0,
				$exception
			);
		}

		if ( ! is_string( $json ) ) {
			throw new RuntimeException(
				'preview_token_encoding_failed'
			);
		}

		$encoded_payload = self::base64url_encode( $json );

		return $encoded_payload
			. '.'
			. self::sign( $encoded_payload );
	}

	/**
	 * Sign encoded claims.
	 *
	 * @param string $encoded_payload Encoded claims.
	 *
	 * @return string
	 */
	private static function sign(
		string $encoded_payload
	): string {
		return self::base64url_encode(
			hash_hmac(
				'sha256',
				$encoded_payload,
				wp_salt( 'auth' ),
				true
			)
		);
	}

	/**
	 * Build canonical event-list hash.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 *
	 * @return string
	 */
	private static function events_hash( array $events ): string {
		$canonical = array();

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$canonical[] = array(
				'unit_code'      =>
					(string) ( $event['unit_code'] ?? '' ),
				'source'         =>
					(string) ( $event['source'] ?? '' ),
				'source_event_id' =>
					(string) ( $event['source_event_id'] ?? '' ),
				'local_date'     =>
					(string) ( $event['local_date'] ?? '' ),
				'start_at_utc'   =>
					(string) ( $event['start_at_utc'] ?? '' ),
				'end_at_utc'     =>
					(string) ( $event['end_at_utc'] ?? '' ),
				'area'           =>
					(string) ( $event['area'] ?? '' ),
				'reason'         =>
					(string) ( $event['reason'] ?? '' ),
				'status'         =>
					(string) ( $event['status'] ?? '' ),
			);
		}

		try {
			$json = wp_json_encode(
				$canonical,
				JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_THROW_ON_ERROR,
				32
			);
		} catch ( JsonException $exception ) {
			throw new RuntimeException(
				'preview_events_encoding_failed',
				0,
				$exception
			);
		}

		if ( ! is_string( $json ) ) {
			throw new RuntimeException(
				'preview_events_encoding_failed'
			);
		}

		return hash( 'sha256', $json );
	}

	/**
	 * Find possible database duplicates.
	 *
	 * @param array<string, mixed> $event Validated event.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function possible_duplicates(
		array $event
	): array {
		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			(string) $event['local_date'],
			new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
		);

		if ( false === $date ) {
			return array();
		}

		$date_from = $date
			->modify( '-1 day' )
			->format( 'Y-m-d' );

		$date_to = $date
			->modify( '+1 day' )
			->format( 'Y-m-d' );

		$candidates = Power_Schedule_Manager_Repository::query(
			$date_from,
			$date_to,
			(string) $event['unit_code'],
			array(
				Power_Schedule_Manager_Status::SCHEDULED,
				Power_Schedule_Manager_Status::ONGOING,
				Power_Schedule_Manager_Status::COMPLETED,
				Power_Schedule_Manager_Status::CANCELLED,
			),
			100,
			0
		);

		$matches         = array();
		$event_timestamp = strtotime(
			(string) $event['start_at_utc'] . ' UTC'
		);

		if ( false === $event_timestamp ) {
			return array();
		}

		foreach ( $candidates as $candidate ) {
			$candidate_timestamp = strtotime(
				(string) $candidate['start_at_utc'] . ' UTC'
			);

			if ( false === $candidate_timestamp ) {
				continue;
			}

			if (
				abs( $event_timestamp - $candidate_timestamp )
				> self::DUPLICATE_TIME_WINDOW
			) {
				continue;
			}

			$similarity = self::area_similarity(
				(string) $event['area'],
				(string) $candidate['area']
			);

			if ( $similarity < self::AREA_SIMILARITY_THRESHOLD ) {
				continue;
			}

			$matches[] = array(
				'id'           => (int) $candidate['id'],
				'local_date'   => (string) $candidate['local_date'],
				'start_at_utc' => (string) $candidate['start_at_utc'],
				'end_at_utc'   => (string) $candidate['end_at_utc'],
				'area'         => (string) $candidate['area'],
				'reason'       => (string) $candidate['reason'],
				'status'       => (string) $candidate['status'],
				'similarity'   => round( $similarity, 2 ),
			);
		}

		return array_slice( $matches, 0, 5 );
	}

	/**
	 * Calculate Jaccard similarity between area tokens.
	 *
	 * @param string $first  First area.
	 * @param string $second Second area.
	 *
	 * @return float
	 */
	private static function area_similarity(
		string $first,
		string $second
	): float {
		$first_tokens  = self::area_tokens( $first );
		$second_tokens = self::area_tokens( $second );

		if (
			array() === $first_tokens
			|| array() === $second_tokens
		) {
			return 0.0;
		}

		$intersection = array_intersect(
			$first_tokens,
			$second_tokens
		);

		$union = array_unique(
			array_merge(
				$first_tokens,
				$second_tokens
			)
		);

		if ( array() === $union ) {
			return 0.0;
		}

		return count( array_unique( $intersection ) )
			/ count( $union );
	}

	/**
	 * Normalize area into comparison tokens.
	 *
	 * @param string $area Area text.
	 *
	 * @return array<int, string>
	 */
	private static function area_tokens( string $area ): array {
		$area = strtolower(
			remove_accents( $area )
		);

		$area = preg_replace(
			'/[^a-z0-9]+/',
			' ',
			$area
		) ?? '';

		$tokens = preg_split(
			'/\s+/',
			trim( $area ),
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		if ( ! is_array( $tokens ) ) {
			return array();
		}

		$stop_words = array(
			'khu',
			'vuc',
			'duong',
			'phuong',
			'xa',
			'thon',
			'to',
			'tu',
			'den',
			'va',
		);

		$tokens = array_filter(
			$tokens,
			static function ( string $token ) use (
				$stop_words
			): bool {
				return strlen( $token ) > 1
					&& ! in_array(
						$token,
						$stop_words,
						true
					);
			}
		);

		return array_values(
			array_unique( $tokens )
		);
	}

	/**
	 * Assert preview permission.
	 *
	 * @return void
	 */
	private static function assert_user_can_preview(): void {
		if (
			! is_user_logged_in()
			|| get_current_user_id() < 1
			|| ! Power_Schedule_Manager_Capabilities::current_user_can_import()
		) {
			throw new RuntimeException(
				'preview_permission_denied'
			);
		}
	}

	/**
	 * Encode URL-safe Base64 without padding.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private static function base64url_encode(
		string $value
	): string {
		return rtrim(
			strtr(
				base64_encode( $value ),
				'+/',
				'-_'
			),
			'='
		);
	}

	/**
	 * Decode URL-safe Base64.
	 *
	 * @param string $value Encoded value.
	 *
	 * @return string|null
	 */
	private static function base64url_decode(
		string $value
	): ?string {
		if (
			'' === $value
			|| 1 !== preg_match( '/\A[A-Za-z0-9_-]+\z/', $value )
		) {
			return null;
		}

		$padding = strlen( $value ) % 4;

		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		$decoded = base64_decode(
			strtr( $value, '-_', '+/' ),
			true
		);

		return false === $decoded
			? null
			: $decoded;
	}
}
