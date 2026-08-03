<?php
/**
 * Public template loading.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads plugin templates with safe theme overrides.
 */
final class Power_Schedule_Manager_Template_Loader {

	/**
	 * Theme override directory.
	 */
	private const string THEME_DIRECTORY =
		'power-schedule-manager';

	/**
	 * Main templates.
	 *
	 * @var array<string, string>
	 */
	private const array MAIN_TEMPLATES = array(
		'single'   => 'single-schedule.php',
		'archive'  => 'archive-schedule.php',
		'taxonomy' => 'taxonomy-area.php',
	);

	/**
	 * Reusable template parts.
	 *
	 * @var array<string, string>
	 */
	private const array TEMPLATE_PARTS = array(
		'schedule-table' => 'schedule-table.php',
		'schedule-cards' => 'schedule-cards.php',
		'search-form'    => 'search-form.php',
		'empty-state'    => 'empty-state.php',
		'map'            => 'map.php',
		'area-links'     => 'area-links.php',
		'next-schedule'  => 'next-schedule.php',
		'schedule-alert' => 'schedule-alert.php',
		'schedule-days'  => 'schedule-days.php',
		'recent-updates' => 'recent-updates.php',
		'home-hub'       => 'home-hub.php',
		'home-hero'      => 'home-hero.php',
		'home-portal'    => 'home-portal.php',
		'home-guidance'  => 'home-guidance.php',
		'sponsor'        => 'sponsor.php',
		'market-prices'  => 'market-prices.php',
	);

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'template_include',
			array( $this, 'filter_template' ),
			99
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_template_assets' ),
			20
		);

		add_filter(
			'body_class',
			array( $this, 'filter_body_classes' )
		);
	}

	/**
	 * Select plugin template for the current request.
	 *
	 * @param string $template WordPress-selected template.
	 *
	 * @return string
	 */
	public function filter_template(
		string $template
	): string {
		$template_key = $this->current_template_key();

		if ( null === $template_key ) {
			return $template;
		}

		$resolved = self::resolve_main_template(
			$template_key
		);

		if ( null === $resolved ) {
			return $template;
		}

		/**
		 * Filters the selected public plugin template.
		 *
		 * Custom paths must remain inside the plugin, child theme, or parent
		 * theme. Invalid filtered paths are ignored.
		 *
		 * @param string $resolved     Resolved template.
		 * @param string $template_key Template key.
		 * @param string $template     Original WordPress template.
		 */
		$filtered = apply_filters(
			'power_schedule_manager_template',
			$resolved,
			$template_key,
			$template
		);

		if (
			is_string( $filtered )
			&& self::is_allowed_template_file( $filtered )
		) {
			return $filtered;
		}

		return $resolved;
	}

	/**
	 * Enqueue public assets for plugin template requests.
	 *
	 * @return void
	 */
	public function enqueue_template_assets(): void {
		if ( null === $this->current_template_key() ) {
			return;
		}

		Power_Schedule_Manager_Assets::enqueue_public_assets();
	}

	/**
	 * Add stable body classes.
	 *
	 * @param array<int, string> $classes Existing classes.
	 *
	 * @return array<int, string>
	 */
	public function filter_body_classes(
		array $classes
	): array {
		$template_key = $this->current_template_key();

		if ( null === $template_key ) {
			return $classes;
		}

		$classes[] = 'power-schedule-manager';
		$classes[] = 'psm-template-' . $template_key;

		return array_values(
			array_unique(
				array_map(
					'sanitize_html_class',
					$classes
				)
			)
		);
	}

	/**
	 * Render a reusable template part.
	 *
	 * Arguments are exposed through $psm_template_args. extract() is
	 * intentionally not used to prevent variable collisions.
	 *
	 * @param string               $part      Template part key.
	 * @param array<string, mixed> $arguments Template arguments.
	 *
	 * @return string
	 */
	public static function render_part(
		string $part,
		array $arguments = array()
	): string {
		if ( ! isset( self::TEMPLATE_PARTS[ $part ] ) ) {
			return '';
		}

		$template = self::resolve_template_file(
			self::TEMPLATE_PARTS[ $part ]
		);

		if ( null === $template ) {
			return '';
		}

		$psm_template_args = $arguments;

		ob_start();

		require $template;

		$output = ob_get_clean();

		return is_string( $output )
			? $output
			: '';
	}

	/**
	 * Return the key for the current plugin template request.
	 *
	 * @return string|null
	 */
	private function current_template_key(): ?string {
		if (
			is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			return 'single';
		}

		if (
			is_post_type_archive(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			return 'archive';
		}

		if (
			is_tax(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			return 'taxonomy';
		}

		return null;
	}

	/**
	 * Resolve main template.
	 *
	 * @param string $template_key Template key.
	 *
	 * @return string|null
	 */
	private static function resolve_main_template(
		string $template_key
	): ?string {
		if ( ! isset( self::MAIN_TEMPLATES[ $template_key ] ) ) {
			return null;
		}

		return self::resolve_template_file(
			self::MAIN_TEMPLATES[ $template_key ]
		);
	}

	/**
	 * Resolve a template with child/parent theme override support.
	 *
	 * Search order:
	 * 1. Child theme.
	 * 2. Parent theme.
	 * 3. Plugin public/templates directory.
	 *
	 * @param string $filename Allowlisted filename.
	 *
	 * @return string|null
	 */
	private static function resolve_template_file(
		string $filename
	): ?string {
		if (
			1 !== preg_match(
				'/\A[a-z0-9-]+\.php\z/',
				$filename
			)
		) {
			return null;
		}

		$theme_template = locate_template(
			array(
				self::THEME_DIRECTORY . '/' . $filename,
			),
			false,
			false
		);

		if (
			is_string( $theme_template )
			&& '' !== $theme_template
			&& self::is_allowed_template_file(
				$theme_template
			)
		) {
			return $theme_template;
		}

		$plugin_template =
			POWER_SCHEDULE_MANAGER_PATH
			. 'public/templates/'
			. $filename;

		if (
			self::is_allowed_template_file(
				$plugin_template
			)
		) {
			return realpath( $plugin_template ) ?: null;
		}

		return null;
	}

	/**
	 * Validate template path.
	 *
	 * Allowed roots:
	 * - Plugin public/templates.
	 * - Active child theme.
	 * - Active parent theme.
	 *
	 * @param string $template Template path.
	 *
	 * @return bool
	 */
	private static function is_allowed_template_file(
		string $template
	): bool {
		if (
			'' === $template
			|| ! str_ends_with(
				strtolower( $template ),
				'.php'
			)
		) {
			return false;
		}

		$real_template = realpath( $template );

		if (
			false === $real_template
			|| ! is_file( $real_template )
			|| ! is_readable( $real_template )
		) {
			return false;
		}

		$real_template = wp_normalize_path(
			$real_template
		);

		foreach ( self::allowed_roots() as $root ) {
			if (
				$real_template === $root
				|| str_starts_with(
					$real_template,
					$root . '/'
				)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return normalized allowed template roots.
	 *
	 * @return array<int, string>
	 */
	private static function allowed_roots(): array {
		$roots = array(
			POWER_SCHEDULE_MANAGER_PATH
				. 'public/templates',
			get_stylesheet_directory(),
			get_template_directory(),
		);

		$allowed = array();

		foreach ( $roots as $root ) {
			if ( ! is_string( $root ) || '' === $root ) {
				continue;
			}

			$real_root = realpath( $root );

			if ( false === $real_root ) {
				continue;
			}

			$allowed[] = wp_normalize_path(
				untrailingslashit( $real_root )
			);
		}

		return array_values(
			array_unique( $allowed )
		);
	}
}
