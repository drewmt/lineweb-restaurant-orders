<?php
/**
 * Main SnapOrder plugin controller.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads SnapOrder components and registers shared assets.
 */
final class SnapOrder {

	/**
	 * Singleton instance.
	 *
	 * @var SnapOrder|null
	 */
	protected static $instance = null;

	/**
	 * Gets the plugin instance.
	 *
	 * @return SnapOrder
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers plugin components and hooks.
	 */
	private function __construct() {
		$this->includes();
		$this->instantiate_classes();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_filter( 'plugin_action_links_' . SNAPORDER_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Loads component classes.
	 */
	private function includes() {
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-order-calculator.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-stripe-gateway.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-privacy.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-post-type.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-metaboxes.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-product-options.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-featured.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-shortcode.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-template-loader.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-orders.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-settings.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-admin-sorting.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-statistics.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-qr-code.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-whatsapp.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-sound.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-tips.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-pwa.php';
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-printer.php';
	}

	/**
	 * Instantiates plugin components.
	 */
	private function instantiate_classes() {
		new SnapOrder_Post_Type();
		new SnapOrder_Metaboxes();
		new SnapOrder_Product_Options();
		new SnapOrder_Featured();
		new SnapOrder_Shortcode();
		new SnapOrder_Template_Loader();
		new SnapOrder_Orders();
		new SnapOrder_Settings();
		new SnapOrder_Admin_Sorting();
		new SnapOrder_Statistics();
		new SnapOrder_QR_Code();
		new SnapOrder_Stripe_Gateway();
		new SnapOrder_Privacy();
		new SnapOrder_WhatsApp();
		new SnapOrder_Sound();
		new SnapOrder_Tips();
		new SnapOrder_PWA();
		new SnapOrder_Printer();
	}

	/**
	 * Enqueues assets on SnapOrder frontend surfaces only.
	 */
	public function enqueue_frontend() {
		if ( ! $this->is_frontend_surface() ) {
			return;
		}

		wp_enqueue_style( 'snaporder-app', SNAPORDER_PLUGIN_URL . 'assets/css/app.css', array(), SNAPORDER_VERSION );
		wp_enqueue_style( 'mfm-style', SNAPORDER_PLUGIN_URL . 'assets/css/style.css', array(), SNAPORDER_VERSION );
		wp_enqueue_script( 'snaporder-lucide', SNAPORDER_PLUGIN_URL . 'assets/vendor/lucide/lucide.min.js', array(), '0.468.0', true );

		$dependencies = array( 'jquery', 'snaporder-lucide' );
		if ( in_array( 'stripe', SnapOrder_Settings::get_enabled_payment_methods(), true ) ) {
			// Stripe requires merchants to load Stripe.js directly from js.stripe.com.
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The service controls the canonical versioned script.
			wp_enqueue_script( 'snaporder-stripe', 'https://js.stripe.com/v3/', array(), null, true );
			$dependencies[] = 'snaporder-stripe';
		}

		wp_enqueue_script( 'mfm-script', SNAPORDER_PLUGIN_URL . 'assets/js/script.js', $dependencies, SNAPORDER_VERSION, true );
		$payment_methods = SnapOrder_Settings::get_enabled_payment_methods();
		wp_localize_script(
			'mfm-script',
			'mfm_vars',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'snaporder_order_nonce' ),
				'currency'        => SnapOrder_Settings::get_currency_symbol(),
				'stripe_key'      => SnapOrder_Settings::get_stripe_publishable_key(),
				'default_payment' => ! empty( $payment_methods ) ? $payment_methods[0] : '',
				'strings'         => array(
					'processing'    => __( 'Processing...', 'lineweb-restaurant-orders' ),
					'empty_cart'    => __( 'Your cart is empty.', 'lineweb-restaurant-orders' ),
					'order_error'   => __( 'We could not place the order. Please try again.', 'lineweb-restaurant-orders' ),
					'payment_error' => __( 'The card payment could not be completed.', 'lineweb-restaurant-orders' ),
					'invalid_link'  => __( 'This order link is not available on this device.', 'lineweb-restaurant-orders' ),
				),
			)
		);
	}

	/**
	 * Determines whether the current request renders SnapOrder UI.
	 *
	 * @return bool
	 */
	private function is_frontend_surface() {
		if ( is_page_template( 'mfm-app-view.php' ) ) {
			return true;
		}

		global $post;

		return is_singular() && $post instanceof WP_Post && has_shortcode( $post->post_content, 'modern_food_menu' );
	}

	/**
	 * Enqueues assets on SnapOrder admin screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ( ! in_array( $screen->post_type, array( 'food_item', 'mfm_order', 'mfm_banner' ), true ) && false === strpos( $hook, 'mfm-' ) ) ) {
			return;
		}

		wp_enqueue_style( 'mfm-admin-style', SNAPORDER_PLUGIN_URL . 'assets/css/admin-style.css', array(), SNAPORDER_VERSION );
		wp_enqueue_script( 'mfm-admin-script', SNAPORDER_PLUGIN_URL . 'assets/js/admin-script.js', array( 'jquery' ), SNAPORDER_VERSION, true );
		wp_localize_script(
			'mfm-admin-script',
			'mfm_admin_vars',
			array(
				'nonce' => wp_create_nonce( 'mfm_nonce' ),
			)
		);
	}

	/**
	 * Adds a settings link to the Plugins screen.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[]
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=food_item&page=mfm-settings' ) ) . '">' . esc_html__( 'Settings', 'lineweb-restaurant-orders' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}
}
