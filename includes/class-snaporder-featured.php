<?php
/**
 * Save "Featured Item" post meta.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saves the featured-product flag.
 */
class SnapOrder_Featured {

	/**
	 * Registers the product-save hook.
	 */
	public function __construct() {
		add_action( 'snaporder_save_product_options', array( $this, 'save_featured' ), 10, 1 );
	}

	/**
	 * Persists the featured-product checkbox.
	 *
	 * @param int $post_id Food item post ID.
	 */
	public function save_featured( $post_id ) {
		// The parent food-details save handler verifies the form nonce first.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The parent save handler has already verified the nonce.
		if ( isset( $_POST['snaporder_featured'] ) ) {
			update_post_meta( $post_id, '_snaporder_featured', '1' );
		} else {
			delete_post_meta( $post_id, '_snaporder_featured' );
		}
	}
}
