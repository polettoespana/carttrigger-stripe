=== CartTrigger – Stripe ===
Contributors: polettoespana
Tags: woocommerce, stripe, payment, checkout, klarna
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
WC tested up to: 10.7.0
Requires Plugins: woocommerce
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stripe Payment Element gateway for WooCommerce. Displays all payment methods enabled in your Stripe Dashboard automatically.

== Description ==

CartTrigger – Stripe integrates Stripe's Payment Element into WooCommerce checkout. Unlike the official Stripe plugin, it displays **all payment methods enabled in your Stripe Dashboard** without requiring per-method manual configuration — including Bizum, MB Way, Revolut Pay, Multibanco, Klarna, and more.

**Features:**

* Single unified Payment Element showing all your enabled Stripe payment methods
* Supports payment method profiles (pmc_…) for fine-grained control
* Webhook handler for asynchronous payment methods (Klarna, bank transfers, etc.)
* Refund support from the WooCommerce order screen
* No Stripe PHP SDK required — uses WordPress HTTP API

== Installation ==

1. Upload the `carttrigger-stripe` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce → Settings → Payments → CartTrigger Stripe**.
4. Enter your Stripe Publishable Key, Secret Key, and Webhook Secret.
5. Optionally enter a Payment Method Configuration ID (`pmc_…`) to use a specific Stripe profile.
6. Go to **Settings → Permalinks** and click Save to flush rewrite rules.
7. Configure a Stripe webhook pointing to: `https://yoursite.com/wc-api/ctstripe_webhook`
   Events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`

== Changelog ==

= 1.1.0 =
* Express Checkout Element (Apple Pay / Google Pay) con shortcode `[ctstripe_express_checkout]`.
* Endpoint automatico per la verifica del dominio Apple Pay (`.well-known`).
* Re-mount del Payment Element dopo aggiornamento AJAX del checkout WooCommerce.
* Impostazioni per altezza e layout (colonne) dei pulsanti express.

= 1.0.0 =
* Initial release.
