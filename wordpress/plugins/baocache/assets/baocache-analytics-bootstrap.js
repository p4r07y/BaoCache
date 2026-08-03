(() => {
	const node = document.querySelector('meta[name="baocache-analytics-config"]');
	if (!node) return;
	let config;
	try { config = JSON.parse(node.getAttribute('content') || '{}'); } catch (_) { return; }
	if (!config || (!config.id && !config.clarity)) return;

	window.dataLayer = window.dataLayer || [];
	window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
	const consent = config.consent === 'denied' || config.consent === 'granted' ? config.consent : '';
	if (consent) {
		window.gtag('consent', 'default', {
			ad_storage: consent,
		analytics_storage: consent,
		ad_user_data: consent,
		ad_personalization: consent,
		wait_for_update: 500,
		});
	}

	const load = (src, id) => {
		const script = document.createElement('script');
		script.async = true;
		script.src = src;
		if (id) script.id = id;
		script.dataset.baocacheAnalytics = '1';
		document.head.appendChild(script);
		return script;
	};

	if (config.provider === 'ga4' && /^G-[A-Z0-9]+$/i.test(config.id || '')) {
		window.gtag('js', new Date());
		window.gtag('config', config.id, { send_page_view: true });
		load('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.id), 'baocache-google-tag');
	}
	if (config.provider === 'gtm' && /^GTM-[A-Z0-9]+$/i.test(config.id || '')) {
		window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
		load('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(config.id), 'baocache-google-tag-manager');
	}
	if (config.clarity && /^[a-z0-9]+$/i.test(config.clarity)) {
		window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
		load('https://www.clarity.ms/tag/' + encodeURIComponent(config.clarity), 'baocache-microsoft-clarity');
	}

	if (config.events) {
		(config.serverEvents || []).forEach((item) => {
			if (!item || !item.event) return;
			window.dataLayer.push(Object.assign({ event: item.event }, item.parameters || {}));
		});
	}
	document.dispatchEvent(new CustomEvent('baocache:analytics-ready', { detail: config }));
})();
