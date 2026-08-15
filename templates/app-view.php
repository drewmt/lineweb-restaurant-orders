<?php
/**
 * Template Name: Food Menu App View
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Data preparation
// -------------------------------------------------------------------------

// Tracking details are fetched only after the browser proves possession of
// the opaque order token. Never expose order data from a numeric ID alone.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route selector; no order data is returned here.
$snaporder_tracking_order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

// Brand settings.
$snaporder_brand_logo        = get_option( 'snaporder_brand_logo', '' );
$snaporder_currency_symbol   = SnapOrder_Settings::get_currency_symbol();
$snaporder_placeholder_image = SNAPORDER_PLUGIN_URL . 'assets/images/food-placeholder.svg';
$snaporder_payment_methods   = SnapOrder_Settings::get_enabled_payment_methods();
$snaporder_default_payment   = ! empty( $snaporder_payment_methods ) ? $snaporder_payment_methods[0] : '';

/**
 * Gets a product image with thumbnail and bundled fallbacks.
 *
 * @param int    $item_id  Food item post ID.
 * @param string $size     Requested WordPress image size.
 * @param string $fallback Bundled fallback URL.
 * @return string
 */
function snaporder_get_item_image( $item_id, $size, $fallback ) {
	$custom_image = get_post_meta( $item_id, '_snaporder_custom_image_url', true );
	if ( $custom_image ) {
		return $custom_image;
	}

	$thumbnail = get_the_post_thumbnail_url( $item_id, $size );
	return $thumbnail ? $thumbnail : $fallback;
}
// Opening hours check uses the WordPress site timezone.
$snaporder_store_closed   = ! SnapOrder_Settings::is_store_open();
$snaporder_closed_message = '';
if ( $snaporder_store_closed ) {
	$snaporder_closed_message = __( 'We are currently closed. Please order during opening hours.', 'lineweb-restaurant-orders' );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_title( '|', true, 'right' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( array( 'bg-gray-50', 'pb-20', $snaporder_store_closed ? 'snaporder-store-closed' : 'snaporder-store-open' ) ); ?>>
<?php wp_body_open(); ?>

<?php if ( $snaporder_store_closed ) : ?>
	<div class="fixed bottom-[60px] left-0 w-full z-[60] px-4">
		<div class="bg-red-600 text-white p-3 rounded-xl shadow-lg flex items-center justify-center gap-2 font-bold text-sm text-center">
			<i data-lucide="clock" class="w-5 h-5"></i>
			<?php echo esc_html( $snaporder_closed_message ); ?>
		</div>
	</div>
<?php endif; ?>

<?php if ( $snaporder_tracking_order_id ) : ?>
	<!-- ====================================================== -->
	<!-- TRACKING SCREEN                                        -->
	<!-- ====================================================== -->
	<div class="max-w-md mx-auto bg-white min-h-screen relative shadow-2xl overflow-hidden">

		<div class="snaporder-bg-primary-50 p-5 text-center border-b snaporder-border-primary-100">
			<div class="w-14 h-14 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm">
				<i data-lucide="check" class="w-6 h-6 snaporder-text-primary"></i>
			</div>
			<h1 class="text-2xl font-bold text-gray-900 mb-1"><?php esc_html_e( 'Thank you for your order!', 'lineweb-restaurant-orders' ); ?></h1>
				<p class="text-gray-500 text-sm"><?php /* translators: %d: order ID. */ printf( esc_html__( 'Order #%d', 'lineweb-restaurant-orders' ), (int) $snaporder_tracking_order_id ); ?></p>
		</div>

		<div class="p-5" id="order-status-container" data-order-id="<?php echo (int) $snaporder_tracking_order_id; ?>" data-current-status="">
			<h3 class="font-bold text-gray-900 mb-3 text-base"><?php esc_html_e( 'Order Status', 'lineweb-restaurant-orders' ); ?></h3>
			<div class="snaporder-bg-primary text-white p-3 rounded-xl flex items-center justify-between mb-5 shadow-lg snaporder-shadow-primary">
				<div class="flex items-center gap-2">
					<div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
					<span class="font-bold text-lg capitalize" id="live-status-text"><?php esc_html_e( 'Checking order...', 'lineweb-restaurant-orders' ); ?></span>
				</div>
				<span class="text-xs bg-white/20 px-2 py-0.5 rounded"><?php esc_html_e( 'Live Update', 'lineweb-restaurant-orders' ); ?></span>
			</div>
			<div class="space-y-5 relative pl-4 border-l-2 border-gray-100 ml-2">
				<?php
				$snaporder_steps = array(
					'pending'   => __( 'Order Received', 'lineweb-restaurant-orders' ),
					'accepted'  => __( 'Accepted', 'lineweb-restaurant-orders' ),
					'cooking'   => __( 'Cooking', 'lineweb-restaurant-orders' ),
					'ready'     => __( 'Ready', 'lineweb-restaurant-orders' ),
					'completed' => __( 'Completed', 'lineweb-restaurant-orders' ),
				);
				foreach ( $snaporder_steps as $snaporder_key => $snaporder_label ) :
					?>
					<div class="relative pl-5 status-step" data-step="<?php echo esc_attr( $snaporder_key ); ?>">
						<div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full border-2 bg-gray-200 border-gray-200 bg-white"></div>
						<p class="font-medium text-base text-gray-400"><?php echo esc_html( $snaporder_label ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div id="snaporder-order-details" class="p-5 border-t border-gray-100 hidden">
			<h3 class="font-bold text-gray-900 mb-3 text-base"><?php esc_html_e( 'Order Details', 'lineweb-restaurant-orders' ); ?></h3>
			<div class="bg-gray-50 rounded-xl p-3 space-y-2" id="snaporder-order-detail-items"></div>
			<div class="bg-gray-50 rounded-xl px-3 pb-3">
				<div class="border-t border-gray-200 pt-2 flex justify-between items-center mt-2">
					<span class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Total', 'lineweb-restaurant-orders' ); ?></span>
					<span class="font-bold text-xl snaporder-text-primary" id="snaporder-order-detail-total"></span>
				</div>
			</div>
		</div>

		<div class="p-5">
			<a href="<?php echo esc_url( get_permalink() ); ?>" class="block w-full snaporder-bg-primary text-white text-center font-bold py-3.5 rounded-xl shadow-lg snaporder-shadow-primary snaporder-hover-bg-primary-dark transition-all text-base">
				<?php esc_html_e( 'Back to Menu', 'lineweb-restaurant-orders' ); ?>
			</a>
		</div>
	</div>

<?php else : ?>
	<!-- ====================================================== -->
	<!-- APP VIEW (MENU)                                         -->
	<!-- ====================================================== -->
	<div class="max-w-md mx-auto bg-white min-h-screen relative shadow-2xl overflow-hidden">

		<!-- Header -->
		<header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-gray-100 px-4 py-3 flex justify-between items-center">
			<div class="flex items-center gap-3">
				<?php
				if ( $snaporder_brand_logo ) {
					echo '<img src="' . esc_url( $snaporder_brand_logo ) . '" alt="Logo" class="h-8 w-auto">';
				} elseif ( has_custom_logo() ) {
					the_custom_logo();
				} else {
					echo '<span class="font-black text-xl tracking-tight text-gray-900">SNAP<span class="snaporder-text-primary">ORDER</span></span>';
				}
				$snaporder_store_title   = get_option( 'snaporder_store_title' );
				$snaporder_store_tagline = get_option( 'snaporder_store_tagline' );
				if ( $snaporder_store_title || $snaporder_store_tagline ) :
					?>
				<div class="flex flex-col">
					<?php
					if ( $snaporder_store_title ) :
						?>
						<span class="font-bold text-gray-900 text-base leading-tight"><?php echo esc_html( $snaporder_store_title ); ?></span><?php endif; ?>
					<?php
					if ( $snaporder_store_tagline ) :
						?>
						<span class="text-xs text-gray-500 leading-tight"><?php echo esc_html( $snaporder_store_tagline ); ?></span><?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="flex items-center gap-2">
				<?php
				$snaporder_social = array(
					'snaporder_facebook_url'  => 'facebook',
					'snaporder_instagram_url' => 'instagram',
					'snaporder_twitter_url'   => 'twitter',
				);
				foreach ( $snaporder_social as $snaporder_option => $snaporder_icon ) :
					$snaporder_url = get_option( $snaporder_option );
					if ( $snaporder_url ) :
						?>
				<a href="<?php echo esc_url( $snaporder_url ); ?>" target="_blank" rel="noopener noreferrer" class="p-2 rounded-full hover:bg-gray-100 text-gray-600 transition-colors">
					<i data-lucide="<?php echo esc_attr( $snaporder_icon ); ?>" class="w-5 h-5"></i>
				</a>
						<?php
				endif;
endforeach;
				?>
			</div>
		</header>

		<!-- Search -->
		<div class="px-4 py-3">
			<div class="relative">
				<i data-lucide="search" class="absolute left-4 top-3.5 w-4 h-4 text-gray-400"></i>
				<input type="text" id="snaporder-search-input" placeholder="<?php esc_attr_e( 'Search for food...', 'lineweb-restaurant-orders' ); ?>"
					class="w-full bg-gray-100 text-gray-900 rounded-xl pl-10 pr-4 py-3 font-medium text-base focus:outline-none focus:ring-2 snaporder-ring-primary transition-all">
			</div>
		</div>

		<!-- Banners -->
		<?php
		$snaporder_banners = new WP_Query(
			array(
				'post_type'      => 'snaporder_banner',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);
		if ( $snaporder_banners->have_posts() ) :
			?>
		<div class="mb-6 overflow-x-auto hide-scrollbar px-4 flex gap-3 snap-x snap-mandatory">
			<?php
			while ( $snaporder_banners->have_posts() ) :
				$snaporder_banners->the_post();
				$snaporder_bg = get_post_meta( get_the_ID(), '_snaporder_custom_image_url', true );
				if ( ! $snaporder_bg ) {
					$snaporder_bg = get_the_post_thumbnail_url( get_the_ID(), 'large' );
				}
				if ( ! $snaporder_bg ) {
					$snaporder_bg = $snaporder_placeholder_image;
				}
				$snaporder_subtitle    = get_post_meta( get_the_ID(), '_snaporder_banner_subtitle', true );
				$snaporder_button_text = get_post_meta( get_the_ID(), '_snaporder_banner_button_text', true );
				$snaporder_button_link = get_post_meta( get_the_ID(), '_snaporder_banner_button_link', true );
				?>
			<div class="snap-center shrink-0 w-[70vw] h-40 rounded-2xl bg-cover bg-center relative overflow-hidden shadow-md flex flex-col justify-end p-6"
				style="background-image:url('<?php echo esc_url( $snaporder_bg ); ?>');">
				<div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
				<div class="relative z-10 text-white">
					<h2 class="text-3xl font-bold mb-1 leading-tight"><?php echo esc_html( get_the_title() ); ?></h2>
					<?php
					if ( $snaporder_subtitle ) :
						?>
						<p class="text-gray-200 text-base mb-3 line-clamp-2"><?php echo esc_html( $snaporder_subtitle ); ?></p><?php endif; ?>
					<?php if ( $snaporder_button_text && $snaporder_button_link ) : ?>
					<a href="<?php echo esc_url( $snaporder_button_link ); ?>" class="inline-block snaporder-bg-primary text-white px-4 py-2 rounded-lg font-bold text-base snaporder-hover-bg-primary-dark transition-colors shadow-sm">
						<?php echo esc_html( $snaporder_button_text ); ?>
					</a>
					<?php endif; ?>
				</div>
			</div>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<?php endif; ?>

		<!-- Main Content Wrapper -->
		<div id="snaporder-main-content">
			<div class="mb-4 px-4">
				<div id="snaporder-category-nav" class="flex gap-2 overflow-x-auto hide-scrollbar pb-2"></div>
			</div>

			<!-- Recommended / Featured Items -->
			<section class="mb-8 px-4 snaporder-recommended-section">
				<div class="flex justify-between items-end mb-4">
					<h2 class="text-2xl font-bold text-gray-900"><?php esc_html_e( 'Recommended', 'lineweb-restaurant-orders' ); ?></h2>
					<a href="#" onclick="SnapOrderApp.showFeaturedPage(); return false;" class="snaporder-text-primary text-base font-bold snaporder-hover-text-primary-dark"><?php esc_html_e( 'See all', 'lineweb-restaurant-orders' ); ?></a>
				</div>
				<div class="flex gap-4 overflow-x-auto hide-scrollbar pb-4">
					<?php
					$snaporder_featured_query = new WP_Query(
						array(
							'post_type'      => 'snaporder_item',
							'posts_per_page' => 5,
								// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Featured status is stored as product meta.
							'meta_key'       => '_snaporder_featured',
							'meta_value'     => '1',
								// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							'orderby'        => 'menu_order',
							'order'          => 'ASC',
						)
					);
					while ( $snaporder_featured_query->have_posts() ) :
						$snaporder_featured_query->the_post();
						$snaporder_f_price     = get_post_meta( get_the_ID(), '_snaporder_price', true );
							$snaporder_f_img   = snaporder_get_item_image( get_the_ID(), 'medium', $snaporder_placeholder_image );
						$snaporder_f_extras    = get_post_meta( get_the_ID(), '_snaporder_extras', true );
						$snaporder_f_calories  = get_post_meta( get_the_ID(), '_snaporder_calories', true );
						$snaporder_f_dietary   = get_post_meta( get_the_ID(), '_snaporder_dietary', true );
						$snaporder_f_allergens = get_post_meta( get_the_ID(), '_snaporder_allergens', true );
						$snaporder_f_variants  = get_post_meta( get_the_ID(), '_snaporder_size', true );
						$snaporder_f_item_data = array(
							'id'          => get_the_ID(),
							'title'       => get_the_title(),
							'price'       => $snaporder_f_price,
							'image'       => $snaporder_f_img,
							'description' => wp_kses_post( get_the_content() ),
							'extras'      => is_array( $snaporder_f_extras ) ? $snaporder_f_extras : array(),
							'calories'    => $snaporder_f_calories,
							'dietary'     => is_array( $snaporder_f_dietary ) ? $snaporder_f_dietary : array(),
							'allergens'   => $snaporder_f_allergens,
							'variants'    => is_array( $snaporder_f_variants ) ? $snaporder_f_variants : array(),
						);
						?>
					<div class="snaporder-open-item shrink-0 w-[60vw] sm:w-72 bg-white rounded-2xl shadow-sm border border-gray-100 cursor-pointer overflow-hidden group transition-all hover:shadow-md"
						role="button" tabindex="0" data-snaporder-item="<?php echo esc_attr( wp_json_encode( $snaporder_f_item_data ) ); ?>">
						<div class="aspect-square w-full bg-cover bg-center relative" style="background-image:url('<?php echo esc_url( $snaporder_f_img ); ?>');">
							<div class="absolute inset-0 bg-black/5 group-hover:bg-black/0 transition-all"></div>
							<div class="absolute top-3 right-3 bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-full text-sm font-bold shadow-sm snaporder-text-primary"><?php echo esc_html( $snaporder_f_price . $snaporder_currency_symbol ); ?></div>
							<?php if ( $snaporder_f_calories ) : ?>
							<div class="absolute bottom-3 left-3 bg-black/60 backdrop-blur-md px-2 py-1 rounded-lg text-xs font-medium text-white flex items-center gap-1">
								<i data-lucide="flame" class="w-3.5 h-3.5 snaporder-text-primary-light"></i> <?php echo esc_html( $snaporder_f_calories ); ?> kcal
							</div>
							<?php endif; ?>
						</div>
						<div class="p-4">
							<h3 class="font-bold text-gray-900 truncate text-lg"><?php the_title(); ?></h3>
							<p class="text-sm text-gray-500 line-clamp-2 leading-relaxed"><?php echo esc_html( get_the_excerpt() ); ?></p>
						</div>
					</div>
						<?php
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>

			<!-- Main Menu List -->
			<div class="px-4 pb-28">
				<?php
				if ( ! is_wp_error( $snaporder_categories ) ) :
					foreach ( $snaporder_categories as $snaporder_cat ) :
						$snaporder_cat_query = new WP_Query(
							array(
								'post_type'      => 'snaporder_item',
								'posts_per_page' => -1,
									// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Each visible section is intentionally scoped to one menu category.
								'tax_query'      => array(
									array(
										'taxonomy' => 'snaporder_category',
										'field'    => 'slug',
										'terms'    => $snaporder_cat->slug,
									),
								),
								'orderby'        => 'menu_order',
								'order'          => 'ASC',
							)
						);
						if ( ! $snaporder_cat_query->have_posts() ) {
							wp_reset_postdata();
							continue;
						}
						?>
				<div class="mb-6 snaporder-category-list cat-<?php echo esc_attr( $snaporder_cat->slug ); ?>">
					<h3 class="text-xl font-bold text-gray-900 mb-3"><?php echo esc_html( $snaporder_cat->name ); ?></h3>
					<div class="space-y-3">
						<?php
						while ( $snaporder_cat_query->have_posts() ) :
							$snaporder_cat_query->the_post();
							$snaporder_m_price     = get_post_meta( get_the_ID(), '_snaporder_price', true );
								$snaporder_m_img   = snaporder_get_item_image( get_the_ID(), 'thumbnail', $snaporder_placeholder_image );
							$snaporder_m_extras    = get_post_meta( get_the_ID(), '_snaporder_extras', true );
							$snaporder_m_calories  = get_post_meta( get_the_ID(), '_snaporder_calories', true );
							$snaporder_m_dietary   = get_post_meta( get_the_ID(), '_snaporder_dietary', true );
							$snaporder_m_allergens = get_post_meta( get_the_ID(), '_snaporder_allergens', true );
							$snaporder_m_variants  = get_post_meta( get_the_ID(), '_snaporder_size', true );
							$snaporder_m_item_data = array(
								'id'          => get_the_ID(),
								'title'       => get_the_title(),
								'price'       => $snaporder_m_price,
								'image'       => $snaporder_m_img,
								'description' => wp_kses_post( get_the_content() ),
								'extras'      => is_array( $snaporder_m_extras ) ? $snaporder_m_extras : array(),
								'calories'    => $snaporder_m_calories,
								'dietary'     => is_array( $snaporder_m_dietary ) ? $snaporder_m_dietary : array(),
								'allergens'   => $snaporder_m_allergens,
								'variants'    => is_array( $snaporder_m_variants ) ? $snaporder_m_variants : array(),
							);
							?>
						<div class="snaporder-open-item snaporder-list-card snaporder-menu-item flex gap-3 bg-white p-2.5 rounded-xl shadow-sm border border-gray-100 cursor-pointer snaporder-hover-border-primary-200 transition-all"
							role="button" tabindex="0" data-snaporder-item="<?php echo esc_attr( wp_json_encode( $snaporder_m_item_data ) ); ?>">
							<div class="w-20 h-20 bg-gray-100 rounded-lg bg-cover bg-center flex-none relative" style="background-image:url('<?php echo esc_url( $snaporder_m_img ); ?>');">
								<?php if ( ! empty( $snaporder_m_dietary ) && ( in_array( 'vegetarian', $snaporder_m_dietary, true ) || in_array( 'vegan', $snaporder_m_dietary, true ) ) ) : ?>
								<div class="absolute -top-1 -left-1 w-5 h-5 rounded-full bg-green-100 flex items-center justify-center shadow-sm border border-white">
									<i data-lucide="leaf" class="w-3 h-3 text-green-600"></i>
								</div>
								<?php endif; ?>
							</div>
							<div class="flex-1 flex flex-col justify-center min-w-0">
								<h4 class="font-bold text-gray-900 mb-0.5 text-lg truncate"><?php the_title(); ?></h4>
								<p class="text-xs text-gray-500 line-clamp-2 mb-1.5 leading-tight"><?php echo esc_html( get_the_excerpt() ); ?></p>
								<div class="flex items-center gap-2">
									<div class="font-bold snaporder-text-primary text-base"><?php echo esc_html( $snaporder_m_price . $snaporder_currency_symbol ); ?></div>
									<?php if ( $snaporder_m_calories ) : ?>
									<div class="text-xs text-red-500 flex items-center gap-0.5 font-medium">
										<i data-lucide="flame" class="w-3 h-3"></i> <?php echo esc_html( $snaporder_m_calories ); ?>
									</div>
									<?php endif; ?>
								</div>
							</div>
							<div class="flex items-end">
								<button class="w-7 h-7 bg-gray-900 text-white rounded-full flex items-center justify-center shadow-lg">
									<i data-lucide="plus" class="w-4 h-4"></i>
								</button>
							</div>
						</div>
							<?php
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>
						<?php
				endforeach;
endif;
				?>
			</div>
		</div><!-- End #snaporder-main-content -->

		<!-- Featured Items Full-Page (hidden by default) -->
		<div id="snaporder-featured-page" class="hidden px-4 pb-24">
			<div class="flex items-center gap-3 mb-6">
				<button onclick="SnapOrderApp.hideFeaturedPage()" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
					<i data-lucide="arrow-left" class="w-6 h-6 text-gray-900"></i>
				</button>
				<h2 class="text-2xl font-bold text-gray-900"><?php esc_html_e( 'Featured Items', 'lineweb-restaurant-orders' ); ?></h2>
			</div>
			<div class="grid grid-cols-2 gap-3">
				<?php
				$snaporder_all_featured = new WP_Query(
					array(
						'post_type'      => 'snaporder_item',
						'posts_per_page' => -1,
							// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Featured status is stored as product meta.
						'meta_key'       => '_snaporder_featured',
						'meta_value'     => '1',
							// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'orderby'        => 'menu_order',
						'order'          => 'ASC',
					)
				);
				while ( $snaporder_all_featured->have_posts() ) :
					$snaporder_all_featured->the_post();
					$snaporder_af_price     = get_post_meta( get_the_ID(), '_snaporder_price', true );
						$snaporder_af_img   = snaporder_get_item_image( get_the_ID(), 'medium', $snaporder_placeholder_image );
					$snaporder_af_extras    = get_post_meta( get_the_ID(), '_snaporder_extras', true );
					$snaporder_af_calories  = get_post_meta( get_the_ID(), '_snaporder_calories', true );
					$snaporder_af_dietary   = get_post_meta( get_the_ID(), '_snaporder_dietary', true );
					$snaporder_af_allergens = get_post_meta( get_the_ID(), '_snaporder_allergens', true );
					$snaporder_af_variants  = get_post_meta( get_the_ID(), '_snaporder_size', true );
					$snaporder_af_item_data = array(
						'id'          => get_the_ID(),
						'title'       => get_the_title(),
						'price'       => $snaporder_af_price,
						'image'       => $snaporder_af_img,
						'description' => wp_kses_post( get_the_content() ),
						'extras'      => is_array( $snaporder_af_extras ) ? $snaporder_af_extras : array(),
						'calories'    => $snaporder_af_calories,
						'dietary'     => is_array( $snaporder_af_dietary ) ? $snaporder_af_dietary : array(),
						'allergens'   => $snaporder_af_allergens,
						'variants'    => is_array( $snaporder_af_variants ) ? $snaporder_af_variants : array(),
					);
					?>
				<div class="snaporder-open-item bg-white rounded-2xl shadow-sm border border-gray-100 cursor-pointer overflow-hidden group transition-all hover:shadow-md"
					role="button" tabindex="0" data-snaporder-item="<?php echo esc_attr( wp_json_encode( $snaporder_af_item_data ) ); ?>">
					<div class="aspect-square w-full bg-cover bg-center relative" style="background-image:url('<?php echo esc_url( $snaporder_af_img ); ?>');">
						<div class="absolute top-2 right-2 bg-white/90 backdrop-blur-md px-2.5 py-1 rounded-full text-sm font-bold shadow-sm snaporder-text-primary"><?php echo esc_html( $snaporder_af_price . $snaporder_currency_symbol ); ?></div>
						<?php if ( $snaporder_af_calories ) : ?>
						<div class="absolute bottom-2 left-2 bg-black/60 backdrop-blur-md px-2 py-0.5 rounded-lg text-xs font-medium text-white flex items-center gap-1">
							<i data-lucide="flame" class="w-3 h-3 snaporder-text-primary-light"></i> <?php echo esc_html( $snaporder_af_calories ); ?>
						</div>
						<?php endif; ?>
					</div>
					<div class="p-3">
						<h4 class="font-bold text-gray-900 text-base mb-1 line-clamp-2"><?php the_title(); ?></h4>
						<p class="text-xs text-gray-500 line-clamp-2 leading-tight"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php if ( $snaporder_af_allergens ) : ?>
						<div class="text-[10px] text-red-400 mt-1.5 truncate">Contains: <?php echo esc_html( $snaporder_af_allergens ); ?></div>
						<?php endif; ?>
					</div>
				</div>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>

		<!-- Floating Cart Bar -->
		<div id="snaporder-bottom-bar" class="fixed bottom-[70px] left-0 w-full px-4 z-40 hidden transform transition-transform duration-300">
			<div class="max-w-md mx-auto bg-gray-900 text-white p-3 rounded-xl shadow-2xl flex justify-between items-center cursor-pointer" onclick="SnapOrderCart.openCart()">
				<div class="flex items-center gap-3">
					<div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center font-bold text-sm" id="snaporder-bar-count">0</div>
					<div class="flex flex-col">
						<span class="text-[10px] text-gray-400"><?php esc_html_e( 'Total', 'lineweb-restaurant-orders' ); ?></span>
						<span class="font-bold text-base leading-none" id="snaporder-bar-total">0.00<?php echo esc_html( $snaporder_currency_symbol ); ?></span>
					</div>
				</div>
				<div class="flex items-center gap-2 font-bold text-xs"><?php esc_html_e( 'View Cart', 'lineweb-restaurant-orders' ); ?> <i data-lucide="chevron-right" class="w-4 h-4"></i></div>
			</div>
		</div>

		<!-- Bottom Navigation -->
		<div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 pb-safe">
			<div class="max-w-md mx-auto flex justify-around items-center h-14 px-2">
				<a href="<?php echo esc_url( get_permalink() ); ?>" class="bottom-nav-item active flex flex-col items-center justify-center w-full h-full space-y-0.5 text-gray-400 snaporder-hover-text-primary">
					<i data-lucide="home" class="w-6 h-6"></i>
					<span class="text-xs font-medium"><?php esc_html_e( 'Menu', 'lineweb-restaurant-orders' ); ?></span>
				</a>
				<button onclick="document.getElementById('snaporder-search-input').focus()" class="bottom-nav-item flex flex-col items-center justify-center w-full h-full space-y-0.5 text-gray-400 snaporder-hover-text-primary">
					<i data-lucide="search" class="w-6 h-6"></i>
					<span class="text-xs font-medium"><?php esc_html_e( 'Search', 'lineweb-restaurant-orders' ); ?></span>
				</button>
				<button onclick="SnapOrderCart.openCart()" class="bottom-nav-item flex flex-col items-center justify-center w-full h-full space-y-0.5 text-gray-400 snaporder-hover-text-primary relative">
					<i data-lucide="shopping-bag" class="w-6 h-6"></i>
					<span class="text-xs font-medium"><?php esc_html_e( 'Cart', 'lineweb-restaurant-orders' ); ?></span>
					<span class="snaporder-cart-count absolute top-0 right-3 w-4 h-4 snaporder-bg-primary text-white text-[9px] font-bold flex items-center justify-center rounded-full border-2 border-white">0</span>
				</button>
				<button class="bottom-nav-item flex flex-col items-center justify-center w-full h-full space-y-0.5 text-gray-400 snaporder-hover-text-primary">
					<i data-lucide="user" class="w-6 h-6"></i>
					<span class="text-xs font-medium"><?php esc_html_e( 'Profile', 'lineweb-restaurant-orders' ); ?></span>
				</button>
			</div>
		</div>

		<!-- Item Modal -->
		<div id="snaporder-modal" class="fixed inset-0 z-50 hidden">
			<div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="SnapOrderApp.closeModal()"></div>
			<div class="snaporder-modal-content absolute bottom-0 left-0 w-full bg-white rounded-t-3xl h-[90vh] overflow-y-auto translate-y-full">
				<div class="relative h-60 bg-gray-100">
					<div class="snaporder-modal-image w-full h-full bg-cover bg-center"></div>
					<button onclick="SnapOrderApp.closeModal()" class="absolute top-4 right-4 w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-lg z-10">
						<i data-lucide="x" class="w-5 h-5 text-gray-900"></i>
					</button>
				</div>
				<div class="p-5 pb-32">
					<div class="flex justify-between items-start mb-2">
						<h2 class="snaporder-modal-title text-2xl font-bold text-gray-900 w-3/4"></h2>
						<div class="flex flex-col items-end">
							<span class="snaporder-modal-price text-xl font-bold snaporder-text-primary"></span>
							<div class="snaporder-modal-dietary flex gap-1 mt-1"></div>
						</div>
					</div>
					<div class="flex items-center gap-4 mb-4 text-base text-gray-500">
						<div class="snaporder-modal-calories-wrap flex items-center gap-1 text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded-lg">
							<i data-lucide="flame" class="w-4 h-4 snaporder-text-primary"></i>
							<span class="snaporder-modal-calories font-medium"></span>
						</div>
						<div class="snaporder-modal-allergens flex items-center gap-1 hidden text-red-500">
							<i data-lucide="alert-circle" class="w-4 h-4"></i>
							<span></span>
						</div>
					</div>
					<p class="snaporder-modal-desc text-gray-500 text-base leading-relaxed mb-6"></p>
					<div class="snaporder-modal-variants-wrapper hidden mb-6">
						<h3 class="font-bold text-gray-900 mb-3 text-sm"><?php esc_html_e( 'Choose Variant', 'lineweb-restaurant-orders' ); ?></h3>
						<div class="snaporder-modal-variants space-y-2"></div>
					</div>
					<div class="snaporder-modal-extras-wrapper hidden mb-8">
						<h3 class="font-bold text-gray-900 mb-3 text-sm"><?php esc_html_e( 'Extras', 'lineweb-restaurant-orders' ); ?></h3>
						<div class="snaporder-modal-extras space-y-2"></div>
					</div>
					<div class="mb-6">
						<label class="block font-bold text-gray-900 mb-2 text-sm"><?php esc_html_e( 'Special Instructions (Optional)', 'lineweb-restaurant-orders' ); ?></label>
						<textarea id="snaporder-product-notes" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 snaporder-ring-primary focus:border-transparent resize-none" rows="3" placeholder="<?php esc_attr_e( 'E.g., No onions, extra sauce, well done...', 'lineweb-restaurant-orders' ); ?>"></textarea>
					</div>
					<div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-100 p-4 pb-8 shadow-[0_-10px_40px_rgb(0,0,0,0.05)]">
						<div class="flex items-center justify-between gap-3 max-w-md mx-auto">
							<div class="flex items-center gap-3 bg-gray-100 rounded-xl p-1">
								<button class="w-10 h-10 flex items-center justify-center font-bold text-xl hover:bg-white rounded-lg transition-all" onclick="SnapOrderApp.decrementQty()">-</button>
								<span class="snaporder-modal-qty font-bold text-lg w-6 text-center">1</span>
								<button class="w-10 h-10 flex items-center justify-center font-bold text-xl hover:bg-white rounded-lg transition-all" onclick="SnapOrderApp.incrementQty()">+</button>
							</div>
							<button class="flex-1 bg-gray-900 text-white font-bold py-4 rounded-xl shadow-lg hover:bg-gray-800 transition-all flex justify-between px-5 text-base" onclick="SnapOrderApp.addToCart()">
								<span><?php esc_html_e( 'Add to Order', 'lineweb-restaurant-orders' ); ?></span>
								<span class="snaporder-modal-price-display"></span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Cart Modal -->
		<div id="snaporder-cart-modal" class="fixed inset-0 z-50 hidden">
			<div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="SnapOrderCart.closeCart()"></div>
			<div class="snaporder-cart-content absolute bottom-0 left-0 w-full bg-white rounded-t-3xl h-[85vh] flex flex-col translate-y-full">
				<div class="p-4 border-b border-gray-100 flex justify-between items-center">
					<h2 class="text-lg font-bold"><?php esc_html_e( 'Your Cart', 'lineweb-restaurant-orders' ); ?></h2>
					<button onclick="SnapOrderCart.closeCart()" class="p-2 bg-gray-100 rounded-full"><i data-lucide="x" class="w-5 h-5"></i></button>
				</div>
				<div class="flex-1 overflow-y-auto p-4 space-y-3" id="snaporder-cart-items"></div>
				<div class="p-4 border-t border-gray-100 bg-gray-50 pb-safe">
					<div class="flex justify-between items-center mb-4">
						<span class="text-gray-500 text-sm"><?php esc_html_e( 'Total Amount', 'lineweb-restaurant-orders' ); ?></span>
						<span class="text-xl font-bold text-gray-900" id="snaporder-cart-total">0.00<?php echo esc_html( $snaporder_currency_symbol ); ?></span>
					</div>
					<button onclick="SnapOrderCart.openCheckout()" class="w-full snaporder-bg-primary text-white font-bold py-3.5 rounded-xl shadow-lg snaporder-shadow-primary snaporder-hover-bg-primary-dark transition-all text-sm">
						<?php esc_html_e( 'Proceed to Checkout', 'lineweb-restaurant-orders' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Checkout Modal -->
		<div id="snaporder-checkout-modal" class="fixed inset-0 z-[60] hidden bg-white overflow-y-auto">
			<div class="max-w-md mx-auto min-h-screen flex flex-col">
				<div class="p-4 border-b border-gray-100 flex items-center gap-3 sticky top-0 bg-white z-10">
					<button onclick="SnapOrderCart.closeCheckout()" class="p-2 hover:bg-gray-100 rounded-full"><i data-lucide="arrow-left" class="w-5 h-5"></i></button>
					<h2 class="text-lg font-bold"><?php esc_html_e( 'Checkout', 'lineweb-restaurant-orders' ); ?></h2>
				</div>
				<div class="flex-1 p-5">
					<form id="snaporder-checkout-form" class="space-y-5">
						<!-- Delivery Type -->
						<div class="bg-gray-100 p-1 rounded-xl flex">
							<button type="button" id="btn-delivery" class="flex-1 py-3 rounded-lg text-sm font-bold shadow-sm bg-white text-gray-900 transition-all" onclick="SnapOrderCart.setDeliveryType('delivery')"><?php esc_html_e( 'Delivery', 'lineweb-restaurant-orders' ); ?></button>
							<button type="button" id="btn-pickup"   class="flex-1 py-3 rounded-lg text-sm font-bold text-gray-500 transition-all" onclick="SnapOrderCart.setDeliveryType('pickup')"><?php esc_html_e( 'Pickup', 'lineweb-restaurant-orders' ); ?></button>
							<?php if ( get_option( 'snaporder_dinein_enabled' ) === '1' ) : ?>
							<button type="button" id="btn-dinein" class="flex-1 py-3 rounded-lg text-sm font-bold text-gray-500 transition-all" onclick="SnapOrderCart.setDeliveryType('dinein')"><?php esc_html_e( 'Dine-In', 'lineweb-restaurant-orders' ); ?></button>
							<?php endif; ?>
						</div>
						<!-- Contact -->
						<div class="space-y-3">
							<h3 class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Contact Info', 'lineweb-restaurant-orders' ); ?></h3>
							<div id="contact-info-fields" class="space-y-2">
								<input type="text" name="name"  placeholder="<?php esc_attr_e( 'Full Name', 'lineweb-restaurant-orders' ); ?>"    required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary">
								<input type="tel"  name="phone" placeholder="<?php esc_attr_e( 'Phone Number', 'lineweb-restaurant-orders' ); ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary">
							</div>
							<?php if ( get_option( 'snaporder_dinein_enabled' ) === '1' ) : ?>
							<div id="dinein-fields" class="hidden">
								<input type="number" name="table_number" placeholder="<?php esc_attr_e( 'Table Number', 'lineweb-restaurant-orders' ); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary">
							</div>
							<?php endif; ?>
						</div>
						<!-- Delivery Address -->
						<div id="delivery-fields" class="space-y-3">
							<h3 class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Delivery Address', 'lineweb-restaurant-orders' ); ?></h3>
							<div class="flex gap-2">
								<input type="text" name="street" placeholder="<?php esc_attr_e( 'Street Name', 'lineweb-restaurant-orders' ); ?>" required class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary">
								<input type="text" name="number" placeholder="<?php esc_attr_e( 'No.', 'lineweb-restaurant-orders' ); ?>"         required class="w-24 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary">
							</div>
							<div class="flex gap-2">
								<input type="text" name="city" placeholder="<?php esc_attr_e( 'City', 'lineweb-restaurant-orders' ); ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary">
								<input type="text" name="zip"  placeholder="<?php esc_attr_e( 'ZIP', 'lineweb-restaurant-orders' ); ?>"  required class="w-28  bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary">
							</div>
						</div>
						<!-- Tips -->
						<?php if ( get_option( 'snaporder_tipping_enabled' ) === '1' ) : ?>
						<div class="space-y-3">
							<h3 class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Add a Tip', 'lineweb-restaurant-orders' ); ?></h3>
							<div class="grid grid-cols-4 gap-2">
								<button type="button" class="tip-btn border border-gray-200 rounded-xl py-2 font-medium text-sm hover:border-orange-500 hover:text-orange-500 transition-colors" onclick="SnapOrderCart.setTip(0.05)">5%</button>
								<button type="button" class="tip-btn border border-gray-200 rounded-xl py-2 font-medium text-sm hover:border-orange-500 hover:text-orange-500 transition-colors" onclick="SnapOrderCart.setTip(0.10)">10%</button>
								<button type="button" class="tip-btn border border-gray-200 rounded-xl py-2 font-medium text-sm hover:border-orange-500 hover:text-orange-500 transition-colors" onclick="SnapOrderCart.setTip(0.15)">15%</button>
								<button type="button" class="tip-btn border border-gray-200 rounded-xl py-2 font-medium text-sm hover:border-orange-500 hover:text-orange-500 transition-colors" onclick="SnapOrderCart.toggleCustomTip()"><?php esc_html_e( 'Custom', 'lineweb-restaurant-orders' ); ?></button>
							</div>
							<div id="custom-tip-wrap" class="hidden mt-2">
								<div class="relative">
									<span class="absolute left-4 top-3.5 text-gray-500 font-bold"><?php echo esc_html( $snaporder_currency_symbol ); ?></span>
									<input type="number" id="custom-tip-input" step="0.50" min="0" placeholder="0.00" class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary" oninput="SnapOrderCart.setCustomTip(this.value)">
								</div>
							</div>
							<input type="hidden" name="tip_amount" id="tip-amount-input" value="0">
							<div id="tip-display-row" class="text-sm text-gray-500 flex justify-between hidden">
								<span><?php esc_html_e( 'Tip added:', 'lineweb-restaurant-orders' ); ?></span>
								<span class="font-bold text-gray-900" id="tip-display-amount"></span>
							</div>
						</div>
						<?php endif; ?>
						<!-- Order Notes -->
						<div class="space-y-3">
							<h3 class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Order Notes (Optional)', 'lineweb-restaurant-orders' ); ?></h3>
							<textarea name="order_notes" id="snaporder-order-notes" placeholder="<?php esc_attr_e( 'Any special requests?', 'lineweb-restaurant-orders' ); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 text-base focus:outline-none snaporder-focus-border-primary resize-none" rows="3"></textarea>
						</div>
						<!-- Payment -->
						<div class="space-y-3">
							<h3 class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Payment Method', 'lineweb-restaurant-orders' ); ?></h3>
							<?php if ( in_array( 'stripe', $snaporder_payment_methods, true ) ) : ?>
							<div class="payment-option <?php echo 'stripe' === $snaporder_default_payment ? 'selected snaporder-border-primary snaporder-bg-primary-50' : 'border border-gray-200'; ?> rounded-xl p-4 flex items-center gap-3 cursor-pointer" data-payment-method="stripe" role="button" tabindex="0">
								<div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm"><i data-lucide="credit-card" class="w-5 h-5 snaporder-text-primary"></i></div>
								<span class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Credit/Debit Card', 'lineweb-restaurant-orders' ); ?></span>
								<input type="radio" name="payment" value="stripe" <?php checked( 'stripe', $snaporder_default_payment ); ?> class="hidden">
							</div>
							<div id="stripe-card-element" class="mt-4 p-4 bg-white rounded-lg border border-gray-200"<?php echo 'stripe' === $snaporder_default_payment ? '' : ' style="display:none"'; ?>></div>
							<div id="stripe-card-errors" class="mt-2 text-red-600 text-sm"></div>
							<?php endif; ?>
							<?php if ( in_array( 'cod', $snaporder_payment_methods, true ) ) : ?>
							<div class="payment-option <?php echo 'cod' === $snaporder_default_payment ? 'selected snaporder-border-primary snaporder-bg-primary-50' : 'border border-gray-200'; ?> rounded-xl p-4 flex items-center gap-3 cursor-pointer" data-payment-method="cod" role="button" tabindex="0">
								<div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm"><i data-lucide="banknote" class="w-5 h-5 snaporder-text-primary"></i></div>
								<span class="font-bold text-gray-900 text-base"><?php esc_html_e( 'Cash on Delivery', 'lineweb-restaurant-orders' ); ?></span>
								<input type="radio" name="payment" value="cod" <?php checked( 'cod', $snaporder_default_payment ); ?> class="hidden">
							</div>
							<?php endif; ?>
							<?php if ( empty( $snaporder_payment_methods ) ) : ?>
							<p class="rounded-xl bg-red-50 p-4 text-sm text-red-700"><?php esc_html_e( 'Ordering is temporarily unavailable because no payment method is configured.', 'lineweb-restaurant-orders' ); ?></p>
							<?php endif; ?>
						</div>
						<button type="submit" <?php disabled( empty( $snaporder_payment_methods ) ); ?> class="w-full bg-gray-900 text-white font-bold py-4 rounded-xl shadow-lg hover:bg-gray-800 transition-all mt-6 text-base disabled:opacity-50 disabled:cursor-not-allowed">
							<?php esc_html_e( 'Place Order', 'lineweb-restaurant-orders' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div><!-- End #snaporder-checkout-modal -->
	</div><!-- End app wrapper -->
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
