<?php
/**
 * Lightweight bootstrap for framework-independent unit tests.
 *
 * @package SnapOrder
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $message ) {
		return $message;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		$value = strip_tags( (string) $value );
		return trim( preg_replace( "/[\r\n]+/", "\n", $value ) );
	}
}

require dirname( __DIR__ ) . '/includes/class-snaporder-order-calculator.php';
require dirname( __DIR__ ) . '/includes/class-snaporder-stripe-gateway.php';
require dirname( __DIR__ ) . '/includes/class-snaporder-settings.php';
