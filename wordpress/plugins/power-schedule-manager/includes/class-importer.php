<?php
/**
 * Secure power schedule importer.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Imports validated schedule events.
 */
final class Power_Schedule_Manager_Importer {

	/**
	 * Completed import status.
	 */
	private const string RUN_COMPLETED = 'completed';

	/**
	 * Running import status.
	 */
	private const string RUN_RUNNING = 'running';

	/**
	 * Failed import status.
	 */
	private const string RUN_FAILED = 'failed';

	/**
	 * MySQL named-lock timeout.
	 */
	private const int LOCK_TIMEOUT = 5;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Import a previously previewed payload.
	 *
	 * Possible duplicate resolutions use the event identity hash as key:
	 *
	 * identity_hash => create
	 * identity_hash => skip
	 *
	 * @param string               $raw_payload          Raw payload.
	 * @param string               $unit_code            Unit code.
	 * @param string               $preview_token        Signed preview token.
	 * @param string               $source               Import source.
	 * @param string               $source_url           Optional source URL.
	 * @param array<string,string> $duplicate_resolutions Duplicate decisions.
	 *
	 * @return array{
	 *     run_id: int,
	 *     run_uuid: string,
	 *     inserted: int,
	 *     updated: int,
	 *     unchanged: int,
	 *     duplicates_skipped: int,
	 *     places_discovered: int,
	 *     posts: array<int, int>
	 * }
	 *
	 * @throws RuntimeException When import fails.
	 */
	public static function import(
		string $raw_payload,
		string $unit_code,
		string $preview_token,
		string $source = 'evn',
		string $source_url = '',
		array $duplicate_resolutions = array()
	): array {
		self::assert_permission();

		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$unit_code
		);

		if ( '' === $unit_code ) {
			throw new RuntimeException(
				'import_unit_required'
			);
		}

		$parsed = Power_Schedule_Manager_Parser::parse(
			$raw_payload,
			$unit_code,
			$source,
			true
		);

		$unit = Power_Schedule_Manager_Units::find_by_code(
			$unit_code
		);

		if ( null === $unit ) {
			throw new RuntimeException(
				'import_unit_not_found'
			);
		}

		if (
			! isset( $parsed['events'] )
			|| ! is_array( $parsed['events'] )
			|| array() === $parsed['events']
		) {
			throw new RuntimeException(
				'import_contains_no_valid_events'
			);
		}

		if (
			isset( $parsed['errors'] )
			&& is_array( $parsed['errors'] )
			&& array() !== $parsed['errors']
		) {
			throw new RuntimeException(
				'import_contains_parser_errors'
			);
		}

		if (
			! Power_Schedule_Manager_Preview::verify_token(
				$preview_token,
				$raw_payload,
				$unit_code,
				$parsed['events']
			)
		) {
			throw new RuntimeException(
				'import_preview_token_invalid_or_expired'
			);
		}

		/*
		 * Preview is rebuilt from trusted server-side data. Browser-submitted
		 * preview rows are never trusted.
		 */
		$current_preview = Power_Schedule_Manager_Preview::create(
			$parsed,
			$raw_payload
		);

		if (
			Power_Schedule_Manager_Preview::has_blocking_errors(
				$current_preview
			)
		) {
			throw new RuntimeException(
				'import_preview_has_blocking_errors'
			);
		}

		$duplicate_resolutions = self::sanitize_duplicate_resolutions(
			$duplicate_resolutions
		);

		$events = self::resolve_possible_duplicates(
			$current_preview,
			$duplicate_resolutions
		);

		$lock_name = self::lock_name(
			$unit_code,
			$source
		);

		if ( ! Power_Schedule_Manager_Database::acquire_write_lock( 5 ) ) {
			throw new RuntimeException(
				'import_plugin_write_locked'
			);
		}

		if ( ! self::acquire_lock( $lock_name ) ) {
			Power_Schedule_Manager_Database::release_write_lock();

			throw new RuntimeException(
				'import_already_running_for_unit'
			);
		}

		$run_id   = 0;
		$run_uuid = wp_generate_uuid4();
		$started  = microtime( true );

		try {
			$run_id = self::create_import_run(
				$run_uuid,
				$unit,
				$source,
				$source_url,
				$raw_payload,
				$preview_token,
				count( $parsed['events'] ),
				isset( $parsed['warnings'] )
					&& is_array( $parsed['warnings'] )
					? count( $parsed['warnings'] )
					: 0
			);

			$result = self::persist_events(
				$events,
				$unit,
				$run_id
			);

			self::complete_import_run(
				$run_id,
				$result,
				microtime( true ) - $started
			);

			/**
			 * Fires after a successful schedule import.
			 *
			 * Cache and page-cache integrations should listen to this hook and
			 * only invalidate affected unit/date keys.
			 *
			 * @param int                  $run_id Import run ID.
			 * @param array<string, mixed> $result Import result.
			 */
			do_action(
				'power_schedule_manager_import_completed',
				$run_id,
				$result
			);

			return array(
				'run_id'             => $run_id,
				'run_uuid'           => $run_uuid,
				'inserted'           => $result['inserted'],
					'updated'            => $result['updated'],
					'unchanged'          => $result['unchanged'],
					'duplicates_skipped' => $result['duplicates_skipped'],
					'places_discovered'  => $result['places_discovered'],
					'posts'              => $result['posts'],
			);
		} catch ( Throwable $throwable ) {
			if ( $run_id > 0 ) {
				self::fail_import_run(
					$run_id,
					$throwable,
					microtime( true ) - $started
				);
			}

			throw new RuntimeException(
				'import_failed',
				0,
				$throwable
			);
		} finally {
			self::release_lock( $lock_name );
			Power_Schedule_Manager_Database::release_write_lock();
		}
	}

	/**
	 * Persist events within one transaction.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 * @param array<string, mixed>             $unit   Unit.
	 * @param int                              $run_id Import run ID.
	 *
	 * @return array{
	 *     inserted: int,
	 *     updated: int,
	 *     unchanged: int,
	 *     duplicates_skipped: int,
	 *     posts: array<int, int>
	 * }
	 *
	 * @throws RuntimeException When persistence fails.
	 */
	private static function persist_events(
		array $events,
		array $unit,
		int $run_id
	): array {
		global $wpdb;

		$created_posts    = array();
		$post_ids         = array();
		$inserted         = 0;
		$updated          = 0;
		$unchanged        = 0;
		$skipped          = 0;
		$places_discovered = 0;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			throw new RuntimeException(
				'import_transaction_start_failed'
			);
		}

		try {
			foreach ( $events as $entry ) {
				if (
					isset( $entry['_skip_duplicate'] )
					&& true === $entry['_skip_duplicate']
				) {
					/*
					 * A duplicate may belong to a draft created by an older
					 * plugin version. Reusing the schedule post promotes that
					 * validated import to publish without inserting an event.
					 */
					$post_result = self::find_or_create_schedule_post(
						$unit,
						(string) $entry['local_date']
					);
					$post_id = $post_result['post_id'];
					$post_ids[ $entry['local_date'] ] = $post_id;

					if ( $post_result['created'] ) {
						$created_posts[] = $post_id;
					}

					$places_discovered +=
						self::synchronize_place_library(
							0,
							$unit,
							(string) ( $entry['area'] ?? '' )
						);

					++$skipped;
					continue;
				}

				unset( $entry['_skip_duplicate'] );

				$post_result = self::find_or_create_schedule_post(
					$unit,
					(string) $entry['local_date']
				);

				$post_id = $post_result['post_id'];

				if ( $post_result['created'] ) {
					$created_posts[] = $post_id;
				}

				$post_ids[ $entry['local_date'] ] = $post_id;

				$inspection =
					Power_Schedule_Manager_Repository::inspect(
						$entry
					);

				if (
					Power_Schedule_Manager_Repository::ACTION_NEW
					=== $inspection['action']
				) {
					$event_id =
						Power_Schedule_Manager_Repository::insert(
							$inspection['event'],
							$run_id,
							$post_id
						);

					self::create_revision(
						$event_id,
						$run_id,
						'created',
						$inspection['content_hash'],
						null,
						$inspection['event']
					);

					$places_discovered +=
						self::synchronize_place_library(
							$event_id,
							$unit,
							(string) $inspection['event']['area']
						);

					++$inserted;
					continue;
				}

				$existing = $inspection['existing'];

				if ( ! is_array( $existing ) ) {
					throw new RuntimeException(
						'import_existing_event_missing'
					);
				}

				$event_id = (int) $existing['id'];

				if (
					Power_Schedule_Manager_Repository::ACTION_UPDATE
					=== $inspection['action']
				) {
					Power_Schedule_Manager_Repository::update(
						$event_id,
						$inspection['event'],
						$run_id,
						$post_id
					);

					self::create_revision(
						$event_id,
						$run_id,
						'updated',
						$inspection['content_hash'],
						$existing,
						$inspection['event']
					);

					$places_discovered +=
						self::synchronize_place_library(
							$event_id,
							$unit,
							(string) $inspection['event']['area']
						);

					++$updated;
					continue;
				}

				Power_Schedule_Manager_Repository::touch(
					$event_id,
					$run_id
				);

				if ( (int) $existing['post_id'] !== $post_id ) {
					Power_Schedule_Manager_Repository::set_post_id(
						$event_id,
						$post_id
					);
				}

				$places_discovered +=
					self::synchronize_place_library(
						$event_id,
						$unit,
						(string) $inspection['event']['area']
					);

				++$unchanged;
			}

			foreach ( $post_ids as $post_id ) {
				self::refresh_post_metadata( $post_id );
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException(
					'import_transaction_commit_failed'
				);
			}
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );

			/*
			 * wp_insert_post() uses the same database connection and normally
			 * rolls back with the transaction. This cleanup is retained as a
			 * controlled compensation for unusual database configurations.
			 */
			foreach ( $created_posts as $post_id ) {
				if ( get_post( $post_id ) instanceof WP_Post ) {
					wp_delete_post( $post_id, true );
				}
			}

			throw $throwable;
		}

		return array(
			'inserted'           => $inserted,
			'updated'            => $updated,
			'unchanged'          => $unchanged,
			'duplicates_skipped' => $skipped,
			'places_discovered'  => $places_discovered,
			'posts'              => array_values(
				array_unique( $post_ids )
			),
		);
	}

	/**
	 * Discover explicit road candidates and attach reviewed library places.
	 *
	 * @param int                 $event_id Event ID.
	 * @param array<string,mixed> $unit Unit data.
	 * @param string              $area Event area.
	 * @return int Number of newly discovered pending places.
	 */
	private static function synchronize_place_library(
		int $event_id,
		array $unit,
		string $area
	): int {
		try {
			$created =
				Power_Schedule_Manager_Place_Library::discover_from_area(
					(string) $unit['code'],
					$area
				);

			if ( $event_id > 0 ) {
				Power_Schedule_Manager_Place_Library::attach_matches(
					$event_id,
					(string) $unit['code'],
					$area
				);
			}

			return $created;
		} catch ( Throwable $throwable ) {
			/*
			 * Place discovery enriches an otherwise valid outage event. A
			 * missing/outdated map table must not roll back the schedule
			 * import; administrators can review and relink the place later.
			 */
			Power_Schedule_Manager_Logger::error(
				'import_place_sync_failed',
				$throwable,
				array(
					'event_id'  => $event_id,
					'unit_code' => (string) ( $unit['code'] ?? '' ),
				)
			);

			return 0;
		}
	}

	/**
	 * Find or create one CPT for a unit and local date.
	 *
	 * @param array<string, mixed> $unit       Unit.
	 * @param string               $local_date Local Y-m-d.
	 *
	 * @return array{post_id: int, created: bool}
	 */
	private static function find_or_create_schedule_post(
		array $unit,
		string $local_date
	): array {
		Power_Schedule_Manager_Validator::validate_local_date(
			$local_date
		);

		$query = new WP_Query(
			array(
				'post_type'              =>
					Power_Schedule_Manager_Post_Type::POST_TYPE,
				'post_status'            => array(
					'publish',
					'draft',
					'pending',
					'private',
					'future',
				),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'     =>
							Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
						'value'   => (string) $unit['code'],
						'compare' => '=',
					),
					array(
						'key'     =>
							Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
						'value'   => $local_date,
						'compare' => '=',
					),
				),
			)
		);

		if (
			isset( $query->posts[0] )
			&& absint( $query->posts[0] ) > 0
		) {
			$post_id = absint( $query->posts[0] );

			if ( 'publish' === self::import_post_status() ) {
				self::publish_imported_schedule_post(
					$post_id,
					$unit,
					$local_date
				);
			}

			return array(
				'post_id' => $post_id,
				'created' => false,
			);
		}

		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$local_date,
			new DateTimeZone(
				(string) $unit['timezone']
			)
		);

		if ( false === $date ) {
			throw new RuntimeException(
				'import_post_date_invalid'
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_type' =>
					Power_Schedule_Manager_Post_Type::POST_TYPE,
				'post_status' => self::import_post_status(),
				'post_title' =>
					Power_Schedule_Manager_Post_Type::build_schedule_title(
						(string) $unit['name'],
						$date
					),
				'post_name' =>
					Power_Schedule_Manager_Post_Type::build_schedule_slug(
						(string) $unit['slug'],
						$date
					),
				'post_excerpt' => sprintf(
					/* translators: 1: Locality name, 2: Date. */
					__(
						'Tra cứu lịch cúp điện %1$s ngày %2$s: khu vực, thời gian và lý do ngừng cung cấp điện.',
						'power-schedule-manager'
					),
					Power_Schedule_Manager_Post_Type::location_name(
						(string) $unit['name']
					),
					$date->format(
						Power_Schedule_Manager_Post_Type::DISPLAY_DATE_FORMAT
					)
				),
				'post_content' => '',
				'meta_input'   => array(
					Power_Schedule_Manager_Post_Type::META_UNIT_CODE =>
						(string) $unit['code'],
					Power_Schedule_Manager_Post_Type::META_LOCAL_DATE =>
						$local_date,
					Power_Schedule_Manager_Post_Type::META_EVENT_COUNT =>
						0,
					Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC =>
						Power_Schedule_Manager_Database::utc_now(),
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || $post_id < 1 ) {
			throw new RuntimeException(
				'import_schedule_post_creation_failed'
			);
		}

		$term_id = Power_Schedule_Manager_Units::find_term_id_by_unit_code(
			(string) $unit['code']
		);

		if ( null === $term_id ) {
			throw new RuntimeException(
				'import_unit_term_missing'
			);
		}

		$term_result = wp_set_object_terms(
			$post_id,
			array( $term_id ),
			Power_Schedule_Manager_Taxonomy::TAXONOMY,
			false
		);

		if ( is_wp_error( $term_result ) ) {
			throw new RuntimeException(
				'import_schedule_term_assignment_failed'
			);
		}

		return array(
			'post_id' => $post_id,
			'created' => true,
		);
	}

	/**
	 * Return the configured status for posts created by source imports.
	 *
	 * @return string
	 */
	private static function import_post_status(): string {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);

		return is_array( $settings )
			&& 'draft' === ( $settings['import_post_status'] ?? '' )
				? 'draft'
				: 'publish';
	}

	/**
	 * Publish an existing schedule reused by a confirmed source import.
	 *
	 * Import validation and preview have already completed before this method
	 * runs. Existing draft/pending/private schedules are therefore promoted
	 * without requiring one-by-one editorial work.
	 *
	 * @param int                 $post_id Existing schedule post.
	 * @param array<string,mixed> $unit Unit data.
	 * @param string              $local_date Local Y-m-d date.
	 * @return void
	 */
	private static function publish_imported_schedule_post(
		int $post_id,
		array $unit,
		string $local_date
	): void {
		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$local_date,
			new DateTimeZone( (string) $unit['timezone'] )
		);

		if ( false === $date ) {
			throw new RuntimeException( 'import_post_date_invalid' );
		}

		$current_title = get_the_title( $post_id );
		$updates = array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		);

		/*
		 * Correct titles generated by older plugin versions while preserving
		 * genuinely custom editorial titles.
		 */
		if (
			'' === trim( $current_title )
			|| str_starts_with(
				$current_title,
				'Lịch cúp điện Điện lực '
			)
		) {
			$updates['post_title'] =
				Power_Schedule_Manager_Post_Type::build_schedule_title(
					(string) $unit['name'],
					$date
				);
		}

		$result = wp_update_post( $updates, true );

		if ( is_wp_error( $result ) || $result < 1 ) {
			throw new RuntimeException(
				'import_schedule_post_publish_failed'
			);
		}
	}

	/**
	 * Refresh event count and update time for a schedule post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private static function refresh_post_metadata(
		int $post_id
	): void {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table}
				WHERE post_id = %d
					AND deleted_at_utc IS NULL
					AND status <> %s",
				$post_id,
				Power_Schedule_Manager_Status::REMOVED
			)
		);

		update_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_EVENT_COUNT,
			max( 0, (int) $count )
		);

		update_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC,
			Power_Schedule_Manager_Database::utc_now()
		);
	}

	/**
	 * Create immutable event revision.
	 *
	 * @param int                       $event_id    Event ID.
	 * @param int                       $run_id      Import run ID.
	 * @param string                    $change_type Change type.
	 * @param string                    $content_hash Content hash.
	 * @param array<string,mixed>|null  $before      Previous data.
	 * @param array<string,mixed>       $after       New data.
	 *
	 * @return void
	 */
	private static function create_revision(
		int $event_id,
		int $run_id,
		string $change_type,
		string $content_hash,
		?array $before,
		array $after
	): void {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_REVISIONS
		);

		$revision_number = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(MAX(revision_number), 0) + 1
				FROM {$table}
				WHERE event_id = %d",
				$event_id
			)
		);

		$before_json = null === $before
			? null
			: self::encode_revision_data( $before );

		$after_json = self::encode_revision_data( $after );

		$sql = $wpdb->prepare(
			"INSERT INTO {$table}
				(
					event_id,
					import_run_id,
					user_id,
					revision_number,
					change_type,
					content_hash,
					before_data,
					after_data,
					created_at_utc
				)
			VALUES
				(
					%d,
					%d,
					%d,
					%d,
					%s,
					UNHEX(%s),
					%s,
					%s,
					%s
				)",
			$event_id,
			$run_id,
			get_current_user_id(),
			max( 1, $revision_number ),
			sanitize_key( $change_type ),
			$content_hash,
			$before_json,
			$after_json,
			Power_Schedule_Manager_Database::utc_now()
		);

		if ( false === $wpdb->query( $sql ) ) {
			throw new RuntimeException(
				'import_revision_creation_failed'
			);
		}
	}

	/**
	 * Create import-run log.
	 *
	 * @param string               $run_uuid     Run UUID.
	 * @param array<string, mixed> $unit         Unit.
	 * @param string               $source       Source.
	 * @param string               $source_url   Source URL.
	 * @param string               $raw_payload  Raw payload.
	 * @param string               $preview_token Preview token.
	 * @param int                  $found_count  Found count.
	 * @param int                  $warning_count Warning count.
	 *
	 * @return int
	 */
	private static function create_import_run(
		string $run_uuid,
		array $unit,
		string $source,
		string $source_url,
		string $raw_payload,
		string $preview_token,
		int $found_count,
		int $warning_count
	): int {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$now = Power_Schedule_Manager_Database::utc_now();

		$source = sanitize_key( $source );
		$source_url = esc_url_raw(
			$source_url,
			array( 'http', 'https' )
		);

		$sql = $wpdb->prepare(
			"INSERT INTO {$table}
				(
					run_uuid,
					user_id,
					unit_id,
					unit_code,
					source,
					source_url,
					status,
					payload_hash,
					preview_token_hash,
					raw_payload,
					payload_bytes,
					found_count,
					warning_count,
					started_at_utc,
					created_at_utc,
					updated_at_utc
				)
			VALUES
				(
					%s,
					%d,
					%d,
					%s,
					%s,
					%s,
					%s,
					UNHEX(%s),
					UNHEX(%s),
					%s,
					%d,
					%d,
					%d,
					%s,
					%s,
					%s
				)",
			$run_uuid,
			get_current_user_id(),
			(int) $unit['id'],
			(string) $unit['code'],
			$source,
			$source_url,
			self::RUN_RUNNING,
			hash( 'sha256', $raw_payload ),
			hash( 'sha256', $preview_token ),
			$raw_payload,
			strlen( $raw_payload ),
			max( 0, $found_count ),
			max( 0, $warning_count ),
			$now,
			$now,
			$now
		);

		if ( false === $wpdb->query( $sql ) ) {
			throw new RuntimeException(
				'import_run_creation_failed'
			);
		}

		$run_id = Power_Schedule_Manager_Database::insert_id();

		if ( $run_id < 1 ) {
			throw new RuntimeException(
				'import_run_id_missing'
			);
		}

		return $run_id;
	}

	/**
	 * Mark import run completed.
	 *
	 * @param int                  $run_id   Run ID.
	 * @param array<string, mixed> $result   Result.
	 * @param float                $duration Duration seconds.
	 *
	 * @return void
	 */
	private static function complete_import_run(
		int $run_id,
		array $result,
		float $duration
	): void {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$now = Power_Schedule_Manager_Database::utc_now();

		/*
		 * Duration is currently emitted through the action hook rather than
		 * stored because schema 1.0.0 has no duration column.
		 */
		unset( $duration );

		$result_value = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					status = %s,
					inserted_count = %d,
					updated_count = %d,
					unchanged_count = %d,
					duplicate_count = %d,
					finished_at_utc = %s,
					updated_at_utc = %s
				WHERE id = %d",
				self::RUN_COMPLETED,
				(int) $result['inserted'],
				(int) $result['updated'],
				(int) $result['unchanged'],
				(int) $result['duplicates_skipped'],
				$now,
				$now,
				$run_id
			)
		);

		if ( false === $result_value ) {
			throw new RuntimeException(
				'import_run_completion_failed'
			);
		}
	}

	/**
	 * Mark import run failed.
	 *
	 * @param int       $run_id    Run ID.
	 * @param Throwable $throwable Error.
	 * @param float     $duration  Duration.
	 *
	 * @return void
	 */
	private static function fail_import_run(
		int $run_id,
		Throwable $throwable,
		float $duration
	): void {
		global $wpdb;

		unset( $duration );

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);
		$now = Power_Schedule_Manager_Database::utc_now();

		$error_code = sanitize_key(
			$throwable->getMessage()
		);

		if ( '' === $error_code ) {
			$error_code = 'import_failed';
		}

		$error_message = substr(
			wp_strip_all_tags( $throwable->getMessage() ),
			0,
			1000
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					status = %s,
					error_count = 1,
					error_code = %s,
					error_message = %s,
					finished_at_utc = %s,
					updated_at_utc = %s
				WHERE id = %d",
				self::RUN_FAILED,
				substr( $error_code, 0, 64 ),
				$error_message,
				$now,
				$now,
				$run_id
			)
		);
	}

	/**
	 * Resolve possible duplicates using explicit administrator decisions.
	 *
	 * @param array<string, mixed> $preview     Server-side preview.
	 * @param array<string,string> $resolutions Resolutions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function resolve_possible_duplicates(
		array $preview,
		array $resolutions
	): array {
		$events = array();

		foreach ( $preview['rows'] as $row ) {
			if (
				! is_array( $row )
				|| ! isset( $row['event'] )
				|| ! is_array( $row['event'] )
			) {
				continue;
			}

			$event = $row['event'];

			if (
				Power_Schedule_Manager_Preview::ACTION_POSSIBLE_DUPLICATE
				=== $row['action']
			) {
				$identity_hash = (string) $row['identity_hash'];
				$resolution = $resolutions[ $identity_hash ] ?? '';

				if ( 'skip' === $resolution ) {
					$event['_skip_duplicate'] = true;
				} elseif ( 'create' !== $resolution ) {
					throw new RuntimeException(
						'import_duplicate_resolution_required'
					);
				}
			}

			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Sanitize duplicate decisions.
	 *
	 * @param array<string,string> $resolutions Raw decisions.
	 *
	 * @return array<string,string>
	 */
	private static function sanitize_duplicate_resolutions(
		array $resolutions
	): array {
		$sanitized = array();

		foreach ( $resolutions as $hash => $decision ) {
			if (
				! is_string( $hash )
				|| ! is_string( $decision )
				|| 1 !== preg_match(
					'/\A[a-f0-9]{64}\z/',
					strtolower( $hash )
				)
			) {
				continue;
			}

			$decision = sanitize_key( $decision );

			if ( in_array( $decision, array( 'create', 'skip' ), true ) ) {
				$sanitized[ strtolower( $hash ) ] = $decision;
			}
		}

		return $sanitized;
	}

	/**
	 * Encode revision data.
	 *
	 * @param array<string, mixed> $data Revision data.
	 *
	 * @return string
	 */
	private static function encode_revision_data(
		array $data
	): string {
		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $json ) ) {
			throw new RuntimeException(
				'import_revision_encoding_failed'
			);
		}

		return $json;
	}

	/**
	 * Assert import permission.
	 *
	 * @return void
	 */
	private static function assert_permission(): void {
		if (
			! is_user_logged_in()
			|| ! Power_Schedule_Manager_Capabilities::current_user_can_import()
		) {
			throw new RuntimeException(
				'import_permission_denied'
			);
		}
	}

	/**
	 * Build database lock name.
	 *
	 * @param string $unit_code Unit code.
	 * @param string $source    Source.
	 *
	 * @return string
	 */
	private static function lock_name(
		string $unit_code,
		string $source
	): string {
		return 'psm_import_'
			. substr(
				hash( 'sha256', $unit_code . '|' . $source ),
				0,
				40
			);
	}

	/**
	 * Acquire MySQL or MariaDB named lock.
	 *
	 * @param string $lock_name Lock name.
	 *
	 * @return bool
	 */
	private static function acquire_lock(
		string $lock_name
	): bool {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, %d)',
				$lock_name,
				self::LOCK_TIMEOUT
			)
		);

		return '1' === (string) $result;
	}

	/**
	 * Release database lock.
	 *
	 * @param string $lock_name Lock name.
	 *
	 * @return void
	 */
	private static function release_lock(
		string $lock_name
	): void {
		global $wpdb;

		$wpdb->get_var(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s)',
				$lock_name
			)
		);
	}
}
