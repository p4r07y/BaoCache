(() => {
	const rules = document.querySelector('[data-baocache-rules]');
	const template = document.querySelector('[data-baocache-rule-template]');
	const addButton = document.querySelector('[data-baocache-add-rule]');
	const tabs = document.querySelectorAll('[data-baocache-tab]');
	const panes = document.querySelectorAll('[data-baocache-pane]');
	const settingsForm = document.querySelector('.baocache-form');
	const inspectButton = document.querySelector('[data-baocache-inspect]');
	const inspectUrl = document.querySelector('[data-baocache-inspect-url]');
	const inspectResult = document.querySelector('[data-baocache-inspect-result]');
	const previewButton = document.querySelector('[data-baocache-preview]');
	const previewType = document.querySelector('[data-baocache-preview-type]');
	const previewHandle = document.querySelector('[data-baocache-preview-handle]');
	const previewScope = document.querySelector('[data-baocache-preview-scope]');
	const previewValue = document.querySelector('[data-baocache-preview-value]');
	const previewResult = document.querySelector('[data-baocache-preview-result]');
	const inspectorShortcut = document.querySelector('[data-baocache-inspect-shortcut]');
	const diagnosticsShortcut = document.querySelector('[data-baocache-diagnostics-shortcut]');
	const scanShortcut = document.querySelector('[data-baocache-scan-shortcut]');
	const warmShortcut = document.querySelector('[data-baocache-warm-shortcut]');
	const tabShortcuts = document.querySelectorAll('[data-baocache-go]');
	const warmupForm = document.querySelector('[data-baocache-warmup-form]');
	const warmupButton = document.querySelector('[data-baocache-warmup]');
	const assetTabs = document.querySelectorAll('[data-baocache-assets-tab]');
	const assetPanes = document.querySelectorAll('[data-baocache-assets-pane]');
	const cspTabs = document.querySelectorAll('[data-baocache-csp-tab]');
	const cspPanes = document.querySelectorAll('[data-baocache-csp-pane]');
	const assetSearch = document.querySelector('[data-baocache-asset-search]');
	const assetType = document.querySelector('[data-baocache-asset-type]');
	const assetSource = document.querySelector('[data-baocache-asset-source]');
	const assetScanButtons = document.querySelectorAll('[data-baocache-scan-assets]');
	const cloudflareAuditButton = document.querySelector('[data-baocache-cloudflare-audit]');
	const cloudflareAuditResult = document.querySelector('[data-baocache-cloudflare-audit-result]');
	const cloudflarePurgeButton = document.querySelector('[data-baocache-cloudflare-purge]');
	const cloudflarePurgeUrl = document.querySelector('[data-baocache-cloudflare-purge-url]');
	const clearFrontendMetricsButton = document.querySelector('[data-baocache-clear-frontend-metrics]');
	const hardeningProbeButton = document.querySelector('[data-baocache-hardening-probe]');
	const hardeningProbeResult = document.querySelector('[data-baocache-hardening-probe-result]');
	const hardeningBaselineButton = document.querySelector('[data-baocache-set-baseline]');
	const hardeningBaselineResult = document.querySelector('[data-baocache-baseline-result]');
	const renderBlockingJson = document.querySelector('[data-baocache-render-blocking-json]');
	const renderBlockingSnapshot = document.querySelector('[data-baocache-render-blocking-snapshot]');
	const renderBlockingImportButton = document.querySelector('[data-baocache-render-blocking-import]');
	const renderBlockingResult = document.querySelector('[data-baocache-render-blocking-result]');
	const criticalCssInput = document.querySelector('[data-baocache-critical-css]');
	const criticalCssTemplate = document.querySelector('[data-baocache-critical-template]');
	const criticalCssViewport = document.querySelector('[data-baocache-critical-viewport]');
	const criticalCssStageButton = document.querySelector('[data-baocache-stage-critical-css]');
	const criticalCssStageResult = document.querySelector('[data-baocache-critical-css-result]');
	const criticalCssRollbackButton = document.querySelector('[data-baocache-rollback-critical-css]');
	const compatibilityQaSaveButton = document.querySelector('[data-baocache-save-compatibility-qa]');
	const compatibilityQaResetButton = document.querySelector('[data-baocache-reset-compatibility-qa]');
	const compatibilityQaResult = document.querySelector('[data-baocache-compatibility-qa-result]');
	const contextQaButton = document.querySelector('[data-baocache-context-qa]');
	const contextQaResult = document.querySelector('[data-baocache-context-result]');
	const contextQaPath = document.querySelector('[data-baocache-context-path]');
	const contextQaHandle = document.querySelector('[data-baocache-context-handle]');
	const contextQaLoggedIn = document.querySelector('[data-baocache-context-logged-in]');
	const contextQaPreview = document.querySelector('[data-baocache-context-preview]');
	const contextQaCheckout = document.querySelector('[data-baocache-context-checkout]');
	const analyticsIdInput = document.querySelector('[data-baocache-analytics-id]');
	const analyticsDetected = document.querySelector('[data-baocache-analytics-detected]');
	const analyticsProvider = document.querySelector('[data-baocache-analytics-provider]');
	const analyticsGoogleLabel = document.querySelector('[data-baocache-analytics-google-label]');
	const analyticsTestButton = document.querySelector('[data-baocache-analytics-test]');
	const analyticsTestResult = document.querySelector('[data-baocache-analytics-test-result]');
	const analyticsCopyButton = document.querySelector('[data-baocache-analytics-copy]');
	const analyticsPreview = document.querySelector('[data-baocache-analytics-preview]');
	const injectorList = document.querySelector('[data-baocache-injector-list]');
	const injectorSummary = document.querySelector('[data-baocache-injector-summary]');
	const clearCspReportsButton = document.querySelector('[data-baocache-clear-csp-reports]');
	const reviewCspEvidenceButton = document.querySelector('[data-baocache-review-csp-evidence]');
	const cspRecommendationButtons = document.querySelectorAll('[data-baocache-apply-csp-recommendation]');
	const cspDismissButtons = document.querySelectorAll('[data-baocache-dismiss-csp-recommendation]');
	const cspRollbackButtons = document.querySelectorAll('[data-baocache-rollback-csp-recommendation]');
	const cspPostProbeButton = document.querySelector('[data-baocache-csp-post-probe]');
	const cspPostProbeResult = document.querySelector('[data-baocache-csp-post-probe-result]');
	const cspManualRollbackButton = document.querySelector('[data-baocache-csp-manual-rollback]');
	const cspProbeAckButton = document.querySelector('[data-baocache-csp-probe-ack]');
	const cspRemediationSaveButtons = document.querySelectorAll('[data-baocache-csp-remediation-save]');
	const verifyPurgeButton = document.querySelector('[data-baocache-verify-purge]');
	const verifyPurgeResult = document.querySelector('[data-baocache-verify-purge-result]');
	const purgeUrlButton = document.querySelector('[data-baocache-purge-url-submit]');
	const purgeUrlInput = document.querySelector('[data-baocache-purge-url]');
	const purgeUrlResult = document.querySelector('[data-baocache-purge-url-result]');
	const criticalImageScanButton = document.querySelector('[data-baocache-scan-critical-images]');
	const criticalImageApplyButtons = document.querySelectorAll('[data-baocache-apply-critical-image]');
	const criticalImageRollbackButton = document.querySelector('[data-baocache-rollback-critical-image]');
	const resourceHintScanButton = document.querySelector('[data-baocache-scan-resource-hints]');
	const resourceHintApplyButton = document.querySelector('[data-baocache-apply-resource-hints]');
	const resourceHintRollbackButton = document.querySelector('[data-baocache-rollback-resource-hints]');
	const resourceHintResult = document.querySelector('[data-baocache-resource-hints-result]');
	const thirdPartyScanButton = document.querySelector('[data-baocache-scan-third-party]');
	const thirdPartyApplyButton = document.querySelector('[data-baocache-apply-third-party]');
	const thirdPartyRollbackButton = document.querySelector('[data-baocache-rollback-third-party]');
	const thirdPartyResult = document.querySelector('[data-baocache-third-party-result]');
	const commerceScanButton = document.querySelector('[data-baocache-scan-commerce]');
	const commerceApplyButton = document.querySelector('[data-baocache-apply-commerce]');
	const commerceRollbackButton = document.querySelector('[data-baocache-rollback-commerce]');
	const commerceResult = document.querySelector('[data-baocache-commerce-result]');
	const adapterScanButton = document.querySelector('[data-baocache-scan-adapters]');
	const adapterApplyButton = document.querySelector('[data-baocache-apply-adapters]');
	const adapterRollbackButton = document.querySelector('[data-baocache-rollback-adapters]');
	const adapterResult = document.querySelector('[data-baocache-adapter-result]');
	const retentionKeep = document.querySelector('[data-baocache-retention-keep]');
	const retentionHistory = document.querySelector('[data-baocache-retention-history]');
	const retentionRemove = document.querySelector('[data-baocache-retention-remove]');
	const retentionWarning = document.querySelector('[data-baocache-retention-warning]');
	const databaseCheckButton = document.querySelector('[data-baocache-database-check]');
	const databaseRepairButton = document.querySelector('[data-baocache-database-repair]');
	const databaseCleanButton = document.querySelector('[data-baocache-database-clean]');
	const databaseResult = document.querySelector('[data-baocache-database-result]');
	let toastTimer;

	const setButtonLabel = (button, label) => {
		if (!button) return;
		if (button.tagName === 'INPUT') button.value = label;
		else button.textContent = label;
	};

	const showToast = (message, options = {}) => {
		const existing = document.querySelector('[data-baocache-toast]');
		if (existing) existing.remove();
		window.clearTimeout(toastTimer);
		const toast = document.createElement('div');
		toast.className = `baocache-toast${options.error ? ' is-error' : ''}`;
		toast.dataset.baocacheToast = 'true';
		toast.setAttribute('role', options.error ? 'alert' : 'status');
		toast.innerHTML = `<span class="baocache-toast__icon" aria-hidden="true">${options.error ? '!' : '✓'}</span><span class="baocache-toast__message"></span>`;
		toast.querySelector('.baocache-toast__message').textContent = message;
		if (options.action && options.onAction) {
			const action = document.createElement('button');
			action.type = 'button';
			action.className = 'button button-small';
			action.textContent = options.action;
			action.addEventListener('click', () => {
				action.disabled = true;
				options.onAction();
			});
			toast.appendChild(action);
		}
		document.body.appendChild(toast);
		const duration = options.duration ?? (options.action ? 8000 : 3200);
		if (duration > 0) toastTimer = window.setTimeout(() => toast.remove(), duration);
	};

	const serverFeedback = document.querySelector('[data-baocache-server-feedback]');
	if (serverFeedback) {
		showToast(serverFeedback.textContent.trim(), { error: serverFeedback.dataset.baocacheServerFeedback === 'error' });
		serverFeedback.remove();
	}

	const request = async (data, fallbackMessage) => {
		const response = await fetch(BaoCacheAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams(data),
		});
		let result;
		try {
			result = JSON.parse(await response.text());
		} catch (error) {
			throw new Error(`${fallbackMessage} (HTTP ${response.status})`);
		}
		if (!result.success) throw new Error(result.data?.message || fallbackMessage);
		return result.data;
	};

	const runWarmup = async () => {
		if (!warmupButton || !window.BaoCacheAdmin) return;
		const originalLabel = warmupButton.textContent;
		warmupButton.disabled = true;
		setButtonLabel(warmupButton, 'Đang đọc sitemap…');
		showToast('Đang đọc sitemap…', { duration: 0 });
		try {
			const data = await request({ action: 'baocache_refresh_warmup', nonce: BaoCacheAdmin.warmupNonce }, BaoCacheAdmin.warmupError);
			const queued = document.querySelector('[data-baocache-warm-queued]');
			const detectedSitemap = document.querySelector('[data-baocache-warm-sitemap]');
			if (queued) queued.textContent = data.queued;
			if (detectedSitemap && data.detected_sitemap) detectedSitemap.textContent = data.detected_sitemap;
			showToast(`Đã thêm ${data.added} URL vào Warm Queue.`);
		} catch (error) {
			showToast(error.message || BaoCacheAdmin.warmupError, { error: true, duration: 5500 });
		} finally {
			setButtonLabel(warmupButton, originalLabel);
			warmupButton.disabled = false;
		}
	};

	if (warmupForm && warmupButton && window.BaoCacheAdmin) {
		warmupForm.addEventListener('submit', (event) => {
			event.preventDefault();
			runWarmup();
		});
	}

	if (settingsForm && window.BaoCacheAdmin) {
		const saveButton = settingsForm.querySelector('[type="submit"]');
		const warmEnabled = settingsForm.querySelector('[name="baocache_settings[warm_enabled]"]');
		const sitemap = settingsForm.querySelector('[name="baocache_settings[warm_sitemap]"]');
		let initialWarmEnabled = Boolean(warmEnabled?.checked);
		let initialSitemap = sitemap?.value.trim() || '';
		const originalSaveLabel = saveButton ? (saveButton.tagName === 'INPUT' ? saveButton.value : saveButton.textContent) : '';

		settingsForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			if (retentionRemove?.checked && !window.confirm('Xóa toàn bộ dữ liệu BaoCache khi uninstall? Hành động này không thể hoàn tác.')) return;
			if (!saveButton) return;
			saveButton.disabled = true;
			setButtonLabel(saveButton, 'Đang lưu…');
			try {
				const data = new FormData(settingsForm);
				data.set('action', 'baocache_save_settings');
				data.set('nonce', BaoCacheAdmin.saveNonce);
				await request(data, BaoCacheAdmin.saveError);
				const warmChanged = Boolean(warmEnabled?.checked) && (!initialWarmEnabled || initialSitemap !== (sitemap?.value.trim() || ''));
				initialWarmEnabled = Boolean(warmEnabled?.checked);
				initialSitemap = sitemap?.value.trim() || '';
				warmupButton && (warmupButton.disabled = !initialWarmEnabled || !initialSitemap);
				setButtonLabel(saveButton, '✓ Đã lưu');
				showToast('Đã lưu cấu hình.', warmChanged ? { action: 'Đọc sitemap ngay', onAction: runWarmup } : {});
				window.setTimeout(() => setButtonLabel(saveButton, originalSaveLabel), 2000);
			} catch (error) {
				setButtonLabel(saveButton, originalSaveLabel);
				showToast(error.message || BaoCacheAdmin.saveError, { error: true, duration: 5500 });
			} finally {
				saveButton.disabled = false;
			}
		});
	}

	const syncRetentionPolicy = () => {
		const destructive = Boolean(retentionRemove?.checked);
		if (retentionKeep) retentionKeep.disabled = destructive;
		if (retentionHistory) retentionHistory.disabled = destructive;
		retentionWarning?.classList.toggle('is-hidden', !destructive);
	};
	retentionRemove?.addEventListener('change', () => {
		if (!retentionRemove.checked && retentionKeep) retentionKeep.checked = true;
		syncRetentionPolicy();
	});
	retentionKeep?.addEventListener('change', () => {
		if (!retentionKeep.checked && retentionRemove) retentionRemove.checked = true;
		syncRetentionPolicy();
	});
	syncRetentionPolicy();

	const runDatabaseAction = async (button, action, nonce, fallback, confirmation = '') => {
		if (!button || !window.BaoCacheAdmin) return;
		if (confirmation && !window.confirm(confirmation)) return;
		const label = button.textContent;
		button.disabled = true;
		setButtonLabel(button, 'Đang xử lý…');
		if (databaseResult) databaseResult.textContent = 'Đang kiểm tra dữ liệu BaoCache…';
		try {
			const data = await request({ action, nonce }, fallback);
			const health = data.health || data;
			if (databaseResult) databaseResult.textContent = `Schema ${health.schema_current ?? health.schema_expected}/${health.schema_expected} · ${health.pending_migrations ?? 0} migration chờ · ${health.queue_jobs ?? 0} queue job`;
			showToast(action === 'baocache_database_clean_runtime' ? `Đã dọn ${data.records_removed || 0} runtime record.` : 'Đã hoàn tất kiểm tra dữ liệu BaoCache.');
		} catch (error) {
			if (databaseResult) databaseResult.textContent = error.message || fallback;
			showToast(error.message || fallback, { error: true, duration: 6000 });
		} finally {
			button.disabled = false;
			setButtonLabel(button, label);
		}
	};
	databaseCheckButton?.addEventListener('click', () => runDatabaseAction(databaseCheckButton, 'baocache_database_check', BaoCacheAdmin.databaseCheckNonce, BaoCacheAdmin.databaseCheckError));
	databaseRepairButton?.addEventListener('click', () => runDatabaseAction(databaseRepairButton, 'baocache_database_repair', BaoCacheAdmin.databaseRepairNonce, BaoCacheAdmin.databaseRepairError, 'BaoCache chỉ tạo option/schema marker bị thiếu và xác minh cron do BaoCache sở hữu. Tiếp tục?'));
	databaseCleanButton?.addEventListener('click', () => runDatabaseAction(databaseCleanButton, 'baocache_database_clean_runtime', BaoCacheAdmin.databaseCleanNonce, BaoCacheAdmin.databaseCleanError, 'Dọn Warm Queue, lock, transient, inventory tạm và cron runtime của BaoCache? Cấu hình sẽ được giữ.'));

	const detectAnalyticsId = () => {
		const id = (analyticsIdInput?.value || '').trim().toUpperCase();
		let type = 'none';
		if (/^G-[A-Z0-9]{6,}$/.test(id)) type = 'ga4';
		else if (/^GTM-[A-Z0-9]{4,}$/.test(id)) type = 'gtm';
		else if (id) type = 'invalid';
		const labels = { ga4: 'Detected · Google Analytics 4', gtm: 'Detected · Google Tag Manager', invalid: 'Invalid Measurement ID', none: '' };
		if (analyticsDetected) {
			analyticsDetected.textContent = labels[type];
			analyticsDetected.classList.toggle('is-valid', type === 'ga4' || type === 'gtm');
			analyticsDetected.classList.toggle('is-invalid', type === 'invalid');
		}
		if (analyticsProvider) {
			analyticsProvider.textContent = type === 'ga4' ? 'Google Analytics 4' : (type === 'gtm' ? 'Google Tag Manager' : (type === 'invalid' ? 'ID không hợp lệ' : 'Chưa cấu hình'));
			analyticsProvider.classList.toggle('is-good', type === 'ga4' || type === 'gtm');
			analyticsProvider.classList.toggle('is-bad', type === 'invalid');
			analyticsProvider.classList.toggle('is-neutral', type === 'none');
		}
		if (analyticsGoogleLabel) analyticsGoogleLabel.textContent = type === 'gtm' ? 'Google Tag Manager' : 'Google Analytics';
		return { id, type };
	};

	if (analyticsIdInput) analyticsIdInput.addEventListener('input', detectAnalyticsId);
	const renderAnalyticsChecks = (checks) => {
		if (!analyticsTestResult) return;
		analyticsTestResult.innerHTML = checks.map((check) => `<span class="is-${check.state}">${check.state === 'pass' ? '✓' : (check.state === 'info' ? 'i' : '!')}</span><strong>${check.label}</strong>`).join('');
	};
	const evidenceState = (value) => value === true ? 'pass' : (value === false ? 'warn' : 'info');
	const renderInjectors = (injectors) => {
		if (!injectorList || !injectorSummary) return;
		injectorList.replaceChildren();
		injectorSummary.replaceChildren();
		const list = Array.isArray(injectors) ? injectors : [];
		const canonical = list.filter((item) => item.state === 'healthy').length;
		const candidates = list.filter((item) => item.state === 'detected' || item.state === 'unknown').length;
		const duplicates = list.filter((item) => item.state === 'potential-duplicate').length;
		const actions = list.filter((item) => item.risk === 'high' || item.risk === 'critical').length;
		[
			['Canonical', canonical, canonical ? 'is-good' : 'is-neutral'],
			['Candidates', candidates, candidates ? 'is-warn' : 'is-neutral'],
			['Potential duplicate', duplicates, duplicates ? 'is-bad' : 'is-neutral'],
			['Recommended actions', actions, actions ? 'is-warn' : 'is-neutral'],
		].forEach(([label, value, state]) => {
			const item = document.createElement('span');
			item.className = state;
			const number = document.createElement('strong');
			number.textContent = String(value);
			const caption = document.createElement('small');
			caption.textContent = label;
			item.append(number, caption);
			injectorSummary.appendChild(item);
		});
		if (!list.length) {
			const empty = document.createElement('p');
			empty.className = 'baocache-analysis-note';
			empty.textContent = 'Không thấy injector public nào ngoài các marker hiện có trong response mẫu. Đây không chứng minh toàn site không còn injector khác.';
			injectorList.appendChild(empty);
			return;
		}
		list.forEach((injector) => {
			const row = document.createElement('article');
			row.className = 'baocache-injector-row';
			const risk = ['info', 'low', 'medium', 'high', 'critical'].includes(injector.risk) ? injector.risk : 'info';
			const state = ['healthy', 'detected', 'potential-duplicate', 'unknown'].includes(injector.state) ? injector.state : 'unknown';
			const stateLabels = { healthy: 'Healthy', detected: 'Detected', 'potential-duplicate': 'Potential duplicate', unknown: 'Unknown' };
			const cells = [
				['Status', stateLabels[state]],
				['Source', injector.source || 'Unknown'],
				['Owner candidate', injector.owner || 'Unknown'],
				['Confidence', `${Math.max(0, Math.min(100, Number(injector.confidence) || 0))}%`],
				['Evidence', injector.evidence || '—'],
				['Risk', risk === 'critical' ? 'Likely duplicate tracking' : (risk === 'high' ? 'Review required' : (risk === 'medium' ? 'Potential overlap' : 'No issue'))],
			];
			cells.forEach(([label, value], index) => {
				const cell = document.createElement('div');
				const caption = document.createElement('span');
				caption.textContent = label;
				const text = document.createElement(index === 0 || index === 5 ? 'strong' : 'small');
				text.textContent = value;
				if (index === 0) text.className = `is-${state}`;
				if (index === 5) text.className = `is-${risk}`;
				cell.append(caption, text);
				row.appendChild(cell);
			});
			const recommendation = document.createElement('div');
			const caption = document.createElement('span');
			caption.textContent = 'Recommendation';
			const text = document.createElement('small');
			text.textContent = injector.recommendation || 'Manual review';
			recommendation.append(caption, text);
			if (risk === 'high' && /gateway/i.test(injector.source || '')) {
				const action = document.createElement('a');
				action.className = 'button button-secondary button-small'; action.target = '_blank'; action.rel = 'noopener'; action.href = 'https://dash.cloudflare.com/'; action.textContent = 'Mở Cloudflare'; recommendation.appendChild(action);
			} else if (/plugin/i.test(injector.owner || '')) {
				const action = document.createElement('a');
				action.className = 'button button-secondary button-small'; action.href = 'plugins.php'; action.textContent = 'Kiểm tra plugin'; recommendation.appendChild(action);
			} else if (state === 'potential-duplicate' || state === 'unknown') {
				const action = document.createElement('a');
				action.className = 'button button-secondary button-small'; action.href = 'themes.php'; action.textContent = 'Kiểm tra theme'; recommendation.appendChild(action);
			}
			row.appendChild(recommendation);
			injectorList.appendChild(row);
		});
	};
	if (analyticsTestButton) analyticsTestButton.addEventListener('click', async () => {
		const result = detectAnalyticsId();
		const consent = settingsForm?.querySelector('[name="baocache_settings[analytics_consent_mode]"]')?.value || 'unset';
		const enabled = settingsForm?.querySelector('[name="baocache_settings[analytics_enabled]"]')?.checked;
		const checks = [
			{ state: result.type === 'ga4' || result.type === 'gtm' ? 'pass' : 'warn', label: result.type === 'ga4' ? 'Measurement ID hợp lệ · Google Analytics 4' : (result.type === 'gtm' ? 'Container ID hợp lệ · Google Tag Manager' : 'Measurement/Container ID hợp lệ') },
			{ state: enabled ? 'pass' : 'warn', label: 'Analytics được bật trong cấu hình' },
			{ state: consent !== 'unset' ? 'pass' : 'info', label: consent !== 'unset' ? 'Consent Mode đã có trạng thái' : 'Consent Mode do CMP/website quyết định · chưa chọn tại BaoCache' },
			{ state: 'pass', label: 'Bootstrap local dùng defer và không inline executable script' },
			{ state: 'info', label: 'Realtime không được gọi từ admin; kiểm tra public frontend, GTM Preview hoặc GA4 DebugView' },
		];
		renderAnalyticsChecks(checks);
		if (!window.BaoCacheAdmin?.analyticsEvidenceNonce) return;
		analyticsTestButton.disabled = true;
		setButtonLabel(analyticsTestButton, 'Đang kiểm tra public…');
		try {
			const evidence = await request({ action: 'baocache_analytics_public_evidence', nonce: BaoCacheAdmin.analyticsEvidenceNonce }, BaoCacheAdmin.analyticsEvidenceError);
			checks.push(
				{ state: evidence.http_status >= 200 && evidence.http_status < 400 ? 'pass' : 'warn', label: `Public frontend · HTTP ${evidence.http_status} · ${evidence.response_ms} ms` },
				{ state: evidenceState(evidence.bootstrap_found), label: 'BaoCache local bootstrap xuất hiện trong HTML public' },
				{ state: evidenceState(evidence.config_found && evidence.configured_id_found), label: 'Analytics config và ID đã lưu xuất hiện trong HTML public' },
				{ state: evidenceState(evidence.csp_script_allows_google), label: evidence.policy_mode === 'none' ? 'Không thấy CSP response header từ request này' : `CSP ${evidence.policy_mode} cho phép tải Google Tag Manager` },
				{ state: evidenceState(evidence.csp_connect_allows_google), label: evidence.policy_mode === 'none' ? 'Không thể xác minh CSP connect-src' : 'CSP connect-src cho phép Analytics/Google endpoint' },
			);
			if (result.type === 'gtm') checks.push({ state: evidenceState(evidence.csp_frame_allows_gtm), label: evidence.policy_mode === 'none' ? 'Không thể xác minh CSP frame-src cho GTM noscript' : 'CSP frame-src cho phép GTM noscript iframe' });
			if (evidence.events_listener_found !== null && evidence.events_listener_found !== undefined) checks.push({ state: evidenceState(evidence.events_listener_found), label: 'Auto Events listener xuất hiện trong HTML public' });
			if (evidence.adapters_listener_found !== null && evidence.adapters_listener_found !== undefined) checks.push({ state: evidenceState(evidence.adapters_listener_found), label: `${evidence.adapter_count || 0} adapter bridge xuất hiện trong HTML public` });
			if (Array.isArray(evidence.unexpected_containers) && evidence.unexpected_containers.length) checks.push({ state: 'warn', label: `Container GTM ngoài cấu hình BaoCache: ${evidence.unexpected_containers.join(', ')} · xác nhận chủ sở hữu trước khi xoá để tránh double tracking` });
			if (Array.isArray(evidence.unexpected_measurements) && evidence.unexpected_measurements.length) checks.push({ state: 'warn', label: `Measurement ID GA4 ngoài cấu hình BaoCache: ${evidence.unexpected_measurements.join(', ')} · chỉ giữ một nguồn phát page_view` });
			if ((!Array.isArray(evidence.unexpected_containers) || !evidence.unexpected_containers.length) && (!Array.isArray(evidence.unexpected_measurements) || !evidence.unexpected_measurements.length) && Array.isArray(evidence.unexpected_ids) && evidence.unexpected_ids.length) checks.push({ state: 'warn', label: `Google ID ngoài cấu hình BaoCache: ${evidence.unexpected_ids.join(', ')}` });
			if ((evidence.events_listener_found === null || evidence.events_listener_found === undefined) && (evidence.adapters_listener_found === null || evidence.adapters_listener_found === undefined)) checks.push({ state: 'info', label: 'Auto Events/adapter chưa được phát vì Consent Mode hoặc adapter opt-in chưa sẵn sàng; đây không phải lỗi runtime' });
			renderInjectors(evidence.injectors);
			renderAnalyticsChecks(checks);
		} catch (error) {
			checks.push({ state: 'warn', label: error.message || BaoCacheAdmin.analyticsEvidenceError });
			if (injectorList) injectorList.innerHTML = '<p class="baocache-analysis-note">Không thể lấy injector evidence từ frontend công khai.</p>';
			renderAnalyticsChecks(checks);
		} finally {
			setButtonLabel(analyticsTestButton, 'Run Analytics Inspector');
			analyticsTestButton.disabled = false;
		}
	});
	if (analyticsCopyButton && analyticsPreview) analyticsCopyButton.addEventListener('click', async () => {
		const text = analyticsPreview.textContent || '';
		try {
			if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(text);
			else { const area = document.createElement('textarea'); area.value = text; area.style.position = 'fixed'; area.style.opacity = '0'; document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove(); }
			showToast('Đã copy preview Analytics.');
		} catch (_) { showToast('Không thể copy preview trên trình duyệt này.', { error: true, duration: 4500 }); }
	});
	if (clearCspReportsButton && window.BaoCacheAdmin?.cspClearReportsNonce) {
		clearCspReportsButton.addEventListener('click', async () => {
			if (!window.confirm('Xóa toàn bộ CSP violation evidence tổng hợp?')) return;
			clearCspReportsButton.disabled = true;
			try {
				await request({ action: 'baocache_csp_clear_reports', nonce: BaoCacheAdmin.cspClearReportsNonce }, BaoCacheAdmin.cspClearReportsError);
				showToast('Đã xóa CSP evidence.');
				window.setTimeout(() => window.location.reload(), 350);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.cspClearReportsError, { error: true, duration: 5500 });
				clearCspReportsButton.disabled = false;
			}
		});
	}
	if (reviewCspEvidenceButton && window.BaoCacheAdmin?.cspReviewEvidenceNonce) {
		reviewCspEvidenceButton.addEventListener('click', async () => {
			const label = reviewCspEvidenceButton.textContent;
			reviewCspEvidenceButton.disabled = true;
			setButtonLabel(reviewCspEvidenceButton, 'Đang rà soát…');
			try {
				const review = await request({ action: 'baocache_csp_review_evidence', nonce: BaoCacheAdmin.cspReviewEvidenceNonce }, BaoCacheAdmin.cspReviewEvidenceError);
				const removed = Number(review.reports_removed || 0) + Number(review.dismissals_removed || 0) + Number(review.ledger_removed || 0);
				showToast(removed ? `Đã dọn ${removed} CSP evidence record quá hạn.` : 'Đã rà soát CSP evidence; không có record quá hạn.');
				window.setTimeout(() => window.location.reload(), 350);
			} catch (error) {
				reviewCspEvidenceButton.disabled = false;
				setButtonLabel(reviewCspEvidenceButton, label);
				showToast(error.message || BaoCacheAdmin.cspReviewEvidenceError, { error: true, duration: 5500 });
			}
		});
	}
	const updateCspRecommendation = async (button, action, nonce, errorMessage) => {
		const recommendation = button.dataset.baocacheApplyCspRecommendation || button.dataset.baocacheDismissCspRecommendation || '';
		if (!recommendation) return;
		const label = button.textContent;
		button.disabled = true;
		setButtonLabel(button, 'Đang lưu…');
		try {
			const response = await request({ action, nonce, recommendation }, errorMessage);
			showToast(response.message || (action.includes('apply') ? 'Đã thêm CSP source.' : 'Đã bỏ qua recommendation.'));
			window.setTimeout(() => window.location.reload(), 350);
		} catch (error) {
			button.disabled = false;
			setButtonLabel(button, label);
			showToast(error.message || errorMessage, { error: true, duration: 5500 });
		}
	};
	cspRecommendationButtons.forEach((button) => button.addEventListener('click', () => updateCspRecommendation(button, 'baocache_csp_apply_recommendation', BaoCacheAdmin.cspApplyRecommendationNonce, BaoCacheAdmin.cspRecommendationError)));
	cspDismissButtons.forEach((button) => button.addEventListener('click', () => updateCspRecommendation(button, 'baocache_csp_dismiss_recommendation', BaoCacheAdmin.cspDismissRecommendationNonce, BaoCacheAdmin.cspRecommendationError)));
	cspRollbackButtons.forEach((button) => button.addEventListener('click', () => {
		const record = button.dataset.baocacheRollbackCspRecommendation;
		if (!record || !window.BaoCacheAdmin?.cspRollbackRecommendationNonce) return;
		const label = button.textContent;
		button.disabled = true;
		setButtonLabel(button, 'Đang rollback…');
		request({ action: 'baocache_csp_rollback_recommendation', nonce: BaoCacheAdmin.cspRollbackRecommendationNonce, record }, BaoCacheAdmin.cspRollbackRecommendationError)
			.then((data) => {
				showToast(data.message || 'Đã rollback CSP source.');
				window.setTimeout(() => window.location.reload(), 350);
			})
			.catch((error) => {
				button.disabled = false;
				setButtonLabel(button, label);
				showToast(error.message || BaoCacheAdmin.cspRollbackRecommendationError, { error: true, duration: 6500 });
		});
	}));
	if (cspPostProbeButton && window.BaoCacheAdmin?.cspPostProbeNonce) {
		cspPostProbeButton.addEventListener('click', async () => {
			const label = cspPostProbeButton.textContent;
			cspPostProbeButton.disabled = true;
			setButtonLabel(cspPostProbeButton, 'Đang kiểm tra…');
			if (cspPostProbeResult) cspPostProbeResult.textContent = 'Đang đọc public response…';
			try {
				const data = await request({ action: 'baocache_csp_post_enforcement_probe', nonce: BaoCacheAdmin.cspPostProbeNonce }, BaoCacheAdmin.cspPostProbeError);
				const resultLabel = data.outcome === 'pass' ? 'PASS' : (data.outcome === 'warn' ? 'REVIEW' : 'FAIL');
				if (cspPostProbeResult) cspPostProbeResult.textContent = `${resultLabel} · HTTP ${data.status_code} · ${data.response_ms} ms · ${data.mode}`;
				showToast(data.message || 'Đã kiểm tra public CSP.');
				window.setTimeout(() => window.location.reload(), 500);
			} catch (error) {
				if (cspPostProbeResult) cspPostProbeResult.textContent = error.message || BaoCacheAdmin.cspPostProbeError;
				showToast(error.message || BaoCacheAdmin.cspPostProbeError, { error: true, duration: 6500 });
				cspPostProbeButton.disabled = false;
				setButtonLabel(cspPostProbeButton, label);
			}
		});
	}
	if (cspManualRollbackButton && window.BaoCacheAdmin?.cspManualRollbackNonce) {
		cspManualRollbackButton.addEventListener('click', async () => {
			if (!window.confirm('Chuyển CSP về Report-Only để rollback thủ công?')) return;
			const label = cspManualRollbackButton.textContent;
			cspManualRollbackButton.disabled = true;
			setButtonLabel(cspManualRollbackButton, 'Đang rollback…');
			try {
				const data = await request({ action: 'baocache_csp_manual_rollback', nonce: BaoCacheAdmin.cspManualRollbackNonce, confirm: '1' }, BaoCacheAdmin.cspManualRollbackError);
				showToast(data.message || 'Đã chuyển CSP về Report-Only.');
				window.setTimeout(() => window.location.reload(), 500);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.cspManualRollbackError, { error: true, duration: 6500 });
				cspManualRollbackButton.disabled = false;
				setButtonLabel(cspManualRollbackButton, label);
			}
		});
	}
	if (cspProbeAckButton && window.BaoCacheAdmin?.cspProbeAckNonce) {
		cspProbeAckButton.addEventListener('click', async () => {
			const label = cspProbeAckButton.textContent;
			cspProbeAckButton.disabled = true;
			setButtonLabel(cspProbeAckButton, 'Đang xác nhận…');
			try {
				const data = await request({ action: 'baocache_csp_probe_acknowledge', nonce: BaoCacheAdmin.cspProbeAckNonce }, BaoCacheAdmin.cspProbeAckError);
				showToast(data.message || 'Đã xác nhận canary failure.');
				window.setTimeout(() => window.location.reload(), 450);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.cspProbeAckError, { error: true, duration: 6500 });
				cspProbeAckButton.disabled = false;
				setButtonLabel(cspProbeAckButton, label);
			}
		});
	}
	if (cspRemediationSaveButtons.length && window.BaoCacheAdmin?.cspRemediationStepNonce) {
		cspRemediationSaveButtons.forEach((button) => button.addEventListener('click', async () => {
			const step = button.dataset.baocacheCspRemediationSave || '';
			const complete = document.querySelector(`[data-baocache-csp-remediation-complete="${step}"]`);
			const note = document.querySelector(`[data-baocache-csp-remediation-note="${step}"]`);
			const label = button.textContent;
			button.disabled = true;
			setButtonLabel(button, 'Đang lưu…');
			try {
				const data = await request({ action: 'baocache_csp_remediation_step', nonce: BaoCacheAdmin.cspRemediationStepNonce, step, completed: complete?.checked ? '1' : '0', note: note?.value || '' }, BaoCacheAdmin.cspRemediationStepError);
				showToast(data.message || 'Đã lưu remediation step.');
				setButtonLabel(button, '✓ Đã lưu');
				window.setTimeout(() => setButtonLabel(button, label), 1800);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.cspRemediationStepError, { error: true, duration: 5500 });
				setButtonLabel(button, label);
			}
			button.disabled = false;
		}));
	}
	const verifyPurgeEndpoint = async () => {
		if (!verifyPurgeButton || !window.BaoCacheAdmin?.purgeVerifyNonce) return;
			const label = verifyPurgeButton.textContent;
			verifyPurgeButton.disabled = true;
			setButtonLabel(verifyPurgeButton, 'Đang xác minh…');
			if (verifyPurgeResult) verifyPurgeResult.textContent = 'Đang kiểm tra Nginx nội bộ…';
			try {
				const data = await request({ action: 'baocache_verify_fastcgi_purge', nonce: BaoCacheAdmin.purgeVerifyNonce }, BaoCacheAdmin.purgeVerifyError);
				if (verifyPurgeResult) verifyPurgeResult.textContent = `✓ ${data.message} HTTP ${data.code}.`;
				showToast('Purge endpoint đã xác minh.');
			} catch (error) {
				if (verifyPurgeResult) verifyPurgeResult.textContent = error.message || BaoCacheAdmin.purgeVerifyError;
				showToast(error.message || BaoCacheAdmin.purgeVerifyError, { error: true, duration: 6500 });
			} finally {
				verifyPurgeButton.disabled = false;
				setButtonLabel(verifyPurgeButton, label);
			}
	};
	if (verifyPurgeButton && window.BaoCacheAdmin?.purgeVerifyNonce) {
		verifyPurgeButton.addEventListener('click', verifyPurgeEndpoint);
	}
	if (purgeUrlButton && purgeUrlInput && window.BaoCacheAdmin?.purgeUrlNonce) {
		purgeUrlButton.addEventListener('click', async () => {
			const url = purgeUrlInput.value.trim();
			const label = purgeUrlButton.textContent;
			if (!url) {
				purgeUrlInput.focus();
				showToast('Nhập URL cùng website cần purge.', { error: true, duration: 4500 });
				return;
			}
			purgeUrlButton.disabled = true;
			setButtonLabel(purgeUrlButton, 'Đang purge…');
			if (purgeUrlResult) purgeUrlResult.textContent = 'Đang gửi purge nội bộ…';
			try {
				const data = await request({ action: 'baocache_purge_fastcgi_url_ajax', nonce: BaoCacheAdmin.purgeUrlNonce, url }, BaoCacheAdmin.purgeUrlError);
				if (purgeUrlResult) purgeUrlResult.textContent = `✓ ${data.message}`;
				setButtonLabel(purgeUrlButton, '✓ Đã purge');
				showToast(data.message);
				window.setTimeout(() => setButtonLabel(purgeUrlButton, label), 2200);
			} catch (error) {
				if (purgeUrlResult) purgeUrlResult.textContent = error.message || BaoCacheAdmin.purgeUrlError;
				showToast(error.message || BaoCacheAdmin.purgeUrlError, { error: true, duration: 7000, action: 'Xác minh endpoint', onAction: verifyPurgeEndpoint });
				setButtonLabel(purgeUrlButton, label);
			} finally {
				purgeUrlButton.disabled = false;
			}
		});
	}
	detectAnalyticsId();

	if (tabs.length && panes.length) {
		const dashboardShell = document.querySelector('.baocache-dashboard-shell');
		const dashboardGroups = {
			dashboard: ['.baocache-status-grid', '.baocache-dashboard-grid', '.baocache-recommendations'],
			diagnostics: ['.baocache-site-diagnostics', '.baocache-compatibility-qa', '.baocache-runtime-history', '.baocache-technical', '.baocache-inspector', '.baocache-database-health', '.baocache-bypass-diagnostics'],
			logs: ['.baocache-activity'],
			cloudflare: ['.baocache-cloudflare-audit'],
		};
		const warmSettingsPanel = settingsForm?.querySelector('[name="baocache_settings[warm_enabled]"]')?.closest('.baocache-panel');
		const warmupRuntimePanel = document.querySelector('.baocache-warmup');
		const activateTab = (tab) => {
			if (!tab) return;
			const current = tab.dataset.baocacheTab;
			tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
			panes.forEach((pane) => pane.classList.toggle('is-hidden', pane.dataset.baocachePane !== current));
			dashboardShell?.setAttribute('data-baocache-active-tab', current);
			settingsForm?.classList.toggle('is-hidden', !['cache', 'wordpress', 'security', 'warmup', 'resources', 'assets', 'analytics', 'settings'].includes(current));
			if (settingsForm) {
				['cache', 'wordpress', 'security', 'warmup', 'resources', 'assets', 'analytics', 'settings'].forEach((name) => settingsForm.classList.remove('is-' + name + '-view'));
				settingsForm.classList.add('is-' + current + '-view');
			}
			if (warmSettingsPanel) warmSettingsPanel.classList.toggle('is-hidden', current !== 'warmup');
			if (warmupRuntimePanel) warmupRuntimePanel.classList.toggle('is-hidden', current !== 'warmup');
			if (dashboardShell) {
				dashboardShell.children && Array.from(dashboardShell.children).forEach((panel) => panel.classList.add('is-hidden'));
				(dashboardGroups[current] || []).forEach((selector) => document.querySelectorAll(selector).forEach((panel) => panel.classList.remove('is-hidden')));
			}
			// Keep deep-links stable across AJAX saves/reloads. The fragment is
			// local UI state only; it never changes the WordPress request.
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${current}`);
			} else {
				window.location.hash = current;
			}
		};
		tabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab)));
		tabShortcuts.forEach((shortcut) => shortcut.addEventListener('click', () => {
			const tab = document.querySelector(`[data-baocache-tab="${shortcut.dataset.baocacheGo}"]`);
			if (tab) activateTab(tab);
		}));
		document.querySelectorAll('.baocache-card[data-baocache-go]').forEach((card) => {
			const target = document.querySelector(`[data-baocache-tab="${card.dataset.baocacheGo}"]`);
			card.addEventListener('click', () => target?.click());
			card.addEventListener('keydown', (event) => {
				if (event.key !== 'Enter' && event.key !== ' ') return;
				event.preventDefault();
				card.click();
			});
		});
		const requestedTab = window.location.hash.replace(/^#/, '');
		const requestedTabButton = requestedTab ? Array.from(tabs).find((item) => item.dataset.baocacheTab === requestedTab) : null;
		activateTab(requestedTabButton || document.querySelector('[data-baocache-tab].is-active') || tabs[0]);
	}

	if (assetTabs.length && assetPanes.length) {
		const activateAssetTab = (tab) => {
			const current = tab.dataset.baocacheAssetsTab;
			assetTabs.forEach((item) => item.classList.toggle('is-active', item === tab));
			assetPanes.forEach((pane) => pane.classList.toggle('is-hidden', pane.dataset.baocacheAssetsPane !== current));
		};
		assetTabs.forEach((tab) => tab.addEventListener('click', () => activateAssetTab(tab)));
		activateAssetTab(document.querySelector('[data-baocache-assets-tab].is-active') || assetTabs[0]);
	}

	if (cspTabs.length && cspPanes.length) {
		const activateCspTab = (tab) => {
			if (!tab) return;
			const current = tab.dataset.baocacheCspTab;
			cspTabs.forEach((item) => {
				const active = item === tab;
				item.classList.toggle('is-active', active);
				item.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			cspPanes.forEach((pane) => pane.classList.toggle('is-active', pane.dataset.baocacheCspPane === current));
		};
		cspTabs.forEach((tab) => tab.addEventListener('click', () => activateCspTab(tab)));
		activateCspTab(document.querySelector('[data-baocache-csp-tab].is-active') || cspTabs[0]);
	}

	const filterAssets = () => {
		const query = (assetSearch?.value || '').trim().toLowerCase();
		const type = assetType?.value || 'all';
		const source = assetSource?.value || 'all';
		document.querySelectorAll('[data-baocache-asset-row]').forEach((row) => {
			const visible = (type === 'all' || row.dataset.baocacheType === type)
				&& (source === 'all' || row.dataset.baocacheSource === source)
				&& (!query || (row.dataset.baocacheSearch || '').includes(query));
			row.hidden = !visible;
		});
		document.querySelectorAll('[data-baocache-asset-group]').forEach((group) => {
			group.hidden = !group.querySelector('[data-baocache-asset-row]:not([hidden])');
		});
	};
	assetSearch?.addEventListener('input', filterAssets);
	assetType?.addEventListener('change', filterAssets);
	assetSource?.addEventListener('change', filterAssets);

	if (assetScanButtons.length && window.BaoCacheAdmin) {
		assetScanButtons.forEach((button) => button.addEventListener('click', async () => {
			const label = button.textContent;
			assetScanButtons.forEach((item) => { item.disabled = true; setButtonLabel(item, 'Đang quét…'); });
			showToast('Đang quét Asset Inventory qua Nginx nội bộ…', { duration: 0 });
			try {
				const data = await request({ action: 'baocache_scan_assets', nonce: BaoCacheAdmin.scanNonce }, BaoCacheAdmin.scanError);
				showToast(`Quét xong ${data.count} assets. Đang tải lại Inventory…`, { duration: 1000 });
				window.setTimeout(() => window.location.reload(), 650);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.scanError, { error: true, duration: 8000 });
				assetScanButtons.forEach((item) => { item.disabled = false; setButtonLabel(item, label); });
			}
		}));
	}

	if (criticalImageScanButton && window.BaoCacheAdmin) {
		criticalImageScanButton.addEventListener('click', async () => {
			const label = criticalImageScanButton.textContent;
			criticalImageScanButton.disabled = true;
			setButtonLabel(criticalImageScanButton, 'Đang phân tích…');
			showToast('Đang đọc DOM frontend công khai…', { duration: 0 });
			try {
				const data = await request({ action: 'baocache_scan_critical_images', nonce: BaoCacheAdmin.criticalImageScanNonce }, BaoCacheAdmin.criticalImageScanError);
				showToast(`Đã tìm thấy ${data.count} critical image candidate.`);
				window.setTimeout(() => window.location.reload(), 650);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.criticalImageScanError, { error: true, duration: 7000 });
				criticalImageScanButton.disabled = false;
				setButtonLabel(criticalImageScanButton, label);
			}
		});
	}

	criticalImageApplyButtons.forEach((button) => button.addEventListener('click', async () => {
		if (!window.confirm('Áp dụng candidate này cho trang chủ? BaoCache sẽ chạy post-change probe và tự rollback nếu output không hợp lệ.')) return;
		const label = button.textContent;
		button.disabled = true;
		setButtonLabel(button, 'Đang xác minh…');
		showToast('Đang áp dụng và kiểm tra public HTML…', { duration: 0 });
		try {
			const data = await request({ action: 'baocache_apply_critical_image', nonce: BaoCacheAdmin.criticalImageApplyNonce, fingerprint: button.dataset.baocacheApplyCriticalImage || '' }, BaoCacheAdmin.criticalImageApplyError);
			const imageInput = document.querySelector('[name="baocache_settings[lcp_image]"]');
			const scopeInput = document.querySelector('[name="baocache_settings[lcp_scope]"]');
			if (imageInput) imageInput.value = data.candidate?.url || '';
			if (scopeInput) scopeInput.value = 'front-page';
			showToast('Đã áp dụng: image, preload và fetchpriority đều PASS.');
			window.setTimeout(() => window.location.reload(), 800);
		} catch (error) {
			showToast(error.message || BaoCacheAdmin.criticalImageApplyError, { error: true, duration: 8000 });
			button.disabled = false;
			setButtonLabel(button, label);
		}
	}));

	if (criticalImageRollbackButton && window.BaoCacheAdmin) {
		criticalImageRollbackButton.addEventListener('click', async () => {
			if (!window.confirm('Rollback về cấu hình Critical Image trước lần áp dụng tự động?')) return;
			criticalImageRollbackButton.disabled = true;
			setButtonLabel(criticalImageRollbackButton, 'Đang rollback…');
			try {
				const data = await request({ action: 'baocache_rollback_critical_image', nonce: BaoCacheAdmin.criticalImageRollbackNonce }, BaoCacheAdmin.criticalImageRollbackError);
				showToast(data.message || 'Đã rollback Critical Image.');
				window.setTimeout(() => window.location.reload(), 650);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.criticalImageRollbackError, { error: true, duration: 7000 });
				criticalImageRollbackButton.disabled = false;
				setButtonLabel(criticalImageRollbackButton, 'Rollback');
			}
		});
	}

	if (resourceHintScanButton && window.BaoCacheAdmin) {
		resourceHintScanButton.addEventListener('click', async () => {
			resourceHintScanButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_scan_resource_hints', nonce: BaoCacheAdmin.resourceHintScanNonce }, 'Không thể tạo recommendation Resource Hints.');
				if (data.count > 0) {
					if (resourceHintResult) resourceHintResult.textContent = `${data.count} candidate · fingerprint ${String(data.fingerprint).slice(0, 12)}. Tải lại trang để apply.`;
					showToast('Đã tạo recommendation Resource & Font Hints.');
				} else {
					if (resourceHintResult) resourceHintResult.textContent = 'Đã quét Asset Inventory nhưng chưa có origin/font đủ evidence. Không có gì để apply.';
					showToast('Chưa có Resource Hint đủ evidence để đề xuất.');
				}
			} catch (error) {
				showToast(error.message || 'Không thể tạo recommendation Resource Hints.', { error: true });
			} finally { resourceHintScanButton.disabled = false; }
		});
	}

	if (resourceHintApplyButton && window.BaoCacheAdmin) {
		resourceHintApplyButton.addEventListener('click', async () => {
			resourceHintApplyButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_apply_resource_hints', nonce: BaoCacheAdmin.resourceHintApplyNonce, fingerprint: resourceHintApplyButton.dataset.fingerprint || '' }, 'Không thể áp dụng Resource Hints.');
				if (resourceHintResult) resourceHintResult.textContent = data.message || 'Đã apply.';
				showToast(data.message || 'Đã áp dụng Resource Hints.');
			} catch (error) { showToast(error.message || 'Không thể áp dụng Resource Hints.', { error: true }); }
			finally { resourceHintApplyButton.disabled = false; }
		});
	}

	if (resourceHintRollbackButton && window.BaoCacheAdmin) {
		resourceHintRollbackButton.addEventListener('click', async () => {
			resourceHintRollbackButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_rollback_resource_hints', nonce: BaoCacheAdmin.resourceHintRollbackNonce }, 'Không thể rollback Resource Hints.');
				if (resourceHintResult) resourceHintResult.textContent = data.message || 'Đã rollback.';
				showToast(data.message || 'Đã rollback Resource Hints.');
			} catch (error) { showToast(error.message || 'Không thể rollback Resource Hints.', { error: true }); }
			finally { resourceHintRollbackButton.disabled = false; }
		});
	}

	if (thirdPartyScanButton && window.BaoCacheAdmin) {
		thirdPartyScanButton.addEventListener('click', async () => {
			thirdPartyScanButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_scan_third_party', nonce: BaoCacheAdmin.thirdPartyScanNonce }, 'Không thể phân tích third-party script.');
				if (thirdPartyResult) thirdPartyResult.textContent = `${data.count} candidate · fingerprint ${String(data.fingerprint).slice(0, 12)}. Tải lại trang để apply.`;
				showToast('Đã tạo third-party recommendation.');
			} catch (error) { showToast(error.message || 'Không thể phân tích third-party script.', { error: true }); }
			finally { thirdPartyScanButton.disabled = false; }
		});
	}

	if (thirdPartyApplyButton && window.BaoCacheAdmin) {
		thirdPartyApplyButton.addEventListener('click', async () => {
			thirdPartyApplyButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_apply_third_party', nonce: BaoCacheAdmin.thirdPartyApplyNonce, fingerprint: thirdPartyApplyButton.dataset.fingerprint || '' }, 'Không thể apply third-party delay.');
				if (thirdPartyResult) thirdPartyResult.textContent = data.message || 'Đã apply.';
				showToast(data.message || 'Đã apply third-party delay.');
			} catch (error) { showToast(error.message || 'Không thể apply third-party delay.', { error: true }); }
			finally { thirdPartyApplyButton.disabled = false; }
		});
	}

	if (thirdPartyRollbackButton && window.BaoCacheAdmin) {
		thirdPartyRollbackButton.addEventListener('click', async () => {
			thirdPartyRollbackButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_rollback_third_party', nonce: BaoCacheAdmin.thirdPartyRollbackNonce }, 'Không thể rollback third-party delay.');
				if (thirdPartyResult) thirdPartyResult.textContent = data.message || 'Đã rollback.';
				showToast(data.message || 'Đã rollback third-party delay.');
			} catch (error) { showToast(error.message || 'Không thể rollback third-party delay.', { error: true }); }
			finally { thirdPartyRollbackButton.disabled = false; }
		});
	}

	if (commerceScanButton && window.BaoCacheAdmin) {
		commerceScanButton.addEventListener('click', async () => {
			commerceScanButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_scan_commerce', nonce: BaoCacheAdmin.commerceScanNonce }, 'Không thể quét commerce evidence.');
				if (commerceResult) commerceResult.textContent = `${data.count} route · fingerprint ${String(data.fingerprint).slice(0, 12)}. Tải lại trang để áp dụng.`;
				showToast('Đã tạo commerce protection evidence.');
			} catch (error) { showToast(error.message || 'Không thể quét commerce evidence.', { error: true }); }
			finally { commerceScanButton.disabled = false; }
		});
	}

	if (commerceApplyButton && window.BaoCacheAdmin) {
		commerceApplyButton.addEventListener('click', async () => {
			commerceApplyButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_apply_commerce', nonce: BaoCacheAdmin.commerceApplyNonce, fingerprint: commerceApplyButton.dataset.fingerprint || '' }, 'Không thể áp dụng commerce protection.');
				if (commerceResult) commerceResult.textContent = data.message || 'Đã áp dụng.';
				showToast(data.message || 'Đã bảo vệ route commerce.');
			} catch (error) { showToast(error.message || 'Không thể áp dụng commerce protection.', { error: true }); }
			finally { commerceApplyButton.disabled = false; }
		});
	}

	if (commerceRollbackButton && window.BaoCacheAdmin) {
		commerceRollbackButton.addEventListener('click', async () => {
			commerceRollbackButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_rollback_commerce', nonce: BaoCacheAdmin.commerceRollbackNonce }, 'Không thể rollback commerce protection.');
				if (commerceResult) commerceResult.textContent = data.message || 'Đã rollback.';
				showToast(data.message || 'Đã rollback commerce protection.');
			} catch (error) { showToast(error.message || 'Không thể rollback commerce protection.', { error: true }); }
			finally { commerceRollbackButton.disabled = false; }
		});
	}

	if (adapterScanButton && window.BaoCacheAdmin) {
		adapterScanButton.addEventListener('click', async () => {
			adapterScanButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_scan_theme_builders', nonce: BaoCacheAdmin.adapterScanNonce }, 'Không thể quét adapter evidence.');
				if (adapterResult) adapterResult.textContent = `${data.count} handle · fingerprint ${String(data.fingerprint).slice(0, 12)}. Tải lại trang để áp dụng.`;
				showToast('Đã tạo adapter evidence.');
			} catch (error) { showToast(error.message || 'Không thể quét adapter evidence.', { error: true }); }
			finally { adapterScanButton.disabled = false; }
		});
	}

	if (adapterApplyButton && window.BaoCacheAdmin) {
		adapterApplyButton.addEventListener('click', async () => {
			adapterApplyButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_apply_theme_builders', nonce: BaoCacheAdmin.adapterApplyNonce, fingerprint: adapterApplyButton.dataset.fingerprint || '' }, 'Không thể áp dụng adapter exclusions.');
				if (adapterResult) adapterResult.textContent = data.message || 'Đã áp dụng.';
				showToast(data.message || 'Đã bảo vệ adapter handles.');
			} catch (error) { showToast(error.message || 'Không thể áp dụng adapter exclusions.', { error: true }); }
			finally { adapterApplyButton.disabled = false; }
		});
	}

	if (adapterRollbackButton && window.BaoCacheAdmin) {
		adapterRollbackButton.addEventListener('click', async () => {
			adapterRollbackButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_rollback_theme_builders', nonce: BaoCacheAdmin.adapterRollbackNonce }, 'Không thể rollback adapter exclusions.');
				if (adapterResult) adapterResult.textContent = data.message || 'Đã rollback.';
				showToast(data.message || 'Đã rollback adapter exclusions.');
			} catch (error) { showToast(error.message || 'Không thể rollback adapter exclusions.', { error: true }); }
			finally { adapterRollbackButton.disabled = false; }
		});
	}

	if (clearFrontendMetricsButton && window.BaoCacheAdmin) {
		clearFrontendMetricsButton.addEventListener('click', async () => {
			if (!window.confirm('Xóa toàn bộ Browser Resource Timing đã lưu?')) return;
			clearFrontendMetricsButton.disabled = true;
			setButtonLabel(clearFrontendMetricsButton, 'Đang xóa…');
			try {
				await request({ action: 'baocache_clear_frontend_metrics', nonce: BaoCacheAdmin.clearFrontendMetricsNonce }, BaoCacheAdmin.clearFrontendMetricsError);
				showToast('Đã xóa Browser Resource Timing.');
				window.setTimeout(() => window.location.reload(), 650);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.clearFrontendMetricsError, { error: true, duration: 5500 });
				clearFrontendMetricsButton.disabled = false;
				setButtonLabel(clearFrontendMetricsButton, 'Xóa dữ liệu mẫu');
			}
		});
	}

	if (hardeningProbeButton && hardeningProbeResult && window.BaoCacheAdmin) {
		hardeningProbeButton.addEventListener('click', async () => {
			hardeningProbeButton.disabled = true;
			setButtonLabel(hardeningProbeButton, 'Đang kiểm tra…');
			hardeningProbeResult.textContent = 'Đang gửi request public không đăng nhập…';
			try {
				const data = await request({ action: 'baocache_probe_hardening', nonce: BaoCacheAdmin.hardeningProbeNonce }, BaoCacheAdmin.hardeningProbeError);
				hardeningProbeResult.replaceChildren();
				const summary = document.createElement('strong');
				const passed = (data.checks || []).filter((check) => check.state === 'good').length;
				const regressions = data.regressions || [];
				const baselineReady = (data.checks || []).length > 0 && !(data.checks || []).some((check) => ['warn', 'bad'].includes(check.state));
				if (hardeningBaselineButton) hardeningBaselineButton.disabled = !baselineReady;
				summary.textContent = `${passed}/${(data.checks || []).length} PASS · ${data.response_ms || 0} ms${regressions.length ? ` · ${regressions.length} regression` : ''}`;
				hardeningProbeResult.appendChild(summary);
				if (regressions.length) {
					const regressionNote = document.createElement('div');
					regressionNote.className = 'is-regression';
					regressionNote.textContent = `Regression: ${regressions.map((item) => `${item.label} → ${item.value}`).join(' · ')}`;
					hardeningProbeResult.appendChild(regressionNote);
				}
				const list = document.createElement('ul');
				(data.checks || []).forEach((check) => {
					const item = document.createElement('li');
					item.className = `is-${check.state || 'neutral'}`;
					item.textContent = `${check.label}: ${check.value}`;
					list.appendChild(item);
				});
				hardeningProbeResult.appendChild(list);
				showToast(regressions.length ? `Phát hiện ${regressions.length} regression từ lần probe trước.` : 'Đã hoàn tất Public Response Probe.', regressions.length ? { error: true, duration: 6500 } : {});
			} catch (error) {
				hardeningProbeResult.textContent = error.message || BaoCacheAdmin.hardeningProbeError;
				showToast(error.message || BaoCacheAdmin.hardeningProbeError, { error: true, duration: 5500 });
			} finally {
				hardeningProbeButton.disabled = false;
				setButtonLabel(hardeningProbeButton, 'Probe public response');
			}
		});
	}

	if (hardeningBaselineButton && hardeningBaselineResult && window.BaoCacheAdmin) {
		hardeningBaselineButton.addEventListener('click', async () => {
			hardeningBaselineButton.disabled = true;
			setButtonLabel(hardeningBaselineButton, 'Đang đặt…');
			try {
				const data = await request({ action: 'baocache_set_hardening_baseline', nonce: BaoCacheAdmin.hardeningBaselineNonce }, BaoCacheAdmin.hardeningBaselineError);
				hardeningBaselineResult.textContent = `Baseline đã đặt · ${data.checks} checks`;
				showToast('Đã đặt baseline Hardening từ probe PASS.');
			} catch (error) {
				hardeningBaselineResult.textContent = error.message || BaoCacheAdmin.hardeningBaselineError;
				showToast(error.message || BaoCacheAdmin.hardeningBaselineError, { error: true, duration: 5500 });
			} finally {
				setButtonLabel(hardeningBaselineButton, 'Đặt baseline từ probe PASS');
				hardeningBaselineButton.disabled = false;
			}
		});
	}

	document.querySelectorAll('[data-baocache-ack-probe]').forEach((button) => {
		button.addEventListener('click', async () => {
			const probeId = button.dataset.baocacheAckProbe || '';
			if (!probeId || !window.BaoCacheAdmin) return;
			button.disabled = true;
			const originalLabel = button.textContent;
			setButtonLabel(button, 'Đang lưu…');
			try {
				await request({ action: 'baocache_ack_hardening_probe', nonce: BaoCacheAdmin.hardeningAckNonce, probe_id: probeId }, BaoCacheAdmin.hardeningAckError);
				setButtonLabel(button, 'Đã xác nhận');
				button.classList.add('is-acknowledged');
				showToast('Đã xác nhận cảnh báo probe.');
			} catch (error) {
				setButtonLabel(button, originalLabel);
				button.disabled = false;
				showToast(error.message || BaoCacheAdmin.hardeningAckError, { error: true, duration: 5500 });
			}
		});
	});

	if (renderBlockingImportButton && renderBlockingJson && renderBlockingResult && window.BaoCacheAdmin) {
		renderBlockingImportButton.addEventListener('click', async () => {
			if (!renderBlockingJson.value.trim()) {
				renderBlockingResult.textContent = 'Dán JSON Lighthouse trước khi nhập.';
				return;
			}
			renderBlockingImportButton.disabled = true;
			setButtonLabel(renderBlockingImportButton, 'Đang phân tích…');
			renderBlockingResult.textContent = 'Đang map resource về WordPress handle…';
			try {
				const data = await request({ action: 'baocache_import_render_blocking_audit', nonce: BaoCacheAdmin.renderBlockingImportNonce, snapshot: renderBlockingSnapshot?.value || 'after', json: renderBlockingJson.value }, BaoCacheAdmin.renderBlockingImportError);
				const snapshot = renderBlockingSnapshot?.value || 'after';
				const count = data.snapshots?.[snapshot]?.items?.length || 0;
				renderBlockingResult.textContent = `Đã nhập ${count} resource render-blocking (${snapshot}). Đang tải lại Analysis…`;
				showToast('Đã nhập Lighthouse audit.');
				window.setTimeout(() => window.location.reload(), 650);
			} catch (error) {
				renderBlockingResult.textContent = error.message || BaoCacheAdmin.renderBlockingImportError;
				showToast(error.message || BaoCacheAdmin.renderBlockingImportError, { error: true, duration: 5500 });
				renderBlockingImportButton.disabled = false;
				setButtonLabel(renderBlockingImportButton, 'Nhập audit');
			}
		});
	}

	document.querySelectorAll('[data-baocache-preview-render-blocking]').forEach((button) => {
		button.addEventListener('click', async () => {
			const handle = button.dataset.baocachePreviewRenderBlocking || '';
			if (!handle || !window.BaoCacheAdmin) return;
			const originalLabel = button.textContent;
			button.disabled = true;
			setButtonLabel(button, 'Đang kiểm tra…');
			try {
				const data = await request({ action: 'baocache_preview_render_blocking', nonce: BaoCacheAdmin.renderBlockingPreviewNonce, handle }, BaoCacheAdmin.renderBlockingPreviewError);
				showToast(`${data.eligible ? 'Có thể defer' : 'Cần loại trừ'} · ${data.reason}`, { error: !data.eligible, duration: 6500 });
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.renderBlockingPreviewError, { error: true, duration: 5500 });
			} finally {
				button.disabled = false;
				setButtonLabel(button, originalLabel);
			}
		});
	});

	if (contextQaButton && contextQaResult && window.BaoCacheAdmin) {
		contextQaButton.addEventListener('click', async () => {
			contextQaButton.disabled = true;
			setButtonLabel(contextQaButton, 'Đang kiểm tra…');
			contextQaResult.textContent = 'Đang kiểm tra handle, URL và context…';
			try {
				const data = await request({
					action: 'baocache_render_blocking_context_qa',
					nonce: BaoCacheAdmin.renderBlockingContextNonce,
					path: contextQaPath?.value || '/',
					handle: contextQaHandle?.value || '',
					logged_in: contextQaLoggedIn?.checked ? '1' : '',
					preview: contextQaPreview?.checked ? '1' : '',
					checkout: contextQaCheckout?.checked ? '1' : '',
				}, BaoCacheAdmin.renderBlockingContextError);
				const reasons = Array.isArray(data.reasons) && data.reasons.length ? ` · ${data.reasons.join(' ')}` : '';
				contextQaResult.textContent = `${data.eligible ? 'PASS · Có thể áp dụng strategy' : 'BYPASS · Không áp dụng strategy'}${reasons}`;
				showToast(data.eligible ? 'Context đủ điều kiện cho strategy.' : 'Context đang được loại trừ để an toàn.', { error: !data.eligible, duration: 5000 });
			} catch (error) {
				contextQaResult.textContent = error.message || BaoCacheAdmin.renderBlockingContextError;
				showToast(error.message || BaoCacheAdmin.renderBlockingContextError, { error: true, duration: 5500 });
			} finally {
				contextQaButton.disabled = false;
				setButtonLabel(contextQaButton, 'Kiểm tra context');
			}
		});
	}

	if (compatibilityQaSaveButton && compatibilityQaResult && window.BaoCacheAdmin) {
		compatibilityQaSaveButton.addEventListener('click', async () => {
			const checks = {};
		document.querySelectorAll('[data-baocache-compatibility-check]').forEach((select) => {
				checks[select.dataset.baocacheCompatibilityCheck || ''] = select.value;
			});
			compatibilityQaSaveButton.disabled = true;
			setButtonLabel(compatibilityQaSaveButton, 'Đang lưu…');
			try {
				const data = await request({ action: 'baocache_save_compatibility_qa', nonce: BaoCacheAdmin.compatibilityQaSaveNonce, checks: JSON.stringify(checks) }, BaoCacheAdmin.compatibilityQaError);
				compatibilityQaResult.textContent = `Đã lưu · ${data.passed || 0} PASS · ${data.failed || 0} FAIL`;
				showToast(data.failed > 0 ? 'Đã lưu QA, còn hạng mục FAIL cần xử lý.' : 'Đã lưu kết quả staging QA.');
				window.location.hash = 'diagnostics';
				window.setTimeout(() => window.location.reload(), 650);
			} catch (error) {
				compatibilityQaResult.textContent = error.message || BaoCacheAdmin.compatibilityQaError;
				showToast(error.message || BaoCacheAdmin.compatibilityQaError, { error: true, duration: 5500 });
				compatibilityQaSaveButton.disabled = false;
				setButtonLabel(compatibilityQaSaveButton, 'Lưu kết quả QA');
			}
		});
	}

	if (compatibilityQaResetButton && window.BaoCacheAdmin) {
		compatibilityQaResetButton.addEventListener('click', async () => {
			if (!window.confirm('Reset toàn bộ checklist Compatibility QA?')) return;
			compatibilityQaResetButton.disabled = true;
			try {
				await request({ action: 'baocache_reset_compatibility_qa', nonce: BaoCacheAdmin.compatibilityQaResetNonce }, BaoCacheAdmin.compatibilityQaError);
				showToast('Đã reset checklist QA.');
				window.location.hash = 'diagnostics';
				window.setTimeout(() => window.location.reload(), 500);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.compatibilityQaError, { error: true, duration: 5500 });
				compatibilityQaResetButton.disabled = false;
			}
		});
	}

	document.querySelectorAll('[data-baocache-save-rule-gate]').forEach((button) => {
		button.addEventListener('click', async () => {
			const row = button.closest('[data-baocache-rule-gate-row]');
			if (!row || !window.BaoCacheAdmin) return;
			const qa = row.querySelector('[data-baocache-gate-qa]')?.value || 'pending';
			const rollback = row.querySelector('[data-baocache-gate-rollback]')?.checked ? '1' : '';
			const handle = row.dataset.baocacheGateHandle || '';
			const strategy = row.dataset.baocacheGateStrategy || '';
			const status = row.querySelector('[data-baocache-gate-status]');
			const originalLabel = button.textContent;
			button.disabled = true;
			setButtonLabel(button, 'Đang lưu…');
			try {
				const data = await request({ action: 'baocache_save_rule_gate', nonce: BaoCacheAdmin.ruleGateSaveNonce, handle, strategy, qa, rollback_verified: rollback }, BaoCacheAdmin.ruleGateError);
				if (status) {
					status.textContent = data.stale ? (data.acknowledged ? 'Stale · đã xem' : 'Stale · lưu lại gate') : (data.allowed ? 'Được phép' : 'Bị chặn production');
					status.className = 'baocache-badge is-' + (data.stale ? 'warn' : (data.allowed ? 'good' : 'warn'));
				}
				showToast(data.stale ? handle + ': evidence đã thay đổi, hãy lưu lại gate sau khi QA.' : (data.allowed ? handle + ': gate PASS, production được phép.' : handle + ': production vẫn bị chặn; cần QA PASS và rollback.'));
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.ruleGateError, { error: true, duration: 5500 });
			} finally {
				button.disabled = false;
				setButtonLabel(button, originalLabel);
			}
		});
	});

	const gateHistoryJson = document.querySelector('[data-baocache-gate-history-json]');
	const gateDiffDrawer = document.querySelector('[data-baocache-gate-diff-drawer]');
	const gateDiffBody = gateDiffDrawer?.querySelector('[data-baocache-gate-diff-body]');
	const gateDiffTitle = gateDiffDrawer?.querySelector('[data-baocache-gate-diff-title]');
	const gateDiffSubtitle = gateDiffDrawer?.querySelector('[data-baocache-gate-diff-subtitle]');
	const gateDiffClose = gateDiffDrawer?.querySelector('[data-baocache-gate-diff-close]');
	const gateAckButton = gateDiffDrawer?.querySelector('[data-baocache-ack-stale-gate]');
	const gatePruneButton = gateDiffDrawer?.querySelector('[data-baocache-prune-gate-history]');
	const gatePolicy = gateDiffDrawer?.querySelector('[data-baocache-gate-policy]');
	if (gateHistoryJson && gateDiffDrawer && gateDiffBody) {
		let gateHistory = {};
		let activeGate = null;
		try { gateHistory = JSON.parse(gateHistoryJson.textContent || '{}'); } catch (error) { gateHistory = {}; }
		const closeGateDiff = () => { gateDiffDrawer.classList.add('is-hidden'); gateDiffDrawer.setAttribute('aria-hidden', 'true'); activeGate = null; if (gateAckButton) gateAckButton.hidden = true; };
		const openGateDiff = (row, key) => {
			const history = Array.isArray(gateHistory[key]) ? gateHistory[key] : [];
			const handle = row.dataset.baocacheGateHandle || '';
			const strategy = row.dataset.baocacheGateStrategy || '';
			activeGate = { handle, strategy };
			const statusText = row.querySelector('[data-baocache-gate-status]')?.textContent || '';
			if (gateAckButton) { gateAckButton.hidden = !statusText.includes('Stale') || statusText.includes('đã xem'); gateAckButton.disabled = false; }
			if (gateDiffTitle) gateDiffTitle.textContent = handle + ' · ' + strategy.toUpperCase();
			if (gateDiffSubtitle) gateDiffSubtitle.textContent = 'Evidence history · ' + (history.length ? history.length + ' bản ghi' : 'chưa có bản ghi');
			gateDiffBody.textContent = '';
			if (!history.length) {
				const empty = document.createElement('p');
				empty.className = 'baocache-analysis-note';
				empty.textContent = 'Chưa có lịch sử. Lưu gate lần đầu để tạo evidence reference.';
				gateDiffBody.appendChild(empty);
			} else {
				history.forEach((item) => {
					const entry = document.createElement('article');
					entry.className = 'baocache-gate-diff-entry';
					const heading = document.createElement('div');
					heading.className = 'baocache-gate-diff-entry__heading';
					const time = document.createElement('strong');
					time.textContent = item.at ? new Date(item.at * 1000).toLocaleString() : '—';
					const state = document.createElement('span');
					state.className = 'baocache-badge ' + (item.qa === 'pass' && item.rollback_verified ? 'is-good' : 'is-warn');
					state.textContent = (item.qa || 'pending').toUpperCase() + (item.rollback_verified ? ' · rollback' : '');
					heading.append(time, state);
					entry.appendChild(heading);
					const change = document.createElement('p');
					const changed = Object.keys(item.changed || {}).filter((name) => item.changed[name]);
					change.textContent = 'Thay đổi: ' + (changed.length ? changed.join(', ') : (item.change || 'reapproval'));
					entry.appendChild(change);
					const refs = document.createElement('dl');
					refs.className = 'baocache-gate-diff-entry__refs';
					[['Evidence mới', item.evidence_ref], ['Evidence trước', item.previous_ref], ['Môi trường', item.environment]].forEach(([label, value]) => {
						const wrapper = document.createElement('div');
						const term = document.createElement('dt');
						term.textContent = label;
						const definition = document.createElement('dd');
						definition.textContent = value || '—';
						wrapper.append(term, definition);
						refs.appendChild(wrapper);
					});
					entry.appendChild(refs);
					gateDiffBody.appendChild(entry);
				});
			}
			gateDiffDrawer.classList.remove('is-hidden');
			gateDiffDrawer.setAttribute('aria-hidden', 'false');
			gateDiffClose?.focus();
		};
		document.querySelectorAll('[data-baocache-rule-gate-row]').forEach((row) => {
			const actionCell = row.lastElementChild;
			if (!actionCell) return;
			const handle = row.dataset.baocacheGateHandle || '';
			const strategy = row.dataset.baocacheGateStrategy || '';
			const key = handle + '__' + strategy;
			const diffButton = document.createElement('button');
			diffButton.type = 'button';
			diffButton.className = 'button button-small baocache-gate-diff-button';
			diffButton.textContent = 'Diff';
			diffButton.addEventListener('click', () => openGateDiff(row, key));
			actionCell.appendChild(diffButton);
		});
		gateDiffClose?.addEventListener('click', closeGateDiff);
		gateAckButton?.addEventListener('click', async () => {
			if (!activeGate || !window.BaoCacheAdmin) return;
			gateAckButton.disabled = true;
			try {
				await request({ action: 'baocache_ack_stale_gate', nonce: BaoCacheAdmin.gateAckNonce, handle: activeGate.handle, strategy: activeGate.strategy }, BaoCacheAdmin.gateAckError);
				showToast('Đã đánh dấu stale gate là đã xem; production vẫn bị chặn cho tới khi lưu evidence mới.');
				window.setTimeout(() => window.location.reload(), 500);
			} catch (error) { gateAckButton.disabled = false; showToast(error.message || BaoCacheAdmin.gateAckError, { error: true, duration: 5500 }); }
		});
		gatePruneButton?.addEventListener('click', async () => {
			if (!window.BaoCacheAdmin || !window.confirm('Dọn các bản ghi evidence quá 90 ngày?')) return;
			gatePruneButton.disabled = true;
			try {
				const data = await request({ action: 'baocache_prune_gate_history', nonce: BaoCacheAdmin.gateHistoryPruneNonce }, BaoCacheAdmin.gateHistoryError);
				if (gatePolicy && data.policy) gatePolicy.textContent = 'Policy: giữ ' + data.policy.retention_days + ' ngày · tối đa ' + data.policy.max_entries + ' bản ghi · hiện có ' + data.policy.count;
				showToast('Đã dọn ' + (data.removed || 0) + ' bản ghi quá hạn.');
			} catch (error) { showToast(error.message || BaoCacheAdmin.gateHistoryError, { error: true, duration: 5500 }); }
			finally { gatePruneButton.disabled = false; }
		});
		gateDiffDrawer.addEventListener('click', (event) => { if (event.target === gateDiffDrawer) closeGateDiff(); });
		document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !gateDiffDrawer.classList.contains('is-hidden')) closeGateDiff(); });
	}

	const gateReviewButton = document.querySelector('[data-baocache-review-gates]');
	if (gateReviewButton && window.BaoCacheAdmin) {
		gateReviewButton.addEventListener('click', async () => {
			gateReviewButton.disabled = true;
			setButtonLabel(gateReviewButton, 'Đang rà soát…');
			try {
				const data = await request({ action: 'baocache_review_gate_evidence', nonce: BaoCacheAdmin.gateReviewNonce }, BaoCacheAdmin.gateReviewError);
				showToast('Đã rà soát ' + (data.total || 0) + ' gate · ' + (data.stale_count || 0) + ' stale.');
				window.setTimeout(() => window.location.reload(), 500);
			} catch (error) { showToast(error.message || BaoCacheAdmin.gateReviewError, { error: true, duration: 5500 }); }
			finally { setButtonLabel(gateReviewButton, 'Rà soát ngay'); gateReviewButton.disabled = false; }
		});
	}

	if (criticalCssStageButton && criticalCssInput && criticalCssStageResult && window.BaoCacheAdmin) {
		criticalCssStageButton.addEventListener('click', async () => {
			if (!criticalCssInput.value.trim()) {
				criticalCssStageResult.textContent = 'Dán CSS đã tạo và kiểm tra trước.';
				return;
			}
			criticalCssStageButton.disabled = true;
			setButtonLabel(criticalCssStageButton, 'Đang validate…');
			try {
				const data = await request({ action: 'baocache_stage_critical_css', nonce: BaoCacheAdmin.criticalCssStageNonce, css: criticalCssInput.value, template: criticalCssTemplate?.value || 'front-page', viewport: criticalCssViewport?.value || 'desktop' }, BaoCacheAdmin.criticalCssStageError);
				criticalCssStageResult.textContent = 'Đã stage · fingerprint ' + data.fingerprint;
				showToast('Critical CSS đã validate và stage. Purge trang tương ứng để kiểm thử.');
			} catch (error) {
				criticalCssStageResult.textContent = error.message || BaoCacheAdmin.criticalCssStageError;
				showToast(error.message || BaoCacheAdmin.criticalCssStageError, { error: true, duration: 5500 });
			} finally {
				criticalCssStageButton.disabled = false;
				setButtonLabel(criticalCssStageButton, 'Validate & stage');
			}
		});
	}

	if (criticalCssRollbackButton && window.BaoCacheAdmin) {
		criticalCssRollbackButton.addEventListener('click', async () => {
			criticalCssRollbackButton.disabled = true;
			try {
				await request({ action: 'baocache_rollback_critical_css', nonce: BaoCacheAdmin.criticalCssRollbackNonce }, BaoCacheAdmin.criticalCssRollbackError);
				showToast('Đã rollback Critical CSS; CSS không còn inline.');
				window.setTimeout(() => window.location.reload(), 500);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.criticalCssRollbackError, { error: true, duration: 5500 });
				criticalCssRollbackButton.disabled = false;
			}
		});
	}

	document.querySelectorAll('[data-baocache-create-rule]').forEach((button) => button.addEventListener('click', () => {
		document.querySelector('[data-baocache-tab="assets"]')?.click();
		document.querySelector('[data-baocache-assets-tab="rules"]')?.click();
		addButton?.click();
		const rule = rules?.querySelector('[data-baocache-rule]:last-child');
		if (!rule) return;
		const typeInput = rule.querySelector('[name$="[type]"]');
		const handleInput = rule.querySelector('[name$="[handle]"]');
		const scopeInput = rule.querySelector('[name$="[scope]"]');
		const valueInput = rule.querySelector('[name$="[value]"]');
		if (typeInput) typeInput.value = button.dataset.baocacheAssetType || 'script';
		if (handleInput) handleInput.value = button.dataset.baocacheAssetHandle || '';
		if (scopeInput) scopeInput.value = 'url-prefix';
		if (valueInput) valueInput.value = button.dataset.baocacheAssetPath || '/';
		rule.scrollIntoView({ behavior: 'smooth', block: 'center' });
		showToast('Đã tạo rule nháp cho URL mẫu. Kiểm tra rồi lưu cấu hình.');
	}));

	document.querySelectorAll('[data-baocache-suggest-defer]').forEach((button) => button.addEventListener('click', () => {
		const handle = button.dataset.baocacheSuggestDefer || '';
		const field = document.querySelector('[name="baocache_settings[defer_handles]"]');
		if (!handle || !field) return;
		const handles = field.value.split(/\r?\n/).map((item) => item.trim()).filter(Boolean);
		if (!handles.includes(handle)) handles.push(handle);
		field.value = handles.join('\n');
		document.querySelector('[data-baocache-tab="assets"]')?.click();
		document.querySelector('[data-baocache-assets-tab="rules"]')?.click();
		field.scrollIntoView({ behavior: 'smooth', block: 'center' });
		field.focus({ preventScroll: true });
		showToast('Đã thêm handle vào Defer nháp. Kiểm tra rồi lưu cấu hình.');
	}));

	if (inspectorShortcut && inspectButton) {
		inspectorShortcut.addEventListener('click', () => {
			document.querySelector('[data-baocache-tab="cache"]')?.click();
			inspectButton.click();
			inspectButton.closest('.baocache-inspector')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	}

	if (diagnosticsShortcut) {
		diagnosticsShortcut.addEventListener('click', async () => {
			const originalLabel = diagnosticsShortcut.textContent;
			diagnosticsShortcut.disabled = true;
			setButtonLabel(diagnosticsShortcut, 'Đang lấy snapshot…');
			showToast('Đang lấy runtime snapshot…', { duration: 0 });
			try {
				const data = await request({ action: 'baocache_take_runtime_snapshot', nonce: BaoCacheAdmin.snapshotNonce }, BaoCacheAdmin.snapshotError);
				showToast(`Đã lưu runtime snapshot thứ ${data.count}.`);
				document.querySelector('[data-baocache-tab="diagnostics"]')?.click();
				document.querySelector('.baocache-site-diagnostics')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
				window.setTimeout(() => window.location.reload(), 700);
			} catch (error) {
				showToast(error.message || BaoCacheAdmin.snapshotError, { error: true, duration: 5500 });
				setButtonLabel(diagnosticsShortcut, originalLabel);
				diagnosticsShortcut.disabled = false;
			}
		});
	}

	if (scanShortcut) {
		scanShortcut.addEventListener('click', () => {
			document.querySelector('[data-baocache-tab="assets"]')?.click();
			window.setTimeout(() => assetScanButtons[0]?.click(), 0);
		});
	}

	if (warmShortcut) {
		warmShortcut.addEventListener('click', () => {
			if (!warmupButton || warmupButton.disabled) {
				showToast('Bật Warm Queue và lưu cấu hình trước khi đọc sitemap.', { error: true, duration: 4500 });
				return;
			}
			document.querySelector('[data-baocache-tab="warmup"]')?.click();
			runWarmup();
		});
	}

	if (cloudflareAuditButton && cloudflareAuditResult && window.BaoCacheAdmin) {
		cloudflareAuditButton.addEventListener('click', async () => {
			const label = cloudflareAuditButton.textContent;
			cloudflareAuditButton.disabled = true;
			setButtonLabel(cloudflareAuditButton, 'Đang kiểm tra…');
			cloudflareAuditResult.hidden = false;
			cloudflareAuditResult.textContent = 'Đang xác minh token, Zone và Cache Rules…';
			try {
				const data = await request({ action: 'baocache_cloudflare_audit', nonce: BaoCacheAdmin.cloudflareAuditNonce }, BaoCacheAdmin.cloudflareAuditError);
				cloudflareAuditResult.replaceChildren();
				const summary = document.createElement('strong');
				summary.textContent = 'PASS · Cloudflare integration audit';
				const details = document.createElement('dl');
				[
					['Token', data.token_verified ? 'Đã xác minh' : '—'],
					['Zone', data.zone || '—'],
					['Trạng thái', data.zone_status || '—'],
					['Zone type', data.zone_type || '—'],
					['Zone paused', data.paused ? 'Có' : 'Không'],
					['Development Mode', data.development_mode ? 'Bật' : 'Tắt'],
					['Cache Rules', data.cache_rules?.state === 'observed' ? `${data.cache_rules.count || 0} rule được quan sát` : 'Không đọc được (cần Zone Rulesets Read)'],
					['Exact URL purge', data.purge_enabled ? 'Được bật riêng trong Coolify' : 'Đang khóa'],
				].forEach(([key, value]) => {
					const row = document.createElement('div');
					const term = document.createElement('dt');
					const description = document.createElement('dd');
					term.textContent = key;
					description.textContent = value;
					row.append(term, description);
					details.appendChild(row);
				});
				cloudflareAuditResult.append(summary, details);
				showToast('Đã hoàn tất Cloudflare integration audit.');
			} catch (error) {
				cloudflareAuditResult.textContent = error.message || BaoCacheAdmin.cloudflareAuditError;
				showToast(error.message || BaoCacheAdmin.cloudflareAuditError, { error: true, duration: 5500 });
			} finally {
				cloudflareAuditButton.disabled = false;
				setButtonLabel(cloudflareAuditButton, label);
			}
		});
	}

	if (cloudflarePurgeButton && cloudflarePurgeUrl && window.BaoCacheAdmin) {
		cloudflarePurgeButton.addEventListener('click', async () => {
			const url = cloudflarePurgeUrl.value.trim();
			if (!window.confirm(`Gửi exact URL purge tới Cloudflare?\n${url}`)) return;
			const label = cloudflarePurgeButton.textContent;
			cloudflarePurgeButton.disabled = true;
			setButtonLabel(cloudflarePurgeButton, 'Đang purge…');
			try {
				const data = await request({ action: 'baocache_cloudflare_purge_url', nonce: BaoCacheAdmin.cloudflarePurgeNonce, url }, 'Không thể purge Cloudflare URL.');
				showToast(data.message || 'Đã gửi Cloudflare exact URL purge.');
			} catch (error) { showToast(error.message || 'Không thể purge Cloudflare URL.', { error: true, duration: 5500 }); }
			finally { cloudflarePurgeButton.disabled = false; setButtonLabel(cloudflarePurgeButton, label); }
		});
	}

	if (inspectButton && inspectUrl && inspectResult && window.BaoCacheAdmin) {
		inspectButton.addEventListener('click', async () => {
			inspectButton.disabled = true;
			inspectResult.hidden = false;
			inspectResult.textContent = 'Đang kiểm tra…';
			try {
				const body = new URLSearchParams({ action: 'baocache_inspect_headers', nonce: BaoCacheAdmin.nonce, url: inspectUrl.value });
				const response = await fetch(BaoCacheAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body });
				const result = await response.json();
				if (!result.success) throw new Error(result.data?.message || BaoCacheAdmin.inspectError);
				const data = result.data;
				inspectResult.replaceChildren();
				const summary = document.createElement('p');
				summary.className = 'baocache-inspector__summary';
				summary.textContent = `${data.outcome || 'INFO'} · HTTP ${data.status_code} · ${data.response_ms} ms`;
				const checks = document.createElement('div');
				checks.className = 'baocache-inspector__checks';
				(data.checks || []).forEach((check) => {
					const row = document.createElement('div');
					row.className = `is-${check.state || 'neutral'}`;
					const label = document.createElement('span');
					const value = document.createElement('strong');
					label.textContent = check.label;
					value.textContent = check.value;
					row.append(label, value);
					checks.appendChild(row);
				});
				const explanation = document.createElement('p');
				explanation.className = 'baocache-inspector__explanation';
				explanation.textContent = data.explanation || '';
				const recommendations = document.createElement('div');
				recommendations.className = 'baocache-inspector__recommendations';
				const heading = document.createElement('h3');
				heading.textContent = 'Khuyến nghị từ response';
				const list = document.createElement('ol');
				(data.recommendations || []).forEach((recommendation) => {
					const item = document.createElement('li');
					const badge = document.createElement('span');
					badge.className = `baocache-badge is-${recommendation.state || 'neutral'}`;
					badge.textContent = recommendation.label || 'Info';
					const body = document.createElement('div');
					const title = document.createElement('strong');
					title.textContent = recommendation.title || '';
					const detail = document.createElement('span');
					detail.textContent = recommendation.detail || '';
					body.append(title, detail);
					const action = document.createElement('button');
					action.type = 'button';
					action.className = 'button-link';
					action.textContent = 'Mở mục liên quan';
					action.addEventListener('click', () => document.querySelector(`[data-baocache-tab="${recommendation.tab || 'dashboard'}"]`)?.click());
					item.append(badge, body, action);
					list.appendChild(item);
				});
				recommendations.append(heading, list);
				inspectResult.append(summary, checks, explanation, recommendations);
			} catch (error) {
				inspectResult.textContent = error.message || BaoCacheAdmin.inspectError;
			} finally {
				inspectButton.disabled = false;
			}
		});
	}

	if (previewButton && previewType && previewHandle && previewScope && previewValue && previewResult && window.BaoCacheAdmin) {
		previewButton.addEventListener('click', async () => {
			const body = new URLSearchParams({ action: 'baocache_preview_asset_rule', nonce: BaoCacheAdmin.previewNonce, type: previewType.value, handle: previewHandle.value, scope: previewScope.value, value: previewValue.value });
			const response = await fetch(BaoCacheAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body });
			const result = await response.json();
			if (!result.success) return previewResult.textContent = result.data?.message || 'Không thể xem trước.';
			const data = result.data;
			previewResult.textContent = !data.found ? 'Handle chưa có trong mẫu frontend.' : (data.dependents.length ? 'Không an toàn: đang là dependency của ' + data.dependents.join(', ') + '.' : (data.context === null ? 'Handle không có dependent trong mẫu. Điều kiện shortcode/block sẽ được đánh giá trên nội dung của từng trang khi frontend tải.' : (data.context ? 'Có thể tạo rule cho mẫu ' + data.path + '. Dependencies: ' + (data.dependencies.join(', ') || 'không có') + '.' : 'Rule không khớp mẫu frontend tại ' + data.path + '.')));
		});
	}

	if (!rules || !template || !addButton) return;

	const removeRule = (event) => {
		const button = event.target.closest('[data-baocache-remove-rule]');
		if (button) button.closest('[data-baocache-rule]')?.remove();
	};

	rules.addEventListener('click', removeRule);
	addButton.addEventListener('click', () => {
		const index = `${Date.now()}${Math.floor(Math.random() * 1000)}`;
		const fragment = template.content.cloneNode(true);
		fragment.querySelectorAll('[name]').forEach((element) => {
			element.name = element.name.replaceAll('__INDEX__', index);
		});
		rules.appendChild(fragment);
	});
})();
