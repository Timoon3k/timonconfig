# TimonConfigs — five additional visual references (user screenshots)

These **five** reference screenshots were provided in the user's chat after `docs/CODEX_NEXT_FEATURES_AND_SEO_2026-10-23.md` was created. They are visual material for the same implementation, **not evidence that third-party content may be copied**.

**Status:** This document transcribes and maps the five screenshots. The actual binary image attachments are in the source conversation; unless files have been uploaded under `docs/reference-images/`, do not claim that the image binaries themselves exist in Git. The textual descriptions are enough to start targeted UI changes. The screenshots are listed in the exact sequence they were received in this later message, **A–E**; do not accidentally map them to the previous brief's 1–6 scheme.

## A — Current home FAQ and final community CTA

Source attachment: `download (4).png` (approx. 1730 × 417).

Visible composition:
- FAQ block (upper half): dark background with *fantasy/sci-fi mountainous scene* running behind the FAQ; small eyebrow `FAQ`, left aligned two-line heading `Frequently Asked Questions`, compact `View all questions →`.
- Right side: five transparent dark accordion rows, plus icons, with example labels: `How do I receive my configs?`, `Do I need a Cronus Zen or XIM Matrix?`, `Which games are supported?`, `What does VIP Lifetime include?`, `Do you offer refunds?`.
- Bottom `READY TO IMPROVE` eyebrow and big `Join the TimonConfigs Community` heading. This section currently repeats a wide landscape/sci-fi background with an orange `Get Started` and dark `Join Discord` button.
- **Requested change:** REMOVE only the image/landscape/photo and all image-derived dark/gradient overlays from the **bottom CTA block**. Replace with a clean solid/consistent near-black surface. Keep heading, copy, divider, orange CTA, Discord button, spacing and mobile design. **Do not accidentally remove the FAQ background** (unless owner subsequently requests it).
- Check the actual source `wp-content/themes/timonconfigs/template-parts/home/cta.php`, corresponding Gutenberg section/component and `.tc-final-cta` CSS. Ensure no repeated background is inserted by global fallback, CSS pseudo-elements, `::before`, `::after`, or an image customizer setting.

## B — Current TimonConfigs home: header, quick searches, games and pricing

Source attachment: `download (3).png` (approx. 1701 × 914).

Visible design:
- Black/dark Header, orange-white jagged `TimonConfigs` logo; links `Home | Configs | Games | Pricing | Reviews | FAQ | Contact`, `Profile`, `Join Discord`, orange `Get Started`.
- Device/services strip: Cronus Zen (controller configs), XIM Matrix (controller & MnK), Free + VIP, Upgrade.chat official checkout.
- Search box `FIND YOUR PERFECT CONFIG`, subtitle `Search for your device, game, or setup...`. Popular searches chips: `Cronus Zen Configs`, `Apex Legends Configs`, `XIM Matrix Configs`, `Free Configs`, `VIP Monthly`, `Apex Gameplay`.
- Games grid: seven image covers Apex Legends, Call of Duty, Fortnite, Rainbow Six Siege, Rust, EA FC, NBA2K; many covers show `COMING SOON` text. Curated titles may be intentionally unreleased. Do not mark them available without real release data.
- Pricing cards: Free €0, VIP Monthly €40/month ("Most Popular"), VIP Lifetime €100 one time. Clean orange highlights, near-black surfaces, readable contrast.
- **Requested improvements:** add genuine relevant **XIM Matrix Apex Legends** and **XIM Matrix Call of Duty** topical links to popular searches (likewise Cronus Zen + Apex/CoD), linking to useful live/canonical destinations; enhance H1/H2 and descriptive home copy without keyword stuffing. Retain the actual visual identity. Ensure mobile menu and chips wrap, avoid horizontal scroll.

## C — Coldz.pro comparison: Call of Duty VIP script card and Guides/Values

Source attachment: `download (2).png` (approx. 1276 × 431). Third-party design inspiration **only**.

- A single row of three equal dark cards. Each has a widescreen Call of Duty/Ghost-like photo banner, clipped rounded top corners, a small gold `VIP` badge at top-right.
- Under picture: large bold script title (examples shown: `T-800 MW4`, `Zen king aim V7`, `Zen king Ultimate V5`), smaller `Creator: Coldz`, `Call of Duty`, a genuine download count and date. First card also has two small rounded actions `Guide` and `Values` on right.
- Bottom separated by a divider with a wide gold `Download` button and subtle glow.
- **Desired TimonConfigs behavior:** per-`tc_config` picture, title, creator, category, device, Free/VIP badge, factual date and download metric; conditional `Guide` and `Values` buttons only where content exists, each opening accessible text dialogs with copy action and correct entitlement checks. Free cards should use TimonConfigs brand-orange action; VIP cards may use the existing yellow/gold premium accent if consistent. **Do not copy the Ghost artwork or Coldz text/logo.**

## D — Coldz.pro comparison: Apex Legends catalog with five distinct cards

Source attachment: `download (1).png` (approx. 1452 × 808). Third-party design inspiration **only**.

- 3 cards across in first row + 2 cards in second row, *same Apex Legends cover art repeated across scripts*. Black/deep charcoal surface, rounded cards, white script titles, subtitle `Creator`, game label, download count, posted date, thick separating line and large bottom button.
- Some are Free with a **red** Download button; premium card has **gold VIP top-right corner** and **gold Download** button. Example text cards: `Scripted elite x Smoshi`, `S29 Zen Studio Live fix`, `Season 30 Apex predator`, `Panda Apex S30`, `Apex Ultimate`.
- **Desired TimonConfigs behavior:** one category contains many distinct `tc_config` cards. Each inherits a per-game cover if no unique featured image. Responsive 3/2/1 columns; consistent card height/footer action alignment, padding, hover/focus states, clear Free/VIP distinction. Add Device (XIM Matrix/Cronus Zen) and filter/sort controls above grid. Download counts only from actual completed authorized deliveries, with safe handling for no count or new posts.
- Show **Browse Memberships** upsell for unentitled Free Member on VIP card, not a usable download URL; authorize content on server. Do not reuse third-party card titles/images unless owner holds distribution/artwork rights.

## E — Current TimonConfigs authenticated member dashboard

Source attachment: `download.png` (approx. 1629 × 765).

- Top visible actions `Refresh Roles`, `Reconnect Discord`, `Browse Memberships`, verification hint.
- `Quick actions` 2×2 grid: `View Configs`, **`My Downloads`**, `Your Profile`, `Support`.
- Separate **`Recent downloads`** section with `Open My Downloads` link and empty state.
- Lower **`Your memberships`** section with Free, VIP Monthly and VIP Lifetime cards, plus Browse Memberships link.
- **Owner request:** remove `My Downloads` quick action, `Recent downloads` section and their redirects from the **OAuth profile dashboard**, as downloads should happen contextually on each published config page/card. Preserve legitimate download routes, logs/audit and secure token flow; avoid exposing a new page that weakens access controls. Keep profile, Discord badges, roles refresh, browse membership and useful Configs/Support shortcuts.
- Add meaningful **Staff** and **Owner** role labels based on verified Discord role IDs (while excluding `Unverified` from display), and show an Owner-only link to secure **Manage Configs** editor. Staff must not get write privileges. Paid membership tier must remain independent of staff status. Ensure logged-in Free Member sees a tasteful offer to view VIP Monthly/Lifetime plans.

## Implementation notes / constraints

1. Read companion `docs/CODEX_NEXT_FEATURES_AND_SEO_2026-10-23.md` and current `PROGRESS.md`/`NEXT_SESSION.md`; use real user images if available to the Codex workspace.
2. Do not treat this markdown as a replacement for exact pixels. The layout descriptions are enough to begin, but image matching and final visual QA require actual screenshot assets if not accessible in that session.
3. Current source areas to inspect: `wp-content/themes/timonconfigs/template-parts/home/cta.php`, `wp-content/themes/timonconfigs/inc/seo.php`, `wp-content/plugins/timonconfigs-core/includes/class-tc-member-area.php`, `class-tc-catalog.php`, `class-tc-discord.php`, and card templates/CSS/JS.
4. Keep real checkout unchanged; do not invent role IDs or public download counts; do not enable arbitrary unvalidated executables/archive uploads. Separate safe public art from private script assets.
5. No production deployment without owner approval. Write tests for role authorization, protected Guide/Values data, responsive catalog and no-cache behavior; document all UI changes and include actual before/after screenshots from staging.
