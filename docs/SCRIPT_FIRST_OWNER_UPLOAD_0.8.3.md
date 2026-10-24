# TimonConfigs — script-first Owner upload panel (Core 0.8.3, theme 0.10.3)

## Symptom

The Owner screen for an existing config such as `config_id=116` offered a prominent **Public preview image** button, but no private script file chooser. The `Protected script files` section contained `Configure TIMONCONFIGS_PRIVATE_STORAGE_DIR outside every public document root before uploading.` The image form is not the script uploader. It never stored private configs.

## Changes to the WordPress code

- Existing Owner config: the first action is now **Upload configuration / script files**. It accepts multiple files through the existing private upload handler and leaves validation, approved file inspector, anti-CSRF, user roles and audit unchanged.
- Missing/unwritable private storage: the panel prominently explains the Hostinger prerequisite, shows no misleading upload action and confirms the config is still editable. Never silently send paid scripts to public Media Library or a world-readable fallback folder.
- Existing attached files remain manageable through version/order, replace and delete controls.
- The former **Public preview image** form is collapsed under **Optional: custom card artwork (not a script file)** after the main edit form. Existing per-game shared cover remains default.
- XIM Matrix can use a real `.txt` attachment or Owner-authored Values text subject to the existing signed delivery and role checks. Binary `.ximmr` is not converted simply by renaming.
- No change to storage path, Discord role mappings, public access control or live WordPress database. Theme 0.10.3 / Core 0.8.3 are source code releases only until owner-approved Hostinger update.

## Required Hostinger action — cannot be fixed merely by moving a button

1. Open the **correct WordPress installation** in Hostinger; the screenshot uses the temporary `darkorchid-dragonfly-367115.hostingersite.com` domain. Compare with `timonconfigs.com` in the hPanel to avoid editing a different installation.
2. Back up the DB and `wp-config.php`. In Hostinger File Manager choose **Access all files of your web hosting**, not only the current domain's `public_html`.
3. Create a private directory outside every website document root (and outside Media Library), verify real absolute path and that WordPress PHP user can write. If supported, 0700 on owner-owned folder is preferred; no 0777, no symlink.
4. Edit the current WordPress `wp-config.php` before WP loads. Add (example path only):

```php
define( 'TIMONCONFIGS_PRIVATE_STORAGE_DIR', '/home/ACTUAL_ACCOUNT/domains/timonconfigs.com/private-scripts' );
```

5. Replace **the entire path** with the actual canonical directory on the same hosting account; don't copy that example blindly. If the constant already exists, fix its definition instead of duplicating it.
6. Reload the existing Owner config editor. The private upload form should appear if the location passes the plugin's security checks. Upload a harmless `.txt` first and verify the protected one-time download for an entitled test account. Only then upload actual paid scripts.

See `docs/HOSTINGER_PRIVATE_STORAGE_AND_OWNER_UPLOAD.md` for the previous storage troubleshooting steps. GitHub commits do **not** deploy to Hostinger.

## Verification

- PHP lint and disposable WordPress integration must pass before merging. The Owner test now verifies the real upload UI appears **before** optional image controls and disappears when private storage is invalid.
- Keep production Restricted pilot and security scanner rules enabled. Do not edit customers' Discord/Upgrade.chat roles or push to production without approval.