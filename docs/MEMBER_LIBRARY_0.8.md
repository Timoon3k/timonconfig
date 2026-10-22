# Member library handoff — theme 0.8.0 / Core 0.6.0

## Implemented without replacing the existing project

The existing Config CPT, game/device/platform collections, Gutenberg sections, premium design and validated Upgrade.chat links are preserved. The preliminary PR #1 private-file class was reviewed and hardened in place. See `PR_1_REVIEW.md` for findings and the adjacent added-file manifest for exact new paths.

- Multiple separate script posts per game, publisher/creator/release metadata, allowlisted sorting, pagination, responsive cards, inherited cover art and context-dependent Log in / Download / Browse Memberships actions.
- Guarded multipart private uploads with content/type/byte checks, quotas, filesystem write locking, version/notes/order, confirmed replace/delete and append-only file-operation audit records. No ZIP/executable delivery. Unknown binary formats require a configured inspector.
- Authorization-code Discord OAuth, single-use state, secure host-only cookie, optional verified PKCE, encrypted tokens, unique identity binding, session rotation/logout, refresh-token concurrency checks and read-only guild-role verification. OAuth is disabled by default.
- Owner-only Profile/My Downloads, membership cards, Last Checked, Refresh Roles, reconnect, recent delivery history and membership/support navigation. Member pages are noindex, excluded from sitemaps and sent private/no-store; REST rendering returns a neutral placeholder.
- Sixty-second signed access bound to the actual WordPress login session/blog/user/config/file, atomic one-use claim, tier/release checks, safe resolution and SHA-256 verification of the opened descriptor before streaming. Role freshness is bounded to 60 seconds; revocation takes effect on refreshed/expired verification, rather than promising instant Discord propagation.

New accounts use opaque WordPress usernames so a Discord snowflake is not exposed through an author slug. Existing WordPress accounts are not automatically attached to a new Discord identity. Private access has no administrator bypass and does not trust a checkout return parameter.

## Required real configuration

1. Isolated HTTPS staging WordPress with `WP_ENVIRONMENT_TYPE` = `staging`, PHP 8.1+ with OpenSSL/fileinfo, and the owner's actual plugin stack. No Node server is needed in production.
2. Private storage path outside **every** public document root, writable only by the PHP owner. Confirm hosting open_basedir/storage/backup constraints and actual PHP upload/post limits. Back up both this directory and the database together.
3. Discord application client ID/secret, actual guild ID, actual Free Member/VIP Monthly/VIP Lifetime role IDs, and the exact callback `home_url('/?tc_discord_callback=1')` displayed in Settings. Do not paste secrets into repository files or public uploads.
4. Optional binary format/malware scanner integration, support email, original brand assets and reviewed license/privacy/refund text. No scanner service or production credentials were invented.
5. Actual Monthly/Lifetime Upgrade.chat product URLs in the existing settings. Browse Memberships links to Pricing and the existing handoff; no new checkout, webhook, subscription or role writer exists.

On staging, enable the configured OAuth application to run real-account consent/login/refresh/logout and role-removal tests. Production additionally requires the explicit confirmation of completed real staging tests. PKCE transport is tested with mocked responses; provider support and real consent are still owner/staging checks.

Exclude `/account/`, `/my-downloads/`, admin-post download actions, `tc_download` and all `tc_discord_*` query routes from page/CDN caching. The code emits no-store, but a cache that bypasses WordPress or ignores headers must be configured and verified on the target host. Keep optional tracking disabled until the final cookie/privacy configuration is reviewed.

## Local verification

Tests use only disposable local accounts/configs/files and mocked Discord transport. They do not make purchases, read real customer data, or grant Discord roles. The focused stream test exercises actual PHP delivery bytes and rejects a same-size descriptor mutation. Real multipart tests upload through WordPress HTTP forms. Existing catalogue, SEO, media, installer, blocks, editorial and checkout-validation suites remain in use.

The WordPress screenshot at `wp-content/themes/timonconfigs/screenshot.png` is exactly 1200x900. It is captured from real local WordPress DOM/assets with browser-only compact styling for Header/Hero/Pricing/Footer; it is not generated wallpaper. The untouched 1440/390 full-page captures and capture metadata are under ignored artifacts. It is not a production capture.

Run setup with `bash tools/setup-local-wordpress.sh`. Read `PROGRESS.md` for observed assertion/browser counts and release checks. `tests/member-catalog-integration.php` uses isolated service doubles for presentation; run it with `--skip-plugins=timonconfigs-core`, then flush rewrites with the real plugin loaded if necessary. Other authorization suites exercise the real classes with only the external Discord HTTP provider mocked.

Observed final checks: 424 distinct PHP integration assertions, 30 real multipart assertions, 8 member browser cases, 35 existing storefront cases and 55 full-site cases passed. Actual delivery-byte/replay/descriptor-mutation tests also passed. Both final ZIPs match source byte-for-byte and passed 66 fresh-install assertions on an isolated WordPress 6.8.3/PHP 8.2, including idempotent setup and disabled/unconfigured OAuth. The local setup helper checked 27 routes; native PHP lint, JavaScript/static checks and Git whitespace checks passed. These results cover local fixtures, not live Discord credentials, payment transactions or the remote hosting stack.

## Installation, review and rollback

Back up the target database, WordPress files, private directory and settings. Install both tested ZIPs on isolated staging, activate Core then the theme, run Tools → TimonConfigs setup, save permalinks and clear page/CDN caches. Confirm the two member pages and all existing authored content. Do not publish draft releases or legal drafts automatically.

Before production approval, test actual Discord Free/Monthly/Lifetime/unverified users, revoked roles, token/session expiry, cache bypass, downloads, scanner policy, current checkout destinations, and HTTPS/cookie behavior with the actual hosting stack. Public website/Hostinger inspection remains blocked in this environment; local results do not prove remote deployment. No production operation was performed.

To roll back, disable OAuth, restore the prior theme/Core packages and relevant settings/database backup on staging first. Keep private storage outside the web root; never move it into uploads as a fallback. This release performs no destructive post-type rename, role changes or billing migration. Member pages/metadata may remain safely backed up until the owner decides their retention. Restoring a database must use the matching private-file backup because confirmed file replacements/deletions affect disk contents.
