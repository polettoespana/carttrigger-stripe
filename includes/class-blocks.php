<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class CTStripe_Blocks extends AbstractPaymentMethodType {

    protected $name = 'ctstripe';

    public function initialize(): void {
        $this->settings = get_option( 'woocommerce_ctstripe_settings', [] );
    }

    public function is_active(): bool {
        return ( $this->settings['enabled'] ?? 'no' ) === 'yes';
    }

    public function get_payment_method_script_handles(): array {
        wp_register_script(
            'ctstripe-blocks',
            CTSTRIPE_URL . 'assets/js/blocks.js',
            [ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'stripe-js' ],
            CTSTRIPE_VERSION,
            true
        );
        return [ 'ctstripe-blocks' ];
    }

    public function get_payment_method_data(): array {
        $s = $this->settings;

        $appearance = array_filter( [
            'theme'           => $s['appearance_theme'] ?? 'stripe',
            'colorPrimary'    => $s['appearance_color_primary'] ?? '',
            'colorBackground' => $s['appearance_color_background'] ?? '',
            'colorText'       => $s['appearance_color_text'] ?? '',
            'colorDanger'     => $s['appearance_color_danger'] ?? '',
            'fontFamily'      => $s['appearance_font_family'] ?? '',
            'fontSizeBase'    => $s['appearance_font_size_base'] ?? '',
            'borderRadius'    => $s['appearance_border_radius'] ?? '4px',
            'spacingUnit'     => $s['appearance_spacing_unit'] ?? '',
            'rules'           => $s['appearance_rules'] ?? '',
        ] );

        $locale_map = [
            'es_ES' => 'es', 'es_AR' => 'es',
            'it_IT' => 'it',
            'pt_PT' => 'pt', 'pt_BR' => 'pt-BR',
            'fr_FR' => 'fr',
            'de_DE' => 'de',
            'en_US' => 'en', 'en_GB' => 'en-GB',
        ];

        return [
            'title'           => $s['title'] ?? __( 'Pay with card or other method', 'carttrigger-stripe' ),
            'description'     => $s['description'] ?? '',
            'publishable_key' => $s['publishable_key'] ?? '',
            'locale'          => $locale_map[ get_locale() ] ?? 'auto',
            'pmc_id'          => $s['payment_method_config_id'] ?? '',
            'pe_layout'       => $s['pe_layout'] ?? 'accordion',
            'return_url'      => home_url( '/ctstripe-return' ),
            'appearance'      => $appearance,
            'supports'        => $this->get_supported_features(),
        ];
    }
}
