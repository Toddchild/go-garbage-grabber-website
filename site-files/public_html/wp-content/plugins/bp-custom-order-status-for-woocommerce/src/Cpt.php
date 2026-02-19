<?php
namespace Brightplugins_COS;

class Cpt {

	public function __construct() {
		add_action( 'init', [$this, 'orderstatus_custom_post_register'] );
		add_action( 'admin_menu', [$this, 'add_order_status_menu'] );
		add_action( 'parent_file', [$this, 'wc_as_highlight'] );
		add_filter( 'enter_title_here', [$this, 'change_title_text'] );
		//$this->status_meta_box();
		add_action( 'after_setup_theme', function(){
            $this->status_meta_box();
        } );
	}
	/**
	 * @param $title
	 * @return string
	 */
	public function change_title_text( $title ) {
		$screen = get_current_screen();

		if ( 'order_status' == $screen->post_type ) {
			$title = 'Add Order Status Name';
		}

		return $title;
	}

	/**
	 * Custom status options.
	 *
	 * @return void.
	 */

	public function status_meta_box() {

		$prefix = 'wcbv-order-status-new';
		global $pagenow;
		// Create a metabox
		\CSF::createMetabox( $prefix, array(
			'title'              => __( 'Order Status Options', 'bp-custom-order-status' ),
			'post_type'          => 'order_status',
			'data_type'          => 'unserialize',
			'context'            => 'normal',
			'priority'           => 'default',
			'exclude_post_types' => array(),

			'show_restore'       => false,
			'enqueue_webfont'    => true,
			'async_webfont'      => false,
			'output_css'         => true,
			'nav'                => 'normal',

			'class'              => '',
		) );

		$attributes = array(
			'maxlength' => 17,
			'required'  => 'required',
		);
		if (  ( 'post.php' == $pagenow ) ) {
			$attributes = array(
				'readonly'  => 'readonly',
				'maxlength' => 17,
			);
		}
		\CSF::createSection( $prefix, array(
			'fields' => array(

				array(
					'id'         => 'status_slug',
					'type'       => 'text',
					'title'      => 'Slug',
					'subtitle'   => __( '* Without wc- prefix', 'bp-custom-order-status' ),
					'desc'       => __( 'Do not use more than 17 characters, do not begin with numbers, and do not use a name that is solely numbered(ex: 3456).<br>Avoid using accents or special characters. Otherwise, until you modify the order status, your orders may be concealed on the orders list.', 'bp-custom-order-status' ),
					'sanitize'   => 'sanitize_title',
					'attributes' => $attributes,
				),
				array(
					'id'    => 'status_icon',
					'type'  => 'icon',
					'title' => __( 'Status Icon', 'bp-custom-order-status' ),
				),

				array(
					'id'      => 'what_to_show',
					'type'    => 'select',
					'title'   => __( 'Status View', 'bp-custom-order-status' ),
					'desc'    => __( 'Choose where the icon should be placed. If icon is not set this option is ignored.<p> Show Name: Icon will be used only on actions area<br>Show Icon: Icon will replace the status name on Orders page', 'bp-custom-order-status' ),
					'default' => 'text',
					'options' => array(
						'icon' => __( 'Status Icon', 'bp-custom-order-status' ),
						'text' => __( 'Status Name', 'bp-custom-order-status' ),
					),
				),

				array(
					'id'      => 'text_color',
					'default' => '#000000',
					'type'    => 'color',
					'title'   => __( 'Color', 'bp-custom-order-status' ),
					'desc'    => __( 'This color is applied to the Text and to the Icon', 'bp-custom-order-status' ),
				),
				array(
					'id'         => 'background_color',
					'default'    => '#ffffff',
					'type'       => 'color',
					'title'      => __( 'Text Background Color', 'bp-custom-order-status' ),
					'dependency' => array( 'what_to_show', '==', 'text' ),
				),
				array(
					'id'      => 'is_status_paid',
					'type'    => 'switcher',
					'title'   => __( 'Paid Status', 'bp-custom-order-status' ),
					'desc'    => __( 'Enable if order on this status has been paid', 'bp-custom-order-status' ),
					'default' => '0',
				),
				array(
					'id'      => 'downloadable_grant',
					'type'    => 'switcher',
					'title'   => __( 'Download Access', 'bp-custom-order-status' ),
					'desc'    => __( 'Enable this option to grant access to downloads when orders are on this status', 'bp-custom-order-status' ),
					'default' => '0',
				),
				array(
					'id'      => '_enable_action_status',
					'type'    => 'switcher',
					'title'   => __( 'Add to actions on orders page', 'bp-custom-order-status' ),
					'default' => '1',
				),
				array(
					'id'      => '_enable_bulk',
					'type'    => 'switcher',
					'title'   => __( 'Add to bulk actions list', 'bp-custom-order-status' ),
					'desc'    => __( 'Enable to add this order status in the Bulk Actions list.', 'bp-custom-order-status' ),
					'default' => '1',
				),
				array(
					'id'      => '_enable_order_edit',
					'type'    => 'switcher',
					'title'   => __( 'Edit Mode', 'bp-custom-order-status' ),
					'desc'    => __( 'Enable this to edit order data at this status.', 'bp-custom-order-status' ),
					'default' => '0',
				),
				array(
					'id'    => '_enable_email',
					'type'  => 'switcher',
					'title' => __( 'Email Notification', 'bp-custom-order-status' ),
					'desc'  => __( 'Enable this if you want notify by email when order status change to this status', 'bp-custom-order-status' ),

				),
				array(
					'id'         => '_email_type',
					'type'       => 'select',
					'title'      => __( 'Email Recipient', 'bp-custom-order-status' ),
					'default'    => 'admin',
					'options'    => array(
						'admin'    => 'Admin',
						'customer' => 'Customer',
					),
					'dependency' => array( '_enable_email', '==', 'true' ),
				),
				array(
					'id'         => '_recipient_cc',
					'type'       => 'group',
					'title'      => 'Email Copy',
					'dependency' => array( '_enable_email', '==', 'true' ),
					'subtitle'   => __( 'Send a copy to these emails', 'bp-custom-order-status' ),
					'fields'     => array(
						array(
							'id'    => '_recipient_cc_email',
							'type'  => 'text',
							'title' => 'Email',
						),
					),
				),
				array(
					'id'    => 'icon_code',
					//'subtitle' => __( 'For the chosen Status Icon', 'bp-custom-order-status' ),
					'type'  => 'text',
					'class' => 'hidden',
					//'title' => __( 'Icon Code', 'bp-custom-order-status' ),
				),
				array(
					'type'     => 'callback',
					'function' => 'bpos_cb_strtolower_status_slug',
					'class'    => 'hidden',
				),
			),
		) );

	}

	/**
	 * @param  $parent_file
	 * @return mixed
	 */
	public function wc_as_highlight( $parent_file ) {
		global $submenu_file, $current_screen;

		// Set correct active/current menu and submenu in the WordPress Admin menu for the "example_cpt" Add-New/Edit/List
		if ( 'order_status' == $current_screen->post_type ) {
			$submenu_file = 'edit.php?post_type=order_status';
			$parent_file  = 'woocommerce';
		}
		return $parent_file;
	}
	public function add_order_status_menu() {
		add_submenu_page( 'woocommerce', 'Order Status', 'Order Status', 'manage_woocommerce', 'edit.php?post_type=order_status' );
	}
	public function orderstatus_custom_post_register() {
		$labels = array(
			'name'               => _x( 'Order Status', 'custom_order_status', 'bp-custom-order-status' ),
			'singular_name'      => _x( 'Order Status', 'post type singular name', 'bp-custom-order-status' ),
			'menu_name'          => _x( 'Order Status', 'admin menu', 'bp-custom-order-status' ),
			'name_admin_bar'     => _x( 'Order Status', 'add new on admin bar', 'bp-custom-order-status' ),
			'add_new'            => _x( 'Add New', 'custom_order_status', 'bp-custom-order-status' ),
			'add_new_item'       => __( 'Add New  Order Status', 'bp-custom-order-status' ),
			'new_item'           => __( 'New  Order Status', 'bp-custom-order-status' ),
			'edit_item'          => __( 'Edit  Order Status', 'bp-custom-order-status' ),
			'view_item'          => __( 'View  Order Status', 'bp-custom-order-status' ),
			'all_items'          => __( 'All  Order Status', 'bp-custom-order-status' ),
			'search_items'       => __( 'Search  Order Status', 'bp-custom-order-status' ),
			'parent_item_colon'  => __( 'Parent  Order Status:', 'bp-custom-order-status' ),
			'not_found'          => __( 'No  Order Status found.', 'bp-custom-order-status' ),
			'not_found_in_trash' => __( 'No  Order Status found in Trash.', 'bp-custom-order-status' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Description.', 'bp-custom-order-status' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'order_status' ),
			'capability_type'    => 'post',
			'has_archive'        => 'order_status',
			'hierarchical'       => false,
			'supports'           => array( 'title' ),
		);

		register_post_type( 'order_status', $args );

	}

}
