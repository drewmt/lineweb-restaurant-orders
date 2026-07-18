<?php
/**
 * Optional SnapOrder data removal.
 *
 * Data is preserved unless an administrator explicitly enables the
 * "Permanently delete data" setting before deleting the plugin.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( '1' !== get_option( 'mfm_delete_data_on_uninstall' ) ) {
	return;
}

wp_clear_scheduled_hook( 'snaporder_privacy_cleanup' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit administrator-approved uninstall cleanup.
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mfm_stats`" );

$snaporder_option_keys = array(
	'mfm_store_title',
	'mfm_store_tagline',
	'mfm_primary_color',
	'mfm_currency',
	'mfm_facebook_url',
	'mfm_instagram_url',
	'mfm_twitter_url',
	'mfm_tiktok_url',
	'mfm_brand_logo',
	'mfm_opening_hours',
	'mfm_enable_stripe',
	'mfm_stripe_publishable_key',
	'mfm_stripe_secret_key',
	'mfm_stripe_webhook_secret',
	'mfm_enable_cod',
	'mfm_tipping_enabled',
	'mfm_whatsapp_enabled',
	'mfm_twilio_sid',
	'mfm_twilio_token',
	'mfm_twilio_phone',
	'mfm_sound_enabled',
	'mfm_pwa_enabled',
	'mfm_pwa_name',
	'mfm_pwa_short_name',
	'mfm_pwa_theme_color',
	'mfm_dinein_enabled',
	'mfm_order_retention_days',
	'mfm_delete_data_on_uninstall',
	'mfm_first_activation',
	'snaporder_version',
);

foreach ( $snaporder_option_keys as $snaporder_option_key ) {
	delete_option( $snaporder_option_key );
}

// Remove short-lived idempotency locks left by interrupted orders.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup requires discovering dynamic option names.
$snaporder_request_locks = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'snaporder_request_' ) . '%' ) );
foreach ( $snaporder_request_locks as $snaporder_request_lock ) {
	delete_option( $snaporder_request_lock );
}

foreach ( array( 'food_item', 'mfm_order', 'mfm_banner' ) as $snaporder_post_type ) {
	do {
		$snaporder_post_ids = get_posts(
			array(
				'post_type'              => $snaporder_post_type,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => 100,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $snaporder_post_ids as $snaporder_post_id ) {
			wp_delete_post( $snaporder_post_id, true );
		}
	} while ( ! empty( $snaporder_post_ids ) );
}

$snaporder_term_ids = get_terms(
	array(
		'taxonomy'   => 'food_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
if ( ! is_wp_error( $snaporder_term_ids ) ) {
	foreach ( $snaporder_term_ids as $snaporder_term_id ) {
		wp_delete_term( $snaporder_term_id, 'food_category' );
	}
}
