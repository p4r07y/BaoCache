<?php
/**
 * Secure plugin class autoloader.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads plugin classes from an explicit allowlist.
 *
 * Arbitrary class names are never converted directly into filesystem paths.
 * This makes class loading predictable and prevents path traversal.
 */
final class Power_Schedule_Manager_Autoloader {

	/**
	 * Explicit class-to-file map.
	 *
	 * Paths are relative to the plugin root.
	 *
	 * @var array<class-string, string>
	 */
	private const array CLASS_MAP = array(
		/*
		 * Plugin lifecycle and foundation.
		 */
		'Power_Schedule_Manager_Plugin' =>
			'includes/class-plugin.php',

		'Power_Schedule_Manager_Activator' =>
			'includes/class-activator.php',

		'Power_Schedule_Manager_Deactivator' =>
			'includes/class-deactivator.php',

		'Power_Schedule_Manager_Database' =>
			'includes/class-database.php',

		'Power_Schedule_Manager_Migrator' =>
			'includes/class-migrator.php',

		'Power_Schedule_Manager_Capabilities' =>
			'includes/class-capabilities.php',

		/*
		 * WordPress content model.
		 */
		'Power_Schedule_Manager_Post_Type' =>
			'includes/class-post-type.php',

		'Power_Schedule_Manager_Content_Blocks' =>
			'includes/class-content-blocks.php',

		'Power_Schedule_Manager_Page_SEO_Controls' =>
			'includes/class-page-seo-controls.php',

		'Power_Schedule_Manager_Taxonomy' =>
			'includes/class-taxonomy.php',

		'Power_Schedule_Manager_Units' =>
			'includes/class-units.php',

		/*
		 * Data processing.
		 */
		'Power_Schedule_Manager_Parser' =>
			'includes/class-parser.php',

		'Power_Schedule_Manager_Validator' =>
			'includes/class-validator.php',

		'Power_Schedule_Manager_Preview' =>
			'includes/class-preview.php',

		'Power_Schedule_Manager_Importer' =>
			'includes/class-importer.php',

		'Power_Schedule_Manager_Repository' =>
			'includes/class-repository.php',

		'Power_Schedule_Manager_Status' =>
			'includes/class-status.php',

		/*
		 * Map and geographic data.
		 */
		'Power_Schedule_Manager_GeoJSON' =>
			'includes/class-geojson.php',

		'Power_Schedule_Manager_Map' =>
			'includes/class-map.php',

		'Power_Schedule_Manager_API' =>
			'includes/class-api.php',

		'Power_Schedule_Manager_Place_Library' =>
			'includes/class-place-library.php',

		'Power_Schedule_Manager_OSM_Road_Importer' =>
			'includes/class-osm-road-importer.php',

		/*
		 * Frontend, templates, assets, and SEO.
		 */
		'Power_Schedule_Manager_Renderer' =>
			'includes/class-renderer.php',

		'Power_Schedule_Manager_Shortcodes' =>
			'includes/class-shortcodes.php',

		'Power_Schedule_Manager_Sponsorship' =>
			'includes/class-sponsorship.php',

		'Power_Schedule_Manager_Market_Prices' =>
			'includes/class-market-prices.php',

		'Power_Schedule_Manager_Lottery' =>
			'includes/class-lottery.php',

		'Power_Schedule_Manager_Lottery_Renderer' =>
			'includes/class-lottery-renderer.php',

		'Power_Schedule_Manager_Weather' =>
			'includes/class-weather.php',

		'Power_Schedule_Manager_Template_Loader' =>
			'includes/class-template-loader.php',

		'Power_Schedule_Manager_Assets' =>
			'includes/class-assets.php',

		'Power_Schedule_Manager_PWA' =>
			'includes/class-pwa.php',

		'Power_Schedule_Manager_SEO' =>
			'includes/class-seo.php',

		/*
		 * Cache, logging, and maintenance.
		 */
		'Power_Schedule_Manager_Cache' =>
			'includes/class-cache.php',

		'Power_Schedule_Manager_Cloudflare' =>
			'includes/class-cloudflare.php',

		'Power_Schedule_Manager_Secrets' =>
			'includes/class-secrets.php',

		'Power_Schedule_Manager_Cron' =>
			'includes/class-cron.php',

		'Power_Schedule_Manager_Cleanup' =>
			'includes/class-cleanup.php',

		'Power_Schedule_Manager_Logger' =>
			'includes/class-logger.php',

		'Power_Schedule_Manager_Dashboard_Stats' =>
			'includes/class-dashboard-stats.php',

		'Power_Schedule_Manager_System_Health' =>
			'includes/class-system-health.php',

		'Power_Schedule_Manager_Backup' =>
			'includes/class-backup.php',

		'Power_Schedule_Manager_Notifications' =>
			'includes/class-notifications.php',

		'Power_Schedule_Manager_Benchmark' =>
			'includes/class-benchmark.php',

		/*
		 * Administration.
		 */
		'Power_Schedule_Manager_Admin' =>
			'admin/class-admin.php',

		'Power_Schedule_Manager_Schedule_List' =>
			'admin/class-schedule-list.php',

		'Power_Schedule_Manager_Map_Editor' =>
			'admin/class-map-editor.php',
	);

	/**
	 * Whether the autoloader is registered.
	 */
	private static bool $registered = false;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Register the autoloader.
	 *
	 * Calling this method more than once is safe.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		$registered = spl_autoload_register(
			array( self::class, 'autoload' ),
			true,
			true
		);

		if ( false === $registered ) {
			throw new RuntimeException(
				'power_schedule_manager_autoloader_registration_failed'
			);
		}

		self::$registered = true;
	}

	/**
	 * Unregister the autoloader.
	 *
	 * This is primarily useful for automated tests.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		if ( ! self::$registered ) {
			return;
		}

		spl_autoload_unregister(
			array( self::class, 'autoload' )
		);

		self::$registered = false;
	}

	/**
	 * Load a mapped plugin class.
	 *
	 * Unknown classes are ignored. Missing mapped files are also ignored here
	 * so dependency validation can fail in a controlled location.
	 *
	 * @param string $class_name Requested class name.
	 *
	 * @return void
	 */
	public static function autoload( string $class_name ): void {
		if (
			'' === $class_name
			|| class_exists( $class_name, false )
			|| interface_exists( $class_name, false )
			|| trait_exists( $class_name, false )
			|| ! isset( self::CLASS_MAP[ $class_name ] )
		) {
			return;
		}

		$file = self::resolve_path(
			self::CLASS_MAP[ $class_name ]
		);

		if ( null === $file ) {
			return;
		}

		require_once $file;
	}

	/**
	 * Determine whether the autoloader supports a class.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return bool
	 */
	public static function supports( string $class_name ): bool {
		return isset( self::CLASS_MAP[ $class_name ] );
	}

	/**
	 * Return all mapped class names.
	 *
	 * @return array<int, class-string>
	 */
	public static function registered_classes(): array {
		return array_keys( self::CLASS_MAP );
	}

	/**
	 * Return mapped classes whose files do not currently exist.
	 *
	 * Useful for controlled installation diagnostics.
	 *
	 * @return array<class-string, string>
	 */
	public static function missing_files(): array {
		$missing = array();

		foreach ( self::CLASS_MAP as $class_name => $relative_path ) {
			if ( null === self::resolve_path( $relative_path ) ) {
				$missing[ $class_name ] = $relative_path;
			}
		}

		return $missing;
	}

	/**
	 * Resolve a mapped file safely.
	 *
	 * Both the plugin root and candidate file are resolved to their real
	 * filesystem locations. A candidate outside the plugin directory is
	 * rejected even if a symlink is involved.
	 *
	 * @param string $relative_path Relative mapped path.
	 *
	 * @return string|null
	 */
	private static function resolve_path(
		string $relative_path
	): ?string {
		if (
			! defined( 'POWER_SCHEDULE_MANAGER_PATH' )
			|| ! self::is_safe_relative_path( $relative_path )
		) {
			return null;
		}

		$plugin_root = realpath(
			POWER_SCHEDULE_MANAGER_PATH
		);

		if ( false === $plugin_root ) {
			return null;
		}

		$candidate = realpath(
			trailingslashit( $plugin_root )
				. $relative_path
		);

		if ( false === $candidate ) {
			return null;
		}

		$plugin_root = wp_normalize_path(
			untrailingslashit( $plugin_root )
		);

		$candidate = wp_normalize_path(
			$candidate
		);

		if (
			! str_starts_with(
				$candidate,
				$plugin_root . '/'
			)
		) {
			return null;
		}

		if (
			! is_file( $candidate )
			|| ! is_readable( $candidate )
		) {
			return null;
		}

		return $candidate;
	}

	/**
	 * Validate a mapped relative path.
	 *
	 * @param string $relative_path Relative path.
	 *
	 * @return bool
	 */
	private static function is_safe_relative_path(
		string $relative_path
	): bool {
		if ( '' === $relative_path ) {
			return false;
		}

		if (
			str_starts_with( $relative_path, '/' )
			|| str_starts_with( $relative_path, '\\' )
			|| str_contains( $relative_path, "\0" )
			|| str_contains( $relative_path, '..' )
			|| str_contains( $relative_path, '\\' )
		) {
			return false;
		}

		return 1 === preg_match(
			'/\A(?:includes|admin)\/[a-z0-9-]+\.php\z/',
			$relative_path
		);
	}
}
