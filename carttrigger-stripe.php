<?php
/**
 * Plugin Name: CartTrigger – Stripe
 * Description: Stripe Payment Element gateway for WooCommerce. Supports all payment methods enabled in your Stripe Dashboard.
 * Version:     1.0.0
 * Author:      Poletto 1976 S.L.U.
 * Author URI:  https://poletto.es
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'CTSTRIPE_VERSION', '1.0.0' );
define( 'CTSTRIPE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTSTRIPE_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }
    require_once CTSTRIPE_DIR . 'includes/class-gateway.php';
    require_once CTSTRIPE_DIR . 'includes/class-webhook.php';
    require_once CTSTRIPE_DIR . 'includes/class-api.php';

    add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
        $gateways[] = 'CTStripe_Gateway';
        return $gateways;
    } );

    ( new CTStripe_Webhook() )->init();

    // Render express checkout buttons above checkout form and above cart.
    $ece_html = '<div id="ctstripe-ece-global" style="margin-bottom:16px;"></div>';

    add_action( 'woocommerce_before_checkout_form', function () use ( $ece_html ) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        if ( isset( $gateways['ctstripe'] ) && $gateways['ctstripe']->is_available() ) {
            echo $ece_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static HTML, no user input.
        }
    }, 5 );

    add_action( 'woocommerce_before_cart', function () use ( $ece_html ) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        if ( isset( $gateways['ctstripe'] ) && $gateways['ctstripe']->is_available() ) {
            echo $ece_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static HTML, no user input.
        }
    }, 5 );
} );
