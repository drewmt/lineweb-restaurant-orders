<?php
/**
 * Order registration, AJAX handlers, and admin columns.
 *
 * Security improvements over original:
 *  - Order token system prevents enumeration of order IDs on status checks.
 *  - Rate limiting (10 submissions per IP per hour) via transients.
 *  - Nonce + capability check on ajax_get_order_details.
 *  - wp_date() used instead of date() to respect WP timezone.
 *  - Removed duplicate variable assignments.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles order creation, payment state, tracking, and administration.
 */
class SnapOrder_Orders {

	/**
	 * Registers order hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_order_cpt' ) );
		add_action( 'wp_ajax_mfm_submit_order', array( $this, 'handle_order_submission' ) );
		add_action( 'wp_ajax_nopriv_mfm_submit_order', array( $this, 'handle_order_submission' ) );
		add_action( 'wp_ajax_mfm_check_status', array( $this, 'handle_check_status' ) );
		add_action( 'wp_ajax_nopriv_mfm_check_status', array( $this, 'handle_check_status' ) );
		add_action( 'wp_ajax_mfm_confirm_stripe_payment', array( $this, 'handle_confirm_stripe_payment' ) );
		add_action( 'wp_ajax_nopriv_mfm_confirm_stripe_payment', array( $this, 'handle_confirm_stripe_payment' ) );
		add_action( 'snaporder_stripe_payment_succeeded', array( $this, 'complete_stripe_order' ), 10, 2 );
		add_action( 'snaporder_stripe_payment_failed', array( $this, 'fail_stripe_order' ), 10, 2 );
		add_action( 'snaporder_cleanup_request_lock', array( $this, 'cleanup_request_lock' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_order_meta_boxes' ) );

		add_filter( 'manage_mfm_order_posts_columns', array( $this, 'add_order_columns' ) );
		add_action( 'manage_mfm_order_posts_custom_column', array( $this, 'render_order_columns' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_pending_orders_bubble' ), 999 );
		add_action( 'admin_menu', array( $this, 'add_statistics_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_orders_management_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_to_custom_orders_page' ) );

		add_action( 'wp_ajax_mfm_get_order_details', array( $this, 'ajax_get_order_details' ) );
		add_action( 'wp_ajax_mfm_update_order_status', array( $this, 'ajax_update_order_status' ) );
		add_action( 'wp_ajax_mfm_delete_order', array( $this, 'ajax_delete_order' ) );
	}

	/**
	 * Registers the private order post type.
	 */
	public function register_order_cpt() {
		$labels = array(
			'name'               => _x( 'Orders', 'post type general name', 'snaporder' ),
			'singular_name'      => _x( 'Order', 'post type singular name', 'snaporder' ),
			'menu_name'          => _x( 'Orders', 'admin menu', 'snaporder' ),
			'name_admin_bar'     => _x( 'Order', 'add new on admin bar', 'snaporder' ),
			'add_new'            => _x( 'Add New', 'order', 'snaporder' ),
			'add_new_item'       => __( 'Add New Order', 'snaporder' ),
			'new_item'           => __( 'New Order', 'snaporder' ),
			'edit_item'          => __( 'Edit Order', 'snaporder' ),
			'view_item'          => __( 'View Order', 'snaporder' ),
			'all_items'          => __( 'Manage Orders', 'snaporder' ),
			'search_items'       => __( 'Search Orders', 'snaporder' ),
			'not_found'          => __( 'No orders found.', 'snaporder' ),
			'not_found_in_trash' => __( 'No orders found in Trash.', 'snaporder' ),
		);

		register_post_type(
			'mfm_order',
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => true,
				'rewrite'            => array( 'slug' => 'mfm-order' ),
				'capability_type'    => 'post',
				'capabilities'       => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => null,
				'menu_icon'          => 'dashicons-cart',
				'supports'           => array( 'title' ),
			)
		);
	}

	/**
	 * Adds the custom order-management page.
	 */
	public function add_orders_management_page() {
		global $submenu;
		if ( isset( $submenu['edit.php?post_type=mfm_order'] ) ) {
			unset( $submenu['edit.php?post_type=mfm_order'][5] );
		}
		add_submenu_page(
			'edit.php?post_type=mfm_order',
			__( 'Manage Orders', 'snaporder' ),
			__( 'Manage Orders', 'snaporder' ),
			'manage_options',
			'mfm-manage-orders',
			array( $this, 'render_orders_management_page' ),
			0
		);
	}

	/**
	 * Redirects the default order list to the custom management screen.
	 */
	public function redirect_to_custom_orders_page() {
		global $pagenow;
		// Read-only routing check in wp-admin; no state is changed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && 'mfm_order' === $_GET['post_type'] && ! isset( $_GET['page'] ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=mfm_order&page=mfm-manage-orders' ) );
			exit;
		}
	}

	/**
	 * Adds the pending-order count to the admin menu.
	 */
	public function add_pending_orders_bubble() {
		global $menu, $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin menu badge must reflect current actionable orders.
		$pending_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'mfm_order'
			   AND p.post_status = 'publish'
			   AND pm.meta_key = '_mfm_order_status'
			   AND pm.meta_value = 'pending'"
		);
		if ( $pending_count > 0 ) {
			foreach ( $menu as $key => $item ) {
				if ( 'edit.php?post_type=mfm_order' === $item[2] ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress requires updating its global admin-menu array.
					$menu[ $key ][0] .= ' <span class="update-plugins count-' . $pending_count . '"><span class="plugin-count">' . number_format_i18n( $pending_count ) . '</span></span>';
					break;
				}
			}
		}
	}

	/**
	 * Adds the order statistics submenu.
	 */
	public function add_statistics_menu() {
		add_submenu_page(
			'edit.php?post_type=mfm_order',
			__( 'Order Statistics', 'snaporder' ),
			__( 'Statistics', 'snaporder' ),
			'manage_options',
			'mfm-order-statistics',
			array( $this, 'render_statistics_page' )
		);
	}

	/**
	 * Handle new order submission.
	 *
	 * Rate limited: 10 orders per IP per hour.
	 * Generates an opaque order token to avoid enumeration on status checks.
	 */
	public function handle_order_submission() {
		check_ajax_referer( 'snaporder_order_nonce', 'nonce' );

		// Rate limiting — 10 orders per IP per hour.
		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rate_key  = 'mfm_rate_' . substr( wp_hash( wp_privacy_anonymize_ip( $remote_ip ) ), 0, 32 );
		$attempts  = (int) get_transient( $rate_key );
		if ( $attempts >= 10 ) {
			wp_send_json_error( array( 'message' => __( 'Too many orders submitted. Please wait before trying again.', 'snaporder' ) ) );
		}
		set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );

		// Opening hours check using WP timezone-aware helper.
		if ( ! SnapOrder_Settings::is_store_open() ) {
			wp_send_json_error(
				array(
					'message' => __( 'We are currently closed. Please order during opening hours.', 'snaporder' ),
				)
			);
		}

		$delivery_type = $this->limited_text_field( 'deliveryType', 20 );
		$payment       = $this->limited_text_field( 'payment', 20 );
		$request_id    = $this->limited_text_field( 'request_id', 64 );

		$allowed_delivery_types = array( 'delivery', 'pickup' );
		if ( '1' === get_option( 'mfm_dinein_enabled' ) ) {
			$allowed_delivery_types[] = 'dinein';
		}
		if ( ! in_array( $delivery_type, $allowed_delivery_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'The selected order type is unavailable.', 'snaporder' ) ), 400 );
		}

		if ( ! in_array( $payment, SnapOrder_Settings::get_enabled_payment_methods(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'The selected payment method is unavailable.', 'snaporder' ) ), 400 );
		}

		if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $request_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The order request is invalid. Please refresh and try again.', 'snaporder' ) ), 400 );
		}

		$customer = $this->validate_customer_fields( $delivery_type );
		if ( is_wp_error( $customer ) ) {
			wp_send_json_error( array( 'message' => $customer->get_error_message() ), 400 );
		}

		// Raw JSON is unslashed, bounded, decoded, and validated by the calculator.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_cart_json = isset( $_POST['cart'] ) ? wp_unslash( $_POST['cart'] ) : '[]';
		if ( ! is_string( $raw_cart_json ) || strlen( $raw_cart_json ) > 65535 ) {
			wp_send_json_error( array( 'message' => __( 'The cart payload is too large.', 'snaporder' ) ), 400 );
		}

		$raw_cart = json_decode( $raw_cart_json, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			wp_send_json_error( array( 'message' => __( 'The cart payload is invalid.', 'snaporder' ) ), 400 );
		}

		$calculator  = new SnapOrder_Order_Calculator();
		$calculation = $calculator->calculate(
			$raw_cart,
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated as decimal money by the calculator.
			isset( $_POST['tip_amount'] ) ? wp_unslash( $_POST['tip_amount'] ) : '0',
			'1' === get_option( 'mfm_tipping_enabled' )
		);
		if ( is_wp_error( $calculation ) ) {
			wp_send_json_error( array( 'message' => $calculation->get_error_message() ), 400 );
		}

		$lock_name = 'snaporder_request_' . hash( 'sha256', $request_id );
		$lock      = get_option( $lock_name );
		if ( is_array( $lock ) && ! empty( $lock['order_id'] ) ) {
			$this->send_existing_order_response( (int) $lock['order_id'] );
		}
		if ( is_array( $lock ) && ! empty( $lock['expires'] ) && (int) $lock['expires'] < time() ) {
			delete_option( $lock_name );
		}
		if ( ! add_option(
			$lock_name,
			array(
				'state'   => 'processing',
				'expires' => time() + 60,
			),
			'',
			false
		) ) {
			wp_send_json_error( array( 'message' => __( 'This order is already being processed. Please wait a moment.', 'snaporder' ) ), 409 );
		}

		$name         = $customer['name'];
		$phone        = $customer['phone'];
		$table_number = $customer['table_number'];
		$street       = $customer['street'];
		$house_number = $customer['house_number'];
		$city         = $customer['city'];
		$zip          = $customer['zip'];
		$order_notes  = $customer['order_notes'];
		$cart         = $calculation['items'];
		$tip_amount   = $calculation['tip'];
		$total        = $calculation['total'];

		if ( 'dinein' === $delivery_type ) {
			$post_title = sprintf( 'Order #%s - Table %s', wp_date( 'Ymd-His' ), $table_number );
		} else {
			$post_title = sprintf( 'Order #%s - %s', wp_date( 'Ymd-His' ), $name );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $post_title,
				'post_type'   => 'mfm_order',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			delete_option( $lock_name );
			wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'snaporder' ) ) );
		}

		// Generate opaque token to allow status polling without ID enumeration.
		$order_token = wp_generate_password( 32, false, false );

		update_post_meta( $post_id, '_mfm_customer_name', $name );
		update_post_meta( $post_id, '_mfm_customer_phone', $phone );
		update_post_meta( $post_id, '_mfm_delivery_type', $delivery_type );
		update_post_meta( $post_id, '_mfm_table_number', $table_number );
		update_post_meta( $post_id, '_mfm_tip_amount', $tip_amount );
		update_post_meta( $post_id, '_mfm_address', trim( $street . ' ' . $house_number ) );
		update_post_meta( $post_id, '_mfm_street', $street );
		update_post_meta( $post_id, '_mfm_house_number', $house_number );
		update_post_meta( $post_id, '_mfm_city', $city );
		update_post_meta( $post_id, '_mfm_zip', $zip );
		update_post_meta( $post_id, '_mfm_payment_method', $payment );
		update_post_meta( $post_id, '_mfm_order_total', $total );
		update_post_meta( $post_id, '_mfm_cart_items', $cart );
		update_post_meta( $post_id, '_mfm_order_status', 'stripe' === $payment ? 'awaiting_payment' : 'pending' );
		update_post_meta( $post_id, '_mfm_order_notes', $order_notes );
		update_post_meta( $post_id, '_mfm_order_token', $order_token );
		update_post_meta( $post_id, '_snaporder_subtotal_cents', $calculation['subtotal_cents'] );
		update_post_meta( $post_id, '_snaporder_tip_cents', $calculation['tip_cents'] );
		update_post_meta( $post_id, '_snaporder_order_total_cents', $calculation['total_cents'] );
		update_post_meta( $post_id, '_snaporder_currency', SnapOrder_Settings::get_currency_code() );
		update_post_meta( $post_id, '_snaporder_request_id', $request_id );

		update_option(
			$lock_name,
			array(
				'order_id' => $post_id,
				'expires'  => time() + DAY_IN_SECONDS,
			),
			false
		);
		wp_schedule_single_event( time() + DAY_IN_SECONDS, 'snaporder_cleanup_request_lock', array( $lock_name ) );

		if ( 'cod' === $payment ) {
			update_post_meta( $post_id, '_mfm_payment_status', 'pending' );
			$this->notify_new_order( $post_id );
			wp_send_json_success(
				array(
					'message'  => __( 'Order placed successfully!', 'snaporder' ),
					'order_id' => $post_id,
					'token'    => $order_token,
				)
			);
		}

		$gateway = new SnapOrder_Stripe_Gateway();
		$intent  = $gateway->create_payment_intent(
			$post_id,
			$calculation['total_cents'],
			SnapOrder_Settings::get_currency_code(),
			$request_id
		);
		if ( is_wp_error( $intent ) ) {
			delete_option( $lock_name );
			wp_delete_post( $post_id, true );
			wp_send_json_error( array( 'message' => $intent->get_error_message() ), 502 );
		}

		update_post_meta( $post_id, '_mfm_payment_status', 'pending' );
		update_post_meta( $post_id, '_snaporder_stripe_intent_id', $intent['id'] );
		update_post_meta( $post_id, '_snaporder_stripe_client_secret', $intent['client_secret'] );

		wp_send_json_success(
			array(
				'payment_required' => true,
				'client_secret'    => $intent['client_secret'],
				'order_id'         => $post_id,
				'token'            => $order_token,
			)
		);
	}

	/**
	 * Validates customer fields required by the selected order type.
	 *
	 * @param string $delivery_type Delivery, pickup, or dine-in.
	 * @return array|WP_Error
	 */
	private function validate_customer_fields( $delivery_type ) {
		// The public handler verifies the order nonce before calling this method.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data = array(
			'name'         => $this->limited_text_field( 'name', 100 ),
			'phone'        => $this->limited_text_field( 'phone', 30 ),
			'table_number' => $this->limited_text_field( 'table_number', 10 ),
			'street'       => $this->limited_text_field( 'street', 120 ),
			'house_number' => $this->limited_text_field( 'number', 20 ),
			'city'         => $this->limited_text_field( 'city', 100 ),
			'zip'          => $this->limited_text_field( 'zip', 20 ),
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public handler verifies the nonce before calling this method.
			'order_notes'  => isset( $_POST['order_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ) ) : '',
		);

		$data['order_notes'] = function_exists( 'mb_substr' ) ? mb_substr( $data['order_notes'], 0, 500 ) : substr( $data['order_notes'], 0, 500 );

		if ( 'dinein' === $delivery_type ) {
			$table_number = (int) $data['table_number'];
			if ( $table_number < 1 || $table_number > 9999 ) {
				return new WP_Error( 'snaporder_invalid_table', __( 'Please enter a valid table number.', 'snaporder' ) );
			}
			$data['table_number'] = (string) $table_number;
			return $data;
		}

		if ( '' === $data['name'] || ! preg_match( '/^[0-9+() .-]{6,30}$/', $data['phone'] ) ) {
			return new WP_Error( 'snaporder_invalid_contact', __( 'Please enter a valid name and phone number.', 'snaporder' ) );
		}

		if ( 'delivery' === $delivery_type && ( '' === $data['street'] || '' === $data['house_number'] || '' === $data['city'] || '' === $data['zip'] ) ) {
			return new WP_Error( 'snaporder_invalid_address', __( 'Please complete the delivery address.', 'snaporder' ) );
		}

		return $data;
	}

	/**
	 * Reads, sanitizes, and bounds one submitted text field.
	 *
	 * @param string $key        Form field key.
	 * @param int    $max_length Maximum character length.
	 * @return string
	 */
	private function limited_text_field( $key, $max_length ) {
		// All callers verify either the public order nonce or an admin nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	/**
	 * Returns the prior result for an idempotent request retry.
	 *
	 * @param int $order_id Order post ID.
	 */
	private function send_existing_order_response( $order_id ) {
		if ( 'mfm_order' !== get_post_type( $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The existing order could not be resumed.', 'snaporder' ) ), 409 );
		}

		$token          = (string) get_post_meta( $order_id, '_mfm_order_token', true );
		$payment_method = (string) get_post_meta( $order_id, '_mfm_payment_method', true );
		$payment_status = (string) get_post_meta( $order_id, '_mfm_payment_status', true );

		if ( 'stripe' === $payment_method && 'paid' !== $payment_status ) {
			wp_send_json_success(
				array(
					'payment_required' => true,
					'client_secret'    => (string) get_post_meta( $order_id, '_snaporder_stripe_client_secret', true ),
					'order_id'         => $order_id,
					'token'            => $token,
				)
			);
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Order placed successfully!', 'snaporder' ),
				'order_id' => $order_id,
				'token'    => $token,
			)
		);
	}

	/**
	 * Removes an expired request-id lock.
	 *
	 * @param string $lock_name Option name to remove.
	 */
	public function cleanup_request_lock( $lock_name ) {
		if ( is_string( $lock_name ) && 0 === strpos( $lock_name, 'snaporder_request_' ) ) {
			delete_option( $lock_name );
		}
	}

	/**
	 * Verifies a client-confirmed Stripe payment with Stripe's API.
	 */
	public function handle_confirm_stripe_payment() {
		check_ajax_referer( 'snaporder_order_nonce', 'nonce' );

		$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$token     = $this->limited_text_field( 'token', 64 );
		$intent_id = $this->limited_text_field( 'payment_intent', 128 );

		if ( ! $order_id || 'mfm_order' !== get_post_type( $order_id ) || ! $this->order_token_matches( $order_id, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'The order could not be verified.', 'snaporder' ) ), 403 );
		}

		$gateway = new SnapOrder_Stripe_Gateway();
		$intent  = $gateway->verify_order_payment( $order_id, $intent_id );
		if ( is_wp_error( $intent ) ) {
			wp_send_json_error( array( 'message' => $intent->get_error_message() ), 402 );
		}

		$this->complete_stripe_order( $order_id, $intent );
		wp_send_json_success(
			array(
				'message'  => __( 'Payment confirmed and order placed!', 'snaporder' ),
				'order_id' => $order_id,
				'token'    => $token,
			)
		);
	}

	/**
	 * Marks a verified Stripe order as paid and actionable.
	 *
	 * @param int   $order_id Order post ID.
	 * @param array $intent   Verified Stripe PaymentIntent.
	 */
	public function complete_stripe_order( $order_id, $intent ) {
		$expected_intent = (string) get_post_meta( $order_id, '_snaporder_stripe_intent_id', true );
		$received_intent = is_array( $intent ) && isset( $intent['id'] ) ? (string) $intent['id'] : '';
		if (
			'mfm_order' !== get_post_type( $order_id ) ||
			'stripe' !== get_post_meta( $order_id, '_mfm_payment_method', true ) ||
			'paid' === get_post_meta( $order_id, '_mfm_payment_status', true ) ||
			'' === $expected_intent ||
			'' === $received_intent ||
			! hash_equals( $expected_intent, $received_intent )
		) {
			return;
		}

		update_post_meta( $order_id, '_mfm_transaction_id', sanitize_text_field( $received_intent ) );
		update_post_meta( $order_id, '_mfm_payment_status', 'paid' );
		update_post_meta( $order_id, '_mfm_order_status', 'pending' );
		delete_post_meta( $order_id, '_snaporder_stripe_client_secret' );
		$this->notify_new_order( $order_id );
	}

	/**
	 * Marks a matching failed Stripe payment and releases its retry lock.
	 *
	 * @param int   $order_id Order post ID.
	 * @param array $intent   Signed Stripe PaymentIntent payload.
	 */
	public function fail_stripe_order( $order_id, $intent ) {
		$expected_intent = (string) get_post_meta( $order_id, '_snaporder_stripe_intent_id', true );
		$received_intent = is_array( $intent ) && isset( $intent['id'] ) ? (string) $intent['id'] : '';
		if (
			'mfm_order' === get_post_type( $order_id ) &&
			'stripe' === get_post_meta( $order_id, '_mfm_payment_method', true ) &&
			'paid' !== get_post_meta( $order_id, '_mfm_payment_status', true ) &&
			'' !== $expected_intent &&
			'' !== $received_intent &&
			hash_equals( $expected_intent, $received_intent )
		) {
			update_post_meta( $order_id, '_mfm_payment_status', 'failed' );
			update_post_meta( $order_id, '_mfm_order_status', 'payment_failed' );
			delete_post_meta( $order_id, '_snaporder_stripe_client_secret' );

			$request_id = (string) get_post_meta( $order_id, '_snaporder_request_id', true );
			if ( preg_match( '/^[a-f0-9-]{36}$/i', $request_id ) ) {
				delete_option( 'snaporder_request_' . hash( 'sha256', $request_id ) );
			}
		}
	}

	/**
	 * Emits a new-order event once for an actionable order.
	 *
	 * @param int $order_id Order post ID.
	 */
	private function notify_new_order( $order_id ) {
		if ( ! add_post_meta( $order_id, '_snaporder_new_order_notified', wp_date( 'c' ), true ) ) {
			return;
		}

		do_action(
			'snaporder_new_order_placed',
			$order_id,
			array(
				'name'  => get_post_meta( $order_id, '_mfm_customer_name', true ),
				'phone' => get_post_meta( $order_id, '_mfm_customer_phone', true ),
				'total' => get_post_meta( $order_id, '_mfm_order_total', true ),
				'items' => get_post_meta( $order_id, '_mfm_cart_items', true ),
			)
		);
	}

	/**
	 * Compares an order tracking token in constant time.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $token    Submitted opaque token.
	 * @return bool
	 */
	private function order_token_matches( $order_id, $token ) {
		$stored_token = (string) get_post_meta( $order_id, '_mfm_order_token', true );
		return '' !== $stored_token && '' !== $token && hash_equals( $stored_token, $token );
	}

	/**
	 * Status check — requires the opaque order token issued at order creation.
	 */
	public function handle_check_status() {
		check_ajax_referer( 'snaporder_order_nonce', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		if ( ! $order_id || ! $token || 'mfm_order' !== get_post_type( $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'snaporder' ) ) );
		}

		$stored_token = get_post_meta( $order_id, '_mfm_order_token', true );

		// Constant-time comparison to prevent timing attacks.
		if ( ! hash_equals( (string) $stored_token, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order token.', 'snaporder' ) ) );
		}

		$status = get_post_meta( $order_id, '_mfm_order_status', true );
		$status = $status ? $status : 'pending';
		$cart   = get_post_meta( $order_id, '_mfm_cart_items', true );
		$items  = array();
		foreach ( is_array( $cart ) ? $cart : array() as $item ) {
			$items[] = array(
				'title'      => isset( $item['title'] ) ? (string) $item['title'] : '',
				'qty'        => isset( $item['qty'] ) ? (int) $item['qty'] : 0,
				'line_total' => isset( $item['line_total'] ) ? (string) $item['line_total'] : number_format( (float) ( $item['price'] ?? 0 ) * (int) ( $item['qty'] ?? 0 ), 2, '.', '' ),
			);
		}

		wp_send_json_success(
			array(
				'status'   => $status,
				'items'    => $items,
				'total'    => (string) get_post_meta( $order_id, '_mfm_order_total', true ),
				'currency' => SnapOrder_Settings::get_currency_symbol( get_post_meta( $order_id, '_snaporder_currency', true ) ),
			)
		);
	}

	/**
	 * Returns an escaped order-details fragment to administrators.
	 */
	public function ajax_get_order_details() {
		check_ajax_referer( 'mfm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snaporder' ) ) );
		}

		$order_id = intval( $_POST['order_id'] ?? 0 );

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID', 'snaporder' ) ) );
		}

		$order = get_post( $order_id );
		if ( ! $order || 'mfm_order' !== $order->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Order not found', 'snaporder' ) ) );
		}

		$customer_name  = get_post_meta( $order_id, '_mfm_customer_name', true );
		$customer_name  = $customer_name ? $customer_name : __( 'Guest', 'snaporder' );
		$customer_phone = get_post_meta( $order_id, '_mfm_customer_phone', true );
		$delivery_type  = get_post_meta( $order_id, '_mfm_delivery_type', true );
		$table_number   = get_post_meta( $order_id, '_mfm_table_number', true );
		$tip_amount     = get_post_meta( $order_id, '_mfm_tip_amount', true );
		$address        = get_post_meta( $order_id, '_mfm_address', true );
		$city           = get_post_meta( $order_id, '_mfm_city', true );
		$zip            = get_post_meta( $order_id, '_mfm_zip', true );
		$payment        = get_post_meta( $order_id, '_mfm_payment_method', true );
		$total          = get_post_meta( $order_id, '_mfm_order_total', true );
		$cart           = get_post_meta( $order_id, '_mfm_cart_items', true );
		$status         = get_post_meta( $order_id, '_mfm_order_status', true );
		$status         = $status ? $status : 'pending';
		$order_notes    = get_post_meta( $order_id, '_mfm_order_notes', true );

		ob_start();
		?>
		<div class="mfm-modal-header">
			<h2 class="mfm-modal-title"><?php echo esc_html( $order->post_title ); ?></h2>
			<button onclick="closeOrderModal()" class="mfm-modal-close">&times; <?php esc_html_e( 'Close', 'snaporder' ); ?></button>
		</div>
		<div class="mfm-modal-grid">
			<div>
				<h3 class="mfm-modal-section-title"><?php esc_html_e( 'Customer Info', 'snaporder' ); ?></h3>
				<p class="mfm-info-row"><strong><?php esc_html_e( 'Name:', 'snaporder' ); ?></strong> <?php echo esc_html( $customer_name ); ?></p>
				<p class="mfm-info-row"><strong><?php esc_html_e( 'Phone:', 'snaporder' ); ?></strong> <a href="tel:<?php echo esc_attr( $customer_phone ); ?>" class="mfm-phone-link"><?php echo esc_html( $customer_phone ); ?></a></p>
				<p class="mfm-info-row"><strong><?php esc_html_e( 'Type:', 'snaporder' ); ?></strong> <?php echo esc_html( ucfirst( (string) $delivery_type ) ); ?></p>
				<?php if ( 'delivery' === $delivery_type ) : ?>
					<p class="mfm-info-row"><strong><?php esc_html_e( 'Address:', 'snaporder' ); ?></strong> <?php echo esc_html( "{$address}, {$city} {$zip}" ); ?></p>
				<?php elseif ( 'dinein' === $delivery_type ) : ?>
					<p class="mfm-info-row"><strong><?php esc_html_e( 'Table Number:', 'snaporder' ); ?></strong> <?php echo esc_html( $table_number ); ?></p>
				<?php endif; ?>
				<p class="mfm-info-row"><strong><?php esc_html_e( 'Payment:', 'snaporder' ); ?></strong> <?php echo esc_html( ucfirst( (string) $payment ) ); ?></p>
			</div>
			<div>
				<h3 class="mfm-modal-section-title"><?php esc_html_e( 'Order Status', 'snaporder' ); ?></h3>
				<select onchange="updateOrderStatus(<?php echo (int) $order_id; ?>, this.value)" class="mfm-modal-select">
					<?php
					$statuses = array(
						'pending'   => __( 'Pending', 'snaporder' ),
						'accepted'  => __( 'Accepted', 'snaporder' ),
						'cooking'   => __( 'Cooking', 'snaporder' ),
						'ready'     => __( 'Ready', 'snaporder' ),
						'completed' => __( 'Completed', 'snaporder' ),
						'rejected'  => __( 'Rejected', 'snaporder' ),
					);
					foreach ( $statuses as $key => $label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $key ),
							selected( $status, $key, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
				<p class="mfm-modal-date"><?php esc_html_e( 'Order placed:', 'snaporder' ); ?> <?php echo esc_html( get_the_date( 'F j, Y g:i a', $order ) ); ?></p>
			</div>
		</div>

		<h3 class="mfm-modal-section-title"><?php esc_html_e( 'Order Items', 'snaporder' ); ?></h3>
		<div class="mfm-order-items-box">
			<?php
			if ( is_array( $cart ) ) :
				foreach ( $cart as $item ) :
					?>
				<div class="mfm-modal-item-row">
					<div class="mfm-modal-item-content">
						<div>
							<strong><?php echo esc_html( $item['qty'] ); ?>x <?php echo esc_html( $item['title'] ); ?></strong>
											<?php if ( ! empty( $item['extras'] ) ) : ?>
								<br><small class="mfm-item-extras"><?php esc_html_e( 'Extras:', 'snaporder' ); ?> <?php echo esc_html( implode( ', ', array_column( $item['extras'], 'name' ) ) ); ?></small>
							<?php endif; ?>
											<?php if ( ! empty( $item['variant'] ) ) : ?>
								<br><small class="mfm-item-variant"><?php esc_html_e( 'Variant:', 'snaporder' ); ?> <?php echo esc_html( $item['variant']['name'] ); ?></small>
							<?php endif; ?>
											<?php if ( ! empty( $item['notes'] ) ) : ?>
								<br><small class="mfm-item-notes">&#9881; <?php echo esc_html( $item['notes'] ); ?></small>
							<?php endif; ?>
						</div>
						<span class="mfm-font-bold"><?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( (float) $item['price'], 2 ) ); ?></span>
					</div>
				</div>
							<?php
			endforeach;
endif;
			?>
			<div class="mfm-modal-total-row">
				<span><?php esc_html_e( 'Total:', 'snaporder' ); ?></span>
				<span><?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( (float) $total, 2 ) ); ?></span>
			</div>
			<?php if ( $tip_amount > 0 ) : ?>
				<div class="mfm-modal-total-row" style="font-size:0.9em;color:#666;margin-top:5px;">
					<span><?php esc_html_e( 'Tip included:', 'snaporder' ); ?></span>
					<span><?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( (float) $tip_amount, 2 ) ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $order_notes ) ) : ?>
			<h3 class="mfm-modal-section-title"><?php esc_html_e( 'Order Notes', 'snaporder' ); ?></h3>
			<div class="mfm-notes-box mfm-mb-20">
				<p class="mfm-notes-text-modal"><?php echo nl2br( esc_html( $order_notes ) ); ?></p>
			</div>
		<?php endif; ?>

		<div class="mfm-delete-btn-wrap">
			<?php do_action( 'snaporder_order_details_actions', $order_id ); ?>
			<button onclick="deleteOrder(<?php echo (int) $order_id; ?>)" class="mfm-delete-btn">
				<span class="dashicons dashicons-trash mfm-trash-icon"></span>
				<?php esc_html_e( 'Delete Order', 'snaporder' ); ?>
			</button>
		</div>
		<?php
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * Validates and updates an order status.
	 */
	public function ajax_update_order_status() {
		check_ajax_referer( 'mfm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'snaporder' ) ) );
		}

		$order_id   = intval( $_POST['order_id'] ?? 0 );
		$new_status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

		$valid_statuses = array( 'pending', 'accepted', 'cooking', 'ready', 'completed', 'rejected' );
		if ( ! $order_id || 'mfm_order' !== get_post_type( $order_id ) || ! in_array( $new_status, $valid_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'snaporder' ) ) );
		}

		update_post_meta( $order_id, '_mfm_order_status', $new_status );
		do_action( 'snaporder_order_status_updated', $order_id, $new_status );

		wp_send_json_success( array( 'message' => __( 'Status updated successfully.', 'snaporder' ) ) );
	}

	/**
	 * Permanently deletes a validated order.
	 */
	public function ajax_delete_order() {
		check_ajax_referer( 'mfm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'snaporder' ) ) );
		}

		$order_id = intval( $_POST['order_id'] ?? 0 );
		if ( ! $order_id || 'mfm_order' !== get_post_type( $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'snaporder' ) ) );
		}

		if ( wp_delete_post( $order_id, true ) ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete order.', 'snaporder' ) ) );
		}
	}

	/**
	 * Registers order status and detail metaboxes.
	 */
	public function add_order_meta_boxes() {
		add_meta_box(
			'mfm_order_status_box',
			__( 'Order Status', 'snaporder' ),
			array( $this, 'render_order_status_meta_box' ),
			'mfm_order',
			'side',
			'high'
		);
		add_meta_box(
			'mfm_order_details',
			__( 'Order Details', 'snaporder' ),
			array( $this, 'render_order_details_meta_box' ),
			'mfm_order',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the order-status selector.
	 *
	 * @param WP_Post $post Order post.
	 */
	public function render_order_status_meta_box( $post ) {
		wp_nonce_field( 'mfm_save_order_status', 'mfm_order_status_nonce' );
		$status   = get_post_meta( $post->ID, '_mfm_order_status', true );
		$status   = $status ? $status : 'pending';
		$statuses = array(
			'pending'   => __( 'Pending', 'snaporder' ),
			'accepted'  => __( 'Accepted', 'snaporder' ),
			'cooking'   => __( 'Cooking', 'snaporder' ),
			'ready'     => __( 'Ready', 'snaporder' ),
			'completed' => __( 'Completed', 'snaporder' ),
			'rejected'  => __( 'Rejected', 'snaporder' ),
		);
		echo '<select name="mfm_order_status" class="mfm-status-select mfm-mb-10">';
		foreach ( $statuses as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $status, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Change the status of this order.', 'snaporder' ) . '</p>';
	}

	/**
	 * Validates and saves the order-status metabox.
	 *
	 * @param int $post_id Order post ID.
	 */
	public function save_order_meta_boxes( $post_id ) {
		if ( ! isset( $_POST['mfm_order_status_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfm_order_status_nonce'] ) ), 'mfm_save_order_status' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['mfm_order_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['mfm_order_status'] ) );
			if ( in_array( $status, array( 'pending', 'accepted', 'cooking', 'ready', 'completed', 'rejected' ), true ) ) {
				update_post_meta( $post_id, '_mfm_order_status', $status );
			}
		}
	}

	/**
	 * Renders read-only order details.
	 *
	 * @param WP_Post $post Order post.
	 */
	public function render_order_details_meta_box( $post ) {
		$name          = get_post_meta( $post->ID, '_mfm_customer_name', true );
		$phone         = get_post_meta( $post->ID, '_mfm_customer_phone', true );
		$delivery_type = get_post_meta( $post->ID, '_mfm_delivery_type', true );
		$table_number  = get_post_meta( $post->ID, '_mfm_table_number', true );
		$address       = get_post_meta( $post->ID, '_mfm_address', true );
		$city          = get_post_meta( $post->ID, '_mfm_city', true );
		$zip           = get_post_meta( $post->ID, '_mfm_zip', true );
		$payment       = get_post_meta( $post->ID, '_mfm_payment_method', true );
		$total         = get_post_meta( $post->ID, '_mfm_order_total', true );
		$cart          = get_post_meta( $post->ID, '_mfm_cart_items', true );
		?>
		<div class="mfm-order-details">
			<p><strong><?php esc_html_e( 'Customer:', 'snaporder' ); ?></strong> <?php echo esc_html( $name ); ?></p>
			<p><strong><?php esc_html_e( 'Phone:', 'snaporder' ); ?></strong> <?php echo esc_html( $phone ); ?></p>
			<p><strong><?php esc_html_e( 'Type:', 'snaporder' ); ?></strong> <?php echo esc_html( ucfirst( (string) $delivery_type ) ); ?></p>
			<?php if ( 'delivery' === $delivery_type ) : ?>
				<p><strong><?php esc_html_e( 'Address:', 'snaporder' ); ?></strong> <?php echo esc_html( "{$address}, {$city} {$zip}" ); ?></p>
			<?php elseif ( 'dinein' === $delivery_type ) : ?>
				<p><strong><?php esc_html_e( 'Table:', 'snaporder' ); ?></strong> <?php echo esc_html( $table_number ); ?></p>
			<?php endif; ?>
			<p><strong><?php esc_html_e( 'Payment:', 'snaporder' ); ?></strong> <?php echo esc_html( ucfirst( (string) $payment ) ); ?></p>
			<?php
			$payment_status = get_post_meta( $post->ID, '_mfm_payment_status', true );
			$transaction_id = get_post_meta( $post->ID, '_mfm_transaction_id', true );
			if ( $payment_status ) :
				?>
				<p><strong><?php esc_html_e( 'Payment Status:', 'snaporder' ); ?></strong>
					<?php echo esc_html( ucfirst( $payment_status ) ); ?>
				</p>
			<?php endif; ?>
			<?php if ( $transaction_id ) : ?>
				<p><strong><?php esc_html_e( 'Transaction ID:', 'snaporder' ); ?></strong> <code><?php echo esc_html( $transaction_id ); ?></code></p>
			<?php endif; ?>
			<hr>
			<h3><?php esc_html_e( 'Items', 'snaporder' ); ?></h3>
			<ul>
				<?php
				if ( is_array( $cart ) ) :
					foreach ( $cart as $item ) :
						?>
					<li class="mfm-item-row">
						<strong><?php echo esc_html( $item['qty'] ); ?>x <?php echo esc_html( $item['title'] ); ?></strong>
						&mdash; <?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( (float) $item['price'], 2 ) ); ?>
											<?php if ( ! empty( $item['extras'] ) ) : ?>
							<br><small><?php esc_html_e( 'Extras:', 'snaporder' ); ?> <?php echo esc_html( implode( ', ', array_column( $item['extras'], 'name' ) ) ); ?></small>
						<?php endif; ?>
											<?php if ( ! empty( $item['variant'] ) ) : ?>
							<br><small><?php esc_html_e( 'Variant:', 'snaporder' ); ?> <?php echo esc_html( $item['variant']['name'] ); ?></small>
						<?php endif; ?>
					</li>
									<?php
				endforeach;
endif;
				?>
			</ul>
			<hr>
			<p><strong><?php esc_html_e( 'Total:', 'snaporder' ); ?> <?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( (float) $total, 2 ) ); ?></strong></p>
		</div>
		<?php
	}

	/**
	 * Defines columns for the fallback order list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_order_columns( $columns ) {
		return array(
			'cb'           => $columns['cb'],
			'title'        => __( 'Order ID', 'snaporder' ),
			'customer'     => __( 'Customer', 'snaporder' ),
			'order_status' => __( 'Status', 'snaporder' ),
			'order_total'  => __( 'Total', 'snaporder' ),
			'phone'        => __( 'Phone', 'snaporder' ),
			'date'         => $columns['date'],
		);
	}

	/**
	 * Renders one fallback order-list column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order post ID.
	 */
	public function render_order_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'order_status':
				$status = get_post_meta( $post_id, '_mfm_order_status', true );
				$status = $status ? $status : 'pending';
				echo '<span class="mfm-status-badge mfm-status-' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
				break;

			case 'order_total':
				$total = get_post_meta( $post_id, '_mfm_order_total', true );
				if ( $total ) {
					echo '<span class="mfm-order-total">' . esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( (float) $total, 2 ) ) . '</span>';
				} else {
					echo '<span class="mfm-empty-cell">-</span>';
				}
				break;

			case 'customer':
				$name          = get_post_meta( $post_id, '_mfm_customer_name', true );
				$delivery_type = get_post_meta( $post_id, '_mfm_delivery_type', true );
				$table_number  = get_post_meta( $post_id, '_mfm_table_number', true );
				if ( $name || ( 'dinein' === $delivery_type && $table_number ) ) {
					echo '<div class="mfm-customer-cell">';
					if ( 'delivery' === $delivery_type ) {
						echo '<span class="dashicons dashicons-car mfm-delivery-icon"></span>';
					} elseif ( 'dinein' === $delivery_type ) {
						echo '<span class="dashicons dashicons-universal-access mfm-pickup-icon"></span>';
					} else {
						echo '<span class="dashicons dashicons-store mfm-pickup-icon"></span>';
					}
					echo '<span class="mfm-customer-name">' . esc_html( $name ? $name : __( 'Guest', 'snaporder' ) );
					if ( 'dinein' === $delivery_type && $table_number ) {
						echo ' <span style="color:#888;font-size:12px;">(Table ' . esc_html( $table_number ) . ')</span>';
					}
					echo '</span></div>';
				} else {
					echo '<span class="mfm-empty-cell">-</span>';
				}
				break;

			case 'phone':
				$phone = get_post_meta( $post_id, '_mfm_customer_phone', true );
				if ( $phone ) {
					echo '<a href="tel:' . esc_attr( $phone ) . '" class="mfm-phone-link">' . esc_html( $phone ) . '</a>';
				} else {
					echo '<span class="mfm-empty-cell">-</span>';
				}
				break;
		}
	}

	/**
	 * Renders the custom order-management view.
	 */
	public function render_orders_management_page() {
		include SNAPORDER_PLUGIN_DIR . 'includes/mfm-orders-page.php';
	}

	/**
	 * Renders paid and cash order statistics.
	 */
	public function render_statistics_page() {
		global $wpdb;

		// Read-only report filter; value is restricted to an explicit allowlist.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days       = isset( $_GET['days'] ) ? intval( $_GET['days'] ) : 30;
		$days       = in_array( $days, array( 1, 7, 30, 90, 365 ), true ) ? $days : 30;
		$date_limit = current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current aggregate order report; stale cached revenue is undesirable.
		$order_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(p.ID) as count, SUM(pm.meta_value) as revenue
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'mfm_order'
			   AND p.post_status = 'publish'
			   AND pm.meta_key = '_mfm_order_total'
			   AND (
				EXISTS (SELECT 1 FROM {$wpdb->postmeta} pay_method WHERE pay_method.post_id = p.ID AND pay_method.meta_key = '_mfm_payment_method' AND pay_method.meta_value = 'cod')
				OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pay_status WHERE pay_status.post_id = p.ID AND pay_status.meta_key = '_mfm_payment_status' AND pay_status.meta_value = 'paid')
			   )
			   AND p.post_date >= %s",
				$date_limit
			)
		);

		$avg_order = ( $order_stats && $order_stats->count > 0 )
			? $order_stats->revenue / $order_stats->count
			: 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current paid-order lines are required for product totals.
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value as cart_items
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'mfm_order'
			   AND p.post_status = 'publish'
			   AND pm.meta_key = '_mfm_cart_items'
			   AND (
				EXISTS (SELECT 1 FROM {$wpdb->postmeta} pay_method WHERE pay_method.post_id = p.ID AND pay_method.meta_key = '_mfm_payment_method' AND pay_method.meta_value = 'cod')
				OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pay_status WHERE pay_status.post_id = p.ID AND pay_status.meta_key = '_mfm_payment_status' AND pay_status.meta_value = 'paid')
			   )
			   AND p.post_date >= %s",
				$date_limit
			)
		);

		$product_sales = array();
		foreach ( $orders as $order ) {
			$cart = maybe_unserialize( $order->cart_items );
			if ( is_array( $cart ) ) {
				foreach ( $cart as $item ) {
					$title = $item['title'] ?? 'Unknown';
					if ( ! isset( $product_sales[ $title ] ) ) {
						$product_sales[ $title ] = array(
							'qty'     => 0,
							'revenue' => 0,
						);
					}
					$product_sales[ $title ]['qty']     += $item['qty'] ?? 0;
					$product_sales[ $title ]['revenue'] += ( $item['price'] ?? 0 ) * ( $item['qty'] ?? 0 );
				}
			}
		}
		uasort( $product_sales, fn( $a, $b ) => $b['qty'] - $a['qty'] );
		$top_products = array_slice( $product_sales, 0, 10, true );
		?>
		<div class="wrap mfm-stats-wrap">
			<h1 class="mfm-page-header">
				<span class="dashicons dashicons-chart-line mfm-header-icon"></span>
				<?php esc_html_e( 'Order Statistics', 'snaporder' ); ?>
			</h1>
			<form method="get" class="mfm-filter-form">
				<input type="hidden" name="post_type" value="mfm_order">
				<input type="hidden" name="page" value="mfm-order-statistics">
				<select name="days" onchange="this.form.submit()" class="mfm-date-select">
					<?php
					$period_options = array(
						1   => __( 'Today', 'snaporder' ),
						7   => __( 'Last 7 Days', 'snaporder' ),
						30  => __( 'Last 30 Days', 'snaporder' ),
						90  => __( 'Last 90 Days', 'snaporder' ),
						365 => __( 'Last Year', 'snaporder' ),
					);
					foreach ( $period_options as $value => $label ) {
							printf( '<option value="%d" %s>%s</option>', (int) $value, selected( $days, $value, false ), esc_html( $label ) );
					}
					?>
				</select>
			</form>
			<div class="mfm-stats-grid">
				<div class="mfm-stat-card mfm-card-orders">
					<div class="mfm-card-content">
						<span class="dashicons dashicons-cart mfm-card-icon"></span>
						<div>
							<p class="mfm-card-label"><?php esc_html_e( 'Total Orders', 'snaporder' ); ?></p>
							<p class="mfm-card-value"><?php echo $order_stats ? number_format( intval( $order_stats->count ) ) : 0; ?></p>
						</div>
					</div>
				</div>
				<div class="mfm-stat-card mfm-card-revenue">
					<div class="mfm-card-content">
						<span class="dashicons dashicons-money-alt mfm-card-icon"></span>
						<div>
							<p class="mfm-card-label"><?php esc_html_e( 'Total Revenue', 'snaporder' ); ?></p>
							<p class="mfm-card-value"><?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . ( $order_stats ? number_format( (float) $order_stats->revenue, 2 ) : '0.00' ) ); ?></p>
						</div>
					</div>
				</div>
				<div class="mfm-stat-card mfm-card-avg">
					<div class="mfm-card-content">
						<span class="dashicons dashicons-chart-area mfm-card-icon"></span>
						<div>
							<p class="mfm-card-label"><?php esc_html_e( 'Average Order Value', 'snaporder' ); ?></p>
							<p class="mfm-card-value"><?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( $avg_order, 2 ) ); ?></p>
						</div>
					</div>
				</div>
			</div>
			<div class="mfm-top-products-box">
				<h2 class="mfm-box-title"><?php esc_html_e( 'Top Selling Products', 'snaporder' ); ?></h2>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'snaporder' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Quantity Sold', 'snaporder' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Revenue', 'snaporder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( ! empty( $top_products ) ) :
							foreach ( $top_products as $product_name => $data ) :
								?>
								<tr>
									<td><?php echo esc_html( $product_name ); ?></td>
									<td style="text-align:right;"><?php echo number_format( $data['qty'] ); ?></td>
									<td style="text-align:right;"><?php echo esc_html( SnapOrder_Settings::get_currency_symbol() . number_format( $data['revenue'], 2 ) ); ?></td>
								</tr>
								<?php
							endforeach;
						else :
							?>
							<tr><td colspan="3" style="padding:20px;text-align:center;color:#9ca3af;"><?php esc_html_e( 'No data found.', 'snaporder' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
