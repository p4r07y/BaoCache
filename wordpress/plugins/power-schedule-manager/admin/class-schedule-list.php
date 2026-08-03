<?php
/**
 * Configure the Power Schedule administration list.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage the schedule list screen in WordPress administration.
 */
final class Power_Schedule_Manager_Schedule_List {

	/**
	 * Unit filter query parameter.
	 */
	private const UNIT_FILTER = 'psm_admin_unit';

	/**
	 * Local date filter query parameter.
	 */
	private const DATE_FILTER = 'psm_admin_date';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );

		add_filter(
			'manage_psm_schedule_posts_columns',
			array( $this, 'register_columns' )
		);

		add_action(
			'manage_psm_schedule_posts_custom_column',
			array( $this, 'render_column' ),
			10,
			2
		);

		add_filter(
			'manage_edit-psm_schedule_sortable_columns',
			array( $this, 'register_sortable_columns' )
		);

		add_action(
			'restrict_manage_posts',
			array( $this, 'render_filters' ),
			10,
			2
		);

		add_action(
			'pre_get_posts',
			array( $this, 'apply_filters_and_sorting' )
		);

		add_filter(
			'post_row_actions',
			array( $this, 'filter_row_actions' ),
			10,
			2
		);
	}

	/**
	 * Register the standard schedule list as a plugin submenu.
	 *
	 * WordPress treats a menu slug beginning with edit.php as a link to
	 * the native post list screen, so no custom callback is required.
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			Power_Schedule_Manager_Admin::MENU_SLUG,
			__( 'Danh sách lịch điện', 'power-schedule-manager' ),
			__( 'Danh sách lịch điện', 'power-schedule-manager' ),
			Power_Schedule_Manager_Capabilities::EDIT_POSTS,
			'edit.php?post_type=' . Power_Schedule_Manager_Post_Type::POST_TYPE
		);
	}

	/**
	 * Register custom administration columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function register_columns( array $columns ): array {
		$prepared = array();

		foreach ( $columns as $key => $label ) {
			$prepared[ $key ] = $label;

			if ( 'title' !== $key ) {
				continue;
			}

			$prepared['psm_unit'] = __(
				'Đơn vị điện lực',
				'power-schedule-manager'
			);

			$prepared['psm_local_date'] = __(
				'Ngày áp dụng',
				'power-schedule-manager'
			);

			$prepared['psm_event_count'] = __(
				'Số lịch',
				'power-schedule-manager'
			);

			$prepared['psm_last_updated'] = __(
				'Cập nhật dữ liệu',
				'power-schedule-manager'
			);
		}

		return $prepared;
	}

	/**
	 * Render a custom administration column.
	 *
	 * Post metadata is already primed by WP_Query for the list screen.
	 * Calling get_post_meta() here therefore does not create one database
	 * query for every displayed row.
	 *
	 * @param string $column_name Column identifier.
	 * @param int    $post_id     Schedule post ID.
	 * @return void
	 */
	public function render_column( string $column_name, int $post_id ): void {
		switch ( $column_name ) {
			case 'psm_unit':
				$this->render_unit_column( $post_id );
				break;

			case 'psm_local_date':
				$this->render_local_date_column( $post_id );
				break;

			case 'psm_event_count':
				$this->render_event_count_column( $post_id );
				break;

			case 'psm_last_updated':
				$this->render_last_updated_column( $post_id );
				break;
		}
	}

	/**
	 * Register sortable custom columns.
	 *
	 * @param array<string,string> $columns Existing sortable columns.
	 * @return array<string,string>
	 */
	public function register_sortable_columns( array $columns ): array {
		$columns['psm_local_date']  = 'psm_local_date';
		$columns['psm_event_count'] = 'psm_event_count';
		$columns['psm_last_updated'] = 'psm_last_updated';

		return $columns;
	}

	/**
	 * Render unit and date filters above the post table.
	 *
	 * @param string $post_type Current post type.
	 * @param string $position  Filter position.
	 * @return void
	 */
	public function render_filters(
		string $post_type,
		string $position = 'top'
	): void {
		if (
			Power_Schedule_Manager_Post_Type::POST_TYPE !== $post_type
			|| 'top' !== $position
			|| ! current_user_can(
				Power_Schedule_Manager_Capabilities::EDIT_POSTS
			)
		) {
			return;
		}

		$selected_unit = $this->get_requested_unit_code();
		$selected_date = $this->get_requested_local_date();
		$units         = $this->get_filter_units();

		?>
		<label class="screen-reader-text" for="psm-admin-unit">
			<?php
			esc_html_e(
				'Lọc theo đơn vị điện lực',
				'power-schedule-manager'
			);
			?>
		</label>

		<select
			id="psm-admin-unit"
			name="<?php echo esc_attr( self::UNIT_FILTER ); ?>"
		>
			<option value="">
				<?php
				esc_html_e(
					'Tất cả đơn vị điện lực',
					'power-schedule-manager'
				);
				?>
			</option>

			<?php foreach ( $units as $unit ) : ?>
				<option
					value="<?php echo esc_attr( $unit['code'] ); ?>"
					<?php selected( $selected_unit, $unit['code'] ); ?>
				>
					<?php
					echo esc_html(
						sprintf(
							'%1$s — %2$s',
							$unit['code'],
							$unit['name']
						)
					);
					?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="psm-admin-date">
			<?php
			esc_html_e(
				'Lọc theo ngày áp dụng',
				'power-schedule-manager'
			);
			?>
		</label>

		<input
			type="date"
			id="psm-admin-date"
			name="<?php echo esc_attr( self::DATE_FILTER ); ?>"
			value="<?php echo esc_attr( $selected_date ); ?>"
			placeholder="YYYY-MM-DD"
		/>
		<?php
	}

	/**
	 * Apply validated filters and sorting to the native post list query.
	 *
	 * @param WP_Query $query Current WordPress query.
	 * @return void
	 */
	public function apply_filters_and_sorting( WP_Query $query ): void {
		if (
			! is_admin()
			|| ! $query->is_main_query()
			|| ! current_user_can(
				Power_Schedule_Manager_Capabilities::EDIT_POSTS
			)
		) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		if (
			Power_Schedule_Manager_Post_Type::POST_TYPE !== $post_type
		) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );

		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		$unit_code = $this->get_requested_unit_code();

		if ( '' !== $unit_code && $this->unit_exists( $unit_code ) ) {
			$meta_query[] = array(
				'key'     => Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
				'value'   => $unit_code,
				'compare' => '=',
			);
		}

		$local_date = $this->get_requested_local_date();

		if ( '' !== $local_date ) {
			$meta_query[] = array(
				'key'     => Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
				'value'   => $local_date,
				'compare' => '=',
				'type'    => 'DATE',
			);
		}

		if ( array() !== $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}

		$this->apply_sorting( $query );
	}

	/**
	 * Remove row actions that are not appropriate for generated schedules.
	 *
	 * @param array<string,string> $actions Existing row actions.
	 * @param WP_Post              $post    Current post.
	 * @return array<string,string>
	 */
	public function filter_row_actions(
		array $actions,
		WP_Post $post
	): array {
		if (
			Power_Schedule_Manager_Post_Type::POST_TYPE
			!== $post->post_type
		) {
			return $actions;
		}

		/*
		 * Quick Edit cannot safely maintain the custom schedule metadata,
		 * so schedule changes must use the complete editor.
		 */
		unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}

	/**
	 * Apply an allowlisted administration sort order.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	private function apply_sorting( WP_Query $query ): void {
		$orderby = sanitize_key(
			(string) $query->get( 'orderby' )
		);

		switch ( $orderby ) {
			case 'psm_local_date':
				$query->set(
					'meta_key',
					Power_Schedule_Manager_Post_Type::META_LOCAL_DATE
				);
				$query->set( 'orderby', 'meta_value' );
				$query->set( 'meta_type', 'DATE' );
				break;

			case 'psm_event_count':
				$query->set(
					'meta_key',
					Power_Schedule_Manager_Post_Type::META_EVENT_COUNT
				);
				$query->set( 'orderby', 'meta_value_num' );
				break;

			case 'psm_last_updated':
				$query->set(
					'meta_key',
					Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC
				);
				$query->set( 'orderby', 'meta_value' );
				$query->set( 'meta_type', 'DATETIME' );
				break;
		}
	}

	/**
	 * Render the schedule unit.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_unit_column( int $post_id ): void {
		$unit_code = sanitize_text_field(
			(string) get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_UNIT_CODE,
				true
			)
		);

		if ( '' === $unit_code ) {
			$this->render_missing_value();

			return;
		}

		$unit_name = $this->get_unit_name( $unit_code );

		echo '<strong>' . esc_html( $unit_name ) . '</strong>';
		echo '<br>';
		echo '<code>' . esc_html( $unit_code ) . '</code>';
	}

	/**
	 * Render the local schedule date.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_local_date_column( int $post_id ): void {
		$local_date = sanitize_text_field(
			(string) get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_LOCAL_DATE,
				true
			)
		);

		$formatted = $this->format_local_date( $local_date );

		if ( '' === $formatted ) {
			$this->render_missing_value();

			return;
		}

		echo '<time datetime="' . esc_attr( $local_date ) . '">';
		echo esc_html( $formatted );
		echo '</time>';
	}

	/**
	 * Render the event count.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_event_count_column( int $post_id ): void {
		$count = absint(
			get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_EVENT_COUNT,
				true
			)
		);

		echo esc_html(
			sprintf(
				/* translators: %d: Number of outage events. */
				_n(
					'%d lịch',
					'%d lịch',
					$count,
					'power-schedule-manager'
				),
				$count
			)
		);
	}

	/**
	 * Render the last data update time in the configured local timezone.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_last_updated_column( int $post_id ): void {
		$utc_value = sanitize_text_field(
			(string) get_post_meta(
				$post_id,
				Power_Schedule_Manager_Post_Type::META_LAST_UPDATED_UTC,
				true
			)
		);

		$formatted = $this->format_utc_datetime( $utc_value );

		if ( '' === $formatted ) {
			$this->render_missing_value();

			return;
		}

		echo '<time datetime="' . esc_attr( $utc_value ) . '">';
		echo esc_html( $formatted );
		echo '</time>';
	}

	/**
	 * Get the requested unit code.
	 *
	 * @return string
	 */
	private function get_requested_unit_code(): string {
		if ( ! isset( $_GET[ self::UNIT_FILTER ] ) ) {
			return '';
		}

		return strtoupper(
			sanitize_text_field(
				wp_unslash(
					(string) $_GET[ self::UNIT_FILTER ]
				)
			)
		);
	}

	/**
	 * Get and validate the requested local date.
	 *
	 * @return string
	 */
	private function get_requested_local_date(): string {
		if ( ! isset( $_GET[ self::DATE_FILTER ] ) ) {
			return '';
		}

		$value = sanitize_text_field(
			wp_unslash(
				(string) $_GET[ self::DATE_FILTER ]
			)
		);

		if (
			1 !== preg_match(
				'/\A(\d{4})-(\d{2})-(\d{2})\z/',
				$value,
				$matches
			)
		) {
			return '';
		}

		$year  = (int) $matches[1];
		$month = (int) $matches[2];
		$day   = (int) $matches[3];

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Determine whether a unit exists.
	 *
	 * @param string $unit_code Unit code.
	 * @return bool
	 */
	private function unit_exists( string $unit_code ): bool {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$unit_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$table}
				WHERE code = %s
				LIMIT 1",
				$unit_code
			)
		);

		return null !== $unit_id;
	}

	/**
	 * Get all units for the administration filter.
	 *
	 * Administrators may need to inspect non-public units, so this method
	 * intentionally returns both public and internal units.
	 *
	 * @return array<int,array{code:string,name:string}>
	 */
	private function get_filter_units(): array {
		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$rows = $wpdb->get_results(
			"SELECT code, name
			FROM {$table}
			ORDER BY sort_order ASC, name ASC",
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$units = array();

		foreach ( $rows as $row ) {
			$code = isset( $row['code'] )
				? sanitize_text_field( (string) $row['code'] )
				: '';

			$name = isset( $row['name'] )
				? sanitize_text_field( (string) $row['name'] )
				: '';

			if ( '' === $code || '' === $name ) {
				continue;
			}

			$units[] = array(
				'code' => $code,
				'name' => $name,
			);
		}

		return $units;
	}

	/**
	 * Get a display name for a unit code.
	 *
	 * WordPress object cache prevents repeated database reads for the same
	 * unit during one request and across requests when Redis is enabled.
	 *
	 * @param string $unit_code Unit code.
	 * @return string
	 */
	private function get_unit_name( string $unit_code ): string {
		$cache_key = 'admin_unit_name_' . md5( $unit_code );
		$cached    = wp_cache_get(
			$cache_key,
			'power_schedule_manager'
		);

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table = Power_Schedule_Manager_Database::table(
			Power_Schedule_Manager_Database::UNITS
		);

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name
				FROM {$table}
				WHERE code = %s
				LIMIT 1",
				$unit_code
			)
		);

		$name = is_string( $name ) && '' !== $name
			? sanitize_text_field( $name )
			: $unit_code;

		wp_cache_set(
			$cache_key,
			$name,
			'power_schedule_manager',
			300
		);

		return $name;
	}

	/**
	 * Format a YYYY-MM-DD date for Vietnamese readers.
	 *
	 * @param string $value Local date.
	 * @return string
	 */
	private function format_local_date( string $value ): string {
		if (
			1 !== preg_match(
				'/\A(\d{4})-(\d{2})-(\d{2})\z/',
				$value,
				$matches
			)
		) {
			return '';
		}

		if (
			! checkdate(
				(int) $matches[2],
				(int) $matches[3],
				(int) $matches[1]
			)
		) {
			return '';
		}

		return sprintf(
			'%1$02d/%2$02d/%3$04d',
			(int) $matches[3],
			(int) $matches[2],
			(int) $matches[1]
		);
	}

	/**
	 * Convert a stored UTC datetime to the plugin local timezone.
	 *
	 * @param string $utc_value UTC database datetime.
	 * @return string
	 */
	private function format_utc_datetime( string $utc_value ): string {
		if ( '' === $utc_value ) {
			return '';
		}

		try {
			$utc_timezone = new DateTimeZone( 'UTC' );
			$local_zone   = new DateTimeZone(
				POWER_SCHEDULE_MANAGER_TIMEZONE
			);

			$date = DateTimeImmutable::createFromFormat(
				'!Y-m-d H:i:s',
				$utc_value,
				$utc_timezone
			);

			$errors = DateTimeImmutable::getLastErrors();

			if (
				false === $date
				|| (
					is_array( $errors )
					&& (
						$errors['warning_count'] > 0
						|| $errors['error_count'] > 0
					)
				)
			) {
				return '';
			}

			return $date
				->setTimezone( $local_zone )
				->format( 'H:i d/m/Y' );
		} catch ( Exception ) {
			return '';
		}
	}

	/**
	 * Render a consistent missing-value marker.
	 *
	 * @return void
	 */
	private function render_missing_value(): void {
		echo '<span aria-hidden="true">—</span>';
		echo '<span class="screen-reader-text">';
		esc_html_e(
			'Chưa có dữ liệu',
			'power-schedule-manager'
		);
		echo '</span>';
	}
}
