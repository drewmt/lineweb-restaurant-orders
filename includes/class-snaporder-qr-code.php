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
			'edit.php?post_type=food_item',
			__( 'QR Code', 'lineweb-restaurant-orders' ),
			__( 'QR Code', 'lineweb-restaurant-orders' ),
			'manage_options',
			'mfm-qr-code',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues the local QR library on its admin screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'food_item_page_mfm-qr-code' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'mfm-qrcode-js', SNAPORDER_PLUGIN_URL . 'assets/vendor/qrcodejs/qrcode.min.js', array(), '1.0.0', true );
	}

	/**
	 * Renders the QR-code generator screen.
	 */
	public function render_page() {
		// Get store details for defaults.
		$store_title   = get_option( 'mfm_store_title', get_bloginfo( 'name' ) );
		$primary_color = get_option( 'mfm_primary_color', '#f97316' );

		// Find the page using the Food Menu App View template.
		$menu_url = '';
		$pages    = get_pages(
			array(
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Page-template meta is the canonical WordPress lookup.
				'meta_key'   => '_wp_page_template',
				'meta_value' => 'mfm-app-view.php',
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			)
		);

		if ( ! empty( $pages ) ) {
			$menu_url = get_permalink( $pages[0]->ID );
		} else {
			// Fall back to the product archive or menu URL.
			$menu_url = get_post_type_archive_link( 'food_item' );
			if ( ! $menu_url ) {
				$menu_url = home_url( '/menu' );
			}
		}
		?>
		<div class="wrap mfm-wrap">
			<h1 class="mfm-page-title"><?php esc_html_e( 'QR Code Generator', 'lineweb-restaurant-orders' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Generate and print QR codes for your customers to scan and order at the table.', 'lineweb-restaurant-orders' ); ?>
			</p>

			<div class="mfm-qr-layout">
				<!-- Settings Column -->
				<div class="mfm-qr-card">
					<h2 class="mfm-box-header">
						<span class="dashicons dashicons-admin-settings mfm-box-icon"></span>
						<?php esc_html_e( 'Customize Code', 'lineweb-restaurant-orders' ); ?>
					</h2>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="mfm_qr_url"
									style="color:var(--mfm-text-main); font-weight:600;"><?php esc_html_e( 'Menu URL', 'lineweb-restaurant-orders' ); ?></label>
							</th>
							<td>
								<input type="url" id="mfm_qr_url" class="large-text" value="<?php echo esc_url( $menu_url ); ?>">
								<p class="description"><?php esc_html_e( 'The link your customers will visit.', 'lineweb-restaurant-orders' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="mfm_qr_color"
									style="color:var(--mfm-text-main); font-weight:600;"><?php esc_html_e( 'Color', 'lineweb-restaurant-orders' ); ?></label>
							</th>
							<td>
								<input type="color" id="mfm_qr_color" value="<?php echo esc_attr( $primary_color ); ?>"
									style="height: 40px; width: 60px; border:none; background:none; cursor:pointer;">
							</td>
						</tr>
						<tr>
							<th scope="row"><label
									style="color:var(--mfm-text-main); font-weight:600;"><?php esc_html_e( 'Size', 'lineweb-restaurant-orders' ); ?></label>
							</th>
							<td>
								<div style="display: flex; align-items: center; gap: 15px;">
									<input type="range" id="mfm_qr_size" min="128" max="512" step="32" value="256"
										class="mfm-range-slider">
									<span id="mfm_qr_size_val"
										style="font-weight:600; color:var(--mfm-text-muted); width: 60px;">256px</span>
								</div>
							</td>
						</tr>
					</table>

					<div style="margin-top: 30px; text-align: right; border-top: 1px solid #f3f4f6; padding-top: 20px;">
						<button type="button" class="button mfm-btn-primary button-large" id="mfm_print_btn"
							style="padding: 10px 24px !important; font-size: 16px !important;">
							<span class="dashicons dashicons-printer" style="line-height: normal; margin-right: 8px;"></span>
							<?php esc_html_e( 'Print QR Code', 'lineweb-restaurant-orders' ); ?>
						</button>
					</div>
				</div>

				<!-- Preview Column -->
				<div class="mfm-qr-preview-col">
					<h2
						style="margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: var(--mfm-text-muted); text-align: left; width: 100%; max-width: 400px;">
						<?php esc_html_e( 'Live Preview', 'lineweb-restaurant-orders' ); ?></h2>

					<div id="mfm_qr_container" class="mfm-qr-print-box">
						<h3 id="mfm_preview_title" class="mfm-qr-title">
							<?php echo esc_html( $store_title ); ?>
						</h3>
						<div id="mfm_qrcode" style="display: flex; justify-content: center;"></div>
						<p class="mfm-qr-scan-text">
							<?php esc_html_e( 'Scan to Order', 'lineweb-restaurant-orders' ); ?>
						</p>
					</div>

					<p class="description" style="margin-top: 20px; text-align: center;">
						<?php esc_html_e( 'Right-click the image to save as PNG.', 'lineweb-restaurant-orders' ); ?>
					</p>
				</div>
			</div>
		</div>

		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function () {
				var qrcode = new QRCode(document.getElementById("mfm_qrcode"), {
					width: 256,
					height: 256,
					colorDark: "<?php echo esc_js( $primary_color ); ?>",
					colorLight: "#ffffff",
					correctLevel: QRCode.CorrectLevel.H
				});

				function makeCode() {
					var elUrl = document.getElementById("mfm_qr_url");
					var elColor = document.getElementById("mfm_qr_color");
					var elSize = document.getElementById("mfm_qr_size");

					if (!elUrl.value) {
						return;
					}

					document.getElementById("mfm_qrcode").innerHTML = "";

					qrcode = new QRCode(document.getElementById("mfm_qrcode"), {
						text: elUrl.value,
						width: parseInt(elSize.value),
						height: parseInt(elSize.value),
						colorDark: elColor.value,
						colorLight: "#ffffff",
						correctLevel: QRCode.CorrectLevel.H
					});
				}

				document.getElementById("mfm_qr_url").addEventListener("keyup", makeCode);
				document.getElementById("mfm_qr_url").addEventListener("change", makeCode);
				document.getElementById("mfm_qr_color").addEventListener("change", makeCode);

				document.getElementById("mfm_qr_size").addEventListener("input", function (e) {
					document.getElementById("mfm_qr_size_val").innerText = e.target.value + "px";
					makeCode();
				});

				makeCode();

				document.getElementById("mfm_print_btn").addEventListener("click", function () {
					var printWindow = window.open('', '', 'height=600,width=800');
					var containerHtml = document.getElementById("mfm_qr_container").outerHTML;

					printWindow.document.write('<html><head><title>Print QR Code</title>');
					printWindow.document.write('<style>body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; } #mfm_qr_container { text-align: center; padding: 40px; border: 2px solid #000; border-radius: 20px; width: 100%; max-width: 400px; } h3 { font-size: 32px; margin-bottom: 30px; } img { margin: 0 auto; } </style>');
					printWindow.document.write('</head><body>');
					printWindow.document.write(containerHtml);
					printWindow.document.write('</body></html>');
					printWindow.document.close();
					printWindow.focus();
					setTimeout(function () {
						printWindow.print();
						printWindow.close();
					}, 500);
				});
			});
		</script>
		<?php
	}
}
