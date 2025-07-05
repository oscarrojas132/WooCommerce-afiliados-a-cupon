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

		// Hooks for the users table
		add_filter( 'manage_users_columns', array( $this, 'add_vendor_user_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_vendor_user_column' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( $this, 'add_vendor_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_vendor_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'vendor_bulk_action_admin_notice' ) );
		
		// --- NEW: Hooks for the coupon edit page ---
		add_action( 'woocommerce_coupon_options', array( $this, 'add_vendor_field_to_coupon' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_vendor_field_from_coupon' ), 10, 2 );
	}

	/**
	 * NEW: Adds a field to select a vendor on the coupon edit page.
	 */
	public function add_vendor_field_to_coupon( $coupon_id, $coupon ) {
		echo '<div class="options_group">';
		
		// Get the vendor ID already saved, if it exists.
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
	 * NEW: Saves the selected vendor ID from the coupon page.
	 */
	public function save_vendor_field_from_coupon( $post_id, $coupon ) {
		if ( isset( $_POST['vendedor_id'] ) ) {
			$vendor_id = intval( $_POST['vendedor_id'] );
			update_post_meta( $post_id, '_vendedor_id', $vendor_id );
		}
	}

	/**
	 * NEW: Helper function to get a list of all vendors.
	 */
	private function get_all_vendors_for_dropdown() {
		$vendors = get_users( array( 'role' => 'vendedor' ) );
		$options = array(
			0 => '— None —', // Option to unassign
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
	 * STEP 1: Adds the "Vendor" column to the Users table.
	 */
	public function add_vendor_user_column( $columns ) {
		$columns['is_vendor'] = 'Vendor';
		return $columns;
	}

	/**
	 * STEP 2: Displays the content in the new "Vendor" column.
	 */
	public function render_vendor_user_column( $value, $column_name, $user_id ) {
		if ( 'is_vendor' === $column_name ) {
			// Use the standard WordPress function, which is more reliable
			// and respects the cache we clear in the bulk action.
			if ( user_can( $user_id, 'vendedor' ) ) {
				return '✅ Yes';
			}
			return '❌ No';
		}
		return $value;
	}

	/**
	 * STEP 3: Adds new actions to the "Bulk Actions" dropdown menu.
	 */
	public function add_vendor_bulk_actions( $actions ) {
		$actions['mark_vendor'] = 'Mark as Vendor';
		$actions['unmark_vendor'] = 'Remove as Vendor';
		return $actions;
	}

	/**
	 * STEP 4: Processes the logic when a bulk action is executed.
	 * FIXED AND IMPROVED VERSION.
	 */
	public function handle_vendor_bulk_actions( $redirect_to, $action_name, $user_ids ) {
		$users_changed = 0;

		if ( 'mark_vendor' === $action_name ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				// Use user_can() for a more robust check.
				if ( $user && ! user_can( $user, 'vendedor' ) ) {
					$user->add_role( 'vendedor' );
					$users_changed++;
					
					// KEY LINE! Clear the cache for this user.
					clean_user_cache( $user_id );
				}
			}
			$redirect_to = add_query_arg( 'vendor_action', 'marked', $redirect_to );
			
		} elseif ( 'unmark_vendor' === $action_name ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				// Use user_can() for a more robust check.
				if ( $user && user_can( $user, 'vendedor' ) ) {
					// Check if 'vendedor' is the user's only role BEFORE removing it.
					$is_only_vendor = ( count( $user->roles ) === 1 && 'vendedor' === $user->roles[0] );

					$user->remove_role( 'vendedor' );

					// If it was their only role, assign 'customer' so they are not left without a role.
					// This check is now done more safely.
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
	 * STEP 5: Displays a confirmation notice to the admin.
	 */
	public function vendor_bulk_action_admin_notice() {
		if ( ! empty( $_REQUEST['vendor_action'] ) && ! empty( $_REQUEST['users_changed'] ) ) {
			$users_changed = intval( $_REQUEST['users_changed'] );
			$message = '';

			if ( $_REQUEST['vendor_action'] === 'marked' ) {
				$message = sprintf( _n( '%s user has been marked as Vendor.', '%s users have been marked as Vendors.', $users_changed, 'wc-afiliados' ), $users_changed );
			} elseif ( $_REQUEST['vendor_action'] === 'unmarked' ) {
				$message = sprintf( _n( '%s user has been unmarked as Vendor.', '%s users have been unmarked as Vendors.', $users_changed, 'wc-afiliados' ), $users_changed );
			}
			
			if ( $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
			}
		}
	}
}