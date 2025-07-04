<?php
/**
 * Maneja la lógica del panel de administración.
 *
 * @package WooCommerce Afiliados a Cupón
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Afiliados_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook para el submenú de comisiones
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Hooks para la tabla de usuarios
		add_filter( 'manage_users_columns', array( $this, 'add_vendor_user_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_vendor_user_column' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( $this, 'add_vendor_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_vendor_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'vendor_bulk_action_admin_notice' ) );
		
		// --- NUEVO: Hooks para la página de edición de cupones ---
		add_action( 'woocommerce_coupon_options', array( $this, 'add_vendor_field_to_coupon' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_vendor_field_from_coupon' ), 10, 2 );
	}

	//
	// ... Aquí van todas tus funciones anteriores: add_admin_menu, render_admin_panel, add_vendor_user_column, etc. ...
	//

	/**
	 * NUEVO: Añade un campo para seleccionar un vendedor en la página de edición de cupones.
	 */
	public function add_vendor_field_to_coupon( $coupon_id, $coupon ) {
		echo '<div class="options_group">';
		
		// Obtenemos el ID del vendedor que ya está guardado, si existe.
		$vendor_id = get_post_meta( $coupon_id, '_vendedor_id', true );

		woocommerce_wp_select(
			array(
				'id'          => 'vendedor_id',
				'label'       => 'Vendedor Asociado',
				'description' => 'Asigna este cupón a un usuario con el rol de Vendedor. Esto se usará para calcular sus comisiones.',
				'value'       => $vendor_id,
				'options'     => $this->get_all_vendors_for_dropdown(),
				'desc_tip'    => true,
			)
		);

		echo '</div>';
	}

	/**
	 * NUEVO: Guarda el ID del vendedor seleccionado desde la página del cupón.
	 */
	public function save_vendor_field_from_coupon( $post_id, $coupon ) {
		if ( isset( $_POST['vendedor_id'] ) ) {
			$vendor_id = intval( $_POST['vendedor_id'] );
			update_post_meta( $post_id, '_vendedor_id', $vendor_id );
		}
	}

	/**
	 * NUEVO: Función auxiliar para obtener una lista de todos los vendedores.
	 */
	private function get_all_vendors_for_dropdown() {
		$vendors = get_users( array( 'role' => 'vendedor' ) );
		$options = array(
			0 => '— Ninguno —', // Opción para desasignar
		);

		foreach ( $vendors as $vendor ) {
			$options[ $vendor->ID ] = $vendor->display_name . ' (' . $vendor->user_email . ')';
		}

		return $options;
	}

	/**
	 * Agrega la página de comisiones como un submenú de WooCommerce.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Comisiones de Vendedores',
			'Comisiones Vendedores',
			'manage_woocommerce',
			'wc-afiliados-comisiones-admin',
			array( $this, 'render_admin_panel' )
		);
	}

	/**
	 * Renderiza la página de administración de comisiones.
	 */
	public function render_admin_panel() {
		$plugin_path = dirname( __DIR__ );
		include $plugin_path . '/templates/admin-comisiones.php';
	}

	/**
	 * PASO 1: Añade la columna "Vendedor" a la tabla de Usuarios.
	 */
	public function add_vendor_user_column( $columns ) {
		$columns['is_vendor'] = 'Vendedor';
		return $columns;
	}

	/**
	 * PASO 2: Muestra el contenido en la nueva columna "Vendedor".
	 */
	public function render_vendor_user_column( $value, $column_name, $user_id ) {
		if ( 'is_vendor' === $column_name ) {
			// Usamos la función estándar de WordPress, que es más fiable
			// y respeta el caché que limpiamos en la acción en lote.
			if ( user_can( $user_id, 'vendedor' ) ) {
				return '✅ Sí';
			}
			return '❌ No';
		}
		return $value;
	}

	/**
	 * PASO 3: Agrega las nuevas acciones al menú desplegable "Acciones en lote".
	 */
	public function add_vendor_bulk_actions( $actions ) {
		$actions['mark_vendor'] = 'Marcar como Vendedor';
		$actions['unmark_vendor'] = 'Quitar como Vendedor';
		return $actions;
	}

	/**
	 * PASO 4: Procesa la lógica cuando se ejecuta una acción en lote.
	 * VERSIÓN CORREGIDA Y MEJORADA.
	 */
	public function handle_vendor_bulk_actions( $redirect_to, $action_name, $user_ids ) {
		$users_changed = 0;

		if ( 'mark_vendor' === $action_name ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				// Usamos user_can() para una comprobación más robusta.
				if ( $user && ! user_can( $user, 'vendedor' ) ) {
					$user->add_role( 'vendedor' );
					$users_changed++;
					
					// ¡LÍNEA CLAVE! Limpiamos el caché para este usuario.
					clean_user_cache( $user_id );
				}
			}
			$redirect_to = add_query_arg( 'vendor_action', 'marked', $redirect_to );
			
		} elseif ( 'unmark_vendor' === $action_name ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				// Usamos user_can() para una comprobación más robusta.
				if ( $user && user_can( $user, 'vendedor' ) ) {
					// Verificamos si 'vendedor' es el único rol del usuario ANTES de eliminarlo.
					$is_only_vendor = ( count( $user->roles ) === 1 && 'vendedor' === $user->roles[0] );

					$user->remove_role( 'vendedor' );

					// Si era su único rol, le asignamos 'customer' para que no se quede sin rol.
					// Esta comprobación se hace ahora de forma más segura.
					if ( $is_only_vendor ) {
						$user->add_role( 'customer' );
					}
					$users_changed++;

					clean_user_cache( $user_id );
				}
			}
			$redirect_to = add_query_arg( 'vendor_action', 'unmarked', $redirect_to );
		}

		if ( $users_changed > 0 ) {
			$redirect_to = add_query_arg( 'users_changed', $users_changed, $redirect_to );
		}

		return $redirect_to;
	}

	/**
	 * PASO 5: Muestra un aviso de confirmación al administrador.
	 */
	public function vendor_bulk_action_admin_notice() {
		if ( ! empty( $_REQUEST['vendor_action'] ) && ! empty( $_REQUEST['users_changed'] ) ) {
			$users_changed = intval( $_REQUEST['users_changed'] );
			$message = '';

			if ( $_REQUEST['vendor_action'] === 'marked' ) {
				$message = sprintf( _n( '%s usuario ha sido marcado como Vendedor.', '%s usuarios han sido marcados como Vendedores.', $users_changed, 'wc-afiliados' ), $users_changed );
			} elseif ( $_REQUEST['vendor_action'] === 'unmarked' ) {
				$message = sprintf( _n( 'A %s usuario se le ha quitado el rol de Vendedor.', 'A %s usuarios se les ha quitado el rol de Vendedor.', $users_changed, 'wc-afiliados' ), $users_changed );
			}
			
			if ( $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
			}
		}
	}
}