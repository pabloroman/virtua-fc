#!/usr/bin/env bash
# Pull a new image tag and roll the app/horizon/scheduler services.
#
# Usage:
#   IMAGE_TAG=<sha> ./deploy.sh
#
# Called both interactively and from .github/workflows/deploy.yml. Records the
# previous IMAGE_TAG to /srv/virtua-fc/env/.previous so rollback.sh can revert.

set -euo pipefail

ROOT="${VFC_ROOT:-/srv/virtua-fc}"
ENV_FILE="${VFC_ENV_FILE:-$ROOT/env/.env}"
PREV_FILE="$ROOT/env/.previous"
COMPOSE="$(dirname "$0")/compose.sh"

if [ -z "${IMAGE_TAG:-}" ]; then
    echo "IMAGE_TAG required" >&2
    exit 1
fi

CURRENT_TAG=$(grep -E '^IMAGE_TAG=' "$ENV_FILE" | cut -d= -f2- || echo "latest")
echo "==> Current tag: $CURRENT_TAG"
echo "==> Deploying tag: $IMAGE_TAG"

# Save the rollback target.
echo "$CURRENT_TAG" > "$PREV_FILE"

# Update the tag in .env atomically.
sed -i.bak "s|^IMAGE_TAG=.*|IMAGE_TAG=$IMAGE_TAG|" "$ENV_FILE"
rm -f "$ENV_FILE.bak"

echo "==> Pulling images"
"$COMPOSE" pull app horizon scheduler

echo "==> Rolling app, horizon, scheduler"
"$COMPOSE" up -d app horizon scheduler

echo "==> Waiting for /up health check"
APP_DOMAIN=$(grep -E '^APP_DOMAIN=' "$ENV_FILE" | cut -d= -f2-)
for i in $(seq 1 30); do
    if curl -fsS --max-time 5 "https://$APP_DOMAIN/up" >/dev/null; then
        echo "==> /up returned 200 (attempt $i)"
        echo "==> Deploy successful: $IMAGE_TAG"
        exit 0
    fi
    sleep 2
done

echo "!! /up did not return 200 within 60s — consider rolling back" >&2
exit 1
