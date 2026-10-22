# TimonConfigs editing guide

The theme preserves the premium mockup layout while making content editable in WordPress. No Node server or page-builder plugin is required.

## Initial setup and existing installations

After activating Core and the theme, open **Tools → TimonConfigs setup**. The explicit administrator action creates only missing pages and fills only empty content. It preserves existing authored content, statuses, and assigned menus. The same action prepares seven game collections and three draft Apex configuration structures.

The existing internal config post type remains `tc_config`. Renaming it to `timonconfigs_config` would strand existing posts and external references; the requested configuration model is implemented by extending the existing type.

## Home and pages

Edit **Pages → Home**. Its ten TimonConfigs section blocks can be reordered or removed using Gutenberg List View. Select a block to edit its heading; Hero also supports description, tagline, eyebrow, image URL, and button text. Benefits have individual editable titles/descriptions. A shared-content link opens the corresponding WordPress content model or global settings. A complete Home pattern is available in the block inserter.

If an existing Home has authored content, setup does not replace it. Insert **TimonConfigs complete Home** manually if you want that page to use the section layout. The old empty-page fallback remains functional.

Other editorial and legal pages use native paragraphs and headings. Existing premium templates keep their catalogue, pricing, media, and support components while rendering the authored page content. Do not add a second H1 inside the page body.

Use **Appearance → Customize → TimonConfigs site content** for shared pricing labels, amounts, descriptions, features, branding, background art, global links, and footer description. Set the original logo and site icon under **Site Identity** when the owner provides them.

## Game collections and configurations

Open **Configs → Games** for Apex Legends, Call of Duty, Fortnite, Rainbow Six Siege, Rust, EA Sports FC, and NBA 2K. The Finals is not added. Each term has an editable description, public cover image URL, search title/description, and indexing control. Device and Platform terms are edited from their respective menus.

Empty, unavailable, and archived-only collections are automatically excluded from indexing and the core XML sitemap. Publishing a configuration is a deliberate editorial decision; do not invent platform or game-version compatibility.

Config fields include title, short/full description, featured image, Game, Device, Platform, input method, access tier, config version, supported game version, last update, confirmed compatibility, tutorial URL, changelog, and release status.

The access URL is a **public Free resource only**. It is omitted from REST metadata, cleared when saving a premium tier in the admin form, and rendered only for an available published Free release. Premium files must not be uploaded to the public Media Library or entered as public access URLs. The protected membership-download service remains a separate implementation task.

The three Apex structures (Cronus Zen S30, XIM Matrix Controller, XIM Matrix MnK) start as drafts without fabricated files, platforms, versions, or dates.

## Video showcase

Open **Apex Videos** to edit published first-party footage. Up to three published entries are shown. Title, description, poster/featured image, device label, MP4 or YouTube URL, caption VTT URL, order, playback mode, upload date, duration, and recording-verification state are editable.

Existing clips remain as neutral fallback footage until CMS entries are published. Their device mapping is not assumed. Mark a recording verified only after confirming its actual metadata. VideoObject schema is emitted only when verification, upload date, duration, poster, and source are present. YouTube is requested only after a visitor chooses to load the provider. Inline remains the default; modal is optional.

## Reviews and FAQ

Use **Reviews** for pseudonym/title, content, featured avatar, score, config label, demo flag, order, and Draft/Published status. The six fallback samples are visibly marked Demo Content. Replace them with approved feedback only after obtaining the author's permission; there is no automatic Discord scraping/import or purchase-verification claim.

Use **FAQs** for question/title, answer blocks, and order. Home shows five questions; the FAQ page shows the collection. Structured data follows the visible FAQ section and is omitted when that section is removed from Home.

## Checkout, Discord, and SEO

Enter real Monthly/Lifetime product URLs in **Settings → TimonConfigs**. Only validated HTTPS Upgrade.chat destinations are accepted. An empty setting intentionally produces an unavailable state; a browser return never grants membership.

The owner-supplied Discord invite is `https://discord.gg/RyvzSqrF6` and is editable in the Customizer. Its validity could not be checked because this execution environment blocks the Discord API request. No account role is granted or removed by this release.

Search/social fields are available on pages/configs and game/device collections. An active AIOSEO, Yoast, Rank Math, SEOPress, TSF, or Slim SEO provider owns its metadata; the theme suppresses its own title/social/canonical/schema output to avoid duplicates. Use that provider's editor to configure its values and verify its sitemap behavior on staging.
