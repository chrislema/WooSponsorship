<?php

/**
 * WC_Sponsorship_Checkout class.
 */
class WC_Sponsorship_Checkout {

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	function __construct() {
		add_filter( 'woocommerce_product_title', array( &$this, 'get_product_title_for_checkout' ), 10, 3 );
		add_action( 'woocommerce_checkout_update_order_meta', array( &$this, 'track_order' ) );
	}

	function get_product_title_for_checkout( $title, $product ) {
		if ( !is_object( $product ) ) {
			$product = new WC_Product_Variable( $product );
		}

		if ( WC_Sponsorship_Product::is_sponsorship_contribution_level( $product ) ) {
			$title = WC_Sponsorship_Product::get_contribution_level_title( $product );
		}

		return $title;
	}

	function track_order( $order ) {
		if ( !is_object( $order ) ) {
			$order = new WC_Order( $order );
		}

		$projects = array();
		foreach ( $order->get_items() as $order_item ) {
			if ( WC_Sponsorship_Product::is_sponsorship_contribution_level( $order_item['product_id'] ) ) {
				$cl = get_post( $order_item['product_id'] );
				if ( !in_array( $cl->post_parent, $projects ) ) {
					$projects[] = $cl->post_parent;
				}
			}
		}
		
		foreach ( $projects as $project ) {
			update_post_meta( $order->id, '_sponsorship_project', $project );
		}		
	}

}

$GLOBALS[ 'WC_Sponsorship_Checkout' ] = new WC_Sponsorship_Checkout(); // Init
?>
