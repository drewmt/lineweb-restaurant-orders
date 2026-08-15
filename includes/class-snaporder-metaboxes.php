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
		add_action( 'save_post_snaporder_banner', array( $this, 'save_banner_meta_box' ) );
	}

	/**
	 * Adds the food details metabox.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'snaporder_food_details',
			__( 'Food Details', 'lineweb-restaurant-orders' ),
			array( $this, 'render_meta_box' ),
			'snaporder_item',
			'normal',
			'high'
		);

		add_meta_box(
			'snaporder_banner_details',
			__( 'Banner Details', 'lineweb-restaurant-orders' ),
			array( $this, 'render_banner_meta_box' ),
			'snaporder_banner',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the promotional banner fields.
	 *
	 * @param WP_Post $post Banner post.
	 */
	public function render_banner_meta_box( $post ) {
		wp_nonce_field( 'snaporder_save_banner_details', 'snaporder_banner_details_nonce' );

		$subtitle   = get_post_meta( $post->ID, '_snaporder_banner_subtitle', true );
		$button     = get_post_meta( $post->ID, '_snaporder_banner_button_text', true );
		$button_url = get_post_meta( $post->ID, '_snaporder_banner_button_link', true );
		?>
		<div class="snaporder-meta-box">
			<div class="snaporder-row">
				<label for="snaporder_banner_subtitle"><?php esc_html_e( 'Subtitle', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="snaporder_banner_subtitle" name="snaporder_banner_subtitle" value="<?php echo esc_attr( $subtitle ); ?>">
			</div>
			<div class="snaporder-row">
				<label for="snaporder_banner_button_text"><?php esc_html_e( 'Button text', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="snaporder_banner_button_text" name="snaporder_banner_button_text" value="<?php echo esc_attr( $button ); ?>">
			</div>
			<div class="snaporder-row">
				<label for="snaporder_banner_button_link"><?php esc_html_e( 'Button link', 'lineweb-restaurant-orders' ); ?></label>
				<input type="url" id="snaporder_banner_button_link" name="snaporder_banner_button_link" value="<?php echo esc_url( $button_url ); ?>" placeholder="https://">
			</div>
			<p class="description"><?php esc_html_e( 'Use the featured image as the banner background. Menu order controls banner order.', 'lineweb-restaurant-orders' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the food details form.
	 *
	 * @param WP_Post $post Food item post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'snaporder_save_food_details', 'snaporder_food_details_nonce' );

		$price       = get_post_meta( $post->ID, '_snaporder_price', true );
		$ingredients = get_post_meta( $post->ID, '_snaporder_ingredients', true );
		$calories    = get_post_meta( $post->ID, '_snaporder_calories', true );
		$allergens   = get_post_meta( $post->ID, '_snaporder_allergens', true );
		$dietary     = get_post_meta( $post->ID, '_snaporder_dietary', true );
		if ( ! is_array( $dietary ) ) {
			$dietary = array();
		}
		?>
		<div class="snaporder-meta-box">
			<div class="snaporder-row">
				<label for="snaporder_price"><?php esc_html_e( 'Price', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="snaporder_price" name="snaporder_price" value="<?php echo esc_attr( $price ); ?>" placeholder="e.g. 12.99">
			</div>
			<div class="snaporder-row">
				<label for="snaporder_ingredients"><?php esc_html_e( 'Ingredients', 'lineweb-restaurant-orders' ); ?></label>
				<textarea id="snaporder_ingredients" name="snaporder_ingredients" rows="3"><?php echo esc_textarea( $ingredients ); ?></textarea>
			</div>
			<div class="snaporder-row">
				<label for="snaporder_calories"><?php esc_html_e( 'Calories', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="snaporder_calories" name="snaporder_calories" value="<?php echo esc_attr( $calories ); ?>" placeholder="e.g. 350 kcal">
			</div>
			<div class="snaporder-row">
				<label for="snaporder_allergens"><?php esc_html_e( 'Allergens', 'lineweb-restaurant-orders' ); ?></label>
				<input type="text" id="snaporder_allergens" name="snaporder_allergens" value="<?php echo esc_attr( $allergens ); ?>" placeholder="e.g. Nuts, Dairy">
			</div>

			<?php do_action( 'snaporder_product_options_metabox', $post ); ?>

			<div class="snaporder-row">
				<label><?php esc_html_e( 'Dietary Badges', 'lineweb-restaurant-orders' ); ?></label>
				<div class="snaporder-checkbox-group">
					<?php
					$badges = array(
						'vegan'       => __( 'Vegan', 'lineweb-restaurant-orders' ),
						'vegetarian'  => __( 'Vegetarian', 'lineweb-restaurant-orders' ),
						'gluten_free' => __( 'Gluten Free', 'lineweb-restaurant-orders' ),
						'spicy'       => __( 'Spicy', 'lineweb-restaurant-orders' ),
						'nut_free'    => __( 'Nut Free', 'lineweb-restaurant-orders' ),
					);
					foreach ( $badges as $key => $label ) {
						echo '<label><input type="checkbox" name="snaporder_dietary[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $dietary, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
					}
					?>
				</div>
			</div>

			<div class="snaporder-row">
				<label for="snaporder_featured" style="display:inline-block;margin-right:10px;"><?php esc_html_e( 'Featured Item', 'lineweb-restaurant-orders' ); ?></label>
				<?php $featured = get_post_meta( $post->ID, '_snaporder_featured', true ); ?>
				<input type="checkbox" id="snaporder_featured" name="snaporder_featured" value="1" <?php checked( $featured, '1' ); ?>>
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
		if ( ! isset( $_POST['snaporder_food_details_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['snaporder_food_details_nonce'] ) ), 'snaporder_save_food_details' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['snaporder_price'] ) && is_scalar( $_POST['snaporder_price'] ) ) {
			$price_cents = SnapOrder_Order_Calculator::money_to_cents( sanitize_text_field( wp_unslash( $_POST['snaporder_price'] ) ) );
			if ( ! is_wp_error( $price_cents ) && $price_cents > 0 ) {
				update_post_meta( $post_id, '_snaporder_price', SnapOrder_Order_Calculator::cents_to_money( $price_cents ) );
			}
		}
		if ( isset( $_POST['snaporder_ingredients'] ) ) {
			update_post_meta( $post_id, '_snaporder_ingredients', sanitize_textarea_field( wp_unslash( $_POST['snaporder_ingredients'] ) ) );
		}
		if ( isset( $_POST['snaporder_calories'] ) ) {
			update_post_meta( $post_id, '_snaporder_calories', sanitize_text_field( wp_unslash( $_POST['snaporder_calories'] ) ) );
		}
		if ( isset( $_POST['snaporder_allergens'] ) ) {
			update_post_meta( $post_id, '_snaporder_allergens', sanitize_text_field( wp_unslash( $_POST['snaporder_allergens'] ) ) );
		}

		do_action( 'snaporder_save_product_options', $post_id );

		if ( isset( $_POST['snaporder_dietary'] ) && is_array( $_POST['snaporder_dietary'] ) ) {
			$dietary = array_intersect(
				array_map( 'sanitize_key', wp_unslash( $_POST['snaporder_dietary'] ) ),
				array( 'vegan', 'vegetarian', 'gluten_free', 'spicy', 'nut_free' )
			);
			update_post_meta( $post_id, '_snaporder_dietary', $dietary );
		} else {
			delete_post_meta( $post_id, '_snaporder_dietary' );
		}
	}

	/**
	 * Validates and saves promotional banner fields.
	 *
	 * @param int $post_id Banner post ID.
	 */
	public function save_banner_meta_box( $post_id ) {
		if ( ! isset( $_POST['snaporder_banner_details_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['snaporder_banner_details_nonce'] ) ), 'snaporder_save_banner_details' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['snaporder_banner_subtitle'] ) && is_scalar( $_POST['snaporder_banner_subtitle'] ) ) {
			update_post_meta( $post_id, '_snaporder_banner_subtitle', sanitize_text_field( wp_unslash( $_POST['snaporder_banner_subtitle'] ) ) );
		}
		if ( isset( $_POST['snaporder_banner_button_text'] ) && is_scalar( $_POST['snaporder_banner_button_text'] ) ) {
			update_post_meta( $post_id, '_snaporder_banner_button_text', sanitize_text_field( wp_unslash( $_POST['snaporder_banner_button_text'] ) ) );
		}
		if ( isset( $_POST['snaporder_banner_button_link'] ) && is_scalar( $_POST['snaporder_banner_button_link'] ) ) {
			update_post_meta( $post_id, '_snaporder_banner_button_link', esc_url_raw( wp_unslash( $_POST['snaporder_banner_button_link'] ) ) );
		}
	}
}
