<?php
/**
 * Handles the logic for the admin panel.
 *
 * @package WooCommerce Coupon Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Affiliates_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook for the commissions submenu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// --- NEW: Hook to process bulk actions on the commissions page ---
		add_action( 'admin_init', array( $this, 'process_commission_bulk_actions' ) );

		// Hooks for the users table
		add_filter( 'manage_users_columns', array( $this, 'add_vendor_user_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_vendor_user_column' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( $this, 'add_vendor_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_vendor_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'show_bulk_action_admin_notices' ) ); // Renamed for clarity

		// Hooks for the coupon edit page
		add_action( 'woocommerce_coupon_options', array( $this, 'add_vendor_field_to_coupon' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_vendor_field_from_coupon' ), 10, 2 );
	}
	
	/**
	 * NEW: Processes the "Mark as Paid" bulk action from the commissions panel.
	 */
	public function process_commission_bulk_actions() {
		// Check if we are on the correct admin page, a bulk action has been selected, and the nonce is valid.
		if (
			isset( $_POST['page'] ) && 'wc-afiliados-comisiones-admin' === $_POST['page'] &&
			isset( $_POST['action'] ) && 'mark_paid' === $_POST['action'] &&
			isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'commission_bulk_actions' )
		) {
			// Check if any commission IDs have been selected.
			if ( isset( $_POST['commission_ids'] ) && is_array( $_POST['commission_ids'] ) ) {
				$commission_ids = array_map( 'absint', $_POST['commission_ids'] ); // Sanitize IDs to integers.
				
				if ( ! empty( $commission_ids ) ) {
					global $wpdb;
					$table_name = $wpdb->prefix . 'afiliados_ventas';
					
					// Create a placeholder string for the IN clause.
					$id_placeholders = implode( ', ', array_fill( 0, count( $commission_ids ), '%d' ) );
					
					// Prepare the SQL query to update the payment state.
					$query = $wpdb->prepare(
						"UPDATE {$table_name} SET payment_state = 'paid' WHERE id IN ( {$id_placeholders} )",
						$commission_ids
					);
					
					$updated_count = $wpdb->query( $query );
					
					// Redirect back with a success notice.
					$redirect_to = add_query_arg(
						array(
							'bulk_action_completed' => 'paid',
							'updated_count'         => $updated_count,
						),
						wp_get_referer()
					);
					
					wp_safe_redirect( $redirect_to );
					exit;
				}
			}
		}
	}


	/**
	 * Adds a field to select a vendor on the coupon edit page.
	 */
	public function add_vendor_field_to_coupon( $coupon_id, $coupon ) {
		echo '<div class="options_group">';
		
		$vendor_id = get_post_meta( $coupon_id, '_vendedor_id', true );

		woocommerce_wp_select(
			array(
				'id'          => 'vendedor_id',
				'label'       => 'Associated Vendor',
				'description' => 'Assign this coupon to a user with the Vendor role. This will be used to calculate their commissions.',
				'value'       => $vendor_id,
				'options'     => $this->get_all_vendors_for_dropdown(),
				'desc_tip'    => true,
			)
		);

		echo '</div>';
	}

	/**
	 * Saves the selected vendor ID from the coupon page.
	 */
	public function save_vendor_field_from_coupon( $post_id, $coupon ) {
		if ( isset( $_POST['vendedor_id'] ) ) {
			$vendor_id = intval( $_POST['vendedor_id'] );
			update_post_meta( $post_id, '_vendedor_id', $vendor_id );
		}
	}

	/**
	 * Helper function to get a list of all vendors.
	 */
	private function get_all_vendors_for_dropdown() {
		$vendors = get_users( array( 'role' => 'vendedor' ) );
		$options = array(
			0 => '— None —',
		);

		foreach ( $vendors as $vendor ) {
			$options[ $vendor->ID ] = $vendor->display_name . ' (' . $vendor->user_email . ')';
		}

		return $options;
	}

	/**
	 * Adds the commissions page as a WooCommerce submenu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Vendor Commissions',
			'Vendor Commissions',
			'manage_woocommerce',
			'wc-afiliados-comisiones-admin',
			array( $this, 'render_admin_panel' )
		);
	}

	/**
	 * Renders the commissions admin page.
	 */
	public function render_admin_panel() {
		$plugin_path = dirname( __DIR__ );
		include $plugin_path . '/templates/admin-commissions.php';
	}

	/**
	 * Adds the "Vendor" column to the Users table.
	 */
	public function add_vendor_user_column( $columns ) {
		$columns['is_vendor'] = 'Vendor';
		return $columns;
	}

	/**
	 * Displays the content in the new "Vendor" column.
	 */
	public function render_vendor_user_column( $value, $column_name, $user_id ) {
		if ( 'is_vendor' === $column_name ) {
			if ( user_can( $user_id, 'vendedor' ) ) {
				return '✅ Yes';
			}
			return '❌ No';
		}
		return $value;
	}

	/**
	 * Adds new actions to the "Bulk Actions" dropdown menu.
	 */
	public function add_vendor_bulk_actions( $actions ) {
		$actions['mark_vendor'] = 'Mark as Vendor';
		$actions['unmark_vendor'] = 'Remove as Vendor';
		return $actions;
	}

	/**
	 * Processes the logic when a bulk action is executed on the Users table.
	 */
	public function handle_vendor_bulk_actions( $redirect_to, $action_name, $user_ids ) {
		$users_changed = 0;

		if ( 'mark_vendor' === $action_name ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user && ! user_can( $user, 'vendedor' ) ) {
					$user->add_role( 'vendedor' );
					$users_changed++;
					clean_user_cache( $user_id );
				}
			}
			$redirect_to = add_query_arg( 'vendor_action', 'marked', $redirect_to );
			
		} elseif ( 'unmark_vendor' === $action_name ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user && user_can( $user, 'vendedor' ) ) {
					$is_only_vendor = ( count( $user->roles ) === 1 && 'vendedor' === $user->roles[0] );
					$user->remove_role( 'vendedor' );
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
	 * Displays confirmation notices to the admin for all bulk actions.
	 */
	public function show_bulk_action_admin_notices() {
		// Notice for user role changes
		if ( ! empty( $_REQUEST['vendor_action'] ) && ! empty( $_REQUEST['users_changed'] ) ) {
			$users_changed = intval( $_REQUEST['users_changed'] );
			$message = '';

			if ( 'marked' === $_REQUEST['vendor_action'] ) {
				$message = sprintf( _n( '%s user has been marked as Vendor.', '%s users have been marked as Vendors.', $users_changed, 'wc-afiliados' ), $users_changed );
			} elseif ( 'unmarked' === $_REQUEST['vendor_action'] ) {
				$message = sprintf( _n( '%s user has been unmarked as Vendor.', '%s users have been unmarked as Vendors.', $users_changed, 'wc-afiliados' ), $users_changed );
			}
			
			if ( $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}

		// NEW: Notice for commission status changes
		if ( ! empty( $_REQUEST['bulk_action_completed'] ) && 'paid' === $_REQUEST['bulk_action_completed'] ) {
			$updated_count = isset( $_REQUEST['updated_count'] ) ? intval( $_REQUEST['updated_count'] ) : 0;
			$message       = sprintf(
				_n(
					'%s commission was successfully marked as paid.',
					'%s commissions were successfully marked as paid.',
					$updated_count,
					'wc-afiliados'
				),
				number_format_i18n( $updated_count )
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}
}