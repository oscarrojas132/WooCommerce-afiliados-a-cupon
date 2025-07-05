# WooCommerce Affiliates to Coupon

An advanced commission system for WooCommerce, designed for vendors or affiliates who operate with coupon codes and a monthly tiered commission model.

This plugin extends WooCommerce functionality to create a custom commission system, ideal for service or development companies where commission payment is not tied to the sale, but to the completion of the project.

---

## Description

This plugin solves a specific business need: managing a team of external vendors who earn commissions based on monthly performance by quantity, not value. Unlike standard affiliate plugins, the final commission for all sales in a month is calculated retroactively on the first day of the following month, ensuring the correct rate is applied according to the total sales volume.

The system decouples commission calculation from its payment. A commission is considered "earned" at the end of the month, but only becomes "payable" once an administrator manually marks the associated project as "Completed".

## Main Features

* **"Vendor" User Role:** Creates a specific role for your affiliates that inherits the capabilities of a regular customer.
* **Coupon Assignment:** Easily assign a WooCommerce coupon code to a specific vendor.
* **Integrated Vendor Dashboard:** Adds a new tab in the WooCommerce "My Account" area so vendors can see in real time the status of their sales, pending commissions, payable commissions, and their history.
* **Centralized Admin Panel:** Provides the site administrator with a dedicated page to view all commissions, filter them by vendor or status, and manage payments.
* **Monthly Tiered Commission System:** Calculates commissions based on the total number of sales a vendor achieves within a calendar month.
* **Automated Calculation via Cron:** Uses WordPress's scheduled task system (WP-Cron) to automatically run the complex commission calculation on the first day of each month.
* **Custom Database Table:** Stores all commission data in a custom table (`wp_afiliados_ventas`) for optimal performance and to avoid overloading WordPress's `postmeta` table.
* **Payment Approval Workflow:** Includes a workflow for the administrator to mark projects as "completed", which changes the commission status to "Payable".

## Installation

#### 1. From the WordPress Dashboard (Recommended)

1.  Compress the entire plugin folder into a `.zip` file.
2.  Go to your WordPress admin dashboard and navigate to `Plugins > Add New`.
3.  Click the `Upload Plugin` button at the top of the page.
4.  Select the `.zip` file you just created and click `Install Now`.
5.  Once installed, click `Activate Plugin`.

#### 2. Manually (via FTP/SFTP)

1.  Unzip the plugin `.zip` file on your computer.
2.  Connect to your server using an FTP client (like FileZilla).
3.  Navigate to the `wp-content/plugins/` directory of your WordPress installation.
4.  Upload the entire plugin folder (`woocommerce-afiliados-cupon`) to this directory.
5.  Go to your WordPress admin dashboard, navigate to `Plugins`, and look for "WooCommerce Affiliates to Coupon" in the list.
6.  Click `Activate`.

## Workflow (Usage)

1.  **Initial Setup:**
    * Make sure your vendor users are assigned the "Vendor" role.
    * Create coupons in `WooCommerce > Marketing > Coupons` and, in the custom field, assign each coupon to the corresponding vendor.

2.  **Sales Cycle:**
    * A customer uses a vendor's coupon to make a purchase.
    * The plugin automatically records the sale in the commissions table with an initial status and a provisional commission of 0.

3.  **End-of-Month Process (Automatic):**
    * On the first day of each month, the scheduled task runs in the background.
    * The system calculates the total number of sales from the previous month for each vendor, determines the correct commission rate according to the defined tiers, and updates all commissions for that month with the final amount.

4.  **Project Completion:**
    * When a project/service associated with an order is completed, the administrator must go to the edit page for that order in WooCommerce.
    * There, they will find a "Commission Management" panel where they can mark the project as "Completed".
    * This changes the commission status to "Payable".

5.  **Management and Payments:**
    * The vendor can see in their dashboard which commissions are ready to be paid.
    * The administrator can see a summary of all payable commissions in the plugin's admin panel and proceed to make payments externally (bank transfer, PayPal, etc.).

## Screenshots

**1. Vendor Dashboard in "My Account"**

Shows the vendor their sales, the status of each commission, and totals.

![Vendor Dashboard in My Account]()

---

**2. Commissions Admin Panel**

View for the site administrator, with tools to filter and manage all commissions.

![Commissions Admin Panel]()

---

**3. Assigning a Vendor to a Coupon**

Custom field that appears on the WooCommerce coupon edit page.

![Assigning a Vendor to a Coupon]()

---

**4. Management Metabox in the Order**

Allows the administrator to mark a project as "Completed" directly from the WooCommerce order.

![Management Metabox in the Order]()


## License

This plugin is released under the Apache 2.0 License.
See: https://www.apache.org/licenses/LICENSE-2.0