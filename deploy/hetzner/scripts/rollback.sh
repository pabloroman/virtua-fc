#!/usr/bin/env bash
# Roll back to the previous image tag (recorded by deploy.sh in env/.previous).
# Optionally pass an explicit tag: ./rollback.sh <tag>

set -euo pipefail

ROOT="${VFC_ROOT:-/srv/virtua-fc}"
PREV_FILE="$ROOT/env/.previous"

TARGET="${1:-}"
if [ -z "$TARGET" ]; then
    if [ ! -f "$PREV_FILE" ]; then
        echo "No previous tag recorded at $PREV_FILE — pass one explicitly: ./rollback.sh <tag>" >&2
        exit 1
    fi
    TARGET=$(cat "$PREV_FILE")
fi

echo "==> Rolling back to $TARGET"
IMAGE_TAG="$TARGET" "$(dirname "$0")/deploy.sh"
