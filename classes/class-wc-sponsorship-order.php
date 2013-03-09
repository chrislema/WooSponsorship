<?php

/**
 * Sponsorship Order Class
 * 
 * The WooCommerce Sponsorship order class handles individual project 
 * product data.
 *
 * @class 		WC_Sponsorship_Order
 * @package		WooCommerce Sponsorship
 * @category	Class
 * @author		Justin Kussow
 */
class WC_Sponsorship_Order {

	function __construct() {
		add_action( 'woocommerce_thankyou', array( &$this, 'sponsorship_thank_you' ) );
	}


	function sponsorship_thank_you( $order_id ){
		if( WC_Sponsorship_Order::order_contains_sponsorship( $order_id ) ) {
			// as good of a time as any to see if the project is complete...			
			$project = get_post_meta( $order_id, '_sponsorship_project', true );
			$project_complete = WC_Sponsorship_Product::check_project_progress( $project );
			
			if ( $project_complete ) {
				echo '<p>Your contribution has pushed the project over it\'s goal! Your payment will be processed soon.</p>';
			} else {
				echo '<p>Your contribution will be processed when the project goal has been met.</p>';
			}
			echo '<p>' . sprintf( 'View the status of your subscription in %syour account%s.', '<a href="' . get_permalink( woocommerce_get_page_id( 'myaccount' ) ) . '">', '</a>' ) . '</p>';
		}
	}

	public static function order_contains_sponsorship( $order ) {
		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$contains_contribution = false;

		foreach ( $order->get_items() as $order_item ) {
			if ( WC_Sponsorship_Product::is_sponsorship_contribution_level( $order_item['product_id'] ) ) {
				$contains_contribution = true;
				break;
			}
		}

		return $contains_contribution;
	}
	
	public static function cancel_order( $order, $note ) {
		if ( !is_object( $order ) )
			$order = new WC_Order( $order );
		
		if ( !WC_Sponsorship_Order::order_contains_sponsorship( $order ) )
			return;
		
		$order->update_status( 'cancelled', $note );
		update_post_meta( $order->id, '_stripe_customer_id', null);
		update_post_meta( $order->id, '_stripe_token', null);
	}
	
	public static function complete_order( $order, $project_id, $amount_to_charge ) {
		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );
		
		if ( !WC_Sponsorship_Order::order_contains_sponsorship( $order ) )
			return;
		
		do_action( 'complete_sponsorship_payment_' . $order->payment_method, $amount_to_charge, $order, $project_id );
	}
}

$GLOBALS[ 'WC_Sponsorship_Order' ] = new WC_Sponsorship_Order();
?>
