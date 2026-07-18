<?php
/**
 * Product variants and extras metabox.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages food-item variants and extras.
 */
class SnapOrder_Product_Options {

	/**
	 * Registers option-field hooks.
	 */
	public function __construct() {
		add_action( 'snaporder_product_options_metabox', array( $this, 'render_options_metabox' ) );
		add_action( 'snaporder_save_product_options', array( $this, 'save_options_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues option controls on food-item edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			$screen = get_current_screen();
			if ( $screen && 'food_item' === $screen->post_type ) {
				wp_enqueue_script(
					'mfm-product-options',
					SNAPORDER_PLUGIN_URL . 'assets/js/admin-product-options.js',
					array( 'jquery' ),
					SNAPORDER_VERSION,
					true
				);
			}
		}
	}

	/**
	 * Renders variant and extra fields.
	 *
	 * @param WP_Post $post Food item post.
	 */
	public function render_options_metabox( $post ) {
		$sizes  = get_post_meta( $post->ID, '_mfm_size', true );
		$extras = get_post_meta( $post->ID, '_mfm_extras', true );

		if ( ! is_array( $sizes ) ) {
			$sizes = array();
		}
		if ( ! is_array( $extras ) ) {
			$extras = array();
		}
		if ( empty( $sizes ) ) {
			$sizes[] = array(
				'name'  => '',
				'price' => '',
			);
		}
		if ( empty( $extras ) ) {
			$extras[] = array(
				'name'  => '',
				'price' => '',
			);
		}
		?>
		<div class="mfm-row">
			<label><?php esc_html_e( 'Variants', 'lineweb-restaurant-orders' ); ?></label>
			<div id="mfm-sizes-wrapper">
				<?php foreach ( $sizes as $index => $size ) : ?>
					<div class="mfm-size-item" style="margin-bottom:10px;display:flex;gap:10px;">
						<input type="text" name="mfm_size[<?php echo (int) $index; ?>][name]"
							value="<?php echo esc_attr( $size['name'] ); ?>"
							placeholder="<?php esc_attr_e( 'Variant Name (e.g. Large)', 'lineweb-restaurant-orders' ); ?>">
						<input type="text" name="mfm_size[<?php echo (int) $index; ?>][price]"
							value="<?php echo esc_attr( $size['price'] ); ?>"
							placeholder="<?php esc_attr_e( 'Price (+)', 'lineweb-restaurant-orders' ); ?>">
						<button type="button" class="button mfm-remove-size">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="mfm-add-size"><?php esc_html_e( 'Add Variant', 'lineweb-restaurant-orders' ); ?></button>
		</div>

		<div class="mfm-row">
			<label><?php esc_html_e( 'Extras', 'lineweb-restaurant-orders' ); ?></label>
			<div id="mfm-extras-wrapper">
				<?php foreach ( $extras as $index => $extra ) : ?>
					<div class="mfm-extra-item" style="margin-bottom:10px;display:flex;gap:10px;">
						<input type="text" name="mfm_extras[<?php echo (int) $index; ?>][name]"
							value="<?php echo esc_attr( $extra['name'] ); ?>"
							placeholder="<?php esc_attr_e( 'Extra Name (e.g. Extra Cheese)', 'lineweb-restaurant-orders' ); ?>">
						<input type="text" name="mfm_extras[<?php echo (int) $index; ?>][price]"
							value="<?php echo esc_attr( $extra['price'] ); ?>"
							placeholder="<?php esc_attr_e( 'Price (e.g. 1.50)', 'lineweb-restaurant-orders' ); ?>">
						<button type="button" class="button mfm-remove-extra">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="mfm-add-extra"><?php esc_html_e( 'Add Extra', 'lineweb-restaurant-orders' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Validates and saves variants and extras.
	 *
	 * @param int $post_id Food item post ID.
	 */
	public function save_options_metabox( $post_id ) {
		// The parent food-details save handler verifies the form nonce first.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$submitted_sizes = isset( $_POST['mfm_size'] ) && is_array( $_POST['mfm_size'] )
			? map_deep( wp_unslash( $_POST['mfm_size'] ), 'sanitize_text_field' )
			: array();
		if ( ! empty( $submitted_sizes ) ) {
			$sizes = array();
			foreach ( $submitted_sizes as $size ) {
				if ( is_array( $size ) && ! empty( $size['name'] ) ) {
					$price = $this->normalize_option_price( $size['price'] ?? '' );
					if ( null === $price ) {
						continue;
					}
					$sizes[] = array(
						'name'  => sanitize_text_field( $size['name'] ),
						'price' => $price,
					);
				}
			}
			update_post_meta( $post_id, '_mfm_size', $sizes );
		} else {
			delete_post_meta( $post_id, '_mfm_size' );
		}

		$submitted_extras = isset( $_POST['mfm_extras'] ) && is_array( $_POST['mfm_extras'] )
			? map_deep( wp_unslash( $_POST['mfm_extras'] ), 'sanitize_text_field' )
			: array();
		if ( ! empty( $submitted_extras ) ) {
			$extras = array();
			foreach ( $submitted_extras as $extra ) {
				if ( is_array( $extra ) && ! empty( $extra['name'] ) ) {
					$price = $this->normalize_option_price( $extra['price'] ?? '' );
					if ( null === $price ) {
						continue;
					}
					$extras[] = array(
						'name'  => sanitize_text_field( $extra['name'] ),
						'price' => $price,
					);
				}
			}
			update_post_meta( $post_id, '_mfm_extras', $extras );
		} else {
			delete_post_meta( $post_id, '_mfm_extras' );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Converts an option price to canonical decimal storage.
	 *
	 * @param mixed $price Submitted price.
	 * @return string|null
	 */
	private function normalize_option_price( $price ) {
		if ( ! is_scalar( $price ) ) {
			return null;
		}
		$price = '' === trim( (string) $price ) ? '0' : sanitize_text_field( $price );
		$cents = SnapOrder_Order_Calculator::money_to_cents( $price );
		return is_wp_error( $cents ) ? null : SnapOrder_Order_Calculator::cents_to_money( $cents );
	}
}
