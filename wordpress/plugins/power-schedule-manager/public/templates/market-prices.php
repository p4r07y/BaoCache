<?php
/**
 * Public market-price table.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$args = $psm_template_args;
$rows = is_array( $args['rows'] ?? null ) ? $args['rows'] : array();
$config = is_array( $args['config'] ?? null ) ? $args['config'] : array();
$title = (string) ( $args['title'] ?: ( $config['title'] ?? '' ) );
$section_id = wp_unique_id( 'psm-market-' );
$description = (string) (
	$args['description'] ?: ( $config['description'] ?? '' )
);
$market = (string) ( $args['market'] ?? '' );
$date = (string) ( $rows[0]['price_date'] ?? '' );
$today = wp_date(
	'Y-m-d',
	null,
	new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
);
$current_row = is_array( $rows[0] ?? null ) ? $rows[0] : array();
$has_numeric_value = static function ( mixed $value ): bool {
	return null !== $value
		&& '' !== $value
		&& is_numeric( $value )
		&& (float) $value > 0;
};
$current_candidates = array(
	$current_row['price'] ?? null,
	$current_row['buy_price'] ?? null,
	$current_row['sell_price'] ?? null,
);
$has_current_value = count(
	array_filter( $current_candidates, $has_numeric_value )
) > 0;
if (
	'coffee' === (string) ( $config['commodity'] ?? '' )
	&& count(
		array_filter(
			$current_candidates,
			static fn ( mixed $value ): bool =>
				is_numeric( $value ) && (float) $value > 10000000
		)
	) > 0
) {
	/*
	 * Prevent legacy separator mistakes such as 953,000,000 VND/kg from being
	 * published as a coffee price. Editors keep the row and can correct it.
	 */
	$has_current_value = false;
}
$has_display_data = array() !== $rows && $has_current_value;
$is_updating = ! $has_display_data;
$is_stale = $has_display_data && $date !== $today;
$history = ! empty( $args['history'] );
$is_futures = 'coffee_futures' === $market;
$is_domestic_coffee = 'coffee_domestic' === $market;
$is_domestic_agriculture = $is_domestic_coffee
	|| 'pepper_domestic' === $market;
$is_exchange_rates = 'exchange_rates' === $market;
$is_domestic_gold = 'gold_daily' === $market;
$is_world_gold = 'gold_world' === $market;
$show_footer = ! isset( $args['show_footer'] )
	|| ! empty( $args['show_footer'] );
$show_table = ! isset( $args['show_table'] )
	|| ! empty( $args['show_table'] );
$interactive_chart = $is_world_gold
	&& ! empty( $args['interactive_chart'] );
$compact_header = ! empty( $args['compact_header'] );
$exchange_reference = is_string( $args['exchange_reference'] ?? null )
	? $args['exchange_reference']
	: '';
$chart_rows = is_array( $args['chart_rows'] ?? null )
	? array_reverse( $args['chart_rows'] )
	: array();
$normalize_gold_pair = static function ( array $row ): array {
	$buy = isset( $row['buy_price'] ) && is_numeric( $row['buy_price'] )
		? (float) $row['buy_price']
		: 0.0;
	$sell = isset( $row['sell_price'] ) && is_numeric( $row['sell_price'] )
		? (float) $row['sell_price']
		: 0.0;
	if ( $buy >= 10000 && $sell >= 100 && $sell < 1000 ) {
		$row['sell_price'] = $sell * 1000;
	} elseif ( $sell >= 10000 && $buy >= 100 && $buy < 1000 ) {
		$row['buy_price'] = $buy * 1000;
	}

	return $row;
};
if ( $is_domestic_gold ) {
	$rows = array_map( $normalize_gold_pair, $rows );
	$chart_rows = array_map( $normalize_gold_pair, $chart_rows );
}
$display_unit = static function ( mixed $unit ): string {
	$value = trim( (string) $unit );

	return 'USD/oz' === $value ? 'USD/ounce' : $value;
};
$format_price = static function ( mixed $value ): string {
	if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
		return __( 'Đang cập nhật', 'power-schedule-manager' );
	}
	$number = (float) $value;

	return number_format_i18n(
		$number,
		fmod( $number, 1.0 ) === 0.0 ? 0 : 2
	);
};
$format_integer = static function ( mixed $value ): string {
	if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
		return __( 'Đang cập nhật', 'power-schedule-manager' );
	}

	return number_format_i18n( (int) $value );
};
$format_percentage = static function ( mixed $value ) use ( $format_price ): string {
	if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
		return __( 'Đang cập nhật', 'power-schedule-manager' );
	}

	return $format_price( $value ) . '%';
};
$trend_class = static function ( mixed $value ): string {
	if ( ! is_numeric( $value ) ) {
		return '';
	}

	return (float) $value < 0
		? 'is-down'
		: ( (float) $value > 0 ? 'is-up' : '' );
};
$current_value = $rows[0]['price']
	?? $rows[0]['sell_price']
	?? $rows[0]['buy_price']
	?? null;
$current_change = $rows[0]['change_value'] ?? null;
$domestic_values = array();
if ( $is_domestic_coffee ) {
	foreach ( $rows as $domestic_row ) {
		$domestic_value = $domestic_row['price']
			?? $domestic_row['buy_price']
			?? null;
		if ( is_numeric( $domestic_value ) && (float) $domestic_value > 0 ) {
			$domestic_values[] = array(
				'label' => (string) ( $domestic_row['label'] ?? '' ),
				'value' => (float) $domestic_value,
			);
		}
	}
}
$domestic_average = array() !== $domestic_values
	? array_sum( array_column( $domestic_values, 'value' ) )
		/ count( $domestic_values )
	: null;
$domestic_highest = array() !== $domestic_values
	? max( array_column( $domestic_values, 'value' ) )
	: null;
$domestic_lowest = array() !== $domestic_values
	? min( array_column( $domestic_values, 'value' ) )
	: null;
$domestic_highest_label = '';
foreach ( $domestic_values as $domestic_metric ) {
	if ( $domestic_metric['value'] === $domestic_highest ) {
		$domestic_highest_label = $domestic_metric['label'];
		break;
	}
}
$observed_at = (string) ( $rows[0]['observed_at_utc'] ?? '' );
$updated_label = $is_updating
	? __( 'Chưa có dữ liệu', 'power-schedule-manager' )
	: '';
$updated_datetime = '';
if ( $has_display_data ) {
	if ( $is_world_gold && '' !== $observed_at ) {
		$updated_label = sprintf(
			/* translators: %s: Local date and time. */
			__( 'Cập nhật %s', 'power-schedule-manager' ),
			get_date_from_gmt( $observed_at, 'H:i d/m/Y' )
		);
		$updated_datetime = gmdate(
			DATE_ATOM,
			strtotime( $observed_at . ' UTC' )
		);
	} else {
		$updated_label = $is_stale
			? sprintf(
				/* translators: %s: Latest saved date. */
				__( 'Dữ liệu gần nhất %s', 'power-schedule-manager' ),
				mysql2date( 'd/m/Y', $date )
			)
			: sprintf(
				/* translators: %s: Current data date. */
				__( 'Cập nhật %s', 'power-schedule-manager' ),
				mysql2date( 'd/m/Y', $date )
			);
		$updated_datetime = $date;
	}
}
?>
<section class="psm-market psm-market--<?php echo esc_attr( sanitize_html_class( $market ) ); ?>" aria-labelledby="<?php echo esc_attr( $section_id ); ?>">
	<header class="psm-market__header">
		<div>
			<?php if ( ! $compact_header ) : ?><p class="psm-market__eyebrow"><?php esc_html_e( 'Dữ liệu tham khảo', 'power-schedule-manager' ); ?></p><?php endif; ?>
			<h2 id="<?php echo esc_attr( $section_id ); ?>"><?php echo esc_html( $title ); ?></h2>
			<?php if ( ! $compact_header && '' !== $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
		</div>
		<?php if ( ! $compact_header ) : ?>
			<time<?php echo '' !== $updated_datetime ? ' datetime="' . esc_attr( $updated_datetime ) . '"' : ''; ?>><?php echo esc_html( $updated_label ); ?></time>
		<?php endif; ?>
	</header>
	<?php if ( $is_updating ) : ?>
		<?php if ( $compact_header && ! $has_display_data ) : ?>
			<div class="psm-market__empty" role="status">
				<strong>
					<?php
					echo esc_html(
						$is_futures
							? __( 'Chưa có dữ liệu giá thế giới', 'power-schedule-manager' )
							: __( 'Chưa có dữ liệu để hiển thị', 'power-schedule-manager' )
					);
					?>
				</strong>
				<small><?php esc_html_e( 'Bảng sẽ tự hiển thị sau lần nhập hoặc đồng bộ dữ liệu tiếp theo.', 'power-schedule-manager' ); ?></small>
			</div>
		<?php else : ?>
			<div class="psm-market__updating<?php echo $has_display_data ? ' has-data' : ''; ?>" role="status" aria-live="polite">
				<span aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Đang cập nhật', 'power-schedule-manager' ); ?></strong>
				<?php if ( $has_display_data && '' !== $date ) : ?>
				<small>
					<?php
					printf(
						/* translators: %s: Date of the newest saved data. */
						esc_html__( 'Dữ liệu gần nhất: %s', 'power-schedule-manager' ),
						esc_html( mysql2date( 'd/m/Y', $date ) )
					);
					?>
				</small>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( $has_display_data ) : ?>
	<?php if ( $history ) : ?>
		<div class="psm-market__summary">
			<div>
				<span><?php esc_html_e( 'Mức giá mới nhất', 'power-schedule-manager' ); ?></span>
				<strong><?php echo esc_html( $format_price( $current_value ) ); ?></strong>
				<small><?php echo esc_html( $display_unit( $rows[0]['unit'] ?? '' ) ); ?></small>
			</div>
			<div class="<?php echo esc_attr( $trend_class( $current_change ) ); ?>">
				<span><?php esc_html_e( 'So với lần cập nhật trước', 'power-schedule-manager' ); ?></span>
				<?php if ( is_numeric( $current_change ) ) : ?>
					<strong><?php echo esc_html( (float) $current_change > 0 ? '+' : '' ); ?><?php echo esc_html( $format_price( $current_change ) ); ?></strong>
				<?php else : ?>
					<strong class="is-empty">—</strong>
					<small><?php esc_html_e( 'Chưa đủ dữ liệu để so sánh', 'power-schedule-manager' ); ?></small>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( $is_world_gold ) : ?>
		<div class="psm-market__spot">
			<div>
				<span><?php esc_html_e( 'Giá vàng quốc tế mới nhất', 'power-schedule-manager' ); ?></span>
				<strong><?php echo esc_html( $format_price( $current_value ) ); ?></strong>
				<small><?php echo esc_html( $display_unit( $rows[0]['unit'] ?? 'USD/oz' ) ); ?></small>
			</div>
			<div class="<?php echo esc_attr( $trend_class( $current_change ) ); ?>">
				<span><?php esc_html_e( 'Thay đổi so với ngày trước', 'power-schedule-manager' ); ?></span>
				<strong><?php echo is_numeric( $current_change ) ? esc_html( ( (float) $current_change > 0 ? '+' : '' ) . $format_price( $current_change ) ) : '—'; ?></strong>
				<small><?php echo is_numeric( $current_change ) ? esc_html__( 'Mức giá quốc tế, không phải giá mua bán tại cửa hàng', 'power-schedule-manager' ) : esc_html__( 'Chưa đủ dữ liệu để so sánh với ngày trước', 'power-schedule-manager' ); ?></small>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( $interactive_chart ) : ?>
		<?php
		$widget_config = array(
			'width'               => '100%',
			'height'              => 520,
			'symbol'              => 'FOREXCOM:XAUUSD',
			'interval'            => 'D',
			'timezone'            => POWER_SCHEDULE_MANAGER_TIMEZONE,
			'theme'               => 'light',
			'style'               => '1',
			'locale'              => 'vi_VN',
			'allow_symbol_change' => false,
			'save_image'          => false,
			'details'             => true,
			'hotlist'             => false,
			'hide_volume'         => true,
			'support_host'        => 'https://www.tradingview.com',
			'page-uri'            => home_url( '/' ),
		);
		$widget_url = 'https://www.tradingview-widget.com/embed-widget/advanced-chart/?locale=vi_VN#'
			. rawurlencode(
				(string) wp_json_encode(
					$widget_config,
					JSON_UNESCAPED_SLASHES
				)
			);
		?>
		<figure class="psm-market__tradingview">
			<figcaption><?php esc_html_e( 'Biểu đồ giá vàng thế giới', 'power-schedule-manager' ); ?></figcaption>
			<iframe
				src="<?php echo esc_url( $widget_url ); ?>"
				title="<?php esc_attr_e( 'Biểu đồ giá vàng thế giới', 'power-schedule-manager' ); ?>"
				loading="lazy"
				referrerpolicy="strict-origin-when-cross-origin"
				allowfullscreen
			></iframe>
			<p>
				<?php esc_html_e( 'Biểu đồ giúp theo dõi xu hướng; mức giá có thể thay đổi theo thời điểm.', 'power-schedule-manager' ); ?>
			</p>
		</figure>
	<?php elseif ( ( $is_domestic_gold || $is_world_gold ) && count( $chart_rows ) > 1 ) : ?>
		<?php
		$chart_values = array();
		$chart_columns = $is_world_gold
			? array( 'price' )
			: array( 'buy_price', 'sell_price' );
		foreach ( $chart_rows as $chart_row ) {
			foreach ( $chart_columns as $column ) {
				if (
					isset( $chart_row[ $column ] )
					&& is_numeric( $chart_row[ $column ] )
				) {
					$chart_values[] = (float) $chart_row[ $column ];
				}
			}
		}
		$chart_min = $chart_values ? min( $chart_values ) : 0.0;
		$chart_max = $chart_values ? max( $chart_values ) : 1.0;
		$chart_range = max( 1.0, $chart_max - $chart_min );
		$chart_count = max( 1, count( $chart_rows ) - 1 );
		$points_for = static function ( string $column ) use (
			$chart_rows,
			$chart_min,
			$chart_range,
			$chart_count
		): string {
			$points = array();
			foreach ( $chart_rows as $index => $chart_row ) {
				if (
					! isset( $chart_row[ $column ] )
					|| ! is_numeric( $chart_row[ $column ] )
				) {
					continue;
				}
				$x = 4 + ( 92 * $index / $chart_count );
				$y = 92 - (
					84
					* ( (float) $chart_row[ $column ] - $chart_min )
					/ $chart_range
				);
				$points[] = number_format( $x, 2, '.', '' )
					. ','
					. number_format( $y, 2, '.', '' );
			}

			return implode( ' ', $points );
			};
			?>
			<figure class="psm-market__chart">
				<figcaption>
					<?php
					echo esc_html(
						$is_world_gold
							? __(
								'Giá vàng thế giới trong 30 ngày gần nhất',
								'power-schedule-manager'
							)
							: __(
								'Biểu đồ giá mua và giá bán SJC trong 30 ngày gần nhất',
								'power-schedule-manager'
							)
					);
					?>
				</figcaption>
				<div class="psm-market__chart-summary">
					<span><?php esc_html_e( 'Thấp nhất', 'power-schedule-manager' ); ?> <strong><?php echo esc_html( $format_price( $chart_min ) ); ?></strong></span>
					<span><?php esc_html_e( 'Cao nhất', 'power-schedule-manager' ); ?> <strong><?php echo esc_html( $format_price( $chart_max ) ); ?></strong></span>
				</div>
				<svg viewBox="0 0 100 100" role="img" aria-label="<?php echo esc_attr( $is_world_gold ? __( 'Đường biểu diễn giá vàng thế giới', 'power-schedule-manager' ) : __( 'Đường màu xanh là giá mua, đường màu vàng là giá bán', 'power-schedule-manager' ) ); ?>" preserveAspectRatio="none">
					<?php if ( $is_world_gold ) : ?>
						<polyline class="psm-market__chart-world" points="<?php echo esc_attr( $points_for( 'price' ) ); ?>"></polyline>
					<?php else : ?>
						<polyline class="psm-market__chart-buy" points="<?php echo esc_attr( $points_for( 'buy_price' ) ); ?>"></polyline>
						<polyline class="psm-market__chart-sell" points="<?php echo esc_attr( $points_for( 'sell_price' ) ); ?>"></polyline>
					<?php endif; ?>
				</svg>
				<p>
					<?php if ( $is_world_gold ) : ?>
						<span class="is-world"><?php esc_html_e( 'Giá thế giới', 'power-schedule-manager' ); ?></span>
					<?php else : ?>
						<span class="is-buy"><?php esc_html_e( 'Giá mua', 'power-schedule-manager' ); ?></span>
						<span class="is-sell"><?php esc_html_e( 'Giá bán', 'power-schedule-manager' ); ?></span>
					<?php endif; ?>
				</p>
			</figure>
	<?php elseif ( $is_domestic_gold || $is_world_gold ) : ?>
		<p class="psm-market__chart-empty">
			<?php esc_html_e( 'Biểu đồ sẽ xuất hiện sau khi hệ thống có dữ liệu của ít nhất hai ngày.', 'power-schedule-manager' ); ?>
		</p>
	<?php endif; ?>
	<?php if ( $show_table ) : ?>
	<?php if ( $is_domestic_coffee && null !== $domestic_average ) : ?>
		<div class="psm-coffee-domestic-summary" aria-label="<?php esc_attr_e( 'Tóm tắt giá cà phê trong nước', 'power-schedule-manager' ); ?>">
			<div><span><?php esc_html_e( 'Giá trung bình', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $format_price( $domestic_average ) ); ?></strong><small>VND/kg</small></div>
			<div><span><?php esc_html_e( 'Khu vực cao nhất', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $format_price( $domestic_highest ) ); ?></strong><small><?php echo esc_html( $domestic_highest_label ); ?></small></div>
			<div><span><?php esc_html_e( 'Khoảng giá', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( $format_price( $domestic_lowest ) . ' – ' . $format_price( $domestic_highest ) ); ?></strong><small>VND/kg</small></div>
		</div>
	<?php endif; ?>
	<div class="psm-market__scroll" tabindex="0">
		<table>
			<caption class="screen-reader-text"><?php echo esc_html( $title . ' — ' . mysql2date( 'd/m/Y', $date ) ); ?></caption>
			<?php if ( $history ) : ?>
				<thead><tr><th scope="col"><?php esc_html_e( 'Ngày', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Giá', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Thay đổi', 'power-schedule-manager' ); ?></th></tr></thead>
				<tbody><?php foreach ( $rows as $row ) : ?><tr>
					<th scope="row"><time datetime="<?php echo esc_attr( (string) $row['price_date'] ); ?>"><?php echo esc_html( mysql2date( 'd/m/Y', (string) $row['price_date'] ) ); ?></time><small><?php echo esc_html( (string) $row['label'] ); ?></small></th>
					<td data-label="<?php esc_attr_e( 'Giá', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['price'] ) ); ?> <small><?php echo esc_html( (string) $row['unit'] ); ?></small></td>
					<td data-label="<?php esc_attr_e( 'Thay đổi', 'power-schedule-manager' ); ?>" class="<?php echo esc_attr( $trend_class( $row['change_value'] ) ); ?>"><?php echo esc_html( (float) $row['change_value'] > 0 ? '+' : '' ); ?><?php echo esc_html( $format_price( $row['change_value'] ) ); ?></td>
				</tr><?php endforeach; ?></tbody>
			<?php elseif ( $is_domestic_agriculture ) : ?>
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Khu vực', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Giá thu mua', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Thay đổi', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Ngày ghi nhận', 'power-schedule-manager' ); ?></th>
				</tr></thead>
				<tbody><?php foreach ( $rows as $row ) :
					$coffee_price = $row['price'] ?? $row['buy_price'] ?? null;
					?><tr>
					<th scope="row"><?php echo esc_html( (string) $row['label'] ); ?><small><?php echo esc_html( (string) $row['unit'] ); ?></small></th>
					<td data-label="<?php esc_attr_e( 'Giá thu mua', 'power-schedule-manager' ); ?>"><strong><?php echo esc_html( $format_price( $coffee_price ) ); ?></strong></td>
					<td data-label="<?php esc_attr_e( 'Thay đổi', 'power-schedule-manager' ); ?>" class="<?php echo esc_attr( $trend_class( $row['change_value'] ) ); ?>"><?php echo is_numeric( $row['change_value'] ) ? esc_html( ( (float) $row['change_value'] > 0 ? '+' : '' ) . $format_price( $row['change_value'] ) ) : '—'; ?></td>
					<td data-label="<?php esc_attr_e( 'Ngày ghi nhận', 'power-schedule-manager' ); ?>"><time datetime="<?php echo esc_attr( (string) $row['price_date'] ); ?>"><?php echo esc_html( mysql2date( 'd/m/Y', (string) $row['price_date'] ) ); ?></time></td>
				</tr><?php endforeach; ?></tbody>
			<?php elseif ( $is_futures ) : ?>
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Sản phẩm', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Giá mới nhất', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Thay đổi', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Cao nhất', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Thấp nhất', 'power-schedule-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Lượng giao dịch', 'power-schedule-manager' ); ?></th>
				</tr></thead>
				<tbody><?php foreach ( $rows as $row ) : ?><tr>
					<th scope="row"><?php echo esc_html( (string) $row['label'] ); ?></th>
					<td data-label="<?php esc_attr_e( 'Giá mới nhất', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Thay đổi', 'power-schedule-manager' ); ?>" class="<?php echo esc_attr( $trend_class( $row['change_value'] ) ); ?>"><?php echo esc_html( (float) $row['change_value'] > 0 ? '+' : '' ); ?><?php echo esc_html( $format_price( $row['change_value'] ) ); ?><small><?php echo esc_html( $format_percentage( $row['change_percent'] ) ); ?></small></td>
					<td data-label="<?php esc_attr_e( 'Cao nhất', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['high_price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Thấp nhất', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['low_price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Lượng giao dịch', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_integer( $row['volume'] ) ); ?></td>
				</tr><?php endforeach; ?></tbody>
				<?php elseif ( $is_domestic_gold ) : ?>
					<thead><tr><th scope="col"><?php esc_html_e( 'Hệ thống', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Giá mua', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Giá bán', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Chênh lệch', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Biến động mua / bán', 'power-schedule-manager' ); ?></th></tr></thead>
				<tbody><?php foreach ( $rows as $row ) : ?><tr>
					<th scope="row"><?php echo esc_html( (string) $row['label'] ); ?><small><?php echo esc_html( (string) $row['unit'] ); ?></small></th>
					<td data-label="<?php esc_attr_e( 'Giá mua', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['buy_price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Giá bán', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['sell_price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Chênh lệch', 'power-schedule-manager' ); ?>"><?php echo esc_html( is_numeric( $row['buy_price'] ) && is_numeric( $row['sell_price'] ) ? $format_price( (float) $row['sell_price'] - (float) $row['buy_price'] ) : __( 'Đang cập nhật', 'power-schedule-manager' ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Biến động', 'power-schedule-manager' ); ?>"><span class="<?php echo esc_attr( $trend_class( $row['buy_change'] ) ); ?>"><?php echo esc_html( (float) $row['buy_change'] > 0 ? '+' : '' ); ?><?php echo esc_html( $format_price( $row['buy_change'] ) ); ?></span> / <span class="<?php echo esc_attr( $trend_class( $row['sell_change'] ) ); ?>"><?php echo esc_html( (float) $row['sell_change'] > 0 ? '+' : '' ); ?><?php echo esc_html( $format_price( $row['sell_change'] ) ); ?></span></td>
					</tr><?php endforeach; ?></tbody>
				<?php elseif ( $is_world_gold ) : ?>
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Loại giá', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Giá mới nhất', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Thay đổi', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Thời điểm ghi nhận', 'power-schedule-manager' ); ?></th>
					</tr></thead>
					<tbody><?php foreach ( $rows as $row ) : ?><tr>
					<th scope="row"><?php esc_html_e( 'Vàng thế giới', 'power-schedule-manager' ); ?><small><?php echo esc_html( $display_unit( $row['unit'] ) ); ?></small></th>
						<td data-label="<?php esc_attr_e( 'Giá mới nhất', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['price'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Thay đổi', 'power-schedule-manager' ); ?>" class="<?php echo esc_attr( $trend_class( $row['change_value'] ) ); ?>"><?php echo esc_html( (float) $row['change_value'] > 0 ? '+' : '' ); ?><?php echo esc_html( $format_price( $row['change_value'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Ghi nhận', 'power-schedule-manager' ); ?>"><?php echo esc_html( '' !== (string) $row['observed_at_utc'] ? get_date_from_gmt( (string) $row['observed_at_utc'], 'H:i d/m/Y' ) : __( 'Đang cập nhật', 'power-schedule-manager' ) ); ?></td>
					</tr><?php endforeach; ?></tbody>
				<?php elseif ( $is_exchange_rates ) : ?>
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Ngoại tệ / ngân hàng', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Mua tiền mặt', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Mua chuyển khoản', 'power-schedule-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Bán ra', 'power-schedule-manager' ); ?></th>
					</tr></thead>
					<tbody><?php foreach ( $rows as $row ) : ?><tr>
						<th scope="row"><?php echo esc_html( (string) $row['label'] ); ?><small><?php echo esc_html( (string) $row['unit'] ); ?></small></th>
						<td data-label="<?php esc_attr_e( 'Mua tiền mặt', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['buy_price'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Mua chuyển khoản', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['price'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Bán ra', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['sell_price'] ) ); ?></td>
					</tr><?php endforeach; ?></tbody>
				<?php else : ?>
				<thead><tr><th scope="col"><?php esc_html_e( 'Khu vực / loại', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Giá', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Mua vào', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Bán ra', 'power-schedule-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Thay đổi', 'power-schedule-manager' ); ?></th></tr></thead>
				<tbody><?php foreach ( $rows as $row ) : ?><tr>
					<th scope="row"><?php echo esc_html( (string) $row['label'] ); ?><small><?php echo esc_html( (string) $row['unit'] ); ?></small></th>
					<td data-label="<?php esc_attr_e( 'Giá', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Mua vào', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['buy_price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Bán ra', 'power-schedule-manager' ); ?>"><?php echo esc_html( $format_price( $row['sell_price'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Thay đổi', 'power-schedule-manager' ); ?>" class="<?php echo esc_attr( $trend_class( $row['change_value'] ) ); ?>"><?php echo esc_html( (float) $row['change_value'] > 0 ? '+' : '' ); ?><?php echo esc_html( $format_price( $row['change_value'] ) ); ?></td>
				</tr><?php endforeach; ?></tbody>
			<?php endif; ?>
		</table>
	</div>
	<?php endif; ?>
	<?php if ( '' !== $exchange_reference ) : ?>
		<?php echo $exchange_reference; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<?php if ( $show_footer ) : ?>
	<footer class="psm-market__footer">
		<p><?php esc_html_e( 'Giá có thể khác theo thời điểm, chất lượng, thương hiệu và điểm giao dịch. Thông tin không phải khuyến nghị mua bán.', 'power-schedule-manager' ); ?></p>
		<?php if ( ! empty( $args['show_content'] ) ) : ?>
			<div class="psm-market__guide">
				<h3><?php esc_html_e( 'Cách đọc và sử dụng bảng giá', 'power-schedule-manager' ); ?></h3>
				<p><?php esc_html_e( 'Đối chiếu đúng ngày cập nhật và đơn vị tính trước khi sử dụng. Cột thay đổi cho biết mức chênh lệch so với bản ghi liền trước.', 'power-schedule-manager' ); ?></p>
				<p>
					<?php
					$guide = match ( (string) ( $config['commodity'] ?? '' ) ) {
						'gold' => __( 'Giá vàng mua vào và bán ra có thể khác giữa thương hiệu, loại vàng và địa điểm giao dịch. Hãy kiểm tra trực tiếp tại đơn vị niêm yết trước khi quyết định.', 'power-schedule-manager' ),
						'pepper' => __( 'Giá hồ tiêu thực tế thay đổi theo chất lượng, độ ẩm, vùng nguyên liệu, đại lý và thời điểm chốt giá. Bảng này hỗ trợ đối chiếu nhanh, không thay thế báo giá tại điểm thu mua.', 'power-schedule-manager' ),
						'fx' => __( 'Tỷ giá có thể khác giữa ngân hàng, thị trường và thời điểm giao dịch. Hãy đối chiếu đúng loại tỷ giá mua, bán hoặc chuyển khoản tại đơn vị cung cấp.', 'power-schedule-manager' ),
						default => __( 'Giá cà phê thực tế có thể thay đổi theo chất lượng hạt, tỷ lệ tạp, độ ẩm, điểm thu mua và thời điểm chốt giá. Bảng này hỗ trợ tra cứu nhanh, không thay thế báo giá tại đại lý.', 'power-schedule-manager' ),
					};
					echo esc_html( $guide );
					?>
				</p>
			</div>
		<?php endif; ?>
	</footer>
	<?php endif; ?>
	<?php endif; ?>
</section>
