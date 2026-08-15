<?php
/**
 * QR-code generator for the public menu URL.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the local QR-code generator admin screen.
 */
class SnapOrder_QR_Code {

	/**
	 * Registers QR-code admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Adds the QR-code generator submenu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=snaporder_item',
			__( 'QR Code', 'lineweb-restaurant-orders' ),
			__( 'QR Code', 'lineweb-restaurant-orders' ),
			'manage_options',
			'snaporder-qr-code',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues the local QR library on its admin screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'snaporder_item_page_snaporder-qr-code' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'snaporder-qrcode-js', SNAPORDER_PLUGIN_URL . 'assets/vendor/qrcodejs/qrcode.min.js', array(), '1.0.0', true );
		wp_enqueue_script(
			'snaporder-admin-qr-code',
			SNAPORDER_PLUGIN_URL . 'assets/js/admin-qr-code.js',
			array( 'snaporder-qrcode-js' ),
			SNAPORDER_VERSION,
			true
		);
		wp_localize_script(
			'snaporder-admin-qr-code',
			'snaporder_qr_vars',
			array(
				'print_stylesheet' => SNAPORDER_PLUGIN_URL . 'assets/css/qr-print.css',
				'print_title'      => __( 'Print QR Code', 'lineweb-restaurant-orders' ),
			)
		);
	}

	/**
	 * Renders the QR-code generator screen.
	 */
	public function render_page() {
		// Get store details for defaults.
		$store_title   = get_option( 'snaporder_store_title', get_bloginfo( 'name' ) );
		$primary_color = get_option( 'snaporder_primary_color', '#f97316' );

		// Find the page using the Food Menu App View template.
		$menu_url = '';
		$pages    = get_pages(
			array(
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Page-template meta is the canonical WordPress lookup.
				'meta_key'   => '_wp_page_template',
				'meta_value' => 'snaporder-app-view.php',
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			)
		);

		if ( ! empty( $pages ) ) {
			$menu_url = get_permalink( $pages[0]->ID );
		} else {
			// Fall back to the product archive or menu URL.
			$menu_url = get_post_type_archive_link( 'snaporder_item' );
			if ( ! $menu_url ) {
				$menu_url = home_url( '/menu' );
			}
		}
		?>
		<div class="wrap snaporder-wrap">
			<h1 class="snaporder-page-title"><?php esc_html_e( 'QR Code Generator', 'lineweb-restaurant-orders' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Generate and print QR codes for your customers to scan and order at the table.', 'lineweb-restaurant-orders' ); ?>
			</p>

			<div class="snaporder-qr-layout">
				<!-- Settings Column -->
				<div class="snaporder-qr-card">
					<h2 class="snaporder-box-header">
						<span class="dashicons dashicons-admin-settings snaporder-box-icon"></span>
						<?php esc_html_e( 'Customize Code', 'lineweb-restaurant-orders' ); ?>
					</h2>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="snaporder_qr_url"><?php esc_html_e( 'Menu URL', 'lineweb-restaurant-orders' ); ?></label>
							</th>
							<td>
								<input type="url" id="snaporder_qr_url" class="large-text" value="<?php echo esc_url( $menu_url ); ?>">
								<p class="description"><?php esc_html_e( 'The link your customers will visit.', 'lineweb-restaurant-orders' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="snaporder_qr_color"><?php esc_html_e( 'Color', 'lineweb-restaurant-orders' ); ?></label>
							</th>
							<td>
								<input type="color" id="snaporder_qr_color" class="snaporder-qr-color" value="<?php echo esc_attr( $primary_color ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><label><?php esc_html_e( 'Size', 'lineweb-restaurant-orders' ); ?></label>
							</th>
							<td>
								<div class="snaporder-qr-size-row">
									<input type="range" id="snaporder_qr_size" min="128" max="512" step="32" value="256"
										class="snaporder-range-slider">
									<span id="snaporder_qr_size_val" class="snaporder-qr-size-value">256px</span>
								</div>
							</td>
						</tr>
					</table>

					<div class="snaporder-qr-actions">
						<button type="button" class="button snaporder-btn-primary button-large snaporder-qr-print-button" id="snaporder_print_btn">
							<span class="dashicons dashicons-printer snaporder-qr-print-icon"></span>
							<?php esc_html_e( 'Print QR Code', 'lineweb-restaurant-orders' ); ?>
						</button>
					</div>
				</div>

				<!-- Preview Column -->
				<div class="snaporder-qr-preview-col">
					<h2 class="snaporder-qr-preview-title">
						<?php esc_html_e( 'Live Preview', 'lineweb-restaurant-orders' ); ?></h2>

					<div id="snaporder_qr_container" class="snaporder-qr-print-box">
						<h3 id="snaporder_preview_title" class="snaporder-qr-title">
							<?php echo esc_html( $store_title ); ?>
						</h3>
						<div id="snaporder_qrcode" class="snaporder-qrcode"></div>
						<p class="snaporder-qr-scan-text">
							<?php esc_html_e( 'Scan to Order', 'lineweb-restaurant-orders' ); ?>
						</p>
					</div>

					<p class="description snaporder-qr-save-note">
						<?php esc_html_e( 'Right-click the image to save as PNG.', 'lineweb-restaurant-orders' ); ?>
					</p>
				</div>
			</div>
		</div>
			<?php
	}
}
