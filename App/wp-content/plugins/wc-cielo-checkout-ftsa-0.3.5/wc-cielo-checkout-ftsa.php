<?php
/**
 * Plugin Name: WC Cielo Checkout FTSA
 * Description: Integração com o Checkout Cielo (Link/Checkout API). Redireciona para a CheckoutUrl, recebe notificações e importa EDI (CIELO04) para registrar taxa e líquido oficiais no pedido.
 * Version: 0.3.5
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC tested up to: 9.1
 * WC requires at least: 6.0
 * Author: FTSA_Vitrine
 * License: GPLv2 or later
 * Text Domain: wc-cielo-checkout-ftsa
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WC_CIELO_FTSA_VERSION', '0.3.5' );
define( 'WC_CIELO_FTSA_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_CIELO_FTSA_URL',  untrailingslashit( plugin_dir_url( __FILE__ ) ) );

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    require_once WC_CIELO_FTSA_PATH . '/includes/class-wc-cielo-api.php';
    require_once WC_CIELO_FTSA_PATH . '/includes/class-wc-gateway-cielo.php';
    require_once WC_CIELO_FTSA_PATH . '/includes/class-wc-cielo-webhook.php';
    require_once WC_CIELO_FTSA_PATH . '/admin/class-wc-cielo-edi-admin.php';
    add_filter( 'woocommerce_payment_gateways', function( $methods ) {
        $methods[] = 'WC_Gateway_Cielo_FTSA';
        return $methods;
    } );
}, 5);
