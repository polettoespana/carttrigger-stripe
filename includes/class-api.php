<?php
defined( 'ABSPATH' ) || exit;

class CTStripe_API {

    private string $secret_key;

    public function __construct( string $secret_key ) {
        $this->secret_key = $secret_key;
    }

    public function create_payment_intent( array $args ): array {
        return $this->request( 'POST', 'payment_intents', $args );
    }

    public function update_payment_intent( string $id, array $args ): array {
        return $this->request( 'POST', "payment_intents/{$id}", $args );
    }

    public function create_refund( array $args ): array {
        return $this->request( 'POST', 'refunds', $args );
    }

    public function retrieve_payment_intent( string $id ): array {
        return $this->request( 'GET', "payment_intents/{$id}" );
    }

    public function construct_webhook_event( string $payload, string $sig_header, string $secret ): array {
        // Stripe webhook signature verification (manual, no SDK).
        $parts     = explode( ',', $sig_header );
        $timestamp = '';
        $signatures = [];

        foreach ( $parts as $part ) {
            if ( substr( $part, 0, 2 ) === 't=' ) {
                $timestamp = substr( $part, 2 );
            } elseif ( substr( $part, 0, 3 ) === 'v1=' ) {
                $signatures[] = substr( $part, 3 );
            }
        }

        if ( ! $timestamp || ! $signatures ) {
            throw new \Exception( 'Invalid Stripe-Signature header.' );
        }

        $tolerance = 300;
        if ( abs( time() - (int) $timestamp ) > $tolerance ) {
            throw new \Exception( 'Webhook timestamp too old.' );
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected       = hash_hmac( 'sha256', $signed_payload, $secret );

        foreach ( $signatures as $sig ) {
            if ( hash_equals( $expected, $sig ) ) {
                return json_decode( $payload, true );
            }
        }

        throw new \Exception( 'Webhook signature mismatch.' );
    }

    private function request( string $method, string $endpoint, array $body = [] ): array {
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Stripe-Version' => '2024-06-20',
            ],
            'timeout' => 30,
        ];

        if ( $method === 'POST' && $body ) {
            $args['body'] = $this->flatten( $body );
        }

        $url      = 'https://api.stripe.com/v1/' . $endpoint;
        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new \Exception( esc_html( $response->get_error_message() ) );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['error'] ) ) {
            throw new \Exception( esc_html( $data['error']['message'] ?? 'Stripe API error.' ) );
        }

        return $data;
    }

    // Flatten nested array to Stripe's dot-notation / bracket-notation format.
    private function flatten( array $data, string $prefix = '' ): array {
        $result = [];
        foreach ( $data as $key => $value ) {
            $full_key = $prefix ? "{$prefix}[{$key}]" : $key;
            if ( is_array( $value ) ) {
                $result = array_merge( $result, $this->flatten( $value, $full_key ) );
            } else {
                $result[ $full_key ] = $value;
            }
        }
        return $result;
    }
}
