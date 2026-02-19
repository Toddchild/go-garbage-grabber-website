<?php
namespace Brightplugins_COS;

class Status {

	public function __construct() {

		add_action( 'admin_footer', array( $this, 'getIconCodeOnChangeScript' ) );
		add_action( 'init', array( $this, 'registerPostOrderStatus' ) );
		add_filter( 'wc_order_statuses', array( $this, 'addStatusToFilter' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'wcbvCustomStatusIsPaid' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'registerOrderCustomStatusBulkActions' ), 10 );
		// Compatibility with new orders page on HPOS/COT
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'registerOrderCustomStatusBulkActions' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueueScripts' ), 90 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'orderToalsAfterTotalRender' ), 10, 1 );

		// add_action( 'admin_head', array( $this, 'custom_post_type_icon' ) );
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_custom_status_actions_buttons' ), 9999, 2 );
		add_filter( 'wc_order_is_editable', array( $this, 'bp_add_order_statuses_to_editable' ), 10, 2 );
		$this->reduce_stock_status_if_order_status_is_paid();
		add_action( 'woocommerce_order_status_changed', [$this, 'set_payment_date_on_status_change'], 10, 3 );

	}

	public function set_payment_date_on_status_change( $order_id, $old_status, $new_status ){
		$status_paid_list = $this->wcbvCustomStatusIsPaid([]);
		if( empty( $status_paid_list ) ){
			return;
		}
		$order = wc_get_order( $order_id );
		if( !$order || !empty( $order->get_date_paid() ) ){
			return;
		}
		if ( in_array( $new_status, $status_paid_list, true ) ) {
			$order->set_date_paid( current_time( 'timestamp', true ) );
        	$order->save();
		}
		
	}
		
	/**
	 * Show paid data after order admin table if status is paid
	 * @param $_order_id
	 */
	public function orderToalsAfterTotalRender( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( !in_array( $order->get_status(), array( 'processing', 'completed', 'refunded' ), true ) && in_array( $order->get_status(), wc_get_is_paid_statuses(), true ) ):
		?>
		<hr>
		<tr>
				<td class="<?php echo $order->get_total_refunded() ? 'label' : 'label label-highlight'; ?>"><?php esc_html_e( 'Paid', 'bp-custom-order-status' );?>: <br /></td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped      ?>
				</td>
			</tr>
			<tr>
				<td>
					<span class="description">
					<?php
if ( !empty( $order->get_date_paid() ) ) {
			if ( $order->get_payment_method_title() ) {
				/* translators: 1: payment date. 2: payment method */
				echo esc_html( sprintf( __( '%1$s via %2$s', 'bp-custom-order-status' ), $order->get_date_paid()->date_i18n( get_option( 'date_format' ) ), $order->get_payment_method_title() ) );
			} else {
				echo esc_html( $order->get_date_paid()->date_i18n( get_option( 'date_format' ) ) );
			}
		}
		?>
				</span>
			</td>
			<td colspan="2"></td>
		</tr>
		<?php endif;
	}
	/**
	 * Include script to get Icon Unicode code from Icon Select onChange event
	 *
	 * @return void.
	 */
	public function getIconCodeOnChangeScript() {

		?>

        <script>
            jQuery( '#wcbv-order-status-new .csf-icon-value' ).change(function() {
                var iconclass=jQuery(this).val().replace(" ",".");
                if ( iconclass.length == 0 ){
                    jQuery("#wcbv-order-status-new [data-depend-id='icon_code']").val('');
                    return;
                }
                jQuery('.'+iconclass).each(function() {
                    var unicode = window.getComputedStyle(this, ':before').content
                                                                            .replace(/'|"/g, '') // <-----
                                                                            .charCodeAt(0)
                                                                            .toString(16);

                    jQuery("#wcbv-order-status-new [data-depend-id='icon_code']").val(unicode);
                });
            });

        </script>


         <?php

	}

	/**
	 * It includes styles for showing the icon status on admin panel
	 * only include for orders listing : edit.php?post_type=shop_order
	 * Admin Enqueue scripts (fontawesome)
	 *
	 * @return void.
	 */
	public function admin_enqueueScripts() {
		global $pagenow;

		if (
			!( 'edit.php' == $pagenow && isset( $_GET['post_type'] ) && 'shop_order' == $_GET['post_type'] )
			&&
			!( 'admin.php' == $pagenow && isset( $_GET['page'] ) && 'wc-orders' == $_GET['page'] )
		) {
			return;
		}
		$statuslist = array();
		$arg        = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
		);
		$postStatusList = get_posts( $arg );

		$default_class = 'mark.status-';
		$custom_css    = '';
		?>


      <?php
		$statusWithIconlist = [];

		foreach ( $postStatusList as $post ) {
			$iconCode        = ( get_post_meta( $post->ID, 'icon_code', true ) ) ? '\ ' . get_post_meta( $post->ID, 'icon_code', true ) : '';
			$iconColor       = get_post_meta( $post->ID, 'text_color', true );
			$slug            = get_post_meta( $post->ID, 'status_slug', true );
			$backgroundColor = get_post_meta( $post->ID, 'background_color', true );
			if ( !empty( $backgroundColor ) ) {
				$custom_css .= "
					mark.status-{$slug}{color:{$iconColor};background-color:$backgroundColor;}
					";
			} else {
				$custom_css .= "
					mark.status-{$slug}{color:{$iconColor};}
					";
			}
			$show_icon = 1;
			if ( !empty( get_post_meta( $post->ID, 'what_to_show', true ) ) ) {
				if ( 'text' == get_post_meta( $post->ID, 'what_to_show', true ) ) {
					$show_icon = 0;
				}
				$show_icon = 1;
				if ( !empty( get_post_meta( $post->ID, 'what_to_show', true ) ) ) {
					if ( 'text' == get_post_meta( $post->ID, 'what_to_show', true ) ) {
						$show_icon = 0;
					}
				}

				if ( !empty( $iconCode ) ) {
					$iconCode = str_replace( ' ', '', $iconCode );

					if ( $show_icon ) {
						$statusWithIconlist[] = $slug;
						$custom_css .= "
						.column-order_status .order-status.status-{$slug}:after{
					 	font-family: \"Font Awesome 5 Free\";font-weight: 600 !important; content: \"{$iconCode}\";
						}";
					}
					$custom_css .= "
					.view.{$slug}:after{ font-family: \"Font Awesome 5 Free\" !important; font-weight: 600 !important;content: \"{$iconCode}\" !important;   }
					";

				}
				/*$custom_css .= "
					.view.{$slug}:after{ font-family: \"Font Awesome 5 Free\" !important; font-weight: 600 !important;content: \"{$iconCode}\" !important;   }
					";*/

			}

		}

		if ( count( $statusWithIconlist ) ) {

			$cssStr     = $default_class . implode( ',' . $default_class, $statusWithIconlist );
			$cssStrSpan = $default_class . implode( ' span,' . $default_class, $statusWithIconlist );

			$custom_css .= "
					{$cssStr}{
						position: relative;
	                    padding: 0;
	                    text-indent: 0px;
	                    background: transparent;
	                    border: 0;
	                    font-size: 2em;
	                    line-height: 1;
	                    vertical-align: text-top;
					}
					{$cssStrSpan} span{
	                    display:none;
	                }
				";
		}

		wp_add_inline_style( 'woocommerce_admin_styles', $custom_css );
		wp_enqueue_style( 'font-awesome-cdn', 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.3/css/all.min.css?ver=5.15.3', array(), '5.13.3', 'all' );
		wp_enqueue_style( 'font-awesome-cdn', 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.3/css/all.min.css?ver=5.15.3', array(), '5.13.3', 'all' );
	}
	/**
	 * Enqueue scripts (fontawesome)
	 *
	 * @return void.
	 */
	public function enqueueScripts() {
		if ( !is_account_page() ) {
			return;
		}
		wp_enqueue_style( 'fa5', 'https://use.fontawesome.com/releases/v5.13.0/css/all.css', array(), '5.13.0', 'all' );
		wp_enqueue_style( 'fa5-v4-shims', 'https://use.fontawesome.com/releases/v5.13.0/css/v4-shims.css', array(), '5.13.0', 'all' );
	}

	/**
	 * Insert all custom status to bulk actions select on orders page.
	 *
	 * @param  $bulk_actions Current woocommerce array with the status on bulk
	 * @return mixed.
	 */
	public function registerOrderCustomStatusBulkActions( $bulk_actions ) {
		// get all custom status with bulk option enabled
		$customOrderStatus = $this->wcbvCustomStatusFiltermetaActive( '_enable_bulk', true );
		foreach ( $customOrderStatus as $slug => $label ) {
			/* translators: Custom Order Status Name */
			$bulk_actions['mark_' . $slug] = sprintf( __( 'Change status to %s', 'bp-custom-order-status' ), $label );
		}
		return $bulk_actions;
	}

	/**
	 * Insert custom status to woocommerce statuses paid array
	 *
	 * @param  $statuses Current woocommerce array with paid status
	 * @return mixed.
	 */
	public function wcbvCustomStatusIsPaid( $statuses ) {
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			'meta_query'  => [[
				'key'     => 'is_status_paid',
				'compare' => '=',
				'value'   => '1',
			]],
		);
		$postStatusList = get_posts( $arg );

		foreach ( $postStatusList as $post ) {
			$slug       = get_post_meta( $post->ID, 'status_slug', true );
			$statuses[] = $slug;
		}

		return $statuses;
	}
	/**
	 * Reduce product stock if order status is paid
	 * @return void
	 */
	public function reduce_stock_status_if_order_status_is_paid() {
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			'meta_query'  => [[
				'key'     => 'is_status_paid',
				'compare' => '=',
				'value'   => '1',
			]],
		);
		$statuses       = array();
		$postStatusList = get_posts( $arg );

		foreach ( $postStatusList as $post ) {
			$slug       = get_post_meta( $post->ID, 'status_slug', true );
			$statuses[] = $slug;
		}
		if ( $statuses ) {
			foreach ( $statuses as $key => $status ) {
				add_action( 'woocommerce_order_status_' . $status, 'wc_maybe_reduce_stock_levels' );
			}
		}

	}

	/**
	 * Get a list of custom statuses with option enabled
	 *
	 * @param  $filtermeta {  is_status_paid | _enable_email | _enable_bulk | _enable_action_status } The post meta for filtering enabled option
	 * @param  $cut_prefix If should return wc- prefix on custom status return array
	 * @return mixed.
	 */
	public function wcbvCustomStatusFiltermetaActive( $filtermeta, $cut_prefix = false ) {
		$defaultOptions        = get_option( 'wcbv_status_default', null );
		$is_wpml_compatible    = false;
		$wpml_default_language = ' ';
		$wpml_current_lang     = '';
		if ( $defaultOptions ) {
			if ( isset( $defaultOptions['enable_wpml'] ) && '1' == $defaultOptions['enable_wpml'] ) {
				if ( class_exists( 'sitepress' ) ) {
					global $sitepress;
					$wpml_default_language = $sitepress->get_default_language();
					$wpml_current_lang     = apply_filters( 'wpml_current_language', NULL );
					$is_wpml_compatible    = true;
				}
			}
		}
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			'meta_query'  => [[
				'key'     => $filtermeta,
				'compare' => '=',
				'value'   => '1',
			]],
		);
		$postStatusList          = get_posts( $arg );
		$prefix                  = !$cut_prefix ? 'wc-' : '';
		$orderStastusArrayReturn = array();

		foreach ( $postStatusList as $post ) {
			//$statusSlug = get_post_meta( $post->ID, 'status_slug', true );
			$current_title = $post->post_title;
			if ( $is_wpml_compatible ) {
				$status_id_default_lang = apply_filters( 'wpml_object_id', $post->ID, 'post', true, $wpml_default_language );
				$statusSlug             = get_post_meta( $status_id_default_lang, 'status_slug', true );

				$status_id_current_lang = apply_filters( 'wpml_object_id', $post->ID, 'post', true, $wpml_current_lang );
				$current_title          = get_the_title( $status_id_current_lang );
			} else {
				$statusSlug = get_post_meta( $post->ID, 'status_slug', true );
			}
			if ( $statusSlug ) {
				$orderStastusArrayReturn[$prefix . $statusSlug] = empty( trim( $current_title ) ) ? '(no title)' : $current_title;
			}
		}

		return $orderStastusArrayReturn;
	}

	/**
	 * Get a list of all custom statuses
	 *
	 * @param  $cut_prefix If should return wc- prefix on custom status return array
	 * @return mixed.
	 */
	public function getOrderStatusList( $cut_prefix = false ) {
		$defaultOptions        = get_option( 'wcbv_status_default', null );
		$is_wpml_compatible    = false;
		$wpml_default_language = ' ';
		$wpml_current_lang     = '';
		if ( $defaultOptions ) {
			if ( isset( $defaultOptions['enable_wpml'] ) && '1' == $defaultOptions['enable_wpml'] ) {
				if ( class_exists( 'sitepress' ) ) {
					global $sitepress;
					$wpml_default_language = $sitepress->get_default_language();
					$wpml_current_lang     = apply_filters( 'wpml_current_language', NULL );
					$is_wpml_compatible    = true;
				}
			}
		}
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			//'suppress_filters' => false
		);
		$postStatusList = get_posts( $arg );

		$prefix                  = !$cut_prefix ? 'wc-' : '';
		$orderStastusArrayReturn = array();

		foreach ( $postStatusList as $post ) {
			$current_title = $post->post_title;
			if ( $is_wpml_compatible ) {
				$status_id_default_lang = apply_filters( 'wpml_object_id', $post->ID, 'post', true, $wpml_default_language );
				$statusSlug             = get_post_meta( $status_id_default_lang, 'status_slug', true );

				$status_id_current_lang = apply_filters( 'wpml_object_id', $post->ID, 'post', true, $wpml_current_lang );
				$current_title          = get_the_title( $status_id_current_lang );
			} else {
				$statusSlug = get_post_meta( $post->ID, 'status_slug', true );
			}

			if ( $statusSlug ) {
				$orderStastusArrayReturn[$prefix . $statusSlug] = empty( trim( $current_title ) ) ? ' (no title) ' : $current_title;
			}
		}
		return $orderStastusArrayReturn;
	}

	/**
	 * Register all custom statuses to woocommerce
	 *
	 */
	public function registerPostOrderStatus() {

		$orderStastusArray = $this->getOrderStatusList();
		foreach ( $orderStastusArray as $slug => $label ) {
			register_post_status(
				$slug,
				array(
					'label'                     => $label,
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( "$label <span class='count'>(%s)</span>", "$label <span class='count'>(%s)</span>" ),
				)
			);
		}
	}

	/**
	 * Add all custom status to woocommerce status list (used on order view page)
	 *
	 * @param  $defaultOrderStatus The current list of woocommerce order status
	 * @return mixed.
	 */
	public function addStatusToFilter( $defaultOrderStatus ) {
		$orderStastusArray  = $this->getOrderStatusList();
		$defaultOrderStatus = ( '' === $defaultOrderStatus ) ? array() : $defaultOrderStatus;
		return array_merge( $defaultOrderStatus, $orderStastusArray );
	}

	/**
	 * Add custom status to order actions buttons.
	 *
	 *
	 * @param  array   $actions - array of actions.
	 * @param  object  $order  - order object.
	 * @return mixed
	 */

	public function add_custom_status_actions_buttons( $actions, $order ) {
		// get the list of custom status the user want to add on orders actions column

		$statuses = $this->wcbvCustomStatusFiltermetaActive( '_enable_action_status', true );
		$order_id = $order->get_id();
		// if the complete order action is not present in the array, add it .
		if ( !in_array( 'complete', $actions, true ) && !$order->has_status( array( 'completed' ) ) ) {
			$actions['complete'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order_id ), 'woocommerce-mark-order-status' ),
				'name'   => __( 'Complete', 'woocommerce' ),
				'action' => 'complete',
			);
		}
		foreach ( $statuses as $slug => $label ) {
			$custom_order_status = substr( $slug, 0 );
			if ( !$order->has_status( array( $custom_order_status ) ) ) { // if order status is not $custom_order_status.

				$status_post = get_posts(
					array(
						'post_type' => 'order_status',
						'title'     => $label,
					)
				);

				$page_got_by_title = null;

				if ( !empty( $status_post ) ) {
					$page_got_by_title = $status_post[0];
				}

				$custom_order_status_obj = $page_got_by_title; //get_page_by_title( $label, OBJECT, 'order_status' );
				$custom_order_status_id  = $custom_order_status_obj->ID;
				$custom_status_icon      = get_post_meta( $custom_order_status_id, 'status_icon', true );

				$actions[$custom_order_status] = array(
					'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=' . $custom_order_status . '&order_id=' . $order_id ), 'woocommerce-mark-order-status' ),
					'name'   => $label,
					'action' => 'view ' . $custom_order_status,
					'icon'   => '<i class="' . $custom_status_icon . '"></i>',
				);
			}
		}

		return $actions;
	}

	/**
	 * @return mixed
	 */
	public function get_status_editable() {
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'order_status',
			'meta_query'  => [[
				'key'     => '_enable_order_edit',
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
	 * @param $editable
	 * @param $order
	 * @return boolen
	 */
	public function bp_add_order_statuses_to_editable( $editable, $order ) {
		if ( $order->has_status( $this->get_status_editable() ) ) {
			return true;
		}
		return $editable;
	}
}
