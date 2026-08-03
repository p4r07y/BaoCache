<?php
/**
 * Electrical service unit management.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages electrical service units.
 *
 * The custom database table is the authoritative source for unit
 * configuration. WordPress taxonomy terms provide public archives and
 * SEO-friendly URLs.
 */
final class Power_Schedule_Manager_Units {

	/**
	 * Maximum unit code length.
	 */
	private const int MAX_CODE_LENGTH = 32;

	/**
	 * Maximum unit name length.
	 */
	private const int MAX_NAME_LENGTH = 191;

	/**
	 * Maximum region length.
	 */
	private const int MAX_REGION_LENGTH = 191;

	/**
	 * Maximum source URL length.
	 */
	private const int MAX_SOURCE_URL_LENGTH = 2048;

	/**
	 * Maximum metadata JSON size: 64 KiB.
	 */
	private const int MAX_METADATA_BYTES = 65536;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_init',
			array( $this, 'maybe_upgrade_seed_data' ),
			20
		);
	}

	/**
	 * Install or update default electrical service units.
	 *
	 * This operation is idempotent because units are upserted using their
	 * unique unit code.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When unit installation fails.
	 */
	public static function install_or_update(): void {
		$units = self::default_units();

		foreach ( $units as $unit ) {
			$normalized = self::normalize_unit( $unit );

			self::upsert( $normalized );
			self::synchronize_taxonomy_term( $normalized );
		}

		update_option(
			POWER_SCHEDULE_MANAGER_SEED_OPTION,
			POWER_SCHEDULE_MANAGER_SEED_VERSION,
			false
		);
	}

	/**
	 * Upgrade seed data when plugin files are updated without reactivation.
	 *
	 * @return void
	 */
	public function maybe_upgrade_seed_data(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_UNITS
			)
		) {
			return;
		}

		$installed_version = get_option(
			POWER_SCHEDULE_MANAGER_SEED_OPTION,
			'0.0.0'
		);

		if ( ! is_string( $installed_version ) ) {
			$installed_version = '0.0.0';
		}

		if (
			version_compare(
				$installed_version,
				POWER_SCHEDULE_MANAGER_SEED_VERSION,
				'>='
			)
		) {
			return;
		}

		try {
			self::install_or_update();
		} catch ( Throwable $exception ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'Cúp Điện Lâm Đồng unit upgrade failed: %s',
						$exception->getMessage()
					)
				);
			}

			add_action(
				'admin_notices',
				array( self::class, 'render_upgrade_error_notice' )
			);
		}
	}

	/**
	 * Render a generic seed upgrade error.
	 *
	 * Internal database details are intentionally not displayed.
	 *
	 * @return void
	 */
	public static function render_upgrade_error_notice(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_UNITS
			)
		) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Cúp Điện Lâm Đồng không thể cập nhật danh mục đơn vị điện lực. Vui lòng kiểm tra database log.',
				'power-schedule-manager'
			)
		);
	}

	/**
	 * Load default unit data.
	 *
	 * Site-specific unit data is kept outside this service class so it can be
	 * maintained without changing database logic.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_units(): array {
		$units     = array();
		$data_file = POWER_SCHEDULE_MANAGER_PATH
			. 'includes/data/default-units.php';

		if ( is_file( $data_file ) && is_readable( $data_file ) ) {
			$loaded = require $data_file;

			if ( is_array( $loaded ) ) {
				$units = $loaded;
			}
		}

		/**
		 * Filters the default electrical service units.
		 *
		 * @param array<int, array<string, mixed>> $units Default units.
		 */
		$units = apply_filters(
			'power_schedule_manager_default_units',
			$units
		);

		if ( ! is_array( $units ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$units,
				'is_array'
			)
		);
	}

	/**
	 * Insert or update an electrical service unit.
	 *
	 * @param array<string, mixed> $unit Raw or normalized unit data.
	 *
	 * @return int Database row ID.
	 *
	 * @throws RuntimeException When the database operation fails.
	 */
	public static function upsert( array $unit ): int {
		global $wpdb;

		$unit  = self::normalize_unit( $unit );
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);
		$now       = Power_Schedule_Manager_Database::utc_now();
		$row_alias =
			Power_Schedule_Manager_Database::upsert_row_alias();

		$name_value =
			Power_Schedule_Manager_Database::upsert_value(
				'name'
			);
		$slug_value = Power_Schedule_Manager_Database::upsert_value(
			'slug'
		);
		$parent_code_value =
			Power_Schedule_Manager_Database::upsert_value(
				'parent_code'
			);
		$region_value = Power_Schedule_Manager_Database::upsert_value(
			'region'
		);
		$source_url_value =
			Power_Schedule_Manager_Database::upsert_value(
				'source_url'
			);
		$timezone_value =
			Power_Schedule_Manager_Database::upsert_value(
				'timezone'
			);
		$is_public_value =
			Power_Schedule_Manager_Database::upsert_value(
				'is_public'
			);
		$sort_order_value =
			Power_Schedule_Manager_Database::upsert_value(
				'sort_order'
			);
		$metadata_value =
			Power_Schedule_Manager_Database::upsert_value(
				'metadata'
			);
		$updated_at_value =
			Power_Schedule_Manager_Database::upsert_value(
				'updated_at_utc'
			);

		$sql = $wpdb->prepare(
			"INSERT INTO {$table}
				(
					code,
					name,
					slug,
					parent_code,
					region,
					source_url,
					timezone,
					is_public,
					sort_order,
					metadata,
					created_at_utc,
					updated_at_utc
				)
			VALUES
				(
					%s,
					%s,
					%s,
					%s,
					%s,
					%s,
					%s,
					%d,
					%d,
					%s,
					%s,
					%s
				){$row_alias}
			ON DUPLICATE KEY UPDATE
				name = {$name_value},
				slug = {$slug_value},
				parent_code = {$parent_code_value},
				region = {$region_value},
				source_url = {$source_url_value},
				timezone = {$timezone_value},
				is_public = {$is_public_value},
				sort_order = {$sort_order_value},
				metadata = {$metadata_value},
				updated_at_utc = {$updated_at_value}",
			$unit['code'],
			$unit['name'],
			$unit['slug'],
			$unit['parent_code'],
			$unit['region'],
			$unit['source_url'],
			$unit['timezone'],
			$unit['is_public'] ? 1 : 0,
			$unit['sort_order'],
			$unit['metadata_json'],
			$now,
			$now
		);

		$result = $wpdb->query( $sql );

		if ( false === $result ) {
			throw new RuntimeException(
				__( 'Không thể lưu đơn vị điện lực.', 'power-schedule-manager' )
			);
		}

		$stored = self::find_by_code( $unit['code'] );

		if ( null === $stored ) {
			throw new RuntimeException(
				__(
					'Không thể xác minh đơn vị điện lực vừa lưu.',
					'power-schedule-manager'
				)
			);
		}

		return (int) $stored['id'];
	}

	/**
	 * Find a unit using its unique code.
	 *
	 * @param string $code Unit code.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_by_code( string $code ): ?array {
		global $wpdb;

		$code = self::sanitize_code( $code );

		if ( '' === $code ) {
			return null;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					id,
					code,
					name,
					slug,
					parent_code,
					region,
					source_url,
					timezone,
					is_public,
					sort_order,
					metadata,
					created_at_utc,
					updated_at_utc
				FROM {$table}
				WHERE code = %s
				LIMIT 1",
				$code
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::cast_database_row( $row );
	}

	/**
	 * Find a unit using its public slug.
	 *
	 * @param string $slug Public slug.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_by_slug( string $slug ): ?array {
		global $wpdb;

		$slug = sanitize_title( $slug );

		if ( '' === $slug ) {
			return null;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					id,
					code,
					name,
					slug,
					parent_code,
					region,
					source_url,
					timezone,
					is_public,
					sort_order,
					metadata,
					created_at_utc,
					updated_at_utc
				FROM {$table}
				WHERE slug = %s
				LIMIT 1",
				$slug
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::cast_database_row( $row );
	}

	/**
	 * Return all units.
	 *
	 * @param bool $public_only Return only publicly visible units.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all( bool $public_only = false ): array {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		if ( $public_only ) {
			$rows = $wpdb->get_results(
				"SELECT
					id,
					code,
					name,
					slug,
					parent_code,
					region,
					source_url,
					timezone,
					is_public,
					sort_order,
					metadata,
					created_at_utc,
					updated_at_utc
				FROM {$table}
				WHERE is_public = 1
				ORDER BY sort_order ASC, name ASC",
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT
					id,
					code,
					name,
					slug,
					parent_code,
					region,
					source_url,
					timezone,
					is_public,
					sort_order,
					metadata,
					created_at_utc,
					updated_at_utc
				FROM {$table}
				ORDER BY sort_order ASC, name ASC",
				ARRAY_A
			);
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			array( self::class, 'cast_database_row' ),
			$rows
		);
	}

	/**
	 * Check whether a unit is publicly visible.
	 *
	 * @param string $code Unit code.
	 *
	 * @return bool
	 */
	public static function is_public( string $code ): bool {
		$unit = self::find_by_code( $code );

		return null !== $unit && true === $unit['is_public'];
	}

	/**
	 * Synchronize a unit with its WordPress taxonomy term.
	 *
	 * @param array<string, mixed> $unit Raw or normalized unit.
	 *
	 * @return int Term ID.
	 *
	 * @throws RuntimeException When term synchronization fails.
	 */
	public static function synchronize_taxonomy_term( array $unit ): int {
		$unit = self::normalize_unit( $unit );

		if (
			! taxonomy_exists(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			throw new RuntimeException(
				__(
					'Taxonomy khu vực điện lực chưa được đăng ký.',
					'power-schedule-manager'
				)
			);
		}

		$term_id = self::find_term_id_by_unit_code(
			$unit['code']
		);

		$term_arguments = array(
			'slug'        => $unit['slug'],
			'description' => self::build_term_description( $unit ),
		);

		if ( null === $term_id ) {
			$result = wp_insert_term(
				$unit['name'],
				Power_Schedule_Manager_Taxonomy::TAXONOMY,
				$term_arguments
			);
		} else {
			$result = wp_update_term(
				$term_id,
				Power_Schedule_Manager_Taxonomy::TAXONOMY,
				array_merge(
					$term_arguments,
					array(
						'name' => $unit['name'],
					)
				)
			);
		}

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: WordPress taxonomy error. */
					__(
						'Không thể đồng bộ khu vực điện lực: %s',
						'power-schedule-manager'
					),
					$result->get_error_message()
				)
			);
		}

		$term_id = isset( $result['term_id'] )
			? absint( $result['term_id'] )
			: 0;

		if ( $term_id < 1 ) {
			throw new RuntimeException(
				__( 'WordPress không trả về term ID hợp lệ.', 'power-schedule-manager' )
			);
		}

		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
			$unit['code']
		);

		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_IS_PUBLIC,
			$unit['is_public']
		);

		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_DISPLAY_ORDER,
			$unit['sort_order']
		);

		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_SHORT_DESCRIPTION,
			self::build_term_description( $unit )
		);

		return $term_id;
	}

	/**
	 * Find the taxonomy term linked to a unit code.
	 *
	 * @param string $code Unit code.
	 *
	 * @return int|null
	 */
	public static function find_term_id_by_unit_code(
		string $code
	): ?int {
		$code = self::sanitize_code( $code );

		if ( '' === $code ) {
			return null;
		}

		$term_ids = get_terms(
			array(
				'taxonomy'   => Power_Schedule_Manager_Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'     => Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
						'value'   => $code,
						'compare' => '=',
					),
				),
			)
		);

		if (
			is_wp_error( $term_ids )
			|| ! is_array( $term_ids )
			|| ! isset( $term_ids[0] )
		) {
			return null;
		}

		$term_id = absint( $term_ids[0] );

		return $term_id > 0 ? $term_id : null;
	}

	/**
	 * Normalize and validate a unit.
	 *
	 * The legacy input keys unit_code and display_order are accepted only to
	 * make data migration safer. Returned data always uses code and sort_order.
	 *
	 * @param array<string, mixed> $unit Raw unit data.
	 *
	 * @return array{
	 *     code: string,
	 *     name: string,
	 *     slug: string,
	 *     parent_code: string,
	 *     region: string,
	 *     source_url: string,
	 *     timezone: string,
	 *     is_public: bool,
	 *     sort_order: int,
	 *     metadata: array<string, scalar|null>,
	 *     metadata_json: string
	 * }
	 *
	 * @throws InvalidArgumentException When data is invalid.
	 */
	public static function normalize_unit( array $unit ): array {
		$code = self::sanitize_code(
			$unit['code'] ?? $unit['unit_code'] ?? ''
		);

		if ( '' === $code ) {
			throw new InvalidArgumentException(
				__( 'Mã đơn vị điện lực không hợp lệ.', 'power-schedule-manager' )
			);
		}

		$name = self::sanitize_limited_text(
			$unit['name'] ?? '',
			self::MAX_NAME_LENGTH
		);

		if ( '' === $name ) {
			throw new InvalidArgumentException(
				__( 'Tên đơn vị điện lực không hợp lệ.', 'power-schedule-manager' )
			);
		}

		$slug = isset( $unit['slug'] ) && is_scalar( $unit['slug'] )
			? sanitize_title( (string) $unit['slug'] )
			: '';

		if ( '' === $slug ) {
			$slug = Power_Schedule_Manager_Taxonomy::build_term_slug(
				$name
			);
		}

		if ( '' === $slug ) {
			throw new InvalidArgumentException(
				__( 'Không thể tạo đường dẫn cho đơn vị điện lực.', 'power-schedule-manager' )
			);
		}

		$parent_code = self::sanitize_code(
			$unit['parent_code'] ?? ''
		);

		if ( $parent_code === $code ) {
			throw new InvalidArgumentException(
				__(
					'Đơn vị không thể là đơn vị cha của chính nó.',
					'power-schedule-manager'
				)
			);
		}

		$region = self::sanitize_limited_text(
			$unit['region'] ?? '',
			self::MAX_REGION_LENGTH
		);

		$source_url = self::sanitize_source_url(
			$unit['source_url'] ?? ''
		);

		$timezone = self::sanitize_timezone(
			$unit['timezone'] ?? POWER_SCHEDULE_MANAGER_TIMEZONE
		);

		$is_public = array_key_exists( 'is_public', $unit )
			? Power_Schedule_Manager_Taxonomy::sanitize_boolean(
				$unit['is_public']
			)
			: true;

		$sort_order = Power_Schedule_Manager_Taxonomy::sanitize_display_order(
			$unit['sort_order'] ?? $unit['display_order'] ?? 0
		);

		$metadata = self::sanitize_metadata(
			$unit['metadata'] ?? array()
		);

		$metadata_json = self::encode_metadata( $metadata );

		return array(
			'code'          => $code,
			'name'          => $name,
			'slug'          => $slug,
			'parent_code'   => $parent_code,
			'region'        => $region,
			'source_url'    => $source_url,
			'timezone'      => $timezone,
			'is_public'     => $is_public,
			'sort_order'    => $sort_order,
			'metadata'      => $metadata,
			'metadata_json' => $metadata_json,
		);
	}

	/**
	 * Sanitize a unit code.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_code( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strtoupper(
			trim( (string) $value )
		);

		$value = preg_replace(
			'/[^A-Z0-9_-]/',
			'',
			$value
		);

		if ( ! is_string( $value ) ) {
			return '';
		}

		return substr(
			$value,
			0,
			self::MAX_CODE_LENGTH
		);
	}

	/**
	 * Sanitize timezone identifier.
	 *
	 * @param mixed $value Raw timezone.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When timezone is invalid.
	 */
	private static function sanitize_timezone( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			throw new InvalidArgumentException(
				__( 'Múi giờ không hợp lệ.', 'power-schedule-manager' )
			);
		}

		$timezone = trim( (string) $value );

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
	 * Sanitize an HTTP or HTTPS source URL.
	 *
	 * @param mixed $value Raw URL.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When URL is invalid.
	 */
	private static function sanitize_source_url( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$url = esc_url_raw(
			$value,
			array( 'http', 'https' )
		);

		if ( '' === $url || strlen( $url ) > self::MAX_SOURCE_URL_LENGTH ) {
			throw new InvalidArgumentException(
				__( 'URL nguồn của đơn vị không hợp lệ.', 'power-schedule-manager' )
			);
		}

		return $url;
	}

	/**
	 * Sanitize unit metadata.
	 *
	 * Metadata is limited to one-dimensional scalar values. HTML, objects,
	 * nested arrays, resources, and executable content are not accepted.
	 *
	 * @param mixed $metadata Raw metadata.
	 *
	 * @return array<string, scalar|null>
	 *
	 * @throws InvalidArgumentException When metadata is invalid.
	 */
	private static function sanitize_metadata( mixed $metadata ): array {
		if ( null === $metadata || '' === $metadata ) {
			return array();
		}

		if ( is_string( $metadata ) ) {
			try {
				$metadata = json_decode(
					$metadata,
					true,
					16,
					JSON_THROW_ON_ERROR
				);
			} catch ( JsonException $exception ) {
				throw new InvalidArgumentException(
					__( 'Metadata đơn vị không phải JSON hợp lệ.', 'power-schedule-manager' ),
					0,
					$exception
				);
			}
		}

		if ( ! is_array( $metadata ) ) {
			throw new InvalidArgumentException(
				__( 'Metadata đơn vị không hợp lệ.', 'power-schedule-manager' )
			);
		}

		$sanitized = array();

		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$key = sanitize_key( $key );

			if ( '' === $key ) {
				continue;
			}

			if ( null === $value || is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
				continue;
			}

			if ( is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
				continue;
			}

			if ( is_string( $value ) ) {
				$sanitized[ $key ] = self::sanitize_limited_text(
					$value,
					500
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Encode sanitized metadata.
	 *
	 * @param array<string, scalar|null> $metadata Sanitized metadata.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When encoding fails.
	 */
	private static function encode_metadata( array $metadata ): string {
		try {
			$encoded = wp_json_encode(
				$metadata,
				JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_PRESERVE_ZERO_FRACTION
				| JSON_THROW_ON_ERROR,
				16
			);
		} catch ( JsonException $exception ) {
			throw new InvalidArgumentException(
				__( 'Không thể mã hóa metadata đơn vị.', 'power-schedule-manager' ),
				0,
				$exception
			);
		}

		if (
			! is_string( $encoded )
			|| strlen( $encoded ) > self::MAX_METADATA_BYTES
		) {
			throw new InvalidArgumentException(
				__( 'Metadata đơn vị vượt quá giới hạn cho phép.', 'power-schedule-manager' )
			);
		}

		return $encoded;
	}

	/**
	 * Build an SEO-friendly taxonomy description.
	 *
	 * @param array<string, mixed> $unit Normalized unit.
	 *
	 * @return string
	 */
	private static function build_term_description( array $unit ): string {
		if ( '' !== $unit['region'] ) {
			return sprintf(
				/* translators: 1: Unit name, 2: Region name. */
				__(
					'Lịch ngừng và giảm cung cấp điện do %1$s quản lý tại khu vực %2$s.',
					'power-schedule-manager'
				),
				$unit['name'],
				$unit['region']
			);
		}

		return sprintf(
			/* translators: %s: Electrical service unit name. */
			__(
				'Lịch ngừng và giảm cung cấp điện do %s quản lý.',
				'power-schedule-manager'
			),
			$unit['name']
		);
	}

	/**
	 * Sanitize and limit plain text.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum character count.
	 *
	 * @return string
	 */
	private static function sanitize_limited_text(
		mixed $value,
		int $length
	): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field(
			(string) $value
		);

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}

	/**
	 * Cast a database row to stable PHP value types.
	 *
	 * @param array<string, mixed> $row Database row.
	 *
	 * @return array<string, mixed>
	 */
	private static function cast_database_row( array $row ): array {
		$metadata = array();

		if (
			isset( $row['metadata'] )
			&& is_string( $row['metadata'] )
			&& '' !== $row['metadata']
		) {
			$decoded = json_decode(
				$row['metadata'],
				true
			);

			if ( is_array( $decoded ) ) {
				$metadata = $decoded;
			}
		}

		return array(
			'id'             => isset( $row['id'] )
				? (int) $row['id']
				: 0,
			'code'           => isset( $row['code'] )
				? (string) $row['code']
				: '',
			'name'           => isset( $row['name'] )
				? (string) $row['name']
				: '',
			'slug'           => isset( $row['slug'] )
				? (string) $row['slug']
				: '',
			'parent_code'    => isset( $row['parent_code'] )
				? (string) $row['parent_code']
				: '',
			'region'         => isset( $row['region'] )
				? (string) $row['region']
				: '',
			'source_url'     => isset( $row['source_url'] )
				? (string) $row['source_url']
				: '',
			'timezone'       => isset( $row['timezone'] )
				? (string) $row['timezone']
				: POWER_SCHEDULE_MANAGER_TIMEZONE,
			'is_public'      => isset( $row['is_public'] )
				&& 1 === (int) $row['is_public'],
			'sort_order'     => isset( $row['sort_order'] )
				? (int) $row['sort_order']
				: 0,
			'metadata'       => $metadata,
			'created_at_utc' => isset( $row['created_at_utc'] )
				? (string) $row['created_at_utc']
				: '',
			'updated_at_utc' => isset( $row['updated_at_utc'] )
				? (string) $row['updated_at_utc']
				: '',
		);
	}
}
