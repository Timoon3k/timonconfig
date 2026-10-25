# TimonConfigs legal publication checklist — review required before use

**Not legal advice, not an executable contract.** The repository currently seeds **draft** pages from TC_Site::legal_sections() when pages are empty. This file tells the owner exactly what must be verified and corrected in the LIVE WordPress pages. Editing the repo **does not modify existing WordPress policy text**. All final terms must be reviewed by an appropriate EU/Poland consumer law and privacy specialist. Do not insert invented seller identity, address, tax ID, company status or contact details.

## P0 — Missing or unclear mandatory information

[ ] **Seller/operator:** full legal name, business designation/type (sole trader, unregistered activity or legal person where legally permitted), official business address, valid contact email, support method, tax registration/VAT details and a consistent merchant-of-record at payment. Confirm eligibility to sell globally, contract and invoice. Do not list Upgrade.chat, Stripe or PayPal as the seller unless the actual contract says so.

[ ] **Price consistency:** production site, checkout and all policies show the SAME current actual prices incl. tax treatment. Repo fallback defaults still show Monthly €40 / Lifetime €100; the proposed €29.99 / €80 must NOT be advertised as live until the processor is configured to charge those values. Define gross/net, VAT and FX conditions on checkout.

[ ] **Provider roles:** state that Checkout for current new sales uses Upgrade.chat (when applicable) and payment processors connected there. Clarify what provider handles cancellation and billing, which entity issues invoices/receipts, how to contact support and which checkout terms take precedence.

[ ] **Scope, license and account:** describe Free, VIP Monthly, VIP Lifetime and actual included game/device configurations, private Discord access, requirements and compatibility. Explain whether access requires joining TimonConfigs Discord and authorization via OAuth2. No warranty of competitive advantage, future patches, game compatibility or platform policy compliance.

[ ] **Recurring billing:** before order say monthly price, renewal interval/date, automatic charge, cancellation controls, effective end of subscription and whether access remains until paid-through date. Failure of payment must not silently revoke other independent valid entitlements (e.g. Lifetime/grant). Explain provider's manage-subscription procedure.

[ ] **Digital delivery and statutory withdrawal:** DO NOT claim "no refunds under any circumstances." The withdrawal exception for immediate non-tangible digital content depends on express prior consent, acknowledgement of losing the right and confirmation on durable medium, under applicable rules. Mixed ongoing digital SERVICE vs digital CONTENT may need distinct analysis. Clarify complaint rights for nonconformity, duplicate payments, failed delivery and defects independently of discretionary refunds. See UOKiK: https://prawakonsumenta.uokik.gov.pl/prawo-odstapienia-od-umowy/umowy-szczegolne/

[ ] **Fairness:** identify what Lifetime means (specific offering and updates, duration of product/service, not unlimited future products), but do not use vague terms to strip legally protected rights or retroactively deny previously sold access. Legal review required.

[ ] **Disputes:** adequate contact & complaint route, jurisdiction information consistent with mandatory consumer rights. Do not link to obsolete European Commission ODR portal (closed July 20, 2025), see https://eur-lex.europa.eu/eli/reg/2024/3228/oj/eng.

## Terms of Service — exact headings/content to add or verify

1. Operator's complete identification and contact [REQUIRES REAL DATA].
2. Eligibility and independent/non-affiliation statement, platform-specific third-party device trademarks where relevant.
3. Definitions: Account / Discord ID / Config / Free / VIP Monthly / VIP Lifetime / License / Update / Payment Provider.
4. How to create an account, Discord OAuth scopes (identify, guilds.members.read as configured), which role determines access and what happens when Discord account is suspended/disconnected.
5. Current confirmed offer, compatibility caveats, live checkout link and full final prices; do not embed old hardcoded prices in legal pages without plan to update.
6. Payment receipt, how support handles delivery failure, binding of purchase to specific Discord identity.
7. Subscription renewal, cancellation, remaining paid-period rights, payment failure, migration from Upgrade.chat to own processing if implemented in future (no silent migration or double billing).
8. Personal-use license and clear redistribution limits; mandatory consumer rights survive.
9. Digital delivery / withdrawal and consent process, support and complaints.
10. Suspension only for specified proportionate reasons with contact/review process; refunds and paid access treated lawfully.
11. Law and dispute resolution without restricting mandatory cross-border consumer protections.
12. Effective date, version, advance notice for material changes, preserved copies of prior terms.

## Privacy Policy — real records required

[ ] Name and contact for actual data controller [REQUIRES REAL DATA].
[ ] Define purposes, legal bases and retention schedules by data class: Discord ID/avatar/guild roles, WordPress user and OAuth token, purchase receipt or subscription reference, download audit history, files/storage, security logs, contact tickets, analytics/cookies.
[ ] Identify real recipients and processors: Hostinger, Discord, Upgrade.chat, underlying processor (Stripe/PayPal if actually connected), analytics, delivery and support vendors. Explain cross-border transfers and safeguards, not claim that data stays only in EU unless verified.
[ ] OAuth scope/consent, session storage, expiry and revocation, user rights: access, rectification, erasure, objection, portability, complaints to supervisory authority, and deletion limitations where bookkeeping or abuse prevention require records.
[ ] Security and breach contact. Do NOT reveal internal storage paths or security secrets in public policy.
[ ] List contact details for exercising rights and response timeline, tailored to actual jurisdiction.

## Cookie Policy / consent implementation

Inventory **actual** cookies and tags using test browser and consent manager. At minimum classify WordPress login/session and security cookies, optional statistics, YouTube/media embeds and payment checkout redirect cookies. Show operator, purpose, retention, type, necessary/optional.
Do not activate optional analytics or marketing before valid consent if required. Reject should be as simple as accept; allow consent withdrawal at any time. Install tested consent management software only if needed, not just a static banner. AIOSEO and GSC domain verification themselves do not require an optional site analytics tracking pixel.

## Refund and cancellation policy

- State where to **cancel** Monthly (Upgrade.chat/current provider), when renewal stops, and the paid-through cutoff. Cancellation ≠ automatic refund or instantaneous revocation.
- Provide an actual functioning refund/technical support request channel with reference number and relevant dates.
- Distinguish voluntary refunds from non-waivable consumer-law remedies and statutory withdrawal.
- No promise "all digital items non-refundable" without counsel-approved consent and delivery process. Disclose how confirmation/consent evidence is retained.
- Handle disputed and duplicate charges without asking users to disclose card secrets.

## Lifetime terms

- Describe exactly which edition, devices, games, updates, support and Discord privileges are included. Say whether "Lifetime" means the current product/service's support lifetime; avoid ambiguous perpetual uptime guarantees.
- License attaches to the purchasing Discord account where applicable. State transfer policy, redistribution prohibition and compatibility/update limitations.
- Cannot promise all future games or future third-party product lines without actually delivering them. Respect acquired contractual rights.

## Site compliance and reviews

Customer reviews: publish only genuine feedback, get permission where necessary, disclose whether and HOW authenticity is verified, and do not suppress truthful negative feedback solely because unfavorable. Never mark internal demonstration reviews as real customers or issue fake Review/AggregateRating structured data. UOKiK: https://uokik.gov.pl/falszywe-opinie-stop ; https://developers.google.com/search/docs/appearance/structured-data/review-snippet

## Suggested notice while legal pages remain unapproved

Do not launch paid checkout to the general public with 'Draft policy' pages presented as definitive legal terms. Complete and validate them, then deliberately publish the finalized versions in WordPress. Repository default_content() only populates genuinely empty pages and will not overwrite user-edited legal text; therefore deploy code alone does not repair the current database.

## Info required from actual owner/legal reviewer

- Seller name and legal form, jurisdiction, postal address, customer support email, tax/VAT status.
- Exact current checkout product links/prices/tax and who takes payments.
- Refund/withdrawal consent flow and order confirmation content.
- Whether Lifetime includes future Apex seasons; exact sunset/service availability commitments.
- Real cookie/analytics/video processor inventory and data retention.
- Customer group age/regions, accepted currencies, invoicing process.

Relevant regulatory sources:
https://prawakonsumenta.uokik.gov.pl/prawo-odstapienia-od-umowy/umowy-szczegolne/
https://prawakonsumenta.uokik.gov.pl/prawo-do-informacji/sprzedaz-poza-lokalem-i-na-odleglosc/
https://eur-lex.europa.eu/eli/reg/2024/3228/oj/eng
