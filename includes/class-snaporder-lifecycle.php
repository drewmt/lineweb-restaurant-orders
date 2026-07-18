<?php
/**
 * SnapOrder activation and deactivation routines.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin lifecycle operations.
 */
final class SnapOrder_Lifecycle {

	/**
	 * Creates required storage and safe first-run defaults.
	 */
	public static function activate() {
		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-statistics.php';

		$statistics = new SnapOrder_Statistics();
		$statistics->create_stats_table();

		if ( '1' !== get_option( 'mfm_first_activation' ) ) {
			update_option( 'mfm_store_title', 'SnapOrder Restaurant' );
			update_option( 'mfm_store_tagline', 'Delicious food delivered to your door.' );
			update_option( 'mfm_primary_color', '#FF6B35' );
			update_option( 'mfm_enable_cod', '1' );
			update_option( 'mfm_order_retention_days', 0 );
			update_option( 'mfm_first_activation', '1' );
		}

		update_option( 'snaporder_version', SNAPORDER_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Clears plugin schedules and rewrite rules.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'snaporder_privacy_cleanup' );
		flush_rewrite_rules();
	}
}
