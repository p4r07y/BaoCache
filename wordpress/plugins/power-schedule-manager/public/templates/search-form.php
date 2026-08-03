<?php
/**
 * Public schedule search form.
 *
 * Theme override:
 * cup-dien-lam-dong/power-schedule-manager/search-form.php
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$units = isset( $psm_template_args['units'] )
	&& is_array( $psm_template_args['units'] )
	? $psm_template_args['units']
	: array();

$action = isset( $psm_template_args['action'] )
	&& is_string( $psm_template_args['action'] )
	? esc_url_raw(
		$psm_template_args['action'],
		array( 'http', 'https' )
	)
	: '';

if ( '' === $action ) {
	$archive_url = get_post_type_archive_link(
		Power_Schedule_Manager_Post_Type::POST_TYPE
	);

	$action = is_string( $archive_url )
		? $archive_url
		: home_url( '/' );
}

$button_text = isset( $psm_template_args['button_text'] )
	&& is_string( $psm_template_args['button_text'] )
	? sanitize_text_field(
		$psm_template_args['button_text']
	)
	: __(
		'Tra cứu',
		'power-schedule-manager'
	);
$unit_search = true === ( $psm_template_args['unit_search'] ?? false );
$compact = true === ( $psm_template_args['compact'] ?? false );

$selected_unit = isset( $psm_template_args['selected_unit'] )
	? Power_Schedule_Manager_Units::sanitize_code(
		$psm_template_args['selected_unit']
	)
	: '';

$selected_date = isset( $psm_template_args['selected_date'] )
	&& is_string( $psm_template_args['selected_date'] )
	? sanitize_text_field(
		$psm_template_args['selected_date']
	)
	: '';

try {
	$selected_date =
		Power_Schedule_Manager_Validator::validate_local_date(
			$selected_date
		);
} catch ( InvalidArgumentException ) {
	$selected_date = wp_date(
		'Y-m-d',
		null,
		new DateTimeZone(
			POWER_SCHEDULE_MANAGER_TIMEZONE
		)
	);
}

$today_date = wp_date(
	'Y-m-d',
	null,
	new DateTimeZone(
		POWER_SCHEDULE_MANAGER_TIMEZONE
	)
);

if ( $selected_date < $today_date ) {
	$selected_date = $today_date;
}

/*
 * Unique IDs prevent duplicate label targets when a page contains more
 * than one search shortcode.
 */
$form_id = wp_unique_id( 'psm-search-' );
$unit_id = $form_id . '-unit';
$unit_filter_id = $form_id . '-unit-filter';
$date_id = $form_id . '-date';

$has_filter = '' !== $selected_unit
	|| (
		isset( $_GET['psm_date'] )
		&& '' !== sanitize_text_field(
			wp_unslash(
				(string) $_GET['psm_date']
			)
		)
	);

if ( $compact ) :
	$list_id = $form_id . '-units';
	?>
	<form
		id="<?php echo esc_attr( $form_id ); ?>"
		class="psm-search psm-search--compact"
		method="get"
		action="<?php echo esc_url( $action ); ?>"
		role="search"
		data-psm-compact-unit-search
	>
		<label class="screen-reader-text" for="<?php echo esc_attr( $unit_filter_id ); ?>">
			<?php esc_html_e( 'Nhập khu vực điện lực cần tra cứu', 'power-schedule-manager' ); ?>
		</label>
		<span class="psm-search__compact-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" focusable="false">
				<circle cx="10.8" cy="10.8" r="6.2"></circle>
				<path d="m15.4 15.4 4.1 4.1"></path>
			</svg>
		</span>
		<label class="screen-reader-text" for="<?php echo esc_attr( $unit_filter_id . '-mobile' ); ?>">
			<?php esc_html_e( 'Chọn khu vực điện lực', 'power-schedule-manager' ); ?>
		</label>
		<select
			id="<?php echo esc_attr( $unit_filter_id . '-mobile' ); ?>"
			class="psm-search__mobile-unit"
			aria-label="<?php esc_attr_e( 'Chọn khu vực điện lực', 'power-schedule-manager' ); ?>"
			data-psm-mobile-unit-select
		>
			<option value=""><?php esc_html_e( 'Chọn khu vực của bạn', 'power-schedule-manager' ); ?></option>
			<?php foreach ( $units as $unit ) : ?>
				<?php
				if ( ! is_array( $unit ) || empty( $unit['is_public'] ) ) {
					continue;
				}
				$mobile_code = Power_Schedule_Manager_Units::sanitize_code(
					$unit['code'] ?? ''
				);
				$mobile_name = sanitize_text_field(
					(string) ( $unit['name'] ?? '' )
				);
				if ( '' === $mobile_code || '' === $mobile_name ) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr( $mobile_code ); ?>"><?php echo esc_html( $mobile_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<input
			id="<?php echo esc_attr( $unit_filter_id ); ?>"
			type="search"
			class="psm-search__unit-filter"
			placeholder="<?php esc_attr_e( 'Ví dụ: Đà Lạt, Bảo Lộc…', 'power-schedule-manager' ); ?>"
			autocomplete="off"
			role="combobox"
			aria-autocomplete="list"
			aria-controls="<?php echo esc_attr( $list_id ); ?>"
			aria-expanded="false"
			data-psm-compact-unit-input
		>
		<div
			id="<?php echo esc_attr( $list_id ); ?>"
			class="psm-search__autocomplete"
			role="listbox"
			hidden
			data-psm-compact-unit-list
		>
			<?php foreach ( $units as $unit ) : ?>
				<?php
				if ( ! is_array( $unit ) || empty( $unit['is_public'] ) ) {
					continue;
				}
				$code = Power_Schedule_Manager_Units::sanitize_code(
					$unit['code'] ?? ''
				);
				$name = sanitize_text_field( (string) ( $unit['name'] ?? '' ) );
				if ( '' === $code || '' === $name ) {
					continue;
				}
				?>
				<button
					id="<?php echo esc_attr( $list_id . '-' . strtolower( $code ) ); ?>"
					type="button"
					class="psm-search__autocomplete-option"
					role="option"
					aria-selected="false"
					data-psm-compact-unit-option
					data-code="<?php echo esc_attr( $code ); ?>"
					data-label="<?php echo esc_attr( $name ); ?>"
				>
					<span><?php echo esc_html( $name ); ?></span>
					<small><?php echo esc_html( $code ); ?></small>
				</button>
			<?php endforeach; ?>
		</div>
		<input type="hidden" name="psm_unit" value="" data-psm-compact-unit-value>
		<input type="hidden" name="psm_date" value="<?php echo esc_attr( $today_date ); ?>">
		<button type="submit" class="psm-button psm-button--search">
			<?php echo esc_html( $button_text ); ?>
		</button>
	</form>
	<?php
	return;
endif;
?>

<form
	id="<?php echo esc_attr( $form_id ); ?>"
	class="psm-search"
	method="get"
	action="<?php echo esc_url( $action ); ?>"
	role="search"
	aria-label="<?php
	esc_attr_e(
		'Tra cứu lịch cúp điện',
		'power-schedule-manager'
	);
	?>"
>
	<?php if ( $unit_search ) : ?>
		<div class="psm-search__field psm-search__field--unit-filter">
			<label for="<?php echo esc_attr( $unit_filter_id ); ?>">
				<?php esc_html_e( 'Tìm nhanh khu vực', 'power-schedule-manager' ); ?>
			</label>
			<input
				id="<?php echo esc_attr( $unit_filter_id ); ?>"
				type="search"
				class="psm-search__unit-filter"
				placeholder="<?php esc_attr_e( 'Nhập Đà Lạt, Bảo Lộc…', 'power-schedule-manager' ); ?>"
				autocomplete="off"
				data-psm-unit-filter
				data-psm-unit-target="<?php echo esc_attr( $unit_id ); ?>"
			>
		</div>
	<?php endif; ?>

	<div class="psm-search__field">
		<label for="<?php echo esc_attr( $unit_id ); ?>">
			<?php
			esc_html_e(
				'Khu vực điện lực',
				'power-schedule-manager'
			);
			?>
		</label>

		<select
			id="<?php echo esc_attr( $unit_id ); ?>"
			name="psm_unit"
			class="psm-search__select"
		>
			<option value="">
				<?php
				esc_html_e(
					'Tất cả khu vực',
					'power-schedule-manager'
				);
				?>
			</option>

			<?php foreach ( $units as $unit ) : ?>
				<?php
				if (
					! is_array( $unit )
					|| empty( $unit['is_public'] )
				) {
					continue;
				}

				$unit_code =
					Power_Schedule_Manager_Units::sanitize_code(
						$unit['code'] ?? ''
					);

				$unit_name = isset( $unit['name'] )
					? sanitize_text_field(
						(string) $unit['name']
					)
					: '';

				if (
					'' === $unit_code
					|| '' === $unit_name
				) {
					continue;
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
					<?php echo esc_html( $unit_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="psm-search__field">
		<label for="<?php echo esc_attr( $date_id ); ?>">
			<?php
			esc_html_e(
				'Ngày bắt đầu',
				'power-schedule-manager'
			);
			?>
		</label>

		<input
			id="<?php echo esc_attr( $date_id ); ?>"
			name="psm_date"
			class="psm-search__date"
			type="date"
			value="<?php echo esc_attr( $selected_date ); ?>"
			min="<?php echo esc_attr( $today_date ); ?>"
			required
		>
	</div>

	<div class="psm-search__actions">
		<button
			type="submit"
			class="psm-button psm-button--search"
		>
			<?php echo esc_html( $button_text ); ?>
		</button>

		<?php if ( $has_filter ) : ?>
			<a
				class="psm-button psm-button--secondary"
				href="<?php echo esc_url( $action ); ?>"
			>
				<?php
				esc_html_e(
					'Xóa bộ lọc',
					'power-schedule-manager'
				);
				?>
			</a>
		<?php endif; ?>
	</div>
</form>
