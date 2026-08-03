<?php
/**
 * Public weather map shortcodes.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders lightweight, lazy-loaded weather maps.
 */
final class Power_Schedule_Manager_Weather {

	public const string REFRESH_HOOK =
		'power_schedule_manager_weather_refresh';

	private const string FORECAST_URL =
		'https://api.open-meteo.com/v1/forecast';

	/**
	 * Supported Windy overlays and their Vietnamese labels.
	 *
	 * @var array<string, string>
	 */
	private const array OVERLAYS = array(
		'rain'      => 'Radar mưa',
		'wind'      => 'Tốc độ gió',
		'temp'      => 'Nhiệt độ',
		'clouds'    => 'Mây',
		'snowcover' => 'Tuyết phủ',
		'thunder'   => 'Dông sét',
	);

	/**
	 * Register public shortcodes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			self::REFRESH_HOOK,
			array( $this, 'refresh_forecast' ),
			10,
			2
		);

		$settings = $this->settings();
		$this->queue_forecast_refresh(
			(float) $settings['weather_default_lat'],
			(float) $settings['weather_default_lon'],
			5
		);

		add_shortcode(
			'power_schedule_weather',
			array( $this, 'render_weather' )
		);
		add_shortcode(
			'power_schedule_weather_forecast',
			function ( mixed $attributes = array() ): string {
				$attributes = is_array( $attributes )
					? $attributes
					: array();
				$attributes['map'] = 'no';
				$attributes['tabs'] = 'no';

				return $this->render_weather( $attributes );
			}
		);

		$shortcodes = array(
			'power_schedule_weather_rain'         => 'rain',
			'power_schedule_weather_wind'         => 'wind',
			'power_schedule_weather_temperature'  => 'temp',
			'power_schedule_weather_clouds'       => 'clouds',
			'power_schedule_weather_snow'         => 'snowcover',
			'power_schedule_weather_thunderstorms' => 'thunder',
		);

		foreach ( $shortcodes as $shortcode => $overlay ) {
			add_shortcode(
				$shortcode,
				function ( mixed $attributes = array() ) use ( $overlay ): string {
					$attributes = is_array( $attributes )
						? $attributes
						: array();
					$attributes['overlay'] = $overlay;
					$attributes['tabs']    = 'no';

					return $this->render_weather( $attributes );
				}
			);
		}
	}

	/**
	 * Render a weather map.
	 *
	 * @param mixed $attributes Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_weather( mixed $attributes = array() ): string {
		Power_Schedule_Manager_Assets::enqueue_public_assets( 'weather' );

		$attributes = is_array( $attributes ) ? $attributes : array();
		$settings = $this->settings();

		$attributes = shortcode_atts(
			array(
				'title'   => __( 'Thời tiết khu vực', 'power-schedule-manager' ),
				'area'    => (string) $settings['weather_default_label'],
				'lat'     => (string) $settings['weather_default_lat'],
				'lon'     => (string) $settings['weather_default_lon'],
				'zoom'    => (string) $settings['weather_default_zoom'],
				'height'  => (string) $settings['weather_default_height'],
				'overlay' => 'rain',
				'tabs'    => 'yes',
				'forecast' => 'yes',
				'map'      => 'yes',
				'show_content' => 'yes',
			),
			$attributes,
			'power_schedule_weather'
		);

		$latitude = $this->clamp_float(
			$attributes['lat'],
			-90.0,
			90.0,
			(float) $settings['weather_default_lat']
		);
		$longitude = $this->clamp_float(
			$attributes['lon'],
			-180.0,
			180.0,
			(float) $settings['weather_default_lon']
		);
		$zoom = $this->clamp_integer(
			$attributes['zoom'],
			3,
			15,
			(int) $settings['weather_default_zoom']
		);
		$height = $this->clamp_integer(
			$attributes['height'],
			320,
			760,
			(int) $settings['weather_default_height']
		);
		$overlay = sanitize_key( (string) $attributes['overlay'] );

		if ( ! isset( self::OVERLAYS[ $overlay ] ) ) {
			$overlay = 'rain';
		}

		$show_tabs = ! in_array(
			strtolower( (string) $attributes['tabs'] ),
			array( '0', 'false', 'no', 'off' ),
			true
		);
		$show_forecast = ! in_array(
			strtolower( (string) $attributes['forecast'] ),
			array( '0', 'false', 'no', 'off' ),
			true
		);
		$show_map = ! in_array(
			strtolower( (string) $attributes['map'] ),
			array( '0', 'false', 'no', 'off' ),
			true
		);
		$show_content = ! in_array(
			strtolower( (string) $attributes['show_content'] ),
			array( '0', 'false', 'no', 'off' ),
			true
		);
		$area      = sanitize_text_field( (string) $attributes['area'] );
		$title     = sanitize_text_field( (string) $attributes['title'] );
		$forecast  = $show_forecast
			? $this->forecast( $latitude, $longitude )
			: array();
		$map_url   = $this->build_embed_url(
			$latitude,
			$longitude,
			$zoom,
			$overlay
		);
		$map_title = sprintf(
			/* translators: 1: weather layer, 2: area name. */
			__( '%1$s tại %2$s', 'power-schedule-manager' ),
			self::OVERLAYS[ $overlay ],
			'' !== $area ? $area : __( 'khu vực đã chọn', 'power-schedule-manager' )
		);
		$id = wp_unique_id( 'psm-weather-' );

		ob_start();
		?>
		<section
			id="du-bao"
			class="psm-weather"
			aria-labelledby="<?php echo esc_attr( $id . '-title' ); ?>"
			data-psm-weather
			style="<?php echo esc_attr( '--psm-weather-height:' . $height . 'px;' ); ?>"
		>
			<h2 id="<?php echo esc_attr( $id . '-title' ); ?>" class="screen-reader-text">
				<?php echo esc_html( $title ); ?>
			</h2>
			<?php if ( $show_map && $show_tabs ) : ?>
				<nav class="psm-weather__toolbar" aria-label="<?php esc_attr_e( 'Chọn lớp dữ liệu thời tiết', 'power-schedule-manager' ); ?>">
					<span><?php esc_html_e( 'Lớp dữ liệu', 'power-schedule-manager' ); ?></span>
						<div
							class="psm-weather__tabs"
							role="tablist"
							aria-label="<?php esc_attr_e( 'Chọn lớp thời tiết', 'power-schedule-manager' ); ?>"
						>
							<?php foreach ( self::OVERLAYS as $layer => $label ) : ?>
								<?php
								$layer_url = $this->build_embed_url(
									$latitude,
									$longitude,
									$zoom,
									$layer
								);
								$layer_title = sprintf(
									/* translators: 1: weather layer, 2: area name. */
									__( '%1$s tại %2$s', 'power-schedule-manager' ),
									$label,
									'' !== $area
										? $area
										: __( 'khu vực đã chọn', 'power-schedule-manager' )
								);
								?>
								<button
									id="<?php echo esc_attr( $id . '-tab-' . $layer ); ?>"
									type="button"
									role="tab"
									class="<?php echo esc_attr( $layer === $overlay ? 'is-active' : '' ); ?>"
									aria-selected="<?php echo esc_attr( $layer === $overlay ? 'true' : 'false' ); ?>"
									aria-controls="<?php echo esc_attr( $id . '-panel' ); ?>"
									tabindex="<?php echo esc_attr( $layer === $overlay ? '0' : '-1' ); ?>"
									data-psm-weather-source="<?php echo esc_url( $layer_url ); ?>"
									data-psm-weather-title="<?php echo esc_attr( $layer_title ); ?>"
								>
									<?php echo esc_html( $label ); ?>
								</button>
							<?php endforeach; ?>
						</div>
				</nav>
			<?php endif; ?>

			<?php
			if ( $forecast ) {
				echo $this->render_forecast( $forecast ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $show_forecast ) {
				?>
				<div class="psm-weather__updating" role="status">
					<span aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Chưa có dự báo mới', 'power-schedule-manager' ); ?></strong>
					<small><?php esc_html_e( 'Hệ thống sẽ tự động thử lại trong nền.', 'power-schedule-manager' ); ?></small>
				</div>
				<?php
			}
			?>

			<?php if ( $show_map ) : ?>
			<div
				id="<?php echo esc_attr( $id . '-panel' ); ?>"
				class="psm-weather__frame"
				role="<?php echo esc_attr( $show_tabs ? 'tabpanel' : 'region' ); ?>"
				<?php if ( $show_tabs ) : ?>
					aria-labelledby="<?php echo esc_attr( $id . '-tab-' . $overlay ); ?>"
				<?php else : ?>
					aria-label="<?php echo esc_attr( $map_title ); ?>"
				<?php endif; ?>
				tabindex="0"
				data-psm-weather-panel
			>
				<iframe
					src="<?php echo esc_url( $map_url ); ?>"
					title="<?php echo esc_attr( $map_title ); ?>"
					loading="lazy"
					referrerpolicy="strict-origin-when-cross-origin"
					allowfullscreen
					data-psm-weather-frame
				></iframe>
			</div>
			<?php endif; ?>

			<p class="psm-weather__notice">
				<?php
				esc_html_e(
					'Dự báo có thể thay đổi theo thời điểm. Hãy theo dõi thông báo chính thức khi thời tiết diễn biến bất thường.',
					'power-schedule-manager'
				);
				?>
			</p>
			<?php if ( $show_content ) : ?>
				<section class="psm-supporting-content psm-supporting-content--weather">
					<h2><?php esc_html_e( 'Cách đọc dự báo thời tiết khu vực', 'power-schedule-manager' ); ?></h2>
					<div>
						<section>
							<h3><?php esc_html_e( 'Nhiệt độ và cảm giác thực tế', 'power-schedule-manager' ); ?></h3>
							<p><?php esc_html_e( 'Nhiệt độ cảm nhận có thể khác nhiệt độ đo được do độ ẩm và gió. Nên xem đồng thời cả hai chỉ số khi lên kế hoạch ngoài trời.', 'power-schedule-manager' ); ?></p>
						</section>
						<section>
							<h3><?php esc_html_e( 'Mưa, gió và dông sét', 'power-schedule-manager' ); ?></h3>
							<p><?php esc_html_e( 'Bản đồ giúp quan sát xu hướng theo khu vực, còn dự báo theo ngày cung cấp mức tham khảo. Dữ liệu có thể thay đổi khi hình thái thời tiết di chuyển.', 'power-schedule-manager' ); ?></p>
						</section>
						<section>
							<h3><?php esc_html_e( 'Thời điểm cập nhật', 'power-schedule-manager' ); ?></h3>
							<p><?php esc_html_e( 'Hãy kiểm tra thời gian cập nhật gần nhất và theo dõi cảnh báo từ cơ quan khí tượng khi có mưa lớn, bão hoặc thời tiết nguy hiểm.', 'power-schedule-manager' ); ?></p>
						</section>
					</div>
				</section>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Read one cached forecast without calling an external API during render.
	 *
	 * @return array<string,mixed>
	 */
	private function forecast(
		float $latitude,
		float $longitude
	): array {
		$fresh_key = $this->forecast_cache_key( $latitude, $longitude );
		$stale_key = $fresh_key . '_stale';
		$cached = get_transient( $fresh_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$stale = get_transient( $stale_key );
		$this->queue_forecast_refresh( $latitude, $longitude, 5 );
		if ( ! is_array( $stale ) ) {
			/*
			 * Low-traffic sites may not run WP-Cron soon enough to populate
			 * the first forecast. Fetch once on a cold cache; the refresh
			 * method keeps its own lock so concurrent visitors do not fan out.
			 */
			$this->refresh_forecast( $latitude, $longitude );
			$refreshed = get_transient( $fresh_key );
			if ( is_array( $refreshed ) ) {
				return $refreshed;
			}
		}

		return is_array( $stale ) ? $stale : array();
	}

	/**
	 * Refresh weather data in WP-Cron, never in a visitor page request.
	 */
	public function refresh_forecast(
		mixed $latitude,
		mixed $longitude
	): void {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return;
		}
		$latitude = $this->clamp_float( $latitude, -90.0, 90.0, 11.5753 );
		$longitude = $this->clamp_float(
			$longitude,
			-180.0,
			180.0,
			108.1429
		);
		$fresh_key = $this->forecast_cache_key( $latitude, $longitude );
		$stale_key = $fresh_key . '_stale';
		$lock_key = $fresh_key . '_lock';
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 20 );

		$url = add_query_arg(
			array(
				'latitude'      => number_format( $latitude, 5, '.', '' ),
				'longitude'     => number_format( $longitude, 5, '.', '' ),
				'current'       => implode(
					',',
					array(
						'temperature_2m',
						'apparent_temperature',
						'relative_humidity_2m',
						'precipitation',
						'weather_code',
						'wind_speed_10m',
					)
				),
				'daily'         => implode(
					',',
					array(
						'weather_code',
						'temperature_2m_max',
						'temperature_2m_min',
						'precipitation_probability_max',
					)
				),
				'timezone'      => POWER_SCHEDULE_MANAGER_TIMEZONE,
				'forecast_days' => 4,
			),
			self::FORECAST_URL
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 6,
				'redirection'         => 0,
				'limit_response_size' => 131072,
				'user-agent'          => 'Cúp Điện Lâm Đồng/'
					. POWER_SCHEDULE_MANAGER_VERSION,
			)
		);
		delete_transient( $lock_key );
		if (
			is_wp_error( $response )
			|| 200 !== wp_remote_retrieve_response_code( $response )
		) {
			$this->queue_forecast_refresh( $latitude, $longitude, 300 );
			return;
		}
		$decoded = json_decode(
			wp_remote_retrieve_body( $response ),
			true,
			16
		);
		if (
			! is_array( $decoded )
			|| ! is_array( $decoded['current'] ?? null )
			|| ! is_array( $decoded['daily'] ?? null )
		) {
			$this->queue_forecast_refresh( $latitude, $longitude, 300 );
			return;
		}

		$current = $decoded['current'];
		$daily = $decoded['daily'];
		$forecast = array(
			'current' => array(
				'time'        => sanitize_text_field(
					(string) ( $current['time'] ?? '' )
				),
				'temperature' => (float) ( $current['temperature_2m'] ?? 0 ),
				'feels_like'  => (float) (
					$current['apparent_temperature'] ?? 0
				),
				'humidity'    => absint(
					$current['relative_humidity_2m'] ?? 0
				),
				'rain'        => (float) ( $current['precipitation'] ?? 0 ),
				'wind'        => (float) ( $current['wind_speed_10m'] ?? 0 ),
				'code'        => absint( $current['weather_code'] ?? 0 ),
			),
			'daily' => array(),
		);
		$dates = is_array( $daily['time'] ?? null )
			? $daily['time']
			: array();
		foreach ( array_slice( $dates, 0, 4 ) as $index => $date ) {
			$forecast['daily'][] = array(
				'date' => sanitize_text_field( (string) $date ),
				'code' => absint( $daily['weather_code'][ $index ] ?? 0 ),
				'max'  => (float) (
					$daily['temperature_2m_max'][ $index ] ?? 0
				),
				'min'  => (float) (
					$daily['temperature_2m_min'][ $index ] ?? 0
				),
				'rain' => absint(
					$daily['precipitation_probability_max'][ $index ] ?? 0
				),
			);
		}
		set_transient( $fresh_key, $forecast, 30 * MINUTE_IN_SECONDS );
		set_transient( $stale_key, $forecast, 6 * HOUR_IN_SECONDS );
		$this->queue_forecast_refresh( $latitude, $longitude, 25 * MINUTE_IN_SECONDS );
	}

	/**
	 * Schedule an idempotent background refresh for one coordinate pair.
	 */
	private function queue_forecast_refresh(
		float $latitude,
		float $longitude,
		int $delay
	): void {
		$args = array(
			number_format( $latitude, 5, '.', '' ),
			number_format( $longitude, 5, '.', '' ),
		);
		if (
			false === wp_next_scheduled(
				self::REFRESH_HOOK,
				$args
			)
		) {
			wp_schedule_single_event(
				time() + max( 1, $delay ),
				self::REFRESH_HOOK,
				$args,
				true
			);
		}
	}

	/**
	 * Build a short cache key without exposing coordinates in option names.
	 */
	private function forecast_cache_key(
		float $latitude,
		float $longitude
	): string {
		$key = hash(
			'sha256',
			number_format( $latitude, 3, '.', '' )
				. '|'
				. number_format( $longitude, 3, '.', '' )
		);

		return 'psm_weather_' . substr( $key, 0, 24 );
	}

	/**
	 * Render readable forecast cards for users and search engines.
	 *
	 * @param array<string,mixed> $forecast Normalized forecast.
	 */
	private function render_forecast( array $forecast ): string {
		$current = is_array( $forecast['current'] ?? null )
			? $forecast['current']
			: array();
		$daily = is_array( $forecast['daily'] ?? null )
			? $forecast['daily']
			: array();
		if ( ! $current || ! $daily ) {
			return '';
		}
		$code = absint( $current['code'] ?? 0 );
		$html = '<div class="psm-weather__forecast"><article class="psm-weather__now">'
			. '<span class="psm-weather__icon" aria-hidden="true">'
			. esc_html( self::weather_icon( $code ) )
			. '</span><div><span>'
			. esc_html__( 'Hiện tại', 'power-schedule-manager' )
			. '</span><strong>'
			. esc_html( number_format_i18n( (float) $current['temperature'], 1 ) )
			. '°C</strong><small>'
			. esc_html( self::weather_label( $code ) )
			. '</small></div><dl><div><dt>'
			. esc_html__( 'Cảm giác', 'power-schedule-manager' )
			. '</dt><dd>'
			. esc_html( number_format_i18n( (float) $current['feels_like'], 1 ) )
			. '°C</dd></div><div><dt>'
			. esc_html__( 'Độ ẩm', 'power-schedule-manager' )
			. '</dt><dd>' . esc_html( (string) absint( $current['humidity'] ) )
			. '%</dd></div><div><dt>'
			. esc_html__( 'Gió', 'power-schedule-manager' )
			. '</dt><dd>'
			. esc_html( number_format_i18n( (float) $current['wind'], 1 ) )
			. ' km/h</dd></div></dl></article><div class="psm-weather__days">';
		foreach ( $daily as $index => $day ) {
			if ( ! is_array( $day ) || empty( $day['date'] ) ) {
				continue;
			}
			$day_code = absint( $day['code'] ?? 0 );
			$day_label = 0 === $index
				? __( 'Hôm nay', 'power-schedule-manager' )
				: (
					1 === $index
						? __( 'Ngày mai', 'power-schedule-manager' )
						: wp_date(
							'l',
							strtotime( (string) $day['date'] )
						)
				);
			$html .= '<article><time datetime="'
				. esc_attr( (string) $day['date'] ) . '">'
				. esc_html( $day_label )
				. '</time><span class="psm-weather__icon" aria-hidden="true">'
				. esc_html( self::weather_icon( $day_code ) )
				. '</span><strong>'
				. esc_html( number_format_i18n( (float) $day['max'], 0 ) )
				. '° / '
				. esc_html( number_format_i18n( (float) $day['min'], 0 ) )
				. '°</strong><small>'
				. esc_html( self::weather_label( $day_code ) )
				. ' · '
				. esc_html( (string) absint( $day['rain'] ?? 0 ) )
				. '% '
				. esc_html__( 'mưa', 'power-schedule-manager' )
				. '</small></article>';
		}
		$html .= '</div></div>';

		return $html;
	}

	private static function weather_label( int $code ): string {
		return match ( true ) {
			0 === $code => __( 'Trời quang', 'power-schedule-manager' ),
			$code <= 3 => __( 'Có mây', 'power-schedule-manager' ),
			in_array( $code, array( 45, 48 ), true ) =>
				__( 'Có sương mù', 'power-schedule-manager' ),
			$code >= 51 && $code <= 57 =>
				__( 'Mưa phùn', 'power-schedule-manager' ),
			$code >= 61 && $code <= 67 =>
				__( 'Có mưa', 'power-schedule-manager' ),
			$code >= 71 && $code <= 77 =>
				__( 'Có tuyết', 'power-schedule-manager' ),
			$code >= 80 && $code <= 82 =>
				__( 'Mưa rào', 'power-schedule-manager' ),
			$code >= 85 && $code <= 86 =>
				__( 'Mưa tuyết', 'power-schedule-manager' ),
			$code >= 95 =>
				__( 'Có dông', 'power-schedule-manager' ),
			default => __( 'Thời tiết thay đổi', 'power-schedule-manager' ),
		};
	}

	private static function weather_icon( int $code ): string {
		return match ( true ) {
			0 === $code => '☀️',
			$code <= 3 => '⛅',
			in_array( $code, array( 45, 48 ), true ) => '🌫️',
			$code >= 51 && $code <= 67 => '🌧️',
			$code >= 71 && $code <= 77 => '❄️',
			$code >= 80 && $code <= 86 => '🌦️',
			$code >= 95 => '⛈️',
			default => '🌤️',
		};
	}

	/**
	 * Build a fixed Windy embed URL.
	 *
	 * @param float  $latitude Latitude.
	 * @param float  $longitude Longitude.
	 * @param int    $zoom Zoom.
	 * @param string $overlay Overlay.
	 *
	 * @return string
	 */
	private function build_embed_url(
		float $latitude,
		float $longitude,
		int $zoom,
		string $overlay
	): string {
		$query = array(
			'type'       => 'map',
			'location'   => 'coordinates',
			'metricWind' => 'default',
			'metricTemp' => 'default',
			'radarRange' => '-1',
			'level'      => 'surface',
			'overlay'    => $overlay,
			'product'    => 'ecmwf',
			'lat'        => number_format( $latitude, 6, '.', '' ),
			'lon'        => number_format( $longitude, 6, '.', '' ),
			'zoom'       => (string) $zoom,
			'detailLat'  => number_format( $latitude, 6, '.', '' ),
			'detailLon'  => number_format( $longitude, 6, '.', '' ),
			'detail'     => 'true',
			'message'    => 'true',
			'marker'     => 'true',
			'calendar'   => 'now',
			'pressure'   => 'true',
		);

		return add_query_arg(
			$query,
			'https://embed.windy.com/embed2.html'
		);
	}

	/**
	 * Read the current cached observation for the utility hero.
	 * External weather APIs are never called from this method.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function hero_summary(): array {
		$settings = $this->settings();
		$key = $this->forecast_cache_key(
			(float) $settings['weather_default_lat'],
			(float) $settings['weather_default_lon']
		);
		$forecast = get_transient( $key );
		if ( ! is_array( $forecast ) ) {
			$forecast = get_transient( $key . '_stale' );
		}
		$current = is_array( $forecast )
			&& is_array( $forecast['current'] ?? null )
			? $forecast['current'] : array();
		if ( array() === $current ) {
			return array();
		}

		$temperature = is_numeric( $current['temperature'] ?? null )
			? number_format_i18n( (float) $current['temperature'], 1 ) . '°C'
			: '';
		$time = sanitize_text_field( (string) ( $current['time'] ?? '' ) );
		$timestamp = strtotime( $time );

		return array(
			array(
				'label'  => __( 'Hiện tại', 'power-schedule-manager' ),
				'value'  => $temperature,
				'detail' => sanitize_text_field(
					(string) $settings['weather_default_label']
				),
				'tone'   => 'live',
			),
			array(
				'label'  => __( 'Cập nhật', 'power-schedule-manager' ),
				'value'  => false !== $timestamp ? wp_date( 'H:i', $timestamp ) : '',
				'detail' => __( 'Dữ liệu khí tượng gần nhất', 'power-schedule-manager' ),
				'tone'   => '',
			),
		);
	}

	/**
	 * Get normalized weather settings.
	 *
	 * @return array<string, mixed>
	 */
	private function settings(): array {
		$settings = get_option(
			POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
			array()
		);
		$settings = is_array( $settings ) ? $settings : array();

		return wp_parse_args(
			$settings,
			array(
				'weather_default_label'  => 'Lâm Đồng',
				'weather_default_lat'    => 11.5753,
				'weather_default_lon'    => 108.1429,
				'weather_default_zoom'   => 7,
				'weather_default_height' => 520,
			)
		);
	}

	/**
	 * Clamp a float.
	 *
	 * @param mixed $value Value.
	 * @param float $minimum Minimum.
	 * @param float $maximum Maximum.
	 * @param float $fallback Fallback.
	 *
	 * @return float
	 */
	private function clamp_float(
		mixed $value,
		float $minimum,
		float $maximum,
		float $fallback
	): float {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return min( $maximum, max( $minimum, (float) $value ) );
	}

	/**
	 * Clamp an integer.
	 *
	 * @param mixed $value Value.
	 * @param int   $minimum Minimum.
	 * @param int   $maximum Maximum.
	 * @param int   $fallback Fallback.
	 *
	 * @return int
	 */
	private function clamp_integer(
		mixed $value,
		int $minimum,
		int $maximum,
		int $fallback
	): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return min( $maximum, max( $minimum, (int) $value ) );
	}
}
