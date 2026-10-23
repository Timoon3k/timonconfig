# TimonConfigs — theme 0.10.0 / Core 0.8.0

Implementation of the 23 October feature brief and 24 October references A–E in the existing project. Branch: `feature/owner-catalog-guides-seo`, based on `main` at `c32fefd`. No production deployment, billing changes or Discord role writes.

## Delivered

- Verified Free/Monthly/Lifetime/Staff/Owner badges; known labels come from configured immutable role IDs. Paid access remains independent of Staff/Owner. Management requires a verified Owner role, administrator-approved Discord user ID and unique account binding. Restricted pilot stays under the existing administrator settings.
- Frontend Manage Configs: paginated drafts/releases, metadata/taxonomies, create/edit/publish/Trash, safe public preview images, public tutorial/preview URL, protected versioned files, replacement/deletion confirmation and audit. WordPress Administrator retains the backend fallback; Subscriber is not promoted. Every write checks a nonce and fresh server role verification; the existing 10-second provider throttle fails closed.
- Config catalog: 3/2/1 cards, inherited or featured cover, genuine creator/date/version/device/tier, Guide/Values controls and contextual download/membership actions. Upload Date / A–Z / Downloads use stable ordering. Atomic download counts measure completed server deliveries from this implementation onward; no historical counts are invented.
- Profile cleanup: removed My Downloads navigation/quick action and Recent downloads/listings. The old private URL offers a config-page link, while download history/audit, signed one-use routes and server authorization remain intact.
- Separate Guide/Values visibility, protected metadata, revisions, no-store nonce/content endpoints, native accessible dialog, Escape/focus lifecycle, exact Values copying and long/mobile content. Partial writes default missing visibility to Monthly; invalid content is rejected before a post update.
- Administrator-configurable subset of GPC/JSON/TXT/CFG/INI/ZNBK/XIMMR; CFG/INI are text only. Mandatory ZNBK/XIMMR inspection, private containment, quotas/checksums/locking and unsafe-format rejection remain. Public images are decoded/reencoded into JPEG separately from private scripts.
- Home H1/title/copy/default CTA and topical links; useful device sections on existing Apex/CoD collections. Authored Gutenberg/Customizer overrides and SEO provider ownership remain authoritative. Only the final community CTA loses its artwork; FAQ retains its background.
- Temporary `*.hostingersite.com` installations/aliases emit noindex, suppress sitemap advertising/entries and retain existing site URL settings. Production canonical redirects and Search Console still require host verification. No thin intent pages, fake firmware/settings or ranking guarantees were introduced.

## Verification

Local WordPress 6.8.3 / PHP 8.2 / MariaDB 11.4. Tests use disposable fixtures; Discord HTTP is mocked. The Owner browser fixture uses a temporary loopback TLS proxy and scoped fixture login to obtain real WordPress session cookies; it does not test Discord consent. Its MU plugin, keys, users, private bytes and attachments are removed. No production accounts are used.

| Integration suite | Assertions |
| --- | ---: |
| Discord roles/OAuth | 122 |
| Owner editor | 211 |
| Guide/Values | 47 |
| Private format policy / files / downloads | 28 / 65 / 59 |
| Delivery aggregation / member catalog | 16 / 24 |
| Member UI | 43 |
| Home focus / temporary host | 21 / 20 |
| Existing catalog / SEO / video | 42 / 22 / 24 |
| Installer / blocks / editorial / checkout / setup notice | 87 / 14 / 17 / 14 / 15 |
| **Total integration** | **891** |

Actual browser checks passed: Owner forms 18, Guide/Values 27, private multipart 34, member states 18 layouts, member HTTP 12 cases plus 4 header widths and 8 logout/session assertions, Home/collection focus 9 cases, storefront 35 viewport/routes, full website 55 viewport/routes, Home regression 10 cases, plus real byte/replay/descriptor mutation delivery and inline/optional video behavior. PHP lint, JS/static checks and `git diff --check` passed.

Fresh installation of the exact theme/Core ZIPs on a separate offline WordPress passed **76 assertions**. Archives have 87 theme files and 14 Core files and match source bytes. Manifest/checksums and concise logs are in ignored `artifacts/feature-release-manifest.json` and `artifacts/feature-release-installation.log`.

The theme screenshot is a real WordPress 1200×900 PNG. Browser-only compact preview styling fits Header/Hero/Pricing/Footer; full unmodified Home captures are retained. Other screenshots include `artifacts/owner-editor-{1440,390}.png`, `config-values-{1440,768,390}.png`, `home-solid-cta-{1440,768,390}.png`, and member/catalog views. A previous real theme screenshot is preserved as `artifacts/theme-before-0.9.0.png`; it is a historical capture, not a fresh capture of the current environment. The A–E image binaries remain absent; visual review uses their supplied textual mapping and actual local renders, not a claimed pixel-perfect comparison.

## Required owner/host steps before production

1. Back up database, WordPress files and matching private storage. Review these packages on isolated HTTPS staging. No Node production server is required.
2. Preserve existing OAuth/payment settings. Configure real distinct Staff/Owner role IDs and approved Owner Discord user IDs. Verify only the correct Owner sees Manage Configs; test a separate Staff account with a valid nonce. Keep Restricted pilot enabled.
3. Verify actual Free/Monthly/Lifetime and Owner/Staff role loss, reconnect/logout, token expiry, API failure and cache behavior on the real host. The owner previously demonstrated Free login; that does not establish real VIP authorization or complete staging acceptance.
4. Confirm private storage is outside every public root, PHP owner permissions, OpenSSL/fileinfo/image support/upload limits, backups, actual approved proprietary-format inspector and allowed file formats. Test harmless files first. Never bypass the scanner or move premium files into Media Library.
5. Exclude account/member-management, admin-post content/download/write actions, OAuth and download query routes from LiteSpeed/CDN caches and purge prior copies. Test signed link replay/expiry/wrong-file and logged-out denial on the actual stack. A cache serving responses before WordPress must be configured separately.
6. Set the intended canonical domain and HTTPS in hosting/WordPress deliberately, confirm exact Discord callback, and verify temporary aliases/301s. The code does not migrate URLs or DNS. Configure the authoritative SEO plugin's own editable titles/descriptions where it overrides theme defaults. Measure Search Console baseline and field performance; see `SEARCH_INTENT_HANDOFF_2026-10-24.md`.
7. Supply original screenshots A–E for exact visual comparison, real release/compatibility/creator details, verified Apex video mappings, reviewed legal content and actual existing Upgrade.chat product destinations. Do not publish draft scripts or fake data to fill cards.
8. Obtain explicit owner approval before a production deployment. Install plugin/theme together, run explicit setup only if required pages/terms are missing, save permalinks and clear cache. Never replace the live database with a staging copy.

Rollback: disable OAuth if access is suspect, restore prior matching theme/Core packages and settings from backup on staging first. Do not unprotect or delete private storage. Restore database/private files together when reversing file replacement/deletion. Config-specific administrator capabilities may remain harmlessly recorded after rollback; review them before any later permission migration. Keep current payment/role automation intact.

Public HEAD probes of the canonical and preview hosts on 24 October were rejected by the execution proxy during CONNECT (curl 56 / proxy 403). These are not observed website HTTP statuses; remote canonical/indexing/deployment remains unverified.
