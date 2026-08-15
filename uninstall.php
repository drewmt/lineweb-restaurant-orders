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

if ( '1' !== get_option( 'snaporder_delete_data_on_uninstall' ) ) {
	return;
}

wp_clear_scheduled_hook( 'snaporder_privacy_cleanup' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit administrator-approved uninstall cleanup.
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}snaporder_stats`" );

$snaporder_option_keys = array(
	'snaporder_store_title',
	'snaporder_store_tagline',
	'snaporder_primary_color',
	'snaporder_currency',
	'snaporder_facebook_url',
	'snaporder_instagram_url',
	'snaporder_twitter_url',
	'snaporder_tiktok_url',
	'snaporder_brand_logo',
	'snaporder_opening_hours',
	'snaporder_enable_stripe',
	'snaporder_stripe_publishable_key',
	'snaporder_stripe_secret_key',
	'snaporder_stripe_webhook_secret',
	'snaporder_enable_cod',
	'snaporder_tipping_enabled',
	'snaporder_whatsapp_enabled',
	'snaporder_twilio_sid',
	'snaporder_twilio_token',
	'snaporder_twilio_phone',
	'snaporder_sound_enabled',
	'snaporder_pwa_enabled',
	'snaporder_pwa_name',
	'snaporder_pwa_short_name',
	'snaporder_pwa_theme_color',
	'snaporder_dinein_enabled',
	'snaporder_order_retention_days',
	'snaporder_delete_data_on_uninstall',
	'snaporder_first_activation',
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

foreach ( array( 'snaporder_item', 'snaporder_order', 'snaporder_banner' ) as $snaporder_post_type ) {
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
		'taxonomy'   => 'snaporder_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
if ( ! is_wp_error( $snaporder_term_ids ) ) {
	foreach ( $snaporder_term_ids as $snaporder_term_id ) {
		wp_delete_term( $snaporder_term_id, 'snaporder_category' );
	}
}
