<?php
/**
 * Pure order-calculator tests.
 *
 * @package SnapOrder
 */

use PHPUnit\Framework\TestCase;

class SnapOrder_Order_Calculator_Unit_Test extends TestCase {

	private function product_loader() {
		return static function ( $product_id ) {
			if ( 42 !== $product_id ) {
				return new WP_Error( 'snaporder_invalid_product', 'Product is unavailable.' );
			}

			return array(
				'id'       => 42,
				'title'    => 'House Burger',
				'image'    => 'https://example.test/burger.jpg',
				'price'    => '10.00',
				'variants' => array(
					array(
						'name'  => 'Large',
						'price' => '2.00',
					),
				),
				'extras'   => array(
					array(
						'name'  => 'Cheese',
						'price' => '1.50',
					),
				),
			);
		};
	}

	public function test_ignores_client_prices_and_titles() {
		$calculator = new SnapOrder_Order_Calculator( $this->product_loader() );
		$result     = $calculator->calculate(
			array(
				array(
					'id'            => 42,
					'title'         => 'Manipulated title',
					'price'         => '0.01',
					'qty'           => 2,
					'variant_index' => 0,
					'extras'        => array(
						array(
							'index' => 0,
							'price' => '0.00',
							'qty'   => 2,
						),
					),
				)
			),
			'3.00',
			true
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 3000, $result['subtotal_cents'] );
		$this->assertSame( 300, $result['tip_cents'] );
		$this->assertSame( 3300, $result['total_cents'] );
		$this->assertSame( 'House Burger', $result['items'][0]['title'] );
		$this->assertSame( '15.00', $result['items'][0]['price'] );
	}

	public function test_rejects_unknown_variant_and_extra_indexes() {
		$calculator = new SnapOrder_Order_Calculator( $this->product_loader() );

		$invalid_variant = $calculator->calculate(
			array(
				array(
					'id'            => 42,
					'qty'           => 1,
					'variant_index' => 9,
					'extras'        => array(),
				)
			),
			'0',
			false
		);
		$this->assertInstanceOf( WP_Error::class, $invalid_variant );
		$this->assertSame( 'snaporder_invalid_variant', $invalid_variant->get_error_code() );

		$invalid_extra = $calculator->calculate(
			array(
				array(
					'id'     => 42,
					'qty'    => 1,
					'extras' => array(
						array(
							'index' => 5,
							'qty'   => 1,
						),
					),
				)
			),
			'0',
			false
		);
		$this->assertInstanceOf( WP_Error::class, $invalid_extra );
		$this->assertSame( 'snaporder_invalid_extra', $invalid_extra->get_error_code() );
	}

	public function test_rejects_empty_cart_invalid_quantity_and_excessive_tip() {
		$calculator = new SnapOrder_Order_Calculator( $this->product_loader() );

		$empty = $calculator->calculate( array(), '0', false );
		$this->assertSame( 'snaporder_empty_cart', $empty->get_error_code() );

		$invalid_quantity = $calculator->calculate(
			array(
				array(
					'id'     => 42,
					'qty'    => 0,
					'extras' => array(),
				)
			),
			'0',
			false
		);
		$this->assertSame( 'snaporder_invalid_quantity', $invalid_quantity->get_error_code() );

		$invalid_tip = $calculator->calculate(
			array(
				array(
					'id'     => 42,
					'qty'    => 1,
					'extras' => array(),
				)
			),
			'10.01',
			true
		);
		$this->assertSame( 'snaporder_invalid_tip', $invalid_tip->get_error_code() );
	}

	public function test_money_parser_is_decimal_safe() {
		$this->assertSame( 1299, SnapOrder_Order_Calculator::money_to_cents( '12.99' ) );
		$this->assertSame( 1299, SnapOrder_Order_Calculator::money_to_cents( '12,99' ) );
		$this->assertInstanceOf( WP_Error::class, SnapOrder_Order_Calculator::money_to_cents( '-1.00' ) );
		$this->assertInstanceOf( WP_Error::class, SnapOrder_Order_Calculator::money_to_cents( '12.999' ) );
	}

	public function test_rejects_malformed_catalog_options() {
		$calculator = new SnapOrder_Order_Calculator(
			static function () {
				return array(
					'id'       => 42,
					'title'    => 'Unsafe item',
					'price'    => '10.00',
					'variants' => array( 'not-an-array' ),
					'extras'   => array( array( 'name' => 'Missing price' ) ),
				);
			}
		);

		$variant = $calculator->calculate( array( array( 'id' => 42, 'qty' => 1, 'variant_index' => 0 ) ), '0', false );
		$this->assertSame( 'snaporder_invalid_variant', $variant->get_error_code() );

		$extra = $calculator->calculate( array( array( 'id' => 42, 'qty' => 1, 'extras' => array( array( 'index' => 0, 'qty' => 1 ) ) ) ), '0', false );
		$this->assertSame( 'snaporder_invalid_extra', $extra->get_error_code() );
	}
}
