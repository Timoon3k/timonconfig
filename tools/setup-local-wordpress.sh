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
wp rewrite structure '/%postname%/' --hard

home_id="$(wp post list --post_type=page --name=home --field=ID --format=ids)"
if [[ -z "$home_id" ]]; then
  home_id="$(wp post create --post_type=page --post_title=Home --post_name=home --post_status=publish --porcelain)"
fi

pricing_id="$(wp post list --post_type=page --name=pricing --field=ID --format=ids)"
if [[ -z "$pricing_id" ]]; then
  pricing_id="$(wp post create --post_type=page --post_title=Pricing --post_name=pricing --post_status=publish --porcelain)"
fi

games_id="$(wp post list --post_type=page --name=games --field=ID --format=ids)"
if [[ -z "$games_id" ]]; then
  games_id="$(wp post create --post_type=page --post_title=Games --post_name=games --post_status=publish --porcelain)"
fi

for page_slug in vip-monthly vip-lifetime; do
  page_id="$(wp post list --post_type=page --name="$page_slug" --field=ID --format=ids)"
  if [[ "$page_slug" == "vip-monthly" ]]; then
    page_title="VIP Monthly"
  else
    page_title="VIP Lifetime"
  fi
  if [[ -z "$page_id" ]]; then
    wp post create --post_type=page --post_title="$page_title" --post_name="$page_slug" --post_status=publish >/dev/null
  else
    wp post update "$page_id" --post_title="$page_title" >/dev/null
  fi
done

policy_slugs=(faq contact video-showcase terms-of-service privacy-policy refund-policy)
policy_titles=(FAQ Contact "Video Showcase" "Terms of Service" "Privacy Policy" "Refund Policy")
for index in "${!policy_slugs[@]}"; do
  page_slug="${policy_slugs[$index]}"
  page_title="${policy_titles[$index]}"
  page_id="$(wp post list --post_type=page --name="$page_slug" --field=ID --format=ids)"
  if [[ -z "$page_id" ]]; then
    wp post create --post_type=page --post_title="$page_title" --post_name="$page_slug" --post_status=publish >/dev/null
  else
    wp post update "$page_id" --post_title="$page_title" --post_status=publish >/dev/null
  fi
done

wp option update show_on_front page
wp option update page_on_front "$home_id"

if ! wp menu list --fields=name --format=csv | grep -qx 'Primary'; then
  wp menu create Primary
  wp menu item add-post Primary "$home_id" --title=Home
  wp menu item add-post Primary "$pricing_id" --title=Pricing
  wp menu item add-custom Primary Configs http://127.0.0.1:8080/configs/
  wp menu item add-custom Primary FAQ http://127.0.0.1:8080/faq/
fi
faq_item_id="$(wp menu item list Primary --fields=db_id,title --format=csv | awk -F, '$2 == "FAQ" { print $1; exit }')"
if [[ -n "$faq_item_id" ]]; then
  wp menu item update "$faq_item_id" --url=http://127.0.0.1:8080/faq/ >/dev/null
fi
if ! wp menu item list Primary --fields=title --format=csv | grep -qx 'Games'; then
  wp menu item add-post Primary "$games_id" --title=Games
fi
wp menu location assign Primary primary

if ! wp menu list --fields=name --format=csv | grep -qx 'Footer'; then
  wp menu create Footer
  wp menu item add-post Footer "$pricing_id" --title=Pricing
fi
for page_slug in contact terms-of-service privacy-policy refund-policy; do
  page_id="$(wp post list --post_type=page --name="$page_slug" --field=ID --format=ids)"
  page_title="$(wp post get "$page_id" --field=post_title)"
  if ! wp menu item list Footer --fields=title --format=csv | grep -Fxq "$page_title"; then
    wp menu item add-post Footer "$page_id" --title="$page_title" >/dev/null
  fi
done
wp menu location assign Footer footer

curl --silent --fail --output /dev/null http://127.0.0.1:8080/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/pricing/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/games/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/vip-monthly/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/vip-lifetime/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/faq/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/contact/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/video-showcase/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/terms-of-service/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/privacy-policy/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/refund-policy/
echo "TimonConfigs local WordPress is ready on port 8080."

