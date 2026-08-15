<?php
/**
 * Server-authoritative order total calculation.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rebuilds cart lines from the WordPress product catalogue.
 *
 * Client titles and prices are deliberately ignored. The browser is only
 * allowed to select product, variant and extra indexes plus quantities.
 */
class SnapOrder_Order_Calculator {

	const MAX_CART_LINES  = 50;
	const MAX_ITEM_QTY    = 99;
	const MAX_EXTRA_QTY   = 20;
	const MAX_TOTAL_CENTS = 100000000;

	/**
	 * Product loader.
	 *
	 * @var callable
	 */
	private $product_loader;

	/**
	 * Constructor.
	 *
	 * @param callable|null $product_loader Optional loader used by unit tests.
	 */
	public function __construct( $product_loader = null ) {
		$this->product_loader = is_callable( $product_loader )
			? $product_loader
			: array( $this, 'load_product' );
	}

	/**
	 * Calculate a canonical cart.
	 *
	 * @param array $raw_cart        Untrusted cart payload.
	 * @param mixed $raw_tip         Untrusted tip amount.
	 * @param bool  $tipping_enabled Whether tipping is enabled.
	 * @return array|WP_Error
	 */
	public function calculate( $raw_cart, $raw_tip, $tipping_enabled ) {
		if ( ! is_array( $raw_cart ) || empty( $raw_cart ) ) {
			return new WP_Error( 'snaporder_empty_cart', __( 'Your cart is empty.', 'lineweb-restaurant-orders' ) );
		}

		if ( count( $raw_cart ) > self::MAX_CART_LINES ) {
			return new WP_Error( 'snaporder_cart_too_large', __( 'The cart contains too many items.', 'lineweb-restaurant-orders' ) );
		}

		$items          = array();
		$subtotal_cents = 0;

		foreach ( $raw_cart as $raw_item ) {
			$item = $this->calculate_item( $raw_item );

			if ( is_wp_error( $item ) ) {
				return $item;
			}

			if ( $subtotal_cents > self::MAX_TOTAL_CENTS - $item['line_total_cents'] ) {
				return new WP_Error( 'snaporder_total_too_large', __( 'The order total is too large.', 'lineweb-restaurant-orders' ) );
			}

			$subtotal_cents += $item['line_total_cents'];
			$items[]         = $item;
		}

		if ( $subtotal_cents <= 0 ) {
			return new WP_Error( 'snaporder_invalid_total', __( 'The order total must be greater than zero.', 'lineweb-restaurant-orders' ) );
		}

		$tip_cents = 0;
		if ( $tipping_enabled ) {
			$tip_cents = self::money_to_cents( $raw_tip );

			if ( is_wp_error( $tip_cents ) || $tip_cents > $subtotal_cents ) {
				return new WP_Error( 'snaporder_invalid_tip', __( 'The selected tip is invalid.', 'lineweb-restaurant-orders' ) );
			}
		}

		$total_cents = $subtotal_cents + $tip_cents;
		if ( $total_cents > self::MAX_TOTAL_CENTS ) {
			return new WP_Error( 'snaporder_total_too_large', __( 'The order total is too large.', 'lineweb-restaurant-orders' ) );
		}

		return array(
			'items'          => $items,
			'subtotal_cents' => $subtotal_cents,
			'tip_cents'      => $tip_cents,
			'total_cents'    => $total_cents,
			'subtotal'       => self::cents_to_money( $subtotal_cents ),
			'tip'            => self::cents_to_money( $tip_cents ),
			'total'          => self::cents_to_money( $total_cents ),
		);
	}

	/**
	 * Calculate one canonical cart line.
	 *
	 * @param mixed $raw_item Untrusted line payload.
	 * @return array|WP_Error
	 */
	private function calculate_item( $raw_item ) {
		if ( ! is_array( $raw_item ) ) {
			return new WP_Error( 'snaporder_invalid_cart_item', __( 'A cart item is invalid.', 'lineweb-restaurant-orders' ) );
		}

		$product_id = isset( $raw_item['id'] ) ? (int) $raw_item['id'] : 0;
		$quantity   = isset( $raw_item['qty'] ) ? (int) $raw_item['qty'] : 0;

		if ( $product_id <= 0 ) {
			return new WP_Error( 'snaporder_invalid_product', __( 'A selected product is unavailable.', 'lineweb-restaurant-orders' ) );
		}

		if ( $quantity < 1 || $quantity > self::MAX_ITEM_QTY ) {
			return new WP_Error( 'snaporder_invalid_quantity', __( 'A product quantity is invalid.', 'lineweb-restaurant-orders' ) );
		}

		$product = call_user_func( $this->product_loader, $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		if ( ! is_array( $product ) || ! isset( $product['price'], $product['title'] ) ) {
			return new WP_Error( 'snaporder_invalid_product', __( 'A selected product is unavailable.', 'lineweb-restaurant-orders' ) );
		}

		$unit_cents = self::money_to_cents( $product['price'] );
		if ( is_wp_error( $unit_cents ) ) {
			return new WP_Error( 'snaporder_invalid_catalog_price', __( 'A selected product has an invalid price.', 'lineweb-restaurant-orders' ) );
		}

		$canonical_variant = null;
		if ( array_key_exists( 'variant_index', $raw_item ) && '' !== $raw_item['variant_index'] && null !== $raw_item['variant_index'] ) {
			$variant_index = filter_var( $raw_item['variant_index'], FILTER_VALIDATE_INT );
			$variants      = isset( $product['variants'] ) && is_array( $product['variants'] ) ? array_values( $product['variants'] ) : array();

			if (
				false === $variant_index ||
				$variant_index < 0 ||
				! isset( $variants[ $variant_index ] ) ||
				! is_array( $variants[ $variant_index ] ) ||
				! isset( $variants[ $variant_index ]['name'], $variants[ $variant_index ]['price'] )
			) {
				return new WP_Error( 'snaporder_invalid_variant', __( 'The selected product variant is unavailable.', 'lineweb-restaurant-orders' ) );
			}

			$variant_cents = self::money_to_cents( $variants[ $variant_index ]['price'] );
			if ( is_wp_error( $variant_cents ) ) {
				return new WP_Error( 'snaporder_invalid_catalog_price', __( 'A selected product variant has an invalid price.', 'lineweb-restaurant-orders' ) );
			}

			$unit_cents       += $variant_cents;
			$canonical_variant = array(
				'index' => $variant_index,
				'name'  => (string) $variants[ $variant_index ]['name'],
				'price' => self::cents_to_money( $variant_cents ),
			);
		}

		$canonical_extras = array();
		$raw_extras       = isset( $raw_item['extras'] ) && is_array( $raw_item['extras'] ) ? $raw_item['extras'] : array();
		$catalog_extras   = isset( $product['extras'] ) && is_array( $product['extras'] ) ? array_values( $product['extras'] ) : array();

		if ( count( $raw_extras ) > count( $catalog_extras ) ) {
			return new WP_Error( 'snaporder_invalid_extra', __( 'A selected product extra is unavailable.', 'lineweb-restaurant-orders' ) );
		}

		$seen_extra_indexes = array();
		foreach ( $raw_extras as $raw_extra ) {
			if ( ! is_array( $raw_extra ) || ! array_key_exists( 'index', $raw_extra ) ) {
				return new WP_Error( 'snaporder_invalid_extra', __( 'A selected product extra is unavailable.', 'lineweb-restaurant-orders' ) );
			}

			$extra_index = filter_var( $raw_extra['index'], FILTER_VALIDATE_INT );
			$extra_qty   = isset( $raw_extra['qty'] ) ? (int) $raw_extra['qty'] : 0;

			if (
				false === $extra_index ||
				$extra_index < 0 ||
				! isset( $catalog_extras[ $extra_index ] ) ||
				! is_array( $catalog_extras[ $extra_index ] ) ||
				! isset( $catalog_extras[ $extra_index ]['name'], $catalog_extras[ $extra_index ]['price'] ) ||
				isset( $seen_extra_indexes[ $extra_index ] ) ||
				$extra_qty < 1 ||
				$extra_qty > self::MAX_EXTRA_QTY
			) {
				return new WP_Error( 'snaporder_invalid_extra', __( 'A selected product extra is unavailable.', 'lineweb-restaurant-orders' ) );
			}

			$extra_cents = self::money_to_cents( $catalog_extras[ $extra_index ]['price'] );
			if ( is_wp_error( $extra_cents ) ) {
				return new WP_Error( 'snaporder_invalid_catalog_price', __( 'A selected product extra has an invalid price.', 'lineweb-restaurant-orders' ) );
			}

			$seen_extra_indexes[ $extra_index ] = true;
			$unit_cents                        += $extra_cents * $extra_qty;
			$canonical_extras[]                 = array(
				'index' => $extra_index,
				'name'  => (string) $catalog_extras[ $extra_index ]['name'],
				'price' => self::cents_to_money( $extra_cents ),
				'qty'   => $extra_qty,
			);
		}

		if ( $unit_cents <= 0 || $unit_cents > self::MAX_TOTAL_CENTS / $quantity ) {
			return new WP_Error( 'snaporder_invalid_total', __( 'A cart item has an invalid total.', 'lineweb-restaurant-orders' ) );
		}

		$notes = isset( $raw_item['notes'] ) ? sanitize_textarea_field( $raw_item['notes'] ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$notes = mb_substr( $notes, 0, 300 );
		} else {
			$notes = substr( $notes, 0, 300 );
		}

		return array(
			'id'               => (int) $product['id'],
			'title'            => (string) $product['title'],
			'image'            => isset( $product['image'] ) ? (string) $product['image'] : '',
			'price'            => self::cents_to_money( $unit_cents ),
			'unit_price_cents' => $unit_cents,
			'line_total'       => self::cents_to_money( $unit_cents * $quantity ),
			'line_total_cents' => $unit_cents * $quantity,
			'qty'              => $quantity,
			'extras'           => $canonical_extras,
			'variant'          => $canonical_variant,
			'notes'            => $notes,
		);
	}

	/**
	 * Load one product from WordPress.
	 *
	 * @param int $product_id Product post ID.
	 * @return array|WP_Error
	 */
	private function load_product( $product_id ) {
		$product = get_post( $product_id );

		if ( ! $product || 'snaporder_item' !== $product->post_type || 'publish' !== $product->post_status ) {
			return new WP_Error( 'snaporder_invalid_product', __( 'A selected product is unavailable.', 'lineweb-restaurant-orders' ) );
		}

		$image    = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
		$variants = get_post_meta( $product_id, '_snaporder_size', true );
		$extras   = get_post_meta( $product_id, '_snaporder_extras', true );

		return array(
			'id'       => $product_id,
			'title'    => get_the_title( $product_id ),
			'image'    => $image ? $image : '',
			'price'    => get_post_meta( $product_id, '_snaporder_price', true ),
			'variants' => is_array( $variants ) ? $variants : array(),
			'extras'   => is_array( $extras ) ? $extras : array(),
		);
	}

	/**
	 * Parse a human decimal amount into integer minor units.
	 *
	 * @param mixed $value Decimal amount.
	 * @return int|WP_Error
	 */
	public static function money_to_cents( $value ) {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		} elseif ( is_float( $value ) ) {
			$value = number_format( $value, 2, '.', '' );
		} elseif ( ! is_string( $value ) ) {
			return new WP_Error( 'snaporder_invalid_money', __( 'A monetary value is invalid.', 'lineweb-restaurant-orders' ) );
		}

		$value = trim( str_replace( array( "\xc2\xa0", ' ' ), '', $value ) );

		if ( false !== strpos( $value, ',' ) && false !== strpos( $value, '.' ) ) {
			if ( strrpos( $value, ',' ) > strrpos( $value, '.' ) ) {
				$value = str_replace( '.', '', $value );
				$value = str_replace( ',', '.', $value );
			} else {
				$value = str_replace( ',', '', $value );
			}
		} else {
			$value = str_replace( ',', '.', $value );
		}

		if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) ) {
			return new WP_Error( 'snaporder_invalid_money', __( 'A monetary value is invalid.', 'lineweb-restaurant-orders' ) );
		}

		$parts   = explode( '.', $value, 2 );
		$whole   = ltrim( $parts[0], '0' );
		$whole   = '' === $whole ? '0' : $whole;
		$decimal = isset( $parts[1] ) ? str_pad( $parts[1], 2, '0' ) : '00';

		if ( strlen( $whole ) > 7 ) {
			return new WP_Error( 'snaporder_invalid_money', __( 'A monetary value is too large.', 'lineweb-restaurant-orders' ) );
		}

		$cents = ( (int) $whole * 100 ) + (int) $decimal;
		if ( $cents > self::MAX_TOTAL_CENTS ) {
			return new WP_Error( 'snaporder_invalid_money', __( 'A monetary value is too large.', 'lineweb-restaurant-orders' ) );
		}

		return $cents;
	}

	/**
	 * Format integer minor units for storage and display calculations.
	 *
	 * @param int $cents Amount in minor units.
	 * @return string
	 */
	public static function cents_to_money( $cents ) {
		return number_format( (int) $cents / 100, 2, '.', '' );
	}
}
