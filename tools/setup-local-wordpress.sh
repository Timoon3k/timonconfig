#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose_file="$project_root/docker-compose.local.yml"

find "$project_root/wp-content/themes/timonconfigs" "$project_root/wp-content/plugins/timonconfigs-core" -type d -exec chmod 755 {} +
find "$project_root/wp-content/themes/timonconfigs" "$project_root/wp-content/plugins/timonconfigs-core" -type f -exec chmod 644 {} +

docker compose -f "$compose_file" up -d db wordpress

for attempt in $(seq 1 40); do
  if curl --silent --fail --output /dev/null http://127.0.0.1:8080/wp-admin/install.php; then
    break
  fi
  if curl --silent --fail --output /dev/null http://127.0.0.1:8080/; then
    break
  fi
  if [[ "$attempt" -eq 40 ]]; then
    echo "WordPress did not become ready." >&2
    exit 1
  fi
  sleep 2
done

wp() {
  docker compose -f "$compose_file" run --rm cli wp "$@"
}

if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url=http://127.0.0.1:8080 \
    --title='TimonConfigs Local' \
    --admin_user=timonadmin \
    --admin_password='local-preview-only' \
    --admin_email=local@example.test \
    --skip-email
fi

wp plugin activate timonconfigs-core
wp theme activate timonconfigs

# The same explicit installer is available under Tools → TimonConfigs setup.
# It fills missing/empty content without replacing authored pages or assigned menus.
wp eval '
if (!class_exists("TC_Site")) { WP_CLI::error("TimonConfigs Core site installer is unavailable."); }
TC_Catalog::register_content_types();
TC_Catalog::register_landing_rewrites();
TC_Catalog::seed_core_terms();
if (method_exists("TC_Catalog", "seed_flagship_drafts")) { TC_Catalog::seed_flagship_drafts(); }
$result = TC_Site::install();
if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
WP_CLI::success("Site structure ready: " . count($result) . " editorial pages.");
'

wp rewrite structure '/%postname%/' --hard

routes=(
  / /configs/ '/configs/?tier=free' /games/ /pricing/ /reviews/ /faq/ /contact/
  /video-showcase/ /vip-monthly/ /vip-lifetime/
  /apex-legends-configs/ /call-of-duty-configs/ /fortnite-configs/
  /rainbow-six-siege-configs/ /rust-configs/ /ea-sports-fc-configs/ /nba-2k-configs/
  /cronus-zen-configs/ /xim-matrix-configs/
  /terms-of-service/ /privacy-policy/ /cookie-policy/ /refund-policy/ /lifetime-license-terms/
)
for route in "${routes[@]}"; do
  curl --silent --show-error --fail --output /dev/null "http://127.0.0.1:8080$route"
done

echo "TimonConfigs local WordPress is ready on port 8080; ${#routes[@]} storefront routes passed."
