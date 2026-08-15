<?php
/**
 * Stripe PaymentIntent integration and webhook verification.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe gateway implemented with the WordPress HTTP API.
 */
class SnapOrder_Stripe_Gateway {

	const API_BASE          = 'https://api.stripe.com/v1/';
	const WEBHOOK_TOLERANCE = 300;

	/**
	 * Register the verified webhook endpoint.
	 */
	public function __construct() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
		}
	}

	/**
	 * Register Stripe's public webhook receiver.
	 *
	 * Authentication is performed with Stripe's signed payload header.
	 */
	public function register_webhook_route() {
		register_rest_route(
			'snaporder/v1',
			'/stripe/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Create a PaymentIntent for an authoritative order total.
	 *
	 * @param int    $order_id   Order post ID.
	 * @param int    $amount     Amount in minor units.
	 * @param string $currency   ISO currency code.
	 * @param string $request_id Browser-generated idempotency UUID.
	 * @return array|WP_Error
	 */
	public function create_payment_intent( $order_id, $amount, $currency, $request_id ) {
		if ( $order_id <= 0 || $amount <= 0 || ! preg_match( '/^[a-z]{3}$/', strtolower( $currency ) ) ) {
			return new WP_Error( 'snaporder_invalid_payment', __( 'The payment request is invalid.', 'lineweb-restaurant-orders' ) );
		}

		$response = $this->api_request(
			'payment_intents',
			'POST',
			array(
				'amount'                       => (int) $amount,
				'currency'                     => strtolower( $currency ),
				'payment_method_types[]'       => 'card',
				'description'                  => sprintf( 'Restaurant order #%d', $order_id ),
				'metadata[snaporder_order_id]' => (string) $order_id,
			),
			'snaporder_' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $request_id )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['id'] ) || empty( $response['client_secret'] ) ) {
			return new WP_Error( 'snaporder_stripe_error', __( 'Stripe could not start the payment.', 'lineweb-restaurant-orders' ) );
		}

		return array(
			'id'            => sanitize_text_field( $response['id'] ),
			'client_secret' => sanitize_text_field( $response['client_secret'] ),
			'status'        => isset( $response['status'] ) ? sanitize_key( $response['status'] ) : '',
		);
	}

	/**
	 * Retrieve and validate an order's PaymentIntent.
	 *
	 * @param int    $order_id  Order post ID.
	 * @param string $intent_id Stripe PaymentIntent ID.
	 * @return array|WP_Error
	 */
	public function verify_order_payment( $order_id, $intent_id ) {
		if ( ! preg_match( '/^pi_[a-zA-Z0-9_]+$/', $intent_id ) ) {
			return new WP_Error( 'snaporder_invalid_payment', __( 'The payment reference is invalid.', 'lineweb-restaurant-orders' ) );
		}

		$intent = $this->api_request( 'payment_intents/' . rawurlencode( $intent_id ), 'GET' );
		if ( is_wp_error( $intent ) ) {
			return $intent;
		}

		$expected_intent   = (string) get_post_meta( $order_id, '_snaporder_stripe_intent_id', true );
		$expected_amount   = (int) get_post_meta( $order_id, '_snaporder_order_total_cents', true );
		$expected_currency = (string) get_post_meta( $order_id, '_snaporder_currency', true );
		$valid             = self::validate_intent( $intent, $order_id, $expected_intent, $expected_amount, $expected_currency );

		return is_wp_error( $valid ) ? $valid : $intent;
	}

	/**
	 * Validate all business-critical PaymentIntent fields.
	 *
	 * @param array  $intent            Stripe PaymentIntent payload.
	 * @param int    $order_id          Expected order ID.
	 * @param string $expected_intent   Expected PaymentIntent ID.
	 * @param int    $expected_amount   Expected amount in minor units.
	 * @param string $expected_currency Expected ISO currency code.
	 * @return true|WP_Error
	 */
	public static function validate_intent( $intent, $order_id, $expected_intent, $expected_amount, $expected_currency ) {
		if ( ! is_array( $intent ) ) {
			return new WP_Error( 'snaporder_payment_not_verified', __( 'The payment could not be verified.', 'lineweb-restaurant-orders' ) );
		}

		$intent_order_id = isset( $intent['metadata']['snaporder_order_id'] ) ? (string) $intent['metadata']['snaporder_order_id'] : '';
		$received_amount = isset( $intent['amount_received'] ) ? (int) $intent['amount_received'] : 0;

		if (
			empty( $expected_intent ) ||
			! isset( $intent['id'], $intent['status'], $intent['currency'] ) ||
			! hash_equals( $expected_intent, (string) $intent['id'] ) ||
			'succeeded' !== $intent['status'] ||
			$received_amount !== (int) $expected_amount ||
			strtolower( (string) $intent['currency'] ) !== strtolower( $expected_currency ) ||
			$intent_order_id !== (string) $order_id
		) {
			return new WP_Error( 'snaporder_payment_not_verified', __( 'The payment could not be verified.', 'lineweb-restaurant-orders' ) );
		}

		return true;
	}

	/**
	 * Verify Stripe's signed webhook header.
	 *
	 * @param string   $payload    Raw request body.
	 * @param string   $header     Stripe-Signature header.
	 * @param string   $secret     Webhook signing secret.
	 * @param int|null $now        Current timestamp, injectable for tests.
	 * @return bool
	 */
	public static function verify_webhook_signature( $payload, $header, $secret, $now = null ) {
		if ( '' === $payload || '' === $header || '' === $secret ) {
			return false;
		}

		$timestamp  = null;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = array_map( 'trim', explode( '=', $part, 2 ) );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] && ctype_digit( $pair[1] ) ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] && preg_match( '/^[a-f0-9]{64}$/', $pair[1] ) ) {
				$signatures[] = $pair[1];
			}
		}

		$now = null === $now ? time() : (int) $now;
		if ( null === $timestamp || empty( $signatures ) || abs( $now - $timestamp ) > self::WEBHOOK_TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle a signed Stripe webhook.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( $request ) {
		$payload   = $request->get_body();
		$signature = (string) $request->get_header( 'stripe-signature' );
		$secret    = SnapOrder_Settings::get_stripe_webhook_secret();

		if ( ! self::verify_webhook_signature( $payload, $signature, $secret ) ) {
			return new WP_Error( 'snaporder_invalid_webhook', __( 'Invalid Stripe signature.', 'lineweb-restaurant-orders' ), array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) || empty( $event['data']['object'] ) ) {
			return new WP_Error( 'snaporder_invalid_webhook', __( 'Invalid Stripe payload.', 'lineweb-restaurant-orders' ), array( 'status' => 400 ) );
		}

		$intent   = $event['data']['object'];
		$order_id = isset( $intent['metadata']['snaporder_order_id'] ) ? absint( $intent['metadata']['snaporder_order_id'] ) : 0;

		if ( ! $order_id || 'snaporder_order' !== get_post_type( $order_id ) ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		if ( 'payment_intent.succeeded' === $event['type'] ) {
			$expected_intent   = (string) get_post_meta( $order_id, '_snaporder_stripe_intent_id', true );
			$expected_amount   = (int) get_post_meta( $order_id, '_snaporder_order_total_cents', true );
			$expected_currency = (string) get_post_meta( $order_id, '_snaporder_currency', true );
			$valid             = self::validate_intent( $intent, $order_id, $expected_intent, $expected_amount, $expected_currency );

			if ( is_wp_error( $valid ) ) {
				return new WP_Error( 'snaporder_invalid_webhook_payment', __( 'Stripe payment verification failed.', 'lineweb-restaurant-orders' ), array( 'status' => 400 ) );
			}

			do_action( 'snaporder_stripe_payment_succeeded', $order_id, $intent );
		} elseif ( in_array( $event['type'], array( 'payment_intent.payment_failed', 'payment_intent.canceled' ), true ) ) {
			$expected_intent = (string) get_post_meta( $order_id, '_snaporder_stripe_intent_id', true );
			$received_intent = isset( $intent['id'] ) ? (string) $intent['id'] : '';
			if ( '' === $expected_intent || '' === $received_intent || ! hash_equals( $expected_intent, $received_intent ) ) {
				return new WP_Error( 'snaporder_invalid_webhook_payment', __( 'Stripe payment verification failed.', 'lineweb-restaurant-orders' ), array( 'status' => 400 ) );
			}

			do_action( 'snaporder_stripe_payment_failed', $order_id, $intent );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Execute one authenticated Stripe API request.
	 *
	 * @param string $path            API path.
	 * @param string $method          HTTP method.
	 * @param array  $body            Form body.
	 * @param string $idempotency_key Optional idempotency key.
	 * @return array|WP_Error
	 */
	private function api_request( $path, $method, $body = array(), $idempotency_key = '' ) {
		$secret = SnapOrder_Settings::get_stripe_secret();
		if ( ! $secret ) {
			return new WP_Error( 'snaporder_stripe_not_configured', __( 'Stripe is not configured.', 'lineweb-restaurant-orders' ) );
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $secret,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);
		if ( $idempotency_key ) {
			$headers['Idempotency-Key'] = substr( $idempotency_key, 0, 255 );
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 20,
		);
		if ( 'POST' === $method ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( self::API_BASE . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'snaporder_stripe_unavailable', __( 'Stripe is temporarily unavailable.', 'lineweb-restaurant-orders' ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'snaporder_stripe_error', __( 'Stripe could not process the payment.', 'lineweb-restaurant-orders' ) );
		}

		return $data;
	}
}
