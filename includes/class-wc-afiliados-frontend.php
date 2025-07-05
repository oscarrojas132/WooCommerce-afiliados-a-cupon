<?php
/**
 * Handles frontend logic for vendors.
 *
 * @package WooCommerce Coupon Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Afiliados_Frontend {

	/**
	 * Constructor.
	 * Hooks all necessary frontend actions.
	 */
	public function __construct() {
		// Hooks to create the panel in "My Account"
		add_action( 'init', array( $this, 'register_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu' ) );
		add_action( 'woocommerce_account_afiliados_endpoint', array( $this, 'render_affiliates_panel' ) );
	}

	/**
	 * Register the "afiliados" endpoint for the URL.
	 */
	public function register_endpoints() {
		add_rewrite_endpoint( 'afiliados', EP_PAGES );
	}

	/**
	 * Add the "Afiliados" item to the "My Account" menu.
	 *
	 * @param array $items Existing menu items.
	 * @return array Modified menu items.
	 */
	public function add_account_menu( $items ) {
		// Only show the menu if the user has the 'vendedor' role
		if ( current_user_can( 'vendedor' ) ) {
			// Create a new array to reorder and put our link before 'Logout'
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );
			$items['afiliados'] = 'Vendor Panel';
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Render the content of the affiliates panel.
	 * Loads the 'affiliates.php' template.
	 */
	public function render_affiliates_panel() {
		// No need to check is_user_logged_in() because this hook only fires for logged-in users.
		
		// Get the main plugin folder path
		$plugin_path = dirname( __DIR__ ); // Go up one level from /includes/

		wc_get_template( 
			'myaccount/affiliates.php', 
			array( 'vendor_id' => get_current_user_id() ), 
			'', 
			$plugin_path . '/templates/' 
		);
	}
}