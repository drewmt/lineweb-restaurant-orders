<?php
/**
 * Print-friendly receipt view.
 *
 * Opened via ?snaporder-print={order_id}. Requires the user to be logged in and
 * have manage_options capability to prevent unauthenticated access to order data.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders nonce-protected printable order receipts.
 */
class SnapOrder_Printer {

	/**
	 * Registers receipt actions.
	 */
	public function __construct() {
		add_action( 'snaporder_order_details_actions', array( $this, 'render_print_button' ) );
		add_action( 'template_redirect', array( $this, 'render_receipt_template' ) );
	}

	/**
	 * Renders the print-receipt button for an order.
	 *
	 * @param int $order_id Order post ID.
	 */
	public function render_print_button( $order_id ) {
		$print_url = wp_nonce_url( add_query_arg( 'snaporder-print', (int) $order_id, site_url( '/' ) ), 'snaporder_print_order_' . (int) $order_id );
		?>
		<a href="<?php echo esc_url( $print_url ); ?>" target="_blank" class="button button-secondary snaporder-print-btn">
			<span class="dashicons dashicons-printer"></span>
			<?php esc_html_e( 'Print Receipt', 'lineweb-restaurant-orders' ); ?>
		</a>
		<?php
	}

	/**
	 * Validates the request and renders a printable receipt.
	 */
	public function render_receipt_template() {
		if ( ! isset( $_GET['snaporder-print'] ) ) {
			return;
		}

		// Strict auth check: must be logged-in admin.
		if ( ! current_user_can( 'manage_options' ) ) {
			auth_redirect();
			exit;
		}

		$order_id = absint( wp_unslash( $_GET['snaporder-print'] ) );
		if ( ! $order_id ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'snaporder_print_order_' . $order_id ) ) {
			wp_die( esc_html__( 'This print link is invalid or has expired.', 'lineweb-restaurant-orders' ), '', array( 'response' => 403 ) );
		}

		$order = get_post( $order_id );
		if ( ! $order || 'snaporder_order' !== $order->post_type ) {
			return;
		}

		$customer_name  = get_post_meta( $order_id, '_snaporder_customer_name', true );
		$customer_name  = $customer_name ? $customer_name : __( 'Guest', 'lineweb-restaurant-orders' );
		$customer_phone = get_post_meta( $order_id, '_snaporder_customer_phone', true );
		$delivery_type  = get_post_meta( $order_id, '_snaporder_delivery_type', true );
		$table_number   = get_post_meta( $order_id, '_snaporder_table_number', true );
		$tip_amount     = get_post_meta( $order_id, '_snaporder_tip_amount', true );
		$address        = get_post_meta( $order_id, '_snaporder_address', true );
		$street         = get_post_meta( $order_id, '_snaporder_street', true );
		$city           = get_post_meta( $order_id, '_snaporder_city', true );
		$zip            = get_post_meta( $order_id, '_snaporder_zip', true );
		$payment        = get_post_meta( $order_id, '_snaporder_payment_method', true );
		$total          = get_post_meta( $order_id, '_snaporder_order_total', true );
		$cart           = get_post_meta( $order_id, '_snaporder_cart_items', true );
		$notes          = get_post_meta( $order_id, '_snaporder_order_notes', true );
		$currency       = SnapOrder_Settings::get_currency_symbol();

		wp_enqueue_style( 'snaporder-receipt', SNAPORDER_PLUGIN_URL . 'assets/css/receipt.css', array(), SNAPORDER_VERSION );
		wp_enqueue_script( 'snaporder-receipt', SNAPORDER_PLUGIN_URL . 'assets/js/receipt.js', array(), SNAPORDER_VERSION, true );
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title>Receipt #<?php echo (int) $order_id; ?></title>
			<?php wp_print_styles( 'snaporder-receipt' ); ?>
		</head>
		<body>
			<div class="center">
				<h2 class="receipt-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<p class="receipt-meta">Order #<?php echo (int) $order_id; ?></p>
				<p class="receipt-meta"><?php echo esc_html( get_the_date( 'd/m/Y H:i', $order ) ); ?></p>
			</div>
			<div class="line"></div>
			<div class="row">
				<span class="bold">Type:</span>
				<span>
					<?php
					echo esc_html( ucfirst( (string) $delivery_type ) );
					if ( 'dinein' === $delivery_type && $table_number ) {
						echo esc_html( ' (Table ' . $table_number . ')' );
					}
					?>
				</span>
			</div>
			<div class="row"><span class="bold">Name:</span><span><?php echo esc_html( $customer_name ); ?></span></div>
			<?php if ( 'delivery' === $delivery_type ) : ?>
				<div class="row receipt-address">
					<span class="bold">Address:</span><br>
					<?php echo esc_html( "{$street} {$address}" ); ?><br>
					<?php echo esc_html( "{$city} {$zip}" ); ?>
				</div>
			<?php endif; ?>
			<div class="row"><span class="bold">Phone:</span><span><?php echo esc_html( $customer_phone ); ?></span></div>
			<div class="row"><span class="bold">Payment:</span><span><?php echo esc_html( ucfirst( (string) $payment ) ); ?></span></div>
			<div class="line"></div>
			<?php
			if ( is_array( $cart ) ) :
				foreach ( $cart as $item ) :
					?>
				<div class="item-row">
					<div class="row">
						<span><?php echo esc_html( $item['qty'] ); ?>x <?php echo esc_html( $item['title'] ); ?></span>
						<span><?php echo esc_html( number_format( (float) $item['price'], 2 ) ); ?></span>
					</div>
									<?php if ( ! empty( $item['extras'] ) ) : ?>
						<div class="item-extras">+ <?php echo esc_html( implode( ', ', array_column( $item['extras'], 'name' ) ) ); ?></div>
					<?php endif; ?>
									<?php if ( ! empty( $item['variant'] ) ) : ?>
						<div class="item-extras">Var: <?php echo esc_html( $item['variant']['name'] ); ?></div>
					<?php endif; ?>
									<?php if ( ! empty( $item['notes'] ) ) : ?>
						<div class="item-extras">Notes: <?php echo esc_html( $item['notes'] ); ?></div>
					<?php endif; ?>
				</div>
							<?php
			endforeach;
endif;
			?>
			<div class="line"></div>
			<?php if ( $tip_amount > 0 ) : ?>
				<div class="row"><span>Tip:</span><span><?php echo esc_html( number_format( (float) $tip_amount, 2 ) ); ?></span></div>
			<?php endif; ?>
			<div class="row bold receipt-total">
				<span>TOTAL:</span>
				<span><?php echo esc_html( $currency . number_format( (float) $total, 2 ) ); ?></span>
			</div>
			<?php if ( ! empty( $notes ) ) : ?>
				<div class="line"></div>
				<p class="bold">Order Notes:</p>
				<p><?php echo nl2br( esc_html( $notes ) ); ?></p>
			<?php endif; ?>
			<div class="footer">Thank you!</div>
			<?php wp_print_footer_scripts(); ?>
		</body>
		</html>
		<?php
		exit;
	}
}
