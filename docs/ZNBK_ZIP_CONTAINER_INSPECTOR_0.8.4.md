# Core 0.8.4 — ZIP-backed Zen Backup (.znbk) inspection

## Why the Owner saw this error
The existing private upload scanner recognized `.znbk` as an allowed *extension*, but rejected any file beginning with ZIP's `PK\x03\x04` signature **before** calling the proprietary format inspector. Hence the Owner got `Archive, executable, and shell payloads are not accepted.`

The exact uploaded binary has NOT been inspected in this task; a real ZNBK backup might use ZIP framing or another binary layout. The screenshot error itself does not prove which signature caused it.

## What changed
- An ordinary ZIP, renamed GPC, executable or shell file is **still prohibited**.
- `.znbk` specifically may contain a real ZIP envelope **only if** the PHP ZipArchive extension is available and the container passes strict structural preflight: max 128 entries, path traversal & symlink denial, no executable filenames, nested archives, dangerous compression algorithms, extraction-size limits and executable signatures in member prefixes. **Nothing is extracted to disk or exposed publicly.**
- Passing the container preflight does **not authorize a proprietary Zen backup**: the existing `timonconfigs_private_file_scan` filter must still return `true` from a properly configured and trusted scanner / ZNBK format inspector. The same checks execute on upload AND on subsequent download.
- No global ZIP exemptions or unconditional-approval hook have been installed. Simply selecting ZNBK in **Settings → Private script formats** never disables inspection.
- If a ZNBK passes container checks without a configured inspector, the UI clearly says that an approved ZNBK scanner is missing. If the upload is malformed or embeds an unsafe entry, it stays rejected even when an external inspector approves.
- Other formats (including legitimate `.gpc` source text and `.txt` importable values) retain their existing behavior.

## Production prerequisites
1. Confirm the Hostinger private storage outside every public root is working — the Owner's upload form is already visible, so this is separate from the current format rejection.
2. Confirm `zip` / `ZipArchive` is enabled in the hosting PHP extensions when using ZIP-backed backups.
3. Engage the developer/administrator to configure a **real trustworthy** handler for `timonconfigs_private_file_scan` that can inspect the proprietary ZNBK payload and optionally use antivirus / safe-format validation. No scanner exists merely because Core lists the extension.
4. Test on staging with a backup the owner is entitled to distribute, first using an independent local copy. Do not upload secrets or third-party paid content to external "conversion" sites.
5. Existing public Free/VIP permissions, signed one-use downloads, quotas, and Discord OAuth2 are unchanged. Do not deploy production before the external scanner is set up and an actual sample is verified.

## Regression testing
`tests/private-files-integration.php` now includes a generated ZIP-backed ZNBK fixture, rejecting unsigned/unapproved backups, invalid ZIPs, traversal paths, nested archives and embedded executable samples, including while a test-only scanner returns true. The trusted-scanner mock is **only for disposable integration tests**; never ship or configure it as a production `return true` filter. The WordPress CI workflow executes the test alongside existing protected-download checks.

Important: A real owner-provided `.znbk` sample is needed to confirm actual ZIP member layout and whether the structural envelope accepts the vendor format. No real user script bytes have been uploaded, read or modified by this change.
