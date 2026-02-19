<?php
namespace Brightplugins_COS;

class WCBV_Order_Status_Email extends \WC_Email {
	
	public $default_body_text;
	/**
	 * @param $id
	 * @param array $args
	 */
	public function __construct( $id, array $args ) {
		$this->id = $id;

		$this->title             = $args['title'];
		$this->description       = __( 'Regarding your {site_title} order from {order_date}', 'bp-custom-order-status' );
		$this->default_body_text = __( 'Your order is now {order_status}. Order details are shown below for your reference:', 'bp-custom-order-status' );
		$this->heading           = $args['title'];
		$this->subject           = __( 'Regarding your {site_title} order from {order_date}', 'bp-custom-order-status' );
		$this->template_html     = 'emails/customer-order-status-email.php';
		$this->template_plain    = 'emails/plain/customer-order-status-email.php';

		$this->template_base = BVOS_TEMPLATE_PATH;
		$this->placeholders  = array(
			'{order_date}'   => '',
			'{order_number}' => '',
			'{order_status}' => '',
		);

		switch ( $args['type'] ) {

		case 'customer':

			$this->heading           = __( 'Order status changed to {order_status}', 'bp-custom-order-status' );
			$this->subject           = __( 'Regarding your {site_title} order!', 'bp-custom-order-status' );
			$this->default_body_text = __( 'Your order is now {order_status}. Order details are shown below for your reference:', 'bp-custom-order-status' );

			if ( !$this->recipient ) {
				$this->recipient = __( 'Customer', 'bp-custom-order-status' );
			}
			$this->template_html  = 'emails/customer-order-status-email.php';
			$this->template_plain = 'emails/plain/customer-order-status-email.php';
			$status_slug          = str_replace( 'bvos_custom_', '', $id );
			if ( file_exists( get_template_directory() . '/woocommerce/emails/customer-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_html = 'emails/customer-order-status-email-' . $status_slug . '.php';
			}
			if ( file_exists( get_template_directory() . '/woocommerce/emails/plain/customer-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_plain = 'emails/plain/customer-order-status-email-' . $status_slug . '.php';
			}
			if ( file_exists( get_stylesheet_directory() . '/woocommerce/emails/customer-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_html = 'emails/customer-order-status-email-' . $status_slug . '.php';
			}
			if ( file_exists( get_stylesheet_directory() . '/woocommerce/emails/plain/customer-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_plain = 'emails/plain/customer-order-status-email-' . $status_slug . '.php';
			}
			$this->customer_email = true;
			break;

		case 'admin':

			$this->heading           = __( 'Order status changed to {order_status}', 'bp-custom-order-status' );
			$this->subject           = __( '[{site_title}] Customer order #{order_number} updated', 'bp-custom-order-status' );
			$this->default_body_text = __( 'This order is now {order_status}. Order details are as follows:', 'bp-custom-order-status' );

			if ( !$this->recipient ) {
				$this->recipient = get_option( 'admin_email' );
			}
			$this->template_html  = 'emails/admin-order-status-email.php';
			$this->template_plain = 'emails/plain/admin-order-status-email.php';
			$status_slug          = str_replace( 'bvos_custom_', '', $id );
			if ( file_exists( get_template_directory() . '/woocommerce/emails/admin-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_html = 'emails/admin-order-status-email-' . $status_slug . '.php';
			}
			if ( file_exists( get_template_directory() . '/woocommerce/emails/plain/admin-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_plain = 'emails/plain/admin-order-status-email-' . $status_slug . '.php';
			}
			if ( file_exists( get_stylesheet_directory() . '/woocommerce/emails/admin-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_html = 'emails/admin-order-status-email-' . $status_slug . '.php';
			}
			if ( file_exists( get_stylesheet_directory() . '/woocommerce/emails/plain/admin-order-status-email-' . $status_slug . '.php' ) ) {
				$this->template_plain = 'emails/plain/admin-order-status-email-' . $status_slug . '.php';
			}
			$this->customer_email = false;
			break;

		}
		//	add_action( 'woocommerce_order_status_changed', array( $this, 'trigger' ), 10, 2 );
		parent::__construct();
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param int            $order_id The order ID.
	 * @param WC_Order|false $order Order object.
	 */
	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();

		if ( $order_id && !is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		$statusToNotifyArray = $this->wcbvGetStatusToNofify();
		if ( !in_array( $order->get_status(), $statusToNotifyArray, true ) ) {
			return;
		}

		if ( is_a( $order, 'WC_Order' ) ) {

			$status_post = get_posts( array(
				'meta_key'    => 'status_slug',
				'post_type'   => 'order_status',
				'meta_value'  => $order->get_status(),
				'numberposts' => 1,
			) );

			$recipient_email = get_bloginfo( 'admin_email' );
			if ( !empty( $status_post ) ) {
				foreach ( $status_post as $sp ) {
					if ( isset( $sp->ID ) ) {
						if ( get_post_meta( $sp->ID, '_email_type', true ) == 'customer' ) {
							$recipient_email = $order->get_billing_email();
						}
					}
				}
			}
			$this->object = $order;

			$this->recipient                      = $recipient_email; //$this->object->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{order_number}'] = $this->object->get_order_number();
			$this->placeholders['{order_status}'] = wc_get_order_status_name( $this->object->get_status() );
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			$status                    = $this->object->get_status();
			$emailList                 = $this->getStatusRecipients( $status );
			foreach ( $emailList as $email ) {
				$this->send( $email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
 
		}

		$this->restore_locale();

	}

	/**
	 * @param $status
	 * @return mixed
	 */
	public function getStatusRecipients( $status ) {
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			'meta_query'  => [[
				'key'     => 'status_slug',
				'compare' => '=',
				'value'   => $status,
			]],
		);
		$postStatusList = get_posts( $arg );
		$emailList      = array();
		foreach ( $postStatusList as $post ) {
			$recipients = get_post_meta( $post->ID, '_recipient_cc', true );
			if ( !empty( $recipients ) && !is_null( $recipients ) && is_array( $recipients ) ) {
				foreach ( $recipients as $email ) {
					if ( isset( $email["_recipient_cc_email"] ) && !empty( trim( $email["_recipient_cc_email"] ) ) ) {
						$emailList[] = $email["_recipient_cc_email"];
					}
				}
			}
		}
		return $emailList;
	}

	/**
	 * @return mixed
	 */
	public function wcbvGetStatusToNofify() {
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			'meta_query'  => [[
				'key'     => '_enable_email',
				'compare' => '=',
				'value'   => '1',
			]],
		);
		$postStatusList = get_posts( $arg );
		$statuses       = array();
		foreach ( $postStatusList as $post ) {
			$slug       = get_post_meta( $post->ID, 'status_slug', true );
			$statuses[] = $slug;
		}

		return $statuses;
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => !$this->customer_email,
			'plain_text'         => false,
			'email'              => $this,
		), '', $this->template_base );
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => !$this->customer_email,
			'plain_text'         => true,
			'email'              => $this,
		), '', $this->template_base );
	}

} // end
