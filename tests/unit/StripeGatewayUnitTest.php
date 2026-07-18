<?php
/**
 * Stripe verification regression tests.
 *
 * @package SnapOrder
 */

use PHPUnit\Framework\TestCase;

class SnapOrder_Stripe_Gateway_Unit_Test extends TestCase {

	public function test_accepts_only_the_expected_succeeded_intent() {
		$intent = array(
			'id'              => 'pi_expected',
			'status'          => 'succeeded',
			'amount'          => 3300,
			'amount_received' => 3300,
			'currency'        => 'eur',
			'metadata'        => array(
				'snaporder_order_id' => '77',
			),
		);

		$result = SnapOrder_Stripe_Gateway::validate_intent( $intent, 77, 'pi_expected', 3300, 'EUR' );
		$this->assertTrue( $result );
	}

	public function test_rejects_wrong_intent_amount_currency_order_or_status() {
		$base = array(
			'id'              => 'pi_expected',
			'status'          => 'succeeded',
			'amount'          => 3300,
			'amount_received' => 3300,
			'currency'        => 'eur',
			'metadata'        => array(
				'snaporder_order_id' => '77',
			),
		);

		$cases = array(
			array_replace( $base, array( 'id' => 'pi_other' ) ),
			array_replace( $base, array( 'status' => 'requires_payment_method' ) ),
			array_replace( $base, array( 'amount_received' => 1 ) ),
			array_replace( $base, array( 'currency' => 'usd' ) ),
			array_replace_recursive( $base, array( 'metadata' => array( 'snaporder_order_id' => '78' ) ) ),
		);

		foreach ( $cases as $intent ) {
			$this->assertInstanceOf(
				WP_Error::class,
				SnapOrder_Stripe_Gateway::validate_intent( $intent, 77, 'pi_expected', 3300, 'EUR' )
			);
		}
	}

	public function test_verifies_webhook_signature_and_timestamp() {
		$payload   = '{"id":"evt_123"}';
		$timestamp = 1700000000;
		$secret    = 'whsec_test';
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		$header    = 't=' . $timestamp . ',v1=' . $signature;

		$this->assertTrue(
			SnapOrder_Stripe_Gateway::verify_webhook_signature( $payload, $header, $secret, $timestamp + 60 )
		);
		$this->assertFalse(
			SnapOrder_Stripe_Gateway::verify_webhook_signature( $payload, $header, $secret, $timestamp + 301 )
		);
		$this->assertFalse(
			SnapOrder_Stripe_Gateway::verify_webhook_signature( $payload . 'x', $header, $secret, $timestamp + 60 )
		);
	}

	public function test_rejects_non_array_intent_payload() {
		$this->assertInstanceOf(
			WP_Error::class,
			SnapOrder_Stripe_Gateway::validate_intent( null, 77, 'pi_expected', 3300, 'EUR' )
		);
	}
}
