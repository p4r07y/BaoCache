<?php
/**
 * Backup and safe recovery centre.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

$summary = isset( $psm_admin_args['summary'] )
	&& is_array( $psm_admin_args['summary'] )
	? $psm_admin_args['summary']
	: array();

$notice = isset( $psm_admin_args['notice'] )
	&& is_array( $psm_admin_args['notice'] )
	? $psm_admin_args['notice']
	: null;

$tables = isset( $summary['tables'] ) && is_array( $summary['tables'] )
	? $summary['tables']
	: array();

$core_rows = 0;

foreach ( $tables as $table_key => $row_count ) {
	if (
		Power_Schedule_Manager_Database::IMPORT_RUNS === $table_key
		|| Power_Schedule_Manager_Database::EVENT_REVISIONS === $table_key
		|| ! is_numeric( $row_count )
	) {
		continue;
	}

	$core_rows += (int) $row_count;
}

$history_rows =
	(int) ( $tables[ Power_Schedule_Manager_Database::IMPORT_RUNS ] ?? 0 )
	+ (int) (
		$tables[ Power_Schedule_Manager_Database::EVENT_REVISIONS ] ?? 0
	);

$can_restore = ! empty( $summary['can_restore'] );
$max_upload  = absint( $summary['max_upload'] ?? 0 );
$cloud = isset( $psm_admin_args['cloud'] )
	&& is_array( $psm_admin_args['cloud'] )
	? $psm_admin_args['cloud']
	: array();
?>

<div class="wrap psm-admin-wrap psm-backup">
	<div class="psm-system-health__header">
		<div>
			<h1><?php esc_html_e( 'Sao lưu và khôi phục', 'power-schedule-manager' ); ?></h1>
			<p class="psm-admin-lead">
				<?php esc_html_e( 'Tạo bản sao độc lập với tiền tố database và phục hồi an toàn khi cần chuyển máy chủ hoặc xử lý sự cố.', 'power-schedule-manager' ); ?>
			</p>
		</div>

		<div class="psm-system-health__meta">
			<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: Backup format version. */
						__( 'Định dạng backup v%d', 'power-schedule-manager' ),
						absint( $summary['format_version'] ?? 0 )
					)
				);
				?>
			</strong>
			<span class="psm-health-readonly">
				<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Checksum SHA-256', 'power-schedule-manager' ); ?>
			</span>
		</div>
	</div>

	<?php if ( is_array( $notice ) ) : ?>
		<?php $notice_type = 'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error'; ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> inline psm-backup__notice">
			<p>
				<strong><?php echo esc_html( (string) ( $notice['message'] ?? '' ) ); ?></strong>
			</p>

			<?php if ( isset( $notice['details'] ) && is_array( $notice['details'] ) ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Posts, 2: Events, 3: Places. */
							__(
								'Đã phục hồi %1$d bài lịch, %2$d sự kiện và %3$d địa điểm bản đồ.',
								'power-schedule-manager'
							),
							absint( $notice['details']['posts'] ?? 0 ),
							absint( $notice['details']['events'] ?? 0 ),
							absint( $notice['details']['places'] ?? 0 )
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="psm-backup__stats" aria-label="<?php esc_attr_e( 'Tóm tắt dữ liệu', 'power-schedule-manager' ); ?>">
		<div>
			<span class="dashicons dashicons-database" aria-hidden="true"></span>
			<p>
				<strong><?php echo esc_html( number_format_i18n( $core_rows ) ); ?></strong>
				<span><?php esc_html_e( 'hàng dữ liệu cốt lõi', 'power-schedule-manager' ); ?></span>
			</p>
		</div>
		<div>
			<span class="dashicons dashicons-admin-post" aria-hidden="true"></span>
			<p>
				<strong><?php echo esc_html( number_format_i18n( absint( $summary['posts'] ?? 0 ) ) ); ?></strong>
				<span><?php esc_html_e( 'bài lịch theo ngày', 'power-schedule-manager' ); ?></span>
			</p>
		</div>
		<div>
			<span class="dashicons dashicons-backup" aria-hidden="true"></span>
			<p>
				<strong><?php echo esc_html( number_format_i18n( $history_rows ) ); ?></strong>
				<span><?php esc_html_e( 'bản ghi lịch sử', 'power-schedule-manager' ); ?></span>
			</p>
		</div>
	</div>

	<div class="psm-backup__grid">
		<section class="psm-dashboard-panel psm-backup__card">
			<div class="psm-backup__icon psm-backup__icon--export">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
			</div>
			<div>
				<h2><?php esc_html_e( 'Tải bản sao lưu', 'power-schedule-manager' ); ?></h2>
				<p>
					<?php esc_html_e( 'Bao gồm cấu hình, đơn vị điện lực, bài lịch, sự kiện, quan hệ taxonomy và thư viện bản đồ. Tên bảng thật không được ghi vào file nên có thể chuyển sang website dùng tiền tố khác.', 'power-schedule-manager' ); ?>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="psm_export_backup">
				<?php wp_nonce_field( 'psm_export_backup' ); ?>

				<label class="psm-backup__choice">
					<input type="checkbox" name="include_history" value="1" checked>
					<span>
						<strong><?php esc_html_e( 'Kèm lịch sử nhập và revision', 'power-schedule-manager' ); ?></strong>
						<small><?php esc_html_e( 'Bản đầy đủ có thể chứa dữ liệu nguồn thô và có kích thước lớn hơn.', 'power-schedule-manager' ); ?></small>
					</span>
				</label>

				<button type="submit" class="button button-primary button-hero">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Tạo và tải backup', 'power-schedule-manager' ); ?>
				</button>
			</form>
		</section>

		<section class="psm-dashboard-panel psm-backup__card">
			<div class="psm-backup__icon psm-backup__icon--restore">
				<span class="dashicons dashicons-update-alt" aria-hidden="true"></span>
			</div>
			<div>
				<h2><?php esc_html_e( 'Khôi phục từ backup', 'power-schedule-manager' ); ?></h2>
				<p>
					<?php esc_html_e( 'Plugin đọc hết file, kiểm tra định dạng, thứ tự quan hệ và checksum trước khi ghi. Nếu một bước thất bại, transaction được hoàn tác.', 'power-schedule-manager' ); ?>
				</p>
			</div>

			<?php if ( $can_restore ) : ?>
				<div class="psm-backup__state is-ready">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<div>
						<strong><?php esc_html_e( 'Sẵn sàng khôi phục', 'power-schedule-manager' ); ?></strong>
						<span><?php esc_html_e( 'Chưa có dữ liệu vận hành; danh sách đơn vị seed không cản trở phục hồi.', 'power-schedule-manager' ); ?></span>
					</div>
				</div>
			<?php else : ?>
				<div class="psm-backup__state is-locked">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<div>
						<strong><?php esc_html_e( 'Khôi phục đang khóa', 'power-schedule-manager' ); ?></strong>
						<span><?php esc_html_e( 'Website đang có dữ liệu. Plugin không tự xóa, gộp hoặc ghi đè lịch đang vận hành.', 'power-schedule-manager' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<form
				method="post"
				enctype="multipart/form-data"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>
				<input type="hidden" name="action" value="psm_restore_backup">
				<?php wp_nonce_field( 'psm_restore_backup' ); ?>

				<label class="psm-backup__file">
					<span><?php esc_html_e( 'File .ndjson hoặc .jsonl', 'power-schedule-manager' ); ?></span>
					<input
						type="file"
						name="backup_file"
						accept=".ndjson,.jsonl,application/x-ndjson"
						required
						<?php disabled( ! $can_restore ); ?>
					>
					<small>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: Formatted upload limit. */
								__( 'Giới hạn file: %s.', 'power-schedule-manager' ),
								size_format( $max_upload )
							)
						);
						?>
					</small>
				</label>

				<label class="psm-backup__choice">
					<input
						type="checkbox"
						name="confirm_restore"
						value="1"
						required
						<?php disabled( ! $can_restore ); ?>
					>
					<span>
						<strong><?php esc_html_e( 'Tôi đã tạo backup hiện tại và hiểu điều kiện phục hồi', 'power-schedule-manager' ); ?></strong>
						<small><?php esc_html_e( 'Chỉ dùng file do Cúp Điện Lâm Đồng tạo ra.', 'power-schedule-manager' ); ?></small>
					</span>
				</label>

				<button
					type="submit"
					class="button button-secondary button-hero"
					<?php disabled( ! $can_restore ); ?>
				>
					<span class="dashicons dashicons-update-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Kiểm tra và khôi phục', 'power-schedule-manager' ); ?>
				</button>
			</form>
		</section>
	</div>

	<section class="psm-dashboard-panel psm-backup-cloud">
		<header>
			<p><?php esc_html_e( 'Lưu bản sao ngoài máy chủ', 'power-schedule-manager' ); ?></p>
			<h2><?php esc_html_e( 'Kết nối kho lưu trữ đám mây', 'power-schedule-manager' ); ?></h2>
			<span><?php esc_html_e( 'Thông tin bí mật được mã hóa. Nút kiểm tra chỉ thực hiện yêu cầu đọc, không tạo hoặc xóa tệp.', 'power-schedule-manager' ); ?></span>
		</header>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="psm_backup_cloud_save">
			<?php wp_nonce_field( 'psm_backup_cloud_save' ); ?>
			<div class="psm-backup-cloud__grid">
				<fieldset>
					<legend>Wasabi</legend>
					<label><span>Endpoint HTTPS</span><input type="url" name="wasabi_endpoint" value="<?php echo esc_attr( (string) ( $cloud['wasabi_endpoint'] ?? '' ) ); ?>" placeholder="https://s3.ap-southeast-1.wasabisys.com"></label>
					<div class="psm-backup-cloud__pair">
						<label><span><?php esc_html_e( 'Vùng', 'power-schedule-manager' ); ?></span><input type="text" name="wasabi_region" value="<?php echo esc_attr( (string) ( $cloud['wasabi_region'] ?? '' ) ); ?>"></label>
						<label><span>Bucket</span><input type="text" name="wasabi_bucket" value="<?php echo esc_attr( (string) ( $cloud['wasabi_bucket'] ?? '' ) ); ?>"></label>
					</div>
					<label><span><?php esc_html_e( 'Thư mục lưu', 'power-schedule-manager' ); ?></span><input type="text" name="wasabi_prefix" value="<?php echo esc_attr( (string) ( $cloud['wasabi_prefix'] ?? '' ) ); ?>"></label>
					<label><span>Access key</span><input type="text" autocomplete="off" name="wasabi_access_key" value="<?php echo esc_attr( (string) ( $cloud['wasabi_access_key'] ?? '' ) ); ?>"></label>
					<label><span>Secret key</span><input type="password" autocomplete="new-password" name="wasabi_secret" placeholder="<?php echo ! empty( $cloud['wasabi_has_secret'] ) ? esc_attr__( 'Đã lưu — để trống để giữ nguyên', 'power-schedule-manager' ) : ''; ?>"></label>
				</fieldset>
				<fieldset>
					<legend>Google Drive</legend>
					<label><span>OAuth Client ID</span><input type="text" autocomplete="off" name="google_client_id" value="<?php echo esc_attr( (string) ( $cloud['google_client_id'] ?? '' ) ); ?>"></label>
					<label><span>OAuth Client Secret</span><input type="password" autocomplete="new-password" name="google_client_secret" placeholder="<?php echo ! empty( $cloud['google_has_secret'] ) ? esc_attr__( 'Đã lưu — để trống để giữ nguyên', 'power-schedule-manager' ) : ''; ?>"></label>
					<label><span>Refresh token</span><input type="password" autocomplete="new-password" name="google_refresh_token" placeholder="<?php echo ! empty( $cloud['google_has_refresh_token'] ) ? esc_attr__( 'Đã lưu — để trống để giữ nguyên', 'power-schedule-manager' ) : ''; ?>"></label>
					<label><span><?php esc_html_e( 'ID thư mục đích', 'power-schedule-manager' ); ?></span><input type="text" name="google_folder_id" value="<?php echo esc_attr( (string) ( $cloud['google_folder_id'] ?? '' ) ); ?>"></label>
					<p><?php esc_html_e( 'Dùng OAuth scope drive.file để plugin chỉ quản lý các tệp do chính kết nối này tạo hoặc được cấp quyền.', 'power-schedule-manager' ); ?></p>
				</fieldset>
			</div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu kết nối an toàn', 'power-schedule-manager' ); ?></button>
		</form>
		<div class="psm-backup-cloud__tests">
			<?php foreach ( array( 'wasabi' => 'Wasabi', 'google' => 'Google Drive' ) as $provider => $label ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="psm_backup_cloud_test">
					<input type="hidden" name="provider" value="<?php echo esc_attr( $provider ); ?>">
					<?php wp_nonce_field( 'psm_backup_cloud_test' ); ?>
					<button type="submit" class="button"><?php echo esc_html( sprintf( __( 'Kiểm tra %s', 'power-schedule-manager' ), $label ) ); ?></button>
				</form>
			<?php endforeach; ?>
		</div>
		<div class="psm-backup-cloud__operations">
			<?php foreach ( array( 'wasabi' => 'Wasabi', 'google' => 'Google Drive' ) as $provider => $label ) :
				$latest = is_array( $cloud['latest'][ $provider ] ?? null )
					? $cloud['latest'][ $provider ]
					: array();
				?>
				<article>
					<div>
						<strong><?php echo esc_html( $label ); ?></strong>
						<span>
							<?php
							echo ! empty( $latest['name'] )
								? esc_html( (string) $latest['name'] )
								: esc_html__( 'Chưa có bản sao do plugin tạo', 'power-schedule-manager' );
							?>
						</span>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="psm_backup_cloud_create">
						<input type="hidden" name="provider" value="<?php echo esc_attr( $provider ); ?>">
						<?php wp_nonce_field( 'psm_backup_cloud_create' ); ?>
						<label><input type="checkbox" name="include_history" value="1" checked> <?php esc_html_e( 'Kèm lịch sử', 'power-schedule-manager' ); ?></label>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Tạo backup cloud', 'power-schedule-manager' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="psm_backup_cloud_restore">
						<input type="hidden" name="provider" value="<?php echo esc_attr( $provider ); ?>">
						<?php wp_nonce_field( 'psm_backup_cloud_restore' ); ?>
						<label class="psm-backup-cloud__locator">
							<span><?php echo esc_html( 'wasabi' === $provider ? __( 'Object key', 'power-schedule-manager' ) : __( 'File ID', 'power-schedule-manager' ) ); ?></span>
							<input type="text" name="remote_locator" value="<?php echo esc_attr( (string) ( $latest[ 'wasabi' === $provider ? 'key' : 'id' ] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Có thể nhập khi phục hồi trên website mới', 'power-schedule-manager' ); ?>">
						</label>
						<label><input type="checkbox" name="confirm_restore" value="1" required <?php disabled( ! $can_restore ); ?>> <?php esc_html_e( 'Xác nhận khôi phục', 'power-schedule-manager' ); ?></label>
						<button type="submit" class="button" <?php disabled( ! $can_restore ); ?>><?php esc_html_e( 'Tải về và khôi phục', 'power-schedule-manager' ); ?></button>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="psm-dashboard-panel psm-backup__policy">
		<div>
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<div>
				<h2><?php esc_html_e( 'Chính sách phục hồi an toàn', 'power-schedule-manager' ); ?></h2>
				<p><?php esc_html_e( 'Bản backup của plugin bổ sung cho backup toàn website, không thay thế bản sao database và thư mục wp-content do Coolify hoặc nhà cung cấp máy chủ quản lý.', 'power-schedule-manager' ); ?></p>
			</div>
		</div>
		<ol>
			<li><?php esc_html_e( 'Luôn giữ ít nhất một bản backup ngoài máy chủ WordPress.', 'power-schedule-manager' ); ?></li>
			<li><?php esc_html_e( 'Kiểm tra phục hồi trên staging trước khi dùng cho production.', 'power-schedule-manager' ); ?></li>
			<li><?php esc_html_e( 'Không sửa nội dung file; checksum sẽ từ chối file bị thay đổi.', 'power-schedule-manager' ); ?></li>
		</ol>
	</section>
</div>
