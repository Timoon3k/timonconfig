# Legal publication — current release, 2026-10-26

The owner's explicit instruction to publish completed policies supersedes the earlier draft-only workflow. Core 0.9.8 / theme 0.11.3 provide three English documents, published in WordPress as normal Gutenberg pages:

- `/terms-of-service/` includes the Lifetime license at `#lifetime-license`.
- `/privacy-policy/` includes the cookie inventory at `#cookies`.
- `/refund-policy/` covers withdrawal, non-conformity, complaints, refunds and cancellation.

The old Cookie Policy and Lifetime License Terms pages are reversibly moved to Trash after their content and menu references are archived. Their public GET/HEAD URLs redirect permanently to the corresponding sections. Existing canonical page IDs and unrelated footer links are preserved.

## Facts supplied by the owner

- Brand: TimonConfigs. Seller and data controller: Tomasz Majewski.
- Business form: unregistered business activity in Poland.
- Final customer prices: €29.99 monthly / €80 once, in EUR, including any tax the seller must account for. No tax exemption, NIP, VAT registration or fictitious company details have been invented.
- Correspondence and complaints address supplied by the owner: TimonConfigs, ul. Dąbrowica 21, 05-326 Poświetne, Poland. Spelling is retained as supplied.
- Public contact, complaints and privacy email supplied by the owner: contact@timonconfigs.com. Version 0.9.8 replaces the empty fields stored by 0.9.6 and republishes all three documents; the previous missing-contact limitation is resolved in the supplied text. Postal deliverability, mailbox operation and overall legal compliance have not been independently verified.

## How this release applies

Update both packages and open the WordPress dashboard as an administrator. The owner-authorized, one-time `admin_init` migration replaces the existing legal bodies, publishes them and consolidates the duplicate pages. It does not run from an anonymous page view. Billing settings, OAuth configuration, customer orders and Discord roles are not changed.

**Settings → Legal documents** subsequently edits supplied seller details and republishes the policies. Original page content, status, metadata and affected menu references are saved in non-autoloaded private `_tc_legal_archive_*` options before writes. Include the database in the normal pre-update backup. If a write fails, the completion marker is not set and the next administrator request retries; this is an archived multi-step CMS update, not an atomic transaction.

## Remaining operational checks

- Verify that the supplied postal address and email are accurate and that mail/support actually reaches the seller. Do not assume a brand replaces the legal seller.
- Verify prices and tax/accounting obligations for the actual Polish activity and destinations sold to. Confirm current provider totals match the published offer.
- Test the deployed privacy/cookie inventory against actual hosting, consent software, analytics and embeds. The custom theme has no built-in analytics, but hosting plugins may differ. No optional tracking is authorized by this publication.
- The documents preserve the applicable 14-day consumer withdrawal right. Current checkout does not implement a separate immediate-delivery waiver and durable-medium confirmation; do not claim that downloading automatically removes statutory rights.
- Verify payment confirmation, ongoing billing, cancellation and protected delivery on the installed release. Original purchases retain their applicable terms and independent entitlements; no contract or subscription is silently migrated.
- Consider an appropriate Polish/EU consumer-law and privacy review. The texts were written for the current implementation and supplied facts, not copied from coldz.pro. Official source pages and Coldz were unavailable to this environment, so no current external legal-source verification is claimed.

Test evidence and installation instructions: [LEGAL_CONTACT_UPDATE_0.9.7.md](LEGAL_CONTACT_UPDATE_0.9.7.md), [LEGAL_PUBLICATION_0.9.6.md](LEGAL_PUBLICATION_0.9.6.md). A GitHub push is not proof that Hostinger installed the packages.
