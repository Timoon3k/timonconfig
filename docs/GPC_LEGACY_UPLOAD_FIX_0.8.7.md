# TimonConfigs Core 0.8.7 / theme 0.10.6 — legacy .gpc upload fix

## Reported behavior
The Owner upload of `PREDATOR SCRIPT S29_PIN_350305.gpc` fails with a diagnostic stating that PHP finfo classifies it as `text/plain` but the former text-only Unicode/control-character predicate rejects it as `binary or unsupported encoding`. A screenshot also shows the **Upload script files** button stretching vertically when the AJAX error banner is inserted beside it.

The actual user .gpc bytes have **not** been provided, so the following is a source-level fix for the reproducible `text/plain` + legacy control-byte case, not a claim that this exact proprietary file has been tested.

## Implementation
- For **.gpc only**, accept `text/plain` with legacy control bytes (e.g. vertical tab, form feed, ESC) or undefined CP1252 bytes. If strict text decoding fails on a legacy unmarked GPC but PHP identifies it as `text/*` and it does not contain NUL bytes, inspect original bytes rather than automatically rejecting it.
- Preserve **all original bytes** and the SHA-256 checksum. Never rewrite script source content to UTF-8.
- Keep rejection of executable/active web code, archive signatures, PHP content, malicious filename/path extensions, zero-size and oversized uploads, scanner veto, and invalid or untrusted private storage. Binary backups should retain their correct ZNBK extension. TXT, JSON, CFG, INI and XIMMR policies are unaffected.
- Show a more accurate Owner UI message and place async error/status feedback on its own full grid row. The upload CTA remains normal-sized.
- Keep signed downloads and Discord entitlements intact; no production secrets, payments or roles were touched.

## Test plan and constraints
- `tests/text-upload-integration.php`: replicate `text/plain` source GPC with representative control and codepage bytes while asserting byte-for-byte preservation, rejection of analogous TXT, and continued rejection of disguised executable/ZIP/PHP content.
- `tests/private-files-integration.php`: existing private storage and authorization regression checks.
- Run CI disposable WordPress integration and PHP lint; optionally perform browser visual regression on Owner panel in staging.
- After approval, update **both** Core 0.8.7 and theme 0.10.6 on Hostinger and clear relevant LiteSpeed/CDN caches. Version-controlled GitHub changes alone do not update Hostinger.
- If the same specific file still fails, capture only its type, size and harmless structural diagnostics (not paid content) or supply the real sample for authorized offline validation. Never claim an exact file is accepted without testing it.
