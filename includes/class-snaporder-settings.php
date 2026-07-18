<?php
/**
 * Settings: stores and renders all plugin configuration.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers, validates, and renders SnapOrder settings.
 */
class SnapOrder_Settings {

	/**
	 * Registers settings hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Adds the settings submenu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=food_item',
			__( 'Settings', 'lineweb-restaurant-orders' ),
			__( 'Settings', 'lineweb-restaurant-orders' ),
			'manage_options',
			'mfm-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers settings and their sanitizers.
	 */
	public function register_settings() {
		// Branding.
		register_setting( 'mfm_settings_group', 'mfm_brand_logo', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'mfm_settings_group', 'mfm_primary_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'mfm_settings_group', 'mfm_store_title', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mfm_settings_group', 'mfm_store_tagline', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		// Opening hours.
		register_setting( 'mfm_settings_group', 'mfm_opening_hours', array( 'sanitize_callback' => array( $this, 'sanitize_opening_hours' ) ) );
		// General.
		register_setting( 'mfm_settings_group', 'mfm_currency', array( 'sanitize_callback' => array( $this, 'sanitize_currency' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_enable_cod', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_dinein_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_delete_data_on_uninstall', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_order_retention_days', array( 'sanitize_callback' => array( $this, 'sanitize_retention_days' ) ) );
		// Social.
		register_setting( 'mfm_settings_group', 'mfm_facebook_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'mfm_settings_group', 'mfm_instagram_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'mfm_settings_group', 'mfm_twitter_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'mfm_settings_group', 'mfm_tiktok_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		// Stripe.
		register_setting( 'mfm_settings_group', 'mfm_enable_stripe', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_stripe_publishable_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mfm_settings_group', 'mfm_stripe_secret_key', array( 'sanitize_callback' => array( $this, 'sanitize_stripe_secret' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_stripe_webhook_secret', array( 'sanitize_callback' => array( $this, 'sanitize_webhook_secret' ) ) );
		// Tipping.
		register_setting( 'mfm_settings_group', 'mfm_tipping_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		// WhatsApp and Twilio.
		register_setting( 'mfm_settings_group', 'mfm_whatsapp_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_twilio_sid', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mfm_settings_group', 'mfm_twilio_token', array( 'sanitize_callback' => array( $this, 'sanitize_twilio_token' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_twilio_phone', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		// Progressive web app.
		register_setting( 'mfm_settings_group', 'mfm_pwa_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		register_setting( 'mfm_settings_group', 'mfm_pwa_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mfm_settings_group', 'mfm_pwa_short_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mfm_settings_group', 'mfm_pwa_theme_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
	}

	/**
	 * Normalizes a checkbox value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_checkbox( $value ) {
		return '1' === (string) $value ? '1' : '';
	}

	/**
	 * Restricts currency to supported two-decimal currencies.
	 *
	 * @param mixed $value Submitted currency.
	 * @return string
	 */
	public function sanitize_currency( $value ) {
		$value = strtoupper( sanitize_text_field( $value ) );
		return in_array( $value, array( 'EUR', 'USD', 'GBP', 'AUD', 'CAD' ), true ) ? $value : 'EUR';
	}

	/**
	 * Replaces the Stripe secret only when a new value is supplied.
	 *
	 * @param mixed $value Submitted secret.
	 * @return string
	 */
	public function sanitize_stripe_secret( $value ) {
		$value = sanitize_text_field( $value );
		return '' === $value ? get_option( 'mfm_stripe_secret_key', '' ) : $value;
	}

	/**
	 * Replaces the webhook secret only when supplied.
	 *
	 * @param mixed $value Submitted secret.
	 * @return string
	 */
	public function sanitize_webhook_secret( $value ) {
		$value = sanitize_text_field( $value );
		return '' === $value ? get_option( 'mfm_stripe_webhook_secret', '' ) : $value;
	}

	/**
	 * Replaces the Twilio token only when supplied.
	 *
	 * @param mixed $value Submitted token.
	 * @return string
	 */
	public function sanitize_twilio_token( $value ) {
		$value = sanitize_text_field( $value );
		return '' === $value ? get_option( 'mfm_twilio_token', '' ) : $value;
	}

	/**
	 * Bounds the personal-data retention period.
	 *
	 * @param mixed $value Submitted day count.
	 * @return int
	 */
	public function sanitize_retention_days( $value ) {
		$value = absint( $value );
		return min( $value, 3650 );
	}

	/**
	 * Validates the weekly opening-hours schedule.
	 *
	 * @param mixed $input Submitted schedule.
	 * @return array
	 */
	public function sanitize_opening_hours( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean        = array();
		$allowed_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		foreach ( $input as $day => $data ) {
			$day = sanitize_key( $day );
			if ( ! in_array( $day, $allowed_days, true ) || ! is_array( $data ) ) {
				continue;
			}
			$open          = isset( $data['open'] ) && is_string( $data['open'] ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $data['open'] ) ? $data['open'] : '09:00';
			$close         = isset( $data['close'] ) && is_string( $data['close'] ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $data['close'] ) ? $data['close'] : '22:00';
			$clean[ $day ] = array(
				'is_open' => isset( $data['is_open'] ) ? '1' : '0',
				'open'    => $open,
				'close'   => $close,
			);
		}
		return $clean;
	}

	/**
	 * Enqueues media and colour controls on the settings screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'food_item_page_mfm-settings' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'mfm-settings-script', SNAPORDER_PLUGIN_URL . 'assets/js/admin-settings.js', array( 'jquery', 'wp-color-picker' ), SNAPORDER_VERSION, true );
	}

	/**
	 * Renders the settings screen.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap mfm-wrap">
			<h1 class="mfm-page-title"><?php esc_html_e( 'Lineweb Restaurant Orders Settings', 'lineweb-restaurant-orders' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'mfm_settings_group' ); ?>

				<div class="mfm-settings-container">
					<!-- Tabs -->
					<div class="mfm-settings-tabs">
						<a href="#general" class="mfm-tab-link active">
							<span class="dashicons dashicons-admin-generic mfm-tab-icon"></span>
							<?php esc_html_e( 'General', 'lineweb-restaurant-orders' ); ?>
						</a>
						<a href="#branding" class="mfm-tab-link">
							<span class="dashicons dashicons-art mfm-tab-icon"></span>
							<?php esc_html_e( 'Branding', 'lineweb-restaurant-orders' ); ?>
						</a>
						<a href="#hours" class="mfm-tab-link">
							<span class="dashicons dashicons-clock mfm-tab-icon"></span>
							<?php esc_html_e( 'Opening Hours', 'lineweb-restaurant-orders' ); ?>
						</a>
						<a href="#payment" class="mfm-tab-link">
							<span class="dashicons dashicons-money mfm-tab-icon"></span>
							<?php esc_html_e( 'Payment', 'lineweb-restaurant-orders' ); ?>
						</a>
						<a href="#notifications" class="mfm-tab-link">
							<span class="dashicons dashicons-bell mfm-tab-icon"></span>
							<?php esc_html_e( 'Notifications', 'lineweb-restaurant-orders' ); ?>
						</a>
						<a href="#pwa" class="mfm-tab-link">
							<span class="dashicons dashicons-smartphone mfm-tab-icon"></span>
							<?php esc_html_e( 'PWA', 'lineweb-restaurant-orders' ); ?>
						</a>
					</div>

					<div class="mfm-settings-content">

						<!-- General -->
						<div id="general" class="mfm-settings-section active">
							<h2><?php esc_html_e( 'General Settings', 'lineweb-restaurant-orders' ); ?></h2>
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Store Title', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<input type="text" name="mfm_store_title" value="<?php echo esc_attr( get_option( 'mfm_store_title', 'My Restaurant' ) ); ?>" class="regular-text">
										<p class="description"><?php esc_html_e( 'Displayed in the app header and browser tab.', 'lineweb-restaurant-orders' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Store Tagline', 'lineweb-restaurant-orders' ); ?></th>
									<td><input type="text" name="mfm_store_tagline" value="<?php echo esc_attr( get_option( 'mfm_store_tagline' ) ); ?>" class="regular-text"></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Currency', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<select name="mfm_currency">
											<?php
											$currencies = array(
												'EUR' => '€ (EUR)',
												'USD' => '$ (USD)',
												'GBP' => '£ (GBP)',
												'AUD' => 'A$ (AUD)',
												'CAD' => 'C$ (CAD)',
											);
											$current    = get_option( 'mfm_currency', 'EUR' );
											foreach ( $currencies as $code => $label ) {
												echo '<option value="' . esc_attr( $code ) . '" ' . selected( $current, $code, false ) . '>' . esc_html( $label ) . '</option>';
											}
											?>
										</select>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Dine-In', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="mfm_dinein_enabled" value="1" <?php checked( get_option( 'mfm_dinein_enabled' ), '1' ); ?>>
											<?php esc_html_e( 'Enable Dine-In ordering (table number instead of delivery address)', 'lineweb-restaurant-orders' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Data removal', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="mfm_delete_data_on_uninstall" value="1" <?php checked( get_option( 'mfm_delete_data_on_uninstall' ), '1' ); ?>>
											<?php esc_html_e( 'Permanently delete Lineweb Restaurant Orders products, orders, settings, and statistics when the plugin is deleted.', 'lineweb-restaurant-orders' ); ?>
										</label>
										<p class="description"><?php esc_html_e( 'Leave this disabled to preserve business and order data.', 'lineweb-restaurant-orders' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><label for="mfm_order_retention_days"><?php esc_html_e( 'Order data retention', 'lineweb-restaurant-orders' ); ?></label></th>
									<td>
										<input type="number" id="mfm_order_retention_days" name="mfm_order_retention_days" value="<?php echo esc_attr( get_option( 'mfm_order_retention_days', 0 ) ); ?>" min="0" max="3650" class="small-text">
										<?php esc_html_e( 'days', 'lineweb-restaurant-orders' ); ?>
										<p class="description"><?php esc_html_e( 'Personal data in completed, rejected, and failed orders is anonymized after this period. Use 0 to retain it until manually removed.', 'lineweb-restaurant-orders' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Social Media', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<div style="display:flex;flex-direction:column;gap:8px;">
											<input type="url" name="mfm_facebook_url"  value="<?php echo esc_attr( get_option( 'mfm_facebook_url' ) ); ?>"  class="regular-text" placeholder="Facebook URL">
											<input type="url" name="mfm_instagram_url" value="<?php echo esc_attr( get_option( 'mfm_instagram_url' ) ); ?>" class="regular-text" placeholder="Instagram URL">
											<input type="url" name="mfm_twitter_url"   value="<?php echo esc_attr( get_option( 'mfm_twitter_url' ) ); ?>"   class="regular-text" placeholder="Twitter/X URL">
											<input type="url" name="mfm_tiktok_url"    value="<?php echo esc_attr( get_option( 'mfm_tiktok_url' ) ); ?>"    class="regular-text" placeholder="TikTok URL">
										</div>
									</td>
								</tr>
							</table>
						</div>

						<!-- Branding -->
						<div id="branding" class="mfm-settings-section">
							<h2><?php esc_html_e( 'Branding', 'lineweb-restaurant-orders' ); ?></h2>
							<?php
							$logo  = get_option( 'mfm_brand_logo' );
							$color = get_option( 'mfm_primary_color', '#f97316' );
							?>
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Brand Logo', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<input type="hidden" name="mfm_brand_logo" id="mfm_brand_logo" value="<?php echo esc_attr( $logo ); ?>">
										<div id="mfm-logo-preview" style="margin-bottom:15px;background:#f9f9f9;padding:20px;border-radius:8px;display:inline-block;">
											<?php
											if ( $logo ) :
												?>
												<img src="<?php echo esc_url( $logo ); ?>" style="max-width:150px;height:auto;">
												<?php
else :
	?>
												<span class="dashicons dashicons-format-image" style="font-size:48px;height:48px;width:48px;color:#ccc;"></span><?php endif; ?>
										</div><br>
										<button id="mfm-upload-logo" class="button"><?php esc_html_e( 'Upload Logo', 'lineweb-restaurant-orders' ); ?></button>
										<button id="mfm-remove-logo" class="button button-link-delete" style="<?php echo $logo ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'lineweb-restaurant-orders' ); ?></button>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Primary Colour', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<input type="text" name="mfm_primary_color" value="<?php echo esc_attr( $color ); ?>" class="mfm-color-field" data-default-color="#f97316">
										<p class="description"><?php esc_html_e( 'Used for buttons, links, and accents.', 'lineweb-restaurant-orders' ); ?></p>
									</td>
								</tr>
								<?php $this->render_pwa_branding_settings(); ?>
							</table>
						</div>

						<!-- Opening Hours -->
						<div id="hours" class="mfm-settings-section">
							<h2><?php esc_html_e( 'Store Opening Hours', 'lineweb-restaurant-orders' ); ?></h2>
							<p class="description" style="margin-bottom:20px;"><?php esc_html_e( 'Uncheck "Open" to mark a day as closed.', 'lineweb-restaurant-orders' ); ?></p>
							<?php
							$days         = array(
								'monday'    => 'Monday',
								'tuesday'   => 'Tuesday',
								'wednesday' => 'Wednesday',
								'thursday'  => 'Thursday',
								'friday'    => 'Friday',
								'saturday'  => 'Saturday',
								'sunday'    => 'Sunday',
							);
							$stored_hours = get_option( 'mfm_opening_hours', array() );
							?>
							<table class="widefat striped" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Day', 'lineweb-restaurant-orders' ); ?></th>
										<th style="text-align:center;"><?php esc_html_e( 'Open?', 'lineweb-restaurant-orders' ); ?></th>
										<th><?php esc_html_e( 'Open Time', 'lineweb-restaurant-orders' ); ?></th>
										<th><?php esc_html_e( 'Close Time', 'lineweb-restaurant-orders' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ( $days as $key => $label ) :
										$is_open    = isset( $stored_hours[ $key ]['is_open'] ) ? $stored_hours[ $key ]['is_open'] : '1';
										$open_time  = isset( $stored_hours[ $key ]['open'] ) ? $stored_hours[ $key ]['open'] : '09:00';
										$close_time = isset( $stored_hours[ $key ]['close'] ) ? $stored_hours[ $key ]['close'] : '22:00';
										?>
									<tr>
										<td style="font-weight:500;"><?php echo esc_html( $label ); ?></td>
										<td style="text-align:center;"><input type="checkbox" name="mfm_opening_hours[<?php echo esc_attr( $key ); ?>][is_open]" value="1" <?php checked( $is_open, '1' ); ?>></td>
										<td><input type="time" name="mfm_opening_hours[<?php echo esc_attr( $key ); ?>][open]"  value="<?php echo esc_attr( $open_time ); ?>"></td>
										<td><input type="time" name="mfm_opening_hours[<?php echo esc_attr( $key ); ?>][close]" value="<?php echo esc_attr( $close_time ); ?>"></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<!-- Payment -->
						<div id="payment" class="mfm-settings-section">
							<h2><?php esc_html_e( 'Payment Settings', 'lineweb-restaurant-orders' ); ?></h2>
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Cash on Delivery', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="mfm_enable_cod" value="1" <?php checked( get_option( 'mfm_enable_cod', '1' ), '1' ); ?>>
											<?php esc_html_e( 'Enable Cash on Delivery / Pay at Counter', 'lineweb-restaurant-orders' ); ?>
										</label>
									</td>
								</tr>

								<!-- Stripe Section -->
								<tr>
									<th colspan="2" style="padding-top:20px;">
										<h3 style="margin:0 0 5px;">
											<label>
												<input type="checkbox" name="mfm_enable_stripe" value="1" <?php checked( get_option( 'mfm_enable_stripe' ), '1' ); ?>>
												<?php esc_html_e( 'Enable Stripe Payments', 'lineweb-restaurant-orders' ); ?>
											</label>
										</h3>
									</th>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Publishable Key', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<input type="text" name="mfm_stripe_publishable_key" value="<?php echo esc_attr( get_option( 'mfm_stripe_publishable_key' ) ); ?>" class="regular-text" placeholder="pk_live_...">
											<p class="description"><?php esc_html_e( 'Keys may be stored in wp-config.php with SNAPORDER_STRIPE_PUBLISHABLE_KEY and SNAPORDER_STRIPE_SECRET.', 'lineweb-restaurant-orders' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Secret Key', 'lineweb-restaurant-orders' ); ?></th>
									<td>
											<input type="password" name="mfm_stripe_secret_key" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo get_option( 'mfm_stripe_secret_key' ) ? esc_attr__( 'Configured - enter a new key to replace it', 'lineweb-restaurant-orders' ) : 'sk_live_...'; ?>">
										</td>
									</tr>
									<tr>
										<th><?php esc_html_e( 'Webhook signing secret', 'lineweb-restaurant-orders' ); ?></th>
										<td>
											<input type="password" name="mfm_stripe_webhook_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo get_option( 'mfm_stripe_webhook_secret' ) ? esc_attr__( 'Configured - enter a new secret to replace it', 'lineweb-restaurant-orders' ) : 'whsec_...'; ?>">
											<p class="description">
												<?php
												printf(
													/* translators: %s: webhook URL. */
													esc_html__( 'Create a Stripe webhook for %s and subscribe to payment_intent.succeeded, payment_intent.payment_failed, and payment_intent.canceled.', 'lineweb-restaurant-orders' ),
													esc_html( rest_url( 'snaporder/v1/stripe/webhook' ) )
												);
												?>
											</p>
										</td>
								</tr>

								<!-- Tipping -->
								<tr>
									<th colspan="2" style="padding-top:20px;">
										<h3 style="margin:0 0 5px;">
											<label>
												<input type="checkbox" name="mfm_tipping_enabled" value="1" <?php checked( get_option( 'mfm_tipping_enabled' ), '1' ); ?>>
												<?php esc_html_e( 'Enable Tipping at Checkout', 'lineweb-restaurant-orders' ); ?>
											</label>
										</h3>
										<p class="description"><?php esc_html_e( 'Lets customers add a tip (5%, 10%, 15%, or custom) to their order.', 'lineweb-restaurant-orders' ); ?></p>
									</th>
								</tr>
							</table>
						</div>

						<!-- Notifications (WhatsApp) -->
						<div id="notifications" class="mfm-settings-section">
							<h2><?php esc_html_e( 'WhatsApp Notifications', 'lineweb-restaurant-orders' ); ?></h2>
							<p class="description" style="margin-bottom:20px;"><?php esc_html_e( 'Send automatic WhatsApp messages to customers via Twilio when orders are placed or updated.', 'lineweb-restaurant-orders' ); ?></p>
							<table class="form-table">
								<tr>
									<th colspan="2">
										<label>
											<input type="checkbox" name="mfm_whatsapp_enabled" value="1" <?php checked( get_option( 'mfm_whatsapp_enabled' ), '1' ); ?>>
											<?php esc_html_e( 'Enable WhatsApp Notifications (via Twilio)', 'lineweb-restaurant-orders' ); ?>
										</label>
									</th>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Twilio Account SID', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<input type="text" name="mfm_twilio_sid" value="<?php echo esc_attr( get_option( 'mfm_twilio_sid' ) ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'Tip: store credentials in wp-config.php as SNAPORDER_TWILIO_SID and SNAPORDER_TWILIO_TOKEN for better security.', 'lineweb-restaurant-orders' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Twilio Auth Token', 'lineweb-restaurant-orders' ); ?></th>
								<td><input type="password" name="mfm_twilio_token" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo get_option( 'mfm_twilio_token' ) ? esc_attr__( 'Configured - enter a new token to replace it', 'lineweb-restaurant-orders' ) : ''; ?>"></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Twilio Phone Number', 'lineweb-restaurant-orders' ); ?></th>
									<td>
										<input type="text" name="mfm_twilio_phone" value="<?php echo esc_attr( get_option( 'mfm_twilio_phone' ) ); ?>" class="regular-text" placeholder="whatsapp:+14155238886">
										<p class="description"><?php esc_html_e( 'Format: whatsapp:+1234567890. Use the Twilio Sandbox number for testing.', 'lineweb-restaurant-orders' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<!-- PWA -->
						<div id="pwa" class="mfm-settings-section">
							<h2><?php esc_html_e( 'Progressive Web App (PWA)', 'lineweb-restaurant-orders' ); ?></h2>
							<p class="description" style="margin-bottom:20px;"><?php esc_html_e( 'Allow customers to install the restaurant menu as an app on their phone.', 'lineweb-restaurant-orders' ); ?></p>
							<table class="form-table">
								<tr>
									<th colspan="2">
										<label>
											<input type="checkbox" name="mfm_pwa_enabled" value="1" <?php checked( get_option( 'mfm_pwa_enabled' ), '1' ); ?>>
											<?php esc_html_e( 'Enable PWA', 'lineweb-restaurant-orders' ); ?>
										</label>
									</th>
								</tr>
								<tr>
									<th><?php esc_html_e( 'App Name', 'lineweb-restaurant-orders' ); ?></th>
									<td><input type="text" name="mfm_pwa_name" value="<?php echo esc_attr( get_option( 'mfm_pwa_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text"></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Short Name', 'lineweb-restaurant-orders' ); ?></th>
									<td><input type="text" name="mfm_pwa_short_name" value="<?php echo esc_attr( get_option( 'mfm_pwa_short_name', 'Restaurant' ) ); ?>" class="regular-text"></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Theme Color', 'lineweb-restaurant-orders' ); ?></th>
									<td><input type="text" name="mfm_pwa_theme_color" value="<?php echo esc_attr( get_option( 'mfm_pwa_theme_color', '#10b981' ) ); ?>" class="regular-text" placeholder="#10b981"></td>
								</tr>
							</table>
						</div>

						<div style="margin-top:40px;padding-top:20px;border-top:1px solid #eee;">
							<?php submit_button( __( 'Save Changes', 'lineweb-restaurant-orders' ), 'primary mfm-btn-primary', 'submit', false ); ?>
						</div>
					</div>
				</div>
			</form>
			<div style="margin-top:16px;color:#646970;font-size:13px;">
				<p style="margin:0;">
					<?php esc_html_e( 'Source by', 'lineweb-restaurant-orders' ); ?>
					<a href="https://www.lineweb.gr/" target="_blank" rel="noopener noreferrer">Andrew Matia / Lineweb</a>.
					<?php esc_html_e( 'For custom restaurant integrations or tailored workflows, visit', 'lineweb-restaurant-orders' ); ?>
					<a href="https://www.lineweb.gr/" target="_blank" rel="noopener noreferrer">www.lineweb.gr</a>.
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a small PWA row inside the Branding tab (logo URL used as icon).
	 */
	private function render_pwa_branding_settings() {
		// intentionally empty – PWA settings live in the dedicated PWA tab.
	}

	/**
	 * Return the currency symbol for the configured currency.
	 * Accepts uppercase OR lowercase codes (EUR / eur).
	 *
	 * @param string|null $currency Optional currency code.
	 * @return string
	 */
	public static function get_currency_symbol( $currency = null ) {
		if ( ! $currency ) {
			$currency = get_option( 'mfm_currency', 'EUR' );
		}
		switch ( strtoupper( $currency ) ) {
			case 'EUR':
				return '€';
			case 'GBP':
				return '£';
			case 'USD':
				return '$';
			case 'AUD':
				return 'A$';
			case 'CAD':
				return 'C$';
			default:
				return '€';
		}
	}

	/**
	 * Gets the validated ISO currency code.
	 *
	 * @return string
	 */
	public static function get_currency_code() {
		$currency = strtoupper( (string) get_option( 'mfm_currency', 'EUR' ) );
		return in_array( $currency, array( 'EUR', 'USD', 'GBP', 'AUD', 'CAD' ), true ) ? $currency : 'EUR';
	}

	/**
	 * Gets the Stripe publishable key.
	 *
	 * @return string
	 */
	public static function get_stripe_publishable_key() {
		if ( defined( 'SNAPORDER_STRIPE_PUBLISHABLE_KEY' ) ) {
			return SNAPORDER_STRIPE_PUBLISHABLE_KEY;
		}
		return get_option( 'mfm_stripe_publishable_key', '' );
	}

	/**
	 * Retrieve Stripe secret key, preferring a wp-config.php constant over the DB.
	 */
	public static function get_stripe_secret() {
		if ( defined( 'SNAPORDER_STRIPE_SECRET' ) ) {
			return SNAPORDER_STRIPE_SECRET;
		}
		if ( defined( 'MFM_STRIPE_SECRET' ) ) {
			return MFM_STRIPE_SECRET; // Legacy constant.
		}
		return get_option( 'mfm_stripe_secret_key', '' );
	}

	/**
	 * Gets the Stripe webhook signing secret.
	 *
	 * @return string
	 */
	public static function get_stripe_webhook_secret() {
		if ( defined( 'SNAPORDER_STRIPE_WEBHOOK_SECRET' ) ) {
			return SNAPORDER_STRIPE_WEBHOOK_SECRET;
		}
		return get_option( 'mfm_stripe_webhook_secret', '' );
	}

	/**
	 * Gets configured payment methods that are ready for checkout.
	 *
	 * @return string[]
	 */
	public static function get_enabled_payment_methods() {
		$methods = array();

		$publishable_key = self::get_stripe_publishable_key();
		$secret_key      = self::get_stripe_secret();
		$webhook_secret  = self::get_stripe_webhook_secret();
		if (
			'1' === get_option( 'mfm_enable_stripe' ) &&
			preg_match( '/^pk_(?:test|live)_[A-Za-z0-9]+$/', $publishable_key ) &&
			preg_match( '/^sk_(?:test|live)_[A-Za-z0-9]+$/', $secret_key ) &&
			preg_match( '/^whsec_[A-Za-z0-9]+$/', $webhook_secret )
		) {
			$methods[] = 'stripe';
		}
		if ( '1' === get_option( 'mfm_enable_cod', '1' ) ) {
			$methods[] = 'cod';
		}

		return $methods;
	}

	/**
	 * Retrieve Twilio credentials, preferring wp-config.php constants over the DB.
	 */
	public static function get_twilio_credentials() {
		return array(
			'sid'   => defined( 'SNAPORDER_TWILIO_SID' ) ? SNAPORDER_TWILIO_SID : ( defined( 'MFM_TWILIO_SID' ) ? MFM_TWILIO_SID : get_option( 'mfm_twilio_sid', '' ) ),
			'token' => defined( 'SNAPORDER_TWILIO_TOKEN' ) ? SNAPORDER_TWILIO_TOKEN : ( defined( 'MFM_TWILIO_TOKEN' ) ? MFM_TWILIO_TOKEN : get_option( 'mfm_twilio_token', '' ) ),
			'phone' => get_option( 'mfm_twilio_phone', '' ),
		);
	}

	/**
	 * Check whether the store is currently open, using WP timezone.
	 *
	 * @return bool
	 */
	public static function is_store_open() {
		$opening_hours = get_option( 'mfm_opening_hours', array() );
		if ( empty( $opening_hours ) ) {
			return true; // No hours configured means always open.
		}

		$days         = array(
			1 => 'monday',
			2 => 'tuesday',
			3 => 'wednesday',
			4 => 'thursday',
			5 => 'friday',
			6 => 'saturday',
			7 => 'sunday',
		);
		$day_number   = (int) wp_date( 'N' );
		$current_day  = $days[ $day_number ];
		$previous_day = $days[ 1 === $day_number ? 7 : $day_number - 1 ];
		$current_time = wp_date( 'H:i' );

		return self::schedule_is_open( $opening_hours, $current_day, $previous_day, $current_time );
	}

	/**
	 * Evaluate normal and overnight opening intervals.
	 *
	 * @param array  $opening_hours Configured weekly schedule.
	 * @param string $current_day   Lowercase current weekday.
	 * @param string $previous_day  Lowercase previous weekday.
	 * @param string $current_time  Current 24-hour time in H:i format.
	 * @return bool
	 */
	public static function schedule_is_open( $opening_hours, $current_day, $previous_day, $current_time ) {
		$previous       = isset( $opening_hours[ $previous_day ] ) && is_array( $opening_hours[ $previous_day ] )
			? $opening_hours[ $previous_day ]
			: array();
		$previous_open  = isset( $previous['open'] ) ? $previous['open'] : '';
		$previous_close = isset( $previous['close'] ) ? $previous['close'] : '';

		if (
			'1' === ( $previous['is_open'] ?? '' ) &&
			self::is_valid_schedule_time( $previous_open ) &&
			self::is_valid_schedule_time( $previous_close ) &&
			$previous_open > $previous_close &&
			$current_time <= $previous_close
		) {
			return true;
		}

		if ( ! isset( $opening_hours[ $current_day ] ) || ! is_array( $opening_hours[ $current_day ] ) ) {
			return true;
		}

		$current = $opening_hours[ $current_day ];
		if ( '1' !== ( $current['is_open'] ?? '' ) ) {
			return false;
		}

		$open  = isset( $current['open'] ) ? $current['open'] : '09:00';
		$close = isset( $current['close'] ) ? $current['close'] : '22:00';
		if ( ! self::is_valid_schedule_time( $open ) || ! self::is_valid_schedule_time( $close ) ) {
			return false;
		}

		if ( $open === $close ) {
			return true;
		}

		return $open < $close
			? $current_time >= $open && $current_time <= $close
			: $current_time >= $open;
	}

	/**
	 * Validate one H:i schedule value.
	 *
	 * @param mixed $time Candidate time.
	 * @return bool
	 */
	private static function is_valid_schedule_time( $time ) {
		return is_string( $time ) && 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
	}
}
