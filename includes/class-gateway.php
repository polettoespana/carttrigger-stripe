<?php
defined( 'ABSPATH' ) || exit;

class CTStripe_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'ctstripe';
        $this->method_title       = 'CartTrigger Stripe';
        $this->method_description = __( 'Stripe Payment Element — automatically displays all payment methods enabled in your Stripe Dashboard.', 'carttrigger-stripe' );
        $this->has_fields         = true;
        $this->supports           = [ 'products', 'refunds' ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Keep cart amount in sync on checkout (order review) and cart page (coupon/qty changes).
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'add_cart_amount_fragment' ] );
        add_filter( 'woocommerce_cart_fragments', [ $this, 'add_cart_amount_fragment' ] );
    }

    public function init_form_fields(): void {
        $this->form_fields = [
            // ── Credentials ──────────────────────────────────────────────────
            'enabled'            => [
                'title'   => __( 'Enable', 'carttrigger-stripe' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable CartTrigger Stripe', 'carttrigger-stripe' ),
                'default' => 'no',
            ],
            'publishable_key'    => [
                'title' => __( 'Publishable Key', 'carttrigger-stripe' ),
                'type'  => 'text',
            ],
            'secret_key'         => [
                'title'             => __( 'Secret Key', 'carttrigger-stripe' ),
                'type'              => 'password',
                'custom_attributes' => [ 'autocomplete' => 'new-password' ],
            ],
            'webhook_secret'     => [
                'title'             => __( 'Webhook Secret', 'carttrigger-stripe' ),
                'type'              => 'password',
                /* translators: %s: webhook endpoint URL */
                'description'       => sprintf( __( 'Stripe webhook signing secret (whsec_…). Endpoint: %s', 'carttrigger-stripe' ), home_url( '/wc-api/ctstripe_webhook' ) ),
                'custom_attributes' => [ 'autocomplete' => 'new-password' ],
            ],
            'apple_pay_domain_verification' => [
                'title'       => __( 'Apple Pay – Domain Verification File', 'carttrigger-stripe' ),
                'type'        => 'textarea',
                /* translators: 1: Stripe dashboard path, 2: domain verification URL */
                'description' => sprintf( __( 'Paste here the content of the file provided by Stripe (<em>%1$s</em>). It will be served automatically at: <code>%2$s</code><br>After saving, go to <strong>Settings → Permalinks</strong> and click Save to flush rewrite rules.', 'carttrigger-stripe' ), 'Dashboard → Settings → Payment methods → Apple Pay → Add domain', esc_html( home_url( '/.well-known/apple-developer-merchantid-domain-association' ) ) ),
                'default'     => '',
                'css'         => 'height:80px;font-family:monospace;font-size:11px;',
            ],
            // ── Checkout appearance ──────────────────────────────────────────
            'title'              => [
                'title'   => __( 'Title', 'carttrigger-stripe' ),
                'type'    => 'text',
                'default' => __( 'Pay with card or other method', 'carttrigger-stripe' ),
            ],
            'title_class'        => [
                'title'       => __( 'Title CSS class', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'CSS class(es) applied to the payment method label in checkout.', 'carttrigger-stripe' ),
                'default'     => 'font-grotesk',
            ],
            'description'        => [
                'title'   => __( 'Description', 'carttrigger-stripe' ),
                'type'    => 'textarea',
                'default' => '',
            ],
            'description_class'  => [
                'title'       => __( 'Description CSS class', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'CSS class applied to the description wrapper in the payment box. Leave empty for no class.', 'carttrigger-stripe' ),
                'default'     => 'ctstripe-description',
            ],
            // ── Payment Element appearance ────────────────────────────────────
            'appearance_theme'        => [
                'title'   => __( 'Theme', 'carttrigger-stripe' ),
                'type'    => 'select',
                'options' => [
                    'stripe' => __( 'Stripe (default)', 'carttrigger-stripe' ),
                    'flat'   => __( 'Flat (no shadows)', 'carttrigger-stripe' ),
                    'none'   => __( 'None (variables only)', 'carttrigger-stripe' ),
                ],
                'default' => 'stripe',
            ],
            'appearance_color_primary'    => [
                'title'       => __( 'Primary colour', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'Colour for focus, active borders and buttons (e.g. <code>#0570de</code> or <code>rgb(5,112,222)</code>).', 'carttrigger-stripe' ),
                'default'     => '',
                'placeholder' => '#0570de',
            ],
            'appearance_color_background' => [
                'title'       => __( 'Input background colour', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'Background of input fields.', 'carttrigger-stripe' ),
                'default'     => '',
                'placeholder' => '#ffffff',
            ],
            'appearance_color_text'       => [
                'title'       => __( 'Text colour', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'Main text colour inside fields.', 'carttrigger-stripe' ),
                'default'     => '',
                'placeholder' => '#30313d',
            ],
            'appearance_color_danger'     => [
                'title'       => __( 'Error colour', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'Colour for error messages and invalid field borders.', 'carttrigger-stripe' ),
                'default'     => '',
                'placeholder' => '#df1b41',
            ],
            'appearance_font_family'      => [
                'title'       => __( 'Font family', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'E.g. <code>Inter, system-ui, sans-serif</code>. Leave empty to use the Stripe system font.', 'carttrigger-stripe' ),
                'default'     => '',
                'placeholder' => '',
            ],
            'appearance_border_radius'    => [
                'title'       => __( 'Border radius', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'E.g. <code>4px</code>, <code>8px</code>, <code>0px</code>.', 'carttrigger-stripe' ),
                'default'     => '4px',
                'placeholder' => '4px',
            ],
            'appearance_spacing_unit'     => [
                'title'       => __( 'Spacing unit', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'Base spacing unit (e.g. <code>4px</code>, <code>6px</code>). Leave empty for Stripe default.', 'carttrigger-stripe' ),
                'default'     => '',
                'placeholder' => '4px',
            ],
            'appearance_font_size_base'   => [
                'title'       => __( 'Base font size', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'Label and input text size (e.g. <code>14px</code>, <code>1rem</code>). Leave empty for Stripe default.', 'carttrigger-stripe' ),
                'default'     => '',
                'placeholder' => '16px',
            ],
            'appearance_rules'            => [
                'title'       => __( 'CSS Rules (JSON)', 'carttrigger-stripe' ),
                'type'        => 'textarea',
                'description' => __( 'Advanced CSS rules in JSON format to target internal Stripe components.<br>
                    Useful selectors: <code>.AccordionItem</code>, <code>.Input</code>, <code>.Label</code>, <code>.Tab</code>.<br>
                    Example:<br><pre style="font-size:11px;background:#f6f7f7;padding:8px;border-radius:4px;overflow:auto;">{
  ".AccordionItem": {
    "border": "1px solid #e8eaed",
    "borderRadius": "6px",
    "boxShadow": "none"
  },
  ".Input": {
    "borderColor": "#c3c4c7",
    "boxShadow": "none"
  },
  ".Label": {
    "fontWeight": "600",
    "fontSize": "13px"
  }
}</pre>', 'carttrigger-stripe' ),
                'default'     => '',
                'css'         => 'height:140px;font-family:monospace;font-size:11px;',
            ],
            // ── Payment configuration ────────────────────────────────────────
            'payment_method_config_id' => [
                'title'       => __( 'Payment Method Configuration ID', 'carttrigger-stripe' ),
                'type'        => 'text',
                'description' => __( 'Stripe profile ID (pmc_…). Leave empty to use the default profile.', 'carttrigger-stripe' ),
                'default'     => '',
            ],
            'capture_mode'       => [
                'title'   => __( 'Payment capture', 'carttrigger-stripe' ),
                'type'    => 'select',
                'options' => [
                    'automatic' => __( 'Automatic (immediate)', 'carttrigger-stripe' ),
                    'manual'    => __( 'Manual (authorise + capture)', 'carttrigger-stripe' ),
                ],
                'default' => 'automatic',
            ],
            // ── Payment method layout ────────────────────────────────────────
            'pe_layout'              => [
                'title'   => __( 'Payment method layout', 'carttrigger-stripe' ),
                'type'    => 'select',
                'options' => [
                    'accordion' => __( 'Accordion (vertical list)', 'carttrigger-stripe' ),
                    'tabs'      => __( 'Tabs (scrollable horizontal row)', 'carttrigger-stripe' ),
                ],
                'default' => 'accordion',
            ],
            // ── Express Checkout ─────────────────────────────────────────────
            'express_in_payment_box' => [
                'title'   => __( 'Express buttons in payment box', 'carttrigger-stripe' ),
                'type'    => 'checkbox',
                'label'   => __( 'Show Apple Pay / Google Pay inside the payment method box', 'carttrigger-stripe' ),
                'default' => 'yes',
            ],
            'ece_button_height'  => [
                'title'             => __( 'Express button height (px)', 'carttrigger-stripe' ),
                'type'              => 'number',
                'description'       => __( 'Value between 40 and 55. Default: 44.', 'carttrigger-stripe' ),
                'default'           => '44',
                'custom_attributes' => [ 'min' => '40', 'max' => '55', 'step' => '1' ],
            ],
            'ece_columns'        => [
                'title'   => __( 'Express button layout', 'carttrigger-stripe' ),
                'type'    => 'select',
                'options' => [
                    '2' => __( 'Side by side (2 columns)', 'carttrigger-stripe' ),
                    '1' => __( 'Stacked (1 per row)', 'carttrigger-stripe' ),
                ],
                'default' => '2',
            ],
            'ece_max_rows'       => [
                'title'       => __( 'Express button max rows', 'carttrigger-stripe' ),
                'type'        => 'number',
                'description' => __( '<code>0</code> = no limit (shows all methods, no "More info" button). Values > 0 limit visible rows.', 'carttrigger-stripe' ),
                'default'     => '0',
                'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
            ],
            'ece_show_link'      => [
                'title'   => __( 'Stripe Link', 'carttrigger-stripe' ),
                'type'    => 'checkbox',
                'label'   => __( 'Show Stripe Link in Express Checkout', 'carttrigger-stripe' ),
                'default' => 'yes',
            ],
            'ece_show_paypal'    => [
                'title'   => __( 'PayPal', 'carttrigger-stripe' ),
                'type'    => 'checkbox',
                'label'   => __( 'Show PayPal in Express Checkout', 'carttrigger-stripe' ),
                'default' => 'yes',
            ],
            'ece_show_amazon_pay' => [
                'title'   => __( 'Amazon Pay', 'carttrigger-stripe' ),
                'type'    => 'checkbox',
                'label'   => __( 'Show Amazon Pay in Express Checkout', 'carttrigger-stripe' ),
                'default' => 'yes',
            ],
            'nif_invoice_threshold' => [
                'title'             => __( 'Required NIF threshold (€)', 'carttrigger-stripe' ),
                'type'              => 'number',
                'description'       => __( 'Amount in euros above which a NIF is required for express payments. Set to 0 to disable. Default: 400.', 'carttrigger-stripe' ),
                'default'           => '400',
                'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
            ],
            // ── Shortcode (admin_options only, not in the standard WC form) ──
            'shortcode_info'     => [
                'title'       => __( 'Express buttons shortcode', 'carttrigger-stripe' ),
                'type'        => 'title',
                'description' => '<p>' . __( 'Use the shortcode <code>[ctstripe_express_checkout]</code> to place the Apple Pay / Google Pay buttons anywhere in your theme or WordPress pages.', 'carttrigger-stripe' ) . '</p>
                    <table class="widefat" style="border-collapse:collapse;">
                        <thead><tr>
                            <th>' . __( 'Attribute', 'carttrigger-stripe' ) . '</th>
                            <th>' . __( 'Default', 'carttrigger-stripe' ) . '</th>
                            <th>' . __( 'Description', 'carttrigger-stripe' ) . '</th>
                        </tr></thead>
                        <tbody>
                            <tr><td><code>class</code></td><td>—</td><td>' . __( 'Additional CSS class on the wrapper', 'carttrigger-stripe' ) . '</td></tr>
                            <tr><td><code>style</code></td><td>—</td><td>' . __( 'Inline style on the wrapper', 'carttrigger-stripe' ) . '</td></tr>
                        </tbody>
                    </table>
                    <p style="margin-top:12px;"><strong>' . __( 'Examples:', 'carttrigger-stripe' ) . '</strong><br>
                        <code>[ctstripe_express_checkout]</code><br>
                        <code>[ctstripe_express_checkout class="my-buttons" style="margin-bottom:24px;"]</code>
                    </p>
                    <p>' . __( 'Via PHP (e.g. <code>functions.php</code>):', 'carttrigger-stripe' ) . '<br>
                        <code>echo do_shortcode(\'[ctstripe_express_checkout class="my-class"]\');</code>
                    </p>',
            ],
        ];
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function enqueue_admin_scripts( string $hook ): void {
        if ( 'woocommerce_page_wc-settings' !== $hook ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( sanitize_text_field( wp_unslash( $_GET['section'] ?? '' ) ) !== $this->id ) {
            return;
        }
        wp_enqueue_style( 'ctstripe-admin', CTSTRIPE_URL . 'assets/css/admin.css', [], CTSTRIPE_VERSION );
    }

    public function admin_options(): void {
        $groups = [
            [
                'title'  => __( 'Credentials', 'carttrigger-stripe' ),
                'icon'   => 'dashicons-lock',
                'fields' => [ 'enabled', 'publishable_key', 'secret_key', 'webhook_secret', 'apple_pay_domain_verification' ],
            ],
            [
                'title'  => __( 'Checkout appearance', 'carttrigger-stripe' ),
                'icon'   => 'dashicons-visibility',
                'fields' => [ 'title', 'title_class', 'description', 'description_class' ],
            ],
            [
                'title'  => __( 'Payment Element appearance', 'carttrigger-stripe' ),
                'icon'   => 'dashicons-art',
                'fields' => [ 'appearance_theme', 'appearance_color_primary', 'appearance_color_background', 'appearance_color_text', 'appearance_color_danger', 'appearance_font_family', 'appearance_font_size_base', 'appearance_border_radius', 'appearance_spacing_unit', 'appearance_rules' ],
            ],
            [
                'title'  => __( 'Payment configuration', 'carttrigger-stripe' ),
                'icon'   => 'dashicons-admin-generic',
                'fields' => [ 'payment_method_config_id', 'capture_mode' ],
            ],
            [
                'title'  => __( 'Payment method layout', 'carttrigger-stripe' ),
                'icon'   => 'dashicons-menu-alt',
                'fields' => [ 'pe_layout' ],
            ],
            [
                'title'  => __( 'Express Checkout', 'carttrigger-stripe' ),
                'icon'   => 'dashicons-smartphone',
                'fields' => [ 'express_in_payment_box', 'ece_button_height', 'ece_columns', 'ece_max_rows', 'ece_show_link', 'ece_show_paypal', 'ece_show_amazon_pay', 'nif_invoice_threshold' ],
            ],
            [
                'title'     => __( 'Express buttons shortcode', 'carttrigger-stripe' ),
                'icon'      => 'dashicons-shortcode',
                'shortcode' => true,
            ],
        ];

        echo '<div class="ctstripe-wrap">';
        echo '<div class="ctstripe-header">';
        echo '<h1>' . esc_html( $this->method_title ) . '</h1>';
        echo '<span class="ctstripe-version">v' . esc_html( CTSTRIPE_VERSION ) . '</span>';
        echo '</div>';

        foreach ( $groups as $group ) {
            $extra_class = ! empty( $group['shortcode'] ) ? ' ctstripe-card-shortcode' : '';
            echo '<div class="ctstripe-card' . esc_attr( $extra_class ) . '">';
            echo '<h2><span class="dashicons ' . esc_attr( $group['icon'] ) . '"></span>' . esc_html( $group['title'] ) . '</h2>';

            if ( ! empty( $group['shortcode'] ) ) {
                $field = $this->form_fields['shortcode_info'] ?? [];
                echo wp_kses_post( $field['description'] ?? '' );
            } else {
                $subset = [];
                foreach ( $group['fields'] as $key ) {
                    if ( isset( $this->form_fields[ $key ] ) ) {
                        $subset[ $key ] = $this->form_fields[ $key ];
                    }
                }
                echo '<table class="form-table">' . $this->generate_settings_html( $subset, false ) . '</table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            echo '</div>';
        }

        echo '</div>';
    }

    // ── Frontend scripts ──────────────────────────────────────────────────────

    public function enqueue_scripts(): void {
        if ( ! is_checkout() && ! is_cart() && ! is_page() ) {
            return;
        }

        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            [],
            '3',
            true
        );

        wp_enqueue_style(
            'ctstripe-checkout',
            CTSTRIPE_URL . 'assets/css/checkout.css',
            [],
            CTSTRIPE_VERSION
        );

        wp_enqueue_script(
            'ctstripe-checkout',
            CTSTRIPE_URL . 'assets/js/checkout.js',
            [ 'stripe-js', 'jquery' ],
            CTSTRIPE_VERSION,
            true
        );

        wp_localize_script( 'ctstripe-checkout', 'ctstripe', [
            'ajax_url'             => add_query_arg( 'wc-ajax', 'ctstripe_create_order', home_url( '/' ) ),
            'normalize_state_url'  => add_query_arg( 'wc-ajax', 'ctstripe_normalize_state', home_url( '/' ) ),
            'nonce'                => wp_create_nonce( 'ctstripe_create_order' ),
            'publishable_key' => $this->get_option( 'publishable_key' ),
            'return_url'      => home_url( '/ctstripe-return' ),
            'locale'          => $this->get_stripe_locale(),
            'gateway_id'      => $this->id,
            'cart_amount'     => $this->get_stripe_amount( (float) WC()->cart->get_total( 'raw' ), get_woocommerce_currency() ),
            'checkout_url'    => wc_get_checkout_url(),
            'nif_threshold'   => (int) round( floatval( $this->get_option( 'nif_invoice_threshold', 400 ) ) * 100 ),
            'i18n'            => [
                /* translators: %s: formatted price (e.g. "400,00 €") */
                'nif_required'   => sprintf( __( 'For purchases over %s, a Tax ID (NIF) is required.', 'carttrigger-stripe' ), wc_price( floatval( $this->get_option( 'nif_invoice_threshold', 400 ) ) ) ),
                'go_to_checkout' => __( 'Go to checkout to enter it and pay with your chosen method.', 'carttrigger-stripe' ),
                'terms_required' => __( 'Please read and accept the terms and conditions before paying.', 'carttrigger-stripe' ),
            ],
            'cart_currency'   => strtolower( get_woocommerce_currency() ),
            'pmc_id'          => $this->get_option( 'payment_method_config_id', '' ),
            'pe_layout'       => $this->get_option( 'pe_layout', 'accordion' ),
            'title_class'     => trim( $this->get_option( 'title_class', 'font-grotesk' ) ),
            'ece_height'      => max( 40, min( 55, (int) $this->get_option( 'ece_button_height', 44 ) ) ),
            'ece_columns'     => (int) $this->get_option( 'ece_columns', 2 ),
            'ece_max_rows'    => (int) $this->get_option( 'ece_max_rows', 0 ),
            'ece_payment_methods' => [
                'link'      => $this->get_option( 'ece_show_link', 'yes' ) === 'yes' ? 'auto' : 'never',
                'paypal'    => $this->get_option( 'ece_show_paypal', 'yes' ) === 'yes' ? 'auto' : 'never',
                'amazonPay' => $this->get_option( 'ece_show_amazon_pay', 'yes' ) === 'yes' ? 'auto' : 'never',
            ],
            'appearance'      => array_filter( [
                'theme'        => $this->get_option( 'appearance_theme', 'stripe' ),
                'colorPrimary'    => $this->get_option( 'appearance_color_primary', '' ),
                'colorBackground' => $this->get_option( 'appearance_color_background', '' ),
                'colorText'       => $this->get_option( 'appearance_color_text', '' ),
                'colorDanger'     => $this->get_option( 'appearance_color_danger', '' ),
                'fontFamily'      => $this->get_option( 'appearance_font_family', '' ),
                'fontSizeBase'    => $this->get_option( 'appearance_font_size_base', '' ),
                'borderRadius'    => $this->get_option( 'appearance_border_radius', '4px' ),
                'spacingUnit'     => $this->get_option( 'appearance_spacing_unit', '' ),
                'rules'           => $this->get_option( 'appearance_rules', '' ),
            ] ),
        ] );
    }

    // ── Checkout rendering ────────────────────────────────────────────────────

    public function payment_fields(): void {
        $desc_class  = trim( $this->get_option( 'description_class', 'ctstripe-description' ) );

        if ( $this->description ) {
            $attr = $desc_class ? ' class="' . esc_attr( $desc_class ) . '"' : '';
            echo '<p' . $attr . '>' . esc_html( $this->description ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        if ( 'yes' === $this->get_option( 'express_in_payment_box', 'yes' ) ) {
            echo '<div id="ctstripe-express-checkout-element" data-ctstripe-ece></div>';
            echo '<div id="ctstripe-separator" style="display:none;text-align:center;margin:16px 0;color:#6b7280;font-size:0.85em;">— ' . esc_html__( 'or pay with', 'carttrigger-stripe' ) . ' —</div>';
        }
        echo '<div id="ctstripe-payment-element" style="min-height:40px;"></div>';
        echo '<div id="ctstripe-errors" style="color:#cc0000;margin-top:8px;"></div>';
    }

    // ── Payment processing ────────────────────────────────────────────────────

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        // WooCommerce Blocks checkout: confirmation token set by context hook.
        $blocks_token = WC()->session ? WC()->session->get( 'ctstripe_blocks_confirmation_token' ) : '';
        if ( $blocks_token ) {
            WC()->session->set( 'ctstripe_blocks_confirmation_token', null );
            return $this->process_payment_blocks( $order, $blocks_token );
        }

        try {
            $api  = $this->get_api();
            $args = [
                'amount'         => $this->get_stripe_amount( (float) $order->get_total(), $order->get_currency() ),
                'currency'       => strtolower( $order->get_currency() ),
                'capture_method' => $this->get_option( 'capture_mode', 'automatic' ),
                'automatic_payment_methods' => [ 'enabled' => true ],
                'metadata'       => [ 'order_id' => $order->get_id() ],
            ];
            $billing_email = $order->get_billing_email();
            if ( $billing_email ) {
                $args['receipt_email'] = $billing_email;
            }

            $pmc_id = $this->get_option( 'payment_method_config_id' );
            if ( $pmc_id ) {
                $args['payment_method_configuration'] = $pmc_id;
                unset( $args['automatic_payment_methods'] );
            }

            $existing_intent = $order->get_meta( '_ctstripe_intent_id' );
            if ( $existing_intent ) {
                $intent = $api->update_payment_intent( $existing_intent, $args );
            } else {
                $intent = $api->create_payment_intent( $args );
            }

            $order->update_meta_data( '_ctstripe_intent_id', $intent['id'] );
            $order->update_status( 'pending', __( 'Awaiting Stripe confirmation.', 'carttrigger-stripe' ) );
            $order->save();

            WC()->cart->empty_cart();

            return [
                'result'                 => 'success',
                'redirect'               => '#ctstripe-confirm',
                'ctstripe_client_secret' => $intent['client_secret'],
            ];

        } catch ( \Exception $e ) {
            wc_add_notice( $e->getMessage(), 'error' );
            return [ 'result' => 'failure' ];
        }
    }

    // ── Blocks checkout payment processing ────────────────────────────────────

    private function process_payment_blocks( WC_Order $order, string $confirmation_token ): array {
        try {
            $api  = $this->get_api();
            $args = [
                'amount'             => $this->get_stripe_amount( (float) $order->get_total(), $order->get_currency() ),
                'currency'           => strtolower( $order->get_currency() ),
                'capture_method'     => $this->get_option( 'capture_mode', 'automatic' ),
                'confirmation_token' => $confirmation_token,
                'confirm'            => true,
                'return_url'         => home_url( '/ctstripe-return' ),
                'automatic_payment_methods' => [ 'enabled' => true ],
                'metadata'           => [ 'order_id' => $order->get_id() ],
            ];

            $billing_email = $order->get_billing_email();
            if ( $billing_email ) {
                $args['receipt_email'] = $billing_email;
            }

            $pmc = $this->get_option( 'payment_method_config_id' );
            if ( $pmc ) {
                $args['payment_method_configuration'] = $pmc;
                unset( $args['automatic_payment_methods'] );
            }

            $intent = $api->create_payment_intent( $args );
            $order->update_meta_data( '_ctstripe_intent_id', $intent['id'] );

            switch ( $intent['status'] ) {
                case 'succeeded':
                    $order->payment_complete( $intent['id'] );
                    $order->save();
                    WC()->cart->empty_cart();
                    return [
                        'result'   => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                    ];

                case 'processing':
                    $order->update_status( 'on-hold', __( 'Payment being processed (asynchronous method).', 'carttrigger-stripe' ) );
                    $order->save();
                    WC()->cart->empty_cart();
                    return [
                        'result'   => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                    ];

                case 'requires_action':
                    // Redirect-based 3DS or other next action.
                    $redirect = $intent['next_action']['redirect_to_url']['url']
                        ?? add_query_arg(
                            [
                                'payment_intent'               => $intent['id'],
                                'payment_intent_client_secret' => $intent['client_secret'],
                            ],
                            home_url( '/ctstripe-return' )
                        );
                    $order->update_status( 'pending', __( 'Awaiting payment authentication.', 'carttrigger-stripe' ) );
                    $order->save();
                    WC()->cart->empty_cart();
                    return [ 'result' => 'success', 'redirect' => $redirect ];

                default:
                    $order->update_status( 'pending', __( 'Awaiting Stripe confirmation.', 'carttrigger-stripe' ) );
                    $order->save();
                    WC()->cart->empty_cart();
                    return [
                        'result'   => 'success',
                        'redirect' => add_query_arg(
                            [
                                'payment_intent'               => $intent['id'],
                                'payment_intent_client_secret' => $intent['client_secret'],
                            ],
                            home_url( '/ctstripe-return' )
                        ),
                    ];
            }
        } catch ( \Exception $e ) {
            wc_add_notice( $e->getMessage(), 'error' );
            return [ 'result' => 'failure' ];
        }
    }

    public function add_cart_amount_fragment( array $fragments ): array {
        $fragments['ctstripe_cart_amount'] = $this->get_stripe_amount(
            (float) WC()->cart->get_total( 'raw' ),
            get_woocommerce_currency()
        );
        return $fragments;
    }

    public function ajax_create_order(): void {
        if ( ! check_ajax_referer( 'ctstripe_create_order', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'carttrigger-stripe' ) ] );
            return;
        }

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            wp_send_json_error( [ 'message' => __( 'Cart is empty.', 'carttrigger-stripe' ) ] );
            return;
        }

        WC()->cart->calculate_totals();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_ajax_referer
        $raw     = sanitize_text_field( wp_unslash( $_POST['billing'] ?? '{}' ) );
        $billing = json_decode( $raw, true ) ?: [];
        $address = $billing['address'] ?? [];

        $name_parts = explode( ' ', $billing['name'] ?? '', 2 );
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        $order = wc_create_order( [ 'customer_id' => get_current_user_id(), 'created_via' => 'checkout' ] );

        // Products — copy cart line totals directly so discounts are preserved.
        foreach ( WC()->cart->get_cart() as $item ) {
            $order->add_product(
                $item['data'],
                $item['quantity'],
                [
                    'variation'    => $item['variation'] ?? [],
                    'subtotal'     => $item['line_subtotal'],
                    'subtotal_tax' => $item['line_subtotal_tax'],
                    'total'        => $item['line_total'],
                    'total_tax'    => $item['line_tax'],
                    'taxes'        => [
                        'subtotal' => $item['line_tax_data']['subtotal'] ?? [],
                        'total'    => $item['line_tax_data']['total'] ?? [],
                    ],
                ]
            );
        }

        // Shipping.
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods', [] );
        foreach ( WC()->cart->get_shipping_packages() as $pkg_key => $package ) {
            $chosen = $chosen_methods[ $pkg_key ] ?? null;
            if ( $chosen && isset( $package['rates'][ $chosen ] ) ) {
                $rate      = $package['rates'][ $chosen ];
                $ship_item = new WC_Order_Item_Shipping();
                $ship_item->set_props( [
                    'method_title' => $rate->label,
                    'method_id'    => $rate->method_id,
                    'instance_id'  => $rate->instance_id,
                    'cost'         => wc_format_decimal( $rate->cost ),
                    'taxes'        => [ 'total' => $rate->taxes ],
                ] );
                $order->add_item( $ship_item );
            }
        }

        // Coupons — add as items with pre-computed amounts from cart (not re-evaluated).
        $coupon_discounts     = WC()->cart->get_coupon_discount_totals();
        $coupon_discount_taxes = WC()->cart->get_coupon_discount_tax_totals();
        foreach ( WC()->cart->get_applied_coupons() as $code ) {
            $code        = wc_format_coupon_code( $code );
            $coupon_item = new WC_Order_Item_Coupon();
            $coupon_item->set_props( [
                'code'         => $code,
                'discount'     => $coupon_discounts[ $code ] ?? 0,
                'discount_tax' => $coupon_discount_taxes[ $code ] ?? 0,
            ] );
            $order->add_item( $coupon_item );
        }

        // Billing address (from Apple Pay / Google Pay).
        $order->set_billing_first_name( sanitize_text_field( $first_name ) );
        $order->set_billing_last_name( sanitize_text_field( $last_name ) );
        $order->set_billing_email( sanitize_email( $billing['email'] ?? '' ) );
        $order->set_billing_phone( sanitize_text_field( $billing['phone'] ?? '' ) );
        $order->set_billing_address_1( sanitize_text_field( $address['line1'] ?? '' ) );
        $order->set_billing_address_2( sanitize_text_field( $address['line2'] ?? '' ) );
        $order->set_billing_city( sanitize_text_field( $address['city'] ?? '' ) );
        $order->set_billing_postcode( sanitize_text_field( $address['postal_code'] ?? '' ) );
        $billing_country = sanitize_text_field( $address['country'] ?? '' );
        $billing_state   = $this->normalize_state( $billing_country, sanitize_text_field( $address['state'] ?? '' ) );
        $order->set_billing_country( $billing_country );
        $order->set_billing_state( $billing_state );

        // Mirror billing → shipping.
        $order->set_shipping_first_name( sanitize_text_field( $first_name ) );
        $order->set_shipping_last_name( sanitize_text_field( $last_name ) );
        $order->set_shipping_address_1( sanitize_text_field( $address['line1'] ?? '' ) );
        $order->set_shipping_address_2( sanitize_text_field( $address['line2'] ?? '' ) );
        $order->set_shipping_city( sanitize_text_field( $address['city'] ?? '' ) );
        $order->set_shipping_postcode( sanitize_text_field( $address['postal_code'] ?? '' ) );
        $order->set_shipping_country( $billing_country );
        $order->set_shipping_state( $billing_state );

        $order->set_payment_method( $this->id );
        $order->set_payment_method_title( $this->title );
        $order->calculate_totals();
        $order->update_status( 'pending', __( 'Awaiting Stripe confirmation (Express Checkout).', 'carttrigger-stripe' ) );
        $order->save();

        try {
            $args = [
                'amount'         => $this->get_stripe_amount( (float) $order->get_total(), $order->get_currency() ),
                'currency'       => strtolower( $order->get_currency() ),
                'capture_method' => $this->get_option( 'capture_mode', 'automatic' ),
                'automatic_payment_methods' => [ 'enabled' => true ],
                'metadata'       => [ 'order_id' => $order->get_id() ],
            ];
            $ece_email = $order->get_billing_email();
            if ( $ece_email ) {
                $args['receipt_email'] = $ece_email;
            }

            $pmc = $this->get_option( 'payment_method_config_id' );
            if ( $pmc ) {
                $args['payment_method_configuration'] = $pmc;
                unset( $args['automatic_payment_methods'] );
            }

            $intent = $this->get_api()->create_payment_intent( $args );
            $order->update_meta_data( '_ctstripe_intent_id', $intent['id'] );
            $order->save();

            WC()->cart->empty_cart();

            wp_send_json_success( [ 'client_secret' => $intent['client_secret'] ] );

        } catch ( \Exception $e ) {
            /* translators: %s: Stripe error message */
            $order->update_status( 'failed', sprintf( __( 'Stripe error: %s', 'carttrigger-stripe' ), $e->getMessage() ) );
            $order->save();
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    private function normalize_state( string $country, string $state ): string {
        if ( ! $country || ! $state ) {
            return $state;
        }
        $wc_states = WC()->countries->get_states( $country );
        if ( ! is_array( $wc_states ) ) {
            return $state;
        }
        // Already a valid code.
        if ( isset( $wc_states[ strtoupper( $state ) ] ) ) {
            return strtoupper( $state );
        }
        // Case-insensitive match against state names.
        foreach ( $wc_states as $code => $name ) {
            if ( preg_match( '/' . preg_quote( $name, '/' ) . '/i', $state ) ||
                 preg_match( '/' . preg_quote( $state, '/' ) . '/i', $name ) ) {
                return $code;
            }
        }
        return $state;
    }

    public function ajax_normalize_state(): void {
        if ( ! check_ajax_referer( 'ctstripe_create_order', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'carttrigger-stripe' ) ] );
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above
        $country = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
        $state   = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
        // phpcs:enable
        wp_send_json_success( [ 'state' => $this->normalize_state( $country, $state ) ] );
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order     = wc_get_order( $order_id );
        $intent_id = $order->get_meta( '_ctstripe_intent_id' );

        if ( ! $intent_id ) {
            return new WP_Error( 'no_intent', __( 'No PaymentIntent found for this order.', 'carttrigger-stripe' ) );
        }

        try {
            $intent    = $this->get_api()->retrieve_payment_intent( $intent_id );
            $charge_id = $intent['latest_charge'] ?? '';

            if ( ! $charge_id ) {
                return new WP_Error( 'no_charge', __( 'No charge found.', 'carttrigger-stripe' ) );
            }

            $refund_args = [ 'charge' => $charge_id ];
            if ( $amount ) {
                $refund_args['amount'] = $this->get_stripe_amount( (float) $amount, $order->get_currency() );
            }
            if ( $reason ) {
                $refund_args['reason']   = 'other';
                $refund_args['metadata'] = [ 'reason' => $reason ];
            }

            $this->get_api()->create_refund( $refund_args );
            return true;

        } catch ( \Exception $e ) {
            return new WP_Error( 'refund_failed', $e->getMessage() );
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    public function get_api(): CTStripe_API {
        return new CTStripe_API( $this->get_option( 'secret_key' ) );
    }

    public function get_stripe_amount( float $amount, string $currency ): int {
        $zero_decimal = [ 'bif','clp','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf' ];
        if ( in_array( strtolower( $currency ), $zero_decimal, true ) ) {
            return (int) $amount;
        }
        return (int) round( $amount * 100 );
    }

    public function get_stripe_locale(): string {
        $locale = get_locale();
        $map    = [
            'es_ES' => 'es',
            'es_AR' => 'es',
            'it_IT' => 'it',
            'pt_PT' => 'pt',
            'pt_BR' => 'pt-BR',
            'fr_FR' => 'fr',
            'de_DE' => 'de',
            'en_US' => 'en',
            'en_GB' => 'en-GB',
        ];
        return $map[ $locale ] ?? 'auto';
    }
}
