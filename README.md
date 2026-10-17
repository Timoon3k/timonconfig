# TimonConfigs WordPress website

Custom WordPress theme and business plugin for `timonconfigs.com`.

## Components

- `wp-content/themes/timonconfigs` — premium marketing theme, Home, Pricing, Apex showcase, responsive navigation and core SEO metadata.
- `wp-content/plugins/timonconfigs-core` — validated Upgrade.chat checkout redirects and admin settings.

The production site needs WordPress 6.5+ and PHP 8.1+. No Node.js server is required at runtime.

## Local installation

### Docker (recommended)

With Docker available, run:

```bash
bash tools/setup-local-wordpress.sh
```

This starts WordPress 6.8.3 with PHP 8.2 and MariaDB 11.4 on port `8080`, activates the theme and plugin, and creates Home, Pricing, and navigation. The disposable local administrator is `timonadmin` with password `local-preview-only`. These credentials are local-only and must never be reused on staging or production.

Stop the services without deleting their database:

```bash
docker compose -f docker-compose.local.yml down
```

### Existing WordPress installation

1. Install WordPress in a local or staging environment.
2. Copy both directories into the matching `wp-content` directories.
3. Activate **TimonConfigs Core**, then activate **TimonConfigs**.
4. Create a page with slug `pricing`. WordPress automatically uses `page-pricing.php`.
5. Set a static front page in **Settings → Reading**.
6. Create and assign Primary and Footer menus in **Appearance → Menus**.
7. Enter the real product checkout URLs under **Settings → TimonConfigs**. Only HTTPS links on `upgrade.chat` or its subdomains are accepted.
8. Update homepage copy, Discord URL, video titles, device labels, and MP4 files in **Appearance → Customize → TimonConfigs homepage**.

Do not put Discord OAuth secrets, tokens, or premium downloads in the theme, Media Library, repository, or public web root.

## Validation

Run:

```bash
bash tests/verify-project.sh
find wp-content -name '*.php' -print0 | xargs -0 -n1 php -l
```

The second command requires PHP CLI. A full WordPress staging smoke test remains required before deployment.

