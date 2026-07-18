<?php
/**
 * Template Loader for Food Menu App.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the app template and supports safe theme overrides.
 */
class SnapOrder_Template_Loader {


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'theme_page_templates', array( $this, 'add_page_template' ), 20, 1 );
		add_filter( 'theme_post_templates', array( $this, 'add_page_template' ), 20, 1 );
		add_filter( 'template_include', array( $this, 'load_page_template' ) );
	}

	/**
	 * Add "Food Menu App" to page templates list.
	 *
	 * @param array $templates Existing templates.
	 * @return array Modified templates.
	 */
	public function add_page_template( $templates ) {
		$templates['mfm-app-view.php'] = __( 'Food Menu App View', 'lineweb-restaurant-orders' );
		return $templates;
	}

	/**
	 * Load the template file.
	 *
	 * @param string $template Current template path.
	 * @return string Modified template path.
	 */
	public function load_page_template( $template ) {
		if ( is_page() ) {
			$meta = get_post_meta( get_the_ID(), '_wp_page_template', true );
			if ( 'mfm-app-view.php' === $meta ) {
				$template_name = 'app-view.php';

				// Check the child theme.
				$file = get_stylesheet_directory() . '/snaporder/' . $template_name;
				if ( file_exists( $file ) ) {
					return $file;
				}

				// Check the parent theme.
				$file = get_template_directory() . '/snaporder/' . $template_name;
				if ( file_exists( $file ) ) {
					return $file;
				}

				// Fall back to the bundled template.
				$file = SNAPORDER_PLUGIN_DIR . 'templates/' . $template_name;
				if ( file_exists( $file ) ) {
					return $file;
				}
			}
		}
		return $template;
	}
}
