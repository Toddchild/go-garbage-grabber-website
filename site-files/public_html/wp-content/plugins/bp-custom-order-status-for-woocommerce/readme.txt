=== Custom Order Status Manager for WooCommerce ===
Contributors: brightvesseldev, kleinmannbrightvessel
Requires at least: 5.0
Tags: custom order status,custom status,order status,statuses
Requires PHP: 7.4
WC tested up to: 10.3.4
Tested up to: 6.8.3
WC requires at least: 4.0
Stable tag: 1.3.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Custom Order Status Manager for WooCommerce plugin allows you to create, delete and edit order statuses to better control the flow of your orders.

== Description ==

**Custom Order Status Manager for WooCommerce** plugin allows you to create, delete and edit order statuses to better control the flow of your orders.

= What is a Custom Order Statuses for WooCommerce? =
Not all order pipelines are created equal. **Customer Order Status Manager for WooCommerce** allows you to create, delete and edit order statuses to better control the flow of your orders.

= Custom Order Status Manager for WooCommerce Features: =
* Create unlimited order statuses
* Customize the status label.
* Customize the status icon or text color.
* Create default statuses for payment methods.
* Enable email notifications for customers or administrators.
* Adds a status column to the order list page.
* Set order status for Default and Third-party Payment methods.

= How to create Custom Order Status? =

After activating the plugin, follow these steps to create new Custom Order Status:

* Navigate to WooCommerce > Order Status menu
* Now click "Add New" button and add order title name
* Enter a slug (Must need to be unique )
* Press “Publish” button
* All Set!

= How to activate the Email template for the created Custom Order Status? =

* Navigate to WooCommerce > Settings > Email Tab
* Select the Email Template name of your Custom Order Status
* Click on the “Enable/Disable” checkbox
* Hit the “Save Changes” button and you are done!

##See what a few WooCommerce store owners are saying about Custom Order Status Manager for WooCommerce:##

> “Such a great simple but powerful plugin to extend WC functionality”.
> - mdf092
>
> “Really useful tool, good support, very pleased with it.”.
> - jwfrag
>
> “ Custom Order Status Manager works flawlessly for me and is compatible with all my other plugins. Highly recommended.”.
> - ozviewer
>

**If you have any issues, please let us know and give us a chance to resolve and fix them.** [Visit Documentation](https://brightplugins.com/docs/customer-order-status-manager-for-woocommerce-documentation/) | [Plugin Support](https://brightplugins.com/support/) | [Upgrade with Club](https://brightplugins.com/product/club-membership/?utm_source=wp_org&utm_medium=cosm&utm_content=plugin-description&utm_campaign=upgrade_club_membership)

## 🔥 ALL ACCESS MEMBERSHIP 🔥 ##

Unlock all 19 premium WooCommerce plugins with one club membership. [Join the Club](https://brightplugins.com/product/club-membership/?utm_source=wp_org&utm_medium=cosm&utm_content=plugin-description&utm_campaign=upgrade_club_membership)

## SOME OF OUR PREMIUM PLUGINS ##

[Additional Variation Images for WooCommerce](https://brightplugins.com/additional-variation-images-for-woocommerce/)
[Min/Max Quantities for WooCommerce](https://brightplugins.com/min-max-quantities-for-woocommerce-review/)
[Pre-Orders for WooCommerce PRO](https://brightplugins.com/woocommerce-preorder-plugin-review/)
[Deposits for WooCommerce PRO](https://brightplugins.com/deposits-for-woocommerce/)

## SOME OF OUR FREE PLUGINS ##
[Order Delivery Date Time & Pickup for WooCommerce](https://wordpress.org/plugins/bp-order-date-time-for-woocommerce/) During the checkout process, customers can effortlessly choose a delivery date and time for their orders.
[Pre-Orders for WooCommerce](https://wordpress.org/plugins/pre-orders-for-woocommerce/)
[Show Stock for WooCommerce](https://wordpress.org/plugins/woo-show-stock/)
[Order Status Control for WooCommerce](https://wordpress.org/plugins/order-status-control-for-woocommerce/)
[Disable Email Notifications for WooCommerce](https://wordpress.org/plugins/woo-disable-email-notifications/)


== Changelog ==

= 1.3.1 -  2 Dec 25 =
* Support for WooCommerce 10.3.x

= 1.3 -  20 Oct 25 =
* Fixed: Payment methods were not saving from the plugin's settings page.

= 1.2.1 -  11 Aug 25 =
* Fixed: Security layer added using 'try-catch' for payment methods incompatible with the free version

= 1.2 -  8 Aug 25 =
* Fixed: Notice: Function _load_textdomain_just_in_time was called incorrectly.
* Fixed: Hide the 'Complete' action for orders that have already been completed - Orders List Page

= 1.1.5 -  4 Dec 24 =
* Fixed: remove dashboard notice

= 1.1.4 -  Date 28 Nov 24 =
* Fixed: `default_body_text` php error
* Update: Plugin Framework
* Support for WooCommerce 9.4.x

= 1.1.3.2 -  Date 24 Dec 23 =
* Fixed: Fatal error on new order admin page
* Fixed: php 8.2 deprecated errors

= 1.1.3.1 -  Date 23 Dec 23 =
* Fixed: The admin notice cannot be closed for some sites due to a third-party plugin conflict.

= 1.1.3 -  Date 6 Nov 23 =
Fixed: revert to the previous version code for admin email issue [1.1.2]

= 1.1.2 -  Date 5 Nov 23 =

* Fixed: New Order admin email notification not working for custom order status


= 1.1.1 -  Date 24 Oct 23 =

* Fixed: Automatically change order status in some cases
* Fixed: Prevent Font Awesome CSS loading for all pages except "My Account"
* Fixed: When performing bulk actions, email notifications are not sent.
* Tweak: Revert back the complete action button for the custom order status column
* Support for WooCommerce 8.2.x

= 1.1 -  Date 8 Aug 23 =

* Fixed: Dokan icons Not showing on the orders page in vendor dashboard
* Fixed: Product stock levels are not changing for custom order status
* Tweak: If the custom status paid option is enabled, display payment details in the order metadata.

* Support for WooCommerce 7.9


= 1.0 -  Date 9 May 23 =

* Fixed: Email template file for specific status not working (child theme)
* Tweak: Code refactor
* Support for WooCommerce 7.6

= 0.12 -  Date 21 Feb 23 =

* Added: Order editable option for custom order status
* Added: Display CPT metadata in the order status column
* Added: COT/HPOS compatibility
* Tweak: Update email template hook
* Support for WooCommerce 7.4

= 0.11 -  Date 11 Jan 23 =

* Fix email recipients
* Fix: Changed plugin load for more compatibility with method payment plugins
* Added support to override default email template
* Added option to grant access to downloadable products
* Update: Option to use icon only on action buttons and show status name 
* Update: codestar Framework
* Experimental: WPML compatibility
* Support for WooCommerce 7.x

= 0.10 -  Date 12 Sep 22 =

* Update: Add option for change background color of custom status
* Update: Multiple recipients option for email when custom order status changes
* Update: Change order status from preorder
* Fix: "Additional content” is not showing on the email
* Support for WooCommerce 6.8


= 0.9 -  Date 12 Aug 22 =

* Update: Add "Order status settings" link into plugin meta
* Fix: WooCommerce inactive notice showing for multisite enable dashboard
* Fix: stripe payment gateway is not showing on the plugin option page
* Support for WooCommerce 6.8

= 0.8 -  Date 26 Jul 22 =
 * Bug fix

= 0.7 -  Date 14 Jun 22 =
 * Bug fix
 * Support for WooCommerce 6.7

= 0.6 -  Date 22 Jun 22 =
 * Bug fix
 * thrid party stripe plugin not showing on the checkout page
 * Support for WooCommerce 6.6.1


= 0.5 -  Date 10 Jun 22 =
 * Bug fix
 * Support for WooCommerce 6.5.1
 * Support for WordPress 6.x

= 0.4 -  Date 11 Mar 22 =
 * Update: Update plugin structure for payment gatways conflicts

= 0.3 -  Date 11 Mar 22 =
 * Fix: Fatal error

= 0.2 -  Released on 18 Feb 22 =
 * Fix: order not showing if status slug have capital letter

= 0.1 =
* first release - 17 Feb 22
