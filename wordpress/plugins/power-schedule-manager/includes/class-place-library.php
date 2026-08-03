<?php
/**
 * Persistent reusable map place library.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores roads and areas independently from outage events.
 */
final class Power_Schedule_Manager_Place_Library {

	public const string MENU_SLUG = 'power-schedule-manager-places';

	public const string EDIT_MENU_SLUG = 'power-schedule-manager-place-editor';

	private const string SAVE_ACTION = 'psm_save_place';

	private const string DELETE_ACTION = 'psm_delete_place';

	private const string RELINK_ACTION = 'psm_relink_places';

	private const string NONCE_ACTION = 'psm_save_place';

	private const string RELINK_NONCE_ACTION = 'psm_relink_places';

	/**
	 * Versioned one-time rebuild for automatic event-place links.
	 */
	private const string LINK_MATCH_VERSION = '5';

	private const string LINK_MATCH_VERSION_OPTION =
		'psm_place_link_match_version';

	private const string LINK_MATCH_LOCK_OPTION =
		'psm_place_link_match_lock';

	/**
	 * WordPress screen hook for conditional assets.
	 */
	private array $screen_ids = array();

	/**
	 * Request-local place names, cleared whenever aliases change.
	 *
	 * @var array<string,array<int,array<int,string>>>
	 */
	private static array $active_place_names_cache = array();

	/**
	 * Register administration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_menu',
			array( $this, 'register_menu' ),
			20
		);

		add_action(
			'admin_post_' . self::SAVE_ACTION,
			array( $this, 'handle_save' )
		);

		add_action(
			'admin_post_' . self::DELETE_ACTION,
			array( $this, 'handle_delete' )
		);

		add_action(
			'admin_post_' . self::RELINK_ACTION,
			array( $this, 'handle_relink' )
		);

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_assets' )
		);

		add_action(
			'admin_init',
			array( $this, 'maybe_upgrade_place_links' ),
			40
		);
	}

	/**
	 * Rebuild saved-place links once after the matching rules change.
	 *
	 * The lock prevents two concurrent administration requests from running
	 * the same potentially expensive maintenance job. A stale lock is cleared
	 * after five minutes so an interrupted request cannot block future repairs.
	 *
	 * @return void
	 */
	public function maybe_upgrade_place_links(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
			|| self::LINK_MATCH_VERSION === (string) get_option(
				self::LINK_MATCH_VERSION_OPTION,
				''
			)
		) {
			return;
		}

		$now = time();
		$lock = absint(
			get_option(
				self::LINK_MATCH_LOCK_OPTION,
				0
			)
		);

		if ( $lock > 0 && $lock >= $now - 300 ) {
			return;
		}

		if ( $lock > 0 ) {
			delete_option( self::LINK_MATCH_LOCK_OPTION );
		}

		if (
			! add_option(
				self::LINK_MATCH_LOCK_OPTION,
				$now,
				'',
				false
			)
		) {
			return;
		}

		try {
			self::relink_all_active_places();
			update_option(
				self::LINK_MATCH_VERSION_OPTION,
				self::LINK_MATCH_VERSION,
				false
			);
		} catch ( Throwable $throwable ) {
			Power_Schedule_Manager_Logger::error(
				'place_link_upgrade_failed',
				$throwable
			);
		} finally {
			delete_option( self::LINK_MATCH_LOCK_OPTION );
		}
	}

	/**
	 * Register the place-library screen.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$list_screen = add_submenu_page(
			Power_Schedule_Manager_Admin::MENU_SLUG,
			__( 'Thư viện đường và khu vực', 'power-schedule-manager' ),
			__( 'Thư viện bản đồ', 'power-schedule-manager' ),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);

		$editor_screen = add_submenu_page(
			'power-schedule-manager-hidden',
			__( 'Thêm địa điểm bản đồ', 'power-schedule-manager' ),
			__( 'Thêm địa điểm', 'power-schedule-manager' ),
			Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS,
			self::EDIT_MENU_SLUG,
			array( $this, 'render_editor_page' )
		);

		foreach ( array( $list_screen, $editor_screen ) as $screen_id ) {
			if ( is_string( $screen_id ) ) {
				$this->screen_ids[] = $screen_id;
			}
		}
	}

	/**
	 * Load the existing administration stylesheet only on this screen.
	 *
	 * @param string $hook_suffix Current administration screen.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$current_page = isset( $_GET['page'] )
			&& is_scalar( $_GET['page'] )
			? sanitize_key( wp_unslash( (string) $_GET['page'] ) )
			: '';

		if (
			! in_array( $hook_suffix, $this->screen_ids, true )
			&& ! in_array(
				$current_page,
				array( self::MENU_SLUG, self::EDIT_MENU_SLUG ),
				true
			)
		) {
			return;
		}

		$file = POWER_SCHEDULE_MANAGER_PATH . 'admin/assets/admin.css';

		wp_enqueue_style(
			'power-schedule-manager-admin',
			POWER_SCHEDULE_MANAGER_URL . 'admin/assets/admin.css',
			array(),
			is_file( $file )
				? (string) filemtime( $file )
				: POWER_SCHEDULE_MANAGER_VERSION
		);

		$leaflet_css = POWER_SCHEDULE_MANAGER_PATH
			. 'public/assets/vendor/leaflet/leaflet.css';
		$leaflet_js = POWER_SCHEDULE_MANAGER_PATH
			. 'public/assets/vendor/leaflet/leaflet.js';
		$osm_editor = POWER_SCHEDULE_MANAGER_PATH
			. 'admin/assets/osm-road-editor.js';

		wp_enqueue_style(
			'power-schedule-manager-leaflet-admin',
			POWER_SCHEDULE_MANAGER_URL
				. 'public/assets/vendor/leaflet/leaflet.css',
			array(),
			is_file( $leaflet_css )
				? (string) filemtime( $leaflet_css )
				: POWER_SCHEDULE_MANAGER_VERSION
		);

		wp_enqueue_script(
			'power-schedule-manager-leaflet-admin',
			POWER_SCHEDULE_MANAGER_URL
				. 'public/assets/vendor/leaflet/leaflet.js',
			array(),
			is_file( $leaflet_js )
				? (string) filemtime( $leaflet_js )
				: POWER_SCHEDULE_MANAGER_VERSION,
			true
		);

		wp_enqueue_script(
			'power-schedule-manager-osm-road-editor',
			POWER_SCHEDULE_MANAGER_URL
				. 'admin/assets/osm-road-editor.js',
			array( 'power-schedule-manager-leaflet-admin' ),
			is_file( $osm_editor )
				? (string) filemtime( $osm_editor ) . '.area-boundary-1'
				: POWER_SCHEDULE_MANAGER_VERSION . '.area-boundary-1',
			true
		);

		wp_localize_script(
			'power-schedule-manager-osm-road-editor',
			'PowerScheduleManagerOSM',
			Power_Schedule_Manager_OSM_Road_Importer::editor_configuration()
		);

	}

	/**
	 * Render the place-library administration page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die(
				esc_html__(
					'Bạn không có quyền quản lý thư viện bản đồ.',
					'power-schedule-manager'
				)
			);
		}

		$units = Power_Schedule_Manager_Units::all();
		$filters = array(
			'search'    => self::request_text( 'place_search' ),
			'unit_code' => Power_Schedule_Manager_Units::sanitize_code(
				self::request_text( 'place_unit' )
			),
			'type'      => sanitize_key(
				self::request_text( 'place_type' )
			),
			'status'    => sanitize_key(
				self::request_text( 'place_status' )
			),
			'page'      => max( 1, absint( $_GET['paged'] ?? 1 ) ),
			'per_page'  => 25,
		);
		$listing = self::query( $filters );
		$places = $listing['items'];
		$statistics = self::statistics();
		?>
		<div class="wrap psm-admin-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Thư viện đường và khu vực', 'power-schedule-manager' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( add_query_arg( 'page', self::EDIT_MENU_SLUG, admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'Thêm địa điểm', 'power-schedule-manager' ); ?>
			</a>
			<hr class="wp-header-end">
			<p class="psm-admin-lead">
				<?php esc_html_e( 'Dữ liệu tại đây tồn tại độc lập với từng lịch cúp điện và được tự động tái sử dụng khi tên đường hoặc bí danh xuất hiện trong KHU VỰC.', 'power-schedule-manager' ); ?>
			</p>

			<?php if ( isset( $_GET['psm_saved'] ) ) : ?>
				<?php $saved_link_count = absint( $_GET['psm_linked'] ?? 0 ); ?>
				<?php $saved_place_id = absint( $_GET['psm_place_id'] ?? 0 ); ?>
				<?php $saved_place_status = sanitize_key( self::request_text( 'psm_place_status' ) ); ?>
				<div class="notice <?php echo 0 === $saved_link_count ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p>
					<?php if ( 'pending' === $saved_place_status ) : ?>
						<?php esc_html_e( 'Đã lưu địa điểm ở trạng thái chờ vì chưa có tọa độ hoặc GeoJSON hợp lệ. Hoàn thiện bản đồ và chuyển sang “Đang sử dụng” để tự liên kết với lịch điện.', 'power-schedule-manager' ); ?>
					<?php elseif ( 'inactive' === $saved_place_status ) : ?>
						<?php esc_html_e( 'Đã lưu địa điểm ở trạng thái ngừng sử dụng. Địa điểm này không được liên kết hoặc hiển thị trên frontend.', 'power-schedule-manager' ); ?>
					<?php elseif ( 0 === $saved_link_count ) : ?>
						<?php esc_html_e( 'Đã lưu bản đồ nhưng chưa có nội dung KHU VỰC nào của đúng đơn vị chứa tên chuẩn hoặc bí danh này.', 'power-schedule-manager' ); ?>
						<?php if ( $saved_place_id > 0 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::EDIT_MENU_SLUG, 'place_id' => $saved_place_id ), admin_url( 'admin.php' ) ) ); ?>">
								<?php esc_html_e( 'Kiểm tra tên và bí danh', 'power-schedule-manager' ); ?>
							</a>
						<?php endif; ?>
					<?php else : ?>
						<?php
						printf(
							/* translators: %d: Number of event-place links. */
							esc_html__( 'Đã lưu địa điểm và tự liên kết với %d lịch điện. Bản đồ sẽ xuất hiện ở frontend của các lịch đã xuất bản.', 'power-schedule-manager' ),
							$saved_link_count
						);
						?>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['psm_relinked'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					printf(
						/* translators: %d: Number of event-place links. */
						esc_html__( 'Đã khớp lại thư viện bản đồ và tạo/cập nhật %d liên kết lịch điện.', 'power-schedule-manager' ),
						absint( $_GET['psm_linked'] ?? 0 )
					);
					?>
				</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['psm_deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php esc_html_e( 'Đã xóa địa điểm và các liên kết lịch liên quan.', 'power-schedule-manager' ); ?>
				</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['psm_error'] ) ) : ?>
				<div class="notice notice-error"><p>
					<?php echo esc_html( self::save_error_message( self::request_text( 'psm_error' ) ) ); ?>
				</p></div>
			<?php endif; ?>

			<div class="psm-dashboard-kpis psm-place-kpis">
				<div class="psm-dashboard-kpi">
					<span><?php esc_html_e( 'Tổng địa điểm', 'power-schedule-manager' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $statistics['total'] ) ); ?></strong>
				</div>
				<div class="psm-dashboard-kpi">
					<span><?php esc_html_e( 'Đang sử dụng', 'power-schedule-manager' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $statistics['active'] ) ); ?></strong>
				</div>
				<div class="psm-dashboard-kpi">
					<span><?php esc_html_e( 'Có dữ liệu bản đồ', 'power-schedule-manager' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $statistics['mapped'] ) ); ?></strong>
				</div>
				<div class="psm-dashboard-kpi">
					<span><?php esc_html_e( 'Chờ hoàn thiện', 'power-schedule-manager' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $statistics['pending'] ) ); ?></strong>
				</div>
				<div class="psm-dashboard-kpi">
					<span><?php esc_html_e( 'Chưa liên kết lịch', 'power-schedule-manager' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $statistics['unlinked'] ) ); ?></strong>
				</div>
			</div>

			<section class="psm-dashboard-panel">
				<div class="psm-place-library-heading">
					<div>
						<h2><?php esc_html_e( 'Địa điểm đã lưu', 'power-schedule-manager' ); ?></h2>
						<p><?php esc_html_e( 'Quản lý tên đường, bí danh và hình học bản đồ dùng lại cho nhiều lịch cúp điện.', 'power-schedule-manager' ); ?></p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::RELINK_ACTION ); ?>">
						<?php wp_nonce_field( self::RELINK_NONCE_ACTION, 'psm_relink_nonce' ); ?>
						<button type="submit" class="button">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php esc_html_e( 'Khớp lại thư viện', 'power-schedule-manager' ); ?>
						</button>
					</form>
				</div>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="psm-place-filters">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
					<label class="screen-reader-text" for="psm-place-search"><?php esc_html_e( 'Tìm địa điểm', 'power-schedule-manager' ); ?></label>
					<input id="psm-place-search" type="search" name="place_search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Tìm tên đường hoặc bí danh…', 'power-schedule-manager' ); ?>">
					<select name="place_unit" aria-label="<?php esc_attr_e( 'Lọc theo đơn vị', 'power-schedule-manager' ); ?>">
						<option value=""><?php esc_html_e( 'Tất cả đơn vị', 'power-schedule-manager' ); ?></option>
						<?php foreach ( $units as $unit ) : ?>
							<?php if ( ! is_array( $unit ) ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $unit['code'] ); ?>" <?php selected( $filters['unit_code'], (string) $unit['code'] ); ?>><?php echo esc_html( (string) $unit['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="place_type" aria-label="<?php esc_attr_e( 'Lọc theo loại vị trí', 'power-schedule-manager' ); ?>">
						<option value=""><?php esc_html_e( 'Tất cả loại', 'power-schedule-manager' ); ?></option>
						<option value="road_segment" <?php selected( $filters['type'], 'road_segment' ); ?>><?php esc_html_e( 'Toàn tuyến đường', 'power-schedule-manager' ); ?></option>
						<option value="area" <?php selected( $filters['type'], 'area' ); ?>><?php esc_html_e( 'Khu vực', 'power-schedule-manager' ); ?></option>
						<option value="point" <?php selected( $filters['type'], 'point' ); ?>><?php esc_html_e( 'Điểm', 'power-schedule-manager' ); ?></option>
						<option value="facility" <?php selected( $filters['type'], 'facility' ); ?>><?php esc_html_e( 'Công trình', 'power-schedule-manager' ); ?></option>
					</select>
					<select name="place_status" aria-label="<?php esc_attr_e( 'Lọc theo trạng thái', 'power-schedule-manager' ); ?>">
						<option value=""><?php esc_html_e( 'Mọi trạng thái', 'power-schedule-manager' ); ?></option>
						<option value="active" <?php selected( $filters['status'], 'active' ); ?>><?php esc_html_e( 'Đang sử dụng', 'power-schedule-manager' ); ?></option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>><?php esc_html_e( 'Chờ bổ sung bản đồ', 'power-schedule-manager' ); ?></option>
						<option value="inactive" <?php selected( $filters['status'], 'inactive' ); ?>><?php esc_html_e( 'Ngừng sử dụng', 'power-schedule-manager' ); ?></option>
					</select>
					<?php submit_button( __( 'Lọc', 'power-schedule-manager' ), 'secondary', 'filter_action', false ); ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Đặt lại', 'power-schedule-manager' ); ?></a>
				</form>
				<p class="description">
					<?php
					printf(
						/* translators: %d: Number of matching places. */
						esc_html__( 'Tìm thấy %d địa điểm. Bí danh và tên cũ đều có thể tìm kiếm.', 'power-schedule-manager' ),
						(int) $listing['total']
					);
					?>
				</p>
				<table class="widefat striped psm-place-library-table"><thead><tr>
					<th><?php esc_html_e( 'Tên chuẩn', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Đơn vị', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Bí danh', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Hình học', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Lịch liên kết', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?></th>
					<th class="psm-place-actions-column"><?php esc_html_e( 'Thao tác', 'power-schedule-manager' ); ?></th>
				</tr></thead><tbody>
				<?php if ( array() === $places ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Chưa có địa điểm phù hợp.', 'power-schedule-manager' ); ?></td></tr>
				<?php else : foreach ( $places as $place ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::EDIT_MENU_SLUG, 'place_id' => (int) $place['id'] ), admin_url( 'admin.php' ) ) ); ?>"><strong><?php echo esc_html( (string) $place['canonical_name'] ); ?></strong></a></td>
						<td><code><?php echo esc_html( (string) $place['unit_code'] ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', (array) $place['aliases'] ) ); ?></td>
						<td><?php echo esc_html( ! empty( $place['geojson'] ) ? 'GeoJSON' : ( null !== $place['center_lat'] ? 'Tọa độ' : '—' ) ); ?></td>
						<td>
							<?php $usage_count = absint( $place['usage_count'] ?? 0 ); ?>
							<?php if ( $usage_count > 0 ) : ?>
								<?php echo esc_html( number_format_i18n( $usage_count ) ); ?>
							<?php else : ?>
								<span class="psm-place-status psm-place-status--pending">
									<?php esc_html_e( 'Chưa khớp', 'power-schedule-manager' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$place_status = (string) $place['status'];
							$status_label = match ( $place_status ) {
									'active' => __( 'Đang dùng', 'power-schedule-manager' ),
									'pending' => __( 'Chờ hoàn thiện', 'power-schedule-manager' ),
									default => __( 'Ngừng dùng', 'power-schedule-manager' ),
								};
							?>
							<span class="psm-place-status psm-place-status--<?php echo esc_attr( $place_status ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						</td>
						<td class="psm-place-actions-cell">
							<div class="psm-place-row-actions">
								<a class="button button-small psm-place-action-icon" href="<?php echo esc_url( add_query_arg( array( 'page' => self::EDIT_MENU_SLUG, 'place_id' => (int) $place['id'] ), admin_url( 'admin.php' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Sửa địa điểm', 'power-schedule-manager' ); ?>" title="<?php esc_attr_e( 'Sửa địa điểm', 'power-schedule-manager' ); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Sửa địa điểm', 'power-schedule-manager' ); ?></span>
								</a>
								<?php if ( ! empty( $place['geojson'] ) || ( null !== $place['center_lat'] && null !== $place['center_lng'] ) ) : ?>
									<a
										class="button button-small psm-place-view-map psm-place-action-icon"
										href="<?php echo esc_url( add_query_arg( array( 'page' => self::EDIT_MENU_SLUG, 'place_id' => (int) $place['id'], 'view_map' => 1 ), admin_url( 'admin.php' ) ) . '#psm-place-map-preview' ); ?>"
										aria-label="<?php esc_attr_e( 'Xem bản đồ', 'power-schedule-manager' ); ?>"
										title="<?php esc_attr_e( 'Xem bản đồ', 'power-schedule-manager' ); ?>"
									>
										<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'Xem bản đồ', 'power-schedule-manager' ); ?></span>
									</a>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( sprintf( __( 'Xóa địa điểm này và gỡ %d liên kết lịch?', 'power-schedule-manager' ), $usage_count ) ); ?>');">
									<input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_ACTION ); ?>">
									<input type="hidden" name="place_id" value="<?php echo esc_attr( (string) absint( $place['id'] ) ); ?>">
									<?php wp_nonce_field( self::DELETE_ACTION . '_' . absint( $place['id'] ), 'psm_place_delete_nonce' ); ?>
									<button type="submit" class="button button-small psm-place-action-icon psm-place-action-delete" aria-label="<?php esc_attr_e( 'Xóa địa điểm', 'power-schedule-manager' ); ?>" title="<?php esc_attr_e( 'Xóa địa điểm', 'power-schedule-manager' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Xóa địa điểm', 'power-schedule-manager' ); ?></span></button>
								</form>
							</div>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody></table>
				<?php
				$pagination_base = add_query_arg(
					array(
						'page'         => self::MENU_SLUG,
						'place_search' => $filters['search'],
						'place_unit'   => $filters['unit_code'],
						'place_type'   => $filters['type'],
						'place_status' => $filters['status'],
						'paged'        => 999999999,
					),
					admin_url( 'admin.php' )
				);
				$pagination = paginate_links(
					array(
						'base'      => str_replace(
							'999999999',
							'%#%',
							$pagination_base
						),
						'format'    => '',
						'current'   => (int) $listing['page'],
						'total'     => (int) $listing['total_pages'],
						'type'      => 'list',
						'prev_text' => '‹',
						'next_text' => '›',
					)
				);
				if ( is_string( $pagination ) && '' !== $pagination ) :
					?>
					<nav class="tablenav-pages psm-place-pagination" aria-label="<?php esc_attr_e( 'Phân trang thư viện', 'power-schedule-manager' ); ?>">
						<?php echo wp_kses_post( $pagination ); ?>
					</nav>
				<?php endif; ?>
			</section>

		</div>
		<?php
	}

	/**
	 * Render the dedicated add/edit screen.
	 *
	 * @return void
	 */
	public function render_editor_page(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die(
				esc_html__(
					'Bạn không có quyền quản lý thư viện bản đồ.',
					'power-schedule-manager'
				)
			);
		}

		$edit_id = isset( $_GET['place_id'] )
			? absint( $_GET['place_id'] )
			: 0;
		$editing = $edit_id > 0 ? self::find( $edit_id ) : null;

		if ( $edit_id > 0 && null === $editing ) {
			wp_die(
				esc_html__(
					'Địa điểm không tồn tại hoặc đã bị xóa.',
					'power-schedule-manager'
				),
				esc_html__( 'Không tìm thấy địa điểm', 'power-schedule-manager' ),
				array( 'response' => 404 )
			);
		}

		$units = Power_Schedule_Manager_Units::all();
		?>
		<div class="wrap psm-admin-wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( null === $editing ? __( 'Thêm địa điểm', 'power-schedule-manager' ) : __( 'Cập nhật địa điểm', 'power-schedule-manager' ) ); ?>
			</h1>
			<a class="page-title-action" href="<?php echo esc_url( add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'Quay lại thư viện', 'power-schedule-manager' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['psm_error'] ) ) : ?>
				<div class="notice notice-error"><p>
					<?php echo esc_html( self::save_error_message( self::request_text( 'psm_error' ) ) ); ?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<input type="hidden" name="place_id" value="<?php echo esc_attr( (string) $edit_id ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, 'psm_place_nonce' ); ?>

				<div class="psm-place-editor-layout">
					<div class="psm-place-editor-main">
						<div class="psm-place-editor-toolbar" aria-label="<?php esc_attr_e( 'Quy trình cập nhật địa điểm', 'power-schedule-manager' ); ?>">
							<span><strong>1</strong><?php esc_html_e( 'Chọn đơn vị', 'power-schedule-manager' ); ?></span>
							<span><strong>2</strong><?php esc_html_e( 'Tìm đúng tuyến hoặc địa giới', 'power-schedule-manager' ); ?></span>
							<span><strong>3</strong><?php esc_html_e( 'Lưu và tự liên kết lịch', 'power-schedule-manager' ); ?></span>
						</div>

						<section class="psm-dashboard-panel psm-place-identity">
							<div class="psm-place-section-heading">
								<span class="psm-place-heading-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" focusable="false">
										<path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/>
									</svg>
								</span>
								<div>
									<h2><?php esc_html_e( 'Thông tin địa điểm', 'power-schedule-manager' ); ?></h2>
									<p><?php esc_html_e( 'Tên chuẩn được dùng để nhận diện và liên kết lịch điện.', 'power-schedule-manager' ); ?></p>
								</div>
							</div>
							<label class="psm-editor-label" for="psm-place-name"><?php esc_html_e( 'Tên chuẩn', 'power-schedule-manager' ); ?></label>
							<input id="psm-place-name" class="large-text psm-place-title-input" type="text" maxlength="191" name="canonical_name" required autofocus value="<?php echo esc_attr( (string) ( $editing['canonical_name'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Ví dụ: Đường Vạn Kiếp', 'power-schedule-manager' ); ?>">
							<p class="description"><?php esc_html_e( 'Dùng tên chính thức đang được sử dụng. Nếu đường đổi tên, giữ tên cũ ở phần Bí danh.', 'power-schedule-manager' ); ?></p>
						</section>

						<section class="psm-dashboard-panel psm-place-map-panel">
							<div class="psm-place-section-heading">
								<span class="psm-place-heading-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" focusable="false">
										<path d="m15 5-6-3-7 3.5v16L9 18l6 3 7-3.5v-16L15 5Zm-7 11.4-4 2V6.7l4-2v11.7Zm6 2.1-4-2V4.6l4 2v11.9Zm6-2.2-4 2V6.6l4-2v11.7Z"/>
									</svg>
								</span>
								<div>
									<h2><?php esc_html_e( 'Bản đồ tuyến và khu vực', 'power-schedule-manager' ); ?></h2>
									<p><?php esc_html_e( 'Tìm tuyến đường hoặc địa giới từ OSM, kiểm tra trực quan rồi mới lưu.', 'power-schedule-manager' ); ?></p>
								</div>
							</div>
							<div
								class="psm-osm-road-importer"
								data-psm-osm-importer
								data-auto-open="<?php echo isset( $_GET['view_map'] ) ? '1' : '0'; ?>"
							>
								<div class="psm-osm-road-importer__header">
									<div>
										<h3><?php esc_html_e( 'Nhập tuyến đường hoặc địa giới từ OpenStreetMap', 'power-schedule-manager' ); ?></h3>
										<p><?php esc_html_e( 'Plugin tìm đúng tên trong phạm vi đơn vị điện lực, hỗ trợ đường, polygon và multipolygon relation/way.', 'power-schedule-manager' ); ?></p>
									</div>
									<span class="psm-osm-badge">OpenStreetMap</span>
								</div>
								<div class="psm-osm-road-primary">
									<p>
										<label for="psm-osm-search-type"><?php esc_html_e( 'Loại dữ liệu', 'power-schedule-manager' ); ?></label>
										<select id="psm-osm-search-type" class="widefat">
											<option value="road" <?php selected( (string) ( $editing['location_type'] ?? 'road_segment' ), 'road_segment' ); ?>><?php esc_html_e( 'Tuyến đường', 'power-schedule-manager' ); ?></option>
											<option value="area" <?php selected( (string) ( $editing['location_type'] ?? '' ), 'area' ); ?>><?php esc_html_e( 'Địa giới / khu vực', 'power-schedule-manager' ); ?></option>
										</select>
									</p>
									<p>
										<label for="psm-osm-road-name" data-psm-osm-name-label><?php esc_html_e( 'Tên trên OSM', 'power-schedule-manager' ); ?></label>
										<input id="psm-osm-road-name" class="widefat" type="text" maxlength="120" value="<?php echo esc_attr( preg_replace( '/^\s*đường\s+/iu', '', (string) ( $editing['canonical_name'] ?? '' ) ) ); ?>" placeholder="<?php esc_attr_e( 'Ví dụ: Lộc Châu', 'power-schedule-manager' ); ?>">
									</p>
									<p data-psm-osm-locality-wrap>
										<label for="psm-osm-locality"><?php esc_html_e( 'Thuộc địa phương', 'power-schedule-manager' ); ?></label>
										<input id="psm-osm-locality" class="widefat" type="text" maxlength="120" placeholder="<?php esc_attr_e( 'Ví dụ: Bảo Lộc', 'power-schedule-manager' ); ?>">
										<small><?php esc_html_e( 'Tên địa giới cha trên OSM, nên nhập khi có địa danh trùng.', 'power-schedule-manager' ); ?></small>
									</p>
									<button type="button" class="button button-primary" data-psm-osm-search>
										<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
										<span data-psm-osm-search-label><?php esc_html_e( 'Tìm và xem trước', 'power-schedule-manager' ); ?></span>
									</button>
								</div>
								<div class="psm-osm-workflow-note">
									<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
									<p><?php esc_html_e( 'Tên có thể trùng ở nhiều tỉnh/thành. Truy vấn luôn bị giới hạn bởi đơn vị điện lực ở phía máy chủ; với địa giới, có thể giới hạn thêm bằng địa phương cha.', 'power-schedule-manager' ); ?></p>
								</div>
								<details class="psm-osm-bounds">
									<summary><?php esc_html_e( 'Phạm vi tìm kiếm nâng cao', 'power-schedule-manager' ); ?></summary>
									<p class="description"><?php esc_html_e( 'Phạm vi được tự động thu hẹp theo các địa điểm đã xác nhận của đơn vị điện lực. Khi thư viện còn trống, hãy phóng bản đồ tới đúng địa phương rồi chọn “Dùng vùng bản đồ đang xem” để phân biệt đường trùng tên.', 'power-schedule-manager' ); ?></p>
									<div class="psm-osm-road-grid">
										<p><label for="psm-osm-south"><?php esc_html_e( 'Vĩ độ Nam', 'power-schedule-manager' ); ?></label><input id="psm-osm-south" class="widefat" type="number" step="0.000001"></p>
										<p><label for="psm-osm-west"><?php esc_html_e( 'Kinh độ Tây', 'power-schedule-manager' ); ?></label><input id="psm-osm-west" class="widefat" type="number" step="0.000001"></p>
										<p><label for="psm-osm-north"><?php esc_html_e( 'Vĩ độ Bắc', 'power-schedule-manager' ); ?></label><input id="psm-osm-north" class="widefat" type="number" step="0.000001"></p>
										<p><label for="psm-osm-east"><?php esc_html_e( 'Kinh độ Đông', 'power-schedule-manager' ); ?></label><input id="psm-osm-east" class="widefat" type="number" step="0.000001"></p>
									</div>
									<button type="button" class="button psm-osm-use-map-bounds" data-psm-osm-use-map-bounds>
										<span class="dashicons dashicons-editor-expand" aria-hidden="true"></span>
										<?php esc_html_e( 'Dùng vùng bản đồ đang xem', 'power-schedule-manager' ); ?>
									</button>
								</details>
								<div class="psm-osm-road-feedback">
									<span class="spinner" data-psm-osm-spinner></span>
									<span class="psm-osm-road-result" data-psm-osm-result role="status" aria-live="polite"></span>
								</div>
								<div class="psm-osm-candidates" data-psm-osm-candidates hidden></div>
								<p class="psm-map-pick-help">
									<span class="dashicons dashicons-move" aria-hidden="true"></span>
									<?php esc_html_e( 'Kéo bản đồ để kiểm tra toàn tuyến hoặc ranh giới. Nhấp vào vị trí phù hợp hoặc kéo ghim để chọn điểm đại diện hiển thị trên frontend.', 'power-schedule-manager' ); ?>
								</p>
								<div id="psm-place-map-preview" class="psm-osm-preview" data-psm-osm-preview></div>
							</div>
							<details class="psm-place-technical">
								<summary><?php esc_html_e( 'Dữ liệu kỹ thuật', 'power-schedule-manager' ); ?></summary>
								<p class="description"><?php esc_html_e( 'Chỉ chỉnh thủ công khi bạn hiểu rõ tọa độ và GeoJSON.', 'power-schedule-manager' ); ?></p>
								<div class="psm-coordinate-grid">
									<p><label for="psm-place-lat"><?php esc_html_e( 'Vĩ độ', 'power-schedule-manager' ); ?></label>
									<input id="psm-place-lat" class="widefat" type="number" step="0.0000001" min="-90" max="90" name="center_lat" placeholder="11.9404" value="<?php echo esc_attr( (string) ( $editing['center_lat'] ?? '' ) ); ?>"></p>
									<p><label for="psm-place-lng"><?php esc_html_e( 'Kinh độ', 'power-schedule-manager' ); ?></label>
									<input id="psm-place-lng" class="widefat" type="number" step="0.0000001" min="-180" max="180" name="center_lng" placeholder="108.4583" value="<?php echo esc_attr( (string) ( $editing['center_lng'] ?? '' ) ); ?>"></p>
									<p><label for="psm-place-zoom"><?php esc_html_e( 'Mức thu phóng', 'power-schedule-manager' ); ?></label>
									<input id="psm-place-zoom" class="widefat" type="number" min="1" max="20" name="default_zoom" value="<?php echo esc_attr( (string) ( $editing['default_zoom'] ?? 15 ) ); ?>"></p>
								</div>
								<label class="psm-editor-label" for="psm-place-geojson"><?php esc_html_e( 'GeoJSON đoạn đường hoặc khu vực', 'power-schedule-manager' ); ?></label>
								<textarea id="psm-place-geojson" class="large-text code" rows="8" name="geojson"><?php echo esc_textarea( (string) ( $editing['geojson'] ?? '' ) ); ?></textarea>
							</details>
						</section>

						<section class="psm-dashboard-panel psm-place-meta-panel">
							<div class="psm-place-section-heading">
								<span class="psm-place-heading-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" focusable="false">
										<path d="M21.4 11.6 12.4 2.6A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 .6 1.4l9 9a2 2 0 0 0 2.8 0l7-7a2 2 0 0 0 0-2.8ZM7 8.5A1.5 1.5 0 1 1 7 5a1.5 1.5 0 0 1 0 3.5Z"/>
									</svg>
								</span>
								<div>
									<h2><?php esc_html_e( 'Tên khác và ghi chú', 'power-schedule-manager' ); ?></h2>
									<p><?php esc_html_e( 'Giúp plugin nhận diện cùng một địa điểm từ nhiều cách viết.', 'power-schedule-manager' ); ?></p>
								</div>
							</div>
							<label class="psm-editor-label" for="psm-place-aliases"><?php esc_html_e( 'Bí danh', 'power-schedule-manager' ); ?></label>
							<textarea id="psm-place-aliases" class="large-text" rows="4" name="aliases"><?php echo esc_textarea( implode( "\n", (array) ( $editing['aliases'] ?? array() ) ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Mỗi dòng một tên cũ, tên viết tắt hoặc cách viết khác.', 'power-schedule-manager' ); ?></p>
							<label class="psm-editor-label" for="psm-place-description"><?php esc_html_e( 'Mô tả nội bộ', 'power-schedule-manager' ); ?></label>
							<textarea id="psm-place-description" class="large-text" rows="3" name="description"><?php echo esc_textarea( (string) ( $editing['description'] ?? '' ) ); ?></textarea>
						</section>
					</div>

					<aside class="psm-place-editor-side">
						<section class="psm-dashboard-panel psm-place-publish-panel">
							<h2><?php esc_html_e( 'Thiết lập và lưu', 'power-schedule-manager' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Chọn phạm vi quản lý trước khi lưu địa điểm.', 'power-schedule-manager' ); ?></p>
							<?php if ( is_array( $editing ) ) : ?>
								<div class="psm-place-editor-summary">
									<span><small><?php esc_html_e( 'Đang dùng trong', 'power-schedule-manager' ); ?></small><strong><?php echo esc_html( number_format_i18n( absint( $editing['usage_count'] ?? 0 ) ) ); ?> <?php esc_html_e( 'lịch', 'power-schedule-manager' ); ?></strong></span>
									<span><small><?php esc_html_e( 'Cập nhật UTC', 'power-schedule-manager' ); ?></small><strong><?php echo esc_html( (string) ( $editing['updated_at_utc'] ?? '—' ) ); ?></strong></span>
								</div>
							<?php endif; ?>
							<p><label for="psm-place-unit"><?php esc_html_e( 'Đơn vị điện lực', 'power-schedule-manager' ); ?></label>
							<select id="psm-place-unit" class="widefat" name="unit_code" required>
								<option value=""><?php esc_html_e( '— Chọn đơn vị —', 'power-schedule-manager' ); ?></option>
								<?php foreach ( $units as $unit ) : ?>
									<?php if ( ! is_array( $unit ) ) { continue; } ?>
									<option value="<?php echo esc_attr( (string) $unit['code'] ); ?>" <?php selected( (string) ( $editing['unit_code'] ?? '' ), (string) $unit['code'] ); ?>><?php echo esc_html( (string) $unit['name'] ); ?></option>
								<?php endforeach; ?>
							</select></p>

							<p><label for="psm-place-type"><?php esc_html_e( 'Loại vị trí', 'power-schedule-manager' ); ?></label>
							<select id="psm-place-type" class="widefat" name="location_type">
								<?php foreach ( array( 'road_segment' => 'Toàn tuyến đường', 'area' => 'Khu vực', 'point' => 'Điểm', 'facility' => 'Công trình' ) as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $editing['location_type'] ?? 'road_segment' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></p>

							<p><label for="psm-place-status"><?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?></label>
							<select id="psm-place-status" class="widefat" name="status">
								<option value="active" <?php selected( (string) ( $editing['status'] ?? 'active' ), 'active' ); ?>><?php esc_html_e( 'Đang sử dụng', 'power-schedule-manager' ); ?></option>
								<option value="pending" <?php selected( (string) ( $editing['status'] ?? '' ), 'pending' ); ?>><?php esc_html_e( 'Chờ bổ sung bản đồ', 'power-schedule-manager' ); ?></option>
								<option value="inactive" <?php selected( (string) ( $editing['status'] ?? '' ), 'inactive' ); ?>><?php esc_html_e( 'Ngừng sử dụng', 'power-schedule-manager' ); ?></option>
							</select></p>

							<div class="psm-place-save-actions">
								<?php submit_button( null === $editing ? __( 'Thêm địa điểm', 'power-schedule-manager' ) : __( 'Lưu thay đổi', 'power-schedule-manager' ), 'primary', 'submit', false ); ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Hủy', 'power-schedule-manager' ); ?></a>
							</div>
						</section>

						<section class="psm-dashboard-panel psm-place-help">
							<h2><?php esc_html_e( 'Lấy dữ liệu bản đồ', 'power-schedule-manager' ); ?></h2>
							<ol>
								<li><?php esc_html_e( 'Chọn tuyến đường hoặc địa giới/khu vực, rồi nhập đúng tên đang có trên OSM.', 'power-schedule-manager' ); ?></li>
								<li><?php esc_html_e( 'Chọn đúng đơn vị điện lực; với địa giới trùng tên, nhập thêm địa phương cha hoặc thu hẹp vùng bản đồ.', 'power-schedule-manager' ); ?></li>
								<li><?php esc_html_e( 'Xem trước hình học, chọn đúng ứng viên rồi lưu. GeoJSON và điểm đại diện sẽ được dùng lại cho các lịch phù hợp.', 'power-schedule-manager' ); ?></li>
							</ol>
						</section>
					</aside>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Save an administration form submission.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die( esc_html__( 'Không đủ quyền.', 'power-schedule-manager' ) );
		}

		check_admin_referer( self::NONCE_ACTION, 'psm_place_nonce' );

		$aliases = isset( $_POST['aliases'] )
			? preg_split(
				'/\R/u',
				sanitize_textarea_field(
					self::post_scalar( 'aliases' )
				)
			)
			: array();

		try {
			$place_id = self::save(
				array(
					'id'             => absint( $_POST['place_id'] ?? 0 ),
					'unit_code'      => self::post_scalar( 'unit_code' ),
					'canonical_name' => self::post_scalar( 'canonical_name' ),
					'aliases'        => is_array( $aliases ) ? $aliases : array(),
					'location_type'  => self::post_scalar( 'location_type' ),
					'description'    => self::post_scalar( 'description' ),
					'geojson'        => self::post_scalar( 'geojson' ),
					'center_lat'     => self::post_scalar( 'center_lat' ),
					'center_lng'     => self::post_scalar( 'center_lng' ),
					'default_zoom'   => self::post_scalar( 'default_zoom', '15' ),
					'status'         => self::post_scalar( 'status', 'active' ),
				)
			);

			$saved_place = self::find( $place_id );
			$relinked = is_array( $saved_place )
				? self::relink_place_events(
					$place_id,
					absint( $saved_place['unit_id'] ?? 0 )
				)
				: 0;
		} catch ( Throwable $throwable ) {
			global $wpdb;

			Power_Schedule_Manager_Logger::error(
				'place_save_failed',
				$throwable,
				array(
					'exception'      => $throwable::class,
					'database_error' => (string) $wpdb->last_error,
				)
			);

			$error_code = sanitize_key( $throwable->getMessage() );

			if ( '' === $error_code ) {
				$error_code = 'place_save_failed';
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => self::EDIT_MENU_SLUG,
						'place_id'  => absint( $_POST['place_id'] ?? 0 ),
						'psm_error' => $error_code,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
					array(
						'page'             => self::MENU_SLUG,
						'psm_saved'        => 1,
						'psm_linked'       => $relinked,
						'psm_place_id'     => $place_id,
						'psm_place_status' => sanitize_key(
							(string) (
								$saved_place['status'] ?? ''
							)
						),
					),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete one place and its aliases/event links in one transaction.
	 */
	public function handle_delete(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die( esc_html__( 'Không đủ quyền.', 'power-schedule-manager' ) );
		}

		$place_id = absint( $_POST['place_id'] ?? 0 );
		check_admin_referer(
			self::DELETE_ACTION . '_' . $place_id,
			'psm_place_delete_nonce'
		);

		try {
			self::delete( $place_id );
		} catch ( Throwable $throwable ) {
			Power_Schedule_Manager_Logger::error(
				'place_delete_failed',
				$throwable,
				array( 'place_id' => $place_id )
			);
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => self::MENU_SLUG,
						'psm_error' => 'place_delete_failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'psm_deleted' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete one place and dependent rows.
	 */
	public static function delete( int $place_id ): void {
		if ( $place_id < 1 || null === self::find( $place_id ) ) {
			throw new InvalidArgumentException( 'place_not_found' );
		}

		global $wpdb;
		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$aliases = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACE_ALIASES
		);
		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			throw new RuntimeException( 'place_delete_transaction_failed' );
		}

		try {
			if (
				false === $wpdb->delete(
					$links,
					array( 'place_id' => $place_id ),
					array( '%d' )
				)
				|| false === $wpdb->delete(
					$aliases,
					array( 'place_id' => $place_id ),
					array( '%d' )
				)
				|| false === $wpdb->delete(
					$places,
					array( 'id' => $place_id ),
					array( '%d' )
				)
			) {
				throw new RuntimeException( 'place_delete_failed' );
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'place_delete_commit_failed' );
			}
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );
			throw $throwable;
		}

		self::$active_place_names_cache = array();
		Power_Schedule_Manager_Cache::invalidate_all();
	}

	/**
	 * Re-evaluate automatic event-place links for all active places.
	 *
	 * @return void
	 */
	public function handle_relink(): void {
		if (
			! current_user_can(
				Power_Schedule_Manager_Capabilities::MANAGE_SETTINGS
			)
		) {
			wp_die( esc_html__( 'Không đủ quyền.', 'power-schedule-manager' ) );
		}

		check_admin_referer(
			self::RELINK_NONCE_ACTION,
			'psm_relink_nonce'
		);

		try {
			$linked = self::relink_all_active_places();
		} catch ( Throwable $throwable ) {
			Power_Schedule_Manager_Logger::error(
				'place_relink_failed',
				$throwable
			);

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => self::MENU_SLUG,
						'psm_error' => 'place_relink_failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::MENU_SLUG,
					'psm_relinked' => 1,
					'psm_linked'   => $linked,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save or update one persistent place.
	 *
	 * @param array<string,mixed> $data Place data.
	 * @return int
	 */
	public static function save( array $data ): int {
		global $wpdb;

		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$data['unit_code'] ?? ''
		);
		$unit = Power_Schedule_Manager_Units::find_by_code( $unit_code );

		if ( null === $unit ) {
			throw new InvalidArgumentException( 'invalid_place_unit' );
		}

		$name = sanitize_text_field(
			(string) ( $data['canonical_name'] ?? '' )
		);

		if ( '' === $name ) {
			throw new InvalidArgumentException( 'invalid_place_name' );
		}

		$type = sanitize_key(
			(string) ( $data['location_type'] ?? 'road_segment' )
		);

		if ( ! in_array( $type, array( 'road_segment', 'area', 'point', 'facility' ), true ) ) {
			$type = 'road_segment';
		}

		$geojson = trim( (string) ( $data['geojson'] ?? '' ) );
		$lat = null;
		$lng = null;
		$submitted_center = null;

		if (
			is_numeric( $data['center_lat'] ?? null )
			&& is_numeric( $data['center_lng'] ?? null )
		) {
			$submitted_center =
				Power_Schedule_Manager_GeoJSON::sanitize_center(
					(float) $data['center_lat'],
					(float) $data['center_lng']
				);
		}

		if ( '' !== $geojson ) {
			$geojson = Power_Schedule_Manager_GeoJSON::sanitize( $geojson );
			$center = is_array( $submitted_center )
				? $submitted_center
				: Power_Schedule_Manager_GeoJSON::calculate_center( $geojson );
			$lat = $center['lat'];
			$lng = $center['lng'];
		} elseif ( is_array( $submitted_center ) ) {
			$center = $submitted_center;
			$lat = $center['lat'];
			$lng = $center['lng'];
			$geojson = null;
		} else {
			$geojson = null;
		}

		$place_id = absint( $data['id'] ?? 0 );
		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$now = Power_Schedule_Manager_Database::utc_now();
		$hash = self::name_hash( $name );
		$previous_name = '';
		$previous_unit_id = 0;
		$preserved_aliases = array();

		if ( $place_id > 0 ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, unit_id, canonical_name
					FROM {$places}
					WHERE id = %d
					LIMIT 1",
					$place_id
				),
				ARRAY_A
			);

			if ( ! is_array( $existing ) ) {
				throw new InvalidArgumentException( 'place_not_found' );
			}

			$previous_name = (string) $existing['canonical_name'];
			$previous_unit_id = absint( $existing['unit_id'] );
			$preserved_aliases = self::aliases( $place_id );

			if ( $previous_unit_id !== (int) $unit['id'] ) {
				$links_table =
					Power_Schedule_Manager_Database::table(
						Power_Schedule_Manager_Database::EVENT_PLACES
					);
				$link_count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*)
						FROM {$links_table}
						WHERE place_id = %d",
						$place_id
					)
				);

				if ( $link_count > 0 ) {
					throw new InvalidArgumentException(
						'place_unit_has_links'
					);
				}
			}

			$duplicate_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$places}
					WHERE unit_id = %d
						AND normalized_hash = UNHEX(%s)
						AND id <> %d
					LIMIT 1",
					(int) $unit['id'],
					$hash,
					$place_id
				)
			);

			if ( $duplicate_id > 0 ) {
				throw new InvalidArgumentException(
					'duplicate_place_name'
				);
			}
		} else {
			$place_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$places}
					WHERE unit_id = %d AND normalized_hash = UNHEX(%s)
					LIMIT 1",
					(int) $unit['id'],
					$hash
				)
			);

			if ( $place_id > 0 ) {
				$previous_unit_id = (int) $unit['id'];
				$previous_name = (string) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT canonical_name
						FROM {$places}
						WHERE id = %d
						LIMIT 1",
						$place_id
					)
				);
				$preserved_aliases = self::aliases( $place_id );
			}
		}

		$status = in_array(
			$data['status'] ?? '',
			array( 'active', 'pending', 'inactive' ),
			true
		)
			? (string) $data['status']
			: 'active';

		if (
			'active' === $status
			&& null === $geojson
			&& ( null === $lat || null === $lng )
		) {
			$status = 'pending';
		}

		$row = array(
			'unit_id'         => (int) $unit['id'],
			'unit_code'       => $unit_code,
			'canonical_name'  => $name,
			'normalized_hash' => hex2bin( $hash ),
			'location_type'   => $type,
			'description'     => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'geojson'         => $geojson,
			'center_lat'      => $lat,
			'center_lng'      => $lng,
			'default_zoom'    => Power_Schedule_Manager_GeoJSON::sanitize_zoom( (int) ( $data['default_zoom'] ?? 15 ) ),
			'status'          => $status,
			'updated_at_utc'  => $now,
		);
		$submitted_aliases = array_merge(
			(array) ( $data['aliases'] ?? array() ),
			$preserved_aliases
		);

		if ( '' !== $previous_name && $previous_name !== $name ) {
			$submitted_aliases[] = $previous_name;
		}

		self::assert_aliases_available(
			$place_id,
			(int) $unit['id'],
			array_merge( array( $name ), $submitted_aliases )
		);

		if ( $place_id > 0 ) {
			if (
				false === $wpdb->update(
					$places,
					$row,
					array(
						'id'      => $place_id,
						'unit_id' => $previous_unit_id,
					)
				)
			) {
				throw new RuntimeException( 'place_update_failed' );
			}
		} else {
			$row['created_at_utc'] = $now;
			if ( false === $wpdb->insert( $places, $row ) ) {
				throw new RuntimeException( 'place_insert_failed' );
			}
			$place_id = Power_Schedule_Manager_Database::insert_id();
		}

		self::replace_aliases(
			$place_id,
			(int) $unit['id'],
			$name,
			$submitted_aliases
		);

		Power_Schedule_Manager_Cache::invalidate_all();
		return $place_id;
	}

	/**
	 * Discover explicitly labelled road names from an imported area.
	 *
	 * Candidates are stored without fabricated coordinates and remain pending
	 * until an administrator reviews their geometry.
	 *
	 * @param string $unit_code Unit code.
	 * @param string $area Event area text.
	 * @return int Number of newly created candidates.
	 */
	public static function discover_from_area(
		string $unit_code,
		string $area
	): int {
		global $wpdb;

		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$unit_code
		);
		$unit = Power_Schedule_Manager_Units::find_by_code( $unit_code );

		if ( null === $unit || '' === trim( $area ) ) {
			return 0;
		}

		$matched = preg_match_all(
			'/(?<![\p{L}\p{N}])đường\s+(.+?)'
			. '(?=…|\.|;\s*|-\s*(?:phường|xã|thị\s*trấn)'
			. '(?![\p{L}\p{N}])|\z)/iu',
			$area,
			$matches
		);

		if (
			false === $matched
			|| 0 === $matched
			|| ! isset( $matches[1] )
			|| ! is_array( $matches[1] )
		) {
			return 0;
		}

		$names = array();

		foreach ( $matches[1] as $group ) {
			$parts = preg_split( '/\s*,\s*/u', (string) $group );

			if ( ! is_array( $parts ) ) {
				continue;
			}

			foreach ( $parts as $name ) {
				$name = trim(
					sanitize_text_field( $name ),
					" \t\n\r\0\x0B-,"
				);

				$name_length = function_exists( 'mb_strlen' )
					? mb_strlen( $name )
					: strlen( $name );

				if (
					$name_length < 3
					|| $name_length > 120
					|| preg_match( '/\A\d+(?:\/\d+)*\z/u', $name )
				) {
					continue;
				}

				$names[ self::name_hash( $name ) ] =
					'Đường ' . $name;
			}
		}

		if ( array() === $names ) {
			return 0;
		}

		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$created = 0;

		foreach ( $names as $hash => $name ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$places}
					WHERE unit_id = %d
						AND normalized_hash = UNHEX(%s)
					LIMIT 1",
					(int) $unit['id'],
					$hash
				)
			);

			if ( $exists > 0 ) {
				continue;
			}

			try {
				self::save(
					array(
						'unit_code'      => $unit_code,
						'canonical_name' => $name,
						'location_type'  => 'road_segment',
						'status'         => 'pending',
					)
				);
			} catch ( InvalidArgumentException $exception ) {
				if (
					in_array(
						$exception->getMessage(),
						array(
							'duplicate_place_name',
							'duplicate_place_alias',
						),
						true
					)
				) {
					continue;
				}

				throw $exception;
			}
			++$created;
		}

		return $created;
	}

	/**
	 * Link known place aliases found in an event area.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $unit_code Stable electricity-unit code.
	 * @param string $area Event area.
	 * @return int Linked places.
	 */
	public static function attach_matches(
		int $event_id,
		string $unit_code,
		string $area
	): int {
		global $wpdb;

		$unit_code = strtoupper(
			sanitize_key( $unit_code )
		);

		if (
			$event_id < 1
			|| '' === $unit_code
			|| '' === trim( $area )
		) {
			return 0;
		}

		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);
		$place_names = self::active_place_names_for_unit_code(
			$unit_code
		);
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$links}
				WHERE event_id = %d AND link_source = 'auto'",
				$event_id
			)
		);

		if ( false === $deleted ) {
			throw new RuntimeException( 'automatic_place_link_delete_failed' );
		}

		if ( array() === $place_names ) {
			return 0;
		}

		$linked = 0;
		$now = Power_Schedule_Manager_Database::utc_now();
		$order = 0;

		foreach ( $place_names as $place_id => $names ) {
			$place_id = absint( $place_id );

			if (
				$place_id < 1
				|| ! self::area_contains_place_name(
					$area,
					$names
				)
			) {
				continue;
			}

			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$links}
						(event_id,place_id,sort_order,link_source,created_at_utc)
						VALUES (%d,%d,%d,'auto',%s)
						ON DUPLICATE KEY UPDATE
							place_id = place_id",
					$event_id,
					$place_id,
					$order,
					$now
				)
			);

			if ( false === $result ) {
				throw new RuntimeException(
					'automatic_place_link_insert_failed'
				);
			}

			/*
			 * The no-op duplicate branch preserves an existing manual
			 * relationship without relying on deprecated VALUES(column)
			 * syntax. Such a relationship is still a successful match.
			 */
			++$linked;
			++$order;
		}

		return $linked;
	}

	/**
	 * Re-evaluate existing events affected by one saved place.
	 *
	 * Events are narrowed by the stable indexed electricity-unit code, then matched
	 * in PHP with the same accent-insensitive and token-aware rules used by the
	 * importer. Avoiding an SQL LIKE prefilter prevents valid Vietnamese street
	 * names from being missed when a site uses a binary or accent-sensitive
	 * database collation.
	 *
	 * @param int $place_id Place ID.
	 * @param int $unit_id Legacy unit row ID retained for call compatibility.
	 *                     Matching uses the stable code stored on the place.
	 * @param bool $invalidate_cache Whether to invalidate cache after relinking.
	 * @return int Number of links matched.
	 */
	public static function relink_place_events(
		int $place_id,
		int $unit_id,
		bool $invalidate_cache = true
	): int {
		global $wpdb;

		if ( $place_id < 1 ) {
			return 0;
		}

		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);
		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$place = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT unit_id, unit_code, canonical_name, status
				FROM {$places}
				WHERE id = %d
				LIMIT 1",
				$place_id
			),
			ARRAY_A
		);

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$links}
				WHERE place_id = %d AND link_source = 'auto'",
				$place_id
			)
		);

		if ( false === $deleted ) {
			throw new RuntimeException(
				'automatic_place_link_delete_failed'
			);
		}

		if (
			! is_array( $place )
			|| '' === (string) ( $place['unit_code'] ?? '' )
			|| 'active' !== (string) ( $place['status'] ?? '' )
		) {
			if ( $invalidate_cache ) {
				Power_Schedule_Manager_Cache::invalidate_all();
			}

			return 0;
		}

		$names = array_values(
			array_unique(
				array_filter(
					array_merge(
						array(
							(string) (
								$place['canonical_name'] ?? ''
							),
						),
						self::aliases( $place_id )
					),
					static fn ( string $name ): bool =>
						'' !== trim( $name )
				)
			)
		);

		if ( array() === $names ) {
			if ( $invalidate_cache ) {
				Power_Schedule_Manager_Cache::invalidate_all();
			}

			return 0;
		}

		$linked = 0;
		$now = Power_Schedule_Manager_Database::utc_now();
		$unit_code = strtoupper(
			sanitize_key(
				(string) $place['unit_code']
			)
		);

		$last_event_id = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, area
					FROM {$events}
					WHERE unit_code = %s
						AND id > %d
						AND deleted_at_utc IS NULL
					ORDER BY id ASC
					LIMIT 1000",
					$unit_code,
					$last_event_id
				),
				ARRAY_A
			);

			if ( ! is_array( $rows ) ) {
				return $linked;
			}

			foreach ( $rows as $row ) {
				$event_id = absint( $row['id'] ?? 0 );
				$last_event_id = max(
					$last_event_id,
					$event_id
				);
				$area = (string) ( $row['area'] ?? '' );

				if (
					$event_id < 1
					|| ! self::area_contains_place_name(
						$area,
						$names
					)
				) {
					continue;
				}

				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$links}
							(event_id,place_id,sort_order,link_source,created_at_utc)
							VALUES (%d,%d,0,'auto',%s)
							ON DUPLICATE KEY UPDATE
								place_id = place_id",
						$event_id,
						$place_id,
						$now
					)
				);

				if ( false === $result ) {
					throw new RuntimeException(
						'automatic_place_link_insert_failed'
					);
				}

				++$linked;
			}
		} while ( 1000 === count( $rows ) );

		if ( $invalidate_cache ) {
			Power_Schedule_Manager_Cache::invalidate_all();
		}

		return $linked;
	}

	/**
	 * Re-evaluate links for every active library place.
	 *
	 * @return int Number of links matched.
	 */
	public static function relink_all_active_places(): int {
		global $wpdb;

		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$events = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENTS
		);
		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);
		$removed = $wpdb->query(
			"DELETE links
			FROM {$links} AS links
			INNER JOIN {$places} AS places
				ON places.id = links.place_id
			WHERE links.link_source = 'auto'
				AND places.status <> 'active'"
		);

		if ( false === $removed ) {
			throw new RuntimeException(
				'automatic_place_link_cleanup_failed'
			);
		}

		$unit_codes = $wpdb->get_col(
			"SELECT DISTINCT unit_code
			FROM {$places}
			WHERE status = 'active'
				AND unit_code <> ''
			ORDER BY unit_code ASC"
		);

		if ( ! is_array( $unit_codes ) ) {
			return 0;
		}

		$linked = 0;

		foreach ( array_map( 'strval', $unit_codes ) as $unit_code ) {
			$unit_code = strtoupper(
				sanitize_key( $unit_code )
			);

			if ( '' === $unit_code ) {
				continue;
			}

			$last_event_id = 0;

			do {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, area
						FROM {$events}
						WHERE unit_code = %s
							AND id > %d
							AND deleted_at_utc IS NULL
						ORDER BY id ASC
						LIMIT 500",
						$unit_code,
						$last_event_id
					),
					ARRAY_A
				);

				if ( ! is_array( $rows ) ) {
					break;
				}

				foreach ( $rows as $row ) {
					$event_id = absint( $row['id'] ?? 0 );
					$last_event_id = max(
						$last_event_id,
						$event_id
					);

					if ( $event_id < 1 ) {
						continue;
					}

					$linked += self::attach_matches(
						$event_id,
						$unit_code,
						(string) ( $row['area'] ?? '' )
					);
				}
			} while ( 500 === count( $rows ) );
		}

		Power_Schedule_Manager_Cache::invalidate_all();

		return $linked;
	}

	/**
	 * Replace event links with reusable places created by the map editor.
	 *
	 * @param int                              $event_id Event ID.
	 * @param string                           $unit_code Unit code.
	 * @param array<int,array<string,mixed>>   $locations Normalized locations.
	 * @return array<int,int> Place IDs.
	 */
	public static function replace_event_locations(
		int $event_id,
		string $unit_code,
		array $locations
	): array {
		global $wpdb;

		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);

		if ( false === $wpdb->delete( $links, array( 'event_id' => $event_id ), array( '%d' ) ) ) {
			throw new RuntimeException( 'event_place_delete_failed' );
		}

		$place_ids = array();
		$now = Power_Schedule_Manager_Database::utc_now();
		$row_alias =
			Power_Schedule_Manager_Database::upsert_row_alias();
		$sort_order_value =
			Power_Schedule_Manager_Database::upsert_value(
				'sort_order'
			);
		$link_source_value =
			Power_Schedule_Manager_Database::upsert_value(
				'link_source'
			);

		foreach ( $locations as $location ) {
			$place_id = self::save(
				array(
					'unit_code'      => $unit_code,
					'canonical_name' => $location['label'] ?? '',
					'location_type'  => $location['location_type'] ?? 'road_segment',
					'description'    => $location['description'] ?? '',
					'geojson'        => $location['geojson'] ?? '',
					'center_lat'     => $location['center_lat'] ?? '',
					'center_lng'     => $location['center_lng'] ?? '',
					'default_zoom'   => $location['default_zoom'] ?? 15,
					'status'         => 'active',
				)
			);

			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$links} (event_id,place_id,sort_order,link_source,created_at_utc)
						VALUES (%d,%d,%d,'manual',%s){$row_alias}
						ON DUPLICATE KEY UPDATE
							sort_order = {$sort_order_value},
							link_source = {$link_source_value}",
					$event_id,
					$place_id,
					absint( $location['sort_order'] ?? count( $place_ids ) ),
					$now
				)
			);

			if ( false === $result ) {
				throw new RuntimeException( 'event_place_insert_failed' );
			}

			$place_ids[] = $place_id;
		}

		return $place_ids;
	}

	/**
	 * Return linked reusable places for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function event_locations( int $event_id ): array {
		global $wpdb;
		$places = Power_Schedule_Manager_Database::table( Power_Schedule_Manager_Database::PLACES );
		$links = Power_Schedule_Manager_Database::table( Power_Schedule_Manager_Database::EVENT_PLACES );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT places.id, links.event_id, places.location_type,
					places.canonical_name AS label, places.description,
					places.geojson, places.center_lat, places.center_lng,
					places.default_zoom, links.sort_order,
					places.created_at_utc, places.updated_at_utc
				FROM {$links} AS links
				INNER JOIN {$places} AS places ON places.id = links.place_id
				WHERE links.event_id = %d AND places.status = 'active'
				ORDER BY links.sort_order ASC, places.id ASC",
				$event_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Query the place library with indexed filters and bounded pagination.
	 *
	 * Aliases are loaded for the entire result page in one query, avoiding the
	 * N+1 query pattern that becomes expensive with a large street library.
	 *
	 * @param array<string,mixed> $arguments Query arguments.
	 * @return array{
	 *     items: array<int,array<string,mixed>>,
	 *     total: int,
	 *     page: int,
	 *     per_page: int,
	 *     total_pages: int
	 * }
	 */
	public static function query( array $arguments = array() ): array {
		global $wpdb;

		$arguments = wp_parse_args(
			$arguments,
			array(
				'search'    => '',
				'unit_code' => '',
				'type'      => '',
				'status'    => '',
				'page'      => 1,
				'per_page'  => 25,
			)
		);
		$page = max( 1, absint( $arguments['page'] ) );
		$per_page = min( 100, max( 10, absint( $arguments['per_page'] ) ) );
		$offset = ( $page - 1 ) * $per_page;
		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$aliases = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACE_ALIASES
		);
		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);
		$where = array( '1 = 1' );
		$values = array();
		$search = sanitize_text_field( (string) $arguments['search'] );
		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$arguments['unit_code']
		);
		$type = sanitize_key( (string) $arguments['type'] );
		$status = sanitize_key( (string) $arguments['status'] );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$search_hash = self::name_hash( $search );
			$where[] = '(
				places.normalized_hash = UNHEX(%s)
				OR aliases.alias_hash = UNHEX(%s)
				OR places.canonical_name LIKE %s
				OR aliases.alias LIKE %s
			)';
			$values[] = $search_hash;
			$values[] = $search_hash;
			$values[] = $like;
			$values[] = $like;
		}

		if ( '' !== $unit_code ) {
			$where[] = 'places.unit_code = %s';
			$values[] = $unit_code;
		}

		if (
			in_array(
				$type,
				array( 'road_segment', 'area', 'point', 'facility' ),
				true
			)
		) {
			$where[] = 'places.location_type = %s';
			$values[] = $type;
		}

		if (
			in_array(
				$status,
				array( 'active', 'pending', 'inactive' ),
				true
			)
		) {
			$where[] = 'places.status = %s';
			$values[] = $status;
		}

		$where_sql = implode( ' AND ', $where );
		$join_sql = "LEFT JOIN {$aliases} AS aliases
			ON aliases.place_id = places.id";
		$count_sql = "SELECT COUNT(DISTINCT places.id)
			FROM {$places} AS places
			{$join_sql}
			WHERE {$where_sql}";
		$total = (int) (
			array() === $values
				? $wpdb->get_var( $count_sql )
				: $wpdb->get_var(
					$wpdb->prepare( $count_sql, $values )
				)
		);
		$query_values = array_merge( $values, array( $per_page, $offset ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT places.*,
					COALESCE(usage_stats.usage_count, 0) AS usage_count
				FROM {$places} AS places
				{$join_sql}
				LEFT JOIN (
					SELECT place_id, COUNT(*) AS usage_count
					FROM {$links}
					GROUP BY place_id
				) AS usage_stats ON usage_stats.place_id = places.id
				WHERE {$where_sql}
				ORDER BY places.unit_code ASC, places.canonical_name ASC
				LIMIT %d OFFSET %d",
				$query_values
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();
		self::hydrate_aliases( $rows );

		return array(
			'items'       => $rows,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	/**
	 * Return compact library counters with one database query.
	 *
	 * @return array{
	 *     total:int,
	 *     active:int,
	 *     mapped:int,
	 *     pending:int,
	 *     unlinked:int
	 * }
	 */
	public static function statistics(): array {
		global $wpdb;

		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$links = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::EVENT_PLACES
		);
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM(status = 'active') AS active,
				SUM(status = 'pending') AS pending,
				SUM(
					geojson IS NOT NULL
					OR (center_lat IS NOT NULL AND center_lng IS NOT NULL)
				) AS mapped
			FROM {$places}",
			ARRAY_A
		);
		$unlinked = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$places} AS places
			LEFT JOIN {$links} AS links
				ON links.place_id = places.id
			WHERE places.status = 'active'
				AND (
					places.geojson IS NOT NULL
					OR (
						places.center_lat IS NOT NULL
						AND places.center_lng IS NOT NULL
					)
				)
				AND links.place_id IS NULL"
		);

		return array(
			'total'   => absint( $row['total'] ?? 0 ),
			'active'  => absint( $row['active'] ?? 0 ),
			'mapped'  => absint( $row['mapped'] ?? 0 ),
			'pending' => absint( $row['pending'] ?? 0 ),
			'unlinked' => max( 0, $unlinked ),
		);
	}

	/**
	 * Hydrate aliases for a result page in one query.
	 *
	 * @param array<int,array<string,mixed>> $rows Place rows.
	 * @return void
	 */
	private static function hydrate_aliases( array &$rows ): void {
		global $wpdb;

		$ids = array_values(
			array_filter(
				array_unique(
					array_map(
						static fn ( array $row ): int =>
							absint( $row['id'] ?? 0 ),
						$rows
					)
				)
			)
		);

		if ( array() === $ids ) {
			return;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACE_ALIASES
		);
		$id_list = implode( ',', $ids );
		$alias_rows = $wpdb->get_results(
			"SELECT place_id, alias
			FROM {$table}
			WHERE place_id IN ({$id_list})
			ORDER BY place_id ASC, id ASC",
			ARRAY_A
		);
		$alias_map = array();

		if ( is_array( $alias_rows ) ) {
			foreach ( $alias_rows as $alias_row ) {
				$place_id = absint( $alias_row['place_id'] ?? 0 );
				$alias = sanitize_text_field(
					(string) ( $alias_row['alias'] ?? '' )
				);

				if ( $place_id > 0 && '' !== $alias ) {
					$alias_map[ $place_id ][] = $alias;
				}
			}
		}

		foreach ( $rows as &$row ) {
			$place_id = absint( $row['id'] ?? 0 );
			$row['aliases'] = $alias_map[ $place_id ] ?? array();
		}
		unset( $row );
	}

	/**
	 * Find one place.
	 *
	 * @param int $place_id Place ID.
	 * @return array<string,mixed>|null
	 */
	public static function find( int $place_id ): ?array {
		global $wpdb;
		$places = Power_Schedule_Manager_Database::table( Power_Schedule_Manager_Database::PLACES );
		$links = Power_Schedule_Manager_Database::table( Power_Schedule_Manager_Database::EVENT_PLACES );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT places.*,
					(SELECT COUNT(*) FROM {$links} AS links WHERE links.place_id = places.id) AS usage_count
				FROM {$places} AS places
				WHERE places.id = %d
				LIMIT 1",
				$place_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['aliases'] = self::aliases( $place_id );
		return $row;
	}

	/**
	 * Replace aliases while retaining the canonical name as an alias.
	 *
	 * @param int $place_id Place ID.
	 * @param int $unit_id Unit ID.
	 * @param string $canonical_name Canonical name.
	 * @param array<int,mixed> $aliases Aliases.
	 * @return void
	 */
	private static function replace_aliases( int $place_id, int $unit_id, string $canonical_name, array $aliases ): void {
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table( Power_Schedule_Manager_Database::PLACE_ALIASES );
		$names = array_merge( array( $canonical_name ), $aliases );
		$now = Power_Schedule_Manager_Database::utc_now();
		$seen = array();
		$row_alias =
			Power_Schedule_Manager_Database::upsert_row_alias();
		$alias_value =
			Power_Schedule_Manager_Database::upsert_value(
				'alias'
			);
		$updated_at_value =
			Power_Schedule_Manager_Database::upsert_value(
				'updated_at_utc'
			);

		foreach ( $names as $alias ) {
			$alias = sanitize_text_field( (string) $alias );
			if ( '' === $alias ) {
				continue;
			}
			$hash = self::name_hash( $alias );

			if ( isset( $seen[ $hash ] ) ) {
				continue;
			}

			$seen[ $hash ] = true;
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (place_id,unit_id,alias,alias_hash,created_at_utc,updated_at_utc)
						VALUES (%d,%d,%s,UNHEX(%s),%s,%s){$row_alias}
						ON DUPLICATE KEY UPDATE
							alias = {$alias_value},
							updated_at_utc = {$updated_at_value}",
					$place_id,
					$unit_id,
					$alias,
					$hash,
					$now,
					$now
				)
			);

			if ( false === $inserted ) {
				throw new RuntimeException( 'place_alias_insert_failed' );
			}
		}

		$placeholders = implode(
			',',
			array_fill( 0, count( $seen ), 'UNHEX(%s)' )
		);
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE place_id = %d
					AND alias_hash NOT IN ({$placeholders})",
				array_merge(
					array( $place_id ),
					array_keys( $seen )
				)
			)
		);

		if ( false === $deleted ) {
			throw new RuntimeException( 'place_alias_delete_failed' );
		}

		self::$active_place_names_cache = array();
	}

	/**
	 * Ensure aliases do not belong to another place in the same unit.
	 *
	 * @param int               $place_id Current place, or zero for a new one.
	 * @param int               $unit_id Unit row ID.
	 * @param array<int,mixed>  $names Candidate names.
	 * @return void
	 */
	private static function assert_aliases_available(
		int $place_id,
		int $unit_id,
		array $names
	): void {
		global $wpdb;

		$hashes = array();

		foreach ( $names as $name ) {
			$name = sanitize_text_field( (string) $name );

			if ( '' !== $name ) {
				$hashes[ self::name_hash( $name ) ] = true;
			}
		}

		if ( array() === $hashes ) {
			return;
		}

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACE_ALIASES
		);
		$placeholders = implode(
			',',
			array_fill( 0, count( $hashes ), 'UNHEX(%s)' )
		);
		$arguments = array_merge(
			array( $unit_id ),
			array_keys( $hashes ),
			array( $place_id )
		);
		$conflict = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT place_id
				FROM {$table}
				WHERE unit_id = %d
					AND alias_hash IN ({$placeholders})
					AND place_id <> %d
				LIMIT 1",
				$arguments
			)
		);

		if ( $conflict > 0 ) {
			throw new InvalidArgumentException(
				'duplicate_place_alias'
			);
		}
	}

	/**
	 * Return aliases for one place.
	 *
	 * @param int $place_id Place ID.
	 * @return array<int,string>
	 */
	private static function aliases( int $place_id ): array {
		global $wpdb;
		$table = Power_Schedule_Manager_Database::table( Power_Schedule_Manager_Database::PLACE_ALIASES );
		$rows = $wpdb->get_col(
			$wpdb->prepare( "SELECT alias FROM {$table} WHERE place_id = %d ORDER BY id ASC", $place_id )
		);
		return is_array( $rows ) ? array_map( 'strval', $rows ) : array();
	}

	/**
	 * Return active place names grouped by place for one electricity unit.
	 *
	 * Matching stays in PHP so MySQL/MariaDB collations cannot produce a
	 * different result from the maintenance relink action.
	 *
	 * @param string $unit_code Stable electricity-unit code.
	 * @return array<int,array<int,string>>
	 */
	private static function active_place_names_for_unit_code(
		string $unit_code
	): array {
		global $wpdb;

		$unit_code = strtoupper(
			sanitize_key( $unit_code )
		);

		if ( '' === $unit_code ) {
			return array();
		}

		if ( isset( self::$active_place_names_cache[ $unit_code ] ) ) {
			return self::$active_place_names_cache[ $unit_code ];
		}

		$places = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACES
		);
		$aliases = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::PLACE_ALIASES
		);
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT places.id AS place_id,
					places.canonical_name,
					aliases.alias
				FROM {$places} AS places
				LEFT JOIN {$aliases} AS aliases
					ON aliases.place_id = places.id
				WHERE places.unit_code = %s
					AND places.status = 'active'
				ORDER BY places.id ASC, aliases.id ASC",
				$unit_code
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			throw new RuntimeException(
				'active_place_names_query_failed'
			);
		}

		$grouped = array();

		foreach ( $rows as $row ) {
			$place_id = absint( $row['place_id'] ?? 0 );

			if ( $place_id < 1 ) {
				continue;
			}

			$grouped[ $place_id ] ??= array();

			foreach (
				array(
					$row['canonical_name'] ?? '',
					$row['alias'] ?? '',
				)
				as $name
			) {
				$name = trim(
					sanitize_text_field( (string) $name )
				);

				if ( '' !== $name ) {
					$grouped[ $place_id ][] = $name;
				}
			}
		}

		foreach ( $grouped as $place_id => $names ) {
			$grouped[ $place_id ] = array_values(
				array_unique( $names )
			);
		}

		self::$active_place_names_cache[ $unit_code ] = $grouped;

		return self::$active_place_names_cache[ $unit_code ];
	}

	/**
	 * Check whether an event-area description contains one saved place name.
	 *
	 * Matching is accent-insensitive and token-boundary aware. This permits a
	 * road name inside a longer EVN description while avoiding partial-word
	 * matches such as "An" inside "An Bình".
	 *
	 * @param string            $area Area description.
	 * @param array<int,string> $names Canonical name and aliases.
	 * @return bool
	 */
	private static function area_contains_place_name(
		string $area,
		array $names
	): bool {
		$haystack = self::normalize_match_text( $area );

		if ( '' === $haystack ) {
			return false;
		}

		foreach (
			array_slice( self::place_name_variants( $names ), 0, 50 )
			as $name
		) {
			$needle = self::normalize_match_text( (string) $name );

			if ( strlen( $needle ) < 3 ) {
				continue;
			}

			if (
				1 === preg_match(
					'/(?:^|\s)' . preg_quote( $needle, '/' ) . '(?:$|\s)/u',
					$haystack
				)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build safe source-name variants used during automatic event linking.
	 *
	 * EVN descriptions frequently omit generic prefixes such as "Đường" or
	 * "Phường". The canonical name remains unchanged in storage; variants are
	 * used only for matching within the same electricity unit.
	 *
	 * @param array<int,string> $names Canonical name and aliases.
	 * @return array<int,string>
	 */
	private static function place_name_variants( array $names ): array {
		$variants = array();

		foreach ( $names as $name ) {
			$name = trim( sanitize_text_field( (string) $name ) );

			if ( '' === $name ) {
				continue;
			}

			$variants[] = $name;
			$without_prefix = preg_replace(
				'/^(?:đường|phường|xã|thị\s+trấn)\s+/iu',
				'',
				$name
			);
			$without_prefix = is_string( $without_prefix )
				? trim( $without_prefix )
				: '';

			if (
				strlen( $without_prefix ) >= 3
				&& $without_prefix !== $name
			) {
				$variants[] = $without_prefix;
			}
		}

		return array_values( array_unique( $variants ) );
	}

	/**
	 * Normalize free text for safe place-name containment checks.
	 *
	 * @param string $value Source text.
	 * @return string
	 */
	private static function normalize_match_text( string $value ): string {
		$value = strtolower(
			remove_accents(
				sanitize_text_field( $value )
			)
		);
		$value = preg_replace( '/[^a-z0-9]+/u', ' ', $value ) ?? $value;

		return trim(
			preg_replace( '/\s+/u', ' ', $value ) ?? $value
		);
	}

	/**
	 * Hash a normalized Vietnamese place name.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function name_hash( string $name ): string {
		$name = strtolower(
			remove_accents(
				sanitize_text_field( $name )
			)
		);
		$name = preg_replace( '/\b(?:duong|phuong|xa|thi tran)\b/u', '', $name ) ?? $name;
		$name = preg_replace( '/[^a-z0-9]+/u', '', $name ) ?? $name;
		return hash( 'sha256', $name );
	}

	/**
	 * Read one scalar administration query value.
	 *
	 * @param string $key Query parameter.
	 * @return string
	 */
	private static function request_text( string $key ): string {
		if (
			! isset( $_GET[ $key ] )
			|| ! is_scalar( $_GET[ $key ] )
		) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash( (string) $_GET[ $key ] )
		);
	}

	/**
	 * Read one scalar POST value and remove WordPress magic slashes.
	 *
	 * @param string $key Request key.
	 * @param string $default Default value.
	 * @return string
	 */
	private static function post_scalar(
		string $key,
		string $default = ''
	): string {
		if (
			! isset( $_POST[ $key ] )
			|| ! is_scalar( $_POST[ $key ] )
		) {
			return $default;
		}

		return wp_unslash( (string) $_POST[ $key ] );
	}

	/**
	 * Convert a protected save error code into an actionable admin message.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	private static function save_error_message( string $code ): string {
		return match ( sanitize_key( $code ) ) {
			'invalid_place_unit' => __( 'Hãy chọn đúng đơn vị điện lực trước khi lưu địa điểm.', 'power-schedule-manager' ),
			'invalid_place_name' => __( 'Tên chuẩn của địa điểm không được để trống.', 'power-schedule-manager' ),
			'place_not_found' => __( 'Địa điểm không còn tồn tại. Hãy quay lại thư viện và tải lại danh sách.', 'power-schedule-manager' ),
			'duplicate_place_name' => __( 'Tên địa điểm này đã tồn tại trong đơn vị được chọn. Hãy sửa địa điểm hiện có hoặc thêm tên này làm bí danh.', 'power-schedule-manager' ),
			'duplicate_place_alias' => __( 'Một tên hoặc bí danh đã thuộc về địa điểm khác trong cùng đơn vị. Hãy kiểm tra thư viện trước khi lưu.', 'power-schedule-manager' ),
			'place_unit_has_links' => __( 'Không thể đổi đơn vị vì địa điểm đã liên kết với lịch điện. Hãy tạo địa điểm mới trong đơn vị đích để giữ dữ liệu lịch sử chính xác.', 'power-schedule-manager' ),
			'place_alias_delete_failed',
			'place_alias_insert_failed' => __( 'Không thể cập nhật bí danh địa điểm trong database. Dữ liệu chính chưa thể được xác nhận hoàn chỉnh.', 'power-schedule-manager' ),
			'place_update_failed' => __( 'Không thể cập nhật địa điểm trong database. Hãy kiểm tra migration bảng địa điểm.', 'power-schedule-manager' ),
			'place_insert_failed' => __( 'Không thể thêm địa điểm vào database. Có thể tên này đã tồn tại trong cùng đơn vị.', 'power-schedule-manager' ),
			'place_relink_failed' => __( 'Không thể khớp lại thư viện bản đồ. Hãy kiểm tra bảng liên kết địa điểm và lịch điện.', 'power-schedule-manager' ),
			'place_delete_failed',
			'place_delete_transaction_failed',
			'place_delete_commit_failed' => __( 'Không thể xóa địa điểm. Hãy kiểm tra kết nối database rồi thử lại.', 'power-schedule-manager' ),
			default => __( 'Dữ liệu GeoJSON hoặc tọa độ không hợp lệ. Hãy tìm và xem trước toàn tuyến lại trước khi lưu.', 'power-schedule-manager' ),
		};
	}
}
