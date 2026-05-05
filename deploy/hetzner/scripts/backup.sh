#!/usr/bin/env bash
# Nightly Postgres backup. Writes a custom-format dump locally, ships it to
# the Hetzner Storage Box, prunes anything older than BACKUP_RETENTION_DAYS.
#
# Wire to systemd timer or cron:
#   0 3 * * *  /srv/virtua-fc/scripts/backup.sh >>/var/log/virtua-fc-backup.log 2>&1

set -euo pipefail

ROOT="${VFC_ROOT:-/srv/virtua-fc}"
ENV_FILE="${VFC_ENV_FILE:-$ROOT/env/.env}"
BACKUP_DIR="$ROOT/backups"
COMPOSE="$(dirname "$0")/compose.sh"

# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a

mkdir -p "$BACKUP_DIR"
TS=$(date -u +%Y%m%dT%H%M%SZ)
DUMP="$BACKUP_DIR/${DB_DATABASE}-${TS}.dump"

echo "==> Dumping ${DB_DATABASE} → ${DUMP}"
"$COMPOSE" exec -T postgres pg_dump -U "$DB_USERNAME" -Fc "$DB_DATABASE" > "$DUMP"

# Sanity check — pg_restore --list should succeed on a valid dump.
docker run --rm -i postgres:18-alpine pg_restore --list < "$DUMP" >/dev/null
echo "==> Dump verified ($(du -h "$DUMP" | cut -f1))"

if [ -n "${STORAGEBOX_HOST:-}" ] && [ -n "${STORAGEBOX_USER:-}" ]; then
    echo "==> Shipping to ${STORAGEBOX_USER}@${STORAGEBOX_HOST}:${STORAGEBOX_PATH:-/}"
    rsync -az --partial --timeout=120 \
        -e "ssh -o StrictHostKeyChecking=accept-new" \
        "$DUMP" \
        "${STORAGEBOX_USER}@${STORAGEBOX_HOST}:${STORAGEBOX_PATH:-/}/"
else
    echo "!! STORAGEBOX_HOST/USER not set — skipping offsite copy"
fi

RETAIN="${BACKUP_RETENTION_DAYS:-14}"
echo "==> Pruning local dumps older than ${RETAIN} days"
find "$BACKUP_DIR" -maxdepth 1 -name "${DB_DATABASE}-*.dump" -mtime +"$RETAIN" -print -delete || true

echo "==> Backup OK: $DUMP"
