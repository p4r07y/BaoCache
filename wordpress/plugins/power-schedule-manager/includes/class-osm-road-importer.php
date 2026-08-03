<?php
/**
 * Secure OpenStreetMap road and boundary importer.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds named roads and administrative boundaries inside a guarded box.
 */
final class Power_Schedule_Manager_OSM_Road_Importer {

	private const string AJAX_ACTION = 'psm_osm_preview_road';

	private const string NONCE_ACTION = 'psm_osm_preview_road';

	/**
	 * Public Overpass instances tried in order.
	 *
	 * Endpoints are hard-coded so this server-side request cannot be changed
	 * into an SSRF request through administrator input.
	 *
	 * @var array<int,string>
	 */
	private const array OVERPASS_ENDPOINTS = array(
		'https://overpass-api.de/api/interpreter',
		'https://overpass.private.coffee/api/interpreter',
		'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
	);

	private const string NOMINATIM_ENDPOINT =
		'https://nominatim.openstreetmap.org/search';

	private const int MAX_RESPONSE_BYTES = 5_000_000;

	/**
	 * Avoid repeatedly waiting on an endpoint that has just failed.
	 */
	private const int ENDPOINT_FAILURE_TTL = 120;

	private const int MAX_WAYS = 250;

	private const int MAX_AREA_RESULTS = 80;

	/*
	 * Keep below the GeoJSON service limit so an accepted preview is always
	 * saveable without a second, surprising validation failure.
	 */
	private const int MAX_POINTS = 4_500;

	/**
	 * Register authenticated administration endpoint.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'wp_ajax_' . self::AJAX_ACTION,
			array( $this, 'handle_preview' )
		);
	}

	/**
	 * Return data needed by the editor JavaScript.
	 *
	 * @return array<string,mixed>
	 */
	public static function editor_configuration(): array {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$settings = is_array( $settings ) ? $settings : array();
		$map = Power_Schedule_Manager_Assets::provider_configuration(
			$settings
		);

		if ( empty( $map['enabled'] ) ) {
			$map = Power_Schedule_Manager_Assets::provider_configuration(
				array(
					'map_provider' => 'osm',
					'map_max_zoom' => 19,
				)
			);
		}

		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'action'      => self::AJAX_ACTION,
			'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
			'tileUrl'     => (string) $map['tile_url'],
			'attribution' => wp_strip_all_tags(
				(string) $map['attribution']
			),
			'maxZoom'     => (string) $map['max_zoom'],
			'tileSize'    => (string) $map['tile_size'],
			'zoomOffset'  => (string) $map['zoom_offset'],
			'crossOrigin' => (bool) $map['cross_origin'],
			'unitBounds'  => self::unit_bounds(),
		);
	}

	/**
	 * Return safe regional search bounds keyed by source unit.
	 *
	 * These are starting viewports, not fabricated place coordinates. An
	 * administrator can narrow the viewport before querying OSM.
	 *
	 * @return array<string,array{south:float,west:float,north:float,east:float}>
	 */
	private static function unit_bounds(): array {
		/*
		 * These boxes are deliberately scoped by electricity unit instead
		 * of company prefix. They are search guards, not administrative
		 * boundary geometry and are never shown as outage coverage.
		 */
		return array(
			'PB0201' => self::bounds( 10.78, 107.98, 11.03, 108.36 ),
			'PB0202' => self::bounds( 11.12, 108.45, 11.48, 108.97 ),
			'PB0203' => self::bounds( 10.92, 107.36, 11.35, 107.83 ),
			'PB0204' => self::bounds( 10.58, 107.52, 10.92, 108.02 ),
			'PB0205' => self::bounds( 10.52, 107.62, 10.76, 107.93 ),
			'PB0206' => self::bounds( 10.42, 108.83, 10.66, 109.02 ),
			'PB0207' => self::bounds( 10.83, 107.80, 11.30, 108.38 ),
			'PB0208' => self::bounds( 10.62, 107.82, 11.03, 108.34 ),
			'PB0209' => self::bounds( 10.98, 108.18, 11.49, 108.75 ),
			'PB0210' => self::bounds( 10.90, 107.44, 11.37, 108.02 ),
			'PB0211' => self::bounds( 8.30, 111.50, 9.70, 113.00 ),
			'PB0301' => self::bounds( 11.78, 108.28, 12.12, 108.62 ),
			'PB0302' => self::bounds( 11.42, 107.64, 11.72, 107.96 ),
			'PB0303' => self::bounds( 11.64, 108.42, 11.98, 108.78 ),
			'PB0304' => self::bounds( 11.38, 107.91, 11.76, 108.38 ),
			'PB0305' => self::bounds( 11.52, 108.10, 11.91, 108.57 ),
			'PB0306' => self::bounds( 11.68, 107.80, 12.14, 108.34 ),
			'PB0307' => self::bounds( 11.18, 107.38, 11.64, 107.88 ),
			'PB0308' => self::bounds( 11.42, 107.30, 11.79, 107.78 ),
			'PB0309' => self::bounds( 11.31, 107.05, 11.82, 107.62 ),
			'PB0310' => self::bounds( 11.36, 107.51, 11.94, 108.04 ),
			'PB0311' => self::bounds( 11.91, 108.20, 12.38, 108.79 ),
			'PB0312' => self::bounds( 11.91, 107.78, 12.48, 108.48 ),
			'PC13BB' => self::bounds( 11.75, 107.28, 12.18, 107.78 ),
			'PC13CC' => self::bounds( 12.38, 107.52, 12.82, 108.04 ),
			'PC13DD' => self::bounds( 12.20, 107.37, 12.67, 107.85 ),
			'PC13EE' => self::bounds( 12.13, 107.68, 12.68, 108.18 ),
			'PC13FF' => self::bounds( 11.78, 107.47, 12.18, 107.91 ),
			'PC13HH' => self::bounds( 11.91, 107.34, 12.40, 107.88 ),
			'PC13II' => self::bounds( 11.82, 107.02, 12.34, 107.60 ),
			'PC13ZZ' => self::bounds( 11.65, 107.00, 12.90, 108.25 ),
		);
	}

	/**
	 * Build one consistently keyed geographic box.
	 *
	 * @return array{south:float,west:float,north:float,east:float}
	 */
	private static function bounds(
		float $south,
		float $west,
		float $north,
		float $east
	): array {
		return compact( 'south', 'west', 'north', 'east' );
	}

	/**
	 * Find a full named road and return sanitized GeoJSON.
	 *
	 * @return void
	 */
	public function handle_preview(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_send_json_error(
				array( 'message' => __( 'Bạn không có quyền nhập dữ liệu OSM.', 'power-schedule-manager' ) ),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$name = sanitize_text_field(
			wp_unslash( (string) ( $_POST['road_name'] ?? '' ) )
		);
		$search_type = sanitize_key(
			wp_unslash( (string) ( $_POST['search_type'] ?? 'road' ) )
		);
		$locality = sanitize_text_field(
			wp_unslash( (string) ( $_POST['locality'] ?? '' ) )
		);

		if ( ! in_array( $search_type, array( 'road', 'area' ), true ) ) {
			$search_type = 'road';
		}
		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			wp_unslash( (string) ( $_POST['unit_code'] ?? '' ) )
		);

		if ( null === Power_Schedule_Manager_Units::find_by_code( $unit_code ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Hãy chọn đơn vị điện lực hợp lệ trước khi tìm đường.',
						'power-schedule-manager'
					),
				),
				422
			);
		}

		try {
			$bounds = self::sanitize_bounds(
				$_POST['south'] ?? null,
				$_POST['west'] ?? null,
				$_POST['north'] ?? null,
				$_POST['east'] ?? null
			);
			$bounds = self::constrain_bounds_to_unit(
				$bounds,
				$unit_code
			);
			$result = $this->fetch(
				$name,
				$bounds,
				$unit_code,
				$search_type,
				$locality
			);
		} catch ( Throwable $throwable ) {
			Power_Schedule_Manager_Logger::error(
				'osm_road_preview_failed',
				$throwable
			);

			$message = match ( $throwable->getMessage() ) {
				'invalid_road_name' => __( 'Tên tìm kiếm không hợp lệ.', 'power-schedule-manager' ),
				'invalid_locality' => __( 'Tên địa phương giới hạn không hợp lệ.', 'power-schedule-manager' ),
				'invalid_bounds' => __( 'Khung tìm kiếm không hợp lệ hoặc quá lớn.', 'power-schedule-manager' ),
				'bounds_outside_unit' => __( 'Vùng bản đồ đang xem nằm ngoài phạm vi đơn vị điện lực đã chọn.', 'power-schedule-manager' ),
				'road_not_found' => __( 'Không tìm thấy toàn tuyến phù hợp trong khung tìm kiếm.', 'power-schedule-manager' ),
				'area_not_found' => __( 'Không tìm thấy ranh giới khép kín phù hợp trên OpenStreetMap. Hãy thử tên hành chính hiện hành (ví dụ “Phường Lộc Châu”), thêm địa phương cha hoặc thu hẹp vùng bản đồ. Nếu OpenStreetMap chưa có ranh giới, hãy dán GeoJSON đã được xác minh; plugin không tự suy đoán phạm vi.', 'power-schedule-manager' ),
				'too_many_results' => __( 'Có quá nhiều kết quả. Hãy thu hẹp khung tìm kiếm.', 'power-schedule-manager' ),
				'overpass_rate_limited' => __( 'Máy chủ OSM đang giới hạn yêu cầu. Vui lòng chờ một phút rồi thử lại.', 'power-schedule-manager' ),
				'overpass_unavailable' => __( 'Tạm thời chưa thể kết nối các máy chủ bản đồ. Dữ liệu đã nhập không bị mất; vui lòng đợi một lát rồi thử lại.', 'power-schedule-manager' ),
				'invalid_overpass_response' => __( 'Dịch vụ OSM trả về dữ liệu không hợp lệ. Vui lòng thử lại với khung tìm kiếm nhỏ hơn.', 'power-schedule-manager' ),
				default => __( 'Không thể lấy dữ liệu OSM lúc này. Vui lòng thử lại sau.', 'power-schedule-manager' ),
			};

			wp_send_json_error( array( 'message' => $message ), 422 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Intersect administrator viewport with the selected unit search guard.
	 *
	 * This server-side check prevents a stale browser state or modified AJAX
	 * request from returning a same-named road in another locality.
	 *
	 * @param array<string,float> $bounds Requested bounds.
	 * @param string              $unit_code Unit code.
	 * @return array<string,float>
	 */
	private static function constrain_bounds_to_unit(
		array $bounds,
		string $unit_code
	): array {
		$unit_bounds = self::unit_bounds();
		$allowed = $unit_bounds[ $unit_code ] ?? null;

		if ( ! is_array( $allowed ) ) {
			throw new InvalidArgumentException( 'invalid_bounds' );
		}

		$constrained = array(
			'south' => max( $bounds['south'], $allowed['south'] ),
			'west'  => max( $bounds['west'], $allowed['west'] ),
			'north' => min( $bounds['north'], $allowed['north'] ),
			'east'  => min( $bounds['east'], $allowed['east'] ),
		);

		if (
			$constrained['south'] >= $constrained['north']
			|| $constrained['west'] >= $constrained['east']
		) {
			throw new InvalidArgumentException(
				'bounds_outside_unit'
			);
		}

		return $constrained;
	}

	/**
	 * Query Overpass and group matching ways into connected candidates.
	 *
	 * @param string              $name   Exact OSM road name.
	 * @param array<string,float> $bounds Bounding box.
	 * @param string              $unit_code Electricity unit code.
	 * @return array<string,mixed>
	 */
	private function fetch(
		string $name,
		array $bounds,
		string $unit_code,
		string $search_type,
		string $locality
	): array {
		if ( 'area' === $search_type ) {
			return $this->fetch_area(
				$name,
				$bounds,
				$unit_code,
				$locality
			);
		}

		return $this->fetch_road( $name, $bounds, $unit_code );
	}

	/**
	 * Query Overpass and group matching road ways into connected candidates.
	 *
	 * @param string              $name Exact OSM road name.
	 * @param array<string,float> $bounds Bounding box.
	 * @param string              $unit_code Electricity unit code.
	 * @return array<string,mixed>
	 */
	private function fetch_road(
		string $name,
		array $bounds,
		string $unit_code
	): array {
		$name = trim( $name );

		self::assert_search_term( $name, 'invalid_road_name' );

		$cache_key = 'psm_osm_' . hash(
			'sha256',
			wp_json_encode(
				array( 4, 'road', $unit_code, $name, $bounds )
			)
		);
		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$escaped_name = self::overpass_exact_pattern( $name );
		$bbox = self::format_bbox( $bounds );
		$query = sprintf(
			'[out:json][timeout:22][maxsize:67108864];way["highway"]["name"~%s,i](%s);out body geom;',
			$escaped_name,
			$bbox
		);

		$response = $this->request_overpass( $query );

		try {
			$decoded = json_decode(
				wp_remote_retrieve_body( $response ),
				true,
				512,
				JSON_THROW_ON_ERROR
			);
		} catch ( JsonException $exception ) {
			throw new RuntimeException(
				'invalid_overpass_response',
				0,
				$exception
			);
		}
		$elements = is_array( $decoded['elements'] ?? null )
			? $decoded['elements']
			: array();

		if ( count( $elements ) > self::MAX_WAYS ) {
			throw new RuntimeException( 'too_many_results' );
		}

		$ways = array();
		$point_count = 0;

		foreach ( $elements as $element ) {
			if (
				! is_array( $element )
				|| 'way' !== ( $element['type'] ?? '' )
				|| ! is_array( $element['geometry'] ?? null )
			) {
				continue;
			}

			$line = array();

			foreach ( $element['geometry'] as $point ) {
				if (
					! is_array( $point )
					|| ! is_numeric( $point['lat'] ?? null )
					|| ! is_numeric( $point['lon'] ?? null )
				) {
					continue;
				}

				$coordinate = array(
					round( (float) $point['lon'], 7 ),
					round( (float) $point['lat'], 7 ),
				);

				if ( array() === $line || end( $line ) !== $coordinate ) {
					$line[] = $coordinate;
					++$point_count;
				}

				if ( $point_count > self::MAX_POINTS ) {
					throw new RuntimeException( 'too_many_results' );
				}
			}

			if ( count( $line ) >= 2 ) {
				$ways[] = array(
					'line'   => $line,
					'way_id' => absint( $element['id'] ?? 0 ),
				);
			}
		}

		if ( array() === $ways ) {
			throw new RuntimeException( 'road_not_found' );
		}

		$groups = self::connected_groups( $ways );
		$candidates = array();

		foreach ( $groups as $index => $group ) {
			$lines = array_column( $group, 'line' );
			$way_ids = array_values(
				array_filter(
					array_map(
						'absint',
						array_column( $group, 'way_id' )
					)
				)
			);
			$candidate_points = array_sum(
				array_map( 'count', $lines )
			);
			$geojson = wp_json_encode(
				array(
					'type'        => 'MultiLineString',
					'coordinates' => $lines,
				),
				JSON_UNESCAPED_SLASHES
			);
			$geojson =
				Power_Schedule_Manager_GeoJSON::sanitize( $geojson );
			$center =
				Power_Schedule_Manager_GeoJSON::calculate_center(
					$geojson
				);
			$candidates[] = array(
				'id'         => 'candidate-' . ( $index + 1 ),
				'geojson'    => $geojson,
				'centerLat'  => $center['lat'],
				'centerLng'  => $center['lng'],
				'wayIds'     => $way_ids,
				'wayCount'   => count( $lines ),
				'pointCount' => $candidate_points,
				'searchType' => 'road',
				'geometryType' => 'MultiLineString',
				'name'       => $name,
			);
		}

		usort(
			$candidates,
			static fn ( array $left, array $right ): int =>
				$right['pointCount'] <=> $left['pointCount']
		);
		$primary = $candidates[0];
		$result = array(
			'geojson'    => $primary['geojson'],
			'centerLat'  => $primary['centerLat'],
			'centerLng'  => $primary['centerLng'],
			'wayIds'     => $primary['wayIds'],
			'wayCount'   => $primary['wayCount'],
			'pointCount' => $point_count,
			'candidates' => $candidates,
			'provider'   => 'OpenStreetMap',
			'unitCode'   => $unit_code,
			'searchType' => 'road',
			'fetchedAt'  => gmdate( DATE_ATOM ),
		);

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return $result;
	}

	/**
	 * Find exact named OSM administrative polygons.
	 *
	 * @param string              $name Boundary name.
	 * @param array<string,float> $bounds Server-constrained search bounds.
	 * @param string              $unit_code Electricity unit code.
	 * @param string              $locality Optional parent locality name.
	 * @return array<string,mixed>
	 */
	private function fetch_area(
		string $name,
		array $bounds,
		string $unit_code,
		string $locality
	): array {
		$name = trim( $name );
		$locality = trim( $locality );
		self::assert_search_term( $name, 'invalid_road_name' );

		if ( '' !== $locality ) {
			self::assert_search_term( $locality, 'invalid_locality' );
		}

		$cache_key = 'psm_osm_' . hash(
			'sha256',
			wp_json_encode(
				array( 6, 'area', $unit_code, $name, $locality, $bounds )
			)
		);
		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$bbox = self::format_bbox( $bounds );
		$name_pattern = self::overpass_area_pattern( $name );
		$name_alias_pattern = self::overpass_area_alias_pattern( $name );

		if ( '' !== $locality ) {
			$locality_pattern = self::overpass_area_pattern( $locality );
			$locality_alias_pattern =
				self::overpass_area_alias_pattern( $locality );
			$scope_setup = self::overpass_area_scope_query(
				$locality_pattern,
				$locality_alias_pattern,
				$bbox
			);
			$scope = '(area.psmLocality)';
		} else {
			$scope_setup = '';
			$scope = '(' . $bbox . ')';
		}

		$query = sprintf(
			'[out:json][timeout:25][maxsize:67108864];%s(%s);out body geom;',
			$scope_setup,
			self::overpass_area_element_query(
				$name_pattern,
				$name_alias_pattern,
				$scope
			)
		);
		$elements = array();

		try {
			$response = $this->request_overpass( $query );
			$decoded = json_decode(
				wp_remote_retrieve_body( $response ),
				true,
				512,
				JSON_THROW_ON_ERROR
			);
			$elements = is_array( $decoded['elements'] ?? null )
				? $decoded['elements']
				: array();
		} catch ( JsonException | RuntimeException $exception ) {
			/*
			 * Nominatim is an exact-boundary fallback for temporary Overpass
			 * failure and incomplete locality indexing. Validation below still
			 * rejects points, circles and fuzzy administrative-name matches.
			 */
			$elements = array();
		}

		if ( count( $elements ) > self::MAX_AREA_RESULTS ) {
			throw new RuntimeException( 'too_many_results' );
		}

		$candidates = array();
		$seen = array();

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$osm_type = (string) ( $element['type'] ?? '' );
			$osm_id = absint( $element['id'] ?? 0 );
			$element_key = $osm_type . ':' . $osm_id;

			if (
				! in_array( $osm_type, array( 'relation', 'way' ), true )
				|| $osm_id < 1
				|| isset( $seen[ $element_key ] )
			) {
				continue;
			}
			$seen[ $element_key ] = true;

			$geometry = 'relation' === $osm_type
				? self::relation_geometry( $element )
				: self::way_polygon_geometry( $element );

			if ( null === $geometry ) {
				continue;
			}

			$point_count = self::geometry_point_count( $geometry );

			if ( $point_count > self::MAX_POINTS ) {
				continue;
			}

			$tags = is_array( $element['tags'] ?? null )
				? $element['tags']
				: array();
			$display_name = self::preferred_osm_name( $tags, $name );
			$feature = array(
				'type'       => 'Feature',
				'properties' => array(
					'name'        => $display_name,
					'description' => sprintf(
						'OpenStreetMap %s/%d',
						$osm_type,
						$osm_id
					),
				),
				'geometry'   => $geometry,
			);
			$geojson = Power_Schedule_Manager_GeoJSON::sanitize(
				(string) wp_json_encode(
					$feature,
					JSON_UNESCAPED_UNICODE
						| JSON_UNESCAPED_SLASHES
				)
			);
			$center = Power_Schedule_Manager_GeoJSON::calculate_center(
				$geojson
			);

			if ( ! self::point_in_bounds( $center, $bounds ) ) {
				continue;
			}

			$candidates[] = array(
				'id'           => 'osm-' . $osm_type . '-' . $osm_id,
				'geojson'      => $geojson,
				'centerLat'    => $center['lat'],
				'centerLng'    => $center['lng'],
				'wayIds'       => 'way' === $osm_type
					? array( $osm_id )
					: array(),
				'wayCount'     => 'way' === $osm_type
					? 1
					: count( (array) ( $element['members'] ?? array() ) ),
				'pointCount'   => $point_count,
				'searchType'   => 'area',
				'geometryType' => (string) $geometry['type'],
				'name'         => $display_name,
				'aliases'      => self::osm_area_aliases(
					$tags,
					$name,
					$display_name
				),
				'osmType'      => $osm_type,
				'osmId'        => $osm_id,
				'adminLevel'   => sanitize_text_field(
					(string) ( $tags['admin_level'] ?? '' )
				),
				'boundaryType' => sanitize_key(
					(string) (
						$tags['boundary']
						?? $tags['place']
						?? ''
					)
				),
			);
		}

		if ( array() === $candidates ) {
			$candidates = $this->fetch_nominatim_area_candidates(
				$name,
				$locality,
				$bounds
			);
		}

		if ( array() === $candidates ) {
			throw new RuntimeException( 'area_not_found' );
		}

		usort(
			$candidates,
			static function ( array $left, array $right ): int {
				$type_order = array( 'relation' => 0, 'way' => 1 );
				$comparison = ( $type_order[ $left['osmType'] ] ?? 2 )
					<=> ( $type_order[ $right['osmType'] ] ?? 2 );

				return 0 !== $comparison
					? $comparison
					: $right['pointCount'] <=> $left['pointCount'];
			}
		);

		$primary = $candidates[0];
		$result = array_merge(
			$primary,
			array(
				'pointCount' => (int) $primary['pointCount'],
				'candidates' => $candidates,
				'resultCount' => count( $candidates ),
				'provider'   => 'OpenStreetMap',
				'unitCode'   => $unit_code,
				'searchType' => 'area',
				'locality'   => $locality,
				'fetchedAt'  => gmdate( DATE_ATOM ),
			)
		);

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return $result;
	}

	/**
	 * Validate one exact-name search term.
	 *
	 * @param string $value Search term.
	 * @param string $error_code Stable validation error code.
	 * @return void
	 */
	private static function assert_search_term(
		string $value,
		string $error_code
	): void {
		$length = function_exists( 'mb_strlen' )
			? mb_strlen( $value )
			: strlen( $value );

		if ( $length < 2 || $length > 120 ) {
			throw new InvalidArgumentException( $error_code );
		}
	}

	/**
	 * Build an escaped, JSON-quoted exact regex for Overpass QL.
	 *
	 * @param string $value Literal OSM tag value.
	 * @return string
	 */
	private static function overpass_exact_pattern( string $value ): string {
		$pattern = '^' . preg_quote( $value, '/' ) . '$';
		return self::encode_overpass_pattern( $pattern );
	}

	/**
	 * Match a Vietnamese boundary with or without its administrative prefix.
	 *
	 * @param string $value User-entered boundary name.
	 * @return string
	 */
	private static function overpass_area_pattern( string $value ): string {
		$base_name = self::strip_vietnamese_admin_prefix( $value );
		$prefixes = 'Xã|Phường|Thị trấn|Thành phố|Quận|Huyện|'
			. 'Thị xã|Đặc khu';
		$pattern = '^((' . $prefixes . ')[[:space:]]+)?'
			. preg_quote( $base_name, '/' )
			. '$';

		return self::encode_overpass_pattern( $pattern );
	}

	/**
	 * Match an exact value inside semicolon-separated OSM alias tags.
	 *
	 * @param string $value User-entered boundary name.
	 * @return string
	 */
	private static function overpass_area_alias_pattern(
		string $value
	): string {
		$base_name = self::strip_vietnamese_admin_prefix( $value );
		$prefixes = 'Xã|Phường|Thị trấn|Thành phố|Quận|Huyện|'
			. 'Thị xã|Đặc khu';
		$pattern = '(^|;[[:space:]]*)(((' . $prefixes
			. ')[[:space:]]+)?'
			. preg_quote( $base_name, '/' )
			. ')([[:space:]]*;|$)';

		return self::encode_overpass_pattern( $pattern );
	}

	/**
	 * Remove one Vietnamese administrative prefix before exact matching.
	 *
	 * @param string $value Boundary name.
	 * @return string
	 */
	private static function strip_vietnamese_admin_prefix(
		string $value
	): string {
		$stripped = preg_replace(
			'/^(xã|phường|thị trấn|thành phố|quận|huyện|thị xã|đặc khu)\s+/iu',
			'',
			trim( $value )
		);

		return is_string( $stripped ) && '' !== trim( $stripped )
			? trim( $stripped )
			: trim( $value );
	}

	/**
	 * Build the parent-locality lookup across canonical and alias OSM tags.
	 *
	 * @param string $name_pattern Canonical exact-name pattern.
	 * @param string $alias_pattern Semicolon-aware alias pattern.
	 * @param string $bbox Bounding box.
	 * @return string
	 */
	private static function overpass_area_scope_query(
		string $name_pattern,
		string $alias_pattern,
		string $bbox
	): string {
		$canonical_tags = array(
			'name',
			'name:vi',
			'official_name',
			'short_name',
		);
		$alias_tags = array( 'alt_name', 'old_name' );
		$clauses = array();

		foreach ( $canonical_tags as $tag ) {
			$clauses[] = sprintf(
				'area["boundary"]["%s"~%s,i](%s);',
				$tag,
				$name_pattern,
				$bbox
			);
		}

		foreach ( $alias_tags as $tag ) {
			$clauses[] = sprintf(
				'area["boundary"]["%s"~%s,i](%s);',
				$tag,
				$alias_pattern,
				$bbox
			);
		}

		return '(' . implode( '', $clauses ) . ')->.psmLocality;';
	}

	/**
	 * Build relation/way queries for exact administrative boundaries.
	 *
	 * @param string $name_pattern Canonical exact-name pattern.
	 * @param string $alias_pattern Semicolon-aware alias pattern.
	 * @param string $scope Overpass area or bbox scope.
	 * @return string
	 */
	private static function overpass_area_element_query(
		string $name_pattern,
		string $alias_pattern,
		string $scope
	): string {
		$canonical_tags = array(
			'name',
			'name:vi',
			'official_name',
			'short_name',
		);
		$alias_tags = array( 'alt_name', 'old_name' );
		$clauses = array();

		foreach ( array( 'relation', 'way' ) as $element_type ) {
			foreach ( array( 'boundary', 'place' ) as $scope_tag ) {
				foreach ( $canonical_tags as $name_tag ) {
					$clauses[] = sprintf(
						'%s["%s"]["%s"~%s,i]%s;',
						$element_type,
						$scope_tag,
						$name_tag,
						$name_pattern,
						$scope
					);
				}

				foreach ( $alias_tags as $name_tag ) {
					$clauses[] = sprintf(
						'%s["%s"]["%s"~%s,i]%s;',
						$element_type,
						$scope_tag,
						$name_tag,
						$alias_pattern,
						$scope
					);
				}
			}
		}

		return implode( '', $clauses );
	}

	/**
	 * Prefer Vietnamese and official OSM labels for admin-facing results.
	 *
	 * @param array<string,mixed> $tags OSM tags.
	 * @param string              $fallback User-entered name.
	 * @return string
	 */
	private static function preferred_osm_name(
		array $tags,
		string $fallback
	): string {
		foreach (
			array(
				'name:vi',
				'name',
				'official_name',
				'short_name',
				'alt_name',
				'old_name',
			) as $tag
		) {
			$value = sanitize_text_field( (string) ( $tags[ $tag ] ?? '' ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return sanitize_text_field( $fallback );
	}

	/**
	 * Build aliases that let an exact OSM boundary match historical schedule text.
	 *
	 * A schedule may contain "Lộc Châu" while OSM stores
	 * "Phường Lộc Châu". Both forms, plus official/old/alternate names, are
	 * retained. Geometry still comes exclusively from the selected exact OSM
	 * Polygon/MultiPolygon; aliases never broaden the mapped boundary.
	 *
	 * @param array<string,mixed> $tags OSM tags.
	 * @param string              $requested_name User-entered name.
	 * @param string              $display_name Preferred OSM name.
	 * @return array<int,string>
	 */
	private static function osm_area_aliases(
		array $tags,
		string $requested_name,
		string $display_name
	): array {
		$values = array(
			$requested_name,
			$display_name,
			self::strip_vietnamese_admin_prefix( $requested_name ),
			self::strip_vietnamese_admin_prefix( $display_name ),
		);

		foreach (
			array(
				'name:vi',
				'name',
				'official_name',
				'short_name',
				'alt_name',
				'old_name',
			) as $tag
		) {
			$tag_value = (string) ( $tags[ $tag ] ?? '' );

			foreach ( preg_split( '/\s*;\s*/u', $tag_value ) ?: array() as $value ) {
				$values[] = $value;
				$values[] = self::strip_vietnamese_admin_prefix( $value );
			}
		}

		$aliases = array();
		$seen    = array();

		foreach ( $values as $value ) {
			$value = sanitize_text_field( (string) $value );
			$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );

			if ( '' === $value ) {
				continue;
			}

			$value = function_exists( 'mb_substr' )
				? mb_substr( $value, 0, 190, 'UTF-8' )
				: substr( $value, 0, 190 );
			$key   = strtolower( remove_accents( $value ) );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$aliases[]    = $value;
		}

		return $aliases;
	}

	/**
	 * Find exact administrative polygons through Nominatim.
	 *
	 * This is only a fallback when Overpass cannot return a usable boundary.
	 * The result must still be a named OSM way/relation, remain inside the
	 * configured electricity-unit bounds and contain Polygon/MultiPolygon
	 * geometry. Point and importance-only search results are rejected.
	 *
	 * @param string              $name Requested boundary name.
	 * @param string              $locality Optional parent locality.
	 * @param array<string,float> $bounds Electricity-unit search bounds.
	 * @return array<int,array<string,mixed>>
	 */
	private function fetch_nominatim_area_candidates(
		string $name,
		string $locality,
		array $bounds
	): array {
		$query = '' !== $locality
			? sprintf( '%s, %s, Việt Nam', $name, $locality )
			: sprintf( '%s, Việt Nam', $name );
		$url = add_query_arg(
			array(
				'q'               => $query,
				'format'          => 'jsonv2',
				'polygon_geojson' => 1,
				'addressdetails'  => 1,
				'namedetails'     => 1,
				'extratags'       => 1,
				'countrycodes'    => 'vn',
				'bounded'         => 1,
				'limit'           => 10,
				'viewbox'         => implode(
					',',
					array(
						$bounds['west'],
						$bounds['north'],
						$bounds['east'],
						$bounds['south'],
					)
				),
			),
			self::NOMINATIM_ENDPOINT
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 2,
				'reject_unsafe_urls' => true,
				'user-agent'  => sprintf(
					'PowerScheduleManager/%s (+%s)',
					defined( 'POWER_SCHEDULE_MANAGER_VERSION' )
						? POWER_SCHEDULE_MANAGER_VERSION
						: 'unknown',
					home_url( '/' )
				),
				'headers'     => array(
					'Accept' => 'application/json',
				),
			)
		);

		if (
			is_wp_error( $response )
			|| 200 !== wp_remote_retrieve_response_code( $response )
		) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );

		if ( '' === $body || strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
			return array();
		}

		try {
			$results = json_decode(
				$body,
				true,
				512,
				JSON_THROW_ON_ERROR
			);
		} catch ( JsonException $exception ) {
			return array();
		}

		if ( ! is_array( $results ) ) {
			return array();
		}

		$candidates = array();
		$seen       = array();

		foreach ( $results as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			$osm_type = sanitize_key( (string) ( $result['osm_type'] ?? '' ) );
			$osm_id   = absint( $result['osm_id'] ?? 0 );
			$geometry = is_array( $result['geojson'] ?? null )
				? $result['geojson']
				: array();
			$geometry_type = (string) ( $geometry['type'] ?? '' );

			if (
				! in_array( $osm_type, array( 'relation', 'way' ), true )
				|| $osm_id < 1
				|| ! in_array(
					$geometry_type,
					array( 'Polygon', 'MultiPolygon' ),
					true
				)
			) {
				continue;
			}

			$result_key = $osm_type . ':' . $osm_id;

			if ( isset( $seen[ $result_key ] ) ) {
				continue;
			}

			$aliases = self::nominatim_area_aliases( $result, $name );

			if ( ! self::area_name_matches( $name, $aliases ) ) {
				continue;
			}

			$display_name = self::preferred_nominatim_name(
				$result,
				$name
			);
			$feature = array(
				'type'       => 'Feature',
				'properties' => array(
					'name'        => $display_name,
					'description' => sprintf(
						'OpenStreetMap %s/%d',
						$osm_type,
						$osm_id
					),
				),
				'geometry'   => $geometry,
			);

			try {
				$geojson = Power_Schedule_Manager_GeoJSON::sanitize(
					(string) wp_json_encode(
						$feature,
						JSON_UNESCAPED_UNICODE
							| JSON_UNESCAPED_SLASHES
					)
				);
			} catch ( InvalidArgumentException | RuntimeException $exception ) {
				continue;
			}

			$point_count = self::geometry_point_count( $geometry );

			if ( $point_count < 4 || $point_count > self::MAX_POINTS ) {
				continue;
			}

			$center = Power_Schedule_Manager_GeoJSON::calculate_center(
				$geojson
			);

			if ( ! self::point_in_bounds( $center, $bounds ) ) {
				continue;
			}

			$extra_tags = is_array( $result['extratags'] ?? null )
				? $result['extratags']
				: array();
			$seen[ $result_key ] = true;
			$candidates[] = array(
				'id'           => 'nominatim-' . $osm_type . '-' . $osm_id,
				'geojson'      => $geojson,
				'centerLat'    => $center['lat'],
				'centerLng'    => $center['lng'],
				'wayIds'       => 'way' === $osm_type
					? array( $osm_id )
					: array(),
				'wayCount'     => 'way' === $osm_type ? 1 : 0,
				'pointCount'   => $point_count,
				'searchType'   => 'area',
				'geometryType' => $geometry_type,
				'name'         => $display_name,
				'aliases'      => $aliases,
				'osmType'      => $osm_type,
				'osmId'        => $osm_id,
				'adminLevel'   => sanitize_text_field(
					(string) ( $extra_tags['admin_level'] ?? '' )
				),
				'boundaryType' => sanitize_key(
					(string) (
						$result['type']
						?? $result['class']
						?? ''
					)
				),
			);
		}

		return $candidates;
	}

	/**
	 * Build aliases from one Nominatim result.
	 *
	 * @param array<string,mixed> $result Nominatim result.
	 * @param string              $requested_name Requested boundary name.
	 * @return array<int,string>
	 */
	private static function nominatim_area_aliases(
		array $result,
		string $requested_name
	): array {
		$values = array( $requested_name );
		$namedetails = is_array( $result['namedetails'] ?? null )
			? $result['namedetails']
			: array();
		$display_parts = preg_split(
			'/\s*,\s*/u',
			(string) ( $result['display_name'] ?? '' )
		);

		if ( is_array( $display_parts ) && isset( $display_parts[0] ) ) {
			$values[] = $display_parts[0];
		}

		foreach ( $namedetails as $detail ) {
			if ( is_scalar( $detail ) ) {
				$values[] = (string) $detail;
			}
		}

		$aliases = array();
		$seen    = array();

		foreach ( $values as $raw_value ) {
			foreach (
				preg_split( '/\s*;\s*/u', (string) $raw_value )
					?: array()
				as $value
			) {
				foreach (
					array(
						$value,
						self::strip_vietnamese_admin_prefix( $value ),
					) as $alias
				) {
					$alias = sanitize_text_field( $alias );
					$alias = trim(
						preg_replace( '/\s+/u', ' ', $alias ) ?? ''
					);

					if ( '' === $alias ) {
						continue;
					}

					$key = self::normalize_area_name( $alias );

					if ( '' === $key || isset( $seen[ $key ] ) ) {
						continue;
					}

					$seen[ $key ] = true;
					$aliases[]    = $alias;
				}
			}
		}

		return $aliases;
	}

	/**
	 * Select the clearest Vietnamese Nominatim label.
	 *
	 * @param array<string,mixed> $result Nominatim result.
	 * @param string              $fallback Fallback name.
	 * @return string
	 */
	private static function preferred_nominatim_name(
		array $result,
		string $fallback
	): string {
		$namedetails = is_array( $result['namedetails'] ?? null )
			? $result['namedetails']
			: array();

		foreach ( array( 'name:vi', 'name', 'official_name', 'short_name' ) as $key ) {
			$value = sanitize_text_field(
				(string) ( $namedetails[ $key ] ?? '' )
			);

			if ( '' !== $value ) {
				return $value;
			}
		}

		$display_parts = preg_split(
			'/\s*,\s*/u',
			(string) ( $result['display_name'] ?? '' )
		);
		$display_name = is_array( $display_parts )
			? sanitize_text_field( (string) ( $display_parts[0] ?? '' ) )
			: '';

		return '' !== $display_name
			? $display_name
			: sanitize_text_field( $fallback );
	}

	/**
	 * Require one exact normalized administrative name.
	 *
	 * @param string            $requested Requested name.
	 * @param array<int,string> $aliases Candidate names.
	 * @return bool
	 */
	private static function area_name_matches(
		string $requested,
		array $aliases
	): bool {
		$requested = self::normalize_area_name( $requested );

		if ( '' === $requested ) {
			return false;
		}

		foreach ( $aliases as $alias ) {
			if ( $requested === self::normalize_area_name( $alias ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize an administrative name without broad fuzzy matching.
	 *
	 * @param string $value Administrative label.
	 * @return string
	 */
	private static function normalize_area_name( string $value ): string {
		$value = self::strip_vietnamese_admin_prefix(
			sanitize_text_field( $value )
		);
		$value = remove_accents( $value );
		$value = function_exists( 'mb_strtolower' )
			? mb_strtolower( $value, 'UTF-8' )
			: strtolower( $value );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value ) ?? '';

		return trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
	}

	/**
	 * Safely quote one regex as an Overpass QL string literal.
	 *
	 * @param string $pattern Regex.
	 * @return string
	 */
	private static function encode_overpass_pattern(
		string $pattern
	): string {
		$encoded = wp_json_encode(
			$pattern,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			throw new InvalidArgumentException( 'invalid_road_name' );
		}

		return $encoded;
	}

	/**
	 * Format one validated bounding box for Overpass.
	 *
	 * @param array<string,float> $bounds Bounds.
	 * @return string
	 */
	private static function format_bbox( array $bounds ): string {
		return implode(
			',',
			array_map(
				static fn ( float $coordinate ): string =>
					number_format( $coordinate, 7, '.', '' ),
				array(
					$bounds['south'],
					$bounds['west'],
					$bounds['north'],
					$bounds['east'],
				)
			)
		);
	}

	/**
	 * Convert a closed OSM way into a Polygon.
	 *
	 * @param array<string,mixed> $element OSM element.
	 * @return array{type:string,coordinates:array<mixed>}|null
	 */
	private static function way_polygon_geometry( array $element ): ?array {
		$ring = self::osm_geometry_line( $element['geometry'] ?? null );

		if ( ! self::is_closed_ring( $ring ) ) {
			return null;
		}

		return array(
			'type'        => 'Polygon',
			'coordinates' => array( $ring ),
		);
	}

	/**
	 * Assemble an OSM boundary/multipolygon relation.
	 *
	 * @param array<string,mixed> $element OSM relation.
	 * @return array{type:string,coordinates:array<mixed>}|null
	 */
	private static function relation_geometry( array $element ): ?array {
		$members = is_array( $element['members'] ?? null )
			? $element['members']
			: array();
		$outer_fragments = array();
		$inner_fragments = array();

		foreach ( $members as $member ) {
			if (
				! is_array( $member )
				|| 'way' !== ( $member['type'] ?? '' )
			) {
				continue;
			}

			$line = self::osm_geometry_line(
				$member['geometry'] ?? null
			);

			if ( count( $line ) < 2 ) {
				continue;
			}

			if ( 'inner' === ( $member['role'] ?? '' ) ) {
				$inner_fragments[] = $line;
			} else {
				$outer_fragments[] = $line;
			}
		}

		$outers = self::stitch_rings( $outer_fragments );

		if (
			array() === $outers
			|| self::fragment_edge_count( $outer_fragments )
				!== self::fragment_edge_count( $outers )
		) {
			return null;
		}

		$polygons = array_map(
			static fn ( array $outer ): array => array( $outer ),
			$outers
		);

		$inners = self::stitch_rings( $inner_fragments );

		if (
			self::fragment_edge_count( $inner_fragments )
				!== self::fragment_edge_count( $inners )
		) {
			return null;
		}

		foreach ( $inners as $inner ) {
			$test_point = $inner[0];
			$assigned = false;

			foreach ( $outers as $outer_index => $outer ) {
				if ( self::point_in_ring( $test_point, $outer ) ) {
					$polygons[ $outer_index ][] = $inner;
					$assigned = true;
					break;
				}
			}

			if ( ! $assigned ) {
				return null;
			}
		}

		if ( 1 === count( $polygons ) ) {
			return array(
				'type'        => 'Polygon',
				'coordinates' => $polygons[0],
			);
		}

		return array(
			'type'        => 'MultiPolygon',
			'coordinates' => $polygons,
		);
	}

	/**
	 * Normalize Overpass geometry points to GeoJSON positions.
	 *
	 * @param mixed $geometry Raw Overpass geometry.
	 * @return array<int,array{0:float,1:float}>
	 */
	private static function osm_geometry_line( mixed $geometry ): array {
		if ( ! is_array( $geometry ) ) {
			return array();
		}

		$line = array();

		foreach ( $geometry as $point ) {
			if (
				! is_array( $point )
				|| ! is_numeric( $point['lat'] ?? null )
				|| ! is_numeric( $point['lon'] ?? null )
			) {
				return array();
			}

			$coordinate = array(
				round( (float) $point['lon'], 7 ),
				round( (float) $point['lat'], 7 ),
			);

			if ( array() === $line || end( $line ) !== $coordinate ) {
				$line[] = $coordinate;
			}
		}

		return $line;
	}

	/**
	 * Join relation way fragments into closed rings.
	 *
	 * @param array<int,array<int,array{0:float,1:float}>> $fragments Fragments.
	 * @return array<int,array<int,array{0:float,1:float}>>
	 */
	private static function stitch_rings( array $fragments ): array {
		$rings = array();
		$remaining = array_values( $fragments );

		while ( array() !== $remaining ) {
			$ring = array_shift( $remaining );

			while ( ! self::is_closed_ring( $ring ) ) {
				$tail_key = self::coordinate_key( end( $ring ) );
				$match_index = null;
				$match_line = array();

				foreach ( $remaining as $index => $fragment ) {
					if (
						$tail_key === self::coordinate_key(
							$fragment[0]
						)
					) {
						$match_index = $index;
						$match_line = $fragment;
						break;
					}

					if (
						$tail_key === self::coordinate_key(
							end( $fragment )
						)
					) {
						$match_index = $index;
						$match_line = array_reverse( $fragment );
						break;
					}
				}

				if ( null === $match_index ) {
					break;
				}

				unset( $remaining[ $match_index ] );
				$remaining = array_values( $remaining );
				$ring = array_merge( $ring, array_slice( $match_line, 1 ) );
			}

			if ( self::is_closed_ring( $ring ) ) {
				$rings[] = $ring;
			}
		}

		return $rings;
	}

	/**
	 * Count line edges to ensure no relation fragment was silently dropped.
	 *
	 * @param array<int,array<int,array{0:float,1:float}>> $fragments Lines.
	 * @return int
	 */
	private static function fragment_edge_count( array $fragments ): int {
		return array_sum(
			array_map(
				static fn ( array $fragment ): int =>
					max( 0, count( $fragment ) - 1 ),
				$fragments
			)
		);
	}

	/**
	 * Check GeoJSON ring closure and minimum size.
	 *
	 * @param array<int,array{0:float,1:float}> $ring Ring.
	 * @return bool
	 */
	private static function is_closed_ring( array $ring ): bool {
		return count( $ring ) >= 4
			&& self::coordinate_key( $ring[0] )
				=== self::coordinate_key( end( $ring ) );
	}

	/**
	 * Ray-casting point-in-polygon test used to assign inner rings.
	 *
	 * @param array{0:float,1:float}             $point Point.
	 * @param array<int,array{0:float,1:float}> $ring Ring.
	 * @return bool
	 */
	private static function point_in_ring( array $point, array $ring ): bool {
		$inside = false;
		$count = count( $ring );

		for ( $i = 0, $j = $count - 1; $i < $count; $j = $i++ ) {
			$xi = $ring[ $i ][0];
			$yi = $ring[ $i ][1];
			$xj = $ring[ $j ][0];
			$yj = $ring[ $j ][1];
			$intersects = ( $yi > $point[1] ) !== ( $yj > $point[1] )
				&& $point[0] < ( $xj - $xi )
					* ( $point[1] - $yi )
					/ ( $yj - $yi )
					+ $xi;

			if ( $intersects ) {
				$inside = ! $inside;
			}
		}

		return $inside;
	}

	/**
	 * Count positions in a generated Polygon/MultiPolygon.
	 *
	 * @param array<string,mixed> $geometry Geometry.
	 * @return int
	 */
	private static function geometry_point_count( array $geometry ): int {
		$count = 0;
		$walk = static function ( array $coordinates ) use ( &$count, &$walk ): void {
			if (
				isset( $coordinates[0], $coordinates[1] )
				&& is_numeric( $coordinates[0] )
				&& is_numeric( $coordinates[1] )
			) {
				++$count;
				return;
			}

			foreach ( $coordinates as $child ) {
				if ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};
		$walk( (array) ( $geometry['coordinates'] ?? array() ) );

		return $count;
	}

	/**
	 * Confirm a candidate center remains in the constrained unit viewport.
	 *
	 * @param array{lat:float,lng:float} $point Point.
	 * @param array<string,float>         $bounds Bounds.
	 * @return bool
	 */
	private static function point_in_bounds(
		array $point,
		array $bounds
	): bool {
		return $point['lat'] >= $bounds['south']
			&& $point['lat'] <= $bounds['north']
			&& $point['lng'] >= $bounds['west']
			&& $point['lng'] <= $bounds['east'];
	}

	/**
	 * Split OSM ways into connected components.
	 *
	 * Ways belong to the same candidate when they share a coordinate. This
	 * prevents unrelated roads with the same name from being silently merged.
	 *
	 * @param array<int,array{line:array<int,array{0:float,1:float}>,way_id:int}> $ways Ways.
	 * @return array<int,array<int,array{line:array<int,array{0:float,1:float}>,way_id:int}>>
	 */
	private static function connected_groups( array $ways ): array {
		$coordinate_owners = array();

		foreach ( $ways as $way_index => $way ) {
			foreach ( $way['line'] as $coordinate ) {
				$key = self::coordinate_key( $coordinate );
				$coordinate_owners[ $key ][] = $way_index;
			}
		}

		$adjacency = array_fill( 0, count( $ways ), array() );

		foreach ( $coordinate_owners as $owners ) {
			$owners = array_values( array_unique( $owners ) );

			foreach ( $owners as $owner ) {
				$adjacency[ $owner ] = array_values(
					array_unique(
						array_merge( $adjacency[ $owner ], $owners )
					)
				);
			}
		}

		$visited = array();
		$groups = array();

		foreach ( array_keys( $ways ) as $start ) {
			if ( isset( $visited[ $start ] ) ) {
				continue;
			}

			$queue = array( $start );
			$visited[ $start ] = true;
			$group = array();

			while ( array() !== $queue ) {
				$current = array_shift( $queue );
				$group[] = $ways[ $current ];

				foreach ( $adjacency[ $current ] as $neighbor ) {
					if ( isset( $visited[ $neighbor ] ) ) {
						continue;
					}

					$visited[ $neighbor ] = true;
					$queue[] = $neighbor;
				}
			}

			$groups[] = $group;
		}

		return $groups;
	}

	/**
	 * Build a stable coordinate key.
	 *
	 * @param array{0:float,1:float} $coordinate Longitude and latitude.
	 * @return string
	 */
	private static function coordinate_key( array $coordinate ): string {
		return number_format( $coordinate[0], 7, '.', '' )
			. ','
			. number_format( $coordinate[1], 7, '.', '' );
	}

	/**
	 * Request an allowlisted Overpass instance with a controlled fallback.
	 *
	 * @param string $query Valid Overpass QL query.
	 * @return array<string,mixed>
	 */
	private function request_overpass( string $query ): array {
		$saw_rate_limit = false;
		$failures = array();
		$endpoints = self::available_endpoints();

		if ( array() === $endpoints ) {
			throw new RuntimeException( 'overpass_unavailable' );
		}

		foreach ( $endpoints as $endpoint ) {
			$response = wp_safe_remote_post(
				$endpoint,
				array(
					'timeout'             => 28,
					'redirection'         => 0,
					'limit_response_size' => self::MAX_RESPONSE_BYTES,
					'headers'             => array(
						'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
						'Accept'       => 'application/json',
						'Accept-Encoding' => 'gzip, deflate',
						'User-Agent'   => 'Power-Schedule-Manager/' . POWER_SCHEDULE_MANAGER_VERSION
							. ' (' . home_url( '/' ) . ')',
					),
					'body'                => array( 'data' => $query ),
				)
			);

			if ( is_wp_error( $response ) ) {
				$failures[] = array(
					'endpoint' => (string) wp_parse_url(
						$endpoint,
						PHP_URL_HOST
					),
					'error'    => sanitize_key(
						$response->get_error_code()
					),
				);
				self::mark_endpoint_failed( $endpoint );
				continue;
			}

			$status = wp_remote_retrieve_response_code( $response );

			if ( 200 === $status ) {
				delete_transient(
					self::endpoint_failure_key( $endpoint )
				);

				return $response;
			}

			if ( 429 === $status ) {
				$saw_rate_limit = true;
			}

			$failures[] = array(
				'endpoint' => (string) wp_parse_url(
					$endpoint,
					PHP_URL_HOST
				),
				'status'   => $status,
			);

			/*
			 * Other 4xx responses mean the query was rejected. A different
			 * mirror cannot repair malformed input.
			 */
			if ( $status >= 400 && $status < 500 && 429 !== $status ) {
				throw new RuntimeException(
					'invalid_overpass_response'
				);
			}

			self::mark_endpoint_failed( $endpoint );
		}

		$exception = new RuntimeException(
			$saw_rate_limit
				? 'overpass_rate_limited'
				: 'overpass_unavailable'
		);

		Power_Schedule_Manager_Logger::error(
			'overpass_endpoints_exhausted',
			$exception,
			array(
				'failures' => $failures,
				'attempts' => count( $endpoints ),
			)
		);

		throw $exception;
	}

	/**
	 * Return endpoints not currently in a short failure backoff.
	 *
	 * When every endpoint is cooling down an empty array is returned. The
	 * caller fails fast until the two-minute backoff expires.
	 *
	 * @return array<int,string>
	 */
	private static function available_endpoints(): array {
		$available = array_values(
			array_filter(
				self::OVERPASS_ENDPOINTS,
				static fn ( string $endpoint ): bool =>
					false === get_transient(
						self::endpoint_failure_key( $endpoint )
					)
			)
		);

		return $available;
	}

	/**
	 * Mark one allowlisted endpoint as temporarily unhealthy.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @return void
	 */
	private static function mark_endpoint_failed( string $endpoint ): void {
		set_transient(
			self::endpoint_failure_key( $endpoint ),
			1,
			self::ENDPOINT_FAILURE_TTL
		);
	}

	/**
	 * Build a bounded transient key for endpoint health.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @return string
	 */
	private static function endpoint_failure_key( string $endpoint ): string {
		return 'psm_osm_fail_' . substr(
			hash( 'sha256', $endpoint ),
			0,
			20
		);
	}

	/**
	 * Validate a compact geographic search box.
	 *
	 * The maximum area prevents accidental country-wide Overpass queries.
	 *
	 * @return array<string,float>
	 */
	private static function sanitize_bounds(
		mixed $south,
		mixed $west,
		mixed $north,
		mixed $east
	): array {
		if (
			! is_numeric( $south )
			|| ! is_numeric( $west )
			|| ! is_numeric( $north )
			|| ! is_numeric( $east )
		) {
			throw new InvalidArgumentException( 'invalid_bounds' );
		}

		$bounds = array(
			'south' => (float) $south,
			'west'  => (float) $west,
			'north' => (float) $north,
			'east'  => (float) $east,
		);

		if (
			$bounds['south'] < -90
			|| $bounds['north'] > 90
			|| $bounds['west'] < -180
			|| $bounds['east'] > 180
			|| $bounds['south'] >= $bounds['north']
			|| $bounds['west'] >= $bounds['east']
			|| ( $bounds['north'] - $bounds['south'] ) > 1.5
			|| ( $bounds['east'] - $bounds['west'] ) > 1.5
		) {
			throw new InvalidArgumentException( 'invalid_bounds' );
		}

		return $bounds;
	}
}
