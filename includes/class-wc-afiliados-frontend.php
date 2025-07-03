<?php
/**
 * Maneja la lógica del frontend para los vendedores.
 *
 * @package WooCommerce Afiliados a Cupón
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Afiliados_Frontend {

	/**
	 * Constructor.
	 * Engancha todos los hooks necesarios para el frontend.
	 */
	public function __construct() {
		// Hooks para crear el panel en "Mi Cuenta"
		add_action( 'init', array( $this, 'register_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu' ) );
		add_action( 'woocommerce_account_afiliados_endpoint', array( $this, 'render_affiliates_panel' ) );
	}

	/**
	 * Registrar el endpoint "afiliados" para la URL.
	 */
	public function register_endpoints() {
		add_rewrite_endpoint( 'afiliados', EP_PAGES );
	}

	/**
	 * Agregar el ítem "Afiliados" al menú de "Mi Cuenta".
	 *
	 * @param array $items Los ítems del menú existentes.
	 * @return array Los ítems del menú modificados.
	 */
	public function add_account_menu( $items ) {
		// Solo mostramos el menú si el usuario tiene el rol 'vendedor'
		if ( current_user_can( 'vendedor' ) ) {
			// Creamos un nuevo array para reordenar y poner nuestro link antes de 'Salir'
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );
			$items['afiliados'] = 'Panel de Vendedor';
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Renderizar el contenido del panel de afiliados.
	 * Carga la plantilla 'affiliates.php'.
	 */
	public function render_affiliates_panel() {
		// Ya no se necesita la comprobación de is_user_logged_in() porque este hook solo se dispara para usuarios logueados.
		
		// Obtiene la ruta de la carpeta principal del plugin
		$plugin_path = dirname( __DIR__ ); // Sube un nivel desde /includes/

		wc_get_template( 
			'myaccount/affiliates.php', 
			array( 'vendor_id' => get_current_user_id() ), 
			'', 
			$plugin_path . '/templates/' 
		);
	}
}