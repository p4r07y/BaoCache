<?php
/**
 * Import and synchronization history view.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$history = isset( $psm_admin_args['history'] )
	&& is_array( $psm_admin_args['history'] )
	? $psm_admin_args['history']
	: array();

$units = isset( $psm_admin_args['units'] )
	&& is_array( $psm_admin_args['units'] )
	? $psm_admin_args['units']
	: array();

$items = isset( $history['items'] )
	&& is_array( $history['items'] )
	? $history['items']
	: array();

$total = isset( $history['total'] )
	? absint( $history['total'] )
	: 0;

$current_page = isset( $history['page'] )
	? max( 1, absint( $history['page'] ) )
	: 1;

$total_pages = isset( $history['total_pages'] )
	? absint( $history['total_pages'] )
	: 0;

$selected_unit = isset( $_GET['unit_code'] ) && is_scalar( $_GET['unit_code'] )
	? Power_Schedule_Manager_Units::sanitize_code(
		wp_unslash( (string) $_GET['unit_code'] )
	)
	: '';

$selected_status = isset( $_GET['status'] ) && is_scalar( $_GET['status'] )
	? sanitize_key(
		wp_unslash(
			(string) $_GET['status']
		)
	)
	: '';

$date_from = isset( $_GET['date_from'] ) && is_scalar( $_GET['date_from'] )
	? sanitize_text_field(
		wp_unslash(
			(string) $_GET['date_from']
		)
	)
	: '';

$date_to = isset( $_GET['date_to'] ) && is_scalar( $_GET['date_to'] )
	? sanitize_text_field(
		wp_unslash(
			(string) $_GET['date_to']
		)
	)
	: '';

$history_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::HISTORY_SLUG,
	),
	admin_url( 'admin.php' )
);
$history_pagination_base = str_replace(
	'999999999',
	'%#%',
	add_query_arg( 'paged', '999999999', $history_url )
);

/**
 * Convert UTC database time to the configured local timezone.
 *
 * @param mixed $value UTC datetime.
 * @return string
 */
$format_datetime = static function ( mixed $value ): string {
	if ( ! is_string( $value ) || '' === $value ) {
		return '—';
	}

	try {
		$date = new DateTimeImmutable(
			$value,
			new DateTimeZone( 'UTC' )
		);

		return $date
			->setTimezone(
				new DateTimeZone(
					POWER_SCHEDULE_MANAGER_TIMEZONE
				)
			)
			->format( 'H:i:s d/m/Y' );
	} catch ( Exception ) {
		return '—';
	}
};

/**
 * Return a translated status label.
 *
 * @param string $status Import status.
 * @return string
 */
$status_label = static function ( string $status ): string {
	$labels = array(
		'completed' => __(
			'Hoàn thành',
			'power-schedule-manager'
		),
		'running' => __(
			'Đang xử lý',
			'power-schedule-manager'
		),
		'failed' => __(
			'Thất bại',
			'power-schedule-manager'
		),
	);

	return $labels[ $status ]
		?? __(
			'Không xác định',
			'power-schedule-manager'
		);
};

/**
 * Return the CSS status class.
 *
 * @param string $status Import status.
 * @return string
 */
$status_class = static function ( string $status ): string {
	$classes = array(
		'completed' => 'success',
		'running'   => 'warning',
		'failed'    => 'error',
	);

	return $classes[ $status ] ?? 'neutral';
};

/*
 * Prime the WordPress user cache once to avoid one query for every row.
 */
$user_ids = array();

foreach ( $items as $item ) {
	if ( is_array( $item ) ) {
		$user_id = absint( $item['user_id'] ?? 0 );

		if ( $user_id > 0 ) {
			$user_ids[ $user_id ] = $user_id;
		}
	}
}

if ( array() !== $user_ids && function_exists( 'cache_users' ) ) {
	cache_users( array_values( $user_ids ) );
}

/*
 * Summarize only records visible with the current filters. These figures stay
 * meaningful when an administrator is investigating one period or unit.
 */
$page_summary = array(
	'runs'      => 0,
	'completed' => 0,
	'failed'    => 0,
	'inserted'  => 0,
	'updated'   => 0,
	'errors'    => 0,
);

foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	++$page_summary['runs'];
	$page_summary['inserted'] += absint( $item['inserted_count'] ?? 0 );
	$page_summary['updated'] += absint( $item['updated_count'] ?? 0 );
	$page_summary['errors'] += absint( $item['error_count'] ?? 0 );

	if ( 'completed' === ( $item['status'] ?? '' ) ) {
		++$page_summary['completed'];
	}

	if ( 'failed' === ( $item['status'] ?? '' ) ) {
		++$page_summary['failed'];
	}
}
?>

<div class="wrap psm-admin-wrap psm-history-admin">
	<header class="psm-editorial-hero">
		<div>
			<span class="psm-editorial-hero__eyebrow"><?php esc_html_e( 'Nhật ký vận hành', 'power-schedule-manager' ); ?></span>
			<h1><?php esc_html_e( 'Lịch sử đồng bộ dữ liệu', 'power-schedule-manager' ); ?></h1>
			<p><?php esc_html_e( 'Theo dõi kết quả nhập, nguồn dữ liệu và các lần xử lý cần kiểm tra.', 'power-schedule-manager' ); ?></p>
		</div>
		<div class="psm-editorial-hero__metric"><span><?php esc_html_e( 'Tổng số lần xử lý', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong></div>
	</header>

	<section class="psm-history-kpis" aria-label="<?php esc_attr_e( 'Thống kê cập nhật trong kết quả đang xem', 'power-schedule-manager' ); ?>">
		<article class="psm-history-kpi">
			<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html( number_format_i18n( $page_summary['runs'] ) ); ?></strong>
				<span><?php esc_html_e( 'Lần xử lý đang xem', 'power-schedule-manager' ); ?></span>
			</div>
		</article>

		<article class="psm-history-kpi psm-history-kpi--new">
			<span class="dashicons dashicons-insert" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html( number_format_i18n( $page_summary['inserted'] ) ); ?></strong>
				<span><?php esc_html_e( 'Lịch mới được thêm', 'power-schedule-manager' ); ?></span>
			</div>
		</article>

		<article class="psm-history-kpi psm-history-kpi--updated">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html( number_format_i18n( $page_summary['updated'] ) ); ?></strong>
				<span><?php esc_html_e( 'Lịch có thay đổi', 'power-schedule-manager' ); ?></span>
			</div>
		</article>

		<article class="psm-history-kpi psm-history-kpi--attention">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html( number_format_i18n( $page_summary['failed'] + $page_summary['errors'] ) ); ?></strong>
				<span><?php esc_html_e( 'Mục cần kiểm tra', 'power-schedule-manager' ); ?></span>
			</div>
		</article>
	</section>

	<div class="notice notice-info inline">
		<p>
			<strong>
				<?php
				esc_html_e(
					'Chính sách lưu lịch sử:',
					'power-schedule-manager'
				);
				?>
			</strong>

			<?php
			esc_html_e(
				'Nội dung nguồn thô được xóa sau 30 ngày; thống kê lần nhập thành công hoặc thất bại được giữ 12 tháng rồi cron xóa theo từng đợt. Plugin hiện không cho xóa thủ công từng bản ghi để bảo toàn dấu vết kiểm tra.',
				'power-schedule-manager'
			);
			?>
		</p>
	</div>

	<form
		method="get"
		action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
		class="psm-history-filters"
	>
		<input
			type="hidden"
			name="page"
			value="<?php
			echo esc_attr(
				Power_Schedule_Manager_Admin::HISTORY_SLUG
			);
			?>"
		>

		<label for="psm-history-unit">
			<span>
				<?php
				esc_html_e(
					'Đơn vị điện lực',
					'power-schedule-manager'
				);
				?>
			</span>

			<select
				id="psm-history-unit"
				name="unit_code"
			>
				<option value="">
					<?php
					esc_html_e(
						'Tất cả đơn vị',
						'power-schedule-manager'
					);
					?>
				</option>

				<?php foreach ( $units as $unit ) : ?>
					<?php
					if ( ! is_array( $unit ) ) {
						continue;
					}

					$code = isset( $unit['code'] )
						? sanitize_text_field(
							(string) $unit['code']
						)
						: '';

					$name = isset( $unit['name'] )
						? sanitize_text_field(
							(string) $unit['name']
						)
						: '';

					if ( '' === $code || '' === $name ) {
						continue;
					}
					?>

					<option
						value="<?php echo esc_attr( $code ); ?>"
						<?php
						selected(
							$selected_unit,
							$code
						);
						?>
					>
						<?php
						echo esc_html(
							sprintf(
								'%1$s — %2$s',
								$code,
								$name
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<label for="psm-history-status">
			<span>
				<?php
				esc_html_e(
					'Trạng thái',
					'power-schedule-manager'
				);
				?>
			</span>

			<select
				id="psm-history-status"
				name="status"
			>
				<option value="">
					<?php
					esc_html_e(
						'Tất cả trạng thái',
						'power-schedule-manager'
					);
					?>
				</option>

				<option
					value="completed"
					<?php selected( $selected_status, 'completed' ); ?>
				>
					<?php
					esc_html_e(
						'Hoàn thành',
						'power-schedule-manager'
					);
					?>
				</option>

				<option
					value="running"
					<?php selected( $selected_status, 'running' ); ?>
				>
					<?php
					esc_html_e(
						'Đang xử lý',
						'power-schedule-manager'
					);
					?>
				</option>

				<option
					value="failed"
					<?php selected( $selected_status, 'failed' ); ?>
				>
					<?php
					esc_html_e(
						'Thất bại',
						'power-schedule-manager'
					);
					?>
				</option>
			</select>
		</label>

		<label for="psm-history-from">
			<span>
				<?php
				esc_html_e(
					'Từ ngày',
					'power-schedule-manager'
				);
				?>
			</span>

			<input
				type="date"
				id="psm-history-from"
				name="date_from"
				value="<?php echo esc_attr( $date_from ); ?>"
			>
		</label>

		<label for="psm-history-to">
			<span>
				<?php
				esc_html_e(
					'Đến ngày',
					'power-schedule-manager'
				);
				?>
			</span>

			<input
				type="date"
				id="psm-history-to"
				name="date_to"
				value="<?php echo esc_attr( $date_to ); ?>"
			>
		</label>

		<div class="psm-history-filter-actions">
			<button type="submit" class="button button-primary">
				<?php
				esc_html_e(
					'Lọc lịch sử',
					'power-schedule-manager'
				);
				?>
			</button>

			<a
				class="button"
				href="<?php echo esc_url( $history_url ); ?>"
			>
				<?php
				esc_html_e(
					'Đặt lại',
					'power-schedule-manager'
				);
				?>
			</a>
		</div>
	</form>

	<div class="psm-history-table-wrap">
		<table class="widefat striped psm-history-table">
			<thead>
				<tr>
					<th scope="col">
						<?php esc_html_e( 'Lần nhập và nguồn', 'power-schedule-manager' ); ?>
					</th>

					<th scope="col">
						<?php
						esc_html_e(
							'Kết quả',
							'power-schedule-manager'
						);
						?>
					</th>

					<th scope="col">
						<?php esc_html_e( 'Vận hành', 'power-schedule-manager' ); ?>
					</th>

					<th scope="col">
						<?php
						esc_html_e(
							'Thời gian',
							'power-schedule-manager'
						);
						?>
					</th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<?php
					if ( ! is_array( $item ) ) {
						continue;
					}

					$run_id = absint( $item['id'] ?? 0 );

					$unit_code = sanitize_text_field(
						(string) ( $item['unit_code'] ?? '' )
					);

					$source = sanitize_key(
						(string) ( $item['source'] ?? '' )
					);

					$source_url = esc_url(
						(string) ( $item['source_url'] ?? '' ),
						array( 'http', 'https' )
					);

					$status = sanitize_key(
						(string) ( $item['status'] ?? '' )
					);

					$user_id = absint(
						$item['user_id'] ?? 0
					);

					$user = $user_id > 0
						? get_userdata( $user_id )
						: false;

					$user_name = $user instanceof WP_User
						? $user->display_name
						: __(
							'Hệ thống',
							'power-schedule-manager'
						);

					$error_message = isset( $item['error_message'] )
						&& is_string( $item['error_message'] )
						? $item['error_message']
						: '';

					$started_at_utc = (string) (
						$item['started_at_utc'] ?? ''
					);

					$finished_at_utc = (string) (
						$item['finished_at_utc'] ?? ''
					);
					?>

					<tr>
						<td data-label="<?php esc_attr_e( 'Lần nhập và nguồn', 'power-schedule-manager' ); ?>">
							<strong>
								<a
									href="<?php
									echo esc_url(
										add_query_arg(
											array(
												'page'   => Power_Schedule_Manager_Admin::HISTORY_SLUG,
												'run_id' => $run_id,
											),
											admin_url( 'admin.php' )
										)
									);
									?>"
								>
									<?php
									echo esc_html(
										sprintf(
											'#%d',
											$run_id
										)
									);
									?>
								</a>
							</strong>

							<?php if ( ! empty( $item['run_uuid'] ) ) : ?>
								<br>

								<code>
									<?php
									echo esc_html(
										substr(
											(string) $item['run_uuid'],
											0,
											8
										)
									);
									?>
								</code>
								<?php endif; ?>

							<div class="psm-history-run-source">
								<strong><?php echo esc_html( $unit_code ); ?></strong>
								<span><?php echo esc_html( $source ); ?></span>
								<?php if ( '' !== $source_url ) : ?>
									<a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer nofollow">
										<?php esc_html_e( 'Xem nguồn', 'power-schedule-manager' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</td>

						<td data-label="<?php esc_attr_e( 'Kết quả', 'power-schedule-manager' ); ?>">
							<ul class="psm-history-counts">
								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: Found count. */
											__(
												'Tìm thấy: %d',
												'power-schedule-manager'
											),
											absint(
												$item['found_count'] ?? 0
											)
										)
									);
									?>
								</li>

								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: Inserted count. */
											__(
												'Thêm: %d',
												'power-schedule-manager'
											),
											absint(
												$item['inserted_count'] ?? 0
											)
										)
									);
									?>
								</li>

								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: Updated count. */
											__(
												'Cập nhật: %d',
												'power-schedule-manager'
											),
											absint(
												$item['updated_count'] ?? 0
											)
										)
									);
									?>
								</li>

								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: Unchanged count. */
											__(
												'Không đổi: %d',
												'power-schedule-manager'
											),
											absint(
												$item['unchanged_count'] ?? 0
											)
										)
									);
									?>
								</li>

								<?php if (
									absint(
										$item['error_count'] ?? 0
									) > 0
								) : ?>
									<li class="psm-text-error">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: Error count. */
												__(
													'Lỗi: %d',
													'power-schedule-manager'
												),
												absint(
													$item['error_count']
												)
											)
										);
										?>
									</li>
								<?php endif; ?>
							</ul>
						</td>

						<td data-label="<?php esc_attr_e( 'Vận hành', 'power-schedule-manager' ); ?>">
							<span
								class="psm-status psm-status--<?php
								echo esc_attr(
									$status_class( $status )
								);
								?>"
							>
								<?php
								echo esc_html(
									$status_label( $status )
								);
								?>
							</span>

							<span class="psm-history-operator">
								<?php echo esc_html( $user_name ); ?>
							</span>

							<?php if ( '' !== $error_message ) : ?>
								<p class="psm-history-error">
									<?php
									echo esc_html(
										$error_message
									);
									?>
								</p>
							<?php endif; ?>
						</td>

						<td data-label="<?php esc_attr_e( 'Thời gian', 'power-schedule-manager' ); ?>">
							<div class="psm-history-time">
								<span>
									<small><?php esc_html_e( 'Bắt đầu', 'power-schedule-manager' ); ?></small>
									<strong><?php echo esc_html( $format_datetime( $started_at_utc ) ); ?></strong>
								</span>

								<?php if ( '' !== $finished_at_utc ) : ?>
									<span>
										<small><?php esc_html_e( 'Hoàn tất', 'power-schedule-manager' ); ?></small>
										<strong>
											<?php
											echo esc_html(
												$finished_at_utc === $started_at_utc
													? __( 'Ngay sau khi nhập', 'power-schedule-manager' )
													: $format_datetime( $finished_at_utc )
											);
											?>
										</strong>
									</span>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>

				<?php if ( array() === $items ) : ?>
					<tr>
						<td colspan="4">
							<?php
							esc_html_e(
								'Không tìm thấy lịch sử đồng bộ phù hợp.',
								'power-schedule-manager'
							);
							?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $total_pages > 1 ) : ?>
		<nav class="psm-admin-pagination" aria-label="<?php esc_attr_e( 'Phân trang lịch sử đồng bộ', 'power-schedule-manager' ); ?>">
			<span class="psm-admin-pagination__summary">
				<?php
				printf(
					/* translators: 1: Current page, 2: Total pages, 3: Total records. */
					esc_html__( 'Trang %1$s/%2$s · %3$s lần xử lý', 'power-schedule-manager' ),
					esc_html( number_format_i18n( $current_page ) ),
					esc_html( number_format_i18n( $total_pages ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</span>
			<div class="psm-admin-pagination__links">
				<?php
				$pagination_args = array(
					'base'      => $history_pagination_base,
					'format'    => '',
					'current'   => $current_page,
					'total'     => $total_pages,
					'prev_text' => __(
						'‹ Trước',
						'power-schedule-manager'
					),
					'next_text' => __(
						'Sau ›',
						'power-schedule-manager'
					),
				);

				if ( '' !== $selected_unit ) {
					$pagination_args['add_args']['unit_code'] =
						$selected_unit;
				}

				if ( '' !== $selected_status ) {
					$pagination_args['add_args']['status'] =
						$selected_status;
				}

				if ( '' !== $date_from ) {
					$pagination_args['add_args']['date_from'] =
						$date_from;
				}

				if ( '' !== $date_to ) {
					$pagination_args['add_args']['date_to'] =
						$date_to;
				}

				echo wp_kses_post(
					paginate_links( $pagination_args )
				);
				?>
			</div>
		</nav>
	<?php endif; ?>
</div>
