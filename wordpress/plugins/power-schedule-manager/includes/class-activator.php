<?php
/**
 * Plugin activation handler.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Performs controlled plugin activation.
 */
final class Power_Schedule_Manager_Activator {

	/**
	 * Required activation dependencies.
	 *
	 * @var array<class-string, array{method: string, static: bool}>
	 */
	private const array DEPENDENCIES = array(
		'Power_Schedule_Manager_Migrator' => array(
			'method' => 'activate',
			'static' => true,
		),
		'Power_Schedule_Manager_Capabilities' => array(
			'method' => 'install',
			'static' => true,
		),
		'Power_Schedule_Manager_Post_Type' => array(
			'method' => 'register_post_type',
			'static' => false,
		),
		'Power_Schedule_Manager_Taxonomy' => array(
			'method' => 'register_taxonomy',
			'static' => false,
		),
		'Power_Schedule_Manager_Units' => array(
			'method' => 'install_or_update',
			'static' => true,
		),
		'Power_Schedule_Manager_Cron' => array(
			'method' => 'schedule_events',
			'static' => true,
		),
	);

	/**
	 * Activate the plugin.
	 *
	 * Order is important:
	 * 1. Validate environment and dependencies.
	 * 2. Create and verify database schema.
	 * 3. Install capabilities.
	 * 4. Register CPT and taxonomy.
	 * 5. Seed units and create taxonomy terms.
	 * 6. Schedule maintenance tasks.
	 * 7. Store runtime versions.
	 * 8. Flush rewrite rules once.
	 *
	 * @param bool $network_wide Whether network activation was requested.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When activation cannot complete.
	 */
	public static function activate( bool $network_wide = false ): void {
		self::validate_environment( $network_wide );
		self::validate_dependencies();

		/**
		 * Fires before activation changes are applied.
		 *
		 * This hook is not an authorization mechanism.
		 *
		 * @param bool $network_wide Network activation state.
		 */
		do_action(
			'power_schedule_manager_before_activation',
			$network_wide
		);

		try {
			Power_Schedule_Manager_Migrator::activate();
			Power_Schedule_Manager_Capabilities::install();

			self::register_content_structures();

			Power_Schedule_Manager_Units::install_or_update();
			Power_Schedule_Manager_Cron::schedule_events();

			self::store_runtime_options();

			flush_rewrite_rules( false );

			/**
			 * Fires after successful activation.
			 *
			 * @param bool $network_wide Network activation state.
			 */
			do_action(
				'power_schedule_manager_activated',
				$network_wide
			);
		} catch ( Throwable $throwable ) {
			self::handle_failure( $throwable );

			throw new RuntimeException(
				'power_schedule_manager_activation_failed',
				0,
				$throwable
			);
		}
	}

	/**
	 * Validate server requirements.
	 *
	 * @param bool $network_wide Whether network activation was requested.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When requirements are not met.
	 */
	private static function validate_environment(
		bool $network_wide
	): void {
		global $wp_version;

		if (
			version_compare(
				PHP_VERSION,
				POWER_SCHEDULE_MANAGER_MIN_PHP_VERSION,
				'<'
			)
		) {
			throw new RuntimeException(
				'php_version_not_supported'
			);
		}

		if (
			! isset( $wp_version )
			|| ! is_string( $wp_version )
			|| version_compare(
				$wp_version,
				POWER_SCHEDULE_MANAGER_MIN_WP_VERSION,
				'<'
			)
		) {
			throw new RuntimeException(
				'wordpress_version_not_supported'
			);
		}

		if (
			! class_exists( 'Power_Schedule_Manager_Database' )
		) {
			throw new RuntimeException(
				'database_service_unavailable'
			);
		}

		if (
			! Power_Schedule_Manager_Database::is_supported_server()
		) {
			throw new RuntimeException(
				'database_version_not_supported'
			);
		}

		if (
			! Power_Schedule_Manager_Database::supports_utf8mb4()
		) {
			throw new RuntimeException(
				'database_utf8mb4_not_supported'
			);
		}

		if ( is_multisite() && $network_wide ) {
			throw new RuntimeException(
				'network_activation_not_supported'
			);
		}

	}

	/**
	 * Validate every activation dependency.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When a dependency is missing or invalid.
	 */
	private static function validate_dependencies(): void {
		foreach (
			self::DEPENDENCIES as $class_name => $definition
		) {
			if (
				! Power_Schedule_Manager_Autoloader::supports(
					$class_name
				)
			) {
				throw new RuntimeException(
					'activation_dependency_not_mapped'
				);
			}

			if ( ! class_exists( $class_name ) ) {
				throw new RuntimeException(
					'activation_dependency_missing'
				);
			}

			$reflection = new ReflectionClass( $class_name );

			if ( ! $reflection->hasMethod( $definition['method'] ) ) {
				throw new RuntimeException(
					'activation_dependency_method_missing'
				);
			}

			$method = $reflection->getMethod(
				$definition['method']
			);

			if (
				! $method->isPublic()
				|| $method->isStatic() !== $definition['static']
				|| $method->getNumberOfRequiredParameters() > 0
			) {
				throw new RuntimeException(
					'activation_dependency_method_invalid'
				);
			}

			if ( ! $definition['static'] ) {
				if ( ! $reflection->isInstantiable() ) {
					throw new RuntimeException(
						'activation_dependency_not_instantiable'
					);
				}

				$constructor = $reflection->getConstructor();

				if (
					null !== $constructor
					&& $constructor->getNumberOfRequiredParameters() > 0
				) {
					throw new RuntimeException(
						'activation_dependency_constructor_invalid'
					);
				}
			}
		}

		if (
			! is_callable(
				array(
					'Power_Schedule_Manager_Cron',
					'clear_scheduled_events',
				)
			)
		) {
			throw new RuntimeException(
				'cron_cleanup_method_missing'
			);
		}
	}

	/**
	 * Register the CPT and taxonomy before unit synchronization.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When registration fails.
	 */
	private static function register_content_structures(): void {
		$post_type = new Power_Schedule_Manager_Post_Type();
		$taxonomy  = new Power_Schedule_Manager_Taxonomy();

		$post_type->register_post_type();
		$taxonomy->register_taxonomy();

		if (
			! post_type_exists(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			throw new RuntimeException(
				'post_type_registration_failed'
			);
		}

		if (
			! taxonomy_exists(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			throw new RuntimeException(
				'taxonomy_registration_failed'
			);
		}
	}

	/**
	 * Store plugin runtime options.
	 *
	 * Schema and seed versions are owned by their respective services and are
	 * not written here.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When an option cannot be verified.
	 */
	private static function store_runtime_options(): void {
		update_option(
			POWER_SCHEDULE_MANAGER_VERSION_OPTION,
			POWER_SCHEDULE_MANAGER_VERSION,
			false
		);

		$stored_version = get_option(
			POWER_SCHEDULE_MANAGER_VERSION_OPTION,
			''
		);

		if (
			! is_string( $stored_version )
			|| POWER_SCHEDULE_MANAGER_VERSION !== $stored_version
		) {
			throw new RuntimeException(
				'plugin_version_not_saved'
			);
		}

		$cache_version = get_option(
			POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
			0
		);

		$cache_version = is_numeric( $cache_version )
			? max( 1, (int) $cache_version )
			: 1;

		update_option(
			POWER_SCHEDULE_MANAGER_CACHE_VERSION_OPTION,
			$cache_version,
			false
		);
	}

	/**
	 * Clean up reversible runtime state after failed activation.
	 *
	 * Database tables and imported content are intentionally preserved.
	 *
	 * @param Throwable $throwable Original activation exception.
	 *
	 * @return void
	 */
	private static function handle_failure(
		Throwable $throwable
	): void {
		if (
			class_exists( 'Power_Schedule_Manager_Cron' )
			&& is_callable(
				array(
					'Power_Schedule_Manager_Cron',
					'clear_scheduled_events',
				)
			)
		) {
			Power_Schedule_Manager_Cron::clear_scheduled_events();
		}

		delete_option(
			POWER_SCHEDULE_MANAGER_VERSION_OPTION
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Cúp Điện Lâm Đồng activation failed [%s] at %s:%d: %s',
					get_class( $throwable ),
					$throwable->getFile(),
					$throwable->getLine(),
					$throwable->getMessage()
				)
			);
		}
	}
}
