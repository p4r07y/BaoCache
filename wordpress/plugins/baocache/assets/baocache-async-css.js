(() => {
	const activate = () => {
		document.querySelectorAll('link[data-baocache-async-css="1"]').forEach((link) => {
			link.media = 'all';
		});
	};
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', activate, { once: true });
	else activate();
})();
