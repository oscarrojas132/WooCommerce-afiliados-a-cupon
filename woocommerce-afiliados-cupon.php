<?php
/**
 * Plugin Name: WooCommerce Coupon Affiliates
 * Description: Affiliate system based on coupons, with panels for affiliates and administrators.
 * Version:     1.0.1
 * Author:      Oscar Rojas
 * Text Domain: wc-afiliados
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class WC_Afiliados
{

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '1.0.1';

    /**
     * Singleton instance.
     *
     * @var WC_Afiliados|null
     */
    private static $instance = null;

    /**
     * Custom table name for sales.
     *
     * @var string
     */
    private $table_name;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'afiliados_ventas';

        $this->includes();
        $this->init_classes();

        register_activation_hook(__FILE__, array($this, 'install'));
        register_deactivation_hook(__FILE__, array($this, 'uninstall'));

        add_action('woocommerce_order_status_changed', array($this, 'track_order'), 10, 4);
        add_action('wc_afiliados_monthly_event', array($this, 'monthly_summary'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Load required plugin files.
     */
    public function includes()
    {
        require_once dirname(__FILE__) . '/includes/class-wc-affiliates-frontend.php';
        require_once dirname(__FILE__) . '/includes/class-wc-affiliates-admin.php';
    }

    /**
     * Initialize the required classes.
     */
    public function init_classes()
    {
        new WC_Affiliates_Frontend();
        new WC_Affiliates_Admin();
    }

    /**
     * Load plugin textdomain for translation.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('wc-afiliados', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Main plugin instance. Ensures only one instance of the plugin is loaded.
     *
     * @return WC_Afiliados
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin activation actions: Create role, create table, and schedule cron.
     */
    public function install()
    {
        global $wpdb;

        // Create the 'vendedor' role.
        $customer_role = get_role('customer');
        add_role(
            'vendedor',
            __('Vendor', 'wc-afiliados'),
            $customer_role ? $customer_role->capabilities : array('read' => true)
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        // Use VARCHAR instead of ENUM for better flexibility with WC statuses.
        $sql = "CREATE TABLE {$this->table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			vendor_id BIGINT(20) UNSIGNED NOT NULL,
			amount DECIMAL(12,2) NOT NULL,
			commission_rate DECIMAL(5,2) NOT NULL,
			date DATETIME NOT NULL,
			order_state ENUM('cancelled','processing','completed') NOT NULL,
            payment_state ENUM('pending_completion', 'ready_to_pay', 'paid', 'cancelled') NOT NULL DEFAULT 'pending_completion',
			coupon_code VARCHAR(50) NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY vendor_id (vendor_id)
		) $charset;";

        dbDelta($sql);

        // Schedule the monthly summary cron job if it's not already scheduled.
        if (!wp_next_scheduled('wc_afiliados_monthly_event')) {
            wp_schedule_single_event(strtotime('first day of next month midnight'), 'wc_afiliados_monthly_event');
        }
    }

    /**
     * Plugin deactivation actions: Clear cron and optionally remove role.
     */
    public function uninstall()
    {
        wp_clear_scheduled_hook('wc_afiliados_monthly_event');

        // The role is not removed if users are still assigned to it.
        // Consider adding a settings page to allow the admin to choose whether to remove data on uninstall.
        remove_role('vendedor');
    }

    /**
     * Tracks order status changes to create or update commission records.
     *
     * @param int      $order_id   The ID of the order.
     * @param string   $old_status The old status of the order.
     * @param string   $new_status The new status of the order.
     * @param WC_Order $order      The order object.
     */
    public function track_order($order_id, $old_status, $new_status, $order)
    {
        global $wpdb;

        $codes = $order->get_coupon_codes();
        if (empty($codes)) {
            return;
        }

        foreach ($codes as $code) {
            $coupon = new WC_Coupon($code);
            if (!$coupon->get_id()) {
                continue;
            }
            $vendor_id = get_post_meta($coupon->get_id(), '_vendedor_id', true);

            if (empty($vendor_id)) {
                continue;
            }

            // BUSINESS LOGIC: Commission is based on the subtotal (before discounts).
            // To use the final total, change to $order->get_total().
            $amount = floatval($order->get_subtotal());
            $order_state = $this->map_status($new_status);
            $payment_state = 'pending_completion'; // Default state.

            if ('completed' === $order_state) {
                $payment_state = 'ready_to_pay';
            } elseif ('cancelled' === $order_state) {
                $payment_state = 'cancelled';
            }

            $existing_record = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM {$this->table_name} WHERE order_id = %d AND vendor_id = %d", $order_id, $vendor_id)
            );

            if ($existing_record) {
                $wpdb->update(
                    $this->table_name,
                    array(
                        'order_state' => $order_state,
                        'payment_state' => $payment_state,
                        'date' => current_time('mysql', false),
                    ),
                    array('id' => $existing_record->id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $this->table_name,
                    array(
                        'order_id' => $order_id,
                        'vendor_id' => $vendor_id,
                        'amount' => $amount,
                        'commission_rate' => 10.00, // Provisional rate, updated monthly.
                        'date' => current_time('mysql', false),
                        'order_state' => $order_state,
                        'payment_state' => $payment_state,
                        'coupon_code' => $code,
                    ),
                    array('%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s')
                );
            }
        }
    }

    /**
     * Maps WooCommerce order statuses to simplified internal statuses.
     *
     * @param string $status The WooCommerce order status.
     * @return string The mapped internal status.
     */
    private function map_status($status)
    {
        switch ($status) {
            case 'cancelled':
            case 'refunded':
            case 'failed':
                return 'cancelled';
            case 'processing':
            case 'on-hold':
                return 'processing';
            case 'completed':
                return 'completed';
            default:
                return 'processing';
        }
    }

    /**
     * Calculates final commissions for the previous month based on sales volume
     * and sends reports. This runs on a monthly cron job.
     */
    public function monthly_summary()
    {
        global $wpdb;

        $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $end_date = date('Y-m-t 23:59:59', strtotime('last day of last month'));

        $vendor_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT vendor_id FROM {$this->table_name} WHERE date BETWEEN %s AND %s", $start_date, $end_date)
        );

        if (empty($vendor_ids)) {
            wp_schedule_single_event(strtotime('first day of next month midnight'), 'wc_afiliados_monthly_event');
            return;
        }

        foreach ($vendor_ids as $vendor_id) {
            $total_sales = $wpdb->get_var(
                $wpdb->prepare("SELECT SUM(amount) FROM {$this->table_name} WHERE vendor_id = %d AND order_state != 'cancelled' AND date BETWEEN %s AND %s", $vendor_id, $start_date, $end_date)
            );

            if (is_null($total_sales)) {
                continue;
            }

            // Determine commission percentage based on sales tiers.
            if ($total_sales >= 10000) {
                $commission_percentage = 25;
            } elseif ($total_sales >= 5000) {
                $commission_percentage = 20;
            } else {
                $commission_percentage = 10; // Base rate
            }

            // Update the commission rate for all of the vendor's sales in that month.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table_name} SET commission_rate = %f WHERE vendor_id = %d AND date BETWEEN %s AND %s",
                    $commission_percentage,
                    $vendor_id,
                    $start_date,
                    $end_date
                )
            );
        }

        wp_schedule_single_event(strtotime('first day of next month midnight'), 'wc_afiliados_monthly_event');
    }
}

/**
 * Main function to start the plugin.
 *
 * @return WC_Afiliados
 */
function wc_afiliados_run()
{
    return WC_Afiliados::instance();
}

// Start the plugin.
wc_afiliados_run();