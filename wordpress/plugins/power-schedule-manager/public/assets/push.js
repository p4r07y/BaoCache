/**
 * Explicit, on-demand OneSignal Web Push preference control.
 *
 * The OneSignal SDK is loaded only after the visitor opens the preference
 * control. Notification permission is requested only after they save at least
 * one followed electricity area.
 *
 * @package Power_Schedule_Manager
 */

(function () {
	'use strict';

	const config = window.PowerScheduleManagerPush || {};
	const root = document.querySelector('[data-psm-push-root]');
	const button = root ? root.querySelector('[data-psm-push-subscribe]') : null;
	const status = root ? root.querySelector('[data-psm-push-status]') : null;
	const preferences = root
		? root.querySelector('[data-psm-push-preferences]')
		: null;
	const areas = root ? root.querySelector('[data-psm-push-areas]') : null;
	const lottery = root ? root.querySelector('[data-psm-push-lottery]') : null;
	const saveButton = root ? root.querySelector('[data-psm-push-save]') : null;
	const closeButton = root ? root.querySelector('[data-psm-push-close]') : null;
	const units = Array.isArray(config.units) ? config.units : [];
	const lotteryTopics = Array.isArray(config.lotteryTopics)
		? config.lotteryTopics
		: [];
	let oneSignal = null;
	let sdkPromise = null;
	let messageTimer = 0;

	if (
		!config.enabled ||
		!(root instanceof HTMLElement) ||
		!(button instanceof HTMLButtonElement) ||
		!(preferences instanceof HTMLElement) ||
		!(areas instanceof HTMLElement) ||
		!(lottery instanceof HTMLElement) ||
		!(saveButton instanceof HTMLButtonElement)
	) {
		return;
	}

	function string(key, fallback) {
		return config.strings && typeof config.strings[key] === 'string'
			? config.strings[key]
			: fallback;
	}

	function isIOS() {
		return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
	}

	function isStandalone() {
		return (
			window.matchMedia('(display-mode: standalone)').matches ||
			window.navigator.standalone === true
		);
	}

	function showMessage(message, persistent) {
		if (!(status instanceof HTMLElement)) {
			return;
		}

		window.clearTimeout(messageTimer);
		status.textContent = message;
		status.dataset.visible = 'true';

		if (!persistent) {
			messageTimer = window.setTimeout(function () {
				delete status.dataset.visible;
			}, 5200);
		}
	}

	function setState(optedIn) {
		const subscribed = optedIn === true;
		root.dataset.state = subscribed ? 'subscribed' : 'unsubscribed';
		button.setAttribute(
			'aria-label',
			subscribed
				? string('preferences', 'Tùy chọn thông báo')
				: string('enable', 'Bật thông báo lịch cúp điện')
		);
		button.title = subscribed
			? string('preferences', 'Tùy chọn thông báo')
			: config.label || string('enable', 'Bật thông báo lịch cúp điện');
	}

	function areaTag(code) {
		return 'psm_area_' + String(code).toLowerCase().replace(/[^a-z0-9_]/g, '');
	}

	/* Remove the short-lived pre-0.38.6 mixed-case tag during the next save. */
	function legacyAreaTag(code) {
		return 'psm_area_' + String(code).replace(/[^a-z0-9_]/gi, '');
	}

	function lotteryTag(code) {
		return 'psm_lottery_' + String(code).toLowerCase().replace(/[^a-z0-9_]/g, '');
	}

	function selectedCodes() {
		return Array.from(
			areas.querySelectorAll('[data-psm-push-area]:checked')
		)
			.map(function (input) {
				return input instanceof HTMLInputElement ? input.value : '';
			})
			.filter(Boolean);
	}

	function selectedLotteryTopics() {
		return Array.from(
			lottery.querySelectorAll('[data-psm-push-lottery]:checked')
		)
			.map(function (input) {
				return input instanceof HTMLInputElement ? input.value : '';
			})
			.filter(Boolean);
	}

	function renderAreas(tags) {
		areas.replaceChildren();
		const selectedTags = tags && typeof tags === 'object' ? tags : {};

		units.forEach(function (unit) {
			if (!unit || typeof unit.code !== 'string' || typeof unit.name !== 'string') {
				return;
			}

			const label = document.createElement('label');
			label.className = 'psm-push-fab__area';
			const input = document.createElement('input');
			input.type = 'checkbox';
			input.value = unit.code;
			input.dataset.psmPushArea = '1';
			input.checked =
				String(selectedTags[areaTag(unit.code)] || '') === '1' ||
				String(selectedTags[legacyAreaTag(unit.code)] || '') === '1';
			const text = document.createElement('span');
			text.textContent = unit.name;
			label.append(input, text);
			areas.appendChild(label);
		});
	}

	function renderLotteryTopics(tags) {
		lottery.replaceChildren();
		const selectedTags = tags && typeof tags === 'object' ? tags : {};

		lotteryTopics.forEach(function (topic) {
			if (!topic || typeof topic.code !== 'string' || typeof topic.name !== 'string') {
				return;
			}

			const label = document.createElement('label');
			label.className = 'psm-push-fab__area';
			const input = document.createElement('input');
			input.type = 'checkbox';
			input.value = topic.code;
			input.dataset.psmPushLottery = '1';
			input.checked = String(selectedTags[lotteryTag(topic.code)] || '') === '1';
			const text = document.createElement('span');
			text.textContent = topic.name;
			label.append(input, text);
			lottery.appendChild(label);
		});
	}

	async function readTags() {
		if (!oneSignal || !oneSignal.User || typeof oneSignal.User.getTags !== 'function') {
			return {};
		}

		try {
			const tags = await oneSignal.User.getTags();
			return tags && typeof tags === 'object' ? tags : {};
		} catch (error) {
			return {};
		}
	}

	async function loadSDK() {
		if (oneSignal !== null) {
			return oneSignal;
		}
		if (sdkPromise !== null) {
			return sdkPromise;
		}

		sdkPromise = new Promise(function (resolve, reject) {
			const url = typeof config.sdkUrl === 'string' ? config.sdkUrl : '';
			if (!url || !config.init || typeof config.init !== 'object') {
				reject(new Error('onesignal_config_missing'));
				return;
			}

			window.OneSignalDeferred = window.OneSignalDeferred || [];
			window.OneSignalDeferred.push(async function (sdk) {
				try {
					await sdk.init(config.init);
					oneSignal = sdk;
					if (!sdk.Notifications.isPushSupported()) {
						throw new Error('push_unsupported');
					}
					setState(sdk.User.PushSubscription.optedIn);
					sdk.User.PushSubscription.addEventListener('change', function () {
						setState(sdk.User.PushSubscription.optedIn);
					});
					resolve(sdk);
				} catch (error) {
					reject(error);
				}
			});

			const script = document.createElement('script');
			script.src = url;
			script.async = true;
			script.dataset.psmOneSignal = '1';
			script.addEventListener('error', function () {
				reject(new Error('onesignal_sdk_load_failed'));
			}, { once: true });
			document.head.appendChild(script);
		});

		try {
			return await sdkPromise;
		} catch (error) {
			sdkPromise = null;
			throw error;
		}
	}

	async function openPreferences() {
		if (isIOS() && !isStandalone()) {
			showMessage(
				string('installFirst', 'Hãy thêm website vào Màn hình chính trước khi bật thông báo.'),
				true
			);
			return;
		}

		button.disabled = true;
		showMessage(string('loading', 'Đang kết nối dịch vụ thông báo…'), false);
		try {
			await loadSDK();
			const tags = await readTags();
			renderAreas(tags);
			renderLotteryTopics(tags);
			preferences.hidden = false;
			root.dataset.open = 'true';
			showMessage(string('chooseAreas', 'Chọn khu vực bạn muốn nhận thông báo.'), false);
		} catch (error) {
			root.dataset.state = error instanceof Error && error.message === 'push_unsupported'
				? 'blocked'
				: 'unsubscribed';
			showMessage(
				error instanceof Error && error.message === 'push_unsupported'
					? string('unsupported', 'Trình duyệt này chưa hỗ trợ thông báo web.')
					: string('error', 'Chưa thể cập nhật thông báo. Vui lòng thử lại.'),
				true
			);
		} finally {
			button.disabled = false;
		}
	}

	function closePreferences() {
		preferences.hidden = true;
		delete root.dataset.open;
		button.focus();
	}

	async function savePreferences() {
		const codes = selectedCodes();
		const topics = selectedLotteryTopics();
		if (codes.length === 0 && topics.length === 0) {
			showMessage(string('chooseOneArea', 'Hãy chọn ít nhất một khu vực.'), true);
			return;
		}
		if (oneSignal === null) {
			return;
		}

		saveButton.disabled = true;
		try {
			if (window.Notification.permission === 'denied') {
				root.dataset.state = 'blocked';
				showMessage(string('blocked', 'Thông báo đang bị trình duyệt chặn.'), true);
				return;
			}

			if (!oneSignal.Notifications.permission) {
				await oneSignal.Notifications.requestPermission();
			}
			if (oneSignal.Notifications.permission) {
				await oneSignal.User.PushSubscription.optIn();
			}
			if (!oneSignal.User.PushSubscription.optedIn) {
				showMessage(string('blocked', 'Bạn chưa cấp quyền nhận thông báo.'), true);
				return;
			}

			const desired = {};
			codes.forEach(function (code) {
				desired[areaTag(code)] = '1';
			});
			topics.forEach(function (topic) {
				desired[lotteryTag(topic)] = '1';
			});
			const current = await readTags();
			const removable = units
				.reduce(function (keys, unit) {
					keys.push(areaTag(unit.code), legacyAreaTag(unit.code));
					return keys;
				}, [])
				.filter(function (key) { return current[key] && !desired[key]; });
			lotteryTopics.forEach(function (topic) {
				const key = lotteryTag(topic.code);
				if (current[key] && !desired[key]) {
					removable.push(key);
				}
			});
			if (removable.length) {
				if (typeof oneSignal.User.removeTags !== 'function') {
					throw new Error('onesignal_remove_tags_unavailable');
				}
				await oneSignal.User.removeTags(removable);
			}
			if (typeof oneSignal.User.addTags !== 'function') {
				throw new Error('onesignal_add_tags_unavailable');
			}
			await oneSignal.User.addTags(desired);
			setState(true);
			closePreferences();
			showMessage(string('savedAreas', 'Đã lưu khu vực theo dõi trên thiết bị này.'), false);
		} catch (error) {
			showMessage(string('error', 'Chưa thể cập nhật thông báo. Vui lòng thử lại.'), false);
		} finally {
			saveButton.disabled = false;
		}
	}

	button.addEventListener('click', openPreferences);
	saveButton.addEventListener('click', savePreferences);
	if (closeButton instanceof HTMLButtonElement) {
		closeButton.addEventListener('click', closePreferences);
	}
	setState(false);
}());
