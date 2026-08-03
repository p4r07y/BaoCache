<?php
/**
 * Sponsorship inquiries and historical privacy support.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides the public sponsorship inquiry shortcode and its private workflow.
 */
final class Power_Schedule_Manager_Sponsorship {

	public const string SPONSOR_SHORTCODE = 'power_schedule_sponsor';

	public const string SPONSOR_POST_TYPE =
		'psm_sponsor_lead';

	private const string SPONSOR_SUBMIT_ACTION =
		'psm_submit_sponsorship';

	/**
	 * Register public and administration hooks.
	 */
	public function register(): void {
		add_shortcode(
			self::SPONSOR_SHORTCODE,
			array( $this, 'render_sponsor_shortcode' )
		);

		add_action(
			'admin_post_' . self::SPONSOR_SUBMIT_ACTION,
			array( $this, 'submit_sponsorship' )
		);
		add_action(
			'admin_post_nopriv_' . self::SPONSOR_SUBMIT_ACTION,
			array( $this, 'submit_sponsorship' )
		);
		add_action(
			'init',
			array( $this, 'register_sponsor_post_type' )
		);
		add_action(
			'add_meta_boxes_' . self::SPONSOR_POST_TYPE,
			array( $this, 'register_sponsor_meta_box' )
		);
		add_action(
			'save_post_' . self::SPONSOR_POST_TYPE,
			array( $this, 'save_sponsor_inquiry' ),
			10,
			2
		);
		add_filter(
			'manage_' . self::SPONSOR_POST_TYPE . '_posts_columns',
			array( $this, 'sponsor_columns' )
		);
		add_action(
			'manage_' . self::SPONSOR_POST_TYPE . '_posts_custom_column',
			array( $this, 'render_sponsor_column' ),
			10,
			2
		);
		add_filter(
			'post_row_actions',
			array( $this, 'filter_sponsor_row_actions' ),
			10,
			2
		);
		add_filter(
			'edit_' . self::SPONSOR_POST_TYPE . '_per_page',
			static fn (): int => 15
		);
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_admin_assets' )
		);
		add_filter(
			'wp_privacy_personal_data_exporters',
			array( $this, 'register_personal_data_exporter' )
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			array( $this, 'register_personal_data_eraser' )
		);
	}

	public function register_personal_data_exporter(
		array $exporters
	): array {
		$exporters['power-schedule-manager-sponsorships'] = array(
			'exporter_friendly_name' => __(
				'Đề nghị hợp tác tài trợ Cúp Điện Lâm Đồng',
				'power-schedule-manager'
			),
			'callback' => array(
				$this,
				'export_sponsor_personal_data',
			),
		);

		return $exporters;
	}

	/**
	 * Register the WordPress personal-data eraser.
	 *
	 * @param array<string, mixed> $erasers Registered erasers.
	 *
	 * @return array<string, mixed>
	 */
	public function register_personal_data_eraser(
		array $erasers
	): array {
		$erasers['power-schedule-manager-sponsorships'] = array(
			'eraser_friendly_name' => __(
				'Đề nghị hợp tác tài trợ Cúp Điện Lâm Đồng',
				'power-schedule-manager'
			),
			'callback' => array(
				$this,
				'erase_sponsor_personal_data',
			),
		);

		return $erasers;
	}

	/**
	 * Register a private administration queue for sponsor inquiries.
	 */
	public function register_sponsor_post_type(): void {
		register_post_type(
			self::SPONSOR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Hợp tác tài trợ', 'power-schedule-manager' ),
					'singular_name' => __( 'Đề nghị tài trợ', 'power-schedule-manager' ),
					'menu_name'     => __( 'Hợp tác tài trợ', 'power-schedule-manager' ),
					'edit_item'     => __( 'Chi tiết đề nghị tài trợ', 'power-schedule-manager' ),
					'search_items'  => __( 'Tìm đề nghị tài trợ', 'power-schedule-manager' ),
					'not_found'     => __( 'Chưa có đề nghị tài trợ.', 'power-schedule-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => Power_Schedule_Manager_Admin::MENU_SLUG,
				'show_in_rest'        => false,
				'supports'            => array( 'title' ),
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'read_post'              => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'delete_post'            => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'edit_posts'             => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'edit_others_posts'      => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'publish_posts'          => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'read_private_posts'     => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'delete_posts'           => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'delete_private_posts'   => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'delete_published_posts' => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'delete_others_posts'    => Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
					'create_posts'           => 'do_not_allow',
				),
			)
		);
	}

	/**
	 * Register the read-only contact detail box.
	 */
	public function register_sponsor_meta_box(): void {
		add_meta_box(
			'psm-sponsor-inquiry',
			__( 'Thông tin liên hệ và nhu cầu hiển thị', 'power-schedule-manager' ),
			array( $this, 'render_sponsor_meta_box' ),
			self::SPONSOR_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render one sponsor inquiry for an administrator.
	 */
	public function render_sponsor_meta_box( WP_Post $post ): void {
		$positions = get_post_meta( $post->ID, '_psm_sponsor_positions', true );
		$positions = is_array( $positions ) ? $positions : array();
		$position_labels = self::sponsor_positions();
		$status = sanitize_key(
			(string) get_post_meta(
				$post->ID,
				'_psm_sponsor_status',
				true
			)
		);
		$status = in_array(
			$status,
			array( 'new', 'contacted', 'closed' ),
			true
		) ? $status : 'new';

		wp_nonce_field(
			'psm_save_sponsor_inquiry_' . $post->ID,
			'_psm_sponsor_admin_nonce'
		);
		?>
		<table class="widefat striped">
			<tbody>
				<tr><th><?php esc_html_e( 'Người liên hệ', 'power-schedule-manager' ); ?></th><td><?php echo esc_html( (string) get_post_meta( $post->ID, '_psm_sponsor_contact_name', true ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'power-schedule-manager' ); ?></th><td><a href="mailto:<?php echo esc_attr( (string) get_post_meta( $post->ID, '_psm_sponsor_email', true ) ); ?>"><?php echo esc_html( (string) get_post_meta( $post->ID, '_psm_sponsor_email', true ) ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Điện thoại', 'power-schedule-manager' ); ?></th><td><?php echo esc_html( (string) get_post_meta( $post->ID, '_psm_sponsor_phone', true ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Website', 'power-schedule-manager' ); ?></th><td><?php echo esc_html( (string) get_post_meta( $post->ID, '_psm_sponsor_website', true ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Thời gian dự kiến', 'power-schedule-manager' ); ?></th><td><?php echo esc_html( self::sponsor_timeframe_label( (string) get_post_meta( $post->ID, '_psm_sponsor_timeframe', true ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Vị trí quan tâm', 'power-schedule-manager' ); ?></th><td><?php echo esc_html( implode( ', ', array_map( static fn ( string $key ): string => $position_labels[ $key ]['title'] ?? $key, $positions ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Lời nhắn', 'power-schedule-manager' ); ?></th><td><?php echo nl2br( esc_html( (string) $post->post_content ) ); ?></td></tr>
				<tr>
					<th><label for="psm-sponsor-status"><?php esc_html_e( 'Trạng thái xử lý', 'power-schedule-manager' ); ?></label></th>
					<td>
						<select id="psm-sponsor-status" name="psm_sponsor_status">
							<option value="new" <?php selected( $status, 'new' ); ?>><?php esc_html_e( 'Mới nhận', 'power-schedule-manager' ); ?></option>
							<option value="contacted" <?php selected( $status, 'contacted' ); ?>><?php esc_html_e( 'Đã liên hệ', 'power-schedule-manager' ); ?></option>
							<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Đã kết thúc', 'power-schedule-manager' ); ?></option>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save only the administrator workflow status.
	 */
	public function save_sponsor_inquiry(
		int $post_id,
		WP_Post $post
	): void {
		if (
			self::SPONSOR_POST_TYPE !== $post->post_type
			|| ! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
			|| ! isset( $_POST['_psm_sponsor_admin_nonce'] )
			|| ! is_scalar( $_POST['_psm_sponsor_admin_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						(string) $_POST['_psm_sponsor_admin_nonce']
					)
				),
				'psm_save_sponsor_inquiry_' . $post_id
			)
		) {
			return;
		}

		$status = isset( $_POST['psm_sponsor_status'] )
			&& is_scalar( $_POST['psm_sponsor_status'] )
				? sanitize_key(
					wp_unslash(
						(string) $_POST['psm_sponsor_status']
					)
				)
				: '';
		if ( in_array( $status, array( 'new', 'contacted', 'closed' ), true ) ) {
			update_post_meta(
				$post_id,
				'_psm_sponsor_status',
				$status
			);
		}
	}

	/**
	 * Customize the sponsor inquiry queue columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function sponsor_columns( array $columns ): array {
		return array(
			'cb'         => $columns['cb'] ?? '',
			'title'      => __( 'Doanh nghiệp', 'power-schedule-manager' ),
			'contact'    => __( 'Liên hệ', 'power-schedule-manager' ),
			'placements' => __( 'Vị trí quan tâm', 'power-schedule-manager' ),
			'lead_status' => __( 'Trạng thái', 'power-schedule-manager' ),
			'date'       => $columns['date'] ?? __( 'Ngày gửi', 'power-schedule-manager' ),
		);
	}

	/**
	 * Render one sponsor inquiry queue column.
	 */
	public function render_sponsor_column(
		string $column,
		int $post_id
	): void {
		if ( 'contact' === $column ) {
			$email = sanitize_email(
				(string) get_post_meta(
					$post_id,
					'_psm_sponsor_email',
					true
				)
			);
			echo '<a href="mailto:' . esc_attr( $email ) . '">'
				. esc_html( $email ) . '</a>';
			return;
		}

		if ( 'placements' === $column ) {
			$positions = get_post_meta(
				$post_id,
				'_psm_sponsor_positions',
				true
			);
			$positions = is_array( $positions ) ? $positions : array();
			echo esc_html(
				sprintf(
					/* translators: %d: Number of requested ad placements. */
					_n( '%d vị trí', '%d vị trí', count( $positions ), 'power-schedule-manager' ),
					count( $positions )
				)
			);
			return;
		}

		if ( 'lead_status' === $column ) {
			$status = sanitize_key(
				(string) get_post_meta(
					$post_id,
					'_psm_sponsor_status',
					true
				)
			);
			echo esc_html(
				match ( $status ) {
					'contacted' => __( 'Đã liên hệ', 'power-schedule-manager' ),
					'closed' => __( 'Đã kết thúc', 'power-schedule-manager' ),
					default => __( 'Mới nhận', 'power-schedule-manager' ),
				}
			);
		}
	}

	/**
	 * Remove irrelevant public actions from private inquiries.
	 *
	 * @param array<string,string> $actions Row actions.
	 * @return array<string,string>
	 */
	public function filter_sponsor_row_actions(
		array $actions,
		WP_Post $post
	): array {
		if ( self::SPONSOR_POST_TYPE === $post->post_type ) {
			unset(
				$actions['inline hide-if-no-js'],
				$actions['view']
			);
		}

		return $actions;
	}

	/**
	 * Enqueue the shared admin stylesheet for sponsor inquiries.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$is_sponsor_screen = self::SPONSOR_POST_TYPE === get_current_screen()?->post_type;
		if ( ! $is_sponsor_screen ) {
			return;
		}

		$path = POWER_SCHEDULE_MANAGER_PATH
			. 'admin/assets/admin.css';

		wp_enqueue_style(
			'power-schedule-manager-admin',
			POWER_SCHEDULE_MANAGER_URL
				. 'admin/assets/admin.css',
			array(),
			is_file( $path )
				? (string) filemtime( $path )
				: POWER_SCHEDULE_MANAGER_VERSION
		);
	}

	/**
	 * Render the standalone sponsorship page.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 */
	public function render_sponsor_shortcode(
		array|string $attributes = array()
	): string {
		$settings = self::settings();

		Power_Schedule_Manager_Assets::enqueue_public_assets( 'community' );

		$turnstile_site_key = self::turnstile_site_key( $settings );
		self::enqueue_turnstile( $turnstile_site_key );

		return Power_Schedule_Manager_Template_Loader::render_part(
			'sponsor',
			array(
				'settings'           => $settings,
				'action'             => add_query_arg(
					'action',
					self::SPONSOR_SUBMIT_ACTION,
					admin_url( 'admin-post.php' )
				),
				'return'             => self::current_public_url(),
				'turnstile_site_key' => $turnstile_site_key,
				'nonce'              => wp_create_nonce(
					self::SPONSOR_SUBMIT_ACTION
				),
			)
		);
	}

	/**
	 * Load Cloudflare Turnstile only on a page containing a protected form.
	 */
	private static function enqueue_turnstile( string $site_key ): void {
		if ( '' === $site_key ) {
			return;
		}

		wp_enqueue_script(
			'power-schedule-manager-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			null,
			true
		);
		wp_script_add_data(
			'power-schedule-manager-turnstile',
			'strategy',
			'async'
		);
		wp_script_add_data(
			'power-schedule-manager-turnstile',
			'defer',
			true
		);
	}

	public function submit_sponsorship(): void {
		if (
			! isset( $_POST['_psm_sponsor_nonce'] )
			|| ! is_scalar( $_POST['_psm_sponsor_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						(string) $_POST['_psm_sponsor_nonce']
					)
				),
				self::SPONSOR_SUBMIT_ACTION
			)
		) {
			wp_die(
				esc_html__(
					'Phiên gửi đề nghị hợp tác đã hết hạn.',
					'power-schedule-manager'
				),
				'',
				array( 'response' => 403 )
			);
		}

		$return_url = self::safe_return_url();
		$settings = self::settings();
		$honeypot = isset( $_POST['sponsor_company_url'] )
			&& is_scalar( $_POST['sponsor_company_url'] )
				? trim(
					(string) wp_unslash(
						$_POST['sponsor_company_url']
					)
				)
				: '';
		if ( '' !== $honeypot ) {
			self::redirect_with_sponsor_error(
				$return_url,
				'invalid'
			);
		}

		$company = self::limit_text(
			sanitize_text_field(
				wp_unslash(
					(string) ( $_POST['sponsor_company'] ?? '' )
				)
			),
			191
		);
		$contact_name = self::limit_text(
			sanitize_text_field(
				wp_unslash(
					(string) (
						$_POST['sponsor_contact_name'] ?? ''
					)
				)
			),
			191
		);
		$email = sanitize_email(
			wp_unslash(
				(string) ( $_POST['sponsor_email'] ?? '' )
			)
		);
		$raw_phone = wp_unslash(
			(string) ( $_POST['sponsor_phone'] ?? '' )
		);
		$phone = '' === trim( $raw_phone )
			? ''
			: self::normalize_vietnamese_phone( $raw_phone );
		$website = esc_url_raw(
			wp_unslash(
				(string) ( $_POST['sponsor_website'] ?? '' )
			),
			array( 'http', 'https' )
		);
		$message = self::limit_text(
			sanitize_textarea_field(
				wp_unslash(
					(string) ( $_POST['sponsor_message'] ?? '' )
				)
			),
			1500
		);
		$intent = sanitize_key(
			wp_unslash(
				(string) ( $_POST['sponsor_intent'] ?? '' )
			)
		);

		if (
			strlen( $contact_name ) < 2
			|| '' === $email
			|| ! is_email( $email )
			|| ( '' !== trim( $raw_phone ) && '' === $phone )
			|| ! in_array(
				$intent,
				array( 'feedback', 'connect', 'community', 'content', 'other' ),
				true
			)
			|| empty( $_POST['sponsor_consent'] )
		) {
			self::redirect_with_sponsor_error(
				$return_url,
				'invalid'
			);
		}

		$ip_hash = self::request_ip_hash()
			?? hash( 'sha256', 'unknown', true );
		if (
			! self::rate_limit_allows(
				$settings,
				'sponsor_ip_' . bin2hex( $ip_hash )
			)
		) {
			self::redirect_with_sponsor_error(
				$return_url,
				'rate'
			);
		}

		$turnstile_result = self::validate_turnstile(
			$settings,
			'sponsor_submit'
		);
		if ( true !== $turnstile_result ) {
			self::redirect_with_sponsor_error(
				$return_url,
				$turnstile_result
			);
		}

		if (
			! self::rate_limit_allows(
				$settings,
				'sponsor_email_' . hash_hmac(
					'sha256',
					strtolower( $email ),
					wp_salt( 'nonce' )
				)
			)
		) {
			self::redirect_with_sponsor_error(
				$return_url,
				'rate'
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::SPONSOR_POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => '' !== $company ? $company : $contact_name,
				'post_content' => $message,
				'post_author'  => 0,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			self::redirect_with_sponsor_error(
				$return_url,
				'storage'
			);
		}

		$metadata = array(
			'_psm_sponsor_contact_name' => $contact_name,
			'_psm_sponsor_email'        => $email,
			'_psm_sponsor_phone'        => $phone,
			'_psm_sponsor_website'      => $website,
			'_psm_sponsor_intent'       => $intent,
			'_psm_sponsor_status'       => 'new',
			'_psm_sponsor_ip_hash'      => bin2hex( $ip_hash ),
		);
		foreach ( $metadata as $key => $value ) {
			update_post_meta( (int) $post_id, $key, $value );
		}

		$recipient = sanitize_email(
			(string) ( $settings['sponsor_email'] ?? '' )
		);
		if ( '' === $recipient ) {
			$recipient = sanitize_email(
				(string) get_option( 'admin_email', '' )
			);
		}
		if ( '' !== $recipient ) {
			wp_mail(
				$recipient,
				sprintf(
					/* translators: %s: Company name. */
					__( '[Kết nối cộng đồng] Lời nhắn mới từ %s', 'power-schedule-manager' ),
					'' !== $company ? $company : $contact_name
				),
				sprintf(
					"%s: %s\n%s: %s\n%s: %s\n%s: %s\n\n%s",
					__( 'Người liên hệ', 'power-schedule-manager' ),
					$contact_name,
					__( 'Email', 'power-schedule-manager' ),
					$email,
					__( 'Điện thoại', 'power-schedule-manager' ),
					$phone,
					__( 'Mục đích', 'power-schedule-manager' ),
					self::sponsor_intent_label( $intent ),
					$message
				),
				array( 'Reply-To: ' . $contact_name . ' <' . $email . '>' )
			);
		}

		$success_url = add_query_arg(
			'psm_sponsor',
			'sent',
			$return_url
		);
		wp_safe_redirect(
			$success_url . '#psm-sponsor-form'
		);
		exit;
	}
	public function export_sponsor_personal_data(
		string $email_address,
		int $page = 1
	): array {
		$email = sanitize_email( $email_address );
		if ( '' === $email ) {
			return array( 'data' => array(), 'done' => true );
		}

		$query = new WP_Query(
			array(
				'post_type'      => self::SPONSOR_POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 50,
				'paged'          => max( 1, $page ),
				'meta_key'       => '_psm_sponsor_email',
				'meta_value'     => $email,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => false,
			)
		);
		$data = array();
		$labels = self::sponsor_positions();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$positions = get_post_meta(
				$post->ID,
				'_psm_sponsor_positions',
				true
			);
			$positions = is_array( $positions ) ? $positions : array();
			$data[] = array(
				'group_id'    => 'power-schedule-manager-sponsorships',
				'group_label' => __( 'Đề nghị hợp tác tài trợ', 'power-schedule-manager' ),
				'item_id'     => 'sponsorship-' . $post->ID,
				'data'        => array(
					array( 'name' => __( 'Doanh nghiệp', 'power-schedule-manager' ), 'value' => $post->post_title ),
					array( 'name' => __( 'Người liên hệ', 'power-schedule-manager' ), 'value' => (string) get_post_meta( $post->ID, '_psm_sponsor_contact_name', true ) ),
					array( 'name' => __( 'Email', 'power-schedule-manager' ), 'value' => $email ),
					array( 'name' => __( 'Điện thoại', 'power-schedule-manager' ), 'value' => (string) get_post_meta( $post->ID, '_psm_sponsor_phone', true ) ),
					array( 'name' => __( 'Website', 'power-schedule-manager' ), 'value' => (string) get_post_meta( $post->ID, '_psm_sponsor_website', true ) ),
					array( 'name' => __( 'Vị trí quan tâm', 'power-schedule-manager' ), 'value' => implode( ', ', array_map( static fn ( string $key ): string => $labels[ $key ]['title'] ?? $key, $positions ) ) ),
					array( 'name' => __( 'Lời nhắn', 'power-schedule-manager' ), 'value' => $post->post_content ),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => $page >= (int) $query->max_num_pages,
		);
	}

	/**
	 * Permanently erase sponsor inquiries for one email address.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int,string>, done: bool}
	 */
	public function erase_sponsor_personal_data(
		string $email_address,
		int $page = 1
	): array {
		unset( $page );
		$email = sanitize_email( $email_address );
		if ( '' === $email ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => self::SPONSOR_POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_key'       => '_psm_sponsor_email',
				'meta_value'     => $email,
			)
		);
		$removed = false;
		foreach ( $posts as $post_id ) {
			$removed = null !== wp_delete_post(
				absint( $post_id ),
				true
			) || $removed;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $posts ) < 50,
		);
	}

	/**
	 * Return plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings(): array {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Return sponsor inventory shown publicly and stored as stable keys.
	 *
	 * @return array<string,array{title:string,description:string}>
	 */
	private static function sponsor_positions(): array {
		return array(
			'home_after_hero' => array(
				'title' => __( 'Trang chủ — sau Hero', 'power-schedule-manager' ),
				'description' => __( 'Hiện sau công cụ tra cứu, không chen vào thao tác tìm kiếm.', 'power-schedule-manager' ),
			),
			'home_end' => array(
				'title' => __( 'Trang chủ — cuối nội dung', 'power-schedule-manager' ),
				'description' => __( 'Phù hợp chiến dịch nhận diện dài hạn và nội dung cộng đồng.', 'power-schedule-manager' ),
			),
			'archive_after_search' => array(
				'title' => __( 'Danh sách lịch — sau bộ lọc', 'power-schedule-manager' ),
				'description' => __( 'Banner ngang trước bảng lịch, có khoảng cách an toàn với nút tra cứu.', 'power-schedule-manager' ),
			),
			'archive_end' => array(
				'title' => __( 'Danh sách lịch — cuối trang', 'power-schedule-manager' ),
				'description' => __( 'Ít gián đoạn, phù hợp chiến dịch tài trợ thương hiệu dài hạn.', 'power-schedule-manager' ),
			),
			'schedule_after_summary' => array(
				'title' => __( 'Chi tiết lịch — sau thông tin chính', 'power-schedule-manager' ),
				'description' => __( 'Chỉ xuất hiện sau khi người dùng đã đọc được lịch quan trọng.', 'power-schedule-manager' ),
			),
			'schedule_end' => array(
				'title' => __( 'Chi tiết lịch — cuối bài', 'power-schedule-manager' ),
				'description' => __( 'Vị trí nhẹ, xuất hiện sau khi người dùng đã xem xong thông tin chính.', 'power-schedule-manager' ),
			),
			'utility_content' => array(
				'title' => __( 'Trang tiện ích — giữa các khối nội dung', 'power-schedule-manager' ),
				'description' => __( 'Áp dụng chọn lọc cho giá vàng, cà phê, xổ số hoặc thời tiết.', 'power-schedule-manager' ),
			),
			'custom_package' => array(
				'title' => __( 'Gói chuyên mục hoặc vị trí tùy chỉnh', 'power-schedule-manager' ),
				'description' => __( 'Hai bên thống nhất riêng về chuyên mục, kích thước và thời gian.', 'power-schedule-manager' ),
			),
		);
	}

	private static function sponsor_timeframe_label(
		string $timeframe
	): string {
		return match ( sanitize_key( $timeframe ) ) {
			'asap' => __( 'Sớm nhất có thể', 'power-schedule-manager' ),
			'one_month' => __( 'Trong một tháng', 'power-schedule-manager' ),
			'one_three_months' => __( 'Trong 1–3 tháng', 'power-schedule-manager' ),
			default => __( 'Linh hoạt', 'power-schedule-manager' ),
		};
	}

	private static function sponsor_intent_label( string $intent ): string {
		return match ( sanitize_key( $intent ) ) {
			'feedback' => __( 'Góp ý dữ liệu hoặc trải nghiệm', 'power-schedule-manager' ),
			'community' => __( 'Đồng hành dự án cộng đồng', 'power-schedule-manager' ),
			'content' => __( 'Gợi ý nội dung hoặc chuyên mục', 'power-schedule-manager' ),
			'other' => __( 'Khác', 'power-schedule-manager' ),
			default => __( 'Kết nối và trao đổi', 'power-schedule-manager' ),
		};
	}

	private static function current_public_url(): string {
		$url = home_url( '/' );

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = wp_unslash(
				(string) $_SERVER['REQUEST_URI']
			);
			$path = wp_parse_url( $request_uri, PHP_URL_PATH );

			if ( is_string( $path ) && str_starts_with( $path, '/' ) ) {
				$url = home_url( $path );
			}
		}

		return $url;
	}

	private static function safe_return_url(): string {
		$return = isset( $_POST['return_url'] )
			&& is_scalar( $_POST['return_url'] )
				? esc_url_raw(
					wp_unslash(
						(string) $_POST['return_url']
					)
				)
				: home_url( '/' );

		return wp_validate_redirect( $return, home_url( '/' ) );
	}

	private static function redirect_with_sponsor_error(
		string $url,
		string $code
	): never {
		$target = add_query_arg(
			'psm_sponsor_error',
			sanitize_key( $code ),
			$url
		);
		wp_safe_redirect( $target . '#psm-sponsor-form' );
		exit;
	}

	private static function request_ip_hash(): ?string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? trim( (string) $_SERVER['REMOTE_ADDR'] )
			: '';

		if ( '' === $ip ) {
			return null;
		}

		return hash_hmac(
			'sha256',
			$ip,
			wp_salt( 'nonce' ),
			true
		);
	}

	/**
	 * Enforce a bounded local limit before any remote CAPTCHA request.
	 *
	 * Cloudflare Rate Limiting remains the preferred edge layer. This local
	 * counter protects the origin when traffic bypasses or misses that rule.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @param string              $identifier One pseudonymous rate bucket.
	 */
	private static function rate_limit_allows(
		array $settings,
		string $identifier
	): bool {
		unset( $settings );
		$limit = 5;
		$window = MINUTE_IN_SECONDS * 10;
		$key = 'psm_sponsor_' . $identifier;
		$count = absint( get_transient( $key ) );
		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, (string) ( $count + 1 ), $window );

		return true;
	}

	/**
	 * Return the public Turnstile site key only when protection is complete.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	private static function turnstile_site_key( array $settings ): string {
		if ( empty( $settings['cloudflare_turnstile_enabled'] ) ) {
			return '';
		}

		$site_key = trim(
			(string) ( $settings['cloudflare_turnstile_site_key'] ?? '' )
		);
		$secret = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_TURNSTILE_SECRET',
			(string) (
				$settings['cloudflare_turnstile_secret_encrypted'] ?? ''
			)
		);

		return '' !== $site_key && '' !== $secret ? $site_key : '';
	}

	/**
	 * Validate a Turnstile token with Cloudflare Siteverify.
	 *
	 * @param array<string,mixed> $settings        Plugin settings.
	 * @param string              $expected_action Expected widget action.
	 * @return true|string True or a safe public error code.
	 */
	private static function validate_turnstile(
		array $settings,
		string $expected_action
	): true|string {
		if ( empty( $settings['cloudflare_turnstile_enabled'] ) ) {
			return true;
		}

		$site_key = trim(
			(string) ( $settings['cloudflare_turnstile_site_key'] ?? '' )
		);
		$secret = Power_Schedule_Manager_Secrets::resolve(
			'POWER_SCHEDULE_MANAGER_TURNSTILE_SECRET',
			(string) (
				$settings['cloudflare_turnstile_secret_encrypted'] ?? ''
			)
		);

		if ( '' === $site_key || '' === $secret ) {
			return 'captcha_config';
		}

		$token = isset( $_POST['cf-turnstile-response'] )
			&& is_scalar( $_POST['cf-turnstile-response'] )
				? trim(
					wp_unslash(
						(string) $_POST['cf-turnstile-response']
					)
				)
				: '';

		if ( '' === $token || strlen( $token ) > 2048 ) {
			return 'captcha';
		}

		$body = array(
			'secret'   => $secret,
			'response' => $token,
		);
		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] )
			? trim( (string) $_SERVER['REMOTE_ADDR'] )
			: '';
		if ( false !== filter_var( $remote_ip, FILTER_VALIDATE_IP ) ) {
			$body['remoteip'] = $remote_ip;
		}

		$response = wp_safe_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'body'        => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return 'captcha_unavailable';
		}

		$data = json_decode(
			wp_remote_retrieve_body( $response ),
			true
		);
		if (
			200 !== wp_remote_retrieve_response_code( $response )
			|| ! is_array( $data )
			|| empty( $data['success'] )
		) {
			return 'captcha_failed';
		}

		$expected_host = strtolower(
			(string) wp_parse_url( home_url( '/' ), PHP_URL_HOST )
		);
		$verified_host = strtolower(
			sanitize_text_field( (string) ( $data['hostname'] ?? '' ) )
		);
		$verified_action = sanitize_key(
			(string) ( $data['action'] ?? '' )
		);

		if (
			'' === $expected_host
			|| ! hash_equals( $expected_host, $verified_host )
			|| ! hash_equals(
				sanitize_key( $expected_action ),
				$verified_action
			)
		) {
			return 'captcha_failed';
		}

		return true;
	}

	/**
	 * Normalize and validate a Vietnamese mobile or landline number.
	 *
	 * Accepted prefixes are 0, 84 and +84. Formatting characters are ignored,
	 * but letters, extensions and impossible lengths are rejected.
	 */
	private static function normalize_vietnamese_phone(
		string $phone
	): string {
		$phone = trim( $phone );
		if (
			'' === $phone
			|| 1 === preg_match( '/[^\d+().\s-]/u', $phone )
		) {
			return '';
		}

		$digits = preg_replace( '/\D+/', '', $phone );
		if ( ! is_string( $digits ) ) {
			return '';
		}

		if ( str_starts_with( $digits, '84' ) ) {
			$digits = '0' . substr( $digits, 2 );
		}

		/*
		 * Mobile: 03/05/07/08/09 + 8 digits.
		 * Landline: 02 + 9 digits (area code included).
		 */
		return 1 === preg_match(
			'/\A(?:0(?:3|5|7|8|9)\d{8}|02\d{9})\z/',
			$digits
		)
			? $digits
			: '';
	}

	private static function limit_text(
		string $value,
		int $length
	): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, $length, 'UTF-8' )
			: substr( $value, 0, $length );
	}
}
