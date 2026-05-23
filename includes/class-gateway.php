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

        // Keep cart amount in sync when WC recalculates order review.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'add_cart_amount_fragment' ] );
    }

    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled'            => [
                'title'   => 'Abilita',
                'type'    => 'checkbox',
                'label'   => 'Abilita CartTrigger Stripe',
                'default' => 'no',
            ],
            'title'              => [
                'title'   => 'Titolo',
                'type'    => 'text',
                'default' => 'Paga con carta o altro metodo',
            ],
            'description'        => [
                'title'   => 'Descrizione',
                'type'    => 'textarea',
                'default' => '',
            ],
            'publishable_key'    => [
                'title' => 'Publishable Key',
                'type'  => 'text',
            ],
            'secret_key'         => [
                'title' => 'Secret Key',
                'type'  => 'password',
            ],
            'webhook_secret'     => [
                'title'       => 'Webhook Secret',
                'type'        => 'password',
                'description' => 'Signing secret del webhook Stripe (whsec_…). Endpoint: ' . home_url( '/wc-api/ctstripe_webhook' ),
            ],
            'apple_pay_domain_verification' => [
                'title'       => 'Apple Pay – File di verifica dominio',
                'type'        => 'textarea',
                'description' => 'Incolla qui il contenuto del file fornito da Stripe (<em>Dashboard → Settings → Payment methods → Apple Pay → Add domain</em>). Verrà servito automaticamente su: <code>' . esc_html( home_url( '/.well-known/apple-developer-merchantid-domain-association' ) ) . '</code><br>Dopo aver salvato, vai su <strong>Impostazioni → Permalink</strong> e clicca Salva per aggiornare le regole di riscrittura.',
                'default'     => '',
                'css'         => 'height:80px;font-family:monospace;font-size:11px;',
            ],
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
            'shortcode_info'     => [
                'title'       => 'Shortcode pulsanti express',
                'type'        => 'title',
                'description' => '
                    <p>Usa lo shortcode <code>[ctstripe_express_checkout]</code> per posizionare i pulsanti Apple Pay / Google Pay ovunque nel tema o nelle pagine WordPress.</p>
                    <table class="widefat" style="margin-top:8px;border-collapse:collapse;">
                        <thead><tr>
                            <th style="padding:6px 10px;border:1px solid #ddd;background:#f9f9f9;">Attributo</th>
                            <th style="padding:6px 10px;border:1px solid #ddd;background:#f9f9f9;">Default</th>
                            <th style="padding:6px 10px;border:1px solid #ddd;background:#f9f9f9;">Descrizione</th>
                        </tr></thead>
                        <tbody>
                            <tr><td style="padding:6px 10px;border:1px solid #ddd;"><code>class</code></td><td style="padding:6px 10px;border:1px solid #ddd;">—</td><td style="padding:6px 10px;border:1px solid #ddd;">Classe CSS aggiuntiva sul wrapper</td></tr>
                            <tr><td style="padding:6px 10px;border:1px solid #ddd;"><code>style</code></td><td style="padding:6px 10px;border:1px solid #ddd;">—</td><td style="padding:6px 10px;border:1px solid #ddd;">Stile inline sul wrapper</td></tr>
                        </tbody>
                    </table>
                    <p style="margin-top:8px;"><strong>Esempi:</strong><br>
                        <code>[ctstripe_express_checkout]</code><br>
                        <code>[ctstripe_express_checkout class="my-buttons" style="margin-bottom:24px;"]</code>
                    </p>
                    <p>Per inserirlo via PHP (es. <code>functions.php</code>):<br>
                        <code>echo do_shortcode(\'[ctstripe_express_checkout class="my-class"]\');</code>
                    </p>',
            ],
            'express_in_payment_box' => [
                'title'   => 'Pulsanti express nel box pagamento',
                'type'    => 'checkbox',
                'label'   => 'Mostra Apple Pay / Google Pay dentro il box metodo di pagamento',
                'default' => 'yes',
            ],
        ];
    }

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
        ] );
    }

    public function payment_fields(): void {
        if ( $this->description ) {
            echo '<p>' . esc_html( $this->description ) . '</p>';
        }
        if ( 'yes' === $this->get_option( 'express_in_payment_box', 'yes' ) ) {
            echo '<div id="ctstripe-express-checkout-element" data-ctstripe-ece></div>';
            echo '<div id="ctstripe-separator" style="display:none;text-align:center;margin:16px 0;color:#6b7280;font-size:0.85em;">— ' . esc_html__( 'o paga con', 'carttrigger-stripe' ) . ' —</div>';
        }
        echo '<div id="ctstripe-payment-element" style="min-height:40px;"></div>';
        echo '<div id="ctstripe-errors" style="color:#cc0000;margin-top:8px;"></div>';
    }

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        try {
            $api  = $this->get_api();
            $args = [
                'amount'         => $this->get_stripe_amount( (float) $order->get_total(), $order->get_currency() ),
                'currency'       => strtolower( $order->get_currency() ),
                'capture_method' => $this->get_option( 'capture_mode', 'automatic' ),
                'automatic_payment_methods' => [ 'enabled' => 'true' ],
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
