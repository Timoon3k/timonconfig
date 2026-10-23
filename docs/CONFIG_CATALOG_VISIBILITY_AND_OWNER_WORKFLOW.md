# TimonConfigs — published config visibility and simple Owner workflow

## Observed issue and root cause (code-level)

Owner reports an existing config with WordPress publication status **Published** but no visible card on `/configs/`. In Core 0.8.0 `TC_Catalog::filter_archive()` reused `released_meta_query()`, which excluded `tc_release_status=coming-soon`. The Owner form allowed the independent combination `status=publish` and `tc_release_status=coming-soon`, making the post public but absent from the catalog. This was an actual source-level mismatch, not a remotely reproduced Hostinger observation.

This branch corrects the mismatch: **published Coming Soon configs are visible as labelled, non-downloadable preview cards**. Archived configs remain excluded. Existing `released_meta_query()` remains in use for available-release/indexability tests, while `catalog_visible_meta_query()` applies to public archive browsing.

**Security invariant:** `TC_Downloads` and `TC_Catalog::download_cta()` still require `tc_release_status=available`, a configured and validated private file and an authorized Discord tier. Publishing a Coming Soon card never makes its files downloadable.

## Owner steps after deploying tested matching plugin + theme versions

1. In WordPress, navigate to **Configs → Configs**. Locate the published entry and check its `Release status`. `Coming soon` means a public preview; `Archived` means hidden from the catalog. A WordPress `Publish` button alone is not an instruction to unlock files.
2. Ensure **Game** and **Device** are assigned. Missing game/device terms mean the config cannot appear when filtering by those categories. The new Owner frontend form rejects new published entries without both fields, but older administrator-created posts may require manual repair.
3. In the Discord-connected account, the **verified and administrator-approved Owner** can open `/account/?tc_manage_configs=1`. The frontend now has simple **Game** and **Device** dropdowns and an expanded **Existing configurations** overview showing WP status, release status, game and device. This is an OAuth-connected *website* panel; it is not a Discord bot `/upload` command.
4. Choose Free / VIP Monthly / VIP Lifetime and Release status. **Draft** = hidden; **Published + Coming Soon** = card visible, no download; **Published + Available** = card visible and eligible for downloads after validating file + role; **Archived** = hidden.
5. New entries default to a **shared game category cover**, making every Apex card inherit the same Apex artwork, every Call of Duty card inherit the same CoD artwork, etc. The Owner can explicitly choose **Use uploaded custom thumbnail** to override the common game cover. The custom preview may be public; never upload private scripts as a public image.
6. Optional: select the first batch of safe private config files in the create form. With selected files the system creates a **draft**, validates/storage-writes the files via the existing private file manager, then publishes only if the upload succeeded and publication was requested. If upload fails, the draft remains available to edit and retry; no accidental public download.
7. For an existing config use its **Protected script files** section to upload, replace, delete or order file versions. New/old entries still support the existing private-file viewer and WordPress admin fallback.

## Format and storage policy

Config formats currently recognized by the Core allowlist: `.gpc`, `.txt`, `.json`, `.cfg`, `.ini`, `.znbk`, `.ximmr`. Enabled subset is configurable via **Settings → Private script formats**. The existing safe format inspector remains compulsory for **ZNBK and XIMMR** regardless of binary/text encoding. Executables, archives and arbitrary file extensions remain blocked. Private files require `TIMONCONFIGS_PRIVATE_STORAGE_DIR` outside all public website directories, proper PHP ownership/permissions and host-specific caches excluded. Changes here do not disable these rules.

## Release/deployment caveats

- Source edits and commits **do not automatically update Hostinger WordPress**. First compare actual active Core and theme versions with GitHub. The previous baseline was theme 0.10.0 and Core 0.8.0. Do not overwrite active DB, settings or script storage.
- Production requires backup of database, web files and private files; isolated staging (or otherwise expressly approved safe test target); PHP lint/integration/browser tests; then simultaneous theme+Core update and cache purge (LiteSpeed/CDN if present). Save WordPress permalinks if catalog rewrites were not initialized.
- This session can modify GitHub but cannot directly verify the active Hostinger DB, uploaded files, or browser rendering. **No live production confirmation is claimed**.
- Automated test sources updated: `tests/catalog-integration.php` and `tests/owner-editor-integration.php`. They must be executed on disposable local WP before deployment, along with `tests/member-catalog-integration.php`, file/upload and Discord authorization suites. Do not invent passing test counts from earlier releases.

## Still separate work

- Real Discord chat bot commands (e.g. an actual `/upload-config` slash command) are **not** implemented; current owner uploads occur through the site authenticated via Discord OAuth2.
- Real Owner/Staff role credentials, allowed Discord IDs, private scanner, protected file serving, and hosting performance/cache behavior require the owner's host configuration and end-to-end tests.
