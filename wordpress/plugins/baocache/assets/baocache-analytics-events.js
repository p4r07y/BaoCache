(() => {
	const config = (() => {
		const node = document.querySelector('meta[name="baocache-analytics-config"]');
		try { return node ? JSON.parse(node.getAttribute('content') || '{}') : {}; } catch (_) { return {}; }
	})();
	if (!config.events) return;

	const adapters = new Set(Array.isArray(config.adapters) ? config.adapters : []);
	const adapterEvents = {
		woocommerce: new Set(['view_item', 'add_to_cart', 'begin_checkout', 'purchase']),
		forms: new Set(['form_submit']),
		onesignal: new Set(['subscribe_push', 'unsubscribe_push']),
		'power-schedule-manager': new Set(['search_schedule', 'select_area', 'view_schedule', 'subscribe_push', 'unsubscribe_push'])
	};
	const safeParameters = (parameters) => {
		const clean = {};
		if (!parameters || typeof parameters !== 'object') return clean;
		Object.entries(parameters).slice(0, 8).forEach(([key, value]) => {
			if (!/^[a-z][a-z0-9_]{0,40}$/.test(key)) return;
			if (typeof value === 'number' && Number.isFinite(value)) clean[key] = value;
			if (typeof value === 'boolean') clean[key] = value;
			if (typeof value === 'string') {
				const text = value.trim().slice(0, 80);
				// This bridge is metadata-only: do not let an adapter push likely PII.
				if (text && !/@/.test(text)) clean[key] = text;
			}
		});
		return clean;
	};
	const push = (event, parameters = {}) => {
		if (!/^baocache_[a-z0-9_]{1,60}$/.test(event)) return false;
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push(Object.assign({ event }, safeParameters(parameters)));
		return true;
	};
	const adapterTrack = (adapter, event, parameters = {}) => {
		if (!adapters.has(adapter) || !adapterEvents[adapter] || !adapterEvents[adapter].has(event)) return false;
		return push(`baocache_${event}`, Object.assign({ adapter }, parameters));
	};

	// Public extension contract for first-party integrations. It is CSP-safe and
	// never sends a request itself; the configured Google tag consumes dataLayer.
	window.BaoCacheAnalytics = Object.freeze({ trackAdapterEvent: adapterTrack });
	document.addEventListener('baocache:track', (event) => {
		const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
		adapterTrack(String(detail.adapter || ''), String(detail.event || ''), detail.parameters || {});
	});

	const isExternal = (url) => {
		try { return new URL(url, window.location.href).host !== window.location.host; } catch (_) { return false; }
	};
	const downloadPattern = /\.(?:pdf|zip|rar|7z|docx?|xlsx?|pptx?|csv|mp3|mp4|webm|avi)(?:$|[?#])/i;

	document.addEventListener('click', (event) => {
		const link = event.target.closest('a[href]');
		if (!link) return;
		const href = link.href || '';
		if (/^mailto:/i.test(href)) push('baocache_mailto_click');
		else if (/^tel:/i.test(href)) push('baocache_tel_click');
		else if (downloadPattern.test(href) || link.hasAttribute('download')) push('baocache_file_download', { file_extension: (href.split('?')[0].split('.').pop() || '').toLowerCase() });
		else if (isExternal(href)) push('baocache_outbound_click');
	}, { capture: true });

	document.addEventListener('submit', (event) => {
		if (event.target && event.target.id === 'commentform') push('baocache_comment_submit');
	}, { capture: true });

	const search = new URLSearchParams(window.location.search).get('s');
	if (search) push('baocache_search');
	if (config.is404) push('baocache_404');

	let scrolled = false;
	window.addEventListener('scroll', () => {
		if (scrolled) return;
		const max = document.documentElement.scrollHeight - window.innerHeight;
		if (max > 0 && window.scrollY / max >= 0.9) { scrolled = true; push('baocache_scroll_90'); }
	}, { passive: true });
	window.setTimeout(() => push('baocache_time_on_page_30'), 30000);
})();
