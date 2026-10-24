# Core 0.8.9 — Discord OAuth, Owner verification and restricted pilot diagnostics

## Symptoms reported (2026-10-25)

- The mobile Owner screen occasionally says `Discord verification is unavailable` while publishing a config.
- Another Discord member cannot complete website sign-in.
- Screenshots show the temporary Hostinger domain (`*.hostingersite.com`) while the intended public address is `timonconfigs.com`.

## Source-level findings

1. `TC_Discord::can_manage_configs()` forces verification on EVERY Owner write. The 10-second request cooldown can reject rapid actions. This does **not** log out the WordPress user or remove the role, but the Owner form shows an unavailable screen. Do not shorten the retry interval or bypass write checks.
2. Previously, a forced role check dropped `_tc_discord_status` BEFORE the request. If the API timed out, even a valid 60-second read-only verification was lost; another GET rendered the unavailable error. Core 0.8.9 retains only unexpired role caches through forced-check failures; the denied write is **never** authorized from the cache. Expired/mismatched cache is discarded. An ordinary read still has the pre-existing 60-second TTL.
3. HTTP 429 now respects capped Discord `Retry-After` to avoid piling on more requests. The Owner panel shows an approximate number of seconds to retry, not a generic repeated message. No raw Discord API response or tokens are displayed.
4. A production **Restricted pilot** (`pilot_enabled=1`) limits OAuth2 to the configured maximum 10 Discord user IDs. It prevents all other users from joining even with Free Member. Core provides a clear `Limited beta access` message instead of an unexplained generic error. It is also intentional that sign-in refuses linking a new Discord ID to an unrelated existing WordPress session.

## Owner site configuration — REQUIRED manual checks

1. Confirm **Settings → General → WordPress Address and Site Address** use the SAME desired canonical HTTPS host (`https://timonconfigs.com`), not a temporary Hostinger domain, and verify that both are accessible. Do not change blindly before backing up and planning domain cutover.
2. In Discord Developer Portal → OAuth2 → Redirects, register the callback for the configured WordPress home exactly: `https://timonconfigs.com/?tc_discord_callback=1` (only when that is the actual canonical `home_url()`). The configured client secret must match that SAME Discord application. Avoid mixing Hostinger temporary host and public domain in a single OAuth round-trip because the state cookie is host-bound.
3. In WordPress → **Settings → Discord membership**, read the redacted **Last Discord verification failure** stage/reason/status. HTTP 429 indicates provider rate limiting; `pilot_denied` means the user is not in the allowlist; `account_conflict` means already logged into a different WordPress identity or conflicting binding; transport/401/403 require investigating the server/Discord credentials/scopes.
4. Do not remove Restricted Pilot to troubleshoot a failure until real staging tests pass with Owner, new Free Member, VIP Monthly, VIP Lifetime and blocked guest; back up before enabling public production access. Disabling Pilot in production also requires the deliberate `staging_confirmed` flag and valid OAuth settings.
5. LiteSpeed/Cloudflare/CDN must NEVER cache `account`, `?tc_manage_configs`, OAuth start/callback, `admin-post.php` or protected download/Values routes. Check cookies on the canonical hostname, `SameSite=Lax`, `Secure`, and callback query parameters.
6. Wait the shown retry interval between upload, save and publish operations. The form should not lose an existing published config or remove purchased VIP roles when Discord is briefly unavailable.

## Test boundaries

- CI unit/integration mocks are run on disposable WordPress, not Hostinger; they cannot confirm DNS, Discord OAuth consent screen, actual session cookies, network routing, Live Discord role changes or real-user failure.
- New tests exercise HTTP 429 backoff, bounded cached display, forced verification fail-closed, pilot/identity diagnostics, credential binding and existing security regression checks.
- Core 0.8.9 is source code only until separately installed on Hostinger. Production rollout requires site backup, staging and confirmation using real test accounts. Do not touch payments or role automation.