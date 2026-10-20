# TimonConfigs 0.6.0 design correction

The 577 × 1024 mockup supplied in the conversation is the visual reference. The previous theme used the same broad colors but did not preserve its geometry. This pass changes the existing theme; Core 0.4.0 and its checkout settings are retained.

## Concrete corrections

- Increased the desktop content grid from 1240 to 1360 px at a 1440 px viewport, matching the reference's approximately 95% content width. Hero text retains its own 110 px inset.
- Rebuilt the premium CSS in readable section rules instead of accumulating competing overrides. Sticky navigation, mobile navigation, focus states, reduced motion, and inline video behavior are preserved.
- Added locally hosted Inter variable and Barlow Condensed fonts, including upstream SIL OFL licenses. Previously the theme declared Inter without supplying it, so browsers used Arial.
- Added an ultrawide decorative Hero reconstruction with the character and both hands/devices in the frame. It is a reconstruction, not the original source image; existing administrator-selected Hero URLs remain authoritative.
- Added large central game-title treatments over seven separate replaceable cover illustrations. Apex is available; the six planned games retain explicit Coming soon labels and link to the corresponding Games roadmap anchors.
- Moved each plan's short tagline beside the icon and name, emphasized Monthly with Most Popular, shortened Home card presentation, and corrected the Free CTA to use the Free filter. The full description remains on Pricing.
- Reduced excess spacing around games, pricing, reviews, and FAQ. Kept a smaller Apex video section as the explicitly requested addition to the original mockup.
- Preserved visible Join Discord buttons. If an invite has not been configured, they lead to Contact's community information rather than an invented invite or an unrelated catalogue action.
- Restored the primary navigation and configured social icons in the footer, with legal links and the required creator credit below.

## Measured desktop proportions

Measurements from the rendered local WordPress at 1440 px, after fonts and images load:

| Section | Theme 0.6.0 height | Approximate reference height at 1440 px |
| --- | ---: | ---: |
| Header | 77 px | 75–78 px |
| Hero | 540 px | 540–545 px |
| Trust bar | 104 px | 105 px |
| Search | 185 px | 190–195 px |
| Games | 245 px | 245 px |
| Pricing | 433 px | 425–435 px |
| Benefits | 190 px | 190–195 px |
| Reviews | 261 px | 245 px; demo labels require extra space |
| Apex video section | 400 px | User-requested addition |
| FAQ | 225 px | 215–220 px |
| Final CTA | 170 px | 185 px |
| Footer | 150 px | 125 px; legal links/credit retained |

These are proportional comparisons, not a claim of pixel identity. `node tools/capture-storefront.cjs` refreshes full-page screenshots for 1440, 1024, 768, 390, and 320 px and writes the measured geometry to `artifacts/storefront-geometry.json`.

## Unresolved source assets and live-site access

The original logo, layered or text-free Hero, and separate original game-cover assets are not present in the supplied repository/files. The existing fallback logo is retained rather than replaced with another invented brand. Upload the original logo via Site Identity and final artwork via TimonConfigs site content when supplied.

Read-only HTTPS access to `https://timonconfigs.pl/` failed at the environment's outbound proxy with `403 CONNECT tunnel failed`, including an escalated retry. This is not an observed HTTP response from the WordPress server. No live-site screenshot or deployed-version comparison was completed. Only `timonconfigs.pl` and `www.timonconfigs.pl` were added to the environment network draft; that draft requires review/save/publication before access can be retried.

Production WordPress, its database, checkout values, payments, and Discord roles were not modified. Review the preview, install the updated theme ZIP on staging, and compare the deployed assets before any production update.

## Validation performed

- All theme PHP files pass native `php -l` inside the WordPress PHP 8.2 container; static checks and JavaScript syntax pass.
- Playwright passes 35 route/viewport combinations, covering 7 public routes at 1440/1024/768/390/320 px, real in-card playback, deferred video loading, pause coordination, media retry, keyboard navigation/FAQ, search, and the review carousel.
- Read-only local QA checked 26 unique internal links, all HTTP 200. A stale `/pricing/#faq` navigation target exposed a missing anchor and an incorrect setup CLI flag; Pricing now preserves that legacy anchor, and setup uses `--link` to correctly point the FAQ menu entry to `/faq/`.
- Both CSS assets, both WOFF2 files, and the new Hero return HTTP 200. Screenshot capture confirms fonts/images are loaded before measuring. No horizontal overflow was observed at the five captured widths.
