<?php
defined( 'ABSPATH' ) || exit;

class CTStripe_Webhook {

    public function init(): void {
        add_action( 'woocommerce_api_ctstripe_webhook', [ $this, 'handle' ] );
        add_action( 'init', [ $this, 'register_return_endpoint' ] );
        add_action( 'template_redirect', [ $this, 'handle_return' ] );
    }

    public function handle(): void {
        $payload    = file_get_contents( 'php://input' );
        $sig_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '' ) );

        $gateway = $this->get_gateway();
        if ( ! $gateway ) {
            status_header( 503 );
            exit( 'Gateway not configured.' );
        }

        $webhook_secret = $gateway->get_option( 'webhook_secret' );
        if ( ! $webhook_secret ) {
            status_header( 400 );
            exit( 'Webhook secret not configured.' );
        }

        try {
            $event = $gateway->get_api()->construct_webhook_event( $payload, $sig_header, $webhook_secret );
        } catch ( \Exception $e ) {
            status_header( 400 );
            exit( 'Webhook error: ' . esc_html( $e->getMessage() ) );
        }

        $intent = $event['data']['object'] ?? [];

        switch ( $event['type'] ) {
            case 'payment_intent.succeeded':
                $this->on_payment_succeeded( $intent );
                break;

            case 'payment_intent.payment_failed':
                $this->on_payment_failed( $intent );
                break;

            case 'payment_intent.canceled':
                $this->on_payment_canceled( $intent );
                break;

            case 'charge.refunded':
                // handled by process_refund() directly.
                break;
        }

        status_header( 200 );
        exit( 'OK' );
    }

    private function on_payment_succeeded( array $intent ): void {
        $order = $this->get_order_from_intent( $intent );
        if ( ! $order || $order->is_paid() ) {
            return;
        }

        $order->payment_complete( $intent['id'] );
        $order->add_order_note( sprintf(
            'Pagamento completato via Stripe. PaymentIntent: %s. Metodo: %s.',
            esc_html( $intent['id'] ),
            esc_html( $intent['payment_method_types'][0] ?? 'n/a' )
        ) );
    }

    private function on_payment_failed( array $intent ): void {
        $order = $this->get_order_from_intent( $intent );
        if ( ! $order ) {
            return;
        }

        $error = $intent['last_payment_error']['message'] ?? 'Pagamento fallito.';
        $order->update_status( 'failed', 'Stripe: ' . esc_html( $error ) );
    }

    private function on_payment_canceled( array $intent ): void {
        $order = $this->get_order_from_intent( $intent );
        if ( ! $order ) {
            return;
        }
        $order->update_status( 'cancelled', 'PaymentIntent annullato su Stripe.' );
    }

    private function get_order_from_intent( array $intent ): ?WC_Order {
        $order_id = $intent['metadata']['order_id'] ?? 0;
        if ( $order_id ) {
            $order = wc_get_order( (int) $order_id );
            if ( $order ) {
                return $order;
            }
        }

        // Fallback: search by intent id stored in meta.
        $orders = wc_get_orders( [
            'meta_key'   => '_ctstripe_intent_id',
            'meta_value' => $intent['id'],
            'limit'      => 1,
        ] );

        return $orders[0] ?? null;
    }

    public function register_return_endpoint(): void {
        add_rewrite_rule( '^ctstripe-return/?$', 'index.php?ctstripe_return=1', 'top' );
        add_rewrite_tag( '%ctstripe_return%', '1' );
    }

    public function handle_return(): void {
        if ( ! get_query_var( 'ctstripe_return' ) ) {
            return;
        }

        $intent_id     = sanitize_text_field( wp_unslash( $_GET['payment_intent'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_GET['payment_intent_client_secret'] ?? '' ) );

        if ( ! $intent_id ) {
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        try {
            $gateway = $this->get_gateway();
            $intent  = $gateway->get_api()->retrieve_payment_intent( $intent_id );
            $order   = $this->get_order_from_intent( $intent );

            if ( ! $order ) {
                wc_add_notice( 'Ordine non trovato.', 'error' );
                wp_safe_redirect( wc_get_checkout_url() );
                exit;
            }

            switch ( $intent['status'] ) {
                case 'succeeded':
                    if ( ! $order->is_paid() ) {
                        $order->payment_complete( $intent_id );
                    }
                    wp_safe_redirect( $order->get_checkout_order_received_url() );
                    exit;

                case 'processing':
                    $order->update_status( 'on-hold', 'Pagamento in elaborazione (metodo asincrono).' );
                    wp_safe_redirect( $order->get_checkout_order_received_url() );
                    exit;

                case 'requires_payment_method':
                    $order->update_status( 'failed', 'Pagamento non completato.' );
                    wc_add_notice( 'Il pagamento non è andato a buon fine. Riprova.', 'error' );
                    wp_safe_redirect( wc_get_checkout_url() );
                    exit;

                default:
                    wp_safe_redirect( $order->get_checkout_order_received_url() );
                    exit;
            }
        } catch ( \Exception $e ) {
            wc_add_notice( 'Errore nella verifica del pagamento.', 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }
    }

    private function get_gateway(): ?CTStripe_Gateway {
        $gateways = WC()->payment_gateways()->payment_gateways();
        return $gateways['ctstripe'] ?? null;
    }
}
