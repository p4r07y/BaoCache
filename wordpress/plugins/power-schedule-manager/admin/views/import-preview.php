<?php
/**
 * Schedule import preview.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$preview = isset( $psm_admin_args['preview'] )
	&& is_array( $psm_admin_args['preview'] )
	? $psm_admin_args['preview']
	: array();

$form = isset( $psm_admin_args['form'] )
	&& is_array( $psm_admin_args['form'] )
	? $psm_admin_args['form']
	: array();

$error = isset( $psm_admin_args['error'] )
	&& is_string( $psm_admin_args['error'] )
	? $psm_admin_args['error']
	: '';

$items = isset( $preview['rows'] )
	&& is_array( $preview['rows'] )
	? $preview['rows']
	: array();

$token = isset( $preview['token'] )
	&& is_string( $preview['token'] )
	? $preview['token']
	: '';

$preview_warnings = isset( $preview['warnings'] )
	&& is_array( $preview['warnings'] )
	? $preview['warnings']
	: array();

$visible_warnings = array_slice( $preview_warnings, 0, 20 );

$unit_code = isset( $form['unit_code'] )
	? sanitize_text_field( (string) $form['unit_code'] )
	: '';

$payload = isset( $form['payload'] )
	? (string) $form['payload']
	: '';

$import_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::IMPORT_SLUG,
	),
	admin_url( 'admin.php' )
);

/**
 * Format a stored UTC datetime in Vietnam time.
 *
 * @param string $value UTC datetime.
 * @return string
 */
$format_datetime = static function ( string $value ): string {
	if ( '' === $value ) {
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
			->format( 'H:i d/m/Y' );
	} catch ( Exception ) {
		return '—';
	}
};

/**
 * Normalize one preview classification.
 *
 * @param array<string,mixed> $item Preview item.
 * @return string
 */
$get_classification = static function ( array $item ): string {
	$value = $item['classification']
		?? $item['action']
		?? $item['preview_status']
		?? '';

	$value = sanitize_key( (string) $value );

	$aliases = array(
		'insert'             => 'new',
		'create'             => 'new',
		'changed'            => 'update',
		'same'               => 'unchanged',
		'duplicate'          => 'possible_duplicate',
		'possible-duplicate' => 'possible_duplicate',
		'invalid'            => 'error',
	);

	return $aliases[ $value ] ?? $value;
};

/**
 * Return a Vietnamese preview status.
 *
 * @param string $classification Classification.
 * @return string
 */
$get_status_label = static function (
	string $classification
): string {
	$labels = array(
		'new' => __(
			'Thêm mới',
			'power-schedule-manager'
		),
		'update' => __(
			'Cập nhật',
			'power-schedule-manager'
		),
		'unchanged' => __(
			'Đã tồn tại — không ghi thêm',
			'power-schedule-manager'
		),
		'possible_duplicate' => __(
			'Có thể trùng',
			'power-schedule-manager'
		),
		'time_changed' => __(
			'Thay đổi thời gian',
			'power-schedule-manager'
		),
		'cancelled' => __(
			'Bị hủy',
			'power-schedule-manager'
		),
		'error' => __(
			'Lỗi',
			'power-schedule-manager'
		),
	);

	return $labels[ $classification ]
		?? __(
			'Cần kiểm tra',
			'power-schedule-manager'
		);
};

$summary = array(
	'new'                => 0,
	'update'             => 0,
	'unchanged'          => 0,
	'possible_duplicate' => 0,
	'error'              => 0,
);

$normalized_items = array();

foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$event = isset( $item['event'] )
		&& is_array( $item['event'] )
		? $item['event']
		: $item;

	$classification = $get_classification( $item );

	if ( '' === $classification ) {
		$classification = 'error';
	}

	if ( isset( $summary[ $classification ] ) ) {
		++$summary[ $classification ];
	}

	$normalized_items[] = array(
		'item'           => $item,
		'event'          => $event,
		'classification' => $classification,
	);
}

$has_blocking_error = '' === $token
	|| array() === $normalized_items
	|| $summary['error'] > 0;
?>

<div class="wrap psm-admin-wrap psm-import-preview">
	<header class="psm-editorial-hero">
		<div>
			<span class="psm-editorial-hero__eyebrow"><?php esc_html_e( 'Bước kiểm tra an toàn', 'power-schedule-manager' ); ?></span>
			<h1><?php esc_html_e( 'Kiểm tra dữ liệu trước khi nhập', 'power-schedule-manager' ); ?></h1>
			<p><?php esc_html_e( 'Đây mới là bản xem trước. Database chưa bị thay đổi.', 'power-schedule-manager' ); ?></p>
		</div>
		<div class="psm-editorial-hero__metric"><span><?php esc_html_e( 'Sự kiện đã đọc', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $normalized_items ) ) ); ?></strong></div>
	</header>

	<?php if ( '' !== $error ) : ?>
		<div class="notice notice-error" role="alert">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $has_blocking_error ) : ?>
		<div class="notice notice-error" role="alert">
			<p>
				<?php
				esc_html_e(
					'Dữ liệu xem trước chưa hợp lệ. Không thể xác nhận import cho đến khi lỗi được xử lý.',
					'power-schedule-manager'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( array() !== $preview_warnings ) : ?>
		<div class="notice notice-warning" role="status">
			<p>
				<strong>
					<?php
					esc_html_e(
						'Một số khối dữ liệu đã được bỏ qua hoặc cần lưu ý:',
						'power-schedule-manager'
					);
					?>
				</strong>
			</p>

			<ul class="ul-disc">
				<?php foreach ( $visible_warnings as $warning ) : ?>
					<?php
					$warning_message = is_array( $warning )
						? sanitize_text_field(
							(string) ( $warning['message'] ?? '' )
						)
						: '';

					if ( '' === $warning_message ) {
						continue;
					}
					?>

					<li><?php echo esc_html( $warning_message ); ?></li>
				<?php endforeach; ?>

				<?php if ( count( $preview_warnings ) > count( $visible_warnings ) ) : ?>
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: Remaining warning count. */
								__(
									'Và %d cảnh báo khác. Hãy kiểm tra lại dữ liệu nguồn nếu số lượng cảnh báo bất thường.',
									'power-schedule-manager'
								),
								count( $preview_warnings )
									- count( $visible_warnings )
							)
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="psm-preview-summary">
		<div class="psm-preview-summary__item">
			<span>
				<?php
				esc_html_e(
					'Tổng sự kiện',
					'power-schedule-manager'
				);
				?>
			</span>

			<strong>
				<?php
				echo esc_html(
					number_format_i18n(
						count( $normalized_items )
					)
				);
				?>
			</strong>
		</div>

		<div class="psm-preview-summary__item psm-preview-summary__item--new">
			<span>
				<?php
				esc_html_e(
					'Thêm mới',
					'power-schedule-manager'
				);
				?>
			</span>

			<strong>
				<?php
				echo esc_html(
					number_format_i18n( $summary['new'] )
				);
				?>
			</strong>
		</div>

		<div class="psm-preview-summary__item psm-preview-summary__item--update">
			<span>
				<?php
				esc_html_e(
					'Cập nhật',
					'power-schedule-manager'
				);
				?>
			</span>

			<strong>
				<?php
				echo esc_html(
					number_format_i18n( $summary['update'] )
				);
				?>
			</strong>
		</div>

		<div class="psm-preview-summary__item">
			<span>
				<?php
				esc_html_e(
					'Đã tồn tại — không ghi thêm',
					'power-schedule-manager'
				);
				?>
			</span>

			<strong>
				<?php
				echo esc_html(
					number_format_i18n(
						$summary['unchanged']
					)
				);
				?>
			</strong>
		</div>

		<div class="psm-preview-summary__item psm-preview-summary__item--warning">
			<span>
				<?php
				esc_html_e(
					'Có thể trùng',
					'power-schedule-manager'
				);
				?>
			</span>

			<strong>
				<?php
				echo esc_html(
					number_format_i18n(
						$summary['possible_duplicate']
					)
				);
				?>
			</strong>
		</div>
	</div>

	<form
		method="post"
		action="<?php echo esc_url( $import_url ); ?>"
		class="psm-preview-form"
	>
		<?php
		wp_nonce_field(
			'psm_import_confirm',
			'psm_nonce'
		);
		?>

		<input
			type="hidden"
			name="psm_action"
			value="confirm"
		>

		<input
			type="hidden"
			name="unit_code"
			value="<?php echo esc_attr( $unit_code ); ?>"
		>

		<input
			type="hidden"
			name="preview_token"
			value="<?php echo esc_attr( $token ); ?>"
		>

		<textarea
			name="payload"
			hidden
			aria-hidden="true"
			tabindex="-1"
		><?php echo esc_textarea( $payload ); ?></textarea>

		<div class="psm-preview-table-wrap">
			<table class="widefat striped psm-preview-table">
				<thead>
					<tr>
						<th scope="col">
							<?php
							esc_html_e(
								'Trạng thái',
								'power-schedule-manager'
							);
							?>
						</th>

						<th scope="col">
							<?php
							esc_html_e(
								'Thời gian',
								'power-schedule-manager'
							);
							?>
						</th>

						<th scope="col">
							<?php
							esc_html_e(
								'Khu vực',
								'power-schedule-manager'
							);
							?>
						</th>

						<th scope="col">
							<?php
							esc_html_e(
								'Lý do',
								'power-schedule-manager'
							);
							?>
						</th>

						<th scope="col">
							<?php
							esc_html_e(
								'Xử lý',
								'power-schedule-manager'
							);
							?>
						</th>
					</tr>
				</thead>

				<tbody>
					<?php foreach (
						$normalized_items as $index => $normalized
					) : ?>
						<?php
						$item           = $normalized['item'];
						$event          = $normalized['event'];
						$classification = $normalized['classification'];

						$start_at = isset( $event['start_at_utc'] )
							? (string) $event['start_at_utc']
							: '';

						$end_at = isset( $event['end_at_utc'] )
							? (string) $event['end_at_utc']
							: '';

						$area = isset( $event['area'] )
							? sanitize_textarea_field(
								(string) $event['area']
							)
							: '';

						$reason = isset( $event['reason'] )
							? sanitize_textarea_field(
								(string) $event['reason']
							)
							: '';

						$identity_hash = isset( $item['identity_hash'] )
							? sanitize_text_field(
								(string) $item['identity_hash']
							)
							: (
								isset( $event['identity_hash'] )
									? sanitize_text_field(
										(string) $event['identity_hash']
									)
									: ''
							);

						if (
							1 !== preg_match(
								'/\A[a-f0-9]{64}\z/i',
								$identity_hash
							)
						) {
							$identity_hash = hash(
								'sha256',
								$unit_code . '|' .
								$start_at . '|' .
								$end_at . '|' .
								$area . '|' .
								(string) $index
							);
						}

						$row_class = 'psm-preview-row--' .
							sanitize_html_class( $classification );
						?>

						<tr class="<?php echo esc_attr( $row_class ); ?>">
							<td data-label="<?php esc_attr_e( 'Trạng thái', 'power-schedule-manager' ); ?>">
								<span class="psm-preview-status">
									<?php
									echo esc_html(
										$get_status_label(
											$classification
										)
									);
									?>
								</span>
							</td>

							<td data-label="<?php esc_attr_e( 'Thời gian', 'power-schedule-manager' ); ?>">
								<strong>
									<?php
									echo esc_html(
										$format_datetime( $start_at )
									);
									?>
								</strong>

								<br>

								<span aria-hidden="true">→</span>

								<?php
								echo esc_html(
									$format_datetime( $end_at )
								);
								?>
							</td>

							<td data-label="<?php esc_attr_e( 'Khu vực', 'power-schedule-manager' ); ?>">
								<?php
								echo '' !== $area
									? esc_html( $area )
									: '—';
								?>
							</td>

							<td data-label="<?php esc_attr_e( 'Lý do', 'power-schedule-manager' ); ?>">
								<?php
								echo '' !== $reason
									? esc_html( $reason )
									: '—';
								?>
							</td>

							<td data-label="<?php esc_attr_e( 'Xử lý', 'power-schedule-manager' ); ?>">
								<?php if (
									'possible_duplicate'
									=== $classification
								) : ?>
									<label>
										<span class="screen-reader-text">
											<?php
											esc_html_e(
												'Chọn cách xử lý sự kiện có thể trùng',
												'power-schedule-manager'
											);
											?>
										</span>

										<select
											name="duplicate_resolution[<?php echo esc_attr( $identity_hash ); ?>]"
											required
										>
											<option value="skip">
												<?php
												esc_html_e(
													'Bỏ qua an toàn',
													'power-schedule-manager'
												);
												?>
											</option>

											<option value="create">
												<?php
												esc_html_e(
													'Vẫn nhập sự kiện',
													'power-schedule-manager'
												);
												?>
											</option>
										</select>
									</label>
								<?php elseif (
									'error' === $classification
								) : ?>
									<span class="psm-text-error">
										<?php
										esc_html_e(
											'Phải sửa dữ liệu',
											'power-schedule-manager'
										);
										?>
									</span>
								<?php else : ?>
									<span aria-hidden="true">—</span>

									<span class="screen-reader-text">
										<?php
										esc_html_e(
											'Không cần lựa chọn',
											'power-schedule-manager'
										);
										?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php if ( array() === $normalized_items ) : ?>
						<tr>
							<td colspan="5">
								<?php
								esc_html_e(
									'Không có sự kiện hợp lệ để hiển thị.',
									'power-schedule-manager'
								);
								?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="psm-preview-actions">
			<a
				class="button"
				href="<?php echo esc_url( $import_url ); ?>"
			>
				<?php
				esc_html_e(
					'Quay lại nhập dữ liệu',
					'power-schedule-manager'
				);
				?>
			</a>

			<?php
			submit_button(
				__(
					'Xác nhận nhập dữ liệu',
					'power-schedule-manager'
				),
				'primary',
				'submit',
				false,
				$has_blocking_error
					? array( 'disabled' => 'disabled' )
					: array()
			);
			?>
		</div>

		<p class="description">
			<?php
			esc_html_e(
				'Khi xác nhận, plugin sẽ phân tích lại payload và kiểm tra token. Nếu nội dung bị thay đổi sau preview, yêu cầu sẽ bị từ chối.',
				'power-schedule-manager'
			);
			?>
		</p>
	</form>
</div>
