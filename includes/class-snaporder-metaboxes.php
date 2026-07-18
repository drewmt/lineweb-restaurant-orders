<?php
/**
 * Meta boxes for food items — clean version with all options included.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and saves the primary food-item fields.
 */
class SnapOrder_Metaboxes {

	/**
	 * Registers metabox hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
	}

	/**
	 * Adds the food details metabox.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'mfm_food_details',
			__( 'Food Details', 'lineweb-restaurant-orders' ),
			array( $this, 'render_meta_box' ),
			'food_item',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the food details form.
	 *
	 * @param WP_Post $post Food item post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'mfm_save_food_details', 'mfm_food_details_nonce' );

		$price       = get_post_meta( $post->ID, '_mfm_price', true );
		$ingredients = get_post_meta( $post->ID, '_mfm_ingredients', true );
		$calories    = get_post_meta( $post->ID, '_mfm_calories', true );
		$allergens   = get_post_meta( $post->ID, '_mfm_allergens', true );
		$dietary     = get_post_meta( $post->ID, '_mfm_dietary', true );
		if ( ! is_array( $dietary ) ) {
			$dietary = array();
		}
		?>
		<style>
			.mfm-row { margin-bottom: 15px; }
			.mfm-row label { display: block; font-weight: bold; margin-bottom: 5px; }
			.mfm-row input[type="text"], .mfm-row textarea { width: 100%; }
			.mfm-checkbox-group label { display: inline-block; margin-right: 15px; font-weight: normal; }
		</style>
		<div class="mfm-meta-box">
			<div class="mfm-row">
				<label for="mfm_price"><?php esc_html_e( 'Price', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="mfm_price" name="mfm_price" value="<?php echo esc_attr( $price ); ?>" placeholder="e.g. 12.99">
			</div>
			<div class="mfm-row">
				<label for="mfm_ingredients"><?php esc_html_e( 'Ingredients', 'lineweb-restaurant-orders' ); ?></label>
				<textarea id="mfm_ingredients" name="mfm_ingredients" rows="3"><?php echo esc_textarea( $ingredients ); ?></textarea>
			</div>
			<div class="mfm-row">
				<label for="mfm_calories"><?php esc_html_e( 'Calories', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="mfm_calories" name="mfm_calories" value="<?php echo esc_attr( $calories ); ?>" placeholder="e.g. 350 kcal">
			</div>
			<div class="mfm-row">
				<label for="mfm_allergens"><?php esc_html_e( 'Allergens', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="mfm_allergens" name="mfm_allergens" value="<?php echo esc_attr( $allergens ); ?>" placeholder="e.g. Nuts, Dairy">
			</div>

			<?php do_action( 'snaporder_product_options_metabox', $post ); ?>

			<div class="mfm-row">
				<label><?php esc_html_e( 'Dietary Badges', 'lineweb-restaurant-orders' ); ?></label>
				<div class="mfm-checkbox-group">
					<?php
					$badges = array(
						'vegan'       => __( 'Vegan', 'lineweb-restaurant-orders' ),
						'vegetarian'  => __( 'Vegetarian', 'lineweb-restaurant-orders' ),
						'gluten_free' => __( 'Gluten Free', 'lineweb-restaurant-orders' ),
						'spicy'       => __( 'Spicy', 'lineweb-restaurant-orders' ),
						'nut_free'    => __( 'Nut Free', 'lineweb-restaurant-orders' ),
					);
					foreach ( $badges as $key => $label ) {
						echo '<label><input type="checkbox" name="mfm_dietary[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $dietary, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
					}
					?>
				</div>
			</div>

			<div class="mfm-row">
				<label for="mfm_featured" style="display:inline-block;margin-right:10px;"><?php esc_html_e( 'Featured Item', 'lineweb-restaurant-orders' ); ?></label>
				<?php $featured = get_post_meta( $post->ID, '_mfm_featured', true ); ?>
				<input type="checkbox" id="mfm_featured" name="mfm_featured" value="1" <?php checked( $featured, '1' ); ?>>
				<span class="description"><?php esc_html_e( 'Show this item in the Recommended section.', 'lineweb-restaurant-orders' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Validates and saves food-item fields.
	 *
	 * @param int $post_id Food item post ID.
	 */
	public function save_meta_boxes( $post_id ) {
		if ( ! isset( $_POST['mfm_food_details_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfm_food_details_nonce'] ) ), 'mfm_save_food_details' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['mfm_price'] ) && is_scalar( $_POST['mfm_price'] ) ) {
			$price_cents = SnapOrder_Order_Calculator::money_to_cents( sanitize_text_field( wp_unslash( $_POST['mfm_price'] ) ) );
			if ( ! is_wp_error( $price_cents ) && $price_cents > 0 ) {
				update_post_meta( $post_id, '_mfm_price', SnapOrder_Order_Calculator::cents_to_money( $price_cents ) );
			}
		}
		if ( isset( $_POST['mfm_ingredients'] ) ) {
			update_post_meta( $post_id, '_mfm_ingredients', sanitize_textarea_field( wp_unslash( $_POST['mfm_ingredients'] ) ) );
		}
		if ( isset( $_POST['mfm_calories'] ) ) {
			update_post_meta( $post_id, '_mfm_calories', sanitize_text_field( wp_unslash( $_POST['mfm_calories'] ) ) );
		}
		if ( isset( $_POST['mfm_allergens'] ) ) {
			update_post_meta( $post_id, '_mfm_allergens', sanitize_text_field( wp_unslash( $_POST['mfm_allergens'] ) ) );
		}

		do_action( 'snaporder_save_product_options', $post_id );

		if ( isset( $_POST['mfm_dietary'] ) && is_array( $_POST['mfm_dietary'] ) ) {
			$dietary = array_intersect(
				array_map( 'sanitize_key', wp_unslash( $_POST['mfm_dietary'] ) ),
				array( 'vegan', 'vegetarian', 'gluten_free', 'spicy', 'nut_free' )
			);
			update_post_meta( $post_id, '_mfm_dietary', $dietary );
		} else {
			delete_post_meta( $post_id, '_mfm_dietary' );
		}
	}
}
