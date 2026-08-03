<?php
/**
 * Editorial market-price tables and reusable shortcodes.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores reference prices without scraping third-party pages.
 */
final class Power_Schedule_Manager_Market_Prices {

	/**
	 * Datasets already rendered when a collection explicitly enables
	 * de-duplication. Standalone shortcodes remain fully independent.
	 *
	 * @var array<string,bool>
	 */
	private array $rendered_markets = array();

	/**
	 * Public data sources collected while rendering the current request.
	 *
	 * @var array<string,string>
	 */
	private array $rendered_sources = array();

	private const string COFFEE_MENU_SLUG =
		'power-schedule-manager-coffee-prices';

	private const string GOLD_MENU_SLUG =
		'power-schedule-manager-gold-prices';

	private const string SAVE_ACTION = 'psm_save_market_price';

	private const string DELETE_ACTION = 'psm_delete_market_price';

	private const string GOLD_REFRESH_ACTION =
		'psm_refresh_world_gold';

	private const string GOLD_SETTINGS_ACTION =
		'psm_save_world_gold_settings';

	public const string GOLD_REFRESH_HOOK =
		'power_schedule_manager_refresh_world_gold';

	private const string SETTINGS_OPTION =
		'power_schedule_manager_market_settings';

	private const string GIAVANG_NOW_URL =
		'https://giavang.now/api/prices';

	private const string WIFEED_GOLD_URL =
		'https://wifeed.vn/api/du-lieu-vimo/hang-hoa/v2/gia-vang-trong-nuoc-quoc-te';

	private const string WIFEED_FX_URL =
		'https://wifeed.vn/api/du-lieu-vimo/ty-gia';

	private const string GOLD_API_URL =
		'https://api.gold-api.com/price/XAU';

	private const string VNAPPMOB_API_BASE =
		'https://api.vnappmob.com/api/v2';

	private const string COMMODITIES_API_URL =
		'https://commodities-api.com/api/latest';

	/**
	 * Supported public datasets.
	 *
	 * @var array<string, array{commodity:string,title:string,description:string,unit:string}>
	 */
	private const array MARKETS = array(
		'coffee_lam_dong' => array(
			'commodity'   => 'coffee',
			'title'       => 'Giá cà phê Lâm Đồng hôm nay',
			'description' => 'Bảng giá cà phê tham khảo tại Lâm Đồng, kèm ngày cập nhật và nguồn dữ liệu.',
			'unit'        => 'VND/kg',
		),
		'coffee_domestic' => array(
			'commodity'   => 'coffee',
			'title'       => 'Giá cà phê trong nước hôm nay',
			'description' => 'So sánh giá cà phê tham khảo giữa các khu vực trong nước.',
			'unit'        => 'VND/kg',
		),
		'coffee_futures' => array(
			'commodity'   => 'coffee',
			'title'       => 'Giá cà phê thế giới hôm nay',
			'description' => 'Theo dõi mức giá mới nhất, thay đổi trong ngày và lượng giao dịch của các loại cà phê phổ biến.',
			'unit'        => 'USD',
		),
		'pepper_domestic' => array(
			'commodity'   => 'pepper',
			'title'       => 'Giá hồ tiêu trong nước hôm nay',
			'description' => 'So sánh giá hồ tiêu tham khảo tại các vùng sản xuất, theo ngày cập nhật.',
			'unit'        => 'VND/kg',
		),
		'usd_vnd' => array(
			'commodity'   => 'fx',
			'title'       => 'Tỷ giá USD/VND hôm nay',
			'description' => 'Tỷ giá USD/VND dùng để đối chiếu các thị trường niêm yết bằng đô la Mỹ.',
			'unit'        => 'VND/USD',
		),
		'exchange_rates' => array(
			'commodity'   => 'fx',
			'title'       => 'Tỷ giá ngoại tệ hôm nay',
			'description' => 'Tỷ giá mua và bán mới nhất tại ngân hàng đã chọn.',
			'unit'        => 'VND',
		),
		'gold_daily' => array(
			'commodity'   => 'gold',
			'title'       => 'Giá vàng trong nước hôm nay',
			'description' => 'So sánh giá mua, giá bán và mức chênh lệch của SJC, DOJI, Phú Quý, BTMC và các hệ thống được biên tập từ nguồn công bố.',
			'unit'        => 'VND/lượng',
		),
		'gold_world' => array(
			'commodity'   => 'gold',
			'title'       => 'Giá vàng thế giới hôm nay',
			'description' => 'Giá vàng quốc tế mới nhất, kèm thời điểm cập nhật và mức thay đổi so với trước.',
			'unit'        => 'USD/oz',
		),
	);

	/**
	 * Attach WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 72 );
		add_action(
			'admin_post_' . self::SAVE_ACTION,
			array( $this, 'save' )
		);
		add_action(
			'admin_post_' . self::DELETE_ACTION,
			array( $this, 'delete' )
		);
		add_action(
			'admin_post_' . self::GOLD_REFRESH_ACTION,
			array( $this, 'refresh_world_gold_now' )
		);
		add_action(
			'admin_post_' . self::GOLD_SETTINGS_ACTION,
			array( $this, 'save_world_gold_settings' )
		);
		add_action(
			self::GOLD_REFRESH_HOOK,
			array( $this, 'refresh_world_gold' )
		);
		add_filter(
			'cron_schedules',
			array( $this, 'register_cron_interval' )
		);
		add_action(
			'admin_init',
			array( $this, 'ensure_world_gold_schedule' ),
			40
		);
		add_action(
			'admin_init',
			array( $this, 'repair_legacy_gold_separators' ),
			45
		);
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_admin_assets' )
		);
		add_action(
			'power_schedule_manager_render_data_source_settings',
			array( $this, 'render_data_source_settings' ),
			20
		);

		$shortcodes = array(
			'power_schedule_market_prices'   => '',
			'power_schedule_coffee_lam_dong' => 'coffee_lam_dong',
			'power_schedule_coffee_domestic' => 'coffee_domestic',
			'power_schedule_coffee_futures'  => 'coffee_futures',
			'power_schedule_coffee_live'     => 'coffee_futures',
			'power_schedule_pepper_prices'   => 'pepper_domestic',
			'power_schedule_usd_vnd'         => 'usd_vnd',
		);

		foreach ( $shortcodes as $tag => $market ) {
			add_shortcode(
				$tag,
				function ( array|string $attributes = array() ) use (
					$market
				): string {
					return $this->render_shortcode(
						$attributes,
						$market
					);
				}
			);
		}

		$filtered_shortcodes = array(
			'power_schedule_coffee_dak_lak' => array(
				'market' => 'coffee_domestic',
				'series' => 'Đắk Lắk',
				'title'  => 'Giá cà phê Đắk Lắk hôm nay',
			),
			'power_schedule_coffee_gia_lai' => array(
				'market' => 'coffee_domestic',
				'series' => 'Gia Lai',
				'title'  => 'Giá cà phê Gia Lai hôm nay',
			),
			'power_schedule_coffee_dak_nong' => array(
				'market' => 'coffee_domestic',
				'series' => 'Đắk Nông',
				'title'  => 'Giá cà phê Đắk Nông hôm nay',
			),
			'power_schedule_coffee_robusta' => array(
				'market' => 'coffee_futures',
				'series' => 'Robusta quốc tế',
				'title'  => 'Giá cà phê Robusta quốc tế',
			),
			'power_schedule_coffee_arabica' => array(
				'market' => 'coffee_futures',
				'series' => 'Arabica quốc tế',
				'title'  => 'Giá cà phê Arabica quốc tế',
			),
			'power_schedule_gold_api' => array(
				'market'   => 'gold_world',
				'provider' => 'gold_api',
				'title'    => 'Giá vàng thế giới hôm nay',
			),
			'power_schedule_gold_vnappmob_sjc' => array(
				'market'   => 'gold_daily',
				'provider' => 'vnappmob_sjc',
				'title'    => 'Giá vàng SJC hôm nay',
			),
			'power_schedule_gold_vnappmob_doji' => array(
				'market'   => 'gold_daily',
				'provider' => 'vnappmob_doji',
				'title'    => 'Giá vàng DOJI hôm nay',
			),
			'power_schedule_gold_vnappmob_pnj' => array(
				'market'   => 'gold_daily',
				'provider' => 'vnappmob_pnj',
				'title'    => 'Giá vàng PNJ hôm nay',
			),
			'power_schedule_exchange_rates_vcb' => array(
				'market'   => 'exchange_rates',
				'provider' => 'vnappmob_fx_vcb',
			),
			'power_schedule_exchange_rates_ctg' => array(
				'market'   => 'exchange_rates',
				'provider' => 'vnappmob_fx_ctg',
			),
			'power_schedule_exchange_rates_tcb' => array(
				'market'   => 'exchange_rates',
				'provider' => 'vnappmob_fx_tcb',
			),
			'power_schedule_exchange_rates_bidv' => array(
				'market'   => 'exchange_rates',
				'provider' => 'vnappmob_fx_bid',
			),
			'power_schedule_exchange_rates_stb' => array(
				'market'   => 'exchange_rates',
				'provider' => 'vnappmob_fx_stb',
			),
			'power_schedule_exchange_rates_sbv' => array(
				'market'   => 'exchange_rates',
				'provider' => 'vnappmob_fx_sbv',
			),
		);

		foreach ( $filtered_shortcodes as $tag => $filter ) {
			add_shortcode(
				$tag,
				function ( array|string $attributes = array() ) use (
					$filter
				): string {
					$attributes = is_array( $attributes )
						? $attributes
						: array();
					if ( isset( $filter['series'] ) ) {
						$attributes['series'] = $filter['series'];
					}
					if ( isset( $filter['provider'] ) ) {
						$attributes['provider'] = $filter['provider'];
					}
					if (
						isset( $filter['title'] )
						&& empty( $attributes['title'] )
					) {
						$attributes['title'] = $filter['title'];
					}

					return $this->render_shortcode(
						$attributes,
						$filter['market']
					);
				}
			);
		}

		add_shortcode(
			'power_schedule_exchange_rates',
			function ( array|string $attributes = array() ): string {
				$attributes = is_array( $attributes )
					? $attributes
					: array();
				$attributes['provider'] = 'wifeed';

				return $this->render_shortcode(
					$attributes,
					'usd_vnd'
				);
			}
		);

		add_shortcode(
			'power_schedule_gold_world',
			function ( array|string $attributes = array() ): string {
				$attributes = is_array( $attributes )
					? $attributes
					: array();
				$settings = self::market_settings();
				$attributes['provider'] =
					'wifeed' === $settings['gold_provider']
						? 'wifeed'
						: 'gold_api';

				return $this->render_shortcode(
					$attributes,
					'gold_world'
				);
			}
		);

		add_shortcode(
			'power_schedule_gold_domestic',
			function ( array|string $attributes = array() ): string {
				$attributes = is_array( $attributes )
					? $attributes
					: array();
				$settings = self::market_settings();
				$attributes['provider'] =
					'wifeed' === $settings['gold_provider']
						? 'wifeed'
						: 'vang_today';

				return $this->render_shortcode(
					$attributes,
					'gold_daily'
				);
			}
		);

		foreach (
			array(
				'power_schedule_gold_prices',
				'power_schedule_gold_overview',
				'power_schedule_gold_comparison',
			)
			as $tag
		) {
			add_shortcode(
				$tag,
				fn ( array|string $attributes = array() ): string =>
					$this->render_gold_overview( $attributes )
			);
		}

		add_shortcode(
			'power_schedule_market_iframe',
			array( $this, 'render_market_iframe' )
		);
		add_shortcode(
			'power_schedule_gold_iframe',
			array( $this, 'render_gold_iframe' )
		);

		foreach (
			array(
				'power_schedule_coffee_prices',
				'power_schedule_coffee_overview',
			)
			as $tag
		) {
			add_shortcode(
				$tag,
				fn ( array|string $attributes = array() ): string =>
					$this->render_coffee_overview( $attributes )
			);
		}
	}

	/**
	 * Add the editorial screen.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Power_Schedule_Manager_Admin::MENU_SLUG,
			__( 'Quản lý giá nông sản và tỷ giá', 'power-schedule-manager' ),
			__( 'Giá thị trường', 'power-schedule-manager' ),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::COFFEE_MENU_SLUG,
			array( $this, 'render_coffee_page' )
		);

		add_submenu_page(
			Power_Schedule_Manager_Admin::MENU_SLUG,
			__( 'Quản lý giá vàng', 'power-schedule-manager' ),
			__( 'Giá vàng', 'power-schedule-manager' ),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::GOLD_MENU_SLUG,
			array( $this, 'render_gold_page' )
		);
	}

	/**
	 * Load the existing admin design system only on this screen.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if (
			false === str_contains(
				$hook_suffix,
				self::COFFEE_MENU_SLUG
			)
			&& false === str_contains(
				$hook_suffix,
				self::GOLD_MENU_SLUG
			)
		) {
			return;
		}

		$path = POWER_SCHEDULE_MANAGER_PATH . 'admin/assets/admin.css';
		wp_enqueue_style(
			'power-schedule-manager-admin',
			POWER_SCHEDULE_MANAGER_URL . 'admin/assets/admin.css',
			array(),
			is_file( $path )
				? (string) filemtime( $path )
				: POWER_SCHEDULE_MANAGER_VERSION
		);

		$script_path = POWER_SCHEDULE_MANAGER_PATH
			. 'admin/assets/market-prices.js';
		wp_enqueue_script(
			'power-schedule-manager-market-prices',
			POWER_SCHEDULE_MANAGER_URL
				. 'admin/assets/market-prices.js',
			array(),
			is_file( $script_path )
				? (string) filemtime( $script_path )
				: POWER_SCHEDULE_MANAGER_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Render an accessible, server-side price table.
	 */
	public function render_shortcode(
		array|string $attributes,
		string $forced_market = ''
	): string {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::MARKET_PRICES
			)
		) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'market'       => $forced_market ?: 'coffee_lam_dong',
				'title'        => '',
				'description'  => '',
				'limit'        => '20',
				'show_content' => 'no',
				'history'      => 'auto',
				'series'       => '',
				'provider'     => '',
				'deduplicate'  => 'no',
				'show_footer'  => 'no',
				'show_table'   => 'yes',
				'interactive_chart' => 'no',
				'compact_header' => 'no',
				'show_exchange_reference' => 'no',
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$market = $forced_market ?: sanitize_key(
			(string) $attributes['market']
		);

		if ( ! isset( self::MARKETS[ $market ] ) ) {
			return '';
		}

		$limit = max( 1, min( 90, absint( $attributes['limit'] ) ) );
		$history = 'yes' === strtolower( (string) $attributes['history'] )
			|| (
				'auto' === strtolower( (string) $attributes['history'] )
				&& 'coffee_lam_dong' === $market
			);
		$series = self::limit_text(
			sanitize_text_field( (string) $attributes['series'] ),
			191
		);
		$provider = self::limit_text(
			sanitize_key( (string) $attributes['provider'] ),
			64
		);
		$render_key = implode( '|', array( $market, $series, $provider ) );
		$deduplicate = 'no' !== strtolower(
			(string) $attributes['deduplicate']
		);
		if (
			$deduplicate
			&& ! empty( $this->rendered_markets[ $render_key ] )
		) {
			return '';
		}
		$rows = $history
			? self::history_rows( $market, $limit, $series, $provider )
			: self::latest_rows( $market, $limit, $series, $provider );

		foreach ( $rows as $row ) {
			$source_url = isset( $row['source_url'] )
				? esc_url_raw( (string) $row['source_url'] )
				: '';
			if ( '' === $source_url ) {
				continue;
			}
			$this->rendered_sources[ $source_url ] = self::limit_text(
				sanitize_text_field(
					(string) ( $row['source_name'] ?? '' )
				),
				191
			) ?: __( 'Nguồn dữ liệu', 'power-schedule-manager' );
		}

		if ( $deduplicate ) {
			$this->rendered_markets[ $render_key ] = true;
		}

		Power_Schedule_Manager_Assets::enqueue_public_assets( 'market' );

		return Power_Schedule_Manager_Template_Loader::render_part(
			'market-prices',
			array(
				'market'       => $market,
				'config'       => self::MARKETS[ $market ],
				'rows'         => $rows,
				'title'        => sanitize_text_field(
					(string) $attributes['title']
				),
				'description'  => sanitize_text_field(
					(string) $attributes['description']
				),
				'show_content' => 'yes' === strtolower(
					(string) $attributes['show_content']
				),
				'show_footer'  => 'yes' === strtolower(
					(string) $attributes['show_footer']
				),
				'show_table'   => 'yes' === strtolower(
					(string) $attributes['show_table']
				),
				'interactive_chart' => 'yes' === strtolower(
					(string) $attributes['interactive_chart']
				),
				'compact_header' => 'yes' === strtolower(
					(string) $attributes['compact_header']
				),
				'exchange_reference' => 'yes' === strtolower(
					(string) $attributes['show_exchange_reference']
				)
					? $this->render_exchange_reference()
					: '',
				'history'      => $history,
				'chart_rows'   => match ( $market ) {
					'gold_daily' => self::gold_chart_rows(
						30,
						$series,
						$provider
					),
					'gold_world' => self::history_rows(
						$market,
						30,
						'XAU/USD',
						$provider
					),
					default => array(),
				},
			)
		);
	}

	/**
	 * Render an administrator-supplied HTTPS page in a responsive frame.
	 */
	public function render_market_iframe(
		array|string $attributes = array()
	): string {
		$attributes = shortcode_atts(
			array(
				'src'    => '',
				'title'  => __( 'Dữ liệu thị trường', 'power-schedule-manager' ),
				'height' => '560',
			),
			is_array( $attributes ) ? $attributes : array(),
			'power_schedule_market_iframe'
		);
		$source = esc_url_raw( (string) $attributes['src'], array( 'https' ) );
		if ( '' === $source || false === wp_http_validate_url( $source ) ) {
			return '<div class="psm-market-frame psm-market-frame--empty">'
				. esc_html__(
					'Chưa cấu hình nguồn biểu đồ.',
					'power-schedule-manager'
				)
				. '</div>';
		}

		Power_Schedule_Manager_Assets::enqueue_public_assets( 'market' );
		$height = min( 900, max( 320, absint( $attributes['height'] ) ) );
		$title = sanitize_text_field( (string) $attributes['title'] );

		return sprintf(
			'<figure class="psm-market-frame" style="--psm-market-frame-height:%1$dpx">'
			. '<iframe src="%2$s" title="%3$s" loading="lazy" '
			. 'referrerpolicy="strict-origin-when-cross-origin" '
			. 'sandbox="allow-scripts allow-same-origin allow-forms allow-popups" '
			. 'allowfullscreen></iframe></figure>',
			$height,
			esc_url( $source ),
			esc_attr( $title )
		);
	}

	/**
	 * Render the standalone XAU/USD TradingView frame.
	 */
	public function render_gold_iframe(
		array|string $attributes = array()
	): string {
		$attributes = shortcode_atts(
			array(
				'title'  => __( 'Biểu đồ giá vàng thế giới', 'power-schedule-manager' ),
				'height' => '560',
			),
			is_array( $attributes ) ? $attributes : array(),
			'power_schedule_gold_iframe'
		);
		$config = array(
			'symbol'              => 'FOREXCOM:XAUUSD',
			'interval'            => 'D',
			'timezone'            => POWER_SCHEDULE_MANAGER_TIMEZONE,
			'theme'               => 'light',
			'style'               => '1',
			'locale'              => 'vi_VN',
			'allow_symbol_change' => false,
			'save_image'          => false,
			'hide_volume'         => true,
		);
		$source = 'https://www.tradingview-widget.com/embed-widget/advanced-chart/?locale=vi_VN#'
			. rawurlencode(
				(string) wp_json_encode(
					$config,
					JSON_UNESCAPED_SLASHES
				)
			);
		$attributes['src'] = $source;

		return $this->render_market_iframe( $attributes );
	}

	/**
	 * Render the canonical coffee page while keeping every individual
	 * shortcode available to editors.
	 */
	private function render_coffee_overview(
		array|string $attributes = array()
	): string {
		$attributes = shortcode_atts(
			array(
				'title'        => __( 'Giá cà phê hôm nay', 'power-schedule-manager' ),
				'description'  => __( 'Giá Lâm Đồng, so sánh trong nước và thị trường quốc tế trong một bố cục thống nhất.', 'power-schedule-manager' ),
				'limit'        => '20',
				'show_content' => 'yes',
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$base_id = wp_unique_id( 'psm-coffee-overview-' );
		$sources_before = $this->rendered_sources;
		$coffee_latest = self::latest_rows( 'coffee_domestic', 1 );
		$coffee_date = (string) (
			$coffee_latest[0]['price_date'] ?? ''
		);
		$coffee_updated = '' !== $coffee_date
			? '<time class="psm-market-overview__updated" datetime="'
				. esc_attr( $coffee_date ) . '">'
				. esc_html(
					sprintf(
						/* translators: %s: Latest coffee price date. */
						__( 'Dữ liệu gần nhất %s', 'power-schedule-manager' ),
						mysql2date( 'd/m/Y', $coffee_date )
					)
				)
				. '</time>'
			: '';
		$sections = array(
			'trong-nuoc' => $this->render_shortcode(
				array(
					'title'          => __( 'Giá cà phê trong nước', 'power-schedule-manager' ),
					'limit'          => $attributes['limit'],
					'history'        => 'no',
					'show_table'     => 'yes',
					'compact_header' => 'yes',
					'deduplicate'    => 'yes',
					'show_exchange_reference' => 'yes',
				),
				'coffee_domestic'
			),
			'the-gioi' => $this->render_shortcode(
				array(
					'title'          => __( 'Giá cà phê thế giới', 'power-schedule-manager' ),
					'limit'          => $attributes['limit'],
					'history'        => 'no',
					'show_table'     => 'yes',
					'compact_header' => 'yes',
					'deduplicate'    => 'yes',
				),
				'coffee_futures'
			),
			'tham-chieu' => $this->render_coffee_reference_snapshot(),
		);

		return $this->render_overview(
			$base_id,
			(string) $attributes['title'],
			(string) $attributes['description'],
			array(
				'trong-nuoc' => __( 'Trong nước', 'power-schedule-manager' ),
				'the-gioi'   => __( 'Thế giới', 'power-schedule-manager' ),
				'tham-chieu' => __( 'Chỉ số liên quan', 'power-schedule-manager' ),
			),
			$sections,
			'no' !== strtolower( (string) $attributes['show_content'] )
				? $this->render_collection_guide(
					array(
						'coffee_lam_dong',
						'coffee_domestic',
						'pepper_domestic',
						'coffee_futures',
					),
					array_diff_key(
						$this->rendered_sources,
						$sources_before
					)
				)
				: '',
			'coffee',
			$coffee_updated
		);
	}

	/**
	 * Render supporting coffee metrics without repeating full data tables.
	 */
	private function render_coffee_reference_snapshot(): string {
		$domestic_rows = self::latest_rows( 'coffee_domestic', 30 );
		$pepper_rows = self::latest_rows( 'pepper_domestic', 1 );
		$lam_dong = array();
		$domestic_values = array();
		foreach ( $domestic_rows as $row ) {
			$value = $row['price'] ?? $row['buy_price'] ?? null;
			if ( is_numeric( $value ) && (float) $value > 0 ) {
				$domestic_values[] = (float) $value;
			}
			$label = strtolower(
				remove_accents( (string) ( $row['label'] ?? '' ) )
			);
			if ( str_contains( $label, 'lam dong' ) ) {
				$lam_dong = $row;
				break;
			}
		}
		if ( array() === $lam_dong ) {
			$lam_dong = $domestic_rows[0] ?? array();
		}
		$pepper = $pepper_rows[0] ?? array();
		$spread = array();
		if ( array() !== $domestic_values ) {
			$spread = array(
				'price'      => max( $domestic_values )
					- min( $domestic_values ),
				'unit'       => 'VND/kg',
				'price_date' => (string) (
					$domestic_rows[0]['price_date'] ?? ''
				),
			);
		}
		foreach ( array( $pepper ) as $row ) {
			$url = esc_url_raw( (string) ( $row['source_url'] ?? '' ) );
			if ( '' !== $url ) {
				$this->rendered_sources[ $url ] = sanitize_text_field(
					(string) ( $row['source_name'] ?? __( 'Nguồn dữ liệu', 'power-schedule-manager' ) )
				);
			}
		}

		$card = static function (
			string $eyebrow,
			string $title,
			array $row,
			string $fallback_unit
		): string {
			$value = $row['price']
				?? $row['buy_price']
				?? $row['sell_price']
				?? null;
			$change = $row['change_value'] ?? null;
			$unit = trim( (string) ( $row['unit'] ?? $fallback_unit ) );
			$value_text = is_numeric( $value )
				? number_format_i18n(
					(float) $value,
					fmod( (float) $value, 1.0 ) === 0.0 ? 0 : 2
				)
				: '—';
			$change_class = is_numeric( $change )
				? ( (float) $change < 0 ? ' is-down' : ( (float) $change > 0 ? ' is-up' : '' ) )
				: '';
			$change_text = is_numeric( $change )
				? ( (float) $change > 0 ? '+' : '' )
					. number_format_i18n( (float) $change, 0 )
				: __( 'Chưa đủ dữ liệu so sánh', 'power-schedule-manager' );
			$date = (string) ( $row['price_date'] ?? '' );

			return '<article class="psm-coffee-context__card">'
				. '<p>' . esc_html( $eyebrow ) . '</p><h3>'
				. esc_html( $title ) . '</h3><div><strong>'
				. esc_html( $value_text ) . '</strong><span>'
				. esc_html( $unit ) . '</span></div><footer><span class="'
				. esc_attr( trim( $change_class ) ) . '">'
				. esc_html( $change_text ) . '</span>'
				. ( '' !== $date
					? '<time datetime="' . esc_attr( $date ) . '">'
						. esc_html( mysql2date( 'd/m/Y', $date ) )
						. '</time>'
					: '' )
				. '</footer></article>';
		};

		return '<section class="psm-coffee-context" aria-label="'
			. esc_attr__( 'Thông tin tham chiếu cho thị trường cà phê', 'power-schedule-manager' )
			. '"><header><div><p>'
			. esc_html__( 'Thông tin bổ trợ', 'power-schedule-manager' )
			. '</p><h2>'
			. esc_html__( 'Chỉ số liên quan đến thị trường', 'power-schedule-manager' )
			. '</h2></div><span>'
			. esc_html__( 'Dùng để tham khảo, không phải giá giao dịch cam kết.', 'power-schedule-manager' )
			. '</span></header><div class="psm-coffee-context__grid">'
			. $card(
				__( 'Thị trường trọng tâm', 'power-schedule-manager' ),
				__( 'Cà phê Lâm Đồng', 'power-schedule-manager' ),
				$lam_dong,
				'VND/kg'
			)
			. $card(
				__( 'Nông sản liên quan', 'power-schedule-manager' ),
				__( 'Hồ tiêu trong nước', 'power-schedule-manager' ),
				$pepper,
				'VND/kg'
			)
			. $card(
				__( 'So sánh trong nước', 'power-schedule-manager' ),
				__( 'Biên độ giữa các khu vực', 'power-schedule-manager' ),
				$spread,
				'VND/kg'
			)
			. '</div></section>';
	}

	/**
	 * Render one selected domestic provider and one world-gold source.
	 */
	private function render_gold_overview(
		array|string $attributes = array()
	): string {
		$attributes = shortcode_atts(
			array(
				'title'             => __( 'Giá vàng hôm nay', 'power-schedule-manager' ),
				'description'       => __( 'Giá trong nước và giá vàng thế giới được tách rõ đơn vị và thời điểm cập nhật.', 'power-schedule-manager' ),
				'limit'             => '30',
				'interactive_chart' => 'yes',
				'show_content'      => 'yes',
			),
			is_array( $attributes ) ? $attributes : array()
		);
		$settings = self::market_settings();
		$selected = (string) $settings['gold_provider'];
		$domestic_provider = 'wifeed' === $selected
			? 'wifeed'
			: 'vang_today';
		$world_provider = 'wifeed' === $selected
			? 'wifeed'
			: 'gold_api';
		$base_id = wp_unique_id( 'psm-gold-overview-' );
		$sources_before = $this->rendered_sources;
		$sections = array(
			'so-sanh' => $this->render_gold_snapshot(
				$domestic_provider,
				$world_provider
			),
		);

		$sections['trong-nuoc'] = $this->render_shortcode(
			array(
				'title'          => __( 'Giá vàng trong nước', 'power-schedule-manager' ),
				'provider'       => $domestic_provider,
				'limit'          => $attributes['limit'],
				'history'        => 'no',
				'show_table'     => 'yes',
				'compact_header' => 'yes',
				'deduplicate'    => 'yes',
				'show_exchange_reference' => 'yes',
			),
			'gold_daily'
		);
		$sections['the-gioi'] = $this->render_shortcode(
			array(
				'title'             => __( 'Giá vàng thế giới', 'power-schedule-manager' ),
				'provider'          => $world_provider,
				'limit'             => $attributes['limit'],
				'history'           => 'no',
				'show_table'        => 'no',
				'interactive_chart' => $attributes['interactive_chart'],
				'compact_header'    => 'yes',
				'deduplicate'       => 'yes',
			),
			'gold_world'
		);

		$navigation = array();
		$navigation['so-sanh'] = __(
			'Tổng quan',
			'power-schedule-manager'
		);
		$navigation['trong-nuoc'] = __(
			'Trong nước',
			'power-schedule-manager'
		);
		$navigation['the-gioi'] = __( 'Thế giới', 'power-schedule-manager' );

		return $this->render_overview(
			$base_id,
			(string) $attributes['title'],
			(string) $attributes['description'],
			$navigation,
			$sections,
			'no' !== strtolower( (string) $attributes['show_content'] )
				? $this->render_collection_guide(
					array( 'gold_daily', 'gold_world' ),
					array_diff_key(
						$this->rendered_sources,
						$sources_before
					)
				)
				: '',
			'gold',
			''
		);
	}

	/**
	 * Give total shortcodes a consistent page hierarchy and anchor navigation.
	 *
	 * @param array<string,string> $navigation Anchor labels.
	 * @param array<string,string> $sections   Rendered sections.
	 */
	private function render_overview(
		string $base_id,
		string $title,
		string $description,
		array $navigation,
		array $sections,
		string $footer,
		string $kind,
		string $header_extra = ''
	): string {
		unset( $title, $description, $navigation, $header_extra );
		$section_title = 'gold' === $kind
			? __( 'Bảng giá vàng chi tiết', 'power-schedule-manager' )
			: __( 'Bảng giá cà phê chi tiết', 'power-schedule-manager' );
		$anchor_id = 'gold' === $kind ? 'bang-gia' : 'gia-lam-dong';
		$html = '<section id="' . esc_attr( $anchor_id )
			. '" class="psm-market-overview psm-market-overview--'
			. esc_attr( $kind ) . '" aria-labelledby="' . esc_attr( $base_id )
			. '-data-title"><div class="psm-market-overview__body">'
			. '<header class="psm-data-section-header"><h2 id="' . esc_attr( $base_id ) . '-data-title">'
			. esc_html( $section_title ) . '</h2></header><div class="psm-market-overview__sections">';
		foreach ( $sections as $anchor => $section ) {
			if ( '' === $section ) {
				continue;
			}
			$html .= '<div id="' . esc_attr( $base_id . '-' . $anchor )
				. '" class="psm-market-overview__section psm-market-overview__section--'
				. esc_attr( sanitize_html_class( $anchor ) ) . '">' . $section
				. '</div>';
		}

		return $html . '</div>' . $footer . '</div></section>';
	}

	/**
	 * Return a compact summary from the latest stored market row.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function hero_summary( string $kind ): array {
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::MARKET_PRICES
			)
		) {
			return array();
		}

		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$commodity = 'gold' === sanitize_key( $kind ) ? 'gold' : 'coffee';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT price,buy_price,sell_price,change_value,change_percent,unit,price_date,observed_at_utc FROM %i WHERE is_public=1 AND commodity=%s ORDER BY price_date DESC,observed_at_utc DESC,id DESC LIMIT 1',
				$table,
				$commodity
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return array();
		}

		$observed = sanitize_text_field( (string) ( $row['observed_at_utc'] ?? '' ) );
		$display_time = '' !== $observed
			? get_date_from_gmt( $observed, 'H:i' )
			: '';
		$display_date = '' !== $observed
			? get_date_from_gmt( $observed, 'd/m/Y' )
			: sanitize_text_field( (string) ( $row['price_date'] ?? '' ) );
		$price = $row['price'] ?? $row['sell_price'] ?? $row['buy_price'] ?? null;
		$summary = array();
		if ( '' !== $display_time ) {
			$summary[] = array(
				'label'  => __( 'Cập nhật', 'power-schedule-manager' ),
				'value'  => $display_time,
				'detail' => $display_date,
				'tone'   => 'live',
			);
		}
		if ( is_numeric( $price ) ) {
			$summary[] = array(
				'label'  => __( 'Mức gần nhất', 'power-schedule-manager' ),
				'value'  => number_format_i18n( (float) $price, 0 ),
				'detail' => sanitize_text_field( (string) ( $row['unit'] ?? '' ) ),
				'tone'   => '',
			);
		}
		$change = is_numeric( $row['change_percent'] ?? null )
			? (float) $row['change_percent']
			: null;
		if ( null !== $change ) {
			$summary[] = array(
				'label'  => __( 'Biến động', 'power-schedule-manager' ),
				'value'  => ( $change >= 0 ? '▲ +' : '▼ ' )
					. number_format_i18n( $change, 2 ) . '%',
				'detail' => __( 'So với mốc trước', 'power-schedule-manager' ),
				'tone'   => $change >= 0 ? 'up' : 'down',
			);
		}

		return $summary;
	}

	/**
	 * Render a page-ready collection without coupling the data sets together.
	 */
	private function render_collection(
		array $markets,
		array|string $attributes
	): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$output = '';
		$rendered_count = 0;
		$sources_before = $this->rendered_sources;

		foreach ( $markets as $market ) {
			$section = $this->render_shortcode(
				array(
					'limit'        => $attributes['limit'] ?? '20',
					'show_content' => 'no',
					'show_footer'  => 'no',
					'show_table'   => 'gold_world' === $market
						? 'no'
						: 'yes',
					'interactive_chart' => 'gold_world' === $market
						? ( $attributes['interactive_chart'] ?? 'yes' )
						: 'no',
					'compact_header' => 'yes',
					'history'      => 'coffee_lam_dong' === $market
						? 'yes'
						: 'no',
					'deduplicate'  => $attributes['deduplicate'] ?? 'yes',
				),
				$market
			);
			if ( '' !== $section ) {
				$output .= $section;
				++$rendered_count;
			}
		}

		if ( '' === $output ) {
			return '';
		}

		$output .= $this->render_exchange_reference();

		if (
			$rendered_count > 0
			&& 'no' !== strtolower(
				(string) ( $attributes['show_content'] ?? 'yes' )
			)
		) {
			$output .= $this->render_collection_guide(
				$markets,
				array_diff_key(
					$this->rendered_sources,
					$sources_before
				)
			);
		}

		return '<div class="psm-market-collection">' . $output . '</div>';
	}

	/**
	 * Save or update one dated row.
	 */
	public function save(): void {
		$this->authorize( self::SAVE_ACTION );
		global $wpdb;
		$editing_id = isset( $_POST['price_id'] )
			? absint( $_POST['price_id'] )
			: 0;

		$market = isset( $_POST['market_code'] )
			? sanitize_key( wp_unslash( (string) $_POST['market_code'] ) )
			: '';
		$label = isset( $_POST['label'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['label'] ) )
			: '';
		$date = isset( $_POST['price_date'] )
			? sanitize_text_field(
				wp_unslash( (string) $_POST['price_date'] )
			)
			: '';

		if (
			! isset( self::MARKETS[ $market ] )
			|| '' === $label
			|| ! self::valid_date( $date )
		) {
			$this->redirect(
				'invalid',
				self::page_kind( $market )
			);
		}

		$config = self::MARKETS[ $market ];
		$now = current_time( 'mysql', true );
		$price = self::decimal_input( 'price', $market );
		$change = self::decimal_input( 'change_value', $market );
		$change_percent = self::decimal_input(
			'change_percent',
			'percentage'
		);
		$buy_price = self::decimal_input( 'buy_price', $market );
		$sell_price = self::decimal_input( 'sell_price', $market );
		$buy_change = self::decimal_input( 'buy_change', $market );
		$sell_change = self::decimal_input( 'sell_change', $market );
		$high_price = self::decimal_input( 'high_price', $market );
		$low_price = self::decimal_input( 'low_price', $market );
		$open_price = self::decimal_input( 'open_price', $market );
		$previous_close = self::decimal_input(
			'previous_close',
			$market
		);
		$contract_code = self::text_input( 'contract_code', '', 32 );
		$volume = self::integer_input( 'volume' );
		$open_interest = self::integer_input( 'open_interest' );

		if (
			'' === $price
			&& '' === $buy_price
			&& '' === $sell_price
		) {
			$this->redirect( 'invalid', self::page_kind( $market ) );
		}

		/*
		 * Domestic agricultural tables have one canonical purchase price.
		 * Editors may naturally enter it in either "Giá" or "Giá mua".
		 */
		if (
			in_array(
				$market,
				array( 'coffee_domestic', 'pepper_domestic' ),
				true
			)
			&& '' === $price
			&& '' !== $buy_price
		) {
			$price = $buy_price;
		}

		if (
			'gold_daily' === $market
			&& (
				( '' !== $buy_price && (float) $buy_price < 10000 )
				|| ( '' !== $sell_price && (float) $sell_price < 10000 )
				|| (
					'' !== $buy_price
					&& '' !== $sell_price
					&& (
						(float) $sell_price < (float) $buy_price * 0.5
						|| (float) $sell_price > (float) $buy_price * 2
					)
				)
			)
		) {
			$this->redirect( 'invalid', self::page_kind( $market ) );
		}

		if ( '' === $change && '' !== $price ) {
			$previous = self::previous_metric(
				$market,
				self::limit_text( $label, 191 ),
				$contract_code,
				$date
			);
			if ( null !== $previous ) {
				$change = self::decimal_string(
					(float) $price - $previous
				);
			}
		}

		if ( '' === $buy_change && '' !== $buy_price ) {
			$previous_buy = self::previous_metric(
				$market,
				self::limit_text( $label, 191 ),
				$contract_code,
				$date,
				'buy_price'
			);
			if ( null !== $previous_buy ) {
				$buy_change = self::decimal_string(
					(float) $buy_price - $previous_buy
				);
			}
		}

		if ( '' === $sell_change && '' !== $sell_price ) {
			$previous_sell = self::previous_metric(
				$market,
				self::limit_text( $label, 191 ),
				$contract_code,
				$date,
				'sell_price'
			);
			if ( null !== $previous_sell ) {
				$sell_change = self::decimal_string(
					(float) $sell_price - $previous_sell
				);
			}
		}

		$observed_at = self::observed_at_input( $date );
		$provider = self::provider_input();
		$data = array(
			'commodity'    => $config['commodity'],
			'market_code'  => $market,
			'label'        => self::limit_text( $label, 191 ),
			'contract_code' => $contract_code,
			'price_date'   => $date,
			'price'        => $price,
			'buy_price'    => $buy_price,
			'sell_price'   => $sell_price,
			'change_value' => $change,
			'change_percent' => $change_percent,
			'buy_change'   => $buy_change,
			'sell_change'  => $sell_change,
			'high_price'   => $high_price,
			'low_price'    => $low_price,
			'open_price'   => $open_price,
			'previous_close' => $previous_close,
			'volume'       => $volume,
			'open_interest' => $open_interest,
			'unit'         => self::text_input(
				'unit',
				$config['unit'],
				32
			),
			'currency'     => self::text_input( 'currency', 'VND', 8 ),
			'source_name'  => self::text_input( 'source_name', '', 191 ),
			'source_url'   => isset( $_POST['source_url'] )
				? esc_url_raw(
					wp_unslash( (string) $_POST['source_url'] )
				)
				: '',
			'provider_code' => $provider,
			'observed_at_utc' => $observed_at,
			'fetched_at_utc' => 'editorial' === $provider
				? null
				: $now,
			'is_public'    => isset( $_POST['is_public'] ) ? 1 : 0,
			'created_by'   => get_current_user_id(),
			'created_at_utc' => $now,
			'updated_at_utc' => $now,
		);
		$data['data_hash'] = hash(
			'sha256',
			wp_json_encode(
				array(
					$data['market_code'],
					$data['label'],
					$data['contract_code'],
					$data['price_date'],
					$data['price'],
					$data['buy_price'],
					$data['sell_price'],
					$data['change_value'],
					$data['change_percent'],
					$data['buy_change'],
					$data['sell_change'],
					$data['high_price'],
					$data['low_price'],
					$data['open_price'],
					$data['previous_close'],
					$data['volume'],
					$data['open_interest'],
					$data['unit'],
					$data['currency'],
					$data['provider_code'],
					$data['observed_at_utc'],
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			),
			false
		);

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$upsert_alias = Power_Schedule_Manager_Database::upsert_row_alias();
		$upsert_columns = array(
			'price',
			'buy_price',
			'sell_price',
			'change_value',
			'change_percent',
			'buy_change',
			'sell_change',
			'high_price',
			'low_price',
			'open_price',
			'previous_close',
			'volume',
			'open_interest',
			'unit',
			'currency',
			'source_name',
			'source_url',
			'provider_code',
			'observed_at_utc',
			'fetched_at_utc',
			'data_hash',
			'is_public',
			'updated_at_utc',
		);
		$upsert_assignments = array();
		foreach ( $upsert_columns as $column ) {
			$upsert_assignments[] = sprintf(
				'`%1$s`=%2$s',
				$column,
				Power_Schedule_Manager_Database::upsert_value( $column )
			);
		}
		$upsert_sql = implode( ",\n", $upsert_assignments );
		$sql = $wpdb->prepare(
			"INSERT INTO %i
			(commodity,market_code,label,contract_code,price_date,
			price,buy_price,sell_price,change_value,change_percent,
			buy_change,sell_change,high_price,low_price,open_price,
			previous_close,volume,open_interest,unit,currency,
			source_name,source_url,
			provider_code,observed_at_utc,fetched_at_utc,data_hash,
			is_public,created_by,created_at_utc,updated_at_utc)
			VALUES
			(%s,%s,%s,%s,%s,NULLIF(%s,''),NULLIF(%s,''),
			NULLIF(%s,''),NULLIF(%s,''),NULLIF(%s,''),
			NULLIF(%s,''),NULLIF(%s,''),NULLIF(%s,''),
			NULLIF(%s,''),NULLIF(%s,''),NULLIF(%s,''),
			%d,%d,%s,%s,%s,%s,%s,
			NULLIF(%s,''),NULLIF(%s,''),UNHEX(%s),%d,%d,%s,%s)
			{$upsert_alias}
			ON DUPLICATE KEY UPDATE
			{$upsert_sql}",
			$table,
			$data['commodity'],
			$data['market_code'],
			$data['label'],
			$data['contract_code'],
			$data['price_date'],
			$data['price'],
			$data['buy_price'],
			$data['sell_price'],
			$data['change_value'],
			$data['change_percent'],
			$data['buy_change'],
			$data['sell_change'],
			$data['high_price'],
			$data['low_price'],
			$data['open_price'],
			$data['previous_close'],
			$data['volume'],
			$data['open_interest'],
			$data['unit'],
			$data['currency'],
			$data['source_name'],
			$data['source_url'],
			$data['provider_code'],
			$data['observed_at_utc'] ?? '',
			$data['fetched_at_utc'] ?? '',
			$data['data_hash'],
			$data['is_public'],
			$data['created_by'],
			$data['created_at_utc'],
			$data['updated_at_utc']
		);
		$result = $wpdb->query( $sql );

		if ( false === $result ) {
			$this->redirect(
				'storage',
				self::page_kind( $market )
			);
		}

		/*
		 * The unique market/label/contract/date key remains the canonical
		 * identity. When an editor changes that identity, remove the old row
		 * only after the new/upserted row has been stored successfully.
		 */
		if ( $editing_id > 0 ) {
			$saved_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM %i
					WHERE market_code=%s AND label=%s
						AND contract_code=%s AND price_date=%s
					LIMIT 1",
					$table,
					$data['market_code'],
					$data['label'],
					$data['contract_code'],
					$data['price_date']
				)
			);
			if ( $saved_id > 0 && $saved_id !== $editing_id ) {
				$wpdb->delete(
					$table,
					array( 'id' => $editing_id ),
					array( '%d' )
				);
			}
		}

		Power_Schedule_Manager_Cache::invalidate_all();
		do_action(
			'power_schedule_manager_page_cache_purge',
			'market_prices',
			array()
		);
		$this->redirect(
			'saved',
			self::page_kind( $market )
		);
	}

	/**
	 * Delete one editorial row.
	 */
	public function delete(): void {
		$id = isset( $_POST['price_id'] ) ? absint( $_POST['price_id'] ) : 0;
		$this->authorize( self::DELETE_ACTION . '_' . $id );
		$page_kind = 'coffee';

		if ( $id > 0 ) {
			global $wpdb;
			$table = Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::MARKET_PRICES
			);
			$commodity = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT commodity FROM %i WHERE id=%d',
					$table,
					$id
				)
			);
			$page_kind = 'gold' === (string) $commodity
				? 'gold'
				: 'coffee';
			$wpdb->delete(
				$table,
				array( 'id' => $id ),
				array( '%d' )
			);
			Power_Schedule_Manager_Cache::invalidate_all();
			do_action(
				'power_schedule_manager_page_cache_purge',
				'market_prices',
				array()
			);
		}
		$this->redirect( 'deleted', $page_kind );
	}

	/**
	 * Render the coffee-only administration screen.
	 */
	public function render_coffee_page(): void {
		$this->render_admin_page( 'coffee' );
	}

	/**
	 * Render the gold-only administration screen.
	 */
	public function render_gold_page(): void {
		$this->render_admin_page( 'gold' );
	}

	/**
	 * Render gold, exchange-rate and coffee integrations centrally.
	 */
	public function render_data_source_settings(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			return;
		}

		$market_settings = self::market_settings();
		require POWER_SCHEDULE_MANAGER_PATH
			. 'admin/views/data-source-market.php';
	}

	/**
	 * Render one commodity-specific admin view.
	 */
	private function render_admin_page( string $page_kind ): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die( esc_html__( 'Bạn không có quyền.', 'power-schedule-manager' ) );
		}

		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::MARKET_PRICES
			)
		) {
			wp_die(
				esc_html__(
					'Bảng giá thị trường chưa được tạo. Hãy chạy lại migration trong Trạng thái hệ thống.',
					'power-schedule-manager'
				)
			);
		}

		$commodity = 'gold' === $page_kind ? 'gold' : 'market';
		$markets = array_filter(
			self::MARKETS,
			static fn ( array $config ): bool =>
				'gold' === $commodity
					? 'gold' === $config['commodity']
					: 'gold' !== $config['commodity']
		);
		$admin_market_filter = isset( $_GET['market_filter'] )
			&& is_scalar( $_GET['market_filter'] )
				? sanitize_key(
					wp_unslash( (string) $_GET['market_filter'] )
				)
				: '';
		if (
			'' !== $admin_market_filter
			&& ! isset( $markets[ $admin_market_filter ] )
		) {
			$admin_market_filter = '';
		}
		$admin_page = isset( $_GET['data_page'] )
			? max( 1, absint( wp_unslash( (string) $_GET['data_page'] ) ) )
			: 1;
		$admin_per_page = 15;
		$admin_total = self::admin_row_count(
			$commodity,
			$admin_market_filter
		);
		$admin_pages = max(
			1,
			(int) ceil( $admin_total / $admin_per_page )
		);
		$admin_page = min( $admin_page, $admin_pages );
		$rows = self::admin_rows(
			$commodity,
			$admin_page,
			$admin_per_page,
			$admin_market_filter
		);
		$market_settings = self::market_settings();
		$edit_id = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] )
			? absint( wp_unslash( (string) $_GET['edit'] ) )
			: 0;
		$edit_row = $edit_id > 0
			? self::admin_row( $edit_id, $commodity )
			: null;
		require POWER_SCHEDULE_MANAGER_PATH
			. 'admin/views/market-prices.php';
	}

	/**
	 * Read the latest public date for one dataset.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function latest_rows(
		string $market,
		int $limit,
		string $series = '',
		string $provider = ''
	): array {
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		if (
			in_array(
				$market,
				array( 'coffee_domestic', 'pepper_domestic' ),
				true
			)
			&& '' === $series
		) {
			$provider_sql = '';
			$values = array( $table );
			if ( '' !== $provider ) {
				$provider_value = 'vnappmob' === $provider
					? 'vnappmob_%'
					: $provider;
				$operator = 'vnappmob' === $provider ? 'LIKE' : '=';
				$provider_sql = " AND provider_code {$operator} %s";
				$values[] = $provider_value;
			}
			$values[] = max( 200, $limit * 20 );
			$sql = $wpdb->prepare(
				"SELECT id,market_code,label,contract_code,price_date,price,
				buy_price,sell_price,change_value,change_percent,
				buy_change,sell_change,high_price,low_price,open_price,
				previous_close,volume,open_interest,unit,currency,
				source_name,source_url,provider_code,observed_at_utc
				FROM %i
				WHERE is_public=1
					AND market_code IN (
						'coffee_domestic',
						'coffee_lam_dong',
						'pepper_domestic'
					)
				{$provider_sql}
				ORDER BY price_date DESC,id DESC LIMIT %d",
				$values
			);
			$candidates = $wpdb->get_results( $sql, ARRAY_A );
			$rows = array();
			$seen = array();
			foreach ( is_array( $candidates ) ? $candidates : array() as $row ) {
				$normalized_label = strtolower(
					remove_accents( (string) ( $row['label'] ?? '' ) )
				);
				$is_pepper = str_contains( $normalized_label, 'tieu' )
					|| str_contains( $normalized_label, 'pepper' )
					|| 'pepper_domestic' === (string) $row['market_code'];
				if (
					( 'pepper_domestic' === $market ) !== $is_pepper
					|| isset( $seen[ $normalized_label ] )
				) {
					continue;
				}
				$seen[ $normalized_label ] = true;
				if ( ! is_numeric( $row['change_value'] ?? null ) ) {
					$column = is_numeric( $row['price'] ?? null )
						? 'price'
						: (
							is_numeric( $row['buy_price'] ?? null )
								? 'buy_price'
								: ''
						);
					if ( '' !== $column ) {
						$previous = self::previous_metric(
							(string) $row['market_code'],
							(string) $row['label'],
							(string) $row['contract_code'],
							(string) $row['price_date'],
							$column
						);
						if ( null !== $previous ) {
							$row['change_value'] =
								(float) $row[ $column ] - $previous;
						}
					}
				}
				$rows[] = $row;
				if ( count( $rows ) >= $limit ) {
					break;
				}
			}
			usort(
				$rows,
				static fn ( array $left, array $right ): int =>
					strnatcasecmp(
						(string) ( $left['label'] ?? '' ),
						(string) ( $right['label'] ?? '' )
					)
			);

			return $rows;
		}
		$filters = '';
		$values = array( $table, $market );
		$subquery_values = array( $table, $market );
		if ( '' !== $series ) {
			$filters .= ' AND label=%s';
			$values[] = $series;
			$subquery_values[] = $series;
		}
		if ( '' !== $provider ) {
			$provider_value = 'vnappmob' === $provider
				? 'vnappmob_%'
				: $provider;
			$filters .= ' AND provider_code'
				. ( 'vnappmob' === $provider ? ' LIKE %s' : '=%s' );
			$values[] = $provider_value;
			$subquery_values[] = $provider_value;
		}
		$values = array_merge(
			$values,
			$subquery_values,
			array( $limit )
		);
		$sql = $wpdb->prepare(
			"SELECT id,label,contract_code,price_date,price,buy_price,
			sell_price,change_value,change_percent,buy_change,
			sell_change,high_price,low_price,open_price,
			previous_close,volume,open_interest,unit,currency,
			source_name,source_url,provider_code,observed_at_utc
			FROM %i
			WHERE is_public=1 AND market_code=%s
			{$filters}
			AND price_date=(
				SELECT MAX(price_date) FROM %i
				WHERE is_public=1 AND market_code=%s
				{$filters}
			)
			ORDER BY label ASC,id ASC LIMIT %d",
			$values
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( 'gold_daily' === $market && is_array( $rows ) ) {
			$priority = array(
				'sjc 9999',
				'nhan sjc',
				'doji',
				'pnj',
				'bao tin',
				'phu quy',
				'mi hong',
			);
			usort(
				$rows,
				static function ( array $left, array $right ) use (
					$priority
				): int {
					$rank = static function ( array $row ) use (
						$priority
					): int {
						$label = strtolower(
							remove_accents(
								(string) ( $row['label'] ?? '' )
							)
						);
						foreach ( $priority as $index => $needle ) {
							if ( str_contains( $label, $needle ) ) {
								return $index;
							}
						}

						return count( $priority );
					};
					$difference = $rank( $left ) - $rank( $right );

					return 0 !== $difference
						? $difference
						: strcasecmp(
							(string) ( $left['label'] ?? '' ),
							(string) ( $right['label'] ?? '' )
						);
				}
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Read a daily series, choosing the latest label when none is supplied.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function history_rows(
		string $market,
		int $limit,
		string $series,
		string $provider = ''
	): array {
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);

		$provider_filter = '' !== $provider
			? ' AND provider_code'
				. ( 'vnappmob' === $provider ? ' LIKE %s' : '=%s' )
			: '';
		$provider_value = 'vnappmob' === $provider
			? 'vnappmob_%'
			: $provider;

		if ( '' === $series ) {
			$values = array( $table, $market );
			if ( '' !== $provider ) {
				$values[] = $provider_value;
			}
			$series = (string) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT label FROM %i
					WHERE is_public=1 AND market_code=%s
					' . $provider_filter . '
					ORDER BY price_date DESC,id DESC LIMIT 1',
					$values
				)
			);
		}

		if ( '' === $series ) {
			return array();
		}

		$values = array( $table, $market, $series );
		if ( '' !== $provider ) {
			$values[] = $provider_value;
		}
		$values[] = $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id,label,contract_code,price_date,price,
				buy_price,sell_price,change_value,change_percent,
				buy_change,sell_change,high_price,low_price,
				open_price,previous_close,volume,open_interest,
				unit,currency,source_name,source_url,
				provider_code,observed_at_utc
				FROM %i
				WHERE is_public=1 AND market_code=%s AND label=%s
				' . $provider_filter . '
				ORDER BY price_date DESC,id DESC LIMIT %d',
				$values
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Read one representative SJC row per day for the domestic-gold chart.
	 *
	 * Editors normally use product labels such as "SJC 1L" or "SJC 5c",
	 * therefore querying the exact label "SJC" leaves the chart empty.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function gold_chart_rows(
		int $limit,
		string $series = '',
		string $provider = ''
	): array {
		if ( '' !== $series ) {
			return self::history_rows(
				'gold_daily',
				$limit,
				$series,
				$provider
			);
		}

		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$provider_filter = '' !== $provider
			? ' AND provider_code'
				. ( 'vnappmob' === $provider ? ' LIKE %s' : '=%s' )
			: '';
		$provider_value = 'vnappmob' === $provider
			? 'vnappmob_%'
			: $provider;
		$values = array(
			$table,
			'gold_daily',
			'SJC%',
		);
		if ( '' !== $provider ) {
			$values[] = $provider_value;
		}
		$values[] = 'SJC';
		$values[] = max( $limit * 12, $limit );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id,label,contract_code,price_date,price,
				buy_price,sell_price,change_value,change_percent,
				buy_change,sell_change,high_price,low_price,
				open_price,previous_close,volume,open_interest,
				unit,currency,source_name,source_url,
				provider_code,observed_at_utc
				FROM %i
				WHERE is_public=1 AND market_code=%s
				AND label LIKE %s
				' . $provider_filter . '
				AND buy_price IS NOT NULL AND sell_price IS NOT NULL
				ORDER BY price_date DESC,
				CASE WHEN label=%s THEN 0 ELSE 1 END,
				id DESC LIMIT %d',
				$values
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || array() === $rows ) {
			return array();
		}

		$daily = array();
		foreach ( $rows as $row ) {
			$date = (string) ( $row['price_date'] ?? '' );
			if ( '' === $date || isset( $daily[ $date ] ) ) {
				continue;
			}
			$daily[ $date ] = $row;
			if ( count( $daily ) >= $limit ) {
				break;
			}
		}

		return array_values( $daily );
	}

	/**
	 * Render a compact USD/VND reference inside commodity collections.
	 */
	private function render_exchange_reference(): string {
		if ( ! empty( $this->rendered_markets['usd_vnd'] ) ) {
			return '';
		}

		$rows = self::latest_rows( 'usd_vnd', 1 );
		if ( array() === $rows ) {
			return '';
		}

		$row = $rows[0];
		$value = $row['price']
			?? $row['sell_price']
			?? $row['buy_price']
			?? null;
		if ( ! is_numeric( $value ) ) {
			return '';
		}

		$this->rendered_markets['usd_vnd'] = true;

		$change = $row['change_value'] ?? null;
		$change_class = is_numeric( $change ) && (float) $change < 0
			? ' is-down'
			: (
				is_numeric( $change ) && (float) $change > 0
					? ' is-up'
					: ''
			);
		$change_text = is_numeric( $change )
			? number_format_i18n( (float) $change, 0 )
			: '—';
		$date = (string) ( $row['price_date'] ?? '' );

		return sprintf(
			'<aside class="psm-market-reference" aria-label="%1$s">'
			. '<div><span>%2$s</span><strong>%3$s<small>%4$s</small></strong></div>'
			. '<div class="%5$s"><span>%6$s</span><strong>%7$s</strong></div>'
			. '<p><span>%8$s</span>%9$s</p>'
			. '</aside>',
			esc_attr__( 'Tỷ giá USD/VND', 'power-schedule-manager' ),
			esc_html__( 'Tỷ giá USD/VND', 'power-schedule-manager' ),
			esc_html( number_format_i18n( (float) $value, 0 ) ),
			esc_html__( 'VND cho 1 USD', 'power-schedule-manager' ),
			esc_attr( trim( $change_class ) ),
			esc_html__( 'Thay đổi', 'power-schedule-manager' ),
			esc_html(
				(
					is_numeric( $change ) && (float) $change > 0
						? '+'
						: ''
				) . $change_text
			),
			esc_html__(
				'Hỗ trợ đối chiếu các mức giá niêm yết bằng USD.',
				'power-schedule-manager'
			),
			'' !== $date
				? '<time datetime="' . esc_attr( $date ) . '">'
					. esc_html( mysql2date( 'd/m/Y', $date ) )
					. '</time>'
				: ''
		);
	}

	/**
	 * Render a concise domestic/world snapshot before detailed gold tables.
	 */
	private function render_gold_snapshot(
		string $domestic_provider,
		string $world_provider
	): string {
		$domestic_rows = self::latest_rows(
			'gold_daily',
			30,
			'',
			$domestic_provider
		);
		$world_rows = self::latest_rows(
			'gold_world',
			1,
			'',
			$world_provider
		);
		$exchange_rows = self::latest_rows( 'usd_vnd', 1 );
		$domestic = $domestic_rows[0] ?? array();
		$world = $world_rows[0] ?? array();
		$buy = is_numeric( $domestic['buy_price'] ?? null )
			? (float) $domestic['buy_price']
			: null;
		$sell = is_numeric( $domestic['sell_price'] ?? null )
			? (float) $domestic['sell_price']
			: null;

		if ( null !== $buy && null !== $sell ) {
			if ( $buy >= 10000 && $sell >= 100 && $sell < 1000 ) {
				$sell *= 1000;
			} elseif ( $sell >= 10000 && $buy >= 100 && $buy < 1000 ) {
				$buy *= 1000;
			}
		}

		$to_million = static fn ( ?float $value ): ?float =>
			null === $value ? null : ( $value >= 1000 ? $value / 1000 : $value );
		$buy_million = $to_million( $buy );
		$sell_million = $to_million( $sell );
		$world_price = is_numeric( $world['price'] ?? null )
			? (float) $world['price']
			: null;
		$exchange = 26323.0;

		if ( array() !== $exchange_rows ) {
			$candidate = $exchange_rows[0]['price']
				?? $exchange_rows[0]['sell_price']
				?? $exchange_rows[0]['buy_price']
				?? null;
			if ( is_numeric( $candidate ) && (float) $candidate > 0 ) {
				$exchange = (float) $candidate;
			}
		}

		$converted = null !== $world_price
			? $world_price * $exchange * ( 37.5 / 31.1034768 ) / 1000000
			: null;
		$difference = null !== $sell_million && null !== $converted
			? $sell_million - $converted
			: null;
		$format = static function (
			?float $value,
			int $decimals = 1
		): string {
			return null === $value
				? '—'
				: number_format_i18n( $value, $decimals );
		};
		$updated = (string) (
			$world['observed_at_utc']
			?? $domestic['observed_at_utc']
			?? ''
		);
		$updated_label = '' !== $updated
			? get_date_from_gmt( $updated, 'H:i d/m/Y' )
			: (
				'' !== (string) ( $domestic['price_date'] ?? '' )
					? mysql2date(
						'd/m/Y',
						(string) $domestic['price_date']
					)
					: ''
			);
		$id = wp_unique_id( 'psm-gold-snapshot-' );

		return '<section class="psm-gold-snapshot" aria-labelledby="'
			. esc_attr( $id ) . '"><header><div><p>'
			. esc_html__( 'So sánh nhanh', 'power-schedule-manager' )
			. '</p><h2 id="' . esc_attr( $id ) . '">'
			. esc_html__(
				'Vàng Việt Nam và thế giới',
				'power-schedule-manager'
			)
			. '</h2></div>'
			. (
				'' !== $updated_label
					? '<time>' . esc_html( $updated_label ) . '</time>'
					: ''
			)
			. '</header><div class="psm-gold-snapshot__grid">'
			. '<article class="psm-gold-snapshot__domestic"><span>'
			. esc_html__( 'Việt Nam', 'power-schedule-manager' )
			. '</span><h3>'
			. esc_html( (string) ( $domestic['label'] ?? 'Vàng SJC' ) )
			. '</h3><div><p><small>'
			. esc_html__( 'Mua vào', 'power-schedule-manager' )
			. '</small><strong>' . esc_html( $format( $buy_million ) )
			. '</strong></p><p><small>'
			. esc_html__( 'Bán ra', 'power-schedule-manager' )
			. '</small><strong>' . esc_html( $format( $sell_million ) )
			. '</strong></p></div><em>'
			. esc_html__( 'triệu đồng/lượng', 'power-schedule-manager' )
			. '</em></article>'
			. '<article class="psm-gold-snapshot__world"><span>'
			. esc_html__( 'Thế giới', 'power-schedule-manager' )
			. '</span><h3>XAU/USD</h3><p><small>'
			. esc_html__( 'Giá quốc tế', 'power-schedule-manager' )
			. '</small><strong>' . esc_html( $format( $world_price, 2 ) )
			. '</strong><em>USD/ounce</em></p></article></div>'
			. '<footer><p><span>'
			. esc_html__( 'Quy đổi theo USD/VND', 'power-schedule-manager' )
			. '</span><strong>' . esc_html( $format( $converted ) )
			. ' ' . esc_html__( 'triệu đồng/lượng', 'power-schedule-manager' )
			. '</strong></p><p><span>'
			. esc_html__(
				'Chênh lệch giá bán trong nước',
				'power-schedule-manager'
			)
			. '</span><strong>'
			. ( null !== $difference && $difference > 0 ? '+' : '' )
			. esc_html( $format( $difference, 2 ) )
			. ' ' . esc_html__( 'triệu đồng/lượng', 'power-schedule-manager' )
			. '</strong></p></footer></section>';
	}

	/**
	 * Render one useful, non-repetitive SEO guide after a collection.
	 *
	 * @param array<int,string>    $markets Collection markets.
	 * @param array<string,string> $sources Source URL/name pairs.
	 */
	private function render_collection_guide(
		array $markets,
		array $sources = array()
	): string {
		$is_gold = in_array( 'gold_daily', $markets, true );

		if ( $is_gold ) {
			$title = __(
				'Cách theo dõi và so sánh giá vàng hôm nay',
				'power-schedule-manager'
			);
			$sections = array(
				__( 'Giá vàng SJC hôm nay mua vào và bán ra', 'power-schedule-manager' ) => __(
					'Bảng giá vàng trong nước trình bày riêng giá mua vào, giá bán ra và chênh lệch của từng hệ thống. Khi so sánh, hãy kiểm tra đúng loại vàng, thương hiệu, ngày cập nhật và đơn vị đồng mỗi lượng.',
					'power-schedule-manager'
				),
				__( 'So sánh giá vàng trong nước và thế giới', 'power-schedule-manager' ) => __(
					'Giá vàng thế giới được tính theo đô la Mỹ trên mỗi ounce. Mức quy đổi sang tiền Việt chỉ để tham khảo vì giá trong nước còn phụ thuộc tỷ giá, chi phí, cung cầu và mức niêm yết của từng doanh nghiệp.',
					'power-schedule-manager'
				),
				__( 'Chênh lệch mua bán nói lên điều gì?', 'power-schedule-manager' ) => __(
					'Khoảng cách giữa giá mua vào và bán ra là chi phí cần cân nhắc khi giao dịch ngắn hạn. Nên so sánh cùng một loại vàng tại nhiều thương hiệu, không dùng giá nhẫn để đối chiếu trực tiếp với vàng miếng.',
					'power-schedule-manager'
				),
				__( 'Tra cứu giá vàng theo thời điểm cập nhật', 'power-schedule-manager' ) => __(
					'Trước khi giao dịch, nên đối chiếu thời điểm cập nhật, chênh lệch mua bán và bảng giá chính thức của thương hiệu tại khu vực. Giá hiển thị là thông tin tham khảo, không phải cam kết mua hoặc bán.',
					'power-schedule-manager'
				),
			);
		} else {
			$title = __(
				'Cách theo dõi và so sánh giá cà phê hôm nay',
				'power-schedule-manager'
			);
			$sections = array(
				__( 'Giá cà phê Lâm Đồng và Tây Nguyên hôm nay', 'power-schedule-manager' ) => __(
					'Giá cà phê trong nước được trình bày theo vùng và đơn vị đồng mỗi kilogram. Giá thu mua thực tế có thể khác theo chất lượng hạt, độ ẩm, tỷ lệ tạp, đại lý và thời điểm chốt giá.',
					'power-schedule-manager'
				),
				__( 'So sánh giá Robusta và Arabica', 'power-schedule-manager' ) => __(
					'Giá cà phê thế giới phản ánh giao dịch quốc tế, không phải giá thu mua trực tiếp tại vườn. Có thể xem thêm tỷ giá bên dưới để đối chiếu, đồng thời kiểm tra thời điểm cập nhật trước khi sử dụng.',
					'power-schedule-manager'
				),
				__( 'Theo dõi biến động giá cà phê nhiều ngày', 'power-schedule-manager' ) => __(
					'Khi theo dõi xu hướng, nên so sánh nhiều ngày liên tiếp thay vì chỉ nhìn một mức giá. Chênh lệch giữa các tỉnh có thể đến từ vận chuyển, chất lượng, nguồn cung và nhu cầu của từng đại lý.',
					'power-schedule-manager'
				),
				__( 'Cách đọc nguồn và đơn vị của bảng giá', 'power-schedule-manager' ) => __(
					'Mỗi bảng cần được đọc cùng ngày ghi nhận, đơn vị và nguồn đối chiếu. Giá nội địa, giá hợp đồng quốc tế và tỷ giá tham chiếu là ba nhóm dữ liệu khác nhau, không nên cộng hoặc quy đổi khi thiếu cùng mốc thời gian.',
					'power-schedule-manager'
				),
			);
		}

		$html = '<section class="psm-market-collection__guide"><h2>'
			. esc_html( $title ) . '</h2>';
		$html .= '<div class="psm-market-collection__guide-grid">';
		foreach ( $sections as $heading => $paragraph ) {
			$html .= '<section><h3>' . esc_html( $heading ) . '</h3><p>'
				. esc_html( $paragraph ) . '</p></section>';
		}
		$html .= '</div>';
		return $html . '</section>';
	}

	private static function previous_metric(
		string $market,
		string $label,
		string $contract_code,
		string $date,
		string $column = 'price'
	): ?float {
		if (
			! in_array(
				$column,
				array( 'price', 'buy_price', 'sell_price' ),
				true
			)
		) {
			return null;
		}

		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT {$column} FROM %i
				WHERE market_code=%s AND label=%s
				AND contract_code=%s AND price_date<%s
				AND {$column} IS NOT NULL
				ORDER BY price_date DESC,id DESC LIMIT 1",
				$table,
				$market,
				$label,
				$contract_code,
				$date
			)
		);

		return null === $value ? null : (float) $value;
	}

	/**
	 * Read recent admin rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function admin_rows(
		string $commodity,
		int $page = 1,
		int $per_page = 20,
		string $market_filter = ''
	): array {
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$where = 'gold' === $commodity
			? 'commodity=%s'
			: 'commodity<>%s';
		$values = array( $table, 'gold' );
		if ( '' !== $market_filter ) {
			$where .= ' AND market_code=%s';
			$values[] = $market_filter;
		}
		$per_page = max( 1, min( 100, $per_page ) );
		$offset = ( max( 1, $page ) - 1 ) * $per_page;
		$values[] = $per_page;
		$values[] = $offset;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where}
				ORDER BY price_date DESC,id DESC LIMIT %d OFFSET %d",
				$values
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	private static function admin_row_count(
		string $commodity,
		string $market_filter = ''
	): int {
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$where = 'gold' === $commodity
			? 'commodity=%s'
			: 'commodity<>%s';
		$values = array( $table, 'gold' );
		if ( '' !== $market_filter ) {
			$where .= ' AND market_code=%s';
			$values[] = $market_filter;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE {$where}",
				$values
			)
		);
	}

	/**
	 * Read one editable row and keep it inside the current commodity screen.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function admin_row(
		int $id,
		string $commodity
	): ?array {
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$where = 'gold' === $commodity
			? 'commodity=%s'
			: 'commodity<>%s';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id=%d AND {$where} LIMIT 1",
				$table,
				$id,
				'gold'
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Add a conservative five-minute interval for world-gold refreshes.
	 *
	 * @param array<string,array<string,int|string>> $schedules Schedules.
	 *
	 * @return array<string,array<string,int|string>>
	 */
	public function register_cron_interval( array $schedules ): array {
		$schedules['psm_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __(
				'Mỗi 5 phút (Cúp Điện Lâm Đồng)',
				'power-schedule-manager'
			),
		);

		return $schedules;
	}

	/**
	 * Keep the API refresh event aligned with its setting.
	 */
	public function ensure_world_gold_schedule(): void {
		$settings = self::market_settings();
		$enabled = ! empty( $settings['gold_api_enabled'] )
			|| ! empty( $settings['fx_api_enabled'] )
			|| ! empty( $settings['commodities_api_enabled'] );
		$scheduled = wp_next_scheduled( self::GOLD_REFRESH_HOOK );

		if ( ! $enabled ) {
			if ( false !== $scheduled ) {
				wp_clear_scheduled_hook( self::GOLD_REFRESH_HOOK );
			}
			return;
		}

		if ( false === $scheduled ) {
			wp_schedule_event(
				time() + MINUTE_IN_SECONDS,
				'psm_five_minutes',
				self::GOLD_REFRESH_HOOK
			);
		}
	}

	/**
	 * Save the opt-in setting for automatic XAU/USD refreshes.
	 */
	public function save_world_gold_settings(): void {
		$this->authorize( self::GOLD_SETTINGS_ACTION );
		$settings = self::market_settings();
		$return_page = isset( $_POST['return_page'] )
			? sanitize_key(
				wp_unslash( (string) $_POST['return_page'] )
			)
			: 'gold';
		$return_page = in_array(
			$return_page,
			array(
				'gold',
				'commodity',
				'data_sources_gold',
				'data_sources_coffee',
			),
			true
		) ? $return_page : 'gold';
		if (
			in_array(
				$return_page,
				array( 'commodity', 'data_sources_coffee' ),
				true
			)
		) {
			$settings['commodities_api_enabled'] = isset(
				$_POST['commodities_api_enabled']
			);
			$commodities_key = Power_Schedule_Manager_Secrets::update(
				$_POST['commodities_api_key'] ?? '',
				(string) $settings['commodities_api_key_encrypted'],
				isset( $_POST['clear_commodities_api_key'] )
			);
			if ( is_wp_error( $commodities_key ) ) {
				$this->redirect( 'invalid', $return_page );
			}
			$settings['commodities_api_key_encrypted'] = $commodities_key;
		} else {
			$settings['gold_api_enabled'] = isset(
				$_POST['gold_api_enabled']
			);
			$provider = isset( $_POST['gold_provider'] )
				? sanitize_key(
					wp_unslash( (string) $_POST['gold_provider'] )
				)
				: 'vang_today';
			$settings['gold_provider'] = in_array(
				$provider,
				array( 'vang_today', 'wifeed' ),
				true
			) ? $provider : 'vang_today';
			$settings['fx_api_enabled'] = isset(
				$_POST['fx_api_enabled']
			);
			$settings['fx_provider'] = 'wifeed';
			$encrypted_key = Power_Schedule_Manager_Secrets::update(
				$_POST['wifeed_api_key'] ?? '',
				(string) $settings['wifeed_api_key_encrypted'],
				isset( $_POST['clear_wifeed_api_key'] )
			);
			if ( is_wp_error( $encrypted_key ) ) {
				$this->redirect( 'invalid', $return_page );
			}
			$settings['wifeed_api_key_encrypted'] = $encrypted_key;
		}
		unset( $settings['wifeed_key_source'] );
		unset( $settings['vnappmob_key_source'] );
		unset( $settings['vnappmob_fx_key_source'] );
		unset( $settings['commodities_key_source'] );
		update_option(
			self::SETTINGS_OPTION,
			$settings,
			false
		);
		$this->ensure_world_gold_schedule();
		$this->redirect( 'settings_saved', $return_page );
	}

	/**
	 * Run an authenticated manual refresh from the administration screen.
	 */
	public function refresh_world_gold_now(): void {
		$this->authorize( self::GOLD_REFRESH_ACTION );
		$return_page = isset( $_POST['return_page'] )
			? sanitize_key(
				wp_unslash( (string) $_POST['return_page'] )
			)
			: 'gold';
		$return_page = in_array(
			$return_page,
			array(
				'gold',
				'commodity',
				'data_sources_gold',
				'data_sources_coffee',
			),
			true
		) ? $return_page : 'gold';
		$scope = in_array(
			$return_page,
			array( 'commodity', 'data_sources_coffee' ),
			true
		) ? 'coffee' : 'gold';
		if ( 'coffee' === $scope ) {
			delete_transient( 'psm_commodities_refresh_throttle' );
		} else {
			delete_transient( 'psm_wifeed_fx_refresh_throttle' );
			delete_transient( 'psm_fx_refresh_throttle' );
		}
		$result = $this->refresh_world_gold( $scope );
		$notice = 'gold_refreshed';
		if ( is_wp_error( $result ) ) {
			$notice = 'market_api_partial' === $result->get_error_code()
				? 'api_partial'
				: 'gold_api_error';
		}
		$this->redirect(
			$notice,
			$return_page
		);
	}

	/**
	 * Refresh the selected gold feed and optional WiFeed exchange rates.
	 *
	 * @return true|WP_Error
	 */
	public function refresh_world_gold(
		string $scope = 'all'
	): true|WP_Error {
		$scope = in_array( $scope, array( 'all', 'gold', 'coffee' ), true )
			? $scope
			: 'all';
		if (
			! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::MARKET_PRICES
			)
		) {
			return new WP_Error( 'market_table_missing' );
		}

		$lock = 'psm_market_api_refresh_lock';
		if ( false !== get_transient( $lock ) ) {
			return new WP_Error( 'market_refresh_locked' );
		}
		set_transient( $lock, '1', MINUTE_IN_SECONDS );

		try {
			$settings = self::market_settings();
			$results = array();
			if (
				in_array( $scope, array( 'all', 'gold' ), true )
				&& ! empty( $settings['gold_api_enabled'] )
			) {
				if ( 'wifeed' === $settings['gold_provider'] ) {
					$results['gold_primary'] =
						$this->refresh_wifeed_gold( $settings );
				} else {
					$results['gold_domestic'] =
						$this->refresh_vang_today();
					$results['gold_world'] = $this->refresh_gold_api();
				}
			}
			if (
				in_array( $scope, array( 'all', 'gold' ), true )
				&&
				! empty( $settings['fx_api_enabled'] )
				&& false === get_transient(
					'psm_fx_refresh_throttle'
				)
			) {
				$fx_result = $this->refresh_wifeed_fx( $settings );
				$results['exchange_rates'] = $fx_result;
				if ( true === $fx_result ) {
					set_transient(
						'psm_fx_refresh_throttle',
						'1',
						30 * MINUTE_IN_SECONDS
					);
				}
			}
			if (
				in_array( $scope, array( 'all', 'coffee' ), true )
				&&
				! empty( $settings['commodities_api_enabled'] )
				&& false === get_transient(
					'psm_commodities_refresh_throttle'
				)
			) {
				$coffee_result = $this->refresh_commodities_coffee(
					$settings
				);
				$results['coffee_world'] = $coffee_result;
				if ( true === $coffee_result ) {
					set_transient(
						'psm_commodities_refresh_throttle',
						'1',
						30 * MINUTE_IN_SECONDS
					);
				}
			}

			$success = false;
			$first_error = null;
			foreach ( $results as $result ) {
				if ( true === $result ) {
					$success = true;
				} elseif ( is_wp_error( $result ) && null === $first_error ) {
					$first_error = $result;
				}
			}
			self::store_refresh_report( $results );
			if ( ! $success ) {
				return $first_error ?? new WP_Error(
					'market_api_not_enabled'
				);
			}

			Power_Schedule_Manager_Cache::invalidate_all();
			do_action(
				'power_schedule_manager_page_cache_purge',
				'market_prices',
				array()
			);

			return null === $first_error
				? true
				: new WP_Error( 'market_api_partial' );
		} finally {
			delete_transient( $lock );
		}
	}

	/**
	 * Refresh domestic and world gold prices from Giavang.now.
	 */
	private function refresh_vang_today(): true|WP_Error {
		$payload = self::remote_json( self::GIAVANG_NOW_URL );
		if (
			is_wp_error( $payload )
			|| empty( $payload['success'] )
			|| (
				! is_array( $payload['prices'] ?? null )
				&& ! is_array( $payload['data'] ?? null )
			)
		) {
			return is_wp_error( $payload )
				? $payload
				: new WP_Error( 'giavang_now_invalid_response' );
		}

		$timestamp = isset( $payload['timestamp'] )
			&& is_numeric( $payload['timestamp'] )
				? (int) $payload['timestamp']
				: (
					isset( $payload['current_time'] )
					&& is_numeric( $payload['current_time'] )
						? (int) $payload['current_time']
						: time()
				);
		$items = is_array( $payload['prices'] ?? null )
			? array_map(
				static function ( mixed $item, string|int $code ): mixed {
					if ( ! is_array( $item ) ) {
						return $item;
					}
					$item['type_code'] = (string) $code;

					return $item;
				},
				$payload['prices'],
				array_keys( $payload['prices'] )
			)
			: (
				is_array( $payload['data'] ?? null )
					? $payload['data']
					: array()
			);
		$observed = ( new DateTimeImmutable( '@' . $timestamp ) )
			->setTimezone( new DateTimeZone( 'UTC' ) );
		$labels = array(
			'SJL1L10'     => 'SJC 9999',
			'SJ9999'      => 'Nhẫn SJC 9999',
			'BTSJC'       => 'Bảo Tín Minh Châu SJC',
			'BT9999NTT'   => 'Bảo Tín Minh Châu 9999',
			'DOHNL'       => 'DOJI Hà Nội',
			'DOHCML'      => 'DOJI TP.HCM',
			'DOJINHTV'    => 'DOJI Nữ trang',
			'PQHNVM'      => 'Phú Quý SJC',
			'PQHN24NTT'   => 'Phú Quý 9999',
			'VNGSJC'      => 'Vàng Mi Hồng SJC',
			'VIETTINMSJC' => 'VietinBank Gold SJC',
		);
		$stored = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$code = isset( $item['type_code'] )
				? strtoupper( sanitize_key( (string) $item['type_code'] ) )
				: '';
			$buy = isset( $item['buy'] ) && is_numeric( $item['buy'] )
				? (float) $item['buy']
				: 0.0;
			$sell = isset( $item['sell'] ) && is_numeric( $item['sell'] )
				? (float) $item['sell']
				: 0.0;

			if ( 'XAUUSD' === $code && $buy > 0 ) {
				$result = self::upsert_feed_row(
					array(
						'commodity'      => 'gold',
						'market_code'    => 'gold_world',
						'label'          => 'XAU/USD',
						'price'          => $buy,
						'change_value'   => self::numeric_or_null(
							$item['change_buy'] ?? null
						),
						'unit'           => 'USD/oz',
						'currency'       => 'USD',
						'source_name'    => 'Giavang.now',
						'source_url'     => 'https://giavang.now/api',
						'provider_code'  => 'vang_today',
						'observed_at_utc' => $observed->format(
							'Y-m-d H:i:s'
						),
					)
				);
			} elseif (
				isset( $labels[ $code ] )
				&& $buy >= 10000
				&& $sell >= 10000
				&& $sell >= $buy * 0.5
				&& $sell <= $buy * 2
			) {
				$result = self::upsert_feed_row(
					array(
						'commodity'      => 'gold',
						'market_code'    => 'gold_daily',
						'label'          => $labels[ $code ],
						'buy_price'      => $buy,
						'sell_price'     => $sell,
						'buy_change'     => self::numeric_or_null(
							$item['change_buy'] ?? null
						),
						'sell_change'    => self::numeric_or_null(
							$item['change_sell'] ?? null
						),
						'unit'           => 'VND/lượng',
						'currency'       => 'VND',
						'source_name'    => 'Giavang.now',
						'source_url'     => 'https://giavang.now/api',
						'provider_code'  => 'vang_today',
						'observed_at_utc' => $observed->format(
							'Y-m-d H:i:s'
						),
					)
				);
			} else {
				continue;
			}
			if ( true === $result ) {
				++$stored;
			}
		}

		return $stored > 0
			? true
			: new WP_Error( 'giavang_now_no_valid_rows' );
	}

	/**
	 * Refresh the WiFeed SJC and XAU/USD reference series.
	 *
	 * WiFeed's demo key intentionally returns historical sample data and is
	 * never accepted for a production refresh.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private function refresh_wifeed_gold(
		array $settings
	): true|WP_Error {
		$key = self::wifeed_api_key( $settings );
		if ( '' === $key || 'demo' === strtolower( $key ) ) {
			return new WP_Error( 'wifeed_production_key_required' );
		}
		$url = add_query_arg(
			array(
				'page'      => 1,
				'limit'     => 1,
				'data_type' => 'value_today',
				'apikey'    => $key,
			),
			self::WIFEED_GOLD_URL
		);
		$payload = self::remote_json( $url );
		if (
			is_wp_error( $payload )
			|| ! isset( $payload['data'][0] )
			|| ! is_array( $payload['data'][0] )
		) {
			return is_wp_error( $payload )
				? $payload
				: new WP_Error( 'wifeed_gold_invalid_response' );
		}
		$row = $payload['data'][0];
		$date = isset( $row['ngay'] ) && self::valid_date(
			(string) $row['ngay']
		) ? (string) $row['ngay'] : '';
		if ( '' === $date ) {
			return new WP_Error( 'wifeed_gold_invalid_date' );
		}
		$observed = $date . ' 00:00:00';
		$map = array(
			'SJC' => array(
				'gia_vang_trong_nuoc_mua_vao',
				'gia_vang_trong_nuoc_ban_ra',
			),
			'SJC 5 chỉ' => array(
				'vang_sjc_5_chi_mua_vao',
				'vang_sjc_5_chi_ban_ra',
			),
			'Nhẫn SJC 9999' => array(
				'vang_nhan_sjc_9999_1_chi_2_chi_5_chi_mua_vao',
				'vang_nhan_sjc_9999_1_chi_2_chi_5_chi_ban_ra',
			),
		);
		$stored = 0;
		foreach ( $map as $label => $fields ) {
			$buy = self::numeric_or_null( $row[ $fields[0] ] ?? null );
			$sell = self::numeric_or_null( $row[ $fields[1] ] ?? null );
			if ( null === $buy || null === $sell ) {
				continue;
			}
			if (
				true === self::upsert_feed_row(
					array(
						'commodity'      => 'gold',
						'market_code'    => 'gold_daily',
						'label'          => $label,
						'price_date'     => $date,
						'buy_price'      => $buy,
						'sell_price'     => $sell,
						'unit'           => 'VND/lượng',
						'currency'       => 'VND',
						'source_name'    => 'WiFeed',
						'source_url'     => 'https://wifeed.vn/dashboard',
						'provider_code'  => 'wifeed',
						'observed_at_utc' => $observed,
					)
				)
			) {
				++$stored;
			}
		}
		$xau = self::numeric_or_null( $row['xau_usd'] ?? null );
		if (
			null !== $xau
			&& true === self::upsert_feed_row(
				array(
					'commodity'      => 'gold',
					'market_code'    => 'gold_world',
					'label'          => 'XAU/USD',
					'price_date'     => $date,
					'price'          => $xau,
					'unit'           => 'USD/oz',
					'currency'       => 'USD',
					'source_name'    => 'WiFeed',
					'source_url'     => 'https://wifeed.vn/dashboard',
					'provider_code'  => 'wifeed',
					'observed_at_utc' => $observed,
				)
			)
		) {
			++$stored;
		}

		return $stored > 0
			? true
			: new WP_Error( 'wifeed_gold_no_valid_rows' );
	}

	/**
	 * Refresh three useful USD/VND references from WiFeed.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private function refresh_wifeed_fx( array $settings ): true|WP_Error {
		$key = self::wifeed_api_key( $settings );
		if ( '' === $key || 'demo' === strtolower( $key ) ) {
			return new WP_Error( 'wifeed_production_key_required' );
		}
		$url = add_query_arg(
			array(
				'page'   => 1,
				'limit'  => 1,
				'apikey' => $key,
			),
			self::WIFEED_FX_URL
		);
		$payload = self::remote_json( $url );
		if (
			is_wp_error( $payload )
			|| ! isset( $payload['data'][0] )
			|| ! is_array( $payload['data'][0] )
		) {
			return is_wp_error( $payload )
				? $payload
				: new WP_Error( 'wifeed_fx_invalid_response' );
		}
		$row = $payload['data'][0];
		$date = isset( $row['ngay'] ) && self::valid_date(
			(string) $row['ngay']
		) ? (string) $row['ngay'] : '';
		if ( '' === $date ) {
			return new WP_Error( 'wifeed_fx_invalid_date' );
		}
		$references = array(
			'USD/VND ngân hàng' => array(
				'price' => 'usd_nhtm_chuyen_khoan',
				'buy'   => 'usd_nhtm_mua_vao',
				'sell'  => 'usd_nhtm_ban_ra',
			),
			'USD/VND tự do' => array(
				'price' => '',
				'buy'   => 'usd_tu_do_mua_vao',
				'sell'  => 'usd_tu_do_ban_ra',
			),
			'Tỷ giá trung tâm' => array(
				'price' => 'usd_nhnn_trung_tam',
				'buy'   => '',
				'sell'  => '',
			),
		);
		$stored = 0;
		foreach ( $references as $label => $fields ) {
			$data = array(
				'commodity'      => 'fx',
				'market_code'    => 'usd_vnd',
				'label'          => $label,
				'price_date'     => $date,
				'price'          => '' !== $fields['price']
					? self::numeric_or_null( $row[ $fields['price'] ] ?? null )
					: null,
				'buy_price'      => '' !== $fields['buy']
					? self::numeric_or_null( $row[ $fields['buy'] ] ?? null )
					: null,
				'sell_price'     => '' !== $fields['sell']
					? self::numeric_or_null( $row[ $fields['sell'] ] ?? null )
					: null,
				'unit'           => 'VND/USD',
				'currency'       => 'VND',
				'source_name'    => 'WiFeed',
				'source_url'     => 'https://wifeed.vn/dashboard',
				'provider_code'  => 'wifeed',
				'observed_at_utc' => $date . ' 00:00:00',
			);
			if ( true === self::upsert_feed_row( $data ) ) {
				++$stored;
			}
		}

		return $stored > 0
			? true
			: new WP_Error( 'wifeed_fx_no_valid_rows' );
	}

	/**
	 * Refresh the Gold API world spot reference.
	 */
	private function refresh_gold_api(): true|WP_Error {
		$payload = self::remote_json( self::GOLD_API_URL );
		if (
			is_wp_error( $payload )
			|| ! isset( $payload['price'] )
			|| ! is_numeric( $payload['price'] )
		) {
			return is_wp_error( $payload )
				? $payload
				: new WP_Error( 'gold_api_invalid_response' );
		}
		$updated = isset( $payload['updatedAt'] )
			&& is_string( $payload['updatedAt'] )
				? $payload['updatedAt']
				: 'now';
		try {
			$observed = new DateTimeImmutable( $updated );
		} catch ( Throwable ) {
			$observed = new DateTimeImmutable( 'now' );
		}

		return self::upsert_feed_row(
			array(
				'commodity'      => 'gold',
				'market_code'    => 'gold_world',
				'label'          => 'XAU/USD',
				'price'          => (float) $payload['price'],
				'unit'           => 'USD/oz',
				'currency'       => 'USD',
				'source_name'    => 'Gold API',
				'source_url'     => self::GOLD_API_URL,
				'provider_code'  => 'gold_api',
				'observed_at_utc' => $observed
					->setTimezone( new DateTimeZone( 'UTC' ) )
					->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Refresh SJC, DOJI and PNJ as three separately addressable feeds.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private function refresh_vnappmob_gold(
		array $settings
	): true|WP_Error {
		$key = self::vnappmob_api_key( $settings );
		if ( '' === $key ) {
			return new WP_Error( 'vnappmob_api_key_required' );
		}
		$feeds = array(
			'sjc' => array(
				'SJC 1 lượng' => array( 'buy_1l', 'sell_1l', 'VND/lượng' ),
				'SJC 1 chỉ' => array( 'buy_1c', 'sell_1c', 'VND/chỉ' ),
				'Nhẫn SJC 1 chỉ' => array(
					'buy_nhan1c',
					'sell_nhan1c',
					'VND/chỉ',
				),
				'Trang sức SJC 49' => array(
					'buy_trangsuc49',
					'sell_trangsuc49',
					'VND/chỉ',
				),
			),
			'doji' => array(
				'DOJI TP.HCM' => array( 'buy_hcm', 'sell_hcm', 'VND/lượng' ),
				'DOJI Hà Nội' => array( 'buy_hn', 'sell_hn', 'VND/lượng' ),
			),
			'pnj' => array(
				'PNJ TP.HCM' => array( 'buy_hcm', 'sell_hcm', 'VND/lượng' ),
				'PNJ Hà Nội' => array( 'buy_hn', 'sell_hn', 'VND/lượng' ),
			),
		);
		$stored = 0;
		$first_error = null;
		$observed = gmdate( 'Y-m-d H:i:s' );

		foreach ( $feeds as $feed => $fields ) {
			$url = self::VNAPPMOB_API_BASE . '/gold/' . $feed;
			$payload = self::remote_json(
				$url,
				array( 'Authorization' => 'Bearer ' . $key )
			);
			if (
				is_wp_error( $payload )
				|| ! isset( $payload['results'] )
				|| ! is_array( $payload['results'] )
			) {
				if ( null === $first_error ) {
					$first_error = is_wp_error( $payload )
						? $payload
						: new WP_Error(
							'vnappmob_gold_invalid_response'
						);
				}
				continue;
			}

			foreach ( array_slice( $payload['results'], 0, 5 ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$row_stored = 0;
				foreach ( $fields as $label => $mapping ) {
					$buy = self::numeric_or_null(
						$row[ $mapping[0] ] ?? null
					);
					$sell = self::numeric_or_null(
						$row[ $mapping[1] ] ?? null
					);
					if (
						null === $buy
						|| null === $sell
						|| $buy <= 0
						|| $sell <= 0
					) {
						continue;
					}
					$result = self::upsert_feed_row(
						array(
							'commodity'      => 'gold',
							'market_code'    => 'gold_daily',
							'label'          => $label,
							'buy_price'      => $buy,
							'sell_price'     => $sell,
							'unit'           => $mapping[2],
							'currency'       => 'VND',
							'source_name'    => 'VNAppMob ' . strtoupper(
								$feed
							),
							'source_url'     => 'https://vapi.vnappmob.com/gold.v2.html',
							'provider_code'  => 'vnappmob_' . $feed,
							'observed_at_utc' => $observed,
						)
					);
					if ( true === $result ) {
						++$stored;
						++$row_stored;
					}
				}
				if ( $row_stored > 0 ) {
					break;
				}
			}
		}

		return $stored > 0
			? true
			: (
				$first_error
				?? new WP_Error( 'vnappmob_gold_no_valid_rows' )
			);
	}

	/**
	 * Refresh a separately stored VNAppMob exchange-rate feed.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private function refresh_vnappmob_fx(
		array $settings
	): true|WP_Error {
		$key = self::vnappmob_fx_api_key( $settings );
		if ( '' === $key ) {
			return new WP_Error( 'vnappmob_fx_api_key_required' );
		}
		$bank = (string) $settings['vnappmob_fx_bank'];
		$url = self::VNAPPMOB_API_BASE . '/exchange_rate/' . $bank;
		$payload = self::remote_json(
			$url,
			array( 'Authorization' => 'Bearer ' . $key )
		);
		if (
			is_wp_error( $payload )
			|| ! isset( $payload['results'] )
			|| ! is_array( $payload['results'] )
		) {
			return is_wp_error( $payload )
				? $payload
				: new WP_Error( 'vnappmob_fx_invalid_response' );
		}
		$bank_names = array(
			'vcb' => 'Vietcombank',
			'ctg' => 'VietinBank',
			'tcb' => 'Techcombank',
			'bid' => 'BIDV',
			'stb' => 'Sacombank',
			'sbv' => 'Ngân hàng Nhà nước',
		);
		$stored = 0;
		$observed = gmdate( 'Y-m-d H:i:s' );
		foreach ( array_slice( $payload['results'], 0, 50 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$currency = isset( $row['currency'] )
				? strtoupper(
					sanitize_key( (string) $row['currency'] )
				)
				: '';
			$buy_cash = self::numeric_or_null(
				$row['buy_cash'] ?? $row['buy'] ?? null
			);
			$buy_transfer = self::numeric_or_null(
				$row['buy_transfer'] ?? $row['buy'] ?? null
			);
			$sell = self::numeric_or_null( $row['sell'] ?? null );
			if (
				3 !== strlen( $currency )
				|| null === $sell
				|| $sell <= 0
			) {
				continue;
			}
			if (
				true === self::upsert_feed_row(
					array(
						'commodity'      => 'fx',
						'market_code'    => 'exchange_rates',
						'label'          => $currency . '/VND — '
							. $bank_names[ $bank ],
						'price'          => $buy_transfer,
						'buy_price'      => $buy_cash,
						'sell_price'     => $sell,
						'unit'           => 'VND',
						'currency'       => $currency,
						'source_name'    => 'VNAppMob — '
							. $bank_names[ $bank ],
						'source_url'     => 'https://vapi.vnappmob.com/exchange_rate.v2.html',
						'provider_code'  => 'vnappmob_fx_' . $bank,
						'observed_at_utc' => $observed,
					)
				)
			) {
				++$stored;
			}
		}

		return $stored > 0
			? true
			: new WP_Error( 'vnappmob_fx_no_valid_rows' );
	}

	/**
	 * Refresh international coffee references from Commodities-API.
	 *
	 * Domestic coffee remains editorial. API rates relative to USD are
	 * inverted according to the provider documentation.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private function refresh_commodities_coffee(
		array $settings
	): true|WP_Error {
		$key = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_COMMODITIES_API_KEY',
			(string) $settings['commodities_api_key_encrypted']
		);
		if ( '' === $key ) {
			return new WP_Error( 'commodities_api_key_required' );
		}
		$url = add_query_arg(
			array(
				'access_key' => $key,
				'base'       => 'USD',
				'symbols'    => 'ROBUSTA,COFFEE',
			),
			self::COMMODITIES_API_URL
		);
		$payload = self::remote_json( $url );
		if (
			is_wp_error( $payload )
			|| empty( $payload['success'] )
			|| ! isset( $payload['rates'] )
			|| ! is_array( $payload['rates'] )
		) {
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}
			$error_code = isset( $payload['error']['code'] )
				&& is_scalar( $payload['error']['code'] )
					? sanitize_key(
						'commodities_api_' . (string) $payload['error']['code']
					)
					: 'commodities_api_invalid_response';
			return new WP_Error( $error_code );
		}
		$timestamp = isset( $payload['timestamp'] )
			&& is_numeric( $payload['timestamp'] )
				? (int) $payload['timestamp']
				: time();
		$observed = ( new DateTimeImmutable( '@' . $timestamp ) )
			->setTimezone( new DateTimeZone( 'UTC' ) );
		$date = isset( $payload['date'] )
			&& self::valid_date( (string) $payload['date'] )
				? (string) $payload['date']
				: $observed->setTimezone(
					new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
				)->format( 'Y-m-d' );
		$units = isset( $payload['unit'] ) && is_array( $payload['unit'] )
			? $payload['unit']
			: array();
		$labels = array(
			'ROBUSTA' => 'Robusta quốc tế',
			'COFFEE'  => 'Arabica quốc tế',
		);
		$stored = 0;
		foreach ( $labels as $symbol => $label ) {
			$rate = self::numeric_or_null(
				$payload['rates'][ $symbol ] ?? null
			);
			if ( null === $rate || $rate <= 0 ) {
				continue;
			}
			$price = 1 / $rate;
			$unit = isset( $units[ $symbol ] )
				&& is_scalar( $units[ $symbol ] )
					? self::limit_text(
						sanitize_text_field(
							(string) $units[ $symbol ]
						),
						32
					)
					: 'USD';
			if (
				true === self::upsert_feed_row(
					array(
						'commodity'      => 'coffee',
						'market_code'    => 'coffee_futures',
						'label'          => $label,
						'contract_code'  => 'SPOT',
						'price_date'     => $date,
						'price'          => $price,
						'unit'           => $unit,
						'currency'       => 'USD',
						'source_name'    => 'Commodities-API',
						'source_url'     => 'https://www.commodities-api.com/documentation',
						'provider_code'  => 'commodities_api',
						'observed_at_utc' => $observed->format(
							'Y-m-d H:i:s'
						),
					)
				)
			) {
				++$stored;
			}
		}

		return $stored > 0
			? true
			: new WP_Error( 'commodities_api_no_valid_rows' );
	}

	/**
	 * Resolve the WiFeed key without exposing it to templates.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private static function wifeed_api_key( array $settings ): string {
		return Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_WIFEED_API_KEY',
			(string) $settings['wifeed_api_key_encrypted']
		);
	}

	/**
	 * Resolve the VNAppMob Bearer key without exposing it to templates.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private static function vnappmob_api_key( array $settings ): string {
		$key = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_VNAPPMOB_API_KEY',
			(string) $settings['vnappmob_api_key_encrypted']
		);

		return 1 === preg_match( '/[\r\n]/', $key ) ? '' : $key;
	}

	/**
	 * Resolve the VNAppMob exchange-rate key. VNAppMob issues keys by scope,
	 * so a gold key must not silently be treated as an exchange-rate key.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private static function vnappmob_fx_api_key( array $settings ): string {
		$key = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_VNAPPMOB_FX_API_KEY',
			(string) $settings['vnappmob_fx_api_key_encrypted']
		);

		return 1 === preg_match( '/[\r\n]/', $key ) ? '' : $key;
	}

	/**
	 * Keep a safe per-source result for the administration screen.
	 *
	 * @param array<string,true|WP_Error> $results Provider results.
	 */
	private static function store_refresh_report( array $results ): void {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$report = isset( $settings['last_refresh_report'] )
			&& is_array( $settings['last_refresh_report'] )
				? $settings['last_refresh_report']
				: array();
		foreach ( $results as $service => $result ) {
			$report[ sanitize_key( (string) $service ) ] = array(
				'ok'   => true === $result,
				'code' => is_wp_error( $result )
					? sanitize_key( $result->get_error_code() )
					: 'ok',
				'at'   => Power_Schedule_Manager_Database::utc_now(),
			);
		}
		$settings['last_refresh_report'] = $report;
		update_option( self::SETTINGS_OPTION, $settings, false );
	}

	/**
	 * Fetch a small JSON document from one fixed HTTPS provider.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private static function remote_json(
		string $url,
		array $headers = array()
	): array|WP_Error {
		$headers = array_merge(
			array( 'Accept' => 'application/json' ),
			array_filter(
				$headers,
				static fn ( mixed $value ): bool => is_scalar( $value )
			)
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 1,
				'limit_response_size' => 2 * MB_IN_BYTES,
				'headers'             => $headers,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'market_provider_unavailable' );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if (
			$status < 200
			|| $status >= 300
			|| '' === $body
			|| strlen( $body ) > 2 * MB_IN_BYTES
		) {
			return new WP_Error(
				in_array( $status, array( 401, 403 ), true )
					? 'market_provider_auth_failed'
					: (
						429 === $status
							? 'market_provider_rate_limited'
							: 'market_provider_invalid_http'
					)
			);
		}
		$payload = json_decode( $body, true );

		return is_array( $payload )
			? $payload
			: new WP_Error( 'market_provider_invalid_json' );
	}

	/**
	 * Upsert one normalized feed row.
	 *
	 * @param array<string,mixed> $row Normalized row.
	 */
	private static function upsert_feed_row(
		array $row
	): true|WP_Error {
		$market = sanitize_key( (string) ( $row['market_code'] ?? '' ) );
		$label = self::limit_text(
			sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			191
		);
		if ( ! isset( self::MARKETS[ $market ] ) || '' === $label ) {
			return new WP_Error( 'market_feed_row_invalid' );
		}
		$observed = isset( $row['observed_at_utc'] )
			? (string) $row['observed_at_utc']
			: gmdate( 'Y-m-d H:i:s' );
		try {
			$observed_date = new DateTimeImmutable(
				$observed,
				new DateTimeZone( 'UTC' )
			);
		} catch ( Throwable ) {
			$observed_date = new DateTimeImmutable(
				'now',
				new DateTimeZone( 'UTC' )
			);
		}
		$date = isset( $row['price_date'] )
			&& self::valid_date( (string) $row['price_date'] )
				? (string) $row['price_date']
				: $observed_date->setTimezone(
					new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
				)->format( 'Y-m-d' );
		$contract = self::limit_text(
			sanitize_text_field(
				(string) ( $row['contract_code'] ?? '' )
			),
			32
		);
		$metrics = array();
		foreach (
			array(
				'price',
				'buy_price',
				'sell_price',
				'change_value',
				'buy_change',
				'sell_change',
			) as $metric
		) {
			$value = self::numeric_or_null( $row[ $metric ] ?? null );
			$metrics[ $metric ] = null === $value
				? ''
				: self::decimal_string( $value );
		}
		if (
			'' === $metrics['price']
			&& '' === $metrics['buy_price']
			&& '' === $metrics['sell_price']
		) {
			return new WP_Error( 'market_feed_metrics_missing' );
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$hash = hash(
			'sha256',
			wp_json_encode(
				array(
					$market,
					$label,
					$contract,
					$date,
					$metrics,
					$observed_date->format( 'Y-m-d H:i:s' ),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			),
			false
		);
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$upsert_alias = Power_Schedule_Manager_Database::upsert_row_alias();
		$upsert_columns = array(
			'price',
			'buy_price',
			'sell_price',
			'change_value',
			'buy_change',
			'sell_change',
			'unit',
			'currency',
			'source_name',
			'source_url',
			'provider_code',
			'observed_at_utc',
			'fetched_at_utc',
			'data_hash',
			'updated_at_utc',
		);
		$upsert_assignments = array();

		foreach ( $upsert_columns as $column ) {
			$upsert_assignments[] = sprintf(
				'`%1$s`=%2$s',
				$column,
				Power_Schedule_Manager_Database::upsert_value( $column )
			);
		}

		$upsert_sql = implode( ",\n", $upsert_assignments );
		$sql = $wpdb->prepare(
			"INSERT INTO %i
			(commodity,market_code,label,contract_code,price_date,
			price,buy_price,sell_price,change_value,buy_change,
			sell_change,unit,currency,source_name,source_url,
			provider_code,observed_at_utc,fetched_at_utc,data_hash,
			is_public,created_by,created_at_utc,updated_at_utc)
			VALUES
			(%s,%s,%s,%s,%s,NULLIF(%s,''),NULLIF(%s,''),
			NULLIF(%s,''),NULLIF(%s,''),NULLIF(%s,''),
			NULLIF(%s,''),%s,%s,%s,%s,%s,%s,%s,UNHEX(%s),
			1,0,%s,%s)
			{$upsert_alias}
			ON DUPLICATE KEY UPDATE
			{$upsert_sql},is_public=1",
			$table,
			sanitize_key( (string) ( $row['commodity'] ?? '' ) ),
			$market,
			$label,
			$contract,
			$date,
			$metrics['price'],
			$metrics['buy_price'],
			$metrics['sell_price'],
			$metrics['change_value'],
			$metrics['buy_change'],
			$metrics['sell_change'],
			self::limit_text(
				sanitize_text_field( (string) ( $row['unit'] ?? '' ) ),
				32
			),
			self::limit_text(
				sanitize_text_field(
					(string) ( $row['currency'] ?? '' )
				),
				8
			),
			self::limit_text(
				sanitize_text_field(
					(string) ( $row['source_name'] ?? '' )
				),
				191
			),
			esc_url_raw( (string) ( $row['source_url'] ?? '' ) ),
			sanitize_key( (string) ( $row['provider_code'] ?? '' ) ),
			$observed_date->format( 'Y-m-d H:i:s' ),
			$now,
			$hash,
			$now,
			$now
		);

		return false === $wpdb->query( $sql )
			? new WP_Error( 'market_api_storage_failed' )
			: true;
	}

	private static function numeric_or_null( mixed $value ): ?float {
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$value = (float) $value;

		return is_finite( $value ) ? $value : null;
	}

	/**
	 * Read sanitized market integration settings.
	 *
	 * @return array{
	 *   gold_api_enabled:bool,
	 *   gold_provider:string,
	 *   fx_api_enabled:bool,
	 *   fx_provider:string,
	 *   vnappmob_fx_bank:string,
	 *   wifeed_api_key_encrypted:string,
	 *   wifeed_key_source:string,
	 *   vnappmob_api_key_encrypted:string,
	 *   vnappmob_key_source:string,
	 *   vnappmob_fx_api_key_encrypted:string,
	 *   vnappmob_fx_key_source:string,
	 *   commodities_api_enabled:bool,
	 *   commodities_api_key_encrypted:string,
	 *   commodities_key_source:string
	 * }
	 */
	private static function market_settings(): array {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$encrypted = isset( $settings['wifeed_api_key_encrypted'] )
			? (string) $settings['wifeed_api_key_encrypted']
			: '';
		$provider = isset( $settings['gold_provider'] )
			? sanitize_key( (string) $settings['gold_provider'] )
			: 'vang_today';
		$commodities_encrypted = isset(
			$settings['commodities_api_key_encrypted']
		) ? (string) $settings['commodities_api_key_encrypted'] : '';
		$vnappmob_encrypted = isset(
			$settings['vnappmob_api_key_encrypted']
		) ? (string) $settings['vnappmob_api_key_encrypted'] : '';
		$vnappmob_fx_encrypted = isset(
			$settings['vnappmob_fx_api_key_encrypted']
		) ? (string) $settings['vnappmob_fx_api_key_encrypted'] : '';
		$fx_bank = isset( $settings['vnappmob_fx_bank'] )
			? sanitize_key( (string) $settings['vnappmob_fx_bank'] )
			: 'vcb';

		return array(
			'gold_api_enabled' => ! empty(
				$settings['gold_api_enabled']
			),
			'gold_provider' => in_array(
				$provider,
				array( 'vang_today', 'wifeed' ),
				true
			) ? $provider : 'vang_today',
			'fx_api_enabled' => ! empty(
				$settings['fx_api_enabled']
			) || ! empty(
				$settings['wifeed_fx_enabled']
			),
			'fx_provider' => 'wifeed',
			'vnappmob_fx_bank' => in_array(
				$fx_bank,
				array( 'vcb', 'ctg', 'tcb', 'bid', 'stb', 'sbv' ),
				true
			) ? $fx_bank : 'vcb',
			'wifeed_api_key_encrypted' => $encrypted,
			'wifeed_key_source' => Power_Schedule_Manager_Secrets::source(
				'POWER_SCHEDULE_MANAGER_WIFEED_API_KEY',
				$encrypted
			),
			'vnappmob_api_key_encrypted' => $vnappmob_encrypted,
			'vnappmob_key_source' => Power_Schedule_Manager_Secrets::source(
				'POWER_SCHEDULE_MANAGER_VNAPPMOB_API_KEY',
				$vnappmob_encrypted
			),
			'vnappmob_fx_api_key_encrypted' => $vnappmob_fx_encrypted,
			'vnappmob_fx_key_source' => Power_Schedule_Manager_Secrets::source(
				'POWER_SCHEDULE_MANAGER_VNAPPMOB_FX_API_KEY',
				$vnappmob_fx_encrypted
			),
			'last_refresh_report' => isset(
				$settings['last_refresh_report']
			) && is_array( $settings['last_refresh_report'] )
				? $settings['last_refresh_report']
				: array(),
			'commodities_api_enabled' => ! empty(
				$settings['commodities_api_enabled']
			),
			'commodities_api_key_encrypted' => $commodities_encrypted,
			'commodities_key_source' => Power_Schedule_Manager_Secrets::source(
				'POWER_SCHEDULE_MANAGER_COMMODITIES_API_KEY',
				$commodities_encrypted
			),
		);
	}

	private function authorize( string $action ): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
			|| ! isset( $_POST['_psm_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( (string) $_POST['_psm_nonce'] )
				),
				$action
			)
		) {
			wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'power-schedule-manager' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Repair rows created when Vietnamese thousands separators were parsed as
	 * decimal separators (for example, 141.530 becoming 141.53).
	 *
	 * The repair is deliberately narrow: only domestic-gold pairs where one
	 * side is a normal VND/lượng value and the other is exactly three orders
	 * of magnitude smaller are changed.
	 */
	public function repair_legacy_gold_separators(): void {
		$option = 'power_schedule_manager_gold_separator_repair_1';
		if (
			get_option( $option, false )
			|| ! Power_Schedule_Manager_Database::table_exists(
				Power_Schedule_Manager_Database::MARKET_PRICES
			)
		) {
			return;
		}

		global $wpdb;
		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::MARKET_PRICES
		);
		$batch_size = 500;
		$max_batches = 10;
		$repaired = 0;
		$complete = false;

		for ( $batch = 0; $batch < $max_batches; ++$batch ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id,buy_price,sell_price
					FROM %i
					WHERE market_code='gold_daily'
						AND (
							(buy_price>=10000 AND sell_price BETWEEN 100 AND 999.9999)
							OR
							(sell_price>=10000 AND buy_price BETWEEN 100 AND 999.9999)
						)
					ORDER BY id ASC
					LIMIT %d",
					$table,
					$batch_size
				),
				ARRAY_A
			);
			$rows = is_array( $rows ) ? $rows : array();

			foreach ( $rows as $row ) {
				$id = absint( $row['id'] ?? 0 );
				$buy = (float) ( $row['buy_price'] ?? 0 );
				$sell = (float) ( $row['sell_price'] ?? 0 );
				if ( $buy >= 10000 && $sell >= 100 && $sell < 1000 ) {
					$sell *= 1000;
				} elseif ( $sell >= 10000 && $buy >= 100 && $buy < 1000 ) {
					$buy *= 1000;
				} else {
					continue;
				}

				$buy_text = self::decimal_string( $buy );
				$sell_text = self::decimal_string( $sell );
				$updated_at = gmdate( 'Y-m-d H:i:s' );
				$hash = hash(
					'sha256',
					'gold-separator-repair|'
						. $id . '|' . $buy_text . '|' . $sell_text,
					false
				);
				$result = $wpdb->query(
					$wpdb->prepare(
						"UPDATE %i
						SET buy_price=%s,sell_price=%s,
							updated_at_utc=%s,data_hash=UNHEX(%s)
						WHERE id=%d",
						$table,
						$buy_text,
						$sell_text,
						$updated_at,
						$hash,
						$id
					)
				);
				if ( false !== $result ) {
					++$repaired;
				}
			}

			if ( count( $rows ) < $batch_size ) {
				$complete = true;
				break;
			}
		}

		if ( $complete ) {
			update_option( $option, gmdate( 'Y-m-d H:i:s' ), false );
		}
		if ( $repaired > 0 ) {
			Power_Schedule_Manager_Cache::invalidate_all();
		}
	}

	/**
	 * Parse a localized decimal without confusing a VND thousands separator
	 * with the decimal point.
	 */
	private static function decimal_input(
		string $key,
		string $market = ''
	): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}

		$value = preg_replace(
			'/[\s\x{00A0}]+/u',
			'',
			trim( wp_unslash( (string) $_POST[ $key ] ) )
		);
		if (
			! is_string( $value )
			|| 1 !== preg_match( '/\A-?[\d.,]+\z/', $value )
		) {
			return '';
		}

		$integer_markets = array(
			'coffee_lam_dong',
			'coffee_domestic',
			'pepper_domestic',
			'usd_vnd',
			'gold_daily',
		);
		if ( 'percentage' === $market ) {
			$value = str_replace( ',', '.', $value );
		} elseif ( in_array( $market, $integer_markets, true ) ) {
			$value = str_replace( array( '.', ',' ), '', $value );
		} else {
			$dot_count = substr_count( $value, '.' );
			$comma_count = substr_count( $value, ',' );
			if ( $dot_count > 0 && $comma_count > 0 ) {
				$last_dot = strrpos( $value, '.' );
				$last_comma = strrpos( $value, ',' );
				$decimal_separator = $last_dot > $last_comma ? '.' : ',';
				$group_separator = '.' === $decimal_separator ? ',' : '.';
				$value = str_replace( $group_separator, '', $value );
				$value = str_replace( $decimal_separator, '.', $value );
			} elseif ( 1 === $dot_count + $comma_count ) {
				$separator = $dot_count ? '.' : ',';
				$parts = explode( $separator, ltrim( $value, '-' ) );
				$value = isset( $parts[1] ) && 3 === strlen( $parts[1] )
					? str_replace( $separator, '', $value )
					: str_replace( $separator, '.', $value );
			} elseif ( $dot_count + $comma_count > 1 ) {
				$value = str_replace( array( '.', ',' ), '', $value );
			}
		}

		return 1 === preg_match( '/\A-?\d{1,16}(?:\.\d{1,4})?\z/', $value )
			? $value
			: '';
	}

	private static function decimal_string( float $value ): string {
		return rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );
	}

	private static function provider_input(): string {
		return 'editorial';
	}

	private static function integer_input( string $key ): int {
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return 0;
		}

		return max( 0, absint( $_POST[ $key ] ) );
	}

	private static function observed_at_input( string $date ): ?string {
		$value = isset( $_POST['observed_at'] )
			? sanitize_text_field(
				wp_unslash( (string) $_POST['observed_at'] )
			)
			: '';

		if ( '' === $value ) {
			$value = $date . ' 00:00';
		}

		$local = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i',
			str_replace( 'T', ' ', $value ),
			new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
		);

		if ( ! $local instanceof DateTimeImmutable ) {
			return null;
		}

		return $local
			->setTimezone( new DateTimeZone( 'UTC' ) )
			->format( 'Y-m-d H:i:s' );
	}

	private static function text_input(
		string $key,
		string $default,
		int $length
	): string {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) )
			: $default;

		return self::limit_text( $value, $length );
	}

	private static function limit_text( string $value, int $length ): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, $length, 'UTF-8' )
			: substr( $value, 0, $length );
	}

	private static function valid_date( string $date ): bool {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		return $parsed instanceof DateTimeImmutable
			&& $parsed->format( 'Y-m-d' ) === $date;
	}

	private static function page_kind( string $market ): string {
		return isset( self::MARKETS[ $market ] )
			&& 'gold' === self::MARKETS[ $market ]['commodity']
				? 'gold'
				: 'coffee';
	}

	private function redirect(
		string $notice,
		string $page_kind = 'coffee'
	): never {
		if (
			in_array(
				$page_kind,
				array( 'data_sources_gold', 'data_sources_coffee' ),
				true
			)
		) {
			$tab = 'data_sources_gold' === $page_kind ? 'gold' : 'coffee';
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'         => Power_Schedule_Manager_Admin::DATA_SOURCES_SLUG,
						'settings_tab' => $tab,
						'psm_notice'   => sanitize_key( $notice ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		$page = 'gold' === $page_kind
			? self::GOLD_MENU_SLUG
			: self::COFFEE_MENU_SLUG;
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => $page,
					'psm_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
