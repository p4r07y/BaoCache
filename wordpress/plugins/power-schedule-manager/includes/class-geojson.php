<?php
/**
 * Validate and normalize GeoJSON data.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * GeoJSON validation and normalization service.
 *
 * Security principles:
 * - Reject malformed JSON.
 * - Limit payload size and coordinate count.
 * - Only accept supported geometry types.
 * - Validate every longitude and latitude.
 * - Remove unapproved properties.
 * - Return canonical GeoJSON before database storage.
 *
 * A database location row represents one GeoJSON Feature. Multiple road
 * segments may be represented by separate rows or a MultiLineString.
 */
final class Power_Schedule_Manager_GeoJSON {

	/**
	 * Maximum GeoJSON payload size: 256 KiB.
	 */
	private const MAX_PAYLOAD_BYTES = 262144;

	/**
	 * Maximum number of coordinate positions in one feature.
	 */
	private const MAX_COORDINATE_POSITIONS = 5000;

	/**
	 * Maximum JSON nesting depth.
	 */
	private const MAX_JSON_DEPTH = 64;

	/**
	 * Minimum supported map zoom.
	 */
	private const MIN_ZOOM = 1;

	/**
	 * Maximum supported map zoom.
	 */
	private const MAX_ZOOM = 20;

	/**
	 * Supported GeoJSON geometry types.
	 *
	 * GeometryCollection is intentionally excluded because each location row
	 * should have one predictable geometry structure.
	 *
	 * @var array<string, true>
	 */
	private const ALLOWED_GEOMETRY_TYPES = array(
		'Point'           => true,
		'MultiPoint'      => true,
		'LineString'      => true,
		'MultiLineString' => true,
		'Polygon'         => true,
		'MultiPolygon'    => true,
	);

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Validate and normalize a GeoJSON payload.
	 *
	 * Accepted top-level objects:
	 * - Feature.
	 * - A supported Geometry object.
	 *
	 * Geometry objects are automatically wrapped in a Feature.
	 *
	 * @param string $geojson Raw GeoJSON.
	 *
	 * @return string Canonical GeoJSON Feature.
	 *
	 * @throws InvalidArgumentException When validation fails.
	 */
	public static function sanitize( string $geojson ): string {
		$feature = self::normalize_feature(
			self::decode( $geojson )
		);

		try {
			$encoded = wp_json_encode(
				$feature,
				JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_PRESERVE_ZERO_FRACTION
				| JSON_THROW_ON_ERROR,
				self::MAX_JSON_DEPTH
			);
		} catch ( JsonException $exception ) {
			throw new InvalidArgumentException(
				__( 'Không thể mã hóa dữ liệu bản đồ.', 'power-schedule-manager' ),
				0,
				$exception
			);
		}

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			throw new InvalidArgumentException(
				__( 'Không thể mã hóa dữ liệu bản đồ.', 'power-schedule-manager' )
			);
		}

		if ( strlen( $encoded ) > self::MAX_PAYLOAD_BYTES ) {
			throw new InvalidArgumentException(
				__( 'Dữ liệu bản đồ sau khi chuẩn hóa vượt quá giới hạn cho phép.', 'power-schedule-manager' )
			);
		}

		return $encoded;
	}

	/**
	 * Decode a GeoJSON string.
	 *
	 * @param string $geojson Raw GeoJSON.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When JSON is empty or invalid.
	 */
	public static function decode( string $geojson ): array {
		$geojson = trim( $geojson );

		if ( '' === $geojson ) {
			throw new InvalidArgumentException(
				__( 'Dữ liệu bản đồ không được để trống.', 'power-schedule-manager' )
			);
		}

		if ( strlen( $geojson ) > self::MAX_PAYLOAD_BYTES ) {
			throw new InvalidArgumentException(
				__( 'Dữ liệu bản đồ vượt quá giới hạn 256 KiB.', 'power-schedule-manager' )
			);
		}

		try {
			$decoded = json_decode(
				$geojson,
				true,
				self::MAX_JSON_DEPTH,
				JSON_THROW_ON_ERROR
			);
		} catch ( JsonException $exception ) {
			throw new InvalidArgumentException(
				__( 'Dữ liệu GeoJSON không hợp lệ.', 'power-schedule-manager' ),
				0,
				$exception
			);
		}

		if ( ! is_array( $decoded ) ) {
			throw new InvalidArgumentException(
				__( 'GeoJSON phải là một đối tượng JSON.', 'power-schedule-manager' )
			);
		}

		return $decoded;
	}

	/**
	 * Determine whether a payload is valid.
	 *
	 * This method is intended for simple conditional checks. Use sanitize()
	 * when the validation error must be displayed to an administrator.
	 *
	 * @param string $geojson Raw GeoJSON.
	 *
	 * @return bool
	 */
	public static function is_valid( string $geojson ): bool {
		try {
			self::sanitize( $geojson );

			return true;
		} catch ( InvalidArgumentException ) {
			return false;
		}
	}

	/**
	 * Get the geometry type from a validated payload.
	 *
	 * @param string $geojson Raw or normalized GeoJSON.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When validation fails.
	 */
	public static function geometry_type( string $geojson ): string {
		$feature  = self::normalize_feature( self::decode( $geojson ) );
		$geometry = $feature['geometry'];

		return (string) $geometry['type'];
	}

	/**
	 * Calculate the center of a geometry's bounding box.
	 *
	 * GeoJSON positions use [longitude, latitude]. This method returns values
	 * named for direct use by Leaflet and the database.
	 *
	 * @param string $geojson Raw or normalized GeoJSON.
	 *
	 * @return array{lat: float, lng: float}
	 *
	 * @throws InvalidArgumentException When validation fails.
	 */
	public static function calculate_center( string $geojson ): array {
		$feature     = self::normalize_feature( self::decode( $geojson ) );
		$coordinates = array();

		self::collect_positions(
			$feature['geometry']['coordinates'],
			$coordinates
		);

		if ( array() === $coordinates ) {
			throw new InvalidArgumentException(
				__( 'Không tìm thấy tọa độ hợp lệ trong dữ liệu bản đồ.', 'power-schedule-manager' )
			);
		}

		$longitudes = array_column( $coordinates, 0 );
		$latitudes  = array_column( $coordinates, 1 );

		$longitude = ( min( $longitudes ) + max( $longitudes ) ) / 2;
		$latitude  = ( min( $latitudes ) + max( $latitudes ) ) / 2;

		return array(
			'lat' => round( $latitude, 7 ),
			'lng' => round( $longitude, 7 ),
		);
	}

	/**
	 * Validate manually supplied center coordinates.
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 *
	 * @return array{lat: float, lng: float}
	 *
	 * @throws InvalidArgumentException When coordinates are invalid.
	 */
	public static function sanitize_center(
		float $latitude,
		float $longitude
	): array {
		self::assert_finite_number( $latitude, 'latitude' );
		self::assert_finite_number( $longitude, 'longitude' );

		if ( $latitude < -90 || $latitude > 90 ) {
			throw new InvalidArgumentException(
				__( 'Vĩ độ phải nằm trong khoảng từ -90 đến 90.', 'power-schedule-manager' )
			);
		}

		if ( $longitude < -180 || $longitude > 180 ) {
			throw new InvalidArgumentException(
				__( 'Kinh độ phải nằm trong khoảng từ -180 đến 180.', 'power-schedule-manager' )
			);
		}

		return array(
			'lat' => round( $latitude, 7 ),
			'lng' => round( $longitude, 7 ),
		);
	}

	/**
	 * Normalize a map zoom value.
	 *
	 * @param int $zoom Requested zoom.
	 *
	 * @return int
	 */
	public static function sanitize_zoom( int $zoom ): int {
		return min(
			self::MAX_ZOOM,
			max( self::MIN_ZOOM, $zoom )
		);
	}

	/**
	 * Normalize a GeoJSON Feature or Geometry.
	 *
	 * @param array<string, mixed> $data Decoded GeoJSON.
	 *
	 * @return array{
	 *     type: string,
	 *     properties: array<string, string>,
	 *     geometry: array{type: string, coordinates: array<mixed>}
	 * }
	 *
	 * @throws InvalidArgumentException When the structure is invalid.
	 */
	private static function normalize_feature( array $data ): array {
		$type = isset( $data['type'] ) && is_string( $data['type'] )
			? $data['type']
			: '';

		if ( 'FeatureCollection' === $type ) {
			throw new InvalidArgumentException(
				__(
					'Mỗi vị trí chỉ được chứa một Feature. Hãy tách FeatureCollection thành nhiều vị trí.',
					'power-schedule-manager'
				)
			);
		}

		if ( 'GeometryCollection' === $type ) {
			throw new InvalidArgumentException(
				__(
					'GeometryCollection chưa được hỗ trợ. Hãy dùng một kiểu hình học cụ thể.',
					'power-schedule-manager'
				)
			);
		}

		if ( 'Feature' === $type ) {
			if (
				! isset( $data['geometry'] )
				|| ! is_array( $data['geometry'] )
			) {
				throw new InvalidArgumentException(
					__( 'GeoJSON Feature thiếu geometry hợp lệ.', 'power-schedule-manager' )
				);
			}

			$geometry   = self::normalize_geometry( $data['geometry'] );
			$properties = self::sanitize_properties(
				isset( $data['properties'] ) && is_array( $data['properties'] )
					? $data['properties']
					: array()
			);

			return array(
				'type'       => 'Feature',
				'properties' => $properties,
				'geometry'   => $geometry,
			);
		}

		if ( isset( self::ALLOWED_GEOMETRY_TYPES[ $type ] ) ) {
			return array(
				'type'       => 'Feature',
				'properties' => array(),
				'geometry'   => self::normalize_geometry( $data ),
			);
		}

		throw new InvalidArgumentException(
			__(
				'GeoJSON phải là Feature hoặc một kiểu hình học được hỗ trợ.',
				'power-schedule-manager'
			)
		);
	}

	/**
	 * Normalize and validate a GeoJSON Geometry.
	 *
	 * @param array<string, mixed> $geometry Geometry data.
	 *
	 * @return array{type: string, coordinates: array<mixed>}
	 *
	 * @throws InvalidArgumentException When geometry is invalid.
	 */
	private static function normalize_geometry( array $geometry ): array {
		$type = isset( $geometry['type'] ) && is_string( $geometry['type'] )
			? $geometry['type']
			: '';

		if ( ! isset( self::ALLOWED_GEOMETRY_TYPES[ $type ] ) ) {
			throw new InvalidArgumentException(
				__( 'Kiểu hình học GeoJSON không được hỗ trợ.', 'power-schedule-manager' )
			);
		}

		if (
			! array_key_exists( 'coordinates', $geometry )
			|| ! is_array( $geometry['coordinates'] )
		) {
			throw new InvalidArgumentException(
				__( 'Geometry thiếu mảng coordinates.', 'power-schedule-manager' )
			);
		}

		$coordinates = self::normalize_coordinates(
			$type,
			$geometry['coordinates']
		);

		$position_count = 0;
		self::count_positions( $coordinates, $position_count );

		if ( $position_count > self::MAX_COORDINATE_POSITIONS ) {
			throw new InvalidArgumentException(
				__(
					'Dữ liệu bản đồ chứa quá nhiều điểm tọa độ.',
					'power-schedule-manager'
				)
			);
		}

		return array(
			'type'        => $type,
			'coordinates' => $coordinates,
		);
	}

	/**
	 * Validate coordinates according to the geometry type.
	 *
	 * @param string       $type        Geometry type.
	 * @param array<mixed> $coordinates Coordinates.
	 *
	 * @return array<mixed>
	 */
	private static function normalize_coordinates(
		string $type,
		array $coordinates
	): array {
		return match ( $type ) {
			'Point'           => self::normalize_position( $coordinates ),
			'MultiPoint'      => self::normalize_position_list(
				$coordinates,
				1,
				'MultiPoint'
			),
			'LineString'      => self::normalize_position_list(
				$coordinates,
				2,
				'LineString'
			),
			'MultiLineString' => self::normalize_multi_line_string( $coordinates ),
			'Polygon'         => self::normalize_polygon( $coordinates ),
			'MultiPolygon'    => self::normalize_multi_polygon( $coordinates ),
			default           => throw new InvalidArgumentException(
				__( 'Kiểu hình học GeoJSON không được hỗ trợ.', 'power-schedule-manager' )
			),
		};
	}

	/**
	 * Normalize one GeoJSON position.
	 *
	 * Only longitude and latitude are stored. Optional altitude or additional
	 * values are removed to keep map calculations predictable.
	 *
	 * @param array<mixed> $position Position.
	 *
	 * @return array{0: float, 1: float}
	 */
	private static function normalize_position( array $position ): array {
		if (
			count( $position ) < 2
			|| ! is_int( $position[0] ) && ! is_float( $position[0] )
			|| ! is_int( $position[1] ) && ! is_float( $position[1] )
		) {
			throw new InvalidArgumentException(
				__(
					'Mỗi tọa độ phải có kinh độ và vĩ độ dạng số.',
					'power-schedule-manager'
				)
			);
		}

		$longitude = (float) $position[0];
		$latitude  = (float) $position[1];

		self::assert_finite_number( $longitude, 'longitude' );
		self::assert_finite_number( $latitude, 'latitude' );

		if ( $longitude < -180 || $longitude > 180 ) {
			throw new InvalidArgumentException(
				__( 'Kinh độ phải nằm trong khoảng từ -180 đến 180.', 'power-schedule-manager' )
			);
		}

		if ( $latitude < -90 || $latitude > 90 ) {
			throw new InvalidArgumentException(
				__( 'Vĩ độ phải nằm trong khoảng từ -90 đến 90.', 'power-schedule-manager' )
			);
		}

		return array(
			round( $longitude, 7 ),
			round( $latitude, 7 ),
		);
	}

	/**
	 * Normalize a list of positions.
	 *
	 * @param array<mixed> $positions     Positions.
	 * @param int          $minimum_count Minimum position count.
	 * @param string       $context       Geometry name for errors.
	 *
	 * @return array<int, array{0: float, 1: float}>
	 */
	private static function normalize_position_list(
		array $positions,
		int $minimum_count,
		string $context
	): array {
		if ( count( $positions ) < $minimum_count ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: 1: Geometry type, 2: Minimum coordinate count. */
					__(
						'%1$s cần ít nhất %2$d điểm tọa độ.',
						'power-schedule-manager'
					),
					$context,
					$minimum_count
				)
			);
		}

		$normalized = array();

		foreach ( $positions as $position ) {
			if ( ! is_array( $position ) ) {
				throw new InvalidArgumentException(
					__( 'Cấu trúc tọa độ GeoJSON không hợp lệ.', 'power-schedule-manager' )
				);
			}

			$normalized[] = self::normalize_position( $position );
		}

		return $normalized;
	}

	/**
	 * Normalize a MultiLineString.
	 *
	 * @param array<mixed> $lines Lines.
	 *
	 * @return array<int, array<int, array{0: float, 1: float}>>
	 */
	private static function normalize_multi_line_string( array $lines ): array {
		if ( array() === $lines ) {
			throw new InvalidArgumentException(
				__( 'MultiLineString phải chứa ít nhất một đường.', 'power-schedule-manager' )
			);
		}

		$normalized = array();

		foreach ( $lines as $line ) {
			if ( ! is_array( $line ) ) {
				throw new InvalidArgumentException(
					__( 'Cấu trúc MultiLineString không hợp lệ.', 'power-schedule-manager' )
				);
			}

			$normalized[] = self::normalize_position_list(
				$line,
				2,
				'LineString'
			);
		}

		return $normalized;
	}

	/**
	 * Normalize a Polygon.
	 *
	 * @param array<mixed> $rings Polygon rings.
	 *
	 * @return array<int, array<int, array{0: float, 1: float}>>
	 */
	private static function normalize_polygon( array $rings ): array {
		if ( array() === $rings ) {
			throw new InvalidArgumentException(
				__( 'Polygon phải chứa ít nhất một vòng tọa độ.', 'power-schedule-manager' )
			);
		}

		$normalized = array();

		foreach ( $rings as $ring ) {
			if ( ! is_array( $ring ) ) {
				throw new InvalidArgumentException(
					__( 'Cấu trúc Polygon không hợp lệ.', 'power-schedule-manager' )
				);
			}

			$normalized_ring = self::normalize_position_list(
				$ring,
				4,
				'Polygon'
			);

			if (
				$normalized_ring[0]
				!== $normalized_ring[ count( $normalized_ring ) - 1 ]
			) {
				throw new InvalidArgumentException(
					__(
						'Vòng Polygon phải khép kín: điểm đầu và điểm cuối phải giống nhau.',
						'power-schedule-manager'
					)
				);
			}

			$normalized[] = $normalized_ring;
		}

		return $normalized;
	}

	/**
	 * Normalize a MultiPolygon.
	 *
	 * @param array<mixed> $polygons Polygons.
	 *
	 * @return array<int, array<int, array<int, array{0: float, 1: float}>>>
	 */
	private static function normalize_multi_polygon( array $polygons ): array {
		if ( array() === $polygons ) {
			throw new InvalidArgumentException(
				__( 'MultiPolygon phải chứa ít nhất một Polygon.', 'power-schedule-manager' )
			);
		}

		$normalized = array();

		foreach ( $polygons as $polygon ) {
			if ( ! is_array( $polygon ) ) {
				throw new InvalidArgumentException(
					__( 'Cấu trúc MultiPolygon không hợp lệ.', 'power-schedule-manager' )
				);
			}

			$normalized[] = self::normalize_polygon( $polygon );
		}

		return $normalized;
	}

	/**
	 * Sanitize approved Feature properties.
	 *
	 * Styling properties are intentionally removed. Map colors and styles will
	 * be controlled by the plugin according to event status.
	 *
	 * @param array<string, mixed> $properties Raw properties.
	 *
	 * @return array<string, string>
	 */
	private static function sanitize_properties( array $properties ): array {
		$sanitized = array();

		if ( isset( $properties['name'] ) && is_scalar( $properties['name'] ) ) {
			$name = sanitize_text_field( (string) $properties['name'] );

			if ( '' !== $name ) {
				$sanitized['name'] = self::limit_text( $name, 191 );
			}
		}

		if (
			isset( $properties['description'] )
			&& is_scalar( $properties['description'] )
		) {
			$description = sanitize_textarea_field(
				(string) $properties['description']
			);

			if ( '' !== $description ) {
				$sanitized['description'] = self::limit_text(
					$description,
					1000
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Count coordinate positions recursively.
	 *
	 * @param array<mixed> $coordinates Coordinates.
	 * @param int          $count       Current count.
	 *
	 * @return void
	 */
	private static function count_positions(
		array $coordinates,
		int &$count
	): void {
		if ( self::is_position_array( $coordinates ) ) {
			++$count;

			if ( $count > self::MAX_COORDINATE_POSITIONS ) {
				throw new InvalidArgumentException(
					__(
						'Dữ liệu bản đồ chứa quá nhiều điểm tọa độ.',
						'power-schedule-manager'
					)
				);
			}

			return;
		}

		foreach ( $coordinates as $child ) {
			if ( ! is_array( $child ) ) {
				throw new InvalidArgumentException(
					__( 'Cấu trúc tọa độ GeoJSON không hợp lệ.', 'power-schedule-manager' )
				);
			}

			self::count_positions( $child, $count );
		}
	}

	/**
	 * Collect coordinate positions recursively.
	 *
	 * @param array<mixed>                         $coordinates Coordinates.
	 * @param array<int, array{0: float, 1: float}> $positions   Collected positions.
	 *
	 * @return void
	 */
	private static function collect_positions(
		array $coordinates,
		array &$positions
	): void {
		if ( self::is_position_array( $coordinates ) ) {
			$positions[] = array(
				(float) $coordinates[0],
				(float) $coordinates[1],
			);

			return;
		}

		foreach ( $coordinates as $child ) {
			if ( is_array( $child ) ) {
				self::collect_positions( $child, $positions );
			}
		}
	}

	/**
	 * Check whether an array represents one coordinate position.
	 *
	 * @param array<mixed> $value Value.
	 *
	 * @return bool
	 */
	private static function is_position_array( array $value ): bool {
		return isset( $value[0], $value[1] )
			&& ( is_int( $value[0] ) || is_float( $value[0] ) )
			&& ( is_int( $value[1] ) || is_float( $value[1] ) );
	}

	/**
	 * Ensure a floating-point value is finite.
	 *
	 * @param float  $value   Value.
	 * @param string $context Value name.
	 *
	 * @return void
	 */
	private static function assert_finite_number(
		float $value,
		string $context
	): void {
		if ( is_nan( $value ) || is_infinite( $value ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: Coordinate field name. */
					__( 'Giá trị %s không phải là một số hữu hạn.', 'power-schedule-manager' ),
					$context
				)
			);
		}
	}

	/**
	 * Limit text length safely.
	 *
	 * @param string $text   Text.
	 * @param int    $length Maximum character count.
	 *
	 * @return string
	 */
	private static function limit_text( string $text, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $length );
		}

		return substr( $text, 0, $length );
	}
}
