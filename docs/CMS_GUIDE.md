# TimonConfigs editing guide

The theme preserves the premium mockup layout while making content editable in WordPress. No Node server or page-builder plugin is required.

## Initial setup and existing installations

After activating Core and the theme, open **Tools → TimonConfigs setup**. The explicit administrator action creates only missing pages and fills only empty content. It preserves existing authored content, statuses, and assigned menus. The same action prepares seven game collections and three draft Apex configuration structures.

The existing internal config post type remains `tc_config`. Renaming it to `timonconfigs_config` would strand existing posts and external references; the requested configuration model is implemented by extending the existing type.

## Home and pages

Edit **Pages → Home**. Its ten TimonConfigs section blocks can be reordered or removed using Gutenberg List View. Select a block to edit its heading; Hero also supports description, tagline, eyebrow, image URL, and button text. Benefits have individual editable titles/descriptions. A shared-content link opens the corresponding WordPress content model or global settings. A complete Home pattern is available in the block inserter.

If an existing Home has authored content, setup does not replace it. Insert **TimonConfigs complete Home** manually if you want that page to use the section layout. The old empty-page fallback remains functional.

Other editorial and legal pages use native paragraphs and headings. Existing premium templates keep their catalogue, pricing, media, and support components while rendering the authored page content. Do not add a second H1 inside the page body.

Setup preserves existing publication states, including WordPress's default draft Privacy Policy on a fresh installation. Review legal content and deliberately publish the approved policies before checking their public URLs on staging.

Use **Appearance → Customize → TimonConfigs site content** for shared pricing labels, amounts, descriptions, features, branding, background art, global links, and footer description. Set the original logo and site icon under **Site Identity** when the owner provides them.

## Game collections and configurations

Open **Configs → Games** for Apex Legends, Call of Duty, Fortnite, Rainbow Six Siege, Rust, EA Sports FC, and NBA 2K. The Finals is not added. Each term has an editable description, public cover image URL, search title/description, and indexing control. Device and Platform terms are edited from their respective menus.

Empty, unavailable, and archived-only collections are automatically excluded from indexing and the core XML sitemap. Publishing a configuration is a deliberate editorial decision; do not invent platform or game-version compatibility.

Config fields include title, short/full description, featured image, Game, Device, Platform, input method, access tier, config version, supported game version, last update, confirmed compatibility, tutorial URL, changelog, and release status.

The legacy access URL remains public-Free metadata for compatibility; it is not the new member download pipeline and is no longer rendered as a protected script link. Store member files through **Configs → Private files**, outside the public Media Library. Premium files must not be entered as public access URLs.

The three Apex structures (Cronus Zen S30, XIM Matrix Controller, XIM Matrix MnK) start as drafts without fabricated files, platforms, versions, or dates.

## Member library — theme 0.8.0 / Core 0.6.0

Each script is a separate Config post. Publisher, creator, release date, verified-update date, cover, description and compatibility are editable. Catalogue sorting supports upload/publication date, updated date and title; it shows twelve records per page in a 3/2/1 responsive grid. No invented download counts are displayed.

Open the private-file management link from a Config editor, or **Configs → Private files**, and select the Config. Upload up to ten files per operation. Duplicate original names create separate opaque records. Edit version, notes and numeric order; replacement/deletion require explicit confirmation and append audit events. Replacing a file gives it a new ID. Existing ZIP/unsupported records are not offered for download; legacy files without checksums require revalidation/re-upload.

Configure `TIMONCONFIGS_PRIVATE_STORAGE_DIR` in wp-config to an existing writable directory outside every public web root. Local Docker provides `/var/lib/timonconfigs-private` through a separate private volume; this local path is not a Hostinger path recommendation. The module enforces 20 MB/file, 100 files/100 MB per Config and 1 GB/library, plus the host's PHP limits. GPC/JSON/TXT receive structural/content checks. Archives/executables are rejected. Binary ZNBK/XIMMR requires an approved format/malware inspector through `timonconfigs_private_file_scan`; structural checks do not certify files malware-free.

The explicit setup also creates **Member Profile** (`/account/`) and **My Downloads** (`/my-downloads/`) with native shortcode blocks. Existing authored pages are preserved; insert `[timonconfigs_member_area]` or `[timonconfigs_member_downloads]` yourself if those slugs already contain custom content. Profile artwork is editable in the Customizer. The member navigation links to Profile, My Downloads, Browse Memberships, Support and nonce-protected Logout.

Under **Settings → Discord membership**, enter the actual client ID, secret, guild ID and immutable Free/Monthly/Lifetime role IDs. The secret field stays blank/redacted on subsequent visits; a wp-config `TIMONCONFIGS_DISCORD_CLIENT_SECRET` constant can supply it instead. OAuth starts disabled. Use an isolated HTTPS site with `WP_ENVIRONMENT_TYPE` set to `staging` for real account tests before confirming production readiness. Register the exact callback displayed by the settings screen. Enable optional PKCE only after verifying support for that Discord application.

New Discord members receive subscriber accounts with opaque WordPress logins, linked by verified Discord ID. Existing WordPress accounts are never automatically linked by email or username; any existing-account migration needs a separate deliberate process. Use a logged-out/private browser when testing a new member identity.

Access policy: verified Free/Monthly/Lifetime can download Free; Monthly or Lifetime can download Monthly; only Lifetime can download Lifetime. All releases must be published, password-free and explicitly marked Available. Roles are read from the configured Discord guild, cached for at most 60 seconds, and denied on failed/expired verification. Upgrade.chat remains responsible for paid roles; Security Bot remains responsible for verification. Refresh Roles is rate-limited to ten seconds.

Download buttons submit a nonce-protected POST and receive a one-use, session-bound signed link valid for sixty seconds. Delivery rechecks current bounded authorization, file ownership/path/integrity and the opened file descriptor's checksum. Recent downloads record completed server delivery, not a claim that the client successfully installed a script. Account/download responses are private/no-store/noindex; exclude these paths and OAuth/download query endpoints from host/CDN caching before staging acceptance. See `MEMBER_LIBRARY_0.8.md` for deployment requirements and rollback.

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
