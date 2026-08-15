<?php
/**
 * WhatsApp order notifications via Twilio.
 *
 * Credentials are resolved through SnapOrder_Settings::get_twilio_credentials()
 * which checks wp-config constants before falling back to DB options.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends optional order updates through Twilio WhatsApp.
 */
class SnapOrder_WhatsApp {

	/**
	 * Registers order-notification hooks.
	 */
	public function __construct() {
		add_action( 'snaporder_new_order_placed', array( $this, 'send_new_order_notification' ), 10, 2 );
		add_action( 'snaporder_order_status_updated', array( $this, 'send_status_update_notification' ), 10, 2 );
	}

	/**
	 * Sends the order-received message.
	 *
	 * @param int   $order_id   Order post ID.
	 * @param array $order_data Canonical order summary.
	 */
	public function send_new_order_notification( $order_id, $order_data ) {
		if ( get_option( 'snaporder_whatsapp_enabled' ) !== '1' ) {
			return;
		}

		$phone = $order_data['phone'] ?? '';
		if ( ! $phone ) {
			return;
		}

		$msg = sprintf(
			/* translators: 1: order ID, 2: total with currency */
			__( 'Thanks for your order #%1$s! We have received it and will start preparing it soon. Total: %2$s', 'lineweb-restaurant-orders' ),
			$order_id,
			$order_data['total'] . SnapOrder_Settings::get_currency_symbol()
		);

		$this->send_whatsapp( $phone, $msg );
	}

	/**
	 * Sends a customer-facing status update.
	 *
	 * @param int    $order_id  Order post ID.
	 * @param string $new_status New order status.
	 */
	public function send_status_update_notification( $order_id, $new_status ) {
		if ( get_option( 'snaporder_whatsapp_enabled' ) !== '1' ) {
			return;
		}

		$phone = get_post_meta( $order_id, '_snaporder_customer_phone', true );
		if ( ! $phone ) {
			return;
		}

		$messages = array(
			/* translators: %s: order ID. */
			'accepted'  => __( 'Your order #%s has been ACCEPTED and is being processed.', 'lineweb-restaurant-orders' ),
			/* translators: %s: order ID. */
			'cooking'   => __( 'Your order #%s is now being PREPARED by our kitchen.', 'lineweb-restaurant-orders' ),
			/* translators: %s: order ID. */
			'ready'     => __( 'Great news! Your order #%s is READY for pickup/delivery.', 'lineweb-restaurant-orders' ),
			/* translators: %s: order ID. */
			'completed' => __( 'Your order #%s has been COMPLETED. Enjoy your meal!', 'lineweb-restaurant-orders' ),
			/* translators: %s: order ID. */
			'rejected'  => __( 'Important: Your order #%s was cancelled. Please contact us for details.', 'lineweb-restaurant-orders' ),
		);

		if ( ! isset( $messages[ $new_status ] ) ) {
			return;
		}

		$this->send_whatsapp( $phone, sprintf( $messages[ $new_status ], $order_id ) );
	}

	/**
	 * Sends one validated WhatsApp message through Twilio.
	 *
	 * @param string $to_number Customer phone number.
	 * @param string $body      Message text.
	 */
	private function send_whatsapp( $to_number, $body ) {
		$creds = SnapOrder_Settings::get_twilio_credentials();
		$sid   = (string) $creds['sid'];
		$token = (string) $creds['token'];
		$from  = preg_replace( '/[^0-9+]/', '', preg_replace( '/^whatsapp:/i', '', trim( (string) $creds['phone'] ) ) );
		$to    = preg_replace( '/[^0-9+]/', '', preg_replace( '/^whatsapp:/i', '', trim( (string) $to_number ) ) );

		if (
			! preg_match( '/^AC[a-f0-9]{32}$/i', $sid ) ||
			! preg_match( '/^[a-f0-9]{32}$/i', $token ) ||
			! preg_match( '/^\+[1-9][0-9]{7,14}$/', $from ) ||
			! preg_match( '/^\+[1-9][0-9]{7,14}$/', $to )
		) {
			return;
		}

		$url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json';

		wp_remote_post(
			$url,
			array(
				'headers' => array(
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication requires Base64 encoding; this is not encryption.
					'Authorization' => 'Basic ' . base64_encode( "{$sid}:{$token}" ),
				),
				'body'    => array(
					'From' => 'whatsapp:' . $from,
					'To'   => 'whatsapp:' . $to,
					'Body' => $body,
				),
				'timeout' => 10,
			)
		);
	}
}
