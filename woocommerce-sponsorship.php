<?php
/*
  Plugin Name: WooCommerce Sponsorship Add-On
  Plugin URI: http://woothemes.com/woocommerce
  Description: Extends WooCommerce to provide projects that can be sponsored. Requires WooCommerce 1.6+
  Version: 1.0
  Author: Justin Kussow (jdkussow@gmail.com) and Chris Lema (cflema@gmail.com)
  Author URI: http://www.chrislema.com

  Copyright: © 2009-2012 WooThemes.
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Required functions
 * */
if ( ! function_exists( 'is_woocommerce_active' ) )
	require_once( 'woo-includes/woo-functions.php' );

if ( is_woocommerce_active() ) {

	if ( class_exists( 'WC_Sponsorship' ) ) return;

	class WC_Sponsorship {

		var $plugin_url;
		var $plugin_path;
		var $messages = array( );
		public static $name = 'sponsorship';

		function __construct() {
			// Include required functions and classes
			$this->includes();
			
			if ( is_admin() ) {
				// add admin styles and scripts
				add_action( 'woocommerce_admin_css', array( &$this, 'admin_styles' ) );
				add_action( 'admin_enqueue_scripts', array( &$this, 'admin_scripts' ) );
				add_action( 'admin_menu', array( &$this, 'menu' ) );
			} else {
				// add front end styles and scripts
				add_action( 'wp_print_scripts', array( &$this, 'frontend_scripts' ) );
			}

			add_filter( 'add_to_cart_redirect', array( &$this, 'add_product_redirect' ) );
			add_filter( 'woocommerce_add_to_cart_validation', array( &$this, 'add_product_validation' ), 10, 3 );
			
			// some sidebar stuff
			add_action( 'wp_head', create_function( "", 'ob_start();' ) );
			add_action( 'get_sidebar', array( &$this, 'get_wc_sponsorship_sidebar' ) );
			add_action( 'wp_footer', array( &$this, 'wc_sponsorship_sidebar_class_replace' ) );
			register_sidebar( array( 'name' => 'Sponsorship Sidebar', 'id' => 'sponsorship_project_sidebar', 'description' => "Sidebar that overrides other sidebars when viewing a single product", ) );

			// and a widget
			add_action( 'widgets_init', array( &$this, 'init_widgets' ) );
		}

		/*
		 * Plugin maintenance - install and uninstall
		 */

		function activation() {
			//wp_create_category('Sponsorship Project', 'product_cat');
		}

		function deactivation() {
			
		}

		/*
		 * Include required functions and classes
		 */

		function includes() {
			include_once( 'classes/class-wc-sponsorship-product.php' );
			include_once( 'classes/class-wc-sponsorship-order.php' );
			include_once( 'classes/class-wc-sponsorship-checkout.php' );
		}

		/*
		 * Admin -	include necessary admin files, scripts, styles and handle any
		 * 			admin related actions and filters
		 */

		function admin_styles() {
			wp_enqueue_style( 'woocommerce_sponsorship_admin_styles', $this->plugin_url() . '/assets/css/admin.css' );
		}

		function admin_scripts() {
			//wp_register_script( 'wc-sponsorship-product-js', $this->plugin_url() . '/assets/js/wc-sponsorship-admin-product.js' );  
			//wp_enqueue_script( 'wc-sponsorship-product-js' );  
		}

		function menu() { }

		/*
		 * Front end -	include scripts, styles, and handle any front end related
		 * 				handlers for actions or filters
		 */

		function frontend_scripts() {
			wp_enqueue_style( 'woocommerce_sponsorship_styles', $this->plugin_url() . '/assets/css/wc-sponsorship.css' );
		}

		/*
		 * Helper functions 
		 */

		function plugin_url() {
			if ( $this->plugin_url ) return $this->plugin_url;
			return $this->plugin_url = plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) );
		}

		function plugin_path() {
			if ( $this->plugin_path ) return $this->plugin_path;
			return $this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		function is_sponsorship( $product ) {
			if ( !is_object( $product ) ) {
				$product = new WC_Product_Variable( $product );
			}
			if ( $product->is_type('variable') || $product->is_type('simple') ) {
				$pm = get_post_meta( $product->id, '_sponsorship', true );
				return is_array( $pm );
			}
			return ( $product->is_type( 'sponsorship-project' ) ) ? true : false;
		}

		function is_sponsorship_contribution_level( $product ) {
			if ( !is_object( $product ) ) {
				$product = new WC_Product_Variable( $product );
			}

			$prod_post = $product->post;
			if ( !$prod_post ) {
				$prod_post = get_post( $product->id );
			}

			if ( $prod_post->post_parent ) {
				return WC_Sponsorship::is_sponsorship( $prod_post->post_parent );
			}
			return false;
		}

		function cart_contains_sponsorship_contribution() {
			global $woocommerce;

			$contains_contribution = false;

			if ( !empty( $woocommerce->cart->cart_contents ) ) {
				foreach ( $woocommerce->cart->cart_contents as $cart_item ) {
					if ( WC_Sponsorship_Product::is_sponsorship_contribution_level( $cart_item[ 'product_id' ] ) ) {
						$contains_contribution = true;
						break;
					}
				}
			}

			return $contains_contribution;
		}

		function add_product_redirect( $url ) {
			global $woocommerce;

			if ( is_numeric( $_REQUEST[ 'add-to-cart' ] ) && WC_Sponsorship_Product::is_sponsorship_contribution_level( ( int ) $_REQUEST[ 'add-to-cart' ] ) ) {
				$woocommerce->clear_messages();
				$url = $woocommerce->cart->get_checkout_url();
			}

			return $url;
		}

		function add_product_validation( $valid, $product_id, $quantity ) {
			global $woocommerce;

			if ( WC_Sponsorship_Product::is_sponsorship_contribution_level( $product_id ) ) {
				$woocommerce->cart->empty_cart();
			} elseif ( WC_Sponsorship::cart_contains_sponsorship_contribution() ) {
				WC_Sponsorship::remove_sponsorship_from_cart();

				$woocommerce->add_error( 'A sponsorship contribution has been removed from your cart. Due to payment gateway restrictions, products can not be purchased at the same time.' );
				$woocommerce->set_messages();

				// Redirect to cart page to remove subscription & notify shopper
				add_filter( 'add_to_cart_fragments', array( &$this, 'redirect_ajax_add_to_cart' ) );
			}

			return true;
		}

		function remove_sponsorship_from_cart() {
			global $woocommerce;

			foreach ( $woocommerce->cart->cart_contents as $cart_item_key => $cart_item ) if ( WC_Sponsorship_Product::is_sponsorship_contribution_level( $cart_item[ 'product_id' ] ) ) $woocommerce->cart->set_quantity( $cart_item_key, 0 );
		}

		function get_wc_sponsorship_sidebar( $name ) {
			global $product;

			if ( is_single() && WC_Sponsorship_Product::is_sponsorship( $product ) && 'Sponsorship Sidebar' != $name ) {
				load_template( $this->plugin_path() . '/classes/class-wc-sponsorship-sidebar.php', true );

				static $class = "wc-sponsorship-hidden";
				$this->wc_sponsorship_sidebar_class_replace( $class );
			}
		}

		function wc_sponsorship_sidebar_class_replace( $c = '' ) {
			static $class = '';
			if ( !empty( $c ) ) {
				$class = $c;
			} else {
				echo str_replace( '<div id="sidebar">', '<div id="sidebar" class="' . $class . '"> ', ob_get_clean() );
				ob_start();
			}
		}

		function init_widgets() {
			if ( !class_exists( !'WC_Sponsorship_Levels_Widget' ) ) 
				require_once ( $this->plugin_path() . '/classes/widgets/class-wc-sponsorship-levels-widget.php');
			register_widget( 'WC_Sponsorship_Levels_Widget' );
			
			if ( !class_exists( !'WC_Sponsorship_Project_Status_Widget' ) ) 
				require_once ( $this->plugin_path() . '/classes/widgets/class-wc-sponsorship-project-status-widget.php');
			register_widget( 'WC_Sponsorship_Project_Status_Widget' );
		}

	}

	// end WC_Sponsorship
	// Init the main class
	$GLOBALS[ 'wc_sponsorship' ] = new WC_Sponsorship();

	// Hook into activation
	register_activation_hook( __FILE__, array( $GLOBALS[ 'wc_sponsorship' ], 'activation' ) );
	register_deactivation_hook( __FILE__, array( $GLOBALS[ 'wc_sponsorship' ], 'deactivation' ) );
} // end if ( is_woocommerce_active() )
?>
