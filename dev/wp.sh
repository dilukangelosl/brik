#!/usr/bin/env bash
# Run WP-CLI against the dev site: dev/wp.sh plugin list
cd "$(dirname "$0")"
exec docker compose run --rm -T cli "$@"
