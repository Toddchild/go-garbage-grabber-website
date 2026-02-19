<?php
/**
 * Plugin Name: Quick Pickup - Payment Intent Handler
 * Description: Server-side ajax handler for qp_create_payment_intent (creates WC order + Stripe PaymentIntent) and Stripe webhook endpoint.
 * Version: v17.1
 * Author: Todd Child
 *
 * Notes:
 * - Set QP_STRIPE_SECRET (or QP_STRIPE_SECRET_KEY or option 'qp_stripe_secret') for API calls.
 * - Set QP_STRIPE_WEBHOOK_SECRET (or option 'qp_stripe_webhook_secret') to your webhook signing secret.
 * - This copy includes a nonce check for the AJAX endpoint and more robust webhook logging.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Load Composer autoload if present (for Stripe PHP SDK)
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Register AJAX endpoints (authenticated and unauthenticated).
 */
add_action( 'wp_ajax_qp_create_payment_intent', 'qp_create_payment_intent' );
add_action( 'wp_ajax_nopriv_qp_create_payment_intent', 'qp_create_payment_intent' );

/**
 * Create a WooCommerce order and a Stripe PaymentIntent, then return client_secret.
 *
 * Expects POST parameters:
 * - amount (required): decimal amount in major currency units (e.g. "12.50")
 * - currency (optional): currency code (default: WooCommerce currency)
 * - email (optional): customer's email for receipt
 * - qp_nonce (required): nonce named 'qp_apply_nonce'
 */
function qp_create_payment_intent() {
    // Require nonce for this endpoint to reduce abuse
    if ( empty( $_POST['qp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qp_nonce'] ) ), 'qp_apply_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Invalid or missing nonce' ), 403 );
    }

    // Basic permission / sanity checks
    if ( ! isset( $_POST['amount'] ) ) {
        wp_send_json_error( array( 'message' => 'Missing amount' ), 400 );
    }

    // Sanitize inputs
    $amount_input = str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ) ) );
    $amount = floatval( $amount_input ); // major units, e.g. 12.50
    if ( $amount <= 0 ) {
        wp_send_json_error( array( 'message' => 'Invalid amount' ), 400 );
    }

    $currency = isset( $_POST['currency'] ) ? strtoupper( sanitize_text_field( $_POST['currency'] ) ) : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' );
    $email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

    // Ensure WooCommerce is available
    if ( ! class_exists( 'WC_Order' ) || ! function_exists( 'wc_create_order' ) ) {
        wp_send_json_error( array( 'message' => 'WooCommerce not active' ), 500 );
    }

    // Stripe secret lookup (accept multiple possible names / sources)
    $stripe_secret = defined( 'QP_STRIPE_SECRET' ) ? QP_STRIPE_SECRET
                   : ( defined( 'QP_STRIPE_SECRET_KEY' ) ? QP_STRIPE_SECRET_KEY
                   : ( getenv( 'QP_STRIPE_SECRET' ) ?: get_option( 'qp_stripe_secret', '' ) ) );

    if ( empty( $stripe_secret ) ) {
        wp_send_json_error( array( 'message' => 'Stripe secret key not configured' ), 500 );
    }

    // Create minimal WooCommerce order
    try {
        $order = wc_create_order(); // creates a blank order
        if ( $email ) {
            $order->set_billing_email( $email );
        }

        // Set currency & total (major units)
        $order->set_currency( $currency );
        // Ensure decimal formatting with WooCommerce decimals
        $order->set_total( wc_format_decimal( $amount, wc_get_price_decimals() ) );
        $order->save();

        $order_id = $order->get_id();
    } catch ( Exception $e ) {
        wp_send_json_error( array( 'message' => 'Failed to create order: ' . $e->getMessage() ), 500 );
    }

    // Create Stripe PaymentIntent
    try {
        if ( ! class_exists( '\Stripe\StripeClient' ) ) {
            wp_send_json_error( array( 'message' => 'Stripe PHP SDK not available' ), 500 );
        }

        $stripe = new \Stripe\StripeClient( $stripe_secret );

        // Determine multiplier for smallest currency unit
        // Some currencies are zero-decimal per Stripe (JPY, KRW, etc.)
        $zero_decimal_currencies = array(
            'BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'
        );

        if ( in_array( strtoupper( $currency ), $zero_decimal_currencies, true ) ) {
            $multiplier = 1;
        } else {
            // Fall back to Woo decimals for most currencies
            $decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
            $multiplier = (int) pow( 10, max( 0, (int) $decimals ) );
        }

        $unit_amount = intval( round( $amount * $multiplier ) );

        $pi_params = array(
            'amount'   => $unit_amount,
            'currency' => strtolower( $currency ),
            'metadata' => array(
                'order_id' => (string) $order_id,
                'source'   => 'quick-pickup-plugin',
            ),
        );

        if ( $email ) {
            $pi_params['receipt_email'] = $email;
        }

        $payment_intent = $stripe->paymentIntents->create( $pi_params );

        // Save PI id on the order for later reference
        update_post_meta( $order_id, '_qp_stripe_payment_intent_id', sanitize_text_field( $payment_intent->id ) );

        // Mark order pending
        $order->update_status( 'pending', 'PaymentIntent created via Quick Pickup' );

        wp_send_json_success( array(
            'client_secret'     => isset( $payment_intent->client_secret ) ? $payment_intent->client_secret : '',
            'order_id'          => $order_id,
            'payment_intent_id' => $payment_intent->id,
        ) );
    } catch ( \Stripe\Exception\ApiErrorException $e ) {
        wp_send_json_error( array( 'message' => 'Stripe API error: ' . $e->getMessage() ), 500 );
    } catch ( Exception $e ) {
        wp_send_json_error( array( 'message' => 'Unexpected error: ' . $e->getMessage() ), 500 );
    }
}

/* ------------------ Stripe webhook handler ------------------ */

/**
 * Register a REST endpoint for Stripe webhooks: POST /wp-json/qp/v1/webhook
 */
add_action( 'rest_api_init', function () {
    register_rest_route(
        'qp/v1',
        '/webhook',
        array(
            'methods'             => 'POST',
            'callback'            => 'qp_handle_stripe_webhook',
            'permission_callback' => '__return_true',
        )
    );
} );

/**
 * Minimal helper to log webhook processing output to uploads or PHP error log.
 *
 * Writes to wp-content/uploads/qp-webhook.log if possible; falls back to error_log().
 */
function qp_webhook_log( $message ) {
    $uploads = wp_upload_dir();
    $uploads_dir = isset( $uploads['basedir'] ) ? trailingslashit( $uploads['basedir'] ) : ( WP_CONTENT_DIR . '/uploads/' );
    $log_file = $uploads_dir . 'qp-webhook.log';
    $line = '[' . date( 'c' ) . '] ' . $message . PHP_EOL;

    // Ensure uploads directory exists (silent)
    if ( ! is_dir( $uploads_dir ) ) {
        @wp_mkdir_p( $uploads_dir );
    }

    // Try file write; if failing, fallback to error_log
    if ( @file_put_contents( $log_file, $line, FILE_APPEND ) === false ) {
        error_log( $line );
    }
}

/**
 * Webhook handler for Stripe events.
 */
function qp_handle_stripe_webhook( \WP_REST_Request $request ) {
    qp_webhook_log( 'Webhook received' );

    // Ensure Stripe Webhook class is available
    if ( ! class_exists( '\Stripe\Webhook' ) ) {
        qp_webhook_log( 'ERROR: Stripe library not available' );
        return new \WP_REST_Response( array( 'error' => 'Stripe library not available' ), 500 );
    }

    $payload    = $request->get_body();
    $sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) : '';

    // Accept constant or option for webhook secret
    $endpoint_secret = defined( 'QP_STRIPE_WEBHOOK_SECRET' ) ? QP_STRIPE_WEBHOOK_SECRET : ( getenv( 'QP_STRIPE_WEBHOOK_SECRET' ) ?: get_option( 'qp_stripe_webhook_secret', '' ) );

    if ( empty( $endpoint_secret ) ) {
        qp_webhook_log( 'ERROR: Webhook signing secret not configured' );
        return new \WP_REST_Response( array( 'error' => 'Webhook signing secret not configured' ), 400 );
    }

    try {
        $event = \Stripe\Webhook::constructEvent( $payload, $sig_header, $endpoint_secret );
    } catch ( \UnexpectedValueException $e ) {
        qp_webhook_log( 'ERROR: Invalid payload - ' . $e->getMessage() );
        return new \WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
    } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
        qp_webhook_log( 'ERROR: Invalid signature - ' . $e->getMessage() );
        return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
    } catch ( Exception $e ) {
        qp_webhook_log( 'ERROR: Webhook exception - ' . $e->getMessage() );
        return new \WP_REST_Response( array( 'error' => 'Webhook error: ' . $e->getMessage() ), 400 );
    }

    qp_webhook_log( 'Event type: ' . $event->type );

    try {
        switch ( $event->type ) {
            case 'payment_intent.succeeded':
                $pi = $event->data->object;
                $order_id = isset( $pi->metadata->order_id ) ? intval( $pi->metadata->order_id ) : 0;
                qp_webhook_log( "payment_intent.succeeded order_id={$order_id}" );
                if ( $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        $status = $order->get_status();
                        if ( ! in_array( $status, array( 'processing', 'completed', 'refunded', 'cancelled' ), true ) ) {
                            $order->payment_complete( isset( $pi->id ) ? sanitize_text_field( $pi->id ) : '' );
                            $order->add_order_note( 'Stripe PaymentIntent succeeded: ' . sanitize_text_field( $pi->id ) );
                            qp_webhook_log( "Marked order {$order_id} paid via PI {$pi->id}" );
                        } else {
                            qp_webhook_log( "Order {$order_id} already in terminal state {$status}" );
                        }
                    } else {
                        qp_webhook_log( "Order {$order_id} not found" );
                    }
                }
                break;

            case 'charge.succeeded':
            case 'charge.updated':
                $ch = $event->data->object;
                $order_id = isset( $ch->metadata->order_id ) ? intval( $ch->metadata->order_id ) : 0;
                qp_webhook_log( "charge event id={$ch->id} order_id_from_charge={$order_id}" );

                // If no order_id on charge, try retrieving PI metadata (requires secret key)
                if ( ! $order_id && ! empty( $ch->payment_intent ) ) {
                    $stripe_secret = defined( 'QP_STRIPE_SECRET' ) ? QP_STRIPE_SECRET
                                   : ( defined( 'QP_STRIPE_SECRET_KEY' ) ? QP_STRIPE_SECRET_KEY
                                   : ( getenv( 'QP_STRIPE_SECRET' ) ?: get_option( 'qp_stripe_secret', '' ) ) );

                    if ( ! empty( $stripe_secret ) && class_exists( '\Stripe\StripeClient' ) ) {
                        try {
                            $stripe_client = new \Stripe\StripeClient( $stripe_secret );
                            $pi = $stripe_client->paymentIntents->retrieve( $ch->payment_intent );
                            if ( isset( $pi->metadata->order_id ) ) {
                                $order_id = intval( $pi->metadata->order_id );
                            }
                            qp_webhook_log( "Fetched PI {$ch->payment_intent} metadata order_id={$order_id}" );
                        } catch ( Exception $e ) {
                            qp_webhook_log( 'ERROR: Failed to retrieve PI - ' . $e->getMessage() );
                        }
                    } else {
                        qp_webhook_log( 'No stripe secret available (or SDK missing) to retrieve PI' );
                    }
                }

                if ( $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        $status = $order->get_status();
                        qp_webhook_log( "Order {$order_id} status={$status}" );
                        if ( ! in_array( $status, array( 'processing', 'completed', 'refunded', 'cancelled' ), true ) ) {
                            $order->payment_complete( isset( $ch->payment_intent ) ? sanitize_text_field( $ch->payment_intent ) : '' );
                            $order->add_order_note( 'Stripe charge succeeded: ' . sanitize_text_field( $ch->id ) );
                            qp_webhook_log( "Marked order {$order_id} paid via charge {$ch->id}" );
                        } else {
                            qp_webhook_log( "Order {$order_id} already terminal state {$status}" );
                        }
                    } else {
                        qp_webhook_log( "Order {$order_id} not found" );
                    }
                }
                break;

            case 'payment_intent.payment_failed':
                $pi = $event->data->object;
                $order_id = isset( $pi->metadata->order_id ) ? intval( $pi->metadata->order_id ) : 0;
                qp_webhook_log( "payment_intent.payment_failed order_id={$order_id}" );
                if ( $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        $order->update_status( 'failed', 'Stripe PaymentIntent failed: ' . ( isset( $pi->last_payment_error->message ) ? sanitize_text_field( $pi->last_payment_error->message ) : '' ) );
                        qp_webhook_log( "Marked order {$order_id} failed" );
                    }
                }
                break;

            default:
                qp_webhook_log( "Unhandled event type: " . $event->type );
                break;
        }
    } catch ( Exception $e ) {
        qp_webhook_log( 'EXCEPTION during processing: ' . $e->getMessage() );
    }

    qp_webhook_log( 'Webhook processing completed' );

    // Return success
    return new \WP_REST_Response( array( 'received' => true ), 200 );
}