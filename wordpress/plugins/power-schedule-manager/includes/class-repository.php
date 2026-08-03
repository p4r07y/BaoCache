<?php
/**
 * Power schedule event repository.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides controlled database access for schedule events.
 *
 * This class does not manage WordPress posts or start transactions. Importer
 * and importer services coordinate those higher-level operations.
 */
final class Power_Schedule_Manager_Repository {

	/**
	 * Insert classification.
	 */
	public const string ACTION_NEW = 'new';

	/**
	 * Update classification.
	 */
	public const string ACTION_UPDATE = 'update';

	/**
	 * Unchanged classification.
	 */
	public const string ACTION_UNCHANGED = 'unchanged';

	/**
	 * Maximum rows returned by one public query.
	 */
	private const int MAX_QUERY_LIMIT = 500;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Inspect an event without writing to the database.
	 *
	 * @param array<string, mixed> $event       Event data.
	 * @param bool                 $verify_unit Verify unit existence.
	 *
	 * @return array{
	 *     action: string,
	 *     event: array<string, mixed>,
	 *     existing: array<string, mixed>|null,
	 *     identity_hash: string,
	 *     content_hash: string
	 * }
	 */
	public static function inspect(
		array $event,
		bool $verify_unit = true
	): array {
		$event = Power_Schedule_Manager_Validator::validate_event(
			$event,
			$verify_unit
		);

		$identity_hash = self::identity_hash( $event );
		$content_hash  = self::content_hash( $event );
		$existing      = self::find_by_identity_hash(
			$identity_hash
		);

		if ( null === $existing ) {
			$action = self::ACTION_NEW;
		} elseif (
			hash_equals(
				(string) $existing['content_hash'],
				$content_hash
			)
		) {
			$action = self::ACTION_UNCHANGED;
		} else {
			$action = self::ACTION_UPDATE;
		}

		return array(
			'action'        => $action,
			'event'         => $event,
			'existing'      => $existing,
			'identity_hash' => $identity_hash,
			'content_hash'  => $content_hash,
		);
	}

	/**
	 * Build canonical identity hash.
	 *
	 * When source_event_id exists, it defines identity so a source-side time
	 * change updates the same event.
	 *
	 * Without source_event_id, identity is based on source, unit, start, end,
	 * and area. A time change then becomes a new candidate and may be flagged
	 * as a possible duplicate by the preview service.
	 *
	 * @param array<string, mixed> $event Validated event.
	 *
	 * @return string Lowercase SHA-256 hexadecimal hash.
	 */
	public static function identity_hash( array $event ): string {
		$source_event_id = trim(
			(string) ( $event['source_event_id'] ?? '' )
		);

		if ( '' !== $source_event_id ) {
			$identity = array(
				'version'         => 1,
				'source'          => (string) $event['source'],
				'unit_code'       => (string) $event['unit_code'],
				'source_event_id' => $source_event_id,
			);
		} else {
			$identity = array(
				'version'      => 1,
				'source'       => (string) $event['source'],
				'unit_code'    => (string) $event['unit_code'],
				'start_at_utc' => (string) $event['start_at_utc'],
				'end_at_utc'   => (string) $event['end_at_utc'],
				'area'         => self::canonical_text(
					(string) $event['area']
				),
			);
		}

		return self::hash_array( $identity );
	}

	/**
	 * Build canonical content hash.
	 *
	 * @param array<string, mixed> $event Validated event.
	 *
	 * @return string Lowercase SHA-256 hexadecimal hash.
	 */
	public static function content_hash( array $event ): string {
		return self::hash_array(
			array(
				'version'         => 1,
				'unit_code'       => (string) $event['unit_code'],
				'source'          => (string) $event['source'],
				'source_event_id' => (string) $event['source_event_id'],
				'local_date'      => (string) $event['local_date'],
				'start_at_utc'    => (string) $event['start_at_utc'],
				'end_at_utc'      => (string) $event['end_at_utc'],
				'area'            => self::canonical_text(
					(string) $event['area']
				),
				'reason'          => self::canonical_text(
					(string) $event['reason']
				),
				'status'          => (string) $event['status'],
			)
		);
	}

	/**
	 * Find an event using its identity hash.
	 *
	 * @param string $identity_hash SHA-256 hexadecimal hash.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_by_identity_hash(
		string $identity_hash
	): ?array {
		global $wpdb;

		$identity_hash = self::validate_hex_hash(
			$identity_hash
		);

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					id,
					unit_id,
					unit_code,
					source,
					source_event_id,
					LOWER(HEX(identity_hash)) AS identity_hash,
					LOWER(HEX(content_hash)) AS content_hash,
					local_date,
					start_at_utc,
					end_at_utc,
					area,
					reason,
					status,
					import_run_id,
					post_id,
					missing_count,
					sync_count,
					first_seen_at_utc,
					last_seen_at_utc,
					created_at_utc,
					updated_at_utc,
					deleted_at_utc
				FROM {$table}
				WHERE identity_hash = UNHEX(%s)
				LIMIT 1",
				$identity_hash
			),
			ARRAY_A
		);

		return is_array( $row )
			? self::cast_row( $row )
			: null;
	}

	/**
	 * Find an event by database ID.
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_by_id( int $event_id ): ?array {
		global $wpdb;

		$event_id = absint( $event_id );

		if ( $event_id < 1 ) {
			return null;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					id,
					unit_id,
					unit_code,
					source,
					source_event_id,
					LOWER(HEX(identity_hash)) AS identity_hash,
					LOWER(HEX(content_hash)) AS content_hash,
					local_date,
					start_at_utc,
					end_at_utc,
					area,
					reason,
					status,
					import_run_id,
					post_id,
					missing_count,
					sync_count,
					first_seen_at_utc,
					last_seen_at_utc,
					created_at_utc,
					updated_at_utc,
					deleted_at_utc
				FROM {$table}
				WHERE id = %d
				LIMIT 1",
				$event_id
			),
			ARRAY_A
		);

		return is_array( $row )
			? self::cast_row( $row )
			: null;
	}

	/**
	 * Insert a validated event.
	 *
	 * @param array<string, mixed> $event         Event.
	 * @param int                  $import_run_id Import run ID.
	 * @param int                  $post_id       WordPress post ID.
	 *
	 * @return int Event ID.
	 *
	 * @throws RuntimeException When insert fails.
	 */
	public static function insert(
		array $event,
		int $import_run_id = 0,
		int $post_id = 0
	): int {
		global $wpdb;

		$inspection = self::inspect( $event );

		if ( self::ACTION_NEW !== $inspection['action'] ) {
			throw new RuntimeException(
				'event_identity_already_exists'
			);
		}

		$event          = $inspection['event'];
		$identity_hash  = $inspection['identity_hash'];
		$content_hash   = $inspection['content_hash'];
		$import_run_id  = absint( $import_run_id );
		$post_id        = absint( $post_id );
		$now            = Power_Schedule_Manager_Database::utc_now();
		$table          = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$sql = $wpdb->prepare(
			"INSERT INTO {$table}
				(
					unit_id,
					unit_code,
					source,
					source_event_id,
					identity_hash,
					content_hash,
					local_date,
					start_at_utc,
					end_at_utc,
					area,
					reason,
					status,
					import_run_id,
					post_id,
					missing_count,
					sync_count,
					first_seen_at_utc,
					last_seen_at_utc,
					created_at_utc,
					updated_at_utc,
					deleted_at_utc
				)
			VALUES
				(
					%d,
					%s,
					%s,
					%s,
					UNHEX(%s),
					UNHEX(%s),
					%s,
					%s,
					%s,
					%s,
					%s,
					%s,
					%d,
					%d,
					0,
					1,
					%s,
					%s,
					%s,
					%s,
					NULL
				)",
			$event['unit_id'],
			$event['unit_code'],
			$event['source'],
			$event['source_event_id'],
			$identity_hash,
			$content_hash,
			$event['local_date'],
			$event['start_at_utc'],
			$event['end_at_utc'],
			$event['area'],
			$event['reason'],
			$event['status'],
			$import_run_id,
			$post_id,
			$now,
			$now,
			$now,
			$now
		);

		if ( false === $wpdb->query( $sql ) ) {
			throw new RuntimeException(
				'event_insert_failed'
			);
		}

		$event_id = Power_Schedule_Manager_Database::insert_id();

		if ( $event_id < 1 ) {
			throw new RuntimeException(
				'event_insert_id_missing'
			);
		}

		return $event_id;
	}

	/**
	 * Update an existing event and mark it as seen.
	 *
	 * Identity hash is not changed. This is important for events whose stable
	 * source_event_id remains the same while time or content changes.
	 *
	 * @param int                  $event_id      Event ID.
	 * @param array<string, mixed> $event         Event.
	 * @param int                  $import_run_id Import run ID.
	 * @param int                  $post_id       WordPress post ID.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When update fails.
	 */
	public static function update(
		int $event_id,
		array $event,
		int $import_run_id = 0,
		int $post_id = 0
	): void {
		global $wpdb;

		$event_id = absint( $event_id );

		if ( $event_id < 1 || null === self::find_by_id( $event_id ) ) {
			throw new RuntimeException(
				'event_not_found'
			);
		}

		$event        = Power_Schedule_Manager_Validator::validate_event(
			$event
		);
		$content_hash = self::content_hash( $event );
		$table        = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$now          = Power_Schedule_Manager_Database::utc_now();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					unit_id = %d,
					unit_code = %s,
					source = %s,
					source_event_id = %s,
					content_hash = UNHEX(%s),
					local_date = %s,
					start_at_utc = %s,
					end_at_utc = %s,
					area = %s,
					reason = %s,
					status = %s,
					import_run_id = %d,
					post_id = %d,
					missing_count = 0,
					sync_count = sync_count + 1,
					last_seen_at_utc = %s,
					updated_at_utc = %s,
					deleted_at_utc = NULL
				WHERE id = %d",
				$event['unit_id'],
				$event['unit_code'],
				$event['source'],
				$event['source_event_id'],
				$content_hash,
				$event['local_date'],
				$event['start_at_utc'],
				$event['end_at_utc'],
				$event['area'],
				$event['reason'],
				$event['status'],
				absint( $import_run_id ),
				absint( $post_id ),
				$now,
				$now,
				$event_id
			)
		);

		if ( false === $result ) {
			throw new RuntimeException(
				'event_update_failed'
			);
		}
	}

	/**
	 * Mark an unchanged event as seen.
	 *
	 * @param int $event_id      Event ID.
	 * @param int $import_run_id Import run ID.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When update fails.
	 */
	public static function touch(
		int $event_id,
		int $import_run_id = 0
	): void {
		global $wpdb;

		$event_id = absint( $event_id );

		if ( $event_id < 1 ) {
			throw new InvalidArgumentException(
				'invalid_event_id'
			);
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$now   = Power_Schedule_Manager_Database::utc_now();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					import_run_id = %d,
					missing_count = 0,
					sync_count = sync_count + 1,
					last_seen_at_utc = %s,
					deleted_at_utc = NULL
				WHERE id = %d",
				absint( $import_run_id ),
				$now,
				$event_id
			)
		);

		if ( false === $result ) {
			throw new RuntimeException(
				'event_touch_failed'
			);
		}
	}

	/**
	 * Update the WordPress post linked to an event.
	 *
	 * @param int $event_id Event ID.
	 * @param int $post_id  Post ID.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When update fails.
	 */
	public static function set_post_id(
		int $event_id,
		int $post_id
	): void {
		global $wpdb;

		$event_id = absint( $event_id );
		$post_id  = absint( $post_id );

		if ( $event_id < 1 || $post_id < 1 ) {
			throw new InvalidArgumentException(
				'invalid_event_or_post_id'
			);
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET post_id = %d,
					updated_at_utc = %s
				WHERE id = %d",
				$post_id,
				Power_Schedule_Manager_Database::utc_now(),
				$event_id
			)
		);

		if ( false === $result ) {
			throw new RuntimeException(
				'event_post_link_failed'
			);
		}
	}

	/**
	 * Query events by local date range.
	 *
	 * @param string      $date_from Local Y-m-d start.
	 * @param string      $date_to   Local Y-m-d end.
	 * @param string|null $unit_code Optional unit code.
	 * @param array       $statuses  Allowed statuses.
	 * @param int         $limit     Result limit.
	 * @param int         $offset    Result offset.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function query(
		string $date_from,
		string $date_to,
		?string $unit_code = null,
		array $statuses = array(),
		int $limit = 100,
		int $offset = 0
	): array {
		global $wpdb;

		$date_from = Power_Schedule_Manager_Validator::validate_local_date(
			$date_from
		);
		$date_to = Power_Schedule_Manager_Validator::validate_local_date(
			$date_to
		);

		if ( $date_to < $date_from ) {
			throw new InvalidArgumentException(
				'invalid_date_range'
			);
		}

		$limit  = min(
			self::MAX_QUERY_LIMIT,
			max( 1, $limit )
		);
		$offset = max( 0, $offset );
		$table  = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$where  = array(
			'local_date BETWEEN %s AND %s',
			'deleted_at_utc IS NULL',
		);
		$values = array(
			$date_from,
			$date_to,
		);

		if ( null !== $unit_code && '' !== $unit_code ) {
			$unit_code = Power_Schedule_Manager_Units::sanitize_code(
				$unit_code
			);

			if ( '' === $unit_code ) {
				throw new InvalidArgumentException(
					'invalid_unit_code'
				);
			}

			$where[]  = 'unit_code = %s';
			$values[] = $unit_code;
		}

		if ( array() !== $statuses ) {
			$normalized_statuses = array();

			foreach ( $statuses as $status ) {
				if ( ! is_string( $status ) ) {
					continue;
				}

				$normalized_statuses[] =
					Power_Schedule_Manager_Status::normalize(
						$status
					);
			}

			$normalized_statuses = array_values(
				array_unique( $normalized_statuses )
			);

			if ( array() !== $normalized_statuses ) {
				$placeholders = implode(
					',',
					array_fill(
						0,
						count( $normalized_statuses ),
						'%s'
					)
				);

				$where[] = "status IN ({$placeholders})";

				array_push(
					$values,
					...$normalized_statuses
				);
			}
		}

		$values[] = $limit;
		$values[] = $offset;

		$sql = "SELECT
				id,
				unit_id,
				unit_code,
				source,
				source_event_id,
				LOWER(HEX(identity_hash)) AS identity_hash,
				LOWER(HEX(content_hash)) AS content_hash,
				local_date,
				start_at_utc,
				end_at_utc,
				area,
				reason,
				status,
				import_run_id,
				post_id,
				missing_count,
				sync_count,
				first_seen_at_utc,
				last_seen_at_utc,
				created_at_utc,
				updated_at_utc,
				deleted_at_utc
			FROM {$table}
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY local_date ASC, start_at_utc ASC, id ASC
			LIMIT %d OFFSET %d';

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $values ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			array( self::class, 'cast_row' ),
			$rows
		);
	}

	/**
	 * Query one public page grouped by local date and electricity unit.
	 *
	 * Public archive pagination must happen in the database. Loading an
	 * arbitrary event limit and slicing groups in PHP can silently omit later
	 * dates when the dataset grows.
	 *
	 * @param string      $date_from        Local Y-m-d start.
	 * @param string      $date_to          Local Y-m-d end.
	 * @param string|null $unit_code        Optional public unit code.
	 * @param array       $statuses         Allowed stored statuses.
	 * @param int         $page             Requested group page.
	 * @param int         $groups_per_page  Groups per page.
	 * @param bool        $include_completed Include events that already ended.
	 *
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     total_groups: int,
	 *     page: int,
	 *     groups_per_page: int
	 * }
	 */
	public static function query_public_group_page(
		string $date_from,
		string $date_to,
		?string $unit_code,
		array $statuses,
		int $page,
		int $groups_per_page,
		bool $include_completed = false
	): array {
		global $wpdb;

		$date_from = Power_Schedule_Manager_Validator::validate_local_date(
			$date_from
		);
		$date_to = Power_Schedule_Manager_Validator::validate_local_date(
			$date_to
		);

		if ( $date_to < $date_from ) {
			throw new InvalidArgumentException( 'invalid_date_range' );
		}

		$page = max( 1, $page );
		$groups_per_page = min(
			50,
			max( 1, $groups_per_page )
		);

		$events_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$units_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$where = array(
			'events.local_date BETWEEN %s AND %s',
			'events.deleted_at_utc IS NULL',
			'units.is_public = 1',
			'posts.post_status = %s',
			'posts.post_type = %s',
		);
		$values = array(
			$date_from,
			$date_to,
			'publish',
			Power_Schedule_Manager_Post_Type::POST_TYPE,
		);

		if ( null !== $unit_code && '' !== $unit_code ) {
			$unit_code = Power_Schedule_Manager_Units::sanitize_code(
				$unit_code
			);

			if ( '' === $unit_code ) {
				throw new InvalidArgumentException(
					'invalid_unit_code'
				);
			}

			$where[] = 'events.unit_code = %s';
			$values[] = $unit_code;
		}

		$normalized_statuses = array();

		foreach ( $statuses as $status ) {
			if ( is_string( $status ) ) {
				$normalized_statuses[] =
					Power_Schedule_Manager_Status::normalize(
						$status
					);
			}
		}

		$normalized_statuses = array_values(
			array_unique( $normalized_statuses )
		);

		if ( array() !== $normalized_statuses ) {
			$status_placeholders = implode(
				',',
				array_fill(
					0,
					count( $normalized_statuses ),
					'%s'
				)
			);
			$where[] = "events.status IN ({$status_placeholders})";
			array_push( $values, ...$normalized_statuses );
		}

		if ( ! $include_completed ) {
			$where[] = 'events.end_at_utc >= %s';
			$values[] = Power_Schedule_Manager_Database::utc_now();
		}

		$joins = "FROM {$events_table} AS events
			INNER JOIN {$units_table} AS units
				ON units.id = events.unit_id
			INNER JOIN {$wpdb->posts} AS posts
				ON posts.ID = events.post_id";
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*)
			FROM (
				SELECT events.local_date, events.unit_id
				{$joins}
				WHERE {$where_sql}
				GROUP BY events.local_date, events.unit_id
			) AS public_groups";

		$total_groups = (int) $wpdb->get_var(
			$wpdb->prepare( $count_sql, $values )
		);
		$total_pages = max(
			1,
			(int) ceil( $total_groups / $groups_per_page )
		);
		$page = min( $page, $total_pages );
		$offset = ( $page - 1 ) * $groups_per_page;

		$group_values = $values;
		$group_values[] = $groups_per_page;
		$group_values[] = $offset;

		$group_sql = "SELECT
				events.local_date,
				events.unit_id,
				MIN(events.start_at_utc) AS first_start
			{$joins}
			WHERE {$where_sql}
			GROUP BY events.local_date, events.unit_id
			ORDER BY events.local_date ASC, first_start ASC, events.unit_id ASC
			LIMIT %d OFFSET %d";

		$groups = $wpdb->get_results(
			$wpdb->prepare( $group_sql, $group_values ),
			ARRAY_A
		);

		if ( ! is_array( $groups ) || array() === $groups ) {
			return array(
				'items'           => array(),
				'total_groups'    => $total_groups,
				'page'            => $page,
				'groups_per_page' => $groups_per_page,
			);
		}

		$pair_conditions = array();
		$event_values = $values;

		foreach ( $groups as $group ) {
			$local_date = isset( $group['local_date'] )
				? (string) $group['local_date']
				: '';
			$unit_id = absint( $group['unit_id'] ?? 0 );

			if ( '' === $local_date || $unit_id < 1 ) {
				continue;
			}

			$pair_conditions[] =
				'(events.local_date = %s AND events.unit_id = %d)';
			$event_values[] = $local_date;
			$event_values[] = $unit_id;
		}

		if ( array() === $pair_conditions ) {
			return array(
				'items'           => array(),
				'total_groups'    => $total_groups,
				'page'            => $page,
				'groups_per_page' => $groups_per_page,
			);
		}

		$event_sql = "SELECT
				events.id,
				events.unit_id,
				events.unit_code,
				events.source,
				events.source_event_id,
				LOWER(HEX(events.identity_hash)) AS identity_hash,
				LOWER(HEX(events.content_hash)) AS content_hash,
				events.local_date,
				events.start_at_utc,
				events.end_at_utc,
				events.area,
				events.reason,
				events.status,
				events.import_run_id,
				events.post_id,
				events.missing_count,
				events.sync_count,
				events.first_seen_at_utc,
				events.last_seen_at_utc,
				events.created_at_utc,
				events.updated_at_utc,
				events.deleted_at_utc
			{$joins}
			WHERE {$where_sql}
				AND (" . implode( ' OR ', $pair_conditions ) . ')
			ORDER BY events.local_date ASC,
				events.start_at_utc ASC,
				events.id ASC';

		$rows = $wpdb->get_results(
			$wpdb->prepare( $event_sql, $event_values ),
			ARRAY_A
		);

		return array(
			'items' => is_array( $rows )
				? array_map(
					array( self::class, 'cast_row' ),
					$rows
				)
				: array(),
			'total_groups'    => $total_groups,
			'page'            => $page,
			'groups_per_page' => $groups_per_page,
		);
	}

	/**
	 * Query published ongoing or upcoming events for compact public widgets.
	 *
	 * @param string|null $unit_code Optional public unit code.
	 * @param int         $limit Maximum rows.
	 * @param string      $order Order mode: next or updated.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query_public_upcoming(
		?string $unit_code,
		int $limit = 10,
		string $order = 'next'
	): array {
		global $wpdb;

		$limit = min( 50, max( 1, $limit ) );
		$order = 'updated' === $order ? 'updated' : 'next';
		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$now = Power_Schedule_Manager_Database::utc_now();
		$where = array(
			'events.deleted_at_utc IS NULL',
			'events.end_at_utc >= %s',
			'events.status NOT IN (%s, %s, %s)',
			'units.is_public = 1',
			'posts.post_status = %s',
			'posts.post_type = %s',
		);
		$values = array(
			$now,
			Power_Schedule_Manager_Status::CANCELLED,
			Power_Schedule_Manager_Status::REMOVED,
			Power_Schedule_Manager_Status::COMPLETED,
			'publish',
			Power_Schedule_Manager_Post_Type::POST_TYPE,
		);

		if ( null !== $unit_code && '' !== $unit_code ) {
			$unit_code = Power_Schedule_Manager_Units::sanitize_code(
				$unit_code
			);

			if ( '' === $unit_code ) {
				throw new InvalidArgumentException( 'invalid_unit_code' );
			}

			$where[] = 'events.unit_code = %s';
			$values[] = $unit_code;
		}

		$order_sql = 'updated' === $order
			? 'events.updated_at_utc DESC, events.start_at_utc ASC, events.id ASC'
			: "CASE
					WHEN events.start_at_utc <= %s
						AND events.end_at_utc > %s THEN 0
					ELSE 1
				END ASC,
				events.start_at_utc ASC,
				events.id ASC";

		if ( 'next' === $order ) {
			$values[] = $now;
			$values[] = $now;
		}

		$values[] = $limit;
		$sql = "SELECT
				events.id,
				events.unit_id,
				events.unit_code,
				events.source,
				events.source_event_id,
				LOWER(HEX(events.identity_hash)) AS identity_hash,
				LOWER(HEX(events.content_hash)) AS content_hash,
				events.local_date,
				events.start_at_utc,
				events.end_at_utc,
				events.area,
				events.reason,
				events.status,
				events.import_run_id,
				events.post_id,
				events.missing_count,
				events.sync_count,
				events.first_seen_at_utc,
				events.last_seen_at_utc,
				events.created_at_utc,
				events.updated_at_utc,
				events.deleted_at_utc
			FROM {$events} AS events
			INNER JOIN {$units} AS units
				ON units.id = events.unit_id
			INNER JOIN {$wpdb->posts} AS posts
				ON posts.ID = events.post_id
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY {$order_sql}
			LIMIT %d";
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $values ),
			ARRAY_A
		);

		return is_array( $rows )
			? array_map( array( self::class, 'cast_row' ), $rows )
			: array();
	}

	/**
	 * Query one keyset-paginated page for the public application API.
	 *
	 * Offset pagination becomes progressively slower and can duplicate rows
	 * while imports are running. The cursor uses the indexed start time and
	 * primary key as a stable continuation point.
	 *
	 * @return array{
	 *     items:array<int,array<string,mixed>>,
	 *     has_more:bool
	 * }
	 */
	public static function query_public_api_page(
		string $date_from,
		string $date_to,
		?string $unit_code,
		int $limit,
		?string $cursor_start = null,
		int $cursor_id = 0,
		bool $include_completed = false
	): array {
		global $wpdb;

		$date_from = Power_Schedule_Manager_Validator::validate_local_date(
			$date_from
		);
		$date_to = Power_Schedule_Manager_Validator::validate_local_date(
			$date_to
		);

		if ( $date_to < $date_from ) {
			throw new InvalidArgumentException( 'invalid_date_range' );
		}

		$limit = min( 100, max( 1, $limit ) );
		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$where = array(
			'events.local_date BETWEEN %s AND %s',
			'events.deleted_at_utc IS NULL',
			'events.status NOT IN (%s, %s)',
			'units.is_public = 1',
			'posts.post_status = %s',
			'posts.post_type = %s',
		);
		$values = array(
			$date_from,
			$date_to,
			Power_Schedule_Manager_Status::CANCELLED,
			Power_Schedule_Manager_Status::REMOVED,
			'publish',
			Power_Schedule_Manager_Post_Type::POST_TYPE,
		);

		if ( ! $include_completed ) {
			$where[] = 'events.end_at_utc >= %s';
			$values[] = Power_Schedule_Manager_Database::utc_now();
		}

		if ( null !== $unit_code && '' !== $unit_code ) {
			$unit_code = Power_Schedule_Manager_Units::sanitize_code(
				$unit_code
			);

			if ( '' === $unit_code ) {
				throw new InvalidArgumentException(
					'invalid_unit_code'
				);
			}

			$where[] = 'events.unit_code = %s';
			$values[] = $unit_code;
		}

		if ( null !== $cursor_start && '' !== $cursor_start ) {
			$cursor_start = Power_Schedule_Manager_Validator::
				parse_utc_datetime(
					$cursor_start,
					'cursor_start'
				)->format( 'Y-m-d H:i:s' );
			$cursor_id = absint( $cursor_id );

			if ( $cursor_id < 1 ) {
				throw new InvalidArgumentException( 'invalid_cursor' );
			}

			$where[] = '(events.start_at_utc > %s OR '
				. '(events.start_at_utc = %s AND events.id > %d))';
			$values[] = $cursor_start;
			$values[] = $cursor_start;
			$values[] = $cursor_id;
		}

		$values[] = $limit + 1;
		$sql = "SELECT
				events.id,
				events.unit_id,
				events.unit_code,
				events.local_date,
				events.start_at_utc,
				events.end_at_utc,
				events.area,
				events.reason,
				events.status,
				events.post_id,
				events.updated_at_utc,
				units.name AS unit_name,
				units.slug AS unit_slug
			FROM {$events} AS events
			INNER JOIN {$units} AS units
				ON units.id = events.unit_id
			INNER JOIN {$wpdb->posts} AS posts
				ON posts.ID = events.post_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY events.start_at_utc ASC, events.id ASC
			LIMIT %d';
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $values ),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();
		$has_more = count( $rows ) > $limit;

		if ( $has_more ) {
			array_pop( $rows );
		}

		return array(
			'items'    => array_values( $rows ),
			'has_more' => $has_more,
		);
	}

	/**
	 * Return accurate public status counters and the next start time.
	 *
	 * @param string|null $unit_code Optional public unit code.
	 * @return array{ongoing:int,upcoming:int,next_start_at_utc:string}
	 */
	public static function public_status_summary(
		?string $unit_code
	): array {
		global $wpdb;

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$now = Power_Schedule_Manager_Database::utc_now();
		$where = array(
			'events.deleted_at_utc IS NULL',
			'events.end_at_utc >= %s',
			'events.status NOT IN (%s, %s, %s)',
			'units.is_public = 1',
			'posts.post_status = %s',
			'posts.post_type = %s',
		);
		$where_values = array(
			$now,
			Power_Schedule_Manager_Status::CANCELLED,
			Power_Schedule_Manager_Status::REMOVED,
			Power_Schedule_Manager_Status::COMPLETED,
			'publish',
			Power_Schedule_Manager_Post_Type::POST_TYPE,
		);

		if ( null !== $unit_code && '' !== $unit_code ) {
			$unit_code = Power_Schedule_Manager_Units::sanitize_code(
				$unit_code
			);

			if ( '' === $unit_code ) {
				throw new InvalidArgumentException( 'invalid_unit_code' );
			}

			$where[] = 'events.unit_code = %s';
			$where_values[] = $unit_code;
		}

		$values = array_merge(
			array( $now, $now, $now, $now ),
			$where_values
		);
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(
						events.start_at_utc <= %s
						AND events.end_at_utc > %s
					), 0) AS ongoing_count,
					COALESCE(SUM(events.start_at_utc > %s), 0)
						AS upcoming_count,
					MIN(
						CASE WHEN events.start_at_utc > %s
							THEN events.start_at_utc
							ELSE NULL
						END
					) AS next_start_at_utc
				FROM {$events} AS events
				INNER JOIN {$units} AS units
					ON units.id = events.unit_id
				INNER JOIN {$wpdb->posts} AS posts
					ON posts.ID = events.post_id
				WHERE " . implode( ' AND ', $where ),
				$values
			),
			ARRAY_A
		);

		return array(
			'ongoing' => absint( $row['ongoing_count'] ?? 0 ),
			'upcoming' => absint( $row['upcoming_count'] ?? 0 ),
			'next_start_at_utc' => is_string(
				$row['next_start_at_utc'] ?? null
			)
				? $row['next_start_at_utc']
				: '',
		);
	}

	/**
	 * Return cached-ready figures used by the homepage hero.
	 *
	 * @return array{
	 *     today_units:int,
	 *     tomorrow_units:int,
	 *     week_events:int,
	 *     public_units:int,
	 *     updated_at_utc:string
	 * }
	 */
	public static function public_home_summary(): array {
		global $wpdb;

		$timezone = new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		);
		$today = new DateTimeImmutable( 'today', $timezone );
		$tomorrow = $today->modify( '+1 day' );
		$week_end = $today->modify( '+6 days' );
		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT CASE
						WHEN events.local_date = %s THEN events.unit_code
						ELSE NULL
					END) AS today_units,
					COUNT(DISTINCT CASE
						WHEN events.local_date = %s THEN events.unit_code
						ELSE NULL
					END) AS tomorrow_units,
					COALESCE(SUM(
						events.local_date BETWEEN %s AND %s
					), 0) AS week_events,
					MAX(events.updated_at_utc) AS updated_at_utc
				FROM {$events} AS events
				INNER JOIN {$units} AS units
					ON units.id = events.unit_id
				INNER JOIN {$wpdb->posts} AS posts
					ON posts.ID = events.post_id
				WHERE events.deleted_at_utc IS NULL
					AND events.status NOT IN (%s, %s)
					AND units.is_public = 1
					AND posts.post_status = 'publish'
					AND posts.post_type = %s
					AND events.local_date BETWEEN %s AND %s",
				$today->format( 'Y-m-d' ),
				$tomorrow->format( 'Y-m-d' ),
				$today->format( 'Y-m-d' ),
				$week_end->format( 'Y-m-d' ),
				Power_Schedule_Manager_Status::CANCELLED,
				Power_Schedule_Manager_Status::REMOVED,
				Power_Schedule_Manager_Post_Type::POST_TYPE,
				$today->format( 'Y-m-d' ),
				$week_end->format( 'Y-m-d' )
			),
			ARRAY_A
		);
		$public_units = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$units} WHERE is_public = 1"
		);

		return array(
			'today_units'   => absint( $row['today_units'] ?? 0 ),
			'tomorrow_units' => absint( $row['tomorrow_units'] ?? 0 ),
			'week_events'   => absint( $row['week_events'] ?? 0 ),
			'public_units'  => absint( $public_units ),
			'updated_at_utc' => is_string(
				$row['updated_at_utc'] ?? null
			)
				? (string) $row['updated_at_utc']
				: '',
		);
	}

	/**
	 * Count published active schedules by local date.
	 *
	 * @param string      $date_from Local start date.
	 * @param string      $date_to Local end date.
	 * @param string|null $unit_code Optional public unit code.
	 * @return array<int,array{local_date:string,event_count:int,first_start_at_utc:string}>
	 */
	public static function public_day_counts(
		string $date_from,
		string $date_to,
		?string $unit_code
	): array {
		global $wpdb;

		$date_from = Power_Schedule_Manager_Validator::validate_local_date(
			$date_from
		);
		$date_to = Power_Schedule_Manager_Validator::validate_local_date(
			$date_to
		);

		if ( $date_to < $date_from ) {
			throw new InvalidArgumentException( 'invalid_date_range' );
		}

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$where = array(
			'events.local_date BETWEEN %s AND %s',
			'events.deleted_at_utc IS NULL',
			'events.end_at_utc >= %s',
			'events.status NOT IN (%s, %s, %s)',
			'units.is_public = 1',
			'posts.post_status = %s',
			'posts.post_type = %s',
		);
		$values = array(
			$date_from,
			$date_to,
			Power_Schedule_Manager_Database::utc_now(),
			Power_Schedule_Manager_Status::CANCELLED,
			Power_Schedule_Manager_Status::REMOVED,
			Power_Schedule_Manager_Status::COMPLETED,
			'publish',
			Power_Schedule_Manager_Post_Type::POST_TYPE,
		);

		if ( null !== $unit_code && '' !== $unit_code ) {
			$unit_code = Power_Schedule_Manager_Units::sanitize_code(
				$unit_code
			);

			if ( '' === $unit_code ) {
				throw new InvalidArgumentException( 'invalid_unit_code' );
			}

			$where[] = 'events.unit_code = %s';
			$values[] = $unit_code;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					events.local_date,
					COUNT(*) AS event_count,
					MIN(events.start_at_utc) AS first_start_at_utc
				FROM {$events} AS events
				INNER JOIN {$units} AS units
					ON units.id = events.unit_id
				INNER JOIN {$wpdb->posts} AS posts
					ON posts.ID = events.post_id
				WHERE " . implode( ' AND ', $where ) . '
				GROUP BY events.local_date
				ORDER BY events.local_date ASC',
				$values
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values(
			array_map(
				static fn ( array $row ): array => array(
					'local_date' => (string) ( $row['local_date'] ?? '' ),
					'event_count' => absint( $row['event_count'] ?? 0 ),
					'first_start_at_utc' =>
						(string) ( $row['first_start_at_utc'] ?? '' ),
				),
				$rows
			)
		);
	}

	/**
	 * Mark event as softly deleted.
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return void
	 */
	public static function soft_delete( int $event_id ): void {
		global $wpdb;

		$event_id = absint( $event_id );

		if ( $event_id < 1 ) {
			throw new InvalidArgumentException(
				'invalid_event_id'
			);
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$now   = Power_Schedule_Manager_Database::utc_now();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET
					status = %s,
					deleted_at_utc = %s,
					updated_at_utc = %s
				WHERE id = %d",
				Power_Schedule_Manager_Status::REMOVED,
				$now,
				$now,
				$event_id
			)
		);

		if ( false === $result ) {
			throw new RuntimeException(
				'event_soft_delete_failed'
			);
		}
	}

	/**
	 * Create canonical SHA-256 for an array.
	 *
	 * @param array<string, int|string> $values Canonical values.
	 *
	 * @return string
	 */
	private static function hash_array( array $values ): string {
		try {
			$json = wp_json_encode(
				$values,
				JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_PRESERVE_ZERO_FRACTION
				| JSON_THROW_ON_ERROR,
				16
			);
		} catch ( JsonException $exception ) {
			throw new InvalidArgumentException(
				'unable_to_encode_hash_payload',
				0,
				$exception
			);
		}

		if ( ! is_string( $json ) ) {
			throw new InvalidArgumentException(
				'unable_to_encode_hash_payload'
			);
		}

		return hash( 'sha256', $json );
	}

	/**
	 * Normalize text for stable hashing.
	 *
	 * Accents and punctuation are preserved because they may materially change
	 * the public schedule content.
	 *
	 * @param string $value Text.
	 *
	 * @return string
	 */
	private static function canonical_text( string $value ): string {
		$value = trim( $value );

		$value = preg_replace(
			'/\s+/u',
			' ',
			$value
		) ?? $value;

		return $value;
	}

	/**
	 * Validate hexadecimal SHA-256.
	 *
	 * @param string $hash Hash.
	 *
	 * @return string
	 */
	private static function validate_hex_hash(
		string $hash
	): string {
		$hash = strtolower( trim( $hash ) );

		if (
			64 !== strlen( $hash )
			|| 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $hash )
		) {
			throw new InvalidArgumentException(
				'invalid_sha256_hash'
			);
		}

		return $hash;
	}

	/**
	 * Cast database values to stable PHP types.
	 *
	 * @param array<string, mixed> $row Database row.
	 *
	 * @return array<string, mixed>
	 */
	private static function cast_row( array $row ): array {
		return array(
			'id'                => (int) ( $row['id'] ?? 0 ),
			'unit_id'           => (int) ( $row['unit_id'] ?? 0 ),
			'unit_code'         => (string) ( $row['unit_code'] ?? '' ),
			'source'            => (string) ( $row['source'] ?? '' ),
			'source_event_id'   => (string) ( $row['source_event_id'] ?? '' ),
			'identity_hash'     => (string) ( $row['identity_hash'] ?? '' ),
			'content_hash'      => (string) ( $row['content_hash'] ?? '' ),
			'local_date'        => (string) ( $row['local_date'] ?? '' ),
			'start_at_utc'      => (string) ( $row['start_at_utc'] ?? '' ),
			'end_at_utc'        => (string) ( $row['end_at_utc'] ?? '' ),
			'area'              => (string) ( $row['area'] ?? '' ),
			'reason'            => (string) ( $row['reason'] ?? '' ),
			'status'            =>
				Power_Schedule_Manager_Status::normalize_or_default(
					$row['status'] ?? ''
				),
			'import_run_id'     => (int) ( $row['import_run_id'] ?? 0 ),
			'post_id'           => (int) ( $row['post_id'] ?? 0 ),
			'missing_count'     => (int) ( $row['missing_count'] ?? 0 ),
			'sync_count'        => (int) ( $row['sync_count'] ?? 0 ),
			'first_seen_at_utc' => (string) ( $row['first_seen_at_utc'] ?? '' ),
			'last_seen_at_utc'  => (string) ( $row['last_seen_at_utc'] ?? '' ),
			'created_at_utc'    => (string) ( $row['created_at_utc'] ?? '' ),
			'updated_at_utc'    => (string) ( $row['updated_at_utc'] ?? '' ),
			'deleted_at_utc'    => isset( $row['deleted_at_utc'] )
				? (string) $row['deleted_at_utc']
				: null,
		);
	}
}
