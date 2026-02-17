<?php
namespace Brightplugins_COS;

class StatusColums {

	public function __construct() {
		add_action( 'manage_order_status_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_order_status_posts_columns', array( $this, 'columns' ) );

	}
	/**
	 * Add the custom columns to the order_status post type:
	 *
	 * @param  array  $columns
	 * @return void
	 */
	public function columns( $columns ) {
		unset( $columns['date'] );
		return array(
			'cb'              => '<input type="checkbox" />',
			'title'           => __( 'Status Name', 'bp-custom-order-status' ),
			'status_preview'  => __( 'Status Preview', 'bp-custom-order-status' ),
			'email_recipient' => __( 'Email Recipient(s)', 'bp-custom-order-status' ),
			'email_template'  => __( 'Email Template', 'bp-custom-order-status' ),
			'os_date'         => __( 'Date', 'bp-custom-order-status' ),
		);
	}

	/**
	 * Add the data to the custom columns for the order_status post type:
	 *
	 * @param  loop   $column
	 * @param  int    $post_id
	 * @return void
	 */
	public function column( $column, $post_id ) {

		switch ( $column ) {

		case 'email_recipient':
			echo ucfirst( get_post_meta( $post_id, '_email_type', true ) ) . '<br>';
			$rec_cc = get_post_meta( $post_id, '_recipient_cc', true );

			if ( $rec_cc ) {

				foreach ( $rec_cc as $key => $value ) {
					//echo $value;
					echo $value['_recipient_cc_email'] . '<br>';
				}
			}
			break;

		case 'email_template':
			$slug          = get_post_meta( $post_id, 'status_slug', true );
			$template_link = admin_url( 'admin.php?page=wc-settings&tab=email&section=bvos_custom_' . $slug );
			echo '<a href="' . esc_url( $template_link ) . '">' . __( 'Edit Template', 'bp-custom-order-status' ) . '</a>';
			//http://bright.plugins/wp-admin/admin.php?page=wc-settings&tab=email&section=bvos_custom_pickup-point

			break;

		case 'os_date':
			echo get_the_date();

			break;

		case 'status_preview':
			$preview_type = get_post_meta( $post_id, 'what_to_show', true );
			$icon         = get_post_meta( $post_id, 'status_icon', true );
			$bg_color     = get_post_meta( $post_id, 'background_color', true );
			$text_color   = get_post_meta( $post_id, 'text_color', true );

			if ( 'text' == $preview_type ) {
				echo '<div style="border-radius:4px;padding:6px 12px;width:auto;display:inline-block;background-color:' . esc_attr( $bg_color ) . ';color:' . esc_attr( $text_color ) . ';" class="order-status-preview-txt"><span>' . get_the_title() . '</span></div>';
			} else {
				echo '<i style="font-size:28px;color:' . esc_attr( $text_color ) . '" class="' . esc_attr( $icon ) . '"></i>';
			}
			break;

		}
	}

}
