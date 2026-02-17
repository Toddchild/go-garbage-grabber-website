<?php
/**
 * Plugin Name: QP Create Order (Tiny)
 * Description: REST endpoint to create a WooCommerce order from Instant Quick Pick checkout.
 * Version: 0.1
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register REST route
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'qp/v1', '/create-order', array(
        'methods'             => 'POST',
        'callback'            => 'qp_create_order_handler',
        'permission_callback' => '__return_true',
    ) );
} );

/**
 * Create a WC order from incoming payload and return order pay URL
 *
 * Expected POST JSON payload (example keys used by the checkout page):
 * {
 *   pid, qp_item, qp_name, qp_price, qp_env_fee, qp_img,
 *   customer_name, customer_phone, customer_email,
 *   address1, address2, city, postal,
 *   pickup_location, other_details, stairs, rush (bool), rush_fee,
 *   pickup_date, subtotal
 * }
 */
function qp_create_order_handler( WP_REST_Request $request ) {
    if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_create_order' ) ) {
        return new WP_REST_Response( array( 'error' => 'WooCommerce not active' ), 500 );
    }

    $body = $request->get_json_params();

    // Basic validation
    if ( empty( $body['customer_name'] ) || empty( $body['customer_phone'] ) || empty( $body['address1'] ) ) {
        return new WP_REST_Response( array( 'error' => 'Missing required customer fields' ), 400 );
    }

    // Create the order
    $order = wc_create_order();

    // Set billing fields (you can also set shipping if different)
    $order->set_billing_first_name( sanitize_text_field( $body['customer_name'] ) );
    $order->set_billing_phone( sanitize_text_field( $body['customer_phone'] ) );
    if ( ! empty( $body['customer_email'] ) ) {
        $order->set_billing_email( sanitize_email( $body['customer_email'] ) );
    }

    // Address
    $order->set_billing_address_1( sanitize_text_field( $body['address1'] ) );
    if ( ! empty( $body['address2'] ) ) {
        $order->set_billing_address_2( sanitize_text_field( $body['address2'] ) );
    }
    if ( ! empty( $body['city'] ) ) {
        $order->set_billing_city( sanitize_text_field( $body['city'] ) );
    }
    if ( ! empty( $body['postal'] ) ) {
        $order->set_billing_postcode( sanitize_text_field( $body['postal'] ) );
    }

    // Add fees / line items as fee items so we don't require a product ID
    // 1) Base service (using a fee item with the service name)
    $base = floatval( $body['qp_price'] ?? 0 );
    if ( $base > 0 ) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Service: ' . sanitize_text_field( $body['qp_name'] ?? 'Pickup' ) );
        $fee->set_total( round( $base, 2 ) );
        $order->add_item( $fee );
    }

    // 2) Environmental fee
    $env = floatval( $body['qp_env_fee'] ?? 0 );
    if ( $env > 0 ) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Environmental fee' );
        $fee->set_total( round( $env, 2 ) );
        $order->add_item( $fee );
    }

    // 3) Location fee (garage/inside)
    $location_fee = floatval( $body['location_fee'] ?? 0 );
    if ( $location_fee > 0 ) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Location fee (' . sanitize_text_field( $body['pickup_location'] ?? '' ) . ')' );
        $fee->set_total( round( $location_fee, 2 ) );
        $order->add_item( $fee );
    }

    // 4) Stairs fee
    $stairs_fee = floatval( $body['stairs_fee'] ?? 0 );
    if ( $stairs_fee > 0 ) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Stairs fee' );
        $fee->set_total( round( $stairs_fee, 2 ) );
        $order->add_item( $fee );
    }

    // 5) Rush fee
    $rush_fee = floatval( $body['rush_fee'] ?? 0 );
    if ( $rush_fee > 0 ) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Rush pickup fee' );
        $fee->set_total( round( $rush_fee, 2 ) );
        $order->add_item( $fee );
    }

    // Add any meta / booking details
    $order->add_meta_data( 'qp_item', sanitize_text_field( $body['qp_item'] ?? '' ) );
    $order->add_meta_data( 'qp_name', sanitize_text_field( $body['qp_name'] ?? '' ) );
    $order->add_meta_data( 'pickup_location', sanitize_text_field( $body['pickup_location'] ?? '' ) );
    $order->add_meta_data( 'other_details', sanitize_text_field( $body['other_details'] ?? '' ) );
    $order->add_meta_data( 'stairs', intval( $body['stairs'] ?? 0 ) );
    $order->add_meta_data( 'rush', ! empty( $body['rush'] ) ? '1' : '0' );
    if ( ! empty( $body['pickup_date'] ) ) {
        $order->add_meta_data( 'pickup_date', sanitize_text_field( $body['pickup_date'] ) );
    }

    // Set currency if you need to (defaults to store currency)
    // $order->set_currency( 'USD' );

    // Calculate totals and save
    $order->calculate_totals( true );
    $order->save();

    // Mark status as pending
    $order->update_status( 'pending', 'Order created via quick pickup endpoint' );

    // Provide order pay URL (works with WooCommerce)
    // Preferred: $order->get_checkout_payment_url()
    $pay_url = method_exists( $order, 'get_checkout_payment_url' )
        ? $order->get_checkout_payment_url()
        : wc_get_endpoint_url( 'order-pay', $order->get_id(), wc_get_page_permalink( 'checkout' ) );

    return rest_ensure_response( array(
        'success' => true,
        'order_id' => $order->get_id(),
        'pay_url' => $pay_url,
    ) );
}