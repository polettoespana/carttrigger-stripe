<?php
defined( 'ABSPATH' ) || exit;

class CTStripe_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'ctstripe';
        $this->method_title       = 'CartTrigger Stripe';
        $this->method_description = 'Stripe Payment Element — mostra automaticamente tutti i metodi abilitati nel tuo dashboard Stripe.';
        $this->has_fields         = true;
        $this->supports           = [ 'products', 'refunds' ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Keep cart amount in sync when WC recalculates order review.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'add_cart_amount_fragment' ] );
    }

    public function init_form_fields(): void {
        $this->form_fields = [
            // ── Credenziali ──────────────────────────────────────────────────
            'enabled'            => [
                'title'   => 'Abilita',
                'type'    => 'checkbox',
                'label'   => 'Abilita CartTrigger Stripe',
                'default' => 'no',
            ],
            'publishable_key'    => [
                'title' => 'Publishable Key',
                'type'  => 'text',
            ],
            'secret_key'         => [
                'title'             => 'Secret Key',
                'type'              => 'password',
                'custom_attributes' => [ 'autocomplete' => 'new-password' ],
            ],
            'webhook_secret'     => [
                'title'             => 'Webhook Secret',
                'type'              => 'password',
                'description'       => 'Signing secret del webhook Stripe (whsec_…). Endpoint: ' . home_url( '/wc-api/ctstripe_webhook' ),
                'custom_attributes' => [ 'autocomplete' => 'new-password' ],
            ],
            'apple_pay_domain_verification' => [
                'title'       => 'Apple Pay – File di verifica dominio',
                'type'        => 'textarea',
                'description' => 'Incolla qui il contenuto del file fornito da Stripe (<em>Dashboard → Settings → Payment methods → Apple Pay → Add domain</em>). Verrà servito automaticamente su: <code>' . esc_html( home_url( '/.well-known/apple-developer-merchantid-domain-association' ) ) . '</code><br>Dopo aver salvato, vai su <strong>Impostazioni → Permalink</strong> e clicca Salva per aggiornare le regole di riscrittura.',
                'default'     => '',
                'css'         => 'height:80px;font-family:monospace;font-size:11px;',
            ],
            // ── Aspetto nel checkout ─────────────────────────────────────────
            'title'              => [
                'title'   => 'Titolo',
                'type'    => 'text',
                'default' => 'Paga con carta o altro metodo',
            ],
            'title_class'        => [
                'title'       => 'Classe CSS titolo',
                'type'        => 'text',
                'description' => 'Classe/e CSS applicate al label del metodo di pagamento nel checkout.',
                'default'     => 'font-grotesk',
            ],
            'description'        => [
                'title'   => 'Descrizione',
                'type'    => 'textarea',
                'default' => '',
            ],
            'description_class'  => [
                'title'       => 'Classe CSS descrizione',
                'type'        => 'text',
                'description' => 'Classe applicata al wrapper della descrizione nel box di pagamento. Lascia vuoto per nessuna classe.',
                'default'     => 'ctstripe-description',
            ],
            // ── Aspetto Payment Element ──────────────────────────────────────
            'appearance_theme'        => [
                'title'   => 'Tema',
                'type'    => 'select',
                'options' => [
                    'stripe' => 'Stripe (default)',
                    'flat'   => 'Flat (senza ombre)',
                    'none'   => 'None (solo variabili)',
                ],
                'default' => 'stripe',
            ],
            'appearance_color_primary'    => [
                'title'       => 'Colore primario',
                'type'        => 'text',
                'description' => 'Colore per focus, bordi attivi, pulsanti (es. <code>#0570de</code> o <code>rgb(5,112,222)</code>).',
                'default'     => '',
                'placeholder' => '#0570de',
            ],
            'appearance_color_background' => [
                'title'       => 'Colore sfondo input',
                'type'        => 'text',
                'description' => 'Sfondo dei campi di input.',
                'default'     => '',
                'placeholder' => '#ffffff',
            ],
            'appearance_color_text'       => [
                'title'       => 'Colore testo',
                'type'        => 'text',
                'description' => 'Testo principale nei campi.',
                'default'     => '',
                'placeholder' => '#30313d',
            ],
            'appearance_color_danger'     => [
                'title'       => 'Colore errori',
                'type'        => 'text',
                'description' => 'Colore per messaggi di errore e bordi non validi.',
                'default'     => '',
                'placeholder' => '#df1b41',
            ],
            'appearance_font_family'      => [
                'title'       => 'Font family',
                'type'        => 'text',
                'description' => 'Es. <code>Inter, system-ui, sans-serif</code>. Lascia vuoto per usare il font di sistema Stripe.',
                'default'     => '',
                'placeholder' => '',
            ],
            'appearance_border_radius'    => [
                'title'       => 'Border radius',
                'type'        => 'text',
                'description' => 'Es. <code>4px</code>, <code>8px</code>, <code>0px</code>.',
                'default'     => '4px',
                'placeholder' => '4px',
            ],
            'appearance_spacing_unit'     => [
                'title'       => 'Spacing unit',
                'type'        => 'text',
                'description' => 'Unità base per spaziatura interna (es. <code>4px</code>, <code>6px</code>). Lascia vuoto per default Stripe.',
                'default'     => '',
                'placeholder' => '4px',
            ],
            'appearance_font_size_base'   => [
                'title'       => 'Font size base',
                'type'        => 'text',
                'description' => 'Dimensione testo dei label e input (es. <code>14px</code>, <code>1rem</code>). Lascia vuoto per default Stripe.',
                'default'     => '',
                'placeholder' => '16px',
            ],
            'appearance_rules'            => [
                'title'       => 'CSS Rules (JSON)',
                'type'        => 'textarea',
                'description' => 'Regole CSS avanzate in formato JSON per targetare componenti interni Stripe.<br>
                    Selettori utili: <code>.AccordionItem</code>, <code>.Input</code>, <code>.Label</code>, <code>.Tab</code>.<br>
                    Esempio:<br><pre style="font-size:11px;background:#f6f7f7;padding:8px;border-radius:4px;overflow:auto;">{
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
}</pre>',
                'default'     => '',
                'css'         => 'height:140px;font-family:monospace;font-size:11px;',
            ],
            // ── Configurazione ───────────────────────────────────────────────
            'payment_method_config_id' => [
                'title'       => 'Payment Method Configuration ID',
                'type'        => 'text',
                'description' => 'ID del profilo Stripe (pmc_…). Lascia vuoto per usare il profilo default.',
                'default'     => '',
            ],
            'capture_mode'       => [
                'title'   => 'Cattura pagamento',
                'type'    => 'select',
                'options' => [
                    'automatic' => 'Automatica (immediata)',
                    'manual'    => 'Manuale (autorizzazione + cattura)',
                ],
                'default' => 'automatic',
            ],
            // ── Layout Payment Element ───────────────────────────────────────
            'pe_layout'              => [
                'title'   => 'Layout metodi di pagamento',
                'type'    => 'select',
                'options' => [
                    'accordion' => 'Accordion (lista verticale)',
                    'tabs'      => 'Tabs (riga orizzontale scorrevole)',
                ],
                'default' => 'accordion',
            ],
            // ── Express Checkout ─────────────────────────────────────────────
            'express_in_payment_box' => [
                'title'   => 'Pulsanti express nel box pagamento',
                'type'    => 'checkbox',
                'label'   => 'Mostra Apple Pay / Google Pay dentro il box metodo di pagamento',
                'default' => 'yes',
            ],
            'ece_button_height'  => [
                'title'             => 'Altezza pulsanti express (px)',
                'type'              => 'number',
                'description'       => 'Valore tra 40 e 55. Default: 44.',
                'default'           => '44',
                'custom_attributes' => [ 'min' => '40', 'max' => '55', 'step' => '1' ],
            ],
            'ece_columns'        => [
                'title'   => 'Layout pulsanti express',
                'type'    => 'select',
                'options' => [
                    '2' => 'Affiancati (2 colonne)',
                    '1' => 'In colonna (1 per riga)',
                ],
                'default' => '2',
            ],
            // ── Shortcode (solo per admin_options, non nel form WC standard) ─
            'shortcode_info'     => [
                'title'       => 'Shortcode pulsanti express',
                'type'        => 'title',
                'description' => '
                    <p>Usa lo shortcode <code>[ctstripe_express_checkout]</code> per posizionare i pulsanti Apple Pay / Google Pay ovunque nel tema o nelle pagine WordPress.</p>
                    <table class="widefat" style="border-collapse:collapse;">
                        <thead><tr>
                            <th>Attributo</th>
                            <th>Default</th>
                            <th>Descrizione</th>
                        </tr></thead>
                        <tbody>
                            <tr><td><code>class</code></td><td>—</td><td>Classe CSS aggiuntiva sul wrapper</td></tr>
                            <tr><td><code>style</code></td><td>—</td><td>Stile inline sul wrapper</td></tr>
                        </tbody>
                    </table>
                    <p style="margin-top:12px;"><strong>Esempi:</strong><br>
                        <code>[ctstripe_express_checkout]</code><br>
                        <code>[ctstripe_express_checkout class="my-buttons" style="margin-bottom:24px;"]</code>
                    </p>
                    <p>Via PHP (es. <code>functions.php</code>):<br>
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
        if ( ( $_GET['section'] ?? '' ) !== $this->id ) {
            return;
        }
        wp_enqueue_style( 'ctstripe-admin', CTSTRIPE_URL . 'assets/css/admin.css', [], CTSTRIPE_VERSION );
    }

    public function admin_options(): void {
        $groups = [
            [
                'title'  => 'Credenziali',
                'icon'   => 'dashicons-lock',
                'fields' => [ 'enabled', 'publishable_key', 'secret_key', 'webhook_secret', 'apple_pay_domain_verification' ],
            ],
            [
                'title'  => 'Aspetto nel checkout',
                'icon'   => 'dashicons-visibility',
                'fields' => [ 'title', 'title_class', 'description', 'description_class' ],
            ],
            [
                'title'  => 'Aspetto Payment Element',
                'icon'   => 'dashicons-art',
                'fields' => [ 'appearance_theme', 'appearance_color_primary', 'appearance_color_background', 'appearance_color_text', 'appearance_color_danger', 'appearance_font_family', 'appearance_font_size_base', 'appearance_border_radius', 'appearance_spacing_unit', 'appearance_rules' ],
            ],
            [
                'title'  => 'Configurazione pagamento',
                'icon'   => 'dashicons-admin-generic',
                'fields' => [ 'payment_method_config_id', 'capture_mode' ],
            ],
            [
                'title'  => 'Layout metodi di pagamento',
                'icon'   => 'dashicons-menu-alt',
                'fields' => [ 'pe_layout' ],
            ],
            [
                'title'  => 'Express Checkout',
                'icon'   => 'dashicons-smartphone',
                'fields' => [ 'express_in_payment_box', 'ece_button_height', 'ece_columns' ],
            ],
            [
                'title'     => 'Shortcode pulsanti express',
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
            'publishable_key' => $this->get_option( 'publishable_key' ),
            'return_url'      => home_url( '/ctstripe-return' ),
            'locale'          => $this->get_stripe_locale(),
            'gateway_id'      => $this->id,
            'cart_amount'     => $this->get_stripe_amount( (float) WC()->cart->get_total( 'raw' ), get_woocommerce_currency() ),
            'cart_currency'   => strtolower( get_woocommerce_currency() ),
            'pmc_id'          => $this->get_option( 'payment_method_config_id', '' ),
            'pe_layout'       => $this->get_option( 'pe_layout', 'accordion' ),
            'title_class'     => trim( $this->get_option( 'title_class', 'font-grotesk' ) ),
            'ece_height'      => max( 40, min( 55, (int) $this->get_option( 'ece_button_height', 44 ) ) ),
            'ece_columns'     => (int) $this->get_option( 'ece_columns', 2 ),
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
            echo '<div id="ctstripe-separator" style="display:none;text-align:center;margin:16px 0;color:#6b7280;font-size:0.85em;">— ' . esc_html__( 'o paga con', 'carttrigger-stripe' ) . ' —</div>';
        }
        echo '<div id="ctstripe-payment-element" style="min-height:40px;"></div>';
        echo '<div id="ctstripe-errors" style="color:#cc0000;margin-top:8px;"></div>';
    }

    // ── Payment processing ────────────────────────────────────────────────────

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        try {
            $api  = $this->get_api();
            $args = [
                'amount'         => $this->get_stripe_amount( (float) $order->get_total(), $order->get_currency() ),
                'currency'       => strtolower( $order->get_currency() ),
                'capture_method' => $this->get_option( 'capture_mode', 'automatic' ),
                'automatic_payment_methods' => [ 'enabled' => true ],
                'metadata'       => [ 'order_id' => $order->get_id() ],
            ];

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
            $order->update_status( 'pending', __( 'In attesa di conferma Stripe.', 'carttrigger-stripe' ) );
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

    public function add_cart_amount_fragment( array $fragments ): array {
        $fragments['ctstripe_cart_amount'] = $this->get_stripe_amount(
            (float) WC()->cart->get_total( 'raw' ),
            get_woocommerce_currency()
        );
        return $fragments;
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order     = wc_get_order( $order_id );
        $intent_id = $order->get_meta( '_ctstripe_intent_id' );

        if ( ! $intent_id ) {
            return new WP_Error( 'no_intent', 'Nessun PaymentIntent trovato per questo ordine.' );
        }

        try {
            $intent    = $this->get_api()->retrieve_payment_intent( $intent_id );
            $charge_id = $intent['latest_charge'] ?? '';

            if ( ! $charge_id ) {
                return new WP_Error( 'no_charge', 'Nessun addebito trovato.' );
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

    private function get_stripe_locale(): string {
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
