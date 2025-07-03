<?php
/**
 * Maneja la lógica del panel de administración para la gestión de comisiones.
 *
 * @package WooCommerce Afiliados a Cupón
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Afiliados_Admin {

	/**
	 * Constructor.
	 * Engancha los hooks necesarios para el área de administración.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Agrega la página de comisiones como un submenú de WooCommerce.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',                              // Slug del menú padre (WooCommerce)
			'Comisiones de Vendedores',                 // Título de la página
			'Comisiones Vendedores',                    // Título del menú
			'manage_woocommerce',                       // Capacidad requerida para ver el menú
			'wc-afiliados-comisiones-admin',            // Slug del menú
			array( $this, 'render_admin_panel' )        // Función que renderiza la página
		);
	}

	/**
	 * Renderiza la página de administración de comisiones.
	 * Carga la plantilla 'admin-comisiones.php'.
	 */
	public function render_admin_panel() {
		$plugin_path = dirname( __DIR__ ); // Sube un nivel desde /includes/

		// Aquí se podría incluir lógica para manejar formularios, como marcar comisiones como pagadas.
		// Por ahora, simplemente cargamos la plantilla.
		include $plugin_path . '/templates/admin-comisiones.php';
	}
}