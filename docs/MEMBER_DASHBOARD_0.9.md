# Member dashboard — theme 0.9.0 / Core 0.7.0

The coldz.pro screenshot reference is now applied to the existing TimonConfigs member UI. The public header offers **Member login**; `/account/` presents a centered branded Discord sign-in card to guests and a welcome dashboard to signed-in members. The design uses the existing TimonConfigs logo, orange controls, blue accents and editable profile artwork.

The dashboard retains real server-derived membership status, avatar/name, Last Checked, refresh/reconnect, safe logout, recent downloads and membership cards. New quick actions open Configs, My Downloads, the profile banner and Support. My Downloads places the protected library before membership offers. Page content remains editable; the existing page title stays available to screen readers. No second account system, ticket system, payment implementation or role writer was added.

Only presentation changed in the Core member class. OAuth, role verification, private-file storage and signed-download services remain intact. OAuth is still disabled until configured; the login card clearly explains that state. Actual Discord consent and target-host cache behavior still require isolated HTTPS staging with the owner's credentials.

## Observed checks

- Real membership/rendering integration: **37 assertions**. Transport to Discord is mocked; the actual service derives Free, Monthly, Lifetime, unverified and API-error states. The suite checks escaped long owner names, missing avatars, reconnect and absence of tokens/storage paths.
- Existing OAuth and protected-download regressions: **60 + 59 assertions**, with provider transport mocked.
- HTTP browser checks: **12 guest/owner route and viewport cases**, including privacy headers, REST isolation, IDOR parameters, loaded branding and quick-action links; **4 intermediate-width header checks**.
- **18** layout checks for the actual PHP-rendered fixture states at 390/768/1440 px, including enabled guest sign-in and keyboard focus. These fixtures are not live Discord accounts.
- Existing storefront suite: **35 checks**, including responsive pages, inline video and keyboard controls. Native PHP lint, JavaScript/static checks and Git whitespace checks passed.
- Updated theme screenshot: actual local WordPress, **1200×900**, using the existing documented compact browser capture. Member screenshots and full Home captures are in ignored `artifacts/`.
- Final theme **0.9.0** (82 files) and Core **0.7.0** (12 files) ZIPs match the tested source byte-for-byte. Actual package installation passed **68 assertions** on a separate WordPress 6.8.3/PHP 8.2, preserving authored content/drafts and keeping unconfigured OAuth off. Disposable installation resources were removed. SHA-256 hashes are in `artifacts/dashboard-release-manifest.json`.

## Reproduce the focused checks

Run only against the disposable local WordPress. Fixtures restore their settings and remove their own account.

```bash
docker compose -f docker-compose.local.yml run --rm -T cli wp eval-file - < tests/member-ui-integration.php > artifacts/member-ui-render.json
node tests/member-ui-states-browser.cjs
node tests/member-area-browser.cjs
node tests/storefront-browser.cjs
```

Install the matching theme/Core ZIPs on staging, clear cached CSS and open `/account/` both signed out and signed in. Keep the exact existing Discord callback and role IDs. See `MEMBER_LIBRARY_0.8.md` for the unchanged real integration requirements and rollback procedure; `INSTALL_UPDATE.md` covers package installation. No Hostinger deployment or real provider login was performed in this stage.
