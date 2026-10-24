# Owner upload recovery — Core 0.8.6 / theme 0.10.5

Continues `main` at `6d1e20e` / code `19ae648`; no catalogue, payment or role migration.

## Changes

- GPC/TXT/CFG/INI inspection understands UTF-8, BOM-marked UTF-16 LE/BE and Windows ANSI (CP1252). Decoding is used only for inspection; storage and protected downloads retain the original bytes and SHA-256.
- Harmless libmagic source-language guesses no longer reject plain source text merely because it resembles Java/Pascal rather than C. Active web/shell types, executable/archive signatures, malformed encodings, quotas and optional scanner veto remain enforced.
- Rejected types report detected MIME and a fixed encoding category, never file contents. No original owner script was supplied, so compatibility with the precise file in the screenshot is not independently confirmed.
- ZNBK still uses automatic structural checks without an external approval requirement. ZIP-backed ZNBK requires PHP ZipArchive. XIMMR retains its separate inspector requirement.
- **Publish config** saves a valid first upload and publishes its card in one operation. **Save draft** remains deliberate. A failed first upload retains a recoverable draft instead of publishing an empty card.
- Temporary Discord verification failures have a separate message from genuine loss of approved Owner access. Feedback remains visible after the permission gate. Membership/refresh failures expose only bounded stage/reason/HTTP diagnostics to administrators.
- The AJAX form preserves typed fields and the selected private file after a temporary verification/network failure. Manual retries respect the server cooldown. Every write still verifies nonce, current Discord Owner role, approved ID and account binding; unavailable Discord does not authorize a write. No tokens/files are placed in browser persistence.
- Without JavaScript, the existing POST/303 flow remains. During AJAX, the form URL is read with `getAttribute('action')`; the hidden WordPress `action` field must not override the request destination.
- **VIP — Monthly & Lifetime** still uses the existing `monthly` access value and shares files between both paid roles. **VIP Lifetime only** remains available. Prices, checkout links, Discord role IDs and Upgrade.chat automation are unchanged.

## Validation

Local WordPress 6.8.3/PHP 8.2, disposable identities and synthetic format fixtures. Discord transport is mocked, not a live OAuth/role test. 726 PHP assertions passed across file validation, Owner authorization/recovery, downloads, OAuth, shared VIP, catalogue, routes and member UI. The real browser passed 39 Owner HTTP checks (initial TXT/GPC/ZNBK publication, cards, exact-byte protected downloads and 503 retry without losing the form) plus 24 member layouts. Static checks and 66 PHP source lints passed. Distribution installation checks are recorded in PROGRESS.md before release completion.

## Update and target-host check

1. Back up the target installation. Install **both** Core 0.8.6 and theme 0.10.5, then purge page/CDN cache. Updating only Core leaves the old form JavaScript.
2. Keep existing Discord settings, pilot restrictions, private storage and format settings. Verify the uploader version and that GPC/ZNBK/TXT are enabled in Private script formats. Do not add a blanket scanner approval callback.
3. As the approved Owner, use **New configuration**, enter title/game/device/access and choose the original GPC, ZNBK or TXT; click **Publish config**. Confirm the card and download with the relevant member account. Do not rename binary backups to TXT.
4. If rejected, record the new MIME/encoding message and provide a harmless representative original file for diagnosis. Never send client secrets or OAuth tokens. For a temporary Discord error, wait and retry on the same form; use the administrator's redacted verification diagnostic if it persists.

No Hostinger installation, production database change, live purchase or Discord role write is performed by this change. GitHub publication alone does not establish the installed hosting version.
