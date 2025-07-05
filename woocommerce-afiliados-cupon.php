<?php
/**
 * Plugin Name: WooCommerce Coupon Affiliates
 * Description: Affiliate system based on coupons, with panels for affiliates and administrators.
 * Version:     1.0.0
 * Author:      Oscar Rojas
 * Text Domain: wc-afiliados
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Afiliados
{

    /** Singleton instance */
    private static $instance = null;

    /** Custom table */
    private $table_name;

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'afiliados_ventas';

        // --- LOAD FILES ---
        $this->includes();

        // --- INITIALIZE CLASSES ---
        $this->init_classes();

        register_activation_hook(__FILE__, array($this, 'install'));
        register_deactivation_hook(__FILE__, array($this, 'uninstall'));

        add_action('woocommerce_order_status_changed', array($this, 'track_order'), 10, 4);

        // Cron for monthly summary
        add_action('wc_afiliados_monthly_event', array($this, 'monthly_summary'));
    }

    /**
     * Load required files.
     */
    public function includes()
    {
        require_once dirname(__FILE__) . '/includes/class-wc-affiliates-frontend.php';
        require_once dirname(__FILE__) . '/includes/class-wc-affiliates-admin.php';
    }

    /**
     * Initialize loaded classes.
     */
    public function init_classes()
    {
        new WC_Affiliates_Frontend();
        new WC_Affiliates_Admin();
    }

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Activation actions: Create role, create table, and schedule cron.
     */
    public function install()
    {
        global $wpdb;

        // Create the 'vendedor' role with the same capabilities as 'customer'.
        // Safe to run multiple times, will not create duplicates.
        $customer_role = get_role('customer');
        add_role('vendedor', 'Vendedor', $customer_role ? $customer_role->capabilities : array('read' => true));

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
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

        if (!wp_next_scheduled('wc_afiliados_monthly_event')) {
            // 1st of each month at 00:00 UTC-5
            // Schedule a single event that will reschedule itself.
            wp_schedule_single_event(strtotime('first day of next month midnight'), 'wc_afiliados_monthly_event');
        }
    }

    /**
     * Deactivation actions: Clear cron and remove role.
     */
    public function uninstall()
    {
        wp_clear_scheduled_hook('wc_afiliados_monthly_event');

        // Remove the 'vendedor' role from the system.
        // WordPress will not remove it if there are still users with this role assigned.
        remove_role('vendedor');
    }

    /** Order status change hook */
    public function track_order($order_id, $old_status, $new_status, $order)
    {
        global $wpdb;

        // Get used coupons
        $codes = $order->get_coupon_codes();
        if (empty($codes)) {
            return;
        }

        foreach ($codes as $code) {
            // Get coupon metadata => vendor_id
            $coupon = new WC_Coupon($code);
            $vendor_id = get_post_meta($coupon->get_id(), '_vendedor_id', true);

            if (!$vendor_id) {
                continue;
            }

            $amount = floatval($order->get_subtotal());
            $order_state = $this->map_status($new_status);

            // Determine payment state based on order status.
            $payment_state = 'pending_completion'; // Default value for 'processing'.
            if ('completed' === $order_state) {
                $payment_state = 'ready_to_pay';
            } elseif ('cancelled' === $order_state) {
                $payment_state = 'cancelled';
            }

            // Provisional commission. The real percentage will be calculated in the monthly summary.
            $commission = 10.00; // 10% default commission

            // Check if a record already exists for this order and vendor
            $existing_record = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE order_id = %d AND vendor_id = %d",
                $order_id,
                $vendor_id
            ));

            if ($existing_record) {
                // If exists, update state and date
                $wpdb->update(
                    $this->table_name,
                    array(
                        'order_state' => $order_state,
                        'payment_state' => $payment_state,
                        'date' => current_time('mysql', false),
                    ),
                    array('id' => $existing_record->id), // WHERE
                    array('%s', '%s', '%s'), // format data
                    array('%d') // format WHERE
                );
            } else {
                // If not exists, insert a new record
                $wpdb->insert(
                    $this->table_name,
                    array(
                        'order_id' => $order_id,
                        'vendor_id' => $vendor_id,
                        'amount' => $amount,
                        'commission_rate' => $commission,
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

    private function map_status($status)
    {
        switch ($status) {
            case 'cancelled':
                return 'cancelled';
            case 'refunded':
                return 'cancelled';
            case 'processing':
                return 'processing';
            case 'on-hold':
                return 'processing';
            case 'completed':
                return 'completed';
            default:
                return 'processing';
        }
    }

    /** Monthly summary: calculate commissions and send reports */
    public function monthly_summary()
    {
        global $wpdb;

        // 1. Define date range.
        $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $end_date = date('Y-m-t 23:59:59', strtotime('last day of last month'));

        // 2. Get all vendors with sales in the date range.
        $vendor_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT vendor_id FROM {$this->table_name} WHERE date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        if (empty($vendor_ids)) {
            wp_schedule_single_event(strtotime('first day of next month midnight'), 'wc_afiliados_monthly_event');
            return;
        }

        foreach ($vendor_ids as $vendor_id) {
            // 3. Calculate total sales for the vendor.
            $total_sales = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM {$this->table_name} WHERE vendor_id = %d AND date BETWEEN %s AND %s",
                $vendor_id,
                $start_date,
                $end_date
            ));

            if (is_null($total_sales)) {
                continue;
            }

            // 4. Determine commission percentage.
            $commission_percentage = 10; // Base rate
            if ($total_sales >= 10000) {
                $commission_percentage = 25;
            } elseif ($total_sales >= 5000) {
                $commission_percentage = 20;
            }

            // 5. Update 'commission_rate' column.
            $rows_affected = $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} SET commission_rate = %f WHERE vendor_id = %d AND date BETWEEN %s AND %s",
                $commission_percentage,
                $vendor_id,
                $start_date,
                $end_date
            ));
        }

        // Reschedule the task for the first day of the next month.
        wp_schedule_single_event(strtotime('first day of next month midnight'), 'wc_afiliados_monthly_event');
    }

}

/**
 * Main function to start the plugin.
 */
function wc_afiliados_run()
{
    return WC_Afiliados::instance();
}

// Start the plugin.
wc_afiliados_run();

