#!/usr/bin/env bash
# Copies the plugin and theme into the running WordPress container.
set -e
cd "$(dirname "$0")"
c=$(docker compose ps -q wordpress)
docker exec "$c" rm -rf /var/www/html/wp-content/plugins/brik-builder /var/www/html/wp-content/themes/brik /var/www/html/wp-content/themes/brikwp
docker cp ../plugin/brik-builder "$c":/var/www/html/wp-content/plugins/brik-builder
docker cp ../theme/brikwp "$c":/var/www/html/wp-content/themes/brikwp
docker exec "$c" chown -R www-data:www-data /var/www/html/wp-content/plugins/brik-builder /var/www/html/wp-content/themes/brikwp
