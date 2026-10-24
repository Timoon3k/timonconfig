# Hostinger private storage and Owner upload recovery

## What the screenshot means

The Owner panel shows `Configure TIMONCONFIGS_PRIVATE_STORAGE_DIR outside every public document root before uploading.` In the existing Core this indicates the protected storage constant is missing or unset. Previously, a new config submitted together with a file failed before a WordPress draft was created, leaving the form empty.

## Correct Hostinger configuration

1. Back up the WordPress database, `wp-config.php`, theme and plugin.
2. In Hostinger hPanel select Websites → Dashboard → Files → File Manager → **Access all files of your web hosting**. Do not use the domain-only file manager that starts inside public_html.
3. Identify the correct installation's `public_html` and create a separate private folder outside **every publicly served website document root**. Never put paid files in `public_html`, Media Library, `wp-content`, or public upload folders.
4. Verify the actual absolute server path for the new folder and that the PHP user can write. Prefer directory mode 0700 if ownership permits; never 0777, group/other-writable, or symlinked.
5. Add this before WordPress initialization in the correct `wp-config.php` (replace placeholder with the verified real absolute server path):

```php
define( 'TIMONCONFIGS_PRIVATE_STORAGE_DIR', '/home/ACTUAL_HOSTINGER_ACCOUNT/timonconfigs-private' );
```

6. Refresh `/account/?tc_manage_configs=1`. Once the warning disappears, test a harmless TXT on a draft. Do not publish premium scripts until download-denial tests pass on the actual server.

## Recovery behavior in theme 0.10.2 / Core 0.8.2

- If storage is not available, the new-config upload input is disabled and a clear error explains that Owner can still create a config **without a file**.
- If an old tab posts a new config with file while storage is missing, the server now creates a **draft**, returns a recoverable error containing its ID, and redirects to that draft. The file is **not** stored; no false success or exposed public download.
- A successful draft is visible only under Owner's Existing configurations (or WordPress administrator → Configs), not public `/configs/`.
- Protected uploader retains its format allowlist, scanning, outside-document-root path validation, role checks and signed file downloads.

## Verification

`tests/owner-editor-integration.php` adds a simulated missing-storage assertion that checks saved draft ID, draft status and absence of private file metadata. `.github/workflows/wordpress-download-integration.yml` runs it on a disposable WordPress installation. This does not verify production Hostinger. Back up before deployment and update matching theme/Core versions together.