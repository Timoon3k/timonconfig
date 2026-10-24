# Upload and shared VIP — Core 0.8.5 / theme 0.10.4

This continues `main` at `b10f360` (including PR #6). Earlier unfinished local Owner UI work was preserved on `checkpoint/owner-ux-wip-2026-10-24` at `f376b8e`; the relevant status copy was integrated without replacing newer upstream upload/card fixes.

## Current ZNBK policy — explicit owner instruction

The owner requested on 24 October that ZNBK no longer require external inspection approval. This **supersedes the mandatory ZNBK inspector prerequisite in 0.8.4 and older handoffs**.

- `.znbk` is accepted after automatic checks without a registered approval callback. ZIP-backed backups use the existing bounded ZIP structural validation; PHP ZipArchive is needed for those containers.
- Limits, traversal/symlink/executable/nested-archive rejection, size/checksum validation and private storage remain. General `.zip` uploads are not enabled. This does not certify vendor compatibility or malware-free content.
- If an optional scanner is actually installed and explicitly rejects a file, its veto is retained. A missing callback or a callback returning null is not an approval requirement for ZNBK.
- XIMMR's existing mandatory inspector policy is unchanged. Do not rename binary XIMMR/ZNBK to TXT.
- Uploaded bytes are stored privately and streamed through the existing authorized, signed one-use route. No public Media Library fallback, payment changes or Discord role writes were added.

TXT accepts UTF-8 (with/without BOM) and BOM-marked UTF-16 LE/BE for Windows exports. Decoding is only for inspection: original bytes, size and SHA-256 remain unchanged for downloads. Encoded PHP/shell/web payloads, malformed encodings and renamed binary/archive data remain rejected. Plain TXT starting with the printable letters `MZ` is no longer misidentified as an executable solely from those two letters.

## Owner workflow

Open **Manage Configs → New configuration**, enter title, game, device and **Who can download?**, choose the script file and click **Publish config**. **Save draft** permits working without a file. Game art is inherited automatically; descriptions, versions, Guide/Values and additional metadata are inside collapsed **Advanced options**. Existing file/version management and optional custom artwork remain available.

A simple-form attempt to publish an Available config without a file stays unpublished/recoverable as a draft. Invalid uploads are never published. The server's existing Owner identity/allowlist/nonce/fresh-role checks remain. A small client-side wait handles the provider's existing 10-second verification cooldown; it does not bypass backend authorization.

Approved Owner without a Free/VIP role now sees **Owner tools enabled**, not the misleading verification prompt. This is presentation only: Owner management and paid download entitlement remain separate.

Config access choices are **Free Member**, **VIP — Monthly & Lifetime**, and **VIP Lifetime only**. Shared VIP continues using the existing stored value `monthly`, which already authorized both paid roles. There is no data migration, price change, role rename or restriction of existing Lifetime access.

The Configs archive route cache refreshes once on update. This repairs missing routes that would otherwise show a Home page instead of newly published cards; authored Home, integration settings and configured URLs are preserved.

## Verification and rollout

Verification passed: 671 focused PHP assertions, 29 actual Owner HTTP/browser assertions, 24 member-state layouts and 76 fresh-package installation assertions. PHP lint, JavaScript/static checks and source/archive byte equality passed. Detailed results are recorded in PROGRESS.md. Tests use disposable local WordPress, fake Discord HTTP and real private files. The Owner browser uses fixture login and genuine WordPress cookies over a temporary local TLS proxy; it is not a live Discord consent test. It checks one-submit publication, exact UTF-16 download bytes and actual ZNBK upload/download without an approval callback. Generated ZNBK samples demonstrate the transport/policy, not a real owner's vendor-specific backup.

Install the matching Core/theme ZIPs as updates after backing up the site/private directory. Preserve existing client/guild/role/pilot settings. Keep ZNBK/TXT enabled under Settings → Private script formats; enable PHP ZIP on hosting for ZIP-backed ZNBK. No extra external approval setup is required for ZNBK. Keep Restricted pilot until real hosting tests are accepted. This task does not deploy Hostinger or mutate production customers/payments.

Historical scanner-approved records and file checksums are preserved. Rollback reinstates the previous release's external ZNBK approval prerequisite; do not move private files into public uploads to work around it. Restore database/private files together if reverting actual file operations.
