<?php
/**
 * Database migration của Cúp Điện Lâm Đồng.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tạo, cập nhật và xác minh database schema.
 */
final class Power_Schedule_Manager_Migrator {

	/**
	 * Phiên bản schema đầu tiên.
	 */
	private const string INITIAL_SCHEMA_VERSION = '1.0.0';

	/**
	 * Column và kiểu dữ liệu bắt buộc.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const array REQUIRED_COLUMNS = array(
		Power_Schedule_Manager_Database::EVENTS => array(
			'id'                => 'bigint unsigned',
			'unit_id'           => 'bigint unsigned',
			'unit_code'         => 'varchar(32)',
			'source'            => 'varchar(32)',
			'source_event_id'   => 'varchar(191)',
			'identity_hash'     => 'binary(32)',
			'content_hash'      => 'binary(32)',
			'local_date'        => 'date',
			'start_at_utc'      => 'datetime',
			'end_at_utc'        => 'datetime',
			'area'              => 'text',
			'reason'            => 'text',
			'status'            => 'varchar(20)',
			'import_run_id'     => 'bigint unsigned',
			'post_id'           => 'bigint unsigned',
			'missing_count'     => 'smallint unsigned',
			'sync_count'        => 'int unsigned',
			'first_seen_at_utc' => 'datetime',
			'last_seen_at_utc'  => 'datetime',
			'created_at_utc'    => 'datetime',
			'updated_at_utc'    => 'datetime',
			'deleted_at_utc'    => 'datetime',
		),

		Power_Schedule_Manager_Database::UNITS => array(
			'id'             => 'bigint unsigned',
			'code'           => 'varchar(32)',
			'name'           => 'varchar(191)',
			'slug'           => 'varchar(191)',
			'parent_code'    => 'varchar(32)',
			'region'         => 'varchar(191)',
			'source_url'     => 'varchar(2048)',
			'timezone'       => 'varchar(64)',
			'is_public'      => 'tinyint unsigned',
			'sort_order'     => 'int unsigned',
			'metadata'       => 'longtext',
			'created_at_utc' => 'datetime',
			'updated_at_utc' => 'datetime',
		),

		Power_Schedule_Manager_Database::IMPORT_RUNS => array(
			'id'                 => 'bigint unsigned',
			'run_uuid'           => 'char(36)',
			'user_id'            => 'bigint unsigned',
			'unit_id'            => 'bigint unsigned',
			'unit_code'          => 'varchar(32)',
			'source'             => 'varchar(32)',
			'source_url'         => 'varchar(2048)',
			'status'             => 'varchar(20)',
			'payload_hash'       => 'binary(32)',
			'preview_token_hash' => 'binary(32)',
			'raw_payload'        => 'longtext',
			'payload_bytes'      => 'bigint unsigned',
			'found_count'        => 'int unsigned',
			'inserted_count'     => 'int unsigned',
			'updated_count'      => 'int unsigned',
			'unchanged_count'    => 'int unsigned',
			'duplicate_count'    => 'int unsigned',
			'warning_count'      => 'int unsigned',
			'error_count'        => 'int unsigned',
			'error_code'         => 'varchar(64)',
			'error_message'      => 'text',
			'started_at_utc'     => 'datetime',
			'finished_at_utc'    => 'datetime',
			'expires_at_utc'     => 'datetime',
			'created_at_utc'     => 'datetime',
			'updated_at_utc'     => 'datetime',
		),

		Power_Schedule_Manager_Database::EVENT_REVISIONS => array(
			'id'              => 'bigint unsigned',
			'event_id'        => 'bigint unsigned',
			'import_run_id'   => 'bigint unsigned',
			'user_id'         => 'bigint unsigned',
			'revision_number' => 'int unsigned',
			'change_type'     => 'varchar(32)',
			'content_hash'    => 'binary(32)',
			'before_data'     => 'longtext',
			'after_data'      => 'longtext',
			'created_at_utc'  => 'datetime',
		),

		Power_Schedule_Manager_Database::EVENT_LOCATIONS => array(
			'id'             => 'bigint unsigned',
			'event_id'       => 'bigint unsigned',
			'location_type'  => 'varchar(32)',
			'label'          => 'varchar(191)',
			'description'    => 'text',
			'geojson'        => 'longtext',
			'center_lat'     => 'decimal(10,7)',
			'center_lng'     => 'decimal(10,7)',
			'default_zoom'   => 'tinyint unsigned',
			'sort_order'     => 'int unsigned',
			'created_at_utc' => 'datetime',
			'updated_at_utc' => 'datetime',
		),

		Power_Schedule_Manager_Database::PLACES => array(
			'id'              => 'bigint unsigned',
			'unit_id'         => 'bigint unsigned',
			'unit_code'       => 'varchar(32)',
			'canonical_name'  => 'varchar(191)',
			'normalized_hash' => 'binary(32)',
			'location_type'   => 'varchar(32)',
			'description'     => 'text',
			'geojson'         => 'longtext',
			'center_lat'      => 'decimal(10,7)',
			'center_lng'      => 'decimal(10,7)',
			'default_zoom'    => 'tinyint unsigned',
			'status'          => 'varchar(20)',
			'created_at_utc'  => 'datetime',
			'updated_at_utc'  => 'datetime',
		),

		Power_Schedule_Manager_Database::PLACE_ALIASES => array(
			'id'             => 'bigint unsigned',
			'place_id'       => 'bigint unsigned',
			'unit_id'        => 'bigint unsigned',
			'alias'          => 'varchar(191)',
			'alias_hash'     => 'binary(32)',
			'created_at_utc' => 'datetime',
			'updated_at_utc' => 'datetime',
		),

		Power_Schedule_Manager_Database::EVENT_PLACES => array(
			'event_id'       => 'bigint unsigned',
			'place_id'       => 'bigint unsigned',
			'sort_order'     => 'int unsigned',
			'link_source'    => 'varchar(16)',
			'created_at_utc' => 'datetime',
		),

		Power_Schedule_Manager_Database::NOTIFICATIONS => array(
			'id'               => 'bigint unsigned',
			'channel'          => 'varchar(24)',
			'dedupe_hash'      => 'binary(32)',
			'notification_hash' => 'binary(32)',
			'status'           => 'varchar(20)',
			'payload'          => 'longtext',
			'attempts'         => 'smallint unsigned',
			'last_attempt_at_utc' => 'datetime',
			'available_at_utc' => 'datetime',
			'locked_at_utc'    => 'datetime',
			'response_code'    => 'smallint unsigned',
			'onesignal_message_id' => 'varchar(64)',
			'last_error'       => 'text',
			'sent_at_utc'      => 'datetime',
			'created_at_utc'   => 'datetime',
			'updated_at_utc'   => 'datetime',
		),

		Power_Schedule_Manager_Database::DONATIONS => array(
			'id'                  => 'bigint unsigned',
			'receipt_code'        => 'char(32)',
			'reference_hash'      => 'binary(32)',
			'method'              => 'varchar(20)',
			'donor_name'          => 'varchar(191)',
			'donor_email'         => 'varchar(191)',
			'donor_phone'         => 'varchar(32)',
			'amount'              => 'bigint unsigned',
			'transfer_reference'  => 'varchar(191)',
			'message'             => 'text',
			'status'              => 'varchar(20)',
			'ip_hash'             => 'binary(32)',
			'submitted_at_utc'    => 'datetime',
			'confirmed_at_utc'    => 'datetime',
			'confirmed_by'        => 'bigint unsigned',
			'updated_at_utc'      => 'datetime',
		),

		Power_Schedule_Manager_Database::MARKET_PRICES => array(
			'id'             => 'bigint unsigned',
			'commodity'      => 'varchar(32)',
			'market_code'    => 'varchar(64)',
			'label'          => 'varchar(191)',
			'contract_code'  => 'varchar(32)',
			'price_date'     => 'date',
			'price'          => 'decimal(20,4)',
			'buy_price'      => 'decimal(20,4)',
			'sell_price'     => 'decimal(20,4)',
			'change_value'   => 'decimal(20,4)',
			'change_percent' => 'decimal(10,4)',
			'buy_change'     => 'decimal(20,4)',
			'sell_change'    => 'decimal(20,4)',
			'high_price'     => 'decimal(20,4)',
			'low_price'      => 'decimal(20,4)',
			'open_price'     => 'decimal(20,4)',
			'previous_close' => 'decimal(20,4)',
			'volume'         => 'bigint unsigned',
			'open_interest'  => 'bigint unsigned',
			'unit'           => 'varchar(32)',
			'currency'       => 'varchar(8)',
			'source_name'    => 'varchar(191)',
			'source_url'     => 'varchar(2048)',
			'provider_code'  => 'varchar(64)',
			'observed_at_utc' => 'datetime',
			'fetched_at_utc' => 'datetime',
			'data_hash'      => 'binary(32)',
			'is_public'      => 'tinyint unsigned',
			'created_by'     => 'bigint unsigned',
			'created_at_utc' => 'datetime',
			'updated_at_utc' => 'datetime',
		),

		Power_Schedule_Manager_Database::LOTTERY_DRAWS => array(
			'id'                  => 'bigint unsigned',
			'provider_draw_id'    => 'varchar(191)',
			'draw_key'            => 'binary(32)',
			'region'              => 'varchar(32)',
			'province_code'       => 'varchar(64)',
			'province_name'       => 'varchar(191)',
			'game_type'           => 'varchar(64)',
			'draw_date'           => 'date',
			'draw_time'           => 'varchar(16)',
			'status'              => 'varchar(20)',
			'special_prize'       => 'varchar(191)',
			'results_json'        => 'longtext',
			'source_payload_json' => 'longtext',
			'provider_code'       => 'varchar(64)',
			'source_url'          => 'varchar(2048)',
			'observed_at_utc'     => 'datetime',
			'fetched_at_utc'      => 'datetime',
			'data_hash'           => 'binary(32)',
			'is_public'           => 'tinyint unsigned',
			'created_at_utc'      => 'datetime',
			'updated_at_utc'      => 'datetime',
		),
	);

	/**
	 * Index bắt buộc.
	 *
	 * @var array<string, array<string, array{unique: bool, columns: array<int, string>}>>
	 */
	private const array REQUIRED_INDEXES = array(
		Power_Schedule_Manager_Database::EVENTS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'identity_hash' => array(
				'unique'  => true,
				'columns' => array( 'identity_hash' ),
			),
			'source_event' => array(
				'unique'  => false,
				'columns' => array(
					'source',
					'unit_code',
					'source_event_id',
				),
			),
			'unit_code_id' => array(
				'unique'  => false,
				'columns' => array(
					'unit_code',
					'id',
				),
			),
			'unit_status_start' => array(
				'unique'  => false,
				'columns' => array(
					'unit_id',
					'status',
					'start_at_utc',
				),
			),
			'local_date_unit' => array(
				'unique'  => false,
				'columns' => array(
					'local_date',
					'unit_id',
					'status',
				),
			),
			'api_date_start' => array(
				'unique'  => false,
				'columns' => array(
					'local_date',
					'start_at_utc',
					'id',
				),
			),
			'api_unit_date_start' => array(
				'unique'  => false,
				'columns' => array(
					'unit_code',
					'local_date',
					'start_at_utc',
					'id',
				),
			),
			'status_start' => array(
				'unique'  => false,
				'columns' => array(
					'status',
					'start_at_utc',
				),
			),
			'status_end' => array(
				'unique'  => false,
				'columns' => array(
					'status',
					'end_at_utc',
				),
			),
			'status_end_cleanup' => array(
				'unique'  => false,
				'columns' => array(
					'status',
					'end_at_utc',
					'id',
				),
			),
			'status_updated_cleanup' => array(
				'unique'  => false,
				'columns' => array(
					'status',
					'updated_at_utc',
					'id',
				),
			),
			'deleted_at' => array(
				'unique'  => false,
				'columns' => array( 'deleted_at_utc' ),
			),
			'import_run_id' => array(
				'unique'  => false,
				'columns' => array( 'import_run_id' ),
			),
			'post_id' => array(
				'unique'  => false,
				'columns' => array( 'post_id' ),
			),
			'last_seen' => array(
				'unique'  => false,
				'columns' => array( 'last_seen_at_utc' ),
			),
		),

		Power_Schedule_Manager_Database::UNITS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'code' => array(
				'unique'  => true,
				'columns' => array( 'code' ),
			),
			'slug' => array(
				'unique'  => true,
				'columns' => array( 'slug' ),
			),
			'public_sort' => array(
				'unique'  => false,
				'columns' => array(
					'is_public',
					'sort_order',
				),
			),
			'parent_code' => array(
				'unique'  => false,
				'columns' => array( 'parent_code' ),
			),
		),

		Power_Schedule_Manager_Database::IMPORT_RUNS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'run_uuid' => array(
				'unique'  => true,
				'columns' => array( 'run_uuid' ),
			),
			'unit_started' => array(
				'unique'  => false,
				'columns' => array(
					'unit_id',
					'started_at_utc',
				),
			),
			'status_expires' => array(
				'unique'  => false,
				'columns' => array(
					'status',
					'expires_at_utc',
				),
			),
			'unit_payload_started' => array(
				'unique'  => false,
				'columns' => array(
					'unit_id',
					'payload_hash',
					'started_at_utc',
				),
			),
			'user_started' => array(
				'unique'  => false,
				'columns' => array(
					'user_id',
					'started_at_utc',
				),
			),
			'finished_at' => array(
				'unique'  => false,
				'columns' => array( 'finished_at_utc' ),
			),
			'status_started_cleanup' => array(
				'unique'  => false,
				'columns' => array(
					'status',
					'started_at_utc',
					'id',
				),
			),
			'status_finished_cleanup' => array(
				'unique'  => false,
				'columns' => array(
					'status',
					'finished_at_utc',
					'id',
				),
			),
		),

		Power_Schedule_Manager_Database::EVENT_REVISIONS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'event_revision' => array(
				'unique'  => true,
				'columns' => array(
					'event_id',
					'revision_number',
				),
			),
			'import_run_id' => array(
				'unique'  => false,
				'columns' => array( 'import_run_id' ),
			),
			'created_at' => array(
				'unique'  => false,
				'columns' => array( 'created_at_utc' ),
			),
		),

		Power_Schedule_Manager_Database::EVENT_LOCATIONS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'event_sort' => array(
				'unique'  => false,
				'columns' => array(
					'event_id',
					'sort_order',
				),
			),
			'event_type' => array(
				'unique'  => false,
				'columns' => array(
					'event_id',
					'location_type',
				),
			),
		),

		Power_Schedule_Manager_Database::PLACES => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'unit_name' => array(
				'unique'  => true,
				'columns' => array( 'unit_id', 'normalized_hash' ),
			),
			'unit_status' => array(
				'unique'  => false,
				'columns' => array( 'unit_id', 'status' ),
			),
			'unit_library_filter' => array(
				'unique'  => false,
				'columns' => array(
					'unit_code',
					'status',
					'location_type',
				),
			),
			'canonical_name' => array(
				'unique'  => false,
				'columns' => array( 'canonical_name' ),
			),
		),

		Power_Schedule_Manager_Database::PLACE_ALIASES => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'unit_alias' => array(
				'unique'  => true,
				'columns' => array( 'unit_id', 'alias_hash' ),
			),
			'place_id' => array(
				'unique'  => false,
				'columns' => array( 'place_id' ),
			),
			'alias_name' => array(
				'unique'  => false,
				'columns' => array( 'alias' ),
			),
		),

		Power_Schedule_Manager_Database::EVENT_PLACES => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'event_id', 'place_id' ),
			),
			'place_event' => array(
				'unique'  => false,
				'columns' => array( 'place_id', 'event_id' ),
			),
		),

		Power_Schedule_Manager_Database::NOTIFICATIONS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'channel_dedupe' => array(
				'unique'  => true,
				'columns' => array( 'channel', 'dedupe_hash' ),
			),
			'status_available' => array(
				'unique'  => false,
				'columns' => array( 'status', 'available_at_utc', 'id' ),
			),
			'status_updated' => array(
				'unique'  => false,
				'columns' => array( 'status', 'updated_at_utc', 'id' ),
			),
		),

		Power_Schedule_Manager_Database::DONATIONS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'receipt_code' => array(
				'unique'  => true,
				'columns' => array( 'receipt_code' ),
			),
			'reference_hash' => array(
				'unique'  => true,
				'columns' => array( 'reference_hash' ),
			),
			'status_submitted' => array(
				'unique'  => false,
				'columns' => array( 'status', 'submitted_at_utc', 'id' ),
			),
		),

		Power_Schedule_Manager_Database::MARKET_PRICES => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'market_contract_row' => array(
				'unique'  => true,
				'columns' => array(
					'commodity',
					'market_code',
					'label',
					'contract_code',
					'price_date',
				),
			),
			'public_market_date' => array(
				'unique'  => false,
				'columns' => array(
					'is_public',
					'commodity',
					'market_code',
					'price_date',
					'id',
				),
			),
			'market_series_date' => array(
				'unique'  => false,
				'columns' => array(
					'market_code',
					'label',
					'contract_code',
					'price_date',
					'id',
				),
			),
		),

		Power_Schedule_Manager_Database::LOTTERY_DRAWS => array(
			'PRIMARY' => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'draw_key' => array(
				'unique'  => true,
				'columns' => array( 'draw_key' ),
			),
			'public_date' => array(
				'unique'  => false,
				'columns' => array(
					'is_public',
					'draw_date',
					'region',
					'province_code',
					'id',
				),
			),
			'public_game_date' => array(
				'unique'  => false,
				'columns' => array(
					'is_public',
					'game_type',
					'draw_date',
					'id',
				),
			),
			'public_region_date' => array(
				'unique'  => false,
				'columns' => array(
					'is_public',
					'region',
					'draw_date',
					'id',
				),
			),
			'provider_draw' => array(
				'unique'  => false,
				'columns' => array(
					'provider_code',
					'provider_draw_id',
				),
			),
		),
	);

	/**
	 * Database named lock hiện tại.
	 */
	private static string $lock_name = '';

	/**
	 * Token quan sát lock hiện tại.
	 */
	private static string $lock_token = '';

	/**
	 * Runtime migration có lỗi hay không.
	 */
	private bool $runtime_migration_failed = false;

	/**
	 * Đăng ký kiểm tra migration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_init',
			array( $this, 'maybe_upgrade' ),
			1
		);
	}

	/**
	 * Chạy migration khi activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::run_migrations( true );
	}

	/**
	 * Kiểm tra migration khi vào admin.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed_version = self::installed_version();

		if (
			POWER_SCHEDULE_MANAGER_SCHEMA_VERSION
				=== $installed_version
			&& Power_Schedule_Manager_Database::all_tables_exist()
		) {
			return;
		}

		try {
			self::run_migrations( false );
		} catch ( Throwable $throwable ) {
			$this->runtime_migration_failed = true;

			self::log_migration_error(
				'runtime_upgrade',
				$throwable
			);

			add_action(
				'admin_notices',
				array(
					$this,
					'render_migration_error_notice',
				)
			);
		}
	}

	/**
	 * Hiển thị lỗi migration.
	 *
	 * @return void
	 */
	public function render_migration_error_notice(): void {
		if (
			! $this->runtime_migration_failed
			|| ! current_user_can( 'activate_plugins' )
		) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Cúp Điện Lâm Đồng chưa thể hoàn tất cập nhật database. Plugin sẽ thử lại trong lần truy cập quản trị tiếp theo.',
				'power-schedule-manager'
			)
		);
	}

	/**
	 * Chạy migration và xác minh schema.
	 *
	 * @param bool $force_verify Luôn xác minh đầy đủ schema.
	 * @return void
	 */
	private static function run_migrations(
		bool $force_verify
	): void {
		$installed_version = self::installed_version();

		if (
			version_compare(
				$installed_version,
				POWER_SCHEDULE_MANAGER_SCHEMA_VERSION,
				'>'
			)
		) {
			throw new RuntimeException(
				'schema_newer_than_plugin'
			);
		}

		if (
			! $force_verify
			&& POWER_SCHEDULE_MANAGER_SCHEMA_VERSION
				=== $installed_version
			&& Power_Schedule_Manager_Database::all_tables_exist()
		) {
			return;
		}

		self::acquire_lock();

		try {
			$installed_version = self::installed_version();

			if (
				version_compare(
					$installed_version,
					POWER_SCHEDULE_MANAGER_SCHEMA_VERSION,
					'>'
				)
			) {
				throw new RuntimeException(
					'schema_newer_than_plugin'
				);
			}

			if (
				version_compare(
					$installed_version,
					POWER_SCHEDULE_MANAGER_SCHEMA_VERSION,
					'<'
				)
				|| ! Power_Schedule_Manager_Database::all_tables_exist()
			) {
				self::create_or_update_schema();
				self::upgrade_market_price_indexes();
				self::normalize_event_sources();
				self::reconcile_unit_relations();
			}

			self::verify_schema();
			self::store_schema_version();
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Remove the legacy unique index that prevented multiple futures
	 * contracts from being stored for the same market and date.
	 */
	private static function upgrade_market_price_indexes(): void {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$legacy = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT INDEX_NAME FROM information_schema.STATISTICS
				WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s
				AND INDEX_NAME=%s LIMIT 1',
				$table,
				'market_row'
			)
		);

		if ( 'market_row' !== $legacy ) {
			return;
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				'ALTER TABLE %i DROP INDEX %i',
				$table,
				'market_row'
			)
		);

		if ( false === $result ) {
			throw new RuntimeException(
				'market_legacy_index_drop_failed: '
				. Power_Schedule_Manager_Database::last_error()
			);
		}
	}

	/**
	 * Replace the removed manual-entry source with the EVN source.
	 *
	 * Both tables are updated during the versioned migration so existing
	 * installations and fresh installations use the same source vocabulary.
	 *
	 * @return void
	 */
	private static function normalize_event_sources(): void {
		global $wpdb;

		$tables = array(
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::IMPORT_RUNS
			),
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::EVENTS
			),
		);

		foreach ( $tables as $table ) {
			$result = $wpdb->query(
				"UPDATE {$table}
				SET source = 'evn'
				WHERE source = 'manual'"
			);

			if ( false === $result ) {
				throw new RuntimeException(
					'event_source_migration_failed: '
					. Power_Schedule_Manager_Database::last_error()
				);
			}
		}
	}

	/**
	 * Repair legacy unit IDs using the stable unit code.
	 *
	 * Older installations could retain an outdated numeric unit ID after seed
	 * data was rebuilt. The code is the durable external identifier, so this
	 * bounded indexed update restores map matching without changing events.
	 *
	 * @return void
	 */
	private static function reconcile_unit_relations(): void {
		global $wpdb;

		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$tables = array(
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::EVENTS
			),
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::IMPORT_RUNS
			),
			Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::PLACES
			),
		);

		foreach ( $tables as $table ) {
			$result = $wpdb->query(
				"UPDATE {$table} AS records
				INNER JOIN {$units} AS units
					ON units.code = records.unit_code
				SET records.unit_id = units.id
				WHERE records.unit_id <> units.id"
			);

			if ( false === $result ) {
				throw new RuntimeException(
					'unit_relation_repair_failed: '
					. Power_Schedule_Manager_Database::last_error()
				);
			}
		}
	}

	/**
	 * Chạy dbDelta.
	 *
	 * @return void
	 */
	private static function create_or_update_schema(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH
				. 'wp-admin/includes/upgrade.php';
		}

		self::drop_conflicting_indexes();

		$previous_state = $wpdb->suppress_errors(
			true
		);

		try {
			foreach ( self::schema_statements() as $statement ) {
				$wpdb->last_error = '';

				dbDelta( $statement );

				if ( '' !== $wpdb->last_error ) {
					throw new RuntimeException(
						'database_schema_error: '
						. Power_Schedule_Manager_Database::last_error()
					);
				}
			}
		} finally {
			$wpdb->suppress_errors(
				(bool) $previous_state
			);
		}
	}

	/**
	 * Drop indexes whose names are current but whose definitions are stale.
	 *
	 * dbDelta can add a missing index, but it cannot reliably replace an
	 * existing index when the index name is unchanged and its columns or
	 * uniqueness have changed. MySQL then rejects the generated ALTER TABLE
	 * statement with "Duplicate key name". Removing only the conflicting
	 * definition before dbDelta makes migrations repeatable while preserving
	 * every table row.
	 *
	 * @return void
	 */
	private static function drop_conflicting_indexes(): void {
		global $wpdb;

		foreach (
			self::REQUIRED_INDEXES
			as $table_key => $required_indexes
		) {
			if (
				! Power_Schedule_Manager_Database::table_exists(
					$table_key
				)
			) {
				continue;
			}

			$actual_indexes = self::read_table_indexes(
				$table_key
			);

			foreach (
				$required_indexes
				as $index_name => $expected
			) {
				if (
					'PRIMARY' === $index_name
					|| ! isset( $actual_indexes[ $index_name ] )
				) {
					continue;
				}

				$actual = $actual_indexes[ $index_name ];

				if (
					$expected['unique'] === $actual['unique']
					&& $expected['columns'] === $actual['columns']
				) {
					continue;
				}

				$table_name =
					Power_Schedule_Manager_Database::table(
						$table_key
					);

				$result = $wpdb->query(
					$wpdb->prepare(
						'ALTER TABLE %i DROP INDEX %i',
						$table_name,
						$index_name
					)
				);

				if ( false === $result ) {
					throw new RuntimeException(
						'database_conflicting_index_drop_failed: '
							. Power_Schedule_Manager_Database::last_error()
					);
				}
			}
		}
	}

	/**
	 * Lấy SQL tạo bảng.
	 *
	 * @return array<int, string>
	 */
	private static function schema_statements(): array {
		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);

		$units = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$imports = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::IMPORT_RUNS
		);

		$revisions = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_REVISIONS
		);

		$locations = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_LOCATIONS
		);

		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);

		$aliases = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACE_ALIASES
		);

		$event_places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);

		$notifications = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::NOTIFICATIONS
		);

		$donations = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::DONATIONS
		);

		$market_prices = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);

		$lottery_draws = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS
		);

		$charset = Power_Schedule_Manager_Database::charset_collate();

		$units_sql = "CREATE TABLE {$units} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
code varchar(32) NOT NULL,
name varchar(191) NOT NULL,
slug varchar(191) NOT NULL,
parent_code varchar(32) NOT NULL DEFAULT '',
region varchar(191) NOT NULL DEFAULT '',
source_url varchar(2048) NOT NULL DEFAULT '',
timezone varchar(64) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
is_public tinyint(1) unsigned NOT NULL DEFAULT 1,
sort_order int(10) unsigned NOT NULL DEFAULT 0,
metadata longtext DEFAULT NULL,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY code (code),
UNIQUE KEY slug (slug),
KEY public_sort (is_public,sort_order),
KEY parent_code (parent_code)
) ENGINE=InnoDB {$charset};";

		$imports_sql = "CREATE TABLE {$imports} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
run_uuid char(36) NOT NULL,
user_id bigint(20) unsigned NOT NULL DEFAULT 0,
unit_id bigint(20) unsigned NOT NULL DEFAULT 0,
unit_code varchar(32) NOT NULL DEFAULT '',
source varchar(32) NOT NULL DEFAULT 'evn',
source_url varchar(2048) NOT NULL DEFAULT '',
status varchar(20) NOT NULL DEFAULT 'preview',
payload_hash binary(32) NOT NULL,
preview_token_hash binary(32) DEFAULT NULL,
raw_payload longtext DEFAULT NULL,
payload_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
found_count int(10) unsigned NOT NULL DEFAULT 0,
inserted_count int(10) unsigned NOT NULL DEFAULT 0,
updated_count int(10) unsigned NOT NULL DEFAULT 0,
unchanged_count int(10) unsigned NOT NULL DEFAULT 0,
duplicate_count int(10) unsigned NOT NULL DEFAULT 0,
warning_count int(10) unsigned NOT NULL DEFAULT 0,
error_count int(10) unsigned NOT NULL DEFAULT 0,
error_code varchar(64) NOT NULL DEFAULT '',
error_message text DEFAULT NULL,
started_at_utc datetime NOT NULL,
finished_at_utc datetime DEFAULT NULL,
expires_at_utc datetime DEFAULT NULL,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY run_uuid (run_uuid),
KEY unit_started (unit_id,started_at_utc),
KEY status_expires (status,expires_at_utc),
KEY unit_payload_started (unit_id,payload_hash,started_at_utc),
KEY user_started (user_id,started_at_utc),
KEY finished_at (finished_at_utc),
KEY status_started_cleanup (status,started_at_utc,id),
KEY status_finished_cleanup (status,finished_at_utc,id)
) ENGINE=InnoDB {$charset};";

		$events_sql = "CREATE TABLE {$events} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
unit_id bigint(20) unsigned NOT NULL,
unit_code varchar(32) NOT NULL,
source varchar(32) NOT NULL DEFAULT 'evn',
source_event_id varchar(191) NOT NULL DEFAULT '',
identity_hash binary(32) NOT NULL,
content_hash binary(32) NOT NULL,
local_date date NOT NULL,
start_at_utc datetime NOT NULL,
end_at_utc datetime NOT NULL,
area text NOT NULL,
reason text NOT NULL,
status varchar(20) NOT NULL DEFAULT 'scheduled',
import_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
post_id bigint(20) unsigned NOT NULL DEFAULT 0,
missing_count smallint(5) unsigned NOT NULL DEFAULT 0,
sync_count int(10) unsigned NOT NULL DEFAULT 1,
first_seen_at_utc datetime NOT NULL,
last_seen_at_utc datetime NOT NULL,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
deleted_at_utc datetime DEFAULT NULL,
PRIMARY KEY  (id),
UNIQUE KEY identity_hash (identity_hash),
KEY source_event (source,unit_code,source_event_id),
KEY unit_code_id (unit_code,id),
KEY unit_status_start (unit_id,status,start_at_utc),
KEY local_date_unit (local_date,unit_id,status),
KEY api_date_start (local_date,start_at_utc,id),
KEY api_unit_date_start (unit_code,local_date,start_at_utc,id),
KEY status_start (status,start_at_utc),
KEY status_end (status,end_at_utc),
KEY status_end_cleanup (status,end_at_utc,id),
KEY status_updated_cleanup (status,updated_at_utc,id),
KEY deleted_at (deleted_at_utc),
KEY import_run_id (import_run_id),
KEY post_id (post_id),
KEY last_seen (last_seen_at_utc)
) ENGINE=InnoDB {$charset};";

		$locations_sql = "CREATE TABLE {$locations} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
event_id bigint(20) unsigned NOT NULL,
location_type varchar(32) NOT NULL DEFAULT 'road_segment',
label varchar(191) NOT NULL,
description text DEFAULT NULL,
geojson longtext DEFAULT NULL,
center_lat decimal(10,7) DEFAULT NULL,
center_lng decimal(10,7) DEFAULT NULL,
default_zoom tinyint(3) unsigned NOT NULL DEFAULT 15,
sort_order int(10) unsigned NOT NULL DEFAULT 0,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
KEY event_sort (event_id,sort_order),
KEY event_type (event_id,location_type)
) ENGINE=InnoDB {$charset};";

		$places_sql = "CREATE TABLE {$places} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
unit_id bigint(20) unsigned NOT NULL,
unit_code varchar(32) NOT NULL,
canonical_name varchar(191) NOT NULL,
normalized_hash binary(32) NOT NULL,
location_type varchar(32) NOT NULL DEFAULT 'road_segment',
description text DEFAULT NULL,
geojson longtext DEFAULT NULL,
center_lat decimal(10,7) DEFAULT NULL,
center_lng decimal(10,7) DEFAULT NULL,
default_zoom tinyint(3) unsigned NOT NULL DEFAULT 15,
status varchar(20) NOT NULL DEFAULT 'active',
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY unit_name (unit_id,normalized_hash),
KEY unit_status (unit_id,status),
KEY unit_library_filter (unit_code,status,location_type),
KEY canonical_name (canonical_name)
) ENGINE=InnoDB {$charset};";

		$aliases_sql = "CREATE TABLE {$aliases} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
place_id bigint(20) unsigned NOT NULL,
unit_id bigint(20) unsigned NOT NULL,
alias varchar(191) NOT NULL,
alias_hash binary(32) NOT NULL,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY unit_alias (unit_id,alias_hash),
KEY place_id (place_id),
KEY alias_name (alias)
) ENGINE=InnoDB {$charset};";

		$event_places_sql = "CREATE TABLE {$event_places} (
event_id bigint(20) unsigned NOT NULL,
place_id bigint(20) unsigned NOT NULL,
sort_order int(10) unsigned NOT NULL DEFAULT 0,
link_source varchar(16) NOT NULL DEFAULT 'auto',
created_at_utc datetime NOT NULL,
PRIMARY KEY  (event_id,place_id),
KEY place_event (place_id,event_id)
) ENGINE=InnoDB {$charset};";

		$revisions_sql = "CREATE TABLE {$revisions} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
event_id bigint(20) unsigned NOT NULL,
import_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
user_id bigint(20) unsigned NOT NULL DEFAULT 0,
revision_number int(10) unsigned NOT NULL,
change_type varchar(32) NOT NULL,
content_hash binary(32) NOT NULL,
before_data longtext DEFAULT NULL,
after_data longtext NOT NULL,
created_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY event_revision (event_id,revision_number),
KEY import_run_id (import_run_id),
KEY created_at (created_at_utc)
) ENGINE=InnoDB {$charset};";

		$notifications_sql = "CREATE TABLE {$notifications} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
channel varchar(24) NOT NULL,
dedupe_hash binary(32) NOT NULL,
notification_hash binary(32) DEFAULT NULL,
status varchar(20) NOT NULL DEFAULT 'pending',
payload longtext NOT NULL,
attempts smallint(5) unsigned NOT NULL DEFAULT 0,
last_attempt_at_utc datetime DEFAULT NULL,
available_at_utc datetime NOT NULL,
locked_at_utc datetime DEFAULT NULL,
response_code smallint(5) unsigned NOT NULL DEFAULT 0,
onesignal_message_id varchar(64) DEFAULT NULL,
last_error text DEFAULT NULL,
sent_at_utc datetime DEFAULT NULL,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY channel_dedupe (channel,dedupe_hash),
KEY status_available (status,available_at_utc,id),
KEY status_updated (status,updated_at_utc,id)
) ENGINE=InnoDB {$charset};";

		$donations_sql = "CREATE TABLE {$donations} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
receipt_code char(32) NOT NULL,
reference_hash binary(32) NOT NULL,
method varchar(20) NOT NULL,
donor_name varchar(191) NOT NULL DEFAULT '',
donor_email varchar(191) NOT NULL DEFAULT '',
donor_phone varchar(32) NOT NULL DEFAULT '',
amount bigint(20) unsigned NOT NULL DEFAULT 0,
transfer_reference varchar(191) NOT NULL,
message text DEFAULT NULL,
status varchar(20) NOT NULL DEFAULT 'pending',
ip_hash binary(32) DEFAULT NULL,
submitted_at_utc datetime NOT NULL,
confirmed_at_utc datetime DEFAULT NULL,
confirmed_by bigint(20) unsigned NOT NULL DEFAULT 0,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY receipt_code (receipt_code),
UNIQUE KEY reference_hash (reference_hash),
KEY status_submitted (status,submitted_at_utc,id)
) ENGINE=InnoDB {$charset};";

		$market_prices_sql = "CREATE TABLE {$market_prices} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
commodity varchar(32) NOT NULL,
market_code varchar(64) NOT NULL,
label varchar(191) NOT NULL,
contract_code varchar(32) NOT NULL DEFAULT '',
price_date date NOT NULL,
price decimal(20,4) DEFAULT NULL,
buy_price decimal(20,4) DEFAULT NULL,
sell_price decimal(20,4) DEFAULT NULL,
change_value decimal(20,4) DEFAULT NULL,
change_percent decimal(10,4) DEFAULT NULL,
buy_change decimal(20,4) DEFAULT NULL,
sell_change decimal(20,4) DEFAULT NULL,
high_price decimal(20,4) DEFAULT NULL,
low_price decimal(20,4) DEFAULT NULL,
open_price decimal(20,4) DEFAULT NULL,
previous_close decimal(20,4) DEFAULT NULL,
volume bigint(20) unsigned DEFAULT NULL,
open_interest bigint(20) unsigned DEFAULT NULL,
unit varchar(32) NOT NULL DEFAULT 'VND/kg',
currency varchar(8) NOT NULL DEFAULT 'VND',
source_name varchar(191) NOT NULL DEFAULT '',
source_url varchar(2048) NOT NULL DEFAULT '',
provider_code varchar(64) NOT NULL DEFAULT 'editorial',
observed_at_utc datetime DEFAULT NULL,
fetched_at_utc datetime DEFAULT NULL,
data_hash binary(32) DEFAULT NULL,
is_public tinyint(1) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL DEFAULT 0,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY market_contract_row (commodity,market_code,label,contract_code,price_date),
KEY public_market_date (is_public,commodity,market_code,price_date,id),
KEY market_series_date (market_code,label,contract_code,price_date,id)
) ENGINE=InnoDB {$charset};";

		$lottery_draws_sql = "CREATE TABLE {$lottery_draws} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
provider_draw_id varchar(191) NOT NULL DEFAULT '',
draw_key binary(32) NOT NULL,
region varchar(32) NOT NULL DEFAULT '',
province_code varchar(64) NOT NULL DEFAULT '',
province_name varchar(191) NOT NULL DEFAULT '',
game_type varchar(64) NOT NULL DEFAULT 'traditional',
draw_date date NOT NULL,
draw_time varchar(16) NOT NULL DEFAULT '',
status varchar(20) NOT NULL DEFAULT 'completed',
special_prize varchar(191) NOT NULL DEFAULT '',
results_json longtext NOT NULL,
source_payload_json longtext DEFAULT NULL,
provider_code varchar(64) NOT NULL DEFAULT 'xosoapi',
source_url varchar(2048) NOT NULL DEFAULT '',
observed_at_utc datetime DEFAULT NULL,
fetched_at_utc datetime NOT NULL,
data_hash binary(32) NOT NULL,
is_public tinyint(1) unsigned NOT NULL DEFAULT 1,
created_at_utc datetime NOT NULL,
updated_at_utc datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY draw_key (draw_key),
KEY public_date (is_public,draw_date,region,province_code,id),
KEY public_game_date (is_public,game_type,draw_date,id),
KEY public_region_date (is_public,region,draw_date,id),
KEY provider_draw (provider_code,provider_draw_id)
) ENGINE=InnoDB {$charset};";

		return array(
			$units_sql,
			$imports_sql,
			$events_sql,
			$locations_sql,
			$revisions_sql,
			$places_sql,
			$aliases_sql,
			$event_places_sql,
			$notifications_sql,
			$donations_sql,
			$market_prices_sql,
			$lottery_draws_sql,
		);
	}

	/**
	 * Xác minh toàn bộ schema.
	 *
	 * @return void
	 */
	private static function verify_schema(): void {
		if ( ! Power_Schedule_Manager_Database::all_tables_exist() ) {
			throw new RuntimeException(
				'missing_database_tables'
			);
		}

		foreach (
			Power_Schedule_Manager_Database::table_keys()
			as $table_key
		) {
			self::verify_table_engine( $table_key );
			self::verify_table_columns( $table_key );
			self::verify_table_indexes( $table_key );
		}
	}

	/**
	 * Kiểm tra InnoDB.
	 *
	 * @param string $table_key Khóa bảng.
	 * @return void
	 */
	private static function verify_table_engine(
		string $table_key
	): void {
		global $wpdb;

		$table_name = Power_Schedule_Manager_Database::table(
			$table_key
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SHOW TABLE STATUS LIKE %s',
				$wpdb->esc_like( $table_name )
			),
			ARRAY_A
		);

		if (
			! is_array( $row )
			|| ! isset( $row['Engine'] )
			|| 'innodb' !== strtolower( (string) $row['Engine'] )
		) {
			throw new RuntimeException(
				'invalid_database_table_engine'
			);
		}
	}

	/**
	 * Kiểm tra column.
	 *
	 * @param string $table_key Khóa bảng.
	 * @return void
	 */
	private static function verify_table_columns(
		string $table_key
	): void {
		global $wpdb;

		$table_name = Power_Schedule_Manager_Database::table(
			$table_key
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i',
				$table_name
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			throw new RuntimeException(
				'unable_to_read_database_columns'
			);
		}

		$actual_columns = array();

		foreach ( $rows as $row ) {
			if (
				isset( $row['Field'], $row['Type'] )
				&& is_string( $row['Field'] )
			) {
				$actual_columns[ $row['Field'] ] =
					self::normalize_column_type(
						(string) $row['Type']
					);
			}
		}

		foreach (
			self::REQUIRED_COLUMNS[ $table_key ]
			as $column_name => $expected_type
		) {
			if (
				! isset( $actual_columns[ $column_name ] )
				|| self::normalize_column_type( $expected_type )
					!== $actual_columns[ $column_name ]
			) {
				throw new RuntimeException(
					'invalid_database_column'
				);
			}
		}
	}

	/**
	 * Chuẩn hóa kiểu column.
	 *
	 * @param string $type Kiểu dữ liệu.
	 * @return string
	 */
	private static function normalize_column_type(
		string $type
	): string {
		$type = strtolower(
			trim( $type )
		);

		$type = preg_replace(
			'/\b(bigint|int|smallint|tinyint)\(\d+\)/',
			'$1',
			$type
		);

		$type = preg_replace(
			'/\s+/',
			' ',
			(string) $type
		);

		return trim( (string) $type );
	}

	/**
	 * Kiểm tra index.
	 *
	 * @param string $table_key Khóa bảng.
	 * @return void
	 */
	private static function verify_table_indexes(
		string $table_key
	): void {
		$actual_indexes = self::read_table_indexes(
			$table_key
		);

		foreach (
			self::REQUIRED_INDEXES[ $table_key ]
			as $index_name => $expected
		) {
			if ( ! isset( $actual_indexes[ $index_name ] ) ) {
				throw new RuntimeException(
					'missing_database_index'
				);
			}

			$actual = $actual_indexes[ $index_name ];

			if (
				$expected['unique'] !== $actual['unique']
				|| $expected['columns'] !== $actual['columns']
			) {
				throw new RuntimeException(
					'invalid_database_index'
				);
			}
		}
	}

	/**
	 * Đọc index của bảng.
	 *
	 * @param string $table_key Khóa bảng.
	 * @return array<string, array{unique: bool, columns: array<int, string>}>
	 */
	private static function read_table_indexes(
		string $table_key
	): array {
		global $wpdb;

		$table_name = Power_Schedule_Manager_Database::table(
			$table_key
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW INDEX FROM %i',
				$table_name
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			throw new RuntimeException(
				'unable_to_read_database_indexes'
			);
		}

		$indexes = array();

		foreach ( $rows as $row ) {
			if (
				! isset(
					$row['Key_name'],
					$row['Column_name'],
					$row['Seq_in_index'],
					$row['Non_unique']
				)
			) {
				continue;
			}

			$key_name = (string) $row['Key_name'];
			$position = max(
				0,
				(int) $row['Seq_in_index'] - 1
			);

			if ( ! isset( $indexes[ $key_name ] ) ) {
				$indexes[ $key_name ] = array(
					'unique'  =>
						0 === (int) $row['Non_unique'],
					'columns' => array(),
				);
			}

			$indexes[ $key_name ]['columns'][ $position ] =
				(string) $row['Column_name'];
		}

		foreach ( $indexes as &$index ) {
			ksort( $index['columns'] );

			$index['columns'] = array_values(
				$index['columns']
			);
		}
		unset( $index );

		return $indexes;
	}

	/**
	 * Lưu schema version.
	 *
	 * @return void
	 */
	private static function store_schema_version(): void {
		$updated = update_option(
			POWER_SCHEDULE_MANAGER_SCHEMA_OPTION,
			POWER_SCHEDULE_MANAGER_SCHEMA_VERSION,
			false
		);

		$stored = get_option(
			POWER_SCHEDULE_MANAGER_SCHEMA_OPTION,
			''
		);

		if (
			! $updated
			&& POWER_SCHEDULE_MANAGER_SCHEMA_VERSION !== $stored
		) {
			throw new RuntimeException(
				'schema_version_not_saved'
			);
		}
	}

	/**
	 * Lấy schema version đã cài.
	 *
	 * @return string
	 */
	private static function installed_version(): string {
		$version = get_option(
			POWER_SCHEDULE_MANAGER_SCHEMA_OPTION,
			'0.0.0'
		);

		if (
			! is_string( $version )
			|| 1 !== preg_match(
				'/^\d+\.\d+\.\d+$/',
				$version
			)
		) {
			return '0.0.0';
		}

		return $version;
	}

	/**
	 * Lấy named lock database.
	 *
	 * @return void
	 */
	private static function acquire_lock(): void {
		global $wpdb;

		$blog_id = function_exists( 'get_current_blog_id' )
			? get_current_blog_id()
			: 1;

		self::$lock_name = 'psm_migration_'
			. substr(
				hash(
					'sha256',
					$wpdb->prefix . '|' . $blog_id
				),
				0,
				40
			);

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, 0)',
				self::$lock_name
			)
		);

		if ( 1 !== (int) $result ) {
			self::$lock_name = '';

			throw new RuntimeException(
				'migration_lock_unavailable'
			);
		}

		self::$lock_token = bin2hex(
			random_bytes( 16 )
		);

		update_option(
			POWER_SCHEDULE_MANAGER_MIGRATION_LOCK_OPTION,
			array(
				'token'      => self::$lock_token,
				'created_at' => time(),
			),
			false
		);
	}

	/**
	 * Giải phóng named lock.
	 *
	 * @return void
	 */
	private static function release_lock(): void {
		global $wpdb;

		if ( '' === self::$lock_name ) {
			return;
		}

		$stored_lock = get_option(
			POWER_SCHEDULE_MANAGER_MIGRATION_LOCK_OPTION,
			array()
		);

		$stored_token = is_array( $stored_lock )
			&& isset( $stored_lock['token'] )
				? (string) $stored_lock['token']
				: '';

		if (
			'' !== self::$lock_token
			&& '' !== $stored_token
			&& hash_equals(
				self::$lock_token,
				$stored_token
			)
		) {
			delete_option(
				POWER_SCHEDULE_MANAGER_MIGRATION_LOCK_OPTION
			);
		}

		$wpdb->get_var(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s)',
				self::$lock_name
			)
		);

		self::$lock_name  = '';
		self::$lock_token = '';
	}

	/**
	 * Ghi lỗi migration khi WP_DEBUG bật.
	 *
	 * @param string    $context   Ngữ cảnh.
	 * @param Throwable $throwable Exception.
	 * @return void
	 */
	private static function log_migration_error(
		string $context,
		Throwable $throwable
	): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context = preg_replace(
			'/[^a-z0-9_-]/',
			'',
			strtolower( $context )
		);

		error_log(
			sprintf(
				'Cúp Điện Lâm Đồng migration [%1$s]: %2$s',
				substr( (string) $context, 0, 80 ),
				power_schedule_manager_sanitize_log_value(
					$throwable->getMessage()
				)
			)
		);
	}
}
