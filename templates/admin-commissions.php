<?php
/**
 * Template for the Commission Admin Panel.
 *
 * @package WooCommerce Coupon Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ---- 1. DATA RETRIEVAL AND FILTERING LOGIC ----

global $wpdb;
$table_name = $wpdb->prefix . 'afiliados_ventas';

// Get filter values (if any)
$filter_vendor_id = isset( $_GET['filter_vendor'] ) ? absint( $_GET['filter_vendor'] ) : 0;
$filter_status    = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';

// Build SQL query with filters
$where_clauses = array();
if ( $filter_vendor_id ) {
	$where_clauses[] = $wpdb->prepare( 'sales.vendor_id = %d', $filter_vendor_id );
}
if ( $filter_status ) {
	$where_clauses[] = $wpdb->prepare( 'sales.payment_state = %s', $filter_status );
}

$where_sql = '';
if ( ! empty( $where_clauses ) ) {
	$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
}

// Main query to get commission data, joining with users table to get vendor name
// NOTE: We select sales.id, assuming 'id' is the primary key of your sales table.
$query = "
	SELECT sales.*, users.display_name AS vendor_name
	FROM {$table_name} AS sales
	LEFT JOIN {$wpdb->users} AS users ON sales.vendor_id = users.ID
	{$where_sql}
	ORDER BY sales.date DESC
";

$commission_data = $wpdb->get_results( $query );

// Get all vendors with sales for the filter
$vendors = $wpdb->get_results( "SELECT DISTINCT T1.vendor_id, T2.display_name FROM {$table_name} T1 JOIN {$wpdb->users} T2 ON T1.vendor_id = T2.ID ORDER BY T2.display_name ASC" );

// ---- 2. HTML CODE TO DISPLAY THE PANEL ----
?>

<div class="wrap">
	<h1 class="wp-heading-inline">Vendor Commission Management</h1>

	<form method="post">
		<input type="hidden" name="page" value="wc-afiliados-comisiones-admin">
		<?php wp_nonce_field( 'commission_bulk_actions' ); ?>

		<div class="tablenav top">
			
			<div class="alignleft actions bulkactions">
				<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
				<select name="action" id="bulk-action-selector-top">
					<option value="-1">Bulk Actions</option>
					<option value="mark_paid">Mark as Paid</option>
				</select>
				<input type="submit" id="doaction" class="button action" value="Apply">
			</div>

			<div class="alignleft actions">
				<select name="filter_vendor" id="filter_vendor">
					<option value="">All vendors</option>
					<?php foreach ( $vendors as $vendor ) : ?>
						<option value="<?php echo esc_attr( $vendor->vendor_id ); ?>" <?php selected( $filter_vendor_id, $vendor->vendor_id ); ?>>
							<?php echo esc_html( $vendor->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select name="filter_status" id="filter_status">
					<option value="">All statuses</option>
					<option value="pending_completion" <?php selected( $filter_status, 'pending_completion' ); ?>>Pending Completion</option>
					<option value="ready_to_pay" <?php selected( $filter_status, 'ready_to_pay' ); ?>>Ready to Pay</option>
					<option value="paid" <?php selected( $filter_status, 'paid' ); ?>>Paid</option>
					<option value="cancelled" <?php selected( $filter_status, 'cancelled' ); ?>>Cancelled</option>
				</select>

				<input type="submit" class="button" value="Filter" formaction="<?php echo esc_url( admin_url( 'admin.php?page=wc-afiliados-comisiones-admin' ) ); ?>" formmethod="get">
				<a href="?page=wc-afiliados-comisiones-admin" class="button">Clear</a>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<input id="cb-select-all-1" type="checkbox">
					</td>
					<th scope="col" class="manage-column">Order</th>
					<th scope="col" class="manage-column">Vendor</th>
					<th scope="col" class="manage-column">Date</th>
					<th scope="col" class="manage-column">Sale Amount</th>
					<th scope="col" class="manage-column">Rate (%)</th>
					<th scope="col" class="manage-column">Commission</th>
					<th scope="col" class="manage-column">Order Status</th>
					<th scope="col" class="manage-column">Payment Status</th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php if ( $commission_data ) : ?>
					<?php
						$total_commission_amount = 0;
					foreach ( $commission_data as $data ) :
						// Assumes 'id' is the primary key of the sales table.
						if ( ! isset( $data->id ) ) {
							continue; // Skip if no ID is present.
						}
						$commission_amount = ( $data->amount * $data->commission_rate ) / 100;
						$total_commission_amount += ( 'cancelled' !== $data->order_state ) ? $commission_amount : 0;
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="commission_ids[]" value="<?php echo esc_attr( $data->id ); ?>">
							</th>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $data->order_id ) ); ?>">
									#<?php echo esc_html( $data->order_id ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $data->vendor_name ); ?></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $data->date ) ) ); ?></td>
							<td><?php echo wc_price( $data->amount ); ?></td>
							<td><?php echo esc_html( $data->commission_rate ); ?>%</td>
							<td>
								<?php if ( 'cancelled' === $data->order_state ) : ?>
									<span style="color:#e2401c;">Cancelled</span>
								<?php else : ?>
									<strong><?php echo wc_price( $commission_amount ); ?></strong>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $data->order_state ) ) ); ?></td>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $data->payment_state ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr class="no-items">
						<td class="colspanchange" colspan="9">No commissions found with the selected filters.</td>
					</tr>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="col" colspan="6" style="text-align:right;">Total Commissions (Filtered):</th>
					<th scope="col">
						<strong><?php echo wc_price( $total_commission_amount ); ?></strong>
					</th>
					<th scope="col" colspan="2"></th>
				</tr>
			</tfoot>
		</table>
	</form>
</div>