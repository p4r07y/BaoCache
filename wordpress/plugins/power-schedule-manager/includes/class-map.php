<?php
/**
 * Event map and location management.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages structured locations and public map data.
 */
final class Power_Schedule_Manager_Map {

	/**
	 * REST namespace.
	 */
	private const string REST_NAMESPACE =
		'power-schedule-manager/v1';

	/**
	 * Maximum locations per event.
	 */
	private const int MAX_LOCATIONS_PER_EVENT = 100;

	/**
	 * Maximum label length.
	 */
	private const int MAX_LABEL_LENGTH = 191;

	/**
	 * Maximum description length.
	 */
	private const int MAX_DESCRIPTION_LENGTH = 2000;

	/**
	 * Allowed location types.
	 *
	 * @var array<string, true>
	 */
	private const array ALLOWED_LOCATION_TYPES = array(
		'road_segment' => true,
		'area'         => true,
		'point'        => true,
		'facility'     => true,
	);

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'rest_api_init',
			array( $this, 'register_rest_routes' )
		);
	}

	/**
	 * Register public map REST route.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/events/(?P<event_id>\d+)/locations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array(
					$this,
					'get_public_locations',
				),
				'permission_callback' => array(
					$this,
					'can_view_public_locations',
				),
				'args'                => array(
					'event_id' => array(
						'description'       => __(
							'ID sự kiện lịch điện.',
							'power-schedule-manager'
						),
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function (
							mixed $value
						): bool {
							return is_numeric( $value )
								&& (int) $value > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Check whether event locations may be viewed publicly.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public function can_view_public_locations(
		WP_REST_Request $request
	): bool|WP_Error {
		$rate_limit = Power_Schedule_Manager_API::rate_limit();

		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$event_id = absint(
			$request->get_param( 'event_id' )
		);

		$event = Power_Schedule_Manager_Repository::find_by_id(
			$event_id
		);

		if ( null === $event ) {
			return new WP_Error(
				'psm_event_not_found',
				__( 'Không tìm thấy lịch điện.', 'power-schedule-manager' ),
				array( 'status' => 404 )
			);
		}

		if (
			! Power_Schedule_Manager_Status::is_publicly_visible(
				(string) $event['status']
			)
		) {
			return new WP_Error(
				'psm_event_not_public',
				__( 'Lịch điện này không được công khai.', 'power-schedule-manager' ),
				array( 'status' => 404 )
			);
		}

		if (
			! Power_Schedule_Manager_Units::is_public(
				(string) $event['unit_code']
			)
		) {
			return new WP_Error(
				'psm_unit_not_public',
				__( 'Khu vực điện lực này không được công khai.', 'power-schedule-manager' ),
				array( 'status' => 404 )
			);
		}

		$post_id = (int) $event['post_id'];

		if (
			$post_id < 1
			|| 'publish' !== get_post_status( $post_id )
		) {
			return new WP_Error(
				'psm_schedule_not_published',
				__( 'Lịch điện chưa được xuất bản.', 'power-schedule-manager' ),
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/**
	 * Return public locations.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_public_locations(
		WP_REST_Request $request
	): WP_REST_Response {
		$event_id = absint(
			$request->get_param( 'event_id' )
		);

		$locations = Power_Schedule_Manager_Cache::remember(
			'event_locations',
			array(
				'event_id' => $event_id,
				'public'   => true,
			),
			static fn (): array => self::locations(
				$event_id,
				true
			),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);

		$response = new WP_REST_Response(
			array(
				'event_id'  => $event_id,
				'count'     => count( $locations ),
				'locations' => $locations,
			),
			200
		);

		$response->header(
			'Cache-Control',
			'public, max-age=300'
		);

		return $response;
	}

	/**
	 * Return locations for an event.
	 *
	 * @param int  $event_id   Event ID.
	 * @param bool $public_api Format for public REST use.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function locations(
		int $event_id,
		bool $public_api = false
	): array {
		global $wpdb;

		$event_id = absint( $event_id );

		if ( $event_id < 1 ) {
			return array();
		}

		$rows = Power_Schedule_Manager_Place_Library::event_locations(
			$event_id
		);

		/*
		 * Keep legacy event-owned locations readable during the 1.1 migration.
		 * New edits are stored in the reusable place library.
		 */
		if ( array() === $rows ) {
			$table = Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::EVENT_LOCATIONS
			);

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						id,
						event_id,
						location_type,
						label,
						description,
						geojson,
						center_lat,
						center_lng,
						default_zoom,
						sort_order,
						created_at_utc,
						updated_at_utc
					FROM {$table}
					WHERE event_id = %d
					ORDER BY sort_order ASC, id ASC",
					$event_id
				),
				ARRAY_A
			);
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ) use (
				$public_api
			): array {
				return self::cast_location_row(
					$row,
					$public_api
				);
			},
			$rows
		);
	}

	/**
	 * Replace all locations belonging to an event.
	 *
	 * @param int                       $event_id Event ID.
	 * @param array<int, array<string, mixed>> $locations Locations.
	 *
	 * @return array<int, int> New location IDs.
	 *
	 * @throws RuntimeException When replacement fails.
	 */
	public static function replace_locations(
		int $event_id,
		array $locations
	): array {
		global $wpdb;

		$event_id = absint( $event_id );
		$event    = Power_Schedule_Manager_Repository::find_by_id(
			$event_id
		);

		if ( null === $event ) {
			throw new RuntimeException(
				'map_event_not_found'
			);
		}

		self::assert_can_edit_event( $event );

		if ( count( $locations ) > self::MAX_LOCATIONS_PER_EVENT ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d: Maximum locations. */
					__(
						'Mỗi sự kiện chỉ được có tối đa %d vị trí.',
						'power-schedule-manager'
					),
					self::MAX_LOCATIONS_PER_EVENT
				)
			);
		}

		$normalized = array();

		foreach ( $locations as $index => $location ) {
			if ( ! is_array( $location ) ) {
				throw new InvalidArgumentException(
					__(
						'Dữ liệu vị trí bản đồ không hợp lệ.',
						'power-schedule-manager'
					)
				);
			}

			$normalized[] = self::normalize_location(
				$location,
				(int) $index
			);
		}

		$legacy_table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_LOCATIONS
		);

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			throw new RuntimeException(
				'map_transaction_start_failed'
			);
		}

		$place_ids = array();

		try {
			$place_ids =
				Power_Schedule_Manager_Place_Library::replace_event_locations(
					$event_id,
					(string) $event['unit_code'],
					$normalized
				);

			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$legacy_table}
					WHERE event_id = %d",
					$event_id
				)
			);

			if ( false === $deleted ) {
				throw new RuntimeException(
					'map_existing_locations_delete_failed'
				);
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException(
					'map_transaction_commit_failed'
				);
			}
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );

			throw $throwable;
		}

		Power_Schedule_Manager_Cache::invalidate_all();

		do_action(
			'power_schedule_manager_event_updated',
			$event_id,
			array(
				'post_id'   => (int) $event['post_id'],
				'unit_code' => (string) $event['unit_code'],
				'local_date' => (string) $event['local_date'],
				'change'    => 'locations',
			)
		);

		return $place_ids;
	}

	/**
	 * Normalize one location.
	 *
	 * @param array<string, mixed> $location   Raw location.
	 * @param int                  $sort_order Default order.
	 *
	 * @return array{
	 *     location_type: string,
	 *     label: string,
	 *     description: string|null,
	 *     geojson: string|null,
	 *     center_lat: float|null,
	 *     center_lng: float|null,
	 *     default_zoom: int,
	 *     sort_order: int
	 * }
	 */
	private static function normalize_location(
		array $location,
		int $sort_order
	): array {
		$location_type = isset( $location['location_type'] )
			&& is_string( $location['location_type'] )
			? sanitize_key( $location['location_type'] )
			: 'road_segment';

		if (
			! isset(
				self::ALLOWED_LOCATION_TYPES[ $location_type ]
			)
		) {
			throw new InvalidArgumentException(
				__( 'Loại vị trí không hợp lệ.', 'power-schedule-manager' )
			);
		}

		$label = self::sanitize_text(
			$location['label'] ?? '',
			self::MAX_LABEL_LENGTH
		);

		if ( '' === $label ) {
			throw new InvalidArgumentException(
				__( 'Tên vị trí không được để trống.', 'power-schedule-manager' )
			);
		}

		$description = self::sanitize_textarea(
			$location['description'] ?? '',
			self::MAX_DESCRIPTION_LENGTH
		);

		$geojson    = null;
		$center_lat = null;
		$center_lng = null;
		$submitted_center = null;

		if (
			isset(
				$location['center_lat'],
				$location['center_lng']
			)
			&& is_numeric( $location['center_lat'] )
			&& is_numeric( $location['center_lng'] )
		) {
			$submitted_center =
				Power_Schedule_Manager_GeoJSON::sanitize_center(
					(float) $location['center_lat'],
					(float) $location['center_lng']
				);
		}

		if (
			isset( $location['geojson'] )
			&& is_string( $location['geojson'] )
			&& '' !== trim( $location['geojson'] )
		) {
			$geojson = Power_Schedule_Manager_GeoJSON::sanitize(
				$location['geojson']
			);

			$center = is_array( $submitted_center )
				? $submitted_center
				: Power_Schedule_Manager_GeoJSON::calculate_center(
					$geojson
				);

			$center_lat = $center['lat'];
			$center_lng = $center['lng'];
		} elseif ( is_array( $submitted_center ) ) {
			$center = $submitted_center;

			$center_lat = $center['lat'];
			$center_lng = $center['lng'];
		}

		$default_zoom =
			Power_Schedule_Manager_GeoJSON::sanitize_zoom(
				isset( $location['default_zoom'] )
				? (int) $location['default_zoom']
				: 15
			);

		$sort_order = isset( $location['sort_order'] )
			? max( 0, absint( $location['sort_order'] ) )
			: max( 0, $sort_order );

		return array(
			'location_type' => $location_type,
			'label'         => $label,
			'description'   => '' === $description
				? null
				: $description,
			'geojson'       => $geojson,
			'center_lat'    => $center_lat,
			'center_lng'    => $center_lng,
			'default_zoom'  => $default_zoom,
			'sort_order'    => $sort_order,
		);
	}

	/**
	 * Check edit permission for an event.
	 *
	 * @param array<string, mixed> $event Event.
	 *
	 * @return void
	 */
	private static function assert_can_edit_event(
		array $event
	): void {
		if (
			! is_user_logged_in()
			|| ! Power_Schedule_Manager_Capabilities::current_user_can_import()
		) {
			throw new RuntimeException(
				'map_edit_permission_denied'
			);
		}

		$post_id = (int) ( $event['post_id'] ?? 0 );

		if (
			$post_id > 0
			&& ! current_user_can( 'edit_post', $post_id )
		) {
			throw new RuntimeException(
				'map_post_edit_permission_denied'
			);
		}
	}

	/**
	 * Cast database location row.
	 *
	 * @param array<string, mixed> $row        Database row.
	 * @param bool                 $public_api Public response.
	 *
	 * @return array<string, mixed>
	 */
	private static function cast_location_row(
		array $row,
		bool $public_api
	): array {
		$geojson = null;

		if (
			isset( $row['geojson'] )
			&& is_string( $row['geojson'] )
			&& '' !== $row['geojson']
		) {
			try {
				$geojson = Power_Schedule_Manager_GeoJSON::decode(
					$row['geojson']
				);
			} catch ( InvalidArgumentException ) {
				$geojson = null;
			}
		}

		$location = array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'event_id'      => (int) ( $row['event_id'] ?? 0 ),
			'location_type' => (string) ( $row['location_type'] ?? '' ),
			'label'         => (string) ( $row['label'] ?? '' ),
			'description'   => isset( $row['description'] )
				? (string) $row['description']
				: null,
			'geojson'       => $geojson,
			'center_lat'    => isset( $row['center_lat'] )
				? (float) $row['center_lat']
				: null,
			'center_lng'    => isset( $row['center_lng'] )
				? (float) $row['center_lng']
				: null,
			'default_zoom'  => (int) ( $row['default_zoom'] ?? 15 ),
			'sort_order'    => (int) ( $row['sort_order'] ?? 0 ),
		);

		if ( ! $public_api ) {
			$location['created_at_utc'] =
				(string) ( $row['created_at_utc'] ?? '' );

			$location['updated_at_utc'] =
				(string) ( $row['updated_at_utc'] ?? '' );
		}

		return $location;
	}

	/**
	 * Sanitize limited single-line text.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum characters.
	 *
	 * @return string
	 */
	private static function sanitize_text(
		mixed $value,
		int $length
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return self::limit_text(
			sanitize_text_field( (string) $value ),
			$length
		);
	}

	/**
	 * Sanitize limited multiline text.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum characters.
	 *
	 * @return string
	 */
	private static function sanitize_textarea(
		mixed $value,
		int $length
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return self::limit_text(
			sanitize_textarea_field( (string) $value ),
			$length
		);
	}

	/**
	 * Limit text safely.
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
