<?php
/*
	Plugin Name:       Unified Payment Services Limited - WooCommerce Gateway
	Plugin URI:        http://olalekan.pw/ups
	Description:       UPS Gateway allows you to accept payment on your Woocommerce store via Visa Cards and Mastercards.
	Version:           1.0.0
	Author:            Olalekan Omotayo
	Author URI:        http://olalekan.pw/
	License:           GPL-2.0+
 	License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 	GitHub Plugin URI: 
*/

// Include UPS Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'oo_ups_payment_gateway_init', 0 );
function oo_ups_payment_gateway_init()
{
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-ups.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'oo_add_ups_gateway' );
	function oo_add_ups_gateway( $methods ) {
		$methods[] = 'WC_OO_Unified_Payment_System';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'oo_ups_action_links' );
function oo_ups_action_links($links)
{
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'oo-ups' ) . '</a>', 
	);
	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}