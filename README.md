# CartTrigger – Stripe

Stripe Payment Element gateway for WooCommerce. Displays all payment methods enabled in your Stripe Dashboard automatically — no per-method configuration required.

## Features

- **Payment Element** — single unified UI that shows every payment method enabled in your Stripe Dashboard (cards, Bizum, MB Way, Klarna, Revolut Pay, Multibanco, and more)
- **Express Checkout Element** — Apple Pay and Google Pay buttons via the `[ctstripe_express_checkout]` shortcode, placeable on any page (cart, product, landing page)
- **Payment Method Configuration** — supports `pmc_…` profiles for fine-grained control over which methods appear
- **Webhook handler** — processes `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled` for asynchronous methods (Klarna, bank transfers, etc.)
- **Apple Pay domain verification** — serves the `.well-known` file automatically
- **Refund support** — partial and full refunds from the WooCommerce order screen
- **Appearance customisation** — theme, colours, font, border radius and custom CSS rules configurable from the admin panel
- **No Stripe PHP SDK required** — uses the WordPress HTTP API

## Requirements

- WordPress 6.3+
- WooCommerce 8.0+
- PHP 7.4+
- A Stripe account (live or test mode)

## Installation

1. Upload the `carttrigger-stripe` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins** in WordPress admin.
3. Go to **WooCommerce → Settings → Payments → CartTrigger Stripe**.
4. Enter your **Publishable Key**, **Secret Key**, and **Webhook Secret**.
5. Optionally enter a **Payment Method Configuration ID** (`pmc_…`) to use a specific Stripe profile.
6. Go to **Settings → Permalinks** and click Save to flush rewrite rules.
7. In the Stripe Dashboard, create a webhook pointing to:
   ```
   https://yoursite.com/wc-api/ctstripe_webhook
   ```
   Events to listen for: `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`

## Express Checkout Shortcode

Place Apple Pay / Google Pay buttons anywhere with:

```
[ctstripe_express_checkout]
```

Available attributes:

| Attribute | Default | Description |
|---|---|---|
| `id` | auto-generated | Stable HTML ID for the container |
| `class` | — | CSS class added to the wrapper |
| `style` | — | Inline style added to the wrapper |
| `notice` | `Se utilizarán los datos guardados…` | Informational text shown outside the checkout page |
| `checkout_link` | `Para otros datos, ve al checkout.` | Link text pointing to the checkout page |

Example with stable ID (recommended to avoid issues after fragment refresh):

```
[ctstripe_express_checkout id="ctstripe-ece-cart" style="margin-bottom:24px;"]
```

## Apple Pay Domain Verification

The plugin automatically serves the Apple Pay domain association file at `/.well-known/apple-developer-merchantid-domain-association`. Paste the file content in **WooCommerce → Settings → Payments → CartTrigger Stripe → Apple Pay domain verification**.

After saving, register your domain in the Stripe Dashboard under **Settings → Payment methods → Apple Pay**.

## Changelog

See [readme.txt](readme.txt).

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
