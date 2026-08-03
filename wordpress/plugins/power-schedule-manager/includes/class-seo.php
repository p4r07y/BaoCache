<?php
/**
 * SEO and Rank Math compatibility.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides contextual SEO metadata without duplicate output.
 */
final class Power_Schedule_Manager_SEO {

	/**
	 * Maximum meta-description length.
	 */
	private const int DESCRIPTION_LENGTH = 160;

	/**
	 * Register SEO integration.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( self::rank_math_is_active() ) {
			$this->register_rank_math_filters();

			return;
		}

		/*
		 * Another SEO plugin owns metadata output. Do not create duplicate
		 * title, canonical, robots, description, or JSON-LD tags.
		 */
		if ( self::another_seo_plugin_is_active() ) {
			return;
		}

		$this->register_fallback_hooks();
	}

	/**
	 * Register Rank Math filters.
	 *
	 * @return void
	 */
	private function register_rank_math_filters(): void {
		add_filter(
			'rank_math/frontend/robots',
			array( $this, 'filter_rank_math_robots' ),
			20
		);

		add_filter(
			'rank_math/frontend/breadcrumb/items',
			array( $this, 'filter_rank_math_breadcrumb_items' ),
			90,
			2
		);
	}

	/**
	 * Register lightweight fallback SEO hooks.
	 *
	 * @return void
	 */
	private function register_fallback_hooks(): void {
		add_filter(
			'pre_get_document_title',
			array( $this, 'filter_title' ),
			20
		);

		add_filter(
			'wp_robots',
			array( $this, 'filter_wordpress_robots' ),
			20
		);

		add_filter(
			'get_canonical_url',
			array( $this, 'filter_canonical' ),
			20
		);

		add_action(
			'wp_head',
			array( $this, 'render_fallback_description' ),
			2
		);

		add_action(
			'wp_head',
			array( $this, 'render_fallback_archive_canonical' ),
			9
		);

		add_action(
			'wp_head',
			array( $this, 'render_fallback_json_ld' ),
			30
		);
	}

	/**
	 * Filter SEO title.
	 *
	 * @param string $title Existing title.
	 *
	 * @return string
	 */
	public function filter_title( string $title ): string {
		if ( ! self::is_plugin_request() ) {
			return $title;
		}

		if (
			is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			$post_id = get_queried_object_id();
			$unit_code = get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
				true
			);
			$local_date = get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
				true
			);
			$unit = is_string( $unit_code )
				? Power_Schedule_Manager_Units::find_by_code( $unit_code )
				: null;
			$date = is_string( $local_date )
				? DateTimeImmutable::createFromFormat(
					'!Y-m-d',
					$local_date
				)
				: false;

			if ( null !== $unit && false !== $date ) {
				return Power_Schedule_Manager_Post_Type::build_schedule_title(
					(string) $unit['name'],
					$date
				);
			}

			$post_title = single_post_title( '', false );

			return '' !== $post_title
				? $post_title
				: $title;
		}

		if (
			is_tax(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				return sprintf(
					/* translators: %s: Electrical area. */
					__(
						'Lịch cúp điện %s mới nhất',
						'power-schedule-manager'
					),
					$term->name
				);
			}
		}

		if (
			is_post_type_archive(
				Power_Schedule_Manager_Post_Type::POST_TYPE
				)
			) {
			return __(
					'Cúp Điện Lâm Đồng — lịch điện mới nhất',
					'power-schedule-manager'
				);
			}

		return $title;
	}

	/**
	 * Filter meta description.
	 *
	 * @param string $description Existing description.
	 *
	 * @return string
	 */
	public function filter_description(
		string $description
	): string {
		if ( ! self::is_plugin_request() ) {
			return $description;
		}

		$generated = self::description();

		return '' !== $generated
			? $generated
			: $description;
	}

	/**
	 * Filter canonical URL.
	 *
	 * Search/filter parameters intentionally canonicalize to the unfiltered
	 * archive or taxonomy URL.
	 *
	 * @param string $canonical Existing canonical.
	 *
	 * @return string
	 */
	public function filter_canonical(
		string $canonical
	): string {
		if ( ! self::is_plugin_request() ) {
			return $canonical;
		}

		$generated = self::canonical_url();

		return '' !== $generated
			? $generated
			: $canonical;
	}

	/**
	 * Filter Rank Math robots directives.
	 *
	 * @param array<string, string> $robots Directives.
	 *
	 * @return array<string, string>
	 */
	public function filter_rank_math_robots(
		array $robots
	): array {
		if ( ! self::is_plugin_request() ) {
			return $robots;
		}

		if ( self::should_noindex() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';

			return $robots;
		}

		return $robots;
	}

	/**
	 * Keep Rank Math breadcrumb output and schema aligned with plugin pages.
	 *
	 * @param array<int, array<int, string>> $crumbs      Existing crumbs.
	 * @param mixed                          $breadcrumbs Rank Math instance.
	 *
	 * @return array<int, array<int, string>>
	 */
	public function filter_rank_math_breadcrumb_items(
		array $crumbs,
		mixed $breadcrumbs
	): array {
		unset( $breadcrumbs );

		if ( self::utility_page_has_managed_hero() ) {
			$utility_page = get_page_by_path( 'tien-ich' );
			$utility_url = $utility_page instanceof WP_Post
				? (string) get_permalink( $utility_page ) : '';
			$utility_crumb = array(
				__( 'Tiện ích', 'power-schedule-manager' ),
				$utility_url,
			);
			$has_utility = count(
				array_filter(
					$crumbs,
					static fn ( array $crumb ): bool =>
						isset( $crumb[0] )
						&& __( 'Tiện ích', 'power-schedule-manager' ) === (string) $crumb[0]
				)
			) > 0;
			if ( ! $has_utility && count( $crumbs ) >= 1 ) {
				array_splice( $crumbs, max( 1, count( $crumbs ) - 1 ), 0, array( $utility_crumb ) );
			}

			return $crumbs;
		}

		if ( ! self::is_plugin_request() ) {
			return $crumbs;
		}

		$items = self::breadcrumb_items();

		if ( count( $items ) < 2 ) {
			return $crumbs;
		}

		return array_map(
			static fn ( array $item ): array => array(
				(string) $item['name'],
				! empty( $item['current'] )
					? ''
					: (string) $item['url'],
			),
			$items
		);
	}

	/** Whether the current Page uses the plugin's visible utility hero. */
	private static function utility_page_has_managed_hero(): bool {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		$presentation = get_post_meta(
			$post->ID,
			'_psm_page_presentation',
			true
		);

		return (
			is_array( $presentation )
			&& 'plugin' === (string) ( $presentation['owner'] ?? '' )
		) || has_shortcode(
			$post->post_content,
			Power_Schedule_Manager_Shortcodes::PAGE_HERO_SHORTCODE
		);
	}

	/**
	 * Filter WordPress robots directives.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 *
	 * @return array<string, bool|string>
	 */
	public function filter_wordpress_robots(
		array $robots
	): array {
		if ( ! self::is_plugin_request() ) {
			return $robots;
		}

		if ( self::should_noindex() ) {
			unset( $robots['index'] );

			$robots['noindex'] = true;
			$robots['follow']  = true;

			return $robots;
		}

		unset( $robots['noindex'] );

		$robots['index']  = true;
		$robots['follow'] = true;

		return $robots;
	}

	/**
	 * Extend Rank Math JSON-LD.
	 *
	 * @param array<string, mixed> $data   Rank Math graph.
	 * @param mixed                $jsonld Rank Math JSON-LD instance.
	 *
	 * @return array<string, mixed>
	 */
	public function filter_rank_math_json_ld(
		array $data,
		mixed $jsonld
	): array {
		unset( $jsonld );

		if (
			! self::is_plugin_request()
			|| self::should_noindex()
		) {
			return $data;
		}

		$item_list = self::schedule_item_list_schema();

		if ( null !== $item_list ) {
			$data['psmScheduleList'] = $item_list;
		}

		return $data;
	}

	/**
	 * Render fallback meta description.
	 *
	 * @return void
	 */
	public function render_fallback_description(): void {
		if ( ! self::is_plugin_request() ) {
			return;
		}

		$description = self::description();

		if ( '' === $description ) {
			return;
		}

		printf(
			'<meta name="description" content="%s">' . "\n",
			esc_attr( $description )
		);
	}

	/**
	 * Render archive/taxonomy fallback canonical.
	 *
	 * WordPress core already renders singular canonical links.
	 *
	 * @return void
	 */
	public function render_fallback_archive_canonical(): void {
		if (
			! self::is_plugin_request()
			|| is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			return;
		}

		$canonical = self::canonical_url();

		if ( '' === $canonical ) {
			return;
		}

		printf(
			'<link rel="canonical" href="%s">' . "\n",
			esc_url( $canonical )
		);
	}

	/**
	 * Render fallback JSON-LD.
	 *
	 * @return void
	 */
	public function render_fallback_json_ld(): void {
		if (
			! self::is_plugin_request()
			|| self::should_noindex()
		) {
			return;
		}

		$graph = array(
			array(
				'@type'       => is_singular()
					? 'WebPage'
					: 'CollectionPage',
				'@id'         => self::canonical_url() . '#webpage',
				'url'         => self::canonical_url(),
				'name'        => wp_get_document_title(),
				'description' => self::description(),
				'inLanguage'  => get_bloginfo( 'language' ),
			),
		);

		$item_list = self::schedule_item_list_schema();

		if ( null !== $item_list ) {
			$graph[] = $item_list;
		}

		$breadcrumb = self::breadcrumb_schema();

		if ( null !== $breadcrumb ) {
			$graph[] = $breadcrumb;
		}

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_HEX_TAG
				| JSON_HEX_AMP
				| JSON_HEX_APOS
				| JSON_HEX_QUOT
		);

		if ( ! is_string( $json ) ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Build contextual description.
	 *
	 * @return string
	 */
	private static function description(): string {
		if (
			is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			$post_id = get_queried_object_id();
			$excerpt = get_post_field(
				'post_excerpt',
				$post_id
			);

			$is_legacy_excerpt = is_string( $excerpt )
				&& str_starts_with(
					trim( $excerpt ),
					'Lịch ngừng, giảm cung cấp điện của Điện lực '
				);

			if (
				is_string( $excerpt )
				&& '' !== trim( $excerpt )
				&& ! $is_legacy_excerpt
			) {
				return self::limit_description(
					$excerpt
				);
			}

			$unit_code = get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
				true
			);

			$local_date = get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
				true
			);

			$unit = is_string( $unit_code )
				? Power_Schedule_Manager_Units::find_by_code(
					$unit_code
				)
				: null;

			if (
				null !== $unit
				&& is_string( $local_date )
			) {
				$date = DateTimeImmutable::createFromFormat(
					'!Y-m-d',
					$local_date
				);

				if ( false !== $date ) {
					return self::limit_description(
						sprintf(
							/* translators: 1: Unit, 2: Date. */
							__(
								'Lịch cúp điện %1$s ngày %2$s: tra cứu thời gian mất điện, khu vực ảnh hưởng, lý do và trạng thái cập nhật mới nhất.',
								'power-schedule-manager'
							),
							Power_Schedule_Manager_Post_Type::location_name(
								(string) $unit['name']
							),
							$date->format( 'd/m/Y' )
						)
					);
				}
			}
		}

		if (
			is_tax(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$description = term_description( $term );

				if ( '' !== trim( $description ) ) {
					return self::limit_description(
						wp_strip_all_tags( $description )
					);
				}

				return self::limit_description(
					sprintf(
						/* translators: %s: Electrical area. */
						__(
							'Tra cứu lịch cúp điện %s mới nhất, gồm khu vực, thời gian và lý do ngừng cung cấp điện.',
							'power-schedule-manager'
						),
						$term->name
					)
				);
			}
		}

		return self::limit_description(
			__(
				'Cúp Điện Lâm Đồng giúp tra cứu lịch điện hôm nay và ngày mai, gồm thời gian, khu vực ảnh hưởng, lý do và trạng thái theo từng điện lực.',
				'power-schedule-manager'
			)
		);
	}

	/**
	 * Return canonical URL.
	 *
	 * @return string
	 */
	private static function canonical_url(): string {
		if (
			is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			$url = get_permalink(
				get_queried_object_id()
			);

			return is_string( $url ) ? $url : '';
		}

		if (
			is_tax(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			$url = get_term_link(
				get_queried_object()
			);

			return is_wp_error( $url ) ? '' : $url;
		}

		$url = get_post_type_archive_link(
			Power_Schedule_Manager_Post_Type::POST_TYPE
		);

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Determine whether current plugin page should be noindex.
	 *
	 * @return bool
	 */
	private static function should_noindex(): bool {
		if (
			isset( $_GET['psm_unit'] )
			|| isset( $_GET['psm_date'] )
			|| isset( $_GET['psm_page'] )
		) {
			return true;
		}

		if (
			is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			$events = self::current_single_events();

			if ( array() === $events ) {
				return true;
			}

			foreach ( $events as $event ) {
				if (
					Power_Schedule_Manager_Status::is_indexable(
						(string) $event['status']
					)
				) {
					return false;
				}
			}

			return true;
		}

		if (
			is_tax(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			$term = get_queried_object();

			if ( ! $term instanceof WP_Term ) {
				return true;
			}

			$is_public = get_term_meta(
				$term->term_id,
				Power_Schedule_Manager_Taxonomy::META_IS_PUBLIC,
				true
			);

			return ! Power_Schedule_Manager_Taxonomy::sanitize_boolean(
				$is_public
			);
		}

		return false;
	}

	/**
	 * Build ItemList schema from current singular schedule.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function schedule_item_list_schema(): ?array {
		if (
			! is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			return null;
		}

		$events = self::current_single_events();

		if ( array() === $events ) {
			return null;
		}

		$items    = array();
		$position = 1;
		$url      = self::canonical_url();

		foreach ( array_slice( $events, 0, 50 ) as $event ) {
			if (
				! Power_Schedule_Manager_Status::is_publicly_visible(
					(string) $event['status']
				)
			) {
				continue;
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'url'      => $url . '#lich-' . (int) $event['id'],
				'name'     => (string) $event['area'],
				'item'     => array(
					'@type'       => 'Thing',
					'name'        => (string) $event['area'],
					'description' => (string) $event['reason'],
				),
			);

			++$position;
		}

		if ( array() === $items ) {
			return null;
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => $url . '#schedule-list',
			'name'            => get_the_title(),
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
	}

	/**
	 * Build the visible breadcrumb hierarchy for plugin-owned pages.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	private static function breadcrumb_items(): array {
		$items = array(
			array(
				'name'    => __(
					'Trang chủ',
					'power-schedule-manager'
				),
				'url'     => home_url( '/' ),
				'current' => false,
			),
		);

		$archive_url = get_post_type_archive_link(
			Power_Schedule_Manager_Post_Type::POST_TYPE
		);

		if ( is_string( $archive_url ) && '' !== $archive_url ) {
			$items[] = array(
				'name'    => __(
					'Lịch điện tại Lâm Đồng',
					'power-schedule-manager'
				),
				'url'     => $archive_url,
				'current' => is_post_type_archive(
					Power_Schedule_Manager_Post_Type::POST_TYPE
				),
			);
		}

		if (
			is_tax(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			)
		) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$term_url = get_term_link( $term );

				$items[] = array(
					'name'    => $term->name,
					'url'     => is_wp_error( $term_url )
						? self::canonical_url()
						: $term_url,
					'current' => true,
				);
			}

			return $items;
		}

		if (
			! is_singular(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
		) {
			return $items;
		}

		$post_id = get_queried_object_id();
		$terms   = get_the_terms(
			$post_id,
			Power_Schedule_Manager_Taxonomy::TAXONOMY
		);

		if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}

				$term_url = get_term_link( $term );

				if ( is_wp_error( $term_url ) ) {
					continue;
				}

				$items[] = array(
					'name'    => $term->name,
					'url'     => $term_url,
					'current' => false,
				);
				break;
			}
		}

		$local_date = get_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
			true
		);
		$date_label = '';

		if ( is_string( $local_date ) ) {
			$date = DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				$local_date,
				new DateTimeZone(
					POWER_SCHEDULE_MANAGER_TIMEZONE
				)
			);

			if ( $date instanceof DateTimeImmutable ) {
				$date_label = sprintf(
					/* translators: %s: Schedule date. */
					__(
						'Ngày %s',
						'power-schedule-manager'
					),
					$date->format( 'd/m/Y' )
				);
			}
		}

		$items[] = array(
			'name'    => '' !== $date_label
				? $date_label
				: get_the_title( $post_id ),
			'url'     => self::canonical_url(),
			'current' => true,
		);

		return $items;
	}

	/**
	 * Build BreadcrumbList schema when no SEO plugin owns JSON-LD.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function breadcrumb_schema(): ?array {
		$items = self::breadcrumb_items();

		if ( count( $items ) < 2 ) {
			return null;
		}

		$list = array();

		foreach ( $items as $index => $item ) {
			$list_item = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => (string) $item['name'],
			);

			if (
				empty( $item['current'] )
				&& '' !== (string) $item['url']
			) {
				$list_item['item'] = (string) $item['url'];
			}

			$list[] = $list_item;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => self::canonical_url() . '#breadcrumb',
			'itemListElement' => $list,
		);
	}

	/**
	 * Return events linked to current schedule post.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function current_single_events(): array {
		$post_id = get_queried_object_id();

		if ( $post_id < 1 ) {
			return array();
		}

		$unit_code = get_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
			true
		);

		$local_date = get_post_meta(
			$post_id,
			Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
			true
		);

		if (
			! is_string( $unit_code )
			|| ! is_string( $local_date )
			|| '' === $unit_code
			|| '' === $local_date
		) {
			return array();
		}

		$events = Power_Schedule_Manager_Cache::remember(
			'seo_single_events',
			array(
				'post_id'    => $post_id,
				'unit_code'  => $unit_code,
				'local_date' => $local_date,
			),
			static fn (): array =>
				Power_Schedule_Manager_Repository::query(
					$local_date,
					$local_date,
					$unit_code,
					array(
						Power_Schedule_Manager_Status::SCHEDULED,
						Power_Schedule_Manager_Status::ONGOING,
						Power_Schedule_Manager_Status::COMPLETED,
						Power_Schedule_Manager_Status::CANCELLED,
					),
					100,
					0
				),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);

		return array_values(
			array_filter(
				$events,
				static fn ( array $event ): bool =>
					(int) $event['post_id'] === $post_id
			)
		);
	}

	/**
	 * Detect Rank Math.
	 *
	 * @return bool
	 */
	private static function rank_math_is_active(): bool {
		return defined( 'RANK_MATH_VERSION' )
			|| class_exists( 'RankMath', false )
			|| function_exists( 'rank_math' );
	}

	/**
	 * Detect other common SEO plugins.
	 *
	 * @return bool
	 */
	private static function another_seo_plugin_is_active(): bool {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| class_exists( 'WPSEO_Options', false )
			|| class_exists( 'AIOSEO\\Plugin\\AIOSEO', false );
	}

	/**
	 * Determine whether current request belongs to the plugin.
	 *
	 * @return bool
	 */
	private static function is_plugin_request(): bool {
		return is_singular(
			Power_Schedule_Manager_Post_Type::POST_TYPE
		)
			|| is_post_type_archive(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			)
			|| is_tax(
				Power_Schedule_Manager_Taxonomy::TAXONOMY
			);
	}

	/**
	 * Sanitize and limit description.
	 *
	 * @param string $description Description.
	 *
	 * @return string
	 */
	private static function limit_description(
		string $description
	): string {
		$description = trim(
			preg_replace(
				'/\s+/u',
				' ',
				wp_strip_all_tags( $description )
			) ?? ''
		);

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr(
				$description,
				0,
				self::DESCRIPTION_LENGTH
			);
		}

		return substr(
			$description,
			0,
			self::DESCRIPTION_LENGTH
		);
	}
}
