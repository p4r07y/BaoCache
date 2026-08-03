<?php
defined( 'ABSPATH' ) || exit;

final class BaoCache_Admin {
	private const string PAGE_SLUG = 'baocache';
	private const string FLUSH_ACTION = 'baocache_flush_object_cache';
	private const string EXPORT_ACTION = 'baocache_export_report';
	private const string INSPECT_ACTION = 'baocache_inspect_headers';
	private const string RESTORE_ACTION = 'baocache_restore_revision';
	private const string PREVIEW_ACTION = 'baocache_preview_asset_rule';
	private const string WARM_ACTION = 'baocache_refresh_warmup';
	private const string SAVE_ACTION = 'baocache_save_settings';
	private const string SCAN_ACTION = 'baocache_scan_assets';
	private const string SNAPSHOT_ACTION = 'baocache_take_runtime_snapshot';
	private const string CLOUDFLARE_AUDIT_ACTION = 'baocache_cloudflare_audit';
	private const string CLEAR_FRONTEND_METRICS_ACTION = 'baocache_clear_frontend_metrics';
	private const string DELAY_PREVIEW_ACTION = 'baocache_delay_preview';
	private const string HARDENING_PROBE_ACTION = 'baocache_probe_hardening';
	private const string HARDENING_PROBE_TICK = 'baocache_hardening_probe_tick';
	private const string HARDENING_BASELINE_ACTION = 'baocache_set_hardening_baseline';
	private const string HARDENING_ACK_ACTION = 'baocache_ack_hardening_probe';
	private const string RENDER_BLOCKING_IMPORT_ACTION = 'baocache_import_render_blocking_audit';
	private const string RENDER_BLOCKING_PREVIEW_ACTION = 'baocache_preview_render_blocking';
	private const string RENDER_BLOCKING_CONTEXT_ACTION = 'baocache_render_blocking_context_qa';
	private const string CRITICAL_CSS_STAGE_ACTION = 'baocache_stage_critical_css';
	private const string CRITICAL_CSS_ROLLBACK_ACTION = 'baocache_rollback_critical_css';
	private const string COMPATIBILITY_QA_SAVE_ACTION = 'baocache_save_compatibility_qa';
	private const string COMPATIBILITY_QA_RESET_ACTION = 'baocache_reset_compatibility_qa';
	private const string RULE_GATE_SAVE_ACTION = 'baocache_save_rule_gate';
	private const string GATE_HISTORY_PRUNE_ACTION = 'baocache_prune_gate_history';
	private const string GATE_ACK_ACTION = 'baocache_ack_stale_gate';
	private const string GATE_REVIEW_ACTION = 'baocache_review_gate_evidence';
	private const string GATE_REVIEW_TICK = 'baocache_rule_gate_review_tick';
	private const string ANALYTICS_EVIDENCE_ACTION = 'baocache_analytics_public_evidence';
	private const string CSP_CLEAR_REPORTS_ACTION = 'baocache_csp_clear_reports';
	private const string CSP_REVIEW_EVIDENCE_ACTION = 'baocache_csp_review_evidence';
	private const string CSP_APPLY_RECOMMENDATION_ACTION = 'baocache_csp_apply_recommendation';
	private const string CSP_DISMISS_RECOMMENDATION_ACTION = 'baocache_csp_dismiss_recommendation';
	private const string CSP_ROLLBACK_RECOMMENDATION_ACTION = 'baocache_csp_rollback_recommendation';
	private const string CSP_POST_PROBE_ACTION = 'baocache_csp_post_enforcement_probe';
	private const string CSP_MANUAL_ROLLBACK_ACTION = 'baocache_csp_manual_rollback';
	private const string CSP_PROBE_ACK_ACTION = 'baocache_csp_probe_acknowledge';
	private const string CSP_REMEDIATION_STEP_ACTION = 'baocache_csp_remediation_step';
	private const string CSP_CANARY_TICK = 'baocache_csp_canary_tick';
	private const string PURGE_VERIFY_ACTION = 'baocache_verify_fastcgi_purge';
	private const string PURGE_URL_AJAX_ACTION = 'baocache_purge_fastcgi_url_ajax';
	private const string CRITICAL_IMAGE_SCAN_ACTION = 'baocache_scan_critical_images';
	private const string CRITICAL_IMAGE_APPLY_ACTION = 'baocache_apply_critical_image';
	private const string CRITICAL_IMAGE_ROLLBACK_ACTION = 'baocache_rollback_critical_image';
	private const string RESOURCE_HINT_SCAN_ACTION = 'baocache_scan_resource_hints';
	private const string RESOURCE_HINT_APPLY_ACTION = 'baocache_apply_resource_hints';
	private const string RESOURCE_HINT_ROLLBACK_ACTION = 'baocache_rollback_resource_hints';
	private const string THIRD_PARTY_SCAN_ACTION = 'baocache_scan_third_party';
	private const string THIRD_PARTY_APPLY_ACTION = 'baocache_apply_third_party';
	private const string THIRD_PARTY_ROLLBACK_ACTION = 'baocache_rollback_third_party';
	private const string DATABASE_CHECK_ACTION = 'baocache_database_check';
	private const string DATABASE_REPAIR_ACTION = 'baocache_database_repair';
	private const string DATABASE_CLEAN_ACTION = 'baocache_database_clean_runtime';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_' . self::FLUSH_ACTION, array( $this, 'flush_object_cache' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'export_report' ) );
		add_action( 'wp_ajax_' . self::INSPECT_ACTION, array( $this, 'inspect_headers' ) );
		add_action( 'admin_post_' . self::RESTORE_ACTION, array( $this, 'restore_revision' ) );
		add_action( 'wp_ajax_' . self::PURGE_URL_AJAX_ACTION, array( $this, 'purge_fastcgi_url_ajax' ) );
		add_action( 'admin_post_' . self::DELAY_PREVIEW_ACTION, array( $this, 'delay_preview' ) );
		add_action( 'admin_post_' . self::WARM_ACTION, array( $this, 'refresh_warmup' ) );
		add_action( 'wp_ajax_' . self::SAVE_ACTION, array( $this, 'save_settings_ajax' ) );
		add_action( 'wp_ajax_' . self::WARM_ACTION, array( $this, 'refresh_warmup_ajax' ) );
		add_action( 'wp_ajax_' . self::SCAN_ACTION, array( $this, 'scan_assets_ajax' ) );
		add_action( 'wp_ajax_' . self::SNAPSHOT_ACTION, array( $this, 'take_runtime_snapshot_ajax' ) );
		add_action( 'wp_ajax_' . self::CLOUDFLARE_AUDIT_ACTION, array( $this, 'cloudflare_audit_ajax' ) );
		add_action( 'wp_ajax_' . self::CLEAR_FRONTEND_METRICS_ACTION, array( $this, 'clear_frontend_metrics_ajax' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_ACTION, array( $this, 'preview_asset_rule' ) );
		add_action( 'wp_ajax_' . self::HARDENING_PROBE_ACTION, array( $this, 'probe_hardening_ajax' ) );
		add_action( 'wp_ajax_' . self::HARDENING_BASELINE_ACTION, array( $this, 'set_hardening_baseline_ajax' ) );
		add_action( 'wp_ajax_' . self::HARDENING_ACK_ACTION, array( $this, 'acknowledge_hardening_probe_ajax' ) );
		add_action( 'wp_ajax_' . self::RENDER_BLOCKING_IMPORT_ACTION, array( $this, 'import_render_blocking_ajax' ) );
		add_action( 'wp_ajax_' . self::RENDER_BLOCKING_PREVIEW_ACTION, array( $this, 'preview_render_blocking_ajax' ) );
		add_action( 'wp_ajax_' . self::RENDER_BLOCKING_CONTEXT_ACTION, array( $this, 'render_blocking_context_qa_ajax' ) );
		add_action( 'wp_ajax_' . self::CRITICAL_CSS_STAGE_ACTION, array( $this, 'stage_critical_css_ajax' ) );
		add_action( 'wp_ajax_' . self::CRITICAL_CSS_ROLLBACK_ACTION, array( $this, 'rollback_critical_css_ajax' ) );
		add_action( 'wp_ajax_' . self::COMPATIBILITY_QA_SAVE_ACTION, array( $this, 'save_compatibility_qa_ajax' ) );
		add_action( 'wp_ajax_' . self::COMPATIBILITY_QA_RESET_ACTION, array( $this, 'reset_compatibility_qa_ajax' ) );
		add_action( 'wp_ajax_' . self::RULE_GATE_SAVE_ACTION, array( $this, 'save_rule_gate_ajax' ) );
		add_action( 'wp_ajax_' . self::GATE_HISTORY_PRUNE_ACTION, array( $this, 'prune_gate_history_ajax' ) );
		add_action( 'wp_ajax_' . self::GATE_ACK_ACTION, array( $this, 'acknowledge_stale_gate_ajax' ) );
		add_action( 'wp_ajax_' . self::GATE_REVIEW_ACTION, array( $this, 'review_gate_evidence_ajax' ) );
		add_action( 'wp_ajax_' . self::ANALYTICS_EVIDENCE_ACTION, array( $this, 'analytics_public_evidence_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_CLEAR_REPORTS_ACTION, array( $this, 'clear_csp_reports_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_REVIEW_EVIDENCE_ACTION, array( $this, 'review_csp_evidence_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_APPLY_RECOMMENDATION_ACTION, array( $this, 'apply_csp_recommendation_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_DISMISS_RECOMMENDATION_ACTION, array( $this, 'dismiss_csp_recommendation_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_ROLLBACK_RECOMMENDATION_ACTION, array( $this, 'rollback_csp_recommendation_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_POST_PROBE_ACTION, array( $this, 'csp_post_enforcement_probe_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_MANUAL_ROLLBACK_ACTION, array( $this, 'csp_manual_rollback_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_PROBE_ACK_ACTION, array( $this, 'csp_probe_acknowledge_ajax' ) );
		add_action( 'wp_ajax_' . self::CSP_REMEDIATION_STEP_ACTION, array( $this, 'csp_remediation_step_ajax' ) );
		add_action( self::CSP_CANARY_TICK, array( $this, 'run_csp_canary' ) );
		add_action( 'wp_ajax_' . self::PURGE_VERIFY_ACTION, array( $this, 'verify_fastcgi_purge_ajax' ) );
		add_action( 'wp_ajax_' . self::CRITICAL_IMAGE_SCAN_ACTION, array( $this, 'scan_critical_images_ajax' ) );
		add_action( 'wp_ajax_' . self::CRITICAL_IMAGE_APPLY_ACTION, array( $this, 'apply_critical_image_ajax' ) );
		add_action( 'wp_ajax_' . self::CRITICAL_IMAGE_ROLLBACK_ACTION, array( $this, 'rollback_critical_image_ajax' ) );
		add_action( 'wp_ajax_' . self::RESOURCE_HINT_SCAN_ACTION, array( $this, 'scan_resource_hints_ajax' ) );
		add_action( 'wp_ajax_' . self::RESOURCE_HINT_APPLY_ACTION, array( $this, 'apply_resource_hints_ajax' ) );
		add_action( 'wp_ajax_' . self::RESOURCE_HINT_ROLLBACK_ACTION, array( $this, 'rollback_resource_hints_ajax' ) );
		add_action( 'wp_ajax_' . self::THIRD_PARTY_SCAN_ACTION, array( $this, 'scan_third_party_ajax' ) );
		add_action( 'wp_ajax_' . self::THIRD_PARTY_APPLY_ACTION, array( $this, 'apply_third_party_ajax' ) );
		add_action( 'wp_ajax_' . self::THIRD_PARTY_ROLLBACK_ACTION, array( $this, 'rollback_third_party_ajax' ) );
		add_action( 'wp_ajax_' . self::DATABASE_CHECK_ACTION, array( $this, 'database_check_ajax' ) );
		add_action( 'wp_ajax_' . self::DATABASE_REPAIR_ACTION, array( $this, 'database_repair_ajax' ) );
		add_action( 'wp_ajax_' . self::DATABASE_CLEAN_ACTION, array( $this, 'database_clean_runtime_ajax' ) );
		add_action( self::HARDENING_PROBE_TICK, array( $this, 'run_scheduled_probe' ) );
		add_action( self::GATE_REVIEW_TICK, array( $this, 'run_gate_evidence_review' ) );
		add_action( 'init', array( $this, 'ensure_probe_schedule' ), 25 );
		add_action( 'init', array( $this, 'ensure_gate_review_schedule' ), 26 );
		add_action( 'init', array( $this, 'ensure_csp_canary_schedule' ), 28 );
		add_filter( 'cron_schedules', array( $this, 'probe_schedules' ) );
		add_filter( 'site_status_tests', array( $this, 'site_health_tests' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'BaoCache', 'baocache' ),
			__( 'BaoCache', 'baocache' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-performance',
			58
		);
	}

	public function settings(): void {
		register_setting( 'baocache_settings', BAOCACHE_OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( mixed $input ): array {
		$previous = BaoCache_Settings::get();
		$settings = BaoCache_Settings::sanitize( $input );
		$validation = $this->csp_enforce_transition( $previous, $settings, $input );
		if ( '' !== $validation['message'] ) {
			add_settings_error( BAOCACHE_OPTION, 'baocache_csp_enforce', $validation['message'], 'error' );
			return $previous;
		}
		if ( is_array( $previous ) ) {
			$history = get_option( 'baocache_settings_history', array() );
			$history = is_array( $history ) ? $history : array();
			array_unshift( $history, array( 'saved_at' => time(), 'settings' => $previous ) );
			update_option( 'baocache_settings_history', array_slice( $history, 0, 5 ), false );
		}
		if ( ! empty( $validation['requested'] ) ) {
			BaoCache_CSP::record_enforce_acknowledgement( $settings );
			BaoCache_Activity::log( 'csp_enforce_acknowledged', 'warning', __( 'Operator đã xác nhận checklist trước khi bật CSP Enforce.', 'baocache' ), array( 'mode' => 'enforce', 'fingerprint' => substr( (string) BaoCache_CSP::policy_snapshot( $settings )['fingerprint'], 0, 12 ) ) );
		}
		BaoCache_Uninstall_Manager::save_policy( $settings );
		return $settings;
	}

	/** @return array{requested: bool, message: string} */
	private function csp_enforce_transition( array $previous, array $settings, mixed $input ): array {
		$requested = ! empty( $settings['csp_enabled'] )
			&& 'enforce' === (string) $settings['csp_mode']
			&& ( empty( $previous['csp_enabled'] ) || 'enforce' !== (string) ( $previous['csp_mode'] ?? 'report' ) );
		if ( ! $requested ) {
			return array( 'requested' => false, 'message' => '' );
		}
		$input = is_array( $input ) ? $input : array();
		if ( empty( $input['csp_enforce_ack'] ) ) {
			return array( 'requested' => true, 'message' => __( 'Trước khi bật CSP Enforce, hãy xác nhận checklist triển khai cho policy hiện tại.', 'baocache' ) );
		}
		$staged_settings = $settings;
		$staged_settings['csp_mode'] = 'report';
		$staged_settings['csp_collect_reports'] = true;
		$readiness = BaoCache_CSP::enforce_readiness( $staged_settings );
		if ( 'good' !== (string) $readiness['state'] ) {
			return array( 'requested' => true, 'message' => sprintf( __( 'Chưa thể bật CSP Enforce: %s', 'baocache' ), (string) $readiness['detail'] ) );
		}
		return array( 'requested' => true, 'message' => '' );
	}

	public function assets( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'baocache-admin', BAOCACHE_URL . 'admin/assets/baocache.css', array(), BAOCACHE_VERSION );
		wp_enqueue_script( 'baocache-admin', BAOCACHE_URL . 'admin/assets/baocache.js', array(), BAOCACHE_VERSION, true );
		wp_localize_script( 'baocache-admin', 'BaoCacheAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::INSPECT_ACTION ),
			'previewNonce' => wp_create_nonce( self::PREVIEW_ACTION ),
			'saveNonce' => wp_create_nonce( self::SAVE_ACTION ),
			'warmupNonce' => wp_create_nonce( self::WARM_ACTION ),
			'scanNonce' => wp_create_nonce( self::SCAN_ACTION ),
			'snapshotNonce' => wp_create_nonce( self::SNAPSHOT_ACTION ),
			'cloudflareAuditNonce' => wp_create_nonce( self::CLOUDFLARE_AUDIT_ACTION ),
			'clearFrontendMetricsNonce' => wp_create_nonce( self::CLEAR_FRONTEND_METRICS_ACTION ),
			'hardeningProbeNonce' => wp_create_nonce( self::HARDENING_PROBE_ACTION ),
			'hardeningBaselineNonce' => wp_create_nonce( self::HARDENING_BASELINE_ACTION ),
			'hardeningAckNonce' => wp_create_nonce( self::HARDENING_ACK_ACTION ),
			'renderBlockingImportNonce' => wp_create_nonce( self::RENDER_BLOCKING_IMPORT_ACTION ),
			'renderBlockingPreviewNonce' => wp_create_nonce( self::RENDER_BLOCKING_PREVIEW_ACTION ),
			'renderBlockingContextNonce' => wp_create_nonce( self::RENDER_BLOCKING_CONTEXT_ACTION ),
			'criticalCssStageNonce' => wp_create_nonce( self::CRITICAL_CSS_STAGE_ACTION ),
			'criticalCssRollbackNonce' => wp_create_nonce( self::CRITICAL_CSS_ROLLBACK_ACTION ),
			'compatibilityQaSaveNonce' => wp_create_nonce( self::COMPATIBILITY_QA_SAVE_ACTION ),
			'compatibilityQaResetNonce' => wp_create_nonce( self::COMPATIBILITY_QA_RESET_ACTION ),
			'ruleGateSaveNonce' => wp_create_nonce( self::RULE_GATE_SAVE_ACTION ),
			'gateHistoryPruneNonce' => wp_create_nonce( self::GATE_HISTORY_PRUNE_ACTION ),
			'gateAckNonce' => wp_create_nonce( self::GATE_ACK_ACTION ),
			'gateReviewNonce' => wp_create_nonce( self::GATE_REVIEW_ACTION ),
			'analyticsEvidenceNonce' => wp_create_nonce( self::ANALYTICS_EVIDENCE_ACTION ),
			'cspClearReportsNonce' => wp_create_nonce( self::CSP_CLEAR_REPORTS_ACTION ),
			'cspReviewEvidenceNonce' => wp_create_nonce( self::CSP_REVIEW_EVIDENCE_ACTION ),
			'cspApplyRecommendationNonce' => wp_create_nonce( self::CSP_APPLY_RECOMMENDATION_ACTION ),
			'cspDismissRecommendationNonce' => wp_create_nonce( self::CSP_DISMISS_RECOMMENDATION_ACTION ),
			'cspRollbackRecommendationNonce' => wp_create_nonce( self::CSP_ROLLBACK_RECOMMENDATION_ACTION ),
			'cspPostProbeNonce' => wp_create_nonce( self::CSP_POST_PROBE_ACTION ),
			'cspManualRollbackNonce' => wp_create_nonce( self::CSP_MANUAL_ROLLBACK_ACTION ),
			'cspProbeAckNonce' => wp_create_nonce( self::CSP_PROBE_ACK_ACTION ),
			'cspRemediationStepNonce' => wp_create_nonce( self::CSP_REMEDIATION_STEP_ACTION ),
			'purgeVerifyNonce' => wp_create_nonce( self::PURGE_VERIFY_ACTION ),
			'purgeUrlNonce' => wp_create_nonce( self::PURGE_URL_AJAX_ACTION ),
			'criticalImageScanNonce' => wp_create_nonce( self::CRITICAL_IMAGE_SCAN_ACTION ),
			'criticalImageApplyNonce' => wp_create_nonce( self::CRITICAL_IMAGE_APPLY_ACTION ),
			'criticalImageRollbackNonce' => wp_create_nonce( self::CRITICAL_IMAGE_ROLLBACK_ACTION ),
			'resourceHintScanNonce' => wp_create_nonce( self::RESOURCE_HINT_SCAN_ACTION ),
			'resourceHintApplyNonce' => wp_create_nonce( self::RESOURCE_HINT_APPLY_ACTION ),
			'resourceHintRollbackNonce' => wp_create_nonce( self::RESOURCE_HINT_ROLLBACK_ACTION ),
			'thirdPartyScanNonce' => wp_create_nonce( self::THIRD_PARTY_SCAN_ACTION ),
			'thirdPartyApplyNonce' => wp_create_nonce( self::THIRD_PARTY_APPLY_ACTION ),
			'thirdPartyRollbackNonce' => wp_create_nonce( self::THIRD_PARTY_ROLLBACK_ACTION ),
			'databaseCheckNonce' => wp_create_nonce( self::DATABASE_CHECK_ACTION ),
			'databaseRepairNonce' => wp_create_nonce( self::DATABASE_REPAIR_ACTION ),
			'databaseCleanNonce' => wp_create_nonce( self::DATABASE_CLEAN_ACTION ),
			'inspectError' => __( 'Không thể kiểm tra response header.', 'baocache' ),
			'saveError' => __( 'Không thể lưu cấu hình. Hãy thử lại.', 'baocache' ),
			'warmupError' => __( 'Không thể đọc sitemap. Hãy kiểm tra lại cấu hình.', 'baocache' ),
			'scanError' => __( 'Không thể quét Asset Inventory. Hãy thử lại.', 'baocache' ),
			'snapshotError' => __( 'Không thể lấy runtime snapshot. Hãy thử lại.', 'baocache' ),
			'cloudflareAuditError' => __( 'Không thể kiểm tra Cloudflare. Hãy thử lại.', 'baocache' ),
			'clearFrontendMetricsError' => __( 'Không thể xóa Browser Resource Timing. Hãy thử lại.', 'baocache' ),
			'hardeningProbeError' => __( 'Không thể kiểm tra public response. Hãy thử lại.', 'baocache' ),
			'hardeningBaselineError' => __( 'Không thể đặt baseline. Cần một probe PASS trước.', 'baocache' ),
			'hardeningAckError' => __( 'Không thể xác nhận cảnh báo probe. Hãy thử lại.', 'baocache' ),
			'renderBlockingImportError' => __( 'Không thể đọc báo cáo Lighthouse. Hãy kiểm tra JSON.', 'baocache' ),
			'renderBlockingPreviewError' => __( 'Không thể tạo preview strategy cho asset này.', 'baocache' ),
			'renderBlockingContextError' => __( 'Không thể kiểm tra context strategy.', 'baocache' ),
			'criticalCssStageError' => __( 'Không thể validate Critical CSS.', 'baocache' ),
			'criticalCssRollbackError' => __( 'Không thể rollback Critical CSS.', 'baocache' ),
			'compatibilityQaError' => __( 'Không thể lưu Compatibility QA.', 'baocache' ),
			'ruleGateError' => __( 'Không thể lưu compatibility gate cho rule.', 'baocache' ),
			'gateHistoryError' => __( 'Không thể dọn lịch sử evidence.', 'baocache' ),
			'gateAckError' => __( 'Không thể xác nhận stale gate.', 'baocache' ),
			'gateReviewError' => __( 'Không thể rà soát evidence gate.', 'baocache' ),
			'analyticsEvidenceError' => __( 'Không thể kiểm tra Analytics trên frontend công khai.', 'baocache' ),
			'cspClearReportsError' => __( 'Không thể xóa CSP evidence.', 'baocache' ),
			'cspReviewEvidenceError' => __( 'Không thể rà soát CSP evidence.', 'baocache' ),
			'cspRecommendationError' => __( 'Không thể cập nhật CSP recommendation.', 'baocache' ),
			'cspRollbackRecommendationError' => __( 'Không thể rollback CSP recommendation.', 'baocache' ),
			'cspPostProbeError' => __( 'Không thể kiểm tra CSP public sau Enforce.', 'baocache' ),
			'cspManualRollbackError' => __( 'Không thể chuyển CSP về Report-Only.', 'baocache' ),
			'cspProbeAckError' => __( 'Không thể xác nhận CSP canary failure.', 'baocache' ),
			'cspRemediationStepError' => __( 'Không thể lưu remediation step.', 'baocache' ),
			'purgeVerifyError' => __( 'Không thể xác minh Nginx purge endpoint.', 'baocache' ),
			'purgeUrlError' => __( 'Không thể purge URL FastCGI.', 'baocache' ),
			'criticalImageScanError' => __( 'Không thể phân tích ảnh quan trọng trên frontend.', 'baocache' ),
			'criticalImageApplyError' => __( 'Không thể áp dụng critical image candidate.', 'baocache' ),
			'criticalImageRollbackError' => __( 'Không thể rollback Critical Image.', 'baocache' ),
			'databaseCheckError' => __( 'Không thể kiểm tra dữ liệu BaoCache.', 'baocache' ),
			'databaseRepairError' => __( 'Không thể sửa cấu trúc dữ liệu BaoCache.', 'baocache' ),
			'databaseCleanError' => __( 'Không thể dọn dữ liệu runtime BaoCache.', 'baocache' ),
		) );
	}

	public function probe_schedules( array $schedules ): array {
		$schedules['baocache_daily'] = array( 'interval' => DAY_IN_SECONDS, 'display' => __( 'BaoCache mỗi ngày', 'baocache' ) );
		return $schedules;
	}

	public function ensure_probe_schedule(): void {
		$settings = BaoCache_Settings::get();
		$wanted = (string) ( $settings['probe_schedule'] ?? 'manual' );
		if ( empty( $settings['probe_enabled'] ) || 'manual' === $wanted ) {
			wp_clear_scheduled_hook( self::HARDENING_PROBE_TICK );
			return;
		}
		$schedules = wp_get_schedules();
		if ( ! isset( $schedules[ $wanted ] ) ) {
			return;
		}
		if ( wp_get_schedule( self::HARDENING_PROBE_TICK ) !== $wanted ) {
			wp_clear_scheduled_hook( self::HARDENING_PROBE_TICK );
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, $wanted, self::HARDENING_PROBE_TICK, array(), true );
		}
	}

	public function run_scheduled_probe(): void {
		$settings = BaoCache_Settings::get();
		if ( empty( $settings['probe_enabled'] ) || 'manual' === (string) $settings['probe_schedule'] || get_transient( 'baocache_hardening_probe_lock' ) ) {
			return;
		}
		set_transient( 'baocache_hardening_probe_lock', '1', 90 );
		$home = $this->probe_public_url( home_url( '/' ) );
		$feed = $this->probe_public_url( function_exists( 'get_feed_link' ) ? get_feed_link() : home_url( '/feed/' ) );
		$users = $this->probe_public_url( rest_url( 'wp/v2/users' ) );
		if ( is_wp_error( $home ) || is_wp_error( $feed ) || is_wp_error( $users ) ) {
			BaoCache_Activity::log( 'hardening_probe', 'failed', __( 'Scheduled Public Response Probe không hoàn tất.', 'baocache' ) );
			delete_transient( 'baocache_hardening_probe_lock' );
			return;
		}
		$body = strtolower( (string) $home['body'] );
		$rss_mode = (string) $settings['rss_mode'];
		$generator_found = false !== strpos( $body, 'name="generator"' ) || false !== strpos( $body, "name='generator'" );
		$feed_link_found = false !== strpos( $body, 'application/rss+xml' ) || false !== strpos( $body, 'application/atom+xml' );
		$rest_link_found = false !== strpos( $body, 'api.w.org' );
		$checks = array(
			array( 'label' => __( 'RSS response', 'baocache' ), 'state' => ( ( 'keep' === $rss_mode && 200 === (int) $feed['status'] ) || ( 'redirect' === $rss_mode && in_array( (int) $feed['status'], array( 301, 302, 307, 308 ), true ) ) || ( 'gone' === $rss_mode && 410 === (int) $feed['status'] ) ) ? 'good' : 'warn', 'value' => sprintf( __( 'Policy %1$s · HTTP %2$d', 'baocache' ), strtoupper( $rss_mode ), (int) $feed['status'] ) ),
			array( 'label' => __( 'REST users endpoint', 'baocache' ), 'state' => ! empty( $settings['disable_rest_user_enumeration'] ) ? ( 404 === (int) $users['status'] ? 'good' : 'warn' ) : 'neutral', 'value' => sprintf( __( 'HTTP %d', 'baocache' ), (int) $users['status'] ) ),
			array( 'label' => __( 'Generator metadata', 'baocache' ), 'state' => ( ! empty( $settings['remove_generator'] ) && ! $generator_found ) || ( empty( $settings['remove_generator'] ) && $generator_found ) ? 'good' : 'warn', 'value' => $generator_found ? __( 'Found in HTML', 'baocache' ) : __( 'Not found in HTML', 'baocache' ) ),
			array( 'label' => __( 'Feed discovery links', 'baocache' ), 'state' => $feed_link_found === $this->expected_public_link( $settings, 'feed' ) ? 'good' : 'warn', 'value' => $feed_link_found ? __( 'Found in HTML', 'baocache' ) : __( 'Not found in HTML', 'baocache' ) ),
			array( 'label' => __( 'REST discovery link', 'baocache' ), 'state' => $rest_link_found === empty( $settings['remove_rest_api_link'] ) ? 'good' : 'warn', 'value' => $rest_link_found ? __( 'Found in HTML', 'baocache' ) : __( 'Not found in HTML', 'baocache' ) ),
			array( 'label' => __( 'X-Pingback header', 'baocache' ), 'state' => ( ! empty( $settings['remove_x_pingback'] ) && '' === (string) wp_remote_retrieve_header( $home['headers'], 'x-pingback' ) ) ? 'good' : ( empty( $settings['remove_x_pingback'] ) ? 'neutral' : 'warn' ), 'value' => '' === (string) wp_remote_retrieve_header( $home['headers'], 'x-pingback' ) ? __( 'Not present', 'baocache' ) : __( 'Present', 'baocache' ) ),
		);
		$passed = count( array_filter( $checks, static fn( array $check ): bool => 'good' === ( $check['state'] ?? '' ) ) );
		$history = get_option( 'baocache_hardening_probe_history', array() );
		$history = is_array( $history ) ? $history : array();
		$baseline = get_option( 'baocache_hardening_probe_baseline', array() );
		$baseline_environment = (string) ( $baseline['environment'] ?? '' );
		$baseline_matches = ! empty( $baseline['checks'] ) && ( '' === $baseline_environment || $baseline_environment === wp_get_environment_type() );
		$comparison_checks = $baseline_matches ? $baseline['checks'] : ( $history[0]['checks'] ?? array() );
		$previous = array();
		foreach ( (array) $comparison_checks as $item ) {
			if ( is_array( $item ) && isset( $item['label'] ) ) $previous[ (string) $item['label'] ] = $item;
		}
		$regressions = array();
		$improvements = array();
		foreach ( $checks as $check ) {
			$label = (string) $check['label'];
			$previous_state = (string) ( $previous[ $label ]['state'] ?? '' );
			$current_state = (string) $check['state'];
			if ( 'good' === $previous_state && 'good' !== $current_state ) {
				$regressions[] = array( 'label' => $label, 'from' => $previous_state, 'to' => $current_state, 'value' => (string) $check['value'] );
			} elseif ( 'good' !== $previous_state && 'good' === $current_state && '' !== $previous_state ) {
				$improvements[] = array( 'label' => $label, 'from' => $previous_state, 'to' => $current_state, 'value' => (string) $check['value'] );
			}
		}
		$record = array( 'checked_at' => time(), 'source' => 'scheduled', 'passed' => $passed, 'total' => count( $checks ), 'response_ms' => max( (int) $home['response_ms'], (int) $feed['response_ms'], (int) $users['response_ms'] ), 'regressions' => $regressions, 'improvements' => $improvements, 'checks' => array_map( static fn( array $check ): array => array( 'label' => (string) $check['label'], 'state' => (string) $check['state'], 'value' => (string) $check['value'] ), $checks ) );
		array_unshift( $history, $record );
		update_option( 'baocache_hardening_probe_history', array_slice( $history, 0, 10 ), false );
		BaoCache_Activity::log( 'hardening_probe', empty( $regressions ) ? 'success' : 'warning', sprintf( __( 'Scheduled Public Response Probe: %1$d/%2$d PASS.', 'baocache' ), $passed, count( $checks ) ), array( 'passed' => (string) $passed, 'regressions' => (string) count( $regressions ) ) );
		delete_transient( 'baocache_hardening_probe_lock' );
	}

	public function save_settings_ajax(): void {
		check_ajax_referer( self::SAVE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền lưu cấu hình.', 'baocache' ) ), 403 );
		}
		$input = isset( $_POST[ BAOCACHE_OPTION ] ) ? wp_unslash( $_POST[ BAOCACHE_OPTION ] ) : array();
		$analytics_id = is_array( $input ) ? (string) ( $input['analytics_id'] ?? '' ) : '';
		$analytics_enabled = is_array( $input ) && ! empty( $input['analytics_enabled'] );
		if ( $analytics_enabled && ! BaoCache_Settings::valid_tracking_id( $analytics_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Measurement ID phải có dạng G-XXXX hoặc GTM-XXXX.', 'baocache' ) ), 422 );
		}
		$clarity_enabled = is_array( $input ) && ! empty( $input['clarity_enabled'] );
		$clarity_id = is_array( $input ) ? (string) ( $input['clarity_project_id'] ?? '' ) : '';
		if ( $clarity_enabled && ! BaoCache_Settings::valid_clarity_project_id( $clarity_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Microsoft Clarity Project ID không hợp lệ.', 'baocache' ) ), 422 );
		}
		$previous_settings = BaoCache_Settings::get();
		$settings = BaoCache_Settings::sanitize( $input );
		$csp_enforce_transition = $this->csp_enforce_transition( $previous_settings, $settings, $input );
		if ( '' !== $csp_enforce_transition['message'] ) {
			wp_send_json_error( array( 'message' => $csp_enforce_transition['message'] ), 422 );
		}
		update_option( BAOCACHE_OPTION, $settings, false );
		BaoCache_Uninstall_Manager::save_policy( $settings );
		$this->ensure_csp_canary_schedule();
		if ( ! empty( $csp_enforce_transition['requested'] ) ) {
			BaoCache_CSP::record_enforce_acknowledgement( $settings );
			BaoCache_Activity::log( 'csp_enforce_acknowledged', 'warning', __( 'Operator đã xác nhận checklist trước khi bật CSP Enforce.', 'baocache' ), array( 'mode' => 'enforce', 'fingerprint' => substr( (string) BaoCache_CSP::policy_snapshot( $settings )['fingerprint'], 0, 12 ) ) );
		}
		if ( ! empty( $settings['csp_enabled'] ) ) {
			BaoCache_CSP::record_policy_snapshot( $settings );
		}
		foreach ( array_diff( BaoCache_Settings::lines( (string) $settings['defer_handles'] ), BaoCache_Settings::lines( (string) $previous_settings['defer_handles'] ) ) as $handle ) {
			BaoCache_Render_Blocking::record_strategy( $handle, 'defer', 'Added from WordPress strategy settings', 'frontend', false );
		}
		foreach ( array_diff( BaoCache_Settings::lines( (string) $previous_settings['defer_handles'] ), BaoCache_Settings::lines( (string) $settings['defer_handles'] ) ) as $handle ) {
			BaoCache_Render_Blocking::record_strategy( $handle, 'defer', 'Removed from WordPress strategy settings', 'frontend', true );
		}
		foreach ( array_diff( BaoCache_Settings::lines( (string) $settings['async_style_handles'] ), BaoCache_Settings::lines( (string) $previous_settings['async_style_handles'] ) ) as $handle ) {
			BaoCache_Render_Blocking::record_strategy( $handle, 'async-css', 'Added from Render Blocking Strategy settings', 'frontend', false );
		}
		foreach ( array_diff( BaoCache_Settings::lines( (string) $previous_settings['async_style_handles'] ), BaoCache_Settings::lines( (string) $settings['async_style_handles'] ) ) as $handle ) {
			BaoCache_Render_Blocking::record_strategy( $handle, 'async-css', 'Removed from Render Blocking Strategy settings', 'frontend', true );
		}
		( new BaoCache_Warmup() )->ensure_scheduled();
		$this->ensure_probe_schedule();
		$purge = $this->queue_frontend_cache_invalidation();
		if ( (string) $settings['analytics_id'] !== (string) $previous_settings['analytics_id'] || (bool) $settings['analytics_enabled'] !== (bool) $previous_settings['analytics_enabled'] || (bool) $settings['clarity_enabled'] !== (bool) $previous_settings['clarity_enabled'] || (bool) $settings['analytics_auto_events'] !== (bool) $previous_settings['analytics_auto_events'] || (array) $settings['analytics_adapters'] !== (array) $previous_settings['analytics_adapters'] || (bool) $settings['analytics_duplicate_ack'] !== (bool) $previous_settings['analytics_duplicate_ack'] ) {
			BaoCache_Activity::log( 'analytics_config', 'success', __( 'Đã cập nhật Analytics & Tracking.', 'baocache' ), array( 'provider' => BaoCache_Settings::tracking_id_type( (string) $settings['analytics_id'] ), 'consent' => (string) $settings['analytics_consent_mode'], 'events' => ! empty( $settings['analytics_auto_events'] ) ? 'enabled' : 'disabled', 'clarity' => ! empty( $settings['clarity_enabled'] ) ? 'enabled' : 'disabled', 'adapters' => (string) count( (array) $settings['analytics_adapters'] ), 'duplicate_ack' => ! empty( $settings['analytics_duplicate_ack'] ) ? 'acknowledged' : 'pending' ) );
		}
		BaoCache_Activity::log( 'settings_saved', 'success', __( 'Đã lưu cấu hình BaoCache.', 'baocache' ) );
		wp_send_json_success( array( 'warm_enabled' => (bool) $settings['warm_enabled'], 'warm_sitemap' => (string) $settings['warm_sitemap'], 'frontend_cache_invalidation' => $purge ) );
	}

	/**
	 * Queue exact-key invalidation for the public routes whose HTML is always
	 * affected by frontend settings. Nginx deliberately has no wildcard purge;
	 * sitemap/content changes continue to use the existing URL queue.
	 *
	 * @return array{queued: int, available: bool}
	 */
	private function queue_frontend_cache_invalidation(): array {
		$urls = array( home_url( '/' ) );
		if ( function_exists( 'get_feed_link' ) ) {
			$urls[] = get_feed_link();
		}
		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
		$purge = new BaoCache_Purge();
		foreach ( $urls as $url ) {
			$purge->queue_url( $url );
		}
		return array( 'queued' => count( $urls ), 'available' => BaoCache_Purge::available() );
	}

	public function database_check_ajax(): void {
		check_ajax_referer( self::DATABASE_CHECK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền kiểm tra database.', 'baocache' ) ), 403 );
		wp_send_json_success( BaoCache_Database_Health::inspect() );
	}

	public function database_repair_ajax(): void {
		check_ajax_referer( self::DATABASE_REPAIR_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền sửa dữ liệu BaoCache.', 'baocache' ) ), 403 );
		$result = BaoCache_Database_Health::repair();
		BaoCache_Activity::log( 'database_repair', 'success', __( 'Đã xác minh schema marker, cấu hình và cron BaoCache.', 'baocache' ), array( 'actions' => (string) count( $result['actions'] ) ) );
		wp_send_json_success( $result );
	}

	public function database_clean_runtime_ajax(): void {
		check_ajax_referer( self::DATABASE_CLEAN_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền dọn runtime.', 'baocache' ) ), 403 );
		$result = BaoCache_Uninstall_Manager::clean_runtime();
		BaoCache_Warmup::activate();
		BaoCache_Metrics::activate();
		$this->ensure_probe_schedule();
		$this->ensure_gate_review_schedule();
		$this->ensure_csp_canary_schedule();
		( new BaoCache_CSP() )->ensure_review_schedule();
		BaoCache_Activity::log( 'database_runtime_cleanup', 'success', __( 'Đã dọn queue, lock, transient và runtime snapshot tạm của BaoCache.', 'baocache' ), $result );
		wp_send_json_success( $result );
	}

	public function clear_csp_reports_ajax(): void {
		check_ajax_referer( self::CSP_CLEAR_REPORTS_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền xóa CSP evidence.', 'baocache' ) ), 403 );
		}
		BaoCache_CSP::clear_reports();
		BaoCache_Activity::log( 'csp_reports_cleared', 'success', __( 'Đã xóa CSP violation evidence tổng hợp.', 'baocache' ) );
		wp_send_json_success( array( 'cleared' => true ) );
	}

	public function review_csp_evidence_ajax(): void {
		check_ajax_referer( self::CSP_REVIEW_EVIDENCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rà soát CSP evidence.', 'baocache' ) ), 403 );
		}
		$review = ( new BaoCache_CSP() )->review_evidence();
		$removed = (int) $review['reports_removed'] + (int) $review['dismissals_removed'] + (int) $review['ledger_removed'];
		BaoCache_Activity::log( 'csp_evidence_review', 'success', $removed > 0 ? sprintf( __( 'Đã dọn %d CSP evidence record quá hạn.', 'baocache' ), $removed ) : __( 'Đã rà soát CSP evidence; không có record quá hạn.', 'baocache' ), array( 'removed' => (string) $removed, 'active_reports' => (string) $review['active_reports'] ) );
		wp_send_json_success( $review );
	}

	public function flush_object_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'baocache' ) );
		}
		check_admin_referer( self::FLUSH_ACTION );
		$result = wp_cache_flush();
		BaoCache_Activity::log( 'redis_flush', $result ? 'success' : 'failed', $result ? __( 'Đã flush Redis object cache.', 'baocache' ) : __( 'Không thể flush Redis object cache.', 'baocache' ) );
		wp_safe_redirect(
			add_query_arg(
				'baocache_flush',
				$result ? 'success' : 'failed',
				admin_url( 'admin.php?page=' . self::PAGE_SLUG )
			)
		);
		exit;
	}

	public function export_report(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền xuất báo cáo này.', 'baocache' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );
		$payload = array(
			'schema' => 'baocache-report/v1',
			'generated_at' => current_time( 'c' ),
			'baocache_version' => BAOCACHE_VERSION,
			'wordpress' => $this->technical_report(),
			'redis' => BaoCache_Diagnostics::redis_metrics(),
			'asset_inventory' => get_transient( 'baocache_asset_inventory' ),
			'runtime_snapshots' => BaoCache_Metrics::history(),
			'render_blocking_audit' => BaoCache_Render_Blocking::audit(),
			'critical_css' => array_diff_key( BaoCache_Render_Blocking::critical_css(), array( 'css' => true ) ),
			'render_blocking_strategy_log' => get_option( 'baocache_render_blocking_log', array() ),
			'rule_gate_history' => BaoCache_Render_Blocking::export_gate_history( 200 ),
			'rule_gate_history_policy' => BaoCache_Render_Blocking::gate_history_policy(),
			'csp_evidence_summary' => BaoCache_CSP::export_evidence_summary(),
			'csp_enforce_readiness' => array_intersect_key( BaoCache_CSP::enforce_readiness(), array( 'state' => true, 'title' => true, 'checks' => true ) ),
			'csp_enforce_acknowledgement' => array_intersect_key( BaoCache_CSP::enforce_acknowledgement(), array( 'matched' => true, 'acknowledged_at' => true, 'fingerprint' => true ) ),
			'csp_post_enforcement_probe' => BaoCache_CSP::post_enforcement_probe(),
			'csp_post_enforcement_probe_history' => BaoCache_CSP::post_enforcement_probe_history(),
			'csp_post_probe_regression' => array_intersect_key( BaoCache_CSP::post_enforcement_probe_regression(), array( 'available' => true, 'regression' => true, 'repeated_failure' => true, 'changed' => true, 'fingerprint' => true ) ),
			'csp_post_probe_acknowledgement' => array_intersect_key( BaoCache_CSP::post_probe_acknowledgement(), array( 'matched' => true, 'acknowledged_at' => true, 'fingerprint' => true ) ),
			'csp_post_probe_trend' => array_intersect_key( BaoCache_CSP::post_enforcement_probe_trend(), array( 'available' => true, 'window' => true, 'pass' => true, 'warn' => true, 'fail' => true, 'failure_streak' => true, 'avg_response_ms' => true, 'latest_at' => true ) ),
			'csp_post_probe_remediation' => BaoCache_CSP::post_enforcement_remediation(),
			'csp_post_probe_acknowledgement_history' => BaoCache_CSP::post_probe_acknowledgement_history(),
			'critical_image_snapshot' => BaoCache_Critical_Images::snapshot(),
			'critical_image_application' => BaoCache_Critical_Images::application(),
			'activity' => BaoCache_Activity::recent( 200 ),
			'configuration' => BaoCache_Settings::get(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=baocache-report-' . gmdate( 'Ymd-His' ) . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public function restore_revision(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền khôi phục cấu hình.', 'baocache' ) );
		}
		check_admin_referer( self::RESTORE_ACTION );
		$index = absint( $_POST['revision'] ?? -1 );
		$history = get_option( 'baocache_settings_history', array() );
		if ( ! is_array( $history ) || ! isset( $history[ $index ]['settings'] ) || ! is_array( $history[ $index ]['settings'] ) ) {
			wp_die( esc_html__( 'Không tìm thấy revision yêu cầu.', 'baocache' ) );
		}
		$current = get_option( BAOCACHE_OPTION, array() );
		if ( is_array( $current ) ) {
			array_unshift( $history, array( 'saved_at' => time(), 'settings' => $current ) );
		}
		update_option( BAOCACHE_OPTION, BaoCache_Settings::sanitize( $history[ $index + 1 ]['settings'] ?? $history[ $index ]['settings'] ), false );
		update_option( 'baocache_settings_history', array_slice( $history, 0, 5 ), false );
		wp_safe_redirect( add_query_arg( 'baocache_restore', 'success', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	public function purge_fastcgi_url_ajax(): void {
		check_ajax_referer( self::PURGE_URL_AJAX_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền purge FastCGI cache.', 'baocache' ) ), 403 );
		}
		$url = esc_url_raw( (string) wp_unslash( $_POST['url'] ?? '' ), array( 'http', 'https' ) );
		$result = ( new BaoCache_Purge() )->purge_url( $url );
		BaoCache_Activity::log( 'fastcgi_purge', $result['success'] ? 'success' : 'failed', $result['success'] ? __( 'Đã purge FastCGI URL.', 'baocache' ) : __( 'Purge FastCGI URL thất bại.', 'baocache' ), array( 'path' => BaoCache_Activity::safe_path( $url ), 'http_status' => (string) $result['code'] ) );
		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $this->purge_result_message( $result ), 'code' => $result['code'], 'state' => $result['state'] ), 422 );
		}
		wp_send_json_success( array( 'message' => $this->purge_result_message( $result ), 'code' => $result['code'], 'state' => $result['state'] ) );
	}

	/** @param array{success: bool, code: int, state: string, message: string} $result */
	private function purge_result_message( array $result ): string {
		$message = (string) $result['message'];
		return (int) $result['code'] > 0 ? sprintf( '%s (HTTP %d)', $message, (int) $result['code'] ) : $message;
	}

	public function verify_fastcgi_purge_ajax(): void {
		check_ajax_referer( self::PURGE_VERIFY_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền xác minh purge endpoint.', 'baocache' ) ), 403 );
		}
		$result = ( new BaoCache_Purge() )->verify_endpoint();
		BaoCache_Activity::log( 'fastcgi_purge_verify', $result['success'] ? 'success' : 'failed', $result['success'] ? __( 'Đã xác minh FastCGI purge endpoint.', 'baocache' ) : __( 'FastCGI purge endpoint không xác minh được.', 'baocache' ), array( 'http_status' => (string) $result['code'], 'state' => (string) $result['state'] ) );
		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'], 'code' => $result['code'], 'state' => $result['state'] ), 422 );
		}
		wp_send_json_success( array( 'message' => $result['message'], 'code' => $result['code'], 'state' => $result['state'] ) );
	}

	public function delay_preview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền chạy Delay preview.', 'baocache' ) );
		}
		check_admin_referer( self::DELAY_PREVIEW_ACTION );
		$enabled = 'stop' !== sanitize_key( (string) ( $_GET['mode'] ?? 'start' ) );
		BaoCache_Runtime::set_delay_preview( $enabled );
		BaoCache_Activity::log( 'delay_preview', 'success', $enabled ? __( 'Đã mở Delay preview 30 phút.', 'baocache' ) : __( 'Đã kết thúc Delay preview.', 'baocache' ) );
		wp_safe_redirect( $enabled ? home_url( '/' ) : admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public function refresh_warmup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền tạo warm queue.', 'baocache' ) );
		}
		check_admin_referer( self::WARM_ACTION );
		$added = ( new BaoCache_Warmup() )->refresh_sitemap();
		BaoCache_Activity::log( 'warm_queue', $added > 0 ? 'success' : 'warning', $added > 0 ? sprintf( __( 'Đã thêm %d URL vào Warm Queue.', 'baocache' ), $added ) : __( 'Warm Queue không thêm URL mới.', 'baocache' ) );
		wp_safe_redirect( add_query_arg( array( 'baocache_warmup' => $added > 0 ? 'queued' : 'empty' ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	public function refresh_warmup_ajax(): void {
		check_ajax_referer( self::WARM_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền tạo warm queue.', 'baocache' ) ), 403 );
		}
		$added = ( new BaoCache_Warmup() )->refresh_sitemap();
		$status = BaoCache_Warmup::status();
		if ( $added < 1 ) {
			BaoCache_Activity::log( 'warm_queue', 'warning', ! empty( $status['last_sitemap_error'] ) ? (string) $status['last_sitemap_error'] : __( 'Warm Queue không tìm thấy URL hợp lệ.', 'baocache' ) );
			wp_send_json_error( array( 'message' => ! empty( $status['last_sitemap_error'] ) ? (string) $status['last_sitemap_error'] : __( 'Không tìm thấy URL hợp lệ để thêm vào queue.', 'baocache' ) ), 422 );
		}
		BaoCache_Activity::log( 'warm_queue', 'success', sprintf( __( 'Đã thêm %d URL vào Warm Queue.', 'baocache' ), $added ), array( 'queued' => (string) ( $status['queued'] ?? 0 ) ) );
		wp_send_json_success( array( 'added' => $added, 'queued' => (int) ( $status['queued'] ?? 0 ), 'detected_sitemap' => (string) ( $status['detected_sitemap'] ?? '' ) ) );
	}

	public function scan_assets_ajax(): void {
		check_ajax_referer( self::SCAN_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền quét asset.', 'baocache' ) ), 403 );
		}
		$inventory = BaoCache_Runtime::scan_asset_inventory();
		if ( is_wp_error( $inventory ) ) {
			BaoCache_Activity::log( 'asset_scan', 'failed', $inventory->get_error_message() );
			wp_send_json_error( array( 'message' => $inventory->get_error_message() ), 502 );
		}
		$assets = is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		BaoCache_Activity::log( 'asset_scan', 'success', sprintf( __( 'Đã quét %d assets.', 'baocache' ), count( $assets ) ) );
		wp_send_json_success( array( 'count' => count( $assets ), 'scanned_at' => (int) ( $inventory['captured_at'] ?? time() ) ) );
	}

	public function scan_critical_images_ajax(): void {
		check_ajax_referer( self::CRITICAL_IMAGE_SCAN_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền quét critical images.', 'baocache' ) ), 403 );
		}
		$probe = $this->probe_public_url( home_url( '/' ) );
		if ( is_wp_error( $probe ) ) {
			BaoCache_Activity::log( 'critical_image_scan', 'failed', $probe->get_error_message() );
			wp_send_json_error( array( 'message' => $probe->get_error_message() ), 502 );
		}
		if ( 200 > (int) $probe['status'] || 299 < (int) $probe['status'] ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Frontend trả HTTP %d; chưa thể phân tích ảnh.', 'baocache' ), (int) $probe['status'] ) ), 502 );
		}
		$snapshot = BaoCache_Critical_Images::analyze( (string) $probe['body'] );
		if ( is_wp_error( $snapshot ) ) {
			BaoCache_Activity::log( 'critical_image_scan', 'failed', $snapshot->get_error_message() );
			wp_send_json_error( array( 'message' => $snapshot->get_error_message() ), 422 );
		}
		$top = is_array( $snapshot['candidates'][0] ?? null ) ? $snapshot['candidates'][0] : array();
		BaoCache_Activity::log( 'critical_image_scan', 'success', sprintf( __( 'Đã phân tích %d critical image candidates.', 'baocache' ), (int) $snapshot['candidate_count'] ), array( 'candidates' => (string) $snapshot['candidate_count'], 'top_kind' => sanitize_key( (string) ( $top['kind'] ?? 'none' ) ) ) );
		wp_send_json_success( array( 'count' => (int) $snapshot['candidate_count'], 'scanned_at' => (int) $snapshot['scanned_at'] ) );
	}

	public function apply_critical_image_ajax(): void {
		check_ajax_referer( self::CRITICAL_IMAGE_APPLY_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền áp dụng critical image.', 'baocache' ) ), 403 );
		}
		$fingerprint = sanitize_text_field( (string) wp_unslash( $_POST['fingerprint'] ?? '' ) );
		$result = BaoCache_Critical_Images::apply( $fingerprint );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}
		$candidate = is_array( $result['candidate'] ?? null ) ? $result['candidate'] : array();
		$probe_url = add_query_arg( 'baocache-critical-image-probe', wp_generate_uuid4(), home_url( '/' ) );
		$probe = $this->probe_public_url( $probe_url );
		$verification = is_wp_error( $probe ) ? array( 'present' => false, 'preload' => false, 'fetchpriority' => false ) : BaoCache_Critical_Images::verify_html( (string) $probe['body'], (string) ( $candidate['url'] ?? '' ) );
		$verified = ! is_wp_error( $probe ) && 200 <= (int) $probe['status'] && 299 >= (int) $probe['status'] && ! in_array( false, $verification, true );
		if ( ! $verified ) {
			$rollback = BaoCache_Critical_Images::rollback();
			$this->queue_frontend_cache_invalidation();
			BaoCache_Activity::log( 'critical_image_apply', 'failed', __( 'Post-change probe không xác minh đủ output; BaoCache đã rollback.', 'baocache' ), array( 'present' => ! empty( $verification['present'] ) ? 'yes' : 'no', 'preload' => ! empty( $verification['preload'] ) ? 'yes' : 'no', 'priority' => ! empty( $verification['fetchpriority'] ) ? 'yes' : 'no', 'rollback' => is_wp_error( $rollback ) ? 'failed' : 'success' ) );
			wp_send_json_error( array( 'message' => __( 'Post-change probe không thấy đủ image, preload và fetchpriority; thay đổi đã được rollback.', 'baocache' ), 'verification' => $verification ), 422 );
		}
		$this->queue_frontend_cache_invalidation();
		BaoCache_Activity::log( 'critical_image_apply', 'success', __( 'Đã áp dụng và xác minh critical image candidate.', 'baocache' ), array( 'path' => BaoCache_Activity::safe_path( (string) $candidate['url'] ), 'confidence' => (string) (int) ( $candidate['confidence'] ?? 0 ) ) );
		wp_send_json_success( array( 'candidate' => $candidate, 'verification' => $verification ) );
	}

	public function scan_resource_hints_ajax(): void {
		check_ajax_referer( self::RESOURCE_HINT_SCAN_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền quét Resource Hints.', 'baocache' ) ), 403 );
		$result = BaoCache_Resource_Hints::scan();
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		BaoCache_Activity::log( 'resource_hints_scan', 'success', __( 'Đã tạo recommendation Resource & Font Hints từ Asset Inventory evidence.', 'baocache' ), array( 'candidates' => (string) $result['candidate_count'], 'fingerprint' => substr( (string) $result['fingerprint'], 0, 12 ) ) );
		wp_send_json_success( array( 'count' => (int) $result['candidate_count'], 'fingerprint' => (string) $result['fingerprint'] ) );
	}

	public function apply_resource_hints_ajax(): void {
		check_ajax_referer( self::RESOURCE_HINT_APPLY_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền áp dụng Resource Hints.', 'baocache' ) ), 403 );
		$fingerprint = sanitize_text_field( (string) wp_unslash( $_POST['fingerprint'] ?? '' ) );
		$result = BaoCache_Resource_Hints::apply( $fingerprint );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		$this->queue_frontend_cache_invalidation();
		BaoCache_Activity::log( 'resource_hints_apply', 'success', __( 'Đã áp dụng recommendation Resource & Font Hints.', 'baocache' ), array( 'fingerprint' => substr( $fingerprint, 0, 12 ) ) );
		wp_send_json_success( array( 'message' => __( 'Đã áp dụng Resource & Font Hints và xếp hàng purge frontend.', 'baocache' ) ) );
	}

	public function rollback_resource_hints_ajax(): void {
		check_ajax_referer( self::RESOURCE_HINT_ROLLBACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rollback Resource Hints.', 'baocache' ) ), 403 );
		$result = BaoCache_Resource_Hints::rollback();
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		$this->queue_frontend_cache_invalidation();
		BaoCache_Activity::log( 'resource_hints_rollback', 'success', __( 'Đã rollback Resource & Font Hints.', 'baocache' ) );
		wp_send_json_success( array( 'message' => __( 'Đã rollback Resource & Font Hints về cấu hình trước đó.', 'baocache' ) ) );
	}

	public function scan_third_party_ajax(): void {
		check_ajax_referer( self::THIRD_PARTY_SCAN_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền phân tích third-party script.', 'baocache' ) ), 403 );
		$result = BaoCache_Third_Party_Optimizer::scan();
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		BaoCache_Activity::log( 'third_party_scan', 'success', __( 'Đã phân tích third-party script từ Asset Inventory evidence.', 'baocache' ), array( 'candidates' => (string) $result['candidate_count'], 'fingerprint' => substr( (string) $result['fingerprint'], 0, 12 ) ) );
		wp_send_json_success( array( 'count' => (int) $result['candidate_count'], 'fingerprint' => (string) $result['fingerprint'] ) );
	}

	public function apply_third_party_ajax(): void {
		check_ajax_referer( self::THIRD_PARTY_APPLY_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền apply third-party delay.', 'baocache' ) ), 403 );
		$fingerprint = sanitize_text_field( (string) wp_unslash( $_POST['fingerprint'] ?? '' ) );
		$result = BaoCache_Third_Party_Optimizer::apply( $fingerprint );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		$this->queue_frontend_cache_invalidation();
		BaoCache_Activity::log( 'third_party_apply', 'success', __( 'Đã apply third-party delay recommendation.', 'baocache' ), array( 'fingerprint' => substr( $fingerprint, 0, 12 ) ) );
		wp_send_json_success( array( 'message' => __( 'Đã apply delay cho third-party script và xếp hàng purge frontend.', 'baocache' ) ) );
	}

	public function rollback_third_party_ajax(): void {
		check_ajax_referer( self::THIRD_PARTY_ROLLBACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rollback third-party delay.', 'baocache' ) ), 403 );
		$result = BaoCache_Third_Party_Optimizer::rollback();
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		$this->queue_frontend_cache_invalidation();
		BaoCache_Activity::log( 'third_party_rollback', 'success', __( 'Đã rollback third-party delay recommendation.', 'baocache' ) );
		wp_send_json_success( array( 'message' => __( 'Đã rollback third-party delay về cấu hình trước đó.', 'baocache' ) ) );
	}

	public function rollback_critical_image_ajax(): void {
		check_ajax_referer( self::CRITICAL_IMAGE_ROLLBACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rollback Critical Image.', 'baocache' ) ), 403 );
		}
		$result = BaoCache_Critical_Images::rollback();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}
		$this->queue_frontend_cache_invalidation();
		BaoCache_Activity::log( 'critical_image_rollback', 'success', __( 'Đã rollback Automatic Critical Image.', 'baocache' ) );
		wp_send_json_success( array( 'message' => __( 'Đã rollback Critical Image về cấu hình trước đó.', 'baocache' ) ) );
	}

	public function clear_frontend_metrics_ajax(): void {
		check_ajax_referer( self::CLEAR_FRONTEND_METRICS_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền xóa Browser Resource Timing.', 'baocache' ) ), 403 );
		}
		BaoCache_Frontend_Metrics::clear();
		BaoCache_Activity::log( 'frontend_timing_cleared', 'success', __( 'Đã xóa Browser Resource Timing tổng hợp.', 'baocache' ) );
		wp_send_json_success();
	}

	public function apply_csp_recommendation_ajax(): void {
		check_ajax_referer( self::CSP_APPLY_RECOMMENDATION_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền áp dụng CSP recommendation.', 'baocache' ) ), 403 );
		}
		$result = BaoCache_CSP::apply_recommendation( sanitize_text_field( (string) ( $_POST['recommendation'] ?? '' ) ) );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => (string) $result['message'] ), 422 );
		}
		BaoCache_Activity::log( 'csp_recommendation_applied', 'success', __( 'Đã áp dụng CSP source recommendation từ Report-Only evidence.', 'baocache' ) );
		wp_send_json_success( array( 'message' => (string) $result['message'] ) );
	}

	public function dismiss_csp_recommendation_ajax(): void {
		check_ajax_referer( self::CSP_DISMISS_RECOMMENDATION_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền bỏ qua CSP recommendation.', 'baocache' ) ), 403 );
		}
		if ( ! BaoCache_CSP::dismiss_recommendation( sanitize_text_field( (string) ( $_POST['recommendation'] ?? '' ) ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Recommendation không còn hợp lệ. Hãy tải lại evidence CSP.', 'baocache' ) ), 422 );
		}
		BaoCache_Activity::log( 'csp_recommendation_dismissed', 'success', __( 'Đã bỏ qua CSP source recommendation cho fingerprint hiện tại.', 'baocache' ) );
		wp_send_json_success();
	}

	public function rollback_csp_recommendation_ajax(): void {
		check_ajax_referer( self::CSP_ROLLBACK_RECOMMENDATION_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rollback CSP recommendation.', 'baocache' ) ), 403 );
		}
		$result = BaoCache_CSP::rollback_recommendation( sanitize_text_field( (string) ( $_POST['record'] ?? '' ) ) );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => (string) $result['message'] ), 422 );
		}
		BaoCache_Activity::log( 'csp_recommendation_rolled_back', 'success', __( 'Đã rollback CSP source recommendation về source list trước đó.', 'baocache' ) );
		wp_send_json_success( array( 'message' => (string) $result['message'] ) );
	}

	/**
	 * Probe the public response after an operator enables CSP Enforce.
	 * Only bounded metadata is returned/stored; policy text and response body never leave this request.
	 */
	public function csp_post_enforcement_probe_ajax(): void {
		check_ajax_referer( self::CSP_POST_PROBE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền kiểm tra CSP public.', 'baocache' ) ), 403 );
		}
		$settings = BaoCache_Settings::get();
		$result = $this->collect_csp_post_probe( $settings );
		if ( is_wp_error( $result ) ) {
			BaoCache_Activity::log( 'csp_post_enforcement_probe', 'failed', __( 'CSP post-enforcement public probe thất bại.', 'baocache' ), array( 'reason' => 'request_failed' ) );
			wp_send_json_error( array( 'message' => __( 'Không thể đọc public response CSP. Hãy thử lại sau.', 'baocache' ) ), 502 );
		}
		BaoCache_CSP::record_post_enforcement_probe( $result, 'manual' );
		$outcome = (string) $result['outcome'];
		$status = (int) $result['status_code'];
		BaoCache_Activity::log( 'csp_post_enforcement_probe', 'pass' === $outcome ? 'success' : ( 'warn' === $outcome ? 'warning' : 'failed' ), sprintf( __( 'CSP public probe: %s · HTTP %d.', 'baocache' ), strtoupper( $outcome ), $status ), array( 'http_status' => (string) $status, 'mode' => (string) $result['mode'], 'matches' => ! empty( $result['matches'] ) ? 'yes' : 'no', 'conflict' => ! empty( $result['conflict'] ) ? 'yes' : 'no' ) );
		$result['message'] = 'pass' === $outcome ? __( 'Public CSP Enforce khớp policy BaoCache.', 'baocache' ) : ( 'warn' === $outcome ? __( 'Public response đã đọc; CSP chưa ở Enforce hoặc cần operator review.', 'baocache' ) : __( 'Public CSP không khớp hoặc có conflict. Chưa tự động rollback.', 'baocache' ) );
		wp_send_json_success( $result );
	}

	/** Acknowledge the newest failed scheduled canary; this never changes CSP. */
	public function csp_probe_acknowledge_ajax(): void {
		check_ajax_referer( self::CSP_PROBE_ACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền xác nhận CSP canary.', 'baocache' ) ), 403 );
		}
		if ( ! BaoCache_CSP::record_post_probe_acknowledgement() ) {
			wp_send_json_error( array( 'message' => __( 'Không còn canary failure scheduled cần xác nhận.', 'baocache' ) ), 409 );
		}
		BaoCache_Activity::log( 'csp_post_probe_acknowledged', 'warning', __( 'Operator đã xác nhận CSP scheduled canary failure; policy không thay đổi.', 'baocache' ), array( 'source' => 'scheduled' ) );
		wp_send_json_success( array( 'message' => __( 'Đã xác nhận canary failure. CSP vẫn giữ nguyên; không tự rollback.', 'baocache' ) ) );
	}

	/** Save one remediation checkbox/note for the current canary fingerprint. */
	public function csp_remediation_step_ajax(): void {
		check_ajax_referer( self::CSP_REMEDIATION_STEP_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền lưu remediation step.', 'baocache' ) ), 403 );
		}
		$step = sanitize_key( (string) ( $_POST['step'] ?? '' ) );
		$completed = '1' === (string) ( $_POST['completed'] ?? '' );
		$note = sanitize_textarea_field( (string) wp_unslash( $_POST['note'] ?? '' ) );
		if ( ! BaoCache_CSP::save_remediation_step( $step, $completed, $note ) ) {
			wp_send_json_error( array( 'message' => __( 'Remediation step không còn hợp lệ hoặc chưa có scheduled canary.', 'baocache' ) ), 409 );
		}
		BaoCache_Activity::log( 'csp_remediation_step', 'success', __( 'Đã cập nhật CSP remediation step.', 'baocache' ), array( 'step' => $step, 'completed' => $completed ? 'yes' : 'no', 'has_note' => '' !== $note ? 'yes' : 'no' ) );
		wp_send_json_success( array( 'message' => $completed ? __( 'Đã đánh dấu bước hoàn tất.', 'baocache' ) : __( 'Đã cập nhật bước remediation.', 'baocache' ) ) );
	}

	/** @return array<string, mixed>|WP_Error */
	private function collect_csp_post_probe( array $settings ): array|WP_Error {
		$response = $this->probe_public_url( home_url( '/' ) );
		if ( is_wp_error( $response ) ) return $response;
		$enforce = $this->header_values( $response['headers'], 'content-security-policy' );
		$report = $this->header_values( $response['headers'], 'content-security-policy-report-only' );
		$all = array_merge( $enforce, $report );
		$matches = 1 === count( $all ) && '' !== $all[0] && str_starts_with( $all[0], BaoCache_CSP::build_policy( $settings ) );
		$conflict = ( ! empty( $enforce ) && ! empty( $report ) ) || count( $enforce ) > 1 || count( $report ) > 1 || ( ! empty( $all ) && ! $matches );
		$mode = ! empty( $enforce ) ? 'enforce' : ( ! empty( $report ) ? 'report-only' : 'none' );
		$status = (int) $response['status'];
		$outcome = ( ! ( $status >= 200 && $status < 400 ) || $conflict || ( 'enforce' === (string) $settings['csp_mode'] && ( 1 !== count( $enforce ) || ! $matches ) ) ) ? 'fail' : ( 'enforce' === (string) $settings['csp_mode'] ? 'pass' : 'warn' );
		return array( 'status_code' => $status, 'response_ms' => max( 0, min( 60000, (int) $response['response_ms'] ) ), 'mode' => $mode, 'present' => ! empty( $all ), 'matches' => $matches, 'conflict' => $conflict, 'outcome' => $outcome );
	}

	/** Roll back CSP to Report-Only only after an explicit operator confirmation. */
	public function csp_manual_rollback_ajax(): void {
		check_ajax_referer( self::CSP_MANUAL_ROLLBACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rollback CSP.', 'baocache' ) ), 403 );
		}
		if ( '1' !== (string) ( $_POST['confirm'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'Cần xác nhận rollback thủ công.', 'baocache' ) ), 422 );
		}
		$current = BaoCache_Settings::get();
		if ( empty( $current['csp_enabled'] ) || 'enforce' !== (string) $current['csp_mode'] ) {
			wp_send_json_error( array( 'message' => __( 'CSP hiện không ở Enforce; không cần rollback.', 'baocache' ) ), 409 );
		}
		$current['csp_mode'] = 'report';
		$current['csp_collect_reports'] = true;
		$settings = BaoCache_Settings::sanitize( $current );
		update_option( BAOCACHE_OPTION, $settings, false );
		$this->ensure_csp_canary_schedule();
		BaoCache_CSP::record_policy_snapshot( $settings );
		BaoCache_Activity::log( 'csp_manual_rollback', 'warning', __( 'Operator đã chuyển CSP từ Enforce về Report-Only.', 'baocache' ), array( 'mode' => 'report-only' ) );
		wp_send_json_success( array( 'message' => __( 'Đã chuyển CSP về Report-Only để kiểm tra lại frontend.', 'baocache' ), 'mode' => 'report-only' ) );
	}

	public function take_runtime_snapshot_ajax(): void {
		check_ajax_referer( self::SNAPSHOT_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền lấy runtime snapshot.', 'baocache' ) ), 403 );
		}
		$record = ( new BaoCache_Metrics() )->snapshot();
		if ( empty( $record ) ) {
			wp_send_json_error( array( 'message' => __( 'Snapshot đang được tạo bởi một worker khác. Hãy thử lại sau một phút.', 'baocache' ) ), 409 );
		}
		$count = count( BaoCache_Metrics::history() );
		BaoCache_Activity::log( 'runtime_snapshot', 'success', __( 'Đã lưu runtime snapshot.', 'baocache' ), array( 'snapshots' => (string) $count ) );
		wp_send_json_success( array( 'recorded_at' => (int) $record['recorded_at'], 'count' => $count ) );
	}

	/** Runs only when an administrator explicitly asks for the opt-in audit. */
	public function cloudflare_audit_ajax(): void {
		check_ajax_referer( self::CLOUDFLARE_AUDIT_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền kiểm tra Cloudflare.', 'baocache' ) ), 403 );
		}
		$result = BaoCache_Cloudflare::audit();
		if ( empty( $result['success'] ) ) {
			BaoCache_Activity::log( 'cloudflare_audit', 'warning', __( 'Cloudflare read-only audit chưa hoàn tất.', 'baocache' ), array( 'state' => (string) ( $result['state'] ?? 'unknown' ), 'http_status' => (string) ( $result['http_status'] ?? 0 ) ) );
			wp_send_json_error( array( 'message' => (string) ( $result['message'] ?? __( 'Không thể kiểm tra Cloudflare.', 'baocache' ) ) ), 422 );
		}
		BaoCache_Activity::log( 'cloudflare_audit', 'success', __( 'Đã hoàn tất Cloudflare read-only audit.', 'baocache' ), array( 'zone' => (string) ( $result['zone'] ?? '' ), 'zone_status' => (string) ( $result['zone_status'] ?? '' ) ) );
		wp_send_json_success( $result );
	}

	public function probe_hardening_ajax(): void {
		check_ajax_referer( self::HARDENING_PROBE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền kiểm tra public response.', 'baocache' ) ), 403 );
		}

		$settings = BaoCache_Settings::get();
		$home_url = home_url( '/' );
		$feed_url = function_exists( 'get_feed_link' ) ? get_feed_link() : home_url( '/feed/' );
		$rest_users_url = rest_url( 'wp/v2/users' );
		$home = $this->probe_public_url( $home_url );
		$feed = $this->probe_public_url( $feed_url );
		$users = $this->probe_public_url( $rest_users_url );
		if ( is_wp_error( $home ) || is_wp_error( $feed ) || is_wp_error( $users ) ) {
			$error = is_wp_error( $home ) ? $home : ( is_wp_error( $feed ) ? $feed : $users );
			wp_send_json_error( array( 'message' => $error->get_error_message() ), 502 );
		}

		$home_body = strtolower( (string) $home['body'] );
		$home_headers = $home['headers'];
		$checks = array();
		$feed_status = (int) $feed['status'];
		$rss_mode = (string) $settings['rss_mode'];
		$rss_pass = ( 'keep' === $rss_mode && 200 === $feed_status ) || ( 'redirect' === $rss_mode && in_array( $feed_status, array( 301, 302, 307, 308 ), true ) ) || ( 'gone' === $rss_mode && 410 === $feed_status );
		$checks[] = array( 'label' => __( 'RSS response', 'baocache' ), 'state' => $rss_pass ? 'good' : 'warn', 'value' => sprintf( __( 'Policy %1$s · HTTP %2$d', 'baocache' ), strtoupper( $rss_mode ), $feed_status ) );

		$users_status = (int) $users['status'];
		if ( ! empty( $settings['disable_rest_user_enumeration'] ) ) {
			$users_pass = 404 === $users_status;
			$checks[] = array( 'label' => __( 'REST users endpoint', 'baocache' ), 'state' => $users_pass ? 'good' : 'warn', 'value' => $users_pass ? __( '404 · public enumeration blocked', 'baocache' ) : sprintf( __( 'HTTP %d · kiểm tra lại policy', 'baocache' ), $users_status ) );
		} else {
			$checks[] = array( 'label' => __( 'REST users endpoint', 'baocache' ), 'state' => 'neutral', 'value' => sprintf( __( 'HTTP %d · policy disabled', 'baocache' ), $users_status ) );
		}

		$generator_found = false !== strpos( $home_body, 'name="generator"' ) || false !== strpos( $home_body, "name='generator'" );
		$feed_link_found = false !== strpos( $home_body, 'application/rss+xml' ) || false !== strpos( $home_body, 'application/atom+xml' );
		$rest_link_found = false !== strpos( $home_body, 'api.w.org' );
		$pingback = (string) wp_remote_retrieve_header( $home_headers, 'x-pingback' );
		$checks[] = array( 'label' => __( 'Generator metadata', 'baocache' ), 'state' => ( ! empty( $settings['remove_generator'] ) && ! $generator_found ) || ( empty( $settings['remove_generator'] ) && $generator_found ) ? 'good' : 'warn', 'value' => $generator_found ? __( 'Found in HTML', 'baocache' ) : __( 'Not found in HTML', 'baocache' ) );
		$checks[] = array( 'label' => __( 'Feed discovery links', 'baocache' ), 'state' => $feed_link_found === $this->expected_public_link( $settings, 'feed' ) ? 'good' : 'warn', 'value' => $feed_link_found ? __( 'Found in HTML', 'baocache' ) : __( 'Not found in HTML', 'baocache' ) );
		$checks[] = array( 'label' => __( 'REST discovery link', 'baocache' ), 'state' => $rest_link_found === empty( $settings['remove_rest_api_link'] ) ? 'good' : 'warn', 'value' => $rest_link_found ? __( 'Found in HTML', 'baocache' ) : __( 'Not found in HTML', 'baocache' ) );
		$checks[] = array( 'label' => __( 'X-Pingback header', 'baocache' ), 'state' => ( ! empty( $settings['remove_x_pingback'] ) && '' === $pingback ) || ( empty( $settings['remove_x_pingback'] ) && '' !== $pingback ) ? 'good' : 'warn', 'value' => '' === $pingback ? __( 'Not present', 'baocache' ) : __( 'Present', 'baocache' ) );

		$passed = count( array_filter( $checks, static fn( array $check ): bool => 'good' === ( $check['state'] ?? '' ) ) );
		$history = get_option( 'baocache_hardening_probe_history', array() );
		$history = is_array( $history ) ? $history : array();
		$baseline = get_option( 'baocache_hardening_probe_baseline', array() );
		$comparison_checks = ! empty( $baseline['checks'] ) ? $baseline['checks'] : ( $history[0]['checks'] ?? array() );
		$previous_checks = array();
		foreach ( (array) $comparison_checks as $previous_check ) {
			if ( is_array( $previous_check ) && isset( $previous_check['label'] ) ) {
				$previous_checks[ (string) $previous_check['label'] ] = $previous_check;
			}
		}
		$regressions = array();
		$improvements = array();
		foreach ( $checks as $check ) {
			$label = (string) $check['label'];
			$previous_state = (string) ( $previous_checks[ $label ]['state'] ?? '' );
			$current_state = (string) $check['state'];
			if ( 'good' === $previous_state && 'good' !== $current_state ) {
				$regressions[] = array( 'label' => $label, 'from' => $previous_state, 'to' => $current_state, 'value' => (string) $check['value'] );
			} elseif ( 'good' !== $previous_state && 'good' === $current_state && '' !== $previous_state ) {
				$improvements[] = array( 'label' => $label, 'from' => $previous_state, 'to' => $current_state, 'value' => (string) $check['value'] );
			}
		}
		$probe_record = array( 'checked_at' => time(), 'source' => 'manual', 'passed' => $passed, 'total' => count( $checks ), 'response_ms' => max( (int) $home['response_ms'], (int) $feed['response_ms'], (int) $users['response_ms'] ), 'regressions' => $regressions, 'improvements' => $improvements, 'checks' => array_map( static fn( array $check ): array => array( 'label' => (string) $check['label'], 'state' => (string) $check['state'], 'value' => (string) $check['value'] ), $checks ) );
		array_unshift( $history, $probe_record );
		update_option( 'baocache_hardening_probe_history', array_slice( $history, 0, 10 ), false );
		BaoCache_Activity::log( 'hardening_probe', empty( $regressions ) ? 'success' : 'warning', sprintf( __( 'Public Response Probe: %1$d/%2$d PASS.', 'baocache' ), $passed, count( $checks ) ), array( 'passed' => (string) $passed, 'checks' => (string) count( $checks ), 'regressions' => (string) count( $regressions ) ) );
		wp_send_json_success( array( 'checked_at' => current_time( 'mysql' ), 'probe_id' => (int) $probe_record['checked_at'], 'checks' => $checks, 'regressions' => $regressions, 'improvements' => $improvements, 'response_ms' => (int) $probe_record['response_ms'] ) );
	}

	public function set_hardening_baseline_ajax(): void {
		check_ajax_referer( self::HARDENING_BASELINE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền đặt baseline.', 'baocache' ) ), 403 );
		}
		$history = get_option( 'baocache_hardening_probe_history', array() );
		$latest = is_array( $history ) ? ( $history[0] ?? array() ) : array();
		$baseline_ready = ! empty( $latest['checks'] ) && empty( array_filter( (array) $latest['checks'], static fn( mixed $check ): bool => is_array( $check ) && in_array( (string) ( $check['state'] ?? '' ), array( 'warn', 'bad' ), true ) ) );
		if ( ! $baseline_ready ) {
			wp_send_json_error( array( 'message' => __( 'Chỉ đặt baseline từ một probe PASS hoàn chỉnh.', 'baocache' ) ), 422 );
		}
		$baseline = array( 'created_at' => time(), 'environment' => wp_get_environment_type(), 'passed' => (int) $latest['passed'], 'total' => (int) $latest['total'], 'checks' => $latest['checks'] );
		update_option( 'baocache_hardening_probe_baseline', $baseline, false );
		BaoCache_Activity::log( 'hardening_baseline', 'success', __( 'Đã đặt baseline Hardening từ probe PASS.', 'baocache' ), array( 'checks' => (string) $baseline['total'] ) );
		wp_send_json_success( array( 'created_at' => current_time( 'mysql' ), 'checks' => $baseline['total'] ) );
	}

	public function acknowledge_hardening_probe_ajax(): void {
		check_ajax_referer( self::HARDENING_ACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền xác nhận cảnh báo.', 'baocache' ) ), 403 );
		}
		$probe_id = absint( $_POST['probe_id'] ?? 0 );
		$history = get_option( 'baocache_hardening_probe_history', array() );
		$history = is_array( $history ) ? $history : array();
		$found = false;
		foreach ( $history as $record ) {
			if ( is_array( $record ) && $probe_id === (int) ( $record['checked_at'] ?? 0 ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			wp_send_json_error( array( 'message' => __( 'Không tìm thấy snapshot probe này.', 'baocache' ) ), 404 );
		}
		$acknowledged = get_option( 'baocache_hardening_probe_acknowledged', array() );
		$acknowledged = is_array( $acknowledged ) ? array_map( 'absint', $acknowledged ) : array();
		$acknowledged[] = $probe_id;
		$acknowledged = array_values( array_unique( array_filter( $acknowledged ) ) );
		update_option( 'baocache_hardening_probe_acknowledged', array_slice( $acknowledged, -20 ), false );
		BaoCache_Activity::log( 'hardening_ack', 'success', __( 'Đã xác nhận cảnh báo Hardening Probe.', 'baocache' ), array( 'probe_id' => (string) $probe_id ) );
		wp_send_json_success( array( 'probe_id' => $probe_id, 'acknowledged' => true ) );
	}

	public function import_render_blocking_ajax(): void {
		check_ajax_referer( self::RENDER_BLOCKING_IMPORT_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền nhập audit frontend.', 'baocache' ) ), 403 );
		}
		$json = (string) wp_unslash( $_POST['json'] ?? '' );
		$snapshot = sanitize_key( (string) ( $_POST['snapshot'] ?? 'after' ) );
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		$result = BaoCache_Render_Blocking::parse( $json, $assets, $snapshot );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}
		BaoCache_Activity::log( 'render_blocking_audit', 'success', sprintf( __( 'Đã nhập audit render-blocking (%s).', 'baocache' ), 'baseline' === $snapshot ? __( 'baseline', 'baocache' ) : __( 'after', 'baocache' ) ), array( 'resources' => (string) count( $result['snapshots'][ $snapshot ]['items'] ?? array() ) ) );
		wp_send_json_success( $result );
	}

	public function preview_render_blocking_ajax(): void {
		check_ajax_referer( self::RENDER_BLOCKING_PREVIEW_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền preview strategy.', 'baocache' ) ), 403 );
		}
		$handle = sanitize_key( (string) ( $_POST['handle'] ?? '' ) );
		if ( '' === $handle || ! isset( wp_scripts()->registered[ $handle ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Handle không tồn tại trong registry hiện tại.', 'baocache' ) ), 404 );
		}
		$registry = wp_scripts();
		$script = $registry->registered[ $handle ];
		$reasons = array();
		if ( ! empty( $registry->get_data( $handle, 'before' ) ) || ! empty( $registry->get_data( $handle, 'after' ) ) || ! empty( $registry->get_data( $handle, 'data' ) ) ) $reasons[] = __( 'Có inline/localized code cần kiểm thử riêng.', 'baocache' );
		if ( ! empty( $registry->get_data( $handle, 'conditional' ) ) || ! empty( $registry->get_data( $handle, 'type' ) ) ) $reasons[] = __( 'Có conditional/module metadata.', 'baocache' );
		$dependencies = array_values( array_map( 'sanitize_key', (array) $script->deps ) );
		$eligible = empty( $reasons );
		wp_send_json_success( array( 'handle' => $handle, 'eligible' => $eligible, 'dependencies' => $dependencies, 'reason' => $eligible ? __( 'Có thể tạo bản nháp defer bằng strategy API của WordPress; dependency order vẫn do WordPress quản lý.', 'baocache' ) : implode( ' ', $reasons ) ) );
	}

	public function render_blocking_context_qa_ajax(): void {
		check_ajax_referer( self::RENDER_BLOCKING_CONTEXT_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền kiểm tra context strategy.', 'baocache' ) ), 403 );
		}
		$path = sanitize_text_field( (string) wp_unslash( $_POST['path'] ?? '/' ) );
		$handle = sanitize_key( (string) wp_unslash( $_POST['handle'] ?? '' ) );
		$context = array(
			'logged_in' => ! empty( $_POST['logged_in'] ),
			'preview' => ! empty( $_POST['preview'] ),
			'checkout' => ! empty( $_POST['checkout'] ),
			'admin' => ! empty( $_POST['admin'] ),
			'feed' => ! empty( $_POST['feed'] ),
			'rest' => ! empty( $_POST['rest'] ),
			'ajax' => ! empty( $_POST['ajax'] ),
		);
		$status = BaoCache_Render_Blocking::context_status( BaoCache_Settings::get(), $path, $handle, $context );
		BaoCache_Activity::log( 'render_blocking_context', $status['eligible'] ? 'success' : 'warning', $status['eligible'] ? __( 'Context QA: strategy đủ điều kiện.', 'baocache' ) : __( 'Context QA: strategy bị loại trừ.', 'baocache' ), array( 'path' => (string) $status['path'], 'handle' => $handle, 'reasons' => implode( ' | ', (array) $status['reasons'] ) ) );
		wp_send_json_success( $status );
	}

	public function stage_critical_css_ajax(): void {
		check_ajax_referer( self::CRITICAL_CSS_STAGE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền stage Critical CSS.', 'baocache' ) ), 403 );
		}
		$css = (string) wp_unslash( $_POST['css'] ?? '' );
		$template = sanitize_key( (string) ( $_POST['template'] ?? 'front-page' ) );
		$viewport = sanitize_key( (string) ( $_POST['viewport'] ?? 'desktop' ) );
		$result = BaoCache_Render_Blocking::stage_critical_css( $css, $template, $viewport );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}
		BaoCache_Activity::log( 'critical_css_staged', 'success', __( 'Đã validate và stage Critical CSS.', 'baocache' ), array( 'template' => $template, 'viewport' => $viewport, 'bytes' => (string) strlen( $css ) ) );
		wp_send_json_success( array( 'validated_at' => current_time( 'mysql' ), 'fingerprint' => substr( (string) $result['fingerprint'], 0, 12 ) ) );
	}

	public function rollback_critical_css_ajax(): void {
		check_ajax_referer( self::CRITICAL_CSS_ROLLBACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rollback Critical CSS.', 'baocache' ) ), 403 );
		}
		$record = BaoCache_Render_Blocking::critical_css();
		if ( empty( $record ) ) {
			wp_send_json_error( array( 'message' => __( 'Chưa có Critical CSS staged.', 'baocache' ) ), 404 );
		}
		$record['enabled'] = false;
		$record['rolled_back_at'] = time();
		update_option( 'baocache_critical_css', $record, false );
		$this->queue_frontend_cache_invalidation();
		BaoCache_Render_Blocking::record_strategy( 'critical-css', 'inline', 'Administrator rollback', 'frontend', true );
		BaoCache_Activity::log( 'critical_css_rollback', 'success', __( 'Đã rollback Critical CSS.', 'baocache' ) );
		wp_send_json_success( array( 'rolled_back' => true ) );
	}

	private function compatibility_qa_items(): array {
		return array(
			'menu' => array( 'label' => __( 'Menu / navigation', 'baocache' ), 'detail' => __( 'Desktop, mobile menu, dropdown và keyboard navigation.', 'baocache' ) ),
			'form' => array( 'label' => __( 'Form / validation', 'baocache' ), 'detail' => __( 'Form liên hệ, validation, submit và thông báo lỗi/thành công.', 'baocache' ) ),
			'map' => array( 'label' => __( 'Map / interactive block', 'baocache' ), 'detail' => __( 'Bản đồ, zoom, marker và thao tác kéo trên thiết bị di động.', 'baocache' ) ),
			'analytics' => array( 'label' => __( 'Analytics / consent', 'baocache' ), 'detail' => __( 'Consent, page view và event sau khi defer/delay strategy chạy.', 'baocache' ) ),
			'chat' => array( 'label' => __( 'Chat / third-party widget', 'baocache' ), 'detail' => __( 'Widget chat mở đúng lúc, không lỗi console và không chặn nội dung chính.', 'baocache' ) ),
			'checkout' => array( 'label' => __( 'Checkout / payment', 'baocache' ), 'detail' => __( 'Checkout không bị defer, async CSS hoặc rule dequeue tác động.', 'baocache' ) ),
			'login' => array( 'label' => __( 'Login / authenticated', 'baocache' ), 'detail' => __( 'Login, wp-admin và phiên đăng nhập vẫn được bypass tự động.', 'baocache' ) ),
			'rollback' => array( 'label' => __( 'Rollback / recovery', 'baocache' ), 'detail' => __( 'Tắt strategy, purge đúng URL và xác nhận frontend trở lại bình thường.', 'baocache' ) ),
		);
	}

	public function save_compatibility_qa_ajax(): void {
		check_ajax_referer( self::COMPATIBILITY_QA_SAVE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền lưu Compatibility QA.', 'baocache' ) ), 403 );
		}
		$raw = (string) wp_unslash( $_POST['checks'] ?? '' );
		$input = json_decode( $raw, true );
		$allowed = array( 'pending', 'pass', 'fail', 'skip' );
		$items = $this->compatibility_qa_items();
		$checks = array();
		foreach ( $items as $id => $item ) {
			$value = is_array( $input ) ? sanitize_key( (string) ( $input[ $id ] ?? 'pending' ) ) : 'pending';
			$checks[ $id ] = in_array( $value, $allowed, true ) ? $value : 'pending';
		}
		$record = array( 'saved_at' => time(), 'environment' => wp_get_environment_type(), 'plugin_version' => BAOCACHE_VERSION, 'checks' => $checks );
		update_option( 'baocache_compatibility_qa', $record, false );
		$passed = count( array_filter( $checks, static fn( string $value ): bool => 'pass' === $value ) );
		$failed = count( array_filter( $checks, static fn( string $value ): bool => 'fail' === $value ) );
		BaoCache_Activity::log( 'compatibility_qa', $failed > 0 ? 'warning' : 'success', __( 'Đã lưu Compatibility QA trên staging.', 'baocache' ), array( 'passed' => (string) $passed, 'failed' => (string) $failed, 'environment' => (string) $record['environment'] ) );
		wp_send_json_success( array( 'saved_at' => current_time( 'mysql' ), 'passed' => $passed, 'failed' => $failed, 'checks' => $checks ) );
	}

	public function reset_compatibility_qa_ajax(): void {
		check_ajax_referer( self::COMPATIBILITY_QA_RESET_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền reset Compatibility QA.', 'baocache' ) ), 403 );
		}
		delete_option( 'baocache_compatibility_qa' );
		BaoCache_Activity::log( 'compatibility_qa', 'success', __( 'Đã reset Compatibility QA.', 'baocache' ) );
		wp_send_json_success( array( 'reset' => true ) );
	}

	public function save_rule_gate_ajax(): void {
		check_ajax_referer( self::RULE_GATE_SAVE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền cập nhật compatibility gate.', 'baocache' ) ), 403 );
		}
		$handle = sanitize_key( (string) wp_unslash( $_POST['handle'] ?? '' ) );
		$strategy = sanitize_key( (string) wp_unslash( $_POST['strategy'] ?? '' ) );
		$qa = sanitize_key( (string) wp_unslash( $_POST['qa'] ?? 'pending' ) );
		if ( '' === $handle || ! in_array( $strategy, array( 'defer', 'async-css', 'delay' ), true ) || ! in_array( $qa, array( 'pending', 'pass', 'fail' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule gate không hợp lệ.', 'baocache' ) ), 422 );
		}
		$gate = BaoCache_Render_Blocking::save_compatibility_gate( $handle, $strategy, $qa, ! empty( $_POST['rollback_verified'] ) );
		$outcome = ( 'pass' === $qa && ! empty( $gate['rollback_verified'] ) ) ? 'success' : 'warning';
		BaoCache_Activity::log( 'rule_gate', $outcome, sprintf( __( 'Đã cập nhật gate %1$s cho %2$s.', 'baocache' ), strtoupper( $strategy ), $handle ), array( 'qa' => $qa, 'rollback' => ! empty( $gate['rollback_verified'] ) ? 'verified' : 'pending', 'environment' => (string) $gate['environment'] ) );
		wp_send_json_success( $gate );
	}

	public function prune_gate_history_ajax(): void {
		check_ajax_referer( self::GATE_HISTORY_PRUNE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền dọn lịch sử evidence.', 'baocache' ) ), 403 );
		}
		$removed = BaoCache_Render_Blocking::prune_gate_history();
		BaoCache_Activity::log( 'rule_gate_history', 'success', __( 'Đã dọn lịch sử evidence quá hạn.', 'baocache' ), array( 'removed' => (string) $removed ) );
		wp_send_json_success( array( 'removed' => $removed, 'policy' => BaoCache_Render_Blocking::gate_history_policy() ) );
	}

	public function acknowledge_stale_gate_ajax(): void {
		check_ajax_referer( self::GATE_ACK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền xác nhận stale gate.', 'baocache' ) ), 403 );
		}
		$handle = sanitize_key( (string) wp_unslash( $_POST['handle'] ?? '' ) );
		$strategy = sanitize_key( (string) wp_unslash( $_POST['strategy'] ?? '' ) );
		if ( '' === $handle || ! in_array( $strategy, array( 'defer', 'async-css', 'delay' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule gate không hợp lệ.', 'baocache' ) ), 422 );
		}
		$gate = BaoCache_Render_Blocking::acknowledge_stale_gate( $handle, $strategy );
		if ( is_wp_error( $gate ) ) {
			wp_send_json_error( array( 'message' => $gate->get_error_message() ), 422 );
		}
		BaoCache_Activity::log( 'rule_gate_ack', 'warning', __( 'Đã đánh dấu stale gate là đã xem; production vẫn bị chặn.', 'baocache' ), array( 'handle' => $handle, 'strategy' => $strategy, 'evidence_ref' => (string) ( $gate['evidence_ref'] ?? '' ) ) );
		wp_send_json_success( $gate );
	}

	public function review_gate_evidence_ajax(): void {
		check_ajax_referer( self::GATE_REVIEW_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền rà soát evidence gate.', 'baocache' ) ), 403 );
		}
		$review = $this->run_gate_evidence_review();
		wp_send_json_success( $review );
	}

	public function run_gate_evidence_review(): array {
		$review = BaoCache_Render_Blocking::review_gate_evidence();
		$outcome = (int) ( $review['stale_count'] ?? 0 ) > 0 ? 'warning' : 'success';
		BaoCache_Activity::log( 'rule_gate_review', $outcome, sprintf( __( 'Evidence review: %1$d stale trên %2$d gate.', 'baocache' ), (int) ( $review['stale_count'] ?? 0 ), (int) ( $review['total'] ?? 0 ) ), array( 'stale' => (string) ( $review['stale_count'] ?? 0 ), 'total' => (string) ( $review['total'] ?? 0 ), 'environment' => (string) ( $review['environment'] ?? wp_get_environment_type() ) ) );
		return $review;
	}

	public function ensure_gate_review_schedule(): void {
		if ( ! wp_next_scheduled( self::GATE_REVIEW_TICK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::GATE_REVIEW_TICK );
		}
	}

	public function ensure_csp_canary_schedule(): void {
		$settings = BaoCache_Settings::get();
		$enabled = ! empty( $settings['csp_canary_enabled'] ) && ! empty( $settings['csp_enabled'] ) && 'enforce' === (string) $settings['csp_mode'];
		if ( ! $enabled ) {
			wp_clear_scheduled_hook( self::CSP_CANARY_TICK );
			return;
		}
		if ( ! wp_next_scheduled( self::CSP_CANARY_TICK ) ) {
			wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'daily', self::CSP_CANARY_TICK, array(), true );
		}
	}

	public function run_csp_canary(): void {
		$settings = BaoCache_Settings::get();
		if ( empty( $settings['csp_canary_enabled'] ) || empty( $settings['csp_enabled'] ) || 'enforce' !== (string) $settings['csp_mode'] || get_transient( 'baocache_csp_canary_lock' ) ) return;
		set_transient( 'baocache_csp_canary_lock', '1', 90 );
		$result = $this->collect_csp_post_probe( $settings );
		if ( is_wp_error( $result ) ) {
			BaoCache_Activity::log( 'csp_post_enforcement_probe', 'failed', __( 'CSP scheduled canary không đọc được public response.', 'baocache' ), array( 'source' => 'scheduled' ) );
			delete_transient( 'baocache_csp_canary_lock' );
			return;
		}
		BaoCache_CSP::record_post_enforcement_probe( $result, 'scheduled' );
		BaoCache_Activity::log( 'csp_post_enforcement_probe', 'pass' === (string) $result['outcome'] ? 'success' : ( 'warn' === (string) $result['outcome'] ? 'warning' : 'failed' ), __( 'Đã chạy CSP scheduled canary.', 'baocache' ), array( 'source' => 'scheduled', 'http_status' => (string) $result['status_code'], 'outcome' => (string) $result['outcome'] ) );
		delete_transient( 'baocache_csp_canary_lock' );
	}

	private function expected_public_link( array $settings, string $type ): bool {
		return 'feed' === $type ? ( empty( $settings['remove_feed_links'] ) && 'keep' === (string) $settings['rss_mode'] ) : true;
	}

	/** @return list<string> */
	private function header_values( mixed $headers, string $name ): array {
		$value = is_array( $headers ) || $headers instanceof ArrayAccess ? ( $headers[ $name ] ?? null ) : null;
		$values = is_array( $value ) ? $value : array( $value );
		return array_values( array_filter( array_map( static fn( mixed $item ): string => trim( (string) $item ), $values ), static fn( string $item ): bool => '' !== $item ) );
	}

	private function probe_public_url( string $url ): array|WP_Error {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $host || $host !== $site_host ) {
			return new WP_Error( 'baocache_probe_host', __( 'Probe chỉ hỗ trợ URL cùng domain.', 'baocache' ) );
		}
		$started = hrtime( true );
		$response = wp_remote_get( $url, array( 'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 524288, 'headers' => array( 'Cookie' => '', 'Cache-Control' => 'no-cache', 'User-Agent' => 'BaoCache-Public-Probe/' . BAOCACHE_VERSION ) ) );
		$elapsed = (int) round( ( hrtime( true ) - $started ) / 1000000 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array( 'status' => (int) wp_remote_retrieve_response_code( $response ), 'headers' => wp_remote_retrieve_headers( $response ), 'body' => wp_remote_retrieve_body( $response ), 'response_ms' => $elapsed );
	}

	public function inspect_headers(): void {
		check_ajax_referer( self::INSPECT_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền kiểm tra URL.', 'baocache' ) ), 403 );
		}
		$url = esc_url_raw( (string) wp_unslash( $_POST['url'] ?? '' ), array( 'http', 'https' ) );
		$target_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $url || '' === $target_host || $target_host !== $site_host ) {
			wp_send_json_error( array( 'message' => __( 'Chỉ kiểm tra URL cùng domain với website này.', 'baocache' ) ), 400 );
		}
		$started = hrtime( true );
		$response = wp_remote_head( $url, array( 'timeout' => 10, 'redirection' => 0, 'headers' => array( 'Cookie' => '' ) ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'Không thể kết nối URL này từ WordPress.', 'baocache' ) ), 502 );
		}
		$headers = wp_remote_retrieve_headers( $response );
		$header = static fn( string $name ): string => (string) ( $headers[ $name ] ?? '—' );
		$fastcgi = $header( 'x-fastcgi-cache' );
		$cloudflare = $header( 'cf-cache-status' );
		$compression = $header( 'content-encoding' );
		$csp_enforced_values = $this->header_values( $headers, 'content-security-policy' );
		$csp_report_only_values = $this->header_values( $headers, 'content-security-policy-report-only' );
		$csp_value = $csp_enforced_values[0] ?? ( $csp_report_only_values[0] ?? '' );
		$csp_mode = ! empty( $csp_enforced_values ) ? 'enforce' : ( ! empty( $csp_report_only_values ) ? 'report-only' : 'none' );
		$local_csp = BaoCache_CSP::build_policy( BaoCache_Settings::get() );
		$csp_matches_baocache = 1 === count( $csp_enforced_values ) + count( $csp_report_only_values ) && '' !== $csp_value && str_starts_with( $csp_value, $local_csp );
		$csp_conflict = ( ! empty( $csp_enforced_values ) && ! empty( $csp_report_only_values ) ) || count( $csp_enforced_values ) > 1 || count( $csp_report_only_values ) > 1 || ( '' !== $csp_value && ! $csp_matches_baocache );
		BaoCache_CSP::record_owner_observation( '' !== $csp_value, $csp_mode, $csp_matches_baocache, array( 'enforce_present' => ! empty( $csp_enforced_values ), 'report_present' => ! empty( $csp_report_only_values ), 'enforce_count' => count( $csp_enforced_values ), 'report_count' => count( $csp_report_only_values ) ) );
		$checks = array(
			array( 'label' => 'HTTP', 'value' => (string) wp_remote_retrieve_response_code( $response ), 'state' => wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 400 ? 'good' : 'bad' ),
			array( 'label' => 'FastCGI', 'value' => $fastcgi, 'state' => '—' === $fastcgi ? 'warn' : ( 'BYPASS' === strtoupper( $fastcgi ) ? 'warn' : 'good' ) ),
			array( 'label' => 'Cloudflare', 'value' => $cloudflare, 'state' => '—' === $cloudflare ? 'neutral' : ( 'DYNAMIC' === strtoupper( $cloudflare ) ? 'neutral' : 'good' ) ),
			array( 'label' => 'Cache-Control', 'value' => $header( 'cache-control' ), 'state' => '—' === $header( 'cache-control' ) ? 'neutral' : 'good' ),
			array( 'label' => 'Expires', 'value' => $header( 'expires' ), 'state' => 'neutral' ),
			array( 'label' => 'Compression', 'value' => $compression, 'state' => '—' === $compression ? 'neutral' : 'good' ),
			array( 'label' => 'Age', 'value' => $header( 'age' ), 'state' => 'neutral' ),
			array( 'label' => 'Vary', 'value' => $header( 'vary' ), 'state' => 'neutral' ),
			array( 'label' => 'X-Accel-Expires', 'value' => $header( 'x-accel-expires' ), 'state' => 'neutral' ),
			array( 'label' => 'ETag', 'value' => $header( 'etag' ), 'state' => 'neutral' ),
			array( 'label' => 'Last-Modified', 'value' => $header( 'last-modified' ), 'state' => 'neutral' ),
			array( 'label' => 'Content-Length', 'value' => $header( 'content-length' ), 'state' => 'neutral' ),
			array( 'label' => 'Server-Timing', 'value' => $header( 'server-timing' ), 'state' => 'neutral' ),
			array( 'label' => 'CF-Ray', 'value' => $header( 'cf-ray' ), 'state' => 'neutral' ),
			array( 'label' => 'CSP', 'value' => '' === $csp_value ? '—' : ( $csp_conflict ? 'Conflict · review owner' : ( $csp_matches_baocache ? 'BaoCache · ' . $csp_mode : 'External/unknown · ' . $csp_mode ) ), 'state' => '' === $csp_value ? 'neutral' : ( $csp_conflict ? 'warn' : ( $csp_matches_baocache ? 'good' : 'warn' ) ) ),
		);
		$status_code = wp_remote_retrieve_response_code( $response );
		$response_ms = round( ( hrtime( true ) - $started ) / 1000000, 1 );
		$outcome = $status_code < 200 || $status_code >= 400 ? 'FAIL' : ( '—' === $fastcgi || 'BYPASS' === strtoupper( $fastcgi ) ? 'WARN' : 'PASS' );
		BaoCache_Activity::log( 'header_check', 'FAIL' === $outcome ? 'failed' : ( 'WARN' === $outcome ? 'warning' : 'success' ), sprintf( __( 'Header check: %s · HTTP %d.', 'baocache' ), $outcome, $status_code ), array( 'path' => BaoCache_Activity::safe_path( $url ), 'fastcgi' => $fastcgi, 'cloudflare' => $cloudflare ) );
		wp_send_json_success( array(
			'status_code' => $status_code,
			'response_ms' => $response_ms,
			'outcome' => $outcome,
			'checks' => $checks,
			'recommendations' => $this->header_recommendations( $status_code, $fastcgi, $cloudflare, $compression, $response_ms ),
			'explanation' => 'DYNAMIC' === strtoupper( $cloudflare ) ? __( 'Cloudflare đang proxy nhưng không edge-cache HTML cho response này; FastCGI Nginx vẫn là page cache chính.', 'baocache' ) : ( '—' === $cloudflare ? __( 'Không phát hiện header Cloudflare trên response này.', 'baocache' ) : __( 'Đây là trạng thái Cloudflare của một response, không phải dữ liệu lịch sử.', 'baocache' ) ),
		) );
	}

	/**
	 * Public HTML evidence for Analytics. This never executes remote scripts,
	 * stores no response body and returns only bounded public tag identifiers.
	 */
	public function analytics_public_evidence_ajax(): void {
		check_ajax_referer( self::ANALYTICS_EVIDENCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bạn không có quyền kiểm tra Analytics.', 'baocache' ) ), 403 );
		}
		$probe = $this->probe_public_url( home_url( '/' ) );
		if ( is_wp_error( $probe ) ) {
			wp_send_json_error( array( 'message' => __( 'Không thể đọc frontend công khai từ WordPress.', 'baocache' ) ), 502 );
		}
		$settings = BaoCache_Settings::get();
		$status = BaoCache_Analytics::status( $settings );
		$body = (string) ( $probe['body'] ?? '' );
		$headers = $probe['headers'] ?? array();
		$report_only = (string) wp_remote_retrieve_header( $headers, 'content-security-policy-report-only' );
		$enforced = (string) wp_remote_retrieve_header( $headers, 'content-security-policy' );
		$policy = '' !== $enforced ? $enforced : $report_only;
		$policy_mode = '' !== $enforced ? 'enforced' : ( '' !== $report_only ? 'report-only' : 'none' );
		preg_match_all( '/\b(?:GTM-[A-Z0-9]{4,}|G-[A-Z0-9]{6,})\b/i', $body, $matches );
		$ids = array_values( array_unique( array_map( 'strtoupper', array_slice( (array) ( $matches[0] ?? array() ), 0, 24 ) ) ) );
		$configured_id = strtoupper( (string) ( $status['id'] ?? '' ) );
		$unexpected_ids = array_values( array_filter( $ids, static fn( string $id ): bool => '' !== $configured_id && $id !== $configured_id ) );
		$unexpected_containers = array_values( array_filter( $unexpected_ids, static fn( string $id ): bool => str_starts_with( $id, 'GTM-' ) ) );
		$unexpected_measurements = array_values( array_filter( $unexpected_ids, static fn( string $id ): bool => str_starts_with( $id, 'G-' ) ) );
		$injectors = $this->public_tag_injectors( $body, $status, $ids, $unexpected_ids );
		$csp_script = $this->csp_directive( $policy, 'script-src-elem' );
		if ( '' === $csp_script ) {
			$csp_script = $this->csp_directive( $policy, 'script-src' );
		}
		$csp_connect = $this->csp_directive( $policy, 'connect-src' );
		$csp_frame = $this->csp_directive( $policy, 'frame-src' );
		$script_allows_google = '' === $policy ? null : str_contains( $csp_script, 'www.googletagmanager.com' );
		$connect_allows_google = '' === $policy ? null : ( str_contains( $csp_connect, 'analytics.google.com' ) || str_contains( $csp_connect, 'google-analytics.com' ) || str_contains( $csp_connect, 'www.googletagmanager.com' ) );
		$frame_allows_gtm = '' === $policy ? null : str_contains( $csp_frame, 'www.googletagmanager.com' );
		BaoCache_Activity::log( 'analytics_evidence', ( ! empty( $status['enabled'] ) && false !== $script_allows_google ) ? 'success' : 'warning', __( 'Đã kiểm tra Analytics public evidence.', 'baocache' ), array( 'bootstrap' => false !== strpos( $body, 'baocache-analytics-bootstrap.js' ) ? 'found' : 'missing', 'config' => false !== strpos( $body, 'baocache-analytics-config' ) ? 'found' : 'missing', 'csp' => $policy_mode, 'unexpected_tags' => (string) count( $unexpected_ids ), 'injectors' => (string) count( $injectors ) ) );
		wp_send_json_success( array(
			'http_status' => (int) ( $probe['status'] ?? 0 ),
			'response_ms' => (int) ( $probe['response_ms'] ?? 0 ),
			'bootstrap_found' => false !== strpos( $body, 'baocache-analytics-bootstrap.js' ),
			'config_found' => false !== strpos( $body, 'baocache-analytics-config' ),
			'configured_id_found' => '' !== $configured_id && in_array( $configured_id, $ids, true ),
			'events_listener_found' => ! empty( $status['auto_events'] ) ? false !== strpos( $body, 'baocache-analytics-events.js' ) : null,
			'adapters_listener_found' => ! empty( $status['enabled_adapters'] ) ? false !== strpos( $body, 'baocache-analytics-adapters.js' ) : null,
			'adapter_count' => count( (array) $status['enabled_adapters'] ),
			'policy_mode' => $policy_mode,
			'csp_script_allows_google' => $script_allows_google,
			'csp_connect_allows_google' => $connect_allows_google,
			'csp_frame_allows_gtm' => 'gtm' === (string) ( $status['type'] ?? '' ) ? $frame_allows_gtm : null,
			'unexpected_ids' => array_slice( $unexpected_ids, 0, 8 ),
			'unexpected_containers' => array_slice( $unexpected_containers, 0, 8 ),
			'unexpected_measurements' => array_slice( $unexpected_measurements, 0, 8 ),
			'injectors' => array_slice( $injectors, 0, 12 ),
		) );
	}

	/**
	 * Classify observable public tag injectors without persisting HTML, scripts,
	 * full URLs or ownership claims. A heuristic is always presented as a
	 * candidate; it never disables a plugin, theme snippet or Cloudflare feature.
	 *
	 * @param array<string, mixed> $status Analytics status.
	 * @param string[]             $ids Public Google IDs found in this response.
	 * @param string[]             $unexpected_ids IDs not configured by BaoCache.
	 * @return array<int, array<string, string>>
	 */
	private function public_tag_injectors( string $body, array $status, array $ids, array $unexpected_ids ): array {
		$items = array();
		$add = static function ( string $source, string $owner, string $evidence, string $risk, string $recommendation, string $state, int $confidence ) use ( &$items ): void {
			$key = sanitize_key( $source . '-' . $evidence );
			if ( '' === $key || isset( $items[ $key ] ) ) {
				return;
			}
			$items[ $key ] = array(
				'source' => $source,
				'owner' => $owner,
				'evidence' => $evidence,
				'risk' => in_array( $risk, array( 'info', 'low', 'medium', 'high', 'critical' ), true ) ? $risk : 'info',
				'recommendation' => $recommendation,
				'state' => in_array( $state, array( 'healthy', 'detected', 'potential-duplicate', 'unknown' ), true ) ? $state : 'unknown',
				'confidence' => min( 100, max( 0, $confidence ) ),
			);
		};

		$configured_id = strtoupper( (string) ( $status['id'] ?? '' ) );
		if ( false !== strpos( $body, 'baocache-analytics-bootstrap.js' ) || false !== strpos( $body, 'baocache-analytics-config' ) ) {
			$add( 'BaoCache Analytics', 'BaoCache', __( 'Local bootstrap/config marker found.', 'baocache' ), 'info', __( 'Managed by BaoCache; keep one canonical Google ID.', 'baocache' ), 'healthy', 100 );
		}

		$lower = strtolower( $body );
		$has_google_tag = ! empty( $ids ) || false !== strpos( $lower, 'googletagmanager.com' ) || false !== strpos( $lower, 'gtag(' );
		if ( $has_google_tag && ( false !== strpos( $lower, 'google_tags_first_party' ) || false !== strpos( $lower, 'google tag gateway' ) ) ) {
			$path = $this->first_party_tag_path( $body );
			$add( 'Google Tag Gateway', 'Cloudflare candidate', '' !== $path ? sprintf( __( 'First-party Google tag marker · path %s', 'baocache' ), $path ) : __( 'First-party Google tag marker found.', 'baocache' ), 'high', __( 'Review Cloudflare Google Tag Gateway; BaoCache will not change it.', 'baocache' ), 'detected', '' !== $path ? 92 : 78 );
		}
		if ( $has_google_tag && ( false !== strpos( $lower, 'rank-math') || false !== strpos( $lower, 'rankmath') ) ) {
			$add( 'Rank Math marker', 'Plugin candidate', __( 'Rank Math public marker and Google tag evidence found in the same response.', 'baocache' ), 'medium', __( 'Review the Rank Math analytics/injector setting before disabling any duplicate tag.', 'baocache' ), 'detected', 72 );
		}
		if ( $has_google_tag && ( false !== strpos( $lower, 'googlesitekit') || false !== strpos( $lower, 'google site kit') || false !== strpos( $lower, 'site-kit-by-google') ) ) {
			$add( 'Site Kit marker', 'Plugin candidate', __( 'Google Site Kit public marker and Google tag evidence found in the same response.', 'baocache' ), 'medium', __( 'Review Site Kit connection/tag settings; keep a single page-view owner.', 'baocache' ), 'detected', 72 );
		}
		if ( $has_google_tag && ( false !== strpos( $lower, 'gtm4wp') || false !== strpos( $lower, 'duracelltomi-google-tag-manager') || false !== strpos( $lower, 'google-tag-manager-for-wordpress') ) ) {
			$add( 'GTM plugin marker', 'Plugin candidate', __( 'Known GTM plugin marker and Google tag evidence found in the same response.', 'baocache' ), 'medium', __( 'Review the plugin injection setting before changing a duplicate tag.', 'baocache' ), 'detected', 76 );
		}
		foreach ( array_slice( $unexpected_ids, 0, 8 ) as $id ) {
			$add( 'Unknown Google tag', __( 'Theme / wp_head / snippet unknown', 'baocache' ), sprintf( __( 'Public HTML contains external ID %s.', 'baocache' ), $id ), 'critical', __( 'Identify the injector manually before removing it; this may be a theme, snippet or external integration.', 'baocache' ), 'potential-duplicate', 18 );
		}
		if ( $has_google_tag && empty( $unexpected_ids ) && '' !== $configured_id && false !== strpos( $lower, 'gtag(' ) && false === strpos( $body, 'baocache-analytics-bootstrap.js' ) ) {
			$add( 'Unknown inline Google tag', __( 'Theme / wp_head / snippet unknown', 'baocache' ), __( 'Inline gtag() marker found without BaoCache bootstrap evidence.', 'baocache' ), 'high', __( 'Review theme header, wp_head snippets and tag plugins manually.', 'baocache' ), 'unknown', 28 );
		}
		return array_values( $items );
	}

	/** Return only a bounded same-site path hint for an observed first-party tag. */
	private function first_party_tag_path( string $body ): string {
		if ( ! preg_match_all( '/<script\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $body, $matches ) ) {
			return '';
		}
		$home_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		foreach ( array_slice( (array) ( $matches[1] ?? array() ), 0, 20 ) as $src ) {
			$src = html_entity_decode( (string) $src, ENT_QUOTES, 'UTF-8' );
			$parts = wp_parse_url( $src );
			$host = (string) ( $parts['host'] ?? '' );
			$path = (string) ( $parts['path'] ?? '' );
			if ( '' === $path || ( '' !== $host && strtolower( $host ) !== strtolower( $home_host ) ) ) {
				continue;
			}
			return '/' . ltrim( sanitize_text_field( $path ), '/' );
		}
		return '';
	}

	private function csp_directive( string $policy, string $directive ): string {
		if ( '' === $policy ) {
			return '';
		}
		$pattern = '/(?:^|;)\s*' . preg_quote( $directive, '/' ) . '\s+([^;]*)/i';
		return preg_match( $pattern, $policy, $matches ) ? strtolower( trim( (string) $matches[1] ) ) : '';
	}

	/** Recommendations are tied to one inspected response, never a synthetic performance score. */
	private function header_recommendations( int $status, string $fastcgi, string $cloudflare, string $compression, float $response_ms ): array {
		$items = array();
		if ( $status < 200 || 400 <= $status ) {
			$items[] = array( 'state' => 'bad', 'label' => __( 'Critical', 'baocache' ), 'title' => __( 'URL did not return a successful response', 'baocache' ), 'detail' => __( 'Resolve the HTTP error before tuning cache or frontend assets.', 'baocache' ), 'tab' => 'cache' );
			return $items;
		}
		if ( '—' === $fastcgi ) {
			$items[] = array( 'state' => 'warn', 'label' => __( 'Review', 'baocache' ), 'title' => __( 'FastCGI status header is missing', 'baocache' ), 'detail' => __( 'Verify the Nginx observer/header configuration before relying on cache diagnostics.', 'baocache' ), 'tab' => 'cache' );
		} elseif ( 'BYPASS' === strtoupper( $fastcgi ) ) {
			$items[] = array( 'state' => 'warn', 'label' => __( 'Review', 'baocache' ), 'title' => __( 'FastCGI bypassed this response', 'baocache' ), 'detail' => __( 'A bypass can be intentional for cookies, admin paths or query strings. Compare the same clean URL as a logged-out visitor.', 'baocache' ), 'tab' => 'cache' );
		} elseif ( 'MISS' === strtoupper( $fastcgi ) ) {
			$items[] = array( 'state' => 'neutral', 'label' => __( 'Info', 'baocache' ), 'title' => __( 'FastCGI cache is warming', 'baocache' ), 'detail' => __( 'A MISS is normal after a purge or expiry. Run the same check again to verify a subsequent HIT.', 'baocache' ), 'tab' => 'dashboard' );
		}
		if ( 'DYNAMIC' === strtoupper( $cloudflare ) ) {
			$items[] = array( 'state' => 'neutral', 'label' => __( 'Info', 'baocache' ), 'title' => __( 'Cloudflare is not edge-caching this HTML', 'baocache' ), 'detail' => __( 'FastCGI remains the page-cache layer. Configure Cloudflare caching only as a separate, explicit decision.', 'baocache' ), 'tab' => 'cache' );
		}
		if ( '—' === $compression ) {
			$items[] = array( 'state' => 'warn', 'label' => __( 'Review', 'baocache' ), 'title' => __( 'No content compression was observed', 'baocache' ), 'detail' => __( 'Check Nginx and Cloudflare compression for text responses; this is not relevant for already compressed images.', 'baocache' ), 'tab' => 'cache' );
		}
		if ( 1000 <= $response_ms ) {
			$items[] = array( 'state' => 'warn', 'label' => __( 'Review', 'baocache' ), 'title' => __( 'Slow inspected response', 'baocache' ), 'detail' => sprintf( __( 'This request took %s ms from WordPress. Compare a FastCGI HIT and inspect PHP/database work before changing frontend settings.', 'baocache' ), $response_ms ), 'tab' => 'dashboard' );
		}
		return empty( $items ) ? array( array( 'state' => 'good', 'label' => __( 'Pass', 'baocache' ), 'title' => __( 'No response-level issue detected', 'baocache' ), 'detail' => __( 'This is one request only. Continue monitoring runtime history rather than treating it as a PageSpeed score.', 'baocache' ), 'tab' => 'dashboard' ) ) : array_slice( $items, 0, 4 );
	}

	public function preview_asset_rule(): void {
		check_ajax_referer( self::PREVIEW_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Bạn không có quyền xem trước rule.', 'baocache' ) ), 403 );
		$handle = sanitize_key( (string) wp_unslash( $_POST['handle'] ?? '' ) );
		$type = 'style' === (string) wp_unslash( $_POST['type'] ?? '' ) ? 'style' : 'script';
		$scope = sanitize_key( (string) wp_unslash( $_POST['scope'] ?? 'everywhere' ) );
		$value = sanitize_text_field( (string) wp_unslash( $_POST['value'] ?? '' ) );
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		$target = null; $dependents = array();
		foreach ( $assets as $asset ) {
			if ( $type === ( $asset['type'] ?? '' ) && $handle === ( $asset['handle'] ?? '' ) ) $target = $asset;
			if ( $type === ( $asset['type'] ?? '' ) && in_array( $handle, (array) ( $asset['dependencies'] ?? array() ), true ) ) $dependents[] = (string) $asset['handle'];
		}
		$path = (string) ( $target['path'] ?? '' );
		$content_scope = in_array( $scope, array( 'has-shortcode', 'missing-shortcode', 'has-block', 'missing-block' ), true );
		$context = $content_scope ? null : ( 'everywhere' === $scope || ( 'url-prefix' === $scope && '' !== $value && 0 === strpos( $path, '/' . ltrim( $value, '/' ) ) ) || ( 'front-page' === $scope && '/' === $path ) );
		wp_send_json_success( array( 'found' => null !== $target, 'dependencies' => $target['dependencies'] ?? array(), 'dependents' => $dependents, 'path' => $path, 'context' => $context, 'scope' => $scope ) );
	}

	public function site_health_tests( array $tests ): array {
		$tests['direct']['baocache_redis_runtime'] = array(
			'label' => __( 'BaoCache Redis runtime', 'baocache' ),
			'test' => array( $this, 'site_health_redis_runtime' ),
		);
		return $tests;
	}

	public function site_health_redis_runtime(): array {
		$redis = BaoCache_Diagnostics::redis_metrics();
		if ( $redis['connected'] ) {
			return array( 'label' => __( 'BaoCache đã xác minh Redis runtime.', 'baocache' ), 'status' => 'good', 'badge' => array( 'label' => 'BaoCache', 'color' => 'blue' ), 'description' => '<p>' . esc_html__( 'PhpRedis kết nối được Redis bằng cấu hình WordPress hiện tại.', 'baocache' ) . '</p>', 'actions' => '', 'test' => 'baocache_redis_runtime' );
		}
		return array( 'label' => __( 'BaoCache không xác minh được Redis runtime.', 'baocache' ), 'status' => 'critical', 'badge' => array( 'label' => 'BaoCache', 'color' => 'red' ), 'description' => '<p>' . esc_html__( 'Kiểm tra PhpRedis, Redis service và Docker secret.', 'baocache' ) . '</p>', 'actions' => '', 'test' => 'baocache_redis_runtime' );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = BaoCache_Settings::get();
		$status = $this->status();
		$overview = $this->overview( $settings, $status );
		$health = $this->health_checks( $status );
		$diagnostics = $this->site_diagnostics();
		$purge_evidence = BaoCache_Purge::evidence();
		$purge_remediation = BaoCache_Purge::remediation( $purge_evidence );
		$cloudflare_configuration = BaoCache_Cloudflare::configuration();
		$fastcgi_diagnostics = BaoCache_Diagnostics::fastcgi_metrics();
		$activity = BaoCache_Activity::recent();
		$metric_windows = array( BaoCache_Metrics::window( 24 ), BaoCache_Metrics::window( 7 * 24 ), BaoCache_Metrics::window( 30 * 24 ) );
		$analytics_type = ! empty( $settings['analytics_enabled'] ) ? BaoCache_Settings::tracking_id_type( (string) $settings['analytics_id'] ) : '';
		?>
		<div class="wrap baocache">
			<?php $this->notices(); ?>
			<div class="baocache-layout">
				<aside class="baocache-sidebar">
					<div class="baocache-sidebar__brand"><span class="dashicons dashicons-performance"></span><strong>BaoCache</strong></div>
					<p><?php esc_html_e( 'PERFORMANCE', 'baocache' ); ?></p>
					<nav aria-label="<?php esc_attr_e( 'Điều hướng BaoCache', 'baocache' ); ?>">
						<button type="button" class="is-active" data-baocache-tab="dashboard"><span class="dashicons dashicons-dashboard"></span><?php esc_html_e( 'Tổng quan', 'baocache' ); ?></button>
						<button type="button" data-baocache-tab="cache"><span class="dashicons dashicons-database"></span><?php esc_html_e( 'Cache', 'baocache' ); ?></button>
						<button type="button" data-baocache-tab="assets"><span class="dashicons dashicons-media-code"></span><?php esc_html_e( 'Assets', 'baocache' ); ?></button>
						<button type="button" data-baocache-tab="resources"><span class="dashicons dashicons-admin-links"></span><?php esc_html_e( 'Resource Hints', 'baocache' ); ?></button>
					</nav>
					<p><?php esc_html_e( 'WORDPRESS', 'baocache' ); ?></p>
					<nav aria-label="<?php esc_attr_e( 'WordPress', 'baocache' ); ?>"><button type="button" data-baocache-tab="wordpress"><span class="dashicons dashicons-admin-generic"></span><?php esc_html_e( 'WordPress', 'baocache' ); ?></button><button type="button" data-baocache-tab="settings"><span class="dashicons dashicons-admin-settings"></span><?php esc_html_e( 'Settings', 'baocache' ); ?></button></nav>
					<p><?php esc_html_e( 'SECURITY', 'baocache' ); ?></p>
					<nav aria-label="<?php esc_attr_e( 'Security', 'baocache' ); ?>"><button type="button" data-baocache-tab="security"><span class="dashicons dashicons-shield-alt"></span><?php esc_html_e( 'Security', 'baocache' ); ?></button></nav>
					<p><?php esc_html_e( 'OPERATIONS', 'baocache' ); ?></p>
					<nav aria-label="<?php esc_attr_e( 'Vận hành BaoCache', 'baocache' ); ?>"><button type="button" data-baocache-tab="warmup"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Warmup', 'baocache' ); ?></button><button type="button" data-baocache-tab="diagnostics"><span class="dashicons dashicons-search"></span><?php esc_html_e( 'Diagnostics', 'baocache' ); ?></button><button type="button" data-baocache-tab="logs"><span class="dashicons dashicons-list-view"></span><?php esc_html_e( 'Logs', 'baocache' ); ?></button></nav>
					<p><?php esc_html_e( 'INTEGRATIONS', 'baocache' ); ?></p>
					<nav aria-label="<?php esc_attr_e( 'Tích hợp BaoCache', 'baocache' ); ?>"><button type="button" data-baocache-tab="cloudflare"><span class="dashicons dashicons-cloud"></span><?php esc_html_e( 'Cloudflare', 'baocache' ); ?></button><button type="button" data-baocache-tab="analytics"><span class="dashicons dashicons-chart-line"></span><?php esc_html_e( 'Analytics', 'baocache' ); ?></button></nav>
					<div class="baocache-sidebar__foot"><small><?php echo esc_html( 'v' . BAOCACHE_VERSION . ' · Nguyễn Hoàng Thái Bảo' ); ?></small></div>
				</aside>
				<main class="baocache-main">
				<header class="baocache-hero">
					<div><h1><?php esc_html_e( 'BaoCache', 'baocache' ); ?></h1><p><?php esc_html_e( 'Trung tâm điều khiển hiệu năng WordPress cho Nginx FastCGI Cache, Redis và Docker.', 'baocache' ); ?></p></div>
					<div class="baocache-hero__meta"><div class="baocache-hero__services"><span class="baocache-badge is-<?php echo esc_attr( (string) ( $status[0]['state'] ?? 'neutral' ) ); ?>">Nginx</span><span class="baocache-badge is-<?php echo esc_attr( (string) ( $status[1]['state'] ?? 'neutral' ) ); ?>">Redis</span><span class="baocache-badge is-<?php echo ! empty( $cloudflare_configuration['configured'] ) ? 'good' : 'neutral'; ?>">Cloudflare</span><span class="baocache-badge is-<?php echo '' !== $analytics_type ? 'good' : 'neutral'; ?>">Analytics</span></div><span class="baocache-badge is-neutral"><?php echo esc_html( ucfirst( wp_get_environment_type() ) ); ?></span><span class="baocache-version">v<?php echo esc_html( BAOCACHE_VERSION ); ?></span></div>
				</header>

			<section class="baocache-dashboard-shell">
				<section class="baocache-dashboard-grid" data-baocache-pane="dashboard">
					<article class="baocache-panel baocache-health"><div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'System Health', 'baocache' ); ?></h2><p><?php echo esc_html( $overview['runtime'] ); ?></p></div></div><ul><?php foreach ( $health as $check ) : ?><li><span class="baocache-badge is-<?php echo esc_attr( $check['state'] ); ?>"><?php echo esc_html( $check['status'] ); ?></span><strong><?php echo esc_html( $check['label'] ); ?></strong><span><?php echo esc_html( $check['value'] ); ?></span></li><?php endforeach; ?></ul></article>
					<article class="baocache-panel baocache-quick-actions"><div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Quick Actions', 'baocache' ); ?></h2><p><?php esc_html_e( 'Nhóm theo mục đích thay vì ma trận nút.', 'baocache' ); ?></p></div></div><div class="baocache-action-groups"><section><strong><?php esc_html_e( 'Diagnostics', 'baocache' ); ?></strong><button type="button" class="button button-primary" data-baocache-diagnostics-shortcut><?php esc_html_e( 'Run Diagnostics', 'baocache' ); ?></button><button type="button" class="button button-secondary" data-baocache-inspect-shortcut><?php esc_html_e( 'Check Cache', 'baocache' ); ?></button></section><section><strong><?php esc_html_e( 'Optimization', 'baocache' ); ?></strong><button type="button" class="button button-secondary" data-baocache-scan-shortcut><?php esc_html_e( 'Scan Assets', 'baocache' ); ?></button><button type="button" class="button button-secondary" data-baocache-warm-shortcut><?php esc_html_e( 'Warm Queue', 'baocache' ); ?></button></section><section><strong><?php esc_html_e( 'Export', 'baocache' ); ?></strong><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION ), self::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Download JSON', 'baocache' ); ?></a></section></div></article>
				</section>
				<section class="baocache-panel baocache-site-diagnostics"><div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Site Diagnostics', 'baocache' ); ?></h2><p><?php esc_html_e( 'Kiểm tra tại thời điểm mở dashboard; không phải dữ liệu lịch sử.', 'baocache' ); ?></p></div><small><?php echo esc_html( sprintf( __( 'Kiểm tra %s', 'baocache' ), current_time( 'H:i' ) ) ); ?></small></div><div class="baocache-site-diagnostics__grid"><?php foreach ( $diagnostics as $diagnostic ) : ?><div><span class="baocache-badge is-<?php echo esc_attr( $diagnostic['state'] ); ?>"><?php echo esc_html( $diagnostic['status'] ); ?></span><strong><?php echo esc_html( $diagnostic['label'] ); ?></strong><small><?php echo esc_html( $diagnostic['detail'] ); ?></small></div><?php endforeach; ?></div></section>
				<?php $this->compatibility_qa_panel(); ?>

				<section class="baocache-panel baocache-runtime-history"><div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Runtime History', 'baocache' ); ?></h2><p><?php esc_html_e( 'Chỉ vẽ xu hướng từ snapshot thực; mỗi khoảng cần coverage đủ trước khi hiển thị.', 'baocache' ); ?></p></div></div><div class="baocache-runtime-history__grid"><?php foreach ( $metric_windows as $window ) : ?><?php $this->runtime_history_window( $window ); ?><?php endforeach; ?></div></section>

				<section class="baocache-panel baocache-activity"><div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Hoạt động gần đây', 'baocache' ); ?></h2><p><?php esc_html_e( 'Nhật ký quản trị bền vững; không lưu token, secret hoặc query string.', 'baocache' ); ?></p></div></div><?php if ( empty( $activity ) ) : ?><p class="baocache-activity__empty"><?php esc_html_e( 'Chưa có thao tác nào được ghi nhận.', 'baocache' ); ?></p><?php else : ?><ol><?php foreach ( $activity as $item ) : ?><li><span class="baocache-badge is-<?php echo esc_attr( 'success' === ( $item['outcome'] ?? '' ) ? 'good' : ( 'failed' === ( $item['outcome'] ?? '' ) ? 'bad' : 'warn' ) ); ?>"><?php echo esc_html( $this->activity_label( (string) ( $item['action'] ?? '' ) ) ); ?></span><strong><?php echo esc_html( (string) ( $item['detail'] ?? '' ) ); ?></strong><small><?php echo esc_html( sprintf( __( '%1$s · %2$s', 'baocache' ), human_time_diff( (int) ( $item['at'] ?? time() ), time() ) . ' ' . __( 'trước', 'baocache' ), (string) ( $item['actor'] ?? __( 'System', 'baocache' ) ) ) ); ?></small></li><?php endforeach; ?></ol><?php endif; ?></section>

				<section class="baocache-panel baocache-recommendations"><div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Khuyến nghị', 'baocache' ); ?></h2><p><?php esc_html_e( 'Dữ liệu cấu hình; không phải điểm PageSpeed hay Core Web Vitals.', 'baocache' ); ?></p></div><span class="baocache-module-progress"><?php echo esc_html( sprintf( __( '%d/3 module đang bật', 'baocache' ), $overview['modules_enabled'] ) ); ?></span></div><div class="baocache-recommendations__list"><?php foreach ( $overview['recommendations'] as $recommendation ) : ?><div><span class="baocache-priority is-<?php echo esc_attr( $recommendation['priority'] ); ?>"><?php echo esc_html( $recommendation['priority_label'] ); ?></span><strong><?php echo esc_html( $recommendation['title'] ); ?></strong><span><?php echo esc_html( $recommendation['detail'] ); ?></span><button type="button" class="button-link" data-baocache-go="<?php echo esc_attr( $recommendation['tab'] ); ?>"><?php echo esc_html( $recommendation['action'] ); ?></button></div><?php endforeach; ?></div></section>

			<details class="baocache-technical">
				<summary><span><?php esc_html_e( 'Báo cáo kỹ thuật', 'baocache' ); ?></span><small><?php esc_html_e( 'Dữ liệu thực từ WordPress/PHP, hữu ích khi hỗ trợ kỹ thuật', 'baocache' ); ?></small></summary>
				<div class="baocache-technical__grid">
					<?php foreach ( $this->technical_report() as $item ) : ?><div><span><?php echo esc_html( $item['label'] ); ?></span><strong><?php echo esc_html( $item['value'] ); ?></strong></div><?php endforeach; ?>
				</div>
			</details>

			<section class="baocache-panel baocache-inspector">
				<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Header Inspector', 'baocache' ); ?></h2><p><?php esc_html_e( 'Kiểm tra một URL cùng domain ở chế độ không đăng nhập. Đây là kết quả của một request, không phải hit ratio 24 giờ.', 'baocache' ); ?></p></div><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION ), self::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Xuất báo cáo JSON', 'baocache' ); ?></a></div>
				<div class="baocache-inspector__form"><input type="url" data-baocache-inspect-url value="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'URL cần kiểm tra', 'baocache' ); ?>"><button type="button" class="button button-primary" data-baocache-inspect><?php esc_html_e( 'Kiểm tra header', 'baocache' ); ?></button></div>
				<div class="baocache-inspector__result" data-baocache-inspect-result hidden></div>
			</section>

			<?php $this->database_health_panel(); ?>

			<section class="baocache-panel baocache-bypass-diagnostics">
				<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'FastCGI BYPASS diagnostics', 'baocache' ); ?></h2><p><?php esc_html_e( 'Tổng hợp 24 giờ từ Nginx observer. Đây là lý do cache bị bỏ qua theo nhóm, không lưu URL, cookie, query string hoặc dữ liệu khách.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( ! empty( $fastcgi_diagnostics['available'] ) ? 'good' : 'neutral' ); ?>"><?php echo esc_html( ! empty( $fastcgi_diagnostics['available'] ) ? __( 'Observed', 'baocache' ) : __( 'Waiting', 'baocache' ) ); ?></span></div>
				<?php if ( empty( $fastcgi_diagnostics['available'] ) ) : ?>
					<p class="baocache-bypass-diagnostics__empty"><?php esc_html_e( 'Nginx observer chưa có dữ liệu. Sau redeploy, chờ request công khai đầu tiên rồi mở lại dashboard.', 'baocache' ); ?></p>
				<?php elseif ( empty( $fastcgi_diagnostics['bypass_reasons'] ) ) : ?>
					<p class="baocache-bypass-diagnostics__empty"><?php esc_html_e( 'Chưa có BYPASS được phân loại trong cửa sổ 24 giờ. Các request HIT/MISS không được xem là BYPASS.', 'baocache' ); ?></p>
				<?php else : ?>
					<dl class="baocache-bypass-diagnostics__grid"><?php foreach ( (array) $fastcgi_diagnostics['bypass_reasons'] as $reason => $count ) : ?><div><dt><?php echo esc_html( $this->bypass_reason_label( (string) $reason ) ); ?></dt><dd><?php echo esc_html( sprintf( _n( '%d request', '%d requests', (int) $count, 'baocache' ), (int) $count ) ); ?></dd></div><?php endforeach; ?></dl>
				<?php endif; ?>
			</section>

			<section class="baocache-panel baocache-cloudflare-audit">
				<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Cloudflare read-only audit', 'baocache' ); ?></h2><p><?php esc_html_e( 'Chỉ xác minh API token và đọc trạng thái Zone. BaoCache không purge, thay đổi Cache Rules, DNS, WAF, SSL, Workers hay APO.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( $cloudflare_configuration['configured'] ? 'good' : 'neutral' ); ?>"><?php echo esc_html( $cloudflare_configuration['configured'] ? __( 'Ready', 'baocache' ) : __( 'Opt-in', 'baocache' ) ); ?></span></div>
				<?php if ( $cloudflare_configuration['configured'] ) : ?>
					<div class="baocache-cloudflare-audit__action"><div><strong><?php esc_html_e( 'Sẵn sàng kiểm tra', 'baocache' ); ?></strong><small><?php esc_html_e( 'Token chỉ được đọc từ Coolify environment tại thời điểm kiểm tra; không xuất ra JSON hoặc log.', 'baocache' ); ?></small></div><button type="button" class="button button-secondary" data-baocache-cloudflare-audit><?php esc_html_e( 'Chạy audit', 'baocache' ); ?></button></div>
				<?php else : ?>
					<div class="baocache-callout"><strong><?php esc_html_e( 'Chưa cấu hình trong Coolify', 'baocache' ); ?></strong><span><?php esc_html_e( 'Tạo Coolify Secret cho API token, thêm Zone ID và bật audit. Không có trường token trong wp-admin.', 'baocache' ); ?></span><small><?php echo esc_html( implode( ' · ', (array) $cloudflare_configuration['missing'] ) ); ?></small></div>
				<?php endif; ?>
				<div class="baocache-cloudflare-audit__result" data-baocache-cloudflare-audit-result hidden></div>
			</section>
			</section>

			<form method="post" action="options.php" class="baocache-form">
				<?php settings_fields( 'baocache_settings' ); ?>
				<section class="baocache-grid">
					<article class="baocache-panel baocache-panel--wide baocache-settings-advanced" data-baocache-pane="settings">
						<div class="baocache-panel__heading"><div><span class="baocache-eyebrow"><?php esc_html_e( 'Settings → Advanced', 'baocache' ); ?></span><h2><?php esc_html_e( 'Data Retention', 'baocache' ); ?></h2><p><?php esc_html_e( 'Kiểm soát dữ liệu được giữ lại khi gỡ BaoCache. Runtime luôn được dọn.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php esc_html_e( 'Default: Keep', 'baocache' ); ?></span></div>
						<div class="baocache-retention-options">
							<label><input type="checkbox" data-baocache-retention-keep name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[uninstall_keep_configuration]" value="1" <?php checked( ! empty( $settings['uninstall_keep_configuration'] ) ); ?>><span><strong><?php esc_html_e( 'Keep configuration after uninstall', 'baocache' ); ?></strong><small><?php esc_html_e( 'Giữ Asset Rules, Resource Hints, Analytics, Cloudflare audit config, Delay JS, Security và Site Overrides để có thể khôi phục khi cài lại.', 'baocache' ); ?></small></span></label>
							<label><input type="checkbox" data-baocache-retention-history name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[uninstall_keep_diagnostics]" value="1" <?php checked( ! empty( $settings['uninstall_keep_diagnostics'] ) ); ?>><span><strong><?php esc_html_e( 'Keep diagnostics history', 'baocache' ); ?></strong><small><?php esc_html_e( 'Giữ Runtime metrics, logs, probe history, QA checklist và diagnostics baseline.', 'baocache' ); ?></small></span></label>
							<label class="is-destructive"><input type="checkbox" data-baocache-retention-remove name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[uninstall_remove_everything]" value="1" <?php checked( ! empty( $settings['uninstall_remove_everything'] ) ); ?>><span><strong><?php esc_html_e( 'Remove everything on uninstall', 'baocache' ); ?></strong><small><?php esc_html_e( 'Xóa toàn bộ option và history đã đăng ký thuộc BaoCache.', 'baocache' ); ?></small></span></label>
						</div>
						<div class="baocache-callout is-warning<?php echo empty( $settings['uninstall_remove_everything'] ) ? ' is-hidden' : ''; ?>" data-baocache-retention-warning role="alert"><strong><?php esc_html_e( 'Không thể hoàn tác', 'baocache' ); ?></strong><span><?php esc_html_e( 'Hành động này sẽ xóa toàn bộ cấu hình BaoCache khỏi WordPress khi uninstall. BaoCache không xóa posts, users, media hoặc dữ liệu plugin khác.', 'baocache' ); ?></span></div>
						<div class="baocache-retention-matrix"><div><strong><?php esc_html_e( 'Luôn xóa', 'baocache' ); ?></strong><span>Warm Queue · lock · transient · temporary inventory · cron</span></div><div><strong><?php esc_html_e( 'Giữ mặc định', 'baocache' ); ?></strong><span>Settings · asset rules · hints · analytics · security · Cloudflare audit</span></div><div><strong><?php esc_html_e( 'Tùy chọn', 'baocache' ); ?></strong><span>Logs · history · runtime metrics · QA · baseline</span></div></div>
					</article>
					<article class="baocache-panel baocache-panel--wide" data-baocache-pane="cache">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Cache policy', 'baocache' ); ?></h2><p><?php esc_html_e( 'BaoCache chỉ gửi TTL gợi ý. Nginx FastCGI cache vẫn là lớp page cache duy nhất.', 'baocache' ); ?></p></div></div>
						<label class="baocache-field"><span><?php esc_html_e( 'TTL công khai mặc định (giây)', 'baocache' ); ?></span><input type="number" min="0" max="3600" name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[public_ttl]" value="<?php echo esc_attr( (string) $settings['public_ttl'] ); ?>"><small><?php esc_html_e( '0 giữ nguyên TTL Nginx. BaoCache không ghi đè TTL ngắn hơn do plugin khác đã gửi.', 'baocache' ); ?></small></label>
						<?php if ( BaoCache_Purge::available() ) : ?>
							<div class="baocache-callout is-success"><strong><?php esc_html_e( 'FastCGI purge đã cấu hình', 'baocache' ); ?></strong><span><?php esc_html_e( 'Bấm “Xác minh endpoint” để kiểm tra image Nginx, module, Docker DNS và generated secret trước khi purge URL. Kiểm tra này dùng key không tồn tại, không xóa cache.', 'baocache' ); ?></span></div>
							<section class="baocache-purge-evidence" aria-label="<?php esc_attr_e( 'FastCGI purge deployment evidence', 'baocache' ); ?>">
								<div>
									<span class="baocache-badge is-<?php echo esc_attr( ! empty( $purge_evidence['success'] ) ? 'good' : ( ! empty( $purge_evidence['checked_at'] ) ? 'warn' : 'neutral' ) ); ?>"><?php echo esc_html( ! empty( $purge_evidence['success'] ) ? __( 'Verified live', 'baocache' ) : ( ! empty( $purge_evidence['checked_at'] ) ? __( 'Needs review', 'baocache' ) : __( 'Not checked', 'baocache' ) ) ); ?></span>
									<strong><?php echo esc_html( $purge_remediation['title'] ); ?></strong>
									<small><?php echo esc_html( ! empty( $purge_evidence['checked_at'] ) ? sprintf( __( 'Lần xác minh %1$s · HTTP %2$d', 'baocache' ), human_time_diff( (int) $purge_evidence['checked_at'], time() ) . ' ' . __( 'trước', 'baocache' ), (int) $purge_evidence['code'] ) : __( 'Chưa có probe runtime được lưu.', 'baocache' ) ); ?></small>
								</div>
								<p><?php echo esc_html( $purge_remediation['detail'] ); ?></p>
							</section>
							<div class="baocache-purge-actions"><button type="button" class="button button-secondary" data-baocache-verify-purge><?php esc_html_e( 'Xác minh endpoint', 'baocache' ); ?></button><output data-baocache-verify-purge-result aria-live="polite"></output></div>
							<div class="baocache-purge-form">
								<label class="baocache-field"><span><?php esc_html_e( 'Purge một URL FastCGI', 'baocache' ); ?></span><input data-baocache-purge-url type="url" required value="<?php echo esc_url( home_url( '/' ) ); ?>"><small><?php esc_html_e( 'Không purge wildcard hay toàn bộ cache từ wp-admin.', 'baocache' ); ?></small></label>
								<div class="baocache-purge-url-action"><button type="button" class="button button-secondary" data-baocache-purge-url-submit><?php esc_html_e( 'Purge URL', 'baocache' ); ?></button><output data-baocache-purge-url-result aria-live="polite"></output></div>
							</div>
						<?php else : ?>
							<div class="baocache-callout"><strong><?php esc_html_e( 'Purge API chưa xác minh', 'baocache' ); ?></strong><span><?php esc_html_e( 'Redeploy image BaoCache có Nginx purge module rồi tải lại trang này. Cache vẫn hết hạn theo TTL an toàn.', 'baocache' ); ?></span></div>
						<?php endif; ?>
					</article>

					<article class="baocache-panel" data-baocache-pane="wordpress">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Core nhẹ hơn', 'baocache' ); ?></h2><p><?php esc_html_e( 'Các thay đổi mặc định an toàn cho Blocksy, Astra và theme chuẩn.', 'baocache' ); ?></p></div></div>
						<?php $this->toggle( 'disable_emoji', __( 'Tắt emoji của WordPress', 'baocache' ), $settings ); ?>
						<?php $this->toggle( 'dashicons_guests', __( 'Không tải Dashicons cho khách', 'baocache' ), $settings ); ?>
						<?php $this->toggle( 'disable_embeds', __( 'Tắt oEmbed discovery', 'baocache' ), $settings ); ?>
						<label class="baocache-field"><span><?php esc_html_e( 'Heartbeat trong wp-admin', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[heartbeat]"><?php foreach ( array( 15, 30, 60 ) as $interval ) : ?><option value="<?php echo esc_attr( (string) $interval ); ?>" <?php selected( (int) $settings['heartbeat'], $interval ); ?>><?php echo esc_html( $interval . ' giây' ); ?></option><?php endforeach; ?></select></label>
					</article>

					<?php $hardening_keys = array( 'disable_xmlrpc', 'disable_self_pingback', 'disable_trackbacks', 'hide_login_errors', 'disable_application_passwords', 'remove_rsd', 'remove_wlw', 'remove_shortlink', 'remove_x_pingback', 'remove_feed_links', 'remove_rest_api_link', 'disable_rest_user_enumeration', 'disable_file_editor', 'disable_attachment_pages', 'disable_author_enumeration' ); $hardening_enabled = count( array_filter( $hardening_keys, static fn( string $key ): bool => ! empty( $settings[ $key ] ) ) ) + ( 'keep' !== $settings['rss_mode'] ? 1 : 0 ) + ( 'off' !== (string) ( $settings['asset_version_masking'] ?? 'off' ) ? 1 : 0 ); ?>
					<details class="baocache-panel baocache-panel--wide baocache-disclosure" data-baocache-pane="security">
						<summary class="baocache-disclosure__summary"><span><strong><?php esc_html_e( 'WordPress Hardening', 'baocache' ); ?><small><?php esc_html_e( 'Giảm bề mặt WordPress; không thay thế WAF, firewall, malware scan hoặc 2FA.', 'baocache' ); ?></small></span><span class="baocache-disclosure__meta"><span class="baocache-badge is-neutral"><?php echo esc_html( sprintf( __( '%d policy đang bật', 'baocache' ), $hardening_enabled ) ); ?></span><span class="baocache-disclosure__chevron" aria-hidden="true">+</span></span></summary>
						<div class="baocache-disclosure__body">
						<div class="baocache-two-columns baocache-hardening-grid">
							<div>
								<h3 class="baocache-hardening-label"><?php esc_html_e( 'General', 'baocache' ); ?></h3>
								<?php $this->toggle( 'disable_xmlrpc', __( 'Tắt XML-RPC', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'disable_self_pingback', __( 'Tắt self pingback', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'disable_trackbacks', __( 'Tắt trackbacks / pings', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'hide_login_errors', __( 'Ẩn chi tiết lỗi đăng nhập', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'disable_application_passwords', __( 'Tắt Application Passwords', 'baocache' ), $settings ); ?>
							</div>
							<div>
								<h3 class="baocache-hardening-label"><?php esc_html_e( 'Discovery', 'baocache' ); ?></h3>
								<?php $this->toggle( 'remove_rsd', __( 'Xóa RSD link', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'remove_wlw', __( 'Xóa WLW manifest', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'remove_shortlink', __( 'Xóa shortlink và generator', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'remove_x_pingback', __( 'Xóa X-Pingback header', 'baocache' ), $settings ); ?>
								<small class="baocache-hardening-hint">✓ <?php esc_html_e( 'Bao gồm HTML meta generator và RSS generator.', 'baocache' ); ?></small>
							</div>
						</div>
						<div class="baocache-two-columns baocache-hardening-secondary">
							<div>
								<h3 class="baocache-hardening-label"><?php esc_html_e( 'Discovery & Privacy', 'baocache' ); ?></h3>
							<?php $this->toggle( 'disable_attachment_pages', __( 'Chuyển attachment pages về nội dung cha', 'baocache' ), $settings ); ?>
							<?php $this->toggle( 'disable_author_enumeration', __( 'Chuyển author archive khách về trang chủ', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'disable_rest_user_enumeration', __( 'Chặn REST API user enumeration', 'baocache' ), $settings ); ?>
								<?php $this->toggle( 'remove_rest_api_link', __( 'Xóa REST API discovery link', 'baocache' ), $settings ); ?>
							</div>
							<div>
								<h3 class="baocache-hardening-label"><?php esc_html_e( 'RSS Policy', 'baocache' ); ?></h3>
								<label class="baocache-field baocache-rss-field"><span><?php esc_html_e( 'RSS Policy', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[rss_mode]"><option value="keep" <?php selected( $settings['rss_mode'], 'keep' ); ?>><?php esc_html_e( 'Keep Feed (Recommended)', 'baocache' ); ?></option><option value="redirect" <?php selected( $settings['rss_mode'], 'redirect' ); ?>><?php esc_html_e( 'Redirect to Homepage', 'baocache' ); ?></option><option value="gone" <?php selected( $settings['rss_mode'], 'gone' ); ?>><?php esc_html_e( 'Return 410 Gone', 'baocache' ); ?></option></select><small><?php esc_html_e( 'Giữ RSS để đảm bảo tương thích SEO và các dịch vụ đọc RSS. Chỉ thay đổi nếu website không sử dụng RSS.', 'baocache' ); ?></small></label>
								<?php $this->toggle( 'remove_feed_links', __( 'Remove Feed Links', 'baocache' ), $settings ); ?>
							</div>
						</div>
						<div class="baocache-hardening-editor">
							<h3 class="baocache-hardening-label"><?php esc_html_e( 'Editor', 'baocache' ); ?></h3>
							<?php $this->toggle( 'disable_file_editor', __( 'Vô hiệu hóa trình sửa mã Theme & Plugin', 'baocache' ), $settings ); ?>
						</div>
						<div class="baocache-security-version-masking">
							<h3 class="baocache-hardening-label"><?php esc_html_e( 'Asset Version Masking', 'baocache' ); ?></h3>
							<label class="baocache-field"><span><?php esc_html_e( 'Cách xử lý ?ver= trên asset cùng website', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[asset_version_masking]"><option value="off" <?php selected( $settings['asset_version_masking'], 'off' ); ?>><?php esc_html_e( 'Tắt · giữ nguyên version', 'baocache' ); ?></option><option value="remove" <?php selected( $settings['asset_version_masking'], 'remove' ); ?>><?php esc_html_e( 'Ẩn · xóa ?ver=', 'baocache' ); ?></option><option value="fingerprint" <?php selected( $settings['asset_version_masking'], 'fingerprint' ); ?>><?php esc_html_e( 'Fingerprint · đổi thành ?v= mã ngắn', 'baocache' ); ?></option></select><small><?php esc_html_e( 'Chỉ áp dụng URL CSS/JS same-site ở frontend. Asset Inventory vẫn giữ version thật để chẩn đoán; third-party không bị thay đổi.', 'baocache' ); ?></small></label>
						</div>
						<?php if ( $this->wordfence_active() ) : ?><div class="baocache-callout is-success"><strong><?php esc_html_e( 'Đã phát hiện Wordfence', 'baocache' ); ?></strong><ul class="baocache-security-boundary"><li><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><?php esc_html_e( 'Firewall', 'baocache' ); ?></li><li><span class="dashicons dashicons-search" aria-hidden="true"></span><?php esc_html_e( 'Malware Scan', 'baocache' ); ?></li><li><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'Login Security', 'baocache' ); ?></li><li><span class="dashicons dashicons-admin-network" aria-hidden="true"></span><?php esc_html_e( '2FA', 'baocache' ); ?></li><li><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( 'Brute Force Protection', 'baocache' ); ?></li></ul><span><?php esc_html_e( 'BaoCache chỉ áp dụng WordPress Hardening và tối ưu hiệu năng để tránh chồng chéo chức năng.', 'baocache' ); ?></span><small><?php esc_html_e( 'Không phát hiện xung đột cấu hình.', 'baocache' ); ?></small></div><?php else : ?><div class="baocache-callout"><strong><?php esc_html_e( 'Phạm vi bảo mật', 'baocache' ); ?></strong><span><?php esc_html_e( 'BaoCache chỉ hardening WordPress và không phải WAF, firewall hoặc trình quét malware. Dùng Wordfence hoặc giải pháp bảo mật riêng nếu cần các lớp đó.', 'baocache' ); ?></span></div><?php endif; ?>
						<?php $this->hardening_verification( $settings ); ?>
						</div>
					</details>

					<details class="baocache-panel baocache-panel--wide baocache-disclosure" data-baocache-pane="security">
						<summary class="baocache-disclosure__summary"><span><strong><?php esc_html_e( 'Performance Headers', 'baocache' ); ?><small><?php esc_html_e( 'Header bảo mật được phát ở Nginx cho HTML, asset và response lỗi.', 'baocache' ); ?></small></span><span class="baocache-disclosure__meta"><span class="baocache-badge is-good"><?php esc_html_e( 'Nginx managed', 'baocache' ); ?></span><span class="baocache-disclosure__chevron" aria-hidden="true">+</span></span></summary>
						<div class="baocache-disclosure__body">
						<div class="baocache-header-policy"><span><strong>X-Content-Type-Options</strong><small>nosniff</small></span><span><strong>Referrer-Policy</strong><small>strict-origin-when-cross-origin</small></span><span><strong>X-Frame-Options</strong><small>SAMEORIGIN</small></span><span><strong>Permissions-Policy</strong><small>camera / microphone / geolocation off</small></span><span><strong>HSTS</strong><small>production HTTPS</small></span></div>
						<p class="baocache-analysis-note"><?php esc_html_e( 'BaoCache không ghi đè các header này từ PHP để tránh response bị lặp. Header Inspector là nơi xác minh response thực tế; CSP vẫn cần policy riêng theo asset và không được bật mù.', 'baocache' ); ?></p>
						</div>
					</details>
					<?php $this->csp_panel( $settings ); ?>

					<article class="baocache-panel baocache-panel--wide baocache-assets-workspace" data-baocache-pane="assets">
						<?php $this->asset_inventory(); ?>
					</article>

					<article class="baocache-panel baocache-panel--wide is-hidden" data-baocache-pane="assets" data-baocache-assets-pane="rules">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Defer JavaScript', 'baocache' ); ?></h2><p><?php esc_html_e( 'Dùng API strategy của WordPress, không sửa HTML bằng regex.', 'baocache' ); ?></p></div></div>
						<label class="baocache-field"><span><?php esc_html_e( 'Script handle cần defer', 'baocache' ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[defer_handles]" rows="7" placeholder="theme-script&#10;analytics-script"><?php echo esc_textarea( $settings['defer_handles'] ); ?></textarea><small><?php esc_html_e( 'Mỗi handle một dòng. Chỉ thêm sau khi kiểm tra trên staging; WordPress sẽ bảo toàn dependency hợp lệ.', 'baocache' ); ?></small></label>
					</article>

					<article class="baocache-panel baocache-panel--wide is-hidden" data-baocache-pane="assets" data-baocache-assets-pane="rules">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Render Blocking Strategy', 'baocache' ); ?></h2><p><?php esc_html_e( 'Chỉ áp dụng theo handle WordPress đã xác minh; không regex toàn bộ HTML.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php esc_html_e( 'Opt-in', 'baocache' ); ?></span></div>
						<div class="baocache-two-columns"><label class="baocache-field"><span><?php esc_html_e( 'Style handle được phép async', 'baocache' ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[async_style_handles]" rows="5" placeholder="theme-critical-nonblocking"><?php echo esc_textarea( $settings['async_style_handles'] ); ?></textarea><small><?php esc_html_e( 'BaoCache giữ noscript fallback và chỉ đổi media cho handle này.', 'baocache' ); ?></small></label><label class="baocache-field"><span><?php esc_html_e( 'Exclusion handle', 'baocache' ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[render_blocking_exclude_handles]" rows="5" placeholder="checkout-script&#10;menu-script"><?php echo esc_textarea( $settings['render_blocking_exclude_handles'] ); ?></textarea><small><?php esc_html_e( 'Một handle mỗi dòng; exclusion luôn thắng defer/async.', 'baocache' ); ?></small></label><label class="baocache-field"><span><?php esc_html_e( 'Exclusion URL prefix', 'baocache' ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[render_blocking_exclude_urls]" rows="4" placeholder="/checkout/&#10;/tai-khoan/"><?php echo esc_textarea( $settings['render_blocking_exclude_urls'] ); ?></textarea></label><label class="baocache-field"><span><?php esc_html_e( 'Exclusion context', 'baocache' ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[render_blocking_exclude_contexts]" rows="4" placeholder="authenticated&#10;preview&#10;checkout"><?php echo esc_textarea( $settings['render_blocking_exclude_contexts'] ); ?></textarea><small><?php esc_html_e( 'Mặc định loại trừ authenticated, admin, preview và checkout.', 'baocache' ); ?></small></label></div>
						<p class="baocache-analysis-note"><?php esc_html_e( 'Preview strategy chỉ phân tích dependency và metadata; Save mới thay đổi runtime. Revision cấu hình hiện tại là rollback tức thì.', 'baocache' ); ?></p>
					</article>

					<article class="baocache-panel baocache-panel--wide is-hidden" data-baocache-pane="assets" data-baocache-assets-pane="rules">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Delay JavaScript', 'baocache' ); ?></h2><p><?php esc_html_e( 'Chỉ trì hoãn handle đã chọn đến tương tác đầu tiên hoặc mốc thời gian an toàn.', 'baocache' ); ?></p></div><span class="baocache-badge is-warn"><?php esc_html_e( 'Opt-in', 'baocache' ); ?></span></div>
						<div class="baocache-two-columns baocache-delay-fields"><label class="baocache-field"><span><?php esc_html_e( 'Script handle cần delay', 'baocache' ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[delay_handles]" rows="7" placeholder="analytics-script&#10;chat-widget"><?php echo esc_textarea( $settings['delay_handles'] ); ?></textarea><small><?php esc_html_e( 'Mỗi handle một dòng. Phù hợp analytics, chat hoặc widget không cần lúc tải trang.', 'baocache' ); ?></small></label><label class="baocache-field"><span><?php esc_html_e( 'Chạy chậm nhất sau', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[delay_timeout]"><?php foreach ( array( 5000, 10000, 15000 ) as $timeout ) : ?><option value="<?php echo esc_attr( (string) $timeout ); ?>" <?php selected( (int) $settings['delay_timeout'], $timeout ); ?>><?php echo esc_html( sprintf( __( '%d giây', 'baocache' ), $timeout / 1000 ) ); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Người dùng tương tác sẽ chạy ngay. Timeout là fallback để không treo script mãi.', 'baocache' ); ?></small></label><div class="baocache-delay-note"><strong><?php esc_html_e( 'Compatibility guard', 'baocache' ); ?></strong><span><?php esc_html_e( 'BaoCache tự bỏ qua handle có inline/localization, conditional, module hoặc đang là dependency. Quản trị viên đăng nhập luôn thấy script bình thường để kiểm thử và rollback.', 'baocache' ); ?></span></div></div>
						<?php $delay_preview_active = BaoCache_Runtime::delay_preview_active(); $delay_preview_url = wp_nonce_url( add_query_arg( array( 'action' => self::DELAY_PREVIEW_ACTION, 'mode' => $delay_preview_active ? 'stop' : 'start' ), admin_url( 'admin-post.php' ) ), self::DELAY_PREVIEW_ACTION ); ?>
						<div class="baocache-delay-preview"><div><strong><?php echo esc_html( $delay_preview_active ? __( 'Delay preview đang bật', 'baocache' ) : __( 'Delay preview riêng cho quản trị viên', 'baocache' ) ); ?></strong><small><?php echo esc_html( $delay_preview_active ? __( 'Chỉ tài khoản hiện tại bị áp dụng Delay; preview tự hết hạn sau 30 phút và không tác động khách truy cập.', 'baocache' ) : __( 'Mở một tab frontend đăng nhập để thử handle đã chọn, theo dõi lỗi cục bộ và rollback trước khi khách truy cập bị ảnh hưởng.', 'baocache' ) ); ?></small></div><a class="button button-secondary" href="<?php echo esc_url( $delay_preview_url ); ?>"<?php echo $delay_preview_active ? '' : ' target="_blank" rel="noopener"'; ?>><?php echo esc_html( $delay_preview_active ? __( 'Kết thúc preview', 'baocache' ) : __( 'Mở preview 30 phút', 'baocache' ) ); ?></a></div>
					</article>

					<?php $third_party_snapshot = BaoCache_Third_Party_Optimizer::snapshot(); $third_party_application = BaoCache_Third_Party_Optimizer::application(); $third_party_candidates = is_array( $third_party_snapshot['candidates'] ?? null ) ? $third_party_snapshot['candidates'] : array(); ?>
					<article class="baocache-panel baocache-panel--wide baocache-assets-workspace" data-baocache-pane="assets">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Third-party Optimizer', 'baocache' ); ?></h2><p><?php esc_html_e( 'Phân loại script ngoài domain từ Asset Inventory. Chỉ đề xuất handle độc lập; cart, checkout, payment, navigation và dependency sẽ bị loại.', 'baocache' ); ?></p></div><span class="baocache-badge is-warn"><?php esc_html_e( 'beta79 · Review required', 'baocache' ); ?></span></div>
						<?php if ( ! empty( $third_party_candidates ) ) : ?><ul class="baocache-third-party-list"><?php foreach ( $third_party_candidates as $candidate ) : ?><li><code><?php echo esc_html( (string) ( $candidate['handle'] ?? '' ) ); ?></code><span><?php echo esc_html( (string) ( $candidate['host'] ?? '' ) ); ?></span><small><?php echo esc_html( (string) ( $candidate['risk'] ?? 'review' ) ); ?></small></li><?php endforeach; ?></ul><?php endif; ?>
						<div class="baocache-purge-actions"><button type="button" class="button button-secondary" data-baocache-scan-third-party><?php esc_html_e( 'Phân tích third-party', 'baocache' ); ?></button><?php if ( ! empty( $third_party_candidates ) ) : ?><button type="button" class="button button-primary" data-baocache-apply-third-party data-fingerprint="<?php echo esc_attr( (string) ( $third_party_snapshot['fingerprint'] ?? '' ) ); ?>"><?php esc_html_e( 'Apply delay candidates', 'baocache' ); ?></button><?php endif; ?><?php if ( ! empty( $third_party_application['applied_at'] ) && empty( $third_party_application['rolled_back_at'] ) ) : ?><button type="button" class="button button-secondary" data-baocache-rollback-third-party><?php esc_html_e( 'Rollback', 'baocache' ); ?></button><?php endif; ?><output data-baocache-third-party-result><?php echo esc_html( ! empty( $third_party_candidates ) ? sprintf( __( '%d candidate · fingerprint %s', 'baocache' ), count( $third_party_candidates ), substr( (string) ( $third_party_snapshot['fingerprint'] ?? '' ), 0, 12 ) ) : __( 'Chưa có recommendation. Hãy quét Asset Inventory trước.', 'baocache' ) ); ?></output></div>
					</article>

					<article class="baocache-panel baocache-panel--wide baocache-resource-workspace" data-baocache-pane="resources">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Resource hints', 'baocache' ); ?></h2><p><?php esc_html_e( 'Chỉ preload tài nguyên thực sự xuất hiện above-the-fold để tránh làm chậm LCP.', 'baocache' ); ?></p></div></div>
						<div class="baocache-two-columns">
							<?php $this->textarea( 'preconnect', __( 'Preconnect URL', 'baocache' ), $settings, 'https://fonts.example.com' ); ?>
							<?php $this->textarea( 'dns_prefetch', __( 'DNS prefetch URL', 'baocache' ), $settings, 'https://cdn.example.com' ); ?>
							<?php $this->textarea( 'preload', __( 'Preload URL', 'baocache' ), $settings, 'https://example.com/wp-content/uploads/hero.webp' ); ?>
						</div>
						<?php $hint_snapshot = BaoCache_Resource_Hints::snapshot(); $hint_application = BaoCache_Resource_Hints::application(); $hint_candidates = is_array( $hint_snapshot['candidates'] ?? null ) ? $hint_snapshot['candidates'] : array(); ?>
						<div class="baocache-callout" data-baocache-resource-hints><strong><?php esc_html_e( 'Automatic Resource & Font Hints · beta78', 'baocache' ); ?></strong><span><?php esc_html_e( 'Đề xuất tối đa 6 origin và 4 font từ Asset Inventory đã quan sát. Không preload mù; fingerprint cũ sẽ bị chặn.', 'baocache' ); ?></span><?php if ( ! empty( $hint_candidates ) ) : ?><ul class="baocache-resource-hint-list"><?php foreach ( $hint_candidates as $hint ) : ?><li><code><?php echo esc_html( (string) ( $hint['value'] ?? '' ) ); ?></code><small><?php echo esc_html( 'preconnect' === (string) ( $hint['type'] ?? '' ) ? __( 'Origin evidence', 'baocache' ) : __( 'Font evidence · preload', 'baocache' ) ); ?></small></li><?php endforeach; ?></ul><?php endif; ?><div class="baocache-purge-actions"><button type="button" class="button button-secondary" data-baocache-scan-resource-hints><?php esc_html_e( 'Tạo recommendation từ inventory', 'baocache' ); ?></button><?php if ( ! empty( $hint_candidates ) ) : ?><button type="button" class="button button-primary" data-baocache-apply-resource-hints data-fingerprint="<?php echo esc_attr( (string) ( $hint_snapshot['fingerprint'] ?? '' ) ); ?>"><?php esc_html_e( 'Apply recommendation', 'baocache' ); ?></button><?php endif; ?><?php if ( ! empty( $hint_application['applied_at'] ) && empty( $hint_application['rolled_back_at'] ) ) : ?><button type="button" class="button button-secondary" data-baocache-rollback-resource-hints><?php esc_html_e( 'Rollback', 'baocache' ); ?></button><?php endif; ?><output data-baocache-resource-hints-result><?php echo esc_html( ! empty( $hint_candidates ) ? sprintf( __( '%d candidate · fingerprint %s', 'baocache' ), count( $hint_candidates ), substr( (string) ( $hint_snapshot['fingerprint'] ?? '' ), 0, 12 ) ) : __( 'Chưa có recommendation. Hãy chạy Asset Inventory trước.', 'baocache' ) ); ?></output></div></div>
					</article>

					<article class="baocache-panel baocache-panel--wide baocache-resource-workspace baocache-critical-images" data-baocache-pane="resources">
						<?php $this->critical_images_panel( $settings ); ?>
					</article>

					<article class="baocache-panel baocache-panel--wide baocache-resource-workspace" data-baocache-pane="resources">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Manual critical image override', 'baocache' ); ?></h2><p><?php esc_html_e( 'Dùng URL ảnh đã đo từ PageSpeed hoặc DevTools khi muốn ghi đè recommendation tự động.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php esc_html_e( 'Advanced', 'baocache' ); ?></span></div>
						<div class="baocache-two-columns baocache-lcp-fields"><label class="baocache-field"><span><?php esc_html_e( 'URL ảnh LCP cùng website', 'baocache' ); ?></span><input name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[lcp_image]" type="url" value="<?php echo esc_attr( $settings['lcp_image'] ); ?>" placeholder="https://example.com/wp-content/uploads/hero.webp"><small><?php esc_html_e( 'Phải khớp chính xác URL ảnh WordPress tạo ra, không phải ảnh CSS background.', 'baocache' ); ?></small></label><label class="baocache-field"><span><?php esc_html_e( 'Áp dụng tại', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[lcp_scope]"><option value="front-page" <?php selected( $settings['lcp_scope'], 'front-page' ); ?>><?php esc_html_e( 'Chỉ trang chủ', 'baocache' ); ?></option><option value="everywhere" <?php selected( $settings['lcp_scope'], 'everywhere' ); ?>><?php esc_html_e( 'Toàn frontend', 'baocache' ); ?></option></select><small><?php esc_html_e( 'Chỉ chọn toàn frontend nếu cùng ảnh thực sự là LCP trên các trang đó.', 'baocache' ); ?></small></label><div class="baocache-lcp-note"><strong><?php esc_html_e( 'Khi URL khớp', 'baocache' ); ?></strong><span><?php esc_html_e( 'BaoCache thêm loading=eager, fetchpriority=high và preload ảnh tại head. Không điền khi chưa kiểm chứng HTML runtime.', 'baocache' ); ?></span></div></div>
					</article>

					<article class="baocache-panel baocache-panel--wide baocache-resource-workspace baocache-critical-resources" data-baocache-pane="resources">
						<?php $this->critical_resource_diagnostics( $settings ); ?>
					</article>

					<article class="baocache-panel baocache-panel--wide" data-baocache-pane="warmup">
						<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Sitemap preload & warm queue', 'baocache' ); ?></h2><p><?php esc_html_e( 'Tạo request ẩn tới Nginx qua Docker. Mỗi phút chỉ xử lý số URL đã chọn để bảo vệ PHP và database.', 'baocache' ); ?></p></div></div>
						<?php $this->toggle( 'warm_enabled', __( 'Bật warm queue có giới hạn tốc độ', 'baocache' ), $settings ); ?>
							<label class="baocache-field"><span><?php esc_html_e( 'Sitemap URL cùng website', 'baocache' ); ?></span><input name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[warm_sitemap]" type="url" value="<?php echo esc_attr( $settings['warm_sitemap'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/sitemap_index.xml' ) ); ?>"><small><?php esc_html_e( 'Mặc định dùng sitemap_index.xml. Chỉ chấp nhận sitemap cùng domain.', 'baocache' ); ?></small></label>
						<div class="baocache-two-columns baocache-warm-fields">
							<label class="baocache-field"><span><?php esc_html_e( 'Tốc độ tối đa', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[warm_batch]"><?php foreach ( array( 1, 2, 5 ) as $batch ) : ?><option value="<?php echo esc_attr( (string) $batch ); ?>" <?php selected( (int) $settings['warm_batch'], $batch ); ?>><?php echo esc_html( sprintf( _n( '%d URL / phút', '%d URL / phút', $batch, 'baocache' ), $batch ) ); ?></option><?php endforeach; ?></select></label>
							<label class="baocache-field"><span><?php esc_html_e( 'Đọc lại sitemap', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[warm_schedule]"><option value="hourly" <?php selected( $settings['warm_schedule'], 'hourly' ); ?>><?php esc_html_e( 'Mỗi giờ', 'baocache' ); ?></option><option value="baocache_six_hours" <?php selected( $settings['warm_schedule'], 'baocache_six_hours' ); ?>><?php esc_html_e( 'Mỗi 6 giờ', 'baocache' ); ?></option><option value="twicedaily" <?php selected( $settings['warm_schedule'], 'twicedaily' ); ?>><?php esc_html_e( 'Mỗi 12 giờ', 'baocache' ); ?></option></select></label>
							<div class="baocache-warm-note"><strong><?php esc_html_e( 'Giới hạn an toàn', 'baocache' ); ?></strong><span><?php esc_html_e( 'Không crawl link ngoài domain, tối đa 500 URL mỗi lần đọc sitemap và thử lại tối đa 2 lần khi lỗi.', 'baocache' ); ?></span></div>
						</div>
						</article>

						<article class="baocache-panel baocache-panel--wide baocache-analytics-workspace" data-baocache-pane="analytics">
							<?php $this->analytics_panel( $settings ); ?>
						</article>
					</section>

				<section class="baocache-panel baocache-assets is-hidden" data-baocache-pane="assets" data-baocache-assets-pane="rules">
					<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Script Manager', 'baocache' ); ?></h2><p><?php esc_html_e( 'Chặn CSS/JS theo ngữ cảnh URL, post type, shortcode hoặc block. BaoCache không bỏ handle đang là dependency của asset khác.', 'baocache' ); ?></p></div><button type="button" class="button button-secondary" data-baocache-add-rule><?php esc_html_e( 'Thêm rule', 'baocache' ); ?></button></div>
					<div class="baocache-rules" data-baocache-rules>
						<?php foreach ( (array) $settings['asset_rules'] as $index => $rule ) { $this->rule_row( (int) $index, $rule ); } ?>
					</div>
					<template data-baocache-rule-template><?php $this->rule_row( '__INDEX__', array( 'type' => 'script', 'handle' => '', 'scope' => 'everywhere', 'value' => '' ) ); ?></template>
				</section>

				<?php submit_button( __( 'Lưu cấu hình BaoCache', 'baocache' ), 'primary large', array( 'class' => 'baocache-save-button' ) ); ?>
				</form>
				<?php if ( ! empty( get_option( 'baocache_settings_history', array() ) ) ) : ?>
					<section class="baocache-panel baocache-revisions-panel" data-baocache-pane="assets">
						<?php $this->revision_history(); ?>
					</section>
				<?php endif; ?>

			<section class="baocache-panel baocache-redis" data-baocache-pane="cache">
				<div><h2><?php esc_html_e( 'Redis object cache', 'baocache' ); ?></h2><p><?php esc_html_e( 'Thao tác này chỉ flush object cache. Nó không xóa database, uploads hoặc HTML FastCGI cache.', 'baocache' ); ?></p></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( self::FLUSH_ACTION ); ?>"><?php wp_nonce_field( self::FLUSH_ACTION ); ?><button class="button button-secondary" type="submit"><?php esc_html_e( 'Flush Redis object cache', 'baocache' ); ?></button></form>
			</section>

			<?php $warmup = BaoCache_Warmup::status(); ?>
				<section class="baocache-panel baocache-warmup" data-baocache-pane="warmup">
					<div><h2><?php esc_html_e( 'Warm queue runtime', 'baocache' ); ?></h2><p><?php echo esc_html( $warmup['scheduled'] ? __( 'Worker mỗi phút đang được lên lịch bởi WordPress cron.', 'baocache' ) : __( 'Warm queue đang tắt hoặc chưa có lịch chạy.', 'baocache' ) ); ?></p><dl><div><dt><?php esc_html_e( 'Đang chờ', 'baocache' ); ?></dt><dd data-baocache-warm-queued><?php echo esc_html( (string) ( $warmup['queued'] ?? 0 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Sitemap đã phát hiện', 'baocache' ); ?></dt><dd class="baocache-warmup__url" data-baocache-warm-sitemap><?php echo esc_html( ! empty( $warmup['detected_sitemap'] ) ? (string) $warmup['detected_sitemap'] : '—' ); ?></dd></div><div><dt><?php esc_html_e( 'Kết quả gần nhất', 'baocache' ); ?></dt><dd><?php echo esc_html( isset( $warmup['last_warmed'] ) ? sprintf( __( '%d warm / %d lỗi', 'baocache' ), (int) $warmup['last_warmed'], (int) ( $warmup['last_failed'] ?? 0 ) ) : '—' ); ?></dd></div></dl><?php if ( ! empty( $warmup['last_sitemap_error'] ) ) : ?><p class="baocache-warmup__error"><?php echo esc_html( (string) $warmup['last_sitemap_error'] ); ?></p><?php endif; ?><?php if ( ! empty( $warmup['last_schedule_error'] ) ) : ?><p class="baocache-warmup__error"><?php echo esc_html( sprintf( __( 'Cron: %s', 'baocache' ), (string) $warmup['last_schedule_error'] ) ); ?></p><?php endif; ?></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-baocache-warmup-form><?php wp_nonce_field( self::WARM_ACTION ); ?><input type="hidden" name="action" value="<?php echo esc_attr( self::WARM_ACTION ); ?>"><button class="button button-secondary" type="submit" data-baocache-warmup <?php disabled( empty( $settings['warm_enabled'] ) || '' === $settings['warm_sitemap'] ); ?>><?php esc_html_e( 'Đọc sitemap và thêm queue', 'baocache' ); ?></button></form>
			</section>
				</main>
			</div>
		</div>
		<?php
	}

	private function compatibility_qa_panel(): void {
		$items = $this->compatibility_qa_items();
		$record = get_option( 'baocache_compatibility_qa', array() );
		$record = is_array( $record ) ? $record : array();
		$checks = is_array( $record['checks'] ?? null ) ? $record['checks'] : array();
		$passed = count( array_filter( $checks, static fn( mixed $value ): bool => 'pass' === $value ) );
		$failed = count( array_filter( $checks, static fn( mixed $value ): bool => 'fail' === $value ) );
		$has_record = ! empty( $record['saved_at'] );
		$badge_state = ! $has_record ? 'neutral' : ( $failed > 0 ? 'warn' : ( $passed === count( $items ) ? 'good' : 'neutral' ) );
		$badge_label = ! $has_record ? __( 'Chưa test', 'baocache' ) : sprintf( __( '%1$d/%2$d PASS', 'baocache' ), $passed, count( $items ) );
		?>
		<section class="baocache-panel baocache-compatibility-qa" aria-labelledby="baocache-compatibility-qa-title">
			<div class="baocache-panel__heading"><div><h2 id="baocache-compatibility-qa-title"><?php esc_html_e( 'Staging Compatibility QA', 'baocache' ); ?></h2><p><?php esc_html_e( 'Checklist thủ công sau khi bật strategy. BaoCache không giả lập click, không tự gọi analytics/chat và không biến checklist thành điểm PageSpeed.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( $badge_state ); ?>"><?php echo esc_html( $badge_label ); ?></span></div>
			<div class="baocache-compatibility-qa__meta"><span><?php echo esc_html( ! empty( $record['saved_at'] ) ? sprintf( __( 'Lần lưu gần nhất: %s · %s', 'baocache' ), human_time_diff( (int) $record['saved_at'], time() ) . ' ' . __( 'trước', 'baocache' ), (string) ( $record['environment'] ?? wp_get_environment_type() ) ) : __( 'Chưa có kết quả staging.', 'baocache' ) ); ?></span><small><?php esc_html_e( 'Các context login/checkout phải PASS trước khi bật production strategy.', 'baocache' ); ?></small></div>
			<div class="baocache-compatibility-qa__table"><table><thead><tr><th><?php esc_html_e( 'Hạng mục', 'baocache' ); ?></th><th><?php esc_html_e( 'Phạm vi kiểm tra', 'baocache' ); ?></th><th><?php esc_html_e( 'Kết quả', 'baocache' ); ?></th></tr></thead><tbody><?php foreach ( $items as $id => $item ) : ?><tr><td><strong><?php echo esc_html( $item['label'] ); ?></strong></td><td><small><?php echo esc_html( $item['detail'] ); ?></small></td><td><select data-baocache-compatibility-check="<?php echo esc_attr( $id ); ?>"><option value="pending" <?php selected( (string) ( $checks[ $id ] ?? 'pending' ), 'pending' ); ?>><?php esc_html_e( 'Chưa test', 'baocache' ); ?></option><option value="pass" <?php selected( (string) ( $checks[ $id ] ?? '' ), 'pass' ); ?>><?php esc_html_e( 'PASS', 'baocache' ); ?></option><option value="fail" <?php selected( (string) ( $checks[ $id ] ?? '' ), 'fail' ); ?>><?php esc_html_e( 'FAIL', 'baocache' ); ?></option><option value="skip" <?php selected( (string) ( $checks[ $id ] ?? '' ), 'skip' ); ?>><?php esc_html_e( 'Bỏ qua', 'baocache' ); ?></option></select></td></tr><?php endforeach; ?></tbody></table></div>
			<div class="baocache-compatibility-qa__actions"><button type="button" class="button button-primary" data-baocache-save-compatibility-qa><?php esc_html_e( 'Lưu kết quả QA', 'baocache' ); ?></button><button type="button" class="button button-secondary" data-baocache-reset-compatibility-qa><?php esc_html_e( 'Reset checklist', 'baocache' ); ?></button><output data-baocache-compatibility-qa-result></output></div>
		</section>
		<?php
	}

	private function status(): array {
		$redis_configured = defined( 'WP_REDIS_HOST' );
		$object_cache = wp_using_ext_object_cache();
		$redis_metrics = BaoCache_Diagnostics::redis_metrics();
		$fastcgi_metrics = BaoCache_Diagnostics::fastcgi_metrics();
		$settings = BaoCache_Settings::get();
		$core_enabled = ! empty( $settings['disable_emoji'] ) || ! empty( $settings['disable_embeds'] ) || ! empty( $settings['dashicons_guests'] );
		$asset_enabled = '' !== trim( (string) $settings['defer_handles'] ) || '' !== trim( (string) $settings['delay_handles'] ) || ! empty( $settings['asset_rules'] );
		$hint_enabled = '' !== trim( (string) $settings['preconnect'] ) || '' !== trim( (string) $settings['dns_prefetch'] ) || '' !== trim( (string) $settings['preload'] ) || '' !== trim( (string) $settings['lcp_image'] );
		return array(
			array( 'label' => __( 'Nginx FastCGI Cache', 'baocache' ), 'status' => $fastcgi_metrics['available'] ? __( 'Healthy', 'baocache' ) : __( 'Đang chờ dữ liệu', 'baocache' ), 'value' => $fastcgi_metrics['available'] && null !== $fastcgi_metrics['hit_ratio'] ? sprintf( __( 'HIT %s%%', 'baocache' ), $fastcgi_metrics['hit_ratio'] ) : __( 'Chưa có tỷ lệ HIT', 'baocache' ), 'meta' => $fastcgi_metrics['available'] ? sprintf( __( '%d request trong 24 giờ', 'baocache' ), $fastcgi_metrics['total'] ) : __( 'Kiểm tra header để xác minh', 'baocache' ), 'state' => $fastcgi_metrics['available'] ? 'good' : ( defined( 'WP_CACHE' ) && WP_CACHE ? 'warn' : 'bad' ), 'tab' => 'cache' ),
			array( 'label' => __( 'Redis Object Cache', 'baocache' ), 'status' => $redis_configured && $object_cache && $redis_metrics['connected'] ? __( 'Connected', 'baocache' ) : __( 'Cần chú ý', 'baocache' ), 'value' => $redis_metrics['connected'] && null !== $redis_metrics['latency_ms'] ? $redis_metrics['latency_ms'] . ' ms' : __( 'Chưa kết nối', 'baocache' ), 'meta' => null !== $redis_metrics['hit_ratio'] ? sprintf( __( 'HIT %s%%', 'baocache' ), $redis_metrics['hit_ratio'] ) : __( 'Chưa đủ dữ liệu HIT', 'baocache' ), 'state' => $redis_configured && $object_cache && $redis_metrics['connected'] ? 'good' : 'warn', 'tab' => 'cache' ),
			array( 'label' => __( 'PHP Runtime', 'baocache' ), 'status' => extension_loaded( 'redis' ) ? __( 'Healthy', 'baocache' ) : __( 'Error', 'baocache' ), 'value' => 'PHP ' . PHP_VERSION, 'meta' => sprintf( __( 'Memory %s · PhpRedis %s', 'baocache' ), WP_MEMORY_LIMIT, extension_loaded( 'redis' ) ? __( 'đã nạp', 'baocache' ) : __( 'thiếu', 'baocache' ) ), 'state' => extension_loaded( 'redis' ) ? 'good' : 'bad', 'tab' => 'dashboard' ),
			array( 'label' => __( 'Optimization Modules', 'baocache' ), 'status' => __( 'Configuration', 'baocache' ), 'value' => sprintf( __( '%d/3 đang bật', 'baocache' ), (int) $core_enabled + (int) $asset_enabled + (int) $hint_enabled ), 'meta' => sprintf( __( '%d module chờ cấu hình', 'baocache' ), 3 - ( (int) $core_enabled + (int) $asset_enabled + (int) $hint_enabled ) ), 'state' => ( $core_enabled || $asset_enabled || $hint_enabled ) ? 'neutral' : 'warn', 'tab' => 'assets' ),
		);
	}

	private function overview( array $settings, array $status ): array {
		$has_configuration_issue = false;
		$runtime_good = 0;
		$recommendations = array();
		foreach ( $status as $card ) {
			if ( 'good' === $card['state'] ) {
				$runtime_good++;
			}
			if ( 'bad' === $card['state'] ) {
				$has_configuration_issue = true;
				$recommendations[] = array( 'priority' => 'high', 'priority_label' => __( 'Cao', 'baocache' ), 'title' => __( 'PhpRedis chưa sẵn sàng', 'baocache' ), 'detail' => __( 'Redis object cache không thể vận hành ổn định khi thiếu extension.', 'baocache' ), 'tab' => 'cache', 'action' => __( 'Mở Cache', 'baocache' ) );
			} elseif ( 'warn' === $card['state'] ) {
				$has_configuration_issue = true;
				$recommendations[] = array( 'priority' => 'medium', 'priority_label' => __( 'Trung bình', 'baocache' ), 'title' => sprintf( __( '%s cần xác minh', 'baocache' ), $card['label'] ), 'detail' => __( 'Kiểm tra trạng thái runtime trước khi bật các tối ưu frontend.', 'baocache' ), 'tab' => 'cache', 'action' => __( 'Kiểm tra', 'baocache' ) );
			}
		}

		$core_enabled = ! empty( $settings['disable_emoji'] ) || ! empty( $settings['disable_embeds'] ) || ! empty( $settings['dashicons_guests'] );
		$asset_enabled = '' !== trim( (string) $settings['defer_handles'] ) || '' !== trim( (string) $settings['delay_handles'] ) || ! empty( $settings['asset_rules'] );
		$hint_enabled = '' !== trim( (string) $settings['preconnect'] ) || '' !== trim( (string) $settings['dns_prefetch'] ) || '' !== trim( (string) $settings['preload'] ) || '' !== trim( (string) $settings['lcp_image'] );
		if ( ! $hint_enabled ) {
			$recommendations[] = array( 'priority' => 'medium', 'priority_label' => __( 'Trung bình', 'baocache' ), 'title' => __( 'Chưa cấu hình LCP resource', 'baocache' ), 'detail' => __( 'Đo trang chủ trước khi thêm preload; preload sai có thể làm LCP chậm hơn.', 'baocache' ), 'tab' => 'resources', 'action' => __( 'Mở Resource Hints', 'baocache' ) );
		}
		if ( ! $asset_enabled ) {
			$recommendations[] = array( 'priority' => 'low', 'priority_label' => __( 'Thấp', 'baocache' ), 'title' => __( 'Chưa có asset strategy', 'baocache' ), 'detail' => __( 'Quét handle trên staging trước khi thêm defer hoặc rule unload.', 'baocache' ), 'tab' => 'assets', 'action' => __( 'Mở Assets', 'baocache' ) );
		}
		if ( ! $core_enabled ) {
			$recommendations[] = array( 'priority' => 'low', 'priority_label' => __( 'Thấp', 'baocache' ), 'title' => __( 'Core reduction chưa đầy đủ', 'baocache' ), 'detail' => __( 'Rà soát emoji, oEmbed discovery và Dashicons cho khách.', 'baocache' ), 'tab' => 'cache', 'action' => __( 'Mở Cache', 'baocache' ) );
		}
		if ( empty( $recommendations ) ) {
			$recommendations[] = array( 'priority' => 'low', 'priority_label' => __( 'Thông tin', 'baocache' ), 'title' => __( 'Cấu hình nền tảng đã ổn', 'baocache' ), 'detail' => __( 'Bước tiếp theo là đo PageSpeed và Core Web Vitals từ nguồn dữ liệu thực.', 'baocache' ), 'tab' => 'dashboard', 'action' => __( 'Xem diagnostics', 'baocache' ) );
		}

		$modules_enabled = (int) $core_enabled + (int) $asset_enabled + (int) $hint_enabled;
		return array(
			'label' => $has_configuration_issue ? __( 'Cần rà soát', 'baocache' ) : __( 'Đã xác minh', 'baocache' ),
			'runtime' => sprintf( __( 'Runtime: %d/3 thành phần cache đã xác minh', 'baocache' ), min( 3, $runtime_good ) ),
			'recommendations' => array_slice( $recommendations, 0, 3 ),
			'modules_enabled' => $modules_enabled,
		);
	}

	private function health_checks( array $status ): array {
		return array_map(
			static fn( array $card ): array => array( 'label' => $card['label'], 'value' => $card['value'], 'status' => $card['status'], 'state' => $card['state'] ),
			array_slice( $status, 0, 3 )
		);
	}

	private function activity_label( string $action ): string {
		return match ( $action ) {
			'redis_flush' => __( 'Redis flush', 'baocache' ),
			'fastcgi_purge' => __( 'FastCGI purge', 'baocache' ),
			'fastcgi_purge_verify' => __( 'FastCGI purge verification', 'baocache' ),
			'warm_queue' => __( 'Warm Queue', 'baocache' ),
			'asset_scan' => __( 'Asset Scan', 'baocache' ),
			'runtime_snapshot' => __( 'Runtime Snapshot', 'baocache' ),
			'header_check' => __( 'Header Check', 'baocache' ),
			'cloudflare_audit' => __( 'Cloudflare Audit', 'baocache' ),
			'analytics_config' => __( 'Analytics configuration', 'baocache' ),
			'analytics_evidence' => __( 'Analytics public evidence', 'baocache' ),
			'csp_reports_cleared' => __( 'CSP evidence cleared', 'baocache' ),
			'csp_recommendation_applied' => __( 'CSP recommendation applied', 'baocache' ),
			'csp_recommendation_dismissed' => __( 'CSP recommendation dismissed', 'baocache' ),
			'csp_recommendation_rolled_back' => __( 'CSP recommendation rolled back', 'baocache' ),
			'csp_evidence_review' => __( 'CSP evidence retention review', 'baocache' ),
			'csp_enforce_acknowledged' => __( 'CSP Enforce acknowledgement', 'baocache' ),
			'csp_post_enforcement_probe' => __( 'CSP post-enforcement probe', 'baocache' ),
			'csp_post_probe_acknowledged' => __( 'CSP canary acknowledgement', 'baocache' ),
			'csp_remediation_step' => __( 'CSP remediation step', 'baocache' ),
			'csp_manual_rollback' => __( 'CSP manual rollback', 'baocache' ),
			'hardening_probe' => __( 'Hardening Probe', 'baocache' ),
			'hardening_baseline' => __( 'Hardening Baseline', 'baocache' ),
			'hardening_ack' => __( 'Probe Acknowledged', 'baocache' ),
			'render_blocking_audit' => __( 'Render-Blocking Audit', 'baocache' ),
			'render_blocking_context' => __( 'Render-Blocking Context QA', 'baocache' ),
			'critical_css_staged' => __( 'Critical CSS Staged', 'baocache' ),
			'critical_css_rollback' => __( 'Critical CSS Rollback', 'baocache' ),
			'critical_image_scan' => __( 'Critical Image Scan', 'baocache' ),
			'critical_image_apply' => __( 'Critical Image Apply', 'baocache' ),
			'critical_image_rollback' => __( 'Critical Image Rollback', 'baocache' ),
			'resource_hints_scan' => __( 'Resource Hints Scan', 'baocache' ),
			'resource_hints_apply' => __( 'Resource Hints Apply', 'baocache' ),
			'resource_hints_rollback' => __( 'Resource Hints Rollback', 'baocache' ),
			'third_party_scan' => __( 'Third-party Scan', 'baocache' ),
			'third_party_apply' => __( 'Third-party Apply', 'baocache' ),
			'third_party_rollback' => __( 'Third-party Rollback', 'baocache' ),
			'database_repair' => __( 'BaoCache Database Repair', 'baocache' ),
			'database_runtime_cleanup' => __( 'BaoCache Runtime Cleanup', 'baocache' ),
			'compatibility_qa' => __( 'Staging Compatibility QA', 'baocache' ),
			'rule_gate' => __( 'Per-rule Compatibility Gate', 'baocache' ),
			'rule_gate_history' => __( 'Gate evidence history', 'baocache' ),
			'rule_gate_ack' => __( 'Stale gate acknowledgement', 'baocache' ),
			'rule_gate_review' => __( 'Evidence gate review', 'baocache' ),
			'delay_preview' => __( 'Delay Preview', 'baocache' ),
			'settings_saved' => __( 'Cấu hình', 'baocache' ),
			default => __( 'Hệ thống', 'baocache' ),
		};
	}

	/** Nginx emits these bounded groups only; never surface raw request data. */
	private function bypass_reason_label( string $reason ): string {
		return match ( $reason ) {
			'method' => __( 'Phương thức không phải GET/HEAD', 'baocache' ),
			'query' => __( 'Có query string', 'baocache' ),
			'path' => __( 'Đường dẫn được loại trừ', 'baocache' ),
			'cookie' => __( 'Cookie phiên hoặc đăng nhập', 'baocache' ),
			'authorization' => __( 'Authorization header', 'baocache' ),
			default => __( 'Không rõ', 'baocache' ),
		};
	}

	/** Render a gated historical range; no trend is shown before it has real coverage. */
	private function runtime_history_window( array $window ): void {
		$hours = (int) ( $window['hours'] ?? 0 );
		$coverage_hours = (int) floor( (int) ( $window['coverage_seconds'] ?? 0 ) / HOUR_IN_SECONDS );
		$required_hours = (int) ceil( (int) ( $window['required_seconds'] ?? 0 ) / HOUR_IN_SECONDS );
		$label = 24 === $hours ? __( '24 giờ', 'baocache' ) : ( 168 === $hours ? __( '7 ngày', 'baocache' ) : __( '30 ngày', 'baocache' ) );
		$series = is_array( $window['series'] ?? null ) ? $window['series'] : array();
		$fastcgi = is_array( $series['fastcgi_hit_ratio'] ?? null ) ? $series['fastcgi_hit_ratio'] : array();
		$redis = is_array( $series['redis_latency'] ?? null ) ? $series['redis_latency'] : array();
		?>
		<article><div><strong><?php echo esc_html( $label ); ?></strong><?php if ( empty( $window['ready'] ) ) : ?><span class="baocache-badge is-neutral"><?php esc_html_e( 'Collecting', 'baocache' ); ?></span><?php endif; ?></div>
			<?php if ( empty( $window['ready'] ) ) : ?><p><?php echo esc_html( sprintf( __( '%1$d snapshot · coverage %2$d/%3$d giờ', 'baocache' ), (int) ( $window['samples'] ?? 0 ), $coverage_hours, $required_hours ) ); ?></p>
			<?php else : ?>
				<div class="baocache-runtime-history__metric"><span><?php esc_html_e( 'FastCGI HIT', 'baocache' ); ?></span><b><?php echo esc_html( $this->last_metric( $fastcgi, '%' ) ); ?></b><?php echo $this->metric_sparkline( $fastcgi, '#3858e9' ); ?></div>
				<div class="baocache-runtime-history__metric"><span><?php esc_html_e( 'Redis latency', 'baocache' ); ?></span><b><?php echo esc_html( $this->last_metric( $redis, ' ms' ) ); ?></b><?php echo $this->metric_sparkline( $redis, '#159562' ); ?></div>
				<p><?php echo esc_html( sprintf( __( '%d snapshot thực', 'baocache' ), (int) ( $window['samples'] ?? 0 ) ) ); ?></p>
			<?php endif; ?>
		</article>
		<?php
	}

	private function last_metric( array $points, string $suffix ): string {
		$last = $points ? $points[ count( $points ) - 1 ] : array();
		return isset( $last['value'] ) ? number_format_i18n( (float) $last['value'], '%' === $suffix ? 1 : 2 ) . $suffix : '—';
	}

	/** @param array<int,array{at:int,value:float}> $points */
	private function metric_sparkline( array $points, string $color ): string {
		if ( count( $points ) < 2 ) {
			return '';
		}
		$values = array_map( static fn( array $point ): float => (float) $point['value'], $points );
		$minimum = min( $values );
		$span = max( $values ) - $minimum;
		$coordinates = array();
		foreach ( $values as $index => $value ) {
			$x = 100 * $index / ( count( $values ) - 1 );
			$y = 28 - ( $span > 0 ? ( ( $value - $minimum ) / $span * 24 ) : 12 );
			$coordinates[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}
		return '<svg class="baocache-sparkline" viewBox="0 0 100 32" role="img" aria-label="Runtime trend"><polyline fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="2" vector-effect="non-scaling-stroke" points="' . esc_attr( implode( ' ', $coordinates ) ) . '" /></svg>';
	}

	private function site_diagnostics(): array {
		$settings = BaoCache_Settings::get();
		$redis = BaoCache_Diagnostics::redis_metrics();
		$fastcgi = BaoCache_Diagnostics::fastcgi_metrics();
		$php = BaoCache_Diagnostics::php_runtime();
		$warmup = BaoCache_Warmup::status();
		$cron_next = wp_next_scheduled( 'baocache_warmup_tick' );
		$snapshot_count = count( BaoCache_Metrics::history() );
		$cloudflare = BaoCache_Cloudflare::configuration();
		$purge_evidence = BaoCache_Purge::evidence();
		$purge_remediation = BaoCache_Purge::remediation( $purge_evidence );
		$probe_next = wp_next_scheduled( self::HARDENING_PROBE_TICK );
		$probe_history = get_option( 'baocache_hardening_probe_history', array() );
		$probe_latest = is_array( $probe_history ) ? ( $probe_history[0] ?? array() ) : array();
		$probe_regressions = is_array( $probe_latest['regressions'] ?? null ) ? count( $probe_latest['regressions'] ) : 0;
		$probe_scheduled = ! empty( $settings['probe_enabled'] ) && 'manual' !== (string) ( $settings['probe_schedule'] ?? 'manual' ) && $probe_next;
		$probe_status = $probe_regressions > 0 ? __( 'Regression', 'baocache' ) : ( $probe_scheduled ? __( 'Scheduled', 'baocache' ) : __( 'Manual', 'baocache' ) );
		$probe_detail = $probe_regressions > 0
			? sprintf( _n( '%d regression trong lần probe gần nhất', '%d regressions trong lần probe gần nhất', $probe_regressions, 'baocache' ), $probe_regressions )
			: ( $probe_scheduled ? sprintf( __( 'Lần kế tiếp %s', 'baocache' ), wp_date( 'H:i', (int) $probe_next ) ) : __( 'Probe định kỳ chưa bật', 'baocache' ) );
		return array(
			array( 'label' => __( 'Redis Object Cache', 'baocache' ), 'status' => $redis['connected'] ? __( 'Connected', 'baocache' ) : __( 'Error', 'baocache' ), 'detail' => $redis['connected'] ? sprintf( __( 'Ping %s ms', 'baocache' ), (string) $redis['latency_ms'] ) : __( 'Không xác minh được kết nối', 'baocache' ), 'state' => $redis['connected'] ? 'good' : 'bad' ),
			array( 'label' => __( 'FastCGI Observer', 'baocache' ), 'status' => $fastcgi['available'] ? __( 'Healthy', 'baocache' ) : __( 'Waiting', 'baocache' ), 'detail' => $fastcgi['available'] ? sprintf( __( '%d events / 24h', 'baocache' ), (int) $fastcgi['total'] ) : __( 'Chưa có dữ liệu Nginx', 'baocache' ), 'state' => $fastcgi['available'] ? 'good' : 'warn' ),
			array( 'label' => __( 'PHP OPcache', 'baocache' ), 'status' => $php['opcache_enabled'] ? __( 'Enabled', 'baocache' ) : __( 'Unavailable', 'baocache' ), 'detail' => $php['opcache_enabled'] ? sprintf( __( 'Hit %s%% · JIT %s', 'baocache' ), null !== $php['opcache_hit_rate'] ? (string) $php['opcache_hit_rate'] : '—', $php['jit_enabled'] ? __( 'on', 'baocache' ) : __( 'off', 'baocache' ) ) : __( 'Không sửa cấu hình PHP từ BaoCache', 'baocache' ), 'state' => $php['opcache_enabled'] ? 'good' : 'warn' ),
			array( 'label' => __( 'Warm Queue Cron', 'baocache' ), 'status' => ! empty( $warmup['scheduled'] ) ? __( 'Scheduled', 'baocache' ) : __( 'Disabled', 'baocache' ), 'detail' => ! empty( $warmup['scheduled'] ) && $cron_next ? sprintf( __( 'Lần kế tiếp %s', 'baocache' ), wp_date( 'H:i', (int) $cron_next ) ) : __( 'Không có worker được lên lịch', 'baocache' ), 'state' => ! empty( $warmup['scheduled'] ) ? 'good' : 'neutral' ),
			array(
				'label' => __( 'FastCGI Purge', 'baocache' ),
				'status' => ! BaoCache_Purge::available() ? __( 'Unavailable', 'baocache' ) : ( ! empty( $purge_evidence['success'] ) ? __( 'Verified', 'baocache' ) : ( ! empty( $purge_evidence['checked_at'] ) ? __( 'Review', 'baocache' ) : __( 'Configured', 'baocache' ) ) ),
				'detail' => ! BaoCache_Purge::available() ? __( 'Kiểm tra Nginx image và secret volume', 'baocache' ) : ( ! empty( $purge_evidence['success'] ) ? sprintf( __( 'Live endpoint · HTTP %d', 'baocache' ), (int) $purge_evidence['code'] ) : ( ! empty( $purge_evidence['checked_at'] ) ? $purge_remediation['detail'] : __( 'Docker-only · run Verify endpoint', 'baocache' ) ) ),
				'state' => ! BaoCache_Purge::available() ? 'warn' : ( ! empty( $purge_evidence['success'] ) ? 'good' : ( ! empty( $purge_evidence['checked_at'] ) ? 'warn' : 'neutral' ) ),
			),
			array( 'label' => __( 'Runtime Metrics', 'baocache' ), 'status' => $snapshot_count > 0 ? __( 'Collecting', 'baocache' ) : __( 'Scheduled', 'baocache' ), 'detail' => $snapshot_count > 0 ? sprintf( __( '%d snapshot thực đã lưu', 'baocache' ), $snapshot_count ) : __( 'Snapshot đầu tiên chạy sau khoảng 5 phút', 'baocache' ), 'state' => 'neutral' ),
			array( 'label' => __( 'Cloudflare Audit', 'baocache' ), 'status' => $cloudflare['configured'] ? __( 'Ready', 'baocache' ) : __( 'Opt-in', 'baocache' ), 'detail' => $cloudflare['configured'] ? __( 'Read-only · Coolify environment', 'baocache' ) : __( 'Không yêu cầu token nếu không dùng audit', 'baocache' ), 'state' => $cloudflare['configured'] ? 'good' : 'neutral' ),
			array( 'label' => __( 'Hardening Probe', 'baocache' ), 'status' => $probe_status, 'detail' => $probe_detail, 'state' => $probe_regressions > 0 ? 'warn' : ( $probe_scheduled ? 'good' : 'neutral' ) ),
		);
	}

	private function database_health_panel(): void {
		$health = BaoCache_Database_Health::inspect();
		$autoload = is_array( $health['autoload'] ?? null ) ? $health['autoload'] : array();
		?>
		<section class="baocache-panel baocache-database-health">
			<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'BaoCache Database Health', 'baocache' ); ?></h2><p><?php esc_html_e( 'Chỉ kiểm tra và sửa option, cron, queue và schema marker do BaoCache quản lý. Không thay đổi bảng nội dung WordPress hoặc dữ liệu plugin khác.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo 'healthy' === $health['status'] ? 'good' : 'warn'; ?>" data-baocache-database-status><?php echo esc_html( 'healthy' === $health['status'] ? __( 'Healthy', 'baocache' ) : __( 'Needs attention', 'baocache' ) ); ?></span></div>
			<div class="baocache-database-health__grid">
				<div><small><?php esc_html_e( 'Schema version', 'baocache' ); ?></small><strong><?php echo esc_html( (string) $health['schema_current'] . ' / ' . (string) $health['schema_expected'] ); ?></strong></div>
				<div><small><?php esc_html_e( 'Custom tables', 'baocache' ); ?></small><strong>0 / 0</strong><span><?php esc_html_e( 'Không yêu cầu trong bản này', 'baocache' ); ?></span></div>
				<div><small><?php esc_html_e( 'Pending migrations', 'baocache' ); ?></small><strong><?php echo esc_html( (string) $health['pending_migrations'] ); ?></strong></div>
				<div><small><?php esc_html_e( 'Warm queue jobs', 'baocache' ); ?></small><strong><?php echo esc_html( (string) $health['queue_jobs'] ); ?></strong></div>
				<div><small><?php esc_html_e( 'Configuration', 'baocache' ); ?></small><strong><?php echo ! empty( $health['configuration_valid'] ) ? esc_html__( 'Valid', 'baocache' ) : esc_html__( 'Invalid', 'baocache' ); ?></strong></div>
				<div><small><?php esc_html_e( 'Autoload options', 'baocache' ); ?></small><strong><?php echo esc_html( BaoCache_Diagnostics::bytes( (int) ( $autoload['bytes'] ?? 0 ) ) ); ?></strong><span><?php echo esc_html( sprintf( __( '%d options · chỉ đọc', 'baocache' ), (int) ( $autoload['items'] ?? 0 ) ) ); ?></span></div>
			</div>
			<details class="baocache-autoload-inspector"><summary><?php esc_html_e( 'Largest autoload options (read-only)', 'baocache' ); ?></summary><p><?php esc_html_e( 'BaoCache chỉ đo tên option và kích thước; không đọc ra UI giá trị, không xóa và không disable plugin.', 'baocache' ); ?></p><div class="baocache-autoload-list"><?php foreach ( (array) ( $autoload['largest'] ?? array() ) as $option ) : ?><span><code><?php echo esc_html( (string) $option['name'] ); ?></code><strong><?php echo esc_html( BaoCache_Diagnostics::bytes( (int) $option['bytes'] ) ); ?></strong></span><?php endforeach; ?></div></details>
			<div class="baocache-database-actions"><button type="button" class="button button-secondary" data-baocache-database-check><?php esc_html_e( 'Kiểm tra dữ liệu BaoCache', 'baocache' ); ?></button><button type="button" class="button button-secondary" data-baocache-database-repair><?php esc_html_e( 'Sửa cấu trúc dữ liệu BaoCache', 'baocache' ); ?></button><button type="button" class="button button-secondary" data-baocache-database-clean><?php esc_html_e( 'Dọn dữ liệu runtime BaoCache', 'baocache' ); ?></button><output data-baocache-database-result aria-live="polite"></output></div>
		</section>
		<?php
	}

	private function technical_report(): array {
		$active_plugins = get_option( 'active_plugins', array() );
		$redis = BaoCache_Diagnostics::redis_metrics();
		$fastcgi = BaoCache_Diagnostics::fastcgi_metrics();
		$redis_memory = BaoCache_Diagnostics::bytes( $redis['memory'] );
		if ( null !== $redis['memory_max'] && $redis['memory_max'] > 0 ) {
			$redis_memory .= ' / ' . BaoCache_Diagnostics::bytes( $redis['memory_max'] );
		}
		return array(
			array( 'label' => __( 'WordPress', 'baocache' ), 'value' => get_bloginfo( 'version' ) ),
			array( 'label' => __( 'PHP', 'baocache' ), 'value' => PHP_VERSION ),
			array( 'label' => __( 'PHP memory limit', 'baocache' ), 'value' => (string) WP_MEMORY_LIMIT ),
			array( 'label' => __( 'Môi trường', 'baocache' ), 'value' => wp_get_environment_type() ),
			array( 'label' => __( 'Plugin đang kích hoạt', 'baocache' ), 'value' => (string) ( is_array( $active_plugins ) ? count( $active_plugins ) : 0 ) ),
			array( 'label' => __( 'Object-cache drop-in', 'baocache' ), 'value' => wp_using_ext_object_cache() ? __( 'Đang dùng', 'baocache' ) : __( 'Không phát hiện', 'baocache' ) ),
			array( 'label' => __( 'Redis latency', 'baocache' ), 'value' => $redis['connected'] && null !== $redis['latency_ms'] ? $redis['latency_ms'] . ' ms' : __( 'Chưa kết nối', 'baocache' ) ),
			array( 'label' => __( 'Redis hit ratio', 'baocache' ), 'value' => null !== $redis['hit_ratio'] ? $redis['hit_ratio'] . '%' : __( 'Chưa đủ dữ liệu', 'baocache' ) ),
			array( 'label' => __( 'Redis memory', 'baocache' ), 'value' => $redis_memory ),
			array( 'label' => __( 'FastCGI requests (24h)', 'baocache' ), 'value' => $fastcgi['available'] ? (string) $fastcgi['total'] : __( 'Observer chưa sẵn sàng', 'baocache' ) ),
			array( 'label' => __( 'FastCGI hit ratio (24h)', 'baocache' ), 'value' => $fastcgi['available'] && null !== $fastcgi['hit_ratio'] ? $fastcgi['hit_ratio'] . '%' : __( 'Chưa đủ dữ liệu', 'baocache' ) ),
		);
	}

	/**
	 * Report only configuration that BaoCache can deterministically inspect.
	 * This is deliberately not an LCP detector or a browser render verdict.
	 */
	private function critical_images_panel( array $settings ): void {
		$snapshot = BaoCache_Critical_Images::snapshot();
		$application = BaoCache_Critical_Images::application();
		$candidates = is_array( $snapshot['candidates'] ?? null ) ? $snapshot['candidates'] : array();
		$after = is_array( $application['after'] ?? null ) ? $application['after'] : array();
		$active = ! empty( $application['applied_at'] ) && empty( $application['rolled_back_at'] ) && (string) $settings['lcp_image'] === (string) ( $after['lcp_image'] ?? '' ) && (string) $settings['lcp_scope'] === (string) ( $after['lcp_scope'] ?? '' );
		$kind_labels = array( 'hero' => __( 'Hero candidate', 'baocache' ), 'slider' => __( 'Slider candidate', 'baocache' ), 'logo' => __( 'Logo candidate', 'baocache' ), 'first-image' => __( 'First image', 'baocache' ), 'image' => __( 'Image candidate', 'baocache' ) );
		?>
		<div class="baocache-panel__heading">
			<div><h2><?php esc_html_e( 'Automatic Critical Images', 'baocache' ); ?></h2><p><?php esc_html_e( 'Phân tích DOM trang chủ để xếp hạng ứng viên; confidence không phải kết luận LCP hay Core Web Vitals.', 'baocache' ); ?></p></div>
			<div class="baocache-critical-images__actions"><span class="baocache-badge is-<?php echo $active ? 'good' : 'neutral'; ?>"><?php echo esc_html( $active ? __( 'Applied & verified', 'baocache' ) : __( 'Evidence-driven', 'baocache' ) ); ?></span><button type="button" class="button button-secondary" data-baocache-scan-critical-images><?php esc_html_e( 'Quét trang chủ', 'baocache' ); ?></button><?php if ( $active ) : ?><button type="button" class="button button-secondary" data-baocache-rollback-critical-image><?php esc_html_e( 'Rollback', 'baocache' ); ?></button><?php endif; ?></div>
		</div>
		<?php if ( ! empty( $snapshot['scanned_at'] ) ) : ?><p class="baocache-critical-images__meta"><?php echo esc_html( sprintf( __( '%1$d ứng viên · quét %2$s · fingerprint %3$s', 'baocache' ), (int) ( $snapshot['candidate_count'] ?? count( $candidates ) ), wp_date( 'd/m/Y H:i', (int) $snapshot['scanned_at'] ), substr( (string) ( $snapshot['fingerprint'] ?? '—' ), 0, 12 ) ) ); ?></p><?php endif; ?>
		<?php if ( empty( $candidates ) ) : ?>
			<div class="baocache-empty baocache-critical-images__empty"><strong><?php esc_html_e( 'Chưa có critical image evidence', 'baocache' ); ?></strong><span><?php esc_html_e( 'Quét frontend công khai để tìm ảnh cùng website trong 20 vị trí đầu DOM. BaoCache không lưu HTML hoặc URL trang đã quét.', 'baocache' ); ?></span></div>
		<?php else : ?>
			<div class="baocache-critical-images__list">
				<?php foreach ( array_slice( $candidates, 0, 5 ) as $index => $candidate ) : ?>
					<?php $is_applied = $active && (string) ( $application['candidate_fingerprint'] ?? '' ) === (string) ( $candidate['fingerprint'] ?? '' ); ?>
					<article class="baocache-critical-image-card<?php echo $is_applied ? ' is-applied' : ''; ?>">
						<div class="baocache-critical-image-card__rank"><strong>#<?php echo esc_html( (string) ( $index + 1 ) ); ?></strong><span><?php echo esc_html( (string) (int) ( $candidate['confidence'] ?? 0 ) . '%' ); ?></span><small><?php esc_html_e( 'confidence', 'baocache' ); ?></small></div>
						<div class="baocache-critical-image-card__body"><div><span class="baocache-badge is-<?php echo 70 <= (int) ( $candidate['confidence'] ?? 0 ) ? 'good' : ( 45 <= (int) ( $candidate['confidence'] ?? 0 ) ? 'warn' : 'neutral' ); ?>"><?php echo esc_html( $kind_labels[ (string) ( $candidate['kind'] ?? 'image' ) ] ?? __( 'Image candidate', 'baocache' ) ); ?></span><?php if ( $is_applied ) : ?><span class="baocache-badge is-good"><?php esc_html_e( 'Active', 'baocache' ); ?></span><?php endif; ?></div><code><?php echo esc_html( (string) ( $candidate['path'] ?? '—' ) ); ?></code><small><?php echo esc_html( implode( ' · ', array_map( 'sanitize_text_field', (array) ( $candidate['reasons'] ?? array() ) ) ) ); ?></small></div>
						<div class="baocache-critical-image-card__facts"><span><small><?php esc_html_e( 'Dimensions', 'baocache' ); ?></small><strong><?php echo esc_html( ! empty( $candidate['has_dimensions'] ) ? (int) $candidate['width'] . '×' . (int) $candidate['height'] : 'Missing' ); ?></strong></span><span><small>loading</small><strong><?php echo esc_html( (string) ( $candidate['loading'] ?? 'default' ) ); ?></strong></span><span><small>fetchpriority</small><strong><?php echo esc_html( (string) ( $candidate['fetchpriority'] ?? 'default' ) ); ?></strong></span><span><small>srcset</small><strong><?php echo ! empty( $candidate['has_srcset'] ) ? 'Yes' : 'No'; ?></strong></span></div>
						<div class="baocache-critical-image-card__control"><?php if ( $is_applied ) : ?><strong class="is-verified"><?php esc_html_e( 'Public probe verified', 'baocache' ); ?></strong><?php elseif ( 20 <= (int) ( $candidate['confidence'] ?? 0 ) ) : ?><button type="button" class="button button-secondary" data-baocache-apply-critical-image="<?php echo esc_attr( (string) ( $candidate['fingerprint'] ?? '' ) ); ?>"><?php esc_html_e( 'Preview & apply', 'baocache' ); ?></button><?php else : ?><span class="baocache-badge is-neutral"><?php esc_html_e( 'Low confidence', 'baocache' ); ?></span><?php endif; ?></div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="baocache-callout is-neutral baocache-critical-images__guard"><strong><?php esc_html_e( 'Safety boundary', 'baocache' ); ?></strong><span><?php esc_html_e( 'Chỉ xử lý ảnh <img> cùng domain. BaoCache chưa tự sửa CSS background, tự tạo dimensions/srcset hoặc gọi một candidate là LCP. Apply phải PASS post-change probe; nếu không, thay đổi được rollback ngay.', 'baocache' ); ?></span></div>
		<?php
	}

	private function critical_resource_diagnostics( array $settings ): void {
		$preconnect = BaoCache_Settings::lines( (string) $settings['preconnect'] );
		$dns_prefetch = BaoCache_Settings::lines( (string) $settings['dns_prefetch'] );
		$preloads = BaoCache_Settings::lines( (string) $settings['preload'] );
		$unsupported_preloads = array_values( array_filter( $preloads, static fn( string $url ): bool => '' === BaoCache_Settings::preload_as( $url ) ) );
		$normalized_preconnect = array_map( static fn( string $url ): string => untrailingslashit( strtolower( $url ) ), $preconnect );
		$normalized_dns = array_map( static fn( string $url ): string => untrailingslashit( strtolower( $url ) ), $dns_prefetch );
		$redundant_hints = array_values( array_intersect( $normalized_preconnect, $normalized_dns ) );
		$lcp_image = trim( (string) $settings['lcp_image'] );
		$lcp_in_preloads = '' !== $lcp_image && in_array( untrailingslashit( strtolower( $lcp_image ) ), array_map( static fn( string $url ): string => untrailingslashit( strtolower( $url ) ), $preloads ), true );
		$emitted_preloads = count( $preloads ) - count( $unsupported_preloads ) + ( '' !== $lcp_image && ! $lcp_in_preloads ? 1 : 0 );
		$items = array(
			array(
				'state' => '' === $lcp_image ? 'neutral' : 'good',
				'status' => '' === $lcp_image ? __( 'Chưa cấu hình', 'baocache' ) : __( 'Configured', 'baocache' ),
				'title' => __( 'LCP assistance', 'baocache' ),
				'detail' => '' === $lcp_image
					? __( 'Chưa có URL ảnh được đo. BaoCache không tự đoán LCP.', 'baocache' )
					: sprintf( __( 'Ảnh được ưu tiên tại %s.', 'baocache' ), 'everywhere' === $settings['lcp_scope'] ? __( 'toàn frontend', 'baocache' ) : __( 'trang chủ', 'baocache' ) ),
			),
			array(
				'state' => empty( $unsupported_preloads ) ? 'good' : 'warn',
				'status' => empty( $unsupported_preloads ) ? __( 'Ready', 'baocache' ) : __( 'Cần sửa', 'baocache' ),
				'title' => __( 'Preload output', 'baocache' ),
				'detail' => empty( $unsupported_preloads )
					? sprintf( _n( '%d preload có thể xuất ra HTML.', '%d preload có thể xuất ra HTML.', $emitted_preloads, 'baocache' ), $emitted_preloads )
					: sprintf( _n( '%d URL không có loại tài nguyên hỗ trợ; BaoCache sẽ không xuất preload đó.', '%d URL không có loại tài nguyên hỗ trợ; BaoCache sẽ không xuất các preload đó.', count( $unsupported_preloads ), 'baocache' ), count( $unsupported_preloads ) ),
			),
			array(
				'state' => empty( $redundant_hints ) ? 'good' : 'warn',
				'status' => empty( $redundant_hints ) ? __( 'No overlap', 'baocache' ) : __( 'Rà soát', 'baocache' ),
				'title' => __( 'Connection hints', 'baocache' ),
				'detail' => empty( $redundant_hints )
					? sprintf( __( '%1$d preconnect · %2$d DNS prefetch.', 'baocache' ), count( $preconnect ), count( $dns_prefetch ) )
					: sprintf( _n( '%d origin vừa preconnect vừa DNS-prefetch; preconnect đã bao gồm DNS.', '%d origin vừa preconnect vừa DNS-prefetch; preconnect đã bao gồm DNS.', count( $redundant_hints ), 'baocache' ), count( $redundant_hints ) ),
			),
			array(
				'state' => $lcp_in_preloads ? 'neutral' : 'good',
				'status' => $lcp_in_preloads ? __( 'Deduplicated', 'baocache' ) : __( 'Clean', 'baocache' ),
				'title' => __( 'LCP preload', 'baocache' ),
				'detail' => '' === $lcp_image
					? __( 'Sẽ kiểm tra khi bạn thêm URL ảnh LCP đã đo.', 'baocache' )
					: ( $lcp_in_preloads ? __( 'URL LCP cũng nằm trong danh sách preload; BaoCache chỉ in một thẻ preload.', 'baocache' ) : __( 'BaoCache tự thêm một preload ảnh LCP khi URL xuất hiện ở phạm vi đã chọn.', 'baocache' ) ),
			),
		);
		?>
		<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Critical Resource Diagnostics', 'baocache' ); ?></h2><p><?php esc_html_e( 'Kiểm tra cấu hình BaoCache sẽ xuất ra HTML. Không đo LCP runtime, Core Web Vitals hay render-blocking.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php esc_html_e( 'Configuration only', 'baocache' ); ?></span></div>
		<div class="baocache-critical-resources__grid">
			<?php foreach ( $items as $item ) : ?>
				<div><span class="baocache-badge is-<?php echo esc_attr( $item['state'] ); ?>"><?php echo esc_html( $item['status'] ); ?></span><strong><?php echo esc_html( $item['title'] ); ?></strong><small><?php echo esc_html( $item['detail'] ); ?></small></div>
			<?php endforeach; ?>
		</div>
		<p class="baocache-analysis-note"><?php esc_html_e( 'Chỉ thêm preload sau khi đo đúng tài nguyên above-the-fold. Một URL không có phần mở rộng font, CSS, JavaScript hoặc ảnh hỗ trợ sẽ được giữ trong form để bạn sửa, nhưng không được xuất ra thẻ preload vô hiệu.', 'baocache' ); ?></p>
		<?php
	}

	private function analytics_adapters_panel( array $settings, array $status ): void {
		$events = array(
			'woocommerce' => 'view_item · add_to_cart · begin_checkout · purchase',
			'forms' => 'form_submit',
			'onesignal' => 'subscribe_push · unsubscribe_push',
			'power-schedule-manager' => 'search_schedule · select_area · view_schedule · subscribe_push',
		);
		?>
		<section class="baocache-analytics-adapters" aria-labelledby="baocache-adapters-title">
			<div class="baocache-panel__heading"><div><h3 id="baocache-adapters-title"><?php esc_html_e( 'Adapter integrations', 'baocache' ); ?></h3><p><?php esc_html_e( 'Chỉ bật adapter cho plugin đang dùng. Mỗi adapter chỉ đẩy event đã chuẩn hoá vào dataLayer sau consent; không gửi form data, email, URL hoặc dữ liệu khách về BaoCache.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php esc_html_e( 'Opt-in', 'baocache' ); ?></span></div>
			<div class="baocache-adapter-grid">
				<?php foreach ( (array) ( $status['adapters'] ?? array() ) as $key => $adapter ) : ?>
					<?php $available = ! empty( $adapter['available'] ); ?>
					<label class="baocache-adapter-card <?php echo $available ? '' : 'is-unavailable'; ?>">
						<span class="baocache-adapter-card__top"><strong><?php echo esc_html( (string) ( $adapter['label'] ?? $key ) ); ?></strong><span class="baocache-badge is-<?php echo $available ? ( ! empty( $adapter['enabled'] ) ? 'good' : 'neutral' ) : 'warning'; ?>"><?php echo esc_html( $available ? ( ! empty( $adapter['enabled'] ) ? __( 'Enabled', 'baocache' ) : __( 'Available', 'baocache' ) ) : __( 'Not detected', 'baocache' ) ); ?></span></span>
						<small><?php echo esc_html( $events[ $key ] ?? '' ); ?></small>
						<span class="baocache-adapter-card__control"><input type="checkbox" name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[analytics_adapters][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $adapter['enabled'] ) ); ?> <?php disabled( ! $available ); ?>><?php echo esc_html( $available ? __( 'Enable adapter', 'baocache' ) : __( 'Plugin chưa active', 'baocache' ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="baocache-analysis-note"><?php esc_html_e( 'Power Schedule Manager được phát hiện trực tiếp. WooCommerce và form dùng frontend completion hooks; OneSignal bên thứ ba cần phát event bridge sau khi SDK tự xác minh thay đổi subscription.', 'baocache' ); ?></p>
		</section>
		<?php
	}

	private function csp_panel( array $settings ): void {
		$suggested = BaoCache_CSP::suggested_sources( $settings );
		$reports = BaoCache_CSP::reports();
		$evidence_summary = BaoCache_CSP::export_evidence_summary();
		$evidence_review = get_option( 'baocache_csp_evidence_review', array() );
		$evidence_review = is_array( $evidence_review ) ? $evidence_review : array();
		$enforce_readiness = BaoCache_CSP::enforce_readiness( $settings );
		$enforce_ack = BaoCache_CSP::enforce_acknowledgement( $settings );
		$post_probe = BaoCache_CSP::post_enforcement_probe();
		$post_probe_history = BaoCache_CSP::post_enforcement_probe_history();
		$probe_regression = BaoCache_CSP::post_enforcement_probe_regression();
		$probe_ack = BaoCache_CSP::post_probe_acknowledgement();
		$probe_trend = BaoCache_CSP::post_enforcement_probe_trend();
		$probe_remediation = BaoCache_CSP::post_enforcement_remediation();
		$probe_ack_history = BaoCache_CSP::post_probe_acknowledgement_history();
		$remediation_state = BaoCache_CSP::remediation_state();
		$recommendations = BaoCache_CSP::recommendations( $settings );
		$applied_recommendations = BaoCache_CSP::applied_recommendations();
		$owner_observation = BaoCache_CSP::owner_observation();
		$policy_history = BaoCache_CSP::policy_history();
		$current_policy = BaoCache_CSP::policy_snapshot( $settings );
		$previous_policy = $policy_history[1] ?? null;
		$changed_directives = array();
		if ( is_array( $previous_policy ) ) {
			$all_directives = array_unique( array_merge( array_keys( (array) ( $previous_policy['directives'] ?? array() ) ), array_keys( (array) $current_policy['directives'] ) ) );
			foreach ( $all_directives as $directive ) {
				if ( (string) ( $previous_policy['directives'][ $directive ] ?? '' ) !== (string) ( $current_policy['directives'][ $directive ] ?? '' ) ) {
					$changed_directives[] = (string) $directive;
				}
			}
		}
		$fields = array(
			'script' => __( 'Script Sources', 'baocache' ),
			'style' => __( 'Style Sources', 'baocache' ),
			'img' => __( 'Image Sources', 'baocache' ),
			'font' => __( 'Font Sources', 'baocache' ),
			'connect' => __( 'Connect Sources', 'baocache' ),
			'frame' => __( 'Frame Sources', 'baocache' ),
			'worker' => __( 'Worker Sources', 'baocache' ),
		);
		?>
		<details class="baocache-panel baocache-panel--wide baocache-disclosure" data-baocache-pane="security">
			<summary class="baocache-disclosure__summary"><span><strong><?php esc_html_e( 'Security · Content Security Policy', 'baocache' ); ?><small><?php esc_html_e( 'Quản lý CSP tĩnh tương thích với FastCGI cache; Report-Only là mặc định an toàn.', 'baocache' ); ?></small></span><span class="baocache-disclosure__meta"><span class="baocache-badge is-<?php echo esc_attr( ! empty( $settings['csp_enabled'] ) ? 'good' : 'neutral' ); ?>"><?php echo esc_html( ! empty( $settings['csp_enabled'] ) ? ( 'enforce' === (string) $settings['csp_mode'] ? __( 'Enforced', 'baocache' ) : __( 'Report-Only', 'baocache' ) ) : __( 'Disabled', 'baocache' ) ); ?></span><span class="baocache-disclosure__chevron" aria-hidden="true">+</span></span></summary>
			<div class="baocache-disclosure__body">
				<nav class="baocache-csp-tabs" aria-label="<?php esc_attr_e( 'CSP workspace', 'baocache' ); ?>">
					<button type="button" class="is-active" data-baocache-csp-tab="basic"><?php esc_html_e( 'Basic', 'baocache' ); ?></button>
					<button type="button" data-baocache-csp-tab="sources"><?php esc_html_e( 'Sources', 'baocache' ); ?></button>
					<button type="button" data-baocache-csp-tab="evidence"><?php esc_html_e( 'Evidence', 'baocache' ); ?></button>
					<button type="button" data-baocache-csp-tab="diagnostics"><?php esc_html_e( 'Diagnostics', 'baocache' ); ?></button>
					<button type="button" data-baocache-csp-tab="advanced"><?php esc_html_e( 'Advanced', 'baocache' ); ?></button>
				</nav>
				<div class="baocache-csp-tab-pane is-active" data-baocache-csp-pane="basic">
				<?php $this->toggle( 'csp_enabled', __( 'Enable CSP', 'baocache' ), $settings ); ?>
				<label class="baocache-field"><span><?php esc_html_e( 'Mode', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[csp_mode]"><option value="report" <?php selected( $settings['csp_mode'], 'report' ); ?>><?php esc_html_e( 'Report Only · khuyến nghị khi triển khai', 'baocache' ); ?></option><option value="enforce" <?php selected( $settings['csp_mode'], 'enforce' ); ?>><?php esc_html_e( 'Enforce · chỉ sau khi đã xử lý report', 'baocache' ); ?></option></select></label>
				<?php $this->toggle( 'csp_collect_reports', __( 'Collect aggregate violation reports', 'baocache' ), $settings ); ?>
				<p class="baocache-analysis-note"><?php esc_html_e( 'Chỉ lưu directive, origin bị chặn đã rút gọn, disposition và số lần trong tối đa 30 ngày. Không lưu URL trang, path, query, referrer hoặc dữ liệu khách.', 'baocache' ); ?></p>
				<div class="baocache-callout is-warn"><strong><?php esc_html_e( 'Chỉ chọn một nơi phát CSP', 'baocache' ); ?></strong><span><?php esc_html_e( 'Nếu bật BaoCache, hãy tắt policy tương ứng ở Cloudflare/Nginx. Hai CSP header sẽ bị trình duyệt giao nhau và có thể làm hỏng frontend. BaoCache không dùng nonce vì HTML được FastCGI cache.', 'baocache' ); ?></span></div>
				<section class="baocache-csp-readiness" aria-labelledby="baocache-csp-readiness-title">
					<div class="baocache-panel__heading"><div><h3 id="baocache-csp-readiness-title"><?php esc_html_e( 'Staged Enforce readiness', 'baocache' ); ?></h3><p><?php echo esc_html( $enforce_readiness['detail'] ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( $enforce_readiness['state'] ); ?>"><?php echo esc_html( $enforce_readiness['title'] ); ?></span></div>
					<ul><?php foreach ( array( 'enabled' => __( 'CSP enabled', 'baocache' ), 'report_mode' => __( 'Report-Only mode', 'baocache' ), 'collecting' => __( 'Aggregate evidence collection', 'baocache' ), 'public_match' => __( 'Public header matches BaoCache', 'baocache' ), 'no_active_reports' => __( 'No active retained reports', 'baocache' ), 'no_conflict' => __( 'One public policy owner', 'baocache' ) ) as $key => $label ) : ?><?php $value = $enforce_readiness['checks'][ $key ] ?? null; ?><li><span class="baocache-badge is-<?php echo esc_attr( true === $value ? 'good' : ( false === $value ? 'warn' : 'neutral' ) ); ?>"><?php echo esc_html( true === $value ? __( 'PASS', 'baocache' ) : ( false === $value ? __( 'Review', 'baocache' ) : '—' ) ); ?></span><span><?php echo esc_html( $label ); ?></span></li><?php endforeach; ?></ul>
					<p class="baocache-analysis-note"><?php esc_html_e( 'Read-only: BaoCache không tự đổi mode. Chỉ người vận hành mới có thể chọn Enforce trong Mode và bấm Lưu cấu hình sau khi kiểm tra staging/public frontend.', 'baocache' ); ?></p>
				</section>
				<section class="baocache-csp-deployment" aria-labelledby="baocache-csp-deployment-title">
					<div class="baocache-panel__heading"><div><h3 id="baocache-csp-deployment-title"><?php esc_html_e( 'Enforce deployment checklist', 'baocache' ); ?></h3><p><?php esc_html_e( 'Checklist này không gọi Cloudflare/Nginx, không thay đổi policy và không thay thế kiểm thử staging.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( ! empty( $enforce_ack['matched'] ) ? 'good' : 'neutral' ); ?>"><?php echo esc_html( ! empty( $enforce_ack['matched'] ) ? __( 'Acknowledged', 'baocache' ) : __( 'Operator action required', 'baocache' ) ); ?></span></div>
					<ol><li><?php esc_html_e( 'Chạy Header Inspector trên URL public và chỉ giữ một CSP owner.', 'baocache' ); ?></li><li><?php esc_html_e( 'Kiểm tra menu, form, map, analytics/chat và trang đăng nhập trên staging khi không đăng nhập.', 'baocache' ); ?></li><li><?php esc_html_e( 'Rà soát toàn bộ Report-Only evidence còn active; không xem “không có report” là bảo đảm tương thích.', 'baocache' ); ?></li><li><?php esc_html_e( 'Chuẩn bị rollback: chọn lại Report-Only rồi lưu nếu frontend có lỗi.', 'baocache' ); ?></li></ol>
					<?php if ( ! empty( $settings['csp_enabled'] ) && 'report' === (string) $settings['csp_mode'] ) : ?>
						<label class="baocache-csp-enforce-ack"><input type="checkbox" name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[csp_enforce_ack]" value="1"> <span><strong><?php esc_html_e( 'Tôi đã hoàn tất checklist cho policy hiện tại và hiểu Enforce có thể chặn frontend.', 'baocache' ); ?></strong><small><?php esc_html_e( 'Checkbox chỉ dùng cho lần chuyển sang Enforce hiện tại. BaoCache sẽ từ chối lưu Enforce nếu Header Inspector/evidence chưa sẵn sàng.', 'baocache' ); ?></small></span></label>
					<?php elseif ( 'enforce' === (string) $settings['csp_mode'] ) : ?>
						<p class="baocache-analysis-note"><?php echo esc_html( ! empty( $enforce_ack['matched'] ) ? sprintf( __( 'Acknowledgement khớp fingerprint policy hiện tại · %s.', 'baocache' ), human_time_diff( (int) $enforce_ack['acknowledged_at'], time() ) . ' ' . __( 'trước', 'baocache' ) ) : __( 'Policy Enforce này chưa có acknowledgement khớp fingerprint hiện tại; giữ nguyên hoặc quay về Report-Only để rà soát.', 'baocache' ) ); ?></p>
					<?php endif; ?>
				</section>
				</div>
				<div class="baocache-csp-tab-pane" data-baocache-csp-pane="diagnostics">
				<section class="baocache-csp-post-probe" aria-labelledby="baocache-csp-post-probe-title">
					<div class="baocache-panel__heading"><div><h3 id="baocache-csp-post-probe-title"><?php esc_html_e( 'Post-enforcement public probe', 'baocache' ); ?></h3><p><?php esc_html_e( 'Kiểm tra response public hiện tại sau khi bật Enforce. Chỉ lưu HTTP, thời gian, mode và kết quả; không lưu policy hay response body.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( 'pass' === (string) ( $post_probe['outcome'] ?? '' ) ? 'good' : ( 'fail' === (string) ( $post_probe['outcome'] ?? '' ) ? 'bad' : ( ! empty( $post_probe['checked_at'] ) ? 'warn' : 'neutral' ) ) ); ?>"><?php echo esc_html( ! empty( $post_probe['checked_at'] ) ? strtoupper( (string) ( $post_probe['outcome'] ?? 'review' ) ) : __( 'Chưa kiểm tra', 'baocache' ) ); ?></span></div>
					<div class="baocache-csp-post-probe__actions"><button type="button" class="button button-secondary" data-baocache-csp-post-probe><?php esc_html_e( 'Kiểm tra public policy', 'baocache' ); ?></button><?php if ( 'enforce' === (string) $settings['csp_mode'] ) : ?><button type="button" class="button button-secondary" data-baocache-csp-manual-rollback><?php esc_html_e( 'Quay lại Report-Only', 'baocache' ); ?></button><?php endif; ?><output data-baocache-csp-post-probe-result aria-live="polite"></output></div>
					<?php if ( 'enforce' === (string) $settings['csp_mode'] ) : ?><div class="baocache-csp-canary-control"><?php $this->toggle( 'csp_canary_enabled', __( 'Scheduled canary · mỗi ngày', 'baocache' ), $settings ); ?><small><?php esc_html_e( 'Chỉ chạy khi bật Enforce và WordPress Cron hoạt động. Không tự rollback.', 'baocache' ); ?></small></div><?php endif; ?>
					<?php if ( ! empty( $post_probe['checked_at'] ) ) : ?><p class="baocache-analysis-note" data-baocache-csp-post-probe-meta><?php echo esc_html( sprintf( __( 'Lần gần nhất %1$s · HTTP %2$d · %3$d ms · mode %4$s · không lưu raw header.', 'baocache' ), human_time_diff( (int) $post_probe['checked_at'], time() ) . ' ' . __( 'trước', 'baocache' ), (int) ( $post_probe['status_code'] ?? 0 ), (int) ( $post_probe['response_ms'] ?? 0 ), (string) ( $post_probe['mode'] ?? 'none' ) ) ); ?></p><?php endif; ?>
					<?php if ( ! empty( $post_probe_history ) ) : ?><details class="baocache-csp-probe-history"><summary><?php echo esc_html( sprintf( _n( '%d probe trong 30 ngày', '%d probes trong 30 ngày', count( $post_probe_history ), 'baocache' ), count( $post_probe_history ) ) ); ?></summary><div><?php foreach ( array_slice( $post_probe_history, 0, 10 ) as $probe ) : ?><span><strong><?php echo esc_html( strtoupper( (string) ( $probe['outcome'] ?? 'warn' ) ) ); ?></strong><?php echo esc_html( sprintf( ' · %s · HTTP %d · %d ms', 'scheduled' === (string) ( $probe['source'] ?? '' ) ? __( 'Định kỳ', 'baocache' ) : __( 'Thủ công', 'baocache' ), (int) ( $probe['status_code'] ?? 0 ), (int) ( $probe['response_ms'] ?? 0 ) ) ); ?></span><?php endforeach; ?></div></details><?php endif; ?>
					<?php if ( ! empty( $probe_regression['available'] ) && ! empty( $probe_regression['regression'] ) ) : ?><div class="baocache-csp-probe-regression"><div><strong><?php esc_html_e( 'CSP probe regression', 'baocache' ); ?></strong><small><?php echo esc_html( ! empty( $probe_regression['repeated_failure'] ) ? __( 'Canary tiếp tục FAIL so với lần trước.', 'baocache' ) : __( 'Canary FAIL sau baseline PASS hoặc chưa có baseline.', 'baocache' ) ); ?></small><?php if ( ! empty( $probe_regression['changed'] ) ) : ?><small><?php echo esc_html( sprintf( __( 'Thay đổi: %s', 'baocache' ), implode( ' · ', array_map( 'sanitize_key', (array) $probe_regression['changed'] ) ) ) ); ?></small><?php endif; ?></div><?php if ( 'scheduled' === (string) ( $probe_regression['latest']['source'] ?? '' ) && 'fail' === (string) ( $probe_regression['latest']['outcome'] ?? '' ) ) : ?><?php if ( ! empty( $probe_ack['matched'] ) ) : ?><span class="baocache-badge is-warn"><?php esc_html_e( 'Đã xác nhận', 'baocache' ); ?></span><?php else : ?><button type="button" class="button button-secondary button-small" data-baocache-csp-probe-ack><?php esc_html_e( 'Xác nhận đã xem', 'baocache' ); ?></button><?php endif; ?><?php endif; ?></div><?php endif; ?>
					<section class="baocache-csp-trend" aria-labelledby="baocache-csp-trend-title"><div class="baocache-panel__heading"><div><h3 id="baocache-csp-trend-title"><?php esc_html_e( 'Canary trend · 7 scheduled checks', 'baocache' ); ?></h3><p><?php esc_html_e( 'Chỉ tổng hợp các lần canary đã chạy; không phải health score hay Core Web Vitals.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( ! empty( $probe_trend['available'] ) ? ( ! empty( $probe_trend['fail'] ) ? 'warn' : 'good' ) : 'neutral' ); ?>"><?php echo esc_html( ! empty( $probe_trend['available'] ) ? sprintf( __( '%d mẫu', 'baocache' ), (int) $probe_trend['window'] ) : __( 'Chưa có dữ liệu', 'baocache' ) ); ?></span></div><?php if ( empty( $probe_trend['available'] ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Bật Scheduled canary và chờ WordPress Cron chạy. BaoCache không tạo số liệu giả.', 'baocache' ); ?></p><?php else : ?><div class="baocache-csp-trend__stats"><span><strong><?php echo esc_html( (string) $probe_trend['pass'] ); ?></strong><?php esc_html_e( 'PASS', 'baocache' ); ?></span><span><strong><?php echo esc_html( (string) $probe_trend['warn'] ); ?></strong><?php esc_html_e( 'WARN', 'baocache' ); ?></span><span><strong><?php echo esc_html( (string) $probe_trend['fail'] ); ?></strong><?php esc_html_e( 'FAIL', 'baocache' ); ?></span><span><strong><?php echo esc_html( (string) $probe_trend['avg_response_ms'] ); ?> ms</strong><?php esc_html_e( 'Trung bình', 'baocache' ); ?></span><?php if ( ! empty( $probe_trend['failure_streak'] ) ) : ?><span class="is-failure"><strong><?php echo esc_html( (string) $probe_trend['failure_streak'] ); ?></strong><?php esc_html_e( 'FAIL liên tiếp', 'baocache' ); ?></span><?php endif; ?></div><?php endif; ?><?php if ( ! empty( $probe_ack_history ) ) : ?><details class="baocache-csp-ack-history"><summary><?php echo esc_html( sprintf( _n( '%d acknowledgement trong 30 ngày', '%d acknowledgements trong 30 ngày', count( $probe_ack_history ), 'baocache' ), count( $probe_ack_history ) ) ); ?></summary><div><?php foreach ( array_slice( $probe_ack_history, 0, 8 ) as $ack ) : ?><span><?php echo esc_html( sprintf( '%s · %s · %s', wp_date( 'd/m H:i', (int) ( $ack['acknowledged_at'] ?? 0 ) ), __( 'Operator đã xác nhận canary failure', 'baocache' ), '' !== (string) ( $ack['fingerprint'] ?? '' ) ? 'fp ' . substr( (string) $ack['fingerprint'], 0, 10 ) : 'fp —' ) ); ?></span><?php endforeach; ?></div></details><?php endif; ?></section>
					<section class="baocache-csp-remediation" aria-labelledby="baocache-csp-remediation-title"><div class="baocache-panel__heading"><div><h3 id="baocache-csp-remediation-title"><?php esc_html_e( 'Operator remediation checklist', 'baocache' ); ?></h3><p><?php esc_html_e( 'Các bước kiểm tra theo evidence hiện có. BaoCache không tự áp dụng, purge hoặc rollback.', 'baocache' ); ?></p></div></div><?php if ( empty( $probe_remediation ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có remediation nào được kích hoạt từ trend hiện tại.', 'baocache' ); ?></p><?php else : ?><?php if ( '' !== (string) ( $remediation_state['fingerprint'] ?? '' ) ) : ?><p class="baocache-analysis-note"><?php echo esc_html( sprintf( __( 'Ghi chú được gắn với trend fingerprint %s; sample mới sẽ tạo checklist context mới.', 'baocache' ), substr( (string) $remediation_state['fingerprint'], 0, 12 ) ) ); ?></p><?php endif; ?><ol><?php foreach ( $probe_remediation as $step ) : ?><?php $step_id = sanitize_key( (string) $step['id'] ); $step_state = is_array( $remediation_state['steps'][ $step_id ] ?? null ) ? $remediation_state['steps'][ $step_id ] : array(); ?><li><span class="baocache-badge is-<?php echo esc_attr( 'critical' === (string) $step['priority'] ? 'bad' : ( 'high' === (string) $step['priority'] ? 'warn' : 'neutral' ) ); ?>"><?php echo esc_html( strtoupper( (string) $step['priority'] ) ); ?></span><div><strong><?php echo esc_html( (string) $step['title'] ); ?></strong><small><?php echo esc_html( (string) $step['detail'] ); ?></small><div class="baocache-csp-remediation__controls"><label><input type="checkbox" data-baocache-csp-remediation-complete="<?php echo esc_attr( $step_id ); ?>" <?php checked( ! empty( $step_state['completed'] ) ); ?>> <?php esc_html_e( 'Đã hoàn tất', 'baocache' ); ?></label><input type="text" maxlength="300" data-baocache-csp-remediation-note="<?php echo esc_attr( $step_id ); ?>" value="<?php echo esc_attr( (string) ( $step_state['note'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Ghi chú ngắn (không nhập token/secret)', 'baocache' ); ?>"><button type="button" class="button button-secondary button-small" data-baocache-csp-remediation-save="<?php echo esc_attr( $step_id ); ?>"><?php esc_html_e( 'Lưu bước', 'baocache' ); ?></button></div></div></li><?php endforeach; ?></ol><?php endif; ?></section>
				</section>
				</div>
				<div class="baocache-csp-tab-pane" data-baocache-csp-pane="sources">
				<?php if ( empty( $owner_observation['checked_at'] ) ) : ?>
					<p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có public Header Inspector để đối chiếu CSP owner. BaoCache không tự đoán hay ghi đè policy tại Cloudflare/Nginx.', 'baocache' ); ?></p>
				<?php elseif ( empty( $owner_observation['present'] ) ) : ?>
					<p class="baocache-analysis-note"><?php echo esc_html( sprintf( __( 'Lần Header Inspector gần nhất (%s) không thấy CSP response header.', 'baocache' ), wp_date( 'd/m H:i', (int) $owner_observation['checked_at'] ) ) ); ?></p>
				<?php elseif ( ! empty( $owner_observation['matches_baocache'] ) ) : ?>
					<p class="baocache-analysis-note"><?php echo esc_html( sprintf( __( 'Lần Header Inspector gần nhất (%1$s) khớp policy BaoCache (%2$s). Vẫn giữ một owner duy nhất; BaoCache không sửa Cloudflare/Nginx.', 'baocache' ), wp_date( 'd/m H:i', (int) $owner_observation['checked_at'] ), (string) ( $owner_observation['mode'] ?? 'report-only' ) ) ); ?></p>
				<?php else : ?>
					<p class="baocache-analysis-note"><?php echo esc_html( sprintf( __( 'Lần Header Inspector gần nhất (%s) thấy CSP external/unknown. BaoCache chỉ báo trạng thái, không suy đoán owner và không ghi đè Cloudflare/Nginx.', 'baocache' ), wp_date( 'd/m H:i', (int) $owner_observation['checked_at'] ) ) ); ?></p>
				<?php endif; ?>
				<p class="baocache-analysis-note"><?php esc_html_e( 'Mỗi origin một dòng. BaoCache tự thêm origin theo Analytics/Clarity và adapter đang bật; danh sách dưới đây là phần bổ sung bạn kiểm soát.', 'baocache' ); ?></p>
				<div class="baocache-csp-source-grid">
				<?php foreach ( $fields as $key => $label ) : ?>
					<label class="baocache-field"><span><?php echo esc_html( $label ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[csp_<?php echo esc_attr( $key ); ?>_sources]" rows="3" spellcheck="false"><?php echo esc_textarea( (string) ( $settings[ 'csp_' . $key . '_sources' ] ?? '' ) ); ?></textarea><small><?php echo esc_html( ! empty( $suggested[ $key ] ) ? sprintf( __( 'Tự nhận: %s', 'baocache' ), implode( ' · ', array_diff( $suggested[ $key ], array( "'self'", 'data:' ) ) ) ?: __( 'self', 'baocache' ) ) : __( 'Chưa có origin tự nhận.', 'baocache' ) ); ?></small></label>
				<?php endforeach; ?>
				</div>
				<section class="baocache-csp-recommendations" aria-labelledby="baocache-csp-recommendations-title"><div class="baocache-panel__heading"><div><h3 id="baocache-csp-recommendations-title"><?php esc_html_e( 'Evidence-based source recommendations', 'baocache' ); ?></h3><p><?php esc_html_e( 'Chỉ đề xuất HTTPS origin đã lặp lại trong Report-Only. Không tự thêm, không bật Enforce và không đề xuất inline/eval.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php echo esc_html( sprintf( _n( '%d candidate', '%d candidates', count( $recommendations ), 'baocache' ), count( $recommendations ) ) ); ?></span></div><?php if ( empty( $recommendations ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có candidate đủ evidence. Cần ít nhất hai report, hoặc ba lần trong cùng thời điểm, trước khi BaoCache hiển thị nguồn.', 'baocache' ); ?></p><?php else : ?><div class="baocache-csp-recommendations__list"><?php foreach ( $recommendations as $recommendation ) : ?><article><div><code><?php echo esc_html( $recommendation['origin'] ); ?></code><small><?php echo esc_html( sprintf( __( '%1$s → %2$s · %3$d reports', 'baocache' ), $recommendation['directive'], $recommendation['field'] . '-src', $recommendation['count'] ) ); ?></small></div><div class="baocache-csp-recommendations__actions"><button type="button" class="button button-secondary button-small" data-baocache-apply-csp-recommendation="<?php echo esc_attr( $recommendation['id'] ); ?>"><?php esc_html_e( 'Thêm source', 'baocache' ); ?></button><button type="button" class="button-link" data-baocache-dismiss-csp-recommendation="<?php echo esc_attr( $recommendation['id'] ); ?>"><?php esc_html_e( 'Bỏ qua', 'baocache' ); ?></button></div></article><?php endforeach; ?></div><?php endif; ?></section>
				<section class="baocache-csp-applied" aria-labelledby="baocache-csp-applied-title"><div class="baocache-panel__heading"><div><h3 id="baocache-csp-applied-title"><?php esc_html_e( 'Applied source changes', 'baocache' ); ?></h3><p><?php esc_html_e( 'Rollback chỉ mở khi fingerprint và source list chưa đổi từ lần áp dụng. BaoCache không rollback CSP do Cloudflare/Nginx hoặc thao tác thủ công quản lý.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php echo esc_html( (string) count( $applied_recommendations ) ); ?></span></div><?php if ( empty( $applied_recommendations ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có recommendation nào do BaoCache áp dụng có thể rollback.', 'baocache' ); ?></p><?php else : ?><div class="baocache-csp-recommendations__list"><?php foreach ( $applied_recommendations as $record ) : ?><article><div><code><?php echo esc_html( (string) $record['origin'] ); ?></code><small><?php echo esc_html( sprintf( __( '%1$s-src · applied %2$s', 'baocache' ), (string) $record['field'], human_time_diff( (int) $record['applied_at'], time() ) . ' ' . __( 'trước', 'baocache' ) ) ); ?></small></div><div class="baocache-csp-recommendations__actions"><?php if ( ! empty( $record['stale'] ) ) : ?><span class="baocache-badge is-warn"><?php esc_html_e( 'Review required', 'baocache' ); ?></span><?php else : ?><button type="button" class="button button-secondary button-small" data-baocache-rollback-csp-recommendation="<?php echo esc_attr( (string) $record['id'] ); ?>"><?php esc_html_e( 'Rollback', 'baocache' ); ?></button><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?></section>
				</div>
				<div class="baocache-csp-tab-pane" data-baocache-csp-pane="evidence">
				<section class="baocache-csp-evidence" aria-labelledby="baocache-csp-evidence-title"><div class="baocache-panel__heading"><div><h3 id="baocache-csp-evidence-title"><?php esc_html_e( 'CSP violation evidence', 'baocache' ); ?></h3><p><?php esc_html_e( 'Aggregate-only evidence từ Report-Only; không phải danh sách URL khách truy cập.', 'baocache' ); ?></p></div><button type="button" class="button button-secondary button-small" data-baocache-clear-csp-reports <?php disabled( empty( $reports ) ); ?>><?php esc_html_e( 'Xóa evidence', 'baocache' ); ?></button></div><?php if ( empty( $settings['csp_collect_reports'] ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Đang tắt thu thập. Bật tùy chọn trên và giữ Report-Only để quan sát an toàn.', 'baocache' ); ?></p><?php elseif ( empty( $reports ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có violation report nào được gửi.', 'baocache' ); ?></p><?php else : ?><div class="baocache-csp-evidence__table"><table><thead><tr><th><?php esc_html_e( 'Directive', 'baocache' ); ?></th><th><?php esc_html_e( 'Blocked origin', 'baocache' ); ?></th><th><?php esc_html_e( 'Disposition', 'baocache' ); ?></th><th><?php esc_html_e( 'Count', 'baocache' ); ?></th><th><?php esc_html_e( 'Last seen', 'baocache' ); ?></th></tr></thead><tbody><?php foreach ( array_slice( $reports, 0, 12 ) as $report ) : ?><tr><td><code><?php echo esc_html( (string) ( $report['directive'] ?? 'unknown' ) ); ?></code></td><td><code><?php echo esc_html( (string) ( $report['blocked_origin'] ?? 'unknown' ) ); ?></code></td><td><?php echo esc_html( ucfirst( (string) ( $report['disposition'] ?? 'report' ) ) ); ?></td><td><?php echo esc_html( (string) (int) ( $report['count'] ?? 0 ) ); ?></td><td><?php echo esc_html( ! empty( $report['last_at'] ) ? wp_date( 'd/m H:i', (int) $report['last_at'] ) : '—' ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
				</div>
				<div class="baocache-csp-tab-pane" data-baocache-csp-pane="advanced">
				<section class="baocache-csp-diff" aria-labelledby="baocache-csp-diff-title"><div class="baocache-panel__heading"><div><h3 id="baocache-csp-diff-title"><?php esc_html_e( 'Policy fingerprint & diff', 'baocache' ); ?></h3><p><?php esc_html_e( 'So sánh directive giữa snapshot hiện tại và snapshot trước; không tự promote Enforce.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php echo esc_html( substr( (string) $current_policy['fingerprint'], 0, 12 ) ); ?></span></div><?php if ( ! is_array( $previous_policy ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Snapshot đầu tiên sẽ được lưu sau lần Save cấu hình CSP.', 'baocache' ); ?></p><?php elseif ( empty( $changed_directives ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Không có directive thay đổi so với snapshot trước.', 'baocache' ); ?></p><?php else : ?><p class="baocache-analysis-note"><?php echo esc_html( sprintf( __( 'Directive thay đổi: %s', 'baocache' ), implode( ' · ', $changed_directives ) ) ); ?></p><?php endif; ?></section>
				<section class="baocache-csp-retention" aria-labelledby="baocache-csp-retention-title">
					<div class="baocache-panel__heading"><div><h3 id="baocache-csp-retention-title"><?php esc_html_e( 'Evidence retention', 'baocache' ); ?></h3><p><?php esc_html_e( 'CSP reports và dismissal evidence tự hết hạn sau 30 ngày; ledger đã áp dụng được giữ tối đa 90 ngày và không tự sửa policy.', 'baocache' ); ?></p></div><div class="baocache-csp-retention__actions"><span class="baocache-badge is-neutral"><?php echo esc_html( sprintf( __( '%d groups', 'baocache' ), (int) $evidence_summary['active_report_groups'] ) ); ?></span><button type="button" class="button button-secondary button-small" data-baocache-review-csp-evidence><?php esc_html_e( 'Rà soát retention', 'baocache' ); ?></button></div></div>
					<p class="baocache-analysis-note"><?php echo esc_html( ! empty( $evidence_review['review_at'] ) ? sprintf( __( 'Rà soát gần nhất %1$s · đã dọn %2$d record. Export chỉ có metadata tổng hợp, không gồm blocked origin hoặc report thô.', 'baocache' ), human_time_diff( (int) $evidence_review['review_at'], time() ) . ' ' . __( 'trước', 'baocache' ), (int) $evidence_review['reports_removed'] + (int) $evidence_review['dismissals_removed'] + (int) $evidence_review['ledger_removed'] ) : __( 'Chưa có lần rà soát thủ công. Cron hằng ngày sẽ dọn evidence quá hạn khi WordPress Cron chạy.', 'baocache' ) ); ?></p>
				</section>
				</div>
			</div>
		</details>
		<?php
	}

	private function analytics_migration_panel( array $settings ): void {
		?>
		<section class="baocache-analytics-migration">
			<div class="baocache-panel__heading"><div><h3><?php esc_html_e( 'Analytics migration checklist', 'baocache' ); ?></h3><p><?php esc_html_e( 'Dùng khi public evidence còn phát hiện GTM hoặc GA4 ngoài BaoCache. Acknowledgement chỉ ghi nhận người vận hành đã xem, không xoá cảnh báo và không tự tắt tag.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( ! empty( $settings['analytics_duplicate_ack'] ) ? 'good' : 'neutral' ); ?>"><?php echo esc_html( ! empty( $settings['analytics_duplicate_ack'] ) ? __( 'Acknowledged', 'baocache' ) : __( 'Review when needed', 'baocache' ) ); ?></span></div>
			<div class="baocache-migration-list"><span>1. <?php esc_html_e( 'Chọn một GTM container làm nguồn canonical.', 'baocache' ); ?></span><span>2. <?php esc_html_e( 'Chuyển Google tag/GA4 page_view vào container đó.', 'baocache' ); ?></span><span>3. <?php esc_html_e( 'Tắt injector cũ trong Site Kit, plugin header hoặc theme.', 'baocache' ); ?></span><span>4. <?php esc_html_e( 'Chạy lại Analytics Inspector và kiểm tra GTM Preview.', 'baocache' ); ?></span></div>
			<?php $this->toggle( 'analytics_duplicate_ack', __( 'Tôi đã kiểm tra nguồn tag ngoài và hiểu acknowledgement không tự xoá tag.', 'baocache' ), $settings ); ?>
		</section>
		<?php
	}

	/** Read-only public injector results are rendered by the Analytics probe. */
	private function analytics_injector_panel(): void {
		?>
		<section class="baocache-analytics-injectors" aria-labelledby="baocache-injectors-title">
			<div class="baocache-panel__heading"><div><h3 id="baocache-injectors-title"><?php esc_html_e( 'Analytics Inspector', 'baocache' ); ?></h3><p><?php esc_html_e( 'Phát hiện Google Tag Gateway và injector ngoài BaoCache từ một HTML frontend cùng website. Không lưu HTML, không gọi vendor và không tắt injector.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php esc_html_e( 'Read-only', 'baocache' ); ?></span></div>
			<div class="baocache-injector-summary" data-baocache-injector-summary aria-live="polite"><span><strong>—</strong><?php esc_html_e( 'Canonical', 'baocache' ); ?></span><span><strong>—</strong><?php esc_html_e( 'Candidates', 'baocache' ); ?></span><span><strong>—</strong><?php esc_html_e( 'Potential duplicate', 'baocache' ); ?></span><span><strong>—</strong><?php esc_html_e( 'Actions', 'baocache' ); ?></span></div>
			<div class="baocache-injector-list" data-baocache-injector-list aria-live="polite"><p class="baocache-analysis-note"><?php esc_html_e( 'Bấm “Run Analytics Inspector” để phát hiện BaoCache, Google Tag Gateway, marker plugin và injector chưa rõ chủ sở hữu.', 'baocache' ); ?></p></div>
			<p class="baocache-analysis-note"><?php esc_html_e( 'Owner là suy luận có điều kiện từ marker công khai, không phải kết luận quyền quản trị. “Unknown” cần được kiểm tra trong theme, wp_head snippet hoặc dịch vụ edge trước khi thay đổi.', 'baocache' ); ?></p>
		</section>
		<?php
	}

	private function analytics_panel( array $settings ): void {
		$status = BaoCache_Analytics::status( $settings );
		$provider_label = match ( $status['type'] ) {
			'ga4' => __( 'Google Analytics 4', 'baocache' ),
			'gtm' => __( 'Google Tag Manager', 'baocache' ),
			'invalid' => __( 'ID không hợp lệ', 'baocache' ),
			default => __( 'Chưa cấu hình', 'baocache' ),
		};
		$provider_state = $status['enabled'] ? 'good' : ( 'invalid' === $status['type'] ? 'bad' : 'neutral' );
		$google_card_label = 'gtm' === $status['type'] ? __( 'Google Tag Manager', 'baocache' ) : __( 'Google Analytics', 'baocache' );
		$consent_label = match ( $status['consent'] ) {
			'denied' => __( 'Default denied', 'baocache' ),
			'granted' => __( 'Granted by your consent flow', 'baocache' ),
			default => __( 'Chưa cấu hình', 'baocache' ),
		};
		?>
		<div class="baocache-panel__heading baocache-analytics-heading"><div><h2><?php esc_html_e( 'Analytics & Tracking', 'baocache' ); ?></h2><p><?php esc_html_e( 'Kết nối Google Analytics, Google Tag Manager và Microsoft Clarity mà không cần chỉnh sửa theme.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( $provider_state ); ?>" data-baocache-analytics-provider><?php echo esc_html( $provider_label ); ?></span></div>
		<div class="baocache-analytics-status"><div><span data-baocache-analytics-google-label><?php echo esc_html( $google_card_label ); ?></span><strong><?php echo esc_html( $status['enabled'] ? __( 'Injected on public frontend', 'baocache' ) : __( 'Chưa cấu hình', 'baocache' ) ); ?></strong><small><?php esc_html_e( 'Chỉ xác nhận cấu hình BaoCache sẽ phát tag; không giả nhận Realtime từ Google.', 'baocache' ); ?></small></div><div><span><?php esc_html_e( 'Auto Events', 'baocache' ); ?></span><strong><?php echo esc_html( $status['auto_events'] ? __( 'Enabled after consent', 'baocache' ) : __( 'Disabled', 'baocache' ) ); ?></strong><small><?php esc_html_e( 'Các event được đưa vào dataLayer; không lưu event hoặc dữ liệu khách tại BaoCache.', 'baocache' ); ?></small></div><div><span><?php esc_html_e( 'Microsoft Clarity', 'baocache' ); ?></span><strong><?php echo esc_html( $status['clarity_enabled'] ? __( 'Injected on public frontend', 'baocache' ) : __( 'Chưa cấu hình', 'baocache' ) ); ?></strong><small><?php esc_html_e( 'Clarity Project ID là định danh public của script, không phải API token.', 'baocache' ); ?></small></div></div>
		<div class="baocache-analytics-fields"><section><h3><?php esc_html_e( 'Google Analytics hoặc Google Tag Manager', 'baocache' ); ?></h3><?php $this->toggle( 'analytics_enabled', __( 'Enable Google Analytics', 'baocache' ), $settings ); ?><label class="baocache-field"><span><?php esc_html_e( 'Google Analytics hoặc Google Tag Manager', 'baocache' ); ?></span><input data-baocache-analytics-id name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[analytics_id]" type="text" value="<?php echo esc_attr( (string) $settings['analytics_id'] ); ?>" placeholder="G-XXXXXXXXXX hoặc GTM-XXXXXXX" autocomplete="off" spellcheck="false" aria-describedby="baocache-analytics-id-help"><small id="baocache-analytics-id-help"><?php esc_html_e( 'Nhập một ID duy nhất. BaoCache tự nhận G- là Google Analytics 4 và GTM- là Google Tag Manager.', 'baocache' ); ?></small><output class="baocache-inline-validation" data-baocache-analytics-detected aria-live="polite"><?php echo esc_html( $status['type'] === 'ga4' ? __( 'Detected · Google Analytics 4', 'baocache' ) : ( $status['type'] === 'gtm' ? __( 'Detected · Google Tag Manager', 'baocache' ) : ( 'invalid' === $status['type'] ? __( 'Invalid Measurement ID', 'baocache' ) : '' ) ) ); ?></output></label><label class="baocache-field"><span><?php esc_html_e( 'Consent Mode', 'baocache' ); ?> <button type="button" class="baocache-help" aria-label="<?php esc_attr_e( 'Giải thích Consent Mode', 'baocache' ); ?>" title="<?php esc_attr_e( 'Nếu dùng CookieYes, Complianz hoặc Cookiebot, chọn External CMP.', 'baocache' ); ?>">?</button></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[analytics_consent_mode]"><option value="unset" <?php selected( $status['consent'], 'unset' ); ?>><?php esc_html_e( 'External CMP / chưa cấu hình', 'baocache' ); ?></option><option value="denied" <?php selected( $status['consent'], 'denied' ); ?>><?php esc_html_e( 'Default denied', 'baocache' ); ?></option><option value="granted" <?php selected( $status['consent'], 'granted' ); ?>><?php esc_html_e( 'Granted by your consent flow', 'baocache' ); ?></option></select><small><?php esc_html_e( 'Dùng External CMP nếu CookieYes, Complianz, Cookiebot hoặc banner khác quản lý consent. BaoCache không thay thế CMP.', 'baocache' ); ?></small></label><?php $this->toggle( 'analytics_auto_events', __( 'Auto Track WordPress Events', 'baocache' ), $settings ); ?><p class="baocache-analysis-note"><?php esc_html_e( 'Automatically pushes common WordPress events to the dataLayer. Chỉ chạy khi consent mode là denied hoặc granted.', 'baocache' ); ?></p><div class="baocache-event-list"><strong><?php esc_html_e( 'Events included', 'baocache' ); ?></strong><span>✓ search</span><span>✓ outbound_click</span><span>✓ mailto · tel</span><span>✓ file_download</span><span>✓ comment_submit</span><span>✓ scroll_90</span><span>✓ time_on_page_30</span><span>✓ 404</span><small><?php esc_html_e( 'WooCommerce, form, login/register, OneSignal và Power Schedule Manager sẽ dùng adapter riêng khi được kết nối; BaoCache không tự đoán event plugin.', 'baocache' ); ?></small></div></section><section><h3><?php esc_html_e( 'Microsoft Clarity', 'baocache' ); ?></h3><?php $this->toggle( 'clarity_enabled', __( 'Enable Microsoft Clarity', 'baocache' ), $settings ); ?><label class="baocache-field"><span><?php esc_html_e( 'Clarity Project ID', 'baocache' ); ?></span><input name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[clarity_project_id]" type="text" value="<?php echo esc_attr( (string) $settings['clarity_project_id'] ); ?>" placeholder="abc123def" autocomplete="off" spellcheck="false"><small><?php esc_html_e( 'BaoCache chỉ inject script Clarity khi bật và Project ID hợp lệ.', 'baocache' ); ?></small></label><div class="baocache-callout is-neutral"><strong><?php esc_html_e( 'Coming soon · OAuth integrations', 'baocache' ); ?></strong><span><?php esc_html_e( 'Search Console và Google Ads chưa kết nối OAuth/API trong beta này. Không hiển thị trạng thái “Connected” khi chưa có xác minh thực tế.', 'baocache' ); ?></span></div></section></div>
		<?php $this->analytics_adapters_panel( $settings, $status ); ?>
		<?php $this->analytics_migration_panel( $settings ); ?>
		<?php $this->analytics_injector_panel(); ?>
		<div class="baocache-analytics-tools"><section><div class="baocache-panel__heading"><div><h3><?php esc_html_e( 'Configuration & public diagnostics', 'baocache' ); ?></h3><p><?php esc_html_e( 'Kiểm tra cấu hình local, HTML public và CSP response header; không gọi API vendor hoặc giả nhận Realtime.', 'baocache' ); ?></p></div><button type="button" class="button button-secondary" data-baocache-analytics-test><?php esc_html_e( 'Run Analytics Inspector', 'baocache' ); ?></button></div><div class="baocache-analytics-test-result" data-baocache-analytics-test-result aria-live="polite"></div></section><section><div class="baocache-panel__heading"><div><h3><?php esc_html_e( 'Injected script preview', 'baocache' ); ?></h3><p><?php esc_html_e( 'BaoCache dùng wp_head() cho bootstrap local và wp_body_open() cho GTM noscript.', 'baocache' ); ?></p></div><div class="baocache-analytics-preview-actions"><a class="button button-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Mở frontend', 'baocache' ); ?></a><button type="button" class="button button-secondary" data-baocache-analytics-copy><?php esc_html_e( 'Copy preview', 'baocache' ); ?></button></div></div><pre class="baocache-analytics-preview" data-baocache-analytics-preview><?php echo esc_html( "<!-- BaoCache local deferred bootstrap -->\nwp_head() → baocache-analytics-bootstrap.js\n" . ( 'gtm' === $status['type'] ? "wp_body_open() → GTM noscript iframe\n" : '' ) . "External source is loaded only when the ID is valid and the public context is eligible." ); ?></pre></section></div>
		<div class="baocache-callout baocache-csp-callout is-neutral"><strong><?php esc_html_e( 'CSP ở Cloudflare', 'baocache' ); ?></strong><span><?php esc_html_e( 'Vào Cloudflare → Rules → Transform Rules → Modify Response Header. Cho phép www.googletagmanager.com và www.clarity.ms trong script-src-elem (hoặc script-src); thêm www.googletagmanager.com vào frame-src cho GTM noscript. Không thêm CSP thứ hai vào nginx/default.conf.', 'baocache' ); ?></span></div>
		<?php
	}

	private function asset_inventory(): void {
		$inventory = get_transient( 'baocache_asset_inventory' );
		$assets = is_array( $inventory['assets'] ?? null ) ? $inventory['assets'] : array();
		$groups = $this->asset_groups( $assets );
		$scripts = count( array_filter( $assets, static fn( array $asset ): bool => 'script' === ( $asset['type'] ?? '' ) ) );
		$styles = count( array_filter( $assets, static fn( array $asset ): bool => 'style' === ( $asset['type'] ?? '' ) ) );
		$settings = BaoCache_Settings::get();
		$deferred = count( array_filter( $assets, static fn( array $asset ): bool => 'script' === ( $asset['type'] ?? '' ) && in_array( (string) ( $asset['handle'] ?? '' ), BaoCache_Settings::lines( (string) $settings['defer_handles'] ), true ) ) );
		$delay_requested = count( array_filter( $assets, static fn( array $asset ): bool => 'script' === ( $asset['type'] ?? '' ) && in_array( (string) ( $asset['handle'] ?? '' ), BaoCache_Settings::lines( (string) $settings['delay_handles'] ), true ) ) );
		$insights = $this->asset_insights( $assets, $settings, 4 <= (int) ( $inventory['schema'] ?? 0 ) );
		?>
		<div class="baocache-panel__heading baocache-assets-heading"><div><h2><?php esc_html_e( 'Asset Explorer', 'baocache' ); ?></h2><p><?php esc_html_e( 'Inventory thực từ một frontend request không đăng nhập; chỉ hiển thị dữ liệu BaoCache đã xác minh.', 'baocache' ); ?></p></div><div class="baocache-assets-heading__actions"><button type="button" class="button button-primary" data-baocache-scan-assets><?php esc_html_e( 'Quét assets', 'baocache' ); ?></button><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION ), self::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Xuất JSON', 'baocache' ); ?></a></div></div>
		<?php if ( ! empty( $inventory['scan_verified'] ) ) : ?><p class="baocache-inventory-meta"><?php esc_html_e( 'Đã xác minh qua Nginx nội bộ. Phạm vi hiện tại là một URL frontend mẫu, không phải toàn site.', 'baocache' ); ?></p><?php endif; ?>
		<div class="baocache-asset-stats"><span><strong data-baocache-asset-total><?php echo esc_html( (string) count( $assets ) ); ?></strong><?php esc_html_e( 'Assets', 'baocache' ); ?></span><span><strong><?php echo esc_html( (string) $scripts ); ?></strong><?php esc_html_e( 'Scripts', 'baocache' ); ?></span><span><strong><?php echo esc_html( (string) $styles ); ?></strong><?php esc_html_e( 'Styles', 'baocache' ); ?></span><span><strong><?php echo esc_html( (string) $deferred ); ?></strong><?php esc_html_e( 'Deferred', 'baocache' ); ?></span><span><strong><?php echo esc_html( (string) $delay_requested ); ?></strong><?php esc_html_e( 'Delay requested', 'baocache' ); ?></span><small><?php echo esc_html( ! empty( $inventory['captured_at'] ) ? sprintf( __( 'Cập nhật %s', 'baocache' ), human_time_diff( (int) $inventory['captured_at'], time() ) . ' ' . __( 'trước', 'baocache' ) ) : __( 'Chưa quét', 'baocache' ) ); ?></small></div>
		<nav class="baocache-assets-tabs" aria-label="<?php esc_attr_e( 'Asset Explorer', 'baocache' ); ?>"><button type="button" class="is-active" data-baocache-assets-tab="inventory"><?php esc_html_e( 'Inventory', 'baocache' ); ?></button><button type="button" data-baocache-assets-tab="rules"><?php esc_html_e( 'Rules', 'baocache' ); ?></button><button type="button" data-baocache-assets-tab="dependencies"><?php esc_html_e( 'Dependencies', 'baocache' ); ?></button><button type="button" data-baocache-assets-tab="analysis"><?php esc_html_e( 'Analysis', 'baocache' ); ?></button></nav>
		<section data-baocache-assets-pane="inventory">
			<?php if ( empty( $assets ) ) : ?>
				<div class="baocache-asset-empty"><span class="dashicons dashicons-media-code"></span><h3><?php esc_html_e( 'Chưa có Asset Inventory', 'baocache' ); ?></h3><p><?php esc_html_e( 'Quét frontend để phát hiện CSS và JavaScript thực sự được WordPress enqueue.', 'baocache' ); ?></p><button type="button" class="button button-primary" data-baocache-scan-assets><?php esc_html_e( 'Quét assets', 'baocache' ); ?></button></div>
			<?php else : ?>
				<div class="baocache-asset-toolbar"><label><span class="screen-reader-text"><?php esc_html_e( 'Tìm asset', 'baocache' ); ?></span><input type="search" data-baocache-asset-search placeholder="<?php esc_attr_e( 'Tìm handle, nguồn hoặc URL…', 'baocache' ); ?>"></label><select data-baocache-asset-type><option value="all"><?php esc_html_e( 'Tất cả loại', 'baocache' ); ?></option><option value="script"><?php esc_html_e( 'Scripts', 'baocache' ); ?></option><option value="style"><?php esc_html_e( 'Styles', 'baocache' ); ?></option></select><select data-baocache-asset-source><option value="all"><?php esc_html_e( 'Tất cả nguồn', 'baocache' ); ?></option><?php foreach ( $groups as $group_key => $group ) : ?><option value="<?php echo esc_attr( $group_key ); ?>"><?php echo esc_html( $group['label'] ); ?></option><?php endforeach; ?></select></div>
				<div class="baocache-asset-groups">
					<?php foreach ( $groups as $group_key => $group ) : ?>
						<section class="baocache-asset-group" data-baocache-asset-group="<?php echo esc_attr( $group_key ); ?>"><header><h3><?php echo esc_html( $group['label'] ); ?></h3><span><?php echo esc_html( sprintf( _n( '%d asset', '%d assets', count( $group['assets'] ), 'baocache' ), count( $group['assets'] ) ) ); ?></span></header><div class="baocache-inventory"><table><thead><tr><th><?php esc_html_e( 'Type', 'baocache' ); ?></th><th><?php esc_html_e( 'Handle', 'baocache' ); ?></th><th><?php esc_html_e( 'Size', 'baocache' ); ?></th><th><?php esc_html_e( 'Dependencies', 'baocache' ); ?></th><th><?php esc_html_e( 'Loaded on', 'baocache' ); ?></th><th><?php esc_html_e( 'Source', 'baocache' ); ?></th><th><?php esc_html_e( 'Status', 'baocache' ); ?></th><th><?php esc_html_e( 'Action', 'baocache' ); ?></th></tr></thead><tbody><?php foreach ( $group['assets'] as $asset ) : ?><?php $status = $this->asset_status( $asset, $settings ); $source = $this->asset_source( (string) $asset['source'] ); $size = isset( $asset['size_bytes'] ) && is_int( $asset['size_bytes'] ) ? BaoCache_Diagnostics::bytes( $asset['size_bytes'] ) : '—'; ?><tr data-baocache-asset-row data-baocache-type="<?php echo esc_attr( (string) $asset['type'] ); ?>" data-baocache-source="<?php echo esc_attr( $group_key ); ?>" data-baocache-search="<?php echo esc_attr( strtolower( implode( ' ', array( (string) $asset['handle'], (string) $asset['source'], implode( ' ', (array) $asset['dependencies'] ) ) ) ) ); ?>"><td><span class="baocache-type"><?php echo esc_html( strtoupper( (string) $asset['type'] ) ); ?></span></td><td><code><?php echo esc_html( (string) $asset['handle'] ); ?></code><small><?php echo esc_html( basename( (string) $asset['source'] ) ); ?></small></td><td><?php echo esc_html( $size ); ?></td><td><?php echo esc_html( implode( ', ', (array) $asset['dependencies'] ) ?: '—' ); ?></td><td><small><?php echo esc_html( sprintf( __( 'Mẫu: %s', 'baocache' ), (string) ( $asset['path'] ?? '/' ) ) ); ?></small></td><td><small><?php echo esc_html( $source['label'] ); ?></small></td><td><span class="baocache-badge is-<?php echo esc_attr( $status['state'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span></td><td><button type="button" class="button-link" data-baocache-create-rule data-baocache-asset-handle="<?php echo esc_attr( (string) $asset['handle'] ); ?>" data-baocache-asset-type="<?php echo esc_attr( (string) $asset['type'] ); ?>" data-baocache-asset-path="<?php echo esc_attr( (string) ( $asset['path'] ?? '/' ) ); ?>"><?php esc_html_e( 'Tạo rule', 'baocache' ); ?></button></td></tr><?php endforeach; ?></tbody></table></div></section>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<section data-baocache-assets-pane="dependencies" class="is-hidden"><?php $this->dependency_graph( $assets ); ?></section>
		<section data-baocache-assets-pane="analysis" class="is-hidden"><div class="baocache-assets-analysis"><article><span><?php esc_html_e( 'Scripts', 'baocache' ); ?></span><strong><?php echo esc_html( (string) $scripts ); ?></strong></article><article><span><?php esc_html_e( 'Styles', 'baocache' ); ?></span><strong><?php echo esc_html( (string) $styles ); ?></strong></article><article><span><?php esc_html_e( 'Largest local asset', 'baocache' ); ?></span><strong><?php echo esc_html( $insights['largest']['size'] ?? '—' ); ?></strong><small><?php echo esc_html( $insights['largest']['handle'] ?? __( 'Chưa có size local', 'baocache' ) ); ?></small></article><article><span><?php esc_html_e( 'Third-party assets', 'baocache' ); ?></span><strong><?php echo esc_html( (string) $insights['external_count'] ); ?></strong><small><?php echo esc_html( sprintf( __( '%d nguồn ngoài', 'baocache' ), $insights['external_sources'] ) ); ?></small></article><article><span><?php esc_html_e( 'Duplicate sources', 'baocache' ); ?></span><strong><?php echo esc_html( (string) $insights['duplicate_count'] ); ?></strong><small><?php esc_html_e( 'Cần rà soát', 'baocache' ); ?></small></article><article><span><?php esc_html_e( 'Head script candidates', 'baocache' ); ?></span><strong><?php echo esc_html( null === $insights['head_scripts'] ? '—' : (string) $insights['head_scripts'] ); ?></strong><small><?php echo esc_html( null === $insights['head_scripts'] ? __( 'Quét lại Inventory', 'baocache' ) : __( 'Không phải render-blocking', 'baocache' ) ); ?></small></article></div><?php if ( empty( $insights['items'] ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có insight cần rà soát trong mẫu hiện tại. Đây vẫn chỉ là một frontend sample, không phải kết luận cho toàn site.', 'baocache' ); ?></p><?php else : ?><div class="baocache-insights"><h3><?php esc_html_e( 'Measured insights', 'baocache' ); ?></h3><p><?php esc_html_e( 'Một click chỉ tạo nháp; BaoCache không tự lưu hay tự unload/defer asset.', 'baocache' ); ?></p><ol><?php foreach ( $insights['items'] as $item ) : ?><li><span class="baocache-badge is-<?php echo esc_attr( $item['state'] ); ?>"><?php echo esc_html( $item['label'] ); ?></span><strong><?php echo esc_html( $item['title'] ); ?></strong><span><?php echo esc_html( $item['detail'] ); ?></span><?php if ( 'rule' === ( $item['action'] ?? '' ) ) : ?><button type="button" class="button-link" data-baocache-create-rule data-baocache-asset-handle="<?php echo esc_attr( (string) $item['handle'] ); ?>" data-baocache-asset-type="<?php echo esc_attr( (string) $item['type'] ); ?>" data-baocache-asset-path="<?php echo esc_attr( (string) $item['path'] ); ?>"><?php esc_html_e( 'Tạo rule nháp', 'baocache' ); ?></button><?php elseif ( 'defer' === ( $item['action'] ?? '' ) ) : ?><button type="button" class="button-link" data-baocache-suggest-defer="<?php echo esc_attr( (string) $item['handle'] ); ?>"><?php esc_html_e( 'Thêm defer nháp', 'baocache' ); ?></button><?php else : ?><button type="button" class="button-link" data-baocache-go="assets"><?php esc_html_e( 'Mở Inventory', 'baocache' ); ?></button><?php endif; ?></li><?php endforeach; ?></ol></div><?php endif; ?><p class="baocache-analysis-note"><?php esc_html_e( 'Size là dung lượng file local trên disk, không phải transfer size sau nén. Head script là tín hiệu vị trí enqueue, không phải kết luận render-blocking. Waterfall và “unused” vẫn cần Resource Timing từ trình duyệt hoặc Chromium worker.', 'baocache' ); ?></p></section>
		<?php $this->render_blocking_panel( $assets ); ?>
		<section class="baocache-panel baocache-browser-timing is-hidden" data-baocache-assets-pane="analysis"><?php $this->frontend_timing_panel( $settings ); ?></section>
		<section class="baocache-preview is-hidden" data-baocache-assets-pane="rules"><h3><?php esc_html_e( 'Rule Preview', 'baocache' ); ?></h3><p><?php esc_html_e( 'Kiểm tra dependency và ngữ cảnh từ mẫu frontend trước khi thêm rule gỡ asset. Điều kiện shortcode/block được xác minh lúc tải trang.', 'baocache' ); ?></p><div><select data-baocache-preview-type><option value="script">JavaScript</option><option value="style">CSS</option></select><input type="text" data-baocache-preview-handle placeholder="handle"><select data-baocache-preview-scope><option value="everywhere">Toàn frontend</option><option value="front-page">Trang chủ</option><option value="url-prefix">URL prefix</option><option value="missing-shortcode">Không có shortcode</option><option value="missing-block">Không có block</option></select><input type="text" data-baocache-preview-value placeholder="/duong-dan/, shortcode hoặc block"><button type="button" class="button button-secondary" data-baocache-preview><?php esc_html_e( 'Xem trước', 'baocache' ); ?></button></div><output data-baocache-preview-result></output></section>
			<?php
	}

	private function render_blocking_panel( array $assets ): void {
		$settings = BaoCache_Settings::get();
		$audit = BaoCache_Render_Blocking::audit();
		$snapshots = is_array( $audit['snapshots'] ?? null ) ? $audit['snapshots'] : array();
		$after = is_array( $snapshots['after'] ?? null ) ? $snapshots['after'] : array();
		$comparison = is_array( $audit['comparison'] ?? null ) ? $audit['comparison'] : array();
		$metrics = is_array( $after['metrics'] ?? null ) ? $after['metrics'] : array();
		$resources = is_array( $after['items'] ?? null ) ? $after['items'] : array();
		$critical = BaoCache_Render_Blocking::critical_css();
		$critical_valid = '' !== BaoCache_Render_Blocking::validated_critical_css();
		$ledger = BaoCache_Render_Blocking::strategy_log();
		$inventory_handles = array_values( array_filter( array_map( static fn( mixed $asset ): string => is_array( $asset ) ? sanitize_key( (string) ( $asset['handle'] ?? '' ) ) : '', $assets ) ) );
		$strategy_handles = array(
			'defer' => BaoCache_Settings::lines( (string) ( $settings['defer_handles'] ?? '' ) ),
			'async-css' => BaoCache_Settings::lines( (string) ( $settings['async_style_handles'] ?? '' ) ),
			'delay' => BaoCache_Settings::lines( (string) ( $settings['delay_handles'] ?? '' ) ),
		);
		$configured_handles = array_values( array_unique( array_merge( $strategy_handles['defer'], $strategy_handles['async-css'], $strategy_handles['delay'] ) ) );
		$gate_rows = array();
		foreach ( $strategy_handles as $strategy => $handles ) {
			foreach ( $handles as $handle ) {
				$gate = BaoCache_Render_Blocking::compatibility_gate( $handle, $strategy );
				$gate['history'] = BaoCache_Render_Blocking::gate_history( $handle, $strategy, 12 );
				$gate_rows[] = $gate;
			}
		}
		$stale_handles = array_values( array_diff( $configured_handles, $inventory_handles ) );
		$gate_review = BaoCache_Render_Blocking::gate_review();
		?>
		<section class="baocache-panel baocache-render-blocking" data-baocache-assets-pane="analysis">
			<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Render Blocking Optimization', 'baocache' ); ?></h2><p><?php esc_html_e( 'Nhập JSON từ PageSpeed/Lighthouse để xác định CSS/JS render-blocking và map về handle WordPress khi có thể.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( $critical_valid ? 'good' : 'neutral' ); ?>"><?php echo esc_html( $critical_valid ? __( 'Critical CSS validated', 'baocache' ) : __( 'Audit-driven', 'baocache' ) ); ?></span></div>
			<div class="baocache-render-blocking__import"><textarea data-baocache-render-blocking-json rows="6" placeholder="Dán Lighthouse JSON…"></textarea><div><label><span><?php esc_html_e( 'Loại snapshot', 'baocache' ); ?></span><select data-baocache-render-blocking-snapshot><option value="after"><?php esc_html_e( 'After / hiện tại', 'baocache' ); ?></option><option value="baseline"><?php esc_html_e( 'Baseline / trước tối ưu', 'baocache' ); ?></option></select><button type="button" class="button button-primary" data-baocache-render-blocking-import><?php esc_html_e( 'Nhập audit', 'baocache' ); ?></button></label><output data-baocache-render-blocking-result></output></div></div>
			<div class="baocache-context-qa"><div><strong><?php esc_html_e( 'Context QA', 'baocache' ); ?></strong><small><?php esc_html_e( 'Kiểm tra một rule trước khi áp dụng. BaoCache luôn bỏ qua admin, login, preview, checkout và phiên đăng nhập.', 'baocache' ); ?></small></div><div class="baocache-context-qa__fields"><label><span><?php esc_html_e( 'Path', 'baocache' ); ?></span><input type="text" data-baocache-context-path value="/" placeholder="/lich-dien/"></label><label><span><?php esc_html_e( 'Handle (tuỳ chọn)', 'baocache' ); ?></span><input type="text" data-baocache-context-handle placeholder="theme-script"></label><label class="baocache-context-qa__check"><input type="checkbox" data-baocache-context-logged-in> <?php esc_html_e( 'Đã đăng nhập', 'baocache' ); ?></label><label class="baocache-context-qa__check"><input type="checkbox" data-baocache-context-preview> <?php esc_html_e( 'Preview', 'baocache' ); ?></label><label class="baocache-context-qa__check"><input type="checkbox" data-baocache-context-checkout> <?php esc_html_e( 'Checkout', 'baocache' ); ?></label><button type="button" class="button button-secondary" data-baocache-context-qa><?php esc_html_e( 'Kiểm tra context', 'baocache' ); ?></button></div><output data-baocache-context-result></output></div>
			<div class="baocache-critical-css-stage"><div><strong><?php esc_html_e( 'Critical CSS staging', 'baocache' ); ?></strong><small><?php esc_html_e( 'Chỉ inline sau khi CSS được validate và fingerprint theme/plugin hiện tại khớp.', 'baocache' ); ?></small></div><textarea data-baocache-critical-css rows="4" placeholder="/* CSS được tạo bởi worker/CI */"></textarea><div class="baocache-critical-css-stage__controls"><select data-baocache-critical-template><option value="front-page"><?php esc_html_e( 'Trang chủ', 'baocache' ); ?></option><option value="page"><?php esc_html_e( 'Page template', 'baocache' ); ?></option><option value="post"><?php esc_html_e( 'Post template', 'baocache' ); ?></option><option value="archive"><?php esc_html_e( 'Archive', 'baocache' ); ?></option><option value="everywhere"><?php esc_html_e( 'Toàn frontend', 'baocache' ); ?></option></select><select data-baocache-critical-viewport><option value="desktop">Desktop</option><option value="tablet">Tablet</option><option value="mobile">Mobile</option></select><button type="button" class="button button-secondary" data-baocache-stage-critical-css><?php esc_html_e( 'Validate & stage', 'baocache' ); ?></button><?php if ( ! empty( $critical ) ) : ?><button type="button" class="button button-secondary" data-baocache-rollback-critical-css><?php esc_html_e( 'Rollback', 'baocache' ); ?></button><?php endif; ?><output data-baocache-critical-css-result></output></div></div>
			<?php if ( ! empty( $metrics ) || ! empty( $resources ) ) : ?>
				<div class="baocache-render-blocking__summary"><article><span><?php esc_html_e( 'FCP', 'baocache' ); ?></span><strong><?php echo esc_html( isset( $metrics['fcp'] ) ? number_format_i18n( (float) $metrics['fcp'], 0 ) . ' ms' : '—' ); ?></strong></article><article><span><?php esc_html_e( 'LCP', 'baocache' ); ?></span><strong><?php echo esc_html( isset( $metrics['lcp'] ) ? number_format_i18n( (float) $metrics['lcp'], 0 ) . ' ms' : '—' ); ?></strong></article><article><span><?php esc_html_e( 'CLS', 'baocache' ); ?></span><strong><?php echo esc_html( isset( $metrics['cls'] ) ? number_format_i18n( (float) $metrics['cls'], 2 ) : '—' ); ?></strong></article><article><span><?php esc_html_e( 'TBT', 'baocache' ); ?></span><strong><?php echo esc_html( isset( $metrics['tbt'] ) ? number_format_i18n( (float) $metrics['tbt'], 0 ) . ' ms' : '—' ); ?></strong></article><article><span><?php esc_html_e( 'Render-blocking', 'baocache' ); ?></span><strong><?php echo esc_html( (string) count( $resources ) ); ?></strong></article><?php if ( isset( $comparison['estimated_savings_ms'] ) ) : ?><article><span><?php esc_html_e( 'Estimated savings', 'baocache' ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) $comparison['estimated_savings_ms'], 0 ) . ' ms' ); ?></strong></article><?php endif; ?></div>
				<div class="baocache-inventory"><table><thead><tr><th><?php esc_html_e( 'Resource', 'baocache' ); ?></th><th><?php esc_html_e( 'Handle', 'baocache' ); ?></th><th><?php esc_html_e( 'Wasted', 'baocache' ); ?></th><th><?php esc_html_e( 'Strategy', 'baocache' ); ?></th></tr></thead><tbody><?php foreach ( $resources as $resource ) : ?><tr><td><code><?php echo esc_html( (string) ( $resource['url'] ?? '—' ) ); ?></code><small><?php echo esc_html( (string) ( $resource['type'] ?? 'other' ) ); ?></small></td><td><?php if ( ! empty( $resource['handle'] ) ) : ?><code><?php echo esc_html( (string) $resource['handle'] ); ?></code><button type="button" class="button-link" data-baocache-preview-render-blocking="<?php echo esc_attr( (string) $resource['handle'] ); ?>"><?php esc_html_e( 'Preview', 'baocache' ); ?></button><?php else : ?><span>—</span><?php endif; ?></td><td><?php echo esc_html( number_format_i18n( (float) ( $resource['wasted_ms'] ?? 0 ), 0 ) . ' ms' ); ?></td><td><?php echo esc_html( ! empty( $resource['mapped'] ) ? __( 'Handle-aware', 'baocache' ) : __( 'URL only · không tự sửa', 'baocache' ) ); ?></td></tr><?php endforeach; ?></tbody></table></div>
				<?php if ( ! empty( $snapshots['baseline'] ) && ! empty( $snapshots['after'] ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Delta là chênh lệch giữa hai báo cáo bạn nhập; không phải kết quả tự đo của BaoCache.', 'baocache' ); ?></p><?php endif; ?>
			<?php else : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có Lighthouse audit. BaoCache không tự gọi PageSpeed và không tự gắn nhãn render-blocking từ vị trí enqueue.', 'baocache' ); ?></p><?php endif; ?>
			<p class="baocache-analysis-note"><?php echo esc_html( ! empty( $critical ) ? ( $critical_valid ? __( 'Critical CSS đã được validate, fingerprint khớp theme/plugin hiện tại và mới được phép inline.', 'baocache' ) : __( 'Critical CSS có bản ghi nhưng chưa hợp lệ hoặc fingerprint đã thay đổi; BaoCache không inline.', 'baocache' ) ) : __( 'Critical CSS architecture sẵn sàng cho worker tạo/validate; chưa có CSS được phép inline.', 'baocache' ) ); ?></p>
			<?php if ( ! empty( $stale_handles ) ) : ?><div class="baocache-callout is-warning"><strong><?php esc_html_e( 'Strategy cần rà soát', 'baocache' ); ?></strong><span><?php echo esc_html( sprintf( _n( '%d handle trong cấu hình không xuất hiện ở Inventory hiện tại.', '%d handles trong cấu hình không xuất hiện ở Inventory hiện tại.', count( $stale_handles ), 'baocache' ), count( $stale_handles ) ) ); ?> <?php echo esc_html( implode( ', ', array_slice( $stale_handles, 0, 8 ) ) ); ?></span><small><?php esc_html_e( 'Không tự xoá cấu hình; hãy quét lại frontend hoặc kiểm tra điều kiện enqueue.', 'baocache' ); ?></small></div><?php endif; ?>
			<section class="baocache-rule-gates" aria-labelledby="baocache-rule-gates-title"><div class="baocache-panel__heading"><div><h3 id="baocache-rule-gates-title"><?php esc_html_e( 'Per-rule Compatibility Gates', 'baocache' ); ?></h3><p><?php esc_html_e( 'Production chỉ áp dụng defer, async CSS hoặc delay khi rule có QA PASS, rollback đã xác minh và evidence còn hợp lệ. Staging/development vẫn cho phép thử để kiểm tra.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( in_array( wp_get_environment_type(), array( 'staging', 'development' ), true ) ? 'neutral' : 'warn' ); ?>"><?php echo esc_html( wp_get_environment_type() ); ?></span></div><div class="baocache-gate-review"><span><strong><?php esc_html_e( 'Evidence review', 'baocache' ); ?></strong><small><?php echo esc_html( ! empty( $gate_review['reviewed_at'] ) ? sprintf( __( '%1$d stale · kiểm tra %2$s', 'baocache' ), (int) ( $gate_review['stale_count'] ?? 0 ), wp_date( 'd/m H:i', (int) $gate_review['reviewed_at'] ) ) : __( 'Chưa có lần rà soát tự động.', 'baocache' ) ); ?></small></span><button type="button" class="button button-secondary button-small" data-baocache-review-gates><?php esc_html_e( 'Rà soát ngay', 'baocache' ); ?></button></div><?php if ( empty( $gate_rows ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có handle defer/async/delay để tạo gate.', 'baocache' ); ?></p><?php else : ?><div class="baocache-rule-gates__table"><table><thead><tr><th><?php esc_html_e( 'Handle', 'baocache' ); ?></th><th><?php esc_html_e( 'Strategy', 'baocache' ); ?></th><th><?php esc_html_e( 'QA', 'baocache' ); ?></th><th><?php esc_html_e( 'Rollback', 'baocache' ); ?></th><th><?php esc_html_e( 'Evidence', 'baocache' ); ?></th><th><?php esc_html_e( 'Trạng thái', 'baocache' ); ?></th><th></th></tr></thead><tbody><?php foreach ( $gate_rows as $gate ) : ?><tr data-baocache-rule-gate-row data-baocache-gate-handle="<?php echo esc_attr( $gate['handle'] ); ?>" data-baocache-gate-strategy="<?php echo esc_attr( $gate['strategy'] ); ?>"><td><code><?php echo esc_html( $gate['handle'] ); ?></code></td><td><?php echo esc_html( strtoupper( $gate['strategy'] ) ); ?></td><td><select data-baocache-gate-qa><option value="pending" <?php selected( $gate['qa'], 'pending' ); ?>><?php esc_html_e( 'Chưa PASS', 'baocache' ); ?></option><option value="pass" <?php selected( $gate['qa'], 'pass' ); ?>><?php esc_html_e( 'PASS', 'baocache' ); ?></option><option value="fail" <?php selected( $gate['qa'], 'fail' ); ?>><?php esc_html_e( 'FAIL', 'baocache' ); ?></option></select></td><td><label class="baocache-gate-check"><input type="checkbox" data-baocache-gate-rollback <?php checked( $gate['rollback_verified'] ); ?>> <?php esc_html_e( 'Đã thử', 'baocache' ); ?></label></td><td><code class="baocache-gate-evidence" title="<?php echo esc_attr( $gate['evidence_ref'] ?: $gate['current_evidence_ref'] ); ?>"><?php echo esc_html( $gate['evidence_ref'] ? substr( $gate['evidence_ref'], 0, 15 ) : __( 'Chưa có', 'baocache' ) ); ?></code></td><td><span class="baocache-badge is-<?php echo esc_attr( $gate['stale'] ? 'warn' : ( $gate['allowed'] ? 'good' : 'warn' ) ); ?>" data-baocache-gate-status><?php echo esc_html( $gate['stale'] ? ( $gate['acknowledged'] ? __( 'Stale · đã xem', 'baocache' ) : __( 'Stale · lưu lại gate', 'baocache' ) ) : ( $gate['allowed'] ? __( 'Được phép', 'baocache' ) : __( 'Bị chặn production', 'baocache' ) ) ); ?></span></td><td><button type="button" class="button button-small" data-baocache-save-rule-gate><?php esc_html_e( 'Lưu gate', 'baocache' ); ?></button></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
			<section class="baocache-strategy-ledger" aria-labelledby="baocache-strategy-ledger-title"><div class="baocache-panel__heading"><div><h3 id="baocache-strategy-ledger-title"><?php esc_html_e( 'Strategy Ledger', 'baocache' ); ?></h3><p><?php esc_html_e( 'Nhật ký defer/async và rollback; chỉ ghi handle, lý do và context, không ghi URL/query.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', count( $ledger ), 'baocache' ), count( $ledger ) ) ); ?></span></div><?php if ( empty( $ledger ) ) : ?><p class="baocache-analysis-note"><?php esc_html_e( 'Chưa có strategy nào được ghi nhận.', 'baocache' ); ?></p><?php else : ?><div class="baocache-strategy-ledger__table"><table><thead><tr><th><?php esc_html_e( 'Thời gian', 'baocache' ); ?></th><th><?php esc_html_e( 'Handle', 'baocache' ); ?></th><th><?php esc_html_e( 'Strategy', 'baocache' ); ?></th><th><?php esc_html_e( 'Context', 'baocache' ); ?></th><th><?php esc_html_e( 'Trạng thái', 'baocache' ); ?></th></tr></thead><tbody><?php foreach ( array_slice( $ledger, 0, 20 ) as $entry ) : ?><tr><td><?php echo esc_html( ! empty( $entry['at'] ) ? wp_date( 'd/m H:i', (int) $entry['at'] ) : '—' ); ?></td><td><code><?php echo esc_html( (string) ( $entry['handle'] ?? '—' ) ); ?></code><small><?php echo esc_html( (string) ( $entry['reason'] ?? '' ) ); ?></small></td><td><?php echo esc_html( strtoupper( (string) ( $entry['strategy'] ?? '—' ) ) ); ?></td><td><?php echo esc_html( (string) ( $entry['context'] ?? 'frontend' ) ); ?></td><td><span class="baocache-badge is-<?php echo ! empty( $entry['rolled_back'] ) ? 'warn' : 'good'; ?>"><?php echo esc_html( ! empty( $entry['rolled_back'] ) ? __( 'Rolled back', 'baocache' ) : __( 'Applied', 'baocache' ) ); ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
						<?php $this->gate_diff_drawer( $gate_rows ); ?>
						</section>
		<?php
	}

	private function gate_diff_drawer( array $gate_rows ): void {
		$history = array();
		$policy = BaoCache_Render_Blocking::gate_history_policy();
		foreach ( $gate_rows as $gate ) {
			$history[ (string) $gate['key'] ] = array_values( array_map( static function ( $entry ): array {
				return array(
					'at' => (int) ( $entry['at'] ?? 0 ),
					'qa' => (string) ( $entry['qa'] ?? 'pending' ),
					'rollback_verified' => ! empty( $entry['rollback_verified'] ),
					'change' => (string) ( $entry['change'] ?? 'reapproval' ),
					'changed' => is_array( $entry['changed'] ?? null ) ? $entry['changed'] : array(),
					'previous_ref' => (string) ( $entry['previous_ref'] ?? '' ),
					'evidence_ref' => (string) ( $entry['evidence_ref'] ?? '' ),
					'environment' => (string) ( $entry['environment'] ?? '' ),
					'plugin_version' => (string) ( $entry['plugin_version'] ?? '' ),
				);
			}, (array) ( $gate['history'] ?? array() ) ) );
		}
		?>
		<script type="application/json" data-baocache-gate-history-json><?php echo wp_json_encode( $history ); ?></script>
		<aside class="baocache-gate-diff-drawer is-hidden" data-baocache-gate-diff-drawer role="dialog" aria-modal="true" aria-labelledby="baocache-gate-diff-title" aria-hidden="true"><div class="baocache-gate-diff-drawer__panel"><header><div><h3 id="baocache-gate-diff-title" data-baocache-gate-diff-title><?php esc_html_e( 'Rule evidence history', 'baocache' ); ?></h3><p data-baocache-gate-diff-subtitle><?php esc_html_e( 'Các thay đổi bất biến của gate, không chứa URL hoặc nội dung asset.', 'baocache' ); ?></p></div><button type="button" class="button-link" data-baocache-gate-diff-close aria-label="<?php esc_attr_e( 'Đóng evidence history', 'baocache' ); ?>">×</button></header><div class="baocache-gate-diff-drawer__body" data-baocache-gate-diff-body></div><footer class="baocache-gate-diff-drawer__footer"><small data-baocache-gate-policy><?php echo esc_html( sprintf( __( 'Policy: giữ %1$d ngày · tối đa %2$d bản ghi · hiện có %3$d', 'baocache' ), (int) $policy['retention_days'], (int) $policy['max_entries'], (int) $policy['count'] ) ); ?></small><button type="button" class="button-link" data-baocache-ack-stale-gate hidden><?php esc_html_e( 'Đánh dấu đã xem', 'baocache' ); ?></button><button type="button" class="button-link" data-baocache-prune-gate-history><?php esc_html_e( 'Dọn lịch sử quá hạn', 'baocache' ); ?></button></footer></div></aside>
		<?php
	}

	private function frontend_timing_panel( array $settings ): void {
		$latest = BaoCache_Frontend_Metrics::latest();
		$groups = is_array( $latest['groups'] ?? null ) ? array_slice( $latest['groups'], 0, 8 ) : array();
		?>
		<div class="baocache-panel__heading"><div><h2><?php esc_html_e( 'Browser Resource Timing', 'baocache' ); ?></h2><p><?php esc_html_e( 'Mẫu browser opt-in, chỉ dùng để định hướng điều tra asset. Không phải waterfall, Core Web Vitals hay đánh giá render-blocking.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo esc_attr( ! empty( $settings['frontend_timing_enabled'] ) ? 'good' : 'neutral' ); ?>"><?php echo esc_html( ! empty( $settings['frontend_timing_enabled'] ) ? __( 'Opt-in enabled', 'baocache' ) : __( 'Disabled', 'baocache' ) ); ?></span></div>
		<?php $this->toggle( 'frontend_timing_enabled', __( 'Thu thập Resource Timing tổng hợp từ frontend công khai', 'baocache' ), $settings ); ?>
		<p class="baocache-analysis-note"><?php esc_html_e( 'Khi bật, BaoCache nhận tối đa một mẫu mỗi 15 phút cho toàn site và giữ tối đa 96 mẫu. Dữ liệu chỉ gồm hostname nguồn, loại resource, số request, tổng thời gian và bytes transfer; không lưu URL/path/query, cookie, IP hay định danh khách. HTML đã FastCGI-cache sẽ có collector sau TTL kế tiếp hoặc khi purge đúng URL cần thử.', 'baocache' ); ?></p>
		<?php if ( ! empty( $groups ) ) : ?><button type="button" class="button button-secondary" data-baocache-clear-frontend-metrics><?php esc_html_e( 'Xóa dữ liệu mẫu', 'baocache' ); ?></button><?php endif; ?>
		<?php if ( empty( $groups ) ) : ?>
			<p class="baocache-browser-timing__empty"><?php echo esc_html( ! empty( $settings['frontend_timing_enabled'] ) ? __( 'Đang chờ một lượt truy cập ẩn danh đủ điều kiện. Mẫu đầu tiên có thể mất đến 15 phút nếu rate limit vừa dùng.', 'baocache' ) : __( 'Bật tùy chọn trên, lưu cấu hình rồi kiểm tra lại sau một lượt truy cập ẩn danh.', 'baocache' ) ); ?></p>
		<?php else : ?>
			<p class="baocache-browser-timing__meta"><?php echo esc_html( sprintf( __( 'Mẫu gần nhất: %s trước', 'baocache' ), human_time_diff( (int) ( $latest['recorded_at'] ?? time() ), time() ) ) ); ?></p>
			<div class="baocache-inventory"><table><thead><tr><th><?php esc_html_e( 'Source', 'baocache' ); ?></th><th><?php esc_html_e( 'Type', 'baocache' ); ?></th><th><?php esc_html_e( 'Requests', 'baocache' ); ?></th><th><?php esc_html_e( 'Tổng thời gian', 'baocache' ); ?></th><th><?php esc_html_e( 'Transfer', 'baocache' ); ?></th></tr></thead><tbody><?php foreach ( $groups as $group ) : ?><tr><td><code><?php echo esc_html( (string) ( $group['source'] ?? '—' ) ); ?></code></td><td><?php echo esc_html( strtoupper( (string) ( $group['type'] ?? 'other' ) ) ); ?> · <?php echo esc_html( (string) ( $group['extension'] ?? 'other' ) ); ?></td><td><?php echo esc_html( (string) (int) ( $group['count'] ?? 0 ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) ( $group['duration_ms'] ?? 0 ) ) . ' ms' ); ?></td><td><?php echo esc_html( BaoCache_Diagnostics::bytes( (int) ( $group['transfer_bytes'] ?? 0 ) ) ); ?></td></tr><?php endforeach; ?></tbody></table></div>
		<?php endif; ?>
		<?php
	}

	private function asset_groups( array $assets ): array {
		$groups = array();
		foreach ( $assets as $asset ) {
			$source = $this->asset_source( (string) ( $asset['source'] ?? '' ) );
			if ( ! isset( $groups[ $source['key'] ] ) ) {
				$groups[ $source['key'] ] = array( 'label' => $source['label'], 'assets' => array() );
			}
			$groups[ $source['key'] ]['assets'][] = $asset;
		}
		return $groups;
	}

	/** Build only observations that the single captured frontend registry can support. */
	private function asset_insights( array $assets, array $settings, bool $has_placement ): array {
		$largest = null;
		$external = array();
		$sources = array();
		$head_scripts = 0;
		$head_candidates = array();
		$items = array();

		foreach ( $assets as $asset ) {
			$source = (string) ( $asset['source'] ?? '' );
			$type = (string) ( $asset['type'] ?? '' );
			$handle = (string) ( $asset['handle'] ?? '' );
			$size = $asset['size_bytes'] ?? null;
			if ( is_int( $size ) && ( null === $largest || $size > $largest['bytes'] ) ) {
				$largest = array( 'bytes' => $size, 'size' => BaoCache_Diagnostics::bytes( $size ), 'handle' => $handle, 'type' => $type, 'path' => (string) ( $asset['path'] ?? '/' ) );
			}
			$group = $this->asset_source( $source );
			if ( str_starts_with( (string) $group['key'], 'external-' ) ) {
				$external[ (string) $group['key'] ] = true;
			}
			if ( '' !== $source && empty( $asset['inline'] ) ) {
				$key = $type . '|' . $source;
				$sources[ $key ][] = $handle;
			}
			if ( $has_placement && 'script' === $type && '' !== $source && empty( $asset['in_footer'] ) && ! in_array( $handle, BaoCache_Settings::lines( (string) $settings['defer_handles'] ), true ) && ! in_array( $handle, BaoCache_Settings::lines( (string) $settings['delay_handles'] ), true ) ) {
				$head_scripts++;
				$head_candidates[] = $handle;
			}
		}

		$duplicates = array_filter( $sources, static fn( array $handles ): bool => 1 < count( $handles ) );
		if ( null !== $largest && 100 * KB_IN_BYTES <= $largest['bytes'] ) {
			$items[] = array( 'state' => 'warn', 'label' => __( 'Review', 'baocache' ), 'title' => sprintf( __( 'Large local %1$s: %2$s', 'baocache' ), $largest['type'], $largest['handle'] ), 'detail' => sprintf( __( '%s trên disk trong mẫu hiện tại. Kiểm tra xem có cần tải theo điều kiện hay không.', 'baocache' ), $largest['size'] ), 'action' => 'rule', 'handle' => $largest['handle'], 'type' => $largest['type'], 'path' => $largest['path'] );
		}
		if ( ! empty( $duplicates ) ) {
			$duplicate = reset( $duplicates );
			$items[] = array( 'state' => 'warn', 'label' => __( 'Review', 'baocache' ), 'title' => __( 'Duplicate asset source', 'baocache' ), 'detail' => sprintf( __( '%1$s được đăng ký bởi %2$s.', 'baocache' ), basename( (string) substr( (string) array_key_first( $duplicates ), strpos( (string) array_key_first( $duplicates ), '|' ) + 1 ) ), implode( ', ', (array) $duplicate ) ) );
		}
		if ( ! empty( $external ) ) {
			$items[] = array( 'state' => 'neutral', 'label' => __( 'Info', 'baocache' ), 'title' => __( 'Third-party assets detected', 'baocache' ), 'detail' => sprintf( _n( '%d external source in the current sample.', '%d external sources in the current sample.', count( $external ), 'baocache' ), count( $external ) ) );
		}
		if ( $has_placement && 0 < $head_scripts ) {
			$items[] = array( 'state' => 'neutral', 'label' => __( 'Info', 'baocache' ), 'title' => __( 'Scripts enqueued in head', 'baocache' ), 'detail' => sprintf( __( '%d script(s) are in the head on this sample. This is not itself a render-blocking verdict.', 'baocache' ), $head_scripts ), 'action' => 'defer', 'handle' => (string) $head_candidates[0] );
		}

		return array( 'largest' => $largest, 'external_count' => count( array_filter( $assets, fn( array $asset ): bool => str_starts_with( (string) $this->asset_source( (string) ( $asset['source'] ?? '' ) )['key'], 'external-' ) ) ), 'external_sources' => count( $external ), 'duplicate_count' => count( $duplicates ), 'head_scripts' => $has_placement ? $head_scripts : null, 'items' => array_slice( $items, 0, 4 ) );
	}

	private function asset_source( string $source ): array {
		$path = (string) wp_parse_url( $source, PHP_URL_PATH );
		$host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' !== $host && $host !== $site_host ) return array( 'key' => 'external-' . sanitize_key( $host ), 'label' => sprintf( __( 'External: %s', 'baocache' ), $host ) );
		if ( preg_match( '#/wp-content/plugins/([^/]+)#', $path, $match ) ) return array( 'key' => 'plugin-' . sanitize_key( $match[1] ), 'label' => sprintf( __( 'Plugin: %s', 'baocache' ), ucwords( str_replace( array( '-', '_' ), ' ', $match[1] ) ) ) );
		if ( preg_match( '#/wp-content/themes/([^/]+)#', $path, $match ) ) return array( 'key' => 'theme-' . sanitize_key( $match[1] ), 'label' => sprintf( __( 'Theme: %s', 'baocache' ), ucwords( str_replace( array( '-', '_' ), ' ', $match[1] ) ) ) );
		return array( 'key' => 'core', 'label' => __( 'WordPress / Inline', 'baocache' ) );
	}

	private function asset_status( array $asset, array $settings ): array {
		$handle = (string) ( $asset['handle'] ?? '' );
		$type = (string) ( $asset['type'] ?? '' );
		if ( 'script' === $type && in_array( $handle, BaoCache_Settings::lines( (string) $settings['delay_handles'] ), true ) ) return array( 'state' => 'warn', 'label' => __( 'Delay requested', 'baocache' ) );
		if ( 'script' === $type && in_array( $handle, BaoCache_Settings::lines( (string) $settings['defer_handles'] ), true ) ) return array( 'state' => 'good', 'label' => __( 'Deferred', 'baocache' ) );
		if ( 'style' === $type && in_array( $handle, BaoCache_Settings::lines( (string) $settings['async_style_handles'] ), true ) ) return array( 'state' => 'good', 'label' => __( 'Async CSS', 'baocache' ) );
		foreach ( (array) $settings['asset_rules'] as $rule ) {
			if ( $handle === ( $rule['handle'] ?? '' ) && $type === ( $rule['type'] ?? '' ) ) return array( 'state' => 'warn', 'label' => __( 'Rule', 'baocache' ) );
		}
		return array( 'state' => 'neutral', 'label' => __( 'Loaded', 'baocache' ) );
	}

	/** Render a bounded, dependency-aware view from the same sampled frontend registry. */
	private function dependency_graph( array $assets ): void {
		$assets = array_slice( $assets, 0, 40 );
		if ( empty( $assets ) ) {
			return;
		}
		$dependents = array();
		foreach ( $assets as $asset ) {
			$type = (string) ( $asset['type'] ?? '' );
			$handle = (string) ( $asset['handle'] ?? '' );
			foreach ( (array) ( $asset['dependencies'] ?? array() ) as $dependency ) {
				$key = $type . ':' . (string) $dependency;
				$dependents[ $key ][] = $handle;
			}
		}
		?>
		<section class="baocache-dependency-graph" aria-label="<?php esc_attr_e( 'Dependency map', 'baocache' ); ?>">
			<div class="baocache-dependency-graph__heading"><div><h3><?php esc_html_e( 'Dependency map', 'baocache' ); ?></h3><p><?php esc_html_e( 'Quan hệ trực tiếp trong mẫu frontend hiện tại. Một asset có “Được dùng bởi” không nên được unload.', 'baocache' ); ?></p></div><span><?php echo esc_html( sprintf( __( 'Tối đa %d assets', 'baocache' ), 40 ) ); ?></span></div>
			<div class="baocache-dependency-graph__nodes">
				<?php foreach ( $assets as $asset ) : ?>
					<?php $type = (string) ( $asset['type'] ?? '' ); $handle = (string) ( $asset['handle'] ?? '' ); $dependencies = array_filter( array_map( 'strval', (array) ( $asset['dependencies'] ?? array() ) ) ); $used_by = $dependents[ $type . ':' . $handle ] ?? array(); ?>
					<article class="baocache-dependency-node">
						<div><span class="baocache-type"><?php echo esc_html( strtoupper( $type ) ); ?></span><code><?php echo esc_html( $handle ); ?></code></div>
						<p><strong><?php esc_html_e( 'Phụ thuộc:', 'baocache' ); ?></strong> <?php echo esc_html( implode( ', ', $dependencies ) ?: '—' ); ?></p>
						<p class="<?php echo ! empty( $used_by ) ? 'is-blocking' : ''; ?>"><strong><?php esc_html_e( 'Được dùng bởi:', 'baocache' ); ?></strong> <?php echo esc_html( implode( ', ', $used_by ) ?: '—' ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function revision_history(): void {
		$history = get_option( 'baocache_settings_history', array() );
		if ( ! is_array( $history ) || empty( $history ) ) return;
		?>
		<div class="baocache-revisions"><h3><?php esc_html_e( 'Revision gần đây', 'baocache' ); ?></h3><p><?php esc_html_e( 'Khôi phục cấu hình trước đó nếu một rule asset gây lỗi.', 'baocache' ); ?></p><div><?php foreach ( $history as $index => $revision ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( self::RESTORE_ACTION ); ?><input type="hidden" name="action" value="<?php echo esc_attr( self::RESTORE_ACTION ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( (string) $index ); ?>"><span><?php echo esc_html( human_time_diff( (int) ( $revision['saved_at'] ?? time() ), time() ) . ' ' . __( 'trước', 'baocache' ) ); ?></span><button class="button button-secondary" type="submit"><?php esc_html_e( 'Khôi phục', 'baocache' ); ?></button></form><?php endforeach; ?></div></div>
		<?php
	}

	private function hardening_verification( array $settings ): void {
		$probe_history = get_option( 'baocache_hardening_probe_history', array() );
		$probe_history = is_array( $probe_history ) ? $probe_history : array();
		$latest_probe = $probe_history[0] ?? array();
		$latest_regressions = is_array( $latest_probe['regressions'] ?? null ) ? count( $latest_probe['regressions'] ) : 0;
		$baseline_ready = ! empty( $latest_probe['checks'] ) && empty( array_filter( (array) $latest_probe['checks'], static fn( mixed $check ): bool => is_array( $check ) && in_array( (string) ( $check['state'] ?? '' ), array( 'warn', 'bad' ), true ) ) );
		$baseline = get_option( 'baocache_hardening_probe_baseline', array() );
		$baseline_environment = (string) ( $baseline['environment'] ?? '' );
		$baseline_matches = ! empty( $baseline['checks'] ) && ( '' === $baseline_environment || $baseline_environment === wp_get_environment_type() );
		$acknowledged_probes = get_option( 'baocache_hardening_probe_acknowledged', array() );
		$acknowledged_probes = is_array( $acknowledged_probes ) ? array_map( 'absint', $acknowledged_probes ) : array();
		$feed_removed = ! empty( $settings['remove_feed_links'] ) || 'keep' !== (string) $settings['rss_mode'];
		$rss_labels = array(
			'keep' => __( 'Keep Feed', 'baocache' ),
			'redirect' => __( 'Redirect to Homepage', 'baocache' ),
			'gone' => __( 'Return 410 Gone', 'baocache' ),
		);
		$checks = array(
			array( 'label' => __( 'RSS Policy', 'baocache' ), 'value' => $rss_labels[ (string) $settings['rss_mode'] ] ?? $rss_labels['keep'], 'state' => 'good' ),
			array( 'label' => __( 'Feed Links', 'baocache' ), 'value' => $feed_removed ? __( 'Removed by policy', 'baocache' ) : __( 'Preserved for compatibility', 'baocache' ), 'state' => $feed_removed ? 'good' : 'neutral' ),
			array( 'label' => __( 'REST User Enumeration', 'baocache' ), 'value' => ! empty( $settings['disable_rest_user_enumeration'] ) ? __( 'Public users route blocked', 'baocache' ) : __( 'Policy disabled', 'baocache' ), 'state' => ! empty( $settings['disable_rest_user_enumeration'] ) ? 'good' : 'warn' ),
			array( 'label' => __( 'REST Discovery Link', 'baocache' ), 'value' => ! empty( $settings['remove_rest_api_link'] ) ? __( 'HTML/header link removed', 'baocache' ) : __( 'Preserved', 'baocache' ), 'state' => ! empty( $settings['remove_rest_api_link'] ) ? 'good' : 'neutral' ),
			array( 'label' => __( 'Generator Metadata', 'baocache' ), 'value' => ! empty( $settings['remove_generator'] ) ? __( 'HTML and RSS generator hidden', 'baocache' ) : __( 'WordPress version may be visible', 'baocache' ), 'state' => ! empty( $settings['remove_generator'] ) ? 'good' : 'warn' ),
			array( 'label' => __( 'X-Pingback', 'baocache' ), 'value' => ! empty( $settings['remove_x_pingback'] ) ? __( 'Response header removed', 'baocache' ) : __( 'Preserved', 'baocache' ), 'state' => ! empty( $settings['remove_x_pingback'] ) ? 'good' : 'neutral' ),
		);
		?>
		<section class="baocache-hardening-verification" aria-labelledby="baocache-hardening-verification-title">
			<div class="baocache-panel__heading"><div><h3 id="baocache-hardening-verification-title"><?php esc_html_e( 'Hardening Verification', 'baocache' ); ?></h3><p><?php esc_html_e( 'Kiểm tra policy và WordPress hooks hiện tại; không phải điểm bảo mật. Dùng Header Inspector để xác minh response public sau purge.', 'baocache' ); ?></p></div><span class="baocache-badge is-<?php echo $latest_regressions > 0 ? 'warn' : 'neutral'; ?>"><?php echo ! empty( $latest_probe['checked_at'] ) ? esc_html( sprintf( __( 'Last probe %s', 'baocache' ), human_time_diff( (int) $latest_probe['checked_at'], time() ) . ' ' . __( 'trước', 'baocache' ) ) ) : esc_html__( 'Chưa probe', 'baocache' ); ?><?php echo $latest_regressions > 0 ? esc_html( sprintf( __( ' · %d regression', 'baocache' ), $latest_regressions ) ) : ''; ?></span></div>
			<ul><?php foreach ( $checks as $check ) : ?><li><span class="baocache-badge is-<?php echo esc_attr( $check['state'] ); ?>"><?php echo esc_html( $check['state'] === 'good' ? __( 'PASS', 'baocache' ) : ( $check['state'] === 'warn' ? __( 'WARN', 'baocache' ) : __( 'INFO', 'baocache' ) ) ); ?></span><strong><?php echo esc_html( $check['label'] ); ?></strong><span><?php echo esc_html( $check['value'] ); ?></span></li><?php endforeach; ?></ul>
			<div class="baocache-probe-schedule"><h4><?php esc_html_e( 'Probe Schedule & Baseline', 'baocache' ); ?></h4><?php $this->toggle( 'probe_enabled', __( 'Bật probe định kỳ', 'baocache' ), $settings ); ?><label class="baocache-field"><span><?php esc_html_e( 'Lịch probe', 'baocache' ); ?></span><select name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[probe_schedule]"><option value="manual" <?php selected( $settings['probe_schedule'], 'manual' ); ?>><?php esc_html_e( 'Manual only', 'baocache' ); ?></option><option value="hourly" <?php selected( $settings['probe_schedule'], 'hourly' ); ?>><?php esc_html_e( 'Mỗi giờ', 'baocache' ); ?></option><option value="baocache_six_hours" <?php selected( $settings['probe_schedule'], 'baocache_six_hours' ); ?>><?php esc_html_e( 'Mỗi 6 giờ', 'baocache' ); ?></option><option value="baocache_daily" <?php selected( $settings['probe_schedule'], 'baocache_daily' ); ?>><?php esc_html_e( 'Mỗi ngày', 'baocache' ); ?></option></select><small><?php esc_html_e( 'Probe định kỳ chỉ chạy khi bật; cần WordPress Cron/Coolify worker hoạt động.', 'baocache' ); ?></small></label><p class="description"><?php echo esc_html( $baseline_matches ? sprintf( __( 'Baseline: %1$s · môi trường %2$s', 'baocache' ), __( 'đang dùng', 'baocache' ), wp_get_environment_type() ) : __( 'Chưa có baseline cùng môi trường; hãy chạy probe PASS rồi đặt baseline cho môi trường này.', 'baocache' ) ); ?></p><button type="button" class="button button-secondary" data-baocache-set-baseline <?php disabled( ! $baseline_ready ); ?>><?php esc_html_e( 'Đặt baseline từ probe PASS', 'baocache' ); ?></button><span data-baocache-baseline-result></span></div>
			<div class="baocache-hardening-probe"><button type="button" class="button button-secondary" data-baocache-hardening-probe><?php esc_html_e( 'Probe public response', 'baocache' ); ?></button><span data-baocache-hardening-probe-result></span></div>
			<?php if ( ! empty( $probe_history ) ) : ?>
				<section class="baocache-probe-history" aria-labelledby="baocache-probe-history-title"><div class="baocache-probe-history__heading"><div><h4 id="baocache-probe-history-title"><?php esc_html_e( 'Probe History', 'baocache' ); ?></h4><p><?php esc_html_e( 'Snapshot gần đây của public response. Xác nhận chỉ đánh dấu đã xem, không xóa regression hay dữ liệu gốc.', 'baocache' ); ?></p></div><span class="baocache-badge is-neutral"><?php echo esc_html( sprintf( _n( '%d snapshot', '%d snapshots', count( $probe_history ), 'baocache' ), count( $probe_history ) ) ); ?></span></div><div class="baocache-probe-history__table-wrap"><table><thead><tr><th><?php esc_html_e( 'Thời gian', 'baocache' ); ?></th><th><?php esc_html_e( 'Nguồn', 'baocache' ); ?></th><th><?php esc_html_e( 'Kết quả', 'baocache' ); ?></th><th><?php esc_html_e( 'Thay đổi', 'baocache' ); ?></th><th><?php esc_html_e( 'Trạng thái', 'baocache' ); ?></th></tr></thead><tbody>
				<?php foreach ( array_slice( $probe_history, 0, 10 ) as $record ) : ?>
					<?php if ( ! is_array( $record ) ) continue; $probe_id = absint( $record['checked_at'] ?? 0 ); $regression_count = is_array( $record['regressions'] ?? null ) ? count( $record['regressions'] ) : 0; $improvement_count = is_array( $record['improvements'] ?? null ) ? count( $record['improvements'] ) : 0; $is_acknowledged = in_array( $probe_id, $acknowledged_probes, true ); ?>
					<tr data-baocache-probe-row="<?php echo esc_attr( (string) $probe_id ); ?>"><td><strong><?php echo esc_html( $probe_id ? wp_date( 'd/m/Y H:i', $probe_id ) : '—' ); ?></strong><small><?php echo esc_html( sprintf( __( '%d ms', 'baocache' ), (int) ( $record['response_ms'] ?? 0 ) ) ); ?></small></td><td><?php echo esc_html( 'scheduled' === (string) ( $record['source'] ?? '' ) ? __( 'Định kỳ', 'baocache' ) : __( 'Thủ công', 'baocache' ) ); ?></td><td><span class="baocache-badge is-<?php echo esc_attr( $regression_count > 0 ? 'warn' : 'good' ); ?>"><?php echo esc_html( sprintf( __( '%1$d/%2$d PASS', 'baocache' ), (int) ( $record['passed'] ?? 0 ), (int) ( $record['total'] ?? 0 ) ) ); ?></span></td><td><?php echo esc_html( $regression_count > 0 ? sprintf( _n( '%d regression', '%d regressions', $regression_count, 'baocache' ), $regression_count ) : ( $improvement_count > 0 ? sprintf( _n( '%d improvement', '%d improvements', $improvement_count, 'baocache' ), $improvement_count ) : __( 'Không đổi', 'baocache' ) ) ); ?></td><td><?php if ( $regression_count > 0 ) : ?><button type="button" class="button button-small baocache-probe-ack" data-baocache-ack-probe="<?php echo esc_attr( (string) $probe_id ); ?>" <?php disabled( $is_acknowledged || 0 === $probe_id ); ?>><?php echo esc_html( $is_acknowledged ? __( 'Đã xác nhận', 'baocache' ) : __( 'Xác nhận đã xem', 'baocache' ) ); ?></button><?php else : ?><span class="baocache-badge is-good"><?php esc_html_e( 'Ổn định', 'baocache' ); ?></span><?php endif; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table></div><div class="baocache-probe-history__details"><?php foreach ( array_slice( $probe_history, 0, 10 ) as $record ) : ?><?php if ( ! is_array( $record ) ) continue; $probe_id = absint( $record['checked_at'] ?? 0 ); ?><details><summary><?php echo esc_html( $probe_id ? wp_date( 'd/m/Y H:i', $probe_id ) : '—' ); ?><span><?php echo esc_html( sprintf( __( '%d/%d PASS', 'baocache' ), (int) ( $record['passed'] ?? 0 ), (int) ( $record['total'] ?? 0 ) ) ); ?></span></summary><ul><?php foreach ( (array) ( $record['checks'] ?? array() ) as $check ) : ?><li><span class="baocache-badge is-<?php echo esc_attr( 'good' === ( $check['state'] ?? '' ) ? 'good' : ( 'warn' === ( $check['state'] ?? '' ) ? 'warn' : 'neutral' ) ); ?>"><?php echo esc_html( strtoupper( (string) ( $check['state'] ?? 'info' ) ) ); ?></span><strong><?php echo esc_html( (string) ( $check['label'] ?? '—' ) ); ?></strong><span><?php echo esc_html( (string) ( $check['value'] ?? '—' ) ); ?></span></li><?php endforeach; ?></ul><?php if ( ! empty( $record['regressions'] ) || ! empty( $record['improvements'] ) ) : ?><p><?php esc_html_e( 'Check-level diff', 'baocache' ); ?></p><ol><?php foreach ( array_merge( (array) ( $record['regressions'] ?? array() ), (array) ( $record['improvements'] ?? array() ) ) as $change ) : ?><li><?php echo esc_html( sprintf( '%s: %s → %s', (string) ( $change['label'] ?? '—' ), (string) ( $change['from'] ?? '—' ), (string) ( $change['to'] ?? '—' ) ) ); ?></li><?php endforeach; ?></ol><?php endif; ?></details><?php endforeach; ?></div></section>
			<?php endif; ?>
		</section>
		<?php
	}
		

	private function wordfence_active(): bool {
		$active = get_option( 'active_plugins', array() );
		$network_active = get_site_option( 'active_sitewide_plugins', array() );
		$plugins = array_merge( is_array( $active ) ? $active : array(), is_array( $network_active ) ? array_keys( $network_active ) : array() );
		foreach ( $plugins as $plugin ) {
			if ( str_contains( strtolower( (string) $plugin ), 'wordfence' ) ) {
				return true;
			}
		}
		return defined( 'WF_VERSION' ) || class_exists( 'wfConfig' );
	}

	private function notices(): void {
		$flush = sanitize_key( (string) ( $_GET['baocache_flush'] ?? '' ) );
		if ( 'success' === $flush ) {
			echo '<div class="baocache-server-feedback" data-baocache-server-feedback="success">' . esc_html__( 'Đã flush Redis object cache.', 'baocache' ) . '</div>';
		} elseif ( 'failed' === $flush ) {
			echo '<div class="baocache-server-feedback" data-baocache-server-feedback="error">' . esc_html__( 'Không thể flush object cache.', 'baocache' ) . '</div>';
		}
		$warmup = sanitize_key( (string) ( $_GET['baocache_warmup'] ?? '' ) );
		if ( 'empty' === $warmup ) {
			echo '<div class="baocache-server-feedback" data-baocache-server-feedback="error">' . esc_html__( 'Không thêm được URL. Kiểm tra đã bật warm queue, sitemap cùng domain và sitemap có dữ liệu.', 'baocache' ) . '</div>';
		}
	}

	private function toggle( string $key, string $label, array $settings ): void {
		?><label class="baocache-toggle"><input type="checkbox" name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>><span></span><?php echo esc_html( $label ); ?></label><?php
	}

	private function textarea( string $key, string $label, array $settings, string $placeholder ): void {
		?><label class="baocache-field"><span><?php echo esc_html( $label ); ?></span><textarea name="<?php echo esc_attr( BAOCACHE_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" rows="5" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $settings[ $key ] ); ?></textarea><small><?php esc_html_e( 'Mỗi URL một dòng.', 'baocache' ); ?></small></label><?php
	}

	private function rule_row( int|string $index, array $rule ): void {
		$name = BAOCACHE_OPTION . '[asset_rules][' . $index . ']';
		?>
		<div class="baocache-rule" data-baocache-rule>
			<select name="<?php echo esc_attr( $name ); ?>[type]"><option value="script" <?php selected( $rule['type'], 'script' ); ?>><?php esc_html_e( 'JavaScript', 'baocache' ); ?></option><option value="style" <?php selected( $rule['type'], 'style' ); ?>><?php esc_html_e( 'CSS', 'baocache' ); ?></option></select>
			<input name="<?php echo esc_attr( $name ); ?>[handle]" value="<?php echo esc_attr( $rule['handle'] ); ?>" placeholder="handle">
			<select name="<?php echo esc_attr( $name ); ?>[scope]"><option value="everywhere" <?php selected( $rule['scope'], 'everywhere' ); ?>><?php esc_html_e( 'Toàn frontend', 'baocache' ); ?></option><option value="front-page" <?php selected( $rule['scope'], 'front-page' ); ?>><?php esc_html_e( 'Trang chủ', 'baocache' ); ?></option><option value="page" <?php selected( $rule['scope'], 'page' ); ?>><?php esc_html_e( 'Trang (ID/slug)', 'baocache' ); ?></option><option value="post-type" <?php selected( $rule['scope'], 'post-type' ); ?>><?php esc_html_e( 'Post type', 'baocache' ); ?></option><option value="url-prefix" <?php selected( $rule['scope'], 'url-prefix' ); ?>><?php esc_html_e( 'URL prefix', 'baocache' ); ?></option><option value="missing-shortcode" <?php selected( $rule['scope'], 'missing-shortcode' ); ?>><?php esc_html_e( 'Không có shortcode (chỉ tải khi có)', 'baocache' ); ?></option><option value="has-shortcode" <?php selected( $rule['scope'], 'has-shortcode' ); ?>><?php esc_html_e( 'Có shortcode (unload tại đây)', 'baocache' ); ?></option><option value="missing-block" <?php selected( $rule['scope'], 'missing-block' ); ?>><?php esc_html_e( 'Không có block (chỉ tải khi có)', 'baocache' ); ?></option><option value="has-block" <?php selected( $rule['scope'], 'has-block' ); ?>><?php esc_html_e( 'Có block (unload tại đây)', 'baocache' ); ?></option></select>
			<input name="<?php echo esc_attr( $name ); ?>[value]" value="<?php echo esc_attr( $rule['value'] ); ?>" placeholder="ID, slug, shortcode, block hoặc /duong-dan/">
			<button type="button" class="button-link-delete" data-baocache-remove-rule><?php esc_html_e( 'Xóa', 'baocache' ); ?></button>
		</div>
		<?php
	}
}
