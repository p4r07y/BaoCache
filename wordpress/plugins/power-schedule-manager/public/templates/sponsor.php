<?php
/**
 * Standalone public sponsorship inquiry page.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$settings = is_array( $psm_template_args['settings'] ?? null )
	? $psm_template_args['settings']
	: array();
$action = (string) ( $psm_template_args['action'] ?? '' );
$return = (string) ( $psm_template_args['return'] ?? '' );
$nonce = (string) ( $psm_template_args['nonce'] ?? '' );
$turnstile_site_key = (string) (
	$psm_template_args['turnstile_site_key'] ?? ''
);
$title = sanitize_text_field(
	(string) (
		$settings['sponsor_title']
		?? 'Hợp tác cùng Cúp Điện Lâm Đồng'
	)
);
if ( str_contains( strtolower( $title ), '.com' ) ) {
	$title = 'Hợp tác cùng Cúp Điện Lâm Đồng';
}
$description = sanitize_textarea_field(
	(string) (
		$settings['sponsor_description']
		?? 'Trao đổi các sáng kiến nội dung, truyền thông và hợp tác phù hợp với một nền tảng thông tin cộng đồng độc lập.'
	)
);
$media_kit_url = esc_url(
	(string) ( $settings['sponsor_media_kit_url'] ?? '' )
);
$error = isset( $_GET['psm_sponsor_error'] )
	&& is_scalar( $_GET['psm_sponsor_error'] )
		? sanitize_key(
			wp_unslash( (string) $_GET['psm_sponsor_error'] )
		)
		: '';
$sent = isset( $_GET['psm_sponsor'] )
	&& is_scalar( $_GET['psm_sponsor'] )
	&& 'sent' === sanitize_key(
		wp_unslash( (string) $_GET['psm_sponsor'] )
	);
$error_messages = array(
	'disabled' => 'Kênh hợp tác tài trợ hiện đang tạm dừng.',
	'invalid' => 'Vui lòng kiểm tra thông tin liên hệ, mục đích liên hệ và xác nhận đồng ý.',
	'rate' => 'Bạn đã gửi quá nhiều lần trong thời gian ngắn. Vui lòng thử lại sau.',
	'captcha' => 'Vui lòng hoàn tất bước xác minh bảo mật.',
	'captcha_failed' => 'Xác minh bảo mật không hợp lệ hoặc đã hết hạn.',
	'captcha_unavailable' => 'Chưa thể kết nối dịch vụ xác minh bảo mật. Vui lòng thử lại sau.',
	'captcha_config' => 'Biểu mẫu đang được bảo trì cấu hình bảo mật.',
	'storage' => 'Chưa thể lưu đề nghị hợp tác lúc này. Vui lòng thử lại sau.',
);
?>
<section class="psm-sponsor-page" aria-labelledby="psm-sponsor-title">
	<header class="psm-sponsor-page__intro">
		<div class="psm-sponsor-partnership__copy">
			<p class="psm-sponsor-partnership__eyebrow"><?php esc_html_e( 'Kết nối cộng đồng', 'power-schedule-manager' ); ?></p>
			<h2 id="psm-sponsor-title"><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="psm-sponsor-partnership__actions">
				<a class="psm-button psm-button--primary" href="#psm-sponsor-form"><?php esc_html_e( 'Gửi lời nhắn', 'power-schedule-manager' ); ?></a>
				<?php if ( '' !== $media_kit_url ) : ?>
					<a class="psm-button psm-button--secondary" href="<?php echo esc_url( $media_kit_url ); ?>"><?php esc_html_e( 'Xem hồ sơ tài trợ', 'power-schedule-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<ul class="psm-sponsor-partnership__principles">
			<li><strong><?php esc_html_e( 'Tự nguyện', 'power-schedule-manager' ); ?></strong><span><?php esc_html_e( 'Biểu mẫu chỉ để kết nối, góp ý hoặc trao đổi một ý tưởng.', 'power-schedule-manager' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Không giao dịch', 'power-schedule-manager' ); ?></strong><span><?php esc_html_e( 'Website không nhận tiền, không tạo nghĩa vụ thanh toán và không cam kết lợi ích.', 'power-schedule-manager' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Tôn trọng dữ liệu', 'power-schedule-manager' ); ?></strong><span><?php esc_html_e( 'Chỉ thu thông tin cần thiết để phản hồi lời nhắn của bạn.', 'power-schedule-manager' ); ?></span></li>
		</ul>
	</header>

	<aside class="psm-sponsor-independence">
		<strong><?php esc_html_e( 'Nền tảng độc lập', 'power-schedule-manager' ); ?></strong>
		<p><?php esc_html_e( 'Cúp Điện Lâm Đồng không trực thuộc hoặc đại diện cho EVN hay đơn vị điện lực nào. Đây không phải trang quyên góp, tiếp nhận tiền, bán dịch vụ hay ký kết tự động; mọi trao đổi chỉ có hiệu lực khi hai bên liên hệ và thống nhất riêng bằng kênh phù hợp.', 'power-schedule-manager' ); ?></p>
	</aside>

	<section class="psm-sponsor-process" aria-labelledby="psm-sponsor-process-title">
		<header>
			<p class="psm-sponsor-partnership__eyebrow"><?php esc_html_e( 'Quy trình rõ ràng', 'power-schedule-manager' ); ?></p>
			<h3 id="psm-sponsor-process-title"><?php esc_html_e( 'Từ nhu cầu đến phương án phù hợp', 'power-schedule-manager' ); ?></h3>
		</header>
		<ol>
			<li><span>01</span><strong><?php esc_html_e( 'Chọn mục đích liên hệ', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Góp ý, kết nối, đề xuất nội dung hoặc đồng hành dự án cộng đồng.', 'power-schedule-manager' ); ?></small></li>
			<li><span>02</span><strong><?php esc_html_e( 'Gửi thông tin tối thiểu', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Chỉ để quản trị viên có thể phản hồi khi cần.', 'power-schedule-manager' ); ?></small></li>
			<li><span>03</span><strong><?php esc_html_e( 'Trao đổi riêng khi phù hợp', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Không có cam kết, chi phí hay hiển thị tự động từ biểu mẫu này.', 'power-schedule-manager' ); ?></small></li>
		</ol>
	</section>

	<section id="psm-sponsor-form" class="psm-sponsor-form-wrap" aria-labelledby="psm-sponsor-form-title">
		<div>
			<p class="psm-sponsor-partnership__eyebrow"><?php esc_html_e( 'Bắt đầu trao đổi', 'power-schedule-manager' ); ?></p>
			<h3 id="psm-sponsor-form-title"><?php esc_html_e( 'Bạn muốn kết nối về điều gì?', 'power-schedule-manager' ); ?></h3>
			<p><?php esc_html_e( 'Không nhập số tiền hoặc vị trí quảng cáo. Hãy để lại một lời nhắn, chúng tôi chỉ dùng thông tin này để phản hồi.', 'power-schedule-manager' ); ?></p>
		</div>

		<?php if ( $sent ) : ?>
			<p class="psm-sponsor-form__success" role="status"><?php esc_html_e( 'Đã nhận lời nhắn. Nếu phù hợp, quản trị viên sẽ phản hồi qua email hoặc số điện thoại bạn để lại.', 'power-schedule-manager' ); ?></p>
		<?php else : ?>
			<?php if ( '' !== $error && isset( $error_messages[ $error ] ) ) : ?>
				<p class="psm-sponsor-form__error" role="alert"><?php echo esc_html( $error_messages[ $error ] ); ?></p>
			<?php endif; ?>
			<form class="psm-sponsor-form" method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="action" value="psm_submit_sponsorship">
				<input type="hidden" name="_psm_sponsor_nonce" value="<?php echo esc_attr( $nonce ); ?>">
				<input type="hidden" name="return_url" value="<?php echo esc_url( $return ); ?>">
				<p class="psm-sponsor-form__trap" aria-hidden="true"><label>Company URL <input type="text" name="sponsor_company_url" tabindex="-1" autocomplete="off"></label></p>
				<div class="psm-sponsor-form__fields">
					<label><?php esc_html_e( 'Tên cá nhân, nhóm hoặc tổ chức', 'power-schedule-manager' ); ?><input type="text" name="sponsor_company" maxlength="191" autocomplete="organization"></label>
					<label><?php esc_html_e( 'Người liên hệ', 'power-schedule-manager' ); ?> <span aria-hidden="true">*</span><input type="text" name="sponsor_contact_name" maxlength="191" required autocomplete="name"></label>
					<label><?php esc_html_e( 'Email công việc', 'power-schedule-manager' ); ?> <span aria-hidden="true">*</span><input type="email" name="sponsor_email" maxlength="191" required autocomplete="email"></label>
					<label><?php esc_html_e( 'Số điện thoại', 'power-schedule-manager' ); ?><input type="tel" name="sponsor_phone" maxlength="18" autocomplete="tel" inputmode="tel" placeholder="0912345678"></label>
					<label><?php esc_html_e( 'Website hoặc kênh tham khảo', 'power-schedule-manager' ); ?><input type="url" name="sponsor_website" maxlength="500" autocomplete="url" placeholder="https://example.com"></label>
					<label><?php esc_html_e( 'Mục đích liên hệ', 'power-schedule-manager' ); ?> <span aria-hidden="true">*</span><select name="sponsor_intent" required><option value=""><?php esc_html_e( 'Chọn một mục đích', 'power-schedule-manager' ); ?></option><option value="feedback"><?php esc_html_e( 'Góp ý dữ liệu hoặc trải nghiệm', 'power-schedule-manager' ); ?></option><option value="connect"><?php esc_html_e( 'Kết nối và trao đổi', 'power-schedule-manager' ); ?></option><option value="community"><?php esc_html_e( 'Đồng hành dự án cộng đồng', 'power-schedule-manager' ); ?></option><option value="content"><?php esc_html_e( 'Gợi ý nội dung hoặc chuyên mục', 'power-schedule-manager' ); ?></option><option value="other"><?php esc_html_e( 'Khác', 'power-schedule-manager' ); ?></option></select></label>
				</div>
				<label><?php esc_html_e( 'Lời nhắn', 'power-schedule-manager' ); ?><textarea name="sponsor_message" rows="4" maxlength="1500" placeholder="<?php esc_attr_e( 'Ví dụ: góp ý thông tin khu vực, đề xuất một nội dung hữu ích hoặc mong muốn kết nối…', 'power-schedule-manager' ); ?>"></textarea></label>
				<?php if ( '' !== $turnstile_site_key ) : ?>
					<div class="psm-sponsor-form__turnstile"><div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>" data-theme="auto" data-size="flexible" data-action="sponsor_submit"></div></div>
				<?php endif; ?>
				<label class="psm-sponsor-form__consent"><input type="checkbox" name="sponsor_consent" value="1" required> <?php esc_html_e( 'Tôi đồng ý để website lưu thông tin trên nhằm phản hồi lời nhắn. Tôi hiểu đây không phải biểu mẫu thanh toán, quyên góp hoặc cam kết hợp đồng.', 'power-schedule-manager' ); ?></label>
				<button class="psm-button psm-button--primary" type="submit"><?php esc_html_e( 'Gửi lời nhắn', 'power-schedule-manager' ); ?> →</button>
			</form>
		<?php endif; ?>
	</section>
</section>
