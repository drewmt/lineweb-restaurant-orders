<?php
/**
 * Legacy identifier migration tests.
 *
 * @package SnapOrder
 */

class SnapOrder_Lifecycle_Migration_Test extends WP_UnitTestCase {

	/**
	 * Confirms that public registrations use only the distinctive prefix.
	 */
	public function test_registers_only_directory_safe_identifiers() {
		$this->assertTrue( post_type_exists( 'snaporder_item' ) );
		$this->assertTrue( post_type_exists( 'snaporder_order' ) );
		$this->assertTrue( post_type_exists( 'snaporder_banner' ) );
		$this->assertTrue( taxonomy_exists( 'snaporder_category' ) );
		$this->assertTrue( shortcode_exists( 'snaporder_restaurant_menu' ) );
		$this->assertFalse( post_type_exists( 'food_item' ) );
		$this->assertFalse( post_type_exists( 'mfm_order' ) );
		$this->assertFalse( post_type_exists( 'mfm_banner' ) );
		$this->assertFalse( taxonomy_exists( 'food_category' ) );
		$this->assertFalse( shortcode_exists( 'modern_food_menu' ) );
	}

	/**
	 * Restores the current version marker after each test.
	 */
	public function tear_down() {
		update_option( 'snaporder_version', SNAPORDER_VERSION );
		parent::tear_down();
	}

	/**
	 * Preserves existing 1.0.0 settings, content, taxonomy, and metadata.
	 */
	public function test_migrates_legacy_github_release_data() {
		update_option( 'mfm_store_title', 'Legacy Restaurant' );
		delete_option( 'snaporder_store_title' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'food_item',
				'post_status'  => 'publish',
				'post_content' => '[modern_food_menu category="lunch"][/modern_food_menu]',
			)
		);
		update_post_meta( $post_id, '_mfm_price', '12.50' );
		update_post_meta( $post_id, '_wp_page_template', 'mfm-app-view.php' );

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Legacy category',
			)
		);
		global $wpdb;
		$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => 'food_category' ), array( 'term_id' => $term_id ) );
		update_term_meta( $term_id, '_mfm_order', 4 );

		update_option( 'snaporder_version', '1.0.0' );
		SnapOrder_Lifecycle::maybe_upgrade();
		clean_post_cache( $post_id );
		clean_term_cache( $term_id );

		$this->assertSame( 'Legacy Restaurant', get_option( 'snaporder_store_title' ) );
		$this->assertFalse( get_option( 'mfm_store_title', false ) );
		$this->assertSame( 'snaporder_item', get_post_type( $post_id ) );
		$this->assertSame( '12.50', get_post_meta( $post_id, '_snaporder_price', true ) );
		$this->assertSame( 'snaporder-app-view.php', get_post_meta( $post_id, '_wp_page_template', true ) );
		$this->assertSame( '[snaporder_restaurant_menu category="lunch"][/snaporder_restaurant_menu]', get_post_field( 'post_content', $post_id ) );
		$this->assertSame( 'snaporder_category', get_term( $term_id )->taxonomy );
		$this->assertSame( '4', get_term_meta( $term_id, '_snaporder_order', true ) );
		$this->assertSame( SNAPORDER_VERSION, get_option( 'snaporder_version' ) );
	}
}
