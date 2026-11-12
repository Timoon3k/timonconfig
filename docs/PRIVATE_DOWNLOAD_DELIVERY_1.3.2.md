# Protected downloads — Core 1.3.2

The reported symptom was a download opening Member Login/profile instead of saving a file, including for an Owner who has all membership roles. This release requires **Core 1.3.2**. The current theme remains **0.17.2**.

## Changes

- My Downloads now renders its protected file library. The previous public renderer only showed the profile and membership panels; its existing library helper was never called.
- The library uses the same file selection as catalogue and release pages. Matrix releases expose their uploaded TXT, or generated TXT from available Values. An XIMMR-only release without Values does not advertise a TXT download.
- New forms POST to `/?tc_download_request=1` on the site's own public origin. Valid requests redirect with HTTP 303 to the existing signed attachment URL, which is handled before normal page/template redirects. This also supports WordPress installed under a public subdirectory. The old authenticated admin-post handler remains available for older forms.
- Output buffer cleanup is bounded. A non-removable host buffer returns an error instead of an endless cleanup loop or a damaged attachment. Successful file responses explicitly use HTTP 200 and `Content-Disposition: attachment`.

The wp-admin restriction scenario was reproduced in a disposable browser test. The precise redirect policy on production was not inspected. A role labelled Owner still provides management access separately from membership entitlement: downloads require the existing verified Discord/WooCommerce access rules. Nonces, logged-in session binding, expiry, one-use claims, path validation and checksums remain enforced.

## Update

1. Back up the current plugin and database, then install `artifacts/timonconfigs-core-1.3.2.zip` as an update to TimonConfigs Core. No theme update is needed if 0.17.2 is already installed.
2. Purge LiteSpeed/page/CDN cache once. Close old release/account tabs and open a fresh page while signed in.
3. Open **My Downloads**. The protected library should appear before the membership panels. Check the verified membership status, then download an allowed release. Test a real ZNBK/GPC release and a Matrix TXT release as applicable.
4. Check the browser's Downloads list or configured download directory and confirm the original filename and file size. A save dialog depends on browser preferences.

Do not rerun site setup or reimport Rank Math settings for this update. Pushing GitHub main does not itself establish that the plugin has been installed on WordPress hosting.

## If a profile still opens

Use a freshly loaded release page and inspect the form action and browser Network responses. The new action contains `tc_download_request=1`; an allowed request should return 303 followed by a 200 attachment. A 403 requires checking the current verified membership/session. A 500 requires checking the corresponding server error/output-buffer configuration. If the first response is a redirect to `/account/`, identify which hosting/security rule generated it.

Share only the request path, HTTP status and visible error message when diagnosing. Do not share signed download links, cookies, nonces, Discord tokens or private file contents.

## Validation

The automated suites use disposable localhost WordPress fixtures, synthetic files and simulated Discord responses. They exercise native browser downloads and disk byte comparisons, the public My Downloads renderer, malformed requests, session/nonces, membership denial, replay, file integrity and non-removable output buffers. The packaged plugin is also installed in a separate offline WordPress instance. Results for this release are recorded in `PROGRESS.md` and ignored local `artifacts/` reports. No real Discord membership, production file or hosting deployment is claimed by these tests.
