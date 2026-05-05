#!/usr/bin/env bash
# Open psql against the production database, reading credentials and
# database name from the rendered .env so this stays correct after env
# changes.
#
# Examples:
#   ./psql.sh
#   ./psql.sh -c "SELECT count(*) FROM users;"
#   ./psql.sh -f /tmp/some-script.sql
#
# For a GUI client (TablePlus, pgAdmin, DBeaver, …), use an SSH tunnel
# from your laptop instead — postgres is bound to 127.0.0.1:5432 on the
# host, so:
#   ssh -L 5432:127.0.0.1:5432 deploy@<host>
# then connect to localhost:5432 with the credentials in .env.

set -euo pipefail

ROOT="${VFC_ROOT:-/srv/virtua-fc}"
ENV_FILE="${VFC_ENV_FILE:-$ROOT/env/.env}"

if [ ! -f "$ENV_FILE" ]; then
    echo "Missing env file: $ENV_FILE" >&2
    exit 1
fi

# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a

exec "$(dirname "$0")/compose.sh" exec postgres \
    psql -U "$DB_USERNAME" "$DB_DATABASE" "$@"
