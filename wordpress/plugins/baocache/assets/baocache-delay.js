(() => {
	const runner = document.currentScript;
	const options = new URL(runner.src, window.location.href).searchParams;
	const timeout = Math.max(1000, Math.min(60000, Number(options.get('timeout')) || 10000));
	const preview = options.get('preview') === '1';
	const events = ['pointerdown', 'keydown', 'touchstart', 'scroll', 'mousemove'];
	let complete = false;
	let timer;
	let panel;

	const report = (message, state = 'info') => {
		if (!preview) return;
		if (!panel) {
			panel = document.createElement('aside');
			panel.setAttribute('aria-live', 'polite');
			panel.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:2147483647;max-width:340px;padding:12px 14px;border:1px solid #cbd5e3;border-radius:8px;background:#fff;color:#24324b;box-shadow:0 12px 30px rgba(20,38,70,.18);font:12px/1.45 system-ui,sans-serif';
			panel.innerHTML = '<strong style="display:block;margin-bottom:6px">BaoCache Delay preview</strong><ol style="margin:0;padding-left:18px"></ol>';
			document.body.appendChild(panel);
		}
		const item = document.createElement('li');
		item.textContent = message;
		item.style.color = state === 'error' ? '#a61b2b' : state === 'ok' ? '#14734e' : '#4a5a73';
		panel.querySelector('ol').appendChild(item);
	};

	if (preview) {
		window.addEventListener('error', () => report('Phát hiện JavaScript error trong phiên preview.', 'error'), true);
		window.addEventListener('unhandledrejection', () => report('Phát hiện Promise rejection trong phiên preview.', 'error'));
		report('Đang chờ tương tác hoặc timeout để chạy handle đã chọn.');
	}

	const loadInOrder = (tags, index = 0) => {
		if (index >= tags.length) return;
		const original = tags[index];
		const source = original.getAttribute('data-baocache-src');
		if (!source || !original.parentNode) return loadInOrder(tags, index + 1);
		const script = document.createElement('script');
		script.src = source;
		script.async = false;
		script.id = original.id;
		original.parentNode.replaceChild(script, original);
		script.onload = () => { report(`Đã chạy ${original.getAttribute('data-baocache-handle') || 'script'}.`, 'ok'); loadInOrder(tags, index + 1); };
		script.onerror = () => { report(`Không tải được ${original.getAttribute('data-baocache-handle') || 'script'}.`, 'error'); loadInOrder(tags, index + 1); };
	};

	const run = () => {
		if (complete) return;
		complete = true;
		window.clearTimeout(timer);
		events.forEach((event) => window.removeEventListener(event, run));
		const tags = [...document.querySelectorAll('script[data-baocache-delay="1"]')];
		report(`Bắt đầu chạy ${tags.length} handle Delay.`);
		loadInOrder(tags);
	};

	events.forEach((event) => window.addEventListener(event, run, { passive: true, once: true }));
	timer = window.setTimeout(run, timeout);
})();
