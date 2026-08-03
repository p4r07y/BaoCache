<?php
/**
 * Page-level utility hero and supporting SEO content controls.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors choose whether Blocksy or the plugin owns the Page hero.
 */
final class Power_Schedule_Manager_Page_SEO_Controls {

	private const string META_KEY = '_psm_page_presentation';

	private const string NONCE_ACTION = 'psm_save_page_presentation';

	private const string NONCE_NAME = 'psm_page_presentation_nonce';

	/** @var array<int,bool> Prevent nested the_content calls duplicating Page UI. */
	private static array $rendered_pages = array();

	/** Register hooks. */
	public function register(): void {
		add_action( 'add_meta_boxes_page', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_page', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'sync_existing_blocksy_pages' ), 40 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 6 );
	}

	/** One-time upgrade for Pages configured before automatic Blocksy sync. */
	public function sync_existing_blocksy_pages(): void {
		$option = 'power_schedule_manager_blocksy_hero_sync';
		$needs_sync = '1' !== (string) get_option( $option, '' );
		$lottery_option = 'power_schedule_manager_lottery_page_hero_sync_038';
		$needs_lottery_sync = '1' !== (string) get_option( $lottery_option, '' );
		if ( ! $needs_sync && ! $needs_lottery_sync ) {
			return;
		}
		$page_ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $page_ids as $page_id ) {
			$value = get_post_meta( (int) $page_id, self::META_KEY, true );
			if ( $needs_lottery_sync && ! is_array( $value ) ) {
				$page = get_post( (int) $page_id );
				$inferred = $page instanceof WP_Post
					? self::infer_lottery_presentation( (string) $page->post_content )
					: array();
				if ( array() !== $inferred ) {
					$value = array_merge( array( 'owner' => 'plugin' ), $inferred );
					update_post_meta( (int) $page_id, self::META_KEY, $value );
				}
			}
			if ( is_array( $value ) && 'plugin' === (string) ( $value['owner'] ?? '' ) ) {
				self::sync_blocksy_page_title( (int) $page_id, 'plugin' );
			}
		}
		if ( $needs_sync ) {
			update_option( $option, '1', false );
		}
		if ( $needs_lottery_sync ) {
			update_option( $lottery_option, '1', false );
		}
	}

	/** Register one consolidated Page settings box. */
	public function add_meta_box(): void {
		add_meta_box(
			'psm-page-presentation',
			__( 'Cúp Điện Lâm Đồng — Hero và nội dung SEO', 'power-schedule-manager' ),
			array( $this, 'render_meta_box' ),
			'page',
			'normal',
			'high'
		);
	}

	/** Render controls without exposing Rank Math-owned metadata fields. */
	public function render_meta_box( WP_Post $post ): void {
		$value = get_post_meta( $post->ID, self::META_KEY, true );
		$value = is_array( $value ) ? $value : array();
		$inferred = self::infer_lottery_presentation( (string) $post->post_content );
		if ( array() === $value && array() !== $inferred ) {
			$value = array_merge( array( 'owner' => 'plugin' ), $inferred );
		}
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$owner = sanitize_key( (string) ( $value['owner'] ?? 'blocksy' ) );
		$preset = sanitize_key( (string) ( $value['preset'] ?? 'schedule' ) );
		$blocksy_options = get_post_meta( $post->ID, 'blocksy_post_meta_options', true );
		$blocksy_title_disabled = is_array( $blocksy_options )
			&& 'disabled' === (string) ( $blocksy_options['has_hero_section'] ?? '' );
		?>
		<p><strong><?php esc_html_e( 'Quyền quản lý H1/Hero', 'power-schedule-manager' ); ?></strong></p>
		<select name="psm_page_presentation[owner]">
			<option value="blocksy" <?php selected( $owner, 'blocksy' ); ?>><?php esc_html_e( 'Blocksy/Page quản lý H1 và Hero', 'power-schedule-manager' ); ?></option>
			<option value="plugin" <?php selected( $owner, 'plugin' ); ?>><?php esc_html_e( 'Plugin tự chèn Utility Hero', 'power-schedule-manager' ); ?></option>
			<option value="shortcode" <?php selected( $owner, 'shortcode' ); ?>><?php esc_html_e( 'Tự đặt shortcode hero trong nội dung', 'power-schedule-manager' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Khi chọn Plugin, hệ thống tự tắt Page Title/Hero riêng của Blocksy cho Page này để không trùng H1. Rank Math vẫn quản lý SEO title, description, canonical và schema.', 'power-schedule-manager' ); ?></p>
		<p class="notice notice-info inline"><strong><?php esc_html_e( 'Trạng thái hiện tại:', 'power-schedule-manager' ); ?></strong>
			<?php
			echo esc_html(
				'plugin' === $owner
					? ( $blocksy_title_disabled
						? __( 'Plugin quản lý Hero; Page Title Blocksy đã tắt.', 'power-schedule-manager' )
						: __( 'Plugin quản lý Hero; hãy bấm Cập nhật để đồng bộ trạng thái Blocksy.', 'power-schedule-manager' ) )
					: __( 'Blocksy/Page hoặc shortcode đang quản lý H1/Hero.', 'power-schedule-manager' )
			);
			?>
		</p>

		<table class="form-table" role="presentation"><tbody>
		<tr><th><label for="psm-page-preset"><?php esc_html_e( 'Loại trang', 'power-schedule-manager' ); ?></label></th><td>
		<select id="psm-page-preset" name="psm_page_presentation[preset]">
		<?php
		foreach (
			array(
				'schedule'       => __( 'Lịch điện', 'power-schedule-manager' ),
				'gold'           => __( 'Giá vàng', 'power-schedule-manager' ),
				'coffee'         => __( 'Giá cà phê', 'power-schedule-manager' ),
				'lottery'        => __( 'Kết quả xổ số', 'power-schedule-manager' ),
				'lottery_lookup' => __( 'Tra cứu xổ số', 'power-schedule-manager' ),
				'lottery_north'   => __( 'Xổ số miền Bắc', 'power-schedule-manager' ),
				'lottery_central' => __( 'Xổ số miền Trung', 'power-schedule-manager' ),
				'lottery_south'   => __( 'Xổ số miền Nam', 'power-schedule-manager' ),
				'lottery_mega645' => __( 'Vietlott Mega 6/45', 'power-schedule-manager' ),
				'lottery_power655' => __( 'Vietlott Power 6/55', 'power-schedule-manager' ),
				'lottery_max3d'   => __( 'Vietlott Max 3D', 'power-schedule-manager' ),
				'lottery_keno'    => __( 'Vietlott Keno', 'power-schedule-manager' ),
				'lottery_dientoan' => __( 'Xổ số Điện toán', 'power-schedule-manager' ),
				'lottery_dientoan123' => __( 'Điện toán 123', 'power-schedule-manager' ),
				'lottery_dientoan6x36' => __( 'Điện toán 6x36', 'power-schedule-manager' ),
				'lottery_thantai' => __( 'Xổ số Thần Tài', 'power-schedule-manager' ),
				'weather'        => __( 'Thời tiết', 'power-schedule-manager' ),
				'participate'    => __( 'Tham gia cộng đồng', 'power-schedule-manager' ),
				'sponsor'        => __( 'Hợp tác', 'power-schedule-manager' ),
			) as $key => $label
		) :
			?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $preset, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
		</select></td></tr>
		<?php
		$this->text_field( $value, 'eyebrow', __( 'Badge/Eyebrow', 'power-schedule-manager' ), 100 );
		$this->text_field( $value, 'title', __( 'H1', 'power-schedule-manager' ), 160 );
		$this->textarea_field( $value, 'description', __( 'Mô tả chức năng', 'power-schedule-manager' ), 320, 3 );
		$this->text_field( $value, 'cta_label', __( 'Nhãn CTA', 'power-schedule-manager' ), 80 );
		$this->text_field( $value, 'cta_url', __( 'Anchor/URL CTA', 'power-schedule-manager' ), 500 );
		?>
		<tr><th><?php esc_html_e( 'Tóm tắt bên phải', 'power-schedule-manager' ); ?></th><td>
			<p class="description"><?php esc_html_e( 'Để trống để tự lấy dữ liệu đã lưu. Nhập nhãn và giá trị để ghi đè; không nhập số liệu giả.', 'power-schedule-manager' ); ?></p>
			<div class="psm-page-summary-fields">
			<?php for ( $index = 1; $index <= 3; ++$index ) : ?>
				<p>
					<input type="text" name="psm_page_presentation[meta_<?php echo esc_attr( (string) $index ); ?>_label]" value="<?php echo esc_attr( (string) ( $value[ 'meta_' . $index . '_label' ] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Nhãn', 'power-schedule-manager' ); ?>">
					<input type="text" name="psm_page_presentation[meta_<?php echo esc_attr( (string) $index ); ?>_value]" value="<?php echo esc_attr( (string) ( $value[ 'meta_' . $index . '_value' ] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Giá trị', 'power-schedule-manager' ); ?>">
					<input type="text" name="psm_page_presentation[meta_<?php echo esc_attr( (string) $index ); ?>_detail]" value="<?php echo esc_attr( (string) ( $value[ 'meta_' . $index . '_detail' ] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Chi tiết', 'power-schedule-manager' ); ?>">
				</p>
			<?php endfor; ?>
			</div>
		</td></tr>
		<?php
		$this->text_field( $value, 'guide_heading', __( 'H2 hướng dẫn', 'power-schedule-manager' ), 160 );
		$this->textarea_field( $value, 'guide_content', __( 'Nội dung hướng dẫn', 'power-schedule-manager' ), 4000, 6 );
		$this->text_field( $value, 'source_heading', __( 'H2 nguồn và lưu ý', 'power-schedule-manager' ), 160 );
		$this->textarea_field( $value, 'source_content', __( 'Nội dung nguồn và lưu ý', 'power-schedule-manager' ), 4000, 6 );
		$this->textarea_field( $value, 'faq', __( 'FAQ hiển thị', 'power-schedule-manager' ), 6000, 7 );
		?>
		</tbody></table>
		<p class="description"><?php esc_html_e( 'FAQ: mỗi dòng dùng định dạng Câu hỏi | Câu trả lời. Plugin chỉ render nội dung nhìn thấy; nếu cần FAQ Schema, cấu hình trong Rank Math để tránh schema trùng.', 'power-schedule-manager' ); ?></p>
		<?php
	}

	/** Save Page fields with nonce, autosave and capability protection. */
	public function save( int $post_id, WP_Post $post ): void {
		if (
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| 'page' !== $post->post_type
			|| ! current_user_can( 'edit_page', $post_id )
			|| ! isset( $_POST[ self::NONCE_NAME ] )
			|| ! is_scalar( $_POST[ self::NONCE_NAME ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) ),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		$raw = isset( $_POST['psm_page_presentation'] )
			&& is_array( $_POST['psm_page_presentation'] )
			? wp_unslash( $_POST['psm_page_presentation'] ) : array();
		$allowed_owners = array( 'blocksy', 'plugin', 'shortcode' );
		$allowed_presets = array(
			'schedule', 'gold', 'coffee', 'lottery', 'lottery_lookup', 'weather',
			'lottery_north', 'lottery_central', 'lottery_south',
			'lottery_mega645', 'lottery_power655', 'lottery_max3d',
			'lottery_keno', 'lottery_dientoan', 'lottery_dientoan123',
			'lottery_dientoan6x36', 'lottery_thantai', 'participate', 'sponsor',
		);
		$value = array(
			'owner'  => in_array( sanitize_key( (string) ( $raw['owner'] ?? '' ) ), $allowed_owners, true )
				? sanitize_key( (string) $raw['owner'] ) : 'blocksy',
			'preset' => in_array( sanitize_key( (string) ( $raw['preset'] ?? '' ) ), $allowed_presets, true )
				? sanitize_key( (string) $raw['preset'] ) : 'schedule',
		);
		foreach ( array( 'eyebrow', 'title', 'cta_label' ) as $key ) {
			$value[ $key ] = sanitize_text_field( (string) ( $raw[ $key ] ?? '' ) );
		}
		$value['description'] = sanitize_textarea_field( (string) ( $raw['description'] ?? '' ) );
		$value['cta_url'] = self::sanitize_cta_url( (string) ( $raw['cta_url'] ?? '' ) );
		foreach ( array( 'guide_heading', 'source_heading' ) as $key ) {
			$value[ $key ] = sanitize_text_field( (string) ( $raw[ $key ] ?? '' ) );
		}
		foreach ( array( 'guide_content', 'source_content', 'faq' ) as $key ) {
			$value[ $key ] = wp_kses_post( (string) ( $raw[ $key ] ?? '' ) );
		}
		for ( $index = 1; $index <= 3; ++$index ) {
			foreach ( array( 'label', 'value', 'detail' ) as $part ) {
				$key = 'meta_' . $index . '_' . $part;
				$value[ $key ] = sanitize_text_field( (string) ( $raw[ $key ] ?? '' ) );
			}
		}
		update_post_meta( $post_id, self::META_KEY, $value );
		self::sync_blocksy_page_title( $post_id, (string) $value['owner'] );
	}

	/** Keep Blocksy's per-page title state consistent with the selected owner. */
	private static function sync_blocksy_page_title(
		int $post_id,
		string $owner
	): void {
		$meta_key = 'blocksy_post_meta_options';
		$options = get_post_meta( $post_id, $meta_key, true );
		$options = is_array( $options ) ? $options : array();
		$marker = '_psm_blocksy_hero_disabled';

		if ( 'plugin' === $owner ) {
			$options['page_title_panel'] = '';
			$options['has_hero_section'] = 'disabled';
			update_post_meta( $post_id, $meta_key, $options );
			update_post_meta( $post_id, $marker, '1' );
			return;
		}

		if ( '1' === (string) get_post_meta( $post_id, $marker, true ) ) {
			unset( $options['has_hero_section'] );
			update_post_meta( $post_id, $meta_key, $options );
			delete_post_meta( $post_id, $marker );
		}
	}

	/** Prepend the managed hero and append optional visible supporting copy. */
	public function filter_content( string $content ): string {
		if (
			is_admin()
			|| ! is_singular( 'page' )
			|| ! in_the_loop()
			|| ! is_main_query()
		) {
			return $content;
		}
		$post_id = get_queried_object_id();
		if ( isset( self::$rendered_pages[ $post_id ] ) ) {
			return $content;
		}
		self::$rendered_pages[ $post_id ] = true;
		$value = get_post_meta( $post_id, self::META_KEY, true );
		$value = is_array( $value ) ? $value : array();
		if ( array() === $value ) {
			$value = self::infer_lottery_presentation( $content );
			if ( array() !== $value ) {
				$value['owner'] = 'plugin';
			}
		}
		if (
			'plugin' === (string) ( $value['owner'] ?? '' )
			&& ! has_shortcode( $content, Power_Schedule_Manager_Shortcodes::PAGE_HERO_SHORTCODE )
		) {
			$attributes = array( 'variant' => (string) ( $value['preset'] ?? 'schedule' ) );
			foreach ( array( 'eyebrow', 'title', 'description', 'cta_label', 'cta_url' ) as $key ) {
				if ( '' !== trim( (string) ( $value[ $key ] ?? '' ) ) ) {
					$attributes[ $key ] = (string) $value[ $key ];
				}
			}
			for ( $index = 1; $index <= 3; ++$index ) {
				foreach ( array( 'label', 'value', 'detail' ) as $part ) {
					$key = 'meta_' . $index . '_' . $part;
					if ( '' !== trim( (string) ( $value[ $key ] ?? '' ) ) ) {
						$attributes[ $key ] = (string) $value[ $key ];
					}
				}
			}
			$content = ( new Power_Schedule_Manager_Shortcodes() )
				->render_page_hero_shortcode( $attributes ) . $content;
		}

		return $content . self::supporting_content( $value );
	}

	/**
	 * Infer the approved presentation for a dedicated child lottery Page.
	 * Only exact child shortcodes are mapped; arbitrary editorial Pages remain untouched.
	 *
	 * @return array<string,string>
	 */
	private static function infer_lottery_presentation( string $content ): array {
		$mappings = array(
			'power_schedule_lottery_north' => 'lottery_north',
			'power_schedule_lottery_central' => 'lottery_central',
			'power_schedule_lottery_south' => 'lottery_south',
			'power_schedule_lottery_mega645' => 'lottery_mega645',
			'power_schedule_lottery_power655' => 'lottery_power655',
			'power_schedule_lottery_max3d' => 'lottery_max3d',
			'power_schedule_lottery_max3d_plus' => 'lottery_max3d',
			'power_schedule_lottery_max3d_pro' => 'lottery_max3d',
			'power_schedule_lottery_keno' => 'lottery_keno',
			'power_schedule_lottery_dientoan' => 'lottery_dientoan',
			'power_schedule_lottery_dientoan123' => 'lottery_dientoan123',
			'power_schedule_lottery_dientoan6x36' => 'lottery_dientoan6x36',
			'power_schedule_lottery_thantai' => 'lottery_thantai',
		);
		foreach ( $mappings as $shortcode => $preset ) {
			if ( has_shortcode( $content, $shortcode ) ) {
				return array( 'preset' => $preset );
			}
		}

		return array();
	}

	/** @param array<string,mixed> $value Saved values. */
	private static function supporting_content( array $value ): string {
		$html = '';
		foreach ( array( 'guide', 'source' ) as $section ) {
			$heading = sanitize_text_field( (string) ( $value[ $section . '_heading' ] ?? '' ) );
			$copy = wp_kses_post( (string) ( $value[ $section . '_content' ] ?? '' ) );
			if ( '' !== $heading && '' !== trim( $copy ) ) {
				$html .= '<section class="psm-page-seo-content psm-page-seo-content--'
					. esc_attr( $section ) . '"><h2>' . esc_html( $heading )
					. '</h2>' . wpautop( $copy ) . '</section>';
			}
		}
		$faq = (string) ( $value['faq'] ?? '' );
		$items = array();
		foreach ( preg_split( '/\R/u', wp_strip_all_tags( $faq ) ) ?: array() as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
				$items[] = $parts;
			}
		}
		if ( array() !== $items ) {
			$html .= '<section class="psm-page-seo-content psm-page-seo-content--faq"><h2>'
				. esc_html__( 'Câu hỏi thường gặp', 'power-schedule-manager' ) . '</h2>';
			foreach ( $items as $item ) {
				$html .= '<details><summary>' . esc_html( $item[0] )
					. '</summary><p>' . esc_html( $item[1] ) . '</p></details>';
			}
			$html .= '</section>';
		}

		return $html;
	}

	/** @param array<string,mixed> $value Saved values. */
	private function text_field( array $value, string $key, string $label, int $maxlength ): void {
		?>
		<tr><th><label for="psm-page-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="large-text" id="psm-page-<?php echo esc_attr( $key ); ?>" type="text" maxlength="<?php echo esc_attr( (string) $maxlength ); ?>" name="psm_page_presentation[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $value[ $key ] ?? '' ) ); ?>"></td></tr>
		<?php
	}

	/** @param array<string,mixed> $value Saved values. */
	private function textarea_field( array $value, string $key, string $label, int $maxlength, int $rows ): void {
		?>
		<tr><th><label for="psm-page-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><textarea class="large-text" id="psm-page-<?php echo esc_attr( $key ); ?>" maxlength="<?php echo esc_attr( (string) $maxlength ); ?>" rows="<?php echo esc_attr( (string) $rows ); ?>" name="psm_page_presentation[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( (string) ( $value[ $key ] ?? '' ) ); ?></textarea></td></tr>
		<?php
	}

	private static function sanitize_cta_url( string $url ): string {
		$url = trim( $url );
		if ( str_starts_with( $url, '#' ) ) {
			return '#' . sanitize_title( substr( $url, 1 ) );
		}

		return esc_url_raw( $url, array( 'https', 'http' ) );
	}
}
