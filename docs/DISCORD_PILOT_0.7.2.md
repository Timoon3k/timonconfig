# Discord pilot — Core 0.7.2

This opt-in mode permits real-account testing on HTTPS production without claiming that isolated staging tests have passed. It is not a completed security audit or permission to open access to all members.

## Owner steps

1. Back up the database and plugin files. Install Core 0.7.2 as an update; do not delete the plugin or overwrite the database. Existing settings and encrypted credentials remain in place.
2. Open Settings → Discord membership. Register exactly the callback shown there in Discord Developer Portal → OAuth2 → Redirects. Resolve any mismatch between the intended domain and the WordPress home URL before signing in; do not change site URLs blindly.
3. Copy your Discord **user ID**, not a role, guild or application ID (Discord developer mode → Copy User ID). Enter it in **Pilot Discord user IDs**. Maximum 10 IDs, one per line.
4. Check **Enable OAuth** and **Restricted pilot**. Leave the staging confirmation unchecked. Leave PKCE off unless provider support has been verified. Save. Client secret should show Configured (redacted); an empty replacement input is normal.
5. Exclude `/account/`, `/my-downloads/`, OAuth query routes and downloads from hosting/CDN/page caching. Do not cache authentication responses. Purge previously cached member pages.
6. Keep your admin window open. In a separate private browser window, signed out of WordPress, open `/account/` on the same domain and click Continue with Discord. Approve the configured application. Never initiate login by opening the callback directly. Existing administrator accounts are not implicitly attached to Discord identities.
7. Verify the displayed identity and actual membership, sign out and retry. Test an unlisted account: it must not receive a new WordPress account/session. Remove the listed ID while its member session is active: member access must be denied on the next request even with a fresh role cache. Restore the ID only if you intend to continue testing.
8. For file tests, first configure private storage outside every public document root and use a harmless test file. The pilot does not grant VIP roles or override file-tier checks. Do not change customer roles, buy subscriptions or alter Upgrade.chat automation for testing.

Stop the pilot by unchecking **Enable OAuth** and saving; purge page caches. Disabling only Restricted pilot restores the existing production staging-confirmation requirement. Never mark staging tests complete merely because pilot testing succeeded.

## Enforcement and limits

The identity allowlist is checked against the Discord `/users/@me` result before binding or creating a WordPress account. It is also checked before role-cache reuse and protected download authorization. An empty, malformed or oversized allowlist disables OAuth in pilot mode. A configured pilot restricts identities even if staging confirmation was previously enabled. These settings are administrator-only and excluded from REST.

The sign-in entry point remains reachable publicly: an unlisted person may see Discord consent, but is denied when their identity returns, before WordPress account creation. This is an identity allowlist, not a private invitation URL. Existing WordPress sessions are not deleted on allowlist removal; their member-file authorization is denied.

No payments, role writes, callback URI conventions, token storage or private-file format policies were changed. Up to 60 seconds of Discord role-cache freshness still applies to listed users. The allowlist itself is checked on every authorization call.

Local verification: 82 OAuth assertions (including 22 new pilot/settings checks), 59 download assertions and 37 member UI assertions passed with mocked Discord HTTP. A real provider login and the actual hosting cache/HTTPS configuration still require owner testing. No production settings were changed by this implementation.

## Callback troubleshooting — Core 0.7.3

Update the plugin, attempt a new login from `/account/` in a private window, then open Settings → Discord membership in the administrator window. Report only the **Stage / Reason / HTTP** from **Last Discord sign-in failure**, confirming the timestamp matches your attempt. It stores only the latest failure, expires after 24 hours and may be overwritten by another attempt. It does not diagnose prior attempts retroactively.

- `token / invalid_client`: check the client ID and OAuth client secret belong to the same application (not a bot token).
- `token / invalid_grant`: begin a fresh flow; verify the exact callback and PKCE setting. Do not reuse callback URLs.
- `transport`: the WordPress server could not complete its HTTP request; investigate outbound connectivity/TLS without sharing raw credential-bearing logs.
- `identity / http`: identity lookup was rejected; report the HTTP status.
- `binding / pilot_denied`: authenticated Discord identity is not on the configured pilot list.
- `binding / account_conflict`: sign out of WordPress in the test window; if it persists, an administrator must inspect the existing identity binding. Do not delete user accounts or bindings blindly.
- `binding / account_create_failed`: WordPress account creation failed; investigate the local user creation/plugin policy.
- `token / malformed_response` or `scope_mismatch`: the provider response does not satisfy the expected token contract. Do not weaken validation before inspecting the cause.

These are diagnostic leads, not proof of a specific hosting fault. The module uses user OAuth and does not require a bot installation for these endpoints. Never share the callback query containing code/state, client secrets or token values.
