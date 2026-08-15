<?php
/**
 * Orders management page template.
 *
 * Security fix: status filter is properly parameterised with $wpdb->prepare()
 * instead of direct string interpolation.
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Read-only, allowlisted admin report filters.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$snaporder_current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
$snaporder_search_term    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// -------------------------------------------------------------------------
// Quick stats (today)
// -------------------------------------------------------------------------
$snaporder_today_start = current_time( 'Y-m-d' ) . ' 00:00:00';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live order dashboard data must not be stale.
$snaporder_stats_today = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT COUNT(p.ID) as count, SUM(pm.meta_value) as revenue
	 FROM {$wpdb->posts} p
	 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
	 WHERE p.post_type = 'snaporder_order'
	   AND p.post_status = 'publish'
	   AND pm.meta_key = '_snaporder_order_total'
	   AND (
		EXISTS (SELECT 1 FROM {$wpdb->postmeta} pay_method WHERE pay_method.post_id = p.ID AND pay_method.meta_key = '_snaporder_payment_method' AND pay_method.meta_value = 'cod')
		OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pay_status WHERE pay_status.post_id = p.ID AND pay_status.meta_key = '_snaporder_payment_status' AND pay_status.meta_value = 'paid')
	   )
	   AND p.post_date >= %s",
		$snaporder_today_start
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live pending-order count must not be stale.
$snaporder_pending_count = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} p
	 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
	 WHERE p.post_type = 'snaporder_order'
	   AND p.post_status = 'publish'
	   AND pm.meta_key = '_snaporder_order_status'
	   AND pm.meta_value = 'pending'"
);

$snaporder_today_count   = $snaporder_stats_today ? (int) $snaporder_stats_today->count : 0;
$snaporder_today_revenue = $snaporder_stats_today ? (float) $snaporder_stats_today->revenue : 0.0;

// -------------------------------------------------------------------------
// Per-status counts
// -------------------------------------------------------------------------
$snaporder_status_counts = array();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live status totals must not be stale.
$snaporder_count_results = $wpdb->get_results(
	"SELECT pm.meta_value as status, COUNT(p.ID) as count
	 FROM {$wpdb->posts} p
	 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
	 WHERE p.post_type = 'snaporder_order'
	   AND p.post_status = 'publish'
	   AND pm.meta_key = '_snaporder_order_status'
	 GROUP BY pm.meta_value"
);

foreach ( (array) $snaporder_count_results as $snaporder_row ) {
	$snaporder_status_counts[ $snaporder_row->status ] = (int) $snaporder_row->count;
}
$snaporder_status_counts['all'] = array_sum( $snaporder_status_counts );

// -------------------------------------------------------------------------
// Main query — using $wpdb->prepare() for all user-supplied values
// -------------------------------------------------------------------------
$snaporder_allowed_statuses = array( 'awaiting_payment', 'payment_failed', 'pending', 'accepted', 'cooking', 'ready', 'completed', 'rejected' );

$snaporder_has_status = 'all' !== $snaporder_current_status && in_array( $snaporder_current_status, $snaporder_allowed_statuses, true );
$snaporder_has_search = '' !== $snaporder_search_term;

if ( $snaporder_has_status && $snaporder_has_search ) {
	$snaporder_search_like = '%' . $wpdb->esc_like( $snaporder_search_term ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live filtered order list must not be stale.
	$snaporder_orders = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, p.post_date
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
			 WHERE p.post_type = 'snaporder_order'
			   AND p.post_status = 'publish'
			   AND pm_status.meta_key = '_snaporder_order_status'
			   AND pm_status.meta_value = %s
			   AND (CAST(p.ID AS CHAR) LIKE %s OR p.post_title LIKE %s)
			 ORDER BY p.post_date DESC
			 LIMIT 50",
			$snaporder_current_status,
			$snaporder_search_like,
			$snaporder_search_like
		)
	);
} elseif ( $snaporder_has_status ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live status-filtered order list must not be stale.
	$snaporder_orders = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, p.post_date
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
			 WHERE p.post_type = 'snaporder_order'
			   AND p.post_status = 'publish'
			   AND pm_status.meta_key = '_snaporder_order_status'
			   AND pm_status.meta_value = %s
			 ORDER BY p.post_date DESC
			 LIMIT 50",
			$snaporder_current_status
		)
	);
} elseif ( $snaporder_has_search ) {
	$snaporder_search_like = '%' . $wpdb->esc_like( $snaporder_search_term ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live search results must not be stale.
	$snaporder_orders = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, p.post_date
			 FROM {$wpdb->posts} p
			 WHERE p.post_type = 'snaporder_order'
			   AND p.post_status = 'publish'
			   AND (CAST(p.ID AS CHAR) LIKE %s OR p.post_title LIKE %s)
			 ORDER BY p.post_date DESC
			 LIMIT 50",
			$snaporder_search_like,
			$snaporder_search_like
		)
	);
} else {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live order list must not be stale; the query has no user input.
	$snaporder_orders = $wpdb->get_results(
		"SELECT p.ID, p.post_title, p.post_date
		 FROM {$wpdb->posts} p
		 WHERE p.post_type = 'snaporder_order'
		   AND p.post_status = 'publish'
		 ORDER BY p.post_date DESC
		 LIMIT 50"
	);
}

$snaporder_currency = SnapOrder_Settings::get_currency_symbol();
?>
<div class="wrap snaporder-orders-wrap">
	<h1 class="snaporder-page-title"><?php esc_html_e( 'Orders', 'lineweb-restaurant-orders' ); ?></h1>

	<!-- Dashboard Cards -->
	<div class="snaporder-dashboard-grid">
		<div class="snaporder-dash-card">
			<div class="snaporder-dash-icon-box snaporder-dash-icon-blue">
				<span class="dashicons dashicons-cart"></span>
			</div>
			<div>
				<div class="snaporder-dash-label"><?php esc_html_e( "Today's Orders", 'lineweb-restaurant-orders' ); ?></div>
				<div class="snaporder-dash-value"><?php echo number_format( $snaporder_today_count ); ?></div>
			</div>
		</div>
		<div class="snaporder-dash-card">
			<div class="snaporder-dash-icon-box snaporder-dash-icon-green">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div>
				<div class="snaporder-dash-label"><?php esc_html_e( "Today's Revenue", 'lineweb-restaurant-orders' ); ?></div>
				<div class="snaporder-dash-value"><?php echo esc_html( number_format( $snaporder_today_revenue, 2 ) . $snaporder_currency ); ?></div>
			</div>
		</div>
		<div class="snaporder-dash-card">
			<div class="snaporder-dash-icon-box snaporder-dash-icon-orange">
				<span class="dashicons dashicons-bell"></span>
			</div>
			<div>
				<div class="snaporder-dash-label"><?php esc_html_e( 'Pending Action', 'lineweb-restaurant-orders' ); ?></div>
				<div class="snaporder-dash-value"><?php echo number_format( $snaporder_pending_count ); ?></div>
			</div>
		</div>
	</div>

	<!-- Toolbar: Tabs + Search -->
	<div class="snaporder-search-bar">
		<div class="snaporder-status-tabs" style="margin-bottom:0;">
			<div class="snaporder-tabs-container">
				<?php
				$snaporder_tabs = array(
					'all'              => array(
						'label' => __( 'All', 'lineweb-restaurant-orders' ),
						'color' => '#6b7280',
					),
					'awaiting_payment' => array(
						'label' => __( 'Awaiting payment', 'lineweb-restaurant-orders' ),
						'color' => '#7c3aed',
					),
					'payment_failed'   => array(
						'label' => __( 'Payment failed', 'lineweb-restaurant-orders' ),
						'color' => '#b91c1c',
					),
					'pending'          => array(
						'label' => __( 'Pending', 'lineweb-restaurant-orders' ),
						'color' => '#f59e0b',
					),
					'accepted'         => array(
						'label' => __( 'Accepted', 'lineweb-restaurant-orders' ),
						'color' => '#3b82f6',
					),
					'cooking'          => array(
						'label' => __( 'Cooking', 'lineweb-restaurant-orders' ),
						'color' => '#f97316',
					),
					'ready'            => array(
						'label' => __( 'Ready', 'lineweb-restaurant-orders' ),
						'color' => '#10b981',
					),
					'completed'        => array(
						'label' => __( 'Completed', 'lineweb-restaurant-orders' ),
						'color' => '#059669',
					),
					'rejected'         => array(
						'label' => __( 'Rejected', 'lineweb-restaurant-orders' ),
						'color' => '#ef4444',
					),
				);
				foreach ( $snaporder_tabs as $snaporder_status => $snaporder_tab ) :
					$snaporder_is_active  = ( $snaporder_current_status === $snaporder_status );
					$snaporder_count      = $snaporder_status_counts[ $snaporder_status ] ?? 0;
					$snaporder_bg_color   = $snaporder_is_active ? $snaporder_tab['color'] : '#f3f4f6';
					$snaporder_text_color = $snaporder_is_active ? 'white' : '#374151';
					$snaporder_url        = add_query_arg(
						array(
							'post_type' => 'snaporder_order',
							'page'      => 'snaporder-manage-orders',
							'status'    => $snaporder_status,
						),
						admin_url( 'edit.php' )
					);
					?>
					<a href="<?php echo esc_url( $snaporder_url ); ?>" class="snaporder-status-tab"
						style="background:<?php echo esc_attr( $snaporder_bg_color ); ?>;color:<?php echo esc_attr( $snaporder_text_color ); ?>;">
						<?php echo esc_html( $snaporder_tab['label'] ); ?>
						<span class="snaporder-tab-count" style="background:<?php echo $snaporder_is_active ? 'rgba(255,255,255,0.3)' : '#e5e7eb'; ?>;">
							<?php echo number_format( $snaporder_count ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="snaporder-search-form">
			<input type="hidden" name="post_type" value="snaporder_order">
			<input type="hidden" name="page" value="snaporder-manage-orders">
			<?php if ( 'all' !== $snaporder_current_status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $snaporder_current_status ); ?>">
			<?php endif; ?>
			<input type="text" name="s" value="<?php echo esc_attr( $snaporder_search_term ); ?>"
				placeholder="<?php esc_attr_e( 'Search order ID or name...', 'lineweb-restaurant-orders' ); ?>"
				class="snaporder-search-input">
			<button type="submit" class="button snaporder-btn-primary"><?php esc_html_e( 'Search', 'lineweb-restaurant-orders' ); ?></button>
			<?php if ( $snaporder_search_term ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=snaporder_order&page=snaporder-manage-orders' ) ); ?>" class="button">
					<?php esc_html_e( 'Clear', 'lineweb-restaurant-orders' ); ?>
				</a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Orders Table -->
	<div class="snaporder-orders-table-container">
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col" style="width:100px;"><?php esc_html_e( 'Status', 'lineweb-restaurant-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Order', 'lineweb-restaurant-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'lineweb-restaurant-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Customer', 'lineweb-restaurant-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'lineweb-restaurant-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Total', 'lineweb-restaurant-orders' ); ?></th>
					<th scope="col" style="width:120px;text-align:right;"><?php esc_html_e( 'Actions', 'lineweb-restaurant-orders' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( $snaporder_orders ) :
					foreach ( $snaporder_orders as $snaporder_order ) :
						$snaporder_order_id       = $snaporder_order->ID;
						$snaporder_customer_name  = get_post_meta( $snaporder_order_id, '_snaporder_customer_name', true );
						$snaporder_customer_phone = get_post_meta( $snaporder_order_id, '_snaporder_customer_phone', true );
						$snaporder_total          = get_post_meta( $snaporder_order_id, '_snaporder_order_total', true );
						$snaporder_status         = get_post_meta( $snaporder_order_id, '_snaporder_order_status', true );
						$snaporder_status         = $snaporder_status ? $snaporder_status : 'pending';
						$snaporder_delivery_type  = get_post_meta( $snaporder_order_id, '_snaporder_delivery_type', true );
						$snaporder_payment_method = get_post_meta( $snaporder_order_id, '_snaporder_payment_method', true );
						$snaporder_post_date_ts   = get_post_time( 'U', true, $snaporder_order );
						$snaporder_time_ago       = $snaporder_post_date_ts
							? human_time_diff( $snaporder_post_date_ts, time() ) . ' ' . __( 'ago', 'lineweb-restaurant-orders' )
							: '';

						$snaporder_status_colors = array(
							'awaiting_payment' => array(
								'bg'     => '#ede9fe',
								'text'   => '#5b21b6',
								'border' => '#ddd6fe',
							),
							'payment_failed'   => array(
								'bg'     => '#fee2e2',
								'text'   => '#991b1b',
								'border' => '#fecaca',
							),
							'pending'          => array(
								'bg'     => '#fef3c7',
								'text'   => '#92400e',
								'border' => '#fde68a',
							),
							'accepted'         => array(
								'bg'     => '#dbeafe',
								'text'   => '#1e40af',
								'border' => '#bfdbfe',
							),
							'cooking'          => array(
								'bg'     => '#fed7aa',
								'text'   => '#9a3412',
								'border' => '#fdba74',
							),
							'ready'            => array(
								'bg'     => '#d1fae5',
								'text'   => '#065f46',
								'border' => '#a7f3d0',
							),
							'completed'        => array(
								'bg'     => '#ecfccb',
								'text'   => '#3f6212',
								'border' => '#d9f99d',
							),
							'rejected'         => array(
								'bg'     => '#fee2e2',
								'text'   => '#991b1b',
								'border' => '#fecaca',
							),
						);
						$snaporder_color         = $snaporder_status_colors[ $snaporder_status ] ?? $snaporder_status_colors['pending'];
						?>
						<tr>
							<td>
								<span class="snaporder-status-badge"
									style="background:<?php echo esc_attr( $snaporder_color['bg'] ); ?>;color:<?php echo esc_attr( $snaporder_color['text'] ); ?>;border:1px solid <?php echo esc_attr( $snaporder_color['border'] ); ?>;">
									<?php echo esc_html( ucfirst( $snaporder_status ) ); ?>
								</span>
							</td>
							<td><strong>#<?php echo (int) $snaporder_order_id; ?></strong></td>
							<td>
								<?php echo esc_html( get_the_date( 'Y-m-d H:i', $snaporder_order ) ); ?><br>
								<small style="color:#6b7280;font-style:italic;"><?php echo esc_html( $snaporder_time_ago ); ?></small>
							</td>
							<td>
								<strong><?php echo esc_html( $snaporder_customer_name ); ?></strong><br>
								<a href="tel:<?php echo esc_attr( $snaporder_customer_phone ); ?>" style="text-decoration:none;color:var(--snaporder-primary);font-size:13px;">
									<?php echo esc_html( $snaporder_customer_phone ); ?>
								</a>
							</td>
							<td>
								<span class="dashicons dashicons-<?php echo 'delivery' === $snaporder_delivery_type ? 'car' : 'store'; ?>"
									style="font-size:16px;width:16px;height:16px;vertical-align:middle;color:#9ca3af;"></span>
								<?php echo esc_html( ucfirst( (string) $snaporder_delivery_type ) ); ?>
							</td>
							<td>
								<strong><?php echo esc_html( $snaporder_currency . number_format( (float) $snaporder_total, 2 ) ); ?></strong><br>
								<small style="color:#6b7280;"><?php echo esc_html( ucfirst( (string) $snaporder_payment_method ) ); ?></small>
							</td>
							<td style="text-align:right;">
								<button class="button button-small snaporder-view-order-btn"
									data-order-id="<?php echo (int) $snaporder_order_id; ?>">
									<?php esc_html_e( 'View', 'lineweb-restaurant-orders' ); ?>
								</button>
							</td>
						</tr>
						<?php
					endforeach;
				else :
					?>
					<tr>
						<td colspan="7" style="padding:40px;text-align:center;color:#6b7280;">
							<span class="dashicons dashicons-cart" style="font-size:32px;width:32px;height:32px;display:block;margin:0 auto 10px;"></span>
							<?php esc_html_e( 'No orders found', 'lineweb-restaurant-orders' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<!-- Order Details Modal -->
<div id="snaporder-order-modal" class="snaporder-modal-overlay">
	<div class="snaporder-modal-container">
		<div id="snaporder-modal-content" class="snaporder-modal-content">
			<!-- Content loaded via AJAX -->
		</div>
	</div>
</div>
