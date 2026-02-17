<?php
namespace Brightplugins_COS;

class Checkout {

	public function __construct() {

		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'set_status_offline_payment' ), 10, 2 );
		add_filter( 'woocommerce_bacs_process_payment_order_status', array( $this, 'set_status_offline_payment' ), 10, 2 );
		add_filter( 'woocommerce_cheque_process_payment_order_status', array( $this, 'set_status_offline_payment' ), 10, 2 );
		add_action( 'woocommerce_payment_complete_order_status', array( $this, 'set_payment_complete_status' ), 10, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'admin_new_order_email' ), 10, 3 );

	}
	
	/**
	 * Sends new order email to admin.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function admin_new_order_email( $order_id ) {
		WC()->mailer()->emails['WC_Email_New_Order']->trigger( $order_id );
	}
	/**
	 * Sets the order status for offline payments.
	 *
	 * This function is responsible for setting the order status when the payment method used is offline.
	 * It retrieves the order using the provided order ID and checks the payment method.
	 * If the payment method is offline, it updates the order status to a specific status meant for offline payments.
	 *
	 * @param int $order_id The ID of the order for which the status needs to be set.
	 * @return void
	 */
	public function set_status_offline_payment( $status, $order ) {
		if ( !$order ) {
			return $status;
		}
		$payment_method = $order->get_payment_method();
		$status         = $this->get_default_order_status( $status, $payment_method );
		return $status;
	}

	
	/**
	 * Set the payment complete status for an order.
	 *
	 * @param string $status The current order status.
	 * @param int $order_id The ID of the order.
	 * @param object $order The order object.
	 * @return string The updated order status.
	 */
	public function set_payment_complete_status( $status, $order_id, $order ) {
		if ( !$order ) {
			return $status;
		}

	//	WC()->mailer()->emails['WC_Email_New_Order']->trigger( $order_id, $order, true );
		$payment_method = $order->get_payment_method();
		$option_prefix  = 'orderstatus_default_statusgateway_' . $payment_method;
		$defaultStatus  = get_option( 'wcbv_status_default', null );
		if ( $defaultStatus ) {
			if ( isset( $defaultStatus[$option_prefix] ) && 'bpos_disabled' !== $defaultStatus[$option_prefix] ) {
				$status = $defaultStatus[$option_prefix];
			} elseif ( isset( $defaultStatus['orderstatus_default_status'] ) && 'bpos_disabled' !== $defaultStatus['orderstatus_default_status'] ) {
				$status = $defaultStatus['orderstatus_default_status'];
			}
		}
		return $status;
	}

	/**
	 * @param $order_status
	 * @return mixed
	 */
	public function set_default_order_status( $order_status ) {
		$defaultStatus = get_option( 'wcbv_status_default', null );
		if ( isset( $defaultStatus['orderstatus_default_status'] ) && 'bpos_disabled' !== $defaultStatus['orderstatus_default_status'] ) {
			$order_status = $defaultStatus['orderstatus_default_status'];
		}
		return $order_status;
	}

	/**
	 * @param $status
	 * @param $payment_method
	 * @return mixed
	 */
	public function get_default_order_status( $status, $payment_method ) {
		$option_prefix = 'orderstatus_default_statusgateway_' . $payment_method;
		$defaultStatus = get_option( 'wcbv_status_default', null );
		if ( isset( $defaultStatus[$option_prefix] ) && 'bpos_disabled' !== $defaultStatus[$option_prefix] ) {
			$status = $defaultStatus[$option_prefix];
		} elseif ( isset( $defaultStatus['orderstatus_default_status'] ) && 'bpos_disabled' !== $defaultStatus['orderstatus_default_status'] ) {
			$status = $defaultStatus['orderstatus_default_status'];
		}
		return $status;
	}

}
