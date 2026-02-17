<?php

namespace Brightplugins_COS;

class Bootstrap {
	/**
	 * @var string
	 */
	public $woddp_title;
	/**
	 * @var string
	 */
	public $woddp_plugin_url;
	/**
	 * @var boolen
	 */
	public $woddp_activate;
	public function __construct() {
		$this->woddpDefination();
		new Cpt();
		new StatusColums();
		new Status();
		new Email();
		new Checkout();
		new Settings();

		add_action( 'admin_notices', [$this, 'review'] );
		add_action( 'admin_init', [$this, 'url_param_check'] );
		add_filter( 'cosm_upsale_notice', [$this, 'cosm_upsale_notice_render'] );
	}
	/**
	 * Get data of Custom Order status plugin
	 *
	 * @return void
	 */
	public function woddpDefination() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( 'bp-order-date-time-for-woocommerce/main.php' ) ) {

			$this->woddp_title      = __( 'Check Options', 'bv-order-status' );
			$this->woddp_activate   = true;
			$this->woddp_plugin_url = admin_url( 'admin.php?page=wcbp-woodevelivery-setting' );

		} elseif ( file_exists( WP_PLUGIN_DIR . '/bp-order-date-time-for-woocommerce/main.php' ) ) {

			$this->woddp_title      = __( 'Activate Now', 'bv-order-status' );
			$this->woddp_activate   = false;
			$this->woddp_plugin_url = wp_nonce_url( 'plugins.php?action=activate&plugin=bp-order-date-time-for-woocommerce/main.php&plugin_status=all&paged=1', 'activate-plugin_bp-order-date-time-for-woocommerce/main.php' );

		} else {

			$this->woddp_title      = __( 'Install Now', 'bv-order-status' );
			$this->woddp_activate   = false;
			$this->woddp_plugin_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=bp-order-date-time-for-woocommerce' ), 'install-plugin_bp-order-date-time-for-woocommerce' );

		}
	}
	
	/**
	 * @return null
	 */
	public function cosm_upsale_notice_render( $data ) {

		$data = '🌟 Checkout our new <b>Order Delivery Date Time & Pickup for WooCommerce</b> plugin.<br>It\'s enables customers to conveniently select the date and time they prefer for the delivery of their orders. <a href="' . $this->woddp_plugin_url . '">' . $this->woddp_title . '</a>';
		return $data;

	}
	/**
	 * simple dismissable logic
	 *
	 * @return void
	 */
	public function url_param_check() {
		if ( isset( $_GET['bpcosm-review-dismiss'] ) && 1 == $_GET['bpcosm-review-dismiss'] ) {
			update_option( 'dfwc_plugin_review', 1 );
		}
		if ( isset( $_GET['bpcosm-review-dismiss-temp'] ) && 1 == $_GET['bpcosm-review-dismiss-temp'] ) {
			set_transient( 'bpcosm_review_later', 1, 2 * WEEK_IN_SECONDS );
		}
	}
	
	/**
	 * Displays a review notice for the Custom Order Status Manager for WooCommerce plugin after 1 week of usage.
	 * If the user has dismissed the notice or has chosen to review later, the notice won't be displayed.
	 * If the user hasn't dismissed the notice and it has been more than 7 days since the plugin was installed, the notice will be displayed.
	 * The notice includes a request for a five-star review on WordPress.org and options to dismiss or review later.
	 *
	 * @return void
	 */
	public function review() {
		$dismiss_parm = array( 'bpcosm-review-dismiss' => '1' );
		$temp_dismiss = array( 'bpcosm-review-dismiss-temp' => '1' );

		$datetime1     = new \DateTime( date( 'Y-m-d h:i:s', get_option( 'bp_custom_order_status_installed' ) ) );
		$datetime2     = new \DateTime( date( 'Y-m-d h:i:s' ) );
		$diff_interval = $this->get_days( $datetime1, $datetime2 );

		if ( get_option( 'dfwc_plugin_review' ) || get_transient( 'bpcosm_review_later' ) ) {
			return;
		} elseif ( $diff_interval > 7 ) {

			?>
        <div class="notice notice-info bpcosm-review-notice">
			<h3><img draggable="false" class="emoji" alt="🎉" src="https://s.w.org/images/core/emoji/11/svg/1f389.svg">  Congrats!</h3>
        <p>You're using <strong>Custom Order Status Manager for WooCommerce</strong> plugin more than 1 week - that’s awesome! If you can spare a minute, please help us by leaving a five star review on WordPress.org.</p>
        <p><strong>~ Bright Plugins</strong></p>
        <p class="dfwc-message-actions">
            <a style="margin-right:8px;" href="https://wordpress.org/support/plugin/bp-custom-order-status-for-woocommerce/reviews/?filter=5#new-post" target="_blank" class="button button-primary">Okay, You Deserve It</a>
            <a style="margin-right:8px;" href="<?php echo esc_url( add_query_arg( $temp_dismiss ) ); ?>"  class="button">Nope, Maybe Later</a>
            <a href="<?php echo wp_nonce_url( add_query_arg( $dismiss_parm ) ); ?>" class=" button">Hide Notification</a>
        </p>
        </div>
        <?php }
	}
	/**
	 * @param $from_date
	 * @param $to_date
	 */
	public function get_days( $from_date, $to_date ) {
		return round(  ( $to_date->format( 'U' ) - $from_date->format( 'U' ) ) / ( 60 * 60 * 24 ) );
	}
	/**
	 * Check if WooCommerce is installed
	 *
	 * @since 1.2.7
	 * @access public
	 *
	 * @return bool
	 */
	public static function is_woocommerce_installed() {

		/**
		 * Checks if it is a multisite
		 */
		if ( is_multisite() ) {
			add_filter( 'active_plugins', function ( $active_plugins ) {

				$network = get_network();

				if ( !isset( $network->id ) ) {
					return $active_plugins;
				}
				$active_sitewide_plugins = get_network_option( $network->id, 'active_sitewide_plugins', null );

				if ( !empty( $active_sitewide_plugins ) ) {
					$network_active_plugins = array();

					foreach ( $active_sitewide_plugins as $key => $value ) {
						$network_active_plugins[] = $key;
					}

					$active_plugins = array_merge( $active_plugins, $network_active_plugins );
				}

				return $active_plugins;
			} );
		}

		$filter_active_plugins    = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
		$is_woocommerce_installed = in_array( 'woocommerce/woocommerce.php', $filter_active_plugins, true );

		return $is_woocommerce_installed;
	}

}
