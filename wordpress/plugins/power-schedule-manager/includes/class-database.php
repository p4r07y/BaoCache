<?php
/**
 * Tiện ích database của Cúp Điện Lâm Đồng.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quản lý tên bảng và biểu diễn dữ liệu database cấp thấp.
 *
 * Class này không chứa:
 * - Schema SQL.
 * - Migration.
 * - Truy vấn nghiệp vụ.
 * - Transaction.
 * - Import logic.
 */
final class Power_Schedule_Manager_Database {

	/**
	 * Khóa bảng sự kiện.
	 */
	public const string EVENTS = 'events';

	/**
	 * Khóa bảng đơn vị điện lực.
	 */
	public const string UNITS = 'units';

	/**
	 * Khóa bảng lịch sử nhập.
	 */
	public const string IMPORT_RUNS = 'import_runs';

	/**
	 * Khóa bảng revision sự kiện.
	 */
	public const string EVENT_REVISIONS = 'event_revisions';

	/**
	 * Khóa bảng vị trí và đoạn đường.
	 */
	public const string EVENT_LOCATIONS = 'event_locations';

	public const string PLACES = 'places';

	public const string PLACE_ALIASES = 'place_aliases';

	public const string EVENT_PLACES = 'event_places';

	/**
	 * Durable outbound notification queue.
	 */
	public const string NOTIFICATIONS = 'notifications';

	/**
	 * Donation declarations awaiting manual verification.
	 */
	public const string DONATIONS = 'donations';

	/**
	 * Daily reference prices entered by an editor or an authorized provider.
	 */
	public const string MARKET_PRICES = 'market_prices';

	/**
	 * Verified lottery draws synchronized from an authorized provider.
	 */
	public const string LOTTERY_DRAWS = 'lottery_draws';

	/**
	 * Số byte của SHA-256 dạng binary.
	 */
	public const int SHA256_BINARY_LENGTH = 32;

	/**
	 * Số ký tự của SHA-256 dạng hexadecimal.
	 */
	public const int SHA256_HEX_LENGTH = 64;

	/**
	 * Database version trong request hiện tại.
	 */
	private static ?string $server_version_cache = null;

	/**
	 * Database server information trong request hiện tại.
	 */
	private static ?string $server_info_cache = null;

	/**
	 * Mapping giữa khóa logic và hậu tố bảng.
	 *
	 * WordPress tự thêm database prefix của website.
	 *
	 * @var array<string, string>
	 */
	private const array TABLE_MAP = array(
		self::EVENTS =>
			'psm_events',

		self::UNITS =>
			'psm_units',

		self::IMPORT_RUNS =>
			'psm_import_runs',

		self::EVENT_REVISIONS =>
			'psm_event_revisions',

		self::EVENT_LOCATIONS =>
			'psm_event_locations',

		self::PLACES =>
			'psm_places',

		self::PLACE_ALIASES =>
			'psm_place_aliases',

		self::EVENT_PLACES =>
			'psm_event_places',

		self::NOTIFICATIONS =>
			'psm_notifications',

		self::DONATIONS =>
			'psm_donations',

		self::MARKET_PRICES =>
			'psm_market_prices',

		self::LOTTERY_DRAWS =>
			'psm_lottery_draws',
	);

	/**
	 * Lấy tên đầy đủ của một bảng.
	 *
	 * @param string $table_key Khóa bảng.
	 * @return string
	 *
	 * @throws InvalidArgumentException Khi khóa bảng không hợp lệ.
	 */
	public static function table(
		string $table_key
	): string {
		global $wpdb;

		if ( ! isset( self::TABLE_MAP[ $table_key ] ) ) {
			throw new InvalidArgumentException(
				'Invalid Cúp Điện Lâm Đồng table key.'
			);
		}

		return $wpdb->prefix
			. self::TABLE_MAP[ $table_key ];
	}

	/**
	 * Lấy tất cả tên bảng.
	 *
	 * @return array<string, string>
	 */
	public static function tables(): array {
		global $wpdb;

		$tables = array();

		foreach (
			self::TABLE_MAP
			as $table_key => $table_suffix
		) {
			$tables[ $table_key ] =
				$wpdb->prefix . $table_suffix;
		}

		return $tables;
	}

	/**
	 * Lấy danh sách khóa bảng.
	 *
	 * @return array<int, string>
	 */
	public static function table_keys(): array {
		return array_keys(
			self::TABLE_MAP
		);
	}

	/**
	 * Kiểm tra một khóa bảng có được hỗ trợ không.
	 *
	 * @param string $table_key Khóa bảng.
	 * @return bool
	 */
	public static function supports_table(
		string $table_key
	): bool {
		return isset(
			self::TABLE_MAP[ $table_key ]
		);
	}

	/**
	 * Return the site-scoped named lock used by data writers.
	 *
	 * Import and restore share this lock so a recovery operation can never
	 * overlap an import request. The name contains no database credentials.
	 *
	 * @return string
	 */
	public static function write_lock_name(): string {
		global $wpdb;

		return 'psm_write_'
			. substr(
				hash(
					'sha256',
					(string) $wpdb->dbname
						. '|'
						. (string) $wpdb->prefix
						. '|'
						. (string) get_current_blog_id()
				),
				0,
				40
			);
	}

	/**
	 * Acquire the shared plugin write lock.
	 *
	 * @param int $timeout_seconds Maximum wait time.
	 *
	 * @return bool
	 */
	public static function acquire_write_lock(
		int $timeout_seconds = 5
	): bool {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, %d)',
				self::write_lock_name(),
				max( 0, min( 30, $timeout_seconds ) )
			)
		);

		return '1' === (string) $result;
	}

	/**
	 * Release the shared plugin write lock held by this DB connection.
	 *
	 * @return void
	 */
	public static function release_write_lock(): void {
		global $wpdb;

		$wpdb->get_var(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s)',
				self::write_lock_name()
			)
		);
	}

	/**
	 * Kiểm tra bảng có tồn tại.
	 *
	 * @param string $table_key Khóa bảng.
	 * @return bool
	 */
	public static function table_exists(
		string $table_key
	): bool {
		global $wpdb;

		$table_name = self::table(
			$table_key
		);

		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table_name )
			)
		);

		return is_string( $found_table )
			&& hash_equals(
				$table_name,
				$found_table
			);
	}

	/**
	 * Kiểm tra toàn bộ bảng đã tồn tại.
	 *
	 * @return bool
	 */
	public static function all_tables_exist(): bool {
		foreach ( self::table_keys() as $table_key ) {
			if ( ! self::table_exists( $table_key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Lấy danh sách bảng còn thiếu.
	 *
	 * @return array<string, string>
	 */
	public static function missing_tables(): array {
		$missing_tables = array();

		foreach (
			self::tables()
			as $table_key => $table_name
		) {
			if ( ! self::table_exists( $table_key ) ) {
				$missing_tables[ $table_key ] =
					$table_name;
			}
		}

		return $missing_tables;
	}

	/**
	 * Lấy charset và collation theo cấu hình WordPress.
	 *
	 * @return string
	 */
	public static function charset_collate(): string {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * Lấy phiên bản database server.
	 *
	 * @return string
	 */
	public static function server_version(): string {
		if ( null !== self::$server_version_cache ) {
			return self::$server_version_cache;
		}

		global $wpdb;

		$version = $wpdb->db_version();

		self::$server_version_cache = is_string( $version )
			? $version
			: '';

		return self::$server_version_cache;
	}

	/**
	 * Lấy thông tin database server.
	 *
	 * Kết quả có thể chứa MySQL hoặc MariaDB.
	 *
	 * @return string
	 */
	public static function server_info(): string {
		if ( null !== self::$server_info_cache ) {
			return self::$server_info_cache;
		}

		global $wpdb;

		if ( ! method_exists( $wpdb, 'db_server_info' ) ) {
			self::$server_info_cache = self::server_version();

			return self::$server_info_cache;
		}

		$server_info = $wpdb->db_server_info();

		self::$server_info_cache = is_string( $server_info )
			? $server_info
			: '';

		return self::$server_info_cache;
	}

	/**
	 * Kiểm tra database server có phải MariaDB không.
	 *
	 * Percona Server và các bản phân phối tương thích MySQL được xử lý như
	 * MySQL vì sử dụng cùng cú pháp row alias của MySQL 8.4.
	 *
	 * @return bool
	 */
	public static function is_mariadb(): bool {
		return false !== stripos(
			self::server_info(),
			'mariadb'
		);
	}

	/**
	 * Tên họ database đang sử dụng.
	 *
	 * @return string
	 */
	public static function server_family(): string {
		return self::is_mariadb()
			? 'mariadb'
			: 'mysql';
	}

	/**
	 * Kiểm tra phiên bản database tối thiểu.
	 *
	 * @return bool
	 */
	public static function is_supported_server(): bool {
		$version = self::server_version();

		if ( '' === $version ) {
			return false;
		}

		$minimum = self::is_mariadb()
			? POWER_SCHEDULE_MANAGER_MIN_MARIADB_VERSION
			: POWER_SCHEDULE_MANAGER_MIN_MYSQL_VERSION;

		return version_compare(
			$version,
			$minimum,
			'>='
		);
	}

	/**
	 * Kiểm tra kết nối WordPress hỗ trợ utf8mb4.
	 *
	 * @return bool
	 */
	public static function supports_utf8mb4(): bool {
		global $wpdb;

		return method_exists( $wpdb, 'has_cap' )
			&& (bool) $wpdb->has_cap( 'utf8mb4' );
	}

	/**
	 * SQL alias cho hàng mới trong câu lệnh upsert.
	 *
	 * MySQL 8.0.19+ thay VALUES(column) bằng row alias. MariaDB hiện vẫn dùng
	 * VALUES(column), nên phương thức trả chuỗi rỗng trên MariaDB.
	 *
	 * @param string $alias Alias cố định do source code cung cấp.
	 * @return string
	 */
	public static function upsert_row_alias(
		string $alias = 'incoming'
	): string {
		self::assert_sql_identifier( $alias );

		if (
			self::is_mariadb()
			|| version_compare(
				self::server_version(),
				'8.0.19',
				'<'
			)
		) {
			return '';
		}

		return ' AS `' . $alias . '`';
	}

	/**
	 * Tham chiếu giá trị sắp được insert trong câu lệnh upsert.
	 *
	 * @param string $column Tên column cố định do source code cung cấp.
	 * @param string $alias  Alias cố định do source code cung cấp.
	 * @return string
	 */
	public static function upsert_value(
		string $column,
		string $alias = 'incoming'
	): string {
		self::assert_sql_identifier( $column );
		self::assert_sql_identifier( $alias );

		if ( '' === self::upsert_row_alias( $alias ) ) {
			return 'VALUES(`' . $column . '`)';
		}

		return '`'
			. $alias
			. '`.`'
			. $column
			. '`';
	}

	/**
	 * Lấy thời gian UTC hiện tại theo định dạng MySQL.
	 *
	 * @return string
	 */
	public static function utc_now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Lấy Unix timestamp UTC hiện tại.
	 *
	 * @return int
	 */
	public static function utc_timestamp(): int {
		return time();
	}

	/**
	 * Lấy lỗi database gần nhất.
	 *
	 * Chỉ dùng cho log nội bộ. Không hiển thị trực tiếp ra UI.
	 *
	 * @return string
	 */
	public static function last_error(): string {
		global $wpdb;

		if (
			! isset( $wpdb->last_error )
			|| ! is_string( $wpdb->last_error )
		) {
			return '';
		}

		$error = str_replace(
			array( "\r", "\n", "\0" ),
			' ',
			$wpdb->last_error
		);

		return substr(
			$error,
			0,
			2000
		);
	}

	/**
	 * Lấy ID vừa insert.
	 *
	 * @return int
	 */
	public static function insert_id(): int {
		global $wpdb;

		return isset( $wpdb->insert_id )
			? absint( $wpdb->insert_id )
			: 0;
	}

	/**
	 * Kiểm tra tên bảng có thuộc plugin không.
	 *
	 * @param string $table_name Tên bảng đầy đủ.
	 * @return bool
	 */
	public static function is_plugin_table(
		string $table_name
	): bool {
		return in_array(
			$table_name,
			array_values( self::tables() ),
			true
		);
	}

	/**
	 * Xác minh identifier SQL nội bộ.
	 *
	 * Phương thức chỉ nhận tên column hoặc alias hard-code trong source. Không
	 * truyền dữ liệu request hoặc dữ liệu người dùng vào đây.
	 *
	 * @param string $identifier Identifier cần kiểm tra.
	 * @return void
	 *
	 * @throws InvalidArgumentException Khi identifier không hợp lệ.
	 */
	private static function assert_sql_identifier(
		string $identifier
	): void {
		if (
			1 !== preg_match(
				'/\\A[a-z_][a-z0-9_]*\\z/i',
				$identifier
			)
		) {
			throw new InvalidArgumentException(
				'Invalid internal SQL identifier.'
			);
		}
	}

	/**
	 * Chuyển SHA-256 hexadecimal sang binary(32).
	 *
	 * @param string $hex_hash SHA-256 hexadecimal.
	 * @return string Binary hash dài 32 byte.
	 *
	 * @throws InvalidArgumentException Khi hash không hợp lệ.
	 */
	public static function hash_to_storage(
		string $hex_hash
	): string {
		$hex_hash = strtolower(
			trim( $hex_hash )
		);

		if (
			self::SHA256_HEX_LENGTH !== strlen( $hex_hash )
			|| 1 !== preg_match(
				'/^[a-f0-9]{64}$/',
				$hex_hash
			)
		) {
			throw new InvalidArgumentException(
				'Invalid SHA-256 hexadecimal hash.'
			);
		}

		$binary_hash = hex2bin(
			$hex_hash
		);

		if (
			false === $binary_hash
			|| self::SHA256_BINARY_LENGTH
				!== strlen( $binary_hash )
		) {
			throw new InvalidArgumentException(
				'Unable to convert SHA-256 hash to binary.'
			);
		}

		return $binary_hash;
	}

	/**
	 * Chuyển binary(32) thành SHA-256 hexadecimal.
	 *
	 * @param string $binary_hash Binary hash.
	 * @return string SHA-256 hexadecimal.
	 *
	 * @throws InvalidArgumentException Khi binary hash không hợp lệ.
	 */
	public static function hash_from_storage(
		string $binary_hash
	): string {
		if (
			self::SHA256_BINARY_LENGTH
			!== strlen( $binary_hash )
		) {
			throw new InvalidArgumentException(
				'Invalid SHA-256 binary hash.'
			);
		}

		$hex_hash = bin2hex(
			$binary_hash
		);

		if (
			self::SHA256_HEX_LENGTH
			!== strlen( $hex_hash )
		) {
			throw new InvalidArgumentException(
				'Unable to convert SHA-256 hash from binary.'
			);
		}

		return $hex_hash;
	}

	/**
	 * Tạo SHA-256 binary trực tiếp từ nội dung.
	 *
	 * @param string $content Nội dung cần hash.
	 * @return string Binary hash dài 32 byte.
	 */
	public static function hash_content_for_storage(
		string $content
	): string {
		return hash(
			'sha256',
			$content,
			true
		);
	}

	/**
	 * So sánh hai binary hash theo constant time.
	 *
	 * @param string $known_hash Binary hash đã lưu.
	 * @param string $user_hash  Binary hash cần kiểm tra.
	 * @return bool
	 */
	public static function hashes_equal(
		string $known_hash,
		string $user_hash
	): bool {
		if (
			self::SHA256_BINARY_LENGTH
				!== strlen( $known_hash )
			|| self::SHA256_BINARY_LENGTH
				!== strlen( $user_hash )
		) {
			return false;
		}

		return hash_equals(
			$known_hash,
			$user_hash
		);
	}

	/**
	 * Ngăn khởi tạo class tiện ích.
	 */
	private function __construct() {
	}
}
