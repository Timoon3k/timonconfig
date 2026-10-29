> Aktualizacja 2026-10-30: od Core **1.1.9**, na polecenie właściciela, reCAPTCHA została usunięta. Poniższe wymagania CAPTCHA dotyczą historycznej wersji.

# TimonConfigs Core 1.1.7 — controlled release, subscriptions and role moderation

## Confirmed current behavior
The original integration queues user reconciliation 5 seconds after a verified WooCommerce paid order and performs a periodic 5-minute scan via WP-Cron. WordPress front-end page caching and the Discord 60-second membership cache can cause visible delay. New release queues via WooCommerce Action Scheduler where available and invalidates only this member's Discord badge cache after a real role change.

## Member profile
- The visible `Manage WooCommerce orders and subscriptions` button is removed. Customers use the TimonConfigs `/account/` page.
- `Cancel monthly subscription` POST requires an authenticated WP session from Discord, a per-user nonce, ownership of the actual ArraySubs 2.0.9 subscription, matching product ID, and a supported active status. ArraySubs' native `arraysubs_schedule_cancellation(...,'customer')` method cancels future automatic renewals at period end. It preserves the current paid access and all financial records.
- The standard WooCommerce customer dashboard redirects to `/account/`. Essential order payment/receipt endpoints stay available to gateways.
- The WooCommerce return URL for VIP products points to the member dashboard with a pending-payment status notice. Do not claim a payment or role is confirmed until provider webhook reconciles.

## Moderator role removal
- When an existing bot-owned VIP role disappears, the bot **does not automatically restore it** based on an old paid order.
- For automatic revocation it requires **Discord View Audit Log** and a fresh `MEMBER_ROLE_UPDATE` audit log entry proving the specific role was removed by an actor currently holding the configured Staff or Owner role. It sets a timestamped local entitlement cutoff and calls ArraySubs' immediate cancellation for Monthly. It never deletes historical orders or captures/refunds payments.
- If audit verification is unavailable or no Staff/Owner entry is present, it **does not silently cancel a customer's subscription**; it records `vip_role_missing_manual_review_required` and refuses to auto-restore the missing role pending support review. Operators must verify and stop billing manually in ArraySubs/Stripe/PayPal where necessary.
- A new *separate* verified paid purchase after the revocation timestamp can re-enable the affected VIP entitlement. Old Lifetime orders and old Monthly parent orders are not sufficient.
- On a successful VIP role grant the bot removes Free Member. When neither Monthly nor Lifetime remains paid and no verified VIP role is present, it restores Free Member to previously tracked customers. The bot must have Manage Roles and sit above all three roles.

## Mandatory checkout reCAPTCHA v2 — setup BEFORE installing 1.1.7
This release **blocks VIP checkout** until reCAPTCHA v2 is configured and solved. It does not merely display the v3 badge. ReCAPTCHA v2 uses server-side `siteverify`, checks the response hostname, and saves a 10-minute, per-Woo-session, per-WordPress-user verification. The classic checkout and Woo Store API payment endpoints reject unverified card, PayPal and wallet attempts.

1. In Google reCAPTCHA Admin create a **Challenge (v2) / "I'm not a robot" checkbox** key restricted to `timonconfigs.com` (also include actual checkout hostnames).
2. Put these in Hostinger's private `wp-config.php`, before `/* That's all, stop editing! */`:

```php
define( 'TIMONCONFIGS_RECAPTCHA_V2_SITE_KEY', 'PUBLIC_V2_SITE_KEY' );
define( 'TIMONCONFIGS_RECAPTCHA_V2_SECRET_KEY', 'PRIVATE_V2_SECRET_KEY' );
```

3. Do not upload the secret key or `wp-config.php` to GitHub, screenshots, chat, or frontend. The public site key is not confidential.
4. Test the checkbox, the "Verification completed" notice and card/PayPal checkout, and repeat with Google Pay/Apple Pay on supported devices. With JS blocked, Google unavailable, wrong keys or expired proof, checkout **fails closed**. The reCAPTCHA widget may be unavailable in some countries; provide support alternatives.

## PayPal Sandbox
Use **PayPal (ArraySubs)** for Monthly (not the one-off official PayPal Payments gateway). On staging: WooCommerce → Settings → Payments → PayPal (ArraySubs) → Manage → Enable PayPal Sandbox mode. Create sandbox Business + buyer accounts at PayPal Developer, a sandbox REST app with Client ID and Secret, and add the ArraySubs webhook URL with all required 12 events. Set its sandbox Webhook ID. Sandbox and Live credentials/webhook IDs must not be mixed.

For **VIP Lifetime** (one-time) PayPal comes from WooCommerce PayPal Payments. Its sandbox connection is configured separately via that plugin's Connection/Advanced settings. Do not switch live payment processing to sandbox for real customers. Prefer staging domain for all tests.

## Release requirements
- PHP lint and offline WordPress-hook mock regressions.
- Controlled staging installation after backup; verify PHP error log.
- Full paid checkout, Discord role grant/Free removal; monthly cancellation and expiry/Free restore; moderation audit, provider recurring cancellation, Lifetime block after Staff removal and new purchase behavior; negative reCAPTCHA tests; OAuth login.
- Real Stripe/PayPal webhook behavior and a subsequent paid renewal have NOT been tested using GitHub CI. Do not deploy to live until passed; do not claim cancellation or refund guaranteed solely by a local role change.

Official references:
https://support.arrayhash.com/arraysubs/checkout-and-payments/automatic-payments/paypal.html
https://support.arrayhash.com/arraysubs/developers/subscriptions.html
https://woocommerce.com/document/woocommerce-paypal-payments/account-setup-and-onboarding/
