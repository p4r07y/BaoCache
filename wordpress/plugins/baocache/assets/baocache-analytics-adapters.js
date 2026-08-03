(() => {
	const config = (() => {
		const node = document.querySelector('meta[name="baocache-analytics-config"]');
		try { return node ? JSON.parse(node.getAttribute('content') || '{}') : {}; } catch (_) { return {}; }
	})();
	if (!config.events || !Array.isArray(config.adapters)) return;
	const enabled = new Set(config.adapters);
	const emit = (adapter, event, parameters = {}) => {
		if (!enabled.has(adapter)) return;
		document.dispatchEvent(new CustomEvent('baocache:track', { detail: { adapter, event, parameters } }));
	};

	if (enabled.has('woocommerce')) {
		if (document.body.classList.contains('single-product')) emit('woocommerce', 'view_item');
		if (document.body.classList.contains('woocommerce-checkout') && !document.body.classList.contains('woocommerce-order-received')) emit('woocommerce', 'begin_checkout');
		if (document.body.classList.contains('woocommerce-order-received')) emit('woocommerce', 'purchase');
		if (window.jQuery) {
			window.jQuery(document.body).on('added_to_cart', () => emit('woocommerce', 'add_to_cart'));
		}
	}

	if (enabled.has('forms')) {
		// Official frontend completion hooks. No form values, IDs or submissions are retained.
		document.addEventListener('wpcf7mailsent', () => emit('forms', 'form_submit'));
		document.addEventListener('fluentform_submission_success', () => emit('forms', 'form_submit'));
		if (window.jQuery) window.jQuery(document).on('gform_confirmation_loaded', () => emit('forms', 'form_submit'));
	}

	if (enabled.has('power-schedule-manager')) {
		if (document.querySelector('.psm-single-schedule')) emit('power-schedule-manager', 'view_schedule');
		document.addEventListener('submit', (event) => {
			if (event.target instanceof Element && event.target.matches('.psm-search, [data-psm-compact-unit-search]')) emit('power-schedule-manager', 'search_schedule');
		}, { capture: true });
		document.addEventListener('click', (event) => {
			const target = event.target instanceof Element ? event.target : null;
			if (target && target.closest('[data-psm-compact-unit-option], [data-psm-map-trigger]')) emit('power-schedule-manager', 'select_area');
		}, { capture: true });
		const root = document.querySelector('[data-psm-push-root]');
		if (root instanceof HTMLElement) {
			let state = root.dataset.state || '';
			new MutationObserver(() => {
				const next = root.dataset.state || '';
				if (state === 'unsubscribed' && next === 'subscribed') emit('power-schedule-manager', 'subscribe_push');
				if (state === 'subscribed' && next === 'unsubscribed') emit('power-schedule-manager', 'unsubscribe_push');
				state = next;
			}).observe(root, { attributes: true, attributeFilter: ['data-state'] });
		}
	}

	if (enabled.has('onesignal')) {
		// Vendor-specific code can use this stable, opt-in bridge after it has
		// verified a subscription change: BaoCacheAnalytics.trackAdapterEvent(...).
		document.addEventListener('baocache:onesignal-subscription', (event) => {
			const state = event && event.detail && event.detail.state;
			if (state === 'subscribed') emit('onesignal', 'subscribe_push');
			if (state === 'unsubscribed') emit('onesignal', 'unsubscribe_push');
		});
	}
})();
