# Installation and update — theme 0.7.0 / Core 0.5.0

## Environment requirements

- WordPress 6.5+ with PHP 8.1+; local verification uses WordPress 6.8.3/PHP 8.2/MariaDB 11.4.
- Standard WordPress permalinks and writable uploads for public artwork only.
- No production Node server, WooCommerce installation, or parallel payment processor is required.
- Provide the real Monthly/Lifetime Upgrade.chat checkout URLs, a working support email, the current Discord invite, owner-approved legal identity/terms, and original brand assets.
- For future Discord sign-in, obtain real client/guild/role configuration separately; do not place secrets in the theme, plugin ZIP, public uploads, or repository.

## Safe update process

1. Back up the target WordPress files, database, and existing theme/plugin settings. The local backup made for this release is private and excluded from the repository and distributions; it is not a production backup.
2. Use an isolated staging site. Upload the Core and theme ZIPs through WordPress; replace only these two components. Preserve uploads, database rows, checkout settings, roles, and other active plugins.
3. Activate **TimonConfigs Core**, then **TimonConfigs**. Existing config content uses the unchanged `tc_config` identifier and does not require a destructive migration.
4. Open **Tools → TimonConfigs setup** and run the explicit action. It fills missing/empty content without replacing authored pages, statuses, or assigned menus, prepares collections/draft structures, and refreshes rewrites.
5. Confirm **Settings → Reading** uses the intended Home and inspect **Appearance → Menus**. Existing menu assignments remain authoritative. Set normal pretty permalinks if not already enabled.
6. Enter actual Upgrade.chat URLs under **Settings → TimonConfigs**. Keep existing Stripe/PayPal products and Discord role automation unchanged. Test the redirect destination without completing a transaction.
7. Review all 25 routes in `URL_STATUS.md`, configured Discord buttons, filters, editor previews, media playback, indexing, sitemap, and supported SEO-plugin behavior. Replace demo reviews and unpublished release drafts only with verified content.
8. Review the policy drafts with the owner before production use; confirm entity, contact information, Lifetime scope, cancellation, refunds, privacy, and storage disclosures.
9. Purge the target site's page/CDN cache after a staging update so CSS/JS version 0.7.0 and the new artwork are served together. Compare screenshots with the approved reference.
10. Production deployment requires the owner's explicit approval after staging validation. This session did not modify Hostinger production or any live payment/membership state.

## Current external limitations

The environment proxy blocked read-only requests to timonconfigs.com and the Discord invitation API. This is not an observed 404 from the website. Required domains were added to the cloud environment draft; review/save/publication is needed before retrying access. No callable Hostinger hosting/WordPress tools were exposed in this session, so account state and remote backups could not be inspected.

Original logo/Hero sources and confirmed clip-to-device metadata remain owner inputs. OAuth2, live guild-role authorization, and protected premium file delivery remain unimplemented and must be tested separately before they are described as operational.
