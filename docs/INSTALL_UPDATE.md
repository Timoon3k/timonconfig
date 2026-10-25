# Installation and update — theme 0.10.5 / Core 0.8.6

Current release: **Core 0.9.1 / theme 0.11.1**, website-only checkout, disabled by default. See [Ticket Tool links](WEBSITE_CHECKOUT_TICKET_TOOL.md) and [current billing configuration](DIRECT_BILLING_SETUP.md) before testing.

## Environment requirements

- WordPress 6.5+ with PHP 8.1+; local verification uses WordPress 6.8.3/PHP 8.2/MariaDB 11.4.
- Standard WordPress permalinks and writable uploads for public artwork only.
- No production Node server, WooCommerce installation, or parallel payment processor is required.
- For the new member library, also follow `MEMBER_LIBRARY_0.8.md`: OpenSSL/fileinfo, private storage outside all public roots, actual Discord configuration, cache exclusions, and real HTTPS staging tests. OAuth starts disabled.
- Configure the website payment products and private credentials using [direct billing setup](DIRECT_BILLING_SETUP.md), and provide a working support email, the current Discord invite, owner-approved legal identity/terms, and original brand assets.
- For future Discord sign-in, obtain real client/guild/role configuration separately; do not place secrets in the theme, plugin ZIP, public uploads, or repository.

## Safe update process

1. Back up the target WordPress files, database, and existing theme/plugin settings. The local backup made for this release is private and excluded from the repository and distributions; it is not a production backup.
2. Use an isolated staging site. Upload the Core and theme ZIPs through WordPress; replace only these two components. Preserve uploads, database rows, checkout settings, roles, and other active plugins.
3. Activate **TimonConfigs Core**, then **TimonConfigs**. Existing config content uses the unchanged `tc_config` identifier and does not require a destructive migration.
4. Open **Tools → TimonConfigs setup** and run the explicit action. It fills missing/empty content without replacing authored pages, statuses, or assigned menus, prepares collections/draft structures, and refreshes rewrites.
5. Confirm **Settings → Reading** uses the intended Home and inspect **Appearance → Menus**. Existing menu assignments remain authoritative. Set normal pretty permalinks if not already enabled.
6. Follow [website billing setup](DIRECT_BILLING_SETUP.md) on isolated HTTPS staging. **Settings → TimonConfigs** supplies read-only Ticket Tool links; payment settings are separate under **TimonConfigs billing**. Old external destinations are ignored. Preserve earlier contracts and access; do not perform real purchases during setup.
7. Review all 25 routes in `URL_STATUS.md`, configured Discord buttons, filters, editor previews, media playback, indexing, sitemap, and supported SEO-plugin behavior. Replace demo reviews and unpublished release drafts only with verified content.
8. Review the policy drafts with the owner before production use; confirm entity, contact information, Lifetime scope, cancellation, refunds, privacy, and storage disclosures. Setup preserves WordPress's existing draft Privacy Policy: publish approved legal pages deliberately before expecting their public URLs to respond.
9. Purge the target site's page/CDN cache after a staging update so CSS/JS version 0.10.5 and the artwork are served together. Compare screenshots with the approved reference. If Home showed the default welcome post, follow the targeted steps in `HOME_FIX_0.7.1.md`. An update of an already active Core still needs the explicit setup to create missing member pages; no existing content is overwritten.
10. Production deployment requires the owner's explicit approval after staging validation. This session did not modify Hostinger production or any live payment/membership state.

## Current external limitations

The current ZIPs have passed an isolated fresh local installation and activation with 76 assertions. Reproduce that check with `bash tests/release-installation.sh`; it uses only cached images and its own temporary internal Docker network, WordPress filesystem, and database, then cleans them up. This does not replace target-host staging checks with the actual plugin stack and owner-approved content.

The environment proxy blocked read-only requests to timonconfigs.com and the Discord invitation API. This is not an observed 404 from the website. Required domains were added to the cloud environment draft; review/save/publication is needed before retrying access. No callable Hostinger hosting/WordPress tools were exposed in this session, so account state and remote backups could not be inspected.

Original logo/Hero sources and confirmed clip-to-device metadata remain owner inputs. OAuth2, live guild-role authorization and protected delivery have local integration coverage; actual provider consent, target-host role changes and delivery still require real-account validation.

## Historical Core 0.7.1 — private format policy

Superseded for ZNBK by the explicit owner-approved automatic checks in Core 0.8.5+. XIMMR still requires its inspector.

ZNBK and XIMMR require explicit approval from the existing file inspector even when their bytes are text-encoded. This applies to new uploads and existing-file resolution before download. Existing files are preserved, but these formats cannot be delivered without the inspector. GPC, JSON and TXT retain their structural checks. Configure and verify the actual inspector on isolated staging; never install an unconditional approval callback as a production scanner.

## Core 0.7.2 — restricted Discord pilot

Optional administrator-configured Discord identity allowlist permits controlled HTTPS testing without claiming completed staging tests. Existing credentials are preserved. See [pilot instructions](DISCORD_PILOT_0.7.2.md) before enabling; public production access retains the staging-confirmation gate.

Core 0.7.3 adds administrator-only, redacted callback failure diagnostics. Existing credentials and pilot settings are preserved. See the troubleshooting section in `DISCORD_PILOT_0.7.2.md`.

For Owner/Staff settings, the frontend editor, protected Guides/Values, permitted formats and preview-domain indexing protection, follow `FEATURE_RELEASE_0.10.md` and the current section of `CMS_GUIDE.md`. These packages do not configure real roles, scanners, storage or production URLs automatically. Keep Restricted pilot enabled during target-host validation.

Current upload/access policy: `UPLOAD_AND_SHARED_VIP_0.8.5.md`. Mandatory external approval was removed for ZNBK at the explicit owner request; older inspector instructions apply only to historical versions or XIMMR. Install both current packages to receive the compact form and automatic cooldown handling.

Historical recovery update (superseded by current releases): [Core 0.8.6 / theme 0.10.5](OWNER_UPLOAD_RECOVERY_0.8.6.md). Install both packages; preserve existing private storage, Discord identity and earlier purchase records.
