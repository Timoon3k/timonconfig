# Core 0.8.8 — Raw private configuration uploads

## Change requested

Owner needs to upload genuine Cronus Zen and XIM Matrix configs without rejection due to text/plain, UTF encoding, MIME guesses or ZIP/binary source signatures. Enabled configuration extensions are stored as opaque byte streams. Files are never transcoded or executed. A SHA-256 digest protects integrity.

## Hard security boundaries that remain

- The Owner must be logged in with verified Discord role and WordPress edit capability, submitting a valid nonce.
- All uploads are stored outside every public WordPress/web root, with safe directory and file ownership/permissions and no symlinks.
- Only administrator-enabled config extensions .gpc, .znbk, .ximmr, .json, .txt, .cfg and .ini are allowed. Unsafe filename components, traversal, double executable extensions and arbitrary extensions remain forbidden.
- Per-file 20 MB, ten per operation, 100 files/100 MB per config and 1 GB overall quotas remain, additionally subject to Hostinger/PHP limitations.
- An optional trusted security scanner may veto any upload.
- File bytes are served through session-bound, signed, role-protected one-time download, forced Content-Disposition attachment, nosniff headers and no cache.
- Uploaded files never enter WordPress Media Library, are not parsed or executed by WordPress, and no server path is disclosed.

## Limits and responsibility

This mode deliberately skips malware/content inspection within the allowed private formats. Only trusted Owners should upload files they are legally permitted to distribute. If uploads are expanded to other people, configure a separate malicious-file scanner first.

Uploading binary data under a .txt extension never converts it to an importable XIM Matrix text code. Generated Matrix Values downloads continue to use the Values field.

## Rollout

Source-only GitHub update, not Hostinger deployment. Back up WordPress database, Core plugin, and private storage. Deploy Core 0.8.8 after CI approval, check the uploader version, and try the Owner's real GPC sample before releasing to Free/VIP members. No real proprietary source bytes were available for direct verification in this development session.