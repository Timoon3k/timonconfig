# TimonConfigs — Codex implementation brief (23 October 2026)

**Read this entire file together with the 6 screenshots attached in the user's Codex chat.**
This is an actionable implementation request, not a request for a plan alone. Work in the existing `Timoon3k/timonconfig` WordPress repository. Public UI copy must be in English; progress/report to the owner in Polish.

## 0. Baseline — preserve what's working

- Expected repo baseline at the time of writing: theme 0.9.0 and Core 0.7.3, `tc_config` CPT, `tc_game`/`tc_device`/`tc_platform` taxonomies, Gutenberg, Upgrade.chat checkout handoffs, Discord OAuth2 with Restricted pilot, Free/Monthly/Lifetime role verification, protected multi-file uploads, signed downloads, `/account/` and `/my-downloads/` implemented.
- **Check `git status`, recent commits, `PROGRESS.md`, `NEXT_SESSION.md`, `docs/CMS_GUIDE.md`, `docs/MEMBER_LIBRARY_0.8.md`, `docs/MEMBER_DASHBOARD_0.9.md` first.** Verify each item against present code; do not assume a deployment or recreate features.
- The owner reports real Discord login and Free Member status working on their account, but this is not proof of VIP download authorization. Do not regress OAuth session/logout, file checks, cache rules, Security Bot verification, or Upgrade.chat role/payment ownership.
- Preserve existing paid prices **VIP Monthly €40 / VIP Lifetime €100**, real links from existing settings, Free tier, Discord invitation, custom theme, releases, and existing drafts. No Stripe rebuild, no new role/payment automation, no production changes without explicit owner approval.
- Visual reference: `https://coldz.pro` and owner screenshots for **functional/layout inspiration only**. Do not copy third-party logos, game artwork without rights, text, proprietary files, code, or claims.
- Site canonical target: `https://timonconfigs.com`. Check actual hosting/canonical behavior; temporary `hostingersite.com` domain must not become a second SEO indexable site.

## 1. Screenshot mapping — use actual images in Codex conversation

The user will attach **6 image files separately**. Associate them with the numbered requirements as far as they correspond to the supplied screenshot; if an image's subject differs, follow its visible design cues while retaining the functional rules below.

1. **Screenshot 1: OAuth/member interface** — preserve the existing branded Discord user profile and correct membership state; remove script download listings/history/cards from the OAuth **dashboard/profile** (not from the config catalog). Reconcile any old `/my-downloads/` navigation gracefully and keep secure download infrastructure. Profile may show avatar, username, role badges, renewal/membership link, Refresh Roles and quick navigation to Configs.
2. **Screenshot 2: library filters** — game, device (Cronus Zen / XIM Matrix), tier, search and sortable Upload Date / A–Z / Downloads. Existing filters for input/platform should remain where useful.
3. **Screenshot 3: script cards** — responsive per-config cards, ideally 3/2/1 columns, each with editable graphic, title, creator/publisher, real date, game, device, access badge, real download count if measured, actions for View/Download.
4. **Screenshot 4: Guides and Values** — separate per-config modal dialog(s) displaying editable setup instructions and copyable configuration values; a config can have Guide, Values or both. Preserve newlines, tables/code text, accessibility (focus trap, Esc, close button, return focus), mobile scrolling. Premium content MUST be checked server-side, never pre-rendered to unentitled users, including REST endpoints.
5. **Screenshot 5: SEO/search presentation** — clear XIM Matrix and Cronus Zen game-focused text, internal linking and indexable useful landing pages rather than keyword stuffing.
6. **Screenshot 6: final home CTA** — remove image and decorative background from the specific `Join the TimonConfigs Community` section, including overlays/background pseudo-elements if present, without stripping the whole website's styling. Keep accessible contrast and responsive spacing.

## 2. Discord roles and Owner-only management

- Continue existing `TC_Discord` provider integration, not a second OAuth system. Fetch current guild-member roles via Discord API as immutable IDs, and (using a properly authenticated and least-privileged service as needed) resolve names for visual badges. Display verified meaningful guild roles, **excluding Unverified**, and explicitly support **Staff** and **Owner** in addition to Free Member, VIP Monthly, VIP Lifetime. Do not claim all roles without a verified source; handle Discord/API outages without stale privilege escalation.
- Add secure admin settings for the real Staff and Owner role IDs. **Owner alone** gets the new frontend `Manage Configs` interface and all create/edit/publish/delete/media/private-upload capabilities for that interface. Staff gets a badge/profile role but **no configuration write access**. Free/VIP roles only affect reading/downloading their allowed tiers. A member may have multiple badges; privilege checks must use role IDs, not display labels.
- Ownership MUST require a server-verified guild Owner role **and explicit administrator-approved Discord user ID allowlist/account binding** (defense in depth); never trust client-side state, user-submitted role names, email/username or a hidden UI button. The existing restricted OAuth pilot should remain enabled until explicitly approved otherwise.
- All write routes use authenticated identity, capability/policy checks, nonces/CSRF defenses, strict validation, rate limits, safe file handling, audit trail, no-store and noindex. Must reject crafted REST/admin-post requests by Free/VIP/Staff/Unverified/anonymous users. Do not elevate Discord-linked WordPress Subscriber into WP Administrator or expose generic wp-admin rights. Integrate with WP's existing capability model safely instead of bypassing it.
- Separate public identity role badges from actual paid download entitlement. Staff/Owner labels must **not** automatically grant VIP file access unless an explicit, reviewed policy says so.

## 3. Config library/catalog and frontend owner editor

- Preserve one published/draft `tc_config` per distinct configuration/script, unlimited number of cards per game; a script may attach multiple versioned private files. Seven existing games remain: Apex Legends, Call of Duty, Fortnite, Rainbow Six Siege, Rust, EA Sports FC and NBA 2K; preserve `tc_device` terms Cronus Zen and XIM Matrix.
- Add/polish **Search**, **Game**, **Device**, **Tier**, **Sort by Upload Date / A–Z / Downloads**. Query parameters must be allowlisted, sanitized, canonical/noindex handled correctly for filter URLs, pagination stable, keyboard/mobile accessible; no exposing unpublished or protected metadata through filter endpoints.
- Upload Date means actual recorded publication/upload date; download sorting requires a genuine, privacy-preserving aggregate of completed server download deliveries, **not an invented count**. If unavailable, do not fake numbers: show honest empty state or add a tested counter.
- Script card: configuration image (featured or inherited term art), creator, date, version, game, device, access badge, buttons `View Details`, `Guide`, `Values`, `Download` or `Browse Memberships` according to authorization. Clearly distinguish preview from download.
- **Owner-only frontend editor reachable after Discord OAuth2**: create new config; edit/draft/publish; attach game/device/platform/input/tier, title, descriptions, featured image, cover/preview media, upload date/version/changelog, guide text, values text, separate protected file versions. Support safe multiple uploads and editor validation; preserve existing WordPress admin editor as fallback for site administrator.
- Expand accepted *genuine script/support file formats* via a carefully scoped, configurable allowlist with actual MIME/content inspection and a scanner/format inspector where necessary; user asked for more than `.znbk` and `.gpc`, **NOT** unrestricted arbitrary executable upload. Maintain existing bans for PHP, JS executables, HTML/SVG active content, archives/executables unless separately reviewed and scanned. Keep `TIMONCONFIGS_PRIVATE_STORAGE_DIR` outside **all** public roots and avoid WordPress Media Library for paid files. Use WP Media Library only for deliberately public safe preview images/video; sanitize/validate type.
- Existing Core requires approved scanning of ZNBK/XIMMR including text-encoded backups; **do not disable/bypass this requirement**. Show clear upload errors when inspector or private directory is not configured. Preserve version history and checksum/audit. Never expose permanent file URLs.
- For unauthenticated users show login prompt. For Free users inspecting VIP-only config show visually appealing non-intrusive upsell to **VIP Monthly €40** and **VIP Lifetime €100** using the *existing* verified Upgrade.chat product handoffs. No fake discounts, VIP claims or payment callbacks.
- Download authorization continues server-side in `TC_Downloads`, checking login/session, current Discord role/entitlement, release status, individual file, expiry, one-use signed URL, revocation and no-cache. Removing dashboard listing must never weaken this.

## 4. Guides & Values content model

- Add optional **Guide** and **Values** sections for each `tc_config` with real WordPress editor fields or Gutenberg blocks, revisions and safe sanitization; support formatted steps, numbered lists, line breaks, command/config blocks, copy-to-clipboard, optional screenshots. Use modal UI matching Screenshot 4.
- Define visibility separately for each content item: public teaser, Free Member or VIP tier as appropriate. No protected text in page source, cached fragments, exposed REST metadata, or HTML attributes for unauthorized users.
- Use accessible native dialog or correct focus lifecycle; mobile, keyboard, escape, long-text scrolling, loading and error states.

## 5. SEO — priority search intent and suggested copy

**Top priority: `xim matrix apex legends`**, then `xim matrix call of duty`, `cronus zen apex legends`, `cronus zen call of duty`.

Improve the actual home:
- Suggested home SEO title: `XIM Matrix & Cronus Zen Configs for Apex Legends | TimonConfigs`
- Suggested Home H1: `XIM Matrix & Cronus Zen Configs for Apex Legends`
- Suggested hero copy: `Explore Apex Legends configurations for XIM Matrix and Cronus Zen, including controller and mouse & keyboard setups, verified compatibility guides, Free releases and VIP membership options.`
- Suggested H2: `XIM Matrix Apex Legends Configs`, with link to the relevant genuine collection/landing.
- Suggested H2: `Call of Duty Configs for XIM Matrix & Cronus Zen`.
- Primary Apex CTA: `Explore XIM Matrix Apex Configs`; ensure an actual destination and no broken/duplicate URLs.

Create/enrich meaningful, uniquely useful search landing content for each game-device combination, especially **XIM Matrix Apex Legends**:
- Proposed candidate `/xim-matrix-apex-legends/` only if it offers distinct editorial value and a clear canonical intent. Audit existing `/xim-matrix-configs/`, `/apex-legends-configs/`, filter routes and any already published target **before creating new URLs**. Avoid duplicate/thin/doorway pages or multiple canonicals for identical content; use 301 redirects if changing destinations.
- XIM + Apex landing: carefully documented firmware/version compatibility, verified controller and MnK setup, actual live scripts, installation steps, setup guide, video demonstration with truthful captions, editable release history, owner-provided FAQ, relevant internal links, clear limitations. Do not invent settings, claims, reviews, creator credits or release dates.
- Similar (but independently useful) intent pages for XIM Matrix + Call of Duty, Cronus Zen + Apex, Cronus Zen + Call of Duty when real content exists. Do not fabricate availability for absent games.
- One unique descriptive H1/title per landing, clear canonical URLs, concise meta descriptions, descriptive breadcrumbs and alt text, internal links from home/header/games/library/footer and indexable category pages. Validate Search Console + sitemap, mobile layout, loading/CLS/LCP and social cards. Use only factually valid schema (BreadcrumbList and real editorial content), no fake review/rating/FAQ rich-result guarantees.
- Follow existing provider-aware SEO code: when AIOSEO or another supported plugin owns metadata, **do not emit duplicate title, canonical, OG or schema**.
- Explain in the handoff that no ethical implementation can guarantee Page 1 in Google. Provide concrete content priorities and a measurable Search Console baseline rather than keyword-stuffing or ranking guarantees.

## 6. Specific visual edits

- On Home final CTA `Join the TimonConfigs Community`, remove background artwork/image and optional gradients/pseudo-image only *for that section*, not all site backgrounds. Current source likely `wp-content/themes/timonconfigs/template-parts/home/cta.php` and theme CSS. Retain typography/buttons/spacings and contrast.
- Keep TimonConfigs premium near-black, orange + electric-blue identity, custom logo, cross-device responsive layout and keyboard access. Screenshots are visual reference; preserve user-authored Gutenberg content and editable options. Avoid cloning Coldz designs verbatim.

## 7. Acceptance criteria and rollout discipline

Implementation (not a generic plan) in **small, testable commits**, preserving `main` and any concurrent changes. Make a feature branch if appropriate; report actual branch/commit IDs.

Acceptance checks:
- OAuth login/logout/refresh still works in pilot; Free/Monthly/Lifetime and real Staff/Owner badges correctly shown; Unverified omitted; role loss/rate limit/API failure handles securely.
- Free, VIP, Staff, anonymous and mismatched-guild users cannot create/edit/publish/delete/upload via any route. Explicitly allowlisted Owner can perform these via frontend only after server verification.
- Free user sees upsell on VIP config; verified premium member can download from config page; no download list required in OAuth dashboard; revocation / signed URL expiry / replay / wrong-file requests cannot bypass server auth.
- File format, malicious extension, MIME mismatch, path traversal, oversize, private storage, audit records, replacement/deletion and backup tested. Existing inspector requirements stay intact.
- Search and device/game/tier filters; sort Upload Date / A–Z / real Downloads; draft exclusion; pagination and cards work at desktop/tablet/mobile.
- Guides/Values modals work on keyboard/touch, copy accurately, and do not leak private content in HTML, REST or cache.
- SEO Home metadata/H1, useful new unique landings, internal links, canonical, noindex/private pages, sitemap/robots, duplicate plugin metadata checks pass; CTA background removal matches screenshot.
- Run actual PHP lint, targeted WordPress integration suites + Playwright responsive checks, track exact pass/fail; note if screenshots or third-party access couldn't be inspected.
- Never write live users' roles, change Upgrade.chat/Stripe/PayPal state, purchase products, publish unfinished scripts, disable restricted pilot or deploy production without owner's explicit instruction.
- Update `PROGRESS.md`, `NEXT_SESSION.md`, `docs/CMS_GUIDE.md` and release handoff with files changed, testing evidence, remaining blockers, real Hostinger steps and rollback. Keep summaries brief to avoid multi-MB tool responses.

## 8. Token/output limits in Codex

Earlier session failed at `decoded message length too large` (~4.36 MB > 4 MiB). **Do not inline six image base64 strings, raw binary, giant combined diffs, complete minified bundles, full test logs, or large JSON payloads.** Read only necessary source ranges; summarize tool output; generate source files in repo rather than printing full contents. Request screenshots in 2–3 batches or use workspace image file paths/references. Attachments' image numbers should be noted during review. If interrupted, commit checkpoint and update NEXT_SESSION without losing work.

## 9. Recommended execution order

1. Read documents/screenshots, inspect implementation and create **short gap checklist** (already exists / to change / needs owner environment). Avoid asking repeat questions answered by current files.
2. Owner/Staff Discord role read + server authorization + frontend Owner Config editor (secure uploads).
3. Catalog cards, filters and real download sort; remove redundant dashboard download listing; add Free→VIP purchase prompt.
4. Guide/Values modals and privacy checks.
5. Remove Home CTA background and apply style corrections from screenshots.
6. SEO content/landing pages and metadata/linking.
7. Full targeted tests, screenshots, commit history, brief deploy checklist. **No production deploy without explicit permission.**
