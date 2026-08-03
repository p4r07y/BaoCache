<?php
/**
 * Raw schedule import form.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$units = isset( $psm_admin_args['units'] )
	&& is_array( $psm_admin_args['units'] )
	? $psm_admin_args['units']
	: array();

$form = isset( $psm_admin_args['form'] )
	&& is_array( $psm_admin_args['form'] )
	? $psm_admin_args['form']
	: array();

$result = isset( $psm_admin_args['result'] )
	&& is_array( $psm_admin_args['result'] )
	? $psm_admin_args['result']
	: null;

$error = isset( $psm_admin_args['error'] )
	&& is_string( $psm_admin_args['error'] )
	? $psm_admin_args['error']
	: '';

$selected_unit = isset( $form['unit_code'] )
	? sanitize_text_field( (string) $form['unit_code'] )
	: '';

$payload = isset( $form['payload'] )
	? (string) $form['payload']
	: '';

$import_page_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Admin::IMPORT_SLUG,
	),
	admin_url( 'admin.php' )
);

$schedule_list_url = add_query_arg(
	array(
		'post_type' => Power_Schedule_Manager_Post_Type::POST_TYPE,
	),
	admin_url( 'edit.php' )
);

$place_library_url = add_query_arg(
	array(
		'page'         => Power_Schedule_Manager_Place_Library::MENU_SLUG,
		'place_status' => 'pending',
	),
	admin_url( 'admin.php' )
);
?>

<div class="wrap psm-admin-wrap">
	<h1>
		<?php
		esc_html_e(
			'Nhập dữ liệu lịch điện',
			'power-schedule-manager'
		);
		?>
	</h1>

	<p class="psm-admin-lead">
		<?php
		esc_html_e(
			'Dán nội dung lịch điện từ nguồn, kiểm tra bản xem trước rồi mới xác nhận ghi dữ liệu.',
			'power-schedule-manager'
		);
		?>
	</p>

	<?php if ( '' !== $error ) : ?>
		<div
			class="notice notice-error"
			role="alert"
			data-psm-import-error
		>
			<p>
				<strong>
					<?php
					esc_html_e(
						'Không thể xử lý dữ liệu:',
						'power-schedule-manager'
					);
					?>
				</strong>

				<?php echo esc_html( $error ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( null !== $result ) : ?>
		<?php
		$inserted = isset( $result['inserted'] )
			? absint( $result['inserted'] )
			: 0;

		$updated = isset( $result['updated'] )
			? absint( $result['updated'] )
			: 0;

		$unchanged = isset( $result['unchanged'] )
			? absint( $result['unchanged'] )
			: 0;

		$duplicates_skipped =
			isset( $result['duplicates_skipped'] )
				? absint( $result['duplicates_skipped'] )
				: 0;

		$places_discovered =
			isset( $result['places_discovered'] )
				? absint( $result['places_discovered'] )
				: 0;

		$run_id = isset( $result['run_id'] )
			? absint( $result['run_id'] )
			: 0;

		$posts = isset( $result['posts'] )
			&& is_array( $result['posts'] )
			? array_values(
				array_filter(
					array_map( 'absint', $result['posts'] )
				)
			)
			: array();
		?>

		<div
			class="notice notice-success"
			role="status"
		>
			<p>
				<strong>
					<?php
					esc_html_e(
						'Nhập dữ liệu thành công.',
						'power-schedule-manager'
					);
					?>
				</strong>
			</p>

			<ul>
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Inserted event count. */
							__(
								'Thêm mới: %d sự kiện',
								'power-schedule-manager'
							),
							$inserted
						)
					);
					?>
				</li>

				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Newly discovered map places. */
							__(
								'Địa điểm bản đồ mới chờ hoàn thiện: %d',
								'power-schedule-manager'
							),
							$places_discovered
						)
					);
					?>
				</li>

				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Updated event count. */
							__(
								'Cập nhật: %d sự kiện',
								'power-schedule-manager'
							),
							$updated
						)
					);
					?>
				</li>

				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Exact duplicate event count. */
							__(
								'Đã nhận diện trùng chính xác, không ghi thêm: %d sự kiện',
								'power-schedule-manager'
							),
							$unchanged
						)
					);
					?>
				</li>

				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Skipped duplicate count. */
							__(
								'Bỏ qua do có thể trùng: %d sự kiện',
								'power-schedule-manager'
							),
							$duplicates_skipped
						)
					);
					?>
				</li>

				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Number of schedule posts. */
							__(
								'Lịch theo đơn vị và ngày: %d bài',
								'power-schedule-manager'
							),
							count( $posts )
						)
					);
					?>
				</li>

				<?php if ( $run_id > 0 ) : ?>
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: Import run ID. */
								__(
									'Mã lần nhập: #%d',
									'power-schedule-manager'
								),
								$run_id
							)
						);
						?>
					</li>
				<?php endif; ?>
			</ul>

			<p>
				<a
					class="button button-primary"
					href="<?php echo esc_url( $schedule_list_url ); ?>"
				>
					<?php
					esc_html_e(
						'Kiểm tra lịch vừa nhập',
						'power-schedule-manager'
					);
					?>
				</a>

				<a
					class="button"
					href="<?php echo esc_url( $import_page_url ); ?>"
				>
					<?php
					esc_html_e(
						'Nhập dữ liệu khác',
						'power-schedule-manager'
					);
					?>
				</a>

				<?php if ( $places_discovered > 0 ) : ?>
					<a
						class="button"
						href="<?php echo esc_url( $place_library_url ); ?>"
					>
						<?php
						esc_html_e(
							'Hoàn thiện địa điểm bản đồ',
							'power-schedule-manager'
						);
						?>
					</a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( null === $result ) : ?>
		<div class="psm-import-layout">
			<section class="psm-dashboard-panel">
				<form
					method="post"
					action="<?php echo esc_url( $import_page_url ); ?>"
					class="psm-import-form"
				>
					<?php
					wp_nonce_field(
						'psm_import_preview',
						'psm_nonce'
					);
					?>

					<input
						type="hidden"
						name="psm_action"
						value="preview"
					>

					<div class="psm-import-stepper" aria-label="<?php esc_attr_e( 'Quy trình nhập dữ liệu', 'power-schedule-manager' ); ?>">
						<span class="is-active"><strong>1</strong><?php esc_html_e( 'Chọn đơn vị', 'power-schedule-manager' ); ?></span>
						<span><strong>2</strong><?php esc_html_e( 'Dán và kiểm tra', 'power-schedule-manager' ); ?></span>
						<span><strong>3</strong><?php esc_html_e( 'Ghi database', 'power-schedule-manager' ); ?></span>
					</div>

					<div class="psm-import-fields">
						<div class="psm-import-field psm-import-field--unit">
							<div class="psm-import-field__label">
								<label for="psm-unit-code">
									<?php esc_html_e( 'Đơn vị điện lực', 'power-schedule-manager' ); ?>
								</label>
								<p><?php esc_html_e( 'Chọn đơn vị trước để giới hạn dữ liệu, tránh nhập nhầm khu vực.', 'power-schedule-manager' ); ?></p>
							</div>
							<div class="psm-import-field__control">
								<select
									id="psm-unit-code"
									name="unit_code"
									class="regular-text"
									required
								>
									<option value="">
										<?php
										esc_html_e(
											'— Chọn đơn vị điện lực —',
											'power-schedule-manager'
										);
										?>
									</option>

									<?php foreach ( $units as $unit ) : ?>
										<?php
										if ( ! is_array( $unit ) ) {
											continue;
										}

										$unit_code = isset( $unit['code'] )
											? sanitize_text_field(
												(string) $unit['code']
											)
											: '';

										$unit_name = isset( $unit['name'] )
											? sanitize_text_field(
												(string) $unit['name']
											)
											: '';

										$is_public = ! empty(
											$unit['is_public']
										);

										if (
											'' === $unit_code
											|| '' === $unit_name
										) {
											continue;
										}

										$option_label = sprintf(
											'%1$s — %2$s',
											$unit_code,
											$unit_name
										);

										if ( ! $is_public ) {
											$option_label .= ' — ' .
												__(
													'Nội bộ',
													'power-schedule-manager'
												);
										}
										?>

										<option
											value="<?php echo esc_attr( $unit_code ); ?>"
											<?php
											selected(
												$selected_unit,
												$unit_code
											);
											?>
										>
											<?php echo esc_html( $option_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>

								<p class="description">
									<?php
									esc_html_e(
										'Đơn vị đã chọn được dùng để kiểm tra, chống nhập nhầm và liên kết dữ liệu.',
										'power-schedule-manager'
									);
									?>
								</p>
							</div>
						</div>

						<div class="psm-import-field psm-import-field--payload">
							<div class="psm-import-field__label">
								<label for="psm-payload">
									<?php esc_html_e( 'Dữ liệu lịch điện', 'power-schedule-manager' ); ?>
								</label>
								<p><?php esc_html_e( 'Dán nguyên khối thông báo từ điện lực. Plugin sẽ chuẩn hóa HTML, markdown và khoảng trắng.', 'power-schedule-manager' ); ?></p>
							</div>
							<div class="psm-import-field__control">
									<textarea
										id="psm-payload"
										name="payload"
										class="large-text code"
										rows="20"
										required
										spellcheck="false"
										autocomplete="off"
										placeholder="<?php
										echo esc_attr(
											"Thông báo lịch ngừng giảm cung cấp điện\nĐơn vị: Điện lực Đà Lạt\nKHU VỰC: Vạn Kiếp, Tô Hiệu\nTHỜI GIAN: Từ 08:00:00 ngày 24/07/2026 đến 11:00:00 ngày 24/07/2026\nLÝ DO NGỪNG CUNG CẤP ĐIỆN: Sửa chữa, bảo dưỡng"
										);
										?>"
									><?php echo esc_textarea( $payload ); ?></textarea>
							</div>
						</div>
					</div>

					<div class="psm-import-warning">
						<h2>
							<?php
							esc_html_e(
								'Lưu ý trước khi kiểm tra',
								'power-schedule-manager'
							);
							?>
						</h2>

						<p>
							<?php
							esc_html_e(
								'Bước kiểm tra chưa ghi dữ liệu vào database. Khối có nhãn KHU VỰC nhưng không có nội dung sẽ được bỏ qua và cảnh báo trong bản xem trước; lỗi đơn vị, thời gian hoặc cấu trúc vẫn chặn lần nhập.',
								'power-schedule-manager'
							);
							?>
						</p>
					</div>

					<?php if ( '' !== $error ) : ?>
						<div
							class="psm-import-inline-error"
							role="alert"
						>
							<strong>
								<?php
								esc_html_e(
									'Chưa thể chuyển sang bản xem trước.',
									'power-schedule-manager'
								);
								?>
							</strong>

							<span><?php echo esc_html( $error ); ?></span>
						</div>
					<?php endif; ?>

					<div class="psm-import-actions">
						<?php
						submit_button(
							__(
								'Kiểm tra dữ liệu trước khi nhập',
								'power-schedule-manager'
							),
							'primary',
							'submit',
							false
						);
						?>

						<p
							class="description"
							data-psm-import-submit-status
							role="status"
							aria-live="polite"
						></p>
					</div>
				</form>

				<div class="psm-import-footnote">
					<strong>
						<?php
						esc_html_e(
							'Định dạng hỗ trợ:',
							'power-schedule-manager'
						);
						?>
					</strong>

					<?php
					esc_html_e(
						'KHU VỰC, THỜI GIAN, LÝ DO hoặc LÍ DO; có thể dán HTML, markdown, nhiều sự kiện và khoảng trắng đặc biệt. Nội dung dán không được thực thi như mã HTML/JavaScript.',
						'power-schedule-manager'
					);
					?>
				</div>
			</section>
		</div>
	<?php endif; ?>
</div>
