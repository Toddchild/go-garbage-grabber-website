<?php
namespace Brightplugins_COS;

class Email {

	public function __construct() {
		add_action( 'woocommerce_order_status_changed', [$this, 'status_changed'], 10, 3 );
		add_filter( 'woocommerce_email_classes', [$this, 'order_status_emails'] );
		add_filter( 'woocommerce_order_is_download_permitted', [$this, 'bvadd_status_to_download_permission'], 10, 2 );
	}

	/**
	 * @param $data
	 * @param $order
	 * @return mixed
	 */
	public function bvadd_status_to_download_permission( $data, $order ) {
		$statusGrantDownloadArray = $this->wcbvGetStatusGrantDownloadable();
		if ( in_array( $order->get_status(), $statusGrantDownloadArray, true ) ) {
			return true;
		}
		return $data;
	}

	/**
	 * @param $order_id
	 * @param $old_status
	 * @param $new_status
	 */
	public function status_changed( $order_id, $old_status, $new_status ) {
		$statusGrantDownloadArray = $this->wcbvGetStatusGrantDownloadable();
		if ( in_array( $new_status, $statusGrantDownloadArray, true ) ) {
			wc_downloadable_product_permissions( $order_id, true );
		}

		$wc_emails = WC()->mailer()->get_emails();
		if ( isset( $wc_emails['bvos_custom_' . $new_status] ) ) {
			//$wc_emails['bvos_custom_' . $new_status]->trigger( $order_id );
			$defaultOptions        = get_option( 'wcbv_status_default', null );
			$is_wpml_compatible    = false;
			$wpml_default_language = ' ';
			$wpml_current_lang     = '';
			if ( $defaultOptions ) {
				if ( isset( $defaultOptions['enable_wpml'] ) && '1' == $defaultOptions['enable_wpml'] ) {
					if ( class_exists( 'sitepress' ) ) {
						global $sitepress;
						$wpml_default_language = $sitepress->get_default_language();
						$wpml_current_lang     = get_post_meta( $order_id, 'wpml_language', true );
						if ( empty( $wpml_current_lang ) ) {
							$wpml_current_lang = $wpml_default_language;
						}
						$is_wpml_compatible = true;
					}
				}
			}

			if ( $is_wpml_compatible ) {
				$type         = apply_filters( 'wpml_element_type', get_post_type( $order_id ) );
				$trid         = apply_filters( 'wpml_element_trid', false, $order_id, $type );
				$order_lang   = $wpml_current_lang;
				$translations = apply_filters( 'wpml_get_element_translations', array(), $trid, $type );
				foreach ( $translations as $lang => $translation ) {
					$order_lang = $translation->language_code;
					break;
				}

				if ( strlen( $order_lang ) > 2 ) {
					$order_lang     = str_replace( '-', '_', $order_lang );
					$order_lang_aux = explode( '_', $order_lang );
					if ( isset( $order_lang_aux[1] ) ) {
						$order_lang_aux[1] = strtoupper( $order_lang_aux[1] );
					}
					$order_lang = implode( '_', $order_lang_aux );
				}
				$locale = $order_lang;
				switch_to_locale( $locale );
				$wc_emails['bvos_custom_' . $new_status]->trigger( $order_id );
				restore_previous_locale();
			} else {
				$wc_emails['bvos_custom_' . $new_status]->trigger( $order_id );
			}

		}
	}

	/**
	 * @param $emails
	 * @return mixed
	 */
	public function order_status_emails( $emails ) {

		include_once BVOS_PLUGIN_DIR . '/src/emails/class-wcbv-order-status-email.php';

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

		foreach ( $postStatusList as $post ) {

			$status_index = $statusSlug = get_post_meta( $post->ID, 'status_slug', true );

			$emails['bvos_custom_' . $status_index] = new WCBV_Order_Status_Email(
				'bvos_custom_' . $status_index, array(
					'post_id'     => $post->ID,
					'title'       => $post->post_title,
					'description' => $post->post_excerpt,
					'type'        => get_post_meta( $post->ID, '_email_type', true ),
				)
			);

		}
		return $emails;

	}

	/**
	 * @return mixed
	 */
	public function wcbvGetStatusGrantDownloadable() {
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			'meta_query'  => [[
				'key'     => 'downloadable_grant',
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

}
