# TimonConfigs — compact one-click config cards and XIM Matrix TXT downloads

## What changed

- Catalogue cards no longer show long descriptions, release/updated dates, the publisher/creator line, or the recorded download count. They show the shared game banner, title, concise game/device label and Free/VIP badge.
- A **single full-card action** is presented:
  - Verified eligible user + Available config + usable private file/code → full-card POST to the **existing** nonce, session-bound, signed, one-use download handler.
  - Not logged in → Discord login.
  - Member without the required VIP tier → Browse Memberships.
  - Published Coming soon or missing verified file/text → no download (details/placeholder only).
- Optional Guide / Values modal controls remain separate click targets and remain subject to existing content authorization.
- Cronus Zen and other device entries use the original private attachment and retain its actual extension.
- **XIM Matrix** chooses a validated **private `.txt` file** when one is uploaded. If there is no private TXT and Owner populated protected **Values**, the backend creates a download response using that code as `<config-slug>-xim-matrix.txt` (without writing it to public WordPress Media Library). A binary `.ximmr` is never blindly relabelled `.txt`.
- The Values export is only prepared after checking the specific user's **live session + Discord release entitlement + Values-specific visibility**; uses the same HMAC-signed short-lived, one-use URL, binds a SHA-256 hash of Values to the token, refuses replay and denies changed/removed content or changed device/release status.
- Previous private file download protections still apply. A published card is not evidence that it has a downloadable script.

## Owner steps

1. Sign in to `/account/?tc_manage_configs=1` with a verified Owner account.
2. For XIM Matrix: pick the game, **Device: XIM Matrix**, title, tier and **Release: Available** only after verifying the actual working profile.
3. Under **Values**, paste the exact importable Matrix text code and choose its intended access visibility; alternatively attach a safe `.txt` file through **Protected script files**. For Matrix, TXT attachments have priority over Values.
4. For Cronus Zen: upload actual supported private script files (GPC/ZNBK/etc.). ZNBK and XIMMR still require the configured content inspector; do not disable scanning or permit arbitrary executables.
5. Publish after validation, visit `/configs/`, click on the card from eligible Free/VIP account. Browser should download a file rather than navigate to the details page. User without access sees login/upgrade flow.
6. Coming Soon remains a preview card, **not** a download. Adding a real file or code alone does not bypass the Release: Available setting.

## Security and testing

- No new endpoint serving a public/private file path in the HTML, REST API or query string.
- No client-side tier/role trust; Authorizer and protected delivery still check Discord roles and current session server-side.
- The TXT contains **only Owner-provided Values**, not a conversion of an actual .ximmr backup. If Values are absent, the Owner must provide valid TXT.
- Existing file limits, private storage, CSRF/nonces, quotas and download audit remain.
- Changed integration tests: `tests/member-catalog-integration.php` and `tests/downloads-integration.php` (mocked Discord; real local WordPress required). Run full suite and browser checks; PHP lint CI is not equivalent to live integration.
- Never deploy automatically to Hostinger; review with backup on staging. CDN/LiteSpeed bypasses must include nonce-bearing card archives for logged-in users as well as OAuth/request/download endpoints.
