# TimonConfigs WordPress website

Custom WordPress theme and business plugin for `timonconfigs.com`.

## Components

- `wp-content/themes/timonconfigs` — premium marketing theme with Home, Pricing, Games, VIP plan landings, video showcase, FAQ, Contact, policy pages, config catalogue templates, block patterns, responsive navigation, and SEO metadata.
- `wp-content/plugins/timonconfigs-core` — existing Upgrade.chat redirects, editable catalogue/CMS/SEO, private multi-file management, disabled-by-default Discord OAuth/role checks, member pages and protected session-bound downloads.

The production site needs WordPress 6.5+ and PHP 8.1+. No Node.js server is required at runtime.

## Local installation

### Docker (recommended)

With Docker available, run:

```bash
bash tools/setup-local-wordpress.sh
```

This starts WordPress 6.8.3 with PHP 8.2 and MariaDB 11.4 on port `8080`, activates the theme and plugin, and creates the storefront, support, video, and policy pages plus navigation. HTTP checks explicitly skip existing unpublished editorial pages, including WordPress's default draft Privacy Policy; other route errors fail setup. The disposable local administrator is `timonadmin` with password `local-preview-only`. These credentials are local-only and must never be reused on staging or production.

Stop the services without deleting their database:

```bash
docker compose -f docker-compose.local.yml down
```

### Existing WordPress installation

1. Install WordPress in a local or staging environment.
2. Copy both directories into the matching `wp-content` directories.
3. Activate **TimonConfigs Core**, then activate **TimonConfigs**.
4. Open **Tools → TimonConfigs setup** and run the explicit installer. It creates missing pages, fills genuinely empty content with native blocks, seeds seven game collections and three unpublished Apex draft structures, and refreshes permalinks. Existing authored content, statuses, and assigned menus are preserved.
5. Set a static front page in **Settings → Reading**.
6. Create and assign Primary and Footer menus in **Appearance → Menus**.
7. Enter the real product checkout URLs under **Settings → TimonConfigs**. Only HTTPS links on `upgrade.chat` or its subdomains are accepted.
8. Edit **Pages → Home** to reorder its ten native TimonConfigs sections or change their heading/hero/benefit content. Shared prices, links, branding, and fallbacks remain in **Appearance → Customize → TimonConfigs site content**.
9. Manage collections under **Configs → Games / Devices / Platforms**, and add release details in the Config Gutenberg editor. Keep unverified releases as drafts; empty/coming-soon collections are noindex and excluded from the core sitemap.
10. Add FAQs, videos, and approved reviews in their dedicated Gutenberg menus. Every sample review is visibly labelled Demo Content and excluded from rating schema. Videos support native MP4/VTT and click-to-load YouTube, with inline playback by default and an optional per-entry modal.
11. Edit page and collection search/social fields in the **Search and social metadata** panel. If AIOSEO or another supported SEO provider is active, use that provider's title/social settings; the theme suppresses its own duplicates.

Do not put Discord OAuth secrets, tokens, or premium downloads in the theme, Media Library, repository, or public web root.

## Validation

Run:

```bash
bash tests/verify-project.sh
find wp-content -name '*.php' -print0 | xargs -0 -n1 php -l
```

The second command requires PHP CLI. A full WordPress staging smoke test remains required before deployment.


## Visual reference and inline media (theme 0.9.0 / Core 0.7.1)

The storefront follows the supplied mockup composition while retaining the real offer and omitting unverified statistics and invented reviews. The recreated wordmark and seven game covers are replaceable; use **Appearance → Customize** to provide final licensed production assets. Set the official logo under Site Identity when available.

Home and Video Showcase share player cards. MP4 downloads and YouTube embeds begin on explicit interaction. Playback remains within the card by default; a video editor may choose the optional accessible modal. Public showcase videos are not premium download storage.

If the homepage shows WordPress's welcome post after installing the packages, activate **TimonConfigs Core**, run **Tools → TimonConfigs setup**, then select the intended published Home under **Settings → Reading → A static page**. Assign Primary/Footer menus and clear the host's page cache. Theme 0.7.1 also renders the premium storefront when Reading still uses latest posts; an administrator-only notice explains any remaining setup. Authored static Home content and a separate posts page remain supported. See [homepage fix](docs/HOME_FIX_0.7.1.md).

Fresh installations show six compact review-layout samples. Every sample is visibly labelled **Sample Review · Demo Content** and is never emitted as review structured data. Add approved feedback under **Reviews** with Gutenberg, a featured image, rating, config label, demo status, publication state, and menu order. Hero and pricing starter patterns are available in the block inserter together with the CTA and FAQ patterns.

Run the browser checks against the Docker WordPress with Playwright and Chromium available:

```bash
node tests/storefront-browser.cjs
```

Set `TC_TEST_URL` for a staging URL and `CHROMIUM_PATH` for another Chromium executable. Tests interact with search, menus, FAQ, and the public video player; they do not purchase products or modify memberships.

Run `node tools/capture-storefront.cjs` to capture the current Home at 1440, 1024, 768, 390, and 320 px with the real local fonts. Measured reference comparisons and unresolved original-asset/live-site limitations are documented in `docs/DESIGN_CORRECTION_0.6.md`.

See [CMS guide](docs/CMS_GUIDE.md), [installation and update steps](docs/INSTALL_UPDATE.md), and [observed URL results](docs/URL_STATUS.md). Full route/SEO/CMS browser checks run with `node tests/website-browser.cjs`; integration tests run with `docker compose -f docker-compose.local.yml run --rm -T cli wp eval-file - < tests/<name>-integration.php` for `catalog`, `seo`, `video`, `site`, `blocks`, `editorial`, and `checkout`. The checkout suite performs validation only and never starts a purchase. Run `node tests/video-browser.cjs` for focused inline/modal playback tests.

After building the release ZIPs, run `bash tests/release-installation.sh` to install and activate the actual packages in a separate, disposable WordPress. It uses cached Docker images and an internal network, checks the fresh content model and repeat installation, then removes its own test resources. It never uses the existing local database. Optional arguments select another theme ZIP and Core ZIP, in that order.

## Member library

Read [member dashboard update](docs/MEMBER_DASHBOARD_0.9.md), [member-library handoff](docs/MEMBER_LIBRARY_0.8.md) and [PR #1 review](docs/PR_1_REVIEW.md). Private uploads use a separate Docker volume outside the web root. The explicit setup provisions Profile and My Downloads in addition to the original fourteen pages. OAuth is **off** until actual client/guild/role configuration and isolated HTTPS staging tests; no secrets are bundled. Existing payment providers and Discord automation remain unchanged.

New focused suites are `discord`, `private-files`, and `downloads` integration files. `tests/member-catalog-integration.php` uses service doubles and requires `--skip-plugins=timonconfigs-core`; flush rewrites with the real plugin after isolated presentation tests if needed. Browser tests are `tests/private-files-browser.cjs` and `tests/member-area-browser.cjs`; `node tests/download-stream.cjs` verifies delivery bytes and descriptor integrity. These mutate only disposable localhost data. No actual provider consent or purchase is automated.

`node tools/capture-theme-screenshot.cjs` creates the real 1200x900 WordPress screenshot and separately retains untouched full-page captures; its compact-preview mode is documented in the tool/report.
