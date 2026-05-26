<?php
/**
 * Plugin Name: CartTrigger – Stripe
 * Description: Stripe Payment Element gateway for WooCommerce. Supports all payment methods enabled in your Stripe Dashboard.
 * Version:     1.8.0
 * Author:      Poletto 1976 S.L.U.
 * Author URI:  https://poletto.es
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: carttrigger-stripe
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CTSTRIPE_VERSION', '1.8.0' );
define( 'CTSTRIPE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTSTRIPE_URL', plugin_dir_url( __FILE__ ) );

add_action( 'init', function () {
    load_plugin_textdomain( 'carttrigger-stripe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

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

$ctstripe_plugin_file = plugin_basename( __FILE__ );

add_filter( 'plugin_action_links_' . $ctstripe_plugin_file, function ( $links ) {
    $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ctstripe' );
    array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'carttrigger-stripe' ) . '</a>' );
    return $links;
} );

add_filter( 'plugin_row_meta', function ( $links, $file ) use ( $ctstripe_plugin_file ) {
    if ( $file !== $ctstripe_plugin_file ) {
        return $links;
    }
    $links[] = '<a href="https://poletto.es/nuestros-servicios/eficiencia/ct-stripe/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'carttrigger-stripe' ) . '</a>';
    return $links;
}, 10, 2 );

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

    // AJAX handler for ECE order creation — registered here (not in the gateway
    // constructor) so it fires even when WC hasn't instantiated gateways yet.
    // Uses ?wc-ajax= to bypass admin-ajax.php and any WAF rules blocking it.
    $ctstripe_create_order_handler = function () {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = $gateways['ctstripe'] ?? null;
        if ( $gateway ) {
            $gateway->ajax_create_order();
        } else {
            wp_send_json_error( [ 'message' => __( 'Gateway not available.', 'carttrigger-stripe' ) ] );
        }
    };
    add_action( 'wc_ajax_ctstripe_create_order', $ctstripe_create_order_handler );
    // Fallback via admin-ajax.php (e.g. if WC AJAX endpoint unavailable).
    add_action( 'wp_ajax_ctstripe_create_order', $ctstripe_create_order_handler );
    add_action( 'wp_ajax_nopriv_ctstripe_create_order', $ctstripe_create_order_handler );

    // State normalization endpoint — converts Apple Pay full state names to WC codes.
    $ctstripe_normalize_state_handler = function () {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = $gateways['ctstripe'] ?? null;
        if ( $gateway ) {
            $gateway->ajax_normalize_state();
        } else {
            wp_send_json_error( [ 'message' => __( 'Gateway not available.', 'carttrigger-stripe' ) ] );
        }
    };
    add_action( 'wc_ajax_ctstripe_normalize_state', $ctstripe_normalize_state_handler );
    add_action( 'wp_ajax_ctstripe_normalize_state', $ctstripe_normalize_state_handler );
    add_action( 'wp_ajax_nopriv_ctstripe_normalize_state', $ctstripe_normalize_state_handler );

    ( new CTStripe_Webhook() )->init();

    // WooCommerce Blocks integration — purely additive, classic checkout unaffected.
    add_action( 'woocommerce_blocks_loaded', function () {
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }
        require_once CTSTRIPE_DIR . 'includes/class-blocks.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function ( $registry ) {
                $registry->register( new CTStripe_Blocks() );
            }
        );
    } );

    // Extract confirmation token from blocks checkout context before process_payment() runs.
    add_action( 'woocommerce_rest_checkout_process_payment_with_context', function ( $context ) {
        if ( ( $context->payment_method ?? '' ) !== 'ctstripe' ) {
            return;
        }
        $token = sanitize_text_field( $context->payment_data['ctstripe_confirmation_token'] ?? '' );
        if ( $token ) {
            WC()->session->set( 'ctstripe_blocks_confirmation_token', $token );
        }
    }, 10, 1 );

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
            'class'         => '',
            'style'         => '',
            'id'            => 'ctstripe-ece-' . wp_unique_id(),
            'notice'        => __( 'The details saved in your selected express payment method will be used (name, address and email).', 'carttrigger-stripe' ),
            'checkout_link' => __( 'For other details, go to checkout.', 'carttrigger-stripe' ),
        ],
        $atts,
        'ctstripe_express_checkout'
    );

    // Enqueue scripts — safe to call here, WP collects and outputs in footer.
    $gateway->enqueue_scripts();

    $class = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
    $style = $atts['style'] ? ' style="' . esc_attr( $atts['style'] ) . '"' : '';

    // Wrap container + optional notice in a single element that can be hidden together.
    $html  = '<div data-ctstripe-ece-wrapper' . $class . $style . '>';
    $html .= '<div id="' . esc_attr( $atts['id'] ) . '" data-ctstripe-ece></div>';

    if ( ! is_checkout() && ( $atts['notice'] || $atts['checkout_link'] ) ) {
        $html .= '<p class="ctstripe-ece-notice">';
        if ( $atts['notice'] ) {
            $html .= esc_html( $atts['notice'] );
        }
        if ( $atts['checkout_link'] ) {
            $html .= ' <a href="' . esc_url( wc_get_checkout_url() ) . '" class="ctstripe-ece-checkout-link">' . esc_html( $atts['checkout_link'] ) . '</a>';
        }
        $html .= '</p>';
    }

    $html .= '</div>';

    return $html;
}
