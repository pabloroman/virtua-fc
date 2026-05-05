#!/usr/bin/env bash
# Thin wrapper around `docker compose` that loads the production env file and
# both compose files. Usage:
#
#   ./compose.sh ps
#   ./compose.sh logs -f --tail=200 app
#   ./compose.sh exec app php artisan tinker
#   ./compose.sh up -d
#   ./compose.sh pull
#
# Anything you'd type after `docker compose` is forwarded.

set -euo pipefail

ROOT="${VFC_ROOT:-/srv/virtua-fc}"
ENV_FILE="${VFC_ENV_FILE:-$ROOT/env/.env}"
COMPOSE_DIR="${VFC_COMPOSE_DIR:-$ROOT/compose}"

if [ ! -f "$ENV_FILE" ]; then
    echo "Missing env file: $ENV_FILE" >&2
    exit 1
fi

exec docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_DIR/docker-compose.yml" \
    -f "$COMPOSE_DIR/docker-compose.monitoring.yml" \
    "$@"
