<?php
/**
 * Drag-and-drop ordering for SnapOrder content.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds scoped drag-and-drop ordering to products, banners, and categories.
 */
class SnapOrder_Admin_Sorting {

	/**
	 * Registers sorting hooks.
	 */
	public function __construct() {
		// Enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Register the AJAX handler.
		add_action( 'wp_ajax_mfm_update_order', array( $this, 'update_order' ) );

		// Add columns to sortable post types.
		foreach ( array( 'food_item', 'mfm_banner' ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		// Add ordering to the food category taxonomy.
		add_filter( 'manage_edit-food_category_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_food_category_custom_column', array( $this, 'render_term_column' ), 10, 3 );
		add_action( 'pre_get_terms', array( $this, 'pre_get_terms' ) );
	}

	/**
	 * Enqueues sorting assets on supported list screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		unset( $hook );
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$is_food_item = 'food_item' === $screen->post_type && 'edit' === $screen->base;
		$is_banner    = 'mfm_banner' === $screen->post_type && 'edit' === $screen->base;
		$is_category  = 'food_category' === $screen->taxonomy && 'edit-tags' === $screen->base;

		if ( $is_food_item || $is_banner || $is_category ) {
			wp_enqueue_style( 'mfm-admin-sorting', SNAPORDER_PLUGIN_URL . 'assets/css/admin-sorting.css', array(), '1.0.0' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'mfm-admin-sorting', SNAPORDER_PLUGIN_URL . 'assets/js/admin-sorting.js', array( 'jquery', 'jquery-ui-sortable' ), '1.0.0', true );

			wp_localize_script(
				'mfm-admin-sorting',
				'mfm_sorting',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'mfm_sorting_nonce' ),
					'type'     => $is_category ? 'category' : 'post',
				)
			);
		}
	}

	/**
	 * Adds the drag-handle column.
	 *
	 * @param array $columns Existing list-table columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			if ( 'cb' === $key ) {
				$new_columns[ $key ]     = $value;
				$new_columns['mfm_sort'] = '<span class="dashicons dashicons-menu"></span>';
			} else {
				$new_columns[ $key ] = $value;
			}
		}
		return $new_columns;
	}

	/**
	 * Renders the post drag handle.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		unset( $post_id );
		if ( 'mfm_sort' === $column ) {
			echo '<span class="mfm-drag-handle dashicons dashicons-menu"></span>';
		}
	}

	/**
	 * Renders the taxonomy drag handle.
	 *
	 * @param string $content     Existing cell content.
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	public function render_term_column( $content, $column_name, $term_id ) {
		unset( $term_id );
		if ( 'mfm_sort' === $column_name ) {
			return '<span class="mfm-drag-handle dashicons dashicons-menu"></span>';
		}
		return $content;
	}

	/**
	 * Orders supported post list screens by menu order.
	 *
	 * @param WP_Query $query Current post query.
	 */
	public function pre_get_posts( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if (
			( 'edit-food_item' === $screen->id && 'food_item' === $query->get( 'post_type' ) ) ||
			( 'edit-mfm_banner' === $screen->id && 'mfm_banner' === $query->get( 'post_type' ) )
		) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Orders the food category list by saved term order.
	 *
	 * @param WP_Term_Query $query Current term query.
	 */
	public function pre_get_terms( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit-food_category' !== $screen->id ) {
			return;
		}

		$taxonomies = isset( $query->query_vars['taxonomy'] ) ? $query->query_vars['taxonomy'] : '';

		if ( 'food_category' === $taxonomies || ( is_array( $taxonomies ) && in_array( 'food_category', $taxonomies, true ) ) ) {
			// Include both ordered terms and terms that have not been ordered yet.
			$meta_query = array(
				'relation'    => 'OR',
				'ordered'     => array(
					'key'     => '_mfm_order',
					'compare' => 'EXISTS',
					'type'    => 'NUMERIC',
				),
				'not_ordered' => array(
					'key'     => '_mfm_order',
					'compare' => 'NOT EXISTS',
				),
			);

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Both ordered and new terms must remain visible.
			$query->query_vars['meta_query'] = $meta_query;
			$query->query_vars['orderby']    = 'meta_value_num';
			$query->query_vars['order']      = 'ASC';
		}
	}

	/**
	 * Persists a validated drag-and-drop order.
	 */
	public function update_order() {
		check_ajax_referer( 'mfm_sorting_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'snaporder' ), 403 );
		}

		$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'absint', wp_unslash( $_POST['order'] ) ) : array();
		$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';

		if ( empty( $order ) || ! in_array( $type, array( 'category', 'post' ), true ) ) {
			wp_send_json_error( __( 'Invalid sorting data.', 'snaporder' ), 400 );
		}

		foreach ( $order as $index => $id ) {
			$id = intval( $id );

			if ( 'category' === $type ) {
				$term = get_term( $id, 'food_category' );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				update_term_meta( $id, '_mfm_order', $index );
			} else {
				if ( ! in_array( get_post_type( $id ), array( 'food_item', 'mfm_banner' ), true ) || ! current_user_can( 'edit_post', $id ) ) {
					continue;
				}
				$post_data = array(
					'ID'         => $id,
					'menu_order' => $index,
				);
				wp_update_post( $post_data );
			}
		}

		wp_send_json_success( __( 'Order updated.', 'snaporder' ) );
	}
}
