<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! defined( 'WOE_IPN_URL_OPTION_KEY' ) ) {
	define( 'WOE_IPN_URL_OPTION_KEY', 'woe_ipn_url_key' );
}

if ( ! function_exists( "woe_is_zapier_connecting" ) ) {
	function woe_is_zapier_connecting() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return ! empty( $_GET['woe_zapier_export_api_key'] );
	}
}

add_filter( 'woe_check_running_options', function ( $is_backend ) {

	$is_cron              = defined( 'DOING_CRON' );
    //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
    $req_uri = $_SERVER['REQUEST_URI'] ?? '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
    $is_frontend_checkout = isset( $_REQUEST['wc-ajax'] ) && $_REQUEST['wc-ajax'] === 'checkout' || isset( $_POST['woocommerce_checkout_place_order'] )
	                        || preg_match( '/\bwc\-api\b/', $req_uri ) // WC_API
	                        || preg_match( '/\bwc\/v\d+\b/', $req_uri ) // Rest API
	                        || preg_match( '/\bwc\/store\/v\d+\b/', $req_uri ) // Rest API for blocks cart
	                        || woe_is_zapier_connecting();
	// use preg to match multilpe values
	$ipn_url = get_option( WOE_IPN_URL_OPTION_KEY );
	$is_ipn  = ( $ipn_url AND preg_match( "#($ipn_url)#i", $req_uri ) );
	$is_jetpack = ( isset($_SERVER['HTTP_USER_AGENT'])  AND $_SERVER['HTTP_USER_AGENT'] == 'Jetpack by WordPress.com' );

	return $is_backend || $is_cron || $is_frontend_checkout || $is_ipn || $is_jetpack;
} );
