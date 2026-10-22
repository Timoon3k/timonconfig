# TimonConfigs URL verification

This report records observed checks. Local responses do not establish deployment to the public website. A CONNECT/proxy failure is an environment access failure, not an observed WordPress HTTP response.

Local run: 2026-10-22T19:33:46.170Z.
Public run: 2026-10-22T01:21:08.869Z.

| Route | Local HTTP | H1 | Canonical count | Robots | Public result |
| --- | ---: | ---: | ---: | --- | --- |
| / | 200 | 1 | 1 | max-image-preview:large | blocked by environment proxy |
| /configs/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /configs/?tier=free | 200 | 1 | 1 | max-image-preview:large, noindex | not checked origin unreachable |
| /games/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /pricing/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /vip-monthly/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /vip-lifetime/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /apex-legends-configs/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /cronus-zen-configs/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /xim-matrix-configs/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /call-of-duty-configs/ | 200 | 1 | 1 | max-image-preview:large, noindex | not checked origin unreachable |
| /fortnite-configs/ | 200 | 1 | 1 | max-image-preview:large, noindex | not checked origin unreachable |
| /rainbow-six-siege-configs/ | 200 | 1 | 1 | max-image-preview:large, noindex | not checked origin unreachable |
| /rust-configs/ | 200 | 1 | 1 | max-image-preview:large, noindex | not checked origin unreachable |
| /ea-sports-fc-configs/ | 200 | 1 | 1 | max-image-preview:large, noindex | not checked origin unreachable |
| /nba-2k-configs/ | 200 | 1 | 1 | max-image-preview:large, noindex | not checked origin unreachable |
| /faq/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /reviews/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /contact/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /video-showcase/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /terms-of-service/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /privacy-policy/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /refund-policy/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /cookie-policy/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |
| /lifetime-license-terms/ | 200 | 1 | 1 | max-image-preview:large | not checked origin unreachable |

## Local behavior checks

Result: **passed**. 55 route/viewport checks.

- catalogueFilters: search/game/device/input/platform/tier controls present; submitted filters retain collection context; malformed scalar and array parameters handled.
- navigation: ["Home","Configs","Games","Pricing","Reviews","FAQ","Contact"].
- sitemap: {"status":200,"discoveredURLs":30,"emptyCollectionsExcluded":true}.
- gutenberg: {"registeredBlocks":["timonconfigs/section"],"editorialTypes":["tc_config","tc_review","tc_faq","tc_video"],"homeBlocks":[{"name":"timonconfigs/section","section":"hero","valid":true},{"name":"timonconfigs/section","section":"assurance","valid":true},{"name":"timonconfigs/section","section":"search","valid":true},{"name":"timonconfigs/section","section":"games","valid":true},{"name":"timonconfigs/section","section":"pricing","valid":true},{"name":"timonconfigs/section","section":"benefits","valid":true},{"name":"timonconfigs/section","section":"reviews","valid":true},{"name":"timonconfigs/section","section":"videos","valid":true},{"name":"timonconfigs/section","section":"faq","valid":true},{"name":"timonconfigs/section","section":"cta","valid":true}],"renderedPreviews":10,"editorErrors":[],"editorEnvironmentFailures":[{"host":"secure.gravatar.com","reason":"blocked_by_environment_proxy"},{"host":"secure.gravatar.com","reason":"blocked_by_environment_proxy"}],"savedContent":false}.
- browserErrors: [].

## Synthetic performance observations

These are single local browser observations under the current machine load. They are not field Core Web Vitals, a Lighthouse score, or a verified INP measurement.

| Viewport | LCP (ms) | CLS | Observed interaction duration (ms) |
| --- | ---: | ---: | ---: |
| 1440px | 124 | 0 | 56 |
| 390px | 88 | 0 | 56 |

## Additional Gutenberg UI verification

Run: 2026-10-21T19:17:13.213Z. Result: **passed**.

The real Home editor contained 10 valid storefront sections with section attributes and 10 rendered server previews. Selecting Hero exposed its Heading control. Editorial REST types were available. Page content was not saved.

Application editor errors: [].

WordPress admin assets from secure.gravatar.com, s.w.org were blocked by the environment proxy. These verified external-resource failures are recorded separately from React/application exceptions.

## Public access and Discord invitation

Origin: https://timonconfigs.com.

The environment proxy rejected the HTTPS CONNECT request. Remaining routes were not attempted; their public HTTP status is unknown. No claim of public-site 404 or availability can be made.

Discord invite API: blocked_by_environment_proxy. Invitation validity remains unverified because the API request could not complete.

Payment transactions, existing checkout products, subscription state, and Discord role changes are outside these automatic checks.
