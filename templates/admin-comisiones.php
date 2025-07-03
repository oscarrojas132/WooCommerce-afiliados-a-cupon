<?php
/**
 * Plantilla para el Panel de Administración de Comisiones.
 *
 * @package WooCommerce Afiliados a Cupón
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ---- 1. LÓGICA DE OBTENCIÓN Y FILTRADO DE DATOS ----

global $wpdb;
$table_name = $wpdb->prefix . 'afiliados_ventas';

// Obtener los valores de los filtros (si existen)
$filter_vendor_id = isset( $_GET['filter_vendor'] ) ? absint( $_GET['filter_vendor'] ) : 0;
$filter_status    = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';

// Construir la consulta SQL con los filtros
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

// Consulta principal para obtener los datos de comisiones, uniendo con la tabla de usuarios para obtener el nombre del vendedor
$query = "
    SELECT sales.*, users.display_name AS vendor_name
    FROM {$table_name} AS sales
    LEFT JOIN {$wpdb->users} AS users ON sales.vendor_id = users.ID
    {$where_sql}
    ORDER BY sales.date DESC
";

$commission_data = $wpdb->get_results( $query );

// Obtener todos los vendedores que tienen ventas para el filtro
$vendors = $wpdb->get_results( "SELECT DISTINCT T1.vendor_id, T2.display_name FROM {$table_name} T1 JOIN {$wpdb->users} T2 ON T1.vendor_id = T2.ID ORDER BY T2.display_name ASC" );

// ---- 2. CÓDIGO HTML PARA MOSTRAR EL PANEL ----
?>

<div class="wrap">
	<h1 class="wp-heading-inline">Gestión de Comisiones de Vendedores</h1>

	<!-- Formulario de Filtros -->
	<form method="get">
		<input type="hidden" name="page" value="wc-afiliados-comisiones-admin">
		<div class="tablenav top">
			<div class="alignleft actions">
				<label for="filter_vendor" class="screen-reader-text">Filtrar por vendedor</label>
				<select name="filter_vendor" id="filter_vendor">
					<option value="">Todos los vendedores</option>
					<?php foreach ( $vendors as $vendor ) : ?>
						<option value="<?php echo esc_attr( $vendor->vendor_id ); ?>" <?php selected( $filter_vendor_id, $vendor->vendor_id ); ?>>
							<?php echo esc_html( $vendor->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="filter_status" class="screen-reader-text">Filtrar por estado de pago</label>
				<select name="filter_status" id="filter_status">
					<option value="">Todos los estados</option>
					<option value="pendiente_finalizacion" <?php selected( $filter_status, 'pendiente_finalizacion' ); ?>>Pendiente de Finalización</option>
					<option value="lista_para_pagar" <?php selected( $filter_status, 'lista_para_pagar' ); ?>>Lista para Pagar</option>
					<option value="pagado" <?php selected( $filter_status, 'pagado' ); ?>>Pagado</option>
					<option value="cancelado" <?php selected( $filter_status, 'cancelado' ); ?>>Cancelado</option>
				</select>

				<input type="submit" class="button" value="Filtrar">
				<a href="?page=wc-afiliados-comisiones-admin" class="button">Limpiar</a>
			</div>
		</div>
	</form>

	<!-- Tabla de Comisiones -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col" class="manage-column">Pedido</th>
				<th scope="col" class="manage-column">Vendedor</th>
				<th scope="col" class="manage-column">Fecha</th>
				<th scope="col" class="manage-column">Monto Venta</th>
				<th scope="col" class="manage-column">Tasa (%)</th>
				<th scope="col" class="manage-column">Comisión</th>
				<th scope="col" class="manage-column">Estado Pedido</th>
				<th scope="col" class="manage-column">Estado Pago</th>
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
								<span style="color:#e2401c;">Anulada</span>
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
					<td class="colspanchange" colspan="8">No se encontraron comisiones con los filtros seleccionados.</td>
				</tr>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th scope="col" colspan="5" style="text-align:right;">Total de Comisiones (Filtrado):</th>
				<th scope="col">
					<strong><?php echo wc_price( $total_commission_amount ); ?></strong>
				</th>
				<th scope="col" colspan="2"></th>
			</tr>
		</tfoot>
	</table>
</div>