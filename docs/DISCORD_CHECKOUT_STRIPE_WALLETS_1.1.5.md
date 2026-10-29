# TimonConfigs Core 1.1.5 / Theme 0.14.2: Discord-first English checkout and Stripe wallets

## Changes
- **Checkout in English**: English WooCommerce/domain strings for checkout HTML and `wc-ajax=checkout`/`update_order_review`; contextual WooCommerce gettext strings; English privacy notice linked to the published Privacy Policy.
- **No separate WooCommerce login or logout UI**: anonymous checkout users continue through Discord OAuth; disable WooCommerce registration and hide Woo's customer-logout menu item. **Do not disable WordPress user sessions:** the logged-in Discord-linked WordPress ID must own each WooCommerce order and ArraySubs subscription.
- **Stripe card white rectangle**: checkout-scoped CSS removes the default white border of hosted `fieldset`/iframe wrappers without modifying embedded Stripe Elements or raw card data.
- **Readable mobile totals**: improved ArraySubs order summary text contrast and restricted horizontal overflow.
- **Stripe diagnostics**: the TimonConfigs Woo+Discord admin screen now shows the official webhook endpoint `https://timonconfigs.com/?wc-api=wc_stripe`, a link to Stripe configuration, and a warning when a virtual product plus billing-address tax calculation may suppress Express Checkout.

## Why Apple Pay / Google Pay can remain hidden
1. Apple Pay is not available on Android. Check on a compatible Apple device/browser with Apple Wallet set up. Google Pay requires a compatible browser, logged-in Google account and eligible payment method.
2. Open **WooCommerce → Settings → Payments → Stripe → Payment Methods → Express checkouts → Customize** and confirm **Checkout page** placement; Apple Pay / Google Pay must be enabled together.
3. Ensure HTTPS, a valid TLS certificate and registered payment method domain `timonconfigs.com`.
4. The official WooCommerce Stripe extension **intentionally hides** Apple Pay / Google Pay for **virtual products** when taxes are calculated from the **customer billing address**. Do not blindly override `wc_stripe_should_hide_express_checkout_button_based_on_tax_setup`; evaluate your real tax rules or ask a qualified tax adviser.
5. Clear WooCommerce transients, LiteSpeed Cache and CDN. Check WooCommerce → Status → Logs (Stripe) and the official plugin's express button eligibility diagnostic.
6. Express Checkout support for **ArraySubs Free recurring VIP Monthly** must be verified with an initial payment **and successful subsequent renewal**; Apple Pay/Google Pay display alone is not evidence of a reusable subscription mandate. Until confirmed, use Stripe card or a recurring PayPal gateway already tested with ArraySubs.

## Stripe webhook
- The public checkout must **not** display a webhook. Stripe sends events server-to-server.
- In the official Stripe plugin: WooCommerce → Settings → Payments → Stripe → Settings → Account details → Configure connection; check Live and Test webhook status are both **Configured**. Use **Reconfigure webhooks** if needed.
- Expected Stripe endpoint: `https://timonconfigs.com/?wc-api=wc_stripe`. Do not create another custom webhook in TimonConfigs; the official WooCommerce Stripe extension owns signature verification and event handling.
- Do **not** commit webhook signing secrets or Stripe keys to GitHub.

## Release gates
- Run `php tests/checkout-presentation-integration.php`.
- Run PHP lint, confirm no PHP warnings/fatal errors in WooCommerce checkout.
- Verify logged-out users can log in **through Discord only** and then reach the custom checkout. WooCommerce should not show separate login, registration or logout controls.
- Verify **both** billing details and checkout errors are English. Validate real address and country-dependent required fields; never invent an address or skip mandatory provider fields.
- Confirm VIP Lifetime (product ID 430, EUR 80) and VIP Monthly (ID 427, EUR 29.99 monthly), their correct payment methods, and PayPal gateway separation.
- End-to-end payment, initial Discord role grant, renewal, failed renewal, cancellation at paid term, and refunds must be confirmed on a staging system or controlled environment before sales Live.

Official docs:
https://woocommerce.com/document/stripe/setup-and-configuration/express-checkouts/
https://woocommerce.com/document/stripe/setup-and-configuration/stripe-webhooks/
https://woocommerce.com/document/stripe/customer-experience/express-checkouts/
