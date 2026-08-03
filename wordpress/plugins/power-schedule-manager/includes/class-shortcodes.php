<?php
/**
 * Public schedule shortcodes.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers public schedule and search shortcodes.
 */
final class Power_Schedule_Manager_Shortcodes {

	/**
	 * Schedule shortcode.
	 */
	public const string SCHEDULE_SHORTCODE = 'power_schedule';

	/**
	 * Search-form shortcode.
	 */
	public const string SEARCH_SHORTCODE = 'power_schedule_search';

	/**
	 * Public electricity-area navigation shortcode.
	 */
	public const string AREAS_SHORTCODE = 'power_schedule_areas';

	/**
	 * Compact next schedule shortcode.
	 */
	public const string NEXT_SHORTCODE = 'power_schedule_next';

	/**
	 * Current public alert shortcode.
	 */
	public const string ALERT_SHORTCODE = 'power_schedule_alert';

	/**
	 * Navigation for days containing schedules.
	 */
	public const string DAYS_SHORTCODE = 'power_schedule_days';

	/**
	 * Recently updated public schedules.
	 */
	public const string RECENT_UPDATES_SHORTCODE =
		'power_schedule_recent_updates';

	/**
	 * Composite homepage hub shortcode.
	 */
	public const string HOME_SHORTCODE = 'power_schedule_home';

	/**
	 * Server-rendered page hero for utility pages.
	 */
	public const string PAGE_HERO_SHORTCODE = 'power_schedule_page_hero';

	/**
	 * Maximum query date range.
	 */
	private const int MAX_DAYS = 31;

	/**
	 * Return every public shortcode tag registered by this service.
	 *
	 * @return array<int, string>
	 */
	public static function tags(): array {
		return array(
			self::SCHEDULE_SHORTCODE,
			self::SEARCH_SHORTCODE,
			self::AREAS_SHORTCODE,
			self::NEXT_SHORTCODE,
			self::ALERT_SHORTCODE,
			self::DAYS_SHORTCODE,
			self::RECENT_UPDATES_SHORTCODE,
			self::HOME_SHORTCODE,
			self::PAGE_HERO_SHORTCODE,
		);
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_shortcode_tags();

		/*
		 * A few themes and full-page cache integrations rebuild the shortcode
		 * registry during bootstrap. Registering again after every plugin and
		 * the theme have loaded is harmless (add_shortcode replaces the same
		 * callback) and prevents the homepage tag being printed as plain text.
		 */
		add_action( 'init', array( $this, 'register_shortcode_tags' ), 1 );
		add_action( 'wp_loaded', array( $this, 'register_shortcode_tags' ), 100 );
	}

	/**
	 * Register every public shortcode tag idempotently.
	 */
	public function register_shortcode_tags(): void {
		add_shortcode(
			self::SCHEDULE_SHORTCODE,
			array( $this, 'render_schedule_shortcode' )
		);

		add_shortcode(
			self::SEARCH_SHORTCODE,
			array( $this, 'render_search_shortcode' )
		);

		add_shortcode(
			self::AREAS_SHORTCODE,
			array( $this, 'render_areas_shortcode' )
		);

		add_shortcode(
			self::NEXT_SHORTCODE,
			array( $this, 'render_next_shortcode' )
		);

		add_shortcode(
			self::ALERT_SHORTCODE,
			array( $this, 'render_alert_shortcode' )
		);

		add_shortcode(
			self::DAYS_SHORTCODE,
			array( $this, 'render_days_shortcode' )
		);

		add_shortcode(
			self::RECENT_UPDATES_SHORTCODE,
			array( $this, 'render_recent_updates_shortcode' )
		);

		add_shortcode(
			self::HOME_SHORTCODE,
			array( $this, 'render_home_shortcode' )
		);

		add_shortcode(
			self::PAGE_HERO_SHORTCODE,
			array( $this, 'render_page_hero_shortcode' )
		);
	}

	/**
	 * Render the single H1 hero owned by a WordPress utility page.
	 *
	 * The Blocksy page title must be disabled when this shortcode is used so
	 * the document keeps exactly one H1. Rank Math remains responsible for all
	 * metadata and structured data.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 */
	public function render_page_hero_shortcode(
		array|string $attributes = array()
	): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$variant = sanitize_key( (string) ( $attributes['variant'] ?? 'schedule' ) );
		$presets = self::page_hero_presets();
		if ( ! isset( $presets[ $variant ] ) ) {
			$variant = 'schedule';
		}
		$defaults = array_merge(
			$presets[ $variant ],
			array(
				'variant'         => $variant,
				'meta_1_label'    => '',
				'meta_1_value'    => '',
				'meta_1_detail'   => '',
				'meta_1_tone'     => '',
				'meta_2_label'    => '',
				'meta_2_value'    => '',
				'meta_2_detail'   => '',
				'meta_2_tone'     => '',
				'meta_3_label'    => '',
				'meta_3_value'    => '',
				'meta_3_detail'   => '',
				'meta_3_tone'     => '',
				'secondary_label' => '',
				'secondary_url'   => '',
				'breadcrumb_parent_label' => '',
				'breadcrumb_parent_url'   => '',
				'show_breadcrumb' => 'yes',
			)
		);
		$attributes = shortcode_atts(
			$defaults,
			$attributes,
			self::PAGE_HERO_SHORTCODE
		);
		$title = sanitize_text_field( (string) $attributes['title'] );
		if ( '' === $title ) {
			$title = sanitize_text_field( (string) get_the_title() );
		}
		if ( '' === $title ) {
			return '';
		}

		self::enqueue_frontend_assets();
		$id = wp_unique_id( 'psm-page-hero-' );
		$meta = array();
		for ( $index = 1; $index <= 3; ++$index ) {
			$label = sanitize_text_field(
				(string) $attributes[ 'meta_' . $index . '_label' ]
			);
			$value = sanitize_text_field(
				(string) $attributes[ 'meta_' . $index . '_value' ]
			);
			$detail = sanitize_text_field(
				(string) $attributes[ 'meta_' . $index . '_detail' ]
			);
			$tone = sanitize_key(
				(string) $attributes[ 'meta_' . $index . '_tone' ]
			);
			if ( '' !== $label && '' !== $value ) {
				$meta[] = array(
					'label'  => $label,
					'value'  => $value,
					'detail' => $detail,
					'tone'   => in_array( $tone, array( 'live', 'up', 'down' ), true )
						? $tone : '',
				);
			}
		}
		if ( array() === $meta ) {
			$meta = self::page_hero_live_summary( $variant );
		}

		$html = '<section class="psm-page-hero psm-page-hero--'
			. esc_attr( sanitize_html_class( $variant ) )
			. '" aria-labelledby="' . esc_attr( $id ) . '"><div class="psm-page-hero__inner ct-container">';
		if ( self::boolean_attribute( $attributes['show_breadcrumb'] ) ) {
			$html .= self::page_hero_breadcrumb(
				$title,
				(string) $attributes['breadcrumb_parent_label'],
				(string) $attributes['breadcrumb_parent_url']
			);
		}
		$html .= '<div class="psm-page-hero__layout"><div class="psm-page-hero__content"><p class="psm-page-hero__eyebrow">'
			. esc_html( sanitize_text_field( (string) $attributes['eyebrow'] ) )
			. '</p><h1 id="' . esc_attr( $id ) . '">' . esc_html( $title )
			. '</h1><p class="psm-page-hero__description">'
			. esc_html( sanitize_text_field( (string) $attributes['description'] ) )
			. '</p>';
		$html .= '<div class="psm-page-hero__actions"><a class="psm-page-hero__primary" href="'
			. esc_url( (string) $attributes['cta_url'] ) . '">'
			. '<span aria-hidden="true">↓</span>'
			. esc_html( sanitize_text_field( (string) $attributes['cta_label'] ) )
			. '</a>';
		if (
			'' !== trim( (string) $attributes['secondary_label'] )
			&& '' !== trim( (string) $attributes['secondary_url'] )
		) {
			$html .= '<a class="psm-page-hero__secondary" href="'
				. esc_url( (string) $attributes['secondary_url'] ) . '">'
				. esc_html( sanitize_text_field( (string) $attributes['secondary_label'] ) )
				. '</a>';
		}
		$html .= '</div>' . self::page_hero_tabs(
			(string) $attributes['tabs']
		) . '</div>';
		if ( array() !== $meta ) {
			$html .= '<aside class="psm-page-hero__summary" aria-label="'
				. esc_attr__( 'Thông tin cập nhật', 'power-schedule-manager' )
				. '"><dl>';
			foreach ( $meta as $item ) {
				$tone = sanitize_html_class( (string) ( $item['tone'] ?? '' ) );
				$html .= '<div' . ( '' !== $tone ? ' class="is-' . esc_attr( $tone ) . '"' : '' )
					. '><dt>' . esc_html( (string) $item['label'] ) . '</dt><dd>'
					. esc_html( (string) $item['value'] ) . '</dd>';
				if ( '' !== (string) ( $item['detail'] ?? '' ) ) {
					$html .= '<small>' . esc_html( (string) $item['detail'] ) . '</small>';
				}
				$html .= '</div>';
			}
			$html .= '</dl></aside>';
		}
		$html .= '</div></div></section>';

		return $html;
	}

	/**
	 * Defaults for the utility-page hero variants in the approved design spec.
	 *
	 * @return array<string,array<string,string>>
	 */
	private static function page_hero_presets(): array {
		return array(
			'schedule' => array(
				'eyebrow' => __( 'Lịch điện Lâm Đồng', 'power-schedule-manager' ),
				'title' => __( 'Lịch cúp điện Lâm Đồng', 'power-schedule-manager' ),
				'description' => __( 'Lịch ngừng, giảm cung cấp điện được trình bày theo khu vực và ngày áp dụng.', 'power-schedule-manager' ),
				'cta_label' => __( 'Tra cứu ngay', 'power-schedule-manager' ),
				'cta_url' => '#tra-cuu',
				'tabs' => '',
			),
			'gold' => array(
				'eyebrow' => __( 'Cập nhật thị trường vàng', 'power-schedule-manager' ),
				'title' => __( 'Giá vàng hôm nay', 'power-schedule-manager' ),
				'description' => __( 'Giá vàng trong nước và thế giới được cập nhật theo từng nguồn dữ liệu.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem bảng giá', 'power-schedule-manager' ),
				'cta_url' => '#bang-gia',
				'tabs' => 'Tổng quan|#tong-quan;Trong nước|#trong-nuoc;Thế giới|#the-gioi',
			),
			'coffee' => array(
				'eyebrow' => __( 'Thị trường cà phê', 'power-schedule-manager' ),
				'title' => __( 'Giá cà phê hôm nay', 'power-schedule-manager' ),
				'description' => __( 'Giá cà phê Lâm Đồng, trong nước và thế giới được phân tách rõ theo đơn vị.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem giá Lâm Đồng', 'power-schedule-manager' ),
				'cta_url' => '#gia-lam-dong',
				'tabs' => 'Trong nước|#trong-nuoc;Thế giới|#the-gioi;Chỉ số liên quan|#chi-so',
			),
			'lottery' => array(
				'eyebrow' => __( 'Kết quả mới nhất', 'power-schedule-manager' ),
				'title' => __( 'Kết quả xổ số hôm nay', 'power-schedule-manager' ),
				'description' => __( 'Kết quả ba miền và Vietlott được sắp xếp theo sản phẩm và kỳ quay.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua',
				'tabs' => 'Miền Bắc|#mien-bac;Miền Trung|#mien-trung;Miền Nam|#mien-nam',
			),
			'lottery_lookup' => array(
				'eyebrow' => __( 'Kho kết quả xổ số', 'power-schedule-manager' ),
				'title' => __( 'Tra cứu kết quả xổ số', 'power-schedule-manager' ),
				'description' => __( 'Chọn ngày và loại xổ số để xem lại kết quả các kỳ đã lưu.', 'power-schedule-manager' ),
				'cta_label' => __( 'Bắt đầu tra cứu', 'power-schedule-manager' ),
				'cta_url' => '#tra-cuu',
				'tabs' => '',
			),
			'lottery_north' => array(
				'eyebrow' => __( 'Xổ số truyền thống', 'power-schedule-manager' ),
				'title' => __( 'Kết quả xổ số miền Bắc', 'power-schedule-manager' ),
				'description' => __( 'Bảng kết quả miền Bắc theo ngày quay, trình bày rõ từng hạng giải.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem bảng kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-north',
				'tabs' => '',
			),
			'lottery_central' => array(
				'eyebrow' => __( 'Xổ số truyền thống', 'power-schedule-manager' ),
				'title' => __( 'Kết quả xổ số miền Trung', 'power-schedule-manager' ),
				'description' => __( 'Kết quả các đài miền Trung được nhóm theo tỉnh và hạng giải.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem bảng kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-central',
				'tabs' => '',
			),
			'lottery_south' => array(
				'eyebrow' => __( 'Xổ số truyền thống', 'power-schedule-manager' ),
				'title' => __( 'Kết quả xổ số miền Nam', 'power-schedule-manager' ),
				'description' => __( 'Kết quả các đài miền Nam được nhóm theo tỉnh và hạng giải.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem bảng kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-south',
				'tabs' => '',
			),
			'lottery_mega645' => array(
				'eyebrow' => __( 'Vietlott', 'power-schedule-manager' ),
				'title' => __( 'Kết quả Mega 6/45', 'power-schedule-manager' ),
				'description' => __( 'Dãy số trúng, kỳ quay và giá trị Jackpot Mega 6/45 mới nhất.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-mega645',
				'tabs' => '',
			),
			'lottery_power655' => array(
				'eyebrow' => __( 'Vietlott', 'power-schedule-manager' ),
				'title' => __( 'Kết quả Power 6/55', 'power-schedule-manager' ),
				'description' => __( 'Kết quả Power 6/55, Jackpot 1 và Jackpot 2 theo kỳ quay gần nhất.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-power655',
				'tabs' => '',
			),
			'lottery_max3d' => array(
				'eyebrow' => __( 'Vietlott Max 3D', 'power-schedule-manager' ),
				'title' => __( 'Kết quả Max 3D', 'power-schedule-manager' ),
				'description' => __( 'Bảng giải Max 3D, Max 3D+ và Max 3D Pro được tách đúng sản phẩm.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem bảng giải', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-max3d',
				'tabs' => 'Max 3D|#ket-qua-max3d;Max 3D+|#ket-qua-max3dplus;Max 3D Pro|#ket-qua-max3dpro',
			),
			'lottery_keno' => array(
				'eyebrow' => __( 'Vietlott Keno', 'power-schedule-manager' ),
				'title' => __( 'Kết quả Keno', 'power-schedule-manager' ),
				'description' => __( 'Hai mươi số Keno và thống kê chẵn, lẻ, lớn, nhỏ theo kỳ quay.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-keno',
				'tabs' => '',
			),
			'lottery_dientoan' => array(
				'eyebrow' => __( 'Xổ số Điện toán', 'power-schedule-manager' ),
				'title' => __( 'Kết quả xổ số Điện toán', 'power-schedule-manager' ),
				'description' => __( 'Điện toán 123, Điện toán 6x36 và Thần Tài được trình bày theo đúng từng sản phẩm.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-dientoan',
				'tabs' => 'Điện toán 123|#ket-qua-dientoan123;Điện toán 6x36|#ket-qua-dientoan6x36;Thần Tài|#ket-qua-thantai',
			),
			'lottery_dientoan123' => array(
				'eyebrow' => __( 'Xổ số Điện toán', 'power-schedule-manager' ),
				'title' => __( 'Kết quả Điện toán 123', 'power-schedule-manager' ),
				'description' => __( 'Kết quả Điện toán 123 được trình bày theo kỳ quay và từng hàng số.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-dientoan123',
				'tabs' => '',
			),
			'lottery_dientoan6x36' => array(
				'eyebrow' => __( 'Xổ số Điện toán', 'power-schedule-manager' ),
				'title' => __( 'Kết quả Điện toán 6x36', 'power-schedule-manager' ),
				'description' => __( 'Sáu số Điện toán 6x36 theo kỳ quay mới nhất đã lưu trên hệ thống.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-dientoan6x36',
				'tabs' => '',
			),
			'lottery_thantai' => array(
				'eyebrow' => __( 'Xổ số Điện toán', 'power-schedule-manager' ),
				'title' => __( 'Kết quả Thần Tài', 'power-schedule-manager' ),
				'description' => __( 'Kết quả Thần Tài theo kỳ quay gần nhất đã lưu trên hệ thống.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem kết quả', 'power-schedule-manager' ),
				'cta_url' => '#ket-qua-thantai',
				'tabs' => '',
			),
			'weather' => array(
				'eyebrow' => __( 'Dữ liệu khí tượng', 'power-schedule-manager' ),
				'title' => __( 'Thời tiết Lâm Đồng hôm nay', 'power-schedule-manager' ),
				'description' => __( 'Nhiệt độ, mưa, gió và dự báo khu vực được cập nhật từ dữ liệu khí tượng.', 'power-schedule-manager' ),
				'cta_label' => __( 'Xem dự báo', 'power-schedule-manager' ),
				'cta_url' => '#du-bao',
				'tabs' => 'Radar mưa|#weather-rain;Gió|#weather-wind;Nhiệt độ|#weather-temp;Mây|#weather-clouds',
			),
			'participate' => array(
				'eyebrow' => __( 'Nền tảng cộng đồng độc lập', 'power-schedule-manager' ),
				'title' => __( 'Hợp tác cùng Cúp Điện Lâm Đồng', 'power-schedule-manager' ),
				'description' => __( 'Góp ý dữ liệu, đề xuất tính năng hoặc kết nối những sáng kiến hữu ích cho cộng đồng.', 'power-schedule-manager' ),
				'cta_label' => __( 'Tìm hiểu hợp tác', 'power-schedule-manager' ),
				'cta_url' => '#psm-participate-paths',
				'tabs' => '',
			),
			'sponsor' => array(
				'eyebrow' => __( 'Hợp tác minh bạch', 'power-schedule-manager' ),
				'title' => __( 'Hợp tác cùng Cúp Điện Lâm Đồng', 'power-schedule-manager' ),
				'description' => __( 'Trao đổi nhu cầu nội dung, truyền thông và hợp tác phù hợp với nền tảng thông tin địa phương.', 'power-schedule-manager' ),
				'cta_label' => __( 'Gửi đề xuất hợp tác', 'power-schedule-manager' ),
				'cta_url' => '#psm-sponsor-form',
				'tabs' => '',
			),
		);
	}

	/**
	 * Return small, truthful values already stored by each utility module.
	 * No external provider request is made while rendering the hero.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function page_hero_live_summary( string $variant ): array {
		$cache_key = 'psm_hero_summary_' . sanitize_key( $variant );
		$cached = wp_cache_get( $cache_key, 'power_schedule_manager' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$summary = match ( $variant ) {
			'gold', 'coffee' => Power_Schedule_Manager_Market_Prices::hero_summary(
				$variant
			),
			'lottery', 'lottery_lookup', 'lottery_north', 'lottery_central',
			'lottery_south', 'lottery_mega645', 'lottery_power655',
			'lottery_max3d', 'lottery_keno', 'lottery_dientoan',
			'lottery_dientoan123', 'lottery_dientoan6x36',
			'lottery_thantai' =>
				Power_Schedule_Manager_Lottery::hero_summary(),
			'weather' => ( new Power_Schedule_Manager_Weather() )->hero_summary(),
			'schedule' => self::schedule_hero_summary(),
			default => array(),
		};

		wp_cache_set(
			$cache_key,
			$summary,
			'power_schedule_manager',
			MINUTE_IN_SECONDS
		);

		return $summary;
	}

	/** @return array<int,array<string,string>> */
	private static function schedule_hero_summary(): array {
		$posts = get_posts(
			array(
				'post_type'      => Power_Schedule_Manager_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'meta_value',
				'order'          => 'DESC',
				'meta_key'       => Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
				'no_found_rows'  => true,
			)
		);
		if ( array() === $posts ) {
			return array();
		}

		$date = sanitize_text_field(
			(string) get_post_meta(
				(int) $posts[0],
				Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
				true
			)
		);
		$timestamp = strtotime( $date . ' 00:00:00' );

		return array(
			array(
				'label'  => __( 'Khu vực', 'power-schedule-manager' ),
				'value'  => __( 'Lâm Đồng', 'power-schedule-manager' ),
				'detail' => __( 'Theo đơn vị điện lực', 'power-schedule-manager' ),
				'tone'   => 'live',
			),
			array(
				'label'  => __( 'Dữ liệu gần nhất', 'power-schedule-manager' ),
				'value'  => false !== $timestamp ? wp_date( 'd/m/Y', $timestamp ) : $date,
				'detail' => __( 'Lịch đã lưu trên hệ thống', 'power-schedule-manager' ),
				'tone'   => '',
			),
		);
	}

	private static function page_hero_breadcrumb(
		string $title,
		string $parent_label = '',
		string $parent_url = ''
	): string {
		$parent_label = sanitize_text_field( $parent_label );
		if ( '' !== $parent_label ) {
			$utility = '' !== trim( $parent_url )
				? '<a href="' . esc_url( $parent_url ) . '">'
					. esc_html( $parent_label ) . '</a>'
				: '<span>' . esc_html( $parent_label ) . '</span>';
		} else {
			$utility_page = get_page_by_path( 'tien-ich' );
			$utility = $utility_page instanceof WP_Post
				? '<a href="' . esc_url( get_permalink( $utility_page ) ) . '">'
					. esc_html__( 'Tiện ích', 'power-schedule-manager' ) . '</a>'
				: '<span>' . esc_html__( 'Tiện ích', 'power-schedule-manager' ) . '</span>';
		}
		$crumb_title = trim(
			(string) preg_replace( '/\s+hôm nay$/iu', '', $title )
		);

		return '<nav class="psm-page-hero__breadcrumb" aria-label="'
			. esc_attr__( 'Breadcrumb', 'power-schedule-manager' ) . '"><a href="'
			. esc_url( home_url( '/' ) ) . '">'
			. esc_html__( 'Trang chủ', 'power-schedule-manager' )
			. '</a><span aria-hidden="true">/</span>' . $utility
			. '<span aria-hidden="true">/</span><span aria-current="page">'
			. esc_html( '' !== $crumb_title ? $crumb_title : $title ) . '</span></nav>';
	}

	private static function page_hero_tabs( string $tabs ): string {
		$items = array();
		foreach ( explode( ';', $tabs ) as $tab ) {
			$parts = array_map( 'trim', explode( '|', $tab, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
				$items[] = $parts;
			}
		}
		if ( array() === $items ) {
			return '';
		}
		$html = '<nav class="psm-page-hero__tabs" aria-label="'
			. esc_attr__( 'Điều hướng nội dung', 'power-schedule-manager' ) . '">';
		foreach ( $items as $item ) {
			$html .= '<a href="' . esc_url( $item[1] ) . '">'
				. esc_html( sanitize_text_field( $item[0] ) ) . '</a>';
		}

		return $html . '</nav>';
	}

	/**
	 * Render schedule shortcode.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_schedule_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$raw_attributes = $attributes;

		$attributes = shortcode_atts(
			array(
				'date'            => 'today',
				'unit'            => '',
				'days'            => '1',
				'title'           => '',
				'show_unit'       => 'yes',
				'show_reason'     => 'yes',
				'show_status'     => 'yes',
				'show_map'        => 'yes',
				'map_mode'        => 'row_buttons',
				'show_details'    => 'yes',
				'show_disclaimer' => 'yes',
				'groups_per_page' => '0',
				'use_query'       => 'yes',
			),
			$attributes,
			self::SCHEDULE_SHORTCODE
		);

		$uses_query = self::boolean_attribute(
			$attributes['use_query']
		);

		$explicit_date_search = $uses_query
			&& '' !== self::request_value( 'psm_date' );

		if ( $uses_query ) {
			$attributes = self::apply_query_parameters(
				$attributes
			);
		}

		try {
			$range = self::date_range(
				(string) $attributes['date'],
				(int) $attributes['days']
			);

			$unit = self::resolve_public_unit(
				(string) $attributes['unit']
			);

			if (
				'' !== trim( (string) $attributes['unit'] )
				&& null === $unit
			) {
				return Power_Schedule_Manager_Renderer::empty_state(
					__(
						'Không tìm thấy khu vực điện lực công khai phù hợp.',
						'power-schedule-manager'
					),
					self::boolean_attribute(
						$attributes['show_disclaimer']
					)
				);
			}

			$unit_code = null !== $unit
				? (string) $unit['code']
				: null;

			$public_statuses = array(
				Power_Schedule_Manager_Status::SCHEDULED,
				Power_Schedule_Manager_Status::ONGOING,
			);

			$group_page = $uses_query
				? self::query_page()
				: 1;
			$groups_per_page = self::bounded_int(
				$attributes['groups_per_page'],
				0,
				50
			);
			$pre_paginated = $groups_per_page > 0;
			$total_groups = null;

			if ( $pre_paginated ) {
				$paged_result =
					Power_Schedule_Manager_Cache::remember(
						'schedule_group_page',
						array(
							'date_from' => $range['from'],
							'date_to'   => $range['to'],
							'unit_code' => $unit_code ?? 'all',
							'statuses'  => $public_statuses,
							'page'      => $group_page,
							'per_page'  => $groups_per_page,
							'include_completed' =>
								$explicit_date_search,
						),
						static fn (): array =>
							Power_Schedule_Manager_Repository::query_public_group_page(
								$range['from'],
								$range['to'],
								$unit_code,
								$public_statuses,
								$group_page,
								$groups_per_page,
								$explicit_date_search
							),
						Power_Schedule_Manager_Cache::DEFAULT_TTL
					);

				$events = isset( $paged_result['items'] )
					&& is_array( $paged_result['items'] )
						? $paged_result['items']
						: array();
				$total_groups = absint(
					$paged_result['total_groups'] ?? 0
				);
				$group_page = max(
					1,
					absint( $paged_result['page'] ?? $group_page )
				);
			} else {
				$events = Power_Schedule_Manager_Cache::remember(
					'schedule_query',
					array(
						'date_from' => $range['from'],
						'date_to'   => $range['to'],
						'unit_code' => $unit_code ?? 'all',
						'statuses'   => $public_statuses,
						'visibility' => $explicit_date_search
							? 'published_date_search'
							: 'published_active',
					),
					static fn (): array =>
						Power_Schedule_Manager_Repository::query(
							$range['from'],
							$range['to'],
							$unit_code,
							$public_statuses,
							500,
							0
						),
					Power_Schedule_Manager_Cache::DEFAULT_TTL
				);
			}

			$events = self::filter_public_units(
				$events
			);

			$events = self::hydrate_event_display_data(
				$events
			);

			self::enqueue_frontend_assets();

			$title = sanitize_text_field(
				(string) $attributes['title']
			);

			$title_was_provided = array_key_exists(
				'title',
				$raw_attributes
			);

			if (
				'' === $title
				&& ! $title_was_provided
			) {
				$title = self::default_title(
					$range,
					$unit
				);
			}

			return Power_Schedule_Manager_Renderer::schedule(
				$events,
				array(
					'title'           => $title,
					'timezone'        => null !== $unit
						? (string) $unit['timezone']
						: POWER_SCHEDULE_MANAGER_TIMEZONE,
					'show_unit'       =>
						self::boolean_attribute(
							$attributes['show_unit']
						),
					'show_reason'     =>
						self::boolean_attribute(
							$attributes['show_reason']
						),
					'show_status'     =>
						self::boolean_attribute(
							$attributes['show_status']
						),
					'show_map'        =>
						self::boolean_attribute(
							$attributes['show_map']
						),
					'map_mode'        => self::sanitize_map_mode(
						(string) $attributes['map_mode']
					),
					'show_details'    =>
						self::boolean_attribute(
							$attributes['show_details']
						),
					'show_completed'  => $explicit_date_search,
					'require_publish' => true,
					'show_disclaimer' =>
						self::boolean_attribute(
							$attributes['show_disclaimer']
						),
					'group_page'      => $group_page,
					'groups_per_page' => $groups_per_page,
					'pre_paginated'   => $pre_paginated,
					'total_groups'    => $total_groups,
				)
			);
		} catch ( InvalidArgumentException ) {
			return Power_Schedule_Manager_Renderer::empty_state(
				__(
					'Thông tin tìm kiếm lịch điện không hợp lệ.',
					'power-schedule-manager'
				),
				true
			);
		}
	}

	/**
	 * Return current public pagination page.
	 *
	 * @return int
	 */
	private static function query_page(): int {
		$page = self::request_value( 'psm_page' );

		return max( 1, absint( $page ) );
	}

	/**
	 * Bound a shortcode integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $minimum Minimum.
	 * @param int   $maximum Maximum.
	 *
	 * @return int
	 */
	private static function bounded_int(
		mixed $value,
		int $minimum,
		int $maximum
	): int {
		return min(
			$maximum,
			max( $minimum, absint( $value ) )
		);
	}

	/**
	 * Sanitize map rendering mode.
	 *
	 * @param string $mode Raw mode.
	 *
	 * @return string
	 */
	private static function sanitize_map_mode( string $mode ): string {
		$mode = sanitize_key( $mode );

		return in_array(
			$mode,
			array( 'row_buttons', 'modal_only', 'none' ),
			true
		)
			? $mode
			: 'row_buttons';
	}

	/**
	 * Render public search form.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_search_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$attributes = shortcode_atts(
			array(
				'action'      => '',
				'anchor'      => 'tra-cuu',
				'unit_search' => 'no',
				'compact'     => 'no',
				'button_text' => __(
					'Tra cứu',
					'power-schedule-manager'
				),
			),
			$attributes,
			self::SEARCH_SHORTCODE
		);

		$action = esc_url_raw(
			(string) $attributes['action']
		);

		if ( '' === $action ) {
			$archive = get_post_type_archive_link(
				Power_Schedule_Manager_Post_Type::POST_TYPE
			);

			$action = is_string( $archive )
				? $archive
				: home_url( '/' );
		}

		$selected_unit = self::request_value(
			'psm_unit'
		);

		$selected_date = self::request_value(
			'psm_date'
		);

		if ( '' === $selected_date ) {
			$selected_date = wp_date(
				'Y-m-d',
				null,
				new DateTimeZone(
					POWER_SCHEDULE_MANAGER_TIMEZONE
				)
			);
		}

		$units = Power_Schedule_Manager_Cache::remember(
			'public_units',
			array( 'list' => 1 ),
			static fn (): array =>
				Power_Schedule_Manager_Units::all( true ),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);

		self::enqueue_frontend_assets();

		$output = Power_Schedule_Manager_Template_Loader::render_part(
			'search-form',
			array(
				'units'         => $units,
				'action'        => $action,
				'button_text'   => sanitize_text_field(
					(string) $attributes['button_text']
				),
				'selected_unit' => $selected_unit,
				'selected_date' => $selected_date,
				'unit_search'   => self::boolean_attribute(
					$attributes['unit_search']
				),
				'compact'       => self::boolean_attribute(
					$attributes['compact']
				),
			)
		);
		$anchor = sanitize_title( (string) $attributes['anchor'] );

		return '' !== $anchor
			? '<div id="' . esc_attr( $anchor )
				. '" class="psm-shortcode-anchor">' . $output . '</div>'
			: $output;
	}

	/**
	 * Render reusable public electricity-area navigation.
	 *
	 * Example:
	 * [power_schedule_areas region="lam-dong" columns="4"
	 * title="Xem lịch điện tại Lâm Đồng theo khu vực hôm nay"
	 * description="Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới."
	 * theme="dark"]
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_areas_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$attributes = shortcode_atts(
			array(
				'region'      => '',
				'title'       => __(
					'Xem lịch điện tại Lâm Đồng theo khu vực hôm nay',
					'power-schedule-manager'
				),
				'description' => __(
					'Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới.',
					'power-schedule-manager'
				),
				'columns'     => '4',
				'limit'       => '0',
				'theme'       => 'light',
				'show_icon'   => 'yes',
				'show_code'   => 'no',
				'link_to'     => 'schedule',
				'units'       => '',
				'exclude'     => '',
				'sort'        => 'default',
				'order'       => 'asc',
				'label'       => 'short',
				'initial'     => '0',
			),
			$attributes,
			self::AREAS_SHORTCODE
		);

		$region_key = self::normalize_region_key(
			(string) $attributes['region']
		);
		$columns = self::bounded_int(
			$attributes['columns'],
			1,
			6
		);
		$limit = self::bounded_int(
			$attributes['limit'],
			0,
			100
		);
		$initial = self::bounded_int(
			$attributes['initial'],
			0,
			100
		);
		$theme = sanitize_key(
			(string) $attributes['theme']
		);
		$link_to = sanitize_key(
			(string) $attributes['link_to']
		);
		$sort = sanitize_key(
			(string) $attributes['sort']
		);
		$order = sanitize_key(
			(string) $attributes['order']
		);
		$label_mode = sanitize_key(
			(string) $attributes['label']
		);
		$included_codes = self::unit_code_list(
			(string) $attributes['units']
		);
		$excluded_codes = self::unit_code_list(
			(string) $attributes['exclude']
		);

		if ( ! in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$theme = 'light';
		}

		if ( ! in_array( $link_to, array( 'area', 'schedule' ), true ) ) {
			$link_to = 'schedule';
		}

		if ( ! in_array( $sort, array( 'default', 'name', 'code' ), true ) ) {
			$sort = 'default';
		}

		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'asc';
		}

		if ( ! in_array( $label_mode, array( 'short', 'full' ), true ) ) {
			$label_mode = 'short';
		}

		$units = Power_Schedule_Manager_Cache::remember(
			'public_area_navigation',
			array(
				'region' => '' !== $region_key ? $region_key : 'all',
			),
			static function () use ( $region_key ): array {
				$public_units = Power_Schedule_Manager_Units::all( true );

				if ( '' === $region_key ) {
					return $public_units;
				}

				return array_values(
					array_filter(
						$public_units,
						static fn ( array $unit ): bool =>
							self::normalize_region_key(
								(string) ( $unit['region'] ?? '' )
							) === $region_key
					)
				);
			},
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);

		if ( array() !== $included_codes ) {
			$units_by_code = array();

			foreach ( $units as $unit ) {
				$unit_code = Power_Schedule_Manager_Units::sanitize_code(
					is_array( $unit ) ? ( $unit['code'] ?? '' ) : ''
				);

				if ( '' !== $unit_code ) {
					$units_by_code[ $unit_code ] = $unit;
				}
			}

			$units = array_values(
				array_filter(
					array_map(
						static fn ( string $code ): ?array =>
							isset( $units_by_code[ $code ] )
							&& is_array( $units_by_code[ $code ] )
								? $units_by_code[ $code ]
								: null,
						$included_codes
					)
				)
			);
		}

		if ( array() !== $excluded_codes ) {
			$units = array_values(
				array_filter(
					$units,
					static fn ( array $unit ): bool =>
						! in_array(
							Power_Schedule_Manager_Units::sanitize_code(
								$unit['code'] ?? ''
							),
							$excluded_codes,
							true
						)
				)
			);
		}

		if ( 'default' !== $sort ) {
			usort(
				$units,
				static function ( array $left, array $right ) use ( $sort, $order ): int {
					$left_value = 'code' === $sort
						? (string) ( $left['code'] ?? '' )
						: (string) ( $left['name'] ?? '' );
					$right_value = 'code' === $sort
						? (string) ( $right['code'] ?? '' )
						: (string) ( $right['name'] ?? '' );
					$result = strnatcasecmp(
						remove_accents( $left_value ),
						remove_accents( $right_value )
					);

					return 'desc' === $order ? -$result : $result;
				}
			);
		} elseif ( 'desc' === $order ) {
			$units = array_reverse( $units );
		}

		if ( $limit > 0 ) {
			$units = array_slice( $units, 0, $limit );
		}

		$items = array();
		$schedule_archive = get_post_type_archive_link(
			Power_Schedule_Manager_Post_Type::POST_TYPE
		);

		foreach ( $units as $unit ) {
			if ( ! is_array( $unit ) ) {
				continue;
			}

			$code = Power_Schedule_Manager_Units::sanitize_code(
				$unit['code'] ?? ''
			);
			$name = sanitize_text_field(
				(string) ( $unit['name'] ?? '' )
			);

			if ( '' === $code || '' === $name ) {
				continue;
			}

			$url = '';

			if ( 'area' === $link_to ) {
				$term_id =
					Power_Schedule_Manager_Units::find_term_id_by_unit_code(
						$code
					);

				if ( null !== $term_id ) {
					$term_url = get_term_link(
						$term_id,
						Power_Schedule_Manager_Taxonomy::TAXONOMY
					);

					if ( ! is_wp_error( $term_url ) ) {
						$url = $term_url;
					}
				}
			}

			if (
				'' === $url
				&& is_string( $schedule_archive )
				&& '' !== $schedule_archive
			) {
				$url = add_query_arg(
					array(
						'psm_unit' => $code,
					),
					$schedule_archive
				);
			}

			if ( '' === $url ) {
				continue;
			}

			$label = 'full' === $label_mode
				? $name
				: preg_replace(
					'/^\s*Điện\s+lực\s+/iu',
					'',
					$name
				);

			$items[] = array(
				'code'  => $code,
				'label' => is_string( $label ) && '' !== trim( $label )
					? trim( $label )
					: $name,
				'name'  => $name,
				'url'   => $url,
			);
		}

		if ( array() === $items ) {
			return '';
		}

		self::enqueue_frontend_assets();

		return Power_Schedule_Manager_Template_Loader::render_part(
			'area-links',
			array(
				'items'       => $items,
				'title'       => sanitize_text_field(
					(string) $attributes['title']
				),
				'description' => sanitize_text_field(
					(string) $attributes['description']
				),
				'columns'     => $columns,
				'theme'       => $theme,
				'show_icon'   => self::boolean_attribute(
					$attributes['show_icon']
				),
				'show_code'   => self::boolean_attribute(
					$attributes['show_code']
				),
				'initial'     => $initial,
			)
		);
	}

	/**
	 * Render the configurable homepage lookup hub.
	 *
	 * The optional hero provides the page H1. The theme page title should be
	 * hidden when this composite shortcode is used on the front page.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_home_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$settings = is_array( $settings ) ? $settings : array();
		$configured_hero_title = (string) (
			$settings['home_hero_title'] ?? ''
		);
		$configured_hero_description = (string) (
			$settings['home_hero_description'] ?? ''
		);

		if (
			in_array(
				$configured_hero_title,
				array(
					'',
					'Chủ động theo dõi lịch cúp điện tại Lâm Đồng',
					'Chủ động theo dõi lịch điện tại Lâm Đồng mới nhất',
					'Chủ động theo dõi lịch điện mới nhất tại Lâm Đồng',
					'Chủ động theo dõi lịch điện tại Lâm Đồng',
					'Hôm nay khu vực của bạn có cúp điện không?',
					'Tra cứu lịch cúp điện Lâm Đồng',
				),
				true
			)
		) {
			$configured_hero_title = __(
				'Cúp Điện Lâm Đồng',
				'power-schedule-manager'
			);
		}

		if (
			''
			=== $configured_hero_description
			|| 'Tra cứu theo ngày và khu vực để chuẩn bị công việc, sinh hoạt và bảo vệ thiết bị điện.'
			=== $configured_hero_description
			|| 'Chọn ngày và đơn vị điện lực để kiểm tra lịch cúp điện chỉ trong vài giây.'
			=== $configured_hero_description
			|| 'Tra cứu lịch điện theo ngày và khu vực chỉ trong vài giây.'
			=== $configured_hero_description
		) {
			$configured_hero_description = __(
				'Tin tức, việc làm, thời tiết, giá nông sản và tiện ích địa phương được cập nhật mỗi ngày.',
				'power-schedule-manager'
			);
		}
		$configured_seo_heading = (string) (
			$settings['home_seo_heading'] ?? ''
		);
		if (
			''
			=== $configured_seo_heading
			|| 'Thông tin lịch điện tại Lâm Đồng dành cho cộng đồng'
			=== $configured_seo_heading
			|| 'Cúp Điện Lâm Đồng — thông tin lịch điện cộng đồng'
			=== $configured_seo_heading
		) {
			$configured_seo_heading = __(
				'Cúp Điện Lâm Đồng — cổng thông tin và tiện ích địa phương',
				'power-schedule-manager'
			);
		}
		$configured_seo_intro = (string) (
			$settings['home_seo_intro'] ?? ''
		);
		if (
			''
			=== $configured_seo_intro
			|| 'Website tổng hợp lịch cúp điện đã được công bố theo từng ngày và đơn vị điện lực tại Lâm Đồng.'
			=== $configured_seo_intro
			|| 'Website tổng hợp lịch cúp điện đã được công bố theo từng ngày và đơn vị điện lực tại Lâm Đồng. Người dân, hộ kinh doanh và doanh nghiệp có thể tra cứu khung giờ, khu vực ảnh hưởng và lý do dự kiến để chủ động sắp xếp sinh hoạt, công việc và bảo vệ thiết bị.'
			=== $configured_seo_intro
		) {
			$configured_seo_intro = __(
				'Cập nhật tin tức, việc làm, thời tiết, giá nông sản, kết quả xổ số, du lịch và lịch điện tại Lâm Đồng trong một bố cục thống nhất, dễ đọc trên điện thoại lẫn máy tính.',
				'power-schedule-manager'
			);
		}
		$configured_seo_extra = (string) (
			$settings['home_seo_extra'] ?? ''
		);
		if (
			''
			=== $configured_seo_extra
			|| 'Dữ liệu trên website mang tính thông tin và có thể được đơn vị điện lực điều chỉnh theo tình hình vận hành thực tế. Hãy kiểm tra lại lịch gần thời điểm dự kiến và đối chiếu thông báo của đơn vị điện lực khi cần quyết định quan trọng.'
			=== $configured_seo_extra
		) {
			$configured_seo_extra = __(
				'Cúp Điện Lâm Đồng là nền tảng thông tin cộng đồng độc lập. Mỗi chuyên mục được tổ chức theo nhu cầu hằng ngày và cần ghi rõ nguồn khi sử dụng dữ liệu từ đơn vị khác. Riêng lịch điện được tổng hợp từ thông tin đã công bố của EVN và đơn vị điện lực, không phải xác nhận cấp điện theo thời gian thực.',
				'power-schedule-manager'
			);
		}
		$configured_area_title = (string) (
			$settings['home_area_title'] ?? ''
		);
		if (
			''
			=== $configured_area_title
			|| 'Xem lịch điện tại Lâm Đồng theo khu vực hôm nay'
			=== $configured_area_title
		) {
			$configured_area_title = __(
				'Lịch điện theo khu vực tại Lâm Đồng hôm nay',
				'power-schedule-manager'
			);
		}

		$attributes = shortcode_atts(
			array(
				'show_hero'       => ! empty(
					$settings['home_show_hero'] ?? true
				) ? 'yes' : 'no',
				'show_search'     => ! empty(
					$settings['home_show_search'] ?? true
				) ? 'yes' : 'no',
				'show_alert'      => ! empty(
					$settings['home_show_alert'] ?? true
				) ? 'yes' : 'no',
				'show_days'       => ! empty(
					$settings['home_show_days'] ?? true
				) ? 'yes' : 'no',
				'show_areas'      => ! empty(
					$settings['home_show_areas'] ?? true
				) ? 'yes' : 'no',
				'show_next'       => ! empty(
					$settings['home_show_next'] ?? true
				) ? 'yes' : 'no',
				'show_recent'     => ! empty(
					$settings['home_show_recent'] ?? true
				) ? 'yes' : 'no',
				'show_weather'    => 'yes',
				'show_content'    => ! empty(
					$settings['home_show_content'] ?? true
				) ? 'yes' : 'no',
				'hero_title'      => $configured_hero_title,
				'hero_description' => $configured_hero_description,
				'seo_heading'      => $configured_seo_heading,
				'seo_intro'        => $configured_seo_intro,
				'seo_extra'        => $configured_seo_extra,
				'days'            => (string) (
					$settings['home_days'] ?? 31
				),
				'recent_limit'    => (string) (
					$settings['home_recent_limit'] ?? 3
				),
				'region'          => (string) (
					$settings['home_area_region'] ?? 'lam-dong'
				),
				'columns'         => (string) (
					$settings['home_area_columns'] ?? 4
				),
				'area_initial'    => (string) (
					$settings['home_area_initial'] ?? 8
				),
				'theme'           => (string) (
					$settings['home_area_theme'] ?? 'light'
				),
				'area_title'      => $configured_area_title,
				'area_description' => (string) (
					$settings['home_area_description']
					?? __(
						'Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới.',
						'power-schedule-manager'
					)
				),
			),
			$attributes,
			self::HOME_SHORTCODE
		);

		/*
		 * The settings screen is the source of truth for editorial content.
		 * Older pages often keep attributes in the shortcode, which otherwise
		 * silently override newly saved headings and descriptions.
		 */
		$setting_overrides = array(
			'hero_title'       => 'home_hero_title',
			'hero_description' => 'home_hero_description',
			'seo_heading'      => 'home_seo_heading',
			'seo_intro'        => 'home_seo_intro',
			'seo_extra'        => 'home_seo_extra',
			'area_title'       => 'home_area_title',
			'area_description' => 'home_area_description',
		);
		foreach ( $setting_overrides as $attribute => $setting_key ) {
			if (
				isset( $settings[ $setting_key ] )
				&& is_string( $settings[ $setting_key ] )
				&& '' !== trim( $settings[ $setting_key ] )
			) {
				$attributes[ $attribute ] = $settings[ $setting_key ];
			}
		}

		$theme = sanitize_key( (string) $attributes['theme'] );

		if ( ! in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$theme = 'light';
		}

		$sections = array(
			'hero'   => '',
			'portal' => '',
			'search' => '',
			'ad_top' => '',
			'schedule_summary' => '',
			'days'   => '',
			'areas'  => '',
			'weather' => '',
			'ad_bottom' => '',
			'content' => '',
		);
		$show_search = self::boolean_attribute(
			$attributes['show_search']
		);
		$hero_search = $show_search
			? $this->render_search_shortcode(
				array(
					'button_text' => __(
						'Tra cứu ngay',
						'power-schedule-manager'
					),
					'unit_search' => 'yes',
					'compact'     => 'yes',
				)
			)
			: '';
		$hero_summary = Power_Schedule_Manager_Cache::remember(
			'home_hero_summary',
			array( 'scope' => 'public' ),
			static fn (): array =>
				Power_Schedule_Manager_Repository::public_home_summary(),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);
		$hero_updated = '';

		if ( '' !== (string) ( $hero_summary['updated_at_utc'] ?? '' ) ) {
			try {
				$hero_updated =
					Power_Schedule_Manager_Validator::parse_utc_datetime(
						$hero_summary['updated_at_utc'],
						'updated_at_utc'
					)
					->setTimezone(
						new DateTimeZone(
							POWER_SCHEDULE_MANAGER_TIMEZONE
						)
					)
					->format( 'H:i d/m/Y' );
			} catch ( InvalidArgumentException ) {
				$hero_updated = '';
			}
		}

		if ( self::boolean_attribute( $attributes['show_hero'] ) ) {
			$sections['hero'] =
				Power_Schedule_Manager_Template_Loader::render_part(
					'home-hero',
					array(
						'title'       => sanitize_text_field(
							(string) $attributes['hero_title']
						),
						'description' => sanitize_text_field(
							(string) $attributes['hero_description']
						),
						'archive_url' => self::public_schedule_url(),
						'search'      => $hero_search,
						'summary'     => $hero_summary,
						'updated'     => $hero_updated,
					)
				);
		}

		$sections['portal'] = $this->render_home_portal(
			is_array( $hero_summary ) ? $hero_summary : array()
		);

		if (
			$show_search
			&& ! self::boolean_attribute( $attributes['show_hero'] )
		) {
			$sections['search'] = $hero_search;
		}

		$ads_enabled = ! array_key_exists( 'ads_enabled', $settings )
			|| ! empty( $settings['ads_enabled'] );
		$sections['ad_top'] = $ads_enabled
			? self::render_advertising_section(
				(string) ( $settings['home_top_banner_ad'] ?? '' ),
				'home-top'
			)
			: '';

		if ( self::boolean_attribute( $attributes['show_days'] ) ) {
			$sections['days'] = $this->render_days_shortcode(
				array(
					'days'       => (string) self::bounded_int(
						$attributes['days'],
						8,
						31
					),
					'limit'      => '8',
					'title'      => __(
						'Những ngày sắp tới có lịch cúp điện',
						'power-schedule-manager'
					),
					'show_count' => 'yes',
				)
			);
		}

		if ( self::boolean_attribute( $attributes['show_areas'] ) ) {
			$sections['areas'] = $this->render_areas_shortcode(
				array(
					'region'      => sanitize_key(
						(string) $attributes['region']
					),
					'title'       => sanitize_text_field(
						(string) $attributes['area_title']
					),
					'description' => sanitize_text_field(
						(string) $attributes['area_description']
					),
					'columns'     => (string) self::bounded_int(
						$attributes['columns'],
						1,
						6
					),
					'theme'       => $theme,
					'link_to'     => 'schedule',
					'initial'     => (string) self::bounded_int(
						$attributes['area_initial'],
						0,
						8
					),
				)
			);
		}

		if (
			self::boolean_attribute( $attributes['show_alert'] )
			|| self::boolean_attribute( $attributes['show_next'] )
			|| self::boolean_attribute( $attributes['show_recent'] )
		) {
			$summary_rows = Power_Schedule_Manager_Cache::remember(
				'home_schedule_summary_rows',
				array( 'scope' => 'public' ),
				static function (): array {
					$rows = array_merge(
						Power_Schedule_Manager_Repository::query_public_upcoming( null, 8, 'next' ),
						Power_Schedule_Manager_Repository::query_public_upcoming( null, 8, 'updated' )
					);
					$unique = array();
					foreach ( $rows as $row ) {
						$key = (string) ( $row['id'] ?? '' );
						if ( '' !== $key && ! isset( $unique[ $key ] ) ) {
							$unique[ $key ] = $row;
						}
					}

					return array_slice( array_values( $unique ), 0, 8 );
				},
				Power_Schedule_Manager_Cache::DEFAULT_TTL
			);
			$sections['schedule_summary'] = Power_Schedule_Manager_Renderer::schedule(
				self::hydrate_event_display_data( $summary_rows ),
				array(
					'title'        => __( 'Lịch cúp điện mới nhất', 'power-schedule-manager' ),
					'caption'      => __( 'Lịch hôm nay, lịch gần nhất và các lịch vừa cập nhật', 'power-schedule-manager' ),
					'show_map'     => false,
					'show_reason'  => true,
					'show_details' => true,
				)
			);
		}

		if ( self::boolean_attribute( $attributes['show_weather'] ) ) {
			$sections['weather'] = do_shortcode(
				'[power_schedule_weather_forecast'
				. ' title="Thời tiết Lâm Đồng hôm nay"'
				. ' area="Lâm Đồng" show_content="no"]'
			);
		}

		if ( self::boolean_attribute( $attributes['show_content'] ) ) {
			$sections['content'] =
				Power_Schedule_Manager_Template_Loader::render_part(
					'home-guidance',
					array(
						'archive_url' => self::public_schedule_url(),
						'heading'     => sanitize_text_field(
							(string) $attributes['seo_heading']
						),
						'intro'       => sanitize_textarea_field(
							(string) $attributes['seo_intro']
						),
						'extra'       => sanitize_textarea_field(
							(string) $attributes['seo_extra']
						),
					)
				);
		}

		$sections['ad_bottom'] = $ads_enabled
			? self::render_advertising_section(
				(string) ( $settings['home_bottom_banner_ad'] ?? '' ),
				'home-bottom'
			)
			: '';

		$sections = array_filter(
			$sections,
			static fn ( string $section ): bool => '' !== trim( $section )
		);

		if ( array() === $sections ) {
			return '';
		}

		self::enqueue_frontend_assets();

		return Power_Schedule_Manager_Template_Loader::render_part(
			'home-hub',
			array(
				'sections' => $sections,
			)
		);
	}

	/**
	 * Render the province-wide portal navigation and editorial entry points.
	 *
	 * @param array<string,mixed> $power_summary Current electricity summary.
	 */
	private function render_home_portal( array $power_summary ): string {
		$editorial_groups = array(
			'news' => array(
				'title' => __( 'Tin tức Lâm Đồng', 'power-schedule-manager' ),
				'slug'  => 'tin-tuc',
				'url'   => home_url( '/tin-tuc/' ),
			),
			'jobs' => array(
				'title' => __( 'Việc làm mới', 'power-schedule-manager' ),
				'slug'  => 'viec-lam',
				'url'   => home_url( '/viec-lam/' ),
			),
			'travel' => array(
				'title' => __( 'Khám phá Lâm Đồng', 'power-schedule-manager' ),
				'slug'  => 'du-lich',
				'url'   => home_url( '/du-lich/' ),
			),
		);
		foreach ( $editorial_groups as $group_key => $group ) {
			$query = new WP_Query(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 4,
					'category_name'       => $group['slug'],
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);
			$posts = array();
			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$thumbnail_id = get_post_thumbnail_id( $post );
				$thumbnail = $thumbnail_id > 0
					? wp_get_attachment_image_src( $thumbnail_id, 'medium_large' )
					: false;
				$posts[] = array(
					'title'     => get_the_title( $post ),
					'url'       => get_permalink( $post ),
					'date'      => get_the_date( 'd/m/Y', $post ),
					'excerpt'   => wp_trim_words(
						get_the_excerpt( $post ),
						18
					),
					'thumbnail' => is_array( $thumbnail ) ? (string) $thumbnail[0] : '',
					'thumbnail_width' => is_array( $thumbnail ) ? absint( $thumbnail[1] ) : 0,
					'thumbnail_height' => is_array( $thumbnail ) ? absint( $thumbnail[2] ) : 0,
					'thumbnail_srcset' => $thumbnail_id > 0
						? (string) wp_get_attachment_image_srcset( $thumbnail_id, 'medium_large' )
						: '',
				);
			}
			wp_reset_postdata();
			$editorial_groups[ $group_key ]['posts'] = $posts;
		}

		return Power_Schedule_Manager_Template_Loader::render_part(
			'home-portal',
			array(
				'power_summary' => $power_summary,
				'groups'        => $editorial_groups,
				'links'         => array(
					'power'  => self::public_schedule_url(),
					'jobs'   => home_url( '/viec-lam/' ),
					'coffee' => home_url( '/gia-ca-phe-hom-nay/' ),
					'weather' => home_url( '/thoi-tiet-lam-dong/' ),
					'news'   => home_url( '/tin-tuc/' ),
					'lottery' => home_url( '/ket-qua-xo-so-hom-nay/' ),
					'travel' => home_url( '/du-lich/' ),
				),
			)
		);
	}

	/**
	 * Render the nearest ongoing or upcoming public schedule.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_next_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$attributes = shortcode_atts(
			array(
				'unit'        => '',
				'title'       => __(
					'Lịch cúp điện gần nhất',
					'power-schedule-manager'
				),
				'show_reason' => 'yes',
				'show_map'    => 'no',
				'featured'    => 'no',
				'limit'       => '1',
			),
			$attributes,
			self::NEXT_SHORTCODE
		);

		$context = self::public_unit_context(
			(string) $attributes['unit']
		);

		if ( null === $context ) {
			return self::compact_empty();
		}

		$limit = self::bounded_int( $attributes['limit'], 1, 5 );

		$rows = Power_Schedule_Manager_Cache::remember(
			'shortcode_next',
			array(
				'unit'  => $context['unit_code'] ?? 'all',
				'limit' => $limit,
			),
			static fn (): array =>
				Power_Schedule_Manager_Repository::query_public_upcoming(
					$context['unit_code'],
					$limit,
					'next'
				),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);
		$events = Power_Schedule_Manager_Renderer::prepare_public_events(
			self::hydrate_event_display_data( $rows ),
			$context['timezone']
		);

		self::enqueue_frontend_assets();

		return Power_Schedule_Manager_Template_Loader::render_part(
			'next-schedule',
			array(
				'event'       => $events[0] ?? null,
				'events'      => $events,
				'title'       => sanitize_text_field(
					(string) $attributes['title']
				),
				'show_reason' => self::boolean_attribute(
					$attributes['show_reason']
				),
				'show_map'    => self::boolean_attribute(
					$attributes['show_map']
				),
				'featured'    => self::boolean_attribute(
					$attributes['featured']
				),
				'archive_url' => self::public_schedule_url(
					$context['unit_code']
				),
			)
		);
	}

	/**
	 * Render a compact status alert for the selected public scope.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_alert_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$attributes = shortcode_atts(
			array(
				'unit'  => '',
				'title' => __(
					'Thông tin lịch cúp điện',
					'power-schedule-manager'
				),
			),
			$attributes,
			self::ALERT_SHORTCODE
		);
		$context = self::public_unit_context(
			(string) $attributes['unit']
		);

		if ( null === $context ) {
			return self::compact_empty();
		}

		$summary = Power_Schedule_Manager_Cache::remember(
			'shortcode_alert',
			array( 'unit' => $context['unit_code'] ?? 'all' ),
			static fn (): array =>
				Power_Schedule_Manager_Repository::public_status_summary(
					$context['unit_code']
				),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);
		$next_display = '';
		$next_iso = '';
		$upcoming_events = array();
		$today = wp_date(
			'Y-m-d',
			null,
			new DateTimeZone( $context['timezone'] )
		);

		if ( '' !== (string) ( $summary['next_start_at_utc'] ?? '' ) ) {
			try {
				$next = Power_Schedule_Manager_Validator::parse_utc_datetime(
					$summary['next_start_at_utc'],
					'next_start_at_utc'
				)->setTimezone(
					new DateTimeZone( $context['timezone'] )
				);
				$next_display = $next->format( 'H:i d/m/Y' );
				$next_iso = $next->format( DATE_ATOM );
			} catch ( InvalidArgumentException ) {
				$next_display = '';
			}
		}

		if (
			absint( $summary['upcoming'] ?? 0 ) > 0
			|| absint( $summary['ongoing'] ?? 0 ) > 0
		) {
			$upcoming_rows = Power_Schedule_Manager_Cache::remember(
				'shortcode_alert_upcoming',
				array( 'unit' => $context['unit_code'] ?? 'all' ),
				static fn (): array =>
					Power_Schedule_Manager_Repository::query_public_upcoming(
						$context['unit_code'],
						20,
						'next'
					),
				Power_Schedule_Manager_Cache::DEFAULT_TTL
			);
			$prepared_events =
				Power_Schedule_Manager_Renderer::prepare_public_events(
					self::hydrate_event_display_data( $upcoming_rows ),
					$context['timezone']
				);
			$upcoming_events = array_values(
				array_filter(
					$prepared_events,
					static fn ( array $event ): bool =>
						$today === (string) ( $event['local_date'] ?? '' )
				)
			);
			$upcoming_events = array_slice( $upcoming_events, 0, 4 );

			if ( array() === $upcoming_events ) {
				$upcoming_events = array_slice( $prepared_events, 0, 3 );
			}
		}

		self::enqueue_frontend_assets();

		return Power_Schedule_Manager_Template_Loader::render_part(
			'schedule-alert',
			array(
				'title'        => sanitize_text_field(
					(string) $attributes['title']
				),
				'unit_name'    => $context['unit_name'],
				'ongoing'      => absint( $summary['ongoing'] ?? 0 ),
				'upcoming'     => absint( $summary['upcoming'] ?? 0 ),
				'next_display' => $next_display,
				'next_iso'     => $next_iso,
				'upcoming_events' => $upcoming_events,
				'event_list_label' => array() !== $upcoming_events
					&& $today === (string) (
						$upcoming_events[0]['local_date'] ?? ''
					)
						? __(
							'Các khu vực cần chú ý trong hôm nay',
							'power-schedule-manager'
						)
						: __(
							'Các khu vực có lịch gần nhất',
							'power-schedule-manager'
						),
				'archive_url'  => self::public_schedule_url(
					$context['unit_code']
				),
			)
		);
	}

	/**
	 * Render links for upcoming days that contain public schedules.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_days_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$attributes = shortcode_atts(
			array(
				'unit'       => '',
				'title'      => __(
					'Chọn ngày có lịch cúp điện',
					'power-schedule-manager'
				),
				'days'       => '7',
				'limit'      => '31',
				'show_count' => 'yes',
			),
			$attributes,
			self::DAYS_SHORTCODE
		);
		$context = self::public_unit_context(
			(string) $attributes['unit']
		);

		if ( null === $context ) {
			return self::compact_empty();
		}

		$days = self::bounded_int( $attributes['days'], 1, 31 );
		$limit = self::bounded_int( $attributes['limit'], 1, 31 );
		$zone = new DateTimeZone( $context['timezone'] );
		$today = new DateTimeImmutable( 'today', $zone );
		$date_from = $today->format( 'Y-m-d' );
		$date_to = $today
			->modify( '+' . ( $days - 1 ) . ' days' )
			->format( 'Y-m-d' );
		$rows = Power_Schedule_Manager_Cache::remember(
			'shortcode_days',
			array(
				'unit' => $context['unit_code'] ?? 'all',
				'from' => $date_from,
				'to'   => $date_to,
			),
			static fn (): array =>
				Power_Schedule_Manager_Repository::public_day_counts(
					$date_from,
					$date_to,
					$context['unit_code']
				),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);
		$items = array();

		foreach ( $rows as $row ) {
			$date_value = (string) ( $row['local_date'] ?? '' );
			$date = DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				$date_value,
				$zone
			);

			if ( false === $date ) {
				continue;
			}

			$offset = (int) $today->diff( $date )->format( '%r%a' );
			$weekday_labels = array(
				1 => __( 'Thứ Hai', 'power-schedule-manager' ),
				2 => __( 'Thứ Ba', 'power-schedule-manager' ),
				3 => __( 'Thứ Tư', 'power-schedule-manager' ),
				4 => __( 'Thứ Năm', 'power-schedule-manager' ),
				5 => __( 'Thứ Sáu', 'power-schedule-manager' ),
				6 => __( 'Thứ Bảy', 'power-schedule-manager' ),
				7 => __( 'Chủ Nhật', 'power-schedule-manager' ),
			);
			$label = match ( $offset ) {
				0 => __( 'Hôm nay', 'power-schedule-manager' ),
				1 => __( 'Ngày mai', 'power-schedule-manager' ),
				default => $weekday_labels[
					(int) $date->format( 'N' )
				],
			};
			$items[] = array(
				'label' => $label,
				'date'  => $date->format( 'd/m/Y' ),
				'iso'   => $date_value,
				'count' => absint( $row['event_count'] ?? 0 ),
				'url'   => self::public_schedule_url(
					$context['unit_code'],
					$date_value
				),
			);

			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		self::enqueue_frontend_assets();

		return Power_Schedule_Manager_Template_Loader::render_part(
			'schedule-days',
			array(
				'title'      => sanitize_text_field(
					(string) $attributes['title']
				),
				'items'      => $items,
				'show_count' => self::boolean_attribute(
					$attributes['show_count']
				),
			)
		);
	}

	/**
	 * Render recently updated ongoing or upcoming schedules.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_recent_updates_shortcode(
		array|string $attributes = array()
	): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$attributes = shortcode_atts(
			array(
				'unit'      => '',
				'title'     => __(
					'Lịch vừa cập nhật',
					'power-schedule-manager'
				),
				'limit'     => '5',
				'show_area' => 'yes',
			),
			$attributes,
			self::RECENT_UPDATES_SHORTCODE
		);
		$context = self::public_unit_context(
			(string) $attributes['unit']
		);

		if ( null === $context ) {
			return self::compact_empty();
		}

		$limit = self::bounded_int( $attributes['limit'], 1, 20 );
		$rows = Power_Schedule_Manager_Cache::remember(
			'shortcode_recent_updates',
			array(
				'unit'  => $context['unit_code'] ?? 'all',
				'limit' => $limit,
			),
			static fn (): array =>
				Power_Schedule_Manager_Repository::query_public_upcoming(
					$context['unit_code'],
					$limit,
					'updated'
				),
			Power_Schedule_Manager_Cache::DEFAULT_TTL
		);
		$events = Power_Schedule_Manager_Renderer::prepare_public_events(
			self::hydrate_event_display_data( $rows ),
			$context['timezone']
		);

		self::enqueue_frontend_assets();

		return Power_Schedule_Manager_Template_Loader::render_part(
			'recent-updates',
			array(
				'title'       => sanitize_text_field(
					(string) $attributes['title']
				),
				'events'      => $events,
				'show_area'   => self::boolean_attribute(
					$attributes['show_area']
				),
				'archive_url' => self::public_schedule_url(
					$context['unit_code']
				),
			)
		);
	}

	/**
	 * Resolve and validate an optional public unit shortcode scope.
	 *
	 * Null means the supplied identifier was invalid. The empty identifier
	 * returns an all-unit context.
	 *
	 * @param string $identifier Unit code or slug.
	 * @return array{unit_code:?string,unit_name:string,timezone:string}|null
	 */
	private static function public_unit_context(
		string $identifier
	): ?array {
		$identifier = trim( $identifier );

		if ( '' === $identifier ) {
			return array(
				'unit_code' => null,
				'unit_name' => '',
				'timezone'  => POWER_SCHEDULE_MANAGER_TIMEZONE,
			);
		}

		$unit = self::resolve_public_unit( $identifier );

		if ( null === $unit ) {
			return null;
		}

		return array(
			'unit_code' => (string) $unit['code'],
			'unit_name' => (string) $unit['name'],
			'timezone'  => (string) $unit['timezone'],
		);
	}

	/**
	 * Build the schedule archive URL for a unit and optional date.
	 *
	 * @param string|null $unit_code Unit code.
	 * @param string|null $date Local date.
	 * @return string
	 */
	private static function public_schedule_url(
		?string $unit_code = null,
		?string $date = null
	): string {
		$archive = get_post_type_archive_link(
			Power_Schedule_Manager_Post_Type::POST_TYPE
		);
		$url = is_string( $archive ) && '' !== $archive
			? $archive
			: home_url( '/lich-cup-dien/' );
		$query = array();

		if ( null !== $unit_code && '' !== $unit_code ) {
			$query['psm_unit'] = $unit_code;
		}

		if ( null !== $date && '' !== $date ) {
			$query['psm_date'] = $date;
		}

		return array() === $query
			? $url
			: add_query_arg( $query, $url );
	}

	/**
	 * Return a small accessible empty widget.
	 *
	 * @return string
	 */
	private static function compact_empty(): string {
		self::enqueue_frontend_assets();

		return '<p class="psm-widget-empty">'
			. esc_html__(
				'Hiện chưa có lịch cúp điện phù hợp.',
				'power-schedule-manager'
			)
			. '</p>';
	}

	/**
	 * Convert a comma-separated shortcode value to valid unique unit codes.
	 *
	 * @param string $value Raw shortcode value.
	 * @return array<int,string>
	 */
	private static function unit_code_list( string $value ): array {
		$codes = preg_split( '/[\s,;]+/', $value ) ?: array();

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn ( string $code ): string =>
							Power_Schedule_Manager_Units::sanitize_code(
								$code
							),
						$codes
					)
				)
			)
		);
	}

	/**
	 * Normalize a human region name or slug for shortcode filtering.
	 *
	 * @param string $region Region name or slug.
	 * @return string
	 */
	private static function normalize_region_key( string $region ): string {
		$region = preg_replace(
			'/^\s*Khu\s+vực\s+/iu',
			'',
			sanitize_text_field( $region )
		);

		return sanitize_title(
			is_string( $region ) ? $region : ''
		);
	}

	/**
	 * Apply supported GET parameters.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 *
	 * @return array<string, mixed>
	 */
	private static function apply_query_parameters(
		array $attributes
	): array {
		$query_unit = self::request_value(
			'psm_unit'
		);

		$query_date = self::request_value(
			'psm_date'
		);

		if ( '' !== $query_unit ) {
			$attributes['unit'] =
				Power_Schedule_Manager_Units::sanitize_code(
					$query_unit
				);
		}

		if ( '' !== $query_date ) {
			try {
				$attributes['date'] =
					Power_Schedule_Manager_Validator::validate_local_date(
						$query_date
					);
			} catch ( InvalidArgumentException ) {
				$attributes['date'] = 'today';
			}
		}

		return $attributes;
	}

	/**
	 * Build local date range.
	 *
	 * @param string $date Requested date.
	 * @param int    $days Number of days.
	 *
	 * @return array{from: string, to: string, days: int}
	 */
	private static function date_range(
		string $date,
		int $days
	): array {
		$timezone = new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		);

		$today = new DateTimeImmutable(
			'today',
			$timezone
		);

		$date = strtolower(
			trim( $date )
		);

		$start = match ( $date ) {
			'today', '' => $today,
			'tomorrow'  => $today->modify( '+1 day' ),
			default     =>
				DateTimeImmutable::createFromFormat(
					'!Y-m-d',
					Power_Schedule_Manager_Validator::validate_local_date(
						$date
					),
					$timezone
				),
		};

		if ( false === $start ) {
			throw new InvalidArgumentException(
				'invalid_shortcode_date'
			);
		}

		if ( $start < $today ) {
			$start = $today;
		}

		$days = min(
			self::MAX_DAYS,
			max( 1, $days )
		);

		return array(
			'from' => $start->format( 'Y-m-d' ),
			'to'   => $start
				->modify( '+' . ( $days - 1 ) . ' days' )
				->format( 'Y-m-d' ),
			'days' => $days,
		);
	}

	/**
	 * Resolve public unit by code or slug.
	 *
	 * @param string $identifier Code or slug.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function resolve_public_unit(
		string $identifier
	): ?array {
		$identifier = trim( $identifier );

		if ( '' === $identifier ) {
			return null;
		}

		$code = Power_Schedule_Manager_Units::sanitize_code(
			$identifier
		);

		$unit = '' !== $code
			? Power_Schedule_Manager_Units::find_by_code( $code )
			: null;

		if ( null === $unit ) {
			$unit = Power_Schedule_Manager_Units::find_by_slug(
				$identifier
			);
		}

		if (
			null === $unit
			|| true !== $unit['is_public']
		) {
			return null;
		}

		return $unit;
	}

	/**
	 * Remove events belonging to non-public units.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function filter_public_units(
		array $events
	): array {
		$public_units = Power_Schedule_Manager_Units::all(
			true
		);

		$allowed_codes = array_fill_keys(
			array_map(
				static fn ( array $unit ): string =>
					(string) $unit['code'],
				$public_units
			),
			true
		);

		return array_values(
			array_filter(
				$events,
				static function ( array $event ) use (
					$allowed_codes
				): bool {
					$code = (string) (
						$event['unit_code'] ?? ''
					);

					return isset( $allowed_codes[ $code ] );
				}
			)
		);
	}

	/**
	 * Add unit names and map flags without N+1 queries.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function hydrate_event_display_data(
		array $events
	): array {
		global $wpdb;

		if ( array() === $events ) {
			return array();
		}

		$unit_map = array();

		foreach (
			Power_Schedule_Manager_Units::all( true )
			as $unit
		) {
			$unit_map[ (string) $unit['code'] ] =
				(string) $unit['name'];
		}

		$event_ids = array_values(
			array_filter(
				array_unique(
					array_map(
						static fn ( array $event ): int =>
							absint( $event['id'] ?? 0 ),
						$events
					)
				)
			)
		);

		$map_event_ids = array();

		if ( array() !== $event_ids ) {
			$legacy_table = Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::EVENT_LOCATIONS
			);
			$link_table = Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::EVENT_PLACES
			);
			$places_table = Power_Schedule_Manager_Database::table(
				Power_Schedule_Manager_Database::PLACES
			);

			$id_list = implode(
				',',
				array_map( 'absint', $event_ids )
			);

			$found_ids = $wpdb->get_col(
				"SELECT DISTINCT links.event_id
				FROM {$link_table} AS links
				INNER JOIN {$places_table} AS places
					ON places.id = links.place_id
				WHERE links.event_id IN ({$id_list})
					AND places.status = 'active'
					AND (
						NULLIF(TRIM(places.geojson), '') IS NOT NULL
						OR (
							places.center_lat IS NOT NULL
							AND places.center_lng IS NOT NULL
						)
					)
				UNION
				SELECT DISTINCT event_id
				FROM {$legacy_table}
				WHERE event_id IN ({$id_list})
					AND (
						NULLIF(TRIM(geojson), '') IS NOT NULL
						OR (
							center_lat IS NOT NULL
							AND center_lng IS NOT NULL
						)
					)"
			);

			if ( is_array( $found_ids ) ) {
				$map_event_ids = array_fill_keys(
					array_map( 'absint', $found_ids ),
					true
				);
			}
		}

		foreach ( $events as &$event ) {
			$unit_code = (string) (
				$event['unit_code'] ?? ''
			);
			$event_id = absint( $event['id'] ?? 0 );

			$event['unit_name'] =
				$unit_map[ $unit_code ] ?? $unit_code;

			$event['has_map'] =
				isset( $map_event_ids[ $event_id ] );
		}

		unset( $event );

		return $events;
	}

	/**
	 * Build default section title.
	 *
	 * @param array{from:string,to:string,days:int} $range Date range.
	 * @param array<string,mixed>|null              $unit Unit.
	 *
	 * @return string
	 */
	private static function default_title(
		array $range,
		?array $unit
	): string {
		$unit_name = null !== $unit
			? (string) $unit['name']
			: __( 'các khu vực', 'power-schedule-manager' );

		$from = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$range['from']
		);

		$to = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$range['to']
		);

		if ( false === $from || false === $to ) {
			return __(
				'Lịch cúp điện',
				'power-schedule-manager'
			);
		}

		if ( $range['from'] === $range['to'] ) {
			return sprintf(
				/* translators: 1: Unit, 2: Date. */
				__(
					'Lịch cúp điện %1$s ngày %2$s',
					'power-schedule-manager'
				),
				$unit_name,
				$from->format( 'd/m/Y' )
			);
		}

		return sprintf(
			/* translators: 1: Unit, 2: Start date, 3: End date. */
			__(
				'Lịch cúp điện %1$s từ %2$s đến %3$s',
				'power-schedule-manager'
			),
			$unit_name,
			$from->format( 'd/m/Y' ),
			$to->format( 'd/m/Y' )
		);
	}

	/**
	 * Enqueue frontend assets when shortcode renders.
	 *
	 * @return void
	 */
	private static function enqueue_frontend_assets(): void {
		if (
			class_exists( 'Power_Schedule_Manager_Assets' )
			&& is_callable(
				array(
					'Power_Schedule_Manager_Assets',
					'enqueue_public_assets',
				)
			)
		) {
			Power_Schedule_Manager_Assets::enqueue_public_assets();
		}
	}

	/**
	 * Render one sanitized, full-width advertising slot.
	 *
	 * Stored advertising content has already passed the settings sanitizer.
	 * It is filtered again after shortcode expansion to protect frontend
	 * output when another shortcode returns unsafe markup.
	 *
	 * @param string $content Stored HTML or shortcode.
	 * @param string $position Stable slot identifier.
	 * @return string
	 */
	private static function render_advertising_section(
		string $content,
		string $position
	): string {
		$content = trim( $content );

		if ( '' === $content ) {
			return '';
		}

		$rendered = wp_kses_post( do_shortcode( $content ) );

		if ( '' === trim( $rendered ) ) {
			return '';
		}

		return sprintf(
			'<aside class="psm-home-ad psm-home-ad--%1$s" aria-label="%2$s"><span class="psm-home-ad__label">%2$s</span><div class="psm-home-ad__content">%3$s</div></aside>',
			esc_attr( sanitize_html_class( $position ) ),
			esc_attr__( 'Quảng cáo', 'power-schedule-manager' ),
			$rendered
		);
	}

	/**
	 * Convert shortcode Boolean attribute.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return bool
	 */
	private static function boolean_attribute(
		mixed $value
	): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( ! is_scalar( $value ) ) {
			return false;
		}

		return in_array(
			strtolower( trim( (string) $value ) ),
			array( '1', 'true', 'yes', 'on' ),
			true
		);
	}

	/**
	 * Read a scalar GET value safely.
	 *
	 * Read-only search requests do not require a nonce.
	 *
	 * @param string $key Query key.
	 *
	 * @return string
	 */
	private static function request_value(
		string $key
	): string {
		if (
			! isset( $_GET[ $key ] )
			|| ! is_scalar( $_GET[ $key ] )
		) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash(
				(string) $_GET[ $key ]
			)
		);
	}
}
