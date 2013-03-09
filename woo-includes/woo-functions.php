<?php
/**
 * Functions used by plugins
 */

if ( ! class_exists( 'WC_Dependencies' ) ) require_once( 'class-wc-dependencies.php' );

/**
 * WC Detection
 **/
function is_woocommerce_active() {

	return WC_Dependencies::woocommerce_active_check();
	
}