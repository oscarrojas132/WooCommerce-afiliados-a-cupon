<?php
/**
 * Plantilla para el Panel de Afiliado en "Mi Cuenta".
 *
 * @package WooCommerce Afiliados a Cupón
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ---- 1. LÓGICA DE OBTENCIÓN Y CÁLCULO DE DATOS ----

global $wpdb;
$vendor_id    = get_current_user_id();
$table_name   = $wpdb->prefix . 'afiliados_ventas';

// Preparamos la consulta para seguridad
$query = $wpdb->prepare(
	"SELECT * FROM {$table_name} WHERE vendor_id = %d ORDER BY date DESC",
	$vendor_id
);

$sales_data = $wpdb->get_results( $query );

// Inicializamos variables para los totales
$total_commission_payable = 0;
$total_commission_pending = 0;
$total_sales_count        = 0;
$total_sales_amount       = 0;


// Calculamos los totales antes de mostrar la tabla
if ( $sales_data ) {
	$total_sales_count = count( $sales_data );

	foreach ( $sales_data as $sale ) {
		// Sumamos el monto de las ventas (excluyendo canceladas)
		if ( $sale->order_state !== 'cancelado' ) {
			$total_sales_amount += $sale->amount;
		}
		
		// Calculamos la comisión para esta venta
		$commission_amount = ( $sale->amount * $sale->commission_rate ) / 100;

		// Distribuimos la comisión según el estado de pago
		switch ( $sale->payment_state ) {
			case 'lista_para_pagar':
				$total_commission_payable += $commission_amount;
				break;
			case 'pendiente_finalizacion':
				$total_commission_pending += $commission_amount;
				break;
			// 'pagado' o 'cancelado' no se suman a los totales principales.
		}
	}
}

// ---- 2. CÓDIGO HTML PARA MOSTRAR EL PANEL ----
?>

<h2>Panel de Vendedor</h2>
<p>Aquí puedes ver un resumen de tu rendimiento, el historial de ventas generadas con tu cupón y el estado de tus comisiones.</p>

<hr>

<h3>📊 Tu Resumen</h3>
<div class="woocommerce-MyAccount-summary" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
	<div class="summary-box">
		<strong>Comisiones Listas para Pagar</strong>
		<span style="font-size: 1.5em; color: #2c7c2c; display: block;"><?php echo wc_price( $total_commission_payable ); ?></span>
	</div>
	<div class="summary-box">
		<strong>Comisiones Pendientes</strong>
		<span style="font-size: 1.5em; color: #cc7a00; display: block;"><?php echo wc_price( $total_commission_pending ); ?></span>
	</div>
	<div class="summary-box">
		<strong>Ventas Totales Generadas</strong>
		<span style="font-size: 1.5em; color: #4a4a4a; display: block;"><?php echo esc_html( $total_sales_count ); ?></span>
	</div>
	<div class="summary-box">
		<strong>Monto Total Vendido</strong>
		<span style="font-size: 1.5em; color: #4a4a4a; display: block;"><?php echo wc_price( $total_sales_amount ); ?></span>
	</div>
</div>

<hr>

<h3>📋 Historial de Ventas y Comisiones</h3>

<?php if ( $sales_data ) : ?>
	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders">
		<thead>
			<tr>
				<th class="order-number"><span class="nobr">Pedido</span></th>
				<th class="order-date"><span class="nobr">Fecha</span></th>
				<th class="order-total"><span class="nobr">Monto Venta</span></th>
				<th class="commission-rate"><span class="nobr">Tasa (%)</span></th>
				<th class="commission-amount"><span class="nobr">Tu Comisión</span></th>
				<th class="commission-status"><span class="nobr">Estado Pago</span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $sales_data as $sale ) : ?>
				<?php
					// Calculamos el monto de la comisión para mostrar en la fila
					$row_commission_amount = ( $sale->amount * $sale->commission_rate ) / 100;
				?>
				<tr class="woocommerce-orders-table__row order">
					<td class="woocommerce-orders-table__cell order-number" data-title="Pedido">
						#<?php echo esc_html( $sale->order_id ); ?>
					</td>
					<td class="woocommerce-orders-table__cell order-date" data-title="Fecha">
						<time datetime="<?php echo esc_attr( date( 'Y-m-d', strtotime( $sale->date ) ) ); ?>">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sale->date ) ) ); ?>
						</time>
					</td>
					<td class="woocommerce-orders-table__cell order-total" data-title="Monto Venta">
						<?php echo wc_price( $sale->amount ); ?>
					</td>
					<td class="woocommerce-orders-table__cell commission-rate" data-title="Tasa (%)">
						<?php echo esc_html( $sale->commission_rate ); ?>%
					</td>
					<td class="woocommerce-orders-table__cell commission-amount" data-title="Tu Comisión">
						<?php if ( 'cancelado' === $sale->order_state ) : ?>
							<span style="color:#e2401c;">Anulada</span>
						<?php else : ?>
							<strong><?php echo wc_price( $row_commission_amount ); ?></strong>
						<?php endif; ?>
					</td>
					<td class="woocommerce-orders-table__cell commission-status" data-title="Estado Pago">
						<?php
						// Hacemos los nombres de estado más amigables para el usuario
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
		Aún no has generado ninguna venta con tu código de cupón. ¡Sigue adelante!
	</div>
<?php endif; ?>