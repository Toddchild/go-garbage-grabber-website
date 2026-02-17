<?php

defined( 'ABSPATH' ) or exit;

/**
 * Default customer order status email template.
 *
 */
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email );?>

<?php if ( $email_body_text ?? FALSE ): ?>
<div id="body_text"><?php echo esc_html( $email_body_text ); ?></div>
<?php endif;?>

<?php do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );?>

<h2><?php echo esc_html__( 'Order:', 'bp-custom-order-status' ) . ' #' . $order->get_order_number(); ?></h2>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%;" border="1">
	<thead>
		<tr>
			<th class="td" scope="col" style="text-align: left;"><?php esc_html_e( 'Product', 'bp-custom-order-status' );?></th>
			<th class="td" scope="col" style="text-align: left;"><?php esc_html_e( 'Quantity', 'bp-custom-order-status' );?></th>
			<th class="td" scope="col" style="text-align: left;"><?php esc_html_e( 'Price', 'bp-custom-order-status' );?></th>
		</tr>
	</thead>
	<tbody>
		<?php

$email_order_items = array(
	'show_purchase_note'  => false,
	'show_download_links' => false,
	'show_sku'            => false,
);

echo wc_get_email_order_items( $order, $email_order_items );

?>
	</tbody>
	<tfoot>
		<?php
if ( $totals = $order->get_order_item_totals() ) {
	$i = 0;
	foreach ( $totals as $total ) {
		$i++;?>
					<tr>
						<th class="td" scope="row" colspan="2" style="text-align: left; <?php if ( 1 == $i ) {
			echo 'border-top-width: 4px;';
		}
		?>"><?php echo wp_strip_all_tags( $total['label'] ); ?></th>
						<td class="td" style="text-align: left; <?php if ( 1 == $i ) {
			echo 'border-top-width: 4px;';
		}
		?>"><?php echo wp_strip_all_tags( $total['value'] ); ?></td>
					</tr><?php
}
}
?>
	</tfoot>
</table>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );?>

<?php do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );?>

<?php do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>
<?php do_action( 'woocommerce_email_footer', $email );?>
