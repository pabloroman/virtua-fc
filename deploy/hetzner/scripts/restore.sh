#!/usr/bin/env bash
# Restore a Postgres dump produced by backup.sh.
#
# Usage:
#   ./restore.sh /srv/virtua-fc/backups/virtua_fc-20260101T030000Z.dump          # destructive
#   ./restore.sh --dry-run /srv/.../foo.dump                                      # list only
#
# DESTRUCTIVE: drops and recreates the target database. Read the prompt before
# typing yes.

set -euo pipefail

ROOT="${VFC_ROOT:-/srv/virtua-fc}"
ENV_FILE="${VFC_ENV_FILE:-$ROOT/env/.env}"
COMPOSE="$(dirname "$0")/compose.sh"

DRY_RUN=0
if [ "${1:-}" = "--dry-run" ]; then
    DRY_RUN=1
    shift
fi

DUMP="${1:-}"
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
    echo "Usage: $0 [--dry-run] <path-to-dump>" >&2
    exit 1
fi

# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a

if [ "$DRY_RUN" -eq 1 ]; then
    echo "==> Listing contents of $DUMP"
    docker run --rm -i postgres:18-alpine pg_restore --list < "$DUMP"
    exit 0
fi

echo "WARNING: this will DROP and recreate database '$DB_DATABASE' on the running"
echo "         postgres container. All current data will be lost."
read -r -p "Type the database name ('$DB_DATABASE') to confirm: " CONFIRM
if [ "$CONFIRM" != "$DB_DATABASE" ]; then
    echo "Aborted." >&2
    exit 1
fi

echo "==> Putting app into maintenance mode"
"$COMPOSE" exec -T app php artisan down --retry=15 --secret=restore-in-progress || true

echo "==> Drop + recreate database"
"$COMPOSE" exec -T postgres psql -U "$DB_USERNAME" -d postgres -c "DROP DATABASE IF EXISTS \"$DB_DATABASE\";"
"$COMPOSE" exec -T postgres psql -U "$DB_USERNAME" -d postgres -c "CREATE DATABASE \"$DB_DATABASE\" OWNER \"$DB_USERNAME\";"

echo "==> Restoring $DUMP (this can take a while)"
"$COMPOSE" exec -T postgres pg_restore -U "$DB_USERNAME" -d "$DB_DATABASE" --no-owner --no-privileges < "$DUMP"

echo "==> Bringing app back up"
"$COMPOSE" exec -T app php artisan up || true

echo "==> Restore complete."
