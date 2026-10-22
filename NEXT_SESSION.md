# Next session

1. Read `PROGRESS.md`, `docs/HOME_FIX_0.7.1.md`, `docs/COMPLETION_REPORT_0.7.md`, and `docs/URL_STATUS.md`. Preserve theme **0.7.1 / Core 0.5.0** and the existing `tc_config` records. The latest-posts Home regression is fixed and tested; do not reinstate the unconditional front-page post loop. Do not regenerate the project or repeat a full audit without new evidence. Start local WordPress with `bash tools/setup-local-wordpress.sh` if stopped.
2. Configure the owner's actual **Monthly and Lifetime product checkout URLs**, support email, social links, approved legal identity/terms, and confirmed video-to-device mapping. The supplied Discord invite `https://discord.gg/RyvzSqrF6` is already the editable default; external verification remains blocked. Unconfigured checkout intentionally returns HTTP 503 and never grants access.
3. Review the local frontend and CMS with `docs/CMS_GUIDE.md`. Home has ten reorderable Gutenberg sections; collection art, configs, FAQs, reviews, videos, shared prices, and purchase links have admin controls. Review metadata now passes real authenticated REST round-trip tests. Replace labelled demo reviews and publish Apex draft configs only with approved release content. Original logo/Hero source assets remain needed for exact asset matching.
4. Retry the public-site/Discord checks once the saved network configuration is published. Current access failures are environment proxy failures, not observed public-site HTTP failures. The draft retains timonconfigs.pl/www.timonconfigs.pl, timonconfigs.com/www.timonconfigs.com, discord.com, and discord.gg. Check which version the public site actually serves before claiming deployment.
5. The next agreed **Astra Medium** stage is Discord OAuth2 Authorization Code Flow (state/PKCE where supported), account binding, token/session handling and logout, current guild/role verification, protected premium delivery outside public uploads, and authorization/revocation/security tests. Do not start that stage with invented secrets, guild/role IDs, or a second role/payment system. Security Bot, Ticket Tool, Upgrade.chat, Stripe, and PayPal remain authoritative for their existing responsibilities.
6. Before production: test archive installation and supported SEO plugins on isolated staging, finish owner/legal review, measure staging performance, inspect Hostinger backups/access when callable hosting tools are available, and obtain explicit production approval. No live database, subscription, customer, or Discord-role changes are authorized.

Useful targeted checks:

```bash
bash tests/verify-project.sh
docker compose -f docker-compose.local.yml run --rm -T cli wp eval-file - < tests/catalog-integration.php
docker compose -f docker-compose.local.yml run --rm -T cli wp eval-file - < tests/editorial-integration.php
node tests/website-browser.cjs
```

Other existing integration suites are `seo`, `video`, `site`, `blocks`, and `checkout`. Browser suites also include `tests/storefront-browser.cjs` and `tests/video-browser.cjs`. Run checks appropriate to actual changes; generated results and private local backups belong only in ignored `artifacts/`.

Release ZIPs are `artifacts/timonconfigs-theme-0.7.1.zip` and `artifacts/timonconfigs-core-0.5.0.zip`, with refreshed `*-current.zip` copies. The 0.7.1 package passed 62 fresh-installation assertions; Core sources are unchanged. On fresh WordPress, the existing Privacy Policy draft stays unpublished until reviewed deliberately. The focused homepage regression is `node tests/front-page-browser.cjs` (disposable localhost:8080 only); it restores Reading/plugin settings and deletes its fixtures. Apply the Hostinger setup/Reading steps in `docs/HOME_FIX_0.7.1.md` and verify the remote result after the owner applies the package/settings; do not claim that local changes are already deployed.
