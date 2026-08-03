<?php
/**
 * Read-only application API and anti-scraping controls.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$application_settings = get_option(
	POWER_SCHEDULE_MANAGER_SETTINGS_OPTION,
	array()
);
$application_settings = is_array( $application_settings )
	? $application_settings
	: array();
$application_api_enabled = ! empty( $application_settings['api_enabled'] );
$application_api_limit = min(
	3000,
	max( 30, absint( $application_settings['api_rate_limit'] ?? 180 ) )
);
$application_api_stats = Power_Schedule_Manager_API::security_stats();
$application_api_clients = Power_Schedule_Manager_API::client_tokens();
$application_new_token = Power_Schedule_Manager_API::consume_new_client_token();
$application_notice = isset( $_GET['psm_notice'] )
	&& is_scalar( $_GET['psm_notice'] )
		? sanitize_key( wp_unslash( (string) $_GET['psm_notice'] ) )
		: '';
?>
<section class="psm-dashboard-panel psm-data-source-panel" data-psm-settings-panel="application-api">
	<header class="psm-data-source-panel__header">
		<div><div>
			<h2><?php esc_html_e( 'API ứng dụng và chống thu thập trái phép', 'power-schedule-manager' ); ?></h2>
			<p><?php esc_html_e( 'API v1 chỉ đọc dành cho ứng dụng và website được cấp token riêng. Mỗi client có hạn mức và có thể thu hồi độc lập.', 'power-schedule-manager' ); ?></p>
		</div></div>
		<span class="psm-data-source-panel__badge <?php echo $application_api_enabled ? 'is-on' : 'is-off'; ?>">
			<?php echo $application_api_enabled ? esc_html__( 'API đang mở', 'power-schedule-manager' ) : esc_html__( 'API đang tắt', 'power-schedule-manager' ); ?>
		</span>
	</header>

	<?php if ( array() !== $application_new_token ) : ?>
		<div class="notice notice-success inline psm-api-token-created">
			<p><strong><?php esc_html_e( 'Token mới — chỉ hiển thị một lần', 'power-schedule-manager' ); ?></strong></p>
			<p><?php echo esc_html( (string) ( $application_new_token['name'] ?? '' ) ); ?></p>
			<p><code><?php echo esc_html( (string) ( $application_new_token['token'] ?? '' ) ); ?></code></p>
			<p><?php esc_html_e( 'Hãy sao chép và lưu trong secret/environment variable của ứng dụng. Plugin chỉ lưu mã băm và không thể hiện lại token này.', 'power-schedule-manager' ); ?></p>
		</div>
	<?php elseif ( 'api_token_revoked' === $application_notice ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Token đã được thu hồi.', 'power-schedule-manager' ); ?></p></div>
	<?php elseif ( in_array( $application_notice, array( 'api_token_invalid', 'api_token_error' ), true ) ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Chưa thể tạo token. Hãy kiểm tra tên client và ngày hết hạn.', 'power-schedule-manager' ); ?></p></div>
	<?php endif; ?>

	<form class="psm-data-source-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_save_application_api">
		<?php wp_nonce_field( 'psm_save_application_api' ); ?>
		<div class="psm-data-source-form__toggles">
			<label>
				<input type="checkbox" name="api_enabled" value="1" <?php checked( $application_api_enabled ); ?>>
				<span><strong><?php esc_html_e( 'Bật API v1 có bảo vệ', 'power-schedule-manager' ); ?></strong><small><?php esc_html_e( 'Mọi request đều phải gửi Bearer token hợp lệ; không dùng Application Password.', 'power-schedule-manager' ); ?></small></span>
			</label>
		</div>
		<div class="psm-data-source-form__grid psm-application-api__settings">
			<div>
				<label for="psm-application-api-limit"><strong><?php esc_html_e( 'Hạn mức mặc định khi tạo token', 'power-schedule-manager' ); ?></strong></label>
				<div><input id="psm-application-api-limit" type="number" min="30" max="3000" name="api_rate_limit" value="<?php echo esc_attr( (string) $application_api_limit ); ?>"> <span><?php esc_html_e( 'request/phút', 'power-schedule-manager' ); ?></span></div>
				<p class="description"><?php esc_html_e( 'Mỗi token mới có thể đặt hạn mức riêng. API trả HTTP 429 và Retry-After khi client vượt ngưỡng.', 'power-schedule-manager' ); ?></p>
			</div>
			<div class="psm-data-source-panel__note">
				<strong><?php esc_html_e( 'Endpoint API', 'power-schedule-manager' ); ?></strong>
				<p><code><?php echo esc_html( rest_url( Power_Schedule_Manager_API::REST_NAMESPACE . '/' ) ); ?></code></p>
				<p><?php esc_html_e( 'Danh sách dùng cursor ký số, tối đa 100 bản ghi và khoảng ngày tối đa 31 ngày.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>
		<footer class="psm-data-source-form__actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu trạng thái API', 'power-schedule-manager' ); ?></button>
		</footer>
	</form>

	<section class="psm-api-clients" aria-labelledby="psm-api-clients-title">
		<header><div>
			<h3 id="psm-api-clients-title"><?php esc_html_e( 'Token theo từng website hoặc ứng dụng', 'power-schedule-manager' ); ?></h3>
			<p><?php esc_html_e( 'Tạo một token cho mỗi nơi tích hợp để theo dõi, giới hạn và thu hồi riêng mà không làm gián đoạn client khác.', 'power-schedule-manager' ); ?></p>
		</div></header>
		<form class="psm-api-client-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="psm_create_api_client_token">
			<?php wp_nonce_field( 'psm_create_api_client_token' ); ?>
			<label><span><?php esc_html_e( 'Tên website / ứng dụng', 'power-schedule-manager' ); ?></span><input name="client_name" required maxlength="100" placeholder="<?php esc_attr_e( 'Ví dụ: Website đối tác A', 'power-schedule-manager' ); ?>"></label>
			<label><span><?php esc_html_e( 'Hạn mức', 'power-schedule-manager' ); ?></span><input type="number" name="client_rate_limit" min="30" max="3000" value="<?php echo esc_attr( (string) $application_api_limit ); ?>"><small><?php esc_html_e( 'request/phút', 'power-schedule-manager' ); ?></small></label>
			<label><span><?php esc_html_e( 'Origin HTTPS (không bắt buộc)', 'power-schedule-manager' ); ?></span><input type="url" name="client_origin" placeholder="https://doitac.example"></label>
			<label><span><?php esc_html_e( 'Ngày hết hạn (không bắt buộc)', 'power-schedule-manager' ); ?></span><input type="date" name="client_expires"></label>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Tạo token', 'power-schedule-manager' ); ?></button>
		</form>
		<div class="psm-table-scroll">
			<table class="widefat striped psm-api-client-table">
				<thead><tr><th><?php esc_html_e( 'Client', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Token', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Hạn mức', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Dùng gần nhất', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Trạng thái', 'power-schedule-manager' ); ?></th><th><?php esc_html_e( 'Thao tác', 'power-schedule-manager' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $application_api_clients as $client ) : ?>
					<?php
					$revoked = '' !== (string) ( $client['revoked_at_utc'] ?? '' );
					$expired = '' !== (string) ( $client['expires_at_utc'] ?? '' )
						&& strtotime( (string) $client['expires_at_utc'] . ' UTC' ) < time();
					?>
					<tr>
						<td><strong><?php echo esc_html( (string) ( $client['name'] ?? '' ) ); ?></strong><?php if ( '' !== (string) ( $client['allowed_origin'] ?? '' ) ) : ?><br><small><?php echo esc_html( (string) $client['allowed_origin'] ); ?></small><?php endif; ?></td>
						<td><code><?php echo esc_html( (string) ( $client['prefix'] ?? '' ) ); ?>…</code></td>
						<td><?php echo esc_html( number_format_i18n( absint( $client['rate_limit'] ?? 0 ) ) ); ?>/phút</td>
						<td><?php echo '' !== (string) ( $client['last_used_at_utc'] ?? '' ) ? esc_html( get_date_from_gmt( (string) $client['last_used_at_utc'], 'H:i d/m/Y' ) ) : '—'; ?></td>
						<td><span class="psm-api-client-status <?php echo $revoked || $expired ? 'is-off' : 'is-on'; ?>"><?php echo $revoked ? esc_html__( 'Đã thu hồi', 'power-schedule-manager' ) : ( $expired ? esc_html__( 'Hết hạn', 'power-schedule-manager' ) : esc_html__( 'Đang hoạt động', 'power-schedule-manager' ) ); ?></span></td>
						<td>
							<?php if ( ! $revoked ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="psm_revoke_api_client_token">
									<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) ( $client['id'] ?? '' ) ); ?>">
									<?php wp_nonce_field( 'psm_revoke_api_client_token_' . (string) ( $client['id'] ?? '' ) ); ?>
									<button type="submit" class="button-link-delete"><?php esc_html_e( 'Thu hồi', 'power-schedule-manager' ); ?></button>
								</form>
							<?php else : ?>—<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( array() === $application_api_clients ) : ?><tr><td colspan="6"><?php esc_html_e( 'Chưa có token. API sẽ từ chối mọi request cho đến khi bạn tạo ít nhất một client.', 'power-schedule-manager' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<p class="description"><code>Authorization: Bearer psm_…</code> · <?php esc_html_e( 'Không nhúng token bí mật vào JavaScript công khai. Website đối tác nên gọi API từ máy chủ của họ.', 'power-schedule-manager' ); ?></p>
	</section>

	<div class="psm-application-api__stats" aria-label="<?php esc_attr_e( 'Thống kê truy cập API', 'power-schedule-manager' ); ?>">
		<article><span><?php esc_html_e( 'Tổng request', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( (string) $application_api_stats['total'] ); ?></strong></article>
		<article><span><?php esc_html_e( 'Được phép', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( (string) $application_api_stats['allowed'] ); ?></strong></article>
		<article><span><?php esc_html_e( 'Đã chặn', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( (string) $application_api_stats['blocked'] ); ?></strong></article>
		<article><span><?php esc_html_e( 'Dấu hiệu tự động', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( (string) $application_api_stats['suspicious'] ); ?></strong></article>
		<article><span><?php esc_html_e( 'Dấu vết client', 'power-schedule-manager' ); ?></span><strong><?php echo esc_html( (string) $application_api_stats['unique_clients'] ); ?></strong></article>
	</div>

	<details class="psm-admin-details">
		<summary><?php esc_html_e( 'Giám sát và lớp bảo vệ bổ sung', 'power-schedule-manager' ); ?></summary>
		<div class="psm-admin-details__body psm-application-api__security">
			<div>
				<h3><?php esc_html_e( 'Cách phát hiện crawl bất thường', 'power-schedule-manager' ); ?></h3>
				<p><?php esc_html_e( 'Plugin đếm request, request bị chặn, đường dẫn gọi nhiều và user-agent có dấu hiệu tự động. IP và user-agent thô không được lưu.', 'power-schedule-manager' ); ?></p>
				<?php if ( ! empty( $application_api_stats['paths'] ) ) : ?><ul><?php foreach ( $application_api_stats['paths'] as $path => $count ) : ?><li><code><?php echo esc_html( (string) $path ); ?></code> <strong><?php echo esc_html( (string) $count ); ?></strong></li><?php endforeach; ?></ul><?php endif; ?>
			</div>
			<div>
				<h3><?php esc_html_e( 'Lớp bảo vệ nên bật', 'power-schedule-manager' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Tắt API nếu chưa có app hoặc đối tác thực tế.', 'power-schedule-manager' ); ?></li>
					<li><?php esc_html_e( 'Cấp token riêng, đặt hạn mức vừa đủ và thu hồi khi ngừng tích hợp.', 'power-schedule-manager' ); ?></li>
					<li><?php esc_html_e( 'Áp dụng Cloudflare Rate Limiting cho /wp-json/power-schedule/v1/.', 'power-schedule-manager' ); ?></li>
				</ol>
				<p><code>http.request.uri.path starts_with "/wp-json/power-schedule/v1/"</code></p>
			</div>
		</div>
	</details>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="psm_reset_application_api_stats">
		<?php wp_nonce_field( 'psm_reset_application_api_stats' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Đặt lại thống kê API', 'power-schedule-manager' ); ?></button>
	</form>
</section>
