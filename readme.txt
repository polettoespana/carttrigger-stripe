=== CartTrigger – Stripe ===
Contributors: polettoespana
Tags: woocommerce, stripe, payment, checkout, klarna
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
WC tested up to: 10.7.0
Requires Plugins: woocommerce
Stable tag: 1.8.0
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

== Apple Pay Domain Verification ==

To enable Apple Pay, you need to serve a domain verification file provided by Stripe.

1. Go to **WooCommerce → Settings → Payments → CartTrigger Stripe → Apple Pay domain verification**.
2. Paste the content of the file. If Stripe did not generate it automatically for your account, use the default file hosted by Stripe:
   `https://stripe.com/files/apple-pay/apple-developer-merchantid-domain-association`
3. Save settings. The plugin serves the file automatically at `/.well-known/apple-developer-merchantid-domain-association`.
4. In the Stripe Dashboard go to **Settings → Payment methods → Apple Pay** and register your domain.

== Changelog ==

= 1.8.0 =
* New: WooCommerce Blocks checkout support — the Payment Element now works on the standard block-based checkout page (no shortcode required).
* New: blocks.js registers the payment method with the WC Blocks registry; amount updates automatically as the cart total changes.
* New: process_payment_blocks() handles confirmation token flow from Blocks context — creates and confirms the PaymentIntent server-side, with full 3DS / requires_action redirect support.
* New: woocommerce_rest_checkout_process_payment_with_context hook extracts the ctstripe_confirmation_token from Blocks payment data and stores it in the WC session before process_payment() runs.

= 1.7.2 =
* Fix: ECE amount not updated after coupon applied on cart page — woocommerce_cart_fragments hook added alongside woocommerce_update_order_review_fragments so the cart amount fragment is sent on cart page too.
* Fix: after wc_fragments_refreshed, read updated amount from WC sessionStorage and call elems.update({ amount }) on mounted ECE containers instead of remounting.

= 1.7.1 =
* Fix: added receipt_email to PaymentIntent args (checkout and Express Checkout) — Stripe always sends the receipt to the billing email without relying on the dashboard setting.
* Fix: receipt_email only included when non-empty to avoid Stripe API errors in Express Checkout flows where the wallet may not provide an email.
* Fix: T&C validation error now shown in #ctstripe-errors on checkout when pressing an express payment button without accepting terms.

= 1.7.0 =
* Fix: NIF notice in cart was invisible — replaced showError() (targeting #ctstripe-errors which does not exist outside checkout) with a notice injected next to the ECE container, with a direct link to checkout.
* Added .ctstripe-nif-notice style in checkout.css.

= 1.6.9 =
* Full internationalisation: all strings converted to English and wrapped with __().
* Added Text Domain (carttrigger-stripe) and load_plugin_textdomain().
* Complete Italian (it_IT) and Spanish (es_ES) translations — .po and .mo files included in languages/.
* JS NIF strings passed from PHP via wp_localize_script (ctstripe.i18n).

= 1.6.8 =
* Added "Settings" link in the plugin list (goes directly to WooCommerce → Payments → CartTrigger Stripe).
* Added "Website" link in the plugin row meta.

= 1.6.7 =
* New: NIF check moved to ECE click event (pre-sheet) — the Apple Pay / Google Pay sheet never opens if validation fails, avoiding the negative UX of a sheet that opens and immediately closes.
* New: NIF threshold (€) configurable from the admin panel under Express Checkout settings, replacing the hardcoded 400 € value.

= 1.6.6 =
* Fix: ECE cart blocked for orders ≥ 400 € with redirect to checkout (NIF required by law).
* Fix: ECE checkout blocked when NIF field is empty for orders ≥ 400 € — scroll to NIF field with highlight.
* Added checkout_url and nif_threshold (40000 cents) to localised script data.

= 1.6.4 =
* Fix: added wp_unslash() and sanitize_text_field() on $_GET['section'] in enqueue_admin_scripts() — resolves PHPCS warning WordPress.Security.ValidatedSanitizedInput.

= 1.6.3 =
* Fix: orders created via ECE (Express Checkout outside checkout page) showed "Unknown" as origin — added created_via=checkout to wc_create_order().

= 1.6.2 =
* Fix: ECE checkout page — billing fields are populated only when empty (guest user), without overwriting pre-filled fields for logged-in users.
* Fix: server-side normalisation of Apple Pay state/province field (full name → WC code, e.g. "Madrid" → "M") via new wc-ajax=ctstripe_normalize_state endpoint.
* Fix: same normalisation applied to the ECE cart flow (ajax_create_order).

= 1.6.1 =
* Fix: ECE checkout page — removed overwriting of billing fields with Apple Pay data; logged-in users already have fields correctly pre-filled and Apple Pay state codes do not match WooCommerce select values.

= 1.6.0 =
* Fix: ECE order creation AJAX endpoint moved from admin-ajax.php to ?wc-ajax= (WooCommerce endpoint) — resolves 400 errors caused by WAF rules or lazy gateway instantiation preventing hook registration on admin-ajax.php.
* Fix: AJAX hooks registered on plugins_loaded instead of in the gateway constructor, ensuring availability regardless of gateway instantiation.

= 1.5.9 =
* Fix: Apple Pay and Google Pay (wallet) payments were not completing — stripe.confirmPayment() for wallet methods resolves the Promise without automatic redirect; manual redirect to the return handler with PaymentIntent parameters is now performed.

= 1.5.8 =
* Fix: ECE checkout page — WooCommerce billing fields are populated from event.billingDetails (Apple Pay / Google Pay) before form submission, preventing rejection due to empty required fields.

= 1.5.7 =
* Fix: coupon not applied to order created via AJAX from Express Checkout outside checkout — line totals are now copied directly from the cart (same approach as WC_Checkout::create_order), avoiding recalculation that ignored discounts.

= 1.5.6 =
* Fix: default checkout_link text shortened to avoid overflow in the ECE box.

= 1.5.5 =
* Fix: stable IDs for ECE containers in the theme (ctstripe-ece-cart / ctstripe-ece-checkout) — prevents multiple stripe.elements() instances being created after WooCommerce fragment refresh, resolving Apple Pay failures from the cart.
* Fix: T&C checkbox validation before submitting the checkout form via ECE — if not accepted, the Apple Pay sheet closes immediately and the page scrolls to the checkbox.

= 1.5.4 =
* Shortcode: added checkout_link attribute — clickable text linking to checkout for users who want to fill in details manually. Both notice and checkout_link are customisable via shortcode attributes.

= 1.5.3 =
* New setting: toggle to enable/disable Stripe Link, PayPal and Amazon Pay in the Express Checkout Element (paymentMethods option). Klarna is controlled by the PMC in the Stripe Dashboard.

= 1.5.2 =
* New: direct flow for Express Checkout outside the checkout page — order created via AJAX with billing data from Apple Pay / Google Pay / Link, then confirmPayment() called directly without a WC form.
* New: shortcode displays an informational notice outside checkout; customisable with the notice="" attribute.
* Fix: eceEvent.paymentFailed() called correctly on error to close the Apple Pay sheet.
* Fix: data-ctstripe-ece-wrapper manages container visibility and notice together.

= 1.5.1 =
* Fix: [ctstripe_express_checkout] shortcode rendered nothing on the cart page — Apple Pay / Google Pay only work on the checkout page where the full payment flow exists.

= 1.5.0 =
* Fix: Express Checkout buttons disappeared after quantity update on the cart page — added listeners on updated_cart and wc_fragments_refreshed to reinitialise containers emptied by WooCommerce.

= 1.4.9 =
* Fix: Express Checkout buttons disappeared after quantity update — reusing the same stripe.elements() instance instead of creating a new one on every updated_checkout event.

= 1.4.8 =
* Fix: Express Checkout buttons disappeared after quantity update — unmount + re-mount instead of elements.update().

= 1.4.7 =
* Fix: cart amount sync after coupon application (was reading data.ctstripe_cart_amount instead of data.fragments['ctstripe_cart_amount']).

= 1.4.6 =
* Added CORS Access-Control-Allow-Origin header for Stripe CDN domains.
* Added Permissions-Policy for camera/microphone required by hCaptcha (Stripe antifraud).

= 1.4.5 =
* Added "Express button max rows" setting: 0 = no limit (no "More info" button), values > 0 limit visible rows.

= 1.4.4 =
* Restored "Title CSS class" field with font-grotesk default, applied to the WC label via JS.
* Fix: added autocomplete="new-password" on Secret Key and Webhook Secret fields to prevent the browser password-save prompt.

= 1.4.3 =
* Removed "Title CSS class" field — the title is managed by WooCommerce.

= 1.4.2 =
* "Title CSS class" is now also applied to the WC gateway label injected in checkout.

= 1.4.1 =
* Adds the font-grotesk class to the gateway label injected by WooCommerce.

= 1.4.0 =
* Payment method layout setting: vertical accordion or scrollable horizontal tabs.
* Fix: title no longer duplicated in the payment box (WC already renders it as a label).

= 1.3.1 =
* Appearance: added spacingUnit, fontSizeBase and CSS Rules JSON field for targeting internal Stripe components.

= 1.3.0 =
* New "Payment Element appearance" card: theme, colours (primary, background, text, errors), font family, border radius — configurable from the admin without touching code.

= 1.2.0 =
* Admin panel with card layout (consistent with other CartTrigger plugins).
* New settings: CSS class for title and description in the payment box.
* Title and description not rendered when empty.
* Fix: automatic_payment_methods.enabled passed as boolean (was string).

= 1.1.2 =
* Fix: ECE layout overflow set to 'auto' (Stripe does not support 'never' with maxRows > 0).

= 1.1.1 =
* Fix: express button height and columns parsed as integers (wp_localize_script returns strings).

= 1.1.0 =
* Express Checkout Element (Apple Pay / Google Pay) with `[ctstripe_express_checkout]` shortcode.
* Automatic Apple Pay domain verification endpoint (.well-known).
* Payment Element re-mount after WooCommerce AJAX checkout update.
* Settings for express button height and layout (columns).

= 1.0.0 =
* Initial release.
