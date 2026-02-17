<?php
/**
 * Plugin Name: QP Order Emails & Reminders
 * Description: Create order posts, send confirmation email, and schedule reminder email 1 hour before pickup.
 * Version: 1.0.0
 * Author: You
 *
 * Usage:
 * - Install this plugin.
 * - Call the REST endpoint POST /wp-json/qp/v1/process-order with JSON order payload (example below).
 * - The plugin creates a qp_order, sends confirmation email, and schedules a 1-hour-prior reminder.
 *
 * IMPORTANT: Secure the endpoint in production (see notes at bottom).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'qp_register_order_post_type' );
function qp_register_order_post_type() {
    register_post_type( 'qp_order', array(
        'labels' => array(
            'name' => 'QP Orders',
            'singular_name' => 'QP Order',
        ),
        'public' => false,
        'has_archive' => false,
        'show_ui' => true,
        'supports' => array( 'title' ),
    ) );
}

/**
 * REST route to process an order payload, send confirmation, and schedule a reminder.
 * Expected JSON payload example:
 * {
 *   "order_key": "abc123",
 *   "customer_name": "Jane Doe",
 *   "customer_email": "j@example.com",
 *   "customer_phone": "555-555-5555",
 *   "address1": "123 Main St",
 *   "address2": "Apt 2",
 *   "city": "Ottawa",
 *   "postal": "K1A 0B1",
 *   "country": "CA",
 *   "pickup_date": "2026-02-20T10:30:00"   // ISO datetime preferred
 *   // ... any other fields you want to store ...
 * }
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'qp/v1', '/process-order', array(
        'methods' => 'POST',
        'callback' => 'qp_rest_process_order',
        // TODO: Replace with a real permission callback in production
        'permission_callback' => '__return_true',
    ) );
} );

function qp_rest_process_order( WP_REST_Request $request ) {
    $payload = (array) $request->get_json_params();

    if ( empty( $payload['customer_email'] ) ) {
        return new WP_REST_Response( array( 'error' => 'customer_email required' ), 400 );
    }

    // Create order post
    $post_title = 'Order: ' . ( $payload['order_key'] ?? wp_generate_password( 8, false, false ) );
    $post_id = wp_insert_post( array(
        'post_type' => 'qp_order',
        'post_title' => $post_title,
        'post_status' => 'publish',
        'post_content' => '',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( array( 'error' => 'failed to create order post' ), 500 );
    }

    // Save payload fields as post meta
    foreach ( $payload as $k => $v ) {
        if ( is_scalar( $v ) ) update_post_meta( $post_id, 'qp_' . $k, sanitize_text_field( (string) $v ) );
        else update_post_meta( $post_id, 'qp_' . $k, $v );
    }

    // Send immediate confirmation email
    try {
        qp_send_confirmation_email( $post_id );
    } catch ( Exception $e ) {
        // Log but continue
        error_log( 'qp: confirmation email error: ' . $e->getMessage() );
    }

    // Schedule reminder 1 hour before pickup (if pickup datetime available)
    try {
        qp_schedule_reminder_for_order( $post_id );
    } catch ( Exception $e ) {
        error_log( 'qp: schedule reminder error: ' . $e->getMessage() );
    }

    return new WP_REST_Response( array( 'order_id' => $post_id ), 200 );
}

/**
 * Compose and send confirmation email for an order
 */
function qp_send_confirmation_email( $order_id ) {
    $to = get_post_meta( $order_id, 'qp_customer_email', true );
    if ( ! $to ) return false;

    $subject = 'Your booking is confirmed — Order ' . $order_id;
    $html = qp_build_confirmation_email_html( $order_id );

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    // From header optional:
    $from_email = get_option( 'admin_email' );
    if ( $from_email ) $headers[] = "From: Pickup <{$from_email}>";

    return wp_mail( $to, $subject, $html, $headers );
}

/**
 * Compose and send reminder email for an order
 */
function qp_send_reminder_email( $order_id ) {
    $to = get_post_meta( $order_id, 'qp_customer_email', true );
    if ( ! $to ) return false;

    $subject = 'Reminder: Pickup in ~1 hour — Order ' . $order_id;
    $html = qp_build_reminder_email_html( $order_id );

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    $from_email = get_option( 'admin_email' );
    if ( $from_email ) $headers[] = "From: Pickup <{$from_email}>";

    return wp_mail( $to, $subject, $html, $headers );
}

/**
 * Build confirmation email HTML (editable)
 */
function qp_build_confirmation_email_html( $order_id ) {
    $name = esc_html( get_post_meta( $order_id, 'qp_customer_name', true ) );
    $email = esc_html( get_post_meta( $order_id, 'qp_customer_email', true ) );
    $phone = esc_html( get_post_meta( $order_id, 'qp_customer_phone', true ) );
    $addr = esc_html( get_post_meta( $order_id, 'qp_address1', true ) );
    $city = esc_html( get_post_meta( $order_id, 'qp_city', true ) );
    $postal = esc_html( get_post_meta( $order_id, 'qp_postal', true ) );
    $pickup_raw = get_post_meta( $order_id, 'qp_pickup_date', true );
    $pickup_display = $pickup_raw ? date_i18n( 'M j, Y @ g:i a', strtotime( $pickup_raw ) ) : 'Scheduled';

    // Instructions block (edit this to suit your operations)
    $instructions = '
      <ul>
        <li>Please place the item at the curb (or agreed pickup location) at least <strong>30 minutes before</strong> your pickup time block.</li>
        <li>Remove any personal items from inside the item and secure loose parts.</li>
        <li>If your pickup is on a driveway/garage, leave clear access to the item.</li>
        <li>If you have gate or building codes, reply to this email with instructions or call support.</li>
      </ul>
    ';

    $html = "
      <div style='font-family:Arial,Helvetica,sans-serif;color:#0a2e3b'>
        <h2>Your booking is confirmed</h2>
        <p>Hi {$name},</p>
        <p>Thank you — your pickup is booked. Order #<strong>{$order_id}</strong>.</p>

        <h3>Pickup details</h3>
        <p><strong>When:</strong> {$pickup_display}</p>
        <p><strong>Where:</strong> {$addr} {$city} {$postal}</p>

        <h3>Instructions</h3>
        {$instructions}

        <p><strong>Important:</strong> Be ready at the curb 30 minutes before the pickup time block so our team can collect the item on schedule.</p>

        <hr />
        <p>If you need to change or cancel, contact us at <a href='mailto:" . esc_html( get_option( 'admin_email' ) ) . "'>" . esc_html( get_option( 'admin_email' ) ) . "</a> or reply to this email.</p>
        <p>Thanks,<br/>The Pickup Team</p>
      </div>
    ";

    return $html;
}

/**
 * Build reminder email HTML
 */
function qp_build_reminder_email_html( $order_id ) {
    $name = esc_html( get_post_meta( $order_id, 'qp_customer_name', true ) );
    $pickup_raw = get_post_meta( $order_id, 'qp_pickup_date', true );
    $pickup_display = $pickup_raw ? date_i18n( 'M j, Y @ g:i a', strtotime( $pickup_raw ) ) : 'soon';

    $html = "
      <div style='font-family:Arial,Helvetica,sans-serif;color:#0a2e3b'>
        <h2>Pickup reminder — coming up in ~1 hour</h2>
        <p>Hi {$name},</p>
        <p>This is a friendly reminder that your pickup is scheduled for <strong>{$pickup_display}</strong>.</p>

        <h3>Quick checklist</h3>
        <ul>
          <li>Place the item at the curb (or agreed pickup location) at least <strong>30 minutes before</strong> pickup time.</li>
          <li>Ensure access is clear for the pickup team.</li>
          <li>Have any gate/building codes ready or reply to this email with details if needed.</li>
        </ul>

        <p>We look forward to collecting your item.</p>
        <p>Thanks,<br/>The Pickup Team</p>
      </div>
    ";
    return $html;
}

/**
 * Schedule a reminder 1 hour before the pickup time.
 * Uses wp_schedule_single_event to fire 'qp_send_reminder_event' with the order ID.
 */
function qp_schedule_reminder_for_order( $order_id ) {
    $pickup_raw = get_post_meta( $order_id, 'qp_pickup_date', true );
    $pickup_ts = null;

    if ( $pickup_raw ) {
        // Try parse ISO or timestamp
        $ts = strtotime( $pickup_raw );
        if ( $ts !== false ) $pickup_ts = $ts;
    }

    // If no time included or parsing failed, try pickup_time meta or default to noon local
    if ( ! $pickup_ts ) {
        $pickup_date = get_post_meta( $order_id, 'qp_pickup_date_only', true ) ?: get_post_meta( $order_id, 'qp_pickup_date', true );
        $pickup_time = get_post_meta( $order_id, 'qp_pickup_time', true );
        if ( $pickup_date ) {
            $time = $pickup_time ?: '12:00';
            $ts = strtotime( $pickup_date . ' ' . $time );
            if ( $ts !== false ) $pickup_ts = $ts;
        }
    }

    if ( ! $pickup_ts ) {
        // No valid pickup time found: do not schedule
        return false;
    }

    $reminder_ts = $pickup_ts - HOUR_IN_SECONDS;
    if ( $reminder_ts <= time() ) {
        // reminder time already passed; skip scheduling
        return false;
    }

    // Avoid duplicate scheduling
    if ( ! wp_next_scheduled( 'qp_send_reminder_event', array( $order_id ) ) ) {
        wp_schedule_single_event( $reminder_ts, 'qp_send_reminder_event', array( $order_id ) );
    }

    return true;
}

/**
 * Cron hook for sending reminder
 */
add_action( 'qp_send_reminder_event', 'qp_cron_send_reminder' );
function qp_cron_send_reminder( $order_id ) {
    // Re-check order still exists and hasn't been completed/cancelled (optional)
    if ( get_post_status( $order_id ) ) {
        qp_send_reminder_email( $order_id );
    }
}

/* --- Integration notes:
 * - Call the REST endpoint /wp-json/qp/v1/process-order after your server has created the order (or from your create-order handler).
 *   Example JS:
 *
 *   fetch('/wp-json/qp/v1/process-order', {
 *     method: 'POST',
 *     credentials: 'same-origin',
 *     headers: { 'Content-Type': 'application/json' },
 *     body: JSON.stringify({
 *       order_key: 'abc123',
 *       customer_name: 'Jane Doe',
 *       customer_email: 'j@example.com',
 *       customer_phone: '555-5555',
 *       address1: '123 Main St',
 *       city: 'Ottawa',
 *       postal: 'K1A0B1',
 *       country: 'CA',
 *       pickup_date: '2026-02-20T10:30:00'
 *     })
 *   }).then(r => r.json()).then(console.log);
 *
 * - IMPORTANT: In production you should secure the REST route:
 *   - Require JWT/basic auth or a shared secret, or
 *   - Restrict to authenticated users by replacing 'permission_callback' with a function that checks capabilities or a nonce.
 *
 * - You can also call qp_send_confirmation_email() and qp_schedule_reminder_for_order() directly from your existing order-creation code (if you modify your server plugin).
 *
 * - Customize email templates by editing qp_build_confirmation_email_html() and qp_build_reminder_email_html().
 *
 * - Testing:
 *   - Use test pickup_date values to confirm scheduling: set pickup_date to a few minutes/hours in the future then check wp_cron or wp_next_scheduled and confirm emails (in dev, configure WP to send mail or use a mail plugin/logging).
 *
 */