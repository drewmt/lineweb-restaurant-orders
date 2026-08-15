<?php
/**
 * Menu-view statistics (page-view tracking).
 *
 * @package SnapOrder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks privacy-conscious aggregate menu views.
 */
class SnapOrder_Statistics {

	/**
	 * Registers tracking and reporting hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_nopriv_snaporder_track_view', array( $this, 'track_view' ) );
		add_action( 'wp_ajax_snaporder_track_view', array( $this, 'track_view' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Creates the aggregate view-count table.
	 */
	public static function create_stats_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'snaporder_stats';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			item_id bigint(20) NOT NULL,
			view_date date NOT NULL,
			view_count int(11) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY item_date (item_id, view_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Records one deduplicated product view without storing an IP address.
	 */
	public function track_view() {
		check_ajax_referer( 'snaporder_order_nonce', 'nonce' );

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		if ( ! $item_id || 'snaporder_item' !== get_post_type( $item_id ) ) {
			wp_send_json_error();
		}

		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$view_key  = 'snaporder_view_' . substr( wp_hash( wp_privacy_anonymize_ip( $remote_ip ) . '|' . $item_id ), 0, 32 );
		if ( get_transient( $view_key ) ) {
			wp_send_json_success();
		}
		set_transient( $view_key, 1, 5 * MINUTE_IN_SECONDS );

		global $wpdb;
		$table = $wpdb->prefix . 'snaporder_stats';
		$today = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic aggregate counter; no reusable object cache entry exists.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (item_id, view_date, view_count) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE view_count = view_count + 1',
				$table,
				$item_id,
				$today
			)
		);

		wp_send_json_success();
	}

	/**
	 * Adds the menu-view statistics submenu.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=snaporder_item',
			__( 'View Statistics', 'lineweb-restaurant-orders' ),
			__( 'Statistics', 'lineweb-restaurant-orders' ),
			'manage_options',
			'snaporder-statistics',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders aggregate product-view statistics.
	 */
	public function render_admin_page() {
		global $wpdb;

		// Read-only report filter; value is restricted to an explicit allowlist.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days       = isset( $_GET['days'] ) ? intval( $_GET['days'] ) : 7;
		$days       = in_array( $days, array( 1, 7, 30, 90 ), true ) ? $days : 7;
		$table      = $wpdb->prefix . 'snaporder_stats';
		$date_limit = current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate report from the plugin table.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT item_id, SUM(view_count) as total_views
				 FROM %i
				 WHERE view_date >= %s
				 GROUP BY item_id
				 ORDER BY total_views DESC
				 LIMIT 20',
				$table,
				$date_limit
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Menu View Statistics', 'lineweb-restaurant-orders' ); ?></h1>
			<form method="get">
				<input type="hidden" name="post_type" value="snaporder_item">
				<input type="hidden" name="page" value="snaporder-statistics">
				<select name="days" onchange="this.form.submit()">
					<?php
					$periods = array(
						1  => __( 'Today', 'lineweb-restaurant-orders' ),
						7  => __( 'Last 7 Days', 'lineweb-restaurant-orders' ),
						30 => __( 'Last 30 Days', 'lineweb-restaurant-orders' ),
						90 => __( 'Last 90 Days', 'lineweb-restaurant-orders' ),
					);
					foreach ( $periods as $val => $label ) {
						printf( '<option value="%d" %s>%s</option>', (int) $val, selected( $days, $val, false ), esc_html( $label ) );
					}
					?>
				</select>
			</form>
			<table class="widefat striped" style="margin-top:15px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'lineweb-restaurant-orders' ); ?></th>
						<th><?php esc_html_e( 'Views', 'lineweb-restaurant-orders' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( $results ) :
						foreach ( $results as $row ) :
							$title = get_the_title( $row->item_id );
							$title = $title ? $title : '#' . $row->item_id;
							?>
							<tr>
								<td><?php echo esc_html( $title ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row->total_views ) ); ?></td>
							</tr>
							<?php
						endforeach;
					else :
						?>
						<tr><td colspan="2"><?php esc_html_e( 'No data for this period.', 'lineweb-restaurant-orders' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
