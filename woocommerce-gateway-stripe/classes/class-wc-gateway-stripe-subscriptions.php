<?php


/**
 * WC_Gateway_Stripe_Subscriptions class.
 * 
 * @extends WC_Gateway_Stripe
 */
class WC_Gateway_Stripe_Subscriptions extends WC_Gateway_Stripe {

	function __construct() { 
	
		parent::__construct();
		
		add_action( 'scheduled_subscription_payment_' . $this->id, array( &$this, 'scheduled_subscription_payment' ), 10, 3 );

		add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( &$this, 'remove_renewal_order_meta' ), 10, 4 );
	}
	
	/**
     * Process the payment
     */
	function process_payment( $order_id ) {
		global $woocommerce;
		
		if ( class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Order::order_contains_subscription( $order_id ) ) {	
	
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
					
				if ( method_exists( 'WC_Subscriptions_Order', 'get_total_initial_payment' ) ) {
					$initial_payment = WC_Subscriptions_Order::get_total_initial_payment( $order );
				} else {
					$initial_payment = WC_Subscriptions_Order::get_sign_up_fee( $order ) + WC_Subscriptions_Order::get_price_per_period( $order );
				}
				
				$customer_response = $this->add_customer_to_order( $order, $customer_id, $stripe_token );
				
				if ( $initial_payment > 0 )
					$payment_response = $this->process_subscription_payment( $order, $initial_payment );
				
				if ( is_wp_error( $customer_response ) ) {
					
					throw new Exception( $customer_response->get_error_message() );
					
				} else if ( isset( $payment_response ) && is_wp_error( $payment_response ) ) {
					
					throw new Exception( $payment_response->get_error_message() );
					
				} else {
					
					// Payment complete
					$order->payment_complete();
					
					// Remove cart
					$woocommerce->cart->empty_cart();
					
					// Activate subscriptions
					WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					
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
	 * scheduled_subscription_payment function.
	 * 
	 * @param $amount_to_charge float The amount to charge.
	 * @param $order WC_Order The WC_Order object of the order which the subscription was purchased in.
	 * @param $product_id int The ID of the subscription product for which this payment relates.
	 * @access public
	 * @return void
	 */
	function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {

		$result = $this->process_subscription_payment( $order, $amount_to_charge );
		
		if ( is_wp_error( $result ) ) {
			
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
			
		} else {
			
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
			
		}
		
	}
	
	/**
	 * process_subscription_payment function.
	 * 
	 * @access public
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param string $stripe_token (default: '')
	 * @return void
	 */
	function process_subscription_payment( $order = '', $amount = 0 ) {
		global $woocommerce;
		
		$order_items = $order->get_items();
		$product = $order->get_product_from_item( array_shift( $order_items ) );
		$subscription_name = sprintf( __( 'Subscription for "%s"', 'wc_stripe' ), $product->get_title() ) . ' ' . sprintf( __( '(Order %s)', 'wp_stripe' ), $order->get_order_number() );
				
		if ( $amount * 100 < 50 ) 
			return new WP_Error( 'stripe_error', __( 'Minimum amount is 0.50', 'wc_stripe' ) );	
		
		$stripe_customer = get_post_meta( $order->id, '_stripe_customer_id', true );
		
		if ( ! $stripe_customer ) 
			return new WP_Error( 'stripe_error', __( 'Customer not found', 'wc_stripe' ) );
		
		// Charge the customer
		$response = $this->stripe_request( array(
			'amount' 			=> $amount * 100, // In cents, minimum amount = 50
			'currency' 			=> strtolower( get_woocommerce_currency() ),
			'description' 		=> $subscription_name,
			'customer'			=> $stripe_customer
		), 'charges' );
	
		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			$order->add_order_note( sprintf( __('Stripe subscription payment completed (Charge ID: %s)', 'wc_stripe' ), $response->id ) );
			
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

	/**
	 * Don't transfer Stripe customer/token meta when creating a parent renewal order.
	 * 
	 * @access public
	 * @param array $order_meta_query MySQL query for pulling the metadata
	 * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int $renewal_order_id Post ID of the order created for renewing the subscription
	 * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
	 * @return void
	 */
	function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {

		if ( 'parent' == $new_order_role )
			$order_meta_query .= " AND `meta_key` NOT LIKE '_stripe_customer_id' "
							  .  " AND `meta_key` NOT LIKE '_stripe_token' ";

		return $order_meta_query;
	}
}