# TimonConfigs SEO launch — XIM Matrix Apex Legends first

**Status:** editorial / configuration handbook, not a report of Google rankings or verified live HTTP. Created October 2026. The public site could not be fetched by the assistant; verify with Google Search Console and an external browser. Codebase already has custom fallback SEO metadata, automatic noindex of genuinely thin/empty game collections, and detects AIOSEO/Yoast/Rank Math to avoid duplicate tags. New SEO plugin will control title/meta when active. WordPress database pages and Owner-added configs are not mirrored in GitHub, so no assertion is made about a particular page being published or its content.

## Primary SEO objective and architecture

Lead keyword: **xim matrix apex legends**. SEO cannot guarantee rank 1.

Use ONE well-supported indexable landing page (prefer an edited existing published page over adding overlapping near-duplicates) with:
- URL candidate: /xim-matrix-apex-legends/ (verify slug availability and existing relevant pages first; if there is already an equivalent page, keep that URL and improve its content).
- Title: XIM Matrix Apex Legends Configs — Controller & MnK | TimonConfigs
- Meta description: Explore XIM Matrix Apex Legends configurations for controller and mouse & keyboard. Compare input setups, supported platforms, setup guides and Free/VIP access.
- H1: XIM Matrix Apex Legends Configs for Controller & MnK
- First paragraph: Find XIM Matrix configurations for Apex Legends in one place. Browse controller and mouse-and-keyboard releases, check supported platforms and current compatibility notes, and open each config for setup steps, values, updates and access requirements. Availability and requirements are shown on individual release cards.
- Suggested H2s: XIM Matrix Apex Legends Configuration Library; Controller vs MnK: Choose Your Input; Compatible Platforms and Game Settings; Setup, Installation and Troubleshooting; VIP vs Free Access; Frequently Asked Questions; Latest Release Notes.
- Helpful content required: genuine screenshots of menus, first-party footage, actual setup step-by-step, target supported devices/platforms, firmware, dates tested, troubleshooting, author attribution and real update history. Do NOT assert aim performance, bans or guaranteed competitive advantage.
- Actual released config cards should link from that page by full title; link back to the hub from relevant configs. Do not invent product details or performance metrics.

**Avoid** cannibalization: homepage mentions XIM Matrix Apex Legends and links clearly to dedicated hub; /apex-legends-configs/ is game umbrella; /xim-matrix-configs/ is device umbrella; individual /config/ pages focus on the exact release, not the broad keyword. Use canonical and internal links. Avoid separate near-identical controller/mnk landing pages unless substantial, genuinely distinct documentation exists.

## Secondary keywords and metadata proposals

| Target | Proposed SEO title | Suggested H1 |
| --- | --- | --- |
| Homepage | XIM Matrix & Cronus Zen Configs for Apex Legends | Apex Legends Configs for XIM Matrix & Cronus Zen |
| Apex game archive | Apex Legends Configs for XIM Matrix & Cronus Zen | Apex Legends Configuration Library |
| XIM device archive | XIM Matrix Configs for Apex Legends & CoD | XIM Matrix Config Library |
| Cronus device archive | Cronus Zen Apex Legends & Call of Duty Configs | Cronus Zen Config Library |
| CoD game archive | Call of Duty Configs for XIM Matrix & Cronus Zen | Call of Duty Configuration Library |
| Per-release Predator | Apex Legends Predator S30 Config — Setup & Download | Apex Legends Predator S30 |
| Per-release Classic | Timon S30 VIP Classic — Apex Legends Configuration | Timon S30 VIP Classic |
| Per-release Roller | Timon S30 Roller — Apex Legends Configuration | Timon S30 Roller |

**Important:** Verify each flagship config's actual device, supported game version, platform, tier and file availability in the WordPress editor BEFORE setting page descriptions, categories or schema. Differentiate individual products by REAL detailed values/guides, not generic duplicated descriptions. Do not auto-publish empty/coming-soon collections or thin taxonomy archives.

Individual config meta description pattern: "View [CONFIG], an Apex Legends configuration for [VERIFIED DEVICE]. Check [VERIFIED INPUT], supported platforms, setup values, current release status and membership access."

## WordPress / AIOSEO recommended settings

1. Install **only one** SEO suite; AIOSEO is a compatible choice. Do not activate duplicate Yoast/Rank Math/AIOSEO concurrently. The TimonConfigs theme suppresses its own fallback metadata when a supported SEO plugin is active.
2. Settings > Reading: **Discourage search engines from indexing this site = OFF**. Only switch this off when intentionally launching the public site; continue to keep private account/admin/download URLs excluded.
3. Settings > General: Site address and WordPress address should use canonical **https://timonconfigs.com** (confirm actual hosting routing and SSL first, then plan redirect from Hostinger temporary domain; do not blindly change in live WordPress).
4. AIOSEO Search Appearance > Homepage: set the verified homepage title/meta listed above. The first visible H1 should reflect the actual page purpose. Avoid multiple unrelated primary H1s and keyword repetition.
5. AIOSEO Search Appearance > Content Types: **Pages = Index**, **Configs / tc_config = Index** only for published available configurations with unique useful public copy. Do not index private member dashboards; **Reviews / tc_review** should not be indexed if they are only site-layout demo examples. Do not index private media attachments, empty archives or tag spam. Confirm a noindex condition for drafts/coming-soon.
6. AIOSEO Search Appearance > Taxonomies: index **Games / tc_game** and **Devices / tc_device** ONLY when there are genuinely useful, released entries with unique descriptions; check existing TC_Catalog noindex rules. Disable thin/empty default tags and unused categories.
7. AIOSEO XML Sitemap: enable, include public Pages / public released tc_config and useful tc_game/tc_device collections; exclude logins, account, cart/checkout, private files, search results, internal test content and demo review archives. Choose an actual sitemap URL from the plugin (often **/sitemap.xml** for AIOSEO) and verify it returns valid XML/200; do not confuse with WordPress core /wp-sitemap.xml or Yoast /sitemap_index.xml. Exclude unpublished/coming-soon placeholders.
8. AIOSEO Social: a genuine first-party 1200x630 image, branded, correctly licensed; site name and social cards. Set per-page images where relevant.
9. AIOSEO Schema: usually WebSite/Organization (or correct factual entity), BreadcrumbList where correct, and factual Product/SoftwareApplication ONLY where properties and offers are real. **Do not create fake Review or AggregateRating schema**. Do not claim nonexistent stock/price, effectiveness or testimonials. Ensure metadata matches actual checkout prices.
10. Verify duplicate tags: inspect source HTML to confirm **one** title, description, canonical, OG set. The base theme has fallback metadata and schema; provider precedence should be confirmed in the final rendered HTML.
11. Keep LiteSpeed enabled with correct guest page cache, while excluding login/OAuth callback, account, Member Dashboard, protected file downloads and checkout endpoints; NEVER cache an authenticated person's private content across visitors.
12. Images: unique first-party screenshots or licensed covers with sensible alt texts, width/height, WebP/AVIF and lazy loading for below-fold imagery; hero/LCP image should not be lazy-loaded.
13. Internal links: home > XIM Matrix Apex hub > exact release; game and device archives > hub and releases; tutorials > hub; link from YouTube/Discord ABOUT (where platform rules permit). Avoid bought links, doorway sites and keyword stuffing.

## Flagship Apex S30 content plan

- **Predator S30**: dedicated release card and guide with screenshots, actual device type, author, tested game patch/date, versioned changes, platform, input method, settings and documented issues.
- **Timon S30 VIP Classic**: own release changelog, description of *verified* Classic controller curve/setup, differences from other products, screenshots and access tier.
- **Timon S30 Roller**: own release changelog and verified controller-specific instructions; no promise of aim assist or recoil suppression improvements without evidence.
- Make a public, non-sensitive setup excerpt for each (don't expose paid file contents). Include honest compatibility limitations after Apex updates.

## Google Search Console — Domain property

1. Go to https://search.google.com/search-console/
2. Add property > **Domain**; enter exactly \`timonconfigs.com\` (without scheme/path).
3. Copy **the TXT value generated for your own property**. Do not copy an example value from the internet.
4. Open whichever DNS provider actually serves the domain's nameservers (Hostinger DNS Zone, or Cloudflare if delegated). Add TXT host \`@\` or provider's apex field and value \`google-site-verification=...\`. Leave all existing DNS records in place. Wait for propagation, then click Verify.
5. Open **Sitemaps** > submit the real plugin sitemap path after confirming it opens publicly (AIOSEO typically \`sitemap.xml\`). If the site isn't crawlable from outside, investigate DNS, robots and HTTP before repeatedly submitting.
6. Open URL Inspection for homepage, the primary XIM Apex hub and 2-3 released flagship pages. Click **Test Live URL**. Check HTTP 200, non-blocked crawl, no noindex, canonical points to the real domain. Only then click Request Indexing.
7. Review Pages > Not indexed, Performance > Search results > Queries (target keyword position and impressions), Core Web Vitals and Enhancements every week. Segment branded/non-branded queries; track results over 3-6 months, not from one scrape.
8. Optional: also verify URL-prefix property \`https://timonconfigs.com/\` to help integration with tools, but the Domain property covers http/https, www/non-www and subdomains.

## Non-negotiable data integrity

- Public SEO crawlable pages must not expose private download URLs, tokens, member PII or unprotected paid files.
- Do not over-optimize legal pages for high-volume search queries.
- SEO plugin scores are checklists, not ranking guarantees. Quality, backlinks, intent fit, indexing, and actual competition are decisive.
- Do not publish fake testimonials, inflated review schema, fabricated client numbers or affiliate claims.

Documentation references:
https://developers.google.com/search/docs/crawling-indexing/ask-google-to-recrawl
https://support.google.com/webmasters/answer/9008080?hl=en
https://aioseo.com/docs/how-to-create-an-xml-sitemap/
https://aioseo.com/docs/setting-the-seo-title-and-description-for-your-content/
