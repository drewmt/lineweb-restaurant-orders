<?php
/**
 * Plugin Name: Lineweb Restaurant Orders
 * Plugin URI:  https://github.com/drewmt/lineweb-restaurant-orders
 * Description: Transform WordPress into a mobile-first food ordering app with card and cash payments, order tracking, WhatsApp notifications, and PWA support.
 * Version:     1.0.0
 * Author:      Andrew Matia / Lineweb
 * Author URI:  https://www.lineweb.gr/
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lineweb-restaurant-orders
 * Copyright:   2026 Andrew Matia / Lineweb
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Tested up to:      7.0
 *
 * @package SnapOrder
 * @author  Andrew Matia / Lineweb
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SNAPORDER_VERSION', '1.0.0' );
define( 'SNAPORDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNAPORDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SNAPORDER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder.php';
require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-lifecycle.php';

add_action( 'plugins_loaded', array( 'SnapOrder', 'get_instance' ) );
register_activation_hook( __FILE__, array( 'SnapOrder_Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SnapOrder_Lifecycle', 'deactivate' ) );
