=== CartTrigger – Stripe ===
Contributors: polettoespana
Tags: woocommerce, stripe, payment, checkout, klarna
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
WC tested up to: 10.7.0
Requires Plugins: woocommerce
Stable tag: 1.6.8
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

= 1.6.8 =
* Aggiunto link "Impostazioni" nella lista plugin (porta direttamente a WooCommerce → Pagamenti → CartTrigger Stripe).
* Aggiunto link "Sito web" nella riga meta del plugin.

= 1.6.7 =
* Nuovo: controllo NIF spostato nell'evento ECE `click` (pre-foglio) — il foglio Apple Pay/Google Pay non si apre mai se la validazione fallisce, eliminando l'esperienza negativa del foglio che si chiude.
* Nuovo: soglia NIF (€) configurabile dall'admin nel pannello Express Checkout, invece di essere hardcoded a 400 €.

= 1.6.6 =
* Fix: ECE carrello bloccato per ordini ≥ 400 € con redirect al checkout (NIF obbligatorio per legge).
* Fix: ECE checkout bloccato se NIF vuoto per ordini ≥ 400 € — scroll al campo NIF con highlight.
* Aggiunto checkout_url e nif_threshold (40000 centesimi) ai dati localizzati dello script.

= 1.6.4 =
* Fix: aggiunto wp_unslash() e sanitize_text_field() su $_GET['section'] in enqueue_admin_scripts() — risolve i PHPCS warning WordPress.Security.ValidatedSanitizedInput.

= 1.6.3 =
* Fix: ordini creati via ECE (Express Checkout fuori dal checkout) mostravano "Sconosciuto" come origine — aggiunto created_via=checkout a wc_create_order().

= 1.6.2 =
* Fix: ECE checkout page — i campi billing vengono popolati solo se vuoti (utente guest), senza sovrascrivere quelli precompilati dell'utente loggato.
* Fix: normalizzazione server-side del campo stato/provincia di Apple Pay (nome completo → codice WC, es. "Madrid" → "M") tramite nuovo endpoint wc-ajax=ctstripe_normalize_state.
* Fix: stessa normalizzazione applicata al flusso ECE carrello (ajax_create_order).

= 1.6.1 =
* Fix: ECE checkout page — rimossa la sovrascrittura dei campi billing con i dati di Apple Pay; l'utente loggato ha già i campi precompilati correttamente e i codici stato/provincia di Apple Pay non corrispondono ai valori delle <select> WooCommerce.

= 1.6.0 =
* Fix: endpoint AJAX per la creazione ordine ECE spostato da admin-ajax.php a ?wc-ajax= (WooCommerce endpoint) — risolve i 400 causati da WAF o dall'istanziazione lazy del gateway che impediva la registrazione delle hook su admin-ajax.php.
* Fix: hook AJAX registrate a plugins_loaded invece che nel costruttore del gateway, garantendo che siano disponibili indipendentemente dall'istanziazione del gateway.

= 1.5.9 =
* Fix: Apple Pay e Google Pay (wallet) non completavano il pagamento — stripe.confirmPayment() per i metodi wallet risolve la Promise senza redirect automatico; ora viene fatto redirect manuale al return handler con i parametri del PaymentIntent.

= 1.5.8 =
* Fix: ECE checkout page — i campi billing WooCommerce vengono popolati da event.billingDetails (Apple Pay/Google Pay) prima di sottomettere il form, evitando il rifiuto per campi obbligatori vuoti.

= 1.5.7 =
* Fix: coupon non applicato nell'ordine creato via AJAX da Express Checkout fuori dal checkout — i totali di riga vengono ora copiati direttamente dal carrello (stesso approccio di WC_Checkout::create_order), evitando il ricalcolo che ignorava gli sconti.

= 1.5.6 =
* Fix: testo default checkout_link accorciato in "Para otros datos, ve al checkout." per evitare overflow nel box ECE.

= 1.5.5 =
* Fix: ID stabile per i container ECE nel tema (ctstripe-ece-cart / ctstripe-ece-checkout) — previene la creazione di istanze stripe.elements() multiple dopo il refresh dei fragment WooCommerce, risolvendo i fallimenti Apple Pay dal carrello.
* Fix: validazione checkbox T&C prima dell'invio del form checkout via ECE — se non accettate, il foglio Apple Pay viene chiuso immediatamente e lo scroll va alla checkbox.

= 1.5.4 =
* Shortcode: aggiunto attributo checkout_link — testo cliccabile che rimanda al checkout per chi vuole compilare i dati manualmente. Entrambi notice e checkout_link personalizzabili per shortcode.

= 1.5.3 =
* Nuova impostazione: toggle per abilitare/disabilitare Stripe Link, PayPal e Amazon Pay nell'Express Checkout Element (opzione paymentMethods). Klarna è controllata dal PMC nel Stripe Dashboard.

= 1.5.2 =
* Nuovo: flusso diretto per Express Checkout fuori dalla pagina checkout — ordine creato via AJAX con dati billing da Apple Pay/Google Pay/Link, poi confirmPayment() chiamato direttamente senza form WC.
* Nuovo: shortcode mostra nota informativa ("Los datos de pago...") fuori dal checkout; personalizzabile con attributo notice="...".
* Fix: eceEvent.paymentFailed() chiamato correttamente in caso di errore per chiudere il foglio Apple Pay.
* Fix: wrapper data-ctstripe-ece-wrapper gestisce visibilità container + nota insieme.

= 1.5.1 =
* Fix: shortcode [ctstripe_express_checkout] non renderizza nulla nella pagina carrello — Apple Pay/Google Pay funzionano solo nel checkout dove esiste il flusso di pagamento completo.

= 1.5.0 =
* Fix: pulsanti Express Checkout sparivano dopo aggiornamento quantità nella pagina carrello — aggiunti listener su updated_cart e wc_fragments_refreshed per reinizializzare i container svuotati da WooCommerce.

= 1.4.9 =
* Fix: pulsanti Express Checkout sparivano dopo aggiornamento quantità — riutilizzata la stessa istanza stripe.elements() invece di crearne una nuova ad ogni updated_checkout.

= 1.4.8 =
* Fix: pulsanti Express Checkout sparivano dopo aggiornamento quantità — ora viene fatto unmount + re-mount invece di elements.update().

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
