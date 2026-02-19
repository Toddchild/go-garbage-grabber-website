<?php
/*
Plugin Name: WooCommerce Order Customer Approval
Description: Adds "Awaiting customer approval" status and a secure approve-by-link that customers can click to complete their order.
Version: 1.0
Author: You
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configuration: secret source
 * Preferred: define ORDER_APPROVAL_SECRET in wp-config.php
 * Fallback: generated and stored in option 'order_approval_secret'
 */
function oca_get_secret() {
	if ( defined( 'ORDER_APPROVAL_SECRET' ) && ORDER_APPROVAL_SECRET ) {
		return ORDER_APPROVAL_SECRET;
	}

	$opt = get_option( 'order_approval_secret' );
	if ( ! $opt ) {
		$opt = wp_generate_password( 48, true, true );
		update_option( 'order_approval_secret', $opt );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Order approval secret generated. Please store it securely.' );
		}
	}
	return $opt;
}

/**
 * Register custom order status: awaiting-customer-approval
 */
add_action( 'init', function() {
	register_post_status( 'wc-awaiting-customer-approval', array(
		'label'                     => 'Awaiting customer approval',
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Awaiting customer approval <span class="count">(%s)</span>', 'Awaiting customer approval <span class="count">(%s)</span>' )
	) );
}, 10 );

/**
 * Add the new status to the order statuses list (insert after Processing)
 */
add_filter( 'wc_order_statuses', function( $order_statuses ) {
	$new = array();
	foreach ( $order_statuses as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'wc-processing' === $key ) {
			$new['wc-awaiting-customer-approval'] = 'Awaiting customer approval';
		}
	}
	return $new;
});

/**
 * Generate approval URL
 * URL format: https://your-site/?approve_order=1&order_id=XXX&token=YYY
 */
function oca_generate_approval_url( $order ) {
	$secret    = oca_get_secret();
	$order_id  = intval( $order->get_id() );
	$order_key = $order->get_order_key();
	$data      = $order_id . '|' . $order_key;
	$token     = hash_hmac( 'sha256', $data, $secret );

	$url = add_query_arg( array(
		'approve_order' => 1,
		'order_id'      => $order_id,
		'token'         => $token,
	), home_url( '/' ) );

	return esc_url_raw( $url );
}

/**
 * Send approval email when order status transitions to awaiting-customer-approval
 */
add_action( 'woocommerce_order_status_processing_to_wc-awaiting-customer-approval', 'oca_send_approval_email', 10, 2 );
add_action( 'woocommerce_order_status_on-hold_to_wc-awaiting-customer-approval', 'oca_send_approval_email', 10, 2 );
add_action( 'woocommerce_order_status_pending_to_wc-awaiting-customer-approval', 'oca_send_approval_email', 10, 2 );

function oca_send_approval_email( $order_id, $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
	}

	$to = $order->get_billing_email();
	if ( ! $to ) {
		return;
	}

	$approve_url = oca_generate_approval_url( $order );

	$subject = sprintf( 'Please approve your service for Order #%s', $order->get_order_number() );
	$body  = "Hi " . $order->get_billing_first_name() . ",\n\n";
	$body .= "Your service for order #" . $order->get_order_number() . " is ready for your approval.\n\n";
	$body .= "To confirm and complete the order, click the link below:\n\n";
	$body .= $approve_url . "\n\n";
	$body .= "If you did not request this, please contact us.\n\n";
	$body .= "Thanks,\n";
	$body .= get_bloginfo( 'name' );

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	wp_mail( $to, $subject, $body, $headers );

	// add an order note
	$order->add_order_note( 'Approval email sent to customer: ' . $to );
}

/**
 * Public approval endpoint handler
 * Validates token and moves to completed
 */
add_action( 'init', function() {
	if ( isset( $_GET['approve_order'] ) && intval( $_GET['approve_order'] ) === 1 ) {
		$order_id = isset( $_GET['order_id'] ) ? intval( wp_unslash( $_GET['order_id'] ) ) : 0;
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( ! $order_id || ! $token ) {
			wp_die( 'Invalid approval link.' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( 'Order not found.' );
		}

		// Only allow approving if the order is currently in our awaiting status
		if ( $order->get_status() !== 'awaiting-customer-approval' ) {
			// idempotent: if already completed, show success redirect
			if ( $order->get_status() === 'completed' ) {
				wp_safe_redirect( home_url( '/?approve_result=already_completed' ) );
				exit;
			}
			wp_die( 'Order cannot be approved at this time.' );
		}

		$secret   = oca_get_secret();
		$data     = $order_id . '|' . $order->get_order_key();
		$expected = hash_hmac( 'sha256', $data, $secret );

		if ( ! hash_equals( $expected, $token ) ) {
			wp_die( 'Invalid or tampered approval link.' );
		}

		// All good — mark as completed and add note
		$order->update_status( 'completed', 'Customer approved the service via approval link.' );

		// Redirect to a friendly confirmation page (filterable)
		$redirect = apply_filters( 'oca_approval_redirect', home_url( '/order-approved/?order=' . $order->get_id() ) );
		wp_safe_redirect( $redirect );
		exit;
	}
});