<?php
/**
 * Versioned backup and safe restore service.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exports and restores plugin-owned data without relying on a fixed DB prefix.
 *
 * The archive is newline-delimited JSON (NDJSON). Every line is hashed in
 * sequence and the final footer stores the SHA-256 checksum. Restore validates
 * the complete archive before writing anything and refuses to run when the
 * destination already contains operational plugin data.
 */
final class Power_Schedule_Manager_Backup {

	public const string FORMAT = 'power-schedule-manager-backup';

	public const int FORMAT_VERSION = 1;

	private const int BATCH_SIZE = 500;

	private const int MAX_UPLOAD_BYTES = 134217728;

	/**
	 * Schema versions whose exported business data can be restored safely.
	 *
	 * Schema 1.2.x adds operational indexes and the notification queue without
	 * changing exported business records. Schema 1.3.0 adds donation
	 * declarations. The outbound notification queue remains intentionally
	 * excluded from backups.
	 *
	 * @var array<int, string>
	 */
	private const array SUPPORTED_RESTORE_SCHEMA_VERSIONS = array(
		'1.1.6',
		'1.2.0',
		'1.2.1',
		'1.2.2',
		'1.3.0',
		'1.4.0',
		'1.5.0',
		'1.6.0',
		'1.7.0',
		'1.7.1',
		'1.7.2',
		'1.7.3',
	);

	/**
	 * Stable primary-key order used by streaming exports.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const array TABLE_ORDER_COLUMNS = array(
		Power_Schedule_Manager_Database::UNITS =>
			array( 'id' ),
		Power_Schedule_Manager_Database::IMPORT_RUNS =>
			array( 'id' ),
		Power_Schedule_Manager_Database::EVENTS =>
			array( 'id' ),
		Power_Schedule_Manager_Database::EVENT_LOCATIONS =>
			array( 'id' ),
		Power_Schedule_Manager_Database::EVENT_REVISIONS =>
			array( 'id' ),
		Power_Schedule_Manager_Database::PLACES =>
			array( 'id' ),
		Power_Schedule_Manager_Database::PLACE_ALIASES =>
			array( 'id' ),
		Power_Schedule_Manager_Database::EVENT_PLACES =>
			array( 'event_id', 'place_id' ),
		Power_Schedule_Manager_Database::DONATIONS =>
			array( 'id' ),
		Power_Schedule_Manager_Database::MARKET_PRICES =>
			array( 'id' ),
		Power_Schedule_Manager_Database::LOTTERY_DRAWS =>
			array( 'id' ),
	);

	private const string EXPORT_ACTION = 'psm_export_backup';

	private const string RESTORE_ACTION = 'psm_restore_backup';

	private const string EXPORT_NONCE = 'psm_export_backup';

	private const string RESTORE_NONCE = 'psm_restore_backup';

	private const string CLOUD_OPTION =
		'power_schedule_manager_backup_cloud';

	private const string CLOUD_SAVE_ACTION = 'psm_backup_cloud_save';

	private const string CLOUD_TEST_ACTION = 'psm_backup_cloud_test';

	private const string CLOUD_BACKUP_ACTION = 'psm_backup_cloud_create';

	private const string CLOUD_RESTORE_ACTION = 'psm_backup_cloud_restore';

	/** @var resource|null */
	private $output_handle = null;

	/**
	 * Binary columns encoded as lowercase hexadecimal in an archive.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const array BINARY_COLUMNS = array(
		Power_Schedule_Manager_Database::IMPORT_RUNS =>
			array( 'payload_hash', 'preview_token_hash' ),
		Power_Schedule_Manager_Database::EVENTS =>
			array( 'identity_hash', 'content_hash' ),
		Power_Schedule_Manager_Database::EVENT_REVISIONS =>
			array( 'content_hash' ),
		Power_Schedule_Manager_Database::PLACES =>
			array( 'normalized_hash' ),
		Power_Schedule_Manager_Database::PLACE_ALIASES =>
			array( 'alias_hash' ),
		Power_Schedule_Manager_Database::DONATIONS =>
			array( 'reference_hash', 'ip_hash' ),
		Power_Schedule_Manager_Database::MARKET_PRICES =>
			array( 'data_hash' ),
		Power_Schedule_Manager_Database::LOTTERY_DRAWS =>
			array( 'draw_key', 'data_hash' ),
	);

	/**
	 * Columns accepted from backup rows.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const array TABLE_COLUMNS = array(
		Power_Schedule_Manager_Database::UNITS => array(
			'id', 'code', 'name', 'slug', 'parent_code', 'region',
			'source_url', 'timezone', 'is_public', 'sort_order', 'metadata',
			'created_at_utc', 'updated_at_utc',
		),
		Power_Schedule_Manager_Database::IMPORT_RUNS => array(
			'id', 'run_uuid', 'user_id', 'unit_id', 'unit_code', 'source',
			'source_url', 'status', 'payload_hash', 'preview_token_hash',
			'raw_payload', 'payload_bytes', 'found_count', 'inserted_count',
			'updated_count', 'unchanged_count', 'duplicate_count',
			'warning_count', 'error_count', 'error_code', 'error_message',
			'started_at_utc', 'finished_at_utc', 'expires_at_utc',
			'created_at_utc', 'updated_at_utc',
		),
		Power_Schedule_Manager_Database::EVENTS => array(
			'id', 'unit_id', 'unit_code', 'source', 'source_event_id',
			'identity_hash', 'content_hash', 'local_date', 'start_at_utc',
			'end_at_utc', 'area', 'reason', 'status', 'import_run_id',
			'post_id', 'missing_count', 'sync_count', 'first_seen_at_utc',
			'last_seen_at_utc', 'created_at_utc', 'updated_at_utc',
			'deleted_at_utc',
		),
		Power_Schedule_Manager_Database::EVENT_LOCATIONS => array(
			'id', 'event_id', 'location_type', 'label', 'description',
			'geojson', 'center_lat', 'center_lng', 'default_zoom',
			'sort_order', 'created_at_utc', 'updated_at_utc',
		),
		Power_Schedule_Manager_Database::EVENT_REVISIONS => array(
			'id', 'event_id', 'import_run_id', 'user_id', 'revision_number',
			'change_type', 'content_hash', 'before_data', 'after_data',
			'created_at_utc',
		),
		Power_Schedule_Manager_Database::PLACES => array(
			'id', 'unit_id', 'unit_code', 'canonical_name',
			'normalized_hash', 'location_type', 'description', 'geojson',
			'center_lat', 'center_lng', 'default_zoom', 'status',
			'created_at_utc', 'updated_at_utc',
		),
		Power_Schedule_Manager_Database::PLACE_ALIASES => array(
			'id', 'place_id', 'unit_id', 'alias', 'alias_hash',
			'created_at_utc', 'updated_at_utc',
		),
		Power_Schedule_Manager_Database::EVENT_PLACES => array(
			'event_id', 'place_id', 'sort_order', 'link_source',
			'created_at_utc',
		),
		Power_Schedule_Manager_Database::DONATIONS => array(
			'id', 'receipt_code', 'reference_hash', 'method', 'donor_name',
			'donor_email', 'donor_phone', 'amount', 'transfer_reference',
			'message', 'status', 'ip_hash', 'submitted_at_utc',
			'confirmed_at_utc', 'confirmed_by', 'updated_at_utc',
		),
		Power_Schedule_Manager_Database::MARKET_PRICES => array(
			'id', 'commodity', 'market_code', 'label', 'contract_code',
			'price_date', 'price', 'buy_price', 'sell_price',
			'change_value', 'change_percent', 'buy_change', 'sell_change',
			'high_price', 'low_price', 'open_price', 'previous_close',
			'volume', 'open_interest', 'unit', 'currency', 'source_name',
			'source_url', 'provider_code', 'observed_at_utc',
			'fetched_at_utc', 'data_hash', 'is_public', 'created_by',
			'created_at_utc', 'updated_at_utc',
		),
		Power_Schedule_Manager_Database::LOTTERY_DRAWS => array(
			'id', 'provider_draw_id', 'draw_key', 'region',
			'province_code', 'province_name', 'game_type', 'draw_date',
			'draw_time', 'status', 'special_prize', 'results_json',
			'source_payload_json', 'provider_code', 'source_url',
			'observed_at_utc', 'fetched_at_utc', 'data_hash', 'is_public',
			'created_at_utc', 'updated_at_utc',
		),
	);

	/**
	 * Register protected admin-post endpoints.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_post_' . self::EXPORT_ACTION,
			array( $this, 'export' )
		);

		add_action(
			'admin_post_' . self::RESTORE_ACTION,
			array( $this, 'restore' )
		);
		add_action(
			'admin_post_' . self::CLOUD_SAVE_ACTION,
			array( $this, 'save_cloud' )
		);
		add_action(
			'admin_post_' . self::CLOUD_TEST_ACTION,
			array( $this, 'test_cloud' )
		);
		add_action(
			'admin_post_' . self::CLOUD_BACKUP_ACTION,
			array( $this, 'backup_to_cloud' )
		);
		add_action(
			'admin_post_' . self::CLOUD_RESTORE_ACTION,
			array( $this, 'restore_from_cloud' )
		);
	}

	/**
	 * Return cloud configuration without exposing secrets.
	 *
	 * @return array<string,mixed>
	 */
	public static function cloud_summary(): array {
		$config = self::cloud_config();

		return array(
			'wasabi_endpoint' => (string) ( $config['wasabi_endpoint'] ?? 'https://s3.ap-southeast-1.wasabisys.com' ),
			'wasabi_region'   => (string) ( $config['wasabi_region'] ?? 'ap-southeast-1' ),
			'wasabi_bucket'   => (string) ( $config['wasabi_bucket'] ?? '' ),
			'wasabi_prefix'   => (string) ( $config['wasabi_prefix'] ?? 'power-schedule-manager' ),
			'wasabi_access_key' => (string) ( $config['wasabi_access_key'] ?? '' ),
			'wasabi_has_secret' => '' !== (string) ( $config['wasabi_secret_encrypted'] ?? '' )
				|| '' !== (string) ( $config['wasabi_secret_plain'] ?? '' ),
			'google_client_id' => (string) ( $config['google_client_id'] ?? '' ),
			'google_folder_id' => (string) ( $config['google_folder_id'] ?? '' ),
			'google_has_secret' => '' !== (string) ( $config['google_client_secret_encrypted'] ?? '' )
				|| '' !== (string) ( $config['google_client_secret_plain'] ?? '' ),
			'google_has_refresh_token' => '' !== (string) ( $config['google_refresh_token_encrypted'] ?? '' )
				|| '' !== (string) ( $config['google_refresh_token_plain'] ?? '' ),
			'last_test' => is_array( $config['last_test'] ?? null )
				? $config['last_test']
				: array(),
			'latest' => is_array( $config['latest'] ?? null )
				? $config['latest']
				: array(),
		);
	}

	/**
	 * Save encrypted cloud connection settings.
	 */
	public function save_cloud(): never {
		$this->assert_access();
		check_admin_referer( self::CLOUD_SAVE_ACTION );
		$current = get_option( self::CLOUD_OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$endpoint = esc_url_raw(
			wp_unslash( (string) ( $_POST['wasabi_endpoint'] ?? '' ) )
		);
		$endpoint_host = strtolower(
			(string) wp_parse_url( $endpoint, PHP_URL_HOST )
		);
		if (
			! str_starts_with( $endpoint, 'https://' )
			|| ! (
				'wasabisys.com' === $endpoint_host
				|| str_ends_with( $endpoint_host, '.wasabisys.com' )
			)
		) {
			$endpoint = 'https://s3.ap-southeast-1.wasabisys.com';
		}
		$config = array(
			'wasabi_endpoint' => untrailingslashit( $endpoint ),
			'wasabi_region' => sanitize_key( wp_unslash( (string) ( $_POST['wasabi_region'] ?? '' ) ) ),
			'wasabi_bucket' => strtolower( preg_replace( '/[^a-z0-9.-]/', '', wp_unslash( (string) ( $_POST['wasabi_bucket'] ?? '' ) ) ) ),
			'wasabi_prefix' => trim( sanitize_text_field( wp_unslash( (string) ( $_POST['wasabi_prefix'] ?? '' ) ) ), '/' ),
			'wasabi_access_key' => sanitize_text_field( wp_unslash( (string) ( $_POST['wasabi_access_key'] ?? '' ) ) ),
			'wasabi_secret_encrypted' => (string) ( $current['wasabi_secret_encrypted'] ?? '' ),
			'google_client_id' => sanitize_text_field( wp_unslash( (string) ( $_POST['google_client_id'] ?? '' ) ) ),
			'google_folder_id' => sanitize_text_field( wp_unslash( (string) ( $_POST['google_folder_id'] ?? '' ) ) ),
			'google_client_secret_encrypted' => (string) ( $current['google_client_secret_encrypted'] ?? '' ),
			'google_refresh_token_encrypted' => (string) ( $current['google_refresh_token_encrypted'] ?? '' ),
			'last_test' => is_array( $current['last_test'] ?? null ) ? $current['last_test'] : array(),
		);
		foreach (
			array(
				'wasabi_secret' => 'wasabi_secret_encrypted',
				'google_client_secret' => 'google_client_secret_encrypted',
				'google_refresh_token' => 'google_refresh_token_encrypted',
			) as $input => $target
		) {
			$value = trim( wp_unslash( (string) ( $_POST[ $input ] ?? '' ) ) );
			if ( '' === $value ) {
				continue;
			}
			$encrypted = Power_Schedule_Manager_Secrets::encrypt( $value );
			if ( ! is_wp_error( $encrypted ) ) {
				$config[ $target ] = $encrypted;
			}
		}
		update_option( self::CLOUD_OPTION, $config, false );
		self::store_notice(
			array(
				'type' => 'success',
				'message' => __( 'Đã lưu cấu hình kho sao lưu. Khóa bí mật được mã hóa theo WordPress salts.', 'power-schedule-manager' ),
			)
		);
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Verify a cloud connection with a read-only request.
	 */
	public function test_cloud(): never {
		$this->assert_access();
		check_admin_referer( self::CLOUD_TEST_ACTION );
		$provider = sanitize_key( wp_unslash( (string) ( $_POST['provider'] ?? '' ) ) );
		$config = self::cloud_config();
		$result = 'wasabi' === $provider
			? $this->test_wasabi( $config )
			: $this->test_google_drive( $config );
		$config['last_test'][ $provider ] = array(
			'ok' => ! is_wp_error( $result ),
			'at' => current_time( 'mysql' ),
		);
		update_option( self::CLOUD_OPTION, $config, false );
		self::store_notice(
			array(
				'type' => is_wp_error( $result ) ? 'error' : 'success',
				'message' => is_wp_error( $result )
					? $result->get_error_message()
					: (string) $result,
			)
		);
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Create a verified archive and upload it to the selected provider.
	 */
	public function backup_to_cloud(): never {
		$this->assert_access();
		check_admin_referer( self::CLOUD_BACKUP_ACTION );
		$provider = sanitize_key(
			wp_unslash( (string) ( $_POST['provider'] ?? '' ) )
		);
		$path = '';
		try {
			if ( ! in_array( $provider, array( 'wasabi', 'google' ), true ) ) {
				throw new RuntimeException( 'cloud_provider_invalid' );
			}
			if ( ! Power_Schedule_Manager_Database::all_tables_exist() ) {
				throw new RuntimeException( 'backup_schema_incomplete' );
			}
			$path = $this->create_archive_file(
				! empty( $_POST['include_history'] )
			);
			$config = self::cloud_config();
			$result = 'wasabi' === $provider
				? $this->upload_wasabi( $path, $config )
				: $this->upload_google_drive( $path, $config );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( $result->get_error_message() );
			}
			$stored = get_option( self::CLOUD_OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();
			$stored['latest'][ $provider ] = $result;
			update_option( self::CLOUD_OPTION, $stored, false );
			self::store_notice(
				array(
					'type' => 'success',
					'message' => sprintf(
						/* translators: %s: Provider. */
						__( 'Đã tạo, kiểm tra checksum và lưu backup lên %s.', 'power-schedule-manager' ),
						'wasabi' === $provider ? 'Wasabi' : 'Google Drive'
					),
				)
			);
		} catch ( Throwable $throwable ) {
			self::store_notice(
				array(
					'type' => 'error',
					'message' => sprintf(
						/* translators: %s: Error. */
						__( 'Không thể sao lưu lên cloud: %s', 'power-schedule-manager' ),
						$throwable->getMessage()
					),
				)
			);
		} finally {
			if ( '' !== $path && is_file( $path ) ) {
				unlink( $path );
			}
		}
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Download the latest cloud archive, validate it, then restore safely.
	 */
	public function restore_from_cloud(): never {
		$this->assert_access();
		check_admin_referer( self::CLOUD_RESTORE_ACTION );
		$provider = sanitize_key(
			wp_unslash( (string) ( $_POST['provider'] ?? '' ) )
		);
		$path = '';
		try {
			if ( empty( $_POST['confirm_restore'] ) ) {
				throw new RuntimeException( 'restore_confirmation_required' );
			}
			if ( ! self::destination_is_empty() ) {
				throw new RuntimeException( 'restore_destination_not_empty' );
			}
			$config = self::cloud_config();
			$latest = get_option( self::CLOUD_OPTION, array() );
			$latest = is_array( $latest ) ? $latest : array();
			$remote = is_array( $latest['latest'][ $provider ] ?? null )
				? $latest['latest'][ $provider ]
				: array();
			$locator = trim(
				sanitize_text_field(
					wp_unslash( (string) ( $_POST['remote_locator'] ?? '' ) )
				)
			);
			if ( '' !== $locator ) {
				$remote = 'wasabi' === $provider
					? array( 'key' => $locator )
					: array( 'id' => $locator );
			}
			$path = 'wasabi' === $provider
				? $this->download_wasabi( $remote, $config )
				: $this->download_google_drive( $remote, $config );
			$this->validate_archive( $path );
			if ( ! Power_Schedule_Manager_Database::acquire_write_lock( 10 ) ) {
				throw new RuntimeException( 'restore_write_lock_unavailable' );
			}
			try {
				$result = $this->apply_archive( $path );
			} finally {
				Power_Schedule_Manager_Database::release_write_lock();
			}
			self::store_notice(
				array(
					'type' => 'success',
					'message' => __( 'Đã tải, xác minh và khôi phục bản cloud gần nhất.', 'power-schedule-manager' ),
					'details' => $result,
				)
			);
		} catch ( Throwable $throwable ) {
			self::store_notice(
				array(
					'type' => 'error',
					'message' => sprintf(
						/* translators: %s: Error. */
						__( 'Không thể khôi phục từ cloud: %s', 'power-schedule-manager' ),
						$throwable->getMessage()
					),
				)
			);
		} finally {
			if ( '' !== $path && is_file( $path ) ) {
				unlink( $path );
			}
		}
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Generate a temporary NDJSON archive.
	 */
	private function create_archive_file( bool $include_history ): string {
		$path = wp_tempnam( 'psm-cloud-backup-' );
		if ( ! is_string( $path ) || '' === $path ) {
			throw new RuntimeException( 'backup_temp_unavailable' );
		}
		$handle = fopen( $path, 'wb' );
		if ( false === $handle ) {
			throw new RuntimeException( 'backup_temp_unavailable' );
		}
		$this->output_handle = $handle;
		try {
			$this->generate_archive( $include_history );
		} finally {
			fclose( $handle );
			$this->output_handle = null;
		}
		$this->validate_archive( $path );

		return $path;
	}

	/**
	 * Merge encrypted WordPress settings with immutable Docker constants.
	 *
	 * Database values win so administrators can rotate a connection without
	 * rebuilding the container. Constants provide a rebuild-safe fallback.
	 *
	 * @return array<string,mixed>
	 */
	private static function cloud_config(): array {
		$config = get_option( self::CLOUD_OPTION, array() );
		$config = is_array( $config ) ? $config : array();
		$constant_map = array(
			'wasabi_endpoint' => 'POWER_SCHEDULE_MANAGER_BACKUP_WASABI_ENDPOINT',
			'wasabi_region' => 'POWER_SCHEDULE_MANAGER_BACKUP_WASABI_REGION',
			'wasabi_bucket' => 'POWER_SCHEDULE_MANAGER_BACKUP_WASABI_BUCKET',
			'wasabi_prefix' => 'POWER_SCHEDULE_MANAGER_BACKUP_WASABI_PREFIX',
			'wasabi_access_key' => 'POWER_SCHEDULE_MANAGER_BACKUP_WASABI_ACCESS_KEY',
			'google_client_id' => 'POWER_SCHEDULE_MANAGER_BACKUP_GOOGLE_CLIENT_ID',
			'google_folder_id' => 'POWER_SCHEDULE_MANAGER_BACKUP_GOOGLE_FOLDER_ID',
		);
		foreach ( $constant_map as $key => $constant ) {
			if (
				( ! isset( $config[ $key ] ) || '' === (string) $config[ $key ] )
				&& defined( $constant )
			) {
				$config[ $key ] = (string) constant( $constant );
			}
		}
		$secret_map = array(
			'wasabi_secret_plain' => 'POWER_SCHEDULE_MANAGER_BACKUP_WASABI_SECRET_KEY',
			'google_client_secret_plain' => 'POWER_SCHEDULE_MANAGER_BACKUP_GOOGLE_CLIENT_SECRET',
			'google_refresh_token_plain' => 'POWER_SCHEDULE_MANAGER_BACKUP_GOOGLE_REFRESH_TOKEN',
		);
		foreach ( $secret_map as $key => $constant ) {
			if ( defined( $constant ) ) {
				$config[ $key ] = (string) constant( $constant );
			}
		}

		return $config;
	}

	/**
	 * Return a small read-only dataset summary for the admin page.
	 *
	 * @return array<string, mixed>
	 */
	public static function summary(): array {
		global $wpdb;

		$tables = array();

		foreach ( Power_Schedule_Manager_Database::table_keys() as $key ) {
			$count = null;

			if ( Power_Schedule_Manager_Database::table_exists( $key ) ) {
				$table = Power_Schedule_Manager_Database::table( $key );
				$value = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
				$count = is_numeric( $value ) ? (int) $value : null;
			}

			$tables[ $key ] = $count;
		}

		$post_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->posts}`
				WHERE post_type = %s AND post_status <> 'auto-draft'",
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		);

		return array(
			'tables'       => $tables,
			'posts'        => is_numeric( $post_count )
				? (int) $post_count
				: 0,
			'can_restore'  => self::destination_is_empty(),
			'max_upload'   => self::max_upload_bytes(),
			'format'       => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
		);
	}

	/**
	 * Consume the current user's one-time result message.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function consume_notice(): ?array {
		$key    = self::notice_key();
		$notice = get_transient( $key );

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Stream a complete backup archive to the browser.
	 *
	 * @return never
	 */
	public function export(): never {
		global $wpdb;

		$this->assert_access();
		check_admin_referer( self::EXPORT_NONCE );

		if ( ! Power_Schedule_Manager_Database::all_tables_exist() ) {
			wp_die(
				esc_html__(
					'Không thể sao lưu vì schema plugin chưa đầy đủ.',
					'power-schedule-manager'
				),
				esc_html__( 'Sao lưu thất bại', 'power-schedule-manager' ),
				array( 'response' => 500 )
			);
		}

		$include_history = ! empty( $_POST['include_history'] );
		$filename        = sprintf(
			'power-schedule-manager-%s.ndjson',
			gmdate( 'Ymd-His' )
		);

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/x-ndjson; charset=UTF-8' );
		header(
			'Content-Disposition: attachment; filename="'
				. $filename
				. '"'
		);
		header( 'X-Content-Type-Options: nosniff' );

		$this->generate_archive( $include_history );
		exit;
	}

	/**
	 * Generate an archive to the active output stream.
	 */
	private function generate_archive( bool $include_history ): void {
		global $wpdb;
		$hash   = hash_init( 'sha256' );
		$counts = array();

		$wpdb->query(
			'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'
		);
		$wpdb->query(
			'START TRANSACTION WITH CONSISTENT SNAPSHOT'
		);

		$this->write_record(
			array(
				'record'          => 'manifest',
				'format'          => self::FORMAT,
				'format_version'  => self::FORMAT_VERSION,
				'created_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
				'plugin_version'  => POWER_SCHEDULE_MANAGER_VERSION,
				'schema_version'  => POWER_SCHEDULE_MANAGER_SCHEMA_VERSION,
				'seed_version'    => POWER_SCHEDULE_MANAGER_SEED_VERSION,
				'timezone'        => POWER_SCHEDULE_MANAGER_TIMEZONE,
				'site_url'        => home_url( '/' ),
				'include_history' => $include_history,
			),
			$hash,
			$counts
		);

		$exported_settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$exported_settings = is_array( $exported_settings )
			? $exported_settings
			: array();

		/*
		 * Encrypted credentials are intentionally not portable because their
		 * encryption key is derived from this site's WordPress salts.
		 */
		unset(
			$exported_settings['maptiler_key_encrypted'],
			$exported_settings['stadia_key_encrypted'],
			$exported_settings['cloudflare_api_token_encrypted'],
			$exported_settings['cloudflare_turnstile_secret_encrypted'],
			$exported_settings['onesignal_rest_api_key_encrypted'],
			$exported_settings['telegram_bot_token_encrypted'],
			$exported_settings['webhook_secret_encrypted'],
			$exported_settings['zalo_access_token_encrypted']
		);

		$this->write_record(
			array(
				'record' => 'settings',
				'value'  => $exported_settings,
			),
			$hash,
			$counts
		);

		$this->export_table(
			Power_Schedule_Manager_Database::UNITS,
			$hash,
			$counts
		);
		$this->export_terms( $hash, $counts );
		$this->export_posts( $hash, $counts );

		if ( $include_history ) {
			$this->export_table(
				Power_Schedule_Manager_Database::IMPORT_RUNS,
				$hash,
				$counts
			);
		}

		$this->export_table(
			Power_Schedule_Manager_Database::EVENTS,
			$hash,
			$counts,
			! $include_history
		);
		$this->export_table(
			Power_Schedule_Manager_Database::EVENT_LOCATIONS,
			$hash,
			$counts
		);
		$this->export_table(
			Power_Schedule_Manager_Database::PLACES,
			$hash,
			$counts
		);
		$this->export_table(
			Power_Schedule_Manager_Database::PLACE_ALIASES,
			$hash,
			$counts
		);
		$this->export_table(
			Power_Schedule_Manager_Database::EVENT_PLACES,
			$hash,
			$counts
		);

		if ( $include_history ) {
			$this->export_table(
				Power_Schedule_Manager_Database::EVENT_REVISIONS,
				$hash,
				$counts
			);
		}

		$this->export_table(
			Power_Schedule_Manager_Database::DONATIONS,
			$hash,
			$counts
		);
		$this->export_table(
			Power_Schedule_Manager_Database::MARKET_PRICES,
			$hash,
			$counts
		);
		$this->export_table(
			Power_Schedule_Manager_Database::LOTTERY_DRAWS,
			$hash,
			$counts
		);

		$wpdb->query( 'COMMIT' );

		$footer = array(
			'record'   => 'footer',
			'checksum' => hash_final( $hash ),
			'counts'   => $counts,
		);

		$this->emit( self::encode_record( $footer ) . "\n" );
	}

	/**
	 * Validate and restore an uploaded archive into an empty destination.
	 *
	 * @return never
	 */
	public function restore(): never {
		$this->assert_access();
		check_admin_referer( self::RESTORE_NONCE );

		try {
			if ( empty( $_POST['confirm_restore'] ) ) {
				throw new RuntimeException( 'restore_confirmation_required' );
			}

			$file = $this->uploaded_file();

			$this->validate_archive( $file );

			if ( ! Power_Schedule_Manager_Database::acquire_write_lock( 10 ) ) {
				throw new RuntimeException( 'restore_write_lock_unavailable' );
			}

			try {
				if ( ! self::destination_is_empty() ) {
					throw new RuntimeException(
						'restore_destination_not_empty'
					);
				}

				$result = $this->apply_archive( $file );
			} finally {
				Power_Schedule_Manager_Database::release_write_lock();
			}

			self::store_notice(
				array(
					'type'    => 'success',
					'message' => __(
						'Khôi phục hoàn tất. Dữ liệu, bài lịch, đơn vị và thư viện bản đồ đã được kiểm tra trước khi ghi.',
						'power-schedule-manager'
					),
					'details' => $result,
				)
			);
		} catch ( Throwable $throwable ) {
			Power_Schedule_Manager_Logger::error(
				'backup_restore_failed',
				$throwable,
				array( 'user_id' => get_current_user_id() )
			);

			self::store_notice(
				array(
					'type'    => 'error',
					'message' => self::restore_error_message(
						$throwable->getMessage()
					),
				)
			);
		}

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Export one table in bounded batches.
	 *
	 * @param string             $table_key       Logical table key.
	 * @param HashContext        $hash            Archive hash.
	 * @param array<string, int> $counts          Record counts.
	 * @param bool               $detach_history  Zero import references.
	 *
	 * @return void
	 */
	private function export_table(
		string $table_key,
		HashContext $hash,
		array &$counts,
		bool $detach_history = false
	): void {
		global $wpdb;

		$table         = Power_Schedule_Manager_Database::table( $table_key );
		$order_columns = self::TABLE_ORDER_COLUMNS[ $table_key ] ?? array();

		if ( 1 === count( $order_columns ) ) {
			$cursor = array( 0 );
		} elseif ( 2 === count( $order_columns ) ) {
			$cursor = array( 0, 0 );
		} else {
			throw new RuntimeException( 'backup_table_order_invalid' );
		}

		do {
			if ( 1 === count( $order_columns ) ) {
				$column = $order_columns[0];
				$query  = $wpdb->prepare(
					"SELECT * FROM `{$table}`
					WHERE `{$column}` > %d
					ORDER BY `{$column}` ASC
					LIMIT %d",
					$cursor[0],
					self::BATCH_SIZE
				);
			} else {
				$first_column  = $order_columns[0];
				$second_column = $order_columns[1];
				$query         = $wpdb->prepare(
					"SELECT * FROM `{$table}`
					WHERE (
						`{$first_column}` > %d
						OR (
							`{$first_column}` = %d
							AND `{$second_column}` > %d
						)
					)
					ORDER BY
						`{$first_column}` ASC,
						`{$second_column}` ASC
					LIMIT %d",
					$cursor[0],
					$cursor[0],
					$cursor[1],
					self::BATCH_SIZE
				);
			}

			$rows  = $wpdb->get_results( $query, ARRAY_A );

			if ( ! is_array( $rows ) ) {
				throw new RuntimeException( 'backup_table_read_failed' );
			}

			foreach ( $rows as $row ) {
				if (
					$detach_history
					&& Power_Schedule_Manager_Database::EVENTS === $table_key
				) {
					$row['import_run_id'] = 0;
				}

				$this->write_record(
					array(
						'record' => 'table_row',
						'table'  => $table_key,
						'row'    => self::encode_binary_columns(
							$table_key,
							$row
						),
					),
					$hash,
					$counts
				);
			}

			if ( array() !== $rows ) {
				$last_row = $rows[ array_key_last( $rows ) ];

				foreach ( $order_columns as $index => $column ) {
					if (
						! isset( $last_row[ $column ] )
						|| ! is_numeric( $last_row[ $column ] )
					) {
						throw new RuntimeException(
							'backup_table_cursor_invalid'
						);
					}

					$cursor[ $index ] = (int) $last_row[ $column ];
				}
			}
		} while ( count( $rows ) === self::BATCH_SIZE );
	}

	/**
	 * Export taxonomy terms and plugin-owned term metadata.
	 *
	 * @param HashContext        $hash   Archive hash.
	 * @param array<string, int> $counts Record counts.
	 *
	 * @return void
	 */
	private function export_terms(
		HashContext $hash,
		array &$counts
	): void {
		$terms = get_terms(
			array(
				'taxonomy'   => Power_Schedule_Manager_Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			throw new RuntimeException( 'backup_terms_read_failed' );
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$this->write_record(
				array(
					'record' => 'term',
					'value'  => array(
						'term_id'     => $term->term_id,
						'name'        => $term->name,
						'slug'        => $term->slug,
						'description' => $term->description,
						'parent'      => $term->parent,
						'meta'        => array(
							Power_Schedule_Manager_Taxonomy::META_UNIT_CODE =>
								get_term_meta(
									$term->term_id,
									Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
									true
								),
							Power_Schedule_Manager_Taxonomy::META_IS_PUBLIC =>
								get_term_meta(
									$term->term_id,
									Power_Schedule_Manager_Taxonomy::META_IS_PUBLIC,
									true
								),
							Power_Schedule_Manager_Taxonomy::META_DISPLAY_ORDER =>
								get_term_meta(
									$term->term_id,
									Power_Schedule_Manager_Taxonomy::META_DISPLAY_ORDER,
									true
								),
							Power_Schedule_Manager_Taxonomy::META_SHORT_DESCRIPTION =>
								get_term_meta(
									$term->term_id,
									Power_Schedule_Manager_Taxonomy::META_SHORT_DESCRIPTION,
									true
								),
						),
					),
				),
				$hash,
				$counts
			);
		}
	}

	/**
	 * Export plugin CPT records, plugin metadata, and area assignments.
	 *
	 * @param HashContext        $hash   Archive hash.
	 * @param array<string, int> $counts Record counts.
	 *
	 * @return void
	 */
	private function export_posts(
		HashContext $hash,
		array &$counts
	): void {
		$page = 1;

		do {
			$query = new WP_Query(
				array(
					'post_type'              =>
						Power_Schedule_Manager_Post_Type::POST_TYPE,
					'post_status'            => array(
						'publish', 'draft', 'pending', 'private', 'future',
					),
					'posts_per_page'         => 100,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => false,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => true,
				)
			);

			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$term_codes = array();
				$terms      = wp_get_post_terms(
					$post->ID,
					Power_Schedule_Manager_Taxonomy::TAXONOMY
				);

				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$code = get_term_meta(
							$term->term_id,
							Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
							true
						);

						if ( is_string( $code ) && '' !== $code ) {
							$term_codes[] = $code;
						}
					}
				}

				$this->write_record(
					array(
						'record' => 'post',
						'value'  => array(
							'post_id'        => $post->ID,
							'post_title'     => $post->post_title,
							'post_name'      => $post->post_name,
							'post_content'   => $post->post_content,
							'post_excerpt'   => $post->post_excerpt,
							'post_status'    => $post->post_status,
							'comment_status' => $post->comment_status,
							'ping_status'    => $post->ping_status,
							'menu_order'     => $post->menu_order,
							'post_date'      => $post->post_date,
							'post_date_gmt'  => $post->post_date_gmt,
							'post_modified' => $post->post_modified,
							'post_modified_gmt' =>
								$post->post_modified_gmt,
							'meta'           => array(
								Power_Schedule_Manager_Post_Type::META_UNIT_CODE =>
									get_post_meta(
										$post->ID,
										Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
										true
									),
								Power_Schedule_Manager_Post_Type::META_LOCAL_DATE =>
									get_post_meta(
										$post->ID,
										Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
										true
									),
								Power_Schedule_Manager_Post_Type::META_EVENT_COUNT =>
									get_post_meta(
										$post->ID,
										Power_Schedule_Manager_Post_Type::META_EVENT_COUNT,
										true
									),
								Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC =>
									get_post_meta(
										$post->ID,
										Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC,
										true
									),
							),
							'term_codes'     => array_values(
								array_unique( $term_codes )
							),
						),
					),
					$hash,
					$counts
				);
			}

			++$page;
		} while ( $page <= (int) $query->max_num_pages );

		wp_reset_postdata();
	}

	/**
	 * Validate structure, ordering, record types, size, and checksum.
	 *
	 * @param string $path Uploaded temporary path.
	 *
	 * @return void
	 */
	private function validate_archive( string $path ): void {
		$handle = fopen( $path, 'rb' );

		if ( false === $handle ) {
			throw new RuntimeException( 'restore_file_unreadable' );
		}

		$hash           = hash_init( 'sha256' );
		$line_number    = 0;
		$footer_seen    = false;
		$manifest_seen  = false;
		$expected_order = -1;
		$actual_counts  = array();

		try {
			while ( false !== ( $line = fgets( $handle ) ) ) {
				++$line_number;

				if ( strlen( $line ) > 16777216 ) {
					throw new RuntimeException( 'restore_line_too_large' );
				}

				$record = json_decode( $line, true, 64, JSON_THROW_ON_ERROR );

				if ( ! is_array( $record ) ) {
					throw new RuntimeException( 'restore_invalid_record' );
				}

				$type = isset( $record['record'] )
					&& is_string( $record['record'] )
						? $record['record']
						: '';

				if ( 'footer' === $type ) {
					$footer_seen = true;
					$expected    = isset( $record['checksum'] )
						&& is_string( $record['checksum'] )
							? strtolower( $record['checksum'] )
							: '';

					if (
						1 !== preg_match( '/\A[a-f0-9]{64}\z/', $expected )
						|| ! hash_equals( $expected, hash_final( $hash ) )
					) {
						throw new RuntimeException(
							'restore_checksum_mismatch'
						);
					}

					$footer_counts = isset( $record['counts'] )
						&& is_array( $record['counts'] )
							? array_map( 'absint', $record['counts'] )
							: array();

					if ( $footer_counts !== $actual_counts ) {
						throw new RuntimeException(
							'restore_record_count_mismatch'
						);
					}

					if ( ! feof( $handle ) ) {
						$remaining = stream_get_contents( $handle );

						if ( is_string( $remaining ) && '' !== trim( $remaining ) ) {
							throw new RuntimeException(
								'restore_footer_not_last'
							);
						}
					}

					break;
				}

				if ( $footer_seen ) {
					throw new RuntimeException( 'restore_footer_not_last' );
				}

				hash_update( $hash, $line );

				if ( 1 === $line_number ) {
					if (
						'manifest' !== $type
						|| self::FORMAT !== ( $record['format'] ?? '' )
						|| self::FORMAT_VERSION
							!== (int) ( $record['format_version'] ?? 0 )
					) {
						throw new RuntimeException(
							'restore_incompatible_format'
						);
					}

					if (
						! in_array(
							(string) ( $record['schema_version'] ?? '' ),
							self::SUPPORTED_RESTORE_SCHEMA_VERSIONS,
							true
						)
					) {
						throw new RuntimeException(
							'restore_incompatible_schema'
						);
					}

					$manifest_seen = true;
				}

				$order = self::record_order( $record );

				if ( $order < $expected_order ) {
					throw new RuntimeException( 'restore_invalid_order' );
				}

				$expected_order = $order;
				$actual_counts[ $type ] =
					( $actual_counts[ $type ] ?? 0 ) + 1;
			}
		} catch ( JsonException ) {
			throw new RuntimeException( 'restore_invalid_json' );
		} finally {
			fclose( $handle );
		}

		if ( ! $manifest_seen || ! $footer_seen ) {
			throw new RuntimeException( 'restore_incomplete_archive' );
		}
	}

	/**
	 * Apply an already validated archive.
	 *
	 * @param string $path Uploaded temporary path.
	 *
	 * @return array<string, int>
	 */
	private function apply_archive( string $path ): array {
		global $wpdb;

		$handle = fopen( $path, 'rb' );

		if ( false === $handle ) {
			throw new RuntimeException( 'restore_file_unreadable' );
		}

		$maps = array(
			'units'   => array(),
			'posts'   => array(),
			'imports' => array(),
			'events'  => array(),
			'places'  => array(),
			'terms'   => array(),
		);
		$created_posts = array();
		$settings      = null;
		$result        = array(
			'posts' => 0,
			'events' => 0,
			'places' => 0,
			'rows' => 0,
		);

		$wpdb->query( 'START TRANSACTION' );

		try {
			while ( false !== ( $line = fgets( $handle ) ) ) {
				$record = json_decode( $line, true, 64, JSON_THROW_ON_ERROR );
				$type   = is_array( $record )
					? (string) ( $record['record'] ?? '' )
					: '';

				if ( 'footer' === $type ) {
					break;
				}

				if ( 'settings' === $type ) {
					$settings = is_array( $record['value'] ?? null )
						? $record['value']
						: array();
					continue;
				}

				if ( 'term' === $type ) {
					$term_value = is_array( $record['value'] ?? null )
						? $record['value']
						: array();
					$old_term_id = absint( $term_value['term_id'] ?? 0 );
					$maps['terms'][ $old_term_id ] =
						$this->restore_term(
							$term_value,
							$maps['terms']
						);
					continue;
				}

				if ( 'post' === $type ) {
					$old_id = absint( $record['value']['post_id'] ?? 0 );
					$new_id = $this->restore_post(
						is_array( $record['value'] ?? null )
							? $record['value']
							: array()
					);
					$maps['posts'][ $old_id ] = $new_id;
					$created_posts[]          = $new_id;
					++$result['posts'];
					continue;
				}

				if ( 'table_row' !== $type ) {
					continue;
				}

				$table_key = is_string( $record['table'] ?? null )
					? $record['table']
					: '';
				$row       = is_array( $record['row'] ?? null )
					? self::decode_binary_columns(
						$table_key,
						$record['row']
					)
					: array();

				$this->restore_table_row( $table_key, $row, $maps );

				if ( Power_Schedule_Manager_Database::EVENTS === $table_key ) {
					++$result['events'];
				}
				if ( Power_Schedule_Manager_Database::PLACES === $table_key ) {
					++$result['places'];
				}
				++$result['rows'];
			}

			if ( is_array( $settings ) ) {
				$admin    = new Power_Schedule_Manager_Admin();
				$settings = $admin->sanitize_settings( $settings );

				update_option(
					POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
					$settings,
					false
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );

			foreach ( array_reverse( $created_posts ) as $post_id ) {
				wp_delete_post( $post_id, true );
			}

			throw $throwable;
		} finally {
			fclose( $handle );
		}

		Power_Schedule_Manager_Cache::invalidate_all();
		flush_rewrite_rules( false );

		return $result;
	}

	/**
	 * Restore one custom-table row with foreign-key ID remapping.
	 *
	 * @param string                           $table_key Table key.
	 * @param array<string, mixed>             $row       Row.
	 * @param array<string, array<int, int>>   $maps      ID maps.
	 *
	 * @return void
	 */
	private function restore_table_row(
		string $table_key,
		array $row,
		array &$maps
	): void {
		global $wpdb;

		if ( ! isset( self::TABLE_COLUMNS[ $table_key ] ) ) {
			throw new RuntimeException( 'restore_unknown_table' );
		}

		$row = array_intersect_key(
			$row,
			array_flip( self::TABLE_COLUMNS[ $table_key ] )
		);

		$old_id = absint( $row['id'] ?? 0 );
		unset( $row['id'] );

		if ( Power_Schedule_Manager_Database::UNITS === $table_key ) {
			$code = sanitize_text_field( (string) ( $row['code'] ?? '' ) );

			if ( '' === $code ) {
				throw new RuntimeException( 'restore_invalid_unit' );
			}

			$table      = Power_Schedule_Manager_Database::table( $table_key );
			$current_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM `{$table}` WHERE code = %s LIMIT 1",
					$code
				)
			);

			if ( is_numeric( $current_id ) ) {
				if (
					false === $wpdb->update(
						$table,
						$row,
						array( 'id' => (int) $current_id )
					)
				) {
					throw new RuntimeException( 'restore_unit_update_failed' );
				}
				$new_id = (int) $current_id;
			} else {
				if ( false === $wpdb->insert( $table, $row ) ) {
					throw new RuntimeException( 'restore_unit_insert_failed' );
				}
				$new_id = (int) $wpdb->insert_id;
			}

			$maps['units'][ $old_id ] = $new_id;
			return;
		}

		if ( isset( $row['unit_id'] ) ) {
			$row['unit_id'] = self::mapped_id(
				$maps['units'],
				absint( $row['unit_id'] ),
				true
			);
		}

		if ( Power_Schedule_Manager_Database::IMPORT_RUNS === $table_key ) {
			$row['user_id'] = get_current_user_id();
		}

		if ( Power_Schedule_Manager_Database::EVENTS === $table_key ) {
			$row['import_run_id'] = self::mapped_id(
				$maps['imports'],
				absint( $row['import_run_id'] ?? 0 ),
				false
			);
			$row['post_id'] = self::mapped_id(
				$maps['posts'],
				absint( $row['post_id'] ?? 0 ),
				false
			);
		}

		if (
			Power_Schedule_Manager_Database::EVENT_LOCATIONS === $table_key
			|| Power_Schedule_Manager_Database::EVENT_REVISIONS === $table_key
		) {
			$row['event_id'] = self::mapped_id(
				$maps['events'],
				absint( $row['event_id'] ?? 0 ),
				true
			);
		}

		if ( Power_Schedule_Manager_Database::EVENT_REVISIONS === $table_key ) {
			$row['import_run_id'] = self::mapped_id(
				$maps['imports'],
				absint( $row['import_run_id'] ?? 0 ),
				false
			);
			$row['user_id'] = get_current_user_id();
		}

		if ( Power_Schedule_Manager_Database::PLACE_ALIASES === $table_key ) {
			$row['place_id'] = self::mapped_id(
				$maps['places'],
				absint( $row['place_id'] ?? 0 ),
				true
			);
		}

		if ( Power_Schedule_Manager_Database::EVENT_PLACES === $table_key ) {
			$row['event_id'] = self::mapped_id(
				$maps['events'],
				absint( $row['event_id'] ?? 0 ),
				true
			);
			$row['place_id'] = self::mapped_id(
				$maps['places'],
				absint( $row['place_id'] ?? 0 ),
				true
			);
		}

		$table = Power_Schedule_Manager_Database::table( $table_key );

		if ( false === $wpdb->insert( $table, $row ) ) {
			throw new RuntimeException(
				'restore_table_insert_failed_' . $table_key
			);
		}

		$new_id = (int) $wpdb->insert_id;

		if ( Power_Schedule_Manager_Database::IMPORT_RUNS === $table_key ) {
			$maps['imports'][ $old_id ] = $new_id;
		} elseif ( Power_Schedule_Manager_Database::EVENTS === $table_key ) {
			$maps['events'][ $old_id ] = $new_id;
		} elseif ( Power_Schedule_Manager_Database::PLACES === $table_key ) {
			$maps['places'][ $old_id ] = $new_id;
		}
	}

	/**
	 * Restore one taxonomy term by stable unit code.
	 *
	 * @param array<string, mixed> $value Term payload.
	 *
	 * @return void
	 */
	private function restore_term(
		array $value,
		array $term_map
	): int {
		$meta = is_array( $value['meta'] ?? null )
			? $value['meta']
			: array();
		$code = sanitize_text_field(
			(string) (
				$meta[ Power_Schedule_Manager_Taxonomy::META_UNIT_CODE ]
				?? ''
			)
		);

		if ( '' === $code ) {
			throw new RuntimeException( 'restore_invalid_term' );
		}

		$ids = get_terms(
			array(
				'taxonomy'   => Power_Schedule_Manager_Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   =>
					Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
				'meta_value' => $code,
			)
		);

		if ( is_wp_error( $ids ) ) {
			throw new RuntimeException( 'restore_term_lookup_failed' );
		}

		$args = array(
			'description' => sanitize_textarea_field(
				(string) ( $value['description'] ?? '' )
			),
			'slug'        => sanitize_title(
				(string) ( $value['slug'] ?? '' )
			),
			'parent'      => self::mapped_id(
				$term_map,
				absint( $value['parent'] ?? 0 ),
				false
			),
		);

		if ( array() !== $ids ) {
			$term_id = (int) reset( $ids );
			$result  = wp_update_term(
				$term_id,
				Power_Schedule_Manager_Taxonomy::TAXONOMY,
				array_merge(
					$args,
					array(
						'name' => sanitize_text_field(
							(string) ( $value['name'] ?? '' )
						),
					)
				)
			);
		} else {
			$result = wp_insert_term(
				sanitize_text_field( (string) ( $value['name'] ?? '' ) ),
				Power_Schedule_Manager_Taxonomy::TAXONOMY,
				$args
			);
		}

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( 'restore_term_write_failed' );
		}

		$term_id = (int) $result['term_id'];

		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
			$code
		);
		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_IS_PUBLIC,
			empty(
				$meta[ Power_Schedule_Manager_Taxonomy::META_IS_PUBLIC ]
			) ? 0 : 1
		);
		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_DISPLAY_ORDER,
			absint(
				$meta[ Power_Schedule_Manager_Taxonomy::META_DISPLAY_ORDER ]
				?? 0
			)
		);
		update_term_meta(
			$term_id,
			Power_Schedule_Manager_Taxonomy::META_SHORT_DESCRIPTION,
			sanitize_text_field(
				(string) (
					$meta[
						Power_Schedule_Manager_Taxonomy::META_SHORT_DESCRIPTION
					] ?? ''
				)
			)
		);

		return $term_id;
	}

	/**
	 * Restore one plugin CPT record.
	 *
	 * @param array<string, mixed> $value Post payload.
	 *
	 * @return int
	 */
	private function restore_post( array $value ): int {
		$allowed_statuses = array(
			'publish', 'draft', 'pending', 'private', 'future',
		);
		$status = sanitize_key( (string) ( $value['post_status'] ?? '' ) );

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'draft';
		}

		$post_id = wp_insert_post(
			array(
				'post_type'      =>
					Power_Schedule_Manager_Post_Type::POST_TYPE,
				'post_author'    => get_current_user_id(),
				'post_title'     => sanitize_text_field(
					(string) ( $value['post_title'] ?? '' )
				),
				'post_name'      => sanitize_title(
					(string) ( $value['post_name'] ?? '' )
				),
				'post_content'   => wp_kses_post(
					(string) ( $value['post_content'] ?? '' )
				),
				'post_excerpt'   => sanitize_textarea_field(
					(string) ( $value['post_excerpt'] ?? '' )
				),
				'post_status'    => $status,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'menu_order'     => absint( $value['menu_order'] ?? 0 ),
				'post_date'      => self::valid_mysql_datetime(
					(string) ( $value['post_date'] ?? '' ),
					wp_timezone()
				),
				'post_date_gmt'  => self::valid_mysql_datetime(
					(string) ( $value['post_date_gmt'] ?? '' )
				),
				'post_modified'  => self::valid_mysql_datetime(
					(string) ( $value['post_modified'] ?? '' ),
					wp_timezone()
				),
				'post_modified_gmt' => self::valid_mysql_datetime(
					(string) ( $value['post_modified_gmt'] ?? '' )
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( 'restore_post_insert_failed' );
		}

		$meta = is_array( $value['meta'] ?? null )
			? $value['meta']
			: array();

		update_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
			sanitize_text_field(
				(string) (
					$meta[ Power_Schedule_Manager_Post_Type::META_UNIT_CODE ]
					?? ''
				)
			)
		);
		update_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
			sanitize_text_field(
				(string) (
					$meta[ Power_Schedule_Manager_Post_Type::META_LOCAL_DATE ]
					?? ''
				)
			)
		);
		update_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_EVENT_COUNT,
			absint(
				$meta[ Power_Schedule_Manager_Post_Type::META_EVENT_COUNT ]
				?? 0
			)
		);
		update_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC,
			self::valid_mysql_datetime(
				(string) (
					$meta[
						Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC
					] ?? ''
				)
			)
		);

		$term_ids = array();

		foreach ( (array) ( $value['term_codes'] ?? array() ) as $code ) {
			if ( ! is_string( $code ) ) {
				continue;
			}

			$ids = get_terms(
				array(
					'taxonomy'   =>
						Power_Schedule_Manager_Taxonomy::TAXONOMY,
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => 1,
					'meta_key'   =>
						Power_Schedule_Manager_Taxonomy::META_UNIT_CODE,
					'meta_value' => sanitize_text_field( $code ),
				)
			);

			if ( ! is_wp_error( $ids ) && array() !== $ids ) {
				$term_ids[] = (int) reset( $ids );
			}
		}

		if ( array() !== $term_ids ) {
			$result = wp_set_object_terms(
				$post_id,
				$term_ids,
				Power_Schedule_Manager_Taxonomy::TAXONOMY,
				false
			);

			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( 'restore_post_terms_failed' );
			}
		}

		return (int) $post_id;
	}

	/**
	 * Return record ordering for preflight validation.
	 *
	 * @param array<string, mixed> $record Record.
	 *
	 * @return int
	 */
	private static function record_order( array $record ): int {
		$type = (string) ( $record['record'] ?? '' );

		if ( 'manifest' === $type ) {
			return 0;
		}
		if ( 'settings' === $type ) {
			return 1;
		}
		if ( 'term' === $type ) {
			return 3;
		}
		if ( 'post' === $type ) {
			return 4;
		}
		if ( 'table_row' !== $type ) {
			throw new RuntimeException( 'restore_unknown_record' );
		}

			return match ( (string) ( $record['table'] ?? '' ) ) {
				Power_Schedule_Manager_Database::UNITS => 2,
				Power_Schedule_Manager_Database::IMPORT_RUNS => 5,
				Power_Schedule_Manager_Database::EVENTS => 6,
				Power_Schedule_Manager_Database::EVENT_LOCATIONS => 7,
				Power_Schedule_Manager_Database::PLACES => 8,
				Power_Schedule_Manager_Database::PLACE_ALIASES => 9,
				Power_Schedule_Manager_Database::EVENT_PLACES => 10,
				Power_Schedule_Manager_Database::EVENT_REVISIONS => 11,
				Power_Schedule_Manager_Database::DONATIONS => 12,
				Power_Schedule_Manager_Database::MARKET_PRICES => 13,
				Power_Schedule_Manager_Database::LOTTERY_DRAWS => 14,
				default => throw new RuntimeException( 'restore_unknown_table' ),
			};
	}

	/**
	 * Write and hash one archive record.
	 *
	 * @param array<string, mixed> $record Record.
	 * @param HashContext          $hash   Hash context.
	 * @param array<string, int>   $counts Counts.
	 *
	 * @return void
	 */
	private function write_record(
		array $record,
		HashContext $hash,
		array &$counts
	): void {
		$line = self::encode_record( $record ) . "\n";

		hash_update( $hash, $line );
		$this->emit( $line );

		$type = (string) ( $record['record'] ?? 'unknown' );
		$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
	}

	/**
	 * Write to a temporary archive or stream to the browser.
	 */
	private function emit( string $content ): void {
		if ( is_resource( $this->output_handle ) ) {
			$written = fwrite( $this->output_handle, $content );
			if ( false === $written || $written !== strlen( $content ) ) {
				throw new RuntimeException( 'backup_write_failed' );
			}

			return;
		}

		echo $content;
	}

	/**
	 * Upload an archive to Wasabi.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array<string,string>|WP_Error
	 */
	private function upload_wasabi( string $path, array $config ): array|WP_Error {
		$filename = basename( $path ) . '.ndjson';
		$prefix = trim( (string) ( $config['wasabi_prefix'] ?? '' ), '/' );
		$key = ( '' !== $prefix ? $prefix . '/' : '' )
			. gmdate( 'Y/m/' )
			. 'power-schedule-manager-' . gmdate( 'Ymd-His' ) . '.ndjson';
		$body = file_get_contents( $path );
		if ( false === $body ) {
			return new WP_Error( 'backup_read_failed', __( 'Không đọc được file backup tạm.', 'power-schedule-manager' ) );
		}
		$request = $this->wasabi_request_data(
			'PUT',
			$key,
			hash( 'sha256', $body ),
			$config
		);
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$response = wp_remote_request(
			$request['url'],
			array(
				'method' => 'PUT',
				'timeout' => 90,
				'headers' => $request['headers'] + array(
					'Content-Type' => 'application/x-ndjson',
					'Content-Length' => (string) strlen( $body ),
				),
				'body' => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'wasabi_upload_failed', sprintf( 'Wasabi HTTP %d', $status ) );
		}

		return array( 'key' => $key, 'name' => $filename );
	}

	/**
	 * Download the latest Wasabi archive.
	 *
	 * @param array<string,mixed> $remote Remote metadata.
	 * @param array<string,mixed> $config Configuration.
	 */
	private function download_wasabi( array $remote, array $config ): string {
		$key = (string) ( $remote['key'] ?? '' );
		if ( '' === $key ) {
			throw new RuntimeException( 'wasabi_backup_not_found' );
		}
		$request = $this->wasabi_request_data(
			'GET',
			$key,
			hash( 'sha256', '' ),
			$config
		);
		if ( is_wp_error( $request ) ) {
			throw new RuntimeException( $request->get_error_message() );
		}
		$response = wp_remote_get(
			$request['url'],
			array( 'timeout' => 90, 'headers' => $request['headers'] )
		);
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new RuntimeException( 'wasabi_download_failed' );
		}

		return $this->store_downloaded_archive(
			wp_remote_retrieve_body( $response )
		);
	}

	/**
	 * Build one AWS Signature V4 request for a Wasabi object.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array{url:string,headers:array<string,string>}|WP_Error
	 */
	private function wasabi_request_data(
		string $method,
		string $key,
		string $payload_hash,
		array $config
	): array|WP_Error {
		$endpoint = untrailingslashit( (string) ( $config['wasabi_endpoint'] ?? '' ) );
		$region = sanitize_key( (string) ( $config['wasabi_region'] ?? '' ) );
		$bucket = (string) ( $config['wasabi_bucket'] ?? '' );
		$access = (string) ( $config['wasabi_access_key'] ?? '' );
		$secret = $this->cloud_secret(
			$config,
			'wasabi_secret_plain',
			'wasabi_secret_encrypted'
		);
		if ( '' === $endpoint || '' === $region || '' === $bucket || '' === $access || '' === $secret ) {
			return new WP_Error( 'wasabi_incomplete', __( 'Cấu hình Wasabi chưa đầy đủ.', 'power-schedule-manager' ) );
		}
		$segments = array_map( 'rawurlencode', explode( '/', trim( $key, '/' ) ) );
		$canonical_uri = '/' . rawurlencode( $bucket ) . '/' . implode( '/', $segments );
		$host = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
		$amz_date = gmdate( 'Ymd\\THis\\Z' );
		$date = substr( $amz_date, 0, 8 );
		$canonical_headers = "host:{$host}\n"
			. "x-amz-content-sha256:{$payload_hash}\n"
			. "x-amz-date:{$amz_date}\n";
		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';
		$canonical_request = "{$method}\n{$canonical_uri}\n\n"
			. "{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
		$scope = "{$date}/{$region}/s3/aws4_request";
		$string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$scope}\n"
			. hash( 'sha256', $canonical_request );
		$date_key = hash_hmac( 'sha256', $date, 'AWS4' . $secret, true );
		$region_key = hash_hmac( 'sha256', $region, $date_key, true );
		$service_key = hash_hmac( 'sha256', 's3', $region_key, true );
		$signing_key = hash_hmac( 'sha256', 'aws4_request', $service_key, true );
		$signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );

		return array(
			'url' => $endpoint . $canonical_uri,
			'headers' => array(
				'Authorization' => "AWS4-HMAC-SHA256 Credential={$access}/{$scope}, SignedHeaders={$signed_headers}, Signature={$signature}",
				'x-amz-content-sha256' => $payload_hash,
				'x-amz-date' => $amz_date,
			),
		);
	}

	/**
	 * Upload an archive to Google Drive using multipart upload.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array<string,string>|WP_Error
	 */
	private function upload_google_drive( string $path, array $config ): array|WP_Error {
		$token = $this->google_access_token( $config );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$body = file_get_contents( $path );
		if ( false === $body ) {
			return new WP_Error( 'backup_read_failed', __( 'Không đọc được file backup tạm.', 'power-schedule-manager' ) );
		}
		$name = 'power-schedule-manager-' . gmdate( 'Ymd-His' ) . '.ndjson';
		$metadata = array( 'name' => $name );
		$folder = (string) ( $config['google_folder_id'] ?? '' );
		if ( '' !== $folder ) {
			$metadata['parents'] = array( $folder );
		}
		$boundary = 'psm_' . wp_generate_password( 24, false, false );
		$multipart = '--' . $boundary . "\r\n"
			. "Content-Type: application/json; charset=UTF-8\r\n\r\n"
			. wp_json_encode( $metadata, JSON_UNESCAPED_SLASHES ) . "\r\n"
			. '--' . $boundary . "\r\n"
			. "Content-Type: application/x-ndjson\r\n\r\n"
			. $body . "\r\n--" . $boundary . "--";
		$response = wp_remote_post(
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'multipart/related; boundary=' . $boundary,
				),
				'body' => $multipart,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( wp_remote_retrieve_response_code( $response ) < 200
			|| wp_remote_retrieve_response_code( $response ) >= 300
			|| ! is_array( $data )
			|| empty( $data['id'] )
		) {
			return new WP_Error( 'google_upload_failed', __( 'Google Drive không nhận file backup.', 'power-schedule-manager' ) );
		}

		return array( 'id' => (string) $data['id'], 'name' => $name );
	}

	/**
	 * Download the latest Google Drive archive.
	 *
	 * @param array<string,mixed> $remote Remote metadata.
	 * @param array<string,mixed> $config Configuration.
	 */
	private function download_google_drive( array $remote, array $config ): string {
		$id = (string) ( $remote['id'] ?? '' );
		if ( '' === $id ) {
			throw new RuntimeException( 'google_backup_not_found' );
		}
		$token = $this->google_access_token( $config );
		if ( is_wp_error( $token ) ) {
			throw new RuntimeException( $token->get_error_message() );
		}
		$response = wp_remote_get(
			'https://www.googleapis.com/drive/v3/files/'
				. rawurlencode( $id )
				. '?alt=media&supportsAllDrives=true',
			array(
				'timeout' => 90,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new RuntimeException( 'google_download_failed' );
		}

		return $this->store_downloaded_archive(
			wp_remote_retrieve_body( $response )
		);
	}

	/**
	 * Exchange a refresh token for a short-lived access token.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return string|WP_Error
	 */
	private function google_access_token( array $config ): string|WP_Error {
		$client_id = (string) ( $config['google_client_id'] ?? '' );
		$client_secret = $this->cloud_secret(
			$config,
			'google_client_secret_plain',
			'google_client_secret_encrypted'
		);
		$refresh_token = $this->cloud_secret(
			$config,
			'google_refresh_token_plain',
			'google_refresh_token_encrypted'
		);
		if ( '' === $client_id || '' === $client_secret || '' === $refresh_token ) {
			return new WP_Error( 'google_incomplete', __( 'Cấu hình Google Drive chưa đầy đủ.', 'power-schedule-manager' ) );
		}
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body' => array(
					'client_id' => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type' => 'refresh_token',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = is_array( $data ) ? (string) ( $data['access_token'] ?? '' ) : '';

		return '' !== $token
			? $token
			: new WP_Error( 'google_token_failed', __( 'Google không cấp access token.', 'power-schedule-manager' ) );
	}

	/**
	 * Resolve a secret from Docker or encrypted WordPress settings.
	 *
	 * @param array<string,mixed> $config Configuration.
	 */
	private function cloud_secret(
		array $config,
		string $plain_key,
		string $encrypted_key
	): string {
		$value = (string) ( $config[ $plain_key ] ?? '' );

		return '' !== $value
			? $value
			: Power_Schedule_Manager_Secrets::decrypt(
				(string) ( $config[ $encrypted_key ] ?? '' )
			);
	}

	/**
	 * Store a downloaded response in a bounded temporary file.
	 */
	private function store_downloaded_archive( string $body ): string {
		if ( '' === $body || strlen( $body ) > self::MAX_UPLOAD_BYTES ) {
			throw new RuntimeException( 'backup_upload_size_invalid' );
		}
		$path = wp_tempnam( 'psm-cloud-restore-' );
		if ( ! is_string( $path ) || '' === $path ) {
			throw new RuntimeException( 'backup_temp_unavailable' );
		}
		if ( strlen( $body ) !== file_put_contents( $path, $body ) ) {
			throw new RuntimeException( 'backup_write_failed' );
		}

		return $path;
	}

	/**
	 * Test Wasabi bucket access using AWS Signature Version 4.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return string|WP_Error
	 */
	private function test_wasabi( array $config ): string|WP_Error {
		$endpoint = untrailingslashit( (string) ( $config['wasabi_endpoint'] ?? '' ) );
		$region = sanitize_key( (string) ( $config['wasabi_region'] ?? '' ) );
		$bucket = (string) ( $config['wasabi_bucket'] ?? '' );
		$access = (string) ( $config['wasabi_access_key'] ?? '' );
		$secret = (string) ( $config['wasabi_secret_plain'] ?? '' );
		if ( '' === $secret ) {
			$secret = Power_Schedule_Manager_Secrets::decrypt(
				(string) ( $config['wasabi_secret_encrypted'] ?? '' )
			);
		}
		if ( '' === $endpoint || '' === $region || '' === $bucket || '' === $access || '' === $secret ) {
			return new WP_Error(
				'wasabi_incomplete',
				__( 'Cấu hình Wasabi chưa đầy đủ.', 'power-schedule-manager' )
			);
		}
		$url = $endpoint . '/' . rawurlencode( $bucket );
		$host = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
		$amz_date = gmdate( 'Ymd\\THis\\Z' );
		$date = substr( $amz_date, 0, 8 );
		$payload_hash = hash( 'sha256', '' );
		$canonical_headers = "host:{$host}\n"
			. "x-amz-content-sha256:{$payload_hash}\n"
			. "x-amz-date:{$amz_date}\n";
		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';
		$canonical_request = "HEAD\n/" . rawurlencode( $bucket )
			. "\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
		$scope = "{$date}/{$region}/s3/aws4_request";
		$string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$scope}\n"
			. hash( 'sha256', $canonical_request );
		$date_key = hash_hmac( 'sha256', $date, 'AWS4' . $secret, true );
		$region_key = hash_hmac( 'sha256', $region, $date_key, true );
		$service_key = hash_hmac( 'sha256', 's3', $region_key, true );
		$signing_key = hash_hmac( 'sha256', 'aws4_request', $service_key, true );
		$signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );
		$response = wp_remote_request(
			$url,
			array(
				'method' => 'HEAD',
				'timeout' => 15,
				'headers' => array(
					'Authorization' => "AWS4-HMAC-SHA256 Credential={$access}/{$scope}, SignedHeaders={$signed_headers}, Signature={$signature}",
					'x-amz-content-sha256' => $payload_hash,
					'x-amz-date' => $amz_date,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );

		return $status >= 200 && $status < 300
			? __( 'Kết nối Wasabi thành công và bucket có thể truy cập.', 'power-schedule-manager' )
			: new WP_Error(
				'wasabi_failed',
				sprintf(
					/* translators: %d: HTTP status. */
					__( 'Wasabi từ chối kết nối (HTTP %d). Kiểm tra vùng, bucket và quyền khóa.', 'power-schedule-manager' ),
					$status
				)
			);
	}

	/**
	 * Test Google Drive using a refresh token and least-privilege scope.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return string|WP_Error
	 */
	private function test_google_drive( array $config ): string|WP_Error {
		$client_id = (string) ( $config['google_client_id'] ?? '' );
		$client_secret = (string) (
			$config['google_client_secret_plain'] ?? ''
		);
		if ( '' === $client_secret ) {
			$client_secret = Power_Schedule_Manager_Secrets::decrypt(
				(string) ( $config['google_client_secret_encrypted'] ?? '' )
			);
		}
		$refresh_token = (string) (
			$config['google_refresh_token_plain'] ?? ''
		);
		if ( '' === $refresh_token ) {
			$refresh_token = Power_Schedule_Manager_Secrets::decrypt(
				(string) ( $config['google_refresh_token_encrypted'] ?? '' )
			);
		}
		if ( '' === $client_id || '' === $client_secret || '' === $refresh_token ) {
			return new WP_Error(
				'google_incomplete',
				__( 'Cấu hình Google Drive chưa đầy đủ.', 'power-schedule-manager' )
			);
		}
		$token_response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body' => array(
					'client_id' => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type' => 'refresh_token',
				),
			)
		);
		if ( is_wp_error( $token_response ) ) {
			return $token_response;
		}
		$token_data = json_decode(
			wp_remote_retrieve_body( $token_response ),
			true
		);
		$access_token = is_array( $token_data )
			? (string) ( $token_data['access_token'] ?? '' )
			: '';
		if ( '' === $access_token ) {
			return new WP_Error(
				'google_token_failed',
				__( 'Google không cấp access token. Hãy kiểm tra OAuth Client và refresh token.', 'power-schedule-manager' )
			);
		}
		$folder_id = (string) ( $config['google_folder_id'] ?? '' );
		$test_url = '' !== $folder_id
			? 'https://www.googleapis.com/drive/v3/files/'
				. rawurlencode( $folder_id )
				. '?fields=id,name,mimeType'
			: 'https://www.googleapis.com/drive/v3/about?fields=user';
		$response = wp_remote_get(
			$test_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );

		return 200 === $status
			? __( 'Kết nối Google Drive thành công.', 'power-schedule-manager' )
			: new WP_Error(
				'google_drive_failed',
				sprintf(
					/* translators: %d: HTTP status. */
					__( 'Google Drive từ chối kết nối (HTTP %d). Kiểm tra quyền drive.file và thư mục.', 'power-schedule-manager' ),
					$status
				)
			);
	}

	/**
	 * Encode one record consistently.
	 *
	 * @param array<string, mixed> $record Record.
	 *
	 * @return string
	 */
	private static function encode_record( array $record ): string {
		$json = wp_json_encode(
			$record,
			JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_INVALID_UTF8_SUBSTITUTE
		);

		if ( ! is_string( $json ) ) {
			throw new RuntimeException( 'backup_json_encode_failed' );
		}

		return $json;
	}

	/**
	 * Encode known binary columns as lowercase hexadecimal.
	 *
	 * @param string               $table_key Table key.
	 * @param array<string, mixed> $row       Row.
	 *
	 * @return array<string, mixed>
	 */
	private static function encode_binary_columns(
		string $table_key,
		array $row
	): array {
		foreach ( self::BINARY_COLUMNS[ $table_key ] ?? array() as $column ) {
			if ( isset( $row[ $column ] ) && is_string( $row[ $column ] ) ) {
				$row[ $column ] = bin2hex( $row[ $column ] );
			}
		}

		return $row;
	}

	/**
	 * Decode known hexadecimal hash fields.
	 *
	 * @param string               $table_key Table key.
	 * @param array<string, mixed> $row       Row.
	 *
	 * @return array<string, mixed>
	 */
	private static function decode_binary_columns(
		string $table_key,
		array $row
	): array {
		foreach ( self::BINARY_COLUMNS[ $table_key ] ?? array() as $column ) {
			if ( ! isset( $row[ $column ] ) || null === $row[ $column ] ) {
				continue;
			}

			$hex = is_string( $row[ $column ] )
				? strtolower( $row[ $column ] )
				: '';

			if ( 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $hex ) ) {
				throw new RuntimeException( 'restore_invalid_hash' );
			}

			$binary = hex2bin( $hex );

			if ( false === $binary ) {
				throw new RuntimeException( 'restore_invalid_hash' );
			}

			$row[ $column ] = $binary;
		}

		return $row;
	}

	/**
	 * Resolve an uploaded archive path.
	 *
	 * @return string
	 */
	private function uploaded_file(): string {
		if (
			! isset( $_FILES['backup_file'] )
			|| ! is_array( $_FILES['backup_file'] )
		) {
			throw new RuntimeException( 'restore_file_missing' );
		}

		$file = $_FILES['backup_file'];
		$error = absint( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		$size  = absint( $file['size'] ?? 0 );
		$name  = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		$path  = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] )
			? $file['tmp_name']
			: '';

		if ( UPLOAD_ERR_OK !== $error || '' === $path ) {
			throw new RuntimeException( 'restore_upload_failed' );
		}

		$actual_size = filesize( $path );

		if (
			false === $actual_size
			|| $actual_size !== $size
			|| $size < 1
			|| $size > self::max_upload_bytes()
		) {
			throw new RuntimeException( 'restore_file_size_invalid' );
		}

		$extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, array( 'ndjson', 'jsonl' ), true ) ) {
			throw new RuntimeException( 'restore_file_extension_invalid' );
		}

		if (
			'cli' !== PHP_SAPI
			&& ! is_uploaded_file( $path )
		) {
			throw new RuntimeException( 'restore_upload_invalid' );
		}

		return $path;
	}

	/**
	 * Refuse restore when operational data already exists.
	 *
	 * The seeded units table is intentionally allowed.
	 *
	 * @return bool
	 */
	private static function destination_is_empty(): bool {
		global $wpdb;

		if ( ! Power_Schedule_Manager_Database::all_tables_exist() ) {
			return false;
		}

		foreach ( Power_Schedule_Manager_Database::table_keys() as $key ) {
			if (
				in_array(
					$key,
					array(
						Power_Schedule_Manager_Database::UNITS,
						Power_Schedule_Manager_Database::NOTIFICATIONS,
					),
					true
				)
			) {
				continue;
			}

			$table = Power_Schedule_Manager_Database::table( $key );
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

			if ( is_numeric( $count ) && (int) $count > 0 ) {
				return false;
			}
		}

		$post_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->posts}`
				WHERE post_type = %s AND post_status <> 'auto-draft'",
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		);

		return is_numeric( $post_count ) && 0 === (int) $post_count;
	}

	/**
	 * Map an old archive ID to its restored ID.
	 *
	 * @param array<int, int> $map      ID map.
	 * @param int             $old_id   Old ID.
	 * @param bool            $required Whether a zero/missing ID is invalid.
	 *
	 * @return int
	 */
	private static function mapped_id(
		array $map,
		int $old_id,
		bool $required
	): int {
		if ( 0 === $old_id && ! $required ) {
			return 0;
		}

		if ( isset( $map[ $old_id ] ) ) {
			return (int) $map[ $old_id ];
		}

		if ( $required ) {
			throw new RuntimeException( 'restore_missing_relation' );
		}

		return 0;
	}

	/**
	 * Normalize an exported UTC datetime.
	 *
	 * @param string $value Datetime.
	 *
	 * @return string
	 */
	private static function valid_mysql_datetime(
		string $value,
		?DateTimeZone $timezone = null
	): string {
		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$value,
			$timezone ?? new DateTimeZone( 'UTC' )
		);

		return $date instanceof DateTimeImmutable
			? $date->format( 'Y-m-d H:i:s' )
			: gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Require the settings-management capability.
	 *
	 * @return void
	 */
	private function assert_access(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die(
				esc_html__(
					'Bạn không có quyền sao lưu hoặc khôi phục dữ liệu.',
					'power-schedule-manager'
				),
				esc_html__( 'Không đủ quyền', 'power-schedule-manager' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Store a one-time admin notice.
	 *
	 * @param array<string, mixed> $notice Notice.
	 *
	 * @return void
	 */
	private static function store_notice( array $notice ): void {
		set_transient(
			self::notice_key(),
			$notice,
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * User-specific notice key.
	 *
	 * @return string
	 */
	private static function notice_key(): string {
		return 'psm_backup_notice_' . get_current_user_id();
	}

	/**
	 * Backup page URL.
	 *
	 * @return string
	 */
	private static function page_url(): string {
		return add_query_arg(
			array( 'page' => Power_Schedule_Manager_Admin::BACKUP_SLUG ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Maximum accepted archive size.
	 *
	 * @return int
	 */
	private static function max_upload_bytes(): int {
		$configured = (int) apply_filters(
			'power_schedule_manager_backup_max_upload_bytes',
			self::MAX_UPLOAD_BYTES
		);

		$wordpress_limit = function_exists( 'wp_max_upload_size' )
			? (int) wp_max_upload_size()
			: $configured;

		return max( 1048576, min( $configured, $wordpress_limit ) );
	}

	/**
	 * Convert internal restore errors into safe Vietnamese messages.
	 *
	 * @param string $code Error code.
	 *
	 * @return string
	 */
	private static function restore_error_message( string $code ): string {
		return match ( $code ) {
			'restore_confirmation_required' => __(
				'Bạn phải xác nhận đã hiểu điều kiện khôi phục.',
				'power-schedule-manager'
			),
			'restore_destination_not_empty' => __(
				'Không thể khôi phục vì website đang có lịch, dữ liệu bản đồ hoặc lịch sử nhập. Plugin không tự ghi đè dữ liệu đang vận hành.',
				'power-schedule-manager'
			),
			'restore_write_lock_unavailable' => __(
				'Đang có tiến trình nhập hoặc ghi dữ liệu khác. Vui lòng đợi tiến trình đó hoàn tất rồi thử lại.',
				'power-schedule-manager'
			),
			'restore_file_missing',
			'restore_upload_failed',
			'restore_upload_invalid' => __(
				'Không nhận được file sao lưu hợp lệ từ trình duyệt.',
				'power-schedule-manager'
			),
			'restore_file_size_invalid' => __(
				'File sao lưu rỗng hoặc vượt quá giới hạn cho phép.',
				'power-schedule-manager'
			),
			'restore_file_extension_invalid' => __(
				'Chỉ chấp nhận file .ndjson hoặc .jsonl do plugin xuất ra.',
				'power-schedule-manager'
			),
			'restore_checksum_mismatch' => __(
				'Checksum không khớp. File có thể đã bị sửa hoặc tải xuống chưa đầy đủ.',
				'power-schedule-manager'
			),
			'restore_incompatible_format' => __(
				'Đây không phải bản sao lưu tương thích của Cúp Điện Lâm Đồng.',
				'power-schedule-manager'
			),
			'restore_incompatible_schema' => __(
				'Phiên bản schema trong file không khớp plugin hiện tại. Hãy cài đúng phiên bản plugin rồi thử lại.',
				'power-schedule-manager'
			),
			'restore_invalid_json',
			'restore_invalid_record',
			'restore_unknown_record',
			'restore_unknown_table',
			'restore_invalid_order',
			'restore_incomplete_archive',
			'restore_footer_not_last',
			'restore_record_count_mismatch',
			'restore_line_too_large' => __(
				'Cấu trúc file sao lưu không hợp lệ hoặc không đầy đủ.',
				'power-schedule-manager'
			),
			'restore_missing_relation' => __(
				'File thiếu quan hệ dữ liệu cần thiết nên không thể khôi phục an toàn.',
				'power-schedule-manager'
			),
			default => __(
				'Không thể hoàn tất khôi phục. Không có dữ liệu dở dang nào được giữ lại.',
				'power-schedule-manager'
			),
		};
	}
}
