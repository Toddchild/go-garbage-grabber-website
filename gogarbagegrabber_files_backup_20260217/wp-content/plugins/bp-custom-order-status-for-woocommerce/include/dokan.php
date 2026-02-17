<?php

add_filter( 'dokan_get_order_status_class', 'bpcosm_set_order_status_class', 10, 1 );

/**
 * @param $status
 * @return string
 */
function bpcosm_set_order_status_class( $class ) {
	return 'info';
}

add_filter( 'dokan_get_order_status_translated', 'bpcosm_set_order_status', 10, 2 );

/**
 * @param $status
 * @return string
 */
function bpcosm_set_order_status( $class, $status ) {
	$all_status = bpcosOrderStatusList();
	if ( array_key_exists( $status, $all_status ) ) {
		return $all_status[$status];
	}

}
