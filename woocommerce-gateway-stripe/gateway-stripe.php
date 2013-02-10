<?php
/*
Plugin Name: WooCommerce Stripe Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: A payment gateway for Stripe (https://stripe.com/). A Stripe account and a server with Curl, SSL support, and a valid SSL certificate is required (for security reasons) for this gateway to function. Stripe currently only supports USD and CAD.
Version: 1.5.1
Author: Mike Jolley
Author URI: http://mikejolley.com

	Copyright: © 2009-2011 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html

	Stripe Docs: https://stripe.com/docs
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'b022f53cd049144bfd02586bdc0928cd', '18627' );

add_action( 'plugins_loaded', 'woocommerce_stripe_init', 0 );

function woocommerce_stripe_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) )
		return;

	load_plugin_textdomain( 'wc_stripe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	include_once( 'classes/class-wc-gateway-stripe.php' );

	if ( class_exists( 'WC_Subscriptions_Order' ) )
		include_once( 'classes/class-wc-gateway-stripe-subscriptions.php' );
	
	// JDK 1/30/2013 added
	if ( class_exists( 'WC_Sponsorship_Order' ) )
		include_once( 'classes/class-wc-gateway-stripe-sponsorship.php' );

	/**
	 * account_cc function.
	 *
	 * @access public
	 * @return void
	 */
	function woocommerce_stripe_saved_cards() {
		$credit_cards = get_user_meta( get_current_user_id(), '_stripe_customer_id', false );

		if ( ! $credit_cards )
			return;

        if ( isset( $_POST['delete_card'] ) && wp_verify_nonce( $_POST['_wpnonce'], "stripe_del_card" ) ) {
			$credit_card = $credit_cards[ (int) $_POST['delete_card'] ];
			delete_user_meta( get_current_user_id(), '_stripe_customer_id', $credit_card );
		}

		$credit_cards = get_user_meta( get_current_user_id(), '_stripe_customer_id', false );

		if ( ! $credit_cards )
			return;
		?>
			<h2 id="saved-cards" style="margin-top:40px;"><?php _e('Saved cards', 'wc_stripe' ); ?></h2>
			<table class="shop_table">
				<thead>
					<tr>
						<th><?php _e('Card ending in...','wc_stripe'); ?></th>
						<th><?php _e('Expires','wc_stripe'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $credit_cards as $i => $credit_card ) : ?>
					<tr>
                        <td><?php esc_html_e($credit_card['active_card']); ?></td>
                        <td><?php echo esc_html($credit_card['exp_month']) . '/' . esc_html($credit_card['exp_year']); ?></td>
						<td>
                            <form action="#saved-cards" method="POST">
                                <?php wp_nonce_field ( 'stripe_del_card' ); ?>
                                <input type="hidden" name="delete_card" value="<?php esc_attr($i); ?>">
                                <input type="submit" value="<?php _e( 'Delete card', 'wc_stripe' ); ?>">
                            </form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php
	}

	add_action( 'woocommerce_after_my_account', 'woocommerce_stripe_saved_cards' );

	/**
 	* Add the Gateway to WooCommerce
 	**/
	function add_stripe_gateway($methods) {
		if ( class_exists( 'WC_Subscriptions_Order' ) )
			$methods[] = 'WC_Gateway_Stripe_Subscriptions';
		// JDK 1/30/2013 added for sponsorhip support
		else if ( class_exists( 'WC_Sponsorship_Order' ) )
			$methods[] = 'WC_Gateway_Stripe_Sponsorship';
		else
			$methods[] = 'WC_Gateway_Stripe';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_stripe_gateway' );
}
