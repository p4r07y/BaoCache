<?php
/**
 * Power outage event status management.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calculates and describes schedule event statuses.
 *
 * All comparisons are performed using UTC. Local timezone conversion is only
 * used for display.
 */
final class Power_Schedule_Manager_Status {

	/**
	 * Event has not started.
	 */
	public const string SCHEDULED = 'scheduled';

	/**
	 * Event is currently in progress.
	 */
	public const string ONGOING = 'ongoing';

	/**
	 * Event has ended.
	 */
	public const string COMPLETED = 'completed';

	/**
	 * Event was explicitly cancelled by the source or an administrator.
	 */
	public const string CANCELLED = 'cancelled';

	/**
	 * Event disappeared from the source enough times to be considered removed.
	 */
	public const string REMOVED = 'removed';

	/**
	 * Supported status values.
	 *
	 * @var array<string, true>
	 */
	private const array ALLOWED_STATUSES = array(
		self::SCHEDULED => true,
		self::ONGOING   => true,
		self::COMPLETED => true,
		self::CANCELLED => true,
		self::REMOVED   => true,
	);

	/**
	 * Statuses controlled explicitly instead of by time.
	 *
	 * @var array<string, true>
	 */
	private const array TERMINAL_OVERRIDE_STATUSES = array(
		self::CANCELLED => true,
		self::REMOVED   => true,
	);

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Calculate current event status.
	 *
	 * Status rules:
	 * - cancelled and removed always take precedence.
	 * - now before start: scheduled.
	 * - now from start up to, but not including end: ongoing.
	 * - now at or after end: completed.
	 *
	 * @param DateTimeInterface|string      $start_at_utc Start time in UTC.
	 * @param DateTimeInterface|string      $end_at_utc   End time in UTC.
	 * @param string                        $stored_status Stored database status.
	 * @param DateTimeInterface|string|null $now_utc      Current UTC time.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When dates or status are invalid.
	 */
	public static function calculate(
		DateTimeInterface|string $start_at_utc,
		DateTimeInterface|string $end_at_utc,
		string $stored_status = self::SCHEDULED,
		DateTimeInterface|string|null $now_utc = null
	): string {
		$stored_status = self::normalize( $stored_status );

		if (
			isset(
				self::TERMINAL_OVERRIDE_STATUSES[ $stored_status ]
			)
		) {
			return $stored_status;
		}

		$start = self::to_utc_datetime(
			$start_at_utc,
			'start_at_utc'
		);

		$end = self::to_utc_datetime(
			$end_at_utc,
			'end_at_utc'
		);

		$now = null === $now_utc
			? new DateTimeImmutable( 'now', self::utc_timezone() )
			: self::to_utc_datetime( $now_utc, 'now_utc' );

		if ( $end <= $start ) {
			throw new InvalidArgumentException(
				__(
					'Thời gian kết thúc phải lớn hơn thời gian bắt đầu.',
					'power-schedule-manager'
				)
			);
		}

		if ( $now < $start ) {
			return self::SCHEDULED;
		}

		if ( $now < $end ) {
			return self::ONGOING;
		}

		return self::COMPLETED;
	}

	/**
	 * Normalize a stored status.
	 *
	 * @param string $status Raw status.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When status is unsupported.
	 */
	public static function normalize( string $status ): string {
		$status = sanitize_key(
			strtolower( trim( $status ) )
		);

		if ( ! isset( self::ALLOWED_STATUSES[ $status ] ) ) {
			throw new InvalidArgumentException(
				__( 'Trạng thái lịch điện không hợp lệ.', 'power-schedule-manager' )
			);
		}

		return $status;
	}

	/**
	 * Normalize a status and fall back safely.
	 *
	 * Useful when reading legacy or externally imported data.
	 *
	 * @param mixed  $status   Raw status.
	 * @param string $fallback Fallback status.
	 *
	 * @return string
	 */
	public static function normalize_or_default(
		mixed $status,
		string $fallback = self::SCHEDULED
	): string {
		try {
			$fallback = self::normalize( $fallback );
		} catch ( InvalidArgumentException ) {
			$fallback = self::SCHEDULED;
		}

		if ( ! is_string( $status ) ) {
			return $fallback;
		}

		try {
			return self::normalize( $status );
		} catch ( InvalidArgumentException ) {
			return $fallback;
		}
	}

	/**
	 * Return all supported statuses.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array_keys( self::ALLOWED_STATUSES );
	}

	/**
	 * Return a translated frontend label.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	public static function label( string $status ): string {
		$status = self::normalize_or_default( $status );

		return match ( $status ) {
			self::SCHEDULED => __(
				'Sắp cúp',
				'power-schedule-manager'
			),
			self::ONGOING => __(
				'Đang cúp',
				'power-schedule-manager'
			),
			self::COMPLETED => __(
				'Đã có điện',
				'power-schedule-manager'
			),
			self::CANCELLED => __(
				'Đã hủy',
				'power-schedule-manager'
			),
			self::REMOVED => __(
				'Đã gỡ',
				'power-schedule-manager'
			),
		};
	}

	/**
	 * Return an accessible status description.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	public static function description( string $status ): string {
		$status = self::normalize_or_default( $status );

		return match ( $status ) {
			self::SCHEDULED => __(
				'Lịch ngừng hoặc giảm cung cấp điện chưa bắt đầu.',
				'power-schedule-manager'
			),
			self::ONGOING => __(
				'Thời gian ngừng hoặc giảm cung cấp điện đang diễn ra.',
				'power-schedule-manager'
			),
			self::COMPLETED => __(
				'Thời gian ngừng hoặc giảm cung cấp điện dự kiến đã kết thúc.',
				'power-schedule-manager'
			),
			self::CANCELLED => __(
				'Lịch ngừng hoặc giảm cung cấp điện đã được thông báo hủy.',
				'power-schedule-manager'
			),
			self::REMOVED => __(
				'Lịch này không còn xuất hiện trong dữ liệu nguồn.',
				'power-schedule-manager'
			),
		};
	}

	/**
	 * Return an allowlisted CSS modifier.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	public static function css_class( string $status ): string {
		$status = self::normalize_or_default( $status );

		return 'psm-status--' . $status;
	}

	/**
	 * Return the map color assigned to a status.
	 *
	 * Colors are controlled by the plugin. GeoJSON properties cannot inject
	 * custom colors.
	 *
	 * @param string $status Status.
	 *
	 * @return string Hexadecimal color.
	 */
	public static function map_color( string $status ): string {
		$status = self::normalize_or_default( $status );

		return match ( $status ) {
			self::SCHEDULED => '#2563eb',
			self::ONGOING   => '#dc2626',
			self::COMPLETED => '#16a34a',
			self::CANCELLED => '#7c3aed',
			self::REMOVED   => '#475569',
		};
	}

	/**
	 * Return sort priority for frontend queries.
	 *
	 * Lower values appear first.
	 *
	 * @param string $status Status.
	 *
	 * @return int
	 */
	public static function sort_priority( string $status ): int {
		$status = self::normalize_or_default( $status );

		return match ( $status ) {
			self::ONGOING   => 10,
			self::SCHEDULED => 20,
			self::CANCELLED => 30,
			self::COMPLETED => 40,
			self::REMOVED   => 50,
		};
	}

	/**
	 * Determine whether an event may be displayed publicly.
	 *
	 * Cancelled events remain visible so visitors can see that the original
	 * announcement has changed. Removed events are hidden by default.
	 *
	 * @param string $status Status.
	 *
	 * @return bool
	 */
	public static function is_publicly_visible(
		string $status
	): bool {
		$status = self::normalize_or_default( $status );

		return self::REMOVED !== $status;
	}

	/**
	 * Determine whether a page is suitable for search indexing.
	 *
	 * Cancelled and removed schedules should normally be noindex. The SEO
	 * service will use this method when generating robots directives.
	 *
	 * @param string $status Status.
	 *
	 * @return bool
	 */
	public static function is_indexable( string $status ): bool {
		$status = self::normalize_or_default( $status );

		return in_array(
			$status,
			array(
				self::SCHEDULED,
				self::ONGOING,
				self::COMPLETED,
			),
			true
		);
	}

	/**
	 * Determine whether an event is currently active.
	 *
	 * @param string $status Status.
	 *
	 * @return bool
	 */
	public static function is_active( string $status ): bool {
		return self::ONGOING
			=== self::normalize_or_default( $status );
	}

	/**
	 * Convert a date value to immutable UTC.
	 *
	 * Database strings are interpreted as UTC. DateTime objects are converted
	 * from their original timezone to UTC.
	 *
	 * @param DateTimeInterface|string $value   Date value.
	 * @param string                   $context Field name for diagnostics.
	 *
	 * @return DateTimeImmutable
	 *
	 * @throws InvalidArgumentException When the date is invalid.
	 */
	private static function to_utc_datetime(
		DateTimeInterface|string $value,
		string $context
	): DateTimeImmutable {
		if ( $value instanceof DateTimeInterface ) {
			return DateTimeImmutable::createFromInterface( $value )
				->setTimezone( self::utc_timezone() );
		}

		$value = trim( $value );

		if ( '' === $value ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: Date field name. */
					__( 'Giá trị %s không được để trống.', 'power-schedule-manager' ),
					$context
				)
			);
		}

		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$value,
			self::utc_timezone()
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
				sprintf(
					/* translators: %s: Date field name. */
					__( 'Giá trị thời gian %s không hợp lệ.', 'power-schedule-manager' ),
					$context
				)
			);
		}

		return $date;
	}

	/**
	 * Return the UTC timezone object.
	 *
	 * @return DateTimeZone
	 */
	private static function utc_timezone(): DateTimeZone {
		static $timezone = null;

		if ( ! $timezone instanceof DateTimeZone ) {
			$timezone = new DateTimeZone( 'UTC' );
		}

		return $timezone;
	}
}
