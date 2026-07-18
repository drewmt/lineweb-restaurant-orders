<?php
/**
 * Progressive Web App support.
 *
 * Serves a dynamic web app manifest and registers a cache-first service worker.
 * Settings are in SnapOrder_Settings → PWA tab.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides an optional, menu-scoped progressive web app.
 */
class SnapOrder_PWA {

	/**
	 * Registers PWA hooks when the feature is enabled.
	 */
	public function __construct() {
		if ( get_option( 'mfm_pwa_enabled' ) === '1' ) {
			add_action( 'wp_head', array( $this, 'output_meta_tags' ) );
			add_action( 'template_redirect', array( $this, 'render_manifest' ) );
			add_action( 'template_redirect', array( $this, 'render_service_worker' ) );
			add_action( 'wp_footer', array( $this, 'register_service_worker' ) );
		}
	}

	/**
	 * Outputs manifest and mobile-app metadata on the app template.
	 */
	public function output_meta_tags() {
		if ( ! is_page_template( 'mfm-app-view.php' ) ) {
			return;
		}
		$color = sanitize_hex_color( get_option( 'mfm_pwa_theme_color', '#10b981' ) );
		$color = $color ? $color : '#10b981';
		?>
		<link rel="manifest" href="<?php echo esc_url( site_url( '/?mfm-manifest=1' ) ); ?>">
		<meta name="theme-color" content="<?php echo esc_attr( $color ); ?>">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<?php
	}

	/**
	 * Serves the dynamic web app manifest.
	 */
	public function render_manifest() {
		// Public, read-only endpoint selected by an exact query flag.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['mfm-manifest'] ) || '1' !== $_GET['mfm-manifest'] ) {
			return;
		}

		$name       = get_option( 'mfm_pwa_name', get_bloginfo( 'name' ) );
		$short_name = get_option( 'mfm_pwa_short_name', 'Restaurant' );
		$color      = get_option( 'mfm_pwa_theme_color', '#10b981' );
		$app_url    = $this->get_app_url();

		$manifest = array(
			'name'             => $name,
			'short_name'       => $short_name,
			'start_url'        => $app_url,
			'scope'            => $this->get_app_scope(),
			'display'          => 'standalone',
			'background_color' => '#ffffff',
			'theme_color'      => $color,
			'icons'            => array(),
		);

		if ( has_site_icon() ) {
			$manifest['icons'][] = array(
				'src'   => get_site_icon_url( 192 ),
				'sizes' => '192x192',
			);
			$manifest['icons'][] = array(
				'src'   => get_site_icon_url( 512 ),
				'sizes' => '512x512',
			);
		}

		header( 'Content-Type: application/manifest+json' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $manifest );
		exit;
	}

	/**
	 * Serve the service worker script via query var so it is scoped to the root.
	 */
	public function render_service_worker() {
		// Public, read-only endpoint selected by an exact query flag.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['mfm-sw'] ) || '1' !== $_GET['mfm-sw'] ) {
			return;
		}

		header( 'Content-Type: application/javascript' );
		header( 'Service-Worker-Allowed: ' . $this->get_app_scope() );
		?>
const CACHE_NAME = 'snaporder-v<?php echo esc_js( SNAPORDER_VERSION ); ?>';
const APP_URL = <?php echo wp_json_encode( $this->get_app_url() ); ?>;
const PRECACHE = [APP_URL];

self.addEventListener('install', event => {
	event.waitUntil(
	caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE))
	);
	self.skipWaiting();
});

self.addEventListener('activate', event => {
	event.waitUntil(
	caches.keys().then(keys =>
		Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
	)
	);
	self.clients.claim();
});

self.addEventListener('fetch', event => {
	if (event.request.method !== 'GET') return;
	const url = new URL(event.request.url);
	if (url.origin !== self.location.origin || url.searchParams.has('order_id') || url.pathname.startsWith('/wp-admin') || url.pathname.startsWith('/wp-json')) return;

	if (event.request.mode === 'navigate') {
		event.respondWith(fetch(event.request).catch(() => caches.match(APP_URL)));
		return;
	}

	event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
		if (response.ok) {
			const clone = response.clone();
			caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
		}
		return response;
	})));
});
		<?php
		exit;
	}

	/**
	 * Registers the service worker for the menu app scope.
	 */
	public function register_service_worker() {
		if ( ! is_page_template( 'mfm-app-view.php' ) ) {
			return;
		}
		?>
		<script>
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.register('<?php echo esc_url( site_url( '/?mfm-sw=1' ) ); ?>', { scope: '<?php echo esc_js( $this->get_app_scope() ); ?>' })
				.catch(function(err) { console.warn('SW registration failed:', err); });
		}
		</script>
		<?php
	}

	/**
	 * Gets the first URL using the SnapOrder app template.
	 *
	 * @return string
	 */
	private function get_app_url() {
		$pages = get_pages(
			array(
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Page-template meta is the canonical WordPress lookup.
				'meta_key'   => '_wp_page_template',
				'meta_value' => 'mfm-app-view.php',
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			)
		);

		return ! empty( $pages ) ? get_permalink( $pages[0]->ID ) : home_url( '/' );
	}

	/**
	 * Gets the URL path that the service worker may control.
	 *
	 * @return string
	 */
	private function get_app_scope() {
		$path = wp_parse_url( $this->get_app_url(), PHP_URL_PATH );
		return trailingslashit( $path ? $path : '/' );
	}
}
