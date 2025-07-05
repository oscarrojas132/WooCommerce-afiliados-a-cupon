<?php
/**
 * Template for the Affiliate Panel in "My Account".
 *
 * @package WooCommerce Coupon Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ---- 1. DATA RETRIEVAL AND CALCULATION LOGIC ----

global $wpdb;
$vendor_id    = get_current_user_id();
$table_name   = $wpdb->prefix . 'afiliados_ventas';

// Prepare the query for security
$query = $wpdb->prepare(
	"SELECT * FROM {$table_name} WHERE vendor_id = %d ORDER BY date DESC",
	$vendor_id
);

$sales_data = $wpdb->get_results( $query );

// Initialize variables for totals
$total_commission_payable = 0;
$total_commission_pending = 0;
$total_sales_count        = 0;
$total_sales_amount       = 0;


// Calculate totals before displaying the table
if ( $sales_data ) {
	$total_sales_count = count( $sales_data );

	foreach ( $sales_data as $sale ) {
		// Add the sales amount (excluding cancelled)
		if ( $sale->order_state !== 'cancelled' ) {
			$total_sales_amount += $sale->amount;
		}
		
		// Calculate the commission for this sale
		$commission_amount = ( $sale->amount * $sale->commission_rate ) / 100;

		// Distribute the commission according to payment status
		switch ( $sale->payment_state ) {
			case 'ready_to_pay':
				$total_commission_payable += $commission_amount;
				break;
			case 'pending_completion':
				$total_commission_pending += $commission_amount;
				break;
			// 'paid' or 'cancelled' are not added to the main totals.
		}
	}
}

// ---- 2. HTML CODE TO DISPLAY THE PANEL ----
?>

<h2>Vendor Panel</h2>
<p>Here you can see a summary of your performance, the sales history generated with your coupon, and the status of your commissions.</p>

<hr>

<h3>📊 Your Summary</h3>
<div class="woocommerce-MyAccount-summary" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
	<div class="summary-box">
		<strong>Commissions Ready to Pay</strong>
		<span style="font-size: 1.5em; color: #2c7c2c; display: block;"><?php echo wc_price( $total_commission_payable ); ?></span>
	</div>
	<div class="summary-box">
		<strong>Pending Commissions</strong>
		<span style="font-size: 1.5em; color: #cc7a00; display: block;"><?php echo wc_price( $total_commission_pending ); ?></span>
	</div>
	<div class="summary-box">
		<strong>Total Sales Generated</strong>
		<span style="font-size: 1.5em; color: #4a4a4a; display: block;"><?php echo esc_html( $total_sales_count ); ?></span>
	</div>
	<div class="summary-box">
		<strong>Total Amount Sold</strong>
		<span style="font-size: 1.5em; color: #4a4a4a; display: block;"><?php echo wc_price( $total_sales_amount ); ?></span>
	</div>
</div>

<hr>

<h3>📋 Sales and Commissions History</h3>

<?php if ( $sales_data ) : ?>
	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders">
		<thead>
			<tr>
				<th class="order-number"><span class="nobr">Order</span></th>
				<th class="order-date"><span class="nobr">Date</span></th>
				<th class="order-total"><span class="nobr">Sale Amount</span></th>
				<th class="commission-rate"><span class="nobr">Rate (%)</span></th>
				<th class="commission-amount"><span class="nobr">Your Commission</span></th>
				<th class="commission-status"><span class="nobr">Payment Status</span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $sales_data as $sale ) : ?>
				<?php
					// Calculate the commission amount to display in the row
					$row_commission_amount = ( $sale->amount * $sale->commission_rate ) / 100;
				?>
				<tr class="woocommerce-orders-table__row order">
					<td class="woocommerce-orders-table__cell order-number" data-title="Order">
						#<?php echo esc_html( $sale->order_id ); ?>
					</td>
					<td class="woocommerce-orders-table__cell order-date" data-title="Date">
						<time datetime="<?php echo esc_attr( date( 'Y-m-d', strtotime( $sale->date ) ) ); ?>">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sale->date ) ) ); ?>
						</time>
					</td>
					<td class="woocommerce-orders-table__cell order-total" data-title="Sale Amount">
						<?php echo wc_price( $sale->amount ); ?>
					</td>
					<td class="woocommerce-orders-table__cell commission-rate" data-title="Rate (%)">
						<?php echo esc_html( $sale->commission_rate ); ?>%
					</td>
					<td class="woocommerce-orders-table__cell commission-amount" data-title="Your Commission">
						<?php if ( 'cancelled' === $sale->order_state ) : ?>
							<span style="color:#e2401c;">Cancelled</span>
						<?php else : ?>
							<strong><?php echo wc_price( $row_commission_amount ); ?></strong>
						<?php endif; ?>
					</td>
					<td class="woocommerce-orders-table__cell commission-status" data-title="Payment Status">
						<?php
						// Make the status names more user-friendly
						$status_text = ucwords( str_replace( '_', ' ', $sale->payment_state ) );
						echo esc_html( $status_text );
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="woocommerce-message woocommerce-info">
		You have not generated any sales with your coupon code yet. Keep going!
	</div>
<?php endif; ?>