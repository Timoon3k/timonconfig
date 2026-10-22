# Homepage welcome-post regression — theme 0.7.1

## Cause and correction

The supplied Hostinger screenshot shows the theme header/footer with WordPress's default post content. In WordPress, `front-page.php` is selected for both a static front page and the default latest-posts homepage. The 0.7.0 template loop treated nonempty blog post content as authored Home content, which hid the storefront in latest-posts mode.

Theme 0.7.1 explicitly renders the ten default storefront sections in latest-posts mode. It continues to render an authored static Home, uses the existing fallback for an empty static Home, and leaves a separately assigned Blog page as a real posts index. Schema visibility follows the default storefront in latest-posts mode, including when a stale static-page ID remains in Reading settings.

An administrator-only, read-only notice now identifies inactive/outdated Core, missing pages, missing/stale navigation assignments, or an invalid static front page. It points only to available WordPress administration screens. It never creates pages, publishes content, or changes Reading settings automatically.

Core remains 0.5.0. No checkout, subscription, customer, or Discord-role logic changed.

## Apply on the Hostinger installation

1. Confirm **TimonConfigs Core 0.5.0** is installed and active under Plugins. Upload the corrected **TimonConfigs 0.7.1** theme ZIP if applying this code fix.
2. Open **Tools → TimonConfigs setup** and choose **Create missing site structure**. This creates missing pages/menus and fills empty starter content while preserving authored content and existing assignments.
3. Open **Settings → Reading**, choose **A static page**, and select the intended published **Home** as the homepage. Save changes. Do not choose a blog post as Home.
4. Under **Appearance → Menus**, confirm **Primary** and **Footer** assignments, then purge the page/CDN cache in the hosting or caching controls. Reload the public homepage.

If the setup screen is absent, the current Core installer is not available: activate/update Core first. Existing payments and Discord settings do not need to be recreated.

## Verification boundary

- Ten browser cases passed at 1440/390 px: latest-posts storefront, authored static Home, empty static Home, separate Blog, and latest-posts storefront with Core inactive. Each checks HTTP 200, expected Hero/plan/content behavior, one H1, no overflow, and no PHP/JavaScript errors.
- The browser test rejects non-local/incorrect-port targets before mutations. Reading/plugin options are restored and all test records removed in cleanup.
- Administrator notice integration passes 29 assertions for current, inactive, and outdated Core, including permission checks and preservation of settings.
- Gutenberg/schema visibility integration passes 14 assertions. Theme PHP lint and JavaScript/static checks pass. The 80-file theme ZIP matches the tested source and passes 62 fresh-installation assertions with unchanged Core 0.5.0.

The public preview host `darkorchid-dragonfly-367115.hostingersite.com` could not be inspected directly: its HTTPS request was rejected by the environment proxy with `403 CONNECT`. This is an environment-access failure, not a response from WordPress. The screenshot and the reproduced template behavior support the diagnosis; the remote site's actual Reading settings were not read or changed.

Focused local regression results and release-installation results are recorded in `PROGRESS.md`. They distinguish local behavior from a deployment to Hostinger.
