#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
theme="$project_root/wp-content/themes/timonconfigs"
plugin="$project_root/wp-content/plugins/timonconfigs-core"

required_files=(
  "$theme/style.css"
  "$theme/functions.php"
  "$theme/front-page.php"
  "$theme/page-pricing.php"
  "$theme/page-games.php"
  "$theme/page-vip-monthly.php"
  "$theme/page-vip-lifetime.php"
  "$theme/page-faq.php"
  "$theme/page-reviews.php"
  "$theme/page-contact.php"
  "$theme/page-terms-of-service.php"
  "$theme/page-privacy-policy.php"
  "$theme/page-refund-policy.php"
  "$theme/page-cookie-policy.php"
  "$theme/page-lifetime-license-terms.php"
  "$theme/inc/blocks.php"
  "$theme/inc/seo.php"
  "$theme/page-video-showcase.php"
  "$theme/patterns/cta.php"
  "$theme/patterns/faq.php"
  "$theme/patterns/hero.php"
  "$theme/patterns/pricing.php"
  "$theme/archive-tc_config.php"
  "$theme/single-tc_config.php"
  "$theme/assets/css/main.css"
  "$theme/assets/css/premium.css"
  "$theme/template-parts/video-cards.php"
  "$theme/template-parts/review-cards.php"
  "$theme/assets/images/hero-premium.webp"
  "$theme/assets/images/hero-reference-wide.webp"
  "$theme/assets/fonts/inter-latin-wght-normal.woff2"
  "$theme/assets/fonts/barlow-condensed-latin-700-normal.woff2"
  "$theme/assets/images/timonconfigs-logo.png"
  "$theme/template-parts/game-strip.php"
  "$theme/assets/js/main.js"
  "$theme/woocommerce.php"
  "$theme/page-tc-vip-checkout.php"
  "$theme/woocommerce/checkout/form-billing.php"
  "$theme/woocommerce/checkout/form-checkout.php"
  "$theme/assets/css/woocommerce.css"
  "$theme/assets/css/checkout-premium.css"
  "$plugin/timonconfigs-core.php"
  "$plugin/includes/class-tc-checkout.php"
  "$plugin/includes/class-tc-woocommerce.php"
  "$plugin/includes/class-tc-woo-checkout-presentation.php"
  "$project_root/tests/checkout-integration.php"
  "$project_root/tests/woo-checkout-presentation-integration.php"
  "$plugin/includes/class-tc-settings.php"
  "$plugin/includes/class-tc-catalog.php"
  "$plugin/includes/class-tc-editorial.php"
  "$plugin/includes/class-tc-site.php"
  "$plugin/includes/class-tc-seo.php"
  "$plugin/includes/class-tc-videos.php"
)

for file in "${required_files[@]}"; do
  test -s "$file" || { echo "Missing required file: $file"; exit 1; }
done

test "$(find "$theme/assets/video" -type f -name '*.mp4' | wc -l)" -eq 3
test "$(find "$theme/assets/images" -type f -name 'apex-demo-*.jpg' | wc -l)" -eq 3
test "$(find "$theme/assets/images" -type f -name 'game-*.webp' | wc -l)" -eq 7

rg -q "manage_options" "$plugin/includes/class-tc-settings.php"
rg -q "preload=\"none\"" "$theme/template-parts/video-cards.php"
rg -q "prefers-reduced-motion" "$theme/assets/css/main.css"
rg -q "register_post_type" "$plugin/includes/class-tc-catalog.php"
rg -q "wp_verify_nonce" "$plugin/includes/class-tc-catalog.php"
rg -q "tc_review" "$plugin/includes/class-tc-editorial.php"
rg -q "tc_faq" "$plugin/includes/class-tc-editorial.php"
rg -q "wp_verify_nonce" "$plugin/includes/class-tc-editorial.php"
rg -q "SAMPLE REVIEW · DEMO CONTENT" "$theme/template-parts/review-cards.php"
rg -q "BreadcrumbList" "$theme/inc/seo.php"
rg -q "Coming soon" "$theme/template-parts/game-strip.php"

# The owner-supplied €10,000 library valuation is distinct from fabricated user/download totals.
if rg --pcre2 -ni "(?<!€)10,000|100,000|4\.9/5|99% uptime|guaranteed|undetectable|bypass anti-cheat" "$theme" --glob '!assets/video/*'; then
  echo "Unverified marketing claim detected"
  exit 1
fi

node --check "$theme/assets/js/main.js"
node --check "$theme/assets/js/blocks.js"
echo "Project structure, WooCommerce integration, content claims, media, and JavaScript checks passed. Checkout behavior requires the WordPress + WooCommerce integration suite."

