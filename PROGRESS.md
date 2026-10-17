# TimonConfigs project progress

Last updated: 2026-10-17

## Repository audit

The selected `Timoon3k/timonconfig` repository and its GitHub remote contained no files, commits, branches, previous theme, plugin, tests, mockup files, or project documentation. No earlier implementation was available to preserve. Development was started inside the existing checkout at `/workspace/timonconfig`; no parallel project was created.

The supplied visual reference is used only for layout and art direction. Its fictional metrics, reviews, character artwork, and unsupported claims were not copied. The supplied three MP4 files are real vertical Apex Legends gameplay clips. They contain no reliable device/config metadata, so their public labels remain neutral until the owner confirms the correct Cronus/XIM mapping.

## Completed storefront foundation

- Custom WordPress theme foundation with dark obsidian/gunmetal surfaces, orange CTA styling, blue accents, responsive layout, keyboard focus styles, and reduced-motion support.
- Home page with premium hero, device overview, Apex Legends showcase, pricing preview, and final CTA.
- Pricing page with Free (€0), VIP Monthly (€40/month), VIP Lifetime (€100 one-time), honest renewal language, comparison table, and checkout FAQ.
- Three supplied Apex videos added with generated posters, lazy images, `preload=none`, and an accessible native-dialog video player. Titles, device labels, and uploads are editable in the Customizer.
- Custom business plugin with admin-managed Upgrade.chat product URLs, strict HTTPS/host validation, plan allowlist, non-cached 302 handoff, and a safe unavailable state. No payment completion or Discord role is inferred from browser redirects.
- WordPress title support, basic meta description, canonical, Open Graph, Organization schema, and WordPress core sitemap compatibility.
- Static validation script covering required files, checkout safeguards, unsupported marketing claims, media presence, reduced-motion support, and JavaScript syntax.

## Completed catalogue and sales pages (0.2.0)

- Editable `Config` custom post type with Gutenberg/REST support, revisions, featured images, Game and Device taxonomies, and structured compatibility fields.
- Server-rendered catalogue at `/configs/` with allowlisted search, game, device, and access-tier filters, pagination, responsive cards, empty state, and individual config pages.
- Stable SEO landing URLs for Apex Legends, Cronus Zen, and XIM Matrix. Core term links and paginated rewrites resolve to the short public URLs.
- Games overview using only the currently supported Apex Legends collection; no fictional games or availability claims were added.
- Dedicated VIP Monthly and VIP Lifetime landing pages with clear recurring/one-time language and CTAs routed through the existing safe Upgrade.chat handoff.
- Page-specific canonical URLs and descriptions for catalogue/taxonomy views, visible breadcrumbs, and BreadcrumbList structured data.
- Catalogue date values now reject invalid calendar dates in addition to malformed input.
- Local setup now creates Games and both VIP plan pages idempotently and adds Games to the primary navigation.

## Validation results

- JavaScript syntax: passed with `node --check`.
- MP4 inspection: passed; all three are H.264/AAC Apex clips, 28–37 seconds, vertical 480×854 or 576×1024.
- Static structure/security/content assertions: passed.
- PHP source parsing: all 12 PHP files parsed without syntax errors using Prettier's PHP parser. Its format check reported style differences because the source follows WordPress conventions rather than Prettier formatting.
- Host PHP CLI is absent, but native `php -l` and live WordPress smoke tests now run successfully inside the pinned Docker environment.

## Local WordPress environment

- WordPress 6.8.3, PHP 8.2.29, Apache, and MariaDB 11.4 run in Docker.
- Theme and plugin activation passed.
- Native `php -l` passed for all 22 PHP files inside the WordPress container.
- Home, Pricing, Games, both VIP pages, the catalogue, three primary taxonomy landings, and config detail returned HTTP 200.
- The unconfigured Monthly checkout returned the intentional HTTP 503 unavailable state instead of redirecting to an invented URL.
- Playwright rendered Home at 1440 px and 390 px and Pricing at 1440 px without browser console errors.
- Browser interaction checks passed for the mobile menu, video dialog, Escape close behavior, on-demand video source, and 390 px horizontal overflow.
- Catalogue and sales browser checks passed at desktop and mobile widths without console errors or horizontal overflow. The Monthly filter returned only the matching local fixture.
- Canonical and BreadcrumbList checks passed for Games, catalogue, Apex/Cronus/XIM landings, and config detail.
- Reusable setup is available through `bash tools/setup-local-wordpress.sh`.

The three catalogue entries currently visible in the Docker database are local-only preview fixtures. They are not stored in the distributable plugin/theme and must not be treated as production product data.

## Required before production

- Confirm which supplied video corresponds to Cronus Zen S30, XIM Matrix Controller, and XIM Matrix MnK; then enter accurate device/config version/platform/recording metadata.
- Supply the real VIP Monthly and VIP Lifetime Upgrade.chat checkout URLs.
- Test theme and plugin activation on a WordPress staging installation with PHP 8.1+.
- Complete Discord OAuth2, current guild-role verification, protected premium delivery, legal drafts, standalone FAQ/Contact pages, consent analytics, remaining SEO pages, and installation ZIP testing.
- Do not deploy to production or modify current payments/roles without explicit approval.

## Hostinger deployment preparation

- Deployment archives are rebuilt after each completed release and validated with `unzip -t`. Version 0.2.0 includes the catalogue and VIP landing work.
- The Hostinger Connector skills are available, but the current session exposes no callable Hostinger hosting or WordPress tools. No account state could be read and no Hostinger resource was changed.

