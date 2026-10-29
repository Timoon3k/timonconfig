# TimonConfigs Core 1.1.4 / Theme 0.14.1 — English checkout, billing, Stripe wallets

## Why the earlier checkout failed

The Core 1.1.3 `woocommerce_checkout_fields` filter suppressed required first/last name and address fields on the page. The WooCommerce `wc-ajax=checkout` validation was performed in a separate request where the current page was not detected and the required country-dependent address fields could still be enforced. As a result, checkout showed a list of missing billing name/address errors. The previous checkout CSS also hid the WooCommerce Stripe Express Checkout wrapper.

## Changes

- Render normal WooCommerce billing identity and country-dependent required address fields for digital VIP: first name, last name, receipt email, country, street address, city, state/region, postal code. WooCommerce controls which address components are required for each country. Do not invent addresses or bypass processors' verification.
- Match the same field configuration on both the checkout page and the WooCommerce `wc-ajax=checkout` / `wc-ajax=update_order_review` requests.
- Switch to English for the two WooCommerce checkout AJAX endpoints, not just the frontend page.
- Restore the standard `woocommerce_checkout_before_customer_details` and `woocommerce_checkout_after_customer_details` template hooks and the `#customer_details` element for gateway integration.
- Remove CSS rules hiding the Stripe Express Checkout buttons and improve contrast of ArraySubs renewal information inside the order review.
- Keep Discord ownership, paid-order reconciliation, product-specific PayPal selection, WooCommerce accounts and role management unchanged.

## Enable Stripe Google Pay and Apple Pay (manual merchant steps required)

1. Install/enable the **official WooCommerce Stripe Payment Gateway** and connect the appropriate Stripe account (test or live).
2. In WordPress open **WooCommerce → Settings → Payments → Stripe → Payment Methods → Express checkouts** and enable Apple Pay / Google Pay together. In the Customize settings enable **Checkout** placement; disable product/cart placement to avoid bypassing TimonConfigs' Discord-first flow. Keep standard Stripe card payments enabled.
3. Under Stripe's **Payment method domains** verify that `timonconfigs.com` is registered. Ensure HTTPS and valid TLS. The Stripe extension normally creates the Apple Pay association file itself.
4. In **WooCommerce → Settings → Payments → Stripe → Settings → Account details → Configure connection**, confirm the webhook is marked **Configured** for both Test and Live. If necessary, use **Reconfigure webhooks**. The official Stripe gateway endpoint is `https://timonconfigs.com/?wc-api=wc_stripe`.
5. **Do not create a duplicate custom webhook in TimonConfigs.** The official Stripe gateway validates and processes its own signed events; ArraySubs observes WooCommerce orders and its own subscription lifecycle.
6. Note that Stripe may suppress express checkout for **virtual items** when store tax is calculated from customer billing address. Do **not** force-enable using `wc_stripe_should_hide_express_checkout_button_based_on_tax_setup` until accurate tax location calculations and wallet checkout amounts have been verified.
7. Apple Pay and Google Pay only display on eligible browsers/devices with configured wallets. Their visibility is not guaranteed on every device.
8. **Critical:** test monthly VIP **initial payment and at least one renewal** with Apple Pay and Google Pay and verify ArraySubs stores a reusable mandate/payment method for each. Checkout compatibility alone does not guarantee renewal compatibility; offer standard Stripe card/PayPal until tested.

## Acceptance checks (staging first)

- VIP Monthly product 427: EUR 29.99/month, active ArraySubs subscription, no trial. VIP Lifetime product 430: EUR 80 single payment.
- Normal checkout and WC-AJAX submit display required billing fields and English validation messages (no Polish).
- State/postcode fields adapt when changing billing country. Use a *real* customer billing address (do not use fictitious tax details).
- Only logged-in Discord-linked user can buy VIP. The matching WooCommerce order must carry the correct WP customer ID.
- Stripe card, Apple Pay / Google Pay (eligible device), official one-off PayPal and ArraySubs recurring PayPal are available according to product and gateway support, without duplicate PayPal buttons.
- Make a test payment: check WooCommerce paid order and VIP Discord role. Verify a recurring renewal and cancellation/deactivation, avoiding unwanted role revocation.
- Run WooCommerce → Status → Logs and ArraySubs Gateway/Audits diagnostics. Do not enable Live until all checks pass.

Docs:
- https://woocommerce.com/document/stripe/setup-and-configuration/express-checkouts/
- https://woocommerce.com/document/stripe/setup-and-configuration/stripe-webhooks/
- https://developer.woocommerce.com/docs/code-snippets/customising-checkout-fields
