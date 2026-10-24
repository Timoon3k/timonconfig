# Core 0.8.10 — Owner config management without Discord role API calls

## Goal and scope
The Owner is the sole intended publisher of TimonConfigs configuration files. Previously, **every** Owner operation required a fresh live Discord guild-role verification. Fast successive saves and temporary Discord API failures therefore blocked normal editing.

The Owner editor and account navigation now use `TC_Discord::can_manage_configs_local()`, which **does not contact Discord** when creating, editing, uploading, replacing, deleting, publishing or reviewing configurations.

## Security model (mandatory, not a public upload)
Owner access is granted ONLY when all conditions are met:
1. An authenticated WordPress session exists (no WordPress-admin bypass, no anonymous access).
2. Discord OAuth previously established this exact local user: `_tc_discord_id` is a valid Discord snowflake and `_tc_discord_binding_<id>` identifies this very WordPress account.
3. The Discord ID is explicitly on the administrator-controlled **Approved Owner Discord user IDs** list under **Settings → Discord membership**. If the site remains in Restricted Pilot, the ID must additionally be on the pilot list.
4. The encrypted local Discord OAuth credential from the verified sign-in has not been removed (e.g., by logout/revoke).

Unlike previous versions, the Owner **does not** need an active guild Owner badge, fresh live Discord membership response or 10-second API cooldown to manage configs. The Owner role/badge remains optional *display* metadata; this local publishing privilege is granted by the exact, administrator-approved identity binding, never by a user's self-reported name or role.

CSRF nonce checks, per-request scoped WordPress config capabilities, file extension/quota policy, private off-web-root storage, and server-authorized signed Free/VIP downloads are unchanged. Other accounts, including Staff, Free, Monthly and Lifetime, remain forbidden unless separately and explicitly put on the Owner allowlist.

Revocation is immediate by removing the Discord user ID from Approved Owner IDs, removing the WordPress OAuth token/session, or removing its unique binding. The permission helper never grants generic WP administrator privileges.

## Important distinction
**Premium downloads still verify Discord membership** according to existing expiry/rate-limit rules. Removing remote Owner role polling does not weaken subscriber Free/VIP entitlement checks. A legitimate Owner may edit configs while Discord API is offline, but members may still be unable to download protected files until their role can be verified.

## Safe deployment
- Update Core **0.8.10** from GitHub after a database + plugin + private storage backup; changes in GitHub do not automatically install on Hostinger.
- Check only the intended account ID is in **Approved Owner Discord user IDs**; do not add other VIP/Staff members.
- Test repeated Save → Upload → Publish actions as Owner without the 10-second delay.
- Test an unrelated authenticated Free Member and an anonymous visitor: neither must gain access to Manage Configs, owner POST, or private file storage.
- Test a real VIP download separately, ensuring its Discord verification still works.
- Restricted Pilot remains unchanged; to let other users log in, test on a separate staging site and confirm OAuth2 callback/domain settings before authorizing public production.
- No production account, Discord role, billing, existing script, or live Hostinger configuration was changed by this source-only PR.

## Verification
Disposable WordPress CI includes tests for back-to-back Owner saves without HTTP calls, blocked unapproved users with valid nonces, revoked Owner ID and OAuth token, pilot constraints, safe scoped capabilities, and live-check fail-closed VIP entitlements using mocked Discord API.
