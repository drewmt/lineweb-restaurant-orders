<?php
/**
 * Privacy disclosures and optional order-data retention.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers privacy disclosures and personal-data retention cleanup.
 */
class SnapOrder_Privacy {

	const CRON_HOOK = 'snaporder_privacy_cleanup';

	/**
	 * Registers privacy hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_action( self::CRON_HOOK, array( $this, 'anonymize_expired_orders' ) );
		add_action( 'init', array( $this, 'ensure_cleanup_schedule' ) );
	}

	/**
	 * Ensures the daily cleanup event is scheduled.
	 */
	public function ensure_cleanup_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Adds SnapOrder guidance to WordPress privacy-policy content.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p>' . esc_html__( 'When a customer places an order, Lineweb Restaurant Orders stores the contact, delivery, order, and payment-status information entered at checkout. This data is used to fulfil and manage the order.', 'lineweb-restaurant-orders' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If Stripe card payments are enabled, payment data is sent directly to Stripe and Lineweb Restaurant Orders stores the related payment reference and status. If Twilio WhatsApp notifications are enabled, the customer phone number and order message are sent to Twilio.', 'lineweb-restaurant-orders' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Lineweb Restaurant Orders also stores aggregate menu-item view counts without retaining a visitor IP address. The site owner controls how long completed order personal data is retained in the plugin settings.', 'lineweb-restaurant-orders' ) . '</p>';

		wp_add_privacy_policy_content( 'Lineweb Restaurant Orders', wp_kses_post( wpautop( $content ) ) );
	}

	/**
	 * Anonymizes completed orders whose retention period has expired.
	 */
	public function anonymize_expired_orders() {
		$retention_days = absint( get_option( 'snaporder_order_retention_days', 0 ) );
		if ( 0 === $retention_days ) {
			return;
		}

		$expires_before = current_datetime()->modify( '-' . $retention_days . ' days' )->format( 'Y-m-d H:i:s' );
		$order_ids      = get_posts(
			array(
				'post_type'      => 'snaporder_order',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'date_query'     => array(
					array(
						'before' => $expires_before,
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cleanup must select terminal order states before anonymization.
				'meta_query'     => array(
					array(
						'key'     => '_snaporder_order_status',
						'value'   => array( 'completed', 'rejected', 'payment_failed' ),
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $order_ids as $order_id ) {
			$this->anonymize_order( $order_id );
		}
	}

	/**
	 * Removes personal fields from one retained order.
	 *
	 * @param int $order_id Order post ID.
	 */
	private function anonymize_order( $order_id ) {
		update_post_meta( $order_id, '_snaporder_customer_name', __( 'Anonymized customer', 'lineweb-restaurant-orders' ) );
		foreach ( array( '_snaporder_customer_phone', '_snaporder_address', '_snaporder_street', '_snaporder_house_number', '_snaporder_city', '_snaporder_zip', '_snaporder_order_notes', '_snaporder_order_token', '_snaporder_transaction_id', '_snaporder_stripe_intent_id', '_snaporder_stripe_client_secret', '_snaporder_request_id' ) as $meta_key ) {
			delete_post_meta( $order_id, $meta_key );
		}

		$items = get_post_meta( $order_id, '_snaporder_cart_items', true );
		if ( is_array( $items ) ) {
			foreach ( $items as &$item ) {
				$item['notes'] = '';
			}
			unset( $item );
			update_post_meta( $order_id, '_snaporder_cart_items', $items );
		}
	}
}
