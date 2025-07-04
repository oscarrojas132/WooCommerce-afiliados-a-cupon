<?php
/**
 * Plugin Name: WooCommerce Afiliados a Cupón
 * Description: Sistema de afiliados basado en cupones, con paneles para afiliados y administradores.
 * Version:     1.0.0
 * Author:      Oscar Rojas
 * Text Domain: wc-afiliados
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Afiliados {

    /** Singleton instance */
    private static $instance = null;

    /** Tabla personalizada */
    private $table_name;

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'afiliados_ventas';

        // --- CARGA DE ARCHIVOS ---
        $this->includes();

        // --- INICIALIZACIÓN DE CLASES ---
        $this->init_classes();

        register_activation_hook( __FILE__, array( $this, 'install' ) );
        register_deactivation_hook( __FILE__, array( $this, 'uninstall' ) );



        add_action( 'woocommerce_order_status_changed', array( $this, 'track_order' ), 10, 4 );

        // Cron para resumen mensual
        add_action( 'wc_afiliados_monthly_event', array( $this, 'monthly_summary' ) );
    }

    /**
	 * Carga los archivos necesarios.
	 */
	public function includes() {
		require_once dirname( __FILE__ ) . '/includes/class-wc-afiliados-frontend.php';
		require_once dirname( __FILE__ ) . '/includes/class-wc-afiliados-admin.php';
	}

    /**
	 * Inicializa las clases cargadas.
	 */
	public function init_classes() {
		new WC_Afiliados_Frontend();
		new WC_Afiliados_Admin();
	}

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
	 * Acciones de activación: Crear rol, crear tabla y programar cron.
	 */
    public function install() {
        global $wpdb;

		// Crear el rol 'vendedor' con las mismas capacidades que un 'customer'.
		// Es seguro ejecutarlo varias veces, no creará duplicados.
		$customer_role = get_role( 'customer' );
		add_role( 'vendedor', 'Vendedor', $customer_role ? $customer_role->capabilities : array( 'read' => true ) );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            vendor_id BIGINT(20) UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            commission_rate DECIMAL(5,2) NOT NULL,
            date DATETIME NOT NULL,
            order_state ENUM('cancelado','en_proceso','completado') NOT NULL,
            payment_state ENUM('pendiente_finalizacion', 'lista_para_pagar', 'pagado', 'cancelado') NOT NULL DEFAULT 'pendiente_finalizacion',
            coupon_code VARCHAR(50) NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY vendor_id (vendor_id)
        ) $charset;";

        dbDelta( $sql );

        if ( ! wp_next_scheduled( 'wc_afiliados_monthly_event' ) ) {
            // 1 de cada mes a 00:00 UTC-5
            // Se programa un evento único que se re-programará a sí mismo.
            wp_schedule_single_event( strtotime( 'first day of next month midnight' ), 'wc_afiliados_monthly_event' );
        }
    }

    /**
	 * Acciones de desactivación: Limpiar cron y eliminar rol.
	 */
    public function uninstall() {
        wp_clear_scheduled_hook( 'wc_afiliados_monthly_event' );

		// Elimina el rol 'vendedor' del sistema.
		// WordPress no lo eliminará si todavía hay usuarios con este rol asignado.
		remove_role( 'vendedor' );
    }

    /** Hook de cambio de estado de orden */
    public function track_order( $order_id, $old_status, $new_status, $order ) {
        global $wpdb;

        // Obtener cupones usados
        $codes = $order->get_coupon_codes();
        if ( empty( $codes ) ) {
            return;
        }

        foreach ( $codes as $code ) {
            // Obtener metadata del cupón => vendor_id
            $coupon = new WC_Coupon( $code );
            $vendor_id = $coupon->get_meta( 'vendor_id' );
            if ( ! $vendor_id ) {
                continue;
            }

            $amount = floatval( $order->get_subtotal() );
            $state = $this->map_status( $new_status );
            $payment_state = '';
            if ( 'cancelado' === $state ) {
                $payment_state = 'NA';
            }

            // Comisión provisional. Se calculará el porcentaje real en el resumen mensual.
            $commission = 0;

            // Verificar si ya existe un registro para esta orden y vendedor
            $existing_record = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE order_id = %d AND vendor_id = %d",
                $order_id, $vendor_id
            ) );

            if ( $existing_record ) {
                // Si existe, actualizar el estado y la fecha
                $wpdb->update(
                    $this->table_name,
                    array(
                        'order_state'   => $state,
                        'payment_state' => $payment_state,
                        'date'          => current_time( 'mysql', false ),
                    ),
                    array( 'id' => $existing_record->id ), // WHERE
                    array( '%s', '%s', '%s' ), // format data
                    array( '%d' ) // format WHERE
                );
            } else {
                // Si no existe, insertar un nuevo registro
                $wpdb->insert(
                    $this->table_name,
                    array(
                        'order_id'      => $order_id,
                        'vendor_id'     => $vendor_id,
                        'amount'        => $amount,
                        'commission_rate'    => $commission,
                        'date'          => current_time( 'mysql', false ),
                        'order_state'   => $state,
                        'payment_state' => $payment_state,
                        'coupon_code'   => $code,
                    ),
                    array( '%d','%d','%f','%f','%s','%s','%s','%s' )
                );
            }
        }
    }

    private function map_status( $status ) {
        switch ( $status ) {
            case 'cancelled':
                return 'cancelado';
            case 'refunded':
                return 'cancelado';
            case 'processing':
                return 'en_proceso';
            case 'on-hold':
                return 'en_proceso';
            case 'completed':
                return 'completado';
            default:
                return 'en_proceso';
        }
    }

    /** Resumen mensual: calcular comisiones y enviar reportes */
    public function monthly_summary() {
        global $wpdb;

        // 1. Definir el rango de fechas para el mes anterior
        $start_date = date( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
        $end_date   = date( 'Y-m-t 23:59:59', strtotime( 'last day of last month' ) );

        // 2. Obtener todos los vendedores con ventas en el mes anterior
        $vendor_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT vendor_id FROM {$this->table_name} WHERE date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ) );

        if ( ! empty( $vendor_ids ) ) {
            foreach ( $vendor_ids as $vendor_id ) {
                // 3. Calcular el total de ventas para el vendedor en el mes (sin importar estado)
                $total_sales = $wpdb->get_var( $wpdb->prepare(
                    "SELECT SUM(amount) FROM {$this->table_name} WHERE vendor_id = %d AND date BETWEEN %s AND %s",
                    $vendor_id,
                    $start_date,
                    $end_date
                ) );

                if ( is_null( $total_sales ) ) {
                    continue;
                }

                // 4. Determinar el PORCENTAJE de comisión según los niveles
                $commission_percentage = 10; // 10% por defecto (< 5000)
                if ( $total_sales >= 10000 ) {
                    $commission_percentage = 25; // 25%
                } elseif ( $total_sales >= 5000 ) {
                    $commission_percentage = 20; // 20%
                }

                // 5. Actualizar la columna 'commission' con el porcentaje para todas las ventas del vendedor en ese mes
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$this->table_name} SET commission_rate = %f WHERE vendor_id = %d AND date BETWEEN %s AND %s",
                    $commission_percentage, $vendor_id, $start_date, $end_date
                ) );
            }
        }
        
        // Volver a programar la tarea para el primer día del mes siguiente.
        wp_schedule_single_event( strtotime( 'first day of next month midnight' ), 'wc_afiliados_monthly_event' );
    }

}

/**
 * Función principal para iniciar el plugin.
 */
function wc_afiliados_run() {
    return WC_Afiliados::instance();
}

// Iniciar el plugin.
wc_afiliados_run();
