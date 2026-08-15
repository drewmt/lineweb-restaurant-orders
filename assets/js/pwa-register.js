if ('serviceWorker' in navigator) {
	navigator.serviceWorker
		.register(snaporder_pwa_vars.service_worker_url, {scope: snaporder_pwa_vars.scope})
		.catch(function (error) {
			console.warn('SnapOrder service worker registration failed:', error);
		});
}
