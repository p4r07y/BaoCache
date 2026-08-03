<?php
/**
 * Main plugin orchestrator.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initializes and coordinates plugin services.
 *
 * This class does not contain SQL, import logic, parser logic, or rendered
 * HTML. Every service constructor must be free of side effects. WordPress
 * hooks must only be attached by the service register() method.
 */
final class Power_Schedule_Manager_Plugin {

	/**
	 * Core services.
	 *
	 * Registration order is intentional:
	 * 1. Migration and capabilities.
	 * 2. Content structures and units.
	 * 3. Cache and scheduled maintenance.
	 * 4. Maps, templates, and SEO.
	 *
	 * @var array<string, class-string>
	 */
	private const array CORE_SERVICES = array(
		'migrator' => 'Power_Schedule_Manager_Migrator',

		'capabilities' => 'Power_Schedule_Manager_Capabilities',

		'post_type' => 'Power_Schedule_Manager_Post_Type',

		'content_blocks' => 'Power_Schedule_Manager_Content_Blocks',

		'page_seo_controls' => 'Power_Schedule_Manager_Page_SEO_Controls',

		'taxonomy' => 'Power_Schedule_Manager_Taxonomy',

		'units' => 'Power_Schedule_Manager_Units',

		'cache' => 'Power_Schedule_Manager_Cache',

		'cloudflare' => 'Power_Schedule_Manager_Cloudflare',

		'cron' => 'Power_Schedule_Manager_Cron',

		'notifications' => 'Power_Schedule_Manager_Notifications',

		'map' => 'Power_Schedule_Manager_Map',

		'place_library' => 'Power_Schedule_Manager_Place_Library',

		'osm_road_importer' => 'Power_Schedule_Manager_OSM_Road_Importer',

		'template_loader' => 'Power_Schedule_Manager_Template_Loader',

		'seo' => 'Power_Schedule_Manager_SEO',
	);

	/**
	 * Public frontend services.
	 *
	 * @var array<string, class-string>
	 */
	private const array PUBLIC_SERVICES = array(
		'assets' => 'Power_Schedule_Manager_Assets',

		'pwa' => 'Power_Schedule_Manager_PWA',

		'api' => 'Power_Schedule_Manager_API',

		'shortcodes' => 'Power_Schedule_Manager_Shortcodes',

		'sponsorship' => 'Power_Schedule_Manager_Sponsorship',

		'market_prices' => 'Power_Schedule_Manager_Market_Prices',

		'lottery' => 'Power_Schedule_Manager_Lottery',

		'weather' => 'Power_Schedule_Manager_Weather',
	);

	/**
	 * Administration services.
	 *
	 * @var array<string, class-string>
	 */
	private const array ADMIN_SERVICES = array(
		'backup' => 'Power_Schedule_Manager_Backup',

		'admin' => 'Power_Schedule_Manager_Admin',
	);

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Whether the plugin has already started.
	 */
	private bool $has_run = false;

	/**
	 * Registered service instances.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Return the singleton plugin instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Start the plugin.
	 *
	 * All service definitions are validated and instantiated before the first
	 * service attaches a WordPress hook. This avoids partially initialized
	 * runtime state.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When a service is missing or invalid.
	 */
	public function run(): void {
		if ( $this->has_run ) {
			return;
		}

		$definitions = $this->service_definitions();

		$this->validate_service_definitions( $definitions );

		$instances = $this->instantiate_services( $definitions );

		$this->register_textdomain_loader();
		$this->register_services( $instances );

		$this->services = $instances;
		$this->has_run  = true;

		/**
		 * Fires after every Cúp Điện Lâm Đồng service is registered.
		 *
		 * @param Power_Schedule_Manager_Plugin $plugin Plugin instance.
		 */
		do_action(
			'power_schedule_manager_loaded',
			$this
		);
	}

	/**
	 * Register the translation loader.
	 *
	 * @return void
	 */
	private function register_textdomain_loader(): void {
		add_action(
			'init',
			array( $this, 'load_textdomain' ),
			0
		);
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			POWER_SCHEDULE_MANAGER_TEXT_DOMAIN,
			false,
			dirname( POWER_SCHEDULE_MANAGER_BASENAME )
				. '/languages'
		);
	}

	/**
	 * Build service definitions for the current request.
	 *
	 * @return array<string, class-string>
	 *
	 * @throws RuntimeException When filtered definitions are invalid.
	 */
	private function service_definitions(): array {
		$definitions = array_merge(
			self::CORE_SERVICES,
			self::PUBLIC_SERVICES
		);

		if ( is_admin() ) {
			$definitions = array_merge(
				$definitions,
				self::ADMIN_SERVICES
			);
		}

		/**
		 * Filters trusted plugin service definitions.
		 *
		 * This filter must only be used by trusted PHP code. It must never
		 * receive class names from request parameters or database content.
		 *
		 * @param array<string, class-string>    $definitions Service map.
		 * @param Power_Schedule_Manager_Plugin $plugin      Plugin instance.
		 */
		$definitions = apply_filters(
			'power_schedule_manager_services',
			$definitions,
			$this
		);

		if ( ! is_array( $definitions ) ) {
			throw new RuntimeException(
				'invalid_service_definitions'
			);
		}

		return $definitions;
	}

	/**
	 * Validate all service definitions.
	 *
	 * Each service must:
	 * - Have a valid unique service ID.
	 * - Reference a valid class name.
	 * - Exist and be instantiable.
	 * - Have a constructor without required parameters.
	 * - Have a public register() method without required parameters.
	 *
	 * @param array<mixed, mixed> $definitions Service definitions.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When validation fails.
	 */
	private function validate_service_definitions(
		array $definitions
	): void {
		if ( array() === $definitions ) {
			throw new RuntimeException(
				'empty_service_definitions'
			);
		}

		foreach ( $definitions as $service_id => $class_name ) {
			if (
				! is_string( $service_id )
				|| 1 !== preg_match(
					'/\A[a-z][a-z0-9_]*\z/',
					$service_id
				)
			) {
				throw new RuntimeException(
					'invalid_service_id'
				);
			}

			if (
				! is_string( $class_name )
				|| 1 !== preg_match(
					'/\A[A-Za-z_][A-Za-z0-9_\\\\]*\z/',
					$class_name
				)
			) {
				throw new RuntimeException(
					'invalid_service_class'
				);
			}

			/*
			 * Plugin-owned classes must be present in the explicit autoloader
			 * allowlist. External trusted services added through the filter may
			 * use their own autoloader.
			 */
			if (
				str_starts_with(
					$class_name,
					'Power_Schedule_Manager_'
				)
				&& ! Power_Schedule_Manager_Autoloader::supports(
					$class_name
				)
			) {
				throw new RuntimeException(
					'plugin_service_not_mapped'
				);
			}

			if ( ! class_exists( $class_name ) ) {
				throw new RuntimeException(
					'service_class_missing'
				);
			}

			$reflection = new ReflectionClass( $class_name );

			if ( ! $reflection->isInstantiable() ) {
				throw new RuntimeException(
					'service_not_instantiable'
				);
			}

			$constructor = $reflection->getConstructor();

			if (
				null !== $constructor
				&& $constructor->getNumberOfRequiredParameters() > 0
			) {
				throw new RuntimeException(
					'service_constructor_requires_arguments'
				);
			}

			if ( ! $reflection->hasMethod( 'register' ) ) {
				throw new RuntimeException(
					'service_register_method_missing'
				);
			}

			$register_method = $reflection->getMethod(
				'register'
			);

			if (
				! $register_method->isPublic()
				|| $register_method->isStatic()
				|| $register_method->getNumberOfRequiredParameters() > 0
			) {
				throw new RuntimeException(
					'service_register_method_invalid'
				);
			}
		}
	}

	/**
	 * Instantiate all validated services.
	 *
	 * @param array<string, class-string> $definitions Service definitions.
	 *
	 * @return array<string, object>
	 */
	private function instantiate_services(
		array $definitions
	): array {
		$instances = array();

		foreach ( $definitions as $service_id => $class_name ) {
			$instances[ $service_id ] = new $class_name();
		}

		return $instances;
	}

	/**
	 * Register all service hooks.
	 *
	 * @param array<string, object> $instances Service instances.
	 *
	 * @return void
	 */
	private function register_services( array $instances ): void {
		foreach ( $instances as $service ) {
			$service->register();
		}
	}

	/**
	 * Return a registered service.
	 *
	 * @param string $service_id Service identifier.
	 *
	 * @return object|null
	 */
	public function service( string $service_id ): ?object {
		if (
			1 !== preg_match(
				'/\A[a-z][a-z0-9_]*\z/',
				$service_id
			)
		) {
			return null;
		}

		return $this->services[ $service_id ] ?? null;
	}

	/**
	 * Determine whether a service is registered.
	 *
	 * @param string $service_id Service identifier.
	 *
	 * @return bool
	 */
	public function has_service( string $service_id ): bool {
		return null !== $this->service( $service_id );
	}

	/**
	 * Return all registered service IDs.
	 *
	 * @return array<int, string>
	 */
	public function service_ids(): array {
		return array_keys( $this->services );
	}

	/**
	 * Determine whether the plugin has completed startup.
	 *
	 * @return bool
	 */
	public function has_run(): bool {
		return $this->has_run;
	}

	/**
	 * Prevent cloning the singleton.
	 */
	private function __clone(): void {
	}

	/**
	 * Prevent serialization.
	 *
	 * @return array<never, never>
	 *
	 * @throws LogicException Always.
	 */
	public function __serialize(): array {
		throw new LogicException(
			'Power_Schedule_Manager_Plugin cannot be serialized.'
		);
	}

	/**
	 * Prevent unserialization.
	 *
	 * @param array<mixed> $data Serialized data.
	 *
	 * @return void
	 *
	 * @throws LogicException Always.
	 */
	public function __unserialize( array $data ): void {
		unset( $data );

		throw new LogicException(
			'Power_Schedule_Manager_Plugin cannot be unserialized.'
		);
	}
}
