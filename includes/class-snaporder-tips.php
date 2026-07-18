<?php
/**
 * Tipping at checkout.
 * Settings are registered and rendered in SnapOrder_Settings (Payments tab).
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documents the tip feature boundary for the component loader.
 */
class SnapOrder_Tips {
	// Tip UI lives in the app template; authoritative validation lives in the order calculator.
}
