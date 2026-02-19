<?php

defined( 'ABSPATH' ) or exit;

/**
 * Default admin order status email template.
 *
 * @type string    $email_heading       The email heading.
 * @type string    $email_body_text     The email body.
 * @type \WC_Order $order               The order object.
 * @type bool      $sent_to_admin       Whether email is sent to admin.
 * @type bool      $plain_text          Whether email is plain text.
 * @type bool      $show_download_links Whether to show download links.
 * @type bool      $show_purchase_note  Whether to show purchase note.
 * @type \WC_Email $email               The email object.
 *
 * @version 1.10.1
 * @since 1.0.0
 */

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

if ( $email_body_text ) {
	echo "\n\n";
	echo esc_html( wp_strip_all_tags( $email_body_text ) ) . "\n\n";
}

echo "****************************************************\n\n";

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );

/* translators: Placeholders: %s - order number */
echo sprintf( __( 'Order number: %s', 'bp-custom-order-status' ), $order->get_order_number() ) . "\n";

/* translators: Placeholders: %s - order link */
echo sprintf( __( 'Order link: %s', 'bp-custom-order-status' ), esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ) ) . "\n";

if ( $date_created = $order->get_date_created() ) {

	/* translators: Placeholders: %s - order date */
	echo sprintf( __( 'Order date: %s', 'bp-custom-order-status' ), date_i18n( __( 'jS F Y', 'bp-custom-order-status' ), $date_created->getTimestamp() ) ) . "\n";
}

do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

echo "\n";

$email_order_items = array(
	'show_sku'   => true,
	'plain_text' => true,
);

echo wc_get_email_order_items( $order, $email_order_items );

echo "----------\n\n";

if ( $totals = $order->get_order_item_totals() ) {
	foreach ( $totals as $total ) {
		echo esc_html( $total['label'] ) . "\t " . esc_html( $total['value'] ) . "\n";
	}
}

echo "\n****************************************************\n\n";

do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n****************************************************\n\n";

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo "\n****************************************************\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
