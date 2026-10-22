# PR #1 — member library review

Reviewed the actual PR head through `refs/pull/1/head` (`c2dad49`). GitHub's GraphQL API returned Forbidden, so review comments/approval state were not available. This document records a source review, not a submitted GitHub approval.

The preliminary upload class correctly required administrator/edit-post capability and a post-specific nonce, kept files outside known public roots, generated opaque filenames, and had no public download endpoint.

Changes required before enabling downloads:

- ZIP was accepted without archive inspection. Archives are now rejected; binary device formats require an explicitly configured scanner/format inspector.
- Request-provided size was trusted. Actual bytes, MIME/content structure, JSON validity, executable/polyglot signatures and per-operation limits are now checked.
- Storage lacked library/config quotas, concurrent-write locking, explicit errors and guarded file lifecycle operations. The hardened implementation adds these without silently overwriting duplicate original names.
- File metadata is now ordered and versioned; replace/delete require confirmation and leave append-only audit events. Replacement receives a new opaque ID and invalidates old references.
- Display metadata excludes storage paths/names and stays out of REST. Path, symlink, size and integrity checks are repeated during resolution; checksumless legacy files cannot enter the new download pipeline until revalidated.

Independent review of the new integration also found and fixed a refresh-lease ownership race, an unnecessary forced-refresh cooldown that blocked immediate downloads, and a same-size file replacement race between inspection and streaming. Download delivery verifies the hash of the opened descriptor before writing bytes.

OAuth remains disabled until the owner supplies real client/guild/role configuration and confirms real staging account tests. Mock provider tests do not prove live Discord/PKCE availability. No Upgrade.chat, Stripe, PayPal, Security Bot, Ticket Tool or Discord-role automation was changed.
