=== CartTrigger – Stripe ===
Contributors: polettoespana
Tags: woocommerce, stripe, payment, checkout, klarna
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
WC tested up to: 10.7.0
Requires Plugins: woocommerce
Stable tag: 1.4.7
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

= 1.4.7 =
* Fix: sincronizzazione importo carrello dopo applicazione coupon (leggeva data.ctstripe_cart_amount invece di data.fragments['ctstripe_cart_amount']).

= 1.4.6 =
* Aggiunto header CORS Access-Control-Allow-Origin per domini Stripe CDN.
* Aggiunto Permissions-Policy per camera/microfono di hCaptcha (antifraud Stripe).

= 1.4.5 =
* Aggiunta impostazione "Righe massime pulsanti express": 0 = nessun limite (nessun pulsante "Más información"), valori > 0 limitano le righe visibili.

= 1.4.4 =
* Ripristinato campo "Classe CSS titolo" con default font-grotesk, applicato al label WC via JS.
* Fix: aggiunto autocomplete="new-password" sui campi Secret Key e Webhook Secret per evitare il prompt di salvataggio password del browser.

= 1.4.3 =
* Rimosso campo "Classe CSS titolo" — il titolo è gestito da WooCommerce.

= 1.4.2 =
* Il campo "Classe CSS titolo" viene ora applicato anche al label WC del gateway nel checkout.

= 1.4.1 =
* Aggiunge la classe font-grotesk al label del gateway iniettato da WooCommerce.

= 1.4.0 =
* Impostazione layout metodi di pagamento: accordion verticale o tabs orizzontali scorrevoli.
* Fix: titolo non più duplicato nel box di pagamento (WC lo renderizza già come label).

= 1.3.1 =
* Appearance: aggiunti spacingUnit, fontSizeBase e campo CSS Rules JSON per targeting componenti interni Stripe.

= 1.3.0 =
* Nuova card "Aspetto Payment Element": tema, colori (primario, sfondo, testo, errori), font family, border radius — configurabili dall'admin senza toccare codice.

= 1.2.0 =
* Pannello admin con layout a card (coerente con gli altri plugin CartTrigger).
* Nuove impostazioni: classe CSS per titolo e descrizione nel box di pagamento.
* Titolo e descrizione non renderizzati se vuoti.
* Fix: automatic_payment_methods.enabled passato come booleano (era stringa).

= 1.1.2 =
* Fix: ECE layout overflow impostato su 'auto' (Stripe non supporta 'never' con maxRows > 0).

= 1.1.1 =
* Fix: altezza e colonne dei pulsanti express parsate come interi (wp_localize_script restituisce stringhe).

= 1.1.0 =
* Express Checkout Element (Apple Pay / Google Pay) con shortcode `[ctstripe_express_checkout]`.
* Endpoint automatico per la verifica del dominio Apple Pay (`.well-known`).
* Re-mount del Payment Element dopo aggiornamento AJAX del checkout WooCommerce.
* Impostazioni per altezza e layout (colonne) dei pulsanti express.

= 1.0.0 =
* Initial release.
