<?php
/**
 * Scheduled task management.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and executes plugin cron events.
 */
final class Power_Schedule_Manager_Cron {

	/**
	 * Daily maintenance hook.
	 */
	public const string DAILY_HOOK =
		'power_schedule_manager_daily_maintenance';

	/**
	 * Daily execution hour in the plugin timezone.
	 */
	private const int DAILY_HOUR = 3;

	/**
	 * Daily execution minute.
	 */
	private const int DAILY_MINUTE = 20;

	/**
	 * Database lock name.
	 */
	private const string LOCK_NAME =
		'psm_daily_maintenance';

	/**
	 * Database lock timeout.
	 */
	private const int LOCK_TIMEOUT = 1;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			self::DAILY_HOOK,
			array( $this, 'run_daily_maintenance' )
		);

		add_action(
			'admin_init',
			array( $this, 'maybe_restore_schedule' ),
			30
		);
	}

	/**
	 * Schedule plugin cron events.
	 *
	 * This method is called during activation and is idempotent.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When scheduling fails.
	 */
	public static function schedule_events(): void {
		if ( false !== wp_next_scheduled( self::DAILY_HOOK ) ) {
			return;
		}

		$timestamp = self::next_daily_timestamp();

		$result = wp_schedule_event(
			$timestamp,
			'daily',
			self::DAILY_HOOK,
			array(),
			true
		);

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException(
				'cron_schedule_failed: '
				. $result->get_error_code()
			);
		}

		if ( false === $result ) {
			throw new RuntimeException(
				'cron_schedule_failed'
			);
		}

		if ( false === wp_next_scheduled( self::DAILY_HOOK ) ) {
			throw new RuntimeException(
				'cron_schedule_not_persisted'
			);
		}
	}

	/**
	 * Clear all plugin cron events.
	 *
	 * This method is called during deactivation and failed activation cleanup.
	 *
	 * @return void
	 */
	public static function clear_scheduled_events(): void {
		wp_clear_scheduled_hook(
			self::DAILY_HOOK
		);

		wp_clear_scheduled_hook(
			Power_Schedule_Manager_Notifications::WORKER_HOOK
		);

		if (
			class_exists(
				'Power_Schedule_Manager_Market_Prices'
			)
		) {
			wp_clear_scheduled_hook(
				Power_Schedule_Manager_Market_Prices::GOLD_REFRESH_HOOK
			);
		}

		if ( class_exists( 'Power_Schedule_Manager_Weather' ) ) {
			wp_clear_scheduled_hook(
				Power_Schedule_Manager_Weather::REFRESH_HOOK
			);
		}
	}

	/**
	 * Restore the daily task if WordPress cron was externally cleared.
	 *
	 * Only an administrator with plugin-management permission may trigger the
	 * repair check.
	 *
	 * @return void
	 */
	public function maybe_restore_schedule(): void {
		if (
			! current_user_can( 'activate_plugins' )
			|| wp_doing_ajax()
		) {
			return;
		}

		if ( false !== wp_next_scheduled( self::DAILY_HOOK ) ) {
			return;
		}

		try {
			self::schedule_events();
		} catch ( Throwable $throwable ) {
			self::log_error(
				'cron_restore_failed',
				$throwable
			);
		}
	}

	/**
	 * Execute daily maintenance.
	 *
	 * @return void
	 */
	public function run_daily_maintenance(): void {
		if ( ! self::acquire_lock() ) {
			/**
			 * Fires when another maintenance process already owns the lock.
			 */
			do_action(
				'power_schedule_manager_cron_skipped',
				'already_running'
			);

			return;
		}

		$started_at = microtime( true );

		try {
			if (
				! class_exists(
					'Power_Schedule_Manager_Cleanup'
				)
			) {
				throw new RuntimeException(
					'cleanup_service_missing'
				);
			}

			$result =
				Power_Schedule_Manager_Cleanup::run_daily();

			/**
			 * Fires after successful daily maintenance.
			 *
			 * @param array<string, int> $result   Cleanup counters.
			 * @param float              $duration Duration in seconds.
			 */
			do_action(
				'power_schedule_manager_daily_maintenance_completed',
				$result,
				microtime( true ) - $started_at
			);
		} catch ( Throwable $throwable ) {
			self::log_error(
				'daily_maintenance_failed',
				$throwable
			);

			/**
			 * Fires after daily maintenance fails.
			 *
			 * The throwable should only be written to protected logs.
			 *
			 * @param Throwable $throwable Original exception.
			 */
			do_action(
				'power_schedule_manager_daily_maintenance_failed',
				$throwable
			);
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Return next scheduled event timestamp.
	 *
	 * @return int|null
	 */
	public static function next_run_timestamp(): ?int {
		$timestamp = wp_next_scheduled(
			self::DAILY_HOOK
		);

		return false === $timestamp
			? null
			: (int) $timestamp;
	}

	/**
	 * Calculate the next 03:20 execution in plugin timezone.
	 *
	 * @return int UTC Unix timestamp.
	 */
	private static function next_daily_timestamp(): int {
		$timezone = new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		);

		$now = new DateTimeImmutable(
			'now',
			$timezone
		);

		$next = $now->setTime(
			self::DAILY_HOUR,
			self::DAILY_MINUTE,
			0
		);

		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Acquire the maintenance named lock.
	 *
	 * @return bool
	 */
	private static function acquire_lock(): bool {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, %d)',
				self::LOCK_NAME,
				self::LOCK_TIMEOUT
			)
		);

		return '1' === (string) $result;
	}

	/**
	 * Release the maintenance named lock.
	 *
	 * @return void
	 */
	private static function release_lock(): void {
		global $wpdb;

		$wpdb->get_var(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s)',
				self::LOCK_NAME
			)
		);
	}

	/**
	 * Log a protected cron error.
	 *
	 * @param string    $code      Error code.
	 * @param Throwable $throwable Error.
	 *
	 * @return void
	 */
	private static function log_error(
		string $code,
		Throwable $throwable
	): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$message = preg_replace(
			'/[\r\n\0]+/',
			' ',
			$throwable->getMessage()
		) ?? '';

		error_log(
			sprintf(
				'Cúp Điện Lâm Đồng [%s]: %s',
				sanitize_key( $code ),
				substr( $message, 0, 1000 )
			)
		);
	}
}
