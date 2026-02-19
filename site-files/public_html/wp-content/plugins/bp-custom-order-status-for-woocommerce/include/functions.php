<?php

/**
 * @return mixed
 */
function bpcosOrderStatusList() {
	$allStatus                  = array();
	$statuses                   = wc_get_order_statuses();
	$allStatus['bpos_disabled'] = 'No changes';
	foreach ( $statuses as $status => $status_name ) {
		$allStatus[substr( $status, 3 )] = $status_name;
	}
	return $allStatus;

}
//Preorder Transition Status
add_filter( 'change_order_status_on_preorder_date', function ( $status ) {
	$bvos_options = get_option( 'wcbv_status_default' );
	return $bvos_options['preorder_status'];
}, 30, 1 );
/**
 * strtolower for status slug
 *
 */
if ( !function_exists( 'bpos_cb_strtolower_status_slug' ) ) {

	function bpos_cb_strtolower_status_slug() {

		$status_slug = wc_strtolower( get_post_meta( get_the_ID(), 'status_slug', true ) );

		update_post_meta( get_the_ID(), 'status_slug', $status_slug );
		echo '<style>.post-type-order_status #edit-slug-box{display:none}</style>';
	}
}

/**
 * 
 */
$can_new_version_be_instantiated = isset( NS7_RDNC::$version ) && version_compare( NS7_RDNC::$version, '2.0', '>=' );
if( $can_new_version_be_instantiated ) {
	NS7_RDNC::instance()->add_notification( 255, '3f8f60490970d1a3', 'https://brightplugins.com', 6, 'wcbv-order-status-setting' );
}