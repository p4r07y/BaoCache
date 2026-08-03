<?php
/**
 * Scheduled data cleanup.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Performs bounded daily retention and orphan cleanup.
 */
final class Power_Schedule_Manager_Cleanup {

	/**
	 * Raw payload retention.
	 */
	private const int RAW_PAYLOAD_RETENTION_DAYS = 30;

	/**
	 * Import log retention.
	 */
	private const int IMPORT_LOG_RETENTION_MONTHS = 12;

	/**
	 * Completed event retention.
	 */
	private const int COMPLETED_RETENTION_MONTHS = 24;

	/**
	 * Cancelled and removed event retention.
	 */
	private const int CANCELLED_RETENTION_MONTHS = 12;

	/**
	 * Import is considered stale after two hours.
	 */
	private const int STALE_IMPORT_HOURS = 2;

	/**
	 * Maximum events deleted in one daily run.
	 */
	private const int EVENT_BATCH_SIZE = 200;

	/**
	 * Maximum status rows updated per daily run.
	 */
	private const int STATUS_BATCH_SIZE = 1000;

	/**
	 * Maximum orphan rows deleted per table and run.
	 */
	private const int ORPHAN_BATCH_SIZE = 500;

	/**
	 * Maximum import logs deleted per run.
	 */
	private const int IMPORT_BATCH_SIZE = 200;

	/**
	 * Sent and failed notifications retention.
	 */
	private const int NOTIFICATION_RETENTION_DAYS = 90;

	/**
	 * Remove network-identifying donation hashes after this many days.
	 */
	private const int DONATION_IP_RETENTION_DAYS = 30;

	/**
	 * Maximum donation rows processed per cleanup pass.
	 */
	private const int DONATION_BATCH_SIZE = 200;

	/**
	 * Maximum orphan posts moved to trash per run.
	 */
	private const int POST_BATCH_SIZE = 100;

	/**
	 * Maximum cleanup passes in one cron invocation.
	 *
	 * Each pass remains bounded. Additional passes let a busy site catch up
	 * without turning one SQL statement into a large, long-running write.
	 */
	private const int MAX_PASSES = 5;

	/**
	 * Soft execution budget for additional cleanup passes.
	 *
	 * The first pass always runs completely. The budget only prevents starting
	 * another pass, so every cleanup category gets a fair chance on each run.
	 */
	private const float MAX_ADDITIONAL_PASS_SECONDS = 20.0;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Run all daily cleanup tasks.
	 *
	 * @return array<string, int>
	 */
	public static function run_daily(): array {
		$result = array(
			'statuses_completed'      => 0,
			'stale_imports_failed'    => 0,
			'raw_payloads_cleared'    => 0,
			'events_deleted'          => 0,
			'locations_deleted'       => 0,
			'event_place_links_deleted' => 0,
			'revisions_deleted'       => 0,
			'orphan_locations_deleted' => 0,
			'orphan_event_place_links_deleted' => 0,
			'orphan_revisions_deleted' => 0,
			'import_logs_deleted'     => 0,
			'notifications_deleted'   => 0,
			'donation_ip_hashes_cleared' => 0,
			'donations_deleted'       => 0,
			'posts_trashed'           => 0,
		);

		$started_at = microtime( true );

		for ( $pass = 1; $pass <= self::MAX_PASSES; ++$pass ) {
			$pass_result = self::run_one_pass();

			foreach ( $pass_result as $key => $count ) {
				$result[ $key ] += $count;
			}

			if ( ! self::pass_reached_a_limit( $pass_result ) ) {
				break;
			}

			if (
				( microtime( true ) - $started_at )
				>= self::MAX_ADDITIONAL_PASS_SECONDS
			) {
				break;
			}
		}

		if ( array_sum( $result ) > 0 ) {
			Power_Schedule_Manager_Cache::invalidate_all();

			do_action(
				'power_schedule_manager_page_cache_purge',
				'cleanup',
				$result
			);
		}

		return $result;
	}

	/**
	 * Run one fair, bounded pass across every cleanup category.
	 *
	 * @return array<string, int>
	 */
	private static function run_one_pass(): array {
		$result = array(
			'statuses_completed'      => self::complete_past_events(),
			'stale_imports_failed'    => self::fail_stale_imports(),
			'raw_payloads_cleared'    => self::clear_expired_raw_payloads(),
			'events_deleted'          => 0,
			'locations_deleted'       => 0,
			'event_place_links_deleted' => 0,
			'revisions_deleted'       => 0,
			'orphan_locations_deleted' =>
				0,
			'orphan_event_place_links_deleted' =>
				0,
			'orphan_revisions_deleted' =>
				0,
			'import_logs_deleted'     => 0,
			'notifications_deleted'   => 0,
			'donation_ip_hashes_cleared' => 0,
			'donations_deleted'       => 0,
			'posts_trashed'           => 0,
		);

		$event_cleanup = self::delete_expired_events();

		$result['events_deleted'] =
			$event_cleanup['events'];
		$result['locations_deleted'] =
			$event_cleanup['locations'];
		$result['event_place_links_deleted'] =
			$event_cleanup['event_place_links'];
		$result['revisions_deleted'] =
			$event_cleanup['revisions'];
		$result['orphan_locations_deleted'] =
			self::delete_orphan_locations();
		$result['orphan_event_place_links_deleted'] =
			self::delete_orphan_event_place_links();
		$result['orphan_revisions_deleted'] =
			self::delete_orphan_revisions();
		$result['import_logs_deleted'] =
			self::delete_expired_import_logs();
		$result['notifications_deleted'] =
			self::delete_expired_notifications();
		$result['donation_ip_hashes_cleared'] =
			self::clear_expired_donation_ip_hashes();
		$result['donations_deleted'] =
			self::delete_expired_unverified_donations();
		$result['posts_trashed'] =
			self::trash_orphan_schedule_posts();

		return $result;
	}

	/**
	 * Determine whether a pass filled any batch and may need another pass.
	 *
	 * Dependent rows deleted with expired events are intentionally excluded:
	 * their counts may exceed the event batch but do not indicate a backlog.
	 *
	 * @param array<string, int> $result Pass counters.
	 */
	private static function pass_reached_a_limit( array $result ): bool {
		$limits = array(
			'statuses_completed'      => self::STATUS_BATCH_SIZE,
			'stale_imports_failed'    => self::IMPORT_BATCH_SIZE,
			'raw_payloads_cleared'    => self::IMPORT_BATCH_SIZE,
			'events_deleted'          => self::EVENT_BATCH_SIZE,
			'orphan_locations_deleted' =>
				self::ORPHAN_BATCH_SIZE,
			'orphan_event_place_links_deleted' =>
				self::ORPHAN_BATCH_SIZE,
			'orphan_revisions_deleted' =>
				self::ORPHAN_BATCH_SIZE,
			'import_logs_deleted'     => self::IMPORT_BATCH_SIZE,
			'notifications_deleted'   => self::IMPORT_BATCH_SIZE,
			'donation_ip_hashes_cleared' =>
				self::DONATION_BATCH_SIZE,
			'donations_deleted'       => self::DONATION_BATCH_SIZE,
			'posts_trashed'           => self::POST_BATCH_SIZE,
		);

		foreach ( $limits as $key => $limit ) {
			if ( ( $result[ $key ] ?? 0 ) >= $limit ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mark past scheduled or ongoing events as completed.
	 *
	 * @return int Updated rows.
	 */
	private static function complete_past_events(): int {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$now = Power_Schedule_Manager_Database::utc_now();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					status = %s,
					updated_at_utc = %s
				WHERE status IN (%s, %s)
					AND end_at_utc <= %s
					AND deleted_at_utc IS NULL
				ORDER BY end_at_utc ASC, id ASC
				LIMIT %d",
				Power_Schedule_Manager_Status::COMPLETED,
				$now,
				Power_Schedule_Manager_Status::SCHEDULED,
				Power_Schedule_Manager_Status::ONGOING,
				$now,
				self::STATUS_BATCH_SIZE
			)
		);

		return self::query_count(
			$result,
			'cleanup_complete_past_events_failed'
		);
	}

	/**
	 * Mark stale running imports as failed.
	 *
	 * @return int Updated rows.
	 */
	private static function fail_stale_imports(): int {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$now = Power_Schedule_Manager_Database::utc_now();
		$cutoff = self::utc_cutoff(
			sprintf(
				'-%d hours',
				self::STALE_IMPORT_HOURS
			)
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					status = 'failed',
					error_count = error_count + 1,
					error_code = 'stale_import',
					error_message = %s,
					finished_at_utc = %s,
					updated_at_utc = %s
				WHERE status = 'running'
					AND started_at_utc < %s
				ORDER BY started_at_utc ASC, id ASC
				LIMIT %d",
				__(
					'Import không hoàn tất trong thời gian cho phép.',
					'power-schedule-manager'
				),
				$now,
				$now,
				$cutoff,
				self::IMPORT_BATCH_SIZE
			)
		);

		return self::query_count(
			$result,
			'cleanup_fail_stale_imports_failed'
		);
	}

	/**
	 * Remove expired raw payload content while preserving audit counters.
	 *
	 * @return int Updated rows.
	 */
	private static function clear_expired_raw_payloads(): int {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$cutoff = self::utc_cutoff(
			sprintf(
				'-%d days',
				self::retention_setting(
					'raw_payload_retention_days',
					self::RAW_PAYLOAD_RETENTION_DAYS,
					7,
					90
				)
			)
		);
		$now = Power_Schedule_Manager_Database::utc_now();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					raw_payload = NULL,
					updated_at_utc = %s
				WHERE raw_payload IS NOT NULL
					AND finished_at_utc IS NOT NULL
					AND finished_at_utc < %s
				ORDER BY finished_at_utc ASC, id ASC
				LIMIT %d",
				$now,
				$cutoff,
				self::IMPORT_BATCH_SIZE
			)
		);

		return self::query_count(
			$result,
			'cleanup_clear_raw_payloads_failed'
		);
	}

	/**
	 * Delete expired events and their dependent custom-table rows.
	 *
	 * @return array{
	 *     events: int,
	 *     locations: int,
	 *     event_place_links: int,
	 *     revisions: int
	 * }
	 */
	private static function delete_expired_events(): array {
		global $wpdb;

		$events_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$locations_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_LOCATIONS
		);
		$revisions_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_REVISIONS
		);
		$event_places_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);

		$completed_cutoff = self::utc_cutoff(
			sprintf(
				'-%d months',
				self::retention_setting(
					'completed_retention_months',
					self::COMPLETED_RETENTION_MONTHS,
					12,
					60
				)
			)
		);

		$cancelled_cutoff = self::utc_cutoff(
			sprintf(
				'-%d months',
				self::retention_setting(
					'cancelled_retention_months',
					self::CANCELLED_RETENTION_MONTHS,
					3,
					36
				)
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, post_id
				FROM {$events_table}
				WHERE status = %s
					AND end_at_utc < %s
				ORDER BY end_at_utc ASC, id ASC
				LIMIT %d",
				Power_Schedule_Manager_Status::COMPLETED,
				$completed_cutoff,
				self::EVENT_BATCH_SIZE
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			throw new RuntimeException(
				'cleanup_select_completed_events_failed: '
				. Power_Schedule_Manager_Database::last_error()
			);
		}

		$remaining = self::EVENT_BATCH_SIZE - count( $rows );

		if ( $remaining > 0 ) {
			$cancelled_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, post_id
					FROM {$events_table}
					WHERE status IN (%s, %s)
						AND updated_at_utc < %s
					ORDER BY updated_at_utc ASC, id ASC
					LIMIT %d",
					Power_Schedule_Manager_Status::CANCELLED,
					Power_Schedule_Manager_Status::REMOVED,
					$cancelled_cutoff,
					$remaining
				),
				ARRAY_A
			);

			if ( ! is_array( $cancelled_rows ) ) {
				throw new RuntimeException(
					'cleanup_select_cancelled_events_failed: '
					. Power_Schedule_Manager_Database::last_error()
				);
			}

			$rows = array_merge( $rows, $cancelled_rows );
		}

		if ( array() === $rows ) {
			return array(
				'events'    => 0,
				'locations' => 0,
				'event_place_links' => 0,
				'revisions' => 0,
			);
		}

		$event_ids = array_values(
			array_filter(
				array_map(
					static fn ( array $row ): int =>
						absint( $row['id'] ?? 0 ),
					$rows
				)
			)
		);

		if ( array() === $event_ids ) {
			return array(
				'events'    => 0,
				'locations' => 0,
				'event_place_links' => 0,
				'revisions' => 0,
			);
		}

		$id_list = implode(
			',',
			array_map( 'absint', $event_ids )
		);

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			throw new RuntimeException(
				'cleanup_transaction_start_failed: '
				. Power_Schedule_Manager_Database::last_error()
			);
		}

		try {
			$locations = self::query_count(
				$wpdb->query(
					"DELETE FROM {$locations_table}
					WHERE event_id IN ({$id_list})"
				),
				'cleanup_location_delete_failed'
			);

			$event_place_links = self::query_count(
				$wpdb->query(
					"DELETE FROM {$event_places_table}
					WHERE event_id IN ({$id_list})"
				),
				'cleanup_event_place_link_delete_failed'
			);

			$revisions = self::query_count(
				$wpdb->query(
					"DELETE FROM {$revisions_table}
					WHERE event_id IN ({$id_list})"
				),
				'cleanup_revision_delete_failed'
			);

			$events = self::query_count(
				$wpdb->query(
					"DELETE FROM {$events_table}
					WHERE id IN ({$id_list})"
				),
				'cleanup_event_delete_failed'
			);

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException(
					'cleanup_transaction_commit_failed: '
					. Power_Schedule_Manager_Database::last_error()
				);
			}
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );

			throw $throwable;
		}

		return array(
			'events'    => (int) $events,
			'locations' => (int) $locations,
			'event_place_links' => (int) $event_place_links,
			'revisions' => (int) $revisions,
		);
	}

	/**
	 * Delete location rows whose event no longer exists.
	 *
	 * @return int Deleted rows.
	 */
	private static function delete_orphan_locations(): int {
		global $wpdb;

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$locations = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_LOCATIONS
		);

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT location_row.id
				FROM {$locations} AS location_row
				LEFT JOIN {$events} AS event_row
					ON event_row.id = location_row.event_id
				WHERE event_row.id IS NULL
				ORDER BY location_row.id ASC
				LIMIT %d",
				self::ORPHAN_BATCH_SIZE
			)
		);

		if ( ! is_array( $ids ) ) {
			throw new RuntimeException(
				'cleanup_select_orphan_locations_failed: '
				. Power_Schedule_Manager_Database::last_error()
			);
		}

		return self::delete_by_integer_ids(
			$locations,
			'id',
			$ids,
			'cleanup_delete_orphan_locations_failed'
		);
	}

	/**
	 * Delete reusable-place links whose event or place no longer exists.
	 *
	 * Place records themselves are retained for future schedules.
	 *
	 * @return int Deleted rows.
	 */
	private static function delete_orphan_event_place_links(): int {
		global $wpdb;

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT link_row.event_id, link_row.place_id
				FROM {$links} AS link_row
				LEFT JOIN {$events} AS event_row
					ON event_row.id = link_row.event_id
				LEFT JOIN {$places} AS place_row
					ON place_row.id = link_row.place_id
				WHERE event_row.id IS NULL OR place_row.id IS NULL
				ORDER BY link_row.event_id ASC, link_row.place_id ASC
				LIMIT %d",
				self::ORPHAN_BATCH_SIZE
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			throw new RuntimeException(
				'cleanup_select_orphan_event_places_failed: '
				. Power_Schedule_Manager_Database::last_error()
			);
		}

		return self::delete_event_place_pairs( $links, $rows );
	}

	/**
	 * Delete revision rows whose event no longer exists.
	 *
	 * @return int Deleted rows.
	 */
	private static function delete_orphan_revisions(): int {
		global $wpdb;

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$revisions = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_REVISIONS
		);

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT revision_row.id
				FROM {$revisions} AS revision_row
				LEFT JOIN {$events} AS event_row
					ON event_row.id = revision_row.event_id
				WHERE event_row.id IS NULL
				ORDER BY revision_row.id ASC
				LIMIT %d",
				self::ORPHAN_BATCH_SIZE
			)
		);

		if ( ! is_array( $ids ) ) {
			throw new RuntimeException(
				'cleanup_select_orphan_revisions_failed: '
				. Power_Schedule_Manager_Database::last_error()
			);
		}

		return self::delete_by_integer_ids(
			$revisions,
			'id',
			$ids,
			'cleanup_delete_orphan_revisions_failed'
		);
	}

	/**
	 * Delete old completed or failed import logs.
	 *
	 * @return int Deleted rows.
	 */
	private static function delete_expired_import_logs(): int {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);

		$cutoff = self::utc_cutoff(
			sprintf(
				'-%d months',
				self::retention_setting(
					'import_log_retention_months',
					self::IMPORT_LOG_RETENTION_MONTHS,
					3,
					36
				)
			)
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE status IN ('completed', 'failed')
					AND finished_at_utc IS NOT NULL
					AND finished_at_utc < %s
				ORDER BY finished_at_utc ASC, id ASC
				LIMIT %d",
				$cutoff,
				self::IMPORT_BATCH_SIZE
			)
		);

		return self::query_count(
			$result,
			'cleanup_delete_import_logs_failed'
		);
	}

	/**
	 * Delete old terminal notification rows in a bounded batch.
	 *
	 * @return int Deleted rows.
	 */
	private static function delete_expired_notifications(): int {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::NOTIFICATIONS
			)
		) {
			return 0;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);
		$cutoff = self::utc_cutoff(
			sprintf(
				'-%d days',
				self::NOTIFICATION_RETENTION_DAYS
			)
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE status IN ('sent', 'failed')
					AND updated_at_utc < %s
				ORDER BY updated_at_utc ASC, id ASC
				LIMIT %d",
				$cutoff,
				self::IMPORT_BATCH_SIZE
			)
		);

		return self::query_count(
			$result,
			'cleanup_delete_notifications_failed'
		);
	}

	/**
	 * Clear short-lived rate-limit hashes from donation declarations.
	 *
	 * @return int Updated rows.
	 */
	private static function clear_expired_donation_ip_hashes(): int {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::DONATIONS
			)
		) {
			return 0;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::DONATIONS
		);
		$cutoff = self::utc_cutoff(
			sprintf(
				'-%d days',
				self::DONATION_IP_RETENTION_DAYS
			)
		);
		$now = gmdate( 'Y-m-d H:i:s' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET ip_hash = NULL, updated_at_utc = %s
				WHERE ip_hash IS NOT NULL
					AND submitted_at_utc < %s
				ORDER BY submitted_at_utc ASC, id ASC
				LIMIT %d",
				$now,
				$cutoff,
				self::DONATION_BATCH_SIZE
			)
		);

		return self::query_count(
			$result,
			'cleanup_clear_donation_ip_hashes_failed'
		);
	}

	/**
	 * Delete old unconfirmed donation declarations in a bounded batch.
	 *
	 * Confirmed declarations are retained as an audit record. The retention
	 * setting only applies to pending or rejected submissions.
	 *
	 * @return int Deleted rows.
	 */
	private static function delete_expired_unverified_donations(): int {
		global $wpdb;

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::DONATIONS
			)
		) {
			return 0;
		}

		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$months = min(
			36,
			max(
				3,
				absint(
					is_array( $settings )
						? ( $settings['donate_unverified_retention_months'] ?? 12 )
						: 12
				)
			)
		);
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::DONATIONS
		);
		$cutoff = self::utc_cutoff(
			sprintf( '-%d months', $months )
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE status IN ('pending', 'rejected')
					AND submitted_at_utc < %s
				ORDER BY submitted_at_utc ASC, id ASC
				LIMIT %d",
				$cutoff,
				self::DONATION_BATCH_SIZE
			)
		);

		return self::query_count(
			$result,
			'cleanup_delete_unverified_donations_failed'
		);
	}

	/**
	 * Move schedule posts with no active event to trash.
	 *
	 * Published content is moved to trash rather than permanently deleted so
	 * an administrator can recover it.
	 *
	 * @return int Trashed posts.
	 */
	private static function trash_orphan_schedule_posts(): int {
		global $wpdb;

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT posts.ID
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$events} AS events
					ON events.post_id = posts.ID
					AND events.deleted_at_utc IS NULL
				WHERE posts.post_type = %s
					AND posts.post_status NOT IN ('trash', 'auto-draft')
					AND events.id IS NULL
				ORDER BY posts.ID ASC
				LIMIT %d",
				Power_Schedule_Manager_Post_Type::POST_TYPE,
				self::POST_BATCH_SIZE
			)
		);

		if ( ! is_array( $post_ids ) ) {
			throw new RuntimeException(
				'cleanup_select_orphan_posts_failed: '
				. Power_Schedule_Manager_Database::last_error()
			);
		}

		$trashed = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( $post_id < 1 ) {
				continue;
			}

			$result = wp_trash_post( $post_id );

			if ( $result instanceof WP_Post ) {
				++$trashed;
			}
		}

		return $trashed;
	}

	/**
	 * Delete a bounded set of rows by one unsigned integer primary key.
	 *
	 * Selecting candidates before deleting avoids unsupported combinations of
	 * multi-table DELETE and LIMIT on MySQL while keeping every write small.
	 *
	 * @param string            $table      Trusted table identifier.
	 * @param string            $column     Trusted primary-key identifier.
	 * @param array<int,mixed> $ids        Candidate IDs.
	 * @param string            $error_code Stable internal error code.
	 * @return int Deleted rows.
	 */
	private static function delete_by_integer_ids(
		string $table,
		string $column,
		array $ids,
		string $error_code
	): int {
		global $wpdb;

		self::assert_identifier( $table );
		self::assert_identifier( $column );

		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $ids )
				)
			)
		);

		if ( array() === $ids ) {
			return 0;
		}

		$id_list = implode( ',', $ids );
		$result = $wpdb->query(
			"DELETE FROM {$table}
			WHERE {$column} IN ({$id_list})"
		);

		return self::query_count( $result, $error_code );
	}

	/**
	 * Delete a bounded set of orphan event/place pairs.
	 *
	 * @param string                         $table Trusted table identifier.
	 * @param array<int,array<string,mixed>> $rows Candidate key pairs.
	 * @return int Deleted rows.
	 */
	private static function delete_event_place_pairs(
		string $table,
		array $rows
	): int {
		global $wpdb;

		self::assert_identifier( $table );

		$pairs = array();

		foreach ( $rows as $row ) {
			$event_id = absint( $row['event_id'] ?? 0 );
			$place_id = absint( $row['place_id'] ?? 0 );

			if ( $event_id < 1 || $place_id < 1 ) {
				continue;
			}

			$pairs[ $event_id . ':' . $place_id ] = sprintf(
				'(event_id = %d AND place_id = %d)',
				$event_id,
				$place_id
			);
		}

		if ( array() === $pairs ) {
			return 0;
		}

		$result = $wpdb->query(
			"DELETE FROM {$table}
			WHERE " . implode( ' OR ', $pairs )
		);

		return self::query_count(
			$result,
			'cleanup_delete_orphan_event_places_failed'
		);
	}

	/**
	 * Convert a wpdb write result to a count or raise a visible cron failure.
	 *
	 * @param int|bool $result     wpdb result.
	 * @param string   $error_code Stable internal error code.
	 * @return int Affected rows.
	 */
	private static function query_count(
		int|bool $result,
		string $error_code
	): int {
		if ( false === $result ) {
			throw new RuntimeException(
				$error_code
				. ': '
				. Power_Schedule_Manager_Database::last_error()
			);
		}

		return (int) $result;
	}

	/**
	 * Reject an unexpected SQL identifier before interpolation.
	 *
	 * @param string $identifier Table or column identifier.
	 */
	private static function assert_identifier( string $identifier ): void {
		if ( 1 !== preg_match( '/\\A[A-Za-z0-9_]+\\z/', $identifier ) ) {
			throw new InvalidArgumentException(
				'cleanup_invalid_sql_identifier'
			);
		}
	}

	/**
	 * Read one bounded retention setting.
	 *
	 * @param string $key     Settings key.
	 * @param int    $default Default value.
	 * @param int    $minimum Minimum accepted value.
	 * @param int    $maximum Maximum accepted value.
	 * @return int
	 */
	private static function retention_setting(
		string $key,
		int $default,
		int $minimum,
		int $maximum
	): int {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$value = is_array( $settings )
			? absint( $settings[ $key ] ?? $default )
			: $default;

		return min( $maximum, max( $minimum, $value ) );
	}

	/**
	 * Calculate UTC cutoff.
	 *
	 * @param string $modifier DateTime modifier.
	 *
	 * @return string MySQL UTC datetime.
	 */
	private static function utc_cutoff(
		string $modifier
	): string {
		$now = new DateTimeImmutable(
			'now',
			new DateTimeZone( 'UTC' )
		);

		$cutoff = $now->modify( $modifier );

		if ( false === $cutoff ) {
			throw new RuntimeException(
				'cleanup_invalid_retention_modifier'
			);
		}

		return $cutoff->format( 'Y-m-d H:i:s' );
	}
}
