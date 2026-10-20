# TimonConfigs project progress

Last updated: 2026-10-20

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
- Standalone FAQ and Contact pages with honest compatibility, checkout, Discord, and support guidance. Contact details remain hidden until a real Discord URL and production admin email are configured.
- Terms of Service, Privacy Policy, and Refund Policy baseline pages with editable page content and footer navigation. These drafts require owner/legal review for the operating entity and target markets before production.
- Dedicated Video Showcase page using the three supplied first-party clips, lazy posters, on-demand MP4 loading, and the accessible dialog player. Device labels remain explicitly pending verification.
- Two Gutenberg block patterns provide an editable premium CTA and FAQ starter for future page editing.

## Reference-led visual revision (theme 0.3.0)

- Reworked the Home composition against the supplied mockup: compact wordmark header, wide cinematic hero, orange/blue lighting, clipped four-column information strip, working search and quick filters, compact collection tiles, three pricing cards with Monthly emphasis, five benefit columns, first-party video cards, FAQ, landscape CTA, and compact footer.
- Recreated decorative hero and landscape artwork from the visual reference as optimized WebP assets. These are illustrations, not gameplay evidence. The supplied screenshot is not a source design package: the fallback wordmark is typographic, and the new hero is recreated rather than the original artwork.
- Preserved the actual €0 / €40 per month / €100 one-time offer and existing Upgrade.chat endpoints. Unsupported game releases, invented statistics, and testimonial quotes from the reference were not added; first-party gameplay occupies the testimonial row.
- Replaced the modal player on BOTH Home and Video Showcase with a shared native inline video card. MP4 sources are attached only on interaction, playback stays in the card, other videos pause when one starts, and native controls remain available. Failed media offers an in-card retry; no-JavaScript users receive direct video links.
- Added Customizer image controls for the hero and all three video posters. Existing video/title/device settings remain reusable. Updated the local menu to Home / Configs / Games / Pricing / FAQ / Contact.
- Fixed nested logo links by rendering the uploaded logo image within the existing single brand link.
- Validation: PHP lint passed for all theme PHP files; static checks, JavaScript syntax, and Git whitespace checks passed. The new `tests/storefront-browser.cjs` passed 12 route/viewport combinations at 1440, 768, and 390 px, real inline media playback, pause coordination, keyboard menu/FAQ behavior, homepage search, and media-failure retry UI. No console errors or horizontal overflow were detected in the normal cases.
- Theme ZIP: `artifacts/timonconfigs-theme-0.3.0.zip`; Core remains 0.2.0. Both current ZIP copies are refreshed and archive-validated. No production deployment was performed.

## Editable reference content (theme 0.4.0 / Core 0.3.0)

- Added a replaceable TimonConfigs image wordmark and seven premium game covers based on the supplied visual direction: Apex Legends, Call of Duty, Fortnite, Rainbow Six Siege, Rust, EA Sports FC, and NBA 2K. The Finals was removed as explicitly requested.
- Home and Games share one responsive game strip. Apex is the only active collection; future games are labelled “Coming soon” and have no misleading product links. Every cover can be replaced in the Customizer.
- Added Gutenberg-enabled, non-public `Reviews` and `FAQs` content types. Approved entries can be ordered and published by an editor. Until a real review is approved, the site displays an honest empty state instead of fabricated testimonials.
- Home now includes the reference Reviews row, while retaining the supplied first-party Apex video showcase with inline playback. FAQ content is shared by Home and the FAQ page and emits matching FAQPage structured data.
- Added the Reviews page and exact primary-menu order: Home, Configs, Games, Pricing, Reviews, FAQ, Contact.
- Added global Customizer fields for Discord, X, YouTube, Instagram, and the creator URL. The footer now carries the requested 2026 TimonConfigs / majewskitomasz.pl credit.
- Static checks, Docker PHP lint, and Playwright passed across Home, Games, Pricing, Reviews, FAQ, Video Showcase, and Configs at 1440, 768, and 390 px. The run also verified seven game covers, honest availability states, reviews empty state, search, keyboard controls, deferred inline video playback, pause coordination, and media-error retry.

## Validation results

- JavaScript syntax: passed with `node --check`.
- MP4 inspection: passed; all three are H.264/AAC Apex clips, 28–37 seconds, vertical 480×854 or 576×1024.
- Static structure/security/content assertions: passed.
- PHP source parsing: all 12 PHP files parsed without syntax errors using Prettier's PHP parser. Its format check reported style differences because the source follows WordPress conventions rather than Prettier formatting.
- Host PHP CLI is absent, but native `php -l` and live WordPress smoke tests now run successfully inside the pinned Docker environment.

## Local WordPress environment

- WordPress 6.8.3, PHP 8.2.29, Apache, and MariaDB 11.4 run in Docker.
- Theme and plugin activation passed.
- Native `php -l` passed for all 31 PHP files inside the WordPress container.
- Home, Pricing, Games, both VIP pages, the catalogue, three primary taxonomy landings, and config detail returned HTTP 200.
- The unconfigured Monthly checkout returned the intentional HTTP 503 unavailable state instead of redirecting to an invented URL.
- Playwright rendered Home at 1440 px and 390 px and Pricing at 1440 px without browser console errors.
- Browser interaction checks passed for the mobile menu, video dialog, Escape close behavior, on-demand video source, and 390 px horizontal overflow.
- Catalogue and sales browser checks passed at desktop and mobile widths without console errors or horizontal overflow. The Monthly filter returned only the matching local fixture.
- Canonical and BreadcrumbList checks passed for Games, catalogue, Apex/Cronus/XIM landings, and config detail.
- FAQ, Contact, Video Showcase, Terms, Privacy, and Refund pages return HTTP 200 with unique descriptions. The showcase player opens on demand and closes with Escape in browser tests.
- Home, Pricing, and catalogue passed the 768 px tablet rendering pass without console errors or horizontal overflow.
- Reusable setup is available through `bash tools/setup-local-wordpress.sh`.

The three catalogue entries currently visible in the Docker database are local-only preview fixtures. They are not stored in the distributable plugin/theme and must not be treated as production product data.

## Required before production

- Confirm which supplied video corresponds to Cronus Zen S30, XIM Matrix Controller, and XIM Matrix MnK; then enter accurate device/config version/platform/recording metadata.
- Supply the real VIP Monthly and VIP Lifetime Upgrade.chat checkout URLs.
- Test theme and plugin activation on a WordPress staging installation with PHP 8.1+.
- Complete Discord OAuth2, current guild-role verification, protected premium delivery, consent analytics, and final legal/security review on an isolated staging WordPress. These are the agreed Astra Medium tasks.
- Do not deploy to production or modify current payments/roles without explicit approval.

## Hostinger deployment preparation

- Deployment archives are rebuilt after each completed release and validated with `unzip -t`. Version 0.2.0 includes the catalogue and VIP landing work.
- The Hostinger Connector skills are available, but the current session exposes no callable Hostinger hosting or WordPress tools. No account state could be read and no Hostinger resource was changed.

