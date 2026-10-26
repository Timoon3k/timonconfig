# Stripe session recovery — TimonConfigs Core 0.9.7

## Situation
A lost Stripe create-checkout HTTP response can leave an order `pending` in the WordPress ledger with no `checkout_id`, even when Stripe created a hosted checkout. The deliberate protection `stripe_checkout_close_uncertain` blocks blindly closing it.

## Administrative recovery
1. In the **Stripe Dashboard**, find the actual Checkout Session for the disputed order. Verify the account and Test/Live mode, its `client_reference_id` equals the WordPress order UUID, and no payment or subscription was created.
2. In WordPress **Settings → TimonConfigs billing → Customer orders**, search by WordPress user ID, Discord ID or order UUID. Choose the correct environment.
3. If that *Stripe* order is still pending or in checkout and has no provider references, use **Verify and recover Stripe checkout**, enter the real `cs_test_...` or `cs_live_...` ID, confirm, and submit. This is administrator-only on HTTPS.
4. The server re-reads the exact Stripe session and verifies signed account environment, immutable billing metadata, Discord ID, amount, price, product, and `payment_status=unpaid`. It also requires no PaymentIntent or Subscription. It rejects already claimed IDs. Only then is the reference restored.
5. The server requests Stripe to expire an open session, reads it again, and closes the *local* order **only** after Stripe confirms an expired, unpaid session and the ledger validates that no paid entitlement exists.
6. After success the buyer can start a new checkout. Existing paid time and other members' orders are not changed.

## If recovery is refused
- `stripe_checkout_close_uncertain`: the session may have completed or has associated financial objects. Do **not** force close. Refresh/reconcile and manage a real subscription via **Cancel auto-renewal**.
- `stripe_checkout_binding`: the Stripe object cannot be safely attributed to that order, plan, or Discord account.
- Missing Stripe Session ID: you cannot verify an outstanding provider POST from its absence. Investigate Stripe activity and request support; do not clear the WordPress order or grant VIP manually.
- If Stripe expires while a payment settles, the reference may remain linked and the order unresolved so that webhook reconciliation can finish safely.

No refund, role removal or live payment is triggered by this release itself. Existing Stripe, PayPal, OAuth and webhooks remain untouched.
