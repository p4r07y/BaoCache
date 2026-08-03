<?php
/**
 * Plugin settings view.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$settings = isset( $psm_admin_args['settings'] )
	&& is_array( $psm_admin_args['settings'] )
	? $psm_admin_args['settings']
	: array();

$map_provider = isset( $settings['map_provider'] )
	? sanitize_key( (string) $settings['map_provider'] )
	: 'osm';

if (
	! in_array(
		$map_provider,
		array( 'osm', 'maptiler', 'stadia', 'custom', 'disabled' ),
		true
	)
) {
	$map_provider = 'osm';
}

$map_tile_url = isset( $settings['map_tile_url'] )
	? (string) $settings['map_tile_url']
	: '';

$map_attribution = isset( $settings['map_attribution'] )
	? (string) $settings['map_attribution']
	: '';

$map_max_zoom = isset( $settings['map_max_zoom'] )
	? min( 20, max( 1, absint( $settings['map_max_zoom'] ) ) )
	: 19;

$maptiler_style = Power_Schedule_Manager_Assets::sanitize_maptiler_style(
	(string) ( $settings['maptiler_style'] ?? 'streets-v4' )
);
$stadia_style = Power_Schedule_Manager_Assets::sanitize_stadia_style(
	(string) ( $settings['stadia_style'] ?? 'alidade_smooth' )
);
$maptiler_key_encrypted = (string) (
	$settings['maptiler_key_encrypted'] ?? ''
);
$stadia_key_encrypted = (string) (
	$settings['stadia_key_encrypted'] ?? ''
);
$maptiler_key_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_MAPTILER_KEY',
	$maptiler_key_encrypted
);
$stadia_key_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_STADIA_KEY',
	$stadia_key_encrypted
);
$weather_default_label = sanitize_text_field(
	(string) ( $settings['weather_default_label'] ?? 'Lâm Đồng' )
);
$weather_default_lat = min(
	90,
	max(
		-90,
		(float) ( $settings['weather_default_lat'] ?? 11.5753 )
	)
);
$weather_default_lon = min(
	180,
	max(
		-180,
		(float) ( $settings['weather_default_lon'] ?? 108.1429 )
	)
);
$weather_default_zoom = min(
	15,
	max( 3, absint( $settings['weather_default_zoom'] ?? 7 ) )
);
$weather_default_height = min(
	760,
	max( 320, absint( $settings['weather_default_height'] ?? 520 ) )
);

$import_post_status = isset( $settings['import_post_status'] )
	&& 'draft' === (string) $settings['import_post_status']
		? 'draft'
		: 'publish';

$disclaimer_text = isset( $settings['disclaimer_text'] )
	&& is_string( $settings['disclaimer_text'] )
		? $settings['disclaimer_text']
		: '';
$ads_enabled = ! array_key_exists( 'ads_enabled', $settings )
	|| ! empty( $settings['ads_enabled'] );

$archive_banner_ad = isset( $settings['archive_banner_ad'] )
	&& is_string( $settings['archive_banner_ad'] )
		? $settings['archive_banner_ad']
		: '';

$archive_bottom_banner_ad = isset( $settings['archive_bottom_banner_ad'] )
	&& is_string( $settings['archive_bottom_banner_ad'] )
		? $settings['archive_bottom_banner_ad']
		: '';

$single_top_banner_ad = isset( $settings['single_top_banner_ad'] )
	&& is_string( $settings['single_top_banner_ad'] )
		? $settings['single_top_banner_ad']
		: '';

$single_bottom_banner_ad = isset( $settings['single_bottom_banner_ad'] )
	&& is_string( $settings['single_bottom_banner_ad'] )
		? $settings['single_bottom_banner_ad']
		: '';

$home_top_banner_ad = isset( $settings['home_top_banner_ad'] )
	&& is_string( $settings['home_top_banner_ad'] )
		? $settings['home_top_banner_ad']
		: '';

$home_bottom_banner_ad = isset( $settings['home_bottom_banner_ad'] )
	&& is_string( $settings['home_bottom_banner_ad'] )
		? $settings['home_bottom_banner_ad']
		: '';

$raw_payload_retention_days = min( 90, max( 7, absint( $settings['raw_payload_retention_days'] ?? 30 ) ) );
$import_log_retention_months = min( 36, max( 3, absint( $settings['import_log_retention_months'] ?? 12 ) ) );
$completed_retention_months = min( 60, max( 12, absint( $settings['completed_retention_months'] ?? 24 ) ) );
$cancelled_retention_months = min( 36, max( 3, absint( $settings['cancelled_retention_months'] ?? 12 ) ) );

$home_show_hero = ! array_key_exists( 'home_show_hero', $settings )
	|| ! empty( $settings['home_show_hero'] );
$home_show_search = ! array_key_exists( 'home_show_search', $settings )
	|| ! empty( $settings['home_show_search'] );
$home_show_alert = ! array_key_exists( 'home_show_alert', $settings )
	|| ! empty( $settings['home_show_alert'] );
$home_show_days = ! array_key_exists( 'home_show_days', $settings )
	|| ! empty( $settings['home_show_days'] );
$home_show_areas = ! array_key_exists( 'home_show_areas', $settings )
	|| ! empty( $settings['home_show_areas'] );
$home_show_content = ! array_key_exists( 'home_show_content', $settings )
	|| ! empty( $settings['home_show_content'] );
$home_hero_title = sanitize_text_field(
	(string) (
		$settings['home_hero_title']
		?? 'Hôm nay khu vực của bạn có cúp điện không?'
	)
);

if ( in_array(
	$home_hero_title,
	array(
		'Chủ động theo dõi lịch cúp điện tại Lâm Đồng',
		'Chủ động theo dõi lịch điện tại Lâm Đồng mới nhất',
		'Chủ động theo dõi lịch điện mới nhất tại Lâm Đồng',
		'Chủ động theo dõi lịch điện tại Lâm Đồng',
		'Hôm nay khu vực của bạn có cúp điện không?',
		'Tra cứu lịch cúp điện Lâm Đồng',
	),
	true
) ) {
	$home_hero_title =
		'Cúp Điện Lâm Đồng';
}

$home_hero_description = sanitize_text_field(
	(string) (
		$settings['home_hero_description']
		?? 'Chọn ngày và đơn vị điện lực để kiểm tra lịch cúp điện chỉ trong vài giây.'
	)
);

if (
	'Tra cứu theo ngày và khu vực để chuẩn bị công việc, sinh hoạt và bảo vệ thiết bị điện.'
	=== $home_hero_description
	|| 'Chọn ngày và đơn vị điện lực để kiểm tra lịch cúp điện chỉ trong vài giây.'
	=== $home_hero_description
	|| 'Tra cứu lịch điện theo ngày và khu vực chỉ trong vài giây.'
	=== $home_hero_description
) {
	$home_hero_description =
		'Tin tức, việc làm, thời tiết, giá nông sản và tiện ích địa phương được cập nhật mỗi ngày.';
}
$home_seo_heading = sanitize_text_field(
	(string) (
		$settings['home_seo_heading']
		?? 'Cúp Điện Lâm Đồng — cổng thông tin và tiện ích địa phương'
	)
);
if (
	'Thông tin lịch điện tại Lâm Đồng dành cho cộng đồng'
	=== $home_seo_heading
	|| 'Cúp Điện Lâm Đồng — thông tin lịch điện cộng đồng'
	=== $home_seo_heading
) {
	$home_seo_heading =
		'Cúp Điện Lâm Đồng — cổng thông tin và tiện ích địa phương';
}
$home_seo_intro = sanitize_textarea_field(
	(string) (
		$settings['home_seo_intro']
		?? 'Cập nhật tin tức, việc làm, thời tiết, giá nông sản, kết quả xổ số, du lịch và lịch điện tại Lâm Đồng trong một bố cục thống nhất, dễ đọc trên điện thoại lẫn máy tính.'
	)
);
if (
	'Website tổng hợp lịch cúp điện đã được công bố theo từng ngày và đơn vị điện lực tại Lâm Đồng.'
	=== $home_seo_intro
	|| 'Website tổng hợp lịch cúp điện đã được công bố theo từng ngày và đơn vị điện lực tại Lâm Đồng. Người dân, hộ kinh doanh và doanh nghiệp có thể tra cứu khung giờ, khu vực ảnh hưởng và lý do dự kiến để chủ động sắp xếp sinh hoạt, công việc và bảo vệ thiết bị.'
	=== $home_seo_intro
) {
	$home_seo_intro =
		'Cập nhật tin tức, việc làm, thời tiết, giá nông sản, kết quả xổ số, du lịch và lịch điện tại Lâm Đồng trong một bố cục thống nhất, dễ đọc trên điện thoại lẫn máy tính.';
}
$home_seo_extra = sanitize_textarea_field(
	(string) ( $settings['home_seo_extra'] ?? '' )
);
if (
	''
	=== $home_seo_extra
	|| 'Dữ liệu trên website mang tính thông tin và có thể được đơn vị điện lực điều chỉnh theo tình hình vận hành thực tế. Hãy kiểm tra lại lịch gần thời điểm dự kiến và đối chiếu thông báo của đơn vị điện lực khi cần quyết định quan trọng.'
	=== $home_seo_extra
) {
	$home_seo_extra =
		'Cúp Điện Lâm Đồng là nền tảng thông tin cộng đồng độc lập. Mỗi chuyên mục được tổ chức theo nhu cầu hằng ngày và cần ghi rõ nguồn khi sử dụng dữ liệu từ đơn vị khác. Riêng lịch điện được tổng hợp từ thông tin đã công bố của EVN và đơn vị điện lực, không phải xác nhận cấp điện theo thời gian thực.';
}
$home_days = min( 31, max( 8, absint( $settings['home_days'] ?? 31 ) ) );
$home_recent_limit = min( 3, max( 1, absint( $settings['home_recent_limit'] ?? 3 ) ) );
$home_area_region = sanitize_key(
	(string) ( $settings['home_area_region'] ?? 'lam-dong' )
);
$home_area_columns = min(
	6,
	max( 1, absint( $settings['home_area_columns'] ?? 4 ) )
);
$home_area_initial = min(
	100,
	max( 0, absint( $settings['home_area_initial'] ?? 16 ) )
);
$home_area_theme = isset( $settings['home_area_theme'] )
	&& 'dark' === sanitize_key( (string) $settings['home_area_theme'] )
		? 'dark'
		: 'light';
$home_area_title = sanitize_text_field(
	(string) (
		$settings['home_area_title']
		?? 'Lịch điện theo khu vực tại Lâm Đồng hôm nay'
	)
);
if ( 'Xem lịch điện tại Lâm Đồng theo khu vực hôm nay' === $home_area_title ) {
	$home_area_title =
		'Lịch điện theo khu vực tại Lâm Đồng hôm nay';
}
$front_page_id = absint( get_option( 'page_on_front' ) );
$front_page = $front_page_id > 0 ? get_post( $front_page_id ) : null;
$front_page_content = $front_page instanceof WP_Post
	? (string) $front_page->post_content
	: '';
$home_seo_audit = array(
	array(
		'ok'    => $front_page instanceof WP_Post,
		'label' => __( 'Đã chọn một trang chủ tĩnh', 'power-schedule-manager' ),
		'tip'   => __( 'Chọn trang chủ tại Cài đặt → Đọc.', 'power-schedule-manager' ),
	),
	array(
		'ok'    => has_shortcode( $front_page_content, 'power_schedule_home' ),
		'label' => __( 'Trang chủ có shortcode [power_schedule_home]', 'power-schedule-manager' ),
		'tip'   => __( 'Chèn shortcode một lần vào nội dung trang chủ.', 'power-schedule-manager' ),
	),
	array(
		'ok'    => '' !== trim( $home_hero_title ),
		'label' => __( 'Hero có tiêu đề H1', 'power-schedule-manager' ),
		'tip'   => __( 'Điền tiêu đề H1 và tắt tiêu đề trang mặc định của theme để tránh hai H1.', 'power-schedule-manager' ),
	),
	array(
		'ok'    => mb_strlen( trim( $home_seo_intro ) ) >= 120,
		'label' => __( 'Đoạn giới thiệu SEO đủ thông tin', 'power-schedule-manager' ),
		'tip'   => __( 'Nên viết ít nhất 120 ký tự, tự nhiên và đúng nguồn.', 'power-schedule-manager' ),
	),
	array(
		'ok'    => '' !== (string) get_option( 'permalink_structure' ),
		'label' => __( 'Đường dẫn tĩnh thân thiện đang bật', 'power-schedule-manager' ),
		'tip'   => __( 'Không nên dùng cấu trúc đường dẫn mặc định dạng ?p=123.', 'power-schedule-manager' ),
	),
);
$home_area_description = sanitize_text_field(
	(string) (
		$settings['home_area_description']
		?? 'Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới.'
	)
);
$sponsor_title = sanitize_text_field(
	(string) (
		$settings['sponsor_title']
		?? 'Hợp tác cùng Cúp Điện Lâm Đồng'
	)
);
$sponsor_description = sanitize_textarea_field(
	(string) ( $settings['sponsor_description'] ?? '' )
);
$sponsor_email = sanitize_email(
	(string) ( $settings['sponsor_email'] ?? '' )
);
$sponsor_media_kit_url = esc_url_raw(
	(string) ( $settings['sponsor_media_kit_url'] ?? '' )
);

$telegram_enabled = ! empty( $settings['telegram_enabled'] );
$push_enabled = ! empty( $settings['push_enabled'] );
$push_onesignal_app_id =
	Power_Schedule_Manager_Assets::onesignal_app_id();
$push_button_label = sanitize_text_field(
	(string) (
		$settings['push_button_label']
		?? 'Nhận thông báo lịch cúp điện'
	)
);
$push_delivery_enabled = ! empty( $settings['push_delivery_enabled'] );
$onesignal_rest_api_key_encrypted = (string) (
	$settings['onesignal_rest_api_key_encrypted'] ?? ''
);
$onesignal_rest_api_key_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_ONESIGNAL_REST_API_KEY',
	$onesignal_rest_api_key_encrypted
);
$telegram_bot_token_encrypted = (string) (
	$settings['telegram_bot_token_encrypted'] ?? ''
);
$telegram_bot_token_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_TELEGRAM_BOT_TOKEN',
	$telegram_bot_token_encrypted
);
$webhook_secret_encrypted = (string) (
	$settings['webhook_secret_encrypted'] ?? ''
);
$webhook_secret_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_WEBHOOK_SECRET',
	$webhook_secret_encrypted
);
$zalo_access_token_encrypted = (string) (
	$settings['zalo_access_token_encrypted'] ?? ''
);
$zalo_access_token_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_ZALO_ACCESS_TOKEN',
	$zalo_access_token_encrypted
);
$push_notification_title = sanitize_text_field(
	(string) (
		$settings['push_notification_title']
		?? 'Cập nhật lịch cúp điện %unit_name%'
	)
);
$push_notification_message = sanitize_textarea_field(
	(string) (
		$settings['push_notification_message']
		?? 'Có %event_count% lịch cúp điện mới hoặc vừa cập nhật, áp dụng từ %date_from% đến %date_to%. Nhấn để xem chi tiết.'
	)
);
if ( 'Lịch cúp điện đã cập nhật: %unit_name%' === $push_notification_title ) {
	$push_notification_title = 'Cập nhật lịch cúp điện %unit_name%';
}
if ( 'Đã cập nhật %event_count% lịch cúp điện từ ngày %date_from% đến %date_to%. Nhấn để xem chi tiết.' === $push_notification_message ) {
	$push_notification_message = 'Có %event_count% lịch cúp điện mới hoặc vừa cập nhật, áp dụng từ %date_from% đến %date_to%. Nhấn để xem chi tiết.';
}
$lottery_push_delivery_enabled = ! empty(
	$settings['lottery_push_delivery_enabled']
);
$lottery_push_notification_title = sanitize_text_field(
	(string) (
		$settings['lottery_push_notification_title']
		?? 'Kết quả xổ số đã cập nhật: %lottery_name%'
	)
);
$lottery_push_notification_message = sanitize_textarea_field(
	(string) (
		$settings['lottery_push_notification_message']
		?? 'Đã cập nhật %draw_count% kết quả %lottery_name% ngày %date_from%. Nhấn để xem chi tiết.'
	)
);
$api_enabled = ! empty( $settings['api_enabled'] );
$api_require_authentication = ! empty(
	$settings['api_require_authentication']
);
$api_rate_limit = min(
	3000,
	max( 30, absint( $settings['api_rate_limit'] ?? 180 ) )
);
$pwa_enabled = ! empty( $settings['pwa_enabled'] );
$pwa_prompt_enabled = ! empty( $settings['pwa_prompt_enabled'] );
$pwa_service_worker_enabled = ! empty(
	$settings['pwa_service_worker_enabled']
);
$pwa_app_name = sanitize_text_field(
	(string) ( $settings['pwa_app_name'] ?? 'Cúp Điện Lâm Đồng' )
);
$pwa_short_name = sanitize_text_field(
	(string) ( $settings['pwa_short_name'] ?? 'Cúp điện LĐ' )
);
$pwa_description = sanitize_text_field(
	(string) (
		$settings['pwa_description']
		?? 'Tra cứu lịch điện tại Lâm Đồng theo ngày và khu vực.'
	)
);
$pwa_icon_url = esc_url_raw(
	(string) ( $settings['pwa_icon_url'] ?? '' )
);
$pwa_theme_color = sanitize_hex_color(
	(string) ( $settings['pwa_theme_color'] ?? '#075985' )
) ?: '#075985';
$pwa_background_color = sanitize_hex_color(
	(string) ( $settings['pwa_background_color'] ?? '#ffffff' )
) ?: '#ffffff';
$pwa_visit_threshold = min(
	10,
	max( 2, absint( $settings['pwa_visit_threshold'] ?? 3 ) )
);
$pwa_prompt_delay_seconds = min(
	30,
	max( 2, absint( $settings['pwa_prompt_delay_seconds'] ?? 8 ) )
);
$pwa_prompt_cooldown_days = min(
	365,
	max( 1, absint( $settings['pwa_prompt_cooldown_days'] ?? 30 ) )
);
$pwa_prompt_title = sanitize_text_field(
	(string) (
		$settings['pwa_prompt_title']
		?? 'Thêm Cúp Điện Lâm Đồng vào màn hình chính'
	)
);
$pwa_prompt_message = sanitize_text_field(
	(string) (
		$settings['pwa_prompt_message']
		?? 'Tra cứu nhanh hơn và nhận thông báo lịch cúp điện trên thiết bị này.'
	)
);
$site_icon_url = get_site_icon_url( 512 );
$telegram_chat_id = sanitize_text_field(
	(string) ( $settings['telegram_chat_id'] ?? '' )
);
$webhook_enabled = ! empty( $settings['webhook_enabled'] );
$webhook_url = esc_url_raw( (string) ( $settings['webhook_url'] ?? '' ) );
$zalo_enabled = ! empty( $settings['zalo_enabled'] );
$zalo_recipient_id = sanitize_text_field(
	(string) ( $settings['zalo_recipient_id'] ?? '' )
);
$cloudflare_enabled = ! empty( $settings['cloudflare_enabled'] );
$cloudflare_zone_id = sanitize_text_field(
	(string) ( $settings['cloudflare_zone_id'] ?? '' )
);
$cloudflare_api_token_encrypted = (string) (
	$settings['cloudflare_api_token_encrypted'] ?? ''
);
$cloudflare_token_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_CLOUDFLARE_API_TOKEN',
	$cloudflare_api_token_encrypted
);
$cloudflare_turnstile_enabled = ! empty(
	$settings['cloudflare_turnstile_enabled']
);
$cloudflare_turnstile_site_key = sanitize_text_field(
	(string) ( $settings['cloudflare_turnstile_site_key'] ?? '' )
);
$cloudflare_turnstile_secret_encrypted = (string) (
	$settings['cloudflare_turnstile_secret_encrypted'] ?? ''
);
$cloudflare_turnstile_secret_source = Power_Schedule_Manager_Secrets::source(
	'POWER_SCHEDULE_MANAGER_TURNSTILE_SECRET',
	$cloudflare_turnstile_secret_encrypted
);
$cloudflare_status = Power_Schedule_Manager_Cloudflare::status();

$option_name = POWER_SCHEDULE_MANAGER_SETTINGS_OPTION;

$place_library_url = add_query_arg(
	array(
		'page' => Power_Schedule_Manager_Place_Library::MENU_SLUG,
	),
	admin_url( 'admin.php' )
);

$public_unit_codes = array_values(
	array_filter(
		array_map(
			static fn ( array $unit ): string =>
				Power_Schedule_Manager_Units::sanitize_code(
					$unit['code'] ?? ''
				),
			Power_Schedule_Manager_Units::all( true )
		)
	)
);
?>

<div class="wrap psm-admin-wrap">
	<h1>
		<?php
		esc_html_e(
			'Cài đặt Cúp Điện Lâm Đồng',
			'power-schedule-manager'
		);
		?>
	</h1>

	<p class="psm-admin-lead">
		<?php
		esc_html_e(
			'Cấu hình bản đồ và cách hiển thị dữ liệu lịch điện.',
			'power-schedule-manager'
		);
		?>
	</p>

	<?php settings_errors(); ?>
	<?php
	$psm_notice = isset( $_GET['psm_notice'] ) && is_scalar( $_GET['psm_notice'] )
		? sanitize_key( wp_unslash( (string) $_GET['psm_notice'] ) )
		: '';
	if ( str_starts_with( $psm_notice, 'cloudflare_' ) ) :
		$notice_text = match ( $psm_notice ) {
			'cloudflare_processed' => __( 'Đã xóa cache cho một lô URL và tiếp tục giữ phần còn lại trong hàng đợi.', 'power-schedule-manager' ),
			'cloudflare_empty' => __( 'Hàng đợi Cloudflare hiện không có URL cần xử lý.', 'power-schedule-manager' ),
			default => __( 'Chưa xóa được URL. Plugin đã giữ nguyên hàng đợi và sẽ tự thử lại.', 'power-schedule-manager' ),
		};
		?>
		<div class="notice <?php echo 'cloudflare_retry' === $psm_notice ? 'notice-warning' : 'notice-success'; ?> inline"><p><?php echo esc_html( $notice_text ); ?></p></div>
	<?php endif; ?>

	<form
		id="psm-settings-form"
		method="post"
		action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>"
		class="psm-settings-form"
	>
		<?php
		settings_fields(
			'power_schedule_manager_settings'
		);
		?>
		<input type="hidden" name="<?php echo esc_attr( $option_name . '[api_enabled]' ); ?>" value="<?php echo $api_enabled ? '1' : '0'; ?>">
		<input type="hidden" name="<?php echo esc_attr( $option_name . '[api_require_authentication]' ); ?>" value="<?php echo $api_require_authentication ? '1' : '0'; ?>">
		<input type="hidden" name="<?php echo esc_attr( $option_name . '[api_rate_limit]' ); ?>" value="<?php echo esc_attr( (string) $api_rate_limit ); ?>">

		<nav class="psm-settings-tabs" aria-label="<?php esc_attr_e( 'Nhóm cài đặt', 'power-schedule-manager' ); ?>" data-psm-settings-tabs>
			<button type="button" class="is-active" data-psm-settings-tab="publishing"><?php esc_html_e( 'Xuất bản', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="advertising"><?php esc_html_e( 'Quảng cáo', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="storage"><?php esc_html_e( 'Lưu trữ', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="shortcodes"><?php esc_html_e( 'Shortcode', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="sponsorship"><?php esc_html_e( 'Hợp tác', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="notifications"><?php esc_html_e( 'Thông báo', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="pwa"><?php esc_html_e( 'Ứng dụng PWA', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="weather"><?php esc_html_e( 'Thời tiết', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="map"><?php esc_html_e( 'Bản đồ', 'power-schedule-manager' ); ?></button>
			<button type="button" data-psm-settings-tab="cdn"><?php esc_html_e( 'CDN & Cloudflare', 'power-schedule-manager' ); ?></button>
		</nav>

		<details class="psm-settings-guide">
			<summary><?php esc_html_e( 'Cách cấu hình theo đúng thứ tự vận hành', 'power-schedule-manager' ); ?></summary>
			<ol>
				<li><strong><?php esc_html_e( 'Xuất bản:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'xác nhận slug, số lượng và cách hiển thị lịch công khai trước.', 'power-schedule-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Shortcode:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'tạo Page, chọn đúng một Hero và chèn đúng shortcode dữ liệu ngay dưới Hero.', 'power-schedule-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Thời tiết và Bản đồ:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'chỉ bật lớp dữ liệu cần dùng; bản đồ để lazy-load và không đặt trong Hero.', 'power-schedule-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Hợp tác và Quảng cáo:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'cấu hình email tiếp nhận, nhận diện minh bạch và vị trí không che dữ liệu.', 'power-schedule-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Thông báo và PWA:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'chỉ bật sau khi URL HTTPS, icon và khóa dịch vụ đã hoàn chỉnh.', 'power-schedule-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Lưu trữ, CDN và Cloudflare:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'thiết lập cuối cùng, sau đó kiểm tra cron, cache và Trạng thái hệ thống.', 'power-schedule-manager' ); ?></li>
			</ol>
		</details>

		<section class="psm-dashboard-panel" data-psm-settings-panel="publishing">
			<h2><?php esc_html_e( 'Xuất bản và hiển thị', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Thiết lập cách lịch nhập từ nguồn được xuất bản và nội dung lưu ý ngoài website.', 'power-schedule-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="psm-import-post-status"><?php esc_html_e( 'Sau khi xác nhận nhập', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<select
								id="psm-import-post-status"
								name="<?php echo esc_attr( $option_name . '[import_post_status]' ); ?>"
							>
								<option value="publish" <?php selected( $import_post_status, 'publish' ); ?>><?php esc_html_e( 'Xuất bản ngay', 'power-schedule-manager' ); ?></option>
								<option value="draft" <?php selected( $import_post_status, 'draft' ); ?>><?php esc_html_e( 'Lưu bản nháp để duyệt', 'power-schedule-manager' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Áp dụng sau khi dữ liệu nguồn đã vượt qua bước kiểm tra, xem trước và xác nhận nhập.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="psm-disclaimer-text"><?php esc_html_e( 'Lưu ý ngoài website', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<textarea
								id="psm-disclaimer-text"
								name="<?php echo esc_attr( $option_name . '[disclaimer_text]' ); ?>"
								class="large-text"
								rows="4"
								maxlength="1600"
								placeholder="<?php esc_attr_e( 'Để trống để sử dụng nội dung mặc định của plugin.', 'power-schedule-manager' ); ?>"
							><?php echo esc_textarea( $disclaimer_text ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Tối đa 1.600 ký tự. Để trống để dùng lưu ý mặc định đầy đủ cùng kênh liên hệ EVNSPC.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="advertising">
			<h2><?php esc_html_e( 'Quảng cáo và tài trợ frontend', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Các trang lịch dùng banner ngang tách biệt khỏi công cụ tra cứu và nội dung chính. Mỗi vị trí được gắn nhãn “Quảng cáo”; chỉ nhận shortcode từ plugin quảng cáo đã cài hoặc HTML an toàn, không lưu script trực tiếp.', 'power-schedule-manager' ); ?></p>
			<label class="psm-checkbox-card">
				<input type="checkbox" name="<?php echo esc_attr( $option_name . '[ads_enabled]' ); ?>" value="1" <?php checked( $ads_enabled ); ?>>
				<span><strong><?php esc_html_e( 'Cho phép hiển thị quảng cáo', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Tắt để ẩn đồng thời mọi banner trên trang chủ, trang danh sách và trang chi tiết; nội dung cấu hình vẫn được giữ lại.', 'power-schedule-manager' ); ?></small></span>
			</label>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="psm-home-top-banner-ad"><?php esc_html_e( 'Banner đầu trang chủ', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<textarea
								id="psm-home-top-banner-ad"
								name="<?php echo esc_attr( $option_name . '[home_top_banner_ad]' ); ?>"
								class="large-text code"
								rows="5"
								maxlength="5000"
								placeholder="<?php esc_attr_e( 'Ví dụ: [ad_banner id="trang-chu-tren"] hoặc HTML banner ngang an toàn.', 'power-schedule-manager' ); ?>"
							><?php echo esc_textarea( $home_top_banner_ad ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Hiển thị sau Hero và công cụ tra cứu, trước phần tình hình lịch điện. Banner luôn rộng theo khối nội dung trang chủ.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="psm-home-bottom-banner-ad"><?php esc_html_e( 'Banner cuối trang chủ', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<textarea
								id="psm-home-bottom-banner-ad"
								name="<?php echo esc_attr( $option_name . '[home_bottom_banner_ad]' ); ?>"
								class="large-text code"
								rows="5"
								maxlength="5000"
								placeholder="<?php esc_attr_e( 'Ví dụ: [ad_banner id="trang-chu-duoi"] hoặc HTML banner ngang an toàn.', 'power-schedule-manager' ); ?>"
							><?php echo esc_textarea( $home_bottom_banner_ad ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Hiển thị trước nội dung hướng dẫn và SEO ở cuối trang chủ. Có thể để trống một trong hai vị trí.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="psm-archive-banner-ad"><?php esc_html_e( 'Banner trên trang /lich-cup-dien', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<textarea
								id="psm-archive-banner-ad"
								name="<?php echo esc_attr( $option_name . '[archive_banner_ad]' ); ?>"
								class="large-text code"
								rows="5"
								maxlength="5000"
								placeholder="<?php esc_attr_e( 'Ví dụ: [ad_banner id=\"lich-cup-dien-tren\"] hoặc HTML banner ngang an toàn.', 'power-schedule-manager' ); ?>"
							><?php echo esc_textarea( $archive_banner_ad ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Khuyến nghị cao 80–100px trên desktop, tự co giãn trên mobile. Vị trí này nằm giữa form tra cứu và bảng lịch.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="psm-archive-bottom-banner-ad"><?php esc_html_e( 'Banner dưới trang /lich-cup-dien', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<textarea
								id="psm-archive-bottom-banner-ad"
								name="<?php echo esc_attr( $option_name . '[archive_bottom_banner_ad]' ); ?>"
								class="large-text code"
								rows="5"
								maxlength="5000"
								placeholder="<?php esc_attr_e( 'Ví dụ: [ad_banner id=\"lich-cup-dien-duoi\"] hoặc HTML banner ngang an toàn.', 'power-schedule-manager' ); ?>"
							><?php echo esc_textarea( $archive_bottom_banner_ad ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Hiển thị sau bảng lịch và trước phần lưu ý, phù hợp banner tài trợ hoặc thông tin liên hệ.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="psm-single-top-banner-ad"><?php esc_html_e( 'Banner trên trang chi tiết lịch', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<textarea
								id="psm-single-top-banner-ad"
								name="<?php echo esc_attr( $option_name . '[single_top_banner_ad]' ); ?>"
								class="large-text code"
								rows="5"
								maxlength="5000"
								placeholder="<?php esc_attr_e( 'Ví dụ: [ad_banner id=\"chi-tiet-tren\"] hoặc HTML banner ngang an toàn.', 'power-schedule-manager' ); ?>"
							><?php echo esc_textarea( $single_top_banner_ad ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Hiển thị ngay dưới phần tiêu đề trang chi tiết, rộng theo nội dung chính và không làm co bảng lịch.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="psm-single-bottom-banner-ad"><?php esc_html_e( 'Banner dưới trang chi tiết lịch', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<textarea
								id="psm-single-bottom-banner-ad"
								name="<?php echo esc_attr( $option_name . '[single_bottom_banner_ad]' ); ?>"
								class="large-text code"
								rows="5"
								maxlength="5000"
								placeholder="<?php esc_attr_e( 'Ví dụ: [ad_banner id=\"chi-tiet-duoi\"] hoặc HTML banner ngang an toàn.', 'power-schedule-manager' ); ?>"
							><?php echo esc_textarea( $single_bottom_banner_ad ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Hiển thị sau bảng lịch chi tiết, phù hợp banner tài trợ hoặc ghi chú cộng đồng.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Google AdSense và trải nghiệm người dùng', 'power-schedule-manager' ); ?></strong></p>
				<p><?php esc_html_e( 'Chiến lược khuyến nghị: dùng Site Kit hoặc đoạn mã AdSense toàn website cho Auto ads; các ô của plugin chỉ dùng khi cần kiểm soát vị trí bằng shortcode ad unit responsive. Không dán thẳng mã script AdSense vào các ô này. Khi dùng cả Auto ads và ad unit thủ công, kiểm tra mục Existing ads trong AdSense để tránh chồng vị trí.', 'power-schedule-manager' ); ?></p>
				<p><?php esc_html_e( 'Ưu tiên một banner responsive sau nội dung chính hoặc cuối trang. Không đặt quảng cáo sát ô tìm kiếm, nút Tra cứu, bộ lọc, bản đồ hay biểu mẫu hợp tác; không dùng mũi tên, CTA hoặc nhãn nhằm khuyến khích nhấp quảng cáo.', 'power-schedule-manager' ); ?></p>
				<p><?php esc_html_e( 'Quảng cáo Google chỉ được ghi nhãn “Quảng cáo” hoặc “Liên kết được tài trợ”. Banner bán trực tiếp cần nhãn “Tài trợ” do shortcode/banner đó tự hiển thị.', 'power-schedule-manager' ); ?></p>
			</div>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="storage">
			<h2><?php esc_html_e( 'Dữ liệu và lưu trữ', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Giới hạn thời gian lưu dữ liệu giúp database không tăng liên tục. Cleanup chạy theo lịch và xóa theo từng lô nhỏ.', 'power-schedule-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="psm-raw-retention"><?php esc_html_e( 'Dữ liệu nguồn thô', 'power-schedule-manager' ); ?></label></th>
						<td><input id="psm-raw-retention" type="number" min="7" max="90" name="<?php echo esc_attr( $option_name . '[raw_payload_retention_days]' ); ?>" value="<?php echo esc_attr( (string) $raw_payload_retention_days ); ?>"> <?php esc_html_e( 'ngày', 'power-schedule-manager' ); ?>
						<p class="description"><?php esc_html_e( 'Chỉ xóa nội dung payload; số liệu kiểm toán của lần nhập vẫn được giữ.', 'power-schedule-manager' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-log-retention"><?php esc_html_e( 'Lịch sử nhập dữ liệu', 'power-schedule-manager' ); ?></label></th>
						<td><input id="psm-log-retention" type="number" min="3" max="36" name="<?php echo esc_attr( $option_name . '[import_log_retention_months]' ); ?>" value="<?php echo esc_attr( (string) $import_log_retention_months ); ?>"> <?php esc_html_e( 'tháng', 'power-schedule-manager' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-completed-retention"><?php esc_html_e( 'Lịch đã hoàn tất', 'power-schedule-manager' ); ?></label></th>
						<td><input id="psm-completed-retention" type="number" min="12" max="60" name="<?php echo esc_attr( $option_name . '[completed_retention_months]' ); ?>" value="<?php echo esc_attr( (string) $completed_retention_months ); ?>"> <?php esc_html_e( 'tháng', 'power-schedule-manager' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-cancelled-retention"><?php esc_html_e( 'Lịch hủy hoặc bị gỡ', 'power-schedule-manager' ); ?></label></th>
						<td><input id="psm-cancelled-retention" type="number" min="3" max="36" name="<?php echo esc_attr( $option_name . '[cancelled_retention_months]' ); ?>" value="<?php echo esc_attr( (string) $cancelled_retention_months ); ?>"> <?php esc_html_e( 'tháng', 'power-schedule-manager' ); ?></td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="sponsorship">
			<h2><?php esc_html_e( 'Hợp tác', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Chỉ quản lý kênh hợp tác và biểu mẫu [power_schedule_sponsor]. Plugin không thu tiền, không hiển thị MoMo, QR hoặc số tài khoản.', 'power-schedule-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="psm-sponsor-title"><?php esc_html_e( 'Nội dung tài trợ', 'power-schedule-manager' ); ?></label></th>
						<td>
							<input id="psm-sponsor-title" class="large-text" type="text" maxlength="160" name="<?php echo esc_attr( $option_name . '[sponsor_title]' ); ?>" value="<?php echo esc_attr( $sponsor_title ); ?>">
							<textarea class="large-text" rows="4" maxlength="800" name="<?php echo esc_attr( $option_name . '[sponsor_description]' ); ?>"><?php echo esc_textarea( $sponsor_description ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Liên hệ tài trợ', 'power-schedule-manager' ); ?></th>
						<td class="psm-settings-inline-fields">
							<label><span><?php esc_html_e( 'Email', 'power-schedule-manager' ); ?></span><input type="email" maxlength="191" name="<?php echo esc_attr( $option_name . '[sponsor_email]' ); ?>" value="<?php echo esc_attr( $sponsor_email ); ?>"></label>
							<label><span><?php esc_html_e( 'URL hồ sơ tài trợ HTTPS', 'power-schedule-manager' ); ?></span><input type="url" name="<?php echo esc_attr( $option_name . '[sponsor_media_kit_url]' ); ?>" value="<?php echo esc_attr( $sponsor_media_kit_url ); ?>"></label>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="notice notice-info inline">
				<p><strong>[power_schedule_sponsor]</strong> — <?php esc_html_e( 'chèn vào page “Hợp tác”. Form chỉ tiếp nhận đề nghị để quản trị viên xác minh và liên hệ lại; không hỏi hay nhận số tiền.', 'power-schedule-manager' ); ?></p>
				<p><?php esc_html_e( 'Đề nghị từ doanh nghiệp được lưu riêng trong menu Hợp tác tài trợ và gửi thông báo tới email liên hệ tài trợ; quản trị viên cập nhật trạng thái Mới nhận, Đã liên hệ hoặc Đã kết thúc.', 'power-schedule-manager' ); ?></p>
			</div>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="shortcodes">
			<h2><?php esc_html_e( 'Khối hiển thị tái sử dụng', 'power-schedule-manager' ); ?></h2>
			<p>
				<?php esc_html_e( 'Sao chép mẫu phù hợp vào trang chủ, trang nội dung hoặc widget shortcode. Danh sách khu vực lấy trực tiếp từ database nên tự mở rộng khi có thêm đơn vị công khai.', 'power-schedule-manager' ); ?>
			</p>

			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Hero chuẩn Blocksy:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'chọn Plugin quản lý Hero để hệ thống tự tắt Page Title Blocksy, hoặc chọn Shortcode nếu muốn tự đặt đúng một hero trong nội dung.', 'power-schedule-manager' ); ?></p>
				<p><code>[power_schedule_page_hero variant="schedule"]</code> · <code>gold</code> · <code>coffee</code> · <code>lottery</code> · <code>lottery_lookup</code> · <code>weather</code> · <code>participate</code> · <code>sponsor</code></p>
				<p><code>eyebrow</code> · <code>title</code> · <code>description</code> · <code>cta_label</code> · <code>cta_url</code> · <code>show_breadcrumb</code> · <code>breadcrumb_parent_label</code> · <code>breadcrumb_parent_url</code> · <code>meta_1_label/value/detail</code> đến <code>meta_3_label/value/detail</code>.</p>
				<p><?php esc_html_e( 'Các bảng/shortcode dữ liệu đặt ngay sau hero và chỉ dùng H2/H3. CTA lịch điện dùng #psm-archive-search hoặc #psm-schedule-details; xổ số dùng #ket-qua; tra cứu xổ số dùng #tra-cuu; thời tiết dùng #du-bao.', 'power-schedule-manager' ); ?></p>
				<p><strong><?php esc_html_e( 'Quy tắc một H1:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'nếu Page chọn Plugin tự chèn Hero thì không đặt shortcode Hero trong nội dung; nếu chọn Shortcode thì đặt đúng một power_schedule_page_hero ở đầu Page.', 'power-schedule-manager' ); ?></p>
			</div>

			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Nội dung biên tập:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'mở Cúp Điện Lâm Đồng → Khối nội dung để thêm, sửa, khôi phục revision và lấy shortcode chèn vào bất kỳ Page nào. Hệ thống tự hạ H1 trong khối thành H2 để bảo vệ cấu trúc SEO.', 'power-schedule-manager' ); ?></p>
			</div>

			<div class="psm-home-shortcode-settings">
				<h3><?php esc_html_e( 'Cổng thông tin trang chủ', 'power-schedule-manager' ); ?></h3>
				<p>
					<?php esc_html_e( 'Chèn [power_schedule_home] một lần vào trang chủ. Phiên bản 0.35.7 mở đầu bằng tìm kiếm toàn website, lối tắt tiện ích và các dòng tin địa phương; lịch điện được chuyển xuống thành một tiện ích thiết yếu.', 'power-schedule-manager' ); ?>
				</p>
				<div class="notice notice-info inline">
					<p><strong><?php esc_html_e( 'Chuẩn bị nội dung:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'tạo ba chuyên mục với slug tin-tuc, viec-lam và du-lich. Trang chủ tự lấy tối đa bốn bài mới nhất của mỗi chuyên mục.', 'power-schedule-manager' ); ?></p>
					<p><?php esc_html_e( 'Các lối tắt mặc định trỏ tới /viec-lam/, /gia-ca-phe-hom-nay/, /thoi-tiet-lam-dong/, /tin-tuc/, /ket-qua-xo-so-hom-nay/ và /du-lich/. Hãy tạo các trang tương ứng hoặc thiết lập chuyển hướng phù hợp.', 'power-schedule-manager' ); ?></p>
				</div>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Thành phần hiển thị', 'power-schedule-manager' ); ?></th>
							<td>
								<fieldset class="psm-home-section-toggles">
									<?php
									$home_section_options = array(
										'home_show_hero'   => array( $home_show_hero, 'Mở đầu cổng thông tin' ),
										'home_show_search' => array( $home_show_search, 'Tra cứu lịch điện dự phòng' ),
										'home_show_alert'  => array( $home_show_alert, 'Bảng lịch điện tổng hợp' ),
										'home_show_days'   => array( $home_show_days, 'Ngày có lịch' ),
										'home_show_areas'  => array( $home_show_areas, 'Khu vực điện lực' ),
										'home_show_content' => array( $home_show_content, 'Giới thiệu, nguồn và FAQ' ),
									);

									foreach ( $home_section_options as $setting_key => $section_option ) :
										?>
										<label>
											<input
												type="hidden"
												name="<?php echo esc_attr( $option_name . '[' . $setting_key . ']' ); ?>"
												value="0"
											>
											<input
												type="checkbox"
												name="<?php echo esc_attr( $option_name . '[' . $setting_key . ']' ); ?>"
												value="1"
												<?php checked( (bool) $section_option[0] ); ?>
											>
											<?php echo esc_html( (string) $section_option[1] ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Mở đầu cổng thông tin', 'power-schedule-manager' ); ?></th>
								<td>
									<label for="psm-home-hero-title"><?php esc_html_e( 'Tiêu đề H1', 'power-schedule-manager' ); ?></label>
									<input id="psm-home-hero-title" type="text" class="large-text" maxlength="160" name="<?php echo esc_attr( $option_name . '[home_hero_title]' ); ?>" value="<?php echo esc_attr( $home_hero_title ); ?>">
									<label for="psm-home-hero-description"><?php esc_html_e( 'Mô tả ngắn', 'power-schedule-manager' ); ?></label>
									<input id="psm-home-hero-description" type="text" class="large-text" maxlength="260" name="<?php echo esc_attr( $option_name . '[home_hero_description]' ); ?>" value="<?php echo esc_attr( $home_hero_description ); ?>">
									<p class="description"><?php esc_html_e( 'Hero dùng H1 và luôn có tìm kiếm toàn website. Hãy tắt tiêu đề mặc định của trang chủ trong giao diện để toàn trang chỉ có một H1.', 'power-schedule-manager' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Giới thiệu và minh bạch nguồn cuối trang', 'power-schedule-manager' ); ?></th>
								<td>
									<label for="psm-home-seo-heading"><?php esc_html_e( 'Tiêu đề H2', 'power-schedule-manager' ); ?></label>
									<input id="psm-home-seo-heading" type="text" class="large-text" maxlength="160" name="<?php echo esc_attr( $option_name . '[home_seo_heading]' ); ?>" value="<?php echo esc_attr( $home_seo_heading ); ?>">
									<label for="psm-home-seo-intro"><?php esc_html_e( 'Đoạn giới thiệu', 'power-schedule-manager' ); ?></label>
									<textarea id="psm-home-seo-intro" class="large-text" rows="5" maxlength="1200" name="<?php echo esc_attr( $option_name . '[home_seo_intro]' ); ?>"><?php echo esc_textarea( $home_seo_intro ); ?></textarea>
									<label for="psm-home-seo-extra"><?php esc_html_e( 'Thông tin bổ sung', 'power-schedule-manager' ); ?></label>
									<textarea id="psm-home-seo-extra" class="large-text" rows="7" maxlength="4000" name="<?php echo esc_attr( $option_name . '[home_seo_extra]' ); ?>"><?php echo esc_textarea( $home_seo_extra ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Viết tự nhiên, hữu ích và đúng nguồn. Không lặp từ khóa hoặc tuyên bố Cúp Điện Lâm Đồng là cổng thông tin chính thức.', 'power-schedule-manager' ); ?></p>
									<div class="psm-seo-audit" aria-label="<?php esc_attr_e( 'Kiểm tra SEO trang chủ', 'power-schedule-manager' ); ?>">
										<h3><?php esc_html_e( 'Kiểm tra SEO trang chủ', 'power-schedule-manager' ); ?></h3>
										<ul>
											<?php foreach ( $home_seo_audit as $audit_item ) : ?>
												<li class="<?php echo ! empty( $audit_item['ok'] ) ? 'is-ok' : 'is-warning'; ?>">
													<span aria-hidden="true"><?php echo ! empty( $audit_item['ok'] ) ? '✓' : '!'; ?></span>
													<div>
														<strong><?php echo esc_html( (string) $audit_item['label'] ); ?></strong>
														<?php if ( empty( $audit_item['ok'] ) ) : ?>
															<small><?php echo esc_html( (string) $audit_item['tip'] ); ?></small>
														<?php endif; ?>
													</div>
												</li>
											<?php endforeach; ?>
										</ul>
										<p class="description"><?php esc_html_e( 'Đây là kiểm tra cấu hình nền. Sau khi lưu, hãy xem mã nguồn trang để xác nhận toàn trang chỉ có một H1 và công cụ SEO không tạo title/meta trùng.', 'power-schedule-manager' ); ?></p>
									</div>
								</td>
							</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Giới hạn dữ liệu', 'power-schedule-manager' ); ?></th>
							<td class="psm-settings-inline-fields">
								<label for="psm-home-days">
									<span><?php esc_html_e( 'Số ngày sắp tới', 'power-schedule-manager' ); ?></span>
									<input
										id="psm-home-days"
										type="number"
										name="<?php echo esc_attr( $option_name . '[home_days]' ); ?>"
										value="<?php echo esc_attr( (string) $home_days ); ?>"
										min="8"
										max="31"
									>
									<small><?php esc_html_e( 'Plugin quét trong khoảng này và chỉ hiển thị tối đa 8 ngày có lịch.', 'power-schedule-manager' ); ?></small>
								</label>
								<label for="psm-home-recent-limit">
									<span><?php esc_html_e( 'Số lịch vừa cập nhật', 'power-schedule-manager' ); ?></span>
									<input
										id="psm-home-recent-limit"
										type="number"
										name="<?php echo esc_attr( $option_name . '[home_recent_limit]' ); ?>"
										value="<?php echo esc_attr( (string) $home_recent_limit ); ?>"
										min="1"
										max="3"
									>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Danh sách khu vực', 'power-schedule-manager' ); ?></th>
							<td class="psm-settings-inline-fields">
								<label for="psm-home-area-region">
									<span><?php esc_html_e( 'Mã vùng', 'power-schedule-manager' ); ?></span>
									<input
										id="psm-home-area-region"
										type="text"
										name="<?php echo esc_attr( $option_name . '[home_area_region]' ); ?>"
										value="<?php echo esc_attr( $home_area_region ); ?>"
										maxlength="60"
									>
								</label>
								<label for="psm-home-area-columns">
									<span><?php esc_html_e( 'Số cột desktop', 'power-schedule-manager' ); ?></span>
									<select
										id="psm-home-area-columns"
										name="<?php echo esc_attr( $option_name . '[home_area_columns]' ); ?>"
									>
										<?php for ( $column = 1; $column <= 6; $column++ ) : ?>
											<option value="<?php echo esc_attr( (string) $column ); ?>" <?php selected( $home_area_columns, $column ); ?>>
												<?php echo esc_html( (string) $column ); ?>
											</option>
										<?php endfor; ?>
									</select>
								</label>
									<label for="psm-home-area-theme">
									<span><?php esc_html_e( 'Giao diện', 'power-schedule-manager' ); ?></span>
									<select
										id="psm-home-area-theme"
										name="<?php echo esc_attr( $option_name . '[home_area_theme]' ); ?>"
									>
										<option value="light" <?php selected( $home_area_theme, 'light' ); ?>><?php esc_html_e( 'Sáng', 'power-schedule-manager' ); ?></option>
										<option value="dark" <?php selected( $home_area_theme, 'dark' ); ?>><?php esc_html_e( 'Tối', 'power-schedule-manager' ); ?></option>
										</select>
									</label>
									<label for="psm-home-area-initial">
										<span><?php esc_html_e( 'Hiện ban đầu', 'power-schedule-manager' ); ?></span>
										<input
											id="psm-home-area-initial"
											type="number"
											name="<?php echo esc_attr( $option_name . '[home_area_initial]' ); ?>"
											value="<?php echo esc_attr( (string) $home_area_initial ); ?>"
											min="0"
											max="100"
										>
										<small><?php esc_html_e( '0 = hiện tất cả', 'power-schedule-manager' ); ?></small>
									</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="psm-home-area-title"><?php esc_html_e( 'Tiêu đề khu vực', 'power-schedule-manager' ); ?></label>
							</th>
							<td>
								<input
									id="psm-home-area-title"
									type="text"
									class="large-text"
									name="<?php echo esc_attr( $option_name . '[home_area_title]' ); ?>"
									value="<?php echo esc_attr( $home_area_title ); ?>"
									maxlength="160"
								>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="psm-home-area-description"><?php esc_html_e( 'Mô tả khu vực', 'power-schedule-manager' ); ?></label>
							</th>
							<td>
								<input
									id="psm-home-area-description"
									type="text"
									class="large-text"
									name="<?php echo esc_attr( $option_name . '[home_area_description]' ); ?>"
									value="<?php echo esc_attr( $home_area_description ); ?>"
									maxlength="240"
								>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="psm-shortcode-grid">
				<?php
				$shortcode_examples = array(
					array(
						'title' => __( 'Trang chủ tổng hợp', 'power-schedule-manager' ),
						'code'  => '[power_schedule_home]',
						'note'  => __( 'Dùng cấu hình phía trên để hiển thị toàn bộ luồng tra cứu trang chủ bằng một shortcode.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Trang hợp tác tài trợ', 'power-schedule-manager' ),
						'code'  => '[power_schedule_sponsor]',
						'note'  => __( 'Trang độc lập để doanh nghiệp chọn vị trí, thời gian và gửi nhu cầu cho quản trị viên; không nhập số tiền.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Giá cà phê Lâm Đồng', 'power-schedule-manager' ),
						'code'  => '[power_schedule_coffee_lam_dong]',
						'note'  => __( 'Gắn vào Page giá cà phê Lâm Đồng; dữ liệu quản lý trong menu Giá thị trường.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Giá hồ tiêu', 'power-schedule-manager' ),
						'code'  => '[power_schedule_pepper_prices]',
						'note'  => __( 'Hiển thị bảng giá hồ tiêu theo vùng và ngày cập nhật.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Tỷ giá USD/VND', 'power-schedule-manager' ),
						'code'  => '[power_schedule_usd_vnd]',
						'note'  => __( 'Hiển thị tỷ giá tham chiếu để đối chiếu thị trường quốc tế.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Giá cà phê trong nước', 'power-schedule-manager' ),
						'code'  => '[power_schedule_coffee_dak_lak]
[power_schedule_coffee_gia_lai]
[power_schedule_coffee_dak_nong]',
						'note'  => __( 'Mỗi vùng dùng một shortcode và một bảng riêng.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Giá cà phê thế giới', 'power-schedule-manager' ),
						'code'  => '[power_schedule_coffee_robusta]
[power_schedule_coffee_arabica]',
						'note'  => __( 'Tách riêng dữ liệu Robusta và Arabica tự động.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Trang giá cà phê tổng hợp', 'power-schedule-manager' ),
						'code'  => '[power_schedule_coffee_overview]',
						'note'  => __( 'Shortcode khuyên dùng: tóm tắt Lâm Đồng, bảng trong nước, cà phê thế giới và tỷ giá trong một bố cục.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Giá vàng trong nước', 'power-schedule-manager' ),
						'code'  => '[power_schedule_gold_domestic]',
						'note'  => __( 'Một bảng trong nước theo nguồn vàng đã chọn trong quản trị.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Vàng thế giới và tỷ giá riêng', 'power-schedule-manager' ),
						'code'  => '[power_schedule_gold_api]
[power_schedule_exchange_rates]',
						'note'  => __( 'Chỉ dùng khi cần đặt riêng XAU/USD hoặc USD/VND; trang chính nên dùng shortcode tổng hợp.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Trang giá vàng tổng hợp', 'power-schedule-manager' ),
						'code'  => '[power_schedule_gold_overview]',
						'note'  => __( 'Shortcode khuyên dùng: chỉ lấy nguồn đã chọn trong admin, gộp SJC/DOJI/PNJ vào một bảng và tách giá thế giới rõ ràng.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Trang xổ số tổng hợp', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery_results history_limit="10"]',
						'note'  => __( 'Ba miền, Vietlott, Điện toán, bộ chọn ngày và tối thiểu 10 kỳ trước. Bảng Đặc Biệt Tuần dùng shortcode riêng.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Công cụ tra cứu xổ số cũ', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery_archive]',
						'note'  => __( 'Form chọn sản phẩm và ngày; nên đặt ngay sau Hero lottery_lookup.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Lịch sử Vietlott và Điện toán', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery_mega645_history limit="10"]
[power_schedule_lottery_power655_history limit="10"]
[power_schedule_lottery_max3d_plus_history limit="10"]
[power_schedule_lottery_max3d_pro_history limit="10"]
[power_schedule_lottery_dientoan123_history limit="10"]
[power_schedule_lottery_dientoan6x36_history limit="10"]
[power_schedule_lottery_thantai_history limit="10"]',
						'note'  => __( 'Mỗi shortcode lịch sử dùng cho đúng page sản phẩm; không chèn tất cả vào trang tổng hợp.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Xổ số ba miền', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery_north]
[power_schedule_lottery_central]
[power_schedule_lottery_south]',
						'note'  => __( 'Mỗi miền có bảng kết quả responsive riêng; dữ liệu được đồng bộ phía máy chủ.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Vietlott', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery_mega645]
[power_schedule_lottery_power655]
[power_schedule_lottery_max3d]
[power_schedule_lottery_max3d_plus]
[power_schedule_lottery_max3d_pro]',
						'note'  => __( 'Dùng đúng shortcode của sản phẩm để có bố cục, bóng số và giải thưởng phù hợp.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Keno', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery_keno]',
						'note'  => __( 'Hiển thị kỳ Keno mới nhất; lịch sử đã nằm trong bảng tổng hợp của trang xổ số.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Xổ số điện toán', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery_dientoan]
[power_schedule_lottery_dientoan123]
[power_schedule_lottery_dientoan6x36]
[power_schedule_lottery_thantai]',
						'note'  => __( 'Có bản tổng hợp và shortcode riêng cho từng sản phẩm điện toán.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Xổ số tùy chọn', 'power-schedule-manager' ),
						'code'  => '[power_schedule_lottery province="Lâm Đồng" limit="3"]',
						'note'  => __( 'Dùng khi cần giới hạn theo tỉnh hoặc số kỳ; cấu hình API tại menu Kết quả xổ số.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Lịch gần nhất', 'power-schedule-manager' ),
						'code'  => '[power_schedule_next]',
						'note'  => __( 'Hiển thị một lịch đang diễn ra hoặc sắp tới gần nhất.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Cảnh báo trạng thái', 'power-schedule-manager' ),
						'code'  => '[power_schedule_alert unit="PB0301"]',
						'note'  => __( 'Thông báo lịch đang diễn ra hoặc thời điểm bắt đầu tiếp theo.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Các ngày có lịch', 'power-schedule-manager' ),
						'code'  => '[power_schedule_days days="7" show_count="yes"]',
						'note'  => __( 'Chỉ hiện ngày thực sự có dữ liệu để điều hướng nhanh.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Lịch vừa cập nhật', 'power-schedule-manager' ),
						'code'  => '[power_schedule_recent_updates limit="5"]',
						'note'  => __( 'Hiển thị các lịch công khai mới được cập nhật gần đây.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Tất cả đơn vị công khai', 'power-schedule-manager' ),
						'code'  => '[power_schedule_areas title="Xem lịch điện tại Lâm Đồng theo khu vực hôm nay" description="Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới." columns="4"]',
						'note'  => __( 'Tự hiển thị toàn bộ đơn vị công khai hiện có, không cố định số lượng.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Một vùng cụ thể', 'power-schedule-manager' ),
						'code'  => '[power_schedule_areas region="lam-dong" title="Xem lịch điện tại Lâm Đồng theo khu vực hôm nay" description="Chọn khu vực để xem lịch đang diễn ra và các ngày sắp tới." columns="4" theme="dark"]',
						'note'  => __( 'Lọc theo vùng và tự thích ứng 4/2/1 cột.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Chọn và sắp thứ tự thủ công', 'power-schedule-manager' ),
						'code'  => '[power_schedule_areas units="PB0301,PB0302,PB0305,PB0304" columns="4"]',
						'note'  => __( 'Các thẻ xuất hiện theo đúng thứ tự mã đã nhập.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Loại trừ đơn vị', 'power-schedule-manager' ),
						'code'  => '[power_schedule_areas exclude="PB0211,PC13ZZ" sort="name" order="asc"]',
						'note'  => __( 'Phù hợp khi dùng danh sách toàn hệ thống nhưng cần ẩn một số đơn vị.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Tên đầy đủ và mã đơn vị', 'power-schedule-manager' ),
						'code'  => '[power_schedule_areas region="lam-dong" label="full" show_code="yes" columns="3"]',
						'note'  => __( 'Hữu ích cho trang quản trị nội bộ hoặc nội dung cần đối chiếu mã.', 'power-schedule-manager' ),
					),
					array(
						'title' => __( 'Lịch và biểu mẫu tra cứu', 'power-schedule-manager' ),
						'code'  => '[power_schedule_search]' . "\n" . '[power_schedule date="today"]',
						'note'  => __( 'Có thể đặt biểu mẫu tra cứu và danh sách lịch ở hai block riêng.', 'power-schedule-manager' ),
					),
				);

				foreach ( $shortcode_examples as $index => $example ) :
					$field_id = 'psm-shortcode-example-' . ( $index + 1 );
					?>
					<article class="psm-shortcode-card">
						<h3><?php echo esc_html( $example['title'] ); ?></h3>
						<p><?php echo esc_html( $example['note'] ); ?></p>
						<textarea
							id="<?php echo esc_attr( $field_id ); ?>"
							class="large-text code"
							rows="<?php echo str_contains( $example['code'], "\n" ) ? '2' : '1'; ?>"
							readonly
						><?php echo esc_textarea( $example['code'] ); ?></textarea>
						<button
							type="button"
							class="button"
							data-psm-copy-shortcode
							data-copy-target="<?php echo esc_attr( $field_id ); ?>"
						>
							<?php esc_html_e( 'Sao chép', 'power-schedule-manager' ); ?>
						</button>
					</article>
				<?php endforeach; ?>
			</div>

			<div class="psm-shortcode-reference">
				<h3><?php esc_html_e( 'Công thức Page tiện ích chuẩn', 'power-schedule-manager' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Chọn rõ một chủ sở hữu H1/Hero. Chế độ Plugin sẽ tự tắt Page Title Blocksy khi lưu Page; không chèn thêm shortcode Hero thủ công.', 'power-schedule-manager' ); ?></li>
					<li><?php esc_html_e( 'Nếu Plugin quản lý Hero, chọn preset đúng trong hộp “Cúp Điện Lâm Đồng — Hero và nội dung SEO”.', 'power-schedule-manager' ); ?></li>
					<li><?php esc_html_e( 'Đặt một shortcode dữ liệu ngay sau Hero. CTA mặc định đã trỏ đến anchor của bảng hoặc form tương ứng.', 'power-schedule-manager' ); ?></li>
					<li><?php esc_html_e( 'Dùng Rank Math cho title, description, canonical và schema; không nhập metadata trùng trong plugin.', 'power-schedule-manager' ); ?></li>
				</ol>
				<h3><?php esc_html_e( 'Shortcode dữ liệu và Hero là hai khối khác nhau', 'power-schedule-manager' ); ?></h3>
				<p><?php esc_html_e( 'Khối Hero chỉ tạo breadcrumb, H1, mô tả, CTA và trạng thái cập nhật. Shortcode dữ liệu mới tạo bảng, bộ lọc hoặc danh sách kết quả. Với Page thường, mở hộp “Cúp Điện Lâm Đồng — Hero và nội dung SEO”, chọn Plugin quản lý Hero rồi đặt đúng một shortcode dữ liệu trong nội dung Page.', 'power-schedule-manager' ); ?></p>
				<p><?php esc_html_e( 'Các Page xổ số con có shortcode bên dưới sẽ được plugin tự nhận diện, chọn preset Hero phù hợp và tắt Page Title của Blocksy khi nâng cấp. Quản trị viên vẫn có thể đổi H1, mô tả, CTA và nội dung SEO ngay tại Page.', 'power-schedule-manager' ); ?></p>

				<h3><?php esc_html_e( 'Công thức chi tiết cho Page xổ số con', 'power-schedule-manager' ); ?></h3>
				<div class="psm-table-scroll"><table class="widefat striped"><thead><tr>
					<th><?php esc_html_e( 'Page / sản phẩm', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Shortcode dữ liệu', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Anchor CTA', 'power-schedule-manager' ); ?></th>
					<th><?php esc_html_e( 'Tham số hỗ trợ', 'power-schedule-manager' ); ?></th>
				</tr></thead><tbody>
				<?php
				$lottery_recipes = array(
					array( 'Miền Bắc', '[power_schedule_lottery_north]', '#ket-qua-north', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Miền Trung', '[power_schedule_lottery_central]', '#ket-qua-central', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Miền Nam', '[power_schedule_lottery_south]', '#ket-qua-south', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Mega 6/45', '[power_schedule_lottery_mega645]', '#ket-qua-mega645', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Power 6/55', '[power_schedule_lottery_power655]', '#ket-qua-power655', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Max 3D', '[power_schedule_lottery_max3d]', '#ket-qua-max3d', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Max 3D+', '[power_schedule_lottery_max3d_plus]', '#ket-qua-max3dplus', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Max 3D Pro', '[power_schedule_lottery_max3d_pro]', '#ket-qua-max3dpro', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Keno', '[power_schedule_lottery_keno]', '#ket-qua-keno', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Điện toán tổng hợp', '[power_schedule_lottery_dientoan]', '#ket-qua-dientoan', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Điện toán 123', '[power_schedule_lottery_dientoan123]', '#ket-qua-dientoan123', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Điện toán 6x36', '[power_schedule_lottery_dientoan6x36]', '#ket-qua-dientoan6x36', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
					array( 'Thần Tài', '[power_schedule_lottery_thantai]', '#ket-qua-thantai', 'date="YYYY-MM-DD", limit="1–30", title="…"' ),
				);
				foreach ( $lottery_recipes as $recipe ) :
					?>
					<tr><td><?php echo esc_html( $recipe[0] ); ?></td><td><code><?php echo esc_html( $recipe[1] ); ?></code></td><td><code><?php echo esc_html( $recipe[2] ); ?></code></td><td><code><?php echo esc_html( $recipe[3] ); ?></code></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
				<p class="description"><?php esc_html_e( 'date để trống = kỳ mới nhất; date dùng YYYY-MM-DD; limit được chặn trong khoảng an toàn; title chỉ đổi H2 của khối dữ liệu, không tạo thêm H1. Shortcode lịch sử nhận limit="10–50" và title.', 'power-schedule-manager' ); ?></p>

				<h3><?php esc_html_e( 'Công thức chi tiết cho lịch điện', 'power-schedule-manager' ); ?></h3>
				<ul>
					<li><code>[power_schedule date="today" unit="PB0302" show_reason="yes" show_status="yes" show_map="yes"]</code> — <?php esc_html_e( 'bảng lịch theo ngày và đơn vị.', 'power-schedule-manager' ); ?></li>
					<li><code>[power_schedule_search]</code> — <?php esc_html_e( 'form tra cứu; CTA của Page lịch tổng trỏ tới #psm-archive-search.', 'power-schedule-manager' ); ?></li>
					<li><code>[power_schedule_next unit="PB0302" limit="1"]</code> — <?php esc_html_e( 'lịch gần nhất; limit từ 1 đến 5.', 'power-schedule-manager' ); ?></li>
					<li><code>[power_schedule_alert unit="PB0302"]</code> — <?php esc_html_e( 'trạng thái đang diễn ra hoặc lịch tiếp theo.', 'power-schedule-manager' ); ?></li>
					<li><code>[power_schedule_days unit="PB0302" days="7" limit="7" show_count="yes"]</code> — <?php esc_html_e( 'các ngày có dữ liệu.', 'power-schedule-manager' ); ?></li>
					<li><code>[power_schedule_recent_updates limit="5"]</code> — <?php esc_html_e( 'lịch vừa cập nhật.', 'power-schedule-manager' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Các URL /lich-cup-dien/, trang đơn vị và trang chi tiết theo ngày do plugin tự sinh Hero, breadcrumb và một H1; không chèn shortcode Hero vào các template này. CTA trang chi tiết luôn trỏ đến #psm-schedule-details.', 'power-schedule-manager' ); ?></p>
				<h3><?php esc_html_e( 'Tham số khu vực', 'power-schedule-manager' ); ?></h3>
				<p><code>region</code>, <code>units</code>, <code>exclude</code>, <code>columns="1–6"</code>, <code>limit</code>, <code>theme="light|dark"</code>, <code>sort="default|name|code"</code>, <code>order="asc|desc"</code>, <code>label="short|full"</code>, <code>show_code="yes|no"</code>, <code>show_icon="yes|no"</code>, <code>link_to="schedule|area"</code>.</p>
				<p>
					<?php
					printf(
						/* translators: 1: Number of public units, 2: Comma-separated unit codes. */
						esc_html__( 'Hiện có %1$d đơn vị công khai. Mã đang dùng: %2$s', 'power-schedule-manager' ),
						count( $public_unit_codes ),
						implode( ', ', $public_unit_codes )
					);
					?>
				</p>
			</div>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="pwa">
			<h2><?php esc_html_e( 'Ứng dụng trên màn hình chính (PWA)', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Cho phép người dùng mở website như một ứng dụng, không cần tải từ App Store. Gợi ý cài chỉ xuất hiện sau khi họ đã quay lại đủ số lần.', 'power-schedule-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kích hoạt', 'power-schedule-manager' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( $option_name . '[pwa_enabled]' ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_name . '[pwa_enabled]' ); ?>" value="1" <?php checked( $pwa_enabled ); ?>>
								<?php esc_html_e( 'Bật ứng dụng PWA trên frontend', 'power-schedule-manager' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'PWA chỉ hoạt động trên HTTPS. Khi tắt, manifest, service worker và giao diện gợi ý cài đều không được tải.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Trải nghiệm cài đặt', 'power-schedule-manager' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( $option_name . '[pwa_prompt_enabled]' ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_name . '[pwa_prompt_enabled]' ); ?>" value="1" <?php checked( $pwa_prompt_enabled ); ?>>
								<?php esc_html_e( 'Hiện gợi ý thêm vào màn hình chính', 'power-schedule-manager' ); ?>
							</label>
							<br>
							<input type="hidden" name="<?php echo esc_attr( $option_name . '[pwa_service_worker_enabled]' ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_name . '[pwa_service_worker_enabled]' ); ?>" value="1" <?php checked( $pwa_service_worker_enabled ); ?>>
								<?php esc_html_e( 'Đăng ký service worker ở phạm vi toàn website', 'power-schedule-manager' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Plugin không thay thế một service worker toàn website đã có. Service worker này không cache HTML lịch điện, nhằm tránh hiển thị dữ liệu cũ.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-pwa-app-name"><?php esc_html_e( 'Tên ứng dụng', 'power-schedule-manager' ); ?></label></th>
						<td>
							<input id="psm-pwa-app-name" class="regular-text" type="text" maxlength="80" name="<?php echo esc_attr( $option_name . '[pwa_app_name]' ); ?>" value="<?php echo esc_attr( $pwa_app_name ); ?>">
							<p><label for="psm-pwa-short-name"><?php esc_html_e( 'Tên ngắn dưới biểu tượng', 'power-schedule-manager' ); ?></label></p>
							<input id="psm-pwa-short-name" class="regular-text" type="text" maxlength="30" name="<?php echo esc_attr( $option_name . '[pwa_short_name]' ); ?>" value="<?php echo esc_attr( $pwa_short_name ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-pwa-description"><?php esc_html_e( 'Mô tả ứng dụng', 'power-schedule-manager' ); ?></label></th>
						<td>
							<textarea id="psm-pwa-description" class="large-text" rows="3" maxlength="240" name="<?php echo esc_attr( $option_name . '[pwa_description]' ); ?>"><?php echo esc_textarea( $pwa_description ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-pwa-icon-url"><?php esc_html_e( 'Biểu tượng ứng dụng', 'power-schedule-manager' ); ?></label></th>
						<td>
							<input id="psm-pwa-icon-url" class="large-text code" type="url" inputmode="url" placeholder="https://example.com/icon-512.png" name="<?php echo esc_attr( $option_name . '[pwa_icon_url]' ); ?>" value="<?php echo esc_attr( $pwa_icon_url ); ?>">
							<?php if ( '' !== $site_icon_url ) : ?>
								<p class="description"><?php esc_html_e( 'Website đã có Site Icon; plugin sẽ dùng biểu tượng đó nếu ô này để trống.', 'power-schedule-manager' ); ?></p>
							<?php else : ?>
								<p class="description notice-warning"><?php esc_html_e( 'Chưa có Site Icon. Hãy đặt biểu tượng vuông tối thiểu 512×512 trong WordPress hoặc nhập URL HTTPS ở đây để trình duyệt có thể cài ứng dụng đúng chuẩn.', 'power-schedule-manager' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Màu ứng dụng', 'power-schedule-manager' ); ?></th>
						<td>
							<label for="psm-pwa-theme-color"><?php esc_html_e( 'Màu giao diện', 'power-schedule-manager' ); ?></label>
							<input id="psm-pwa-theme-color" type="color" name="<?php echo esc_attr( $option_name . '[pwa_theme_color]' ); ?>" value="<?php echo esc_attr( $pwa_theme_color ); ?>">
							&nbsp;&nbsp;
							<label for="psm-pwa-background-color"><?php esc_html_e( 'Màu nền khởi động', 'power-schedule-manager' ); ?></label>
							<input id="psm-pwa-background-color" type="color" name="<?php echo esc_attr( $option_name . '[pwa_background_color]' ); ?>" value="<?php echo esc_attr( $pwa_background_color ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Thời điểm gợi ý', 'power-schedule-manager' ); ?></th>
						<td>
							<label for="psm-pwa-visits"><?php esc_html_e( 'Hiện sau số phiên truy cập', 'power-schedule-manager' ); ?></label>
							<input id="psm-pwa-visits" class="small-text" type="number" min="2" max="10" name="<?php echo esc_attr( $option_name . '[pwa_visit_threshold]' ); ?>" value="<?php echo esc_attr( (string) $pwa_visit_threshold ); ?>">
							&nbsp;&nbsp;
							<label for="psm-pwa-delay"><?php esc_html_e( 'Đợi (giây)', 'power-schedule-manager' ); ?></label>
							<input id="psm-pwa-delay" class="small-text" type="number" min="2" max="30" name="<?php echo esc_attr( $option_name . '[pwa_prompt_delay_seconds]' ); ?>" value="<?php echo esc_attr( (string) $pwa_prompt_delay_seconds ); ?>">
							&nbsp;&nbsp;
							<label for="psm-pwa-cooldown"><?php esc_html_e( 'Nhắc lại sau (ngày)', 'power-schedule-manager' ); ?></label>
							<input id="psm-pwa-cooldown" class="small-text" type="number" min="1" max="365" name="<?php echo esc_attr( $option_name . '[pwa_prompt_cooldown_days]' ); ?>" value="<?php echo esc_attr( (string) $pwa_prompt_cooldown_days ); ?>">
							<p class="description"><?php esc_html_e( 'Một phiên chỉ được tính một lần. Mặc định lần truy cập thứ 3, chờ 8 giây; nếu người dùng chọn “Để sau” thì 30 ngày mới hỏi lại.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Nội dung gợi ý', 'power-schedule-manager' ); ?></th>
						<td>
							<label for="psm-pwa-prompt-title"><?php esc_html_e( 'Tiêu đề', 'power-schedule-manager' ); ?></label>
							<input id="psm-pwa-prompt-title" class="large-text" type="text" maxlength="120" name="<?php echo esc_attr( $option_name . '[pwa_prompt_title]' ); ?>" value="<?php echo esc_attr( $pwa_prompt_title ); ?>">
							<p><label for="psm-pwa-prompt-message"><?php esc_html_e( 'Mô tả', 'power-schedule-manager' ); ?></label></p>
							<textarea id="psm-pwa-prompt-message" class="large-text" rows="2" maxlength="240" name="<?php echo esc_attr( $option_name . '[pwa_prompt_message]' ); ?>"><?php echo esc_textarea( $pwa_prompt_message ); ?></textarea>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'iPhone/iPad:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'plugin hướng dẫn người dùng mở menu Chia sẻ và chọn “Thêm vào Màn hình chính”. Web Push trên iOS chỉ dùng được với web app đã thêm vào màn hình chính và hệ điều hành hỗ trợ.', 'power-schedule-manager' ); ?></p>
				<p><strong><?php esc_html_e( 'Kiểm tra kỹ thuật:', 'power-schedule-manager' ); ?></strong>
					<a href="<?php echo esc_url( add_query_arg( 'psm_pwa_manifest', '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Mở manifest', 'power-schedule-manager' ); ?></a>
					·
					<a href="<?php echo esc_url( Power_Schedule_Manager_PWA::worker_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Mở service worker', 'power-schedule-manager' ); ?></a>
				</p>
			</div>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="notifications">
			<h2><?php esc_html_e( 'Push, Telegram, webhook và Zalo OA', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Thông báo được đưa vào hàng đợi sau khi import hoàn tất, có chống gửi trùng và tự thử lại. Khóa bí mật nhập tại đây được mã hóa, không hiển thị lại và không được đưa vào bản backup của plugin.', 'power-schedule-manager' ); ?></p>
			<p class="description"><?php esc_html_e( 'Bảo trì hằng ngày vẫn gửi một cảnh báo tổng hợp nếu Trung tâm hệ thống có lỗi hoặc việc cần xử lý. Với Push, mỗi người dùng tự chọn các khu vực theo dõi trên thiết bị của họ; thông báo chỉ được gửi cho khu vực tương ứng.', 'power-schedule-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Push Notification', 'power-schedule-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_name . '[push_enabled]' ); ?>" value="1" <?php checked( $push_enabled ); ?>>
								<?php esc_html_e( 'Cho phép người dùng đăng ký thông báo trên trình duyệt', 'power-schedule-manager' ); ?>
							</label>
							<p>
								<label for="psm-onesignal-app-id"><?php esc_html_e( 'OneSignal App ID', 'power-schedule-manager' ); ?></label><br>
								<input id="psm-onesignal-app-id" class="regular-text code" type="text" maxlength="36" autocomplete="off" name="<?php echo esc_attr( $option_name . '[push_onesignal_app_id]' ); ?>" value="<?php echo esc_attr( $push_onesignal_app_id ); ?>" placeholder="00000000-0000-0000-0000-000000000000">
							</p>
							<p>
								<label for="psm-push-button-label"><?php esc_html_e( 'Nhãn chuông thông báo nổi', 'power-schedule-manager' ); ?></label><br>
								<input id="psm-push-button-label" class="regular-text" type="text" maxlength="80" name="<?php echo esc_attr( $option_name . '[push_button_label]' ); ?>" value="<?php echo esc_attr( $push_button_label ); ?>">
							</p>
							<p class="description">
								<?php esc_html_e( 'SDK không được tải khi mở trang. Chỉ sau khi người dùng bấm nút “Nhận thông báo”, họ mới chọn khu vực và quyết định cấp quyền; plugin không hiển thị Slidedown tự động.', 'power-schedule-manager' ); ?>
							</p>
							<hr>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_name . '[push_delivery_enabled]' ); ?>" value="1" <?php checked( $push_delivery_enabled ); ?>>
								<?php esc_html_e( 'Gửi tự động khi lịch của khu vực được theo dõi có thay đổi', 'power-schedule-manager' ); ?>
							</label>
							<p>
								<label for="psm-push-notification-title"><?php esc_html_e( 'Tiêu đề thông báo', 'power-schedule-manager' ); ?></label><br>
								<input id="psm-push-notification-title" class="regular-text" type="text" maxlength="120" name="<?php echo esc_attr( $option_name . '[push_notification_title]' ); ?>" value="<?php echo esc_attr( $push_notification_title ); ?>">
							</p>
							<p>
								<label for="psm-push-notification-message"><?php esc_html_e( 'Nội dung thông báo', 'power-schedule-manager' ); ?></label><br>
								<textarea id="psm-push-notification-message" class="large-text" rows="3" maxlength="240" name="<?php echo esc_attr( $option_name . '[push_notification_message]' ); ?>"><?php echo esc_textarea( $push_notification_message ); ?></textarea>
							</p>
							<p class="description">
								<?php esc_html_e( 'Biến dùng được: %unit_name%, %unit_code%, %event_count%, %date_from%, %date_to%, %found%, %inserted%, %updated%, %url%.', 'power-schedule-manager' ); ?>
							</p>
							<p>
								<label for="psm-onesignal-rest-key"><?php esc_html_e( 'OneSignal REST API key', 'power-schedule-manager' ); ?></label><br>
								<input id="psm-onesignal-rest-key" class="regular-text code" type="password" maxlength="4096" autocomplete="new-password" spellcheck="false" name="<?php echo esc_attr( $option_name . '[onesignal_rest_api_key]' ); ?>" value="" placeholder="<?php esc_attr_e( 'Để trống để giữ khóa đã lưu', 'power-schedule-manager' ); ?>">
							</p>
							<p class="description"><?php echo esc_html( 'admin' === $onesignal_rest_api_key_source ? __( 'Đã lưu khóa mã hóa trong WordPress.', 'power-schedule-manager' ) : ( 'environment' === $onesignal_rest_api_key_source ? __( 'Đang dùng khóa từ cấu hình máy chủ.', 'power-schedule-manager' ) : __( 'Chưa có REST API key; gửi tự động sẽ không hoạt động.', 'power-schedule-manager' ) ) ); ?></p>
							<?php if ( '' !== $onesignal_rest_api_key_encrypted ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[onesignal_rest_api_key_clear]' ); ?>" value="1"> <?php esc_html_e( 'Xóa REST API key đã lưu', 'power-schedule-manager' ); ?></label>
							<?php endif; ?>
							<hr>
							<p><strong><?php esc_html_e( 'Thông báo kết quả xổ số', 'power-schedule-manager' ); ?></strong></p>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_name . '[lottery_push_delivery_enabled]' ); ?>" value="1" <?php checked( $lottery_push_delivery_enabled ); ?>>
								<?php esc_html_e( 'Gửi khi kết quả xổ số mới hoặc có thay đổi thực sự', 'power-schedule-manager' ); ?>
							</label>
							<p>
								<label for="psm-lottery-push-title"><?php esc_html_e( 'Tiêu đề xổ số', 'power-schedule-manager' ); ?></label><br>
								<input id="psm-lottery-push-title" class="regular-text" type="text" maxlength="120" name="<?php echo esc_attr( $option_name . '[lottery_push_notification_title]' ); ?>" value="<?php echo esc_attr( $lottery_push_notification_title ); ?>">
							</p>
							<p>
								<label for="psm-lottery-push-message"><?php esc_html_e( 'Nội dung xổ số', 'power-schedule-manager' ); ?></label><br>
								<textarea id="psm-lottery-push-message" class="large-text" rows="2" maxlength="240" name="<?php echo esc_attr( $option_name . '[lottery_push_notification_message]' ); ?>"><?php echo esc_textarea( $lottery_push_notification_message ); ?></textarea>
							</p>
							<p class="description"><?php esc_html_e( 'Người dùng tự chọn Miền Bắc, Miền Trung, Miền Nam hoặc từng sản phẩm xổ số tại nút nhận thông báo. Biến: %lottery_name%, %draw_count%, %date_from%, %date_to%, %url%.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Telegram', 'power-schedule-manager' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[telegram_enabled]' ); ?>" value="1" <?php checked( $telegram_enabled ); ?>> <?php esc_html_e( 'Gửi sau khi nhập thành công', 'power-schedule-manager' ); ?></label>
							<p><label for="psm-telegram-bot-token"><?php esc_html_e( 'Bot token', 'power-schedule-manager' ); ?></label><br>
							<input id="psm-telegram-bot-token" class="regular-text code" type="password" maxlength="4096" autocomplete="new-password" spellcheck="false" name="<?php echo esc_attr( $option_name . '[telegram_bot_token]' ); ?>" value="" placeholder="<?php esc_attr_e( 'Để trống để giữ token đã lưu', 'power-schedule-manager' ); ?>"></p>
							<p><label for="psm-telegram-chat-id"><?php esc_html_e( 'Chat hoặc channel ID', 'power-schedule-manager' ); ?></label><br>
							<input id="psm-telegram-chat-id" class="regular-text" type="text" maxlength="100" name="<?php echo esc_attr( $option_name . '[telegram_chat_id]' ); ?>" value="<?php echo esc_attr( $telegram_chat_id ); ?>" placeholder="-1001234567890"></p>
							<p class="description"><?php echo esc_html( 'missing' === $telegram_bot_token_source ? __( 'Chưa có bot token.', 'power-schedule-manager' ) : __( 'Bot token đã được cấu hình và không được hiển thị lại.', 'power-schedule-manager' ) ); ?></p>
							<?php if ( '' !== $telegram_bot_token_encrypted ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[telegram_bot_token_clear]' ); ?>" value="1"> <?php esc_html_e( 'Xóa bot token đã lưu', 'power-schedule-manager' ); ?></label><?php endif; ?>
							<button type="button" class="button" data-psm-test-notification="telegram"><?php esc_html_e( 'Gửi thử Telegram', 'power-schedule-manager' ); ?></button>
							<span class="psm-notification-test-result" role="status" aria-live="polite"></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-webhook-url"><?php esc_html_e( 'Generic webhook', 'power-schedule-manager' ); ?></label></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[webhook_enabled]' ); ?>" value="1" <?php checked( $webhook_enabled ); ?>> <?php esc_html_e( 'Gửi JSON có chữ ký HMAC', 'power-schedule-manager' ); ?></label>
							<p><input id="psm-webhook-url" class="large-text" type="url" maxlength="2048" name="<?php echo esc_attr( $option_name . '[webhook_url]' ); ?>" value="<?php echo esc_attr( $webhook_url ); ?>" placeholder="https://automation.example.com/webhook/power-schedule"></p>
							<p><label for="psm-webhook-secret"><?php esc_html_e( 'Khóa ký HMAC', 'power-schedule-manager' ); ?></label><br><input id="psm-webhook-secret" class="regular-text code" type="password" maxlength="4096" autocomplete="new-password" spellcheck="false" name="<?php echo esc_attr( $option_name . '[webhook_secret]' ); ?>" value="" placeholder="<?php esc_attr_e( 'Để trống để giữ khóa đã lưu', 'power-schedule-manager' ); ?>"></p>
							<p class="description"><?php echo esc_html( 'missing' === $webhook_secret_source ? __( 'Chưa có khóa ký webhook.', 'power-schedule-manager' ) : __( 'Khóa ký webhook đã được cấu hình và không được hiển thị lại.', 'power-schedule-manager' ) ); ?></p>
							<?php if ( '' !== $webhook_secret_encrypted ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[webhook_secret_clear]' ); ?>" value="1"> <?php esc_html_e( 'Xóa khóa ký đã lưu', 'power-schedule-manager' ); ?></label><?php endif; ?>
							<button type="button" class="button" data-psm-test-notification="webhook"><?php esc_html_e( 'Gửi thử webhook', 'power-schedule-manager' ); ?></button>
							<span class="psm-notification-test-result" role="status" aria-live="polite"></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Zalo Official Account', 'power-schedule-manager' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[zalo_enabled]' ); ?>" value="1" <?php checked( $zalo_enabled ); ?>> <?php esc_html_e( 'Gửi tin OA sau khi import', 'power-schedule-manager' ); ?></label>
							<p><label for="psm-zalo-recipient"><?php esc_html_e( 'Zalo user ID đã đồng ý nhận tin', 'power-schedule-manager' ); ?></label><br>
							<input id="psm-zalo-recipient" class="regular-text" type="text" maxlength="191" name="<?php echo esc_attr( $option_name . '[zalo_recipient_id]' ); ?>" value="<?php echo esc_attr( $zalo_recipient_id ); ?>"></p>
							<p><label for="psm-zalo-access-token"><?php esc_html_e( 'OA access token', 'power-schedule-manager' ); ?></label><br><input id="psm-zalo-access-token" class="regular-text code" type="password" maxlength="4096" autocomplete="new-password" spellcheck="false" name="<?php echo esc_attr( $option_name . '[zalo_access_token]' ); ?>" value="" placeholder="<?php esc_attr_e( 'Để trống để giữ token đã lưu', 'power-schedule-manager' ); ?>"></p>
							<p class="description"><?php echo esc_html( 'missing' === $zalo_access_token_source ? __( 'Chưa có access token.', 'power-schedule-manager' ) : __( 'Access token đã được cấu hình; cần làm mới theo chính sách Zalo OA.', 'power-schedule-manager' ) ); ?></p>
							<?php if ( '' !== $zalo_access_token_encrypted ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[zalo_access_token_clear]' ); ?>" value="1"> <?php esc_html_e( 'Xóa access token đã lưu', 'power-schedule-manager' ); ?></label><?php endif; ?>
							<button type="button" class="button" data-psm-test-notification="zalo"><?php esc_html_e( 'Gửi thử Zalo OA', 'power-schedule-manager' ); ?></button>
							<span class="psm-notification-test-result" role="status" aria-live="polite"></span>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="psm-settings-guides">
				<article class="psm-settings-guide">
					<h3><?php esc_html_e( 'OneSignal — hoàn tất trong 4 bước', 'power-schedule-manager' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Tạo ứng dụng Web Push đúng tên miền HTTPS và dán App ID ở phía trên.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Nếu bật gửi tự động, nhập REST API key vào ô mã hóa phía trên.', 'power-schedule-manager' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: Public service-worker URL. */
								esc_html__( 'Kiểm tra URL service worker trả về JavaScript, không chuyển hướng: %s', 'power-schedule-manager' ),
								esc_url(
									Power_Schedule_Manager_Assets::onesignal_worker_url()
								)
							);
							?>
						</li>
						<li><?php esc_html_e( 'Lưu, xóa cache CDN, mở cửa sổ ẩn danh rồi bấm nút nhận thông báo để chọn khu vực điện hoặc sản phẩm xổ số. Không có Slidedown tự động.', 'power-schedule-manager' ); ?></li>
					</ol>
				</article>

				<article class="psm-settings-guide">
					<h3><?php esc_html_e( 'Telegram — kênh vận hành', 'power-schedule-manager' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Tạo bot, thêm bot vào nhóm hoặc kênh cần nhận cảnh báo.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Nhập bot token và chat/channel ID vào biểu mẫu phía trên.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Lưu trước, sau đó dùng “Gửi thử Telegram” để xác nhận.', 'power-schedule-manager' ); ?></li>
					</ol>
				</article>

				<article class="psm-settings-guide">
					<h3><?php esc_html_e( 'Generic webhook', 'power-schedule-manager' ); ?></h3>
					<p><?php esc_html_e( 'Dùng HTTPS để nối n8n, SMS hoặc CRM. Đầu nhận phải xác minh chữ ký HMAC trong header X-PSM-Signature-SHA256 trước khi xử lý JSON.', 'power-schedule-manager' ); ?></p>
					<p><?php esc_html_e( 'Nhập khóa ký HMAC ở biểu mẫu phía trên. Hàng đợi có chống trùng; chỉ retry timeout, 429 và lỗi máy chủ tạm thời.', 'power-schedule-manager' ); ?></p>
				</article>

				<article class="psm-settings-guide">
					<h3><?php esc_html_e( 'Zalo OA', 'power-schedule-manager' ); ?></h3>
					<p><?php esc_html_e( 'Dùng cho một Zalo user ID đã đồng ý nhận tin. Nhập access token ở biểu mẫu và theo dõi chu kỳ làm mới của Zalo OA.', 'power-schedule-manager' ); ?></p>
					<p><?php esc_html_e( 'Cần nhiều người nhận hoặc đăng ký theo khu vực: dùng OneSignal; cần quy trình Zalo chuyên biệt: nối qua webhook và dịch vụ trung gian.', 'power-schedule-manager' ); ?></p>
				</article>
			</div>

			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'SMS, gói trả phí và donate', 'power-schedule-manager' ); ?></strong></p>
				<p><?php esc_html_e( 'Chưa bật gửi SMS trực tiếp để tránh khóa chặt plugin vào một nhà cung cấp. Kiến trúc production nên bổ sung hồ sơ đồng ý nhận tin, lựa chọn đơn vị điện lực, giới hạn tần suất, nhật ký giao nhận và thanh toán độc lập. Trong giai đoạn hiện tại, generic webhook là điểm tích hợp an toàn cho SMS; donate nên dùng trang thanh toán riêng thay vì trộn vào dữ liệu lịch điện.', 'power-schedule-manager' ); ?></p>
			</div>
		</section>

		<section id="psm-cloudflare" class="psm-dashboard-panel" data-psm-settings-panel="cdn">
			<h2><?php esc_html_e( 'Cloudflare CDN, Turnstile và WAF', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Một nơi cấu hình xóa cache chính xác, bảo vệ biểu mẫu hợp tác và áp dụng các lớp kiểm soát lưu lượng tại biên Cloudflare.', 'power-schedule-manager' ); ?></p>
			<p><?php esc_html_e( 'Plugin chỉ xóa cache đúng các URL công khai bị ảnh hưởng sau khi nhập hoặc cập nhật lịch. Không dùng Purge Everything và không xóa object cache toàn website.', 'power-schedule-manager' ); ?></p>
			<p><strong><?php esc_html_e( 'Cách hoạt động:', 'power-schedule-manager' ); ?></strong> <?php esc_html_e( 'URL được xếp hàng tự động sau mỗi thay đổi. WP-Cron xử lý tối đa 30 URL mỗi lượt và tự thử lại theo thời gian tăng dần nếu Cloudflare tạm lỗi.', 'power-schedule-manager' ); ?></p>

			<div class="psm-system-summary">
				<span class="psm-status <?php echo $cloudflare_status['token'] ? 'psm-status--success' : 'psm-status--neutral'; ?>">
					<?php echo $cloudflare_status['token'] ? esc_html__( 'API token đã cấu hình', 'power-schedule-manager' ) : esc_html__( 'Chưa có API token', 'power-schedule-manager' ); ?>
				</span>
				<span class="psm-status <?php echo $cloudflare_status['zone'] ? 'psm-status--success' : 'psm-status--neutral'; ?>">
					<?php echo $cloudflare_status['zone'] ? esc_html__( 'Zone ID hợp lệ', 'power-schedule-manager' ) : esc_html__( 'Chưa có Zone ID', 'power-schedule-manager' ); ?>
				</span>
				<span class="psm-status psm-status--neutral">
					<?php
					printf(
						/* translators: %d: Number of queued URLs. */
						esc_html__( '%d URL đang chờ xóa cache', 'power-schedule-manager' ),
						absint( $cloudflare_status['queued'] )
					);
					?>
				</span>
				<span class="psm-status <?php echo $cloudflare_status['turnstile_enabled'] && $cloudflare_status['turnstile_site_key'] && $cloudflare_status['turnstile_secret'] ? 'psm-status--success' : 'psm-status--neutral'; ?>">
					<?php echo $cloudflare_status['turnstile_enabled'] && $cloudflare_status['turnstile_site_key'] && $cloudflare_status['turnstile_secret'] ? esc_html__( 'Turnstile sẵn sàng', 'power-schedule-manager' ) : esc_html__( 'Turnstile chưa hoàn chỉnh', 'power-schedule-manager' ); ?>
				</span>
			</div>

			<p>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'psm_process_cloudflare_queue', admin_url( 'admin-post.php' ) ), 'psm_process_cloudflare_queue' ) ); ?>">
					<?php esc_html_e( 'Xử lý hàng đợi ngay', 'power-schedule-manager' ); ?>
				</a>
				<span class="description"><?php esc_html_e( 'Dùng khi cron bị chậm; nút chỉ xử lý một lô giới hạn và không xóa toàn bộ cache.', 'power-schedule-manager' ); ?></span>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Xóa cache tự động', 'power-schedule-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_name . '[cloudflare_enabled]' ); ?>" value="1" <?php checked( $cloudflare_enabled ); ?>>
								<?php esc_html_e( 'Bật hàng đợi Cloudflare exact-URL purge', 'power-schedule-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="psm-cloudflare-zone-id"><?php esc_html_e( 'Cloudflare Zone ID', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<input
								id="psm-cloudflare-zone-id"
								type="text"
								class="regular-text code"
								maxlength="32"
								pattern="[a-fA-F0-9]{32}"
								name="<?php echo esc_attr( $option_name . '[cloudflare_zone_id]' ); ?>"
								value="<?php echo esc_attr( $cloudflare_zone_id ); ?>"
								autocomplete="off"
								spellcheck="false"
							>
							<p class="description"><?php esc_html_e( 'Zone ID nằm trong trang Overview của tên miền trên Cloudflare.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="psm-cloudflare-api-token"><?php esc_html_e( 'Cloudflare API token', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<input
								id="psm-cloudflare-api-token"
								type="password"
								class="regular-text code"
								maxlength="4096"
								name="<?php echo esc_attr( $option_name . '[cloudflare_api_token]' ); ?>"
								value=""
								autocomplete="new-password"
								spellcheck="false"
								placeholder="<?php esc_attr_e( 'Để trống để giữ khóa đã lưu', 'power-schedule-manager' ); ?>"
							>
							<p class="description">
								<?php
								echo esc_html(
									match ( $cloudflare_token_source ) {
										'environment' => __(
											'Đang dùng token từ cấu hình máy chủ; giá trị này luôn được ưu tiên.',
											'power-schedule-manager'
										),
										'admin' => __(
											'Đã lưu token mã hóa trong WordPress. Plugin không hiển thị lại token.',
											'power-schedule-manager'
										),
										default => __(
											'Chưa có token. Chỉ cấp quyền Cache Purge cho đúng zone.',
											'power-schedule-manager'
										),
									}
								);
								?>
							</p>
							<?php if ( '' !== $cloudflare_api_token_encrypted ) : ?>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $option_name . '[cloudflare_api_token_clear]' ); ?>" value="1">
									<?php esc_html_e( 'Xóa token đã lưu trong WordPress', 'power-schedule-manager' ); ?>
								</label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Turnstile', 'power-schedule-manager' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( $option_name . '[cloudflare_turnstile_enabled]' ); ?>" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[cloudflare_turnstile_enabled]' ); ?>" value="1" <?php checked( $cloudflare_turnstile_enabled ); ?>> <?php esc_html_e( 'Bảo vệ form hợp tác bằng Cloudflare Turnstile', 'power-schedule-manager' ); ?></label>
							<p class="description"><?php esc_html_e( 'Khi bật, plugin đóng biểu mẫu nếu thiếu khóa hoặc xác minh máy chủ thất bại. Site Key là khóa công khai; Secret Key chỉ dùng ở máy chủ.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-turnstile-site-key"><?php esc_html_e( 'Turnstile Site Key', 'power-schedule-manager' ); ?></label></th>
						<td>
							<input id="psm-turnstile-site-key" type="text" class="regular-text code" maxlength="128" name="<?php echo esc_attr( $option_name . '[cloudflare_turnstile_site_key]' ); ?>" value="<?php echo esc_attr( $cloudflare_turnstile_site_key ); ?>" autocomplete="off" spellcheck="false">
							<p class="description"><?php esc_html_e( 'Tạo widget Managed cho đúng hostname đang vận hành.', 'power-schedule-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="psm-turnstile-secret"><?php esc_html_e( 'Turnstile Secret Key', 'power-schedule-manager' ); ?></label></th>
						<td>
							<input id="psm-turnstile-secret" type="password" class="regular-text code" maxlength="4096" name="<?php echo esc_attr( $option_name . '[cloudflare_turnstile_secret]' ); ?>" value="" autocomplete="new-password" spellcheck="false" placeholder="<?php esc_attr_e( 'Để trống để giữ khóa đã lưu', 'power-schedule-manager' ); ?>">
							<p class="description">
								<?php
								echo esc_html(
									match ( $cloudflare_turnstile_secret_source ) {
										'environment' => __( 'Đang dùng Secret Key từ cấu hình máy chủ; giá trị này luôn được ưu tiên.', 'power-schedule-manager' ),
										'admin' => __( 'Đã lưu Secret Key mã hóa trong WordPress. Plugin không hiển thị lại khóa.', 'power-schedule-manager' ),
										default => __( 'Chưa có Secret Key.', 'power-schedule-manager' ),
									}
								);
								?>
							</p>
							<?php if ( '' !== $cloudflare_turnstile_secret_encrypted ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[cloudflare_turnstile_secret_clear]' ); ?>" value="1"> <?php esc_html_e( 'Xóa Secret Key đã lưu trong WordPress', 'power-schedule-manager' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="psm-settings-guides psm-settings-guides--accordion">
				<details class="psm-settings-guide">
					<summary><?php esc_html_e( 'Cache Rules nên dùng', 'power-schedule-manager' ); ?></summary>
					<div class="psm-settings-guide__body"><ul>
						<li><?php esc_html_e( 'Chỉ cache request GET/HEAD của trang công khai.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Bỏ qua wp-admin, wp-login.php, preview, REST có xác thực và người dùng đã đăng nhập.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Không cache request POST, bước nhập dữ liệu, trang xem trước hoặc AJAX quản trị.', 'power-schedule-manager' ); ?></li>
					</ul></div>
				</details>
				<details class="psm-settings-guide">
					<summary><?php esc_html_e( 'Cấu hình Coolify', 'power-schedule-manager' ); ?></summary>
					<div class="psm-settings-guide__body">
					<p><?php esc_html_e( 'Nhập token Cache Purge và Turnstile Secret vào các trường mã hóa phía trên. Không cấp quyền DNS, Zone Settings hoặc quyền tài khoản ngoài nhu cầu.', 'power-schedule-manager' ); ?></p>
					</div>
				</details>
				<details class="psm-settings-guide">
						<summary><?php esc_html_e( 'Rate Limiting cho form hợp tác', 'power-schedule-manager' ); ?></summary>
						<div class="psm-settings-guide__body"><pre><code>(http.request.method eq "POST" and
	 http.request.uri.path eq "/wp-admin/admin-post.php" and
	 http.request.uri.query contains "action=psm_submit_sponsorship")</code></pre>
						<p>
							<?php esc_html_e( 'Áp dụng cho biểu mẫu hợp tác: tối đa 5 yêu cầu/IP trong 10 phút. Dùng Managed Challenge trước, sau đó Block 10 phút nếu hành vi lặp lại.', 'power-schedule-manager' ); ?>
						</p>
					</div>
				</details>
				<details class="psm-settings-guide">
					<summary><?php esc_html_e( 'WAF production', 'power-schedule-manager' ); ?></summary>
					<div class="psm-settings-guide__body"><ul>
						<li><?php esc_html_e( 'Bật Cloudflare Managed Ruleset và nhóm quy tắc WordPress; theo dõi Security Events trước khi siết hành động.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Bỏ cache cho admin-post.php, wp-admin, wp-login.php và mọi request POST.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Giới hạn riêng REST API /wp-json/power-schedule/v1/ theo IP và ngưỡng API trong plugin.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Khóa truy cập trực tiếp vào origin, chỉ cho phép Cloudflare và cấu hình máy chủ khôi phục IP khách thật trước khi dùng giới hạn tại origin.', 'power-schedule-manager' ); ?></li>
						<li><?php esc_html_e( 'Nếu website dùng CSP, cho phép script/frame/connect cần thiết từ challenges.cloudflare.com.', 'power-schedule-manager' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Token Cache Purge hiện tại không được dùng để sửa WAF nhằm giữ nguyên nguyên tắc quyền tối thiểu.', 'power-schedule-manager' ); ?></p>
					</div>
				</details>
			</div>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="weather">
			<h2><?php esc_html_e( 'Bản đồ thời tiết cộng đồng', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'Thiết lập vị trí mặc định cho các shortcode thời tiết. Khung Windy chỉ được tải ở trang có shortcode, độc lập với bản đồ tuyến đường Leaflet.', 'power-schedule-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="psm-weather-label"><?php esc_html_e( 'Tên khu vực mặc định', 'power-schedule-manager' ); ?></label></th>
						<td>
							<input
								id="psm-weather-label"
								type="text"
								class="regular-text"
								maxlength="100"
								name="<?php echo esc_attr( $option_name . '[weather_default_label]' ); ?>"
								value="<?php echo esc_attr( $weather_default_label ); ?>"
							>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tâm bản đồ', 'power-schedule-manager' ); ?></th>
						<td class="psm-settings-inline-fields">
							<label>
								<span><?php esc_html_e( 'Vĩ độ', 'power-schedule-manager' ); ?></span>
								<input
									type="number"
									step="0.000001"
									min="-90"
									max="90"
									name="<?php echo esc_attr( $option_name . '[weather_default_lat]' ); ?>"
									value="<?php echo esc_attr( (string) $weather_default_lat ); ?>"
								>
							</label>
							<label>
								<span><?php esc_html_e( 'Kinh độ', 'power-schedule-manager' ); ?></span>
								<input
									type="number"
									step="0.000001"
									min="-180"
									max="180"
									name="<?php echo esc_attr( $option_name . '[weather_default_lon]' ); ?>"
									value="<?php echo esc_attr( (string) $weather_default_lon ); ?>"
								>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Khung hiển thị', 'power-schedule-manager' ); ?></th>
						<td class="psm-settings-inline-fields">
							<label>
								<span><?php esc_html_e( 'Mức thu phóng', 'power-schedule-manager' ); ?></span>
								<input
									type="number"
									min="3"
									max="15"
									name="<?php echo esc_attr( $option_name . '[weather_default_zoom]' ); ?>"
									value="<?php echo esc_attr( (string) $weather_default_zoom ); ?>"
								>
							</label>
							<label>
								<span><?php esc_html_e( 'Chiều cao (px)', 'power-schedule-manager' ); ?></span>
								<input
									type="number"
									min="320"
									max="760"
									step="10"
									name="<?php echo esc_attr( $option_name . '[weather_default_height]' ); ?>"
									value="<?php echo esc_attr( (string) $weather_default_height ); ?>"
								>
							</label>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="psm-settings-guides">
				<article class="psm-settings-guide">
					<h3><?php esc_html_e( 'Dự báo gọn cho trang nội dung', 'power-schedule-manager' ); ?></h3>
					<code>[power_schedule_weather_forecast]</code>
					<p><?php esc_html_e( 'Hiển thị thời tiết hiện tại và bốn ngày tiếp theo, không tải bản đồ.', 'power-schedule-manager' ); ?></p>
				</article>
				<article class="psm-settings-guide">
					<h3><?php esc_html_e( 'Dự báo kèm bản đồ nhiều lớp', 'power-schedule-manager' ); ?></h3>
					<code>[power_schedule_weather]</code>
					<p><?php esc_html_e( 'Có thể ghi đè lat, lon, zoom, height, label; dùng forecast="no" hoặc map="no" để ẩn từng phần.', 'power-schedule-manager' ); ?></p>
				</article>
				<article class="psm-settings-guide">
					<h3><?php esc_html_e( 'Shortcode từng lớp tiếng Việt', 'power-schedule-manager' ); ?></h3>
					<code>[power_schedule_weather_rain]</code><br>
					<code>[power_schedule_weather_wind]</code><br>
					<code>[power_schedule_weather_temperature]</code><br>
					<code>[power_schedule_weather_clouds]</code><br>
					<code>[power_schedule_weather_snow]</code><br>
					<code>[power_schedule_weather_thunderstorms]</code>
				</article>
			</div>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="map">
			<h2>
				<?php
				esc_html_e(
					'Cấu hình bản đồ',
					'power-schedule-manager'
				);
				?>
			</h2>

			<p>
				<?php
				esc_html_e(
					'Bản đồ chỉ được tải ở frontend khi một lịch điện có dữ liệu vị trí.',
					'power-schedule-manager'
				);
				?>
			</p>

			<div
				class="psm-map-configuration-status"
				data-psm-map-settings-status
				data-osm-tile-url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
				data-enabled-text="<?php esc_attr_e( 'Bản đồ đang được bật bằng OpenStreetMap.', 'power-schedule-manager' ); ?>"
				data-maptiler-text="<?php esc_attr_e( 'Bản đồ đang dùng MapTiler.', 'power-schedule-manager' ); ?>"
				data-stadia-text="<?php esc_attr_e( 'Bản đồ đang dùng Stadia Maps.', 'power-schedule-manager' ); ?>"
				data-custom-text="<?php esc_attr_e( 'Bản đồ đang dùng máy chủ tile tùy chỉnh.', 'power-schedule-manager' ); ?>"
				data-disabled-text="<?php esc_attr_e( 'Bản đồ đang tắt; các nút xem bản đồ sẽ được ẩn.', 'power-schedule-manager' ); ?>"
			>
				<span
					class="psm-status <?php echo 'disabled' === $map_provider ? 'psm-status--neutral' : 'psm-status--success'; ?>"
					data-psm-map-settings-status-label
				>
					<?php
					echo esc_html(
						match ( $map_provider ) {
							'maptiler' => __(
								'MapTiler đang được chọn',
								'power-schedule-manager'
							),
							'stadia' => __(
								'Stadia Maps đang được chọn',
								'power-schedule-manager'
							),
							'custom' => __(
								'Đang dùng tile tùy chỉnh',
								'power-schedule-manager'
							),
							'disabled' => __(
								'Bản đồ đang tắt',
								'power-schedule-manager'
							),
							default => __(
								'OpenStreetMap đang hoạt động',
								'power-schedule-manager'
							),
						}
					);
					?>
				</span>

				<p>
					<?php
					esc_html_e(
						'Để nút bản đồ xuất hiện, sự kiện phải có ít nhất một vị trí với tọa độ hoặc GeoJSON hợp lệ.',
						'power-schedule-manager'
					);
					?>
				</p>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="psm-map-provider">
								<?php
								esc_html_e(
									'Nhà cung cấp bản đồ',
									'power-schedule-manager'
								);
								?>
							</label>
						</th>

						<td>
							<select
								id="psm-map-provider"
								data-psm-map-provider
								name="<?php
								echo esc_attr(
									$option_name . '[map_provider]'
								);
								?>"
							>
								<option
									value="osm"
									<?php
									selected(
										$map_provider,
										'osm'
									);
									?>
								>
									<?php
									esc_html_e(
										'OpenStreetMap',
										'power-schedule-manager'
									);
									?>
								</option>

								<option
									value="maptiler"
									<?php selected( $map_provider, 'maptiler' ); ?>
								>
									<?php esc_html_e( 'MapTiler', 'power-schedule-manager' ); ?>
								</option>

								<option
									value="stadia"
									<?php selected( $map_provider, 'stadia' ); ?>
								>
									<?php esc_html_e( 'Stadia Maps', 'power-schedule-manager' ); ?>
								</option>

								<option
									value="custom"
									<?php
									selected(
										$map_provider,
										'custom'
									);
									?>
								>
									<?php
									esc_html_e(
										'Máy chủ tile tùy chỉnh',
										'power-schedule-manager'
									);
									?>
								</option>

								<option
									value="disabled"
									<?php
									selected(
										$map_provider,
										'disabled'
									);
									?>
								>
									<?php
									esc_html_e(
										'Tắt bản đồ',
										'power-schedule-manager'
									);
									?>
								</option>
							</select>

							<p class="description">
								<?php
								esc_html_e(
									'Khi tắt bản đồ, dữ liệu vị trí vẫn được giữ trong database nhưng không hiển thị ngoài website.',
									'power-schedule-manager'
								);
								?>
							</p>
						</td>
					</tr>

					<tr
						data-psm-map-provider-setting="maptiler"
						<?php echo 'maptiler' !== $map_provider ? 'hidden' : ''; ?>
					>
						<th scope="row">
							<label for="psm-maptiler-style"><?php esc_html_e( 'MapTiler map ID', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<input
								id="psm-maptiler-style"
								type="text"
								class="regular-text code"
								name="<?php echo esc_attr( $option_name . '[maptiler_style]' ); ?>"
								value="<?php echo esc_attr( $maptiler_style ); ?>"
								placeholder="streets-v4"
								autocomplete="off"
							>
							<p class="description">
								<?php esc_html_e( 'Ví dụ streets-v4. Plugin dùng endpoint raster 512 px chính thức cho Leaflet.', 'power-schedule-manager' ); ?>
							</p>
							<label for="psm-maptiler-key"><strong><?php esc_html_e( 'API key', 'power-schedule-manager' ); ?></strong></label><br>
							<input
								id="psm-maptiler-key"
								type="password"
								class="regular-text code"
								maxlength="4096"
								name="<?php echo esc_attr( $option_name . '[maptiler_key]' ); ?>"
								value=""
								autocomplete="new-password"
								spellcheck="false"
								placeholder="<?php esc_attr_e( 'Để trống để giữ key đã lưu', 'power-schedule-manager' ); ?>"
							>
							<p class="description">
								<?php
								echo esc_html(
									match ( $maptiler_key_source ) {
										'environment' => __( 'Đang dùng key từ cấu hình máy chủ.', 'power-schedule-manager' ),
										'admin' => __( 'Key đã được lưu mã hóa.', 'power-schedule-manager' ),
										default => __( 'Chưa có key; MapTiler sẽ không được tải.', 'power-schedule-manager' ),
									}
								);
								?>
							</p>
							<?php if ( '' !== $maptiler_key_encrypted ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[maptiler_key_clear]' ); ?>" value="1"> <?php esc_html_e( 'Xóa key đã lưu', 'power-schedule-manager' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>

					<tr
						data-psm-map-provider-setting="stadia"
						<?php echo 'stadia' !== $map_provider ? 'hidden' : ''; ?>
					>
						<th scope="row">
							<label for="psm-stadia-style"><?php esc_html_e( 'Phong cách Stadia Maps', 'power-schedule-manager' ); ?></label>
						</th>
						<td>
							<select
								id="psm-stadia-style"
								name="<?php echo esc_attr( $option_name . '[stadia_style]' ); ?>"
							>
								<?php
								$stadia_styles = array(
									'alidade_smooth'      => __( 'Alidade Smooth', 'power-schedule-manager' ),
									'alidade_smooth_dark' => __( 'Alidade Smooth Dark', 'power-schedule-manager' ),
									'outdoors'            => __( 'Outdoors', 'power-schedule-manager' ),
									'osm_bright'          => __( 'OSM Bright', 'power-schedule-manager' ),
									'stamen_toner_lite'   => __( 'Stamen Toner Lite', 'power-schedule-manager' ),
									'stamen_terrain'      => __( 'Stamen Terrain', 'power-schedule-manager' ),
								);
								foreach ( $stadia_styles as $style_key => $style_label ) :
									?>
									<option value="<?php echo esc_attr( $style_key ); ?>" <?php selected( $stadia_style, $style_key ); ?>>
										<?php echo esc_html( $style_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Stadia Maps cung cấp tile raster tương thích trực tiếp với Leaflet.', 'power-schedule-manager' ); ?>
							</p>
							<label for="psm-stadia-key"><strong><?php esc_html_e( 'API key', 'power-schedule-manager' ); ?></strong></label><br>
							<input
								id="psm-stadia-key"
								type="password"
								class="regular-text code"
								maxlength="4096"
								name="<?php echo esc_attr( $option_name . '[stadia_key]' ); ?>"
								value=""
								autocomplete="new-password"
								spellcheck="false"
								placeholder="<?php esc_attr_e( 'Để trống để giữ key đã lưu', 'power-schedule-manager' ); ?>"
							>
							<p class="description">
								<?php
								echo esc_html(
									match ( $stadia_key_source ) {
										'environment' => __( 'Đang dùng key từ cấu hình máy chủ.', 'power-schedule-manager' ),
										'admin' => __( 'Key đã được lưu mã hóa.', 'power-schedule-manager' ),
										default => __( 'Chưa có key; Stadia Maps sẽ không được tải.', 'power-schedule-manager' ),
									}
								);
								?>
							</p>
							<?php if ( '' !== $stadia_key_encrypted ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $option_name . '[stadia_key_clear]' ); ?>" value="1"> <?php esc_html_e( 'Xóa key đã lưu', 'power-schedule-manager' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>

					<tr
						class="psm-custom-map-setting"
						data-psm-map-provider-setting="custom"
						<?php echo 'custom' !== $map_provider ? 'hidden' : ''; ?>
					>
						<th scope="row">
							<label for="psm-map-tile-url">
								<?php
								esc_html_e(
									'URL tile tùy chỉnh',
									'power-schedule-manager'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="psm-map-tile-url"
								name="<?php
								echo esc_attr(
									$option_name . '[map_tile_url]'
								);
								?>"
								class="large-text code"
								value="<?php echo esc_attr( $map_tile_url ); ?>"
								placeholder="https://tiles.example.com/{z}/{x}/{y}.png"
								autocomplete="off"
								spellcheck="false"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Chỉ chấp nhận HTTPS và phải chứa đủ {z}, {x}, {y}. Không nhập JavaScript hoặc HTML.',
									'power-schedule-manager'
								);
								?>
							</p>
						</td>
					</tr>

					<tr
						class="psm-custom-map-setting"
						data-psm-map-provider-setting="custom"
						<?php echo 'custom' !== $map_provider ? 'hidden' : ''; ?>
					>
						<th scope="row">
							<label for="psm-map-attribution">
								<?php
								esc_html_e(
									'Ghi nguồn bản đồ',
									'power-schedule-manager'
								);
								?>
							</label>
						</th>

						<td>
							<textarea
								id="psm-map-attribution"
								name="<?php
								echo esc_attr(
									$option_name . '[map_attribution]'
								);
								?>"
								class="large-text"
								rows="3"
							><?php echo esc_textarea( $map_attribution ); ?></textarea>

							<p class="description">
								<?php
								esc_html_e(
									'Có thể sử dụng liên kết HTML. Các thẻ hoặc thuộc tính không an toàn sẽ bị loại bỏ.',
									'power-schedule-manager'
								);
								?>
							</p>
						</td>
					</tr>

					<tr data-psm-map-zoom-setting>
						<th scope="row">
							<label for="psm-map-max-zoom">
								<?php
								esc_html_e(
									'Mức thu phóng tối đa',
									'power-schedule-manager'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="number"
								id="psm-map-max-zoom"
								name="<?php
								echo esc_attr(
									$option_name . '[map_max_zoom]'
								);
								?>"
								value="<?php echo esc_attr( (string) $map_max_zoom ); ?>"
								min="1"
								max="20"
								step="1"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Giá trị từ 1 đến 20. OpenStreetMap thường phù hợp ở mức 18 hoặc 19.',
									'power-schedule-manager'
								);
								?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="psm-map-test">
				<button
					type="button"
					class="button"
					data-psm-test-map
				>
					<?php
					esc_html_e(
						'Kiểm tra máy chủ tile',
						'power-schedule-manager'
					);
					?>
				</button>

				<span
					data-psm-map-test-result
					data-testing-text="<?php esc_attr_e( 'Đang kiểm tra kết nối…', 'power-schedule-manager' ); ?>"
					data-success-text="<?php esc_attr_e( 'Máy chủ tile phản hồi thành công.', 'power-schedule-manager' ); ?>"
					data-error-text="<?php esc_attr_e( 'Không tải được tile. Hãy kiểm tra URL, HTTPS hoặc chính sách máy chủ.', 'power-schedule-manager' ); ?>"
					role="status"
					aria-live="polite"
				></span>
			</div>
		</section>

		<section class="psm-dashboard-panel psm-map-setup-guide" data-psm-settings-panel="map">
			<h2>
				<?php
				esc_html_e(
					'Cách để bản đồ hiển thị',
					'power-schedule-manager'
				);
				?>
			</h2>

			<ol>
				<li>
					<?php
					esc_html_e(
						'Chọn OpenStreetMap, MapTiler, Stadia Maps hoặc cấu hình máy chủ tile HTTPS hợp lệ.',
						'power-schedule-manager'
					);
					?>
				</li>
				<li>
					<?php
					esc_html_e(
						'Mở Thư viện bản đồ và tạo tuyến đường hoặc khu vực đúng đơn vị điện lực.',
						'power-schedule-manager'
					);
					?>
				</li>
				<li>
					<?php
					esc_html_e(
						'Tìm tuyến từ OpenStreetMap hoặc lưu GeoJSON hợp lệ, sau đó bổ sung các bí danh thường xuất hiện trong lịch.',
						'power-schedule-manager'
					);
					?>
				</li>
				<li>
					<?php
					esc_html_e(
						'Plugin tự đối chiếu tên và bí danh với nội dung khu vực. Màn sửa bài chỉ hiển thị tóm tắt liên kết để kiểm tra.',
						'power-schedule-manager'
					);
					?>
				</li>
			</ol>

			<p>
				<a
					class="button"
					href="<?php echo esc_url( $place_library_url ); ?>"
				>
					<?php
					esc_html_e(
						'Mở Thư viện bản đồ',
						'power-schedule-manager'
					);
					?>
				</a>
			</p>
		</section>

		<section class="psm-dashboard-panel" data-psm-settings-panel="map">
			<h2>
				<?php
				esc_html_e(
					'Quyền riêng tư và hiệu năng',
					'power-schedule-manager'
				);
				?>
			</h2>

			<ul class="ul-disc">
				<li>
					<?php
					esc_html_e(
						'Thư viện bản đồ chỉ được tải khi trang thực sự có bản đồ.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Trình duyệt của khách truy cập có thể kết nối đến máy chủ tile đã chọn để tải hình ảnh bản đồ.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Dữ liệu GeoJSON được kiểm tra và chuẩn hóa trước khi lưu.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Plugin không gửi dữ liệu lịch điện đến dịch vụ geocoding bên ngoài.',
						'power-schedule-manager'
					);
					?>
				</li>

				<li>
					<?php
					esc_html_e(
						'Thay đổi cài đặt bản đồ không xóa các vị trí đã lưu.',
						'power-schedule-manager'
					);
					?>
				</li>
			</ul>
		</section>

		<?php
		submit_button(
			__(
				'Lưu cài đặt',
				'power-schedule-manager'
			)
		);
		?>
	</form>
</div>
