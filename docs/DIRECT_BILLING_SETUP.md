# Direct billing — configuration and controlled rollout

**2026-10-26 · Core 0.9.2:** the owner explicitly chose a restricted real Live pilot for their own Discord account. Use [the shorter Live pilot setup](SIMPLER_LIVE_CHECKOUT_0.9.2.md) for that authorized scope: encrypted dashboard credentials, two Stripe Price IDs and login returning directly to checkout. The staging-first instructions below remain the broader acceptance guide; they do not prohibit this restricted pilot or authorize a public launch. Payment-button consent replaces the earlier website checkbox; it does not itself waive consumer withdrawal rights.

2026-10-25 · Core 0.9.1 / theme 0.11.1 · Seller country: Poland · Checkout currency: EUR. This release is for local/staging review. It has not been enabled on production or validated against real Stripe Test, PayPal Sandbox or Discord accounts.

## 1. Website checkout and preserved existing access

Back up the WordPress database, plugin/theme and private file store. Install this release first on an **isolated HTTPS staging site**, with separate payment credentials and preferably an isolated Discord guild/application. Set `WP_ENVIRONMENT_TYPE` to `staging`. Do not copy production payment secrets, production subscription data, cron jobs or role automation into a test environment.

The default is `mode=test`, `routing=disabled`, with both PayPal and role synchronization disabled. The only routing values are `disabled` and `direct`; a stored `legacy` or unknown value resolves to `disabled`. Upgrade.chat is retired for new website purchases. There is no external checkout fallback: stored historical URL options are retained but ignored, and destination/sanitization compatibility methods return an empty value. `/checkout/monthly/` and `/checkout/lifetime/` remain the website entry points. Removing old routing does not cancel an earlier subscription or erase purchased access.

The central displayed offer is always **Free €0, Monthly €29.99/month, Lifetime €80 once**, including while checkout is disabled. Showing the price does not enable payment: an unavailable checkout displays its setup notice and offers no purchase form. Browser fields and Customizer amounts cannot change the charged price. Discord Ticket Tool messages should link to these website routes; the ticket is a support/link channel, never payment proof. See [Ticket Tool handoff](WEBSITE_CHECKOUT_TICKET_TOOL.md).

**Settings → TimonConfigs** shows three read-only links for Pricing, Monthly and Lifetime. Configure provider IDs and Test/Sandbox settings separately under **Settings → TimonConfigs billing** (`/wp-admin/options-general.php?page=tc-billing`). The former external URL editor is not registered.

On the first administrator request, a versioned `admin_init` migration with `manage_options` updates only five exactly recognized, whole stock pages: Pricing, VIP Monthly, Terms of Service, Privacy Policy and Refund Policy. A private `_tc_checkout_copy_v1_backup` retains the former content, and SQL compare-and-swap refuses concurrent edits. Page title, status, publication/modification dates and legal-review metadata are preserved. No page-content write occurs on a public request. Authored content is not replaced; review any flagged wording under **Tools → TimonConfigs setup → Website checkout content**. The same page offers a safe retry after a failed update. Legal draft markers remain until the actual business completes review; no existing page is published or unpublished by this migration, and it is not legal approval.

## 2. Server secrets

Put actual values in the hosting server's private `wp-config.php` or equivalent secret injection, never in Git, page content, chat, browser JavaScript or a public environment file. The following placeholders are explanatory and must not be pasted unchanged:

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
define( 'TIMONCONFIGS_BILLING_TEST_STRIPE_SECRET_KEY', '<Stripe test secret key>' );
define( 'TIMONCONFIGS_BILLING_TEST_STRIPE_WEBHOOK_SECRET', '<endpoint whsec_ signing secret>' );
// Add only when the Stripe Test acceptance checklist has passed:
define( 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID', '<Sandbox client ID>' );
define( 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_SECRET', '<Sandbox client secret>' );
// Needed only for explicitly enabled staging role synchronization:
define( 'TIMONCONFIGS_BILLING_TEST_DISCORD_BOT_TOKEN', '<staging bot token>' );
// Public verification key, distinct from both OAuth secret and bot token:
define( 'TIMONCONFIGS_BILLING_DISCORD_PUBLIC_KEY', '<64-character Ed25519 application public key>' );
```

Each credential above except the interactions public key has a separate Live constant with `TEST` replaced by `LIVE`. There is intentionally **no Live approval constant in this setup example**. Live additionally requires `TIMONCONFIGS_BILLING_LIVE_APPROVED === true`, HTTPS, administrator confirmations of real Stripe tests, migration and legal readiness, plus configured role synchronization. Adding that constant is a later, explicitly approved production action, not part of installing this release.

PHP 8.2 is the tested runtime. Use HTTPS, working CA certificates and outbound HTTPS to the pinned Stripe, PayPal and Discord API hosts. Discord interactions require PHP sodium. Keep the existing OAuth encryption salts and private-storage configuration stable. Administrators see whether credentials exist, never their values.

## 3. Stripe Test first

1. Create two **test** Stripe products/prices: EUR 29.99 recurring every one month (no trial) and EUR 80.00 one time. Quantity is always one. Do not add a second billing intermediary.
2. In **Settings → TimonConfigs billing**, enter the test Monthly/Lifetime Product IDs (`prod_…`) and Price IDs (`price_…`). Set Mode to Test / Sandbox. Leave PayPal disabled. Change Routing from Disabled to Direct checkout only on the isolated staging site after entering the required settings. This does not approve Live.
3. Create the test webhook endpoint:
   `https://<staging-host>/wp-json/timonconfigs/v1/billing/stripe/test`.
   Use the endpoint signing secret, not an API key, for `STRIPE_WEBHOOK_SECRET`.
4. The adapter requests Stripe API version **2024-06-20**. Configure compatible event delivery and verify actual account availability/schema in Stripe before testing. Subscribe to at least:
   - `checkout.session.completed`
   - `invoice.paid`, `invoice.payment_failed`
   - `customer.subscription.updated`, `customer.subscription.deleted`
   - `charge.refunded`
   - `charge.dispute.created`, `charge.dispute.updated`, `charge.dispute.closed`
5. Configure Stripe Customer Portal for the actual business: payment method updates, billing history and cancellation at period end. Wallets such as Apple Pay / Google Pay depend on the merchant account, browser, domain and Stripe availability; the plugin does not claim they are already enabled.
6. Log in through the existing Discord OAuth flow with a genuinely verified Free member. Review each price and explicit payment/renewal consent, then complete **test** Monthly and Lifetime checkout. Use Stripe's documented test instruments only. No access is granted from a success URL.
7. Run real Test acceptance scenarios below, retain event/order evidence without secrets and only then check the administrator's **Stripe tests confirmed** flag. A local mocked test pass is not this confirmation.

Stripe validation checks the configured product/price, exact EUR amount, interval, quantity, settled payment and immutable WordPress/Discord order binding. Automatic Tax is currently disabled and discounts are not offered; changing that requires a deliberate tax/amount design and new tests. Do not enable an account setting that silently changes the expected fixed total.

## 4. PayPal Sandbox after Stripe acceptance

1. Create a Sandbox REST application and separate Sandbox buyer/merchant accounts. Set the two Sandbox credentials privately on the server.
2. Create a PayPal product and an active EUR **29.99 every one month** plan, without trials, setup fees or extra billing cycles. Enter its Product ID and Plan ID in the test configuration. Lifetime uses server-created Checkout v2 orders for EUR 80.00.
3. Create the Sandbox webhook endpoint:
   `https://<staging-host>/wp-json/timonconfigs/v1/billing/paypal/test`.
   Enter the actual **webhook ID** in test settings. Subscribe to supported account/API event names used by the adapter:
   - `PAYMENT.CAPTURE.COMPLETED`, `.DENIED`, `.PENDING`, `.REFUNDED`, `.REVERSED`
   - `PAYMENT.SALE.COMPLETED`, `.PENDING`, `.DENIED`, `.REFUNDED`, `.REVERSED`
   - `BILLING.SUBSCRIPTION.CREATED`, `.ACTIVATED`, `.UPDATED`, `.CANCELLED`, `.SUSPENDED`, `.EXPIRED`, `.PAYMENT.FAILED`
   - `CUSTOMER.DISPUTE.CREATED`, `.UPDATED`, `.RESOLVED`
4. Enable PayPal only after real Stripe tests are confirmed. Verify event names and availability against the account's actual Sandbox subscription/API configuration; event names here describe implemented handlers, not proof of provider acceptance.
5. Test Monthly approval/renewal/cancel and Lifetime approval **plus authenticated server capture**. A redirect from PayPal is informational; the account's `Complete approved PayPal payment` action is an owner-bound, nonce-protected POST. Canonical provider verification still determines access.

Sandbox and Live use separate pinned API hosts and credentials. Webhook verification calls PayPal's signature-verification API with the configured webhook ID; the plugin does not fetch arbitrary notification certificate URLs. A subscription merely marked ACTIVE is not payment proof. An uncertain older checkout with no saved provider ID is blocked for support reconciliation; do not delete its ledger row to force another charge. Retrieve the real provider outcome by its TimonConfigs order UUID first. PayPal sale records lack a directly trusted service-period end in this integration; paid-time calculation is conservatively limited to a calendar month, and actual renewal/cancellation timing must pass Sandbox acceptance.

## 5. Ticket Tool, Discord roles and optional purchase commands

For the requested Discord purchase entry, paste the website links or prepared English message from [WEBSITE_CHECKOUT_TICKET_TOOL.md](WEBSITE_CHECKOUT_TICKET_TOOL.md) into the appropriate existing Ticket Tool response or buttons. This code change does not configure Ticket Tool remotely. A normal website link is sufficient; the signed slash commands below are optional, not a requirement for Ticket Tool.

Existing OAuth remains responsible for account identity and current role checks during downloads. Keep its exact callback registered: `https://<staging-host>/?tc_discord_callback=1`. Keep Security Bot verification and approved Owner publishing settings intact.

In billing Test configuration enter Guild ID, VIP Monthly Role ID and VIP Lifetime Role ID. They must match **Discord membership** OAuth settings for that same guild; VIP roles must be distinct from each other and Free/Staff/Owner. Invite the staging application's bot with **Manage Roles** and `applications.commands` scope. Put the bot's highest role strictly above both VIP roles. Never give it Administrator just to bypass hierarchy issues. Explicitly enable role synchronization only for the controlled test guild.

The worker uses exact VIP-role `PUT` / `DELETE` requests and a retry queue. It never replaces a member's full role list and never changes Free, Staff, Owner or verification protections. Existing roles with no ownership marker remain external and require manual review; they are not automatically adopted or removed, even when a matching purchase is active. A remote role write followed by a failed database marker write is an explicit error; retry never manufactures ownership. Independent paid/grant/legacy access is considered together. Inspect administrator diagnostics after each scenario.

For slash commands, set the application's Interactions Endpoint URL to:
`https://<staging-host>/wp-json/timonconfigs/v1/billing/discord/interactions`.
The endpoint validates Discord's Ed25519 signature and timestamp. Register guild-scoped test commands through Discord's authorized application-command API; the plugin does not register them automatically. Use the following JSON as the request body for **creating these four commands individually** through `POST /applications/<application-id>/guilds/<guild-id>/commands` with authorized server-side bot credentials. Do not bulk-overwrite existing commands:

```json
{"name":"buy","description":"Start a TimonConfigs membership checkout","type":1,"options":[{"name":"plan","description":"Choose your membership","type":3,"required":false,"choices":[{"name":"VIP Monthly","value":"monthly"},{"name":"VIP Lifetime","value":"lifetime"}]}]}
{"name":"membership","description":"View your membership and linked account","type":1}
{"name":"manage","description":"Manage your TimonConfigs membership","type":1}
{"name":"link","description":"Link your Discord account on TimonConfigs","type":1}
```

Each line above is an independent JSON request, not a single JSON document. Responses are ephemeral. `/buy` defaults to Monthly or accepts the explicit plan choice; it issues a ten-minute actor-bound website intent, then still requires the same Discord OAuth identity, website nonce and payment consent. `/membership` privately reports the signed actor’s current ledger package (clearly marked in Test), or asks an unlinked actor to sign in; it also links to the account page for current Discord-role checks. `/manage` and `/link` open the authenticated account flow. After OAuth, users return to the checkout link; there is no automatic purchase on login. An always-running gateway bot server is unnecessary: WordPress HTTPS endpoints, Discord REST and reliable server cron handle this implementation.

## 6. Hostinger cron, caching and diagnostics

Create a server cron job every five minutes. Prefer WP-CLI where available (substitute the real private WordPress path):

```sh
wp --path=/absolute/path/to/wordpress cron event run --due-now --quiet
```

Use the actual PHP/WP-CLI binary available on that hosting plan. Alternatively schedule the site's HTTPS `wp-cron.php` through Hostinger's cron interface after verifying its server-side execution. Set `DISABLE_WP_CRON` only once the replacement job is actually running. Never print payment credentials in cron commands or logs. Verify that **Settings → TimonConfigs billing → Last worker run** updates without page traffic. The billing event is `tc_billing_tick`, with a five-minute recurring schedule when there is direct billing or existing ledger work; failed jobs use bounded backoff.

Exclude `/account/`, both `/checkout/` URLs, OAuth query endpoints, billing REST routes, `admin-post.php`, protected-download routes, authenticated Owner pages and associated signed queries from page/CDN cache. Do not block provider webhooks behind Basic Auth, maintenance mode, a browser challenge or a login redirect. Staging can otherwise be private/noindex, but the signed webhook and interaction endpoints must be reachable by their senders. Private file storage stays outside every public document root.

The administrator view shows bounded event/sync diagnostics, attempts, last worker run and safe retry actions protected by capability and nonce. Check provider dashboards alongside these diagnostics. Database/provider outages must remain pending/error, not a successful payment or a successful role removal. Retain financial records with an explicit business retention policy and protect database backups; do not enable request-body/token logging for debugging.

## 7. Actual acceptance and migration

Perform these first with isolated accounts and provider test modes:

| Scenario | Required evidence |
| --- | --- |
| Monthly and Lifetime via each enabled provider | Exact EUR totals, correct linked identity, verified provider event, paid ledger, appropriate Discord role, protected download allowed |
| Declined checkout and failed renewal | No invented access or period extension; already prepaid time remains until its actual end |
| Successful renewal and delayed/out-of-order notifications | Paid end advances once; duplicate/reordered events cannot regress the canonical current state |
| Cancel auto-renewal | Provider confirms no further renewal; paid access remains through the actual paid end |
| Expiry | Reliable cron expires the ledger and removes only a module-owned role when every independent valid source has ended and legacy review is complete |
| Refund, partial refund, dispute and resolution | Only the affected source changes; older-cycle refund cannot erase a newer paid cycle; retryable/unproven reversals stay under review |
| Discord outage/429/hierarchy problem | Visible pending/error state, safe retry after recovery, no changes to protected roles |
| Multi-provider, Lifetime, giveaway and legacy source | A valid independent title prevents inappropriate removal; no accidental second recurring subscription |
| Free, logged-out, wrong account and direct URL/return replay | Premium denied server-side; another member's order cannot be managed |
| Signed slash commands | Forged/expired commands and cross-account purchase intents rejected; real actor can continue the controlled checkout |

Before any Live cutover, export the real Upgrade.chat inventory and match each account to its existing Discord ID. Record **only verified** Monthly end dates and Lifetime/grant rights in the administrator's Verified legacy access section. Reviews are mode-specific; Test review does not approve Live. Confirm each member's legacy inventory only after all valid sources were recorded. The plugin does not import fabricated dates, take over externally managed roles, cancel Upgrade.chat subscriptions or stop its role automation.

Plan ownership of each legacy role/subscription with Upgrade.chat before allowing the new bot to manage it. Two writers for the same VIP role can conflict even with the plugin's conservative safeguards; the migration needs an explicit operational cutover. Tell customers that a new Lifetime payment does not cancel a pre-existing Monthly subscription. Obtain explicit migration consent and verify cancellation/stop of an old recurring contract before starting another one. A rollback sets **new checkout routing** to `disabled`, keeps ledger tables and scheduled maintenance, and preserves audit/payment records. It must not restore retired external URLs or a legacy fallback. Existing native contracts still require their current environment, credentials and working reconciliation cron; disabling new purchases does not undo charges or erase contracts already created.

## 8. Poland, EUR, taxes and actual costs

EUR is the customer-facing and charged currency; it does not by itself guarantee EUR settlement. Complete Polish merchant onboarding/KYC and verify supported EUR settlement/balance/bank settings in each actual account. An EUR bank account may avoid a conversion only when that provider's account and payout policy support it. Record any receiving-bank fees. Do not assume Stripe and PayPal use the same settlement rules.

No current percentage/rate is asserted here. During this session the official documentation/pricing requests were blocked by the execution network (CONNECT 403), so the actual merchant tariff and current provider compatibility remain unverified. Obtain these official sources plus the merchant agreement before choosing settlement configuration:

- Stripe Poland pricing: <https://stripe.com/en-pl/pricing>
- Stripe Billing pricing: <https://stripe.com/billing/pricing>
- PayPal Poland business fees: <https://www.paypal.com/pl/business/paypal-business-fees>
- Stripe Checkout API / webhooks: <https://docs.stripe.com/api/checkout/sessions/create> · <https://docs.stripe.com/webhooks>
- PayPal subscriptions API: <https://developer.paypal.com/docs/api/subscriptions/v1/>

For both €29.99 and €80 calculate `customer total − percentage fee − fixed fee − recurring-billing fee − cross-border surcharge − currency conversion − payout/bank cost`, including fee VAT where applicable. Compare actual EEA cards, non-EEA cards and PayPal buyers, the actual refund-fee policy and dispute exposure. Direct integration removes Upgrade.chat from **new approved purchases**, but Stripe Billing and other provider services may still have their own charges. No “lowest fee” claim is justified before account-specific evidence.

Before Live, the actual Polish business must supply its legal name, address, NIP/VAT information, contact details, accurate prices/tax treatment, supported customer locations, invoices/receipts, renewal/cancellation terms, Lifetime license and privacy/retention policy. Review EU consumer withdrawal/digital-content delivery consent and refund handling for this exact offer with an accountant/legal adviser. The existing payment-consent checkbox is not proof that every legal condition has been satisfied. There is no automatic VAT/OSS determination, tax invoice integration or completed legal approval in this release. The fixed charge is expected to be the reviewed customer total, so tax-inclusive treatment and any territorial restrictions must be resolved before enabling Live. The administrator legal confirmation records a real review; it must not be checked simply to bypass the guard.

Related launch documents preserved from main PR #11: [legal publication](LEGAL_PUBLICATION_CHECKLIST_2026.md), [SEO/GSC](SEO_GSC_APEX_LAUNCH_2026.md) and [authentic reviews](REVIEWS_AUTHENTIC_FEEDBACK_2026.md). Their older €40/€100 and active Upgrade.chat checkout references are historical; the current 0.9.1 offer and routing instructions above take precedence. Seller identity, legal approval, genuine-feedback and SEO readiness checks remain required.
