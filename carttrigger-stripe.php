<?php
/**
 * Plugin Name: CartTrigger – Stripe
 * Description: Stripe Payment Element gateway for WooCommerce. Supports all payment methods enabled in your Stripe Dashboard.
 * Version:     1.5.0
 * Author:      Poletto 1976 S.L.U.
 * Author URI:  https://poletto.es
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'CTSTRIPE_VERSION', '1.5.0' );
define( 'CTSTRIPE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTSTRIPE_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, function () {
    add_rewrite_rule(
        '^\.well-known/apple-developer-merchantid-domain-association$',
        'index.php?ctstripe_apple_pay=1',
        'top'
    );
    flush_rewrite_rules();
} );

// Apple Pay domain verification endpoint.
add_action( 'init', function () {
    add_rewrite_rule(
        '^\.well-known/apple-developer-merchantid-domain-association$',
        'index.php?ctstripe_apple_pay=1',
        'top'
    );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'ctstripe_apple_pay';
    return $vars;
} );

add_action( 'template_redirect', function () {
    if ( ! get_query_var( 'ctstripe_apple_pay' ) ) {
        return;
    }
    if ( ! class_exists( 'WooCommerce' ) ) {
        status_header( 404 );
        exit;
    }
    $gateways = WC()->payment_gateways()->payment_gateways();
    $gateway  = $gateways['ctstripe'] ?? null;
    $content  = $gateway ? trim( $gateway->get_option( 'apple_pay_domain_verification', '' ) ) : '';
    if ( ! $content ) {
        status_header( 404 );
        exit;
    }
    header( 'Content-Type: text/plain; charset=utf-8' );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $content;
    exit;
} );

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

    // Security headers: CSP, CORS and Permissions-Policy for Stripe + hCaptcha.
    add_filter( 'wp_headers', function ( $headers ) {
        $stripe_domains = implode( ' ', [
            'https://*.stripe.com',
            'https://*.stripecdn.com',
            'https://*.stripe.network',
            'https://*.hcaptcha.com',
        ] );

        // Content-Security-Policy.
        if ( isset( $headers['Content-Security-Policy'] ) ) {
            $csp = $headers['Content-Security-Policy'];
            foreach ( [ 'frame-src', 'script-src', 'connect-src', 'img-src', 'style-src' ] as $directive ) {
                if ( strpos( $csp, $directive ) !== false ) {
                    $csp = preg_replace( '/(' . preg_quote( $directive, '/' ) . '[^;]*)/', '$1 ' . $stripe_domains, $csp );
                } else {
                    $csp .= '; ' . $directive . ' ' . $stripe_domains;
                }
            }
            $headers['Content-Security-Policy'] = $csp;
        }

        // CORS: allow Stripe CDN to make cross-origin requests to this server.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $allowed_origins = [ 'https://b.stripecdn.com', 'https://js.stripe.com', 'https://stripe.com' ];
        if ( in_array( $origin, $allowed_origins, true ) ) {
            $headers['Access-Control-Allow-Origin']  = $origin;
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization';
            $headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
            $headers['Vary']                         = 'Origin';
        }

        // Permissions-Policy: allow camera/microphone for hCaptcha (Stripe antifraud).
        $pp = $headers['Permissions-Policy'] ?? '';
        $hcaptcha_policy = 'camera=(self "https://newassets.hcaptcha.com"), microphone=(self "https://newassets.hcaptcha.com")';
        $headers['Permissions-Policy'] = $pp ? $pp . ', ' . $hcaptcha_policy : $hcaptcha_policy;

        return $headers;
    } );

    // Shortcode [ctstripe_express_checkout].
    add_shortcode( 'ctstripe_express_checkout', 'ctstripe_express_checkout_shortcode' );
} );

/**
 * Renders an Express Checkout Element container and enqueues scripts.
 *
 * Usage: [ctstripe_express_checkout class="my-class" style="margin-bottom:16px;"]
 */
function ctstripe_express_checkout_shortcode( $atts ): string {
    $gateways = WC()->payment_gateways()->payment_gateways();
    $gateway  = $gateways['ctstripe'] ?? null;

    if ( ! $gateway || ! $gateway->is_available() ) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'class' => '',
            'style' => '',
            'id'    => 'ctstripe-ece-' . wp_unique_id(),
        ],
        $atts,
        'ctstripe_express_checkout'
    );

    // Enqueue scripts — safe to call here, WP collects and outputs in footer.
    $gateway->enqueue_scripts();

    $class = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
    $style = $atts['style'] ? ' style="' . esc_attr( $atts['style'] ) . '"' : '';

    return '<div id="' . esc_attr( $atts['id'] ) . '"' . $class . $style . ' data-ctstripe-ece></div>';
}
