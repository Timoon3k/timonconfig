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

wp option update show_on_front page
wp option update page_on_front "$home_id"

if ! wp menu list --fields=name --format=csv | grep -qx 'Primary'; then
  wp menu create Primary
  wp menu item add-post Primary "$home_id" --title=Home
  wp menu item add-post Primary "$pricing_id" --title=Pricing
  wp menu item add-custom Primary Configs http://127.0.0.1:8080/configs/
  wp menu item add-custom Primary FAQ http://127.0.0.1:8080/pricing/#faq
fi
wp menu location assign Primary primary

if ! wp menu list --fields=name --format=csv | grep -qx 'Footer'; then
  wp menu create Footer
  wp menu item add-post Footer "$pricing_id" --title=Pricing
fi
wp menu location assign Footer footer

curl --silent --fail --output /dev/null http://127.0.0.1:8080/
curl --silent --fail --output /dev/null http://127.0.0.1:8080/pricing/
echo "TimonConfigs local WordPress is ready on port 8080."

