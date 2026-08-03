<?php
/**
 * Lazy-loaded public event map dialog.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/map.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/*
 * Every rendered schedule receives a unique dialog heading ID so multiple
 * shortcodes can safely exist on the same page.
 */
$dialog_id = wp_unique_id( 'psm-map-dialog-' );
$title_id  = $dialog_id . '-title';
$status_id = $dialog_id . '-status';
$notice_id = $dialog_id . '-notice';
?>

<div
	class="psm-map-modal"
	data-psm-map-modal
	hidden
>
	<button
		type="button"
		class="psm-map-modal__backdrop"
		data-psm-map-close
		aria-label="<?php
		esc_attr_e(
			'Đóng bản đồ',
			'power-schedule-manager'
		);
		?>"
		tabindex="-1"
	></button>

	<div
		id="<?php echo esc_attr( $dialog_id ); ?>"
		class="psm-map-modal__dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
		aria-describedby="<?php echo esc_attr( $notice_id . ' ' . $status_id ); ?>"
		tabindex="-1"
	>
		<header class="psm-map-modal__header">
			<h2
				id="<?php echo esc_attr( $title_id ); ?>"
				class="psm-map-modal__title"
			>
				<?php
				esc_html_e(
					'Bản đồ khu vực có lịch cúp điện',
					'power-schedule-manager'
				);
				?>
			</h2>

			<button
				type="button"
				class="psm-map-modal__close"
				data-psm-map-close
				aria-label="<?php
				esc_attr_e(
					'Đóng bản đồ',
					'power-schedule-manager'
				);
				?>"
			>
				<svg
					viewBox="0 0 24 24"
					aria-hidden="true"
					focusable="false"
				>
					<path d="M6 6l12 12M18 6 6 18"></path>
				</svg>
			</button>
		</header>

		<div class="psm-map-modal__body">
			<aside
				id="<?php echo esc_attr( $notice_id ); ?>"
				class="psm-map-estimate-note"
				role="note"
			>
				<span class="psm-map-estimate-note__icon" aria-hidden="true">i</span>
				<span>
					<strong>
						<?php esc_html_e( 'Bản đồ minh họa', 'power-schedule-manager' ); ?>
					</strong>
					<span>
						<?php
						esc_html_e(
							'Tuyến đường được đánh dấu nhằm mô phỏng khu vực có thể bị ảnh hưởng, không xác định chính xác phạm vi cúp điện.',
							'power-schedule-manager'
						);
						?>
					</span>
				</span>
			</aside>

			<p
				id="<?php echo esc_attr( $status_id ); ?>"
				class="psm-map-status"
				data-psm-map-status
				role="status"
				aria-live="polite"
			></p>

			<div
				class="psm-map-canvas"
				data-psm-map-canvas
				aria-label="<?php
				esc_attr_e(
					'Bản đồ vị trí có lịch cúp điện',
					'power-schedule-manager'
				);
				?>"
			></div>

			<section
				class="psm-map-locations-summary"
				data-psm-map-locations
				aria-labelledby="<?php
				echo esc_attr(
					$title_id . '-locations'
				);
				?>"
				hidden
			>
				<h3 id="<?php echo esc_attr( $title_id . '-locations' ); ?>">
					<?php
					esc_html_e(
						'Các vị trí trong khu vực',
						'power-schedule-manager'
					);
					?>
				</h3>

				<ul data-psm-map-location-list></ul>
			</section>

			<noscript>
				<p class="psm-map-noscript">
					<?php
					esc_html_e(
						'Trình duyệt cần bật JavaScript để hiển thị bản đồ tương tác.',
						'power-schedule-manager'
					);
					?>
				</p>
			</noscript>
		</div>

		<footer class="psm-map-modal__footer">
			<p>
				<?php
				esc_html_e(
					'Vui lòng đối chiếu thêm phần “Khu vực ảnh hưởng” trong lịch điện.',
					'power-schedule-manager'
				);
				?>
			</p>

			<button
				type="button"
				class="psm-button psm-button--secondary"
				data-psm-map-close
			>
				<?php
				esc_html_e(
					'Đóng bản đồ',
					'power-schedule-manager'
				);
				?>
			</button>
		</footer>
	</div>
</div>
