<?php
/**
 * Print-friendly receipt view.
 *
 * Opened via ?mfm-print={order_id}. Requires the user to be logged in and
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
		$print_url = wp_nonce_url( add_query_arg( 'mfm-print', (int) $order_id, site_url( '/' ) ), 'snaporder_print_order_' . (int) $order_id );
		?>
		<a href="<?php echo esc_url( $print_url ); ?>" target="_blank" class="button button-secondary mfm-print-btn" style="margin-right:10px;">
			<span class="dashicons dashicons-printer"></span>
			<?php esc_html_e( 'Print Receipt', 'lineweb-restaurant-orders' ); ?>
		</a>
		<?php
	}

	/**
	 * Validates the request and renders a printable receipt.
	 */
	public function render_receipt_template() {
		if ( ! isset( $_GET['mfm-print'] ) ) {
			return;
		}

		// Strict auth check: must be logged-in admin.
		if ( ! current_user_can( 'manage_options' ) ) {
			auth_redirect();
			exit;
		}

		$order_id = absint( wp_unslash( $_GET['mfm-print'] ) );
		if ( ! $order_id ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'snaporder_print_order_' . $order_id ) ) {
			wp_die( esc_html__( 'This print link is invalid or has expired.', 'lineweb-restaurant-orders' ), '', array( 'response' => 403 ) );
		}

		$order = get_post( $order_id );
		if ( ! $order || 'mfm_order' !== $order->post_type ) {
			return;
		}

		$customer_name  = get_post_meta( $order_id, '_mfm_customer_name', true );
		$customer_name  = $customer_name ? $customer_name : __( 'Guest', 'lineweb-restaurant-orders' );
		$customer_phone = get_post_meta( $order_id, '_mfm_customer_phone', true );
		$delivery_type  = get_post_meta( $order_id, '_mfm_delivery_type', true );
		$table_number   = get_post_meta( $order_id, '_mfm_table_number', true );
		$tip_amount     = get_post_meta( $order_id, '_mfm_tip_amount', true );
		$address        = get_post_meta( $order_id, '_mfm_address', true );
		$street         = get_post_meta( $order_id, '_mfm_street', true );
		$city           = get_post_meta( $order_id, '_mfm_city', true );
		$zip            = get_post_meta( $order_id, '_mfm_zip', true );
		$payment        = get_post_meta( $order_id, '_mfm_payment_method', true );
		$total          = get_post_meta( $order_id, '_mfm_order_total', true );
		$cart           = get_post_meta( $order_id, '_mfm_cart_items', true );
		$notes          = get_post_meta( $order_id, '_mfm_order_notes', true );
		$currency       = SnapOrder_Settings::get_currency_symbol();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title>Receipt #<?php echo (int) $order_id; ?></title>
			<style>
				body{font-family:'Courier New',monospace;font-size:14px;width:80mm;margin:0;padding:10px;color:#000;background:#fff;}
				.center{text-align:center;}.bold{font-weight:bold;}
				.line{border-bottom:1px dashed #000;margin:10px 0;}
				.row{display:flex;justify-content:space-between;margin-bottom:5px;}
				.item-row{margin-bottom:5px;}.item-extras{font-size:12px;padding-left:10px;}
				.footer{margin-top:20px;font-size:12px;text-align:center;}
				@media print{@page{margin:0;}body{width:auto;padding:0 5mm;}}
			</style>
		</head>
		<body onload="window.print()">
			<div class="center">
				<h2 style="margin:0;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<p style="margin:5px 0;">Order #<?php echo (int) $order_id; ?></p>
				<p style="margin:5px 0;"><?php echo esc_html( get_the_date( 'd/m/Y H:i', $order ) ); ?></p>
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
				<div class="row" style="display:block;">
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
			<div class="row bold" style="font-size:16px;">
				<span>TOTAL:</span>
				<span><?php echo esc_html( $currency . number_format( (float) $total, 2 ) ); ?></span>
			</div>
			<?php if ( ! empty( $notes ) ) : ?>
				<div class="line"></div>
				<p class="bold">Order Notes:</p>
				<p><?php echo nl2br( esc_html( $notes ) ); ?></p>
			<?php endif; ?>
			<div class="footer">Thank you!</div>
		</body>
		</html>
		<?php
		exit;
	}
}
