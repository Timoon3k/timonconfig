# Direct billing — implementation report

## Current update — simpler checkout and restricted Live pilot (2026-10-26)

Core **0.9.2** / theme **0.11.1**, code `9527129` published to GitHub `main` after explicit owner authorization for real Live payments restricted to their own Discord account. No merchant credentials were entered, no real transaction or role change was performed, and Hostinger installation/activation is unverified. Earlier staging-only wording is historical within this restricted pilot scope; public Live still has separate approval gates.

**817 PHP assertions passed:** checkout OAuth return 53; OAuth regression 127; Live pilot 44; encrypted credentials 57; facade 87; Discord delivery 93; Store 112; Stripe 99; PayPal 79; checkout 28; presentation 38. Canonical provider/Discord traffic is intercepted; local WordPress bindings, database transactions, AES-GCM storage and Ed25519 signatures are real. Actual OAuth callbacks return Monthly/Lifetime, reject forged destinations and consume state. The payment buttons use deliberate nonce-protected POST with displayed price/renewal terms and recorded acceptance version, without a checkbox. No automatic purchase occurs on login or GET.

Live checks cover single-actor UI/POST/Discord intents, forged identities, missing setup diagnostics, GET-only delivery preflight for guild owner, bot hierarchy/API failures before local orders or provider writes, in-flight pilot pause, authenticated customer cancellation and reconciliation after purchases pause, and HTTPS preservation of Live maintenance. Focused Astra Medium source review found no blocking issue. It does not certify actual Live APIs or consumer-law readiness.

**16 responsive views +2 actual local checkout routes +6 public pages** passed without overflow or browser errors. **97 fresh ZIP installation checks** passed on separate offline WordPress 6.8.3/PHP 8.2; the main development database was not used. PHP source lint **73** files, JS syntax and diff checks passed. ZIP contents match source byte-for-byte: theme89 files, Core21. Manifest: `artifacts/simpler-checkout-release-manifest.json`; logs `artifacts/checkout-*.log`. The existing actual WordPress 1200×900 theme screenshot is retained; theme source did not change.

Setup: [SIMPLER_LIVE_CHECKOUT_0.9.2.md](SIMPLER_LIVE_CHECKOUT_0.9.2.md). Remaining work is installation/configuration on the actual HTTPS site and the owner's controlled Live payment → webhook → VIP → protected-download acceptance, then cancellation/refund and PayPal acceptance. A push to GitHub is not proof of a WordPress deployment.

---

## Previous update — website-only checkout (2026-10-25)

Core **0.9.1** / theme **0.11.1**, `feature/site-only-checkout`, code checkpoint `068ff98`, based on published main `664333f`. New sales no longer route through the retired provider. Stored `legacy` routing resolves to **disabled**; historical URL options and paid access remain intact. Website prices always show €29.99/month and €80 once. Actual Test/Sandbox acceptance and Live approval are still pending.

Final focused verification: **553 PHP assertions** (checkout 28, presentation 38, facade 85, OAuth 127, protected downloads 69, member UI 56, SEO 22, stock migration 42, Discord billing 86); **16 responsive views +2 actual checkout routes +6 public pages**; source lint 72/static/JS; **96 fresh ZIP installation assertions**, WordPress 6.8.3/PHP 8.2. Five frozen migration inputs independently match the actual previous main source byte-for-byte; admin stock migration preserves custom edits, dates, publication status and legal metadata. The actual theme screenshot was recaptured at 1200×900 from local WordPress. ZIP content89 theme /20 Core is identical to source; manifest `artifacts/website-checkout-release-manifest.json`. All provider/Discord HTTP remains mocked in integration tests; no production deployment, charge or Discord/Ticket Tool change occurred.

See [Ticket Tool handoff](WEBSITE_CHECKOUT_TICKET_TOOL.md). The earlier 0.9.0 evidence below is historical; its legacy checkout and old price behavior has been retired by this update.

---

## Earlier 0.9.0 implementation evidence

2026-10-25 · Baseline: GitHub `main` at `7990e20` (Core 0.8.10 / theme 0.10.6). Latest main `ea566d4` (PR #11 documentation) was subsequently merged; work remains on `feature/direct-billing-test` with Core 0.9.0/theme 0.11.0. This report describes local implementation, not a production payment or security certification. Setup and external acceptance: [DIRECT_BILLING_SETUP.md](DIRECT_BILLING_SETUP.md).

## Delivered scope

- Six-table InnoDB ledger for immutable identity-bound orders, independent entitlements, idempotent notification queue, generation-aware role outbox, exact role ownership and serialized leases. UTC times and bounded diagnostics; raw webhook bodies and provider secrets are not recorded.
- Hosted Stripe Checkout, Billing and Customer Portal adapter with pinned API version `2024-06-20`; separate Test/Live configuration, exact EUR product/price/quantity checks, authenticated cancellation and canonical payment/refund/dispute reconciliation.
- PayPal Sandbox/Live adapters for Lifetime Checkout v2 capture and Monthly Subscriptions v1; remote signature verification, exact plan/price binding, canonical transactions and conservative paid-time calculation. PayPal activation depends on real Stripe acceptance confirmation.
- Immutable offer: Free €0, Monthly €29.99 recurring, Lifetime €80 once. Website-only checkout preserves the two existing checkout URLs and stays disabled until configured. Only exact untouched stock content is safely migrated; authored content is preserved for review.
- Nonce-protected checkout/account operations bound to the actual existing OAuth WordPress/Discord identity. Provider redirects never grant access. Pending/possibly retrying recurring contracts and unreviewed existing paid Discord roles prevent accidental duplicate purchases; cancellation preserves verified prepaid time.
- Exact two-role Discord REST synchronization with bot permission/hierarchy checks, independent entitlement union, scoped ownership and retries. Existing external roles and valid Upgrade.chat/giveaway rights are not silently adopted or deleted. Database failures after remote role writes report explicit retry/manual-review errors instead of false completion. Free, Owner, Staff and verification roles remain outside the bot's write scope.
- Signed ephemeral Discord `/buy`, `/membership`, `/manage`, `/link` handlers. Purchase links expire after ten minutes and require the same actor after website OAuth plus normal consent. Commands are registered manually; no gateway bot server is required. `/membership` privately reports the signed actor’s current ledger package or linking requirement, labels Test results, and points to current website access; manage/link direct the member to authenticated website details.
- English dark-theme account billing UI, order history, provider/paid dates, next payment, cancellation/manage actions, status feedback and synchronization state. Billing settings show readiness, bounded queue errors, safe retries and verified legacy/grant inventory tools.
- Mode-filtered, bounded server-cron integration (old Test work cannot starve Live) and extra ledger denial for explicitly reviewed managed membership access; existing current Discord-role checks and protected downloader remain authoritative. Owner publishing keeps the recent approved local-identity behavior.
- Default-off deployment gates: routing remains disabled, role writes/PayPal disabled, Test restricted to nonproduction/localhost; Live requires separate server-side approval, HTTPS, real test, migration and legal confirmations.

## Verification evidence

Local final evidence: **949 PHP assertions** (459 billing +490 existing regressions), **12 responsive browser views**, source lint 72, static/JS checks and whitespace checks passed. Billing split: Store 112, Stripe 66, PayPal 79, Discord 86, facade 83, presentation 33. Existing split: Checkout 14, OAuth 127, Downloads 69, Owner 169, Owner identity 20, Catalog 45, Shared VIP 24, SEO 22. Browser fixtures use actual WordPress PHP rendering with simulated providers, check 1440/768/390/320px, consent/focus and contained table scrolling; no provider navigation occurs. The final actual ZIPs passed **91 fresh-install assertions** on a separate disposable WordPress 6.8.3/PHP 8.2. Package installation result and SHA256 are in `artifacts/billing-release-installation.log` and `artifacts/direct-billing-release-manifest.json`; source contents89 theme / 20 Core match their ZIPs. Safe code checkpoints: `a6175c0`, `a0988d8`, `be2f391`, `ad539de`; merge `3de6024` preserves the latest main.

Added automated suites:

| File | Covered boundary |
| --- | --- |
| `tests/billing-store-integration.php` | Real WordPress database ledger, immutable bindings, deduplication, transaction rollback, leases/fencing, independent entitlements and expiry |
| `tests/billing-stripe-integration.php` | Isolated mocked Stripe API/signatures, strict identity/price/mode, recurring periods, refunds/disputes and checkout reuse |
| `tests/billing-paypal-integration.php` | Isolated mocked Sandbox API/signature verification, capture/subscription lifecycle, plan validation and reversal proof |
| `tests/billing-discord-integration.php` | Real WordPress plus mocked Discord transport and real Ed25519 verification; hierarchy/role ownership, outage/retry, cross-account intent protection |
| `tests/billing-integration.php` | Real WordPress orchestration across mocked provider and Discord boundaries, signed webhook queue, paid ledger/outbox, access constraint and failure handling |
| `tests/billing-presentation-integration.php` | Theme prices/CTA/SEO/schema and disabled/enabled website checkout presentation |

Mocked transport verifies implemented contracts and negative paths. It does **not** prove provider Test/Sandbox acceptance, delivery of a real external webhook, real Customer Portal settings, actual account tax configuration or real Discord role mutation. Source lint, relevant existing regression suites and package installation are separate checks; see the final progress entry for actual results.

## Deliberate limits and outstanding external work

1. No real Stripe/PayPal credentials were supplied to this environment and no real/Test/Sandbox purchases or production role changes were made. Run the setup document's real acceptance matrix on isolated HTTPS staging, Stripe first.
2. Official provider documentation/pricing requests were blocked by CONNECT 403 during this work. API compatibility, event availability and actual Polish merchant fees/FX/settlement must be checked against the account and current provider documentation before Live. No fabricated current fees are published.
3. Configure actual products/prices/plans, webhook IDs/secrets, Customer Portal, bot token/hierarchy, matching OAuth/role IDs, interactions public key/URL and manual slash-command registration. Verify server cron and cache exclusions on the actual hosting plan.
4. New website sales no longer route through Upgrade.chat. Earlier externally managed purchases and access are preserved. Legacy membership inventory, real end dates, customer consent, recurring-contract stop and role-writer ownership require an actual migration process. No existing subscription is automatically canceled/imported or externally managed role automatically taken over.
5. Finalize actual Polish seller/legal/VAT data, EUR settlement settings, tax-inclusive pricing, invoices/receipts, consumer/digital-content consent, refund/withdrawal and privacy documents. Automatic Tax is disabled; a checked legal-review box is not a tax implementation or legal opinion.
6. PayPal paid periods are conservatively derived from canonical sales and bounded calendar-month logic rather than trusting ACTIVE subscription status. Unproven reversals stay retryable/manual-review. Stripe payment history over the bounded invoice window fails closed for review; it is not silently guessed.
7. Admin queue diagnostics and retries are implemented; operational monitoring, provider-dashboard reconciliation, backup retention and an explicit audit/security review remain necessary before authorizing Live.

No production deployment is claimed. A GitHub commit/branch push is source publication, not proof that Hostinger is running this code.
