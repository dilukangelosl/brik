#!/usr/bin/env bash
# Installs WordPress in the dev containers and activates Brik.
set -e
cd "$(dirname "$0")"
docker compose up -d
sleep 5
./sync.sh
until docker compose run --rm cli core is-installed >/dev/null 2>&1 || docker compose run --rm cli db check >/dev/null 2>&1; do sleep 2; done
docker compose run --rm cli core install --url=http://localhost:8888 --title="Brik Dev" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email || true
docker compose run --rm cli rewrite structure '/%postname%/' --hard
docker compose run --rm cli plugin activate brik-builder
docker compose run --rm cli theme activate brik || true
