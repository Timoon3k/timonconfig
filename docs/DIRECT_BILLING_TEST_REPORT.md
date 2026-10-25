# Direct billing — implementation report

2026-10-25 · Baseline: GitHub `main` at `7990e20` (Core 0.8.10 / theme 0.10.6). Work continues on `feature/direct-billing-test`. This report describes local implementation, not a production payment or security certification. Setup and external acceptance: [DIRECT_BILLING_SETUP.md](DIRECT_BILLING_SETUP.md).

## Delivered scope

- Six-table InnoDB ledger for immutable identity-bound orders, independent entitlements, idempotent notification queue, generation-aware role outbox, exact role ownership and serialized leases. UTC times and bounded diagnostics; raw webhook bodies and provider secrets are not recorded.
- Hosted Stripe Checkout, Billing and Customer Portal adapter with pinned API version `2024-06-20`; separate Test/Live configuration, exact EUR product/price/quantity checks, authenticated cancellation and canonical payment/refund/dispute reconciliation.
- PayPal Sandbox/Live adapters for Lifetime Checkout v2 capture and Monthly Subscriptions v1; remote signature verification, exact plan/price binding, canonical transactions and conservative paid-time calculation. PayPal activation depends on real Stripe acceptance confirmation.
- Immutable new offer: Free €0, Monthly €29.99 recurring, Lifetime €80 once. Native checkout preserves the existing two checkout URLs. Legacy routing and €40/€100 display stay active until the controlled cutover; stored content is not rewritten automatically.
- Nonce-protected checkout/account operations bound to the actual existing OAuth WordPress/Discord identity. Provider redirects never grant access. Pending/possibly retrying recurring contracts and unreviewed existing paid Discord roles prevent accidental duplicate purchases; cancellation preserves verified prepaid time.
- Exact two-role Discord REST synchronization with bot permission/hierarchy checks, independent entitlement union, scoped ownership and retries. Existing external roles and valid Upgrade.chat/giveaway rights are not silently adopted or deleted. Database failures after remote role writes report explicit retry/manual-review errors instead of false completion. Free, Owner, Staff and verification roles remain outside the bot's write scope.
- Signed ephemeral Discord `/buy`, `/membership`, `/manage`, `/link` handlers. Purchase links expire after ten minutes and require the same actor after website OAuth plus normal consent. Commands are registered manually; no gateway bot server is required. `/membership` privately reports the signed actor’s current ledger package or linking requirement, labels Test results, and points to current website access; manage/link direct the member to authenticated website details.
- English dark-theme account billing UI, order history, provider/paid dates, next payment, cancellation/manage actions, status feedback and synchronization state. Billing settings show readiness, bounded queue errors, safe retries and verified legacy/grant inventory tools.
- Mode-filtered, bounded server-cron integration (old Test work cannot starve Live) and extra ledger denial for explicitly reviewed managed membership access; existing current Discord-role checks and protected downloader remain authoritative. Owner publishing keeps the recent approved local-identity behavior.
- Default-off deployment gates: routing remains legacy, role writes/PayPal disabled, Test restricted to nonproduction/localhost; Live requires separate server-side approval, HTTPS, real test, migration and legal confirmations.

## Verification evidence

The exact final assertion counts, browser evidence, package versions and commit IDs are recorded by the coordinating agent in `PROGRESS.md` / `NEXT_SESSION.md` when the release checkpoint is complete. Do not infer that a suite passed merely because the test file exists.

Added automated suites:

| File | Covered boundary |
| --- | --- |
| `tests/billing-store-integration.php` | Real WordPress database ledger, immutable bindings, deduplication, transaction rollback, leases/fencing, independent entitlements and expiry |
| `tests/billing-stripe-integration.php` | Isolated mocked Stripe API/signatures, strict identity/price/mode, recurring periods, refunds/disputes and checkout reuse |
| `tests/billing-paypal-integration.php` | Isolated mocked Sandbox API/signature verification, capture/subscription lifecycle, plan validation and reversal proof |
| `tests/billing-discord-integration.php` | Real WordPress plus mocked Discord transport and real Ed25519 verification; hierarchy/role ownership, outage/retry, cross-account intent protection |
| `tests/billing-integration.php` | Real WordPress orchestration across mocked provider and Discord boundaries, signed webhook queue, paid ledger/outbox, access constraint and failure handling |
| `tests/billing-presentation-integration.php` | Theme prices/CTA/SEO/schema and legacy/native presentation through controlled billing configuration |

Mocked transport verifies implemented contracts and negative paths. It does **not** prove provider Test/Sandbox acceptance, delivery of a real external webhook, real Customer Portal settings, actual account tax configuration or real Discord role mutation. Source lint, relevant existing regression suites and package installation are separate checks; see the final progress entry for actual results.

## Deliberate limits and outstanding external work

1. No real Stripe/PayPal credentials were supplied to this environment and no real/Test/Sandbox purchases or production role changes were made. Run the setup document's real acceptance matrix on isolated HTTPS staging, Stripe first.
2. Official provider documentation/pricing requests were blocked by CONNECT 403 during this work. API compatibility, event availability and actual Polish merchant fees/FX/settlement must be checked against the account and current provider documentation before Live. No fabricated current fees are published.
3. Configure actual products/prices/plans, webhook IDs/secrets, Customer Portal, bot token/hierarchy, matching OAuth/role IDs, interactions public key/URL and manual slash-command registration. Verify server cron and cache exclusions on the actual hosting plan.
4. The module preserves Upgrade.chat operation until an explicitly approved cutover. Legacy membership inventory, real end dates, customer consent, recurring-contract stop and role-writer ownership require an actual migration process. No existing subscription is automatically canceled/imported or externally managed role automatically taken over.
5. Finalize actual Polish seller/legal/VAT data, EUR settlement settings, tax-inclusive pricing, invoices/receipts, consumer/digital-content consent, refund/withdrawal and privacy documents. Automatic Tax is disabled; a checked legal-review box is not a tax implementation or legal opinion.
6. PayPal paid periods are conservatively derived from canonical sales and bounded calendar-month logic rather than trusting ACTIVE subscription status. Unproven reversals stay retryable/manual-review. Stripe payment history over the bounded invoice window fails closed for review; it is not silently guessed.
7. Admin queue diagnostics and retries are implemented; operational monitoring, provider-dashboard reconciliation, backup retention and an explicit audit/security review remain necessary before authorizing Live.

No production deployment is claimed. A GitHub commit/branch push is source publication, not proof that Hostinger is running this code.
