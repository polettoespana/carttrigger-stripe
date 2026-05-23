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
        add_action( 'woocommerce_api_ctstripe_intent', [ $this, 'handle_create_intent' ] );
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
        ];
    }

    public function enqueue_scripts(): void {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            [],
            null,
            true
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
            'ajax_url'        => WC()->api_request_url( 'ctstripe_intent' ),
            'nonce'           => wp_create_nonce( 'ctstripe_intent' ),
            'return_url'      => home_url( '/ctstripe-return' ),
            'locale'          => $this->get_stripe_locale(),
            'gateway_id'      => $this->id,
        ] );
    }

    public function payment_fields(): void {
        if ( $this->description ) {
            echo '<p>' . esc_html( $this->description ) . '</p>';
        }
        echo '<div id="ctstripe-payment-element" style="min-height:40px;"></div>';
        echo '<div id="ctstripe-errors" style="color:#cc0000;margin-top:8px;"></div>';
    }

    public function handle_create_intent(): void {
        check_ajax_referer( 'ctstripe_intent', 'nonce' );

        $order_id = absint( sanitize_text_field( wp_unslash( $_POST['order_id'] ?? '' ) ) );
        $order    = $order_id ? wc_get_order( $order_id ) : null;

        // If no confirmed order yet, use cart totals.
        $amount   = $order ? $this->get_stripe_amount( $order->get_total(), $order->get_currency() )
                           : $this->get_stripe_amount( WC()->cart->get_total( 'raw' ), get_woocommerce_currency() );
        $currency = strtolower( $order ? $order->get_currency() : get_woocommerce_currency() );

        try {
            $api  = $this->get_api();
            $args = [
                'amount'               => $amount,
                'currency'             => $currency,
                'capture_method'       => $this->get_option( 'capture_mode', 'automatic' ),
                'automatic_payment_methods' => [ 'enabled' => 'true' ],
            ];

            $pmc_id = $this->get_option( 'payment_method_config_id' );
            if ( $pmc_id ) {
                $args['payment_method_configuration'] = $pmc_id;
                unset( $args['automatic_payment_methods'] );
            }

            if ( $order ) {
                $args['metadata'] = [ 'order_id' => $order->get_id() ];
                $existing_intent  = $order->get_meta( '_ctstripe_intent_id' );

                if ( $existing_intent ) {
                    $intent = $api->update_payment_intent( $existing_intent, $args );
                } else {
                    $intent = $api->create_payment_intent( $args );
                    $order->update_meta_data( '_ctstripe_intent_id', $intent['id'] );
                    $order->save();
                }
            } else {
                $intent = $api->create_payment_intent( $args );
            }

            wp_send_json_success( [
                'client_secret' => $intent['client_secret'],
                'intent_id'     => $intent['id'],
            ] );

        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public function process_payment( $order_id ): array {
        $order     = wc_get_order( $order_id );
        $intent_id = sanitize_text_field( wp_unslash( $_POST['ctstripe_intent_id'] ?? '' ) );

        if ( $intent_id ) {
            $order->update_meta_data( '_ctstripe_intent_id', $intent_id );
            $order->save();
        }

        // JS will call stripe.confirmPayment() and redirect — mark as pending.
        $order->update_status( 'pending', __( 'In attesa di conferma Stripe.', 'carttrigger-stripe' ) );
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => false, // JS handles the redirect via stripe.confirmPayment().
        ];
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
                $refund_args['amount'] = $this->get_stripe_amount( $amount, $order->get_currency() );
            }
            if ( $reason ) {
                $refund_args['reason'] = 'other';
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

    private function get_stripe_amount( float $amount, string $currency ): int {
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
