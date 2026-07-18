<?php
/**
 * Register Custom Post Type and Taxonomies.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers food products and their category taxonomy.
 */
class SnapOrder_Post_Type {


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register Food Item Post Type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Food Items', 'Post Type General Name', 'snaporder' ),
			'singular_name'         => _x( 'Food Item', 'Post Type Singular Name', 'snaporder' ),
			'menu_name'             => __( 'SnapOrder', 'snaporder' ),
			'name_admin_bar'        => __( 'Food Item', 'snaporder' ),
			'archives'              => __( 'Item Archives', 'snaporder' ),
			'attributes'            => __( 'Item Attributes', 'snaporder' ),
			'parent_item_colon'     => __( 'Parent Item:', 'snaporder' ),
			'all_items'             => __( 'All Items', 'snaporder' ),
			'add_new_item'          => __( 'Add New Item', 'snaporder' ),
			'add_new'               => __( 'Add New', 'snaporder' ),
			'new_item'              => __( 'New Item', 'snaporder' ),
			'edit_item'             => __( 'Edit Item', 'snaporder' ),
			'update_item'           => __( 'Update Item', 'snaporder' ),
			'view_item'             => __( 'View Item', 'snaporder' ),
			'view_items'            => __( 'View Items', 'snaporder' ),
			'search_items'          => __( 'Search Item', 'snaporder' ),
			'not_found'             => __( 'Not found', 'snaporder' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'snaporder' ),
			'featured_image'        => __( 'Featured Image', 'snaporder' ),
			'set_featured_image'    => __( 'Set featured image', 'snaporder' ),
			'remove_featured_image' => __( 'Remove featured image', 'snaporder' ),
			'use_featured_image'    => __( 'Use as featured image', 'snaporder' ),
			'insert_into_item'      => __( 'Insert into item', 'snaporder' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'snaporder' ),
			'items_list'            => __( 'Items list', 'snaporder' ),
			'items_list_navigation' => __( 'Items list navigation', 'snaporder' ),
			'filter_items_list'     => __( 'Filter items list', 'snaporder' ),
		);
		$args   = array(
			'label'               => __( 'Food Item', 'snaporder' ),
			'description'         => __( 'Food Menu Items', 'snaporder' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			'taxonomies'          => array( 'food_category' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-carrot',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
		);
		register_post_type( 'food_item', $args );
	}

	/**
	 * Register Taxonomies.
	 */
	public function register_taxonomies() {
		// Register the food category taxonomy.
		$labels_cat = array(
			'name'                       => _x( 'Food Categories', 'Taxonomy General Name', 'snaporder' ),
			'singular_name'              => _x( 'Food Category', 'Taxonomy Singular Name', 'snaporder' ),
			'menu_name'                  => __( 'Categories', 'snaporder' ),
			'all_items'                  => __( 'All Categories', 'snaporder' ),
			'parent_item'                => __( 'Parent Category', 'snaporder' ),
			'parent_item_colon'          => __( 'Parent Category:', 'snaporder' ),
			'new_item_name'              => __( 'New Category Name', 'snaporder' ),
			'add_new_item'               => __( 'Add New Category', 'snaporder' ),
			'edit_item'                  => __( 'Edit Category', 'snaporder' ),
			'update_item'                => __( 'Update Category', 'snaporder' ),
			'view_item'                  => __( 'View Category', 'snaporder' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'snaporder' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'snaporder' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'snaporder' ),
			'popular_items'              => __( 'Popular Categories', 'snaporder' ),
			'search_items'               => __( 'Search Categories', 'snaporder' ),
			'not_found'                  => __( 'Not Found', 'snaporder' ),
			'no_terms'                   => __( 'No categories', 'snaporder' ),
			'items_list'                 => __( 'Categories list', 'snaporder' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'snaporder' ),
		);
		$args_cat   = array(
			'labels'            => $labels_cat,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
		);
		register_taxonomy( 'food_category', array( 'food_item' ), $args_cat );
	}
}
