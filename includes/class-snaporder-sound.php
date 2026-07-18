<?php
/**
 * Real-time new-order sound notifications for the admin orders page.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides opt-in browser sound alerts for new paid or cash orders.
 */
class SnapOrder_Sound {

	/**
	 * Registers sound-alert hooks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_sound_scripts' ) );
		add_action( 'wp_ajax_mfm_check_new_orders', array( $this, 'check_new_orders' ) );
	}

	/**
	 * Enqueues sound polling on the order-management screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_sound_scripts( $hook ) {
		unset( $hook );
		// Read-only screen selection; no state is changed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'mfm-manage-orders' !== $page ) {
			return;
		}

		wp_enqueue_script(
			'mfm-sound-script',
			SNAPORDER_PLUGIN_URL . 'assets/js/admin-sound.js',
			array( 'jquery' ),
			SNAPORDER_VERSION,
			true
		);

		wp_localize_script(
			'mfm-sound-script',
			'mfm_sound_vars',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'mfm_sound_nonce' ),
				'latest_order_id' => $this->get_latest_order_id(),
			)
		);
	}

	/**
	 * Gets the newest actionable order ID.
	 *
	 * @return int
	 */
	private function get_latest_order_id() {
		$latest = get_posts(
			array(
				'post_type'      => 'mfm_order',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'post_status'    => 'any',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The marker identifies orders that are ready to notify.
				'meta_key'       => '_snaporder_new_order_notified',
			)
		);
		return ! empty( $latest ) ? $latest[0]->ID : 0;
	}

	/**
	 * Returns whether a newer actionable order exists.
	 */
	public function check_new_orders() {
		check_ajax_referer( 'mfm_sound_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$last_id = intval( $_POST['last_id'] ?? 0 );

		$new_orders = get_posts(
			array(
				'post_type'      => 'mfm_order',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The marker excludes unpaid Stripe orders.
				'meta_key'       => '_snaporder_new_order_notified',
				'date_query'     => array(
					array( 'after' => '1 hour ago' ),
				),
			)
		);

		if ( ! empty( $new_orders ) && $new_orders[0]->ID > $last_id ) {
			wp_send_json_success(
				array(
					'new_orders' => true,
					'latest_id'  => $new_orders[0]->ID,
				)
			);
		}

		wp_send_json_success( array( 'new_orders' => false ) );
	}
}
