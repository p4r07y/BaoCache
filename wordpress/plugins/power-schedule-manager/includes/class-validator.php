<?php
/**
 * Schedule event validation.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates and normalizes power schedule event data.
 */
final class Power_Schedule_Manager_Validator {

	/**
	 * Maximum area description length.
	 */
	private const int MAX_AREA_LENGTH = 10000;

	/**
	 * Maximum reason length.
	 */
	private const int MAX_REASON_LENGTH = 5000;

	/**
	 * Maximum source identifier length.
	 */
	private const int MAX_SOURCE_LENGTH = 32;

	/**
	 * Maximum source event identifier length.
	 */
	private const int MAX_SOURCE_EVENT_ID_LENGTH = 191;

	/**
	 * Duration that triggers a preview warning.
	 */
	private const int LONG_DURATION_SECONDS = 259200;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Validate and normalize one schedule event.
	 *
	 * Required input:
	 * - unit_code
	 * - local_date
	 * - start_at_utc
	 * - end_at_utc
	 * - area
	 * - reason
	 *
	 * Optional input:
	 * - source
	 * - source_event_id
	 * - status
	 *
	 * @param array<string, mixed> $event       Raw event.
	 * @param bool                 $verify_unit Verify unit against database.
	 *
	 * @return array{
	 *     unit_id: int,
	 *     unit_code: string,
	 *     source: string,
	 *     source_event_id: string,
	 *     local_date: string,
	 *     start_at_utc: string,
	 *     end_at_utc: string,
	 *     area: string,
	 *     reason: string,
	 *     status: string
	 * }
	 *
	 * @throws InvalidArgumentException When validation fails.
	 */
	public static function validate_event(
		array $event,
		bool $verify_unit = true
	): array {
		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$event['unit_code'] ?? $event['code'] ?? ''
		);

		if ( '' === $unit_code ) {
			throw new InvalidArgumentException(
				__( 'Mã đơn vị điện lực không hợp lệ.', 'power-schedule-manager' )
			);
		}

		$unit_id = 0;

		if ( $verify_unit ) {
			$unit = Power_Schedule_Manager_Units::find_by_code(
				$unit_code
			);

			if ( null === $unit ) {
				throw new InvalidArgumentException(
					sprintf(
						/* translators: %s: Electrical service unit code. */
						__(
							'Không tìm thấy đơn vị điện lực có mã %s.',
							'power-schedule-manager'
						),
						$unit_code
					)
				);
			}

			$unit_id = (int) $unit['id'];
		}

		$source = self::sanitize_source(
			$event['source'] ?? 'evn'
		);

		$source_event_id = self::sanitize_source_event_id(
			$event['source_event_id'] ?? ''
		);

		$local_date = self::validate_local_date(
			$event['local_date'] ?? ''
		);

		$start = self::parse_utc_datetime(
			$event['start_at_utc'] ?? '',
			'start_at_utc'
		);

		$end = self::parse_utc_datetime(
			$event['end_at_utc'] ?? '',
			'end_at_utc'
		);

		if ( $end <= $start ) {
			throw new InvalidArgumentException(
				__(
					'Thời gian kết thúc phải lớn hơn thời gian bắt đầu.',
					'power-schedule-manager'
				)
			);
		}

		$area = self::sanitize_required_text(
			$event['area'] ?? '',
			self::MAX_AREA_LENGTH,
			__( 'Khu vực', 'power-schedule-manager' )
		);

		$reason = self::sanitize_required_text(
			$event['reason'] ?? '',
			self::MAX_REASON_LENGTH,
			__( 'Lý do', 'power-schedule-manager' )
		);

		$status = Power_Schedule_Manager_Status::normalize_or_default(
			$event['status'] ?? Power_Schedule_Manager_Status::SCHEDULED
		);

		self::assert_local_date_matches_start(
			$local_date,
			$start,
			$unit_code,
			$verify_unit
		);

		return array(
			'unit_id'        => $unit_id,
			'unit_code'      => $unit_code,
			'source'         => $source,
			'source_event_id' => $source_event_id,
			'local_date'     => $local_date,
			'start_at_utc'   => $start->format( 'Y-m-d H:i:s' ),
			'end_at_utc'     => $end->format( 'Y-m-d H:i:s' ),
			'area'           => $area,
			'reason'         => $reason,
			'status'         => $status,
		);
	}

	/**
	 * Validate a batch without stopping at the first invalid row.
	 *
	 * @param array<int, mixed> $events      Raw events.
	 * @param bool              $verify_unit Verify units against database.
	 *
	 * @return array{
	 *     valid: array<int, array<string, mixed>>,
	 *     errors: array<int, array{index: int, message: string}>,
	 *     warnings: array<int, array{index: int, code: string, message: string}>
	 * }
	 */
	public static function validate_batch(
		array $events,
		bool $verify_unit = true
	): array {
		$valid    = array();
		$errors   = array();
		$warnings = array();

		foreach ( $events as $index => $event ) {
			if ( ! is_array( $event ) ) {
				$errors[] = array(
					'index'   => (int) $index,
					'message' => __(
						'Dòng dữ liệu không có cấu trúc hợp lệ.',
						'power-schedule-manager'
					),
				);

				continue;
			}

			try {
				$normalized = self::validate_event(
					$event,
					$verify_unit
				);

				$valid[] = $normalized;

				foreach (
					self::event_warnings( $normalized )
					as $warning
				) {
					$warnings[] = array(
						'index'   => (int) $index,
						'code'    => $warning['code'],
						'message' => $warning['message'],
					);
				}
			} catch ( InvalidArgumentException $exception ) {
				$errors[] = array(
					'index'   => (int) $index,
					'message' => $exception->getMessage(),
				);
			}
		}

		return array(
			'valid'    => $valid,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Convert a local date and time to UTC database format.
	 *
	 * Accepted time formats:
	 * - H:i
	 * - H:i:s
	 *
	 * @param string $local_date Local date in Y-m-d.
	 * @param string $local_time Local time.
	 * @param string $timezone   IANA timezone identifier.
	 *
	 * @return string UTC date in Y-m-d H:i:s.
	 *
	 * @throws InvalidArgumentException When input is invalid.
	 */
	public static function local_to_utc(
		string $local_date,
		string $local_time,
		string $timezone = POWER_SCHEDULE_MANAGER_TIMEZONE
	): string {
		$local_date = self::validate_local_date( $local_date );
		$local_time = self::validate_local_time( $local_time );
		$timezone   = self::validate_timezone( $timezone );

		$format = 5 === strlen( $local_time )
			? '!Y-m-d H:i'
			: '!Y-m-d H:i:s';

		$date = DateTimeImmutable::createFromFormat(
			$format,
			$local_date . ' ' . $local_time,
			new DateTimeZone( $timezone )
		);

		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $date
			|| (
				is_array( $errors )
				&& (
					$errors['warning_count'] > 0
					|| $errors['error_count'] > 0
				)
			)
		) {
			throw new InvalidArgumentException(
				__(
					'Ngày hoặc giờ địa phương không hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		return $date
			->setTimezone( new DateTimeZone( 'UTC' ) )
			->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Convert a dd/mm/YYYY date to Y-m-d.
	 *
	 * @param string $date Vietnamese display date.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When date is invalid.
	 */
	public static function display_date_to_database(
		string $date
	): string {
		$date = trim( $date );

		$parsed = DateTimeImmutable::createFromFormat(
			'!d/m/Y',
			$date,
			new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
		);

		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $parsed
			|| (
				is_array( $errors )
				&& (
					$errors['warning_count'] > 0
					|| $errors['error_count'] > 0
				)
			)
		) {
			throw new InvalidArgumentException(
				__(
					'Ngày phải có định dạng ngày/tháng/năm hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		return $parsed->format( 'Y-m-d' );
	}

	/**
	 * Validate database local date.
	 *
	 * @param mixed $value Raw local date.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	public static function validate_local_date( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException(
				__(
					'Ngày lịch điện không hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		$value = trim( $value );

		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$value,
			new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
		);

		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $date
			|| (
				is_array( $errors )
				&& (
					$errors['warning_count'] > 0
					|| $errors['error_count'] > 0
				)
			)
			|| $date->format( 'Y-m-d' ) !== $value
		) {
			throw new InvalidArgumentException(
				__(
					'Ngày lịch điện phải có định dạng Y-m-d hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		return $value;
	}

	/**
	 * Validate local time.
	 *
	 * @param string $time Local time.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	public static function validate_local_time(
		string $time
	): string {
		$time = trim( $time );

		$format = match ( strlen( $time ) ) {
			5       => '!H:i',
			8       => '!H:i:s',
			default => '',
		};

		if ( '' === $format ) {
			throw new InvalidArgumentException(
				__(
					'Giờ phải có định dạng HH:MM hoặc HH:MM:SS.',
					'power-schedule-manager'
				)
			);
		}

		$date = DateTimeImmutable::createFromFormat(
			$format,
			$time,
			new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
		);

		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $date
			|| (
				is_array( $errors )
				&& (
					$errors['warning_count'] > 0
					|| $errors['error_count'] > 0
				)
			)
		) {
			throw new InvalidArgumentException(
				__( 'Giờ không hợp lệ.', 'power-schedule-manager' )
			);
		}

		return $time;
	}

	/**
	 * Parse strict UTC database datetime.
	 *
	 * @param mixed  $value   Raw value.
	 * @param string $context Field name.
	 *
	 * @return DateTimeImmutable
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	public static function parse_utc_datetime(
		mixed $value,
		string $context = 'datetime'
	): DateTimeImmutable {
		if ( $value instanceof DateTimeInterface ) {
			return DateTimeImmutable::createFromInterface( $value )
				->setTimezone( new DateTimeZone( 'UTC' ) );
		}

		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: Date field name. */
					__( 'Giá trị %s không hợp lệ.', 'power-schedule-manager' ),
					$context
				)
			);
		}

		$value = trim( $value );

		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$value,
			new DateTimeZone( 'UTC' )
		);

		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $date
			|| (
				is_array( $errors )
				&& (
					$errors['warning_count'] > 0
					|| $errors['error_count'] > 0
				)
			)
			|| $date->format( 'Y-m-d H:i:s' ) !== $value
		) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: Date field name. */
					__(
						'Giá trị %s phải là thời gian UTC dạng Y-m-d H:i:s.',
						'power-schedule-manager'
					),
					$context
				)
			);
		}

		return $date;
	}

	/**
	 * Sanitize event source.
	 *
	 * @param mixed $value Raw source.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	private static function sanitize_source( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			throw new InvalidArgumentException(
				__( 'Nguồn dữ liệu không hợp lệ.', 'power-schedule-manager' )
			);
		}

		$source = sanitize_key(
			strtolower( trim( (string) $value ) )
		);

		if (
			'' === $source
			|| strlen( $source ) > self::MAX_SOURCE_LENGTH
		) {
			throw new InvalidArgumentException(
				__( 'Nguồn dữ liệu không hợp lệ.', 'power-schedule-manager' )
			);
		}

		return $source;
	}

	/**
	 * Sanitize source event identifier.
	 *
	 * @param mixed $value Raw identifier.
	 *
	 * @return string
	 */
	private static function sanitize_source_event_id(
		mixed $value
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field(
			(string) $value
		);

		return self::limit_text(
			$value,
			self::MAX_SOURCE_EVENT_ID_LENGTH
		);
	}

	/**
	 * Sanitize a required plain-text field.
	 *
	 * @param mixed  $value      Raw value.
	 * @param int    $max_length Maximum length.
	 * @param string $label      User-facing field label.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	private static function sanitize_required_text(
		mixed $value,
		int $max_length,
		string $label
	): string {
		if ( ! is_scalar( $value ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: Field label. */
					__( '%s không hợp lệ.', 'power-schedule-manager' ),
					$label
				)
			);
		}

		$value = sanitize_textarea_field(
			(string) $value
		);

		$value = trim(
			preg_replace(
				'/[ \t]+/u',
				' ',
				$value
			) ?? ''
		);

		if ( '' === $value ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: Field label. */
					__( '%s không được để trống.', 'power-schedule-manager' ),
					$label
				)
			);
		}

		if ( self::text_length( $value ) > $max_length ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: 1: Field label, 2: Maximum characters. */
					__(
						'%1$s vượt quá giới hạn %2$d ký tự.',
						'power-schedule-manager'
					),
					$label,
					$max_length
				)
			);
		}

		return $value;
	}

	/**
	 * Confirm local_date matches the start time in the unit timezone.
	 *
	 * @param string            $local_date Local database date.
	 * @param DateTimeImmutable $start      UTC start time.
	 * @param string            $unit_code  Unit code.
	 * @param bool              $verify_unit Whether unit lookup is enabled.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When dates do not match.
	 */
	private static function assert_local_date_matches_start(
		string $local_date,
		DateTimeImmutable $start,
		string $unit_code,
		bool $verify_unit
	): void {
		$timezone = POWER_SCHEDULE_MANAGER_TIMEZONE;

		if ( $verify_unit ) {
			$unit = Power_Schedule_Manager_Units::find_by_code(
				$unit_code
			);

			if (
				null !== $unit
				&& isset( $unit['timezone'] )
				&& is_string( $unit['timezone'] )
				&& '' !== $unit['timezone']
			) {
				$timezone = $unit['timezone'];
			}
		}

		$calculated_date = $start
			->setTimezone( new DateTimeZone( $timezone ) )
			->format( 'Y-m-d' );

		if ( $calculated_date !== $local_date ) {
			throw new InvalidArgumentException(
				__(
					'Ngày địa phương không khớp với thời gian bắt đầu.',
					'power-schedule-manager'
				)
			);
		}
	}

	/**
	 * Generate non-blocking validation warnings.
	 *
	 * @param array<string, mixed> $event Normalized event.
	 *
	 * @return array<int, array{code: string, message: string}>
	 */
	private static function event_warnings( array $event ): array {
		$warnings = array();

		$start = self::parse_utc_datetime(
			$event['start_at_utc'],
			'start_at_utc'
		);

		$end = self::parse_utc_datetime(
			$event['end_at_utc'],
			'end_at_utc'
		);

		$duration = $end->getTimestamp() - $start->getTimestamp();

		if ( $duration > self::LONG_DURATION_SECONDS ) {
			$warnings[] = array(
				'code'    => 'unusually_long_duration',
				'message' => __(
					'Lịch kéo dài hơn 72 giờ. Cần kiểm tra lại thời gian.',
					'power-schedule-manager'
				),
			);
		}

		if ( self::text_length( $event['area'] ) < 5 ) {
			$warnings[] = array(
				'code'    => 'very_short_area',
				'message' => __(
					'Mô tả khu vực quá ngắn. Cần kiểm tra lại dữ liệu nguồn.',
					'power-schedule-manager'
				),
			);
		}

		if ( self::text_length( $event['reason'] ) < 5 ) {
			$warnings[] = array(
				'code'    => 'very_short_reason',
				'message' => __(
					'Mô tả lý do quá ngắn. Cần kiểm tra lại dữ liệu nguồn.',
					'power-schedule-manager'
				),
			);
		}

		return $warnings;
	}

	/**
	 * Validate timezone identifier.
	 *
	 * @param string $timezone Timezone identifier.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	private static function validate_timezone(
		string $timezone
	): string {
		$timezone = trim( $timezone );

		if (
			'' === $timezone
			|| ! in_array(
				$timezone,
				timezone_identifiers_list(),
				true
			)
		) {
			throw new InvalidArgumentException(
				__( 'Múi giờ không hợp lệ.', 'power-schedule-manager' )
			);
		}

		return $timezone;
	}

	/**
	 * Return text length with or without mbstring.
	 *
	 * @param string $value Text.
	 *
	 * @return int
	 */
	private static function text_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value );
		}

		return strlen( $value );
	}

	/**
	 * Limit plain text safely.
	 *
	 * @param string $value  Text.
	 * @param int    $length Maximum characters.
	 *
	 * @return string
	 */
	private static function limit_text(
		string $value,
		int $length
	): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}
}
