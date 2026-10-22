# TIMONCONFIGS — COMPLETE MEMBER LIBRARY, DISCORD OAUTH2, WORDPRESS ADMIN (GPT-6.1 Sol Ultra)

Continue the existing repository `Timoon3k/timonconfig` at the latest commit. Do not recreate the theme, change the production checkout, or touch production without approval. Public text in English, progress reports in Polish.

## Verified baseline
- Theme `timonconfigs` 0.7.1, Core 0.5.0; custom post type `tc_config`, seven `tc_game` collections, `tc_device` and `tc_platform` taxonomies, Gutenberg and Upgrade.chat URL handoffs already exist.
- The public domain is **https://timonconfigs.com**. The user's `timoconfigs.com` spelling omits an 'n'; do not change canonical domain without verification.
- Main repository notes: `PROGRESS.md`, `NEXT_SESSION.md`, `docs/COMPLETION_REPORT_0.7.md`, `docs/HOME_FIX_0.7.1.md`.
- This feature branch adds a **preliminary** `TC_Private_Files` admin-only, multiple-file upload module that stores scripts outside the web root and deliberately has **no public download endpoint** yet. Audit and test it thoroughly; do not make a public media URL for premium files.
- Screens supplied by owner show the desired Coldz-style layout and account page. Use these as **inspiration only**; do not copy Coldz branding, text, code or images.

## P0 — Preserve and inspect
1. `git status`, identify the actual deployment branch, backups and versions; read reports. Do not discard changes.
2. Check both live site and staging via a browser if network access permits; if blocked, report so accurately instead of claiming any live verification.
3. Create a **real 1200×900 theme screenshot** via browser at desktop viewport from the running local/staging WordPress and save `wp-content/themes/timonconfigs/screenshot.png`. Screenshot MUST be the actual rendered theme (not just wallpaper, mockup or placeholder). Validate 4:3 aspect, legibility, asset and footer inclusion. Do not claim the capture is production if it isn't.
4. Confirm HTTPS, Discord invite, Upgrade.chat URLs, and WordPress admin status without exposing secrets.

## P1 — Catalog and actual script files
The desired catalog resembles user's Coldz screenshot:
- One game category e.g. Apex Legends with **unlimited separate script cards**; *one post per script*, not one card per game.
- Search, game, device, input, tier, platform, sort (upload date / updated / title) and paginated grid.
- Each card has game cover (editable per config or inherited from term), title, creator/publisher, release date, update date, downloads (real verified count or omit), Free/VIP badge, public preview/description, and a context-dependent **Download / Log in / Browse Memberships** CTA.
- Admin `Configs → Add New` should support multiple private attachments per config, sortable file entries, visible original filenames, file version, notes and optional checksum; do not silently overwrite uploaded files. Provide delete/replace actions guarded by nonce/capability, confirmations and audit records.
- Restrict extensions and sizes, reject PHP/executable payloads and unsafe archives, enforce upload quotas, perform malware inspection if available, store securely **outside web root**, prevent path traversal, use random opaque filenames, never disclose path or permanent public URL. Avoid accepting archive types before threat model and storage checks are implemented.
- Free downloads: require verified Free Member where intended; use the same authorization pipeline. Premium: server-authorized downloads only, short-lived signed access and no client-forgeable tiers. Never expose paid files in wp-content/uploads or browser HTML/JS/API.
- Existing draft configs must remain drafts; never invent licensed scripts or offer unreleased downloads.

## P2 — Discord OAuth2 account and roles
- `Continue with Discord` using authorization-code flow; `state`, PKCE where available, exact redirect allowlist, session fixation/CSRF protections, secure cookie settings, token encryption and minimization.
- Requested scopes should be minimal: `identify`, `guilds.members.read` if supported by the actual documented endpoint. Account linkage by verified Discord ID, not username; robust refresh/re-auth/logout and API error handling.
- In WordPress create a member area resembling the layout reference: avatar, Discord display name and ID (show only on owner-visible profile), current roles/verification, membership cards (Free / Monthly / Lifetime), Last Checked, Refresh Roles button with rate limiting, private download library, recent downloads, membership management, support link.
- Fetch guild membership/role IDs **server-side** and periodically refresh; do not trust JavaScript or stored one-time role claims for premium authorization. Cached status must expire; when Discord is unavailable, protect premium resources (fail closed) with user-friendly messaging.
- Hierarchy: Lifetime and active Monthly grant defined paid library; Free Member limited to free resources; Unverified no protected access. Explicitly define plan-tier policy and enforce it consistently. Role names are presentation only; configurable immutable role IDs decide access.
- Security Bot is authoritative for verification; Upgrade.chat is authoritative for payment-driven VIP roles. **Do not** create another role automation or fake Stripe/PayPal webhook. Membership label is derived from Discord roles and/or a verified permitted provider API—not from redirect query params.
- `Browse Memberships` must link to /pricing/ and the existing validated Upgrade.chat product checkout URLs. No checkout handling by the new module.
- Do not publish OAuth feature until credentials, correct guild ID, role IDs, access model and real account tests are confirmed. Admin-only configuration must redact secrets, limit capabilities and handle nonce checks.

## P3 — Quality and WordPress UI
- Follow original black/orange/blue TimonConfigs theme, not Coldz colors. Desktop cards 3 per row, responsive 2/1 mobile, accessible forms, loading/empty/error states, loading skeleton as appropriate.
- Gutenberg/Settings API controls for card artwork, descriptions, memberships, protected upload metadata, profile hero artwork, social and help links; structured releases remain `tc_config`.
- Account nav items: Profile, My Downloads, Browse Memberships, Support, Logout; don't expose staff-only features or unrelated data.
- Maintain existing SEO behavior, sane `noindex` for user account and protected downloads, no role/customer data in cache or public structured data.
- Page titles, breadcrumbs, accessible contrast and image alt text, image lazy loading, pagination and sitemap validation.
- Version bump & migration must be backwards-compatible; record all changes and rollback procedure.

## P4 — Security and testing
- Use independent staging and backups before data migrations. Tests: unauthorized/unverified/free/monthly/lifetime users, expiration/revocation, wrong guild, stale cache, race conditions, role removal, expired session, CSRF/state/PKCE, IDOR, directory traversal, malicious filenames/archive, MIME mismatch, direct-url bypass, multisite/public caching, logout and private metadata leak.
- Validate admin multiple upload actions, 0/1/10 files, oversized files, invalid type, error handling, replacement/rollback. No real purchases.
- Run `php -l` for all changed PHP; existing integration/browser tests; write new protected-download and OAuth integration tests; capture local site screenshots at 1440/390 and actual 1200x900 WP screenshot.
- No deployment without explicit authorization. Do not mark features production-ready until integration tests prove access cannot bypass server auth.

## Handoff
Update `PROGRESS.md`, `NEXT_SESSION.md`, `docs/CMS_GUIDE.md`; build tested release ZIPs. Provide exact added files, tests/results, admin setup and required real IDs/secrets (without printing secrets), staging URL if any, blockers and deployment checklist. Commit logically to a feature branch and request review. **Implement code rather than writing only a plan.**
