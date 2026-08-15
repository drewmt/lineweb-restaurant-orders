<?php
/**
 * Public food-menu shortcode.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the lightweight catalogue shortcode.
 */
class SnapOrder_Shortcode {

	/**
	 * Registers the shortcode.
	 */
	public function __construct() {
		add_shortcode( 'snaporder_restaurant_menu', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render a safe, theme-friendly menu catalogue.
	 *
	 * The full ordering experience is available through the Food Menu App View
	 * page template. The shortcode deliberately remains a lightweight catalogue.
	 *
	 * @param array $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'category' => '',
			),
			$attributes,
			'snaporder_restaurant_menu'
		);

		$query_args = array(
			'post_type'      => 'snaporder_item',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$category = sanitize_title( $attributes['category'] );
		if ( $category ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The optional shortcode attribute deliberately scopes products by category.
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'snaporder_category',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$query = new WP_Query( $query_args );
		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'No food items found.', 'lineweb-restaurant-orders' ) . '</p>';
		}

		$categories  = get_terms(
			array(
				'taxonomy'   => 'snaporder_category',
				'hide_empty' => true,
			)
		);
		$instance_id = wp_unique_id( 'snaporder-menu-' );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="snaporder-menu-container snaporder-shortcode-menu">
			<?php if ( ! $category && ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
				<div class="snaporder-cat-nav">
					<div class="snaporder-cat-scroll" role="group" aria-label="<?php esc_attr_e( 'Filter menu by category', 'lineweb-restaurant-orders' ); ?>">
						<button type="button" class="snaporder-cat-pill snaporder-shortcode-cat-pill active" data-filter="all"><?php esc_html_e( 'All', 'lineweb-restaurant-orders' ); ?></button>
						<?php foreach ( $categories as $term ) : ?>
							<button type="button" class="snaporder-cat-pill snaporder-shortcode-cat-pill" data-filter="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="snaporder-menu-items">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$item_id    = get_the_ID();
					$price      = get_post_meta( $item_id, '_snaporder_price', true );
					$image_url  = get_the_post_thumbnail_url( $item_id, 'medium' );
					$item_terms = get_the_terms( $item_id, 'snaporder_category' );
					$term_slugs = array();
					if ( is_array( $item_terms ) ) {
						$term_slugs = wp_list_pluck( $item_terms, 'slug' );
					}
					?>
					<article class="snaporder-list-card snaporder-shortcode-item" data-categories="<?php echo esc_attr( implode( ' ', $term_slugs ) ); ?>">
						<div class="snaporder-list-img">
							<?php if ( $image_url ) : ?>
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy">
							<?php endif; ?>
						</div>
						<div class="snaporder-list-info">
							<h3><?php echo esc_html( get_the_title() ); ?></h3>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
							<span class="snaporder-price"><?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( (float) $price, 2 ) ); ?></span>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
		</div>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}
}
