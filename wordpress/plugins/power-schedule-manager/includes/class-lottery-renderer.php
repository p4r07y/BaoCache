<?php
/**
 * Accessible, responsive lottery result layouts.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders stored lottery data. Provider requests never run on frontend.
 */
final class Power_Schedule_Manager_Lottery_Renderer {

	/**
	 * Render a preset layout.
	 *
	 * @param array<int,array<string,string>> $rows Stored rows.
	 */
	public static function render(
		string $preset,
		array $rows,
		string $custom_title = ''
	): string {
		$title = '' !== trim( $custom_title )
			? sanitize_text_field( $custom_title )
			: self::title( $preset );
		$id = wp_unique_id( 'psm-lottery-' );
		$input_row_count = count( $rows );
		$rows = array_values(
			array_filter(
				$rows,
				array( self::class, 'row_has_valid_results_json' )
			)
		);
		$payload_error = $input_row_count > 0 && array() === $rows;
		if ( $payload_error ) {
			Power_Schedule_Manager_Logger::error(
				'lottery_invalid_results_json',
				'All stored result rows for this preset contain invalid JSON.',
				array( 'preset' => $preset, 'row_count' => $input_row_count )
			);
		}
		$is_traditional = in_array(
			$preset,
			array( 'north', 'central', 'south' ),
			true
		);
		$status_rows = $is_traditional
			? self::prepare_traditional_rows( $rows, $preset )
			: $rows;
		$has_live_draw = count(
			array_filter(
				$status_rows,
				static fn ( array $row ): bool =>
					self::draw_is_live( $row, $preset )
			)
		) > 0;
		$has_waiting_draw = ! $is_traditional
			&& count(
				array_filter(
					$status_rows,
					static fn ( array $row ): bool =>
						! self::draw_has_results( $row )
				)
			) > 0;
		$is_updating = array() === $rows || $has_live_draw;
		$status_label = $has_live_draw
			? __( 'Đang quay', 'power-schedule-manager' )
			: (
				array() === $rows || $has_waiting_draw
					? __( 'Đang chờ kết quả', 'power-schedule-manager' )
					: __( 'Đã công bố', 'power-schedule-manager' )
			);

		try {
			if ( $payload_error ) {
				$content = self::render_failure();
			} elseif ( array() === $rows ) {
				$content = $is_traditional
					? self::render_traditional_empty( $preset )
					: (
						'dientoan' === $preset
							? self::render_dientoan_empty_grid()
							: self::render_empty( $preset )
					);
			} elseif ( $is_traditional ) {
				$content = self::render_traditional( $rows, $preset );
			} elseif ( in_array( $preset, array( 'keno', 'keno_history' ), true ) ) {
				$content = self::render_keno( $rows, $preset );
			} elseif (
				in_array(
					$preset,
					array(
						'dientoan',
						'dientoan123',
						'dientoan6x36',
						'thantai',
						'max3d',
						'max3dplus',
						'max3dpro',
					),
					true
				)
			) {
				$content = self::render_dientoan( $rows, $preset );
			} else {
				$content = self::render_vietlott( $rows, $preset );
			}
		} catch ( Throwable $error ) {
			Power_Schedule_Manager_Logger::error(
				'lottery_renderer_failed',
				$error,
				array( 'preset' => $preset )
			);
			$content = self::render_failure();
			$rows = array();
		}

		$empty_wrapper = ! $is_traditional
			&& array() === $rows
			&& 'dientoan' !== $preset;
		$anchor = 'ket-qua-' . sanitize_html_class( $preset );
		$html = '<section id="' . esc_attr( $anchor ) . '" class="psm-lottery psm-lottery--'
			. esc_attr( $preset ) . ( $is_updating ? ' is-updating' : '' ) . '" '
			. ( $is_traditional
				? 'aria-label="' . esc_attr( $title ) . '"'
				: 'aria-labelledby="' . esc_attr( $id ) . '"' ) . '>';
		if ( $empty_wrapper ) {
			$html .= '<div class="psm-lottery-empty-card"><header class="psm-lottery__masthead">'
				. '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $title )
				. '</h2></header>';
		}
		$html .= $content;
		if ( $empty_wrapper ) {
			$html .= '</div>';
		}

		return $html . '</section>';
	}

	/**
	 * Render previous draws from every product in one responsive table.
	 *
	 * @param array<int,array<string,string>> $rows Stored draw rows.
	 */
	public static function render_history_overview(
		array $rows,
		string $title
	): string {
		if ( array() === $rows ) {
			return '<div class="psm-lottery-history-overview psm-lottery--empty">'
				. esc_html__( 'Các kỳ trước đang được cập nhật.', 'power-schedule-manager' )
				. '</div>';
		}
		usort(
			$rows,
			static fn ( array $a, array $b ): int =>
				strcmp(
					(string) ( $b['draw_date'] ?? '' )
						. (string) ( $b['provider_draw_id'] ?? '' ),
					(string) ( $a['draw_date'] ?? '' )
						. (string) ( $a['provider_draw_id'] ?? '' )
				)
		);
		$html = '<section class="psm-lottery-history-overview"><header><h3>'
			. esc_html( $title ) . '</h3><p>'
			. esc_html__(
				'Hiển thị tối thiểu 10 kỳ đã lưu, tối ưu cho cả máy tính và điện thoại.',
				'power-schedule-manager'
			)
			. '</p></header><div class="psm-lottery-history-overview__list">';
		foreach ( $rows as $row ) {
			$game = sanitize_key( (string) ( $row['game_type'] ?? '' ) );
			$product_label = 'traditional' === $game
				? self::region_title(
					sanitize_key( (string) ( $row['region'] ?? '' ) )
				) . (
					'' !== trim( (string) ( $row['province_name'] ?? '' ) )
						? ' · ' . (string) $row['province_name']
						: ''
				)
				: self::game_label( $game );
			$results = self::decode_results( $row );
			$numbers = self::primary_numbers( $results );
			if ( count( $numbers ) < 2 ) {
				$collected = array();
				foreach ( $results as $key => $value ) {
					if (
						in_array(
							sanitize_key( (string) $key ),
							array(
								'prizes',
								'prize_table',
								'giai_thuong',
								'jackpot_value',
							),
							true
						)
					) {
						continue;
					}
					$collected = array_merge(
						$collected,
						self::ball_numbers( $value )
					);
				}
				$numbers = array_values( array_unique( $collected ) );
			}
			if ( array() === $numbers ) {
				continue;
			}
			$draw_id = trim( (string) ( $row['provider_draw_id'] ?? '' ) );
			$html .= '<article class="psm-lottery-history-card psm-lottery-history-card--'
				. esc_attr( $game ) . '"><header><div><strong>'
				. esc_html( $product_label ) . '</strong><time datetime="'
				. esc_attr( (string) $row['draw_date'] ) . '">'
				. esc_html(
					self::format_full_date( (string) $row['draw_date'] )
				)
				. '</time></div>'
				. ( '' !== $draw_id
					? '<b>' . esc_html( '#' . $draw_id ) . '</b>'
					: '' )
				. '</header><div class="psm-lottery-history-overview__numbers">';
			foreach ( array_slice( $numbers, 0, 20 ) as $number ) {
				$html .= '<span>' . esc_html( $number ) . '</span>';
			}
			$html .= '</div>';
			if ( 'keno' === $game ) {
				$even = count(
					array_filter(
						$numbers,
						static fn ( string $number ): bool =>
							0 === (int) $number % 2
					)
				);
				$large = count(
					array_filter(
						$numbers,
						static fn ( string $number ): bool =>
							(int) $number > 40
					)
				);
				$html .= '<dl class="psm-lottery-history-card__stats">'
					. '<div class="is-even"><dt>Chẵn</dt><dd>'
					. esc_html( (string) $even )
					. '</dd></div><div class="is-odd"><dt>Lẻ</dt><dd>'
					. esc_html( (string) ( count( $numbers ) - $even ) )
					. '</dd></div><div class="is-large"><dt>Lớn</dt><dd>'
					. esc_html( (string) $large )
					. '</dd></div><div class="is-small"><dt>Nhỏ</dt><dd>'
					. esc_html( (string) ( count( $numbers ) - $large ) )
					. '</dd></div></dl>';
			}
			$html .= '</article>';
		}

		return $html . '</div></section>';
	}

	/**
	 * Render a seven-day northern special-prize statistics table.
	 *
	 * @param array<int,array<string,string>> $rows Special-prize rows.
	 */
	public static function render_special_week(
		array $rows,
		string $title
	): string {
		$html = '<section class="psm-lottery-special-week"><header><div>'
			. '<span aria-hidden="true">✦</span><div><p>'
			. esc_html__( 'Thống kê miền Bắc', 'power-schedule-manager' )
			. '</p><h3>' . esc_html( $title )
			. '</h3></div></div><small>'
			. esc_html__(
				'Theo dõi giải đặc biệt, hai số cuối và tổng theo ngày.',
				'power-schedule-manager'
			)
			. '</small></header>';
		if ( array() === $rows ) {
			return $html . '<div class="psm-lottery-special-week__empty">'
				. esc_html__(
					'Chưa đủ dữ liệu giải đặc biệt để lập bảng tuần.',
					'power-schedule-manager'
				)
				. '</div></section>';
		}
		$html .= '<table><thead><tr><th scope="col">'
			. esc_html__( 'Thứ', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Ngày quay', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Giải đặc biệt', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Hai số cuối', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Tổng', 'power-schedule-manager' )
			. '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$date = DateTimeImmutable::createFromFormat(
				'Y-m-d',
				(string) ( $row['draw_date'] ?? '' )
			);
			$weekday = $date instanceof DateTimeImmutable
				? match ( (int) $date->format( 'N' ) ) {
					1 => __( 'Thứ Hai', 'power-schedule-manager' ),
					2 => __( 'Thứ Ba', 'power-schedule-manager' ),
					3 => __( 'Thứ Tư', 'power-schedule-manager' ),
					4 => __( 'Thứ Năm', 'power-schedule-manager' ),
					5 => __( 'Thứ Sáu', 'power-schedule-manager' ),
					6 => __( 'Thứ Bảy', 'power-schedule-manager' ),
					default => __( 'Chủ Nhật', 'power-schedule-manager' ),
				}
				: '—';
			$html .= '<tr><th scope="row">' . esc_html( $weekday )
				. '</th><td data-label="'
				. esc_attr__( 'Ngày quay', 'power-schedule-manager' ) . '">'
				. esc_html(
					self::format_date(
						(string) ( $row['draw_date'] ?? '' )
					)
				)
				. '</td><td class="psm-lottery-special-week__number" data-label="'
				. esc_attr__( 'Giải đặc biệt', 'power-schedule-manager' )
				. '"><strong>'
				. esc_html( (string) ( $row['special_prize'] ?? '' ) )
				. '</strong></td><td data-label="'
				. esc_attr__( 'Hai số cuối', 'power-schedule-manager' )
				. '"><b>'
				. esc_html( (string) ( $row['last_two'] ?? '' ) )
				. '</b></td><td data-label="'
				. esc_attr__( 'Tổng', 'power-schedule-manager' ) . '">'
				. esc_html( (string) ( $row['total'] ?? '' ) )
				. '</td></tr>';
		}

		return $html . '</tbody></table></section>';
	}

	private static function render_traditional( array $rows, string $region ): string {
		$rows = self::prepare_traditional_rows( $rows, $region );
		$groups = array();
		foreach ( $rows as $row ) {
			$groups[ $row['draw_date'] ][] = $row;
		}
		$html = '';
		foreach ( $groups as $date => $date_rows ) {
			$station_count = max( 1, count( $date_rows ) );
			$table_min_width = 92 + ( $station_count * 180 );
			$live = count(
				array_filter(
					$date_rows,
					static fn ( array $row ): bool =>
						self::draw_is_live( $row, $region )
				)
			) > 0;
			$html .= '<article class="psm-lottery-board psm-lottery-board--'
				. esc_attr( $region )
				. ( $live ? ' is-live' : '' )
				. '"><header><h3><span aria-hidden="true">🏆</span> '
				. esc_html( self::region_title( $region ) )
				. '</h3><div class="psm-lottery-board__meta"><time datetime="' . esc_attr( $date ) . '">'
				. esc_html( self::format_full_date( $date ) )
				. '</time><span class="psm-lottery-board__status">'
				. esc_html(
					$live
						? __( 'Đang cập nhật', 'power-schedule-manager' )
						: __( 'Đã có kết quả', 'power-schedule-manager' )
				)
				. '</span></div></header><p class="psm-lottery-scroll-hint">'
				. esc_html__( 'Vuốt ngang để xem đầy đủ các tỉnh', 'power-schedule-manager' )
				. '</p><div class="psm-lottery-table-wrap" tabindex="0" role="region" aria-label="'
				. esc_attr__( 'Bảng kết quả xổ số có thể cuộn ngang', 'power-schedule-manager' )
				. '" style="--psm-lottery-table-min-width:'
				. esc_attr( (string) $table_min_width )
				. 'px"><table><caption class="screen-reader-text">'
				. esc_html( self::region_title( $region ) . ' - ' . self::format_full_date( $date ) )
				. '</caption>'
				. '<thead><tr><th scope="col">Giải</th>';

			foreach ( $date_rows as $row ) {
				$html .= '<th scope="col">'
					. esc_html( $row['province_name'] ?: 'Kết quả' )
					. '</th>';
			}
			$html .= '</tr></thead><tbody>';
			$decoded = array_map( array( self::class, 'decode_results' ), $date_rows );
			$keys = self::expected_prize_keys( $region );
			foreach ( $keys as $key ) {
				$html .= '<tr><th scope="row">'
					. esc_html( self::prize_label( $key ) )
					. '</th>';
				foreach ( $decoded as $index => $results ) {
					$numbers = self::numbers(
						self::prize_value( $results, $key )
					);
					$special = self::is_special( $key );
					$expected = self::expected_prize_count( $region, $key );
					$row_live = self::draw_is_live(
						$date_rows[ $index ],
						$region
					);
					$html .= '<td data-label="'
						. esc_attr(
							$date_rows[ $index ]['province_name']
								?: __( 'Kết quả', 'power-schedule-manager' )
						)
						. '"'
						. ( $special ? ' class="is-special"' : '' ) . '>'
						. self::number_lines(
							$numbers,
							$row_live,
							$expected
						)
						. '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div></article>';
			break;
		}

		return $html;
	}

	/**
	 * Keep one best row per station on the newest date.
	 *
	 * Aggregate and detail endpoints can contain the same northern draw with
	 * different provider IDs. The most complete row wins.
	 *
	 * @param array<int,array<string,string>> $rows Stored rows.
	 * @return array<int,array<string,string>>
	 */
	private static function prepare_traditional_rows(
		array $rows,
		string $region
	): array {
		if ( array() === $rows ) {
			return array();
		}
		$newest_date = max(
			array_map(
				static fn ( array $row ): string =>
					(string) ( $row['draw_date'] ?? '' ),
				$rows
			)
		);
		$stations = array();
		foreach ( $rows as $row ) {
			if ( $newest_date !== (string) ( $row['draw_date'] ?? '' ) ) {
				continue;
			}
			$station_key = sanitize_key(
				(string) (
					$row['province_code']
					?? $row['province_name']
					?? $region
				)
			);
			if ( '' === $station_key || 'north' === $region ) {
				$station_key = $region;
			}
			$score = self::traditional_row_score( $row, $region );
			if (
				! isset( $stations[ $station_key ] )
				|| $score > $stations[ $station_key ]['score']
				|| (
					$score === $stations[ $station_key ]['score']
					&& (int) ( $row['id'] ?? 0 )
						> (int) ( $stations[ $station_key ]['row']['id'] ?? 0 )
				)
			) {
				$stations[ $station_key ] = array(
					'row'   => $row,
					'score' => $score,
				);
			}
		}
		$prepared = array_column( array_values( $stations ), 'row' );
		usort(
			$prepared,
			static fn ( array $a, array $b ): int =>
				(int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 )
		);

		return $prepared;
	}

	private static function traditional_row_score(
		array $row,
		string $region
	): int {
		$results = self::decode_results( $row );
		$score = 0;
		foreach ( self::expected_prize_keys( $region ) as $key ) {
			$score += count(
				self::numbers( self::prize_value( $results, $key ) )
			);
		}

		return $score;
	}

	private static function render_traditional_empty( string $region ): string {
		$date = wp_date(
			'Y-m-d',
			null,
			new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
		);
		$html = '<article class="psm-lottery-board psm-lottery-board--'
			. esc_attr( $region )
			. ' is-live"><header><h3><span aria-hidden="true">🏆</span> '
			. esc_html( self::region_title( $region ) )
			. '</h3><div class="psm-lottery-board__meta"><time datetime="'
			. esc_attr( $date ) . '">'
			. esc_html( self::format_full_date( $date ) )
			. '</time><span class="psm-lottery-board__status">'
			. esc_html__( 'Đang cập nhật', 'power-schedule-manager' )
			. '</span></div></header><div class="psm-lottery-table-wrap">'
			. '<table><thead><tr><th scope="col">Giải</th><th scope="col">'
			. esc_html__( 'Kết quả', 'power-schedule-manager' )
			. '</th></tr></thead><tbody>';
		foreach ( self::expected_prize_keys( $region ) as $key ) {
			$html .= '<tr><th scope="row">'
				. esc_html( self::prize_label( $key ) )
				. '</th><td'
				. ( self::is_special( $key ) ? ' class="is-special"' : '' )
				. '>'
				. self::number_lines(
					array(),
					true,
					self::expected_prize_count( $region, $key )
				)
				. '</td></tr>';
		}

		return $html . '</tbody></table></div></article>';
	}

	private static function render_vietlott( array $rows, string $preset ): string {
		$html = '<div class="psm-lottery__draw-list">';
		foreach ( $rows as $row ) {
			$results = self::decode_results( $row );
			$numbers = self::primary_numbers( $results );
			$live = self::draw_is_live( $row, $preset );
			$waiting = ! $live && ! self::draw_has_results( $row );
			$expected = 'power655' === $preset ? 7 : 6;
			$html .= '<article class="psm-lottery-card psm-lottery-card--'
				. esc_attr( $preset )
				. ( $live ? ' is-live' : '' )
				. '"><header><div><span>'
				. esc_html( self::format_full_date( $row['draw_date'] ) )
				. '</span><h3>'
				. esc_html( 'Xổ số ' . self::game_label( $preset ) )
				. '</h3></div><div class="psm-lottery-card__header-meta">';
			if ( '' !== $row['provider_draw_id'] ) {
				$html .= '<strong>#' . esc_html( $row['provider_draw_id'] ) . '</strong>';
			}
			$html .= '<span class="psm-lottery-card__state">'
				. esc_html(
					$live
						? __( 'Đang quay', 'power-schedule-manager' )
						: (
							$waiting
								? __( 'Đang chờ kết quả', 'power-schedule-manager' )
								: __( 'Đã công bố', 'power-schedule-manager' )
						)
				)
				. '</span></div></header><div class="psm-lottery-balls" aria-label="Dãy số trúng thưởng">';
			foreach ( array_slice( $numbers, 0, $expected ) as $index => $number ) {
				$html .= '<span'
					. ( $index === $expected - 1 && 'power655' === $preset
						? ' class="is-power"'
						: '' )
					. '>' . esc_html( $number ) . '</span>';
			}
			if ( $live && count( $numbers ) < $expected ) {
				for ( $index = count( $numbers ); $index < $expected; ++$index ) {
					$html .= '<span class="psm-lottery-loader'
						. ( $index === $expected - 1 && 'power655' === $preset
							? ' is-power'
							: '' )
						. '" aria-label="Đang quay"></span>';
				}
			}
			$html .= '</div>' . self::metadata_grid( $results, $preset )
				. '<footer class="psm-lottery-card__source">'
				. esc_html(
					sprintf(
						/* translators: %s draw number. */
						__( 'Kỳ quay #%s · Kết quả từ Vietlott', 'power-schedule-manager' ),
						$row['provider_draw_id'] ?: $row['id']
					)
				)
				. '</footer></article>';
		}
		$html .= '</div>';

		return $html;
	}

	private static function render_keno( array $rows, string $preset ): string {
		if ( 'keno' === $preset ) {
			return self::render_keno_featured( $rows );
		}
		/*
		 * The focused Keno page already renders the newest draw as the large
		 * featured card. The history shortcode therefore starts with the next
		 * draw so the same round is not printed twice on one page.
		 */
		if ( 'keno_history' === $preset ) {
			$rows = array_slice( $rows, 1, 10 );
		}

		$html = '<div class="psm-lottery-table-wrap psm-keno-history"><table class="psm-keno-table">'
			. '<caption>' . esc_html__( '10 kỳ Keno gần nhất', 'power-schedule-manager' ) . '</caption>'
			. '<thead><tr><th scope="col">' . esc_html__( 'Kỳ quay', 'power-schedule-manager' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Kết quả', 'power-schedule-manager' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Thống kê', 'power-schedule-manager' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $index => $row ) {
			$numbers = self::keno_numbers( self::decode_results( $row ) );
			$even = count( array_filter( $numbers, static fn ( string $n ): bool => 0 === (int) $n % 2 ) );
			$large = count( array_filter( $numbers, static fn ( string $n ): bool => (int) $n > 40 ) );
			$html .= '<tr' . ( 0 === $index ? ' class="is-latest"' : '' ) . '><th scope="row"><strong>#'
				. esc_html( $row['provider_draw_id'] ?: (string) $row['id'] )
				. '</strong><time datetime="' . esc_attr( $row['draw_date'] ) . '">'
				. esc_html( self::format_full_date( $row['draw_date'] ) )
				. '</time></th><td data-label="' . esc_attr__( 'Kết quả', 'power-schedule-manager' )
				. '"><div class="psm-keno-table__balls">';
			foreach ( array_slice( $numbers, 0, 20 ) as $number ) {
				$html .= '<span>' . esc_html( str_pad( $number, 2, '0', STR_PAD_LEFT ) ) . '</span>';
			}
			$html .= '</div></td><td data-label="' . esc_attr__( 'Thống kê', 'power-schedule-manager' )
				. '"><div class="psm-keno-table__stats"><span class="is-even">Chẵn <b>' . esc_html( (string) $even )
				. '</b></span><span class="is-odd">Lẻ <b>' . esc_html( (string) ( count( $numbers ) - $even ) )
				. '</b></span><span class="is-large">Lớn <b>' . esc_html( (string) $large )
				. '</b></span><span class="is-small">Nhỏ <b>' . esc_html( (string) ( count( $numbers ) - $large ) )
				. '</b></span></div></td></tr>';
		}
		$html .= '</tbody></table></div>';

		return $html;
	}

	/**
	 * Render the latest Keno draw once, separately from the history table.
	 *
	 * @param array<int,array<string,string>> $rows Stored Keno rows.
	 */
	private static function render_keno_featured( array $rows ): string {
		$row = $rows[0] ?? array();
		if ( array() === $row ) {
			return self::render_empty( 'keno' );
		}

		$numbers = self::keno_numbers( self::decode_results( $row ) );
		if ( 20 !== count( $numbers ) ) {
			return self::render_empty( 'keno' );
		}

		$even = count(
			array_filter(
				$numbers,
				static fn ( string $number ): bool => 0 === (int) $number % 2
			)
		);
		$large = count(
			array_filter(
				$numbers,
				static fn ( string $number ): bool => (int) $number > 40
			)
		);
		$draw_id = trim(
			(string) ( $row['provider_draw_id'] ?? $row['id'] ?? '' )
		);
		$draw_date = self::format_date( (string) ( $row['draw_date'] ?? '' ) );
		$html = '<article class="psm-keno-featured"><header><h2>'
			. esc_html__(
				'KẾT QUẢ XỔ SỐ VIETLOTT KENO KỲ QUAY',
				'power-schedule-manager'
			)
			. ' <span class="psm-keno-featured__draw-id">#'
			. esc_html( $draw_id )
			. '</span> '
			. esc_html__( 'NGÀY', 'power-schedule-manager' )
			. ' ' . esc_html( $draw_date )
			. '</h2></header><div class="psm-keno-featured__balls">';
		foreach ( $numbers as $number ) {
			$html .= '<span>' . esc_html( $number ) . '</span>';
		}
		$html .= '</div><dl class="psm-keno-featured__stats">'
			. '<div class="is-even"><dt><i aria-hidden="true">C</i>'
			. esc_html__( 'Chẵn', 'power-schedule-manager' )
			. '</dt><dd>' . esc_html( (string) $even ) . '</dd></div>'
			. '<div class="is-large"><dt><i aria-hidden="true">&gt;</i>'
			. esc_html__( 'Lớn', 'power-schedule-manager' )
			. '</dt><dd>' . esc_html( (string) $large ) . '</dd></div>'
			. '<div class="is-odd"><dt><i aria-hidden="true">L</i>'
			. esc_html__( 'Lẻ', 'power-schedule-manager' )
			. '</dt><dd>' . esc_html( (string) ( 20 - $even ) ) . '</dd></div>'
			. '<div class="is-small"><dt><i aria-hidden="true">&lt;</i>'
			. esc_html__( 'Nhỏ', 'power-schedule-manager' )
			. '</dt><dd>' . esc_html( (string) ( 20 - $large ) ) . '</dd></div>'
			. '</dl></article>';

		return $html;
	}

	/**
	 * Normalize the provider's two known Keno shapes.
	 *
	 * Some responses expose twenty two-digit strings, while others expose
	 * each digit as a nested scalar. The generic flattener must not turn
	 * 05,08,14 into 0,5,0,8,1,4.
	 *
	 * @return array<int,string>
	 */
	private static function keno_numbers( array $results ): array {
		$source = $results['winning_numbers']
			?? $results['winningNumbers']
			?? $results['results']
			?? $results;
		if (
			is_scalar( $source )
			&& 1 === preg_match( '/\A\d{40}\z/', trim( (string) $source ) )
		) {
			$numbers = str_split( trim( (string) $source ), 2 );
		} else {
			$numbers = is_array( $source )
				? self::primary_numbers( $source )
				: self::ball_numbers( $source );
		}
		if (
			count( $numbers ) >= 40
			&& 0 === count(
				array_filter(
					$numbers,
					static fn ( string $number ): bool => strlen( $number ) > 1
				)
			)
		) {
			$paired = array();
			foreach ( array_chunk( array_slice( $numbers, 0, 40 ), 2 ) as $digits ) {
				$paired[] = implode( '', $digits );
			}
			$numbers = $paired;
		}

		$numbers = array_values(
			array_filter(
				array_map(
					static fn ( string $number ): string =>
						str_pad( $number, 2, '0', STR_PAD_LEFT ),
					array_slice( $numbers, 0, 20 )
				),
				static fn ( string $number ): bool =>
					(int) $number >= 1 && (int) $number <= 80
			)
		);

		return 20 === count( $numbers ) ? $numbers : array();
	}

	private static function render_dientoan(
		array $rows,
		string $preset
	): string {
		$html = '<div class="psm-lottery__draw-list psm-lottery__draw-list--compact">';
		$seen_anchors = array();
		foreach ( $rows as $row ) {
			$results = self::decode_results( $row );
			$game = sanitize_key( (string) $row['game_type'] );
			$live = self::draw_is_live( $row, $preset );
			$waiting = ! $live && ! self::draw_has_results( $row );
			$article_anchor = '';
			if (
				'dientoan' === $preset
				&& in_array( $game, array( 'dientoan123', 'dientoan6x36', 'thantai' ), true )
				&& ! isset( $seen_anchors[ $game ] )
			) {
				$article_anchor = 'ket-qua-' . $game;
				$seen_anchors[ $game ] = true;
			}
			$html .= '<article' . ( '' !== $article_anchor ? ' id="' . esc_attr( $article_anchor ) . '"' : '' )
				. ' class="psm-lottery-card psm-lottery-card--'
				. esc_attr( $preset )
				. ' psm-lottery-card--' . esc_attr( $game )
				. ( $live ? ' is-live' : '' )
				. '"><header><div><span>'
				. esc_html( self::format_full_date( $row['draw_date'] ) )
				. '</span><h3>'
				. esc_html( 'Xổ số ' . self::game_label( $game ) )
				. '</h3></div><div class="psm-lottery-card__header-meta">';
			if ( '' !== trim( (string) ( $row['provider_draw_id'] ?? '' ) ) ) {
				$html .= '<strong>#'
					. esc_html( (string) $row['provider_draw_id'] )
					. '</strong>';
			}
			$html .= '<span class="psm-lottery-card__state">'
				. esc_html(
					$live
						? __( 'Đang quay', 'power-schedule-manager' )
						: (
							$waiting
								? __( 'Đang chờ kết quả', 'power-schedule-manager' )
								: __( 'Đã công bố', 'power-schedule-manager' )
						)
				)
				. '</span></div></header>'
				. (
					in_array(
						$preset,
						array( 'max3d', 'max3dplus', 'max3dpro' ),
						true
					)
						? self::max3d_table( $results, $preset, $live )
						: self::dientoan_body( $results, $game )
							. self::metadata_grid( $results, $preset )
				)
				. '</article>';
		}
		$html .= '</div>';

		return $html;
	}

	private static function render_dientoan123_table( array $rows ): string {
		$html = '<div class="psm-lottery-table-wrap psm-dientoan123-history"><table><caption>'
			. esc_html__( 'Kết quả Điện toán 123 mới nhất và các kỳ trước', 'power-schedule-manager' )
			. '</caption><thead><tr><th scope="col">' . esc_html__( 'Kỳ quay', 'power-schedule-manager' )
			. '</th><th scope="col">' . esc_html__( 'Bộ 1', 'power-schedule-manager' )
			. '</th><th scope="col">' . esc_html__( 'Bộ 2', 'power-schedule-manager' )
			. '</th><th scope="col">' . esc_html__( 'Bộ 3', 'power-schedule-manager' )
			. '</th><th scope="col">' . esc_html__( 'Trạng thái', 'power-schedule-manager' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $index => $row ) {
			$sets = self::dientoan123_sets( self::decode_results( $row ) );
			$live = self::draw_is_live( $row, 'dientoan123' );
			$waiting = ! $live && ! self::draw_has_results( $row );
			$html .= '<tr' . ( 0 === $index ? ' class="is-latest"' : '' ) . '><th scope="row"><strong>#'
				. esc_html( $row['provider_draw_id'] ?: (string) $row['id'] ) . '</strong><time datetime="'
				. esc_attr( $row['draw_date'] ) . '">' . esc_html( self::format_full_date( $row['draw_date'] ) ) . '</time></th>';
			foreach ( $sets as $set_index => $numbers ) {
				$html .= '<td data-label="' . esc_attr( sprintf( 'Bộ %d', $set_index + 1 ) )
					. '"><strong class="psm-dientoan123-number">' . esc_html( implode( '', $numbers ) ?: '—' ) . '</strong></td>';
			}
			$html .= '<td data-label="' . esc_attr__( 'Trạng thái', 'power-schedule-manager' ) . '"><span class="psm-lottery-card__state">'
				. esc_html( $live ? __( 'Đang quay', 'power-schedule-manager' ) : ( $waiting ? __( 'Đang chờ kết quả', 'power-schedule-manager' ) : __( 'Đã công bố', 'power-schedule-manager' ) ) )
				. '</span></td></tr>';
		}

		return $html . '</tbody></table></div>';
	}

	/**
	 * Render every Max 3D family product with one consistent result table.
	 */
	private static function max3d_table(
		array $results,
		string $preset,
		bool $live
	): string {
		$prizes = $results['prizes']
			?? $results['prize_table']
			?? $results['giai_thuong']
			?? array();
		$prizes = is_array( $prizes )
			? ( array_is_list( $prizes ) ? $prizes : array_values( $prizes ) )
			: array();
		$filtered_prizes = array_values(
			array_filter(
				$prizes,
				static function ( mixed $prize ) use ( $preset ): bool {
					if ( ! is_array( $prize ) ) {
						return false;
					}
					$category = strtolower(
						self::scalar_text(
							$prize['category']
								?? $prize['product']
								?? ''
						)
					);
					if ( 'max3dplus' === $preset ) {
						return str_contains( $category, '+' )
							|| str_contains( $category, 'plus' );
					}
					if (
						'max3d' === $preset
						&& (
							str_contains( $category, '+' )
							|| str_contains( $category, 'plus' )
						)
					) {
						return false;
					}

					return true;
				}
			)
		);
		$html = '<div class="psm-max3d-table psm-lottery-table-wrap"><table>'
			. '<thead><tr><th scope="col">'
			. esc_html__( 'Giải', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Dãy số trúng', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Số lượng giải', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Giá trị giải', 'power-schedule-manager' )
			. '</th></tr></thead><tbody>';
		$shown_prizes = array();
		$shown = 0;

		if ( 'max3dplus' !== $preset ) {
			$expected = 'max3dpro' === $preset
				? array(
					'db' => 2,
					'pdb' => 2,
					'g1' => 4,
					'g2' => 6,
					'g3' => 8,
				)
				: array( 'db' => 2, 'g1' => 4, 'g2' => 6, 'g3' => 8 );
			foreach ( $expected as $key => $expected_count ) {
				$prize = self::find_max3d_prize( $filtered_prizes, $key );
				$numbers = self::numbers(
					self::prize_value( $results, $key )
				);
				$html .= self::max3d_table_row(
					self::prize_label( $key ),
					self::number_lines( $numbers, $live, $expected_count ),
					$prize
				);
				if ( is_array( $prize ) ) {
					$shown_prizes[] = self::max3d_prize_identity( $prize );
				}
				++$shown;
			}
		}

		foreach ( $filtered_prizes as $prize ) {
			if ( ! is_array( $prize ) ) {
				continue;
			}
			$identity = self::max3d_prize_identity( $prize );
			if ( in_array( $identity, $shown_prizes, true ) ) {
				continue;
			}
			$name = self::scalar_text(
				$prize['prizeName']
					?? $prize['prize_name']
					?? $prize['name']
					?? $prize['prizeCode']
					?? $prize['code']
					?? ''
			);
			if ( '' === trim( $name ) ) {
				continue;
			}
			$description = self::scalar_text(
				$prize['description']
					?? $prize['condition']
					?? $prize['matchCondition']
					?? $prize['match_condition']
					?? ''
			);
			$html .= self::max3d_table_row(
				$name,
				'' !== trim( $description )
					? esc_html( $description )
					: '<span class="psm-max3d-table__condition">'
						. esc_html__(
							'Theo cơ cấu giải công bố',
							'power-schedule-manager'
						)
						. '</span>',
				$prize
			);
			++$shown;
		}

		if ( 0 === $shown ) {
			return self::render_empty( $preset );
		}

		return $html . '</tbody></table></div>';
	}

	/**
	 * Build one Max 3D result row.
	 *
	 * @param array<string,mixed>|null $prize Prize metadata.
	 */
	private static function max3d_table_row(
		string $name,
		string $result_html,
		?array $prize
	): string {
		$winners = is_array( $prize )
			? (
				$prize['winnersCount']
					?? $prize['winners_count']
					?? $prize['winnerCount']
					?? $prize['winner_count']
					?? $prize['numberOfWinners']
					?? $prize['number_of_winners']
					?? $prize['numberOfPrizes']
					?? $prize['number_of_prizes']
					?? $prize['totalWinners']
					?? $prize['total_winners']
					?? $prize['winners']
					?? $prize['quantity']
					?? $prize['count']
					?? '—'
			)
			: '—';
		$value = is_array( $prize )
			? (
				$prize['prizeValue']
					?? $prize['prize_value']
					?? $prize['prizeAmount']
					?? $prize['prize_amount']
					?? $prize['amount']
					?? $prize['value']
					?? ''
			)
			: '';

		return '<tr><th scope="row">' . esc_html( $name )
			. '</th><td data-label="'
			. esc_attr__( 'Dãy số trúng', 'power-schedule-manager' )
			. '">' . $result_html . '</td><td data-label="'
			. esc_attr__( 'Số lượng giải', 'power-schedule-manager' )
			. '">' . esc_html( (string) $winners )
			. '</td><td data-label="'
			. esc_attr__( 'Giá trị giải', 'power-schedule-manager' )
			. '"><strong>'
			. esc_html(
				'' === trim( (string) $value )
					? '—'
					: self::format_money( (string) $value )
			)
			. '</strong></td></tr>';
	}

	/**
	 * Find prize metadata belonging to a primary Max 3D result row.
	 *
	 * @param array<int,mixed> $prizes Prize list.
	 * @return array<string,mixed>|null
	 */
	private static function find_max3d_prize(
		array $prizes,
		string $target_key
	): ?array {
		foreach ( $prizes as $prize ) {
			if (
				is_array( $prize )
				&& $target_key === self::max3d_prize_key( $prize )
			) {
				return $prize;
			}
		}

		return null;
	}

	/**
	 * Normalize a Max 3D prize code or name to db/g1/g2/g3.
	 *
	 * @param array<string,mixed> $prize Prize metadata.
	 */
	private static function max3d_prize_key( array $prize ): string {
		$code = strtoupper(
			(string) (
				$prize['prizeCode']
					?? $prize['prize_code']
					?? $prize['code']
					?? ''
			)
		);
		if ( 1 === preg_match( '/(?:^|[_-])(PDB|DB|G[1-8])$/', $code, $match ) ) {
			return match ( $match[1] ) {
				'PDB' => 'pdb',
				'DB' => 'db',
				default => strtolower( $match[1] ),
			};
		}
		$name = (string) (
			$prize['prizeName']
				?? $prize['prize_name']
				?? $prize['name']
				?? ''
		);

		return self::canonical_prize_key( $name );
	}

	/**
	 * Stable identity for avoiding duplicate primary prize rows.
	 *
	 * @param array<string,mixed> $prize Prize metadata.
	 */
	private static function max3d_prize_identity( array $prize ): string {
		return hash(
			'sha256',
			wp_json_encode( $prize ) ?: serialize( $prize )
		);
	}

	private static function dientoan_body(
		array $results,
		string $game
	): string {
		$provider_results = $results;
		if (
			isset( $results['results'] )
			&& is_array( $results['results'] )
		) {
			$results = $results['results'];
		}
		if ( 'dientoan123' === $game ) {
			/*
			 * Keep the full provider tree: some API revisions put G1/G2/G3
			 * beside (rather than inside) an empty `results` wrapper.
			 */
			$sets = self::dientoan123_sets( $provider_results );
			$html = '<div class="psm-dientoan-123">';
			foreach ( $sets as $index => $numbers ) {
				$html .= '<div><small>'
					. esc_html(
						sprintf(
							/* translators: %d result set. */
							__( 'Bộ %d', 'power-schedule-manager' ),
							$index + 1
						)
					)
					. '</small><strong>'
					. esc_html( implode( '', $numbers ) ?: '—' )
					. '</strong></div>';
			}

			return $html . '</div><p class="psm-dientoan-note">'
				. esc_html__(
					'Cơ cấu giải: Trùng khớp 1, 2 hoặc 3 bộ số',
					'power-schedule-manager'
				)
				. '</p>';
		}
		if ( 'dientoan6x36' === $game ) {
			$numbers = self::numbers( $results['g1'] ?? array() );
			if ( array() === $numbers ) {
				$numbers = self::primary_numbers( $results );
			}
			$html = '<div class="psm-dientoan-6x36">';
			foreach ( array_slice( $numbers, 0, 6 ) as $number ) {
				$html .= '<strong>' . esc_html( $number ) . '</strong>';
			}

			return $html . '</div><p class="psm-dientoan-note">'
				. esc_html__(
					'Cơ cấu giải: Chọn 6 cặp số từ 01 đến 36',
					'power-schedule-manager'
				)
				. '</p>';
		}
		if ( 'thantai' === $game ) {
			$digits = self::numbers(
				$results['db'] ?? $results['g1'] ?? array()
			);
			if ( array() === $digits ) {
				$digits = self::primary_numbers( $results );
			}
			$number = implode( '', array_slice( $digits, 0, 1 ) );
			$html = '<div class="psm-thantai"><div>';
			foreach ( str_split( $number ) as $digit ) {
				$html .= '<strong>' . esc_html( $digit ) . '</strong>';
			}

			return $html . '</div></div><p class="psm-dientoan-note">'
				. esc_html__(
					'Cơ cấu giải: Trùng khớp 4 chữ số may mắn',
					'power-schedule-manager'
				)
				. '</p>';
		}

		return self::result_rows( $results );
	}

	private static function dientoan123_sets( array $results ): array {
		$source = $results;
		foreach ( array( 'results', 'result', 'ket_qua', 'data' ) as $wrapper ) {
			if ( isset( $results[ $wrapper ] ) && is_array( $results[ $wrapper ] ) ) {
				$results = $results[ $wrapper ];
				break;
			}
		}

		/* XoSoAPI returns DT123 as prize rows (G1/G2/G3). */
		if ( array_is_list( $results ) ) {
			$grouped = array();
			foreach ( $results as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$code = self::dientoan_key(
					(string) ( $item['prizeCode'] ?? $item['prize_code'] ?? $item['code'] ?? $item['prize'] ?? $item['name'] ?? '' )
				);
				$position = match ( $code ) {
					'g1', 'bo1', 'set1', 'first' => 'g1',
					'g2', 'bo2', 'set2', 'second' => 'g2',
					'g3', 'bo3', 'set3', 'third' => 'g3',
					default => '',
				};
				if ( '' !== $position ) {
					$grouped[ $position ] = $item['value']
						?? $item['number']
						?? $item['result']
						?? $item['values']
						?? array();
				}
			}
			if ( array() !== $grouped ) {
				$results = $grouped;
			}
		}
		/* JSON object keys are case-sensitive; providers use both G1 and g1. */
		if ( ! array_is_list( $results ) ) {
			$canonical = array();
			foreach ( $results as $key => $value ) {
				$canonical[ self::dientoan_key( (string) $key ) ] = $value;
			}
			$results = $canonical;
		}
		/* API revisions may keep G1/G2/G3 beside an empty results wrapper. */
		if ( ! self::has_dientoan123_keys( $results ) ) {
			$nested = self::find_dientoan123_result_map( $source );
			if ( array() !== $nested ) {
				$results = $nested;
			}
		}

		$aliases = array(
			array( 'g1', 'bo1', 'set1', 'first' ),
			array( 'g2', 'bo2', 'set2', 'second' ),
			array( 'g3', 'bo3', 'set3', 'third' ),
		);
		$sets = array(
			array(),
			array(),
			array(),
		);
		foreach ( $aliases as $index => $keys ) {
			foreach ( $keys as $key ) {
				if ( array_key_exists( $key, $results ) ) {
					$sets[ $index ] = self::numbers( $results[ $key ] );
					break;
				}
			}
		}
		if ( 0 === array_sum( array_map( 'count', $sets ) ) ) {
			$primary = self::primary_numbers( $results );
			$sets = array(
				array_slice( $primary, 0, 1 ),
				array_slice( $primary, 1, 1 ),
				array_slice( $primary, 2, 1 ),
			);
		}

		return $sets;
	}

	/** @param array<int|string,mixed> $results */
	private static function has_dientoan123_keys( array $results ): bool {
		$keys = array_map(
			static fn ( string|int $key ): string => self::dientoan_key( (string) $key ),
			array_keys( $results )
		);
		$groups = array(
			array( 'g1', 'bo1', 'set1', 'first' ),
			array( 'g2', 'bo2', 'set2', 'second' ),
			array( 'g3', 'bo3', 'set3', 'third' ),
		);

		return 3 === count(
			array_filter(
				$groups,
				static fn ( array $aliases ): bool =>
					array() !== array_intersect( $aliases, $keys )
			)
		);
	}

	/**
	 * @param array<int|string,mixed> $node Provider result tree.
	 * @return array<string,mixed>
	 */
	private static function find_dientoan123_result_map(
		array $node,
		int $depth = 0
	): array {
		if ( $depth > 6 ) {
			return array();
		}
		$canonical = array();
		foreach ( $node as $key => $value ) {
			$canonical[ self::dientoan_key( (string) $key ) ] = $value;
		}
		if ( self::has_dientoan123_keys( $canonical ) ) {
			return $canonical;
		}
		foreach ( $node as $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			$found = self::find_dientoan123_result_map( $value, $depth + 1 );
			if ( array() !== $found ) {
				return $found;
			}
		}

		return array();
	}

	private static function dientoan_key( string $key ): string {
		$key = strtolower( remove_accents( trim( $key ) ) );
		$clean = preg_replace( '/[^a-z0-9]+/', '', $key );

		return is_string( $clean ) ? $clean : '';
	}

	private static function result_rows( array $results ): string {
		$html = '<dl class="psm-lottery-result-rows">';
		foreach ( array_slice( $results, 0, 16, true ) as $key => $value ) {
			if (
				in_array(
					sanitize_key( (string) $key ),
					array(
						'prizes',
						'prize_table',
						'giai_thuong',
						'jackpot_value',
					),
					true
				)
			) {
				continue;
			}
			$numbers = self::numbers( $value );
			if ( array() === $numbers ) {
				continue;
			}
			$html .= '<div><dt>' . esc_html( self::prize_label( (string) $key ) )
				. '</dt><dd>' . esc_html( implode( ' · ', $numbers ) )
				. '</dd></div>';
		}

		return $html . '</dl>';
	}

	private static function metadata_grid(
		array $results,
		string $preset = ''
	): string {
		$html = '';
		$prizes = $results['prizes']
			?? $results['prize_table']
			?? $results['giai_thuong']
			?? array();
		$jackpots = array();
		if ( is_array( $prizes ) ) {
			foreach ( $prizes as $prize ) {
				if ( ! is_array( $prize ) ) {
					continue;
				}
				$code = strtoupper(
					self::scalar_text(
						$prize['prizeCode']
							?? $prize['prize_code']
							?? ''
					)
				);
				if ( ! str_contains( $code, 'JACKPOT' ) ) {
					continue;
				}
				$label = self::scalar_text(
					$prize['prizeName']
						?? $prize['prize_name']
						?? 'Jackpot'
				);
				$value = $prize['prizeValue']
					?? $prize['prize_value']
					?? null;
				if ( is_scalar( $value ) ) {
					$jackpots[ $label ] = (string) $value;
				}
			}
		}
		$jackpot = $results['jackpot_value']
			?? $results['gia_tri_jackpot']
			?? null;
		if (
			array() === $jackpots
			&& is_scalar( $jackpot )
			&& '' !== trim( (string) $jackpot )
		) {
			$jackpots[ __( 'Jackpot', 'power-schedule-manager' ) ] =
				(string) $jackpot;
		}
		if ( array() !== $jackpots ) {
			$html .= '<dl class="psm-lottery-jackpots">';
			foreach ( $jackpots as $label => $value ) {
				$html .= '<div><dt>'
					. esc_html(
						sprintf(
							/* translators: %s prize label. */
							__( 'Giá trị %s', 'power-schedule-manager' ),
							$label
						)
					)
					. '</dt><dd>'
					. esc_html( self::format_money( $value ) )
					. '</dd></div>';
			}
			$html .= '</dl>';
		}
		if ( is_array( $prizes ) && array() !== $prizes ) {
			$html .= self::prize_table( $prizes, $preset );
		}

		return $html;
	}

	/**
	 * Render structured Vietlott prize metadata.
	 *
	 * @param array<int|string,mixed> $prizes Prize rows.
	 */
	private static function prize_table(
		array $prizes,
		string $preset = ''
	): string {
		$rows = array_is_list( $prizes ) ? $prizes : array_values( $prizes );
		$show_match = in_array(
			$preset,
			array( 'mega645', 'power655' ),
			true
		);
		$html = '<div class="psm-lottery-prize-table"><table><thead><tr>'
			. '<th scope="col">'
			. esc_html__( 'Giải thưởng', 'power-schedule-manager' )
			. '</th>'
			. (
				$show_match
					? '<th scope="col">'
						. esc_html__(
							'Trùng khớp',
							'power-schedule-manager'
						)
						. '</th>'
					: ''
			)
			. '<th scope="col">'
			. esc_html__( 'Số lượng giải', 'power-schedule-manager' )
			. '</th><th scope="col">'
			. esc_html__( 'Giá trị giải', 'power-schedule-manager' )
			. '</th></tr></thead><tbody>';
		$shown = 0;
		foreach ( $rows as $prize ) {
			if ( ! is_array( $prize ) ) {
				continue;
			}
			$category = strtolower(
				self::scalar_text(
					$prize['category']
					?? $prize['product']
					?? ''
				)
			);
			if (
				in_array( $preset, array( 'max3d', 'max3dplus' ), true )
				&& '' !== $category
				&& (
					(
						'max3dplus' === $preset
						&& ! str_contains( $category, '+' )
					)
					|| (
						'max3d' === $preset
						&& str_contains( $category, '+' )
					)
				)
			) {
				continue;
			}
			$name = self::scalar_text( $prize['prizeName']
				?? $prize['prize_name']
				?? $prize['name']
				?? $prize['prizeCode']
				?? $prize['code']
				?? '' );
			$winners = self::scalar_text( $prize['winnersCount']
				?? $prize['winners_count']
				?? $prize['winnerCount']
				?? $prize['winner_count']
				?? $prize['numberOfWinners']
				?? $prize['number_of_winners']
				?? $prize['numberOfPrizes']
				?? $prize['number_of_prizes']
				?? $prize['totalWinners']
				?? $prize['total_winners']
				?? $prize['winners']
				?? $prize['quantity']
				?? $prize['count']
				?? '—', '—' );
			$value = self::scalar_text( $prize['prizeValue']
				?? $prize['prize_value']
				?? $prize['prizeAmount']
				?? $prize['prize_amount']
				?? $prize['amount']
				?? $prize['value']
				?? '' );
			if ( '' === trim( (string) $name ) ) {
				continue;
			}
			$html .= '<tr><th scope="row">'
				. esc_html( (string) $name )
				. '</th>'
				. (
					$show_match
					? '<td class="psm-lottery-prize-table__match" data-label="'
						. esc_attr__( 'Trùng khớp', 'power-schedule-manager' )
						. '">'
						. self::match_pattern(
							(string) (
								$prize['prizeCode']
									?? $prize['prize_code']
									?? ''
							),
							$preset,
							(string) $name
						)
							. '</td>'
						: ''
				)
				. '<td data-label="'
				. esc_attr__( 'Số lượng giải', 'power-schedule-manager' )
				. '">'
				. esc_html( (string) $winners )
				. '</td><td data-label="'
				. esc_attr__( 'Giá trị giải', 'power-schedule-manager' )
				. '"><strong>'
				. esc_html(
					'' === trim( (string) $value )
						? '—'
						: self::format_money( (string) $value )
				)
				. '</strong></td></tr>';
			++$shown;
		}

		return 0 === $shown ? '' : $html . '</tbody></table></div>';
	}

	private static function match_pattern(
		string $prize_code,
		string $preset,
		string $prize_name = ''
	): string {
		$identity = strtolower(
			remove_accents( $prize_code . ' ' . $prize_name )
		);
		$identity = preg_replace( '/[^a-z0-9]+/', ' ', $identity ) ?? '';
		$is_jackpot_two = str_contains( $identity, 'jackpot 2' )
			|| str_contains( $identity, 'jackpot2' );
		$is_jackpot = str_contains( $identity, 'jackpot' );
		$filled = match ( true ) {
			$is_jackpot_two => 5,
			$is_jackpot => 6,
			1 === preg_match(
				'/(?:^| )(?:g1|prize1|giai nhat)(?: |$)/',
				$identity
			) => 5,
			1 === preg_match(
				'/(?:^| )(?:g2|prize2|giai nhi)(?: |$)/',
				$identity
			) => 4,
			1 === preg_match(
				'/(?:^| )(?:g3|prize3|giai ba)(?: |$)/',
				$identity
			) => 3,
			default => 0,
		};
		$bonus_filled = 'power655' === $preset && $is_jackpot_two;
		$label = sprintf(
			/* translators: %d number of matching main balls. */
			_n(
				'Trùng %d số chính',
				'Trùng %d số chính',
				$filled,
				'power-schedule-manager'
			),
			$filled
		);
		if ( 'power655' === $preset ) {
			$label .= $bonus_filled
				? __( ' và số đặc biệt', 'power-schedule-manager' )
				: __( ', không cần số đặc biệt', 'power-schedule-manager' );
		}

		$html = '<span class="screen-reader-text">'
			. esc_html( $label )
			. '</span><span class="psm-match-pattern" aria-hidden="true">';
		for ( $index = 0; $index < 6; ++$index ) {
			$html .= '<i class="'
				. ( $index < $filled ? 'is-filled' : 'is-empty' )
				. '"></i>';
		}
		if ( 'power655' === $preset ) {
			$html .= '<b></b><i class="is-bonus '
				. ( $bonus_filled ? 'is-filled' : 'is-empty' )
				. '"></i>';
		}

		return $html . '</span>';
	}

	private static function format_money( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '—';
		}
		$value = trim( sanitize_text_field( (string) $value ) );
		$digits = preg_replace( '/[^0-9-]/', '', $value ) ?? '';
		if ( '' === $digits || '-' === $digits ) {
			return $value;
		}

		return number_format_i18n( (int) $digits ) . ' đ';
	}

	private static function scalar_text(
		mixed $value,
		string $fallback = ''
	): string {
		return is_scalar( $value )
			? sanitize_text_field( (string) $value )
			: $fallback;
	}

	private static function render_empty( string $preset ): string {
		$html = '<div class="psm-lottery-empty" role="status">'
			. '<span class="psm-lottery-empty__pulse" aria-hidden="true"><i></i></span>'
			. '<strong>'
			. esc_html__(
				'Đang chờ kết quả',
				'power-schedule-manager'
			)
			. '</strong></div>';

		return $html;
	}

	/**
	 * Preserve the three-product Điện toán layout while data is pending.
	 */
	private static function render_dientoan_empty_grid(): string {
		$products = array(
			'dientoan123' => __( 'Điện toán 123', 'power-schedule-manager' ),
			'dientoan6x36' => __( 'Điện toán 6x36', 'power-schedule-manager' ),
			'thantai' => __( 'Thần Tài 4', 'power-schedule-manager' ),
		);
		$html = '<div class="psm-lottery__draw-list psm-lottery__draw-list--compact psm-dientoan-pending-grid">';
		foreach ( $products as $game => $label ) {
			$html .= '<article class="psm-lottery-card psm-lottery-card--dientoan psm-lottery-card--'
				. esc_attr( $game ) . '"><header><h3>'
				. esc_html( 'Xổ số ' . $label )
				. '</h3><span class="psm-lottery-card__state">'
				. esc_html__( 'Đang chờ kết quả', 'power-schedule-manager' )
				. '</span></header>'
				. self::dientoan_body( array(), $game )
				. '</article>';
		}

		return $html . '</div>';
	}

	/**
	 * Public fallback for one malformed stored result payload.
	 */
	private static function render_failure(): string {
		return '<div class="psm-lottery-error" role="status"><strong>'
			. esc_html__(
				'Chưa thể hiển thị kết quả này',
				'power-schedule-manager'
			)
			. '</strong><p>'
			. esc_html__(
				'Hệ thống đã ghi nhận lỗi dữ liệu và vẫn giữ trang hoạt động.',
				'power-schedule-manager'
			)
			. '</p></div>';
	}

	/**
	 * Reject corrupt JSON rows before any product-specific renderer sees them.
	 *
	 * @param array<string,mixed> $row Stored result row.
	 */
	private static function row_has_valid_results_json( array $row ): bool {
		if ( ! array_key_exists( 'results_json', $row ) ) {
			return false;
		}
		$decoded = json_decode( (string) $row['results_json'], true, 64 );

		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded );
	}

	private static function decode_results( array $row ): array {
		$decoded = json_decode( (string) $row['results_json'], true, 64 );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$metadata = array();
		if (
			isset( $decoded['results'] )
			&& is_array( $decoded['results'] )
		) {
			$metadata = $decoded;
			unset( $metadata['results'] );
			$decoded = $decoded['results'];
		}

		/*
		 * XoSoAPI v2 returns traditional results as rows using either a
		 * scalar value or an array:
		 * { prizeCode: "G4", values: ["12345", "67890"] }.
		 * Older stored rows therefore need to be grouped at render time too.
		 */
		if ( array_is_list( $decoded ) ) {
			$scalar_results = array_values(
				array_filter( $decoded, 'is_scalar' )
			);
			if (
				array() !== $scalar_results
				&& count( $scalar_results ) === count( $decoded )
			) {
				return array_merge(
					array( 'winning_numbers' => $scalar_results ),
					$metadata
				);
			}
			usort(
				$decoded,
				static function ( mixed $a, mixed $b ): int {
					if ( ! is_array( $a ) || ! is_array( $b ) ) {
						return 0;
					}
					$prize_order = absint(
						$a['prizeOrder'] ?? $a['prize_order'] ?? 99
					) <=> absint(
						$b['prizeOrder'] ?? $b['prize_order'] ?? 99
					);
					return 0 !== $prize_order
						? $prize_order
						: absint( $a['position'] ?? 0 )
							<=> absint( $b['position'] ?? 0 );
				}
			);
			$grouped = array();
			foreach ( $decoded as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$raw_code = $item['prizeCode']
					?? $item['prize_code']
					?? $item['code']
					?? $item['prize']
					?? '';
				$key = self::canonical_prize_key( (string) $raw_code );
				if ( '' === $key ) {
					$key = sanitize_key( (string) $raw_code );
				}
				$value = $item['value']
					?? $item['number']
					?? $item['result']
					?? null;
				$item_values = isset( $item['values'] )
					&& is_array( $item['values'] )
						? $item['values']
						: array();
				if ( null !== $value ) {
					$item_values[] = $value;
				}
				if ( '' === $key || array() === $item_values ) {
					continue;
				}
				foreach ( $item_values as $item_value ) {
					if (
						is_scalar( $item_value )
						&& '' !== trim( (string) $item_value )
					) {
						$grouped[ $key ][] = trim( (string) $item_value );
					}
				}
			}
			$grouped = array() !== $grouped ? $grouped : $decoded;

			return array_merge( $grouped, $metadata );
		}

		$canonical = array();
		foreach ( $decoded as $raw_key => $value ) {
			$key = self::canonical_prize_key( (string) $raw_key );
			$canonical[ '' !== $key ? $key : (string) $raw_key ] = $value;
		}

		return array_merge( $canonical, $metadata );
	}

	private static function canonical_prize_key( string $key ): string {
		$slug = sanitize_title( remove_accents( strtolower( trim( $key ) ) ) );
		$compact = str_replace( array( '-', '_' ), '', $slug );
		$map = array(
			'db' => 'db',
			'dacbiet' => 'db',
			'giaidacbiet' => 'db',
			'special' => 'db',
			'specialprize' => 'db',
			'pdb' => 'pdb',
			'phudb' => 'pdb',
			'phudacbiet' => 'pdb',
			'giaiphudacbiet' => 'pdb',
			'g1' => 'g1', 'giai1' => 'g1', 'giainhat' => 'g1',
			'prize1' => 'g1',
			'g2' => 'g2', 'giai2' => 'g2', 'giainhi' => 'g2',
			'prize2' => 'g2',
			'g3' => 'g3', 'giai3' => 'g3', 'giaiba' => 'g3',
			'prize3' => 'g3',
			'g4' => 'g4', 'giai4' => 'g4', 'giaitu' => 'g4',
			'prize4' => 'g4',
			'g5' => 'g5', 'giai5' => 'g5', 'giainam' => 'g5',
			'prize5' => 'g5',
			'g6' => 'g6', 'giai6' => 'g6', 'giaisau' => 'g6',
			'prize6' => 'g6',
			'g7' => 'g7', 'giai7' => 'g7', 'giaibay' => 'g7',
			'prize7' => 'g7',
			'g8' => 'g8', 'giai8' => 'g8', 'giaitam' => 'g8',
			'prize8' => 'g8',
		);

		return $map[ $compact ] ?? '';
	}

	private static function ordered_prize_keys(
		array $sets,
		string $region
	): array {
		$all = array();
		foreach ( $sets as $set ) {
			foreach ( array_keys( $set ) as $key ) {
				$all[ (string) $key ] = true;
			}
		}
		$order = 'north' === $region
			? array(
				'db',
				'special',
				'special_prize',
				'g1',
				'g2',
				'g3',
				'g4',
				'g5',
				'g6',
				'g7',
			)
			: array(
				'g8',
				'g7',
				'g6',
				'g5',
				'g4',
				'g3',
				'g2',
				'g1',
				'db',
				'special',
				'special_prize',
			);
		uksort(
			$all,
			static function ( string $a, string $b ) use ( $order ): int {
				$ai = array_search( sanitize_key( $a ), $order, true );
				$bi = array_search( sanitize_key( $b ), $order, true );
				return ( false === $ai ? 999 : $ai ) <=> ( false === $bi ? 999 : $bi );
			}
		);
		return array_keys( $all );
	}

	/**
	 * Prize rows are fixed by the traditional lottery format. Always rendering
	 * the full structure prevents a live draw from jumping as each prize lands.
	 *
	 * @return array<int,string>
	 */
	private static function expected_prize_keys( string $region ): array {
		return 'north' === $region
			? array( 'db', 'g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7' )
			: array( 'g8', 'g7', 'g6', 'g5', 'g4', 'g3', 'g2', 'g1', 'db' );
	}

	private static function expected_prize_count(
		string $region,
		string $key
	): int {
		$key = sanitize_key( $key );
		$counts = 'north' === $region
			? array(
				'db' => 1, 'g1' => 1, 'g2' => 2, 'g3' => 6,
				'g4' => 4, 'g5' => 6, 'g6' => 3, 'g7' => 4,
			)
			: array(
				'g8' => 1, 'g7' => 1, 'g6' => 3, 'g5' => 1,
				'g4' => 7, 'g3' => 2, 'g2' => 1, 'g1' => 1,
				'db' => 1,
			);

		return $counts[ $key ] ?? 1;
	}

	private static function prize_value( array $results, string $key ): mixed {
		$aliases = array(
			'db' => array(
				'db',
				'special',
				'special_prize',
				'giai_dac_biet',
			),
			'pdb' => array(
				'pdb',
				'phu_db',
				'phu_dac_biet',
				'giai_phu_dac_biet',
			),
		);
		$candidates = $aliases[ $key ] ?? array( $key );
		foreach ( $candidates as $candidate ) {
			if ( array_key_exists( $candidate, $results ) ) {
				return $results[ $candidate ];
			}
		}
		foreach ( $results as $raw_key => $value ) {
			if (
				in_array(
					sanitize_key( (string) $raw_key ),
					$candidates,
					true
				)
			) {
				return $value;
			}
		}

		return array();
	}

	private static function numbers( mixed $value ): array {
		$numbers = array();
		$walk = static function ( mixed $item ) use ( &$numbers ): void {
			if ( is_scalar( $item ) ) {
				$clean = trim( sanitize_text_field( (string) $item ) );
				if ( '' !== $clean ) {
					$numbers[] = $clean;
				}
			}
		};
		if ( is_array( $value ) ) {
			array_walk_recursive( $value, $walk );
		} else {
			$walk( $value );
		}
		return array_values( array_unique( $numbers ) );
	}

	private static function primary_numbers( array $results ): array {
		if ( array_is_list( $results ) ) {
			$values = array();
			foreach ( $results as $item ) {
				if ( is_scalar( $item ) ) {
					$values[] = $item;
					continue;
				}
				if ( ! is_array( $item ) ) {
					continue;
				}
				$value = $item['value']
					?? $item['number']
					?? $item['result']
					?? $item['values']
					?? null;
				if ( is_array( $value ) ) {
					$values = array_merge( $values, $value );
				} elseif ( is_scalar( $value ) ) {
					$values[] = $value;
				}
			}

			return self::ball_numbers( $values );
		}
		foreach (
			array(
				'numbers',
				'winning_numbers',
				'winningNumbers',
				'result',
				'ket_qua',
				'keno',
				'mega645_jackpot',
				'power655_jackpot',
				'jackpot',
				'db',
				'special',
			)
			as $key
		) {
			if ( isset( $results[ $key ] ) ) {
				$numbers = self::ball_numbers( $results[ $key ] );
				if ( count( $numbers ) > 1 ) {
					return $numbers;
				}
				if ( 1 === count( $numbers ) ) {
					$parts = preg_split( '/[\s,;·|-]+/', $numbers[0] );
					$parts = self::ball_numbers(
						array_values(
							array_filter( array_map( 'trim', $parts ) )
						)
					);
					return count( $parts ) > 1 ? $parts : $numbers;
				}
			}
		}

		return array();
	}

	/**
	 * Accept only lottery-number tokens, never prize names or monetary values.
	 *
	 * @return array<int,string>
	 */
	private static function ball_numbers( mixed $value ): array {
		return array_values(
			array_filter(
				self::numbers( $value ),
				static fn ( string $number ): bool =>
					1 === preg_match( '/\A\d{1,3}\z/', $number )
			)
		);
	}

	private static function number_lines(
		array $numbers,
		bool $loading = false,
		int $expected = 1
	): string {
		if ( array() === $numbers ) {
			if ( ! $loading ) {
				return '<span class="psm-lottery-pending">'
					. '<span aria-hidden="true">—</span>'
					. '<span class="screen-reader-text">'
					. esc_html__( 'Chưa có dữ liệu', 'power-schedule-manager' )
					. '</span>'
					. '</span>';
			}
			$html = '';
			for ( $index = 0; $index < $expected; ++$index ) {
				$html .= '<i class="psm-lottery-loader" aria-label="Đang quay"></i>';
			}
			return $html;
		}
		$html = implode(
			'',
			array_map(
				static fn ( string $number ): string =>
					'<span>' . esc_html( $number ) . '</span>',
				$numbers
			)
		);
		if ( $loading && count( $numbers ) < $expected ) {
			for ( $index = count( $numbers ); $index < $expected; ++$index ) {
				$html .= '<i class="psm-lottery-loader" aria-label="Đang quay"></i>';
			}
		}

		return $html;
	}

	private static function draw_is_live( array $row, string $preset ): bool {
		$date = (string) ( $row['draw_date'] ?? '' );
		$today = wp_date(
			'Y-m-d',
			null,
			new DateTimeZone( POWER_SCHEDULE_MANAGER_TIMEZONE )
		);
		if ( $date !== $today ) {
			return false;
		}

		if (
			! in_array(
				$preset,
				array( 'north', 'central', 'south' ),
				true
			)
		) {
			$expected = self::expected_primary_count( $preset );
			$status = sanitize_key( (string) ( $row['status'] ?? '' ) );
			if (
				! in_array(
					$status,
					array(
						'live',
						'drawing',
						'running',
						'in_progress',
						'updating',
					),
					true
				)
			) {
				return false;
			}

			/*
			 * A complete result is authoritative even when the provider has not
			 * moved its draw status from "live" to "completed" yet.
			 */
			$effective_preset = sanitize_key(
				(string) ( $row['game_type'] ?? $preset )
			);
			return count(
				self::product_result_numbers(
					self::decode_results( $row ),
					$effective_preset
				)
			) < self::expected_primary_count( $effective_preset );
		}

		$results = self::decode_results( $row );
		if ( in_array( $preset, array( 'north', 'central', 'south' ), true ) ) {
			foreach ( self::expected_prize_keys( $preset ) as $key ) {
				if (
					count(
						self::numbers(
							self::prize_value( $results, $key )
						)
					) < self::expected_prize_count( $preset, $key )
				) {
					return true;
				}
			}
			return false;
		}

		return false;
	}

	/**
	 * Whether a stored draw contains an actual primary result.
	 *
	 * Provider prize metadata alone does not count as a published result.
	 *
	 * @param array<string,mixed> $row Stored draw.
	 */
	private static function draw_has_results( array $row ): bool {
		return array() !== self::product_result_numbers(
			self::decode_results( $row ),
			sanitize_key( (string) ( $row['game_type'] ?? '' ) )
		);
	}

	/**
	 * Extract only the published result values for one product.
	 *
	 * Điện toán uses G1/G2/G3 prize rows rather than a winningNumbers field,
	 * so the generic Vietlott extractor would incorrectly mark complete draws
	 * as pending.
	 *
	 * @return array<int,string>
	 */
	private static function product_result_numbers(
		array $results,
		string $game
	): array {
		if ( 'dientoan123' === $game ) {
			$numbers = array();
			foreach ( self::dientoan123_sets( $results ) as $set ) {
				$numbers = array_merge( $numbers, $set );
			}

			return array_values( array_filter( $numbers ) );
		}
		if ( 'dientoan6x36' === $game ) {
			$numbers = self::numbers( $results['g1'] ?? array() );

			return array() !== $numbers
				? $numbers
				: self::primary_numbers( $results );
		}
		if ( 'thantai' === $game ) {
			$numbers = self::numbers(
				$results['db'] ?? $results['g1'] ?? array()
			);

			return array() !== $numbers
				? $numbers
				: self::primary_numbers( $results );
		}

		return self::primary_numbers( $results );
	}

	private static function expected_primary_count( string $preset ): int {
		return match ( $preset ) {
			'keno', 'keno_history' => 20,
			'power655' => 7,
			'mega645', 'dientoan6x36' => 6,
			'dientoan', 'dientoan123' => 3,
			default => 1,
		};
	}

	private static function is_special( string $key ): bool {
		return in_array( sanitize_key( $key ), array( 'db', 'special', 'special_prize', 'giai_dac_biet' ), true );
	}

	private static function prize_label( string $key ): string {
		$labels = array(
			'db' => 'Đặc biệt', 'special' => 'Đặc biệt',
			'special_prize' => 'Đặc biệt',
			'pdb' => 'Phụ đặc biệt',
			'g8' => 'Giải tám',
			'g7' => 'Giải bảy', 'g6' => 'Giải sáu',
			'g5' => 'Giải năm', 'g4' => 'Giải tư',
			'g3' => 'Giải ba', 'g2' => 'Giải nhì',
			'g1' => 'Giải nhất', 'jackpot' => 'Jackpot',
			'jackpot1' => 'Jackpot 1', 'jackpot2' => 'Jackpot 2',
		);
		return $labels[ sanitize_key( $key ) ] ?? sanitize_text_field( $key );
	}

	private static function title( string $preset ): string {
		return match ( $preset ) {
			'north' => 'Xổ số miền Bắc',
			'central' => 'Xổ số miền Trung',
			'south' => 'Xổ số miền Nam',
			'mega645' => 'Xổ số Mega 6/45',
			'power655' => 'Xổ số Power 6/55',
			'max3d' => 'Xổ số Max 3D',
			'max3dplus' => 'Xổ số Max 3D+',
			'max3dpro' => 'Xổ số Max 3D Pro',
			'keno' => 'Xổ số Keno',
			'keno_history' => 'Các kỳ Vietlott Keno gần đây',
			'dientoan' => 'Xổ số Điện toán',
			'dientoan123' => 'Xổ số Điện toán 123',
			'dientoan6x36' => 'Xổ số Điện toán 6x36',
			'thantai' => 'Xổ số Thần Tài 4',
			default => 'Kết quả xổ số mới nhất',
		};
	}

	private static function game_label( string $game ): string {
		return match ( sanitize_key( $game ) ) {
			'mega645' => 'Mega 6/45',
			'power655' => 'Power 6/55',
			'max3d' => 'Max 3D',
			'max3dplus' => 'Max 3D+',
			'max3dpro' => 'Max 3D Pro',
			'keno' => 'Keno',
			'dientoan123' => 'Điện toán 123',
			'dientoan6x36' => 'Điện toán 6x36',
			'thantai' => 'Thần Tài 4',
			default => 'Điện toán',
		};
	}

	private static function region_title( string $region ): string {
		return match ( $region ) {
			'north' => 'Xổ số miền Bắc',
			'central' => 'Xổ số miền Trung',
			default => 'Xổ số miền Nam',
		};
	}

	private static function format_date( string $date ): string {
		$timestamp = strtotime( $date );
		return false === $timestamp ? $date : wp_date( 'd/m/Y', $timestamp );
	}

	private static function format_full_date( string $date ): string {
		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return $date;
		}
		$weekday = match ( (int) wp_date( 'N', $timestamp ) ) {
			1 => __( 'Thứ hai', 'power-schedule-manager' ),
			2 => __( 'Thứ ba', 'power-schedule-manager' ),
			3 => __( 'Thứ tư', 'power-schedule-manager' ),
			4 => __( 'Thứ năm', 'power-schedule-manager' ),
			5 => __( 'Thứ sáu', 'power-schedule-manager' ),
			6 => __( 'Thứ bảy', 'power-schedule-manager' ),
			default => __( 'Chủ nhật', 'power-schedule-manager' ),
		};

		return sprintf(
			/* translators: 1: weekday, 2: date. */
			__( '%1$s ngày %2$s', 'power-schedule-manager' ),
			$weekday,
			wp_date( 'd-m-Y', $timestamp )
		);
	}
}
