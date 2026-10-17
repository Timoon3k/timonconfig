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
  "$theme/assets/css/main.css"
  "$theme/assets/js/main.js"
  "$plugin/timonconfigs-core.php"
  "$plugin/includes/class-tc-checkout.php"
  "$plugin/includes/class-tc-settings.php"
)

for file in "${required_files[@]}"; do
  test -s "$file" || { echo "Missing required file: $file"; exit 1; }
done

test "$(find "$theme/assets/video" -type f -name '*.mp4' | wc -l)" -eq 3
test "$(find "$theme/assets/images" -type f -name 'apex-demo-*.jpg' | wc -l)" -eq 3

rg -q "array\( 'https' \)" "$plugin/includes/class-tc-checkout.php"
rg -q "upgrade\.chat" "$plugin/includes/class-tc-checkout.php"
rg -q "manage_options" "$plugin/includes/class-tc-settings.php"
rg -q "nocache_headers" "$plugin/includes/class-tc-checkout.php"
rg -q "preload=\"none\"" "$theme/front-page.php"
rg -q "prefers-reduced-motion" "$theme/assets/css/main.css"

if rg -ni "10,000|100,000|4\.9/5|99% uptime|guaranteed|undetectable|bypass anti-cheat" "$theme" --glob '!assets/video/*'; then
  echo "Unverified marketing claim detected"
  exit 1
fi

node --check "$theme/assets/js/main.js"
echo "Project structure, checkout safeguards, content claims, media, and JavaScript checks passed."

