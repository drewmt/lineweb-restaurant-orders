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
			'name'                  => _x( 'Food Items', 'Post Type General Name', 'lineweb-restaurant-orders' ),
			'singular_name'         => _x( 'Food Item', 'Post Type Singular Name', 'lineweb-restaurant-orders' ),
			'menu_name'             => __( 'Lineweb Orders', 'lineweb-restaurant-orders' ),
			'name_admin_bar'        => __( 'Food Item', 'lineweb-restaurant-orders' ),
			'archives'              => __( 'Item Archives', 'lineweb-restaurant-orders' ),
			'attributes'            => __( 'Item Attributes', 'lineweb-restaurant-orders' ),
			'parent_item_colon'     => __( 'Parent Item:', 'lineweb-restaurant-orders' ),
			'all_items'             => __( 'All Items', 'lineweb-restaurant-orders' ),
			'add_new_item'          => __( 'Add New Item', 'lineweb-restaurant-orders' ),
			'add_new'               => __( 'Add New', 'lineweb-restaurant-orders' ),
			'new_item'              => __( 'New Item', 'lineweb-restaurant-orders' ),
			'edit_item'             => __( 'Edit Item', 'lineweb-restaurant-orders' ),
			'update_item'           => __( 'Update Item', 'lineweb-restaurant-orders' ),
			'view_item'             => __( 'View Item', 'lineweb-restaurant-orders' ),
			'view_items'            => __( 'View Items', 'lineweb-restaurant-orders' ),
			'search_items'          => __( 'Search Item', 'lineweb-restaurant-orders' ),
			'not_found'             => __( 'Not found', 'lineweb-restaurant-orders' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'lineweb-restaurant-orders' ),
			'featured_image'        => __( 'Featured Image', 'lineweb-restaurant-orders' ),
			'set_featured_image'    => __( 'Set featured image', 'lineweb-restaurant-orders' ),
			'remove_featured_image' => __( 'Remove featured image', 'lineweb-restaurant-orders' ),
			'use_featured_image'    => __( 'Use as featured image', 'lineweb-restaurant-orders' ),
			'insert_into_item'      => __( 'Insert into item', 'lineweb-restaurant-orders' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'lineweb-restaurant-orders' ),
			'items_list'            => __( 'Items list', 'lineweb-restaurant-orders' ),
			'items_list_navigation' => __( 'Items list navigation', 'lineweb-restaurant-orders' ),
			'filter_items_list'     => __( 'Filter items list', 'lineweb-restaurant-orders' ),
		);
		$args   = array(
			'label'               => __( 'Food Item', 'lineweb-restaurant-orders' ),
			'description'         => __( 'Food Menu Items', 'lineweb-restaurant-orders' ),
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
			'name'                       => _x( 'Food Categories', 'Taxonomy General Name', 'lineweb-restaurant-orders' ),
			'singular_name'              => _x( 'Food Category', 'Taxonomy Singular Name', 'lineweb-restaurant-orders' ),
			'menu_name'                  => __( 'Categories', 'lineweb-restaurant-orders' ),
			'all_items'                  => __( 'All Categories', 'lineweb-restaurant-orders' ),
			'parent_item'                => __( 'Parent Category', 'lineweb-restaurant-orders' ),
			'parent_item_colon'          => __( 'Parent Category:', 'lineweb-restaurant-orders' ),
			'new_item_name'              => __( 'New Category Name', 'lineweb-restaurant-orders' ),
			'add_new_item'               => __( 'Add New Category', 'lineweb-restaurant-orders' ),
			'edit_item'                  => __( 'Edit Category', 'lineweb-restaurant-orders' ),
			'update_item'                => __( 'Update Category', 'lineweb-restaurant-orders' ),
			'view_item'                  => __( 'View Category', 'lineweb-restaurant-orders' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'lineweb-restaurant-orders' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'lineweb-restaurant-orders' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'lineweb-restaurant-orders' ),
			'popular_items'              => __( 'Popular Categories', 'lineweb-restaurant-orders' ),
			'search_items'               => __( 'Search Categories', 'lineweb-restaurant-orders' ),
			'not_found'                  => __( 'Not Found', 'lineweb-restaurant-orders' ),
			'no_terms'                   => __( 'No categories', 'lineweb-restaurant-orders' ),
			'items_list'                 => __( 'Categories list', 'lineweb-restaurant-orders' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'lineweb-restaurant-orders' ),
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
