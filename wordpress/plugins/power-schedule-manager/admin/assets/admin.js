/**
 * Cúp Điện Lâm Đồng administration interactions.
 *
 * @package Power_Schedule_Manager
 */

'use strict';

(function () {
	const configuration =
		window.PowerScheduleManagerAdmin || {};

	const strings = configuration.strings || {};

	/**
	 * Return a translated string with a safe fallback.
	 *
	 * @param {string} key      String key.
	 * @param {string} fallback Fallback text.
	 * @return {string}
	 */
	function getString(key, fallback) {
		return typeof strings[key] === 'string'
			? strings[key]
			: fallback;
	}

	/**
	 * Keep map settings contextual and explain the effective state.
	 *
	 * @return {void}
	 */
	function initializeMapSettings() {
		const form = document.getElementById(
			'psm-settings-form'
		);

		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const provider = form.querySelector(
			'[data-psm-map-provider]'
		);

		const providerRows = form.querySelectorAll(
			'[data-psm-map-provider-setting]'
		);

		const zoomRow = form.querySelector(
			'[data-psm-map-zoom-setting]'
		);

		const status = form.querySelector(
			'[data-psm-map-settings-status]'
		);

		const statusLabel = form.querySelector(
			'[data-psm-map-settings-status-label]'
		);

		const tileUrl = document.getElementById(
			'psm-map-tile-url'
		);
		const maptilerStyle = document.getElementById(
			'psm-maptiler-style'
		);
		const stadiaStyle = document.getElementById(
			'psm-stadia-style'
		);
		const maptilerKey = document.getElementById(
			'psm-maptiler-key'
		);
		const stadiaKey = document.getElementById(
			'psm-stadia-key'
		);

		const testButton = form.querySelector(
			'[data-psm-test-map]'
		);

		const testResult = form.querySelector(
			'[data-psm-map-test-result]'
		);

		if (!(provider instanceof HTMLSelectElement)) {
			return;
		}

		function refresh() {
			const value = provider.value;
			const isCustom = value === 'custom';
			const isDisabled = value === 'disabled';

			providerRows.forEach(function (row) {
				if (row instanceof HTMLElement) {
					const rowProvider =
						row.dataset.psmMapProviderSetting || '';
					const isActive = rowProvider === value;

					row.hidden = !isActive;
					row.querySelectorAll('input, textarea, select')
						.forEach(function (control) {
							if (
								control instanceof
									HTMLInputElement ||
								control instanceof
									HTMLTextAreaElement ||
								control instanceof
									HTMLSelectElement
							) {
								control.disabled = !isActive;
							}
						});
				}
			});

			if (zoomRow instanceof HTMLElement) {
				zoomRow.hidden = isDisabled;
			}

			if (tileUrl instanceof HTMLInputElement) {
				tileUrl.required = isCustom;
			}

			if (testButton instanceof HTMLButtonElement) {
				testButton.disabled = isDisabled;
			}

			if (testResult instanceof HTMLElement) {
				testResult.textContent = '';
				testResult.className = '';
			}

			if (
				status instanceof HTMLElement &&
				statusLabel instanceof HTMLElement
			) {
				let message = status.dataset.enabledText;

				if (isDisabled) {
					message = status.dataset.disabledText;
				} else if (isCustom) {
					message = status.dataset.customText;
				} else if (value === 'maptiler') {
					message = status.dataset.maptilerText;
				} else if (value === 'stadia') {
					message = status.dataset.stadiaText;
				}

				statusLabel.textContent =
					typeof message === 'string'
						? message
						: '';

				statusLabel.classList.toggle(
					'psm-status--success',
					!isDisabled
				);

				statusLabel.classList.toggle(
					'psm-status--neutral',
					isDisabled
				);
			}
		}

		provider.addEventListener(
			'change',
			refresh
		);

		if (
			testButton instanceof HTMLButtonElement &&
			testResult instanceof HTMLElement
		) {
			testButton.addEventListener(
				'click',
				async function () {
					const customUrl =
						tileUrl instanceof HTMLInputElement
							? tileUrl.value.trim()
							: '';

					testButton.disabled = true;
					testResult.textContent =
						testResult.dataset.testingText || '';
					testResult.className = '';

					const data = new FormData();
					data.set('action', 'psm_test_map_tile');
					data.set(
						'nonce',
						typeof configuration.ajaxNonce === 'string'
							? configuration.ajaxNonce
							: ''
					);
					data.set('provider', provider.value);
					data.set('tile_url', customUrl);
					data.set(
						'maptiler_style',
						maptilerStyle instanceof HTMLInputElement
							? maptilerStyle.value.trim()
							: ''
					);
					data.set(
						'stadia_style',
						stadiaStyle instanceof HTMLSelectElement
							? stadiaStyle.value
							: ''
					);
					data.set(
						'maptiler_key',
						maptilerKey instanceof HTMLInputElement
							? maptilerKey.value.trim()
							: ''
					);
					data.set(
						'stadia_key',
						stadiaKey instanceof HTMLInputElement
							? stadiaKey.value.trim()
							: ''
					);

					try {
						const response = await window.fetch(
							window.ajaxurl,
							{
								method: 'POST',
								credentials: 'same-origin',
								body: data,
								headers: {
									'X-Requested-With':
										'XMLHttpRequest'
								}
							}
						);

						const result = await response.json();
						const message =
							result &&
							result.data &&
							typeof result.data.message === 'string'
								? result.data.message
								: testResult.dataset.errorText || '';

						if (!response.ok || result.success !== true) {
							throw new Error(message);
						}

						testResult.textContent = message;
						testResult.className =
							'psm-map-test__result--success';
					} catch (error) {
						testResult.textContent =
							error instanceof Error
								? error.message
								: testResult.dataset.errorText || '';
						testResult.className =
							'psm-map-test__result--error';
					} finally {
						testButton.disabled =
							provider.value === 'disabled';
					}
				}
			);
		}

		refresh();
	}

	/**
	 * Divide the settings screen into accessible, URL-persisted tabs.
	 *
	 * All controls remain inside one form, so switching tabs never discards
	 * unsaved values. Without JavaScript WordPress displays every panel.
	 *
	 * @return {void}
	 */
	function initializeSettingsTabs() {
		const navigation = document.querySelector(
			'[data-psm-settings-tabs]'
		);

		if (!(navigation instanceof HTMLElement)) {
			return;
		}

		const buttons = Array.from(
			navigation.querySelectorAll(
				'[data-psm-settings-tab]'
			)
		);

		const panels = Array.from(
			document.querySelectorAll(
				'[data-psm-settings-panel]'
			)
		);
		const fallbackTab = buttons[0]?.dataset.psmSettingsTab
			|| 'publishing';

		function activate(tab, updateUrl) {
			const exists = buttons.some(function (button) {
				return button.dataset.psmSettingsTab === tab;
			});

			const selected = exists ? tab : fallbackTab;

			buttons.forEach(function (button) {
				const active =
					button.dataset.psmSettingsTab === selected;

				button.classList.toggle('is-active', active);
				button.setAttribute(
					'aria-selected',
					active ? 'true' : 'false'
				);
			});

			panels.forEach(function (panel) {
				panel.hidden =
					panel.dataset.psmSettingsPanel !== selected;
			});

			if (updateUrl && window.history.replaceState) {
				const url = new URL(window.location.href);
				url.searchParams.set('settings_tab', selected);
				window.history.replaceState({}, '', url);

				const referer = document.querySelector(
					'#psm-settings-form input[name="_wp_http_referer"]'
				);

				if (referer instanceof HTMLInputElement) {
					referer.value =
						url.pathname + url.search + url.hash;
				}
			}
		}

		buttons.forEach(function (button) {
			button.setAttribute('role', 'tab');
			button.addEventListener('click', function () {
				activate(
					button.dataset.psmSettingsTab || '',
					true
				);
			});
		});

		navigation.setAttribute('role', 'tablist');

		const requested = new URL(
			window.location.href
		).searchParams.get('settings_tab');

		activate(requested || fallbackTab, false);
	}

	/**
	 * Show immediate feedback when the source import form is submitted.
	 *
	 * @return {void}
	 */
	function initializeSourceImportFeedback() {
		const form = document.querySelector('.psm-import-form');

		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const unitSelect = form.querySelector('#psm-unit-code');
		const payload = form.querySelector('#psm-payload');
		const status = form.querySelector(
			'[data-psm-import-submit-status]'
		);
		const submitButton = form.querySelector(
			'button[type="submit"], input[type="submit"]'
		);

		if (!(status instanceof HTMLElement)) {
			return;
		}

		form.addEventListener('submit', function (event) {
			const hasUnit =
				unitSelect instanceof HTMLSelectElement &&
				unitSelect.value.trim() !== '';
			const hasPayload =
				payload instanceof HTMLTextAreaElement &&
				payload.value.trim() !== '';

			if (!hasUnit || !hasPayload) {
				event.preventDefault();
				status.className =
					'description psm-text-error';
				status.textContent = !hasUnit
					? getString(
						'unitSelectRequired',
						'Vui lòng chọn đơn vị điện lực trước khi kiểm tra dữ liệu.'
					)
					: 'Vui lòng dán dữ liệu lịch điện trước khi kiểm tra.';

				if (typeof form.reportValidity === 'function') {
					form.reportValidity();
				}

				return;
			}

			status.className =
				'description psm-text-success';
			status.textContent = getString(
				'previewing',
				'Đang kiểm tra dữ liệu…'
			);

			if (
				submitButton instanceof HTMLButtonElement ||
				submitButton instanceof HTMLInputElement
			) {
				submitButton.dataset.originalText =
					submitButton instanceof HTMLInputElement
						? submitButton.value
						: submitButton.textContent || '';

				if (submitButton instanceof HTMLInputElement) {
					submitButton.value = getString(
						'previewing',
						'Đang kiểm tra dữ liệu…'
					);
				} else {
					submitButton.textContent = getString(
						'previewing',
						'Đang kiểm tra dữ liệu…'
					);
				}
			}
		});
	}

	/**
	 * Confirm destructive or important import actions.
	 *
	 * @return {void}
	 */
	function initializeImportConfirmation() {
		document
			.querySelectorAll('.psm-preview-form')
			.forEach(function (form) {
				if (!(form instanceof HTMLFormElement)) {
					return;
				}

				form.addEventListener(
					'submit',
					function (event) {
						if (
							!window.confirm(
								getString(
									'confirmImport',
									'Xác nhận nhập dữ liệu?'
								)
							)
						) {
							event.preventDefault();
						}
					}
				);
			});
	}

	/**
	 * Close the compact dashboard action menu on outside click or Escape.
	 *
	 * @return {void}
	 */
	function initializeDashboardActions() {
		const menu = document.querySelector(
			'.psm-dashboard-more-actions'
		);

		if (!(menu instanceof HTMLDetailsElement)) {
			return;
		}

		document.addEventListener('click', function (event) {
			if (
				menu.open &&
				event.target instanceof Node &&
				!menu.contains(event.target)
			) {
				menu.open = false;
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && menu.open) {
				menu.open = false;
				menu.querySelector('summary')?.focus();
			}
		});
	}

	/**
	 * Copy a shortcode example without submitting the settings form.
	 *
	 * @return {void}
	 */
	function initializeShortcodeCopy() {
		document
			.querySelectorAll('[data-psm-copy-shortcode]')
			.forEach(function (button) {
				if (!(button instanceof HTMLButtonElement)) {
					return;
				}

				button.addEventListener('click', async function () {
					const targetId = button.dataset.copyTarget || '';
					const target = document.getElementById(targetId);

					if (
						!(
							target instanceof HTMLTextAreaElement ||
							target instanceof HTMLInputElement
						)
					) {
						return;
					}

					try {
						await navigator.clipboard.writeText(target.value);
					} catch (error) {
						target.focus();
						target.select();
						document.execCommand('copy');
					}

					const original = button.textContent;
					button.textContent = 'Đã sao chép';

					window.setTimeout(function () {
						button.textContent = original;
					}, 1600);
				});
			});
	}

	/**
	 * Test notification adapters after saved settings have been loaded.
	 *
	 * @return {void}
	 */
	function initializeNotificationTests() {
		document
			.querySelectorAll('[data-psm-test-notification]')
			.forEach(function (button) {
				if (!(button instanceof HTMLButtonElement)) {
					return;
				}

				button.addEventListener('click', async function () {
					const channel =
						button.dataset.psmTestNotification || '';
					const result = button.nextElementSibling;

					if (!(result instanceof HTMLElement)) {
						return;
					}

					const original = button.textContent;
					button.disabled = true;
					result.className =
						'psm-notification-test-result is-loading';
					result.textContent = 'Đang gửi thử…';

					const data = new FormData();
					data.set('action', 'psm_test_notification');
					data.set('channel', channel);
					data.set(
						'nonce',
						typeof configuration.notificationNonce ===
							'string'
							? configuration.notificationNonce
							: ''
					);

					try {
						const response = await window.fetch(
							window.ajaxurl,
							{
								method: 'POST',
								credentials: 'same-origin',
								body: data,
								headers: {
									'X-Requested-With':
										'XMLHttpRequest'
								}
							}
						);
						const payload = await response.json();
						const message =
							payload &&
							payload.data &&
							typeof payload.data.message === 'string'
								? payload.data.message
								: 'Không nhận được phản hồi hợp lệ.';

						if (!response.ok || payload.success !== true) {
							throw new Error(message);
						}

						result.textContent = message;
						result.className =
							'psm-notification-test-result is-success';
					} catch (error) {
						result.textContent =
							error instanceof Error
								? error.message
								: 'Không thể gửi thông báo thử.';
						result.className =
							'psm-notification-test-result is-error';
					} finally {
						button.disabled = false;
						button.textContent = original;
					}
				});
			});
	}

	/**
	 * Keep the long help centre useful without forcing administrators to scan
	 * every guide. Filtering is local, instant and does not alter URLs.
	 *
	 * @return {void}
	 */
	function initializeHelpSearch() {
		const search = document.querySelector(
			'[data-psm-help-search]'
		);
		const status = document.querySelector(
			'[data-psm-help-search-status]'
		);
		const sections = document.querySelectorAll(
			'[data-psm-help-section]'
		);

		if (!(search instanceof HTMLInputElement) || !sections.length) {
			return;
		}

		search.addEventListener('input', function () {
			const query = search.value.trim().toLocaleLowerCase('vi');
			let matches = 0;

			sections.forEach(function (section) {
				if (!(section instanceof HTMLElement)) {
					return;
				}

				const matched =
					query === '' ||
					section.textContent.toLocaleLowerCase('vi').includes(query);

				section.hidden = !matched;

				if (matched) {
					matches += 1;
				}
			});

			if (!(status instanceof HTMLElement)) {
				return;
			}

			if (query === '') {
				status.textContent = '';
				return;
			}

			status.textContent =
				matches > 0
					? 'Tìm thấy ' + matches + ' mục hướng dẫn phù hợp.'
					: 'Không tìm thấy mục phù hợp. Hãy thử từ khóa ngắn hơn.';
		});
	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			initializeMapSettings();
			initializeSettingsTabs();
			initializeSourceImportFeedback();
			initializeImportConfirmation();
			initializeDashboardActions();
			initializeShortcodeCopy();
			initializeNotificationTests();
			initializeHelpSearch();
		}
	);
})();
