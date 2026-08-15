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
	 * Runs pending upgrades before the plugin registers its runtime hooks.
	 */
	public static function maybe_upgrade() {
		if ( SNAPORDER_VERSION !== get_option( 'snaporder_version' ) ) {
			self::upgrade();
		}
	}

	/**
	 * Creates required storage and safe first-run defaults.
	 */
	public static function activate() {
		self::upgrade();
		flush_rewrite_rules();
	}

	/**
	 * Migrates pre-directory installations and prepares current storage.
	 */
	private static function upgrade() {
		self::migrate_legacy_data();

		require_once SNAPORDER_PLUGIN_DIR . 'includes/class-snaporder-statistics.php';

		$statistics = new SnapOrder_Statistics();
		$statistics->create_stats_table();

		if ( '1' !== get_option( 'snaporder_first_activation' ) ) {
			update_option( 'snaporder_store_title', 'My Restaurant' );
			update_option( 'snaporder_store_tagline', 'Delicious food delivered to your door.' );
			update_option( 'snaporder_primary_color', '#FF6B35' );
			update_option( 'snaporder_enable_cod', '1' );
			update_option( 'snaporder_order_retention_days', 0 );
			update_option( 'snaporder_first_activation', '1' );
		}

		update_option( 'snaporder_version', SNAPORDER_VERSION );
	}

	/**
	 * Migrates the public GitHub 1.0.0 identifiers to the directory-safe prefix.
	 *
	 * Legacy identifiers are read only during this one-time migration. New data
	 * is always registered and stored with the distinctive snaporder prefix.
	 */
	private static function migrate_legacy_data() {
		global $wpdb;

		$legacy_options = array(
			'brand_logo',
			'currency',
			'delete_data_on_uninstall',
			'dinein_enabled',
			'enable_cod',
			'enable_stripe',
			'facebook_url',
			'first_activation',
			'instagram_url',
			'opening_hours',
			'order_retention_days',
			'primary_color',
			'pwa_enabled',
			'pwa_name',
			'pwa_short_name',
			'pwa_theme_color',
			'store_tagline',
			'store_title',
			'stripe_publishable_key',
			'stripe_secret_key',
			'stripe_webhook_secret',
			'tiktok_url',
			'tipping_enabled',
			'twilio_phone',
			'twilio_sid',
			'twilio_token',
			'twitter_url',
			'whatsapp_enabled',
		);

		foreach ( $legacy_options as $option_suffix ) {
			$legacy_key  = 'mfm_' . $option_suffix;
			$current_key = 'snaporder_' . $option_suffix;
			$value       = get_option( $legacy_key, null );

			if ( null !== $value && false === get_option( $current_key, false ) ) {
				update_option( $current_key, $value );
			}

			if ( null !== $value && get_option( $current_key, null ) === $value ) {
				delete_option( $legacy_key );
			}
		}

		$post_type_map = array(
			'food_item'  => 'snaporder_item',
			'mfm_order'  => 'snaporder_order',
			'mfm_banner' => 'snaporder_banner',
		);
		foreach ( $post_type_map as $legacy_type => $current_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time identifier migration before runtime caches are populated.
			$wpdb->update( $wpdb->posts, array( 'post_type' => $current_type ), array( 'post_type' => $legacy_type ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time taxonomy identifier migration.
		$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => 'snaporder_category' ), array( 'taxonomy' => 'food_category' ) );

		$legacy_meta_suffixes = array(
			'address',
			'allergens',
			'banner_button_link',
			'banner_button_text',
			'banner_subtitle',
			'calories',
			'cart_items',
			'city',
			'custom_image_url',
			'customer_name',
			'customer_phone',
			'delivery_type',
			'dietary',
			'extras',
			'featured',
			'house_number',
			'ingredients',
			'order_notes',
			'order_status',
			'order_token',
			'order_total',
			'payment_method',
			'payment_status',
			'price',
			'size',
			'street',
			'table_number',
			'tip_amount',
			'transaction_id',
			'zip',
		);
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time key migration, not a frontend query.
		foreach ( $legacy_meta_suffixes as $meta_suffix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time metadata key migration.
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_key' => '_snaporder_' . $meta_suffix ),
				array( 'meta_key' => '_mfm_' . $meta_suffix )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time sorting metadata migration.
		$wpdb->update( $wpdb->termmeta, array( 'meta_key' => '_snaporder_order' ), array( 'meta_key' => '_mfm_order' ) );
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-time page-template migration, not a frontend query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time page-template identifier migration.
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => 'snaporder-app-view.php' ),
			array(
				'meta_key'   => '_wp_page_template',
				'meta_value' => 'mfm-app-view.php',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		$legacy_shortcode  = '[modern_food_menu';
		$current_shortcode = '[snaporder_restaurant_menu';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded replacement of the legacy shortcode token only.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
				$legacy_shortcode,
				$current_shortcode,
				'%' . $wpdb->esc_like( $legacy_shortcode ) . '%'
			)
		);

		$legacy_closing_shortcode  = '[/modern_food_menu]';
		$current_closing_shortcode = '[/snaporder_restaurant_menu]';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded replacement of the legacy closing shortcode token only.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
				$legacy_closing_shortcode,
				$current_closing_shortcode,
				'%' . $wpdb->esc_like( $legacy_closing_shortcode ) . '%'
			)
		);

		self::migrate_legacy_stats_table();
	}

	/**
	 * Preserves aggregate menu-view counts from GitHub release 1.0.0.
	 */
	private static function migrate_legacy_stats_table() {
		global $wpdb;

		$legacy_table  = $wpdb->prefix . 'mfm_stats';
		$current_table = $wpdb->prefix . 'snaporder_stats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time table existence checks.
		$legacy_exists = $legacy_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
		if ( ! $legacy_exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time table existence checks.
		$current_exists = $current_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $current_table ) ) );
		if ( ! $current_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Preserves the existing aggregate table without copying data.
			$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $legacy_table, $current_table ) );
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Merge is required only if both aggregate tables exist.
		$merged = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (item_id, view_date, view_count)
				 SELECT item_id, view_date, view_count FROM %i
				 ON DUPLICATE KEY UPDATE view_count = GREATEST(%i.view_count, VALUES(view_count))',
				$current_table,
				$legacy_table,
				$current_table
			)
		);
		if ( false !== $merged ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove only after a successful merge.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $legacy_table ) );
		}
	}

	/**
	 * Clears plugin schedules and rewrite rules.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'snaporder_privacy_cleanup' );
		flush_rewrite_rules();
	}
}
