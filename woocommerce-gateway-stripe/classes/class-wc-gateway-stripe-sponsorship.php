<?php


/**
 * WC_Gateway_Stripe_Sponsorship class.
 * 
 * Add to support sponsorship projects. Customer info is saved as meta data on
 * the order until a project goal is met. Once the goal is met, the payments are
 * processed. If a goal is not met, the orders are canceled.
 * 
 * @extends WC_Gateway_Stripe
 * @category	Class
 * @author		Justin Kussow
 */
class WC_Gateway_Stripe_Sponsorship extends WC_Gateway_Stripe {

	function __construct() { 
	
		parent::__construct();
		
		add_action( 'complete_sponsorship_payment_' . $this->id, array( &$this, 'complete_sponsorship_payment' ), 10, 3 );
	}
	
	/**
	 * complete_sponsorship_payment function.
	 * 
	 * @param $amount_to_charge float The amount to charge.
	 * @param $order WC_Order The WC_Order object of the order which the subscription was purchased in.
	 * @param $product_id int The ID of the subscription product for which this payment relates.
	 * @access public
	 * @return void
	 */
	function complete_sponsorship_payment( $amount_to_charge, $order, $product_id ) {
		$result = $this->process_sponsorship_payment( $order, $amount_to_charge );

		if ( is_wp_error( $result ) ) {
			// record payment failed
		} else {
			// record payments succeeded
		}
	}
	
	/**
     * Process the payment
     */
	function process_payment( $order_id ) {
		global $woocommerce;
		
		if ( class_exists( 'WC_Sponsorship_Order' ) && WC_Sponsorship_Order::order_contains_sponsorship( $order_id ) ) {					
	
			$order = new WC_Order( $order_id );
			
			$stripe_token = isset( $_POST['stripe_token'] ) ? woocommerce_clean( $_POST['stripe_token'] ) : '';
			
			// Use Stripe CURL API for payment
			try {
				$post_data = array();
				$customer_id = 0;

				// Check if paying via customer ID
				if ( isset( $_POST['stripe_customer_id'] ) && $_POST['stripe_customer_id'] !== 'new' && is_user_logged_in() ) {
					$customer_ids = get_user_meta( get_current_user_id(), '_stripe_customer_id', false );
	
					if ( isset( $customer_ids[ $_POST['stripe_customer_id'] ]['customer_id'] ) ) 
						$customer_id = $customer_ids[ $_POST['stripe_customer_id'] ]['customer_id'];
					else
						throw new Exception( __( 'Invalid card.', 'wc_stripe' ) );
				}
				
				// Else, Check token
				elseif ( empty( $stripe_token ) )
					throw new Exception( __( 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'wc_stripe' ) );
				
				$customer_response = $this->add_customer_to_order( $order, $customer_id, $stripe_token );$customer_response = $this->add_customer_to_order( $order, $stripe_token );
					
				if ( is_wp_error( $customer_response ) ) {
					throw new Exception( $customer_response->get_error_message() );
				} else {
					// Mark as on-hold (we're awaiting the cheque)
					$order->update_status('on-hold', 'Awaiting the sponsorship project\'s goal to be met.');

					// Empty awaiting payment session
					if ( defined( $_SESSION ) && array_key_exists( 'order_awaiting_payment', $_SESSION ) )
						unset( $_SESSION['order_awaiting_payment'] );

					// Remove cart
					$woocommerce->cart->empty_cart();

					// Store token
					if ( $stripe_token )
						update_post_meta( $order->id, '_stripe_token', $stripe_token );
					
					// Return thank you page redirect
					return array(
						'result' 	=> 'success',
						'redirect'	=> $this->get_return_url( $order )
					);
				}
					
			} catch( Exception $e ) {
				$woocommerce->add_error( __('Error:', 'wc_stripe') . ' "' . $e->getMessage() . '"' );
				return;
			}
			
		} else {
			
			return parent::process_payment( $order_id );
			
		}
	}
	
	/**
	* process_sponsorship_payment function.
	* 
	* @access public
	* @param mixed $order
	* @param int $amount (default: 0)
	* @param string $stripe_token (default: '')
	* @return void
	*/
   function process_sponsorship_payment( $order = '', $amount = 0 ) {
	   global $woocommerce;

	   $order_items = $order->get_items();
	   $firstItem = reset( $order_items );
	   $product = get_product( $firstItem['product_id'] );
	   $sponsorship_name = sprintf( __( 'Sponsorship for "%s"', 'wc_stripe' ), $product->get_title() ) . ' ' . sprintf( __( '(Order %s)', 'wp_stripe' ), $order->get_order_number() );

	   if ( $amount * 100 < 50 ) 
		   return new WP_Error( 'stripe_error', __( 'Minimum amount is 0.50', 'wc_stripe' ) );	

	   $stripe_customer = get_post_meta( $order->id, '_stripe_customer_id', true );

	   if ( ! $stripe_customer ) 
		   return new WP_Error( 'stripe_error', __( 'Customer not found', 'wc_stripe' ) );

	   // Charge the customer
	   $response = $this->stripe_request( array(
		   'amount' 			=> $amount * 100, // In cents, minimum amount = 50
		   'currency' 			=> strtolower( get_woocommerce_currency() ),
		   'description' 		=> $sponsorship_name,
		   'customer'			=> $stripe_customer
	   ), 'charges' );

	   if ( is_wp_error( $response ) ) {
		   return $response;
	   } else {
		   $order->update_status('completed', 'Sponsorship project\'s goal has been met.');
		   $order->add_order_note( sprintf( __('Stripe sponsorship payment completed (Charge ID: %s)', 'wc_stripe' ), $response->id ) );

		   return true;
	   }

   }
	
	/**
	 * add_customer_to_order function.
	 * 
	 * @access public
	 * @param mixed $order
	 * @param string $customer_id (default: '')
	 * @param string $stripe_token (default: '')
	 * @return void
	 */
	function add_customer_to_order( $order, $customer_id = '', $stripe_token = '' ) {
		
		// If we have a customer id, use it for the order
		if ( $customer_id ) {
			
			update_post_meta( $order->id, '_stripe_customer_id', $customer_id );
			
		}
		
		// If we have a token, we can create a customer with it
		elseif ( $stripe_token ) {
			$response = $this->stripe_request( array(
				'email'       => $order->billing_email,
				'description' => 'Customer: ' . $order->shipping_first_name . ' ' . $order->shipping_last_name,
				'card'        => $stripe_token
			), 'customers' );
		
			if ( is_wp_error( $response ) ) {
				return $response;
			} else {
				$order->add_order_note( sprintf( __('Stripe customer added: ', 'wc_stripe' ), $response->id ) );
				
				if ( is_user_logged_in() )
					add_user_meta( get_current_user_id(), '_stripe_customer_id', array(
						'customer_id' 	=> $response->id,
						'active_card' 	=> $response->active_card->last4,
						'exp_year'		=> $response->active_card->exp_year,
						'exp_month'		=> $response->active_card->exp_month,
					) );
				
				update_post_meta( $order->id, '_stripe_customer_id', $response->id );
			}
		
		}
	
	}
}