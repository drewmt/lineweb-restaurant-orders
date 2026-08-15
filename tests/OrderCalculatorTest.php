<?php
/**
 * Order calculator regression tests.
 *
 * @package SnapOrder
 */

class SnapOrder_Order_Calculator_Test extends WP_UnitTestCase {

	private $product_id;

	public function set_up() {
		parent::set_up();

		$this->product_id = self::factory()->post->create(
			array(
				'post_type'   => 'snaporder_item',
				'post_status' => 'publish',
				'post_title'  => 'House Burger',
			)
		);

		update_post_meta( $this->product_id, '_snaporder_price', '10.00' );
		update_post_meta(
			$this->product_id,
			'_snaporder_size',
			array(
				array(
					'name'  => 'Large',
					'price' => '2.00',
				),
			)
		);
		update_post_meta(
			$this->product_id,
			'_snaporder_extras',
			array(
				array(
					'name'  => 'Cheese',
					'price' => '1.50',
				),
			)
		);
	}

	public function test_uses_server_prices_instead_of_client_values() {
		$calculator = new SnapOrder_Order_Calculator();
		$result     = $calculator->calculate(
			array(
				array(
					'id'            => $this->product_id,
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

		$this->assertNotWPError( $result );
		$this->assertSame( 3000, $result['subtotal_cents'] );
		$this->assertSame( 300, $result['tip_cents'] );
		$this->assertSame( 3300, $result['total_cents'] );
		$this->assertSame( 'House Burger', $result['items'][0]['title'] );
		$this->assertSame( '15.00', $result['items'][0]['price'] );
	}

	public function test_rejects_an_unknown_variant() {
		$calculator = new SnapOrder_Order_Calculator();
		$result     = $calculator->calculate(
			array(
				array(
					'id'            => $this->product_id,
					'qty'           => 1,
					'variant_index' => 99,
					'extras'        => array(),
				)
			),
			'0',
			false
		);

		$this->assertWPError( $result );
		$this->assertSame( 'snaporder_invalid_variant', $result->get_error_code() );
	}

	public function test_rejects_an_empty_cart() {
		$calculator = new SnapOrder_Order_Calculator();
		$result     = $calculator->calculate( array(), '0', false );

		$this->assertWPError( $result );
		$this->assertSame( 'snaporder_empty_cart', $result->get_error_code() );
	}

	public function test_rejects_a_tip_above_the_subtotal() {
		$calculator = new SnapOrder_Order_Calculator();
		$result     = $calculator->calculate(
			array(
				array(
					'id'     => $this->product_id,
					'qty'    => 1,
					'extras' => array(),
				)
			),
			'10.01',
			true
		);

		$this->assertWPError( $result );
		$this->assertSame( 'snaporder_invalid_tip', $result->get_error_code() );
	}
}

