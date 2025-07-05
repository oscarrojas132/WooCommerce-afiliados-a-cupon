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

	<!-- Filter Form -->
	<form method="get">
		<input type="hidden" name="page" value="wc-afiliados-comisiones-admin">
		<div class="tablenav top">
			<div class="alignleft actions">
				<label for="filter_vendor" class="screen-reader-text">Filter by vendor</label>
				<select name="filter_vendor" id="filter_vendor">
					<option value="">All vendors</option>
					<?php foreach ( $vendors as $vendor ) : ?>
						<option value="<?php echo esc_attr( $vendor->vendor_id ); ?>" <?php selected( $filter_vendor_id, $vendor->vendor_id ); ?>>
							<?php echo esc_html( $vendor->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="filter_status" class="screen-reader-text">Filter by payment status</label>
				<select name="filter_status" id="filter_status">
					<option value="">All statuses</option>
					<option value="pendiente_finalizacion" <?php selected( $filter_status, 'pendiente_finalizacion' ); ?>>Pending Completion</option>
					<option value="lista_para_pagar" <?php selected( $filter_status, 'lista_para_pagar' ); ?>>Ready to Pay</option>
					<option value="pagado" <?php selected( $filter_status, 'pagado' ); ?>>Paid</option>
					<option value="cancelado" <?php selected( $filter_status, 'cancelado' ); ?>>Cancelled</option>
				</select>

				<input type="submit" class="button" value="Filter">
				<a href="?page=wc-afiliados-comisiones-admin" class="button">Clear</a>
			</div>
		</div>
	</form>

	<!-- Commissions Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
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
					$commission_amount = ( $data->amount * $data->commission_rate ) / 100;
					$total_commission_amount += ( 'cancelado' !== $data->order_state ) ? $commission_amount : 0;
					?>
					<tr>
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
							<?php if ( 'cancelado' === $data->order_state ) : ?>
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
					<td class="colspanchange" colspan="8">No commissions found with the selected filters.</td>
				</tr>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th scope="col" colspan="5" style="text-align:right;">Total Commissions (Filtered):</th>
				<th scope="col">
					<strong><?php echo wc_price( $total_commission_amount ); ?></strong>
				</th>
				<th scope="col" colspan="2"></th>
			</tr>
		</tfoot>
	</table>
</div>