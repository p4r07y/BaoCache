(() => {
	const runner = document.currentScript;
	const config = runner ? { endpoint: runner.getAttribute('data-baocache-timing-endpoint'), nonce: runner.getAttribute('data-baocache-timing-nonce') } : null;
	if (!config || !config.endpoint || !config.nonce || !window.performance || !window.performance.getEntriesByType || !window.fetch) return;

	const classify = (entry, url) => {
		const path = url.pathname.toLowerCase();
		const extension = /\.(woff2?|ttf|otf)(?:$|\/)/.test(path) ? 'font'
			: /\.(png|jpe?g|gif|webp|avif|svg)(?:$|\/)/.test(path) ? 'image'
			: /\.css(?:$|\/)/.test(path) ? 'css'
			: /\.js(?:$|\/)/.test(path) ? 'js'
			: /\.json(?:$|\/)/.test(path) ? 'json' : 'other';
		const type = extension === 'font' ? 'font' : extension === 'image' ? 'image'
			: extension === 'css' ? 'style' : extension === 'js' ? 'script'
			: entry.initiatorType === 'fetch' || entry.initiatorType === 'xmlhttprequest' ? 'fetch' : 'other';
		return { type, extension };
	};

	const collect = () => {
		const groups = new Map();
		performance.getEntriesByType('resource').forEach((entry) => {
			if (!entry.name || entry.duration <= 0) return;
			let url;
			try { url = new URL(entry.name, window.location.href); } catch (error) { return; }
			const source = url.origin === window.location.origin ? 'same-site' : url.hostname.toLowerCase();
			const { type, extension } = classify(entry, url);
			const key = `${source}|${type}|${extension}`;
			const group = groups.get(key) || { source, type, extension, count: 0, duration_ms: 0, transfer_bytes: 0 };
			group.count += 1;
			group.duration_ms += Math.round(entry.duration);
			group.transfer_bytes += Number.isFinite(entry.transferSize) ? entry.transferSize : 0;
			groups.set(key, group);
		});
		const payload = Array.from(groups.values())
			.sort((left, right) => right.duration_ms - left.duration_ms)
			.slice(0, 20);
		if (!payload.length) return;
		window.fetch(config.endpoint, {
			method: 'POST',
			credentials: 'omit',
			keepalive: true,
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ nonce: config.nonce, groups: payload }),
		}).catch(() => {});
	};

	window.addEventListener('load', () => window.setTimeout(collect, 3000), { once: true });
})();
