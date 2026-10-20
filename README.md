# TimonConfigs WordPress website

Custom WordPress theme and business plugin for `timonconfigs.com`.

## Components

- `wp-content/themes/timonconfigs` — premium marketing theme with Home, Pricing, Games, VIP plan landings, video showcase, FAQ, Contact, policy pages, config catalogue templates, block patterns, responsive navigation, and SEO metadata.
- `wp-content/plugins/timonconfigs-core` — validated Upgrade.chat checkout redirects, admin settings, editable configs, FAQs, and verified-review content models.

The production site needs WordPress 6.5+ and PHP 8.1+. No Node.js server is required at runtime.

## Local installation

### Docker (recommended)

With Docker available, run:

```bash
bash tools/setup-local-wordpress.sh
```

This starts WordPress 6.8.3 with PHP 8.2 and MariaDB 11.4 on port `8080`, activates the theme and plugin, and creates the storefront, support, video, and policy pages plus navigation. The disposable local administrator is `timonadmin` with password `local-preview-only`. These credentials are local-only and must never be reused on staging or production.

Stop the services without deleting their database:

```bash
docker compose -f docker-compose.local.yml down
```

### Existing WordPress installation

1. Install WordPress in a local or staging environment.
2. Copy both directories into the matching `wp-content` directories.
3. Activate **TimonConfigs Core**, then activate **TimonConfigs**.
4. Create pages using the slugs in `tools/setup-local-wordpress.sh`. WordPress automatically selects the matching storefront, support, showcase, and policy templates.
5. Set a static front page in **Settings → Reading**.
6. Create and assign Primary and Footer menus in **Appearance → Menus**.
7. Enter the real product checkout URLs under **Settings → TimonConfigs**. Only HTTPS links on `upgrade.chat` or its subdomains are accepted.
8. Update global links, homepage copy, logo, game covers, hero artwork, video posters, titles, device labels, and MP4 files in **Appearance → Customize → TimonConfigs site content**.
9. Add approved FAQ and Review entries in their Gutenberg admin menus. Never fabricate testimonials; the frontend provides an honest empty state until an approved review exists.
10. Add catalogue entries under **Configs** and assign their Game, Device, access tier, input method, platform, version, and verified date. Never publish preview fixtures as verified production releases.

Do not put Discord OAuth secrets, tokens, or premium downloads in the theme, Media Library, repository, or public web root.

## Validation

Run:

```bash
bash tests/verify-project.sh
find wp-content -name '*.php' -print0 | xargs -0 -n1 php -l
```

The second command requires PHP CLI. A full WordPress staging smoke test remains required before deployment.


## Visual reference and inline media (theme 0.4.0)

The storefront follows the supplied mockup composition while retaining the real offer and omitting unverified statistics and invented reviews. The recreated wordmark and seven game covers are replaceable; use **Appearance → Customize** to provide final licensed production assets. Set the official logo under Site Identity when available.

Home and Video Showcase use the same inline player cards. MP4 downloads begin on interaction and playback remains within the card. No modal is used. Public showcase videos are not premium download storage.

Run the browser checks against the Docker WordPress with Playwright and Chromium available:

```bash
node tests/storefront-browser.cjs
```

Set `TC_TEST_URL` for a staging URL and `CHROMIUM_PATH` for another Chromium executable. Tests interact with search, menus, FAQ, and the public video player; they do not purchase products or modify memberships.
