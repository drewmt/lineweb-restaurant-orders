<?php
/**
 * WordPress test-suite bootstrap.
 *
 * @package SnapOrder
 */

$snaporder_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $snaporder_tests_dir ) {
	$snaporder_tests_dir = '/wordpress-phpunit';
}

require_once $snaporder_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/snaporder.php';
	}
);

require $snaporder_tests_dir . '/includes/bootstrap.php';

